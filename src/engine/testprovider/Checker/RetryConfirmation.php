<?php

namespace AwardWallet\Engine\testprovider\Checker;

use AwardWallet\Engine\testprovider\Success;

class RetryConfirmation extends Success
{
    public function CheckConfirmationNumberInternal($arFields, &$it)
    {
        if ($arFields['LastName'] === "-u") {
            throw new \CheckRetryNeededException();
        }

        throw new \CheckRetryNeededException(2, 20, 'ACCOUNT_LOCKOUT', ACCOUNT_LOCKOUT);
    }
}
