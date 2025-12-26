<?php

namespace AwardWallet\Engine\marriott;

use AwardWallet\Common\OneTimeCode\EmailQuestionAnalyzerInterface;

class QuestionAnalyzer implements EmailQuestionAnalyzerInterface
{
    public static function isOtcQuestion(string $question): bool
    {
        return stripos($question, "We sent a verification code via email to") !== false
            || stripos($question, "Please enter the code that has been sent to your registered email") !== false;
    }

    public static function getHoldsSession(): bool
    {
        return true;
    }
}
