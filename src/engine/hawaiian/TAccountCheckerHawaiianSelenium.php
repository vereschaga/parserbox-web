<?php

use AwardWallet\Common\Selenium\FingerprintFactory;
use AwardWallet\Common\Selenium\FingerprintRequest;
use AwardWallet\Engine\ProxyList;
use AwardWallet\Engine\Settings;

class TAccountCheckerHawaiianSelenium extends TAccountChecker
{
    use ProxyList;
    use SeleniumCheckerHelper;

    private const XPATH_LOGOUT = '//span[@class = "nav-account-number"]';
    /**
     * @var HttpBrowser
     */
    public $browser;
    protected $headers;
    private $responseData = null;

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->UseSelenium();

        //$random = rand(0, 2);

        // by agreement, we should go directly from our servers
        //$this->setProxyNetNut();
        switch ($this->attempt) {
            case 0:
                $this->http->SetProxy($this->proxyReCaptchaVultr());

                break;

            case 1:
                //$this->setProxyMount();
                $this->http->SetProxy($this->proxyDOP(Settings::DATACENTERS_NORTH_AMERICA), false);

                break;

            default:
                $this->setProxyGoProxies(null,'es');

                break;
        }

        if ($this->attempt == 1) {
            $this->useFirefox();

            $request = FingerprintRequest::firefox();
            $request->browserVersionMin = HttpBrowser::BROWSER_VERSION_MIN;
            $fingerprint = $this->services->get(FingerprintFactory::class)->getOne([$request]);

            if ($fingerprint !== null) {
                $this->seleniumOptions->fingerprint = $fingerprint->getFingerprint();
                $this->seleniumOptions->setResolution([$fingerprint->getScreenWidth(), $fingerprint->getScreenHeight()]);
                $this->http->setUserAgent($fingerprint->getUseragent());
            }
        } elseif (false) {// TODO: not working now
//        } elseif ($random == 1 || $this->attempt === 1) {
            /*
            $this->useGoogleChrome();

            $this->http->setUserAgent(null);
            */
            $this->useChromePuppeteer();
            //$selenium->seleniumOptions->fingerprintOptions = ['devices' => ['desktop'], 'operatingSystems' => ['linux']];
            $this->seleniumOptions->addHideSeleniumExtension = false;
            $this->seleniumOptions->userAgent = null;
        } else {
            $this->useChromePuppeteer();

            $request = FingerprintRequest::chrome();
            $request->browserVersionMin = HttpBrowser::BROWSER_VERSION_MIN;
            $fingerprint = $this->services->get(FingerprintFactory::class)->getOne([$request]);

            if ($fingerprint !== null) {
                $this->seleniumOptions->fingerprint = $fingerprint->getFingerprint();
                $this->seleniumOptions->setResolution([$fingerprint->getScreenWidth(), $fingerprint->getScreenHeight()]);
                $this->http->setUserAgent($fingerprint->getUseragent());
            }
        }

        $this->http->saveScreenshots = true;
        $this->seleniumOptions->recordRequests = true;
    }

    public function IsLoggedIn()
    {
        try {
            $this->http->GetURL('https://www.hawaiianairlines.com/my-account/login');
        } catch (UnexpectedJavascriptException | Facebook\WebDriver\Exception\UnknownErrorException $e) {
            $this->logger->error("Exception: " . $e->getMessage(), ['HtmlEncode' => true]);
        }
        // look for logout link
        if ($this->waitForElement(WebDriverBy::xpath(self::XPATH_LOGOUT), 3)) {
            $this->saveResponse();

            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        try {
            $this->http->removeCookies();
        } catch (NoSuchDriverException $e) {
            $this->logger->error("Exception: " . $e->getMessage(), ['HtmlEncode' => true]);

            throw new CheckRetryNeededException(3, 5);
        }
        $this->DebugInfo = '';

        /*
        if ($this->attempt == 0) {
            $this->http->GetURL('https://ipinfo.io/json', [], 30);
            $response = $this->http->JsonLog($this->http->FindSingleNode("//pre[not(@id)] | //div[@id = 'json']"), 3, true);
            $this->DebugInfo = ArrayVal($response, 'ip');
        }
        */

        try {
            $this->driver->manage()->window()->maximize();
            $this->http->GetURL('https://www.hawaiianairlines.com/my-account/login');
        } catch (TimeOutException | Facebook\WebDriver\Exception\WebDriverException $e) {
            $this->logger->error("Exception: " . $e->getMessage(), ['HtmlEncode' => true]);
            $this->DebugInfo = "Exception";

            throw new CheckRetryNeededException(3, 5);
        }

        if ($error = $this->http->FindPreg("/(?:Secure Connection Failed|Unable to connect|The proxy server is refusing connections|Failed to connect parent proxy|The connection has timed out|You don't have permission to access|Your request to access this website has been temporarily blocked for security reasons\.|This site can’t be reached|There is no Internet connection)/")) {
            $this->logger->error($error);
            $this->DebugInfo .= ': ' . $error;
            $this->markProxyAsInvalid();
            $this->ErrorReason = self::ERROR_REASON_BLOCK;

            throw new CheckRetryNeededException(3);

            return false;
        }

        if (empty($this->http->Response['body'])) {
            throw new CheckRetryNeededException(4, 5);
        }

        $this->waitForElement(WebDriverBy::xpath("
            //input[@name = 'UserName']
            | //div[contains(text(), 'Access to this website has been temporarily blocked. Please try again after 10 minutes.')]
        "), 10);
        $this->saveResponse();
        $loginInput = $this->waitForElement(WebDriverBy::xpath("//input[@name = 'UserName']"), 0);

        // set csrf
        $this->headers = [
            "csrf"             => $this->http->FindPreg("/var tokens = '([^\']+)/"),
            "X-Requested-With" => "XMLHttpRequest",
            "Content-Type"     => "application/x-www-form-urlencoded; charset=UTF-8",
        ];

        if (empty($loginInput)) {
            return $this->checkErrors();
        }
        $login = preg_replace('/\s{1,}/ims', '', $this->AccountFields['Login']);
        $loginInput->sendKeys($login);
        $this->driver->findElement(WebDriverBy::xpath("//input[@name = 'Password']"))->sendKeys($this->AccountFields['Pass']);
        $this->saveResponse();

        return true;
    }

    public function Login()
    {
        $this->logger->notice(__METHOD__);
        $loginButton = $this->waitForElement(WebDriverBy::xpath("//form[@name = 'login']//button"));

        if (!$loginButton) {
            $this->logger->error('Failed to find login button');

            return false;
        }
        /*
        $this->driver->executeScript("$('form[name = \"login\"] button').click()");
        */
        $this->driver->executeScript("document.querySelector('form[name = \"login\"] button').click();");
        $loginButton->click();

        sleep(4);
        $sleep = 30;
        $startTime = time();

        while ((time() - $startTime) < $sleep) {
            try {
                $this->logger->debug("(time() - \$startTime) = " . (time() - $startTime) . " < {$sleep}");
                $this->logger->debug("[Current URL]: {$this->http->currentUrl()}");
                // look for logout link
                $logout = $this->waitForElement(WebDriverBy::xpath(self::XPATH_LOGOUT), 0, true);
                $this->saveResponse();
            } catch (TimeOutException $e) {
                $this->logger->error("TimeOutException: " . $e->getMessage(), ['HtmlEncode' => true]);
                $this->DebugInfo = "TimeOutException";

                throw new CheckRetryNeededException(3);
            }

            if ($logout) {
//            $this->parseWithCurl();
                return true;
            }
            /*
             * Please take a moment to make sure we have your latest information
             * and create a Username to access your new HawaiianMiles dashboard!
             */
            if ($this->waitForElement(WebDriverBy::xpath("//*[contains(text(), 'Please take a moment to make sure we have your latest information and create a Username to access your new HawaiianMiles dashboard!') or contains(text(), 'Update your profile to access your new HawaiianMiles dashboard!') or contains(text(), 'Update your profile to access your HawaiianMiles dashboard!')]"), 0)) {
                $this->throwProfileUpdateMessageException();
            }
            // RESET YOUR PASSWORD
            if (strstr($this->http->currentUrl(), '/my-account/login/change-password')) {
                $this->throwProfileUpdateMessageException();
            }
            $this->saveResponse();
            // invalid credentials
            if ($message = $this->waitForElement(WebDriverBy::xpath("//div[contains(@class, 'alert-content-primary')]"), 0)) {
                $this->logger->debug("try to find error");

                if (!$message) {
                    $this->saveResponse();

                    return false;
                }
                $translateServiceError = $message->getText();

                if (strstr($translateServiceError, 'Your account is locked')) {
                    throw new CheckException($translateServiceError, ACCOUNT_LOCKOUT);
                }
                // Update your profile to access your HawaiianMiles dashboard!
                if (
                    strstr($translateServiceError, 'Update your profile to access your HawaiianMiles dashboard!')
                    || strstr($translateServiceError, 'Update your Security Questions to access your HawaiianMiles dashboard!')
                ) {
                    $this->throwProfileUpdateMessageException();
                }

                if (strstr($translateServiceError, 'Username and password do not match')
                    // Please enter a valid username
                    || strstr($translateServiceError, 'Please enter a valid username')
                    // Incorrect Email Address or Password
                    || strstr($translateServiceError, 'Incorrect Email Address or Password')
                    // Invalid username or password.
                    || strstr($translateServiceError, 'Invalid username or password.')
                    // Email and password could not be found. Please try again. [WEB:HM121]
                    || strstr($translateServiceError, 'Email and password could not be found.')
                    // We cannot find an account with that Username or HawaiianMiles Number. Please try again. [WEB:HM104]
                    || strstr($translateServiceError, 'We cannot find an account with that Username or HawaiianMiles Number')
                    // HawaiianMiles number does not match our records [WEB:HM109]
                    || strstr($translateServiceError, 'HawaiianMiles number does not match our records')
                    // You have 1 attempt left to successfully login to your account.
                    || strstr($translateServiceError, 'You have 1 attempt left to successfully login to your account.')
                    /*
                     * Sorry, your login attempt was unsuccessful. If you entered an email address shared by multiple accounts,
                     * please check your email for instructions. Otherwise, try again or select "Forgot your password?". [WEB:HM109]
                     */
                    || strstr($translateServiceError, 'Sorry, your login attempt was unsuccessful. If you entered an email address shared by multiple accounts')) {
                    throw new CheckException(strip_tags($translateServiceError), ACCOUNT_INVALID_PASSWORD);
                }
                // Your account is locked.
                if (strstr($translateServiceError, 'Your account is locked.')
                    // Your account is permanently locked.
                    || strstr($translateServiceError, 'Your account is permanently locked.')) {
                    throw new CheckException(strip_tags($translateServiceError), ACCOUNT_LOCKOUT);
                }
                // 45319
                if ($translateServiceError == '45319') {
                    throw new CheckException("Invalid credentials", ACCOUNT_INVALID_PASSWORD);
                }
                // Multiple accounts found.Please try login using username / Hawaiian miles number.
                if (strstr($translateServiceError, 'Multiple accounts found.')) {
                    throw new CheckException($translateServiceError, ACCOUNT_PROVIDER_ERROR);
                }
                // Sorry! An error has occurred. Please try again.
                if (strstr($translateServiceError, 'Sorry! An error has occurred. Please try again.')) {
                    throw new CheckException($translateServiceError, ACCOUNT_PROVIDER_ERROR);
                }
            }
            $this->logger->debug("try to find Member #");

            if ($this->waitForElement(WebDriverBy::xpath("//label[contains(text(), 'Member Number')]/following-sibling::span"), 0)) {
                return true;
            }

            $this->logger->debug("try to find other errors");
            // Access Denied
            if ($message = $this->waitForElement(WebDriverBy::xpath("//div[@id = 'LoginError']"), 0)) {
                $translateServiceError = $message->getText();
                $this->logger->error($translateServiceError);
                $translateServiceError = preg_replace('/^\s*error\s*/', '', $translateServiceError);
                $this->logger->error($translateServiceError);
                /**
                 * Access Denied.
                 *
                 * You don't have permission to access "http://www.hawaiianairlines.com/MyAccount/Login/Login" on this server.
                 * Reference #18.26b72d17.1533190632.1188969
                 */
                if (strstr($translateServiceError, 'You don\'t have permission to access "http:')) {
                    $this->logger->error($translateServiceError);
                    $this->DebugInfo .= ': Access Denied';
                    $this->ErrorReason = self::ERROR_REASON_BLOCK;

                    throw new CheckRetryNeededException(2, 5);

                    break;
                }
                // 502 Bad Gateway nginx
                if (strstr($translateServiceError, '502 Bad Gateway nginx')) {
                    $this->logger->error($translateServiceError);
                    $this->DebugInfo .= ': 502 Bad Gateway nginx';

                    throw new CheckRetryNeededException(3, 5, self::PROVIDER_ERROR_MSG);

                    break;
                }

                // Your account is locked. Please reset your password to unlock. [WEB:HM106]
                if (strstr($translateServiceError, 'Your account is locked. Please reset your password to unlock.')) {
                    throw new CheckException(strip_tags($translateServiceError), ACCOUNT_LOCKOUT);
                }
                /**
                 * Sorry, your login attempt was unsuccessful.
                 * If you entered an email address shared by multiple accounts, please check your email for instructions.
                 * Otherwise, try again or select "Forgot your password?". [WEB:HM109].
                 */
                if (strstr($translateServiceError, 'Sorry, your login attempt was unsuccessful. If you entered an email address shared by multiple accounts, please check your email for instructions. ')) {
                    throw new CheckException($translateServiceError, ACCOUNT_INVALID_PASSWORD);
                }

                if (strstr($translateServiceError, 'We apologize. Login is under maintenance. ')) {
                    throw new CheckException($translateServiceError, ACCOUNT_PROVIDER_ERROR);
                }
            }
            // Please enter a valid email, HawaiianMiles number or username
            if ($message = $this->waitForElement(WebDriverBy::xpath("//em[contains(text(), 'Please enter a valid email, HawaiianMiles number or username')]"), 0)) {
                throw new CheckException($message->getText(), ACCOUNT_INVALID_PASSWORD);
            }
            // Only English characters are allowed in password
            if ($message = $this->waitForElement(WebDriverBy::xpath("//em[contains(text(), 'Only English characters are allowed here')]"), 0)) {
                throw new CheckException("Only latin characters are allowed in password field", ACCOUNT_INVALID_PASSWORD);
            }/*review*/
            // Sorry! An error has occurred. Please try again. [WEB:MA115]
            if ($message = $this->waitForElement(WebDriverBy::xpath("//div[contains(text(), 'Sorry! An error has occurred. Please try again.')]"), 0)) {
                throw new CheckException($message->getText(), ACCOUNT_PROVIDER_ERROR);
            }

            $this->saveResponse();
        }// while ((time() - $startTime) < {$sleep})

        $logout = $this->waitForElement(WebDriverBy::xpath(self::XPATH_LOGOUT), 0, true);
        $this->saveResponse();

        if ($logout) {
            return true;
        }

        // auth stuck
        if ($this->waitForElement(WebDriverBy::xpath('//form[@name = "login"]//button[span[@class="button-spinner" and @style="opacity: 1;"]]'))) {
            throw new CheckRetryNeededException(3);
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        $this->logger->debug("[Current URL]: {$this->http->currentUrl()}");

        if (strstr($this->http->currentUrl(), 'book/flights')) {
            $this->http->GetURL('https://www.hawaiianairlines.com/my-account/login');
            $this->waitForElement(WebDriverBy::xpath(self::XPATH_LOGOUT), 3);
            $this->logger->debug("[Current URL]: {$this->http->currentUrl()}");
        }

        $this->saveResponse();
        // Balance - Current Balance
        $this->SetBalance($this->http->FindSingleNode('//span[@id = "current-balance"]/@end-val'));
        // Name
        $this->SetProperty("Name", beautifulName($this->http->FindSingleNode("//h2[@class = 'hamiles-logo-header']/following-sibling::p[1]")));
        // Member Number
        $this->SetProperty("AccountNumber", $this->http->FindSingleNode("//label[contains(text(), 'Member Number')]/following-sibling::span"));
        // Status
        $this->SetProperty("Status", $this->http->FindSingleNode("//h2[@class = 'hamiles-logo-header']/following-sibling::h3"));
        /*
        // Last Activity
        $lastActivity = $this->http->FindSingleNode("(//table[contains(@class, 'data-table')]//tr[td]/th[1])[1]");
        if (isset($lastActivity)) {
            $this->SetProperty("LastActivity", $lastActivity);
            $exp = strtotime($lastActivity);
            if ($exp !== false) {
                $exp = strtotime('+18 months', $exp);

                // refs #19209
                if ($exp < strtotime("December 31, 2020")) {
                    $this->logger->notice("exp date by rules: {$exp}, correcting exp date to January 1, 2021");
                    $exp = strtotime("January 1, 2021");
                }

                $this->SetExpirationDate($exp);
            }
        }// if (isset($lastActivity))
        */

        try {
            $this->increaseTimeLimit();
            $this->http->GetURL("https://www.hawaiianairlines.com/my-account/hawaiianmiles/mileage-statement");
        } catch (UnknownServerException $e) {
            $this->logger->error("Exception: " . $e->getMessage(), ['HtmlEncode' => true]);
        } catch (TimeOutException | Facebook\WebDriver\Exception\TimeoutException | Facebook\WebDriver\Exception\ScriptTimeoutException | ScriptTimeoutException $e) {
            $this->logger->error("Exception: " . $e->getMessage(), ['HtmlEncode' => true]);

            try {
                $this->driver->executeScript('window.stop();');
            } catch (NoSuchDriverException $e) {
                $this->logger->error("Exception: " . $e->getMessage(), ['HtmlEncode' => true]);

                throw new CheckRetryNeededException(3, 1);
            }
        } catch (NoSuchWindowException | NoSuchDriverException | Facebook\WebDriver\Exception\WebDriverException $e) {
            $this->logger->error("Exception: " . $e->getMessage(), ['HtmlEncode' => true]);

            throw new CheckRetryNeededException(3, 1);
        }

        $this->waitForElement(WebDriverBy::xpath("//p[contains(., 'Member since')]"), 10, true);
        $this->saveResponse();
        // Member Since
        $this->SetProperty("MemberSince", $this->http->FindPreg('/"MemberSince":"([^\"]+)/'));
        // Prior Balance
        $this->SetProperty("PriorBalance", $this->http->FindPreg('/"PriorBalance":"([^\"]+)/'));
        // Miles Credited this Month
        $this->SetProperty("CreditedthisMonth", $this->http->FindPreg('/"MilesCredited":"([^\"]+)/'));
        // Miles Redeemed this Month
        $this->SetProperty("RedeemedthisMonth", $this->http->FindPreg('/"MilesRedeemed":"([^\"]+)/'));
        // Qualifying Flight Miles
        $this->SetProperty("QualifyingFlightMiles", $this->http->FindPreg('/"QualifyingMiles":"([^\"]+)/'));
        // Qualifying Flight Segments
        $this->SetProperty("QualifyingFlightSegments", $this->http->FindPreg('/"QualifyingSegments":"([^\"]+)/'));
        // Member Discounts
        // $this->parseDiscounts();
    }

    public function ParseItineraries(): array
    {
        $result = [];

        /*$userToken = $this->http->JsonLog($this->http->getCookieByName('userToken', '.hawaiianairlines.com'));
        if (isset($userToken->AccessToken)) {
            $headers = [
                'Accept' => 'application/json, text/plain, * / *',
                'Content-Type' => 'application/json',
                'Origin' => 'https://mytrips.hawaiianairlines.com',
            ];
            $this->http->GetURL("https://public.itservices.hawaiianairlines.com/exp-web-trips/v1/api/trips?hawaiianMilesNumber={$this->Properties['AccountNumber']}&accessToken={$userToken->AccessToken}&include=flights,passengers&confirmation=1", $headers);
        }*/

        try {
            try {
                //$this->http->GetURL("https://mytrips.hawaiianairlines.com/");
                $this->http->GetURL("https://www.hawaiianairlines.com/my-account/my-trips");
            } catch (Facebook\WebDriver\Exception\JavascriptErrorException $e) {
                $this->logger->error("JavascriptErrorException: " . $e->getMessage(), ['HtmlEncode' => true]);
            }
            sleep(3);

            if (!$this->waitForElement(WebDriverBy::xpath("//div[contains(@class,'first-name')]"), 7)) {
                //$this->http->GetURL("https://www.hawaiianairlines.com/my-account/my-trips");
            }
            $this->saveResponse();
        } catch (NoSuchWindowException | NoSuchDriverException $e) {
            $this->logger->error("Exception: " . $e->getMessage(), ['HtmlEncode' => true]);
        } catch (ScriptTimeoutException | UnknownServerException $e) {
            $this->logger->error("Exception: " . $e->getMessage(), ['HtmlEncode' => true]);

            throw new CheckRetryNeededException(3, 0);
        }
        $this->saveResponse();

        // Retrieving your trips...
        $this->waitFor(function () {
            return is_null($this->waitForElement(WebDriverBy::xpath("//div[contains(text(),'Retrieving your trips...')]"), 1));
        }, 10);
        sleep(7);

        $requests = $this->http->driver->browserCommunicator->getRecordedRequests();

        foreach ($requests as $n => $xhr) {
            $this->logger->debug("xhr request {$n}: {$xhr->request->getVerb()} {$xhr->request->getUri()} " . json_encode($xhr->request->getHeaders()));
            //$this->logger->debug("xhr response {$n}: {$xhr->response->getStatus()} {$xhr->response->getHeaders()}");
            if (strpos($xhr->request->getUri(), '/exp-web-trips/v1/api/trips?') !== false) {
                $this->logger->debug("xhr response {$n} body: " . json_encode($xhr->response->getBody()));
                $this->responseData = json_encode($xhr->response->getBody());
            }
        }
        $response = $this->http->JsonLog($this->responseData);

        if ($this->http->FindPreg('/"count":0,"results":\[\],"status":"Success"/', false, $this->responseData)
                // "type": "HA.Error","code": 22206,
            || ($this->http->FindPreg('/"type":\s*"HA\.Error"/', false, $this->responseData)
                && $this->http->FindPreg('/"code":\s*22206,\s*"errors"/', false, $this->responseData))) {
            //if ($this->waitForElement(WebDriverBy::xpath("//div[contains(text(), 'You do not have any upcoming trips connected to your HawaiianMiles account.')]"), 0)) {
            $this->saveResponse();
            $this->itinerariesMaster->setNoItineraries(true);

            return [];
        }

        if (isset($response->results, $response->count)) {
            foreach ($response->results as $trip) {
                $this->logger->info("Parse Itinerary #{$trip->confirmationCode}", ['Header' => 3]);
                $this->ParseItinerary($trip);
            }
        } else {
            $this->saveResponse();
        }

        return $result;
    }

    public function ParseItinerary($trip)
    {
        $this->logger->notice(__METHOD__);

        $f = $this->itinerariesMaster->createFlight();
        $f->general()->confirmation($trip->confirmationCode, 'Confirmation code');

        foreach ($trip->passengers->entries as $entry) {
            $f->general()->traveller("{$entry->passengerName->firstName} {$entry->passengerName->lastName}");

            if (isset($entry->hawaiianMilesNumber)) {
                $f->program()->account($entry->hawaiianMilesNumber, false);
            }
        }

        foreach ($trip->flights->entries as $entry) {
            $s = $f->addSegment();
            $s->airline()->name($entry->airlineCode ?? $entry->operatedBy);
            $s->airline()->number($entry->flightNumber);

            $s->departure()->date2($entry->scheduledDeparture->airportDateTimeString);
            $s->departure()->code($entry->origin);
            $s->arrival()->date2($entry->scheduledArrival->airportDateTimeString);
            $s->arrival()->code($entry->scheduledDestination);

            $s->extra()->aircraft($entry->aircraftTypeDescription);

            foreach ($trip->segments->entries as $segEntry) {
                foreach ($segEntry->details as $detail) {
                    foreach ($detail->flightDetails as $flightDetail) {
                        if ($flightDetail->flightId == $entry->id) {
                            if (isset($flightDetail->seatNumber)) {
                                $this->logger->debug('go to segment: ' . $entry->id);
                                $s->extra()->seat($flightDetail->seatNumber);
                            }

                            break 2;
                        }
                    }
                }
            }
        }

        $this->logger->debug('Parsed Itinerary:');
        $this->logger->debug(var_export($f->toArray(), true), ['pre' => true]);
    }

    public function GetConfirmationFields()
    {
        return [
            'ConfNo' => [
                'Caption'  => 'Confirmation Code or Ticket #',
                'Type'     => 'string',
                'Size'     => 40,
                'Required' => true,
            ],
            'LastName' => [
                'Caption'  => 'Last Name',
                'Type'     => 'string',
                'Size'     => 40,
                'Value'    => $this->GetUserField('LastName'),
                'Required' => true,
            ],
        ];
    }

    public function GetHistoryColumns()
    {
        return [
            "Posted Date"     => "PostingDate",
            "Activity Date"   => "Info.Date",
            "Description"     => "Description",
            "Status Eligible" => "Info",
            "Segments"        => "Info",
            "Miles"           => "Miles",
            "Bonus Miles"     => "Bonus",
            "Total Miles"     => "Info",
        ];
    }

    public function ParseHistory($startDate = null)
    {
        $result = [];
        $this->logger->debug('[History start date: ' . ((isset($startDate)) ? date('Y/m/d H:i:s', $startDate) : 'all') . ']');
        $startTimer = $this->getTime();
        $page = 0;
        $this->logger->debug("[Page: {$page}]");

        try {
            $this->http->GetURL("https://www.hawaiianairlines.com/my-account/hawaiianmiles/mileage-statement");
        } catch (UnknownServerException | NoSuchWindowException | NoSuchDriverException $e) {
            $this->logger->error("Exception: " . $e->getMessage(), ['HtmlEncode' => true]);
        }
        $this->saveResponse();
        $startIndex = sizeof($result);
        $result = array_merge($result, $this->ParsePageHistory($startIndex, $startDate));

        $this->getTime($startTimer);

        return $result;
    }

    public function ParsePageHistory($startIndex, $startDate)
    {
        $result = [];
        $history = $this->http->JsonLog($this->http->FindPreg('/MilageActivityDetails":([^\]]+\])\,/ims'), 0, true);
        $rows = is_array($history) ? count($history) : "none";
        $this->logger->debug("Total {$rows} history items were found");

        if (is_array($history)) {
            foreach ($history as $row) {
                $dateStr = ArrayVal($row, 'PostedDateDisplay');
                $postDate = strtotime($dateStr);

                if (isset($startDate) && $postDate < $startDate) {
                    $this->logger->notice("break at date {$dateStr} ($postDate)");

                    break;
                }
                $result[$startIndex]['Posted Date'] = $postDate;
                $result[$startIndex]['Activity Date'] = strtotime(ArrayVal($row, 'ActivityDateDisplay', null), false);
                $result[$startIndex]['Description'] = ArrayVal($row, 'Description');
                $result[$startIndex]['Status Eligible'] = ArrayVal($row, 'StatusMiles');
                $result[$startIndex]['Segments'] = ArrayVal($row, 'Segments');
                $result[$startIndex]['Miles'] = ArrayVal($row, 'Miles');
                $result[$startIndex]['Bonus Miles'] = ArrayVal($row, 'BonusMiles');
                $result[$startIndex]['Total Miles'] = ArrayVal($row, 'TotalMiles');
                $startIndex++;
            }
        }

        return $result;
    }

    public function ConfirmationNumberURL($arFields)
    {
        return "https://www.hawaiianairlines.com/my-account/my-trips/manage-trip-itinerary";
    }

    public function CheckConfirmationNumberInternal($arFields, &$it)
    {
        $this->http->GetURL($this->ConfirmationNumberURL($arFields));
        $lastNameInput = $this->waitForElement(WebDriverBy::cssSelector('#lastNameInput'), 5);
        $confInput = $this->waitForElement(WebDriverBy::cssSelector('#confOrTNumInput'), 5);
        $submitButton = $this->waitForElement(WebDriverBy::cssSelector('#myTripsSubmitButton'), 5);
        $this->saveResponse();

        if (!$lastNameInput || !$confInput || !$submitButton) {
            $this->sendNotification('check retrieve');

            return null;
        }

        $lastNameInput->sendKeys($arFields['LastName']);
        $confInput->sendKeys($arFields['ConfNo']);
        sleep(2);
        $submitButton->click();
        $reservationCode = $this->waitForElement(WebDriverBy::cssSelector('#span_reservation_code'), 10);
        $this->saveResponse();

        if ($reservationCode) {
            $it = $this->ParseItinerary();

            return null;
        }

        $error = $this->waitForElement(WebDriverBy::cssSelector('div.alert-content-secondary'), 5);

        if ($error) {
            return $error->getText();
        }

        return null;
    }

    private function checkErrors()
    {
        $this->logger->notice(__METHOD__);
        // Service Unavailable
        // Sorry, an error occurred while processing your request.
        if ($this->http->FindSingleNode("
                //h2[contains(text(), 'Service Unavailable')]
                | //h2[contains(text(), 'Sorry, an error occurred while processing your request.')]
                | //h1[contains(text(), 'Internal Server Error - Read')]
            ")
        ) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }
        // Maintenance
        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'We are currently experiencing technical difficulties with HawaiianMiles.')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // www.hawaiianairlines.com redirected you too many times.
        if ($this->http->FindSingleNode("//p[contains(., 'redirected you too many times.') and strong[contains(text(), 'www.hawaiianairlines.com')]]")) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        // retries
        if ($this->http->FindSingleNode("//strong[contains(text(), 'Your request has been blocked.')]")
            || $this->http->FindPreg("/Access to this website has been temporarily blocked\./")) {
            $this->ErrorReason = self::ERROR_REASON_BLOCK;
            $this->DebugInfo = self::ERROR_REASON_BLOCK;

            throw new CheckRetryNeededException(3);
        }

        return false;
    }

    private function parseDiscounts(): bool
    {
        $this->logger->notice(__METHOD__);
        $this->logger->info("Discounts", ['Header' => 3]);

        try {
            $this->http->GetURL('https://www.hawaiianairlines.com/my-account/member-discounts');
            $this->saveResponse();
        } catch (TimeOutException $e) {
            $this->logger->error("Exception: " . $e->getMessage(), ['HtmlEncode' => true]);
            $this->driver->executeScript('window.stop();');
            $this->saveResponse();
        } catch (UnknownServerException $e) {
            $this->logger->error("Exception: " . $e->getMessage(), ['HtmlEncode' => true]);

            throw new CheckRetryNeededException(2, 0);
        } catch (NoSuchWindowException | NoSuchDriverException $e) {
            $this->logger->error("NoSuch Exception: " . $e->getMessage(), ['HtmlEncode' => true]);

            return false;
        }

        $xpathDiscounts = '
            //h1[contains(text(), "Member Discounts")]
            | //h1[contains(text(), "Member Offers")]
            | //h2[contains(text(), "Sign in to your account")]
        ';
        $headers = $this->http->FindNodes($xpathDiscounts);

        // provider bug fix
        if (!$headers && $this->http->FindSingleNode('//h1[contains(text(), "504 Gateway Time-out")] | //div[contains(text(), "Access to this website has been temporarily blocked. Please try again after 10 minutes.")]')) {
            $delay = 5;
            $this->logger->error("try load page one more time, sleep -> {$delay}");
            $this->sendNotification("try load page one more time, sleep -> {$delay} // RR");
            sleep($delay);
            $this->http->GetURL('https://www.hawaiianairlines.com/my-account/member-discounts');
            $this->saveResponse();
            $headers = $this->http->FindNodes($xpathDiscounts);
        }

        if (!$headers) {
            /*
            $this->sendNotification('check discounts');
            */

            return false;
        }
        $this->sendNotification('check discounts // MI');

        $discounts = $this->xpathQuery('//div[contains(@class, "discount") and contains(@class, "row")]');

        if ($discounts->length === 0) {
            return false;
        }

        foreach ($discounts as $node) {
            $acc = [];
            $fullName = $this->http->FindSingleNode('.//h2', $node);
            $name = (
                $this->http->FindPreg('/^(.+?\b(?:RT|Roundtrip)\b)/i', false, $fullName)
                ?: $this->http->FindPreg('/^(.+?)\s*[-–]/i', false, $fullName)
                ?: $fullName
            );

            if (!$name || strlen($name) > 115) {
                $this->logger->debug("check discount name: {$name} / strlen: " . strlen($name));
                $this->sendNotification("check discount name: {$name}");

                continue;
            }
            $nodeValue = trim(preg_replace('/\n+\s*/', "\n", $node->nodeValue));
            $code = $this->http->FindPreg('/E-certificate # (\w+)/', false, $nodeValue);
            $passengers = $this->http->FindPreg('/# of Passengers:\s+(\d+)/', false, $nodeValue);
            $expStr = (
                $this->http->FindPreg('/Book: Now [-–] (\d+\/\d+\/\d{4})/u', false, $nodeValue)
                ?: $this->http->FindPreg('/Booking Period\s*Now through (\d+\/\d+\/\d{4})/u', false, $nodeValue)
            );
            $exp = $expStr ? strtotime($expStr) : null;

            if ($expStr && !$exp) {
                $this->sendNotification('check discount exp');
            }
            $acc = [
                'Balance'           => null,
                'Code'              => "hawaiian{$code}",
                'DisplayName'       => $name,
                'CertificateNumber' => $code,
                'Passengers'        => $passengers,
                'ExpirationDate'    => $exp,
            ];
            $this->AddSubAccount($acc);
        }

        return true;
    }

    private function xpathQuery($query, ?DOMNode $parent = null): DomNodeList
    {
        $this->logger->notice(__METHOD__);
        $res = $this->http->XPath->query($query, $parent);
        $this->logger->info("found {$res->length} nodes: {$query}");

        return $res;
    }
}
