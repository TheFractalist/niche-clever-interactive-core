<?php

declare(strict_types=1);

namespace NicheCleverInteractive\Core\AI;

use NicheCleverInteractive\Core\Security\PublicError;

final class ChatOutcome
{
    private ?string $answer;
    private ?PublicError $error;

    private function __construct(?string $answer, ?PublicError $error)
    {
        $this->answer = $answer;
        $this->error = $error;
    }

    public static function success(string $answer): self
    {
        return new self($answer, null);
    }

    public static function failure(string $code): self
    {
        return new self(null, new PublicError($code));
    }

    public function isSuccess(): bool
    {
        return $this->error === null;
    }

    /** @return array<string, mixed> */
    public function payload(): array
    {
        if ($this->error !== null) {
            return ['ok' => false, 'error' => $this->error->payload()];
        }

        return [
            'ok' => true,
            'answer' => $this->answer,
        ];
    }

    public function errorCode(): ?string
    {
        return $this->error === null ? null : $this->error->payload()['code'];
    }
}
