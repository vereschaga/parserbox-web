<?php

namespace AwardWallet\Engine\qantas;

use AwardWallet\Common\OneTimeCode\EmailQuestionAnalyzerInterface;

class QuestionAnalyzer implements EmailQuestionAnalyzerInterface
{
    public static function isOtcQuestion(string $question): bool
    {
        return str_starts_with($question, "Please enter the code that has been sent to your registered");
    }

    public static function getHoldsSession(): bool
    {
        return false;
    }
}
