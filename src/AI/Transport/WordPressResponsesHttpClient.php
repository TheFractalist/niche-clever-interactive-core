<?php

declare(strict_types=1);

namespace NicheCleverInteractive\Core\AI\Transport;

use RuntimeException;

final class WordPressResponsesHttpClient implements StreamingResponsesHttpClient
{
    /** @param array<string, string> $headers */
    public function post(string $url, array $headers, string $body, int $timeout): ResponsesHttpResponse
    {
        return $this->request($url, $headers, $body, $timeout);
    }

    /**
     * Keeps the provider request inside WordPress's HTTP API, but asks its documented cURL hook to
     * deliver the response body as it arrives. The callback is temporary and guards the one fixed
     * Core-owned provider URL. A non-cURL transport simply retains the complete event body and it is
     * passed to the same reader once after the request; Core never retries the provider request.
     *
     * @param array<string, string> $headers
     * @param callable(string): void $onChunk
     */
    public function postStream(string $url, array $headers, string $body, int $timeout, callable $onChunk): ResponsesHttpResponse
    {
        if (!function_exists('add_action') || !function_exists('remove_action') || !function_exists('curl_setopt') || !function_exists('curl_getinfo')) {
            return $this->forwardRetainedBody($this->request($url, $headers, $body, $timeout), $onChunk);
        }

        $intercepted = false;
        $callbackFailure = null;
        $hook = static function ($handle, $arguments, $hookUrl) use ($url, $onChunk, &$intercepted, &$callbackFailure): void {
            if (!is_string($hookUrl) || $hookUrl !== $url) {
                return;
            }

            // phpcs:ignore WordPress.WP.AlternativeFunctions.curl_curl_setopt -- WordPress documents http_api_curl for configuring its own handle; this temporary callback streams only the fixed Core provider response.
            $configured = curl_setopt($handle, CURLOPT_WRITEFUNCTION, static function ($streamHandle, string $chunk) use ($onChunk, &$callbackFailure): int {
                // phpcs:ignore WordPress.WP.AlternativeFunctions.curl_curl_getinfo -- The status check prevents a non-2xx provider body becoming early display text in the documented WordPress HTTP hook.
                $status = (int) curl_getinfo($streamHandle, CURLINFO_RESPONSE_CODE);
                if ($status >= 200 && $status < 300 && $callbackFailure === null) {
                    try {
                        $onChunk($chunk);
                    } catch (\Throwable $exception) {
                        $callbackFailure = $exception;
                    }
                }

                return strlen($chunk);
            });
            if ($configured) {
                $intercepted = true;
            }
        };

        add_action('http_api_curl', $hook, 10, 3);
        try {
            $response = $this->request($url, $headers, $body, $timeout);
        } finally {
            remove_action('http_api_curl', $hook, 10);
        }

        if ($callbackFailure !== null) {
            throw new RuntimeException('The streamed provider response could not be processed.');
        }

        return $intercepted ? $response : $this->forwardRetainedBody($response, $onChunk);
    }

    /**
     * @param array<string, string> $headers
     */
    private function request(string $url, array $headers, string $body, int $timeout): ResponsesHttpResponse
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
     * @param callable(string): void $onChunk
     */
    private function forwardRetainedBody(ResponsesHttpResponse $response, callable $onChunk): ResponsesHttpResponse
    {
        if ($response->statusCode() >= 200 && $response->statusCode() < 300 && $response->body() !== '') {
            $onChunk($response->body());
        }

        return $response;
    }
}
