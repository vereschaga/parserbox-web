<?php

namespace AwardWallet\Engine\myer;

use AwardWallet\Common\OneTimeCode\EmailQuestionAnalyzerInterface;

class QuestionAnalyzer implements EmailQuestionAnalyzerInterface
{
    public static function isOtcQuestion(string $question): bool
    {
        return str_starts_with($question, "We’ve sent a code to your email");
    }

    public static function getHoldsSession(): bool
    {
        return true;
    }
}
