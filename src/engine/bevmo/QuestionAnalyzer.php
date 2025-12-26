<?php

namespace AwardWallet\Engine\bevmo;

use AwardWallet\Common\OneTimeCode\EmailQuestionAnalyzerInterface;

class QuestionAnalyzer implements EmailQuestionAnalyzerInterface
{
    public static function isOtcQuestion(string $question): bool
    {
        return
            strpos($question, "To keep your account secure we need to make sure it's really you before you can login.") === 0
        ;
    }

    public static function getHoldsSession(): bool
    {
        return false;
    }
}
