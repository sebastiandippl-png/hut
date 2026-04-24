<?php

declare(strict_types=1);

namespace Hut\controllers;

use Hut\Auth;
use Hut\BggThingFetcher;
use Hut\Resident;

class ResidentController
{
    public static function index(array $params): void
    {
        Auth::requireLogin();

        $currentUserId = (int) (Auth::user()['id'] ?? 0);
        if ($currentUserId <= 0) {
            http_response_code(404);
            require __DIR__ . '/../../templates/404.php';
            return;
        }

        header('Location: ' . \Hut\Url::to('/residents/' . $currentUserId));
        exit;
    }

    public static function profile(array $params): void
    {
        Auth::requireLogin();

        $residentId = (int) ($params['id'] ?? 0);
        if ($residentId <= 0) {
            http_response_code(404);
            require __DIR__ . '/../../templates/404.php';
            return;
        }

        $resident = Resident::findApprovedById($residentId);
        if ($resident === null) {
            http_response_code(404);
            require __DIR__ . '/../../templates/404.php';
            return;
        }

        $latestHearted = Resident::latestHeartedGame($residentId);
        $latestAdded = Resident::latestAddedToHutCollection($residentId);
        $addedGames = Resident::gamesAddedToHutCollection($residentId);
        $heartedGames = Resident::heartedGames($residentId);

        $gameIds = [];
        if ($latestHearted !== null) {
            $gameIds[] = (int) $latestHearted['id'];
        }
        if ($latestAdded !== null) {
            $gameIds[] = (int) $latestAdded['id'];
        }

        foreach ($addedGames as $game) {
            $gameIds[] = (int) $game['id'];
        }
        foreach ($heartedGames as $game) {
            $gameIds[] = (int) $game['id'];
        }

        $gameIds = array_values(array_unique(array_filter($gameIds, static fn (int $id): bool => $id > 0)));
        if (!empty($gameIds)) {
            BggThingFetcher::ensureForPage($gameIds);
        }

        require __DIR__ . '/../../templates/residents/profile.php';
    }
}
