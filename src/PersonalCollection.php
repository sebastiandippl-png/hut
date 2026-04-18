<?php

declare(strict_types=1);

namespace Hut;

class PersonalCollection
{
    /**
     * Add a game to a user's personal collection.
     *
     * Returns true if a new row was inserted, false if it already existed.
     */
    public static function add(int $userId, int $gameId): bool
    {
        $pdo = Database::getInstance();
        $driver = (string) $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);

        $sql = $driver === 'mysql'
            ? 'INSERT IGNORE INTO user_personal_collection (user_id, game_id) VALUES (?, ?)'
            : 'INSERT OR IGNORE INTO user_personal_collection (user_id, game_id) VALUES (?, ?)';

        $stmt = $pdo->prepare($sql);
        $stmt->execute([$userId, $gameId]);

        return $stmt->rowCount() > 0;
    }

    public static function remove(int $userId, int $gameId): bool
    {
        $pdo = Database::getInstance();
        $stmt = $pdo->prepare('DELETE FROM user_personal_collection WHERE user_id = ? AND game_id = ?');
        $stmt->execute([$userId, $gameId]);

        return $stmt->rowCount() > 0;
    }

    public static function has(int $userId, int $gameId): bool
    {
        $pdo = Database::getInstance();
        $stmt = $pdo->prepare(
            'SELECT 1 FROM user_personal_collection WHERE user_id = ? AND game_id = ? LIMIT 1'
        );
        $stmt->execute([$userId, $gameId]);

        return $stmt->fetchColumn() !== false;
    }
}
