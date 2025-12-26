<?php

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\ProxyList;

class TAccountCheckerShowpoints extends TAccountChecker
{
    use ProxyList;
    use SeleniumCheckerHelper;
    private const WAIT_TIMEOUT = 10;

    public function GetRedirectParams($targetURL = null)
    {
        $arg = parent::GetRedirectParams($targetURL);
        $arg['CookieURL'] = 'https://www.audiencerewards.com/Member/Activity';

        return $arg;
    }

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->UseSelenium();
        $this->useGoogleChrome(SeleniumFinderRequest::CHROME_99);
        $this->http->saveScreenshots = true;
    }

    public function IsLoggedIn()
    {
        $this->http->RetryCount = 0;
        $this->http->GetURL('https://www.audiencerewards.com/');
        $this->http->RetryCount = 2;

        return $this->loginSuccessful();
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->getURL("https://www.audiencerewards.com/api/auth/login?redirectTo=%2F");

        $login = $this->waitForElement(WebDriverBy::xpath('//input[@id="username"]'), self::WAIT_TIMEOUT);
        $this->saveResponse();
        $login->sendKeys($this->AccountFields['Login']);

        /*
        $submit = $this->waitForElement(WebDriverBy::xpath('//div/button[@type="submit" and @value="default"]'), 0);
        $this->saveResponse();
        $submit->click();
        */

        sleep(3);
        $this->driver->executeScript("document.getElementsByClassName('_form-login-id')[0].submit();");
        $password = $this->waitForElement(WebDriverBy::xpath('//input[@id="password"] | //span[@id="error-element-username"]'), self::WAIT_TIMEOUT);
        $this->saveResponse();

        if ($message = $this->http->FindSingleNode('//span[@id="error-element-username"]')) {
            $this->logger->error('[ERROR]: ' . $message);

            if (strstr($message, "Email is not valid")) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            $this->DebugInfo = $message;

            return $this->checkErrors();
        }

        /*
        $submit = $this->waitForElement(WebDriverBy::xpath('//div/button[@type="submit" and @value="default"]'), 0);
        $this->saveResponse();
        $submit->click();
        */

        $password->sendKeys($this->AccountFields['Pass']);
        sleep(3);
        $this->driver->executeScript("document.getElementsByClassName('_form-login-password')[0].submit();");

        return true;
    }

    public function Login()
    {
        $this->waitForElement(WebDriverBy::xpath('//span[@id="error-element-password"] | //p[contains(text(), "Account")]'), self::WAIT_TIMEOUT);
        $this->saveResponse();

        if ($message = $this->http->FindSingleNode('//span[@id="error-element-password"]')) {
            $this->logger->error('[ERROR]: ' . $message);

            if (strstr($message, "Wrong email or password")) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            $this->DebugInfo = $message;
        }

        // Sorry, this does not look like a valid email address
        if ($message = $this->http->FindSingleNode("//strong[contains(text(), 'Sorry, this does not look like a valid email address')]")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        // Invalid Email Address / Password combination. Try Again.
        if ($message = $this->http->FindPreg("/(Oops\&\#x21; We don\&\#x27;t have an account matching that info\. Please try again\.)/ims")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        // We're unable to log you in with those user credentials.
        if ($message = $this->http->FindPreg("/(We\'re unable to log you in with those user credentials\.)/ims")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        // We're unable to locate an account with this email address.
        if ($message = $this->http->FindPreg("/(We\'re unable to locate an account with this email address\.)/ims")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        // Sorry, this e-mail is associated with more than one account. Please try using your Account Number to login.
        if ($message = $this->http->FindSingleNode("//strong[contains(text(), 'Sorry, this e-mail is associated with more than one account.')]")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        // Sorry, the number you have entered is not a valid account number
        if ($message = $this->http->FindSingleNode("//strong[contains(text(), 'Sorry, the number you have entered is not a valid account number')]")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        // Sorry! Incorrect password entered. Please try again.
        if ($message = $this->http->FindPreg("/(Sorry! Incorrect password entered. Please try again\.)/")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        // Sorry, this account has been deactivated or merged with a different profile.
        if ($message = $this->http->FindSingleNode("//text()[contains(., 'Sorry, this account has been deactivated or merged with a different profile.')]")) {
            throw new CheckException($message, ACCOUNT_LOCKOUT);
        }

        // Success
        if ($this->loginSuccessful()) {
            return true;
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        $this->http->GetURL('https://www.audiencerewards.com/');
        $this->waitForElement(WebDriverBy::xpath('//p[contains(text(), "Account Number: ")]'), self::WAIT_TIMEOUT);
        $this->saveResponse();
        // Name
        $this->SetProperty('Name', $this->http->FindSingleNode('//p[contains(text(), "Welcome")]', null, true, '/Welcome,\s*([^<]+)!/'));
        // Audience Rewards Number
        $this->SetProperty('Number', $this->http->FindSingleNode('//p[contains(text(), "Account Number: ")]', null, true, '/\d+/'));
        // Status
        $this->SetProperty('Status', $this->http->FindSingleNode('//span[contains(text(), "Member")]', null, true, '/(.*)\s*Member/'));
        // Balance - Current Point Balance
        $this->SetBalance(PriceHelper::Parse($this->http->FindSingleNode('//p[contains(text(), "ShowPoint Balance: ")]', null, true, '/[\d+,]+/')));
        // Qualifying ShowPoints
        $this->SetProperty("QualifyingPoints", $this->http->FindSingleNode('//p[contains(text(), "/") and not(contains(text(), "expires"))]'));
        // Status Expiration
        $this->SetProperty("StatusExpiration", strtotime($this->http->FindSingleNode('//p[contains(text(), "/") and contains(text(), "expires")]')));
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);
        // Our site is temporarily down for maintenance. So grab a drink at concessions, flip through your Playbill ... and we’ll see you at the top of Act II!
        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'Our site is temporarily down for maintenance. So grab a drink at concessions, flip through your')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // Service Unavailable
        if ($message = $this->http->FindSingleNode("//h1[contains(text(), 'Service Unavailable')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        /*
         * Don't worry. It's a 500 Server Error only.
         *
         * Actually, the Director is backstage giving our tech team some notes for the next performance.
         * Something is broken. We will fix it soon.
         */
        if ($message = $this->http->FindSingleNode("//h1[big[contains(text(), '500 Server Error')]]")) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }
        /*
         * Exception in onError
         *
         * The action login.authenticate failed.
         *
         * can't call method [track] on object, object is null
         */
        if ($this->http->Response['code'] == 500
            && $this->http->FindPreg("/<h1>Exception in onError<\/h1><p>The action login\.authenticate failed\.<\/p><h2>can't call method \[track\] on object\, object is null<\/h2><p> (expression)<\/p>/")) {
            throw new CheckRetryNeededException(2, 10);
        }

        return false;
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);
        $el = $this->waitForElement(WebDriverBy::xpath('//a[contains(@href, "login") and @class="button"] | //p[contains(text(), "Account")]'), self::WAIT_TIMEOUT);
        $this->saveResponse();

        if (isset($el) && $el->getTagName() == 'p') {
            return true;
        }

        return false;
    }
}
