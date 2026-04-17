<?php

declare(strict_types=1);

namespace Hut;

use PDO;

class Auth
{
    private const ALWAYS_ADMIN_EMAILS = [];
    private static ?array $alwaysAdminEmailCache = null;

    /**
     * Return the currently logged-in user array, or null.
     */
    public static function user(): ?array
    {
        return $_SESSION['user'] ?? null;
    }

    /**
     * Return a stable CSRF token for the current session.
     */
    public static function csrfToken(): string
    {
        if (empty($_SESSION['_csrf_token']) || !is_string($_SESSION['_csrf_token'])) {
            $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
        }

        return $_SESSION['_csrf_token'];
    }

    public static function isValidCsrfToken(?string $token): bool
    {
        if (!is_string($token) || $token === '') {
            return false;
        }

        $sessionToken = $_SESSION['_csrf_token'] ?? null;
        if (!is_string($sessionToken) || $sessionToken === '') {
            return false;
        }

        return hash_equals($sessionToken, $token);
    }

    public static function requireCsrf(bool $json = false): void
    {
        $token = $_POST['_csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null);
        if (self::isValidCsrfToken(is_string($token) ? $token : null)) {
            return;
        }

        http_response_code(403);
        if ($json) {
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Invalid CSRF token.']);
            exit;
        }

        $_SESSION['flash_error'] = 'Security check failed. Please retry.';
        require __DIR__ . '/../templates/403.php';
        exit;
    }

    /**
     * Require the user to be logged in; redirect to /login otherwise.
     */
    public static function requireLogin(): void
    {
        if (self::user() === null) {
            $_SESSION['flash_error'] = 'Please log in to continue.';
            header('Location: ' . \Hut\Url::to('/login'));
            exit;
        }
    }

    /**
     * Require the user to be an admin; send 403 otherwise.
     */
    public static function requireAdmin(): void
    {
        self::requireLogin();
        if (empty(self::user()['is_admin'])) {
            http_response_code(403);
            require __DIR__ . '/../templates/403.php';
            exit;
        }
    }

    /**
     * Attempt local login. Returns user array on success, null on failure.
     */
    public static function attemptLogin(string $email, string $password): ?array
    {
        $pdo  = Database::getInstance();
        $stmt = $pdo->prepare('SELECT * FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([strtolower(trim($email))]);
        $user = $stmt->fetch();

        if (!$user || !$user['password_hash']) {
            return null;
        }
        if (!password_verify($password, $user['password_hash'])) {
            return null;
        }

        return $user;
    }

    /**
     * Register a new local user. Returns user array on success.
     * Throws \RuntimeException if email already taken.
     */
    public static function register(string $name, string $email, string $password): array
    {
        $pdo  = Database::getInstance();
        $email = strtolower(trim($email));

        $check = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
        $check->execute([$email]);
        if ($check->fetch()) {
            throw new \RuntimeException('Email already registered.');
        }

        $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
        $isAdmin = self::isAlwaysAdminEmail($email) ? 1 : 0;
        $stmt = $pdo->prepare(
            'INSERT INTO users (name, email, password_hash, is_admin) VALUES (?, ?, ?, ?)'
        );
        $stmt->execute([trim($name), $email, $hash, $isAdmin]);
        $id = (int) $pdo->lastInsertId();

        $user = $pdo->prepare('SELECT * FROM users WHERE id = ?');
        $user->execute([$id]);
        return $user->fetch();
    }

    /**
     * Find or create a user from a Google account.
     */
    public static function findOrCreateGoogleUser(string $googleId, string $email, string $name): array
    {
        $pdo = Database::getInstance();
        $email = strtolower(trim($email));
        $forceAdmin = self::isAlwaysAdminEmail($email);

        // Try by google_id first
        $stmt = $pdo->prepare('SELECT * FROM users WHERE google_id = ? LIMIT 1');
        $stmt->execute([$googleId]);
        $user = $stmt->fetch();
        if ($user) {
            if ($forceAdmin && !(bool) $user['is_admin']) {
                $pdo->prepare('UPDATE users SET is_admin = 1 WHERE id = ?')->execute([$user['id']]);
                $user['is_admin'] = 1;
            }
            return $user;
        }

        // Try by email (link accounts)
        $stmt = $pdo->prepare('SELECT * FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        if ($user) {
            if ($forceAdmin) {
                $pdo->prepare('UPDATE users SET google_id = ?, is_admin = 1 WHERE id = ?')
                    ->execute([$googleId, $user['id']]);
                $user['is_admin'] = 1;
            } else {
                $pdo->prepare('UPDATE users SET google_id = ? WHERE id = ?')
                    ->execute([$googleId, $user['id']]);
            }
            $user['google_id'] = $googleId;
            return $user;
        }

        // Create new
        $stmt = $pdo->prepare(
            'INSERT INTO users (name, email, google_id, is_admin) VALUES (?, ?, ?, ?)'
        );
        $stmt->execute([$name, $email, $googleId, $forceAdmin ? 1 : 0]);
        $id = (int) $pdo->lastInsertId();
        $stmt = $pdo->prepare('SELECT * FROM users WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch();
    }

    /**
     * Persist user in session.
     */
    public static function login(array $user): void
    {
        $isAdmin = (bool) $user['is_admin'];
        if (self::isAlwaysAdminEmail((string) ($user['email'] ?? ''))) {
            $isAdmin = true;
            if (!empty($user['id']) && !(bool) $user['is_admin']) {
                Database::getInstance()->prepare('UPDATE users SET is_admin = 1 WHERE id = ?')->execute([$user['id']]);
            }
        }

        session_regenerate_id(true);
        $_SESSION['user'] = [
            'id'       => $user['id'],
            'name'     => $user['name'],
            'email'    => $user['email'],
            'is_admin' => $isAdmin,
        ];

        // Rotate CSRF token after privilege-bearing auth changes.
        $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
    }

    private static function isAlwaysAdminEmail(string $email): bool
    {
        $normalized = strtolower(trim($email));
        return in_array($normalized, self::alwaysAdminEmails(), true);
    }

    /**
     * @return array<int, string>
     */
    private static function alwaysAdminEmails(): array
    {
        if (self::$alwaysAdminEmailCache !== null) {
            return self::$alwaysAdminEmailCache;
        }

        $configured = trim((string) ($_ENV['ALWAYS_ADMIN_EMAILS'] ?? ''));
        if ($configured === '') {
            self::$alwaysAdminEmailCache = self::ALWAYS_ADMIN_EMAILS;
            return self::$alwaysAdminEmailCache;
        }

        $emails = array_values(array_filter(array_map(
            static fn (string $value): string => strtolower(trim($value)),
            explode(',', $configured)
        ), static fn (string $value): bool => $value !== ''));

        self::$alwaysAdminEmailCache = array_values(array_unique($emails));
        return self::$alwaysAdminEmailCache;
    }

    public static function logout(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], (bool) $params['secure'], (bool) $params['httponly']);
        }
        session_destroy();
    }

    /**
     * Return only the first name (first word) from a full name.
     */
    public static function firstName(string $name): string
    {
        $trimmed = trim($name);
        $space   = strpos($trimmed, ' ');
        return $space !== false ? substr($trimmed, 0, $space) : $trimmed;
    }

    /**
     * Apply firstName() to each name in a comma-separated list and rejoin.
     */
    public static function firstNames(string $names, string $separator = ', '): string
    {
        $parts = array_map(
            static fn (string $n): string => self::firstName($n),
            explode($separator, $names)
        );
        return implode($separator, $parts);
    }
}
