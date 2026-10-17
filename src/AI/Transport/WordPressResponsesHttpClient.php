<?php

declare(strict_types=1);

namespace NicheCleverInteractive\Core\AI\Transport;

use RuntimeException;

final class WordPressResponsesHttpClient implements ResponsesHttpClient
{
    /** @param array<string, string> $headers */
    public function post(string $url, array $headers, string $body, int $timeout): ResponsesHttpResponse
    {
        if (!function_exists('wp_remote_post') || !function_exists('wp_remote_retrieve_response_code') || !function_exists('wp_remote_retrieve_body')) {
            throw new RuntimeException('The WordPress HTTP client is unavailable.');
        }

        $response = wp_remote_post($url, [
            'headers' => $headers,
            'body' => $body,
            'timeout' => $timeout,
            'redirection' => 0,
            'data_format' => 'body',
        ]);

        if (function_exists('is_wp_error') && is_wp_error($response)) {
            // Only the closed reason is kept; the error text is never stored or shown.
            throw ProviderFailure::fromTransportError((string) $response->get_error_message()); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception text is a closed internal reason code; it is caught and never printed.
        }

        return new ResponsesHttpResponse(
            (int) wp_remote_retrieve_response_code($response),
            (string) wp_remote_retrieve_body($response)
        );
    }
}
