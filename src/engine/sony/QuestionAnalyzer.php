<?php

namespace AwardWallet\Engine\sony;

use AwardWallet\Common\OneTimeCode\EmailQuestionAnalyzerInterface;

class QuestionAnalyzer implements EmailQuestionAnalyzerInterface
{
    public static function isOtcQuestion(string $question): bool
    {
        return str_starts_with($question, "2-step verification is enabled. Check your email address for a verification code.");
    }

    public static function getHoldsSession(): bool
    {
        return true;
    }
}
