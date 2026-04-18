<?php

declare(strict_types=1);

namespace Hut\controllers;

use Hut\Auth;
use Hut\BggThingFetcher;
use Hut\Game;
use Hut\UserGame;

class GameController
{
    public static function browse(array $params): void
    {
        Auth::requireLogin();

        $search = trim($_GET['q'] ?? '');
        $selectedUsers = isset($_GET['users']) && is_array($_GET['users'])
            ? array_map('strval', array_filter($_GET['users']))
            : [];
        $page = max(1, (int) ($_GET['page'] ?? 1));

        // Get available users from user_collection
        $pdo = \Hut\Database::getInstance();
        $usersStmt = $pdo->query('SELECT DISTINCT bgg_user FROM user_collection ORDER BY bgg_user');
        $availableUsers = array_map(static fn ($row) => $row['bgg_user'], $usersStmt->fetchAll(\PDO::FETCH_ASSOC));

        $userId = (int) Auth::user()['id'];

        $isDefaultView = $search === '' && empty($selectedUsers);
        if ($isDefaultView) {
            $games = Game::randomCollectionGames(24, $userId);
            $total = count($games);
            $perPage = 24;
        } else {
            $result = Game::browse($search, $selectedUsers, $page, 24, $userId);
            extract($result); // $games, $total, $page, $perPage
        }

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

        require __DIR__ . '/../../templates/games/detail.php';
    }

    public static function toggleSelect(array $params): void
    {
        Auth::requireLogin();
        Auth::requireCsrf(true);

        $gameId  = (int) $params['id'];
        $userId  = (int) Auth::user()['id'];
        UserGame::toggle($userId, $gameId);
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
        require __DIR__ . '/../../templates/games/collection.php';
    }
}
