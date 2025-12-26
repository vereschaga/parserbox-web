<?php

namespace AwardWallet\Common\Parsing\Web\Captcha;

class CaptchaProviderResult
{

    public const STATUS_SUCCESS = 'success';
    public const STATUS_ZERO_BALANCE = 'zero_balance';
    public const STATUS_ACCOUNT_SUSPENDED = 'account_suspended';
    public const STATUS_UNKNOWN_KEY = 'unknown_key';
    public const STATUS_UNSOLVED = 'unsolved';
    public const STATUS_RETRY = 'retry';

    private string $status;
    private ?string $solvedCode;

    public function __construct(string $status, ?string $solvedCode = null)
    {
        $this->status = $status;
        $this->solvedCode = $solvedCode;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getSolvedCode(): ?string
    {
        return $this->solvedCode;
    }

}