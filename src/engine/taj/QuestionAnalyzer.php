<?php

namespace AwardWallet\Engine\taj;

use AwardWallet\Common\OneTimeCode\EmailQuestionAnalyzerInterface;

class QuestionAnalyzer implements EmailQuestionAnalyzerInterface
{
    public static function isOtcQuestion(string $question): bool
    {
        return str_starts_with($question, "Enter the OTP we’ve sent you");
    }

    public static function getHoldsSession(): bool
    {
        return true;
    }
}
