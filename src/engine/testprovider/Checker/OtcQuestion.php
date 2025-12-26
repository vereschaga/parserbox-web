<?php

namespace AwardWallet\Engine\testprovider\Checker;

use AwardWallet\Engine\testprovider\Success;
use AwardWallet\Engine\testprovider\QuestionAnalyzer;
use CheckException;

class OtcQuestion extends Success
{
    private const QUESTION_ANSWER = 12345;

    public function Login()
    {
        $this->AskQuestion($this->getQuestion());

        return false;
    }

    public function ProcessStep($step)
    {
        if ($this->Question != $this->getQuestion()) {
            throw new CheckException('Unknown question', ACCOUNT_PROVIDER_ERROR);
        }

        if (self::QUESTION_ANSWER != $this->Answers[$this->getQuestion()]) {
            $this->AskQuestion($this->getQuestion(), 'Wrong answer. Shoud be "' . self::QUESTION_ANSWER . '"');

            return false;
        }

        return true;
    }

    public function Parse()
    {
        $this->SetBalance(random_int(1, 1000));

        return true;
    }

    private function getQuestion(): string
    {
        return QuestionAnalyzer::getEmailOtcQuestion();
    }
}
