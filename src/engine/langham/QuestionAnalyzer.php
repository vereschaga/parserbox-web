<?php

namespace AwardWallet\Engine\langham;

use AwardWallet\Common\OneTimeCode\EmailQuestionAnalyzerInterface;

class QuestionAnalyzer implements EmailQuestionAnalyzerInterface
{
    public static function isOtcQuestion(string $question): bool
    {
        return str_starts_with($question, "Check your inbox now. We've sent a verification code to you");
    }

    public static function getHoldsSession(): bool
    {
        return true;
    }
}
