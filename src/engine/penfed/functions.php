<?php

class TAccountCheckerPenfed extends TAccountChecker
{
    public static function GetAccountChecker($accountInfo)
    {
        require_once __DIR__ . "/TAccountCheckerPenfedSelenium.php";

        return new TAccountCheckerPenfedSelenium();
    }

    public static function FormatBalance($fields, $properties)
    {
        if (isset($properties['Currency']) && ($properties['Currency'] == '$')) {
            return formatFullBalance($fields['Balance'], $fields['ProviderCode'], "$%0.2f");
        }

        return formatFullBalance($fields['Balance'], $fields['ProviderCode'], "");
    }

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->GetURL("https://home.penfed.org/s/member-login?flowId=TrPSP");

        $jsc = $this->http->FindSingleNode("//input[@id = 'user_prefs']/@value");
        $hdm = $this->http->FindSingleNode("//input[@id = 'user_prefs2']/@value");

        if (!$jsc || !$hdm) {
            return $this->checkErrors();
        }

        $this->http->FormURL = 'https://commauth.penfed.org/pf-ws/authn/flows/Y1IGv?action=checkUsernamePasswordWithJscHdmPayload';
        $this->http->SetInputValue('username', $this->AccountFields['Login']);
        $this->http->SetInputValue('password', $this->AccountFields['Pass']);
        $this->http->SetInputValue('jsc', $jsc);
        $this->http->SetInputValue('hdm', $hdm);

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);
        // The PenFed Platinum Rewards site is temporarily unavailable due to site maintenance
        if ($message = $this->http->FindSingleNode("//span[contains(text(), 'PenFed Online is down for scheduled maintenance.')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // Some of PenFed's systems are currently down for scheduled maintenance.
        if ($message = $this->http->FindSingleNode('
                //h3[contains(text(), "Some of PenFed\'s systems are currently down for scheduled maintenance.")]
                | //h2[contains(text(), "Some of PenFed\'s systems are currently down for maintenance.")]
            ')
        ) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // An error has occurred while attempting to process your request on our website.
        if ($message = $this->http->FindSingleNode('//h3[contains(text(), "An error has occurred while attempting to process your request on our website.")]')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // Server Error in '/' Application.
        if (
            $this->http->FindPreg("/(Server Error in \'\/\' Application)/ims")
            || $this->http->FindSingleNode("//h1[contains(text(), 'Service Unavailable - DNS failure')]")
        ) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        // retries - The page you have requested was not found on the Pentagon Federal Credit Union Website.
        if ($message = $this->http->FindSingleNode('//h3[contains(text(), "The page you have requested was not found on the Pentagon Federal Credit Union Website.")]')) {
            throw new CheckRetryNeededException(3, 10);
        }

        return false;
    }

    public function Login()
    {
        if (!$this->http->PostForm()) {
            return $this->checkErrors();
        }
        $response = $this->http->JsonLog();

        if (isset($response->status) && $response->status == 'REFRENCE_ID_REQUIRED') {
            $this->http->GetURL("https://home.penfed.org/s/multi-factor-auth?REF={$response->pickupReferenceId}&flowId={$response->id}");
        }

        $this->checkErrors();
        $this->http->FilterHTML = false;
        $this->handleRedirect();
        $this->http->FilterHTML = true;

        if (strstr($this->http->currentUrl(), 'LogonChallengeQuestion') && $this->parseQuestion()) {
            return false;
        }

        if (!$this->sendPassword()) {
            return false;
        }

        if (
            strstr($this->http->currentUrl(), 'OTPSelect.aspx')
            && $this->http->FindSingleNode("//div[contains(text(), 'For your security, we need to verify your identity.')]")
            && (
                $this->http->FindSingleNode("//span[@id = 'ctl00_MainContentPlaceHolder_lblEmail1']")
                || $this->http->FindSingleNode("//span[@id = 'ctl00_MainContentPlaceHolder_lblPhoneNumber1']")
            )
        ) {
            $this->parse2FA();

            return false;
        }

        // check for invalid login
        if ($message = $this->http->FindSingleNode("//span[@class='errorheader']//li[1]")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        if ($message = $this->http->FindSingleNode("//span[@id = 'ctl00_MainContentPlaceHolder_lblErrorDescription' and contains(text(), 'Your online access is currently blocked.')]")) {
            throw new CheckException($message, ACCOUNT_LOCKOUT);
        }
        // Your request did not complete. Please try logging on again. If the problem still persists, please see the notes below.
        if ($this->http->FindPreg("/Your request did not complete\. Please try logging on again\. If the problem still persists, please see the notes below\./")) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        if ($this->http->FindPreg("/To ensure the safety of your account you are required to update your username and password\. Please click the 'Let's Get Started' button to begin the re-enrollment process\./")) {
            $this->throwProfileUpdateMessageException();
        }

        $queryStrings = parse_url($this->http->currentUrl(), PHP_URL_QUERY);

        if (!empty($queryStrings)) {
            $queryStrings = explode('&', $queryStrings);
            $this->logger->debug(var_export($queryStrings, true), ['pre' => true]);

            foreach ($queryStrings as $queryString) {
                [$name, $value] = explode('=', $queryString);

                if ($name == 'NtlrMsg') {
                    $message = urldecode($value);
                    $this->logger->error($message);

                    if ($message == "We're sorry. The username and password you have entered do not match. Please try again.") {
                        throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
                    }

                    if ($message == "Your account is locked. Unlock account") {
                        throw new CheckException($message, ACCOUNT_LOCKOUT);
                    }

                    break;
                }
            }
        }

        return true;
    }

    public function parseQuestion()
    {
        $this->logger->notice(__METHOD__);
        $this->logger->info('parseQuestion', ['Header' => 3]);
        $question = $this->http->FindSingleNode("//span[@id = 'ctl00_ctl00_MainContentPlaceHolder_cphSecurityMainContent_lblChallengeQuestion']");

        if (!isset($question)) {
            return true;
        }

        if (!$this->http->ParseForm("aspnetForm")) {
            return false;
        }
        $this->Question = $question;
        $this->ErrorCode = ACCOUNT_QUESTION;
        $this->Step = "Question";

        return true;
    }

    public function parse2FA()
    {
        $this->logger->notice(__METHOD__);
        $this->logger->info('2FA', ['Header' => 3]);
        $email = $this->http->FindSingleNode("//span[@id = 'ctl00_MainContentPlaceHolder_lblEmail1']");
        $value = $this->http->FindSingleNode('//span[@id = "ctl00_MainContentPlaceHolder_lblEmail1"]/ancestor::tr[@id = "ctl00_MainContentPlaceHolder_trEmail1"]//input[@name = "ctl00$MainContentPlaceHolder$rdoSelection"]/@value');

        $phone = $this->http->FindSingleNode("//span[@id = 'ctl00_MainContentPlaceHolder_lblPhoneNumber1']");
        $phoneValue = $this->http->FindSingleNode('//span[@id = "ctl00_MainContentPlaceHolder_lblPhoneNumber1"]/ancestor::tr[@id = "ctl00_MainContentPlaceHolder_trPhone1"]//input[@id = "ctl00_MainContentPlaceHolder_rdoPhone1Text"]/@value');

        if (
            (
                (!isset($email) || !$value)
                && (!isset($phone) || !$phoneValue)
            )
            && !$this->http->ParseForm("aspnetForm")
        ) {
            return false;
        }

        // prevent code spam    // refs #6042
        if ($this->isBackgroundCheck()) {
            $this->Cancel();
        }

        if ($email && $value) {
            $this->http->SetInputValue('ctl00$MainContentPlaceHolder$rdoSelection', $value);
        } else {
            $this->http->SetInputValue('ctl00$MainContentPlaceHolder$rdoSelection', $phoneValue);
        }
        $this->http->SetInputValue('ctl00$MainContentPlaceHolder$btnContinue.x', '0');
        $this->http->SetInputValue('ctl00$MainContentPlaceHolder$btnContinue.y', '0');
        $this->http->SetInputValue('__SCROLLPOSITIONX', '0');
        $this->http->SetInputValue('__SCROLLPOSITIONY', '0');
        $this->http->SetInputValue('ctl00$MainContentPlaceHolder$rdoAccountType', 'Personal');
//        $this->http->SetInputValue('ctl00$hdnHoverInd', '1');
        if (!$this->http->PostForm()) {
            return false;
        }

        if (
            !$this->http->ParseForm("aspnetForm")
            || !$this->http->FindSingleNode('//b[contains(text(), "Please enter the one-time passcode you received via ")]')
        ) {
            return false;
        }

        if ($email && $value) {
            $question = "Please enter the one-time passcode which was sent to the following email address: {$email}. Please note that you must provide the latest code that was just sent to you. Previous codes will not work.";
        } elseif ($phone && $phoneValue) {
            $question = "Please enter the one-time passcode which was sent to the following phone number: {$phone}. Please note that you must provide the latest code that was just sent to you. Previous codes will not work.";
        } else {
            return false;
        }
        $this->Question = $question;
        $this->ErrorCode = ACCOUNT_QUESTION;
        $this->Step = "2FA";

        return true;
    }

    public function handleRedirect()
    {
        $this->logger->notice("handleRedirect");

        if ($redirect = $this->http->FindPreg("/href=\"([^\"]+)\">Please click here to continue</ims")) {
            $this->logger->debug("Redirect to -> " . $redirect);
            $this->http->NormalizeURL($redirect);
            $this->http->GetURL($redirect);
        }
    }

    public function ProcessStep($step)
    {
        $this->logger->notice("ProcessStep. Security Question");
        $this->logger->info('ProcessStep', ['Header' => 3]);

        if ($step == '2FA') {
            $this->http->SetInputValue('ctl00$MainContentPlaceHolder$txtPasscode', $this->Answers[$this->Question]);
            $this->http->SetInputValue('ctl00$MainContentPlaceHolder$btnContinue.x', '0');
            $this->http->SetInputValue('ctl00$MainContentPlaceHolder$btnContinue.y', '0');
            unset($this->Answers[$this->Question]);
            $this->http->FilterHTML = false;

            if (!$this->http->PostForm()) {
                return false;
            }

            if ($error = $this->http->FindSingleNode('//span[contains(text(), "Passcode entered was incorrect. Please try again.")]')) {
                $this->AskQuestion($this->Question, $error, '2FA');

                return false;
            }

            return true;
        }

        $this->http->SetInputValue('ctl00$ctl00$MainContentPlaceHolder$cphSecurityMainContent$txtAnswer', $this->Answers[$this->Question]);
        $this->http->SetInputValue('ctl00$ctl00$MainContentPlaceHolder$cphSecurityMainContent$rdoAccountType', "Personal");
        $this->http->SetInputValue('ctl00$ctl00$MainContentPlaceHolder$imgNextSection.x', "0");
        $this->http->SetInputValue('ctl00$ctl00$MainContentPlaceHolder$imgNextSection.y', "0");

        if (!$this->http->PostForm()) {
            return false;
        }
        // The answer you entered is incorrect.
        if ($this->http->FindSingleNode("//span[contains(text(), 'The answer you entered is incorrect')]")
            || $this->http->FindPreg("/Please enter letters and numbers only/ims")) {
            $this->parseQuestion();

            return false;
        }

        if (!$this->sendPassword()) {
            return false;
        }

        return true;
    }

    public function sendPassword()
    {
        $this->logger->notice("sendPassword");
        $this->logger->info('sendPassword', ['Header' => 3]);
        $this->http->FilterHTML = false;

        if (!$this->http->ParseForm("aspnetForm")) {
            return $this->checkErrors();
        }
        $this->http->SetInputValue('ctl00$ctl00$MainContentPlaceHolder$cphSecurityMainContent$txtPassword', $this->AccountFields['Pass']);
        $this->http->SetInputValue('ctl00$ctl00$MainContentPlaceHolder$imgLogon.x', '0');
        $this->http->SetInputValue('ctl00$ctl00$MainContentPlaceHolder$imgLogon.y', '0');

        if (!$this->http->PostForm()) {
            return false;
        }
        $currentURL = $this->http->currentUrl();
        $this->logger->debug("[Current URL]: {$currentURL}");
        // INVALID PASSWORD
        $this->CheckError($this->http->FindPreg("/(INVALID PASSWORD)/ims"));

        if (strstr($currentURL, 'https://www.penfed.org/login?NtlrMsg=INVALID+PASSWORD&')) {
            $this->CheckError("INVALID PASSWORD");
        }
        // ERROR -- USERNAME DOES NOT EXIST
        $this->CheckError($this->http->FindPreg("/(USERNAME DOES NOT EXIST)/ims"));

        if (strstr($currentURL, 'https://www.penfed.org/login?NtlrMsg=USERNAME+DOES+NOT+EXIST&')) {
            $this->CheckError("USERNAME DOES NOT EXIST");
        }
        // Please enter a valid username.
        if (strstr($currentURL, 'https://www.penfed.org/login?NtlrMsg=Please+enter+a+valid+username.&')) {
            $this->CheckError("Please enter a valid username.");
        }
        // Password must contain at least one number
        if ($this->http->FindPreg("/(Must contain at least one number)/ims")) {
            $this->CheckError("Password must contain at least one number");
        }
        // Must be at least 6 characters long
        if ($this->http->FindPreg("/(Must be at least 6 characters long)/ims")) {
            $this->CheckError("Password must be at least 6 characters long");
        }

        if ($this->http->FindPreg("/(You are required to change the password\.)/ims")) {
            $this->CheckError("You are required to change the password.", ACCOUNT_LOCKOUT);
        }
        // PSWD LOCK, CALL 1-800-247-5626
        $this->CheckError($this->http->FindPreg("/(PSWD LOCK, CALL 1-800-247-5626)/ims"), ACCOUNT_LOCKOUT);

        if (strstr($currentURL, 'https://www.penfed.org/login?NtlrMsg=PSWD+LOCK%2c+CALL+1-800-247-5626&')) {
            $this->CheckError("PSWD LOCK, CALL 1-800-247-5626", ACCOUNT_LOCKOUT);
        }
        // Your account is locked.
        if (strstr($currentURL, 'https://www.penfed.org/login?NtlrMsg=Your+account+is+locked.+Unlock+account&Referrer=')) {
            $this->CheckError("Your account is locked.", ACCOUNT_LOCKOUT);
        }
        // We were unable to authenticate your one-time passcode and your account has been blocked.
        $this->CheckError($this->http->FindPreg("/We were unable to authenticate your one-time passcode and your account has been blocked\./"), ACCOUNT_LOCKOUT);
        // Our online system is currently experiencing technical problems.
        $this->CheckError($this->http->FindSingleNode("//span[contains(text(), 'Our online system is currently experiencing technical problems.')]"), ACCOUNT_PROVIDER_ERROR);
        // PenFed Online is down for scheduled maintenance.
        $this->CheckError($this->http->FindSingleNode("//span[contains(text(), 'PenFed Online is down for scheduled maintenance.')]"), ACCOUNT_PROVIDER_ERROR);
        // We apologize for the inconvenience. Due to technical problems, PenFed Online is temporarily unavailable.
        $this->CheckError($this->http->FindSingleNode("//span[contains(text(), 'We apologize for the inconvenience. Due to technical problems, PenFed Online is temporarily unavailable.')]"), ACCOUNT_PROVIDER_ERROR);
        // You have no active accounts on PenFed Online.
        $this->CheckError($this->http->FindSingleNode("//span[contains(text(), 'You have no active accounts on PenFed Online.')]"), ACCOUNT_PROVIDER_ERROR);
        // Must contain only lower-case or capitalized letters, numbers, or these characters: !, @, $, *.  No spaces are allowed.
        if ($message = $this->http->FindPreg("/(Must contain only lower-case or capitalized letters, numbers, or these characters:)/ims")) {
            throw new CheckException("Password must contain only lower-case or capitalized letters, numbers, or these characters: !, @, $, *. No spaces are allowed.", ACCOUNT_INVALID_PASSWORD);
        }

        return true;
    }

    public function Parse()
    {
        // Name
        $this->SetProperty("Name", beautifulName($this->http->FindSingleNode('//h1[contains(text(), "WELCOME,")]', null, true, "/Welcome,\s*([^\|]+)/ims")));

        // parse properties
        $baseHrefs = array_unique($this->http->FindNodes("//a[contains(@id, 'lnkRedeemRewards')]/@href"));
        $this->logger->debug("Total " . count($baseHrefs) . " links were found");

        foreach ($baseHrefs as $baseHref) {
            $acc = $this->parseAccount($baseHref);
            $this->AddSubAccount($acc);
        }
        // Set SubAccounts Properties
        if (!empty($this->Properties['SubAccounts'])) {
            $this->SetBalanceNA();
        }

        if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR) {
            $this->CheckError($this->http->FindPreg("/>(The Rewards System is Temporarily Down for Enhancements<br \/>We are performing system enhancements on our site\. Thank you for your patience\.<br \/>We will be back online shortly\.)<\/h2>/"), ACCOUNT_PROVIDER_ERROR);

            if (($this->http->FindSingleNode("//img[contains(@alt, 'Activate Credit Card')]/@alt")
                || $this->http->FindNodes("//img[contains(@alt, 'Go Paperless')]/@alt"))
                && !$this->http->FindPreg("/Temporarily Down/")
                && !empty($this->Properties['Name'])
                // <table width='100%' border='0' cellspacing='0' cellpadding='0' class='account-summary'>
                && (!empty($acc) || count($baseHrefs) == 0 && (count($this->http->FindNodes("//a[
                            normalize-space(text()) = 'Regular Savings'
                            or contains(text(), 'PENFED_SAVINGS')
                            or contains(text(), '529BACKUP')
                            or contains(text(), 'PENFED SAVINGS')
                            or contains(text(), 'PEDFED BANK ACCT')
                            or contains(text(), 'CHECKING ACCOUNT')
                            or contains(text(), 'PEN FED CHECKING')
                            or contains(text(), '_Checking')
                            or contains(text(), ' CHECKINGS')
                            or contains(text(), 'PRIMARY CHECKING')
                            or normalize-space(text()) = 'Checking'
                            or normalize-space(text()) = 'SAVINGS'
                            or normalize-space(text()) = 'Savings'
                            or normalize-space(text()) = 'SHARE SAVINGS'
                            or normalize-space(text()) = 'SAVINGS ACCOUNT'
                            or normalize-space(text()) = 'MAIN CHECKING'
                        ]/@href
                        | //label[contains(text(), 'Regular Savings') and contains(text(), '***3016')]
                        | //label[contains(text(), 'Regular Savings') and contains(text(), '***3016')]
                        | //label[contains(text(), 'MONEY') and contains(text(), '***4016')]
                        ")) == 1 // AccountID: 2465005, 3112665, 1132634, 1199113, 1181602, 3302413, 4013768, 2428698
                        || count($this->http->FindNodes("//a[
                            normalize-space(text()) = 'Regular Savings'
                            or contains(text(), ' - savings')
                        ]")) > 1// AccountID: 2449244
                        || $this->http->FindSingleNode('//div[@class = "module-header"]/span[@class = "important-message" and contains(., "Are you missing accounts on this list?")]')// AccountID: 2515079
                    )
                )
            ) {
                $this->SetBalanceNA();
            }
        }// if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR)
    }

    private function parseAccount($baseHref)
    {
        $browser = clone $this->http;
        $this->http->brotherBrowser($browser);

        $browser->NormalizeURL($baseHref);
        $browser->GetURL($baseHref);
        $authorization = $this->http->FindPreg("/access_token=([^&]+)/", false, $browser->currentUrl());

        if (!$authorization) {
            if ($browser->ParseForm("form1")) {
                $browser->PostForm();

                // may be wrong error
                if ($browser->ParseForm("form1")) {
                    $error = $browser->FindSingleNode('//span[@id="lblError" and @class="ErrorMessage" and contains(normalize-space(text()), "The Rewards Website is temporarily unavailable. Please try back at a later time.")]');
                    $this->CheckError($error, ACCOUNT_PROVIDER_ERROR);
                }

                $this->sendNotification("old version was found // RR");

                // Card/Account #
                $browser->GetURL("https://rewards.penfed.org/Point/Balance");
                $number = $browser->FindSingleNode("//td[a[@class = 'epsi-Underline']]/following-sibling::td[1]");
                $displayName = $browser->FindSingleNode("//td[contains(text(), '$number')]/preceding-sibling::td/a");
                $detectedCards = [
                    'Code'            => 'penfed' . $number,
                    'DisplayName'     => "{$displayName} ending in {$number}",
                    "CardDescription" => C_CARD_DESC_DO_NOT_EARN,
                ];
                $this->logger->notice("parseAccount  " . $number);
                $balance = $browser->FindSingleNode("//a[@id = 'point-balance-header-link']", null, true, "/:\s*([^<]+)/ims");

                if (!isset($balance)) {
                    $this->AddDetectedCard($detectedCards);

                    return [];
                }

                if (strstr($balance, '$')) {
                    $this->sendNotification("check balances - refs #21681 // RR");
                }

                $detectedCards['CardDescription'] = C_CARD_DESC_ACTIVE;
                $this->AddDetectedCard($detectedCards);

                $subAccount = [
                    'Code'        => 'penfed' . $number,
                    'DisplayName' => $displayName . ' ' . $number,
                    // Balance - Point Balance
                    'Balance'     => $balance,
                    // Card/Account #
                    "Number"      => $number,
                    "Currency"    => strstr($balance, '$') ? '$' : null,
                ];
                // Point Expiration / Cash Rewards Expiration
                $expNodes = $browser->XPath->query("//div[contains(text(), ' Expiration')]/following-sibling::div[1]/table//tr[td]");
                $this->logger->debug("Total {$expNodes->length} exp nodes were found");

                for ($i = 0; $i < $expNodes->length; $i++) {
                    // Expiring balance
                    $subAccount["ExpiringBalance"] = $browser->FindSingleNode("td[1]", $expNodes->item($i));
                    $exp = $browser->FindSingleNode("td[2]", $expNodes->item($i));

                    if ($exp = strtotime($exp)) {
                        $subAccount['ExpirationDate'] = $exp;

                        break;
                    }// if ($exp = strtotime($exp))
                }// for ($i = 0; $i < $expNodes->length; $i++)

                return $subAccount;
            }

            return [];
        }
        $browser->setDefaultHeader("Authorization", "Bearer {$authorization}");

        $browser->GetURL("https://api.meridianloyalty.com/v1/loyalty/loyalty/api/point-summary/summarydetails");
        $response = $browser->JsonLog();

        if (count($response) > 1) {
            $this->sendNotification("need to check card balance");

            return [];
        }
        // refs #20107
        $displayName = $response[0]->mediumDescription;
        // Card/Account #
        $number = $response[0]->last4OfAccount ?? null;
        $detectedCards = [
            'Code'            => 'penfed' . $number,
            'DisplayName'     => "{$displayName} ending in {$number}",
            "CardDescription" => C_CARD_DESC_DO_NOT_EARN,
        ];
        $this->logger->notice("parseAccount  " . $number);
        $balance = $response[0]->redeemablePoints ?? null;

        if (!isset($balance)) {
            $this->AddDetectedCard($detectedCards);

            return [];
        }

        $detectedCards['CardDescription'] = C_CARD_DESC_ACTIVE;
        $this->AddDetectedCard($detectedCards);

        if (strstr($displayName, 'Cash Rewards')) {
            $balance = $balance / 100;
        }

        $subAccount = [
            'Code'        => 'penfed' . $number,
            'DisplayName' => "{$displayName} ending in {$number}",
            // Balance - Point Balance
            'Balance'     => $balance,
            // Card/Account #
            "Number"      => $number,
            "Currency"    => strstr($balance, '$') || strstr($displayName, 'Cash Rewards') ? '$' : null,
        ];
        // Point Expiration / Cash Rewards Expiration
        $browser->GetURL("https://api.meridianloyalty.com/v1/loyalty/loyalty/api/point-summary/expiring");
        $expNodes = $browser->JsonLog();
        $this->logger->debug("Total " . count($expNodes) . " exp nodes were found");

        foreach ($expNodes as $expNode) {
            // Expiring balance
            $subAccount["ExpiringBalance"] = $expNode->POINTS;
            $exp = $expNode->EXPIRATION_DATE;

            if ($exp = strtotime($exp)) {
                $subAccount['ExpirationDate'] = $exp;

                break;
            }// if ($exp = strtotime($exp))
        }

        return $subAccount;
    }
}
