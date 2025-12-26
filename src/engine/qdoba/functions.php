<?php

class TAccountCheckerQdoba extends TAccountChecker
{
    use SeleniumCheckerHelper;

    public static function GetAccountChecker($accountInfo)
    {
        require_once __DIR__ . "/TAccountCheckerQdobaSelenium.php";
        return new TAccountCheckerQdobaSelenium();
    }
    public function LoadLoginForm()
    {
        $this->http->setRandomUserAgent();
        if(!$this->seleniumCookies())
            return false;
        $this->http->GetURL('https://nomnom-prod-migration.qdoba.com/api/profiles/login?redirectUri=https://order.qdoba.com/oauth/callback');

        $state = $this->http->FindPreg('/state=(.+)/', false, $this->http->currentUrl());
        $headers = [
            'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8,application/signed-exchange;v=b3;q=0.7',
            'Origin' => 'https://qdoba-prod.us.auth0.com',
            'priority' => 'u=0, i',
        ];
        $data = [
            'state' => $state,
            'username' => $this->AccountFields['Login'],
            'password' => $this->AccountFields['Pass'],
        ];
        $this->http->PostURL("https://qdoba-prod.us.auth0.com/u/login?state=$state", $data, $headers);
    }

    public function seleniumCookies(): bool
    {
        $this->logger->notice(__METHOD__);

        $selenium = clone $this;
        $retry = false;
        $this->http->brotherBrowser($selenium->http);

        try {
            $this->logger->notice("Running Selenium...");
            //$selenium->setProxyGoProxies(null, 'ca');
            $selenium->UseSelenium();

            /*
            $selenium->useGoogleChrome(\SeleniumFinderRequest::CHROME_94);
            $selenium->useGoogleChrome(\SeleniumFinderRequest::CHROME_95);
            $selenium->seleniumOptions->addHideSeleniumExtension = false;
            $selenium->seleniumOptions->userAgent = null;
            */
            $selenium->useFirefox(SeleniumFinderRequest::FIREFOX_84);
            $selenium->setKeepProfile(true);
            //$selenium->seleniumOptions->fingerprintOptions = ['devices' => ['desktop'], 'operatingSystems' => ['macos']];
            $selenium->seleniumOptions->addHideSeleniumExtension = false;
            $selenium->seleniumOptions->userAgent = null;

//            $selenium->disableImages();
//            $selenium->useCache();
            $selenium->usePacFile(false);
            $selenium->http->start();
            $selenium->Start();
            $selenium->http->saveScreenshots = true;

            $selenium->http->removeCookies();
            $selenium->http->GetURL('https://order.qdoba.com/order/rewards');

            $formXpath = "//form[contains(@method, 'POST')]";

            $selenium->waitForElement(WebDriverBy::xpath("//input[@value = 'Verify you are human'] 
            | //div[@id = 'turnstile-wrapper']//iframe 
            | //div[contains(@class, 'cf-turnstile-wrapper')] 
            | //button[span[contains(text(),'Log In')]]
            | //form[contains(@method,'POST')]//input[@id = 'username']
            | //div[@class='px-captcha-error-message']"), 10);
            $this->savePageToLogs($selenium);


            if ($this->clickCloudFlareCheckboxByMouse($selenium)) {
                sleep(5);
            }
            $cookies = $selenium->driver->manage()->getCookies();

            foreach ($cookies as $cookie) {
                $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
            }
            $this->savePageToLogs($selenium);

            $selenium->http->GetURL('https://nomnom-prod-migration.qdoba.com/api/profiles/login?redirectUri=https://order.qdoba.com/oauth/callback');
            if ($this->clickCloudFlareCheckboxByMouse($selenium)) {
                sleep(5);
            }

            $cookies = $selenium->driver->manage()->getCookies();

            foreach ($cookies as $cookie) {
                $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
            }

            $this->savePageToLogs($selenium);
            $this->logger->debug("[Current URL]: {$selenium->http->currentUrl()}");

            if ($this->http->FindSingleNode("//span[contains(text(), 'Your connection was interrupted')]")) {
                $retry = true;
            }
        } catch (Facebook\WebDriver\Exception\UnknownErrorException | WebDriverCurlException | NoSuchDriverException $e) {
            $this->logger->error("Exception: " . $e->getMessage(), ['HtmlEncode' => true]);
            $retry = true;
        } catch (ScriptTimeoutException $e) {
            $this->logger->error("ScriptTimeoutException: " . $e->getMessage(), ['HtmlEncode' => true]);
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
}
