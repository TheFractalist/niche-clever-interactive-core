<?php

declare(strict_types=1);

namespace NicheCleverInteractive\Core\Rest;

use NicheCleverInteractive\Core\AI\ChatService;
use NicheCleverInteractive\Core\Configuration\CoreSettings;

final class ChatRoute
{
    private ChatService $chat;

    public function __construct(ChatService $chat)
    {
        $this->chat = $chat;
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
        $outcome = $this->chat->handle($body, $settings, is_array($_SERVER ?? null) ? $_SERVER : []);
        $status = $this->statusFor($outcome->errorCode());

        if (class_exists('WP_REST_Response')) {
            return new \WP_REST_Response($outcome->payload(), $status);
        }

        return $outcome->payload();
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
