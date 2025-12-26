<?php

namespace AwardWallet\Engine\renfe;

use AwardWallet\Common\OneTimeCode\EmailQuestionAnalyzerInterface;

class QuestionAnalyzer implements EmailQuestionAnalyzerInterface
{
    public static function isOtcQuestion(string $question): bool
    {
        return str_starts_with($question, "Hemos enviado un código de verificación a");
    }

    public static function getHoldsSession(): bool
    {
        return true;
    }
}
