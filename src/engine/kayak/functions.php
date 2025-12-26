<?php

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Common\Parsing\Html;
use AwardWallet\Engine\ProxyList;
use AwardWallet\Schema\Parser\Common\Event;
use AwardWallet\Schema\Parser\Component\Field\Field;

class TAccountCheckerKayak extends TAccountCheckerExtended
{
    use PriceTools;
    use ProxyList;
    use SeleniumCheckerHelper;

    public const CONF_NO_XPATH_1 = ".//a[contains(@class, 'bookingReferenceNumberDropdown') and contains(@class, 'font-primary')]";
    public const CONF_NO_XPATH_2 = ".//div[contains(text(), 'Reference #')]/following-sibling::div[not(contains(text(), 'N/A'))]";
    public const COST_XPATH = './/div[not(contains(@class, "col-hidden-s"))]/div[contains(text(), "Cost")]/following-sibling::div[not(contains(text(), "N/A"))]';
    public const PASSENGER_XPATH = './/div[not(contains(@class, "col-hidden-s"))]/div[contains(text(), "Cost")]/following-sibling::div[not(contains(text(), "N/A"))]';
    public const DATE_XPATH = "parent::div/parent::div/preceding-sibling::div[contains(@class, 'DayTitle')]//div[contains(@class, 'dayTitleGrid')][1]/div[contains(@class, 'col')]/span";
    public const DATE_XPATH_2 = '/parent::div/parent::div/preceding-sibling::div[1]//div[contains(@class, "dayTitleGrid")][1]/div[contains(@class, "col")]/span';
    public const EVENT_NAME_XPATH = ".//div[contains(@class, 'eventTitle')]//span[contains(@class, 'font-primary')]";
    public const RESERVATION_DATE_XPATH = ".//div[contains(text(), 'Booked on')]/following-sibling::div[1]";
    public const SEATS_XPATH = ".//div[contains(@class, 'travellerGrid')]/div[2]/span[not(contains(text(), 'N/A'))]";
    /**
     * @var CaptchaRecognizer
     */
    private $recognizer;

    private $currentItin = 0;
    private $postForm = true;
    private $rand = 0;
    private $parsedHotels;

    private $loginFromReservation = true;
    private $loginSelenium = false;

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->http->setHttp2(true);
        $this->KeepState = true;
        $this->http->FilterHTML = false;
        $this->http->setDefaultHeader('Accept-Encoding', 'gzip');
        $this->http->setDefaultHeader('Connection', null);
    }

    public function IsLoggedIn()
    {
        $this->http->RetryCount = 0;
        $this->http->GetURL("https://www.kayak.com/in?a=awardwallet&url=/profile/account", [], 20);
        $this->http->RetryCount = 2;

        if (
            $this->loginSuccessful()
            && !strstr($this->http->currentUrl(), 'login?redir=')
            && !strstr($this->http->currentUrl(), '/security/check?out=%2Fprofile%2Faccount')
        ) {
            return true;
        }

        return false;
    }

    public function TuneFormFields(&$arFields, $values = null)
    {
        parent::TuneFormFields($arFields, $values);
        $cacheKey = 'kayak_countries';
        $result = Cache::getInstance()->get($cacheKey);

        if (($result !== false) && (count($result) > 1)) {
            $arFields["Login2"]["Options"] = $result;
        } else {
            $arFields["Login2"]["Options"] = [
                "" => "Select your country/location",
            ];
            $browser = new HttpBrowser("none", new CurlDriver());
            $browser->GetURL("https://www.kayak.com/");
            $countriesList = urldecode($browser->FindPreg("/\{\"countries\":(.+\]),\"currency\"/"));
            $countries = $browser->JsonLog($countriesList);
            $browser->Log("Total " . (is_array($countries) ? count($countries) : "none") . " links were found");
            $options = [];

            foreach ($countries as $country) {
                if (isset($country->href, $country->text)) {
                    $arFields["Login2"]["Options"][parse_url($country->href, PHP_URL_HOST)] = Html::cleanXMLValue($country->text);
                }
            }

            if (count($arFields['Login2']['Options']) > 1) {
                Cache::getInstance()->set($cacheKey, $arFields['Login2']['Options'], 3600);
            } else {
                $this->sendNotification("kayak regions are not found", 'all', true, $browser->Response['body']);
            }
        }
        $arFields["Login2"]["Value"] = (isset($values['Login2']) && $values['Login2']) ? $values['Login2'] : "www.kayak.com";
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();

        $this->http->GetURL("https://www.kayak.com/?a=awardwallet");

        if (strstr($this->http->currentUrl(), 'sitecaptcha') || strstr($this->http->currentUrl(), '/help/bots.html')) {
            if ($this->loginSelenium) {
                return $this->selenium();
            } else {
                $this->selenium();
            }
        }
//        if ($captcha = $this->parseEnterpriseCaptcha()) {
//            $data = '------WebKitFormBoundaryVT1VFj8B48GJiilW\r\nContent-Disposition: form-data; name="g-recaptcha-response"\r\n\r\n' . $captcha . '\r\n------WebKitFormBoundaryVT1VFj8B48GJiilW\r\nContent-Disposition: form-data; name="out"\r\n\r\n/in?a=awardwallet&url=/profile/account\r\n------WebKitFormBoundaryVT1VFj8B48GJiilW--\r\n';
//            $headers = [
//                "Accept"       => "*/*",
//                "Content-Type" => "multipart/form-data; boundary=----WebKitFormBoundaryVT1VFj8B48GJiilW",
//                "Referer"      => "https://www.kayak.com/sitecaptcha.html?out=%2Fin%3Fa%3Dawardwallet%26url%3D%2Fprofile%2Faccount",
//            ];
//            $this->http->RetryCount = 0;
//            $this->http->PostURL("https://www.kayak.com/h/bots/json/submit/sitecaptcha", $data, $headers);
//            $this->http->RetryCount = 2;
//            $this->DebugInfo = $this->http->JsonLog()->message ?? null;
//        }

        $xcsrf = $this->http->FindPreg('/"formtoken":"(.+?)",/');

        if (empty($xcsrf)) {
            return $this->checkErrors();
        }

        $headers = [
            'Accept'           => '*/*',
            //'Accept-Encoding'  => 'gzip, deflate, br',
            'Content-Type'     => 'application/x-www-form-urlencoded',
            'x-csrf'           => $xcsrf,
            'Referer'          => 'https://www.kayak.com/login?redir=%2Fprofile%2Faccount',
            'X-Requested-With' => 'XMLHttpRequest',
        ];

        $this->http->PostURL('https://www.kayak.com/auth/magiccode/v1/email/startLogin', [
            'email' => $this->AccountFields['Login'],
        ], $headers);

        $response = $this->http->JsonLog();

        if (isset($response->errors[0]->code) && $response->errors[0]->code == 'PASSWORD_AUTH_AVAILABLE') {
            $this->http->PostURL('https://www.kayak.com/k/run/auth/login', [
                'username' => $this->AccountFields['Login'],
                'passwd'   => $this->AccountFields['Pass'],
                'sticky'   => 'true',
            ], $headers);
        } elseif (isset($response->requestId)) {
            if ($this->isBackgroundCheck() && !$this->getWaitForOtc()) {
                $this->Cancel();
            }
            $this->State['xcsrf'] = $xcsrf;
            $this->State['requestId'] = $response->requestId;
            $this->AskQuestion("We just sent a 6-digit verification code to your email: {$this->AccountFields['Login']}. Please enter the code within 10 minutes.", null, 'question');
        }

        return true;
    }

    public function ProcessStep($step)
    {
        $answer = $this->Answers[$this->Question];
        unset($this->Answers[$this->Question]);
        $headers = [
            'Accept'           => '*/*',
            'Content-Type'     => 'application/x-www-form-urlencoded',
            'x-csrf'           => $this->State['xcsrf'],
            'Referer'          => 'https://www.kayak.com/login?redir=%2Fprofile%2Faccount',
            'X-Requested-With' => 'XMLHttpRequest',
        ];
        $this->http->PostURL('https://www.kayak.com/auth/magiccode/v1/login', [
            'code'      => $answer,
            'requestId' => $this->State['requestId'],
        ], $headers);
        $response = $this->http->JsonLog();

        if (isset($response->errors[0]->code) && $response->errors[0]->code == 'INVALID_CODE') {
            $this->AskQuestion($this->Question, "The code you entered is incorrect. Please try again.", "Question");

            return false;
        }

        if (isset($response->userInfo)) {
            $this->http->GetURL('https://www.kayak.com/profile/account');

            return true;
        }

        return false;
    }

    public function GetRedirectParams($targetURL = null)
    {
        $arg = parent::GetRedirectParams($targetURL);
        $arg['CookieURL'] = "https://" . $this->AccountFields["Login2"];
        $arg['SuccessURL'] = "https://" . $this->AccountFields["Login2"] . "/account";

        return $arg;
    }

    public function Login()
    {
        if ($this->loginSelenium) {
            // Too many requests. Please slow down.
            if ($message = $this->http->FindSingleNode("//div[contains(text(),'Too many requests. Please slow down.')]")) {
                throw new CheckRetryNeededException(3);
                //throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }

            if ($this->loginSuccessful()) {
                return true;
            }

            return false;
        }
        $response = $this->http->JsonLog();

        $status = $response->status ?? null;
        $message =
            $response->message
            ?? $response->errors[0]->description
            ?? null
        ;

        if (!empty($message)) {
            $this->logger->error($message);

            if (
                // Invalid username.
                // The username you have entered is not registered.
                strpos($message, 'The username you have entered is not registered') !== false
                // Incorrect password
                // Passwords are case-sensitive; make sure you have typed your password exactly as you created it and check your CAPS LOCK key.
                || strpos($message, '<b>Incorrect password.</b> <br />Passwords are case-sensitive;') !== false
                || strpos($message, '<b>Invalid user name and/or password.</b> <br />Passwords are case-sensitive;') !== false
                // We take the security of your account very seriously. To that end, we request that you choose a new password. Please click the Forgot your password? link to set a new password.
                || strpos($message, 'We take the security of your account very seriously. To that end, we request that you choose a new password') !== false
                || $message == 'User not found.'
                || $message == 'Invalid email address. Please check for typos.'
            ) {
                throw new CheckException(strip_tags($message), ACCOUNT_INVALID_PASSWORD);
            }
            // Password too short.
            // Passwords must contain at least 8 characters.
            if (strpos($message, 'You never chose a password. Click the "Forgot password?" link to set one.') !== false) {
                throw new CheckException('Password too short. Passwords must contain at least 8 characters.', ACCOUNT_INVALID_PASSWORD);
            }
            // Your account has been locked due to excessive authentication failures. Please click the Forgot your password? link to set a new password.
            if (strpos($message, 'Your account has been locked due to excessive authentication failures') !== false) {
                throw new CheckException(strip_tags($message), ACCOUNT_LOCKOUT);
            }

            $this->DebugInfo = $message;

            return false;
        }// if (!empty($message))

        if ($status === 0) {
            $this->http->GetURL('https://www.kayak.com/profile/account');
        }

        if ($this->loginSuccessful()) {
            return true;
        }

        return false;
    }

    public function Parse()
    {
        $this->parseProfile();
    }

    public function ParseItineraries()
    {
        $this->logger->notice(__METHOD__);
        $this->http->RetryCount = 0;
        $this->http->GetURL('https://www.kayak.com/trips?a=awardwallet');
        $this->http->LogHeaders = true;

        $xcsrf = $this->http->FindPreg('/"formtoken":"(.+?)",/');

        if (empty($xcsrf)) {
            $this->logger->error('x-csrf not found');

            return [];
        }
        $this->http->setDefaultHeader('x-csrf', $xcsrf);

        if (
            $this->http->FindSingleNode('//div[contains(@class, "noTripsMessage")]/h5[contains(normalize-space(.), "You have no upcoming trips")]')
            || $this->http->FindSingleNode('//div[contains(@class, "v-trips")]//h2[text()="No upcoming trips"]')
            || $this->http->FindSingleNode('//h4[normalize-space(.)="Import your bookings automatically"]/following::text()[normalize-space(.)!=""][1][starts-with(normalize-space(.),"Connect your email inbox")]')
            || $this->http->FindSingleNode('//h2[normalize-space(.) = "No upcoming trips"]')
        ) {
            return $this->noItinerariesArr();
        }

        $headers = [
            'Accept'           => '*/*',
            'x-requested-with' => 'XMLHttpRequest',
        ];
        $this->http->GetURL("https://www.kayak.com/i/api/trips/trip/v2/allTrips?a=awardwallet", $headers);
        $trips = $this->http->JsonLog(null, 1);

        if ($this->http->FindPreg('/\[\]/')) {
            $this->itinerariesMaster->setNoItineraries(true);

            return [];
        }
        $this->currentItin = 0;
        //$this->logger->debug(var_export($trips, true), ["pre" => true]);
        $i = 0;

        foreach ($trips as $trip) {
            $i++;

            if ($i > 100) {
                break;
            }

            if ($trip->isPast == false) {
                $this->parseItineraryJson($trip->tripId);
            } elseif ($this->ParsePastIts) {
                $this->parseItineraryJson($trip->tripId);
            } else {
                $this->logger->debug('Skip: past itinerery');
            }
        }
        $this->http->RetryCount = 2;

        return [];
    }

    public function GetConfirmationFields()
    {
        return [
            "ConfNo" => [
                "Caption"  => "Confirmation #/Record Locator",
                "Type"     => "string",
                "Size"     => 20,
                "Required" => true,
            ],
            "LastName"           => [
                "Caption"  => "Last Name",
                "Type"     => "string",
                "Size"     => 80,
                "Value"    => $this->GetUserField('LastName'),
                "Required" => true,
            ],
        ];
    }

    public function ConfirmationNumberURL($arFields)
    {
        return "https://www.kayak.com/bookings";
    }

    public function CheckConfirmationNumberInternal($arFields, &$it)
    {
        $this->http->setHttp2(false);
        $this->http->setRandomUserAgent();
        $this->selenium();
        $this->http->GetURL($this->ConfirmationNumberURL($arFields));

        $xcsrf = $this->http->FindPreg('/"formtoken":"(.+?)",/');

        if (empty($xcsrf)) {
            $this->sendNotification('failed to retrieve itinerary by conf #');

            return null;
        }

        $headers = [
            'x-requested-with' => 'XMLHttpRequest',
            'x-csrf'           => $xcsrf,
        ];
        $this->http->GetURL("https://www.kayak.com/i/api/trips/v1/bookings/search/byOrderIdNumberAndLastName?orderId={$arFields["ConfNo"]}&lastName=" . urlencode($arFields["LastName"]),
            $headers);
        $response = $this->http->JsonLog();

        if (!isset($response)) {
            $this->sendNotification('failed to retrieve itinerary by conf #');

            return null;
        }

        if ($response->anonUser === false) {
            $this->sendNotification('failed to retrieve itinerary by conf #');

            return null;
        }

        if (!isset($response->encodedOrderID) && $response->anonUser === true) {
            return 'No receipt was found for the values entered';
        }
        $this->sendNotification('check encodedOrderID // MI');

        if ($message = $this->http->FindSingleNode("//p[@class='receiptsection__textblock']/b")) {
            return $message;
        }

        if ($message = $this->http->FindSingleNode("(//div[@id='bookingNotFound']//b)[1]")) {
            return $message;
        }

        $headers = [
            'Accept'         => '*/*',
            'Content-Type'   => 'application/x-www-form-urlencoded; charset=UTF-8',
            'x-csrf'         => $xcsrf,
            'Referer'        => 'https://www.kayak.com/msbookings?order=' . $response->encodedOrderID,
            'Content-Length' => 0,
        ];
        $data = [
            'encodedOrderId'                => $response->encodedOrderID,
            'fullOrder'                     => 'false',
            'lastName'                      => $arFields["LastName"],
            'knownTravelerNumberApiVersion' => 'v2',
        ];
        $this->http->RetryCount = 0;
        $this->http->PostURL("https://www.kayak.com/api/whisky/V1/receipt?" . http_build_query($data), $data, $headers);
        $this->http->RetryCount = 2;

        $data = $this->http->JsonLog(null, 3, true);

        if (!empty($data)) {
            if (isset($data['orderInfo'], $data['orderInfo']['hotelInfo'])) {
                $it = $this->parseHotelByConfNoJson($data);
            } elseif (isset($data['orderInfo'], $data['flightTripInfo'])) {
                $it = $this->parseFlightByConfNoJson($data);
            } elseif (isset($data['orderInfo'], $data['orderInfo']['pickupDate'])) {
                $it = $this->parseRentalByConfNoJson($data);
            }
        }

        return null;
    }

    public function selenium()
    {
        $this->logger->notice(__METHOD__);
        $selenium = clone $this;
        $retry = false;
        $this->http->brotherBrowser($selenium->http);

        try {
            $this->logger->notice("Running Selenium...");
            $selenium->UseSelenium();
            $selenium->useChromium();
            //$selenium->useFirefox(SeleniumFinderRequest::FIREFOX_84);
            $selenium->disableImages();
            $selenium->http->saveScreenshots = true;
            $selenium->useCache();
            $selenium->http->start();
            $selenium->Start();

            $selenium->http->GetURL("https://www.kayak.com/in?a=awardwallet&url=/profile/account");

            $continueEmail = $selenium->waitForElement(WebDriverBy::xpath("//button[.//div[contains(text(),'Continue with email')]]"), 7);

            if (!$continueEmail) {
                return true;
            }

            if ($this->loginSelenium) {
                $continueEmail->click();
                sleep(rand(1, 2));
                $selenium->SaveResponse();
                $loginInput = $selenium->waitForElement(WebDriverBy::xpath("//div[contains(@class,'unified-login')]//input[@aria-label='Email address']"),
                    0);
                $button = $selenium->waitForElement(WebDriverBy::xpath("//div[contains(@class,'unified-login')]//button[.//div[contains(text(), 'Continue')]]"),
                    0);

                if ($loginInput) {
                    $loginInput->sendKeys($this->AccountFields['Login']);
                    sleep(rand(1, 2));
                    $button->click();
                    sleep(1);
                    $selenium->SaveResponse();
                    $passwordInput = $selenium->waitForElement(WebDriverBy::xpath("//div[contains(@class,'unified-login')]//input[@aria-label='Password']"),
                        0);

                    if (!$passwordInput) {
                        $this->savePageToLogs($selenium);

                        return true;
                    }
                    $button = $selenium->waitForElement(WebDriverBy::xpath("//div[contains(@class,'unified-login')]//button[.//div[contains(text(), 'Sign in')]]"),
                        0);
                    $passwordInput->sendKeys($this->AccountFields['Pass']);
                    sleep(rand(1, 2));
                    $button->click();
                    $this->logger->info('button clicked');
                    $selenium->waitForElement(WebDriverBy::id("OYIs-bannerTitle"), 10);
                }
                $this->savePageToLogs($selenium);
                $this->parseProfile();

                $selenium->http->GetURL("https://www.kayak.com/trips");
                sleep(3);
            }
            $cookies = $selenium->driver->manage()->getCookies();

            foreach ($cookies as $cookie) {
                $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
            }
            $this->logger->debug("[Current Selenium URL]: {$selenium->http->currentUrl()}");
            $this->savePageToLogs($selenium);
        } catch (ScriptTimeoutException $e) {
            $this->logger->error("ScriptTimeoutException exception: " . $e->getMessage());
            $this->DebugInfo = "ScriptTimeoutException";
            // retries
            if (strstr($e->getMessage(), 'timeout: Timed out receiving message from renderer')
                || strstr($e->getMessage(), 'timeout ')) {
                $retry = true;
            }
        }// catch (ScriptTimeoutException $e)
        finally {
            // close Selenium browser
            $selenium->http->cleanup();
        }
        // retries
        if ($retry && ConfigValue(CONFIG_SITE_STATE) != SITE_STATE_DEBUG) {
            throw new CheckRetryNeededException(3, 7);
        }

        return true;
    }

    private function parseProfile()
    {
        // Name
        $this->SetProperty('Name',
            $this->http->FindSingleNode("//span[contains(@id, 'account-name')]")
            ?? $this->http->FindPreg('/name\":\s*\"([^\"]+)\",\s*\"accountEmail\":/i')
            ?? $this->http->FindPreg('/"fullName":\s*\"([^\"]+)\",\s*\"accountEmail\":\s*"(.+?@.+?)"/i')
            ?? $this->http->FindPreg('/"accountName":\s*\"([^\"]+)\",\s*"fullName":null,\s*\"accountEmail\":\s*"(.+?@.+?)"/i')
        );
        // Email
        $this->SetProperty('Email', $this->http->FindSingleNode("//div[contains(text(), 'Account Email')]/following-sibling::div[1]"));

        if (isset($this->Properties['Email'])) {
            $this->SetBalanceNA();
        }
    }

    private function parseEnterpriseCaptcha()
    {
        $this->logger->notice(__METHOD__);

        $key = $this->http->FindPreg("/'sitekey'\s*:\s*'([^']+)/");

        if (!$key) {
            return false;
        }

        $this->recognizer = $this->getCaptchaRecognizer();
        $this->recognizer->RecognizeTimeout = 120;
        $parameters = [
            "pageurl" => $this->http->currentUrl(),
            "proxy"   => $this->http->GetProxy(),
        ];

        $parameters += [
            "version"   => "enterprise",
            "action"    => "ENTER",
            "min_score" => 0.7,
        ];

//            $postData = [
//                "type"         => "RecaptchaV3TaskProxyless",
//                "websiteURL"   => $this->http->currentUrl(),
//                "websiteKey"   => $key,
//                "minScore"     => 0.3,
//                "pageAction"   => "ENTER",
//                "isEnterprise" => true,
//            ];
//            $this->recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_ANTIGATE_API_V2);
//            $this->recognizer->RecognizeTimeout = 120;
//
//            return $this->recognizeAntiCaptcha($this->recognizer, $postData);

        return $this->recognizeByRuCaptcha($this->recognizer, $key, $parameters, true, 0, 1);
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);

        if (
            $this->http->FindSingleNode("//span[contains(text(), 'Sign out')]")
            || $this->http->FindPreg('/name\":\s*\"[^\"]+\",\s*\"accountEmail\":\s*"(.+?@.+?)"/i')
            || $this->http->FindPreg('/\",\s*\"userDisplayEmail\":\s*"(.+?@.+?)"/i')
        ) {
            return true;
        }

        return false;
    }

    private function checkErrors()
    {
        $this->logger->notice(__METHOD__);

        if ($this->http->FindPreg("/Http\/1\.1 Service Unavailable/ims")) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_INVALID_PASSWORD);
        }

        return false;
    }

    private function parseItineraryJson($tripId)
    {
        $this->logger->notice(__METHOD__);

        $headers = [
            'Accept'           => '*/*',
            'x-requested-with' => 'XMLHttpRequest',
        ];
        $tripId = trim($tripId, '!');
        $this->http->GetURL("https://www.kayak.com/i/api/trips/event/v1/allEvents/$tripId?a=awardwallet", $headers);
        $response = $this->http->JsonLog(null, 2);

        foreach ($response as $item) {
            switch ($item->eventType) {
                case 'flight':
                    $this->parseFlightJson($item);

                    break;

                case 'hotel':
                    $this->parseHotelJson($item);

                    break;

                case 'car':
                    $this->parseRentalJson($item);

                    break;

                case 'restaurant':
                case 'direction':
                    $this->parseEventJson($item);

                    break;

                default:
                    if (isset($data->bookingDetail->bookingDate)) {
                        $this->sendNotification("new {$item->eventType} // MI");
                    }

                    break;
            }
        }
    }

    private function parseFlightJson($data)
    {
        $f = $this->itinerariesMaster->createFlight();
        $confNo = $data->confirmationNumber ?? null;
        $this->logger->info(sprintf('[%s] Parse Flight #%s', $this->currentItin++, $confNo), ['Header' => 3]);

        // AS-IRUJYH,,JL-KDT2YR
        if (!isset($confNo)) {
            $f->general()->noConfirmation();
        } else {
            $confs = array_filter(explode(',', $confNo));

            foreach ($confs as $conf) {
                // Save time and hassle by completing Mexico’s Immigration and Customs Declaration online before departure:,AKZQQB
                $conf = preg_replace(['/^[A-Z\d]{2}-/', '/\s+/', '/^.+?Customs Declaration online before departure:,/'],
                    '', $conf);

                if (!empty($conf)) {
                    $f->general()->confirmation($conf);
                }
            }
        }

        if (isset($data->bookingDetail->bookingDate)) {
            $f->general()->date2($data->bookingDetail->bookingDate);
        }

        if ($data->isCancelled === true) {
            $f->general()->cancelled();
        }

        if (isset($data->bookingDetail->price)) {
            $f->price()->total($data->bookingDetail->price->price);
            $f->price()->currency($data->bookingDetail->price->currency);
        }

        if (isset($data->travelers)) {
            foreach ($data->travelers as $traveler) {
                $lastName = $traveler->lastName ?? '';
                $firstName = $traveler->firstName ?? '';
                $f->general()->traveller("$firstName $lastName");
            }
        }

        foreach ($data->legs as $leg) {
            foreach ($leg->segments as $segment) {
                $s = $f->addSegment();

                $s->airline()
                    ->name($segment->airlineCode ?? $segment->carrier)
                    ->number($this->http->FindPreg('/\d+/', false, $segment->flightNumber ?? $segment->code));

                $s->departure()
                    ->name($segment->departureLocation->localizedDisplayName)
                    ->code($segment->departureLocation->airportCode)
                    ->date2($segment->departureDate);

                $s->arrival()
                    ->name($segment->arrivalLocation->localizedDisplayName)
                    ->code($segment->arrivalLocation->airportCode)
                    ->date2($segment->arrivalDate);
            }
        }
        $this->logger->debug('Parsed itinerary:');
        $this->logger->debug(var_export($f->toArray(), true), ['pre' => true]);
    }

    private function parseHotelJson($data)
    {
        $h = $this->itinerariesMaster->createHotel();
        $confNo = $data->confirmationNumber ?? null;
        $this->logger->info(sprintf('[%s] Parse Hotel #%s', $this->currentItin++, $confNo), ['Header' => 3]);

        if (!isset($confNo)) {
            $h->general()->noConfirmation();
        } else {
            foreach (explode(',', $confNo) as $conf) {
                $h->general()->confirmation(preg_replace(['/^[A-Z\d]{2}-/', '/\s+/'], '', $conf));
            }
        }

        if (isset($data->bookingDetail->bookingDate)) {
            $h->general()->date2($data->bookingDetail->bookingDate);
        }

        if ($data->isCancelled === true) {
            $h->general()->cancelled();
        }

        if (isset($data->bookingDetail->price)) {
            $h->price()->total($data->bookingDetail->price->price);
            $h->price()->currency($data->bookingDetail->price->currency);
        }

        if (isset($data->travelers)) {
            foreach ($data->travelers as $traveler) {
                $lastName = $traveler->lastName ?? '';
                $firstName = $traveler->firstName ?? '';
                $h->general()->traveller("$firstName $lastName");
            }
        }

        $h->hotel()->name($data->hotelName);

        $address = $data->address->longAddress ?? null;

        if (empty($address)) {
            $address = $data->address->address ?? null;
        }

        if (empty($address) && isset($data->address->longAddress) && $data->address->longAddress == '') {
            $this->logger->debug("Ship: no address");
            $this->itinerariesMaster->removeItinerary($h);

            return;
        }
        $h->hotel()->address($address);

        if ($this->http->FindPreg(Field::PHONE_REGEXP, false, $data->hotelPhone ?? null)) {
            $h->hotel()->phone($data->hotelPhone, true, true);
        }

        $h->booked()->checkIn2($data->startDate);
        $h->booked()->checkOut2($data->endDate);

        $h->booked()->rooms($data->numberOfRooms);
        $this->logger->debug('Parsed itinerary:');
        $this->logger->debug(var_export($h->toArray(), true), ['pre' => true]);
    }

    private function parseRentalJson($data)
    {
        $r = $this->itinerariesMaster->createRental();
        $confNo = $data->confirmationNumber ?? null;
        $this->logger->info(sprintf('[%s] Parse Rental #%s', $this->currentItin++, $confNo), ['Header' => 3]);

        if (!isset($confNo)) {
            $r->general()->noConfirmation();
        } else {
            // G25535257,Opt-2319198
            $r->general()->confirmation(preg_replace('/,Opt-\d+$/', '', $confNo));
        }

        if (isset($data->bookingDetail->bookingDate)) {
            $r->general()->date2($data->bookingDetail->bookingDate);
        }

        if ($data->isCancelled === true) {
            $r->general()->cancelled();
        }

        if (isset($data->bookingDetail->price)) {
            $r->price()->total($data->bookingDetail->price->price);
            $r->price()->currency($data->bookingDetail->price->currency);
        }

        $r->extra()->company($data->agencyName, false, true);
        $r->car()->model($data->carDetails ?? null, false, true);
        $phone = $data->bookingDetail->phoneNumber ?? $data->agencyPhone ?? null;

        if ($phone != 'null') {
            $r->pickup()->phone($phone, false, true);
        }
        $r->pickup()->date2($data->startDate)
            ->location($data->pickUpAddress->address);
        $r->dropoff()->date2($data->endDate)
            ->location($data->dropOffAddress->address);

        foreach ($data->travelers as $traveler) {
            $lastName = $traveler->lastName ?? '';
            $firstName = $traveler->firstName ?? '';
            $r->general()->traveller("$firstName $lastName");
        }

        $this->logger->debug('Parsed itinerary:');
        $this->logger->debug(var_export($r->toArray(), true), ['pre' => true]);
    }

    private function parseEventJson($data)
    {
        $this->logger->notice(__METHOD__);
        $e = $this->itinerariesMaster->createEvent();
        $confNo = $data->confirmationNumber ?? null;
        $this->logger->info(sprintf('[%s] Parse ' . beautifulName($data->eventType) . ' #%s', $this->currentItin++, $confNo), ['Header' => 3]);

        if (!isset($data->confirmationNumber)) {
            $e->general()->noConfirmation();
        } else {
            $e->general()->confirmation($confNo/*, 'Confirmation'*/);
        }

        if (isset($data->bookingDetail->bookingDate)) {
            $e->general()->date2($data->bookingDetail->bookingDate);
        }

        if ($data->isCancelled === true) {
            $e->general()->cancelled();
        }

        if (isset($data->bookingDetail->price)) {
            $e->price()->total($data->bookingDetail->price->price);
            $e->price()->currency($data->bookingDetail->price->currency);
        }

        if (isset($data->travelers)) {
            foreach ($data->travelers as $traveler) {
                $lastName = $traveler->lastName ?? '';
                $firstName = $traveler->firstName ?? '';
                $e->general()->traveller("$firstName $lastName");
            }
        }

        switch ($data->eventType) {
            case 'restaurant':
                $e->place()->type(Event::TYPE_RESTAURANT);
                $e->place()->name($data->placeDescription);
                $e->booked()->start2($data->startDate);

                if (isset($data->endDate)) {
                    $e->booked()->end2($data->endDate);
                } else {
                    $e->booked()->noEnd();
                }
                $address = $data->address->rawAddress ?? $data->location->rawAddress ?? null;

                if ($address) {
                    $e->place()->address(strip_tags($address));
                } else {
                    $this->sendNotification('check no address // MI');
                    $this->itinerariesMaster->removeItinerary($e);
                }

                break;

            case 'direction':
                $e->place()->type(Event::TYPE_EVENT);
                $e->place()->name($data->name);
                $e->booked()->start2($data->startDate);

                if (isset($data->endDate)) {
                    $e->booked()->end2($data->endDate);
                } else {
                    $e->booked()->noEnd();
                }
                $e->place()->address($data->startAddress);

                break;
        }

        $this->logger->debug('Parsed itinerary:');
        $this->logger->debug(var_export($e->toArray(), true), ['pre' => true]);
    }

    private function parseHotelByConfNoJson(array $data)
    {
        $this->logger->notice(__METHOD__);
        $hotel = $this->itinerariesMaster->add()->hotel();
        $hotel->general()
            ->confirmation($data['displayConfirmationNumber'])
            ->cancellation(array_shift($data['orderInfo']['roomInfo']['room']['roomPolicy']['cancelPolicy']))
            ->date(strtotime($data['formattedCreateTime']));
        $hotel->price()
            ->total($data['totalCost'])
            ->cost($data['totalBase'])
            ->tax($data['totalFees'])
            ->currency($this->http->FindPreg("/(.+){$data['totalCost']}/", false, $data['totalCostDisplay']));
        $timeIn = trim($data['orderInfo']['hotelInfo']['checkinTime']);

        if ($this->http->FindPreg("/\d+:\d+[ap]$/", false, $timeIn)) {
            $timeIn .= 'm';
        }
        $timeOut = trim($data['orderInfo']['hotelInfo']['checkoutTime']);

        if ($this->http->FindPreg("/\d+:\d+[ap]$/", false, $timeOut)) {
            $timeOut .= 'm';
        }
        $hotel->booked()
            ->rooms($data['orderInfo']['numRooms'])
            ->guests($data['orderInfo']['numGuests'])
            ->checkIn(strtotime($timeIn, strtotime($data['orderInfo']['checkinDate'])))
            ->checkOut(strtotime($timeOut, strtotime($data['orderInfo']['checkoutDate'])));
        $hotel->hotel()
            ->name($data['orderInfo']['hotelInfo']['name'])
            ->address($data['orderInfo']['hotelInfo']['address'])
            ->phone($data['orderInfo']['hotelInfo']['phone'], true, true);

        $room = $hotel->addRoom();
        $room
            ->setType($data['orderInfo']['roomInfo']['room']['roomDescription'])
            ->setDescription($data['orderInfo']['roomInfo']['room']['longRoomDescription']);

        if ($data['orderInfo']['roomInfo']['room']['bookingCurrencyCode']) {
            $hotel->price()->currency($data['orderInfo']['roomInfo']['room']['bookingCurrencyCode']);
        }

        foreach ($data['travelers'] as $traveler) {
            $hotel->general()->traveller(preg_replace("/\s+/", ' ',
                $traveler['firstName'] . ' ' . $traveler['middleName'] . ' ' . $traveler['lastName']), true);
        }

        if ($deadline = $this->http->FindPreg("/Cancellations before (.+) \(Local Time\) are fully refundable/", false,
            $hotel->getCancellation())
        ) {
            $hotel->booked()->deadline(strtotime($deadline));
        }

        $this->logger->info('Parsed Itinerary:');
        $this->logger->info(var_export($hotel->toArray(), true), ['pre' => true]);

        return null;
    }

    private function parseFlightByConfNoJson(array $data)
    {
        $this->logger->notice(__METHOD__);
        $r = $this->itinerariesMaster->add()->flight();
        $r->general()
            ->confirmation($data['displayConfirmationNumber'])
            ->cancellation($data['orderInfo']['priceInfo']['cancellationPolicy'])
            ->date(strtotime($data['formattedCreateTime']));
        $totalCostForCurrency = str_replace([',', '.', ' '], '', $data['totalCost']);
        $totalCostDisplayForCurrency = str_replace([',', '.', ' '], '', $data['totalCostDisplay']);
        $r->price()
            ->total(PriceHelper::cost($data['totalCost']))
            ->cost($data['totalBase'] ?? null, false, true)
            ->tax($data['totalFees'] ?? null, false, true)
            ->currency($this->http->FindPreg("/(.+){$totalCostForCurrency}/u", false, $totalCostDisplayForCurrency));

        if ($data['orderInfo']['priceInfo']['avgPerPersonPrice']['currency']) {
            $r->price()->currency($data['orderInfo']['priceInfo']['avgPerPersonPrice']['currency']);
        }

        foreach ($data['travelers'] as $traveler) {
            $r->general()->traveller(preg_replace("/\s+/", ' ',
                $traveler['firstName'] . ' ' . $traveler['middleName'] . ' ' . $traveler['lastName']), true);
        }

        foreach ($data['flightTripInfo']['legs'] as $leg) {
            foreach ($leg['segments'] as $segment) {
                $s = $r->addSegment();
                $s->airline()
                    ->name($segment['airlineCode'])
                    ->number($segment['flightNumber']);
                $s->departure()
                    ->name($segment['originCity'] . ', ' . $segment['originCountry'])
                    ->code($segment['originCode'])
                    ->date(strtotime($segment['leaveTimeAirport']));
                $s->arrival()
                    ->name($segment['destinationCity'] . ', ' . $segment['destinationCountry'])
                    ->code($segment['destinationCode'])
                    ->date(strtotime($segment['arriveTimeAirport']));
                $duration = round($segment['duration'] / 60) . 'h' . round($segment['duration'] % 60) . 'm';
                $s->extra()
                    ->duration($duration)
                    ->aircraft($segment['equipmentType'])
                    ->cabin($segment['cabin']);

                if ($segment['miles'] !== -1) {
                    $this->sendNotification('check miles // ZM');
                }
            }
        }

        $this->logger->info('Parsed Itinerary:');
        $this->logger->info(var_export($r->toArray(), true), ['pre' => true]);

        return null;
    }

    private function parseRentalByConfNoJson(array $data)
    {
        $this->logger->notice(__METHOD__);
        $r = $this->itinerariesMaster->add()->rental();
        $otaConf = $data['displayProviderConfirmationNumber']['second'] ?? null;

        if ($otaConf) {
            $r->ota()->confirmation($otaConf, $data['displayProviderConfirmationNumber']['first'] ?? null);
        }

        if (!$otaConf) {
            $r->ota()->confirmation($data['displayConfirmationNumber']);
        }
        $r->ota()->phone($data['providerInfo']['phoneNumber']);
        // maybe it's better throw dictionary keywords->providerCode.
        //  when wrote: agencyName = Enterprise and it's work
        $r->program()->keyword($data['providerInfo']['agencyName']);
        $r->general()
            ->confirmation($data['displayConfirmationNumber'])
            ->date(strtotime($data['formattedCreateTime']));
        $r->price()
            ->total($data['totalCost'])
            ->cost($data['totalBase'])
            ->tax($data['totalFees'])
            ->currency($this->http->FindPreg("/(.+){$data['totalCost']}/", false, $data['totalCostDisplay']));

        if ($data['orderInfo']['carChoice']['bookingCurrency']) {
            $r->price()->currency($data['orderInfo']['carChoice']['bookingCurrency']);
        }

        foreach ($data['travelers'] as $traveler) {
            $r->general()->traveller(preg_replace("/\s+/", ' ',
                $traveler['firstName'] . ' ' . $traveler['middleName'] . ' ' . $traveler['lastName']), true);
        }

        $timeUp = trim($data['orderInfo']['pickupHourDisplay']);

        if ($this->http->FindPreg("/\d+:\d+[ap]$/", false, $timeUp)) {
            $timeUp .= 'm';
        }
        $timeOff = trim($data['orderInfo']['dropoffHourDisplay']);

        if ($this->http->FindPreg("/\d+:\d+[ap]$/", false, $timeOff)) {
            $timeOff .= 'm';
        }
        $r->pickup()
            ->date(strtotime($timeUp, strtotime($data['orderInfo']['pickupDate'])))
            ->location(
                $data['orderInfo']['pickupLocation']['address'] . ', ' .
                $data['orderInfo']['pickupLocation']['city'] . ', ' .
                $data['orderInfo']['pickupLocation']['regionCode'] . ' ' . ($data['orderInfo']['pickupLocation']['postalCode'] ?? '') . ', ' .
                $data['orderInfo']['pickupLocation']['country'])
            ->phone($data['orderInfo']['pickupLocation']['phone1'], true, true);
        $r->dropoff()
            ->date(strtotime($timeOff, strtotime($data['orderInfo']['dropoffDate'])))
            ->location(
                $data['orderInfo']['dropoffLocation']['address'] . ', ' .
                $data['orderInfo']['dropoffLocation']['city'] . ', ' .
                $data['orderInfo']['dropoffLocation']['regionCode'] . ' ' . ($data['orderInfo']['dropoffLocation']['postalCode'] ?? '') . ', ' .
                $data['orderInfo']['dropoffLocation']['country'])
            ->phone($data['orderInfo']['dropoffLocation']['phone1'], true, true);

        $r->car()
            ->type($data['orderInfo']['carChoice']['vehicleClass']['display'])
            ->model($data['orderInfo']['carChoice']['car']['fullName']);

        $preUrl = 'https://www.kayak.com/h/run/api/image?width=178&crop=true&url=';
        $ccurl = $data['orderInfo']['carChoice']['car']['carImages'][0]['ccurl'];

        if ($this->http->FindPreg("/^\/carimages/", false, $ccurl)) {
            $r->car()->image($preUrl . $ccurl);
        } elseif ($this->http->FindPreg("/^https?/", false, $ccurl)) {
            $r->car()->image($ccurl);
        } else {
            $this->sendNotification('check car image // ZM');
        }

        $this->logger->info('Parsed Itinerary:');
        $this->logger->info(var_export($r->toArray(), true), ['pre' => true]);

        return null;
    }
}
