<?php

namespace AwardWallet\Engine\shangrila;

use AwardWallet\Common\OneTimeCode\EmailQuestionAnalyzerInterface;

class QuestionAnalyzer implements EmailQuestionAnalyzerInterface
{
    public static function isOtcQuestion(string $question): bool
    {
        return
            strpos($question, "A verification code has been sent to your email address") === 0
        ;
    }

    public static function getHoldsSession(): bool
    {
        return true;
    }
}
