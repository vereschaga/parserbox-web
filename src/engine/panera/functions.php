<?php

use AwardWallet\Common\Selenium\FingerprintFactory;
use AwardWallet\Common\Selenium\FingerprintRequest;
use AwardWallet\Engine\ProxyList;
use AwardWallet\Common\Parsing\Html;

class TAccountCheckerPanera extends TAccountChecker
{
    use ProxyList;
    use SeleniumCheckerHelper;

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;

        $this->UseSelenium();
        $this->setScreenResolution([1366, 768]);

        if ($this->attempt == 1) {
            $this->useGoogleChrome();

            $request = FingerprintRequest::chrome();
            $request->browserVersionMin = HttpBrowser::BROWSER_VERSION_MIN;
            $fingerprint = $this->services->get(FingerprintFactory::class)->getOne([$request]);

            if (isset($fingerprint)) {
                $this->seleniumOptions->fingerprint = $fingerprint->getFingerprint();
                $this->http->setUserAgent($fingerprint->getUseragent());
            }
        } else {
            $this->useChromePuppeteer();
        }

        $this->disableImages();
        $this->http->saveScreenshots = true;

        $this->setProxyBrightData();
    }

    public function LoadLoginForm()
    {
        $loginURL = ($this->attempt == 2)
            ? 'https://www.panerabread.com/en-us/mypanera/sign-up-with-mypanera.html'
            : 'https://www.panerabread.com';

        try {
            $this->http->GetURL($loginURL);
        } catch (TimeOutException $e) {
            $this->logger->error("TimeOutException exception: " . $e->getMessage());
            $this->driver->executeScript('window.stop();');
            $this->saveResponse();
        }

        if ($this->waitForElement(WebDriverBy::xpath('//h1[contains(text(), "We\'ll be right back...") or contains(text(), "Access Denied")]'), 0)) {
            $this->logger->error($this->DebugInfo = 'selenium has been blocked');

            throw new CheckRetryNeededException(3, 0);
        }

        $signUp = $this->waitForElement(WebDriverBy::xpath('
            //button[contains(text(), "Sign Up With Email")]
            | (//a[contains(@aria-label, "Sign in for MyPanera to re-order your favorites and earn rewards!")])[1]
            | (//a[@aria-label = "Sign in"])[1]
            | (//button[contains(text(), "Sign In")])[1]
        '), 5);
        $this->saveResponse();

        if (!$signUp) {
            $this->logger->debug("login form not loaded");

            if ($this->waitForElement(WebDriverBy::xpath('//span[contains(text(), "Hi ")]'), 0)) {
                return true;
            }

            return $this->checkErrors();
        }

        $this->driver->executeScript("document.getElementsByClassName('alert')[0].style.display = \"none\";");
        $signUp->click();
        $login = $this->waitForElement(WebDriverBy::xpath('//input[@id = "signInUsername"] | //*[@id = "username"]'), 5);
        $this->saveResponse();

        if ($login) {
            $login->click();
        }

        $loginInput = $this->waitForElement(WebDriverBy::xpath('//input[@id = "signInUsername"] | //*[@id = "username"]'), 5);
        $passwordInput = $this->waitForElement(WebDriverBy::id('signInPassword'), 0);

        if (!$loginInput || !$passwordInput) {
            $this->saveResponse();

            if ($this->waitForElement(WebDriverBy::xpath("//p[contains(text(),'MyPanera ordering is temporarily unavailable.')]"), 0)) {
                throw new CheckException('MyPanera ordering is temporarily unavailable.', ACCOUNT_PROVIDER_ERROR);
            }

            $this->driver->executeScript('
                function doEvent( obj, event ) {
                    var event = new Event( event, {target: obj, bubbles: true} );
                    return obj ? obj.dispatchEvent(event): false;
                };
                var el = document.querySelector(\'#username\').shadowRoot.querySelector("input#username");
                el.value = \''.$this->AccountFields['Login'].'\';
                doEvent(el, \'input\');
            ');
            sleep(1);
            $this->driver->executeScript('
                document.querySelector(\'div[class *= "iw-u-margin-y-3"] silo-button\').shadowRoot.querySelector(\'button\').click()
            ');

            $error = $this->waitForElement(WebDriverBy::xpath('//p[contains(@class, "pds-color-alert") or contains(@class, "pds-snackbar-text")]'), 3);
            $this->saveResponse();

            if ($error) {
                $message = Html::cleanXMLValue($error->getText());
                $this->logger->error("[Error]: '{$message}'");

                if (strstr($message, "Please ensure that your email address or phone number is correct.")) {
                    throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
                }

                if ($message == "Oops, something went wrong. Please try again.") {
                    throw new CheckRetryNeededException(2, 0, $message, ACCOUNT_PROVIDER_ERROR);
                }

                $this->DebugInfo = $message;

                return false;
            }

            $this->driver->executeScript('
                function doEvent( obj, event ) {
                    var event = new Event( event, {target: obj, bubbles: true} );
                    return obj ? obj.dispatchEvent(event): false;
                };
                var el = document.querySelector(\'#password\').shadowRoot.querySelector("input#password");
                el.value = \''.$this->AccountFields['Pass'].'\';
                doEvent(el, \'input\');
            ');
            $this->saveResponse();
            sleep(1);
            $button = $this->waitForElement(WebDriverBy::xpath('//silo-button[@class = "w-100" and contains(text(), "Sign In")]'), 5);
            $this->saveResponse();

//            $this->driver->executeScript('
//                document.querySelector(\'div[class *= "iw-u-margin-y-3"] silo-button\').shadowRoot.querySelector(\'button\').click()
//            ');

            if (!$button) {
                return false;
            }

            $button->click();

            return true;
        }

        $loginInput->sendKeys($this->AccountFields['Login']);
        $passwordInput = $this->waitForElement(WebDriverBy::id('signInPassword'), 0);
        $button = $this->waitForElement(WebDriverBy::xpath('//button[@aria-label="Sign In"]'), 0);
        $this->saveResponse();

        if (!$passwordInput || !$button) {
            return false;
        }

        $passwordInput->sendKeys($this->AccountFields['Pass']);
        $this->saveResponse();

        $button->click();

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);

        if ($message = $this->http->FindSingleNode("
            //p[contains(text(), 'Our website is currently unavailable while we conduct essential system maintenance and site enhancements.')]
            | //div[contains(text(), 'This page is currently unavailable while we make improvements')]
            | //div[contains(text(), 'Our application is currently unavailable.')]
            | //div[contains(@class, 'subtxt')]//node()[contains(., 'Please visit any of our locations to enjoy your faves.')]
        ")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // An unknown error has occurred
        if ($message = $this->http->FindSingleNode("//span[contains(text(), 'An unknown error has occurred')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // Internal Server Error - Read
        if (
            $this->http->FindSingleNode("
                //h1[contains(text(), 'Internal Server Error - Read')]
                | //title[contains(text(), 'Error 404--Not Found')]
                | //h1[contains(text(), 'Service Unavailable - Zero size object')]
            ")
        ) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
        $res = $this->waitForElement(WebDriverBy::xpath('//span[contains(text(), "Hi ")] | //div[contains(@class, "pds-snackbar-active")] | //p[contains(text(), "Enter the 6 digit code we sent to your phone number into the field below. Code expires in 2 minutes.")] | //p[contains(@class, "pds-color-alert") or contains(@class, "pds-snackbar-text")]'), 10);
        $resText = isset($res) ? $res->getText() : ''; // prevent reference staleness
        $this->saveResponse();

        if (!$res && ($button = $this->waitForElement(WebDriverBy::xpath('//button[@aria-label="Sign In"]'), 0))) {
            $button->click();
            $res = $this->waitForElement(WebDriverBy::xpath('//span[contains(text(), "Hi ")] | //div[contains(@class, "pds-snackbar-active")] | //p[contains(text(), "Enter the 6 digit code we sent to your phone number into the field below. Code expires in 2 minutes.")]'), 10);
            $resText = isset($res) ? $res->getText() : '';
            $this->saveResponse();
        }

        if ($this->parseQuestion()) {
            return false;
        }

        if (strstr($resText, 'Hi ')) {
            return true;
        }

        if ($error = $this->waitForElement(WebDriverBy::xpath('//p[contains(@class, "pds-color-alert") or contains(@class, "pds-snackbar-text")]'), 3)) {
            $message = $error->getText();
            $this->logger->error("[Error]: {$message}");

            if (strstr($message, "Please enter a valid password.")) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            if (strstr($message, "We were unable to sign you in.")) {
                throw new CheckRetryNeededException(3, 0, $message, ACCOUNT_INVALID_PASSWORD);
            }

            if (
                strstr($message, "For security purposes, your password must be reset. Please use \"Forgot Password\" to get started.")
                || strstr($message, "Your account has been locked for 24 hours due to too many failed log in attempts. T")
            ) {
                throw new CheckException($message, ACCOUNT_LOCKOUT);
            }

            $this->DebugInfo = $message;

            return false;
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        $this->http->GetURL('https://www.panerabread.com/en-us/mypanera/rewards.html');
        $this->waitForElement(WebDriverBy::xpath('//div[@class = "pnra-carousel-inner"]/div[@class = "pnra-slide"] | //p[contains(text(), "No rewards available.")]'), 5);
        $this->saveResponse();
        $rewards = $this->http->XPath->query('//div[@class = "pnra-carousel-inner"]/div[@class = "pnra-slide"]');
        $this->logger->debug("total $rewards->length rewards found");

        foreach ($rewards as $reward) {
            $name = $this->http->FindSingleNode('div/div/div/div/h4', $reward);
            $exp = strtotime($this->http->FindSingleNode('div/div/div/p[contains(@class, "iw-rp-subtext")]', $reward, true, '/Redeem by (.+)/') ?? '');

            if ($name && $exp) {
                $this->AddSubAccount([
                    'Code'           => 'panera' . preg_replace('/\W/', '', $name),
                    'DisplayName'    => $name,
                    'Balance'        => null,
                    'ExpirationDate' => strtotime('+1 day', $exp),
                ]);
            }
        }

        $noRewards = $this->waitForElement(WebDriverBy::xpath('//p[contains(text(), "No rewards available.")]'), 0);

        // loading card data by clicking links, otherwise there is 50% chance to break page loading
        $profileLink = $this->waitForElement(WebDriverBy::xpath('//a[contains(@href, "/profile-and-settings.html")]'), 0);
        $this->saveResponse();

        if (!$profileLink) {
            $this->logger->error('cannot go to profile');

            return;
        }

        if ($maintenance = $this->waitForElement(WebDriverBy::xpath('//p[contains(text(), "Oops! Our site is temporarily down for maintenance. ")]'), 0)) {
            throw new CheckException($maintenance->getText(), ACCOUNT_PROVIDER_ERROR);
        }

        $profileLink->click();

        $this->waitForElement(WebDriverBy::xpath('//a[contains(@href, "/mypanera-card.html")] | //input[@id = "signInPassword"]'), 5);

        if ( // To access your personal information, please re-enter your password.
            ($pwd = $this->waitForElement(WebDriverBy::xpath('//input[@id = "signInPassword"]'), 0))
            && $btn = $this->waitForElement(WebDriverBy::xpath('//button[contains(@class, "pds-sign-in-submit-btn")]'), 0)
        ) {
            $pwd->sendKeys($this->AccountFields['Pass']);
            $this->saveResponse();
            $btn->click();
            $cardLink = $this->waitForElement(WebDriverBy::xpath('//a[contains(@href, "/mypanera-card.html")]'), 5);
        } else {
            $cardLink = $this->waitForElement(WebDriverBy::xpath('//a[contains(@href, "/mypanera-card.html")]'), 0);
        }

        if (!$cardLink) {
            $this->logger->error('cannot go to card');
            $this->saveResponse();

            return;
        }
        $cardLink->click();

//        $this->http->GetURL('https://www.panerabread.com/en-us/mypanera/profile-and-settings/mypanera-card.html');
        $balance = $this->waitForElement(WebDriverBy::xpath('//p[contains(text(), "Available right now")]/preceding-sibling::p'), 5);
        $number = $this->waitForElement(WebDriverBy::xpath('//p[contains(@class, "iw-pas-h-details-name-mypanera-number")]'), 5);
        $this->saveResponse();

        if (!$number) {
            // this not help
//            $this->http->GetURL('https://www.panerabread.com/en-us/mypanera/rewards.html');
//
//            $this->http->GetURL('https://www.panerabread.com/en-us/mypanera/profile-and-settings/mypanera-card.html');
//            $balance = $this->waitForElement(WebDriverBy::xpath('//p[contains(text(), "Available right now")]/preceding-sibling::p'), 5);
            if ($noRewards && $this->http->FindSingleNode('//p[contains(@class, "iw-pas-h-details-name-full")]')) {
                $this->SetWarning("No rewards available");
            } else {
                throw new CheckRetryNeededException(3, 7 * $this->attempt);
            }
        }

        // Name
        $this->SetProperty('Name', beautifulName($this->http->FindSingleNode('//p[contains(@class, "iw-pas-h-details-name-full")]')));
        // MyPanera Card Number
        $this->SetProperty('AccountNumber', $this->http->FindSingleNode('//p[contains(@class, "iw-pas-h-details-name-mypanera-number")]', null, true, '/MyPanera #(\d+)/'));
        // Visits until next reward
        $this->SetProperty('VisitsUntilNextReward', $this->http->FindSingleNode('//span[contains(text(), "Visit")]', null, true, '/(\d+) Visit/'));

        if (!$number && !$noRewards) {
            return;
        }

        // Balance - Rewards
        $this->SetBalance($balance ? $this->http->FindPreg('/(.+)\s+Reward/ims', false, $balance->getText()) : null);
    }

    public function parseQuestion()
    {
        $this->logger->notice(__METHOD__);

        if ($this->waitForElement(WebDriverBy::xpath('//p[contains(text(), "Enter the 6 digit code we sent to your phone number into the field below. Code expires in 2 minutes.")]'), 0)) {
            $this->saveResponse();
            $this->holdSession();
            $this->AskQuestion('Enter the 6 digit code we sent to your phone number into the field below. Code expires in 2 minutes.', null, 'Question');

            return true;
        }

        return false;
    }

    public function ProcessStep($step)
    {
        $code = $this->Answers[$this->Question];
        unset($this->Answers[$this->Question]);
        $codeInput = $this->waitForElement(WebDriverBy::id('code'), 0);

        if (!$codeInput) {
            $this->saveResponse();

            return false;
        }
        $codeInput->clear();
        $codeInput->sendKeys($code);

        if ($this->waitForElement(WebDriverBy::xpath('//span[contains(text(), "The code you entered is incorrect")]'), 4)) {
            $this->saveResponse();
            $this->holdSession();
            $this->AskQuestion($this->Question, 'The code you entered is incorrect.', 'Question');

            return false;
        }

        return true;
    }
}
