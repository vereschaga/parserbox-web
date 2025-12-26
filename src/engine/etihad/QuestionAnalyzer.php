<?php

namespace AwardWallet\Engine\etihad;

use AwardWallet\Common\OneTimeCode\EmailQuestionAnalyzerInterface;

class QuestionAnalyzer implements EmailQuestionAnalyzerInterface
{
    public static function isOtcQuestion(string $question): bool
    {
        return
            str_starts_with($question, "One time password is sent to your email id. Please verify")
            || strstr($question, 'verification code sent to your')
        ;
    }

    public static function getHoldsSession(): bool
    {
        return true;
    }
}
