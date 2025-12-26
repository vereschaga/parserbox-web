<?php

use AwardWallet\Engine\ProxyList;

class TAccountCheckerZoompanelSelenium extends TAccountChecker
{
    use SeleniumCheckerHelper;
    use ProxyList;

    private $host = 'www.oneopinion.com';

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->UseSelenium();
        //$this->disableImages();
        $this->useChromium();
//        $this->useCache();// this broke auth
        //$this->http->SetProxy($this->proxyDOP());
        $this->http->SetProxy($this->proxyReCaptcha());
        $this->restoreHost();
        $this->keepCookies(false);
    }

    public function IsLoggedIn()
    {
        $this->http->GetURL("https://{$this->host}/dashboard");
        $logout = $this->waitForElement(WebDriverBy::xpath("//span[contains(text(), 'Sign Out')]"), 3);
        $this->saveResponse();

        if ($logout) {
            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        // Please enter a valid email address.
        if (filter_var($this->AccountFields['Login'], FILTER_VALIDATE_EMAIL) === false) {
            throw new CheckException("Please enter a valid email address.", ACCOUNT_INVALID_PASSWORD);
        }

        $this->http->removeCookies();

        $this->http->GetURL("https://{$this->host}/dashboard");

        $mover = new MouseMover($this->driver);
        $mover->logger = $this->logger;
        $mover->duration = rand(100000, 120000);
        $mover->steps = rand(50, 70);

        if ($login = $this->waitForElement(WebDriverBy::id('login-btn'), 3)) {
            $login->click();
        }

        $email = $this->waitForElement(WebDriverBy::id('login-email'), 5);
        $button = $this->waitForElement(WebDriverBy::xpath("//form[@id='login-step-one']//button[@type='submit']"), 0);

        if ($email && $button) {
            $mover->sendKeys($email, $this->AccountFields['Login'], rand(2, 5));
            $mover->moveToElement($email);
            $mover->click();
            usleep(rand(200000, 500000));
            $button->click();
            usleep(rand(200000, 500000));
            $pass = $this->waitForElement(WebDriverBy::id('login-password'), 5);
            $mover->sendKeys($pass, $this->AccountFields['Pass'], rand(2, 5));
            $mover->moveToElement($pass);
            $mover->click();
            //$pass->clear();
            $button = $this->waitForElement(WebDriverBy::xpath("//form[@id='login-step-two']//button[@type='submit']"), 0);
            usleep(rand(200000, 500000));

            $captcha = $this->parseCaptcha();

            if ($captcha === false) {
                return false;
            }
            $this->logger->notice("Remove iframe");
            $this->driver->executeScript("$('div.g-recaptcha iframe').remove();");
            $this->driver->executeScript("$('#g-recaptcha-response').val(\"" . $captcha . "\");");

            if ($rememberMe = $this->waitForElement(WebDriverBy::xpath('//input[@id = "remember-me"]'), 0)) {
                $rememberMe->click();
            }

            $button->click();
        }

        // The user for the email you entered could not be found.
        if ($message = $this->waitForElement(WebDriverBy::xpath("//div[contains(text(), 'The user for the email you entered could not be found.')]"), 3)) {
            throw new CheckException($message->getText(), ACCOUNT_INVALID_PASSWORD);
        }

        return true;
    }

    public function checkErrors()
    {
        $this->saveResponse();
        // maintenance
        if ($message = $this->http->FindSingleNode("//strong[contains(text(), 'We are currently performing scheduled maintenance on our site.')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
        $this->logger->notice(__METHOD__);
        $sleep = 20;
        $startTime = time();

        while ((time() - $startTime) < $sleep) {
            $this->logger->debug("(time() - \$startTime) = " . (time() - $startTime) . " < {$sleep}");
            // login successful
            if ($this->waitForElement(WebDriverBy::xpath("//a[contains(text(), 'Sign Out')]"), 0)) {
                return true;
            }
            // The password you entered is incorrect.
            if ($message = $this->waitForElement(WebDriverBy::xpath("//div[contains(text(), 'The password you entered is incorrect.')]"), 0)) {
                $this->saveResponse();

                throw new CheckException($message->getText(), ACCOUNT_INVALID_PASSWORD);
            }
            // Oops, an error occurred. Please try at a later time.
            if ($message = $this->waitForElement(WebDriverBy::xpath("//div[contains(text(), 'Oops, an error occurred. Please try at a later time.')]"), 0)) {
                $this->saveResponse();

                throw new CheckException($message->getText(), ACCOUNT_PROVIDER_ERROR);
            }
            /*
             * Time to update your password. We recently sent you an email with a link to reset your password.
             * If you can't find the email, please click here to resend the email.
             * You'll need to reset your password to continue taking surveys.
             */
            if ($message = $this->waitForElement(WebDriverBy::xpath("//div[contains(text(), 'Time to update your password. We recently sent you an email with a link to reset your password.')]"), 0)) {
                $this->saveResponse();

                throw new CheckException("Time to update your password. We recently sent you an email with a link to reset your password. You'll need to reset your password to continue taking surveys.", ACCOUNT_PROVIDER_ERROR);
            }

            // set-up 2-factor
            $skip = $this->waitForElement(WebDriverBy::xpath("//a[contains(text(), 'Skip this for now')]"), 7);
            $this->saveResponse();

            if ($skip) {
                $skip->click();
                sleep(5);
            }

            sleep(1);
            $this->saveResponse();
        }

        return false;
    }

    public function Parse()
    {
        $this->logger->debug("[Current URL]: {$this->http->currentUrl()}");
        // Name
        $this->SetProperty("Name", beautifulName($this->http->FindSingleNode("//p[@class = 'user-info__name']")));
        // Member since
        $this->SetProperty("MemberSince", $this->http->FindSingleNode("//p[contains(text(), 'Member since')]", null, false, '/\s+(\d{4})/'));
        // Lifetime Earnings
        $this->SetProperty("LifetimeEarnings", $this->http->FindSingleNode("//p[contains(text(), 'Lifetime Earnings')]", null, false, '/\s+([\d,.]+)/'));

        // Balance - Current Balance
        $this->SetBalance($this->http->FindSingleNode("//h4[normalize-space(text())='Points Balance']/preceding-sibling::h2", null, false, self::BALANCE_REGEXP_EXTENDED));
        // Cash Value
        $this->SetProperty("CashValue", $this->http->FindSingleNode("//h4[normalize-space(text())='Cash Value']/preceding-sibling::h2", null, false, '/.[\d,.]+/'));
        // Points needed to cash out
        $this->SetProperty("NeededToCashOut", $this->http->FindSingleNode("//h4[normalize-space(text())='Points needed to cash out']/preceding-sibling::h2", null, false, self::BALANCE_REGEXP_EXTENDED));
    }

    protected function parseCaptcha()
    {
        $this->logger->notice(__METHOD__);
        $key = $this->http->FindSingleNode("//div[@id='recaptcha-login']/@data-sitekey");
        $this->logger->debug("data-sitekey: {$key}");

        if (!$key) {
            return false;
        }
        $recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_RUCAPTCHA);
        $recognizer->RecognizeTimeout = 120;
        $parameters = [
            "pageurl" => $this->http->currentUrl(),
            "proxy"   => $this->http->GetProxy(),
        ];
        $captcha = $this->recognizeByRuCaptcha($recognizer, $key, $parameters);

        return $captcha;
    }

    private function restoreHost()
    {
        $this->logger->notice(__METHOD__);

        if (!empty($this->AccountFields['Login2'])) {
            if ($this->AccountFields['Login2'] == 'UK') {
                $this->host = 'www.oneopinion.co.uk';
            }
            $this->logger->notice("Set host: {$this->host}");
        }// if (!empty($this->AccountFields['Login2']))
        elseif (isset($this->State['Host'])) {
            $this->host = $this->State['Host'];
            $this->logger->notice("Restore host from state: {$this->host}");
        }// if (isset($this->State['Host']))
        else {
            $this->logger->notice("Use default host: {$this->host}");
        }
    }

    private function postLogin()
    {
        $this->logger->notice(__METHOD__);
        $this->logger->notice('entering login...');
        $visitToken = $this->http->FindPreg("/var visitToken = \"([^\"]+)/");
        $loginField = $this->waitForElement(WebDriverBy::xpath('//input[@id = "modlgn_username"]'), 15);
        $loginButton = $this->waitForElement(WebDriverBy::xpath('//input[@value = "Next"]'), 0);

        if (!$loginField || !$loginButton) {
            $this->logger->error('something went wrong');

            return $this->checkErrors();
        }
        $loginField->sendKeys($this->AccountFields["Login"]);
        $loginButton->click();

        return $visitToken;
    }
}
