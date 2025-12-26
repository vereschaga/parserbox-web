<?php

namespace AwardWallet\Engine\testprovider\Selenium;

use AwardWallet\Engine\testprovider\Success;

class UserAgent extends Success
{
    use \SeleniumCheckerHelper;

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->http->setUserAgent("Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_3) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/80.0.3987.132 Safari/537.36 (AwardWallet Service. contact us at https://awardwallet.com/contact)");
        $this->UseSelenium();
    }

    public function Parse()
    {
        $this->http->GetURL("http://www.xhaus.com/headers");
        $this->SetBalance(100);
    }
}
