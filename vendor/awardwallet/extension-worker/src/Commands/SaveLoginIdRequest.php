<?php

namespace AwardWallet\ExtensionWorker\Commands;

class SaveLoginIdRequest
{

    public string $loginId;
    public string $login;

    public function __construct(string $loginId, string $login)
    {
        $this->loginId = $loginId;
        $this->login = $login;
    }

}