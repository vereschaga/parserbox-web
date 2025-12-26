<?php

use AwardWallet\Engine\ProxyList;

class TAccountCheckerMaverik extends TAccountChecker
{
    use SeleniumCheckerHelper;
    use ProxyList;

    private $headers = [
        "Content-Type" => "application/json",
        "Accept"       => "application/json, text/plain, */*",
        "APP-ID"       => "PAYX",
    ];

    private $responseData = null;
    private $responseUserData = null;
    private $token = null;

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->GetURL("https://loyalty.maverik.com/login");

        if ($this->http->Response['code'] != 200 && !$this->http->FindSingleNode('//title[contains(text(), "Just a moment...")]')) {
            return $this->checkErrors();
        }
        //		$this->http->SetInputValue('session[login]', $this->AccountFields['Login']);
        //		$this->http->SetInputValue('session[password]', $this->AccountFields['Pass']);

        return $this->selenium();

        $data = [
            "username" => $this->AccountFields['Login'],
            "password" => $this->AccountFields['Pass'],
        ];
        $this->http->RetryCount = 0;
        $this->http->PostURL("https://gateway.maverik.com/api/oauth/requestToken", json_encode($data), $this->headers);
        $this->http->RetryCount = 2;

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);
        // Service Temporarily Unavailable
        if ($message = $this->http->FindSingleNode("//h1[contains(text(), 'Service Temporarily Unavailable')]")) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
        if (!empty($this->responseData) && $this->responseData != 'null') {
            return true;
        }

        $message = $this->http->FindSingleNode('//div[@role = "alertdialog"]//p[2]');

        if ($message) {
            $this->logger->error("[Error]: {$message}");

            if (strstr($message, 'Check your username and password, make sure you are connected and try again.')) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            return false;
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
//        $this->http->GetURL("https://gateway.maverik.com/ac-acct/userInfo", $this->headers);
        $response = $this->http->JsonLog($this->responseUserData) ?? null;

        // Name
        $this->SetProperty("Name", beautifulName($response->firstName . " " . $response->lastName));

//        $this->http->GetURL("https://gateway.maverik.com/ac-acct/account/{$accountId}", $this->headers);
        $response = $this->http->JsonLog($this->responseData) ?? null;
        // Card #
        $this->SetProperty("Number", $response->primaryCard->printedCardNumber ?? null);
        // Tier
        $this->SetProperty("Tier", $response->tier->name ?? null);
        // Fuel Savings
        $this->SetProperty("FuelSavings", "$" . $response->tier->fuelSavings ?? null);
        // Balance - Trail Points Balance
        $this->SetBalance($response->trailPoints->balance ?? null);

        $expirations = $response->trailPoints->expirations ?? [];

        foreach ($expirations as $expiration) {
            $expBalance = $expiration->amount;
            $expDate = $expiration->expirationDate;

            if (!isset($exp) || $exp > strtotime($expDate)) {
                $exp = strtotime($expDate);
                $this->SetProperty("ExpiringBalance", $expBalance);
                $this->SetExpirationDate($exp);
            }// if (!isset($exp) || $exp < strtotime($expDate))
        }// foreach ($expirations as $expiration)

        unset($expirations);
        unset($expiration);
        unset($exp);

        // my Stuff
        $myStuffs = $response->myStuff ?? [];
        $this->SetProperty("CombineSubAccounts", false);

        foreach ($myStuffs as $myStuff) {
            $balance = $myStuff->balance;
            $displayName = $myStuff->name;

            $subAccount = [
                'Code'           => "maverikStuff" . md5($displayName),
                'DisplayName'    => $displayName,
                'Balance'        => $balance,
            ];

            $expirations = $myStuff->expirations ?? [];
            unset($exp);

            foreach ($expirations as $expiration) {
                $expBalance = $expiration->amount;
                $expDate = $expiration->expirationDate;

                if (!isset($exp) || $exp > strtotime($expDate)) {
                    $exp = strtotime($expDate);
                    $subAccount['ExpirationDate'] = $exp;
                    $subAccount['ExpiringBalance'] = $expBalance;
                }// if (!isset($exp) || $exp < strtotime($expDate))
            }// foreach ($expirations as $expiration)

            $this->AddSubAccount($subAccount);
        }// foreach ($myStuffs as $myStuff)
    }

    public function selenium()
    {
        $this->logger->notice(__METHOD__);
        $currentUrl = null;
        $selenium = clone $this;
        $this->http->brotherBrowser($selenium->http);

        try {
            $this->logger->notice("Running Selenium...");
            $selenium->UseSelenium();
            $selenium->seleniumOptions->recordRequests = true;
            $selenium->setProxyGoProxies();
            $selenium->useGoogleChrome();
            $selenium->seleniumOptions->userAgent = null;
//            $selenium->setKeepProfile(true);
//            $selenium->disableImages();
            $selenium->usePacFile(false);
            $selenium->http->start();
            $selenium->http->saveScreenshots = true;
            $selenium->Start();

            $selenium->http->GetURL('https://loyalty.maverik.com/adventure/club/home/my-stuff');
            $signIn = $selenium->waitForElement(WebDriverBy::xpath('//a[@label = "Sign In"]'), 5);
            $this->savePageToLogs($selenium);

            if (!$signIn) {
                if ($message = $this->http->FindSingleNode("//div[contains(text(), 'Unable to connect to maverik.com. Please try again later.')]")) {
                    throw new CheckRetryNeededException(3, 0);
                }

                return false;
            }

            if ($okBtn = $selenium->waitForElement(WebDriverBy::xpath('//button[contains(., "OK")]'), 0)) {
                $okBtn->click();
            }

            $signIn->click();

            $login = $selenium->waitForElement(WebDriverBy::xpath('//input[@id = "main-content"]'), 5);
            $this->savePageToLogs($selenium);

            if ($login) {
                $login->sendKeys($this->AccountFields['Login']);
                sleep(1);
                $contBtn = $selenium->waitForElement(WebDriverBy::xpath('//button[@label = "Continue"]'), 0);
                $this->savePageToLogs($selenium);

                if (!$contBtn) {
                    return false;
                }

                $contBtn->click();

                $pass = $selenium->waitForElement(WebDriverBy::xpath('//input[@id = "main-content" and @type="password"]'), 10);
                $this->savePageToLogs($selenium);

                if (!$pass) {
                    return false;
                }

                $pass->sendKeys($this->AccountFields['Pass']);
                sleep(1);
                $loginBtn = $selenium->waitForElement(WebDriverBy::xpath('//button[@label = "Login" or contains(., "LOGIN")]'), 0);
                $this->savePageToLogs($selenium);

                if (!$loginBtn) {
                    return false;
                }

                $loginBtn->click();

                sleep(3);

                $selenium->waitForElement(WebDriverBy::xpath('
                    //a[@class = "balance-title"]
                    | //div[@role = "alertdialog"]
                '), 7);
                sleep(3);
                $this->savePageToLogs($selenium);
            }

            $cookies = $selenium->driver->manage()->getCookies();

            foreach ($cookies as $cookie) {
                $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
            }
            $requests = $selenium->http->driver->browserCommunicator->getRecordedRequests();

            foreach ($requests as $xhr) {
                $url = $xhr->request->getUri();

                if (str_contains($url, 'gateway.maverik.com/ac-acct/account/refresh/')) {
                    $this->responseData = json_encode($xhr->response->getBody());
                    $this->logger->debug("XHR request $url\r\n" . htmlspecialchars($this->responseData));
                }

                if (str_contains($url, 'gateway.maverik.com/ac-acct/userInfo')) {
                    $this->responseUserData = json_encode($xhr->response->getBody());
                    $this->logger->debug("XHR request $url\r\n" . htmlspecialchars($this->responseUserData));
                }

                if (isset($this->responseData, $this->responseUserData)) {
                    break;
                }
            }

            if (!isset($this->responseData, $this->responseUserData)) {
                $this->logger->error('Not all XHR requests parsed correctly!');
            }
            $currentUrl = $selenium->http->currentUrl();
            $this->logger->debug("[Current URL]: {$currentUrl}");
        } finally {
            // close Selenium browser
            $selenium->http->cleanup();
        }

        return $currentUrl;
    }
}
