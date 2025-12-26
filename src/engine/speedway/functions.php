<?php

use AwardWallet\Engine\ProxyList;
use AwardWallet\Engine\Settings;

class TAccountCheckerSpeedway extends TAccountChecker
{
    use SeleniumCheckerHelper;
    use ProxyList;

    public const REWARDS_PAGE_URL = 'https://www.speedway.com/my-account/profile';

    private $selenium = false;

    public static function GetAccountChecker($accountInfo)
    {
        require_once __DIR__ . "/TAccountCheckerSpeedwaySelenium.php";

        return new TAccountCheckerSpeedwaySelenium();
    }

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->saveScreenshots = true;

        if ($this->attempt == 2) {
            $this->setProxyBrightData(null, 'static', 'us');
        } else {
            $this->http->SetProxy($this->proxyDOP(Settings::DATACENTERS_NORTH_AMERICA));
        }
    }

    public function IsLoggedIn()
    {
        $this->http->RetryCount = 0;
        $this->http->GetURL(self::REWARDS_PAGE_URL, [], 20);
        $this->http->RetryCount = 2;

        if ($this->loginSuccessful() && !strstr($this->http->currentUrl(), 'connect/authorize?')) {
            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        if (filter_var($this->AccountFields['Login'], FILTER_VALIDATE_EMAIL) === false) {
            throw new CheckException("Invalid email address.", ACCOUNT_INVALID_PASSWORD);
        }

        $this->http->removeCookies();
        $this->http->GetURL(self::REWARDS_PAGE_URL);

        if (!$this->http->ParseForm('loginForm') && $this->http->FindPreg("/src=\"\/_Incapsula_Resource\?SW[^\"]+\"/")) {
            $this->selenium = true;

            return $this->selenium();
        }

        if (!$this->http->ParseForm('loginForm')) {
            return $this->checkErrors();
        }
        $this->http->SetInputValue('Email', $this->AccountFields['Login']);
        $this->http->SetInputValue('Passcode', substr($this->AccountFields['Pass'], 0, 8));
        $this->http->SetInputValue('RememberLogin', "true");
        $this->http->SetInputValue('X-Requested-With', "XMLHttpRequest");

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);

        if ($message = $this->http->FindSingleNode('//span[@class="PageText" and contains(text(), "experiencing difficulties")]', null, false)) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        //# An error has occurred on the server
        if ($message = $this->http->FindPreg("/(An error has occurred on the server\.)/ims")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // Something's gone wrong on our end.
        if ($message = $this->http->FindPreg("/(Something\'s gone wrong on our end\.)/ims")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // Speedway.com Maintenance
        if ($message = $this->http->FindSingleNode("//title[contains(text(), 'Speedway.com Maintenance')]")) {
            throw new CheckException("Speedway.com is under maintenance. Please try to check again later.", ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
        $headers = [
            "X-Requested-With" => "XMLHttpRequest",
        ];

        if ($this->selenium == false) {
            if (!$this->http->PostForm($headers)) {
                return $this->checkErrors();
            }
        }

        //# Speedy Rewards card has been reported as lost/stolen
        if ($message = $this->http->FindPreg("/Your Speedy Rewards card # \d* has been reported as lost\/stolen/ims")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // Login services are currently down. Please try again later.
        if ($message = $this->http->FindPreg("/(Login services are currently down\.\s*Please try again later\.)/ims")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // PIN must be a 4-8 digit number.
        if (!is_numeric($this->AccountFields['Pass']) && !$this->http->FindSingleNode("//span[contains(@class, 'card_number')]", null, true, "/Card\s*#\s*([^<]+)/ims")) {
            throw new CheckException("PIN must be a 4-8 digit number.", ACCOUNT_INVALID_PASSWORD);
        }
        // Login failed. Please check your email and passcode and try again.
        if ($message = $this->http->FindSingleNode("//li[contains(text(), 'Login failed. Please check your email and passcode and try again.')]")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        // Your account has been locked out as a result of multiple failed login attempts.
        if ($message = $this->http->FindSingleNode("
                //li[contains(text(), 'Your account has been locked out as a result of multiple failed login attempts')]
                | //div[@id = 'panLoginError' and contains(text(), 'Your account has been locked out as a result of multiple failed login attempts.')]
            ")
        ) {
            throw new CheckException($message, ACCOUNT_LOCKOUT);
        }
        // hard code (error is empty on provider website)
        if (in_array($this->AccountFields['Login'], ['dbookbinder@gmail.com', 'liangjy10@gmail.com'])
            && strlen($this->AccountFields['Pass']) == 4
            && !$this->http->FindSingleNode("//span[contains(@class, 'card_number')]")) {
            throw new CheckException("PIN must be a 4-8 digit number.", ACCOUNT_INVALID_PASSWORD);
        }

        // hard code - error box does not contains any text (AccountID: 2318469)
        if ($this->AccountFields['Login'] == 'corey.bregman@yahoo.com'
            && !$this->http->FindSingleNode("//span[contains(@class, 'card_number')]")) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        if ($iframe = $this->http->FindSingleNode("//iframe[contains(@src, 'authorize')]/@src")) {
            $this->http->GetURL($iframe);

            if (!$this->http->ParseForm(null, "//form[input[@name = 'id_token']]")) {
                return false;
            }
            $this->http->PostForm($headers);

            // provider bug fix
            if ($this->http->currentUrl() == 'https://www.speedway.com/ErrorPages/Error') {
                $this->http->GetURL("https://www.speedway.com/MyAccount/Transactions");

                if (!$this->loginSuccessful()) {
                    $this->http->GetURL('https://www.speedway.com/login');
                    $this->http->GetURL(self::REWARDS_PAGE_URL);
                }
            }// if ($this->http->currentUrl() == 'https://www.speedway.com/ErrorPages/Error')
        }

        // login successful
        if ($this->loginSuccessful()) {
            return true;
        }

        return $this->checkErrors();
    }

    public function Parse($selenium = null)
    {
        // Replace Card
        // Your Speedy Rewards card #... has been disabled.
        // AccountID: 4044189
        if ($this->http->FindSingleNode("//div[@class = 'toast-message' and contains(text(), 'Your Speedy Rewards card #') and contains(text(), ' has been disabled.')]")) {
            // Balance - Points
            $this->SetBalance($this->http->FindSingleNode("//div[@class= 'header__account']//span[contains(@class, 'header__account-summary__points')]"));
            // Name
            $this->SetProperty("Name", beautifulName($this->http->FindSingleNode("//div[@class= 'header__account']//span[contains(@class, 'header__account-summary__name')]")));

            return;
        }

        // Card #
        $this->SetProperty("Number", $this->http->FindSingleNode("//li[contains(@class, 'account-summary__list__card')]", null, true, "/Card\s*#\s*([^<]+)/ims"));
        // Balance - Points
        $this->SetBalance($this->http->FindSingleNode("//li[contains(@class, 'account-summary__list__points')]"));
        // Name
        $this->SetProperty("Name", beautifulName($this->http->FindSingleNode('//span[contains(text(), "Name")]/following-sibling::span[1]')));

        // Expiration Date   // refs #4416
        if ($this->selenium == true) {
            $selenium->http->GetURL("https://www.speedway.com/MyAccount/Transactions");
            $selenium->waitForElement(WebDriverBy::xpath("//table[contains(@class, 'table-striped')]//tr[td]"), 5);
            $this->savePageToLogs($selenium);
        } else {
            $this->http->GetURL("https://www.speedway.com/MyAccount/Transactions");
        }
        $nodes = $this->http->XPath->query("//div[@id = 'transactionList']/ul/li[not(contains(@class, 'header'))]");
        $this->logger->debug("Total {$nodes->length} nodes found");

        for ($i = 0; $i < $nodes->length; $i++) {
            $date = $this->http->FindSingleNode('span[1]', $nodes->item($i));
            $points = $this->http->FindSingleNode('span[4]/text()', $nodes->item($i));

            if (($exp = strtotime($date)) && $points > 0) {
                $this->logger->debug("Node # " . $i);
                // Expiration Date
                $this->SetExpirationDate(strtotime("+9 month", $exp));
                // Last Activity
                $this->SetProperty("LastActivity", $date);

                break;
            }// if ($exp = strtotime($exp))
        }// for ($i = 0; $i < $nodes->length; $i++)
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);

        if ($this->http->FindNodes("//a[contains(@href, 'logout')]")) {
            return true;
        }

        return false;
    }

    private function selenium()
    {
        $this->logger->notice(__METHOD__);
        $selenium = clone $this;
        $this->http->brotherBrowser($selenium->http);
        $retry = false;

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
                [1920, 1080],
            ];
            $chosenResolution = $resolutions[array_rand($resolutions)];
            $this->logger->info('chosenResolution:');
            $this->logger->info(var_export($chosenResolution, true));
            $selenium->setScreenResolution($chosenResolution);

            $selenium->http->saveScreenshots = true;
            $selenium->useChromium(SeleniumFinderRequest::CHROMIUM_80);

            $selenium->http->start();
            $selenium->Start();
            $selenium->http->removeCookies();
            $selenium->http->GetURL(self::REWARDS_PAGE_URL);

            $login = $selenium->waitForElement(WebDriverBy::id("login-email"), 10);
            $pass = $selenium->waitForElement(WebDriverBy::id("login-passcode"), 0);

            if (empty($login) || empty($pass)) {
                $this->logger->error('something went wrong');
                $this->savePageToLogs($selenium);

                // incapsula workaround
                if ($this->http->FindSingleNode("//iframe[contains(@src, '/_Incapsula_Resource?')]/@src")) {
                    throw new CheckRetryNeededException(3, 3);
                }

                return $this->checkErrors();
            }

            $login->clear();
            $login->sendKeys($this->AccountFields['Login']);
            $pass->clear();
            $pass->sendKeys($this->AccountFields['Pass']);

            $signIn = $selenium->waitForElement(WebDriverBy::xpath("//button[contains(text(), 'Login')]"), 0);

            if (empty($signIn)) {
                $this->logger->error('btn not found');
                $this->savePageToLogs($selenium);

                return $this->checkErrors();
            }
            $signIn->click();

            $status = $selenium->waitForElement(WebDriverBy::xpath("//li[contains(@class, 'account-summary__list__card')] | //span[contains(@class, 'header__account-summary__points')] | //div[@id = 'validationSummary']/ul/li | //div[contains(text(), 'Your Speedy Rewards card #') and contains(text(), ' has been disabled.')] | //iframe[contains(@src, '/_Incapsula_Resource?')]"), 30);
            $this->savePageToLogs($selenium);

            if ($status && $this->http->FindPreg('/(The server could not process your request\.|An unexpected error has occurred\. Please check your internet connection and try again\.)/', false, $status->getText())) {
                throw new CheckRetryNeededException(3, 3);
            }

            $cookies = $selenium->driver->manage()->getCookies();

            foreach ($cookies as $cookie) {
                $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
            }

            if ($this->loginSuccessful()) {
                $this->logger->debug("[Current URL]: {$selenium->http->currentUrl()}");

                if ($selenium->http->currentUrl() == "https://www.speedway.com/") {
                    $selenium->http->GetURL(self::REWARDS_PAGE_URL);
                    $selenium->waitForElement(WebDriverBy::xpath("//li[contains(@class, 'account-summary__list__card')] | //div[contains(text(), 'Your Speedy Rewards card #') and contains(text(), ' has been disabled.')]"), 20);
                    $this->savePageToLogs($selenium);
                }

                $this->Parse($selenium);

                return false;
            }

            if (
                $selenium->waitForElement(WebDriverBy::xpath("//div[@id = 'loading']"), 0)
                || $this->http->FindSingleNode("//iframe[contains(@src, '/_Incapsula_Resource?')]/@src")
            ) {
                throw new CheckRetryNeededException(4, 0);
            }

            return true;
        } catch (StaleElementReferenceException | TimeOutException $e) {
            $this->logger->error("StaleElementReferenceException exception: " . $e->getMessage());
            $retry = true;
        } catch (UnexpectedJavascriptException $e) {
            $this->logger->error("UnexpectedJavascriptException exception: " . $e->getMessage());

            if ($this->attempt != 1) {
                $retry = true;
            }
        } catch (WebDriverCurlException $e) {
            $this->logger->error("WebDriverCurlException exception: " . $e->getMessage());

            if ($this->attempt != 1) {
                $retry = true;
            }
        } finally {
            // close Selenium browser
            $selenium->http->cleanup();

            if ($retry && ConfigValue(CONFIG_SITE_STATE) != SITE_STATE_DEBUG) {
                throw new CheckRetryNeededException(3, 10);
            }
        }

        return null;
    }
}
