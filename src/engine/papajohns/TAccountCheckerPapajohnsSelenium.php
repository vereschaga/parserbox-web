<?php

class TAccountCheckerPapajohnsSelenium extends TAccountChecker
{
    use SeleniumCheckerHelper;

    private $rewardsPage = 'http://www.papajohns.co.uk/my-papa-rewards.aspx';

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->InitSeleniumBrowser();
        $this->disableImages();
        $this->useFirefox();
        $this->keepCookies(true);
        $this->KeepState = true;
        $this->http->saveScreenshots = true;
    }

    public function IsLoggedIn()
    {
        switch ($this->AccountFields['Login2']) {
            case 'UK':
                $this->http->GetURL($this->rewardsPage);

                if ($this->waitForElement(WebDriverBy::xpath('//div[@id = "ctl00__objHeader_pnlLoggedInUserTitle"]/span/span[contains(text(), "Hi ")]'), 5)) {
                    return true;
                }

                break;
        }

        return false;
    }

    public function hideOverlay()
    {
        $this->logger->notice(__METHOD__);
        $this->driver->executeScript('var c = document.getElementsByClassName("fancybox-overlay"); if (c.length) c[0].style.display = "none";');
        $this->driver->executeScript('var overlay = document.getElementById(\'onetrust-consent-sdk\'); if (overlay) overlay.style.display = "none";');
    }

    public function LoadLoginForm()
    {
//        $this->http->removeCookies();
        $this->http->GetURL("http://www.papajohns.co.uk/");
        $loginFForm = $this->waitForElement(WebDriverBy::id('ctl00__objHeader_lbLoginRegisterItem'), 10);
        $this->saveResponse();

        if (empty($loginFForm)) {
            return $this->checkErrors();
        }
        $this->hideOverlay();

        $loginFForm->click();

        $loginField = $this->waitForElement(WebDriverBy::id('ctl00__objHeader_txtEmail1'), 10);

        if (empty($loginField)) {
            return $this->checkErrors();
        }
        $loginField->sendKeys($this->AccountFields["Login"]);
        $passwordInput = $this->waitForElement(WebDriverBy::id('ctl00__objHeader_txtPassword', 0));

        if (!$passwordInput) {
            return false;
        }
        $passwordInput->sendKeys($this->AccountFields['Pass']);

        if ($this->waitForElement(WebDriverBy::id('captcha_container', 0))) {
            $this->saveResponse();
            // reCaptcha
            $this->logger->notice('reCAPTCHA');
            $captcha = $this->parseReCaptcha();

            if ($captcha === false) {
                return false;
            }
            $this->driver->executeScript("document.getElementById('g-recaptcha-response').value='" . $captcha . "';");
        }

        $loginButton = $this->waitForElement(WebDriverBy::id('ctl00__objHeader_lbSignIn', 0));

        if (!$loginButton) {
            return false;
        }
        $loginButton->click();

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);
        /**
         * We're making things better.
         *
         * Sorry for the inconvenience but we're in the process
         * of making some updates to our website at the moment.
         *
         * We'll be back up and running very soon.
         */
        if ($message = $this->http->FindSingleNode('//p[contains(text(), "Sorry for the inconvenience but we\'re in the process")]')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
        $logout = $this->waitForElement(WebDriverBy::xpath('//div[@id = "ctl00__objHeader_pnlLoggedInUserTitle"]/span/span[contains(text(), "Hi ")]'), 7);
        $this->saveResponse();
        // Access is allowed
        if ($logout) {
            return true;
        }

        if ($message = $this->waitForElement(WebDriverBy::id('ctl00__objHeader_pnlLoginError'), 0)) {
            if ($this->http->FindPreg('/You must complete the captcha/i', false, $message->getText())) {
                throw new CheckRetryNeededException(3, 5, self::CAPTCHA_ERROR_MSG);
            }

            throw new CheckException($message->getText(), ACCOUNT_INVALID_PASSWORD);
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        if ($this->http->currentUrl() != $this->rewardsPage) {
            $this->http->GetURL($this->rewardsPage);
        }
        $loginFForm = $this->waitForElement(WebDriverBy::id('ctl00__objHeader_lbLoginRegisterItem'), 5);
        $this->hideOverlay();

        if (empty($loginFForm)) {
            return;
        }
        $loginFForm->click();
        $this->waitForElement(WebDriverBy::xpath('//span[@class = "noPoints" and not(@style="display: none;")]/span'), 10);
        $this->saveResponse();
        // Name
        $this->SetProperty("Name", beautifulName($this->http->FindSingleNode("//div[@id = 'ctl00__objHeader_pnlLoggedInUserTitle']/span/span", null, true, "/Hi\s*([^\!<]+)/ims")));
        // Balance - table "Your Reward History" -> first row -> field "Balance"
        $this->SetBalance($this->http->FindSingleNode('//span[@class = "noPoints" and not(@style="display: none;")]/span', null, true, "/([\d\.\,]+)/ims"));

        // Expiration Date
        $this->http->GetURL('https://www.papajohns.co.uk/my-previous-orders.aspx');
        $this->hideOverlay();
        $nodes = $this->http->XPath->query("//div[@id='ctl00_cphBody_divPreviousOrders']//table//tr[not(tr) and count(td) > 1]");
        $maxDate = 0;

        foreach ($nodes as $node) {
            $lastActivity = $this->http->FindSingleNode("td[@class='orderDate']", $node);
            $this->logger->debug("Last Activity: {$lastActivity}");
            $expDate = strtotime($lastActivity, false);

            if ($expDate && $expDate > $maxDate) {
                $maxDate = $expDate;
                $this->SetExpirationDate(strtotime('+6 month', $maxDate));
                $this->SetProperty("LastActivity", $lastActivity);
                $this->SetProperty("AccountExpirationWarning", "{$this->AccountFields['DisplayName']} state the following on their website: <a target=\"_blank\" href=\"https://www.papajohns.co.uk/terms-and-conditions/papa-rewards.aspx\">Points will expire 6 months after the customers last order date</a>.
 <br><br>We determined that last time you had account activity with Papa John's Pizza on {$lastActivity}, so the expiration date was calculated by adding 6 months to this date.");
            }
        }
        /*
        if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR) {
            if (count($this->http->FindNodes("//table[@class = 'nutritionalTable']//tr")) == 2)
                $this->SetBalanceNA();
        }// if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR)
        */
    }

    protected function parseReCaptcha($key = null)
    {
        $this->logger->notice(__METHOD__);
        $key = $this->http->FindSingleNode("//div[@id = 'captcha_container']//iframe/@src", null, true, "/k=([^&]+)/");
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
}
