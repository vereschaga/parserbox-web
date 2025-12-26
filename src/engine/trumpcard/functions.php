<?php

use AwardWallet\Engine\ProxyList;

class TAccountCheckerTrumpcard extends TAccountChecker
{
    use SeleniumCheckerHelper;
    use ProxyList;

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;

        $this->UseSelenium();
//        $this->useFirefox();
        $this->useFirefox(SeleniumFinderRequest::FIREFOX_84);
        $this->setKeepProfile(true);
        $this->setProxyGoProxies();
        $this->seleniumOptions->fingerprintOptions = ['devices' => ['desktop'], 'operatingSystems' => ['macos']];
        $this->seleniumOptions->addHideSeleniumExtension = false;
        $this->seleniumOptions->userAgent = null;

        $this->http->saveScreenshots = true;
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->GetURL("https://www.trumphotels.com/customer/account/");
        $loginInput = $this->waitForElement(WebDriverBy::xpath('//input[@name="login[username]"]'), 5);
        $passInput = $this->waitForElement(WebDriverBy::xpath('//input[@name="login[password]"]'), 0);
        $btn = $this->waitForElement(WebDriverBy::xpath('//button[@name="send"]'), 0);
        $this->saveResponse();

        if (!$loginInput || !$passInput || !$btn) {
            return $this->checkErrors();
        }

        $loginInput->sendKeys($this->AccountFields['Login']);
        $passInput->sendKeys($this->AccountFields['Pass']);
        $this->saveResponse();
        $btn->click();

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);
        // You have arrived at this page during a planned maintenance period.
        if ($message = $this->http->FindSingleNode("//text()[contains(., 'You have arrived at this page during a planned maintenance period.')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        //# The page cannot be displayed
        if ($message = $this->http->FindPreg("/(The page cannot be displayed)/ims")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // Our website is currently offline for scheduled maintenance.
        if ($message = $this->http->FindSingleNode("//font[contains(text(), 'Our website is currently offline for scheduled maintenance.')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        // Cache directory must be writable. ( modules/custom_loyalty/cache/ )
        if ($message = $this->http->FindPreg("/Cache directory must be writable\. \( modules\/custom_loyalty\/cache\/ \)/")) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
        $this->waitForElement(WebDriverBy::xpath("//a[contains(@href, 'logout')] | //div[contains(@data-ui-id, 'message-error')] | //div[@class='mage-error']"), 10);
        $this->saveResponse();
        $message = $this->http->FindSingleNode("//div[contains(@data-ui-id, 'message-error')] | //div[@class='mage-error']");

        // login successful
        if (!$message && $this->loginSuccessful()) {
            return true;
        }

        if ($message) {
            $message = str_replace("* ", "", $message);
            $this->logger->error("[Error]: {$message}");

            if (
                strstr($message,
                    'The account sign-in was incorrect or your account is disabled temporarily. Please wait and try again later.')
                || strstr($message, 'Please enter a valid email address (Ex: johndoe@domain.com).')

            ) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            $this->DebugInfo = $message;

            return false;
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        // Please accepts the terms of the program
        if ($this->http->FindSingleNode('//p[contains(text(), "Please accept") and contains(text(), "the terms of the program")]')) {
            $this->throwAcceptTermsMessageException();
        }
        // Balance - Points Available
        $this->SetBalance($this->http->FindSingleNode("//div[contains(@class, 'loyalty-points-wrapper')]/div"));
        // Name
        $this->SetProperty("Name", beautifulName($this->http->FindSingleNode("//div[contains(text(), 'Welcome, ')]", null, true, "/Welcome, (.+)/")));
        // Trump Card
        $this->SetProperty("AccountNumber", $this->http->FindSingleNode("//div[contains(@class, 'member-number-wrapper')]/div"));
        // Member Since
        $this->SetProperty("MemberSince", $this->http->FindSingleNode("(//div[contains(text(), 'Member Since')])[1]", null, true, '/Since\s+(\w+)/'));
        // Nights to Next Level
        $this->SetProperty("NightsNextLevel", $this->http->FindSingleNode("//div[contains(@class, 'nights-until-wrapper')]/div"));
        // Qualifying Nights
        $this->SetProperty("QualifyingNights", $this->http->FindSingleNode("//div[contains(@class, 'qualifying-nights-wrapper')]/div"));
        // Current Status
        $this->SetProperty("Status", $this->http->FindSingleNode("//div[@class = 'status-card-wrapper']/button"));

        if (
            $this->ErrorCode == ACCOUNT_ENGINE_ERROR
            && (
                // Tell Us About Yourself
                $this->http->ParseForm("frmTestAuthentication")
                || $this->http->FindSingleNode('//p[contains(text(), "You must update your login information before continuing")]')
            )
        ) {
            $this->throwProfileUpdateMessageException();
        }
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);

        if ($this->http->FindNodes("//a[contains(@href, 'logout')]/@href")) {
            return true;
        }

        return false;
    }
}
