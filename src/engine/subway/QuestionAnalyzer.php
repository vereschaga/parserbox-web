<?php

namespace AwardWallet\Engine\subway;

use AwardWallet\Common\OneTimeCode\EmailQuestionAnalyzerInterface;

class QuestionAnalyzer implements EmailQuestionAnalyzerInterface
{
    public static function isOtcQuestion(string $question): bool
    {
        return strstr($question, "Your Verification code has been sent to your email address.");
    }

    public static function getHoldsSession(): bool
    {
        return true;
    }
}
