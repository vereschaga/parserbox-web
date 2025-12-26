<?php

namespace AwardWallet\Engine\jcrew;

use AwardWallet\Common\OneTimeCode\EmailQuestionAnalyzerInterface;

class QuestionAnalyzer implements EmailQuestionAnalyzerInterface
{
    public static function isOtcQuestion(string $question): bool
    {
        return str_starts_with($question, "Your unique ID code was sent to");
    }

    public static function getHoldsSession(): bool
    {
        return true;
    }
}
