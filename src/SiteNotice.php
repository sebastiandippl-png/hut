<?php

declare(strict_types=1);

namespace Hut;

use RuntimeException;

class SiteNotice
{
    private const STORAGE_PATH = '/storage/site_notice.json';
    private const DEFAULT_MESSAGE = 'Ich musste die Datenbank ersetzen, komplett gecrasht ;-), Ich habe ein backup von 19.04 eingespielt. Alles vom 20.04 ist leider verloren...';

    /**
     * @return array{enabled: bool, message: string}
     */
    public static function current(): array
    {
        $path = self::storagePath();
        if (!is_file($path)) {
            return [
                'enabled' => true,
                'message' => self::DEFAULT_MESSAGE,
            ];
        }

        $json = file_get_contents($path);
        if (!is_string($json) || $json === '') {
            return [
                'enabled' => true,
                'message' => self::DEFAULT_MESSAGE,
            ];
        }

        $decoded = json_decode($json, true);
        if (!is_array($decoded)) {
            return [
                'enabled' => true,
                'message' => self::DEFAULT_MESSAGE,
            ];
        }

        $enabled = isset($decoded['enabled']) ? (bool) $decoded['enabled'] : true;
        $message = isset($decoded['message']) ? trim((string) $decoded['message']) : self::DEFAULT_MESSAGE;
        if ($message === '') {
            $message = self::DEFAULT_MESSAGE;
        }

        return [
            'enabled' => $enabled,
            'message' => $message,
        ];
    }

    public static function update(bool $enabled, string $message): void
    {
        $message = trim($message);
        if ($message === '') {
            $message = self::DEFAULT_MESSAGE;
        }
        if (mb_strlen($message) > 2000) {
            throw new RuntimeException('Notice message is too long (max 2000 characters).');
        }

        $payload = [
            'enabled' => $enabled,
            'message' => $message,
            'updated_at' => date('c'),
        ];

        $encoded = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        if (!is_string($encoded)) {
            throw new RuntimeException('Failed to encode notice settings.');
        }

        if (file_put_contents(self::storagePath(), $encoded, LOCK_EX) === false) {
            throw new RuntimeException('Failed to write notice settings file.');
        }
    }

    private static function storagePath(): string
    {
        $path = dirname(__DIR__) . self::STORAGE_PATH;
        $dir = dirname($path);
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new RuntimeException('Failed to create site notice storage directory.');
        }

        return $path;
    }
}