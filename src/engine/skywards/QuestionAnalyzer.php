<?php

namespace AwardWallet\Engine\skywards;

use AwardWallet\Common\OneTimeCode\EmailQuestionAnalyzerInterface;

class QuestionAnalyzer implements EmailQuestionAnalyzerInterface
{
    public static function isOtcQuestion(string $question): bool
    {
        return str_starts_with($question, "An email with a 6-digit passcode has been sent to");
    }

    public static function getHoldsSession(): bool
    {
        return true;
    }
}
