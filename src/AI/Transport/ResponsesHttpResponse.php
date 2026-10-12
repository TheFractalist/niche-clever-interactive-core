<?php

declare(strict_types=1);

namespace NicheCleverInteractive\Core\AI\Transport;

final class ResponsesHttpResponse
{
    private int $statusCode;
    private string $body;

    public function __construct(int $statusCode, string $body)
    {
        $this->statusCode = $statusCode;
        $this->body = $body;
    }

    public function statusCode(): int
    {
        return $this->statusCode;
    }

    public function body(): string
    {
        return $this->body;
    }
}
