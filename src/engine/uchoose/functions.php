<?php

use AwardWallet\Engine\ProxyList;

class TAccountCheckerUchoose extends TAccountChecker
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
        $this->http->SetProxy($this->proxyReCaptcha());
        $this->http->setDefaultHeader("Accept-Encoding", "gzip, deflate, br");
    }

    public function IsLoggedIn()
    {
        $this->http->RetryCount = 0;
        $this->http->GetURL("https://uchooserewards.com/e/members/home.php", [], 20);
        $this->http->RetryCount = 2;

        if ($this->loginSuccessful()) {
            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->FilterHTML = false;
        $this->http->GetURL('https://uchooserewards.com/e/members/verifypasswd.php?sid=40XXdKrlo40&method=login');

        if (!$this->http->ParseForm('login')) {
            return $this->checkErrors();
        }
        $this->http->SetInputValue('username', $this->AccountFields['Login']);
        $this->http->SetInputValue('password', $this->AccountFields['Pass']);
        $this->http->SetInputValue('submit', "submit");

        $captcha = $this->parseReCaptcha();

        if ($captcha) {
            $this->http->SetInputValue('g-recaptcha-response', $captcha);
        }

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);

        if ($message = $this->http->FindSingleNode('//font[contains(text(), "Sorry, we are currently experiencing technical")]')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
        if (!$this->http->PostForm()) {
            return false;
        }

        if ($this->loginSuccessful()) {
            $this->captchaReporting($this->recognizer);

            return true;
        }
        // Incorrect password
        /**
         * The user name or password you entered is not valid. Note that user names are case sensitive. Try again or contact us for assistance.
         * Not registered for the uChoose Rewards® website? Register now.
         * If you have been reissued a card and your card number changed, either because your card was lost, stolen or simply replaced,
         * please re-register for the program.
         */
        if ($message = $this->http->FindSingleNode('//div[contains(@class, "alert-danger") and (
                contains(., "Incorrect password.")
                or contains(., "The user name or password you entered is not valid.")
                or contains(., "Please contact your financial institution.")
            )]
            | //div[contains(@class, "alert-warning") and (
                contains(., "Your password has expired.")
            )]
        ')) {
            $this->captchaReporting($this->recognizer);

            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        if ($message = $this->http->FindSingleNode('//div[contains(@class, "alert-danger") and (
                contains(., "To login to uChoose Rewards, please access your financial institution’s website or mobile application")
            )]')
        ) {
            $this->captchaReporting($this->recognizer);

            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        // Program Terms & Conditions
        if ($this->http->FindSingleNode('
                //p[contains(text(), "If You participate in the uChoose Rewards® Program, You agree to the following terms and conditions.")]
                | //label[contains(text(), "I accept the Program Terms & Conditions")]
            ')
        ) {
            $this->captchaReporting($this->recognizer);
            $this->throwAcceptTermsMessageException();
        }

        // retry, may be captcha answer wrong?
        if ($message = $this->http->FindSingleNode('//div[contains(@class, "alert-danger") and ( 
                contains(., "One or more required fields weren\'t completed properly. Try again.")
                or contains(., "One or more required fields weren")
            )]')
        ) {
            $this->captchaReporting($this->recognizer, false);

            throw new CheckRetryNeededException(2, 0, $message);
        }

        // no errors, no auth
        if (
            in_array($this->AccountFields['Login'], [
                'kimberlypc', // AccountID: 4548261
                'gsantos3337', // AccountID: 4032216
                'Kgov02', // AccountID: 3229019
                'thomas1235', // AccountID: 6197294
            ])
        ) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        // Balance - Points Available
        $this->SetBalance($this->http->FindSingleNode("//span[@class = 'available-points']", null, true, self::BALANCE_REGEXP));
        // Name
        $this->SetProperty('Name', beautifulName($this->http->FindSingleNode("//div[@aria-labelledby = 'navbarAccountDropdownMenuLink']/span[1]")));
        // Expiring balance
        $expiringBalance = $this->http->FindSingleNode("//span[@class = 'expiring-points']");
        $this->SetProperty('ExpiringBalance', $expiringBalance);
        $exp = $this->http->FindSingleNode("//span[@class = 'expiry-date']");

        if ($expiringBalance > 0 && $exp) {
            $this->SetExpirationDate(strtotime($exp));
        }
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);

        if ($this->http->FindSingleNode("//a[contains(@href, 'logout')]")) {
            return true;
        }

        return false;
    }

    private function parseReCaptcha()
    {
        $this->logger->notice(__METHOD__);
        $key = $this->http->FindSingleNode("//form[@id = 'login']//div[@class = 'g-recaptcha']/@data-sitekey");

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
}
