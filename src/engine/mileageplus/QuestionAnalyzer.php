<?php

namespace AwardWallet\Engine\mileageplus;

use AwardWallet\Common\OneTimeCode\EmailQuestionAnalyzerInterface;

class QuestionAnalyzer implements EmailQuestionAnalyzerInterface
{
    public static function isOtcQuestion(string $question): bool
    {
        return stripos($question, "Enter the verification code sent") !== false;
    }

    public static function getHoldsSession(): bool
    {
        return true;
    }
}
