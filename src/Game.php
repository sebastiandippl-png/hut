<?php

declare(strict_types=1);

namespace Hut;

use PDO;

class Game
{
    private const RANK_ORDER_SQL = 'CASE WHEN rank IS NULL OR rank <= 0 THEN 1 ELSE 0 END ASC, rank ASC, name ASC';

    private static function complexityWhereClause(string $complexity, string $column = 'bt.averageweight'): ?string
    {
        return match ($complexity) {
            'light' => "{$column} > 0 AND {$column} <= 1.8",
            'medium' => "{$column} > 1.8 AND {$column} <= 3.0",
            'complex' => "{$column} > 3.0",
            default => null,
        };
    }

    /**
     * @param array<int, string> $selectedUsers
     */
    public static function browse(
        string $search = '',
        array  $selectedUsers = [],
        int    $page = 1,
        int    $perPage = 24,
        ?int   $currentUserId = null,
        string $complexity = ''
    ): array {
        $pdo    = Database::getInstance();
        $where  = ['1=1'];
        $params = [];
        $joins  = [];

        $complexityWhere = self::complexityWhereClause($complexity);
        if ($complexityWhere !== null) {
            $joins[] = 'LEFT JOIN bgg_thing bt ON bt.bgg_id = g.id';
            $where[] = $complexityWhere;
        }

        if ($search !== '') {
            $where[]          = 'g.name LIKE :search';
            $params[':search'] = '%' . $search . '%';
        }

        // If users are selected, filter by all known owners (BGG imports + manual owners).
        if (!empty($selectedUsers)) {
            $placeholders = implode(',', array_map(static fn ($i) => ":user_$i", range(0, count($selectedUsers) - 1)));
            $where[] = "EXISTS (
                SELECT 1
                FROM (
                    SELECT uc.bgg_game_id AS game_id, uc.bgg_user AS owner_name
                    FROM user_collection uc
                    UNION ALL
                    SELECT upc.game_id AS game_id, u.name AS owner_name
                    FROM user_personal_collection upc
                    JOIN users u ON u.id = upc.user_id
                ) owners
                WHERE owners.game_id = g.id
                  AND owners.owner_name IN ($placeholders)
            )";
            foreach ($selectedUsers as $i => $user) {
                $params[":user_$i"] = $user;
            }
        }

        $whereClause = implode(' AND ', $where);
        $joinClause  = !empty($joins) ? ' ' . implode(' ', $joins) : '';
        $offset      = ($page - 1) * $perPage;

        $countStmt = $pdo->prepare("SELECT COUNT(DISTINCT g.id) FROM games g{$joinClause} WHERE $whereClause");
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $selectedByMeSql = $currentUserId !== null
            ? 'EXISTS(SELECT 1 FROM user_games ug_self WHERE ug_self.game_id = g.id AND ug_self.user_id = :current_user_id AND ug_self.selected = 1)'
            : '0';

        $stmt = $pdo->prepare(
            "SELECT DISTINCT g.*,
                    EXISTS(SELECT 1 FROM user_games ug_any WHERE ug_any.game_id = g.id AND ug_any.selected = 1) AS in_hut,
                    {$selectedByMeSql} AS selected_by_me
                   FROM games g{$joinClause}
             WHERE $whereClause
             ORDER BY " . self::RANK_ORDER_SQL . ' LIMIT :limit OFFSET :offset'
        );
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        if ($currentUserId !== null) {
            $stmt->bindValue(':current_user_id', $currentUserId, PDO::PARAM_INT);
        }
        $stmt->bindValue(':limit',  $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset,  PDO::PARAM_INT);
        $stmt->execute();
        $games = $stmt->fetchAll();

        if (!empty($games)) {
            $ownerRows = self::collectionOwnersMap(array_column($games, 'id'));
            foreach ($games as &$game) {
                $game['collection_owners'] = $ownerRows[(int) $game['id']] ?? null;
            }
            unset($game);
        }

        return ['games' => $games, 'total' => $total, 'page' => $page, 'perPage' => $perPage];
    }

    public static function randomCollectionGames(int $limit = 24, ?int $currentUserId = null, string $complexity = ''): array
    {
        $pdo = Database::getInstance();
        $randomOrderExpr = self::randomOrderExpr($pdo);
        $complexityWhere = self::complexityWhereClause($complexity);

        $selectedByMeSql = $currentUserId !== null
            ? 'EXISTS(SELECT 1 FROM user_games ug_self WHERE ug_self.game_id = g.id AND ug_self.user_id = :current_user_id AND ug_self.selected = 1)'
            : '0';

        $stmt = $pdo->prepare(
            "SELECT DISTINCT g.*,
                    EXISTS(SELECT 1 FROM user_games ug_any WHERE ug_any.game_id = g.id AND ug_any.selected = 1) AS in_hut,
                    {$selectedByMeSql} AS selected_by_me
             FROM games g
             LEFT JOIN bgg_thing bt ON bt.bgg_id = g.id
             WHERE (g.is_expansion IS NULL OR g.is_expansion = 0)
                AND (
                    EXISTS(SELECT 1 FROM user_collection uc WHERE uc.bgg_game_id = g.id)
                    OR EXISTS(SELECT 1 FROM user_personal_collection upc WHERE upc.game_id = g.id)
                )
                " . ($complexityWhere !== null ? " AND {$complexityWhere}" : '') . "
               ORDER BY {$randomOrderExpr}
             LIMIT :limit"
        );
        if ($currentUserId !== null) {
            $stmt->bindValue(':current_user_id', $currentUserId, PDO::PARAM_INT);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll();

        if (empty($rows)) {
            return [];
        }

        $ownerRows = self::collectionOwnersMap(array_column($rows, 'id'));
        foreach ($rows as &$row) {
            $row['collection_owners'] = $ownerRows[(int) $row['id']] ?? null;
        }
        unset($row);

        return $rows;
    }

    public static function suggestions(string $search, int $limit = 8): array
    {
        $search = trim($search);
        if (mb_strlen($search) < 2) {
            return [];
        }

        $pdo = Database::getInstance();
        $stmt = $pdo->prepare(
            'SELECT id, name, yearpublished, rank
             FROM games
             WHERE name LIKE :search
             ORDER BY ' . self::RANK_ORDER_SQL . '
             LIMIT :limit'
        );
        $stmt->bindValue(':search', '%' . $search . '%');
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public static function find(int $id): ?array
    {
        $pdo  = Database::getInstance();
        $stmt = $pdo->prepare(
            'SELECT g.*,
                    bt.thumbnail AS thing_thumbnail,
                    bt.image AS thing_image,
                    bt.primary_name AS thing_primary_name,
                    bt.description AS thing_description,
                    bt.yearpublished AS thing_yearpublished,
                    bt.minplayers AS thing_minplayers,
                    bt.maxplayers AS thing_maxplayers,
                    bt.minage AS thing_minage,
                    bt.minplaytime AS thing_minplaytime,
                    bt.maxplaytime AS thing_maxplaytime,
                    bt.categories AS thing_categories,
                    bt.mechanics AS thing_mechanics,
                    bt.designers AS thing_designers,
                    bt.publishers AS thing_publishers,
                    bt.families AS thing_families,
                    bt.usersrated AS thing_usersrated,
                    bt.average AS thing_average,
                    bt.bayesaverage AS thing_bayesaverage,
                    bt.stddev AS thing_stddev,
                    bt.owned AS thing_owned,
                    bt.wanting AS thing_wanting,
                    bt.wishing AS thing_wishing,
                    bt.numweights AS thing_numweights,
                    bt.averageweight AS thing_averageweight,
                    bt.best_playercount AS thing_best_playercount,
                    bt.best_playercount_num AS thing_best_playercount_num,
                    bt.player_best_values AS thing_player_best_values,
                    bt.player_recommended_values AS thing_player_recommended_values,
                    bt.player_not_recommended_values AS thing_player_not_recommended_values,
                          bt.last_updated AS thing_last_updated,
                          COALESCE(bo.bgg_owned_by, \'\') AS bgg_owned_by
             FROM games g
             LEFT JOIN bgg_thing bt ON bt.bgg_id = g.id
                      LEFT JOIN (
                       SELECT bgg_game_id,
                                GROUP_CONCAT(bgg_user, \', \') AS bgg_owned_by
                       FROM user_collection
                       GROUP BY bgg_game_id
                      ) bo ON bo.bgg_game_id = g.id
             WHERE g.id = ?
             LIMIT 1'
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if ($row) {
            $ownerRows = self::collectionOwnersMap([$id]);
            $row['bgg_owned_by'] = $ownerRows[$id] ?? '';
        }
        return $row ?: null;
    }

    public static function getHeartCount(int $gameId): int
    {
        $pdo  = Database::getInstance();
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM votes WHERE game_id = ?');
        $stmt->execute([$gameId]);
        return (int) $stmt->fetchColumn();
    }

    /**
     * Return games selected by at least one user with selector info.
     */
    public static function collection(int $currentUserId): array
    {
        $pdo = Database::getInstance();
        $selectedByExpr = self::groupConcatNamesExpr($pdo, false);
        $heartedByExpr = self::groupConcatNamesExpr($pdo, true);
        $sql = <<<SQL
            WITH selectors AS (
                SELECT ug.game_id,
                       {$selectedByExpr} AS selected_by
                FROM user_games ug
                JOIN users u ON u.id = ug.user_id
                WHERE ug.selected = 1
                GROUP BY ug.game_id
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
            SELECT g.*,
                     bt.minplayers,
                     bt.maxplayers,
                     bt.minplaytime,
                     bt.maxplaytime,
                     bt.averageweight,
                     bt.best_playercount,
                     bt.best_playercount_num,
                   s.selected_by,
                   COALESCE(bo.bgg_owned_by, '') AS bgg_owned_by,
                   COALESCE(h.hearts, 0) AS hearts,
                   COALESCE(h.hearted_by, 'No hearts yet') AS hearted_by,
                   EXISTS(
                       SELECT 1
                       FROM votes v2
                       WHERE v2.game_id = g.id AND v2.user_id = :user_id
                   ) AS hearted_by_me
            FROM games g
            JOIN selectors s ON s.game_id = g.id
            LEFT JOIN bgg_thing bt ON bt.bgg_id = g.id
            LEFT JOIN bgg_owners bo ON bo.bgg_game_id = g.id
            LEFT JOIN hearts h ON h.game_id = g.id
            ORDER BY hearts DESC,
                     CASE WHEN g.rank IS NULL OR g.rank <= 0 THEN 1 ELSE 0 END ASC,
                     g.rank ASC,
                     g.name ASC
        SQL;
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':user_id' => $currentUserId]);
        $rows = $stmt->fetchAll();
        $ownerRows = self::collectionOwnersMap(array_column($rows, 'id'));

        return array_map(static function (array $row) use ($ownerRows): array {
            if (isset($row['hearted_by']) && $row['hearted_by'] !== 'No hearts yet' && $row['hearted_by'] !== '') {
                $row['hearted_by'] = Auth::firstNames($row['hearted_by']);
            }
            $row['bgg_owned_by'] = $ownerRows[(int) $row['id']] ?? '';
            return $row;
        }, $rows);
    }

    /**
     * @param array<int, int|string> $gameIds
     * @return array<int, string>
     */
    public static function collectionOwnersMap(array $gameIds): array
    {
        $ids = array_values(array_unique(array_map('intval', $gameIds)));
        $ids = array_values(array_filter($ids, static fn (int $id): bool => $id > 0));
        if ($ids === []) {
            return [];
        }

        $pdo = Database::getInstance();
        $placeholders = implode(',', array_map(static fn (int $index): string => ':id_' . $index, array_keys($ids)));

        $bggStmt = $pdo->prepare(
            "SELECT bgg_game_id AS game_id, bgg_user AS owner_name
             FROM user_collection
             WHERE bgg_game_id IN ($placeholders)"
        );
        self::bindGameIds($bggStmt, $ids);
        $bggStmt->execute();

        $manualStmt = $pdo->prepare(
            "SELECT upc.game_id AS game_id, u.name AS owner_name, 1 AS is_manual
             FROM user_personal_collection upc
             JOIN users u ON u.id = upc.user_id
             WHERE upc.game_id IN ($placeholders)"
        );
        self::bindGameIds($manualStmt, $ids);
        $manualStmt->execute();

        $ownerSetsByGame = [];
        foreach (array_merge($bggStmt->fetchAll(), $manualStmt->fetchAll()) as $row) {
            $gameId = (int) ($row['game_id'] ?? 0);
            $ownerName = trim((string) ($row['owner_name'] ?? ''));
            if ((int) ($row['is_manual'] ?? 0) === 1) {
                $ownerName = trim(Auth::firstName($ownerName));
            }
            if ($gameId <= 0 || $ownerName === '') {
                continue;
            }
            if (!isset($ownerSetsByGame[$gameId])) {
                $ownerSetsByGame[$gameId] = [];
            }
            $ownerSetsByGame[$gameId][$ownerName] = true;
        }

        $ownersByGame = [];
        foreach ($ownerSetsByGame as $gameId => $ownerSet) {
            $names = array_keys($ownerSet);
            sort($names, SORT_NATURAL | SORT_FLAG_CASE);
            $ownersByGame[(int) $gameId] = implode(', ', $names);
        }

        return $ownersByGame;
    }

    /**
     * @param array<int, int> $ids
     */
    private static function bindGameIds(\PDOStatement $stmt, array $ids): void
    {
        foreach ($ids as $index => $id) {
            $stmt->bindValue(':id_' . $index, $id, PDO::PARAM_INT);
        }
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

    private static function randomOrderExpr(\PDO $pdo): string
    {
        $driver = (string) $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);
        return $driver === 'mysql' ? 'RAND()' : 'RANDOM()';
    }
}
