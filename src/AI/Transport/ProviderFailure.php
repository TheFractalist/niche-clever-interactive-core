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

    /** @var array<string, array{0: string, 1: string}> reason => [label, what to try] */
    private const DESCRIPTIONS = [
        self::TIMEOUT => ['The provider did not reply in time', 'Lower the reasoning effort or choose a faster model in AI & Corpus, or raise the request timeout, then run the connection test again.'],
        self::CONNECTION_ERROR => ['The provider could not be reached', "Check this server's internet access, DNS and firewall, then try again."],
        self::HTTP_429 => ['The provider refused the request as too frequent or over quota', 'Check your OpenAI usage limits and billing, or wait and try again.'],
        self::HTTP_4XX => ['The provider rejected the request (client error)', "Check the API key, the model name, the vector store reference and the account's access to the chosen model."],
        self::HTTP_5XX => ['The provider reported a server error', "This is usually temporary on the provider's side; try again shortly."],
        self::HTTP_OTHER => ['The provider returned an unexpected status', "Try again; if it persists, check the provider's status page."],
        self::MALFORMED_RESPONSE => ['The provider reply could not be read', 'Try again; if it persists, the provider may have changed its response format.'],
        self::INCOMPLETE_RESPONSE => ['The provider reply was missing expected parts', 'Try again; if it persists, check the model and retrieval settings.'],
        self::NOT_CONFIGURED => ['The live provider configuration is incomplete', 'Check the server-side key, the transport setting and the vector store reference.'],
        self::EMPTY_ANSWER => ['The provider returned no answer text', 'Raise the maximum answer length or lower the reasoning effort, so reasoning does not use the whole allowance.'],
        self::ANSWER_TOO_LONG => ['The answer was longer than Core will show', 'Lower the maximum answer length or ask for a more concise response style.'],
        self::EVIDENCE_MISMATCH => ['The answer did not come from a completed search of your corpus', 'Check the vector store reference and corpus version, then run the connection test again.'],
        self::UNEXPECTED_ERROR => ['An unexpected error occurred', 'Try again; if it persists, enable WordPress debug logging and check the server error log.'],
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
        return isset(self::DESCRIPTIONS[$reason]);
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

    public static function label(string $reason): string
    {
        return (self::DESCRIPTIONS[$reason] ?? self::DESCRIPTIONS[self::UNEXPECTED_ERROR])[0];
    }

    public static function advice(string $reason): string
    {
        return (self::DESCRIPTIONS[$reason] ?? self::DESCRIPTIONS[self::UNEXPECTED_ERROR])[1];
    }

    public static function reasonFor(\Throwable $exception): string
    {
        return $exception instanceof self ? $exception->reason() : self::UNEXPECTED_ERROR;
    }
}
