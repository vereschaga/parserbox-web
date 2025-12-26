<?php

use AwardWallet\Common\Selenium\FingerprintFactory;
use AwardWallet\Common\Selenium\FingerprintRequest;
use AwardWallet\Engine\ProxyList;

class TAccountCheckerLivingsocial extends TAccountChecker
{
    use PriceTools;
    use SeleniumCheckerHelper;
    use ProxyList;

    public $mainBalance = 0;

    public $regionOptions = [
        ""            => "Select your country",
        "UK"          => "UK",
        "USA"         => "USA",
    ];
    protected $facebook;
    /**
     * @var CaptchaRecognizer
     */
    private $recognizer;

    public function __call($method, $params)
    {
        if (method_exists(CouponHelper::class, $method)) {
            return call_user_func_array([CouponHelper::class, $method], $params);
        }
    }

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->http->FilterHTML = false;
    }

    public function TuneFormFields(&$arFields, $values = null)
    {
        parent::TuneFormFields($arFields);
        // Disable facebook
        $arFields["Login2"]["Options"] = $this->regionOptions;
        /*$arFields['Login2']['Options'] = array(
            "basic" => "Basic",
            "facebook" => "With Facebook",
        );*/
    }

    public static function FormatBalance($fields, $properties)
    {
        if (isset($properties['Currency'])) {
            switch ($properties['Currency']) {
                case 'EUR':
                    return formatFullBalance($fields['Balance'], $fields['ProviderCode'], "€%0.2f");

                case 'USD':
                    return formatFullBalance($fields['Balance'], $fields['ProviderCode'], "$%0.2f");

                default:
                    return formatFullBalance($fields['Balance'], $fields['ProviderCode'], "%0.2f " . $properties['Currency']);
            }
        }

        return parent::FormatBalance($fields, $properties);
    }

    /*function GetRedirectParams($targetURL = NULL){
        $arg = parent::GetRedirectParams($targetURL);
        if ($this->AccountFields['Login2'] != "facebook") {
            $arg["NoCookieURL"] = true;
            return $arg;
        }

        $arg['PostValues'] = array();
        $arg['RequestMethod'] = 'GET';
        $arg['URL'] = $this->facebook->getAutoLoginLink();
        $arg['SuccessURL'] = 'https://livingsocial.com';
        return $arg;
    }*/

    public function UpdateGetRedirectParams(&$arg)
    {
        switch ($this->AccountFields['Login2']) {
            case 'UK':
                $redirectURL = "https://secure.livingsocial.co.uk/login";

                break;

            default:
                $redirectURL = "https://www.livingsocial.com/login";

                break;
        }
        $arg["RedirectURL"] = $redirectURL;
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->logger->notice("Region => {$this->AccountFields['Login2']}");
        // Please enter a valid email address.
        if (filter_var($this->AccountFields['Login'], FILTER_VALIDATE_EMAIL) === false) {
            throw new CheckException("Please enter a valid email address.", ACCOUNT_INVALID_PASSWORD);
        }

        // UK
        if ($this->AccountFields['Login2'] == 'UK') {
            $this->setProxyBrightData(null, 'static', 'uk');
//            $this->http->SetProxy($this->proxyUK());

//            $this->http->GetURL("https://secure.livingsocial.co.uk/login");
//            $this->challengeCaptchaForm();

//            $allCookies = array_merge($this->http->GetCookies("secure.livingsocial.co.uk"), $this->http->GetCookies("secure.livingsocial.co.uk", "/", true));
//            $allCookies = array_merge($allCookies, $this->http->GetCookies(".livingsocial.co.uk"), $this->http->GetCookies(".livingsocial.co.uk", "/", true));
//            $allCookies = array_merge($allCookies, $this->http->GetCookies("www.livingsocial.co.uk"), $this->http->GetCookies("www.livingsocial.co.uk", "/", true));
//            $this->logger->debug(var_export($allCookies, true), ['pre' => true]);

            $selenium = clone $this;
            $retry = false;
            $this->http->brotherBrowser($selenium->http);

            try {
                $selenium->UseSelenium();
                $selenium->http->driver->dontSaveStateOnStop();

                $selenium->useGoogleChrome(SeleniumFinderRequest::CHROME_100);
                $selenium->useCache();
                $selenium->http->saveScreenshots = true;

                $selenium->http->start();
                $selenium->Start();

                try {
                    $selenium->http->GetURL("https://secure.livingsocial.co.uk/login");
                } catch (TimeOutException | ScriptTimeoutException | Facebook\WebDriver\Exception\TimeoutException $e) {
                    $this->logger->error("Exception: " . $e->getMessage());
                    $selenium->driver->executeScript('window.stop();');
                }
//
//                $this->logger->debug("set cookies...");
//                $selenium->driver->manage()->deleteAllCookies();
//                foreach ($allCookies as $key => $value) {
//                    $selenium->driver->manage()->addCookie(['name' => $key, 'value' => $value, 'domain' => ".livingsocial.co.uk"]);
//                }
//
//                $selenium->http->GetURL("https://secure.livingsocial.co.uk/login");

                $loginInput = $selenium->waitForElement(WebDriverBy::xpath('//input[@name = "email"]'), 5);
                $this->savePageToLogs($selenium);

                if (!$loginInput && $this->http->ParseForm("challenge-form")) {
                    $selenium->driver->executeScript('
                        let captcha = \'W0_eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJwYXNza2V5IjoiVE5iajZydU9tRUVCeklDbDBoeHZObHFCKzF6VjlUakY1akhUOEhZeENNR1pqSzFZY2h6eEtDZDZUa1hqYzVVK0xaNHZiWHdzbFhTV1hnT0lUdG5CRmJWWTZOLzIxWWJFcDFpbWJkWno4cVdEU1d6aW1VUkpDZGNwRWd0UTRnM3VZdWhnd0tLNDMveFVybVR3LzRzYVVnQnpaOVplZXozYU5RenI5c0dyUGFSb3IzSCtIcFJhMFBETktvSDQ5L2RHc2ZOenRuMnNZQ2Y0bTgzcUQ5VmVXQlk0cWRHNVlIYlp2MEEwUjBMcEk2b2tDWDJFK1ZuMVlTSHBweEc1dmFQTGVQWGdqWXpLN0h1TkV3Z21ibHNoLzBCMHZycU9NeFlnSkNxbld1QkovMFJIUEMvOXFrcTNnZGJkMGhYdm5uWjNybnptTEtRRXM1NDFtMEhYQUNNaDZCeWJOMUJnNFZxYWNheTZpTDF2bmFGUXJqSmJza1ZycjdvUlY4Uythc1BBam5oTFRKU0FmbSttQkRyWGxCTm0zWWRtK0NpN1lXNm9GS2pqcUEzb3h1YWlhSUVoK0R5bExxQ29aM1NLWW8va1luM1FvM2tsNDVJbWk0Mll0Q3BuV1VIMFhoVEs1MTEyK2k4MWtYZFkxa2V4SHlVRWxDVGQ1ZXFiTlptQkI0TkJVWisxUlp2d1lWemNSczN0aWlqemlFMkZSMzJheGVxQndneWFJTXp4Z3htbVZYQlJrTUdOWDVxUVg5L0tma0hKUUdpV0N0ZUhLbTkzZjRFRG9pYjN1MFhYc3hCcEk3Q0cvK2pUSTZWTnJ4UjlCaUZEaFkzL1VzYzNZVFZMWXJpZDdkYzdWZnZaK1pTZTA5dlc2TWxoYUxkU2FKNVlLeEtvT1Q1Ynk2QzZ4OWFWSnlqclM0MDlubDBCQm9hQ1J0ditKeFp0YU8yTHhmWVhhanU3TzdGSXZGVlhMTGNRazROUDFNSTJDUmZ5Y1lUNHMxZlRLZStZcGRBOGgxcDBwOXVOUWs5WUY1RjViWEQ2ZHNCb0wvZ1VIMTR0WFZRRk5tVGc0RWhudGwrR0lNaDVMaHhyV2tQQUZBcElNVC9waVVxUytHTWhMNUZWeGpaWU52LzNIcVJjeDRWNklNUHZtSzlESktDWjF3eUUyd2cwQ1R3OTNodnJXVUJsL2JRSTFaTTdaSWVPSURKYVl5OUhxTitGUjBrT2NhWXU3WUIrMndpaDdmWHhvS1ZhT1JsdUVPYUdpVEtDbk95UUNpQWxPNWdRaXJMN25xajY1K0dDV01wRkJpV0hzZHc3MTVxNnZHcDFsanNzRlE9PTh4ajdWMkVsL2NmY2lhcFAiLCJzaXRla2V5IjoiMzNmOTZlNmEtMzhjZC00MjFiLWJiNjgtNzgwNmUxNzY0NDYwIiwiZXhwIjoxNjIwMjg4NjcyLCJwZCI6MH0.pRbFtXK6uAIywcN_yZF9Cc_5Clga8j_KhrN9oCuGmsk\';
                        document.getElementsByName(\'g-recaptcha-response\').value = captcha;
                        document.getElementsByName(\'h-captcha-response\').value = captcha;
                        document.forms[\'challenge-form\'].submit();
                    ');
                    $loginInput = $selenium->waitForElement(WebDriverBy::xpath('//input[@name = "email"]'), 5);
                }

                $passwordInput = $selenium->waitForElement(WebDriverBy::xpath('//input[@name = "password"]'), 0);
                $button = $selenium->waitForElement(WebDriverBy::xpath('//button[contains(@class, "primary-button")]'), 0);
                $this->savePageToLogs($selenium);

                if (!$loginInput || !$passwordInput || !$button) {
                    $this->logger->error('something went wrong');

                    return $this->checkErrors();
                }
                $loginInput->sendKeys($this->AccountFields['Login']);
                $passwordInput->sendKeys($this->AccountFields['Pass']);
                $button->click();

                $selenium->waitForElement(WebDriverBy::xpath('
                    //span[contains(text(), "logout")]
                    | //span[[@aria-label = "welcome user"]
                    | //div[contains(@class, "bg-danger")]
                '), 10);
                $this->savePageToLogs($selenium);

                if ($error = $selenium->waitForElement(WebDriverBy::xpath('//div[contains(@class, "bg-danger")]'), 5)) {
                    $message = $error->getText();

//                    if (stripos($message, 'Your username or password is incorrect') !== false) {
//                        throw new CheckException('Your username or password is incorrect', ACCOUNT_INVALID_PASSWORD);
//                    }

                    return false;
                }

                $cookies = $selenium->driver->manage()->getCookies();

                foreach ($cookies as $cookie) {
                    $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
                }
            } finally {
                $selenium->http->cleanup();
                // retries
                if ($retry && ConfigValue(CONFIG_SITE_STATE) != SITE_STATE_DEBUG) {
                    throw new CheckRetryNeededException(3);
                }
            }

            $this->http->setDefaultHeader('Accept', 'application/json');
            $this->http->setDefaultHeader('access-control-allow-credentials', 'true');
            $this->http->setDefaultHeader('Content-Type', 'application/json');
            $this->http->setDefaultHeader('webapp', 'true');
            $this->http->setDefaultHeader('country-code', 'GB');
            $this->http->setDefaultHeader('app-platform', 'DESKTOP');
            $this->http->setDefaultHeader('brand', 'living-social');
            $this->http->RetryCount = 0;
            /*
            $this->http->PostURL('https://public-api.livingsocial.co.uk/v1/login?_spring_security_remember_me=true', json_encode([
                'loginRequest' => [
                    'j_username' => $this->AccountFields['Login'],
                    'j_password' => $this->AccountFields['Pass']
                ]
            ]));
            $this->http->RetryCount = 2;
            */

            return true;
        }

        // USA
        switch ($this->AccountFields['Login2']) {
            case "basic":
            default:
                $this->http->setHttp2(true);
                /*
                $this->http->SetProxy($this->proxyReCaptcha());
                */

                $this->http->GetURL("https://www.livingsocial.com");

                $checker = clone $this;
                $retry = false;
                $this->http->brotherBrowser($checker->http);

                try {
                    $checker->UseSelenium();
                    $checker->http->driver->dontSaveStateOnStop();
                    $checker->useGoogleChrome(SeleniumFinderRequest::CHROME_100);

                    $request = FingerprintRequest::chrome();
                    $request->browserVersionMin = HttpBrowser::BROWSER_VERSION_MIN;
                    $fingerprint = $this->services->get(FingerprintFactory::class)->getOne([$request]);

                    if ($fingerprint !== null) {
                        $checker->seleniumOptions->fingerprint = $fingerprint->getFingerprint();
                        $checker->http->setUserAgent($fingerprint->getUseragent());
                    }

                    $checker->http->saveScreenshots = true;
                    $checker->disableImages();
                    $checker->useCache();
                    $checker->http->start();
                    $checker->Start();

                    try {
                        $checker->http->GetURL("https://www.livingsocial.com");
                        $checker->http->GetURL("https://www.livingsocial.com/login");
                    } catch (TimeOutException | ScriptTimeoutException | Facebook\WebDriver\Exception\TimeoutException $e) {
                        $this->logger->error("Exception: " . $e->getMessage());
                        $checker->driver->executeScript('window.stop();');
                    }

                    $loginInput = $checker->waitForElement(WebDriverBy::id('login-email-input'), 20);
                    $passwordInput = $checker->waitForElement(WebDriverBy::id('login-password-input'), 0);
                    $buttonInput = $checker->waitForElement(WebDriverBy::id('signin-button'), 0);
                    $this->savePageToLogs($checker);

                    if (!$loginInput || !$passwordInput || !$buttonInput) {
                        $this->logger->error('something went wrong');

                        return $this->checkErrors();
                    }
                    $mover = new MouseMover($checker->driver);
                    $mover->logger = $this->logger;
                    $mover->duration = rand(300, 1000);
                    $mover->steps = rand(10, 20);

                    try {
                        $mover->moveToElement($loginInput);
                        $mover->click();
                    } catch (Facebook\WebDriver\Exception\MoveTargetOutOfBoundsException | MoveTargetOutOfBoundsException $e) {
                        $this->logger->error('[MoveTargetOutOfBoundsException]: ' . $e->getMessage(), ['pre' => true]);
                    }

                    $loginInput->clear();
                    $mover->sendKeys($loginInput, $this->AccountFields['Login'], 7);
                    // $loginInput->sendKeys($this->AccountFields['Login']);
//                    $checker->driver->executeScript("$('#login-email-input').val('{$this->AccountFields['Login']}');");

                    try {
                        $mover->moveToElement($passwordInput);
                        $mover->click();
                    } catch (Facebook\WebDriver\Exception\MoveTargetOutOfBoundsException | MoveTargetOutOfBoundsException $e) {
                        $this->logger->error('[MoveTargetOutOfBoundsException]: ' . $e->getMessage(), ['pre' => true]);
                    }

                    $passwordInput->clear();
//                    $mover->sendKeys($passwordInput, $this->AccountFields['Pass'], 7);
                    // $passwordInput->sendKeys($this->AccountFields['Pass']);
                    $checker->driver->executeScript('document.getElementById("login-password-input").value = "' . str_replace('"', '\"', $this->AccountFields['Pass']) . '";');
                    $this->savePageToLogs($checker);

                    $buttonInput->click();

                    sleep(5);

                    if ($checker->waitForElement(WebDriverBy::xpath('//*[@id = "sec-overlay" or @id = "sec-text-if"]'), 0)) {
                        $checker->waitFor(function () use ($checker) {
                            return !$checker->waitForElement(WebDriverBy::xpath('//*[@id = "sec-overlay" or @id = "sec-text-if"]'), 0);
                        }, 120);
                        $this->savePageToLogs($checker);
                    }

                    $message = $checker->waitForElement(WebDriverBy::xpath("//div[contains(@class, 'error notification') and normalize-space(text()) != ''] | //*[contains(@class,'generic-error')] | //h1[contains(text(), 'Access Denied')]"), 5);
                    $this->savePageToLogs($checker);

                    if ($key = $this->http->FindSingleNode("//div[@id = 'login-recaptcha']//iframe[@title = 'reCAPTCHA']/@src", null, true, "/&k=([^&;]+)/")) {
                        $this->DebugInfo = 'reCAPTCHA checkbox';
                        $captcha = $this->parseCaptcha($key, $checker->http->currentUrl());

                        if ($captcha === false) {
                            return false;
                        }
                        $checker->driver->executeScript('document.getElementById("g-recaptcha-response").value = "' . $captcha . '";');

                        $passwordInput = $checker->waitForElement(WebDriverBy::id('login-password-input'), 0);
                        $this->savePageToLogs($checker);
                        $passwordInput->clear();
                        $mover->sendKeys($passwordInput, $this->AccountFields['Pass'], 7);

                        $this->logger->debug("click btn");
                        $checker->driver->executeScript('
                            document.getElementById(\'signin-button\').click();
                        ');

                        sleep(5);

                        $this->logger->debug("wait result");
                        $message = $checker->waitForElement(WebDriverBy::xpath("//div[contains(@class, 'error notification') and normalize-space(text()) != ''] | //*[contains(@class,'generic-error')] | //h1[contains(text(), 'Access Denied')]"), 5);
                        $this->savePageToLogs($checker);
                        // save page to logs
                        $this->savePageToLogs($checker);
                    }

                    if ($message) {
                        $message = $message->getText();
                        $this->logger->error("[Error]: {$message}");

                        if (stripos($message, 'Your username or password is incorrect') !== false) {
                            throw new CheckException('Your username or password is incorrect', ACCOUNT_INVALID_PASSWORD);
                        }

                        if (stripos($message, 'Your password has expired,') !== false) {
                            throw new CheckException('Your password has expired, Use Forget Password Link to reset your password.', ACCOUNT_INVALID_PASSWORD);
                        }

                        if (stripos($message, 'Something went wrong, please try again in a few minutes.') !== false) {
                            throw new CheckException('Something went wrong, please try again in a few minutes.', ACCOUNT_PROVIDER_ERROR);
                        }

                        $this->DebugInfo = $message;

                        if (stripos($message, 'Please make sure you have clicked on reCAPTCHA checkbox') !== false) {
                            $this->DebugInfo = 'reCAPTCHA checkbox';
                        }
                    }
                    $this->savePageToLogs($checker);

                    $cookies = $checker->driver->manage()->getCookies();

                    foreach ($cookies as $cookie) {
                        $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
                    }

                    if (
                        $this->http->FindPreg("/An error occurred while processing your request\./")
                        || $this->http->FindSingleNode('
                            //h1[contains(text(), "Access Denied")]
                            | //h2[contains(text(), "Oops! Internal Error")]
                        ')
                    ) {
                        $retry = true;
                    }
                } catch (Facebook\WebDriver\Exception\WebDriverCurlException $e) {
                    $this->logger->error("Exception: " . $e->getMessage());
                    $retry = true;
                } catch (Facebook\WebDriver\Exception\TimeoutException $e) {
                    $this->logger->error("Exception: " . $e->getMessage());

                    if (strstr($e->getMessage(), 'Timed out receiving message from renderer')) {
                        $retry = true;
                    }
                } finally {
                    $checker->http->cleanup();
                    // retries
                    if ($retry && ConfigValue(CONFIG_SITE_STATE) != SITE_STATE_DEBUG) {
                        throw new CheckRetryNeededException(3);
                    }
                }

                break;

            case "facebook":
                throw new CheckException('Sorry, login via Facebook is not supported anymore', ACCOUNT_PROVIDER_ERROR); /*checked*/

                try {
                    $this->getInstanceFC();
                } catch (FacebookException $e) {
                    return false;
                }

                break;
        }

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);
        //if ($this->http->Response['code'] == 503 && $message = $this->http->FindSingleNode("//text()[.='LivingSocial.com will be unavailable for several hours']"))
        //    throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        //# The site is down
        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'The site is down for a moment.')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        // Groupon is temporarily unavailable
        if ($message = $this->http->FindSingleNode("//h1[contains(text(), 'Groupon is temporarily unavailable')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        // LivingSocial is temporarily down for maintenance
        if ($message = $this->http->FindSingleNode("//h1[contains(text(), 'LivingSocial is temporarily down for maintenance')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        // Oops! Internal Error
        if ($message = $this->http->FindSingleNode("//h2[contains(text(), 'Oops! Internal Error')]")) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }
        //# Internal Server Error
        if ($message = $this->http->FindPreg("/(Internal Server Error)/ims")) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
        switch ($this->AccountFields['Login2']) {
            case 'UK':
                if (
                    $this->http->FindPreg('/"message":"Login Successful."/')
                    || $this->http->FindSingleNode("
                            //span[contains(text(), 'logout')]
                            | //span[@aria-label = \"welcome user\"]
                       ")
                ) {
                    return true;
                }
                // Invalid email/password. Please try again.
                if ($message = $this->http->FindPreg('#"message":"(Invalid email/password\. Please try again\.)"#')) {
                    throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
                }

                break;

            default:
                if ($this->http->FindNodes("//a[@href = '/mybucks'] | //a[contains(@href, 'logout')]/@href")) {
                    return true;
                }

                break;
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        switch ($this->AccountFields['Login2']) {
            case 'UK':
                $this->ParseUK();

                break;

            default:
                $this->ParseUSA();

                break;
        }
    }

    public function ParseUK()
    {
        $this->http->GetURL('https://api.livingsocial.co.uk/user');
        $response = $this->http->JsonLog();

        if (isset($response->lastName, $response->firstName)) {
            $this->SetProperty('UserName', beautifulName("{$response->lastName} {$response->firstName}"));
        }

        $this->http->GetURL('https://public-api.livingsocial.co.uk/v1/voucher/0/10?brand=living-social');
        $response = $this->http->JsonLog();

        if (isset($response)) {
            foreach ($response as $item) {
                if (!in_array($item->status, ['redeemed', 'expired'])) {
                    $this->sendNotification('refs #16965, livingsocial - check voucher');

                    break;
                }
            }
        }

        if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR) {
            if (!empty($this->Properties['UserName'])) {
                $this->SetBalanceNA();
            }
        }
    }

    public function ParseUSA()
    {
        $this->http->RetryCount = 1;
        $this->http->GetURL("https://www.livingsocial.com/mystuff", [], 30);

        $this->http->FilterHTML = false;

        try {
            // Deal Bucks
            $this->SetProperty('DealBucks', $this->http->FindPreg('/"myBucks":".?(\d+\.\d+)"/'));

            $coupons = $this->ParseCoupons();
            $restplus = $this->parseRestaurantPlus();
            $this->SetProperty('CombineSubAccounts', false);
            $this->SetProperty("SubAccounts", array_merge($coupons, $restplus));
            $this->SetBalance($this->mainBalance);

            // Name
            $this->increaseTimeLimit(120);
            $this->http->GetURL("https://www.livingsocial.com/myaccount");
            $account = $this->http->JsonLog($this->http->FindPreg('#"account":(\{.+\})}\s*</script>#'));
            $firstName = $account->firstName ?? '';
            $lastName = $account->lastName ?? '';
            $this->SetProperty('UserName', beautifulName("$firstName $lastName"));
            // Currency
            $this->SetProperty('Currency', $account->creditsAvailable->currencyCode ?? null);
        } catch (Exception $e) {
            $this->logger->debug($e->getMessage());

            if ($e->getMessage() == 'Groupon is temporarily unavailable.') {
                throw new CheckException($e->getMessage(), ACCOUNT_PROVIDER_ERROR);
            }
            $this->ErrorCode = ACCOUNT_ENGINE_ERROR;

            if ($e->getMessage() === 'no purchase history found') {
                $this->SetBalanceNA();
            }
        }
    }

    /**
     * Parsing coupons.
     *
     * @return array coupons
     */
    public function ParseCoupons()
    {
        $this->logger->notice(__METHOD__);
        // parsing
        $pages = [];

        if ($links = $this->http->FindNodes("//a[contains(@data-bhc, 'view-item-details:')]/@href")) {
            foreach ($links as $k=>$val) {
                $links[$k] = 'https://www.livingsocial.com' . $val;
            }
            $pages = $links;
        }

        $coupons = [];

        foreach ($pages as $page) {
            $this->logger->debug("[Page: \"$page\"]");

            if (!$this->http->GetURL($page)) {
                return $this->checkErrors();
            }

            if (!$this->http->FindSingleNode("//td[contains(text(), 'Number Ordered:') or contains(text(), 'Qty')]/following-sibling::td[1]")) {
                if (
                    $this->http->FindSingleNode("//div[contains(text(), 'Expired:')]")
                    || $this->http->FindSingleNode("//span[contains(text(), 'Your order is Processing.')]")
                ) {
                    continue;
                }

                $script = $this->http->FindSingleNode('//script[@id = "domConfig"]');
                $domConfig = $this->http->JsonLog($script, 3, false, 'dealOption');

                if (!$domConfig) {
                    $script = preg_replace('/("faqs":[^$]+)"edn"/ims', '"edn"', $script);
                    $script = preg_replace('/"finePrint":.+",\s*"highlightsHtml"/ims', '"highlightsHtml"', $script);
                    $script = preg_replace('/"highlightsHtml":.+",\s*"pitchHtml"/ims', '"pitchHtml"', $script);
                    $script = preg_replace('/"pitchHtml":.+",\s*"largeImageUrl"/ims', '"largeImageUrl"', $script);
                    $script = preg_replace('/"instructions":.+",\s*"specificAttributes"/ims', '"specificAttributes"', $script);

                    $script = preg_replace('/"description":([^\}]+)/ims', '"discount": {}', $script);
                    $this->logger->debug(var_export($script, true), ['pre' => true]);
//                    $this->logger->debug("----------------------------");
//                    $this->http->JsonLog($script.'}', 3, false, 'dealOption');
//                    $this->logger->debug("----------------------------");
//                    $this->http->JsonLog(mb_convert_encoding($script, 'UTF-8', 'UTF-8'), 3, false, 'dealOption');
                    $domConfig = $this->http->JsonLog($script . '}', 3, false, 'dealOption');
                }

                if (isset($domConfig->voucher->dealOption)) {
                    $result = [];

                    if (strtotime($domConfig->voucher->expiresAt) < time()) {
                        $this->logger->notice("Skip expired voucher");

                        continue;
                    }

                    $result['Link'] = $domConfig->voucherActionsPayload->printVoucher->urlPath ?? null;
                    $this->http->NormalizeURL($result['Link']);
                    $result['Code'] = sprintf('livingsocial%s', $this->http->FindPreg('/(?:vouchers|groupons)\/([^\.\/]+)/i', false, $result['Link']));
                    $result['Name'] = $this->http->FindSingleNode("//div[contains(@class, 'twelve')]/h1 | //div[contains(@class, 'deal-title')]/h2");
                    $result['DisplayName'] = $result['ShortName'] = $this->http->FindSingleNode("//div[contains(text(), 'Deal Details:')]/following-sibling::div[1] | //div[contains(@class, 'deal-title')]/a");
                    $result['Locations'] = $this->http->FindSingleNode("//a[@data-bhw = 'GetDirectionsLink']/@href") ?? $this->http->FindPreg("/\"directionsLink\":\"([^\"]+)/");

                    $result['Quantity'] = intval($domConfig->voucher->order->quantity);
                    $value = $domConfig->voucher->dealOption->value->formattedAmount;
                    $result['Value'] = floatval($this->http->FindPreg(self::BALANCE_REGEXP, false, $value));
                    $result['Price'] = floatval($this->http->FindPreg(self::BALANCE_REGEXP, false, $domConfig->voucher->dealOption->price->formattedAmount));
                    $result['Currency'] = $this->currency($value);
                    $result['Save'] = $domConfig->voucher->dealOption->discountPercent;
                    $result['Balance'] = $this->http->FindPreg(self::BALANCE_REGEXP, false, $domConfig->voucher->dealOption->discount->formattedAmount);

                    $expirationDate = $this->http->FindSingleNode("
                        //div[contains(text(), 'Expires:')]/following-sibling::div[1]/strong
                        | //p[strong[contains(text(), 'Expires:')]]/span
                        | //p[strong[contains(text(), 'Expired on:')]]/span
                    ", null, true, '/(\w+\s+\d+,\s+\d{4})/');
                    $result['ExpirationDate'] = strtotime($expirationDate);

                    $coupons[] = $result;

                    continue;
                }// if (isset($domConfig->voucher->dealOption))

                throw new Exception("Deals not found");

                break;
            }

            $result = [];
            $result['Link'] =
                $this->http->FindSingleNode('//a[contains(@class, "print-voucher")]/@href')
                ?? $this->http->FindPreg("/printVoucher\":\{\"isEnabled\":true,\"urlPath\":\"([^\"]+)/")
            ;
            $this->http->NormalizeURL($result['Link']);
            $result['Code'] = sprintf('livingsocial%s', $this->http->FindPreg('/(?:vouchers|groupons)\/([^\.\/]+)/i', false, $result['Link']));
            $result['Name'] = $this->http->FindSingleNode("//div[contains(@class, 'twelve')]/h1 | //div[contains(@class, 'deal-title')]/h2");
            $result['DisplayName'] = $result['ShortName'] = $this->http->FindSingleNode("//div[contains(text(), 'Deal Details:')]/following-sibling::div[1] | //div[contains(@class, 'deal-title')]/a");
            $result['Locations'] = $this->http->FindSingleNode("//a[@data-bhw = 'GetDirectionsLink']/@href") ?? $this->http->FindPreg("/\"directionsLink\":\"([^\"]+)/");

            $result['Quantity'] = intval($this->http->FindSingleNode("//td[contains(text(), 'Number Ordered:') or contains(text(), 'Qty')]/following-sibling::td[1]"));
            $value = $this->http->FindSingleNode("//div[contains(text(), 'Voucher Value:')]/following-sibling::div[1] | //td[strong[contains(text(), 'Value')]]/text()[last()]");
            $result['Value'] = floatval($this->http->FindPreg(self::BALANCE_REGEXP, false, $value));
            $result['Price'] = floatval($this->http->FindSingleNode("//td[contains(text(), 'Unit Price')]/following-sibling::td[1]", null, true, self::BALANCE_REGEXP));
            $result['Currency'] = $this->currency($value);

            if ($result['Value'] > 0) {
                $result['Balance'] = ($result['Value'] - $result['Price']) * $result['Quantity'];
                $result['Save'] = round(100 - (($result['Price'] * 100) / $result['Value']));
            } else {
                $result['Balance'] = null;
            }

            $expirationDate = $this->http->FindSingleNode("//div[contains(text(), 'Expires:')]/following-sibling::div[1]/strong | //p[strong[contains(text(), 'Expires:')]]/span", null, true, '/(\w+\s+\d+,\s+\d{4})/');
            $result['ExpirationDate'] = strtotime($expirationDate);

            $coupons[] = $result;
        }// foreach ($pages as $page)

        // Main Balance
        foreach ($coupons as $coupon) {
            $this->mainBalance += $coupon['Balance'] * $coupon['Quantity'];
        }

        return $coupons;
    }

    public function parseRestaurantPlus()
    {
//        $this->http->GetURL('https://www.livingsocial.com/restaurants-plus-rewards/account');

        // Earned
        $earned = $this->total($this->http->FindSingleNode('//span[contains(@class, "chart-center-content")]'), 'num');
        $earnedValute = $this->currencySymbols[$earned['Currency']] ?? '';
        // Lifetime earnings:
        $totalEarned = $this->total($this->http->FindSingleNode('//div[contains(@class, "cashback-progress--total")]/span'), 'num');
        $totalValute = $this->currencySymbols[$totalEarned['Currency']] ?? '';
        // Cashback will be credited to card ending:
        $cardEnding = $this->http->FindSingleNode('//div[contains(@class, "cashback-progress--card")]/span', null, true, '/(\d*)/');

        $data = [
            'Code'            => 'livingsocial_rest_' . $cardEnding,
            'Balance'         => $earnedValute . $earned['num'],
            'DisplayName'     => 'Restaurants Plus (Credit card *' . $cardEnding . ')',
            'LifetimeEarning' => $totalValute . $totalEarned['num'],
            'Currency'        => $earned['Currency'],
        ];

        return empty($cardEnding) ? [] : [$data];
    }

    public function getInstanceFC()
    {
        global $sPath;

        if (!is_null($this->facebook)) {
            return $this->facebook;
        }

        $login = $this->AccountFields['Login'];
        $this->facebook = new FacebookConnect();
        $this->facebook->setAppId('48187595837')
            ->setAppKey('423d17f114134aa521904f85f1146174')
            ->setRedirectURI('https://www.livingsocial.com/login')
            ->useProxyRedirect()
            ->setChecker($this)
            ->setCredentials($this->AccountFields['Login'], $this->AccountFields['Pass'])
            ->setCallbackFunction(function ($session, $fc, $checker) use ($login) {
                $checker->http->PostURL('https://www.livingsocial.com/deals/update_facebook', [
                    'email'      => $login,
                    'first_name' => 'first_name',
                    'last_name'  => 'last_name',
                ]);
            })
            ->PrepareLoginForm();

        return $this->facebook;
    }

    protected function parseCaptcha($key = null, $pageurl = null)
    {
        $this->logger->notice(__METHOD__);

        if (!$key) {
            return false;
        }

        $this->recognizer = $this->getCaptchaRecognizer();
        $this->recognizer->RecognizeTimeout = 120;
        $parameters = [
            "pageurl" => $pageurl ?? $this->http->currentUrl(),
            "proxy"   => $this->http->GetProxy(),
        ];

        return $this->recognizeByRuCaptcha($this->recognizer, $key, $parameters);
    }
}
