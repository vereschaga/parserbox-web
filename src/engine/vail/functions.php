<?php

// refs #15880

use AwardWallet\Common\Selenium\FingerprintFactory;
use AwardWallet\Common\Selenium\FingerprintRequest;

class TAccountCheckerVail extends TAccountChecker
{
    use SeleniumCheckerHelper;

    private const REWARDS_PAGE_URL = 'https://www.snow.com/account/my-account.aspx';

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
    }

    public function IsLoggedIn()
    {
        $this->http->RetryCount = 0;
        $this->http->GetURL(self::REWARDS_PAGE_URL, [], 20);
        $this->http->RetryCount = 0;

        if ($this->loginSuccessful() && !strstr($this->http->currentUrl(), '/login-page.aspx?url=')) {
            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();

        if (stripos($this->http->currentUrl(), 'https://waitingroom.snow.com/?c=') === false) {
            $this->http->GetURL('https://www.snow.com/account/login-page.aspx');
        }
        $url = $this->http->FindPreg("/document\.location\.href\s?=\s?decodeURIComponent\('(.+?)'/") ?? "https://waitingroom.snow.com/?c=vailresorts&e=vailresortsecomm1&t=https://www.snow.com/account/login-page.aspx";

        if (!$url) {
            throw new CheckRetryNeededException(1);
        }
        $url = rawurldecode($url);
        $this->http->NormalizeURL($url);
        $this->http->GetURL($url);

        if ($location = $this->http->FindPreg("/document.location.href = '([^\']+)/")) {
            $this->http->NormalizeURL($location);
            $this->http->GetURL($location);
        }

        $token = $this->http->FindSingleNode("//input[@id = 'csrfToken']/@value");

        if (!$this->http->ParseForm('returningCustomerForm_3') || !isset($token)) {
            return $this->checkErrors();
        }

        $this->seleniumAuth();

        return true;

        $data = [
            "UserName"    => $this->AccountFields['Login'],
            "Password"    => $this->AccountFields['Pass'],
            "IsLoginPage" => true,
            "UrlReferrer" => null,
        ];
        $headers = [
            'Content-Type'               => 'application/json; charset=UTF-8',
            '__RequestVerificationToken' => $token,
        ];
        $this->http->RetryCount = 0;
        $this->http->PostURL("https://www.snow.com/api/AccountApi/ExistingAccountLogin", json_encode($data), $headers);
        $this->http->RetryCount = 2;

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);
        // This site is currently unavailable, by design.
        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'This site is currently unavailable, by design.')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // Our site is currently experiencing technical difficulties.
        if ($message = $this->http->FindPreg("/(Our site is currently experiencing technical difficulties\.)/ims")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // Internal Server Error
        if ($message = $this->http->FindSingleNode("//h1[contains(text(), 'Internal Server Error')]")) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }
        // We are currently undergoing scheduled maintenance.
        if ($message = $this->http->FindSingleNode("//span[contains(text(), 'currently undergoing scheduled maintenance.')]")) {
            throw new CheckException("We are currently undergoing scheduled maintenance.", ACCOUNT_PROVIDER_ERROR);
        }
        // The server is temporarily unable to service your request. Please try again later.
        if ($message = $this->http->FindPreg("/The server is temporarily unable to service your request\.\s*Please try again\s*later\./")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
        $response = $this->http->JsonLog();
        // "{\"Response\":\"https://www.snow.com/account/my-account.aspx\"}"
        if ($this->http->FindPreg('/"Response/')) {
            $this->http->GetURL(self::REWARDS_PAGE_URL);
        }

        if ($this->loginSuccessful()) {
            return true;
        }

        $message =
            $response->Message
            ?? $this->http->FindSingleNode("//div[@data-component-element = 'accountLogin__errorMessage']/p")
        ;
        $this->logger->error("[Error]: {$message}");
        // The Email Address and Password information entered does not match our records. Please try again.
        if (isset($message) && $this->http->FindPreg('/information entered does not match our records/', false, $message)) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        // The User ID has been locked due to excessive failed login attempts. Please call 1-800-842-8062 for immediate assistance or try again later.
        if (isset($message) && $this->http->FindPreg('/has been locked due to excessive/', false, $message)) {
            throw new CheckException($message, ACCOUNT_LOCKOUT);
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        if ($this->http->currentUrl() != self::REWARDS_PAGE_URL) {
            $this->http->GetURL(self::REWARDS_PAGE_URL);
        }

        // Name
        /*
        $accountData = $this->http->FindPreg('/\.MyAccountData\s*=\s*(.+?);\s*\}\);/');
        $response = $this->http->JsonLog($accountData, 0);
        $this->SetProperty('Name', $response->User->FullName);
        */
        // "FullName":"..."},"RelatedCustomers":
        $this->SetProperty('Name', $this->http->FindPreg("/\"FullName\":\"([^\"]+)\"},\"RelatedCustomers\":/"));

        // Total household points
        $this->SetBalance($this->http->FindSingleNode("//p[contains(@class,'peaks_rewards__heading__point_total')]", null, false, '/points:\s*([\d,.]+)/'));

        if ($this->http->FindSingleNode("//a[@id = 'bdyTabbedElement_OrderHistory']") && !empty($this->Properties['Name']) && !isset($this->Balance)) {
            $this->SetBalanceNA();
        }

        // Sub Accounts - Active/Available Certificates
        $nodes = $this->http->XPath->query("//table[contains(@class, 'peaks_rewards__content__history_table')]//tr[contains(@class,'tablecontent')]");
        $this->logger->debug("Total {$nodes->length} certificates were found");

        foreach ($nodes as $node) {
            $expDate = $this->http->FindSingleNode('td[3]', $node);
            $certificate = $this->http->FindSingleNode('td[2]', $node);
            $this->AddSubAccount([
                'Code'           => 'vailCertificate' . $certificate,
                'DisplayName'    => 'Certificate ' . $certificate,
                'Balance'        => null,
                'IssueDate'      => $this->http->FindSingleNode('td[1]', $node),
                'ExpirationDate' => strtotime($expDate, false),
            ], true);
        }
        $this->SetProperty('CombineSubAccounts', false);
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);
        // Access is allowed
        if ($this->http->FindSingleNode("//a[contains(text(), 'Sign Out')]/@href")) {
            return true;
        }

        return false;
    }

    private function seleniumAuth()
    {
        $this->logger->notice(__METHOD__);
        $retry = false;
        $selenium = clone $this;
        $this->http->brotherBrowser($selenium->http);

        try {
            $this->logger->notice("Running Selenium...");
            $selenium->UseSelenium();
            $selenium->useChromium();

            $request = FingerprintRequest::chrome();
            $request->browserVersionMin = \HttpBrowser::BROWSER_VERSION_MIN;
            $fingerprint = $this->services->get(FingerprintFactory::class)->getOne([$request]);

            if ($fingerprint !== null) {
                $selenium->seleniumOptions->fingerprint = $fingerprint->getFingerprint();
                $selenium->http->setUserAgent($fingerprint->getUseragent());
                $selenium->seleniumOptions->setResolution([$fingerprint->getScreenWidth(), $fingerprint->getScreenHeight()]);
            }

            $selenium->http->saveScreenshots = true;

            $selenium->http->start();
            $selenium->Start();

            $selenium->http->GetURL('https://www.snow.com/account/login-page.aspx?url=%2faccount%2fmy-account.aspx');

            $form = '//div[@id = "accountSignIn"]';
            $loginInput = $selenium->waitForElement(WebDriverBy::xpath("{$form}//input[@name = 'UserName']"), 10);
            $passwordInput = $selenium->waitForElement(WebDriverBy::xpath("{$form}//input[@name = 'Password']"), 0);
            $button = $selenium->waitForElement(WebDriverBy::xpath("{$form}//button[contains(text(), 'Sign In')]"), 10);
            $this->savePageToLogs($selenium);

            if (!$loginInput || !$passwordInput || !$button) {
                $this->logger->error("something went wrong");

                return $this->checkErrors();
            }

            $loginInput->click();
            $loginInput->sendKeys($this->AccountFields['Login']);
            $passwordInput->click();
            $passwordInput->sendKeys($this->AccountFields['Pass']);

            $this->savePageToLogs($selenium);
            $button->click();

            // Access Denied
            sleep(3);
            $timeLimit = 10;
            $selenium->waitForElement(WebDriverBy::xpath("//a[contains(text(), 'Sign Out')] | //div[@data-component-element = 'accountLogin__errorMessage']/p"), $timeLimit);
            // save page to logs
            $this->savePageToLogs($selenium);

            $cookies = $selenium->driver->manage()->getCookies();

            foreach ($cookies as $cookie) {
                $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
            }

            $this->seleniumURL = $selenium->http->currentUrl();
            $this->logger->debug("[Selenium URL]: {$this->seleniumURL}");
        } finally {
            // close Selenium browser
            $selenium->http->cleanup(); // todo

            $this->logger->debug("[retry]: {$retry}");

            if ($retry && ConfigValue(CONFIG_SITE_STATE) != SITE_STATE_DEBUG) {
                $this->logger->debug("[attempt]: {$this->attempt}");

                throw new CheckRetryNeededException(3, 0);
            }
        }

        return true;
    }
}
