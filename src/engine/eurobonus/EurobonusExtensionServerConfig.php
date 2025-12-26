<?php

namespace AwardWallet\Engine\eurobonus;

use AwardWallet\Common\Parsing\Web\Proxy\Provider\GoProxiesRequest;
use AwardWallet\Common\Parsing\Web\Proxy\Provider\NetNutRequest;
use AwardWallet\ExtensionWorker\AbstractServerConfig;
use AwardWallet\ExtensionWorker\AccountOptions;

class EurobonusExtensionServerConfig extends AbstractServerConfig
{
    public function configureServerCheck(?AccountOptions $accountOptions, \SeleniumFinderRequest $seleniumRequest, \SeleniumOptions $seleniumOptions): bool
    {
        $seleniumOptions->setProxy($this->proxyManager->get(new NetNutRequest('us')));
        /*
        $seleniumOptions->setProxy($this->proxyManager->get(new GoProxiesRequest('br')));
        */

        return true;
    }
}
