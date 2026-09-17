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
     * Returns the most recent shared activity (games added/hearted, food suggested/hearted,
     * bring commitments) from approved users, newest first.
     *
     * @return list<array{type:string,actor:string,subject:string,entity_id:int,url:string,occurred_at:string}>
     */
    public static function recentActivity(int $limit = 50): array
    {
        $pdo = Database::getInstance();

        $stmt = $pdo->prepare(
            "SELECT 'added_game' AS type, u.name AS actor, g.name AS subject, g.id AS entity_id, ug.created_at AS occurred_at, NULL AS cook_date_value
             FROM user_games ug
             JOIN games g ON g.id = ug.game_id
             JOIN users u ON u.id = ug.user_id
             WHERE ug.selected = 1 AND u.is_approved = 1

             UNION ALL

             SELECT 'hearted_game', u.name, g.name, g.id, v.created_at, NULL
             FROM votes v
             JOIN games g ON g.id = v.game_id
             JOIN users u ON u.id = v.user_id
             WHERE u.is_approved = 1

             UNION ALL

             SELECT 'suggested_food', u.name, fs.title, fs.id, fs.created_at, NULL
             FROM food_suggestions fs
             JOIN users u ON u.id = fs.user_id
             WHERE u.is_approved = 1

             UNION ALL

             SELECT 'hearted_food', u.name, fs.title, fs.id, fv.created_at, NULL
             FROM food_votes fv
             JOIN food_suggestions fs ON fs.id = fv.food_suggestion_id
             JOIN users u ON u.id = fv.user_id
             WHERE u.is_approved = 1

             UNION ALL

             SELECT 'scheduled_food', u.name, fs.title, fs.id, fs.cook_date_updated_at, fs.cook_date
             FROM food_suggestions fs
             JOIN users u ON u.id = fs.cook_date_user_id
             WHERE u.is_approved = 1 AND fs.cook_date IS NOT NULL AND fs.cook_date_updated_at IS NOT NULL

             UNION ALL

             SELECT 'bring_commitment', u.name, g.name, g.id, gbc.created_at, NULL
             FROM game_bring_commitments gbc
             JOIN games g ON g.id = gbc.game_id
             JOIN users u ON u.id = gbc.user_id
             WHERE u.is_approved = 1

             UNION ALL

             SELECT 'removed_game', u.name, g.name, g.id, cr.removed_at, NULL
             FROM collection_removals cr
             JOIN games g ON g.id = cr.game_id
             JOIN users u ON u.id = cr.removed_by_user_id
             WHERE u.is_approved = 1

             ORDER BY occurred_at DESC
             LIMIT ?"
        );
        $stmt->bindValue(1, $limit, \PDO::PARAM_INT);
        $stmt->execute();

        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        foreach ($rows as &$row) {
            $entityId = (int) $row['entity_id'];
            $row['entity_id'] = $entityId;
            $row['url'] = match ((string) $row['type']) {
                'suggested_food', 'hearted_food', 'scheduled_food' => '/news/food',
                default => '/games/' . $entityId,
            };

            if ($row['type'] === 'scheduled_food' && !empty($row['cook_date_value'])) {
                $cookTimestamp = strtotime((string) $row['cook_date_value']);
                if ($cookTimestamp !== false) {
                    $row['subject'] .= ' (' . date('d.m.Y', $cookTimestamp) . ')';
                }
            }
            unset($row['cook_date_value']);
        }
        unset($row);

        return $rows;
    }
}
