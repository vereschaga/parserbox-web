<?php

use AwardWallet\Engine\ProxyList;

require_once __DIR__ . '/../california/functions.php';

class TAccountCheckerHuhot extends TAccountCheckerCalifornia
{
    use SeleniumCheckerHelper;
    /*
     * like as papaginos, burgerville, gordonb, genghisgrill, california, carinos, cosi, oberweis, restaurants, tortilla
     *
     * like as huhot, canes, whichwich, boloco
     *
     * like as freebirds, maxermas  // refs #16823
     */

    public $loginURL = "https://huhot.myguestaccount.com/guest/nologin/account-balance";
    public $balanceURL = "https://huhot.myguestaccount.com/guest/nologin/account-balance";
    public $code = "huhot";

    public function seleniumAuth()
    {
        $this->logger->notice(__METHOD__);

        $selenium = clone $this;
        $retry = false;
        $this->http->brotherBrowser($selenium->http);

        try {
            $this->logger->notice("Running Selenium...");
            $selenium->UseSelenium();

            $selenium->useFirefox(SeleniumFinderRequest::FIREFOX_84);
            $selenium->setProxyGoProxies();
            $selenium->setKeepProfile(true);
            $selenium->seleniumOptions->fingerprintOptions = ['devices' => ['desktop'], 'operatingSystems' => ['macos']];
            $selenium->seleniumOptions->addHideSeleniumExtension = false;
            $selenium->seleniumOptions->userAgent = null;

//            $selenium->disableImages();
//            $selenium->useCache();
            $selenium->http->start();
            $selenium->Start();
            $selenium->http->saveScreenshots = true;

            $selenium->http->removeCookies();
            $selenium->http->GetURL($this->balanceURL);

            $formXpath = "//form[contains(@action, '/guest/nologin/account-balance')]";

            $selenium->waitForElement(WebDriverBy::xpath("//div[contains(@class, \"cf-turnstile-wrapper\")] | //div[contains(@style, \"margin: 0px; padding: 0px;\")] | //input[@value = 'Verify you are human'] | //div[@id = 'turnstile-wrapper']//iframe | " . $formXpath . "//input[@id = 'inputUsername']"), 50);
            $this->savePageToLogs($selenium);

            if ($this->clickCloudFlareCheckboxByMouse($selenium)) {
                $selenium->waitForElement(WebDriverBy::xpath($formXpath . "//input[@id = 'inputUsername']"), 15);
                $this->savePageToLogs($selenium);
            }

            $loginInput = $selenium->waitForElement(WebDriverBy::xpath($formXpath . "//input[@id = 'printedCard']"), 0);
            $button = $selenium->waitForElement(WebDriverBy::xpath($formXpath . "//button[contains(text(), 'Submit')]"), 0);
            $this->savePageToLogs($selenium);

            if (!$loginInput || !$button) {
                return false;
            }

            $captcha = $this->parseCaptcha();

            if ($captcha === false) {
                return false;
            }

            $selenium->driver->executeScript("$('div.g-recaptcha iframe').remove();");
            $selenium->driver->executeScript("$('#g-recaptcha-response').val(\"" . $captcha . "\");");

            $loginInput->sendKeys($this->AccountFields['Login']);

            $this->savePageToLogs($selenium);
            $this->logger->debug("click by btn");
            $button->click();

            $selenium->waitForElement(WebDriverBy::xpath("//a[contains(@href, 'logout')] | //span[@code = 'cardNumberAppend'] | //h1[contains(text(),'Migrating Your Account')] | //span[contains(@class, 'error')] | //input[@id = 'registrationCode'] | //div[div[strong[contains(text(), 'Your points balance is')]]]/following-sibling::div[1]//div[@class = 'row']"), 10);
            $this->savePageToLogs($selenium);

            if ($this->clickCloudFlareCheckboxByMouse($selenium)) {
                $selenium->waitForElement(WebDriverBy::xpath("//a[contains(@href, 'logout')] | //span[@code = 'cardNumberAppend'] | //h1[contains(text(),'Migrating Your Account')] | //span[contains(@class, 'error')] | //input[@id = 'registrationCode'] | //div[div[strong[contains(text(), 'Your points balance is')]]]/following-sibling::div[1]//div[@class = 'row']"), 10);
                $this->savePageToLogs($selenium);
            }

            // For security reasons, this account requires a registration code.
            if ($this->parseQuestion()) {
                return false;
            }

            $cookies = $selenium->driver->manage()->getCookies();

            foreach ($cookies as $cookie) {
                $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
            }

            $this->savePageToLogs($selenium);
            $this->logger->debug("[Current URL]: {$selenium->http->currentUrl()}");
        } catch (Facebook\WebDriver\Exception\UnknownErrorException | WebDriverCurlException | NoSuchDriverException $e) {
            $this->logger->error("Exception: " . $e->getMessage());
            $retry = true;
        } catch (ScriptTimeoutException $e) {
            $this->logger->error("ScriptTimeoutException exception: " . $e->getMessage());
            $this->DebugInfo = "ScriptTimeoutException";
            // retries
            if (strstr($e->getMessage(), 'timeout: Timed out receiving message from renderer')) {
                $retry = true;
            }
        }// catch (ScriptTimeoutException $e)
        finally {
            // close Selenium browser
            $selenium->http->cleanup();
            // retries
            if ($retry && ConfigValue(CONFIG_SITE_STATE) != SITE_STATE_DEBUG) {
                throw new CheckRetryNeededException(3, 0);
            }
        }

        return true;
    }

    public function parseQuestion()
    {
        $this->logger->notice(__METHOD__);
        $question = $this->http->FindSingleNode("//div[contains(text(), 'For security reasons, this account requires a registration code')]");

        if (!isset($question) || !$this->http->ParseForm(null, "//form[contains(@action, '/guest/nologin/account-balance')]")) {
            return false;
        }
        $this->Question = $question;
        $this->ErrorCode = ACCOUNT_QUESTION;
        $this->Step = "Question";

        return true;
    }

    public function ProcessStep($step)
    {
        $this->http->SetInputValue($this->http->FindSingleNode("//input[@id = 'registrationCode']/@name"), $this->Answers[$this->Question]);
        $this->http->SetInputValue($this->http->FindSingleNode("//button[contains(@class, 'nologinRegCodeSubmitButton')]/@name"), "");

        if (!$this->http->PostForm()) {
            return false;
        }
        // The registration code entered was incorrect
        if ($message = $this->http->FindSingleNode("//span[contains(text(), 'The registration code entered was incorrect')]")) {
            $this->AskQuestion($this->Question, $message);

            return false;
        }// if ($message = $this->http->FindSingleNode("//span[contains(text(), 'The registration code entered was incorrect')]"))

        return true;
    }

    protected function parseCaptcha()
    {
        $this->logger->notice(__METHOD__);
        $key =
            $this->http->FindSingleNode("//form[contains(@action, '/guest/nologin/account-balance')]//div[@class = 'g-recaptcha']/@data-sitekey")
            ?? $key = $this->http->FindPreg("/'sitekey':\s*'(\w+)'/");
        $this->logger->debug("data-sitekey: {$key}");

        if (!$key) {
            return false;
        }
        $this->recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_RUCAPTCHA);
        $this->recognizer->RecognizeTimeout = 120;
        $parameters = [
            "pageurl" => $this->http->currentUrl() ?? 'https://huhot.myguestaccount.com/guest/nologin/account-balance',
            "proxy"   => $this->http->GetProxy(),
        ];

        return $this->recognizeByRuCaptcha($this->recognizer, $key, $parameters);
    }
}

class TAccountCheckerHuhotOld extends TAccountChecker
{
    use ProxyList;

    private const REWARDS_PAGE_URL = "https://huhot.myguestaccount.com/guest/nologin/account-balance";
    /**
     * @var CaptchaRecognizer
     */
    private $recognizer;

    /**
     * like as huhot, canes, whichwich, boloco.
     */
    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->SetProxy($this->proxyReCaptcha());
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->GetURL(self::REWARDS_PAGE_URL);

        if (!$this->http->ParseForm(null, "//form[contains(@action, '/guest/nologin/account-balance')]")) {
            return $this->checkErrors();
        }
        $this->http->SetInputValue($this->http->FindSingleNode("//input[@id = 'printedCard']/@name"), str_replace(' ', '', $this->AccountFields['Login']));
        $this->http->SetInputValue($this->http->FindSingleNode("//button[contains(@class, 'nologinCardnumberSubmitButton')]/@name"), "");

        $captcha = $this->parseCaptcha();

        if ($captcha === false) {
            return false;
        }
        $this->http->SetInputValue("g-recaptcha-response", $captcha);

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);
        /*
         * February 16, 2017
         *
         * The page you are trying to reach has been temporarily disabled due to security concerns.
         *
         * We are working to restore service. It may be days until service is restored for retrieving your balance through this page.
         *
         * Maintaining the security of your account is our highest priority.
         */
        if ($message = $this->http->FindPreg("/We are working to restore service. It may be days until service is restored for retrieving your balance through this page\./")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        if ($message = $this->http->FindPreg("/We are working to restore service and expect it to be available later this month. The balance on your card may be obtained at any participating store\./")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
        sleep(3);

        if (!$this->http->PostForm()) {
            return false;
        }
        // Access is allowed
        if ($this->loginSuccessful()) {
            return true;
        }
        // Invalid card number.
        if ($message = $this->http->FindSingleNode("//span[contains(text(), 'Invalid card number.')]")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        // For security reasons, this account requires a registration code.
        if ($this->parseQuestion()) {
            return false;
        }

        // provider error
        $message = $this->http->FindSingleNode("//div[h2[contains(text(), 'Account Balance')]]/following-sibling::div[@class = 'panel-body']//span[contains(@class, 'alert-danger')]");
        $this->logger->error(">>> '$message'");

        if (
            $message === ''
            || in_array($this->AccountFields['Login'], [
                '0000474085388',
                '0000538193202',
            ])
        ) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }
        // There was a problem validating the CAPTCHA. Please try again
        if ($this->http->FindSingleNode("//span[contains(text(), 'There was a problem validating the CAPTCHA. Please try again')]")) {
            throw new CheckRetryNeededException(3, 10, self::CAPTCHA_ERROR_MSG);
        }
        // Invalid CAPTCHA. Please try again.
        if ($this->http->FindSingleNode("//span[contains(text(), 'Invalid CAPTCHA. Please try again.')]")) {
            $this->recognizer->reportIncorrectlySolvedCAPTCHA();

            throw new CheckRetryNeededException(3, 10, self::CAPTCHA_ERROR_MSG);
        }

        return false;
    }

    public function parseQuestion()
    {
        $this->logger->notice(__METHOD__);
        $question = $this->http->FindSingleNode("//div[contains(text(), 'For security reasons, this account requires a registration code')]");

        if (!isset($question) || !$this->http->ParseForm(null, "//form[contains(@action, '/guest/nologin/account-balance')]")) {
            return false;
        }
        $this->Question = $question;
        $this->ErrorCode = ACCOUNT_QUESTION;
        $this->Step = "Question";

        return true;
    }

    public function ProcessStep($step)
    {
        $this->http->SetInputValue($this->http->FindSingleNode("//input[@id = 'registrationCode']/@name"), $this->Answers[$this->Question]);
        $this->http->SetInputValue($this->http->FindSingleNode("//button[contains(@class, 'nologinRegCodeSubmitButton')]/@name"), "");

        if (!$this->http->PostForm()) {
            return false;
        }
        // The registration code entered was incorrect
        if ($message = $this->http->FindSingleNode("//span[contains(text(), 'The registration code entered was incorrect')]")) {
            $this->AskQuestion($this->Question, $message);

            return false;
        }// if ($message = $this->http->FindSingleNode("//span[contains(text(), 'The registration code entered was incorrect')]"))

        return true;
    }

    public function Parse()
    {
        // Balance - Grill Meal Points
        $this->SetBalance($this->http->FindSingleNode("//div[div[strong[contains(text(), 'Your points balance is')]]]/following-sibling::div[1]//div[@class = 'row']", null, true, self::BALANCE_REGEXP));
        // Expiration Date
        $expire = $this->http->XPath->query("//div[div[strong[contains(text(), 'Your points balance is')]]]/following-sibling::div[1]//div[@class = 'row']/following-sibling::div[contains(@class, 'pointExpirations')]/div");
        $this->logger->debug("Total {$expire->length} exp nodes were found");

        if ($expire->length > 0) {
            $this->sendNotification("huhot. Multiple exp nodes were found");
        }
//        if (strtotime($expire))
//            $this->SetExpirationDate(strtotime($expire));

        // SubAccounts - rewards

        $nodes = $this->http->XPath->query("//div[@class = 'rewardBalance']/div[contains(@class, 'rewardRepeater')]");
        $this->logger->debug("Total {$nodes->length} rewards were found");

        for ($i = 0; $i < $nodes->length; $i++) {
            $node = $nodes->item($i);
            $displayName = $this->http->FindSingleNode('div/div[@class = "row"]', $node, true, "/\d+\s*(.+)/");
            $balance = $this->http->FindSingleNode('div/div[@class = "row"]', $node, true, "/(\d+)/");

            $subAccount = [
                'Code'        => 'huhot' . md5($displayName),
                'DisplayName' => $displayName,
                'Balance'     => $balance,
            ];

            $expNodes = $this->http->XPath->query('div/div[contains(@class, "rewardExpirations")]/div', $node);
            $this->logger->debug("[Node #{$i}]: total {$expNodes->length} exp nodes were found");
            unset($exp);

            foreach ($expNodes as $expNode) {
                $date = $this->http->FindPreg("/expire\s*on\s*(.+)/", false, $expNode->nodeValue);
                $value = $this->http->FindPreg("/(\d+)\s*expire\s*on/", false, $expNode->nodeValue);

                if (!isset($exp) || strtotime($date) < $exp) {
                    $exp = strtotime($date);
                    $this->logger->debug("[Node #{$i}]: set $date -> $value");
                    $subAccount['ExpirationDate'] = $exp;
                    $subAccount['ExpiringBalance'] = $value;
                }//if (!isset($exp) || strtotime($date) < $exp)
            }// foreach ($expNodes as $expNode)

            $subAccounts[] = $subAccount;
        }// for ($i = 0; $i < $nodes->length; $i++)

        if (isset($subAccounts)) {
            $this->logger->debug("Total subAccounts: " . count($subAccounts));

            if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR) {
                $this->SetBalanceNA();
            }
            // Set SubAccounts Properties
            $this->SetProperty("SubAccounts", $subAccounts);
        }// if(isset($subAccounts))
    }

    protected function parseCaptcha()
    {
        $this->logger->notice(__METHOD__);
        $key =
            $this->http->FindSingleNode("//form[contains(@action, '/guest/nologin/account-balance')]//div[@class = 'g-recaptcha']/@data-sitekey")
            ?? $key = $this->http->FindPreg("/'sitekey':\s*'(\w+)'/");
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

    /*
    function IsLoggedIn()
    {
        $this->http->RetryCount = 0;
        $this->http->GetURL(self::REWARDS_PAGE_URL, [], 20);
        $this->http->RetryCount = 2;
        if ($this->loginSuccessful()) {
            return true;
        }

        return false;
    }
    */

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);

        if ($this->http->FindNodes("
                //div[div[strong[contains(text(), 'Your points balance is')]]]
                | //strong[contains(text(), 'Congratulations!')
            ]")
        ) {
            return true;
        }

        return false;
    }
}
