<?php

namespace AwardWallet\Engine\utair;

use AwardWallet\Common\OneTimeCode\EmailQuestionAnalyzerInterface;

class QuestionAnalyzer implements EmailQuestionAnalyzerInterface
{
    public static function isOtcQuestion(string $question): bool
    {
        return stripos($question, "Please enter the ") !== false;
    }

    public static function getHoldsSession(): bool
    {
        return true;
    }
}
