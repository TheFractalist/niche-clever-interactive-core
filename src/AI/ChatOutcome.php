<?php

declare(strict_types=1);

namespace NicheCleverInteractive\Core\AI;

use NicheCleverInteractive\Core\Api\V1\Value\ApprovedEvidenceV1;
use NicheCleverInteractive\Core\Security\PublicError;

final class ChatOutcome
{
    /** @var list<ApprovedEvidenceV1> */
    private array $evidence;
    private ?string $answer;
    private ?PublicError $error;

    /**
     * @param list<ApprovedEvidenceV1> $evidence
     */
    private function __construct(?string $answer, array $evidence, ?PublicError $error)
    {
        $this->answer = $answer;
        $this->evidence = $evidence;
        $this->error = $error;
    }

    /** @param list<ApprovedEvidenceV1> $evidence */
    public static function success(string $answer, array $evidence): self
    {
        return new self($answer, $evidence, null);
    }

    public static function failure(string $code): self
    {
        return new self(null, [], new PublicError($code));
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
            'evidence' => array_map(static fn (ApprovedEvidenceV1 $item): array => $item->publicPayload(), $this->evidence),
        ];
    }

    public function errorCode(): ?string
    {
        return $this->error === null ? null : $this->error->payload()['code'];
    }
}
