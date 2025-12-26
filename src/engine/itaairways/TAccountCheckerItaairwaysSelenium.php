<?php

class TAccountCheckerItaairwaysSelenium extends TAccountChecker
{
    use SeleniumCheckerHelper;

    private const REWARDS_PAGE_URL = 'https://www.volare.ita-airways.com/myloyalty/s/?language=en_US';
    private const WAIT_TIMEOUT = 20;

    private $token = null;
    private $userID = null;

    private $curlDrive;

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->setDefaultHeader("Accept-Encoding", "gzip, deflate, br");
        $this->UseSelenium();
        $this->useGoogleChrome(SeleniumFinderRequest::CHROME_99);
        $this->http->saveScreenshots = true;
    }

    public function IsLoggedIn()
    {
        if ($this->loginSuccessful()) {
            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->GetURL('https://www.volare.ita-airways.com/myloyalty/s/login/?language=en_US&ec=302&startURL=%2Fmyloyalty%2Fs%2F');

        $fwuid = $this->http->FindPreg('/"fwuid":"([^"]+)/');
        $loginApp2 = $this->http->FindPreg('/loginApp2":"([^"]+)/');

        $this->logger->debug('$fwuid' . $fwuid);
        $this->logger->debug('$loginApp2' . $loginApp2);

        $this->State['context'] = '{"mode":"PROD","fwuid":"' . $fwuid . '","app":"siteforce:communityApp","loaded":{"APPLICATION@markup://siteforce:communityApp":"' . $loginApp2 . '"},"dn":[],"globals":{},"uad":false}';

        if ($this->http->Response['code'] != 200) {
            return $this->checkErrors();
        }

        $this->waitForElement(WebDriverBy::xpath('//input[@name="email"] | //span[contains(text(), "We are down for maintenance")]'), self::WAIT_TIMEOUT);
        $login = $this->waitForElement(WebDriverBy::xpath('//input[@name="email"]'), 0);
        $password = $this->waitForElement(WebDriverBy::xpath('//input[@name="password"]'), 0);
        $submit = $this->waitForElement(WebDriverBy::xpath('//button[contains(text(), "Login")]'), 0);

        $this->saveResponse();

        if (!$login || !$password || !$submit) {
            return $this->checkErrors();
        }

        $login->sendKeys($this->AccountFields['Login']);
        $password->sendKeys($this->AccountFields['Pass']);
        $submit->click();

        return true;
    }

    public function Login()
    {
        $this->waitForElement(WebDriverBy::xpath('//p[contains(@class, "text-danger")] | //c-loyalty-login-flow-code | //span[contains(text(), "This requires verification of your email")]'), self::WAIT_TIMEOUT);
        $this->saveResponse();

        if (strstr($this->http->currentUrl(), 'setupid=ChangePassword')) {
            $this->sendNotification('refs #23950 itaairways - change password action detected // IZ');
            $this->throwProfileUpdateMessageException();
        }

        if ($this->http->FindSingleNode('//span[contains(text(), "This requires verification of your email")]')) {
            $this->throwProfileUpdateMessageException();
        }

        if ($error = $this->http->FindSingleNode('//p[contains(@class, "text-danger")]')) {
            $this->logger->error("[Error]: {$error}");

            if (in_array($error, [
                'Your login attempt has failed. Make sure the username and password are correct.',
                'The login attempt failed. Make sure your username and password are correct.',
                'The format you entered is invalid.',
            ])
            ) {
                throw new CheckException($error, ACCOUNT_INVALID_PASSWORD);
            }

            if (strstr($error, 'Account bloccato per mancata attivazione del profilo.')) {
                throw new CheckException($error, ACCOUNT_LOCKOUT);
            }

            $this->DebugInfo = $error;

            return false;
        }

        if ($this->waitForElement(WebDriverBy::xpath('//c-loyalty-login-flow-code'), 0)) {
            return $this->processQuestion();
        }

        if ($this->loginSuccessful()) {
            return true;
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        $data = [
            "message"      => '{"actions":[{"id":"129;a","descriptor":"aura://ApexActionController/ACTION$execute","callingDescriptor":"UNKNOWN","params":{"namespace":"","classname":"ITA_LoyaltyMemberService","method":"getLoyaltyMember","cacheable":false,"isContinuation":false}},{"id":"130;a","descriptor":"aura://ApexActionController/ACTION$execute","callingDescriptor":"UNKNOWN","params":{"namespace":"","classname":"b2c_showcaseController","method":"getAccountFromUserId","params":{"userId":"' . $this->userID . '"},"cacheable":false,"isContinuation":false}},{"id":"131;a","descriptor":"aura://ApexActionController/ACTION$execute","callingDescriptor":"UNKNOWN","params":{"namespace":"","classname":"ITA_LoyaltyMemberService","method":"getLoyaltyTransactions","params":{"filter":{"page":1,"pageSize":5,"filter":{}}},"cacheable":false,"isContinuation":false}},{"id":"138;a","descriptor":"aura://ApexActionController/ACTION$execute","callingDescriptor":"UNKNOWN","params":{"namespace":"","classname":"HeaderCommunityController","method":"getCurrentUser","params":{"userId":"' . $this->userID . '"},"cacheable":false,"isContinuation":false}},{"id":"139;a","descriptor":"aura://ApexActionController/ACTION$execute","callingDescriptor":"UNKNOWN","params":{"namespace":"","classname":"HeaderCommunityController","method":"getFooterNavigationMenu","params":{"menuName":"Default_Navigation","language":"en_US"},"cacheable":false,"isContinuation":false}}]}',
            "aura.context" => $this->State['context'],
            "aura.pageURI" => "/myloyalty/s/",
            "aura.token"   => $this->token,
        ];
        $this->curlDrive->RetryCount = 0;
        $this->curlDrive->PostURL("https://www.volare.ita-airways.com/myloyalty/s/sfsites/aura?r=4&aura.ApexAction.execute=5", $data);
        $this->curlDrive->RetryCount = 2;
        $response = $this->curlDrive->JsonLog(null, 3, false, "PointsBalance");

        foreach ($response->actions as $action) {
            switch ($action->id) {
                case '129;a':
                    // refs $21766
                    foreach ($action->returnValue->returnValue->Loyalty_Member_Currency as $loyalty_Member_Currency) {
                        switch ($loyalty_Member_Currency->LoyaltyProgramCurrency->CurrencyType) {
                            case 'NonQualifying':
                                // Balance - Points Balance
                                $this->SetBalance($loyalty_Member_Currency->PointsBalance);

                                break;

                            case 'Qualifying':
                                // Qualifying Points Balance
                                $qualifyingPoints = $loyalty_Member_Currency->PointsBalance;
                                $this->SetProperty('QualifyingPoints', $qualifyingPoints);

                                break;
                        }
                    }
                    $tier = $action->returnValue->returnValue->Loyalty_Member_Tier[0];

                    if (is_numeric($tier->LoyaltyTier->To_Points__c ?? null)
                        && is_numeric($qualifyingPoints ?? null)
                    ) {
                        $this->SetProperty('QualifyingPointsToNextTier', $tier->LoyaltyTier->To_Points__c - $qualifyingPoints ?? null);
                    } elseif (isset($tier->Name)
                        && $tier->Name == 'Club Executive'
                    ) {
                        $this->SetProperty('QualifyingPointsToNextTier', 0);
                    }

                    // Number
                    $this->SetProperty('Number', $action->returnValue->returnValue->MembershipNumber);
                    // Status
                    $this->SetProperty('Status', $tier->Name);
                    // Status Expiration
                    $this->SetProperty('StatusExpiration', $tier->TierExpirationDate);
                    // Name
                    $contact = $action->returnValue->returnValue->Contact;
                    $this->SetProperty('Name', beautifulName($contact->FirstName . " " . $contact->LastName));
            }
        }// foreach ($response->actions as $action)
    }

    public function ProcessStep($step)
    {
        $this->logger->notice(__METHOD__);
        $this->logger->debug("Current URL: " . $this->http->currentUrl());

        if ($step == "otp") {
            return $this->processQuestion();
        }

        return false;
    }

    private function loginSuccessful()
    {
        $this->openCurlDrive();
        $this->copySeleniumCookies($this, $this->curlDrive);

        $this->logger->notice(__METHOD__);
        $this->curlDrive->RetryCount = 0;
        $this->curlDrive->GetURL(self::REWARDS_PAGE_URL, [], 20);
        $this->curlDrive->RetryCount = 2;

        $cookies = $this->curlDrive->GetCookies("www.volare.ita-airways.com", "/", true);

        foreach ($cookies as $key => $cookie) {
            if (strstr($key, '__Host-ERIC_PROD')) {
                $this->token = urldecode($cookie);
            }
        }

        $bootstrap = $this->curlDrive->FindSingleNode("//script[contains(@src, 'bootstrap.js')]/@src");

        if (!isset($bootstrap) || !$this->token) {
            return false;
        }

        $this->curlDrive->NormalizeURL($bootstrap);
        $this->curlDrive->GetURL($bootstrap, [], 20);

        $this->userID = $this->curlDrive->FindPreg("/\"Email\":\"[^\"]+\",\"Id\":\"([^\"]+)\"\}\}\}/");
        $email = $this->curlDrive->FindPreg("/\"Email\":\"([^\"]+)\",\"Id\":\"[^\"]+\"\}\}\}/");
        $this->logger->debug("[Email]: {$email}");

        if (!$this->userID || strtolower($email) != strtolower($this->AccountFields['Login'])) {
            return false;
        }

        return true;
    }

    private function checkErrors()
    {
        $this->logger->notice(__METHOD__);

        if ($message = $this->http->FindSingleNode('//span[contains(text(), "We are down for maintenance")]')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    private function processQuestion()
    {
        $this->logger->notice(__METHOD__);

        // $this->waitForElement(WebDriverBy::xpath('//c-loyalty-login-flow-code'), self::WAIT_TIMEOUT);
        $questionField = $this->waitForElement(WebDriverBy::xpath('//c-loyalty-login-flow-code//span'), 0);
        $otpInput = $this->waitForElement(WebDriverBy::xpath('//c-loyalty-login-flow-code//input[contains(@id, "otp")]'), 0);
        $otpSubmit = $this->waitForElement(WebDriverBy::xpath('//c-loyalty-login-flow-code//button[contains(text(), "VERIFY")]'), 0);

        $this->saveResponse();

        if (!$questionField || !$otpInput || !$otpSubmit) {
            $this->logger->debug('failed to find otp form fields');

            return false;
        }

        if ($this->getWaitForOtc()) {
            $this->sendNotification("refs #23950 itaairways - account with mailbox // IZ");
        }

        $question = $questionField->getText();

        if (!isset($this->Answers[$question])) {
            $this->holdSession();
            $this->AskQuestion($question, null, "otp");

            return false;
        }

        $otpInput->clear();
        $otpInput->sendKeys($this->Answers[$question]);
        unset($this->Answers[$question]);

        $this->logger->debug("Submit question");
        $otpSubmit->click();

        sleep(5);

        $this->saveResponse();

        $errorField = $this->waitForElement(WebDriverBy::xpath('//lightning-formatted-rich-text[contains(@class, "has-error")]/span'), 0);

        if ($errorField) {
            $error = $errorField->getText();
            $this->logger->error("[Error]: {$error}");
            $this->holdSession();
            $this->AskQuestion($question, $error, "otp");
            $this->DebugInfo = $error;

            return false;
        }

        return $this->loginSuccessful();
    }

    private function copySeleniumCookies($selenium, $curl)
    {
        $this->logger->notice(__METHOD__);

        $cookies = $selenium->driver->manage()->getCookies();

        foreach ($cookies as $cookie) {
            $curl->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
        }
    }

    private function openCurlDrive()
    {
        $this->logger->notice(__METHOD__);
        $this->curlDrive = new HttpBrowser("none", new CurlDriver());
        $this->http->brotherBrowser($this->curlDrive);
    }
}
