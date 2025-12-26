<?php

namespace AwardWallet\Engine\nordic;

use AwardWallet\Common\OneTimeCode\EmailQuestionAnalyzerInterface;

class QuestionAnalyzer implements EmailQuestionAnalyzerInterface
{
    public static function isOtcQuestion(string $question): bool
    {
        return str_starts_with($question, "For your safety we have implemented a two-step verification");
    }

    public static function getHoldsSession(): bool
    {
        return true;
    }
}
