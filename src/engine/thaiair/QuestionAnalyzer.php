<?php

namespace AwardWallet\Engine\thaiair;

use AwardWallet\Common\OneTimeCode\EmailQuestionAnalyzerInterface;

class QuestionAnalyzer implements EmailQuestionAnalyzerInterface
{
    public static function isOtcQuestion(string $question): bool
    {
        return str_starts_with($question, "The OTP Code is a 4 digit number sent to your registered e-mail address.");
    }

    public static function getHoldsSession(): bool
    {
        return true;
    }
}
