<?php

use AwardWallet\Common\Selenium\FingerprintFactory;
use AwardWallet\Common\Selenium\FingerprintRequest;
use AwardWallet\Engine\ProxyList;

class TAccountCheckerSeleniumGolf extends TAccountChecker
{
    use ProxyList;
    use SeleniumCheckerHelper;

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->UseSelenium();
        $this->http->saveScreenshots = true;
        $this->usePacFile(false);

        if ($this->attempt > 0) {
            $this->setProxyGoProxies();
        } else {
            $this->setProxyBrightData();
        }

        $this->useFirefoxPlaywright();
        $this->seleniumOptions->addHideSeleniumExtension = false;
        $this->seleniumOptions->userAgent = null;

        /*
        $this->useGoogleChrome(\SeleniumFinderRequest::CHROME_84);

        $this->seleniumOptions->addAntiCaptchaExtension = true;
        $this->seleniumOptions->antiCaptchaProxyParams = $this->getCaptchaProxy();

        if (!isset($this->State['Fingerprint']) || $this->attempt > 0) {
            $request = FingerprintRequest::chrome();
            $request->browserVersionMin = \HttpBrowser::BROWSER_VERSION_MIN;

            $fp = $this->services->get(FingerprintFactory::class)->getOne([$request]);

            if ($fp !== null) {
                $this->logger->info("selected fingerprint {$fp->getId()}, {{$fp->getBrowserFamily()}}:{{$fp->getBrowserVersion()}}, {{$fp->getPlatform()}}, {$fp->getUseragent()}");
                $this->State['Fingerprint'] = $fp->getFingerprint();
                $this->State['UserAgent'] = $fp->getUseragent();
                $this->State['Resolution'] = [$fp->getScreenWidth(), $fp->getScreenHeight()];
            }
        }

        if (isset($this->State['Fingerprint'])) {
            $this->logger->debug("set fingerprint");
            $this->seleniumOptions->fingerprint = $this->State['Fingerprint'];
        }

        if (isset($this->State["Resolution"])) {
            $this->setScreenResolution($this->State["Resolution"]);
        }

        if (isset($this->State['UserAgent'])) {
            $this->http->setUserAgent($this->State['UserAgent']);
        }
        */

//        $this->useGoogleChrome(\SeleniumFinderRequest::CHROME_95);
//        $this->seleniumOptions->addHideSeleniumExtension = false;
//        $this->seleniumOptions->userAgent = null;
    }

    public function IsLoggedIn()
    {
        try {
            $this->http->GetURL("https://www.golfgalaxy.com/MyAccount/AccountSummary", [], 20);
        } catch (UnexpectedJavascriptException $e) {
            $this->logger->error("UnexpectedJavascriptException: " . $e->getMessage(), ['HtmlEncode' => true]);
        } catch (ScriptTimeoutException | TimeOutException $e) {
            $this->logger->error("TimeOutException: " . $e->getMessage(), ['HtmlEncode' => true]);
            $this->driver->executeScript('window.stop();');
            $this->saveResponse();
        }

        if ($this->loginSuccessful() && $this->waitForElement(WebDriverBy::xpath('//span[@data-testid="point-balance"]'), 0)) {
            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        if (filter_var($this->AccountFields['Login'], FILTER_VALIDATE_EMAIL) === false) {
            throw new CheckException('Please enter a valid email address.', ACCOUNT_INVALID_PASSWORD);
        }

        $this->http->removeCookies();
        $this->http->GetURL('https://www.golfgalaxy.com');

        $signIn = $this->waitForElement(WebDriverBy::xpath("//div[@title='Sign In to Earn Points']"), 5);

        if (!$signIn) {
            $this->saveResponse();

            if ($this->http->FindSingleNode("//h1[contains(text(), 'Access Denied')]")) {
                $this->markProxyAsInvalid();
                $this->DebugInfo = "This site can’t be reached";

                throw new CheckRetryNeededException(4, 0);
            }

            return false;
        }
        $signIn->click();

//        $this->http->GetURL('https://www.golfgalaxy.com/LogonForm');
        $this->waitForElement(WebDriverBy::xpath("//input[@id = 'username'] | //h1[contains(text(), 'Help us verify real visitors')] | //h1[contains(text(), 'Access Denied')]"), 10);
        $this->saveResponse();

        if ($this->waitForElement(WebDriverBy::xpath("//h1[contains(text(), 'Help us verify real visitors')]"), 0)) {
            $this->logger->debug('waiting for recaptcha');

            $this->waitFor(function () {
                return is_null($this->waitForElement(WebDriverBy::xpath('//a[contains(text(), "Solving is in process...")]'), 0));
            }, 120);
            $this->saveResponse();

            if ($submitBtn = $this->waitForElement(WebDriverBy::xpath("//input[@value = 'Submit']"), 120)) {
                $submitBtn->click();
                $this->waitForElement(WebDriverBy::xpath("//input[@id = 'username']"), 10);
            }
        }

        $login = $this->waitForElement(WebDriverBy::xpath("//input[@id = 'username']"), 0);
        $pass = $this->waitForElement(WebDriverBy::xpath("//input[@id = 'password']"), 0);
        $btn = $this->waitForElement(WebDriverBy::xpath("//div[not(contains(@class, 'hidden')) and not(@aria-hidden = 'true')]/button[@type = 'submit']"), 0);
        $this->saveResponse();

        if (empty($login) || empty($pass) || empty($btn)) {
            $this->logger->error('something went wrong');

            if ($this->http->FindSingleNode('//a[contains(text(), "Sign Out")]')) {
                return $this->loginSuccessful();
            }

            if ($this->http->FindSingleNode("//h1[contains(text(), 'Access Denied')]")) {
                $this->markProxyAsInvalid();
                $this->DebugInfo = "This site can’t be reached";

                throw new CheckRetryNeededException(4, 0);
            }

            return $this->checkErrors();
        }
        $mover = new MouseMover($this->driver);
        $mover->logger = $this->logger;

        $this->logger->debug("login");
        $mover->sendKeys($login, $this->AccountFields['Login'], 10);
//        $login->click();
//        $login->sendKeys($this->AccountFields['Login']);

        $this->logger->debug("pass");
        $mover->sendKeys($pass, $this->AccountFields['Pass'], 10);
//        $pass->click();
//        $pass->sendKeys($this->AccountFields['Pass']);

        $this->logger->debug("click");
        sleep(random_int(1, 3));
        $btn->click();

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);

        return false;
    }

    public function Login()
    {
        $result = $this->waitForElement(WebDriverBy::xpath("//a[contains(text(), \"ScoreCard\")] | //p[contains(@id, 'validation-alert')] | //span[@id = \"login-error-message-text\"] | //span[@class = \"ulp-input-error-message\"] | //div[@data-error-code = 'password-breached']/p | //h1[contains(text(), 'Access Denied') or contains(text(), 'ACCESS DENIED')]"), 10);
        $this->saveResponse();

        if ($this->http->BodyContains('<div class="sec-container">', false)) {
            $result = $this->waitForElement(WebDriverBy::xpath("//a[contains(text(), \"ScoreCard\")]"), 15);
            $this->saveResponse();
        }

        if (!$result && ($this->waitForElement(WebDriverBy::xpath("//button[@id = 'sign-in-button']"), 0))) {
            $this->waitForElement(WebDriverBy::xpath("//a[contains(text(), \"ScoreCard\")] | //p[contains(@id, 'validation-alert')] | //span[@id = \"login-error-message-text\"]"), 10);
            $this->saveResponse();
        }

        // Access is allowed
        if ($this->loginSuccessful()) {
            return true;
        }
        $message = $this->http->FindSingleNode('//p[contains(@id, "validation-alert")] | //span[@id = "login-error-message-text"] | //span[@class = "ulp-input-error-message"] | //div[@data-error-code = "password-breached"]/p');

        if ($message) {
            $this->logger->error($message);

            if (
                $message == 'The specified logon ID or password are not correct. Verify the information provided and log in again.'
                || $message == 'Wrong email or password.'
            ) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            if (
                $message == 'To sign in, please enter a valid email address and password.'
            ) {
                throw new CheckRetryNeededException(2, 1, $message, ACCOUNT_INVALID_PASSWORD);
            }

            if (
                $message == 'The user account is disabled. Contact your site administrator regarding your access.'
                || $message == 'You have exceeded the number of password attempts. Please reset your password.'
                || $message == 'We have detected a potential security issue with this account. To protect your account, we have prevented this login. Please reset your password to proceed'
            ) {
                throw new CheckException($message, ACCOUNT_LOCKOUT);
            }

            return false;
        }

        if ($this->http->FindSingleNode("//h1[contains(text(), 'Access Denied')]")) {
            $this->ErrorReason = self::ERROR_REASON_BLOCK;
            $this->DebugInfo = 'Access Denied';

            throw new CheckException("Access Denied", ACCOUNT_PROVIDER_ERROR);
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        $balance = $this->waitForElement(WebDriverBy::xpath('//span[@data-testid="point-balance"]'), 10);
        $this->saveResponse();

        if (!$balance) {
            return;
        }

        // ScoreCard Member
        $this->SetProperty("CardNumber", $this->waitForElement(WebDriverBy::xpath('//*[contains(@class, "loyalty-number")]'), 0)->getText());
        // Name
        $this->SetProperty("Name", beautifulName($this->waitForElement(WebDriverBy::xpath('//*[contains(@class, "card-title sessioncamhidetext")]'), 0)->getText()));

        $goToRewardsBtn = $this->waitForElement(WebDriverBy::xpath('//a[@routerlinkactive="active" and contains(text(), "ScoreCard")]'), 0);

        if ($goToRewardsBtn) {
            $goToRewardsBtn->click();
        }

        // Available Rewards
        $this->logger->info('Available Rewards', ['Header' => 3]);
//        $this->http->GetURL("https://www.golfgalaxy.com/MyAccount/Rewards");
        $this->waitForElement(WebDriverBy::xpath("//h2[contains(text(), 'Point Balance:')]/span[contains(text(), 'POINT')]"), 15);
        $this->saveResponse();
        // Balance - Point Balance
        $this->SetBalance($this->http->FindSingleNode('//h2[contains(text(), "Point Balance:")]/span', null, true, "/(.+)\s+POINT/"));
        // close modal windows
        $modal = $this->waitForElement(WebDriverBy::xpath('//div[@id = "homr-modal"]/descendant::div[@class="close"]'), 0);

        if ($modal) {
            $modal->click();
        }
        $rewards = $this->http->XPath->query('//ul[contains(@class, "active-reward-list") and not(contains(@class, "inactive"))]/li[not(.//p[contains(text(), "REDEEMED")]) and not(.//p[contains(text(), "EXPIRED")])]');
        $this->logger->debug("Total {$rewards->length} rewards were found");

        foreach ($rewards as $reward) {
            // Online Code
            $code = $this->http->FindSingleNode('.//span[contains(@class, "reward-code")]', $reward);
            $balance = $this->http->FindSingleNode('.//p[contains(text(), "Reward") or contains(text(), "REWARD")]', $reward, true, "/(.+)\s*Reward/ims");
            // Expiration Date
            $expirationDate = $this->http->FindSingleNode('.//p[contains(text(), "EXPIRES IN") or contains(text(), "Expires in") or contains(text(), "Expires in")]', $reward, true, "/EXPIRES\s*.N:?\s*(\d+)/ims");
            $this->logger->debug($expirationDate);

            if (isset($code, $balance) && ($exp = strtotime("+{$expirationDate} -1 day"))) {
                $this->AddSubAccount([
                    'Code'           => 'golfAvailableRewards' . $code,
                    'DisplayName'    => "Reward {$code}",
                    'Balance'        => $balance,
                    'ExpirationDate' => $exp,
                    //                    'BarCode'        => ArrayVal($reward, 'storeCode', null),
                    //                    "BarCodeType"    => BAR_CODE_CODE_128,
                ]);
            }
        }// foreach ($rewards as $reward)

        // Expiration Date  // refs #6115
        if ($this->Balance <= 0) {
            return;
        }

        $points = $this->Balance;
        $this->logger->info('Expiration Date', ['Header' => 3]);

        $spinner = $this->driver->executeScript('return $("#myaccount-container").find("common-progress-spinner > svg > circle").length > 0');

        if (!empty($spinner)) {
            $this->logger->debug("spinner: " . $spinner, ['pre' => true]);
            sleep(3);
        }
        $i = 0;

        while (
            ($viewMore = $this->waitForElement(WebDriverBy::xpath('//a[contains(text(), "VIEW MORE")]'), 2))
            && $i < 15
        ) {
            $i++;
            $viewMore->click();
            sleep(2);
            $this->saveResponse();
        }
        $transactions = $this->http->XPath->query('//div[contains(@class, "point-history")]');
        $this->logger->debug("Total {$transactions->length} transactions were found");

        for ($i = 0; $i < $transactions->length; $i++) {
            $historyPoints = $this->http->FindSingleNode('div[contains(@class, "points")]', $transactions->item($i), true, "/(.+)\s+point/ims");
            $historyPoints = str_replace(',', '', $historyPoints);
            $this->logger->debug("Node # $i - Balance: $points, HistoryPoints: $historyPoints");

            if ($historyPoints > 0) {
                $points -= $historyPoints;
            }
            $this->logger->debug("Node # $i - Balance: $points / round: " . round($points, 2));

            if ($points <= 0) {
                $date = $this->http->FindSingleNode('.//span[contains(@class, "transactionDate") or contains(@class, "effective-date")]', $transactions->item($i));

                if (isset($date)) {
                    $this->SetProperty("EarningDate", $date);
                    $this->SetExpirationDate(strtotime("+1 year", strtotime($date)));
                }
                // Expiring balance
                $this->SetProperty("ExpiringBalance", ($points + $historyPoints));

                break;
            }// if ($points <= 0)
        }// for ($i = 0; $i < $historyPoints->length; $i++)
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);

        if ($this->http->FindSingleNode('//a[contains(text(), "ScoreCard")]')) {
            return true;
        }

        return false;
    }
}
