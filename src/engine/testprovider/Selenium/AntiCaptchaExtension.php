<?php

namespace AwardWallet\Engine\testprovider\Selenium;

use AwardWallet\Engine\ProxyList;
use AwardWallet\Engine\testprovider\Success;

class AntiCaptchaExtension extends Success
{
    use ProxyList;
    use \SeleniumCheckerHelper;

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->http->SetProxy($this->proxyReCaptcha());
        $this->seleniumOptions->addHideSeleniumExtension = false;
        $this->seleniumOptions->addAntiCaptchaExtension = true;
        $this->seleniumOptions->antiCaptchaProxyParams = $this->getCaptchaProxy();
        $this->UseSelenium();
    }

    public function Parse()
    {
        $this->http->GetURL("https://www.bahn.de/p/view/meinebahn/login.shtml");
        $this->SetBalance(10);
    }
}
