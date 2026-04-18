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

// Run migrations on first boot / after deploy
Database::migrate(__DIR__ . '/../migrations');

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
$router->get('/collection',         [GameController::class, 'collection']);
$router->get('/rankings',           [VoteController::class, 'rankings']);
$router->post('/games/{id}/heart',  [VoteController::class, 'heart']);

// ─── Admin routes ────────────────────────────────────────────────────────────
$router->get('/admin',              [AdminController::class, 'showAdmin']);
$router->get('/admin/import',       [AdminController::class, 'showImport']);
$router->post('/admin/import',      [AdminController::class, 'doImport']);
$router->post('/admin/import/start', [AdminController::class, 'startImport']);
$router->post('/admin/import/process', [AdminController::class, 'processImport']);
$router->get('/admin/import/status', [AdminController::class, 'importStatus']);
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
