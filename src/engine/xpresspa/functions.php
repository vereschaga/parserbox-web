<?php

use AwardWallet\Engine\ProxyList;

class TAccountCheckerXpresspa extends TAccountChecker
{
    use ProxyList;

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->SetProxy($this->proxyReCaptcha());
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->GetURL("https://checkbalance.rewardforloyalty.com/");

        if (!$this->http->ParseForm("balanceForm")) {
            return false;
        }
        $this->http->SetInputValue('act', $this->AccountFields['Login']);
        $this->http->SetInputValue('x', "28");
        $this->http->SetInputValue('y', "17");

        $captcha = $this->parseCaptcha();

        if ($captcha === false) {
            return false;
        }
        $this->http->SetInputValue('g-recaptcha-response', $captcha);

        return true;
    }

    public function Login()
    {
        if (!$this->http->PostForm()) {
            return false;
        }

        if ($error = $this->http->FindSingleNode("//p[contains(@class, 'error')]")) {
            throw new CheckException($error, ACCOUNT_INVALID_PASSWORD);
        }
        // Invalid captcha entry
        if ($error = $this->http->FindSingleNode("//div[contains(@class, 'error') and contains(text(), 'Invalid captcha entry')]")) {
            throw new CheckRetryNeededException(3, 7, self::CAPTCHA_ERROR_MSG);
        }

        return true;
    }

    public function Parse()
    {
        //# Cash Balance
        $this->SetProperty("CashBalance", $this->http->FindSingleNode("//tr[th[contains(text(), 'Cash Balance')]]/following-sibling::tr[@class = 'main_text_results']/td[1]"));
        //# Lifetime Points
        $this->SetProperty("LifetimePoints", $this->http->FindSingleNode("//tr[th[contains(text(), 'Cash Balance')]]/following-sibling::tr[@class = 'main_text_results']/td[3]"));
        //# Expiration
        $expiration = $this->http->FindSingleNode("//tr[th[contains(text(), 'Cash Balance')]]/following-sibling::tr[@class = 'main_text_results']/td[4]");

        if (!empty($expiration)) {
            $this->sendNotification("XpresSpa (Members) - xpresspa. Expiration found");
        }
//        $this->SetProperty("Expiration", $this->http->FindSingleNode("//tr[th[contains(text(), 'Cash Balance')]]/following-sibling::tr[@class = 'main_text_results']/td[4]"));

        //# Balance - Reward Points
        $find = $this->http->FindSingleNode("//tr[th[contains(text(), 'Cash Balance')]]/following-sibling::tr[@class = 'main_text_results']/td[2]");

        if ($find != null) {
            $this->SetBalance($find);
        }

        if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR && isset($this->Properties['CashBalance'])
            && $this->Properties['CashBalance'] == '0.00') {
            $this->SetBalanceNA();
        }
    }

    public function GetRedirectParams($targetURL = null)
    {
        $arg = parent::GetRedirectParams($targetURL);
        //		$arg['NoCookieURL'] = true;
        //		$arg['RequestMethod'] = 'GET';

        return $arg;
    }

    protected function parseCaptcha()
    {
        $this->logger->notice(__METHOD__);
        $key = $this->http->FindSingleNode("//form[@name = 'balanceForm']//div[@class = 'g-recaptcha']/@data-sitekey");
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
        $captcha = $this->recognizeByRuCaptcha($recognizer, $key, $parameters);

        return $captcha;
    }
}
