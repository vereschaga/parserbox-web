<?php

use AwardWallet\Engine\ProxyList;

class TAccountCheckerMypoints extends TAccountChecker
{
    use ProxyList;

    private const REWARDS_PAGE_URL = 'https://api.mypoints.com/?cmd=mp-gn-member-status';

    protected $authTokenName = null;
    protected $authTokenValue = null;
    /**
     * @var CaptchaRecognizer
     */
    private $recognizer;

    private $recaptchaResponse = "03AGdBq24UuyirQa8fxc9b9nmyCF3iIgculGxLBONBuDQJbiwXzEwdbhVivsLTFURu7g2Hm7UUglqgeos-NRvzgddtT6aVv_1IiY3a_tIfr9zRwBEJnuePkkTH87TYdDr5UjihdQhgISapx0A7jNYG7-tU5yJ1KdgvFckagODYAtW9RZ9QRCawqzSi7BltVCUGBF2nFpjQg6p004TwxJ7G--M8ENPTAPhvU9cQ28VnrA0WGfblM1xr_C8gTvexdq107mhXaFzpe45zYrGLlwY-ZkQ2fuUA6i7RttJGn5dZ5jxSVbxUw0-NUYLNZvh7-VNivOeY7JTXlt5FTPAMqh02WyTfv25OvSqtPp6Rk66CrgHdYnQaRe0Lr4zUFTt-VCh-Jfn0G3TQ6jpTDhwVlr8CrpCKfdEXmfmahcxfZ-QP54nfMH8wtfBysgKZe4tIUropFfm1IMwGUdvjVnbmiof9gL_3CZ7zIf-57RsrN76swUAG_9yh53YtO2gyviX48-8RSLKakaQ0jtNR5AATBzFxl7PqXBytS5F9J3-_0guzf2tRQLJe0ntJLuY";

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
//        $this->http->SetProxy($this->proxyDOP());
        $this->http->SetProxy($this->proxyReCaptcha());
    }

    public function IsLoggedIn()
    {
        if ($this->loginSuccessful()) {
            return true;
        }

        return false;
    }

    /*
     * like as shopathome, mypoints
     */

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        // get authToken
        $this->http->PostURL("https://api.mypoints.com/?cmd=mp-gn-member-status", []);
        $response = $this->http->JsonLog(null, 3, true);
        $authToken = ArrayVal($response, 'authToken');
        $this->authTokenName = ArrayVal($authToken, 'name');
        $this->authTokenValue = ArrayVal($authToken, 'value');
        // get cookies
        $data = [
            "email"              => $this->AccountFields['Login'],
            "password"           => $this->AccountFields['Pass'],
            $this->authTokenName => $this->authTokenValue,
            "persist"            => "on",
        ];
        // refs #14345
        $member = ArrayVal($response, 'member');

        if (ArrayVal($member, 'rc') == true) {
            /*
            $captcha = $this->parseCaptcha();
            if ($captcha === false)
                return false;
            $data['g-recaptcha-response'] = $captcha;
            */
            $data['g-recaptcha-response'] = $this->recaptchaResponse;
        }// if (ArrayVal($member, 'rc') == true)

        $this->logger->error(var_export($data, true));

        $this->http->PostURL("https://api.mypoints.com/secure/login", $data, ["Accept" => "*/*"]);

        if (!$this->http->FindPreg("/\"success\":true/")) {
            return $this->checkErrors();
        }

//        $this->http->GetURL("https://www.mypoints.com/login?rloc=%2Faccount-statement");
//        if (!$this->http->ParseForm("registration") || !$this->authTokenName || !$this->authTokenValue)
//            return $this->checkErrors();
//        $this->http->SetInputValue("email", $this->AccountFields['Login']);
//        $this->http->SetInputValue("password", $this->AccountFields['Pass']);

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);
        // Invalid Login
        if ($this->http->FindPreg("/\"errors\":\{\"login\":\[\"Login_IsValid\"\]/")) {
            throw new CheckException("Invalid Login", ACCOUNT_INVALID_PASSWORD);
        }
        // Your account has been deactivated
        if ($this->http->FindPreg("/\"errors\":\{\"login\":\[\"Login_IsActive\"\]/")) {
            throw new CheckException("Your account has been deactivated", ACCOUNT_INVALID_PASSWORD);
        }
        // We closed your account after 12 months of inactivity.
        if ($this->http->FindPreg("/\"errors\":\{\"login\":\[\"Login_InactiveAccount\"\]/")) {
            throw new CheckException("We closed your account after 12 months of inactivity.", ACCOUNT_INVALID_PASSWORD);
        }
        // Your account has been closed
        if ($this->http->FindPreg("/\"errors\":\{\"login\":\[\"Login_IsDeactivated\"\]/")) {
            throw new CheckException("Your account has been closed.", ACCOUNT_INVALID_PASSWORD);
        }

        return false;
    }

    public function Login()
    {
//        if (!$this->http->PostForm())
//            return $this->checkErrors();

        // A Captcha is required to proceed. Please solve the Captcha and resubmit the form.
        if ($this->http->ParseForm("registration") && $this->http->FindPreg("/Please solve the Captcha and resubmit the form/")) {
            $this->captchaReporting($this->recognizer, false);
            /*
            $captcha = $this->parseCaptcha();
            if ($captcha === false) {
                return false;
            }
            $this->http->SetInputValue('g-recaptcha-response', $captcha);
            */
            $this->http->SetInputValue('g-recaptcha-response', $this->recaptchaResponse);

            if (!$this->http->PostForm()) {
                return $this->checkErrors();
            }
        }
        // Access is allowed
        if ($this->http->FindPreg("/\{\"success\":true\}/")) {
            $this->captchaReporting($this->recognizer);

            return $this->loginSuccessful();
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        $response = $this->http->JsonLog(null, 0);
        // Balance -  Redeemable points
        $this->SetBalance($response->member->points);
        // Name
        $this->SetProperty("Name", beautifulName($response->member->name));
        // Pending Points
        $this->SetProperty("Pending", $response->member->ptsPending);
        // Lifetime Points
        $this->SetProperty("LifetimeEarned", $response->member->earnedLifetimeSB);

        // Full Name
        $this->http->PostURL("https://api.mypoints.com/?cmd=mp-ac-jx-get-settingsuserdata", [$this->authTokenName => $this->authTokenValue], ["Accept" => "*/*"]);
        $response = $this->http->JsonLog(null, 3, true);
        $name = ArrayVal($response, 'firstName') . ' ' . ArrayVal($response, 'lastName');

        if (strlen(trim($name)) > 2) {
            $this->SetProperty("Name", beautifulName($name));
        }
        // Member since
        $memberSince = ArrayVal($response, 'memberSince');

        if ($memberSince) {
            $this->SetProperty('MemberSince', date("m/d/Y", strtotime($memberSince)));
        }
    }

    protected function parseCaptcha()
    {
        $this->logger->notice(__METHOD__);
        $key = '6Ld48JYUAAAAAGBYDutKlRp2ggwiDzfl1iApfaxE';
        $this->logger->debug("data-sitekey: {$key}");

        if (!$key) {
            return false;
        }
        $this->recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_RUCAPTCHA);
        $this->recognizer->RecognizeTimeout = 120;
        $parameters = [
            "pageurl" => 'https://www.mypoints.com/login?rloc=%2Faccount-statement',
            "proxy"   => $this->http->GetProxy(),
        ];

        return $this->recognizeByRuCaptcha($this->recognizer, $key, $parameters);
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);

        $this->http->RetryCount = 0;
        $data = [
            '1'          => "2",
            'RefererUrl' => 'https://www.mypoints.com/login?rloc=%2Faccount-statement',
            'pathName'   => '/account-ledger',
            '_ajax'      => 1,
        ];
        $this->http->PostURL(self::REWARDS_PAGE_URL, $data);
        $this->http->RetryCount = 2;
        $response = $this->http->JsonLog();
        $email = $response->member->emailAddress ?? null;
        $this->logger->debug("[Email]: {$email}");

        if ($email && strtolower($email) == strtolower($this->AccountFields['Login'])) {
            return true;
        }

        return false;
    }
}
