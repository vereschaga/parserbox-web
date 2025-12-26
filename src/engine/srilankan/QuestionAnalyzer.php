<?php

namespace AwardWallet\Engine\srilankan;

use AwardWallet\Common\OneTimeCode\EmailQuestionAnalyzerInterface;

class QuestionAnalyzer implements EmailQuestionAnalyzerInterface
{
    public static function isOtcQuestion(string $question): bool
    {
        return str_starts_with($question, "A One-Time Password has been sent to");
    }

    public static function getHoldsSession(): bool
    {
        return true;
    }
}
