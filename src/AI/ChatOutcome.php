<?php

declare(strict_types=1);

namespace NicheCleverInteractive\Core\AI;

use NicheCleverInteractive\Core\Security\PublicError;

final class ChatOutcome
{
    private ?string $answer;
    private ?PublicError $error;
    /** @var list<\NicheCleverInteractive\Core\Api\V1\Value\ApprovedLinkV1> */
    private array $links;

    /** @param list<\NicheCleverInteractive\Core\Api\V1\Value\ApprovedLinkV1> $links */
    private function __construct(?string $answer, ?PublicError $error, array $links = [])
    {
        $this->answer = $answer;
        $this->error = $error;
        $this->links = $links;
    }

    /** @param list<\NicheCleverInteractive\Core\Api\V1\Value\ApprovedLinkV1> $links Owner-approved links, already validated by Core. */
    public static function success(string $answer, array $links = []): self
    {
        return new self($answer, null, $links);
    }

    public static function failure(string $code): self
    {
        return new self(null, new PublicError($code));
    }

    public static function accessRequired(?string $message): self
    {
        return new self(null, new PublicError('access_required', $message));
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

        $payload = [
            'ok' => true,
            'answer' => $this->answer,
        ];
        if ($this->links !== []) {
            $payload['links'] = array_map(static fn ($link): array => ['title' => $link->title(), 'url' => $link->url()], $this->links);
        }

        return $payload;
    }

    public function errorCode(): ?string
    {
        return $this->error === null ? null : $this->error->payload()['code'];
    }
}
