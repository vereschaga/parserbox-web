<?php

use AwardWallet\Common\Parsing\Html;

class TAccountCheckerSj extends TAccountChecker
{
    use SeleniumCheckerHelper;

    private $apiUrl;
    private $auth;
    private $headers = [
        'Accept'               => 'application/json, text/plain, */*',
        'Content-Type'         => 'application/json;charset=utf-8',
        "X-api.sj.se-language" => "en",
    ];

    private $currentItin = 0;

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;

        $this->UseSelenium();
        $this->useGoogleChrome();
        $this->http->saveScreenshots = true;
    }

    public function LoadLoginForm()
    {
        if (filter_var($this->AccountFields['Login'], FILTER_VALIDATE_EMAIL) === false) {
            throw new CheckException('Please enter a valid Email.', ACCOUNT_INVALID_PASSWORD);
        }

        $this->http->removeCookies();

        /*
        if (!$this->apiProcessing()) {
            $this->logger->error('API processing error');

            return false;
        }
        */

        $this->http->GetURL("https://www.sj.se/en/login");

        $login = $this->waitForElement(WebDriverBy::xpath('//input[@name = "Email address"]'), 10);
        $password = $this->waitForElement(WebDriverBy::xpath('//input[@name = "Password"]'), 0);
        $signInButton = $this->waitForElement(WebDriverBy::xpath('//button[@id = "next"]'), 0);
        $this->saveResponse();

        if (!$login || !$password || !$signInButton) {
            return $this->checkErrors();
        }

        $login->sendKeys($this->AccountFields['Login']);
        $password->sendKeys($this->AccountFields['Pass']);

        $this->driver->executeScript("let remember = document.getElementById('rememberMe'); if (remember) remember.checked = true;");
        $this->saveResponse();

        $signInButton->click();

        return true;
    }

    public function Login()
    {
        $this->waitForElement(WebDriverBy::xpath('
            //button[@id = "sendCode"]
            | //a[contains(@class, \'errorSummaryCard-message\')]
            | //div[contains(@class, "error") and @style="display: block;"]
        '), 10);
        $this->saveResponse();

        if ($sendCode = $this->waitForElement(WebDriverBy::xpath('//button[@id = "sendCode"]'), 0)) {
            $sendCode->click();

            return $this->processSecurityCheckpoint();
        }

        if ($message = $this->http->FindSingleNode('//a[contains(@class, "errorSummaryCard-message")] | //div[contains(@class, "error") and @style="display: block;"]')) {
            $this->logger->notice("[Error]: {$message}");

            if (
                strstr($message, "Check email address")
                || strstr($message, "The login details don’t match, check the email address and password.")
            ) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            $this->DebugInfo = $message;

            return false;
        }

        $response = $this->http->JsonLog($this->http->FindSingleNode("//pre[not(@id)]"));

        if (isset($response->mfaToken)) {
            if ($response->oneTimePasswordRequired == true) {
                $this->logger->notice("sending one-time code");

                // Register your mobile number
                if (empty($response->maskedPhoneNumber)) {
                    $this->throwProfileUpdateMessageException();
                }

                // prevent code spam    // refs #6042
                if ($this->isBackgroundCheck()) {
                    $this->Cancel();
                }

                // A one-time code has been sent to: $response->maskedPhoneNumber
                if ($response->phoneNumberUpdatePossible == true) {
                    $this->http->PostURL($this->apiUrl . "/security/{$response->mfaToken}/onetimepassword", '{}', $this->headers);
                }
                // else code will be sent by default
                $response = $this->http->JsonLog();

                if (!isset($response->mfaToken)) {
                    $this->logger->error("something went wrong");

                    return false;
                }
                $this->State['apiUrl'] = $this->apiUrl;
                $this->State['mfaToken'] = $response->mfaToken;
                $this->AskQuestion("A one-time code has been sent to: {$response->maskedPhoneNumber}", null, "Question");

                return false;
            }// if ($response->oneTimePasswordRequired == true && !empty($response->maskedPhoneNumber))

            $this->http->PostURL($this->apiUrl . "/security/{$response->mfaToken}/usertoken", '{"oneTimePassword":""}', $this->headers);
            $response = $this->http->JsonLog();
        } else {
            $this->logger->error('mfaToken not found');
        }

        if (isset($response->errors[0])) {
            switch ($response->errors[0]->code) {
//                case 'ERROR_FORBIDDEN':
                case 'AUTHENTICATION_FAILURE':
                    throw new CheckException('Your login or password is incorrect', ACCOUNT_INVALID_PASSWORD);

                    break;

                case 'PASSWORD_FORMAT':
                    throw new CheckException('Incorrect password format', ACCOUNT_INVALID_PASSWORD);

                    break;

                case 'AUTHENTICATION_FAILURE_LOCK':
                    throw new CheckException('Your account has been locked.', ACCOUNT_LOCKOUT);

                    break;

                case 'AUTHENTICATION_FAILURE_FORBIDDEN':
                    throw new CheckException('Your account has been temporarily locked', ACCOUNT_PROVIDER_ERROR);

                    break;

                case 'SERVICE_UNAVAILABLE':
                    throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);

                    break;
            }
        }

        $this->logger->debug('[DATA URL]: ' . $this->apiUrl . "/security/currenttoken");
        $this->http->GetURL($this->apiUrl . "/security/currenttoken");
        $response = $this->http->JsonLog($this->http->FindSingleNode("//pre[not(@id)]"));

        if (!empty($response->customer->id)) {
            $this->auth = $response;
            $this->http->setCookie($response->session->name, $response->session->token);
            $this->http->setCookie($response->service->name, $response->service->token);

            return true;
        }

        return $this->checkErrors();
    }

    public function processSecurityCheckpoint(): bool
    {
        $this->logger->notice(__METHOD__);
        $this->logger->info('Security Question', ['Header' => 3]);
        $codeInput = $this->waitForElement(WebDriverBy::xpath('//input[@id = "verificationCode"]'), 5);
        $question = $this->waitForElement(WebDriverBy::xpath('//p[contains(text(), "We\'ve sent a code to your")]'), 0);
        $questionValue = $this->waitForElement(WebDriverBy::xpath('//p[contains(text(), "We\'ve sent a code to your")]/following-sibling::p'), 0);
        $this->saveResponse();

        if (!$question || !$questionValue || !$codeInput) {
            return false;
        }

        $this->Question = Html::cleanXMLValue($question->getText() . " " . $questionValue->getText());

        if (!isset($this->Answers[$this->Question])) {
            $this->holdSession();
            $this->AskQuestion($this->Question, null, "Question2fa");

            return false;
        }

        $answer = $this->Answers[$this->Question];
        unset($this->Answers[$this->Question]);

        $codeInput->click();
        $codeInput->clear();
        $codeInput->sendKeys($answer);

        $this->logger->debug("click button...");
        $button = $this->waitForElement(WebDriverBy::xpath('//button[@id = "verifyCode" and not(contains(@class, "disabled"))]'), 5);
        $this->saveResponse();

        if (!$button) {
            return false;
        }

        $button->click();
        sleep(5);
        $error = $this->waitForElement(WebDriverBy::xpath("//a[contains(@class, 'errorSummaryCard-message')]"), 5);
        $this->saveResponse();

        if ($error) {
            $message = $error->getText();
            $codeInput->clear();

            $this->logger->error("[Error]: {$message}");

            if (strstr($message, 'Check code')) {
                $this->holdSession();
                $this->AskQuestion($this->Question, $message, "Question2fa");
            }

            return false;
        }

        $this->logger->debug("success");
        $this->logger->debug("[Current URL]: {$this->http->currentUrl()}");

        $this->logger->debug('[DATA URL]: ' . $this->apiUrl . "/security/currenttoken");
        $this->http->GetURL($this->apiUrl . "/security/currenttoken");
        $response = $this->http->JsonLog($this->http->FindSingleNode("//pre[not(@id)]"));

        if (!empty($response->customer->id)) {
            $this->auth = $response;
            $this->http->setCookie($response->session->name, $response->session->token);
            $this->http->setCookie($response->service->name, $response->service->token);

            return true;
        }

        return true;
    }

    public function ProcessStep($step)
    {
        $this->apiUrl = $this->State['apiUrl'];

        if ($step == 'Question2fa') {
            return $this->processSecurityCheckpoint();
        }

        $data = [
            "oneTimePassword" => $this->Answers[$this->Question],
        ];
        $this->http->PostURL($this->apiUrl . "/security/{$this->State['mfaToken']}/usertoken", json_encode($data), $this->headers);
        unset($this->Answers[$this->Question]);
        $response = $this->http->JsonLog();
        $code = $response->errors[0]->code ?? null;

        if ($code == 'ONE_TIME_PASSWORD_INVALID') {
            $this->AskQuestion($this->Question, "Incorrect one time password", "Question");

            return false;
        } elseif ($code == 'ONE_TIME_PASSWORD_REVOKED') {
            $this->AskQuestion($this->Question, "The one-time code is no longer valid", "Question");

            return false;
        } elseif ($code == 'ONE_TIME_PASSWORD_EXPIRED') {
            $this->AskQuestion($this->Question, "Expired one time password", "Question");

            return false;
        }

        $this->logger->debug('[DATA URL]: ' . $this->apiUrl . "/security/currenttoken");
        $this->http->GetURL($this->apiUrl . "/security/currenttoken");
        $response = $this->http->JsonLog($this->http->FindSingleNode("//pre[not(@id)]"));

        if (!empty($response->customer->id)) {
            $this->auth = $response;
            $this->http->setCookie($response->session->name, $response->session->token);
            $this->http->setCookie($response->service->name, $response->service->token);

            return true;
        }

        return false;
    }

    public function Parse()
    {
        $this->logger->debug('[DATA URL]: ' . $this->apiUrl . '/customers/' . $this->auth->customer->id . '/loyalties');
        $this->http->GetURL($this->apiUrl . '/customers/' . $this->auth->customer->id . '/loyalties');
        $response = $this->http->JsonLog($this->http->FindSingleNode("//pre[not(@id)]"));

        if (isset($response->loyaltyMemberships[0])) {
            $usr = $response->loyaltyMemberships[0];
            // SJ Prio card
            $this->SetProperty('CardNumber', $usr->loyaltyCard->number);
            // Member since
            $this->SetProperty('MemberSince', $usr->start->date);
            $this->SetProperty('Name', beautifulName(trim($usr->member->firstName) . ' ' . $usr->member->lastName));
            // Points to To use
            $this->SetBalance($usr->balance);
            // Level
            switch ($usr->tier) {       // clientlib.js:31873
                case 'TIER_1':
                    $tier = 'White';

                    break;

                case 'TIER_2':
                    $tier = 'Grey';

                    break;

                case 'TIER_3':
                    $tier = 'Black';

                    break;

                default:
                    $tier = null;
                    $this->sendNotification('fish - refs #2649 [sj] > valid account :: new tier/level status');
            }// switch ($usr->tier)
            $this->SetProperty('Tier', $tier);
            // Level points
            $this->SetProperty('TierPoints', $usr->tierPoints);
            // To $status$ level
            $this->SetProperty('PointsNextTier', $usr->pointsToNextTier);
            // points expiration
            if (!empty($usr->pointExpirations[0]->validThrough->stopDate)) {
                $pointsExpire = [];

                foreach ($usr->pointExpirations as $points) {
                    $date = strtotime($points->validThrough->stopDate->date . ' ' . $points->validThrough->stopTime->time);
                    false === $date ?: $pointsExpire[$date] = $points->points;
                }
                ksort($pointsExpire, SORT_NUMERIC);
                $firstExpire = array_keys($pointsExpire)[0];
                $this->SetProperty('ExpiringPoints', $pointsExpire[$firstExpire]);
                $this->SetExpirationDate($firstExpire);
            }// if (!empty($usr->pointExpirations[0]->validThrough->stopDate))
        }// if (isset($response->loyaltyMemberships[0]))
        elseif ($this->http->FindPreg("/\{\"loyaltyMemberships\":\[\]\}/")) {
            $this->ErrorCode = ACCOUNT_WARNING;
            $this->ErrorMessage = self::NOT_MEMBER_MSG;
        }// elseif ($this->http->FindPreg("/\{\"loyaltyMemberships\":\[\]\}/"))
        // Sorry, an error occurred.
        elseif (isset($response->errors[0]->code) && $response->errors[0]->code == 'LOYALTY_FETCH_FAILURE') {
            throw new CheckException('Sorry, an error occurred.', ACCOUNT_PROVIDER_ERROR);
        }// elseif (isset($response->errors[0]->code) && $response->errors[0]->code == 'LOYALTY_FETCH_FAILURE')

        /*$this->http->GetURL($this->apiUrl . '/customers/' . $this->auth->customer->id . '/orderitems?period=ALL&type=ALL');
        $response = $this->http->JsonLog();
        if (false !== $response && !empty($response))
            $this->sendNotification('fish - refs #2649 [sj] > valid account :: booking');*/
    }

    public function ParseItineraries()
    {
        $this->http->GetURL('https://www.sj.se/en/my-page.html#/');
        $headers = [
            'Accept'               => 'application/json, text/plain, */*',
            'X-api.sj.se-language' => 'en',
        ];
        /*
        $this->http->GetURL($this->apiUrl.'/customers/'.$this->auth->customer->id.'/orderitems?period=FUTURE&type=TRAVEL', $headers);
        */
        $this->http->GetURL($this->apiUrl . '/customers/' . $this->auth->customer->id . '/orderitems?period=ALL&type=ALL'); //todo: почему не собраны прошлые?
        $resp = $this->http->JsonLog($this->http->FindPreg('#<pre.+?>(.+?)</pre>#'), 0, true);

        if ($resp === []) {
            return $this->noItinerariesArr();
        }

        if (!$resp) {
            $this->sendNotification('check itineraries // MI');

            return [];
        }
        $itinTokens = array_map(function ($item) {
            return ArrayVal($item, 'cartToken');
        }, $resp);
        $itinTokens = array_values(array_unique(array_filter($itinTokens)));

        foreach ($itinTokens as $token) {
            $this->parseItinerary($token);
        }

        return [];
    }

    public function ArrayVal($ar, $indices, $default = null)
    {
        $res = $ar;

        foreach ($indices as $index) {
            if (isset($res[$index])) {
                $res = $res[$index];
            } else {
                $this->logger->debug('Invalid indices:');
                $this->logger->debug(var_export($indices, true));

                return $default;
            }
        }

        if (is_string($res)) {
            $res = trim($res);
        }

        return $res;
    }

    public function GetConfirmationFields()
    {
        return [
            "ConfNo" => [
                "Caption"  => "Booking code",
                "Type"     => "string",
                "Size"     => 20,
                "Required" => true,
            ],
            "Email" => [
                "Caption"  => "Phone or email",
                "Type"     => "string",
                "Size"     => 80,
                "Value"    => $this->GetUserField('Email'),
                "Required" => true,
            ],
        ];
    }

    public function ConfirmationNumberURL($arFields)
    {
        return "https://www.sj.se/en/andra-bokning/sok-bokning";
    }

    public function CheckConfirmationNumberInternal($arFields, &$it)
    {
        $this->http->GetURL($this->ConfirmationNumberURL($arFields));

        if ($this->http->Response['code'] != 200) {
            $this->sendNotification("failed to retrieve itinerary by conf #");

            return null;
        }

        if (!$this->apiProcessing()) {
            $this->logger->error('API processing error');
            $this->sendNotification("failed to retrieve itinerary by conf #");

            return null;
        }
        $payload = [
            'orderId'  => $arFields['ConfNo'],
            'security' => $arFields['Email'],
        ];
        $this->http->RetryCount = 0;
        $this->http->PostURL($this->apiUrl . "/orders/carttokens", json_encode($payload), $this->headers);
        $this->http->RetryCount = 2;
        $resp = $this->http->JsonLog(null, 0, true);

        if (!$resp) {
            $this->sendNotification("failed to retrieve itinerary by conf #");

            return null;
        }

        if ($this->http->FindPreg('/"code":"(?:ORDER_ID_FORMAT_ERROR|ORDER_NOT_FOUND)"/')) {
            return 'Incorrect booking number or email/telephone number.';
        }

        if ($error = $this->http->FindPreg('/"code":"ORDER_IS_LOCKED","reason":"(.+?)"/')) {
            return "The booking is locked. If the booking is open on another device/in another window, we recommend that you click on Done with the booking. Otherwise the booking will remain locked for 30 minutes.";
        }
        $token = $this->http->FindPreg('/cartToken":"([\w-]+)"/');

        if (!$token) {
            $this->sendNotification('check retrieve');

            return null;
        }
        $this->parseItinerary($token);

        return null;
    }

    protected function apiProcessing()
    {
        $this->http->GetURL('https://www.sj.se/en/');

//        if ($this->http->FindPreg("/window\[\"bobcmn\"\]/")) {
//            $this->selenium();
//        }

        /*
        $apiDomain = $this->http->FindPreg("/sjServicesUrl\s*:\s*'(.*)'/i") ?? 'https://www.sj.se';
        $apiPath = $this->http->FindPreg("/baseURL\s*:\s*'(.*)'/i") ?? '/v19/rest';

        if (!empty($apiDomain) && !empty($apiPath)) {
            $this->apiUrl = rtrim($apiDomain, '/') . '/' . trim($apiPath, '/');
        }

        $tokens = $this->http->FindPregAll('/"token"\s*:\s*"(.*)"/iU');
        $sessionVersion = $this->http->FindPreg("/name\":\"(X-api.sj.se-session-v[^\"]+)/");

        if (empty($tokens[1]) || !$sessionVersion) {
            return false;
        }
        */

        $this->http->GetURL('https://www.sj.se/cms/configuration');
        $cmsConfig = $this->http->JsonLog($this->http->FindSingleNode("//pre[not(@id)]"));
        $sessionVersion = $cmsConfig->cookie->session->name ?? null;
        $sessionToken = $cmsConfig->cookie->session->token ?? null;
        $serviceToken = $cmsConfig->cookie->service->token ?? null;

        if (!isset($sessionVersion, $sessionToken, $serviceToken)) {
            return false;
        }

        $this->apiUrl = "https://www.sj.se/v{$this->http->FindPreg('/session-v(\d+)/', false, $sessionVersion)}/rest";
        $this->State['apiUrl'] = $this->apiUrl;

        $cookies = $this->http->GetCookies('www.sj.se', '/', true);
        $this->http->setCookie($sessionVersion, $sessionToken, ".www.sj.se");
        $this->http->setCookie('X-api.sj.se-service', $serviceToken, ".www.sj.se");

        if (isset($cookies['X-STBE'])) {
            $this->http->setCookie('X-STBE', $cookies['X-STBE'], "www.sj.se");
        } else {
            $this->logger->error("\$cookies['X-STBE'] not found");
        }

        $this->http->setCookie('notloggedinfired', "1", "www.sj.se");
        $this->http->setCookie('sjcookies', "true", ".sj.se");
        $sessionTypes = ["sessionTypes" =>
                             [
                                 [
                                     "session"    => ["name" => $sessionVersion, "token" => $sessionToken],
                                     "service"    => ["name" => "X-api.sj.se-service", "token" => $serviceToken],
                                     "persistent" => false,
                                     "deviceType" => "STATIONARY", "userType" => "TRAVELLER",
                                 ],
                             ],
        ];
        $this->http->setCookie('sessionTypes', urlencode(json_encode($sessionTypes)), ".www.sj.se");

        $this->http->setDefaultHeader('Accept', 'application/json, text/plain, */*');
        $this->http->setDefaultHeader('X-api.sj.se-language', 'en');
        $this->http->setDefaultHeader('CSRF-Token', 'undefined');

        try {
            $this->http->GetURL('https://ws.sessioncam.com/Record/config.aspx?url=https%3A%2F%2Fwww.sj.se%2Fen%2Fhome.html&sse=' . time() . date("B"));
        } catch (Facebook\WebDriver\Exception\UnknownErrorException $e) {
            $this->logger->error("Facebook\WebDriver\Exception\UnknownErrorException: " . $e->getMessage(), ['pre' => true]);
        }
        $sc = $this->http->getCookieByName('sc.ASP.NET_SESSIONID');

        /*
        if (!$sc) {
            $sc = "undefined";
        }
        $this->http->setCookie('sc.ASP.NET_SESSIONID', $sc, 'www.sj.se');
        */
        $this->http->GetURL('https://www.sj.se/libs/granite/csrf/token.json');

        return true;
    }

    private function parseItinerary(string $token)
    {
        $this->logger->notice(__METHOD__);
        $this->http->RetryCount = 0;
        $headers = [
            'Accept'               => 'application/json, text/plain, */*',
            'Content-Type'         => 'application/json;charset=UTF-8',
            'CSRF-Token'           => 'undefined',
            'X-api.sj.se-language' => 'en',
        ];
        $payload = [
            'cartToken' => $token,
        ];
        $this->http->PostURL($this->apiUrl . '/orders/carttokens', json_encode($payload), $headers);

        if ($error = $this->http->FindPreg('/"code":"ORDER_IS_LOCKED","reason":"(.+?)"/')) {
            $this->logger->error("The booking is locked. If the booking is open on another device/in another window, we recommend that you click on Done with the booking. Otherwise the booking will remain locked for 30 minutes.");
            $this->logger->error("Skipping itinerary: {$error}");

            return;
        }
        $resp = $this->http->JsonLog(null, 0, true);
        $newToken = ArrayVal($resp, 'cartToken');
        $this->http->GetURL(sprintf($this->apiUrl . '/orders/%s', $token), $headers);
        $data = $this->http->JsonLog(null, 3, true);

        $train = $this->itinerariesMaster->createTrain();
        // Conf
        $conf = ArrayVal($data, 'orderId');
        $this->logger->info(sprintf('[%s] Parse Itinerary #%s', $this->currentItin++, $conf), ['Header' => 3]);
        $train->addConfirmationNumber($conf, 'Order', true);
        // Total
        $train->price()->total($this->ArrayVal($data, ['totalPrice', 'amount']));
        // Currency
        $train->price()->currency($this->ArrayVal($data, ['totalPrice', 'currency']));
        // Passengers
        $names = [];

        foreach (ArrayVal($data, 'consumers', []) as $consumer) {
            $name = beautifulName(sprintf('%s %s',
                $this->ArrayVal($consumer, ['personName', 'firstName']),
                $this->ArrayVal($consumer, ['personName', 'lastName'])
            ));
            $names[] = trim($name);
        }
        $train->setTravellers(array_filter(array_unique($names)));
        // Segments
        $segments = ArrayVal($data, 'serviceGroups', []);

        $taxi = false;

        foreach ($segments as $segment) {
            $items = $this->ArrayVal($segment, ['detail', 'items'], []);
            $type = $this->ArrayVal($segment, ['type'], null);

            if ($type == 'TAXI') {
                $taxi = true;

                continue;
            }

            foreach ($items as $itemId => $item) {
                $seg = $train->addSegment();
                // Number
                if ($transportId = $this->ArrayVal($item, ['elements', 0, 'transportId'])) {
                    $seg->setNumber($transportId);
                }
                // ServiceName
                $seg->setServiceName($this->ArrayVal($item, ['serviceProducer', 'name']), false, true);
                // DepName
                $seg->setDepName($this->ArrayVal($item, ['departureLocation', 'name']));
                // DepCode
                $seg->setNoDepCode(true);
                // DepDate
                $depDate = strtotime(
                    $this->ArrayVal($item, ['departureTime', 'time']),
                    strtotime($this->ArrayVal($item, ['departureDate', 'date']))
                );
                $seg->setDepDate($depDate);
                // ArrName
                $seg->setArrName($this->ArrayVal($item, ['arrivalLocation', 'name']));
                // ArrCode
                $seg->setNoArrCode(true);
                // ArrDate
                $arrDate = strtotime(
                    $this->ArrayVal($item, ['arrivalTime', 'time']),
                    strtotime($this->ArrayVal($item, ['arrivalDate', 'date']))
                );
                $seg->setArrDate($arrDate);
                // Duration
                $seg->setDuration($this->ArrayVal($item, ['duration', 'duration']));
                // Seats
                $seats = $this->getSeats($data, $segment, $itemId + 1, 1);
                $seg->setSeats($seats);
            }
        }

        if ($taxi === true) {
            $transfer = $this->itinerariesMaster->createTransfer();
            // Conf
            $conf = ArrayVal($data, 'orderId');
            $this->logger->info(sprintf('[%s] Parse Itinerary #%s', $this->currentItin++, $conf), ['Header' => 3]);
            $transfer->addConfirmationNumber($conf, 'Order', true);
            // Total
            $transfer->price()->total($this->ArrayVal($data, ['totalPrice', 'amount']));
            // Currency
            $transfer->price()->currency($this->ArrayVal($data, ['totalPrice', 'currency']));
            // Passengers
            $names = [];

            foreach (ArrayVal($data, 'consumers', []) as $consumer) {
                $name = beautifulName(sprintf('%s %s',
                    $this->ArrayVal($consumer, ['personName', 'firstName']),
                    $this->ArrayVal($consumer, ['personName', 'lastName'])
                ));
                $names[] = trim($name);
            }
            $transfer->setTravellers(array_filter(array_unique($names)));
            // Segments
            $segments = ArrayVal($data, 'serviceGroups', []);
            $phones = [];

            foreach ($segments as $segment) {
                $items = $this->ArrayVal($segment, ['detail', 'items'], []);
                $type = $this->ArrayVal($segment, ['type'], null);

                if ($type != 'TAXI') {
                    continue;
                }

                foreach ($items as $itemId => $item) {
                    $seg = $transfer->addSegment();
                    // Booking reference
                    $transfer->addConfirmationNumber($this->ArrayVal($item, ['referenceNumber']), 'Booking reference', false);
                    $phones[] = $this->ArrayVal($item, ['customerPhone']);
                    // DepName
                    if ($item['departureLocation'] !== null) {
                        $seg->setDepName($this->ArrayVal($item, ['departureLocation', 'name']));
                    }

                    if ($item['departureAddress'] !== null) {
                        $seg->setDepAddress(
                            $this->ArrayVal($item, ['departureAddress', 'streetName'])
                            . " " . $this->ArrayVal($item, ['departureAddress', 'streetNameExtra'])
                            . ", " . $this->ArrayVal($item, ['departureAddress', 'cityName'])
                        );
                    }
                    // DepDate
                    $depDate = strtotime(
                        $this->ArrayVal($item, ['departureTime', 'time', 'time']),
                        strtotime($this->ArrayVal($item, ['departureTime', 'date', 'date']))
                    );
                    $seg->setDepDate($depDate);
                    // ArrName
                    if ($item['arrivalLocation'] !== null) {
                        $seg->setArrName($this->ArrayVal($item, ['arrivalLocation', 'name']));
                    }

                    if ($item['arrivalAddress'] !== null) {
                        $seg->setArrAddress(
                            $this->ArrayVal($item, ['arrivalAddress', 'streetName'])
                            . " " . $this->ArrayVal($item, ['arrivalAddress', 'streetNameExtra'])
                            . ", " . $this->ArrayVal($item, ['arrivalAddress', 'cityName'])
                        );
                    }
                    // ArrDate
                    $arrDate = strtotime(
                        $this->ArrayVal($item, ['arrivalTime', 'time', 'time']),
                        strtotime($this->ArrayVal($item, ['arrivalTime', 'date', 'date']))
                    );
                    $seg->setArrDate($arrDate);
                }// foreach ($items as $itemId => $item)
            }// foreach ($segments as $segment)

            // Phone
            if (!empty(!$phones)) {
                $transfer->program()->phone(array_unique($phones));
            }
        }// if ($taxi === true)

        if ($newToken) {
            $this->logger->notice("close connection");
            $this->http->DeleteURL(sprintf($this->apiUrl . '/orders/carttokens/%s?rollback=false', $newToken), $headers);
        } else {
            $this->sendNotification("token not found");
        }
        $this->http->RetryCount = 2;

        $this->logger->info('Parsed Itinerary:');
        $this->logger->info(var_export($train->toArray(), true), ['pre' => true]);

        if ($taxi === true) {
            $this->logger->info('Parsed Itinerary:');
            $this->logger->info(var_export($transfer->toArray(), true), ['pre' => true]);
        }
    }

    private function getSeats($data, $segment, $itemId, $elementId)
    {
        $this->logger->notice(__METHOD__);
        $groupId = ArrayVal($segment, 'id');

        if (!$groupId) {
            return [];
        }
        $seats = [];

        foreach (ArrayVal($data, 'placements', []) as $placement) {
            $sameSegment = (
                $this->ArrayVal($placement, ['serviceGroupKey', 'groupId']) == $groupId
                && $this->ArrayVal($placement, ['serviceGroupKey', 'itemId']) == $itemId
                && $this->ArrayVal($placement, ['serviceGroupKey', 'elementId']) == $elementId
            );

            if ($sameSegment) {
                $seats[] = $this->ArrayVal($placement, ['placementSpecification', 'seatNumber']);
            }
        }

        return array_values(array_filter($seats));
    }

    private function selenium()
    {
        $this->logger->notice(__METHOD__);
        $retry = false;
        $result = false;
        $selenium = clone $this;
        $this->http->brotherBrowser($selenium->http);
        $this->logger->notice("Running Selenium...");

        try {
            $selenium->UseSelenium();
            $selenium->useGoogleChrome();
            $selenium->disableImages();
            $selenium->useCache();

            $selenium->http->start();
            $selenium->Start();
            $selenium->http->GetURL('https://www.sj.se/en/home.html');
            $login = $selenium->waitForElement(WebDriverBy::xpath("//span[contains(text(), 'Log in')]"), 7, false);
            // save page to logs
            $this->http->SetBody($selenium->driver->executeScript('return document.documentElement.innerHTML'));
            $this->http->SaveResponse();

            if (!$login/* || !$pass || !$btn*/) {
                $this->logger->error("something went wrong");

                return false;
            }

            $result = true;
            $cookies = $selenium->driver->manage()->getCookies();

            foreach ($cookies as $cookie) {
                $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
            }
            // save page to logs
            $this->http->SetBody($selenium->driver->executeScript('return document.documentElement.innerHTML'));
            $this->http->SaveResponse();
        } catch (TimeOutException $e) {
            $this->logger->error("TimeOutException exception: " . $e->getMessage());
            $this->DebugInfo = "TimeOutException";
            // retries
            if (strstr($e->getMessage(), 'timeout')) {
                $retry = true;
            }
        }// catch (TimeOutException $e)
        finally {
            $this->logger->debug("close selenium");
            $selenium->http->cleanup();

            if ($retry && ConfigValue(CONFIG_SITE_STATE) != SITE_STATE_DEBUG) {
                throw new CheckRetryNeededException(3, 5);
            }
        }

        return $result;
    }

    private function checkErrors()
    {
        $this->logger->notice(__METHOD__);

        return false;
    }
}
