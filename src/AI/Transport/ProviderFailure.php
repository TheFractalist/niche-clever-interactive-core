<?php

declare(strict_types=1);

namespace NicheCleverInteractive\Core\AI\Transport;

use RuntimeException;

/**
 * A provider-side failure with a closed, non-sensitive reason code. The code is
 * for the administrator's diagnostics only; visitors still see one generic message.
 * It never carries a provider payload, header, credential or identifier.
 */
final class ProviderFailure extends RuntimeException
{
    public const TIMEOUT = 'timeout';
    public const CONNECTION_ERROR = 'connection_error';
    public const HTTP_429 = 'http_429';
    public const HTTP_4XX = 'http_4xx';
    public const HTTP_5XX = 'http_5xx';
    public const HTTP_OTHER = 'http_other';
    public const MALFORMED_RESPONSE = 'malformed_response';
    public const INCOMPLETE_RESPONSE = 'incomplete_response';
    public const NOT_CONFIGURED = 'not_configured';
    public const EMPTY_ANSWER = 'empty_answer';
    public const ANSWER_TOO_LONG = 'answer_too_long';
    public const EVIDENCE_MISMATCH = 'evidence_mismatch';
    public const UNEXPECTED_ERROR = 'unexpected_error';

    /** @var list<string> */
    private const REASONS = [
        self::TIMEOUT, self::CONNECTION_ERROR, self::HTTP_429, self::HTTP_4XX, self::HTTP_5XX, self::HTTP_OTHER,
        self::MALFORMED_RESPONSE, self::INCOMPLETE_RESPONSE, self::NOT_CONFIGURED, self::EMPTY_ANSWER,
        self::ANSWER_TOO_LONG, self::EVIDENCE_MISMATCH, self::UNEXPECTED_ERROR,
    ];

    private string $reason;

    public function __construct(string $reason)
    {
        parent::__construct('The provider request was unsuccessful.');
        $this->reason = self::isKnown($reason) ? $reason : self::UNEXPECTED_ERROR;
    }

    public function reason(): string
    {
        return $this->reason;
    }

    public static function isKnown(string $reason): bool
    {
        return in_array($reason, self::REASONS, true);
    }

    public static function fromHttpStatus(int $status): self
    {
        if ($status === 429) {
            return new self(self::HTTP_429);
        }
        if ($status >= 400 && $status < 500) {
            return new self(self::HTTP_4XX);
        }
        if ($status >= 500 && $status < 600) {
            return new self(self::HTTP_5XX);
        }

        return new self(self::HTTP_OTHER);
    }

    /** Classifies a transport-level error message (for example from WP_Error) without keeping it. */
    public static function fromTransportError(string $message): self
    {
        $lower = strtolower($message);
        if (str_contains($lower, 'timed out') || str_contains($lower, 'timeout') || str_contains($lower, 'curl error 28')) {
            return new self(self::TIMEOUT);
        }

        return new self(self::CONNECTION_ERROR);
    }

    /** A translated plain-words reason for Diagnostics. Display-only, so translations load only when shown. */
    public static function label(string $reason): string
    {
        switch ($reason) {
            case self::TIMEOUT:
                return __('The provider did not reply in time', 'niche-clever-interactive');
            case self::CONNECTION_ERROR:
                return __('The provider could not be reached', 'niche-clever-interactive');
            case self::HTTP_429:
                return __('The provider refused the request as too frequent or over quota', 'niche-clever-interactive');
            case self::HTTP_4XX:
                return __('The provider rejected the request (client error)', 'niche-clever-interactive');
            case self::HTTP_5XX:
                return __('The provider reported a server error', 'niche-clever-interactive');
            case self::HTTP_OTHER:
                return __('The provider returned an unexpected status', 'niche-clever-interactive');
            case self::MALFORMED_RESPONSE:
                return __('The provider reply could not be read', 'niche-clever-interactive');
            case self::INCOMPLETE_RESPONSE:
                return __('The provider reply was missing expected parts', 'niche-clever-interactive');
            case self::NOT_CONFIGURED:
                return __('The live provider configuration is incomplete', 'niche-clever-interactive');
            case self::EMPTY_ANSWER:
                return __('The provider returned no answer text', 'niche-clever-interactive');
            case self::ANSWER_TOO_LONG:
                return __('The answer was longer than Core will show', 'niche-clever-interactive');
            case self::EVIDENCE_MISMATCH:
                return __('The answer did not come from a completed search of your corpus', 'niche-clever-interactive');
            default:
                return __('An unexpected error occurred', 'niche-clever-interactive');
        }
    }

    /** What to try next, translated, for Diagnostics. */
    public static function advice(string $reason): string
    {
        switch ($reason) {
            case self::TIMEOUT:
                return __('Lower the reasoning effort or choose a faster model in AI & Corpus, or raise the request timeout, then run the connection test again.', 'niche-clever-interactive');
            case self::CONNECTION_ERROR:
                return __('Check this server\'s internet access, DNS and firewall, then try again.', 'niche-clever-interactive');
            case self::HTTP_429:
                return __('Check your OpenAI usage limits and billing, or wait and try again.', 'niche-clever-interactive');
            case self::HTTP_4XX:
                return __('Check the API key, the model name, the vector store reference and the account\'s access to the chosen model.', 'niche-clever-interactive');
            case self::HTTP_5XX:
                return __('This is usually temporary on the provider\'s side; try again shortly.', 'niche-clever-interactive');
            case self::HTTP_OTHER:
                return __('Try again; if it persists, check the provider\'s status page.', 'niche-clever-interactive');
            case self::MALFORMED_RESPONSE:
                return __('Try again; if it persists, the provider may have changed its response format.', 'niche-clever-interactive');
            case self::INCOMPLETE_RESPONSE:
                return __('Try again; if it persists, check the model and retrieval settings.', 'niche-clever-interactive');
            case self::NOT_CONFIGURED:
                return __('Check the server-side key, the transport setting and the vector store reference.', 'niche-clever-interactive');
            case self::EMPTY_ANSWER:
                return __('Raise the maximum answer length or lower the reasoning effort, so reasoning does not use the whole allowance.', 'niche-clever-interactive');
            case self::ANSWER_TOO_LONG:
                return __('Lower the maximum answer length or ask for a more concise response style.', 'niche-clever-interactive');
            case self::EVIDENCE_MISMATCH:
                return __('Check the vector store reference and corpus version, then run the connection test again.', 'niche-clever-interactive');
            default:
                return __('Try again; if it persists, enable WordPress debug logging and check the server error log.', 'niche-clever-interactive');
        }
    }

    public static function reasonFor(\Throwable $exception): string
    {
        return $exception instanceof self ? $exception->reason() : self::UNEXPECTED_ERROR;
    }
}
