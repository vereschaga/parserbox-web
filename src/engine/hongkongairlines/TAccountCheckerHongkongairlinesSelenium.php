<?php

use AwardWallet\Engine\ProxyList;

class   TAccountCheckerHongkongairlinesSelenium extends TAccountChecker
{
    use SeleniumCheckerHelper;
    use ProxyList;

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->UseSelenium();
        $this->http->SetProxy($this->proxyUK());
        $this->useGoogleChrome(SeleniumFinderRequest::CHROME_100);
        /*
        $this->useFirefox(\SeleniumFinderRequest::FIREFOX_84);
        $this->seleniumOptions->fingerprintParams = FingerprintParams::vanillaFirefox();
        $this->seleniumOptions->addHideSeleniumExtension = false;
        $this->setKeepProfile(true);
        $this->disableImages();
        */
//        $this->useCache();
//        $this->disableImages();
        $this->http->saveScreenshots = true;
    }

    public function IsLoggedIn()
    {
        try {
            $this->http->GetURL("https://www.hainanairlines.com/US/US/Home");
        } catch (TimeOutException | Facebook\WebDriver\Exception\TimeoutException $e) {
            $this->logger->error("Exception: " . $e->getMessage());
            $this->driver->executeScript('window.stop();');
        }

        $logout = $this->waitForElement(WebDriverBy::xpath('//span[contains(@class, "label-txt loggedin")]'), 5);
        $this->saveResponse();

        if ($logout) {
            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        $this->driver->manage()->window()->maximize();

        try {
            $this->http->removeCookies();
        } catch (NoSuchDriverException $e) {
            $this->logger->error("Exception: " . $e->getMessage());

            throw new CheckRetryNeededException(3, 5);
        }
        $this->http->GetURL("https://www.hainanairlines.com/US/US/Home");
        $form = $this->waitForElement(WebDriverBy::xpath('//span[contains(@class, "label-txt login")] | //a[@class = "type-3 login-link login-btn"]'), 15);
        $this->saveResponse();

        if (!$form) {
            $this->logger->error("something went wrong");

            return $this->checkErrors();
        }
        $form->click();

        sleep(3);
        $this->saveResponse();

        $login = $this->waitForElement(WebDriverBy::xpath('//input[@name = "login"]'), 5, false);
        $pass = $this->waitForElement(WebDriverBy::xpath('//input[@name = "password"]'), 0, false);
        $sbm = $this->waitForElement(WebDriverBy::xpath('//button[contains(@class, "loginPopup")]'), 0, false);

        if (!$login || !$pass || !$sbm) {
            $this->logger->error("something went wrong");
            $this->saveResponse();

            return $this->checkErrors();
        }
        // stupid user bug fix
        $this->AccountFields['Login'] = str_replace(' ', '', $this->AccountFields['Login']);
        $this->driver->executeScript("
            $('form[name = \"topLoginForm\"]').find('input[name = \"login\"]').val('{$this->AccountFields['Login']}');
            $('form[name = \"topLoginForm\"]').find('input[name = \"password\"]').val('" . addcslashes($this->AccountFields['Pass'], "'\\") . "');
            setTimeout(function () {
                $('button.loginPopup:visible').click();
            }, 500);
        ");
        $this->saveResponse();
//        $login->sendKeys($this->AccountFields['Login']);
//        $pass->sendKeys($this->AccountFields['Pass']);
//        $sbm->click();

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);
        // Service Unavailable - Zero size object
        if ($this->http->FindSingleNode('
                //body[contains(text(), "Internal Server Error")]
                | //h1[contains(text(), "503 Service Unavailable")]
            ')
        ) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
        $this->logger->notice(__METHOD__);
        $sleep = 40;
        $startTime = time();

        while ((time() - $startTime) < $sleep) {
            $this->logger->debug("(time() - \$startTime) = " . (time() - $startTime) . " < {$sleep}");
            $logout = $this->waitForElement(WebDriverBy::xpath('//a[contains(@class, "topLogoutLink")] | //span[contains(@class, "loggedin")]//span[contains(@class, "capital")]'), 0);
            $this->saveResponse();

            if ($logout || $this->http->FindSingleNode('//span[contains(@class, "loggedin")]//span[contains(@class, "capital")]')) {
                return true;
            }
            // ERROR Incorrect username or password
            if ($this->waitForElement(WebDriverBy::xpath('//em[@id = "loginpasswordMobile-error" and normalize-space(text()) = "Password :"]'), 0, false)) {
                throw new CheckException("ERROR Incorrect username or password", ACCOUNT_INVALID_PASSWORD);
            }

            if ($error = $this->waitForElement(WebDriverBy::xpath('//a[@class = "wdk-errorpanel-link"]'), 0, false)) {
                $message = $error->getText();
                $this->logger->error("[Error]: {$message}");

                if (
                    strstr($message, 'card number merged - ')
                    || $message == "Can\'t find a valid user."
                    || strstr($message, 'Password is invalid.')
                    || strstr($message, 'Can\'t find a valid user. - ')
                    || strstr($message, 'A technical issue has occured (FFP_PasswordVerify_07)')
                    || strstr($message, 'Invalid credentials. (FFP_PasswordVerify_01)')
                    || strstr($message, 'Input error (account does not exist)')
                    || strstr($message, 'The password incorrect. your account will be locked after ')
                ) {
                    throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
                }

                if (
                    strstr($message, 'Login failed due to unknown error')
                ) {
                    throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
                }

                if (
                    strstr($message, 'SyntaxError: JSON.parse: unexpected character at line 1 column 1 of the JSON data')
                    || $message == 'timeout'
                    || $message == 'undefined - undefined'
                    || $message === ''
                ) {
                    throw new CheckRetryNeededException(3, 0, self::PROVIDER_ERROR_MSG);
                }

                $this->DebugInfo = $message;

                break;
            }

            if ($message = $this->http->FindSingleNode('
                    //li[contains(text(), "We are having difficulty with the request as submitted.")]
                ')
            ) {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }

            sleep(1);
            $this->saveResponse();
        }

        return false;
    }

    public function Parse()
    {
        // Points balance
        $this->SetBalance($this->http->FindSingleNode("//span[@id = 'points_balanced']"));
        // Name
        $this->SetProperty("Name", beautifulName($this->http->FindSingleNode('//span[contains(@class, "loggedin")]//span[contains(@class, "capital")]')));
        // Member Status
        $this->SetProperty("Status", $this->http->FindSingleNode("//span[@id = 'status_label']"));
        // Member No.
        $this->SetProperty("MemberNo", $this->http->FindSingleNode("//span[@id = 'loggedin_ffNumber']"));
    }
}
