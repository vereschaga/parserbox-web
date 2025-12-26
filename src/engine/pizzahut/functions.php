<?php

use AwardWallet\Engine\ProxyList;

class TAccountCheckerPizzahut extends TAccountChecker
{
    use ProxyList;
    use SeleniumCheckerHelper;
    /**
     * @var CaptchaRecognizer
     */
    private $recognizer;

    public function InitBrowser(): void
    {
        parent::InitBrowser();
        $this->UseSelenium();
        $this->useFirefox(SeleniumFinderRequest::FIREFOX_84);
        $this->setKeepProfile(true);
        $this->setProxyBrightData();
        $this->setScreenResolution([rand(1090, 1920), rand(700, 1080)]);
        $this->useCache();
        $this->http->saveScreenshots = true;
    }

    public function IsLoggedIn(): bool
    {
        return false;
    }

    public function LoadLoginForm(): bool
    {
        $this->http->removeCookies();
        $this->http->GetURL('https://www.pizzahut.com/');
        $btn = $this->waitForElement(WebDriverBy::xpath('//header//a[@title="Sign In"]'), 5);

        if (!$btn) {
            return false;
        }
        $btn->click();
        $frame = $this->waitForElement(WebDriverBy::xpath('//iframe[@title="Sign In"]'), 0);

        if (!$frame) {
            return false;
        }
        $this->driver->switchTo()->frame($frame);
        $login = $this->waitForElement(WebDriverBy::id('uname'), 10);
        $pwd = $this->waitForElement(WebDriverBy::id('pwd'), 0);
        $remMe = $this->waitForElement(WebDriverBy::id('remember_me'), 0);
        $btn = $this->waitForElement(WebDriverBy::id('but_submit'), 0);
        $siteKeyEl = $this->waitForElement(WebDriverBy::xpath('//div[@class="g-recaptcha"]'), 0);
        $this->saveResponse();

        if (!$login || !$pwd || !$remMe || !$btn || !$siteKeyEl) {
            return false;
        }
        $captcha = $this->parseCaptcha($siteKeyEl);

        if ($captcha === false) {
            return false;
        }
        $this->driver->executeScript("document.querySelector('iframe[title=reCAPTCHA]').remove(); document.getElementById('g-recaptcha-response').innerText='$captcha';");
        $login->sendKeys($this->AccountFields['Login']);
        $pwd->sendKeys($this->AccountFields['Pass']);
        $remMe->click();
        $btn->click();

        return true;
    }

    public function Login(): bool
    {
        $this->saveResponse();

        return false;
    }

    public function Parse(): void
    {
        // Balance -
        // $this->SetBalance($this->http->FindSingleNode('//li[contains(@id, "balance")]'));
        // Name
        // $this->SetProperty('Name', beautifulName($this->http->FindSingleNode('//li[contains(@id, "name")]')));
        // Number
        // $this->SetProperty('Number', $this->http->FindSingleNode('//li[contains(@id, "number")]'));
    }

    private function parseCaptcha($siteKeyEl)
    {
        $this->logger->notice(__METHOD__);
        $siteKey = $siteKeyEl->getAttribute('data-sitekey');

        if (empty($siteKey)) {
            return false;
        }
        $this->recognizer = $this->getCaptchaRecognizer();
        $this->recognizer->RecognizeTimeout = 120;
        $parameters = [
            "pageurl" => 'https://www.pizzahut.com/',
            "proxy"   => $this->http->GetProxy(),
        ];

        return $this->recognizeByRuCaptcha($this->recognizer, $siteKey, $parameters);
    }

    private function loginSuccessful(): bool
    {
        $this->logger->notice(__METHOD__);

        // if ($this->waitForElement()) {
        //     return true;
        // }

        return false;
    }
}
