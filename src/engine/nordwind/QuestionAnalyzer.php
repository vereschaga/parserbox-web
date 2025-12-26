<?php

namespace AwardWallet\Engine\nordwind;

use AwardWallet\Common\OneTimeCode\EmailQuestionAnalyzerInterface;

class QuestionAnalyzer implements EmailQuestionAnalyzerInterface
{
    public static function isOtcQuestion(string $question): bool
    {
        return str_starts_with($question, "Мы отправили код подтверждения на ");
    }

    public static function getHoldsSession(): bool
    {
        return true;
    }
}
