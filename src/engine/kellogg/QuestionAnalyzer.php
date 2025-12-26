<?php

namespace AwardWallet\Engine\kellogg;

use AwardWallet\Common\OneTimeCode\EmailQuestionAnalyzerInterface;

class QuestionAnalyzer implements EmailQuestionAnalyzerInterface
{
    public static function isOtcQuestion(string $question): bool
    {
        return str_starts_with($question, "A verification code has been sent to");
    }

    public static function getHoldsSession(): bool
    {
        return true;
    }
}
