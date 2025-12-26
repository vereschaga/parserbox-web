<?php

use AwardWallet\Engine\ProxyList;

class TAccountCheckerRebatesme extends TAccountChecker
{
    use ProxyList;

    private const REWARDS_PAGE_URL = 'https://www.rebatesme.com/user-center/cashback';
    /**
     * @var CaptchaRecognizer
     */
    private $recognizer;

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->setDefaultHeader("Accept-Encoding", "gzip, deflate, br");
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
        if (filter_var($this->AccountFields['Login'], FILTER_VALIDATE_EMAIL) === false) {
            throw new CheckException('Please enter a valid email address, e.g. john@gmail.com', ACCOUNT_INVALID_PASSWORD);
        }

        $this->http->removeCookies();
        $this->http->GetURL('https://www.rebatesme.com/en/login?redirect_to=/user-center/cashback&rol=1');
        $this->metaRedirect();

        if (!$this->http->ParseForm('loginform1item')) {
            return $this->checkErrors();
        }

        $token = $this->parseReCaptcha();

        if ($token === false) {
            return false;
        }

        $data = [
            "email"        => $this->AccountFields['Login'],
            "password"     => $this->AccountFields['Pass'],
            "registerFrom" => "RebatesMe",
            "parenturl"    => "",
            "referCode"    => "",
            "deviceType"   => "0",
            "deviceId"     => "91ca30e753c132f33680b87e1a748f60",
            "captcha"      => $token,
            "captchaType"  => "0",
        ];
        $headers = [
            "Accept"           => "*/*",
            "Accept-Language"  => "en",
            "Accept-Encoding"  => "gzip, deflate, br",
            "Content-Type"     => "application/json",
            "Authorization"    => "",
            "x-rm-lang-prefer" => "en",
            "X-Requested-With" => "XMLHttpRequest",
        ];
        $this->http->RetryCount = 0;
        $this->http->PostURL('https://www.rebatesme.com/auth/rest/user-login', json_encode($data), $headers);
        $this->http->RetryCount = 2;

        return true;
    }

    public function Login()
    {
        $json = $this->http->JsonLog();
        $code = $json->data->code ?? null;

        if ($code == '200') {
            return $this->loginSuccessful();
        }

        $message = $json->data->msg ?? null;

        if ($message) {
            $this->logger->error("[Error]: {$message}");

            if (
                ($code == 302 && $message == "We do not recognize this email address. Please Join now.")
                || ($code == 301 && $message == "We do not recognize this email address. Please Join now.")
                || (in_array($code, [301, 302]) && stristr($message, "Wrong Password. Please try again"))
                || stristr($message, "Your account has been temporarily disabled.")
                || stristr($message, "We do not recognize this email address")
            ) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            $this->DebugInfo = $message;

            return false;
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        $headers = [
            "Accept"           => "*/*",
            "Accept-Language"  => "en",
            "Accept-Encoding"  => "gzip, deflate, br",
            "Content-Type"     => "application/json",
            "Authorization"    => $this->http->getCookieByName("_sso_token"),
            "x-rm-lang-prefer" => "en",
            "X-Requested-With" => "XMLHttpRequest",
        ];
        $this->http->PostURL("https://www.rebatesme.com/user-center/rest/get-account-info", "{}", $headers);
        $response = $this->http->JsonLog();
        // Balance - Account Balance
        $this->SetBalance($response->data->balance ?? null);
        // RebatesMe has paid you a total of ...
        $this->SetProperty('TotalPaid', $response->data->usedBalance ?? null);
        // Pending
        $pending = $response->data->balance ?? null;
        // Available
        $available = $response->data->availableBalance ?? null;

        if (isset($pending) && isset($available)) {
            $this->AddSubAccount([
                'Code'              => 'Pending',
                'DisplayName'       => 'Pending',
                'Balance'           => number_format($pending - $available, 2),
                "BalanceInTotalSum" => true,
            ]);
            $this->AddSubAccount([
                'Code'              => 'Available',
                'DisplayName'       => 'Available',
                'Balance'           => $available,
                "BalanceInTotalSum" => true,
            ]);
        }

        // Name
        $this->SetProperty('Name', str_replace('*', '', $response->data->userName ?? null));
    }

    private function parseReCaptcha()
    {
        $this->http->RetryCount = 0;
        $key = $this->http->FindSingleNode('//head/meta[contains(@name, "recaptcha")]/@content') ?? '6LeWEYAUAAAAADw84TmK5hfdjetA0wufdCCElzhZ';

        if (!$key) {
            return false;
        }

        $this->recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_RUCAPTCHA);
        $this->recognizer->RecognizeTimeout = 120;
        $parameters = [
            "pageurl" => $this->http->currentUrl(),
            "proxy"   => $this->http->GetProxy(),
        ];

        return $this->recognizeByRuCaptcha($this->recognizer, $key, $parameters);
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);
        $this->http->RetryCount = 0;
        $this->http->GetURL(self::REWARDS_PAGE_URL, [], 20);
        $this->metaRedirect();
        $this->http->RetryCount = 2;

        if (
            !strstr($this->http->currentUrl(), 'https://www.rebatesme.com/en/login?redirect_to')
            && $this->http->FindSingleNode('//a[contains(text(), "Logout")]')
        ) {
            return true;
        }

        return false;
    }

    private function checkErrors()
    {
        $this->logger->notice(__METHOD__);

        return false;
    }

    private function metaRedirect()
    {
        $this->logger->notice(__METHOD__);

        if ($url = $this->http->FindSingleNode("//meta[@http-equiv='Refresh']/@content", null, true, "/URL=([^\']+)/")) {
            $this->http->NormalizeURL($url);
            $this->http->GetURL($url);
        }
    }
}
