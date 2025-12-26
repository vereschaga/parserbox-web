<?php

namespace AwardWallet\Engine\usbank;

use AwardWallet\Common\OneTimeCode\EmailQuestionAnalyzerInterface;

class QuestionAnalyzer implements EmailQuestionAnalyzerInterface
{
    public static function isOtcQuestion(string $question): bool
    {
        return
            str_starts_with($question, "We sent a six-digit code to")
            || str_starts_with($question, "Te enviamos un código de seis dígitos a tu dirección de correo electrónico")
        ;
    }

    public static function getHoldsSession(): bool
    {
        return true;
    }
}
