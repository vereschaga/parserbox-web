<?php

namespace AwardWallet\Engine\velocity;

use AwardWallet\Common\OneTimeCode\EmailQuestionAnalyzerInterface;

class QuestionAnalyzer implements EmailQuestionAnalyzerInterface
{
    public static function isOtcQuestion(string $question): bool
    {
        return str_starts_with($question, "Enter the 6 digit verification code we sent to");
    }

    public static function getHoldsSession(): bool
    {
        return true;
    }
}
