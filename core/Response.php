<?php

declare(strict_types=1);

class Response
{
    private int $statusCode = 200;
    private array $headers  = ['Content-Type' => 'application/json'];

    public function status(int $code): self
    {
        $this->statusCode = $code;
        return $this;
    }

    public function header(string $name, string $value): self
    {
        $this->headers[$name] = $value;
        return $this;
    }

    /** @param mixed $data */
    public function json($data): void
    {
        ob_clean();
        http_response_code($this->statusCode);
        foreach ($this->headers as $name => $value) {
            header("$name: $value");
        }
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}
