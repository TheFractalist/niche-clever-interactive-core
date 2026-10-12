<?php

declare(strict_types=1);

namespace NicheCleverInteractive\Core\Security;

use NicheCleverInteractive\Core\Configuration\CoreSettings;

final class ChatRequestValidator
{
    private QuestionValidator $questions;

    public function __construct(?QuestionValidator $questions = null)
    {
        $this->questions = $questions ?? new QuestionValidator();
    }

    /**
     * @param mixed $request
     * @return array{question: string, profile_id: string, response_preset: string}|null
     */
    public function validate($request, CoreSettings $settings): ?array
    {
        if (!is_array($request)) {
            return null;
        }

        $question = $this->questions->validate($request['question'] ?? null);
        if ($question === null) {
            return null;
        }

        $profile = $request['profile_id'] ?? CoreSettings::DEFAULT_PROFILE_ID;
        if (!is_string($profile) || $profile !== CoreSettings::DEFAULT_PROFILE_ID) {
            return null;
        }

        $preset = $request['response_preset'] ?? $settings->responsePreset();
        if (!is_string($preset) || $preset !== $settings->responsePreset()) {
            return null;
        }

        return [
            'question' => $question,
            'profile_id' => $profile,
            'response_preset' => $preset,
        ];
    }
}
