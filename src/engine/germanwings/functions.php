<?php

use AwardWallet\Engine\ProxyList;

class TAccountCheckerGermanwings extends TAccountChecker
{
    use ProxyList;

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->setProxyBrightData(null, "static", "de");
        $this->http->setHttp2(true);
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->GetURL('https://www.eurowings.com/en/your-rewards/my-eurowings/my-eurowings/login.html');
//        $this->http->GetURL('https://www.eurowings.com/skysales/BoomerangSearch.aspx?culture=de-DE');

//        if (!$this->http->ParseForm("SkySales")) {
//            return $this->checkErrors();
//        }

        $csrf = $this->http->FindPreg("/:csrf-token=\"'([^\']+)/");
        $xcsrftoken = $this->http->FindPreg("/xcsrftoken = \"([^\"]+)/");

        if (!$xcsrftoken || !$csrf) {
            return $this->checkErrors();
        }

        //$this->http->GetURL("https://www.eurowings.com/libs/granite/csrf/token.json");

        $this->http->RetryCount = 0;
        $data = [
            'accountType' => 'CUSTOMER',
            'username'    => $this->AccountFields['Login'],
            'password'    => $this->AccountFields['Pass'],
            'referrerUrl' => "/en.html",
            'locale'      => "en-GB",
            'csrfToken'   => $csrf,
            'processType' => '',
        ];
        $this->http->PostURL('https://www.eurowings.com/services/authentication/v3/login', json_encode($data), [
            'Accept'              => 'application/json, text/plain, */*',
            'Content-Type'        => 'application/json;charset=UTF-8',
            'csrf-token'          => 'undefined',
            'x-sec-clge-req-type' => 'ajax',
        ]);
        $this->http->RetryCount = 2;

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);
        // Maintenance
        if ($this->http->FindPreg("/<title>Eurowings - Wartungsarbeiten - Maintenance<\/title>/")
            || $this->http->FindPreg("/<title>Germanwings - Wartungsarbeiten - Maintenance<\/title>/")) {
            throw new CheckException("Due to routine maintenance on our booking system our website and mobile services are temporarily unavailable. We will back shortly.", ACCOUNT_PROVIDER_ERROR);
        }
        // Maintenance
        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'Due to routine maintenance work, the Boomerang Club is currently unavailable.') or contains(text(), 'Aufgrund von routinemäßigen Wartungsarbeiten ist der Boomerang Club im Moment nicht erreichbar')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // Service Unavailable
        if ($message = $this->http->FindSingleNode("
                //h1[contains(text(), 'Service Unavailable')]
                | //h2[contains(text(), 'Service Unavailable')]
            ")
        ) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        if ($this->http->FindSingleNode("
                //h1[contains(text(), 'Internal Server Error - Read')]
            ")
        ) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        // empty body
        if (empty($this->http->Response['body'])) {
            throw new CheckRetryNeededException(2, 10);
        }

        return false;
    }

    public function Login()
    {
        if ($this->http->FindPreg('/"loginSuccessful":"false"/')
            // TODO: hard code, 2389785, 4433196
            || ($this->http->Response['code'] == 404 && (mb_strlen($this->AccountFields['Pass']) > 16 || strstr($this->AccountFields['Pass'], '}')))) {
            throw new CheckException('Username and password do not match.', ACCOUNT_INVALID_PASSWORD);
        }

        if ($this->http->FindPreg('/\[ \]/') || $this->http->Response['code'] == 404 || $this->http->Response['code'] == 403) {
            $this->logger->debug('Send Old Form');
//            $this->http->GetURL('https://www.eurowings.com/skysales/MyDashboard.aspx?culture=de-DE');
//            $this->http->GetURL('https://www.eurowings.com/skysales/BoomerangSearch.aspx');
            $this->http->GetURL('https://www.eurowings.com/skysales/MyMilesBoomerangClub.aspx?culture=en-GB');

            if (!$this->http->ParseForm("SkySales")) {
                $this->hardCodeFormSomAccounts();

                return $this->checkErrors();
            }
            $this->http->SetInputValue('LoginViewLoginControlMember$FIRST_INPUT_CONTROL_GWUser', $this->AccountFields['Login']);
            $this->http->SetInputValue('LoginViewLoginControlMember$SECOND_INPUT_CONTROL_GWUser', $this->AccountFields['Pass']);
            $this->http->SetInputValue('__EVENTTARGET', 'LoginViewLoginControlMember$ButtonSubmit');
//            $this->http->SetInputValue('BoomerangLoginViewLoginControl$FIRST_INPUT_CONTROL_Boomerang', $this->AccountFields['Login']);
//            $this->http->SetInputValue('BoomerangLoginViewLoginControl$SECOND_INPUT_CONTROL_Boomerang', $this->AccountFields['Pass']);
//            $this->http->SetInputValue('__EVENTTARGET', 'BoomerangLoginViewLoginControl$ButtonSubmit');
            //Error : Please update your personal data on www.germanwings.com
            if (!$this->http->PostForm()) {
                //if ($this->http->Response['code'] == 404 && $this->http->FindSingleNode("//h2[contains(text(), '404 - File or directory not found.')]"))
                //    throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
                return $this->checkErrors();
            }

            $this->hardCodeFormSomAccounts();

            // AccountID: 3120073, 4336169

            // Set new password now
            if ($this->http->FindSingleNode("//p[contains(text(), 'Set new password now:') or contains(text(), 'Jetzt neues Passwort festlegen:')]")) {
                $this->throwProfileUpdateMessageException();
            }

            // Name
            $this->http->GetURL("https://www.eurowings.com/skysales/MySettingsProfile.aspx");
            $this->SetProperty("Name", beautifulName($this->http->FindSingleNode('//input[@id = "MySettingsProfileViewControlGroupMySettingsProfile_MySettingsProfileViewControlGroupUserRegisterControl_FirstName"]/@value') . " " . $this->http->FindSingleNode('//input[@id = "MySettingsProfileViewControlGroupMySettingsProfile_MySettingsProfileViewControlGroupUserRegisterControl_LastName"]/@value')));
        } elseif ($this->http->Response['body'] == '[{"passwordExpired":"true"}]') {
            throw new CheckException('Username and password do not match.', ACCOUNT_INVALID_PASSWORD);
        }

        $response = $this->http->JsonLog();
        $statusMessage = $response->statusMessage ?? null;

        if ($statusMessage) {
            $this->logger->error("[Error]: {$statusMessage}");

            if (
                $statusMessage == 'Wrong username or password'
                || $statusMessage == 'Your password has expired. Please change it now.'
            ) {
                throw new CheckException($statusMessage, ACCOUNT_INVALID_PASSWORD);
            }

            if ($statusMessage == 'This account is blocked. Please reset your password to unblock your account by clicking on the "Forgot password" link.') {// todo: false positive error
                throw new CheckRetryNeededException(2, 0, "This account is blocked. Please reset your password to unblock your account by clicking on the \"Forgot password\" link.", ACCOUNT_INVALID_PASSWORD);
            }

            $this->DebugInfo = $statusMessage;

            return false;
        }

        // AccountID: 1490548
        if ($this->http->Response['code'] == 400 && $this->http->FindPreg("/Bad Request/")) {
            throw new CheckException('An error has occurred. Please try again later.', ACCOUNT_INVALID_PASSWORD);
        }

        $this->checkErrors();

        $this->http->GetURL('https://www.eurowings.com/skysales/BoomerangSearch.aspx?culture=en-GB');
        // Access is allowed
        if ($this->http->FindSingleNode("//a[contains(@class, 'logoutLink')]/@href")
            || $this->http->FindSingleNode("//h3[contains(@id, 'header-subnavigation-myew')]")
            || $this->http->FindSingleNode("//div[p[contains(text(), 'Your Boomerang Club number:')]]/following-sibling::div[1] | //div[contains(text(), 'Your Boomerang Club number:')]/following-sibling::div[1]")) {
            return true;
        }

        if ($this->http->FindPreg("/(?:Sign up now for the Boomerang Club!|Registrieren Sie sich jetzt für den Boomerang Club!|Inscrivez-vous dès maintenant au Boomerang Club|Meld u nu aan bij de Boomerang Club)/ims")) {
            throw new CheckException(self::NOT_MEMBER_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        return $this->checkErrors();
    }

    public function hardCodeFormSomAccounts()
    {
        $this->logger->notice(__METHOD__);

        if (
            $this->http->FindSingleNode("//form[@id='SkySales']/@action")
            || $this->http->currentUrl() == 'https://www.eurowings.com/de/ihre-vorteile/my-eurowings/my-eurowings/login.html'
        ) {
            // Username and password do not match.
            if (in_array($this->AccountFields['Login'], [
                'hendrik.kloeters@gmail.com',
                'Fuhrmann.timo@gmail.com',
                'erik.fjerdingen@yahoo.no',
                'jan.beerbaum@googlemail.com',
            ]
            )) {
                throw new CheckException('Username and password do not match.', ACCOUNT_INVALID_PASSWORD);
            }
        }
    }

    public function Parse()
    {
        // Boomerang Club no.
        $this->SetProperty("BoomerangClubNo", $this->http->FindSingleNode("//div[p[contains(text(), 'Your Boomerang Club number:')]]/following-sibling::div[1] | //div[contains(text(), 'Your Boomerang Club number:')]/following-sibling::div[1]"));
        // Balance - Your Boomerang Club miles
        $this->SetBalance($this->http->FindSingleNode("//div[p[contains(text(), 'Your Boomerang Club miles:')]]/following-sibling::div[1] | //div[contains(text(), 'Your Boomerang Club miles:')]/following-sibling::div[1]"));

        if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR) {
            $this->checkErrors();

            if ($this->http->FindPreg("/(?:Sign up now for the Boomerang Club!|Registrieren Sie sich jetzt für den Boomerang Club!|Inscrivez-vous dès maintenant au Boomerang Club|Meld u nu aan bij de Boomerang Club)/ims")) {
                throw new CheckException(self::NOT_MEMBER_MSG, ACCOUNT_PROVIDER_ERROR);
            }
        }

        // Name
        $this->http->GetURL("https://www.eurowings.com/services/usercontext.json?ajax=true");
        $response = $this->http->JsonLog();
        $this->SetProperty("Name", beautifulName(($response->userInfo->firstname ?? null) . " " . ($response->userInfo->lastname ?? null)));
    }

    public function GetRedirectParams($targetURL = null)
    {
        $arg = parent::GetRedirectParams($targetURL);
        $arg["SuccessURL"] = "https://www.eurowings.com/skysales/BoomerangAccount.aspx";

        return $arg;
    }
}
