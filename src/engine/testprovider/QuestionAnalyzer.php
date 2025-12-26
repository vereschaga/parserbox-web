<?php

namespace AwardWallet\Engine\testprovider;

use AwardWallet\Common\OneTimeCode\EmailQuestionAnalyzerInterface;

class QuestionAnalyzer implements EmailQuestionAnalyzerInterface
{
    public static function getEmailOtcQuestion(): string
    {
        return "Enter code that was sent to email**";
    }

    public static function isOtcQuestion(string $question): bool
    {
        return str_starts_with($question, "Enter code that was sent to email");
    }

    public static function getHoldsSession(): bool
    {
        return true;
    }
}
