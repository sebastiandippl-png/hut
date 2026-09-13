<?php

declare(strict_types=1);

namespace Hut;

class Resident
{
    public static function allApproved(): array
    {
        $pdo = Database::getInstance();
        $stmt = $pdo->query(
            'SELECT id, name, bgg_username, last_login_at, created_at
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
            'SELECT id, name, bgg_username, last_login_at, created_at
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

    /**
     * Returns the most recently added game to the hut collection across all approved users.
     */
    public static function latestAddedGameGlobally(): ?array
    {
        $pdo = Database::getInstance();
        $stmt = $pdo->query(
            'SELECT g.id, g.name, u.name AS added_by, ug.created_at AS added_at
             FROM user_games ug
             JOIN games g ON g.id = ug.game_id
             JOIN users u ON u.id = ug.user_id
             WHERE ug.selected = 1 AND u.is_approved = 1
             ORDER BY ug.created_at DESC, ug.id DESC
             LIMIT 1'
        );

        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Returns the most recently hearted game across all approved users.
     */
    public static function latestHeartedGameGlobally(): ?array
    {
        $pdo = Database::getInstance();
        $stmt = $pdo->query(
            'SELECT g.id, g.name, u.name AS hearted_by, v.created_at AS hearted_at
             FROM votes v
             JOIN games g ON g.id = v.game_id
             JOIN users u ON u.id = v.user_id
             WHERE u.is_approved = 1
             ORDER BY v.created_at DESC, v.id DESC
             LIMIT 1'
        );

        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Returns recent shared activity (games added/hearted, food suggested/hearted, bring
     * commitments) from approved users within the given number of hours, newest first.
     *
     * @return list<array{type:string,actor:string,subject:string,entity_id:int,url:string,occurred_at:string}>
     */
    public static function recentActivity(int $hours = 24, int $limit = 12): array
    {
        $pdo = Database::getInstance();
        $cutoff = (new \DateTimeImmutable("-{$hours} hours"))->format('Y-m-d H:i:s');

        $stmt = $pdo->prepare(
            "SELECT 'added_game' AS type, u.name AS actor, g.name AS subject, g.id AS entity_id, ug.created_at AS occurred_at
             FROM user_games ug
             JOIN games g ON g.id = ug.game_id
             JOIN users u ON u.id = ug.user_id
             WHERE ug.selected = 1 AND u.is_approved = 1 AND ug.created_at >= ?

             UNION ALL

             SELECT 'hearted_game', u.name, g.name, g.id, v.created_at
             FROM votes v
             JOIN games g ON g.id = v.game_id
             JOIN users u ON u.id = v.user_id
             WHERE u.is_approved = 1 AND v.created_at >= ?

             UNION ALL

             SELECT 'suggested_food', u.name, fs.title, fs.id, fs.created_at
             FROM food_suggestions fs
             JOIN users u ON u.id = fs.user_id
             WHERE u.is_approved = 1 AND fs.created_at >= ?

             UNION ALL

             SELECT 'hearted_food', u.name, fs.title, fs.id, fv.created_at
             FROM food_votes fv
             JOIN food_suggestions fs ON fs.id = fv.food_suggestion_id
             JOIN users u ON u.id = fv.user_id
             WHERE u.is_approved = 1 AND fv.created_at >= ?

             UNION ALL

             SELECT 'bring_commitment', u.name, g.name, g.id, gbc.created_at
             FROM game_bring_commitments gbc
             JOIN games g ON g.id = gbc.game_id
             JOIN users u ON u.id = gbc.user_id
             WHERE u.is_approved = 1 AND gbc.created_at >= ?

             ORDER BY occurred_at DESC
             LIMIT ?"
        );
        $stmt->bindValue(1, $cutoff);
        $stmt->bindValue(2, $cutoff);
        $stmt->bindValue(3, $cutoff);
        $stmt->bindValue(4, $cutoff);
        $stmt->bindValue(5, $cutoff);
        $stmt->bindValue(6, $limit, \PDO::PARAM_INT);
        $stmt->execute();

        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        foreach ($rows as &$row) {
            $entityId = (int) $row['entity_id'];
            $row['entity_id'] = $entityId;
            $row['url'] = match ((string) $row['type']) {
                'suggested_food', 'hearted_food' => '/news/food',
                default => '/games/' . $entityId,
            };
        }
        unset($row);

        return $rows;
    }
}
