<?php

declare(strict_types=1);

class AuthService
{
    private static string $secret  = '';
    private static int    $expiry  = 604800; // 7 days in seconds

    public static function init(string $secret): void
    {
        self::$secret = $secret;
    }

    // -------------------------------------------------------------------------
    // JWT — pure PHP, no dependencies
    // -------------------------------------------------------------------------

    public static function generateToken(array $payload): string
    {
        $header = self::base64UrlEncode(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
        $payload['iat'] = time();
        $payload['exp'] = time() + self::$expiry;
        $body      = self::base64UrlEncode(json_encode($payload));
        $signature = self::base64UrlEncode(self::sign("$header.$body"));
        return "$header.$body.$signature";
    }

    public static function verifyToken(string $token): ?array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return null;
        }

        [$header, $body, $signature] = $parts;
        $expectedSig = self::base64UrlEncode(self::sign("$header.$body"));

        if (!hash_equals($expectedSig, $signature)) {
            return null;
        }

        $payload = json_decode(self::base64UrlDecode($body), true);
        if (!$payload || (isset($payload['exp']) && $payload['exp'] < time())) {
            return null;
        }

        return $payload;
    }

    public static function extractToken(?string $authHeader): ?string
    {
        if (!$authHeader || strpos($authHeader, 'Bearer ') !== 0) {
            return null;
        }
        return substr($authHeader, 7);
    }

    // -------------------------------------------------------------------------
    // Password hashing (bcrypt via PHP built-ins)
    // -------------------------------------------------------------------------

    public static function hashPassword(string $password): string
    {
        return password_hash($password, PASSWORD_BCRYPT, ['cost' => 10]);
    }

    public static function comparePassword(string $password, string $hash): bool
    {
        return password_verify($password, $hash);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private static function sign(string $data): string
    {
        return hash_hmac('sha256', $data, self::$secret, true);
    }

    private static function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private static function base64UrlDecode(string $data): string
    {
        return base64_decode(strtr($data, '-_', '+/') . str_repeat('=', (4 - strlen($data) % 4) % 4));
    }
}
