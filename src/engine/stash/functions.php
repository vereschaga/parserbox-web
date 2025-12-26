<?php

// refs #2062
use AwardWallet\Engine\ProxyList;

class TAccountCheckerStash extends TAccountChecker
{
    use ProxyList;
    private const REWARDS_PAGE_URL = 'https://www.stashrewards.com/account';
    /**
     * @var CaptchaRecognizer
     */
    private $recognizer;

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->SetProxy($this->proxyReCaptcha());
    }

    public function IsLoggedIn()
    {
        $this->http->RetryCount = 0;
        $this->http->GetURL(self::REWARDS_PAGE_URL, [], 20);
        $this->http->RetryCount = 2;

        if ($this->loginSuccessful()) {
            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();

        $this->http->GetURL('https://www.stashrewards.com/login');

        if (!$this->http->ParseForm('login-page-form')) {
            return $this->checkErrors();
        }

        $this->http->SetInputValue('email_address', $this->AccountFields['Login']);
        $this->http->SetInputValue('password', $this->AccountFields['Pass']);
        $this->http->SetInputValue('[show_password]', 0);

        $captcha = $this->parseCaptcha('6Lc0LCgUAAAAAJrT80vN4EhTwokY8NkTiT1Ud017');

        if ($captcha) {
            $this->http->SetInputValue('g-recaptcha-response', $captcha);
        }

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);
        // Stash Rewards is currently unavailable due to maintenance
        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'currently unavailable due to maintenance')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // Syntax error in the application's code
        if ($message = $this->http->FindSingleNode('
                //div[contains(text(), "There may be a syntax error in the application\'s code")]
                | //h1[contains(text(), "We\'re sorry, but something went wrong.")]
            ')
        ) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // Service Unavailable
        if ($this->http->FindSingleNode("//h1[contains(text(), 'Service Unavailable')]")
            // Error 405 - Not Allowed
            || $this->http->FindSingleNode("//h1[contains(text(), 'Not Allowed')]")) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }
        //# Server error
        if ($this->http->currentUrl() == 'https://www.stashrewards.com/internal-server-error') {
            throw new CheckException("The website is currently unavailable. Please try again later.", ACCOUNT_PROVIDER_ERROR);
        } /*checked*/

        // proxy issues
        if (strstr($this->http->Error, 'Network error 56 - Received HTTP code 503 from proxy after CONNECT')) {
            throw new CheckRetryNeededException(3, 7);
        }

        $this->http->GetURL('https://www.stashrewards.com/');
        if ($this->http->FindSingleNode('//h1[contains(text(),"Our Site is Down")]')) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }


        return false;
    }

    public function GetRedirectParams($targetURL = null)
    {
        $arg = parent::GetRedirectParams($targetURL);
        $arg['NoCookieURL'] = true;
        $arg['SuccessURL'] = 'https://www.stashrewards.com/account/my-account';

        return $arg;
    }

    public function Login()
    {
        $this->http->RetryCount = 0;
        $headers = [
            'Content-Type' => 'application/x-www-form-urlencoded',
        ];

        if (!$this->http->PostForm($headers) && !in_array($this->http->Response['code'], [500])) {
            return $this->checkErrors();
        }
        $this->http->RetryCount = 2;
        // Access is allowed
        if ($this->loginSuccessful()) {
            return true;
        }
        // We don't recognize this email or password
        if ($message = $this->http->FindSingleNode('//div[contains(text(), "We don\'t recognize this email or password.")]')) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        // Incorrect email & password combo. Try again.
        if ($message = $this->http->FindSingleNode('//div[contains(text(), "Incorrect email & password combo. Try again.")]')) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        // Your password has expired. Please reset your password here
        if ($message = $this->http->FindSingleNode('//div[contains(text(), "Your password has expired.")]')) {
            throw new CheckException("Your password has expired.", ACCOUNT_INVALID_PASSWORD);
        }
        // A password has not yet been set for this account.
        if ($message = $this->http->FindSingleNode('
                //div[contains(text(), "A password has not yet been set for this account.")]
            ')
        ) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        // reCaptcha issue
        // Could not sign in. If you continue to see this contact member support.
        if ($message = $this->http->FindSingleNode('//div[contains(text(), "Could not sign in. If you continue to see this contact member support.")]')) {
            throw new CheckRetryNeededException(3, 10, $message);
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        // Balance - Stash Points
        $this->SetBalance($this->http->FindSingleNode("//span[contains(text(), 'Stash Points')]/preceding-sibling::span[1]", null, true, self::BALANCE_REGEXP));

        if ($this->http->currentUrl() != self::REWARDS_PAGE_URL) {
            $this->http->GetURL(self::REWARDS_PAGE_URL);
        }
        // Name
        $this->SetProperty("Name", beautifulName($this->http->FindSingleNode("//p[strong[contains(text(), 'Name:')]]/text()[last()]")));
        // Member Since
        $this->SetProperty("MemberSince", $this->http->FindSingleNode("//p[strong[contains(text(), 'Member since:')]]/text()[last()]", null, true, "/([\/\d]+)/ims"));
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);

        if ($this->http->FindNodes("//a[contains(@href, 'logout')]/@href")) {
            return true;
        }

        return false;
    }

    private function parseCaptcha($key = null)
    {
        $this->logger->notice(__METHOD__);

        if (empty($key)) {
            $key = $this->http->FindSingleNode("//div[contains(@class, 'g-recaptcha')]/@data-sitekey");
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

        return $this->recognizeByRuCaptcha($this->recognizer, $key, $parameters);
    }
}
