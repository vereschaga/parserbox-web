<?php

namespace AwardWallet\Engine\hortons;

use AwardWallet\Common\OneTimeCode\EmailQuestionAnalyzerInterface;

class QuestionAnalyzer implements EmailQuestionAnalyzerInterface
{
    public static function isOtcQuestion(string $question): bool
    {
        return str_starts_with($question, "Verify with code. We sent an email with login instructions");
    }

    public static function getHoldsSession(): bool
    {
        return false;
    }
}
