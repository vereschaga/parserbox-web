<?php

namespace AwardWallet\Engine\qdoba;

use AwardWallet\Common\OneTimeCode\EmailQuestionAnalyzerInterface;

class QuestionAnalyzer implements EmailQuestionAnalyzerInterface
{
    public static function isOtcQuestion(string $question): bool
    {
        return stripos($question, "ve sent an email with your code to") !== false;
    }

    public static function getHoldsSession(): bool
    {
        return true;
    }
}
