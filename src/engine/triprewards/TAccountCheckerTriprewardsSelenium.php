<?php

use AwardWallet\Common\Selenium\FingerprintFactory;
use AwardWallet\Common\Selenium\FingerprintRequest;
use AwardWallet\Engine\ProxyList;
use AwardWallet\Engine\triprewards\QuestionAnalyzer;

class TAccountCheckerTriprewardsSelenium extends TAccountChecker
{
    use SeleniumCheckerHelper;
    use ProxyList;

    private const XPATH_PROFILE = '//div[contains(@class, "account-tab-text signed-in")]//span[contains(@class, "user-firstname")]';
    private const XPATH_ERRORS = '//div[contains(@class, "background-color-container")]//div[contains(@class, "form-error help-block") and @style = "display: block;"] 
    | //small[@data-fv-result="INVALID"] 
    | //h1[contains(text(), "Internal Server Error")] 
    | //h1[contains(text(), "Access Denied")] 
    | //span[contains(@class, "ulp-input-error-message") and not(contains(@class, "screen-reader-only"))] 
    | //div[@id="prompt-alert"]/p 
    | //body[contains(text(), "Not Authorized!")]';
    private const XPATH_VERIFY = '//button[contains(text(), "Verify Another Way")]';
    private const XPATH_PHONE_NOT_ADDED = '//button[@value="phone::0"]';
    private const REWARDS_PAGE_URL = 'https://www.wyndhamhotels.com/wyndham-rewards/my-account?ICID=IN%3AWR%3A20190403%3AMAQLM%3AMALEFTNAV%3AACCOUNT';
    /**
     * @var CaptchaRecognizer
     */
    public $recognizer;

    private $triprewards;

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->setHttp2(true);

        $this->http->SetProxy($this->proxyReCaptchaVultr());

        $this->UseSelenium();
        $resolutions = [
            [1152, 864],
            [1280, 720],
            [1280, 768],
            [1280, 800],
            [1360, 768],
            [1366, 768],
            [1920, 1080],
        ];
        $resolution = $resolutions[array_rand($resolutions)];
        $this->setScreenResolution($resolution);

        $this->http->saveScreenshots = true;

        $configs = [0, 1, 2, 3];

        if ($this->attempt == 1) {
            $configs = [7];
        } elseif ($this->attempt == 2) {
            $configs = [5];
        }

        $config = $configs[array_rand($configs)];
        $this->logger->notice("[Config]: {$config}");

        switch ($config) {
            case 0:
                $this->useGoogleChrome();
                $this->seleniumOptions->addHideSeleniumExtension = false;

                break;

            case 1:
                $this->useFirefoxPlaywright();

                break;

            case 2:
                $this->useGoogleChrome(SeleniumFinderRequest::CHROME_99);
                $fingerprint = $this->services->get(FingerprintFactory::class)->getOne([FingerprintRequest::chrome()]);

                break;

            case 3:
                $this->useFirefox();

                $request = FingerprintRequest::firefox();
                $request->browserVersionMin = HttpBrowser::BROWSER_VERSION_MIN;
                $fingerprint = $this->services->get(FingerprintFactory::class)->getOne([$request]);

                break;

            case 4:
                $this->useChromePuppeteer();
                $this->seleniumOptions->fingerprintOptions = ['devices' => ['desktop'], 'operatingSystems' => ['macos']];
                $this->seleniumOptions->addHideSeleniumExtension = false;
                $this->seleniumOptions->userAgent = null;

                break;

            case 5:
                $this->useGoogleChrome(SeleniumFinderRequest::CHROME_84);
//                $this->useChromePuppeteer(SeleniumFinderRequest::CHROME_PUPPETEER_100);

                break;

            case 7:
                $this->useGoogleChrome(SeleniumFinderRequest::CHROME_94);
                $this->seleniumOptions->addHideSeleniumExtension = false;
                $this->seleniumOptions->userAgent = null;

                break;
        }

        if (!empty($fingerprint)) {
            $this->seleniumOptions->fingerprint = $fingerprint->getFingerprint();
            $this->http->setUserAgent($fingerprint->getUseragent());
        }
    }

    public function IsLoggedIn()
    {
        $this->http->RetryCount = 0;
        $this->driver->manage()->window()->maximize();
        $this->http->GetURL(self::REWARDS_PAGE_URL, [], 20);
        $this->http->RetryCount = 2;

        if ($this->http->FindSingleNode('//*[contains(@class, "visible-xs")]//a[contains(text(), "SIGN OUT")]')) {
            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->driver->manage()->window()->maximize();
        try {
            $this->http->GetURL("https://login.wyndhamhotels.com/u/login?state=hKFo2SBZNzVEaEZOYW54eG9sYmNCUWxLUXJZMlhNYkNsOWFQQaFur3VuaXZlcnNhbC1sb2dpbqN0aWTZIFhkQzdieE1uQjNpUU1odEZZbzM3R3hPMnBlTTRnaTBfo2NpZNkgQ1VNR21PWWZYS1F0Y2FuN0NTVnhXY2hBbTRPY2tqcnE&ui_locales=en");
        } catch (Facebook\WebDriver\Exception\UnknownErrorException $e) {
            $this->logger->error("Exception: " . $e->getMessage(), ['HtmlEncode' => true]);
            $this->saveResponse();
        }

        $xpathForm = '//div[contains(@class, "background-color-container")]//form[contains(@class, "sign-in-form")] | //main[contains(@class, "login")]//form';

        $loginInput = $this->waitForElement(WebDriverBy::xpath($xpathForm . '//input[@name = "login-username" or @name = "username"]'), 7);
        $this->closePopup();
        $passwordInput = $this->waitForElement(WebDriverBy::xpath($xpathForm . '//input[@name = "login-password" or @name = "password"]'), 0);
        $this->saveResponse();

        if (!$loginInput || !$passwordInput) {
            if ($this->waitForElement(WebDriverBy::xpath(self::XPATH_VERIFY), 0)) {
                return true;
            }

            return false;
        }

        $this->logger->debug("set login");
        $loginInput->click();
        $loginInput->clear();
        $loginInput->sendKeys($this->AccountFields['Login']);
        $this->saveResponse();
        $this->logger->debug("set pass");
        $passwordInput->click();
        $passwordInput->clear();
        $passwordInput->sendKeys($this->AccountFields['Pass']);

        /*
        $mover = new MouseMover($this->driver);
        $mover->logger = $this->logger;

        $this->logger->debug("set login");
        $this->saveResponse();
        $mover->sendKeys($loginInput, $this->AccountFields['Login'], 5);
        $mover->click();
        $this->logger->debug("set pass");
        $passwordInput->click();
        $mover->sendKeys($passwordInput, $this->AccountFields['Pass'], 5);
        $mover->click();
        $mover->moveToElement($loginInput);
        $mover->click();
        */

        $captchaField = $this->waitForElement(WebDriverBy::xpath($xpathForm . '//input[@name = "captcha"]'), 0);

        if ($captchaField) {
            $captcha = $this->parseCaptchaImg();

            if ($captcha === false) {
                return false;
            }

            $captchaField->sendKeys($captcha);
            $this->saveResponse();
        }

        $button = $this->waitForElement(WebDriverBy::xpath($xpathForm . '//button[contains(text(), "SIGN IN") or contains(text(), "Continue")]'), 3);
        $this->saveResponse();

        if (!$button) {
            return false;
        }

        $button->click();

        return true;
    }

    public function Login()
    {
        $this->waitForElement(WebDriverBy::xpath(
            self::XPATH_PROFILE
            . ' | ' . self::XPATH_ERRORS
            . ' | ' . self::XPATH_VERIFY
            . ' | ' . self::XPATH_PHONE_NOT_ADDED
            . ' | //div[contains(@class, "background-color-container")]//input[@name = "answer1"]
        '), 20);
        $this->closePopup();
        $this->saveResponse();

        if ($this->http->FindSingleNode(self::XPATH_PROFILE)) {
            return true;
        }

        if ($verify = $this->waitForElement(WebDriverBy::xpath(self::XPATH_VERIFY), 0)) {
            $this->captchaReporting($this->recognizer);
            $verify->click();
            $sendCode =
                $this->waitForElement(WebDriverBy::xpath('//button[@aria-label="Email"]'), 10)
                ?? $this->waitForElement(WebDriverBy::xpath('//button[@aria-label="Phone Call or Text Message"] | //input[@id = "sms"]'), 0)
            ;
            $this->saveResponse();

            if (!$sendCode) {
                return false;
            }

            if ($this->isBackgroundCheck() && !$this->getWaitForOtc()) {
                $this->Cancel();
            }

            $sendCode->click();

            return $this->processSecurityCheckpoint();
        }

        // Add a phone number to verify your account.
        if ($this->waitForElement(WebDriverBy::xpath(self::XPATH_PHONE_NOT_ADDED), 0)) {
            $this->captchaReporting($this->recognizer);
            $this->throwProfileUpdateMessageException();
        }

        $this->saveResponse();

        if ($error = $this->http->FindSingleNode(self::XPATH_ERRORS)) {
            $this->logger->error("[Error]: {$error}");

            if (strstr($error, "Access Denied")) {
                return false;
//                throw new CheckRetryNeededException(3, 0);
            }
        }

        if ($message = $this->http->FindSingleNode('//span[contains(@class, "ulp-input-error-message") and not(contains(@class, "screen-reader-only"))] | //div[@id="prompt-alert"]/p')) {
            $this->logger->error("[Error]: {$message}");

            if (strstr($message, "Solve the challenge question to verify you are not a robot.")) {
                $this->captchaReporting($this->recognizer, false);

                throw new CheckRetryNeededException(3, 0, self::CAPTCHA_ERROR_MSG);
            }

            $this->captchaReporting($this->recognizer);

            if (strstr($message, "We were unable to verify the information you provided.")) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            if (
                strstr($message, "To protect your account, please reset your password before logging in")
                || strstr($message, "Your account has been locked")
            ) {
                throw new CheckException($message, ACCOUNT_LOCKOUT);
            }

            if ($message == 'We\'re sorry, something went wrong. Please try again later') {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }

            $this->DebugInfo = $message;

            return false;
        }

        $currentUrl = $this->http->currentUrl();
        $this->logger->debug("[Current URL]: {$currentUrl}");

        if (strstr($currentUrl, 'error=access_denied&error_description=Internal%20server%20error&state=')) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        if (
            strstr($currentUrl, 'https://login.wyndhamhotels.com/u/login?state=')
            || $this->waitForElement(WebDriverBy::xpath("//div[contains(text(), 'SIGN IN')]"), 0)
        ) {
            throw new CheckRetryNeededException(4, 0);
        }

        // block workaround
        if (
            $this->http->FindSingleNode('//a[contains(., "Get Access")]')
            && strstr($currentUrl, 'https://login.wyndhamhotels.com/u/login?state=')
        ) {
            throw new CheckRetryNeededException(4, 0);
        }

        return false;
    }

    public function processSecurityCheckpoint(): bool
    {
        $this->logger->notice(__METHOD__);

        if ($sms = $this->waitForElement(WebDriverBy::xpath('//label[@for="sms"]'), 5)) {
            $this->saveResponse();
            $sms->click();
            $this->saveResponse();
            $button = $this->waitForElement(WebDriverBy::xpath('//button[contains(text(), "Continue")]'), 0);

            if (!$button) {
                $this->logger->error("Button not found");
                $this->saveResponse();

                return false;
            }

            $button->click();
        }

        $destination = $this->waitForElement(WebDriverBy::xpath("//span[contains(@class, 'ulp-authenticator-selector-text')]"), 5);
        $this->saveResponse();

        if (!$destination) {
            $this->logger->error("something went wrong");

            return false;
        }

        $question = "We've sent an email with your code to: {$destination->getText()}";

        if (strstr($destination->getText(), 'XXXXXXX')) {
            $question = "We've sent a text message to: {$destination->getText()}";
        }

        if (strstr($destination->getText(), '@') && !QuestionAnalyzer::isOtcQuestion($question)) {
            $this->sendNotification("question has been changed");
        }

        if (!isset($question)) {
            $this->logger->error("something went wrong");

            return false;
        }

        if (!isset($this->Answers[$question])) {
            $this->holdSession();
            $this->AskQuestion($question, null, "Question2fa");

            return false;
        }

        $answer = $this->Answers[$question];
        unset($this->Answers[$question]);
        $code = $this->waitForElement(WebDriverBy::xpath('//input[@name="code"]'), 0);

        if (!$code) {
            return false;
        }

        $code->clear();
        $code->sendKeys($answer);
        $this->saveResponse();

        $button = $this->waitForElement(WebDriverBy::xpath('//button[contains(text(), "Continue")]'), 0);

        if (!$button) {
            $this->logger->error("Button not found");
            $this->saveResponse();

            return false;
        }

        $button->click();
        sleep(5);
        $this->saveResponse();

        $this->waitForElement(WebDriverBy::xpath(
            self::XPATH_ERRORS
            . " | " . self::XPATH_PROFILE
        ), 5);

        $message = $this->waitForElement(WebDriverBy::xpath(self::XPATH_ERRORS), 0);
        $this->saveResponse();

        if ($message) {
            $message = $message->getText();
            $this->logger->error("resetting answer: " . $message);

            if (
                strstr($message, 'The code you entered is not valid.')
                || strstr($message, 'We\'re sorry, we couldn\'t verify the code')
            ) {
                $this->holdSession();
                $this->AskQuestion($question, $message, "Question2fa");
            }

            if (strstr($message, 'Not Authorized!')) {
                throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
            }

            $this->DebugInfo = "[ProcessStep]: " . $message;

            return false;
        }

        $this->waitForElement(WebDriverBy::xpath('//span[@data-binding="AccountInfo.PointBalance"]'), 5);
        $this->saveResponse();

        return true;
    }

    public function ProcessStep($step)
    {
        $this->logger->notice(__METHOD__);
        $this->saveResponse();

        if ($this->isNewSession()) {
            return $this->LoadLoginForm() && $this->Login();
        }

        if ($step == 'Question2fa' && $this->processSecurityCheckpoint()) {
            $currentUrl = $this->http->currentUrl();
            $this->logger->debug("[Current URL]: {$currentUrl}");
            $this->saveResponse();

            return true;
        }

        return false;
    }

    public function Parse()
    {
        if ($this->http->currentUrl() != self::REWARDS_PAGE_URL) {
            $this->http->GetURL(self::REWARDS_PAGE_URL);
        }

        $this->waitForElement(WebDriverBy::xpath('//span[@data-binding="AccountInfo.PointBalance"]'), 5);
        $this->saveResponse();
        // Balance - You have ... Points
        $balance = $this->http->FindSingleNode('//*[contains(@class, "desktop-nav")]//span[@data-binding="AccountInfo.PointBalance"]');

        try {
            $sessionData = $this->driver->executeScript("return localStorage.getItem('OT_WHG_SESSION');");
            $this->logger->debug("[Form OT_WHG_SESSION]: " . $sessionData);
            $data = $this->http->JsonLog($sessionData);
        } catch (UnexpectedJavascriptException $e) {
            $this->logger->error("UnexpectedJavascriptException: " . $e->getMessage(), ['pre' => true]);
        }

        $this->logger->debug("[Balance]: {$balance}");
        $this->logger->debug("[Balance SESSION]: " . ($data->points ?? null));

        $triprewards = $this->getTriprewards();
        $triprewards->loginSuccessful();
        $triprewards->Parse();
        $this->SetBalance($triprewards->Balance);
        $this->Properties = $triprewards->Properties;
        $this->ErrorCode = $triprewards->ErrorCode;

        $this->SetBalance($balance ?? $data->points ?? null);

        // refs #23844
        $this->logger->info('Expiration date', ['Header' => 3]);

        $this->http->GetURL("https://www.wyndhamhotels.com/content/whg-ecomm-responsive/en-us/wr/lightbox/points-expiration.html");
        $this->waitForElement(WebDriverBy::xpath('//div[contains(@class, "signed-in-container")]//span[@data-binding="Channel.WR.ExpireDate"]'), 5);
        $this->saveResponse();

        $expFromProfile = $this->http->FindSingleNode('//div[contains(@class, "signed-in-container")]//span[@data-binding="Channel.WR.ExpireDate"]');
        $this->logger->debug("[Exp date from Profile]: {$expFromProfile}");

        $this->waitForElement(WebDriverBy::xpath("//div[contains(@class, 'signed-in-container')]//span[contains(@class, 'user-expiringpoints')]"), 10);
        $this->saveResponse();
        $expFromPointsExpirationPage = $this->http->FindSingleNode("//div[contains(@class, 'signed-in-container')]//span[contains(@class, 'points-expiration')]");
        $expBalance = $this->http->FindSingleNode("//div[contains(@class, 'signed-in-container')]//span[contains(@class, 'user-expiringpoints')]");
        $this->logger->debug("[Exp date from Points-Expiration page]: {$expFromPointsExpirationPage} / {$expBalance}");

        if (
            $expFromPointsExpirationPage
            && strtotime($expFromPointsExpirationPage) <= strtotime($expFromProfile)
        ) {
            $this->SetProperty("ExpiringBalance", $expBalance);
            $this->SetExpirationDate(strtotime($expFromPointsExpirationPage));
        } elseif ($expFromProfile) {
            $this->SetExpirationDate(strtotime($expFromProfile));
        }

        if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR) {
            $this->waitForElement(WebDriverBy::xpath('//span[@data-binding="AccountInfo.PointBalance"]'), 5);
            $this->saveResponse();
            // Balance - You have ... Points
            $this->SetBalance($this->http->FindSingleNode('//*[contains(@class, "desktop-nav")]//span[@data-binding="AccountInfo.PointBalance"]'));
        }

        if ($this->ErrorCode != ACCOUNT_CHECKED) {
            $sessionData = $this->driver->executeScript("return localStorage.getItem('OT_WHG_SESSION');");
            $this->logger->debug("[Form OT_WHG_SESSION]: " . $sessionData);

            $this->ErrorMessage = $triprewards->ErrorMessage;
            $this->DebugInfo = $triprewards->DebugInfo;

            if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR && empty($sessionData) && !empty($this->Properties['AccountNumber'])) {
                throw new CheckRetryNeededException(3, 0);
            }
        }
    }

    public function ParseItineraries()
    {
        $result = [];
        $triprewards = $this->getTriprewards();

        $this->http->GetURL('https://www.wyndhamhotels.com/wyndham-rewards/my-account/reservations?ICID=IN%3AWR%3A20190403%3AMAQLM%3AMALEFTNAV%3ARESERVATIONS');
        sleep(10);
        $this->saveResponse();
        $this->http->GetURL('https://www.wyndhamhotels.com/WHGServices/loyalty/V4/member/reservations');
        $response = $this->http->JsonLog($this->http->FindSingleNode('//pre[not(@id)]'));

        if ($this->http->FindPreg('/\{"success":"true","data":\[\]\}/')) {
            return $this->noItinerariesArr();
        }

        $noItineraries = false;

        if ($this->http->FindPreg('/"success":"true"/') && $this->http->FindPreg('/"data":\[\]/')) {
            $noItineraries = true;
        }

        if (!isset($response->body->reservations->all)) {
            $this->sendNotification('Check empty reservation // MI');
            /*if (isset($response->ErrorCode) && $response->ErrorCode != 504) {
                $this->sendNotification('Check the reservation for something wrong // MI');

                return $result;
            }*/
        }

        $this->logger->debug("Found " . count($response->body->reservations->all) . " itineraries");

        $noPastItineraries = false;

        if (isset($response->body->reservations->all)) {
            foreach ($response->body->reservations->all as $item) {
                $date = strtotime($item->bookingDate);

                if (!$this->ParsePastIts && isset($date) && $date < time() && $item->status != 'Cancelled') {
                    $this->logger->debug('skip past reservation: ' . $item->confirmationNumber);
                    $noPastItineraries = true;

                    continue;
                }
                $triprewards->ParseItinerary($item, $response->body->property, $item->status == 'Cancelled');
            }
        }

        if (count($triprewards->itinerariesMaster->getItineraries()) === 0 && $noPastItineraries) {
            $this->itinerariesMaster->setNoItineraries(true);
        }

        return $result;
    }

    public function GetHistoryColumns()
    {
        return [
            "Date"          => "PostingDate",
            "Description"   => "Description",
            "Activity Type" => "Info",
            "Nights"        => "Info",
            "Points"        => "Miles",
            "Miles"         => "Info",
        ];
    }

    public function ParseHistory($startDate = null)
    {
        $triprewards = $this->getTriprewards();

        return $triprewards->ParseHistory($startDate);
    }

    protected function parseCaptchaImg()
    {
        $this->logger->notice(__METHOD__);
        $img = $this->waitForElement(WebDriverBy::xpath('//img[@alt="captcha"]'), 0);

        if (!$img) {
            return false;
        }

        $this->recognizer = $this->getCaptchaRecognizer();
        $this->recognizer->RecognizeTimeout = 120;
        $pathToScreenshot = $this->takeScreenshotOfElement($img);
        $this->logger->notice('end takeScreenshotOfElement');
        $parameters = [
            "regsense" => 1,
        ];
        $captcha = $this->recognizeCaptcha($this->recognizer, $pathToScreenshot, $parameters);
        unlink($pathToScreenshot);

        return $captcha;
    }

    protected function getTriprewards()
    {
        $this->logger->notice(__METHOD__);

        if (!isset($this->triprewards)) {
            $this->triprewards = new TAccountCheckerTriprewards();
            $this->triprewards->http = new HttpBrowser("none", new CurlDriver());
            $this->triprewards->http->setProxyParams($this->http->getProxyParams());

            $this->http->brotherBrowser($this->triprewards->http);
            $this->triprewards->State = $this->State;
            $this->triprewards->AccountFields = $this->AccountFields;
            $this->triprewards->itinerariesMaster = $this->itinerariesMaster;
            $this->triprewards->HistoryStartDate = $this->HistoryStartDate;
            $this->triprewards->historyStartDates = $this->historyStartDates;
            $this->triprewards->http->LogHeaders = $this->http->LogHeaders;
            $this->triprewards->ParseIts = $this->ParseIts;
            $this->triprewards->ParsePastIts = $this->ParsePastIts;
            $this->triprewards->WantHistory = $this->WantHistory;
            $this->triprewards->WantFiles = $this->WantFiles;
            $this->triprewards->strictHistoryStartDate = $this->strictHistoryStartDate;
//            $this->logger->debug(var_export($this->http->getDefaultHeaders(), true), ['pre' => true]);
            $this->logger->debug("set headers");
            $defaultHeaders = $this->http->getDefaultHeaders();

            foreach ($defaultHeaders as $header => $value) {
                $this->triprewards->http->setDefaultHeader($header, $value);
            }

            $this->triprewards->globalLogger = $this->globalLogger;
            $this->triprewards->logger = $this->logger;
            $this->triprewards->onTimeLimitIncreased = $this->onTimeLimitIncreased;
        }

        $cookies = $this->driver->manage()->getCookies();
        $this->logger->debug("set cookies");

        foreach ($cookies as $cookie) {
            $this->triprewards->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
        }

        return $this->triprewards;
    }

    private function closePopup()
    {
        $this->logger->debug("close popup");
        $this->driver->executeScript("var popup = document.querySelector('#attentive_overlay'); if (popup) popup.style = \"display: none;\";");
    }
}
