<?php

use AwardWallet\Common\Parsing\WrappedProxyClient;
use AwardWallet\Common\Selenium\FingerprintFactory;
use AwardWallet\Common\Selenium\FingerprintRequest;
use AwardWallet\Engine\ProxyList;

require_once "GrouponAbstract.php";

class TAccountCheckerGroupon extends TAccountChecker
{
    use ProxyList;
    use SeleniumCheckerHelper;

    public $facebook;
    protected $GrouponHandler;

    public static function FormatBalance($fields, $properties)
    {
        if ($fields['Login2'] == 'UK') {
            return formatFullBalance($fields['Balance'], $fields['ProviderCode'], "£%0.2f");
        }

        return formatFullBalance($fields['Balance'], $fields['ProviderCode'], "$%0.2f");
    }

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = false;
        /*
        // As in Chrome in the Selenium
        $this->http->setDefaultHeader('User-Agent', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Ubuntu Chromium/60.0.3112.78 Chrome/60.0.3112.78 Safari/537.36');
        */
    }

    /*function IsLoggedIn() {
        $this->logger->notice(__METHOD__);
        switch ($this->AccountFields['Login2']) {
            case "UK":
                $this->http->GetURL('https://www.groupon.co.uk/login');
                break;
            case 'Australia':
                $this->http->GetURL('https://www.groupon.com.au/login');
                break;
            case "Canada":
                $this->http->GetURL('https://www.groupon.ca/login');
                break;
            default:
                // AccountID: 3508070
                if ($this->AccountFields['Login'] == 'suealdeb_99@yahoo.com')
                    $this->http->RetryCount = 0;
                $this->http->GetURL('https://www.groupon.com/login');
                break;
        }

        if ($this->http->FindSingleNode("//a[contains(text(), 'Sign Out')]") ||
            $this->http->FindSingleNode("//a[contains(text(), 'Logout')]"))
            return true;

        return false;
    }*/

    public function GetRedirectParams($targetURL = null)
    {
        $arg = parent::GetRedirectParams($targetURL);

        if ($this->AccountFields['Login3'] != "facebook") {
            $arg = $this->GrouponHandler->GetRedirectParams($arg);
            $arg["PreloadAsImages"] = true;

            return $arg;
        }
        $arg["PreloadAsImages"] = true;
        $arg['PostValues'] = [];
        $arg['RequestMethod'] = 'GET';
        $arg['URL'] = $this->facebook->getAutoLoginLink();

        return $arg;
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        //$this->ShowLogs = true;
        switch ($this->AccountFields['Login2']) {
            case "UK":
                require_once "GrouponUSANew.php";
                $this->GrouponHandler = new GrouponUSANew($this);
                $this->GrouponHandler->urlProvider = 'www.groupon.co.uk';
                $this->GrouponHandler->baseDomainCookie = '.groupon.co.uk';

                // $this->GrouponHandler->app_id = '128218413912317';
                // $this->GrouponHandler->startCookies = array(
                //     array(
                //         "name" => "division",
                //         "path" => "/",
                //         "domain" => ".groupon.co.uk",
                //         "expires" => "Wed, 06-09-2026 06:43:08 GMT",
                //         // "value" => "st-johns",
                //         "value" => "london",
                //     ),
                // );
            break;

            case 'Australia':
                if (filter_var($this->AccountFields['Login'], FILTER_VALIDATE_EMAIL) === false) {
                    throw new CheckException("Incorrect login. Try again or reset your password.", ACCOUNT_INVALID_PASSWORD);
                }

                require_once "GrouponUSANew.php";
                $this->GrouponHandler = new GrouponUSANew($this);
                $this->GrouponHandler->urlProvider = 'www.groupon.com.au';
                $this->GrouponHandler->baseDomainCookie = '.groupon.com.au';

                // $this->GrouponHandler->app_id = '139243329469945';
                break;

            case "Canada":
                // require_once "GrouponUK.php";
                // $this->GrouponHandler = new GrouponUK($this); # like UK
                require_once "GrouponUSANew.php";
                $this->GrouponHandler = new GrouponUSANew($this);

                $this->GrouponHandler->urlProvider = 'www.groupon.ca';

                $this->GrouponHandler->app_id = '122332024501835';
                $this->GrouponHandler->startCookies = [
                    [
                        "name"    => "user_locale",
                        "path"    => "/",
                        "domain"  => ".groupon.ca",
                        "expires" => "Wed, 18-11-2019 10:57:52 GMT",
                        "value"   => "en_CA",
                    ],
                ];

            break;

            case "USA":
            default:
                // Please enter a valid email address.
                if (filter_var($this->AccountFields['Login'], FILTER_VALIDATE_EMAIL) === false) {
                    throw new CheckException("Please enter a correct email address.", ACCOUNT_INVALID_PASSWORD);
                }

                require_once "GrouponUSANew.php";
                $this->GrouponHandler = new GrouponUSANew($this);
                $this->GrouponHandler->startCookies = [
                    [
                        "name"   => "division",
                        "path"   => "/",
                        "domain" => ".groupon.com",
                        "value"  => "chicago",
                    ],
                    [
                        "name"   => "user_locale",
                        "path"   => "/",
                        "domain" => ".groupon.com",
                        "value"  => "en_US",
                    ],
                ];

            break;
        }
        $this->GrouponHandler->setCredentials($this->AccountFields['Login'], $this->AccountFields['Pass']);

        if (isset($this->AccountFields['AccountID'])) {
            $this->GrouponHandler->setAccountID(ArrayVal($this->AccountFields, 'RequestAccountID', $this->AccountFields['AccountID']));
        }

        if (isset($this->AccountFields['Login3'])) {
            $this->GrouponHandler->setLoginType($this->AccountFields['Login3']);
        }

        return $this->GrouponHandler->LoadLoginForm();
    }

    public function parseCaptcha($key = null, $pageurl = null)
    {
        $this->logger->debug(__METHOD__);
        $key = $key ?? $this->http->FindSingleNode("//div[@class = 'g-recaptcha']/@data-sitekey");

        if (!$key) {
            return false;
        }

        $recognizer = $this->getCaptchaRecognizer();
        $recognizer->RecognizeTimeout = 120;
        $parameters = [
            "pageurl" => $pageurl ?? $this->http->currentUrl(),
            "proxy"   => $this->http->GetProxy(),
        ];

        return $this->recognizeByRuCaptcha($recognizer, $key, $parameters);
    }

    public function selenium()
    {
        $this->logger->notice(__METHOD__);
        $logout = false;
        $retry = false;
        $checker = clone $this;
        $this->http->brotherBrowser($checker->http);

        try {
            $this->logger->notice("Running Selenium...");
            $checker->UseSelenium();

            $checker->useGoogleChrome();

            $request = FingerprintRequest::chrome();
            $request->browserVersionMin = HttpBrowser::BROWSER_VERSION_MIN;
            $fingerprint = $this->services->get(FingerprintFactory::class)->getOne([$request]);

            if ($fingerprint !== null) {
                $checker->seleniumOptions->fingerprint = $fingerprint->getFingerprint();
                $checker->http->setUserAgent($fingerprint->getUseragent());
            }

            $checker->http->SetProxy($this->proxyReCaptcha());
            /*
            $wrappedProxy = $this->services->get(WrappedProxyClient::class);
            $proxy = $wrappedProxy->createPort($checker->http->getProxyParams());
            $checker->seleniumOptions->antiCaptchaProxyParams = $proxy;
            */
            $checker->seleniumOptions->antiCaptchaProxyParams = $checker->getCaptchaProxy();
            $checker->seleniumOptions->addAntiCaptchaExtension = true;

            $checker->disableImages();
            $checker->useCache();
            $checker->http->saveScreenshots = true;
            $checker->http->start();

            try {
                $checker->http->getURL('https://' . $this->GrouponHandler->urlProvider . '/login');
            } catch (Facebook\WebDriver\Exception\TimeoutException | TimeoutException $e) {
                $this->logger->error("Exception: " . (strlen($e->getMessage()) > 40 ? substr($e->getMessage(), 0, 37) . '...' : $e->getMessage()));
                $checker->driver->executeScript('window.stop();');
            }
            $checker->Start();

            $form = '(//form[@data-bhw = "LoginForm"] | //div[@data-bhw="ls-signin-form"]/form)';
            $loginInput = $checker->waitForElement(WebDriverBy::xpath("{$form}//input[@id = 'login-email-input' or @placeholder=\"Email\"]"), 10);
            $passwordInput = $checker->waitForElement(WebDriverBy::xpath("{$form}//input[@id = 'login-password-input' or @placeholder=\"Password\"]"), 0);
            $button = $checker->waitForElement(WebDriverBy::xpath("{$form}//*[@id = 'signin-button' or @data-bhw=\"signin-button\"]"), 0);

            if (!$loginInput || !$passwordInput || !$button) {
                $this->logger->error("something went wrong");
                // save page to logs
                $this->savePageToLogs($checker);
                // Access Denied
                // Oops! Internal Error
                if ($this->http->FindSingleNode('
                        //h1[contains(text(), "Access Denied")]
                        | //h2[contains(text(), "Oops! Internal Error")]
                    ')
                ) {
                    $retry = true;
                }
                /**
                 * Groupon is temporarily unavailable.
                 *
                 * Either because we're updating the site or because someone spilled coffee on it again.
                 * We'll be back just as soon as we finish the update or clean up the coffee.
                 *
                 * Thanks for your patience.
                 */
                if ($message = $this->http->FindSingleNode('//p[contains(text(), "Either because we\'re updating the site or because someone spilled coffee on it again.")]')
                ) {
                    throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
                }

                return false;
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

            try {
                $loginInput->clear();
            } catch (Facebook\WebDriver\Exception\InvalidElementStateException | InvalidElementStateException $e) {
                $this->logger->error('[MoveTargetOutOfBoundsException]: ' . $e->getMessage(), ['pre' => true]);
                $loginInput = $checker->waitForElement(WebDriverBy::xpath("{$form}//input[@id = 'login-email-input' or @placeholder=\"Email\"]"), 10);
                $this->savePageToLogs($checker);
            }

            $mover->sendKeys($loginInput, $this->AccountFields['Login'], 7);
//            $loginInput->sendKeys($this->AccountFields['Login']);

            try {
                $mover->moveToElement($passwordInput);
                $mover->click();
            } catch (Facebook\WebDriver\Exception\MoveTargetOutOfBoundsException | MoveTargetOutOfBoundsException $e) {
                $this->logger->error('[MoveTargetOutOfBoundsException]: ' . $e->getMessage(), ['pre' => true]);
            }

            $passwordInput->clear();
            $mover->sendKeys($passwordInput, $this->AccountFields['Pass'], 7);
//            $passwordInput->sendKeys($this->AccountFields['Pass']);

            $this->logger->debug("click accept");

            if ($accept = $checker->waitForElement(WebDriverBy::xpath("//*[@id = 'gdpr-accept']"), 0)) {
                $accept->click();
                $this->savePageToLogs($checker);
            }

            $this->logger->debug("click btn");
            $checker->driver->executeScript('
                document.querySelector(\'#signin-button, button[data-bhw="signin-button"]\').click();
            ');
//            $button->click();
            sleep(5);

            $this->logger->debug("wait result");
            $loginStatus = $checker->waitForElement(WebDriverBy::xpath("//a[contains(text(), 'Sign Out')] | //a[contains(text(), 'Logout')] | //button[@data-bhw='UserSignOut'] | //button[@data-bhw-path=\"Header|signin-btn\"]"), 5, false);
            // save page to logs
            $this->savePageToLogs($checker);

            if ($checker->waitForElement(WebDriverBy::xpath('//*[@id = "sec-overlay" or @id = "sec-text-if"]'), 0)) {
                $checker->waitFor(function () use ($checker) {
                    return !$checker->waitForElement(WebDriverBy::xpath('//*[@id = "sec-overlay" or @id = "sec-text-if"]'), 0);
                }, 120);
                $this->savePageToLogs($checker);
            }

            $checker->waitFor(function () use ($checker) {
                $this->logger->warning("Solving is in process...");
                sleep(3);
                $this->savePageToLogs($checker);

                return !$this->http->FindSingleNode('//a[contains(text(), "Solving is in process...")]');
            }, 200);

            $this->logger->debug("wait result");
            $loginStatus = $checker->waitForElement(WebDriverBy::xpath("//a[contains(text(), 'Sign Out')] | //a[contains(text(), 'Logout')] | //button[@data-bhw='UserSignOut'] | //button[@data-bhw-path=\"Header|signin-btn\"]"), 5, false);
            // save page to logs
            $this->savePageToLogs($checker);

            if ($key = $this->http->FindSingleNode("//div[@id = 'login-recaptcha' or contains(@class, 'recaptchaBox')]//iframe[@title = 'reCAPTCHA']/@src", null, true, "/&k=([^&;]+)/")) {
                $this->DebugInfo = 'reCAPTCHA checkbox';
                $captcha = $this->parseCaptcha($key, $checker->http->currentUrl());

                if ($captcha === false) {
                    return false;
                }
                $checker->driver->executeScript('document.getElementById("g-recaptcha-response").value = "' . $captcha . '";');

                $passwordInput = $checker->waitForElement(WebDriverBy::xpath("{$form}//input[@id = 'login-password-input']"), 0);
                $this->savePageToLogs($checker);
                $passwordInput->clear();
                $mover->sendKeys($passwordInput, $this->AccountFields['Pass'], 7);

                $this->logger->debug("click btn");
                $checker->driver->executeScript('
                    document.getElementById(\'signin-button\').click();
                ');

                sleep(5);
            }

            $this->logger->debug("wait result");
            $loginStatus = $checker->waitForElement(WebDriverBy::xpath("//a[contains(text(), 'Sign Out')] | //a[contains(text(), 'Logout')] | //button[@data-bhw='UserSignOut'] | //button[@data-bhw-path=\"Header|signin-btn\"]"), 5, false);
            // save page to logs
            $this->savePageToLogs($checker);

            if ($loginStatus) {
                $logout = true;
            } elseif (
                ($error = $checker->waitForElement(WebDriverBy::xpath("//div[contains(@class, 'error notification') and normalize-space(text()) != '']"), 0))
                || ($error = $checker->waitForElement(WebDriverBy::xpath("
                    //*[contains(@class,'generic-error') and normalize-space(text()) != '']
                    | //p[@id = 'error-login-email-input' and contains(@class, 'active')]
                    | //h2[contains(text(), 'Oops! Internal Error')]
                    | //h1[contains(text(), 'Groupon is temporarily unavailable.')]
                    | //h1[contains(text(), 'Access Denied')]
                "), 0, false))
            ) {
                $message = $error->getText();
                $this->logger->error("[Error]: {$message}");

                if (stripos($message, 'Your username or password is incorrect') !== false) {
                    throw new CheckException('Your username or password is incorrect', ACCOUNT_INVALID_PASSWORD);
                }

                if (
                    stripos($message, 'Please enter a correct email address.') !== false
                    || stripos($message, 'The email or password did not match our records. Please try again.') !== false
                ) {
                    throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
                }

                if (stripos($message, 'Your password has expired,') !== false) {
                    throw new CheckException('Your password has expired, Use Forget Password Link to reset your password.', ACCOUNT_INVALID_PASSWORD);
                }

                // Access Denied
                // Oops! Internal Error
                if ($this->http->FindSingleNode('
                        //h1[contains(text(), "Access Denied")]
                        | //h2[contains(text(), "Oops! Internal Error")]
                    ')
                ) {
                    $retry = true;
                }

                if (
                    /*
                    stripos($message, 'Oops! Internal Error') !== false
                    ||
                    */
                    stripos($message, 'Something went wrong, please try again in a few minutes.') !== false
                    || stripos($message, 'Groupon is temporarily unavailable.') !== false
                ) {
                    throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
                }

                $this->DebugInfo = $message;

                if (stripos($message, 'Please make sure you have clicked on reCAPTCHA checkbox') !== false) {
                    $this->DebugInfo = 'reCAPTCHA checkbox2';
                }
            }

            // save page to logs
            $this->savePageToLogs($checker);

            $cookies = $checker->driver->manage()->getCookies();

            foreach ($cookies as $cookie) {
                $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
            }
        } catch (ScriptTimeoutException $e) {
            $this->logger->error("ScriptTimeoutException exception: " . $e->getMessage());
            // retries
            if (strstr($e->getMessage(), 'timeout: Timed out receiving message from renderer')) {
                $retry = true;
            }
        } catch (StaleElementReferenceException $e) {
            $this->logger->error("StaleElementReferenceException exception: " . $e->getMessage());
            // retries
            if (strstr($e->getMessage(), 'Element not found in the cache')
                || strstr($e->getMessage(), 'element is not attached to the page document')) {
                $retry = true;
            }
        } finally {
            // close Selenium browser

            if ($retry && $this->AccountFields['Login2'] == 'USA') {
                $checker->markProxyAsInvalid();
            }

            $checker->http->cleanup();

            if ($retry && ConfigValue(CONFIG_SITE_STATE) != SITE_STATE_DEBUG) {
                $this->logger->debug("[attempt]: {$this->attempt}");

                throw new CheckRetryNeededException(3, 10);
            }
        }

        // AccountID: 2264862
        if ($this->http->FindSingleNode("//h1[contains(text(), 'Access Denied')]") && $this->AccountFields['Pass'] == '<sVg/OnLOaD=prompt(0)>') {
            throw new CheckException("Incorrect login. Try again or reset your password.", ACCOUNT_INVALID_PASSWORD);
        }
        // Access Denied
        if ($this->http->FindSingleNode("//h1[contains(text(), 'Access Denied')]")) {
            $this->DebugInfo = 'Access Denied';
        }

        return $logout;
    }

    public function Login()
    {
        $this->http->Log(__METHOD__);

        return $this->GrouponHandler->Login();
    }

    public function Parse()
    {
        return $this->GrouponHandler->Parse();
    }

    // TODO:
    public function MarkCoupon(array $ids)
    {
        return $this->GrouponHandler->MarkCoupon($ids);
    }

    public function ParseCoupons($onlyActive = false)
    {
        return $this->GrouponHandler->ParseCoupons($onlyActive);
    }

    public function TuneFormFields(&$arFields, $values = null)
    {
        parent::TuneFormFields($arFields);
        $arFields['Login2']['Options'] = [
            ""          => "Select country",
            "Australia" => "Australia", // http://www.groupon.com.au/
            "Canada"    => "Canada", 		// http://www.groupon.ca/
            "UK"        => "United Kingdom",	// http://www.groupon.co.uk/
            "USA"       => "USA", 			// http://www.groupon.com/
        ];
        //		$arFields["Login2"]["OptionAttributes"] = array(
//			"USA" => "onclick=\"GrouponLoginType('USA');\"",
//			"Canada" => "onclick=\"GrouponLoginType('Canada');\"",
//			"UK" => "onclick=\"GrouponLoginType('UK');\"",
//		);
//
//		ArrayInsert($arFields, "Login", true, array("Login3" => array(
//			"Type" => "string",
//			"Required" => true,
//			"Caption" => "Login Type",
//		)));

//		if (isset($values['Login2']) && in_array($values['Login2'], array('USA', 'Canada', 'UK')))
//			$arFields["Login3"]["Options"] = array(
//				"basic" => "Basic",
//				"facebook" => "With Facebook",
//			);
//		else
//			$arFields["Login3"]["Options"] = array(
//				"basic" => "Basic",
//				"facebook" => "With Facebook",
//			);
//		$arFields['Step'] = array(
//			"Type" => "string",
//			"InputType" => "html",
//			"IncludeCaption" => false,
//			"Database" => false,
//			"HTML" => "<script type=\"text/javascript\">
//function GrouponLoginType(country) {
//	if (country == 'USA' || country == 'Canada' || country == 'UK') {
//		$('select[name=Login3]').empty().append($('<option value=\"basic\">Basic</option><option value=\"facebook\">With Facebook</option>'));
//	} else {
//		$('select[name=Login3]').empty().append($('<option value=\"basic\">Basic</option>'));
//	}
//}
//</script>",
//
//		);
    }
}

/**
 * GrouponOtherCountries.
 */
class GrouponOtherCountries extends GrouponAbstract
{
    public function LoadLoginForm()
    {
    }

    public function Login()
    {
    }

    public function Parse()
    {
    }

    public function MarkCoupon(array $ids)
    {
    }

    public function ParseCoupons($onlyActive = false)
    {
    }

    public function getUrlsPages()
    {
    }
}
