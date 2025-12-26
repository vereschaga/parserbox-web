<?php

namespace AwardWallet\Engine\aeroflot;

use AwardWallet\Common\Parsing\Web\Proxy\Provider\GoProxiesRequest;
use AwardWallet\Common\Parsing\Web\Proxy\Provider\NetNutRequest;
use AwardWallet\ExtensionWorker\AbstractServerConfig;
use AwardWallet\ExtensionWorker\AccountOptions;

class AeroflotExtensionServerConfig extends AbstractServerConfig
{
    public function configureServerCheck(?AccountOptions $accountOptions, \SeleniumFinderRequest $seleniumRequest, \SeleniumOptions $seleniumOptions): bool
    {
        $seleniumOptions->setProxy($this->proxyManager->get(new GoProxiesRequest('ru')));
        $seleniumOptions->setResolution([1920, 1080]);
        return true;
    }
}
