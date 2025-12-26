<?php

namespace AwardWallet\Engine\legovip;

use AwardWallet\Common\OneTimeCode\EmailQuestionAnalyzerInterface;

class QuestionAnalyzer implements EmailQuestionAnalyzerInterface
{
    public static function isOtcQuestion(string $question): bool
    {
        return str_starts_with($question, "We’ve sent a two-factor code to your email.") || str_starts_with($question, "To keep your account safe, we want to make sure it");
    }

    public static function getHoldsSession(): bool
    {
        return true;
    }
}
