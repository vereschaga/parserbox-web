<?php

namespace AwardWallet\Engine\testprovider\Checker;

use AwardWallet\Engine\testprovider\Success;

class Retry extends Success
{
    public const QUESTION = "One Time Code";
    public const ANSWER = 'error_code';
    public const STATE_KEY = 'testStateItemKey';
    public const STATE_VALUE = 'testStateItemValue';

    public function Login()
    {
        if ($this->AccountFields['Pass'] === "-invalid-answer-with-state") {
            $question = self::QUESTION;

            if ($this->Answers[$question] === self::ANSWER) {
                unset($this->Answers[$question]);
            }

            $this->setCheckerStateItem(self::STATE_KEY, self::STATE_VALUE);
        }

        if ($this->AccountFields['Pass'] === "-load-retries-state") {
            if (isset($this->Answers[self::QUESTION])) {
                return false;
            }
            $state = $this->getCheckerState();

            if (!isset($state[self::STATE_KEY]) || $state[self::STATE_KEY] !== self::STATE_VALUE) {
                return false;
            }
        }

        return true;
    }

    public function Parse()
    {
        if ($this->AccountFields['Pass'] === "-load-retries-state") {
            $this->SetBalance(10);

            return;
        }

        if ($this->AccountFields['Login2'] === "-ub") {
            $this->SetBalance(10);

            throw new \CheckRetryNeededException(5);
        }

        if ($this->AccountFields['Login2'] === "-ubl") {
            $this->SetBalance(10);

            throw new \CheckRetryNeededException();
        }

        if ($this->AccountFields['Login2'] === "-u") {
            throw new \CheckRetryNeededException();
        }

        throw new \CheckRetryNeededException(2, 20, 'ACCOUNT_LOCKOUT', ACCOUNT_LOCKOUT);
    }
}
