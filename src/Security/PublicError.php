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
        'access_required' => 'Access to this service has not been granted.',
    ];

    public const MAX_ACCESS_MESSAGE = 300;

    private string $code;
    private ?string $message;

    /**
     * @param string|null $message Only `access_required` may carry an owner-supplied message; Core
     *                             reduces it to bounded plain text and ignores it for every other code.
     */
    public function __construct(string $code, ?string $message = null)
    {
        if (!isset(self::MESSAGES[$code])) {
            throw new InvalidArgumentException('Unknown public error code.');
        }

        $this->code = $code;
        $this->message = $code === 'access_required' ? self::plainText($message) : null;
    }

    public static function plainText(?string $message): ?string
    {
        if ($message === null) {
            return null;
        }

        $text = function_exists('wp_strip_all_tags') ? (string) wp_strip_all_tags($message) : strip_tags($message); // phpcs:ignore WordPress.WP.AlternativeFunctions.strip_tags_strip_tags -- Fallback when WordPress is not loaded (pure tests).
        $text = trim(preg_replace('/[\x00-\x1F\x7F]+/', ' ', $text) ?? '');
        if ($text === '' || strlen($text) > self::MAX_ACCESS_MESSAGE) {
            return null;
        }

        return $text;
    }

    /**
     * @return array{code: string, message: string}
     */
    public function payload(): array
    {
        $message = match ($this->code) {
            'profile_unavailable' => __('This interactive service is unavailable.', 'niche-clever-interactive'),
            'invalid_request' => __('Please enter a valid question.', 'niche-clever-interactive'),
            'policy_refusal' => __('I cannot help with that request.', 'niche-clever-interactive'),
            'allowance_exceeded' => __('The question allowance has been reached. Please try again later.', 'niche-clever-interactive'),
            'provider_failure' => __('The interactive service is temporarily unavailable.', 'niche-clever-interactive'),
            'evidence_failure' => __('A verified answer is not available for that question.', 'niche-clever-interactive'),
            'access_required' => __('Access to this service has not been granted.', 'niche-clever-interactive'),
        };

        return ['code' => $this->code, 'message' => $this->message ?? $message];
    }
}
