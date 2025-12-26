<?php

use AwardWallet\Engine\ProxyList;

class TAccountCheckerPlum extends TAccountChecker
{
    use ProxyList;

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;

        $this->http->SetProxy($this->proxyDOP());
    }

    public function IsLoggedIn()
    {
        $this->http->RetryCount = 0;
        $this->http->GetURL("https://www.indigo.ca/account-centre/en-ca?Section=home", [], 20);
        $this->http->RetryCount = 2;

        if ($this->loginSuccessful()) {
            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        if (filter_var($this->AccountFields['Login'], FILTER_VALIDATE_EMAIL) === false) {
            throw new CheckException("Please enter a valid email address.", ACCOUNT_INVALID_PASSWORD);
        }

        $this->http->removeCookies();
        $this->http->GetURL("https://shop.chapters.indigo.ca/Loyalty/myRecommendations.aspx?Section=home&Lang=en");

        if (!$this->http->ParseForm(null, '//form[@data-form-primary="true"]')) {
            return $this->checkErrors();
        }

        $this->http->SetInputValue('username', $this->AccountFields['Login']);
        $this->http->SetInputValue('password', $this->AccountFields['Pass']);
        /*
        $currentUrl = $this->http->currentUrl();
        $client_id = $this->http->FindPreg("/client_id=([^&]+)/", false, $currentUrl);
        $state = $this->http->FindPreg("/state=([^&]+)/", false, $currentUrl);
        $scope = $this->http->FindPreg("/scope=([^&]+)/", false, $currentUrl);
        $_csrf = $this->http->getCookieByName('_csrf', null, '/usernamepassword/login');
        $nonce = $this->http->FindPreg("/nonce=([^&]+)/", false, $this->http->currentUrl());

        if (!$client_id || !$state || !$scope || !$_csrf || !$nonce) {
            return $this->checkErrors();
        }

        $data = [
            "client_id"     => $client_id,
            "redirect_uri"  => "https://www.indigo.ca/account-centre/en-ca/callback",
            "tenant"        => "indigoca-commerce-prod",
            "response_type" => "code id_token",
            "scope"         => "openid profile email",
            "state"         => $state,
            "nonce"         => $nonce,
            "connection"    => "indigo-online-database-prod",
            "username"      => $this->AccountFields['Login'],
            "password"      => $this->AccountFields['Pass'],
            "popup_options" => new stdClass(),
            "sso"           => true,
            "response_mode" => "form_post",
            "_intstate"     => "deprecated",
            "_csrf"         => $_csrf,
            "x-client-_sku" => "ID_NET461",
            "x-client-ver"  => "6.22.0.0",
            "protocol"      => "oauth2",
        ];
        $headers = [
            'Accept'          => '*
        /*',
            'Content-Type'    => 'application/json',
            'Auth0-Client'    => 'eyJuYW1lIjoibG9jay5qcy11bHAiLCJ2ZXJzaW9uIjoiMTEuMzIuMiIsImVudiI6eyJhdXRoMC5qcy11bHAiOiI5LjE5LjAifX0=',
            'Accept-Encoding' => 'gzip, deflate, br',
            'Origin'          => 'https://auth.indigo.ca',
        ];
        $this->http->RetryCount = 0;
        $this->http->PostURL("https://auth.indigo.ca/usernamepassword/login", json_encode($data), $headers);
        $this->http->RetryCount = 2;
        */

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);

        if ($this->http->FindSingleNode("//h1[contains(text(), '502 Bad Gateway')]")) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
        $this->http->RetryCount = 0;

        if (!$this->http->PostForm() && $this->http->Response['code'] != 400) {
            return $this->checkErrors();
        }

        $this->http->RetryCount = 2;

        if ($this->http->ParseForm("hiddenform")) {
            $this->http->PostForm();
        }

        if ($this->http->ParseForm(null, '//form[@action = "https://www.indigo.ca/account-centre/en-ca/callback"]')) {
            $this->http->PostForm();
        }

        if ($this->loginSuccessful()) {
            return true;
        }

        if ($message = $this->http->FindSingleNode('//span[@class = "ulp-input-error-message"] | //div[@id = "prompt-alert"]/p')) {
            $this->logger->error("[Error]: " . $message);

            if (strstr($message, 'Your email or password is incorrect.')) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            if (strstr($message, 'Your account has been blocked')) {
                throw new CheckException($message, ACCOUNT_LOCKOUT);
            }

            $this->DebugInfo = $message;

            return false;
        }

        $response = $this->http->JsonLog();

        if (isset($response->message)) {
            switch ($response->message) {
                case "(401) Invalid credential attempt.":
                    throw new CheckException("Something went wrong. Try again.", ACCOUNT_INVALID_PASSWORD);

                case "Wrong email or password.":
                    throw new CheckException("Your email or password is incorrect. Please check your details and try again.", ACCOUNT_INVALID_PASSWORD);

                default:
                    $this->logger->error("[Error]: " . $response->message);
                    $this->DebugInfo = $response->message;
            }// switch ($response->message)
        }// if (isset($response->message))

        if (isset($response->description)) {
            $message = $response->description;

            switch ($message) {
                case "Invalid captcha value":
                    throw new CheckRetryNeededException(3, 0, self::CAPTCHA_ERROR_MSG);

                case strstr($message, "Your account has been blocked after multiple consecutive login attempts."):
                    throw new CheckException($message, ACCOUNT_LOCKOUT);

                default:
                    $this->logger->error("[Error]: " . $message);
                    $this->DebugInfo = $message;
            }// switch ($response->message)
        }// if (isset($response->description))

        if ($this->http->Response['code'] == 504 && $this->http->FindPreg("/An error occurred while processing your request.<p>/")) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        // Name
        $this->SetProperty("Name", beautifulName($this->http->FindSingleNode('//span[@class = "account-details-tile__name"]')));
        // Member Number
        $this->SetProperty("AccountNumber", $this->http->FindSingleNode("//div[span[contains(., ' NUMBER')]]/following-sibling::div"));

        $balance = $this->http->FindSingleNode('//span[@data-a8n="my-rewards-tile__current-balance"]', null, true, "/(.+) point/");

        $this->http->GetURL('https://www.indigo.ca/account-centre/en-ca/my-rewards.html?focus=pointsHistory');
        // Balance - plum points
        $this->SetBalance($this->http->FindSingleNode('//span[@data-a8n = "my-rewards__textCurrentPoints"]'));
        // Redeemable value
        $this->SetProperty("AvailableToRedeem", $this->http->FindSingleNode('//span[contains(text(), "Redeemable value:")]/following-sibling::span'));

        // Expiration Date  // refs #4043

        // Last Transaction
        $exp = $this->http->FindSingleNode('//ul[contains(@class, "accordion__list")]/li[1]//h5');
        $this->SetProperty("LastActivity", $exp);
        $exp = str_replace('-', '/', $exp);
        $this->logger->debug("Last Transaction $exp - " . var_export(strtotime($exp), true));

        if ($exp && $exp = strtotime('+12 month', strtotime($exp))) {
            $this->logger->debug("Expiration Date $exp - " . var_export(date("m/d/Y", $exp), true));
            $this->SetExpirationDate($exp);
        }// if ($exp && $exp = strtotime('+12 month', strtotime($exp)))

        // Now you have a choice: join plum rewards or irewards
        if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR) {
            if (
                ($this->http->FindSingleNode("//h3[contains(text(), 'Which one is best for you?')]") && $this->http->FindNodes("//a[contains(text(), 'Join now')]"))
                || $this->http->FindPreg("/Join plum<sup>®<\/sup> Free<\/span><\/button>/")
                || $this->http->FindPreg("/class=common-button>Join plum<\/a></")
            ) {
                throw new CheckException(self::NOT_MEMBER_MSG, ACCOUNT_PROVIDER_ERROR);
            }

            // provider bug fix
            if ($this->http->Response['code'] == 500) {
                $this->SetBalance($balance);
            }
        }
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);

        if ($this->http->FindNodes('//a[contains(@data-logout-path, "logout")]/@data-logout-path')) {
            return true;
        }

        return false;
    }
}
