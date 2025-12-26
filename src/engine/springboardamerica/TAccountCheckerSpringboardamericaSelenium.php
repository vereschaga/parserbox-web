<?php

use AwardWallet\Engine\ProxyList;

class TAccountCheckerSpringboardamericaSelenium extends TAccountChecker
{
    use SeleniumCheckerHelper;
    use ProxyList;

    public function IsLoggedIn()
    {
        $this->http->GetURL("https://www.springboardamerica.com/PORTAL/default.aspx");
        $logout = $this->waitForElement(WebDriverBy::xpath("//a[contains(@class, 'logout-btn')]"), 7, false);
        $this->saveResponse();

        if ($logout) {
            return true;
        }

        return false;
    }

    public function InitBrowser()
    {
        parent::InitBrowser();

        if (ConfigValue(CONFIG_SITE_STATE) != SITE_STATE_DEBUG) {
            $this->http->SetProxy($this->proxyDOP());
        }
        $this->InitSeleniumBrowser($this->http->GetProxy());
        $this->useFirefox();
        $this->http->TimeLimit = 600;
        $this->keepCookies(false);
    }

    public function LoadLoginForm()
    {
        $this->http->GetURL("https://www.springboardamerica.com/PORTAL/default.aspx");

        if ($error = $this->http->FindPreg("/(?:Secure Connection Failed|Unable to connect|The proxy server is refusing connections|Failed to connect parent proxy|The connection has timed out)/")) {
            $this->http->Log($error, LOG_LEVEL_ERROR);

            throw new CheckRetryNeededException(5, 10);
        }

        $signIn = $this->waitForElement(WebDriverBy::xpath('//a[contains(text(), "Sign In")]'), 10);

        if (empty($signIn)) {
            return $this->checkErrors();
        }
        $signIn->click();

        $login = $this->waitForElement(WebDriverBy::xpath('//input[@name = "ctl00$ctl00$contentPrimary$LC$ctl08"]'), 10);
        $this->saveResponse();

        if (empty($login)) {
            return $this->checkErrors();
        }
        $login->sendKeys($this->AccountFields['Login']);
        $password = $this->waitForElement(WebDriverBy::xpath('//input[@name = "ctl00$ctl00$contentPrimary$LC$ctl13"]'), 0);

        if (!$password) {
            $this->http->Log('Failed to find password input field', LOG_LEVEL_ERROR);

            return false;
        }
        $password->sendKeys($this->AccountFields['Pass']);
        $this->saveResponse();

        $rememberMe = $this->waitForElement(WebDriverBy::xpath('//input[@name = "ctl00$ctl00$contentPrimary$LC$ctl15"]'), 0);

        if ($rememberMe) {
            $rememberMe->click();
        }// if ($rememberMe)
        else {
            $this->http->Log('Failed to find "Remember Me" input field', LOG_LEVEL_ERROR);
        }

        // captcha
        $iframe = $this->waitForElement(WebDriverBy::xpath("//div[@class = 'g-recaptcha']//iframe"));

        if ($iframe) {
            $this->driver->switchTo()->frame($iframe);
            $recaptchaAnchor = $this->waitForElement(WebDriverBy::id("recaptcha-anchor"), 20);

            if (!$recaptchaAnchor) {
                $this->http->Log('Failed to find reCaptcha "I am not a robot" button');

                throw new CheckRetryNeededException(3, 7);
            }
            $recaptchaAnchor->click();
        } else {
            $this->http->Log("captcha frame not found");

            return false;
        }

        $this->http->Log("wait captcha iframe");
        $this->driver->switchTo()->defaultContent();
        $iframe2 = $this->waitForElement(WebDriverBy::xpath("//iframe[@title = 'recaptcha challenge']"), 10, true);
        $this->saveResponse();

        if ($iframe2) {
            $status = '';

            if (!$status) {
                $this->http->Log('Failed to pass captcha');

                throw new CheckRetryNeededException(3, 2, self::CAPTCHA_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
            }// if (!$status)
        }// if ($iframe2)
        // submit form
        $loginButton = $this->waitForElement(WebDriverBy::xpath('//input[@name = "ctl00$ctl00$contentPrimary$LC$LgnBtn"]'), 0);

        if (!$loginButton) {
            $this->logger->error('Failed to find login button');

            return false;
        }// if (!$loginButton)
        $this->driver->executeScript("$('input[name = \"ctl00\$ctl00\$contentPrimary\$LC\$LgnBtn\"]').click()");
//        $loginButton->click();

        return true;
    }

    public function checkErrors()
    {
        if ($message = $this->http->FindSingleNode("//span[@id = 'ErrorMessageLabel']", null, false)) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // Springboard America is currently undergoing maintenance
        if ($message = $this->http->FindSingleNode("//h2[contains(text(), 'Springboard America is currently undergoing maintenance')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        //# Maintenance
        if ($message = $this->http->FindSingleNode("//font[contains(text(), 'We are performing emergency maintenance')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // The application you are trying to access is currently down for scheduled maintenance.
        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'The application you are trying to access is currently down for scheduled maintenance.')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        //# Sparq is currently unavailable
        if ($message = $this->http->FindPreg("/(Sparq is currently unavailable)/ims")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // Server Error in '/' Application.
        if ($message = $this->http->FindPreg("/(Server Error in \'\/\' Application\.)/ims")) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
        $logout = $this->waitForElement(WebDriverBy::xpath("//a[contains(@class, 'logout-btn')]"), 7, false);
        $this->saveResponse();
        // Access is allowed
        if ($logout) {
            return true;
        }
        // The email address or password you entered is incorrect. Please try again.
        if ($message = $this->http->FindPreg('/(The email address or password you entered is incorrect\. Please try again\.)/ims')) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        // Please complete the security verification question
        if ($this->waitForElement(WebDriverBy::xpath('//div[contains(text(), "Please complete the security verification question")]'), 0)) {
            throw new CheckRetryNeededException(3, 7, self::CAPTCHA_ERROR_MSG);
        }
        // You have entered an incorrect email address or password. Please try again.
        if ($message = $this->waitForElement(WebDriverBy::xpath('//div[contains(text(), "You have entered an incorrect email address or password")]'), 0)) {
            throw new CheckException($message->getText(), ACCOUNT_INVALID_PASSWORD);
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        // Name
        $this->SetProperty("Name", beautifulName($this->http->FindSingleNode("//div[@class = 'welcome-text']/h1", null, true, "/Hello\s*([^<]+)/ims")));
        // Balance - Total points balance
        $this->SetBalance($this->http->FindSingleNode('//span[@class="psBalValLbl"]', null, true, '/([\d\.\,\-\$]+)/ims'));
        // Total points earned
        $this->SetProperty("TotalEarned", $this->http->FindSingleNode('//span[@class="psErndValLbl"]', null, true, '/(\$\d+.\d+)/ims'));
        // Total points redeemed
        $this->SetProperty("TotalRedeemed", $this->http->FindSingleNode('//span[@class="psRdmValLbl"]', null, true, '/(\$\d+.\d+)/ims'));
    }
}
