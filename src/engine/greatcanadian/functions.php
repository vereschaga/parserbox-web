<?php

use AwardWallet\Engine\ProxyList;

class TAccountCheckerGreatcanadian extends TAccountChecker
{
    use ProxyList;

    private $recognizer;

    public static function DisplayName($fields)
    {
        if (isset($fields['Properties']['PayoutOn'])) {
            return $fields["DisplayName"] . " (Payout on {$fields['Properties']['PayoutOn']['Val']})";
        }

        return $fields["DisplayName"];
    }

    public static function GetAccountChecker($accountInfo)
    {
        require_once __DIR__ . "/TAccountCheckerGreatcanadianSelenium.php";

        return new TAccountCheckerGreatcanadianSelenium();
    }

    public function InitBrowser()
    {
        parent::InitBrowser();
        //$this->http->LogHeaders = true;
        //$this->http->SetProxy($this->proxyDOP());
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->RetryCount = 1;
        $this->http->GetURL("https://www.greatcanadianrebates.ca/");
        $this->http->GetURL("https://www.greatcanadianrebates.ca/login.php");

        if (!$this->http->ParseForm(null, 1, true, "//form[contains(@action,'/login.php')]")) {
            return $this->checkErrors();
        }
        $this->http->SetInputValue("uid", $this->AccountFields['Login']);
        $this->http->SetInputValue("pw", $this->AccountFields['Pass']);
        $this->http->SetInputValue("redirurl", "https://www.greatcanadianrebates.ca");
        $this->http->unsetDefaultHeader('merchantsearchname');

        if ($this->http->FindSingleNode("//div[contains(@class, 'g-recaptcha')]/@data-sitekey")) {
            $captcha = $this->parseReCaptcha();

            if ($captcha === false) {
                return false;
            }

            $this->http->SetInputValue("g-recaptcha-response", $captcha);
            $this->http->SetInputValue("captchasource", 1);
        }
        $this->http->RetryCount = 2;

        return true;
    }

    public function checkErrors()
    {
        // The site database appears to be down
        if ($message = $this->http->FindPreg("/The site database appears to be down\./")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
        if (!$this->http->PostForm()) {
            return $this->checkErrors();
        }

        //# Login Incorrect
        if ($message = $this->http->FindSingleNode("//div[contains(@id, 'incorrectlogin')]/p")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        $this->http->GetURL("https://www.greatcanadianrebates.ca/Balance/");

        if ($this->http->FindSingleNode("//a[contains(@href, 'logout.php')]/@href")) {
            return true;
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        // Payment Type
        $this->SetProperty("PaymentType", $this->http->FindSingleNode("//b[contains(text(),'Payment Type :')]", null, false, '/\s*:\s*([\w\s]+)/'));
        // Cash Back Rebates Eligible to be Paid
        $this->SetProperty("CashBackRebatesPaid", $this->http->FindSingleNode("//div[contains(text(), 'Cash Back Rebates Still in Waiting Stage')]/preceding::tr[1]//td[contains(text(), 'Subtotal:')]/following-sibling::td[1]"));
        // Balance - Current Balance
        $this->SetBalance($this->http->FindSingleNode("//p[contains(text(),'Total: ')]", null, true, "/:\s*([\d\.\,]+)/ims"));

        // Name
        $this->http->GetURL("https://www.greatcanadianrebates.ca/settings.php");
        $name = CleanXMLValue($this->http->FindSingleNode("//input[@name = 'fname']/@value")
                . ' ' . $this->http->FindSingleNode("//input[@name = 'lname']/@value"));

        if (strlen($name) > 2) {
            $this->SetProperty("Name", beautifulName($name));
        }
    }

    protected function parseReCaptcha()
    {
        $this->logger->notice(__METHOD__);
        $key = $this->http->FindSingleNode("//div[contains(@class, 'g-recaptcha')]/@data-sitekey");
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
        $captcha = $this->recognizeByRuCaptcha($this->recognizer, $key, $parameters);

        return $captcha;
    }
}
