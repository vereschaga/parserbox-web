<?php

namespace AwardWallet\Engine\testprovider\Proxy;

use AwardWallet\Common\Parsing\LuminatiProxyManager\Port;
use AwardWallet\Engine\ProxyList;
use AwardWallet\Engine\testprovider\Success;

class LuminatiProxyManager extends Success
{

    use ProxyList, \SeleniumCheckerHelper;

    public function InitBrowser()
    {
        parent::InitBrowser();

        //$this->http->SetProxy($this->proxyReCaptcha(), false);
        $this->setProxyGoProxies();
//        $this->setProxyNetNut();
        $this->UseSelenium();
        $this->useChromePuppeteer();
//        $this->seleniumRequest->setOs(\SeleniumFinderRequest::OS_MAC);
//        $this->seleniumOptions->addHideSeleniumExtension = false;
//        $this->seleniumOptions->userAgent = null;
        $this->usePacFile(false);
        $this->KeepState = false;

        # should be called after setProxyXxx method
        $this->setLpmProxy((new Port)
            ->setExternalProxy([$this->http->getProxyUrl()])
            ->cacheUrlByRegexp('\.(js|mp4|jpeg|jpg|webp|svg|png|css|woff2)$')
        );
    }

    public function Parse()
    {
        $this->http->saveScreenshots = true;
        $this->http->GetURL('https://ipinfo.io');
        $this->http->GetURL('https://www.bankofamerica.com');
        $har = $this->getHarFromLpm(preg_quote('.json'));
        $this->logger->info("recorder request: " . $har->log->entries[0]->response->content->text);
        $this->SetBalance(1);
    }

}