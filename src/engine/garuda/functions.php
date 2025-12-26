<?php

use AwardWallet\Common\Parsing\WrappedProxyClient;
use AwardWallet\Engine\ProxyList;

class TAccountCheckerGaruda extends TAccountChecker
{
    use ProxyList;
    use SeleniumCheckerHelper;

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->UseSelenium();
//        $this->http->SetProxy($this->proxyReCaptcha());
        $resolutions = [
            [1152, 864],
            [1280, 720],
            [1280, 768],
            [1280, 800],
            [1360, 768],
            [1366, 768],
        ];
        $resolution = $resolutions[array_rand($resolutions)];
        $this->setScreenResolution($resolution);

        $this->http->saveScreenshots = true;

        $this->setProxyGoProxies();

        /*
        $this->useFirefox(SeleniumFinderRequest::FIREFOX_84);
        $this->setKeepProfile(true);
        $this->seleniumOptions->fingerprintOptions = ['devices' => ['desktop'], 'operatingSystems' => ['macos']];
        $this->seleniumOptions->addHideSeleniumExtension = false;
        $this->seleniumOptions->userAgent = null;
        */

        $this->useChromePuppeteer();
        $this->seleniumOptions->fingerprintOptions = ['devices' => ['desktop'], 'operatingSystems' => ['macos']];
//        $this->seleniumOptions->addHideSeleniumExtension = false;
        $this->seleniumOptions->userAgent = null;

        $wrappedProxy = $this->services->get(WrappedProxyClient::class);
        $proxy = $wrappedProxy->createPort($this->http->getProxyParams());
//        $this->seleniumOptions->antiCaptchaProxyParams = $this->getCaptchaProxy();
        $this->seleniumOptions->antiCaptchaProxyParams = $proxy;
        $this->seleniumOptions->addAntiCaptchaExtension = true;
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->GetURL("https://www.garuda-indonesia.com/oc/en/login");

        $loginInput = $this->waitForElement(WebDriverBy::xpath('//input[@placeholder="Example: xxx@gmail.com"]'), 10);
        $passwordInput = $this->waitForElement(WebDriverBy::xpath('//input[@placeholder="Password"]'), 0);
        $button = $this->waitForElement(WebDriverBy::xpath('//button[contains(., "Sign In GarudaMiles")]'), 0);
        $this->saveResponse();

        if (!$loginInput || !$passwordInput || !$button) {
            return $this->checkErrors();
        }
        sleep(5);
        $loginInput->sendKeys($this->AccountFields['Login']);
        $passwordInput->sendKeys($this->AccountFields['Pass']);

        $this->waitFor(function () {
            $this->logger->warning("Solving is in process...");
            sleep(3);
            $this->saveResponse();

            return !$this->http->FindSingleNode('//a[contains(text(), "Solving is in process...")]');
        }, 280);

        $this->saveResponse();
        $button = $this->waitForElement(WebDriverBy::xpath('//button[contains(., "Sign In GarudaMiles")]'), 0);
        $button->click();

        /*
        if ($this->clickCaptchaCheckboxByMouseV2(
            $this,
            '//iframe[@title="reCAPTCHA"]/..', 35, 35)
        ) {
            sleep(5);
            $this->saveResponse();
            $button = $this->waitForElement(WebDriverBy::xpath('//button[contains(., "Sign In GarudaMiles")]'), 0);
            $button->click();

            // show image select
            /*if ($iframe = $this->waitForElement(WebDriverBy::xpath('//iframe[contains(@title,"recaptcha challenge")]'), 0)) {
                $this->driver->switchTo()->frame($iframe);
                if ($this->waitForElement(WebDriverBy::xpath('//div[@id="rc-imageselect"]'), 5)) {
                    $this->saveResponse();
                    $this->driver->switchTo()->defaultContent();
                    $this->saveResponse();
                    $this->waitForElement(WebDriverBy::xpath('//div[@class="g-recaptcha-bubble-arrow"]/preceding-sibling::div'),
                        0)
                        ->click();
                    $captcha = $this->parseReCaptcha('6LeF8ognAAAAAP7zPGPk49m30AYApQqWuIKdK7w4');

                    if ($captcha !== false) {
                        $this->driver->executeScript("document.getElementsByName('g-recaptcha-response').value = '{$captcha}';");
                    }
                    $this->saveResponse();
                } else {
                    $this->driver->switchTo()->defaultContent();
                }
            }*/
        /*
        }
        */

        return true;
    }

    protected function parseReCaptcha($key)
    {
        $this->logger->notice(__METHOD__);

        if (!$key) {
            return false;
        }

        $postData = [
            "type"         => "RecaptchaV2TaskProxyless",
            "websiteURL"   => $this->http->currentUrl(),
            //"websiteURL"   => "https://www.garuda-indonesia.com/oc/en/login",
            "websiteKey"   => $key,
        ];
        $this->recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_ANTIGATE_API_V2);
        $this->recognizer->RecognizeTimeout = 120;

        return $this->recognizeAntiCaptcha($this->recognizer, $postData);

        $this->recognizer = $this->getCaptchaRecognizer();
        $this->recognizer->RecognizeTimeout = 120;
        $parameters = [
            "pageurl"   => "https://www.garuda-indonesia.com/oc/en/login",
            "version"   => "v2",
            "enterprise"   => true,
        ];

        return $this->recognizeByRuCaptcha($this->recognizer, $key, $parameters);
    }

    public function Login()
    {
        $this->waitForElement(WebDriverBy::xpath('//li[contains(., "Logout")] | //div[contains(@class, "MuiAlert-message") and not(contains(., "Login success"))]'), 25);
        $this->saveResponse();

        if ($this->http->FindSingleNode('//li[contains(., "Logout")]')) {
            return true;
        }

        if ($message = $this->http->FindSingleNode('//div[contains(@class, "MuiAlert-message") and not(contains(., "Login success"))]')) {
            $this->logger->error("[Error]: {$message}");

            if ($message == 'Please verify reCaptcha') {
                throw new CheckRetryNeededException(2, 0, self::CAPTCHA_ERROR_MSG);
            }

            if (strstr($message, "Wrong email/card number or password.")) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            if (strstr($message, "We're sorry, but a system error has occurred. We apologize for any inconvenience")) {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }

            $this->DebugInfo = $message;

            return false;
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        $this->waitForElement(WebDriverBy::xpath('//p[contains(text(), "Number")]/following-sibling::p[normalize-space(.) != ""]'), 25);
        $this->saveResponse();
        // Name
        $this->SetProperty("Name", beautifulName($this->http->FindSingleNode('//button[contains(@class, "edit")]/following-sibling::p')));
        // Card Number
        $this->SetProperty("CardNumber", $this->http->FindSingleNode('//p[contains(text(), "Number")]/following-sibling::p'));
        // Tier
        $this->SetProperty("CurrentTier", $this->http->FindSingleNode('//p[normalize-space(text()) = "Tier"]/following-sibling::p'));
        // Tier Miles
        $this->SetProperty("TierMileage", $this->http->FindSingleNode('//p[contains(text(), "Tier Miles")]/following-sibling::p'));
        // Member Since
        $this->SetProperty("EffectiveDate", $this->http->FindSingleNode('//p[contains(text(), "Member Since")]/following-sibling::p'));
        // Valid Thru
        $this->SetProperty("StatusExpiration", $this->http->FindSingleNode('//p[contains(text(), "Valid Thru")]/following-sibling::p'));
        // Balance - Award Miles
        $this->SetBalance($this->http->FindSingleNode('//p[contains(text(), "Award Miles")]/following-sibling::p'));
    }

    private function checkErrors()
    {
        $this->logger->notice(__METHOD__);

        return false;
    }
}
