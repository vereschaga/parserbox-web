<?php

namespace AwardWallet\Engine\qmiles;

use AwardWallet\Common\OneTimeCode\EmailQuestionAnalyzerInterface;

class QuestionAnalyzer implements EmailQuestionAnalyzerInterface
{
    public static function isOtcQuestion(string $question): bool
    {
        return str_starts_with($question, "Please enter the OTP received in your registered email")
            || str_starts_with($question, "Thanks for registering with Qatar Airways Privilege Club. You’ll soon receive an email from us to activate your account. Please check your spam folder if it does not arrive in your inbox");
    }

    public static function getHoldsSession(): bool
    {
        return true;
    }
}
