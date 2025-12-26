<?php

namespace AwardWallet\Engine\petperks;

use AwardWallet\Common\Parsing\Web\Proxy\Provider\GoProxiesRequest;
use AwardWallet\ExtensionWorker\AbstractServerConfig;
use AwardWallet\ExtensionWorker\AccountOptions;

class PetperksExtensionServerConfig extends AbstractServerConfig
{
    public function configureServerCheck(?AccountOptions $accountOptions, \SeleniumFinderRequest $seleniumRequest, \SeleniumOptions $seleniumOptions): bool
    {
        $seleniumOptions->setProxy($this->proxyManager->get(new GoProxiesRequest(GoProxiesRequest::COUNTRY_US)));

        return false;
    }
}
