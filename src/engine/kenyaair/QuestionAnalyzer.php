<?php

namespace AwardWallet\Engine\kenyaair;

use AwardWallet\Common\OneTimeCode\EmailQuestionAnalyzerInterface;

class QuestionAnalyzer implements EmailQuestionAnalyzerInterface
{
    public static function isOtcQuestion(string $question): bool
    {
        return str_starts_with($question, "We have sent you a one-time password");
    }

    public static function getHoldsSession(): bool
    {
        return true;
    }
}
