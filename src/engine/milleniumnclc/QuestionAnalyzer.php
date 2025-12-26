<?php

namespace AwardWallet\Engine\milleniumnclc;

use AwardWallet\Common\OneTimeCode\EmailQuestionAnalyzerInterface;

class QuestionAnalyzer implements EmailQuestionAnalyzerInterface
{
    public static function isOtcQuestion(string $question): bool
    {
        return str_starts_with($question, "Please enter the code that has been sent to your email.");
    }

    public static function getHoldsSession(): bool
    {
        return true;
    }
}
