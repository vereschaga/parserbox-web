<?php

namespace AwardWallet\Engine\samsclub;

use AwardWallet\Common\OneTimeCode\EmailQuestionAnalyzerInterface;

class QuestionAnalyzer implements EmailQuestionAnalyzerInterface
{
    public static function isOtcQuestion(string $question): bool
    {
        return str_starts_with($question, "Enter the 6-digit code we sent to");
    }

    public static function getHoldsSession(): bool
    {
        return true;
    }
}
