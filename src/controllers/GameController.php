<?php

declare(strict_types=1);

namespace Hut\controllers;

use Hut\Auth;
use Hut\BggThingFetcher;
use Hut\Game;
use Hut\PersonalCollection;
use Hut\UserGame;

class GameController
{
    public static function browse(array $params): void
    {
        Auth::requireLogin();

        $search = trim($_GET['q'] ?? '');
        $complexity = (string) ($_GET['complexity'] ?? '');
        if (!in_array($complexity, ['', 'light', 'medium', 'complex'], true)) {
            $complexity = '';
        }
        $selectedUsers = isset($_GET['users']) && is_array($_GET['users'])
            ? array_map('strval', array_filter($_GET['users']))
            : [];
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $browseComplexity = $search === '' ? $complexity : '';

        // Offer all known owner labels from BGG imports and manual personal ownership.
        $pdo = \Hut\Database::getInstance();
        $usersStmt = $pdo->query(
            'SELECT owner_name
             FROM (
                 SELECT bgg_user AS owner_name FROM user_collection
                 UNION
                 SELECT u.name AS owner_name
                 FROM user_personal_collection upc
                 JOIN users u ON u.id = upc.user_id
             ) owners
             ORDER BY owner_name'
        );
        $availableUsers = array_map(static fn ($row) => $row['owner_name'], $usersStmt->fetchAll(\PDO::FETCH_ASSOC));

        $userId = (int) Auth::user()['id'];

        $isDefaultView = $search === '' && empty($selectedUsers);
        if ($isDefaultView) {
            $games = Game::randomCollectionGames(24, $userId, $browseComplexity);
            $total = count($games);
            $perPage = 24;
        } else {
            $result = Game::browse($search, $selectedUsers, $page, 24, $userId, $browseComplexity);
            extract($result); // $games, $total, $page, $perPage
        }

        $complexity = $browseComplexity;

        BggThingFetcher::ensureForPage(array_column($games, 'id'));
        require __DIR__ . '/../../templates/games/browse.php';
    }

    public static function suggestions(array $params): void
    {
        Auth::requireLogin();

        header('Content-Type: application/json');
        echo json_encode([
            'items' => Game::suggestions((string) ($_GET['q'] ?? '')),
        ]);
    }

    public static function detail(array $params): void
    {
        Auth::requireLogin();

        $gameId = (int) $params['id'];
        $game = Game::find($gameId);
        if (!$game) {
            http_response_code(404);
            require __DIR__ . '/../../templates/404.php';
            return;
        }

        BggThingFetcher::ensureForPage([$gameId]);
        $game = Game::find($gameId) ?? $game;

        $userId = (int) Auth::user()['id'];
        $selectionState = UserGame::selectionState($userId, (int) $game['id']);
        $isSelectedByMe = $selectionState['selected_by_me'];
        $isInHut = $selectionState['in_hut'];
        $hearted    = \Hut\Vote::userHearted($userId, $game['id']);
        $hearts     = Game::getHeartCount($game['id']);
        $heartedBy  = \Hut\Vote::heartedBy($game['id']);
        $isInMyCollection = PersonalCollection::has($userId, $gameId);

        require __DIR__ . '/../../templates/games/detail.php';
    }

    public static function toggleSelect(array $params): void
    {
        Auth::requireLogin();
        Auth::requireCsrf(true);

        $gameId  = (int) $params['id'];
        $userId  = (int) Auth::user()['id'];
        $selectedByMe = UserGame::toggle($userId, $gameId);

        // Keep hearts in sync with hut selection for this user.
        $isHearted = \Hut\Vote::userHearted($userId, $gameId);
        if ($selectedByMe && !$isHearted) {
            \Hut\Vote::toggleHeart($userId, $gameId);
        } elseif (!$selectedByMe && $isHearted) {
            \Hut\Vote::toggleHeart($userId, $gameId);
        }

        $selectionState = UserGame::selectionState($userId, $gameId);

        // AJAX-friendly response
        header('Content-Type: application/json');
        echo json_encode([
            'selectedByMe' => $selectionState['selected_by_me'],
            'inHut' => $selectionState['in_hut'],
        ]);
    }

    public static function adminRemoveFromHut(array $params): void
    {
        Auth::requireAdmin();
        Auth::requireCsrf();

        $gameId = (int) $params['id'];
        UserGame::clearForAll($gameId);

        $_SESSION['flash_success'] = 'Game removed from hut menu for all users.';
        header('Location: ' . \Hut\Url::to('/games/' . $gameId));
        exit;
    }

    public static function collection(array $params): void
    {
        Auth::requireLogin();
        $games = Game::collection(Auth::user()['id']);
        BggThingFetcher::ensureForPage(array_column($games, 'id'));
        $residentNameMap = \Hut\Resident::firstNameToIdMap();
        require __DIR__ . '/../../templates/games/collection.php';
    }

    public static function changelog(array $params): void
    {
        Auth::requireLogin();

        $changelogPath = __DIR__ . '/../../storage/changelog.json';
        $entries = [];
        $generatedAt = null;
        $repoUrl = null;

        if (is_file($changelogPath)) {
            $raw = file_get_contents($changelogPath);
            if (is_string($raw) && $raw !== '') {
                $decoded = json_decode($raw, true);
                if (is_array($decoded)) {
                    $entriesRaw = $decoded['entries'] ?? [];
                    if (is_array($entriesRaw)) {
                        foreach ($entriesRaw as $entry) {
                            if (!is_array($entry)) {
                                continue;
                            }

                            $entries[] = [
                                'hash' => (string) ($entry['hash'] ?? ''),
                                'short_hash' => (string) ($entry['short_hash'] ?? ''),
                                'date' => (string) ($entry['date'] ?? ''),
                                'author' => (string) ($entry['author'] ?? ''),
                                'subject' => (string) ($entry['subject'] ?? ''),
                                'url' => (string) ($entry['url'] ?? ''),
                            ];
                        }
                    }

                    $generatedAt = isset($decoded['generated_at']) ? (string) $decoded['generated_at'] : null;
                    $repoUrl = isset($decoded['repo_url']) ? (string) $decoded['repo_url'] : null;
                }
            }
        }

        require __DIR__ . '/../../templates/info/changelog.php';
    }

    public static function addToMyCollection(array $params): void
    {
        Auth::requireLogin();
        Auth::requireCsrf();

        $gameId = (int) $params['id'];
        $game = Game::find($gameId);
        if (!$game) {
            http_response_code(404);
            require __DIR__ . '/../../templates/404.php';
            return;
        }

        $userId = (int) Auth::user()['id'];
        $added = PersonalCollection::add($userId, $gameId);

        $_SESSION['flash_success'] = $added
            ? 'Added to your personal collection.'
            : 'This game is already in your personal collection.';

        header('Location: ' . \Hut\Url::to('/games/' . $gameId));
        exit;
    }

    public static function removeFromMyCollection(array $params): void
    {
        Auth::requireLogin();
        Auth::requireCsrf();

        $gameId = (int) $params['id'];
        $game = Game::find($gameId);
        if (!$game) {
            http_response_code(404);
            require __DIR__ . '/../../templates/404.php';
            return;
        }

        $userId = (int) Auth::user()['id'];
        $removed = PersonalCollection::remove($userId, $gameId);

        $_SESSION['flash_success'] = $removed
            ? 'Removed from your personal collection.'
            : 'This game is not in your personal collection.';

        header('Location: ' . \Hut\Url::to('/games/' . $gameId));
        exit;
    }
}
