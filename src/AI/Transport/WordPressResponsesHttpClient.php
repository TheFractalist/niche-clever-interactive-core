<?php

declare(strict_types=1);

namespace NicheCleverInteractive\Core\AI\Transport;

use RuntimeException;

final class WordPressResponsesHttpClient implements StreamingResponsesHttpClient
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

    /**
     * WordPress's HTTP API cannot stream a body, so for this one request the cURL handle it is about
     * to use gets a write callback through the documented `http_api_curl` action. The callback is
     * removed again afterwards. Where the cURL transport is not in use nothing is streamed and the
     * whole body is returned as for `post()`.
     *
     * @param array<string, string> $headers
     * @param callable(string): void $onChunk
     */
    public function postStream(string $url, array $headers, string $body, int $timeout, callable $onChunk): ResponsesHttpResponse
    {
        if (!function_exists('add_action') || !function_exists('remove_action') || !function_exists('curl_setopt')) {
            return $this->post($url, $headers, $body, $timeout);
        }

        $errorBody = '';
        $hook = static function ($handle) use ($onChunk, &$errorBody): void {
            curl_setopt($handle, CURLOPT_WRITEFUNCTION, static function ($curl, $data) use ($onChunk, &$errorBody): int {
                $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
                if ($status >= 200 && $status < 300) {
                    $onChunk((string) $data);
                } elseif (strlen($errorBody) < 65536) {
                    $errorBody .= (string) $data;
                }

                return strlen((string) $data);
            });
        };

        add_action('http_api_curl', $hook, 10, 1);
        try {
            $response = wp_remote_post($url, [
                'headers' => $headers,
                'body' => $body,
                'timeout' => $timeout,
                'redirection' => 0,
                'data_format' => 'body',
            ]);
        } finally {
            remove_action('http_api_curl', $hook, 10);
        }

        if (function_exists('is_wp_error') && is_wp_error($response)) {
            throw ProviderFailure::fromTransportError((string) $response->get_error_message()); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception text is a closed internal reason code; it is caught and never printed.
        }

        $status = (int) wp_remote_retrieve_response_code($response);
        $remaining = (string) wp_remote_retrieve_body($response);

        return new ResponsesHttpResponse($status, $errorBody !== '' ? $errorBody : $remaining);
    }
}
