<?php

use AwardWallet\Engine\ProxyList;

class TAccountCheckerMoosejaw extends TAccountChecker
{
    use ProxyList;

    private const REWARDS_PAGE_URL = 'https://www.moosejaw.com/';
    /**
     * @var CaptchaRecognizer
     */
    private $recognizer;

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->RetryCount = 0;
        $this->http->SetProxy($this->proxyReCaptcha());
        $this->http->setHttp2(true);
    }

    public function IsLoggedIn()
    {
        $this->http->RetryCount = 0;
        $this->http->GetURL(self::REWARDS_PAGE_URL, [], 20);
        $this->http->RetryCount = 2;

        if ($this->loginSuccessful()) {
            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->GetURL('https://www.moosejaw.com/content/AccountSummary?catalogId=10000001&myAcctMain=1&langId=-1&storeId=10208');

        if (!$this->http->ParseForm('Logon')) {
            return $this->checkErrors();
        }

        $this->http->SetInputValue('logonId', $this->AccountFields['Login']);
        $this->http->SetInputValue('logonPassword', $this->AccountFields['Pass']);
        $this->http->SetInputValue('rememberMe', "on");
        $this->http->SetInputValue('sessionUserAgent', $this->http->getDefaultHeader("User-Agent"));

        return true;
    }

    public function Login()
    {
        if (!$this->http->PostForm()) {
            return $this->checkErrors();
        }

        if ($this->loginSuccessful()) {
            $this->captchaReporting($this->recognizer, true);

            return true;
        }

        /* wtf ???
        //verify for captcha
        if (
            $this->http->FindSingleNode('//h2[contains(text(), "so sorry, but our Fancy Site Protection System (FSPS)")]')
            && !$this->parseReCaptcha()
        ) {
            return $this->checkErrors();
        }
        */

        if ($message = $this->http->FindSingleNode('//img[@src="/moosejaw/Moosejaw/images/static/MJ-Throttle-Block-Page.jpg"]')) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        $this->http->GetURL('https://www.moosejaw.com');
        //Find link & to to order-status page
        $this->http->GetURL($this->http->FindSingleNode('//div[contains(@class, "account-scene customer-registered")]/a[contains(., "Order Status")]/@href'));
        // Name
        $this->SetProperty('Name', $this->http->FindSingleNode('//div[contains(@class, "row")]/div[contains(@class, "right-side-border-box wishlist")]'));
        //div[contains(@class, "right-side-border-box wishlist")]/p[contains(@class, "account-descr")]/b
        // Balance
        $this->SetBalance($this->http->FindSingleNode('//p[contains(.,"You have")]/b/text()'));
    }

    public function parseReCaptcha()
    {
        $key = '6Ldg4BgaAAAAAACxPHxTI7VHH-DKc6wyY8jf8Unf'; //$this->http->FindSingleNode('//div[contains(@class, "g-recaptcha")]/@data-sitekey');
        $this->logger->debug("data-sitekey: {$key}");

        if (!$key) {
            return false;
        }
        $this->recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_RUCAPTCHA);
        $this->recognizer->RecognizeTimeout = 120;
        $parameters = [
            "pageurl" => $this->http->currentUrl(),
            "proxy"   => $this->http->GetProxy(),
        ];

        return $this->recognizeByRuCaptcha($this->recognizer, $key, $parameters);
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);

        if ($this->http->FindNodes('//div[contains(@id, "AccountContainer")]/div[contains(@class, "account-scene customer-registered")]/div[contains(@class, "top-line")]/span[contains(@id, "DesktopHeaderCustomerName") and not(contains(., "Max"))]')) {
            return true;
        }

        return false;
    }

    private function checkErrors()
    {
        $this->logger->notice(__METHOD__);

        return false;
    }

    private function seleniumAuth()
    {
        $this->logger->notice(__METHOD__);
        $selenium = clone $this;
        $this->http->brotherBrowser($selenium->http);

        try {
            $this->logger->notice("Running Selenium...");
            $selenium->UseSelenium();
            $selenium->useChromium();
//            $selenium->setKeepProfile(true);
            $selenium->usePacFile(false);
//            $selenium->setProxyBrightData();
//            $selenium->http->setUserAgent(\HttpBrowser::FIREFOX_USER_AGENT);
            $selenium->http->start();
            $selenium->Start();
            $selenium->http->GetURL("https://www.moosejaw.com/content/AccountSummary?catalogId=10000001&myAcctMain=1&langId=-1&storeId=10208");

            $loginInput = $selenium->waitForElement(WebDriverBy::xpath('//input[@id = "WC_AccountDisplay_FormInput_logonId_In_Logon_1"]'), 7);
            $passwordInput = $selenium->waitForElement(WebDriverBy::xpath('//input[@id = "WC_AccountDisplay_FormInput_logonPassword_In_Logon_1"]'), 0);
            $button = $selenium->waitForElement(WebDriverBy::xpath('//a[@id = "WC_AccountDisplay_links_2"]'), 0);
            $this->savePageToLogs($selenium);

            if (!$loginInput || !$passwordInput || !$button) {
                return $this->checkErrors();
            }

            $loginInput->sendKeys($this->AccountFields['Login']);
            $passwordInput->sendKeys($this->AccountFields['Pass']);

            $button->click();

            $selenium->waitForElement(WebDriverBy::xpath('//h2[contains(text(), "My Account Summary")]'), 10);
            $this->savePageToLogs($selenium);

            $cookies = $selenium->driver->manage()->getCookies();

            foreach ($cookies as $cookie) {
                $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
            }
        } catch (StaleElementReferenceException $e) {
            $this->logger->error("StaleElementReferenceException exception: " . $e->getMessage());
        } finally {
            // close Selenium browser
            $selenium->http->cleanup();
        }

        return false;
    }
}
