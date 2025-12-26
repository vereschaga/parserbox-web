<?php

use AwardWallet\Common\Parsing\Html;
use AwardWallet\Engine\ProxyList;

class TAccountCheckerRbcbank extends TAccountChecker
{
    use SeleniumCheckerHelper;
    use ProxyList;

    public $regionOptions = [
        ""          => "Select your region",
        'USA'       => 'USA',
        'Canada'    => 'Canada',
        'Caribbean' => 'Caribbean',
    ];

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
    }

    public function TuneFormFields(&$arFields, $values = null)
    {
        parent::TuneFormFields($arFields, $values);
        $arFields['Login2']['Options'] = $this->regionOptions;
    }

    public function LoadLoginForm()
    {
        $this->logger->notice('Region => ' . $this->AccountFields['Login2']);
        $this->AccountFields['Login2'] = $this->checkRegionSelection($this->AccountFields['Login2']);

        return call_user_func([$this, "LoadLoginFormOf" . $this->AccountFields['Login2']]);
    }

    public function Login()
    {
        return call_user_func([$this, "LoginOf" . $this->AccountFields['Login2']]);
    }

    public function Parse()
    {
        return call_user_func([$this, "ParseOf" . $this->AccountFields['Login2']]);
    }

    // --------- Caribbean ----------

    public function LoadLoginFormOfCaribbean()
    {
        $this->logger->notice(__METHOD__);
        $this->http->removeCookies();
        $this->http->GetURL("https://www.rbcrewardscaribbean.com/pages/user/login.aspx");

        if (!$this->http->ParseForm("aspnetForm")) {
            return false;
        }
        $this->http->SetInputValue('ctl00$contentMain$txtLoginName', $this->AccountFields["Login"]);
        $this->http->SetInputValue('ctl00$contentMain$txtLoginPassword', $this->AccountFields["Pass"]);
        $this->http->SetInputValue('__EVENTTARGET', 'ctl00$contentMain$lnkSubmitLogin');
        $this->http->SetInputValue('ctl00$contentMain$chkRememberme', 'on');

        return true;
    }

    public function LoginOfCaribbean()
    {
        $this->logger->notice(__METHOD__);

        if (!$this->http->PostForm()) {
            return false;
        }

        if ($this->LoginOfUSA()) {
            return true;
        }
        // You have entered an invalid Username and/or password.
        if ($message = $this->http->FindSingleNode('//p[contains(text(), "You have entered an invalid Username and/or password.")]')) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        return false;
    }

    public function ParseOfCaribbean()
    {
        $this->logger->notice(__METHOD__);
        $this->ParseOfUSA("https://www.rbcrewardscaribbean.com/pages/user/account.aspx");
    }

    // --------- Canada (Online Banking) ----------

    public function LoadLoginFormOfCanada()
    {
        $this->http->removeCookies();
        $this->http->GetURL("https://www1.royalbank.com/cgi-bin/rbaccess/rbcgi3m01?F6=1&F7=IB&F21=IB&F22=IB&REQUEST=ClientSignin&LANGUAGE=ENGLISH");

        if (!$this->http->ParseForm("rbunxcgi")) {
            return false;
        }

        return $this->selenium();

        if (empty($inputs)) {
            return false;
        }

        foreach ($inputs as $input) {
            if (isset($input['name'], $input['value'])) {
                $this->http->SetInputValue($input['name'], $input['value']);
            }
        }

        $this->http->SetInputValue("K1", $this->AccountFields["Login"]);
        //		$this->http->SetInputValue("Q1", $this->AccountFields["Pass"]);
        $this->http->SetInputValue("NNAME", ""); //ecatsRememberMe
        unset($this->http->Form["NOJAVASCRIPT"]);

        return true;
    }

    public function ConfirmationOfIdentity()
    {
        $this->logger->notice(__METHOD__);

        // AccountID: 4149262
        if ($this->http->FindSingleNode("//title[contains(text(), 'MyGuide Redirect Page') or contains(text(), 'OMNI Redirect Page') or contains(text(), 'Redirect to the OMNI')]")) {
            $this->logger->notice("MyGuide/OMNI Redirect Page");
            $isamdomain = $this->http->FindPreg("/var (?:isamdomain|omnidomain) = \"([^\"]*)/");
            $redirecturl = $this->http->FindPreg("/var redirecturl = \"([^\"]*)/");
            $isamreturndomain = $this->http->getCurrentScheme() . "://" . $this->http->getCurrentHost();

            $redirect = $isamdomain . $redirecturl . $isamreturndomain;
            $this->http->NormalizeURL($redirect);
            $this->http->GetURL($redirect);

            if ($url = $this->http->FindPreg("/function\s*done\(\)\s*\{\s*var\s*url=\"([^\"]+)/")) {
                $url .= urlencode($this->http->currentUrl());
                $this->http->NormalizeURL($url);
                $this->http->GetURL($url);

                if (!$this->http->ParseForm("SAML_REDIRECT")) {
                    return;
                }
                $this->http->PostForm();

                if ($this->http->FindSingleNode("
                        //td[contains(text(), 'Remember My Username or Client Card Number')]
                        | //label[contains(text(), 'I have read and agree to be legally bound by the terms of the Electronic Access Agreement.')]
                    ")
                ) {
                    $this->throwProfileUpdateMessageException();
                }

                $this->http->GetURL("https://www1.royalbank.com/wps/myportal/OLB/com.rbc._3m00.olb.web.portal.pg.myacct.accountsummary");
            }
        }// if ($this->http->FindSingleNode("//title[contains(text(), 'MyGuide Redirect Page')]"))

        // Confirmation of identity
        if ($this->http->FindSingleNode('//*[self::td or self::h1][contains(text(), "Protection de l\'ouverture de session - Alerte") or contains(text(), "Sign-In Protection Alert")]')
            && $this->http->ParseForm("sipwasme")) {
            $this->logger->notice("Confirmation of identity");
            $this->http->PostForm();

            if ($this->http->FindSingleNode('//td[contains(text(), "Protection de l\'ouverture de session - C\'")]')
                && $this->http->ParseForm("m3pref_DispPVQsAForm")) {
                $this->http->PostForm();
            }

            if ($this->http->FindSingleNode('//td[contains(text(), "Questions d\'identification personnelle")] | //h1[contains(text(), "Questions d\'identification personnelle")] | //h1[contains(text(), "Sign-In Protection - Create Personal Verification Questions")]')
                && ($this->http->ParseForm("Continue") || $this->http->ParseForm("frmContinue"))) {
//                $this->http->PostForm();
                throw new CheckException("RBC Rewards Banking Account website is asking you to set security questions, until you do so we would not be able to retrieve your account information.", ACCOUNT_PROVIDER_ERROR);
            } /*checked*/
        }

        if ($this->http->FindSingleNode('//h1[contains(text(), "Questions d\'identification personnelle") or contains(text(), "Sign-In Protection - Create Personal Verification Questions")]')
            && $this->http->FindSingleNode('//td[contains(text(), "Nous vous recommandons de choisir parmi les questions que nous") or contains(text(), "Protecting your personal and financial information is our priority.")]')
            && ($this->http->ParseForm("Continue") || $this->http->ParseForm("frmContinue"))) {
            throw new CheckException("RBC Rewards Banking Account website is asking you to set security questions, until you do so we would not be able to retrieve your account information.", ACCOUNT_PROVIDER_ERROR);
        } /*checked*/

        //# Please review and update your Personal Verification Questions and answers by following the link below.
        if (
            $this->http->FindSingleNode('//td[
                contains(text(), "Please review and update your Personal Verification Questions and answers by following the link below")
                or contains(text(), "Please select 3 questions and enter an answer for each question.")
                or contains(text(), "Please review and update your security settings and personal verification questions and answers before continuing.")
            ]')
            && ($this->http->ParseForm("m3pref_DispPVQsAForm") or $this->http->ParseForm("Continue"))
        ) {
            $this->throwProfileUpdateMessageException();
        }
    }

    public function LoginOfCanada()
    {
        $this->logger->notice(__METHOD__);
//        if (!$this->http->PostForm()) {
//            return false;
//        }

        $this->ConfirmationOfIdentity();

        // Activation Banking completed (Activation Banque en direct terminée)
        if ($this->http->FindSingleNode('//td[
                contains(text(), "Activation Banque en direct termin")
                or contains(text(), "Online Banking Activation Complete")
            ]')
            && $this->http->ParseForm("Continue")) {
            $this->http->PostForm();
        }

        if ($this->http->FindSingleNode('//td[contains(text(), "Configuration des questions d\'identification personnelle") or contains(text(), "Please select 3 questions and enter an answer for each question.")] | //h1[contains(text(), "Questions d\'identification personnelle")]')
            && $this->http->ParseForm("Continue")) {
            throw new CheckException("RBC Rewards Banking Account website is asking you to set security questions, until you do so we would not be able to retrieve your account information.", ACCOUNT_PROVIDER_ERROR);
        } /*checked*/
        // need to complete account activation
        if ($this->http->FindSingleNode('//td[contains(text(), "Code d\'activation")]')
            && $this->http->ParseForm("Form_EnterACTV")) {
            throw new CheckException("RBC Rewards Banking Account website is asking you to update your profile, until you do so we would not be able to retrieve your account information.", ACCOUNT_PROVIDER_ERROR);
        } /*checked*/
        // To protect your banking information, your access to Online Banking has been temporarily disabled
        if ($this->http->FindSingleNode('//p[contains(text(), "Pour assurer la protection de vos renseignements bancaires, votre accès à Banque en direct a été provisoirement désactivé.")]')) {
            $this->throwProfileUpdateMessageException();
        }

        //# Invalid credentials
        if ($message = $this->http->FindSingleNode("//td[@class = 'errorText']")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'Your response does not match our records')]")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'We are having trouble identifying you')]")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        // Il est impossible d'ouvrir ou de poursuivre votre session Banque en direct.
        if ($message = $this->http->FindSingleNode('//p[contains(text(), "Il est impossible d\'ouvrir ou de poursuivre votre session Banque en direct. ")]')) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        // Hmm...Something doesn't seem right.
        if ($message = $this->http->FindSingleNode('//h2[contains(., "Hmm...Something doesn\'t seem right.")]/text()[last()]')) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        if ($message = $this->http->FindSingleNode('//div[@class = \'redMessage\' and contains(., "Something doesn\'t seem right.")]/text()[last()]')) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        // To ensure the security of your banking information, your access to RBC Online Banking has been temporarily disabled.
        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'To ensure the security of your banking information, your access to RBC Online Banking has been temporarily disabled.')]")) {
            throw new CheckException($message, ACCOUNT_LOCKOUT);
        }
        // Par mesure de précaution, nous avons temporairement désactivé l'accès à votre compte.
        if ($message = $this->http->FindPreg('/<p>Par mesure de pr[^<]+caution, nous avons temporairement d[^<]+sactiv[^<]+ l\'acc[^<]+s [^<]+ votre compte.<p>/')) {
            throw new CheckException("Par mesure de précaution, nous avons temporairement désactivé l'accès à votre compte.", ACCOUNT_LOCKOUT);
        }
        // An attempt to access to your Online Banking service was blocked
        if ($message = $this->http->FindPreg("/(An attempt to access to your Online Banking service was blocked)/ims")
            ?? $this->http->FindSingleNode('//td[contains(text(), "Access to your online service was blocked because your Personal Verification Question was not correctly answered.")]')
        ) {
            throw new CheckException($message, ACCOUNT_LOCKOUT);
        }
        /*
         * Quelque chose ne va pas.
         *
         * Nous nous attendions à une réponse différente.Si la tentative échoue, vous pourrez Réinitialiser votre mot de passe pour accéder à Banque en direct.
         */
        if ($this->http->FindSingleNode("//div[@class = 'redMessage' and contains(., 'Quelque chose ne va pas.')]") && $this->http->FindSingleNode("//p[contains(text(), 'Nous nous attendions')]")) {
            throw new CheckException("Quelque chose ne va pas. Nous nous attendions à une réponse différente.Si la tentative échoue, vous pourrez Réinitialiser votre mot de passe pour accéder à Banque en direct.", ACCOUNT_PROVIDER_ERROR);
        }
        //# Remember My Username or Client Card Number
        if ($this->http->FindSingleNode("//td[contains(text(), 'Remember My Username or Client Card Number')]")
            && $this->http->FindSingleNode("//img[@name = 'btn_confirm']/@name")) {
            throw new CheckException("RBC Rewards Banking Account website is asking you to set up Remember My Username or Client Card Number, until you do so we would not be able to retrieve your account information.", ACCOUNT_PROVIDER_ERROR);
        } /*checked*/
        // We Need a Few Minutes of Your Time
        if ($this->http->FindPreg("/(?:I have read and agree to be legally bound by the terms of the Electronic Access Agreement.|J&#x2019;ai lu la Convention d&#x2019;acc&#232;s &#233;lectronique et j&#x2019;accepte d&#x2019;&#234;tre li&#233; par celle-ci.)/")
        ) {
            $this->throwProfileUpdateMessageException();
        }
        // As a precaution, we've temporarily disabled your online access.
        if ($message = $this->http->FindSingleNode('//p[contains(text(), "As a precaution, we\'ve temporarily disabled your online access.")]')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        /**
         * We're sorry.
         *
         * We can't open or continue your Online Banking session. Please contact the RBC Advice Centre at 1-800-769-2555. An advisor would be happy to assist you.
         */
        if ($message = $this->http->FindSingleNode('//p[contains(text(), "We can\'t open or continue your Online Banking session")]')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        // Accounts without questions
        if ($this->form_portal() || $this->http->FindPreg("/'pageTitle': '(?:Account Summary|Business Accounts)',/")) {
            $this->http->FilterHTML = false;

            if ($this->http->FindSingleNode("//a[contains(text(), 'Sign Out')]")
                || $this->http->FindSingleNode("(//a[contains(@href, 'SIGNOUT') or contains(@ga-event-label, 'Sign Out')])[1]")
                || $this->http->FindSingleNode("//span[contains(text(), 'Sign Out') or contains(text(), 'Fin de session')]")
                || $this->http->FindPreg("/span>(?:Sign Out|Fin de session)<\/span>/")) {
                return true;
            }
        }

        if ($this->ParseQuestion()) {
            return false;
        }

        //# Please review and update your Personal Verification Questions and answers by following the link below.
        if ($this->http->FindSingleNode('//td[contains(text(), "Please review and update your Personal Verification Questions and answers by following the link below")]')
            && $this->http->ParseForm("m3pref_DispPVQsAForm")) {
            throw new CheckException("RBC Rewards Banking Account website is asking you to update your Personal Verification Questions and answers, until you do so we would not be able to retrieve your account information.", ACCOUNT_PROVIDER_ERROR);
        } /*checked*/
        // We cannot process your request. Please sign in again.
        if ($message = $this->http->FindSingleNode('//h3[contains(text(), "We cannot process your request. Please sign in again.")]')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // Skip Activation
        if ($this->http->FindSingleNode("//td[contains(text(), 'Activation Code')]")
            && $this->http->ParseForm("Form_IgnorACTV")) {
            $this->logger->notice("Skip Activation");
            $this->http->PostForm();
        }

        if ($this->form_portal()) {
            $this->http->FilterHTML = false;

            if ($this->http->FindSingleNode("//a[contains(text(), 'Sign Out') or contains(text(), 'Fin de session')]")
                || $this->http->FindPreg("/span>Sign Out<\/span>/")) {
                return true;
            }
        }

        /*
        if (!$this->http->ParseForm("ERNEX_REDIRECT")) {
            return false;
        }
        $this->http->PostForm();
        */

        return true;
    }

    public function form_portal()
    {
        $this->logger->notice(__METHOD__);
        $this->http->FilterHTML = false;

        if ($this->http->ParseForm("form_portal") && isset($this->http->Form['7ASCRIPT'])) {
//            $this->http->PostForm();
            $lastFunction = '';
            $this->http->Form['7ASCRIPT'] = str_replace('web.portal.pg.myacct_fr', 'web.portal.pg.myacct_en', $this->http->Form['7ASCRIPT']);

            if (isset($this->http->Form['LASTFUNCTION'])) {
                $lastFunction = "LASTFUNCTION={$this->http->Form['LASTFUNCTION']}&";
            }
            $this->http->GetURL("https://www1.royalbank.com/cgi-bin/rbaccess/rbcgi3m01?FROMSIGNIN=YES&{$lastFunction}F22={$this->http->Form['F22']}&RBUNXCGI={$this->http->Form['RBUNXCGI']}&LANGUAGE=ENGLISH&CPG={$this->http->Form['CPG']}&7ASERVER={$this->http->Form['7ASERVER']}&7ASCRIPT={$this->http->Form['7ASCRIPT']}");

            return true;
        }

        return false;
    }

    public function ParseQuestion()
    {
        $this->logger->info('Security Question', ['Header' => 3]);
        $question = $this->http->FindSingleNode('//td[label[@for = "pvqQuestion"]]/following::td[1]');

        if (!isset($question)) {
            $question = $this->http->FindSingleNode('//label[@for = "pvqQInput"]');

            if (isset($question)) {
                $this->AskQuestion($question, null, "QuestionNew");

                return false;
            }
        }

        if (!isset($question) || !$this->http->ParseForm("continueform")) {
            return false;
        }
        $this->Question = $question;
        $this->ErrorCode = ACCOUNT_QUESTION;
        $this->Step = "Question";

        return true;
    }

    public function ProcessStep($step)
    {
        $this->logger->notice('Region => ' . $this->AccountFields['Login2']);
        $this->AccountFields['Login2'] = $this->checkRegionSelection($this->AccountFields['Login2']);

        $this->http->SetInputValue("SIP_PVQ_ANS", $this->Answers[$this->Question]);
        $this->http->SetInputValue("SIP_NOASK", "on");
        $this->http->PostForm();
        $error = $this->http->FindSingleNode("//td[@class = 'errorText']");

        if (!isset($error)) {
            $error = $this->http->FindSingleNode("//p[contains(text(), 'Your response does not match our records')]");
        }

        if ($this->http->FindPreg("/msguos_fieldMsgs\[1\s*\-\s*1\]\s*=\s*\"Une r/") && $this->http->FindPreg("/ponse valide est requise\.\"/")) {
            $error = 'Une réponse valide est requise.';
        }

        if (!isset($error)) {
            $error = $this->http->FindSingleNode("//p[contains(text(), 'Your answer to the PVQ (Personal Verification Question) does not match our records.')]");
        }

        if (!isset($error)) {
            $error = $this->http->FindPreg("/Please enter your answer to the question. The answer must be a minimum of 4 characters. Reference # SIP-004\./ims");
        }

        if (!isset($error) && $this->http->FindPreg("/Veuillez entrer votre r.ponse . la question. La r.ponse doit contenir un minimum de 4.caract.res\..R.f.rence SIP-004\./ims")) {
            $error = "Veuillez entrer votre réponse la question. La réponse doit contenir un minimum de 4 caractres. Reference SIP-004.";
        }

        if (isset($error)) {
            $this->ErrorMessage = $error;
            $this->ParseQuestion();

            return false;
        }
        $this->form_portal();
        // Remember me
        if ($this->http->ParseForm("sipwasme")) {
            $this->http->PostForm();
        }

        $this->ConfirmationOfIdentity();

        return true;
    }

    public function ParseOfCanada()
    {
        $this->logger->notice(__METHOD__);
        $name =
            $this->http->FindSingleNode('//div[@class = "banner_name"]//button')
            ?? $this->http->FindSingleNode('//div[@class = "nameDropdown"]//button/div')
            ?? $this->http->FindSingleNode('//div[@class = "not-mobile"]//strong[@rb-data = "client_name"]')// AccountID: 1797475
        ;

        // AccountID: 4897464, 4997199, 4133479
        if (empty($name)) {
            $browser = clone $this;
            $headers = [
                "Accept" => "application/json, text/plain, */*",
            ];
            $browser->http->RetryCount = 0;
            $browser->http->GetURL("https://www1.royalbank.com/sgw5/api/user-presentation-service/v2/users/srfId?timestamp=" . date("UB"), $headers);
            $browser->http->JsonLog();
            $browser->http->GetURL("https://www1.royalbank.com/sgw5/api/user-presentation-service/v2/users/basicProfile?timestamp=" . date("UB"), $headers);
            $browser->http->RetryCount = 2;
            $basicProfile = $browser->http->JsonLog();
            $name = $basicProfile->fullName ?? null;
        }

        $this->logger->debug("Name -> {$name}");

        $ficoScore =
            $this->http->FindSingleNode("//a[@id = 'PA_Accsummary_View_Your_Credit_Score']/@onclick", null, true, "/open\(\'([^\']+)/")
            ?? $this->http->FindSingleNode("//a[contains(text(), 'View Your Credit Score')]/@href")
        ;
        // something was changed
        $noSubmit = false;

        if (!$this->http->FindPreg("/form\s*action=\"([^\"]+)\"\s*name=\"rewardHome\"/ims")
            && $this->http->FindSingleNode("(//a[contains(text(), 'Go to RBC Rewards')]/ancestor::form[1])[1]")) {
            if ($this->http->ParseForm(null, "//a[contains(text(), 'Go to RBC Rewards')]/ancestor::form[1]")) {
                $this->http->PostForm();
            }
            $noSubmit = true;
        } elseif ($RBCRewardsLink = $this->http->FindSingleNode("
                (//a[(@class = 'icon-link-btn' or @ga-event-category = 'Account Products Container') and (contains(., 'Go to RBC Rewards') or contains(text(), 'Aller á RBC Récompenses') or contains(text(), 'Aller à RBC Récompenses'))]/@href)[1]
                | //a[@class = 'add-on__links' and contains(., 'RBC Rewards')]/@href
            ")
            ?? $this->http->FindPreg("/<a[^>]+ga-event-category=\"Account Products Container\"[^>]+href=\"([^\"]+)\"[^>]+><[^>]+>Go to RBC Rewards<\/a>/")
            ?? 'https://www1.royalbank.com/sgw1/secureapp/uy10/www/rewards/?'
        ) {
            $this->logger->debug("Go to RBC Rewards -> $RBCRewardsLink");
            $this->http->NormalizeURL($RBCRewardsLink);
            $this->http->GetURL($RBCRewardsLink);
            $noSubmit = true;
        }// elseif ($RBCRewardsLink = $this->http->FindSingleNode("//a[contains(text(), 'Go to RBC Rewards')]/@href"))

        // fico
        $ficoScore = $ficoScore
            ?? $this->http->FindSingleNode("//a[@id = 'PA_Accsummary_View_Your_Credit_Score']/@onclick", null, true, "/open\(\'([^\']+)/")
            ?? $this->http->FindSingleNode("//a[contains(text(), 'View Your Credit Score')]/@href")
        ;

        // RBC Rewards -> New Version
        $doneURL = $this->http->FindPreg("/function\s*done\(\)\s*\{\s*var\s*url=\"([^\"]+)/ims");

        if (
            isset($RBCRewardsLink)
            && (
                $doneURL
                || $RBCRewardsLink == 'https://www1.royalbank.com/sgw1/secureapp/uy10/www/rewards/?'
                || $RBCRewardsLink == 'https://www1.royalbank.com/sgw1/secureapp/uy10/www/rewards/#/home'
            )
        ) {
            $this->logger->notice("Canada: New design");

            if ($doneURL) {
                $doneURL = $doneURL . urlencode($RBCRewardsLink);
                $this->logger->debug("Go to {$doneURL}");
                $this->http->NormalizeURL($doneURL);
                $this->http->GetURL($doneURL);

                if (!$this->http->ParseForm("SAML_REDIRECT")) {
                    if ($message = $this->http->FindSingleNode('//td[contains(text(), "We are experiencing temporary problems. Restoring service is our top priority. Thank you for your patience")]')) {
                        throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
                    }

                    return;
                }
                $this->http->PostForm();
            } else {
                $this->http->NormalizeURL($RBCRewardsLink);
                $this->http->GetURL($RBCRewardsLink);
            }
            // get JSON
            $this->http->setDefaultHeader("Accept", "application/json, text/plain, */*");
            $this->http->setDefaultHeader("RequestId", "REQ-" . time() . date("B")); // REQ-1489566081933
            $this->http->setDefaultHeader("Timeout", "30000");
            $this->http->setDefaultHeader("clientIdType", "CLIENT_CARD_NUM");
            $this->http->RetryCount = 0;
            $this->http->GetURL("https://www1.royalbank.com/sgw1/YC10/Channel-Interactions/v1/loyalty/profile/accounts?lang=en");
            $this->http->RetryCount = 2;
            $response = $this->http->JsonLog(null, 3, true);
            // Name
            $clientInfo = ArrayVal($response, 'clientInfo');
//            $this->logger->debug(var_export($clientInfo, true), ['pre' => true]);
            $clientName = ArrayVal($clientInfo, 'clientName');
//            $this->logger->debug(var_export($clientName, true), ['pre' => true]);
            $this->SetProperty("Name", beautifulName(Html::cleanXMLValue(ArrayVal($clientName, 'firstName') . " " . ArrayVal($clientName, 'middleName') . " " . ArrayVal($clientName, 'lastName'))));

            // Sub Accounts
            $accounts = ArrayVal($response, 'accounts', []);
            $this->logger->debug("Total " . count($accounts) . " accounts were found");

            foreach ($accounts as $account) {
                $card = ArrayVal($account, 'accountDesc');
                $accountNumber = ArrayVal($account, 'accountId');
                // Available Points
                $balance = ArrayVal(ArrayVal($account, 'balance'), 'points');

                if (isset($card, $accountNumber, $balance)) {
                    $this->AddSubAccount([
                        'Code'        => 'rbcbank' . $accountNumber,
                        'DisplayName' => $card . ' (' . $accountNumber . ')',
                        'Balance'     => $balance,
                    ]);
                }// if (isset($card, $accountNumber, $balance))
            }// foreach ($accounts as $account)

            if (!empty($this->Properties['SubAccounts'])) {
                //# Set Sub Accounts
                $this->SetProperty("CombineSubAccounts", false);
                //# Set Balance n\a
                $this->SetBalanceNA();
            }// if (@empty($this->Properties['SubAccounts']))
            /*
             * Thanks for visiting RBC Rewards
             * It looks like you don’t have an RBC Rewards Card right now.
             */
            elseif ($this->http->FindPreg("/\{\"timestamp\":0,\"errorCode\":\"LYLT-LCI-307\",\"message\":\"LYLT-LCI-307 :: There is no eligible account.\"\}/")) {
                $this->SetBalanceNA();
                // Name
                $this->SetProperty("Name", beautifulName($name));
            }
            /**
             * Sorry, we‘re unable to display your account details at this time.
             * Please try again later.
             *
             * AccountID: 4829429, 4208786, 4910197, 4197389, 4911221
             */
            elseif (
                $this->http->FindPreg("/\{\"timestamp\":0,\"errorCode\":\"LYLT-LCI-200\",\"message\":\"LYLT-LCI-200 :: feign\.Response.InputStreamBody\@/")
                || $this->http->FindPreg("/\{\"timestamp\":0,\"errorCode\":\"LYLT-LCI-200\",\"message\":\"LYLT-LCI-200 :: Unexpected Error\.\"\}/")
                || $this->http->FindPreg("/\{\"timestamp\":0,\"errorCode\":\"LYLT-LCI-504\",\"message\":\"LYLT-LCI-504 :: Path for downstream service does not exist.\"\}/")
            ) {
                $this->SetWarning("Sorry, we‘re unable to display your account details at this time. Please try again later.");
                // Name
                $this->SetProperty("Name", beautifulName($name));
            }
            // AccountID: 1797475
            elseif (!empty($name) && $this->http->FindPreg("/\{\"totalBalance\":\{\"points\":0},\"clientInfo\":\{\"loyaltyIdHash\":\"[^\"]+\",\"srfHash\":\"[^\"]+\",\"clientType\":\"Retail\",\"clientName\":\{\"firstName\":\"[^\"]+\",\"middleName\":(?:null|\"[^\"]+\"),\"lastName\":\"[^\"]+\"\}\}\}/")) {
                $this->SetBalanceNA();
            }
            // AccountID: 4292541, 5351191, 4422205
            elseif (
                !empty($name)
                && (
                    $this->http->FindPreg("/^\{\"clientInfo\":\{\"loyaltyIdHash\":\"[^\"]+\",(?:\"srfNumber\":\s*\"\d+\",|)\"srfHash\":\"[^\"]+\",\"tier\":\"\d+\",\"clientType\":\"Retail\",\"clientName\":\{\"firstName\":\"[^\"]+\",\"middleName\":(?:null|\"[^\"]+\"),\"lastName\":\"[^\"]+\"\},\"channelAccess\":[^,]+,\"consent\":\"\d+\",\"shopping\":\"\d+\",\"enrolled\":[^,]+,\"hasEligibleCPC\":[^,]+(?:,\"cavOnly\":\s*[^,\}]+|)(?:,\"ageMajority\":\s*[^,\}]+|)/")
                    || $this->http->FindPreg("/^\{\"clientInfo\":\{\"loyaltyIdHash\":\"[^\"]+\",\"srfHash\":\"[^\"]+\",\"clientType\":\"Retail\",\"clientName\":\{\"firstName\":\"[^\"]+\",\"middleName\":(?:null|\"[^\"]+\"),\"lastName\":\"[^\"]+\"\}\}\}/")
                )
            ) {
                $this->SetBalanceNA();
            }
        }// if ($this->http->ParseForm("rewardHome"))
        elseif ($RBCRewardsLink = $this->http->FindSingleNode("//a[@class = 'icon-link-btn' and contains(., 'Go to RBC Rewards') or contains(text(), 'Aller á RBC Récompenses')]/@href")
        ) {
            $this->logger->debug("Go to RBC Rewards -> $RBCRewardsLink");
            $this->http->NormalizeURL($RBCRewardsLink);
            $this->http->GetURL($RBCRewardsLink);
            $noSubmit = true;
        }// if (($doneURL = $this->http->FindPreg("/function\s*done\(\)\s*\{\s*var\s*url=\"([^\"]+)/ims")) && isset($RBCRewardsLink))
        // RBC Rewards -> link "Redeem Now"
        elseif (($formURL = $this->http->FindPreg("/form\s*action=\"([^\"]+)\"\s*name=\"rewardHome\"/ims")) || $noSubmit) {
            if (!$noSubmit) {
                $this->http->NormalizeURL($formURL);
                $this->http->PostURL($formURL, []);
            }
            //# Name
            $this->SetProperty("Name", beautifulName($this->http->FindSingleNode("//p[@id = 'globalheader-secureinfo']/strong")));
            //@ Sub Accounts
            $nodes = $this->http->XPath->query("//table[@class = 'contentframework']//tr[td]");
            $this->logger->debug("Total nodes found: " . $nodes->length);

            for ($i = 0; $i < $nodes->length; $i++) {
                $card = $this->http->FindSingleNode("td[1]", $nodes->item($i));
                $accountNumber = $this->http->FindSingleNode("td[2]", $nodes->item($i));
                //# RBC Rewards Points (Avion Air Travel eligible)
                $balance = $this->http->FindSingleNode("td[3]", $nodes->item($i));
                //# RBC Rewards Points
                if (empty($balance) && $balance != '0') {
                    $balance = $this->http->FindSingleNode("td[4]", $nodes->item($i));
                }

                if (isset($card, $accountNumber, $balance)) {
                    $subAccounts[] = [
                        'Code'        => 'rbcbank' . $i,
                        'DisplayName' => $card . ' (' . $accountNumber . ')',
                        'Balance'     => $balance,
                    ];
                }// if (isset($card, $accountNumber, $balance))
            }// for ($o = 0; $i < $nodes->length; $i++)

            if (isset($subAccounts)) {
                //# Set Sub Accounts
                $this->SetProperty("CombineSubAccounts", false);
                $this->logger->debug("Total subAccounts: " . count($subAccounts));
                //# Set Balance n\a
                $this->SetBalanceNA();
                //# Set SubAccounts Properties
                $this->SetProperty("SubAccounts", $subAccounts);
            }// if(isset($subAccounts))
            // RBC Rewards points may not be available for this account.
            elseif ($message = $this->http->FindSingleNode("//p[contains(text(), 'RBC Rewards points may not be available for this account.')]")) {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }
            // RBC Rewards Points information is temporarily unavailable.
            elseif ($message = $this->http->FindSingleNode("//p[contains(text(), 'RBC Rewards Points information is temporarily unavailable.')]")) {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }
        }// elseif (($formURL = $this->http->FindPreg("/form\s*action=\"([^\"]+)\"\s*name=\"rewardHome\"/ims")) || $noSubmit)
        elseif ($this->http->ParseForm("form_bkcc")) {
            $this->logger->notice("See credit card details for moving to 'RBC Rewards'");
            $formURL = $this->http->FormURL;
            $form = $this->http->Form;
            // Name
            $this->SetProperty("Name", beautifulName($this->http->FindSingleNode("//p[@id = 'globalheader-secureinfo']/strong/a")));
            // Sub Accounts
            $nodes = $this->http->XPath->query("//form[@name = 'form_bkcc']/ancestor::tr[1]//tr[td[2]/a]");
            $this->logger->debug("Total nodes found: " . $nodes->length);

            for ($i = 0; $i < $nodes->length; $i++) {
                $displayName = $this->http->FindSingleNode("td[2]/a", $nodes->item($i));
                $accountNumber = $this->http->FindSingleNode("td[2]/a", $nodes->item($i), true, "/[\d\-]+/");

                if (!$accountNumber) {
                    $accountNumber = str_replace(' ', '', $this->http->FindSingleNode("td[2]/a", $nodes->item($i)));
                }
                // save form data
                $http2 = clone $this->http;
                $this->http->brotherBrowser($http2);
                $http2->FormURL = $formURL;
                $http2->Form = $form;
                // fill in the form
                $parts = explode(';', $this->http->FindSingleNode("td[2]/a/@href", $nodes->item($i), true, "/javascript:(.+)/"));
                $this->logger->debug(var_export($parts, true), ['pre' => true]);

                foreach ($parts as $part) {
                    if (strstr($part, 'if((')) {
                        $this->logger->debug("Condition");
                        $this->logger->debug(var_export($part, true), ["pre" => true]);
                    } else {
                        preg_match("/document\.form_bkcc\.(?<name>[^\.]+)\.value=\'(?<value>.+)\'/ims", $part, $matches);

                        if (isset($matches['name'], $matches['value'])) {
                            $http2->SetInputValue($matches['name'], $matches['value']);
                        }
                    }
                }
                // Condition
                if (isset($http2->Form['ACCOUNT_TYPE'], $http2->Form['NOTE'], $http2->Form['MOBILE_INDICATOR'])) {
                    if (($http2->Form['ACCOUNT_TYPE'] == 'V' && $http2->Form['NOTE'] == 18)
                        && $http2->Form['MOBILE_INDICATOR'] != '1') {
                        $http2->FormURL = $http2->Form['CCAD_7ASCR_NA'];
                    }
                }
                $http2->PostForm();
                // RBC Rewards® Points Balance
                $balance = $http2->FindSingleNode("//a[contains(@href, 'PARM1=PointsActivity')]/span");

                if (isset($displayName, $accountNumber, $balance)) {
                    $subAccounts[] = [
                        'Code'        => 'rbcbank' . $accountNumber,
                        'DisplayName' => $displayName,
                        'Balance'     => $balance,
                    ];
                }// if (isset($card, $accountNumber, $balance))
                elseif ($nodes->length == 1) {
                    // We are experiencing temporary problems. Please try again later.
                    if ($message = $this->http->FindSingleNode("//p[contains(text(), 'We are experiencing temporary problems. Please try again later.')]")) {
                        throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
                    }
                }// elseif ($nodes->length == 1)
            }// for ($o = 0; $i < $nodes->length; $i++)

            if (isset($subAccounts)) {
                //# Set Sub Accounts
                $this->SetProperty("CombineSubAccounts", false);
                $this->http->Log("Total subAccounts: " . count($subAccounts));
                //# Set Balance n\a
                $this->SetBalanceNA();
                //# Set SubAccounts Properties
                $this->SetProperty("SubAccounts", $subAccounts);
            }// if(isset($subAccounts))
            /*
            else {
                $this->logger->debug(">>{$this->http->FindSingleNode('//div[h2[contains(text(), "Credit Cards")]]/parent::node()')}<<");
                if (empty($this->Properties['Name'])) {
                    $this->SetProperty("Name", beautifulName($this->http->FindSingleNode("//span[@class = 'name-text']")));
                }
                if (
                    (
                        // AccountID: 4998976
                        strstr($this->http->FindSingleNode('//div[h2[contains(text(), "Credit Cards")]]/parent::node()'), 'Credit Cards Apply for a Credit Card')
                    )
                    && !empty($this->Properties['Name'])
                ) {
                    $this->SetBalanceNA();
                }
            }
            */
        }// elseif ($this->http->ParseForm("form_bkcc"))
        elseif ($this->http->FindSingleNode("//div[@id = 'creditCardsBox']//h3[contains(text(), 'Credit Cards')]")) {
            $this->logger->notice("New design?");
            // Name
            $this->SetProperty("Name", beautifulName($this->http->FindSingleNode("//p[@id = 'globalheader-secureinfo']/strong/a")));

            if (isset($this->Properties['Name'])) {
                $this->SetBalanceNA();
            }
        }// elseif ($this->http->FindSingleNode("//div[@id = 'creditCardsBox']//h3[contains(text(), 'Credit Cards')]"))
        // ?
        else {
            if ($this->http->ParseForm("nickCancel")) {
                $this->http->PostForm();
            }

            if ($this->http->ParseForm("sipwasme")) {
                $this->http->PostForm();
            }
            //# Please review and update your Personal Verification Questions and answers
            if ($message = $this->http->FindSingleNode("//td[contains(text(), 'Please review and update your Personal Verification Questions and answers')]")) {
                throw new CheckException("RBC Rewards Banking Account website is asking you to update your personal verification questions and answers, until you do so we would not be able to retrieve your account information.", ACCOUNT_PROVIDER_ERROR);
            }/*checked*/
            // We are experiencing temporary problems. Please try again later.
            if ($message = $this->http->FindSingleNode("//p[contains(text(), 'We are experiencing temporary problems. Please try again later.')]")) {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }
            // We are experiencing technical difficulties. Please try again later.
            if ($message = $this->http->FindSingleNode("//p[contains(text(), 'We are experiencing technical difficulties. Please try again later.')]")) {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }

            // We Need a Few Minutes of Your Time -> I have read and agree to be legally bound by the terms of the Electronic Access Agreement.
            if ($message = $this->http->FindSingleNode('//label[contains(text(), "I have read and agree to be legally bound by the terms of the Electronic Access Agreement.")]')) {
                $this->throwAcceptTermsMessageException();
            }

            // hard code
            $this->logger->debug(">>{$this->http->FindSingleNode("//section[@id = 'creditCards']")}<<");

            if (!isset($this->Properties['Name'])) {
                $this->SetProperty("Name", beautifulName($this->http->FindSingleNode("//div[@class = 'banner_name']/div/button | //p[@id = 'globalheader-secureinfo']/strong/a")));
            }

            if (
                (
                    $this->http->FindSingleNode("//title[text() = 'Business Accounts - RBC Online Banking']") // in_array($this->AccountFields['Login'], ['4519023125713675', 'x2networks'])
                    || in_array($this->http->FindSingleNode("//section[@id = 'creditCards']"), ['Credit Cards Credit Cards Table Apply for a Credit Card', 'Cartes de crédit Cartes de crédit Table Demander une carte de crédit'])
                    // AccountID: 4170838
                    || strstr($this->http->FindSingleNode("//section[@id = 'creditCards']"), 'Credit Cards Credit Cards Table Account Name Balance WestJet RBC® World Elite MasterCard')
                    // AccountID: 4851968
                    || strstr($this->http->FindSingleNode("//section[@id = 'creditCards']"), 'Cartes de crédit Cartes de crédit Table Nom du compte Solde British Airways')
                )
                && !empty($this->Properties['Name'])
            ) {
                $this->SetBalanceNA();
            }
        }

        // refs #21681
        if (isset($this->Properties['SubAccounts']) && count($this->Properties['SubAccounts']) > 1) {
            $subAccountBalance = 0;

            foreach ($this->Properties['SubAccounts'] as &$subAccount) {
                $subAccountBalance += floatval(str_replace([',', '.'], ['', ','], $subAccount['Balance']));
                $subAccount['BalanceInTotalSum'] = true;
            }

            $this->SetBalance($subAccountBalance);
        }

        // refs #14494
        $this->logger->info('FICO® Score', ['Header' => 3]);

        if (!$ficoScore) {
            return;
        }
        $this->http->NormalizeURL($ficoScore);
        $this->http->GetURL($ficoScore);

        if ($this->http->ParseForm("frm1")) {
            $this->http->PostForm();
        }
        // FICO® SCORE
        $fcioScore = $this->http->FindSingleNode("//script[@id = 'UserData']", null, true, "/\"score\":(\d+)/");
        // FICO Score updated on
        $fcioUpdatedOn = $this->http->FindSingleNode("//script[@id = 'UserData']", null, true, "/SingleCreditReport\",\"date\":\"([^\"]+)/");

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
                "Code"               => "rbcbankFICO",
                "DisplayName"        => "FICO® Score (TransUnion)",
                "Balance"            => $fcioScore,
                "FICOScoreUpdatedOn" => $fcioUpdatedOn,
            ]);
        }// if ($fcioScore && $fcioUpdatedOn)
    }

    // ------------- usa --------------

    public function LoadLoginFormOfUSA()
    {
        $this->http->removeCookies();
        $this->http->GetURL("https://www.rbcbankusaredemption.com/pages/user/login.aspx");

        if (!$this->http->ParseForm("form1")) {
            return false;
        }
        $this->http->SetInputValue('ctl00$contentMain$txtLoginName', $this->AccountFields["Login"]);
        $this->http->SetInputValue('ctl00$contentMain$txtLoginPassword', $this->AccountFields["Pass"]);
        $this->http->SetInputValue('__EVENTTARGET', 'ctl00$contentMain$lnkSubmitLogin');

        return true;
    }

    public function LoginOfUSA()
    {
        if (!$this->http->PostForm()) {
            return false;
        }
        // You have entered an invalid User Name and/or password.
        if ($error = $this->http->FindSingleNode("//p[contains(text(), 'You have entered an invalid User Name and/or password.')]")) {
            throw new CheckException($error, ACCOUNT_INVALID_PASSWORD);
        }
        // There was an error processing your request. If this problem persists, please contact the website administrator.
        if ($error = $this->http->FindSingleNode("//li[contains(text(), 'There was an error processing your request.')]")) {
            throw new CheckException($error, ACCOUNT_PROVIDER_ERROR);
        }
        //# This card has an invalid account status. Please contact customer service.
        $this->CheckError($this->http->FindSingleNode("//div[@id = 'valSummary']/ul/li"));

        if ($this->http->FindSingleNode("(//a[contains(@href, 'logout')]/@href)[1]")) {
            return true;
        }
        // This card has an invalid account status.
        if ($error = $this->http->FindSingleNode("//li[contains(text(), 'This card has an invalid account status.')]")) {
            throw new CheckException($error, ACCOUNT_INVALID_PASSWORD);
        }
        // The server is experiencing a problem with the page you requested.
        if ($error = $this->http->FindSingleNode("//p[contains(text(), 'The server is experiencing a problem with the page you requested.')]")) {
            throw new CheckException($error, ACCOUNT_PROVIDER_ERROR);
        }
        // Your RBC Rewards account has been disabled for security reasons. For immediate assistance please contact customer service at 1-877-521-2035.
        if ($error = $this->http->FindSingleNode("//div[contains(text(), 'Your RBC Rewards account has been disabled for security reasons. For immediate assistance please contact customer service at 1-877-521-2035.')]")) {
            throw new CheckException($error, ACCOUNT_LOCKOUT);
        }

        return false;
    }

    public function ParseOfUSA($url = "https://www.rbcbankusaredemption.com/pages/user/account.aspx")
    {
        $this->http->GetURL($url);

        if ($this->http->currentUrl() == 'https://www.rbcbankusaredemption.com/pages/user/login.aspx') {
            $this->http->GetURL($url);
        }
        // User ID
        $this->SetProperty("Name", beautifulName($this->http->FindSingleNode("//span[contains(@id, 'lblLoggedInUser')]", null, true, "/\,\s*([^<]+)/")));
        // Account
        $this->SetProperty("Number", $this->http->FindSingleNode("//span[contains(text(), 'Account Ending in:')]/following-sibling::span[1]"));
        // Balance - Total Points Available
        $this->SetBalance($this->http->FindSingleNode("//span[contains(text(), 'Total Points Available:')]/following-sibling::span[1]", null, true, "/([\d\.\,]+)/ims"));
        // Reward Points Earned
        $this->SetProperty("Earned", $this->http->FindSingleNode("//div[contains(@id, 'contentMain_divRewardPointsEarned')]/div", null, true, "/([\d\.\,]+)/ims"));
        // Bonus Rewards Points Earned
        $this->SetProperty("BonusRewards", $this->http->FindSingleNode("//div[contains(@id,'contentMain_divBonusPointsEarned')]/div", null, true, "/([\d\.\,]+)/ims"));
        // Other Rewards Points Earned
        $this->SetProperty("OtherRewards", $this->http->FindSingleNode("//div[contains(@id,'contentMain_divOtherPointsEarned')]/div", null, true, "/([\d\.\,]+)/ims"));
        // Reward Points Adjusted
        $this->SetProperty("PointsAdjusted", $this->http->FindSingleNode("//div[contains(@id,'contentMain_divPointsAdjusted')]/div", null, true, "/([\d\.\,]+)/ims"));
        // Reward Points Redeemed
        $this->SetProperty("PointsRedeemed", $this->http->FindSingleNode("//div[contains(@id,'contentMain_divPointsRedeemed')]/div", null, true, "/([\d\.\,]+)/ims"));

        // Points Expiring on ...
        $this->SetProperty("Expiring", $this->http->FindSingleNode("//strong[contains(text(), 'Expiring on')]", null, true, "/([\d\.\,]+)\s*Point/ims"));
        // Expiration date
        if (isset($this->Properties['Expiring']) && $this->Properties['Expiring'] != '0') {
            $exp = $this->http->FindSingleNode("//strong[contains(text(), 'Expiring on')]", null, true, "/Expiring on\s*([^<]+)/ims");

            if (strtotime($exp)) {
                $this->SetExpirationDate(strtotime($exp));
            }
        }// if (isset($this->Properties['Expiring']) && $this->Properties['Expiring'] != '0')
        else {
            $nodes = $this->http->XPath->query("//div[contains(@id, 'contentMain_divPointsExpired')]/following-sibling::div[1]/div");
            $this->logger->debug("Total {$nodes->length} nodes were found");

            for ($i = 0; $i < $nodes->length; $i++) {
                $this->logger->debug(Html::cleanXMLValue($nodes->item($i)->nodeValue));
                $exp = $this->http->FindSingleNode("text()[1]", $nodes->item($i));
                $points = $this->http->FindSingleNode("div", $nodes->item($i), true, "/([\d\.\,]+)\s*Point/ims");

                if ($points > 0) {
                    $this->SetProperty("Expiring", $points);

                    if (strtotime($exp)) {
                        $this->SetExpirationDate(strtotime($exp));
                    }

                    break;
                }// if ($points > 0)
            }// for ($i = 0; $i < $nodes->length; $i++)
        }
    }

    protected function checkRegionSelection($region)
    {
        if (!in_array($region, array_flip($this->regionOptions)) || empty($region)) {
            if ($region === 'CanadaRBCRewards') {
                return 'Canada';
            }

            $region = 'USA';
            $this->http->SetProxy($this->proxyReCaptcha());
        }

        return $region;
    }

    private function selenium()
    {
        $this->logger->notice(__METHOD__);

        $selenium = clone $this;
        $retry = false;
        $this->http->brotherBrowser($selenium->http);

        try {
            $this->logger->notice("Running Selenium...");
            $selenium->UseSelenium();
            $selenium->useFirefox();
            $selenium->setKeepProfile(true);
            /*
            $selenium->disableImages();
            */
            $selenium->useCache();
            $selenium->http->start();
            $selenium->Start();
            $selenium->http->saveScreenshots = true;

            $selenium->http->removeCookies();

            try {
                $selenium->http->GetURL("https://www1.royalbank.com/cgi-bin/rbaccess/rbcgi3m01?F6=1&F7=IB&F21=IB&F22=IB&REQUEST=ClientSignin&LANGUAGE=ENGLISH");
            } catch (Facebook\WebDriver\Exception\TimeoutException | ScriptTimeoutException | TimeOutException $e) {
                $this->logger->error("TimeOutException exception: " . $e->getMessage());
                $selenium->driver->executeScript('window.stop();');
            }

            $loginInput = $selenium->waitForElement(WebDriverBy::xpath('//input[@name = "K1"]'), 5, false);
            $passwordInput = $selenium->waitForElement(WebDriverBy::xpath('//input[@name = "QQ"]'), 0);
            $this->saveToLogs($selenium);

            if (!$loginInput || !$passwordInput) {
                return false;
            }
//            $loginInput->sendKeys($this->AccountFields['Login']);
            $selenium->driver->executeScript("
                $('form[name = \"rbunxcgi\"]').find('input[name = \"K1\"]').val('{$this->AccountFields['Login']}');
            ");
            $passwordInput->sendKeys($this->AccountFields['Pass']);
            $this->saveToLogs($selenium);

            $selenium->driver->executeScript("
                $('button.yellowBtnLarge').click();
            ");
//                window.stop();

            $selenium->waitForElement(WebDriverBy::xpath('
                //td[label[@for = "pvqQuestion"]]/following::td[1]
                | //label[@for = "pvqQInput"]
                | //p[
                    contains(text(), "To verify your identity, look for a notification from RBC Mobile on your trusted device")
                    or contains(text(), "Pour vérifier votre identité, cherchez une notification de l’appli Mobile RBC sur votre appareil de confiance.")
                ]
                | //button[
                    contains(text(), "Select Another Option")
                    or contains(text(), "Sélectionner une autre option")
                ]
                | //button[@data-rb-role = "sign_out"]
                | //button[@ga-event-label = "Sign out"]
                | //button[@rbcportalsubmit = "Signout"]
                | //span[contains(text(), "Personal Verification Question") or contains(text(), "Question d’identification personnelle")]
                | //p[contains(text(), "For your protection, access to your online service is blocked.")]
                | //h1[contains(text(), "Nous éprouvons actuellement des problèmes techniques")]
                | //p[contains(text(), "For security reason your last request has been blocked.")]
            '), 7);
            $this->saveToLogs($selenium);

            if ($anotherOption = $selenium->waitForElement(WebDriverBy::xpath('//button[
                    contains(text(), "Select Another Option")
                    or contains(text(), "Sélectionner une autre option")
                ]'), 0)
            ) {
                $anotherOption->click();
                $selenium->waitForElement(WebDriverBy::xpath('//span[contains(text(), "Personal Verification Question") or contains(text(), "Question d’identification personnelle")]'), 3);
                $this->saveToLogs($selenium);
            }

            if ($qs = $selenium->waitForElement(WebDriverBy::xpath('//span[contains(text(), "Personal Verification Question") or contains(text(), "Question d’identification personnelle")]'), 0)) {
                $qs->click();
                sleep(1);

                $selenium->waitForElement(WebDriverBy::xpath('
                    //td[label[@for = "pvqQuestion"]]/following::td[1]
                    | //label[@for = "pvqQInput"]
                    | //p[
                        contains(text(), "To verify your identity, look for a notification from RBC Mobile on your trusted device")
                        or contains(text(), "Pour vérifier votre identité, cherchez une notification de l’appli Mobile RBC sur votre appareil de confiance.")
                    ]
                    | //button[@data-rb-role = "sign_out"]
                    | //button[@ga-event-label = "Sign out"]
                    | //button[@rbcportalsubmit = "Signout"]
                '), 7);
                $this->saveToLogs($selenium);
            }

            $question = $this->http->FindSingleNode('//label[@for = "pvqQInput"]');

            if ($question) {
                if (!isset($this->Answers[$question])) {
                    $this->AskQuestion($question, null, "QuestionNew");

                    return false;
                }

                $answer = $this->Answers[$question];
                $answerInput = $selenium->waitForElement(WebDriverBy::xpath('//input[@id = "pvqQInput"]'), 0);
                $answerInput->sendKeys($answer);
                $cont = $selenium->waitForElement(WebDriverBy::xpath('//button[@data-dig-id="MFA_PMSM_022"]'), 0);
                $this->saveToLogs($selenium);
                $cont->click();
                $selenium->waitForElement(WebDriverBy::xpath('
                    //button[@data-rb-role = "sign_out"]
                    | //button[@ga-event-label = "Sign out"]
                    | //button[@rbcportalsubmit = "Signout"]
                    | //span[@id = "error-pvq-input"]
                '), 7);
                $this->saveToLogs($selenium);
                // waiting an error
                $this->logger->debug("waiting an error...");
                $error = $selenium->waitForElement(WebDriverBy::xpath('//span[@id = "error-pvq-input"]'), 0);

                if ($error) {
                    $this->saveToLogs($selenium);
                    $error = $error->getText();
                    $answerInput->clear();

                    unset($this->Answers[$question]);

                    $this->logger->error("Error -> {$error}");
                    $this->AskQuestion($question, $error, "QuestionNew");

                    return false;
                }
            }

            $i = 0;
            $isLoginVianorifications = false;

            while (
                $selenium->waitForElement(WebDriverBy::xpath('//p[
                    contains(text(), "To verify your identity, look for a notification from RBC Mobile on your ")
                    or contains(text(), "Pour vérifier votre identité, cherchez une notification de l’appli Mobile RBC sur votre appareil")
                ]'), 0)
                && $i < 20
            ) {
                $isLoginVianorifications = true;
                $this->saveToLogs($selenium);
                $sleep = 15;
                $this->logger->debug("sleep {$sleep}");
                sleep($sleep);
                $this->saveToLogs($selenium);
                $i++;
                $this->increaseTimeLimit();
            }

            if ($message = $this->http->FindSingleNode('//p[
                    contains(text(), "To verify your identity, look for a notification from RBC Mobile on your")
                    or contains(text(), "Pour vérifier votre identité, cherchez une notification de l’appli Mobile RBC sur votre appareil")
                ]
                | //h1[contains(text(), "We’re Having Technical Issues")]
                | //h1[contains(text(), "Nous éprouvons actuellement des problèmes techniques")]
            ')) {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }

            if ($message = $this->http->FindSingleNode('//p[contains(text(), "For security reason your last request has been blocked.")]')) {
                $this->DebugInfo = $message;
                $this->ErrorReason = self::ERROR_REASON_BLOCK;

                return false;
            }

            if ($error = $selenium->waitForElement(WebDriverBy::xpath('//p[contains(text(), "For your protection, access to your online service is blocked.")]'), 0)) {
                throw new CheckException($error->getText(), ACCOUNT_LOCKOUT);
            } elseif ($isLoginVianorifications && $this->http->FindSingleNode('//input[@id = "K1"]/@class')) {
                throw new CheckException('To verify your identity, look for a notification from RBC Mobile on your trusted device. You have 5 minutes before it expires.', ACCOUNT_PROVIDER_ERROR);
            }

            /*
            $question = $this->http->FindSingleNode('//td[label[@for = "pvqQuestion"]]/following::td[1]');
            if ($question && isset($this->Answers[$question])) {
                $answer = $this->Answers[$question];
                $answerInput = $selenium->waitForElement(WebDriverBy::xpath('//input[@name = "SIP_PVQ_ANS"]'), 0);
                $cont = $selenium->waitForElement(WebDriverBy::xpath('//a[@id = "id_btn_continue"]'), 0);
                if ($answerInput && $cont) {
                    $answerInput->sendKeys($answer);
                    $selenium->driver->executeScript('$(\'#SIP_ALWAYSASK\').prop(\'checked\', false)');
                    $this->saveToLogs($selenium);
                    $cont->click();
                    if ($cont = $selenium->waitForElement(WebDriverBy::xpath('//a[@id = "id_btn_thatwasme"]'), 3)) {
                        $this->saveToLogs($selenium);
                        $cont->click();
                    }
                    $selenium->waitForElement(WebDriverBy::xpath('
                        //button[@data-rb-role = "sign_out"]
                        | //button[@ga-event-label = "Sign out"]
                        | //button[@rbcportalsubmit = "Signout"]
                    '), 7);
                }
                $this->saveToLogs($selenium);
            }
            */

            $cookies = $selenium->driver->manage()->getCookies();

            foreach ($cookies as $cookie) {
                $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
            }

//            if ($xKey = $selenium->waitForElement(WebDriverBy::xpath('//form[@name = "rbunxcgi"]//input'), 5, false)) {
//                foreach ($selenium->driver->findElements(WebDriverBy::xpath('//form[@name = "rbunxcgi"]//input', 0, false)) as $index => $xKey) {
//                    $xKeys[] = [
//                        'name'  => $xKey->getAttribute("name"),
//                        'value' => $xKey->getAttribute("value")
//                    ];
//                }
//                $this->logger->debug(var_export($xKeys, true), ["pre" => true]);
//            }

            $this->saveToLogs($selenium);
            $this->logger->debug("[Current URL]: {$selenium->http->currentUrl()}");
        } catch (
            Facebook\WebDriver\Exception\WebDriverCurlException
            $e
        ) {
            $this->logger->error("Exception: " . $e->getMessage(), ['HtmlEncode' => true]);
            $retry = true;
        } catch (ScriptTimeoutException | TimeOutException $e) {
            $this->logger->error("Timeout exception: " . $e->getMessage());
            $this->DebugInfo = "TimeOutException";
            // retries
            if (strstr($e->getMessage(), 'timeout')) {
                $retry = true;
            }
        }// catch (ScriptTimeoutException $e)
        finally {
            // close Selenium browser
            $selenium->http->cleanup();
            // retries
            if ($retry && ConfigValue(CONFIG_SITE_STATE) != SITE_STATE_DEBUG) {
                throw new CheckRetryNeededException(3);
            }
        }

        return true;
    }

    private function saveToLogs($selenium)
    {
        $this->logger->notice(__METHOD__);
        // save page to logs
        $selenium->http->SaveResponse();
        // save page to logs
        $this->http->SetBody($selenium->driver->executeScript('return document.documentElement.innerHTML'));
        $this->http->SaveResponse();
    }
}
