<?php

use AwardWallet\Engine\ProxyList;

require_once __DIR__ . '/../algerie/functions.php';

class TAccountCheckerJordanian extends TAccountCheckerAlgerieAero
{
    use ProxyList;
    use SeleniumCheckerHelper;

    public $code = "royalclub";

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        //$this->setProxyGoProxies(null, 'de');
    }

    public function getStatus($tier)
    {
        $this->logger->debug("Tier: {$tier}");

        switch ($tier) {
            case 'BRONZ':
                $status = 'BRONZE SUNBIRD';

                break;

            case 'SILV':
                $status = 'SILVER JAY';

                break;

            case 'GOLD':
            case 'GOLDM':
                $status = 'GOLD SPARROW';

                break;

            case 'PLAT':
            case 'PLATM':
                $status = 'PLATINUM HAWK';

                break;

            default:
                $this->sendNotification("New status was found: {$tier}");
                $status = '';
        }

        return $status;
    }

    public function LoadLoginForm()
    {
        $this->logger->notice(__METHOD__);
        $selenium = clone $this;
        $this->http->brotherBrowser($selenium->http);
        $retry = false;

        try {
            $this->logger->notice("Running Selenium...");
            $selenium->UseSelenium();

//            $selenium->useGoogleChrome(\SeleniumFinderRequest::CHROME_95);
//            $selenium->seleniumOptions->addHideSeleniumExtension = false;

            //$selenium->useChromePuppeteer();
            $selenium->useFirefox(SeleniumFinderRequest::FIREFOX_84);
            $selenium->seleniumOptions->addHideSeleniumExtension = false;
//            $selenium->seleniumOptions->userAgent = null;

            $selenium->disableImages();
//            $selenium->useCache();
            $selenium->usePacFile(false);
            $selenium->http->start();
            $selenium->http->saveScreenshots = true;
            $selenium->Start();

            $selenium->http->GetURL('https://royalclub.frequentflyer.aero/pub/#/main/not-authenticated/');

            $selenium->waitForElement(WebDriverBy::xpath('//input[@id = "username"] | //*[contains(text(), "This site can’t be reached")]'), 30);

            $loginInput = $selenium->waitForElement(WebDriverBy::id("username"), 0);
            $passwordInput = $selenium->waitForElement(WebDriverBy::xpath('//input[@id = "password"]'), 0);
            $button = $selenium->waitForElement(WebDriverBy::xpath('//button[@id = "loginButton"]'), 0);
            // save page to logs
            $this->savePageToLogs($selenium);

            if (!$loginInput || !$passwordInput || !$button) {
                if ($this->http->FindSingleNode('//span[contains(text(), "This site can’t be reached")]')) {
                    $retry = true;
                }

                return $this->checkErrors();
            }
            $loginInput->sendKeys($this->AccountFields['Login']);
            $passwordInput->sendKeys($this->AccountFields['Pass']);

            $selenium->driver->executeScript('
            let oldXHROpen = window.XMLHttpRequest.prototype.open;
            window.XMLHttpRequest.prototype.open = function(method, url, async, user, password) {
                this.addEventListener("load", function() {
                    if (/uniqueHashCode/g.exec( this.responseText )) {
                        localStorage.setItem("responseData", this.responseText);
                    }
                });
                return oldXHROpen.apply(this, arguments);
            };
            ');
            $button->click();
            sleep(4);
            $responseData = $selenium->driver->executeScript("return localStorage.getItem('responseData');");
            $this->logger->info("[Form responseData]: " . $responseData);
            $this->savePageToLogs($selenium);
            // save page to logs
            $this->savePageToLogs($selenium);
            $cookies = $selenium->driver->manage()->getCookies();

            foreach ($cookies as $cookie) {
                $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'],
                    $cookie['expiry'] ?? null);
            }
            $currentUrl = $selenium->http->currentUrl();
            $this->logger->debug("[Current URL]: {$currentUrl}");

            if (!empty($responseData)) {
//                $this->jsonToForm($this->http->JsonLog($responseData, 5, true));
                $this->http->SetBody($responseData);

                return true;
            }
        } catch (NoSuchWindowException $e) {
            $this->logger->error("Exception: " . $e->getMessage(), ['HtmlEncode' => true]);
            $retry = true;
        } finally {
            // close Selenium browser
            $selenium->http->cleanup();

            if ($retry && ConfigValue(CONFIG_SITE_STATE) != SITE_STATE_DEBUG) {
                $this->logger->debug("[attempt]: {$this->attempt}");

                throw new CheckRetryNeededException(3, 7);
            }
        }

        return true;
    }
}
