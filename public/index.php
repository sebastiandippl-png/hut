<?php

declare(strict_types=1);

// Bootstrap
require_once __DIR__ . '/../vendor/autoload.php';

use Dotenv\Dotenv;
use Hut\Database;
use Hut\Router;
use Hut\Auth;
use Hut\Url;
use Hut\controllers\AuthController;
use Hut\controllers\GameController;
use Hut\controllers\AdminController;
use Hut\controllers\VoteController;

$dotenv = Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

// Global exception handler: log full details, return a safe 500 page.
set_exception_handler(static function (\Throwable $e): void {
    $debug   = ($_ENV['APP_DEBUG'] ?? 'false') === 'true';
    $refId   = bin2hex(random_bytes(6));

    $logEntry = sprintf(
        '[%s] [500] ref=%s %s: %s in %s:%d' . PHP_EOL . '%s' . PHP_EOL,
        date('Y-m-d H:i:s'),
        $refId,
        get_class($e),
        $e->getMessage(),
        $e->getFile(),
        $e->getLine(),
        $e->getTraceAsString()
    );

    // Write to a project-local log file so it's accessible on shared hosting
    // where PHP's error_log destination may be inaccessible.
    $logFile = dirname(__DIR__) . '/storage/error.log';
    @file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
    // Also attempt the system error log as a fallback.
    error_log(rtrim($logEntry));

    // Discard any partial output already buffered.
    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: text/html; charset=utf-8');
    }

    $errorRefId = $refId;
    $debugInfo  = $debug
        ? get_class($e) . ': ' . $e->getMessage() . "\n" . $e->getTraceAsString()
        : null;

    require __DIR__ . '/../templates/500.php';
});

// Run migrations on first boot / after deploy.
// Set DB_AUTO_MIGRATE=false in production once the initial deploy is done;
// re-enable only when rolling out schema changes.
if (($_ENV['DB_AUTO_MIGRATE'] ?? 'true') === 'true') {
    Database::migrate(__DIR__ . '/../migrations');
}

// Session
$sessionName = $_ENV['SESSION_NAME'] ?? 'hut_session';
$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || (($_SERVER['SERVER_PORT'] ?? null) === '443');

ini_set('session.use_only_cookies', '1');
ini_set('session.use_strict_mode', '1');
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'secure' => $isHttps,
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_name($sessionName);
session_start();

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Permissions-Policy: geolocation=(), microphone=(), camera=()');

// Rewrite absolute app-local URLs in rendered HTML to include the deployment base path.
ob_start(static function (string $buffer): string {
    $basePath = Url::basePath();
    if ($basePath === '') {
        return $buffer;
    }

    $rewritten = preg_replace(
        '/\b(href|src|action)=(["\'])\/(?!\/)/',
        '$1=$2' . $basePath . '/',
        $buffer
    );

    return is_string($rewritten) ? $rewritten : $buffer;
});

if (!function_exists('app_url')) {
    function app_url(string $path): string
    {
        return Url::to($path);
    }
}

$router = new Router();

// ─── Auth routes ────────────────────────────────────────────────────────────
$router->get('/login',              [AuthController::class, 'showLogin']);
$router->post('/login',             [AuthController::class, 'login']);
$router->get('/register',           [AuthController::class, 'showRegister']);
$router->post('/register',          [AuthController::class, 'register']);
$router->post('/logout',            [AuthController::class, 'logout']);
$router->get('/auth/google',        [AuthController::class, 'googleRedirect']);
$router->get('/auth/google/callback', [AuthController::class, 'googleCallback']);

// ─── Game routes (require login) ────────────────────────────────────────────
$router->get('/',                   [GameController::class, 'browse']);
$router->get('/games',              [GameController::class, 'browse']);
$router->get('/games/suggestions',  [GameController::class, 'suggestions']);
$router->get('/games/{id}',         [GameController::class, 'detail']);
$router->post('/games/{id}/select', [GameController::class, 'toggleSelect']);
$router->post('/games/{id}/add-to-collection', [GameController::class, 'addToMyCollection']);
$router->post('/games/{id}/remove-from-collection', [GameController::class, 'removeFromMyCollection']);
$router->get('/collection',         [GameController::class, 'collection']);
$router->get('/rankings',           [VoteController::class, 'rankings']);
$router->post('/games/{id}/heart',  [VoteController::class, 'heart']);

// ─── Admin routes ────────────────────────────────────────────────────────────
$router->get('/admin',              [AdminController::class, 'showAdmin']);
$router->get('/admin/users',        [AdminController::class, 'showUsers']);
$router->get('/admin/import',       [AdminController::class, 'showImport']);
$router->get('/admin/notice',       [AdminController::class, 'showNotice']);
$router->post('/admin/import',      [AdminController::class, 'doImport']);
$router->post('/admin/import/start', [AdminController::class, 'startImport']);
$router->post('/admin/import/process', [AdminController::class, 'processImport']);
$router->get('/admin/import/status', [AdminController::class, 'importStatus']);
$router->post('/admin/site-notice', [AdminController::class, 'updateSiteNotice']);
$router->post('/admin/collections/fetch', [AdminController::class, 'fetchConfiguredCollections']);
$router->post('/admin/users/{id}/delete', [AdminController::class, 'deleteUser']);
$router->post('/admin/users/{id}/approve', [AdminController::class, 'approveUser']);
$router->post('/admin/users/{id}/disapprove', [AdminController::class, 'disapproveUser']);
$router->post('/admin/games/{id}/remove-from-hut', [GameController::class, 'adminRemoveFromHut']);

// ─── Dispatch ────────────────────────────────────────────────────────────────
$method = $_SERVER['REQUEST_METHOD'];
$uri    = Url::stripBasePathFromUri((string) ($_SERVER['REQUEST_URI'] ?? '/'));

if (!$router->dispatch($method, $uri)) {
    http_response_code(404);
    require __DIR__ . '/../templates/404.php';
}
