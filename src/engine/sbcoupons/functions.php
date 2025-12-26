<?php

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\ProxyList;

class TAccountCheckerSbcoupons extends TAccountChecker
{
    use ProxyList;
    /**
     * @var CaptchaRecognizer
     */
    private $recognizer;

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
//        $this->http->SetProxy($this->proxyReCaptcha());
    }

    public function GetRedirectParams($targetURL = null)
    {
        $arg = parent::GetRedirectParams();

        return $arg;
    }

    public function IsLoggedIn()
    {
        $this->http->RetryCount = 0;
        $this->http->GetURL("https://www.simplybestcoupons.com/Protected/Account/Cashback/", [], 20);
        $this->http->RetryCount = 2;

        if ($this->loginSuccessful()) {
            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->GetURL("https://www.simplybestcoupons.com/Accounts/Logon.aspx?ReturnUrl=%2fProtected%2fAccount%2fCashback%2f");

        if (!$this->http->ParseForm(null, "//input[@type='password']/ancestor::form[1]")) {
            return false;
        }
        $this->http->SetInputValue("email", $this->AccountFields["Login"]);
        $this->http->SetInputValue("password", $this->AccountFields["Pass"]);

        // not needed captcha on the prod
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
        // Add Cell Phone Number
        if ($this->http->FindSingleNode("//h1[contains(text(), 'Add Cell Phone Number')]")) {
            $this->throwProfileUpdateMessageException();
        }
        // logged in
        if ($this->loginSuccessful()) {
            return true;
        }
        // invalid credentials
        $message = $this->http->FindSingleNode("//ul[@class = 'Errors']/li | //div[contains(@class, 'alert alert-danger')]/ul/li");
        $this->logger->error('error message: ' . $message);

        if ($message) {
            // if ($message) // unconrolled language. russian on local
            if (
                strstr($message, "Email address or password incorrect.")
                || strstr($message, 'You signed up for newsletter with this email, but don\'t have an account.')
            ) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            if (strstr($message, "Too many login attempts. Please wait an hour and try again.")
                // Account not active, please verify your email.
                || strstr($message, "Account not active, please verify your email.")) {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }
            // Account is disabled. Please contact customer support.
            if (strstr($message, "Account is disabled. Please contact customer support.")) {
                throw new CheckException($message, ACCOUNT_LOCKOUT);
            }

            // Invalid CAPTCHA
            if (
                $message == "Invalid CAPTCHA"
                || $message == "Please use reCAPTCHA to verify that you are not a robot."
            ) {
                throw new CheckRetryNeededException(3, 10, self::CAPTCHA_ERROR_MSG);
            }
        }

        return false;
    }

    public function Parse()
    {
        // Pending
        $pending = $this->http->FindSingleNode("//div[@id = 'summary-blocks']//h2[normalize-space(.) = 'Pending' or normalize-space(.)= 'Pendiente']/following-sibling::p[1]/span[@class = 'value']", null, true, self::BALANCE_REGEXP_EXTENDED);

        if ($pending !== null) {
            $pending = PriceHelper::cost($pending);
            $this->AddSubAccount([
                "Code"              => "sbcouponsPending",
                "DisplayName"       => "Pending",
                "Balance"           => $pending,
                "BalanceInTotalSum" => true,
            ]);
        }
        // Approved
        $approved = $this->http->FindSingleNode("//div[@id = 'summary-blocks']//h2[normalize-space(.)='Approved' or normalize-space(.)= 'Aprobado']/following-sibling::p[1]/span[@class = 'value']", null, true, self::BALANCE_REGEXP_EXTENDED);

        if ($approved !== null) {
            $approved = PriceHelper::cost($approved);
            $this->AddSubAccount([
                "Code"              => "sbcouponsApproved",
                "DisplayName"       => "Payable",
                "Balance"           => $approved,
                "BalanceInTotalSum" => true,
            ]);
        }
        // Balance = Pending + Approved (Payable)
        if ($pending !== null && $approved !== null) {
            $this->SetBalance($pending + $approved);
        }
        // LastPayment - Last	$28.22 12/22/2016
        $this->SetProperty("LastPayment", $this->http->FindSingleNode("//td[div[normalize-space(.)='Last']]/following-sibling::td[1]/descendant::text()[normalize-space(.)][1]"));
        // LastPaymentDate - Last	$28.22 12/22/2016
        $this->SetProperty("LastPaymentDate", $this->http->FindSingleNode("//td[div[normalize-space(.)='Last']]/following-sibling::td[1]/descendant::text()[normalize-space(.)][2]"));
        // TotalPayment - Total	$543.61
        $this->SetProperty("TotalPayment", $this->http->FindSingleNode("//td[div[normalize-space(.)='Total']]/following-sibling::td[1]/a"));

        // Number of referrals	59
        // Pending	$1.75
        // Approved	$0.63
        $this->SetProperty("NumberOfReferrals", $this->http->FindSingleNode("//td[div[normalize-space(.)='Number of referrals' or normalize-space(.)='Número de referencias']]/following-sibling::td[1]/descendant::text()[normalize-space(.)][1]", null, true, "#^\d+$#"));
        $this->SetProperty("ApprovedReferralsBonus", $this->http->FindSingleNode("//text()[normalize-space(.)='Referrals' or normalize-space(.)='Referencias']/ancestor::header[1]/following-sibling::div[1]//tr/td[div[normalize-space(.)='Approved' or normalize-space(.)='Aprobado']]/following-sibling::td[1]/descendant::text()[normalize-space(.)][1]"));
        $this->SetProperty("PendingReferralsBonus", $this->http->FindSingleNode("//text()[normalize-space(.)='Referrals' or normalize-space(.)='Referencias']/ancestor::header[1]/following-sibling::div[1]//tr/td[div[normalize-space(.)='Pending' or normalize-space(.)='Pendiente']]/following-sibling::td[1]/descendant::text()[normalize-space(.)][1]"));

        $this->http->GetURL("https://www.simplybestcoupons.com/Protected/Account/Preferences/");
        // Name
        $this->SetProperty("Name", beautifulName($this->http->FindSingleNode("//td[normalize-space(.)='First Name' or normalize-space(.)='Nombre de pila']/following-sibling::td[1]") . ' ' . $this->http->FindSingleNode("//td[normalize-space(.)='Last Name' or normalize-space(.)='Apellido']/following-sibling::td[1]")));
    }

    protected function parseCaptcha()
    {
        $this->logger->notice(__METHOD__);
        $key = $this->http->FindSingleNode("//input[@type='password']/ancestor::form[1]//div[@class = 'g-recaptcha']/@data-sitekey");
        $this->logger->debug("data-sitekey: {$key}");

        if (!$key) {
            $this->sendNotification('not captcha // MI');
            return false;
        }
        $this->sendNotification('yes captcha // MI');
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

        if ($this->http->FindNodes("//div[@id = 'AccountStatus']//a[contains(@href, 'Logout')]")) {
            return true;
        }

        return false;
    }
}
