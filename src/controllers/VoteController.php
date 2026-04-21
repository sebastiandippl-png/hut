<?php

declare(strict_types=1);

namespace Hut\controllers;

use Hut\Auth;
use Hut\Vote;

class VoteController
{
    public static function heart(array $params): void
    {
        Auth::requireLogin();
        Auth::requireCsrf(true);

        $gameId = (int) $params['id'];

        $hearted   = Vote::toggleHeart(Auth::user()['id'], $gameId);
        $hearts    = \Hut\Game::getHeartCount($gameId);
        $heartedBy = Vote::heartedBy($gameId);

        header('Content-Type: application/json');
        echo json_encode(['hearted' => $hearted, 'hearts' => $hearts, 'heartedBy' => $heartedBy]);
    }

}
