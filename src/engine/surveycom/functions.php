<?php

class TAccountCheckerSurveycom extends TAccountChecker
{
    /**
     * @var CaptchaRecognizer
     */
    private $recognizer;

    public function GetRedirectParams($targetURL = null)
    {
        $arg = parent::GetRedirectParams($targetURL);
        $arg["CookieURL"] = "https://www.survey.com/";

        return $arg;
    }

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->setDefaultHeader("Accept-Encoding", "gzip, deflate, br");
    }

    public function IsLoggedIn()
    {
        $this->http->RetryCount = 0;
//        $this->http->GetURL("", [], 20);
        $this->http->RetryCount = 2;

        if ($this->loginSuccessful()) {
            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->GetURL("https://dashboard.survey.com/");

        if (!$this->http->ParseForm("login-form")) {
            return false;
        }
        $this->http->SetInputValue("email", $this->AccountFields['Login']);
        $this->http->SetInputValue("password", $this->AccountFields['Pass']);
        $this->http->SetInputValue("remember", "on");
        $this->http->SetInputValue("X-Requested-With", "XMLHttpRequest");

        return true;
    }

    public function Login()
    {
        if (!$this->http->PostForm()) {
            return false;
        }
        // Success
        if ($this->loginSuccessful()) {
            return true;
        }
        // Login failed
        if ($message = $this->http->FindSingleNode('//div[@id = "login_error"]')) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        // AccountID: 6002408
        if ($location = $this->http->FindPreg("/window.location = '([^\']+)/")) {
            $this->http->GetURL($location);
            $this->http->GetURL("http://app.survey.com/app/views/login.html");

            if (!$this->http->ParseForm("form")) {
                return false;
            }

            $captcha = $this->parseRecaptcha();

            if ($captcha === false) {
                return false;
            }

            $data = [
                "email"                => $this->AccountFields['Login'],
                "password"             => $this->AccountFields['Pass'],
                "g-recaptcha-response" => $captcha,
            ];
            $headers = [
                "Accept"       => "application/json, text/plain, */*",
                "Content-Type" => "application/json;charset=utf-8",
            ];
            $this->http->PostURL("http://app.survey.com/Api/Login", json_encode($data), $headers);
            $response = $this->http->JsonLog();

            if ($this->http->FindPreg("/\{\"environment\":\"Production\",\"organization_id\":\"[^\"]+\",\"token\":null,\"email\":null,\"paypal_email\":null,\"password\":null,\"first_name\":null,\"last_name\":null,\"is_phone_confirmed\":false,\"referral_signup\":null,\"referral_source\":null,\"error_message\":null,\"preferred_travel_distance\":\"none\",\"contact_info\":null,\"social_media_integrations\":null,\"notification_settings\":null,\"profile_pic_uploaded\":false,\"do_not_text\":false,\"payment_method\":\"paypal\",\"disable_payment_request\":false,\"optout_date\":\"[^\"]+\",\"recaptcha\":null,\"license_data\":null,\"hasToken\":false,\"hasLinkedIn\":false,\"hasFacebook\":false,\"hasTwitter\":false,\"profilePicUrl\":null,\"_id\":null,\"updated_at\":\"[^\"]+Z\",\"created_at\":\"[^\"]+Z\",\"deleted_at\":null\}/")) {
                $this->captchaReporting($this->recognizer);
                throw new CheckException('Error: Failed to login with provided information.', ACCOUNT_INVALID_PASSWORD);
            }
        }

        return false;
    }

    /**
     * @deprecated
     */
    public function Parse()
    {
        $ticket = $this->http->FindSingleNode("//input[@name = 'ticket']/@value");

        if ($ticket) {
            $this->http->PostURL("http://www.cint.com/cpx/Panelists/Points.aspx", ["ticket" => $ticket]);
        }
        // Balance - Ваша премия
        $this->SetBalance($this->http->FindSingleNode("//td[contains(text(), 'Ваша премия')]/following-sibling::td", null, true, "/[\d\.\,\-]+/ims"));
        // Name
        $this->SetProperty("Name", beautifulName(
            $this->http->FindSingleNode("//input[@name = 'LastNameValue']/@value")
            . ' ' . $this->http->FindSingleNode("//input[@name = 'FirstNameValue']/@value")));
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);

        if ($this->http->FindSingleNode("//input[contains(@name, 'logOut')]/@name")) {
            return true;
        }

        return false;
    }

    protected function parseReCaptcha()
    {
        $this->logger->notice(__METHOD__);
        $key = $this->http->FindSingleNode('//form[@name = "form"]//div[@vc-recaptcha]/@key', null, true, "/\'([^\']+)/");
        $this->logger->debug("data-sitekey: {$key}");

        if (!$key) {
            return false;
        }

        $this->recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_RUCAPTCHA);
        $this->recognizer->RecognizeTimeout = 120;
        $parameters = [
            "pageurl"   => $this->http->currentUrl(),
            "proxy"     => $this->http->GetProxy(),
        ];

        return $this->recognizeByRuCaptcha($this->recognizer, $key, $parameters);
    }
}
