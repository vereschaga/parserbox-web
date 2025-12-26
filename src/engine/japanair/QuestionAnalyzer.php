<?php

namespace AwardWallet\Engine\japanair;

use AwardWallet\Common\OneTimeCode\EmailQuestionAnalyzerInterface;

class QuestionAnalyzer implements EmailQuestionAnalyzerInterface
{
    public static function isOtcQuestion(string $question): bool
    {
        return str_starts_with($question, "The one-time password was sent to");
    }

    public static function getHoldsSession(): bool
    {
        return true;
    }
}
