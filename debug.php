<?php
session_start();

header('Content-Type: application/json');

// Collect all Authorization-related keys from $_SERVER
$authKeys = [];
foreach ($_SERVER as $k => $v) {
    if (stripos($k, 'auth') !== false || stripos($k, 'authorization') !== false) {
        $authKeys[$k] = $v;
    }
}

echo json_encode([
    'session_id'      => session_id(),
    'session_user'    => $_SESSION['user'] ?? null,
    'auth_server_keys'=> $authKeys,
    'HTTP_AUTHORIZATION'         => $_SERVER['HTTP_AUTHORIZATION'] ?? 'NOT SET',
    'REDIRECT_HTTP_AUTHORIZATION'=> $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? 'NOT SET',
    'getallheaders'   => function_exists('getallheaders') ? (getallheaders() ?: 'empty') : 'function missing',
    'cookies'         => $_COOKIE,
    'request_method'  => $_SERVER['REQUEST_METHOD'],
    'request_uri'     => $_SERVER['REQUEST_URI'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
