<?php

declare(strict_types=1);

namespace Hut;

class Resident
{
    public static function allApproved(): array
    {
        $pdo = Database::getInstance();
        $stmt = $pdo->query(
            'SELECT id, name, last_login_at, created_at
             FROM users
             WHERE is_approved = 1
             ORDER BY LOWER(name) ASC, id ASC'
        );

        return $stmt->fetchAll();
    }

    /**
     * Returns a map of first name => user ID for all approved residents.
     * If two residents share a first name, the one with the lower ID wins.
     */
    public static function firstNameToIdMap(): array
    {
        $map = [];
        foreach (self::allApproved() as $row) {
            $firstName = Auth::firstName((string) $row['name']);
            if ($firstName !== '' && !isset($map[$firstName])) {
                $map[$firstName] = (int) $row['id'];
            }
        }
        return $map;
    }

    public static function findApprovedById(int $userId): ?array
    {
        $pdo = Database::getInstance();
        $stmt = $pdo->prepare(
            'SELECT id, name, last_login_at, created_at
             FROM users
             WHERE id = ? AND is_approved = 1
             LIMIT 1'
        );
        $stmt->execute([$userId]);

        $resident = $stmt->fetch();
        return $resident ?: null;
    }

    public static function latestHeartedGame(int $userId): ?array
    {
        $pdo = Database::getInstance();
        $stmt = $pdo->prepare(
            'SELECT g.id, g.name, v.created_at AS hearted_at
             FROM votes v
             JOIN games g ON g.id = v.game_id
             WHERE v.user_id = ?
             ORDER BY v.created_at DESC, v.id DESC
             LIMIT 1'
        );
        $stmt->execute([$userId]);

        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function latestAddedToHutCollection(int $userId): ?array
    {
        $pdo = Database::getInstance();
        $stmt = $pdo->prepare(
            'SELECT g.id, g.name, ug.created_at AS added_at
             FROM user_games ug
             JOIN games g ON g.id = ug.game_id
             WHERE ug.user_id = ? AND ug.selected = 1
             ORDER BY ug.created_at DESC, ug.id DESC
             LIMIT 1'
        );
        $stmt->execute([$userId]);

        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function gamesAddedToHutCollection(int $userId): array
    {
        $pdo = Database::getInstance();
        $stmt = $pdo->prepare(
            'SELECT g.id, g.name, ug.created_at AS added_at
             FROM user_games ug
             JOIN games g ON g.id = ug.game_id
             WHERE ug.user_id = ? AND ug.selected = 1
             ORDER BY ug.created_at DESC, ug.id DESC'
        );
        $stmt->execute([$userId]);

        return $stmt->fetchAll();
    }

    public static function heartedGames(int $userId): array
    {
        $pdo = Database::getInstance();
        $stmt = $pdo->prepare(
            'SELECT g.id, g.name, v.created_at AS hearted_at
             FROM votes v
             JOIN games g ON g.id = v.game_id
             WHERE v.user_id = ?
             ORDER BY v.created_at DESC, v.id DESC'
        );
        $stmt->execute([$userId]);

        return $stmt->fetchAll();
    }
}
