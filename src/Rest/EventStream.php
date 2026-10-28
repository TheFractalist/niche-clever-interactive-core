<?php

declare(strict_types=1);

namespace NicheCleverInteractive\Core\Rest;

/**
 * Writes the chat route's server-sent events. It begins only when there is something to send, so a
 * request that fails or is answered from the cache before any text arrives is still an ordinary
 * JSON response with its usual status code.
 */
final class EventStream
{
    /** @var (callable(string): void)|null */
    private $write;
    private bool $began = false;

    /** @param (callable(string): void)|null $write Replaces the real output; used by tests. */
    public function __construct(?callable $write = null)
    {
        $this->write = $write;
    }

    public function hasBegun(): bool
    {
        return $this->began;
    }

    public function begin(): void
    {
        if ($this->began) {
            return;
        }
        $this->began = true;
        if ($this->write !== null) {
            return;
        }

        if (function_exists('ignore_user_abort')) {
            // The answer is still validated, recorded and counted if the visitor closes the page.
            ignore_user_abort(true);
        }
        while (function_exists('ob_get_level') && ob_get_level() > 0) {
            @ob_end_clean(); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Nothing has been sent yet.
        }
        if (!headers_sent()) {
            header('Content-Type: text/event-stream; charset=utf-8');
            header('Cache-Control: no-cache, no-transform');
            header('X-Accel-Buffering: no');
            header('X-LiteSpeed-Buffering: no');
        }
    }

    /** @param array<string, mixed> $event */
    public function send(array $event): void
    {
        $this->begin();
        $line = 'data: ' . json_encode($event, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE) . "\n\n";
        if ($this->write !== null) {
            ($this->write)($line);
            return;
        }
        echo $line; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- A JSON-encoded event, not HTML.
        if (function_exists('flush')) {
            flush();
        }
    }

    /** Ends the request: the stream is the whole response, so WordPress must not send anything after it. */
    public function end(): void
    {
        if ($this->write === null) {
            exit; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Nothing is printed; the response was already sent as events.
        }
    }
}
