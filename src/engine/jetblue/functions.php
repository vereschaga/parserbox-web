<?php

// refs #2065, jetblue

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Common\Parsing\Html;
use AwardWallet\Common\Selenium\FingerprintFactory;
use AwardWallet\Common\Selenium\FingerprintRequest;
use AwardWallet\Engine\ProxyList;
use AwardWallet\Engine\Settings;

class TAccountCheckerJetblue extends TAccountChecker
{
    use ProxyList;
    use PriceTools;
    use SeleniumCheckerHelper;

    private $ownerCustomerId;
    private $currentItin = 0;
    private $headers = [];
    private $seleniumFail = true;
    private $lastName;
    private $itinerariesUrl;

    private $responseData = null;
    private $disableSeleniumForRetrieve = false;

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->setKeepUserAgent(true);
        $this->http->setDefaultHeader("Accept-Encoding", "gzip, deflate, br");
    }

    public static function GetAccountChecker($accountInfo)
    {
        require_once __DIR__ . "/TAccountCheckerJetblueSelenium.php";

        return new TAccountCheckerJetblueSelenium();
    }

    public function IsLoggedIn()
    {
        $this->http->RetryCount = 0;
        $this->http->GetURL("https://trueblue.jetblue.com/dashboard/", [], 20);
        $accessToken = $this->http->getCookieByName('okta_access_token', '.jetblue.com');

        if (!empty($accessToken)) {
            $headers = [
                "Accept"       => "application/json, text/plain, */*",
                "Content-Type" => "application/json",
            ];
            $this->http->GetURL("https://trueblue.jetblue.com/b2c/authorization/generate-JWT-token-MP-SSO", $headers, 20);
            $this->http->RetryCount = 2;
            $response = $this->http->JsonLog();

            if (isset($response->access_token)) {
//        if ($this->loginSuccessful()) {
                return true;
            }
        }

        return false;
    }

    public function LoadLoginForm()
    {
        // Your email and/or password were entered incorrectly.
        if (filter_var($this->AccountFields['Login'], FILTER_VALIDATE_EMAIL) === false) {
            throw new CheckException("Your email and/or password were entered incorrectly.", ACCOUNT_INVALID_PASSWORD);
        }

        $this->http->removeCookies();
        $this->http->GetURL("https://www.jetblue.com/signin?returnUrl=https:%2F%2Fwww.jetblue.com%2F");

        if (!in_array($this->http->Response['code'], [200, 201])) {
            return $this->checkErrors();
        }

        // uniqueStateKey
        $this->selenium();

        return true;
    }

    public function selenium()
    {
        $this->logger->notice(__METHOD__);
        $selenium = clone $this;
        $result = false;
        $retry = false;
        $proxy = "";
        $this->http->brotherBrowser($selenium->http);

        try {
            $this->logger->notice("Running Selenium...");
            $selenium->UseSelenium();

            $selenium->http->saveScreenshots = true;
            $evaluateMouse = false;

            $selenium->seleniumOptions->recordRequests = true;

            if ($this->attempt == 0) {
                $selenium->useFirefoxPlaywright();
                $selenium->seleniumRequest->setOs(SeleniumFinderRequest::OS_MAC);
                $selenium->seleniumOptions->addHideSeleniumExtension = false;
                $selenium->seleniumOptions->userAgent = null;
            } else {
                $selenium->useFirefox(SeleniumFinderRequest::FIREFOX_100);
                $selenium->setKeepProfile(true);

                $request = FingerprintRequest::firefox();
                $request->browserVersionMin = HttpBrowser::BROWSER_VERSION_MIN - 3;
                $request->platform = 'Linux x86_64';
                $fingerprint = $this->services->get(FingerprintFactory::class)->getOne([$request]);

                $selenium->usePacFile(false);
                $selenium->seleniumOptions->addHideSeleniumExtension = false;

                if (
                    $fingerprint !== null
                    && (!isset($this->State['UserAgent']) || $this->attempt > 0)
                ) {
                    $this->logger->info("selected fingerprint {$fingerprint->getId()}, {{$fingerprint->getBrowserFamily()}}:{{$fingerprint->getBrowserVersion()}}, {{$fingerprint->getPlatform()}}, {$fingerprint->getUseragent()}");
                    $this->State['Fingerprint'] = $fingerprint->getFingerprint();
                    $this->State['Resolution'] = [$fingerprint->getScreenWidth(), $fingerprint->getScreenHeight()];
                    $this->State['UserAgent'] = $fingerprint->getUseragent();
                }
            }

            if ($this->attempt == 2) {
                $selenium->seleniumOptions->fingerprintParams = FingerprintParams::vanillaFirefox();
            } else {
                if (isset($this->State['UserAgent'])) {
                    $selenium->http->setUserAgent($this->State['UserAgent']);
                }
            }

            if ($this->attempt == 10) {
                $this->logger->notice("no Proxy");
                $proxy = "direct";
            } elseif ($this->attempt == 2) {
                $selenium->http->SetProxy($this->proxyReCaptchaIt7());
                $proxy = "recaptcha:" . $selenium->http->getProxyAddress();
            } else {
                $selenium->http->SetProxy($this->proxyDOP(Settings::DATACENTERS_NORTH_AMERICA));
                $proxy = "dop:" . $selenium->http->getProxyAddress();
            }

            $this->http->SetProxy($selenium->http->GetProxy());

            /*
            $selenium->useCache();
            */
            $selenium->http->start();
            $selenium->Start();
            $getFirstPageAttempt = 0;

            // refs #23144
            if (
                !isset($this->State['brokenTrueBlueTime'])
                || $this->State['brokenTrueBlueTime'] > strtotime("-1 month")
            ) {
                unset($this->State['brokenTrueBlue']);
                unset($this->State['brokenTrueBlueTime']);
            }

            try {
                if (
                    (
                        $this->attempt > 0
                        && isset($this->State['brokenTrueBlue'])
                        && $this->State['brokenTrueBlue'] === true
                    )
                    || in_array($this->AccountFields['Login'], [
                        'jetblue@blankwhitecanvas.com',
                        'hlm2home@gmail.com',
                        'tim.arthur@gmail.com',
                        'matt.mazzochi@gmail.com',
                        'jtfales@gmail.com',
                        'dchaid@gmail.com',
                        'franciasia@hotmail.com',
                        'jamesly@gmail.com',
                        'shunt3@babson.edu',
                        'amyannrosa@gmail.com',
                        'oscarpfau@aol.com',
                        'christopher.canada@yahoo.com',
                        'kevinv.huynh@gmail.com',
                        'illufe@gmail.com',
                        'chrisdarbro@gmail.com',
                        'jjp2900@gmail.com',
                        'rldillon@me.com',
                        'a.fadlallah10@gmail.com',
                        'ivyroot@gmail.com',
                        'mperuzzi@gmail.com',
                        'asiegel08@gmail.com',
                        'shekhar.aylawadi@gmail.com',
                        'bartonharris@gmail.com',
                        'genslinton@gmail.com',
                        'furr2ball@cableone.net',
                        'leslie.samanta@gmail.com',
                        'jessleighwilson@gmail.com',
                        'teo.cervantes@me.com',
                        'mchiang1219@gmail.com',
                        'bryangranum@gmail.com',
                        'michael@etzel.com',
                        'clement.h.ou@gmail.com',
                        'x@lorengordon.com',
                        'davidrwu@gmail.com',
                        'cbogen@gmail.com',
                        'mferreira@jaymar.com.br',
                        'melwu3@gmail.com',
                    ])
                ) {
                    $selenium->http->GetURL("https://www.jetblue.com/signin");
                } else {
                    $selenium->http->GetURL("https://trueblue.jetblue.com/login?redirectUrl=%2Fmy-points");
                }
            } catch (UnexpectedJavascriptException $e) {
                $this->logger->error("UnexpectedJavascriptException: " . $e->getMessage(), ['HtmlEncode' => true]);
                sleep(2);
                $this->savePageToLogs($selenium);
            } catch (ScriptTimeoutException | TimeOutException $e) {
                $this->logger->error("TimeOutException: " . $e->getMessage(), ['HtmlEncode' => true]);
                $selenium->driver->executeScript('window.stop();');
            }

            sleep(2);

            try {
                // save page to logs
                $this->savePageToLogs($selenium);
            } catch (UnexpectedJavascriptException $e) {
                $this->logger->error("UnexpectedJavascriptException: " . $e->getMessage(), ['HtmlEncode' => true]);
                sleep(2);
                $this->savePageToLogs($selenium);
            }

            // save 10 seconds, do not wait for inputs, if we are blocked or maintenance
            if ($msg = $this->http->FindPreg("/You don't have permission to access/")) {
                $this->logger->notice($msg);
                $this->DebugInfo = $msg;
                $selenium->http->GetURL("https://www.jetblue.com/signin");
            }

            $loginInput =
                $selenium->waitForElement(WebDriverBy::xpath('//input[@formcontrolname="email" or @id = "okta-signin-username" or @name="identifier"]'), 15, false)
                ?? $selenium->waitForElement(WebDriverBy::xpath('//input[@formcontrolname="email" or @id = "okta-signin-username" or @name="identifier"]'), 0)
            ;
            $this->savePageToLogs($selenium);

            // long form loading
            if (!$loginInput || ($selenium->waitForElement(WebDriverBy::xpath('
                    //svg[@class = "loader"]
                    | //div[@class = "spinner-icon"]
                '), 0))
            ) {
                $this->savePageToLogs($selenium);
                $loginInput =
                    $selenium->waitForElement(WebDriverBy::xpath('//input[@formcontrolname="email" or @id = "okta-signin-username" or @name="identifier"]'), 45, false)
                    ?? $selenium->waitForElement(WebDriverBy::xpath('//input[@formcontrolname="email" or @id = "okta-signin-username" or @name="identifier"]'), 0)
                ;
            }

            $this->logger->debug("find pass");
            // save page to logs
            $this->savePageToLogs($selenium);
            $passwordInput =
                $selenium->waitForElement(WebDriverBy::xpath('//input[@formcontrolname="password" or @id = "okta-signin-password" or @name="credentials.passcode"]'), 0, false)
                ?? $selenium->waitForElement(WebDriverBy::xpath('//input[@formcontrolname="password" or @id = "okta-signin-password" or @name="credentials.passcode"]'), 0)
            ;
            $this->logger->debug("find Sign in btn");
            $button =
                $selenium->waitForElement(WebDriverBy::xpath('//button[span[contains(., "Sign in") or contains(., "sign in")]] | //input[@id = "okta-signin-submit"] | //input[@value="Sign in"]'), 0, false)
                ?? $selenium->waitForElement(WebDriverBy::xpath('//button[span[contains(., "Sign in") or contains(., "sign in")]] | //input[@id = "okta-signin-submit"] | //input[@value="Sign in"]'), 0)
            ;

            $this->closePopups($selenium);

            // save page to logs
            $this->savePageToLogs($selenium);

            if (!$loginInput && $passwordInput && $button) {
                $loginInput = $selenium->waitForElement(WebDriverBy::xpath('//input[@formcontrolname="email" or @id = "okta-signin-username" or @name="identifier"]'), 0, false);
            }

            if (!$loginInput || !$passwordInput || !$button) {
                $this->logger->error("something went wrong");
                // too long loading?
                if ($selenium->waitForElement(WebDriverBy::xpath('//div[@class = "spinner-icon"]'), 0)) {
                    $retry = true;
                }

                if ($selenium->waitForElement(WebDriverBy::xpath('
                        //h1[
                            contains(text(), "Page not found")
                            or contains(text(), "Error 503 backend read error")
                        ]
                    '), 0)
                ) {
                    $selenium->http->GetURL("https://www.jetblue.com/signin?returnUrl=https:%2F%2Fwww.jetblue.com%2F");
                    // TODO: unfinished
                    sleep(3);
                    $this->closePopups($selenium);
                    $loginInput = $selenium->waitForElement(WebDriverBy::xpath('//input[@formcontrolname="email" or @id = "okta-signin-username" or @name="identifier"]'), 1);
                    $passwordInput = $selenium->waitForElement(WebDriverBy::xpath('//input[@formcontrolname="password" or @id = "okta-signin-password" or @name="credentials.passcode"]'));
                    $button = $selenium->waitForElement(WebDriverBy::xpath('//button[span[contains(., "Sign in") or contains(., "sign in")]] | //input[@id = "okta-signin-submit"] | //input[@value="Sign in"]'));
                }

                $this->savePageToLogs($selenium);

                return $this->checkErrors();
            }

            if ($this->seleniumRequest->getBrowser() === SeleniumFinderRequest::BROWSER_FIREFOX) {
                // firefox does not support getting browser logs
                // https://github.com/mozilla/geckodriver/issues/284
                $this->logger->info("setting FF exception handler");

                try {
                    // do not work actually, should debug it
                    $selenium->driver->executeScript('
                    var ul = null;
                    function createErrorList() {
                        ul = document.createElement(\'ul\');
                        ul.setAttribute(\'id\', \'js_error_list\');
                        ul.style.display = \'none\';
                        document.body.appendChild(ul);
                    }
                    window.onerror = function(msg){
                        if (ul === null)
                            createErrorList();
                        var li = document.createElement("li");
                        li.appendChild(document.createTextNode(msg));
                        ul.appendChild(li);
                    };
                ');
                } catch (Exception $exception) {
                    $this->logger->warning("could not set exception handler: " . substr($exception->getMessage(), 0, 50));
                }
            }

            if ($evaluateMouse) {
                $mover = new MouseMover($selenium->driver);
                $mover->logger = $this->logger;
                $mover->duration = 100000;
                $mover->steps = 50;
            }

            $this->logger->debug("enter login");

            if ($evaluateMouse) {
                $mover->moveToElement($loginInput);
                $mover->click();
                $mover->sendKeys($loginInput, $this->AccountFields['Login'], 10);
            } else {
                $loginInput->sendKeys($this->AccountFields['Login']);
            }

            $this->logger->debug("enter password");

            $this->closePopups($selenium);

            if ($evaluateMouse) {
                $mover->moveToElement($passwordInput);
                $this->savePageToLogs($selenium);
                $mover->click();
                $this->savePageToLogs($selenium);
                $passwordInput = $selenium->waitForElement(WebDriverBy::xpath('//input[@formcontrolname="password" or @id = "okta-signin-password" or @name="credentials.passcode"]'), 0);

                if (!$passwordInput) {
                    return false;
                }
                $mover->sendKeys($passwordInput, $this->AccountFields['Pass'], 10);
            } else {
                $this->savePageToLogs($selenium);
                $passwordInput->sendKeys($this->AccountFields['Pass']);
            }
            // Sign In
            $this->logger->debug("click 'Sign In'");
            $this->closePopups($selenium);
            $this->savePageToLogs($selenium);

            // provider bug fix
            $selenium->driver->executeScript('
                let oldXHROpen = window.XMLHttpRequest.prototype.open;
                window.XMLHttpRequest.prototype.open = function(method, url, async, user, password) {
                    this.addEventListener("load", function() {
                        if (/\/iam\/login/g.exec( url )) {
                            localStorage.setItem("responseData", this.responseText);
                        }
                    });
                    return oldXHROpen.apply(this, arguments);
                };
            ');

            $button->click();

            $loaderXpath = '
                //jb-icon[@name = "loading"]
                | //div[@class = "loader-container"]
                | //div[@class = "spinner-icon"]
            ';
            $resultXpath = '
                //*[self::a or self::button][contains(text(), "My Trips") or contains(text(), "Manage Trips")]
                | //div[contains(@class, "red") and not(@hidden)]
                | //jb-error[@class = "jb-error"]/div/p[contains(@class, "red")]
                | //div[contains(@class, "okta-form-infobox-error")]/p
                | //div[contains(@class, "o-form-has-errors")]/p
                | //h2[contains(text(), "Current Balance")]
                | //input[@value="Send me an email"]
            ';

            $this->logger->debug("wait result");
            $startTime = time();

            do {
                $tripsLink = null;
                $loading = null;

                try {
                    $tripsLink = $selenium->waitForElement(WebDriverBy::xpath($resultXpath), 0);
                    $this->savePageToLogs($selenium);
                    $loading = $selenium->waitForElement(WebDriverBy::xpath($loaderXpath), 0);

                    if ($select = $selenium->waitForElement(WebDriverBy::xpath('//div[h3[contains(text(), "Email")]]/following-sibling::div/a[contains(@class, "select-factor")]'), 0)) {
                        $select->click();
                    }
                } catch (StaleElementReferenceException | \Facebook\WebDriver\Exception\StaleElementReferenceException $e) {
                    $this->logger->error("StaleElementReferenceException Exception: " . $e->getMessage());
                } catch (Facebook\WebDriver\Exception\WebDriverException $e) {
                    $this->logger->error("Exception: " . $e->getMessage());
                    $this->logger->notice("brokenTrueBlue = true");
                    $this->State['brokenTrueBlue'] = true;
                    $this->State['brokenTrueBlueTime'] = time();

                    break;
                }

                if ($tripsLink === null) {
                    sleep(1);
                }

                $waitTime = time() - $startTime;
            } while (((!$loading && $waitTime < 30) || ($loading && $waitTime < 40)) && $tripsLink === null);

            // TODO: 2fa
            if ($sendEmail = $selenium->waitForElement(WebDriverBy::xpath('//input[@value="Send me an email"]'), 0)) {
                $selenium->driver->executeScript('
                    const constantMock = window.fetch;
                    window.fetch = function() {
                        console.log(arguments);
                        return new Promise((resolve, reject) => {
                            constantMock.apply(this, arguments)
                            .then((response) => {
                                if (response.url == "https://accounts.jetblue.com/idp/idx/challenge") {
                                    response
                                    .clone()
                                    .json()
                                    .then(body => localStorage.setItem("response2fa", JSON.stringify(body)));
                            }
                                resolve(response);
                            })
                        .catch((error) => {
                                reject(response);
                            })
                        });
                    }
                ');

                $sendEmail->click();

                $question = $selenium->waitForElement(WebDriverBy::xpath('//*[self::div or self::p][contains(text(), "We sent an email to")]'), 5);
                $this->savePageToLogs($selenium);

                $response2fa = $selenium->driver->executeScript("return localStorage.getItem('response2fa');");
                $this->logger->info("[Form response2fa]: " . $response2fa);

//                $seleniumDriver = $selenium->http->driver;
//                $requests = $seleniumDriver->browserCommunicator->getRecordedRequests();
//
//                foreach ($requests as $n => $xhr) {
//                    $this->logger->debug("xhr request {$n}: {$xhr->request->getVerb()} {$xhr->request->getUri()} " . json_encode($xhr->request->getHeaders()));
                ////                    $this->logger->debug("xhr response {$n}: {$xhr->response->getStatus()} {$xhr->response->getHeaders()}");
//
//                    if (stristr($xhr->request->getUri(), '/verify?rememberDevice=')) {
//                        $this->logger->debug("xhr response {$n} body: " . json_encode($xhr->response->getBody()));
//                        $stateToken = $this->http->JsonLog(json_encode($xhr->response->getBody()))->stateToken ?? null;
//                        $requestURL = $xhr->request->getUri();
//
//                        break;
//                    }
//                }

                if ($question && $this->http->ParseForm(null, '//form[@action="/signin"]')) {
                    $this->holdSession();
                    $this->AskQuestion($question->getText(), null, "Question");

                    $this->State['TWO_FA_TOKEN'] = $this->http->JsonLog($response2fa)->stateHandle ?? null;

                    try {
                        $cookies = $selenium->driver->manage()->getCookies();
                    } catch (InvalidArgumentException $e) {
                        $this->logger->error("Exception: " . $e->getMessage(), ['HtmlEncode' => true]);
                        // "InvalidArgumentException: Cookie name should be non-empty trace" workaround
                        $cookies = $selenium->http->driver->browserCommunicator->getCookies();
                    }

                    foreach ($cookies as $cookie) {
                        $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
                    }
                }// if ($question && $this->http->ParseForm(null, '//form[@action="/signin"]'))

                return false;
            }

            $this->increaseTimeLimit();
            $this->closePopups($selenium);
            $this->savePageToLogs($selenium);

            // provider bug fix
            $this->responseData = $selenium->driver->executeScript("return localStorage.getItem('responseData');");
            $this->logger->info("[Form responseData]: " . $this->responseData);

            // for reservations
            $reservations = $selenium->waitForElement(WebDriverBy::xpath('
                (//*[self::a or self::button][contains(text(), "My Trips") or contains(text(), "Manage Trips")])[1]
                | //h1[
                    contains(text(), "Confirm your Contact Info") 
                    or contains(text(), "Welcome to TrueBlue!") 
                    or contains(text(), "Accessing a Child Account")
                ]
                | //h2[contains(text(), "Current Balance")]
            '), 0);
            $this->closePopups($selenium);
            $this->savePageToLogs($selenium);

            $currentUrl = $selenium->http->currentUrl();
            $this->logger->debug("[Current URL]: {$currentUrl}");

            // TODO: broken accounts workaround, (AccountID: 7031344)
            $providerBugFix = $currentUrl === 'https://www.jetblue.com/';

            // it works sometimes
            if ($providerBugFix && !$selenium->driver->manage()->getCookieNamed('jbTrueBlueCookie')) {
                $selenium->driver->executeScript('
                    try {
                        document.querySelector(\'span[data-qaid="TrueBlue"]\').click();
                        setTimeout(function () {
                            document.querySelector(\'span[class="dib relative baseline-offset"]\').click();
                        }, 1000);
                    } catch (e) {}
                ');

                sleep(5);

                do {
                    $tripsLink = null;
                    $loading = null;

                    try {
                        $tripsLink = $selenium->waitForElement(WebDriverBy::xpath($resultXpath), 0);
                        $this->savePageToLogs($selenium);
                        $loading = $selenium->waitForElement(WebDriverBy::xpath($loaderXpath), 0);
                    } catch (StaleElementReferenceException | \Facebook\WebDriver\Exception\StaleElementReferenceException $e) {
                        $this->logger->error("StaleElementReferenceException Exception: " . $e->getMessage());
                    } catch (Facebook\WebDriver\Exception\WebDriverException $e) {
                        $this->logger->error("Exception: " . $e->getMessage());
                        $this->logger->notice("brokenTrueBlue = true");
                        $this->State['brokenTrueBlue'] = true;

                        break;
                    }

                    if ($tripsLink === null) {
                        sleep(1);
                    }

                    $waitTime = time() - $startTime;
                } while (((!$loading && $waitTime < 15) || ($loading && $waitTime < 40)) && $tripsLink === null);

                $this->increaseTimeLimit();
                $this->closePopups($selenium);
                $this->savePageToLogs($selenium);

                // provider bug fix
                $this->responseData = $selenium->driver->executeScript("return localStorage.getItem('responseData');");
                $this->logger->info("[Form responseData]: " . $this->responseData);

                $currentUrl = $selenium->http->currentUrl();
                $this->logger->debug("[Current URL]: {$currentUrl}");
            }// if ($currentUrl == 'https://www.jetblue.com/')

            if (
                (
                    $currentUrl == 'https://www.jetblue.com/'
                    || $currentUrl == 'https://www.jetblue.com/not-found'
                    || ($tripsLink === null && $providerBugFix === true)
                )
                && !$this->http->FindPreg('/ undefined pts <\/p/')
                && !$selenium->driver->manage()->getCookieNamed('jbTrueBlueCookie')
            ) {
                $retry = true;
                $this->markProxyAsInvalid();

                return false;
            }

            if ($reservations) {
                try {
                    $selenium->http->GetURL('https://www.jetblue.com/manage-trips');
                } catch (
                    Facebook\WebDriver\Exception\TimeoutException
                    | TimeoutException
                    | ScriptTimeoutException
                    $e
                ) {
                    $this->logger->error("TimeoutException: " . $e->getMessage(), ['HtmlEncode' => true]);
                    $selenium->driver->executeScript('window.stop();');
                } catch (UnknownServerException | Facebook\WebDriver\Exception\WebDriverException $e) {
                    $this->logger->error("Exception: " . $e->getMessage(), ['HtmlEncode' => true]);
                }
                $selenium->waitForElement(WebDriverBy::xpath("//h3/span[contains(text(), 'Upcoming trips')]"), 10);
                $this->savePageToLogs($selenium);

                try {
                    $selenium->http->GetURL('https://managetrips.jetblue.com/dx/B6DX/#/home?tabIndex=1&locale=en-US');
                } catch (
                    Facebook\WebDriver\Exception\TimeoutException
                    | TimeoutException
                    | ScriptTimeoutException
                    $e
                ) {
                    $this->logger->error("TimeoutException: " . $e->getMessage(), ['HtmlEncode' => true]);
                    $selenium->driver->executeScript('window.stop();');
                } catch (UnknownServerException | Facebook\WebDriver\Exception\WebDriverException $e) {
                    $this->logger->error("Exception: " . $e->getMessage(), ['HtmlEncode' => true]);
                }
                $selenium->waitForElement(WebDriverBy::xpath("//h3/span[contains(text(), 'Upcoming trips')]"), 7);
                $this->savePageToLogs($selenium);
                $this->closePopups($selenium);
                $this->State['itinerariesUrl'] = $selenium->http->currentUrl();
                $this->logger->notice("Itineraries Url: " . $this->State['itinerariesUrl']);
            }

            try {
                $cookies = $selenium->driver->manage()->getCookies();
            } catch (InvalidArgumentException $e) {
                $this->logger->error("Exception: " . $e->getMessage(), ['HtmlEncode' => true]);
                // "InvalidArgumentException: Cookie name should be non-empty trace" workaround
                $cookies = $selenium->http->driver->browserCommunicator->getCookies();
            }

            foreach ($cookies as $cookie) {
                if ($cookie['name'] == 'okta_access_token') {
                    $result = true;
                }

                // AccountID: 4287759
                if ($this->AccountFields['Login'] == 'pa.fb@brobergcapital.com' && $cookie['name'] == 'points') {
                    $this->SetBalance($cookie['value']);
                }

                $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
            }

            // refs #21104
            if ($result == true) {
                $this->JetBlueTravelBankSelenium($selenium, $evaluateMouse);
            }

            // save page to logs
            $this->savePageToLogs($selenium);

            $this->seleniumFail = false;
        } catch (ScriptTimeoutException | TimeOutException | SessionNotCreatedException | ElementNotVisibleException | MoveTargetOutOfBoundsException $e) {
            $this->logger->error("Exception: " . $e->getMessage(), ['HtmlEncode' => true]);
            $this->DebugInfo = "Exception";

            if (
                strstr($e->getMessage(), 'timeout')
                || strstr($e->getMessage(), 'session not created exception')
                || strstr($e->getMessage(), 'element not visible')
                || strstr($e->getMessage(), 'element not interactable')
                || strstr($e->getMessage(), 'is out of bounds of viewport width')
            ) {
                $retry = true;
            } elseif ($this->http->FindPreg("/timeout/", false, $e->getMessage())) {
                $this->sendNotification("timeout // RR");
                $retry = true;
            }

            // provider bug fix
            if (!empty($this->responseData)) {
                $retry = false;
            }
        }// catch (ScriptTimeoutException $e)
        catch (
            NoSuchDriverException
            | NoSuchWindowException
            | Facebook\WebDriver\Exception\UnknownErrorException
            | Facebook\WebDriver\Exception\WebDriverException
            $e
        ) {
            $this->logger->error("Exception: " . $e->getMessage(), ['HtmlEncode' => true]);
            $retry = true;
        } finally {
            $success = $result;

            if (
                (!$result && !empty($tripsLink))
                || !empty($this->responseData)
            ) {
                $success = true;
            }

            StatLogger::getInstance()->info("jetblue login attempt", [
                "success"      => $success,
                "proxy"        => $proxy,
                "retry"        => $retry,
                "browser"      => $selenium->seleniumRequest->getBrowser() . ":" . $selenium->seleniumRequest->getVersion(),
                "resolution"   => ($selenium->seleniumOptions->resolution[0] ?? null) . "x" . ($selenium->seleniumOptions->resolution[1] ?? null),
                "attempt"      => $this->attempt,
                "userAgentStr" => $selenium->http->userAgent,
                "isWindows"    => stripos($selenium->http->userAgent, 'windows') !== false,
            ]);

            if (!$success) {
                $selenium->markProxyAsInvalid();
            }
            // close Selenium browser
            $selenium->http->cleanup();
            // retries
            if ($retry && ConfigValue(CONFIG_SITE_STATE) != SITE_STATE_DEBUG) {
                throw new CheckRetryNeededException(3, 5);
            }
        }

        return $result;
    }

    public function JetBlueTravelBankSelenium($selenium, $evaluateMouse)
    {
        $this->logger->info('Travel Bank (Selenium Auth)', ['Header' => 3]);

        try {
            $selenium->http->GetURL("https://accounts.jetblue.com/oauth2/aus63a5bs52M8z9aE2p7/v1/authorize?prompt=login&response_type=code&nonce=abcdefg&scope=offline_access&idp=0oa6qe03vy6TWGN9o2p7&client_id=0oabozrr37UgDFHS32p7&redirect_uri=https://travelbank.jetblue.com/tbank/okta/oktaServlet.do&state={$this->AccountFields['Login']}");
        } catch (ScriptTimeoutException | TimeOutException $e) {
            $this->logger->error("TimeOutException Exception: " . $e->getMessage(), ['HtmlEncode' => true]);
            $this->driver->executeScript('window.stop();');
        } catch (UnknownServerException $e) {
            $this->logger->error("UnknownServerException: " . $e->getMessage(), ['HtmlEncode' => true]);
        }
        sleep(2);
        $loginInput =
            $selenium->waitForElement(WebDriverBy::xpath('//input[@formcontrolname="email" or @id = "okta-signin-username" or @id = "username"]'), 15, false)
            ?? $selenium->waitForElement(WebDriverBy::xpath('//input[@formcontrolname="email" or @id = "okta-signin-username" or @id = "username"]'), 0)
        ;
        $this->savePageToLogs($selenium);

        // long form loading
        if (!$loginInput && ($loader = $selenium->waitForElement(WebDriverBy::xpath('
                    //svg[@class = "loader"]
                    | //div[@class = "spinner-icon"]
                '), 0))
        ) {
            $this->savePageToLogs($selenium);
            $loginInput = $selenium->waitForElement(WebDriverBy::xpath('//input[@formcontrolname="email" or @id = "okta-signin-username" or @id = "username"]'), 10, false);
        }

        $this->logger->debug("find pass");
        // save page to logs
        $this->savePageToLogs($selenium);
        $passwordInput =
            $selenium->waitForElement(WebDriverBy::xpath('//input[@formcontrolname="password" or @id = "okta-signin-password" or @id = "password"]'), 0, false)
            ?? $selenium->waitForElement(WebDriverBy::xpath('//input[@formcontrolname="password" or @id = "okta-signin-password" or @id = "password"]'), 0)
        ;
        $this->logger->debug("find Sign in btn");
        $button =
            $selenium->waitForElement(WebDriverBy::xpath('//button[span[contains(., "Sign in") or contains(., "sign in")]] | //input[@id = "okta-signin-submit" or @id = "submit"]'), 0, false)
            ?? $selenium->waitForElement(WebDriverBy::xpath('//button[span[contains(., "Sign in") or contains(., "sign in")]] | //input[@id = "okta-signin-submit" or @id = "submit"]'), 0)
        ;

        $this->closePopups($selenium);

        // save page to logs
        $this->savePageToLogs($selenium);

        if (!$loginInput && $passwordInput && $button) {
            $loginInput = $selenium->waitForElement(WebDriverBy::xpath('//input[@formcontrolname="email" or @id = "okta-signin-username" or @id = "username"]'), 0, false);
        }

        if (!$loginInput || !$passwordInput || !$button) {
            // too long loading?
            if ($selenium->waitForElement(WebDriverBy::xpath('//div[@class = "spinner-icon"]'), 0)) {
                $retry = true;
            }

            if ($selenium->waitForElement(WebDriverBy::xpath('//span[contains(text(), "Account Summary")]'), 0)) {
                $cookies = $selenium->driver->manage()->getCookies();

                foreach ($cookies as $cookie) {
                    $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
                }

                return true;
            }

            if ($selenium->waitForElement(WebDriverBy::xpath('
                        //h1[
                            contains(text(), "Page not found")
                            or contains(text(), "Error 503 backend read error")
                        ]
                    '), 0)
            ) {
                $selenium->http->GetURL("https://www.jetblue.com/signin?returnUrl=https:%2F%2Fwww.jetblue.com%2F");

                $selenium->waitForElement(WebDriverBy::xpath('//input[@formcontrolname="email" or @id = "okta-signin-username" or @id = "username"]'), 5);
            }
            /*
            if ($selenium->waitForElement(WebDriverBy::xpath('//div[@id = "gladlyChat_container"]'), 0)) {
                $retry = true;
            }
            */
            $this->savePageToLogs($selenium);

            return $this->checkErrors();
        }

        if ($this->seleniumRequest->getBrowser() === SeleniumFinderRequest::BROWSER_FIREFOX) {
            // firefox does not support getting browser logs
            // https://github.com/mozilla/geckodriver/issues/284
            $this->logger->info("setting FF exception handler");

            try {
                // do not work actually, should debug it
                $selenium->driver->executeScript('
                    var ul = null;
                    function createErrorList() {
                        ul = document.createElement(\'ul\');
                        ul.setAttribute(\'id\', \'js_error_list\');
                        ul.style.display = \'none\';
                        document.body.appendChild(ul);
                    }
                    window.onerror = function(msg){
                        if (ul === null)
                            createErrorList();
                        var li = document.createElement("li");
                        li.appendChild(document.createTextNode(msg));
                        ul.appendChild(li);
                    };
                ');
            } catch (Exception $exception) {
                $this->logger->warning("could not set exception handler: " . substr($exception->getMessage(), 0, 50));
            }
        }

        if ($evaluateMouse) {
            $mover = new MouseMover($selenium->driver);
            $mover->logger = $this->logger;
            $mover->duration = 100000;
            $mover->steps = 50;
        }

        $this->logger->debug("enter login");

        if ($evaluateMouse) {
            $mover->moveToElement($loginInput);
            $mover->click();
            $mover->sendKeys($loginInput, $this->AccountFields['Login'], 10);
        } else {
            $loginInput->sendKeys($this->AccountFields['Login']);
        }

        $this->logger->debug("enter password");

        $this->closePopups($selenium);

        if ($evaluateMouse) {
            $mover->moveToElement($passwordInput);
            $this->savePageToLogs($selenium);
            $mover->click();
            $this->savePageToLogs($selenium);
            $passwordInput = $selenium->waitForElement(WebDriverBy::xpath('//input[@formcontrolname="password" or @id = "okta-signin-password" or @id = "password"]'), 0);

            if (!$passwordInput) {
                return false;
            }
            $mover->sendKeys($passwordInput, $this->AccountFields['Pass'], 10);
        } else {
            $this->savePageToLogs($selenium);
            $passwordInput->sendKeys($this->AccountFields['Pass']);
        }
        // Sign In
        $this->logger->debug("click 'Sign In'");
        $this->closePopups($selenium);
        $this->savePageToLogs($selenium);

        // provider bug fix
        $selenium->driver->executeScript('
                    let oldXHROpen = window.XMLHttpRequest.prototype.open;
                    window.XMLHttpRequest.prototype.open = function(method, url, async, user, password) {
                        this.addEventListener("load", function() {
                            if (/\/iam\/login/g.exec( url )) {
                                localStorage.setItem("responseData", this.responseText);
                            }
                        });
                        return oldXHROpen.apply(this, arguments);
                    };
                ');

        try {
            $button->click();
        } catch (StaleElementReferenceException | Exception $e) {
            $this->logger->error("StaleElementReferenceException: " . $e->getMessage(), ['HtmlEncode' => true]);
            $this->savePageToLogs($selenium);

            $button =
                $selenium->waitForElement(WebDriverBy::xpath('//button[span[contains(., "Sign in") or contains(., "sign in")]] | //input[@id = "okta-signin-submit" or @id = "submit"]'), 0, false)
                ?? $selenium->waitForElement(WebDriverBy::xpath('//button[span[contains(., "Sign in") or contains(., "sign in")]] | //input[@id = "okta-signin-submit" or @id = "submit"]'), 0)
            ;

            if ($button) {
                $button->click();
            }
        }

        sleep(3);
        $selenium->waitForElement(WebDriverBy::xpath('
            //div[@id = "errorBlock"]
            | //span[contains(text(), "Account Summary")]
        '), 10);
        $this->savePageToLogs($selenium);

        $cookies = $selenium->driver->manage()->getCookies();

        foreach ($cookies as $cookie) {
            $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
        }

        return true;
    }

    public function closePopups($selenium)
    {
        $this->logger->notice(__METHOD__);

        try {
            $selenium->driver->executeScript('var overlay = document.getElementsByClassName(\'truste_overlay\'); if (overlay && typeof (overlay[0]) != \'undefined\') overlay[0].style.display = "none";');
            $selenium->driver->executeScript('var overlay = document.getElementsByClassName(\'truste_box_overlay\'); if (overlay && typeof (overlay[0]) != \'undefined\') overlay[0].style.display = "none";');
            $selenium->driver->executeScript('var overlay = document.getElementsByClassName(\'browserWarningOverlay-bg\'); if (overlay && typeof (overlay[0]) != \'undefined\') overlay[0].style.display = "none";');
            $selenium->driver->executeScript('var overlay = document.getElementsByClassName(\'jb-overlay-cont\'); if (overlay && typeof (overlay[0]) != \'undefined\') overlay[0].style.display = "none";');
            $selenium->driver->executeScript('var overlay = document.getElementById(\'bw-close-button\'); if (overlay) overlay.click();');
        } catch (UnexpectedJavascriptException $e) {
            $this->logger->error("UnexpectedJavascriptException: " . $e->getMessage(), ['HtmlEncode' => true]);
        }
//            $acceptCookies = $selenium->waitForElement(WebDriverBy::xpath('//a[contains(text(), "Accept All Cookies")]'), 0);
//            if ($acceptCookies) {
//                $acceptCookies->click();
//            }
    }

    public function GetRedirectParams($targetURL = null)
    {
        $arg = parent::GetRedirectParams($targetURL);

        if ($this->isMobile()) {
            $arg['CookieURL'] = 'https://mobile.jetblue.com/mt/trueblue.jetblue.com/group/trueblue/my-trueblue-home';
        } else {
            $arg['CookieURL'] = 'https://trueblue.jetblue.com/group/trueblue/my-trueblue-homessss/';
        }

        return $arg;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);

        if ($msg = $this->http->FindPreg("/You don't have permission to access/")) {
            $this->logger->notice($msg);
            $this->DebugInfo = $msg;
            $this->http->GetURL("https://www.jetblue.com/signin");
        }

        // Planned TrueBlue maintenance
        if (
            $message = $this->http->FindSingleNode("
                //title[contains(text(), 'Planned TrueBlue maintenance')]
                | //h1[contains(text(), 'TrueBlue scheduled upgrade in progress.')]
                | //p[
                        contains(text(), 'jetblue.com is currently undergoing maintenance. Full service to the website should be restored shortly')
                        or contains(text(), 'jetblue.com is currently undergoing maintenance. Full service to the website should be back shortly')
                        or contains(., 'TrueBlue sign in is currently unavailable. You can still manage your upcoming bookings by visiting')
                        or contains(., 'TrueBlue is currently undergoing scheduled maintenance.')
                    ]
            ")
        ) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // Jetblue.com is temporarily unavailable. Full service to the website should be restored shortly. We apologize for the inconvenience.
        if ($this->http->currentUrl() == 'https://ucampaign.usablenet.net/jetblue/Maintenance+R9/') {
            throw new CheckException("Jetblue.com is temporarily unavailable. Full service to the website should be restored shortly. We apologize for the inconvenience. We promise we'll be back soon! Thanks for your patience.", ACCOUNT_PROVIDER_ERROR);
        }
        //# We are currently experiencing technical difficulties
        if ($message = $this->http->FindSingleNode("//div[contains(text(), 'We are currently experiencing technical difficulties')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        //# Message: "We are experiencing technical difficulties now"
        if ($message = $this->http->FindSingleNode("//div[contains(text(), 'We are experiencing technical difficulties now')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // We're currently undergoing planned maintenance.
        if ($message = $this->http->FindSingleNode('//p[contains(text(), "We\'re currently undergoing planned maintenance.")]')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        //# TrueBlue is currently undergoing routine maintenance
        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'TrueBlue is currently undergoing routine maintenance')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // JetBlue.com is currently undergoing scheduled maintenance
        if ($message = $this->http->FindPreg("/(JetBlue\.com is currently undergoing scheduled maintenance and is temporarily unavailable\. Full service to the website should be restored shortly\. Please check back soon. We apologize for the inconvenience\.)/ims")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // We're currently experiencing technical issues with booking a flight.
        if ($message = $this->http->FindSingleNode('//p[contains(text(), "We\'re currently experiencing technical issues with booking a flight.")]')) {
            throw new CheckException("We are currently experiencing technical difficulties. We promise we'll be back soon! Thanks for your patience.", ACCOUNT_PROVIDER_ERROR);
        }
        // JetBlue.com is currently undergoing scheduled maintenance
        if (isset($this->http->Response['code']) && $this->http->Response['code'] == 301 && strstr($this->http->currentUrl(), 'maintenance/default1.aspx')) {
            throw new CheckException("JetBlue.com is currently undergoing scheduled maintenance and is temporarily unavailable. Full service to the website should be restored shortly. Please check back soon. We apologize for the inconvenience.", ACCOUNT_PROVIDER_ERROR);
        }
        //# Error 404
        if (isset($this->http->Response['code']) && $this->http->Response['code'] == 404) {
            throw new CheckException('The website is currently unavailable. Please try again later.', ACCOUNT_PROVIDER_ERROR);
        } /*checked*/
        //# The parameter is incorrect
        if ($this->http->FindPreg("/(The parameter is incorrect\.)/ims")) {
            throw new CheckException('The website is currently unavailable. Please try again later.', ACCOUNT_PROVIDER_ERROR);
        } /*checked*/
        // The service is unavailable.
        if ($message = $this->http->FindPreg("/(The service is unavailable\.)/ims")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // Page unavailable
        if ($message = $this->http->FindPreg('#There was an issue with the page you were visiting. Our technicians have been notified and are looking into the problem\.#i')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // Internal Server Error - Read
        if ($this->http->FindSingleNode("//h1[contains(text(), 'Internal Server Error - Read')]")
            || $this->http->FindSingleNode("//h1[contains(text(), 'Service Unavailable - Zero size object')]")
            || $this->http->FindSingleNode("//h1[contains(text(), '502 Bad Gateway')]")
            || $this->http->FindSingleNode("//title[contains(text(), '503 Service Temporarily Unavailable')]")
            || ($this->http->currentUrl() == 'https://book.jetblue.com/error/generic/' && isset($this->http->Response['code']) && $this->http->Response['code'] == 302)) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        // retries
        if (!$msg
            && (
            $this->http->FindSingleNode("
                //h1[contains(text(), 'This site can’t be reached')]
                | //pre[contains(text(), '403 Forbidden')]
            ")
            || $this->http->FindPreg('/page isn’t working/ims')
            || $this->http->FindPreg('/<(?:span|div|a|pre) id="[^\"]+" style="display: none;"><\/(?:span|div|a|pre)><(?:span|div|a|pre) id="[^\"]+" style="display: none;"><\/(?:span|div|a|pre)><\/body>/ims')
        )) {
            throw new CheckRetryNeededException(3, 0);
        }

        if ($this->http->FindSingleNode("//p[contains(text(), 'The page you’re trying to visit is unavailable, but we have plenty of other pages on jetblue.com worth exploring!')]")) {
            throw new CheckRetryNeededException(3, 0);
        }

        return false;
    }

    public function Login()
    {
        // $response = $this->http->JsonLog();
        if (
            (
                $this->http->getCookieByName("okta_access_token")
                || $this->http->getCookieByName("jbTrueBlueCookie")// AccountID: 2451833
            )
            && !$this->http->FindPreg("/>403 Forbidden<\/pre>/")
        ) {
            return true;
        }

        // AccountID: 4267092
        if ($this->http->FindPreg("/>403 Forbidden<\/pre>/")
            && in_array($this->AccountFields['Login'], [
                'corygoneke@me.com',
                'liam23vp@gmail.com',
                'Karenlyespada@gmail.com',
                'kipp916@gmail.com',
            ])
        ) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }
        // broken account   // AccountID: 4880554
        if (in_array($this->AccountFields['Login'], [
            'camupod@gmail.com',
        ])
        ) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        if ($message = $this->http->FindSingleNode('//div[contains(@class, "red") and not(@hidden)] | //div/p[contains(@class, "red")] | //div[contains(@class, "okta-form-infobox-error")]/p | //div[contains(@class, "o-form-has-errors")]/p')) {
            $this->logger->error("[Error]: {$message}");

            if (
                strstr($message, "Either your username or password isn't right")
                || strstr($message, "Please enter your email to get started.We need a valid email address.")
                || strstr($message, "Either your email or password isn't right.")
                || strstr($message, "Either your username or password is incorrect. If your password was created before Jul 31 2020")
                || $message == "Please enter a valid password."
            ) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            if (strstr($message, "Either your username or password is incorrect")) {
                throw new CheckRetryNeededException(3, 0, $message, ACCOUNT_INVALID_PASSWORD);
            }

            if (
                // message: "SSO Service has an error, please contact JetBlue Support. !!!"
                strstr($message, "Oops. Our servers are being wonky right now. We’re sorry for the bump on the road to all-out JetBlue bliss. Please try again later")
                || $message == "Unable to sign in"
                || $message == "There was an unsupported response from server."
            ) {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }

            // message on login form - site blocks auth
            if (
                $message == "An error has occurred. Please retry."
                || $message == "Cannot read property 'status' of null"
            ) {
                $this->DebugInfo = 'request has been blocked';

                if (
                    $this->seleniumFail === true
                    || ConfigValue(CONFIG_SITE_STATE) == SITE_STATE_DEBUG
                ) {
                    return false;
                }

                if ($this->AccountFields['Login'] == 'pa.fb@brobergcapital.com') {// AccountID: 4287759
                    return false;
                }

                throw new CheckRetryNeededException(3, 3);
            }

            $this->DebugInfo = $message;

            return false;
        }// if ($message = $this->http->FindSingleNode('//div[contains(@class, "red") and not(@hidden)] | //div/p[contains(@class, "red")]'))

        if ($message = $this->http->FindSingleNode("//span[not(@hidden) and contains(text(), 'We need a valid email address.')]")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        if ($this->responseData) {
            $this->logger->debug("[JSON]: {$this->responseData}");

            if (
                strstr($this->responseData, '{"error":{"httpStatus":"401","code":"JB_INVALID_CREDENTIALS","message":"Invalid credentials","guid":"')
                || $this->responseData == '{"error":{"httpStatus":"401","code":"JB_INVALID_CREDENTIALS","message": "Invalid credentials",}}'
                || $this->http->FindPreg("/\{\s*\"error\"\s*:\{\"httpStatus\"\s*:\s*\"401\",\s*\"code\":\s*\"JB_INVALID_CREDENTIALS\",\s*\"message\":\s*\"Invalid credentials\",\s*\}\s*\}/", false, $this->responseData)
            ) {
                throw new CheckException("Either your username or password is incorrect. If your password was created before Jul 31 2020 please set a new one.", ACCOUNT_INVALID_PASSWORD);
            }

            return false;
        }

        if ($this->http->FindSingleNode('//input[@formcontrolname="password" or @id = "okta-signin-password"]/@name')) {
            throw new CheckRetryNeededException(3, 3);
        }

        return $this->checkErrors();
    }

    public function ProcessStep($step)
    {
        $answer = $this->Answers[$this->Question];
        unset($this->Answers[$this->Question]);

        if (empty($this->State['TWO_FA_TOKEN'])) {
            $this->logger->error("2fa token not found");

            return false;
        }

        $stateHandle = $this->State['TWO_FA_TOKEN'];
        unset($this->State['TWO_FA_TOKEN']);

        $data = [
            "credentials" => [
                "passcode" => $answer,
            ],
            "stateHandle" => $stateHandle,
        ];
        $headers = [
            "Accept"                     => "application/json",
            "Accept-Language"            => "en",
            "Accept-Encoding"            => "gzip, deflate, br, zstd",
            "Content-Type"               => "application/json",
            "Referer"                    => "https://www.jetblue.com/",
            "X-Okta-User-Agent-Extended" => "okta-auth-js/7.0.2 okta-signin-widget-7.14.1",
            "Origin"                     => "https://www.jetblue.com",
        ];
        $this->http->RetryCount = 0;
        $this->http->PostURL("https://accounts.jetblue.com/idp/idx/challenge/answer", json_encode($data), $headers);
        $this->http->RetryCount = 2;
        $response = $this->http->JsonLog();

        if (!isset($response->successWithInteractionCode)) {
            $this->logger->error("something went wrong");

            return false;
        }

        $data = [
            "redirect_uri"  => "https://www.jetblue.com/login-callback",
            "code_verifier" => "3e21795778d2dace84ca12567242744d0b100a33482", // TODO: hard code
        ];

        foreach ($response->successWithInteractionCode->value as $postField) {
            if (!isset($postField->value)) {
                continue;
            }

            $data[$postField->name] = $postField->value;
        }
        // https://accounts.jetblue.com/oauth2/aus63a5bs52M8z9aE2p7/v1/token
        $this->http->PostURL($response->successWithInteractionCode->href, $data);
        $this->http->JsonLog();

//        $this->http->GetURL("https://accounts.jetblue.com/oauth2/aus63a5bs52M8z9aE2p7/v1/authorize?client_id=0oa6pzarfiDxq8sn32p7&code_challenge=GoIl-ybfxzhARGg2C63zSJXTqBkFPezTFDKjNPA-deE&code_challenge_method=S256&nonce=xQtX2pvBFE35RZtdFYg48bGZOf8WG4TxYVUD1fMkWYxh0R8Ro86PRZvc2pNtQCTT&redirect_uri=https%3A%2F%2Fwww.jetblue.com%2Flogin-callback&response_type=code&sessionToken=201119BwHg12YeoGmjpW0XyCfrqLr31-pMeVHrgfVOE2CEHGkM7khCQ&state=OW8CTFI2MZeZraDiC2spiFPfFaefuxwXMCTLAoKNevRegSBVhyppZlEGtXziHHBP&scope=openid%20email&is_authn=true"); // TODO

        $this->http->GetURL("https://trueblue.jetblue.com/dashboard/", [], 20);

        return false;
    }

    public function Parse()
    {
        /*
        if (!strstr($this->http->currentUrl(), 'https://trueblue.jetblue.com/b2c/authorization/generate-JWT-token-MP-SSO?tgtToken=')) {
            $this->http->GetURL("https://trueblue.jetblue.com/b2c/authorization/generate-JWT-token-MP-SSO?tgtToken=" . $this->http->getCookieByName("SSWEB2TGC"));
        }
        */
        $headers = [
            'Accept' => 'application/json, text/plain, */*',
        ];
        $this->http->setCookie('okta_access_token', $this->State['oktaAccessToken'], '.jetblue.com', '/', null, true);
        $this->http->GetURL('https://trueblue.jetblue.com/b2c/authorization/generate-JWT-token-MP-SSO', $headers);
        $response = $this->http->JsonLog();

        /*
        // https://redmine.awardwallet.com/issues/24593#note-6
        if (!isset($response)) {
            $this->State['WrongPage'] = true;
            throw new CheckRetryNeededException(3, 0, self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }
        */

        if (!isset($response->access_token)) {
            // Down for maintenance
            if (
                stripos($this->http->currentUrl(), 'https://www.jetblue.com/error/trueblue/') !== false
                || stripos($this->http->currentUrl(), 'https://www.jetblue.com/mx/trueblue-maintenance?tgtToken=') !== false
            ) {
                throw new CheckException('TrueBlue is currently undergoing routine maintenance. Please check back soon.', ACCOUNT_PROVIDER_ERROR);
            }
            // AccountID: 4880554
            if ($this->http->FindPreg("/^\{\"error\":\"OTHER_ERROR\",\"message\":\"Error occurred.\",\"path\":\"\/b2c\/authorization\/generate-JWT-token-MP-SSO\"/")) {
                throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
            }
            // AccountID: 4367674, 2451833
            if (
                $this->http->Response['code'] == 500
                && isset($response->error, $response->message, $response->path)
                && $response->error == 'FIELD_NOT_MATCH_PATTERN'
                && $response->path == '/b2c/authorization/generate-JWT-token-MP-SSO'
                && strstr($response->message, "Invalid field 'email' value: " . strtoupper($this->AccountFields['Login']) . ", pattern:")
            ) {
                throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
            }

            /*
            $this->maintenanceMode();
            */

            return;
        }

        $this->headers = [
            "Accept"        => "application/json, text/plain, */*",
            "Authorization" => "Bearer {$response->access_token}",
            "Content-Type"  => "application/json",
        ];

        $this->http->RetryCount = 0;
        $this->http->GetURL("https://trueblue.jetblue.com/b2c/me", $this->headers);
        $this->http->RetryCount = 2;
        $response = $this->http->JsonLog();

        if (isset($response, $response->error)) {
            if (strstr($response->error, 'OKTA_TOKEN_EXPIRED')) {
                throw new CheckRetryNeededException(3, 0);
            }
        }

        /*
        if (!isset($response->login)) {
            // AccountID: 4287759
            $this->maintenanceMode();

            return;
        }
        */

        if (strtolower($response->login) != strtolower($this->AccountFields['Login'])) {
            $this->sendNotification("wrong main info // RR");
            $this->logger->error("wrong data");
            // todo
            $this->http->GetURL("https://trueblue.jetblue.com/b2c/me", $this->headers);
            $response = $this->http->JsonLog();

            return;
        }

        if (!$this->SetBalance($response->mainPointsBalance ?? null)) {
            return;
        }
        $this->ownerCustomerId = $response->id;
        // Name
        $this->SetProperty("Name", beautifulName($response->firstNameANSI . " " . $response->lastNameANSI));
        $this->lastName = $response->lastNameANSI;
        // TrueBlue #
        $this->SetProperty("Number", $response->mainIdentifier ?? null);

        // refs #18076
        $hasMosaicTier = $response->hasMosaicTier ?? null;

        // Status (only first status here, Mosaic statuses parsed with Tiles below)
        if ($hasMosaicTier === false) {
            $this->SetProperty('Status', 'TrueBlue');
        } else {
            $this->SetProperty('Status', $response->recognition ?? null);
        }

        // Status Expiration
        if (isset($response->qualifiedForNextYear) && $response->qualifiedForNextYear && $hasMosaicTier) {
            $this->sendNotification("refs # 23132 - need to check qualifiedTierEndDate // RR");
            $this->SetProperty("StatusExpiration", "12/31/" . (date("Y") + 1));
        } elseif ($hasMosaicTier) {
            $this->SetProperty("StatusExpiration", strtotime($response->qualifiedTierEndDate));
        }

        if (isset($response->pointExpirationDisabled) && $response->pointExpirationDisabled != false) {
            $this->sendNotification("jetblue - refs #17120. Exp date was found");
        }

        // Member since
        if (isset($response->enrolmentDate)) {
            $this->SetProperty("MemberSince", date("F Y", strtotime($response->enrolmentDate)));
        }

        // refs #19944
        $this->logger->info('Zip Code', ['Header' => 3]);
        $this->logger->debug('ZipCodeParseDate: ' . ($this->State['ZipCodeParseDate'] ?? null));
        $this->logger->debug('Time: ' . strtotime("-1 month"));

        if (
            !isset($this->State['ZipCodeParseDate'])
            || $this->State['ZipCodeParseDate'] < strtotime("-1 month")
        ) {
            $zip = $response->address->postalCode ?? null;
            $country = $response->address->country ?? null;

            if ($country == 'USA' && strlen($zip) == 9) {
                $zipCode = substr($zip, 0, 5) . " " . substr($zip, 5);
            } else {
                $zipCode = $zip;
            }
            $this->SetProperty("ZipCode", $zipCode);
            $street = $response->address->extAddressLine1 ?? null;
            $state = '';

            if (isset($response->address->region)) {
                $state = ", " . $response->address->region;
            }

            if ($zipCode && $street) {
                $this->SetProperty("ParsedAddress",
                    $street
                    . ", " . $response->address->city
                    . $state
                    . ", " . $zipCode
                    . ", " . $country
                );
            }// if ($zipCode)
            $this->State['ZipCodeParseDate'] = time();
        }// if (!isset($this->State['ZipCodeParseDate']) || $this->State['ZipCodeParseDate'] > strtotime("-1 month"))

        $this->http->GetURL("https://trueblue.jetblue.com/b2c/me/ext/account", $this->headers);
        $householdResponse = $this->http->JsonLog();
        $household = $householdResponse->household ?? null;
        // Family Balance
        if ($household) {
            $this->SetProperty("FamilyBalance", number_format($response->poolPointsBalance ?? null));
        }

        $this->http->GetURL("https://trueblue.jetblue.com/b2c/me/progressTrackers", $this->headers);
        $trackers = $this->http->JsonLog();
        $error = $trackers->error ?? null;

        if (is_null($error) && !empty($trackers)) {
            foreach ($trackers as $tracker) {
                if ($tracker->code == 'MTT' && is_numeric($tracker->currentValue ?? null)) {
                    $this->parseTilesAndMosaicStatus($tracker->currentValue);

                    break;
                }
            }// foreach ($trackers as $tracker)
        }

        $this->logger->info('Move to Mint', ['Header' => 3]);
        $this->http->GetURL("https://trueblue.jetblue.com/b2c/me/mint-coupons", $this->headers);
        $mints = $this->http->JsonLog();
        $mintBalance = 0;
        $exp = false;

        foreach ($mints as $mint) {
            $expiryDate = strtotime($mint->expiryDate);

            if ($mint->statusName != 'Issued' || $expiryDate < time()) {
                continue;
            }

            $mintBalance++;

            if (!$exp || $exp > $expiryDate) {
                $exp = $expiryDate;
            }
        }// foreach ($mints as $mint)

        if ($mintBalance) {
            $this->AddSubAccount([
                "Code"           => "MoveToMint",
                "DisplayName"    => "Move to Mint",
                "Balance"        => $mintBalance,
                'ExpirationDate' => $exp,
            ], true);
        }

        // refs #5052
        $this->JetBlueTravelBank();
    }

    public function JetBlueTravelBank()
    {
        $this->logger->notice("___ Jet Blue (Travel Bank) ___");
        $this->logger->info('Travel Bank', ['Header' => 3]);
        $this->http->FilterHTML = false;
        /*
        $this->http->GetURL("https://travelbank.prod.sabre.com/tbank279/user/login.html");

        $login = $this->http->FindSingleNode("//input[@id = 'username']/@name");
        $password = $this->http->FindSingleNode("//input[@id = 'password']/@name");
        $formID = $this->http->FindSingleNode("//input[@id = 'username']/ancestor::form[1]/@id");
        $logout = $this->http->FindSingleNode("//a[@id = 'userLogout']");

        if ((!isset($formID) || !$this->http->ParseForm($formID)) && !$logout) {
            $this->logger->error("Travel Bank -> form is not found");

            return;
        }
        $this->http->FormURL = "https://travelbank.prod.sabre.com/tbank/ajax_request/";
        $this->http->SetInputValue($login, $this->AccountFields['Login']);
        $this->http->SetInputValue($password, $this->AccountFields['Pass']);
        $headers = [
            "X-Requested-With" => "XMLHttpRequest",
            "Accept"           => "text/javascript, application/javascript, application/ecmascript, application/x-ecmascript, *
        /*; q=0.01",
        ];
        usleep(500);

        if (!$logout && !$this->http->PostForm($headers)) {
            return;
        }

        if ($message = $this->http->FindPreg("/The Username that has been input can not be used to login on this page\./ims")
                ?? $this->http->FindPreg("/Incorrect login or password\./ims")
                ?? $this->http->FindPreg("/For login support please review the\s*instructions below\./ims")
        ) {
            $this->logger->error(">>>> " . $message);

            return;
        }

        $this->http->GetURL("https://travelbank.prod.sabre.com/tbank/user/main.html");

        if (
            $this->http->currentUrl() == 'https://travelbank.prod.sabre.com/tbank/user/500.html'
            && $this->http->FindSingleNode("//a[@id = 'userLogout']")
        ) {
            $this->http->GetURL("https://travelbank.prod.sabre.com/tbank/user/main.html");
        }
        */
        $this->http->GetURL("https://travelbank.jetblue.com/tbank/user/main.html");

        if (!$this->http->FindSingleNode("//a[@id = 'userLogout']")) {
            return;
        }

        // Your Account Balance
        $availableBalance = $this->http->FindSingleNode("//span[@id = 'accountBalance']", null, true, "/([\d\.\,]+)/ims");
        $this->logger->debug("Account Balance: " . $availableBalance);

        if (!empty($availableBalance)) {
            $this->SetProperty("CombineSubAccounts", false);
            $expNodes = $this->http->XPath->query("//table[@id = 'transactionsContent']//tr[td]");
            $this->logger->debug("Total nodes were found: " . $expNodes->length);

            if ($expNodes->length === 0) {
                $this->AddSubAccount([
                    "Code"           => "JetBlueTravelBank",
                    "DisplayName"    => "Jet Blue (Travel Bank)",
                    "Balance"        => $availableBalance,
                    "Number"         => $this->http->FindSingleNode("//span[@id = 'accountNumber']"),
                ], true);
            }

            for ($i = 0; $i < $expNodes->length; $i++) {
                $balance = $this->http->FindSingleNode("td[1]", $expNodes->item($i));
                $date = $this->http->FindSingleNode("td[2]", $expNodes->item($i));
                $exp = strtotime($date);
                $this->AddSubAccount([
                    "Code"           => "JetBlueTravelBank" . $exp . $balance,
                    "DisplayName"    => "Jet Blue (Travel Bank)",
                    "Balance"        => $balance,
                    "Number"         => $this->http->FindSingleNode("//span[@id = 'accountNumber']"),
                    "ExpirationDate" => $exp,
                ], true);
            }// for ($i = 0; $i < $expNodes->length; $i++)
        }// if (!empty($availableBalance))
    }

    public static function FormatBalance($fields, $properties)
    {
        if (isset($properties['SubAccountCode']) && strstr($properties['SubAccountCode'], 'JetBlueTravelBank')) {
            return formatFullBalance($fields['Balance'], $fields['ProviderCode'], "$%0.2f");
        }

        return parent::FormatBalance($fields, $properties);
    }

    public function correctDateTime($bookDate, $otherDate, $correctPeriod = '+1 year')
    {
        $this->logger->notice(__METHOD__);

        if (!is_numeric($bookDate) || !is_numeric($otherDate)) {
            return $otherDate;
        }
        $this->logger->debug("Old date: {$otherDate} ");

        if ($otherDate < $bookDate) {
            $otherDate = strtotime($correctPeriod, $otherDate);
            $this->logger->debug("New date: {$otherDate} Period: {$correctPeriod}");
        }

        return $otherDate;
    }

    public function ParseItinerary()
    {
        $this->logger->notice(__METHOD__);
        $startTimer = time();
        $airLogos = [
            "AA" => "American Airlines",
            "B6" => "JETBLUE AIRWAYS",
        ];
        $result = [];
        $result['Kind'] = 'T';
        // ConfirmationNumber
        // What is this code for?
        $result['RecordLocator'] = $this->http->FindSingleNode("//h2[@id = 'confirmationPNR']", null, true, '/Confirmation \#([^<]+)/ims');
        // Kiosk Checkin Bar Code
        $result['KioskCheckinCode'] = $result['RecordLocator'];
        $result['KioskCheckinCodeFormat'] = 'CODE_39';
        // Status
        $result['Status'] = $this->http->FindSingleNode("//div[@id='booking_status']//dl/dd[1]");
        // AccountNumber
        $accounts = $this->http->FindNodes('//div[@id="printItineraryBody"]//td[@class="itin-passenger"]/span[2]');

        for ($a = 0; $a < count($accounts); $a++) {
            if (preg_match('/(\d+)/', $accounts[$a], $matches)) {
                $accountsArr[] = $matches[1];
            }
        }

        if (isset($accountsArr[0])) {
            $result['AccountNumbers'] = implode(', ', array_unique($accountsArr));
        }
        // ReservationDate
        $resDate = $this->http->FindSingleNode("//div[@id='booking_status']//dl/dd[2]");

        if (strtotime($resDate) !== false) {
            $result['ReservationDate'] = strtotime($resDate);
        }

        $segments = [];
        $xpath = $this->http->XPath;
        $bookings = $xpath->query("//div[@id='itinerary_details']//table/tbody/tr[td[not(contains(@class, 'separator'))][not(@class='itin-pic')]]");
        $this->logger->debug("Total segments found: " . $bookings->length);
        // bad html fix
        if ($bookings->length == 0) {
            $this->logger->notice("Site bug fix");
            $this->http->Response['body'] = str_replace('<td class="itin-date"', '<tr><td class="itin-date"', $this->http->Response['body']);
            $this->http->SetBody($this->http->Response['body'], true);
            $xpath = $this->http->XPath;
            $bookings = $this->http->XPath->query("//div[@id='itinerary_details']//table/tbody/tr[td[not(contains(@class, 'separator'))][not(@class='itin-pic')]]");
            $this->http->Log("Total segments found: " . $bookings->length);
        }// if ($bookings->length == 0)
        $travelers = $xpath->query("//div[@id='itinerary_details']/following-sibling::div[1]/table//tr[td[@class='itin-date']]");
        $this->logger->debug("Total nodes' Seats found: " . $travelers->length);
        $airlines = $this->http->FindNodes("//div[@id='itinerary_details']//table/tbody/tr/td[@class='itin-pic']//img/@src");
        $passengers = [];
        $stops = 0;

        if ($bookings->length > 0) {
            $correct = 0;

            for ($i = 0; $i < $bookings->length; $i++) {
                $c = $i - $correct;
                $booking = $bookings->item($i);
                $traveler = $travelers->item($i);
                $line = $xpath->query('td', $booking);

                if ($stops > 0) {
                    $segments[$c - 1]['ArrCode'] = $this->http->FindSingleNode('td[2]/strong[2]', $booking, true, '/\(([A-Z]{3})\)/');
                    $segments[$c - 1]['ArrName'] = $this->http->FindSingleNode('td[2]/strong[2]', $booking, true, '/(.*)\([A-Z]{3}\)/');

                    $segments[$c - 1]['Stops'] = $stops;
                    $stops = 0; // no cicles
                } else {
                    $stops = 0;

                    if ($temp = $this->http->FindSingleNode('td[1]/@rowspan', $booking)) {
                        if ($temp > 1) {
                            $stops = $temp - 1;
                            $this->logger->debug("There are $stops stops!");
                        }
                    }

                    if ($line->length > 1) {
                        unset($line);
                        //flight number
                        $value = $xpath->query('td[4]//span[contains(@class, "flight-number")]', $booking);

                        if ($value->length > 0) {
                            $segments[$c]['FlightNumber'] = Html::cleanXMLValue($value->item(0)->nodeValue);
                        }
                        // Aircraft
                        $value = $xpath->query('td[4]//span[contains(@class, "aircraft-operated-by")]', $booking);

                        if ($value->length > 0) {
                            $segments[$c]['Aircraft'] = Html::cleanXMLValue($value->item(0)->nodeValue);
                        }
                        //dep/arr code/name
                        $segments[$c]['DepCode'] = $this->http->FindSingleNode('td[3]/strong[1]', $booking, true, '/\(([A-Z]{3})\)/');
                        $segments[$c]['ArrCode'] = $this->http->FindSingleNode('td[3]/strong[2]', $booking, true, '/\(([A-Z]{3})\)/');
                        $segments[$c]['DepName'] = $this->http->FindSingleNode('td[3]/strong[1]', $booking, true, '/(.*)\([A-Z]{3}\)/');
                        $segments[$c]['ArrName'] = $this->http->FindSingleNode('td[3]/strong[2]', $booking, true, '/(.*)\([A-Z]{3}\)/');
                        // refs #8869 - site bug fix
                        if (Html::cleanXMLValue($segments[$c]['DepName']) == 'Location.' . $segments[$c]['DepCode']) {
                            $this->http->Log("site bug fix: DepName -> {$segments[$c]['DepName']}");
                            $segments[$c]['DepName'] = $segments[$c]['DepCode'];
                        }

                        if (Html::cleanXMLValue($segments[$c]['ArrName']) == 'Location.' . $segments[$c]['ArrCode']) {
                            $this->http->Log("site bug fix: ArrName -> {$segments[$c]['ArrName']}");
                            $segments[$c]['ArrName'] = $segments[$c]['ArrCode'];
                        }
                        // refs #9454
                        if (($this->http->FindPreg("/Headquarters/ims", false, $segments[$c]['ArrName'])
                            || $this->http->FindPreg("/Headquarters/ims", false, $segments[$c]['DepName']))) {
                            $this->ArchiveLogs = true;
                            $this->logger->notice("Notification sent");
                            $this->sendNotification("refs #9454 JetBlue - 'Fictitious Headquarters'");
                        }

                        //dep/arr date
                        $value = $xpath->query('td[1]', $booking);

                        if ($value->length > 0) {
                            $date = trim($value->item(0)->nodeValue);
                            $date = preg_replace('/\S*\s*/ims', '', $date, 1);
                            $value = $xpath->query('td[2]/span', $booking);

                            if ($value->length > 1) {
                                // DepDate
                                $this->logger->debug("DepDate");
                                $segments[$c]['DepDate'] = strtotime(Html::cleanXMLValue($value->item(0)->nodeValue) . " $date");
                                // if date in next year
                                $segments[$c]['DepDate'] = $this->correctDateTime(strtotime("-6 month", time()), $segments[$c]['DepDate']);
                                // ArrDate
                                $this->logger->debug("ArrDate");
                                $segments[$c]['ArrDate'] = strtotime(Html::cleanXMLValue($value->item(1)->nodeValue) . " $date");
                                // if date in next year
                                $segments[$c]['ArrDate'] = $this->correctDateTime(strtotime("- 6 month", time()), $segments[$c]['ArrDate']);
                                // if date in next day
                                $segments[$c]['ArrDate'] = $this->correctDateTime($segments[$c]['DepDate'], $segments[$c]['ArrDate'], "+1 day");
                            }// if ($value->length > 1)
                        }// if ($value->length > 0)
                        $trvl = $xpath->query("td[contains(@class,'passenger-seat')]/table//tr", $traveler);
                        //passengers, seats
                        $this->logger->debug('Seats: ' . $trvl->length);

                        if ($trvl->length > 0) {
                            $segments[$c]['Seats'] = '';

                            for ($j = 0; $j < $trvl->length; $j++) {
                                $trvlman = $trvl->item($j);
                                $value = $this->http->FindSingleNode('td[1]/span[1]', $trvlman);

                                if (isset($value)) {
                                    if (!in_array($value, $passengers)) {
                                        $passengers[] = $value;
                                    }
                                }

                                $value = $this->http->FindSingleNode('td[3]/div/strong', $trvlman);
                                $this->logger->debug('Seats value: ' . $value);

                                if (isset($value)) {
                                    $value = str_ireplace(' *', '', $value);

                                    if ($value != '--') {
                                        $segments[$c]['Seats'] .= ',' . $value;
                                    }
                                }
                            }
                        }

                        if (!empty($segments[$c]['Seats'])) {
                            $segments[$c]['Seats'] = trim($segments[$c]['Seats'], ',');
                        } else {
                            unset($segments[$c]['Seats']);
                        }

                        if (isset($airlines[$i]) && preg_match("/itin_logo_([^\.]+)\./", $airlines[$i], $matches)) {
                            $segments[$c]['AirlineName'] = ArrayVal($airLogos, $matches[1], $matches[1]);
                        }
                    } else {
                        $correct++;
                    }
                }
            }

            //currency,tax,class
            $result['Tax'] = $result['TotalCharge'] = 0;
            $pnums = $this->http->FindNodes("//div[@id='printItineraryBody']/table[@class='viewit-payment table-race']/tbody/tr/td[3]/span/a/@href");

            if (count($pnums) > 0) {
                $airlineNameArrF = [];

                foreach ($pnums as $href) {
                    if (isset($href)) {
                        // load recipe
                        $this->http->GetURL($href);

                        if ($this->http->FindPreg("/you currently do not have a valid eTicket viewable on Virtually There/ims")) {
                            break;
                        }
                        //class
                        $classes = $this->http->FindNodes("//*[contains(text(), 'Class')]/following-sibling::span");

                        if (count($classes) > 0) {
                            $j = 0;

                            foreach ($classes as $class) {
                                if (!isset($segments[$j]['Cabin'])) {
                                    $segments[$j]['Cabin'] = ",";
                                }

                                if (!strstr($segments[$j]['Cabin'], ",$class,")) {
                                    $segments[$j]['Cabin'] .= $class . ',';
                                }
                                $j++;
                            }
                        }
                        // AirlineName
                        $airlineNameArr = $this->http->FindNodes('//table[@class="box"]/tbody/tr/td[@class="idAirline"]/node()[1]');

                        if (count($airlineNameArr) > count($airlineNameArrF)) {
                            $airlineNameArrF = $airlineNameArr;
                        }

                        // Tax
                        $tax = $this->http->FindSingleNode('//div[@class="eticketSection eticketPayment"]/table//tr[th[b[contains(text(), "Taxes")]]]/td/b', null, true, "/(\d+.\d+|\d+)/ims");

                        if (isset($tax)) {
                            $result['Tax'] += floatval($tax);
                        }
                        // TotalCharge
                        $TotalCharge = $this->http->FindSingleNode('//div[@class="eticketSection eticketPayment"]/table//tr[th[b[contains(text(), "Total Fare")]]]/td/b', null, true, "/(\d+.\d+|\d+)/ims");

                        if (isset($TotalCharge)) {
                            $result['TotalCharge'] += floatval($TotalCharge);
                        }
                        // Currency
                        if (isset($result['TotalCharge']) && !isset($result['Currency'])) {
                            $result['Currency'] = $this->http->FindSingleNode('//div[@class="eticketSection eticketPayment"]/table//tr[th[b[contains(text(), "Total Fare")]]]/td/b', null, true, "/([A-Z]{3})/ims");
                        }

                        // nodes, refs #7493
                        $nodes = $this->http->XPath->query("//div[contains(@class, 'eticketItineraryDetails')]//tr[td]");
                        $countSegments = count($segments);
                        $this->logger->debug("Segments {$nodes->length} nodes == {$countSegments} segments");

                        if ($nodes->length == $countSegments) {
                            for ($l = 0; $l < $countSegments; $l++) {
                                if (isset($segments[$l]['ArrDate'], $segments[$l]['DepDate'])
                                    && $segments[$l]['DepDate'] == $segments[$l]['ArrDate']) {
                                    $time = $this->http->FindSingleNode("td[@class = 'idArrival']//div[contains(text(), 'Time')]/following-sibling::div[1]", $nodes->item($l));
                                    $arrDate = date("m/d/Y", $segments[$l]['ArrDate']) . ' ' . $time;
                                    $this->logger->notice("ArrDate: bugfix {$arrDate}");
                                    $segments[$l]['ArrDate'] = strtotime($arrDate);

                                    // if date in next day
                                    $segments[$l]['ArrDate'] = $this->correctDateTime($segments[$l]['DepDate'], $segments[$l]['ArrDate'], "+1 day");
                                    // if date in next year
                                    $segments[$l]['ArrDate'] = $this->correctDateTime(strtotime("-6 month", time()), $segments[$l]['ArrDate']);
                                }// if ($segments[$l]['DepDate'] == $segments[$l]['ArrDate'])
                            }
                        }// for ($l = 0; $l < count($segments); $l++)
                    }// if (isset($href))
                }// foreach ($pnums as $href)

                for ($l = 0; $l < count($segments); $l++) {
                    if (isset($segments[$l]['Cabin'])) {
                        $segments[$l]['Cabin'] = trim($segments[$l]['Cabin'], ',');
                    }
                }
            }// if (count($pnums) > 0) {
        }// if ($bookings->length > 0) {

        // AirlineName
        if (isset($airlineNameArrF[0])) {
            for ($i = 0; $i < $bookings->length; $i++) {
                if (isset($airlineNameArrF[$i])) {
                    $segments[$i]['AirlineName'] = $airlineNameArrF[$i];
                }
            }// for ($i = 0; $i < $bookings->length; $i++)
        }// if (isset($airlineNameArrF[0]))

        if (!empty($passengers)) {
            $result['Passengers'] = implode(', ', $passengers);
        }

        if (count($segments) > 0) {
            $result['TripSegments'] = $segments;
        }
        unset($result['Cancelled']);

        $this->getTime($startTimer);

        return $result;
    }

    public function getLastReservationNumber($number)
    {
        // Partial reservation parsing  // refs #16875
        if (isset($this->State['LastReservation'])) {
            $this->logger->notice("Last parsed reservation: {$this->State['LastReservation']} of {$number}");

            if (($this->State['LastReservation'] + 1) >= $number) {
                $i = -1;
            } else {
                $i = $this->State['LastReservation'];
            }
        }// if (isset($this->State['LastReservation']))
        else {
            $i = -1;
        }

        return $i;
    }

    public function ParseItineraries($oktaToken = null)
    {
        $this->disableSeleniumForRetrieve = true;
        $this->http->setMaxRedirects(20);
        $startTimer = time();

        $headers = [
            'Accept'                    => 'application/json, text/plain, */*',
            'Authorization'             => 'Bearer ' . $oktaToken->accessToken->accessToken ?? $oktaToken,
            'ocp-apim-subscription-key' => '5e6eb3c0e4d74bf4b8894eb7e76015d4',
            'Referer'                   => 'https://www.jetblue.com/',
            'Origin'                    => 'https://www.jetblue.com',
        ];
        $this->http->RetryCount = 0;
        $this->http->GetURL('https://cb-api.jetblue.com/cb-mytrips/myb/trips/upcoming', $headers);
        $this->http->RetryCount = 2;

        $upcoming = $this->http->JsonLog();

        if (!isset($upcoming->trips)) {
            return [];
        }

        $trips = $upcoming->trips ?? [];
        $this->logger->debug("Total " . count($trips) . " itineraries were found");
        $headers = [
            'Accept'             => '*/*',
            'Content-Type'       => 'application/json',
            'Authorization'      => 'Bearer Basic anNvbl91c2VyOmpzb25fcGFzc3dvcmQ=',
            'Referer'            => 'https://managetrips.jetblue.com/dx/B6DX/',
            'Origin'             => 'https://managetrips.jetblue.com',
            'x-sabre-storefront' => 'B6DX',
        ];

        foreach ($trips as $trip) {
            /* $this->http->GetURL('https://managetrips.jetblue.com/dx/B6DX/');
             $data = '{"operationName":"getMYBTripDetails","variables":{"pnrQuery":{"pnr":"' . $trip->pnrRecordLocator . '","lastName":"' . $trip->lastName . '"}},"extensions":{},"query":"query getMYBTripDetails($pnrQuery: JSONObject!) {\n  getMYBTripDetails(pnrQuery: $pnrQuery) {\n    originalResponse\n    __typename\n  }\n  getStorefrontConfig {\n    privacySettings {\n      isEnabled\n      maskFrequentFlyerNumber\n      maskPhoneNumber\n      maskEmailAddress\n      maskDateOfBirth\n      maskTravelDocument\n      __typename\n    }\n    __typename\n  }\n}"}';
             $this->http->RetryCount = 1;
             $this->http->PostURL('https://managetrips.jetblue.com/api/graphql', $data, $headers);
             $this->http->RetryCount = 2;
             $response = $this->http->JsonLog();*/

            $arFields = [
                'ConfNo'   => $trip->pnrRecordLocator,
                'LastName' => $trip->lastName,
            ];
            $error = $this->CheckConfirmationNumberInternal($arFields, $it);

            if ($error) {
                $this->logger->error('Skipping itinerary: ' . $error);
            }
        }
        $this->http->RetryCount = 2;

        return [];
    }

    public function ParseCancelledReservations()
    {
        $this->logger->notice(__METHOD__);
        $this->logger->info('Parse All Cancelled', ['Header' => 3]);

        $result = [];
        $cancelled = $this->http->XPath->query('//span[text() = "This itinerary has been cancelled."]/../..//span[contains(text(), "Confirmation")]');
        $this->logger->debug("Total {$cancelled->length} cancelled itineraries found");

        for ($i = 0; $i < $cancelled->length; $i++) {
            $number = $cancelled->item($i)->nodeValue;
            $number = Html::cleanXMLValue(preg_replace("#([^\#]+\#\s*)#", '', $number));
            $this->logger->info(sprintf('Parse Itinerary #%s [%s]', $number, $this->currentItin++), ['Header' => 4]);
            $itinerary = [
                'Kind'          => 'T',
                'RecordLocator' => $number,
                'Cancelled'     => true,
                'Status'        => 'Cancelled',
            ];
            $this->logger->debug('Parsed Flight:');
            $this->logger->debug(var_export($itinerary, true), ['pre' => true]);
            $result[] = $itinerary;
        }

        return $result;
    }

    public function ConfirmationNumberURL($arFields)
    {
        return "https://www.jetblue.com/manage-trips";
    }

    public function seleniumRetrieve($arFields)
    {
        $this->logger->notice(__METHOD__);
        $selenium = clone $this;
        $result = false;
        $retry = false;
        $this->http->brotherBrowser($selenium->http);

        try {
            $this->logger->notice("Running Selenium...");
            $selenium->UseSelenium();
            $selenium->useGoogleChrome(SeleniumFinderRequest::CHROME_84);
            $selenium->http->start();
            $selenium->http->saveScreenshots = true;
            $selenium->Start();

            $selenium->http->GetURL("https://www.jetblue.com/manage-trips");

            $lastName = $selenium->waitForElement(WebDriverBy::xpath("//input[@formcontrolname='lastName']"), 10);
            $confNo = $selenium->waitForElement(WebDriverBy::xpath("//input[@formcontrolname='confirmationCode']"), 0);
            $btn = $selenium->waitForElement(WebDriverBy::xpath("//button[@type='submit']"), 0);

            if (!$confNo || !$lastName || !$btn) {
                $this->savePageToLogs($selenium);
                $retry = true;

                return null;
            }

            $this->savePageToLogs($selenium);
            $confNo->sendKeys($arFields['ConfNo']);
            $lastName->sendKeys($arFields['LastName']);
            //$btn->click();
            //sleep(10);
            /*$spinner = $selenium->waitForElement(\WebDriverBy::xpath("//div[contains(text(),'Loading...')]"), 15);
            $selenium->driver->executeScript("const constantMock = window.fetch;
            window.fetch = function() {
               return new Promise((resolve, reject) => {
                   constantMock.apply(this, arguments)
                       .then((response) => {
                           if (response.url.indexOf('/api/graphql') > -1) {
                               response
                               .clone()
                               .json()
                               .then(body => localStorage.setItem('responseData', JSON.stringify(body)));
                           }
                           resolve(response);
                       })
                       .catch((error) => {
                           console.log(response);
                       })
               });
            }");

            $responseData = $selenium->driver->executeScript("return localStorage.getItem('responseData');");
            $this->logger->info("[Form responseData]: " . $responseData);*/

            //$selenium->waitForElement(\WebDriverBy::xpath("//h2/span[contains(text(),'Manage your trip')]"), 15);

            // login
            $this->savePageToLogs($selenium);
            $cookies = $selenium->driver->manage()->getCookies();

            foreach ($cookies as $cookie) {
                $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
            }
        } catch (ScriptTimeoutException $e) {
            $this->logger->error("ScriptTimeoutException exception: " . $e->getMessage());
            $this->DebugInfo = "ScriptTimeoutException";
        }// catch (ScriptTimeoutException $e)
        catch (UnknownServerException $e) {
            $this->logger->error("UnknownServerException: " . $e->getMessage());
            $this->logger->debug("Need to change ff version");
        } catch (NoSuchWindowException $e) {
            $this->logger->error("exception: " . $e->getMessage());
        } finally {
            // close Selenium browser
            $selenium->http->cleanup(); // TODO
            $this->http->saveScreenshots = false;
            // retries
            if ($retry && ConfigValue(CONFIG_SITE_STATE) != SITE_STATE_DEBUG) {
                throw new CheckRetryNeededException(3, 0);
            }
        }

        return $result;
    }

    public function CheckConfirmationNumberInternal($arFields, &$it)
    {
        $this->logger->notice(__METHOD__);
        $this->http->setRandomUserAgent();
        $this->http->GetURL('https://www.jetblue.com/manage-trips');
        $headers = [
            'Accept'        => 'application/json, text/plain, */*',
            'apikey'        => '45804e33f26b44d1b144090af2788abf',
            'app-id'        => 'DOTCOM',
            'Content-Type'  => 'application/json',
            'Origin'        => 'https://www.jetblue.com',
        ];
        $date = [
            'b6PNRLocatorRQ' => [
                'isNonB6'          => false,
                'lastName'         => $arFields['LastName'],
                'pnrRecordLocator' => $arFields['ConfNo'],
            ],
        ];
        $this->http->RetryCount = 0;
        $this->http->PostURL("https://azrest.jetblue.com/myb/pnr/b6-record-locator", json_encode($date), $headers);
        $this->http->RetryCount = 2;
        $response = $this->http->JsonLog();

        $this->increaseTimeLimit();

        if (isset($response->error->message)) {
            if ($response->error->message == 'Invalid B6/OA/GDS PNR + Last name was entered; No B6 PNR was found !!!') {
                return 'We could not recognize your itinerary. Please try again or enter your ticket #. ';
            }
        } elseif (!isset($response->b6PNRLocatorRS->b6PNRRecordLocator)) {
            return null;
        }

        if (!$this->disableSeleniumForRetrieve) {
            $this->seleniumRetrieve($arFields);
        }

        $headers = [
            'Accept'             => '*/*',
            'Authorization'      => 'Bearer Basic anNvbl91c2VyOmpzb25fcGFzc3dvcmQ=',
            'x-sabre-storefront' => 'B6DX',
            'Content-Type'       => 'application/json',
            'Origin'             => 'https://managetrips.jetblue.com',
        ];
        $data = '{"operationName":"getMYBTripDetails","variables":{"pnrQuery":{"pnr":"' . $arFields['ConfNo'] . '","lastName":"' . $arFields['LastName'] . '"}},"extensions":{},"query":"query getMYBTripDetails($pnrQuery: JSONObject!) {\n  getMYBTripDetails(pnrQuery: $pnrQuery) {\n    originalResponse\n    __typename\n  }\n  getStorefrontConfig {\n    privacySettings {\n      isEnabled\n      maskFrequentFlyerNumber\n      maskPhoneNumber\n      maskEmailAddress\n      maskDateOfBirth\n      maskTravelDocument\n      __typename\n    }\n    __typename\n  }\n}\n"}';
        $this->http->RetryCount = 1;
        $this->http->PostURL('https://managetrips.jetblue.com/api/graphql', $data, $headers);
        $this->http->RetryCount = 2;
        $response = $this->http->JsonLog();

        if (isset($response->extensions->errors[0]->responseData->message)) {
            return $response->extensions->errors[0]->responseData->message;
        }

        if (!isset($response->data->getMYBTripDetails->originalResponse)) {
            return null;
        }
        $this->parseItineraryNew($response->data->getMYBTripDetails->originalResponse);

        return null;
    }

    public function CheckConfirmationNumberInternal_old($arFields, &$it)
    {
        $this->logger->notice(__METHOD__);
        $confUrl = "https://book.jetblue.com/B6/ReservationRetrieveRemoteExternal.do?bookingReference={$arFields['ConfNo']}&remoteSearchCriteria.travelerLastName={$arFields['LastName']}";
        $this->http->GetURL($confUrl);

        if ($this->http->Response['code'] === 403) {
            sleep(2);
            $this->logger->info('retrieve retry on 403');
            $this->http->GetURL($confUrl);
        }

        if ($iframe = $this->http->FindSingleNode("//iframe[contains(@src, 'reservationView')]/@src")) {
            $vsid = $this->http->FindPreg('/vsid=([\w-]+)/') ?: '';
            $this->http->PostURL("https://book.jetblue.com/B6/ReservationRetrieveRemoteExt.do?vsid=$vsid", ["ajaxAction" => "true"]);

            if ($location = $this->http->FindPreg("/redirect:\s*\"([^\"]+)/")) {
                $this->http->NormalizeURL($location);
                $this->http->GetURL($location);
            }// if ($location = $this->http->FindPreg("/redirect:\s*\"([^\"]+)/"))

            if ($error = $this->http->FindPreg("/errors:\s*\[\s*\{\s*message:\s*\"([^\"]+)/")) {
                return $error;
            }
        } elseif ($msg = $this->http->FindPreg('/(Confirmation code cannot be greater than 6 characters.)/')) {
            return $msg;
        } elseif ($msg = $this->http->FindPreg('/(Confirmation code cannot be less than 6 characters.)/')) {
            return $msg;
        } elseif ($this->http->FindPreg('#https://www.jetblue.com/mx/book\-error\?un_jtt_application_platform=#', false,
            $this->http->currentUrl())
        ) {
            $this->http->GetURL("https://www.jetblue.com/cms?path=%252Fhome%252Fmx%252Fbook-error&language=en");
            $res = $this->http->JsonLog(null, 0);

            if (isset($res->data, $res->data->title) && trim($res->data->title) === 'Technical Difficulties'
                && !empty($msg = $this->http->FindPreg("/(Our website’s booking tool is currently experiencing technical difficulties. Full service to the website should be back shortly, so please check back soon. We’re sorry for the inconvenience\!)/"))
            ) {
                return $msg;
            }

            return null;
        } else {
            if ($this->http->Response['code'] == 403) {
                $this->sendNotification("failed to retrieve itinerary by conf #");
            }

            return null;
        }
        $it = $this->ParseItineraryByConfNo($arFields);

        return null;
    }

    public function GetConfirmationFields()
    {
        return [
            "ConfNo" => [
                "Caption"  => "Confirmation #",
                "Type"     => "string",
                "Size"     => 20,
                "Required" => true,
            ],
            "LastName" => [
                "Caption"  => "Last Name",
                "Type"     => "string",
                "Size"     => 40,
                "Value"    => $this->GetUserField('LastName'),
                "Required" => true,
            ],
        ];
    }

    public function GetHistoryColumns()
    {
        return [
            "Date"         => "PostingDate",
            "Activity"     => "Description",
            "Details"      => "Info",
            "Points"       => "Miles",
            "Bonus points" => "Bonus",
        ];
    }

    public function GetHiddenHistoryColumns()
    {
        return [
            'Bonus points',
        ];
    }

    public function ParseHistory($startDate = null)
    {
        $this->http->FilterHTML = false;
        $result = [];
        $this->logger->debug('[History start date: ' . ((isset($startDate)) ? date('Y/m/d H:i:s', $startDate) : 'all') . ']');
        $startTimer = $this->getTime();

        if (!isset($this->ownerCustomerId)) {
            return $result;
        }

        // AccountID: 4052852
        $this->increaseTimeLimit(120);

        $airports = $this->getAirports();

        $this->http->GetURL('https://trueblue.jetblue.com/b2c/me/portalTransactions?firstResult=0&orderType=DESCENDING&orderField=date&maxResults=1000&customersId=' . $this->ownerCustomerId, $this->headers, 60);

        $startIndex = sizeof($result);
        $result = array_merge($result, $this->ParseHistoryPage($startIndex, $startDate, $airports));

        $this->getTime($startTimer);

        return $result;
    }

    public function getAirports()
    {
        $this->logger->notice(__METHOD__);
        $airports = Cache::getInstance()->get('jetblue_airports');

        if (!$airports) {
            $this->http->GetURL('https://trueblue.jetblue.com/b2c/airportsWithStates', $this->headers);
            $airports = $this->http->JsonLog(null, 0);

            if (!empty($airports) > 0) {
                Cache::getInstance()->set('jetblue_airports', $airports, 3600 * 24);
            }
        }

        return $airports;
    }

    public function ParseHistoryPage($startIndex, $startDate, $airports)
    {
        $result = [];
        $response = $this->http->JsonLog(null, 0);

        if (empty($response) || !is_array($response)) {
            return $result;
        }
        $this->logger->debug("Total " . count($response) . " history items were found");
        $skipped = 0;

        foreach ($response as $transaction) {
            $dateStr = $transaction->date;
            $postDate = strtotime($dateStr, false);

            if (isset($startDate) && $postDate < $startDate) {
                $skipped++;

                continue;
            }

            // provider bug fix: only these data {"cashPoints":false,"date":"2019-04-01T06:00:00Z"}
            if (
                !isset($transaction->points)
                && !isset($transaction->customerId)
                && !isset($transaction->pointsPerBusinessRule)
                && !isset($transaction->pointsPerCustomerAndType)
                && !isset($transaction->partnerName)
                && !isset($transaction->pnrNo)
                && !isset($transaction->rewardNameList)
                && !isset($transaction->comments)
                && !isset($transaction->typeName)
            ) {
                $this->logger->notice("skip broken transaction");

                continue;
            }

            $item = [
                'Date'     => $postDate,
                'Activity' => $this->historyName($transaction, $airports),
                'Details'  => $this->historyDetails($transaction),
            ];

            if (!empty($item['Details']) && $this->http->FindPreg('/Bonus/ims', false, $item['Details'])) {
                $item['Bonus points'] = $this->historyDetails($transaction, true);
            }
            $item['Points'] = $transaction->points;

            $result[] = $item;
        }
        $this->logger->notice("skipped {$skipped} transactions before start date");

        return $result;
    }

    public function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);

        if (
            $this->http->FindSingleNode('//a[contains(@href, "logout")]/@href', null, true, null, 0)
            || ($this->http->FindSingleNode('//*[@class = "greeting"]/following-sibling::*[@class = "name"]') !== null)
            || $this->http->FindSingleNode('//jb-avatar/div/div[text()]')
        ) {
            return true;
        }

        return false;
    }

    private function parseItineraryNew($data)
    {
        $this->logger->notice(__METHOD__);

        if (!isset($data->pnr->reloc)) {
            return;
        }

        $this->logger->info('Parse Itinerary #' . $data->pnr->reloc, ['Header' => 3]);

        $f = $this->itinerariesMaster->add()->flight();

        $f->general()->confirmation($data->pnr->reloc, 'Record Locator');

        foreach ($data->pnr->passengers as $passenger) {
            $f->general()->traveller(beautifulName("{$passenger->passengerDetails->firstName} {$passenger->passengerDetails->lastName}"));

            foreach ($passenger->preferences->frequentFlyer as $frequentFlyer) {
                $f->program()->account("$frequentFlyer->airline-$frequentFlyer->number", false);
            }
        }
        $ticketNumbers = [];

        foreach ($data->pnr->travelPartsAdditionalDetails as $travel) {
            if (isset($travel->passengers)) {
                foreach ($travel->passengers as $passenger) {
                    if (isset($passenger->eticketNumber)) {
                        $ticketNumbers[] = $passenger->eticketNumber;
                    }
                }
            }
        }

        if (!empty($ticketNumbers)) {
            $f->issued()->tickets(array_unique($ticketNumbers), false);
        }

        foreach ($data->pnr->itinerary->itineraryParts as $parts) {
            if (!isset($parts->segments)) {
                $this->logger->notice("segments not found");

                continue;
            }

            foreach ($parts->segments as $seg) {
                if (!isset($seg->departure) && !isset($seg->origin) && !isset($seg->destination) && !isset($seg->arrival)) {
                    $this->logger->error('Skip: empty segment');

                    continue;
                }

                if (in_array($seg->origin, ['HDQ']) || in_array($seg->destination, ['HDQ'])) {
                    $this->logger->notice("Skip segment with HDQ (Headquarter)");

                    continue;
                }

                if ($seg->departure === $seg->arrival) {
                    $this->logger->error('Skip: duplicate date');

                    continue;
                }
                $s = $f->addSegment();
                $s->airline()->name($seg->flight->airlineCode);
                $s->airline()->number($seg->flight->flightNumber);

                $s->departure()->code($seg->origin);
                $s->departure()->date2($seg->departure);
                $s->arrival()->code($seg->destination);
                $s->arrival()->date2($seg->arrival);
                $s->extra()->cabin($seg->cabinClass ?? null, false, true);
                $s->extra()->bookingCode($seg->bookingClass);

                if (isset($seg->equipment)) {
                    $s->extra()->aircraft($seg->equipment);
                }

                if ($seg->duration) {
                    $h = floor($seg->duration / 60);
                    $m = $seg->duration % 60;
                    $s->extra()->duration("$h hr $m mins");
                }

                foreach ($data->pnr->travelPartsAdditionalDetails as $travel) {
                    if ($travel->travelPart->{'@ref'} == $seg->{'@id'} && isset($travel->passengers)) {
                        foreach ($travel->passengers as $passenger) {
                            if (isset($passenger->seat->seatCode)) {
                                $s->extra()->seat($passenger->seat->seatCode, false);
                            }
                        }
                    }
                }
            }
        }

        if (empty($f->getSegments())) {
            $this->logger->error('Skip: Reservation has no segments');
            $this->itinerariesMaster->removeItinerary($f);
        }

        if (count($data->pnr->priceBreakdown->price->alternatives) > 1) {
            $this->sendNotification('check price > 1 // MI');
        }

        if (!empty($data->pnr->priceBreakdown->price->alternatives)) {
            $f->price()->total($data->pnr->priceBreakdown->price->alternatives[0][0]->amount);
            $f->price()->currency($data->pnr->priceBreakdown->price->alternatives[0][0]->currency);
        }

        foreach ($data->pnr->priceBreakdown->subElements as $subElement) {
            if ($subElement->label == 'farePrice' && isset($subElement->price->alternatives[0][0]->currency)) {
                if ($subElement->price->alternatives[0][0]->currency == 'FFCURRENCY') {
                    $f->price()->spentAwards($subElement->price->alternatives[0][0]->amount . ' miles');
                } elseif ($subElement->price->alternatives[0][0]->currency == $f->getPrice()->getCurrencyCode()) {
                    $f->price()->cost($subElement->price->alternatives[0][0]->amount);
                }
            } elseif ($subElement->label == 'taxesPrice' && isset($subElement->price->alternatives[0][0]->amount)) {
                $f->price()->tax($subElement->price->alternatives[0][0]->amount);
            }
        }

        $this->logger->debug('Parsed Itinerary:');
        $this->logger->debug(var_export($f->toArray(), true), ['pre' => true]);
    }

    private function maintenanceMode()
    {
        $this->logger->notice(__METHOD__);
        // AccountID: 2451833
        $jbTrueBlueCookie = $this->http->getCookieByName("jbTrueBlueCookie");
        // maintenance workaround
        $this->SetProperty("Name", beautifulName($this->http->FindPreg("/FirstName=([^\&\=]+)/", false, $jbTrueBlueCookie)));
        $this->SetProperty("Number", $this->http->FindPreg("/MembershipID=([^\&\=]+)/", false, $jbTrueBlueCookie));

        if ($this->SetBalance($this->http->FindPreg("/TrueBluePoints=([^\&\=]+)/", false, $jbTrueBlueCookie))) {
            // refs #5052
            $this->JetBlueTravelBank();
        }
    }

    private function parseItinerariesViaRetrieve()
    {
        $this->logger->notice(__METHOD__);
        $this->logger->info('Parse Via Retrieve', ['Header' => 3]);
        $itinBatchSize = 8;

        $res = [];
        $links = $this->http->FindNodes('//a[contains(@onclick, "retrieveRemoteReservationLink") and not(contains(@class, "button2Disabled"))]/@onclick');

        if (!$links) {
            $links = $this->http->FindNodes('//a[contains(@onclick, "retrieveRemoteReservationLink") and contains(@class, "button2") and normalize-space()="Manage flight" and not(contains(@aria-disabled,"true"))]/@onclick');
        }

        if (!$links) {
            $this->logger->info('No itinerary links found');

            return [];
        }
        $totalBookings = count($links);
        $this->logger->debug("Found $totalBookings itineraries");
        //$lastReservationNumber = $this->getLastReservationNumber($totalBookings);

        $flightInfo = [];
        $flights = $this->http->XPath->query('//div[contains(@class, "flightItemsBlock")]/div[contains(@class, "flightItem")]');
        $checkItineraryList = false;
        $hdq = false;

        foreach ($flights as $flight) {
            $f = ['Kind' => 'T'];
            // RecordLocator
            $f['RecordLocator'] = $this->http->FindSingleNode('.//span[contains(@class, "confirmationNumberValue")]', $flight, false, "/#(.+)/");
            $f['Status'] = $this->http->FindSingleNode('.//span[contains(@class, "flightStatusValue")]', $flight);

            if ($f['Status'] == 'Cancelled') {
                $f['Cancelled'] = true;
            }
            $nodes = $this->xpathQuery('.//table[@class = "flightDetailsTable"]//tr', $flight);
            $this->logger->debug("Total {$nodes->length} segments were found");

            $date = null;

            for ($i = 0; $i < $nodes->length; $i++) {
                $segment = [];
                $seg = $nodes->item($i);

                if (count($this->http->FindNodes('./ancestor::tbody/tr', $seg))) {
                    $checkItineraryList = true;
                }
                // FlightNumber
                $segment['FlightNumber'] = $this->http->FindSingleNode(".//td[@class = 'colFlight']", $seg, true, "/Flight\s+(?:\w{2}\s+)?(\d+)/ims");
                // Aircraft
                $segment['Aircraft'] = $this->http->FindSingleNode(".//div[@class = 'equipType']/text()[last()]", $seg);
                // AirlineName
                $segment['AirlineName'] = 'Jetblue';
                // Operator
                $segment['Operator'] = $this->http->FindSingleNode(".//div[@class = 'operatedByAirline']", $seg);
                // Duration
                $segment['Duration'] = $this->http->FindSingleNode(".//div[@class = 'flightDuration']/b", $seg);
                // DepCode
                $segment['DepCode'] = $this->http->FindSingleNode(".//td[@class = 'colRoute']/div/span[1]/text()[1]", $seg);
                // DepName
                $segment['DepName'] = $this->http->FindSingleNode(".//td[@class = 'colRoute']/div/span[1]/div[@class = 'simpleHintOuter']", $seg);

                if ($this->http->FindPreg('/:HDQ\?/', false, $segment['DepName'])) {
                    $this->logger->error('Skipping segment without proper DepCode / DepName');
                    $hdq = true;

                    continue;
                }
                // DepDate
                if (!$date) {
                    $date = $this->http->FindSingleNode(".//span[@class = 'flightDateValue']", $seg);

                    if (!$date) {
                        $date = $this->http->FindSingleNode("./ancestor::div[contains(@class, 'flightDetails')]/preceding-sibling::div[1]//span[contains(@class, 'flightDateValue')]", $seg);
                    }
                }

                $time1 = $this->http->FindSingleNode(".//td[@class = 'colDepart']/div", $seg, false, "/DEPART\s*(.+)/");

                if ($date && $time1) {
                    $depDate = "{$date} {$time1}";
                    $this->logger->debug("DepDate: $depDate / " . strtotime($depDate) . " ");

                    if ($depDate = strtotime($depDate)) {
                        $segment['DepDate'] = $depDate;
                    }
                }
                // ArrCode
                $segment['ArrCode'] = $this->http->FindSingleNode(".//td[@class = 'colRoute']/div/span[3]/text()[1]", $seg);
                // DepName
                $segment['ArrName'] = $this->http->FindSingleNode(".//td[@class = 'colRoute']/div/span[3]/div[@class = 'simpleHintOuter']", $seg);

                if ($this->http->FindPreg('/:HDQ\?/', false, $segment['ArrName'])) {
                    $this->logger->error('Skipping segment without proper ArrCode / ArrName');
                    $hdq = true;

                    continue;
                }
                // ArrDate
                $colArrive = $this->http->FindSingleNode(".//td[@class = 'colArrive']/div", $seg);
                $time2 = $this->http->FindPreg("/ARRIVE\s*(.+?)(?:$|\()/", false, $colArrive);

                if ($date && $time2) {
                    $arrDate = "{$date} {$time2}";
                    $this->logger->debug("ArrDate: $arrDate / " . strtotime($arrDate) . " ");

                    if ($arrDate = strtotime($arrDate)) {
                        $segment['ArrDate'] = $arrDate;

                        if ($this->http->FindPreg('/Next day/i', false, $colArrive)) {
                            $segment['ArrDate'] = strtotime('+1 day', $segment['ArrDate']);
                        }

                        // Monday, July 11, 2022
                        $date = date('l, F d Y', $segment['ArrDate']);
                    }
                }

                $f['TripSegments'][] = $segment;
            }// for ($i = 0; $i < $nodes->length; $i++)

            if (
                $hdq == true
                && empty($f['TripSegments'])
                && $nodes->length == 1
            ) {
                $this->logger->notice("do not collect this itinerary");

                continue;
            }

            $this->logger->debug('Parsed Flight:');
            $this->logger->debug(var_export($f, true), ['pre' => true]);

            $flightInfo[$f['RecordLocator']] = $f;
        }// foreach ($flights as $flight)

        /*
        $itinFrom = $lastReservationNumber + 1;
        $itinTo = min($lastReservationNumber + $itinBatchSize, $totalBookings - 1);
        $this->logger->info(sprintf(
            'Parse %s - %s Future (%s total)',
            $itinFrom,
            $itinTo,
            $itinTo - $itinFrom + 1
        ), ['Header' => 3]);
        $n = 0;
        */

        foreach ($links as $i => $link) {
            if ($i >= 50) {
                $this->logger->debug("Save {$i} reservations");

                break;
            }
            /*
            if ($i <= $lastReservationNumber) {
                $this->logger->notice("Skipped parsed reservation #{$i}");

                continue;
            }

            if ($n === $itinBatchSize) {
                $this->logger->notice("Break at reservation: {$this->State['LastReservation']} of {$totalBookings}");

                break;
            }
            */
            //$n++;
            $conf = $this->http->FindPreg("/retrieveRemoteReservationLink\('(.+?)','.+?'/", false, $link);
            $lastName = $this->http->FindPreg("/retrieveRemoteReservationLink\('.+?','(.+?)'/", false, $link);
            $arFields = [
                'ConfNo'   => $conf,
                'LastName' => $lastName,
            ];
            $it = [];
            $this->increaseTimeLimit();

            if (!$this->retrieveItineraryWrapper($arFields, $it)) {
                $this->logger->error('retrieveItineraryWrapper failed');

                continue;
            }

            if (
                $this->http->currentUrl() == 'https://book.jetblue.com/B6/DisplayCancellationConfirmationPage.do'
                && isset($flightInfo[$it['RecordLocator']]['Status'])
                && $flightInfo[$it['RecordLocator']]['Status'] == 'Unticketed'
            ) {
                $this->logger->notice("Skip non itinerary");

                continue;
            }

            if (
                empty($it['TripSegments'])
                && !empty($it['RecordLocator'])
                && isset($flightInfo[$it['RecordLocator']])
            ) {
                if ($this->http->FindSingleNode('//p[contains(text(), "There has been a change to your upcoming trip. Please review details below.")]')) {
                    $this->logger->error("Flight Change");
                    $status = array_values(array_unique($this->http->FindNodes("//text()[starts-with(normalize-space(),'Flight ')]/ancestor::a[1]/ancestor::td[1]/following-sibling::td")));

                    if (count($status) === 1 && $status[0] === 'Cancelled') {
                        $it['Status'] = 'Cancelled';
                        $it['Cancelled'] = true;
                    }
                } elseif (
                    $this->http->Response['code'] === 302
                    && $this->http->FindPreg('/ConfirmationForward\.do/', false, $this->http->currentUrl())
                ) {
                    $this->logger->error("Too many redirects");
                }
                $this->logger->notice("grab info from itinerary list");

                foreach ($flightInfo[$it['RecordLocator']] as $kk=>$vv) {
                    if (!isset($it[$kk])) {
                        $it[$kk] = $vv;
                    }
                }

                if (isset($f)) {
                    $this->logger->debug('Parsed Flight:');
                    $this->logger->debug(var_export($f, true), ['pre' => true]);
                }
            }

            if ($it) {
                $res[] = $it;
            }
        }
        $this->logger->debug('Parsed Flight:');
        $this->logger->debug(var_export($res, true), ['pre' => true]);

        if (empty($res)) {
            $this->logger->debug('Add what we have collected in the profile');

            foreach ($flightInfo as $it) {
                $res[] = $it;
            }
        }

        return $res;
    }

    private function retrieveItineraryWrapper($arFields, &$it)
    {
        $this->logger->notice(__METHOD__);
        $startTimer = time();
        $error = $this->CheckConfirmationNumberInternal($arFields, $it);
        // Partial reservation parsing  // refs #16875
        if ($error) {
            $this->logger->error($error);
            $this->logger->info(sprintf('Parse Itinerary #%s failed', ArrayVal($arFields, 'ConfNo')), ['Header' => 4]);
            $this->logger->info('retrieve retry');
            $error = $this->CheckConfirmationNumberInternal($arFields, $it);
        }

        if ($error) {
            $this->logger->error($error);
            $this->logger->info(sprintf('Parse Itinerary #%s failed', ArrayVal($arFields, 'ConfNo')), ['Header' => 4]);
        }
        $this->getTime($startTimer);

        if (empty($it)) {
            return false;
        }

        return $error ? false : true;
    }

    private function xpathQuery($query, $parent = null): DOMNodeList
    {
        $this->logger->notice(__METHOD__);
        $res = $this->http->XPath->query($query, $parent);
        $this->logger->info(sprintf('found %s nodes: %s', $res->length, $query));

        return $res;
    }

    private function ParseItineraryByConfNo($arFields)
    {
        $this->logger->notice(__METHOD__);

        $hdq = false;

        $result = ['Kind' => 'T'];
        // RecordLocator
        $result['RecordLocator'] = $this->http->FindSingleNode("//div[@id = 'idComfirmationNumber']") ?: $arFields['ConfNo'];
        $this->logger->info(sprintf('Parse Itinerary #%s [%s]', $result['RecordLocator'], $this->currentItin++), ['Header' => 4]);

        if ($this->http->FindSingleNode("//p[contains(text(), 'Your trip has been cancelled.')]")) {
            $result['Status'] = 'Cancelled';
            $result['Cancelled'] = true;
            $pax = $this->http->FindNodes("//div[starts-with(@id,'idPassengerName_')]");

            if (!empty($pax)) {
                $result['Passengers'] = $pax;
            }

            $this->logger->debug('Parsed Flight:');
            $this->logger->debug(var_export($result, true), ['pre' => true]);

            return $result;
        }

        // Passengers
        $result['Passengers'] = array_map(function ($item) {
            return beautifulName($item);
        }, $this->http->FindNodes("//div[@class = 'passengerInfoBlock']//td[@class = 'colName']/div"));
        $result['Passengers'] = array_values(array_filter($result['Passengers']));
        // TicketNumbers
        $result['TicketNumbers'] = $this->http->FindNodes('//td[contains(text(), "Ticket number")]/following-sibling::td[1]/span[1]', null, '/^\d+/');
        $result['TicketNumbers'] = array_values(array_unique($result['TicketNumbers']));
        // TotalCharge
        $result['SpentAwards'] = $this->http->FindSingleNode("(//div[@id = 'TOTAL_PRICE_SECTION']//div[@class = 'total'])[last()]//span[contains(@class, 'postfixCurrency') and text() = 'pts']/parent::div[1]");

        // 41,500 PTS + $11.20 USD
        $text = $this->http->FindSingleNode("//span[@id='airItineraryComponentTotal']");
        $total = $this->http->FindPreg('/([\d.,]+)\s*[A-Z]{3}\s*$/', false, $text);

        if ($total) {
            $result['TotalCharge'] = PriceHelper::cost($total);
        }

        // Currency
        $result['Currency'] = $this->currency($this->http->FindSingleNode("(//div[@id = 'TOTAL_PRICE_SECTION']//div[@class = 'total'])[last()]//span[contains(@class, 'postfixCurrency') and text() != 'pts']"));

        // $nodes = $this->http->XPath->query("//div[contains(@class, 'flightLeg')]");
        $nodes = $this->xpathQuery('//h2[@id = "idBlockFlightSummaryTitle" or @id = "idBlockFlightSummaryNewTitle" or @id = "idComponentTitle2"]/ancestor::div[contains(@class, "componentHeader")]/following-sibling::div[1]//a[contains(@onclick, "javascript:showFlightDetailsPopUp")]/ancestor::tr[1]');
        $this->logger->debug("Total {$nodes->length} segments were found");

        if ($nodes->length === 0 && $result['RecordLocator'] === 'UGESGA') { // hardcode
            $this->logger->error('Skipping itinerary with no segments');

            return [];
        }

        if ($nodes->length === 0 && $this->http->FindSingleNode('//h1[text()="Flight Change"]')) {
            $nodes = $this->xpathQuery('//tr[@class="flightSegmentCanceled"][count(./td)=4]');

            if ($nodes->length > 0 && count($this->http->FindNodes('//tr[@class="flightSegmentCanceled"][count(./td)=4]/td[4][normalize-space()="Cancelled"]')) === $nodes->length) {
                $result['Status'] = 'Cancelled';
                $result['Cancelled'] = true;
            } else {
                $this->sendNotification("check changes with cancelled segments // ZM");

                return $result;
            }
        }

        for ($i = 0; $i < $nodes->length; $i++) {
            $segment = [];
            $seg = $nodes->item($i);
            // FlightNumber
            $segment['FlightNumber'] = $this->http->FindSingleNode(".//span[@class = 'flightNum']", $seg, true, "/Flight\s+(?:\w{2}\s+)?(\d+)/ims");
            // AirlineName
            $segment['AirlineName'] = $this->http->FindSingleNode(".//span[@class = 'airline']", $seg);

            if (!$segment['AirlineName']) {
                $segment['AirlineName'] = $this->http->FindSingleNode(".//span[@class = 'flightNum']", $seg, true, '/Flight\s+(\w{2})\s+/');
            }
            $detailsLink = $this->http->FindSingleNode('.//a[contains(@onclick, "javascript:showFlightDetailsPopUp")]/@onclick', $seg);
            $airlineCode = $this->http->FindPreg('/airlineCode=(\w+)/', false, $detailsLink);

            if (!$segment['AirlineName']) {
                $segment['AirlineName'] = $airlineCode;
            }

            // Operator
            $segment['Operator'] = $this->http->FindPreg('/operatingAirlineCode=(\w+)/', false, $detailsLink);
            $this->logger->debug("[Operator]: {$segment['Operator']}");
            $segment['Operator'] = preg_replace("/\s+DBA\s+.+/", "", $segment['Operator']);

            if ($segment['Operator'] == $airlineCode) {
                $this->logger->debug("remove Operator");
                unset($segment['Operator']);
            }

            // DepCode
            if ($code = $this->http->FindSingleNode(".//td[@class = 'colDeparture']//span[@class = 'location-code']", $seg, true, "/\((\w{3})\)/ims")) {
                $segment['DepCode'] = $code;
            }
            // DepName
            $segment['DepName'] = trim($this->http->FindSingleNode(".//td[@class = 'colDeparture']//span[@class = 'orig' or @class = 'transf']", $seg, true, "/^([^\(]+)/ims"));

            if (
                empty($segment['DepCode'])
                && $this->http->FindPreg('/:HDQ\?/', false, $segment['DepName'])
            ) {
                $this->logger->error('Skipping segment without proper DepCode');
                $hdq = true;

                continue;
            }

            // DepDate
            $depDate = $this->http->FindSingleNode(".//td[@class = 'colDeparture']//span[@class = 'date']", $seg);
            $depDate .= ' ' . $this->http->FindSingleNode(".//td[@class = 'colDeparture']//span[@class = 'time']", $seg);
            $this->logger->debug("DepDate: $depDate / " . strtotime($depDate) . " ");

            if ($depDate = strtotime($depDate)) {
                $segment['DepDate'] = $depDate;
            }
            // ArrCode
            if ($code = $this->http->FindSingleNode(".//td[@class = 'colArrival']//span[@class = 'location-code']", $seg, true, "/\((\w{3})\)/ims")) {
                $segment['ArrCode'] = $code;
            }
            // ArrName
            $segment['ArrName'] = trim($this->http->FindSingleNode(".//td[@class = 'colArrival']//span[@class = 'transf' or @class = 'dest']", $seg, true, "/^([^\(]+)/ims"));

            if (empty($segment['ArrCode']) && $this->http->FindPreg('/:HDQ\?/', false, $segment['ArrName'])) {
                $this->logger->error('Skipping segment without proper ArrCode / ArrName');
                $hdq = true;

                continue;
            }
            // ArrDate
            $arrDate = $this->http->FindSingleNode(".//td[@class = 'colArrival']//span[@class = 'date']", $seg);
            $arrDate .= ' ' . $this->http->FindSingleNode(".//td[@class = 'colArrival']//span[@class = 'time']", $seg);
            $this->logger->debug("ArrDate: $arrDate / " . strtotime($arrDate) . " ");

            if ($arrDate = strtotime($arrDate)) {
                $segment['ArrDate'] = $arrDate;
            }
            // Seats
            $seats = $this->http->FindNodes("//span[@class = 'routers' and contains(text(), '{$segment['DepCode']}') and contains(., '{$segment['ArrCode']}')]/following-sibling::text()");
            $seats = array_filter($seats, function ($element) {
                return !empty($element) && trim($element) != '*';
            });
            $seats = array_values(array_unique($seats));
            $segment['Seats'] = implode(', ', $seats);
            // Stops
            $stops = $this->http->FindSingleNode(".//span[@class = 'stops']", $seg);

            if ($stops == 'Nonstop') {
                $stops = 0;
            }
            $segment['Stops'] = $stops;
            // Aircraft
            $segment['Aircraft'] = $this->http->FindSingleNode("./following-sibling::tr[1]//span[@class = 'plane']", $seg);
            // Cabin
            $segment['Cabin'] = $this->http->FindSingleNode(".//span[contains(@class, 'fare')]", $seg, true, '/Fare:\s+(.+)/');

            $result['TripSegments'][] = $segment;
        }// for ($i = 0; $i < $nodes->length; $i++)

        $this->logger->notice('[End Parsed Flight] -> ' . $result['RecordLocator']);
        $this->logger->notice(" ");

        if (
            $hdq == true
            && empty($result['TripSegments'])
            && $nodes->length == 1
        ) {
            $this->logger->notice("do not collect this itinerary");

            return [];
        }

        return $result;
    }

    /**
     * Example chunk.js.
     *
     * @param $trn
     * @param $airports
     *
     * @return string
     */
    private function historyName($trn, $airports)
    {
        $codes = array_column($airports, 'code');

        if ($this->ownerCustomerId != $trn->customerId) {
            return $trn->points > 0 ? $trn->customerFirstName . " " . $trn->customerLastName[0] . ". contributed points to the pool balance" : $trn->customerFirstName . " " . $trn->customerLastName[0] . ". redeemed points from the pool balance";
        }

        switch ($trn->type) {
            case "AI":
            case "AR":
                if (isset($trn->extOriginAirport, $trn->extDestinationAirport)) {
                    $origin = $airports[array_search($trn->extOriginAirport, $codes)];
                    $destination = $airports[array_search($trn->extDestinationAirport, $codes)];
                }

                if (isset($origin, $destination)) {
                    $name = "{$origin->cityName} ({$trn->extOriginAirport}) to {$destination->cityName} ({$trn->extDestinationAirport})";

                    if (isset($trn->extDepartureDate)) {
                        $name .= " departing on " . date('F d, Y', strtotime($trn->extDepartureDate));
                    }

                    return $name;
                }

                break;

            case "ER":
            case "AB":
            case "PP":
            case "PQ":
            case "AC":
            case "AN":
                return !empty($trn->comments) ? $trn->comments : $trn->partnerName;

            case "PF":
            case "PT":
            case "A1":
                return isset($trn->refCustomerFirstName, $trn->refCustomerLastName) ? "Points transferred to "
                    . $trn->refCustomerFirstName . " " . $trn->refCustomerLastName[0] : "Points transferred";

            case "AT":
            case "AY":
            case "A2":
                return isset($trn->refCustomerFirstName, $trn->refCustomerLastName) ? "Points transferred from "
                    . $trn->refCustomerFirstName . " " . $trn->refCustomerLastName[0] : "Points transferred";

            case "A3":
            case "A4":
            case "A7":
            case "A8":
            case "AZ":
                return "Points transfer";

            case "A5":
            case "A6":
                return "Points Pooling transfer";

            case "AO":
            case "PC":
            case "CR":
                return $trn->typeName;

            case "BR":
            case "AU":
            case "AD":
                return isset($trn->rewardNameList) ? join(', ', array_unique($trn->rewardNameList))
                    : "Redemption from " . $trn->partnerName;

            case "RF":
            case "RV":
                if (isset($trn->extOriginAirport, $trn->extDestinationAirport)) {
                    $origin = $airports[array_search($trn->extOriginAirport, $codes)];
                    $destination = $airports[array_search($trn->extDestinationAirport, $codes)];
                }

                if (isset($origin, $destination)) {
                    $name = "Cancellation for {$origin->cityName} {$trn->extOriginAirport}) to {$destination->cityName} {$trn->extDestinationAirport})";

                    return $name;
                } elseif (isset($trn->pnrNo)) {
                    $name = "Cancellation for PNR: {$trn->pnrNo}";

                    if (isset($trn->departureDateFirstOutboundSegment)) {
                        $name .= " departing on " . date('F d, Y', strtotime($trn->departureDateFirstOutboundSegment));
                    }

                    return $name;
                } else {
                    $name = "Cancellation";

                    if (isset($trn->extDepartureDate)) {
                        $name .= " on " . date('F d, Y', strtotime($trn->extDepartureDate));
                    }

                    return $name;
                }

                break;

            case "WC":
                return "TrueBlue certificate";

            case "XE":
                return $trn->extraServiceName ?? null;

            case "XR":
                return "Partial points reversal";

            case "CD":
                return "Points donated to " . $trn->charityName;

            case "XL":
                return "Redemption Rebate";
        }

        return null;
    }

    /**
     * Example chunk.js.
     *
     * @param $trn
     * @param $bonus
     *
     * @return string|float
     */
    private function historyDetails($trn, $bonus = false)
    {
        $rows = [];
        $bonusPoints = 0;

        if (in_array($trn->type, ["BR", "AU", "AR", "AD", "CD", "RF"]) && isset($trn->pointsPerCustomerAndType)) {
            foreach ($trn->pointsPerCustomerAndType as $pointsPerCustomerAndType) {
                if (stristr($pointsPerCustomerAndType->pointType, 'Bonus')) {
                    $bonusPoints += $pointsPerCustomerAndType->points;
                }
                $rows[] = $pointsPerCustomerAndType->pointType . ': ' . $pointsPerCustomerAndType->points;
            }
        } elseif (in_array($trn->type, ["AI", "ER", "AB", "PP", "PQ", "AC", "AN", "XE", "XL"]) && isset($trn->pointsPerBusinessRule)) {
            foreach ($trn->pointsPerBusinessRule as $pointsPerBusinessRule) {
                if (stristr($pointsPerBusinessRule->businessRuleName, 'Bonus')) {
                    $bonusPoints += $pointsPerBusinessRule->points;
                }
                $rows[] = $pointsPerBusinessRule->businessRuleName . ': ' . $pointsPerBusinessRule->points;
            }
        } else {
            return null;
        }

        if ($bonus) {
            $this->logger->debug("[Bonus]: {$bonusPoints} - " . join(' | ', $rows));

            return $bonusPoints;
        }

        return join(' | ', $rows);
    }

    private function parseTilesAndMosaicStatus($tilesTotal): void
    {
        // Status (Mosaic only, first status parsed earlier)
        switch (true) {
            case $tilesTotal > 249:
                $this->SetProperty('Status', 'Mosaic 4');

                break;

            case $tilesTotal > 149:
                $this->SetProperty('Status', 'Mosaic 3');

                break;

            case $tilesTotal > 99:
                $this->SetProperty('Status', 'Mosaic 2');

                break;

            case $tilesTotal > 49:
                $this->SetProperty('Status', 'Mosaic 1');

                break;
        }
        // Tiles
        $tilesSubacc = [
            'Code'        => 'Tiles',
            'DisplayName' => 'Tiles',
            'Balance'     => $tilesTotal,
        ];
        $this->SetProperty('Tiles', $tilesTotal); // for elite levels
        $this->http->GetURL('https://trueblue.jetblue.com/b2c/me/tile-counters', $this->headers);
        $counters = $this->http->JsonLog();
        $error = $counters->error ?? null;

        if (is_null($error)) {
            // Tiles for Travel Spend
            if (is_numeric($counters->travelTiles ?? null)) {
                $tilesSubacc['TravelTiles'] = $counters->travelTiles;
                $this->logger->info('subAccount property set - TravelTiles: ' . $counters->travelTiles);
            }
            // Tiles for Card Spend
            if (is_numeric($counters->cardTiles ?? null)) {
                $tilesSubacc['CardTiles'] = $counters->cardTiles;
                $this->logger->info('subAccount property set - CardTiles: ' . $counters->cardTiles);
            }
            // Travel Spend to next tile
            if (is_numeric($counters->travelSpend ?? null)) {
                $this->http->GetURL('https://trueblue.jetblue.com/b2c/parameters/TRAVEL_SPEND_FOR_1_TILE', $this->headers);

                if (is_numeric($spendForTile = $this->http->JsonLog()->value ?? null)) {
                    $this->logger->info('travel spend for 1 tile: $' . $spendForTile);
                    $this->logger->info('travel spend total: $' . $counters->travelSpend);
                    $tilesSubacc['TravelSpendToNextTile'] = '$' . ($spendForTile - fmod($counters->travelSpend, $spendForTile));
                    $this->logger->info('subAccount property set - TravelSpendToNextTile: ' . $tilesSubacc['TravelSpendToNextTile']);
                }
            }
            // Card Spend to next tile
            if (is_numeric($counters->cardSpend ?? null)) {
                $this->http->GetURL('https://trueblue.jetblue.com/b2c/parameters/CARD_SPEND_FOR_1_TILE', $this->headers);

                if (is_numeric($spendForTile = $this->http->JsonLog()->value ?? null)) {
                    $this->logger->info('card spend for 1 tile: $' . $spendForTile);
                    $this->logger->info('card spend total: $' . $counters->cardSpend);
                    $tilesSubacc['CardSpendToNextTile'] = '$' . ($spendForTile - fmod($counters->cardSpend, $spendForTile));
                    $this->logger->info('subAccount property set - CardSpendToNextTile: ' . $tilesSubacc['CardSpendToNextTile']);
                }
            }
        }
        $this->AddSubAccount($tilesSubacc);
    }
}
