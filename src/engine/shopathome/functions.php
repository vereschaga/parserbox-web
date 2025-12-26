<?php

use AwardWallet\Engine\ProxyList;

class TAccountCheckerShopathome extends TAccountChecker
{
    use ProxyList;

    private const REWARDS_PAGE_URL = 'https://api.tada.com/?cmd=mp-gn-member-status';
    /**
     * @var CaptchaRecognizer
     */
    private $recognizer;

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->setHttp2(true);

        if ($this->attempt > 0) {
            $this->http->SetProxy($this->proxyReCaptcha());
        }

        $this->http->SetProxy($this->proxyDOP(\AwardWallet\Engine\Settings::DATACENTERS_NORTH_AMERICA));
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
        $this->http->GetURL('https://www.tada.com/login?rloc=%2Faccount-statement%23%2F');

        if (!$this->http->ParseForm("signinForm")) {
            return $this->checkErrors();
        }
        $from = $this->http->Form;

        $this->http->PostURL("https://api.tada.com/?cmd=mp-gn-member-status", ['_ajax' => 1]);
        $response = $this->http->JsonLog();

        if (!isset($response->authToken)) {
            return $this->checkErrors();
        }
        $this->http->Form = $from;
        $this->http->FormURL = 'https://api.tada.com/?cmd=mp-gn-login255';
        $this->http->SetInputValue("email", $this->AccountFields['Login']);
        $this->http->SetInputValue("password", $this->AccountFields['Pass']);
        $this->http->SetInputValue("_ajax", "1");
        $this->http->SetInputValue($response->authToken->name, $response->authToken->value);

        $captcha = $this->parseReCaptcha();

        if ($captcha !== false) {
            $this->http->SetInputValue('g-recaptcha-response', $captcha);
        }

        return true;
    }

    public function Login()
    {
        $this->http->RetryCount = 0;

        if (!$this->http->PostForm(['Accept' => '*/*'])) {
            return $this->checkErrors();
        }

        $this->http->RetryCount = 2;
        $response = $this->http->JsonLog();

        if ($this->http->FindPreg("/\{\"success\":true\}/")) {
            return $this->loginSuccessful();
        }
        $error = $this->http->FindPreg("/\{\"success\":false,\"errors\":\{\"login\":\[\"([^\"]+)\"\]\}\}/");

        if (!$error) {
            return $this->checkErrors();
        }

        switch ($error) {
            case 'Login_IsValid':
                throw new CheckException('That email and password combination does not match our records. Please double-check and try again.', ACCOUNT_INVALID_PASSWORD);

                break;

            case 'Login_IsActive':
                throw new CheckException('Your account has been deactivated', ACCOUNT_INVALID_PASSWORD);

                break;

            case 'Login_NotIsBlocked':
                throw new CheckRetryNeededException(2, 1);

                break;

            default:
                $this->logger->error("Unknown login error: {$error}");
                $this->DebugInfo = $error;

                break;
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        $response = $this->http->JsonLog(null, 0);
        // Balance = Pending + Redeemable cash back
        $this->SetBalance(($response->member->pts + $response->member->ptsPending) / 100);
        // Lifetime Cash Back
        $this->SetProperty('LifetimeCashBack', '$' . ($response->member->earnedLifetimeSB / 100));
        // Pending cash back
        $this->AddSubAccount([
            "Code"              => "shopathomePending",
            "DisplayName"       => "Pending",
            "Balance"           => ($response->member->ptsPending / 100),
            "BalanceInTotalSum" => true,
        ]);
        // Redeemable cash back
        $this->AddSubAccount([
            "Code"              => "shopathomeRedeemable",
            "DisplayName"       => "Redeemable",
            "Balance"           => ($response->member->pts / 100),
            "BalanceInTotalSum" => true,
        ]);

        $this->http->PostURL("https://api.tada.com/?cmd=mp-gn-member-status", "_ajax=1");
        $response = $this->http->JsonLog();

        if (!isset($response->authToken)) {
            return;
        }
        $this->http->PostURL("https://api.tada.com/?cmd=mp-ac-jx-get-settingsuserdata", "_ajax=1&{$response->authToken->name}={$response->authToken->value}");
        $response = $this->http->JsonLog(null, 3, true);
        $this->SetProperty('Name', beautifulName(ArrayVal($response, 'firstName') . ' ' . ArrayVal($response, 'lastName')));
        // Member since
        $memberSince = ArrayVal($response, 'memberSince');

        if ($memberSince) {
            $this->SetProperty('MemberSince', date("m/d/Y", strtotime($memberSince)));
        }
    }

    protected function parseReCaptcha()
    {
        $this->logger->notice(__METHOD__);
//        $key = $this->http->FindSingleNode("//div[@id = 'recaptcha-login']/@data-sitekey");
        $key = '6Ld48JYUAAAAAGBYDutKlRp2ggwiDzfl1iApfaxE';
        $this->logger->debug("data-sitekey: {$key}");

        if (!$key) {
            return false;
        }
        $this->recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_RUCAPTCHA);
        $this->recognizer->RecognizeTimeout = 120;
        $parameters = [
            "pageurl"   => "https://www.shopathome.com/login?rloc=%2Faccount-statement%23%2F",
            "proxy"     => $this->http->GetProxy(),
            "invisible" => 1,
        ];

        return $this->recognizeByRuCaptcha($this->recognizer, $key, $parameters);
    }

    private function checkErrors()
    {
        $this->logger->notice(__METHOD__);

        return false;
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);

        $this->http->RetryCount = 0;
        $data = [
            '1'          => "2",
            'RefererUrl' => 'https://www.tada.com/login?rloc=%2Faccount-statement%23%2F',
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
