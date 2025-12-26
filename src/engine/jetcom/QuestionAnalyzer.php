<?php

namespace AwardWallet\Engine\jetcom;

use AwardWallet\Common\OneTimeCode\EmailQuestionAnalyzerInterface;

class QuestionAnalyzer implements EmailQuestionAnalyzerInterface
{
    public static function isOtcQuestion(string $question): bool
    {
        return str_starts_with($question, 'We’ve sent a verification code to');
    }

    public static function getHoldsSession(): bool
    {
        return true;
    }
}
