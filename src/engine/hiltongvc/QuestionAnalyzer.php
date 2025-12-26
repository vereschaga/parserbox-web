<?php

namespace AwardWallet\Engine\hiltongvc;

use AwardWallet\Common\OneTimeCode\EmailQuestionAnalyzerInterface;

class QuestionAnalyzer implements EmailQuestionAnalyzerInterface
{
    public static function isOtcQuestion(string $question): bool
    {
        return str_starts_with($question, "Please enter the Two Factor Auth Code");
    }

    public static function getHoldsSession(): bool
    {
        return true;
    }
}
