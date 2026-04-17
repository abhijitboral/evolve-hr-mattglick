<?php

declare(strict_types=1);

class AuthMiddleware
{
    public static function verifyAuth(Request $req, Response $res, callable $next): void
    {
        $token = null;

        // 1. Standard Authorization header (all known Apache/FastCGI key variants)
        $authHeader = $req->header('Authorization');
        $token      = AuthService::extractToken($authHeader);

        // 2. Fallback: raw $_SERVER scan for any key ending in HTTP_AUTHORIZATION
        if (!$token) {
            foreach ($_SERVER as $k => $v) {
                if (str_ends_with($k, 'HTTP_AUTHORIZATION') && !empty($v)) {
                    $token = AuthService::extractToken($v);
                    break;
                }
            }
        }

        if ($token) {
            $decoded = AuthService::verifyToken($token);
            if ($decoded) {
                $req->user = $decoded;
                call_user_func($next);
                return;
            }
        }

        if (isset($_SESSION['user'])) {
            $req->user = $_SESSION['user'];
            call_user_func($next);
            return;
        }

        $res->status(401)->json(['error' => 'Authentication required']);
    }
}
