<?php

declare(strict_types=1);

namespace Hut;

class CollectionRemoval
{
    public const REASON_EXCLUSIVE_OWNER = 'exclusive_owner';
    public const REASON_SUGGESTER = 'suggester';
    public const REASON_ADMIN = 'admin';

    public static function log(int $gameId, int $removedByUserId, string $reason): void
    {
        $pdo = Database::getInstance();
        $stmt = $pdo->prepare(
            'INSERT INTO collection_removals (game_id, removed_by_user_id, reason) VALUES (?, ?, ?)'
        );
        $stmt->execute([$gameId, $removedByUserId, $reason]);
    }

    /**
     * @return list<array{game_id:int,game_name:string,removed_by_user_id:int,removed_by_name:string,reason:string,removed_at:string}>
     */
    public static function recent(int $limit = 200): array
    {
        $pdo = Database::getInstance();
        $stmt = $pdo->prepare(
            'SELECT cr.game_id, g.name AS game_name,
                    cr.removed_by_user_id, u.name AS removed_by_name,
                    cr.reason, cr.removed_at
             FROM collection_removals cr
             JOIN games g ON g.id = cr.game_id
             JOIN users u ON u.id = cr.removed_by_user_id
             ORDER BY cr.removed_at DESC, cr.id DESC
             LIMIT ?'
        );
        $stmt->bindValue(1, $limit, \PDO::PARAM_INT);
        $stmt->execute();

        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        foreach ($rows as &$row) {
            $row['game_id'] = (int) $row['game_id'];
            $row['removed_by_user_id'] = (int) $row['removed_by_user_id'];
        }
        unset($row);

        return $rows;
    }
}
