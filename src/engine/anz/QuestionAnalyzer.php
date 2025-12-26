<?php

namespace AwardWallet\Engine\anz;

use AwardWallet\Common\OneTimeCode\EmailQuestionAnalyzerInterface;

class QuestionAnalyzer implements EmailQuestionAnalyzerInterface
{
    public static function isOtcQuestion(string $question): bool
    {
        return str_starts_with($question, "A One-Time Password (OTP) has been sent to");
    }

    public static function getHoldsSession(): bool
    {
        return true;
    }
}
