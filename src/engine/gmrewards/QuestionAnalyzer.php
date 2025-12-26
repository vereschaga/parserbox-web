<?php

namespace AwardWallet\Engine\gmrewards;

use AwardWallet\Common\OneTimeCode\EmailQuestionAnalyzerInterface;

class QuestionAnalyzer implements EmailQuestionAnalyzerInterface
{
    public static function isOtcQuestion(string $question): bool
    {
        return strpos($question, "In order to validate your Account, enter the security code") === 0 && strstr($question, "@") != false;
    }

    public static function getHoldsSession(): bool
    {
        return false;
    }
}
