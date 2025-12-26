<?php

use AwardWallet\Common\Parsing\JsExecutor;
use AwardWallet\Engine\ProxyList;

class TAccountCheckerHbc extends TAccountChecker
{
    use ProxyList;
    use SeleniumCheckerHelper;

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
//        $this->http->SetProxy($this->proxyReCaptchaIt7());
        $this->setProxyBrightData();
        $this->http->FilterHTML = false;
    }

    public function IsLoggedIn()
    {
        $this->logger->notice(__METHOD__);
        $this->http->RetryCount = 0;
        $this->http->GetURL("https://www.thebay.com/account/summary?registration=false");
        $this->http->RetryCount = 2;

        if ($this->loginSuccessful()) {
            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        $this->logger->notice(__METHOD__);
        $this->http->removeCookies();
        $this->http->GetURL("https://www.thebay.com/account/login?rurl=1");

        if (!$this->http->ParseForm("login-form")) {
            if ($this->http->Response['code'] == 403) {
                throw new CheckRetryNeededException(3, 0);
            }

            return false;
        }

        return $this->selenium();
    }

    /*
        protected function parseCaptcha($key) {
            $this->logger->notice(__METHOD__);
            $this->logger->debug("data-sitekey: {$key}");
            if (!$key)
                return false;
            $recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_RUCAPTCHA);
            $recognizer->RecognizeTimeout = 120;
            $parameters = [
                "pageurl"   => "https://www.thebay.com/account/login",
                "proxy"     => $this->http->GetProxy(),
                "invisible" => 1,
                "version"   => "v3",
                "action"    => "login",
            ];
            $captcha = $this->recognizeByRuCaptcha($recognizer, $key, $parameters);

            return $captcha;
        }
    */

    public function Login()
    {
        if ($this->loginSuccessful()) {
            return true;
        }

        if ($message = $this->http->FindSingleNode('//div[@class = "alert alert-danger" and @role="alert"] | //div[@class = "invalid-feedback" and normalize-space(text()) != ""]')) {
            $this->logger->error($message);
            // Invalid credentials
            if (
                strstr($message, 'Sorry, this does not match our records. Please try again.')
                || strstr($message, 'Enter a valid email address.')
            ) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            return false;
        }

        if ($this->http->FindPreg('/>Enroll Now<\/a>/')) {
            throw new CheckException(self::NOT_MEMBER_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        if ($this->http->FindSingleNode('//h1[contains(text(), "Join The Hudson\'s Bay Rewards Program")] | //p[contains(text(), "Join The Hudson\'s Bay Rewards Program")]')) {
            throw new CheckException(self::NOT_MEMBER_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        if ($message = $this->http->FindSingleNode('//strong[contains(text(), "Hudson’s Bay Rewards is experiencing a temporary service disruption.")]')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        return $this->checkErrors();
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);

        return false;
    }

    public function Parse()
    {
        // Balance
        $this->SetBalance($this->http->FindSingleNode('//div[@id = "maincontent"]//span[contains(@class,"container-detail") and contains(normalize-space(),"point")]'));
        // available to spend (Points Value)
        $this->SetProperty("PointsValue", $this->http->FindSingleNode('//div[@id = "maincontent"]//span[contains(@class, "redeem-points-value")]'));
        // Rewards #
        $this->SetProperty("AccountNumber", $this->http->FindSingleNode('//div[contains(@class, "top-container")]//div[@class = "rewardNo"]'));

        $this->http->GetURL("https://www.thebay.com/loyaltyrewards");
        // Spend to Next Tier
        $this->SetProperty("SpendNextTier", $this->http->FindSingleNode("//div[@class = 'rewards-detail-view']//span[contains(text(), 'Spend')]", null, true, "/Spend\s*([^\s]+)/"));

        // refs 20776
        $json = $this->http->FindPreg("/window.pointHistoryData = (JSON.parse\(\".+\"\));\s*window/");
//        $this->logger->debug(var_export($json, true), ['pre' => true]);
        $jsExecutor = $this->services->get(JsExecutor::class);
//        $script = file_get_contents('https://accounts.tajhotels.com/crypto-min.js');
//        $resource = $v8->compileString($script);
//        $v8->executeScript($resource);
        $json = $jsExecutor->executeString('sendResponseToPhp(JSON.stringify(' . $json . '))');
//        $this->logger->debug(var_export($json, true), ['pre' => true]);

        if ($json && $json !== 'null') {
            $json = $this->http->JsonLog($json, 1);

            foreach ($json as $item) {
                if (isset($item->points) && $item->points != 0) {
                    $date = strtotime($item->created_at);
                    $this->SetProperty("LastActivity", date("m/d/Y", $date));

                    $this->SetExpirationDate(strtotime("+24 month", $date));

                    break;
                }
            }
        }

        $this->http->GetURL("https://www.thebay.com/on/demandware.store/Sites-TheBay-Site/en_CA/Account-Profile");
        // Name
        $this->SetProperty("Name", beautifulName($this->http->FindSingleNode("//div[@class = 'profile-into name']")));
        // Tier
        $this->SetProperty("Tier", $this->http->FindSingleNode("//div[@class = 'user-links']/descendant::span[contains(@class,'loyalty-user-message--')]"));
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);

        if ($this->http->FindSingleNode('//div[contains(@class, "top-container")]//div[@class = "rewardNo"]')) {
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

        try {
            $this->logger->notice("Running Selenium...");
            $selenium->UseSelenium();
            $selenium->useFirefox();
            $selenium->setKeepProfile(true);
            $selenium->useCache();
            $selenium->disableImages();
            $selenium->http->start();
            $selenium->Start();
            $selenium->http->saveScreenshots = true;

            try {
                $selenium->http->GetURL("https://www.thebay.com/account/login");
            } catch (TimeOutException $e) {
                $this->logger->error("Exception: " . $e->getMessage());
                $this->driver->executeScript('window.stop();');
                $this->saveToLogs($selenium);
            }

            $selenium->waitForElement(WebDriverBy::xpath('//div[@id = "welcome-email-modal"] | //input[@id = "login-form-email"]'), 15);
            $this->saveToLogs($selenium);

            $modal = $selenium->waitForElement(WebDriverBy::id('welcome-email-modal'), 0);

            if ($modal) {
                $close = $selenium->waitForElement(WebDriverBy::id('consent-close'));
                $close->click();
            }
            /*
                        if ($this->AccountFields['Login'] == "veresch80@yahoo.com") {
                          //  $key = $selenium->http->FindSingleNode('//form[@name = "login-form"]/descendant::input[@class="g-recaptcha-token"][@name="token"]/@data-secret');
                            $key = '6LeMP90UAAAAAJ7On6bZZDXZl6uaDpffAxWMVQ7e';
                            if ($key) {
                                $captcha = $this->parseCaptcha($key);
                                if (!$captcha) {
                                    return false;
                                }
                                $selenium->driver->executeScript("$( '.g-recaptcha-token' ).val('{$captcha}');");
                            }
                        }
            */
            $login = $selenium->waitForElement(WebDriverBy::id('login-form-email'), 0);
            $pass = $selenium->waitForElement(WebDriverBy::id('login-form-password'), 0);
            $button = $selenium->waitForElement(WebDriverBy::xpath('//form[@name = "login-form"]/descendant::button[@type = "submit" and (contains(normalize-space(),"Sign In") or contains(normalize-space(),"Sign in"))]'), 0);
            $this->saveToLogs($selenium);

            if (!$login || !$pass || !$button) {
                return false;
            }
            $selenium->driver->executeScript("let popup = document.getElementById('welcome-email-modal'); if (popup) $('#welcome-email-modal').hide(); $('.modal-backdrop').hide();");
            sleep(1);
            $this->saveToLogs($selenium);
            $selenium->driver->executeScript("$('#rememberMe').prop('checked', 'checked');");
            $login->sendKeys($this->AccountFields['Login']);
            sleep(1);
            $pass->sendKeys($this->AccountFields['Pass']);
            sleep(3);
            $button->click();

            $selenium->waitForElement(WebDriverBy::xpath('
                //div[@class = "rewards-summary__header"]/span[contains(normalize-space(), "Rewards #")]
                | //div[@class = "alert alert-danger" and @role="alert"]
                | //div[@class = "invalid-feedback" and normalize-space(text()) != ""]
                | //h2[contains(text(), "Be Rewarded As You Shop")]/following-sibling::a[contains(text(), "Enroll Now")]
                | //div[@class = "rewardNo"]
                | //h1[contains(text(), "Join The Hudson\'s Bay Rewards Program")]
            '), 15);

            try {
                $this->saveToLogs($selenium);
            } catch (NoSuchDriverException $e) {
                $this->logger->error("Exception: " . $e->getMessage());
            }

            $cookies = $selenium->driver->manage()->getCookies();

            foreach ($cookies as $cookie) {
                $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
            }
        } catch (NoSuchDriverException | Facebook\WebDriver\Exception\UnknownErrorException $e) {
            $this->logger->error("Exception: " . $e->getMessage());
            $retry = true;
        } finally {
            // close Selenium browser
            $selenium->http->cleanup();

            // retries
            if ($retry && ConfigValue(CONFIG_SITE_STATE) != SITE_STATE_DEBUG) {
                throw new CheckRetryNeededException(3, 5);
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
