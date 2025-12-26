<?php

namespace AwardWallet\ExtensionWorker;

class LoginWithIdResult
{

    public LoginResult $loginResult;
    public Tab $tab;

    public function __construct(LoginResult $loginResult, Tab $tab)
    {

        $this->loginResult = $loginResult;
        $this->tab = $tab;
    }

}