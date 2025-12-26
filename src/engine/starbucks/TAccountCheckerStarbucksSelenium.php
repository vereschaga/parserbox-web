<?php

use AwardWallet\Common\Selenium\FingerprintRequest;
use AwardWallet\Engine\ProxyList;

class TAccountCheckerStarbucksSelenium extends TAccountChecker
{
    use SeleniumCheckerHelper;
    use ProxyList;
    /** @var HttpBrowser */
    public $browser;

    public $regionOptions = [
        ""            => "Select your country",
        "Canada"      => "Canada",
        "China"       => "China",
        "Germany"     => "Germany",
        "Ireland"     => "Ireland",
        "Japan"       => "Japan",
        "HongKong"    => "Hong Kong",
        "Mexico"      => "Mexico",
        "Spain"       => "Spain",
        "Singapore"   => "Singapore",
        "Switzerland" => "Switzerland",
        "Taiwan"      => "Taiwan",
        "Thailand"    => "Thailand",
        "UK"          => "United Kingdom",
        "USA"         => "USA",
    ];
    /** @var CaptchaRecognizer */
    private $recognizer;

    private $domain = 'com';
    private $curl = false;
    private $port;
    private $luminati = false;
    private $netnut = true;
    private $oAuthProxy = false;
    private $ff53 = true;

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->setKeepProfile(false); // wsdl will do retries on same object ?
        $this->UseSelenium();

        if (!in_array($this->AccountFields['Login2'], ['China'])) {
            /*
            $resolutions = [
                [1152, 864],
                [1280, 720],
                [1280, 768],
                [1280, 800],
                [1360, 768],
                [1366, 768],
                [1920, 1080],
            ];
            $this->setScreenResolution($resolutions[array_rand($resolutions)]);

            if ($this->attempt == 0) {
                $this->useFirefox(SeleniumFinderRequest::FIREFOX_59);
                $this->setKeepProfile(true);

                if ((!isset($this->State['Fingerprint']) && !isset($this->State["UserAgent"])) || $this->attempt > 1) {
                    $request = FingerprintRequest::firefox();
                    $request->browserVersionMin = \HttpBrowser::BROWSER_VERSION_MIN;
                    $fp = $this->services->get(\AwardWallet\Common\Selenium\FingerprintFactory::class)->getOne([$request]);

                    if ($fp !== null) {
                        $this->logger->info("selected fingerprint {$fp->getId()}, {{$fp->getBrowserFamily()}}:{{$fp->getBrowserVersion()}}, {{$fp->getPlatform()}}, {$fp->getUseragent()}");
                        $this->State['Fingerprint'] = $fp->getFingerprint();
                        $this->State['UserAgent'] = $fp->getUseragent();
                        $this->State['Resolution'] = [$fp->getScreenWidth(), $fp->getScreenHeight()];
                    }
                }
            } else {
                switch (rand(0, 1)) {
                    case 0:
                        $this->useFirefox(\SeleniumFinderRequest::FIREFOX_84);
                        $this->setKeepProfile(true);

                        if ((!isset($this->State['Fingerprint']) && !isset($this->State["UserAgent"])) || $this->attempt > 1) {
                            $request = FingerprintRequest::firefox();
                            $request->browserVersionMin = \HttpBrowser::BROWSER_VERSION_MIN;
                            $fp = $this->services->get(\AwardWallet\Common\Selenium\FingerprintFactory::class)->getOne([$request]);

                            if ($fp !== null) {
                                $this->logger->info("selected fingerprint {$fp->getId()}, {{$fp->getBrowserFamily()}}:{{$fp->getBrowserVersion()}}, {{$fp->getPlatform()}}, {$fp->getUseragent()}");
                                $this->State['Fingerprint'] = $fp->getFingerprint();
                                $this->State['UserAgent'] = $fp->getUseragent();
                                $this->State['Resolution'] = [$fp->getScreenWidth(), $fp->getScreenHeight()];
                            }
                        }

                        break;

                    case 1:
                        $this->useGoogleChrome(\SeleniumFinderRequest::CHROME_84);

                        if ((!isset($this->State['Fingerprint']) && !isset($this->State["UserAgent"])) || $this->attempt > 1) {
                            $request = FingerprintRequest::chrome();
                            $request->browserVersionMin = \HttpBrowser::BROWSER_VERSION_MIN;
                            $fp = $this->services->get(\AwardWallet\Common\Selenium\FingerprintFactory::class)->getOne([$request]);

                            if ($fp !== null) {
                                $this->logger->info("selected fingerprint {$fp->getId()}, {{$fp->getBrowserFamily()}}:{{$fp->getBrowserVersion()}}, {{$fp->getPlatform()}}, {$fp->getUseragent()}");
                                $this->State['Fingerprint'] = $fp->getFingerprint();
                                $this->State['UserAgent'] = $fp->getUseragent();
                                $this->State['Resolution'] = [$fp->getScreenWidth(), $fp->getScreenHeight()];
                            }
                        }

                        break;
                }
            }

            if (!isset($this->State['Resolution']) || $this->attempt > 1) {
                $this->logger->notice("set new resolution");
                $resolution = $resolutions[array_rand($resolutions)];
                $this->State['Resolution'] = $resolution;
            } else {
                $this->logger->notice("get resolution from State");
                $resolution = $this->State['Resolution'];
                $this->logger->notice("restored resolution: " . join('x', $resolution));
            }
            $this->setScreenResolution($resolution);
            $this->http->setUserAgent($this->State['UserAgent'] ?? $this->http->getDefaultHeader('User-Agent'));
            */
            $this->useFirefox();
            $this->setKeepProfile(true);

            $this->http->saveScreenshots = true;

            if ($this->attempt == 0) {
                $this->http->SetProxy($this->proxyDOP());
            }
            // luminati has been blocked
            elseif ($this->luminati === true) {
                $this->setProxyBrightData();
            } elseif ($this->netnut === true) {
                $this->setProxyGoProxies();
            }
        } else {
//            $this->setScreenResolution([1920, 1080]);
//            $this->useGoogleChrome(\SeleniumFinderRequest::CHROME_99);
            $this->useFirefox(SeleniumFinderRequest::FIREFOX_84);
            $this->setKeepProfile(true);
            $this->http->saveScreenshots = true;
        }
        $this->http->TimeLimit = 500;
        //		$this->disableImages();
        $this->keepCookies(true);

        $this->setDomain();
        $this->AccountFields['Login2'] = $this->checkRegionSelection($this->AccountFields['Login2']);
        $this->logger->notice('Region => ' . $this->AccountFields['Login2']);
    }

    public function IsLoggedIn()
    {
        switch ($this->AccountFields['Login2']) {
            case 'China':
                $this->http->RetryCount = 0;
                $this->http->GetURL('https://profile.starbucks.com.cn/api/Customers/detail', [], 20);
                $this->http->RetryCount = 2;

                if ($this->http->FindPreg('/"userName".+?,"firstName":/')) {
                    return true;
                }

                break;

            case 'Japan':
                $this->http->GetURL('https://www.starbucks.co.jp/mystarbucks/?nid=mm&mode=mb_001');
                // TODO
                break;

            case 'UK':
            case 'Germany':
            case 'Ireland':
            case 'Spain':
            case 'Switzerland':
                $this->http->RetryCount = 0;

                try {
                    $this->http->GetURL("https://www.starbucks." . $this->domain . "/account/settings", [], 20);
                } catch (UnexpectedJavascriptException | WebDriverCurlException $e) {
                    $this->logger->error("Exception: " . $e->getMessage());
                }
                $this->http->RetryCount = 2;
                $this->logger->debug('[Current URL]: ' . $this->http->currentUrl());
                $email = $this->http->FindSingleNode('//dd[contains(@class, "account-settings-cta-value") and contains(text(), "@")]');
                $this->logger->debug('[Email]: ' . $email);

                if (strtolower($email) == strtolower($this->AccountFields['Login'])) {
                    return true;
                }

                break;

            case 'USA':
            case 'Canada':
                if ($this->isBackgroundCheck()) {
                    $this->Cancel(); // checking through extension
                }
                $this->http->RetryCount = 0;

                try {
                    $this->http->GetURL("https://app.starbucks." . $this->domain . "/settings", [], 20);
                } catch (UnexpectedJavascriptException | WebDriverCurlException | NoSuchWindowException | ErrorException $e) {
                    $this->logger->error("Exception: " . $e->getMessage());
                }
                $this->http->RetryCount = 2;
                $this->logger->debug('[Current URL]: ' . $this->http->currentUrl());

                if (
                    isset($this->http->Response['body'])
                    && (
                        $this->http->FindPreg('/Your email address<\/span>/')
                        || $this->http->FindPreg('/Personal info<\/h2>/')
                    )
                    && !strstr($this->http->currentUrl(), 'account/signin?ReturnUrl=https')
                ) {
                    return true;
                }

                break;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        $this->logger->notice('Proxy => ' . $this->http->GetProxy());

        if (in_array($this->AccountFields['Login2'], [
            'China',
            'Japan',
        ])) {
            if (!empty($this->AccountFields['Login2']) && method_exists($this, "LoadLoginForm" . $this->AccountFields['Login2'])) {
                return call_user_func([$this, __METHOD__ . $this->AccountFields['Login2']]);
            }
        }

        try {
            if (strstr($this->AccountFields['Pass'], '❹')) {
                throw new CheckException("Sorry, we were unable to log you in. Please try again.", ACCOUNT_INVALID_PASSWORD);
            }

            $this->logger->debug("[Current URL]: {$this->http->currentUrl()}");

            $loginURL = "https://www.starbucks." . $this->domain . "/account/signin";

            if (!in_array($this->AccountFields['Login2'], ['USA', 'Canada'])) {
                $loginURL = "https://www.starbucks." . $this->domain . "/account/login";
            }

            if (!strstr($this->http->currentUrl(), $loginURL)) {
                try {
                    $this->http->GetURL($loginURL);
                } catch (TimeOutException $e) {
                    $this->logger->error("Exception: " . $e->getMessage());
                    $this->driver->executeScript('window.stop();');
                } catch (UnexpectedAlertOpenException $e) {
                    $this->logger->error("UnexpectedAlertOpenException: " . $e->getMessage());
                    sleep(3);

                    try {
                        $error = $this->driver->switchTo()->alert()->getText();
                        $this->logger->debug("alert -> {$error}");
                        $this->driver->switchTo()->alert()->accept();
                        $this->logger->debug("alert, accept");
                    } catch (NoAlertOpenException | UnexpectedAlertOpenException $e) {
                        $this->logger->error("exception: " . $e->getMessage());
                    }
                }
            }

            $mover = new MouseMover($this->driver);
            $mover->logger = $this->logger;

            if (in_array($this->AccountFields['Login2'], [
                'USA',
                'UK',
                'Germany',
                'Canada',
                'Ireland',
                'Spain',
                'Switzerland',
            ])) {
                $login = $this->waitForElement(WebDriverBy::xpath("//input[@id = 'username' or @id = 'edit-email' or @name=\"email\"]"), 15);
                $this->saveResponse();
                // This site uses cookies, but not the kind you eat
                /*if (!$login) {
                    $btn = $this->waitForElement(WebDriverBy::id('truste-consent-button'), 0, false);
                    if ($btn) {
                        $btn->click();
                        $login = $this->waitForElement(WebDriverBy::xpath("//input[@id = 'username' or @id = 'edit-email']"), 3);
                    }
                }*/
                if (!$login) {
                    if ($this->waitForElement(WebDriverBy::xpath("//a[contains(text(), 'Logout')]"), 0, false)) {
                        $this->http->GetURL("https://www.starbucks." . $this->domain . "/account");

                        return true;
                    }

                    if ($message = $this->waitForElement(WebDriverBy::xpath("//p[contains(text(), 'cleaning up a few things on the site.')]"), 0)) {
                        $this->markProxyAsInvalid();

                        throw new CheckRetryNeededException(3, 7, $message->getText(), ACCOUNT_PROVIDER_ERROR);
                    }

                    if ($message = $this->http->FindSingleNode('//p[contains(text(), "The website that you\'re trying to reach is having technical difficulties and is currently unavailable.")]')) {
                        throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
                    }

                    return $this->callRetry();
                }

                $this->driver->executeScript('var overlay = document.getElementsByClassName(\'truste_overlay\'); if (overlay && typeof (overlay[0]) != \'undefined\') overlay[0].style.display = "none";');
                $this->driver->executeScript('var overlay = document.getElementsByClassName(\'truste_box_overlay\'); if (overlay && typeof (overlay[0]) != \'undefined\') overlay[0].style.display = "none";');

                if ($this->ff53) {
                    $login->sendKeys($this->AccountFields['Login']);
                } else {
                    $mover->duration = 100000;
                    $mover->steps = 50;
                    $mover->moveToElement($login);
                    $mover->click();
                    $mover->sendKeys($login, $this->AccountFields['Login'], 10);
                }
                $pass = $this->waitForElement(WebDriverBy::xpath("//input[@id = 'password' or @id = 'edit-password' or @name=\"password\"]"), 5);

                if (!$pass) {
                    $this->saveResponse();

                    return false;
                }
                $this->logger->debug("entering password...");

                if ($this->ff53) {
                    $pass->sendKeys($this->AccountFields['Pass']);
                } else {
                    $mover->moveToElement($pass);
                    $mover->click();
                    $mover->sendKeys($pass, $this->AccountFields['Pass'], 10);
                }

                // disable
                $this->logger->debug("hide cookies popup");
                $this->driver->executeScript('var c = document.getElementById("consent_blackbar"); if (c) c.style.display = "none";');
                $this->logger->debug("hide cookies popup");
                $this->driver->executeScript('var overlay = document.getElementsByClassName(\'truste_overlay\'); if (overlay && typeof (overlay[0]) != \'undefined\') overlay[0].style.display = "none";');
                $this->driver->executeScript('var overlay = document.getElementsByClassName(\'truste_box_overlay\'); if (overlay && typeof (overlay[0]) != \'undefined\') overlay[0].style.display = "none";');

                if ($cancelButton = $this->waitForElement(WebDriverBy::xpath("//button[contains(., 'Cancel')]"), 0)) {
                    $cancelButton->click();
                }

                $this->logger->debug("click 'Sign In'");
                $this->driver->executeScript('setTimeout(function(){
                    delete document.$cdc_asdjflasutopfhvcZLmcfl_;
                    delete document.$cdc_asdjflasutopfhvcZLawlt_;
                }, 500)');
                $this->saveResponse();
                usleep(rand(100000, 500000));

                try {
                    $button = $this->waitForElement(WebDriverBy::xpath("//button[contains(@class, 'sb-frap') or @id = 'edit-submit' or contains(., 'Anmelden') or contains(., 'Sign in')]"), 0);
//                    $button->submit();
                    $button->click();
                } catch (StaleElementReferenceException $e) {
                    $this->logger->error("StaleElementReferenceException exception: " . $e->getMessage());
                    sleep(1);
                    $this->saveResponse();
                    $button = $this->waitForElement(WebDriverBy::xpath("//button[contains(@class, 'sb-frap') or @id = 'edit-submit' or contains(., 'Anmelden') or contains(., 'Sign in')]"), 0);
//                    $button->submit();
                    $button->click();
                }

                $this->logger->debug("hide cookies popup");
                $this->driver->executeScript('var overlay = document.getElementsByClassName(\'truste_overlay\'); if (overlay && typeof (overlay[0]) != \'undefined\') overlay[0].style.display = "none";');
                $this->driver->executeScript('var overlay = document.getElementsByClassName(\'truste_box_overlay\'); if (overlay && typeof (overlay[0]) != \'undefined\') overlay[0].style.display = "none";');

                $this->saveResponse();

            //$this->driver->executeScript('document.querySelector("button.sb-frap").click();');
//                $button->click();

//              $mover->moveToElement($button);
//              $mover->click();
            } else {
                $login = $this->waitForElement(WebDriverBy::xpath("//form[@id = 'accountForm']//input[@placeholder = 'Username or email' or @placeholder = 'Benutzername oder E-Mail']"));
                $this->saveResponse();

                if (!$login) {
                    return $this->callRetry();
                }

//            $login->sendKeys($this->AccountFields['Login']);
//            $mover->duration = 100000;
//            $mover->steps = 50;
                $mover->moveToElement($login);
                $mover->click();
                $mover->sendKeys($login, $this->AccountFields['Login'], 10);

                $pass = $this->waitForElement(WebDriverBy::xpath("//form[@id = 'accountForm']//input[@placeholder = 'Password' or @placeholder = 'Passwort']"));
//            $pass->sendKeys($this->AccountFields['Pass']);
                $mover->moveToElement($pass);
                $mover->click();
                $mover->sendKeys($pass, $this->AccountFields['Pass'], 10);

//            $signinButton = $this->waitForElement(WebDriverBy::xpath("//form[@id = 'accountForm']//button"), 0, true);
//            if (!$signinButton) {
//                $this->logger->error('Failed to find sign in button');
//                return false;
//            }
//            $mover->moveToElement($signinButton);
                $this->driver->executeAsyncScript('setTimeout(function(){ delete document.$cdc_asdjflasutopfhvcZLawlt_; document.getElementById("AT_SignIn_Button").click(); }, 500)');
                sleep(3);
                // $signinButton->click();
//            $mover->moveToElement($signinButton);
//            $mover->click();
                $this->saveResponse();
            }
        } catch (NoSuchElementException | UnexpectedAlertOpenException | NoSuchWindowException | NoSuchDriverException $e) {
            $this->logger->error("Exception: " . $e->getMessage());

            return $this->callRetry();
        } catch (ScriptTimeoutException $e) {
            $this->logger->error("ScriptTimeoutException exception: " . $e->getMessage());
            // retries
            if (strstr($e->getMessage(), 'timeout: Timed out receiving message from renderer')) {
                return $this->callRetry();
            }
        }

        return true;
    }

    public function Login()
    {
        $this->saveResponse();

        try {
            if ($this->AccountFields['Login2'] == 'China') {
                $startTime = time();
                $time = time() - $startTime;
                $sleep = 60;

                while ($time < $sleep) {
                    $this->logger->debug("(time() - \$startTime) = {$time} < {$sleep}");
                    /*
                    // Please select the correct image
                    if ($this->waitForElement(WebDriverBy::xpath("//div[contains(text(), 'Please select the correct image')]"), 0, true)) {
                        $this->recognizer->reportIncorrectlySolvedCAPTCHA();
                        throw new CheckRetryNeededException(3, 7, self::CAPTCHA_ERROR_MSG);
                    }
                    */
                    // The UserName/Email or Password is invalid
                    if ($message = $this->waitForElement(WebDriverBy::xpath("//div[contains(text(), 'The UserName/Email or Password is invalid')]"), 0, true)) {
                        $this->captchaReporting($this->recognizer);

                        throw new CheckException($message->getText(), ACCOUNT_INVALID_PASSWORD);
                    }
                    $this->saveResponse();
                    // Access is allowed
                    if ($this->loginSuccessful()) {
                        $this->captchaReporting($this->recognizer);

                        return true;
                    }

                    if (!$this->recognizer) {
                        $this->logger->error("something went");

                        return false;
                    }

                    if ($this->waitForElement(WebDriverBy::xpath("//p[contains(text(), 'You need to use the bound mobile phone number:')]"), 0)) {
                        $this->captchaReporting($this->recognizer);

                        $request = $this->waitForElement(WebDriverBy::xpath("//span[contains(text(), 'Request Pin')]"), 0);
                        $request->click();

                        $this->processTwoFactorChina();

                        return false;
                    }

                    // Validate Mobile Number
                    if ($message = $this->waitForElement(WebDriverBy::xpath("//h2[span[contains(text(), 'Validate Mobile Number') or contains(text(), '验证手机号码')]]"), 0, true)) {
                        $this->captchaReporting($this->recognizer);
                        $this->throwProfileUpdateMessageException();
                    }
                    sleep(1);
                    $this->saveResponse();
                    $time = time() - $startTime;

                    if ($this->attempt == 0) {
                        $this->increaseTimeLimit();
                    }
                }// while ($time < $sleep)

                // captcha was not passed
                if ($this->waitForElement(WebDriverBy::xpath("//button[@class = 'button submit full-width disabled spinning']"), 0, true)) {
                    throw new CheckRetryNeededException(3, 7, self::CAPTCHA_ERROR_MSG);
                }

                if (in_array($this->AccountFields['Login'], [
                    'songchen91',
                    'voicereason',
                    'ryan46chiang',
                    'ryan_chiang@outlook.com',
                    'axitte',
                    'askfanxiaojun@163.com',
                    'DavidWang0214',
                    'leon.du@live.cn',
                    'q489327',
                    'aizaidldj',
                    'fishkop',
                    'spiderman542@163.com',
                    'benlee999',
                    'xuedm@21cn.com',
                    'joyl@live.com',
                    'ruthshenyang@hotmail.com',
                    'xucr001@gmail.com',
                    'peterche1990',
                    'wadsmr',
                    'robblelee',
                    'beining',
                    'alvissf',
                    '13710006969',
                    'yuxiao9513@gmail.com',
                    '601054410@qq.com',
                    'litianlei',
                    'w7irene@gmail.com',
                    'dylanicious@foxmail.com',
                    'manyuet@qq.com',
                    'janlay',
                    'yusi.flora@gmail.com',
                    'billzsx',
                    '295789295',
                    'muxiaofan0602',
                    'alexanderma',
                    'stefanshih',
                    'sam_duan@outlook.com',
                    'ilovedinburgh@qq.com',
                    'zc1018',
                    'feifeicandy@163.com',
                    'bradchiu@synnex.com.tw',
                    'yyroyalty',
                    'liupowei',
                    'jy0276575',
                    'im@yanghai.net',
                    'yumingchen2@yeah.net',
                    'wulade',
                    'zhangys10',
                    'fisherlow@gmail.com',
                    '18858102111',
                    'acrschmelzer',
                    'julianyzou@yahoo.com',
                ])
            ) {
                    throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
                }

                // no auth, no errors   // AccountID: 4513650
                if (
                    $this->waitForElement(WebDriverBy::xpath("//button[@class = 'button large' and @disabled]"), 0)
                    && $this->waitForElement(WebDriverBy::xpath('//p[@class = \'apron-green\' and span[contains(text(), "Verified")]]'), 0)
                ) {
                    throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
                }

                return false;
            }// if ($this->AccountFields['Login2'] == 'China')

            if ($this->AccountFields['Login2'] == 'Japan') {
                $this->waitForElement(WebDriverBy::xpath('
                    //*[self::li or self::div][contains(@class, "serviceAndLogin")]
                    | //div[contains(@class, "alert-danger")]
                '), 10);
                $this->saveResponse();

                if ($this->http->FindSingleNode('//*[self::li or self::div][contains(@class, "serviceAndLogin")]/@class')) {
                    return true;
                }

                if ($message = $this->http->FindSingleNode('//div[contains(@class, "alert-danger")]')) {
                    $this->logger->error($message);

                    if (strstr($message, '間違ったメールアドレスもしくはパスワードが入力されました。')) {
                        throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
                    }

                    $this->DebugInfo = $message;

                    return false;
                }

                return false;
            }

            sleep(5);
            $this->saveResponse();

            if ($this->http->FindPreg("/(?:It looks like you’re browsing from outside the United States\.|Looks like this is a Starbucks U.S. account)/")) {
                $this->logger->notice("It looks like you’re browsing from outside the United States");
                $takeMeThereButton = $this->waitForElement(WebDriverBy::xpath("//a[contains(text(), 'Take me there')]"), 0, true);

                if (!$takeMeThereButton) {
                    $this->logger->error('Failed to find "Take me there" button');

                    return false;
                }
                $takeMeThereButton->click();
            }

            $startTime = time();
            $time = time() - $startTime;
            $sleep = 15;

            while ($time < $sleep) {
                $this->logger->debug("(time() - \$startTime) = {$time} < {$sleep}");
                $this->saveResponse();
                $time = time() - $startTime;

                if ($message = $this->http->FindSingleNode("//div[@class = 'validation-summary-errors']")) {
                    throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
                }
                // Thailand - Username or Password incorrect!
                if ($message = $this->http->FindSingleNode("//span[contains(text(), 'Username or Password incorrect!')]")) {
                    throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
                }

                //# Please Register A Card to Start Earning Rewards
                if ($message = $this->http->FindSingleNode("//h3[a[contains(text(), 'Register A Card')]]")) {
                    throw new CheckException("Please Register Your Card to Start Earning Rewards", ACCOUNT_PROVIDER_ERROR);
                } /*checked*/
                // Is your email still ...
                if ($message = $this->http->FindSingleNode("//div[contains(text(), 'Is your email still')]")) {
                    throw new CheckException("Starbucks Card Rewards website is asking you to update your profile, until you do so we would not be able to retrieve your account information.", ACCOUNT_PROVIDER_ERROR);
                }

                $success = $this->waitFor(function () {
                    $successTimeout = 10;

                    return
                    $this->waitForElement(WebDriverBy::xpath("
                        //a[contains(@href, '/account/signout')]
                        | //form[@id = 'accountSignoutForm']
                        | //a[contains(text(), 'Sign Out') or contains(text(), 'Abmelden')]
                        | //p[@id = 'msrMemberTrackerAriaLabelId']
                    "), $successTimeout, false)
                    || $this->waitForElement(WebDriverBy::xpath("
                        //p[contains(text(), 'rewards available')]
                        | //span[contains(@class, 'starBalance___') or @class = 'rewards__currentStars']
                        | //p[@id = 'msrMemberTrackerAriaLabelId']
                        | //*[self::h1 or self::h2]
                            [
                                (contains(@class, 'sb-heading ') or contains(@class, 'greetPerson'))
                                and not(contains(text(), 'Sign in unsuccessful'))
                                and not(contains(text(), 'Sign in or create an account'))
                                and not(contains(text(), 'Melden Sie sich an oder erstellen Sie ein Konto'))
                                and not(contains(text(), 'Melde dich an oder erstelle ein Konto'))
                                and not(contains(text(), 'Looks like this is a Starbucks '))
                                and not(contains(text(), 'Good morning.'))
                                and not(contains(text(), 'Good evening.'))
                                and not(contains(., 'JOIN STARBUCKS'))
                                and not(contains(., 'Join Starbucks'))
                            ]
                        | //div[contains(@class, 'account-profile-screen-item-title') and contains(text(), ' Rewards')]
                        | //span[contains(text(), 'Konto')]
                    "), $successTimeout);
                }, 1);
                $this->saveResponse();

                // when Login2 does not match region set in account settings
                // message will be "User market (AT) does not match current site market (DE)"
                if ($message = $this->waitForElement(WebDriverBy::xpath('//section[class="alert"]//p[contains(text(), "does not match current site market")]'), 0)) {
                    throw new CheckException($message->getText(), ACCOUNT_INVALID_PASSWORD);
                }

                // refs #18453
                if ($this->waitForElement(WebDriverBy::xpath("//input[@id = 'verificationCode']"), 0)) {
                    return $this->processTwoFactor();
                }

                if ($success) {
                    return true;
                }

                if ($err = $this->http->FindPreg('#We’re sorry — something has gone wrong on our[^<]+end.#i')) {
                    throw new CheckException($err, ACCOUNT_PROVIDER_ERROR);
                }
                // UK version
                // US version
                // The email or password you entered is not valid. Please try again.
                // Germany version - Die E-Mail-Adresse oder das Passwort ist ungültig. Bitte versuchen Sie es erneut.
                if ($message = $this->waitForElement(WebDriverBy::xpath("
                    //div[contains(normalize-space(text()), 'Sorry, we were unable to log you in. Please try again.')]
                    | //li[contains(text(), 'Sorry, we were unable to log you in. Please try again.')]
                    | //li[contains(., 'The provided email is invalid')]
                    | //li[contains(., 'Bitte geben Sie eine gültige E-Mail-Adresse ein.')]
                    | //*[self::p or self::li][contains(text(), 'The email or password you entered is not valid. Please try again.')]
                    | //*[self::p or self::li][contains(text(), 'Die E-Mail-Adresse oder das Passwort ist ungültig. Bitte versuchen Sie es erneut.')]
                    | //*[self::p or self::li][contains(text(), 'Die eingegebene E-Mail oder das Passwort sind ungültig.')]
                    | //*[self::p or self::li][contains(text(), 'Falsche E-Mail oder Passwort Kombination.')]
                    | //*[self::p or self::li][contains(text(), 'Incorrect email, password or combination.')]
                    | //*[self::p or self::li][contains(text(), 'The email address') and contains(., 'is not valid.')]
                "), 0)
            ) {
//                throw new CheckException($message->getText(), ACCOUNT_INVALID_PASSWORD);
                    throw new CheckRetryNeededException(2, 1, $message->getText(), ACCOUNT_INVALID_PASSWORD);
                }

                if ($this->AccountFields['Login2'] == 'USA'
                && ($message = $this->http->FindSingleNode('//div[contains(normalize-space(text()), "Sorry, we were unable to log you in. Please try again.")]'))
            ) {
                    throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
                }
                /*
                 * Anmeldung fehlgeschlagen.
                 * Leider konnten Sie nicht angemeldet werden. Bitte versuchen Sie es erneut.
                 */
                // Looks like this is a Starbucks U.S. account / Looks like this is a Starbucks Brasil account
                if ($message = $this->waitForElement(WebDriverBy::xpath("
                        //div[contains(text(), 'Leider konnten Sie nicht angemeldet werden. Bitte versuchen Sie es erneut.')]
                        | //*[self::h1 or self::h2][contains(text(), 'Looks like this is a Starbucks') and contains(text(), 'account')]
                        | //p[contains(text(), 'Unexpected error')]
                        | //p[contains(text(), 'An unexpected error just happened, please report or retry later')]
                        | //p[contains(text(), 'Sorry, we were unable to log you in. Please try again.')]
                        | //li[contains(., 'An error has occured while creating your account.')]
                        | //p[contains(text(), 're trying to reach is having technical difficulties and is currently unavailable.')]
                    "), 0)
                ) {
                    if (strstr($message->getText(), "Sorry, we were unable to log you in. Please try again.")) {
                        throw new CheckRetryNeededException(2, 0, $message->getText(), ACCOUNT_PROVIDER_ERROR);
                    }

                    throw new CheckException($message->getText(), ACCOUNT_PROVIDER_ERROR);
                }

                // hard code (AccountID: 3366064)
                if ($this->AccountFields['Login2'] == 'UK' && $this->AccountFields['Pass'] == '***********') {
                    throw new CheckException("The email or password you entered is not valid. Please try again.", ACCOUNT_INVALID_PASSWORD);
                }

                // hard code (AccountID: 2261225, 3414569, 4686288)
                if ($this->AccountFields['Login2'] == 'UK' && in_array($this->AccountFields['Login'], [
                    'phil.huff@frontseatdriver.co.uk',
                    'voodoomox@yahoo.com',
                    'craig.hughes1@gmail.com',
                    "dmw982@gmail.com",
                    "michael.green@live.co.uk",
                    "andrew@gebbies.plus.com",
                ]
            )) {
                    throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
                }
                // hard code
                if ($this->AccountFields['Login2'] == 'UK' && in_array($this->AccountFields['Login'], ['graham.walsh@gmail.com', 'duncan@boardman.me'])) {
                    throw new CheckException("The email or password you entered is not valid. Please try again.", ACCOUNT_INVALID_PASSWORD);
                }

                if ($this->AccountFields['Login2'] == 'Canada' && in_array($this->AccountFields['Login'], ['sunnyhanda@yahoo.com'])) {
                    throw new CheckException("The email or password you entered is not valid. Please try again.", ACCOUNT_INVALID_PASSWORD);
                }
                // Sorry, we were unable to log you in. Please try again.
                if ($this->AccountFields['Login2'] == 'UK' && $this->AccountFields['Login'] == 'sweetgirl08') {
                    throw new CheckException("Sorry, we were unable to log you in. Please try again.", ACCOUNT_INVALID_PASSWORD);
                }
                // hard code (AccountID: 3392932)
                if ($this->AccountFields['Login2'] == 'USA' && $this->AccountFields['Pass'] == '**********') {
                    throw new CheckException("Sorry, we were unable to log you in. Please try again.", ACCOUNT_INVALID_PASSWORD);
                }

                sleep(1);
                $this->saveResponse();
                $this->doRetry();
            }// while ($time < $sleep)

            // retries
            if ($this->driver->getCurrentURL() == "http://www.starbucks." . $this->domain . "/"
            || $this->driver->getCurrentURL() == "https://app.starbucks." . $this->domain . "/") {
                $this->logger->error("Not logged in, still on main page");
                $this->markProxyAsInvalid();

                throw new CheckRetryNeededException(3);
            }
        } catch (NoSuchDriverException $e) {
            $this->logger->error("Exception: " . $e->getMessage());

            throw new CheckRetryNeededException(3, 1);
        }

        return $this->checkErrors();
    }

    public function ProcessStep($step)
    {
        if ($this->isNewSession()) {
            return $this->LoadLoginForm() && $this->Login();
        }

        switch ($step) {
            case "processTwoFactor":
                return $this->processTwoFactor();

                break;

            case "processTwoFactorChina":
                return $this->processTwoFactorChina();

                break;
        }

        return false;
    }

    public function Parse()
    {
        $this->logger->notice('Region => ' . $this->AccountFields['Login2']);

        // refs #6150
        if ($this->AccountFields['Login2'] == 'China') {
            $this->parseChina();

            return;
        }
        // refs #20209
        if ($this->AccountFields['Login2'] == 'Japan') {
            $this->parseJapan();

            return;
        }

        try {
            $this->logger->debug("[Current URL]: {$this->http->currentUrl()}");
        } catch (UnknownServerException $e) {
            $this->logger->error("exception: " . $e->getMessage());
            $this->callRetry();
        }

        if (in_array($this->AccountFields['Login2'], ['UK', 'Germany', 'Ireland'])) {
            // Balance - Earned stars
            $this->SetBalance($this->http->FindSingleNode("//p[contains(@class, 'ProgressText_number')]"));
            // Name
            $this->SetProperty("Name", beautifulName($this->http->FindSingleNode("//h2[contains(@class, '_greetPerson_')]", null, true, "/,\s*([^!]+)/")));

            return;
        }

        if ($this->useParseUsa()) {
            $totalFreeDrinks = $this->http->FindSingleNode("//p[contains(text(),'free drink or food Rewards')]", null, false, '/^\s*(\d+) free drink/');
            $this->ParseUsa($totalFreeDrinks);

            return;
        }
        /*
         * You have a Starbucks account in a different country.
         * For the best experience, switch over to the Starbucks site where you created your account
         */
        if ($message = $this->waitForElement(WebDriverBy::xpath('//div[contains(text(), "You have a Starbucks account in a different country. For the best experience, switch over to the Starbucks site where you created your account.")]'), 0)) {
            $this->SetWarning($message->getText());
        }
        $this->saveResponse();
        // use curl
        $this->browser = $this->http;
        $this->parseWithCurl();
        $this->logger->notice("Current proxy -> " . $this->browser->GetProxy());

        $this->browser->GetURL("https://www.starbucks." . $this->domain . "/account/rewards/my-rewards");
        // Balance - Earned stars
        $this->SetBalance($this->browser->FindSingleNode("//span[@class = 'balance-text' and position() = 1]"));
        // Level
        if ($this->browser->FindSingleNode('//div[@class = "progress-stars-description"]/text()[last()]', null, true, "/until Gold level/")) {
            $this->SetProperty("EliteLevel", 'Green');
        } elseif ($this->browser->FindSingleNode('//div[@class = \'progress-stars-deadline\' and not(contains(text(), \'to earn\'))]', null, true, "/to stay gold/ims")) {
            $this->SetProperty("EliteLevel", 'Gold');
        }
//        $this->SetProperty("EliteLevel", $this->browser->FindSingleNode("//a[contains(text(), 'My Rewards Level:')]", null, true, "/:\s*([^<]+)/"));
        // Stars until Gold Level
        $this->SetProperty("NeededStarsForNextLevel", $this->browser->FindPreg("/Earn (\d+) Stars? by \d+ [A-Z]+ to go to [A-Z]+/ims"));
        // Earn \d+ Stars by <date> to stay <level>
        // Sammle \d+ Sterne bis zum <date> to stay <level>
        $this->SetProperty("EliteLevelValidTill", $this->browser->FindSingleNode("//div[@class = 'progress-stars-deadline' and not(contains(text(), 'to earn'))]", null, true, "/(?:by|zum)\s*([\/\d]+ [A-Za-z]+)/"));

        if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR) {
            if ($message = $this->http->FindSingleNode('//p[contains(text(), "The website that you\'re trying to reach is having technical difficulties and is currently unavailable.")]')) {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }
        }

        $this->browser->GetURL("https://www.starbucks." . $this->domain . "/account/personal");
        // Name
        $this->SetProperty("Name", beautifulName(
            $this->browser->FindSingleNode("//input[@name = 'firstName']/@value") . " " . $this->browser->FindSingleNode("//input[@name = 'lastName']/@value"))
        );

        /*
        $this->browser->GetURL("https://www.starbucks.".$this->domain."/account/history");
        $this->browser->GetURL("https://www.starbucks.".$this->domain."/api/v1/cards/history/10/0");
        */

        /*
        // Name
        $this->SetProperty("Name", beautifulName($this->browser->FindSingleNode("//span[@class = 'profileBanner__memberName']", null, true, "/Hi\,?\s+([^<\.]+)/ims")));
        // Balance - Earned stars
        if (!$this->SetBalance($this->browser->FindSingleNode("//span[@class = 'rewards__currentStars']"))) {
            // Add a Card to your account to start earning rewards
            if ($this->browser->FindPreg("/(?:Add a card to your account to protect your balance, .+, and earn Stars with My Starbucks Rewards\.|Register and pay with a Starbucks Card to earn free drinks and food\.|Registrieren Sie eine Starbucks Card unter Ihrem Konto um bei jedem Einkauf in allen teilnehmenden Coffee Houses Sterne zu sammeln oder Ihre Karte online wieder aufzuladen|F&#252;gen Sie Ihrem Konto eine Karte hinzu, um Ihr Guthaben zu sch&#252;tzen, Ihre Karte aufzuladen und Sterne mit My Starbucks Rewards zu sammeln\.)/ims")
                || $this->browser->FindPreg("/This card belongs to someone else\./")
                // AccountID: 2757077, 2131617
                || $this->browser->FindPreg("/<h1 class=\"region[^\"]+\">We\’re sorry – we can\’t find the page you’re looking for\. <\/h1>/")
                || $this->browser->FindPreg("/<h1 class=\"region[^\"]+\">Es tut uns leid, aber wir können die von Ihnen gesuchte Seite nicht finden\. <\/h1>/")
                // todo: bug on the website, balance not found
                || (!empty($this->Properties['Name'])
                    && ($this->browser->FindSingleNode("//h1[contains(text(), 'Another year of Gold!')]")
                        || $this->browser->FindSingleNode("//h1[contains(text(), 'Your Starbucks Card balance is under')]")))
                // AccountID: 3819472, 3940848, 3235003, 630985
                || ($this->AccountFields['Login2'] == 'Canada' && in_array($this->AccountFields['Login'], ['dalkowsk@yahoo.com', 'dwyiu@yahoo.com', 'chelljohn', 'ckchee', 'trudipye'])))
                $this->SetBalanceNA();
            /*
             * Warning
             * This is not your country's rewards program.
             * To see your program information please login to your reward program site.
             * /
            elseif ($message = $this->browser->FindSingleNode('//p[contains(text(), "This is not your country\'s rewards program") or contains(text(), "Dies ist nicht das Reward-Programm für Ihr Land.")]')) {
                $this->SetWarning($message);
            }
            /*
             * You have a Starbucks account in a different country.
             * For the best experience, switch over to the Starbucks site where you created your account
             * /
            elseif ($message = $this->browser->FindSingleNode('//div[contains(text(), "You have a Starbucks account in a different country. For the best experience, switch over to the Starbucks site where you created your account.") or contains(text(), "Sie haben ein Starbucks Konto in einem anderen Land. Das beste Nutzungserlebnis erhalten Sie, wenn Sie zur Starbucks-Website wechseln, auf der Sie Ihr Konto erstellt haben.")]')) {
                $this->SetWarning($message);
            }
            else
                $this->SetWarning($this->http->FindSingleNode('//span[contains(text(), "Rewards information is not available at this time.")]'));
        }
        // Stars until your next Reward
        $this->SetProperty("StarsNeeded", $this->browser->FindPreg("/(\d+) Stars? until your next Reward/ims"));
        if (empty($this->Properties['StarsNeeded']))
            $this->SetProperty("StarsNeeded", $this->browser->FindSingleNode("//p[@class = 'stars_until']", null, true, "/(\d+)\s*(?:Stars? until\s*next|Sterne bis zum nächsten)\s*Reward/ims"));
        // Stars until Gold Level
        $this->SetProperty("NeededStarsForNextLevel", $this->browser->FindPreg("/(\d+) Stars? until [A-Z]+ Level/ims"));
        // Rewards Level
        if (in_array($this->AccountFields['Login2'], array('UK', 'Germany', 'Ireland'))) {
            // Name
            $this->SetProperty("Name", beautifulName($this->http->FindSingleNode("//h2[@class = 'welcome-message']", null, true, "/H.llo\,?\s+([^<\,]+)/ims")));
            // Balance - Earned stars
            if (!$this->SetBalance($this->http->FindSingleNode("//span[@class= 'stars_achieved']"))) {
                // Add a Card to your account to start earning rewards
                if ($this->browser->FindPreg("/(?:Add a card to your account to protect your balance, .+, and earn Stars with My Starbucks Rewards\.|Register and pay with a Starbucks Card to earn free drinks and food\.|Registrieren Sie eine Starbucks Card unter Ihrem Konto um bei jedem Einkauf in allen teilnehmenden Coffee Houses Sterne zu sammeln oder Ihre Karte online wieder aufzuladen|F&#252;gen Sie Ihrem Konto eine Karte hinzu, um Ihr Guthaben zu sch&#252;tzen, Ihre Karte aufzuladen und Sterne mit My Starbucks Rewards zu sammeln\.)/ims")
                    || $this->browser->FindPreg("/This card belongs to someone else\./"))
                    $this->SetBalanceNA();
            }// if (!$this->SetBalance($this->browser->FindSingleNode("//span[@class = 'rewards__currentStars']")))

            $this->SetProperty("EliteLevel", beautifulName($this->browser->FindSingleNode("//p[@class = 'tier_level_status__text']/span")));
            if (isset($this->Properties['EliteLevel'])) {
                $this->Properties['EliteLevel'] = preg_replace("/\s*level/ims", "", $this->Properties['EliteLevel']);
                if (preg_match('/willkommen/ims', $this->Properties['EliteLevel']))
                    $this->Properties['EliteLevel'] = 'Welcome';
                if (preg_match('/grüne/ims', $this->Properties['EliteLevel']))
                    $this->Properties['EliteLevel'] = 'Green';
                if (preg_match('/Goldstufe/ims', $this->Properties['EliteLevel']))
                    $this->Properties['EliteLevel'] = 'Gold';
            }// if (isset($this->Properties['EliteLevel']))

            // Reward Available
            $freeDrinksExpirationInfo = $this->http->FindSingleNode('//p[contains(@class, "rewards_expiration") and @data-uia = "ShowBoxAvailableRewardsInfoExpiration"]');
            if ($freeDrinksExpirationInfo) {
                $freeDrinksExpiration = null;
                if ($this->AccountFields['Login2'] == 'Germany') {
                    $freeDrinksExpiration = $this->http->FindPreg('/(\d+\.\d+\.\d{4})/', false, $freeDrinksExpirationInfo);
                } else {
                    $freeDrinksExpiration = $this->http->FindPreg('/(\d+\/\d+\/\d{4})/', false, $freeDrinksExpirationInfo);
                    $freeDrinksExpiration = $this->ModifyDateFormat($freeDrinksExpiration);
                }
                $freeDrinksExpiration = strtotime($freeDrinksExpiration);
                if (!$freeDrinksExpiration) {
                    $this->sendNotification('check free drinks // MI');
                }
            }

            $this->browser->GetURL("https://www.starbucks.".$this->domain."/account/rewards");
            // We’re sorry — something has gone wrong on our end.
            if (preg_match("/www\.starbucks\.com\/static\/error\/index\.html/ims", $this->browser->currentUrl()))
                throw new CheckException("We’re sorry — something has gone wrong on our end.", ACCOUNT_PROVIDER_ERROR);

            if (empty($this->Properties['EliteLevel'])) {
                $this->SetProperty("EliteLevel", $this->browser->FindSingleNode("//a[contains(text(), 'My Rewards Level:')]", null, true, "/:\s*([^<]+)/"));
                if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR)
                    $this->SetBalance($this->browser->FindPreg("/flashvars\.numStars = \"(\d+)\"/"));
                if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR && $this->browser->currentUrl() == 'http://www.starbucks.'.$this->domain.'/error/section-unavailable')
                    // Unfortunately rewards page is currently unavailable.
                    if ($this->browser->FindPreg("/(?:Leider ist diese Seite zurzeit nicht verfügbar\.|We’re sorry — this page isn’t available right now\.)/"))
                        throw new CheckException("Unfortunately rewards page is currently unavailable.", ACCOUNT_PROVIDER_ERROR);/*review* /
            }// if (empty($this->Properties['EliteLevel']))

            // Expiration Date  // refs #6340, 3979 <- last comment #37

            if (isset($this->Properties['EliteLevel'])) {
                $this->logger->notice(">>> Expiration Date");

                // https://redmine.awardwallet.com/issues/6340#note-8
                $this->SetProperty("AccountExpirationWarning", 'Starbucks state the following on their website: <a target="_blank" href="https://customerservice.starbucks.co.uk/app/answers/detail/a_id/3408">"If you are Green level, your stars towards your free drinks will remain on your account as long as you have any activity in the past two years. However, you have 1 year to achieve 50 stars and Gold level - after 1 year your Star count towards Gold will be reset if you have not achieved Gold status.
If you are Gold level and you do not qualify for the Gold level again by your anniversary date (12 months after the date you qualified for Gold level), you will revert to the Green level and your Star count will reset to zero".</a>
<br/>
<br/>
According to these statements, we calculated the expiration date applying the following logic:
<br/>
<br/>
Green level:
Your stars don\'t expire as long as you maintain your Green Level. So we take into consideration when your level was achieved and add one year.
<br/>
<br/>
Gold level:
If you have a Gold level account your stars may expire on the anniversary/transition date. In order to avoid such situation you should re-qualify each subsequent 12 months by earning at least 50 Stars. If you earn less than 50 Stars, you will return to the Green level and your Star count will be reset to zero on your anniversary date.');

                $levelSince = $this->browser->FindPreg("/customer_level_since_date:\s*\'([^\']+)\'/ims");
                $levelSinceUnixTime = strtotime($levelSince);
                // Level Since
                $this->SetProperty("LevelSince", $levelSince);
                $this->logger->debug("level since: {$levelSince} / {$levelSinceUnixTime}");
                switch (strtolower($this->Properties['EliteLevel'])) {
                    case "welcome":
                        $this->logger->notice("Status -> welcome");
                        $exp = $this->getExpirationDate($levelSinceUnixTime);
                        $this->SetExpirationDate($exp);
                        break;
                    case "green":
                        $this->logger->notice("Expiration Date for Green Level");
                        $exp = $this->browser->FindPreg("/(?:Green Level Since|Grüne Stufe seit):([^<]+)/ims");
                        // USA
                        if (!isset($exp) && isset($levelSince))
                            $exp = $levelSince;
                        $this->logger->debug("Date: {$exp}");

                        if (!isset($exp)) {
                            $this->ArchiveLogs = true;
                            if ($this->browser->Response['code'] == 200 && !stristr($this->http->currentUrl(), 'error/section-unavailable'))
                                $this->sendNotification("Starbucks - region {$this->AccountFields['Login2']}. Expiration Date for Green Level not found");
                        }
                        if (in_array($this->AccountFields['Login2'], array('UK', 'Canada', 'Ireland')))
                            $exp = $this->ModifyDateFormat($exp, '/', true);
                        if (isset($exp) && strtotime($exp)) {
                            $exp = strtotime(date("m/d/".date('Y') , strtotime($exp)));
                            $this->browser->Log("Exp: ".date("m/d/Y", $exp));
                            // Next year
                            if ($exp < time())
                                $exp = strtotime("+1 year", $exp);
                            $this->logger->debug("Exp: ".date("m/d/Y", $exp));
                            $this->SetExpirationDate($exp);
                        }// if (isset($exp) && strtotime($exp))
                        break;
                    case "gold":
                        $this->logger->notice("Expiration Date for Gold Level");
                        // UK, Germany, Canada
                        $exp = $this->browser->FindPreg("/(?:Gold Level status is valid until|Ihr Goldstatus gilt bis|Gold Level status is good until|Dein Goldstatus gilt bis):([^<]+)/ims");
                        $this->SetProperty("EliteLevelValidTill", $exp);
                        // USA
                        if (!isset($exp) && isset($levelSince))
                            $exp = $levelSince;
                        $this->logger->debug("Date: {$exp}");

                        // notifications
                        if (!isset($exp)) {
                            $this->ArchiveLogs = true;
                            if ($this->browser->Response['code'] == 200 && !stristr($this->http->currentUrl(), 'error/section-unavailable'))
                                $this->sendNotification("Starbucks - region {$this->AccountFields['Login2']}. Expiration Date for Gold Level not found");
                        }
                        // Stars to renew Gold Level
                        $this->SetProperty("StarsToRenewGoldLevel", $this->browser->FindPreg("/Earn\s*([^<]+)\s*Stars?\s*to\s*enjoy\s*Gold\s*Level\s*Status\s*for\s*another\s*year/ims"));
                        if (in_array($this->AccountFields['Login2'], array('UK', 'Canada', 'Ireland')))
                            $exp = $this->ModifyDateFormat($exp, '/', true);

                        if (isset($exp) && strtotime($exp))
                            $this->SetExpirationDate(strtotime($exp));
                        break;
                    case "status":
                        $this->logger->debug("Unset Status: {$this->Properties['EliteLevel']}");
                        unset($this->Properties['EliteLevel']);
                        break;
                    default:
                        $this->logger->notice("Unknown Status: {$this->Properties['EliteLevel']}");
                        break;
                }// switch (strtolower($this->Properties['EliteLevel']))
            }// if (isset($this->Properties['EliteLevel']) && strtolower($this->Properties['EliteLevel']))

            // notifications
            if (!empty($this->Properties['EliteLevel']) && !in_array($this->Properties['EliteLevel'], array('Green', 'Welcome', 'Gold')))
                $this->sendNotification("Starbucks - region {$this->AccountFields['Login2']}. New Status -> {$this->Properties['EliteLevel']}");

            # Sub Accounts - Total Free Drinks Earned   // refs #4320
            $totalFreeDrinks = $this->browser->FindPreg("/numDrinks\s*=\s*\"([^\"]+)/ims");
            $this->logger->debug("Total Free Drinks Earned: {$totalFreeDrinks}");
        }// if (in_array($this->AccountFields['Login2'], array('UK', 'Germany', 'Ireland')))
        else {// USA, Canada
            $this->SetProperty("EliteLevel", beautifulName($this->browser->FindSingleNode("//span[@class = 'rewards__levelText']")));

            // subAccount - Free drink or food available (...)
            $totalFreeDrinks = $this->browser->FindPreg("/Free drink or food available \(([^\)]+)/ims");
            $this->logger->debug("Total Free drink or food available: {$totalFreeDrinks}");

            $this->browser->GetURL("https://www.starbucks.".$this->domain."/account/history");
            // <level> through <date>
            // Earn \d+ Stars by <date> to stay <level>
            $this->SetProperty("EliteLevelValidTill", $this->browser->FindSingleNode("//span[@class = 'rewards__statusSummary' and not(contains(text(), 'to earn'))]", null, true, "/(?:by|through)\s*([\/\d]+)/"));
            // USA
            $expNodes = $this->browser->XPath->query("//div[contains(@class, 'expiringStars__row')]");
            $this->logger->debug("Total {$expNodes->length} exp nodes were found");
            $expNodesTotal = 0;
            if ($expNodes->length == 0) {
                $exp = $this->browser->FindSingleNode('//p[contains(text(), "t expire until your yearly anniversary on")]', null, true, "/anniversary \s*on\s*([^<\.]+)/ims");
                if (in_array($this->AccountFields['Login2'], array('UK', 'Germany', 'Canada', 'Ireland')))
                    $exp = $this->ModifyDateFormat($exp);
                if ($exp = strtotime($exp))
                    $this->SetExpirationDate($exp);
                unset($exp);
            }
            foreach ($expNodes as $expNode) {
                $exp = $this->browser->FindSingleNode("div[contains(@class, 'expiringStars__month')]", $expNode);
                $expBalance = $this->browser->FindSingleNode("div[contains(@class, 'expiringStars__amountExpiring')]", $expNode);
                $this->logger->debug("Exp: $exp / $expBalance");
                if ($expBalance > 0) {
                    $exp = strtotime($exp);
                    $this->logger->debug("Exp: ".date("m/d/Y", $exp));
                    // Next year
                    if ($exp < time())
                        $exp = strtotime("+1 year", $exp);
                    $this->logger->debug("Exp: ".date("m/d/Y", $exp));
                    $this->SetExpirationDate($exp);
                    // Expiring balance
                    $this->SetProperty("ExpiringBalance", $expBalance);
                    break;
                }// if ($expBalance > 0)
                else
                    $expNodesTotal++;
            }// foreach ($expNodes as $expNode)

            // refs #3979 https://redmine.awardwallet.com/issues/3979#note-40
            if (!isset($this->Properties["AccountExpirationDate"])) {
                if ($expNodesTotal == 8 && !$this->browser->FindSingleNode('//p[contains(text(), "t expire until your yearly anniversary on")]', null, true, "/anniversary \s*on\s*([^<\.]+)/ims"))
                    $this->ClearExpirationDate();
//                if ($this->AccountFields['Login2'] == 'Canada')
//                    $link = 'https://www.starbucks.ca/account/history';
//                else
//                    $link = 'https://www.starbucks.com/account/history';
//                $this->SetProperty("AccountExpirationWarning", "We will display your expiration date and expiring balance as they appear on the <a target='_blank' href='{$link}'>Starbucks</a> website.");
            }// if (!isset($this->Properties["AccountExpirationDate"]))
        }
        if (isset($this->Properties['EliteLevel'])) {
            $this->Properties['EliteLevel'] = preg_replace("/\s*level/ims", "", $this->Properties['EliteLevel']);
            if (preg_match('/willkommen/ims', $this->Properties['EliteLevel']))
                $this->Properties['EliteLevel'] = 'Welcome';
            if (preg_match('/grüne/ims', $this->Properties['EliteLevel']))
                $this->Properties['EliteLevel'] = 'Green';
            if (preg_match('/Goldstufe/ims', $this->Properties['EliteLevel']))
                $this->Properties['EliteLevel'] = 'Gold';
        }// if (isset($this->Properties['EliteLevel']))

        unset($exp);
        preg_match_all("/(\{\"VoucherType\"[^\}]+\})/ims", $this->browser->Response['body'], $expDates);
//        $this->logger->debug(var_export($expDates, true), ["pre" => true]);
        if (isset($expDates[1])) {
            $rows = array_unique($expDates[1]);
            $this->logger->debug(var_export($rows, true), ["pre" => true]);
            foreach ($rows as $row) {
                $row = $this->http->JsonLog($row, true, true);
                if (isset($row['Status'], $row['ExpirationDate']) && $row['Status'] == 'Available') {
                    if (isset($row['VoucherType']) && $row['VoucherType'] == 'MSREarnCoupon'
                        && (!isset($exp) || strtotime($row['ExpirationDate']) < $exp)
                        // if Status == 'Available', but ExpirationDate in the past, refs #7974
                        && strtotime($row['ExpirationDate']) > time()) {
                        $exp = strtotime($row['ExpirationDate']);
                    }
                    // refs #10915
                    elseif (isset($row['VoucherType']) && $row['VoucherType'] == 'MSRPromotionalCoupon') {
                        $this->AddSubAccount(array(
                            'Code' => "starbucksPromotionalCoupon".$row['CouponCode'],
                            'DisplayName' => preg_replace("/^Code\s*\d+\-\s*
        /
        ", "", $row['Name']),
                            'Card' => $row['CouponCode'],
                            'Balance' => null,
                            'ExpirationDate' => strtotime($row['ExpirationDate']),
                        ));
                    }// elseif (isset($row['VoucherType']) && $row['VoucherType'] == 'MSRPromotionalCoupon')
                }// if (isset($row['Status'], $row['ExpirationDate']) && $row['Status'] == 'Available')
            }// foreach ($rows as $row)
        }// if (isset($expDates[1]))

        if (isset($totalFreeDrinks) && $totalFreeDrinks > 0) {
            $subAccounts = array(
                'Code' => "starbucksTotalFreeDrinksEarned",
                'DisplayName' => "Total Free Drinks Earned",
                'Balance' => $totalFreeDrinks,
            );
            // Expiration date
            if (isset($freeDrinksExpiration)) {
                $subAccounts['ExpirationDate'] = $freeDrinksExpiration;
            }
            if (isset($subAccounts)) {
                $this->SetProperty("CombineSubAccounts", false);
                // Set SubAccounts Properties
                $this->AddSubAccount($subAccounts);
            }
        }
        */

        $this->parseCardsEurope();
    }

    public function Cleanup()
    {
        $this->getMemcachedStarbucks()->delete("starbucks_lock_" . $this->port);
        parent::Cleanup();
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);
        $success = $this->waitForElement(WebDriverBy::xpath("//span[contains(text(), 'Welcome to My Starbucks Rewards') or contains(text(), 'Dashboard')]"), 0);
        $this->saveResponse();

        if ($success) {
            return true;
        }

        return false;
    }

    private function getMemcachedStarbucks()
    {
        $cache = new Memcached('sb_' . getmypid());

        if (count($cache->getServerList()) == 0) {
            $cache->addServer(MEMCACHED_HOST, 11211);
            $cache->setOption(Memcached::OPT_RECV_TIMEOUT, 500);
            $cache->setOption(Memcached::OPT_SEND_TIMEOUT, 500);
            $cache->setOption(Memcached::OPT_CONNECT_TIMEOUT, 500);
        }

        return $cache;
    }

    private function checkRegionSelection($region)
    {
        if (!in_array($region, array_flip($this->regionOptions)) || empty($region)) {
            $region = 'USA';
        }

        return $region;
    }

    private function setDomain()
    {
        $this->logger->notice(__METHOD__);
        $this->AccountFields['Login2'] = $this->checkRegionSelection($this->AccountFields['Login2']);
        $this->logger->notice('Region => ' . $this->AccountFields['Login2']);

        switch ($this->AccountFields['Login2']) {
            case 'China':
            case 'Japan':
                break;

            case 'UK':
                $this->domain = 'co.uk';

                break;

            case 'Germany':
                $this->domain = 'de';

                break;

            case 'Canada':
                $this->domain = 'ca';

                break;

            case 'Ireland':
                $this->domain = 'ie';

                break;

            case 'Spain':
                $this->domain = 'es';

                break;

            case 'Switzerland':
                $this->domain = 'ch';

                break;

            case 'USA':
            default:
                $this->domain = 'com';

                break;
        }
    }

    private function callRetry()
    {
        $this->logger->notice(__METHOD__);

        try {
            $this->saveResponse();
        } catch (NoSuchElementException | UnexpectedAlertOpenException | NoSuchWindowException | NoSuchDriverException $e) {
            $this->logger->error("Exception: " . $e->getMessage());
        }

        if ((ConfigValue(CONFIG_SITE_STATE) !== SITE_STATE_DEBUG)) {
            if ($this->http->FindSingleNode("//h1[contains(text(), 'The proxy server is refusing connections')]")) {
                $this->markProxyAsInvalid();
            }

            throw new CheckRetryNeededException(3, 7);
        }

        return false;
    }

    private function keyPresses($s)
    {
        $result = [];

        for ($n = 0; $n < strlen($s); $n++) {
            $char = substr($s, $n, 1);
            $result[] = 'key "' . $char . '"';

            if ($n > 0 && $n < (strlen($s) - 1)) {
                $result[] = "sleep " . rand(245, 298);
            }
        }

        return implode("\n", $result);
    }

    private function checkErrors()
    {
        switch ($this->AccountFields['Login2']) {
            case 'China':
                // maintenance
                if ($message = $this->http->FindSingleNode("
                        //p[contains(text(), 'Dear users, please be advised that My Starbucks Rewards section of the Starbucks China website is now on maintenance')]
                        | //p[contains(text(), 'Dear users, please be advised that Starbucks Rewards section of the Starbucks China website is now on maintenance')]
                    ")
                ) {
                    throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
                }

                break;

            case 'Japan':
                break;

            default:
                if ($message = $this->http->FindSingleNode("//h1[contains(text(), 'something has gone wrong on')]")) {
                    throw new CheckException("Site is temporarily down. Please try to access it later.", ACCOUNT_PROVIDER_ERROR);
                }
                // Bear with us. The page you’re trying to reach is currently down for maintenance.
                if ($message = $this->http->FindSingleNode("//h1[contains(text(), 'Bear with us. The page you’re trying to reach is currently down for maintenance.')]")) {
                    throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
                }
                // Maintenance
                if ($message = $this->http->FindSingleNode("//h1[contains(text(), 'our website is out taking a coffee break')]")) {
                    throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
                }
                //# Service Unavailable
                if ($message = $this->http->FindSingleNode("//h2[contains(text(), 'Service Unavailable')]")) {
                    throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
                }

                if ($this->http->FindPreg("/static\/unavailable\/section\.html/ims", false, $this->http->currentUrl())) {
                    throw new CheckException("www.starbucks.com is currently unavailable. We are working to resolve the issue
                     as quickly as possible and apologize for any inconvenience.", ACCOUNT_PROVIDER_ERROR); /*checked*/
                }
                //# The service is unavailable
                if ($message = $this->http->FindSingleNode("//body[contains(text(), 'The service is unavailable')]")) {
                    throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
                }
                // Server Error in '/' Application
                if ($this->http->FindSingleNode('//h1[contains(text(), "Server Error")]')
                    || $this->http->FindPreg("/(Server Error in '\/' Application\.)/ims")
                    // Service Temporarily Unavailable
                    || $this->http->FindPreg("/(Service Temporarily Unavailable)/ims")
                    || $this->http->FindPreg('#Service\s+Unavailable#')
                    // We’re sorry — something has gone wrong on our end.
                    || $this->http->currentUrl() == 'http://www.starbucks.com/static/error/index.html') {
                    throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
                }

                if ($this->driver->getCurrentURL() == "http://www.starbucks." . $this->domain . "/account/signin"
                    || $this->driver->getCurrentURL() == "https://www.starbucks." . $this->domain . "/account/signin?ReturnUrl=https%3A%2F%2Fapp.starbucks." . $this->domain . "%2F") {
                    $this->logger->error("Not logged in, still on main page");
                    $this->markProxyAsInvalid();

                    return $this->callRetry();
                }
        }

        return false;
    }

    /**
     * Take screenshot of selected element and return path to it on success or false otherwise.
     *
     * @param RemoteWebElement $elem Element which should be screenshoted
     *
     * @return string|false
     */
    private function takeScreenshotOfElement(RemoteWebElement $elem, $selenium = null, array $offset = ['x' => 0, 'y' => 0])
    {
        $this->logger->notice(__METHOD__);

        if (!$elem) {
            return false;
        }

        if (!$selenium) {
            $selenium = $this;
        }
        $time = getmypid() . "-" . microtime(true);
        $path = '/tmp/seleniumPageScreenshot-' . $time . '.png';
        $selenium->driver->takeScreenshot($path);
        $img = imagecreatefrompng($path);
        unlink($path);

        if (!$img) {
            return false;
        }
        $rect = [
            'x'      => $elem->getLocation()->getX() + $offset['x'],
            'y'      => $elem->getLocation()->getY() + $offset['y'],
            'width'  => $elem->getSize()->getWidth(),
            'height' => $elem->getSize()->getHeight(),
        ];
        $cropped = imagecrop($img, $rect);

        if (!$cropped) {
            return false;
        }
        $path = '/tmp/seleniumElemScreenshot-' . $time . '.jpg';
        $status = imagejpeg($cropped, $path);

        if (!$status) {
            return false;
        }
        $this->logger->info('screenshot taken');

        return $path;
    }

    private function solveCoordinatesCaptcha(RemoteWebElement $elem, array $params, array $iframeCoords)
    {
        $this->logger->notice(__METHOD__);
        $this->recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_RUCAPTCHA);
        $this->recognizer->RecognizeTimeout = 120;

        if (!$elem) {
            $this->logger->error('Cannot take screenshot of an empty element');

            return false;
        }

        try {
            // $pathToScreenshot = $this->takeScreenshotOfElement($elem, null, $iframeCoords); // chrome 66, 84
            $pathToScreenshot = $this->takeScreenshotOfElement($elem, null, $iframeCoords); // firefox 59
        } catch (Throwable $e) {
            $this->logger->error("Throwable exception: " . $e->getMessage());

            return false;
        }
        $this->logger->debug('Path to captcha screenshot ' . $pathToScreenshot);

        try {
            $text = $this->recognizer->recognizeFile($pathToScreenshot, $params);
        } catch (CaptchaException $e) {
            $this->logger->error("CaptchaException: {$e->getMessage()}");

            if ($e->getMessage() === 'server returned error: ERROR_CAPTCHA_UNSOLVABLE') {
                // almost always solvable
                $this->recognizer->reportIncorrectlySolvedCAPTCHA();

                throw new CheckRetryNeededException(3, 0, self::CAPTCHA_ERROR_MSG);
            }

            if (
                strstr($e->getMessage(), 'CURL returned error: Operation timed out after ')
                || strstr($e->getMessage(), 'timelimit (120) hit')
                || strstr($e->getMessage(), 'CURL returned error: Failed to connect to rucaptcha.com port 80')
            ) {
                throw new CheckRetryNeededException(3, 0, self::CAPTCHA_ERROR_MSG);
            } else {
                throw $e;
            }
        } finally {
            unlink($pathToScreenshot);
        }

        $coords = $this->parseCoordinates($text);

        return $coords;
    }

    private function solveSlideCaptcha(array $iframeCoords)
    {
        $this->logger->notice(__METHOD__);
        $mover = new MouseMover($this->driver);
        $mover->logger = $this->logger;
        $mover->steps = 25;
        $mover->enableCursor();
        $mouse = $this->driver->getMouse();

        try {
            $captchaElem = $this->waitForElement(WebDriverBy::id('slideBg'), 20);
        } catch (NoSuchElementException $e) {
            $this->logger->error("NoSuchElementException: {$e->getMessage()}");

            throw new CheckRetryNeededException(3, 0);
        }

        // slider btn
        $slider = $this->waitForElement(WebDriverBy::className('tc-slider-normal'), 20);
        $this->saveResponse();

        if (!$captchaElem || !$slider) {
            $this->logger->error("something went wrong");

            return false;
        }
        $captchaCoords = ['x' => $captchaElem->getLocation()->getX(), 'y' => $captchaElem->getLocation()->getY()];
        $this->logger->info('=captchaCoords:');
        $this->logger->info(var_export($captchaCoords, true), ['pre' => true]);

        $params = [
            'coordinatescaptcha' => '1',
            'textinstructions'   => 'Click on the center of the dark puzzle / Кликните на центр темного паззла',
        ];
        $targetRel = $this->solveCoordinatesCaptcha($captchaElem, $params, $iframeCoords);

        if (!$targetRel) {
            $this->logger->error("something went wrong");

            return false;
        }
        $this->logger->info('=targetCoords:');
        $this->logger->info(var_export($targetRel, true), ['pre' => true]);
        $targetRel = end($targetRel);
        $deltaX = $targetRel['x'];
        $deltaX -= 31; // half slider length
        $deltaY = $targetRel['y'];

        $targetAbs = [
            'x' => $captchaCoords['x'] + $deltaX,
            'y' => $captchaCoords['y'] + $deltaY,
        ];
        $this->logger->info('=targetAbs:');
        $this->logger->info(var_export($targetAbs, true), ['pre' => true]);

        foreach ([0, +5, -5] as $i => $dx) { // offsets
            $try = $i + 1;
            $this->logger->info("inner try = {$try}, slide dx = {$dx}");
            $tryTargetAbs = ['x' => $targetAbs['x'] + $dx, 'y' => $targetAbs['y']];
            $this->logger->info('absolute slide try');
            $success = $this->slideTry($mouse, $mover, $tryTargetAbs);
            $this->logger->info('save response');

            try {
                $this->saveResponse();
            } catch (UnknownServerException $e) {
                $this->logger->error($e->getMessage());
            }

            if ($success) {
                return true;
            }

            if ($this->waitForElement(WebDriverBy::xpath('//p[contains(text(), "Please complete the security question")]'), 0)) {
                $this->captchaReporting($this->recognizer);

                return true;
            }

            if ($try == 3 && $this->waitForElement(WebDriverBy::xpath('//p[contains(text(), "请控制拼图块对齐缺口")]'), 0)) {
                $this->captchaReporting($this->recognizer, false);

                throw new CheckRetryNeededException(3, 0, self::CAPTCHA_ERROR_MSG);
            }
        }

        return false;
    }

    private function slideTry(RemoteMouse $mouse, MouseMover $mover, array $targetAbs)
    {
        $this->logger->notice(__METHOD__);
        // slider btn
        $slider = $this->waitForElement(WebDriverBy::className('tc-slider-normal'), 5);

        if (!$slider) {
            return false;
        }
        $root = $this->driver->findElement(WebDriverBy::xpath('//body'));
        $root->click();
        $sliderCoords = ['x' => $slider->getLocation()->getX() + 1, 'y' => $slider->getLocation()->getY() + 1];
        $this->logger->info('=sliderCoordsAbs:');
        $this->logger->info(var_export($sliderCoords, true), ['pre' => true]);
        $mover->setCoords(25, 25);
        $mover->moveToCoordinates($sliderCoords, ['x' => 0, 'y' => 0]);
        $this->logger->info('mouseDown');
        $mouse->mouseDown();
        $this->logger->info('move to coordinates');
        $mover->setCoords(25, 25);

        try {
            $mover->moveToCoordinates($targetAbs, ['x' => 0, 'y' => 0]);
        } catch (MoveTargetOutOfBoundsException | Facebook\WebDriver\Exception\MoveTargetOutOfBoundsException $e) {
            $this->logger->error("exception: " . $e->getMessage());
        }
        $this->logger->info('save response');
        $this->saveResponse();
        $this->logger->info('mouseUp');
        $mouse->mouseUp();

        $this->logger->info('waiting result...');
        $this->waitForElement(WebDriverBy::xpath('//p[contains(text(), "请控制拼图块对齐缺口") or contains(text(), "Please complete the security question")]'), 5);
        $this->saveResponse();
        // slider btn
        $captcha = $this->waitForElement(WebDriverBy::className('tc-slider-normal'), 0);
        // $captcha = $this->waitForElement(WebDriverBy::xpath('//div[contains(@class, "login-form")]//button[@disabled]'), 0);

        return $captcha ? false : true;
    }

    private function slideCaptcha($iframe)
    {
        $this->logger->notice(__METHOD__);

        if (!$iframe) {
            $this->saveResponse();
            $this->logger->error('Failed to find captcha iframe');

            return false;
        }

        $iframeCoords = ['x' => $iframe->getLocation()->getX(), 'y' => $iframe->getLocation()->getY()];
        $this->logger->info('=iframeCoords:');
        $this->logger->info(var_export($iframeCoords, true), ['pre' => true]);
        $this->driver->switchTo()->frame($iframe);
        $this->waitForElement(WebDriverBy::xpath('//body'), 5);

        $result = $this->solveSlideCaptcha($iframeCoords);
        $this->logger->debug("switchTo defaultContent...");

        try {
            $this->driver->switchTo()->defaultContent();
        } catch (NoSuchDriverException $e) {
            $this->logger->error("NoSuchDriverException: " . $e->getMessage());

            throw new CheckRetryNeededException(3, 0);
        }

        return $result;
    }

    private function LoadLoginFormChina()
    {
        $this->logger->notice(__METHOD__);

        try {
            $this->driver->manage()->window()->maximize();
        } catch (UnknownServerException $e) {
            $this->logger->error("UnknownServerException exception: " . $e->getMessage());
            $this->DebugInfo = "UnknownServerException";
            $this->logger->error("failed maximize window");
        }

        $startTimer = $this->getTime();

        try {
            $this->http->GetURL("https://www.starbucks.com.cn/en");
        } catch (TimeOutException $e) {
            $this->logger->error("TimeoutException: " . $e->getMessage());
            $this->driver->executeScript('window.stop();');
        } catch (NoSuchWindowException $e) {
            $this->logger->error("NoSuchElementException: " . $e->getMessage());

            return $this->callRetry();
        }
        sleep(3);
        // $this->http->GetURL("https://www.starbucks.com.cn/en/log-in");
        try {
            $this->http->GetURL("https://www.starbucks.com.cn/en/account/#/");
        } catch (TimeOutException $e) {
            $this->logger->error("Exception: " . $e->getMessage());
            $this->driver->executeScript('window.stop();');
        }
        $this->driver->executeScript("
            let banner = document.getElementById('msr-learn-more');
            if (banner) {
                banner.remove();
            }
        ");
        sleep(2);

        $this->getTime($startTimer);

        $loginInput = $this->waitForElement(WebDriverBy::xpath("//input[@name = 'username']"), 10);

        if (!$loginInput) {
            if (ConfigValue(CONFIG_SITE_STATE) != SITE_STATE_DEBUG) {
                throw new CheckRetryNeededException(2, 0);
            }
        }

        $passwordInput = $this->waitForElement(WebDriverBy::xpath("//input[@name = 'password']"), 0);
        $button = $this->waitForElement(WebDriverBy::xpath("//button[contains(., 'Sign in') or contains(., 'Login')]"), 0);
        $this->saveResponse();

        if (!$loginInput || !$passwordInput || !$button) {
            $this->logger->error("something went wrong");

            if ($this->loginSuccessful()) {
                return true;
            }
            // retries - captcha not loaded properly
            $this->saveResponse();

            if (
                $this->waitForElement(WebDriverBy::xpath("//div[contains(@class, 'spinning') and contains(@class, 'captcha')]"), 0)
                && ConfigValue(CONFIG_SITE_STATE) != SITE_STATE_DEBUG
            ) {
                throw new CheckRetryNeededException(3, 0);
            }

            return $this->checkErrors();
        }
        $mover = new MouseMover($this->driver);
        $mover->logger = $this->logger;
        $mover->duration = 100000;
        $mover->steps = 50;
        $mover->setCoords(25, 25);
        $mover->enableCursor();

        try {
            $mover->moveToElement($loginInput);
            $mover->click();
        } catch (Facebook\WebDriver\Exception\MoveTargetOutOfBoundsException | MoveTargetOutOfBoundsException $e) {
            $this->logger->error('[MoveTargetOutOfBoundsException]: ' . $e->getMessage(), ['pre' => true]);
        }

        $mover->sendKeys($loginInput, $this->AccountFields['Login'], 10);
        $this->saveResponse();

        $passwordInput = $this->waitForElement(WebDriverBy::xpath("//input[@name = 'password']"), 0);

        try {
            $mover->moveToElement($passwordInput);
            $mover->click();
        } catch (Facebook\WebDriver\Exception\MoveTargetOutOfBoundsException | MoveTargetOutOfBoundsException $e) {
            $this->logger->error('[MoveTargetOutOfBoundsException]: ' . $e->getMessage(), ['pre' => true]);
        }

        $passwordInput->sendKeys($this->AccountFields['Pass']);
        $this->driver->executeScript('setTimeout(function(){
            delete document.$cdc_asdjflasutopfhvcZLmcfl_;
            delete document.$cdc_asdjflasutopfhvcZLawlt_;
        }, 500)');

        $this->getTime($startTimer);
        $this->driver->executeScript('setTimeout(function(){
            delete document.$cdc_asdjflasutopfhvcZLmcfl_;
            delete document.$cdc_asdjflasutopfhvcZLawlt_;
        }, 500)');

        $this->logger->notice('remember Me');
        $this->driver->executeScript("$('input[id = \"login-remember\"]').prop('checked', true);");

        $this->getTime($startTimer);
        $captchaIframe = $this->waitForElement(WebDriverBy::xpath("//iframe[contains(@id, 'tcaptcha_iframe')]"), 40);

        if (!$captchaIframe) {
            $this->logger->error('Failed to load captcha');
            // retries - captcha not loaded properly
            $this->saveResponse();

            if (
                $this->waitForElement(WebDriverBy::xpath("//div[contains(@class, 'spinning') and contains(@class, 'captcha')]"), 0)
                && ConfigValue(CONFIG_SITE_STATE) != SITE_STATE_DEBUG
            ) {
                throw new CheckRetryNeededException(3, 0, self::CAPTCHA_ERROR_MSG);
            }

            return false;
        }
        $this->slideCaptcha($captchaIframe);
        $captchaIframe = $this->waitForElement(WebDriverBy::xpath("//iframe[contains(@id, 'tcaptcha_iframe')]"), 40);
        $this->saveResponse();
        $this->logger->notice('remember Me');
        $this->driver->executeScript("$('input[id = \"login-remember\"]').prop('checked', true);");

        if ($captchaIframe && !$this->waitForElement(WebDriverBy::xpath('//p[contains(text(), "You need to use the bound mobile phone number:")]'), 0)) {
            $this->slideCaptcha($captchaIframe);
            $this->saveResponse();
            $this->logger->notice('remember Me');
            $this->driver->executeScript("$('input[id = \"login-remember\"]').prop('checked', true);");
        }
        $this->getTime($startTimer);

        if ($button = $this->waitForElement(WebDriverBy::xpath("//button[contains(., 'Login') and not(@disabled)]"), 5)) {
            $button->click();
            sleep(1);
            $this->saveResponse();
        }

        return true;
    }

    private function LoadLoginFormChinaOld()
    {
        $this->logger->notice(__METHOD__);

        $startTimer = $this->getTime();
        $this->http->GetURL("https://www.starbucks.com.cn/en");
        $this->getTime($startTimer);
//        $this->http->GetURL("https://www.starbucks.com.cn/en/log-in");
        $this->http->GetURL("https://www.starbucks.com.cn/en/account/#/");
        $this->getTime($startTimer);

        $delay = 3;
        $this->logger->debug("delay: {$delay}");
        sleep($delay);

        $loginInput = $this->waitForElement(WebDriverBy::xpath("//input[@name = 'username']"), 20);
        $passwordInput = $this->waitForElement(WebDriverBy::xpath("//input[@name = 'password']"), 0);
        $button = $this->waitForElement(WebDriverBy::xpath("//button[contains(., 'Sign in') or contains(., 'Login')]"), 0);
        $this->saveResponse();

        if (!$loginInput || !$passwordInput || !$button) {
            $this->logger->error("something went wrong");

            return $this->checkErrors();
        }
        $mover = new MouseMover($this->driver);
        $mover->logger = $this->logger;
        $mover->duration = 100000;
        $mover->steps = 50;
        $mover->setCoords(25, 25);
        $mover->enableCursor();

//        $mover->moveToElement($loginInput);
//        $mover->click();
//        $mover->sendKeys($loginInput, $this->AccountFields['Login'], 10);
        $loginInput->sendKeys($this->AccountFields['Login']);
        $this->saveResponse();

        $passwordInput = $this->waitForElement(WebDriverBy::xpath("//input[@name = 'password']"), 0);
//        $mover->moveToElement($passwordInput);
//        $mover->click();
        $passwordInput->sendKeys($this->AccountFields['Pass']);

        $this->getTime($startTimer);
        $this->logger->notice('remember Me');
        $this->driver->executeScript("$('input[id = \"login-remember\"]').prop('checked', true);");

        $this->getTime($startTimer);
        $captchaIframe = $this->waitForElement(WebDriverBy::xpath("//iframe[contains(@src, 'captcha.guard')]"), 40);

        if (!$captchaIframe) {
            $this->logger->error('Failed to load captcha');
            // retries - captcha not loaded properly
            $this->saveResponse();

            if (
                $this->waitForElement(WebDriverBy::xpath("//div[contains(@class, 'spinning') and contains(@class, 'captcha')]"), 0)
                && ConfigValue(CONFIG_SITE_STATE) != SITE_STATE_DEBUG
            ) {
                throw new CheckRetryNeededException(3, 0, self::CAPTCHA_ERROR_MSG);
            }

            return false;
        }

        $captcha = $this->parseCaptcha($captchaIframe);
        $this->getTime($startTimer);
        $this->logger->debug("entering captcha...");
//        $forValidationCodeInput->sendKeys($code);
        $this->driver->executeScript("$('#capAns').val(\"" . $captcha . "\");");
        $this->logger->debug("validate captcha...");
//        $validationCodeButton->click();
        $this->saveResponse();
        $this->driver->executeScript("document.getElementById('submit').click()");
        // waiting success
        $this->logger->debug("waiting success...");
        $this->saveResponse();
        // 输入有误，请重新输入 - The input is incorrect. Please re-enter it
        if ($this->waitForElement(WebDriverBy::xpath("//span[@id = 'tip_word']"), 1, true)) {
            $this->saveResponse();
            $this->recognizer->reportIncorrectlySolvedCAPTCHA();

            if (ConfigValue(CONFIG_SITE_STATE) != SITE_STATE_DEBUG) {
                throw new CheckRetryNeededException(3, 7, self::CAPTCHA_ERROR_MSG);
            }
        }// if ($this->waitForElement(WebDriverBy::xpath("//span[@id = 'tip_word']"), 1, true))
        $this->waitForElement(WebDriverBy::xpath("//div[contains(@class, 'passed')] | //div[contains(text(), 'The UserName/Email or Password is invalid')]"), 4);
        $this->saveResponse();

        // The UserName/Email or Password is invalid
        if ($message = $this->waitForElement(WebDriverBy::xpath("//div[contains(text(), 'The UserName/Email or Password is invalid')]"), 0, true)) {
            throw new CheckException($message->getText(), ACCOUNT_INVALID_PASSWORD);
        }

        $this->logger->debug("switchTo defaultContent...");
        $this->driver->switchTo()->defaultContent();
        sleep(1);

//        $img = $this->waitForElement(WebDriverBy::xpath("//img[contains(@data-reactid, 'captcha-".($captcha-1)."')]"), 5);
//        if (!$img)
//            return $this->checkErrors();
//        $img->click();
//        usleep(rand(400000, 1300000));

        if ($button = $this->waitForElement(WebDriverBy::xpath("//button[contains(., 'Sign in')]"), 0)) {
            $button->click();
        }

        $this->getTime($startTimer);

        return true;
    }

    private function parseCaptcha($captchaIframe)
    {
        $this->logger->notice(__METHOD__);
        $this->driver->switchTo()->frame($captchaIframe);
        $img = $this->waitForElement(WebDriverBy::xpath("//img[@id = 'capImg']"));
//        $forValidationCodeInput = $this->waitForElement(WebDriverBy::xpath("//span[@id = 'capAns']"), 0);
//        $validationCodeButton = $this->waitForElement(WebDriverBy::xpath("//input[@id = 'submit']"), 0);
        if (/*!$forValidationCodeInput || !$validationCodeButton || */ !$img) {
            $this->saveResponse();
            $this->logger->error('Failed to find captcha img');

            return false;
        }// if (!$forValidationCodeButton || !$img)

        $captcha = $this->driver->executeScript("

		var captchaDiv = document.createElement('div');
		captchaDiv.id = 'captchaDiv';
		document.body.appendChild(captchaDiv);

		var canvas = document.createElement('CANVAS'),
			ctx = canvas.getContext('2d'),
			img = document.getElementById('capImg');

		canvas.height = img.height;
		canvas.width = img.width;
		ctx.drawImage(img, 0, 0);
		dataURL = canvas.toDataURL('image/png');

		return dataURL;

		");
        $this->logger->debug("captcha: " . $captcha);
        $marker = "data:image/png;base64,";

        if (strpos($captcha, $marker) !== 0) {
            $this->logger->error("no marker");

            return false;
        }
        $captcha = substr($captcha, strlen($marker));
        $file = tempnam(sys_get_temp_dir(), "captcha") . ".png";
        $this->logger->debug("captcha file: " . $file);
        file_put_contents($file, base64_decode($captcha));

        // watchdog workaround
        $this->increaseTimeLimit(180);

        $this->recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_RUCAPTCHA);
        $this->recognizer->RecognizeTimeout = 120;
        $code = $this->recognizeCaptcha($this->recognizer, $file);
        unlink($file);

        /*$forValidationCodeButton = $this->waitForElement(WebDriverBy::xpath("//img[contains(@data-reactid, 'captcha-5')]"));
        $text = $this->waitForElement(WebDriverBy::xpath("//p[@class = 'explanation']"), 0);
        if (!$forValidationCodeButton || !$text) {
            $this->logger->error('Failed to find captcha img');
            $this->saveResponse();
            return false;
        }// if (!$forValidationCodeButton || !$text)
        $text = $this->http->FindPreg("/touch\s*([^\.]+)/", $text->getText());
        if (!$text)
            return false;

        sleep(3);
        $captcha = $this->driver->executeScript("

        var captchaDiv = document.createElement('div');
        captchaDiv.id = 'captchaDiv';
        document.body.appendChild(captchaDiv);

        var canvas = document.createElement('CANVAS'),
            ctx = canvas.getContext('2d'),
            img = document.querySelector('div.possibilities img[data-reactid]:nth-of-type(1)');

        canvas.height = img.height * 3;
        canvas.width = img.width * 6;

        ctx.font = '16px Verdana';
        ctx.fillText('Select ".$text."', 0, img.height * 3 - Math.round(img.height / 5));

        for (n = 0; n < 6; n++) {
            img = document.querySelector('div.possibilities img[data-reactid]:nth-of-type(' + (n + 1) + ')');
            ctx.drawImage(img, img.width * n, 2);
            ctx.font = '14px Verdana';
            ctx.fillText(n+1, img.width * n + Math.round(img.height / 2), img.height * 2);
        }

        dataURL = canvas.toDataURL('image/png');

        return dataURL;

        ");

        $this->logger->debug("captcha: ".$captcha);
        $marker = "data:image/png;base64,";
        if (strpos($captcha, $marker) !== 0) {
            $this->logger->error("no marker");
            return false;
        }
        $captcha = substr($captcha, strlen($marker));
        $file = tempnam(sys_get_temp_dir(), "captcha").".png";
        $this->logger->debug("captcha file: " . $file);
        file_put_contents($file, base64_decode($captcha));

        $this->recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_RUCAPTCHA);
        $this->recognizer->RecognizeTimeout = 120;
        // https://rucaptcha.com/support/faq
        $code = $this->recognizeCaptcha($this->recognizer, $file, ["id_constructor" => '40']);
        unlink($file);

        $code = CleanXMLValue(str_replace("Select:", "", $code));
        $this->logger->debug("Code: $code");
        // wrong response from rucaptcha
        if ($code === "" || (is_numeric($code) && strlen(trim($code)) > 1) || (!is_numeric($code) && strlen(trim($code)) > 1)) {
            $this->recognizer->reportIncorrectlySolvedCAPTCHA();
            throw new CheckRetryNeededException(3, 7, self::CAPTCHA_ERROR_MSG);
        }// if ($code === "" || (is_numeric($code) && strlen(trim($code)) > 1) || (!is_numeric($code) && strlen(trim($code)) > 1))*/

        return $code;
    }

    private function LoadLoginFormJapan()
    {
        $this->logger->notice(__METHOD__);
        $this->http->GetURL("https://login.starbucks.co.jp/login");

        $login = $this->waitForElement(WebDriverBy::xpath("//input[@name = 'username']"), 10);
        $pass = $this->waitForElement(WebDriverBy::xpath("//input[@name = 'password']"), 0);
        $btn = $this->waitForElement(WebDriverBy::xpath("//button[contains(text(), 'ログイン')]"), 0);
        $this->saveResponse();

        if (!$login || !$pass || !$btn) {
            return false;
        }

        $login->sendKeys($this->AccountFields['Login']);
        $pass->sendKeys($this->AccountFields['Pass']);
        $btn->click();

        return true;
    }

    private function processTwoFactorChina()
    {
        $this->logger->notice(__METHOD__);
        $this->logger->info('Security Question', ['Header' => 3]);
        $questionLabel = $this->waitForElement(WebDriverBy::xpath('//p[contains(text(), "You need to use the bound mobile phone number:")]'), 0);
        $codeInput = $this->waitForElement(WebDriverBy::xpath("//input[@id = 'input-text']"), 0);
        $this->saveResponse();

        if (!$codeInput || !$questionLabel) {
            $this->logger->error("something went wrong");

            return false;
        }

        $question = $questionLabel->getText() . $this->waitForElement(WebDriverBy::xpath('//p[contains(text(), "You need to use the bound mobile phone number:")]/following-sibling::p[1]'), 0)->getText();

        if (!isset($this->Answers[$question])) {
            $this->holdSession();
            $this->AskQuestion($question, null, "processTwoFactorChina");

            return false;
        }// if (!isset($this->Answers[$question]))

        $answer = $this->Answers[$question];
        unset($this->Answers[$question]);

        $codeInput->clear();
        $codeInput->sendKeys($answer);
        $button = $this->waitForElement(WebDriverBy::xpath("//button[contains(text(), 'Verification of mobile phones') and not(@disabled)]"), 5);

        $button->click();

        $this->waitForElement(WebDriverBy::xpath('
            //span[@class = "fieldStatus__text"]/span[contains(., "Check code and try again.")]
            | //div[contains(@class, "starBalance___")]
        '), 5); //todo
//        $error = $this->waitForElement(WebDriverBy::xpath('
//            //span[@class = "fieldStatus__text"]/span[contains(., "Check code and try again.")]
//        '), 0);
        $this->saveResponse();

//        if (!empty($error)) {
//            $error = 'Check code and try again.';
//            $this->logger->notice("error: " . $error);
//            $this->holdSession();
//            $codeInput->clear();
//            $this->AskQuestion($question, $error, "processTwoFactor");
//
//            return false;
//        }// if (!empty($error))

        return true;
    }

    private function processTwoFactor()
    {
        $this->logger->notice(__METHOD__);
        $this->logger->info('Security Question', ['Header' => 3]);
        $this->saveResponse();
        $questionLabel = $this->waitForElement(WebDriverBy::xpath('//p[contains(text(), "We sent a verification code")]'), 0);
        $codeInput = $this->waitForElement(WebDriverBy::xpath("//input[@id = 'verificationCode']"), 0);
        $button = $this->waitForElement(WebDriverBy::xpath("//button[contains(@class, 'sb-frap')]"), 0);

        if (!$codeInput || !$questionLabel || !$button) {
            $this->logger->error("something went wrong");

            return false;
        }
        $question = $questionLabel->getText();

        if (!isset($this->Answers[$question])) {
            $this->holdSession();
            $this->AskQuestion($question, null, "processTwoFactor");

            return false;
        }// if (!isset($this->Answers[$question]))

        $answer = $this->Answers[$question];
        unset($this->Answers[$question]);
        $codeInput->clear();

        if ($this->ff53) {
            $codeInput->sendKeys($answer);
        } else {
            $mover = new MouseMover($this->driver);
            $mover->logger = $this->logger;
            $mover->duration = 100000;
            $mover->steps = 50;
            $mover->duration = 100000;
            $mover->steps = 50;
            $mover->moveToElement($codeInput);
            $mover->click();
            $mover->sendKeys($codeInput, $answer, 10);
        }

        $this->logger->debug("click 'Sign In'");
        $this->driver->executeScript('setTimeout(function(){
            delete document.$cdc_asdjflasutopfhvcZLmcfl_;
            delete document.$cdc_asdjflasutopfhvcZLawlt_;
        }, 500)');
        $this->saveResponse();
        usleep(rand(100000, 500000));

        try {
            $button = $this->waitForElement(WebDriverBy::xpath('//button[contains(@class, "sb-frap") and @data-e2e = "verifyButton"]'), 0);
            $button->submit();
//            $this->driver->executeScript('$(\'button.sb-frap[data-e2e = "verifyButton"]\').click()');
        } catch (StaleElementReferenceException | UnexpectedJavascriptException $e) {
            $this->logger->error("exception: " . $e->getMessage());
            sleep(1);
            $this->saveResponse();
            $button = $this->waitForElement(WebDriverBy::xpath('//button[contains(@class, "sb-frap") and @data-e2e = "verifyButton"]'), 0);
            $button->submit();
        } finally {
            $this->logger->debug("finally");
            $this->saveResponse();
        }
        $this->logger->debug("delay");
        sleep(2);
        $this->logger->debug("after delay");
        $this->saveResponse();

//            //h4[@id = "errorMsg"]
//            | //label[@for = "BlockUserEroorMsg"]
        $this->waitForElement(WebDriverBy::xpath('
            //span[@class = "fieldStatus__text"]/span[contains(., "Check code and try again.")]
            | //div[contains(@class, "starBalance___")]
        '), 5);
        $error = $this->waitForElement(WebDriverBy::xpath('
            //span[@class = "fieldStatus__text"]/span[contains(., "Check code and try again.")]
        '), 0);
        unset($this->Answers[$question]);
        $this->saveResponse();

        if (!empty($error)) {
            $error = 'Check code and try again.';
            $this->logger->notice("error: " . $error);
            $this->holdSession();
            $codeInput->clear();
            $this->AskQuestion($question, $error, "processTwoFactor");

            return false;
        }// if (!empty($error))

        return true;
    }

    private function doRetry()
    {
        $this->logger->notice(__METHOD__);
        $this->logger->debug("[Current URL]: " . $this->http->currentUrl());
        $hostURL = $this->http->getCurrentScheme() . "://" . $this->http->getCurrentHost() . "/";
        $this->logger->debug("[Host URL]: " . $hostURL);
        // retries
        if ($this->http->FindSingleNode("//h1[contains(text(), 'This site can’t be reached')]")
            || (!empty($hostURL) && $this->http->currentUrl() == "{$hostURL}account/signin?ReturnUrl=%2faccount%2fhome")
            || (!empty($hostURL) && $this->http->currentUrl() == "{$hostURL}account/signin?ReturnUrl=%2faccount%2fhome&Timeout=true")
        ) {
            throw new CheckRetryNeededException(3);
        }
        // retries
        if ($error = $this->http->FindPreg("/(?:Secure Connection Failed|Unable to connect|The proxy server is refusing connections|Failed to connect parent proxy|The connection has timed out|page isn’t working|There is no Internet connection)/")) {
            $this->logger->error($error);
            $this->markProxyAsInvalid();
            $this->DebugInfo = $error;

            throw new CheckRetryNeededException(5);
        }
    }

    private function parseWithCurl()
    {
        $this->logger->notice(__METHOD__);
        // parse with curl
        $this->browser = new HttpBrowser("none", new CurlDriver());
        $this->http->brotherBrowser($this->browser);
//        $this->driver->manage()->deleteCookieNamed(''); // #0 /www/loyalty/current/vendor/php-webdriver/webdriver/lib/Cookie.php(25): Facebook\WebDriver\Cookie->validateCookieName('')
        try {
            $cookies = $this->driver->manage()->getCookies();
        } catch (NoSuchDriverException | SessionNotCreatedException $e) {
            $this->logger->error("exception: " . $e->getMessage());

            throw new CheckRetryNeededException(3, 0);
        }

        foreach ($cookies as $cookie) {
            $this->browser->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
        }

        if ($this->AccountFields['Login2'] != 'China') {
            $this->getMemcachedStarbucks()->delete("starbucks_lock_" . $this->port);
            $this->logger->debug("unlocked");
        }

        $this->curl = true;

        $this->browser->LogHeaders = true;

        $rewardsPageURL = "https://www.starbucks." . $this->domain . "/account";

        if ($this->useParseUsa()) {
            $rewardsPageURL = "https://app.starbucks." . $this->domain . "/rewards";

            return;
        }

        if ($this->AccountFields['Login2'] == 'China') {
            $this->browser->GetURL($this->http->currentUrl());
        } else {
            $this->browser->setProxyParams($this->http->getProxyParams());
            $this->browser->GetURL($rewardsPageURL);
        }
    }

    private function parseJapan()
    {
        $this->http->GetURL("https://www.starbucks.co.jp/mystarbucks/reward/");
        $this->waitForElement(WebDriverBy::xpath('//div[@class = "possessionStarContents"]//span[@class = "current decimal"]'), 10);
        $this->saveResponse();
        // Balance - Stars
        $this->SetBalance($this->http->FindSingleNode('//div[@class = "possessionStarContents"]//span[@class = "current decimal"]'));
        // ... Star会員
        $this->SetProperty("EliteLevel", $this->http->FindSingleNode('//div[@id = "category"]/div[@class = "_d1"]', null, true, "/(.+) Star会員/"));
        // Star 更新日
        $this->SetProperty("Since", $this->http->FindSingleNode('//div[@id = "category"]/div[@class = "_d1"]', null, true, "/日:(.+)/"));
        // 更新日までに、... Stars集めてGold Star会員になりましょう。
        $this->SetProperty("NeededStarsForNextLevel", $this->http->FindSingleNode('//div[@id = "category"]/div[@class = "_d2"]', null, true, "/更新日までに、(\d+)/"));

        $this->http->GetURL("https://card.starbucks.co.jp/mystarbucks/card/cardinfo/");
        $updBalance = $this->waitForElement(WebDriverBy::xpath('//p[img[@alt="更新"]]'), 10);
        $this->saveResponse();

        $displayName = $this->http->FindSingleNode('//th[contains(text(), "カード番号")]/following-sibling::td');
        $balance = $this->http->FindSingleNode('//form[@id = "beforeBalanceCard"]/text()[1]', null, true, "/(.+)円/");

        if (isset($balance, $displayName)) {
            $this->AddSubAccount([
                'Code'        => "starbucksCardJapan" . str_replace(' ', '', $displayName),
                'DisplayName' => "Card # " . $displayName,
                'Balance'     => $balance,
                'Card'        => $displayName,
            ]);
        }

        // Name
        $this->http->GetURL("https://www.starbucks.co.jp/mystarbucks/admin/");
        $this->waitForElement(WebDriverBy::xpath('//dt[span[contains(text(), "ユーザー名（ニックネーム）")]]/following-sibling::dd[1]/div'), 5);
        $this->saveResponse();
        $this->SetProperty("Name", $this->http->FindSingleNode('//dt[span[contains(text(), "ユーザー名（ニックネーム）")]]/following-sibling::dd[1]/div'));
    }

    private function parseChina()
    {
        // use curl
        $this->browser = $this->http;
        $this->parseWithCurl();

        if ($this->browser->currentUrl() != "https://profile.starbucks.com.cn/api/Customers/detail") {
            $this->browser->GetURL("https://profile.starbucks.com.cn/api/Customers/detail");
        }
        $response = $this->browser->JsonLog();
        // Name
        if (isset($response->firstName, $response->lastName)) {
            $this->SetProperty("Name", $response->firstName . " " . $response->lastName);
        }
        // Balance - Stars
        if (isset($response->loyaltyPoints)) {
            $this->SetBalance($response->loyaltyPoints);
        }
        //# Cardholder Since
        if (isset($response->since)) {
            $this->SetProperty("Since", $response->since);
        }
        // Level
        if (isset($response->loyaltyTier)) {
            $this->SetProperty("EliteLevel", $response->loyaltyTier);
        }

        if (isset($this->Properties['EliteLevel']) && strtolower($this->Properties['EliteLevel']) != "gold") {
            //# Needed Stars for Next Level
            if (isset($response->b10G1purchasesNeeded)) {
                $this->SetProperty("NeededStarsForNextLevel", $response->b10G1purchasesNeeded);
            }
        } else {
            // Stars Until Your Next Free Drink
            if (isset($response->b10G1purchasesNeeded)) {
                $this->SetProperty("StarsNeeded", $response->b10G1purchasesNeeded);
            }
        }

        // Sub Accounts - Rewards // refs #6150
        $this->browser->GetURL("https://profile.starbucks.com.cn/api/Customers/rewards?status=Unused&pageNum=1&pageSize=50");
        $response = $this->browser->JsonLog(null, 3, true);
        $this->logger->debug("Total rewards found: " . ((is_array($response) || ($response instanceof Countable)) ? count($response) : 0));

        if (is_array($response)) {
            foreach ($response as $reward) {
                $status = ArrayVal($reward, 'status');
                $displayName = ArrayVal($reward, 'description');
                $exp = ArrayVal($reward, 'expiryDate');
                $balance = ArrayVal($reward, 'quantity');
                $memberBenefitId = ArrayVal($reward, 'memberBenefitId');

                if (isset($status, $displayName, $exp, $balance) && strtolower($status) == 'available') {
                    $subAccounts[] = [
                        'Code'           => "starbucksFreeDrinks" . str_replace('@', '_', $memberBenefitId),
                        'DisplayName'    => $displayName,
                        'Balance'        => $balance,
                        'ExpirationDate' => strtotime($exp),
                    ];
                }// if (isset($status, $displayName, $exp, $balance) && strtolower($status) == 'available')
            }
        }// foreach ($response as $reward)

        // cards
        $this->browser->GetURL("https://profile.starbucks.com.cn/api/Customers/cards/list");
        $response = $this->browser->JsonLog(null, 3, true);

        if (is_array($response)) {
            foreach ($response as $card) {
                if (ArrayVal($card, 'cardStatus') == 'Registered') {
                    $cardNumber = ArrayVal($card, 'cardNumber');
                    $subAccount = [
                        "Code"        => 'starbucksCard' . $this->AccountFields['Login2'] . $cardNumber,
                        "DisplayName" => 'Card # ' . $cardNumber,
                        "Card"        => $cardNumber,
                        "Balance"     => null,
                    ];
                    $subAccounts[] = $subAccount;
                }//if (ArrayVal($card, 'cardStatus') == 'Registered')
            }
        }// foreach ($response as $card)

        if (isset($subAccounts)) {
            $this->SetProperty("CombineSubAccounts", false);
            // Set SubAccounts Properties
            $this->SetProperty("SubAccounts", $subAccounts);
        }
    }

    private function ParseUsa($totalFreeDrinks)
    {
        $this->logger->notice(__METHOD__);
        $this->saveResponse();
        // use curl
//        $this->browser = $this->http;
        $this->parseWithCurl();
        $this->logger->notice("Current proxy -> " . $this->browser->GetProxy());

        $header = [
            'x-requested-with' => 'XMLHttpRequest',
            'Accept'           => 'application/json',
            'Content-Type'     => 'application/json',
        ];
        $this->browser->PostURL('https://app.starbucks.' . $this->domain . '/bff/proxy/orchestra/get-user', "{}", $header);
        $response = $this->browser->JsonLog();
        // Name
        if (isset($response->data->user->firstName, $response->data->user->lastName)) {
            $this->SetProperty('Name', beautifulName($response->data->user->firstName . ' ' . $response->data->user->lastName));
        }
        // Balance - Earned stars
        if (!isset($response->data->user->loyaltyProgram->level) || !isset($response->data->user->loyaltyProgram->progress->starBalance)) {
            // AccountID 3793566, 4371602
            if ($this->browser->FindPreg("/\"partnerNumber\":\s*null,\s*\"birthDay\":\s*(?:\d+|null),\s*\"birthMonth\":\s*(?:\d+|null),\s*\"loyaltyProgram\":\s*null/")) {
                $this->SetBalanceNA();
            }
            /*
            // AccountID 3984535, 3874991, 3378370
            if (isset($response->errors[0]->message) && $response->errors[0]->message == "User does not exist.")
                throw new CheckException("The email or password you entered is not valid. Please try again.", ACCOUNT_INVALID_PASSWORD);
            */
            // isLoggedIn issue
            if (
                (isset($response->error->message) && in_array($response->error->message, [
                    'access token invalid',
                    'Forbidden',
                    'Bad Request',
                ])
                )
                || (isset($response->errors[0]->message) && in_array($response->errors[0]->message, [
                    'Unauthorized',
                    'Forbidden',
                    'Unknown error occured',
                    'User context not found.',
                ])
                    )
                || $this->browser->FindSingleNode('//li[contains(text(), "Well, something technical went wrong on our site.")]')
                || $this->browser->FindPreg('/<h1>Whoops!<\/h1>\s*<p>Something has gone wrong on our end\.<\/p>/')
                || $this->browser->FindPreg('/An error occurred while processing your request\.<p>/')
                || $this->browser->Response['body'] == '{"roleProvided":"public","roleRequired":"user:limited","type":"authorize-operation"}'
            ) {
                throw new CheckRetryNeededException(2, 1);
            }

            // auth failed
            if ((isset($response->errors[0]->message) && $response->errors[0]->message == "404: Not Found")) {
                $this->logger->error("Auth filed. See selenium settings");

                throw new CheckRetryNeededException(2, 1);
            }

            // We’re still brewing your Starbucks® Rewards info. Check back soon.
            if (isset($response->errors[0]->message) && in_array($response->errors[0]->message, ['503: Service Unavailable', 'Internal Server Error'])) {
                throw new CheckException("We’re still brewing your Starbucks® Rewards info. Check back soon.", ACCOUNT_PROVIDER_ERROR);
            }

            return;
        }
        $this->SetBalance(floor($response->data->user->loyaltyProgram->progress->starBalance));
        // Stars until your next Reward
        if (!empty($response->data->user->loyaltyProgram->progress->starsToNextGoal)) {
            $this->SetProperty('StarsNeeded', round($response->data->user->loyaltyProgram->progress->starsToNextGoal));
        }
        // Member since
        $this->SetProperty("Since", date("d M Y", strtotime($this->http->FindPreg("/(.+)T/", false, $response->data->user->loyaltyProgram->cardHolderSince))));

        // 6 free drink or food Rewards
        if (isset($totalFreeDrinks) && $totalFreeDrinks > 0) {
            $subAccounts = [
                'Code'        => "starbucksTotalFreeDrinkFoodRewards",
                'DisplayName' => "Total Free drink or food Rewards",
                'Balance'     => $totalFreeDrinks,
            ];

            if (isset($subAccounts)) {
                $this->SetProperty("CombineSubAccounts", false);
                $this->AddSubAccount($subAccounts);
            }
        }

        // Expiration Date
        if ($this->Balance > 0) {
            $this->browser->PostURL('https://app.starbucks.' . $this->domain . '/bff/proxy/orchestra/get-expiring-stars', '{}', $header);
            $response = $this->browser->JsonLog();

            if (isset($response->data->user->loyaltyProgram->expiringStars)) {
                foreach ($response->data->user->loyaltyProgram->expiringStars as $item) {
                    if (isset($item->date, $item->starsExpiring) && $item->starsExpiring > 0) {
                        $expDate = strtotime($item->date, false);

                        if ($expDate >= strtotime('now')) {
                            $this->SetExpirationDate($expDate);
                            $this->SetProperty('ExpiringBalance', $item->starsExpiring);

                            break;
                        }
                    }
                }
            }
        }

        $this->logger->info('Cards:', ['Header' => 2]);
        $this->browser->PostURL('https://app.starbucks.' . $this->domain . '/bff/proxy/orchestra/get-stored-value-card-list', "{}", $header);
        $response = $this->browser->JsonLog();

        if (!isset($response->data->user->storedValueCardList)) {
            return;
        }
        $cards = $response->data->user->storedValueCardList;
        $cardsCount = count($cards);
        $this->logger->debug("Total {$cardsCount} cards were found");
        $this->SetProperty("CombineSubAccounts", false);

        foreach ($cards as $card) {
            $subAccount = [];
            // Card Number
            $barCode = $subAccount["Card"] = $card->cardNumber ?? null;
            $this->logger->info('Card #' . $barCode, ['Header' => 3]);
            // Card balance
            $subAccount["Balance"] = $card->balance->amount;
            $this->logger->debug("BarCode: {$barCode}");
            $subAccount["BarCodeType"] = BAR_CODE_PDF_417;

            if (isset($barCode)) {
                $subAccount['BarCode'] = $barCode;
                $subAccount["Card"] = $barCode;
            }// if (isset($barCode))
            // Card Name
            $cardName = $card->nickname;

            if ($cardName) {
                $subAccount["Code"] = 'starbucksCard' . $this->AccountFields['Login2'] . md5($cardName);
                $subAccount["DisplayName"] = $cardName;
            }// if ($cardName)
            $cardId = $card->cardId ?? null;

            if ($cardId) {
                $this->logger->notice("Update card balance {$barCode}...");
                $data = '{"variables":{"cardId":"' . $cardId . '"}}';
                $this->browser->PostURL('https://app.starbucks.' . $this->domain . '/bff/proxy/orchestra/stored-value-card-realtime-balance', $data, $header);
                $responseBalance = $this->browser->JsonLog();
                // Card balance
                $balance = $responseBalance->data->storedValueCardRealtimeBalance->amount ?? $subAccount["Balance"];
                $this->logger->debug(">>> Card balance: " . $balance);
                $subAccount["Balance"] = $balance;
            }// if ($cardId)
            $this->AddSubAccount($subAccount);
        }// foreach ($cards as $card)
    }

    private function useParseUsa(): bool
    {
        $this->logger->notice(__METHOD__);

        return in_array($this->AccountFields['Login2'], ['USA', 'Canada']) || $this->waitForElement(WebDriverBy::xpath('//p[contains(text(), "Want to see the old site?")]'), 0);
    }

    private function parseCardsEurope()
    {
        $this->logger->notice(__METHOD__);
        $this->browser->GetURL("https://www.starbucks." . $this->domain . "/account/cards");

        if (!$this->curl) {
            $this->saveResponse();
        }

        $cards = $this->browser->FindNodes('//a[@class = "card"]/@href');
        $cardsCount = count($cards);
        $this->logger->debug("Total {$cardsCount} cards were found");
        $this->SetProperty("CombineSubAccounts", false);

        foreach ($cards as $card) {
            $subAccount = [];
            $this->logger->notice("Loading card {$card}...");
            $this->browser->NormalizeURL($card);
            $this->browser->GetURL($card);
            $this->increaseTimeLimit();
            // Card Name
            $cardName = $this->browser->FindSingleNode('//div[@data-endpoint-namespace = "nicknameCard"]//input/@value');
            $balanceURL = $this->browser->FindSingleNode('//div[@data-component = "card-balance"]/@data-endpoint-path');

            if (!$balanceURL) {
                $this->sendNotification("Card balance not found");

                continue;
            }
            $this->browser->NormalizeURL($balanceURL);
            $this->browser->GetURL($balanceURL);
            $response = $this->browser->JsonLog();

            if (!isset($response->balance)) {
                $this->logger->notice("provider bug fix");
                sleep(5);
                $this->browser->NormalizeURL($balanceURL);
                $this->browser->GetURL($balanceURL);
                $response = $this->browser->JsonLog();

                if (!isset($response->balance)) {
                    $this->logger->notice("one more provider bug fix");
                    sleep(5);
                    $this->browser->NormalizeURL($balanceURL);
                    $this->browser->GetURL($balanceURL);
                    $response = $this->browser->JsonLog();
                }
            }

            // Card balance
            $subAccount["Balance"] = $response->balance;
            // Card Number
            $subAccount["Card"] = $response->cardNumber;

            if (!isset($cardName) && isset($subAccount["Card"])) {
                $cardName = "Card # {$subAccount["Card"]}";
            }

            if ($cardName) {
                $subAccount["Code"] = 'starbucksCard' . $this->AccountFields['Login2'] . $subAccount["Card"];
                $subAccount["DisplayName"] = $cardName;
            }

            $this->AddSubAccount($subAccount);
        }// foreach ($cards as $card)
    }

    /*
    //# Get a Expiration Date of Elite Level
    private function getExpirationDate($date)
    {
        $this->logger->notice(__METHOD__);

        if ($date < time()) {
            $this->logger->debug("Conversion date. Step 1 >>> " . date("m/d/Y", $date));
            $exp = strtotime(date("m/d", $date) . '/' . date("Y"));

            if ($exp < time()) {
                $exp = strtotime("+1 year", $exp);
            }
            $this->logger->debug("Conversion date. Step 2 >>> " . date("m/d/Y", $exp));
        } else {
            $exp = $date;
        }
        $this->logger->debug("Expiration Date of Elite Level >>> " . date("m/d/Y", $exp));

        return $exp;
    }
    */
}
