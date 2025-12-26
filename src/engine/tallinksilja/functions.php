<?php

use AwardWallet\Engine\ProxyList;

class TAccountCheckerTallinksilja extends TAccountChecker
{
    use ProxyList;

    public $regionOptions = [
        "EN"  => "International(EN)",
        "FIN"	=> "Finland",
        "SWE"	=> "Sweden",
        "EST"	=> "Estonia",
        "GER"	=> "Germany",
        "LAT"	=> "Latvia",
    ];

    private $auth;

    public function TuneFormFields(&$arFields, $values = null)
    {
        parent::TuneFormFields($arFields);
        $arFields["Login2"]["Options"] = $this->regionOptions;
    }

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->setHttp2(true);
        $this->http->SetProxy($this->proxyReCaptchaIt7());
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->GetURL("https://lv.tallink.com/lv/club-one");

        if (!$this->http->FindSingleNode("//a[contains(text(),'Club One')]")) {
            return false;
        }
        //$this->http->FormURL = $formURL;
        //$this->http->SetInputValue("username", $this->AccountFields["Login"]);
        //$this->http->SetInputValue("password", $this->AccountFields["Pass"]);
        $this->http->RetryCount = 0;
        $this->http->PostURL('https://sso.tallink.com/api/username-login', json_encode([
            'username' => $this->AccountFields["Login"],
            'password' => $this->AccountFields["Pass"],
        ]), [
            'Accept'       => 'application/json',
            'Content-Type' => 'application/json;charset=UTF-8',
        ]);
        $this->http->RetryCount = 2;
        $this->auth = $this->http->JsonLog();

        return true;
    }

    public function Login()
    {
        // form submission
        //if (!$this->http->PostForm())
        //	return false;
        if (isset($this->auth->token, $this->auth->userId)) {
            $this->http->setCookie('LFR_SESSION_STATE_10158', time() . date('B'), 'lv.tallink.com');
            $this->http->setCookie('ssoToken', $this->auth->token, 'lv.tallink.com');
            $this->http->setCookie('ssoUserId', $this->auth->userId, 'lv.tallink.com');

            return true;
        }
        // invalid credentials
        if (isset($this->auth->message)) {
            $message = $this->auth->message;
            $this->logger->error($message);
            /*
             * Unfortunately we could not find your account with these credentials.
             * Please try to log in with your Club One number or try other credentials
             */
            if ($message == 'WRONG_USERNAME_OR_PASSWORD') {
                throw new CheckException("Unfortunately we could not find your account with these credentials. Please try to log in with your Club One number or try other credentials", ACCOUNT_INVALID_PASSWORD);
            }

            if ($this->http->FindPreg('/I\/O error on POST request/', false, $message)) {
                throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
            }
            // Kaut kas nenotika kā plānots. Lūdzu, mēģini vēlreiz.
            if ($message == "User Requires Profile to Login") {
                throw new CheckException("Kaut kas nenotika kā plānots. Lūdzu, mēģini vēlreiz.", ACCOUNT_PROVIDER_ERROR);
            }
        }// if (isset($this->auth->message))

        if ($this->http->FindPreg("/nav korekti!?/ims")) {
            throw new CheckException("Login/password are not valid", ACCOUNT_INVALID_PASSWORD);
        }

        if ($this->http->FindPreg("/OK/")) {
            return true;
        }
        // Problēmas ar savienojumu
        if ($this->http->FindPreg("/Problēmas ar savienojumu/ims")) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }
        // for FIN region
        if ($this->AccountFields["Login2"] == 'FIN') {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Parse()
    {
        $this->http->PostURL('https://lv.tallink.com/lv/club-one?p_p_id=login_WAR_liferayapps&p_p_lifecycle=2&p_p_state=normal&p_p_mode=view&p_p_resource_id=sso-login&p_p_cacheability=cacheLevelPage', [
            'token'  => $this->auth->token,
            'userId' => $this->auth->userId,
        ], [
            'Accept'           => 'application/json, text/javascript, */*; q=0.01',
            'Content-Type'     => 'application/x-www-form-urlencoded; charset=UTF-8',
            'X-Requested-With' => 'XMLHttpRequest',
        ]);
        $this->http->GetURL('https://lv.tallink.com/lv/club-one');
        $response = $this->http->JsonLog($this->http->FindPreg('/window\.digiDataUser\s*=\s*(.+?);/')) ?? $this->http->FindPreg('/window\.digiDataUser\s*=\s*(.+?);/');
        $responseText = is_object($response) ? $this->http->FindPreg('/window\.digiDataUser\s*=\s*(.+?);/') : $response;

        if (!isset($response->points, $response->level, $response->number) && !$this->http->FindPreg("/status\"?:\"logged-in\"/", false, $responseText)) {
            // not a member
            if (isset($response->id, $response->points) && !isset($response->number) && $response->points == 0) {
                $this->SetBalanceNA();
            }

            if (
                $this->http->FindPreg("/window.digiDataUser=\{level:\"regular\",status:\"anonymous\",policy:true,profiling:true\}/")
                && $this->AccountFields["Login2"] == 'FIN'
            ) {
                throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
            }

            return;
        }
        // Balance - Bonusa punkti
        $this->SetBalance($response->points ?? $this->http->FindPreg("/points:(\d+)/", false, $responseText));
        // Bonus Points, duplicate for elite
        $this->SetProperty('BonusPoints', $this->Balance ?? $this->http->FindPreg("/Balance:(\d+)/", false, $responseText));
        // Club One kartes līmenis
        $this->SetProperty('Status', beautifulName($response->level ?? $this->http->FindPreg("/level:\"([^\"]+)/", false, $responseText)));
        // Kartes numurs
        $this->SetProperty('AccountNumber', $response->number ?? $this->http->FindPreg("/number:\"([^\"]+)/", false, $responseText));

        // Name window.clientFullName = 'FirstName LastName';
        $this->SetProperty('Name', beautifulName($this->http->FindPreg("/window\.clientFullName\s*=\s*(?:\"|\')(.+?)(?:\"|\')/")));

        //$this->http->GetURL('https://sso.tallink.com/api/users/' . $this->auth->userId, [
        //	'token' => $this->auth->token
        //]);
        //$response = $this->http->JsonLog();
    }

    public function GetRedirectParams($targetURL = null)
    {
        $arg = parent::GetRedirectParams($targetURL);
        $arg["NoCookieURL"] = true;

        switch ($this->AccountFields["Login2"]) {
            case "EST":
                $arg["SuccessURL"] = "http://lv.tallinksilja.com/et/web/ee/club-one?" . time() . date("B");
                // no break
            case "LAT":
                $arg["SuccessURL"] = "http://www.tallinksilja.com/lv/web/lv/club-one?" . time() . date("B");

            break;

            case "EN":
            case "GER":
            case "FIN":
            case "SWE":
                $this->http->GetURL("http://www.tallinksilja.com/fi/mainMenu/clubOne/default.htm");
                $this->http->ParseForm("Form1");
                $arg["PostValues"] = $this->http->Form;
                $arg["PostValues"]["IncClubSiljaMember1:txtLCNumber"] = $this->AccountFields["Login"];
                $arg["PostValues"]["IncClubSiljaMember1:txtPassword"] = $this->AccountFields["Pass"];
                $arg["PostValues"]["IncClubSiljaMember1:imgLogin.x"] = '0';
                $arg["PostValues"]["IncClubSiljaMember1:imgLogin.y"] = '0';
                $arg["PostValues"]["IncLanguageSelection2:ddlSiteSelection"] = 'Select site';
                $arg["PostValues"]["IncLanguageSelection1:ddlSiteSelection"] = 'Select site';
                $arg["URL"] = "http://www.tallinksilja.com/fi/mainMenu/clubOne/default.htm";
                $arg["RequestMethod"] = "POST";

                switch ($this->AccountFields["Login2"]) {
                    case "EN":
                        $arg["SuccessURL"] = "http://www.tallinksilja.com/en/mainMenu/clubOne/default.htm?ShowLoginDetails=true";

                    break;

                    case "GER":
                        $arg["SuccessURL"] = "http://www.tallinksilja.com/de/mainMenu/clubOne/default.htm?ShowLoginDetails=true";

                    break;

                    case "FIN":
                        $arg["SuccessURL"] = "http://www.tallinksilja.com/fi/mainMenu/clubOne/default.htm?ShowLoginDetails=true";

                    break;

                    case "SWE":
                        $arg["SuccessURL"] = "http://www.tallinksilja.com/sv/mainMenu/clubOne/default.htm?ShowLoginDetails=true";

                    break;
                }

            break;

            default:
                $this->http->Log("LoadLoginForm: incorrect region \"" . $this->AccountFields['Login2'] . "\"");

                return false;

            break;
        }

        return $arg;
    }
}
