<?php

declare(strict_types=1);

class Config
{
    private static array $vars  = [];
    private static bool  $loaded = false;

    public static function load(string $envFile): void
    {
        if (self::$loaded) {
            return;
        }

        if (file_exists($envFile)) {
            $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                $line = trim($line);
                if ($line === '' || $line[0] === '#') {
                    continue;
                }
                if (strpos($line, '=') !== false) {
                    [$key, $value] = explode('=', $line, 2);
                    $key   = trim($key);
                    $value = trim($value, " \t\n\r\0\x0B\"'");
                    self::$vars[$key] = $value;
                    if (!isset($_ENV[$key])) {
                        $_ENV[$key] = $value;
                        putenv("$key=$value");
                    }
                }
            }
        }

        self::$loaded = true;
    }

    public static function get(string $key, $default = null)
    {
        return self::$vars[$key] ?? $_ENV[$key] ?? getenv($key) ?: $default;
    }

    public static function isProduction(): bool
    {
        return self::get('NODE_ENV', 'development') === 'production';
    }
}
