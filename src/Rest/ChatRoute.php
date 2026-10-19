<?php

declare(strict_types=1);

namespace NicheCleverInteractive\Core\Rest;

use NicheCleverInteractive\Core\AI\ChatService;
use NicheCleverInteractive\Core\Configuration\CoreSettings;

final class ChatRoute
{
    private ChatService $chat;
    /** @var (callable(): EventStream)|null */
    private $streams;

    /** @param (callable(): EventStream)|null $streams Supplies the event stream; tests replace it. */
    public function __construct(ChatService $chat, ?callable $streams = null)
    {
        $this->chat = $chat;
        $this->streams = $streams;
    }

    public function register(): void
    {
        if (!function_exists('add_action') || !function_exists('register_rest_route')) {
            return;
        }

        add_action('rest_api_init', function (): void {
            register_rest_route('ncli/v1', '/chat', [
                'methods' => 'POST',
                'callback' => [$this, 'respond'],
                'permission_callback' => '__return_true',
            ]);
        });
    }

    /** @param mixed $request */
    public function respond($request)
    {
        $body = is_object($request) && method_exists($request, 'get_json_params') ? $request->get_json_params() : null;
        $settings = $this->loadSettings();
        $server = is_array($_SERVER ?? null) ? $_SERVER : [];

        // A browser that can read a stream asks for one. Anything else gets the ordinary JSON answer.
        $stream = $this->wantsStream($request, $server) ? ($this->streams !== null ? ($this->streams)() : new EventStream()) : null;
        $onText = $stream === null ? null : static function (string $text) use ($stream): void {
            $stream->send(['type' => 'delta', 'text' => $text]);
        };

        $outcome = $this->chat->handle($body, $settings, $server, $onText);

        if ($stream !== null && $stream->hasBegun()) {
            // Text has already been sent, so the final event carries the complete validated answer (or the
            // public error) and the browser replaces what it showed. WordPress must not add a second body.
            $stream->send(['type' => $outcome->isSuccess() ? 'done' : 'error'] + $outcome->payload());
            $stream->end();

            return null;
        }

        $status = $this->statusFor($outcome->errorCode());
        if (class_exists('WP_REST_Response')) {
            return new \WP_REST_Response($outcome->payload(), $status);
        }

        return $outcome->payload();
    }

    /**
     * @param mixed $request
     * @param array<string, mixed> $server
     */
    private function wantsStream($request, array $server): bool
    {
        $accept = '';
        if (is_object($request) && method_exists($request, 'get_header')) {
            $header = $request->get_header('accept');
            $accept = is_string($header) ? $header : '';
        }
        if ($accept === '' && is_string($server['HTTP_ACCEPT'] ?? null)) {
            $accept = $server['HTTP_ACCEPT'];
        }

        return stripos($accept, 'text/event-stream') !== false;
    }

    private function loadSettings(): CoreSettings
    {
        $stored = function_exists('get_option') ? get_option(CoreSettings::OPTION_NAME, CoreSettings::defaults()) : CoreSettings::defaults();
        return CoreSettings::fromArray(is_array($stored) ? $stored : CoreSettings::defaults());
    }

    private function statusFor(?string $errorCode): int
    {
        return match ($errorCode) {
            null => 200,
            'invalid_request', 'policy_refusal' => 400,
            'access_required' => 403,
            'allowance_exceeded' => 429,
            'evidence_failure' => 422,
            default => 503,
        };
    }
}
