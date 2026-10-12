<?php

declare(strict_types=1);

namespace NicheCleverInteractive\Core\Configuration;

final class SecretResolver
{
    private string $constantName;

    public function __construct(string $constantName = 'NCLI_OPENAI_API_KEY_DEFAULT')
    {
        $this->constantName = $constantName;
    }

    public function isAvailable(): bool
    {
        return defined($this->constantName) && is_string(constant($this->constantName)) && trim((string) constant($this->constantName)) !== '';
    }

    public function forProvider(): ?string
    {
        if (!$this->isAvailable()) {
            return null;
        }

        return trim((string) constant($this->constantName));
    }
}
