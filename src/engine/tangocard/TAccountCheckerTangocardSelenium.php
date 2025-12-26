<?php

use AwardWallet\Engine\ProxyList;

class TAccountCheckerTangocardSelenium extends TAccountChecker
{
    use SeleniumCheckerHelper;
    use ProxyList;

    public function InitBrowser()
    {
        parent::InitBrowser();
//        if (ConfigValue(CONFIG_SITE_STATE) == SITE_STATE_PRODUCTION) {
//            $this->http->SetProxy($this->proxyStaticIpDOP());
//        }
        $this->InitSeleniumBrowser($this->http->GetProxy());
        $this->useFirefox();
        $this->keepCookies(false);
    }

    public function LoadLoginForm()
    {
        if (!$this->isNewSession()) {
            $this->startNewSession();
        }
        $this->http->GetURL('https://www.tangocard.com/user/login');

        if ($error = $this->http->FindPreg("/(?:Secure Connection Failed|Unable to connect|The proxy server is refusing connections|Failed to connect parent proxy|The connection has timed out)/")) {
            $this->http->Log($error, LOG_LEVEL_ERROR);

            throw new CheckRetryNeededException(5, 10);
        }

        $login = $this->waitForElement(WebDriverBy::xpath("//input[@id = 'username']"));
        $this->saveResponse();

        if (empty($login)) {
            return $this->checkErrors();
        }
        $login->sendKeys($this->AccountFields['Login']);
        $this->driver->findElement(WebDriverBy::xpath("//input[@name = 'password']"))->sendKeys($this->AccountFields['Pass']);
        // captcha
        $iframe = $this->waitForElement(WebDriverBy::xpath("//div[@class = 'g-recaptcha']//iframe"));

        if (!$iframe) {
            $this->http->Log("captcha frame not found");

            return $this->checkErrors();
        }
        $this->driver->switchTo()->frame($iframe);
        $recaptchaAnchor = $this->waitForElement(WebDriverBy::id("recaptcha-anchor"));

        if (!$recaptchaAnchor) {
            $this->logger->error('Failed to find recaptcha anchor');

            return false;
        }
        $recaptchaAnchor->click();
        $this->http->Log("wait captcha iframe");
        $this->driver->switchTo()->defaultContent();
        $iframe2 = $this->waitForElement(WebDriverBy::xpath("//iframe[@title = 'recaptcha challenge']"), 10, true);
        $this->saveResponse();

        if ($iframe2) {
            $status = '';

            if (!$status) {
                $this->http->Log('Failed to pass captcha');
                // retries
                throw new CheckRetryNeededException(3, 7, self::CAPTCHA_ERROR_MSG);
            }// if (!$status)
        }// if ($iframe2)

        return true;
    }

    public function checkErrors()
    {
        // maintenance
        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'Tangocard.com is down for maintenance. We apologize for the inconvenience.')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
        $this->http->Log(__METHOD__);
        $loginButton = $this->waitForElement(WebDriverBy::xpath("//input[@value = 'login']"));

        if (!$loginButton) {
            $this->logger->error('Failed to find login button');

            return false;
        }// if (!$loginButton)
        $this->driver->executeScript("$('input[value = \"login\"]').click()");
        //		$loginButton->click();
        $this->saveResponse();
        // look for logout link
        $logout = $this->waitForElement(WebDriverBy::xpath('//a[@href="/user/logout"]'), 10, true);
        $this->saveResponse();

        if ($logout || $this->http->FindSingleNode('//a[@href="/user/logout"]/@href')) {
            return true;
        }

        if ($message = $this->http->FindSingleNode('//div[contains(@class, "errorMessage")]/div')) {
            if (!strstr($message, "We're sorry, your answer to the security challenge was not correct")
                && !strstr($message, "Please click the \"I'm not a robot\" box and then resubmit your request to continue")) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            } else {
                $this->http->Log($message, LOG_LEVEL_ERROR);
                // retries
                throw new CheckRetryNeededException(3, 7, self::CAPTCHA_ERROR_MSG);
            }
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        // Name
        $this->SetProperty("Name", beautifulName($this->http->FindSingleNode('//input[@name="full_name"]/@value')));
        // Balance - Your Tango Card Balance
        $this->SetBalance($this->http->FindSingleNode('//div[@id="my-cards"]/h2/strong', null, null, '/\$(.+)/ims'));
    }
}
