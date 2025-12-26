<?php

class TAccountCheckerTestselenium extends TAccountChecker
{
    use SeleniumCheckerHelper;

    public function InitBrowser()
    {
        $this->InitSeleniumBrowser();
        $this->ArchiveLogs = true;
    }

    public function IsLoggedIn()
    {
        if ($this->AccountFields['Login'] == 'downloadPdf') {
            return false;
        }

        $this->getWebDriver()->get($this->getHost() . '/onecard/');
        //		$this->getWebDriver()->manage()->addCookie(['name' => 'XDEBUG_SESSION', 'value' => 'PHPSTORM', 'domain' => getSymfonyContainer()->getParameter('host')]);
        try {
            return !empty($this->getWebDriver()->findElement(WebDriverBy::xpath('//*[contains(text(), "Mr. Alexi Vereschaga")]')));
        } catch (NoSuchElementException $e) {
        }

        return false;
    }

    public function LoadLoginForm()
    {
        if ($this->AccountFields['Login'] == 'downloadPdf') {
            return true;
        }

        $this->http->GetURL($this->getHost());

        return !empty($this->http->FindPreg('#AwardWallet keeps track#ims'));
    }

    public function Login()
    {
        if ($this->AccountFields['Login'] == 'downloadPdf') {
            return true;
        }

        if ($this->AccountFields['Login'] == 'throwCheckException') {
            throw new CheckException('CheckException. Firefox should be closed', ACCOUNT_INVALID_PASSWORD);
        }

        if ($this->AccountFields['Login'] == 'throwException') {
            throw new Exception('Exception. Firefox should be closed');
        }

        if (empty($this->http->FindSingleNode("//div[@id = 'loginButton']"))) {
            return false;
        }
        $driver = $this->getWebDriver();
        $driver->findElement(WebDriverBy::xpath('//a/div[@id = "loginButton"]'))->click();
        $this->waitFor(function () use ($driver) {
            return $driver->findElement(WebDriverBy::id('popupFrame'))->isDisplayed();
        }, 10);
        $driver->switchTo()->frame('popupFrame');
        $driver->findElement(WebDriverBy::id('fldLogin'))->sendKeys('SiteAdmin');
        $driver->findElement(WebDriverBy::id('fldPassword'))->sendKeys('awdeveloper');
        $driver->findElement(WebDriverBy::id('login-button'))->click();
        //		$driver->switchTo()->defaultContent();
        $element = $this->waitForElement(WebDriverBy::xpath('//*[contains(text(), "Mr. Alexi Vereschaga")]'), 30);

        if (!empty($element)) {
            $this->holdSession();
            $this->AskQuestion('Logged in, answer "account"');
        }

        return false;
    }

    public function Parse()
    {
        $driver = $this->getWebDriver();

        if ($this->AccountFields['Login'] == 'downloadPdf') {
            if ($this->AccountFields['Pass'] != 'session') {
                $driver->get($this->getHost() . '/admin/BofA.pdf');
            }
            $file = $this->getLastDownloadedFile();

            if (preg_match('#BofA\.pdf$#ims', $file)) {
                $contents = file_get_contents($file);

                if (strpos($contents, '%PDF') === 0) {
                    if ($this->AccountFields['Pass'] != 'session') {
                        $this->holdSession();
                        $this->SetBalance(50);
                    } else {
                        $this->SetBalance(30);
                    }
                } else {
                    $this->http->Log("not a PDF", LOG_LEVEL_ERROR);
                }
            }
        }

        if ($driver->getCurrentURL() == $this->getHost() . '/user/edit.php') {
            $this->SetBalance(100);
        }

        if ($driver->getCurrentURL() == $this->getHost() . '/onecard/') {
            $this->SetBalance(200);
        }
    }

    public function ProcessStep($step)
    {
        if ($this->isNewSession()) {
            return $this->LoadLoginForm() && $this->Login();
        } else {
            $driver = $this->getWebDriver();
            $driver->get($this->getHost() . '/user/edit.php');

            return !empty($this->getWebDriver()->findElement(WebDriverBy::id('fldEmail')));
        }
    }

    private function getHost()
    {
        if (ConfigValue(CONFIG_TRAVEL_PLANS)) {
            return getSymfonyContainer()->getParameter('requires_channel') . '://' . getSymfonyContainer()->getParameter('host');
        } else {
            return parse_url(DEBUG_SERVICE_LOCATION, PHP_URL_SCHEME) . '://' . parse_url(DEBUG_SERVICE_LOCATION, PHP_URL_HOST);
        }
    }
}
