<?php

use AwardWallet\Engine\ProxyList;

class TAccountCheckerOnepoll extends TAccountChecker
{
    use ProxyList;

    private $scriptCookie;
    /**
     * @var CaptchaRecognizer
     */
    private $recognizer;

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->FilterHTML = false;
        $this->http->SetProxy($this->proxyReCaptcha());
    }

    public function GetRedirectParams($targetURL = null)
    {
        $arg = parent::GetRedirectParams($targetURL);
        $arg['SuccessURL'] = 'https://onepollus.questionpro.eu/a/showRewardTab.do?lppn=false';

        return $arg;
    }

    public function IsLoggedIn()
    {
        $this->http->RetryCount = 0;
        $this->http->GetURL('https://onepollus.questionpro.eu/a/showRewardTab.do?lppn=false', [], 20);
//        $this->script();
        $this->http->RetryCount = 2;

        if ($this->loginSuccessful()) {
            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        // Please enter a valid email address.
        if (filter_var($this->AccountFields['Login'], FILTER_VALIDATE_EMAIL) === false) {
            throw new CheckException("Please enter a valid email address.", ACCOUNT_INVALID_PASSWORD);
        }

        $this->http->removeCookies();

        if ($this->http->currentUrl() != 'https://onepollus.questionpro.eu/a/panelLogin.do?id=1602630125&mode=showLogin') {
            $this->http->GetURL("https://onepollus.questionpro.eu/a/panelLogin.do?id=1602630125&mode=showLogin");
        }

        if (!$this->http->ParseForm("formID")) {
            return $this->checkErrors();
        }
        $this->http->SetInputValue('emailAddress', $this->AccountFields['Login']);
        $this->http->SetInputValue('password', $this->AccountFields['Pass']);
        $this->http->SetInputValue('rememberMe', "on");
        $this->http->SetInputValue('ajax', "true");
        $this->http->SetInputValue('engine', "dojo");

        /*
        $key = $this->http->FindPreg("/grecaptcha\.execute\('([^\']+)', \{ action: 'header_login' /ims");
        $captcha = $this->parseReCaptcha($key, "header_login");
        if ($captcha == false) {
            return false;
        }
        $this->http->SetInputValue('token', $captcha);
        */

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);
        // maintenance
        if ($message = $this->http->FindSingleNode("//h1[
                contains(text(), 'nepoll.com is currently down for scheduled maintenance')
            ]")
        ) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
        /*
        if (isset($this->scriptCookie[1]))
            $this->http->setCookie($this->scriptCookie[1], $this->scriptCookie[2], 'members.onepoll.com', '/', strtotime("+1 day"));
        */
        $headers = [
            "Accept"       => "*/*",
            "Content-type" => "application/x-www-form-urlencoded;charset=UTF-8",
        ];

        if (!$this->http->PostForm($headers)) {
            return false;
        }

        if ($url = $this->http->FindPreg("/getCSRFTokenURL\('([^']+)/")) {
            $this->http->NormalizeURL($url);
            $this->http->GetURL($url);
        }

        /*
        if ($this->http->FindSingleNode("//form[@action = '?']/@action")) {
            $captcha = $this->parseReCaptcha();
            if ($captcha == false) {
                return false;
            }
            $this->http->SetInputValue("g-recaptcha-response", $captcha);
//            $this->http->FormURL = 'https://members.onepoll.com/members?';
            if (!$this->http->PostForm()) {
                return false;
            }
            if (!$this->http->ParseForm(null, "//form[contains(@class, 'form-inline')]")) {
                return $this->checkErrors();
            }
            $this->http->SetInputValue('username', $this->AccountFields['Login']);
            $this->http->SetInputValue('password', $this->AccountFields['Pass']);
            $key = $this->http->FindPreg("/grecaptcha\.execute\('([^\']+)', \{ action: 'header_login' /ims");
            $captcha = $this->parseReCaptcha($key, "header_login");
            if ($captcha == false) {
                return false;
            }
            $this->http->SetInputValue('token', $captcha);
            if (!$this->http->PostForm()) {
                return false;
            }
        }
        */

        // Access is allowed
        if ($this->loginSuccessful()) {
            return true;
        }

        if ($message =
            $this->http->FindPreg("/modifyText\('PanelLoginSubmit',\s*'([^\']+)\'/")
            ?? $this->http->FindPreg("/^\s*(Enter valid credentials\.)\s*$/")
        ) {
            $this->logger->error("[Error]: {$message}");

            if (
                strstr($message, 'Invalid Email Address/Password combination.')
                || strstr($message, 'Invalid email/password combination.')
                || strstr($message, 'Enter valid credentials.')
            ) {
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
        // Name
        $this->SetProperty("Name", beautifulName($response->response->firstname . " " . $response->response->lastname));
        // Balance - You have: ... points
        $this->SetBalance($response->response->qPointValue ?? null);
    }

    protected function parseReCaptcha($key = null, $action = null)
    {
        $this->logger->notice(__METHOD__);

        if (!$key) {
            $key = $this->http->FindSingleNode('//form[@action = "?"]//div[@class = "g-recaptcha"]/@data-sitekey');
        }
        $this->logger->debug("data-sitekey: {$key}");

        if (!$key) {
            return false;
        }
        $this->recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_RUCAPTCHA);
        $this->recognizer->RecognizeTimeout = 120;
        $parameters = [
            "pageurl" => $this->http->currentUrl(),
            "proxy"   => $this->http->GetProxy(),
        ];

        if ($action) {
            $parameters += [
                "version"   => "v3",
                "action"    => $action,
                "min_score" => 0.9,
            ];
        }
        $captcha = $this->recognizeByRuCaptcha($this->recognizer, $key, $parameters);

        return $captcha;
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);

        if ($this->http->FindSingleNode("//a[contains(@href, 'Logout')]/@href")) {
            $headers = [
                "Accept"           => "application/json, text/javascript, */*; q=0.01",
                "Accept-Language"  => "en-US,en;q=0.5",
                "Content-Type"     => "application/json; charset=utf-8",
                "X-Requested-With" => "XMLHttpRequest",
            ];
            $this->http->PostURL("https://onepollus.questionpro.eu/a/ajs/survey-angular.panel.portal.PanelMemberAJSHandler-GetLoggedMemberDetails", "{}", $headers);
            $response = $this->http->JsonLog();
            $email = $response->response->emailAddress ?? null;
            $this->logger->debug("[email]: {$email}");

            return strtolower($email) == strtolower($this->AccountFields['Login']);
        }

        return false;
    }

    private function script()
    {
        $this->logger->notice(__METHOD__);

        if ($script = $this->http->FindPreg('#<script>(.+?sucuri_cloudproxy_js.+?)</script>#')) {
            $script = preg_replace('/e\(r\);/', 'sendResponseToPhp(r);', $script);
            $jsExecutor = $this->services->get(\AwardWallet\Common\Parsing\JsExecutor::class);
            $script = $jsExecutor->executeString($script);
            $script = preg_replace('/;document.cookie=/', ';r=', $script);
            $script = preg_replace('/location.reload\(\);/', 'sendResponseToPhp(r);', $script);
            $script = $jsExecutor->executeString($script);
            $this->logger->debug($script);

            if (preg_match('/(\w+)=(\w+);/', $script, $m)) {
                $this->scriptCookie = $m;
                $this->http->setCookie($m[1], $m[2], 'members.onepoll.com', '/', strtotime("+1 day"));
                $this->http->GetURL($this->http->currentUrl());
            }
        }
    }
}
