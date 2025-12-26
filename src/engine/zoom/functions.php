<?php

use AwardWallet\Engine\ProxyList;

class TAccountCheckerZoom extends TAccountChecker
{
    use ProxyList;
    use SeleniumCheckerHelper;

    private $headers = [
        'Accept'       => 'application/json, text/plain, */*',
        'Content-Type' => 'application/vnd-v4.0+json',
    ];

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->setProxyGoProxies();
    }

    public function IsLoggedIn()
    {
        if (!isset($this->State['X-GP-ACCESS-TOKEN'])) {
            return false;
        }

        if ($this->loginSuccessful($this->State['X-GP-ACCESS-TOKEN'])) {
            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();

        if (filter_var($this->AccountFields['Login'], FILTER_VALIDATE_EMAIL) === false) {
            throw new CheckException("ZoomBucks was recently acquired by Grab Rewards Ltd. As a result of the acquisition, all ZoomBucks accounts have been moved to GrabPoints.\rPlease update your login information.", ACCOUNT_PROVIDER_ERROR);
        }

        $this->http->GetURL("https://grabpoints.com/#/login");

        if ($this->http->Response['code'] !== 200) {
            return $this->checkErrors();
        }

        $this->selenium();

        $data = [
            'userName' => $this->AccountFields['Login'],
            'password' => $this->AccountFields['Pass'],
        ];
        $this->http->RetryCount = 0;
//        $this->http->PostURL('https://api.grabpoints.com/login', json_encode($data), $this->headers);

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);

        return false;
    }

    public function Login()
    {
        $json = $this->http->JsonLog();

        if (!empty($json->principal->accessToken)) {
            $this->State['X-GP-ACCESS-TOKEN'] = $json->principal->accessToken;

            return $this->loginSuccessful($json->principal->accessToken);
        }

        // wtf?
        /*
        if (isset($json->errType) && 'ACCESS_DENIED' == $json->errType && !empty($json->message)) {
            throw new CheckException($json->message, ACCOUNT_INVALID_PASSWORD);
        }
        */

        if ($this->http->findPreg('/bad credentials/')) {
            throw new CheckException('The password is incorrect.', ACCOUNT_INVALID_PASSWORD);
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        $json = $this->http->JsonLog(null, 0);

        if (!isset($json->points)) {
            // The account you are attempting to access has been closed.
            if ($this->http->findPreg('/User account is closed/')) {
                throw new CheckException('The account you are attempting to access has been closed.', ACCOUNT_INVALID_PASSWORD);
            }

            return;
        }
        // Balance - Points
        $this->SetBalance($json->points);
        // Name
        $this->SetProperty("Name", beautifulName(trim($json->firstName . ' ' . $json->lastName)));
        // User id
        $this->SetProperty('UserId', $json->id);
    }

    private function loginSuccessful($token)
    {
        $this->logger->notice(__METHOD__);
        $headers = [
            'X-GP-ACCESS-TOKEN' => $token,
        ];
        $this->http->RetryCount = 0;
        $this->http->GetURL('https://api.grabpoints.com/api/customer', $this->headers + $headers);
        $this->http->RetryCount = 2;
        $response = $this->http->JsonLog();
        $email = $response->email ?? null;

        if ($email && strtolower($email) === strtolower($this->AccountFields['Login'])) {
            return true;
        }

        return false;
    }

    private function selenium()
    {
        $this->logger->notice(__METHOD__);
        $selenium = clone $this;
        $retry = false;
        $this->http->brotherBrowser($selenium->http);
        $this->logger->notice("Running Selenium...");

        try {
            $selenium->UseSelenium();

            $selenium->seleniumOptions->recordRequests = true;
            $selenium->useFirefox();
            $selenium->http->start();
            $selenium->Start();
            $selenium->http->saveScreenshots = true;

            $selenium->http->GetURL("https://grabpoints.com/#/login");
            $login = $selenium->waitForElement(WebDriverBy::xpath("//input[@name = 'email']"), 7);
            $pass = $selenium->waitForElement(WebDriverBy::xpath("//input[@name = 'password']"), 0);
            $btn = $selenium->waitForElement(WebDriverBy::xpath('//button[contains(@class, "login-button")]'), 0);
            // save page to logs
            $this->saveToLogs($selenium);

            if (!$login || !$pass || !$btn) {
                $this->logger->error("something went wrong");

                return false;
            }

            $login->sendKeys($this->AccountFields['Login']);
            $pass->sendKeys($this->AccountFields['Pass']);

            $btn->click();

            $selenium->waitForElement(WebDriverBy::xpath('//div[contains(@class, "login-alert")]'), 10);

            $seleniumDriver = $selenium->http->driver;
            $requests = $seleniumDriver->browserCommunicator->getRecordedRequests();

            foreach ($requests as $n => $xhr) {
//                $this->logger->debug("xhr request {$n}: {$xhr->request->getVerb()} {$xhr->request->getUri()} " . json_encode($xhr->request->getHeaders()));
//                $this->logger->debug("xhr response {$n}: {$xhr->response->getStatus()} {$xhr->response->getHeaders()}");
                if (stristr($xhr->request->getUri(), 'https://api.grabpoints.com/login')) {
                    $this->logger->debug("xhr request {$n}: {$xhr->request->getVerb()} {$xhr->request->getUri()} " . json_encode($xhr->request->getHeaders()));
                    $this->logger->debug("xhr response {$n} body: " . json_encode($xhr->response->getBody()));
                    $responseData = json_encode($xhr->response->getBody());

                    break;
                }
            }

            $cookies = $selenium->driver->manage()->getCookies();

            foreach ($cookies as $cookie) {
                $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
            }

            $this->saveToLogs($selenium);

            if (!empty($responseData)) {
                $this->http->SetBody($responseData);
            }
        } catch (NoSuchDriverException | TimeOutException $e) {
            $this->logger->error("Exception: " . $e->getMessage(), ['HtmlEncode' => true]);
            $retry = true;
        } finally {
            // close Selenium browser
            $selenium->http->cleanup(); //todo

            // retries
            if ($retry && ConfigValue(CONFIG_SITE_STATE) != SITE_STATE_DEBUG) {
                throw new CheckRetryNeededException(2, 0);
            }
        }

        return true;
    }

    private function saveToLogs($selenium)
    {
        $this->logger->notice(__METHOD__);
        // save page to logs
        $selenium->http->SaveResponse();
        // save page to logs
        $this->http->SetBody($selenium->driver->executeScript('return document.documentElement.innerHTML'));
        $this->http->SaveResponse();
    }
}
