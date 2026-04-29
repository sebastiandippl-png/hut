<?php

declare(strict_types=1);

namespace Hut\controllers;

use Hut\Auth;
use Hut\BggThingFetcher;
use Hut\Game;
use Hut\PersonalCollection;
use Hut\UserGame;

class GameController
{
    public static function browse(array $params): void
    {
        Auth::requireLogin();

        $search = trim($_GET['q'] ?? '');
        $complexity = (string) ($_GET['complexity'] ?? '');
        if (!in_array($complexity, ['', 'light', 'medium', 'complex'], true)) {
            $complexity = '';
        }
        $selectedUsers = isset($_GET['users']) && is_array($_GET['users'])
            ? array_map('strval', array_filter($_GET['users']))
            : [];
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $browseComplexity = $search === '' ? $complexity : '';

        // Offer all known owner labels from BGG imports and manual personal ownership.
        $pdo = \Hut\Database::getInstance();
        $usersStmt = $pdo->query(
            'SELECT owner_name
             FROM (
                 SELECT bgg_user AS owner_name FROM user_collection
                 UNION
                 SELECT u.name AS owner_name
                 FROM user_personal_collection upc
                 JOIN users u ON u.id = upc.user_id
             ) owners
             ORDER BY owner_name'
        );
        $availableUsers = array_map(static fn ($row) => $row['owner_name'], $usersStmt->fetchAll(\PDO::FETCH_ASSOC));

        $userId = (int) Auth::user()['id'];

        $isDefaultView = $search === '' && empty($selectedUsers);
        if ($isDefaultView) {
            $games = Game::randomCollectionGames(24, $userId, $browseComplexity);
            $total = count($games);
            $perPage = 24;
        } else {
            $result = Game::browse($search, $selectedUsers, $page, 24, $userId, $browseComplexity);
            extract($result); // $games, $total, $page, $perPage
        }

        $complexity = $browseComplexity;

        BggThingFetcher::ensureForPage(array_column($games, 'id'));
        require __DIR__ . '/../../templates/games/browse.php';
    }

    public static function suggestions(array $params): void
    {
        Auth::requireLogin();

        header('Content-Type: application/json');
        echo json_encode([
            'items' => Game::suggestions((string) ($_GET['q'] ?? '')),
        ]);
    }

    public static function detail(array $params): void
    {
        Auth::requireLogin();

        $gameId = (int) $params['id'];
        $game = Game::find($gameId);
        if (!$game) {
            http_response_code(404);
            require __DIR__ . '/../../templates/404.php';
            return;
        }

        BggThingFetcher::ensureForPage([$gameId]);
        $game = Game::find($gameId) ?? $game;

        $userId = (int) Auth::user()['id'];
        $selectionState = UserGame::selectionState($userId, (int) $game['id']);
        $isSelectedByMe = $selectionState['selected_by_me'];
        $isInHut = $selectionState['in_hut'];
        $hearted    = \Hut\Vote::userHearted($userId, $game['id']);
        $hearts     = Game::getHeartCount($game['id']);
        $heartedBy  = \Hut\Vote::heartedBy($game['id']);
        $isInMyCollection = PersonalCollection::has($userId, $gameId);
        $residentNameMap = \Hut\Resident::firstNameToIdMap();

        require __DIR__ . '/../../templates/games/detail.php';
    }

    public static function toggleSelect(array $params): void
    {
        Auth::requireLogin();
        Auth::requireCsrf(true);

        $gameId  = (int) $params['id'];
        $userId  = (int) Auth::user()['id'];
        $selectedByMe = UserGame::toggle($userId, $gameId);

        // Keep hearts in sync with hut selection for this user.
        $isHearted = \Hut\Vote::userHearted($userId, $gameId);
        if ($selectedByMe && !$isHearted) {
            \Hut\Vote::toggleHeart($userId, $gameId);
        } elseif (!$selectedByMe && $isHearted) {
            \Hut\Vote::toggleHeart($userId, $gameId);
        }

        $selectionState = UserGame::selectionState($userId, $gameId);

        // AJAX-friendly response
        header('Content-Type: application/json');
        echo json_encode([
            'selectedByMe' => $selectionState['selected_by_me'],
            'inHut' => $selectionState['in_hut'],
        ]);
    }

    public static function adminRemoveFromHut(array $params): void
    {
        Auth::requireAdmin();
        Auth::requireCsrf();

        $gameId = (int) $params['id'];
        UserGame::clearForAll($gameId);

        $_SESSION['flash_success'] = 'Game removed from hut collection for all users.';
        header('Location: ' . \Hut\Url::to('/games/' . $gameId));
        exit;
    }

    public static function landing(array $params): void
    {
        Auth::requireLogin();

        $pdo = \Hut\Database::getInstance();

        // Selected games count + complexity buckets (reuse same query as statistics)
        $stmt = $pdo->query(
            'SELECT g.id, bt.averageweight
             FROM games g
             LEFT JOIN bgg_thing bt ON bt.bgg_id = g.id
             WHERE EXISTS (
                 SELECT 1 FROM user_games ug
                 WHERE ug.game_id = g.id AND ug.selected = 1
             )'
        );
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        $totalGames = count($rows);

        $complexityBuckets = self::labelsToBuckets(['Light', 'Medium', 'Complex', 'Unknown']);
        foreach ($rows as $row) {
            $complexity = isset($row['averageweight']) && $row['averageweight'] !== null
                ? (float) $row['averageweight']
                : null;
            if ($complexity === null || $complexity <= 0.0) {
                $complexityBuckets['Unknown']++;
            } elseif ($complexity <= 1.8) {
                $complexityBuckets['Light']++;
            } elseif ($complexity <= 3.0) {
                $complexityBuckets['Medium']++;
            } else {
                $complexityBuckets['Complex']++;
            }
        }
        $complexityData = self::toChartData($complexityBuckets);

        // Latest global activity (approved users only)
        $latestAdded   = \Hut\Resident::latestAddedGameGlobally();
        $latestHearted = \Hut\Resident::latestHeartedGameGlobally();

        // Most hearted game overall
        $mostHeartedStmt = $pdo->query(
            'SELECT g.id, g.name, COUNT(DISTINCT v.user_id) AS hearts
             FROM votes v
             JOIN games g ON g.id = v.game_id
             JOIN users u ON u.id = v.user_id
             WHERE u.is_approved = 1
             GROUP BY g.id, g.name
             ORDER BY hearts DESC, g.name ASC
             LIMIT 1'
        );
        $mostHearted = $mostHeartedStmt->fetch(\PDO::FETCH_ASSOC) ?: null;
        if ($mostHearted !== null) {
            $mostHearted['hearted_by'] = \Hut\Vote::heartedBy((int) $mostHearted['id']);
        }

        // Preload thumbnails for activity cards
        $thumbIds = array_filter([
            $latestAdded   !== null ? (int) $latestAdded['id']   : 0,
            $latestHearted !== null ? (int) $latestHearted['id'] : 0,
            $mostHearted   !== null ? (int) $mostHearted['id']   : 0,
        ]);
        if (!empty($thumbIds)) {
            BggThingFetcher::ensureForPage(array_values($thumbIds));
        }

        // Random link from the links page
        $driver = (string) $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);
        $randomFunc = $driver === 'mysql' ? 'RAND()' : 'RANDOM()';
        $randomLinkStmt = $pdo->query(
            'SELECT id, title, url, description, preview_image_url
             FROM links
             ORDER BY ' . $randomFunc . '
             LIMIT 1'
        );
        $randomLink = $randomLinkStmt->fetch(\PDO::FETCH_ASSOC) ?: null;

        // Weather – current conditions only
        $weatherToday = null;
        $weatherRaw   = \Hut\WeatherForecast::get();
        if ($weatherRaw !== null) {
            $current = $weatherRaw['current'] ?? [];
            $daily   = $weatherRaw['daily']   ?? [];
            $isDay   = (bool) ($current['is_day'] ?? 1);
            $wmoCode = (int)  ($current['weather_code'] ?? 0);
            $wmoInfo = \Hut\WeatherForecast::wmoInfo($wmoCode, $isDay);
            $weatherToday = [
                'temp'      => $current['temperature_2m']      ?? null,
                'feels_like'=> $current['apparent_temperature'] ?? null,
                'temp_max'  => $daily['temperature_2m_max'][0] ?? null,
                'temp_min'  => $daily['temperature_2m_min'][0] ?? null,
                'label'     => $wmoInfo['label'],
                'icon'      => $wmoInfo['icon'],
            ];
        }

        require __DIR__ . '/../../templates/home/landing.php';
    }

    public static function collection(array $params): void
    {
        Auth::requireLogin();
        $games = Game::collection(Auth::user()['id']);
        BggThingFetcher::ensureForPage(array_column($games, 'id'));
        $residentNameMap = \Hut\Resident::firstNameToIdMap();
        require __DIR__ . '/../../templates/games/collection.php';
    }

    public static function statistics(array $params): void
    {
        Auth::requireLogin();

        $pdo = \Hut\Database::getInstance();
        $stmt = $pdo->query(
            'SELECT g.id,
                    g.rank,
                    bt.averageweight,
                    bt.best_playercount_num,
                    bt.maxplaytime,
                    bt.categories,
                    bt.mechanics,
                    bt.designers,
                    COALESCE(v.hearts, 0) AS hearts
             FROM games g
             LEFT JOIN bgg_thing bt ON bt.bgg_id = g.id
             LEFT JOIN (
                 SELECT game_id, COUNT(DISTINCT user_id) AS hearts
                 FROM votes
                 GROUP BY game_id
             ) v ON v.game_id = g.id
             WHERE EXISTS (
                 SELECT 1
                 FROM user_games ug
                 WHERE ug.game_id = g.id
                   AND ug.selected = 1
             )'
        );

        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $complexityBuckets = self::labelsToBuckets(['Light', 'Medium', 'Complex', 'Unknown']);
        $bestWithBuckets = self::labelsToBuckets(['1', '2', '3', '4', '5', '6+', 'Unknown']);
        $durationBuckets = self::labelsToBuckets(['≤ 30 min', '31-60 min', '61-90 min', '91-120 min', '> 120 min', 'Unknown']);
        $heartsBuckets = self::labelsToBuckets(['0 hearts', '1-2 hearts', '3-5 hearts', '6+ hearts']);
        $rankBuckets = self::labelsToBuckets(['Top 100', '101-500', '501-2000', '2001+', 'Unranked']);

        foreach ($rows as $row) {
            $complexity = isset($row['averageweight']) && $row['averageweight'] !== null
                ? (float) $row['averageweight']
                : null;
            if ($complexity === null || $complexity <= 0.0) {
                $complexityBuckets['Unknown']++;
            } elseif ($complexity <= 1.8) {
                $complexityBuckets['Light']++;
            } elseif ($complexity <= 3.0) {
                $complexityBuckets['Medium']++;
            } else {
                $complexityBuckets['Complex']++;
            }

            $bestWith = isset($row['best_playercount_num']) && $row['best_playercount_num'] !== null
                ? (int) $row['best_playercount_num']
                : null;
            if ($bestWith === null || $bestWith <= 0) {
                $bestWithBuckets['Unknown']++;
            } elseif ($bestWith >= 6) {
                $bestWithBuckets['6+']++;
            } else {
                $bestWithBuckets[(string) $bestWith]++;
            }

            $maxDuration = isset($row['maxplaytime']) && $row['maxplaytime'] !== null
                ? (int) $row['maxplaytime']
                : null;
            if ($maxDuration === null || $maxDuration <= 0) {
                $durationBuckets['Unknown']++;
            } elseif ($maxDuration <= 30) {
                $durationBuckets['≤ 30 min']++;
            } elseif ($maxDuration <= 60) {
                $durationBuckets['31-60 min']++;
            } elseif ($maxDuration <= 90) {
                $durationBuckets['61-90 min']++;
            } elseif ($maxDuration <= 120) {
                $durationBuckets['91-120 min']++;
            } else {
                $durationBuckets['> 120 min']++;
            }

            $hearts = isset($row['hearts']) ? (int) $row['hearts'] : 0;
            if ($hearts <= 0) {
                $heartsBuckets['0 hearts']++;
            } elseif ($hearts <= 2) {
                $heartsBuckets['1-2 hearts']++;
            } elseif ($hearts <= 5) {
                $heartsBuckets['3-5 hearts']++;
            } else {
                $heartsBuckets['6+ hearts']++;
            }

            $rank = isset($row['rank']) && $row['rank'] !== null ? (int) $row['rank'] : null;
            if ($rank === null || $rank <= 0) {
                $rankBuckets['Unranked']++;
            } elseif ($rank <= 100) {
                $rankBuckets['Top 100']++;
            } elseif ($rank <= 500) {
                $rankBuckets['101-500']++;
            } elseif ($rank <= 2000) {
                $rankBuckets['501-2000']++;
            } else {
                $rankBuckets['2001+']++;
            }
        }

        $totalGames = count($rows);
        $complexityData = self::toChartData($complexityBuckets);
        $bestWithData = self::toChartData($bestWithBuckets);
        $durationData = self::toChartData($durationBuckets);
        $heartsData = self::toChartData($heartsBuckets);
        $rankDistributionData = self::toChartData($rankBuckets);

        // Build top-10 frequency lists for categories, mechanics, designers
        $categoryCounts = [];
        $mechanicCounts = [];
        $designerCounts = [];

        foreach ($rows as $row) {
            foreach (['categories' => &$categoryCounts, 'mechanics' => &$mechanicCounts, 'designers' => &$designerCounts] as $col => &$counter) {
                $raw = isset($row[$col]) && $row[$col] !== null ? (string) $row[$col] : '';
                if ($raw === '') {
                    continue;
                }
                foreach (explode(',', $raw) as $item) {
                    $item = trim($item);
                    if ($item === '') {
                        continue;
                    }
                    $counter[$item] = ($counter[$item] ?? 0) + 1;
                }
            }
        }
        unset($counter);

        $topCategories = self::topN($categoryCounts, 10);
        $topMechanics  = self::topN($mechanicCounts, 10);
        $topDesigners  = self::topN($designerCounts, 10);

        require __DIR__ . '/../../templates/games/statistics.php';
    }

    public static function changelog(array $params): void
    {
        Auth::requireLogin();

        $changelogPath = __DIR__ . '/../../storage/changelog.json';
        $entries = [];
        $generatedAt = null;
        $repoUrl = null;

        if (is_file($changelogPath)) {
            $raw = file_get_contents($changelogPath);
            if (is_string($raw) && $raw !== '') {
                $decoded = json_decode($raw, true);
                if (is_array($decoded)) {
                    $entriesRaw = $decoded['entries'] ?? [];
                    if (is_array($entriesRaw)) {
                        foreach ($entriesRaw as $entry) {
                            if (!is_array($entry)) {
                                continue;
                            }

                            $entries[] = [
                                'hash' => (string) ($entry['hash'] ?? ''),
                                'short_hash' => (string) ($entry['short_hash'] ?? ''),
                                'date' => (string) ($entry['date'] ?? ''),
                                'author' => (string) ($entry['author'] ?? ''),
                                'subject' => (string) ($entry['subject'] ?? ''),
                                'url' => (string) ($entry['url'] ?? ''),
                            ];
                        }
                    }

                    $generatedAt = isset($decoded['generated_at']) ? (string) $decoded['generated_at'] : null;
                    $repoUrl = isset($decoded['repo_url']) ? (string) $decoded['repo_url'] : null;
                }
            }
        }

        require __DIR__ . '/../../templates/info/changelog.php';
    }

    public static function addToMyCollection(array $params): void
    {
        Auth::requireLogin();
        Auth::requireCsrf();

        $gameId = (int) $params['id'];
        $game = Game::find($gameId);
        if (!$game) {
            http_response_code(404);
            require __DIR__ . '/../../templates/404.php';
            return;
        }

        $userId = (int) Auth::user()['id'];
        $added = PersonalCollection::add($userId, $gameId);

        $_SESSION['flash_success'] = $added
            ? 'Added to your personal collection.'
            : 'This game is already in your personal collection.';

        header('Location: ' . \Hut\Url::to('/games/' . $gameId));
        exit;
    }

    public static function removeFromMyCollection(array $params): void
    {
        Auth::requireLogin();
        Auth::requireCsrf();

        $gameId = (int) $params['id'];
        $game = Game::find($gameId);
        if (!$game) {
            http_response_code(404);
            require __DIR__ . '/../../templates/404.php';
            return;
        }

        $userId = (int) Auth::user()['id'];
        $removed = PersonalCollection::remove($userId, $gameId);

        $_SESSION['flash_success'] = $removed
            ? 'Removed from your personal collection.'
            : 'This game is not in your personal collection.';

        header('Location: ' . \Hut\Url::to('/games/' . $gameId));
        exit;
    }

    /**
     * Return the top-N entries from a frequency map, sorted by count desc.
     *
     * @param array<string,int> $counts
     * @return list<array{label:string,count:int}>
     */
    private static function topN(array $counts, int $n): array
    {
        arsort($counts);
        $result = [];
        $i = 0;
        foreach ($counts as $label => $count) {
            if ($i >= $n) {
                break;
            }
            $result[] = ['label' => $label, 'count' => $count];
            $i++;
        }
        return $result;
    }

    /**
     * @param list<string> $labels
     * @return array<string,int>
     */
    private static function labelsToBuckets(array $labels): array
    {
        $buckets = [];
        foreach ($labels as $label) {
            $buckets[$label] = 0;
        }
        return $buckets;
    }

    /**
     * @param array<string,int> $buckets
     * @return list<array{label:string,count:int}>
     */
    private static function toChartData(array $buckets): array
    {
        $data = [];
        foreach ($buckets as $label => $count) {
            $data[] = [
                'label' => $label,
                'count' => (int) $count,
            ];
        }
        return $data;
    }
}
