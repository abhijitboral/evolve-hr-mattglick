<?php

declare(strict_types=1);

class Request
{
    private array $body   = [];
    private array $params = [];

    /** @var mixed */
    public $user = null;

    public function __construct()
    {
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
        if (strpos($contentType, 'application/json') !== false) {
            $raw = file_get_contents('php://input');
            $this->body = json_decode($raw ?: '{}', true) ?? [];
        } else {
            $this->body = array_merge($_POST, $_GET);
        }
    }

    public function getMethod(): string
    {
        return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
    }

    public function getUri(): string
    {
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        $pos = strpos($uri, '?');
        $uri = $pos !== false ? substr($uri, 0, $pos) : $uri;

        // Strip subfolder prefix so router always sees /api/... regardless of install path
        $apiPos = strpos($uri, '/api/');
        if ($apiPos !== false) {
            return substr($uri, $apiPos);
        }

        $scriptDir = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
        if ($scriptDir !== '' && $scriptDir !== '/' && strpos($uri, $scriptDir) === 0) {
            $uri = substr($uri, strlen($scriptDir));
        }
        return $uri ?: '/';
    }

    /** @return mixed */
    public function body(?string $key = null, $default = null)
    {
        if ($key === null) {
            return $this->body;
        }
        return $this->body[$key] ?? $default;
    }

    /** @return mixed */
    public function param(string $key, $default = null)
    {
        return $this->params[$key] ?? $default;
    }

    public function setParams(array $params): void
    {
        $this->params = $params;
    }

    public function header(string $name): ?string
    {
        $key = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
        if (isset($_SERVER[$key])) {
            return $_SERVER[$key];
        }

        if ($name === 'Authorization') {
            // Apache mod_rewrite / CGI fallbacks — header may land under various keys
            foreach ([
                'REDIRECT_HTTP_AUTHORIZATION',
                'HTTP_AUTHORIZATION',
                'REDIRECT_Authorization',
            ] as $k) {
                if (!empty($_SERVER[$k])) {
                    return $_SERVER[$k];
                }
            }

            // getallheaders() works on Apache module and some FastCGI setups
            if (function_exists('getallheaders')) {
                $all = getallheaders();
                if (!empty($all['Authorization'])) {
                    return $all['Authorization'];
                }
            }

            if (function_exists('apache_request_headers')) {
                $all = apache_request_headers();
                if (!empty($all['Authorization'])) {
                    return $all['Authorization'];
                }
            }
        }

        return null;
    }
}
