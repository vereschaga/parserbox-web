<?php

namespace AwardWallet\ExtensionWorker;

interface ContinueLoginInterface
{
    /**
     * will be called when browser session was restored, there is already active window
     * typically when use has been asked security question on the previous step
     */
    public function continueLogin(Tab $tab, Credentials $credentials): LoginResult;

}