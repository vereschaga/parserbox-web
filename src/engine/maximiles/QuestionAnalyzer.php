<?php

namespace AwardWallet\Engine\maximiles;

use AwardWallet\Common\OneTimeCode\EmailQuestionAnalyzerInterface;

class QuestionAnalyzer implements EmailQuestionAnalyzerInterface
{
    public static function isOtcQuestion(string $question): bool
    {
        return str_starts_with($question, "Please enter the code") || str_starts_with($question, "Veuillez entrer le code");
    }

    public static function getHoldsSession(): bool
    {
        return true;
    }
}
