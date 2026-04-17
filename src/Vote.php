<?php

declare(strict_types=1);

namespace Hut;

class Vote
{
    /**
     * Toggle a heart for a game.
     * Returns true when the game is hearted after the call, false otherwise.
     */
    public static function toggleHeart(int $userId, int $gameId): bool
    {
        $pdo  = Database::getInstance();
        $driver = (string) $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);
        $stmt = $pdo->prepare(
            'SELECT value FROM votes WHERE user_id = ? AND game_id = ? LIMIT 1'
        );
        $stmt->execute([$userId, $gameId]);
        $exists = $stmt->fetchColumn() !== false;

        if ($exists) {
            $pdo->prepare('DELETE FROM votes WHERE user_id = ? AND game_id = ?')
                ->execute([$userId, $gameId]);
            return false;
        }

        $upsertSql = $driver === 'mysql'
            ? 'INSERT INTO votes (user_id, game_id, value) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE value = VALUES(value)'
            : 'INSERT INTO votes (user_id, game_id, value) VALUES (?, ?, ?) ON CONFLICT(user_id, game_id) DO UPDATE SET value = excluded.value';

        $pdo->prepare($upsertSql)->execute([$userId, $gameId, 1]);

        return true;
    }

    /**
     * Return whether the given user has hearted the game.
     */
    public static function userHearted(int $userId, int $gameId): bool
    {
        $pdo  = Database::getInstance();
        $stmt = $pdo->prepare('SELECT 1 FROM votes WHERE user_id = ? AND game_id = ? LIMIT 1');
        $stmt->execute([$userId, $gameId]);
        return $stmt->fetchColumn() !== false;
    }

    public static function heartCount(int $gameId): int
    {
        $pdo  = Database::getInstance();
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM votes WHERE game_id = ?');
        $stmt->execute([$gameId]);
        return (int) $stmt->fetchColumn();
    }

    public static function heartedBy(int $gameId): string
    {
        $pdo  = Database::getInstance();
        $groupConcat = self::groupConcatNamesExpr($pdo, false);
        $stmt = $pdo->prepare(
            'SELECT ' . $groupConcat . '
             FROM votes v
             JOIN users u ON u.id = v.user_id
             WHERE v.game_id = ?'
        );
        $stmt->execute([$gameId]);

        $names = $stmt->fetchColumn();
        return $names ? Auth::firstNames((string) $names) : 'No hearts yet';
    }

    /**
     * Rankings: all selected games sorted by heart count.
     */
    public static function rankings(): array
    {
        $pdo = Database::getInstance();
        $heartedByExpr = self::groupConcatNamesExpr($pdo, true);
        $rows = $pdo->query(<<<SQL
            WITH selectors AS (
                SELECT game_id, COUNT(DISTINCT user_id) AS selectors
                FROM user_games
                WHERE selected = 1
                GROUP BY game_id
            ),
            bgg_owners AS (
                SELECT bgg_game_id,
                       GROUP_CONCAT(bgg_user, ', ') AS bgg_owned_by
                FROM user_collection
                GROUP BY bgg_game_id
            ),
            hearts AS (
                SELECT v.game_id,
                       COUNT(DISTINCT v.user_id) AS hearts,
                      {$heartedByExpr} AS hearted_by
                FROM votes v
                JOIN users u ON u.id = v.user_id
                GROUP BY v.game_id
            )
            SELECT g.id,
                   g.name,
                   g.yearpublished,
                   g.rank,
                   bt.averageweight,
                   s.selectors,
                   COALESCE(bo.bgg_owned_by, '') AS bgg_owned_by,
                   COALESCE(h.hearts, 0) AS hearts,
                   COALESCE(h.hearted_by, '') AS hearted_by
            FROM games g
            LEFT JOIN bgg_thing bt ON bt.bgg_id = g.id
            JOIN selectors s ON s.game_id = g.id
            LEFT JOIN bgg_owners bo ON bo.bgg_game_id = g.id
            LEFT JOIN hearts h ON h.game_id = g.id
            ORDER BY hearts DESC,
                     CASE WHEN g.rank IS NULL OR g.rank <= 0 THEN 1 ELSE 0 END ASC,
                     g.rank ASC,
                     g.name ASC
        SQL)->fetchAll();

        return array_map(static function (array $row): array {
            if ($row['hearted_by'] !== '') {
                $row['hearted_by'] = Auth::firstNames($row['hearted_by']);
            }
            return $row;
        }, $rows);
    }

    private static function groupConcatNamesExpr(\PDO $pdo, bool $distinct): string
    {
        $driver = (string) $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);
        if ($driver === 'mysql') {
            return $distinct
                ? 'GROUP_CONCAT(DISTINCT u.name SEPARATOR ", ")'
                : 'GROUP_CONCAT(u.name SEPARATOR ", ")';
        }

        return $distinct
            ? 'GROUP_CONCAT(DISTINCT u.name)'
            : 'GROUP_CONCAT(u.name, ", ")';
    }
}
