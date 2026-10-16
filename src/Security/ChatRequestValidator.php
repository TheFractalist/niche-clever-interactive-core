<?php

declare(strict_types=1);

namespace NicheCleverInteractive\Core\Security;

use NicheCleverInteractive\Core\Configuration\CoreSettings;
use NicheCleverInteractive\Core\Conversation\ConversationId;

final class ChatRequestValidator
{
    private QuestionValidator $questions;

    public function __construct(?QuestionValidator $questions = null)
    {
        $this->questions = $questions ?? new QuestionValidator();
    }

    /**
     * @param mixed $request
     * @param bool $namedProfiles Whether an extension may resolve non-default profile ids. When false, only the default id is valid.
     * @return array{question: string, profile_id: string, response_preset: ?string, conversation_id: ?string}|null
     */
    public function validate($request, CoreSettings $settings, bool $namedProfiles = false): ?array
    {
        if (!is_array($request)) {
            return null;
        }

        $question = $this->questions->validate($request['question'] ?? null);
        if ($question === null) {
            return null;
        }

        $profile = $request['profile_id'] ?? CoreSettings::DEFAULT_PROFILE_ID;
        if (!is_string($profile)) {
            return null;
        }
        $isDefault = $profile === CoreSettings::DEFAULT_PROFILE_ID;
        if (!$isDefault && (!$namedProfiles || preg_match('/^[a-z0-9][a-z0-9_-]{0,63}$/D', $profile) !== 1)) {
            return null;
        }

        // A client may repeat a profile's preset but never change it. For a named profile the
        // comparison happens after Core resolves it, so the requested value is returned as sent.
        $requested = $request['response_preset'] ?? null;
        if ($requested !== null && !is_string($requested)) {
            return null;
        }
        if ($isDefault && $requested !== null && $requested !== $settings->responsePreset()) {
            return null;
        }
        $preset = $isDefault ? $settings->responsePreset() : $requested;

        $conversation = $request['conversation_id'] ?? null;
        if ($conversation !== null && !ConversationId::isValid($conversation)) {
            return null;
        }

        return [
            'question' => $question,
            'profile_id' => $profile,
            'response_preset' => $preset,
            'conversation_id' => $conversation,
        ];
    }
}
