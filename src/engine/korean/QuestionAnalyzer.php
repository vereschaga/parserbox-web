<?php

namespace AwardWallet\Engine\korean;

use AwardWallet\Common\OneTimeCode\EmailQuestionAnalyzerInterface;

class QuestionAnalyzer implements EmailQuestionAnalyzerInterface
{
    public static function isOtcQuestion(string $question): bool
    {
        return str_starts_with($question, "Enter 8-digit authentication code");
    }

    public static function getHoldsSession(): bool
    {
        return true;
    }
}
