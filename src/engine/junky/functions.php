<?php

use AwardWallet\Engine\ProxyList;

class TAccountCheckerJunky extends TAccountChecker
{
    use ProxyList;
    use SeleniumCheckerHelper;

    public static function DisplayName($fields)
    {
        if (isset($fields['Properties']['CurrentPayoutDate'])) {
            $currentPayoutDate = preg_replace("/^.+\-\s*/", '', $fields['Properties']['CurrentPayoutDate']['Val']);

            return $fields["DisplayName"] . " (Payout on {$currentPayoutDate})";
        }

        return $fields["DisplayName"];
    }

    public static function GetAccountChecker($accountInfo)
    {
        require_once __DIR__ . "/TAccountCheckerJunkySelenium.php";

        return new TAccountCheckerJunkySelenium();
    }

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->SetProxy($this->proxyDOP());
//        $this->http->setRandomUserAgent(10, true, false, false);
    }

    public function IsLoggedIn()
    {
        $this->http->RetryCount = 0;
        $this->http->GetURL("https://www.activejunky.com/?auth_type=login", [], 20);
        $this->challengeForm();
        $this->http->RetryCount = 2;

        if ($this->loginSuccessful()) {
            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->RetryCount = 0;

        // _px3 cookie workaround
        $this->checkCookies();
//        $v8 = new V8Js();
//        $script = "
//            var lut = []; for (var i=0; i<256; i++) { lut[i] = (i<16?'0':'')+(i).toString(16); }
//            function e7()
//            {
//                var d0 = Math.random()*0xffffffff|0;
//                var d1 = Math.random()*0xffffffff|0;
//                var d2 = Math.random()*0xffffffff|0;
//                var d3 = Math.random()*0xffffffff|0;
//                return lut[d0&0xff]+lut[d0>>8&0xff]+lut[d0>>16&0xff]+lut[d0>>24&0xff]+'-'+
//                    lut[d1&0xff]+lut[d1>>8&0xff]+'-'+lut[d1>>16&0x0f|0x40]+lut[d1>>24&0xff]+'-'+
//                    lut[d2&0x3f|0x80]+lut[d2>>8&0xff]+'-'+lut[d2>>16&0xff]+lut[d2>>24&0xff]+
//                    lut[d3&0xff]+lut[d3>>8&0xff]+lut[d3>>16&0xff]+lut[d3>>24&0xff];
//            }
//
//            e7();
//        ";
//        $encrypted = $v8->executeString($script, 'basic.js');
        ////        $this->logger->debug("encrypted: " . $encrypted);
//        $this->http->setCookie("session_id", $encrypted, "www.activejunky.com");

        $this->http->GetURL('https://www.activejunky.com/');
        $this->http->RetryCount = 2;
        $this->challengeForm();
        $this->challengeForm();
        $this->challengeForm();
        $this->shieldSquareCaptcha();

        // retries
        if ($this->http->Response['code'] == 403) {
            throw new CheckRetryNeededException(2);
        }

        $csrfToken = $this->http->FindSingleNode('//meta[@name="csrf-token"]/@content');

        // Solve the CAPTCHA to request unblock to website.
        if (empty($csrfToken) && !$this->http->ParseForm("authenticationForm")
            && $this->http->ParseForm(null, "//div[@class = 'captcha-mid']/form")) {
            $captcha = $this->parseCaptcha();

            if ($captcha === false) {
                return false;
            }
            $this->http->SetInputValue('g-recaptcha-response', $captcha);
            $this->http->PostForm();
            $csrfToken = $this->http->FindSingleNode('//meta[@name="csrf-token"]/@content');
        }

        if (empty($csrfToken) || !$this->http->ParseForm("authenticationForm")) {
            return false;
        }

        $this->http->setDefaultHeader('X-CSRF-Token', $csrfToken);
        $this->http->SetInputValue('member[email]', $this->AccountFields['Login']);
        $this->http->SetInputValue('member[password]', $this->AccountFields['Pass']);
        $this->http->SetInputValue('commit', "Sign In");

        return true;
    }

    public function Login()
    {
        $this->http->RetryCount = 0;
        $headers = [
            'X-Requested-With' => 'XMLHttpRequest',
            "Accept"           => "*/*;q=0.5, text/javascript, application/javascript, application/ecmascript, application/x-ecmascript",
        ];
        $this->http->PostForm($headers);
        $this->http->RetryCount = 2;

        if ($redirect = $this->http->FindSingleNode("//body[contains(text(), 'You are being')]/a/@href")) {
            // fixed bad link
            if (strpos($redirect, '//www.activejunky.com/?auth_update=tru') === 0) {
                $redirect = 'https:' . $redirect;
            }

            $this->internalRedirect($redirect);

            if ($this->loginSuccessful()) {
                return true;
            }

            // Update Password
            if ($message = $this->http->FindSingleNode('//p[contains(text(), "We\'ve updated the password requirements for your Active Junky account.")]')) {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }
        }// if ($redirect = $this->http->FindSingleNode("//body[contains(text(), 'You are being')]/a/@href"))

        $response = $this->http->JsonLog();
        // Invalid email/password
        if (false !== $response && !empty($response->errors) && $response->errors == 'Invalid email/password') {
            throw new CheckException($response->errors, ACCOUNT_INVALID_PASSWORD);
        }
        // Account login temporarily locked because of too many login attempts. Try logging in again later or reset your password.
        if (false !== $response && !empty($response->errors) && $response->errors == 'Account login temporarily locked because of too many login attempts. Try logging in again later or reset your password.') {
            throw new CheckException($response->errors, ACCOUNT_LOCKOUT);
        }
        // Please try again. If this persists, please contact support.
        if (false !== $response && !empty($response->errors) && $response->errors == 'Please try again. If this persists, please contact support.') {
            throw new CheckRetryNeededException(3, 0);
        }
        /*
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        */
        // We're sorry, but something went wrong (500)
        if ($message = $this->http->FindSingleNode('//h1[contains(text(), "We\'re sorry, but something went wrong")]')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function internalRedirect($url)
    {
        $this->logger->notice(__METHOD__);
        $this->http->NormalizeURL($url);
        $this->http->GetURL($url);
        $this->shieldSquareCaptcha();
        $this->shieldSquareCaptcha();
    }

    public function Parse()
    {
        // Lifetime Cash Back
        $this->SetProperty('LifetimeCashBack', $this->http->FindSingleNode("//div[contains(text(), 'Lifetime Cash Back')]/following-sibling::div[1]"));
        // Member Since
        $this->SetProperty('MemberSince', $this->http->FindSingleNode("//div[contains(text(), 'Member Since')]/following-sibling::div[1]"));

        $myProfile = $this->http->FindSingleNode("(//a[contains(text(), 'My Profile')]/@href)[1]");

        if (!$myProfile) {
            return;
        }
        $this->internalRedirect($myProfile);

        if (
            strstr($this->http->currentUrl(), 'https://www.activejunky.com/members/auth_challenge?auth_gate=false')
            && $this->http->ParseForm("shortcodeAuthenticationForm")
        ) {
            $this->http->SetInputValue('member[email]', $this->AccountFields['Login']);
            $this->http->SetInputValue('member[password]', $this->AccountFields['Pass']);
            $this->http->SetInputValue('commit', "Sign In");
        }

        $cashBackPendingXpath = '//h4[normalize-space(text()) = "Current Payout:"]
            /following-sibling::h4[
                contains(., "Cashback Pending:")
                or contains(., "Cash Back Pending:")
            ]/span';

        if (!$this->http->FindSingleNode($cashBackPendingXpath)) {
            $this->internalRedirect($myProfile);

            if (!$this->http->FindSingleNode($cashBackPendingXpath)) {
                $this->internalRedirect($myProfile);
            }
        }

        // Balance - Current Payout Cash
        // if exist only sections: Previous Payout, Current Payout
        $cashBackPending = $this->http->FindSingleNode($cashBackPendingXpath, null, true, "/\\$(.+)/");
        $pendingOrdersCashback = $this->http->FindSingleNode('//h4[normalize-space(text()) = "Current Payout:"]/following-sibling::h4[contains(., "Pending Orders Cashback:")]/span', null, true, "/\\$(.+)/");

        $this->logger->debug('[cashBackPending]: ' . $cashBackPending);
        $this->logger->debug('[pendingOrdersCashback]: ' . $pendingOrdersCashback);

        if ($cashBackPending !== null || $pendingOrdersCashback !== null) {
            $cashBackPending = str_replace(',', '', $cashBackPending);
            $this->SetBalance(($cashBackPending ?: 0) + ($pendingOrdersCashback ?: 0));
        }

        if (!isset($this->Balance)) {
            // if exist only sections: Previous Payout, Processing Payout, Future Payout
            $this->SetBalance($this->http->FindSingleNode('//h4[normalize-space(text())="Future Payout:"]/following-sibling::h4[
                    contains(.,"Cashback Pending:")
                    or contains(., "Cash Back Pending")
                ]/span')
            );
        }
        // Current Payout Date
        $this->SetProperty('CurrentPayoutDate',
            $this->http->FindSingleNode('//h4[normalize-space(text())="Current Payout:"]/span')
            // if exist only sections: Previous Payout, Processing Payout, Future Payout
            ?? $this->http->FindSingleNode('//h4[normalize-space(text())="Future Payout:"]/span')
        );
        // Processing Payout Cash
        $this->SetProperty('ProcessingPayoutCash', $this->http->FindSingleNode('//h4[normalize-space(text())="Processing Payout:"]/following-sibling::h4[
            contains(.,"Cashback Pending:")
            or contains(.,"Cash Back Pending:")
        ]/span'));
        // Processing Payout Date
        $this->SetProperty('ProcessingPayoutDate', $this->http->FindSingleNode('//h4[normalize-space(text())="Processing Payout:"]/span'));
        // Previous Payout
        $this->SetProperty('PrevPayoutDate', $this->http->FindSingleNode('//h4[@class="payout-header-prev"]/span'));
        // Cash Paid
        $this->SetProperty('PrevPayoutCash', $this->http->FindSingleNode('//h4[@class="payout-header-prev"]/following-sibling::h4[
            contains(.,"Cashback Paid:")
            or contains(.,"Cash Back Paid:")
        ]/span'));

        $this->internalRedirect($myProfile . '/account');
        // Name
        $this->SetProperty('Name', beautifulName(trim($this->http->FindSingleNode('//input[@id="first_name"]/@value') . ' ' . $this->http->FindSingleNode('//input[@id="last_name"]/@value'))));
    }

    protected function parseCaptcha()
    {
        $this->logger->notice(__METHOD__);
        $key = $this->http->FindSingleNode("//form/div[@class = 'g-recaptcha']/@data-sitekey");
        $this->logger->debug("data-sitekey: {$key}");

        if (!$key) {
            return false;
        }

        $recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_RUCAPTCHA);
        $recognizer->RecognizeTimeout = 120;
        $parameters = [
            "pageurl" => $this->http->currentUrl(),
            "proxy"   => $this->http->GetProxy(),
        ];

        return $this->recognizeByRuCaptcha($recognizer, $key, $parameters);
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);

        if ($this->http->FindNodes('//a[text() = "Log Out"]')) {
            return true;
        }

        return false;
    }

    private function challengeForm()
    {
        $this->logger->notice(__METHOD__);
        $script = $this->http->FindPreg("/setTimeout\(function\(\)\{(.+?)'; 121'/s");

        if (!$script) {
            return false;
        }

        $script = str_replace('a.value = ', '', $script);
        $script = str_replace('+ t.length', "+ 'www.activejunky.com'.length", $script);
        $script = preg_replace("/t = document.createElement\('div'\);.+?getElementById\('challenge-form'\);/s", '', $script);
        // not sure
        $script = "sendResponseToPhp($script)";
        $this->logger->debug($script);

        $jsExecutor = $this->services->get(\AwardWallet\Common\Parsing\JsExecutor::class);
        $encrypted = $jsExecutor->executeString($script);
        $this->logger->debug("encrypted: " . $encrypted);

        sleep(4);
        $params = [];
        $inputs = $this->http->XPath->query("//form[@id='challenge-form']//input");

        for ($n = 0; $n < $inputs->length; $n++) {
            $input = $inputs->item($n);
            $params[$input->getAttribute('name')] = $input->getAttribute('value');
        }

        if (!empty($params)) {
            $action = $this->http->FindSingleNode("//form[@id='challenge-form']/@action");
            $this->http->NormalizeURL($action);
            $this->http->RetryCount = 0;
            $this->http->GetURL($action . '?' . http_build_query($params) . $encrypted);
            $this->http->RetryCount = 2;
        }

        return true;
    }

    private function shieldSquareCaptcha()
    {
        $this->logger->notice(__METHOD__);
        // ShieldSquare Captcha
        if (!$this->http->FindSingleNode("//title[contains(text(), 'ShieldSquare Captcha')]")) {
            return;
        }
        $captcha = $this->parseCaptcha();

        if ($captcha !== false) {
            $this->http->SetInputValue('g-recaptcha-response', $captcha);
            $this->http->RetryCount = 0;
            $this->http->PostForm();
            $this->http->RetryCount = 2;
        }
    }

    private function checkCookies()
    {
        $this->logger->notice(__METHOD__);

        $selenium = clone $this;
        $retry = false;
        $this->http->brotherBrowser($selenium->http);

        try {
            $this->logger->notice("Running Selenium...");
            $selenium->UseSelenium();
            $selenium->useChromium();
            $selenium->disableImages();
            $selenium->useCache();
            $selenium->http->start();
            $selenium->Start();
            $selenium->http->saveScreenshots = true;

            $selenium->http->removeCookies();
            $selenium->http->GetURL("https://www.activejunky.com/");

            $cookies = $selenium->driver->manage()->getCookies();

            foreach ($cookies as $cookie) {
                $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
            }

            $this->saveToLogs($selenium);
            $this->logger->debug("[Current URL]: {$selenium->http->currentUrl()}");
        } catch (ScriptTimeoutException $e) {
            $this->logger->error("ScriptTimeoutException exception: " . $e->getMessage());
            $this->DebugInfo = "ScriptTimeoutException";
            // retries
            if (strstr($e->getMessage(), 'timeout: Timed out receiving message from renderer')) {
                $retry = true;
            }
        }// catch (ScriptTimeoutException $e)
        finally {
            // close Selenium browser
            $selenium->http->cleanup();
            // retries
            if ($retry && ConfigValue(CONFIG_SITE_STATE) != SITE_STATE_DEBUG) {
                throw new CheckRetryNeededException(3);
            }
        }

        return true;
    }

    private function saveToLogs($selenium)
    {
        $this->logger->notice(__METHOD__);
        $selenium->http->SaveResponse();
        $this->http->SetBody($selenium->driver->executeScript('return document.documentElement.innerHTML'));
        $this->http->SaveResponse();
    }
}
