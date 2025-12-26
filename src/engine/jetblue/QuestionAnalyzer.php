<?php

namespace AwardWallet\Engine\jetblue;

use AwardWallet\Common\OneTimeCode\EmailQuestionAnalyzerInterface;

class QuestionAnalyzer implements EmailQuestionAnalyzerInterface
{
    public static function isOtcQuestion(string $question): bool
    {
        return
            str_starts_with($question, "We sent an email to")
            || stripos($question, "Check your email and enter the code below") !== false
        ;
    }

    public static function getHoldsSession(): bool
    {
        return true;
    }
}
