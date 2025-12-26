<?php

namespace AwardWallet\Engine\golair;

use AwardWallet\Common\OneTimeCode\EmailQuestionAnalyzerInterface;

class QuestionAnalyzer implements EmailQuestionAnalyzerInterface
{
    public static function isOtcQuestion(string $question): bool
    {
        return
            strpos($question, "Enviamos para o e-mail da sua conta Smiles.") === 0
        ;
    }

    public static function getHoldsSession(): bool
    {
        return false;
    }
}
