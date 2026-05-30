<?php

declare(strict_types=1);

namespace Hut;

class FoodSuggestion
{
    public static function listForPage(int $currentUserId): array
    {
        $pdo = Database::getInstance();
        $heartedByExpr = self::groupConcatNamesExpr($pdo, true, 'u2');

        $stmt = $pdo->prepare(<<<SQL
            SELECT fs.id,
                   fs.user_id,
                   fs.title,
                   fs.notes,
                     fs.image_url,
                     fs.image_source_url,
                     fs.image_creator,
                     fs.image_license,
                   fs.created_at,
                   u.name AS suggested_by_name,
                   COALESCE(h.hearts, 0) AS hearts,
                   COALESCE(h.hearted_by, '') AS hearted_by,
                   EXISTS(
                       SELECT 1
                       FROM food_votes fv_self
                       WHERE fv_self.food_suggestion_id = fs.id
                         AND fv_self.user_id = :current_user_id
                   ) AS hearted_by_me
            FROM food_suggestions fs
            JOIN users u ON u.id = fs.user_id
            LEFT JOIN (
                SELECT fv.food_suggestion_id,
                       COUNT(DISTINCT fv.user_id) AS hearts,
                       {$heartedByExpr} AS hearted_by
                FROM food_votes fv
                JOIN users u2 ON u2.id = fv.user_id
                GROUP BY fv.food_suggestion_id
            ) h ON h.food_suggestion_id = fs.id
            ORDER BY COALESCE(h.hearts, 0) DESC,
                     fs.created_at ASC,
                     fs.title ASC
        SQL);
        $stmt->execute([':current_user_id' => $currentUserId]);
        $rows = $stmt->fetchAll();

        return array_map(static function (array $row): array {
            if (isset($row['hearted_by']) && $row['hearted_by'] !== '') {
                $row['hearted_by'] = Auth::firstNames((string) $row['hearted_by']);
            } else {
                $row['hearted_by'] = 'No hearts yet';
            }

            return $row;
        }, $rows);
    }

    public static function create(int $userId, string $title, string $notes = ''): int
    {
        $pdo = Database::getInstance();
        $stmt = $pdo->prepare('INSERT INTO food_suggestions (user_id, title, notes) VALUES (?, ?, ?)');
        $stmt->execute([$userId, trim($title), trim($notes)]);

        return (int) $pdo->lastInsertId();
    }

    public static function attachImageFromSearch(int $foodSuggestionId, string $title): void
    {
        $image = self::searchDishImage($title);
        if ($image === null) {
            return;
        }

        $pdo = Database::getInstance();
        $stmt = $pdo->prepare(
            'UPDATE food_suggestions
             SET image_url = ?, image_source_url = ?, image_creator = ?, image_license = ?
             WHERE id = ?'
        );
        $stmt->execute([
            $image['url'],
            $image['source_url'],
            $image['creator'],
            $image['license'],
            $foodSuggestionId,
        ]);
    }

    public static function find(int $id): ?array
    {
        $pdo = Database::getInstance();
        $stmt = $pdo->prepare('SELECT * FROM food_suggestions WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);

        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function deleteIfOwned(int $id, int $userId): bool
    {
        $pdo = Database::getInstance();
        $stmt = $pdo->prepare('DELETE FROM food_suggestions WHERE id = ? AND user_id = ?');
        $stmt->execute([$id, $userId]);

        return $stmt->rowCount() > 0;
    }

    public static function updateIfOwned(int $id, int $userId, string $title, string $notes = ''): bool
    {
        $pdo = Database::getInstance();
        $stmt = $pdo->prepare(
            'UPDATE food_suggestions
             SET title = ?, notes = ?
             WHERE id = ? AND user_id = ?'
        );
        $stmt->execute([trim($title), trim($notes), $id, $userId]);

        return $stmt->rowCount() > 0;
    }

    public static function toggleHeart(int $userId, int $foodSuggestionId): bool
    {
        $pdo = Database::getInstance();
        $driver = (string) $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);
        $stmt = $pdo->prepare('SELECT 1 FROM food_votes WHERE user_id = ? AND food_suggestion_id = ? LIMIT 1');
        $stmt->execute([$userId, $foodSuggestionId]);
        $exists = $stmt->fetchColumn() !== false;

        if ($exists) {
            $pdo->prepare('DELETE FROM food_votes WHERE user_id = ? AND food_suggestion_id = ?')
                ->execute([$userId, $foodSuggestionId]);
            return false;
        }

        $upsertSql = $driver === 'mysql'
            ? 'INSERT INTO food_votes (user_id, food_suggestion_id, created_at) VALUES (?, ?, CURRENT_TIMESTAMP) ON DUPLICATE KEY UPDATE created_at = VALUES(created_at)'
            : 'INSERT INTO food_votes (user_id, food_suggestion_id) VALUES (?, ?)';

        $pdo->prepare($upsertSql)->execute([$userId, $foodSuggestionId]);

        return true;
    }

    public static function heartCount(int $foodSuggestionId): int
    {
        $pdo = Database::getInstance();
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM food_votes WHERE food_suggestion_id = ?');
        $stmt->execute([$foodSuggestionId]);

        return (int) $stmt->fetchColumn();
    }

    public static function heartedBy(int $foodSuggestionId): string
    {
        $pdo = Database::getInstance();
        $groupConcat = self::groupConcatNamesExpr($pdo, false);
        $stmt = $pdo->prepare(
            'SELECT ' . $groupConcat . '
             FROM food_votes fv
             JOIN users u ON u.id = fv.user_id
             WHERE fv.food_suggestion_id = ?'
        );
        $stmt->execute([$foodSuggestionId]);

        $names = $stmt->fetchColumn();
        return $names ? Auth::firstNames((string) $names) : 'No hearts yet';
    }

    /**
     * @return array{url:string,source_url:string,creator:string,license:string}|null
     */
    private static function searchDishImage(string $title): ?array
    {
        $query = trim(preg_replace('/\s+/', ' ', $title) ?? $title);
        if ($query === '') {
            return null;
        }

        $englishQuery = self::translateFoodQueryToEnglish($query);
        $candidates = array_values(array_unique(array_filter([
            $englishQuery,
            $englishQuery . ' food',
            $englishQuery . ' dish',
            $englishQuery . ' recipe',
            $query,
            $query . ' food',
            $query . ' dish',
            $query . ' recipe',
        ])));
        foreach ($candidates as $candidate) {
            $image = self::fetchOpenverseImage($candidate);
            if ($image !== null) {
                return $image;
            }
        }

        return null;
    }

    private static function translateFoodQueryToEnglish(string $query): string
    {
        $translated = strtr($query, [
            'ä' => 'ae',
            'ö' => 'oe',
            'ü' => 'ue',
            'Ä' => 'Ae',
            'Ö' => 'Oe',
            'Ü' => 'Ue',
            'ß' => 'ss',
        ]);

        $replacements = [
            'käs' => 'cheese',
            'kaese' => 'cheese',
            'käse' => 'cheese',
            'kartoffel' => 'potato',
            'nudel' => 'pasta',
            'spätzle' => 'spaetzle',
            'spae tzle' => 'spaetzle',
            'suppe' => 'soup',
            'auflauf' => 'casserole',
            'pfanne' => 'skillet',
            'braten' => 'roast',
            'gemüse' => 'vegetable',
            'gemuese' => 'vegetable',
            'hähnchen' => 'chicken',
            'haehnchen' => 'chicken',
            'huhn' => 'chicken',
            'schwein' => 'pork',
            'rind' => 'beef',
            'fisch' => 'fish',
            'salat' => 'salad',
            'brot' => 'bread',
            'reis' => 'rice',
            'gemischt' => 'mixed',
            'vegetarisch' => 'vegetarian',
            'vegan' => 'vegan',
            'tomate' => 'tomato',
            'tomaten' => 'tomato',
            'zwiebel' => 'onion',
            'zwiebeln' => 'onion',
            'schnitzel' => 'cutlet',
        ];

        return trim(str_ireplace(array_keys($replacements), array_values($replacements), $translated));
    }

    /**
     * @return array{url:string,source_url:string,creator:string,license:string}|null
     */
    private static function fetchOpenverseImage(string $query): ?array
    {
        $url = 'https://api.openverse.org/v1/images/?page_size=1&q=' . rawurlencode($query);
        $context = stream_context_create([
            'http' => [
                'timeout' => 5,
                'method' => 'GET',
                'header' => "Accept: application/json\r\nUser-Agent: HutFoodBot/1.0\r\n",
            ],
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
            ],
        ]);

        $response = @file_get_contents($url, false, $context);
        if ($response === false || $response === '') {
            return null;
        }

        $data = json_decode($response, true);
        if (!is_array($data) || empty($data['results'][0]) || !is_array($data['results'][0])) {
            return null;
        }

        $result = $data['results'][0];
        $imageUrl = trim((string) ($result['thumbnail'] ?? $result['url'] ?? ''));
        if ($imageUrl === '' || !preg_match('#^https?://#i', $imageUrl)) {
            return null;
        }

        $sourceUrl = trim((string) ($result['foreign_landing_url'] ?? $result['detail_url'] ?? ''));
        if ($sourceUrl !== '' && !preg_match('#^https?://#i', $sourceUrl)) {
            $sourceUrl = '';
        }

        $creator = trim((string) ($result['creator'] ?? ''));
        $license = trim((string) (($result['license'] ?? '') . ' ' . ($result['license_version'] ?? '')));

        return [
            'url' => $imageUrl,
            'source_url' => $sourceUrl !== '' ? $sourceUrl : $imageUrl,
            'creator' => $creator,
            'license' => $license,
        ];
    }

    private static function groupConcatNamesExpr(\PDO $pdo, bool $distinct, string $alias = 'u'): string
    {
        $driver = (string) $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);
        if ($driver === 'mysql') {
            return $distinct
                ? 'GROUP_CONCAT(DISTINCT ' . $alias . '.name SEPARATOR ", ")'
                : 'GROUP_CONCAT(' . $alias . '.name SEPARATOR ", ")';
        }

        return $distinct
            ? 'GROUP_CONCAT(DISTINCT ' . $alias . '.name)'
            : 'GROUP_CONCAT(' . $alias . '.name, ", ")';
    }
}