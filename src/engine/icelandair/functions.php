<?php

use AwardWallet\Common\Parsing\Html;
use AwardWallet\Common\Parsing\WrappedProxyClient;
use AwardWallet\Engine\ProxyList;

class TAccountCheckerIcelandair extends TAccountChecker
{
    use ProxyList;
    use SeleniumCheckerHelper;
    /**
     * @var CaptchaRecognizer
     */
    private $recognizer;

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;

        // prevent "status":429,"error":"Too Many Requests"
        if ($this->attempt == 1) {
            $this->http->SetProxy($this->proxyReCaptcha());
        }
    }

    public function IsLoggedIn()
    {
        if (!isset($this->State['access_token'])) {
            return false;
        }

        $access_token = $this->State['access_token'];
        $this->http->RetryCount = 0;
        $headers = [
            "Authorization"  => "Bearer {$access_token}",
            "X-Access-Token" => $access_token,
        ];
        $this->http->GetURL("https://www.icelandair.com/api/user/v2/profile/", $headers, 20);
        $this->http->RetryCount = 2;
        $response = $this->http->JsonLog();

        if (isset($response->frequentFlyerId)) {
            return $this->loginSuccessful($access_token);
        }

        return false;
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->GetURL("https://www.icelandair.com");

        if ($this->http->Response['code'] != 200) {
            return $this->checkErrors();
        }

        $this->getCookiesFromSelenium();
        return true;

        $this->http->Form = [];
//        $this->http->FormURL = 'https://www.icelandair.com/api/oauth/token';
        $this->http->FormURL = 'https://my-account.icelandair.com/api/v1/auth/login/';
        $this->http->SetInputValue("username", $this->AccountFields['Login']);
        $this->http->SetInputValue("password", $this->AccountFields['Pass']);
        $this->http->SetInputValue("grant_type", "password");

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);

        if ($this->http->FindSingleNode('
                //h1[contains(text(), "504 Gateway Time-out")]
                | //h2[contains(text(), "The request could not be satisfied.")]
            ')
        ) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
        $this->http->setDefaultHeader("Accept", "application/json, text/plain, */*");
        $this->http->RetryCount = 0;
        $formUrl = $this->http->FormURL;
        $form = $this->http->Form;

        if (false && !$this->http->PostForm() && $this->http->Response['code'] != 400) {
            // prevent "status":429,"error":"Too Many Requests"
            if ($this->http->Response['code'] == 429 && strstr($this->http->Response['body'], ',"status":429,"error":"Too Many Requests","message":"Too many login attempts.","path":"/oauth/token"}')) {
                $captcha = $this->parseReCaptcha();

                if ($captcha !== false) {
                    $this->http->FormURL = $formUrl;
                    $this->http->Form = $form;
                    $this->http->SetInputValue('uvresp', $captcha);

                    if (!$this->http->PostForm() && !in_array($this->http->Response['code'], [400, 401])) {
                        return $this->checkErrors();
                    }

                    if (
                    strstr($this->http->Response['body'], ',"status":401,"error":"Unauthorized","message":"reCaptcha verification failed","path":"/oauth/token"}')
                    ) {
                        $this->captchaReporting($this->recognizer, false);

                        throw new CheckRetryNeededException(3, 1, self::CAPTCHA_ERROR_MSG);
                    }

                    $this->captchaReporting($this->recognizer);
                }
            } else {
                return $this->checkErrors();
            }
        }
        $this->http->RetryCount = 2;
        $response = $this->http->JsonLog(null, 3, true);
        // Access is allowed
        if ($access_token = ArrayVal($response, 'access_token', null)) {
            $this->State['access_token'] = $access_token;
            $this->loginSuccessful($access_token);
            $this->http->GetURL("https://www.icelandair.com/api/user/v2/profile/");

            return true;
        }

        $error =
            $this->http->FindSingleNode("//div[contains(@class, 'LoginEmail_error_bar__1ooml')]")
            ?? $this->http->FindSingleNode("//span[@id = 'err-username']")
        ;
        $this->logger->error("[Error]: {$error}");

        // Incorrect username or password. Please try again.
        if ($this->http->Response['body'] == '{"error":"invalid_grant","error_description":"Authentication failed!"}'
            || $this->http->Response['body'] == '{"error":"invalid_grant","error_description":"Bad credentials"}'
            || $this->http->Response['body'] == '{"error":"invalid_grant","error_description":"User is disabled"}') {
            throw new CheckException("Incorrect username or password. Please try again.", ACCOUNT_INVALID_PASSWORD);
        }

        if ($error == 'Saga Club number is not valid') {
            throw new CheckException($error, ACCOUNT_INVALID_PASSWORD);
        }

        if (
            $this->http->Response['body'] == '{"error":"invalid_grant","error_description":"User credentials have expired"}'
            || $error == 'Your password has expired. Please click Forgot Password below to reset it.'
        ) {
            throw new CheckException("Your password has expired. Please click Forgot Password to reset it.", ACCOUNT_INVALID_PASSWORD);
        }
        // retry
        if (
            $this->http->FindSingleNode('//h2[contains(text(), "The request could not be satisfied.")]')
            && $this->http->Response['code'] == 403
        ) {
            throw new CheckRetryNeededException(3);
        }

        $this->DebugInfo = $error ?? $this->DebugInfo;

        return $this->checkErrors();
    }

    public function Parse()
    {
        $response = $this->http->JsonLog(null, 3, true);
        // Member since
        $dateJoined = ArrayVal($response, 'dateJoined', null);

        if ($dateJoined) {
            $this->SetProperty("MemberSince", DateTime::createFromFormat("Y-m-d", $dateJoined)->format('d.m.Y'));
        }
        // Saga Club Number
        $this->SetProperty("Number", ArrayVal($response, 'frequentFlyerId', null));
        // Name
        $this->SetProperty("Name", beautifulName(ArrayVal($response, 'firstName', null)) . " " . ArrayVal($response, 'lastName', null));

        $this->http->GetURL("https://www.icelandair.com/api/user/v2/getCardInfo/");
        $response = $this->http->JsonLog(null, 3, true);
        // Balance - Frequent flyer points
        $this->SetBalance(ArrayVal($response, 'awardPoints'));

        if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR) {
            if (
                ArrayVal($response, 'status') == '500'
                && ArrayVal($response, 'error') == 'Internal Server Error'
                && ArrayVal($response, 'exception') == 'org.springframework.web.client.HttpClientErrorException'
            ) {
                throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
            }
        }// if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR)

        // Card points
        $this->SetProperty("CardPoints", ArrayVal($response, 'yearlyPoints'));
        // Status
        switch (ArrayVal($response, 'cardType')) {
            case '00':
                $this->SetProperty("Status", "Saga Blue");

                break;

            case '11':
            case '22':
                $this->SetProperty("Status", "Saga Silver");

                break;

            case '77':
            case '88':
                $this->SetProperty("Status", "Saga Gold");

                break;

            default:
                if ($this->ErrorCode == ACCOUNT_CHECKED) {
                    $this->sendNotification("icelandair - refs #16654. New status was found " . ArrayVal($response, 'cardType'));
                }

                break;
        }// switch (ArrayVal($response, 'cardType'))

        // Expiration Date  // refs #4839
        $this->logger->info('Expiration date', ['Header' => 3]);
        // https://redmine.awardwallet.com/issues/4839#note-9
        $pointsToExpire = ArrayVal($response, 'pointsToExpire', []);

        foreach ($pointsToExpire as $pointToExp) {
            $amount = ArrayVal($pointToExp, 'amount', null);
            $dateExpired = ArrayVal($pointToExp, 'dateExpired', null);

            if ($amount != 0 && (!isset($exp) || $exp > strtotime($dateExpired))) {
                $exp = strtotime($dateExpired);
                $expiringBalance = $amount;
            }
        }

        if (!isset($expiringBalance)) {
            if ($this->http->FindPreg("/\"pointsToExpire\":\[\],/")) {
                $this->ClearExpirationDate();

                return;
            }

            if ($this->ErrorCode == ACCOUNT_CHECKED) {
                $this->sendNotification("refs #4839. Need to check exp date");
            }

            return;
        }
        $this->SetProperty("ExpiringBalance", $expiringBalance);

        if (isset($exp) && $expiringBalance != 0) {
            $this->SetExpirationDate($exp);
        } else {
            $this->sendNotification("refs #4839. Need to check exp date");
        }
    }

    public function GetHistoryColumns()
    {
        return [
            "Date"         => "PostingDate",
            "Description"  => "Description",
            "Saga Points"  => "Miles",
            "Bonus Points" => "Bonus",
            "Card Points"  => "Info",
        ];
    }

    public function ParseHistory($startDate = null)
    {
        $result = [];
        $this->logger->debug('[History start date: ' . ((isset($startDate)) ? date('Y/m/d H:i:s', $startDate) : 'all') . ']');
        $startTimer = $this->getTime();
        $page = 0;
        $this->http->GetURL("https://www.icelandair.com/api/user/v1/getPointsOverview/?years=5");
        $page++;
        $this->logger->debug("[Page: {$page}]");
        $startIndex = sizeof($result);
        $result = array_merge($result, $this->ParsePageHistory($startIndex, $startDate));
        $this->getTime($startTimer);

        return $result;
    }

    public function ParsePageHistory($startIndex, $startDate)
    {
        $result = [];
        $response = $this->http->JsonLog(null, 3, true);

        if (is_array($response)) {
            foreach ($response as $row) {
                $dateStr = ArrayVal($row, 'transactionDate');
                $postDate = strtotime($dateStr);

                if (isset($startDate) && $postDate < $startDate) {
                    $this->logger->notice("break at date {$dateStr} ($postDate)");

                    break;
                }// if (isset($startDate) && $postDate < $startDate)
                $result[$startIndex]['Date'] = $postDate;
                $result[$startIndex]['Description'] = Html::cleanXMLValue(ArrayVal($row, 'description'));
                $miles = ArrayVal($row, 'points');

                if ($this->http->FindPreg('/Bonus/ims', false, $result[$startIndex]['Description'])) {
                    $result[$startIndex]['Bonus Points'] = $miles;
                } else {
                    $result[$startIndex]['Saga Points'] = $miles;
                }
                // Miles spent
                $result[$startIndex]['Card Points'] = ArrayVal($row, 'cardPoints');

                $startIndex++;
            }
        }// foreach ($response as $row)

        return $result;
    }

    public function combineHistoryBonusToMiles()
    {
        return true;
    }

    public function GetConfirmationFields()
    {
        return [
            "ConfNo" => [
                "Caption"  => "Booking reference",
                "Type"     => "string",
                "Size"     => 20,
                "Required" => true,
            ],
            "LastName" => [
                "Type"     => "string",
                "Size"     => 40,
                "Value"    => $this->GetUserField('LastName'),
                "Required" => true,
            ],
        ];
    }

    public function ConfirmationNumberURL($arFields)
    {
        return "https://www.icelandair.com/";
    }

    public function CheckConfirmationNumberInternal($arFields, &$it)
    {
        $this->logger->notice(__METHOD__);
        $this->sendNotification('check retrieve // MI');

        $this->http->GetURL($this->ConfirmationNumberURL($arFields));

        if ($this->http->Response['code'] != 200) {
            return null;
        }
        $this->http->GetURL("https://booking.icelandair.com/manage-booking?lastName={$arFields['LastName']}&lang=en-US&orderId={$arFields['ConfNo']}");

        $headers = [
            'Authorization' => $this->http->FindPreg('/"apiKey":"(.+?)",/'),
        ];
        $this->http->GetURL("https://booking.icelandair.com/proxy/v2/purchase/orders/{$arFields['ConfNo']}?lastName={$arFields['LastName']}&showOrderEligibilities=false", $headers);
        $data = $this->http->JsonLog();
        $this->parseItinerary($data);
    }

    private function parseItinerary($data)
    {
        $this->logger->info("Parse itinerary #{$data->data->id}", ['Header' => 3]);
        $f = $this->itinerariesMaster->add()->flight();
        $f->general()->confirmation($data->data->id, 'Booking reference');
        $f->general()->date2($data->data->creationDateTime);

        foreach ($data->data->travelers as $traveller) {
            foreach ($traveller->names as $name) {
                $f->general()->traveller("$name->firstName $name->lastName");
            }
        }

        foreach ($data->data->travelDocuments as $travelDocument) {
            $f->program()->account($travelDocument->id, false);
        }

        foreach ($data->data->air->bounds as $bounds) {
            foreach ($bounds->flights as $flight) {
                $this->logger->debug($flight->id);
                $segment = $data->dictionaries->flight->{$flight->id} ?? null;

                if (!$segment) {
                    continue;
                }
                $s = $f->addSegment();
                $s->airline()->name($segment->operatingAirlineCode);
                $s->airline()->number($segment->marketingFlightNumber);
                $s->extra()->aircraft($data->dictionaries->aircraft->{$segment->aircraftCode} ?? null);
                $s->departure()->code($segment->departure->locationCode);
                $s->arrival()->code($segment->arrival->locationCode);
                // 2024-04-05T18:55:00.000-07:00
                // 2024-04-06T09:20:00.000Z
                $s->departure()->date2($this->http->FindPreg('/^(\d{4}-.+?):\d{2}\./', false, $segment->departure->dateTime));
                $s->arrival()->date2($this->http->FindPreg('/^(\d{4}-.+?):\d{2}\./', false, $segment->arrival->dateTime));

                // $s->extra()->duration($this->convertFormat($segment->duration));
            }
        }

        $this->logger->debug('Parsed itinerary:');
        $this->logger->debug(var_export($f->toArray(), true), ['pre' => true]);
    }

    private function convertFormat($duration)
    {
        if (empty($duration)) {
            return null;
        }
        $hours = floor($duration / 60);
        $minutes = $duration % 60;

        return $hours > 0 ? sprintf('%02dh %02dm', $hours, $minutes)
            : sprintf('%02dm', $minutes);
    }

    private function loginSuccessful($access_token)
    {
        $this->logger->notice(__METHOD__);
        $this->http->setDefaultHeader("Authorization", "Bearer {$access_token}");
        $this->http->setDefaultHeader("X-Access-Token", $access_token);

        return true;
    }

    private function getCookiesFromSelenium($retry = false)
    {
        $this->logger->notice(__METHOD__);

        $selenium = clone $this;
        $retry = false;
        $this->http->brotherBrowser($selenium->http);

        try {
            $this->logger->notice("Running Selenium...");
            $selenium->UseSelenium();
            $selenium->seleniumOptions->recordRequests = true;

//            $selenium->http->SetProxy($this->proxyReCaptcha());
            $selenium->setProxyGoProxies();

            /*
            */
            $wrappedProxy = $this->services->get(WrappedProxyClient::class);
            $proxy = $wrappedProxy->createPort($selenium->http->getProxyParams());
            $selenium->seleniumOptions->antiCaptchaProxyParams = $proxy;
            $selenium->seleniumOptions->addAntiCaptchaExtension = true;

//            $selenium->useFirefox(SeleniumFinderRequest::FIREFOX_84);
//            $selenium->setKeepProfile(true);
//            $selenium->seleniumOptions->fingerprintOptions = ['devices' => ['desktop'], 'operatingSystems' => ['macos']];
//            $selenium->seleniumOptions->addHideSeleniumExtension = false;
//            $selenium->seleniumOptions->userAgent = null;

            $selenium->useChromePuppeteer();
            $selenium->seleniumOptions->fingerprintOptions = ['devices' => ['desktop'], 'operatingSystems' => ['macos']];
            $selenium->seleniumOptions->addHideSeleniumExtension = false;
            $selenium->seleniumOptions->userAgent = null;
//            $selenium->useFirefoxPlaywright();
//            $selenium->seleniumOptions->addHideSeleniumExtension = false;
//            $selenium->seleniumOptions->userAgent = null;
//            $selenium->http->setUserAgent($this->http->getDefaultHeader("User-Agent"));

            $selenium->http->saveScreenshots = true;
            $selenium->http->start();
            $selenium->Start();
            $selenium->http->GetURL('https://my-account.icelandair.com/?showHeader=true');
            $login = $selenium->waitForElement(WebDriverBy::xpath('//input[@name="username"]'), 5);
            $pass = $selenium->waitForElement(WebDriverBy::xpath('//input[@name="password"]'), 5);
            $this->savePageToLogs($selenium);

            if (!$login && $this->clickCloudFlareCheckboxByMouse($selenium)) {
                $login = $selenium->waitForElement(WebDriverBy::xpath('//input[@name="username"]'), 5);
                $pass = $selenium->waitForElement(WebDriverBy::xpath('//input[@name="password"]'), 5);
                $this->savePageToLogs($selenium);
            }

            if (!$login || !$pass) {
                return false;
            }

            $login->sendKeys($this->AccountFields['Login']);
            $pass->sendKeys($this->AccountFields['Pass']);

            $selenium->waitForElement(WebDriverBy::xpath('//a[contains(text(), "Solving is in process...")]'), 10);

            $selenium->waitFor(function () use ($selenium) {
                $this->logger->warning("Solving is in process...");
                sleep(3);
                $this->savePageToLogs($selenium);

                return !$this->http->FindSingleNode('//a[contains(text(), "Solving is in process...")]');
            }, 250);
            /*
            $captcha = $this->parseReCaptcha($selenium->http->currentUrl());

            if ($captcha === false) {
                $this->logger->error('Something went wrong');

                return false;
            }

            $selenium->driver->executeScript('document.getElementsByName("g-recaptcha-response").value = "' . $captcha . '";');
            $this->savePageToLogs($selenium);
            $this->logger->notice("Executing captcha callback");
            $selenium->driver->executeScript('
                var findCb = (object) => {
                    if (!!object["callback"] && !!object["sitekey"]) {
                        return object["callback"]
                    } else {
                        for (let key in object) {
                            if (typeof object[key] == "object") {
                                return findCb(object[key])
                            } else {
                                return null
                            }
                        }
                    }
                }
                findCb(___grecaptcha_cfg.clients[0])("' . $captcha . '")
            ');
            */

            $button = $selenium->waitForElement(WebDriverBy::xpath('//button[@id = "submit_login" and not(@disabled)]'), 3);
            $this->savePageToLogs($selenium);

            if (!$button) {
                $solvingStatus =
                    $this->http->FindSingleNode('//a[@title="AntiCaptcha: Captcha solving status"]')
                    ?? $this->http->FindSingleNode('//a[@class = "status"]')
                ;

                if ($solvingStatus) {
                    $this->logger->error("[AntiCaptcha]: {$solvingStatus}");

                    if (
                        strstr($solvingStatus, 'Proxy response is too slow,')
                        || strstr($solvingStatus, 'Could not connect to proxy related to the task, connection refused')
                        || strstr($solvingStatus, 'Could not connect to proxy related to the task, connection timeout')
                        || strstr($solvingStatus, 'Captcha could not be solved by 5 different workers')
                        || strstr($solvingStatus, 'Solving is in process...')
                        || strstr($solvingStatus, 'Proxy IP is banned by target service')
                        || strstr($solvingStatus, 'Recaptcha server reported that site key is invalid')
                    ) {
                        $this->markProxyAsInvalid();

                        throw new CheckRetryNeededException(2, 3, self::CAPTCHA_ERROR_MSG);
                    }

                    $this->DebugInfo = $solvingStatus;
                }

                return false;
            }

            $selenium->driver->executeScript('
                let oldXHROpen = window.XMLHttpRequest.prototype.open;
                window.XMLHttpRequest.prototype.open = function(method, url, async, user, password) {
                    this.addEventListener("load", function() {
                        if (/api\/v1\/auth\/login/g.exec(url)) {
                            localStorage.setItem("response", this.responseText);
                        }
                    });
                    return oldXHROpen.apply(this, arguments);
                };
            ');

            $button->click();

            $selenium->waitForElement(WebDriverBy::xpath("//div[contains(@class, 'LoginEmail_error_bar__1ooml')]"), 13);
            $this->savePageToLogs($selenium);

            $responseData = $selenium->driver->executeScript("return localStorage.getItem('response');");
            $this->logger->info("[Response]: " . $responseData);
            $responseData = null;

            $seleniumDriver = $selenium->http->driver;
            $requests = $seleniumDriver->browserCommunicator->getRecordedRequests();

            foreach ($requests as $n => $xhr) {
                $this->logger->debug("xhr request {$n}: {$xhr->request->getVerb()} {$xhr->request->getUri()} " . json_encode($xhr->request->getHeaders()));
//                $this->logger->debug("xhr response {$n}: {$xhr->response->getStatus()} {$xhr->response->getHeaders()}");
                if (stristr($xhr->request->getUri(), '/api/v1/auth/login')) {
//                    $this->logger->debug("xhr response {$n} body: " . json_encode($xhr->response->getBody()));
                    $responseData = json_encode($xhr->response->getBody());
                    $this->http->SetBody($responseData);
                }

                if (stristr($xhr->request->getUri(), 'api/oauth/token')) {
                    $this->logger->debug("xhr response {$n} body: " . json_encode($xhr->response->getBody()));
                    $responseData = json_encode($xhr->response->getBody());

                    break;
                }
            }

//            $this->logger->debug("xhr response: $responseData");

            $cookies = $selenium->driver->manage()->getCookies();

            foreach ($cookies as $cookie) {
                $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
            }

            if (!empty($responseData)) {
                $this->http->SetBody($responseData);
            }
        } finally {
            // close Selenium browser
            $selenium->http->cleanup();

            // retries
            if ($retry && ConfigValue(CONFIG_SITE_STATE) != SITE_STATE_DEBUG) {
                throw new CheckRetryNeededException(3);
            }
        }

        return null;
    }

    protected function parseReCaptcha($pageurl)
    {
        $this->logger->notice(__METHOD__);

        $key = $this->http->FindPreg("/\"RECAPTCHA_SITE_KEY\":\"([^\"]+)/");

        if (!$key) {
            return false;
        }

        /*
        $postData = [
            "type"         => "RecaptchaV2TaskProxyless",
            "websiteURL"   => $pageurl,
            "websiteKey"   => $key,
            "apiDomain"    => "www.recaptcha.net",
        ];
        $this->recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_ANTIGATE_API_V2);
        $this->recognizer->RecognizeTimeout = 120;

        return $this->recognizeAntiCaptcha($this->recognizer, $postData);
        */

        $this->recognizer = $this->getCaptchaRecognizer();
        $this->recognizer->RecognizeTimeout = 120;
        $parameters = [
            "pageurl" => $pageurl,
//            "proxy"   => $this->http->GetProxy(),
            "domain"  => "www.recaptcha.net",
        ];

        return $this->recognizeByRuCaptcha($this->recognizer, $key, $parameters);
    }
}
