<?php

declare(strict_types=1);

namespace Hut;

use PDO;

/**
 * Fetches full game data from the BGG XML API2 /thing endpoint and stores it
 * in the bgg_thing table. Also downloads thumbnail images to a local disk cache.
 *
 * Data is considered stale and will be refreshed when last_updated is older
 * than one week.
 *
 * API rules from Learnings.md:
 *  - Batch up to 20 IDs per request
 *  - 5-second delay between batches
 *  - HTTP 202 = data pending cache, still valid — treat as success
 *  - Retry with exponential backoff on 429/503
 */
class BggThingFetcher
{
    private const API_BASE    = 'https://boardgamegeek.com/xmlapi2/thing';
    private const ALLOWED_REMOTE_HOSTS = [
        'boardgamegeek.com',
        'cf.geekdo-images.com',
        'geekdo-images.com',
        'cf.geekdo-static.com',
    ];
    private const BATCH_SIZE  = 20;
    private const DELAY_US    = 5_000_000; // 5 s between batches
    private const MAX_RETRIES = 3;
    private const STALE_DAYS  = 7;

    private static string $cacheDir = '';

    private static function cacheDir(): string
    {
        if (self::$cacheDir === '') {
            self::$cacheDir = dirname(__DIR__) . '/public/cache/thumbnails';
        }
        return self::$cacheDir;
    }

    /**
     * Return the public URL for a cached thumbnail, or null if not yet downloaded.
     */
    public static function localUrl(int $gameId): ?string
    {
        if (file_exists(self::cacheDir() . '/' . $gameId . '.jpg')) {
            return '/cache/thumbnails/' . $gameId . '.jpg';
        }
        return null;
    }

    /**
     * Ensure bgg_thing data is present and fresh for the given game IDs.
     * Fetches from the BGG API for IDs with no data or stale data (> 7 days).
     * Also downloads thumbnail images to the local disk cache.
     */
    public static function ensureForPage(array $gameIds): void
    {
        if (empty($gameIds)) {
            return;
        }

        $pdo       = Database::getInstance();
        $in        = implode(',', array_map('intval', $gameIds));
        $staleDate = date('Y-m-d H:i:s', strtotime('-' . self::STALE_DAYS . ' days'));

        // Find IDs that are missing or stale
        $stmt = $pdo->query(
            "SELECT bgg_id FROM bgg_thing
             WHERE bgg_id IN ($in)
               AND last_updated IS NOT NULL
               AND last_updated > '$staleDate'
               AND (
                    player_best_values IS NOT NULL
                    OR player_recommended_values IS NOT NULL
                    OR player_not_recommended_values IS NOT NULL
               )"
        );
        $fresh    = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
        $needsApi = array_values(array_diff($gameIds, $fresh));

        if (!empty($needsApi)) {
            $fetched = self::fetchFromBgg($needsApi);
            self::upsertAll($pdo, $fetched);
        }

        // Download any missing thumbnail image files
        $stmt     = $pdo->query(
            "SELECT bgg_id, thumbnail FROM bgg_thing
             WHERE bgg_id IN ($in) AND thumbnail IS NOT NULL AND thumbnail != ''"
        );
        $urlMap   = [];
        foreach ($stmt->fetchAll() as $row) {
            $id = (int) $row['bgg_id'];
            if (!file_exists(self::cacheDir() . '/' . $id . '.jpg')) {
                $urlMap[$id] = $row['thumbnail'];
            }
        }
        if (!empty($urlMap)) {
            self::downloadImages($urlMap);
        }
    }

    // ─── Private helpers ────────────────────────────────────────────────────

    /**
     * Query the BGG /thing API (with stats=1) and return an array of parsed rows.
     *
     * @return array<int, array<string, mixed>>  keyed by bgg_id
     */
    private static function fetchFromBgg(array $ids): array
    {
        $result  = [];
        $batches = array_chunk($ids, self::BATCH_SIZE);

        foreach ($batches as $i => $batch) {
            $url  = self::API_BASE . '?stats=1&id=' . implode(',', array_map('intval', $batch));
            $body = self::httpGet($url);

            if ($body !== null) {
                try {
                    $doc = simplexml_load_string($body, 'SimpleXMLElement', LIBXML_NONET | LIBXML_NOCDATA);
                    if ($doc === false) {
                        continue;
                    }
                    foreach ($doc->item as $item) {
                        $row = self::parseItem($item);
                        if ($row !== null) {
                            $result[$row['bgg_id']] = $row;
                        }
                    }
                } catch (\Exception) {
                    // Skip malformed response
                }
            }

            if ($i < count($batches) - 1) {
                usleep(self::DELAY_US);
            }
        }

        return $result;
    }

    /**
     * Parse a single <item> element from the BGG thing API into a flat array.
     *
     * @return array<string, mixed>|null
     */
    private static function parseItem(\SimpleXMLElement $item): ?array
    {
        $id = (int) ($item['id'] ?? 0);
        if ($id <= 0) {
            return null;
        }

        $thumbnail = trim((string) ($item->thumbnail ?? ''));
        if ($thumbnail !== '' && str_starts_with($thumbnail, '//')) {
            $thumbnail = 'https:' . $thumbnail;
        }

        $image = trim((string) ($item->image ?? ''));
        if ($image !== '' && str_starts_with($image, '//')) {
            $image = 'https:' . $image;
        }

        $primaryName = '';
        foreach ($item->name as $nameEl) {
            if ((string) ($nameEl['type'] ?? '') === 'primary') {
                $primaryName = (string) $nameEl['value'];
                break;
            }
        }

        $description   = trim((string) ($item->description ?? ''));
        $yearpublished = (int) ($item->yearpublished['value'] ?? 0) ?: null;
        $minplayers    = (int) ($item->minplayers['value'] ?? 0) ?: null;
        $maxplayers    = (int) ($item->maxplayers['value'] ?? 0) ?: null;
        $minage        = (int) ($item->minage['value'] ?? 0) ?: null;
        $minplaytime   = (int) ($item->minplaytime['value'] ?? 0) ?: null;
        $maxplaytime   = (int) ($item->maxplaytime['value'] ?? 0) ?: null;

        $categories = [];
        $mechanics  = [];
        $designers  = [];
        $publishers = [];
        $families   = [];
        foreach ($item->link as $link) {
            $type  = (string) ($link['type'] ?? '');
            $value = (string) ($link['value'] ?? '');
            match ($type) {
                'boardgamecategory' => $categories[] = $value,
                'boardgamemechanic' => $mechanics[]  = $value,
                'boardgamedesigner' => $designers[]  = $value,
                'boardgamepublisher' => $publishers[] = $value,
                'boardgamefamily'   => $families[]   = $value,
                default             => null,
            };
        }

        $ratings     = $item->statistics->ratings ?? null;
        $usersrated  = $ratings ? (int) ($ratings->usersrated['value'] ?? 0) ?: null : null;
        $average     = $ratings ? (float) ($ratings->average['value'] ?? 0.0) ?: null : null;
        $bayesavg    = $ratings ? (float) ($ratings->bayesaverage['value'] ?? 0.0) ?: null : null;
        $stddev      = $ratings ? (float) ($ratings->stddev['value'] ?? 0.0) ?: null : null;
        $owned       = $ratings ? (int) ($ratings->owned['value'] ?? 0) ?: null : null;
        $wanting     = $ratings ? (int) ($ratings->wanting['value'] ?? 0) ?: null : null;
        $wishing     = $ratings ? (int) ($ratings->wishing['value'] ?? 0) ?: null : null;
        $numweights  = $ratings ? (int) ($ratings->numweights['value'] ?? 0) ?: null : null;
        $avgweight   = $ratings ? (float) ($ratings->averageweight['value'] ?? 0.0) ?: null : null;
        $pollData    = self::parseSuggestedNumPlayers($item);

        return [
            'bgg_id'        => $id,
            'thumbnail'     => $thumbnail !== '' ? $thumbnail : null,
            'image'         => $image !== '' ? $image : null,
            'primary_name'  => $primaryName !== '' ? $primaryName : null,
            'description'   => $description !== '' ? $description : null,
            'yearpublished' => $yearpublished,
            'minplayers'    => $minplayers,
            'maxplayers'    => $maxplayers,
            'minage'        => $minage,
            'minplaytime'   => $minplaytime,
            'maxplaytime'   => $maxplaytime,
            'categories'    => $categories ? implode(', ', $categories) : null,
            'mechanics'     => $mechanics  ? implode(', ', $mechanics)  : null,
            'designers'     => $designers  ? implode(', ', $designers)  : null,
            'publishers'    => $publishers ? implode(', ', $publishers) : null,
            'families'      => $families   ? implode(', ', $families)   : null,
            'usersrated'    => $usersrated,
            'average'       => $average,
            'bayesaverage'  => $bayesavg,
            'stddev'        => $stddev,
            'owned'         => $owned,
            'wanting'       => $wanting,
            'wishing'       => $wishing,
            'numweights'    => $numweights,
            'averageweight' => $avgweight,
            'best_playercount' => $pollData['best_playercount'],
            'best_playercount_num' => $pollData['best_playercount_num'],
            'player_best_values' => $pollData['player_best_values'],
            'player_recommended_values' => $pollData['player_recommended_values'],
            'player_not_recommended_values' => $pollData['player_not_recommended_values'],
            'last_updated'  => date('Y-m-d H:i:s'),
        ];
    }

    /**
     * Parse the suggested_numplayers poll into compact flat strings.
     *
     * Output format for values is "numplayers:votes|numplayers:votes".
     *
     * @return array<string, int|string|null>
     */
    private static function parseSuggestedNumPlayers(\SimpleXMLElement $item): array
    {
        $bestValues = [];
        $recommendedValues = [];
        $notRecommendedValues = [];

        foreach ($item->poll as $poll) {
            if ((string) ($poll['name'] ?? '') !== 'suggested_numplayers') {
                continue;
            }

            foreach ($poll->results as $results) {
                $numPlayers = trim((string) ($results['numplayers'] ?? ''));
                if ($numPlayers === '') {
                    continue;
                }

                $bestVotes = 0;
                $recommendedVotes = 0;
                $notRecommendedVotes = 0;

                foreach ($results->result as $result) {
                    $value = trim((string) ($result['value'] ?? ''));
                    $votes = (int) ($result['numvotes'] ?? 0);

                    if ($value === 'Best') {
                        $bestVotes = $votes;
                    } elseif ($value === 'Recommended') {
                        $recommendedVotes = $votes;
                    } elseif ($value === 'Not Recommended') {
                        $notRecommendedVotes = $votes;
                    }
                }

                $key = '_' . $numPlayers; // prefix prevents int-coercion by PHP
                $bestValues[$key] = $bestVotes;
                $recommendedValues[$key] = $recommendedVotes;
                $notRecommendedValues[$key] = $notRecommendedVotes;            }

            break;
        }

        if (empty($bestValues) && empty($recommendedValues) && empty($notRecommendedValues)) {
            return [
                'best_playercount' => null,
                'best_playercount_num' => null,
                'player_best_values' => null,
                'player_recommended_values' => null,
                'player_not_recommended_values' => null,
            ];
        }

        $bestPlayerCount = null;
        $bestPlayerCountNum = null;
        $bestScore = -1;
        $recommendedScore = -1;

        foreach ($bestValues as $key => $votes) {
            $recVotes = $recommendedValues[$key] ?? 0;
            if ($votes > $bestScore || ($votes === $bestScore && $recVotes > $recommendedScore)) {
                $bestScore = $votes;
                $recommendedScore = $recVotes;
                $bestPlayerCount = ltrim((string) $key, '_');
                $bestPlayerCountNum = self::numPlayersToInt($bestPlayerCount);
            }
        }

        return [
            'best_playercount' => $bestPlayerCount,
            'best_playercount_num' => $bestPlayerCountNum,
            'player_best_values' => self::encodePollMap($bestValues),
            'player_recommended_values' => self::encodePollMap($recommendedValues),
            'player_not_recommended_values' => self::encodePollMap($notRecommendedValues),
        ];
    }

    /**
     * @param array<string, int> $map  Keys are prefixed with '_' to stay string-typed.
     */
    private static function encodePollMap(array $map): ?string
    {
        if (empty($map)) {
            return null;
        }

        $parts = [];
        foreach ($map as $key => $value) {
            $parts[] = ltrim((string) $key, '_') . ':' . $value;
        }
        return implode('|', $parts);
    }

    private static function numPlayersToInt(int|string $label): ?int
    {
        if (preg_match('/^(\d+)/', (string) $label, $match) === 1) {
            return (int) $match[1];
        }
        return null;
    }

    /**
     * Upsert an array of parsed thing rows into the bgg_thing table.
     *
     * @param array<int, array<string, mixed>> $rows
     */
    private static function upsertAll(\PDO $pdo, array $rows): void
    {
        if (empty($rows)) {
            return;
        }

        $driver = (string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        $updateColumns = [
            'thumbnail',
            'image',
            'primary_name',
            'description',
            'yearpublished',
            'minplayers',
            'maxplayers',
            'minage',
            'minplaytime',
            'maxplaytime',
            'categories',
            'mechanics',
            'designers',
            'publishers',
            'families',
            'usersrated',
            'average',
            'bayesaverage',
            'stddev',
            'owned',
            'wanting',
            'wishing',
            'numweights',
            'averageweight',
            'best_playercount',
            'best_playercount_num',
            'player_best_values',
            'player_recommended_values',
            'player_not_recommended_values',
            'last_updated',
        ];

        $updates = implode(",\n                ", array_map(
            static fn (string $column): string => $driver === 'mysql'
                ? $column . ' = VALUES(' . $column . ')'
                : $column . ' = excluded.' . $column,
            $updateColumns
        ));

        $upsertClause = $driver === 'mysql'
            ? 'ON DUPLICATE KEY UPDATE'
            : 'ON CONFLICT(bgg_id) DO UPDATE SET';

        $sql = <<<SQL
            INSERT INTO bgg_thing (
                bgg_id, thumbnail, image, primary_name, description,
                yearpublished, minplayers, maxplayers, minage, minplaytime, maxplaytime,
                categories, mechanics, designers, publishers, families,
                usersrated, average, bayesaverage, stddev,
                owned, wanting, wishing, numweights, averageweight,
                best_playercount, best_playercount_num,
                player_best_values, player_recommended_values, player_not_recommended_values,
                last_updated
            ) VALUES (
                :bgg_id, :thumbnail, :image, :primary_name, :description,
                :yearpublished, :minplayers, :maxplayers, :minage, :minplaytime, :maxplaytime,
                :categories, :mechanics, :designers, :publishers, :families,
                :usersrated, :average, :bayesaverage, :stddev,
                :owned, :wanting, :wishing, :numweights, :averageweight,
                :best_playercount, :best_playercount_num,
                :player_best_values, :player_recommended_values, :player_not_recommended_values,
                :last_updated
            )
            {$upsertClause}
                {$updates}
        SQL;

        $stmt = $pdo->prepare($sql);
        foreach ($rows as $row) {
            $stmt->execute([
                ':bgg_id'        => $row['bgg_id'],
                ':thumbnail'     => $row['thumbnail'],
                ':image'         => $row['image'],
                ':primary_name'  => $row['primary_name'],
                ':description'   => $row['description'],
                ':yearpublished' => $row['yearpublished'],
                ':minplayers'    => $row['minplayers'],
                ':maxplayers'    => $row['maxplayers'],
                ':minage'        => $row['minage'],
                ':minplaytime'   => $row['minplaytime'],
                ':maxplaytime'   => $row['maxplaytime'],
                ':categories'    => $row['categories'],
                ':mechanics'     => $row['mechanics'],
                ':designers'     => $row['designers'],
                ':publishers'    => $row['publishers'],
                ':families'      => $row['families'],
                ':usersrated'    => $row['usersrated'],
                ':average'       => $row['average'],
                ':bayesaverage'  => $row['bayesaverage'],
                ':stddev'        => $row['stddev'],
                ':owned'         => $row['owned'],
                ':wanting'       => $row['wanting'],
                ':wishing'       => $row['wishing'],
                ':numweights'    => $row['numweights'],
                ':averageweight' => $row['averageweight'],
                ':best_playercount' => $row['best_playercount'],
                ':best_playercount_num' => $row['best_playercount_num'],
                ':player_best_values' => $row['player_best_values'],
                ':player_recommended_values' => $row['player_recommended_values'],
                ':player_not_recommended_values' => $row['player_not_recommended_values'],
                ':last_updated'  => $row['last_updated'],
            ]);
        }
    }

    /**
     * Download image files and save to the disk cache.
     *
     * @param array<int, string> $urlMap [gameId => bggThumbnailUrl]
     */
    private static function downloadImages(array $urlMap): void
    {
        $dir = self::cacheDir();
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        foreach ($urlMap as $id => $url) {
            $dest = $dir . '/' . $id . '.jpg';
            if (file_exists($dest)) {
                continue;
            }

            if (!self::isAllowedRemoteUrl($url)) {
                continue;
            }

            $imageData = self::httpGet($url);
            if ($imageData !== null && strlen($imageData) > 0) {
                file_put_contents($dest, $imageData, LOCK_EX);
            }
        }
    }

    private static function isAllowedRemoteUrl(string $url): bool
    {
        $parsed = parse_url($url);
        if (!is_array($parsed)) {
            return false;
        }

        $scheme = strtolower((string) ($parsed['scheme'] ?? ''));
        $host = strtolower((string) ($parsed['host'] ?? ''));
        if ($scheme !== 'https' || $host === '') {
            return false;
        }

        return in_array($host, self::ALLOWED_REMOTE_HOSTS, true);
    }

    /**
     * HTTP GET with retry + exponential backoff.
     * Returns the response body or null on failure.
     */
    private static function httpGet(string $url): ?string
    {
        if (!self::isAllowedRemoteUrl($url)) {
            return null;
        }

        $delay = 1_000_000; // 1 s initial backoff
        $token = trim((string) ($_ENV['BGG_TOKEN'] ?? ''));
        $headers = [
            'User-Agent: HutApp/1.0 (board game voting app)',
        ];
        if ($token !== '') {
            $headers[] = 'Authorization: Bearer ' . $token;
        }

        for ($attempt = 0; $attempt < self::MAX_RETRIES; $attempt++) {
            $context = stream_context_create([
                'http' => [
                    'timeout'       => 30,
                    'header'        => implode("\r\n", $headers),
                    'ignore_errors' => true,
                ],
            ]);

            $body = @file_get_contents($url, false, $context);

            $statusCode = 200;
            if (!empty($http_response_header)) {
                preg_match('#HTTP/\S+ (\d+)#', $http_response_header[0], $m);
                $statusCode = (int) ($m[1] ?? 200);
            }

            if ($body !== false && in_array($statusCode, [200, 202], true)) {
                return $body;
            }

            if (in_array($statusCode, [429, 503], true) && $attempt < self::MAX_RETRIES - 1) {
                usleep($delay);
                $delay *= 2;
            } else {
                break;
            }
        }

        return null;
    }
}
