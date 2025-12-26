<?php

// refs #1761

use AwardWallet\Engine\ProxyList;

class TAccountCheckerInboxdollars extends TAccountChecker
{
    use ProxyList;
    use SeleniumCheckerHelper;
    /**
     * @var CaptchaRecognizer
     */
    private $recognizer;

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
//        $this->http->SetProxy($this->proxyReCaptcha());
        // $this->http->SetProxy($this->proxyDOP(\AwardWallet\Engine\Settings::DATACENTERS_NORTH_AMERICA));
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
        $this->http->removeCookies();
        $response = $this->getToken();

        if (!isset($response->authToken->name)) {
            return $this->checkErrors();
        }

        $this->http->GetURL('https://www.inboxdollars.com/login');

        if (!$this->http->FindSingleNode('//title[contains(text(), "InboxDollars")]')) {
            return $this->checkErrors();
        }

        $data = [
            'ioBlackBox'               => '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',
            'g-recaptcha-response'     => '03AGdBq24xRx6_d5jEBTE46IF6jXAgvsIIaOCJFmbHeTBjlW9zjxyRfkHRVGGm0VG6hOwTGEr7DV8-9HRsE_yizaBA9rYQ2bhwTkYFl7TVChdhBtTa9g2h5vNc3BAKxqZGpWRBBClFkv000ARhyTgpaRwS-jHOhU159EBgl5VtBOGqEZPrD4t6CrB8hsOpEo64PzdoaCLtWJMqgnXxFereH5Wpnlr4tssaDB5ZNzHnMzizmKC_UIScKN_MME18mDajAZ9ZywGd57fYBgjVXFSWYH32JsDNU1hBaVgUGCqTWzP1V9exbh6RfdJeJIqkE_lSVedu-Jm3iJNAAezITX49UUvSs18TC_CEAjD43ybmmt_QOyqEO_qxnP2U-UR6qe6qndkgAErS-MMhkIW2UHw3H1nI1xOFAdK91g',
            'email'                    => $this->AccountFields['Login'],
            'password'                 => $this->AccountFields['Pass'],
            $response->authToken->name => $response->authToken->value,
        ];

        $this->http->PostURL('https://api.inboxdollars.com/secure/login', $data);

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);

        return false;
    }

    public function Login()
    {
        /*
        $this->http->RetryCount = 0;

        if (!$this->http->PostForm()) {
            return $this->checkErrors();
        }

        $this->http->RetryCount = 2;
        */

        $response = $this->http->JsonLog();
        $success = $response->success ?? null;

        if ($success && $this->loginSuccessful()) {
            $this->captchaReporting($this->recognizer);

            return true;
        }

        $errors = $response->errors ?? null;

        // Invalid captcha, invalid credentials...
        if ($success == false && isset($errors->login[0]) && $errors->login[0] = 'Login_IsValid') {
            $this->captchaReporting($this->recognizer);

            throw new CheckException("That email and password combination does not match our records. Please double-check and try again.", ACCOUNT_INVALID_PASSWORD);
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        $response = $this->http->JsonLog(null, 0);
        // Balance
        $this->SetBalance($response->member->pts / 100);
        // $0.00 PENDING
        $this->SetProperty('PtsPending', "$" . $response->member->ptsPending / 100);

        $this->http->PostURL('https://api.inboxdollars.com/?cmd=mp-ac-jx-get-settingsuserdata', [
            'RefererUrl' => '',
            '_ajax'      => '1',
        ], [
            'Accept'       => '*/*',
            'Content-Type' => 'application/x-www-form-urlencoded; charset=UTF-8',
        ]);
        $response = $this->http->JsonLog();

        // Name
        $firstName = $response->firstName ?? '';
        $lastName = $response->lastName ?? '';
        $this->SetProperty('Name', beautifulName($firstName . " " . $lastName));
    }

    protected function parseCaptcha()
    {
        $this->logger->notice(__METHOD__);
        $key = '6Ld48JYUAAAAAGBYDutKlRp2ggwiDzfl1iApfaxE';
        $this->logger->debug("data-sitekey: {$key}");

        if (!$key) {
            return false;
        }
        $this->recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_RUCAPTCHA);
        $this->recognizer->RecognizeTimeout = 120;
        $parameters = [
            "pageurl"      => $this->http->currentUrl(),
            "proxy"        => $this->http->GetProxy(),
            'isInvisible'  => true,
            'isEnterprise' => true,
        ];

        return $this->recognizeByRuCaptcha($this->recognizer, $key, $parameters);
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);
        $response = $this->getToken();
        $loggedIn = $response->member->loggedIn ?? null;
        $emailAddress = $response->member->emailAddress ?? null;

        if ($loggedIn
            && (
                strtolower($emailAddress) === strtolower($this->AccountFields['Login'])
                || filter_var($this->AccountFields['Login'], FILTER_VALIDATE_EMAIL) === false
            )
        ) {
            return true;
        }

        return false;
    }

    private function getToken()
    {
        $this->http->PostURL('https://api.inboxdollars.com/?cmd=mp-gn-member-status', [
            'RefererUrl' => '',
            '_ajax'      => '1',
        ], [
            'Accept'       => '*/*',
            'Content-Type' => 'application/x-www-form-urlencoded; charset=UTF-8',
        ]);

        return $this->http->JsonLog();
    }

    private function selenium()
    {
        $this->logger->notice(__METHOD__);
        $currentUrl = null;
        $selenium = clone $this;
        $this->http->brotherBrowser($selenium->http);

        try {
            $this->logger->notice("Running Selenium...");
            $selenium->UseSelenium();
            $resolutions = [
                [1152, 864],
                [1280, 720],
                [1280, 768],
                [1280, 800],
                [1360, 768],
                [1366, 768],
            ];
            $resolution = $resolutions[array_rand($resolutions)];
            $selenium->setScreenResolution($resolution);

            $selenium->useGoogleChrome(SeleniumFinderRequest::CHROME_99);

            $selenium->usePacFile(false);

            $selenium->http->start();
            $selenium->http->saveScreenshots = true;
            $selenium->Start();
            $selenium->driver->manage()->window()->maximize();

            $selenium->http->GetURL('https://www.inboxdollars.com/login');
            $button = $selenium->waitForElement(WebDriverBy::xpath('//span[contains(text(), "Continue with Email")]/..'), 10);
            $this->savePageToLogs($selenium);

            if ($button) {
                $button->click();
            }

            $selenium->waitForElement(WebDriverBy::xpath('//form[@id="signInForm"]'), 10);
            $this->savePageToLogs($selenium);

            $cookies = $selenium->driver->manage()->getCookies();

            foreach ($cookies as $cookie) {
                $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
            }
            $currentUrl = $selenium->http->currentUrl();
            $this->logger->debug("[Current URL]: {$currentUrl}");
        } finally {
            // close Selenium browser
            $selenium->http->cleanup();
        }

        return true;
    }
}
