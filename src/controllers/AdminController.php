<?php

declare(strict_types=1);

namespace Hut\controllers;

use Hut\Auth;
use Hut\Database;
use Hut\GameImporter;

class AdminController
{
    private const IMPORT_JOB_DIR = '/storage/import_jobs';
    private const IMPORT_JOB_RETENTION_SECONDS = 172800;

    public static function showAdmin(array $params): void
    {
        Auth::requireAdmin();

        $pdo = Database::getInstance();
        $configuredCollectionUsers = self::configuredCollectionUsers();
        $collectionRowCount = (int) $pdo->query('SELECT COUNT(*) FROM user_collection')->fetchColumn();

        $usersStmt = $pdo->query(
            'SELECT
                u.id,
                u.name,
                u.email,
                u.is_admin,
                u.is_approved,
                u.created_at,
                (SELECT COUNT(*) FROM user_games ug WHERE ug.user_id = u.id AND ug.selected = 1) AS selected_games,
                (SELECT COUNT(*) FROM votes v WHERE v.user_id = u.id) AS votes_count
            FROM users u
            ORDER BY u.created_at DESC, u.id DESC'
        );
        $users = $usersStmt->fetchAll();

        require __DIR__ . '/../../templates/admin/import.php';
    }

    public static function showImport(array $params): void
    {
        self::showAdmin($params);
    }

    public static function doImport(array $params): void
    {
        Auth::requireAdmin();
        Auth::requireCsrf();

        $file = $_FILES['bgg_file'] ?? null;
        if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
            $_SESSION['flash_error'] = 'No file uploaded or upload error.';
            header('Location: ' . \Hut\Url::to('/admin/import')); exit;
        }

        $filename = strtolower((string) ($file['name'] ?? ''));

        // Validate MIME type / extension — CSV preferred, XML still supported.
        $finfo    = new \finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($file['tmp_name']);
        $isCsv = str_ends_with($filename, '.csv') || in_array($mimeType, ['text/csv', 'text/plain', 'application/vnd.ms-excel'], true);
        $isXml = str_ends_with($filename, '.xml') || in_array($mimeType, ['text/xml', 'application/xml'], true);
        if (!$isCsv && !$isXml) {
            $_SESSION['flash_error'] = 'File must be a BGG CSV export or XML export.';
            header('Location: ' . \Hut\Url::to('/admin/import')); exit;
        }

        // Enforce a 50 MB limit
        if ($file['size'] > 50 * 1024 * 1024) {
            $_SESSION['flash_error'] = 'File too large (max 50 MB).';
            header('Location: ' . \Hut\Url::to('/admin/import')); exit;
        }

        try {
            $content = file_get_contents($file['tmp_name']);
            $importer = new GameImporter();
            $count = $isCsv ? $importer->importCsv($content) : $importer->importXml($content);
            $_SESSION['flash_success'] = "Import complete: $count games imported/updated.";
        } catch (\RuntimeException $e) {
            error_log('Admin import failed: ' . $e->getMessage());
            $_SESSION['flash_error'] = 'Import failed. Check server logs for details.';
        }

        header('Location: ' . \Hut\Url::to('/admin')); exit;
    }

    public static function startImport(array $params): void
    {
        Auth::requireAdmin();
        Auth::requireCsrf(true);

        self::cleanupImportJobStorage();

        $file = $_FILES['bgg_file'] ?? null;
        if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
            self::jsonResponse(['error' => 'No file uploaded or upload error.'], 400);
        }

        $filename = strtolower((string) ($file['name'] ?? ''));
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($file['tmp_name']);

        $isCsv = str_ends_with($filename, '.csv') || in_array($mimeType, ['text/csv', 'text/plain', 'application/vnd.ms-excel'], true);
        $isXml = str_ends_with($filename, '.xml') || in_array($mimeType, ['text/xml', 'application/xml'], true);

        if (!$isCsv && !$isXml) {
            self::jsonResponse(['error' => 'File must be a BGG CSV export or XML export.'], 400);
        }

        if ((int) ($file['size'] ?? 0) > 50 * 1024 * 1024) {
            self::jsonResponse(['error' => 'File too large (max 50 MB).'], 400);
        }

        $jobId = bin2hex(random_bytes(16));
        $jobDir = self::importJobDir();
        $extension = $isCsv ? 'csv' : 'xml';
        $storedFilePath = $jobDir . '/' . $jobId . '.' . $extension;

        if (!move_uploaded_file((string) $file['tmp_name'], $storedFilePath)) {
            self::jsonResponse(['error' => 'Failed to store uploaded file.'], 500);
        }

        $importer = new GameImporter();
        try {
            $inspection = $isCsv
                ? $importer->inspectCsvFile($storedFilePath)
                : $importer->inspectXmlFile($storedFilePath);
        } catch (\Throwable $e) {
            @unlink($storedFilePath);
            error_log('Admin async import inspect failed: ' . $e->getMessage());
            self::jsonResponse(['error' => 'Failed to read import file.'], 400);
        }

        $currentUser = Auth::user();
        $jobState = [
            'id' => $jobId,
            'user_id' => (int) ($currentUser['id'] ?? 0),
            'type' => $isCsv ? 'csv' : 'xml',
            'runner' => 'background',
            'status' => 'queued',
            'file_path' => $storedFilePath,
            'total' => (int) ($inspection['total'] ?? 0),
            'processed' => 0,
            'imported' => 0,
            'skipped' => 0,
            'offset' => (int) ($inspection['start_offset'] ?? 0),
            'batch_size' => GameImporter::DEFAULT_BATCH_SIZE,
            'message' => null,
            'created_at' => date('c'),
            'updated_at' => date('c'),
            'first_error' => null,
        ];

        self::writeImportJobState($jobId, $jobState);

        if (!self::launchImportWorker($jobId)) {
            // Fallback mode for hosts where detached execution is disabled.
            $jobState['runner'] = 'polling';
            $jobState['status'] = 'queued';
            $jobState['message'] = 'Background worker unavailable. Import will run while this page stays open.';
            $jobState['updated_at'] = date('c');
            self::writeImportJobState($jobId, $jobState);
        }

        self::jsonResponse(self::importJobPayload($jobState));
    }

    public static function processImport(array $params): void
    {
        Auth::requireAdmin();
        Auth::requireCsrf(true);

        $jobId = self::postedJobId();
        if ($jobId === '') {
            self::jsonResponse(['error' => 'Missing import job id.'], 400);
        }

        $jobState = self::readImportJobState($jobId);
        if ($jobState === null) {
            self::jsonResponse(['error' => 'Import job not found.'], 404);
        }

        if (!self::isOwnedByCurrentAdmin($jobState)) {
            self::jsonResponse(['error' => 'Import job not accessible.'], 403);
        }

        if (!in_array((string) ($jobState['status'] ?? ''), ['completed', 'failed'], true)) {
            if ((string) ($jobState['runner'] ?? 'background') === 'polling') {
                self::runImportJobBatchById($jobId);
            } else {
                self::launchImportWorker($jobId);
            }
        }

        $latestState = self::readImportJobState($jobId) ?? $jobState;
        self::jsonResponse(self::importJobPayload($latestState));
    }

    public static function importStatus(array $params): void
    {
        Auth::requireAdmin();

        self::cleanupImportJobStorage();

        $jobId = trim((string) ($_GET['job_id'] ?? ''));
        if ($jobId === '') {
            self::jsonResponse(['error' => 'Missing import job id.'], 400);
        }

        $jobState = self::readImportJobState($jobId);
        if ($jobState === null) {
            self::jsonResponse(['error' => 'Import job not found.'], 404);
        }

        if (!self::isOwnedByCurrentAdmin($jobState)) {
            self::jsonResponse(['error' => 'Import job not accessible.'], 403);
        }

        self::jsonResponse(self::importJobPayload($jobState));
    }

    public static function runImportJobById(string $jobId): void
    {
        $jobState = self::readImportJobState($jobId);
        if ($jobState === null) {
            return;
        }

        if (in_array((string) ($jobState['status'] ?? ''), ['completed', 'failed'], true)) {
            return;
        }

        $lockPath = self::importJobLockPath($jobId);
        $lockHandle = fopen($lockPath, 'c');
        if ($lockHandle === false) {
            return;
        }

        if (!flock($lockHandle, LOCK_EX | LOCK_NB)) {
            fclose($lockHandle);
            return;
        }

        try {
            $jobState = self::readImportJobState($jobId);
            if ($jobState === null) {
                return;
            }

            if (in_array((string) ($jobState['status'] ?? ''), ['completed', 'failed'], true)) {
                return;
            }

            $jobState['status'] = 'running';
            $jobState['message'] = 'Import is running in the background.';
            $jobState['updated_at'] = date('c');
            self::writeImportJobState($jobId, $jobState);

            $importer = new GameImporter();
            $batchSize = max(1, (int) ($jobState['batch_size'] ?? GameImporter::DEFAULT_BATCH_SIZE));
            $filePath = (string) ($jobState['file_path'] ?? '');

            while (true) {
                $jobState = self::readImportJobState($jobId);
                if ($jobState === null) {
                    break;
                }

                $jobState = self::applySingleBatch($jobState, $importer, $batchSize, $filePath);
                self::writeImportJobState($jobId, $jobState);
                if ((string) ($jobState['status'] ?? '') === 'completed') {
                    break;
                }
            }
        } catch (\Throwable $e) {
            $jobState = self::readImportJobState($jobId);
            if ($jobState !== null) {
                $jobState['status'] = 'failed';
                $jobState['message'] = 'Import failed. Check server logs for details.';
                $jobState['updated_at'] = date('c');
                self::writeImportJobState($jobId, $jobState);
            }
            error_log('Admin async import worker failed: ' . $e->getMessage());
        } finally {
            flock($lockHandle, LOCK_UN);
            fclose($lockHandle);
            @unlink($lockPath);
        }
    }

    public static function deleteUser(array $params): void
    {
        Auth::requireAdmin();
        Auth::requireCsrf();

        $id = isset($params['id']) ? (int) $params['id'] : 0;
        if ($id <= 0) {
            $_SESSION['flash_error'] = 'Invalid user id.';
            header('Location: ' . \Hut\Url::to('/admin')); exit;
        }

        $currentUser = Auth::user();
        if ($currentUser !== null && (int) $currentUser['id'] === $id) {
            $_SESSION['flash_error'] = 'You cannot delete your own account.';
            header('Location: ' . \Hut\Url::to('/admin')); exit;
        }

        $pdo = Database::getInstance();
        $userStmt = $pdo->prepare('SELECT id, name, email FROM users WHERE id = ? LIMIT 1');
        $userStmt->execute([$id]);
        $user = $userStmt->fetch();

        if (!$user) {
            $_SESSION['flash_error'] = 'User not found.';
            header('Location: ' . \Hut\Url::to('/admin')); exit;
        }

        try {
            $pdo->beginTransaction();

            // Explicit cleanup keeps behavior correct even if FK cascades are unavailable.
            $deleteUserGamesStmt = $pdo->prepare('DELETE FROM user_games WHERE user_id = ?');
            $deleteUserGamesStmt->execute([$id]);

            $deleteVotesStmt = $pdo->prepare('DELETE FROM votes WHERE user_id = ?');
            $deleteVotesStmt->execute([$id]);

            $deleteUserStmt = $pdo->prepare('DELETE FROM users WHERE id = ?');
            $deleteUserStmt->execute([$id]);

            if ($deleteUserStmt->rowCount() < 1) {
                $pdo->rollBack();
                $_SESSION['flash_error'] = 'User could not be deleted.';
                header('Location: ' . \Hut\Url::to('/admin')); exit;
            }

            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('Admin delete user failed: ' . $e->getMessage());
            $_SESSION['flash_error'] = 'Delete failed. Please try again.';
            header('Location: ' . \Hut\Url::to('/admin')); exit;
        }

        $_SESSION['flash_success'] = 'Deleted user: ' . $user['name'] . ' (' . $user['email'] . ') including selected games and votes.';
        header('Location: ' . \Hut\Url::to('/admin')); exit;
    }

    public static function approveUser(array $params): void
    {
        Auth::requireAdmin();
        Auth::requireCsrf();

        $id = isset($params['id']) ? (int) $params['id'] : 0;
        if ($id <= 0) {
            $_SESSION['flash_error'] = 'Invalid user id.';
            header('Location: ' . \Hut\Url::to('/admin')); exit;
        }

        $pdo = Database::getInstance();
        $userStmt = $pdo->prepare('SELECT id, name, email, is_approved FROM users WHERE id = ? LIMIT 1');
        $userStmt->execute([$id]);
        $user = $userStmt->fetch();

        if (!$user) {
            $_SESSION['flash_error'] = 'User not found.';
            header('Location: ' . \Hut\Url::to('/admin')); exit;
        }

        // Update approval status
        $updateStmt = $pdo->prepare('UPDATE users SET is_approved = 1 WHERE id = ?');
        $updateStmt->execute([$id]);

        $_SESSION['flash_success'] = 'Approved user: ' . $user['name'] . ' (' . $user['email'] . ')';
        header('Location: ' . \Hut\Url::to('/admin')); exit;
    }

    public static function disapproveUser(array $params): void
    {
        Auth::requireAdmin();
        Auth::requireCsrf();

        $id = isset($params['id']) ? (int) $params['id'] : 0;
        if ($id <= 0) {
            $_SESSION['flash_error'] = 'Invalid user id.';
            header('Location: ' . \Hut\Url::to('/admin')); exit;
        }

        $currentUser = Auth::user();
        if ($currentUser !== null && (int) $currentUser['id'] === $id) {
            $_SESSION['flash_error'] = 'You cannot disapprove your own account.';
            header('Location: ' . \Hut\Url::to('/admin')); exit;
        }

        $pdo = Database::getInstance();
        $userStmt = $pdo->prepare('SELECT id, name, email, is_approved FROM users WHERE id = ? LIMIT 1');
        $userStmt->execute([$id]);
        $user = $userStmt->fetch();

        if (!$user) {
            $_SESSION['flash_error'] = 'User not found.';
            header('Location: ' . \Hut\Url::to('/admin')); exit;
        }

        // Update approval status
        $updateStmt = $pdo->prepare('UPDATE users SET is_approved = 0 WHERE id = ?');
        $updateStmt->execute([$id]);

        $_SESSION['flash_success'] = 'Disapproved user: ' . $user['name'] . ' (' . $user['email'] . ')';
        header('Location: ' . \Hut\Url::to('/admin')); exit;
    }

    public static function fetchConfiguredCollections(array $params): void
    {
        Auth::requireAdmin();
        Auth::requireCsrf();

        $bggUsers = self::configuredCollectionUsers();
        if (empty($bggUsers)) {
            $_SESSION['flash_error'] = 'No BGG users configured. Set USER_COLLECTION (or USER_COLLECTIONS) in .env as comma-separated usernames.';
            header('Location: ' . \Hut\Url::to('/admin')); exit;
        }

        $fetched = [];
        $errors = [];
        foreach ($bggUsers as $bggUser) {
            try {
                $fetched[$bggUser] = self::fetchOwnedGameIdsForUser($bggUser);
            } catch (\Throwable $e) {
                $errors[] = $bggUser . ': ' . $e->getMessage();
            }
        }

        if (empty($fetched)) {
            $_SESSION['flash_error'] = 'Fetching collections failed for all configured users. ' . implode(' | ', $errors);
            header('Location: ' . \Hut\Url::to('/admin')); exit;
        }

        $pdo = Database::getInstance();
        $driver = (string) $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);
        $processedUsers = 0;
        $insertedRows = 0;

        try {
            $pdo->beginTransaction();

            $deleteStmt = $pdo->prepare('DELETE FROM user_collection WHERE bgg_user = ?');
            $insertSql = $driver === 'mysql'
                ? 'INSERT IGNORE INTO user_collection (bgg_game_id, bgg_user) VALUES (?, ?)'
                : 'INSERT OR IGNORE INTO user_collection (bgg_game_id, bgg_user) VALUES (?, ?)';
            $insertStmt = $pdo->prepare($insertSql);

            foreach ($fetched as $bggUser => $gameIds) {
                $deleteStmt->execute([$bggUser]);
                foreach ($gameIds as $gameId) {
                    $insertStmt->execute([$gameId, $bggUser]);
                    $insertedRows++;
                }
                $processedUsers++;
            }

            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('Admin fetch collections write failed: ' . $e->getMessage());
            $_SESSION['flash_error'] = 'Failed to update user collection rows.';
            header('Location: ' . \Hut\Url::to('/admin')); exit;
        }

        $success = 'Fetched collections for ' . $processedUsers . ' user(s) and stored ' . $insertedRows . ' row(s) in user_collection.';
        if (!empty($errors)) {
            $_SESSION['flash_error'] = 'Some users failed: ' . implode(' | ', $errors);
        }
        $_SESSION['flash_success'] = $success;
        header('Location: ' . \Hut\Url::to('/admin')); exit;
    }

    /**
     * @return array<int, string>
     */
    private static function configuredCollectionUsers(): array
    {
        $raw = self::firstNonEmptyEnvValue(['USER_COLLECTION', 'USER_COLLECTIONS']);
        if ($raw === '') {
            return [];
        }

        $parts = array_map(
            static fn (string $value): string => trim($value),
            explode(',', $raw)
        );
        $parts = array_values(array_filter($parts, static fn (string $value): bool => $value !== ''));

        return array_values(array_unique($parts));
    }

    /**
     * @param array<int, string> $keys
     */
    private static function firstNonEmptyEnvValue(array $keys): string
    {
        foreach ($keys as $key) {
            if (isset($_ENV[$key])) {
                $value = trim((string) $_ENV[$key]);
                if ($value !== '') {
                    return $value;
                }
            }

            if (isset($_SERVER[$key])) {
                $value = trim((string) $_SERVER[$key]);
                if ($value !== '') {
                    return $value;
                }
            }

            $value = getenv($key);
            if (is_string($value)) {
                $value = trim($value);
                if ($value !== '') {
                    return $value;
                }
            }
        }

        $fromFile = self::firstNonEmptyDotenvFileValue($keys);
        if ($fromFile !== '') {
            return $fromFile;
        }

        return '';
    }

    /**
     * @param array<int, string> $keys
     */
    private static function firstNonEmptyDotenvFileValue(array $keys): string
    {
        $envFile = dirname(__DIR__, 2) . '/.env';
        if (!is_readable($envFile)) {
            return '';
        }

        $lookup = array_fill_keys($keys, true);
        $lines = file($envFile, FILE_IGNORE_NEW_LINES);
        if ($lines === false) {
            return '';
        }

        foreach ($lines as $line) {
            $line = trim((string) $line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }
            if (str_starts_with($line, 'export ')) {
                $line = trim(substr($line, 7));
            }

            $parts = explode('=', $line, 2);
            if (count($parts) !== 2) {
                continue;
            }

            $key = trim($parts[0]);
            if (!isset($lookup[$key])) {
                continue;
            }

            $value = trim($parts[1]);
            if ($value === '') {
                continue;
            }

            $isQuoted = (str_starts_with($value, '"') && str_ends_with($value, '"'))
                || (str_starts_with($value, "'") && str_ends_with($value, "'"));
            if ($isQuoted && strlen($value) >= 2) {
                $value = substr($value, 1, -1);
            }

            $value = trim($value);
            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }

    /**
     * @return array<int, int>
     */
    private static function fetchOwnedGameIdsForUser(string $bggUser): array
    {
        if (!preg_match('/^[A-Za-z0-9_-]{2,64}$/', $bggUser)) {
            throw new \RuntimeException('Invalid BGG username format.');
        }

        $url = 'https://boardgamegeek.com/xmlapi2/collection?own=1&username=' . rawurlencode($bggUser);
        $body = self::httpGetWithRetries($url);

        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($body, 'SimpleXMLElement', LIBXML_NONET | LIBXML_NOCDATA);
        if ($xml === false) {
            throw new \RuntimeException('Invalid XML response from BGG.');
        }

        $ids = [];
        foreach ($xml->item as $item) {
            $id = (int) ($item['objectid'] ?? 0);
            if ($id <= 0) {
                continue;
            }

            // Only include games where status own="1"
            $status = $item->status;
            $ownedFlag = (int) ($status['own'] ?? 0);
            if ($ownedFlag === 1) {
                $ids[$id] = true;
            }
        }

        return array_keys($ids);
    }

    private static function httpGetWithRetries(string $url): string
    {
        $backoffSeconds = 1;
        $maxAttempts = 5;
        $bggToken = self::firstNonEmptyEnvValue(['BGG_TOKEN']);

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            $ch = curl_init($url);
            $headers = [];
            if ($bggToken !== '') {
                $headers[] = 'Authorization: Bearer ' . $bggToken;
            }

            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_CONNECTTIMEOUT => 10,
                CURLOPT_TIMEOUT => 60,
                CURLOPT_USERAGENT => 'Hut/1.0 (+https://localhost)',
                CURLOPT_HTTPHEADER => $headers,
            ]);

            if (defined('CURLOPT_PROTOCOLS') && defined('CURLPROTO_HTTPS')) {
                curl_setopt($ch, CURLOPT_PROTOCOLS, CURLPROTO_HTTPS);
            }
            if (defined('CURLOPT_REDIR_PROTOCOLS') && defined('CURLPROTO_HTTPS')) {
                curl_setopt($ch, CURLOPT_REDIR_PROTOCOLS, CURLPROTO_HTTPS);
            }

            $body = curl_exec($ch);
            $statusCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);

            if (is_string($body) && $statusCode === 200) {
                return $body;
            }

            $retryable = in_array($statusCode, [202, 429, 503], true) || $body === false;
            if ($retryable && $attempt < $maxAttempts) {
                sleep($backoffSeconds);
                $backoffSeconds *= 2;
                continue;
            }

            $statusInfo = $statusCode > 0 ? ('HTTP ' . $statusCode) : 'network error';
            $message = $curlError !== '' ? $curlError : $statusInfo;
            throw new \RuntimeException('BGG request failed: ' . $message);
        }

        throw new \RuntimeException('BGG request failed after retries.');
    }

    private static function jsonResponse(array $payload, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json');
        echo json_encode($payload);
        exit;
    }

    private static function importJobDir(): string
    {
        $dir = dirname(__DIR__, 2) . self::IMPORT_JOB_DIR;
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        if (!is_dir($dir) || !is_writable($dir)) {
            throw new \RuntimeException('Import job storage directory is not writable.');
        }

        return $dir;
    }

    private static function importJobStatePath(string $jobId): string
    {
        return self::importJobDir() . '/' . $jobId . '.json';
    }

    private static function importJobLockPath(string $jobId): string
    {
        return self::importJobDir() . '/' . $jobId . '.lock';
    }

    private static function writeImportJobState(string $jobId, array $state): void
    {
        $encoded = json_encode($state, JSON_PRETTY_PRINT);
        if (!is_string($encoded) || file_put_contents(self::importJobStatePath($jobId), $encoded, LOCK_EX) === false) {
            throw new \RuntimeException('Failed to persist import job state.');
        }
    }

    private static function readImportJobState(string $jobId): ?array
    {
        if (!preg_match('/^[a-f0-9]{32}$/', $jobId)) {
            return null;
        }

        $path = self::importJobStatePath($jobId);
        if (!is_file($path)) {
            return null;
        }

        $json = file_get_contents($path);
        if (!is_string($json) || $json === '') {
            return null;
        }

        $state = json_decode($json, true);
        return is_array($state) ? $state : null;
    }

    private static function postedJobId(): string
    {
        return trim((string) ($_POST['job_id'] ?? ''));
    }

    private static function isOwnedByCurrentAdmin(array $jobState): bool
    {
        $currentUser = Auth::user();
        if ($currentUser === null) {
            return false;
        }

        return (int) ($jobState['user_id'] ?? 0) === (int) $currentUser['id'];
    }

    private static function importJobPayload(array $jobState): array
    {
        $total = max(0, (int) ($jobState['total'] ?? 0));
        $processed = max(0, (int) ($jobState['processed'] ?? 0));
        $percent = $total > 0 ? (int) floor(min(100, ($processed / $total) * 100)) : 0;
        if ((string) ($jobState['status'] ?? '') === 'completed') {
            $percent = 100;
        }

        return [
            'job_id' => (string) ($jobState['id'] ?? ''),
            'runner' => (string) ($jobState['runner'] ?? 'background'),
            'status' => (string) ($jobState['status'] ?? 'unknown'),
            'type' => (string) ($jobState['type'] ?? 'csv'),
            'total' => $total,
            'processed' => $processed,
            'imported' => max(0, (int) ($jobState['imported'] ?? 0)),
            'skipped' => max(0, (int) ($jobState['skipped'] ?? 0)),
            'percent' => $percent,
            'message' => $jobState['message'] ?? null,
            'first_error' => $jobState['first_error'] ?? null,
            'updated_at' => (string) ($jobState['updated_at'] ?? ''),
        ];
    }

    private static function launchImportWorker(string $jobId): bool
    {
        $workerScript = dirname(__DIR__, 2) . '/bin/import_worker.php';
        if (!is_file($workerScript)) {
            return false;
        }

        $phpBinary = PHP_BINARY !== '' ? PHP_BINARY : 'php';
        $command = escapeshellarg($phpBinary)
            . ' ' . escapeshellarg($workerScript)
            . ' ' . escapeshellarg($jobId)
            . ' > /dev/null 2>&1 &';

        if (!function_exists('exec')) {
            return false;
        }

        $output = [];
        $exitCode = 1;
        @exec($command, $output, $exitCode);

        return $exitCode === 0;
    }

    private static function runImportJobBatchById(string $jobId): void
    {
        $jobState = self::readImportJobState($jobId);
        if ($jobState === null) {
            return;
        }

        if (in_array((string) ($jobState['status'] ?? ''), ['completed', 'failed'], true)) {
            return;
        }

        $lockPath = self::importJobLockPath($jobId);
        $lockHandle = fopen($lockPath, 'c');
        if ($lockHandle === false) {
            return;
        }

        if (!flock($lockHandle, LOCK_EX | LOCK_NB)) {
            fclose($lockHandle);
            return;
        }

        try {
            $jobState = self::readImportJobState($jobId);
            if ($jobState === null || in_array((string) ($jobState['status'] ?? ''), ['completed', 'failed'], true)) {
                return;
            }

            if ((string) ($jobState['status'] ?? '') === 'queued') {
                $jobState['status'] = 'running';
                $jobState['message'] = 'Import is running via browser polling.';
                $jobState['updated_at'] = date('c');
            }

            $importer = new GameImporter();
            $batchSize = max(1, (int) ($jobState['batch_size'] ?? GameImporter::DEFAULT_BATCH_SIZE));
            $filePath = (string) ($jobState['file_path'] ?? '');

            $jobState = self::applySingleBatch($jobState, $importer, $batchSize, $filePath);
            self::writeImportJobState($jobId, $jobState);
        } catch (\Throwable $e) {
            $jobState = self::readImportJobState($jobId);
            if ($jobState !== null) {
                $jobState['status'] = 'failed';
                $jobState['message'] = 'Import failed. Check server logs for details.';
                $jobState['updated_at'] = date('c');
                self::writeImportJobState($jobId, $jobState);
            }
            error_log('Admin async polling batch failed: ' . $e->getMessage());
        } finally {
            flock($lockHandle, LOCK_UN);
            fclose($lockHandle);
            @unlink($lockPath);
        }
    }

    private static function applySingleBatch(array $jobState, GameImporter $importer, int $batchSize, string $filePath): array
    {
        $offset = (int) ($jobState['offset'] ?? 0);
        $result = ((string) ($jobState['type'] ?? 'csv')) === 'xml'
            ? $importer->importXmlBatchFromFile($filePath, $offset, $batchSize)
            : $importer->importCsvBatchFromFile($filePath, $offset, $batchSize);

        $jobState['processed'] = (int) ($jobState['processed'] ?? 0) + (int) ($result['processed'] ?? 0);
        $jobState['imported'] = (int) ($jobState['imported'] ?? 0) + (int) ($result['imported'] ?? 0);
        $jobState['skipped'] = (int) ($jobState['skipped'] ?? 0) + (int) ($result['skipped'] ?? 0);
        $jobState['offset'] = (int) ($result['next_offset'] ?? $offset);
        $jobState['updated_at'] = date('c');

        if (($jobState['first_error'] ?? null) === null && ($result['first_error'] ?? null) !== null) {
            $jobState['first_error'] = (string) $result['first_error'];
        }

        if ((bool) ($result['done'] ?? false)) {
            $jobState['status'] = 'completed';
            $jobState['message'] = 'Import complete: ' . (int) ($jobState['imported'] ?? 0) . ' imported, ' . (int) ($jobState['skipped'] ?? 0) . ' skipped.';
            @unlink((string) ($jobState['file_path'] ?? ''));
        }

        return $jobState;
    }

    private static function cleanupImportJobStorage(): void
    {
        $dir = self::importJobDir();
        $now = time();
        $threshold = $now - self::IMPORT_JOB_RETENTION_SECONDS;

        $stateFiles = glob($dir . '/*.json');
        if ($stateFiles === false) {
            return;
        }

        foreach ($stateFiles as $stateFile) {
            $mtime = filemtime($stateFile);
            if ($mtime === false || $mtime >= $threshold) {
                continue;
            }

            $json = file_get_contents($stateFile);
            $state = is_string($json) ? json_decode($json, true) : null;
            if (is_array($state)) {
                $filePath = (string) ($state['file_path'] ?? '');
                if ($filePath !== '' && is_file($filePath)) {
                    @unlink($filePath);
                }
                $jobId = trim((string) ($state['id'] ?? ''));
                if ($jobId !== '') {
                    @unlink(self::importJobLockPath($jobId));
                }
            }

            @unlink($stateFile);
        }

        $payloadFiles = array_merge(glob($dir . '/*.csv') ?: [], glob($dir . '/*.xml') ?: []);
        foreach ($payloadFiles as $payloadFile) {
            $mtime = filemtime($payloadFile);
            if ($mtime !== false && $mtime < $threshold) {
                @unlink($payloadFile);
            }
        }

        $lockFiles = glob($dir . '/*.lock');
        if ($lockFiles !== false) {
            foreach ($lockFiles as $lockFile) {
                $mtime = filemtime($lockFile);
                if ($mtime !== false && $mtime < $threshold) {
                    @unlink($lockFile);
                }
            }
        }
    }
}
