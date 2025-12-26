<?php

namespace AwardWallet\Engine\usaa;

use AwardWallet\Common\OneTimeCode\EmailQuestionAnalyzerInterface;

class QuestionAnalyzer implements EmailQuestionAnalyzerInterface
{
    public static function isOtcQuestion(string $question): bool
    {
        return stripos($question, " 6-digit security code ") !== false;
    }

    public static function getHoldsSession(): bool
    {
        return true;
    }
}
