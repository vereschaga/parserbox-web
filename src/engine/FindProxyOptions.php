<?php

namespace AwardWallet\Engine;

class FindProxyOptions
{
    /** @var callable - function(?string, ?int $httpCode, ?int $curlErrno) : bool */
    public $isValid;
    public $timeout = 5;
    public $userAgent;
}
