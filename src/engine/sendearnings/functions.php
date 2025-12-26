<?php

use AwardWallet\Common\Parsing\Html;
use AwardWallet\Engine\ProxyList;

class TAccountCheckerSendearnings extends TAccountChecker
{
    use ProxyList;

    public const REWARDS_PAGE_URL = 'https://www.sendearnings.com/members/earnings';

    private $form;
    private $formUrl;

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->LogHeaders = true;
        $this->http->SetProxy($this->proxyReCaptcha(), false);
    }

    public function IsLoggedIn()
    {
        $this->http->RetryCount = 0;
        $this->http->GetURL(self::REWARDS_PAGE_URL, [], 20);
        $this->http->RetryCount = 2;

        if ($this->loginSuccessful() && $this->http->FindSingleNode("//tr[@class='summary']/td[2]")) {
            return true;
        }

        return false;
    }

    public function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);

        if ($this->http->FindPreg("/Logout/")) {
            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->GetURL("https://www.sendearnings.com/");

        if ($message = $this->http->FindSingleNode('//p[contains(text(), "SendEarnings will be permanently closed on July 2")]')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        sleep(rand(1, 5));

        if (!$this->http->ParseForm("loginForm")) {
            return false;
        }
        $this->http->SetInputValue('Member[username]', $this->AccountFields['Login']);
        $this->http->SetInputValue('Member[password]', $this->AccountFields['Pass']);

        $this->formUrl = $this->http->FormURL;
        $this->form = $this->http->Form;

        return true;
    }

    public function Login()
    {
        if (!$this->http->PostForm()) {
            return false;
        }
        $this->incapsula();
        $this->incapsula();

        if ($this->http->FindPreg('/window.parent.location.reload\(true\)/')) {
            $this->http->FormURL = $this->formUrl;
            $this->http->Form = $this->form;

            if (!$this->http->PostForm()) {
                return false;
            }
        }

        // Redirect to error page
        if ($location = $this->http->FindPreg("/<script>window\.parent\.location='(\/members\/login_error)';<\/script>/")) {
            $this->logger->notice("Redirect to {$location}");
            $this->http->NormalizeURL($location);
            $this->http->GetURL($location);
        }// if ($location = $this->http->FindPreg("/<script>window\.parent\.location='(\/members\/login_error)';<\/script>/"))

        if ($message = $this->http->FindSingleNode("//p[@class = 'al3rt']")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        // Unfortunately, this account has been inactive for more than 6 months and has been removed in accordance with our Terms & Conditions.
        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'Unfortunately, this account has been inactive')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        if ($message = $this->http->FindSingleNode("//div[@class = 'alert error-minor']")) {
            throw new CheckException("The Log in and Password combination you provided doesn't match our records", ACCOUNT_INVALID_PASSWORD);
        }

        if ($this->loginSuccessful()) {
            return true;
        }

        return false;
    }

    public function Parse()
    {
        if ($this->http->currentUrl() != self::REWARDS_PAGE_URL) {
            $this->http->GetURL(self::REWARDS_PAGE_URL);
        }
        // Name
        $this->SetProperty("Name", beautifulName($this->http->FindSingleNode("//div[@class='headerPersonalText']", null, true, "/Hi\s*([^\!]+)/")));
        // Status
        if ($this->http->FindSingleNode("//img[contains(@alt, 'Gold Member') or contains(@alt, 'GoldMember')]/@alt")) {
            $status = "Gold";
        } else {
            $status = "Member";
        }
        $this->SetProperty("Status", $status);
        //# Balance - Total
        $this->SetBalance($this->http->FindSingleNode("//tr[@class='summary']/td[2]", null, true, '/[0-9\.]+/i'));

        if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR) {
            /*
             * Per your request, your Account has been canceled.
             * If you would like to reactivate your Account,
             * please contact our Member Services department by visiting our Support Center.
             */
            if ($message = $this->http->FindSingleNode("//p[contains(text(), 'Per your request, your Account has been canceled')]")) {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }
            /*
             * In accordance with our Terms of Membership, your Account has been terminated.
             * If you feel this is an error,
             * please contact our Member Services department by visiting our Support Center.
             */
            if ($message = $this->http->FindSingleNode("//p[contains(text(), 'In accordance with our Terms of Membership, your Account has been terminated')] | //p[contains(text(), 'In accordance with our Terms & Conditions, your account has been terminated due to violation of terms')]")) {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }
        }

        $this->http->GetURL("https://www.sendearnings.com/members/my_profile");
        // Full Name
        $name = $this->http->FindSingleNode("//div[contains(text(), 'First Name')]/following-sibling::div[1]") . ' ' . $this->http->FindSingleNode("//div[contains(text(), 'Last Name')]/following-sibling::div[1]");

        if (strlen(Html::cleanXMLValue($name)) > 2) {
            $this->SetProperty("Name", beautifulName($name));
        }
    }

    public function GetRedirectParams($targetURL = null)
    {
        $arg = parent::GetRedirectParams($targetURL);
        $arg["CookieURL"] = "https://www.sendearnings.com/members/login";
        $arg["SuccessURL"] = "https://www.sendearnings.com/members/earnings";

        return $arg;
    }

    protected function incapsula()
    {
        $this->logger->notice(__METHOD__);
        $referer = $this->http->currentUrl();
        $incapsula = $this->http->FindSingleNode("//iframe[contains(@src, '/_Incapsula_Resource?')]/@src");

        if (isset($incapsula)) {
            sleep(2);
            $this->http->NormalizeURL($incapsula);
            $this->http->RetryCount = 0;
            $this->http->FilterHTML = false;
            $this->http->GetURL($incapsula);
            $this->http->RetryCount = 2;
        }// if (isset($distil))
        $this->logger->debug("parse captcha form");
        $action = $this->http->FindPreg("/xhr.open\(\"POST\", \"([^\"]+)/");

        if (!$action) {
            return false;
        }
        $captcha = $this->parseReCaptcha();

        if ($captcha === false) {
            return false;
        }
        $this->http->RetryCount = 0;
        $this->http->PostURL('https://www.sendearnings.com' . $action, [
            'g-recaptcha-response' => $captcha, ],
            ["Referer" => $referer, "Content-Type" => "application/x-www-form-urlencoded"]);
        $this->http->RetryCount = 2;
        $this->http->FilterHTML = true;
        sleep(2);

        return true;
    }

    protected function parseReCaptcha()
    {
        $this->logger->notice(__METHOD__);
        $key = $this->http->FindSingleNode("//div[contains(@class, 'g-recaptcha')]/@data-sitekey");
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
