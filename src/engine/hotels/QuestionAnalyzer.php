<?php

namespace AwardWallet\Engine\hotels;

use AwardWallet\Common\OneTimeCode\EmailQuestionAnalyzerInterface;

class QuestionAnalyzer implements EmailQuestionAnalyzerInterface
{
    public static function isOtcQuestion(string $question): bool
    {
        return
            str_starts_with($question, "Enter the secure code we sent to your email.")
            || str_starts_with($question, "Gib den Sicherheitscode ein, den wir an")
        ;
    }

    public static function getHoldsSession(): bool
    {
        return true;
    }
}
