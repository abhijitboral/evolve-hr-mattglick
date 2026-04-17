<?php

declare(strict_types=1);

class AuthMiddleware
{
    public static function verifyAuth(Request $req, Response $res, callable $next): void
    {
        $authHeader = $req->header('Authorization');
        $token      = AuthService::extractToken($authHeader);

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
