<?php

declare(strict_types=1);

namespace Hut;

class Url
{
    public static function basePath(): string
    {
        $configured = trim((string) ($_ENV['APP_BASE_PATH'] ?? ''));
        if ($configured !== '') {
            return self::normalizeBasePath($configured);
        }

        $scriptName = (string) ($_SERVER['SCRIPT_NAME'] ?? '');
        $dir = str_replace('\\', '/', dirname($scriptName));
        if ($dir === '/' || $dir === '.' || $dir === '') {
            return '';
        }

        $dir = rtrim($dir, '/');
        if (str_ends_with($dir, '/public')) {
            $dir = substr($dir, 0, -7) ?: '';
        }

        return self::normalizeBasePath($dir);
    }

    public static function to(string $path): string
    {
        $normalizedPath = '/' . ltrim($path, '/');
        $base = self::basePath();
        return $base . $normalizedPath;
    }

    public static function asset(string $path): string
    {
        $normalizedPath = '/' . ltrim($path, '/');
        $absolutePath = dirname(__DIR__) . '/public' . $normalizedPath;
        $assetUrl = self::to($normalizedPath);

        if (!is_file($absolutePath)) {
            return $assetUrl;
        }

        return $assetUrl . '?v=' . filemtime($absolutePath);
    }

    public static function stripBasePathFromUri(string $uri): string
    {
        $path = parse_url($uri, PHP_URL_PATH);
        $path = is_string($path) && $path !== '' ? $path : '/';

        if ($path === '/index.php') {
            return '/';
        }
        if (str_starts_with($path, '/index.php/')) {
            $stripped = substr($path, strlen('/index.php'));
            return $stripped !== '' ? $stripped : '/';
        }

        foreach (self::prefixesToStrip() as $prefix) {
            if ($prefix === '') {
                continue;
            }
            if ($path === $prefix) {
                return '/';
            }
            if (str_starts_with($path, $prefix . '/')) {
                $stripped = substr($path, strlen($prefix));
                return $stripped !== '' ? $stripped : '/';
            }
        }

        return $path;
    }

    /**
     * @return array<int, string>
     */
    private static function prefixesToStrip(): array
    {
        $base = self::basePath();
        if ($base === '') {
            return [];
        }

        $prefixes = [];
        if ($base . '/public' !== $base) {
            $prefixes[] = $base . '/public';
        }
        $prefixes[] = $base;

        return $prefixes;
    }

    private static function normalizeBasePath(string $basePath): string
    {
        $normalized = trim(str_replace('\\', '/', $basePath));
        if ($normalized === '' || $normalized === '/') {
            return '';
        }

        $normalized = '/' . trim($normalized, '/');
        return rtrim($normalized, '/');
    }
}
