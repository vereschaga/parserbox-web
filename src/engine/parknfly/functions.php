<?php

class TAccountCheckerParknfly extends TAccountChecker
{
    /**
     * @var CaptchaRecognizer
     */
    private $recognizer;

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->setHttp2(true);
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
        // Australia
        if ($this->AccountFields['Login2'] == 'Australia') {
            throw new CheckException('We no longer support Australia region for Park ’N Fly since there is no balance to track on the website.', ACCOUNT_PROVIDER_ERROR); /*review*/
        }

        // USA
        if ($this->AccountFields['Login2'] == 'USA') {
            throw new CheckException('The Park \'N Fly program has merged with The Parking Spot. After completing your enrollment, you can track your rewards through The Parking Spot program.', ACCOUNT_PROVIDER_ERROR); /*review*/
        }

        $this->http->GetURL("https://www.parknfly.ca/");

        $_wpnonce = $this->http->FindSingleNode('//form[@id = "header_login_form"]/input[@id = "_wpnonce"]/@value');

        if (!$this->http->FindSingleNode("//a[@id='nav_sign_in_btn']") || !$_wpnonce) {
            return $this->checkErrors();
        }

        // https://www.parknfly.ca/wp-content/themes/parknfly/dist/scripts/main_20879994.js
        $captcha = $this->parseCaptcha("6Lct6dgiAAAAAGR_ynf3vX_qr4j1prvnKefjkr_2");

        if ($captcha == null) {
            return false;
        }

        $data = [
            "username" => $this->AccountFields['Login'],
            "password" => $this->AccountFields['Pass'],
            "_wpnonce" => $_wpnonce,
            "token"    => $captcha,
            "action"   => "login",
        ];
        $headers = [
            "Accept"           => "*/*",
            "Content-Type"     => "application/x-www-form-urlencoded; charset=UTF-8",
            "X-Requested-With" => "XMLHttpRequest",
        ];
        $this->http->RetryCount = 0;
        $this->http->PostURL("https://www.parknfly.ca/wp-admin/admin-ajax.php?action=pnf_authenticate", json_encode($data), $headers);
        $this->http->RetryCount = 2;

        return true;
    }

    public function Login()
    {
        if (empty($this->http->Response['body'])) {
            throw new CheckException("Sorry, could not login. Please confirm your username and password and try again.", ACCOUNT_PROVIDER_ERROR);
            $this->DebugInfo = "wrong captcha answer";
            $this->captchaReporting($this->recognizer, false);

            throw new CheckRetryNeededException(3, 0);
        }

        if ($this->http->FindPreg("/For security reasons, it was blocked and logged./")) {
            $this->DebugInfo = self::ERROR_REASON_BLOCK;
            $this->ErrorReason = self::ERROR_REASON_BLOCK;

            return false;
        }

        $response = $this->http->JsonLog();
        $status = $response->result->status ?? null;
        $token = $response->result->user->token ?? null;
        $registrationStatus = $response->registrationStatus ?? null;

        if ($registrationStatus == 'NewRegistration') {
            throw new CheckException(self::NOT_MEMBER_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        if ($registrationStatus == 'Pending') {
            $this->throwProfileUpdateMessageException();
        }

        if (
            $status === "OK"
            && !empty($token)
        ) {
            $this->captchaReporting($this->recognizer);

            if ($this->loginSuccessful()) {
                return true;
            }
        }
        $error = $response->result->errors[0] ?? null;

        if ($error) {
            $this->captchaReporting($this->recognizer);
            $this->logger->error("[Error]: {$error}");

            if (
                $error === "Invalid Email, Rewards Number or Password.  Please try again. [403]"
                || $error === "Invalid Email, Rewards Number or Password.  Please try again. [400]"
            ) {
                throw new CheckException("Sorry, could not login. Please confirm your username and password and try again.", ACCOUNT_INVALID_PASSWORD);
            }

            $this->DebugInfo = $error;

            return false;
        }

        if (
            $this->http->Response['code'] == 500
            && $this->http->FindPreg('/<p>There has been a critical error on this website\.<\/p>/')
        ) {
            throw new CheckException("Sorry, could not login. Please confirm your username and password and try again.", ACCOUNT_INVALID_PASSWORD);
        }

        return false;
    }

    public function Parse()
    {
        $response = $this->http->JsonLog(null, 0);
        // Corporate Discount #
//        $this->SetProperty("CorporateDiscount", $response->result->confirmation[0]->corporateDiscountNumber ?? null);// TODO
        // Account #
        $this->SetProperty("LoyaltyID", $response->result->confirmation->reward_member->parker_reward_number ?? null);
        // You are .. stays away from Platinum Status.
        $this->SetProperty("StaysUntileNextStatus", $response->result->confirmation->reward_member->stays_til_promotion ?? null);
        // YTD Parks
        $this->SetProperty("YTDParks", $response->result->confirmation->reward_member->ytd_parks ?? null);
        // Member since
        if (isset($response->result->confirmation->reward_member->enrollment_date)) {
            $this->SetProperty("MemberSince", date("F Y", strtotime($response->result->confirmation->reward_member->enrollment_date)));
        }
        // Name
        $firstName = $response->result->confirmation->customer->first_name ?? null;
        $lastName = $response->result->confirmation->customer->last_name ?? null;
        $this->SetProperty('Name', beautifulName("{$firstName} {$lastName}"));

        $status = $response->result->confirmation->reward_member->tier_name ?? null;
        // Status
        $this->SetProperty('TierStatus', $status);

        $nextStatuses = [
            'Member'   => 'Green',
            'Green'    => 'Gold',
            'Gold'     => 'Platinum',
            'Platinum' => 'Platinum',
        ];

        if (isset($nextStatuses[$status])) {
            // Next tier status
            $this->SetProperty('NextTierStatus', $nextStatuses[$status]);
        } else {
            $this->sendNotification('refs #24093 - need to check status property // IZ');
        }

        // Balance -  Park'N Fly Rewards Points
        $this->SetBalance($response->result->confirmation->reward_member->points_balance ?? null);
    }

    protected function parseCaptcha($key = null)
    {
        $this->logger->notice(__METHOD__);

        if (!$key) {
            return false;
        }

        $postData = [
            "type"        => "RecaptchaV3TaskProxyless",
            "websiteURL"  => $this->http->currentUrl(),
            "websiteKey"  => $key,
            "minScore"    => 0.7,
            "pageAction"  => "login",
            "isInvisible" => true,
        ];
        $this->recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_ANTIGATE_API_V2);
        $this->recognizer->RecognizeTimeout = 120;

        return $this->recognizeAntiCaptcha($this->recognizer, $postData);

        $this->recognizer = $this->getCaptchaRecognizer();
        $this->recognizer->RecognizeTimeout = 120;
        $parameters = [
            "pageurl"   => $this->http->currentUrl(),
            "proxy"     => $this->http->GetProxy(),
            "invisible" => 1,
            "version"   => "v3",
            "action"    => "login",
            "min_score" => 0.3,
        ];

        return $this->recognizeByRuCaptcha($this->recognizer, $key, $parameters, true, 3, 1);
    }

    private function checkErrors()
    {
        $this->logger->notice(__METHOD__);

        if ($message = $this->http->FindSingleNode('//p[contains(text(), "We are experiencing a temporary technical issue that has disrupted the Park’N Fly app and the website.")]')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);
        $this->http->RetryCount = 0;
        $this->http->PostURL("https://www.parknfly.ca/wp-admin/admin-ajax.php?action=pnf_get_member_profile", [], [], 20);
        $this->http->RetryCount = 2;
        $response = $this->http->JsonLog(null, 5);
        $status = $response->result->status ?? null;
        $rewardNumber = $response->result->confirmation->reward_member->parker_reward_number ?? null;

        if (
            $status === "OK"
            && !empty($rewardNumber)
        ) {
            return true;
        }

        /*
        $error = $response->result->errors[0] ?? null;

        if ($error) {
            $this->logger->error("[Error]: {$error}");

            if ($error === "Sorry, your profile could not be retrieved at this time. [400]") {
                throw new CheckException($error, ACCOUNT_PROVIDER_ERROR);
            }

            $this->DebugInfo = $error;

            return false;
        }
        */

        return false;
    }
}
