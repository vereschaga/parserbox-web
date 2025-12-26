<?php

use AwardWallet\Engine\ProxyList;

class TAccountCheckerOdeon extends TAccountChecker
{
    use ProxyList;
    /**
     * @var CaptchaRecognizer
     */
    private $recognizer;

    private $headers = [];

    public static function GetAccountChecker($accountInfo)
    {
        require_once __DIR__ . "/TAccountCheckerOdeonSelenium.php";

        return new TAccountCheckerOdeonSelenium();
    }

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->SetProxy($this->proxyDOP(['lon1']));
        $this->http->setHttp2(true);
    }

    public function getToken()
    {
        $this->logger->notice(__METHOD__);
        $this->http->getURL('https://www.odeon.co.uk/sign-in');
        $authToken = $this->http->FindPreg("/\"authToken\":\"([^\"]+)/");

        if (!$authToken) {
            return false;
        }
        $this->headers = [
            "authorization" => "Bearer {$authToken}",
        ];

        return true;
    }

    public function IsLoggedIn()
    {
        $this->http->RetryCount = 0;
        $token = $this->getToken();
        $this->http->RetryCount = 2;

        if ($token && $this->loginSuccessful()) {
            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();

        if (!$this->getToken()) {
            //return $this->challengeReCaptchaForm();
            return $this->checkErrors();
        }

        $this->http->FormURL = 'https://vwc.odeon.co.uk/WSVistaWebClient/ocapi/v1/loyalty/authentication/session';
        $this->http->SetInputValue('email', $this->AccountFields['Login']);
        $this->http->SetInputValue('password', $this->AccountFields['Pass']);
        $this->http->SetInputValue('remember', "true");
        $this->http->SetInputValue('grant_type', "password");

        return true;
    }

    public function Login()
    {
        $this->http->RetryCount = 0;

        $captcha = $this->parseCaptcha();

        if ($captcha == false) {
            return false;
        }

        $headers = ["captcharesponse" => $captcha];

        if (!$this->http->PostForm($this->headers + $headers) && !in_array($this->http->Response['code'], [204, 400])) {
            return $this->checkErrors();
        }

        $this->http->RetryCount = 2;

        if ($this->http->getCookieByName("vista-loyalty-member-authentication-token")) {
            $this->captchaReporting($this->recognizer);

            return $this->loginSuccessful();
        }

        $response = $this->http->JsonLog();
        $message = $response->title ?? null;
        $detail = $response->detail ?? null;

        if ($message) {
            if ($message == 'Invalid credentials') {
                $this->captchaReporting($this->recognizer);

                throw new CheckException('The credentials you entered are incorrect.', ACCOUNT_INVALID_PASSWORD);
            }

            if ($detail == 'Failed CAPTCHA validation') {
                $this->captchaReporting($this->recognizer, false);

                throw new CheckRetryNeededException(2, 1, self::CAPTCHA_ERROR_MSG);
            }

            return false;
        }
        // Sign in failed, please try again.
        if (
            $this->http->FindSingleNode('//h1[contains(text(), "403 Forbidden")]')
            && $this->http->FindSingleNode('//center[contains(text(), "Microsoft-Azure-Application-Gateway/v2")]')
            && strstr($this->AccountFields['Pass'], '^')// AccountID: 3855715
        ) {
            $this->captchaReporting($this->recognizer);

            throw new CheckException("Sign in failed, please try again.", ACCOUNT_PROVIDER_ERROR);
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        $response = $this->http->JsonLog(null, 0);
        // Name
        $firstName = $response->member->personalDetails->name->givenName ?? null;
        $lastName = $response->member->personalDetails->name->familyName ?? null;
        $this->SetProperty('Name', beautifulName("$firstName $lastName"));
        // Membership Number
        $this->SetProperty('Number', $response->member->id ?? null);
        // Member since
        if (isset($response->member->membershipStartDate)) {
            $this->SetProperty('MemberSince', date('m/d/Y', strtotime($response->member->membershipStartDate)));
        }
        // Type of membership
        $this->SetProperty('TypeOfMembership', $response->relatedData->club->name->text ?? null);

        $rewards = $response->relatedData->rewards ?? [];

        foreach ($rewards as $reward) {
            $displayName = $reward->name->text;
            $balance = $reward->balanceCost;
            $this->AddSubAccount([
                'Code'           => 'odeon' . $reward->id,
                'DisplayName'    => $displayName,
                'Balance'        => $balance,
            ]);
        }

        if (
            !empty($this->Properties['Name'])
            && !empty($this->Properties['Number'])
            && !empty($this->Properties['MemberSince'])
            && !empty($this->Properties['TypeOfMembership'])
        ) {
            $this->SetBalanceNA();
        }

        // try to find upcoming bookings
        $this->http->RetryCount = 0;
        $this->http->GetURL("https://vwc.odeon.co.uk/WSVistaWebClient/ocapi/v1/loyalty/members/current/journeys/current", $this->headers);
        $this->http->RetryCount = 2;
        $response = $this->http->JsonLog();

        if (!$this->http->FindPreg("/\{\"journeys\":\[\]/")) {
            $this->sendNotification("upcoming bookings were found");
        }
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);
        $this->http->RetryCount = 0;
        $this->http->GetURL('https://vwc.odeon.co.uk/WSVistaWebClient/ocapi/v1/loyalty/members/current', $this->headers, 20);
        $this->http->RetryCount = 2;
        $response = $this->http->JsonLog();
        $email = $response->member->credentials->email ?? null;
        $this->logger->debug("[Email]: {$email}");

        if ($email && strtolower($email) === strtolower($this->AccountFields['Login'])) {
            return true;
        }

        return false;
    }

    private function checkErrors()
    {
        $this->logger->notice(__METHOD__);

        return false;
    }

    private function parseCaptcha($key = null, $method = 'userrecaptcha')
    {
        $this->logger->notice(__METHOD__);

        if (!$key && $method == 'userrecaptcha') {
            $key = $this->http->FindPreg("/\"security\":\{\"captcha\":\{\"siteKey\":\"([^\"]+)/");
        }

        if (!$key) {
            return false;
        }
        $this->logger->debug("data-sitekey: {$key}");

        $this->recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_RUCAPTCHA);
        $this->recognizer->RecognizeTimeout = 120;
        $parameters = [
            "method"  => $method,
            "pageurl" => $this->http->currentUrl(),
            "proxy"   => $this->http->GetProxy(),
        ];

        return $this->recognizeByRuCaptcha($this->recognizer, $key, $parameters);
    }

    private function challengeReCaptchaForm()
    {
        $this->logger->notice(__METHOD__);

        if (!$this->http->ParseForm("challenge-form")) {
            return false;
        }
        $key = $this->http->FindSingleNode("//form[@id = 'challenge-form']//script/@data-sitekey");
        $method = 'userrecaptcha';

        if ($this->http->FindSingleNode("//form[@id = 'challenge-form']//input[@name = 'cf_captcha_kind' and @value = 'h']/@value")) {
            $method = "hcaptcha";
            $key = '33f96e6a-38cd-421b-bb68-7806e1764460';
        }
        $captcha = $this->parseCaptcha($key, $method);

        if ($captcha == false) {
            return false;
        }

        if ($method === "hcaptcha") {
            $form = $this->http->Form;
            $formURL = $this->http->FormURL;

            $headers = [
                'Accept'       => '*/*',
                'Content-Type' => 'application/json;charset=UTF-8',
            ];
//            $this->http->SetInputValue("id", $id);
            //$this->http->SetInputValue("g-recaptcha-response", $captcha);
            $this->http->Form = $form;
            $this->http->FormURL = $formURL;
            $this->http->SetInputValue("captcha_vc", '4848a25e8c763727fb6986cc5bea626f');
            $this->http->SetInputValue("captcha_answer", 'uhXCKijMXDvW-8-66b765041dc9e372');
            $this->http->SetInputValue("cf_ch_verify", 'plat');

            $this->http->SetInputValue("h-captcha-response", 'captchka');
            $this->http->PostForm();
        } else {
            $this->http->SetInputValue("g-recaptcha-response", $captcha);
            $this->http->PostForm();
        }

        return true;
    }
}
