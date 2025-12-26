<?php

namespace AwardWallet\Engine\navy;

use AwardWallet\Common\OneTimeCode\EmailQuestionAnalyzerInterface;

class QuestionAnalyzer implements EmailQuestionAnalyzerInterface
{
    public static function isOtcQuestion(string $question): bool
    {
        return str_starts_with($question, "We sent a security code to");
    }

    public static function getHoldsSession(): bool
    {
        return true;
    }
}
