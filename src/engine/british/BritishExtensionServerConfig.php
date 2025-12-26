<?php

namespace AwardWallet\Engine\british;

use AwardWallet\Common\Parsing\Web\Proxy\Provider\GoProxiesRequest;
use AwardWallet\ExtensionWorker\AbstractServerConfig;
use AwardWallet\ExtensionWorker\AccountOptions;
use SeleniumFinderRequest;
use SeleniumOptions;

class BritishExtensionServerConfig extends AbstractServerConfig
{

    public function configureServerCheck(?AccountOptions $accountOptions, SeleniumFinderRequest $seleniumRequest, SeleniumOptions $seleniumOptions): bool
    {
        /*
        if (in_array($accountOptions->login ,['19185334', 'veresch80@yahoo.com', 'Ashgardyn@gmail.com'])) {
            $seleniumOptions->setProxy($this->proxyManager->get(new GoProxiesRequest(GoProxiesRequest::COUNTRY_US)));

            return true;
        }
        */

        return false;
    }
}
