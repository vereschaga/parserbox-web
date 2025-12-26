<?php

class TAccountCheckerNavy extends TAccountChecker
{
    use SeleniumCheckerHelper;

    public static function FormatBalance($fields, $properties)
    {
        if (isset($properties['Currency']) && $properties['Currency'] == '$') {
            return formatFullBalance($fields['Balance'], $fields['ProviderCode'], "$%0.2f");
        }

        return parent::FormatBalance($fields, $properties);
    }

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->UseSelenium();
//        $this->useFirefoxPlaywright();// TODO: not working now
        $this->useGoogleChrome(SeleniumFinderRequest::CHROME_94);
        $this->seleniumOptions->addHideSeleniumExtension = false;
        $this->seleniumOptions->userAgent = null;

        $this->http->saveScreenshots = true;
    }

    public function LoadLoginForm()
    {
        if (
            strlen($this->AccountFields['Pass']) < 8
            || strstr($this->AccountFields['Login'], '****')
            || strstr($this->AccountFields['Pass'], ')')
        ) {
            throw new CheckException('The username or password you entered was incorrect.', ACCOUNT_INVALID_PASSWORD);
        }

        $this->http->RetryCount = 0;
        $this->http->GetURL("https://digitalapps.navyfederal.org/signin/");
        $this->http->RetryCount = 2;

        $loginInput = $this->waitForElement(WebDriverBy::xpath('//input[@name = "USER"]'), 7);
        $passwordInput = $this->waitForElement(WebDriverBy::xpath('//input[@name = "PASSWORD"]'), 0);
        $this->saveResponse();

        if (!$loginInput || !$passwordInput) {
            return false;
        }

        $mover = new MouseMover($this->driver);
        $mover->logger = $this->logger;
        $mover->sendKeys($loginInput, $this->AccountFields['Login'], 5);
//        $loginInput->sendKeys($this->AccountFields['Login']);
        $passwordInput->sendKeys($this->AccountFields['Pass']);

        $button = $this->waitForElement(WebDriverBy::xpath('//button[@id = "signInButton" and not(@disabled)]'), 2);
        $this->saveResponse();

        if (!$button) {
            return false;
        }

        $button->click();

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);
        // System Unavailable
        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'Account Access is temporarily unavailable')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // We are currently performing routine system maintenance.
        if ($message = $this->http->FindPreg("/We\s*are\s*currently\s*performing\s*routine\s*system\s*maintenance\./ims")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // There was a problem processing your request.
        if ($message = $this->http->FindSingleNode("//label[contains(text(), 'There was a problem processing your request.')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // We're conducting regularly scheduled maintenance and will be back up again in no time.
        if ($message = $this->http->FindSingleNode('//p[contains(text(), "We\'re conducting regularly scheduled maintenance and will be back up again in no time.")]')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // Internal Server Error
        if (
            $this->http->FindSingleNode("//h1[contains(text(), 'Internal Server Error')]")
            || $this->http->FindSingleNode('//h1[contains(text(), "A WebGroup/Virtual Host to handle /NFOAA_Auth/login.jsp has not been defined.")]')
            || $this->http->FindPreg("/An error occurred while processing your request\.<p>/")
            || $this->http->FindSingleNode("//title[contains(text(), 'Service Unavailable - Fail to connect')]")
        ) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }
        // ENROLL IN ACCOUNT ACCESS
        if ($message = $this->http->FindSingleNode("//div[contains(text(), 'ENROLL IN ACCOUNT ACCESS')]")) {
            throw new CheckException(self::NOT_MEMBER_MSG, ACCOUNT_PROVIDER_ERROR);
        }
        // We are working to get everything up and running as quickly as possible.
        if ($this->http->FindNodes("//h1[contains(text(), 'We are working to get everything up')]")) {
            throw new CheckException("Navy Federal Online® Account Access is temporarily unavailable.
We apologize for the inconvenience and thank you for your patience.", ACCOUNT_PROVIDER_ERROR);
        }
        // Navy Federal Online Account Access is temporarily unavailable
        if ($message = $this->http->FindSingleNode("(//p[contains(text(), 'Navy Federal Online Account Access is temporarily unavailable')])[1]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // We are experiencing processing problems. Please try again later.
        if ($message = $this->http->FindSingleNode("
                //h2[contains(text(), 'We are experiencing processing problems. Please try again later.')]
                | //p[contains(text(), 'We experienced a technical issue. Please call us at 1-888-842-6328 for assistance.')]
            ")
        ) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
        $res = $this->waitForElement(WebDriverBy::xpath('//section[contains(@class, "warning")] | //h1[@id = "AcctSummaryTitle"] | //p[@id = "radioEmailLabel"] | //h1[contains(text(), "Access Denied")] | //h2[contains(text(), "Current Balance")] | //p[contains(text(), "We\'re conducting regularly scheduled maintenance and will be back up again in no time.")]'), 15);
        $this->saveResponse();

        if ($res && (strstr($res->getText(), 'Account Summary') || strstr($res->getText(), 'Current Balance'))) {
            return true;
        }

        if ($message = $this->http->FindSingleNode('(//section[contains(@class, "warning")])[1]')) {
            $this->logger->debug("[Error]: {$message}");
            $message = preg_replace("/(?:^alert\s*|\s*Close Alert\s*$)/", "", $message);

            if (strstr($message, 'The username or password you entered was incorrect.')) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            if (strstr($message, "You've exceeded the maximum number of sign in attempts and will need to")) {
                throw new CheckException($message, ACCOUNT_LOCKOUT);
            }

            $this->DebugInfo = $message;

            return false;
        }

        if ($email = $this->waitForElement(WebDriverBy::xpath('//p[@id = "radioEmailLabel"]'), 0)) {
            $email->click();

            $button = $this->waitForElement(WebDriverBy::xpath('//button[contains(text(), "Send") and not(contains(@class, "button-disabled"))]'), 3);
            $this->saveResponse();

            if (!$button) {
                return false;
            }

            $button->click();

            return $this->processSecurityCheckpoint();
        }

        if ($message = $this->http->FindSingleNode('//h1[contains(text(), "Access Denied")]')) {
            $this->DebugInfo = $message;
        }

        if ($message = $this->http->FindSingleNode('//p[contains(text(), "We\'re conducting regularly scheduled maintenance and will be back up again in no time.")]')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function processSecurityCheckpoint()
    {
        $this->logger->notice(__METHOD__);
        $this->logger->info('Security Question', ['Header' => 3]);

        $q = $this->waitForElement(WebDriverBy::xpath('//p[contains(@class, "select-subheading") and contains(., "We sent")]'), 10);

        if ($q) {
            $this->driver->executeScript("document.querySelector('#otp').style.zIndex = '100003';");
        }

        $otp = $this->waitForElement(WebDriverBy::xpath('//input[@id = "otp"]'), 0);
        $this->saveResponse();

        if (!$q || !$otp) {
            return false;
        }

        $question = $q->getText();

        if ($question && !isset($this->Answers[$question])) {
            $this->holdSession();
            $this->AskQuestion($question, null, "Question2fa");

            return false;
        }

        $code = $this->Answers[$question];
        unset($this->Answers[$question]);

        $otp->clear();
        $otp->sendKeys($code);
        $button = $this->waitForElement(WebDriverBy::xpath('//button[contains(text(), "Submit") and not(contains(@class, "button-disabled"))]'), 3);
        $this->saveResponse();

        $button->click();

        // The code you entered was incorrect.
        $error = $this->waitForElement(WebDriverBy::xpath('//p[@id = "bannerText"]'), 5);
        $this->saveResponse();

        if ($error) {
            $this->logger->error("resetting answers");
            $message = preg_replace("/(?:^alert\s*|\s*Close Alert\s*$)/", "", $error->getText());
            $this->AskQuestion($question, $message, "Question2fa");

            return false;
        }

        $buttonRemember = $this->waitForElement(WebDriverBy::xpath('//button[contains(text(), "Remember Browser")]'), 0);

        if (!$buttonRemember) {
            return false;
        }

        $buttonRemember->click();

        $res = $this->waitForElement(WebDriverBy::xpath('//h1[@id = "AcctSummaryTitle"] | //h2[contains(text(), "Current Balance")] | //p[contains(text(), "We experienced a technical problem and can’t sign you in at the moment.")] | //a[@aria-labelledby="skip-tour"] | //h2[contains(text(), "Credit Cards")]'), 15);
        $this->saveResponse();

        if ($res && strstr($res->getText(), 'Skip This Tour')) {
            $res->click();
            $res = $this->waitForElement(WebDriverBy::xpath('//h1[@id = "AcctSummaryTitle"] | //h2[contains(text(), "Current Balance")] | //p[contains(text(), "We experienced a technical problem and can’t sign you in at the moment.")] | //h2[contains(text(), "Credit Cards")]'), 15);
            $this->saveResponse();
        }

        if ($res && strstr($res->getText(), 'We experienced a technical problem and can’t sign you in at the moment.')) {
            throw new CheckException($res->getText(), ACCOUNT_PROVIDER_ERROR);
        }

        $this->logger->debug("success");

        return true;
    }

    public function ProcessStep($step)
    {
        $this->logger->notice(__METHOD__);

        if ($step == "Question2fa") {
            return $this->processSecurityCheckpoint();
        }

        return false;
    }

    public function Parse()
    {
        $links = array_unique($this->http->FindNodes("//div[@id = 'account-summary-credit-table']//a[contains(@href, 'NFCU/accounts/activity?accountGlobalId')]/@href"));
        $this->logger->debug("Total unique links found: " . count($links));
        // other design
        if (count($links) == 0
            && $this->http->FindPreg("/<title>Navy Federal Credit Union eCollections<\/title>/ims")
            && $this->http->FindPreg("/frame src=\"overview\.do\"/ims")) {
            $this->logger->notice(">>> New user: other design");
            $this->http->GetURL("https://my.navyfederal.org/eCollectionsMemberUIWeb/overview.do");

            // Your account(s) is currently in a delinquent status.
            if ($message = $this->http->FindSingleNode("//b[contains(text(), 'Your account(s) is currently in a delinquent status.')]")) {
                $this->http->GetURL("https://myaccounts.navyfederal.org/NFCU/accounts/accountsummary");

                // todo: if this not working then see "if ($this->http->FindPreg("/Introducing 2-Step Verification/")) {"
                if (
                    strstr($this->http->currentUrl(), 'core/twostepverification')
                    && $this->http->FindSingleNode('//button[contains(@id, "RemindMeBtn")]')
                ) {
                    $this->sendNotification("navy - debug // RR");
                    $this->logger->notice("Skip 2fa enabling");
                    $this->http->PostURL("https://myaccounts.navyfederal.org/NFCU/core/updatetwostepverificationstate", [
                        "twoStepAuthState"           => "NeverUsed",
                        "remindMe"                   => "true",
                        "__RequestVerificationToken" => $this->http->FindSingleNode('(//input[@name = "__RequestVerificationToken"]/@value)[1]'),
                    ]);
                    $this->http->GetURL("https://myaccounts.navyfederal.org/NFCU/accounts/accountsummary");
                }

                $links = array_unique($this->http->FindNodes("//div[@id = 'account-summary-credit-table']//a[contains(@href, 'NFCU/accounts/activity?accountGlobalId')]/@href"));
            } else {
                $otherLinks = array_unique($this->http->FindNodes("//a[contains(@href, '/eCollectionsMemberUIWeb/initPayment.do')]/@href"));
                $links = [];

                foreach ($otherLinks as $link) {
                    $this->http->NormalizeURL($link);
                    $links[] = $link;
                }
            }
            $this->logger->debug("Total unique links found: " . count($links));
//            if (count($links) > 0)
//                $this->SetBalanceNA();
        }
        // Name
        $this->SetProperty("Name", beautifulName($this->http->FindSingleNode("//span[@class = 'name-text']")));

        $ficoScoreLink = $this->http->FindSingleNode("//a[@id = 'CreditScoreLink' and @data-credit-score-enrolled = 'True']/@data-get-credit-score-url");

        $subAccounts = [];
        $this->http->ParseMetaRedirects = false;

        foreach ($links as $link) {
            $this->http->NormalizeURL($link);
            $this->http->GetURL($link);

            $balance = $this->http->FindSingleNode("//div[h4[contains(text(), 'Rewards')]]/following-sibling::div"); //h4[contains(@class, 'availAwardsBal')]
            $title = $this->http->FindSingleNode("//div[contains(@class, 'AccWrapper')]/div[1]/h1");
            $code = $this->http->FindSingleNode('//div[contains(@class, "AccWrapper")]/div[1]/div[contains(@class, "account-number")]', null, false, '/\-\s*(\d+)/ims');
            $title .= " (ending in {$code})";
            $this->logger->info("{$title}", ['Header' => 3]);

            if (isset($balance) && isset($title) && isset($code)) {
                if (strstr($balance, '$')) {
                    $currency = '$';
                } else {
                    $currency = 'points';
                }
                $balance = str_replace('$', '', $balance);
                $subAccounts[] = [
                    "Code"        => 'navy' . $code,
                    "DisplayName" => $title,
                    "Balance"     => $balance,
                    "Currency"    => $currency,
                ];
                // Detected cards
                $this->AddDetectedCard([
                    "Code"            => 'navy' . $code,
                    "DisplayName"     => $title,
                    "CardDescription" => C_CARD_DESC_ACTIVE,
                ]);
            }// if (isset($balance) && isset($title) && isset($code))
            elseif (isset($title) && isset($code) && !empty($this->Properties['Name'])
                && !$this->http->FindPreg("/Rewards Redemption/ims")) {
                // Detected cards
                $this->AddDetectedCard([
                    "Code"            => 'navy' . $code,
                    "DisplayName"     => $title,
                    "CardDescription" => C_CARD_DESC_DO_NOT_EARN,
                ]);
                $this->SetBalanceNA();
            }
        }// foreach ($links as $link)

        if (count($subAccounts) > 0) {
            $this->SetBalanceNA();
            $this->SetProperty("SubAccounts", $subAccounts);
        }// if (count($subAccounts) > 0)

        if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR) {
            if (!empty($this->Properties['Name'])) {
                $otherSections =
                    $this->http->FindSingleNode("//h2[contains(text(), 'Savings')]")
                    ?? $this->http->FindSingleNode("//h2[contains(text(), 'Checking')]")
                ;

                if ($otherSections
                    || $this->http->FindSingleNode("//td[contains(text(), 'You have no accounts to display.')]")) {
                    $this->SetBalanceNA();
                }
            }// if (!empty($this->Properties['Name']))
            // You currently do not have any accounts available in Account Access
            if ($message = $this->http->FindSingleNode("//h3[contains(text(), 'You currently do not have any accounts available in Account Access.')]")) {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }
            // Your credit card information is temporarily unavailable
            if ($message = $this->http->FindSingleNode("//p[contains(text(), 'Your credit card information is temporarily unavailable')]")) {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }
            // Navy Federal Online Account Access is temporarily unavailable.
            if ($message = $this->http->FindSingleNode("//div[contains(text(), 'Navy Federal Online Account Access is temporarily unavailable.')]")) {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }
            // The Account Access Service is temporarily unavailable. We apologize for the inconvenience.
            if ($message = $this->http->FindSingleNode("//p[contains(text(), 'The Account Access Service is temporarily unavailable. We apologize for the inconvenience.')]")) {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }
            // Update Your Information
            if ($this->http->currentUrl() == 'https://myaccounts.navyfederal.org/NFCU/settings/informationupdate') {
                throw new CheckException("Navy Federal Credit Union website is asking you to update your profile, until you do so we would not be able to retrieve your account information.", ACCOUNT_PROVIDER_ERROR);
            }
        }//if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR)

        $this->parseFICO($ficoScoreLink);
    }

    public function parseFICO($ficoScoreLink)
    {
        $this->logger->notice(__METHOD__);
        $this->logger->info('FICO® Score', ['Header' => 3]);

        if (!$ficoScoreLink) {
            $this->logger->notice("FICO link not found");

            return false;
        }
        $this->http->NormalizeURL($ficoScoreLink);
        $this->http->GetURL($ficoScoreLink);
        // Your FICO® Score is ...
        $fcioScore = $this->http->FindSingleNode("//div[@class = 'actual-score']");
        // FICO Score updated on
        $fcioUpdatedOn = $this->http->FindSingleNode("//div[@id = 'UpdatedDate']", null, true, "/UPDATED\s*(.+)/ims");
        // refs #14491
        if ($fcioScore && $fcioUpdatedOn) {
            if (isset($this->Properties['SubAccounts']) && count($this->Properties['SubAccounts']) == 1) {
                foreach ($this->Properties['SubAccounts'][0] as $key => $value) {
                    if (in_array($key, ['Code', 'DisplayName'])) {
                        continue;
                    } elseif ($key == 'Balance') {
                        $this->SetBalance($value);
                    } elseif ($key == 'ExpirationDate') {
                        $this->SetExpirationDate($value);
                    } else {
                        $this->SetProperty($key, $value);
                    }
                }// foreach ($this->Properties['SubAccounts'][0] as $key => $value)
                unset($this->Properties['SubAccounts']);
            }// if (isset($this->Properties['SubAccounts']) && count($this->Properties['SubAccounts']) == 1)
            $this->SetProperty("CombineSubAccounts", false);
            $this->AddSubAccount([
                "Code"               => "navyFICO",
                "DisplayName"        => "FICO® Bankcard Score 9 (Equifax)",
                "Balance"            => $fcioScore,
                "FICOScoreUpdatedOn" => $fcioUpdatedOn,
            ]);
        }// if ($fcioScore && $fcioUpdatedOn)

        return true;
    }
}
