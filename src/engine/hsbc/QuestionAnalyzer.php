<?php

namespace AwardWallet\Engine\hsbc;

use AwardWallet\Common\OneTimeCode\EmailQuestionAnalyzerInterface;

class QuestionAnalyzer implements EmailQuestionAnalyzerInterface
{
    public static function isOtcQuestion(string $question): bool
    {
        return str_starts_with($question, "code has been sent to your");
    }

    public static function getHoldsSession(): bool
    {
        return true;
    }
}
