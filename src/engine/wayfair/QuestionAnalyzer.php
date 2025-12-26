<?php

namespace AwardWallet\Engine\wayfair;

use AwardWallet\Common\OneTimeCode\EmailQuestionAnalyzerInterface;

class QuestionAnalyzer implements EmailQuestionAnalyzerInterface
{
    public static function isOtcQuestion(string $question): bool
    {
        return str_starts_with($question, "Enter the Code We Emailed You");
    }

    public static function getHoldsSession(): bool
    {
        return true;
    }
}
