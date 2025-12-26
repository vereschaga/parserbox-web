<?php

use AwardWallet\Engine\ProxyList;

class TAccountCheckerVerizon extends TAccountChecker
{
    use ProxyList;

    /**
     * @var CaptchaRecognizer
     */
    private $recognizer;
    private $newDesign = false;

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->SetProxy($this->proxyReCaptcha());
    }

    public function GetRedirectParams($targetURL = null)
    {
        $arg = parent::GetRedirectParams($targetURL);
        $arg["CookieURL"] = "http://www.verizonwireless.com/";

        return $arg;
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->GetURL("https://www.verizonwireless.com/my-verizon/");
        $this->http->GetURL("https://login.verizonwireless.com/amserver/UI/Login?userNameOnly=false&mode=i&realm=vzw&customerType=DO");

        if (!$this->http->ParseForm("loginForm")) {
            return false;
        }
        $this->http->SetInputValue("IDToken1", $this->AccountFields['Login']);
        $this->http->SetInputValue("IDToken2", $this->AccountFields['Pass']);
        $this->http->SetInputValue("rememberUserNameCheckBoxExists", "Y");
        $this->http->SetInputValue("rememberUserName", "Y");

        return true;
    }

    public function Login()
    {
        $this->logger->debug('Auth Step: loginForm');

        if (!$this->http->PostForm()) {
            return $this->checkErrors();
        }

        if ($this->http->ParseForm("autosubmit")) {
            sleep(1);
            $this->logger->debug('Auth Step: autosubmit');
            $this->http->PostForm();
        }

        // AccountID: 4756951
        if ($this->http->FindSingleNode("//title[contains(text(), 'My Verizon is temporarily unavailable')]")) {
            $this->http->GetURL("https://login.verizonwireless.com/vzauth/UI/Login?userNameOnly=false&mode=i&realm=vzw&customerType=DO");

            if (!$this->http->ParseForm("loginForm")) {
                return false;
            }
            $this->http->FormURL = 'https://ssoauth.verizon.com/vzauth/UI/Login?realm=vzw&service=WlnOneVerizonChain&fromVZT=Y';
            $this->http->SetInputValue("IDToken1", $this->AccountFields['Login']);
            $this->http->SetInputValue("IDToken2", $this->AccountFields['Pass']);
            $this->http->SetInputValue("rememberUserNameCheckBoxExists", "Y");
            $this->http->SetInputValue("rememberUserName", "Y");

            if (!$this->http->PostForm()) {
                return $this->checkErrors();
            }

            if ($this->http->ParseForm("autosubmit")) {
                $this->http->FormURL = 'https://ssoauth.verizon.com/vzauth/UI/Login?realm=vzw&service=WlnOneVerizonChain&fromVZT=Y';
                sleep(1);
                $this->logger->debug('Auth Step: autosubmit');
                $this->http->PostForm();
            }
        }

        if ($this->parseQuestion()) {
            return false;
        }

        if ($message = $this->http->FindSingleNode('//p[contains(text(), "To prove that you\'re not a machine, please type the letters and/or numbers that appear below.")]')) {
            $this->throwProfileUpdateMessageException();
        }

        // The Email Address you entered is not in a valid format. Please enter a correct Email Address.
        if ($message = $this->http->FindSingleNode('//p[@id = "bannererror" and contains(., " you entered is not in a valid format. Please enter a correct Email Address.")]')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        $this->logger->debug('sendPassword');

        if (!$this->sendPassword()) {
            return $this->checkErrors();
        }

        if ($this->parseQuestion()) {
            return false;
        }

        $this->checkErrors();

        $this->logger->debug('save response before login = true check');
        $this->logger->debug('loggedIn debug:' . $this->http->getCookieByName("loggedIn", ".verizonwireless.com"));
        $this->http->SaveResponse();

        if ($this->http->FindSingleNode("(//a[contains(@href, 'Logout')]/@href)[1]")
            || $this->http->getCookieByName("RegistrationApp", ".verizon.com")
            || ($this->http->getCookieByName("loggedIn", ".verizon.com") == true)
            || ($this->http->getCookieByName("loggedIn", ".verizonwireless.com") == true)
            || $this->http->FindSingleNode("//h2[contains(text(), 'Account Number:')]/span")
            || $this->http->FindSingleNode("//b[contains(text(), 'Available Balance')]")
            || $this->http->FindSingleNode("//span[@class = 'ac-name']")
            || $this->http->FindPreg('#var\s*redirectUrl="https://myvpostpay.verizonwireless.com/ui/hub/secure/overview"#')) {
            return true;
        }

        return $this->checkErrors();
    }

    public function sendPassword()
    {
        $this->logger->notice(__METHOD__);
        // new design
        if ($this->http->ParseForm("myaccountForm")) {
            $this->newDesign = true;
            $this->logger->debug("New design");
            $this->http->PostForm();

            if ($this->http->ParseForm("oneVzPoster")) {
                $this->newDesign = false;
                $this->http->PostForm();
            }

            if (($querystring = $this->http->FindPreg("/Querystring = '([^']+)/ims")) || ($this->newDesign == false)
                && $this->http->currentUrl() != 'https://business.verizon.com/MyBusinessAccount/one.portal?_nfpb=true&_pageLabel=gb_overview') {
                $this->logger->notice("New design -> one more form");
                $this->http->PostURL('https://www.verizon.com/foryourhome/myaccount/ngen/pr/common/eprocess.aspx?' . $querystring, []);
            }// if ($this->http->ParseForm("frm"))
            elseif ($message = $this->http->FindSingleNode("//p[contains(text(), 'The Verizon page you are trying to reach is not available at this time.')]")) {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }
            // Verizon Services Transferred to Frontier Communications
            elseif ($message = $this->http->FindSingleNode("//h2[contains(text(), 'Verizon Services Transferred to Frontier Communications')]")) {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            } elseif (!strstr($this->http->currentUrl(), 'https://business.verizon.com/MyBusinessAccount/one.portal?_nfpb=true&_pageLabel=')) {
                $this->http->GetURL("https://www.verizon.com/foryourhome/myaccount/ngen/pr/home/myverizon.aspx");
            }
        }

        return true;
    }

    public function parseQuestion()
    {
        $this->logger->notice(__METHOD__);

        if ($this->http->FindSingleNode('
                //h2[contains(text(), "Sign In Request PIN")]
                | //h1[contains(text(), "Sign In Request PIN")]
                | //h1[contains(text(), "For your protection")]
                | //h1[contains(text(), "Next we\'ll check to make sure it\'s you.")]
                | //h1[contains(text(), "Select a verification option.")]
            ')
        ) {
            $this->logger->debug('Auth Step: Sign In Request PIN');

            // prevent code spam    // refs #6042
            if ($this->isBackgroundCheck()) {
                $this->Cancel();
            }

            $email = $this->http->FindSingleNode("//input[@name = 'IDToken1' and @value = 'E']/parent::label", null, true, "/Send me a One-Time PIN to my email address\s*(.+)\.\s/");

            if (!isset($email)) {
                $email = $this->http->FindSingleNode("//span[contains(text(), 'Send me a One-Time PIN to my email address')]", null, true, "/Send me a One-Time PIN to my email address\s*(.+)\.\s/");
                $phone = $this->http->FindSingleNode("//span[contains(text(), 'Send me a One-Time PIN via text message')]", null, true, "/text\s*message\s*@\s*([*\d.]+)\s*\./");
            }

            if (!isset($email)) {
                $email = $this->http->FindSingleNode("//input[@name = 'IDToken1' and @value = 'E']/parent::label", null, true, "/Email to ([^<]+)/");
                $phone = $this->http->FindSingleNode("//input[@name = 'IDToken1' and @value = 'S']/parent::label", null, true, "/(?:Text to|mobile device ending in) ([^<]+)/");
            }

            if (!isset($email) && !isset($phone)) {
                $email = $this->http->FindSingleNode("//input[@name = 'IDToken1' and not(@value = 'P')]/parent::label", null, true, "/Email to ([^<]+)/");
                $phone = $this->http->FindSingleNode("//input[@name = 'IDToken1' and @value = 'P']/parent::label", null, true, "/(?:Text to|mobile device ending in) ([^<]+)/");

                if (!$phone) {
                    $phone = $this->http->FindSingleNode("//input[@name = 'IDToken1' and not(@value = 'P')]/parent::label", null, true, "/(?:Text to|mobile device ending in) ([^<]+)/");
                }
            }

            if (!isset($email) && !isset($phone)) {
                return false;
            }

            if ($email) {
                $question = "Please enter Login PIN which was sent to the following email address: $email. Please note that you must provide the latest code that was just sent to you. Previous codes will not work.";
            } elseif ($phone) {
                $question = "Please enter Identification Code which was sent to the following phone number: $phone. Please note that you must provide the latest code that was just sent to you. Previous codes will not work.";
            }

            if (!$this->http->ParseForm("loginForm") || !isset($question)) {
                return false;
            }

            if ($this->http->InputExists("IDToken1")) {
                $this->http->SetInputValue("IDToken1", "E");
            }

            // ReCaptcha
            if ($key = $this->http->FindSingleNode("//form[@name= 'loginForm']//div[@class = 'g-recaptcha']/@data-sitekey")) {
                $captcha = $this->parseCaptcha($key);

                if ($captcha === false) {
                    return false;
                }

                $this->http->SetInputValue('g-recaptcha-response', $captcha);
            }

            $this->http->PostForm();

            if (!$this->http->ParseForm("loginForm")) {
                $this->logger->error("something went wrong, loginForm not found");
                // We are sorry, but you cannot complete your login at this time. Please try again later.
                if ($message = $this->http->FindSingleNode("//p[contains(text(), 'We are sorry, but you cannot complete your login at this time. Please try again later.')]")) {
                    throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
                }

                // todo: hard code for ONLY ONE account
                // AccountID: 4685409, 1996251
                if (($this->AccountFields['Login'] == 'edgarcat' || $this->AccountFields['Login'] == '8082037410')
                    && ($message = $this->http->FindSingleNode("//title[contains(text(), 'My Verizon is temporarily unavailable')]"))) {
                    throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
                }

                return false;
            }// if (!$this->http->ParseForm("loginForm"))
        } else {
            $this->logger->debug('Auth Step: Secret Question');
            $question = $this->http->FindPreg("/Secret\s*Question:\s*<\/span>\s*<div[^>]+>\s*<\/div>\s*([^\?<]+\?)\s*</ims");

            if (!isset($question)) {
                $question = $this->http->FindSingleNode("//p[strong[contains(text(), 'Secret Question:')]]/text()[last()]");
            }

            if (!isset($question)) {
                $question = $this->http->FindSingleNode("//h3[contains(text(), 'Secret Question')]/following-sibling::label", null, true, "/\:?\s*(.+)/");
            }

            if (!$this->http->ParseForm("challengequestion")) {
                return false;
            }
        }

        if (empty($question)) {
            return false;
        }

        $this->Question = $question;
        $this->ErrorCode = ACCOUNT_QUESTION;
        $this->Step = "Question";

        return true;
    }

    public function ProcessStep($step)
    {
        $this->logger->notice(__METHOD__);
        // Recaptcha
        if ($key = $this->http->FindSingleNode("//div[@class = 'g-recaptcha']/@data-sitekey")) {
            $captcha = $this->parseCaptcha($key);

            if ($captcha === false) {
                return false;
            }

            $this->http->SetInputValue('g-recaptcha-response', $captcha);
        }

        $this->http->SetInputValue("IDToken1", $this->Answers[$this->Question]);

        if (
            strstr($this->Question, 'Please enter Login PIN which was sent to the following')
            || strstr($this->Question, 'Please enter Identification Code which was sent to the following')
        ) {
            unset($this->Answers[$this->Question]);
        }

        if (!$this->http->PostForm()) {
            if ($this->http->currentUrl() == 'https://myvpostpay.verizonwireless.com/myv/overview') {
                return true;
            }

            return false;
        }

        if ($this->http->FindSingleNode("//h1[contains(text(), 'Two accounts. One login.')]") && $this->http->ParseForm("linkWireless")) {
            $this->logger->notice("login update");
            $this->http->SetInputValue('optionType', "2");
            $this->throwProfileUpdateMessageException();
            $this->http->PostForm();
        }

        if ($this->http->FindSingleNode("//title[contains(text(), 'Please wait...')]") && $this->http->ParseForm("myaccountForm")) {
            $this->http->PostForm();
        }

        if ($this->http->ParseForm("oneVzPoster")) {
            $this->http->PostForm();

            if (($queryString = $this->http->FindPreg("/Querystring = '([^']+)/ims")) || ($this->newDesign == false)
                && $this->http->currentUrl() != 'https://business.verizon.com/MyBusinessAccount/one.portal?_nfpb=true&_pageLabel=gb_overview') {
                $this->logger->debug("New design -> one more form");
                $this->http->PostURL('https://www.verizon.com/foryourhome/myaccount/ngen/pr/common/eprocess.aspx?' . $queryString, []);

                $this->logger->debug("[Current URL]: " . $this->http->currentUrl());
                /*
                 * wrong message
                 */
                // Is your contact information up to date?
                if (
                    $this->http->currentUrl() == 'https://www.verizon.com/foryourhome/myaccount/pr/myvzdashboard/offers?emailmtn'
                    || $this->http->currentUrl() == 'https://www.verizon.com/consumer/myverizon/offers'
                ) {
//                    $this->throwProfileUpdateMessageException();
                    $this->http->GetURL("https://www.verizon.com/foryourhome/myaccount/pr/dashboard/details");
                }
            }
        }// if ($this->http->ParseForm("oneVzPoster"))

        // Invalid answer
        $error = $this->http->FindSingleNode("//div[contains(text(), 'The answer to your Secret Question is not correct')]");
        // AccountID: 2298498
        if (!isset($error)) {
            $error = $this->http->FindSingleNode("//div[contains(text(), 'Please enter your Secret Answer in the field above.')]");
        }
        // Please enter your Secret Answer.
        if (!isset($error)) {
            $error = $this->http->FindSingleNode("//div[contains(text(), 'Please enter your Secret Answer.')]");
        }
        $question = $this->http->FindPreg("/Secret\s*Question:\s*<\/span>\s*<div[^>]+>\s*<\/div>\s*([^\?<]+\?)\s*</ims");

        if (!isset($question)) {
            $question = $this->http->FindSingleNode("//p[strong[contains(text(), 'Secret Question:')]]/text()[last()]");
        }

        if (isset($question, $error)) {
            $this->AskQuestion($question, $error);
            $this->logger->debug("question parsed");

            return false;
        }

        if ($this->parseQuestion()) {
            return false;
        }

        $this->sendPassword();
        $this->checkErrors();

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);
        // The information you entered does not match our files.
        if ($message = $this->http->FindSingleNode("//*[contains(text(), 'The information you entered does not match our files.')]")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        // The information you entered does not match the information we have on file
        if ($message = $this->http->FindSingleNode('//p[@class = "bg-danger" and contains(text(), "The information you entered does not match the information we have on file")]')) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        // The information you have entered does not match the information we have on file
        if ($message = $this->http->FindSingleNode("//*[contains(text(), 'The information you have entered does not match the information we have on file')]")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        // Your account has been locked
        if ($message = $this->http->FindSingleNode("//h1[contains(text(), 'Your account has been locked') or contains(text(), 'Account Locked')]")) {
            throw new CheckException($message, ACCOUNT_LOCKOUT);
        }
        // For security purposes, your online account has been locked.
        if ($message = $this->http->FindSingleNode("//div[contains(text(), 'For security purposes, your online account has been locked.')]")) {
            throw new CheckException($message, ACCOUNT_LOCKOUT);
        }
        // Your account is locked due to multiple failed attempts.
        if ($message = $this->http->FindSingleNode("//div[contains(text(), 'Your account is locked due to multiple failed attempts.')]")) {
            throw new CheckException($message, ACCOUNT_LOCKOUT);
        }
        // Your account is locked.
        if ($message = $this->http->FindSingleNode("//p[@id = 'responseError' and text() != '']", null, true, "/(Your\s*account is locked\.)/")) {
            throw new CheckException("Your account is locked.", ACCOUNT_LOCKOUT);
        }
        // Your account is locked. To unlock your account you will need to verify additional items.
        if ($this->http->ParseForm("loginForm") && $this->http->FormURL == 'https://ssoauth.verizon.com/sso/mForgotFlows/mpassword/verifyIdentity.jsp') {
            $this->http->PostForm();
        }
        // Your account is locked. To unlock your account you will need to verify additional information.
        if ($message = $this->http->FindSingleNode("//div[contains(text(), 'Your account is locked. To unlock your account you will need to verify additional')]")) {
            throw new CheckException($message, ACCOUNT_LOCKOUT);
        }
        // Your account is locked. To unlock your account you will need to verify
        if ($message = $this->http->FindSingleNode('//p[contains(normalize-space(text()), "Your account is locked. To unlock your account you will need to verify")]')) {
            throw new CheckException('Your account is locked.', ACCOUNT_LOCKOUT);
        }
        // Your account has been locked due to too many unsuccessful attempts at answering your secret question. You can still gain access to your account.
        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'Your account has been locked due to too many unsuccessful attempts at answering your secret question. You can still gain access to your account.')]")) {
            throw new CheckException($message, ACCOUNT_LOCKOUT);
        }
        // Your account is locked
        if ($message = $this->http->FindSingleNode("//div[contains(@class, 'padding-all-zero')]/p[@id = 'responseError']", null, true, "/(Your\s*account\s*is\s*locked\.)/")) {
            throw new CheckException($message, ACCOUNT_LOCKOUT);
        }
        // Update Your Security Profile
        if ($message = $this->http->FindSingleNode("(//*[contains(text(), 'Update Your Security Profile') or contains(text(), 'Setup Your User Profile') or contains(text(), 'Select Your Mobile Number') or contains(text(), 'Update Your Information')])[1] | (//div[contains(text(), 'Claim your 1 year of free Netflix from Verizon.')])[1] | //p[contains(text(), \"We thought you'd like to know that you can now use the same sign in information for\")]")) {
            $this->throwProfileUpdateMessageException();
        }
        /*
         * Wrong error
         *
        // My Verizon is temporarily unavailable
        if ($message = $this->http->FindSingleNode("(//*[contains(text(), 'My Verizon is temporarily unavailable')])[1]"))
            throw new CheckException("My Verizon is temporarily unavailable", ACCOUNT_PROVIDER_ERROR);
        */
        // VerizonWireless.com is temporarily unavailable
        if ($message = $this->http->FindSingleNode("(//h1[contains(text(), 'VerizonWireless.com is temporarily unavailable')])[1]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // We are unable to process your request at this time.
        if ($message = $this->http->FindSingleNode("//div[@id = 'errorMsg' and div[contains(text(), 'We are unable to process your request at this time.')]]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // skip offer
        if ($this->http->FindPreg("/(?:<span[^>]*>Remind Me Later\s*<\/span>|<a id=\"rmdltrdesk\"[^>]*>\s*Remind me later<\/a>|<a class=\"button[^>]*>\s*Remind me<\/a>)/ims")
            && strstr($this->http->currentUrl(), 'https://www.verizon.com/home/interstitial/myvzinterstitial.htm?offer=')) {
            $this->logger->notice("Skip offer");

            if ($offer = $this->http->FindPreg("/\?offer=([^<]+)/i", false, $this->http->currentUrl())) {
                $this->logger->debug("Click 'Remind me later'");
                $this->http->GetURL("https://www.verizon.com/foryourhome/myaccount/ngen/pr/widgets/promo.aspx?action=REMIND_ME&offer={$offer}");
                $this->logger->debug("Go to Home page");
                $this->http->GetURL("https://www.verizon.com/foryourhome/myaccount/ngen/pr/home/myverizon.aspx");
            }// if (preg_match("/\?offer=([^<]+)/i", $this->http->currentUrl(), $offer))
        }
        // Enter your Residential User ID & password to continue.
        // Enter your Verizon Residential User ID and password
        if ($this->http->FindSingleNode("//h2[contains(text(), 'Enter your Residential User ID & password to continue.')] | //h2[contains(text(), 'Enter your Verizon Residential password to continue:') or contains(text(), 'Enter your Verizon Residential User ID and password:')]")
            && $this->http->ParseForm("linkResidential")) {
            $this->logger->debug("Skip Reminder");
            $this->http->SetInputValue("optionType", "2");
            $this->http->PostForm();
        }
        // My Verizon Prepaid
        if ($this->http->ParseForm("tutorialWelcomeForm") && $this->http->FindSingleNode("//input[contains(@value, 'CONTINUE TO MY ACCOUNT')]/@value")) {
            $this->logger->debug("skip offer");
            $this->http->SetInputValue("/com/vzw/myverizon/profile/MyVProfileFormHandler.tutorial", "CONTINUE TO MY ACCOUNT");
            $this->http->PostForm();
        }
        // We currently don't have a mobile number associated with your User ID.
        if ($message = $this->http->FindSingleNode('//p[contains(text(), "We currently don\'t have a mobile number associated with your User ID.")]')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Parse()
    {
        // Business Account
        if ($this->http->currentUrl() == 'https://business.verizon.com/MyBusinessAccount/one.portal?_nfpb=true&_pageLabel=gb_overview') {
            $this->logger->notice("Business Account");
            // Name
            $this->SetProperty("Name", beautifulName($this->http->FindSingleNode("//div[contains(@class, 'cus-name')]/text()[last()]")));
            // Balance - PTS
            $this->SetBalance($this->http->FindSingleNode("//div[@id = 'vzBusinessRewards_1']//p[@class = 'mbot0 f12']"));
            // Account #
            $this->SetProperty("AccountNumber", $this->http->FindSingleNode("//span[@class = 'ac-name']"));
        }// if ($this->http->currentUrl() == 'https://business.verizon.com/MyBusinessAccount/one.portal?_nfpb=true&_pageLabel=gb_overview')
        // new design
        elseif ($this->http->currentUrl() == 'https://www.verizon.com/foryourhome/myaccount/ngen/pr/home/myverizon.aspx'
            || $this->http->currentUrl() == 'https://www.verizon.com/ForYourHome/ebillpay/V/code/Payments_New.aspx?target='
            || ($this->newDesign && !$this->http->FindPreg("/:\"RewardsTile\",\"dataUrl\":\"([^\"]+)/"))) {
            $this->logger->notice("New design");
            $this->newDesign = true;
            // Name
            $this->SetProperty("Name", beautifulName($this->http->FindPreg("/var js_greetname = \"([^\"]+)/ims")));

            if (empty($this->Properties['Name'])) {
                $this->SetProperty("Name", beautifulName($this->http->FindPreg("/var aimsName = \'([^\']+)/ims")));
            }

            if (empty($this->Properties['Name'])) {
                $this->SetProperty("Name", beautifulName($this->http->FindPreg("/var gname = \'([^\']+)/ims")));
            }

            if (empty($this->Properties['Name'])) {
                $this->SetProperty("Name", beautifulName($this->http->FindPreg("/body\":\"([^\"]+)/ims")));
            }

            if (empty($this->Properties['Name']) && $this->http->FindPreg("/(var gname = \'\')/ims")) {
                $nameEmpty = true;
            } else {
                $nameEmpty = false;
            }
            // Account Number
            $this->SetProperty("AccountNumber", $this->http->FindSingleNode("//li[contains(@class, 'filter-bar_choice')]/a"));

            $this->http->PostURL("https://www.verizon.com/foryourhome/myaccount/ngen/pr/home/widgets/rwrds.aspx", []);
            $response = $this->http->JsonLog();
            // Balance - Total Amount Due
            if (isset($response->Rwrdpoint)) {
                $this->SetBalance($response->Rwrdpoint);
            }
            // Account Number
            if ($vfyh = $this->http->getCookieByName("vfyh", ".verizon.com")) {
                $this->SetProperty("AccountNumber", $this->http->FindPreg("/ACCIDS=1([\d\-]+)/ims", $vfyh));
            }

            if ($registrationApp = $this->http->getCookieByName("RegistrationApp", ".verizon.com")) {
                $this->http->PostURL("https://www.verizon.com/foryourhome/myaccount/ngen/pr/widgets/bill.aspx?type=LEC&n={$registrationApp}", []);
                $this->SetProperty("CashBalance", $this->http->FindSingleNode("//li[@class = 'amntdue']"));
            }// if ($registrationApp = $this->http->getCookieByName("RegistrationApp", ".verizon.com"))

            // AccountID: 3250363, 2674627, 3785065, 3055731
            if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR
                && empty($this->http->Response['body']) && $this->http->Response['code'] == 200
                && (!empty($this->Properties['Name']) || $nameEmpty) && isset($this->Properties['AccountNumber'])) {
                $this->SetBalanceNA();
            }
        }//if ($this->http->currentUrl()=='https://www.verizon.com/foryourhome/myaccount/ngen/pr/home/myverizon.aspx')
        // AccountID: 4088339, 3716616, 4034184, 3517115
        elseif (
            $this->http->currentUrl() == 'https://www.verizon.com/consumer/myverizon/router?frm=eprocess'
            || $this->http->currentUrl() == 'https://www.verizon.com/consumer/myverizon/router?frm=eprocess&Target='
        ) {
            $headers = [
                "x-apikey"          => "GBFRRaaKsY1dS2NxJZAK4OISGAqEtoAj",
                "Accept"            => "application/json, text/plain, */*",
                "AM_VZID"           => "",
                "baseUrl"           => "https://www.verizon.com",
                "Content-Type"      => "application/json",
                "userinfo"          => "",
                "vzid"              => "",
                "dotcomsid"         => $this->http->getCookieByName("dotcomsid", ".verizon.com"),
                "FlowName"          => "getdashboardwidgets",
                "globalSessionId"   => "",
                "makeLiveCall"      => "true",
                "MYVZ_FLOW_NAME"    => "router",
                "osType"            => "web",
                "PageName"          => "router",
                "PREPROV_FLOW_TYPE" => "",
                "Referer"           => "https://www.verizon.com/consumer/myverizon/router",
                "sessionId"         => $this->http->getCookieByName("MyVzSession", ".verizon.com"),
                "TARGET_URL"        => "",
            ];
            $this->http->GetURL("https://www.verizon.com/digitalservices/myvz/getdashboardwidgets", $headers);
            $response = $this->http->JsonLog(null, false);
            $accounts = $response->Accounts ?? [];
            $countOfAccounts = count($accounts);
            $this->logger->debug("Total {$countOfAccounts} accounts were found");

            foreach ($accounts as $account) {
                if ($account->isVision == "Y" || $countOfAccounts == 1 && isset($account->AccNumToDisplay)) {
                    $this->SetProperty("AccountNumber", $account->AccNumToDisplay);
                }
            }
            // Name
            $this->SetProperty("Name", beautifulName($response->dataMap->fullName ?? null));

            $headers = [
                "x-apikey"          => "GBFRRaaKsY1dS2NxJZAK4OISGAqEtoAj",
                "Accept"            => "application/json, text/plain, */*",
                "AM_VZID"           => "",
                "baseUrl"           => "https://www.verizon.com",
                "Content-Type"      => "application/json",
                "userinfo"          => $response->userinfo ?? null,
                "vzid"              => $response->dataMap->vzid ?? null,
                "dotcomsid"         => $this->http->getCookieByName("dotcomsid", ".verizon.com"),
                "FlowName"          => "getrewardssummary",
                "globalSessionId"   => "",
                "makeLiveCall"      => "true",
                "MYVZ_FLOW_NAME"    => "router",
                "osType"            => "web",
                "PageName"          => "dashboard",
                "PREPROV_FLOW_TYPE" => "",
                "Referer"           => "https://www.verizon.com/consumer/myverizon/router",
                "sessionId"         => $this->http->getCookieByName("MyVzSession", ".verizon.com"),
                "TARGET_URL"        => "",
            ];
            $this->http->RetryCount = 0;
            $this->http->GetURL("https://www.verizon.com/digitalservices/myvz/rewards/summary", $headers);
            $this->http->RetryCount = 2;
            $response = $this->http->JsonLog();
            // Balance - My Rewards (pts)
            if (isset($response->registered) && $response->registered == 'Y') {
                $this->SetBalance($response->available);
            }
            // AccountID: 4470165, 4169155
            elseif ((
                    (isset($response->registered, $response->status)
                    && in_array($response->registered, ['N', 'P'])
                    && in_array($response->status, ['Success', 'SUCCESS', 'FAILURE'])
                    && (
                        (isset($response->errormessage) && $response->errormessage == 'ACCOUNT_NOT_FOUND')
                        || (isset($response->errormessage) && $response->errormessage == 'Kobie Api failed:ERROR-503 Service Unavailable')
                        || $this->http->FindPreg('/,"Error pulling desc from DB;FAILURE\-Index: 0, Size: 0"\]\}$/')
                        )
                    )
                    || (isset($response->fault->detail->errorcode) && $response->fault->detail->errorcode == 'messaging.adaptors.http.flow.GatewayTimeout')
                )
                && !empty($this->Properties['Name'])
                && !empty($this->Properties['AccountNumber'])
            ) {
                $this->SetBalanceNA();
            } elseif (
                (
                    (empty($this->http->Response['body']) && $this->http->Response['code'] == 502)
                     || (isset($response->fault->detail->errorcode) && $response->fault->detail->errorcode == 'messaging.adaptors.http.flow.GatewayTimeout')
                     || $this->http->FindPreg("/404 Not Found: Requested route \('myvzmyrewards\.cfappsawsprodeast\.verizon\.com'\) does not exist\./")
                )
                && !empty($this->Properties['Name'])
                && (
                    !empty($this->Properties['AccountNumber'])
                    || in_array($this->AccountFields['Login'], [
                        'weeyah',
                        'nru1286@gmail.com',
                    ])
                )
            ) {
                $this->SetBalanceNA();
            }
        } elseif (
            strstr($this->http->currentUrl(), '//myvprepay.verizonwireless.com/ui/mobile/index.html')
            || strstr($this->http->currentUrl(), '//myvprepay.verizonwireless.com/prepaid/ui/mobile/index.html')
        ) {
            // AccountID: 3785065
            $this->logger->notice("Mobile account");
//            $this->logger->debug($this->http->getCookieByName("XSRF-TOKEN", "myvprepay.verizonwireless.com", "/ui/mobile"));
            $headers = [
                "Accept"       => "application/json, text/plain, */*",
                "X-XSRF-TOKEN" => $this->http->getCookieByName("XSRF-TOKEN", "myvprepay.verizonwireless.com", "/ui/mobile"),
            ];
//            $this->http->GetURL("https://myvprepay.verizonwireless.com/ui/mobile/gw/web/v1/profiles/details", $headers);
            $this->http->GetURL("https://myvprepay.verizonwireless.com/prepaid/ui/mobile/gw/web/v1/profiles/details", $headers);
            $response = $this->http->JsonLog();
            // Your account balance
            if (isset($response->details->plan->available_balance, $response->details->plan->balance_currency)) {
                $this->SetProperty("CashBalance", $response->details->plan->available_balance . " " . $response->details->plan->balance_currency);
            }
            // Account Number
            if (isset($response->details->profile_info->mdn)) {
                $this->SetProperty("AccountNumber", $response->details->profile_info->mdn);
            }
            // Name
            if (isset($response->details->profile_info->full_name)) {
                $this->SetProperty("Name", beautifulName($response->details->profile_info->full_name));
            }

            if (!empty($this->Properties['Name']) && !empty($this->Properties['AccountNumber']) && !empty($this->Properties['CashBalance'])) {
                $this->SetBalanceNA();
            }
        }// elseif (strstr($this->http->currentUrl(), 'https://myvprepay.verizonwireless.com/ui/mobile/index.html'))
        else {
            $this->logger->notice("Standard design");
            $currentURL = $this->http->currentUrl();
            // Cash Balance (Current Balance)
            $cashBalance = $this->http->FindSingleNode("//div[b[contains(text(), 'Current Balance:')]]/following-sibling::div[1]");

            if (!isset($cashBalance)) {
                $cashBalance = $this->http->FindSingleNode("//div[contains(text(), 'Current Balance')]/following-sibling::div[1]");
            }

            if (!isset($cashBalance)) {
                $cashBalance = $this->http->FindSingleNode("//span[contains(text(), 'Current Balance')]/following-sibling::span[1]");
            }

            if (!isset($cashBalance)) {
                $cashBalance = $this->http->FindSingleNode("//div[b[contains(text(), 'Available Balance')]]/following-sibling::div[1]/text()[1]");
            }
            $this->SetProperty("CashBalance", $cashBalance);
            // Name
            $this->SetProperty("Name", $this->http->FindSingleNode("//h1[contains(text(), 'Welcome,')]", null, true, "/Welcome\,\s*([^!]+)/ims"));

            if (!isset($this->Properties['Name'])) {
                $this->SetProperty("Name", $this->http->FindSingleNode("//b[contains(text(), 'Welcome,')]", null, true, "/Welcome\,\s*([^\s]+)/ims"));
            }

            if (empty($this->Properties['Name'])) {
                $this->SetProperty("Name", beautifulName($this->http->FindSingleNode("//p[contains(@class, 'o-greeting-name') and contains(text(), '!')]", null, true, "/([^\!]+)/ims")));
            }

            if (empty($this->Properties['Name'])) {
                $this->SetProperty("Name", beautifulName($this->http->FindSingleNode("//h3[contains(text(), 'Hello')]", null, true, "/Hello\s*([^\<\!]+)/")));
            }

            if (empty($this->Properties['Name'])) {
                $this->SetProperty("Name", beautifulName($this->http->FindSingleNode("//div[contains(text(), 'Hello ')]", null, false, "/Hello\s*([^\<\!]+)/")));
            }
            // Account Number
            $this->SetProperty("AccountNumber", $this->http->FindSingleNode("//span[contains(@class, 'AcctNumber')]"));

            if (!isset($this->Properties['AccountNumber'])) {
                $this->SetProperty("AccountNumber", $this->http->FindPreg("/Account No\.\&nbsp;([\d\-]+)/"));
            }

            if (!isset($this->Properties['AccountNumber'])) {
                $this->SetProperty("AccountNumber", $this->http->FindSingleNode("//h2[contains(text(), 'Account Number:')]/span"));
            }

            if (empty($this->Properties['AccountNumber'])) {
                $this->SetProperty("AccountNumber", $this->http->FindSingleNode("//div[contains(text(), 'Account number:')]/following-sibling::ul[1]/li[contains(@class, 'bar_choice')]"));
            }

            if (!isset($this->Properties['AccountNumber'])) {
                $this->SetProperty("AccountNumber", $this->http->FindSingleNode("//h2[@id = 'welcomeMtn']"));
            }

            $notMember = $this->http->FindPreg("/UpdateContactInfoSC\(\&quot;my rewards signup\&quot;, \&quot;my rewards signup\&quot;\); scLinkTrackID\(\&quot;my rewards\| get started&quot;\);openPop\(\&quot;rewards&quot;\);/ims");
            $currentURL = $this->http->currentUrl();

            $dataUrl = $this->http->FindPreg("/:\"RewardsTile\",\"dataUrl\":\"([^\"]+)/");

            if ($dataUrl) {
                $data = '{"widgetId":25,"templateId":"RewardsTile","dataUrl":"' . $dataUrl . '","widgetType":"SocialGifting_New","userId":"","osType":"web","osVer":""}';
                $headers = [
                    "Accept"           => "*/*",
                    "Content-Type"     => "application/json",
                    "X-Requested-With" => "XMLHttpRequest",
                ];
                $this->http->RetryCount = 0;
                $this->http->PostURL("https://www.verizon.com/ForYourHome/MyAccount/pr/Dashboard/GetWidgetDetails?wid=25", $data, $headers);
                $this->http->RetryCount = 2;
                // Balance - My Rewards+
                if (!$this->SetBalance($this->http->FindSingleNode("//span[contains(text(), 'My Rewards+')]/following-sibling::span[1]"))) {
                    if ($this->http->FindSingleNode("//div[contains(text(), 'Enroll in My Rewards+')]")) {
                        $notMember = true;
                    }
                }
            } else {
                $this->parseSmartRewards();
            }

            if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR) {
                $this->logger->debug("[Current URL]: " . $this->http->currentUrl());
                $noSmartRewards = ($this->http->currentUrl() == 'https://myvpostpay.verizonwireless.com/ui/hub/data/secure/overview/get_ensighten_tags');

                if ((empty($this->http->Response['body']) && $this->http->Response['code'] == 200
                        || strstr($this->http->currentUrl(), 'billpay.verizonwireless.com/myvprepay/home/')
                        || strstr($currentURL, 'billpay.verizonwireless.com/myv/overview')
                        || $this->http->FindPreg("/<body>\s*Due to your recent account changes, we cannot connect to this page\.\s*Please check back again in a couple of hours\.\s*<\/body>/"))
                    && (isset($this->Properties['Name']) || isset($this->Properties['AccountNumber']))) {
                    $this->SetBalanceNA();
                } elseif ($notMember) {
                    throw new CheckException(self::NOT_MEMBER_MSG, ACCOUNT_PROVIDER_ERROR);
                } elseif ((stripos($currentURL, '/foryourhome/myaccount/pr/dashboard/details') !== false || $noSmartRewards)
                        && (!empty($this->Properties['Name']) || $noSmartRewards) && !empty($this->Properties['AccountNumber'])) {
                    $this->SetBalanceNA();
                }
                // There was an error in retrieving your Rewards information.
                elseif ($message = $this->http->FindSingleNode("//div[contains(text(), 'There was an error in retrieving your Rewards information.')]")) {
                    throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
                }
            }// if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR)
        }
    }

    private function parseCaptcha($key)
    {
        $this->logger->notice(__METHOD__);
        //$key = $this->http->FindSingleNode("//div[@class = 'g-recaptcha']/@data-sitekey");
        $this->logger->debug("data-sitekey: {$key}");

        if (!$key) {
            return false;
        }
        $this->recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_RUCAPTCHA);
        $this->recognizer->RecognizeTimeout = 120;
        $parameters = [
            "pageurl" => $this->http->currentUrl(),
            "proxy"   => $this->http->GetProxy(),
        ];
        $captcha = $this->recognizeByRuCaptcha($this->recognizer, $key, $parameters);

        return $captcha;
    }

    // Accounts 2186128, 3685659
    private function parseSmartRewards()
    {
        $this->http->GetURL('https://rewards.verizonwireless.com/gateway?t=marketplace');
        $this->SetBalance($this->http->FindSingleNode('//*[@id="rewardsbalancevalue"]'));

        // Account 3449434, 2761956
        if ($message = $this->http->FindSingleNode('//div[contains(text(), "Unfortunately, your account is not eligible for Verizon Smart Rewards at this time")]')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        $headers = ['Accept' => 'application/json'];
        $this->http->GetURL('https://myvpostpay.verizonwireless.com/ui/hub/data/secure/overview/get_ensighten_tags', $headers);
        $response = $this->http->JsonLog();

        if (empty($response) || empty($response->ensightenDto->page->authStatus) || $response->ensightenDto->page->authStatus != 'authenticated'
            || empty($response->ensightenDto->authentication) || !empty($this->Properties['Name'])) {
            return false;
        }

        $this->SetProperty('Name', beautifulName($response->ensightenDto->authentication->greetingName));
        $this->SetProperty('AccountNumber', $response->ensightenDto->authentication->accountNumber);

        return true;
    }
}
