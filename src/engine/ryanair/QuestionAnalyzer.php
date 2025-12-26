<?php

namespace AwardWallet\Engine\ryanair;

use AwardWallet\Common\OneTimeCode\EmailQuestionAnalyzerInterface;

class QuestionAnalyzer implements EmailQuestionAnalyzerInterface
{
    public static function isOtcQuestion(string $question): bool
    {
        return str_starts_with($question, "Please enter the 8 character verification code sent to");
    }

    public static function getHoldsSession(): bool
    {
        return true;
    }
}
