<?php

declare(strict_types=1);

namespace NicheCleverInteractive\Core\AI\Transport;

/**
 * Reads an OpenAI Responses server-sent event stream.
 *
 * It does two separate things. It passes display text to a sink, but only after the provider has
 * reported a completed file search, with provider citation markers removed and the total capped.
 * It also keeps the final `response.completed` payload, which is the only thing Core validates
 * and returns; streamed text is never itself the answer.
 */
final class ResponsesStreamReader
{
    private const MARKER_OPEN = "\u{E200}";
    private const MARKER_CLOSE = "\u{E201}";
    private const MAX_MARKER_BYTES = 400;

    /** @var callable(string): void */
    private $sink;
    private int $maxBytes;
    private string $buffer = '';
    private bool $retrievalCompleted = false;
    private string $held = '';
    private string $pending = '';
    private int $sent = 0;
    private bool $started = false;
    private bool $capped = false;
    private bool $failed = false;
    /** @var array<string, mixed>|null */
    private ?array $completed = null;

    /** @param callable(string): void $sink */
    public function __construct(callable $sink, int $maxBytes = 20000)
    {
        $this->sink = $sink;
        $this->maxBytes = $maxBytes;
    }

    public function feed(string $chunk): void
    {
        // A carriage return ending one chunk may be completed by a line feed in the next, so normalise after appending.
        $this->buffer = str_replace("\r\n", "\n", $this->buffer . $chunk);
        while (($end = strpos($this->buffer, "\n\n")) !== false) {
            $block = substr($this->buffer, 0, $end);
            $this->buffer = substr($this->buffer, $end + 2);
            $this->handleBlock($block);
        }
    }

    /** Handles a final event that was not followed by a blank line. */
    public function finish(): void
    {
        if (trim($this->buffer) !== '') {
            $this->handleBlock($this->buffer);
        }
        $this->buffer = '';
    }

    public function failed(): bool
    {
        return $this->failed;
    }

    /** @return array<string, mixed>|null The provider's completed response object, or null if none arrived. */
    public function completedResponse(): ?array
    {
        return $this->completed;
    }

    private function handleBlock(string $block): void
    {
        $data = '';
        foreach (explode("\n", $block) as $line) {
            if (strpos($line, 'data:') === 0) {
                $data .= ltrim(substr($line, 5), ' ');
            }
        }
        if ($data === '' || $data === '[DONE]') {
            return;
        }
        $event = json_decode($data, true);
        if (!is_array($event) || !is_string($event['type'] ?? null)) {
            return;
        }

        switch ($event['type']) {
            case 'response.output_item.done':
                $item = $event['item'] ?? null;
                if (is_array($item) && ($item['type'] ?? null) === 'file_search_call' && ($item['status'] ?? null) === 'completed') {
                    $this->retrievalCompleted = true;
                    if ($this->held !== '') {
                        $held = $this->held;
                        $this->held = '';
                        $this->emit($held);
                    }
                }
                break;
            case 'response.output_text.delta':
                if (is_string($event['delta'] ?? null)) {
                    if ($this->retrievalCompleted) {
                        $this->emit($event['delta']);
                    } elseif (strlen($this->held) < $this->maxBytes) {
                        $this->held .= $event['delta'];
                    }
                }
                break;
            case 'response.completed':
                if (is_array($event['response'] ?? null)) {
                    $this->completed = $event['response'];
                }
                break;
            case 'response.failed':
            case 'response.incomplete':
            case 'error':
                $this->failed = true;
                break;
        }
    }

    /** Removes provider citation markers, which can span chunks, then passes the rest on. */
    private function emit(string $text): void
    {
        if ($this->capped) {
            return;
        }
        $this->pending .= $text;
        $out = '';
        while ($this->pending !== '') {
            $open = strpos($this->pending, self::MARKER_OPEN);
            if ($open === false) {
                $out .= $this->pending;
                $this->pending = '';
                break;
            }
            $out .= substr($this->pending, 0, $open);
            $close = strpos($this->pending, self::MARKER_CLOSE, $open);
            if ($close === false) {
                $this->pending = substr($this->pending, $open);
                if (strlen($this->pending) > self::MAX_MARKER_BYTES) {
                    $this->pending = '';
                }
                break;
            }
            $this->pending = substr($this->pending, $close + strlen(self::MARKER_CLOSE));
        }

        if (!$this->started) {
            $out = ltrim($out);
        }
        if ($out === '') {
            return;
        }
        if ($this->sent + strlen($out) > $this->maxBytes) {
            $this->capped = true;
            return;
        }
        $this->started = true;
        $this->sent += strlen($out);
        ($this->sink)($out);
    }
}
