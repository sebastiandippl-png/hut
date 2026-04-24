<?php

declare(strict_types=1);

namespace Hut\controllers;

use Hut\Auth;
use Hut\Database;

class LinksController
{
    public static function showLinks(array $params): void
    {
        Auth::requireLogin();

        $pdo = Database::getInstance();
        $stmt = $pdo->query(
            'SELECT l.*, c.name AS category_name, c.sort_order AS category_sort_order
             FROM links l
             LEFT JOIN link_categories c ON c.id = l.category_id
             ORDER BY COALESCE(c.sort_order, 2147483647) ASC, c.name ASC, l.sort_order ASC, l.id DESC'
        );
        $links = $stmt->fetchAll();

        // Populate preview images for links not yet tried (preview_image_url IS NULL).
        $update = $pdo->prepare('UPDATE links SET preview_image_url = ? WHERE id = ?');
        foreach ($links as &$link) {
            if ($link['preview_image_url'] === null) {
                $imageUrl = self::fetchOgImage((string) $link['url']);
                $update->execute([$imageUrl, $link['id']]);
                $link['preview_image_url'] = $imageUrl;
            }
        }
        unset($link);

        $linksByCategory = [];
        foreach ($links as $link) {
            $categoryName = trim((string) ($link['category_name'] ?? ''));
            if ($categoryName === '') {
                $categoryName = 'Uncategorized';
            }

            if (!isset($linksByCategory[$categoryName])) {
                $linksByCategory[$categoryName] = [];
            }
            $linksByCategory[$categoryName][] = $link;
        }

        require __DIR__ . '/../../templates/info/links.php';
    }

    /**
     * Fetch the og:image (or twitter:image) meta tag from a URL.
     * Returns the image URL on success, or an empty string if none found.
     */
    public static function fetchOgImage(string $url): string
    {
        // Only allow http/https URLs
        if (!preg_match('/^https?:\/\//i', $url)) {
            return '';
        }

        $context = stream_context_create([
            'http' => [
                'timeout'         => 5,
                'follow_location' => 1,
                'user_agent'      => 'Mozilla/5.0 (compatible; HutBot/1.0; +https://github.com)',
                'method'          => 'GET',
            ],
            'ssl' => [
                'verify_peer'      => true,
                'verify_peer_name' => true,
            ],
        ]);

        $handle = @fopen($url, 'r', false, $context);
        if ($handle === false) {
            return '';
        }

        // Read up to 512 KB in chunks. A single fread() can return only a partial HTTP stream.
        $maxBytes = 524288;
        $html = '';
        while (!feof($handle) && strlen($html) < $maxBytes) {
            $chunk = @fread($handle, 8192);
            if ($chunk === false || $chunk === '') {
                break;
            }
            $html .= $chunk;
        }
        fclose($handle);

        if ($html === false || $html === '') {
            return '';
        }

        // Match both attribute orderings: property/name before content, and content before property/name.
        $patterns = [
            '/<meta[^>]+property=["\']og:image["\'][^>]+content=["\']([^"\']+)["\'][^>]*>/i',
            '/<meta[^>]+content=["\']([^"\']+)["\'][^>]+property=["\']og:image["\'][^>]*>/i',
            '/<meta[^>]+name=["\']twitter:image["\'][^>]+content=["\']([^"\']+)["\'][^>]*>/i',
            '/<meta[^>]+content=["\']([^"\']+)["\'][^>]+name=["\']twitter:image["\'][^>]*>/i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $html, $m)) {
                $imageUrl = html_entity_decode(trim($m[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8');
                // Only accept http/https image URLs
                if (preg_match('/^https?:\/\//i', $imageUrl)) {
                    return $imageUrl;
                }
            }
        }

        return '';
    }
}
