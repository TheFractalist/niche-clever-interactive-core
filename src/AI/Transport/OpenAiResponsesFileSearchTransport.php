<?php

declare(strict_types=1);

namespace NicheCleverInteractive\Core\AI\Transport;

use JsonException;
use NicheCleverInteractive\Core\Api\V1\Value\EvidenceCitationV1;
use NicheCleverInteractive\Core\Configuration\SecretResolver;
use RuntimeException;

final class OpenAiResponsesFileSearchTransport implements ResponseTransport
{
    private const RESPONSES_URL = 'https://api.openai.com/v1/responses';

    private SecretResolver $secrets;
    private ResponsesHttpClient $http;

    public function __construct(?SecretResolver $secrets = null, ?ResponsesHttpClient $http = null)
    {
        $this->secrets = $secrets ?? new SecretResolver();
        $this->http = $http ?? new WordPressResponsesHttpClient();
    }

    public function respond(TransportRequest $request): TransportResult
    {
        $key = $this->secrets->forProvider();
        $vectorStore = $request->vectorStoreReference();
        if ($key === null || preg_match('/^vs_[A-Za-z0-9_-]{6,}$/', $vectorStore) !== 1) {
            throw new ProviderFailure(ProviderFailure::NOT_CONFIGURED);
        }

        try {
            $body = json_encode(self::requestPayloadFor($request), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        } catch (JsonException $exception) {
            throw new ProviderFailure(ProviderFailure::NOT_CONFIGURED);
        }

        $response = $this->http->post(self::RESPONSES_URL, [
            'Authorization' => 'Bearer ' . $key,
            'Content-Type' => 'application/json',
        ], $body, $request->options()->timeoutSeconds());

        if ($response->statusCode() < 200 || $response->statusCode() >= 300) {
            throw ProviderFailure::fromHttpStatus($response->statusCode());
        }

        try {
            $payload = json_decode($response->body(), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new ProviderFailure(ProviderFailure::MALFORMED_RESPONSE);
        }

        if (!is_array($payload)) {
            throw new ProviderFailure(ProviderFailure::MALFORMED_RESPONSE);
        }

        return $this->toTransportResult($payload, $request);
    }

    /** @return array<string, mixed> */
    public static function requestPayloadFor(TransportRequest $request): array
    {
        $options = $request->options();
        $payload = [
            'model' => $options->model(),
            'instructions' => $request->instructions(),
            'input' => self::inputFor($request),
            'tools' => [[
                'type' => 'file_search',
                'vector_store_ids' => [$request->vectorStoreReference()],
                'max_num_results' => $options->retrievalDepth(),
            ]],
            'max_output_tokens' => $options->maxOutputTokens(),
        ];

        $effort = $options->effortToSend();
        if ($effort !== null) {
            $payload['reasoning'] = ['effort' => $effort];
        }

        return $payload;
    }

    /**
     * The current question alone as a string, exactly as before, or, when Core supplied earlier
     * turns, role-tagged messages ending with the current question.
     *
     * @return string|list<array{role: string, content: string}>
     */
    private static function inputFor(TransportRequest $request)
    {
        if ($request->earlierTurns() === []) {
            return $request->question();
        }

        $messages = [];
        foreach ($request->earlierTurns() as $turn) {
            $messages[] = ['role' => 'user', 'content' => $turn->question()];
            $messages[] = ['role' => 'assistant', 'content' => $turn->answer()];
        }
        $messages[] = ['role' => 'user', 'content' => $request->question()];

        return $messages;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function toTransportResult(array $payload, TransportRequest $request): TransportResult
    {
        $output = $payload['output'] ?? null;
        if (!is_array($output) || count($output) > 100) {
            throw new ProviderFailure(ProviderFailure::INCOMPLETE_RESPONSE);
        }

        $completedFileSearch = false;
        $answerParts = [];
        $citations = [];
        foreach ($output as $item) {
            if (!is_array($item)) {
                continue;
            }

            if (($item['type'] ?? null) === 'file_search_call' && ($item['status'] ?? null) === 'completed') {
                $completedFileSearch = true;
            }

            if (($item['type'] ?? null) !== 'message' || !is_array($item['content'] ?? null)) {
                continue;
            }

            foreach ($item['content'] as $content) {
                if (!is_array($content) || ($content['type'] ?? null) !== 'output_text' || !is_string($content['text'] ?? null)) {
                    continue;
                }

                $answerParts[] = $content['text'];
                $this->collectCitations($content['annotations'] ?? null, $citations);
            }
        }

        return new TransportResult(
            $this->publicAnswerText(implode("\n", $answerParts)),
            $completedFileSearch,
            $request->policyFingerprint(),
            $request->corpusFingerprint(),
            array_values($citations)
        );
    }

    /**
     * Keeps only the file reference and name of file citations, at most 10 distinct sources.
     * Anything malformed is skipped; no text, offset or payload is retained.
     *
     * @param mixed $annotations
     * @param array<string, EvidenceCitationV1> $citations
     */
    private function collectCitations($annotations, array &$citations): void
    {
        if (!is_array($annotations)) {
            return;
        }

        foreach ($annotations as $annotation) {
            if (count($citations) >= 10) {
                return;
            }
            if (!is_array($annotation) || ($annotation['type'] ?? null) !== 'file_citation' || !is_string($annotation['file_id'] ?? null)) {
                continue;
            }
            try {
                $citation = new EvidenceCitationV1($annotation['file_id'], is_string($annotation['filename'] ?? null) ? $annotation['filename'] : '');
            } catch (\InvalidArgumentException $exception) {
                continue;
            }
            $citations[$citation->reference()] = $citation;
        }
    }

    private function publicAnswerText(string $text): string
    {
        $withoutCitations = preg_replace('/\\s*\\x{E200}filecite\\x{E202}[^\\s\\x{E201}]+(?:\\x{E202}[^\\s\\x{E201}]+)*\\x{E201}/u', '', $text);

        return trim(is_string($withoutCitations) ? $withoutCitations : $text);
    }
}
