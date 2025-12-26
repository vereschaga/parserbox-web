<?php

namespace AwardWallet\Engine\sncf;

use AwardWallet\Common\OneTimeCode\EmailQuestionAnalyzerInterface;

class QuestionAnalyzer implements EmailQuestionAnalyzerInterface
{
    public static function isOtcQuestion(string $question): bool
    {
        return str_starts_with($question, "In order to confirm your identity, a verification code has been sent to ");
    }

    public static function getHoldsSession(): bool
    {
        return true;
    }
}
