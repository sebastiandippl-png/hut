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
        $stmt = $pdo->query('SELECT * FROM links ORDER BY sort_order ASC, id DESC');
        $links = $stmt->fetchAll();

        require __DIR__ . '/../../templates/info/links.php';
    }
}
