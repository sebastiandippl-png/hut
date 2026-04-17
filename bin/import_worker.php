#!/usr/bin/env php
<?php

declare(strict_types=1);

use Dotenv\Dotenv;
use Hut\Database;
use Hut\controllers\AdminController;

require_once __DIR__ . '/../vendor/autoload.php';

$jobId = $argv[1] ?? '';
if (!is_string($jobId) || !preg_match('/^[a-f0-9]{32}$/', $jobId)) {
    fwrite(STDERR, "Invalid or missing import job id.\n");
    exit(1);
}

$dotenv = Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

// Ensure database and migrations are ready for importer usage in detached mode.
Database::migrate(__DIR__ . '/../migrations');

AdminController::runImportJobById($jobId);
