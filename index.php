<?php

declare(strict_types=1);

// Prevent PHP warnings/notices from corrupting JSON responses
ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
error_reporting(E_ALL);
ini_set('log_errors', '1');

// Buffer all output so any accidental output doesn't break JSON headers
ob_start();

// Global error/exception handler — always return JSON, never HTML
set_exception_handler(function (Throwable $e): void {
    ob_clean();
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Internal server error', 'detail' => $e->getMessage()]);
    exit;
});

set_error_handler(function (int $severity, string $message, string $file, int $line): bool {
    if (!(error_reporting() & $severity)) {
        return false;
    }
    // Log deprecations/notices but don't crash — only throw on real errors
    if (in_array($severity, [E_DEPRECATED, E_USER_DEPRECATED, E_NOTICE, E_USER_NOTICE, E_STRICT], true)) {
        error_log("[$file:$line] $message");
        return true;
    }
    throw new ErrorException($message, 0, $severity, $file, $line);
});

// ── Bootstrap ────────────────────────────────────────────────────────────────

define('BASE_PATH', __DIR__);

require BASE_PATH . '/config/Config.php';
Config::load(BASE_PATH . '/.env');

// Session
session_set_cookie_params([
    'lifetime' => 0,
    'httponly' => true,
    'samesite' => 'Strict',
    'secure'   => Config::isProduction(),
]);
session_start();

// Autoload classes
spl_autoload_register(function (string $class): void {
    $dirs = [
        BASE_PATH . '/core/',
        BASE_PATH . '/config/',
        BASE_PATH . '/services/',
        BASE_PATH . '/middleware/',
        BASE_PATH . '/controllers/',
    ];
    foreach ($dirs as $dir) {
        $file = $dir . $class . '.php';
        if (file_exists($file)) {
            require_once $file;
            return;
        }
    }
});

// Initialise services
AuthService::init(Config::get('JWT_SECRET', 'dev-secret'));
HubSpotService::init(Config::get('HUBSPOT_PRIVATE_APP_TOKEN', ''));

// CORS — allow same origin (browser requests from the same domain)
$requestOrigin = $_SERVER['HTTP_ORIGIN'] ?? '';
$allowedOrigin = Config::get('FRONTEND_URL', '');
if ($allowedOrigin === '' && $requestOrigin !== '') {
    $allowedOrigin = $requestOrigin;
} elseif ($allowedOrigin === '') {
    $allowedOrigin = (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
}
header("Access-Control-Allow-Origin: $allowedOrigin");
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Methods: GET, POST, PATCH, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// ── Routing ──────────────────────────────────────────────────────────────────

$req    = new Request();
$res    = new Response();
$router = new Router();
$auth   = [AuthMiddleware::class, 'verifyAuth'];

// Health
$router->get('/api/health', function ($req, $res) {
    $res->json(['status' => 'OK', 'timestamp' => date(DATE_ATOM)]);
});

// Auth routes
$router->post('/api/auth/login',    [AuthController::class, 'login']);
$router->post('/api/auth/register', [AuthController::class, 'register']);
$router->get( '/api/auth/profile',  [AuthController::class, 'profile'],  [$auth]);
$router->post('/api/auth/logout',   [AuthController::class, 'logout']);
$router->post('/api/auth/refresh',  [AuthController::class, 'refresh'],  [$auth]);

// Ticket routes
$router->get(  '/api/tickets/debug/all',  [TicketController::class, 'debugAll'],     [$auth]);
$router->get(  '/api/tickets',            [TicketController::class, 'index'],        [$auth]);
$router->post( '/api/tickets',            [TicketController::class, 'store'],        [$auth]);
$router->get(  '/api/tickets/:id',        [TicketController::class, 'show'],         [$auth]);
$router->patch('/api/tickets/:id/status', [TicketController::class, 'updateStatus'], [$auth]);
$router->post( '/api/tickets/:id/notes',  [TicketController::class, 'addNote'],      [$auth]);

// Contact route
$router->post('/api/contact', [ContactController::class, 'submit']);

// HubSpot routes
$router->get(   '/api/hubspot/health',       [HubSpotController::class, 'health']);
$router->post(  '/api/hubspot/test-contact', [HubSpotController::class, 'testContact']);
$router->get(   '/api/hubspot/contacts',     [HubSpotController::class, 'listContacts']);
$router->delete('/api/hubspot/contacts/:id', [HubSpotController::class, 'deleteContact']);

// ── Static file / SPA serving ─────────────────────────────────────────────

$uri = $_SERVER['REQUEST_URI'] ?? '/';
$pos = strpos($uri, '?');
$uri = $pos !== false ? substr($uri, 0, $pos) : $uri;

// Normalize: find /api/ anywhere in the URI so the router always sees /api/...
// This handles any subfolder depth (/proposed/, /proposed/php/, /, etc.)
$apiPos = strpos($uri, '/api/');
if ($apiPos !== false) {
    $uri = substr($uri, $apiPos); // e.g. /proposed/api/auth/login → /api/auth/login
} else {
    // Not an API call — strip known subfolder prefix for static/SPA serving
    $scriptDir = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
    if ($scriptDir !== '' && $scriptDir !== '/' && strpos($uri, $scriptDir) === 0) {
        $uri = substr($uri, strlen($scriptDir));
    }
    $uri = $uri ?: '/';
}

if (strpos($uri, '/api/') !== 0) {
    $publicDir = BASE_PATH . '/public';

    // Prevent path traversal — strip leading slash and resolve safely
    $relativePath = ltrim($uri, '/');
    $filePath     = $publicDir . '/' . $relativePath;

    // Normalise without realpath so spaces in paths work on all systems
    $filePath = str_replace(['/./', '/../', '//'], '/', $filePath);

    if ($relativePath !== '' && is_file($filePath)) {
        $ext     = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        $mimeMap = [
            'html' => 'text/html',
            'css'  => 'text/css',
            'js'   => 'application/javascript',
            'json' => 'application/json',
            'png'  => 'image/png',
            'jpg'  => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'svg'  => 'image/svg+xml',
            'ico'  => 'image/x-icon',
            'woff' => 'font/woff',
            'woff2'=> 'font/woff2',
            'ttf'  => 'font/ttf',
        ];
        $mime = isset($mimeMap[$ext]) ? $mimeMap[$ext] : 'application/octet-stream';
        ob_clean();
        header("Content-Type: $mime");
        readfile($filePath);
        exit;
    }

    // SPA fallback — serve index.html
    ob_clean();
    header('Content-Type: text/html');
    readfile($publicDir . '/index.html');
    exit;
}

$router->dispatch($req, $res);
