<?php

use AwardWallet\Engine\ProxyList;

class TAccountCheckerPepboys extends TAccountChecker
{
    use SeleniumCheckerHelper;
    use ProxyList;

    public const REWARDS_PAGE_URL = 'https://www.pepboys.com/account/rewards';
    /**
     * @var CaptchaRecognizer
     */
    private $recognizer;

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->setDefaultHeader("Accept-Encoding", "gzip, deflate, br");
//        $this->http->SetProxy($this->proxyReCaptcha());
        $this->http->setHttp2(true);
        $this->http->setUserAgent(\HttpBrowser::FIREFOX_USER_AGENT);
        // makes it easier to parse an invalid HTML
        $this->http->FilterHTML = false;
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

    public function selenium()
    {
        $this->logger->notice(__METHOD__);
        $logout = false;
        $retry = false;
        $selenium = clone $this;
        $this->http->brotherBrowser($selenium->http);

        try {
            $this->logger->notice("Running Selenium...");
            $selenium->UseSelenium();
            /*
            $selenium->useChromium();
            */
            $selenium->useFirefox();
//            $selenium->disableImages();
            $selenium->usePacFile(false);
//            $selenium->useCache();
            $selenium->http->start();
            $selenium->Start();
            $selenium->http->saveScreenshots = true;
            $selenium->http->GetURL('https://www.pepboys.com');
            $form = '//form[@id = "loginForm"]';

            $selenium->waitForElement(WebDriverBy::xpath("{$form}//input[@name = 'j_username'] | //div[@class = 'g-recaptcha'] | //a[@id = 'loginDropDownNavBar']"), 10);
            $this->savePageToLogs($selenium);

            $this->logger->debug("find iframe");
            $iframe = null;

            try {
//                $iframe = $selenium->driver->findElement(WebDriverBy::xpath('//iframe[contains(@style, "display: block")]'));
                $iframe = $selenium->waitForElement(WebDriverBy::xpath('//iframe[contains(@style, "display: block")]'), 0);
            } catch (NoSuchElementException $e) {
                $this->logger->debug("error: {$e->getMessage()}");
            }

            if ($iframe) {
                $this->logger->debug("switch to iframe");
                $selenium->driver->switchTo()->frame($iframe);

                // save page to logs
                $this->savePageToLogs($selenium);

                $press = $selenium->waitForElement(WebDriverBy::xpath('//p[contains(text(), "Press & Hold")]'), 0);

                $mover = new MouseMover($selenium->driver);
                $mover->logger = $this->logger;
                $mover->enableCursor();

                // save page to logs
                $this->savePageToLogs($selenium);

                $mouse = $selenium->driver->getMouse();

                $this->logger->debug("move to 'press' button");
                $mover->moveToElement($press, ['x' => 20, 'y' => 20]);

                $mouse->mouseDown();
                sleep(30);
                $this->savePageToLogs($selenium);
                sleep(5);
                $this->savePageToLogs($selenium);
                $mouse->mouseUp();

                $selenium->driver->switchTo()->defaultContent();
                $this->savePageToLogs($selenium);
            }

            if ($key = $selenium->waitForElement(WebDriverBy::xpath("//div[@class = 'g-recaptcha']"), 0)) {
                $this->captchaWorkaround($selenium, $key);
                $loginInput = $selenium->waitForElement(WebDriverBy::xpath("{$form}//input[@name = 'j_username']"), 10);
            }// if ($key = $selenium->waitForElement(WebDriverBy::xpath("{$form}//input[@name = 'j_username'] | //div[@class = 'g-recaptcha']"), 0))

            $selenium->driver->executeScript('try { var loginForm = $(\'a#loginDropDownNavBar\'); if (loginForm) loginForm.click(); } catch (e) {}');

            $loginInput = $selenium->waitForElement(WebDriverBy::xpath("{$form}//input[@id = 'inputEmail']"), 0);
            $passwordInput = $selenium->waitForElement(WebDriverBy::xpath("{$form}//input[@id = 'inputPassword']"), 0);
            $button = $selenium->waitForElement(WebDriverBy::xpath("{$form}//button[contains(@id, 'loginsubmit') or @id = 'login-form__login-button']"), 0);
            $rememberMe = $selenium->waitForElement(WebDriverBy::xpath("{$form}//label[@for = 'inputRememberMe']"), 0);
            // save page to logs
            $this->savePageToLogs($selenium);

            if (!$loginInput || !$passwordInput || !$button || !$rememberMe) {
                $this->logger->error("something went wrong");

                return false;
            }
            $loginInput->clear();
            $loginInput->sendKeys($this->AccountFields['Login']);

            $passwordInput->clear();
            $passwordInput->sendKeys($this->AccountFields['Pass']);

            $rememberMe->click();

            // save page to logs
            $this->savePageToLogs($selenium);

            $button->click();

            // save page to logs
            $this->savePageToLogs($selenium);

            $success = $selenium->waitForElement(WebDriverBy::xpath("//h4[contains(text(), 'My Rewards')] | //a[@id = 'logindropdownMenuLink'] | //div[@id = 'js-addErrorSpan']"), 5);
            // save page to logs
            $this->savePageToLogs($selenium);

            if (!$success && ($key = $selenium->waitForElement(WebDriverBy::xpath("//div[@class = 'g-recaptcha']"), 3))) {
                $this->captchaWorkaround($selenium, $key);
                $success = $selenium->waitForElement(WebDriverBy::xpath("//h4[contains(text(), 'My Rewards')] | //div[@id = 'js-addErrorSpan']"), 5);
            }// if (!$success && ($key = $selenium->waitForElement(WebDriverBy::xpath("//div[@class = 'g-recaptcha']"), 3)))

            // todo: debug
            // save page to logs
            $this->savePageToLogs($selenium);

            if (!$success && ($key = $selenium->waitForElement(WebDriverBy::xpath("//div[@class = 'g-recaptcha']"), 3))) {
                $this->captchaWorkaround($selenium, $key);
                $success = $selenium->waitForElement(WebDriverBy::xpath("//h4[contains(text(), 'My Rewards')] | //div[@id = 'js-addErrorSpan']"), 5);
            }// if (!$success && ($key = $selenium->waitForElement(WebDriverBy::xpath("//div[@class = 'g-recaptcha']"), 3)))

            // save page to logs
            $this->savePageToLogs($selenium);

            if ($success && $selenium->waitForElement(WebDriverBy::xpath("//h4[contains(text(), 'My Rewards')]"), 0)) {
                $logout = true;
                $currentUrl = $this->http->currentUrl();
                $this->logger->debug("[Current URL]: {$currentUrl}");

                if ($currentUrl != self::REWARDS_PAGE_URL) {
                    $selenium->http->GetURL(self::REWARDS_PAGE_URL);
                }
                $success = $selenium->waitForElement(WebDriverBy::xpath("//h4[contains(text(), 'My Rewards')]"), 5);

                if (!$success && ($key = $selenium->waitForElement(WebDriverBy::xpath("//div[@class = 'g-recaptcha']"), 3))) {
                    $this->captchaWorkaround($selenium, $key);
                    $success = $selenium->waitForElement(WebDriverBy::xpath("//h4[contains(text(), 'My Rewards')]"), 5);
                }// if (!$success && ($key = $selenium->waitForElement(WebDriverBy::xpath("//div[@class = 'g-recaptcha']"), 3)))
            }// if ($success && $selenium->waitForElement(WebDriverBy::xpath("//h4[contains(text(), 'My Rewards')]"), 0))
            // save page to logs
            $this->savePageToLogs($selenium);

            $cookies = $selenium->driver->manage()->getCookies();

            foreach ($cookies as $cookie) {
                $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
            }
        } catch (ScriptTimeoutException $e) {
            $this->logger->error("ScriptTimeoutException exception: " . $e->getMessage());
            // retries
            if (strstr($e->getMessage(), 'timeout: Timed out receiving message from renderer')) {
                $retry = true;
            }
        } catch (StaleElementReferenceException $e) {
            $this->logger->error("StaleElementReferenceException exception: " . $e->getMessage());
            // retries
            if (strstr($e->getMessage(), 'Element not found in the cache')
                || strstr($e->getMessage(), 'element is not attached to the page document')) {
                $retry = true;
            }
        } finally {
            // close Selenium browser
            $selenium->http->cleanup(); //todo
        }
//        if ($retry && ConfigValue(CONFIG_SITE_STATE) != SITE_STATE_DEBUG) {
//            $this->logger->debug("[attempt]: {$this->attempt}");
//            throw new CheckRetryNeededException(3, 0);
//        }

        return $logout;
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();

//        $this->http->GetURL("https://www.pepboys.com/account/rewards");
        $this->http->GetURL('https://www.pepboys.com');

        if (!$this->http->ParseForm('loginForm') && !$this->http->FindSingleNode("//h1[contains(text(), 'Please verify you are a human')]")) {
            return $this->checkErrors();
        }

        $this->http->SetInputValue("j_username", $this->AccountFields["Login"]);
        //		$this->http->SetInputValue("j_password", md5($this->AccountFields["Pass"]));
        $this->http->SetInputValue("j_password", $this->AccountFields["Pass"]);
        $this->http->SetInputValue("_spring_security_remember_me", "true");

        $this->selenium();

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);
        //# Temporarily Down for Maintenance
        if ($message = $this->http->FindSingleNode('
                //p[contains(text(), "Pep Boys is temporarily down for maintenance")]
                | //h1[contains(text(), "PepBoys.com is Temporarily Unavailable Due to Maintenance")]
                | //h1[contains(text(), "Pepboys.com is temporarily down for maintenance")]     
                | //h1[contains(text(), "PEPBOYS.COM IS TEMPORARILY DOWN FOR MAINTENANCE")]       
                | //div[contains(text(), "Our Site is Currently Being Serviced")]       
            ')
        ) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        if (
            // Well this is embarrassing... it looks like something went wrong.
            $this->http->FindPreg("/Well this is embarrassing\.\.\. it looks like something went wrong\./ims")
            // Looks like you've hit a bump in the road. The page you were looking for has either moved or is no longer available.
            || $this->http->FindPreg("/Looks like you've hit a bump in the road\. The page you were looking for has either moved or is no longer available\./ims")

            || ($this->http->FindSingleNode("//p[@id = 'errorPageText']", null, true, "/Well this is embarrassing\.\.\.\s*it looks like something went wrong\./") && $this->http->currentUrl() == 'https://www.pepboys.com/account' && $this->http->Response['code'] == 500)
            || $this->http->FindSingleNode('//h1[contains(text(), "Error 503 backend read error")]')
        ) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
        /*
        if (!$this->http->PostForm()) {
            return $this->checkErrors();
        }
        */
        // Your login attempt was not successful, try again.
        if ($message = $this->http->FindSingleNode("//div[@id = 'js-addErrorSpan' and contains(., 'Your login attempt was not successful')]")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        // login successful
        if ($this->loginSuccessful()) {
            return true;
        }

        if ($message = $this->http->FindSingleNode("//b[contains(text(), 'Too many attempts to your account have been made. In order to keep your account secure, we have locked')]")) {
            throw new CheckException($message, ACCOUNT_LOCKOUT);
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        if ($this->http->currentUrl() != self::REWARDS_PAGE_URL) {
            $this->http->GetURL(self::REWARDS_PAGE_URL);
        }

        // Balance - Reward Points
        $this->SetBalance($this->http->FindSingleNode("//span[contains(text(), 'Reward Point')]", null, true, "/(\d+) Reward Point/"));
        // Points away from your next reward
        $this->SetProperty("FromNext", $this->http->FindSingleNode('//p[@class = "account-rewards__subtitle"]', null, true, "/Only (\d+) points? until/"));
        // Account #
        $this->SetProperty("Number", $this->http->FindSingleNode('//p[contains(text(), "Account")]', null, true, "/#(\d+)/"));

        // Certificates
        $certificates = $this->http->XPath->query("//div[@id = 'js-rewardsHistorySection']//table[contains(@class, 'account-rewards__table')]//tr[td]");
        $this->logger->debug("Total {$certificates->length} certificates were found");

        if ($certificates->length > 0) {
            $this->SetProperty("CombineSubAccounts", false);

            for ($i = 0; $i < $certificates->length; $i++) {
                $name = $this->http->FindSingleNode('td[1]', $certificates->item($i));
                $code = "sub$i";
                $value = $this->http->FindSingleNode('td[1]', $certificates->item($i), true, "/([\d\.]+)/");
                $expDate = strtotime($this->http->FindSingleNode('td[2]', $certificates->item($i), true, "/(\d+\/\d+\/\d+)/ims"));
                $status = $this->http->FindSingleNode('td[3]', $certificates->item($i));
                // ignore certificates expired more than 1 month ago
                if ($expDate > strtotime('-1 month', time()) && $status != 'Expired') {
                    $this->AddSubAccount([
                        "Code"           => $code,
                        "DisplayName"    => $name,
                        "Balance"        => $value,
                        "ExpirationDate" => $expDate,
                    ], true);
                }
            }// for ($i = 0; $i < $certificates->length; $i++)
        }// if ($certificates->length > 0)

        if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR) {
            if (
                $this->http->FindSingleNode("
                    //p[contains(text(), 'Looks like you aren’t signed up for CARLIFE Rewards!')]
                    | //b[contains(text(), 'In 2021, CARLIFE Rewards is going to be replaced by new programs optimized for your service or parts needs.')]
                ")
            ) {
                $this->SetWarning(self::NOT_MEMBER_MSG);
            }
            // Our Rewards Service is currently unavailable
            if ($message = $this->http->FindSingleNode("//h6[contains(text(), 'Our Rewards Service is currently unavailable')]")) {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }
        }// if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR)

        $this->http->GetURL("https://www.pepboys.com/account/profile/update-address");
        $fName = trim($this->http->FindSingleNode("//input[contains(@id, 'firstName')]/@value"));
        $lName = trim($this->http->FindSingleNode("//input[contains(@id, 'lastName')]/@value"));
        $this->SetProperty("Name", beautifulName("$fName $lName"));
    }

    protected function captchaWorkaround($selenium, $key)
    {
        $this->DebugInfo = 'reCAPTCHA checkbox';
        $captcha = $this->parseReCaptcha($key->getAttribute('data-sitekey'));

        if ($captcha === false) {
            $this->logger->error("failed to recognize captcha");

            return false;
        }
        $selenium->driver->executeScript('window.handleCaptcha(' . json_encode($captcha) . ')');

        return true;
    }

    protected function parseReCaptcha($key)
    {
        $this->logger->notice(__METHOD__);
        // key from https://captcha.perimeterx.net/PXnrdalolX/captcha.js?a=c&u=c7349500-2a90-11e9-a4a6-93984e516e46&v=&m=0
//        $key = '6Lcj-R8TAAAAABs3FrRPuQhLMbp5QrHsHufzLf7b';
        $this->logger->debug("data-sitekey: {$key}");

        if (!$key/* || !$this->http->FindSingleNode("//script[contains(@src, 'https://captcha.perimeterx.net/PXnrdalolX/captcha')]/@src")*/) {
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

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);

        if (
            $this->http->FindSingleNode("//a[@id='glovebarLogin']")
            || $this->http->FindNodes("//a[contains(@href, 'logout')]/@href")
        ) {
            return true;
        }

        return false;
    }
}
