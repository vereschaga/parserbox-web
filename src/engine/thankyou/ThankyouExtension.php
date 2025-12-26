<?php

namespace AwardWallet\Engine\thankyou;

use AwardWallet\Engine\citybank\CitybankExtensionUs;
use AwardWallet\ExtensionWorker\AccountOptions;

class ThankyouExtension extends CitybankExtensionUs
{
    public function getStartingUrl(AccountOptions $options): string
    {
        return 'https://www.citi.com/citi-partner/thankyou/login?userType=tyLogin&locale=en_US&TYNewUser=false&TYForgotUUID=false&TYMigration=&SAMLPostURL=https:%2F%2Fwww.thankyou.com%2F%2Fgateway2.htm&ErrorCode=&TYPostURL=https:%2F%2Fwww.thankyou.com%2F%2FtyLoginGateway.htm&cmp=null';
    }

}

