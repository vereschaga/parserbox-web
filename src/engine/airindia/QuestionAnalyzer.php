<?php

namespace AwardWallet\Engine\airindia;

use AwardWallet\Common\OneTimeCode\EmailQuestionAnalyzerInterface;

class QuestionAnalyzer implements EmailQuestionAnalyzerInterface
{
    public static function isOtcQuestion(string $question): bool
    {
        return
            strpos($question, "OTP sent to") === 0
        ;
    }

    public static function getHoldsSession(): bool
    {
        return true;
    }
}
