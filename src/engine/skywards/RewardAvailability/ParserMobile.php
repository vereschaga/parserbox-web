<?php

namespace AwardWallet\Engine\skywards\RewardAvailability;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\ProxyList;
use AwardWallet\Engine\Settings;
use MouseMover;
use WebDriverBy;

class ParserMobile extends \TAccountChecker
{
    use \SeleniumCheckerHelper;
    use \PriceTools;
    use ProxyList;

    private const START_URL = 'https://mobile.emirates.com/ui/english/index.xhtml#/search/fromhome';

    private const MOBILE_CABIN = [
        'economy'        => 'Economy',
        'premiumEconomy' => 'Premium Economy',
        'business'       => 'Business',
        'firstClass'     => 'First',
    ];

    private bool $isHot = false;

    private const MOBILE_CLASS_TYPE = [
        'economy'        => 0,
        'premiumEconomy' => 3,
        'business'       => 1,
        'firstClass'     => 2,
    ];
    private const MOBILE_CABIN_CODE = [
        'Y' => 'economy',
        'W' => 'premiumEconomy',
        'J' => 'business',
        'F' => 'firstClass',
    ];
    private bool $retryLoad = false;

    private bool $isLoggedIn = false;

    public function InitBrowser()
    {
        \TAccountChecker::InitBrowser();
        $this->UseSelenium();
        $r = rand(1, 1);

        $this->useGoogleChrome(\SeleniumFinderRequest::CHROME_99);
        $this->KeepState = true;
        $resolutions = [
            [360, 640],
            [411, 731],
            [414, 736],
        ];
        $chosenResolution = $resolutions[array_rand($resolutions)];
        $this->logger->info('chosenResolution:');
        $this->logger->info(var_export($chosenResolution, true));
        $this->setScreenResolution($chosenResolution);
        // for debug
        $this->http->saveScreenshots = true;

        $this->disableImages();

        $this->http->setUserAgent('Mozilla/5.0 (Linux; Android 8.0; Pixel 2 Build/OPD3.170816.012) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.114 Mobile Safari/537.36');
        $this->seleniumRequest->setHotSessionPool(
            self::class,
            'skywards',
            $this->AccountFields['AccountKey'] ?? null
        );
    }

    public function IsLoggedIn()
    {
        $this->http->GetURL(self::START_URL);

        $rewards = $this->waitForElement(\WebDriverBy::xpath('//strong[normalize-space()="Use Classic Rewards"]'), 10);

        if ($rewards) {
            $this->isHot = true;
            return true;
        }

        return false;
        /*
                $options = $this->waitForElement(\WebDriverBy::xpath("//a[contains(.,'Options')]"),3);
                if (!$options){
                    return false;
                }
                $options->click();

                $logout = $this->waitForElement(\WebDriverBy::xpath("//a[contains(.,'Log out')]"), 1);

                $this->savePageTolog();
                $close = $this->waitForElement(\WebDriverBy::xpath("//div[@class='close-modal']/a[normalize-space()='']"),1);
                if ($logout && $close) {
                    $close->click();
                    return true;
                }
                if ($close) {
                    $close->click();
                }

                return false;
        */
    }

    public function LoadLoginForm()
    {
        $this->isLoggedIn = false;

        return true;
    }

    public function Login()
    {
//        $this->http->removeCookies();
        $this->http->GetURL(self::START_URL);

        $rewards = $this->waitForElement(\WebDriverBy::xpath('//strong[normalize-space()="Use Classic Rewards"]'), 15);
        $this->saveResponse();

        if ($button = $this->waitForElement(\WebDriverBy::xpath("//button[@id='onetrust-accept-btn-handler']"),5))
            $button->click();

        if ($rewards) {
            return true;
        }

        $options = $this->waitForElement(\WebDriverBy::xpath("//a[normalize-space()='Options']"), 5);
        $options->click();

        $readem = $this->waitForElement(\WebDriverBy::xpath("//a[normalize-space()='Redeem Miles']"), 5);
        $readem->click();
        $this->waitFor(function() {
            return $this->waitForElement(\WebDriverBy::xpath("
            //span[normalize-space()='Search flights']
            | //label[@id='sso-email_label']
            "), 0);
        }, 40);

        if ($this->waitForElement(\WebDriverBy::xpath("//span[normalize-space()='Search flights']"), 0)) {
            $rewards = $this->waitForElement(\WebDriverBy::xpath('//strong[normalize-space()="Use Classic Rewards"]'), 15);
            $this->saveResponse();

            if ($rewards) {
                return true;
            }
        }

        $loginInput = $this->waitForElement(WebDriverBy::xpath('//input[@id = "sso-email" or @id = "txtMembershipNo"]'), 0);
        $passwordInput = $this->waitForElement(WebDriverBy::xpath('//input[@id = "sso-password" or @id = "reauth-password" or @id = "txtPassword"]'), 0);
        $this->saveResponse();

        $btnLogIn = $this->waitForElement(WebDriverBy::xpath('//button[@id = "login-button"] | //input[@id = "btnLogin_LoginWidget"]'), 0);

        $mover = new \MouseMover($this->driver);
        $mover->logger = $this->logger;
//        $mover->enableCursor();
//        $this->AccountFields['Pass'] = 'lkfgn!850L';

        if (!$loginInput && $passwordInput) {
            $this->saveResponse();

            $mover->sendKeys($passwordInput, $this->AccountFields['Pass'], 10);

            $btnLogIn = $this->waitForElement(WebDriverBy::xpath('//button[@id = "reauth-login-btn"]'), 0);
            $this->saveResponse();

            if (!$btnLogIn) {
                $this->logger->error('something went wrong');

                return $this->checkErrors();
            }

            $btnLogIn->click();

            if ($this->waitForElement(\WebDriverBy::id("send-OTP-button"), 10)
                || $this->waitForElement(\WebDriverBy::xpath('//p[contains(text(), "An email with a 6-digit passcode has been sent to")]'), 0)
            ) {
                $this->savePageTolog();

                return $this->twoStepVerification();
            }

            return true;
        }

        if (!$loginInput || !$passwordInput || !$btnLogIn) {
            $this->logger->error('something went wrong');

            return null;
        }// if (!$loginInput || !$passwordInput)
        // refs #14450
        $mover->duration = 100000;
        $mover->steps = 50;

//        $mover->sendKeys($loginInput, 'EK668306903', 10);
        $mover->sendKeys($loginInput, $this->AccountFields['Login'], 10);
        $mover->sendKeys($passwordInput, $this->AccountFields['Pass'], 10);

        $this->driver->executeScript("var remember = document.getElementById('chkRememberMe'); if (remember) remember.checked = true;");

        usleep(rand(400000, 1300000));
        //$this->hideOverlay();
        $btnLogIn = $this->waitForElement(WebDriverBy::xpath('//button[@id = "login-button" or @id = "reauth-login-btn"] | //input[@id = "btnLogin_LoginWidget"]'), 0);
        $this->saveResponse();

        if (!$btnLogIn) {
            return false;
        }

        $btnLogIn->click();

        /*$emailLabel = $this->waitForElement(\WebDriverBy::id('sso-email_label'), 0);
        $email = $this->waitForElement(\WebDriverBy::id('sso-email'), 0);
        $pwd = $this->waitForElement(\WebDriverBy::id('sso-password'), 0);
        $pwdLabel = $this->waitForElement(\WebDriverBy::id('sso-password_label'), 0);
        $btn = $this->waitForElement(\WebDriverBy::id('login-button'), 0);

        if (!$email || !$pwd || !$emailLabel || !$pwdLabel || !$btn) {
            $this->savePageTolog();
            $this->sendNotification('check login // ZM');
            $this->logger->error("form not load");
        } else {
            $emailLabel->click();
            $email->clear();
            $email->sendKeys($this->AccountFields['Login']);
            $pwdLabel->click();
            $pwd->clear();
            $pwd->sendKeys($this->AccountFields['Pass']);
        }
        sleep(1);
        $btn->click();
        $this->logger->debug('wait...');*/

        $this->waitFor(function () {
            return $this->driver->findElement(\WebDriverBy::xpath("//span[contains(.,'Search flights')]"))
                || $this->driver->findElement(\WebDriverBy::xpath("//p[contains(.,'Your account has been locked as a security precaution')]"))
                || $this->driver->findElement(\WebDriverBy::id("send-OTP-button"));
        }, 70);

        $this->logger->debug('wait off.');

        if ($this->waitForElement(\WebDriverBy::id("send-OTP-button"), 0)
            || $this->waitForElement(\WebDriverBy::xpath('//p[contains(text(), "An email with a 6-digit passcode has been sent to")]'), 0)
        ) {
            $this->savePageTolog();

            return $this->twoStepVerification();
        }

        if ($this->waitForElement(\WebDriverBy::xpath("//h2[normalize-space()='Login']"), 0)) {
            sleep(5);
        }

        if ($locked = $this->waitForElement(\WebDriverBy::xpath("//p[contains(.,'Your account has been locked as a security precaution')]"), 0)) {
            throw new \CheckException($locked->getText(), ACCOUNT_LOCKOUT);
        }
        $span = $this->waitForElement(\WebDriverBy::xpath("//span[contains(.,'Search flights')]"), 10);

        $this->savePageTolog();

        if ($this->http->FindPreg('/(?:page isn’t working|There is no Internet connection|This site can’t be reached)/ims')) {
            throw new \CheckRetryNeededException(5, 0);
        }

        if (!$span) {
            return false;
        }

        return true;
    }

    private function checkErrors()
    {
        $this->logger->notice(__METHOD__);
        $this->saveResponse();

        throw new \CheckRetryNeededException(2, 1);

    }

    public function getRewardAvailabilitySettings()
    {
        $arrCurrencies = ['AED']; // по идее пров поддерживает и другие, но там конвертация и approx.

        return [
            'supportedCurrencies'      => $arrCurrencies,
            'supportedDateFlexibility' => 0,
            'defaultCurrency'          => 'AED',
        ];
    }

    public function ParseRewardAvailability(array $fields)
    {
        $this->logger->info("Parse Reward Availability", ['Header' => 2]);
        $this->logger->debug("params: " . var_export($fields, true));

        $this->keepSession(true);
        if ($fields['Currencies'][0] !== 'AED') {
            $fields['Currencies'][0] = $this->getRewardAvailabilitySettings()['defaultCurrency'];
            $this->logger->notice("parse with defaultCurrency: " . $fields['Currencies'][0]);
        }

        if ($fields['Adults'] > 9) {
            $this->SetWarning('You can check max 9 travellers.');

            return ['routes' => []];
        }

        if ($fields['DepDate'] > strtotime('+328 day')) {
            $this->SetWarning('We can only show you flights up to 328 days in advance.');

            return ['routes' => []];
        }

//        $res = $this->enterRequest($fields);
        $res = $this->sendMainRequest($fields);

        if (is_array($res)) {
            return ['routes' => []];
        }

        if ($this->checkNoFlights()) {
            return ['routes' => []];
        }

        $browser = new \HttpBrowser("none", new \CurlDriver());
        $this->http->brotherBrowser($browser);
        $cookies = $this->driver->manage()->getCookies();

        foreach ($cookies as $cookie) {
            $browser->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'],
                $cookie['expiry'] ?? null);
        }
        $headers = [
            'Accept'           => 'application/json, text/plain, */*',
            'Accept-Encoding'  => 'gzip, deflate, br',
            'ADRUM'=>'isAjax:true',
            'Referer' => 'https://mobile.emirates.com/app/english/bookaflight/brandedsearch',
//            'sec-ch-ua-mobile' => '?1',
            'Sec-Fetch-Dest' => 'empty',
            'Sec-Fetch-Mode' => 'cors',
            'Sec-Fetch-Site' => 'same-origin',
            'TE' => 'trailers',
            'User-Agent' => $this->http->getDefaultHeader('User-Agent'),
        ];
//                             "https://mobile.emirates.com/english/restful/mab/getBrandedSearchResultsOutboundJSON.xhtml?start=0&limit=2&ccTenFlowCall=true&isAjaxJson=true"
        $browser->GetURL("https://mobile.emirates.com/english/restful/mab/getBrandedSearchResultsOutboundJSON.xhtml?isAjaxJson=true",
            $headers);
        $data = $browser->JsonLog();

        return ['routes' => $this->parseRewardFlightsJson($fields, $data, $browser)];
    }

    public function ProcessStep($step)
    {
        $this->logger->notice(__METHOD__);
        $this->saveResponse();

        if ($this->waitForElement(\WebDriverBy::xpath("//p[contains(text(), 'Your session has expired')]"), 0)) {
            return $this->LoadLoginForm() && $this->Login();
        }

        if ($step === "Question") {
            return $this->twoStepVerification();
        }

        return true;
    }

    public function twoStepVerification()
    {
        $this->logger->notice(__METHOD__);
        $this->logger->info('Two-step verification', ['Header' => 3]);

        if (
            $this->http->FindSingleNode('//p[contains(text(), "Please choose how you want to receive your passcode.")]')
            && ($email = $this->waitForElement(\WebDriverBy::xpath("//div[label[@for = 'radio-button-email']]"), 0))
        ) {
            $email->click();
            $sendOTP = $this->waitForElement(\WebDriverBy::xpath("//button[@id = 'send-OTP-button']"), 0);
            $sendOTP->click();

            $this->waitForElement(\WebDriverBy::xpath('//p[contains(text(), "An email with a 6-digit passcode has been sent to")]'),
                5);
            $this->saveResponse();
            $this->holdSession();
        }

        $question = $this->http->FindSingleNode('//p[contains(text(), "An email with a 6-digit passcode has been sent to")]');

        if (!$question) {
            $this->logger->error("something went wrong");

            return false;
        }
        $answerInputs = $this->driver->findElements(\WebDriverBy::xpath("//input[contains(@class, 'otp-input-field__input')]"));
        $this->saveResponse();
        $this->logger->debug("count answer inputs: " . count($answerInputs));

        if (!$question || empty($answerInputs)) {
            $this->logger->error("something went wrong");

            return false;
        }
        $this->saveResponse();

        if (!isset($this->Answers[$question])) {
            $this->AskQuestion($question, null, 'Question');

            return false;
        }
        $this->logger->debug("entering answer...");
        $answer = $this->Answers[$question];

        foreach ($answerInputs as $i => $answerInput) {
            if (!isset($answer[$i])) {
                $this->logger->error("wrong answer");

                break;
            }
            $answerInput->sendKeys($answer[$i]);
            $this->saveResponse();
        }// foreach ($elements as $key => $element)
        unset($this->Answers[$question]);
        $this->saveResponse();

        $this->logger->debug("wait errors...");
        $errorXpath = "//p[
                contains(text(), 'The one-time passcode you have entered is incorrect')
                or contains(text(), ' incorrect attempts to enter your passcode. You have ')
                or contains(text(), 'Sorry, you have exceeded the allowed number of attempts to enter your passcode. Your account is temporarily locked for 30 minutes.')
        ]";
        $error = $this->waitForElement(\WebDriverBy::xpath($errorXpath), 5);
        $this->saveResponse();

        if (!$error && $this->waitForElement(\WebDriverBy::xpath('//div[contains(text(), "Loading")]'), 0)) {
            $error = $this->waitForElement(\WebDriverBy::xpath($errorXpath), 40);
            $this->saveResponse();
        }

        if ($error) {
            $message = $error->getText();

            if (
                strpos($message, 'The one-time passcode you have entered is incorrect') !== false
                || strpos($message, ' incorrect attempts to enter your passcode. You have ') !== false
            ) {
                $this->logger->notice("resetting answers");
                $this->AskQuestion($question, $message, 'Question');
                $this->holdSession();

                return false;
            }

            if (
                strpos($message,
                    'Sorry, you have exceeded the allowed number of attempts to enter your passcode. Your account is temporarily locked for 30 minutes.') !== false
            ) {
                throw new \CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }
        }// if ($error)
        $this->logger->debug("success");

        return true;
    }

    private function loadAuthCookie($isLocal = false, $prevCookies = false)
    {
        // Gosling
        $SSOUser = '62d47f4d09fa9dff2372e1e78531493ca16b61b6';
        $remember = 'LZi0Z-uADs5jYCQO8GzQHts_KX8kYjiM-K7tQ0wOxck';
        $script = "
            var date = new Date();
            date.setTime(date.getTime() + (367*24*60*60*1000));
            document.cookie = 'SSOUser={$SSOUser}; expires='+date.toUTCString()+'; domain=.emirates.com; path=/';
            document.cookie = 'SSOloggedIn=1; expires='+date.toUTCString()+'; domain=.emirates.com; path=/';
            document.cookie = 'remember={$remember}; domain=.emirates.com; expires='+date.toUTCString()+'; path=/';
            document.cookie = 'remember={$remember}; domain=fly2.emirates.com; expires='+date.toUTCString()+'; path=/';
            document.cookie = 'remember={$remember}; domain=.fly2.emirates.com; expires='+date.toUTCString()+'; path=/';
            document.cookie = 'remember={$remember}; domain=.fly10.emirates.com; expires='+date.toUTCString()+'; path=/';
            document.cookie = 'remember={$remember}; domain=auth.emirates.com; expires='+date.toUTCString()+'; path=/';
            document.cookie = 'remember={$remember}; domain=www.emirates.com; expires='+date.toUTCString()+'; path=/';
            document.cookie = 'remember={$remember}; domain=mobile1.emirates.com; expires='+date.toUTCString()+'; path=/';
            document.cookie = 'remember={$remember}; domain=payment.emirates.com; expires='+date.toUTCString()+'; path=/';
            document.cookie = 'remember={$remember}; domain=accounts.emirates.com; expires='+date.toUTCString()+'; path=/';
            document.cookie = 'QuantumMetricSessionID=6f92b41583fad4d3440a31ba73181104; domain=.emirates.com; expires='+date.toUTCString()+'; path=/';
            document.cookie = 'originCountry=\"https://mobile.emirates.com/english\"; domain=mobile.emirates.com; expires='+date.toUTCString()+'; path=/';
            document.cookie = 'localeCookie=en_XX; domain=mobile.emirates.com; expires='+date.toUTCString()+'; path=/';

            ";
        $this->logger->debug("[run script]");
        $this->logger->debug($script, ['pre' => true]);
        $this->driver->executeScript($script);
    }

    private function checkNoFlights(): bool
    {
        $this->logger->notice(__METHOD__);

        if ($msg = $this->http->FindSingleNode("//div[@id='oneWayErrorList']")) {
            $this->logger->error($msg);

            if (stripos($msg, 'either flights are unavailable or seats are not available') !== false
                || stripos($msg, 'either flights are unavailable, or seats are not available') !== false
            ) {
                $this->SetWarning($msg);

                return true;
            }
            $this->sendNotification("check msg // ZM");
            $this->SetWarning($msg);

            return true;
        }

        return false;
    }

    private function enterRequest($fields)
    {
        $this->logger->notice(__METHOD__);
        //        if ($this->http->currentUrl() !== self::START_URL) {
        //            $this->http->GetURL(self::START_URL);
        //        }

        $rewards = $this->waitForElement(\WebDriverBy::xpath('//strong[normalize-space()="Use Classic Rewards"]'), 10);
        $this->saveResponse();

        if (!$rewards) {
            //throw new \CheckRetryNeededException(5, 0);
            return null;
        }

        $rewards = $this->waitForElement(\WebDriverBy::xpath("//div[@id='skywardMilsChkbox']/label"), 3);
        $rewards->click();

        $this->logger->debug('one-way click by script');
        $script = "
                    let view = document.querySelectorAll('md-tab-item[id = \"tab-item-1\"]');
                    if (view.length > 0) {
                        view[0].click();
                    }
            ";
        $this->driver->executeScript($script);

        $this->saveResponse();

        // fill departure
        $from = $this->waitForElement(\WebDriverBy::id("departureId"));
        $from->click();
        $input = $this->waitForElement(\WebDriverBy::id('autocompleteId_fromCity'), 3);
        $input->sendKeys($fields['DepCode']);

        $depFound = $this->waitForElement(\WebDriverBy::xpath("//li[.//span[normalize-space()='{$fields['DepCode']}']]"),
            1);

        if (!$depFound) {
            $this->SetWarning('no flights from ' . $fields['DepCode']);

            return [];
        }
        $depFound->click();

        $to = $this->waitForElement(\WebDriverBy::id("arrivalId"));
        $to->click();
        $input = $this->waitForElement(\WebDriverBy::id('autocompleteId_toCity'), 3);
        $input->sendKeys($fields['ArrCode']);

        $arrFound = $this->waitForElement(\WebDriverBy::xpath("//li[.//span[normalize-space()='{$fields['ArrCode']}']]"),
            1);

        if (!$arrFound) {
            $this->SetWarning('no flights from ' . $fields['DepCode'] . ' to ' . $fields['ArrCode']);

            return [];
        }
        $arrFound->click();

        $this->saveResponse();

        $capinPax = $this->waitForElement(\WebDriverBy::id("cabinPax"), 1);
        $capinPax->click();

        $this->waitForElement(\WebDriverBy::xpath("//span[normalize-space()='Passengers and cabin class']"), 1);

        $checked = (int) ($this->waitForElement(\WebDriverBy::xpath("(//p[normalize-space()='Adults (age 12+)'])[1]/following-sibling::p[last()]"))
            ->getText());

        $this->logger->debug("checked: " . $checked);

        if ($checked !== $fields['Adults']) {
            //            $btnAdtMin = $this->waitForElement(\WebDriverBy::xpath("//span[@class='cabin-pessanger-button-section']/button[contains(@class,'pessanger-minus-button') and contains(@ng-click,'adultCount')]"));
            $btnAdtMin = $this->waitForElement(\WebDriverBy::xpath("(//p[normalize-space()='Adults (age 12+)'])[1]/following-sibling::span[1]/button[1]"));

            $ctrlCounter = 9;

            while ($checked !== 1 && $ctrlCounter > 0) {
                $this->logger->debug("checked --");
                $ctrlCounter--;
                $btnAdtMin->click();
                $checked--;
            }

            if ($fields['Adults'] > 1) {
                $btnAdtPlus = $this->waitForElement(\WebDriverBy::xpath("(//p[normalize-space()='Adults (age 12+)'])[1]/following-sibling::span[1]/button[2]"));

                $ctrlCounter = 9;

                while ($checked < $fields['Adults'] && $ctrlCounter > 0) {
                    $btnAdtPlus->click();
                    $checked++;
                    $ctrlCounter--;
                    $this->logger->debug("checked: " . $checked);
                }
            }
        }

        $cabinString = self::MOBILE_CABIN[$fields['Cabin']];
        $cabin = $this->waitForElement(\WebDriverBy::xpath("(//span[contains(@class,'cabin-class-label')][normalize-space()='{$cabinString}'])[1]/ancestor::span[1]"));

        if (!$cabin) {
            return false;
        }
        $cabin->click();

        $btn = $this->waitForElement(\WebDriverBy::xpath("//button[contains(@class,'cabin-pessanger-proceed-btn')]"));
        $btn->click();

        $this->saveResponse();

        $btn = $this->waitForElement(\WebDriverBy::xpath("//span[normalize-space()='Select date']/ancestor::button[1]"));
        $btn->click();

        // date
        $day = (int) date('d', $fields['DepDate']);
        $numMonth = (int) date('m', $fields['DepDate']) - 1;
        $year = (int) date('Y', $fields['DepDate']);

        $date = $this->waitForElement(\WebDriverBy::xpath("//td[@data-month={$numMonth} and @data-year={$year}]/a[normalize-space()='{$day}']"));

        if (!$date) {
            $this->logger->error('check another date');

            return [];
        }
        $y = $date->getLocation()->getY() - 20;
        $this->driver->executeScript("window.scrollBy(0, $y)");
        $this->saveResponse();
        $date = $this->waitForElement(\WebDriverBy::xpath("//td[@data-month={$numMonth} and @data-year={$year}]/a[normalize-space()='{$day}']"));
        $date->click();

        $btn = $this->waitForElement(\WebDriverBy::id('procdBtn'));

        $btn->click();

        $this->saveResponse();

        $this->savePageTolog();

        return true;
    }

    private function sendMainRequest($fields)
    {
        $this->logger->notice(__METHOD__);

        $rewards = $this->waitForElement(\WebDriverBy::xpath('//strong[normalize-space()="Use Classic Rewards"]'), 10);
        $this->saveResponse();

        if (!$rewards) {
            return null;
        }

        $rewards = $this->waitForElement(\WebDriverBy::xpath("//div[@id='skywardMilsChkbox']/label"), 3);
        $rewards->click();

        $this->logger->debug('one-way click by script');
        $script = "
                    let view = document.querySelectorAll('md-tab-item[id = \"tab-item-1\"]');
                    if (view.length > 0) {
                        view[0].click();
                    }
            ";
        $this->driver->executeScript($script);

        $this->saveResponse();

        // fill departure
        $from = $this->waitForElement(\WebDriverBy::id("departureId"));
        $from->click();
        $input = $this->waitForElement(\WebDriverBy::id('autocompleteId_fromCity'), 3);
        $input->sendKeys($fields['DepCode']);

        $depFound = $this->waitForElement(\WebDriverBy::xpath("//li[.//span[normalize-space()='{$fields['DepCode']}']]"),
            1);

        if (!$depFound) {
            $this->SetWarning('no flights from ' . $fields['DepCode']);

            return [];
        }
        $depFound->click();

        $to = $this->waitForElement(\WebDriverBy::id("arrivalId"));
        $to->click();
        $input = $this->waitForElement(\WebDriverBy::id('autocompleteId_toCity'), 3);
        $input->sendKeys($fields['ArrCode']);

        $arrFound = $this->waitForElement(\WebDriverBy::xpath("//li[.//span[normalize-space()='{$fields['ArrCode']}']]"),
            1);

        if (!$arrFound) {
            $this->SetWarning('no flights from ' . $fields['DepCode'] . ' to ' . $fields['ArrCode']);

            return [];
        }
        $arrFound->click();

        $this->saveResponse();

        $paxCount = $fields['Adults'] . ' Adult' . ($fields['Adults'] == 1 ? '' : 's');
        $payloadArr = [
            'cugoDisabledCabinClass' => true,
            'fromCityAuto' => $depFound->getText(),
            'fromCity' => $fields['DepCode'],
            'toCityAuto' => $arrFound->getText(),
            'toCity' => $fields['ArrCode'],
            'departDay' => date('d', $fields['DepDate']),
            'departMonth' => date('m', $fields['DepDate']),
            'departYear' => date('Y', $fields['DepDate']),
            'returnDay' => '',
            'returnMonth' => '',
            'returnYear' => '',
            'flexiDate' => false,
            'searchType' => 'ON',
            'classType' => self::MOBILE_CLASS_TYPE[$fields['Cabin']],
            'classTypeRadioReturn' => '',
            'classTypeRadioOneWay' => self::MOBILE_CLASS_TYPE[$fields['Cabin']],
            'classTypeRadioMulti' => '',
            'bookingType' => 'Revenue',
            'originInterlineFlag' => false,
            'destInterlineFlag' => false,
            'totalAdults' => $fields['Adults'],
            'totalTeens' => 0,
            'totalChildren' => 0,
            'totalInfants' => 0,
            'promoCode' => '',
            'perishableCode' => '',
            'cugoEmailID' => '',
            'cugoPassword' => '',
            'custIdentifier' => '',
            'airportAutocomplete' => [
                '',
                '',
                '',
                '',
                '',
                '',
            ],
            'departureReturnOne' => $depFound->getText(),
            'arrivalReturnOne' => $arrFound->getText(),
            'cabinPax' => [
                $paxCount . ' in ' . self::MOBILE_CABIN[$fields['Cabin']],
                self::MOBILE_CLASS_TYPE[$fields['Cabin']],
                'Economy'
            ],
            'departureName' => [$depFound->getText(), $arrFound->getText()],
            'arrivalName' => [$arrFound->getText(), ''],
            'selctDtSeg1Name' => '',
            'selctDtSeg2Name' => '',
            'paxCount' => $paxCount
     ];

        $output = [];
        $walk = function( $item, $key, $parent_key = '' ) use ( &$output, &$walk ) {

            is_array( $item )
                ? array_walk( $item, $walk, $key )
                : $output[] = http_build_query( array( $parent_key ?: $key => $item ) );

        };

        array_walk( $payloadArr, $walk );
        $payload = implode( '&', $output );

//        "cugoDisabledCabinClass=true&fromCityAuto=Houston+%28IAH%29&fromCity=IAH&toCityAuto=Abu+Dhabi+%28BUS%29+%28ZVJ%29&toCity=ZVJ&departDay=29&departMonth=08&departYear=2024&returnDay=&returnMonth=&returnYear=&flexiDate=false&searchType=ON&classType=3&classTypeRadioReturn=&classTypeRadioOneWay=3&classTypeRadioMulti=&bookingType=Redeem&originInterlineFlag=false&destInterlineFlag=false&totalAdults=4&totalTeens=0&totalChildren=0&totalInfants=0&promoCode=&perishableCode=&cugoEmailID=&cugoPassword=&custIdentifier=&airportAutocomplete=&departureReturnOne=Houston+%28IAH%29&airportAutocomplete=&arrivalReturnOne=Abu+Dhabi+%28BUS%29+%28ZVJ%29&cabinPax=4+Adults+in+Premium+Economy&airportAutocomplete=&departureName=Houston+%28IAH%29&airportAutocomplete=&arrivalName=Abu+Dhabi+%28BUS%29+%28ZVJ%29&selctDtSeg1Name=&cabinPax=Premium+Economy&airportAutocomplete=&departureName=Abu+Dhabi+%28BUS%29+%28ZVJ%29&airportAutocomplete=&arrivalName=&selctDtSeg2Name=&cabinPax=Economy&paxCount=4+Adults"
//        "cugoDisabledCabinClass=true&fromCityAuto=Houston+%28IAH%29&fromCity=IAH&toCityAuto=Abu+Dhabi+%28BUS%29+%28ZVJ%29&toCity=ZVJ&departDay=12&departMonth=04&departYear=2024&returnDay=&returnMonth=&returnYear=&flexiDate=false&searchType=ON&classType=1&classTypeRadioReturn=&classTypeRadioOneWay=1&classTypeRadioMulti=&bookingType=Redeem&originInterlineFlag=false&destInterlineFlag=false&totalAdults=2&totalTeens=0&totalChildren=0&totalInfants=0&promoCode=&perishableCode=&cugoEmailID=&cugoPassword=&custIdentifier=&airportAutocomplete=&departureReturnOne=Houston+%28IAH%29&airportAutocomplete=&arrivalReturnOne=Abu+Dhabi+%28BUS%29+%28ZVJ%29&cabinPax=2+Adults+in+Business&airportAutocomplete=&departureName=Houston+%28IAH%29&airportAutocomplete=&arrivalName=Abu+Dhabi+%28BUS%29+%28ZVJ%29&selctDtSeg1Name=&cabinPax=Business&airportAutocomplete=&departureName=Abu+Dhabi+%28BUS%29+%28ZVJ%29&airportAutocomplete=&arrivalName=&selctDtSeg2Name=&cabinPax=Economy&paxCount=2+Adults"
//        "cugoDisabledCabinClass=true&fromCityAuto=Houston+%28IAH%29&fromCity=IAH&toCityAuto=Abu+Dhabi+%28BUS%29+%28ZVJ%29&toCity=ZVJ&departDay=28&departMonth=03&departYear=2024&returnDay=&returnMonth=&returnYear=&flexiDate=false&searchType=ON&classType=2&classTypeRadioReturn=&classTypeRadioOneWay=2&classTypeRadioMulti=&bookingType=Redeem&originInterlineFlag=false&destInterlineFlag=false&totalAdults=2&totalTeens=0&totalChildren=0&totalInfants=0&promoCode=&perishableCode=&cugoEmailID=&cugoPassword=&custIdentifier=&airportAutocomplete=&departureReturnOne=Houston+%28IAH%29&airportAutocomplete=&arrivalReturnOne=Abu+Dhabi+%28BUS%29+%28ZVJ%29&cabinPax=2+Adults+in+First&airportAutocomplete=&departureName=Houston+%28IAH%29&airportAutocomplete=&arrivalName=Abu+Dhabi+%28BUS%29+%28ZVJ%29&selctDtSeg1Name=&cabinPax=First&airportAutocomplete=&departureName=Abu+Dhabi+%28BUS%29+%28ZVJ%29&airportAutocomplete=&arrivalName=&selctDtSeg2Name=&cabinPax=Economy&paxCount=2+Adults"
        $this->driver->executeScript('
        await fetch("https://mobile.emirates.com/english/CAB/IBE/searchResults.xhtml", {
    "credentials": "include",
    "headers": {
        "User-Agent": "'.$this->http->getDefaultHeader('User-Agent').'",
        "Accept": "text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,*/*;q=0.8",
        "Accept-Language": "en-US,en;q=0.5",
        "Content-Type": "application/x-www-form-urlencoded",
        "Upgrade-Insecure-Requests": "1",
        "Sec-Fetch-Dest": "document",
        "Sec-Fetch-Mode": "navigate",
        "Sec-Fetch-Site": "same-origin",
        "Sec-Fetch-User": "?1",
        "Pragma": "no-cache",
        "Cache-Control": "no-cache"
    },
    "referrer": "https://mobile.emirates.com/ui/english/index.xhtml",
    "body": "'.$payload.'",
    "method": "POST",
    "mode": "cors"
});
        ');
        sleep(3);

        $this->http->GetURL("https://mobile.emirates.com/app/english/bookaflight/brandedsearch");

        $this->waitForElement(\WebDriverBy::xpath("
        //span[contains(@class,'errorMsgText')]
        | //span[normalize-space()='Search Results']
        "),30);
        return true;
    }

    private function parseRewardFlightsJson($fields, $data, $curlBrowser): array
    {
        $this->logger->notice(__METHOD__);

        $routes = [];

        if (!isset($data->flightOptionDetails) || !is_array($data->flightOptionDetails)) {
            throw new \CheckException('something went wrong', ACCOUNT_ENGINE_ERROR);
        }

        $this->logger->debug("Found " . count($data->flightOptionDetails) . " routes");

        $cabins = array_flip(self::MOBILE_CABIN_CODE);

        foreach ($data->flightOptionDetails as $numRoot => $root) {
            $this->logger->debug("num route: " . $numRoot);

            $stop = count($root->flightDetails) - 1;

            $segments = [];

            foreach ($root->flightDetails as $numSeg => $rootSegment) {
                $segStops = $rootSegment->totalStops;

                $depDate = strtotime($rootSegment->departureTime, strtotime($rootSegment->departureDate));
                $arrDate = strtotime($rootSegment->arrivalTime, strtotime($rootSegment->arrivalDate));
                $segment = [
                    'num_stops' => $segStops,
                    'aircraft'  => $root->aircraftType[$numSeg] ?? $rootSegment->aircraftType,
                    'flight'    => [$rootSegment->flightNumber],
                    'airline'   => $rootSegment->carrierCode,
                    'departure' => [
                        'date'     => date('Y-m-d H:i', $depDate),
                        'dateTime' => $depDate,
                        'airport'  => $rootSegment->fromCityCode,
                    ],
                    'arrival' => [
                        'date'     => date('Y-m-d H:i', $arrDate),
                        'dateTime' => $arrDate,
                        'airport'  => $rootSegment->toCityCode,
                    ],
                    'time' => [
                        'flight'  => null,
                        'layover' => null,
                    ],
                    'cabin' => self::MOBILE_CABIN_CODE[$rootSegment->cabinClass] ?? null,
                ];
                $segments[] = $segment;
            }

            $this->sensorData($curlBrowser);
            $index = $numRoot + 1;
            $curlBrowser->GetUrl("https://mobile.emirates.com/english/restful/mab/loadBrandsDetails.xhtml?index={$index}&searchOrigin=ON&cabinClass={$cabins[$fields['Cabin']]}&bookingType=Redeem&stepCount=0&inBound=false&multiLowestPrice=false&pageIdentifier=searchByPriceResults&routeTypeOrSegNumber=OB&isAjaxJson=true");
            $offersData = $curlBrowser->JsonLog(null, 2, true);

            $offers = $offersData['cabinBrandMap'];
            $currency = $offersData['currencyCode'];
            $this->logger->debug("Found " . count($offers) . " offers");

            foreach ($offers as $cabin => $offer) {
                $cabinMain = self::MOBILE_CABIN_CODE[$cabin] ?? $fields['Cabin'];
                $this->logger->debug("Found " . count($offer['brandDetails']) . " brandDetails");

                foreach ($offer['brandDetails'] as $brand) {
                    if ($brand['soldOutIndicator']) {
                        $this->logger->debug('skip ' . $brand['brandCode'] . ' award: soldOutIndicator');
                        continue;
                    }
                    $cabinClass = array_map("trim", explode(',', $brand['cabinClass']));
                    $segments_ = $segments;

                    if (count($cabinClass) !== count($segments)) {
                        foreach ($segments as $num => $segment) {
                            $segments_[$num]['cabin'] = $cabinMain;
                        }
                    } else {
                        foreach ($segments as $num => $segment) {
                            $segments_[$num]['cabin'] = self::MOBILE_CABIN_CODE[$cabinClass[$num]] ?? $cabinMain;
                        }
                    }

                    $miles = (int) str_replace(',', '', $brand['totalFare']);
                    $taxes = PriceHelper::cost($brand['totalTax']);
                    $route = [
                        'num_stops' => $stop,
                        'times'     => [
                            'flight'  => null,
                            'layover' => null,
                        ],
                        'redemptions' => [
                            'miles'   => intdiv($miles, $fields['Adults']),
                            'program' => $this->AccountFields['ProviderCode'],
                        ],
                        'payments' => [
                            'currency' => $currency,
                            'taxes'    => round($taxes / $fields['Adults'], 2),
                            'fees'     => null,
                        ],
                        'classOfService' => self::MOBILE_CABIN[self::MOBILE_CABIN_CODE[$cabin]],
                        'awardType' => $brand['brandCode'],
                        'connections' => $segments_,
                    ];
                    $routes[] = $route;
                }
            }
        }
        $this->logger->debug('Parsed data:');
        $this->logger->debug(var_export($routes, true), ['pre' => true]);

        return $routes;
    }

    private function savePageTolog()
    {
        $this->logger->notice(__METHOD__);
        $this->http->SetBody($this->driver->executeScript('return document.documentElement.innerHTML;'));
        $this->http->SaveResponse();
    }

    private function sensorData($browser)
    {
        $this->logger->notice(__METHOD__);

        $sensorPostUrl = $this->http->FindSingleNode(' //*[self::a or self::pre][@style="display: none;"]/preceding-sibling::*[self::a or self::pre][@style="display: none;"]/preceding-sibling::script[1]/@src');

        if (!$sensorPostUrl) {
            $this->logger->error("sensor_data URL not found");

            return null;
        }

        $sensorData = [
            // 0
            "7a74G7m23Vrp0o5c9279011.7-1,2,-94,-100,Mozilla/5.0 (Linux; Android 6.0; Nexus 5 Build/MRA58N) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/92.0.4515.159 Mobile Safari/537.36,uaend,12147,20030107,en-US,Gecko,0,0,0,0,401073,7136440,314,474,314,474,314,474,314,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:0,sc:0,wrc:1,isc:0,vib:1,bat:1,x11:0,x12:1,10179,0.447426872223,815033568219.5,0,loc:-1,2,-94,-101,do_en,dm_en,t_en-1,2,-94,-105,-1,2,-94,-102,-1,2,-94,-108,-1,2,-94,-110,0,1,20212,292,308;1,3,20212,292,308,-1;2,4,20219,292,308,-1;3,2,20223,292,308,-1;-1,2,-94,-117,0,2,20088,-1,-1;1,3,20210,-1,-1;-1,2,-94,-111,0,65,-1,-1,-1;-1,2,-94,-109,0,43,-1,-1,-1,-1,-1,-1,-1,-1,-1;-1,2,-94,-114,0,3,20080,292,308;1,4,20210,292,308;2,3,29981,157,405;-1,2,-94,-103,2,7454;3,20077;-1,2,-94,-112,https://mobile.emirates.com/app/english/bookaflight/brandedsearch-1,2,-94,-115,1,83314,40332,65,43,72046,195736,29981,0,1630067136439,16,17437,0,4,2906,3,2,29982,191543,0,26894738834871A3CFDE7F1E6525895A~-1~YAAQVP1zPiDVGjp7AQAAid6ShwYEbx8otMl7pK5w5tnmFRgKDho5vh64AiF2ffl/URe1tUrUyZPBye9ZrTA4nkHFGo8/2pACE3Ljgj8jeuAS3A1DERwTWNdUJZjvjUKvJ8v8LW9yAf8h7ViUJcqi5TtWCVzeCZQXkfBkpt8cjRxBICnIJaRa1MqA90SOyvVta//8AnKulic7TBh8Pwa2IPNxgy6EdSG0DimP8nW9RTyndY2Z7mtRduSDV5kqBbAqgbfwCz/Y0YWX0RM2FFGofNUYjt0qOFyC6o/xoDgxkbpixrPCkIX2aaSFuk/OuxwCnMlMFHQimb7/VEkF9N30fC0sJH2mbuYdPR26CX2b4LTKMZGqVFoPFhZG+h7YKskWdn1kpjaVwRLUDVuJpeJg0vQ/7Mvhz3gqtQ==~-1~-1~-1,38837,426,-955605606,30261693,PiZtE,11052,98,0,-1-1,2,-94,-106,2,5-1,2,-94,-119,20,20,20,20,40,40,40,20,220,20,20,20,20,320,-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,-1,2,-94,-126,-1,2,-94,-127,11321144241322243122-1,2,-94,-70,875325766;54528711;dis;;true;true;true;-180;true;24;24;true;false;-1-1,2,-94,-80,5244-1,2,-94,-116,535232967-1,2,-94,-118,106200-1,2,-94,-129,e7fa8e5f9331739c57ebdd0dd49e398c3662b0b364b74c7c64bf301e2c66dd3a,2.0000000298023224,5c8cf8750d5aa2cdfdd0d627e7e37b97006b59327cf682ce743d825eda183ed2,Google Inc. (Intel),ANGLE (Intel, Intel(R) HD Graphics 630 Direct3D11 vs_5_0 ps_5_0, D3D11-26.20.100.7263),95f5b71fe531f867faa814bdd4050dd8057206d53ecec1163523560525884870,33-1,2,-94,-121,;3;13;0",
            // 1
            "7a74G7m23Vrp0o5c9279011.7-1,2,-94,-100,Mozilla/5.0 (Linux; Android 6.0; Nexus 5 Build/MRA58N) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/92.0.4515.159 Mobile Safari/537.36,uaend,12147,20030107,en-US,Gecko,0,0,0,0,401073,7136440,314,474,314,474,314,474,314,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:0,sc:0,wrc:1,isc:0,vib:1,bat:1,x11:0,x12:1,10179,0.310862498155,815033568219.5,0,loc:-1,2,-94,-101,do_en,dm_en,t_en-1,2,-94,-105,-1,2,-94,-102,-1,2,-94,-108,-1,2,-94,-110,0,1,20212,292,308;1,3,20212,292,308,-1;2,4,20219,292,308,-1;3,2,20223,292,308,-1;4,1,30109,156,405;5,3,30110,156,405,-1;6,4,30117,156,405,-1;7,2,30118,156,405,-1;-1,2,-94,-117,0,2,20088,-1,-1;1,3,20210,-1,-1;2,2,29986,-1,-1;3,3,30107,-1,-1;-1,2,-94,-111,0,65,-1,-1,-1;-1,2,-94,-109,0,43,-1,-1,-1,-1,-1,-1,-1,-1,-1;-1,2,-94,-114,0,3,20080,292,308;1,4,20210,292,308;2,3,29981,157,405;3,4,30106,157,405;4,3,125828,143,446;-1,2,-94,-103,2,7454;3,20077;2,33424;3,125827;-1,2,-94,-112,https://mobile.emirates.com/app/english/bookaflight/brandedsearch-1,2,-94,-115,1,206044,100431,65,43,229145,535664,125828,0,1630067136439,16,17437,0,8,2906,5,4,125831,528024,0,26894738834871A3CFDE7F1E6525895A~-1~YAAQVP1zPlrVGjp7AQAArAWThwa+oV2v12RJvyfkeflmY8UOYB3fgayp3z5ABmbRScm33mFNaB/KGezTlz803exgRxTrxwWW9Rioev8owN6KG94VFwfDhmNnBrStteaQDQ2KoZuVCDXJJjk/KxTg8U5G5imiMH0Nn+fGL/yWyMotvTTK0W+UpE+GNwwit2P7pFYppFaK3x2KRNAzeW2DT7DwnLXS7PioZh0hsl5El5/NHsZYH3jVFlI3OLx0mqN9TNDiVUp+tX/iLSFHEJ/wu/6RaBOoMUo5UCGWAUcpeg0xwFx3Wt04J3MEPLb3CK4RRRwLZP32VpNaaaBMa1MkCHh22wpVLuPdkfQ4gO+aH/jjLTRj5HhoPwhYnBcW6P8vbOSHLUDgdB7ANaI58tE=~-1~-1~-1,36925,426,-955605606,30261693,PiZtE,37110,75,0,-1-1,2,-94,-106,2,7-1,2,-94,-119,20,20,20,20,40,40,40,20,220,20,20,20,20,320,-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,-1,2,-94,-126,-1,2,-94,-127,11321144241322243122-1,2,-94,-70,875325766;54528711;dis;;true;true;true;-180;true;24;24;true;false;-1-1,2,-94,-80,5244-1,2,-94,-116,535232967-1,2,-94,-118,112816-1,2,-94,-129,e7fa8e5f9331739c57ebdd0dd49e398c3662b0b364b74c7c64bf301e2c66dd3a,2.0000000298023224,5c8cf8750d5aa2cdfdd0d627e7e37b97006b59327cf682ce743d825eda183ed2,Google Inc. (Intel),ANGLE (Intel, Intel(R) HD Graphics 630 Direct3D11 vs_5_0 ps_5_0, D3D11-26.20.100.7263),95f5b71fe531f867faa814bdd4050dd8057206d53ecec1163523560525884870,33-1,2,-94,-121,;8;13;0",
            // 2
            "7a74G7m23Vrp0o5c9279011.7-1,2,-94,-100,Mozilla/5.0 (Linux; Android 6.0; Nexus 5 Build/MRA58N) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/92.0.4515.159 Mobile Safari/537.36,uaend,12147,20030107,en-US,Gecko,0,0,0,0,401074,8821052,314,406,314,406,314,406,314,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:0,sc:0,wrc:1,isc:0,vib:1,bat:1,x11:0,x12:1,10179,0.809533705404,815034410526,0,loc:-1,2,-94,-101,do_en,dm_en,t_en-1,2,-94,-105,-1,2,-94,-102,-1,0,0,1,1141,1353,0;-1,0,0,0,2411,2035,0;0,-1,1,1,1145,1902,0;-1,0,0,0,2202,2035,0;0,-1,1,1,926,1683,0;0,-1,1,1,806,806,0;-1,0,0,0,2411,2035,0;0,-1,1,1,1145,1357,0;-1,0,0,0,2202,2035,0;0,-1,1,1,926,1138,0;0,-1,0,1,1059,1444,0;0,-1,1,1,949,806,0;-1,0,0,0,2411,2035,0;0,-1,1,1,1145,1357,0;-1,0,0,0,2202,2035,0;0,-1,0,1,926,1138,0;0,-1,0,1,1060,1445,0;0,-1,1,1,950,806,0;0,-1,1,1,850,850,0;-1,2,-94,-108,-1,2,-94,-110,0,1,3501,153,371;1,3,3502,153,371,-1;2,4,3510,153,371,-1;3,2,3517,153,371,-1;4,1,7082,291,118;5,3,7083,291,118,-1;6,4,7092,291,118,-1;7,2,7097,291,118,-1;8,1,8554,188,378;9,3,8554,188,378,-1;10,4,8561,188,378,-1;11,2,8565,188,378,-1;12,1,13136,182,439;13,3,13136,182,439,1500;14,4,13145,182,439,1500;15,2,13149,182,439,1500;16,1,17388,244,352;17,3,17389,244,352,-1;18,4,17398,244,352,-1;19,2,17408,244,352,-1;-1,2,-94,-117,0,2,3371,-1,-1;1,3,3499,-1,-1;2,2,6954,-1,-1;3,3,7080,-1,-1;4,2,8439,-1,-1;5,3,8551,-1,-1;6,2,13011,-1,-1;7,3,13133,-1,-1;8,2,17270,-1,-1;9,3,17384,-1,-1;-1,2,-94,-111,0,356,-1,-1,-1;-1,2,-94,-109,0,356,-1,-1,-1,-1,-1,-1,-1,-1,-1;-1,2,-94,-114,0,3,3362,153,371;1,4,3495,153,371;2,3,6946,290,118;3,4,7079,290,118;4,3,8420,189,378;5,4,8550,189,378;6,3,12999,182,439;7,4,13132,182,439;8,3,17254,244,351;9,4,17383,244,351;10,3,22768,140,363;-1,2,-94,-103,2,21197;3,22765;-1,2,-94,-112,https://mobile.emirates.com/ui/english/index.xhtml#/search/fromhome-1,2,-94,-115,1,209903,98774,356,356,127414,436739,22768,0,1630068821052,24,17438,0,20,2906,11,10,22771,419559,0,26894738834871A3CFDE7F1E6525895A~-1~YAAQVP1zPvLzGjp7AQAADoishwbpSOe4fiNz1xhX057rSaSZ0GDWd/A7n9S6spAZ0O8xCUXBS85wSsLPst36MGdjpLY6CXzl1fLezsm9mFFsZY/M/ZjvUzX5wIzeMbBJCwWS3/J/NGe/Xn/VcVKqlA+HEobsHPYdLKVn5VDgF6PpCfNIdWPbpkSVXeuo83uHh0+D3b2ZwHMCvkk70qf9cC6Bz4uuRUxZd/qJxFJWwS+KOa8M6jh3VlwA6owAblQ6PSh0FMqju/bJ4HKEGCuvxqK4yMoHlnkn6sOX/rCNIWoIXNXbNtnClSYgakY02PlWxD6c+koZpJ9rCasba77nmfPVvKUxOL2yLpKrVfzgInH9cCfD/3h9ORL4W4AG7OTdskiLequa1DgvHXu5gkpkVlbRgHkm9wZ4aw==~-1~-1~-1,38848,786,255354114,30261693,PiZtE,80573,49,0,-1-1,2,-94,-106,2,13-1,2,-94,-119,20,40,40,40,80,60,40,20,20,20,20,20,40,460,-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,-1,2,-94,-126,-1,2,-94,-127,11321144241322243122-1,2,-94,-70,875325766;54528711;dis;;true;true;true;-180;true;24;24;true;false;-1-1,2,-94,-80,5244-1,2,-94,-116,26463162-1,2,-94,-118,155064-1,2,-94,-129,c2a6412211b1d5181874a81afe342066c58780a388917c6aac598b50fc67440c,2.0000000298023224,5c8cf8750d5aa2cdfdd0d627e7e37b97006b59327cf682ce743d825eda183ed2,Google Inc. (Intel),ANGLE (Intel, Intel(R) HD Graphics 630 Direct3D11 vs_5_0 ps_5_0, D3D11-26.20.100.7263),95f5b71fe531f867faa814bdd4050dd8057206d53ecec1163523560525884870,33-1,2,-94,-121,;8;9;0",
            // 3
            "7a74G7m23Vrp0o5c9279011.7-1,2,-94,-100,Mozilla/5.0 (Linux; Android 6.0; Nexus 5 Build/MRA58N) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/92.0.4515.159 Mobile Safari/537.36,uaend,12147,20030107,en-US,Gecko,0,0,0,0,401074,8846425,314,406,314,406,314,406,314,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:0,sc:0,wrc:1,isc:0,vib:1,bat:1,x11:0,x12:1,10179,0.931936314465,815034423212,0,loc:-1,2,-94,-101,do_en,dm_en,t_en-1,2,-94,-105,-1,2,-94,-102,-1,2,-94,-108,-1,2,-94,-110,0,1,69058,292,197;1,3,69058,292,197,-1;2,4,69066,292,197,-1;3,2,69069,292,197,-1;-1,2,-94,-117,0,2,68940,-1,-1;1,3,69055,-1,-1;-1,2,-94,-111,0,48,-1,-1,-1;-1,2,-94,-109,0,45,-1,-1,-1,-1,-1,-1,-1,-1,-1;-1,2,-94,-114,0,3,68931,292,197;1,4,69055,292,197;2,3,76942,128,601;-1,2,-94,-103,2,12102;3,68929;2,75596;3,76942;-1,2,-94,-112,https://mobile.emirates.com/app/english/bookaflight/brandedsearch-1,2,-94,-115,1,278255,138029,48,45,216648,632961,76942,0,1630068846424,17,17438,0,4,2906,3,2,76944,629267,0,26894738834871A3CFDE7F1E6525895A~-1~YAAQVP1zPnD1Gjp7AQAAVbWthwYst8kv5eag7/1TRo7bFYrNbWgSz4ILn2H4HupQBirxV8WkMFXeM4dkHK+Oj80SGi6H18O/3/uECmVjE/956QUQ4M1BS22aED9JKQEkMoOpQdVgXdTc4o67wEEww9l9D4ijCSyZJbVY8OTQCSDJY5dY8kBuQ5D3r9tMhBRO9N+oEFtdj3w5OOAJ9494X98VesqbwlRNdnsF/ynvErWGWUxps0YYO1i7JvlFptaXv/HO3uXNaHa+lG9rjVztqN8MXHH3c6Uv4PQ2KmlKeEs9oh6Ob/GE8xuaLT5q9NKLuEUDmehOkK4aCyzgeuE9iUA7oRoS4LXyrjBglAqO8tJ/4/70/P+lqJlKzS3f/a7G7hNaK99ndqQTInoos0v+15nuMx5dPr8fQw==~-1~-1~-1,37745,244,-974736656,30261693,PiZtE,66560,66,0,-1-1,2,-94,-106,2,5-1,2,-94,-119,20,20,20,20,20,20,20,20,20,0,0,0,20,220,-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,-1,2,-94,-126,-1,2,-94,-127,11321144241322243122-1,2,-94,-70,875325766;54528711;dis;;true;true;true;-180;true;24;24;true;false;-1-1,2,-94,-80,5244-1,2,-94,-116,663481995-1,2,-94,-118,106030-1,2,-94,-129,e7fa8e5f9331739c57ebdd0dd49e398c3662b0b364b74c7c64bf301e2c66dd3a,2.0000000298023224,5c8cf8750d5aa2cdfdd0d627e7e37b97006b59327cf682ce743d825eda183ed2,Google Inc. (Intel),ANGLE (Intel, Intel(R) HD Graphics 630 Direct3D11 vs_5_0 ps_5_0, D3D11-26.20.100.7263),95f5b71fe531f867faa814bdd4050dd8057206d53ecec1163523560525884870,33-1,2,-94,-121,;4;9;0",
        ];

        $secondSensorData = [
            // 0
            "7a74G7m23Vrp0o5c9279011.7-1,2,-94,-100,Mozilla/5.0 (Linux; Android 6.0; Nexus 5 Build/MRA58N) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/92.0.4515.159 Mobile Safari/537.36,uaend,12147,20030107,en-US,Gecko,0,0,0,0,401073,7136440,314,474,314,474,314,474,314,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:0,sc:0,wrc:1,isc:0,vib:1,bat:1,x11:0,x12:1,10179,0.945165575472,815033568219.5,0,loc:-1,2,-94,-101,do_en,dm_en,t_en-1,2,-94,-105,-1,2,-94,-102,-1,2,-94,-108,-1,2,-94,-110,0,1,20212,292,308;1,3,20212,292,308,-1;2,4,20219,292,308,-1;3,2,20223,292,308,-1;4,1,30109,156,405;5,3,30110,156,405,-1;-1,2,-94,-117,0,2,20088,-1,-1;1,3,20210,-1,-1;2,2,29986,-1,-1;3,3,30107,-1,-1;-1,2,-94,-111,0,65,-1,-1,-1;-1,2,-94,-109,0,43,-1,-1,-1,-1,-1,-1,-1,-1,-1;-1,2,-94,-114,0,3,20080,292,308;1,4,20210,292,308;2,3,29981,157,405;3,4,30106,157,405;-1,2,-94,-103,2,7454;3,20077;-1,2,-94,-112,https://mobile.emirates.com/app/english/bookaflight/brandedsearch-1,2,-94,-115,1,144668,100431,65,43,102721,347864,30110,0,1630067136439,16,17437,0,6,2906,4,4,30111,341961,0,26894738834871A3CFDE7F1E6525895A~-1~YAAQVP1zPiDVGjp7AQAAid6ShwYEbx8otMl7pK5w5tnmFRgKDho5vh64AiF2ffl/URe1tUrUyZPBye9ZrTA4nkHFGo8/2pACE3Ljgj8jeuAS3A1DERwTWNdUJZjvjUKvJ8v8LW9yAf8h7ViUJcqi5TtWCVzeCZQXkfBkpt8cjRxBICnIJaRa1MqA90SOyvVta//8AnKulic7TBh8Pwa2IPNxgy6EdSG0DimP8nW9RTyndY2Z7mtRduSDV5kqBbAqgbfwCz/Y0YWX0RM2FFGofNUYjt0qOFyC6o/xoDgxkbpixrPCkIX2aaSFuk/OuxwCnMlMFHQimb7/VEkF9N30fC0sJH2mbuYdPR26CX2b4LTKMZGqVFoPFhZG+h7YKskWdn1kpjaVwRLUDVuJpeJg0vQ/7Mvhz3gqtQ==~-1~-1~-1,38837,426,-955605606,30261693,PiZtE,20695,62,0,-1-1,2,-94,-106,1,6-1,2,-94,-119,20,20,20,20,40,40,40,20,220,20,20,20,20,320,-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,-1,2,-94,-126,-1,2,-94,-127,11321144241322243122-1,2,-94,-70,875325766;54528711;dis;;true;true;true;-180;true;24;24;true;false;-1-1,2,-94,-80,5244-1,2,-94,-116,535232967-1,2,-94,-118,110717-1,2,-94,-129,e7fa8e5f9331739c57ebdd0dd49e398c3662b0b364b74c7c64bf301e2c66dd3a,2.0000000298023224,5c8cf8750d5aa2cdfdd0d627e7e37b97006b59327cf682ce743d825eda183ed2,Google Inc. (Intel),ANGLE (Intel, Intel(R) HD Graphics 630 Direct3D11 vs_5_0 ps_5_0, D3D11-26.20.100.7263),95f5b71fe531f867faa814bdd4050dd8057206d53ecec1163523560525884870,33-1,2,-94,-121,;5;13;0",
            // 1
            "7a74G7m23Vrp0o5c9279011.7-1,2,-94,-100,Mozilla/5.0 (Linux; Android 6.0; Nexus 5 Build/MRA58N) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/92.0.4515.159 Mobile Safari/537.36,uaend,12147,20030107,en-US,Gecko,0,0,0,0,401073,7136440,314,474,314,474,314,474,314,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:0,sc:0,wrc:1,isc:0,vib:1,bat:1,x11:0,x12:1,10179,0.13543226267,815033568219.5,0,loc:-1,2,-94,-101,do_en,dm_en,t_en-1,2,-94,-105,-1,2,-94,-102,-1,2,-94,-108,-1,2,-94,-110,0,1,20212,292,308;1,3,20212,292,308,-1;2,4,20219,292,308,-1;3,2,20223,292,308,-1;4,1,30109,156,405;5,3,30110,156,405,-1;6,4,30117,156,405,-1;7,2,30118,156,405,-1;8,1,125960,143,446;9,3,125960,143,446,-1;-1,2,-94,-117,0,2,20088,-1,-1;1,3,20210,-1,-1;2,2,29986,-1,-1;3,3,30107,-1,-1;4,2,125845,-1,-1;5,3,125958,-1,-1;-1,2,-94,-111,0,65,-1,-1,-1;-1,2,-94,-109,0,43,-1,-1,-1,-1,-1,-1,-1,-1,-1;-1,2,-94,-114,0,3,20080,292,308;1,4,20210,292,308;2,3,29981,157,405;3,4,30106,157,405;4,3,125828,143,446;5,4,125958,143,446;-1,2,-94,-103,2,7454;3,20077;2,33424;3,125827;-1,2,-94,-112,https://mobile.emirates.com/app/english/bookaflight/brandedsearch-1,2,-94,-115,1,459163,352244,65,43,355701,1167152,125960,0,1630067136439,16,17437,0,10,2906,6,6,125962,1157705,0,26894738834871A3CFDE7F1E6525895A~-1~YAAQVP1zPlrVGjp7AQAArAWThwa+oV2v12RJvyfkeflmY8UOYB3fgayp3z5ABmbRScm33mFNaB/KGezTlz803exgRxTrxwWW9Rioev8owN6KG94VFwfDhmNnBrStteaQDQ2KoZuVCDXJJjk/KxTg8U5G5imiMH0Nn+fGL/yWyMotvTTK0W+UpE+GNwwit2P7pFYppFaK3x2KRNAzeW2DT7DwnLXS7PioZh0hsl5El5/NHsZYH3jVFlI3OLx0mqN9TNDiVUp+tX/iLSFHEJ/wu/6RaBOoMUo5UCGWAUcpeg0xwFx3Wt04J3MEPLb3CK4RRRwLZP32VpNaaaBMa1MkCHh22wpVLuPdkfQ4gO+aH/jjLTRj5HhoPwhYnBcW6P8vbOSHLUDgdB7ANaI58tE=~-1~-1~-1,36925,426,-955605606,30261693,PiZtE,46743,91,0,-1-1,2,-94,-106,1,8-1,2,-94,-119,20,20,20,20,40,40,40,20,220,20,20,20,20,320,-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,-1,2,-94,-126,-1,2,-94,-127,11321144241322243122-1,2,-94,-70,875325766;54528711;dis;;true;true;true;-180;true;24;24;true;false;-1-1,2,-94,-80,5244-1,2,-94,-116,535232967-1,2,-94,-118,117659-1,2,-94,-129,e7fa8e5f9331739c57ebdd0dd49e398c3662b0b364b74c7c64bf301e2c66dd3a,2.0000000298023224,5c8cf8750d5aa2cdfdd0d627e7e37b97006b59327cf682ce743d825eda183ed2,Google Inc. (Intel),ANGLE (Intel, Intel(R) HD Graphics 630 Direct3D11 vs_5_0 ps_5_0, D3D11-26.20.100.7263),95f5b71fe531f867faa814bdd4050dd8057206d53ecec1163523560525884870,33-1,2,-94,-121,;4;13;0",
            // 2
            "7a74G7m23Vrp0o5c9279011.7-1,2,-94,-100,Mozilla/5.0 (Linux; Android 6.0; Nexus 5 Build/MRA58N) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/92.0.4515.159 Mobile Safari/537.36,uaend,12147,20030107,en-US,Gecko,0,0,0,0,401074,8821052,314,406,314,406,314,406,314,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:0,sc:0,wrc:1,isc:0,vib:1,bat:1,x11:0,x12:1,10179,0.849750594424,815034410526,0,loc:-1,2,-94,-101,do_en,dm_en,t_en-1,2,-94,-105,-1,2,-94,-102,-1,0,0,1,1141,1353,0;-1,0,0,0,2411,2035,0;0,-1,1,1,1145,1902,0;-1,0,0,0,2202,2035,0;0,-1,1,1,926,1683,0;0,-1,1,1,806,806,0;-1,0,0,0,2411,2035,0;0,-1,1,1,1145,1357,0;-1,0,0,0,2202,2035,0;0,-1,1,1,926,1138,0;0,-1,0,1,1059,1444,0;0,-1,1,1,949,806,0;-1,0,0,0,2411,2035,0;0,-1,1,1,1145,1357,0;-1,0,0,0,2202,2035,0;0,-1,0,1,926,1138,0;0,-1,0,1,1060,1445,0;0,-1,1,1,950,806,0;0,-1,1,1,850,850,0;-1,2,-94,-108,-1,2,-94,-110,0,1,3501,153,371;1,3,3502,153,371,-1;2,4,3510,153,371,-1;3,2,3517,153,371,-1;4,1,7082,291,118;5,3,7083,291,118,-1;6,4,7092,291,118,-1;7,2,7097,291,118,-1;8,1,8554,188,378;9,3,8554,188,378,-1;10,4,8561,188,378,-1;11,2,8565,188,378,-1;12,1,13136,182,439;13,3,13136,182,439,1500;14,4,13145,182,439,1500;15,2,13149,182,439,1500;16,1,17388,244,352;17,3,17389,244,352,-1;18,4,17398,244,352,-1;19,2,17408,244,352,-1;20,1,22899,140,363;21,3,22899,140,363,828;-1,2,-94,-117,0,2,3371,-1,-1;1,3,3499,-1,-1;2,2,6954,-1,-1;3,3,7080,-1,-1;4,2,8439,-1,-1;5,3,8551,-1,-1;6,2,13011,-1,-1;7,3,13133,-1,-1;8,2,17270,-1,-1;9,3,17384,-1,-1;10,2,22780,-1,-1;11,3,22897,-1,-1;-1,2,-94,-111,0,356,-1,-1,-1;-1,2,-94,-109,0,356,-1,-1,-1,-1,-1,-1,-1,-1,-1;-1,2,-94,-114,0,3,3362,153,371;1,4,3495,153,371;2,3,6946,290,118;3,4,7079,290,118;4,3,8420,189,378;5,4,8550,189,378;6,3,12999,182,439;7,4,13132,182,439;8,3,17254,244,351;9,4,17383,244,351;10,3,22768,140,363;11,4,22896,140,363;-1,2,-94,-103,2,21197;3,22765;-1,2,-94,-112,https://mobile.emirates.com/ui/english/index.xhtml#/search/fromhome-1,2,-94,-115,1,256752,144473,356,356,150828,552701,22899,0,1630068821052,24,17438,0,22,2906,12,12,22901,533930,0,26894738834871A3CFDE7F1E6525895A~-1~YAAQVP1zPvLzGjp7AQAADoishwbpSOe4fiNz1xhX057rSaSZ0GDWd/A7n9S6spAZ0O8xCUXBS85wSsLPst36MGdjpLY6CXzl1fLezsm9mFFsZY/M/ZjvUzX5wIzeMbBJCwWS3/J/NGe/Xn/VcVKqlA+HEobsHPYdLKVn5VDgF6PpCfNIdWPbpkSVXeuo83uHh0+D3b2ZwHMCvkk70qf9cC6Bz4uuRUxZd/qJxFJWwS+KOa8M6jh3VlwA6owAblQ6PSh0FMqju/bJ4HKEGCuvxqK4yMoHlnkn6sOX/rCNIWoIXNXbNtnClSYgakY02PlWxD6c+koZpJ9rCasba77nmfPVvKUxOL2yLpKrVfzgInH9cCfD/3h9ORL4W4AG7OTdskiLequa1DgvHXu5gkpkVlbRgHkm9wZ4aw==~-1~-1~-1,38848,786,255354114,30261693,PiZtE,91955,93,0,-1-1,2,-94,-106,1,14-1,2,-94,-119,20,40,40,40,80,60,40,20,20,20,20,20,40,460,-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,-1,2,-94,-126,-1,2,-94,-127,11321144241322243122-1,2,-94,-70,875325766;54528711;dis;;true;true;true;-180;true;24;24;true;false;-1-1,2,-94,-80,5244-1,2,-94,-116,26463162-1,2,-94,-118,159811-1,2,-94,-129,c2a6412211b1d5181874a81afe342066c58780a388917c6aac598b50fc67440c,2.0000000298023224,5c8cf8750d5aa2cdfdd0d627e7e37b97006b59327cf682ce743d825eda183ed2,Google Inc. (Intel),ANGLE (Intel, Intel(R) HD Graphics 630 Direct3D11 vs_5_0 ps_5_0, D3D11-26.20.100.7263),95f5b71fe531f867faa814bdd4050dd8057206d53ecec1163523560525884870,33-1,2,-94,-121,;4;9;0",
            // 3
            "7a74G7m23Vrp0o5c9279011.7-1,2,-94,-100,Mozilla/5.0 (Linux; Android 6.0; Nexus 5 Build/MRA58N) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/92.0.4515.159 Mobile Safari/537.36,uaend,12147,20030107,en-US,Gecko,0,0,0,0,401074,8846425,314,406,314,406,314,406,314,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:0,sc:0,wrc:1,isc:0,vib:1,bat:1,x11:0,x12:1,10179,0.566391340283,815034423212,0,loc:-1,2,-94,-101,do_en,dm_en,t_en-1,2,-94,-105,-1,2,-94,-102,-1,2,-94,-108,-1,2,-94,-110,0,1,69058,292,197;1,3,69058,292,197,-1;2,4,69066,292,197,-1;3,2,69069,292,197,-1;4,1,77072,128,601;5,3,77073,128,601,-1;-1,2,-94,-117,0,2,68940,-1,-1;1,3,69055,-1,-1;2,2,76948,-1,-1;3,3,77070,-1,-1;-1,2,-94,-111,0,48,-1,-1,-1;-1,2,-94,-109,0,45,-1,-1,-1,-1,-1,-1,-1,-1,-1;-1,2,-94,-114,0,3,68931,292,197;1,4,69055,292,197;2,3,76942,128,601;3,4,77067,128,601;-1,2,-94,-103,2,12102;3,68929;2,75596;3,76942;-1,2,-94,-112,https://mobile.emirates.com/app/english/bookaflight/brandedsearch-1,2,-94,-115,1,433871,292053,48,45,294451,1020404,77073,0,1630068846424,17,17438,0,6,2906,4,4,77075,1014497,0,26894738834871A3CFDE7F1E6525895A~-1~YAAQVP1zPnD1Gjp7AQAAVbWthwYst8kv5eag7/1TRo7bFYrNbWgSz4ILn2H4HupQBirxV8WkMFXeM4dkHK+Oj80SGi6H18O/3/uECmVjE/956QUQ4M1BS22aED9JKQEkMoOpQdVgXdTc4o67wEEww9l9D4ijCSyZJbVY8OTQCSDJY5dY8kBuQ5D3r9tMhBRO9N+oEFtdj3w5OOAJ9494X98VesqbwlRNdnsF/ynvErWGWUxps0YYO1i7JvlFptaXv/HO3uXNaHa+lG9rjVztqN8MXHH3c6Uv4PQ2KmlKeEs9oh6Ob/GE8xuaLT5q9NKLuEUDmehOkK4aCyzgeuE9iUA7oRoS4LXyrjBglAqO8tJ/4/70/P+lqJlKzS3f/a7G7hNaK99ndqQTInoos0v+15nuMx5dPr8fQw==~-1~-1~-1,37745,244,-974736656,30261693,PiZtE,102944,80,0,-1-1,2,-94,-106,1,6-1,2,-94,-119,20,20,20,20,20,20,20,20,20,0,0,0,20,220,-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,-1,2,-94,-126,-1,2,-94,-127,11321144241322243122-1,2,-94,-70,875325766;54528711;dis;;true;true;true;-180;true;24;24;true;false;-1-1,2,-94,-80,5244-1,2,-94,-116,663481995-1,2,-94,-118,110561-1,2,-94,-129,e7fa8e5f9331739c57ebdd0dd49e398c3662b0b364b74c7c64bf301e2c66dd3a,2.0000000298023224,5c8cf8750d5aa2cdfdd0d627e7e37b97006b59327cf682ce743d825eda183ed2,Google Inc. (Intel),ANGLE (Intel, Intel(R) HD Graphics 630 Direct3D11 vs_5_0 ps_5_0, D3D11-26.20.100.7263),95f5b71fe531f867faa814bdd4050dd8057206d53ecec1163523560525884870,33-1,2,-94,-121,;9;9;0",
        ];

        $this->http->NormalizeURL($sensorPostUrl);

        if (count($sensorData) != count($secondSensorData)) {
            $this->logger->error("wrong sensor data values");

            return null;
        }

        $key = array_rand($sensorData);
        $this->logger->notice("key: {$key}");

        $browser->RetryCount = 0;
        $data = [
            'sensor_data' => $sensorData[$key],
        ];
        $headers = [
            "Accept"       => "*/*",
            "Content-Type" => "text/plain;charset=UTF-8",
            "Referer"      => "https://mobile.emirates.com/app/english/bookaflight/brandedsearch",
        ];
        $browser->PostURL($sensorPostUrl, json_encode($data), $headers);
        $browser->JsonLog();
        sleep(1);
        $data = [
            'sensor_data' => $secondSensorData[$key],
        ];
        $browser->PostURL($sensorPostUrl, json_encode($data), $headers);
        $browser->RetryCount = 2;
        $browser->JsonLog();
        sleep(1);
    }
}
