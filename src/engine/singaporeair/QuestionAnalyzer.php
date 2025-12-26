<?php

namespace AwardWallet\Engine\singaporeair;

use AwardWallet\Common\OneTimeCode\EmailQuestionAnalyzerInterface;

class QuestionAnalyzer implements EmailQuestionAnalyzerInterface
{
    public static function isOtcQuestion(string $question): bool
    {
        return str_starts_with($question, "A verification email has been sent to you");
    }

    public static function getHoldsSession(): bool
    {
        return true;
    }
}
