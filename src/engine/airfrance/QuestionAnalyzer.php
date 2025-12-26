<?php

namespace AwardWallet\Engine\airfrance;

use AwardWallet\Common\OneTimeCode\EmailQuestionAnalyzerInterface;

class QuestionAnalyzer implements EmailQuestionAnalyzerInterface
{
    public static function isOtcQuestion(string $question): bool
    {
        return str_contains($question, "ve sent the PIN code to your e-mail")
            || str_contains($question, 've sent the PIN code to your email address')
            || str_contains($question, 'To verify your email address, a one-time PIN code is sent');
    }

    public static function getHoldsSession(): bool
    {
        return true;
    }
}
