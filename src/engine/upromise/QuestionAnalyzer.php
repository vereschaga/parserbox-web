<?php

namespace AwardWallet\Engine\upromise;

use AwardWallet\Common\OneTimeCode\EmailQuestionAnalyzerInterface;

class QuestionAnalyzer implements EmailQuestionAnalyzerInterface
{
    public static function isOtcQuestion(string $question): bool
    {
        return str_starts_with($question, "Enter the verification code we emailed to ");
    }

    public static function getHoldsSession(): bool
    {
        return true;
    }
}
