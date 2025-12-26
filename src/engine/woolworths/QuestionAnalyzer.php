<?php

namespace AwardWallet\Engine\woolworths;

use AwardWallet\Common\OneTimeCode\EmailQuestionAnalyzerInterface;

class QuestionAnalyzer implements EmailQuestionAnalyzerInterface
{
    public static function isOtcQuestion(string $question): bool
    {
        return stripos($question, "We've sent a one time verification code") !== false;
    }

    public static function getHoldsSession(): bool
    {
        return true;
    }
}
