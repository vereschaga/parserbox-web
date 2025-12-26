<?php

namespace AwardWallet\Engine\s7;

use AwardWallet\Common\OneTimeCode\EmailQuestionAnalyzerInterface;

class QuestionAnalyzer implements EmailQuestionAnalyzerInterface
{
    public static function isOtcQuestion(string $question): bool
    {
        return str_starts_with($question, "Отправили письмо с кодом на почту");
    }

    public static function getHoldsSession(): bool
    {
        return true;
    }
}
