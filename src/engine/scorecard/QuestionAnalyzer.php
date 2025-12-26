<?php

namespace AwardWallet\Engine\scorecard;

use AwardWallet\Common\OneTimeCode\EmailQuestionAnalyzerInterface;

class QuestionAnalyzer implements EmailQuestionAnalyzerInterface
{
    public static function isOtcQuestion(string $question): bool
    {
        return str_starts_with($question, "We sent a one-time code via the method you selected");
    }

    public static function getHoldsSession(): bool
    {
        return true;
    }
}
