<?php

declare(strict_types=1);

namespace NicheCleverInteractive\Core\Security;

final class QuestionValidator
{
    public function validate($question): ?string
    {
        if (!is_string($question)) {
            return null;
        }

        $question = trim($question);
        if ($question === '' || strlen($question) > 2000 || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', $question) === 1) {
            return null;
        }

        if ($this->isInjectionAttempt($question)) {
            return null;
        }

        return $question;
    }

    public function isInjectionAttempt(string $question): bool
    {
        $normalised = strtolower(preg_replace('/\s+/', ' ', $question) ?? '');
        $patterns = [
            '/\b(ignore|disregard|override)\b.{0,80}\b(previous|system|developer|instructions?)\b/',
            '/\b(reveal|show|print|repeat)\b.{0,80}\b(system prompt|hidden instructions?|api key|secret)\b/',
        ];
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $normalised) === 1) {
                return true;
            }
        }

        return false;
    }
}
