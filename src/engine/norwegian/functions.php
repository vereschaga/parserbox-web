<?php

use AwardWallet\Common\Parsing\Html;
use AwardWallet\Engine\ProxyList;

class TAccountCheckerNorwegian extends TAccountChecker
{
    use PriceTools;
    use SeleniumCheckerHelper;
    use ProxyList;

    /**
     * @var HttpBrowser
     */
    public $browser;

    private $headers = [
        "Content-Type" => "application/json;charset=utf-8",
        "Accept"       => "application/json, text/plain, */*",
    ];

    private $history = [];
    private $selenium = true;

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;

        // reCaptcha on post form workaround
        if ($this->attempt > 0) {
            $this->http->SetProxy($this->proxyDOP());
        }

        if ($this->selenium) {
            $this->UseSelenium();

            $this->useGoogleChrome(SeleniumFinderRequest::CHROME_94);
            $this->seleniumOptions->addHideSeleniumExtension = false;
            $this->seleniumOptions->userAgent = null;

            $this->http->saveScreenshots = true;
        }
    }

    public function IsLoggedIn()
    {
        if ($this->loginSuccessful()) {
            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        try {
            $this->http->removeCookies();
        } catch (NoSuchDriverException $e) {
            $this->logger->error("NoSuchDriverException: " . $e->getMessage(), ['HtmlEncode' => true]);

            throw new CheckRetryNeededException(3, 0);
        }

        if ($this->selenium) {
            try {
                $this->http->GetURL("https://www.norwegian.com/uk/my-travels/#/login");
            } catch (Facebook\WebDriver\Exception\TimeoutException $e) {
                $this->logger->error("TimeOutException: " . $e->getMessage(), ['HtmlEncode' => true]);
                $this->driver->executeScript('window.stop();');
            }

            $this->acceptCookies();

            $loginFormBtn = $this->waitForElement(WebDriverBy::xpath('//button[contains(@id, "nas-button-" ) and contains(text(), "Sign In")]'), 7);

            if (!$loginFormBtn && $this->clickCloudFlareCheckboxByMouse($this)) {
                $loginFormBtn = $this->waitForElement(WebDriverBy::xpath('//input[@id = "nas-element-login-box-0-username" or @name = "username"]'), 7);
                $this->saveResponse();
            }

            if (!$loginFormBtn) {
                try {
                    $this->http->GetURL("https://www.norwegian.com/uk/my-travels/#/login");
                } catch (Facebook\WebDriver\Exception\TimeoutException $e) {
                    $this->logger->error("TimeOutException: " . $e->getMessage(), ['HtmlEncode' => true]);
                    $this->driver->executeScript('window.stop();');
                }

                $loginFormBtn = $this->waitForElement(WebDriverBy::xpath('//button[contains(@id, "nas-button-" ) and contains(text(), "Sign In")]'), 7);
                $this->saveResponse();
            }

            if (!$loginFormBtn) {
                $this->saveResponse();

                if ($this->loginSuccessful()) {
                    return true;
                }

                $loginFormBtn = $this->waitForElement(WebDriverBy::xpath('//button[contains(@id, "nas-button-" ) and contains(text(), "Sign In")]'), 0);

                if (!$loginFormBtn) {
                    if ($this->http->FindSingleNode('//p[contains(text(), "The web server reported a bad gateway error.") or contains(text(), "The origin web server timed out responding to this request.")] | //a[contains(., "Sign in / Find reservatio")]')) {
                        throw new CheckRetryNeededException(3, 0);
                    }

                    $this->DebugInfo = 'loginFormBtn not found';

                    return false;
                }
            }

            $this->logger->debug("open login form");
            $loginFormBtn->click();

            $login = $this->waitForElement(WebDriverBy::xpath('//input[@id = "nas-element-login-box-0-username"] | //form[@id = "gigya-login-form"]//input[@name = "username"]'), 15);

            if (!$login && $this->clickCloudFlareCheckboxByMouse($this)) {
                $login = $this->waitForElement(WebDriverBy::xpath('//input[@id = "nas-element-login-box-0-username"] | //form[@id = "gigya-login-form"]//input[@name = "username"]'), 7);
                $this->saveResponse();
            }

            $this->acceptCookies();

            $pass = $this->waitForElement(WebDriverBy::xpath('//input[@id = "nas-element-login-box-0-password"] | //form[@id = "gigya-login-form"]//input[@name = "password"]'), 0);
            $btn = $this->waitForElement(WebDriverBy::xpath('//*[contains(@class, "nas-continue__action") and contains(., "Sign In")] | //input[@id = "login-sign-in-button"]'), 0);
            $this->saveResponse();

            if (!$login || !$pass || !$btn) {
                if ($this->http->FindSingleNode('//title[contains(text(), "One robot and two humans standing next to each other")] | //a[contains(., "Sign in / Find reservatio")]')) {
                    throw new CheckRetryNeededException(3, 0);
                }

                return false;
            }

            $login->sendKeys($this->AccountFields['Login']);
            $pass->sendKeys($this->AccountFields['Pass']);
            $btn->click();

            return true;
        }

        $this->http->GetURL("https://www.norwegian.com/uk/my-travels/");
        $token = $this->http->FindPreg("/api-request-verification-token=\"([^\"]+)/");

        if (!$this->http->FindSingleNode('//div[@id = "profileHeaderBar"]//a[contains(., "Sign in /")]') || !$token) {
            return $this->checkErrors();
        }
//        $this->http->SetInputValue('username', $this->AccountFields['Login']);
//        $this->http->SetInputValue('password', $this->AccountFields['Pass']);

        $data = [
            "username"    => $this->AccountFields['Login'],
            "password"    => $this->AccountFields['Pass'],
            "persistence" => "true",
        ];
        $this->http->RetryCount = 0;
        $headers = [
            'ApiRequestVerificationToken' => $token,
        ] + $this->headers;
        $this->http->PostURL('https://www.norwegian.com/api/login?channelId=IP&culture=en-GB&marketCode=uk', json_encode($data), $headers);
        $this->http->RetryCount = 2;

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);
        //# Maintenance
        if ($message = $this->http->FindSingleNode('//*[contains(text(), "site is currently down")]', null, false)) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        if (
            // Server Error in '/' Application.
            $this->http->FindPreg("/(Server Error in \'\/\' Application\.)/ims")
            // Service Unavailable
            || $this->http->FindSingleNode('//h2[text()="Service Unavailable"]')
        ) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        // reCaptcha on post form workaround
        if ($this->http->Response['code'] == 403 && $this->http->FindSingleNode('
                //form[@id = "challenge-form"]//textarea[@name = "g-recaptcha-response"]/@id
                | //form[@id = "challenge-form"]//input[@name = "cf_captcha_kind" and @value = "h"]/@value
            ')
        ) {
            throw new CheckRetryNeededException(2, 1);
        }

        return false;
    }

    public function Login()
    {
        $logout = null;

        if ($this->selenium) {
            sleep(3);

            $logout = $this->waitForElement(WebDriverBy::xpath('//a[contains(@class, "__profile-logged-in__logout")] | //span[contains(text(), "Show full profile")]'), 7);
            $this->saveResponse();
        }

        $response = $this->http->JsonLog();
        // Unknown username/password. Please try again.
        $message = $response->messageList[0] ?? null;

        if ($message) {
            $this->logger->error($message);

            if (
                strstr($message, 'Unknown username/password. Please try again.')
                || strstr($message, 'Your current password has been detected as a common or well-known password.')
            ) {
                throw new CheckException($response->messageList[0], ACCOUNT_INVALID_PASSWORD);
            }
            // Your profile has been locked
            if (strstr($response->messageList[0], 'Your profile has been locked')) {
                throw new CheckException($response->messageList[0], ACCOUNT_LOCKOUT);
            }
            // Our service is temporarily unavailable due to a technical error.
            if (strstr($response->messageList[0], 'Our service is temporarily unavailable due to a technical error.')) {
                throw new CheckException($response->messageList[0], ACCOUNT_PROVIDER_ERROR);
            }

            return false;
        }
        // Activate your profile for Norwegian
        if (isset($response->forwardAction) && $response->forwardAction == 'SuccessNotNasProfile') {
            throw new CheckException("Activate your profile for Norwegian", ACCOUNT_PROVIDER_ERROR);
        }
        // Change Your Password
        if (isset($response->forwardAction) && $response->forwardAction == 'ForceChgPwd') {
            $this->throwProfileUpdateMessageException();
        }

        // provider bug fix (AccountID: 3847715, 3000532, 3249387, 3766925)
        if ($this->http->Response['code'] == 400 && $this->http->FindPreg("/^Bad Request$/")) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        if ($this->http->FindSingleNode('//h3[contains(text(), "We have updated our membership Terms and Conditions")]')) {
            $this->throwAcceptTermsMessageException();
        }

        if ($message = $this->http->FindSingleNode('//ul[contains(@class, "list--spaceless")]/li | //div[@id = "login-form-error"]')) {
            $this->logger->error("[Error]: {$message}");

            if (
                strstr($message, 'Unknown username/password. Please try again.')
                || strstr($message, 'Your current password has been detected as a common or well-known password')
                || $message == 'Please reset your password'
            ) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            if (
                strstr($message, 'Your profile has been locked.')
                || $message == 'Account is disabled'
            ) {
                throw new CheckException($message, ACCOUNT_LOCKOUT);
            }

            if (strstr($message, 'Our service is temporarily unavailable due to a technical error.')) {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }

            $this->DebugInfo = $message;

            return false;
        }// if ($message = $this->http->FindSingleNode('//ul[contains(@class, "list--spaceless")]/li'))

        // Access is allowed
        if (
            ($this->http->Response['code'] == 200 || $logout)
            && $this->loginSuccessful()
        ) {
            return true;
        }

        return $this->checkErrors();
    }

    public function parseWithCurl()
    {
        $this->logger->notice(__METHOD__);
        $this->browser = new HttpBrowser("none", new CurlDriver());
        $this->http->brotherBrowser($this->browser);

        $cookies = $this->driver->manage()->getCookies();

        foreach ($cookies as $cookie) {
            $this->browser->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
        }

//        $this->browser->RetryCount = 0;
//        $this->browser->GetURL($this->http->currentUrl());
//        $this->browser->RetryCount = 2;
    }

    public function Parse()
    {
        $profile = null;

        if ($this->selenium) {
            $script = '
                var xhttp = new XMLHttpRequest();
                xhttp.open("GET", "https://www.norwegian.com/api/reward?culture=en&marketCode=uk", false);
                xhttp.setRequestHeader("Accept", "application/json, text/plain, */*");
                xhttp.onreadystatechange = function() {
					console.log(this.responseText);
                    if (this.readyState == 4 && this.status == 200) {
                        localStorage.setItem("profileData", this.responseText);
                    }
                };
                xhttp.send();
                v = localStorage.getItem("profileData");

                return v;
            ';

            try {
                $this->driver->executeScript($script);
            } catch (UnexpectedJavascriptException | Facebook\WebDriver\Exception\UnexpectedJavascriptException $e) {
                $this->logger->error('JavascriptException: ' . $e->getMessage(), ['HtmlEncode' => true]);

                if (str_contains($e->getMessage(), 'Failed to fetch')) {
                    sleep(5);
                    $this->driver->executeScript($script);
                }
            } catch (UnknownServerException | NoSuchDriverException $e) {
                $this->logger->error('Exception: ' . $e->getMessage(), ['HtmlEncode' => true]);

                throw new CheckRetryNeededException(3, 0);
            }

            sleep(2);

            try {
                $profile = $this->driver->executeScript("return localStorage.getItem('profileData');");
            } catch (UnknownServerException $e) {
                $this->logger->error('UnknownServerException: ' . $e->getMessage(), ['HtmlEncode' => true]);

                throw new CheckRetryNeededException(3, 0);
            }
            $this->logger->info("[Form profile]: " . $profile);
        } else {
            $this->http->GetURL("https://www.norwegian.com/api/reward?culture=en-GB", $this->headers);
        }

        $response = $this->http->JsonLog($profile);
        // Balance - CashPoints
        if (isset($response->reward->cashPoints)) {
            $this->SetBalance($response->reward->cashPoints);
        }
        // Reward Number
        if (isset($response->reward->rewardNumber)) {
            $this->SetProperty("Number", $response->reward->rewardNumber);
        }
        // Name
        if (isset($response->profileName)) {
            $this->SetProperty("Name", beautifulName($response->profileName));
        }

        // refs #25083
        if (isset($response->reward->spennBalance)) {
            $this->AddSubAccount([
                'Code'           => 'norwegianSpenn',
                'DisplayName'    => "Spenn",
                'Balance'        => $response->reward->spennBalance,
            ]);
        }

//        $this->http->GetURL("https://www.norwegian.com/api/profile/profile?culture=en-GB", $this->headers);
//        $response = $this->http->JsonLog();
//        // Name
//        if (isset($response->profile->firstName, $response->profile->lastName))
//            $this->SetProperty("Name", beautifulName($response->profile->firstName." ".$response->profile->lastName));

        if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR) {
            // not a member
            if ($this->http->FindPreg("/\"reward\": null,/", false, $profile ?? $this->http->Response['body']) && !empty($this->Properties['Name'])) {
                $this->SetWarning(self::NOT_MEMBER_MSG);
            }
            // provider bug
            elseif ($this->http->FindPreg("/^\{\s*\"reward\": null,\s*\"profileName\": null,\s*\"serviceStatus\": \"(?:ok|unavailable)\"\s*/", false, $profile ?? $this->http->Response['body'])) {
                $this->SetBalanceNA();
            }

            $this->parseWithCurl();
            $this->getExpDateFromSecondSite();
        }// if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR)
        else {
            // Expiration Date  // refs #7037
            $this->logger->info('Expiration Date', ['Header' => 3]);
            $this->parseWithCurl();
            $this->getExpDateFromSecondSite();

            return;

            $headers = [
                "Accept"       => "text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8",
                "Content-Type" => "application/x-www-form-urlencoded",
                "Cookie"       => null,
            ];
            $this->browser->GetURL("https://en.norwegianreward.com/minkonto?confirm=acceptedtermsok", $headers);

            if ($this->browser->ParseForm("norwegian_form")) {
//                $this->http->FormURL = 'https://en.norwegianreward.com/login?ReturnUrl=%2fminkonto%3fconfirm%3dacceptedtermsok&confirm=acceptedtermsok';
                $this->browser->SetInputValue("UserName", $this->AccountFields['Login']);
                $this->browser->SetInputValue("Password", $this->AccountFields['Pass']);
                $this->browser->SetInputValue("button", "Log In");
                $this->browser->unsetInputValue('Text');
                $this->browser->PostForm($headers);

                // Flights
                $this->browser->GetURL("https://en.norwegianreward.com/api/v1/myaccount/getflightsmodel", $headers);
                $response = $this->browser->JsonLog(null, 3, true);
                $this->SetProperty("Flights", ArrayVal($response, 'flightCount'));

                try {
                    $this->browser->GetURL("https://en.norwegianreward.com/api/v1/myaccount/getcashpointstatusmodel", $headers);
                } catch (NoSuchWindowException $e) {
                    $this->logger->error("NoSuchWindowException: " . $e->getMessage(), ['HtmlEncode' => true]);

                    throw new CheckRetryNeededException(2, 0);
                }
                $response = $this->browser->JsonLog(null, 3, true);
                // CashPoints Worth
                $this->SetProperty("BalanceWorth", ArrayVal($response, 'balanceCurrencyFormatted'));
                $expireAmount = ArrayVal($response, 'expireAmount');
                // Expiration Date
                $exp = ArrayVal($expireAmount, 'expireDate');
                $expiringBalance = ArrayVal($expireAmount, 'amount');
                // Expiring Balance
                $this->SetProperty("ExpiringBalance", $expiringBalance);

                if ($expiringBalance > 0 && strtotime($exp)) {
                    $this->SetExpirationDate(strtotime($exp));
                } elseif ($expiringBalance === '0') {
                    $this->ClearExpirationDate();
                }
                /*
                else {
                    // refs #12772
                    $this->http->GetURL("https://en.norwegianreward.com/transactions");
                    $nodes = $this->http->XPath->query('//div[contains(@class, "cp-transaction-list__elements")]');
                    $this->logger->debug("Total {$nodes->length} transactions were found");
                    foreach ($nodes as $node) {
                        $date = $this->http->FindSingleNode('div[1]', $node);
                        $year = $this->http->FindPreg("/\d+\s*[A-Z]{3}\s*(\d{4})/ims", false, $date);
                        $earned = $this->http->FindSingleNode('div[3]', $node);
                        $this->logger->debug("Date: {$date} / Earned: {$earned}");
                        if ($year && $earned > 0) {
                            unset($this->Properties['PointsToExpire']);
                            $this->SetProperty("LastActivity", $date);
                            $this->SetExpirationDate(strtotime("+2 year", strtotime("31 Dec $year")));
                            break;
                        }// if ($earned > 0)
                    }// foreach ($nodes as $node)
                }
                */
                // Family CashPoints
                $this->browser->GetURL("https://en.norwegianreward.com/household");
                $this->SetProperty("FamilyCashPoints", $this->http->FindSingleNode("//p[contains(text(), 'Right now you have:')]/span"));
            } else {
                $this->logger->notice("Expiration date form no found");
            }
        }
    }

    public function GetRedirectParams($targetURL = null)
    {
        $arg = parent::GetRedirectParams($targetURL);
        $arg["CookieURL"] = "https://www.norwegian.com/ssl/customer-services/my-norwegian/my-reward/";

        return $arg;
    }

    public function ParseItineraries()
    {
        $result = [];

        $reservationList = null;

        if ($this->selenium) {
            try {
                $this->http->GetURL("https://www.norwegian.com/uk/my-travels/");
            } catch (UnknownServerException $e) {
                $this->logger->error('UnknownServerException: ' . $e->getMessage(), ['HtmlEncode' => true]);
            } catch (NoSuchDriverException $e) {
                $this->logger->error('NoSuchDriverException: ' . $e->getMessage(), ['HtmlEncode' => true]);

                return [];
            }
            $logout = $this->waitForElement(WebDriverBy::xpath('//a[contains(@class, "__profile-logged-in__logout")] | //span[contains(text(), "Show full profile")]'), 7);
            $this->saveResponse();

            $script = '
                var xhttp = new XMLHttpRequest();
                xhttp.open("GET", "https://www.norwegian.com/api/mynorwegian/myreservation?culture=en-GB&month=0&year=0", false);
                xhttp.setRequestHeader("Accept", "application/json, text/plain, */*");
                xhttp.onreadystatechange = function() {
					console.log(this.responseText);
                    //if (this.readyState == 4 && this.status == 200) {
                        localStorage.setItem("reservationList", this.responseText);
                    //} 
                };
                xhttp.send();
                v = localStorage.getItem("reservationList");

                return v;
            ';

            try {
                $this->driver->executeScript($script);
            } catch (UnexpectedJavascriptException | Facebook\WebDriver\Exception\UnexpectedJavascriptException $e) {
                $this->logger->error('JavascriptException: ' . $e->getMessage(), ['HtmlEncode' => true]);

                if (str_contains($e->getMessage(), 'Failed to fetch')) {
                    sleep(5);
                    $this->driver->executeScript($script);
                }
            } catch (UnknownServerException | NoSuchDriverException $e) {
                $this->logger->error('Exception: ' . $e->getMessage(), ['HtmlEncode' => true]);

                return [];
            }

            sleep(2);

            try {
                $reservationList = $this->driver->executeScript("return localStorage.getItem('reservationList');");
            } catch (UnknownServerException | NoSuchWindowException $e) {
                $this->logger->error('Exception: ' . $e->getMessage(), ['HtmlEncode' => true]);

                return [];
            }
            $this->logger->info("[Form reservationList]: " . $reservationList);
        } else {
            $this->http->GetURL("https://www.norwegian.com/api/mynorwegian/myreservation?culture=en-GB&month=0&year=0", $this->headers);
        }

        $response = $this->http->JsonLog($reservationList);

        $noFuture = false;

        if ($this->http->FindPreg("/\"reservationList\":\s*\[\]/", false, $reservationList)) {
            $noFuture = true;
        }

        if (/*!$this->ParsePastIts && */ $noFuture) {
            return $this->itinerariesMaster->setNoItineraries(true);
        }

        if (!empty($response->reservationList)) {
            $this->logger->debug(count($response->reservationList) . ' reservations found.');

            foreach ($response->reservationList as $pnr) {
                $this->logger->info("Parsing reservation #{$pnr->gdsBookingId}", ['Header' => 3]);
                //$this->http->GetURL("https://www.norwegian.com/api/mynorwegian/reservationDetails/getReservationDetails?culture=en-GB&pnr=".$pnr->gdsBookingId, $this->headers);
                $this->increaseTimeLimit();

                if ($this->selenium) {
                    $script = '
                        var xhttp = new XMLHttpRequest();
                        xhttp.open("GET", "https://www.norwegian.com/resourceipr/api/mynorwegian/reservationDetails?channelId=IP&culture=en-GB&marketCode=uk&pnr=' . $pnr->gdsBookingId . '&pnrLocal=' . $pnr->localBookingId . '", false);
                        xhttp.setRequestHeader("Accept", "application/json, text/plain, */*");
                        xhttp.onreadystatechange = function() {
                            console.log(this.responseText);
                            if (this.readyState == 4 && this.status == 200) {
                                localStorage.setItem("reservation' . $pnr->gdsBookingId . '", this.responseText);
                            }
                        };
                        xhttp.send();
                    ';

                    try {
                        $this->driver->executeScript($script);
                    } catch (UnexpectedJavascriptException | Facebook\WebDriver\Exception\UnexpectedJavascriptException $e) {
                        $this->logger->error('JavascriptException: ' . $e->getMessage(), ['HtmlEncode' => true]);

                        if (str_contains($e->getMessage(), 'Failed to fetch')) {
                            sleep(5);
                            $this->driver->executeScript($script);
                        }
                    } catch (UnknownServerException $e) {
                        $this->logger->error('UnknownServerException: ' . $e->getMessage(), ['HtmlEncode' => true]);

                        throw new CheckRetryNeededException(3, 0);
                    }
                    sleep(4);

                    $reservationData = $this->driver->executeScript("return localStorage.getItem('reservation" . $pnr->gdsBookingId . "');");

                    if (empty($reservationData)) {
                        sleep(5);
                        $reservationData = $this->driver->executeScript("return localStorage.getItem('reservation" . $pnr->gdsBookingId . "');");
                    }
                    $this->logger->info("[Form reservation {$pnr->gdsBookingId}]: " . $reservationData);
                    $reservation = $this->http->JsonLog($reservationData, 3, true);
                } else {
                    $this->http->GetURL("https://www.norwegian.com/resourceipr/api/mynorwegian/reservationDetails?culture=en-GB&pnr=" . $pnr->gdsBookingId, $this->headers);
                    $reservation = $this->http->JsonLog(null, 3, true);

                    if (empty($reservation)) {
                        $this->http->GetURL('https://www.norwegian.com/api/mynorwegian/reservationDetails?culture=en-GB&pnr=' . $pnr->gdsBookingId, $this->headers);
                        $reservation = $this->http->JsonLog(null, 3, true);
                    }
                }

                $f = $this->itinerariesMaster->createFlight();

                if (!isset($reservation['booking'])) {
                    $this->logger->debug("booking not found, see next node");

                    continue;
                }

                $f->general()->confirmation(ArrayVal($reservation['booking'], 'pnr'));
                // Passengers
                $travellerList = ArrayVal($reservation['booking'], 'travellerList', []);

                foreach ($travellerList as $traveller) {
                    $f->general()->traveller(Html::cleanXMLValue(beautifulName($traveller['fullName'])));
                }

                // Segments

                $route = ArrayVal($reservation['booking'], 'routeList', []);

                foreach ($route as $segments) {
                    foreach ($segments['flights'] as $segment) {
                        $s = $f->addSegment();
                        $s->departure()->name($segment['originName']);
                        $s->departure()->code($segment['origin']);
                        $s->departure()->date2(str_replace("T", " ", $segment['localDepartureTime']));
                        $s->arrival()->name($segment['destinationName']);
                        $s->arrival()->code($segment['destination']);
                        $s->arrival()->date2(str_replace("T", " ", $segment['localArrivalTime']));

                        foreach ($segment['flightDetailList'] as $flightDetails) {
                            switch ($flightDetails['header']) {
                                // Duration
                                case 'Flight duration':
                                case 'Reisetid':
                                    $dur = $flightDetails['itemDetailList'][0]['text'];
                                    $dur = preg_replace('/\btimer?\b/', 'hour(s)', $dur);
                                    $dur = preg_replace('/\bog\b/', 'and', $dur);
                                    $dur = preg_replace('/\bminutt(?:er)?\b/', 'minute(s)', $dur);
                                    $s->extra()->duration($dur);

                                    break;
                                // FlightNumber
                                case 'Flight information':
                                case 'Informasjon om flygningen':
                                    $flight = $flightDetails['itemDetailList'][0]['text'];
                                    $s->airline()->number($this->http->FindPreg('/^\w{2}(.+)/', false, $flight));
                                    $s->airline()->name($this->http->FindPreg('/^(\w{2})/', false, $flight));

                                    break;
                                // Seats
                                case 'Seating':
                                case 'Setereservasjon':
                                    $seats = $flightDetails['itemDetailList'][0]['text'];

                                    // '(Not possible)', '(Not reserved)', '(Ikke reservert)', '(Ikke mulig)'
                                    if (!$this->http->FindPreg('/\([\w\s]{5,30}\)/', false, $seats)) {
                                        $s->extra()->seats(preg_split('/,\s*/', $seats));
                                    }

                                    break;
                                // Cabin
                                case 'Måltidreservasjon':
                                    $meal = $flightDetails['itemDetailList'][0]['text'];

                                   if (!in_array($meal, ['Ikke tilgjengelig på denne flyvningen', 'Ikke reservert'])) {
                                       $s->extra()->meal($meal);
                                   }

                                   break;

                               case 'Billettyper':
                                   $s->extra()->cabin($flightDetails['itemDetailList'][0]['text']);

                                   break;
                            }// switch ($flightDetails['header'])
                        }// foreach ($segment['flightDetailList'] as $flightDetails)
                    }// foreach ($segments['flights'] as $segment)
                }// foreach ($route as $segments)
            }// foreach ($response->reservationList as $pnr)
        }// if (isset($response->reservationList))

        // TODO: flights without noName()->noNumber
        /*if ($this->ParsePastIts) {
            $recLocs = [];

            foreach ($result as $it) {
                $recLocs[] = $it['RecordLocator'];
            }
            $pastRes = $this->ParsePastItineraries($recLocs);

            if (empty($pastRes) && $noFuture) {
                return $this->itinerariesMaster->setNoItineraries(true);
            } else {
                $this->sendNotification('check past timeline // MI');
                $result = array_merge($result, $pastRes);
            }
        }*/

        return $result;
    }

    public function ParsePastItineraries($parsedRecLocs = [])
    {
        $this->logger->notice(__METHOD__);
        $result = [];
        $curYear = (int) date("Y");
        $i = 0;
        $this->logger->info("Past reservations", ['Header' => 3]);

        do {
            $parseYear = $curYear - $i;
            $this->http->GetURL("https://www.norwegian.com/api/mynorwegian/myreservation?culture=en-GB&month=0&year=" . $parseYear, $this->headers);
            $response = $this->http->JsonLog();
            $i++;

            if ($this->http->FindPreg("/\"reservationList\": \[\]/")) {
                $this->logger->notice('0 past reservations found. (in ' . $parseYear . ' year)');
            } elseif (isset($response->reservationList)) {
                $this->logger->notice(count($response->reservationList) . ' past reservations found. (in ' . $parseYear . ' year)');

                foreach ($response->reservationList as $booking) {
                    //without details

                    $booking = json_decode(json_encode($booking), true);
                    //$this->logger->debug(var_export($booking, true), ['pre' => true]);
                    $f = $this->itinerariesMaster->createFlight();

                    if (!isset($booking['myFlightDataList'])) {
                        $this->logger->debug("segments not found, see next node");

                        continue;
                    }

                    $f->general()->confirmation(ArrayVal($booking, 'gdsBookingId'));

                    if (in_array(ArrayVal($booking, 'gdsBookingId'), $parsedRecLocs)) {
                        $this->logger->notice("already parsed early");

                        continue;
                    }

                    // Segments
                    $segments = ArrayVal($booking, 'myFlightDataList', []);

                    foreach ($segments as $segment) {
                        $s = $f->addSegment();
                        $s->departure()->name($segment['departureAirportName']);
                        $s->departure()->code($segment['departureAirportCode']);
                        $s->departure()->date2(str_replace("T", " ", $segment['departureDateTime']));
                        $s->arrival()->name($segment['arrivalAirportName']);
                        $s->arrival()->code($segment['arrivalAirportCode']);
                        $s->arrival()->date2(str_replace("T", " ", $segment['arrivalDateTime']));
                        $s->airline()->noName()->noNumber();
                    }// foreach ($segments as $segment)
                }// foreach ($response->reservationList as $booking)
            }// if (isset($response->reservationList))
        } while ($i < 3);

        return $result;
    }

    public function GetHistoryColumns()
    {
        return [
            "Date"             => "PostingDate",
            "Description"      => "Description",
            "Transaction Type" => "Info",
            "CashPoints"       => "Miles",
        ];
    }

    public function ParseHistory($startDate = null)
    {
        if (!empty($this->history)) {
            return $this->history;
        }

        $result = [];
        $this->logger->debug('[History start date: ' . ((isset($startDate)) ? date('Y/m/d H:i:s', $startDate) : 'all') . ']');
        $startTimer = $this->getTime();
        $this->browser->GetURL('https://en.norwegianreward.com/transactions');
        $startIndex = sizeof($result);
        $result = array_merge($result, $this->ParsePageHistory($startIndex, $startDate));

        $this->getTime($startTimer);

        return $result;
    }

    public function ParsePageHistory($startIndex, $startDate)
    {
        $result = [];
        $nodes = $this->http->XPath->query('//div[p[contains(text(), "Description")]]/parent::div/following-sibling::div');
        $this->logger->debug("Total {$nodes->length} history items were found");

        foreach ($nodes as $node) {
            $dateStr = $this->http->FindSingleNode('div[1]', $node);
            $postDate = strtotime($dateStr);

            if (isset($startDate) && $postDate < $startDate) {
                $this->logger->notice("break at date {$dateStr} ($postDate)");

                continue;
            }
            $result[$startIndex]['Date'] = $postDate;
            $result[$startIndex]['Description'] = $this->http->FindSingleNode('div[2]/p[2]', $node);
            $result[$startIndex]['Transaction Type'] = $this->http->FindSingleNode('div[2]/p[1]', $node);
            $result[$startIndex]['CashPoints'] = $this->http->FindSingleNode('div[3]', $node);
            $startIndex++;
        }

        return $result;
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);
        $this->http->FilterHTML = false;

        try {
            $this->http->GetURL("https://www.norwegian.com/uk/my-travels/");
        } catch (UnknownServerException | NoSuchDriverException $e) {
            $this->logger->error('Exception: ' . $e->getMessage(), ['HtmlEncode' => true]);

            throw new CheckRetryNeededException(3, 0);
        }
        $this->http->FilterHTML = true;

        if ($this->selenium) {
            $logout = $this->waitForElement(WebDriverBy::xpath('//a[contains(@class, "__profile-logged-in__logout")] | //span[contains(text(), "Show full profile")]'), 7);
            $this->saveResponse();

            if (!$logout && $this->clickCloudFlareCheckboxByMouse($this)) {
                $logout = $this->waitForElement(WebDriverBy::xpath('//a[contains(@class, "__profile-logged-in__logout")] | //span[contains(text(), "Show full profile")]'), 7);
                $this->saveResponse();
            }
        }

        if (
            $this->http->FindNodes('//div[contains(@class, "reward-number")]')
            || $this->http->FindSingleNode('//span[contains(text(), "Show full profile")]')
        ) {
            return true;
        }

        return false;
    }

    private function selenium()
    {
        $this->logger->notice(__METHOD__);

        $selenium = clone $this;
        $retry = false;
        $result = null;
        $this->http->brotherBrowser($selenium->http);

        try {
            $this->logger->notice("Running Selenium...");
            $selenium->UseSelenium();

            $selenium->useGoogleChrome(SeleniumFinderRequest::CHROME_95);
            $selenium->seleniumOptions->addHideSeleniumExtension = false;
            $selenium->seleniumOptions->userAgent = null;

            $selenium->disableImages();
            $selenium->useCache();
            $selenium->http->start();
            $selenium->Start();
            $selenium->http->saveScreenshots = true;

            try {
                $selenium->http->GetURL("https://en.norwegianreward.com/minkonto?confirm=acceptedtermsok");
            } catch (Facebook\WebDriver\Exception\TimeoutException $e) {
                $this->logger->error("TimeOutException: " . $e->getMessage(), ['HtmlEncode' => true]);
                $selenium->driver->executeScript('window.stop();');
            }

            $login = $selenium->waitForElement(WebDriverBy::xpath('//input[@id = "Username"]'), 7);

            if (!$login && $this->clickCloudFlareCheckboxByMouse($selenium)) {
                $login = $selenium->waitForElement(WebDriverBy::xpath('//input[@id = "Username"]'), 7);
                $this->saveResponse();
            }

            $acceptAllForm = $selenium->waitForElement(WebDriverBy::xpath('//button[@onclick = "$(\'#acceptAllForm\').submit()"]'), 0);

            if ($acceptAllForm) {
                $acceptAllForm->click();
                sleep(1);
                $this->savePageToLogs($selenium);
                $login = $selenium->waitForElement(WebDriverBy::xpath('//input[@id = "Username"]'), 7);
            }

            $pass = $selenium->waitForElement(WebDriverBy::xpath('//input[@id = "Password"]'), 0);
            $btn = $selenium->waitForElement(WebDriverBy::xpath('//button[@id = "button"]'), 0);
            $this->savePageToLogs($selenium);

            if (!$login || !$pass || !$btn) {
                return false;
            }

            $login->sendKeys($this->AccountFields['Login']);
            $pass->sendKeys($this->AccountFields['Pass']);
            $btn->click();

            sleep(3);

            $result = $selenium->waitForElement(WebDriverBy::xpath('//a[@id = "log-out-user"]'), 7);

            if ($result) {
                // Flights
                $selenium->http->GetURL("https://en.norwegianreward.com/api/v1/myaccount/getflightsmodel");
                $this->savePageToLogs($selenium);
                $response = $this->http->JsonLog($this->http->FindSingleNode('//pre[not(@id)]'), 3, true);
                $this->SetProperty("Flights", ArrayVal($response, 'flightCount'));

                try {
                    $selenium->http->GetURL("https://en.norwegianreward.com/api/v1/myaccount/getcashpointstatusmodel");
                } catch (UnknownServerException $e) {
                    $this->logger->error("Exception: " . $e->getMessage(), ['HtmlEncode' => true]);
                    $retry = true;

                    return false;
                }

                $this->savePageToLogs($selenium);
                $response = $this->http->JsonLog($this->http->FindSingleNode('//pre[not(@id)]'), 3, true);
                // CashPoints Worth
                $this->SetProperty("BalanceWorth", ArrayVal($response, 'balanceCurrencyFormatted'));
                $expireAmount = ArrayVal($response, 'expireAmount');
                // Expiration Date
                $exp = ArrayVal($expireAmount, 'expireDate');
                $expiringBalance = ArrayVal($expireAmount, 'amount');
                // Expiring Balance
                $this->SetProperty("ExpiringBalance", $expiringBalance);

                if ($expiringBalance > 0 && strtotime($exp)) {
                    $this->SetExpirationDate(strtotime($exp));
                } elseif ($expiringBalance === '0') {
                    $this->ClearExpirationDate();
                }
                // Family CashPoints
                $selenium->http->GetURL("https://en.norwegianreward.com/household");
                $this->savePageToLogs($selenium);
                $this->SetProperty("FamilyCashPoints", $this->http->FindSingleNode("//p[contains(text(), 'Right now you have:')]/span"));

                if ($this->WantHistory) {
//                    $this->logger->info('Parse History', ['Header' => 2]);
                    $historyResult = [];
                    $this->logger->debug('[History start date: ' . ((isset($this->HistoryStartDate)) ? date('Y/m/d H:i:s', $this->HistoryStartDate) : 'all') . ']');
                    $selenium->http->GetURL('https://en.norwegianreward.com/transactions');
                    $this->savePageToLogs($selenium);
                    $startIndex = sizeof($historyResult);
                    $this->history = array_merge($historyResult, $selenium->ParsePageHistory($startIndex, $this->HistoryStartDate));
//                    $this->logger->info("History:");
//                    $this->logger->info(htmlspecialchars(var_export($this->history, true)), ['pre' => true]);
                }
            }

            $cookies = $selenium->driver->manage()->getCookies();

            foreach ($cookies as $cookie) {
                $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
            }

            $this->savePageToLogs($selenium);
            $this->logger->debug("[Current URL]: {$selenium->http->currentUrl()}");
            $this->browser->GetURL($selenium->http->currentUrl());
        } catch (\Facebook\WebDriver\Exception\WebDriverCurlException | \Facebook\WebDriver\Exception\WebDriverException $e) {
            $this->logger->error("WebDriverCurlException: " . $e->getMessage());
        } finally {
            // close Selenium browser
            $selenium->http->cleanup();
            // retries
            if ($retry && ConfigValue(CONFIG_SITE_STATE) != SITE_STATE_DEBUG) {
                throw new CheckRetryNeededException(3);
            }
        }

        return $result;
    }

    public function acceptCookies()
    {
        $this->logger->notice(__METHOD__);

        if ($accept = $this->waitForElement(WebDriverBy::xpath('//button[@id = "onetrust-accept-btn-handler"]'), 0)) {
            $this->logger->debug("accept cookies");
            $accept->click();

            sleep(1);
            $this->saveResponse();
        }
    }

    private function getExpDateFromSecondSite()
    {
        $this->logger->notice(__METHOD__);
        $this->http->GetURL("https://en.norwegianreward.com/login?ReturnUrl=%2fminkonto");

        $login = $this->waitForElement(WebDriverBy::xpath('//input[@id = "Username"]'), 7);
        $this->saveResponse();

        if (!$login && $this->clickCloudFlareCheckboxByMouse($this)) {
            $login = $this->waitForElement(WebDriverBy::xpath('//input[@id = "Username"]'), 7);
            $this->saveResponse();
        }

        $acceptAllForm = $this->waitForElement(WebDriverBy::xpath('//button[@onclick = "$(\'#acceptAllForm\').submit()"]'), 0);

        if ($acceptAllForm) {
            $acceptAllForm->click();
            sleep(1);
            $this->saveResponse();
            $login = $this->waitForElement(WebDriverBy::xpath('//input[@id = "Username"]'), 7);
        }

        $pass = $this->waitForElement(WebDriverBy::xpath('//input[@id = "Password"]'), 0);
        $btn = $this->waitForElement(WebDriverBy::xpath('//button[@id = "button"]'), 0);
        $this->saveResponse();

        if (!$login || !$pass || !$btn) {
            return false;
        }

        $login->sendKeys($this->AccountFields['Login']);
        $pass->sendKeys($this->AccountFields['Pass']);
        $btn->click();

        sleep(3);

        $result = $this->waitForElement(WebDriverBy::xpath('//a[@id = "log-out-user"]'), 7);

        if ($result) {
            // Flights
            $this->http->GetURL("https://en.norwegianreward.com/api/v1/myaccount/getflightsmodel");
            $this->saveResponse();
            $response = $this->http->JsonLog($this->http->FindSingleNode('//pre[not(@id)]'), 3, true);
            $this->SetProperty("Flights", ArrayVal($response, 'flightCount'));

            try {
                $this->http->GetURL("https://en.norwegianreward.com/api/v1/myaccount/getcashpointstatusmodel");
            } catch (NoSuchWindowException $e) {
                $this->logger->error("NoSuchWindowException: " . $e->getMessage(), ['HtmlEncode' => true]);
            }
            $this->saveResponse();
            $response = $this->http->JsonLog($this->http->FindSingleNode('//pre[not(@id)]'), 3, true);
            // CashPoints Worth
            $this->SetProperty("BalanceWorth", ArrayVal($response, 'balanceCurrencyFormatted'));
            $expireAmount = ArrayVal($response, 'expireAmount');
            // Expiration Date
            $exp = ArrayVal($expireAmount, 'expireDate');
            $expiringBalance = ArrayVal($expireAmount, 'amount');
            // Expiring Balance
            $this->SetProperty("ExpiringBalance", $expiringBalance);

            if ($expiringBalance > 0 && strtotime($exp)) {
                $this->SetExpirationDate(strtotime($exp));
            } elseif ($expiringBalance === '0') {
                $this->ClearExpirationDate();
            }
            // Family CashPoints
            $this->http->GetURL("https://en.norwegianreward.com/household");
            $this->saveResponse();
            $this->SetProperty("FamilyCashPoints", $this->http->FindSingleNode("//p[contains(text(), 'Right now you have:')]/span"));

            if ($this->WantHistory) {
//                    $this->logger->info('Parse History', ['Header' => 2]);
                $historyResult = [];
                $this->logger->debug('[History start date: ' . ((isset($this->HistoryStartDate)) ? date('Y/m/d H:i:s', $this->HistoryStartDate) : 'all') . ']');
                $this->http->GetURL('https://en.norwegianreward.com/transactions');
                $this->saveResponse();
                $startIndex = sizeof($historyResult);
                $this->history = array_merge($historyResult, $this->ParsePageHistory($startIndex, $this->HistoryStartDate));
//                    $this->logger->info("History:");
//                    $this->logger->info(htmlspecialchars(var_export($this->history, true)), ['pre' => true]);
            }
        }

        try {
            $cookies = $this->driver->manage()->getCookies();
        } catch (NoSuchDriverException $e) {
            $this->logger->error('NoSuchDriverException: ' . $e->getMessage(), ['HtmlEncode' => true]);

            throw new CheckRetryNeededException(3, 0);
        }

        foreach ($cookies as $cookie) {
            $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
        }

        $this->saveResponse();
        $this->logger->debug("[Current URL]: {$this->http->currentUrl()}");
        $this->browser->GetURL($this->http->currentUrl());

        return $result;
    }
}
