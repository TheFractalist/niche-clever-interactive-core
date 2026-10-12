<?php

declare(strict_types=1);

namespace NicheCleverInteractive\Core\Security;

use InvalidArgumentException;

final class PublicError
{
    private const MESSAGES = [
        'profile_unavailable' => 'This interactive service is unavailable.',
        'invalid_request' => 'Please enter a valid question.',
        'policy_refusal' => 'I cannot help with that request.',
        'allowance_exceeded' => 'The question allowance has been reached. Please try again later.',
        'provider_failure' => 'The interactive service is temporarily unavailable.',
        'evidence_failure' => 'A verified answer is not available for that question.',
    ];

    private string $code;

    public function __construct(string $code)
    {
        if (!isset(self::MESSAGES[$code])) {
            throw new InvalidArgumentException('Unknown public error code.');
        }

        $this->code = $code;
    }

    /**
     * @return array{code: string, message: string}
     */
    public function payload(): array
    {
        return ['code' => $this->code, 'message' => self::MESSAGES[$this->code]];
    }
}
