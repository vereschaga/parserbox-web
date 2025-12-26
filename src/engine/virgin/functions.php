<?php

// test deploy

// refs #2006, #4025

use AwardWallet\Common\Parsing\Html;
use AwardWallet\Common\Parsing\MitmProxy\Port;
use AwardWallet\Common\Selenium\FingerprintFactory;
use AwardWallet\Common\Selenium\FingerprintRequest;
use AwardWallet\Engine\ProxyList;

class TAccountCheckerVirgin extends TAccountChecker
{
    use ProxyList;
    use SeleniumCheckerHelper;

    private const REWARDS_PAGE_URL = "https://www.virginatlantic.com/myflyingclub/dashboard";

    private $lastname = true;
    private $selenium = true;
    private $key = null;

    private $loginData;

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->setDefaultHeader("Upgrade-Insecure-Requests", "1");
        $this->http->setDefaultHeader("Connection", "keep-alive");
        $this->http->setDefaultHeader('Accept-Encoding', 'gzip, deflate, br');
        $this->http->setHttp2(true);

        /*
        if ($this->attempt == 2) {
            $this->setProxyBrightData();
        } elseif ($this->attempt == 1) {
            $proxy = $this->http->getLiveProxy("https://www.virginatlantic.com/us/en");

            if ($proxy) {
                $this->http->SetProxy($proxy);
            }
        } else {
//            $this->http->SetProxy($this->proxyReCaptcha()); // sometimes requests are blocked, but it's live proxy: 11 apr 2019
        }
        */

        // windows works only with direct connection - webrtc leak ?
        /*
        switch (random_int(5, 5)) {
            case 0:
                $this->logger->info("direct connection");

                break;

            case 1:
                $this->logger->info("vultr");
                $this->http->SetProxy("149.28.67.44:3128");

                break;

            case 2:
                $this->logger->info("proxyUK");
                $this->http->SetProxy($this->proxyUK());

                break;

            case 3:
                $this->logger->info("squid on lpm");
                $this->http->SetProxy("lpm.awardwallet.com:24001");

                break;

            case 5:
                /*
                $this->setProxyGoProxies(null, 'au');
                * /
                $this->setProxyBrightData();

                break;
        }
        */
        $this->setProxyGoProxies();

//        if ($this->AccountFields['UserID'] == 2110 || $this->AccountFields['UserID'] == 7) {
            $this->logger->debug("testing mitm");
            $this->useFirefoxPlaywright(SeleniumFinderRequest::FIREFOX_PLAYWRIGHT_104);
            $this->setKeepProfile(false);
            $this->setMitmProxy(
                (new Port())
                    ->setExternalProxies([$this->http->getProxyUrl()])
                    ->cacheUrls(Port::regexpFromExtensions(Port::EXTENSIONS_IMAGES_VIDEOS_AND_FONTS))
                    ->cacheUrls(Port::allStaticRegexp())
            );

//        }

        if (!isset($this->State["User-Agent"]) || $this->attempt > 1) {
            $this->http->setRandomUserAgent(7);
            $agent = $this->http->getDefaultHeader("User-Agent");

            if (!empty($agent)) {
                $this->State["User-Agent"] = $agent;
            }
        } else {
            $this->http->setUserAgent($this->State["User-Agent"]);
        }
    }

    public function IsLoggedIn()
    {
        $this->http->RetryCount = 0;
        $this->http->GetURL(self::REWARDS_PAGE_URL, [], 20);
        $this->http->RetryCount = 2;
        $name = Html::cleanXMLValue($this->http->FindSingleNode("//input[@id = 'firstName']/@value") . " " . $this->http->FindSingleNode("//input[@id = 'lastName']/@value"));

        if ($name) {
            // Name
            $this->SetProperty("Name", beautifulName($name));

            return true;
        }

        if ($this->http->Response['errorCode'] == 28) {
            $this->logger->info("network error");

            throw new CheckRetryNeededException(2, 0, "Network error");
        }

        return false;
    }

    public function GetRedirectParams($targetURL = null)
    {
        $arg = parent::GetRedirectParams($targetURL);
        //		$arg['CookieURL'] = "https://www.virginatlantic.com/custlogin/loginNow.action";
        $arg['CookieURL'] = "https://www.virginatlantic.com/";

        return $arg;
    }

    public function LoadLoginForm()
    {
        // refs #13956
        if (filter_var($this->AccountFields['Login'], FILTER_VALIDATE_EMAIL) !== false && ($this->AccountFields['Login2'] == '' || filter_var($this->AccountFields['Login2'], FILTER_VALIDATE_EMAIL) !== false)) {
            throw new CheckException("To update this Virgin Atlantic (Flying Club) account you need to fill in the 'Last Name' field. To do so, please click the Edit button which corresponds to this account. Until you do so we would not be able to retrieve your bonus information.", ACCOUNT_PROVIDER_ERROR);
        }/*review*/

        $this->AccountFields['Login'] = str_replace("&amp;", "&", $this->AccountFields['Login']);

        // Please check your login details, something isn't quite right
        if (filter_var($this->AccountFields['Login'], FILTER_VALIDATE_EMAIL) === false && !is_numeric($this->AccountFields['Login'])) {
            throw new CheckException("Please check your login details, something isn't quite right", ACCOUNT_INVALID_PASSWORD);
        }

        $this->http->removeCookies();
        $this->http->RetryCount = 0;
        $this->http->GetURL("https://www.virginatlantic.com/us/en");

        if (
            in_array($this->http->Response['code'], [403, 502])
            || strpos($this->http->Error, 'Network error 28 - Connection timed out after ') !== false
        ) {
            sleep(3);
            $this->http->removeCookies();
            $proxy = $this->http->getLiveProxy("https://www.virginatlantic.com/us/en");

            if ($proxy) {
                $this->http->SetProxy($proxy);
            } else {
                $this->http->SetProxy($this->proxyUK());
                /*
                $this->http->SetProxy($this->proxyAustralia());
                */
            }
            $this->http->removeCookies();
            $this->http->GetURL("https://www.virginatlantic.com/us/en");
        }

        if (in_array($this->http->Response['code'], [403, 502])) {
            return $this->checkErrors();
        }
        /*
        $headers = [
            "Origin"  => "https://www.virginatlantic.com",
            "Referer" => "https://www.virginatlantic.com/us/en",
        ];
        $this->http->PostURL("https://www.virginatlantic.com/login/loginPage", "refreshURL=", $headers);
        $this->http->RetryCount = 2;
        /**
         * prevent error.
         *
         * Sorry, the site is down right now, but we'll be back as soon as we can.
         * https://www.virginatlantic.com/gb/en/error/system-unavailable1.html
         * /
        if ($this->http->currentUrl() == 'https://www.virginatlantic.com/gb/en/error/system-unavailable1.html') {
            $this->http->GetURL("https://www.virginatlantic.com/login/loginPage");
        }

        $tokenId = $this->http->FindPreg("/login_tokenId(?:\":\"|&q;:&q;|=)([^\" &]+)/");
        $login_username = $this->http->FindPreg("/login_username(?:\":\"|=|&q;:&q;)([^\"&]+)/");
        $login_password = $this->http->FindPreg("/login_password(?:\":\"|=|&q;:&q;)([^\" &]+)/");
        $login_lastName = $this->http->FindPreg("/login_lastName(?:\":\"|=|&q;:&q;)([^\"&]+)/");

        if (!$tokenId || !$login_username || !$login_lastName || !$login_password) {
            return $this->checkErrors();
        }
        */

        if ($this->selenium === true || ($this->selenium === false && $this->attempt > 0)) {
            $this->selenium();

            return true;
        } else {
            $this->sendSensorData();
        }

        $data = [
            "rememberMe"        => "Y",
            "persistentLogin"   => "N",
            "refreshURL"        => "https://www.virginatlantic.com",
            "formNameSubmitted" => "login",
            "tokenId"           => $tokenId,
            $login_username     => str_replace("&amp;", "&", $this->AccountFields['Login']),
            $login_password     => substr($this->AccountFields['Pass'], 0, 20),
            $login_lastName     => "",
        ];
        // refs #13956
        if ($this->lastname) {
            $data[$login_lastName] = str_replace("’", "'", $this->AccountFields['Login2']);
        }
        $this->http->RetryCount = 0;
        $headers = [
            "Accept"       => "application/json",
            "Content-Type" => "application/json; charset=utf-8",
            "responseType" => "json",
            "Referer"      => "https://www.virginatlantic.com/login/loginPage",
        ];
        $this->http->PostURL("https://www.virginatlantic.com/login/login/loginCustomer", json_encode($data), $headers);
        $this->http->RetryCount = 2;
        /*
        if (!$this->http->ParseForm("loginForm_LoginPage"))
            return $this->checkErrors();
        $this->http->SetInputValue("username", str_replace("&amp;", "&", $this->AccountFields['Login']));
        // refs #13956
        if ($this->lastname)
            $this->http->SetInputValue("lastname", $this->AccountFields['Login2']);
        $this->http->SetInputValue("password", $this->AccountFields['Pass']);
        $this->http->SetInputValue("rememberMe", "true");
        $this->http->SetInputValue("rememberme", "on");
        $this->http->SetInputValue("Submit", ">");
        */

        return true;
    }

    public function Login()
    {
        $response = $this->http->JsonLog(
            $this->http->FindPreg("/var loginData = (\{[^;]+);/") ?? $this->loginData
        );

        if (($this->selenium === true || ($this->selenium === false && $this->attempt > 0)) && isset($response->loggedIn) && $response->loggedIn == true) {
            if ($this->selenium === true) {
                $this->key = 100;
            }

            $this->sendStatistic(true, $this->attempt > 0);

            return true;
        }

        if (isset($response->isLoggedIn) && $response->isLoggedIn == true) {
            $this->sendStatistic(true, $this->attempt > 0);

            return true;
        }
        // catch errors
        $message = $response->errors[0]->description
            ?? $this->http->FindSingleNode('
                //div[contains(@class, "overlayText")]
                | //p[contains(text(), "Sorry - you\'ve made too many mistakes trying to log in and your account is locked.")]
                | //div[contains(text(), "You haven\'t got an email address assigned to your account. Please add one to continue.")]
                | //div[contains(text(), "There are no security questions for your account. To continue, we need you to set some up.")]
                ', null, false)
            ?? null;

        if ($message) {
            $this->logger->error("[Error]: " . $message);
            // We're sorry, we can't process this right now. Please try again later ERROR_MSG_23
            if (
                $message == 'Service Unavailable - Please try again.'
                || strstr($message, 'We\'re sorry, we can\'t process this right now. Please try again late')
            ) {
                if (strstr($message, 'We\'re sorry, we can\'t process this right now. Please try again late')) {
                    $this->DebugInfo = 'need to upd sensor_data';
                }

                $this->sendStatistic(false, $this->attempt > 0);
                $delay = 10;

                if (ConfigValue(CONFIG_SITE_STATE) == SITE_STATE_DEBUG) {
                    $delay = 0;
                }

                throw new CheckRetryNeededException(3, $delay, "We're sorry, we can't process this right now. Please try again later");
            }
            // Try again - you need to use your Flying Club number or email address to login.
            if (
                $message == "Email Lookup Failed, So unable to authenticate."
                || $message == "Try again - you need to use your Flying Club number or email address to login. Forgotten your login details?"
            ) {
                throw new CheckException("Try again - you need to use your Flying Club number or email address to login.", ACCOUNT_PROVIDER_ERROR);
            }
            // The information you've entered isn't quite right
            if ($message == 'Password mismatch'
                || $message == 'Invalid Login credentials.'
                || $message == 'Incorrect account number entered'
                || $message == 'The information you\'ve entered is not valid. Please try again.') {
                throw new CheckException("The information you've entered isn't quite right", ACCOUNT_INVALID_PASSWORD);
            }
            // Hmm... The email address or last name you've entered aren't quite right. Please try to log in again using your Flying Club number or the email address registered to your account.
            if (
                strstr($message, 'Hmm... The email address or last name you\'ve entered aren\'t quite right.')
                || strstr($message, 'Oh dear - you\'ve entered an invalid Flying Club number or your password isn\'t quite right.')
                || $message == 'Hmm. Either your Flying Club number or password isn\'t quite right. Please try again.'
                || $message == 'Hmm... The details you’ve entered aren\'t quite right. Please try to log in again using your Flying Club number or the email address registered to your account.'
            ) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
                /*
                 * todo: maintenance mode
                throw new CheckException("We’re currently experiencing technical issues with some of our systems, and are aware our Flying Club website is currently unavailable. Our teams are working hard to rectify the issue, so we can get the site back up as soon as possible. We apologise to our members for any inconvenience caused.", ACCOUNT_PROVIDER_ERROR);
                */
            }
            // Sorry - you've made too many mistakes trying to log in and your account is locked.
            if (
                $message == 'You have exceeded the Password attempt limit'
                || $message == 'Sorry - you\'ve made too many mistakes trying to log in and your account is locked.'
            ) {
                throw new CheckException("Sorry - you've made too many mistakes trying to log in and your account is locked.", ACCOUNT_LOCKOUT);
            }

            if (
                $message == 'Please enter Flying Club number or email to log in. Forgotten your login details?'
                || $message == 'The information you\'ve entered isn\'t quite right. Need help getting logged in?'
            ) {
                $this->DebugInfo = $message;

                throw new CheckRetryNeededException(2, 5);
            }

            if ($message == 'Sorry, something went wrong. Please try again later ERROR_MSG_21') {
                $this->ErrorReason = self::ERROR_REASON_BLOCK;
                $this->DebugInfo = self::ERROR_REASON_BLOCK;

                $this->markProxyAsInvalid();

                throw new CheckRetryNeededException(2, 5, $message);

                return false;
            }

            // selenium

            // You haven't got an email address assigned to your account. Please add one to continue.
            if (($this->selenium === true || ($this->selenium === false && $this->attempt > 1)) && (
                    strstr($message, 'You haven\'t got an email address assigned to your account. Please add one to continue.')
                    // There are no security questions for your account. To continue, we need you to set some up.
                    || strstr($message, 'There are no security questions for your account. To continue, we need you to set some up.')
                )) {
                $this->throwProfileUpdateMessageException();
            }

            // refs #13956
            if ($message == 'Lastname is required with username.' && $this->AccountFields['Login2'] == '') {
                throw new CheckException("To update this Virgin Atlantic (Flying Club) account you need to fill in the 'Last Name' field. To do so, please click the Edit button which corresponds to this account. Until you do so we would not be able to retrieve your bonus information.", ACCOUNT_PROVIDER_ERROR);
            }/*review*/

            $this->DebugInfo = $message;

            return false;
        }// if (isset($response->errors[0]->description))
        /**
         * Add security questions.
         *
         * There are no security questions for your account. To continue, we need you to set some up.
         */
        elseif (isset($response->hasSQA, $response->isEmailValid)
            && (($response->hasSQA === false && $response->isEmailValid === true)
            /**
             * Add an email address.
             *
             * You haven't got an email address assigned to your account. Please add one to continue.
             */
            || ($response->hasSQA === false && $response->isEmailValid === false)
            || ($response->hasSQA === true && $response->isEmailValid === false))) {
            $this->throwProfileUpdateMessageException();
        }

        // selenium
        if (($this->selenium === true || ($this->selenium === false && $this->attempt > 1))
            && $this->http->FindSingleNode('//a[contains(text(), "correct the 1 item indicated.")]')
            && ($message = $this->http->FindSingleNode('//span[contains(text(), "Please check your login details, something isn\'t quite right") or contains(text(), "Please check your last name is entered correctly") or contains(text(), "Please check that you\'ve entered your password correctly")]'))) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        if ($this->selenium === true) {
            $this->checkErrors();

            throw new CheckRetryNeededException(4, 5);
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        $this->http->GetURL(self::REWARDS_PAGE_URL);

        if ($this->http->currentUrl() == 'https://www.virginatlantic.com/gb/en/error/system-unavailable1.html') {
            throw new CheckRetryNeededException(4, 5);
        }

        if (
            !$this->http->FindSingleNode("//td[contains(@class, 'MemberShipNo')]")
            && !$this->http->FindSingleNode("//h2[contains(text(), 'Your miles balance')]/following-sibling::div[1]")
            && $this->http->FindPreg("/location.href = \"\/custlogin\/loginNow\.action\"; \/\*APFI2 - 413\*\//")
        ) {
            throw new CheckRetryNeededException(3, ($this->attempt + 1) * 5);
        }

        // Balance - Your Virgin Points balance
        $this->SetBalance($this->http->FindSingleNode("//h2[contains(text(), 'Your') and contains(., 'balance')]/following-sibling::div[1]"));
        // Member Number
        $this->SetProperty("Number", $this->http->FindSingleNode("//td[contains(@class, 'MemberShipNo')]"));
        // Name
        $this->SetProperty("Name", beautifulName($this->http->FindPreg("/\"firstName\":\"([^\"]+)/") . " " . $this->http->FindPreg("/\",\"lastName\":\"([^\"]+)/")));
        // Member since
        $this->SetProperty("MemberSince", $this->http->FindSingleNode("//td[contains(@class, 'memberSince')]"));
        // Tier points
        $this->SetProperty("TierPoints", $this->http->FindSingleNode("//h2[contains(text(), 'Tier points')]/following-sibling::div[1]"));
        // ... member
        $this->SetProperty("EliteStatus", $this->http->FindSingleNode("//h2[contains(text(), 'Tier points')]/following-sibling::h3[1]", null, true, "/(.+) member/"));
        // Expiration date  // https://redmine.awardwallet.com/issues/15041#note-24
        $exp = $this->http->FindSingleNode("//th[contains(text(), 'Miles expiry date')]/following-sibling::td[not(contains(@class, 'noDisplay'))]");
        $this->logger->debug("Exp date: {$exp}");

        if ($exp = strtotime($exp)) {
            $this->SetExpirationDate($exp);
        }
    }

    public function ParseItineraries()
    {
        $result = [];
        $this->http->PostURL("https://www.virginatlantic.com/mytrips/findPnrList.action", [],
            ["X-Requested-With" => "XMLHttpRequest", "Accept" => "*/*", "Referer" => self::REWARDS_PAGE_URL]);

        $xpath = "//form[contains(@id, 'view_details_')]";
        $forms = $this->http->FindNodes($xpath);
        $itineraryForms = $preParseItinerary = [];

        foreach ($forms as $key => $value) {
            $this->logger->debug("key => {$key}");
            $this->http->ParseForm(null, "({$xpath})[" . ($key + 1) . "]");
            $itineraryForms[$key] = $this->http->Form;
            $this->logger->debug(var_export($this->http->Form, true), ["pre" => true]);
            // get what have
            if ($this->http->FindSingleNode("({$xpath})[" . ($key + 1) . "]/ancestor::article[1]")) {
                $root = $this->http->XPath->query("({$xpath})[" . ($key + 1) . "]/ancestor::article[1]")->item(0);
                $preParseItinerary[$key] = [
                    'pnr'         => $this->http->FindSingleNode("./div[1]/div[starts-with(normalize-space(),'Booking ref')]/span", $root),
                    'depCode'     => $this->http->FindSingleNode("./div[2]/div[normalize-space()!=''][1]", $root, false, "/\(([A-Z]{3})\) to/"),
                    'arrCode'     => $this->http->FindSingleNode("./div[2]/div[normalize-space()!=''][1]", $root, false, "/\(([A-Z]{3})\)\s*$/"),
                    'depDateTime' => str_replace(' at', ',', $this->http->FindSingleNode("./div[2]/div[normalize-space()!=''][2]", $root, false, "/Departs \(.+\)\s*(.+ at \d+:\d+.*)\s*$/")),
                    'flight'      => $this->http->FindSingleNode("./div[2]/div[normalize-space()!=''][3]", $root, false, "/Flight\s*(?:[A-Z\d][A-Z]|[A-Z][A-Z\d])\s*(\d+)\s*$/"),
                    'airline'     => $this->http->FindSingleNode("./div[2]/div[normalize-space()!=''][3]", $root, false, "/Flight\s*([A-Z\d][A-Z]|[A-Z][A-Z\d])\s*\d+\s*$/"),
                ];
            }
        }// foreach ($forms as $key => $value)

        $cntIts = count($itineraryForms);
        $this->logger->debug('Total ' . $cntIts . ' itineraries were found');

        foreach ($itineraryForms as $key => $itineraryForm) {
            if ($cntIts >= 3) {
                $rand = rand(1, 3);
                $this->increaseTimeLimit($rand);
                sleep($rand);
            }

            /*$this->http->FormURL = "https://www.virginatlantic.com/mytrips/findPnr.action";
            $this->http->Form = $itineraryForm;
            $this->http->unsetInputValue("flagFromMyProfile");
            $this->http->unsetInputValue("interstitial");

            $this->http->PostForm();*/
            // step 1
            $data = [
                'firstName'             => $this->http->Form['firstName'],
                'lastName'              => $this->http->Form['lastName'],
                'confirmationNo'        => $this->http->Form['confirmationNo'],
                'tab'                   => 'confirmationNo',
                'flagFromUpcomingTrips' => 'fromLoggedIn',
                'returnAction'          => '/mytrips/findPnrList.action',
            ];
            $headers = [
                'Accept'       => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8,application/signed-exchange;v=b3;q=0.7',
                'Content-Type' => 'application/x-www-form-urlencoded',
                'Origin'       => 'https://www.virginatlantic.com',
                'Referer'      => 'https://www.virginatlantic.com/myflyingclub/dashboard',
            ];
            $this->http->PostURL('https://www.virginatlantic.com/mytrips/findPnr', $data, $headers);

            // step 2
            $data = [
                'firstName'             => $this->http->Form['firstName'],
                'lastName'              => $this->http->Form['lastName'],
                'confirmationNo'        => $this->http->Form['confirmationNo'],
                'tab'                   => 'confirmationNo',
                'flagFromUpcomingTrips' => 'fromLoggedIn',
                'returnAction'          => '/mytrips/findPnrList.action',
                'interstitial'          => 'true',
            ];
            $headers = [
                'Accept'       => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8,application/signed-exchange;v=b3;q=0.7',
                'Content-Type' => 'application/x-www-form-urlencoded',
                'Origin'       => 'https://www.virginatlantic.com',
                'Referer'      => 'https://www.virginatlantic.com/mytrips/findPnr',
            ];
            $this->http->PostURL('https://www.virginatlantic.com/mytrips/findPnr.action', $data, $headers);

            /*
            if ($this->http->Response['code'] == 403) {
                $this->http->PostURL('https://www.virginatlantic.com/mytrips/findPnr.action', $data, $headers);

                if ($this->http->Response['code'] == 403) {
                    return [];
                }
            }
            */

            /*if ($this->http->Response['code'] == 403) {
                sleep(5);
                $this->http->FormURL = "https://www.virginatlantic.com/mytrips/findPnr.action";
                $this->http->Form = $itineraryForm;
                $this->http->SetInputValue("interstitial", "true");
                $this->http->SetInputValue("flagFromMyProfile", "Y");
                $this->http->unsetDefaultHeader("flagFromUpcomingTrips");
                $this->http->unsetDefaultHeader("returnAction");
                $this->http->unsetDefaultHeader("tab");
                $this->http->PostForm();
                $this->sendNotification('check retry request // MI');
            }*/

            if ($error = $this->http->FindSingleNode("//span[@id = 'tripFinderStrutsErr']")) {
                $this->logger->error($error);

                if (isset($preParseItinerary[$key])) {
                    $this->logger->debug('get from preParse');
                    $this->logger->debug(var_export($preParseItinerary[$key], true));
                    $referenceNum = $preParseItinerary[$key]['pnr'];
                    $this->logger->info("Parse Itinerary #{$referenceNum} (no details)", ['Header' => 3]);
                    $itinerary = ['Kind' => 'T'];
                    $itinerary['RecordLocator'] = $referenceNum;
                    $itinerary['TripSegments'][] = [
                        'DepCode'      => $preParseItinerary[$key]['depCode'],
                        'ArrCode'      => $preParseItinerary[$key]['arrCode'],
                        'DepDate'      => strtotime($preParseItinerary[$key]['depDateTime']),
                        'ArrDate'      => MISSING_DATE,
                        'AirlineName'  => $preParseItinerary[$key]['airline'],
                        'FlightNumber' => $preParseItinerary[$key]['flight'],
                    ];
                    $this->logger->debug('Parsed Itinerary:');
                    $this->logger->debug(var_export($itinerary, true), ['pre' => true]);
                    $result[] = $itinerary;
                }

                continue;
            }

            $lastName = $this->http->FindPreg('/firstName=(.+?)&/', false, $this->http->currentUrl());
            $firstName = $this->http->FindPreg('/lastName=(.+?)&/', false, $this->http->currentUrl());
            $confNo = $this->http->FindPreg('/recordLocator=(.+?)&/', false, $this->http->currentUrl());

            $this->http->PostURL('https://mytrips-api.vs.air4.com/v1/mytrips/travelreservations', [
                'encryptedConfirmationNum' => $confNo,
                'givenNames'               => $lastName,
                'surname'                  => $firstName,
                'using'                    => 'CONFIRMATION',
            ]);
            $response = $this->http->JsonLog();
            //$itinerary = $this->ParseItineraryV2();
        }// foreach ($itineraryForms as $itineraryForm)

        if (empty($result) && count($forms) === 0) {
            if ($this->http->FindSingleNode("//div[@class='missingFlightDialog borderLineTF']")
                && !$this->http->FindSingleNode("//a[@id='missFlight']")) {
                $this->itinerariesMaster->setNoItineraries(true);

                return [];
            }
        }

        return $result;
    }

    public function ConfirmationNumberURL($arFields)
    {
        return 'https://www.virginatlantic.com/my-trips/search';
    }

    public function CheckConfirmationNumberInternal($arFields, &$it)
    {
        $this->setProxyGoProxies();
        $this->confirmationSelenium($arFields);

//        //$proxy = $this->http->getLiveProxy($this->ConfirmationNumberURL($arFields));
//        //$this->http->SetProxy($proxy);
//        $this->http->GetURL($this->ConfirmationNumberURL($arFields));
//
//        /*$headers = [
//            '' => '',
//        ];*/
//        $data = [
//            'lastName' => '',
//            'firstName' => '',
//            'confirmationNo' => '',
//            'tab' => 'confirmationNo',
//            'returnAction' => '/mytrips/',
//            'originalSource' => 'Find My Trips',
//            'interstitial' => 'true',
//        ];
//        $this->http->PostURL('https://www.virginatlantic.com/mytrips/findPnr.action', $data);
//
//        if ($error = $this->http->FindSingleNode('//p[contains(text(), "Sorry, the site is down right now")]')) {
//            return $error;
//        }
//
//        if (!$this->http->ParseForm('findTripsStandAlone')) {
//            $this->sendNotification("failed to retrieve itinerary by conf #");
//
//            return null;
//        }
//        $this->http->SetInputValue("confirmationNo", $arFields['ConfNo']);
//        $this->http->SetInputValue("firstName", $arFields['FirstName']);
//        $this->http->SetInputValue("lastName", $arFields['LastName']);
//        $this->http->FormURL = $this->ConfirmationNumberURL($arFields);
        ////        $this->http->SetInputValue("interstitial", true);
//        if (isset($this->http->Form[''])) {
//            unset($this->http->Form['']);
//        }
//        $this->http->PostForm();
        // Sorry, we're unable to complete your request. Please check and try again.
        if ($error = $this->http->FindSingleNode("//span[@id = 'tripFinderStrutsErr']")) {
            return $error;
        }

        if ($error = $this->http->FindSingleNode('//p[contains(text(), "Sorry, the site is down right now")]')) {
            return $error;
        }
        // Whoops! We're sorry, we could not find any reservation with the information you have provided. Please check that you've entered everything correctly and try again. If you are looking for a receipt for past travel, please visit our Help Center page.
        if ($error = $this->http->FindSingleNode('//span[contains(text(), "re sorry, we could not find any reservation with the information you have provided.")]')) {
            return $error;
        }

        $it = $this->ParseItinerary();

        return null;
    }

    public function GetConfirmationFields()
    {
        return [
            "ConfNo" => [
                "Caption"  => "Booking ref",
                "Type"     => "string",
                "Size"     => 40,
                "Required" => true,
            ],
            "FirstName"        => [
                "Type"     => "string",
                "Size"     => 50,
                "Value"    => $this->GetUserField('FirstName'),
                "Required" => true,
            ],
            "LastName"         => [
                "Type"     => "string",
                "Size"     => 50,
                "Value"    => $this->GetUserField('LastName'),
                "Required" => true,
            ],
        ];
    }

    public function GetHistoryColumns()
    {
        return [
            "Date"             => "PostingDate",
            "Transaction Date" => "Info.Date",
            "Activity"         => "Description",
            "Mileage"          => "Miles",
            "Tier points"      => "Info",
            "Bonus Mileage"    => "Bonus",
        ];
    }

    public function ParseHistory($startDate = null)
    {
        $result = [];
        $this->logger->debug('[History start date: ' . ((isset($startDate)) ? date('Y/m/d H:i:s', $startDate) : 'all') . ']');
        $startTimer = $this->getTime();

        $this->http->PostURL("https://www.virginatlantic.com/acctactvty/manageacctactvty.action", []);

        if (!$this->http->ParseForm("chooseDateForm")) {
            return $result;
        }
        $this->http->SetInputValue("customSearch", "C");
        $this->http->SetInputValue("startDate", date("m/d/Y", strtotime("-3 year")));
        $this->http->SetInputValue("endDate", date("m/d/Y"));
        $this->http->PostForm();

        $page = 1;
//        do {
        $this->logger->debug("[Page: {$page}]");
//            if ($page > 1) {
//                $this->logger->debug("Loading next page...");
//            }
//            $page++;
        $startIndex = sizeof($result);
        $result = array_merge($result, $this->ParsePageHistory($startIndex, $startDate));
//        }
//        while (
//            $page < 30 &&
//            ($nextPage = $this->http->FindSingleNode("//div[contains(@class, 'paging_full_numbers')]/span/a[contains(@class, 'paginate_button') and normalize-space(text()) = '{$page}']"))
//        );

        $this->getTime($startTimer);

        return $result;
    }

    public function ParsePageHistory($startIndex, $startDate)
    {
        $result = [];
        $nodes = $this->http->XPath->query("//table[contains(@class, 'activityTable')]//tr[td[4]]");
        $this->logger->debug("Total {$nodes->length} items were found");

        if ($nodes->length > 0) {
            for ($i = 0; $i < $nodes->length; $i++) {
                $dateStr = $this->http->FindSingleNode("td[1]/text()[last()]", $nodes->item($i));
                $postDate = strtotime($dateStr);

                if (isset($startDate) && $postDate < $startDate) {
                    $this->logger->notice("break at date {$dateStr} ($postDate)");

                    break;
                }// if (isset($startDate) && $postDate < $startDate)
                $result[$startIndex]['Date'] = $postDate;
                $result[$startIndex]['Transaction Date'] = strtotime($this->http->FindSingleNode("td[2]/text()[last()]", $nodes->item($i)));
                $result[$startIndex]['Activity'] = $this->http->FindSingleNode("td[3]/text()[last()]", $nodes->item($i));

                if ($this->http->FindPreg('/Bonus/ims', false, $result[$startIndex]['Activity'])) {
                    $result[$startIndex]['Bonus Mileage'] = $this->http->FindSingleNode("td[4]/text()[last()]", $nodes->item($i));
                } else {
                    $result[$startIndex]['Mileage'] = $this->http->FindSingleNode("td[4]/text()[last()]", $nodes->item($i));
                }
                $result[$startIndex]['Tier points'] = $this->http->FindSingleNode("td[5]/text()[last()]", $nodes->item($i));
                $startIndex++;
            }// for ($i = $nodes->length-1; $i >= 0; $i--)
        } elseif ($message = $this->http->FindPreg("/(No data available in table)/ims")) {
            $this->logger->debug(">>> " . $message);
        }

        return $result;
    }

    public function combineHistoryBonusToMiles()
    {
        return true;
    }

    private function confirmationSelenium($arFields)
    {
        $this->logger->notice(__METHOD__);
        $selenium = clone $this;
        $retry = false;
        $this->http->brotherBrowser($selenium->http);

        try {
            $this->logger->notice("Running Selenium...");
            $selenium->UseSelenium();

            switch (random_int(0, 4)) {
                case 0:
                    $selenium->useChromium(SeleniumFinderRequest::CHROMIUM_80);

                    break;

                case 1:
                    $selenium->useChromePuppeteer(SeleniumFinderRequest::CHROME_PUPPETEER_100);

                    break;

                case 2:
                    $selenium->useGoogleChrome(SeleniumFinderRequest::CHROME_95);

                    break;

                case 3:
                    $selenium->useGoogleChrome(SeleniumFinderRequest::CHROME_100);

                    break;
            }

//            $selenium->seleniumOptions->antiCaptchaProxyParams = $this->getCaptchaProxy();
            $selenium->disableImages();
            $selenium->http->saveScreenshots = true;
            $selenium->http->start();
            $selenium->Start();

            $selenium->http->GetURL($this->ConfirmationNumberURL($arFields));
            $acceptAll = $selenium->waitForElement(WebDriverBy::xpath('//button[contains(@id, "privacy-btn-reject-all")]'), 5);

            if ($acceptAll) {
                $acceptAll->click();
                sleep(1);
            }
            $confirmationNo = $selenium->waitForElement(WebDriverBy::xpath('//input[@name = "confirmationNo"]'), 5);
            $firstName = $selenium->waitForElement(WebDriverBy::xpath('//input[@name = "firstName"]'), 0);
            $lastName = $selenium->waitForElement(WebDriverBy::xpath('//input[@name = "lastName"]'), 0);
            $findTripSearch = $selenium->waitForElement(WebDriverBy::xpath('//button[contains(@id, "findTripSearch")]'), 0);
            $this->savePageToLogs($selenium);

            if (!$confirmationNo || !$firstName || !$lastName || !$findTripSearch) {
                return false;
            }
            $confirmationNo->sendKeys($arFields['ConfNo']);
            $firstName->sendKeys($arFields['FirstName']);
            $lastName->sendKeys($arFields['LastName']);
            $this->savePageToLogs($selenium);
            $findTripSearch->click();

            sleep(1);

            if ($selenium->waitForElement(WebDriverBy::xpath('//h1[contains(., "Access Denied")]'), 7)) {
                $retry = true;
            }
            $success = $selenium->waitForElement(WebDriverBy::xpath('//span[contains(., "Your booking reference is")]'), 5);
            $this->savePageToLogs($selenium);

            if (!$success) {
                $this->sendNotification('check sleep // MI');
                sleep(10);
            }
            $this->savePageToLogs($selenium);
        } catch (UnknownServerException | SessionNotCreatedException | WebDriverCurlException | TimeOutException | NoSuchWindowException $e) {
            $this->logger->error("exception: " . $e->getMessage());
            $this->DebugInfo = "exception";
            $retry = true;
        } finally {
            // close Selenium browser
            $selenium->http->cleanup(); //todo

            // retries
            if ($retry && ConfigValue(CONFIG_SITE_STATE) != SITE_STATE_DEBUG) {
                $this->logger->debug("[attempt]: {$this->attempt}");

                throw new CheckRetryNeededException(3);
            }
        }

        return true;
    }

    private function selenium()
    {
        $this->logger->notice(__METHOD__);
        $selenium = clone $this;
        $result = false;
        $retry = false;
        $this->http->brotherBrowser($selenium->http);

        try {
            $this->logger->notice("Running Selenium...");
            $selenium->UseSelenium();
            $resolutions = [
                //                [1152, 864],
                [1280, 720],
                //                [1280, 768],
                [1280, 800],
                [1360, 768],
                [1366, 768],
                [1920, 1080],
                //                [1440, 900],
                //                [2560, 1440],
            ];
            $chosenResolution = $resolutions[array_rand($resolutions)];
//            $selenium->setScreenResolution($chosenResolution);

            if ($this->attempt == 2) {
//                $selenium->useChromePuppeteer();// TODO: not wrking now
                $selenium->useGoogleChrome(SeleniumFinderRequest::CHROME_95);
            } else {
                $selenium->useGoogleChrome(SeleniumFinderRequest::CHROME_94);
            }

            $selenium->seleniumOptions->addHideSeleniumExtension = false;
            $selenium->http->setUserAgent(null);

//            $selenium->useGoogleChrome();
//            $selenium->seleniumOptions->addHideSeleniumExtension = false;
//            $request = FingerprintRequest::chrome();
//            $request->browserVersionMin = \HttpBrowser::BROWSER_VERSION_MIN;
//            $fingerprint = $this->services->get(FingerprintFactory::class)->getOne([$request]);
//
//            if ($fingerprint !== null) {
//                $selenium->seleniumOptions->fingerprint = $fingerprint->getFingerprint();
//                $selenium->seleniumOptions->setResolution([$fingerprint->getScreenWidth(), $fingerprint->getScreenHeight()]);
//                $selenium->http->setUserAgent($fingerprint->getUseragent());
//            }
//            $selenium->useCache();
            $selenium->usePacFile(false);

//            $selenium->disableImages();
            $selenium->http->start();
            $selenium->Start();
            $selenium->http->saveScreenshots = true;
            $selenium->http->removeCookies();

            try {
                $selenium->http->GetURL("https://www.virginatlantic.com/us/en");
            } catch (TimeOutException $e) {
                $this->logger->error("TimeOutException: " . $e->getMessage());
                $selenium->driver->executeScript('window.stop();');
            }

            try {
                /*
                $selenium->http->GetURL("https://www.virginatlantic.com/login/loginPage");
                */
                $selenium->http->GetURL("https://www.virginatlantic.com/myflyingclub/dashboard");
            } catch (UnexpectedJavascriptException $e) {
                $this->logger->error("UnexpectedJavascriptException: " . $e->getMessage());

                sleep(5);
                $this->savePageToLogs($selenium);
            } catch (TimeOutException $e) {
                $this->logger->error("TimeOutException: " . $e->getMessage());
                $selenium->driver->executeScript('window.stop();');
            }

            $selenium->waitForElement(WebDriverBy::xpath('
                //input[@id = "userId" or @id = "signInName"]
                | //h1[contains(text(), "Access Denied")]
                | //button[contains(@class, "login-btn")]
                | //button[@aria-label="Flying Club"]
            '), 5);
            $this->savePageToLogs($selenium);

            $this->closePopup($selenium);

            $this->selectLanguage($selenium);

            $loginBtn = $selenium->waitForElement(WebDriverBy::xpath('//button[contains(@class, "login-btn") or contains(@class, "loginButton")] | //button[@aria-label="Flying Club"]'), 0);

            if ($loginBtn) {
                $loginBtn->click();

                if ($myAcc = $selenium->waitForElement(WebDriverBy::xpath('//a[contains(., "My account")]'), 2)) {
                    $this->savePageToLogs($selenium);
//                    $myAcc->click();
                    $selenium->http->GetURL("https://www.virginatlantic.com/login/loginPage");
                }

                $selenium->waitForElement(WebDriverBy::xpath('
                    //input[@id = "userId" or @id = "signInName"]
                    | //h1[contains(text(), "Access Denied")]
                '), 5);

                $this->savePageToLogs($selenium);

                $this->selectLanguage($selenium);

                // provider bug fix
                if (
                    !$this->http->FindSingleNode('//input[@id = "userId" or @id = "signInName"]')
                    && ($loginBtn = $selenium->waitForElement(WebDriverBy::xpath('//button[contains(@class, "login-btn") or contains(@class, "loginButton")]'), 0))
                ) {
                    $loginBtn->click();
                    $selenium->waitForElement(WebDriverBy::xpath('
                        //input[@id = "userId" or @id = "signInName"]
                        | //h1[contains(text(), "Access Denied")]
                    '), 5);
                    // save page to logs
                    $this->savePageToLogs($selenium);
                }
            }

            $this->closePopup($selenium);

            $login = $selenium->waitForElement(WebDriverBy::xpath('//input[@id = "userId" or @id = "signInName"]'), 0);
            $pass = $selenium->waitForElement(WebDriverBy::xpath('//input[@id = "password"]'), 0);

            if (!$pass) {
                $selenium->driver->executeScript('try { document.getElementById("password").style.zIndex = 9999; } catch(e) {}');
                $pass = $selenium->waitForElement(WebDriverBy::xpath('//input[@id = "password"]'), 0);
            }

            if (!$login || !$pass) {
                $this->logger->error("something went wrong");
                $this->logger->debug("[Current URL]: {$selenium->http->currentUrl()}");
                $this->savePageToLogs($selenium);

                if ($this->http->FindSingleNode('//h1[contains(text(), "Access Denied") or contains(text(), "Your connection is not private")]')) {
                    $retry = true;
                }
                // We’re currently experiencing technical issues with some of our systems
                if ($message = $selenium->waitForElement(WebDriverBy::xpath('//p[contains(text(), "We’re currently experiencing technical issues with some of our systems, and are aware our Flying Club website is currently unavailable.")]'), 0)) {
                    throw new CheckException($message->getText(), ACCOUNT_PROVIDER_ERROR);
                }
                // We're sorry you can't log into your Flying Club account at the moment. We're just carrying out some essential maintenance and will be back up and running as soon as possible. Please come back a bit later to try again.
                if ($message = $selenium->waitForElement(WebDriverBy::xpath('//p[contains(text(), "We\'re sorry you can\'t log into your Flying Club account at the moment. We\'re just carrying out some essential maintenance")]'), 0)) {
                    throw new CheckException($message->getText(), ACCOUNT_PROVIDER_ERROR);
                }

                return $result;
            }

            $this->closePopup($selenium);

            $this->logger->debug("enter Login");
            $login->click();
            $login->clear();
            $login->sendKeys($this->AccountFields['Login']);
            $this->logger->debug("enter Password");
            $pass->clear();
            $pass->sendKeys(substr($this->AccountFields['Pass'], 0, 20));

            if ($lastName = $selenium->waitForElement(WebDriverBy::xpath('//input[@id = "lastName"]'), 3)) {
                $this->logger->debug("enter lastName");
                $lastName->click();
                $lastName->clear();
                $lastName->sendKeys($this->AccountFields['Login2']);
            }

            if ($rememberMe = $selenium->waitForElement(WebDriverBy::xpath('//label[@for = "rememberMe_CheckBox"]'), 0)) {
                $rememberMe->click();
            }

            $btn = $selenium->waitForElement(WebDriverBy::xpath('//button[contains(text(), "Log in") or contains(@class, "loginButton") or @id = "continue"]'), 0);

            if (!$btn) {
                return $result;
            }

            // save page to logs
            $this->savePageToLogs($selenium);
            $this->logger->debug("click 'Log in'");

            try {
                $btn->click();
            } catch (StaleElementReferenceException $e) {
                $this->logger->error("Exception: " . $e->getMessage());
                sleep(2);
                $btn = $selenium->waitForElement(WebDriverBy::xpath('//button[contains(text(), "Log in") or contains(@class, "loginButton")]'), 0);
                $btn->click();
            }
            $this->logger->debug("waiting result");

            // wait balance or error
            $resultXpath = '
                //span[contains(@class, "pax-miles")]
                | //h1[contains(text(), "Access Denied")]
                | //h2[contains(text(), "Choose your security questions")]
                | //span[@id = "pwd_LoginPage-error"]
                | //span[@id = "usernm_LoginPage-error"]
                | //span[contains(text(), "Please check your last name is entered correctly")]
                | //div[contains(@class, "overlayText")]
                | //p[contains(text(), "Sorry - you\'ve made too many mistakes trying to log in and your account is locked.")]
                | //div[contains(text(), "You haven\'t got an email address assigned to your account. Please add one to continue.")]
                | //a[contains(text(), "correct the 1 item indicated.")]
                | //span[contains(text(), "This page isn’t working")]
                | //button[@data-testid="button-component" and span[contains(text(), "Flying Club")]]
            ';
            $selenium->waitForElement(WebDriverBy::xpath($resultXpath), 30);
            $balance = $selenium->waitForElement(WebDriverBy::xpath('//span[contains(@class, "pax-miles")]'), 0);
            $myAccountBtn = $selenium->waitForElement(WebDriverBy::xpath('//button[@data-testid="button-component" and span[contains(text(), "Flying Club")]]'), 0);

            // Last name needed
            if (!$lastName && !$balance && $selenium->waitForElement(WebDriverBy::xpath('//span[contains(text(), "Please check your last name is entered correctly")]'), 0)) {
                // save page to logs
                $this->savePageToLogs($selenium);

                if ($lastName = $selenium->waitForElement(WebDriverBy::id('lastName'), 0)) {
                    $lastName->click();
                    $lastName->clear();
                    $lastName->sendKeys($this->AccountFields['Login2']);
                }
                $btn = $selenium->waitForElement(WebDriverBy::xpath('//button[contains(text(), "Log in") or contains(@class, "loginButton")]'), 0);

                if (!$btn) {
                    return $result;
                }
                $selenium->driver->executeScript('setTimeout(function(){
                    delete document.$cdc_asdjflasutopfhvcZLmcfl_;
                    delete document.$cdc_asdjflasutopfhvcZLawlt_;
                }, 500)');
                $this->logger->debug("click 'Log in'");

                try {
                    $btn->click();
                } catch (StaleElementReferenceException $e) {
                    $this->logger->error("Exception: " . $e->getMessage());
                    sleep(2);
                    $btn = $selenium->waitForElement(WebDriverBy::xpath('//button[contains(text(), "Log in") or contains(@class, "loginButton")]'), 0);
                    $btn->click();
                }
                $this->logger->debug("waiting result");

                try {
                    $selenium->waitForElement(WebDriverBy::xpath($resultXpath), 30);
                    $balance = $selenium->waitForElement(WebDriverBy::xpath('//span[contains(@class, "pax-miles")]'), 0);
                    $myAccountBtn = $selenium->waitForElement(WebDriverBy::xpath('//button[@data-testid="button-component" and span[contains(text(), "Flying Club")]]'), 0);
                } finally {
                    // save page to logs
                    $this->savePageToLogs($selenium);
                }
            }

            // save page to logs
            $this->savePageToLogs($selenium);

            if ($selenium->waitForElement(WebDriverBy::xpath('
                    //h1[contains(text(), "Access Denied")]
                    | //span[contains(text(), "This page isn’t working")]
                '), 0)
            ) {
                $retry = true;
            }
            // save page to logs
            $this->savePageToLogs($selenium);

            if ($myAccountBtn) {
                $this->logger->debug('Seems that logged in. Going to profile page');
                $selenium->http->GetURL("https://www.virginatlantic.com/myflyingclub/dashboard");
                sleep(5);
                $this->logger->debug('Searching for balance');
                $balance = $selenium->waitForElement(WebDriverBy::xpath('//span[contains(@class, "pax-miles")]'), 7);
                // save page to logs
                $this->savePageToLogs($selenium);
            }

            if ($balance || $this->http->FindPreg("/\"loggedIn\":true,/")) {
                $this->loginData = $this->http->FindPreg("/var loginData = (\{[^;]+);/");
                /*
                $selenium->http->GetURL("https://www.virginatlantic.com/myflyingclub/dashboard");
                sleep(5);
                $selenium->waitForElement(WebDriverBy::xpath('//span[contains(@class, "pax-miles")]'), 7);
                */

                $popup = $selenium->waitForElement(WebDriverBy::xpath('//button[contains(@class,"virginPointsCloseModalButton removeFocus")]'), 10);

                if ($popup) {
                    sleep(1);
                    $this->savePageToLogs($selenium);
                    $selenium->driver->executeScript('document.querySelector(\'button.virginPointsCloseModalButton\').click();');
                    sleep(1);
                    $this->savePageToLogs($selenium);
                }
                $myFlights = $selenium->waitForElement(WebDriverBy::xpath('//li[@aria-label="My Flights unselected. Press enter to select."]'), 0);

                if ($myFlights) {
                    $selenium->driver->executeScript('document.querySelector(\'li[aria-label="My Flights unselected. Press enter to select."]\').click();');
                    //$myFlights->click();
                    $this->savePageToLogs($selenium);

                    $itinerary0 = $selenium->waitForElement(WebDriverBy::xpath('//a[@id="tripdetailslink_0"]'), 10);

                    if ($itinerary0) {
                        $selenium->driver->executeScript('document.querySelector(\'a#tripdetailslink_0\').click();');
                        sleep(15);
                        $this->savePageToLogs($selenium);
                    }
                }

                $cookies = $selenium->driver->manage()->getCookies();

                foreach ($cookies as $cookie) {
                    $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
                }
                $result = true;
            }// if ($name && $mlc_token == false)

            $this->logger->debug("[Current URL]: {$selenium->http->currentUrl()}");
        } catch (WebDriverCurlException | WebDriverException $e) {
            $this->logger->error("Exception: " . $e->getMessage());
            $this->DebugInfo = "Exception";
            $retry = true;
        } catch (StaleElementReferenceException | UnknownServerException $e) {
            $this->logger->error("Exception: " . $e->getMessage());
            $this->DebugInfo = "Exception";
            // retries
            if (
                /*
                strstr($e->getMessage(), 'element is not attached to the page document')

                || */ strstr($e->getMessage(), 'Permission denied to access property')
                || strstr($e->getMessage(), 'Connection refused (Connection refused)')
                || strstr($e->getMessage(), 'strictFileInteractability: false, timeouts:')
                /*
                || strstr($e->getMessage(), 'The element reference of')
                */
            ) {
                $retry = true;
            }
        }// catch (StaleElementReferenceException $e)
        finally {
            // close Selenium browser
            $selenium->http->cleanup(); //todo
            // retries
            if ($retry && ConfigValue(CONFIG_SITE_STATE) != SITE_STATE_DEBUG) {
                $this->logger->debug("[attempt]: {$this->attempt}");

                throw new CheckRetryNeededException(4, 5);
            }
        }

        return $result;
    }

    private function closePopup($selenium)
    {
        $this->logger->notice(__METHOD__);

        if ($agreeBtn = $selenium->waitForElement(WebDriverBy::xpath('//button[contains(text(), "Yes, I Agree")]'), 0)) {
            $agreeBtn->click();
            $this->savePageToLogs($selenium);
        }
    }

    private function selectLanguage($selenium)
    {
        $this->logger->notice(__METHOD__);

        if ($selectLanguageBtn = $selenium->waitForElement(WebDriverBy::xpath('//button[contains(text(), "United States - English")]'), 0)) {
            $selectLanguageBtn->click();
            sleep(1);
            $this->savePageToLogs($selenium);
        }
    }

    private function checkErrors()
    {
        $this->logger->notice(__METHOD__);
        $this->logger->debug("[Current URL]: {$this->http->currentUrl()}");

        if (isset($this->http->Response['code'])) {
            $this->logger->debug("[HTTP Code]: {$this->http->Response['code']}");
        }
        // Sorry, the site is down right now, but we'll be back as soon as we can.
//        if ($message = $this->http->FindSingleNode('//p[contains(text(), "Sorry, the site is down right now, but we\'ll be back as soon as we can.")]'))
//            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        /*
         * Hmm, not sure what happened there. Could be you clicked on an old link, spelled something wrong or
         * we've just lost that page in all the excitement.
         * Let's get you back on track and on your way to somewhere fabulous.
         */
        if (($message = $this->http->FindSingleNode('//p[contains(text(), "Hmm, not sure what happened there. Could be you clicked on an old link, spelled something wrong")]'))
            && $this->http->currentUrl() == 'http://www.virginatlantic.com/content/www/en_US/system-unavailable.xs.rpt.html') {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        // Sorry, the site is down right now, but we'll be back as soon as we can.
        if ($this->http->FindSingleNode('//p[contains(., "Sorry, the site is down right now, but we\'ll be back as soon as we can.")]')) {
            throw new CheckException("Sorry, the site is down right now, but we'll be back as soon as we can.", ACCOUNT_PROVIDER_ERROR);
        }

        if (
            (in_array($this->http->currentUrl(), [
                'https://www.virginatlantic.com/login/login/loginCustomer',
                'https://www.virginatlantic.com/gb/en',
                'https://www.virginatlantic.com/us/en',
                'https://www.virginatlantic.com/login/loginPage',
            ]))
            && in_array($this->http->Response['code'], [403, 502])) {
            $this->DebugInfo = 403;
            $this->ErrorReason = self::ERROR_REASON_BLOCK;
            $this->markProxyAsInvalid();

            throw new CheckRetryNeededException(3, 3);
        }

        if ($this->http->FindSingleNode("//img[contains(@alt, 'An error has occured with this field, please correct')]/@alt")
            // hard code (pass: "❽❹❹❽❹❷❻❹")
            || $this->AccountFields['Login'] == '00701374001'
            || $this->AccountFields['Login'] == '605501510'
            || $this->AccountFields['Pass'] == '●●●●●●●●'
            || $this->AccountFields['Pass'] == '❾❼❾❹❻❹❷❺❹❸❾❾'
            || stristr($this->AccountFields['Pass'], '❶')
            || stristr($this->AccountFields['Pass'], '<@#~]{\'~')) {
            throw new CheckException("Incorrect username or password.", ACCOUNT_INVALID_PASSWORD);
        }/*checked*/

        return false;
    }

    private function ParseItineraryV2(): array
    {
        $this->logger->notice(__METHOD__);

        return [];
    }

    private function ParseItinerary(): array
    {
        $this->logger->notice(__METHOD__);
        $result = ['Kind' => 'T'];
        $referenceNum = $this->http->FindSingleNode("//span[contains(@class, 'bcReferenceNum')]");

        $this->logger->info("Parse Itinerary #{$referenceNum}", ['Header' => 3]);

        $result['RecordLocator'] = $referenceNum;
        $result['Passengers'] = array_map(function ($elem) {
            return beautifulName($elem);
        }, $this->http->FindNodes("//div[contains(@class, 'PassDetailsBlock')]/div[contains(@class, 'passengersNameUpper')]", null, "/^(.+?)\s*(?:\(|$)/"));
        $result['TicketNumbers'] = $this->http->FindNodes("//div[contains(@class, 'PassDetailsBlock')]//div[contains(text(), 'E-ticket')or contains(text(), 'eTicket')]/span[contains(@class, 'number')]");
        $result['AccountNumbers'] = $this->http->FindNodes("//span[@id = 'frequentFlyerNumberTrip']");

        $segments = $this->http->XPath->query("//div[contains(@id, 'itin_')]");
        $this->logger->debug("Total {$segments->length} segments were found");
        $invalidSegment = false;
        $result['TripSegments'] = [];
        $curveCodes = ['OPE', 'OPB', 'OPE', 'NTK', 'REM', 'VVD', 'VOU', 'UCH'];

        foreach ($segments as $seg) {
            $segment = [];
            // FlightNumber and AirlineName
            $flightNumberStr = $this->http->FindSingleNode(".//p[contains(@class, 'flightNumber')]/text()[last()]", $seg);

            if ($this->http->FindPreg('/^[A-Z]{2}$/', false, $flightNumberStr)) {
                $depTime = $this->http->FindSingleNode('.//span[contains(@class, "departTimeFormat")]', $seg);
                $arrTime = $this->http->FindSingleNode('.//span[contains(@class, "arraivalTimeFormat")]', $seg);

                if ($depTime === '12:00 AM' && $arrTime === '12:00 AM') {
                    $this->logger->error('Skipping invalid segment');
                    $invalidSegment = true;
                } else {
                    $this->sendNotification('check invalid segment // MI');
                }

                continue;
            }
            $flightNumber = $this->http->FindPregAll('#(?<airlineName>\w{2})\s*(?<flightNumber>\d+)#i', $flightNumberStr, PREG_SET_ORDER);
            $segment['AirlineName'] = $flightNumber[0]['airlineName'] ?? null;
            $segment['FlightNumber'] = $flightNumber[0]['flightNumber'] ?? null;
            // DepName, DepCode, ArrName, ArrCode
            $departReturnMainText = $this->http->FindSingleNode(".//p[contains(@class, 'departReturnMainText')]", $seg);
            $locations = $this->http->FindPregAll('#(?<depName>[^\(]*)\s+\((?<depCode>\w{3})\)\,\s*[a-z]+\s*to\s+(?<arrName>.*)\s+\((?<arrCode>\w{3})\)#ims', $departReturnMainText, PREG_SET_ORDER);

            if (!$locations) {
                $locations = $this->http->FindPregAll('#\((?<depCode>\w{3})\)\,\s*to\s+(?<arrName>.*)\s+\((?<arrCode>\w{3})\)#ims', $departReturnMainText, PREG_SET_ORDER);
            }

            if (!$locations) {
                $locations = $this->http->FindPregAll('#(?<depName>[^\(]*)\s+\((?<depCode>\w{3})\)\,\s*[a-z]+\s*to\s+\((?<arrCode>\w{3})\)#ims', $departReturnMainText, PREG_SET_ORDER);
            }

            if (!$locations) {
                $locations = $this->http->FindPregAll('#\((?<depCode>\w{3})\)\,\s*to\s*\((?<arrCode>\w{3})\)#ims', $departReturnMainText, PREG_SET_ORDER);
            }
            $segment['DepName'] = $locations[0]['depName'] ?? $locations[0]['depCode'] ?? null;
            $segment['DepCode'] = $locations[0]['depCode'] ?? null;
            $segment['ArrName'] = $locations[0]['arrName'] ?? $locations[0]['arrCode'] ?? null;
            $segment['ArrCode'] = $locations[0]['arrCode'] ?? null;

            if (!$segment['FlightNumber'] || !$segment['DepCode']) {
                $this->sendNotification('check invalid segment // MI');

                continue;
            }
            $xpath = 'preceding-sibling::input[@class = "itineraryFlags" and contains(@ftnum, ' . $segment['FlightNumber'] . ') and @origcode = "' . $segment['DepCode'] . '" and @destcode = "' . $segment['ArrCode'] . '"]';
            $segmentid = $this->http->FindSingleNode($xpath . "/@segmentid", $seg);
            $legid = $this->http->FindSingleNode($xpath . "/@legid", $seg);
            $this->logger->debug("segmentid: {$segmentid} / legid: {$legid}");

            if (!$legid || !$segmentid) {
                $this->sendNotification('check invalid segment // MI');

                continue;
            }
            // Seats
            $segment['Seats'] = $this->http->FindNodes("//form[input[@name = 'legId' and @value = '{$legid}'] and input[@name = 'segmentNumber' and @value = '{$segmentid}']]/preceding-sibling::span[contains(@class, 'seatValignT')]/span[not(contains(., 'class'))]");
            // DepDate
            $depTime = $this->http->FindSingleNode($xpath . "/@scheddeptime", $seg);
            $depDate = $this->http->FindSingleNode($xpath . "/@depdate", $seg, true, "/\,\s*(.+)/") . " " . $depTime;
            $this->logger->debug("DepDate: {$depDate}");

            if (!empty(trim($depDate)) && strtotime($depDate)) {
                $segment['DepDate'] = strtotime($depDate);
            }
            // ArrDate
            $arrTime = $this->http->FindSingleNode($xpath . "/@schedarrtime", $seg);
            $arrDate = $this->http->FindSingleNode($xpath . "/@arrdate", $seg, true, "/\,\s*(.+)/") . " " . $arrTime;
            $this->logger->debug("ArrDate: {$arrDate}");

            if (!empty(trim($arrDate)) && strtotime($arrDate)) {
                $segment['ArrDate'] = strtotime($arrDate);
            }
            // Duration
            // 7.42 = 7hr 42m
            if ($duration = $this->http->FindSingleNode($xpath . "/@flighttime", $seg)) {
                $segment['Duration'] = sprintf("%0shr %0sm", (int) $duration, number_format(fmod($duration, 1) * 100, 0));
            }

            $segment['TraveledMiles'] = $this->http->FindSingleNode(".//p[contains(@class, 'flightmiles')]/span", $seg);
            $segment['Aircraft'] = $this->http->FindSingleNode(".//span[@class = 'aircraftName']", $seg);
            $segment['Cabin'] = $this->http->FindSingleNode(".//div[contains(@class, 'flightStatusClass')]/span[
                not(contains(., 'Air'))
                and not(contains(., 'All '))
                and not(contains(., 'Operated by '))
            ]", $seg);

            if (empty($segment['Cabin'])) {
                $segment['Cabin'] = $this->http->FindSingleNode(".//span[contains(@class, 'fsrSmallFlightText')]/span[contains(., 'Cabin Class')]/following-sibling::span", $seg);
            }
            $segment['Operator'] = $this->http->FindSingleNode(".//div[contains(@class, 'fsrSmallFlightText')][starts-with(normalize-space(),'Operated by')]", $seg, false, "/Operated by\s*(.+)?(?:\s+DBA\s+|$)/");

            if (in_array($segment['DepCode'], $curveCodes) && in_array($segment['ArrCode'], $curveCodes)
                && ($segment['DepDate'] === $segment['ArrDate'])
                && $segment['Cabin'] === "YY YY"
                && $segment['AirlineName'] === 'YY' && $segment['FlightNumber'] === '101'
            ) {
                //$segment = ['Cancelled' => true];
                $this->logger->error('Flight on hold or something');
                $invalidSegment = true;

                continue;
            }
            $result['TripSegments'][] = $segment;
        }// foreach ($segments as $seg)

        if (count($result['TripSegments']) === 0 && $invalidSegment) {
            $this->logger->error('Skipping invalid flight');

            return [];
        }
        $allCancelled = count($result['TripSegments']) > 0;

        foreach ($result['TripSegments'] as $i => $seg) {
            if (!isset($seg['Cancelled']) || !$seg['Cancelled']) {
                $allCancelled = false;
            } else {
                unset($result['TripSegments'][$i]);
            }
        }

        if ($allCancelled) {
            unset($result['TripSegments']);
            $result['Cancelled'] = true;
        }

        $this->logger->debug('Parsed Itinerary:');
        $this->logger->debug(var_export($result, true), ['pre' => true]);

        return $result;
    }

    private function getSensorData($secondSensor = false)
    {
        $sensorData = [
            // 0
            null,
            // 1
            "7a74G7m23Vrp0o5c9273211.69-1,2,-94,-100,Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:90.0) Gecko/20100101 Firefox/90.0,uaend,11059,20100101,en-US,Gecko,0,0,0,0,400471,7734012,1536,871,1536,960,1536,281,1536,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:1,sc:0,wrc:1,isc:138,vib:1,bat:0,x11:0,x12:1,6002,0.709122492354,813808867006,0,loc:-1,2,-94,-101,do_en,dm_en,t_dis-1,2,-94,-105,-1,2,-94,-102,-1,2,-94,-108,-1,2,-94,-110,-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://www.virginatlantic.com/us/en-1,2,-94,-115,1,32,32,0,0,0,0,1,0,1627617734012,-999999,17411,0,0,2901,0,0,3,0,0,07FCAAD49AF98A107D14950C32CE0B56~-1~YAAQoJ0ZuMZz2+x6AQAAzKKT9QbxmuRzmwyVE6ATJNB+BbGgfmzvFGVyjTi5wThw7WW3m+NOud6xbf00d1RaUbuWComUujYZ3UevJeYtxATZPGvFvTtRZJxGGjaFhCCQLTYYkRIfOXuliao3JjDN4VjDneXD8PjmAqGUnHxicp2Ienblz6dfg/wvRVrQouJMBouyyXWd31wyyoNMl2lXrJJWDbNkP+SzwUYK5v2WAk3dm9aJrqkvN/rSdhC9UC/ez7BRbwBKx9f0oeX1RQf2XlmU3VQn/NOohSoF8BlrL1QSozwZyMxw2OsqRsO2UxWKkPwy9YkS6q/vS9SbkJ0qK2+YLorr4Los2R9TaTwZj0DFQ5LEu0w3g9UVfVQbM1mpKLNBldMMETnn5qFnhBZz5IJhTcBTR9weL7nYD+eD+fU9~0~-1~1624874495,40626,-1,-1,26067385,PiZtE,72638,50-1,2,-94,-106,0,0-1,2,-94,-119,-1-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,-1,2,-94,-126,-1,2,-94,-127,8-1,2,-94,-70,-1-1,2,-94,-80,94-1,2,-94,-116,116010153-1,2,-94,-118,85034-1,2,-94,-129,-1,2,-94,-121,;6;-1;0",
            // 2
            "7a74G7m23Vrp0o5c9273221.69-1,2,-94,-100,Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/92.0.4515.107 Safari/537.36,uaend,12147,20030107,en-US,Gecko,3,0,0,0,400472,1720011,1536,871,1536,960,1536,540,1536,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:0,sc:0,wrc:1,isc:0,vib:1,bat:1,x11:0,x12:1,8971,0.579249632289,813810860005.5,0,loc:-1,2,-94,-101,do_en,dm_en,t_en-1,2,-94,-105,-1,2,-94,-102,-1,2,-94,-108,-1,2,-94,-110,-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://www.virginatlantic.com/us/en-1,2,-94,-115,1,32,32,0,0,0,0,1,0,1627621720011,-999999,17411,0,0,2901,0,0,4,0,0,27207A55526443BF511F396E10C92124~-1~YAAQpYHZF5Lf2ed6AQAAw3/Q9QYG9ANyLkNJZ7+RlBkUIuYc4SKMlG6c/Pt3puOZeptLhSblA6r07aFnKWaHljqnIia1nBRU8S3+Cv+W6ULyUSCEi4X3/IVqTSYicSK+0eg4L7BpsInQhaM6IuWtZnfH+XKFC2YZAzrH+6g9M600vcBAt+rEHsoUnYM5iLMmc3pv7I7fwsa6xFZQKKTPNlWdFS2ceWSo+wxSEw8zdfV2Xc/DeksZZgD9mAnZSe4bBDmn7p/aJJKwKSOw1O1gCnhpuAWDl2GoGv6x9jk6BY7LUdMVouShleYXtGJVOX8WV6qTFlq8PA3s7kYKJthCio+4D63AKtqjmIYYq7nAXcyqwHDtAW0PkyzD45EWWgrJXBONXaaBkmMQjet8DBeqdtQl~-1~-1~1627625256,37631,-1,-1,30261693,PiZtE,50483,81-1,2,-94,-106,0,0-1,2,-94,-119,-1-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,-1,2,-94,-126,-1,2,-94,-127,8-1,2,-94,-70,-1-1,2,-94,-80,94-1,2,-94,-116,5160036-1,2,-94,-118,84922-1,2,-94,-129,-1,2,-94,-121,;6;-1;0",
            // 3
            "7a74G7m23Vrp0o5c9273651.69-1,2,-94,-100,Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/92.0.4515.107 Safari/537.36,uaend,12147,20030107,en-US,Gecko,3,0,0,0,400502,3361567,1536,871,1536,960,1536,479,1536,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:0,sc:0,wrc:1,isc:0,vib:1,bat:1,x11:0,x12:1,8971,0.823020089411,813871680783.5,0,loc:-1,2,-94,-101,do_en,dm_en,t_en-1,2,-94,-105,-1,2,-94,-102,-1,2,-94,-108,-1,2,-94,-110,-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://www.virginatlantic.com/us/en-1,2,-94,-115,1,32,32,0,0,0,0,1,0,1627743361567,-999999,17413,0,0,2902,0,0,3,0,0,A335E1BEEAD3B1C4CFEF3C69F055B7D9~-1~YAAQDmAZuNzUzPN6AQAAkJkQ/QZd0piE+KCrm3zn3AiKjSuBVkd8/rb3GO9LqZuscDdxxhVQ+AYWPaBVf/uC7/BMqIgDEyYbn4Zzd9Nq0Sy2ikqvmIcSKfE3mREUJA345irZUY7yeAWl4ixTV4Eq5UalzEMI8rK5iJAVlvwmffvItYaPKgGjDxDKMneoE2yqnYhTT9ixBRfLw7PWUWO6csfjSqS62HX0c9XfDaMnHDdU2zw4DaOrwugAGMFMjlyf8lxdBMR/px6xiqce7aIhacBA9/gw6y+AxczwxMNd9Um2uDuoLx6ObBv7fHU+4OtvwrkH/Ob9lob0q4e0PNo9TWCrmW0vYE6hBsSiSktC6nJUW8LNMjs58Bo0dRfdZ9NaaKJBwRmaRmE9vU9fyhZeUWQR~-1~-1~1627746875,38736,-1,-1,30261693,PiZtE,81505,72-1,2,-94,-106,0,0-1,2,-94,-119,-1-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,-1,2,-94,-126,-1,2,-94,-127,8-1,2,-94,-70,-1-1,2,-94,-80,94-1,2,-94,-116,10084659-1,2,-94,-118,86073-1,2,-94,-129,-1,2,-94,-121,;4;-1;0",
            // 4
            "7a74G7m23Vrp0o5c9273661.69-1,2,-94,-100,Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/92.0.4515.107 Safari/537.36,uaend,12147,20030107,en-US,Gecko,3,0,0,0,400502,4037427,1536,871,1536,960,1536,479,1536,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:0,sc:0,wrc:1,isc:0,vib:1,bat:1,x11:0,x12:1,8971,0.566506689283,813872018713,0,loc:-1,2,-94,-101,do_en,dm_en,t_en-1,2,-94,-105,-1,2,-94,-102,-1,2,-94,-108,-1,2,-94,-110,-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://www.virginatlantic.com/us/en-1,2,-94,-115,1,32,32,0,0,0,0,2,0,1627744037426,-999999,17413,0,0,2902,0,0,4,0,0,B433CB00344FADFA67A116448BB8C284~-1~YAAQoJ0ZuCNTAe16AQAAIuoa/QYLNA0P4kFk2CDCPIeI7ItomCAiifQ7LzrJH57xLzNm7tfIO3TFqRLjN3S9Jz1skkbZme/7ncxJzqA2JZaYArp9sR9NcacAhUJwqk3oHRMk1BaesFSgT4ewbLI2w1pwUtahi4vs+VclanIm/gaJfq6yp5idy201Brfo3mFv4DWY+ImTTbqeNmGmIzh2o1fkASRl4rePdBCJdaRbUG06PRgfn/hIR0u/lHpe5UhVREr20EorEHuftTg0JXUMP9k7LowDKUBLi3BuDNyTua4fqRwGw4HveUc9pdYZeWR0ONQt0oWhy0cBC127nzhhOrLe9f93lYeFyoZHF7JwWjbAbcafHaXxxB/phnSgwMujvjNPfIuQbMnTNHxzZS1S6f3D~-1~-1~1627747564,38482,-1,-1,30261693,PiZtE,31196,125-1,2,-94,-106,0,0-1,2,-94,-119,-1-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,-1,2,-94,-126,-1,2,-94,-127,8-1,2,-94,-70,-1-1,2,-94,-80,94-1,2,-94,-116,60561204-1,2,-94,-118,85742-1,2,-94,-129,-1,2,-94,-121,;5;-1;0",
            // 5
            "7a74G7m23Vrp0o5c9273661.69-1,2,-94,-100,Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:90.0) Gecko/20100101 Firefox/90.0,uaend,11059,20100101,en-US,Gecko,0,0,0,0,400502,4376016,1536,871,1536,960,1536,456,1536,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:1,sc:0,wrc:1,isc:138,vib:1,bat:0,x11:0,x12:1,6002,0.794372776397,813872188007.5,0,loc:-1,2,-94,-101,do_en,dm_en,t_dis-1,2,-94,-105,-1,2,-94,-102,-1,2,-94,-108,-1,2,-94,-110,-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://www.virginatlantic.com/us/en-1,2,-94,-115,1,32,32,0,0,0,0,3,0,1627744376015,-999999,17413,0,0,2902,0,0,6,0,0,A3380AC63FD54F200417F4303E68141A~-1~YAAQVu8uFzUuru96AQAA7Q8g/QaJMSj2IsOW/lS0/mS7MfJ5edF1TaqbPeb0aHtGLPAv0ARmrlPa8k4p5fJHkQFMTRt19ZJeawrEiMp9LNo6D+acoRJiFNM/0scID2JqZ1Ht22SYAWvgf5Tlryhlxu2zmq8GzDu3rRrT8CJzjl1ABEWoE6eJzZ5NElM1dMcGc3AmcaL4jz+P/MgEt1k2mPEiZ8DntTIxs4xLgRWl0mKQI+NTEvRri3H9uCTtZtTUw8iSkx8Eg5LAB8b2X2avLGrZV4Oy0b8UiIAdQtORXbMfQGnvbm/eraRRgELO+x+8WMDGhFcsj3v6lFrvJro3RGKy19FyDKwwR/kLpQ8Edk+Nb7DMQPwz1PE4BxctivVyeuZu3LDRIrRlLpO2Vn2OxGWsQQ==~-1~-1~1627621668,38102,-1,-1,26067385,PiZtE,103001,104-1,2,-94,-106,0,0-1,2,-94,-119,-1-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,-1,2,-94,-126,-1,2,-94,-127,8-1,2,-94,-70,-1-1,2,-94,-80,94-1,2,-94,-116,13127988-1,2,-94,-118,82749-1,2,-94,-129,-1,2,-94,-121,;22;-1;0",
            // 6
            "7a74G7m23Vrp0o5c9128681.66-1,2,-94,-100,Mozilla/5.0 (Macintosh; Intel Mac OS X 10.16; rv:84.0) Gecko/20100101 Firefox/84.0,uaend,11059,20100101,en-US,Gecko,0,0,0,0,396518,2966824,1536,872,1536,960,1536,462,1536,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:1,sc:0,wrc:1,isc:127,vib:1,bat:0,x11:0,x12:1,6009,0.13925388469,805776483411.5,0,loc:-1,2,-94,-101,do_en,dm_en,t_dis-1,2,-94,-105,-1,2,-94,-102,-1,2,-94,-108,-1,2,-94,-110,-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://www.virginatlantic.com/us/en-1,2,-94,-115,1,32,32,0,0,0,0,1,0,1611552966823,-999999,17239,0,0,2873,0,0,8,0,0,3E03743F9A5753FBDB1A30F57843883F~-1~YAAQprSeQUPUYxx3AQAAVuEAOAVHhCjxg63Jtnb7NyOMWuqULVDOhMbRUkXurZHXoed9iGDJ/yPYglopPoyHly5QJx92PB3YSSScDwZi1fXTMOr5247Xr8KYUD+nEpcwypx0nJM/dDE7VsEgTP2hjJMmR/6NusnMc8mk2xt7BBijODXlayfF5ROTaPzYTIdwl0EoBftwpaKVFGwbU+Zi45bMUp7gBbb44Lp7QUOUKcEdOy4al2vMjZnBJgb2aDS4aLZiJk2ckET+xexeSbXr1651xS20uCISxKZoKFXpvlLFKvl768XkHJ5U1cvGjIAGu2Y=~-1~-1~-1,30567,-1,-1,26067385,PiZtE,53587,73-1,2,-94,-106,0,0-1,2,-94,-119,-1-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,-1,2,-94,-126,-1,2,-94,-127,8-1,2,-94,-70,-1-1,2,-94,-80,94-1,2,-94,-116,400521117-1,2,-94,-118,75114-1,2,-94,-129,-1,2,-94,-121,;9;-1;0",
            // 7
            "7a74G7m23Vrp0o5c9265671.69-1,2,-94,-100,Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:89.0) Gecko/20100101 Firefox/89.0,uaend,11059,20100101,en-US,Gecko,0,0,0,0,399795,774245,1536,871,1536,960,1536,442,1536,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:1,sc:0,wrc:1,isc:138,vib:1,bat:0,x11:0,x12:1,6018,0.492968444246,812435387122.5,0,loc:-1,2,-94,-101,do_en,dm_en,t_dis-1,2,-94,-105,-1,2,-94,-102,-1,2,-94,-108,-1,2,-94,-110,-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://www.virginatlantic.com/us/en-1,2,-94,-115,1,32,32,0,0,0,0,2,0,1624870774245,-999999,17382,0,0,2897,0,0,4,0,0,8DA73C722FA718D69A9E708F1078161A~-1~YAAQXCcwFz/6kEh6AQAAy2HYUQZPdaAzFHk/RyQUlXyzlvtCezPEx3BFHsukWKWrNqvufiZj0XfA0qFrxDzp/lgG3TN75jo2LVP6E43sIfCZkDD02C6GqxYjjStZQQZ7D2hnZVa2L4pJdea+S4ks9+DbujLWfkZxVh9o8YfKRE74fGEheCEHK4rMJ0aoHv+YzKmYKHENVOGPuK6jI8K6JQIehxsQARKvNubqNd74VvbWQ18kxGb3J8An+YMVSD2j6uJtS5+HqwUCm34B5E0U2tIrQXBOr6sBwLPn+yFdbAjbrL/KNU6O+KAv6TFjZ4kBNUOT5GItlZ+RisI+w0K4weh12gAkrgrVfZGiVZA1wzYQjxSun6AD079EfLKcEPug8KKAqS1AMumIgHYM7KVGO6gu2Q==~-1~-1~1624874362,37693,-1,-1,26067385,PiZtE,28299,53-1,2,-94,-106,0,0-1,2,-94,-119,-1-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,-1,2,-94,-126,-1,2,-94,-127,8-1,2,-94,-70,-1-1,2,-94,-80,94-1,2,-94,-116,58068756-1,2,-94,-118,82244-1,2,-94,-129,-1,2,-94,-121,;5;-1;0",
            // 8
            "7a74G7m23Vrp0o5c9273231.69-1,2,-94,-100,Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/92.0.4515.107 Safari/537.36,uaend,12147,20030107,en-US,Gecko,3,0,0,0,400473,5820308,1536,871,1536,960,1536,426,1536,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:0,sc:0,wrc:1,isc:0,vib:1,bat:1,x11:0,x12:1,8971,0.374930433187,813812910153.5,0,loc:-1,2,-94,-101,do_en,dm_en,t_en-1,2,-94,-105,-1,2,-94,-102,-1,2,-94,-108,-1,2,-94,-110,-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://www.virginatlantic.com/us/en-1,2,-94,-115,1,32,32,0,0,0,0,2,0,1627625820307,-999999,17411,0,0,2901,0,0,3,0,0,7024AD21E785561A85BA0ADE2B08AB24~-1~YAAQlSckF9Rxi5x6AQAA9Q8P9gYSeE9oodr+9l/M9ZCWK3PdhXvAJOsUqQeb/wdbpF0nIC471Da/SDAcsbP+q4Hj0++WPrr7+2l2GpVNrFUHqlyhlMNsFhIgnvKMLUAVhb3j3FMqvam01D/po20ygVCerkHDEbyp/J9FlSZt3hlYTHxNuHLdnEIwyOPVmSq+Ju7RHPx8V08ECkWKmq0MPf+NyXdnXU+Bc6+/y8T5ZGr96r3TyadieraCMvb2HDKcRxZw6LaTyLkmVPZM1Vhr7tgAFYXHt/v1MdlA8tSqUBn1nv6eGe8LMWjzUoDejp1htjj6gg5J8LU6EgwtaZiXpD9sBC+0Rj07ffw48NwIBXNqL0TUFHeTtM5fecvokN3Xmr/FW4QpxTMax/yULDNo1oA4~-1~-1~1627629321,37735,-1,-1,30261693,PiZtE,42987,69-1,2,-94,-106,0,0-1,2,-94,-119,-1-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,-1,2,-94,-126,-1,2,-94,-127,8-1,2,-94,-70,-1-1,2,-94,-80,94-1,2,-94,-116,157148295-1,2,-94,-118,85034-1,2,-94,-129,-1,2,-94,-121,;3;-1;0",
            // 9
            "7a74G7m23Vrp0o5c9273651.69-1,2,-94,-100,Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/92.0.4515.107 Safari/537.36,uaend,12147,20030107,en-US,Gecko,3,0,0,0,400502,3156014,1536,871,1536,960,1536,479,1536,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:0,sc:0,wrc:1,isc:0,vib:1,bat:1,x11:0,x12:1,8971,0.488155652244,813871578007,0,loc:-1,2,-94,-101,do_en,dm_en,t_en-1,2,-94,-105,-1,2,-94,-102,-1,2,-94,-108,-1,2,-94,-110,-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://www.virginatlantic.com/us/en-1,2,-94,-115,1,32,32,0,0,0,0,1,0,1627743156014,-999999,17413,0,0,2902,0,0,3,0,0,7024AD21E785561A85BA0ADE2B08AB24~-1~YAAQPGAZuHt3F/t6AQAAVHEN/QYRgtjiJBRCI05nNEZU6PV2rFcC10obds6IhiFFFEmNb55/4XRhIUo3aBnxoI3d1lxEA4bC4mxAIqjnS/mJ47ARln15cLF/sEbn6YGGq5Xa881eyE8L+QKkCgXzl7eFCgFfoTcO0De+uCIfcNQgUmM1ct2V2U5wyAE0k52wcNcH+rkYqet42YqqK0/GzhXJe4DDfHd/ifZ1bA4UD2B3ve+FCF8j1501yOQKT6vIhixULhdXfK3jZ+hP6cY0aGz9nF088PJVRE0ZeXusmjMLzcyqJpOiaZoulJc7d9W156qb+4fIeVKybBv6kaNgMIpeitLlpe6H2aYhfNKBmyjoxKDTMLzRviZHDfZropgua4qX7WfUCHCOxZl7ROckI+a8uZwQ27AqR+jSi9XyejE=~0~-1~1627629309,38917,-1,-1,30261693,PiZtE,30689,58-1,2,-94,-106,0,0-1,2,-94,-119,-1-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,-1,2,-94,-126,-1,2,-94,-127,8-1,2,-94,-70,-1-1,2,-94,-80,94-1,2,-94,-116,47340213-1,2,-94,-118,86124-1,2,-94,-129,-1,2,-94,-121,;5;-1;0",
            // 10
            "7a74G7m23Vrp0o5c9273231.69-1,2,-94,-100,Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/92.0.4515.107 Safari/537.36,uaend,12147,20030107,en-US,Gecko,3,0,0,0,400473,5711717,1536,871,1536,960,1536,540,1536,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:0,sc:0,wrc:1,isc:0,vib:1,bat:1,x11:0,x12:1,8971,0.664469927332,813812855858.5,0,loc:-1,2,-94,-101,do_en,dm_en,t_en-1,2,-94,-105,-1,2,-94,-102,-1,2,-94,-108,-1,2,-94,-110,-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://www.virginatlantic.com/us/en-1,2,-94,-115,1,32,32,0,0,0,0,2,0,1627625711717,-999999,17411,0,0,2901,0,0,3,0,0,EE6EDBABFA92B14FDC11E334F4FC9F03~-1~YAAQoJ0ZuGgL3ux6AQAAeGMN9gYZZcmy27HtrUGGUQkBPTebLdUd/oWaq6xejpUvveX/cpxwDP4e0pWpHSKmdnrbB+XTYj5qGMJY8YO/ozCed03XB3bXGn/c07pR3cH9xL93TYAkn74flYI2h5ydxZBua2uky+CVDhVqxB7LPgXuLP2zBrvTNXBmH7hZ4HBOJVHxO16zFjagwh1sA1Ae90XwUrhGlc+W8a3Ha85BDm12Fa1GYrYv4nCpWSjI4NYJTIodbTJCT37PR4NCdXJmGeLvb54ZiZdlCQKB4Z6sjcciL1wzsBOHxCgqussk+jRW2h9Jw8sWR6rf9SNdNLbLUpY1jZizaz1dKl4yQnuSL0TGqasvcMPs0dqKufa5p0QowCsJRQ==~-1~-1~1627629231,36849,-1,-1,30261693,PiZtE,87833,81-1,2,-94,-106,0,0-1,2,-94,-119,-1-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,-1,2,-94,-126,-1,2,-94,-127,8-1,2,-94,-70,-1-1,2,-94,-80,94-1,2,-94,-116,17135121-1,2,-94,-118,84163-1,2,-94,-129,-1,2,-94,-121,;4;-1;0",
        ];

        $secondSensorData = [
            // 0
            null,
            // 1
            "7a74G7m23Vrp0o5c9273211.69-1,2,-94,-100,Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:90.0) Gecko/20100101 Firefox/90.0,uaend,11059,20100101,en-US,Gecko,0,0,0,0,400471,7734012,1536,871,1536,960,1536,281,1536,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:1,sc:0,wrc:1,isc:138,vib:1,bat:0,x11:0,x12:1,6002,0.681469524340,813808867009.5,0,loc:-1,2,-94,-101,do_en,dm_en,t_dis-1,2,-94,-105,-1,2,-94,-102,-1,0,0,0,1354,-1,0;-1,0,0,0,1124,1638,0;-1,2,-94,-108,-1,2,-94,-110,-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://www.virginatlantic.com/us/en-1,2,-94,-115,1,32,32,0,0,0,0,897,0,1627617734019,6,17411,0,0,2901,0,0,900,0,0,07FCAAD49AF98A107D14950C32CE0B56~-1~YAAQoJ0ZuDh02+x6AQAA5a6T9Qbur/QcIprYIZ7zKBIgW2dRVc6HwaJZwtR4VCNJVpeXqnEvr4LFCVZYQHStp31uK4WjBPFCxyNCzdnrvUCxfTWNX5JtLurdrZaZFBi5mhxX+HqsXnmqj6zhA+fxS8+YX7p5bd4tRGF0GuLlroPhBDmUt7skh8aGAVWlCPpdrqKlSqNU/+hKxsSTKXs0WNINurRUrjU6PMVRqNd4e1pGEat2Fo7SjwwpKb/X4qWX8bZB9Wwgt5rI7MzEZWCxqqEbdH8lKLaZy7DVXb+I6Q5AP+X4SBmQtsezcNPFzceQg7K79P6N1mE/LmSUXuJP5GnHQhaJcyySZgeW6QSUYS5S0MRDy9N1XcFD7yBZqe1R7sANw+o+iY+e0l1NzpLSMcU5WzHl3LDHDacJSbbp6EcA0Q==~-1~-1~1627621265,40015,819,-725293259,26067385,PiZtE,79233,122-1,2,-94,-106,9,1-1,2,-94,-119,0,0,0,0,0,0,200,0,0,0,200,400,200,200,-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,-1,2,-94,-126,-1,2,-94,-127,11133333331333333333-1,2,-94,-70,50988738;1681675197;dis;;true;true;true;-300;true;24;24;true;false;1-1,2,-94,-80,5258-1,2,-94,-116,116010153-1,2,-94,-118,89552-1,2,-94,-129,05d8487f76a54cd7ab6067b1d89de837fca940f0d4135e7ea77bea438463eed3,2,0,Intel Inc.,Intel(R) UHD Graphics 630,faa364726c2d467d321c3121e9ca9e86c8e63c3eae47970c432c83f0c60bbc6e,25-1,2,-94,-121,;119;12;0",
            // 2
            "7a74G7m23Vrp0o5c9273221.69-1,2,-94,-100,Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/92.0.4515.107 Safari/537.36,uaend,12147,20030107,en-US,Gecko,3,0,0,0,400472,1720011,1536,871,1536,960,1536,540,1536,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:0,sc:0,wrc:1,isc:0,vib:1,bat:1,x11:0,x12:1,8971,0.16455526982,813810860005.5,0,loc:-1,2,-94,-101,do_en,dm_en,t_en-1,2,-94,-105,-1,2,-94,-102,-1,0,0,0,1354,-1,0;-1,0,0,0,1124,1638,0;-1,2,-94,-108,-1,2,-94,-110,-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://www.virginatlantic.com/us/en-1,2,-94,-115,1,32,32,0,0,0,0,922,0,1627621720011,103,17411,0,0,2901,0,0,923,0,0,27207A55526443BF511F396E10C92124~-1~YAAQpYHZF7vf2ed6AQAAAoHQ9QaWTIRdGMILHsZD6gHXD5Q7uGo+ZXfmOEARzIVgsypWuzdKVbUlLxMvGwX2b7WgU22EkZF0VZpAFeJe85ioTuGvz9KLOLPvRlnlw3/6QiW1aQCPuv4ILISmAvN7Vhbz5rQCiIe5LH0l+KAuVhlLMb46pVqPB3N7e9JXa43yuYt6eq6GNcxrbEk2GSHhuDIOJdoexSGMrKjELPOX8YI1d+0C8IksTPXASguCYk+JsH/eoBZpdExXqAiVXOwhurzmmk95TvhdOmEoZNEjVrq4TAXWGq8FoeZg+CPiZcrToxZyZHarRwOR9A4fZ6UePvo+daEP9P5IvBJ2zeN2VwMDqoFY7ahaznF6Fpajd/IwiipzYOKiiSPvpk8DwSpFxKzH~-1~-1~1627625257,38407,22,1256267944,30261693,PiZtE,43580,50-1,2,-94,-106,9,1-1,2,-94,-119,40,20,40,40,40,60,40,40,380,20,0,1100,980,160,-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,-1,2,-94,-126,-1,2,-94,-127,11321144241322243122-1,2,-94,-70,-1577612800;862128768;dis;,7,8;true;true;true;-300;true;30;30;true;false;-1-1,2,-94,-80,5575-1,2,-94,-116,5160036-1,2,-94,-118,91015-1,2,-94,-129,87838b6b28b2e952ff9e4e3f1f00f6c6146fa8090ee5d0541c06ecdc180622da,2,0,Intel Inc.,Intel(R) UHD Graphics 630,f437e95c71eb8e0326eb22e9cba9c05b86068086180a4ca09b3bcd32320a1928,31-1,2,-94,-121,;57;14;0",
            // 3
            "7a74G7m23Vrp0o5c9273651.69-1,2,-94,-100,Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/92.0.4515.107 Safari/537.36,uaend,12147,20030107,en-US,Gecko,3,0,0,0,400502,3361567,1536,871,1536,960,1536,479,1536,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:0,sc:0,wrc:1,isc:0,vib:1,bat:1,x11:0,x12:1,8971,0.874364522437,813871680783.5,0,loc:-1,2,-94,-101,do_en,dm_en,t_en-1,2,-94,-105,-1,2,-94,-102,-1,0,0,0,1354,-1,0;-1,0,0,0,1124,1638,0;-1,2,-94,-108,-1,2,-94,-110,-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://www.virginatlantic.com/us/en-1,2,-94,-115,1,32,32,0,0,0,0,682,0,1627743361567,13,17413,0,0,2902,0,0,684,0,0,A335E1BEEAD3B1C4CFEF3C69F055B7D9~-1~YAAQDmAZuOfUzPN6AQAAQpsQ/QZ+FoWXFosgn6JeLPOa3O5xZ4niy8s9R9IN/JYzqV8bc+TKjSdr0ldvHFnnT6IDMI5/fQ8qaoNu0NZzN+hstkFzoVI33dj3OwZR/LvEykAoFdAmBdrbZTdSK/oJ7k6kDvWMpOSRjgnw3Y3NapLbwz6ttKxNflWd3tCaDB1nlfJalBbNoW07lq7ISkpeNIeT8AqM+BTrvRbxA5Gy/W2bUgi0yEeBPqR4TakyXYn1Y8C+uRPcPuw/eQR2fWdP1Va4L0LaAoQEFp3QnukZcNAGk0epdS5AsNjV5gt0/H6EtMb2En17VXPHSRfmHFQKPElBkHPSOgN4BIkR6cylvVUyIhNwjahTypVRhi0/j0EZxS3Ct4fkavtt0hl3bKHoMFPr~-1~-1~1627746912,38341,529,-1047310681,30261693,PiZtE,85666,73-1,2,-94,-106,9,1-1,2,-94,-119,40,40,40,40,40,60,40,40,40,20,0,1140,1140,220,-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,-1,2,-94,-126,-1,2,-94,-127,11321144241322243122-1,2,-94,-70,-1577612800;862128768;dis;,7,8;true;true;true;-300;true;30;30;true;false;-1-1,2,-94,-80,5575-1,2,-94,-116,10084659-1,2,-94,-118,91163-1,2,-94,-129,87838b6b28b2e952ff9e4e3f1f00f6c6146fa8090ee5d0541c06ecdc180622da,2,0,Intel Inc.,Intel(R) UHD Graphics 630,f437e95c71eb8e0326eb22e9cba9c05b86068086180a4ca09b3bcd32320a1928,31-1,2,-94,-121,;17;11;0",
            // 4
            "7a74G7m23Vrp0o5c9273661.69-1,2,-94,-100,Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/92.0.4515.107 Safari/537.36,uaend,12147,20030107,en-US,Gecko,3,0,0,0,400502,4037427,1536,871,1536,960,1536,479,1536,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:0,sc:0,wrc:1,isc:0,vib:1,bat:1,x11:0,x12:1,8971,0.570411673285,813872018713,0,loc:-1,2,-94,-101,do_en,dm_en,t_en-1,2,-94,-105,-1,2,-94,-102,-1,0,0,0,1354,-1,0;-1,0,0,0,1124,1638,0;-1,2,-94,-108,-1,2,-94,-110,-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://www.virginatlantic.com/us/en-1,2,-94,-115,1,32,32,0,0,0,0,908,0,1627744037426,76,17413,0,0,2902,0,0,909,0,0,B433CB00344FADFA67A116448BB8C284~-1~YAAQoJ0ZuDBTAe16AQAALOsa/QZ8YV82ajhZp7nm6CCLI+R531mqd3CScxJEcuRkXQ1LbPnuaXog6OLqzXhgFDSI+alzXDqVBeDRz6mxPiTbydhckKW0YoM9uq2Yvos9Zd7KAIVRCqS9bNptwIT/6h7oNlF+J6YfA06uSPiCni1tRDG/E7nMd410c/Rsi9UctcUMm+9TBx1Eo/+07tiV70GTGTe+dUsAbP5F7usU8UYdEDUIqGUlYFC4e4dE7piggZ4K4neI0zT1QbcIKqutGHUN5nl49UmKOPuvo+HeoYDI54chaetl4fVkyDD/33+Qfl0wanUMbhCgIxIEwqEmnklkWIKPwk6Xp67apJbr/fYUtzWRbxR6afTbcmy/24w85ECaeXqTe05zxrqSdmO2kxrR~-1~-1~1627747618,37827,560,-5830098,30261693,PiZtE,96710,77-1,2,-94,-106,9,1-1,2,-94,-119,40,20,40,40,40,60,40,20,20,0,0,1260,1220,180,-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,-1,2,-94,-126,-1,2,-94,-127,11321144241322243122-1,2,-94,-70,-1577612800;862128768;dis;,7,8;true;true;true;-300;true;30;30;true;false;-1-1,2,-94,-80,5575-1,2,-94,-116,60561204-1,2,-94,-118,90326-1,2,-94,-129,87838b6b28b2e952ff9e4e3f1f00f6c6146fa8090ee5d0541c06ecdc180622da,2,0,Intel Inc.,Intel(R) UHD Graphics 630,f437e95c71eb8e0326eb22e9cba9c05b86068086180a4ca09b3bcd32320a1928,31-1,2,-94,-121,;60;16;0",
            // 5
            "7a74G7m23Vrp0o5c9273661.69-1,2,-94,-100,Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:90.0) Gecko/20100101 Firefox/90.0,uaend,11059,20100101,en-US,Gecko,0,0,0,0,400502,4376016,1536,871,1536,960,1536,456,1536,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:1,sc:0,wrc:1,isc:138,vib:1,bat:0,x11:0,x12:1,6002,0.863073654431,813872188007.5,0,loc:-1,2,-94,-101,do_en,dm_en,t_dis-1,2,-94,-105,-1,2,-94,-102,-1,0,0,0,1354,-1,0;-1,0,0,0,1124,1638,0;-1,2,-94,-108,-1,2,-94,-110,-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://www.virginatlantic.com/us/en-1,2,-94,-115,1,32,32,0,0,0,0,779,0,1627744376015,10,17413,0,0,2902,0,0,780,0,0,A3380AC63FD54F200417F4303E68141A~-1~YAAQVu8uF0kuru96AQAAFhYg/QZLAYE88sNpGZOC+ceBrH+2+jTdpMLqlKcRIlKId6EkuVcWyLfcyNlAPRHH1EFgSGrrL5b6bAxQGqqPYM0ghtlcB+OQ4oEteau+4Qd9EikERRJ1RXLNDTL1omUlrn2929syD7i7wK/xiJ+CMLPJnO5BJHgaL76izGTNv93CvA7nFaiitFpC6/HBOHrLLk0Hal6DFPAXDCojFy6rCMPniL/xTw4mMEdqRrR5qI8KrDYu84k5YCaYddU4kpZKz2aPWDIJIwT+wv53S7XbvgaJUXg1Y58VoUOp1XgFo736N6/bIDB+7nUogXb5lKh0ePCwRm28YM/WUfpxk+uUK6rAS6GE9suRNauuOG4Kld8iTNgFdHNmkcTvbhUuHHusI5EaQw==~-1~-1~1627621668,37614,298,-404393326,26067385,PiZtE,94654,59-1,2,-94,-106,9,1-1,2,-94,-119,200,200,0,0,200,0,0,0,0,0,0,200,200,200,-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,-1,2,-94,-126,-1,2,-94,-127,11133333331333333333-1,2,-94,-70,50988738;1681675197;dis;;true;true;true;-300;true;24;24;true;false;1-1,2,-94,-80,5258-1,2,-94,-116,13127988-1,2,-94,-118,87243-1,2,-94,-129,05d8487f76a54cd7ab6067b1d89de837fca940f0d4135e7ea77bea438463eed3,2,0,Intel Inc.,Intel(R) UHD Graphics 630,faa364726c2d467d321c3121e9ca9e86c8e63c3eae47970c432c83f0c60bbc6e,25-1,2,-94,-121,;60;30;0",
            // 6
            "7a74G7m23Vrp0o5c9128681.66-1,2,-94,-100,Mozilla/5.0 (Macintosh; Intel Mac OS X 10.16; rv:84.0) Gecko/20100101 Firefox/84.0,uaend,11059,20100101,en-US,Gecko,0,0,0,0,396518,2966824,1536,872,1536,960,1536,462,1536,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:1,sc:0,wrc:1,isc:127,vib:1,bat:0,x11:0,x12:1,6009,0.725476250362,805776483411.5,0,loc:-1,2,-94,-101,do_en,dm_en,t_dis-1,2,-94,-105,-1,2,-94,-102,-1,0,0,0,1354,-1,0;-1,0,0,0,1124,1638,0;-1,2,-94,-108,-1,2,-94,-110,0,1,81,317,426;1,1,84,367,452;2,1,581,367,454;-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://www.virginatlantic.com/us/en-1,2,-94,-115,1,3167,32,0,0,0,3135,681,0,1611552966823,7,17239,0,3,2873,0,0,681,746,0,3E03743F9A5753FBDB1A30F57843883F~-1~YAAQnh/JFzny1S13AQAAwskKOAUO72NcD6ot5r8MopTQnXsGsIjOa7aXxug+vt2klb8j3foTpjSVN+NM2fJxJSJwQfwaPAyIqB/1WicTAG5R9akVhAEb7cWVRy4vNCeE5jVZgAIQpcOoGrsd8V8xoeqgC7PKyxYnPrMGYS05yF2hD/+8EI9jdN9Vcva2zHQ9Afo2DiVJjp4h38IaseBUriHc9uhAUAkECSM8O+f8EmXpqo5z7vg7WHL74j3+0lrsdITjeo3eF6FHwtbprtxIdqNxMfChbhaMRu2/gHXX3ys8CDxB5ETZsdI1mRbMm97V4W3A8o+iGgix/oaHS7CqPw==~-1~-1~-1,32052,463,-1277741357,26067385,PiZtE,23159,79-1,2,-94,-106,9,1-1,2,-94,-119,200,0,0,0,0,0,0,200,0,200,0,200,600,0,-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,-1,2,-94,-126,-1,2,-94,-127,11133333331333333333-1,2,-94,-70,50988738;1681675197;dis;;true;true;true;-300;true;24;24;true;false;1-1,2,-94,-80,5258-1,2,-94,-116,400521117-1,2,-94,-118,84336-1,2,-94,-129,d6350cc6832ca216bbeb88243f8742dbb972e8f7f09960559265cf71b2842f75,2,0,,,,0-1,2,-94,-121,;11;14;0",
            // 7
            "7a74G7m23Vrp0o5c9265671.69-1,2,-94,-100,Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:89.0) Gecko/20100101 Firefox/89.0,uaend,11059,20100101,en-US,Gecko,0,0,0,0,399795,774245,1536,871,1536,960,1536,442,1536,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:1,sc:0,wrc:1,isc:138,vib:1,bat:0,x11:0,x12:1,6018,0.261268338130,812435387122.5,0,loc:-1,2,-94,-101,do_en,dm_en,t_dis-1,2,-94,-105,-1,2,-94,-102,-1,0,0,0,1354,-1,0;-1,0,0,0,1124,1638,0;-1,2,-94,-108,-1,2,-94,-110,-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://www.virginatlantic.com/us/en-1,2,-94,-115,1,32,32,0,0,0,0,934,0,1624870774245,8,17382,0,0,2897,0,0,935,0,0,8DA73C722FA718D69A9E708F1078161A~-1~YAAQXCcwF1X6kEh6AQAAbmfYUQab/GuaSDzS0fd6CruqN+mNGtVkOXkgjwZRetK3MsSBU2DQup1C8aigCNpBze0y5AXlNv5eBQNDKniGlK8mTyroLPPl5dsoPxUEmfVXbhrQiYBc9tzHjRLWxVDrDO887MThQcBgEPusePSYar2BtthQGETNDLNbRAnjritV1DpI8NiMXbEZIEGSSKrgUuYTD33NxeUMw5Pw61YAGJ81w5kJF/3WrQSQV75vfU7G/WX6dLd2CA3zB/+WEKYhkxBbdXezO0BtlBDQ0tHf+/wmX+QEYzJC/HoEst5GgN151j77iKcbTJDQdzhsCrsmUxS+pXH/iIV6N8tKnXtZn25V3Bb/I7yrkXsMGCzUYdanOtszP2pedrttv5Nc0/TD/cmP+w==~-1~-1~1624874274,38298,997,-1156692340,26067385,PiZtE,61695,27-1,2,-94,-106,9,1-1,2,-94,-119,0,0,0,200,0,200,0,200,0,200,0,200,200,200,-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,-1,2,-94,-126,-1,2,-94,-127,11133333331333333333-1,2,-94,-70,50988738;1681675197;dis;;true;true;true;-300;true;24;24;true;false;1-1,2,-94,-80,5258-1,2,-94,-116,58068756-1,2,-94,-118,88034-1,2,-94,-129,05d8487f76a54cd7ab6067b1d89de837fca940f0d4135e7ea77bea438463eed3,2,0,Intel Inc.,Intel(R) UHD Graphics 630,faa364726c2d467d321c3121e9ca9e86c8e63c3eae47970c432c83f0c60bbc6e,25-1,2,-94,-121,;23;11;0",
            // 8
            "7a74G7m23Vrp0o5c9273231.69-1,2,-94,-100,Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/92.0.4515.107 Safari/537.36,uaend,12147,20030107,en-US,Gecko,3,0,0,0,400473,5820308,1536,871,1536,960,1536,426,1536,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:0,sc:0,wrc:1,isc:0,vib:1,bat:1,x11:0,x12:1,8971,0.648054891324,813812910153.5,0,loc:-1,2,-94,-101,do_en,dm_en,t_en-1,2,-94,-105,-1,2,-94,-102,-1,0,0,0,1354,-1,0;-1,0,0,0,1124,1638,0;-1,2,-94,-108,-1,2,-94,-110,-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://www.virginatlantic.com/us/en-1,2,-94,-115,1,32,32,0,0,0,0,647,0,1627625820307,10,17411,0,0,2901,0,0,648,0,0,7024AD21E785561A85BA0ADE2B08AB24~-1~YAAQlSckF9Vxi5x6AQAAjhEP9gbFtJwX3W9dcP1KcR7SacHCsL9nEAiZiuD94Tnhha/pDV6OyczW75T4SDnkc8etvqvPGZxulum3w/2tqeqewXcazwRiey2B2msYtoxhKvK/k2piPAMuTi+UA7y6KtHi3dT+JUQ2fIHjAXYfTF602RAlulHS2/f048FdRRCYfU5IPpE0l7F1yN3O+gHIvaDgXRLvO4ZaPslOIdPlAuntPhnMvLEVLPThw98dXDlCPjmdxwEie+lSTUK16QN3mf7mbgRYHXqRnygN/hBtLj+7v4mYiJN6WQSGLeYCfehyB7JeLoGG8uKJeRPCfe+wH4gF1Vqu+CQXq6qORXQYdndiyB1hgKVm4z6QWam5u/9hSRdC0Y1n3vc8xrDRfAbSRQ5j~-1~-1~1627629311,38151,181,1307512387,30261693,PiZtE,39839,75-1,2,-94,-106,9,1-1,2,-94,-119,40,40,40,40,40,60,20,40,20,0,0,1060,1000,220,-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,-1,2,-94,-126,-1,2,-94,-127,11321144241322243122-1,2,-94,-70,-1577612800;862128768;dis;,7,8;true;true;true;-300;true;30;30;true;false;-1-1,2,-94,-80,5575-1,2,-94,-116,157148295-1,2,-94,-118,90785-1,2,-94,-129,87838b6b28b2e952ff9e4e3f1f00f6c6146fa8090ee5d0541c06ecdc180622da,2,0,Intel Inc.,Intel(R) UHD Graphics 630,f437e95c71eb8e0326eb22e9cba9c05b86068086180a4ca09b3bcd32320a1928,31-1,2,-94,-121,;15;10;0",
            // 9
            "7a74G7m23Vrp0o5c9273651.69-1,2,-94,-100,Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/92.0.4515.107 Safari/537.36,uaend,12147,20030107,en-US,Gecko,3,0,0,0,400502,3156014,1536,871,1536,960,1536,479,1536,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:0,sc:0,wrc:1,isc:0,vib:1,bat:1,x11:0,x12:1,8971,0.591835367295,813871578010.5,0,loc:-1,2,-94,-101,do_en,dm_en,t_en-1,2,-94,-105,-1,2,-94,-102,-1,2,-94,-108,-1,2,-94,-110,-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://www.virginatlantic.com/us/en-1,2,-94,-115,1,32,32,0,0,0,0,643,0,1627743156021,101,17413,0,0,2902,0,0,644,0,0,7024AD21E785561A85BA0ADE2B08AB24~-1~YAAQPGAZuJl3F/t6AQAAVXgN/QbFqBJytcINtJCKVE9rnWgSoTanCoiElOlGd/MDxLP3HHTSVXcXqaKd2hYSUN+PTKjRM/rYFbsDnOv5kn009ilMBq7fZyro0CK68D7UcWZDJUpyw3wnrJHaibNPVX+nLDjyrhI9A2GWi0qmIoenzLMKEQ52osJWf5v5PP8IMKkhs4yWp474l/dw3dIRyesVI8sQ4K3EjClg7cTOqNcusx7Wfmtb6RJOjleiwsu3pwge/iQI5ujC9/QOY3kfuePkZOyvZfssilyWGe7JE0Gr3qHhwkYvrqT4MCrtePbXICid91mZlV6/Wfo7aKlXxqJgJl0lRsS2DYL8duaI525zk+UnfCGYCz7W6oYceFb5lzwXnNQHn13yvXH/DRwd6mYXAarlgEOiISMoltWsiD7T~-1~-1~1627746738,40391,340,-137159419,30261693,PiZtE,99524,121-1,2,-94,-106,9,1-1,2,-94,-119,40,20,40,20,40,60,40,0,0,0,0,1140,1040,220,-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,-1,2,-94,-126,-1,2,-94,-127,11321144241322243122-1,2,-94,-70,-1577612800;862128768;dis;,7,8;true;true;true;-300;true;30;30;true;false;-1-1,2,-94,-80,5575-1,2,-94,-116,47340213-1,2,-94,-118,91129-1,2,-94,-129,87838b6b28b2e952ff9e4e3f1f00f6c6146fa8090ee5d0541c06ecdc180622da,2,0,Intel Inc.,Intel(R) UHD Graphics 630,f437e95c71eb8e0326eb22e9cba9c05b86068086180a4ca09b3bcd32320a1928,31-1,2,-94,-121,;28;13;0",
            // 10
            "7a74G7m23Vrp0o5c9273231.69-1,2,-94,-100,Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/92.0.4515.107 Safari/537.36,uaend,12147,20030107,en-US,Gecko,3,0,0,0,400473,5711717,1536,871,1536,960,1536,540,1536,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:0,sc:0,wrc:1,isc:0,vib:1,bat:1,x11:0,x12:1,8971,0.11466239557,813812855858.5,0,loc:-1,2,-94,-101,do_en,dm_en,t_en-1,2,-94,-105,-1,2,-94,-102,-1,0,0,0,1354,-1,0;-1,0,0,0,1124,1638,0;-1,2,-94,-108,-1,2,-94,-110,0,1,85,1373,528;1,1,230,1308,534;2,1,232,1305,535;3,1,246,1302,535;4,1,248,1300,536;5,1,256,1298,537;6,1,269,1298,537;7,1,273,1297,537;8,1,288,1296,537;9,1,378,1295,538;10,1,709,1295,539;-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://www.virginatlantic.com/us/en-1,2,-94,-115,1,23572,32,0,0,0,23540,870,0,1627625711717,59,17411,0,11,2901,0,0,871,3214,0,EE6EDBABFA92B14FDC11E334F4FC9F03~-1~YAAQoJ0ZuIgL3ux6AQAAWmkN9gabu/yhfB3wt5p0Qoj67FRS4xmM585sPa6te3h05cOM7OLApToxf8tfoEX3inHYLUzhXmLRVFKhnR26lCNu+l7Yy37i8Cw+bc7ZHjXq2uEz6UezErSBOVTPcaL9SJxkmdqZ1l21crmNlPGKvGAL1C4VD7VEiwBELU1LlU3wea0PDaeo3LLJQghtpjmx6Xgw62ABLJNtzOhJ2cDhJ2yqwwvG9COHO1xV8A6SK4U9rYA1sDe3TB0OlczWz7yyK1G2Wnr1uzltxdU6NtCv+d8zOmP63vJVxrnmWoyXpqg7KSqr/uM9CD75nSWbUmJ2nZBRciGnlHW2rEmfAOmCW9kX4L9vEtFZQrIiADeCzGI/16PMNpc/9i2SnZwQ38jpDfps~-1~-1~1627629196,38093,530,2068287461,30261693,PiZtE,12764,69-1,2,-94,-106,9,1-1,2,-94,-119,40,40,20,40,40,60,40,40,0,0,0,1080,980,200,-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,-1,2,-94,-126,-1,2,-94,-127,11321144241322243122-1,2,-94,-70,-1577612800;862128768;dis;,7,8;true;true;true;-300;true;30;30;true;false;-1-1,2,-94,-80,5575-1,2,-94,-116,17135121-1,2,-94,-118,100649-1,2,-94,-129,87838b6b28b2e952ff9e4e3f1f00f6c6146fa8090ee5d0541c06ecdc180622da,2,0,Intel Inc.,Intel(R) UHD Graphics 630,f437e95c71eb8e0326eb22e9cba9c05b86068086180a4ca09b3bcd32320a1928,31-1,2,-94,-121,;48;13;0",
        ];

        if (count($sensorData) != count($secondSensorData)) {
            $this->logger->error("wrong sensor data values");

            return null;
        }

        if (!isset($this->key)) {
            $this->key = array_rand($sensorData);
            $this->logger->notice("set key: {$this->key}");
        }
        $this->logger->notice("key: {$this->key}");

        $sensor_data = $secondSensor === false ? $sensorData[$this->key] : $secondSensorData[$this->key];

        return $sensor_data;
    }

    private function sendSensorData()
    {
        $form = $this->http->Form;
        $formURL = $this->http->FormURL;
        $this->http->RetryCount = 0;
        $referer = $this->http->currentUrl();

        $sensorPostUrl =
            $this->http->FindPreg("#_cf\.push\(\['_setAu',\s*'(/.+?)'\]\);#")
            ?? $this->http->FindPreg("# src=\"([^\"]+)\"><\/script><\/body>#")
        ;

        if ($sensorPostUrl) {
            sleep(1);
            $sensorDataHeaders = [
                "Accept"       => "*/*",
                "Content-type" => "text/plain;charset=UTF-8",
                "Origin"       => "https://www.virginatlantic.com",
                "Referer"      => "https://www.virginatlantic.com/us/e",
            ];
            $sensorData = [
                'sensor_data' => $this->getSensorData(),
            ];
            $this->http->NormalizeURL($sensorPostUrl);
            $this->http->PostURL($sensorPostUrl, json_encode($sensorData), $sensorDataHeaders);
            sleep(1);
            $sensorData2 = $this->getSensorData(true);
            $sensorData = [
                'sensor_data' => $sensorData2,
            ];

            if (!empty($sensorData2)) {
                $this->http->PostURL($sensorPostUrl, json_encode($sensorData), $sensorDataHeaders);
                $this->http->JsonLog();
                sleep(1);
            }
        } else {
            $this->logger->error("sensor_data URL not found");
        }

        $this->http->RetryCount = 2;
        $this->http->Form = $form;
        $this->http->FormURL = $formURL;
        $this->http->setDefaultHeader("Referer", $referer);
    }

    private function sendStatistic($success, $retry)
    {
        $this->logger->notice(__METHOD__);
        StatLogger::getInstance()->info("virgin sensor_data attempt", [
            "success"         => $success,
            "userAgentStr"    => $this->http->userAgent,
            "retry"           => $retry,
            "attempt"         => $this->attempt,
            "sensor_data_key" => $this->key,
            "isWindows"       => stripos($this->http->userAgent, 'windows') !== false,
        ]);
    }
}
