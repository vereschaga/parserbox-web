<?php

class TAccountCheckerYelloh extends TAccountChecker
{
    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
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
        $this->http->GetURL("https://www.yellohvillage.co.uk/user/login");

        if (!$this->http->FindSingleNode("//form[@id='myaccount_login_form']/@id")) {
            return false;
        }

        $data = [
            'login'        => $this->AccountFields['Login'],
            'password'     => $this->AccountFields['Pass'],
        ];
        $this->http->PostURL('https://www.yellohvillage.co.uk/ajax_connection', json_encode($data));

        return true;
    }

    public function GetRedirectParams($targetURL = null)
    {
        $arg = parent::GetRedirectParams($targetURL);
        $arg["CookieURL"] = "https://www.yellohvillage.co.uk/";

        return $arg;
    }

    public function Login()
    {
        $response = $this->http->JsonLog();

        if ($this->http->FindPreg("/^\{\"message_error\":null\}/") && $this->loginSuccessful()) {
            return true;
        }

        $message = $response->message_error ?? null;

        if ($message) {
            $this->logger->error("[Error]: {$message}");

            if ($message == 'Ooops! Unknown login or password') {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            $this->DebugInfo = $message;

            return false;
        }

        return false;
    }

    public function Parse()
    {
        $response = $this->http->JsonLog(null, 0);
        $user = $response->adobeDataLayer->user ?? null;
        // Card number
        $this->SetProperty("Account", $response->idFid ?? null);
        // Date of registration
        $this->SetProperty("RegistrationDate", $user->subscription ?? null);
        // Balance - points
        if (
            !$this->SetBalance($user->points ?? null)
            && $user->points === null
            && $user->subscription === ""
        ) {
            throw new CheckException(self::NOT_MEMBER_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        $this->http->GetURL("https://www.yellohvillage.co.uk/api/myaccount");
        $profil = $this->http->JsonLog(null, 3, false, 'birthDate')->profil ?? null;
        // Name
        $name = ($profil->firstName ?? null) . " " . $profil->lastName ?? null;
        $this->SetProperty("Name", beautifulName($name));
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);

        $this->http->RetryCount = 0;
        $this->http->GetURL("https://www.yellohvillage.co.uk/service/myaccount_infos", [], 20);
        $this->http->RetryCount = 2;
        $response = $this->http->JsonLog()->googleAnalyticsDataLayer ?? null;
        $email = $response->visitoremail ?? null;
        $this->logger->debug("[Email]: {$email}");
        $number = $response->visitorId ?? null;
        $this->logger->debug("[Number]: {$number}");

        if (
            strtolower($email) == strtolower($this->AccountFields['Login'])
            || $number == $this->AccountFields['Login']
        ) {
            return true;
        }

        return false;
    }
}
