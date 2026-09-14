<?php

declare(strict_types=1);

namespace Hut\controllers;

use Hut\Auth;
use Hut\BggThingFetcher;
use Hut\Game;
use Hut\GameBringCommitment;
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

        $currentUserId = (int) (Auth::user()['id'] ?? 0);
        $isOwnProfile = $currentUserId > 0 && $currentUserId === $residentId;

        $latestHearted = Resident::latestHeartedGame($residentId);
        $latestAdded = Resident::latestAddedToHutCollection($residentId);
        $addedGames = Resident::gamesAddedToHutCollection($residentId);
        $heartedGames = Resident::heartedGames($residentId);

        $ownedHutGames = Game::gamesOwnedByUserInHutCollection($residentId);
        $bringers = GameBringCommitment::bringersForGames(array_column($ownedHutGames, 'id'));
        $gamesToPack = [];
        $gamesUnclearOwner = [];
        foreach ($ownedHutGames as $game) {
            $bringer = $bringers[(int) $game['id']] ?? null;
            $game['claimed_by_resident'] = $bringer !== null && $bringer['user_id'] === $residentId;
            $game['bringer_name'] = $bringer['name'] ?? null;

            if ((int) $game['owner_count'] === 1 || $game['claimed_by_resident']) {
                $gamesToPack[] = $game;
            } else {
                $gamesUnclearOwner[] = $game;
            }
        }

        $byHeartsDesc = static function (array $a, array $b): int {
            return (int) ($b['hearts'] ?? 0) <=> (int) ($a['hearts'] ?? 0);
        };
        usort($gamesToPack, $byHeartsDesc);
        usort($gamesUnclearOwner, $byHeartsDesc);

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
        foreach ($ownedHutGames as $game) {
            $gameIds[] = (int) $game['id'];
        }

        $gameIds = array_values(array_unique(array_filter($gameIds, static fn (int $id): bool => $id > 0)));
        if (!empty($gameIds)) {
            BggThingFetcher::ensureForPage($gameIds);
        }

        require __DIR__ . '/../../templates/residents/profile.php';
    }
}
