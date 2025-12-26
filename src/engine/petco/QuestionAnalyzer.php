<?php

namespace AwardWallet\Engine\petco;

use AwardWallet\Common\OneTimeCode\EmailQuestionAnalyzerInterface;

class QuestionAnalyzer implements EmailQuestionAnalyzerInterface
{
    public static function isOtcQuestion(string $question): bool
    {
        return str_starts_with($question, "We've sent an email with your code to");
    }

    public static function getHoldsSession(): bool
    {
        return false;
    }
}
