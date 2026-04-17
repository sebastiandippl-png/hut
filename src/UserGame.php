<?php

declare(strict_types=1);

namespace Hut;

class UserGame
{
    /**
     * Toggle: if not selected → select, if selected → deselect.
     * Returns true if now selected, false if deselected.
     */
    public static function toggle(int $userId, int $gameId): bool
    {
        $pdo  = Database::getInstance();
        $stmt = $pdo->prepare(
            'SELECT selected FROM user_games WHERE user_id = ? AND game_id = ? LIMIT 1'
        );
        $stmt->execute([$userId, $gameId]);
        $row = $stmt->fetch();

        if (!$row) {
            $pdo->prepare(
                'INSERT INTO user_games (user_id, game_id, selected) VALUES (?, ?, 1)'
            )->execute([$userId, $gameId]);
            return true;
        }

        $newValue = $row['selected'] ? 0 : 1;
        $pdo->prepare(
            'UPDATE user_games SET selected = ? WHERE user_id = ? AND game_id = ?'
        )->execute([$newValue, $userId, $gameId]);
        return (bool) $newValue;
    }

    /**
     * Return a set of game IDs selected by a given user.
     */
    public static function selectedGameIds(int $userId): array
    {
        $pdo  = Database::getInstance();
        $stmt = $pdo->prepare(
            'SELECT game_id FROM user_games WHERE user_id = ? AND selected = 1'
        );
        $stmt->execute([$userId]);
        return $stmt->fetchAll(\PDO::FETCH_COLUMN);
    }

    /**
     * Return both the current user's selection and whether the game is already in the shared hut.
     *
     * @return array{selected_by_me: bool, in_hut: bool}
     */
    public static function selectionState(int $userId, int $gameId): array
    {
        $pdo = Database::getInstance();
        $stmt = $pdo->prepare(
            'SELECT
                EXISTS(
                    SELECT 1
                    FROM user_games
                    WHERE user_id = :user_id AND game_id = :game_id AND selected = 1
                ) AS selected_by_me,
                EXISTS(
                    SELECT 1
                    FROM user_games
                    WHERE game_id = :game_id AND selected = 1
                ) AS in_hut'
        );
        $stmt->execute([
            ':user_id' => $userId,
            ':game_id' => $gameId,
        ]);

        $state = $stmt->fetch();

        return [
            'selected_by_me' => (bool) ($state['selected_by_me'] ?? false),
            'in_hut' => (bool) ($state['in_hut'] ?? false),
        ];
    }
}
