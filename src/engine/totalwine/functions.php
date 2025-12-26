<?php

use AwardWallet\Common\Selenium\FingerprintFactory;
use AwardWallet\Common\Selenium\FingerprintRequest;
use AwardWallet\Engine\ProxyList;

class TAccountCheckerTotalwine extends TAccountChecker
{
    use SeleniumCheckerHelper;
    use ProxyList;

    public const REWARDS_PAGE_URL = 'https://www.totalwine.com/my-account';

    public function InitBrowser(): void
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->UseSelenium();
        $this->usePacFile(false);

        $this->setProxyNetNut();

        $this->useChromePuppeteer();
        $this->seleniumRequest->setOs(SeleniumFinderRequest::OS_MAC);
        $this->seleniumOptions->addHideSeleniumExtension = false;

        $this->http->saveScreenshots = true;
    }

    public function LoadLoginForm(): bool
    {
        if (filter_var($this->AccountFields['Login'], FILTER_VALIDATE_EMAIL) === false) {
            throw new CheckException('Enter a valid email address in the format example@domain.com', ACCOUNT_INVALID_PASSWORD);
        }

        $this->http->removeCookies();
        $this->driver->manage()->window()->maximize();
        $this->http->GetURL("https://www.totalwine.com/login");

        $this->waitForElement(WebDriverBy::xpath('//span[contains(@class, "customerBlockName_")] | //input[@name = "emailAddress"] | //p[contains(text(), "Our website is currently down for scheduled maintenance.")]'), 15);
        $this->handlePopupSurvey();

        $login = $this->waitForElement(WebDriverBy::xpath('//input[@name = "emailAddress"]'), 0);
        $pwd = $this->waitForElement(WebDriverBy::xpath('//input[@name = "password"]'), 0);
        $btn = $this->waitForElement(WebDriverBy::xpath("//button[@data-at = 'signin-submit-button']"), 0);
        $this->saveResponse();

        if (!$btn || !$login || !$pwd) {
            if ($this->loginSuccessful()) {
                $this->markProxySuccessful();

                return true;
            }

            $this->logger->error('something went wrong');

            return $this->checkErrors();
        }

        if ($this->handlePopupSurvey()) {
            $login = $this->waitForElement(WebDriverBy::xpath('//input[@name = "emailAddress"]'), 0);
            $pwd = $this->waitForElement(WebDriverBy::xpath('//input[@name = "password"]'), 0);
            $btn = $this->waitForElement(WebDriverBy::xpath("//button[@data-at = 'signin-submit-button']"), 0);
        }

        $this->logger->debug("set credentials");
        $mover = new MouseMover($this->driver);
        $mover->logger = $this->logger;
        $mover->duration = rand(100000, 500000);
        $mover->steps = rand(5, 10);
        /*$login = $this->waitForElement(WebDriverBy::xpath('//input[@name = "emailAddress"]'), 0);
        $login->sendKeys($this->AccountFields['Login']);
        $this->saveResponse();
        $pwd = $this->waitForElement(WebDriverBy::xpath('//input[@name = "password"]'), 0);
        $pwd->sendKeys($this->AccountFields['Pass']);
        $this->saveResponse();*/

        $mover->sendKeys($login, $this->AccountFields['Login']);
        $mover->sendKeys($pwd, $this->AccountFields['Pass']);
        $this->saveResponse();

        $this->driver->executeScript('let remMe = document.querySelector("input[name=rememberMe]"); if (!remMe.checked) remMe.click();');

        $this->handlePopupSurvey();
        $btn = $this->waitForElement(WebDriverBy::xpath("//button[@data-at = 'signin-submit-button']"), 0);

        try {
            $btn->click();
        } catch (Exception $e) {
            $this->markProxyAsInvalid();

            throw new CheckRetryNeededException(3, 0);
        }

        return true;
    }

    public function Login(): bool
    {
        $res = $this->waitForElement(WebDriverBy::xpath("
            //h1[contains(@class, 'profileTitle__')]
            | //div[@role = 'alert']
            | //a[contains(@class, 'errorLink')]
            | //div[text() = 'Help us improve TotalWine.com']
            | //button[contains(text(), 'Send Code Via Email')]
        "), 25);
        $this->saveResponse();

        // 2fa
        if ($res && $res->getText() == 'Send Code Via Email') {
            $res->click();
            $this->markProxySuccessful();

            return $this->processSecurityCheckpoint();
        }

        if ($this->loginSuccessful()) {
            $this->markProxySuccessful();

            return true;
        }

        $error = $this->http->FindSingleNode('//a[contains(@class, "errorLink")]/parent::div');

        if ($error) {
            $this->logger->error('[Error]: ' . $error);

            if (
                strstr($error, 'Your password is incorrect')
                || strstr($error, 'The email address or password is incorrect.')
                || strstr($error, "Looks like you don't have an online account yet")
            ) {
                throw new CheckException($error, ACCOUNT_INVALID_PASSWORD);
            }

            if (strstr($error, 'Your account has been disabled')) {
                throw new CheckException($error, ACCOUNT_LOCKOUT);
            }

            $this->DebugInfo = '[Error]: ' . $error;

            return false;
        }

        return $this->checkErrors();
    }

    public function processSecurityCheckpoint()
    {
        $this->logger->notice(__METHOD__);
        $this->logger->info('Security Question', ['Header' => 3]);
        $question = $this->waitForElement(WebDriverBy::xpath('//div[contains(text(), "We have sent a verification code to") and strong[contains(text(), "@")]]'), 5);
        $input = $this->waitForElement(WebDriverBy::xpath("//input[@id = 'TextInputLbl1']"), 0);
        $btn = $this->waitForElement(WebDriverBy::xpath('//button[contains(text(), "Verify Account")]'), 0);
        $this->saveResponse();

        if (!$question || !$input || !$btn) {
            return false;
        }

        $question = $question->getText();

        $this->holdSession();

        if (!isset($this->Answers[$question])) {
            $this->AskQuestion($question, null, "Question");

            return false;
        }

        $answer = $this->Answers[$question];
        unset($this->Answers[$question]);

        $input->clear();
        $input->sendKeys($answer);
        $this->saveResponse();
        $this->logger->debug("click button...");

        try { // repeat click if nothing happened
            $btn->click();
            sleep(3);
//            $btn->click();
            $this->saveResponse();
        } catch (
            StaleElementReferenceException
            | WebDriverException
            | ElementNotVisibleException
            | InvalidElementStateException
            | \Facebook\WebDriver\Exception\StaleElementReferenceException
            | \Facebook\WebDriver\Exception\ElementNotVisibleException
            | \Facebook\WebDriver\Exception\InvalidElementStateException
            $e
        ) {
            $this->logger->error("Exception: " . $e->getMessage(), ['HtmlEncode' => true]);
        }

        $res = $this->waitForElement(WebDriverBy::xpath("
            //h1[contains(@class, 'profileTitle__')]
            | //div[@role = 'alert']
            | //a[contains(@class, 'errorLink')]
            | //div[contains(text(), 'The security code you’ve entered has expired. Please request a new code.')]
            | //div[contains(text(), 'You’ve entered invalid verification code.')]
        "), 10);
        $this->saveResponse();

        if ($error = $this->http->FindSingleNode('//div[contains(text(), "The security code you’ve entered has expired. Please request a new code")] | //div[contains(text(), "You’ve entered invalid verification code.")]')) {
            $this->AskQuestion($question, $error, "Question");

            return false;
        }

        $this->logger->debug("success");
        $this->logger->debug("CurrentUrl: " . $this->http->currentUrl());
        //$this->saveResponse();

        return true;
    }

    public function ProcessStep($step)
    {
        $this->logger->notice(__METHOD__);
        $this->logger->debug("Current URL: " . $this->http->currentUrl());

        if ($this->isNewSession()) {
            return $this->LoadLoginForm() && $this->Login();
        }

        if ($step == "Question") {
            return $this->processSecurityCheckpoint();
        }

        return false;
    }

    public function Parse(): void
    {
        $this->saveResponse();
        // Balance - pts
        $this->SetBalance($this->http->FindSingleNode('//span[contains(@class, "homeLoyaltyPoints")] | //*[contains(@class, "totalPoints__")]'));
        // Member number
        $this->SetProperty("Number", $this->http->FindSingleNode("//span[contains(@class, 'accountHomeMemberNumber')]", null, null, "/(\d+)/"));
        // Status
        $this->SetProperty("Status", ucfirst($this->http->FindSingleNode('//span[contains(@class, "homeLoyaltyTier")] | //p[contains(@class, "loyaltyInfoStripTier")]')));
        // Name
        $this->SetProperty("Name", $this->http->FindSingleNode("//span[contains(@class, 'customerBlockName_')]"));
        $btnMyRewards = $this->waitForElement(WebDriverBy::xpath('//a[@anclick="My Rewards"]'), 0);

        if (is_null($btnMyRewards)) {
            return;
        }
        $btnMyRewards->click();
        sleep(2);
        $this->saveResponse();

        if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR) {
            // Balance - pts
            $this->SetBalance($this->http->FindSingleNode('//span[contains(@class, "homeLoyaltyPoints")] | //span[contains(@class, "loyaltyPointsMessage__")]/span/strong | //*[contains(@class, "totalPoints__")]'));
            // Status
            $this->SetProperty("Status", beautifulName($this->http->FindSingleNode('//span[contains(@class, "homeLoyaltyTier")] | //p[contains(@class, "loyaltyInfoStripTier")]')));
        }

        // Collect X points by DD/MM/YYYY
        $this->SetProperty("PointsToNextLevel", $this->http->FindSingleNode('//span[contains(@class, "loyaltyInfoStripDescription__")]', null, false, "/Collect ([\d\,\. ]+) point/ims"));
        // Status valid until
        $this->SetProperty("StatusExpiration", $this->http->FindSingleNode('//span[@data-at="loyalty-points-expdate"]', null, false, '/(\d\d.\d\d.\d{4})/'));
        // Collect X more points to receive your next $X Reward
        $rewardGoal = $this->http->FindSingleNode('//span[starts-with(@class, "alignRight_")]', null, false, self::BALANCE_REGEXP);
        $rewardProgress = $this->http->FindSingleNode('//text[starts-with(@class, "totalPoints_")]', null, false, self::BALANCE_REGEXP);

        if (isset($rewardGoal, $rewardProgress)) {
            $rewardGoal = str_replace(',', '', $rewardGoal);
            $rewardProgress = str_replace(',', '', $rewardProgress);
            $this->SetProperty("PointsToNextReward", $rewardGoal - $rewardProgress);
        }

        $reward = $this->http->FindSingleNode('//p[starts-with(@class, "activeRewards_")]', null, false, '/You have a (.+)!/');

        if (!empty($reward)) { //activeRewardsExpiry_
            $params = [
                'Code'        => 'GiftCertificate',
                'DisplayName' => $reward,
                'Balance'     => null,
            ];
            $exp = $this->http->FindSingleNode('//p[starts-with(@class, "activeRewardsExpiry_")]', null, false, '/(\d\d.\d\d.\d{4})/');

            if (!empty($exp) && strtotime($exp)) {
                $params['ExpirationDate'] = strtotime($exp);
                $params['Code'] .= $params['ExpirationDate'];
            }
            $this->AddSubAccount($params);
        }
    }

    private function checkErrors(): bool
    {
        $this->logger->notice(__METHOD__);

        if ($message = $this->http->FindSingleNode('//p[contains(text(), "Our website is currently down for scheduled maintenance.")]')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    private function loginSuccessful(): bool
    {
        $this->logger->notice(__METHOD__);
        $name = $this->waitForElement(WebDriverBy::xpath('//span[contains(@class, "customerBlockName_")]'), 0);
        $this->saveResponse();

        if ($name || $this->waitForElement(WebDriverBy::xpath("//span[contains(@class, 'accountHomeMemberNumber')]"), 0)) {
            return true;
        }

        return false;
    }

    private function handlePopupSurvey()
    {
        $closePopUpSurveyButton = $this->waitForElement(WebDriverBy::xpath('//button[starts-with(@class, "QSIWebResponsiveDialog") and contains(text(), "No Thanks")] | //button[contains(@class, "onetrust-close-btn-handler")]'), 5);

        if ($closePopUpSurveyButton) {
            $closePopUpSurveyButton->click();
            sleep(5);
            $this->saveResponse();

            return true;
        }

        return false;
    }
}
