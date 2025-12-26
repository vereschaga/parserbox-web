<?php

use AwardWallet\Common\Parsing\Html;
use AwardWallet\Common\Selenium\FingerprintFactory;
use AwardWallet\Common\Selenium\FingerprintRequest;
use AwardWallet\Engine\ProxyList;
use AwardWallet\Schema\Parser\Common\Flight;
use AwardWallet\Schema\Parser\Common\Train;

class TAccountCheckerLufthansa extends TAccountChecker
{
    use ProxyList;
    use SeleniumCheckerHelper;

    public const SWISS_URL = 'https://www.swiss.com/us/en/login';

    private $curl = false;
    private $mainInfo = null;
    private $mainInfoXpath = '//script[@data-src="/users/userinfobox"]';
    private $apiURL = 'https://www.lufthansa.com/service/secured/api/core/user/profile';
    private $apiHeaders = [
        'Accept'             => 'application/json',
        'X-Distil-Ajax'      => 'awrwsaawcc',
        'X-Portal'           => 'LH',
        'X-Portal-CountryId' => '18002',
        'X-Portal-Language'  => 'en',
        'X-Portal-Site'      => 'EN',
        'X-Portal-Taxonomy'  => 'My_Account>AccountStatement',
    ];

    private $currentItin = 0;
    private $geetestFailed = false;
    private $invalidMAMLogin = false;
    private $name = [];
    private $errorSwiss = '';
    private $checkReservations = true;
    private $urlReservations = 'https://www.lufthansa.com/deeplink/cockpit?country=de&language=en';

    private $endHistory = false;

    private $parsedLocators = [];

    public static function GetAccountChecker($accountInfo)
    {
        require_once __DIR__ . "/TAccountCheckerLufthansaSelenium.php";

        return new TAccountCheckerLufthansaSelenium();
    }

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->setHttp2(true);
        $this->http->setDefaultHeader('Accept-Encoding', 'gzip, deflate, br');
    }

    public function IsLoggedIn()
    {
        $this->http->RetryCount = 0;
        $this->http->GetURL($this->apiURL, $this->apiHeaders, 20);
        // provider error bug fix
        if (
            isset($this->http->Response['code'])
            && $this->http->Response['code'] == 500
            && $this->http->FindPreg("/Error 500: java\.lang\.NullPointerException/")
        ) {
            sleep(3);
            $this->http->GetURL($this->apiURL, $this->apiHeaders, 20);
        }
        $this->http->RetryCount = 2;
        $this->redirectHelperForm();

        if (isset($this->http->Response['code']) && $this->http->Response['code'] == 200) {
            $this->redirectHelperForm();
        }

        if (
            $this->http->FindPreg("/\"kosaid\":\"([^\"]+)/")
            || $this->http->FindPreg("/\"authenticationLevel\":\"authenticated\"/ims")
        ) {
            $this->mainInfo = $this->http->JsonLog();
            $this->redirectHelperForm();

            if ($this->http->Response['code'] == 302) {
                // refs #14600
                $this->curl = true;

                return false;
            }

            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        $this->logger->debug(var_export($this->AccountFields, true), ["pre" => true]);

        $this->logger->debug('[Parse start date: ' . date('Y/m/d H:i:s') . ']');

        if ($this->curl) {
            return $this->LoadLoginFormCurl();
        }

        /*$this->http->RetryCount = 0;
        $this->http->GetURL('https://www.lufthansa.com/etc/designs/dcep/favicon.ico');
        $this->http->RetryCount = 2;*/

        return $this->selenium();
    }

    public function Login()
    {
        $this->logger->debug('[Parse date: ' . date('Y/m/d H:i:s') . ']');

        if ($this->curl) {
            return $this->LoginCurl();
        }
        /*
        $this->http->SetProxy($this->proxyReCaptchaIt7());
        */
        /*
        $this->http->SetProxy($this->proxyAustralia());
        */
        //$this->http->SetProxy($this->proxyUK());
        $this->http->RetryCount = 1;
        $this->http->GetURL($this->apiURL, $this->apiHeaders);
        $this->http->RetryCount = 2;

        if (isset($this->http->Response['errorMessage'])) {
            $this->logger->debug("[errorMessage]: >{$this->http->Response['errorMessage']}<");
        }

        if (isset($this->http->Response['errorCode'])) {
            $this->logger->debug("[errorCode]: {$this->http->Response['errorCode']}");
        }

        if (isset($this->http->Response['code'])) {
            $this->logger->debug("[code]: {$this->http->Response['code']}");
        }
        $this->logger->debug("[Error]: >{$this->http->Error}<");

        if (!isset($this->http->Response['code'])) {
            $this->logger->error("provider bug fix");
            $this->http->Response['code'] = null;
        }

        // provider error bug fix
        if (
            (
                $this->http->Response['code'] == 500
                && $this->http->FindPreg("/Error 500: java\.lang\.NullPointerException/")
            )
            || $this->http->Response['code'] == 404
        ) {
            sleep(3);
            $this->http->GetURL($this->apiURL, $this->apiHeaders);
        }

        if ($this->http->Response['code'] == 456 && $this->http->FindPreg("/<h1>Access To Website Blocked<\/h1>/")) {
            $this->ErrorReason = self::ERROR_REASON_BLOCK;
            $this->DebugInfo = 'Access To Website Blocked';

            throw new CheckRetryNeededException(3, 5);
        }// if ($this->http->Response['code'] == 456 && $this->http->FindPreg("/<h1>Access To Website Blocked<\/h1>/"))

        if ($this->http->Response['code'] == 403 && $this->http->FindSingleNode("//h1[contains(text(), 'Access Denied')]") && !$this->allowSomeAccount()) {
            $this->ErrorReason = self::ERROR_REASON_BLOCK;
            $this->DebugInfo = 'Access Denied';

            throw new CheckRetryNeededException(3, 5);
        }// if ($this->http->Response['code'] == 456 && $this->http->FindPreg("/<h1>Access To Website Blocked<\/h1>/"))

        if (
            ($this->http->Response['code'] == 0 && trim($this->http->Error) == 'Network error 0 -')
            || strstr($this->http->Error, 'Network error 28 - Operation timed out after')
            || strstr($this->http->Error, 'Received HTTP code 503 from proxy after CONNECT')
        ) {
            throw new CheckRetryNeededException(3, 5);
        }

        $this->redirectHelperForm();

        if ($this->http->Response['code'] == 200) {
            $this->redirectHelperForm();
        }
        $this->http->JsonLog();

        if (
            $this->http->FindPreg("/\"kosaid\":\"([^\"]+)/")
            || $this->http->FindPreg("/\"authenticationLevel\":\"authenticated\"/ims")
            || $this->allowSomeAccount()
        ) {
            return true;
        } elseif ($this->distil(true, __METHOD__)) {
            $this->http->GetURL($this->apiURL, $this->apiHeaders);

            $this->redirectHelperForm();

            if ($this->http->Response['code'] == 200) {
                $this->redirectHelperForm();
            }
            $this->http->JsonLog();

            if (
                $this->http->FindPreg("/\"kosaid\":\"([^\"]+)/")
                || $this->http->FindPreg("/\"authenticationLevel\":\"authenticated\"/")
            ) {
                return true;
            }

            // provider error bug fix
            if ($this->http->Response['code'] == 500 && $this->http->FindPreg("/Error 500: java\.lang\.NullPointerException/")) {
                throw new CheckRetryNeededException(3, 7);
            }

            // todo: debug
            $this->http->GetURL($this->apiURL, $this->apiHeaders, 20);
            $this->http->JsonLog();
        }
        // refs #14600
        elseif ($this->http->FindPreg("/\{\"activated\":false,\"communicationSettings\":\{\"preferredLanguage\":\"1000152\"\},\"address\":\{\"country\":\"18002\"\},\"emailAddress\":\[\],\"mamMandatory\":false,\"paymentInfo\":\[\],\"isTravelPartner\":false,\"hiddenElements\":\[\],\"notifications\":\[\],\"travelPartners\":\[\],\"isMamEnrolment\":false,\"syncMamEmail\":false\}/")
            || $this->http->FindPreg("/\{\"authenticationLevel\":\"ANONYMOUS\",\"profile\":\{\"telecomAddresses\":\[\],\"generalDetails\":\{\"language\":\{\"value\":\"EN\"\}\},\"postalAddresses\":\[\{\"addressType\":{\"value\":\"PRIVATE\"\},\"country\":\{\"value\":\"EN\"\}\}\],\"electronicAddresses\":\[\],\"additionalLoyaltyPrograms\":\[\],\"paymentDetails\":\[\],\"paymentMethods\":\[\],\"permissions\":\[\],\"preferences\":\[\],\"discountInfos\":\[\]\},\"ties\":\[\]\}/ims")
        ) {
            throw new CheckRetryNeededException(3, 1);
        }

        if (
            $this->http->currentUrl() == 'https://www.lufthansa.com/service/secured/api/users/current'
            && $this->http->Response['code'] == 403
            && $this->http->FindSingleNode('//title[contains(text(), "Access Denied")]')
            && (isset($this->http->Response['errorCode']) && strstr($this->http->Response['errorCode'], 'Network error 28 - Operation timed out after'))
        ) {
            throw new CheckRetryNeededException(3, 5);
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        $this->logger->notice(__METHOD__);
        $this->logger->debug('[Parse start date: ' . date('Y/m/d H:i:s') . ']');
        $startTimer = $this->getTime();

        $this->parseProperties();

        if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR && !$this->allowSomeAccount()) {
            if ((
                    !empty($this->Properties['Name'])
                    || $this->http->FindPreg("/\"usertype\":\"LIDONLY\",\"connectmode\":\"WT_UNDEF\"/")
                    || $this->http->FindPreg("/\{\"error\":\"No MAM User\"\}/")
                )
                || (isset($this->Properties['Status']) && $this->Properties['Status'] == 'Non Miles & More member')
            ) {
                unset($this->Properties['Status']);
                $this->logger->notice('Non Miles & More member');
                $this->SetWarning(self::NOT_MEMBER_MSG);
            }
        }// if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR && !empty($this->Properties['Name']))

        $this->http->RetryCount = 0;
        $this->http->GetURL("https://www.lufthansa.com/service/secured/api/core/user/accountstatement?showHistory=false", $this->apiHeaders);
        $this->http->RetryCount = 2;
        $response = $this->http->JsonLog();

        if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR && $this->allowSomeAccount()) {
            $this->parseProperties();

            if (in_array($this->http->Response['code'], [403, 500]) || $this->ErrorCode == ACCOUNT_ENGINE_ERROR) {
                $this->http->Response['body'] = json_encode($this->mainInfo);
                $response = $this->mainInfo;

                if (isset($response->milesInfo)) {
                    foreach ($response->milesInfo->amountSummaries as $amountSummary) {
                        switch ($amountSummary->enhancedCurrency) {
                        case 'AWD':
                            $this->SetBalance($amountSummary->amount ?? null);

                            break;

                        case 'STA':
                            $this->SetProperty("StatusMiles", $amountSummary->amount ?? null);

                            break;
                    }
                    }
                }
            }// if (in_array($this->http->Response['code'], [403, 500]))
        } elseif ($this->ErrorCode == ACCOUNT_ENGINE_ERROR && isset($response->error) && $response->error == 'No MAM User') {
            $this->logger->notice('Non Miles & More member');
            $this->SetWarning(self::NOT_MEMBER_MSG);
        }

        // HON Circle miles
        $visible = $response->maminfo->topMiles->visible ?? $response->milesInfo->topMiles->visible ?? false;

        if ($visible) {
            $this->SetProperty("CircleMiles", $response->maminfo->topMiles->value ?? $response->milesInfo->topMiles->value ?? null);
        }
        // Account number - Miles & More customer number
        $this->SetProperty("CustomerNumber", $response->maminfo->accountNumber ?? $response->milesInfo->accountNumber ?? null);
        // Status valid until
        $statusValidUntil = preg_replace('/(\d{4})(\d{2})(\d{2})/', '$1/$2/$3', $response->maminfo->currentStatusExpiryDate ?? $response->milesInfo->currentStatusExpiryDate ?? null);
        $this->logger->debug("Status valid until: {$statusValidUntil} / " . strtotime($statusValidUntil));

        if ($statusValidUntil && strtotime($statusValidUntil) < strtotime("+5 year")) {
            $this->SetProperty("Statusvalidityuntil", date("F Y", strtotime($statusValidUntil)));
        }
        // Status frequencies
        $visible = $response->maminfo->statusFrequencies->visible ?? $response->milesInfo->statusFrequencies->visible ?? false;

        if ($visible) {
            $this->SetProperty("FlightSegments", $response->maminfo->statusFrequencies->value ?? $response->milesInfo->statusFrequencies->value ?? null);
        }

        if (preg_match("/To achieve (?:the )?Frequent Traveller status, you still need (?:to accumulate )?([\d\,]+) (?:additional )?status miles or (?:fly )?([\d\,]+) (?:additional )?flight segment/ims", $this->http->Response['body'], $matches)
                or preg_match('#Um den Frequent Traveller Status zu erreichen, benötigen Sie noch ([\d,]+) Statusmeilen oder ([\d,]+) Flugsegmente#', $this->http->Response['body'], $matches)
                or preg_match('#To achieve the .+ status, you will need to earn ([\d,]+) additional [a-z\s]+ miles between#i', $this->http->Response['body'], $matches)
                or preg_match('#Um d.+ Status zu erreichen, benötigen Sie noch ([\d,]+) Statusmeilen im Zeitraum#i', $this->http->Response['body'], $matches)) {
            if (isset($matches[1])) {
                $this->SetProperty("StatusMilesNeeded", $matches[1]);
            }

            if (isset($matches[2])) {
                $this->SetProperty("SegmentsNeeded", $matches[2]);
            }
        }

        if ($circleMilesNeeded = $this->http->FindPreg('#Um den HON Circle Status zu erreichen, benötigen Sie noch ([\d,]+) HON Circle Meilen#i')) {
            $this->SetProperty('CircleMilesNeeded', $circleMilesNeeded);
        }

        if ($statusMilesToRetainStatus = $this->http->FindPreg('#To extend your Senator status, you will need to earn ([\d,]+) additional status miles between#i')) {
            $this->SetProperty('StatusMilesToRetainStatus', $statusMilesToRetainStatus);
        }

        if ($circleMilesToRetainStatus = $this->http->FindPreg('#Pour prolonger votre statut HON Circle, vous devez cumuler encore ([\d,]+) HON Circle Miles entre#i')) {
            $this->SetProperty('CircleMilesToRetainStatus', $circleMilesToRetainStatus);
        }

        $expires = $response->maminfo->statusMessages ?? $response->milesInfo->statusMessages ?? [];
        $value = 0;
        $date = 0;

        foreach ($expires as $expire) {
            if (preg_match("/([\d\,]+) award miles expire on ([\d\-\/.]+)\./ims", $expire, $matches)) {
                if (isset($matches[2])) {
                    $this->logger->debug(var_export($matches, true), ['pre' => true]);

                    if (strstr($matches[2], '/')) {
                        $exp = explode('/', $matches[2]);
                        $matches[2] = $exp[0] . "-" . $exp[2] . "-" . $exp[1];
                    }
                }// if (isset($matches[2]))
            }// if (preg_match("/([\d\,]+) award miles expire on ([\d\-\/.]+)\./ims"
            // Deutsch
            elseif (preg_match("/([\d\,]+) .+ verfallen zum ([\d\-\/.]+)\./ims", $expire, $matches)) {
                $this->logger->debug(var_export($matches, true), ['pre' => true]);
            } elseif (empty($value)) {
                $this->logger->notice(">>> Expiration date is not found");
                /**
                 * TODO: without any checks exclusively for Lufthansa // refs #17670
                 * We delete the expiration date because it’s not displayed on the provider’s website, herefore, it might be outdated.
                 */
                $this->ClearExpirationDate();
            }
            // Filter dates
            if (isset($matches[2])) {
                $expTime = strtotime($matches[2]);
                $this->logger->debug("expTime: $expTime ");
                $this->logger->debug("date: $date ");

                if ($expTime && ($expTime < $date || $date == 0)) {
                    $date = $expTime;

                    if (isset($matches[1])) {
                        $value = $matches[1];
                    }
                }// if ($expTime && ($expTime < $date || $date == 0))
            }// if (isset($matches[2]))

            if ($value != 0 && $date != 0) {
                $this->SetProperty("MilesToExpire", $value);
                $this->SetExpirationDate($date);
            }// if ($value != 0 && $date != 0)
        }// foreach ($expires as $expire)

        if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR) {
            // Account Statement not available
            if ($this->http->Response['code'] == 400 && $this->http->Response['body'] == 'Account Statement not available') {
                $this->SetWarning("Account Statement not available");
            }
            // AccountID: 5683057
            if ($this->http->FindPreg("/^\{\"error\":\"No MAM User\",\"errors\":\[\],\"validationErrors\":\[\]\}/")) {
                $this->logger->notice('Non Miles & More member');
                $this->SetWarning(self::NOT_MEMBER_MSG);
            }
        }// if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR && !empty($this->Properties['Name']))

        $this->getTime($startTimer);
    }

    public function GetRedirectParams($targetURL = null)
    {
        $arg = parent::GetRedirectParams($targetURL);

        // login to get logout link
        $this->http->PostForm();
        $logoutLink = $this->http->FindSingleNode("//a[@class = 'link_logout']/@href");

        if (!empty($logoutLink)) {
            $arg["CookieURL"] = "https://www.lufthansa.com" . $logoutLink;
        }

        return $arg;
    }

    public function ParseItineraries()
    {
        $this->http->setHttp2(true);
        $this->logger->notice(__METHOD__);

        if (!$this->checkReservations) {
            $this->logger->error('Selenium entrance failed');
            //$this->sendNotification('Failed My bookings// MI');
            return [];
        }

        $this->logger->debug('[Parse start date: ' . date('Y/m/d H:i:s') . ']');
        $startTimer = $this->getTime();

        $this->http->PostURL($this->urlReservations, trim(strstr($this->urlReservations, '?'), '?') . "&layout=L");
        $form = $this->redirectHelperForm();

        if ($this->http->Response['code'] == 200) {
            $this->redirectHelperForm();
        }

        $noItineraries = false;

        if ($this->http->ParseForm("APIS_ELIGIBILITY_FORM")) {
            $this->ParseItinerariesForm();
            $this->sendNotification('step one // MI');
        } else {
            $noItineraries = (bool) $this->http->FindPreg("/\"bookingList\":\[\]/");
            $this->logger->debug("[No itineraries: {$noItineraries}]");
            $result = $this->ParseItinerariesJson($form);
        }

        if (count($this->itinerariesMaster->getItineraries()) === 0 && !$noItineraries) {
            $this->http->GetURL(
                "https://www.lufthansa.com/service/secured/api/core/booking/bookinglist",
                [
                    'Accept'  => 'application/json, text/plain, */*',
                    'Referer' => 'https://www.lufthansa.com/us/en/homepage',
                    // for bookingList
                    'x-content-type-options' => 'nosniff',
                    'x-dns-prefetch-control' => 'on',
                    'x-frame-options'        => 'SAMEORIGIN',
                    'x-portal'               => 'LH',
                    'x-portal-countryid'     => '',
                    'x-portal-language'      => 'en',
                    'x-portal-site'          => 'US',
                    'x-portal-taxonomy'      => '',
                    'x-sec-clge-req-type'    => 'ajax',
                    'x-xss-protection'       => '1; mode=block',
                ]
            );
            $noItineraries = $this->http->FindPreg("/\"bookings\"\s*:\s*\[\s*\]/");
            $resBookings = $this->http->JsonLog();

            if (isset($resBookings->bookings) && is_array($resBookings->bookings) && !empty($resBookings->bookings)) {
                //$this->sendNotification('bookinglist // MI');
                $parsedConfs = [];
                /*$baseHeaders = [
                    'Accept-Encoding' => 'gzip, deflate, br',
                    'Accept' => 'application/json, text/plain, * / *',
                    'Referer' => 'https://www.lufthansa.com/de/en/login?deeplinkRedirect=true',
                    'X-Content-Type-Options' => 'nosniff',
                    'X-Dns-Prefetch-Control' => 'on',
                    'X-Frame-Options' => 'SAMEORIGIN',
                    'X-Portal' => 'LH',
                    'X-Portal-Countryid' => '',
                    'X-Portal-Language' => 'en',
                    'X-Portal-Site' => 'DE',
                    'X-Portal-Taxonomy' => '',
                    'X-Sec-Clge-Req-Type' => 'ajax',
                    'X-Xss-Protection' => '1; mode=block'
                ];*/
                try {
                    //$this->http->removeCookies();
                    $selenium = $this->itinerarySelenium();
                    $i = 0;
                    $failedBookingList = [];

                    foreach ($resBookings->bookings as $booking) {
                        $i++;

                        if (isset($booking->filekey) && isset($booking->name)) {
                            $this->logger->info(sprintf('[%s] Parse Itinerary #%s', $this->currentItin++,
                                $booking->filekey), ['Header' => 3]);
                            $arFields = [
                                'ConfNo'    => $booking->filekey,
                                //'FirstName' => $resBookings->firstName,
                                'LastName'  => $resBookings->lastName,
                            ];
                            $this->logger->debug('Parsed Locators:');
                            $this->logger->debug(var_export($this->parsedLocators, true));

                            if (!in_array($booking->filekey, $this->parsedLocators)) {
                                //$this->retrieveLufthansaAndSwiss($arFields, $selenium);
                                if ($i % 3 == 0) {
                                    $this->logger->notice('Increase time limit: 300');
                                    $this->increaseTimeLimit(300);
                                }
                                $message = $this->retrieveSeleniumFormNew($selenium, $arFields);

                                if (is_string($message)) {
                                    $this->logger->error($message);

                                    continue;
                                } elseif ($message === false) {
                                    $failedBookingList[] = $booking;

                                    continue;
                                }

                                /*$cookies = $selenium->driver->manage()->getCookies();

                                foreach ($cookies as $cookie) {
                                    $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'],
                                        $cookie['expiry'] ?? null);
                                }*/
                                $response = $this->http->JsonLog();
                                $this->parseItinerariesJsonNew($response);
                            }
                        }
                    }
                    $this->logger->notice('Failed booking: ' . count($failedBookingList));

                    foreach ($failedBookingList as $booking) {
                        $i++;

                        if (isset($booking->filekey) && isset($booking->name)) {
                            $this->logger->info(sprintf('[%s] Parse Itinerary #%s', $this->currentItin++,
                                $booking->filekey), ['Header' => 3]);
                            $arFields = [
                                'ConfNo'    => $booking->filekey,
                                //'FirstName' => $resBookings->firstName,
                                'LastName'  => $resBookings->lastName,
                            ];
                            $this->logger->debug('Parsed Locators:');
                            $this->logger->debug(var_export($this->parsedLocators, true));

                            if (!in_array($booking->filekey, $this->parsedLocators)) {
                                //$this->retrieveLufthansaAndSwiss($arFields, $selenium);
                                if ($i % 3 == 0) {
                                    $this->logger->notice('Increase time limit: 300');
                                    $this->increaseTimeLimit(300);
                                }
                                $message = $this->retrieveSeleniumFormNew($selenium, $arFields);

                                if (is_string($message)) {
                                    $this->logger->error($message);

                                    continue;
                                } elseif ($message === false) {
                                    continue;
                                }
                                $response = $this->http->JsonLog();
                                $this->parseItinerariesJsonNew($response);
                            }
                        }
                    }

//                    if ($this->CanSwiss()) {
//                        $this->logger->info("Swiss site", ['Header' => 2]);
//                        $this->logger->notice('Trying Swiss site');
//                        $this->sendNotification('trying Swiss site // MI');
//
//
//                    }
                } catch (ScriptTimeoutException $e) {
                    $this->logger->error("ScriptTimeoutException exception: " . $e->getMessage());
                } catch (WebDriverCurlException $e) {
                    $this->logger->error("WebDriverCurlException exception: " . $e->getMessage());
                } catch (StaleElementReferenceException $e) {
                    $this->logger->error("StaleElementReferenceException exception: " . $e->getMessage(), ['HtmlEncode' => true]);
                } catch (NoSuchDriverException | UnknownServerException | UnexpectedJavascriptException | NoSuchWindowException $e) {
                    $this->logger->error("Exception: " . $e->getMessage());
                } finally {
                    // close Selenium browser

                    if (isset($selenium)) {
                        $selenium->http->cleanup();
                    }
                }
            }
        }

        if (empty($this->itinerariesMaster->getItineraries()) && $noItineraries) {
            $this->itinerariesMaster->setNoItineraries(true);
        }

        $this->logger->info('ParseItineraries:');
        $this->getTime($startTimer);

        return [];
    }

    public function ConfirmationNumberURL($arFields)
    {
        //return "https://www.lufthansa.com/de/en/login?deeplinkRedirect=true";
        return 'https://shop.lufthansa.com/booking/manage-booking/retrieve';
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
            /*"FirstName" => [
                "Type"     => "string",
                "Size"     => 40,
                "Value"    => $this->GetUserField('FirstName'),
                "Required" => false,
            ],*/
            "LastName" => [
                "Type"     => "string",
                "Size"     => 40,
                "Value"    => $this->GetUserField('LastName'),
                "Required" => true,
            ],
        ];
    }

    public function CheckConfirmationNumberInternal($arFields, &$it)
    {
        $this->http->setHttp2(true);
        $this->setProxyGoProxies();
//        $it = [];
//        $itinError = $this->retrieveLufthansaAndSwiss($arFields, $it);
        $itinError = $this->retrieveLufthansaAndSwissNew($arFields);

        return $itinError;
    }

    public function GetHistoryColumns()
    {
        return [
            "Date"                   => "PostingDate",
            "Description"            => "Description",
            "Award miles"            => "Miles",
            "Status miles"           => "Info",
            "Executive Bonus"        => "Bonus",
            "Status&HONCircle Miles" => "Info",
        ];
    }

    public function ParseHistory($startDate = null)
    {
        $this->logger->debug('[History start date: ' . ((isset($startDate)) ? date('Y/m/d H:i:s', $startDate) : 'all') . ']');
        $result = [];
        $startTimer = $this->getTime();

        $this->increaseTimeLimit(100);
        // current statement
        $this->http->GetURL("https://www.lufthansa.com/service/secured/api/core/user/accountstatement?showHistory=false", $this->apiHeaders);
        $startIndex = sizeof($result);
        $result = array_merge($result, $this->ParsePageHistoryV2($startIndex, $startDate));
        // account history
        if (!$this->endHistory) {
            $this->http->GetURL("https://www.lufthansa.com/service/secured/api/core/user/accountstatement?showHistory=true", $this->apiHeaders);
            $startIndex = sizeof($result);
            $result = array_merge($result, $this->ParsePageHistoryV2($startIndex, $startDate));
        }

        $this->getTime($startTimer);

        return $result;
    }

    public function loginMAM()
    {
        $this->logger->notice(__METHOD__);
        $this->logger->debug('[Parse start date: ' . date('Y/m/d H:i:s') . ']');
        $startTimer = $this->getTime();
        $login = false;
        $retry = false;
        /** @var TAccountCheckerLufthansa */
        $selenium = clone $this;
        $this->http->brotherBrowser($selenium->http);

        try {
            $this->logger->notice("Running Selenium...");
            $selenium->UseSelenium();
            $resolutions = [
                [1152, 864],
                [1280, 720],
                [1280, 768],
                [1280, 800],
                [1360, 768],
                [1920, 1080],
            ];
            $resolution = $resolutions[array_rand($resolutions)];
            $this->DebugInfo = "Resolution: " . implode("x", $resolution);
            $selenium->setScreenResolution($resolution);
            $selenium->useFirefox(\SeleniumFinderRequest::FIREFOX_53);
            $selenium->disableImages();
            $selenium->useCache();
            $selenium->http->start();
            $selenium->Start();
            $selenium->http->GetURL('https://www.miles-and-more.com/online/portal/mam/de/profilelogin?l=en&cid=18002');

            $loginInput = $selenium->waitForElement(WebDriverBy::xpath('//input[contains(@name, "_userid")]'), 10);
            $passwordInput = $selenium->waitForElement(WebDriverBy::xpath('//input[contains(@name, "_password")]'), 5);
            // save page to logs
            $this->http->SetBody($selenium->driver->executeScript('return document.documentElement.innerHTML'));
            $this->http->SaveResponse();

            if (!$loginInput || !$passwordInput) {
                $this->logger->error("Login or password input not found");

                return false;
            }
            $loginInput->sendKeys(str_replace(' ', '', $this->AccountFields['Login']));
            $passwordInput->sendKeys($this->AccountFields['Pass']);
            $selenium->driver->executeScript('
                var rememberme = document.querySelector(\'[id = "rememberme"]\').click();
                if (rememberme)
                    rememberme.click()
            ');
            $selenium->driver->executeScript('document.querySelector(\'form[id *= "_mam-usm-cardnr-form"] button.btn-primary\').click();');

            $consent = $selenium->waitForElement(WebDriverBy::id('PM_BUTTON_ACCEPT'), 5);

            if ($consent) {
                $consent->click();
            }
            $this->http->SetBody($selenium->driver->executeScript('return document.documentElement.innerHTML'));
            $this->http->SaveResponse();

            if ($selenium->waitForElement(WebDriverBy::id('logout'), 10, false)) {
                $login = true;
            } else {
                $this->logger->error('Could not log in');

                return false;
            }
            $this->http->SetBody($selenium->driver->executeScript('return document.documentElement.innerHTML'));
            $this->http->SaveResponse();

            $selenium->http->GetURL('https://www.miles-and-more.com/online/myportal/mam/rowr/account/my_bookings?nodeid=2476385&l=en&cid=10001');
            $selenium->waitForElement(WebDriverBy::xpath('//h2[contains(text(), "Bookings")]'), 5);

            $cookies = $selenium->driver->manage()->getCookies();

            foreach ($cookies as $cookie) {
                $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
            }

            $this->logger->debug("[Current Selenium URL]: {$selenium->http->currentUrl()}");
            $this->http->SetBody($selenium->driver->executeScript('return document.documentElement.innerHTML'));
            $this->http->SaveResponse();
        } catch (ScriptTimeoutException $e) {
            $this->logger->error("ScriptTimeoutException exception: " . $e->getMessage());
            // retries
            if (strstr($e->getMessage(), 'timeout: Timed out receiving message from renderer')) {
                $retry = true;
            }
        } catch (WebDriverCurlException $e) {
            $this->logger->error("WebDriverCurlException exception: " . $e->getMessage());
            // retries
            if (strstr($e->getMessage(), 'Curl error thrown for http POST')) {
                $retry = true;
            }
        } catch (StaleElementReferenceException $e) {
            $this->logger->error("StaleElementReferenceException exception: " . $e->getMessage(), ['HtmlEncode' => true]);
            // retries
            if (strstr($e->getMessage(), 'Element not found in the cache')
                || strstr($e->getMessage(), 'The element reference of')) {
                $retry = true;
            }
        } catch (NoSuchDriverException $e) {
            $this->logger->error("NoSuchDriverException exception: " . $e->getMessage());
            $retry = true;
        } catch (UnexpectedJavascriptException $e) {
            $this->logger->error("UnexpectedJavascriptException exception: " . $e->getMessage());
            $retry = true;
        } finally {
            // close Selenium browser
            $selenium->http->cleanup();
//            if ($retry && ConfigValue(CONFIG_SITE_STATE) != SITE_STATE_DEBUG) {
//                $this->logger->debug("[attempt]: {$this->attempt}");
//                throw new CheckRetryNeededException(3, 10);
//            }
        }

        $this->getTime($startTimer);

        return $login;
    }

    public function parseItinerariesJsonNew($response)
    {
        $this->logger->notice(__METHOD__);

        $conf = $response->selectedOrderId ?? $response->ids[0] ?? null;
        $f = $this->itinerariesMaster->add()->flight();

        if (!isset($conf)) {
            return null;
        }
        $f->general()->confirmation($conf, 'Booking reference');
        $data = $response->entities->{$conf};

        foreach ($data->travelers as $traveler) {
            foreach ($traveler->names as $name) {
                $f->general()->traveller(beautifulName("{$name->firstName} {$name->lastName}"));
            }
        }

        foreach ($data->frequentFlyerCards ?? [] as $card) {
            $f->program()->account($card->cardNumber, false);
        }

        if (isset($data->travelers) && !isset($data->air)) {
            $this->logger->error('Deleting a reservation due to missing segments');
            $this->itinerariesMaster->removeItinerary($f);

            return;
        }

        foreach ($data->air->bounds as $bound) {
            if (!isset($bound->flights)) {
                continue;
            }

            foreach ($bound->flights as $seg) {
                $flight = $seg->flight;
                $s = $f->addSegment();
                $s->airline()->name($flight->marketingAirlineCode);
                $s->airline()->number($flight->marketingFlightNumber);

                if (isset($flight->operatingAirline)) {
                    $s->airline()->operator($flight->operatingAirline);
                }

                $s->departure()->code($flight->departure->locationCode);
                $s->departure()->name($flight->departure->location->airportName ?? $flight->departure->location->cityName ?? null);
                $s->departure()->date2($flight->departure->dateTime);
                $s->departure()->terminal($flight->departure->terminal ?? null, false, true);

                $s->arrival()->code($flight->arrival->locationCode);
                $s->arrival()->name($flight->arrival->location->airportName ?? $flight->arrival->location->cityName ?? null);
                $s->arrival()->date2($flight->arrival->dateTime);
                $s->arrival()->terminal($flight->arrival->terminal ?? null, false, true);

                $s->extra()->aircraft($flight->aircraft ?? null, false, true);

                if (isset($flight->meals->bookingClass)) {
                    $s->extra()->bookingCode($flight->meals->bookingClass);
                }

                if ($flight->duration) {
                    $h = floor($flight->duration / 60);
                    $m = $flight->duration % 60;
                    $s->extra()->duration("{$h}h {$m}m");
                }
            }
        }

        if (isset($data->air->prices->totalPrices)) {
            foreach ($data->air->prices->totalPrices as $price) {
                if (isset($price->total->value) && $price->total->value > 0 && $price->total->currency->decimalPlaces > 0) {
                    $offset = strlen((string) $price->total->value) - $price->total->currency->decimalPlaces;
                    $total = $price->total->value;

                    if ($offset > 1) {
                        $total = substr_replace((string) $price->total->value, '.', $offset, 0);
                    }
                    $f->price()->total($total);
                    $f->price()->currency($price->total->currencyCode);

                    break;
                }
            }
        }
    }

    private function retrieveLufthansaAndSwissNew($arFields)
    {
        $this->logger->notice(__METHOD__);
        $message = $this->retrieveSelenium($arFields);

        if (is_string($message) || $message === false) {
            if (stristr($message, 'Unfortunately your booking') || stristr($message, 'Unfortunately, your booking cannot be displayed.')) {
                return $message;
            }

            return null;
        }
        $response = $this->http->JsonLog();
        $this->parseItinerariesJsonNew($response);

        return null;
    }

    private function CanSwiss()
    {
        $canSwiss = preg_match("/^\d+$/", $this->AccountFields["Login"]);
        $this->logger->notice("CanSwiss -> {$canSwiss}");

        return $canSwiss;
    }

    /**
     * provider big fix
     * site always returns 500 on $apiURL for these accounts.
     */
    private function allowSomeAccount(): bool
    {
        $this->logger->notice(__METHOD__);

//        if (in_array($this->AccountFields['Login'], ['milesmendoza'])) {
//            return true;
//        }

        return false;
    }

    private function selenium()
    {
        $this->logger->notice(__METHOD__);
        $this->logger->debug('[Parse start date: ' . date('Y/m/d H:i:s') . ']');
        $startTimer = $this->getTime();
        $login = false;
        $retry = false;
        $selenium = clone $this;
        $this->http->brotherBrowser($selenium->http);

        try {
            $this->logger->notice("Running Selenium...");
            $selenium->UseSelenium();
            $resolutions = [
                [1152, 864],
                [1280, 720],
                [1280, 768],
                [1280, 800],
                [1360, 768],
                [1920, 1080],
            ];
            $resolution = $resolutions[array_rand($resolutions)];
            $this->DebugInfo = "Resolution: " . implode("x", $resolution);
            $selenium->setScreenResolution($resolution);

            $selenium->useChromium();
            /*
            $selenium->useFirefox();
            $selenium->setKeepProfile(true);
            */

            $selenium->setProxyNetNut(null, "us", 'https://www.lufthansa.com/us/en/Homepage'); //todo

            $selenium->seleniumOptions->userAgent = null;

            $request = FingerprintRequest::chrome();
            $request->browserVersionMin = \HttpBrowser::BROWSER_VERSION_MIN;
            $fingerprint = $this->services->get(FingerprintFactory::class)->getOne([$request]);

            if ($fingerprint !== null) {
                $selenium->seleniumOptions->fingerprint = $fingerprint->getFingerprint();
                $selenium->http->setUserAgent($fingerprint->getUseragent());
                $selenium->seleniumOptions->setResolution([$fingerprint->getScreenWidth(), $fingerprint->getScreenHeight()]);
            }

            $selenium->http->saveScreenshots = true;
            // refs #12710
//            if ($this->attempt == 0) {
//                $selenium->useCache();
//            }
            $selenium->http->start();
            $selenium->Start();
            $selenium->driver->manage()->window()->maximize();

            try {
//                $selenium->http->GetURL('https://www.lufthansa.com/us/en/account-statement');
                $selenium->http->GetURL('https://www.lufthansa.com/us/en/homepage');
            } catch (TimeOutException | ScriptTimeoutException $e) {
                $this->logger->error("Exception: " . $e->getMessage(), ['HtmlEncode' => true]);

                try {
                    $selenium->driver->executeScript('window.stop();');
                } catch (Exception $e) {
                    $this->logger->error("Exception: " . $e->getMessage(), ['HtmlEncode' => true]);
                    $retry = true;
                }
            } catch (UnexpectedJavascriptException | StaleElementReferenceException $e) {
                $this->logger->error("Exception: " . $e->getMessage(), ['HtmlEncode' => true]);
            } catch (UnexpectedAlertOpenException $e) {
                $this->logger->error($e->getMessage());
                $error = $selenium->driver->switchTo()->alert()->getText();
                $this->logger->debug($error);

                if (stristr($error, 'is requesting a username and password. The site says: “Luminati”')) {
                    $this->DebugInfo = 'Proxy not enabled';

                    return false;
                }
            }// catch (UnexpectedAlertOpenException $e)
            catch (NoAlertOpenException $e) {
                $this->logger->debug("no alert, skip");
            }

            $pageLoaded = $selenium->waitForElement(WebDriverBy::xpath('//*[contains(@class, "btn-login")] | //button[span[contains(text(), "Log in now")]] | //a[contains(text(), "Log in now")] | //h2[contains(text(), "Pardon Our Interruption ...")] | //body[contains(text(), "Error 500: java.lang.Exception:")] | //p[contains(text(), "We apologise for the interruption. We detected unusual behaviour from your browser, which resembles that of a bot.")] | //button[@id = "cm-acceptAll"]'), 10);

            if ($pageLoaded && $pageLoaded->getText() == 'Agree') {
                $pageLoaded->click();

                $pageLoaded = $selenium->waitForElement(WebDriverBy::xpath('//*[contains(@class, "btn-login")] | //button[span[contains(text(), "Log in now")]] | //a[contains(text(), "Log in now")] | //h2[contains(text(), "Pardon Our Interruption ...")] | //body[contains(text(), "Error 500: java.lang.Exception:")] | //p[contains(text(), "We apologise for the interruption. We detected unusual behaviour from your browser, which resembles that of a bot.")]'), 10);
            }

            // save page to logs
            $this->savePageToLogs($selenium);

            $this->logger->debug("page Loaded");
            $loading = false;

            if (!$pageLoaded) {
                $loading = $selenium->waitForElement(WebDriverBy::xpath("
                    //div[contains(text(), 'Loading …')]
                    | //div[@class = 'loading-frames']
                    | //span[contains(text(), 'My booking')]
                "), 0);
            }

            if ($loading) {
                try {
                    $selenium->http->GetURL('https://www.lufthansa.com/us/en/account-statement');
                } catch (TimeOutException $e) {
                    $this->logger->error("Exception: " . $e->getMessage(), ['HtmlEncode' => true]);

                    try {
                        $selenium->driver->executeScript('window.stop();');
                    } catch (Exception $e) {
                        $this->logger->error("Exception: " . $e->getMessage(), ['HtmlEncode' => true]);
                    }
                } catch (
                    Facebook\WebDriver\Exception\UnknownErrorException
                    | UnknownServerException
                    $e
                ) {
                    $this->logger->error("Exception: " . $e->getMessage(), ['HtmlEncode' => true]);
                }
                $pageLoaded = $selenium->waitForElement(WebDriverBy::xpath('//*[contains(@class, "btn-login")] | //button[span[contains(text(), "Log in now")]] | //a[contains(text(), "Log in now")] | //h2[contains(text(), "Pardon Our Interruption ...")] | //body[contains(text(), "Error 500: java.lang.Exception:")] | //p[contains(text(), "We apologise for the interruption. We detected unusual behaviour from your browser, which resembles that of a bot.")]'), 10);
                // save page to logs
                $this->savePageToLogs($selenium);
            }

            $loginlayer = $selenium->waitForElement(WebDriverBy::xpath('//*[contains(@class, "btn-login")]'), 0);

            try {
                $this->logger->debug("find btn");
                $btnFound = !$loginlayer && $selenium->waitForElement(WebDriverBy::xpath('//button[span[contains(text(), "Log in now")]] | //a[contains(text(), "Log in now")]'), 0);
            } catch (StaleElementReferenceException $e) {
                $this->logger->error("StaleElementReferenceException exception: " . $e->getMessage(), ['HtmlEncode' => true]);
            } finally {
                if (!$loginlayer && !$btnFound) {
                    $loginlayer = $selenium->waitForElement(WebDriverBy::xpath('//*[contains(@class, "btn-login")]'), 0);
                    $btnFound = !$loginlayer && $selenium->waitForElement(WebDriverBy::xpath('//button[span[contains(text(), "Log in now")]] | //a[contains(text(), "Log in now")]'), 0);
                }
            }

            if ($btnFound) {
                $this->logger->debug("click by btn");
                $selenium->driver->executeScript('document.querySelector(\'.btn-sm\').click();');
            } elseif ($loginlayer) {
                clickByLoginLayer: $this->logger->debug("click by loginlayer");

                try {
                    $loginlayer->click();
                } catch (StaleElementReferenceException | Facebook\WebDriver\Exception\StaleElementReferenceException $e) {
                    $this->logger->error("StaleElementReferenceException: " . $e->getMessage(), ['HtmlEncode' => true]);
                    $this->savePageToLogs($selenium);
                    $loginlayer = $selenium->waitForElement(WebDriverBy::xpath('//*[contains(@class, "btn-login")]'), 5);

                    if ($loginlayer) {
                        goto clickByLoginLayer;
                    }

                    return false;
                }
            } else {
                $this->logger->error("form not found");

                // provider bug workaround
                if (!$pageLoaded && $selenium->waitForElement(WebDriverBy::xpath('//body[contains(text(), "Error 500: java.lang.Exception:")]'), 0)) {
                    $this->logger->error("Try to fix provider bug");
                    $selenium->http->GetURL('https://www.lufthansa.com/us');
                    $selenium->waitForElement(WebDriverBy::xpath('//*[contains(@class, "btn-login")] | //h2[contains(text(), "Pardon Our Interruption ...")] | //p[contains(text(), "We apologise for the interruption. We detected unusual behaviour from your browser, which resembles that of a bot.")]'), 10);
                }
                // funCaptcha in selenium
                if ($selenium->waitForElement(WebDriverBy::id('distilCaptchaForm'), 0)) {
                    $retry = true;
                    // save page to logs
                    $this->savePageToLogs($selenium);
                }// if ($selenium->waitForElement(WebDriverBy::id('distilCaptchaForm'), 0))
                // The account page is temporarily unavailable. We are currently solving this problem and apologize for any inconvenience.
                $error = $selenium->waitForElement(WebDriverBy::xpath("//p[contains(text(), 'We are currently solving this problem and apologize for any inconvenience.')]"), 0);

                if (
                    $this->http->FindPreg("/^<head><\/head><body><\/body>$/")
                    || $this->http->FindSingleNode("//pre[contains(text(), '{$this->AccountFields['ProviderCode']} | {$this->AccountFields['Login']} | ')]")
                    || $this->http->FindSingleNode("//pre[contains(text(), 'Not found')]")
                    || $this->http->FindSingleNode("//p[contains(text(), 'Health check')]")
                    || $this->http->FindSingleNode("//span[contains(text(), 'This site can’t be reached')]")
                ) {
                    $retry = true;
                } else { // save page to logs
                    $this->savePageToLogs($selenium);
                }

                if ($error) {
                    throw new CheckException("The account page is temporarily unavailable. We are currently solving this problem and apologize for any inconvenience.", ACCOUNT_PROVIDER_ERROR);
                }

                // retry
                if ($selenium->waitForElement(WebDriverBy::xpath("
                        //div[contains(text(), 'Loading …')]
                        | //div[@class = 'loading-frames']
                        | //h1[contains(text(), 'Access Denied')]
                    "), 0)
                ) {
                    $this->markProxyAsInvalid();
                    $retry = true;
                }

                return false;
            }// if (!$loginlayer)

            sleep(2);
            $loginInput = $selenium->waitForElement(WebDriverBy::xpath('//input[@name = "loginFormQuery.j_username" or @name = "emailLoginStepOne"]'), 7);

            // it helps
            if (!$loginInput && $loginlayer = $selenium->waitForElement(WebDriverBy::xpath('//*[contains(@class, "btn-login")]'), 0)) {
                $this->savePageToLogs($selenium);
                $loginlayer->click();

                sleep(2);
                $loginInput = $selenium->waitForElement(WebDriverBy::xpath('//input[@name = "loginFormQuery.j_username" or @name = "emailLoginStepOne"]'), 7);
                $this->savePageToLogs($selenium);
            }

            if (filter_var($this->AccountFields['Login'], FILTER_VALIDATE_EMAIL) === false) {
                $this->logger->notice(">>> Switch to Service card number login");
                $loginSwitcher = $selenium->waitForElement(WebDriverBy::xpath('//button[contains(., "Service card number login")]'), 3);
                $this->savePageToLogs($selenium);

                if ($loginSwitcher) {
                    $loginSwitcher->click();
                } else {
                    $this->logger->error('Cant find button to go to cards');
                }

                $loginInput = $selenium->waitForElement(WebDriverBy::xpath('//input[@name = "mamLoginStepOne"]'), 3);
            }

            if (!$loginInput) {
                $this->logger->error("something went wrong");
                $this->savePageToLogs($selenium);

                if (
                    $this->http->FindSingleNode("//span[
                        contains(text(), 'This site can’t be reached')
                        or contains(text(), 'This page isn’t working')
                    ]
                    | //h1[
                        contains(text(), 'Access Denied')
                        or contains(text(), 'The connection has timed out')
                        or contains(text(), 'Secure Connection Failed')
                    ]
                    ")
                ) {
                    $selenium->markProxyAsInvalid();
                    $retry = true;
                }

                return false;
            }

            $this->logger->debug("set login");
            $loginInput->sendKeys(str_replace(' ', '', $this->AccountFields['Login']));

            $selenium->driver->executeScript('$(\'.travelid-login__continueButton\').click();');

            $passwordInput = $selenium->waitForElement(WebDriverBy::xpath('//input[@name = "emailLoginStepTwo" or @name = "mamLoginStepTwoPassword" or @name = "mamLoginStepTwoPin"]'), 5, false);
            // save page to logs
            $this->savePageToLogs($selenium);

            if (!$passwordInput) {
                $this->logger->error("something went wrong");

                return false;
            }

            $this->logger->debug("set pass via jq");
            $selenium->driver->executeScript('
                $(\'[name = "mamLoginStepTwoPassword"], [name = "emailLoginStepTwo"], [name = "mamLoginStepTwoPin"]\').val(\'' . str_replace(['\\', "'"], ['\\\\', "\'"], $this->AccountFields['Pass']) . '\');
            ');

//            $selenium->driver->executeScript('
//                document.querySelector(\'[name = "mamLoginStepTwoPassword"], [name = "emailLoginStepTwo"], [name = "mamLoginStepTwoPin"]\').value = \'' . str_replace(['\\', "'"], ['\\\\', "\'"], $this->AccountFields['Pass']) . '\';
//            ');
            $this->savePageToLogs($selenium);

            $this->logger->debug("click 'Sign In'");
//            $button->click();
            sleep(1);
            $selenium->driver->executeScript('
                var loginBtn = $(\'.travelid-login__loginButton:not(:hidden)\').click();
                loginBtn.click();
            ');

            $sleep = 40;
            $startTime = time();
            $time = time() - $startTime;

            while (($time < $sleep) && !$login) {
                $this->logger->debug("(time() - \$startTime) = {$time} < {$sleep}");

                if ($skipBtn = $selenium->waitForElement(WebDriverBy::xpath('//button[@name = "welcome_skipMigration"]'), 0)) {
                    $skipBtn->click();
                    sleep(2);
                }

                try {
                    $logout = $selenium->waitForElement(WebDriverBy::xpath("//span[@id = 'lh-loginModule-name']"), 0);
                    $this->savePageToLogs($selenium);
                } catch (Facebook\WebDriver\Exception\StaleElementReferenceException $e) {
                    $this->logger->error("Exception: " . $e->getMessage());
                }

                if ($logout
                    || $this->http->FindSingleNode("//div[@class = 'user-name']/div[contains(@class, 'heading-small')]")) {
                    $login = true;

                    $this->markProxySuccessful();
                    // watchdog workaround
                    $this->increaseTimeLimit(100);

                    $this->http->FilterHTML = false;
                    // save page to logs
                    $this->savePageToLogs($selenium);
                    $this->mainInfo = $this->http->JsonLog($this->http->FindSingleNode($this->mainInfoXpath));
                    $this->http->FilterHTML = true;

                    break;
                }

                // provider bug workaround
                if ($selenium->waitForElement(WebDriverBy::xpath('//body[contains(text(), "Error 500: java.lang.Exception:")]'), 0)) {
                    $selenium->http->GetURL($selenium->http->currentUrl());
                }

                if ($error = $selenium->waitForElement(WebDriverBy::xpath('//section[contains(@class, "travelid-login__error") and not(@hidden)] | //form[not(@hidden)]//p[contains(@class, "travelid-form__elementValidationMessage")]/span[not(@hidden)] | //p[@class = "travelid-form__errorBoxContentItemText"] | //p[@class="pageHeading" and contains(text(), "Access Denied")] | //h2[contains(text(), "A technical problem has occurred.")] | //div[contains(text(), "Currently a lot of users are trying to use this process. Please re-try at a later time")] | //h1[contains(text(), "The connection has timed out")]'), 0)) {
                    $message = $error->getText();
                    $this->savePageToLogs($selenium);
                    $this->logger->error("[Error]: {$message}");

                    if (
                        $message == 'Please enter your password.'
                        && ($passwordInput = $selenium->waitForElement(WebDriverBy::xpath('//input[@name = "emailLoginStepTwo" or @name = "mamLoginStepTwoPassword" or @name = "mamLoginStepTwoPin"]'), 0))
                    ) {
                        $this->logger->debug("Try to enter password via selenium methods");
                        $passwordInput->sendKeys($this->AccountFields['Login']);
                        sleep(1);
                        $selenium->driver->executeScript('
                            var loginBtn = $(\'.travelid-login__loginButton:not(:hidden)\').click();
                            loginBtn.click();
                        ');
                        sleep(2);

                        continue;
                    }

                    if (
                        // It is not possible to log in. Please contact your local Miles & More Service Team.
                        strstr($message, 'It is not possible to log in.')
                        || $message == 'Wrong username or password'
                        || $message == 'Only numbers (0-9) are permitted.'
                        || $message == 'Your password has expired. Please change your password.'
                        || $message == 'Your service card number consists of 15 digits.'
                        || $message == 'Your account is not currently active. Please contact your local Miles & More Service Team to reactivate your account.'
                        || $message == 'Please check your login data. Login with Lufthansa iD, austrian.com profile and swiss.com profile is no longer possible. Register for Travel ID.'
                        || $message == 'Please check your login data.'
                        || $message == 'Only digits (0-9) are permitted.'
                    ) {
                        throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
                    }

                    if (
                        strstr($message, 'A technical error has occurred.')
                        || $message == 'An error occurred'
                        || $message == 'A technical problem has occurred.'
                        || $message == 'Currently a lot of users are trying to use this process. Please re-try at a later time'
                    ) {
                        throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
                    }

                    if (
                        $message == 'Access Denied'
                        || $message == 'The connection has timed out'
                    ) {
                        $selenium->markProxyAsInvalid();
                        $retry = true;
                    }

                    $this->DebugInfo = $message;

                    break;
                }

                // Everything under one roof: the new Travel ID
                if ($selenium->waitForElement(WebDriverBy::xpath('//button[@name = "welcome_startMigration"]'), 0)) {
                    break;
                }

                if ($message = $this->http->FindPreg('/^<head><link[^>]+><\/head><body><pre>\{"error":"(Service Unavailable)","requestId":"[^\"]+\"\}<\/pre>/')
                    ?? $this->http->FindPreg('/^<head><link[^>]+><\/head><body><pre>\{"error":"ServiceUnavailable","error_description":"(service is currently unavailable)",/')
                ) {
                    throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
                }

                // save page to logs
                $this->savePageToLogs($selenium);
                $time = time() - $startTime;
            }// while (((time() - $startTime) < $sleep) && !$login)

            $delay = 0;

            if (isset($message) && $message == 'An error occured') {
                $delay = 5;
            }

            $welcome_startMigration = $selenium->waitForElement(WebDriverBy::xpath('//button[@name = "welcome_startMigration"]'), $delay);
            $this->savePageToLogs($selenium);

            if ($welcome_startMigration || $this->http->FindSingleNode('//button[@name = "welcome_startMigration"]')) {
                $selenium->driver->executeScript('document.querySelector(\'a.travelid-hallway__backlinkAnchor\').click();');
                $logout = $selenium->waitForElement(WebDriverBy::xpath("//span[@id = 'lh-loginModule-name']"), 10);
                $this->savePageToLogs($selenium);

                if (!$logout && $this->http->FindSingleNode("//h2[contains(text(), 'You’re being logged in')]")) {
                    $logout = $selenium->waitForElement(WebDriverBy::xpath("//span[@id = 'lh-loginModule-name']"), 10);
                    $this->savePageToLogs($selenium);
                }

                if (!$logout
                    && (
                        $this->http->FindSingleNode("//span[contains(text(), 'Login and Register') or contains(text(), 'Login &amp; Registration') or contains(text(), 'Login & Registration')]")
                        || $this->http->FindPreg("/>Login\s*\&amp;\s*Registration<\/span><\/span><\/a><\/div><\/div>/ims")
                        || $selenium->waitForElement(WebDriverBy::xpath("//span[contains(text(), 'Login and Register') or contains(text(), 'Login &amp; Registration') or contains(text(), 'Login & Registration')]"), 0)
                    )
                ) {
                    $this->throwProfileUpdateMessageException();
                }
            }

            if (!$login) {
                // 2 - funCaptcha, debug
                // 3 - Validating JavaScript Engine, debug
                // 4 - Unable to connect
                if ($selenium->waitForElement(WebDriverBy::xpath("
                        //span[@id = 'lh-loginModule-name']
                        | //h2[contains(text(), 'Pardon Our Interruption ...')]
                        | //h2[contains(text(), 'Validating JavaScript Engine')]
                        | //h2[contains(text(), 'Unable to connect')]
                    "), 5)
                    || $this->http->FindSingleNode("//div[@class = 'user-name']/div[contains(@class, 'heading-small')]")) {
                    $login = true;
                }
                // auth failed
                elseif (
                    $selenium->http->currentUrl() == 'https://www.lufthansa.com/us/en/Homepage'
                    || $this->http->FindSingleNode("//*[self::span or self::h1][contains(text(), 'This site can’t be reached')]")
                    || $this->http->FindSingleNode("//*[self::span or self::h1][contains(text(), 'The connection has timed out')]")
                    || $this->http->FindSingleNode("//*[self::span or self::h1][contains(text(), 'Secure Connection Failed')]")
                    || $this->http->FindSingleNode('//p[contains(text(), "We apologise for the interruption. We detected unusual behaviour from your browser, which resembles that of a bot.")]')
                    || $selenium->waitForElement(WebDriverBy::xpath('//button[span[contains(text(), "Log in now")]] | //a[contains(text(), "Log in now")]'), 0)
                ) {
                    $selenium->markProxyAsInvalid();
                    $retry = true;
                } elseif (
                    $this->http->FindSingleNode("//span[contains(text(), 'Login and Register') or contains(text(), 'Login &amp; Registration') or contains(text(), 'Login & Registration')]")
                    || $this->http->FindPreg("/>Login\s*\&amp;\s*Registration<\/span><\/span><\/a><\/div><\/div>/ims")
                    || $selenium->waitForElement(WebDriverBy::xpath("//span[contains(text(), 'Login and Register') or contains(text(), 'Login &amp; Registration') or contains(text(), 'Login & Registration')]"), 0)
                ) {
                    $retry = true;
                }
                // symbol '%' in password
//                elseif (in_array($this->AccountFields['Login'], ['michelluescher', 'sethanagnostis', 'dorronlemesh', 'ahaugaard', 'ChHindersmann']))
                elseif (
                    strstr($this->AccountFields['Pass'], '%')
                    || in_array($this->AccountFields['Login'], ['jamlamming', 'sweden06', 'allisonsmith96', 'sishering', 'DannyGatton88'])
                    // AccountId: 4253003, 4379760, 1730306
                    || (strstr($this->AccountFields['Pass'], '(') && strstr($this->AccountFields['Pass'], ')'))
                ) {
                    throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
                }
            }// if (!$login)

            $this->savePageToLogs($selenium);

            $cookies = $selenium->driver->manage()->getCookies();

            foreach ($cookies as $cookie) {
                $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
            }
            $this->logger->debug("[Current Selenium URL]: {$selenium->http->currentUrl()}");
            // save page to logs
            $this->savePageToLogs($selenium);

            if (!$this->mainInfo && $this->allowSomeAccount()) {
                $selenium->driver->executeScript("
                        var xhr = new XMLHttpRequest();
                        xhr.open('GET', 'https://www.lufthansa.com/service/secured/api/users/current/v2/accountstatement?showHistory=false');
                        xhr.setRequestHeader('Accept', 'application/json, text/plain, */*');
                        xhr.setRequestHeader('X-Distil-Ajax', 'awrwsaawcc');
                        xhr.setRequestHeader('X-Portal', 'LH');
                        xhr.setRequestHeader('X-Portal-CountryId', '18002');
                        xhr.setRequestHeader('X-Portal-Language', 'en');
                        xhr.setRequestHeader('X-Portal-Site', 'EN');
                        xhr.setRequestHeader('X-Portal-Taxonomy', 'My_Account>AccountStatement');
        
                        xhr.onreadystatechange = function() {
                            if (this.readyState != 4) {
                                return;
                            }
        
                            if (this.status != 200) {
                                localStorage.setItem('response', this.statusText);
                                localStorage.setItem('responseData', this.responseText);
                                return;
                            }
        
                            localStorage.setItem('response', this.responseText);
                        }
        
                        xhr.send();
                    ");
                sleep(2);
                $response = $selenium->driver->executeScript("return localStorage.getItem('response');");
                $this->logger->info("[Form response]: " . $response);
                $this->mainInfo = $this->http->JsonLog($response);
            }// if (!$this->mainInfo && $this->allowSomeAccount())

            // provider bug workaround
            if ($login == false
                && ($selenium->waitForElement(WebDriverBy::xpath("//span[@id = 'lh-loginModule-name']"), 0)
                    || $this->http->FindSingleNode("//div[@class = 'user-name']/div[contains(@class, 'heading-small')]"))) {
                $login = true;

                // watchdog workaround
                $this->increaseTimeLimit(100);

                $this->http->FilterHTML = false;
                // save page to logs
                $this->savePageToLogs($selenium);
                $this->mainInfo = $this->http->JsonLog($this->http->FindSingleNode($this->mainInfoXpath));
                $this->http->FilterHTML = true;
            } elseif ($selenium->waitForElement(WebDriverBy::xpath("//div[@id = 'error-id-dcep-' and (
                        contains(text(), 'Account Statement not available')
                        or contains(text(), 'The requested entity could not be found')
                    )]
                    | //div[@class = 'loading-frames loading-frames-small']
                    | //div[contains(text(), 'Loading...')]
                    | //span[contains(@class, 'loadingSpinner') and @hidden=\"\"]
                    | //p[contains(text(), \"We apologise for the interruption. We detected unusual behaviour from your browser, which resembles that of a bot.\")]
                "), 0)
            ) {
                $retry = true;
            }
            // sometimes it helps
            elseif (!$login) {
                $cookies = $selenium->driver->manage()->getCookies();

                foreach ($cookies as $cookie) {
                    if ($cookie['name'] === 'login-token') {
                        $login = true;
                    }
                    $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
                }

                if (!$login
                    && in_array($this->AccountFields['Login'], [
                        '992223110513874',
                        '992221898516119',
                        '992009365025411',
                        '992225086035113',
                        '992229657488172',
                        '992003004392068',
                        'strikeone92@gmail.com',
                    ])
                    || (
                        (strstr($selenium->http->currentUrl(), 'https://lufthansa.miles-and-more.com/web/row/en/login.html?client_id=y')
                        && is_numeric($this->AccountFields['Login']))
                        || ($selenium->http->currentUrl() == 'https://www.lufthansa.com/us/en/account-statement'
                        && is_numeric($this->AccountFields['Login'])
                        && $selenium->waitForElement(WebDriverBy::xpath('//button[span[contains(text(), "Log in now")]] | //a[contains(text(), "Log in now")]'), 0))
                    )
                ) {
                    throw new CheckException("A technical error has occurred.", ACCOUNT_PROVIDER_ERROR);
                }

                // AccountID: 5509076
                if (strstr($selenium->http->currentUrl(), 'https://lufthansa.miles-and-more.com/web/row/en/migrate.html?')) {
                    $this->throwProfileUpdateMessageException();
                }
            }

            if ($login) {
                // Needed for reservations
                $urlReservations = $this->http->FindSingleNode("(//a[contains(@href,'www.lufthansa.com/deeplink/cockpit?')]/@href)[1]");

                if ($urlReservations) {
                    $this->urlReservations = strtolower(htmlspecialchars_decode($urlReservations)); // strtolower - important!
                }
            }
        } catch (ScriptTimeoutException $e) {
            $this->logger->error("ScriptTimeoutException: " . $e->getMessage(), ['HtmlEncode' => true]);
            // retries
            if (strstr($e->getMessage(), 'timeout: Timed out receiving message from renderer')) {
                $retry = true;
            }
        } catch (WebDriverCurlException $e) {
            $this->logger->error("WebDriverCurlException: " . $e->getMessage(), ['HtmlEncode' => true]);
            // retries
            if (strstr($e->getMessage(), 'Curl error thrown for http POST')) {
                $retry = true;
            }
        } catch (UnrecognizedExceptionException $e) {
            $this->logger->error("UnrecognizedExceptionException: " . $e->getMessage(), ['HtmlEncode' => true]);
            // retries
            if (strstr($e->getMessage(), 'is not clickable at point')) {
                $retry = true;
            }
        } catch (StaleElementReferenceException $e) {
            $this->logger->error("StaleElementReferenceException: " . $e->getMessage(), ['HtmlEncode' => true]);
            // retries
            if (strstr($e->getMessage(), 'Element not found in the cache')
                || strstr($e->getMessage(), 'The element reference of')
                || strstr($e->getMessage(), 'stale element reference: element is not attached to the page document')
            ) {
                $retry = true;
            }
        } catch (
            NoSuchDriverException
            | UnknownServerException
            | UnexpectedJavascriptException
            | NoSuchWindowException
            | TimeOutException
            | Facebook\WebDriver\Exception\UnknownErrorException
            | Facebook\WebDriver\Exception\WebDriverCurlException
            $e
        ) {
            $this->logger->error("Exception: " . $e->getMessage(), ['HtmlEncode' => true]);
            $retry = true;
        } finally {
            // close Selenium browser
            $selenium->http->cleanup();

            if ($retry && ConfigValue(CONFIG_SITE_STATE) != SITE_STATE_DEBUG) {
                $this->logger->debug("[attempt]: {$this->attempt}");

                throw new CheckRetryNeededException(4, 5);
            }
        }
        $this->getTime($startTimer);

        return $login;
    }

    private function LoadLoginFormCurl()
    {
        $this->logger->notice(__METHOD__);
        $this->logger->debug('[Parse date: ' . date('Y/m/d H:i:s') . ']');
        $this->http->removeCookies();
        //		$this->http->GetURL('http://www.lufthansa.com/de/en/Homepage');
        //		$this->http->GetURL('https://www.lufthansa.com/online/portal/lh/de/profile_login?l=en&cid=18002');
        $this->http->GetURL('https://www.lufthansa.com/de/en/homepage?llo=true');

        // retries
        if (($this->http->Response['code'] == 403 && $this->http->FindPreg("/<H1>Access Denied<\/H1>/"))
            || $this->http->Response['code'] == 0) {
            throw new CheckRetryNeededException(3, 7);
        }
        // retries
        if ($this->http->Response['code'] == 456 && $this->http->FindPreg("/<h1>Access To Website Blocked<\/h1>/")) {
            $this->ErrorReason = self::ERROR_REASON_BLOCK;
            $this->DebugInfo = 'Access To Website Blocked';
//            throw new CheckRetryNeededException(5, 10);
        }// if ($this->http->Response['code'] == 456 && $this->http->FindPreg("/<h1>Access To Website Blocked<\/h1>/"))

        $this->distil(true, __METHOD__);

        return $this->fillingForm();
    }

    private function fillingForm()
    {
        $this->logger->notice(__METHOD__);
        $this->logger->debug('[Parse start date: ' . date('Y/m/d H:i:s') . ']');
        $startTimer = $this->getTime();

        $config = $this->http->JsonLog($this->http->FindPreg("/\{\"config\":[^<]+/"));

        if (!$this->http->ParseForm('loginUserdataForm') && !$config) {
            return $this->checkErrors();
        }

        if (isset($config->config->loginFormAction, $config->config->loginFormAction)) {
            $this->http->FormURL = $config->config->loginFormAction;
            $this->http->Form = [];
            $headers = [
                "portalId"          => "X-Portal",
                "countryId"         => "X-Portal-CountryId",
                "X-Portal-Language" => "en",
                "siteId"            => "X-Portal-Site",
                "current_taxonomy"  => "X-Portal-Taxonomy",
            ];

            foreach ($config->config->hidden as $key => $value) {
                if (isset($headers[$key])) {
                    $this->http->setDefaultHeader($headers[$key], $value);
                }
                $this->http->SetInputValue($key, $value);
            }// foreach ($config->config->hidden as $key => $value)
            $this->http->setDefaultHeader("X-Portal-Language", "en");
            $this->http->setDefaultHeader("X-Distil-Ajax", "awrwsaawcc");

            $this->http->SetInputValue('hidden', time() . date('B'));
            $this->http->SetInputValue('rememberme', "true");
//            $this->http->SetInputValue('vendor', "MAM");
        }// if (isset($config->loginFormAction, $config->loginFormAction))

        // it's a very strange bug
        $this->logger->debug(var_export($this->http->Form, true), ["pre" => true]);
        // retries
        if (count($this->http->Form) == 1 && isset($this->http->Form['recaptcha_challenge_field'])) {
            throw new CheckRetryNeededException(3, 7);
        }

        $this->AccountFields['Login'] = str_replace(' ', '', $this->AccountFields['Login']);
        $this->http->SetInputValue('userid', $this->AccountFields['Login']);
        $this->http->SetInputValue('password', $this->AccountFields['Pass']);
        $this->http->FormURL = preg_replace('/online\/cxml/ims', 'online/portal/lh/cxml', $this->http->FormURL);

        $this->getTime($startTimer);

        return true;
    }

    private function distil($retry = true, $caller = null, $isSwiss = false)
    {
        $this->logger->notice($caller ? sprintf('%s <- %s', __METHOD__, $caller) : __METHOD__);
        $this->logger->debug('[Parse start date: ' . date('Y/m/d H:i:s') . ']');
        $startTimer = $this->getTime();

        $referer = $this->http->currentUrl();
        $distilLink = $this->http->FindPreg("/meta http-equiv=\"refresh\" content=\"\d+;\s*url=([^\"]+)/ims");

        if (isset($distilLink)) {
            sleep(2);
            $this->http->NormalizeURL($distilLink);
            $this->http->RetryCount = 0;
            $this->http->FilterHTML = false;
            $this->http->GetURL($distilLink);
            $this->parseGeetestCaptcha($retry, $isSwiss);
            $this->http->RetryCount = 2;
        }// if (isset($distil))

        if (!$this->http->ParseForm('distilCaptchaForm')) {
            return false;
        }
        $formURL = $this->http->FormURL;
        $form = $this->http->Form;

        $captcha = $this->parseFunCaptcha($retry);
        $key = null;

        if ($captcha !== false) {
            $key = 'fc-token';
        } elseif (($captcha = $this->parseReCaptcha($retry)) !== false) {
            $key = 'g-recaptcha-response';
        } else {
            return false;
        }
        $this->http->FormURL = $formURL;
        $this->http->Form = $form;
        $this->http->SetInputValue($key, $captcha);

        $this->logger->debug(var_export($this->http->Form, true), ["pre" => true]);
        $this->http->RetryCount = 0;
        $this->http->PostForm(["Referer" => $referer]);
        $this->http->RetryCount = 2;
        $this->http->FilterHTML = true;

        $this->getTime($startTimer);

        return true;
    }

    private function parseGeetestCaptcha($retry = false, $isSwiss = false)
    {
        $this->logger->notice(__METHOD__);
        $gt = $this->http->FindPreg("/gt:\s*'(.+?)'/");
        $apiServer = $this->http->FindPreg("/api_server:\s*'(.+?)'/");
        $ticket = $this->http->FindSingleNode('//input[@name = "dCF_ticket"]/@value');

        if (!$gt || !$apiServer || !$ticket) {
            $this->logger->notice('Not a geetest captcha');

            return false;
        }

        // watchdog workaround
        $this->increaseTimeLimit(180);

        /** @var HTTPBrowser $http2 */
        $http2 = clone $this->http;
        $url = '/distil_r_captcha_challenge';
        $this->http->NormalizeURL($url);
        $http2->PostURL($url, []);
        $challenge = $http2->FindPreg('/^(.+?);/');

        if (!$challenge) {
            return false;
        }

        $recognizer = $this->getCaptchaRecognizer();
        $recognizer->RecognizeTimeout = 120;
        $pageurl = $this->http->currentUrl();
        $parameters = [
            "pageurl"    => $pageurl,
            "proxy"      => $this->http->GetProxy(),
            'api_server' => $apiServer,
            'challenge'  => $challenge,
            'method'     => 'geetest',
        ];
        $captcha = $this->recognizeByRuCaptcha($recognizer, $gt, $parameters, $retry);
        $request = $this->http->JsonLog($captcha, 3, true);

        if (empty($request)) {
            $this->logger->info('Retrying parsing geetest captcha');
            sleep(10);
            $this->increaseTimeLimit(180);
            $captcha = $this->recognizeByRuCaptcha($recognizer, $gt, $parameters, $retry);
            $request = $this->http->JsonLog($captcha, 3, true);
        }

        if (empty($request)) {
            $this->geetestFailed = true;
            $this->logger->error("geetestFailed = true");

            return false;
        }

        $verifyUrl = $this->http->FindSingleNode('//form[@id = "distilCaptchaForm"]/@action');
        $this->http->NormalizeURL($verifyUrl);
        $payload = [
            'dCF_ticket'        => $ticket,
            'geetest_challenge' => $request['geetest_challenge'],
            'geetest_validate'  => $request['geetest_validate'],
            'geetest_seccode'   => $request['geetest_seccode'],
        ];
        $this->http->PostURL($verifyUrl, $payload);

        return true;
    }

    private function parseReCaptcha($retry)
    {
        $this->logger->notice(__METHOD__);
        $key = $this->http->FindSingleNode("//form[@id = 'distilCaptchaForm']//div[@class = 'g-recaptcha']/@data-sitekey");
        $this->logger->debug("data-sitekey: {$key}");

        if (!$key) {
            return false;
        }

        // watchdog workaround
        $this->increaseTimeLimit(180);

        $recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_RUCAPTCHA);
        $recognizer->RecognizeTimeout = 120;
        $parameters = [
            "pageurl" => $this->http->currentUrl(),
            "proxy"   => $this->http->GetProxy(),
        ];

        return $this->recognizeByRuCaptcha($recognizer, $key, $parameters, $retry);
    }

    private function parseFunCaptcha($retry = true)
    {
        $this->logger->notice(__METHOD__);
        $this->logger->debug('[Parse date: ' . date('Y/m/d H:i:s') . ']');
        $startTimer = $this->getTime();

        $key = $this->http->FindSingleNode("//div[@id = 'funcaptcha']/@data-pkey");

        if (!$key) {
            $key = $this->http->FindPreg('/funcaptcha.com.+?pkey=([\w\-]+)/');
        }

        if (!$key) {
            return false;
        }

        // watchdog workaround
        $this->increaseTimeLimit(180);

        $postData = array_merge(
            [
                "type"             => "FunCaptchaTask",
                "websiteURL"       => $this->http->currentUrl(),
                "websitePublicKey" => $key,
            ],
            $this->getCaptchaProxy()
        );
        $recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_ANTIGATE_API_V2);
        $recognizer->RecognizeTimeout = 120;
        $captcha = $this->recognizeAntiCaptcha($recognizer, $postData, $retry);

        // RUCAPTCHA version
//        $recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_RUCAPTCHA);
//        $recognizer->RecognizeTimeout = 120;
//        $parameters = [
//            "method" => 'funcaptcha',
//            "pageurl" => $this->http->currentUrl(),
//            "proxy" => $this->http->GetProxy(),
//        ];
//        $captcha = $this->recognizeByRuCaptcha($recognizer, $key, $parameters, $retry);

        $this->getTime($startTimer);

        return $captcha;
    }

    private function checkErrors()
    {
        $this->logger->notice(__METHOD__);
        //# Miles & More online services and the login function unavailable
        if ($milesAndMoreLink = $this->http->FindSingleNode("//h2/a[contains(@onclick, 'www.miles-and-more.com')]/@href")) {
            $this->http->GetURL($milesAndMoreLink);
        }

        if ($message = $this->http->FindSingleNode("//font[contains(text(), 'Please note that the Miles & More online services and the login function will be unavailable')]", null, true, null, 0)) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        //# Miles & More online services and the login function unavailable
        if ($message = $this->http->FindSingleNode("//font[contains(text(), 'Please note that the Miles & More online services and the login function will be unavailable')]", null, true, null, 0)) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        //# Maintenance
        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'Aufgrund von Wartungsarbeiten des Lufthansa Servers kann diese Seite momentan nicht angezeigt werden')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // The web server for the domain www.lufthansa.com is unreachable at this time. The origin is having network or application issues.
        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'The web server for the domain www.lufthansa.com is unreachable at this time. The origin is having network or application issues.')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // Some parts of our online services are temporarily unavailable.
        if ($message = $this->http->FindPreg("/(Some parts of our online services are temporarily unavailable\.)/")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // connect to upstream server timedout
        if ($this->http->FindSingleNode("//h1[contains(text(), 'connect to upstream server timedout')]")
            // Internal Server Error
            || $this->http->FindSingleNode("//title[contains(text(), 'Internal Server Error')]")
            // An error occurred while processing your request.
            || $this->http->FindPreg("/An error occurred while processing your request\./ims")
            // Error 400
            || $this->http->FindPreg("/<H1>Error 400<\/H1>/ims")
            // Proxy Error
            || $this->http->FindPreg("/The proxy server received an invalid response from an upstream server\./ims")) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        // hard code (AccountID: 3255485)
        if ($this->AccountFields["Login"] == 'JoRead' && $this->http->Response['code'] == 403
            && $this->http->FindSingleNode("//h1[contains(text(), 'Access Denied')]")) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        // retries
        if ($this->http->currentUrl() == 'https://www.lufthansa.com/online/myportal/lh/de/homepage?l=en&cid=1000243&os=DE&wr_rs=s'
            && $this->http->FindSingleNode("//a[@id = 'header-profile-toggle']//span[contains(text(), 'Login')]")) {
            throw new CheckRetryNeededException(3, 7);
        }
        /*
         * This page is temporarily unavailable.
         *
         * We are currently solving this problem and apologize for any inconvenience.
         * Please try again later. Thank you.
         */
        if ($message = $this->http->FindSingleNode("//div[p/strong[contains(text(), 'This page is temporarily unavailable.')]]")) {
            throw new CheckException("Miles & More online services and the login function unavailable. We are currently solving this problem and apologize for any inconvenience. Please try again later. Thank you.", ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    private function redirectHelperForm($retry = true)
    {
        $this->logger->notice(__METHOD__);
        $this->distil($retry, __METHOD__);
        $itinError = $this->http->FindSingleNode('//input[contains(@value, "Unfortunately your booking could not be found.")]/@value');

        if ($itinError) {
            return null;
        }

        $form = [];
        $this->logger->debug('[Parse date: ' . date('Y/m/d H:i:s') . ']');

        if ($this->http->ParseForm("redirectHelperForm")) {
            $form = $this->http->Form;
            $this->http->PostForm();

//            if ($this->http->ParseForm("chlge")) {
//                $this->http->PostForm();
//            }
        }

        if ($this->http->ParseForm(null, "//form[@action = 'https://www.lufthansa.com/deeplink/mybookings']")) {
            $this->http->PostForm();
        }

        if ($this->http->ParseForm(null, "//form[@action = 'https://www.lufthansa.com/de/en/login']")) {
            $this->http->RetryCount = 0;
            $this->http->PostForm();
            $this->http->RetryCount = 2;
        }
        $this->distil($retry, __METHOD__);

        // js
        if (
            $this->http->FindPreg("/If the page doesn't automatically attempt to reload, please <a[^>]+href=\"javascript:window.location.reload\(\)/")
            || $this->http->FindPreg('/Your web browser will automatically reload shortly and you will receive a pop-up asking you to confirm your form submission again/')
            || $this->http->FindSingleNode('//h1[contains(text(), "Unauthorized Activity Has Been Detected")]')
        ) {
            $this->logger->notice("js detecting workaround. page reload");
            $this->http->GetURL($this->http->currentUrl());

            $this->distil($retry, __METHOD__);

            $this->logger->notice("one more login form, try to login again");

            if ($this->http->ParseForm('LoginForm')) {
                $this->http->GetURL('https://www.lufthansa.com/de/en/homepage?llo=true');

                if ($this->fillingForm()) {
                    $this->http->PostForm();
                    // js
                    if (
                        $this->http->FindPreg("/If the page doesn't automatically attempt to reload, please <a href=\"javascript:window.location.reload\(\)/")
                        || $this->http->FindPreg('/Your web browser will automatically reload shortly and you will receive a pop-up asking you to confirm your form submission again/')
                    ) {
                        $this->logger->notice("js detecting workaround. page reload");
                        $this->http->GetURL($this->http->currentUrl());

                        $this->distil($retry, __METHOD__);

                        if ($retry && $this->http->ParseForm('LoginForm')) {
                            throw new CheckRetryNeededException(3, 7);
                        }
                    }
                }// if ($this->fillingForm())
            }// if ($this->http->ParseForm('LoginForm'))
        }

        return $form;
    }

    private function LoginCurl()
    {
        $this->logger->notice(__METHOD__);
        $this->logger->debug('[Parse date: ' . date('Y/m/d H:i:s') . ']');
        $this->http->FilterHTML = false;

        if (!$this->http->PostForm() && $this->http->Response['code'] != 404) {
            return $this->checkErrors();
        }
        $this->http->FilterHTML = true;
        // redirect form
        $this->redirectHelperForm();

        if ($this->http->Response['code'] == 200) {
            $this->redirectHelperForm();
        }
        // Invalid credentials
        $errorCode = $this->http->FindPreg("/errorcode=([^\&]+)/", false, $this->http->currentUrl());
        $this->logger->debug("[Current URL]: {$this->http->currentUrl()}");
        $this->logger->debug("errorCode: {$errorCode}");
        $this->logger->debug("login length: " . strlen($this->AccountFields['Login']));
        // Either the User-ID or the password that you entered is not correct. Please try again. (70102)
        if ($errorCode == 70102) {
            throw new CheckException("Either the User-ID or the password that you entered is not correct. Please try again. (70102)", ACCOUNT_INVALID_PASSWORD);
        }
        // Due to technical reasons, your data could not be displayed at the moment. Please visit out Help & Contact pages or contact Lufthansa Internet Service Center under +49 (0)69 86 799 699
        if ($errorCode == 5) {
            throw new CheckRetryNeededException(2, 7, "Due to technical reasons, your data could not be displayed at the moment. Please visit out Help & Contact pages or contact Lufthansa Internet Service Center under +49 (0)69 86 799 699", ACCOUNT_PROVIDER_ERROR);
        }
        // The combination of the entered login and password is wrong. Please try again. (50101)
        if ($errorCode == 50101) {
            throw new CheckException("The combination of the entered login and password is wrong. Please try again. (50101)", ACCOUNT_INVALID_PASSWORD);
        }
        // The temporary access to your profile has expired.
//        if ($errorCode == 50103)
//            throw new CheckException("The temporary access to your profile has expired.", ACCOUNT_PROVIDER_ERROR);
        // Your account is inactive. Please request another activation link.
        if ($errorCode == 50103) {
            throw new CheckException("Your account is inactive. Please request another activation link.", ACCOUNT_PROVIDER_ERROR);
        }
        // Sorry, a technical error has occurred. Please try again later. (50199)
        if ($errorCode == 50199) {
            throw new CheckException("Sorry, a technical error has occurred. Please try again later. (50199)", ACCOUNT_PROVIDER_ERROR);
        }
        // The user ID entered is invalid. Please try again. (70103)
        if ($errorCode == 70103) {
            throw new CheckException("The user ID entered is invalid. Please try again. (70103)", ACCOUNT_INVALID_PASSWORD);
        }
        // Please check your Miles & More service card number (15-digit number) which you will find on your card. Please note that the 16-digit service card number can no longer be used as your login. (70104)
        if ($errorCode == 70104) {
            throw new CheckException("Please check your Miles & More service card number (15-digit number) which you will find on your card. Please note that the 16-digit service card number can no longer be used as your login. (70104)", ACCOUNT_INVALID_PASSWORD);
        }
        // Please use your currently valid Miles & More service card to log in or contact your local Miles & More service team. (70106)
        if ($errorCode == 70106) {
            throw new CheckException("Please use your currently valid Miles & More service card to log in or contact your local Miles & More service team. (70106)", ACCOUNT_INVALID_PASSWORD);
        }

        if ($errorCode == 70101) {
            throw new CheckException("The Miles & More service card number or PIN you entered is invalid. Please note that the 16-digit service card number can no longer be used as your login for security reasons. Please use the 15-digit service number which you will also find on your service card. (70101)", ACCOUNT_INVALID_PASSWORD);
        }
        // Your password or your Miles & More PIN have been entered incorrectly too many times. (70105)
        if ($errorCode == 70105) {
            throw new CheckException("Your password or your Miles & More PIN have been entered incorrectly too many times. (70105)", ACCOUNT_LOCKOUT);
        }
        // We are sorry, but for technical reasons your data cannot be displayed at the present time. Please contact the Miles & More service team. (70107)
        if ($errorCode == 70107) {
            throw new CheckException("We are sorry, but for technical reasons your data cannot be displayed at the present time. Please contact the Miles & More service team. (70107)", ACCOUNT_PROVIDER_ERROR);
        }
        // Sorry, your account cannot be accessed. Please contact the Miles & More service team. (70108)
        if ($errorCode == 70108) {
            throw new CheckException("Sorry, your account cannot be accessed. Please contact the Miles & More service team. (70108)", ACCOUNT_PROVIDER_ERROR);
        }
        // Your password has expired due to security reasons. Please choose a new one.
        if ($errorCode == 14449
            && ($this->http->FindSingleNode("//p[contains(text(), 'Your password has expired due to security reasons. Please choose a new one.')]"))) {
            $this->throwProfileUpdateMessageException();
        }
        // The Miles & More service card number entered is invalid. Please try again. Your Miles & More service card number is a 15-digit number.
        if (strstr($this->http->currentUrl(), 'action=login&businessWarningsKeys=_error_6&command=handleValidationError')
            && strlen($this->AccountFields['Login']) >= 15) {
            throw new CheckException("The Miles & More service card number entered is invalid. Please try again. Your Miles & More service card number is a 15-digit number.", ACCOUNT_INVALID_PASSWORD);
        }
        // The Miles & More card number or Miles & More PIN that you entered is not valid. Please try again.
        if (strstr($this->http->currentUrl(), 'action=login&businessWarningsKeys=_error_5&command=handleValidationError')) {
            throw new CheckException("The Miles & More card number or Miles & More PIN that you entered is not valid. Please try again.", ACCOUNT_INVALID_PASSWORD);
        }
        // Either the User-ID or the password that you entered is not correct. Please try again.
        if (strstr($this->http->currentUrl(), 'action=login&businessWarningsKeys=_error_141&command=handleValidationError')
            && !is_numeric($this->AccountFields['Login'])) {
            throw new CheckException("Either the User-ID or the password that you entered is not correct. Please try again.", ACCOUNT_INVALID_PASSWORD);
        }
        /*
         * 2 Your login failed
         *
         * The Miles & More card number or Miles & More PIN that you entered is not valid. Please try again.
         *
         * The Miles & More service card number entered is invalid. Please try again. Your Miles & More service card number is a 15-digit number.
         */
        if (strstr($this->http->currentUrl(), 'action=login&businessWarningsKeys=_error_5,_error_6&command=handleValidationError')) {
            throw new CheckException("The Miles & More card number or Miles & More PIN that you entered is not valid. Please try again.", ACCOUNT_INVALID_PASSWORD);
        }

        if (!empty($errorCode)) {
            $this->DebugInfo = "Code: {$errorCode}";
        }

        $this->http->GetURL($this->apiURL, $this->apiHeaders);
        $this->redirectHelperForm();

        if ($this->http->Response['code'] == 200) {
            $this->redirectHelperForm();
        }
        $this->http->JsonLog(null, 0);

        if ($this->http->FindPreg("/\"kosaid\":\"([^\"]+)/")) {
            return true;
        }

        return $this->checkErrors();
    }

    private function parseProperties()
    {
        $this->logger->notice(__METHOD__);
        // Balance - Award miles
        if ($this->allowSomeAccount()) {
            $mainInfo = $this->http->JsonLog();
            $mainInfo = !empty($mainInfo) ?: $this->mainInfo;
        } else {
            $mainInfo = $this->mainInfo = $this->http->JsonLog();
        }
        $this->SetBalance(
            $mainInfo->maminfo->awardMiles->value
            ?? $mainInfo->maminfo->awardMiles
            ?? $mainInfo->milesInfo->awardMiles->value
            ?? $mainInfo->profile->loyaltyDetails->mileageBalance
            ?? null
        );
        // Name
        if (isset($mainInfo->firstName, $mainInfo->lastName)) {
            $this->name = [
                'FirstName' => $mainInfo->firstName,
                'LastName'  => $mainInfo->lastName,
            ];
            $this->SetProperty("Name", Html::cleanXMLValue($mainInfo->firstName . " " . $mainInfo->lastName));
        } elseif ($this->allowSomeAccount() && isset($mainInfo->accountUser->firstName, $mainInfo->accountUser->lastName)) {
            $this->name = [
                'FirstName' => $mainInfo->accountUser->firstName,
                'LastName'  => $mainInfo->accountUser->lastName,
            ];
            $this->SetProperty("Name", Html::cleanXMLValue($mainInfo->accountUser->firstName . " " . $mainInfo->accountUser->lastName));
        } elseif (isset($mainInfo->profile->generalDetails->firstName, $mainInfo->profile->generalDetails->firstName)) {
            $this->name = [
                'FirstName' => $mainInfo->profile->generalDetails->firstName,
                'LastName'  => $mainInfo->profile->generalDetails->lastName,
            ];
            $this->SetProperty("Name", Html::cleanXMLValue($mainInfo->profile->generalDetails->firstName . " " . $mainInfo->profile->generalDetails->lastName));
        }
        // Status
        $status =
            $mainInfo->maminfo->status
            ?? $mainInfo->milesInfo->status
            ?? $mainInfo->profile->loyaltyDetails->programCard->statusCode->value
            ?? null
        ;
        $this->logger->debug("[Status]: {$status}");

        switch ($status) {
            case 'BASE':
            case 'INST':
                $status = 'Miles & More member';

                break;

            case 'FTL':
                $status = 'Frequent Traveller';

                break;

            case 'HON':
                $status = 'HON Circle';

                break;

            case 'SEN':
                $status = 'Senator';

                break;

            default:
                if (!empty($status)) {
                    $this->sendNotification("unknown tier was found {$status}");
                }
        }// case ($mainInfo->maminfo->status)

        if ($status) {
            $this->SetProperty("Status", $status);
        }
        // Status Miles
        $this->SetProperty("StatusMiles",
            $mainInfo->maminfo->statusMiles->value
            ?? $mainInfo->milesInfo->statusMiles->value
            ?? $mainInfo->maminfo->statusMiles
            ?? $mainInfo->profile->loyaltyDetails->remainingStatusPoint
            ?? null
        );
        // Select Miles
        $this->SetProperty("SelectMiles",
            $mainInfo->maminfo->selectMiles
            ?? $mainInfo->profile->loyaltyDetails->selectionInfo->amount
            ?? null
        );
        // eVoucher
        $this->SetProperty("EVouchers",
            $mainInfo->maminfo->vouchers->value
            ?? $mainInfo->milesInfo->vouchers->value
            ?? $mainInfo->maminfo->vouchers
            ?? $mainInfo->profile->loyaltyDetails->remainingEVoucher
            ?? null
        );
        // Card number
        $this->SetProperty("Number",
            $mainInfo->maminfo->number
            ?? $mainInfo->milesInfo->cardNumber
            ?? $mainInfo->profile->loyaltyDetails->programCard->cardNumber
            ?? null
        );
    }

    private function CheckItinerary()
    {
        $this->logger->notice(__METHOD__);
        $this->logger->debug('[Parse start date: ' . date('Y/m/d H:i:s') . ']');
        $startTimer = $this->getTime();

        if ($err = $this->http->FindSingleNode('//text()[contains(normalize-space(.), "Due to technical difficulties this page cannot be displayed")]/ancestor::*[1][name() != "script"]')) {
            $this->logger->error($err);

            return false;
        }

        $r = $this->itinerariesMaster->add()->flight();
        $xpath = $this->http->XPath;
        // ConfirmationNumber
        $recordLocator = $this->http->FindSingleNode("//span[@id = 'recloc']");

        if (!isset($recordLocator)) {
            $recordLocator = $this->http->FindSingleNode("//span[@class = 'booking-no']");
        }
        $r->general()->confirmation($recordLocator);
        $r->price()
            ->total($this->http->FindSingleNode("//tr[@class = 'total']//span[@class = 'price']/span[@class = 'number']/@data-value"))
            ->currency($this->http->FindSingleNode("//tr[@class = 'total']//span[@class = 'price']/span[@class = 'currency']/@data-currency-code"));
        // Passengers, AccountNumbers
        $values = $xpath->query("//div[@id = 'passengersContactDetailsView']//table//tr[not(@class)]");
        $accountNumbers = $ticketNumbers = $passengers = [];

        for ($i = 0; $i < $values->length; $i++) {
            //			$passengers[] = Html::cleanXMLValue($this->http->FindSingleNode("td[1]", $values->item($i)));
            $i++;
            $accountNumbers[] = Html::cleanXMLValue($this->http->FindSingleNode("td[2]", $values->item($i)));
            $ticketNumbers[] = Html::cleanXMLValue($this->http->FindSingleNode("td[1]", $values->item($i), true, '/Ticket ([\d\-]+)/'));
        }// for ($i = 0; $i < $values->length; $i++)
        $accountNumbers = array_unique(array_filter($accountNumbers));

        if (!empty($accountNumbers)) {
            $r->program()->accounts($accountNumbers, false);
        }
        $ticketNumbers = array_unique(array_filter($ticketNumbers));

        if (!empty($ticketNumbers)) {
            $r->issued()->tickets($ticketNumbers, false);
        }

        $script = $this->http->FindSingleNode('//script[contains(., "var clientSideData =")]');
        $this->logger->info('itins:');
        $this->logger->info(print_r($this->http->FindNodes('//irc-itinerary'), true));
        $legs = $xpath->query("//div[contains(@class, 'itinerary-list-segment')] | //irc-itinerary");
        $this->logger->debug("Total {$legs->length} legs were found");
        // for Seats
        $k = 0;

        foreach ($legs as $leg) {
            $date = $this->http->FindSingleNode(".//text()[contains(normalize-space(.), 'Flight on')]", $leg, true, '/on\s*(.+)from/ims');

            if ($date) {
                $this->logger->debug("Date: " . $date . " / " . strtotime($date));
            } else {
                $this->logger->error('Date not found');
            }
            $flights = $xpath->query(".//div[contains(@class, 'table-flight-details')]//div[contains(@class, 'flight-row')]", $leg);
            $totalSegments = $flights->length / 2;
            $this->http->Log("Total {$totalSegments} segments was found");

            for ($i = 0; $i < $flights->length; $i++) {
                // for Seats
                $k++;
                $this->logger->debug("node # " . $i);
                $s = $r->addSegment();

                // Passengers
                $passengersSrc = $this->http->FindNodes("(//div[@class = 'grid' and //span[@class = 'seat-pax-label']])[$k]//span[@class = 'seat-pax-label']");
                $passengersFiltered = [];

                foreach ($passengersSrc as $p) {
                    if (!$this->http->FindPreg('#^Adult\s+\d+$#i', false, $p)) {
                        $passengersFiltered[] = $p;
                        $passengers[] = $p;
                    } else {
                        $this->logger->warning('Ignore passenger "' . $p . '"');
                    }
                }// foreach ($passengersSrc as $p)

                if (empty($passengersFiltered)) {
                    $passengersSrc = $this->http->FindNodes("(//div[@class = 'grid grid-ff-4']/table//tr[th])[$k]//th");

                    foreach ($passengersSrc as $p) {
                        if (!$this->http->FindPreg('#^Adult\s+\d+$#i', false, $p)) {
                            $passengers[] = $p;
                        } else {
                            $this->logger->warning('Ignore passenger "' . $p . '"');
                        }
                    }// foreach ($passengersSrc as $p)
                }

                // departure
                $time = $this->http->FindSingleNode("div[contains(@class, 'date')]", $flights->item($i), true, '/\d{2}:\d{2}/ims');
                $this->logger->debug("Dep Time: " . $time);
                $offset = $this->http->FindSingleNode("div[contains(@class, 'date')]/sub", $flights->item($i));
                $this->logger->debug("Dep offset: " . $offset);

                if ($date) {
                    $depDate = strtotime($date . ' ' . $time);

                    if (isset($offset)) {
                        $depDate = strtotime($offset, $depDate);
                    }
                    $s->departure()->date($depDate);
                }// if ($date)
                else {
                    $this->logger->error('Date not set, ignoring time');
                }
                $depName = $this->http->FindSingleNode("div[contains(@class, 'airport')]", $flights->item($i), true, '/[^\(]+/ims');
                $depName = trim(preg_replace('#Departure\s+airport\s+#i', '', $depName));
                $s->departure()
                    ->name($depName)
                    ->code($this->http->FindSingleNode("div[contains(@class, 'airport')]", $flights->item($i), true, '/\(([A-Z]{3})\)/ims'));

                // airline
                $airlineName = $this->http->FindSingleNode("div[contains(@class, 'details') and not(contains(., 'Cabin class'))]",
                    $flights->item($i));
                $airlineName = trim(preg_replace('#\s*Airline company name\s*#i', '', $airlineName));
                $s->airline()
                    ->name($airlineName)
                    ->number($this->http->FindSingleNode("div[contains(@class, 'numbers-info')]", $flights->item($i), false, '#\w{2}\d+#i'));
                $i++;

                // arrive
                $time = $this->http->FindSingleNode("div[contains(@class, 'date')]", $flights->item($i), true, '/\d{2}:\d{2}/ims');
                $this->logger->debug("Arr Time: " . $time);
                $offset = $this->http->FindSingleNode("div[contains(@class, 'date')]/sub", $flights->item($i));
                $this->logger->debug("Arr offset: " . $offset);

                if ($date) {
                    $arrDate = strtotime($date . ' ' . $time);

                    if (isset($offset)) {
                        $arrDate = strtotime($offset, $arrDate);
                    }
                    $s->arrival()->date($arrDate);
                }// if ($date)
                else {
                    $this->logger->error('Date not set, ignoring time');
                }
                $arrName = $this->http->FindSingleNode("div[contains(@class, 'airport')]", $flights->item($i), true, '/[^\(]+/ims');
                $arrName = trim(preg_replace('#Arrival\s+airport\s+#i', '', $arrName));
                $s->arrival()
                    ->name($arrName)
                    ->code($this->http->FindSingleNode("div[contains(@class, 'airport')]", $flights->item($i), true, '/\(([A-Z]{3})\)/ims'));

                // Seats
                $seatsSrc = $this->http->FindNodes("(//div[@class = 'grid' and //strong[@class = 'seat-num']])[$k]//strong[@class = 'seat-num']");
                $seatsFiltered = [];

                foreach ($seatsSrc as $s) {
                    if (preg_match('#\d+\w#i', $s)) {
                        $seatsFiltered[] = $s;
                    } else {
                        $this->logger->warning('Bad seat "' . $s . '"');
                    }
                }// foreach ($seatsSrc as $s)

                if (!empty($seatsFiltered)) {
                    $s->extra()->seats($seatsFiltered);
                }

                // extra
                $s->extra()
                    ->duration($this->http->FindSingleNode('div[contains(@class, "duration")]', $flights->item($i), true, '/:\s*([^<]+)/ims'));
                $cabin = $this->http->FindSingleNode("div[contains(@class, 'details') and contains(., 'Cabin class')]/div/span", $flights->item($i));

                if (!isset($cabin)) {
                    $cabin = $this->http->FindSingleNode("div[contains(@class, 'details') and contains(., 'Cabin class')]/div/text()[last()]", $flights->item($i), true, '/([^\(]+)/ims');
                }

                $s->extra()
                        ->cabin($cabin)
                        ->bookingCode($this->http->FindSingleNode("div[contains(@class, 'details') and contains(., 'Cabin class')]/div/abbr[@class = 'cabin']", $flights->item($i), true, '/\(([^\)]+)/ims'));

                if ($s->getFlightNumber()) {
                    $flightNumber = preg_replace('/[^\d]+/', '', $s->getFlightNumber());
                    $this->sendNotification("check aircraft // ZM");
                    $s->extra()->aircraft($this->http->FindPreg("/" . $flightNumber . "\"[^\}]+EQUIPMENT\":\"([^\"]+)/ims"));
                }
                // terminals
                $search = sprintf('"B_DATE":"%s","DEPARTURE_LOCATION_CODE":"%s"', date('YmdHi', $s->getDepDate()), $s->getDepCode());
                $pos = strpos($script, $search);

                if ($pos !== false) {
                    $fragment = substr($script, $pos, 250);

                    if (preg_match('/"ARRIVAL_TERMINAL":(?<arr>"\d+"|null).{0,100}"DEPARTURE_TERMINAL":(?<dep>"\d+"|null)/', $fragment, $m)) {
                        if ($m['dep'] !== 'null') {
                            $s->departure()->terminal(trim($m['dep'], '"'));
                        }

                        if ($m['arr'] !== 'null') {
                            $s->arrival()->terminal(trim($m['arr'], '"'));
                        }
                    }
                }
                $this->http->LogSplitter();
            }// for ($i = 0; $i < $totalSegments; $i++)
        }// foreach ($legs as $leg)
        $passengers = array_unique($passengers);

        if (!empty($passengers)) {
            $r->general()->travellers($passengers, true);
        }

        $cancelled = $this->http->FindPreg('/Please note that the Airline has cancelled one or more of your selected flights/ims');

        if ($cancelled) {
            $r->general()->cancelled();
        }

        $this->getTime($startTimer);
        $this->logger->debug('Parsed itinerary:');
        $this->logger->debug(var_export($r->toArray(), true), ['pre' => true]);

        return true;
    }

    private function ParseItinerariesSwiss($found = [], $logLog = true)
    {
        $this->logger->notice(__METHOD__);
        $this->logger->debug('[Parse start date: ' . date('Y/m/d H:i:s') . ']');
        $startTimer = $this->getTime();
        $links = $this->http->FindNodes("//a[contains(@href, 'BookingDetails?details=')]/@href");
        $countLinks = count($links);
        $this->logger->debug('Total itineraries were found: ' . $countLinks);

        if ($countLinks > 0) {
            foreach ($links as $link) {
                $this->http->FilterHTML = false;
                $this->http->NormalizeURL($link);
                $this->http->GetURL($link);
                // refs #14600
                if ($this->http->Response['code'] == 200) {
                    $this->increaseTimeLimit();
                }
                $this->ParseItinerarySwiss($found, $logLog);
                $this->http->FilterHTML = true;
            }// foreach ($links as $link)
        }// if ($countLinks > 0)
        else {// for CheckConfirmationNumberInternal
            $this->logger->notice('Single itinerary');
            $this->ParseItinerarySwiss($found, $logLog);
        }
        $this->getTime($startTimer);

        return true;
    }

    private function ParseItinerarySwiss($found = [], $logLog = true)
    {
        $this->logger->notice(__METHOD__);
        $this->logger->debug('[Parse start date: ' . date('Y/m/d H:i:s') . ']');
        $startTimer = $this->getTime();

        $recordLocator = $this->http->FindSingleNode("//span[contains(text(), 'Your booking reference:')]", null, true, '/:\s*([^<]+)/ims');

        if (!isset($recordLocator)) {
            $recordLocator = $this->http->FindSingleNode('//span[contains(text(), "Booking reference:")]', null, true, '/[A-Z\d]{6}$/');
        }

        if (!isset($recordLocator)) {
            $recordLocator = $this->http->FindSingleNode('//h2[contains(text(), "Your booking reference is")]', null, true, '/[A-Z\d]{6}$/');
        }

        if (in_array($recordLocator, $found)) {
            $this->logger->error("Itinerary with Booking Code {$recordLocator} already exist in result array, skip it");

            return true;
        }

        if (!$recordLocator) {
            $recordLocator = $this->http->FindSingleNode("//div[@class = 'notification-message']/p[@class = 'is-visuallyhidden']/following-sibling::p[contains(., 'Your reservation') and contains(., 'has been deleted')]", null, false, "/Your reservation ([A-Z\d]+) has been deleted/");

            if ($recordLocator) {
                $r = $this->itinerariesMaster->add()->flight();
                $r->general()
                    ->confirmation($recordLocator)
                    ->cancelled()
                    ->status('has been deleted');
                $this->logger->debug('Parsed Itinerary:');
                $this->logger->debug(var_export($r->toArray(), true), ['pre' => true]);

                return true;
            }

            return false;
        }

        if ($logLog) {
            $this->logger->info(sprintf('[%s] Parse Itinerary #%s', $this->currentItin++, $recordLocator), ['Header' => 3]);
        }

        // Segments
        $segs = $this->http->XPath->query("//div[@class = 'payment-summary-travel-info']/div[div[not(contains(@class, 'notification'))]][not(.//span[contains(@class,'ico-train')])]");
        $segsTrain = $this->http->XPath->query("//div[@class = 'payment-summary-travel-info']/div[div[not(contains(@class, 'notification'))]][.//span[contains(@class,'ico-train')]]");
        $this->logger->debug("Total {$segs->length} flight-segments were found");
        $this->logger->debug("Total {$segsTrain->length} train-segments were found");

        if ($segs->length === 0 && $segsTrain->length === 0) {
            $this->logger->debug('Skip result. No Segments');

            return true;
        }

        $r = $this->itinerariesMaster->add()->flight();
        $r->general()->confirmation($recordLocator);

        if ($segsTrain->length > 0) {
            $train = $this->itinerariesMaster->add()->train(); // del if no trains
            $train->general()->confirmation($recordLocator);
            $this->parseSegmentsSwiss($segsTrain, $train);
        }

        $passengers = $this->http->XPath->query("//table[contains(@class, 'passenger-list')]//tr[td]");

        for ($k = 0; $k < $passengers->length; $k++) {
            $name = trim(beautifulName(
                $this->http->FindSingleNode("td[1]/div[@class = 't-strong']", $passengers->item($k))
                . ' ' . $this->http->FindSingleNode("td[2]/div[@class = 't-strong']", $passengers->item($k))
            ));

            if ($name) {
                $r->general()->traveller($name, true);

                if (isset($train)) {
                    $train->general()->traveller($name, true);
                }
            }
            $ticket = $this->http->FindSingleNode("td[3]/span[2]", $passengers->item($k));

            if ($ticket) {
                $r->issued()->ticket($ticket, false);
            }
        }

        $r->price()
            ->total($this->http->FindSingleNode("//div[contains(text(), 'Price per booking')]/preceding-sibling::div[1]",
                null, true, '/(\d+.\d+|\d+)/'), false, true)
            ->currency($this->http->FindSingleNode("//div[contains(text(), 'Price per booking')]/preceding-sibling::div[1]",
                null, true, '/([A-Z]{3})/'), false, true);
        $this->parseSegmentsSwiss($segs, $r);

        $this->logger->debug('Parsed Itinerary:');
        $this->logger->debug(var_export($r->toArray(), true), ['pre' => true]);

        if (isset($train)) {
            $this->logger->debug('Parsed Itinerary (Train):');
            $this->logger->debug(var_export($train->toArray(), true), ['pre' => true]);
        }

        $this->getTime($startTimer);

        return true;
    }

    private function parseSegmentsSwiss(DOMNodeList $segs, $r)
    {
        if (
            !($r instanceof Flight)
            && !($r instanceof Train)
        ) {
            return;
        }

        foreach ($segs as $seg) {
            $s = $r->addSegment();
            // Cabin
            // Economy - V
            $s->extra()
                ->cabin(trim($this->http->FindSingleNode("div[2]/div[contains(@class, 'class')]", $seg, true,
                    "/([^\-]+)/")))
                ->bookingCode($this->http->FindSingleNode("div[2]/div[contains(@class, 'class')]", $seg, true,
                    "/\-\s*([^<]+)/"));

            if ($r instanceof Train) {
                $s->extra()
                    ->service($this->http->FindSingleNode("div[2]/div[contains(@class, 'company')]", $seg, true,
                        "/^([A-Z\d][A-Z]|[A-Z][A-Z\d])\s*\d+/"))
                    ->number($this->http->FindSingleNode("div[2]/div[contains(@class, 'company')]", $seg, true,
                        "/^(?:[A-Z\d][A-Z]|[A-Z][A-Z\d])\s*(\d+)/"));
            } else {
                $s->airline()
                    ->name($this->http->FindSingleNode("div[2]/div[contains(@class, 'company')]", $seg, true,
                        "/^([A-Z\d][A-Z]|[A-Z][A-Z\d])\s*\d+/"))
                    ->number($this->http->FindSingleNode("div[2]/div[contains(@class, 'company')]", $seg, true,
                        "/^(?:[A-Z\d][A-Z]|[A-Z][A-Z\d])\s*(\d+)/"));
            }
            // departure
            // Date, EU format
            $date = $this->http->FindSingleNode("div[1]/div[contains(@class, 'date')]", $seg, true, '/\d+\/\d+\/\d+$/');
            $date = $this->ModifyDateFormat($date);
            $this->logger->debug("Date: $date");
            $time = $this->http->FindSingleNode("div[1]/div[2]/strong[contains(@class, 'departure')]/span/text()[1]",
                $seg);
            $this->logger->info("DepTime: $time");
            $s->departure()
                ->date(strtotime($time, strtotime($date)))
                ->code($this->http->FindSingleNode("div[1]/div[2]/strong[contains(@class, 'departure')]/abbr", $seg));

            // arrival
            $time = $this->http->FindSingleNode("div[1]/div[2]/strong[contains(@class, 'arrival')]/span/text()[1]",
                $seg);
            $day = $this->http->FindSingleNode("div[1]/div[2]/strong[contains(@class, 'arrival')]/span/sub", $seg);
            $this->logger->debug("ArrTime: $time");
            $this->logger->debug("Offset: $day");
            $arrDate = strtotime($time, strtotime($date));

            if (!empty($day) && $arrDate != false) {
                $arrDate = strtotime("$day day", $arrDate);
            }
            $s->arrival()
                ->date($arrDate)
                ->code($this->http->FindSingleNode("div[1]/div[2]/strong[contains(@class, 'arrival')]/abbr", $seg));
        }
    }

    private function ParseItinerariesForm()
    {
        $this->logger->notice(__METHOD__);
        $this->logger->debug('[Parse start date: ' . date('Y/m/d H:i:s') . ']');
        $startTimer = $this->getTime();

        $this->http->Form = [];

        if (!$this->http->ParseForm("APIS_ELIGIBILITY_FORM")) {
            return false;
        }
        unset($this->http->Form['OUTPUT_FORMAT']);
        $formfields = $this->http->Form;
        $formurl = $this->http->FormURL;
        $pnrs = $this->http->XPath->query("//td[@class = 'flight-number']/strong");
        $countPNRs = $pnrs->length;
        $this->logger->debug("Total {$countPNRs} itineraries found");

        $matches1 = $this->http->FindPregAll('/"LAST_NAME":"(?<lastName>[^\"]+)","IS_OFFLINE":[^\,]+,"DEPARTURE_DATE":"[^\"]+","REC_LOC":"(?<recordLocator>[^\"]+)"/ims', $this->http->Response['body'], PREG_SET_ORDER, true);
        $matches2 = $this->http->FindPregAll('/"recordLocator":\s*"(?<recordLocator>.+?)",\s*"retrieveLastName":\s*"(?<lastName>.+?)"/ims', $this->http->Response['body'], PREG_SET_ORDER, true);
        $matches = array_merge($matches1, $matches2);
        $this->logger->info('Found matches for further parsing:');
        $this->logger->info(print_r($matches, true));

        foreach ($matches as $match) {
            $this->logger->info('Parsing itinerary #' . $match['recordLocator'], ['Header' => 3]);
            $this->http->Form = $formfields;
            $this->http->FormURL = $formurl;
            $this->http->Form['PAGE'] = 'BKGD';
            $this->http->Form['DIRECT_RETRIEVE'] = 'TRUE';
            $this->http->Form['DIRECT_RETRIEVE_LASTNAME'] = $match['lastName'];
            $this->http->Form['REC_LOC'] = $match['recordLocator'];

            $this->http->PostForm();

            if (!$this->CheckItinerary()) {
                $this->sendNotification("check fields, mb swiss // MI");

                if (isset($this->name['FirstName'], $this->name['LastName'])) {
                    $arFields = [
                        'FirstName' => $this->name['FirstName'],
                        'LastName'  => $this->name['LastName'],
                        'ConfNo'    => $match['recordLocator'],
                    ];
                    $this->retrieveLufthansaAndSwiss($arFields);
                }
            }
        }
        $this->getTime($startTimer);

        return true;
    }

    private function parseItinerariesMAM($form = [], $parsedConfs = [])
    {
        $this->logger->notice(__METHOD__);
        $this->logger->info('Parse Miles And More', ['Header' => 3]);
        $startTimer = $this->getTime();

        if (!$this->isLoggedInMAM()) {
            $headers = [
                'Accept'          => 'application/json',
                'Accept-Encoding' => 'gzip, deflate, br',
                'Referer'         => 'https://www.miles-and-more.com/',
            ];
            $this->http->RetryCount = 0;
            $this->http->GetURL('https://api.miles-and-more.com/v1/user/me/sso/ams', $headers);
            $this->http->RetryCount = 2;
            $ssoResp = $this->http->JsonLog(null, 3, true);
            $ssoData = ArrayVal($ssoResp, 'ssoData');

            if (!$ssoData) {
                $this->logger->error('ssoData not found');

                return false;
            }

            $headers = [
                'Referer' => 'https://www.miles-and-more.com/',
            ];
            $this->http->GetURL("https://book.miles-and-more.com/mam/dyn/air/servicing/ServicingEntry?COUNTRY_SITE=DE&FORCE_OVERRIDE=TRUE&LANGUAGE=GB&PORTAL=MAM&PORTAL_SESSION=OTk1NjI1MTI0MDI4NDcxODExNzMyMTcxODgxNjE3MzExNzExODI0NzExMzc4ODEzODQ=&POS=DE&SITE=5AHC5AHC&SO_SITE_COUNTRY_OF_RESIDENCE=DE&SO_SITE_LH_FRONTEND_URL=www.miles-and-more.com&WDS_IBM_LOGOUT_URL=https://www.miles-and-more.com&ENC={$ssoData}&ENCT=2&SERVICE_ID=6&PAGE=CPIT", $headers);
            $jsessionid = $this->http->FindPreg('/"sessionID":"(.+?)"/');

            if (!$jsessionid) {
                $this->logger->error('jsessionid not found');

                return false;
            }

            $this->State['jsessionid-mam'] = $jsessionid;
        } else {
            $jsessionid = ArrayVal($this->State, 'jsessionid-mam');
        }

        $headers = [
            'Accept'       => 'application/json, text/plain, */*',
            'Content-Type' => 'application/json',
            'Referer'      => 'https://book.miles-and-more.com/mam/dyn/ui/',
        ];
        $this->http->RetryCount = 0;
        $this->http->PostURL("https://book.miles-and-more.com/mam/dyn/air/servicing/myBookings;jsessionid={$jsessionid}?OUTPUT_FORMAT=json&LANGUAGE=GB&COUNTRY_SITE=US&SITE=5AHC5AHC", 'null', $headers);
        $responseMain = $this->http->JsonLog(null, 2, true);
        $bomMain = ArrayVal($responseMain, 'bom');
        $modelObjectMain = ArrayVal($bomMain, 'modelObject');
        $bookingsMain = ArrayVal($modelObjectMain, 'bookings');
        $flightsMain = ArrayVal($bookingsMain, 'bookings', []);

        if (!isset($responseMain['bom']['modelObject']['bookings']['bookings'])) {
            $this->sendNotification('MAM retry bug json // MI');
            sleep(2);
            $this->http->PostURL("https://book.miles-and-more.com/mam/dyn/air/servicing/myBookings;jsessionid={$jsessionid}?OUTPUT_FORMAT=json&LANGUAGE=GB&COUNTRY_SITE=US&SITE=5AHC5AHC", 'null', $headers);
            $bomMain = ArrayVal($responseMain, 'bom');
            $modelObjectMain = ArrayVal($bomMain, 'modelObject');
            $bookingsMain = ArrayVal($modelObjectMain, 'bookings');
            $flightsMain = ArrayVal($bookingsMain, 'bookings', []);
        }
        $this->http->RetryCount = 2;
        $this->logger->debug("Total " . count($flightsMain) . " flights were found");
        $this->logger->debug(var_export($flightsMain, true), ['pre' => true]);
        // Cabin dictionary
        $cabinDictionary = [
            'B' => 'Business',
            'E' => 'Economy',
            'N' => 'Premium Economy',
            'F' => 'First',
            //            'M' => 'Mixed',// only in header
        ];
        $this->currentItin--;

        foreach ($flightsMain as $flightMain) {
            $itinStartTimer = $this->getTime();
            $locator = ArrayVal(ArrayVal($flightMain, 'recordLocator'), 'code');
            $this->logger->debug('Parsed Locators:');
            $this->logger->debug(var_export($this->parsedLocators, true));

            if (in_array($locator, $this->parsedLocators)) {
                $this->logger->debug('Skip: locator parser');

                continue;
            }

            $this->parsedLocators[] = $locator;

            if (isset($parsedConfs[$locator])) {
                $this->logger->info("Skip MAM Itinerary #{$locator}", ['Header' => 4]);
                $this->logger->error('Already parsed');

                continue;
            }
            $this->logger->info(sprintf('[%s] Parse MAM Itinerary #%s', $this->currentItin++, $locator), ['Header' => 4]);

            $data = [
                "@c"                      => "pnr.input.MAMPnrInput",
                "recLoc"                  => $locator,
                "isDirectRetrieve"        => true,
                "isComingFromBookingList" => false,
                "isDirectLogin"           => false,
                "passengerOfPnr"          => [
                    "@c"        => "traveller.identity.IdentityInformation",
                    "lastName"  => ArrayVal($flightMain, 'lastName'),
                    "firstName" => ArrayVal($flightMain, 'firstName'),
                ],
                "isApisEntry"             => true,
            ];
            $headers = [
                "Accept"          => "application/json, text/plain, */*",
                "Accept-Encoding" => "gzip, deflate, br",
                "Content-Type"    => "application/json",
            ];
            $this->http->RetryCount = 0;
            $this->http->PostURL("https://book.miles-and-more.com/mam/dyn/air/servicing/BookingDetails;jsessionid={$jsessionid}?OUTPUT_FORMAT=json&LANGUAGE=GB&COUNTRY_SITE=DE&SITE=5AHC5AHC&PAGE_TICKET=0", json_encode($data), $headers);
            $this->http->RetryCount = 2;
            $itinError = (
                $this->http->FindPreg('/We had some difficulties retrieving your PNR\. Could it be refunded\?/') ?:
                $this->http->FindPreg('/This trip cannot be found. It may have been cancelled\./') ?:
                $this->http->FindPreg('/An error occurred servicing your request\./')
            );

            if ($itinError) {
                $this->logger->info("Skip MAM Itinerary #{$locator}", ['Header' => 4]);
                $this->logger->error("Skipping itinerary: $itinError");
                $this->currentItin--;

                continue;
            }

            $response = $this->http->JsonLog(null, 0, true);
            $bom = ArrayVal($response, 'bom');
            $modelObject = ArrayVal($bom, 'modelObject');
            $pnrRecap = ArrayVal($modelObject, 'pnrRecap');
            // for cabin
            $dictionaries = ArrayVal($bom, 'dictionaries');
            $dictionariesValues = ArrayVal($dictionaries, 'values');
            $equipments = ArrayVal($dictionariesValues, '30', []);

            $r = $this->itinerariesMaster->add()->flight();
            $r->general()->confirmation($locator);

            // price
            $paymentRecap = ArrayVal($pnrRecap, 'paymentRecap', []);
            $taxes = ArrayVal($paymentRecap, 'taxes', []);
            $totalWithoutTaxes = ArrayVal($paymentRecap, 'totalWithoutTaxes', []);
            $total = ArrayVal($paymentRecap, 'total', []);
            $priceRecap = ArrayVal($pnrRecap, 'priceRecap');
            $priceForAllTravellers = ArrayVal($priceRecap, 'priceForAllTravellers');

            $r->price()
                ->tax(ArrayVal($taxes, 'amount', null) ?? null, false, true)
                ->cost(ArrayVal($totalWithoutTaxes, 'amount', null) ?? null, false, true)
                ->total(ArrayVal($total, 'amount', null) ?? null, false, true)
                ->currency(ArrayVal($total, 'currency', null), false, true)
                ->spentAwards($priceForAllTravellers[0]['totalPrice']['milesAmount']['amount'] ?? null, false, true);

            // travellers
            $travellersInformation = ArrayVal($pnrRecap, 'travellersInformation');
            $travellers = ArrayVal($travellersInformation, 'travellers', []);
            $frequentFlyerNumbers = [];
            $meals = [];
            $seatInformationsList = [];

            foreach ($travellers as $traveller) {
                // AccountNumbers
                if ($frequentFlyerNumber = ArrayVal(ArrayVal($traveller, 'frequentFlyerInformation'), 'accountNumber', null)) {
                    $frequentFlyerNumbers[] = $frequentFlyerNumber;
                }
                // Passengers
                $identityInformation = ArrayVal($traveller, 'identityInformation');
                $name = beautifulName(trim(sprintf('%s %s',
                    ArrayVal($identityInformation, 'firstName'),
                    ArrayVal($identityInformation, 'lastName')
                )));
                $r->general()->traveller($name, true);
                // Meal
                if ($meal = ArrayVal(ArrayVal($traveller, 'meal'), 'name', null)) {
                    $meals[] = $meal;
                }
                $seatInformationsList[] = ArrayVal($traveller, 'seatInformations');
            }// foreach ($travellers as $traveller)

            $frequentFlyerNumbers = array_unique($frequentFlyerNumbers);

            if (!empty($frequentFlyerNumbers)) {
                $r->program()->accounts($frequentFlyerNumbers, false);
            }

            $tickets = ArrayVal($pnrRecap, 'tickets', []);
            $ticketNumbers = [];

            foreach ($tickets as $ticket) {
                if ($ticketNumber = ArrayVal($ticket, 'ticketNumber', null)) {
                    $ticketNumbers[] = $ticketNumber;
                }
            }
            $ticketNumbers = array_unique($ticketNumbers);

            if (!empty($ticketNumber)) {
                $r->issued()->tickets($ticketNumbers, false);
            }

            // air segments
            $airRecap = ArrayVal($pnrRecap, 'airRecap');
            $flightBounds = ArrayVal($airRecap, 'flightBounds', []);
            $this->logger->debug("Total " . count($flightBounds) . " legs of flight were found");
            // Cancelled itinerary
            if (count($flightBounds) == 0) {
                $data = [
                    "COUNTRY_SITE"        => ArrayVal($form, 'COUNTRY_SITE'),
                    "DEVICE_TYPE"         => "DESKTOP",
                    "ENC"                 => ArrayVal($form, 'ENC'),
                    "ENCT"                => ArrayVal($form, 'ENCT'),
                    "LANGUAGE"            => ArrayVal($form, 'LANGUAGE'),
                    "SERVICE_ID"          => ArrayVal($form, 'SERVICE_ID'),
                    "SITE"                => ArrayVal($form, 'SITE'),
                    "WDS_DIRECT_RETRIEVE" => "false",
                    "WDS_IS_ADD_BOOKING"  => "false",
                    "WDS_LAST_NAME"       => ArrayVal($flightMain, 'lastName'),
                    "WDS_REC_LOC"         => $locator,
                ];
                $this->http->PostURL("https://book.lufthansa.com/lh/dyn/air-lh/servicing/booking", $data);
                $itinError = $this->http->FindPreg('/Unfortunately we cannot process your enquiry at the moment due to technical reasons/');

                if ($itinError) {
                    $this->logger->info("Skip MAM Itinerary #{$locator}", ['Header' => 4]);
                    $this->logger->error("Skipping itinerary: {$itinError}");
                    $this->currentItin--;
                    $this->itinerariesMaster->removeItinerary($r);

                    continue;
                }
                $bookingInfo = $this->http->JsonLog(null, 0, true);
                $isCanceled = ArrayVal(ArrayVal($bookingInfo, 'booking'), 'isCanceled', null);
                // cancelled itinerary
                if ($isCanceled === true) {
                    $this->logger->notice(">>> Itinerary with Booking Code {$locator} has been cancelled");
                    $r->general()->cancelled();
                }// if ($isCanceled === true)
            }// if (count($flightBounds) == 0)

            foreach ($flightBounds as $flightBound) {
                $flightSegments = ArrayVal($flightBound, 'flightSegments', []);
                $this->logger->debug("Total " . count($flightSegments) . " segments were found");

                foreach ($flightSegments as $flightSegment) {
                    $cabin = ArrayVal($flightSegment, 'cabins', null);

                    if (isset($cabin[0]['code'])) {
                        $cabin = $cabinDictionary[$cabin[0]['code']] ?? null;

                        if (empty($cabin)) {
                            $this->sendNotification("refs #16526 - Unknown cabin {$cabin[0]['code']} was found");
                            $cabin = null;
                        }// if (empty($cabin))
                    }// if (isset($cabin[0]['code']))
                    // FlightNumber, ArrDate, AirlineName
                    $flightIdentifier = ArrayVal($flightSegment, 'flightIdentifier');
                    $depInfo = ArrayVal($flightSegment, 'originLocation');
                    $arrInfo = ArrayVal($flightSegment, 'destinationLocation');
                    $depCode = $this->http->FindPreg("/_([A-Z]{3})/", false, $depInfo);
                    $arrCode = $this->http->FindPreg("/_([A-Z]{3})/", false, $arrInfo);
                    $depTerminal = $this->http->FindPreg("/T(.*?)_[A-Z]{3}/", false, $depInfo);
                    $arrTerminal = $this->http->FindPreg("/T(.*?)_[A-Z]{3}/", false, $arrInfo);
                    // Duration
                    $duration = $this->http->FindPreg('/(\d+)000$/', false, ArrayVal($flightSegment, 'duration'));
                    // Cabin
                    $equipment = ArrayVal($flightSegment, 'equipment', null);
                    // Seats
                    $id = ArrayVal($flightSegment, 'id');
                    $seats = [];

                    foreach ($seatInformationsList as $seatInformationsRow) {
                        if (is_array($seatInformationsRow)) {
                            foreach ($seatInformationsRow as $seatInformation) {
                                if ($id && ArrayVal($seatInformation, 'segmentId') == $id && ArrayVal($seatInformation, 'seatStatus') === "CONFIRMED") {
                                    $seats[] = ArrayVal($seatInformation, 'seatAssignment');
                                }
                            }
                        }// if (is_array($seatInformationsRow))
                    }// foreach ($seatInformationsList as $seatInformationsRow)
                    $s = $r->addSegment();
                    $s->airline()
                        ->name(ArrayVal($flightIdentifier, 'marketingAirline'))
                        ->number(ArrayVal($flightIdentifier, 'flightNumber'));
                    $s->departure()
                        ->date((int) $this->http->FindPreg('/(\d+)000$/', false, ArrayVal($flightIdentifier, 'originDate')))
                        ->code($depCode)
                        ->terminal($depTerminal, true);
                    $s->arrival()
                        ->date((int) $this->http->FindPreg('/(\d+)000$/', false, ArrayVal($flightSegment, 'destinationDate')))
                        ->code($arrCode)
                        ->terminal($arrTerminal, true);
                    $s->extra()
                        ->stops(ArrayVal($flightSegment, 'numberOfStops'), true, false)
                        ->aircraft($equipments[$equipment]['name'] ?? null, false, true)
                        ->meals($meals)
                        ->seats($seats)
                        ->cabin($cabin)
                        ->duration(date("h", $duration) . "h " . date("i", $duration) . "m");
                }// foreach ($flightSegments as $flightSegment)
            }// foreach ($flightBounds as $flightBound)
            $this->logger->debug('Parsed Itinerary:');
            $this->logger->debug(var_export($r->toArray(), true), ['pre' => true]);
            $this->getTime($itinStartTimer);
        }// foreach ($flightsMain as $flightMain)

        $this->getTime($startTimer);

        return true;
    }

    private function getClientSideJson(): ?array
    {
        $this->logger->notice(__METHOD__);
        $res = null;
        $data = $this->http->FindPreg('/var clientSideData = (\{.+?\});\s+/ims');

        if (!empty($data)) {
            $res = $this->http->JsonLog($data, 2, true);
        }

        if (!$res) {
            $body = $this->http->Response['body'];
            $s1 = 'var clientSideData = ';
            $s2 = '}; ';

            $begin = strpos($body, $s1);

            if ($begin === false) {
                return null;
            }
            $end = strpos($body, $s2, $begin + 1);

            if ($begin !== false && $end !== false) {
                $data = substr($body, $begin + strlen($s1), $end - ($begin + strlen($s1)) + 1);
                $res = $this->http->JsonLog($data, 2, true);
            }
        }

        return $res;
    }

    private function parseFlightJson($parsedConfs = []): bool
    {
        $this->logger->notice(__METHOD__);
        $this->logger->debug('[Parse start date: ' . date('Y/m/d H:i:s') . ']');
        $startTimer = $this->getTime();
        $json = $this->getClientSideJson();

        if (!$json) {
            $timeout = $this->http->FindPreg('/Operation timed out/', false, $this->http->Response['errorMessage']);

            if (!$this->geetestFailed && !$timeout) {
//                $this->sendNotification('check itineraries');
            }

            return true;
        }
        // for debug
        // $this->http->JsonLog(json_encode($json), 2);
        $locator = $this->http->FindPreg('/"recordLocator":\s*"(.+?)"/');

        if (!$locator) {
            return true;
        }

        if (isset($parsedConfs[$locator])) {
            $this->logger->info("Skip Itinerary #{$locator}", ['Header' => 4]);
            $this->logger->error('Already parsed');

            return true;
        }

        $r = $this->itinerariesMaster->add()->flight();
        $r->general()->confirmation($locator);

        if (
            array_key_exists('dictionaries', $json['tpi'] ?? [])
            && $json['tpi']['dictionaries'] === null
        ) {
            $this->itinerariesMaster->removeItinerary($r);
            $this->logger->error('Skipping invalid flight');

            return true;
        }

        if (!isset($json['tpi']['dictionaries']['flightSegments'], $json['PAGE']['PANELS']['IRC_FLIGHTS']['CATALOGS']['FLIGHT_STATUS_LABEL']['ENTRIES'])) {
            $this->logger->error('Could not find flight segments in clientSideData json');

            return true;
        }
        $flStatusLabel = $json['PAGE']['PANELS']['IRC_FLIGHTS']['CATALOGS']['FLIGHT_STATUS_LABEL']['ENTRIES'];
        $flStatus = $json['PAGE']['PANELS']['IRC_FLIGHTS']['CATALOGS']['FLIGHT_STATUS']['ENTRIES'];
        $statusDict = $segmetsDetails = [];

        foreach ($flStatus as $st=>$label) {
            if (isset($flStatusLabel[$label])) {
                $statusDict[$st] = $flStatusLabel[$label];
            }
        }

        foreach ($json['PAGE']['PANELS']['IRC_FLIGHTS']['DATA'] as $key => $data) {
            if (strpos($key, 'BOUND_') === 0) {
                foreach ($data['LIST_SEGMENT_FLIGHT_DETAILS'] as $seg) {
                    $segmetsDetails[$seg['FLIGHT_NUMBER'] . '-' . $seg['DEPARTURE_DATE'] . '-' . $seg['DEPARTURE_TIME']] = $seg;
                }
            }
        }

        $locations = $json['tpi']['dictionaries']['locations'];
        $locationDict = [];

        foreach ($locations as $loc) {
            $locationDict[$loc['id']] = $loc['code'];
        }
        $airlines = $json['tpi']['dictionaries']['airlines'];
        $airlineDict = [];
        $airlineNameDict = [];

        foreach ($airlines as $air) {
            $airlineDict[$air['id']] = $air['code'];
            $airlineNameDict[$air['id']] = $air['code'];
        }
        $aircrafts = $json['tpi']['dictionaries']['aircrafts'];
        $aircraftsDict = [];

        foreach ($aircrafts as $aircraft) {
            $aircraftsDict[$aircraft['id']] = $aircraft['name'];
        }
        $cabins = $json['tpi']['dictionaries']['flightCabins'];
        $cabinDict = [];

        foreach ($cabins as $cab) {
            $cabinDict[$cab['code']] = $cab['name'];
        }
        // AccountNumbers
        $frequentFlyerNumbers = [];
        $frequentFlyerAccounts = $json['tpi']['dictionaries']['frequentFlyerAccounts'];

        foreach ($frequentFlyerAccounts as $frequentFlyerAccount) {
            $frequentFlyerNumbers[] = $frequentFlyerAccount['frequentFlyerNumber'];
        }
        $r->program()->accounts($frequentFlyerNumbers, false);

        if (isset($json['tpi']['dictionaries']['shoppingCarts'][0]['seatDetails']['seats'])) {
            $seats = $json['tpi']['dictionaries']['shoppingCarts'][0]['seatDetails']['seats'];
        } else {
            $seats = [];
        }

        // price
        $totalCharge = (
            $this->arrayVal($json, ['uiModel', 'alreadyBookedShoppingCart', 'airItem', 'totalPrice', 'amount'])
            ?: $this->arrayVal($json, ['uiModel', 'alreadyBookedShoppingCart', 'airItem', 'flightsItem', 'totalPrice', 'amount'])
        );

        if ($totalCharge) {
            $r->price()->total(round($totalCharge, 2));
        }
        $r->price()->currency($this->arrayVal($json, ['uiModel', 'alreadyBookedShoppingCart', 'airItem', 'flightsItem', 'totalPrice', 'currency', 'code']));

        // travellers, tickets
        if (isset($json['PAGE']['DATA']['PASSENGERS'])) {
            $passengersJson = array_values($json['PAGE']['DATA']['PASSENGERS']);
            $passengers = [];
            $ticketNumbers = [];

            foreach ($passengersJson as $pass) {
                $title = $pass['TITLE'] ?? '';
                $firstName = $pass['FIRST_NAME'] ?? '';
                $lastName = $pass['LAST_NAME'] ?? '';
                $name = beautifulName(trim("{$title} {$firstName} {$lastName}"));
                $passengers[] = $name;

                if ($ticketNumber = $pass['TICKET_NUMBERS'] ?? null) {
                    $ticketNumberParts = explode(';', $ticketNumber);

                    foreach ($ticketNumberParts as $ticketNumberPart) {
                        $ticketNumbers[] = $ticketNumberPart;
                    }
                }
            }// foreach ($passengersJson as $pass)
            $r->issued()->tickets($ticketNumbers, false);
            $r->general()->travellers($passengers, true);
        }// if (isset($json['PAGE']['DATA']['PASSENGERS']))
        // TripSegments
        $train = null;

        foreach ($json['tpi']['dictionaries']['flightSegments'] as $fs) {
            if (
                (
                    $this->http->FindPreg('/^(.+?)T/', false, $fs['departureTime'])
                    === $this->http->FindPreg('/^(.+?)T/', false, $fs['arrivalTime'])
                )
                && $this->http->FindPreg('/T00:00/', false, $fs['departureTime'])
                && $this->http->FindPreg('/T00:00/', false, $fs['arrivalTime'])
            ) {
                if ($fs['flightNumber'] === 'OPEN') {
                    $this->logger->error('Skip open flight');

                    continue;
                }
                $this->sendNotification('check bad segment // MI');
            }

            $aircraft = trim($aircraftsDict[$fs['aircraftId']] ?? '');
            $airlineName = $airlineDict[$fs['marketingAirlineId']] ?? null;

            if (empty($airlineName)) {
                $airlineName = $airlineDict[$fs['operatingAirlineId']] ?? null;
            }

            $depDate = strtotime($fs['departureTime']);
            $depCode = $locationDict[$fs['departureLocationId']] ?? null;
            $arrDate = strtotime($fs['arrivalTime']);
            $arrCode = $locationDict[$fs['arrivalLocationId']] ?? null;

            if ($arrCode === $depCode && $depDate === $arrDate) {
                $this->logger->notice("[{$airlineName} {$fs['flightNumber']}]: skip wrong segment: from {$depCode} to {$arrCode}");

                if (count($json['tpi']['dictionaries']['flightSegments']) == 1) {
                    $this->logger->error("Remove wrong itinerary from result");
                    $this->itinerariesMaster->removeItinerary($r);

                    if (isset($train)) {
                        $this->itinerariesMaster->removeItinerary($train);
                    }

                    return false;
                }
            }
            $details = $segmetsDetails[$fs['flightNumber'] . '-' . date("Ymd-H:i", $depDate)] ?? null;
            // for debug
            $this->http->JsonLog(json_encode($details));

            if (
                (isset($airlineName) && (stripos($airlineName, 'Deutsche Bahn') !== false))
                || $aircraft === 'TRS'
                || $aircraft === 'Train'
                || $aircraft === 'High Speed Train'
                || (isset($details, $details['OPERATED_BY_TRAIN']) && $details['OPERATED_BY_TRAIN'])
            ) {
                if (!isset($train)) {
                    $train = $this->itinerariesMaster->add()->train();
                    $train->general()->confirmation($locator);

                    if (isset($passengers)) {
                        $train->general()->travellers($passengers, true);
                    }
                }
                $s = $train->addSegment();
                $s->extra()
                    ->service($airlineName, false, true)
                    ->number($fs['flightNumber']);
            } elseif ($aircraft === 'Surface Equipment-Bus') {
                if (!isset($bus)) {
                    $bus = $this->itinerariesMaster->add()->bus();
                    $bus->general()->confirmation($locator);

                    if (isset($passengers)) {
                        $bus->general()->travellers($passengers, true);
                    }
                }
                $s = $bus->addSegment();
                $s->extra()
                    ->type($airlineName, false, true)
                    ->number($fs['flightNumber']);
            } else {
                $s = $r->addSegment();

                $s->airline()
                    ->name($airlineName)
                    ->number($fs['flightNumber']);

                if (isset($details['OPERATED_BY_AIRLINE_CODE']) && !empty($details['OPERATED_BY_AIRLINE_CODE'])) {
                    $s->airline()->operator($details['OPERATED_BY_AIRLINE_CODE']);
                }

                $s->departure()->terminal($fs['departureTerminal'], false, true);
                $s->arrival()->terminal($fs['arrivalTerminal'], false, true);
            }

            if (isset($details['BOOKING_CLASS']) && !empty($details['BOOKING_CLASS'])) {
                $s->extra()->bookingCode($details['BOOKING_CLASS']);
            }

            $s->extra()
                ->cabin($cabinDict[$fs['cabin']] ?? null, false, true)
                ->duration(sprintf('%.2dh %.2dmin', floor($fs['flightTime'] / 1000 / 60 / 60), ($fs['flightTime'] / 1000 / 60) % 60));

            if (isset($fs['numberOfStops'])) {
                $s->extra()->stops($fs['numberOfStops']);
            }

            $s->departure()
                ->date($depDate)
                ->code($depCode);
            $s->arrival()
                ->date($arrDate)
                ->code($arrCode);

            // status
            if (isset($statusDict[$fs['status']])) {
                $s->extra()->status($statusDict[$fs['status']]);
            } else {
                $this->sendNotification("check status dictionary // ZM");

                switch ($fs['status']) {
                case 'HK':
                case 'HX'://First
                case 'PK':
                case 'KL':
                case 'RR'://Economy
                case 'GK'://Business
                    $s->extra()->status('Confirmed');

                    break;

                case 'TK':
                case 'TL':
                    $s->extra()->status('Schedule change');

                    break;

                case 'UN':
                case 'UC':
                    $s->extra()->status('Cancelled')->cancelled();

                    break;

                case 'HL':
                    $s->extra()->status('Wait list');

                    break;

                default:
                    $this->sendNotification("check status {$fs['status']}");

                    break;
            }
            }
            $segSeats = null;

            if (!empty($seats)) {
                foreach ($seats as $seat) {
                    if ($fs['id'] == $seat['segmentFareId'] && $seat['number']) {
                        $segSeats[] = $seat['number'];
                    }
                }// foreach ($seats as $seat)
            }

            if (is_array($segSeats)) {
                $s->extra()->seats(array_unique($segSeats));
            }
        }// foreach ($json['tpi']['dictionaries']['flightSegments'] as $fs)

        if (isset($train)) {
            $this->logger->debug('Parsed Itinerary (Train):');
            $this->logger->debug(var_export($train->toArray(), true), ['pre' => true]);
        }

        if (isset($bus)) {
            $this->logger->debug('Parsed Itinerary (Bus):');
            $this->logger->debug(var_export($bus->toArray(), true), ['pre' => true]);
        }

        if (count($r->getSegments()) === 0 && (isset($train) || isset($bus))) {
            $this->itinerariesMaster->removeItinerary($r);
        } else {
            $this->logger->debug('Parsed Itinerary:');
            $this->logger->debug(var_export($r->toArray(), true), ['pre' => true]);
        }

        $this->getTime($startTimer);

        return true;
    }

    private function parseFlightJsonV2($json): bool
    {
        $this->logger->notice(__METHOD__);
        $this->logger->debug('[Parse start date: ' . date('Y/m/d H:i:s') . ']');
        $startTimer = $this->getTime();

        if (!$json) {
            $timeout = $this->http->FindPreg('/Operation timed out/', false, $this->http->Response['errorMessage']);

            if (!$this->geetestFailed && !$timeout) {
//                $this->sendNotification('check itineraries');
            }

            return true;
        }
        // for debug
        // $this->http->JsonLog(json_encode($json), 2);
        $locator = $this->http->FindPreg('/"recordLocator":\s*"(.+?)"/');

        if (!$locator) {
            return true;
        }

        $r = $this->itinerariesMaster->add()->flight();
        $this->parsedLocators[] = $locator;
        $r->general()->confirmation($locator);

        if (
            array_key_exists('dictionaries', $json['tpi'] ?? [])
            && $json['tpi']['dictionaries'] === null
        ) {
            $this->itinerariesMaster->removeItinerary($r);
            $this->logger->error('Skipping invalid flight');

            return true;
        }

        if (!isset($json['tpi']['dictionaries']['flightSegments'], $json['PAGE']['PANELS']['IRC_FLIGHTS']['CATALOGS']['FLIGHT_STATUS_LABEL']['ENTRIES'])) {
            $this->itinerariesMaster->removeItinerary($r);
            $this->logger->error('Could not find flight segments in clientSideData json');

            return true;
        }
        $flStatusLabel = $json['PAGE']['PANELS']['IRC_FLIGHTS']['CATALOGS']['FLIGHT_STATUS_LABEL']['ENTRIES'];
        $flStatus = $json['PAGE']['PANELS']['IRC_FLIGHTS']['CATALOGS']['FLIGHT_STATUS']['ENTRIES'];
        $statusDict = $segmetsDetails = [];

        foreach ($flStatus as $st=>$label) {
            if (isset($flStatusLabel[$label])) {
                $statusDict[$st] = $flStatusLabel[$label];
            }
        }

        foreach ($json['PAGE']['PANELS']['IRC_FLIGHTS']['DATA'] as $key => $data) {
            if (strpos($key, 'BOUND_') === 0) {
                foreach ($data['LIST_SEGMENT_FLIGHT_DETAILS'] as $seg) {
                    $segmetsDetails[$seg['FLIGHT_NUMBER'] . '-' . $seg['DEPARTURE_DATE'] . '-' . $seg['DEPARTURE_TIME']] = $seg;
                }
            }
        }

        $locations = $json['tpi']['dictionaries']['locations'];
        $locationDict = [];

        foreach ($locations as $loc) {
            $locationDict[$loc['id']] = $loc['code'];
        }
        $airlines = $json['tpi']['dictionaries']['airlines'];
        $airlineDict = [];
        $airlineNameDict = [];

        foreach ($airlines as $air) {
            $airlineDict[$air['id']] = $air['code'];
            $airlineNameDict[$air['id']] = $air['code'];
        }
        $aircrafts = $json['tpi']['dictionaries']['aircrafts'];
        $aircraftsDict = [];

        foreach ($aircrafts as $aircraft) {
            $aircraftsDict[$aircraft['id']] = $aircraft['name'];
        }
        $cabins = $json['tpi']['dictionaries']['flightCabins'];
        $cabinDict = [];

        foreach ($cabins as $cab) {
            $cabinDict[$cab['code']] = $cab['name'];
        }
        // AccountNumbers
        $frequentFlyerNumbers = [];
        $frequentFlyerAccounts = $json['tpi']['dictionaries']['frequentFlyerAccounts'];

        foreach ($frequentFlyerAccounts as $frequentFlyerAccount) {
            $frequentFlyerNumbers[] = $frequentFlyerAccount['frequentFlyerNumber'];
        }

        if (!empty($frequentFlyerNumbers)) {
            $frequentFlyerNumbers = array_unique($frequentFlyerNumbers);
            $r->program()->accounts($frequentFlyerNumbers, false);
        }

        if (isset($json['tpi']['dictionaries']['shoppingCarts'][0]['seatDetails']['seats'])) {
            $seats = $json['tpi']['dictionaries']['shoppingCarts'][0]['seatDetails']['seats'];
        } else {
            $seats = [];
        }

        // price
        $totalCharge = (
        $this->arrayVal($json, ['uiModel', 'alreadyBookedShoppingCart', 'airItem', 'totalPrice', 'amount'])
            ?: $this->arrayVal($json, ['uiModel', 'alreadyBookedShoppingCart', 'airItem', 'flightsItem', 'totalPrice', 'amount'])
        );

        // 1147416000.00 IRR failed to verify
        if ($totalCharge && strlen($totalCharge) < 10) {
            $r->price()->total(round($totalCharge, 2));
            $r->price()->currency($this->arrayVal($json, ['uiModel', 'alreadyBookedShoppingCart', 'airItem', 'flightsItem', 'totalPrice', 'currency', 'code']));
        }

        // travellers, tickets
        if (isset($json['PAGE']['DATA']['PASSENGERS'])) {
            $passengersJson = array_values($json['PAGE']['DATA']['PASSENGERS']);
            $passengers = [];
            $ticketNumbers = [];

            foreach ($passengersJson as $pass) {
                $title = $pass['TITLE'] ?? '';
                $firstName = $pass['FIRST_NAME'] ?? '';
                $lastName = $pass['LAST_NAME'] ?? '';
                $name = beautifulName(trim("{$title} {$firstName} {$lastName}"));
                $passengers[] = $name;

                if ($ticketNumber = $pass['TICKET_NUMBERS'] ?? null) {
                    $ticketNumberParts = explode(';', $ticketNumber);

                    foreach ($ticketNumberParts as $ticketNumberPart) {
                        $ticketNumbers[] = $ticketNumberPart;
                    }
                }
            }// foreach ($passengersJson as $pass)
            $r->issued()->tickets(array_unique($ticketNumbers), false);
            $r->general()->travellers($passengers, true);
        }// if (isset($json['PAGE']['DATA']['PASSENGERS']))
        // TripSegments
        $train = null;

        foreach ($json['tpi']['dictionaries']['flightSegments'] as $fs) {
            if (
                (
                    $this->http->FindPreg('/^(.+?)T/', false, $fs['departureTime'])
                    === $this->http->FindPreg('/^(.+?)T/', false, $fs['arrivalTime'])
                )
                && $this->http->FindPreg('/T00:00/', false, $fs['departureTime'])
                && $this->http->FindPreg('/T00:00/', false, $fs['arrivalTime'])
            ) {
                if ($fs['flightNumber'] === 'OPEN') {
                    $this->logger->error('Skip open flight');

                    continue;
                }
                $this->sendNotification('check bad segment // MI');
            }

            $aircraft = trim($aircraftsDict[$fs['aircraftId']] ?? '');
            $airlineName = $airlineDict[$fs['marketingAirlineId']] ?? null;

            if (empty($airlineName)) {
                $airlineName = $airlineDict[$fs['operatingAirlineId']] ?? null;
            }

            $depDate = strtotime($fs['departureTime']);
            $depCode = $locationDict[$fs['departureLocationId']] ?? null;
            $arrDate = strtotime($fs['arrivalTime']);
            $arrCode = $locationDict[$fs['arrivalLocationId']] ?? null;

            if ($arrCode === $depCode && $depDate === $arrDate) {
                $this->logger->notice("[{$airlineName} {$fs['flightNumber']}]: skip wrong segment: from {$depCode} to {$arrCode}");

                if (count($json['tpi']['dictionaries']['flightSegments']) == 1) {
                    $this->logger->error("Remove wrong itinerary from result");
                    $this->itinerariesMaster->removeItinerary($r);

                    if (isset($train)) {
                        $this->itinerariesMaster->removeItinerary($train);
                    }

                    return false;
                }
            }
            $details = $segmetsDetails[$fs['flightNumber'] . '-' . date("Ymd-H:i", $depDate)] ?? null;
            // for debug
            $this->http->JsonLog(json_encode($details));

            if (
                (isset($airlineName) && (stripos($airlineName, 'Deutsche Bahn') !== false))
                || $aircraft === 'TRS'
                || $aircraft === 'Train'
                || $aircraft === 'High Speed Train'
                || (isset($details, $details['OPERATED_BY_TRAIN']) && $details['OPERATED_BY_TRAIN'])
            ) {
                if (!isset($train)) {
                    $train = $this->itinerariesMaster->add()->train();
                    $train->general()->confirmation($locator);

                    if (isset($passengers)) {
                        $train->general()->travellers($passengers, true);
                    }
                }
                $s = $train->addSegment();
                $s->extra()
                    ->service($airlineName, false, true)
                    ->number($fs['flightNumber']);
            } elseif ($aircraft === 'Surface Equipment-Bus') {
                if (!isset($bus)) {
                    $bus = $this->itinerariesMaster->add()->bus();
                    $bus->general()->confirmation($locator);

                    if (isset($passengers)) {
                        $bus->general()->travellers($passengers, true);
                    }
                }
                $s = $bus->addSegment();
                $s->extra()
                    ->type($airlineName, false, true)
                    ->number($fs['flightNumber']);
            } else {
                $s = $r->addSegment();

                $s->airline()
                    ->name($airlineName)
                    ->number($fs['flightNumber']);

                if (isset($details['OPERATED_BY_AIRLINE_CODE']) && !empty($details['OPERATED_BY_AIRLINE_CODE'])) {
                    $s->airline()->operator($details['OPERATED_BY_AIRLINE_CODE']);
                }

                $s->departure()->terminal($fs['departureTerminal'], false, true);
                $s->arrival()->terminal($fs['arrivalTerminal'], false, true);
            }

            if (isset($details['BOOKING_CLASS']) && !empty($details['BOOKING_CLASS'])) {
                $s->extra()->bookingCode($details['BOOKING_CLASS']);
            }

            $s->extra()
                ->cabin($cabinDict[$fs['cabin']] ?? null, false, true)
                ->duration(sprintf('%.2dh %.2dmin', floor($fs['flightTime'] / 1000 / 60 / 60), ($fs['flightTime'] / 1000 / 60) % 60));

            if (isset($fs['numberOfStops'])) {
                $s->extra()->stops($fs['numberOfStops']);
            }

            $s->departure()
                ->date($depDate)
                ->code($depCode);
            $s->arrival()
                ->date($arrDate)
                ->code($arrCode);

            // status
            if (isset($statusDict[$fs['status']])) {
                $s->extra()->status($statusDict[$fs['status']]);
            } else {
                $this->sendNotification("check status dictionary // MI");

                switch ($fs['status']) {
                    case 'HK':
                    case 'HX'://First
                    case 'PK':
                    case 'KL':
                    case 'RR'://Economy
                    case 'GK'://Business
                        $s->extra()->status('Confirmed');

                        break;

                    case 'TK':
                    case 'TL':
                        $s->extra()->status('Schedule change');

                        break;

                    case 'UN':
                    case 'UC':
                        $s->extra()->status('Cancelled')->cancelled();

                        break;

                    case 'HL':
                        $s->extra()->status('Wait list');

                        break;

                    default:
                        $this->sendNotification("check status {$fs['status']}");

                        break;
                }
            }
            $segSeats = null;

            if (!empty($seats)) {
                foreach ($seats as $seat) {
                    if ($fs['id'] == $seat['segmentFareId'] && $seat['number']) {
                        $segSeats[] = $seat['number'];
                    }
                }// foreach ($seats as $seat)
            }

            if (is_array($segSeats)) {
                $s->extra()->seats(array_unique($segSeats));
            }
        }// foreach ($json['tpi']['dictionaries']['flightSegments'] as $fs)

        if (isset($train)) {
            $this->logger->debug('Parsed Itinerary (Train):');
            $this->logger->debug(var_export($train->toArray(), true), ['pre' => true]);
        }

        if (isset($bus)) {
            $this->logger->debug('Parsed Itinerary (Bus):');
            $this->logger->debug(var_export($bus->toArray(), true), ['pre' => true]);
        }

        if (count($r->getSegments()) === 0 && (isset($train) || isset($bus))) {
            $this->itinerariesMaster->removeItinerary($r);
        } else {
            $this->logger->debug('Parsed Itinerary:');
            $this->logger->debug(var_export($r->toArray(), true), ['pre' => true]);
        }

        $this->getTime($startTimer);

        return true;
    }

    private function getParsedFlightConfs()
    {
        $this->logger->notice(__METHOD__);
        $confs = [];

        foreach ($this->itinerariesMaster->getItineraries() as $it) {
            if ($it->getType() === 'flight') {
                foreach ($it->getConfirmationNumbers() as $conf) {
                    if (isset($conf['number'])) {
                        $confs[] = $conf['number'];
                    }
                }
            }
        }
        $this->logger->debug(var_export($confs, true));

        return $confs;
    }

    private function arrayVal($ar, $indices, $default = null)
    {
        if (!is_array($indices)) {
            $indices = [$indices];
        }
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

    private function parseItinerariesJsonV2()
    {
        $this->logger->notice(__METHOD__);
        // $jsessionid = $this->http->FindPreg('/"session"\s*:\s*\{\s*"jsession"\s*:\s*"(.+?)"/ims');

        $this->http->setHttp2(false);
//        $headers = [
//            'Accept' => 'application/json, text/javascript, */*; q=0.01',
//            'Content-Type' => 'application/x-www-form-urlencoded',
//            'Referer' => 'https://book.lufthansa.com/lh/dyn/air-lh/servicing/cockpit',
//            'Origin' => 'https://book.lufthansa.com',
//        ];
//        $data = [
//            'COUNTRY_SITE' => 'DE',
//            'LANGUAGE' => 'GB',
//            'SITE' => 'LUFTLUFT',
//            'PORTAL' => 'LH',
//            'WDS_PAGE_CODE' => 'CKPT',
//        ];
//        $this->http->PostURL("https://book.lufthansa.com/lh/dyn/air-lh/servicing/editBookingOptions;jsessionid={$jsessionid}", $data, $headers);

//        sleep(5);
//        $headers = [
//            'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8,application/signed-exchange;v=b3;q=0.9',
//            'Content-Type' => 'application/x-www-form-urlencoded',
//            'Referer' => 'https://book.lufthansa.com/lh/dyn/air-lh/servicing/cockpit',
//            'Origin' => 'https://book.lufthansa.com',
//            'Connection' => '',
//        ];
//        $data = [
//            'COUNTRY_SITE' => 'DE',
//            'LANGUAGE' => 'GB',
//            'SITE' => 'LUFTLUFT',
//            'PORTAL' => 'LH',
//            'WDS_EXPAND_ITEM' => 'FLIGHT',
//        ];
//        $this->http->PostURL("https://book.lufthansa.com/lh/dyn/air-lh/servicing/service;jsessionid={$jsessionid}", $data, $headers);

        $script = $this->http->FindSingleNode("//script[contains(text(), 'var clientSideData')]");
        $clientSideData = $this->http->FindPreg('/var clientSideData = (.+); var lhgData/ims', false, $script);

        if (!empty($clientSideData)) {
            $json = $this->http->JsonLog(preg_replace('/"VALIDATION_REGEXPS":.+?\}\},/sm', '', $clientSideData), 0, true);
            $this->parseFlightJsonV2($json);
        }
    }

    private function ParseItinerariesJson($form = [])
    {
        $this->logger->notice(__METHOD__);
        $this->logger->debug('[Parse start date: ' . date('Y/m/d H:i:s') . ']');
        $startTimer = $this->getTime();
        $result = [];

        $script = $this->http->FindSingleNode("//script[contains(text(), 'var clientSideData')]");
        $clientSideData = $this->http->FindPreg("/var\s*clientSideData\s*=\s*(\{.+\});\s*var\s*lhgData/ims", false, $script);

        if (!$clientSideData) {
            $clientSideData = $this->http->FindPreg("/var\s*clientSideData\s*=\s*(\{.+\});\s*$/ims", false, $script);
        }

        if (!$clientSideData) {
            $clientSideData = $this->http->FindPreg("/var\s*clientSideData\s*=\s*(\{.+\});\s*\]\]\>\s*$/ims", false, $script);
        }
        $this->logger->notice('clientSideData json hide');
        $json = $this->http->JsonLog($clientSideData, 0, true);
        $jsessionid = $this->http->FindPreg('/jsessionid=(.+?)["?]/ims');

        if (!isset($json['tpi']['bookingList'])) {
            $this->logger->error('Could not find itineraries in lufthansa clientSideData json');

            /* if ($this->http->FindPreg('/\blogin\b/', false, $this->http->currentUrl())) {
                 if (!$this->isLoggedInMAM()) {
                     $this->loginMAMApi();
                 }
                 $this->currentItin = 1;

                 if (!$this->invalidMAMLogin) {
                     return $this->parseItinerariesMAM($form, []);
                 }
             }*/

            return false;
        }
        $bookingList = $json['tpi']['bookingList'];
        $locators = array_map(function ($item) { return $item['recordLocator']; }, $bookingList);
        $this->logger->debug('locators: ' . var_export($locators, true));

        if (empty($bookingList)) {
            if (isset($this->State['LastReservation'])) {
                ++$this->State['LastReservation'];
            }
            $this->getTime($startTimer);

            return [];
        }
        $locators = [];
        $parsedConfs = [];
        $headers2 = [
            'Accept'        => 'application/json, text/javascript, */*; q=0.01',
            'Content-Type'  => 'application/x-www-form-urlencoded; charset=UTF-8',
            'Referer'       => 'https://book.lufthansa.com/lh/dyn/air-lh/servicing/cockpit',
            'X-Distil-Ajax' => 'detzqtfsyvdsarwfws',
        ];
        $http2 = $this->http;
        $http2->PostURL(
            "https://book.lufthansa.com/lh/dyn/air-lh/servicing/entryPointParams;jsessionid=" . $jsessionid,
            "COUNTRY_SITE=US&LANGUAGE=GB&SITE=LUFTLUFT",
            $headers2
        );
        $resCheck = $http2->JsonLog(null, 1);

        if ($resCheck && isset($resCheck->entryPointParams)) {
            $filteredBookingList = [];
            $this->logger->info("Parse cancelled itineraries", ['Header' => 3]);

            foreach ($bookingList as $item) {
                $tripType = ArrayVal($item, 'tripType');

                if ($tripType) {
                    $filteredBookingList[] = $item;
                    $locators[] = $item['recordLocator'];

                    continue;
                }
                $formData = $resCheck->entryPointParams . "&WDS_DIRECT_RETRIEVE=false&WDS_REC_LOC={$item['recordLocator']}&WDS_LAST_NAME={$item['retrieveLastName']}&WDS_IS_ADD_BOOKING=false";
                $http2->PostURL("https://book.lufthansa.com/lh/dyn/air-lh/servicing/booking", $formData, $headers2);
                $checkRes = $http2->JsonLog(null, 0);

                if ($checkRes && isset($checkRes->booking, $checkRes->booking->isCanceled)) {
                    $this->logger->info("{$item['recordLocator']} is cancelled", ['Header' => 3]);
                    $r = $this->itinerariesMaster->add()->flight();
                    $r->general()
                        ->confirmation($item['recordLocator'])
                        ->cancelled();
                    $parsedConfs[$item['recordLocator']] = true;
                } else {
                    $this->logger->error("filtering out {$item['recordLocator']} with no trip type");
                }
            }
            $bookingList = $filteredBookingList;
        } else {
            $bookingList = array_filter($bookingList, function ($item) {
                $tripType = ArrayVal($item, 'tripType');

                if ($tripType) {
                    return true;
                }
                $this->logger->error("filtering out {$item['recordLocator']} with no trip type");

                return false;
            });
            $locators = array_map(function ($item) {
                return $item['recordLocator'];
            }, $bookingList);
        }
        $this->logger->debug('filtered locators:');
        $this->logger->debug(var_export($locators, true));

        $this->http->TimeLimit = 500;
        $n = 0;
        $totalBookings = count($bookingList);
        $this->logger->info("Parse main info for itineraries (total: {$totalBookings})", ['Header' => 3]);
//        $parsedBefore = count($this->itinerariesMaster->getItineraries());

        for ($i = 0; $i < $totalBookings; $i++) {
            $book = $bookingList[$i];
            $this->logger->debug("Reservation #{$i}");

            if ($i >= 30) {
                $this->logger->debug("Save {$i} reservations");

                break;
            }
            $locator = ArrayVal($book, 'recordLocator');

            if (isset($parsedConfs[$locator])) {
                $this->logger->info("Skip Itinerary #{$locator}", ['Header' => 3]);
                $this->logger->error('Already parsed');

                continue;
            }
            $lastName = ArrayVal($book, 'retrieveLastName');

            if (!$locator || !$lastName) {
                $this->sendNotification('Missing required information to retrieve an itinerary (recordLocator: "' . $locator . '", retrieveLastName: "' . $lastName . '")');

                continue;
            }
            // skip old its
            $arrivalDate = strtotime(ArrayVal($book, 'arrivalDate'));

            if ($arrivalDate && $arrivalDate < strtotime('-2 days')) {
                $this->logger->info(sprintf('Skipping past itinerary #%s', $locator));

                continue;
            }
            $this->logger->info(sprintf('[%s] Parse Itinerary #%s', $this->currentItin++, $locator), ['Header' => 3]);
            $type = 'FLIGHT'; // no other types so far
            $url = sprintf('https://book.lufthansa.com/lh/dyn/air-lh/servicing/service;jsessionid=%s?COUNTRY_SITE=XX&LANGUAGE=GB&SITE=%s&WDS_EXPAND_ITEM=%s&REC_LOC=%s&DIRECT_RETRIEVE_LASTNAME=%s', $jsessionid, 'LUFTLUFT', $type, $locator, $lastName);
            $this->http->GetURL($url);
//            if ($this->http->ParseForm(null,
//                "//form[starts-with(@action,'https://www.miles-and-more.com/row/en/account/my-bookings.html?')]")
//            ) {
//                $this->sendNotification("check post result // ZM");
//                $this->http->PostForm();
//            }
            if ($this->http->FindSingleNode('//p[contains(text(), "Due to technical difficulties this page cannot be displayed.")]')
                || $this->http->FindPreg("/\"error\"\s*:\s*\[\s*\{\s*\"errorInfo\"\s*:\s*\{\s*\"elementName\"\s*:\s*\"server\",\s*\"errorCode\"\s*:\s*\"msg_\d+\",\s*\"message\"\s*:\s*\"due to technical difficulties this page cannot be displayed./")
            ) {
                //'retry not work'
                if (!$this->tryRetrieveSwiss($locator)) {// checked. parsed if...
                    $this->logger->info("Skip Itinerary #{$locator}", ['Header' => 3]);
                    $this->logger->error("Skipping itinerary: Due to technical difficulties this page cannot be displayed.");
                    $this->currentItin--;
                } else {
                    continue;
                }
            }

            if ($itinError = $this->http->FindSingleNode('//span[contains(text(), "Unfortunately, your request could not be processed. Please start a new session.")]')) {
                // retry not work
                if (!$this->tryRetrieveSwiss($locator)) {
                    $this->logger->info("Skip Itinerary #{$locator}", ['Header' => 3]);
                    $this->logger->error("Skipping itinerary: {$itinError}");
                    $this->currentItin--;
                } else {
                    continue;
                }
            }
            $itinError = $this->http->FindSingleNode('//input[contains(@value, "Unfortunately your booking could not be found")]/@value');

            if ($itinError) {// checked. swiss: Your reservation $locator has been deleted
                $this->errorSwiss = '';

                if (!$this->tryRetrieveSwiss($locator)) {
                    if (!empty($this->errorSwiss) && $this->http->FindPreg("/Your reservation {$locator} has been deleted. \(/i",
                            false, $this->errorSwiss)
                    ) {
                        $f = $this->itinerariesMaster->add()->flight();
                        $f->general()
                            ->confirmation($locator)
                            ->status('deleted')
                            ->cancelled();
                    } else {
                        $this->logger->info("Skip Itinerary #{$locator}", ['Header' => 3]);
                        $this->logger->error("Skipping itinerary: {$itinError}");
                        $this->currentItin--;
                    }
                }
            } else {
                if ($this->wrapperParseItineraryFromJson($form, $parsedConfs, $locator)) {
                    $parsedFlights = $this->getParsedFlightConfs();

                    foreach ($parsedFlights as $locator) {
                        $parsedConfs[$locator] = true;
                    }
                }
            }
            $n++;
        }

        $this->getTime($startTimer);

        return $result;
    }

    private function isLoggedInMAM()
    {
        $this->logger->notice(__METHOD__);
        $jsessionid = ArrayVal($this->State, 'jsessionid-mam');

        if (!$jsessionid) {
            $this->logger->notice('Not logged in');

            return false;
        }
        $headers = [
            'Accept'       => 'application/json, text/plain, */*',
            'Content-Type' => 'application/json',
        ];
        $this->http->RetryCount = 0;
        $this->http->PostURL("https://book.miles-and-more.com/mam/dyn/air/servicing/myBookings;jsessionid={$jsessionid}?OUTPUT_FORMAT=json&LANGUAGE=GB&COUNTRY_SITE=DE&SITE=5AHC5AHC", 'null', $headers);
        $this->http->RetryCount = 2;
        $resp = $this->http->JsonLog(null, 0, true);
        $res = isset($resp['bom']['modelObject']['loggedIn']) && $resp['bom']['modelObject']['loggedIn'] === true;
        $this->logger->info($res ? 'Is logged in' : 'Not logged in');

        return $res;
    }

    private function tryRetrieveSwiss($locator): bool
    {
        $this->logger->notice(__METHOD__);
        $cntParsedBefore = count($this->itinerariesMaster->getItineraries());

        if ($locator && isset($this->name['FirstName'], $this->name['LastName'])) {
            $this->logger->debug("try throw retrieveSwiss");
            $arFields = [
                'FirstName' => $this->name['FirstName'],
                'LastName'  => $this->name['LastName'],
                'ConfNo'    => $locator,
            ];
            $it = [];
            $error = '';
            $this->increaseTimeLimit(120);
            $this->retrieveSwiss($arFields, $it, $error, false);
        }
        $cntParsedAfter = count($this->itinerariesMaster->getItineraries());
        $parsed = $cntParsedAfter - $cntParsedBefore;

        return $parsed > 0;
    }

    private function wrapperParseItineraryFromJson($form, $parsedConfs, $locator = null)
    {
        $this->logger->notice(__METHOD__);
        $this->redirectHelperForm(false);

        if ($itinError = $this->http->FindSingleNode('//input[contains(@value, "Unfortunately your booking could not be found.")]/@value')) {
            $this->logger->error("Skipping itinerary: {$itinError}");

            return [];
        }

        if ($itinError = $this->http->FindSingleNode('//div[@id = "Message" and contains(text(), "org.apache.sling.api.resource.PersistenceException")]')) {
            if (!$this->tryRetrieveSwiss($locator)) {
                $this->logger->error("Skipping itinerary: {$itinError}");
            }

            return [];
        }

        if ($this->http->Response['code'] == 200) {
            // refs #14600
            $this->increaseTimeLimit();
            $this->redirectHelperForm(false);
        }

        if ($itinError = $this->http->FindSingleNode('//input[contains(@value, "Unfortunately your booking could not be found.")]/@value')) {
            $this->logger->error("Skipping itinerary: {$itinError}");
            $this->sendNotification("check skip (1)// ZM");

            return [];
        }

        if (!$itinError && $this->http->FindPreg('/book-and-manage\/flights\.external/', false, $this->http->currentUrl())) {
            $itinError = sprintf('%s redirect', $this->http->currentUrl());
            $this->logger->error("Skipping itinerary: {$itinError}");
            $this->sendNotification("check skip (2)// ZM");

            return [];
        }

        if (
            $this->http->ParseForm(null, "//form[contains(@action, 'miles-and-more.com')]")
            || $this->http->FindSingleNode('//input[@name = "portal" and @value = "MAM"]/@value')
        ) {
            if (!$this->isLoggedInMAM()) {
                $this->loginMAMApi();
            }

            if (!$this->invalidMAMLogin) {
                return $this->parseItinerariesMAM($form, $parsedConfs);
            }

            if (!$this->tryRetrieveSwiss($locator)) {
                $this->logger->info('Skipped Itinerary', ['Header' => 3]);
                $this->currentItin--;
            }

            return true;
        }
        $this->parseFlightJson($parsedConfs);

        return true;
    }

    private function loginMAMApi()
    {
        $this->logger->notice(__METHOD__);
        $this->http->removeCookies();
        $this->http->GetURL('https://www.miles-and-more.com/de/en/static/login.html');
//        if (!$this->sendFingerprintMAM()) {
//            $this->sendNotification('MAM not authorization // MI');
//            return false;
//        }
//        $this->http->GetURL('https://www.miles-and-more.com/de/en/static/login.html');
        if ($this->http->FindSingleNode('//div[@id = "distilIdentificationBlock"]')) {
            $this->http->GetURL($this->http->currentUrl());
        }
        $this->distil(false, __METHOD__);
        $this->distil(false, __METHOD__);
        $authToken = $this->http->FindPreg('/"apiGatewayOAuthTokenAuthorizationBearer"\s*:\s*"(.+?)"/');

        if (!$authToken) {
            $timeout = $this->http->FindPreg('/Operation timed out/', false, $this->http->Response['errorMessage']);

            if (!$timeout) {
                $this->sendNotification('MAM authorization header not found // MI');
            }

            return false;
        }

        $headers = [
            'Accept'          => 'application/json, text/plain, */*',
            'Accept-Encoding' => 'gzip, deflate, br',
            'Origin'          => 'https://www.miles-and-more.com',
            'X-Authorization' => sprintf('Bearer %s', $authToken),
            'X-Logintype'     => 'LHID_PWD',
            'X-Scope'         => 'AUTHENTICATED',
        ];
        $payload = [
            'csrf_token' => '',
            'grant_type' => 'password',
            'identified' => 'true',
            'password'   => $this->AccountFields['Pass'],
            'username'   => str_replace(' ', '', $this->AccountFields['Login']),
        ];
        $this->http->PostURL('https://api.miles-and-more.com/oauth2/token', $payload, $headers);

        if ($this->http->FindPreg('/Invalid captcha response/')) {
            $this->invalidMAMLogin = true;
            $this->logger->error('MAM login error: invalid captcha response');

            return false;
        }

        if (!$this->http->FindPreg('/"message":"Access Token granted."/')) {
            $this->invalidMAMLogin = true;
            $timeout = $this->http->FindPreg('/Operation timed out/', false, $this->http->Response['errorMessage']);

            if (!$timeout && !$this->http->Response['code'] == '456') {
                $this->sendNotification('MAM access token was not granted // MI');
            }

            return false;
        }

        return true;
    }

    private function retrieveSwiss($arFields, &$it, &$error, $logLog = true)
    {
        $this->logger->notice(__METHOD__);

        if (empty($arFields["FirstName"])) {
            return $error;
        }

        $this->http->GetURL(self::SWISS_URL);

        if ($this->http->Response['code'] == 403) {
            sleep(2);
            $this->http->GetURL(self::SWISS_URL);

            if ($this->http->Response['code'] == 403) {
                sleep(2);
                $this->http->GetURL(self::SWISS_URL);
            }
        }
        $this->distil(false, __METHOD__, true);
        $this->distil(false, __METHOD__, true);
        $this->distil(false, __METHOD__, true);

        if ($this->http->FindSingleNode('//div[@id = "distilIdentificationBlock"]')) {
            $this->http->GetURL($this->http->currentUrl());
        }
        $akamai = $this->http->FindNodes("//iframe/@src[contains(.,'challenge')]");

        if (!empty($akamai) && count($akamai) === 2) {
            foreach ($akamai as $url) {
                $this->http->NormalizeURL($url);
                $this->http->GetURL($url);
            }
            $this->http->GetURL(self::SWISS_URL);
            $this->distil(false, __METHOD__, true);
            $this->distil(false, __METHOD__, true);
            $this->distil(false, __METHOD__, true);
        }

        if (!$this->http->ParseForm(null, "//form[contains(@action, '/us/en/Login/PnrLogin')]")) {
            if ($logLog) {
                $this->sendNotification('failed to retrieve itinerary by conf # (swiss) // MI');
            }

            return null;
        }

        $this->http->SetInputValue('FirstName', $arFields["FirstName"]);
        $this->http->SetInputValue('LastName', $arFields["LastName"]);
        $this->http->Form['Locator'] = $arFields["ConfNo"];

        if (!$this->http->PostForm()) {
            return null;
        }
        $this->ParseItinerariesSwiss([], $logLog);

        if (count($this->itinerariesMaster->getItineraries()) === 0) {
            if (!empty($error = $this->http->FindSingleNode("//div[@class='notification-message']/p[@class='is-visuallyhidden']/following-sibling::p[contains(.,'has already been') or contains(.,'has been') or contains(.,'not able to identify the entered name')]"))) {
                $this->logger->error($error);
            }
            $this->errorSwiss = $error; // for its

            return 'Itinerary not found, please make sure you can retrieve your itinerary from <a href="' . self::SWISS_URL . '" target="_blank">this page</a> or <a href="https://www.lufthansa.com/deeplink/cockpit?country=de&language=en" target="_blank">this page</a>';
        }

        return null;
    }

    private function itinerarySelenium()
    {
        $this->logger->notice(__METHOD__);
        $allCookies = array_merge($this->http->GetCookies("www.lufthansa.com"), $this->http->GetCookies("www.lufthansa.com", "/", true));
        $allCookies = array_merge($allCookies, $this->http->GetCookies(".lufthansa.com"), $this->http->GetCookies(".lufthansa.com", "/", true));

        $selenium = clone $this;
        $this->http->brotherBrowser($selenium->http);
        $this->logger->notice("Running Selenium...");
        $selenium->UseSelenium();
        $resolutions = [
            [1152, 864],
            [1280, 720],
            [1280, 768],
            [1280, 800],
            [1360, 768],
            [1920, 1080],
        ];
        $resolution = $resolutions[array_rand($resolutions)];
        $this->DebugInfo = "Resolution: " . implode("x", $resolution);
        $selenium->setScreenResolution($resolution);
        $selenium->useGoogleChrome();
        $selenium->setProxyGoProxies(null, "es", null, null, 'https://www.lufthansa.com/us/en/Homepage'); //todo

        $selenium->seleniumOptions->userAgent = null;

        $request = FingerprintRequest::chrome();
        $request->browserVersionMin = \HttpBrowser::BROWSER_VERSION_MIN;
        $fingerprint = $this->services->get(FingerprintFactory::class)->getOne([$request]);

        if ($fingerprint !== null) {
            $selenium->seleniumOptions->fingerprint = $fingerprint->getFingerprint();
            $selenium->http->setUserAgent($fingerprint->getUseragent());
            $selenium->seleniumOptions->setResolution([$fingerprint->getScreenWidth(), $fingerprint->getScreenHeight()]);
        }

        $selenium->http->saveScreenshots = true;
        // refs #12710
//            if ($this->attempt == 0) {
//                $selenium->useCache();
//            }
        $selenium->http->start();
        $selenium->Start();
        $selenium->driver->manage()->window()->maximize();
        // refs #12710
//        if ($this->attempt == 0) {
//            $selenium->useCache();
//        }

        $selenium->http->GetURL("https://www.lufthansa.com/de/en/a");

        foreach ($allCookies as $key => $value) {
            $selenium->driver->manage()->addCookie(['name' => $key, 'value' => $value, 'domain' => ".lufthansa.com"]);
        }

        return $selenium;
    }

    private function retrieveLufthansaAndSwiss($arFields)
    {
        $this->logger->notice(__METHOD__);
        $message = $this->retrieveSelenium($arFields);

        if ((is_string($message) || $message === false) && $message !== 'is_mam') {
            if (stristr($message, 'Unfortunately your booking')) {
                return $message;
            }

            return null;
        }

//        if ($this->http->FindSingleNode('//input[contains(@value, "Unfortunately your booking could not be found.")]/@value')) {
//            return 'Itinerary not found, please make sure you can retrieve your itinerary from <a href="' . self::SWISS_URL . '" target="_blank">this page</a> or <a href="https://www.lufthansa.com/deeplink/cockpit?country=de&language=en" target="_blank">this page</a>';
//        }

        if ($message === 'is_mam') {
            $this->parseItinerariesMAM();
        } else {
            $this->parseItinerariesJsonV2();
        }

        return null;
    }

    private function retrieveSelenium($arFields)
    {
        $this->logger->notice(__METHOD__);
        $this->logger->debug('[Parse start date: ' . date('Y/m/d H:i:s') . ']');
        $startTimer = $this->getTime();
        $result = false;
        $retry = false;
        $selenium = clone $this;
        $this->http->brotherBrowser($selenium->http);

        try {
            $this->logger->notice("Running Selenium...");
            $selenium->UseSelenium();
            $resolutions = [
                [1152, 864],
                [1280, 720],
                [1280, 768],
                [1280, 800],
                [1360, 768],
                //[1920, 1080],
            ];
            $resolution = $resolutions[array_rand($resolutions)];
            $this->DebugInfo = "Resolution: " . implode("x", $resolution);
            $selenium->setScreenResolution($resolution);
            $selenium->useGoogleChrome();

            $selenium->http->saveScreenshots = true;
            // refs #12710
            if ($this->attempt == 0) {
                $selenium->useCache();
            }
            $selenium->http->start();
            $selenium->Start();

            $result = $this->retrieveSeleniumFormNew($selenium, $arFields);

            if (is_string($result) && $result != 'is_mam') {
                /*$result = $this->retrieveSeleniumForm($selenium, $arFields, 'swiss.com');

                if (is_string($result) || $result === false) {
                    return $result;
                }*/
                return $result;
            }

            $cookies = $selenium->driver->manage()->getCookies();

            foreach ($cookies as $cookie) {
                $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'],
                    $cookie['expiry'] ?? null);
            }
            $this->logger->debug("[Current Selenium URL]: {$selenium->http->currentUrl()}");
        } catch (ScriptTimeoutException $e) {
            $this->logger->error("ScriptTimeoutException exception: " . $e->getMessage());
            // retries
            if (strstr($e->getMessage(), 'timeout: Timed out receiving message from renderer')) {
                $retry = true;
            }
        } catch (WebDriverCurlException $e) {
            $this->logger->error("WebDriverCurlException exception: " . $e->getMessage());
            // retries
            if (strstr($e->getMessage(), 'Curl error thrown for http POST')) {
                $retry = true;
            }
        } catch (StaleElementReferenceException $e) {
            $this->logger->error("StaleElementReferenceException exception: " . $e->getMessage(), ['HtmlEncode' => true]);
            // retries
            if (strstr($e->getMessage(), 'Element not found in the cache')
                || strstr($e->getMessage(), 'The element reference of')) {
                $retry = true;
            }
        } catch (NoSuchDriverException | UnknownServerException | UnexpectedJavascriptException | NoSuchWindowException $e) {
            $this->logger->error("Exception: " . $e->getMessage());
            $retry = true;
        } finally {
            // close Selenium browser
            $selenium->http->cleanup();

            if ($retry && ConfigValue(CONFIG_SITE_STATE) != SITE_STATE_DEBUG) {
                $this->logger->debug("[attempt]: {$this->attempt}");

                throw new CheckRetryNeededException(4, 5);
            }
        }
        $this->getTime($startTimer);

        return $result;
    }

    private function retrieveSeleniumFormNew($selenium, $arFields, $host = 'lufthansa.com')
    {
        $this->logger->notice(__METHOD__);
        $selenium->http->removeCookies();
        $selenium->http->GetURL("https://shop.lufthansa.com/booking/manage-booking/retrieve");

        $cookie = $selenium->waitForElement(WebDriverBy::id("cm-acceptAll"), 10);

        if ($cookie) {
            $cookie->click();
        }

        $bookingCode = $selenium->waitForElement(WebDriverBy::xpath("//input[contains(@id,'dentificationorderId')]"), 5);
        $lastname = $selenium->waitForElement(WebDriverBy::xpath("//input[contains(@id,'dentificationlastName')]"), 0);
        $this->savePageToLogs($selenium);

        $arFields['LastName'] = str_replace('-', '', $arFields['LastName']);
        $this->logger->debug(var_export($arFields, true));

        if (!$bookingCode || !$lastname) {
            return false;
        }

        $bookingCode->sendKeys($arFields['ConfNo']);
        $lastname->sendKeys($arFields['LastName']);
        $button = $selenium->waitForElement(WebDriverBy::xpath("//button[@type='submit' and contains(.,'Continue')]"), 0);
        $this->savePageToLogs($selenium);

        if (!$button) {
            return false;
        }

        $button->click();

        $selenium->waitForElement(WebDriverBy::xpath("//h1/div[contains(text(),'Manage Booking')] | //span[contains(@class,'message-title ng-star-inserted')]"), 20);
        $this->savePageToLogs($selenium);

        /*if (($error = $this->http->FindSingleNode("//span[contains(@class,'message-title ng-star-inserted')]"))
            && !$this->http->FindSingleNode("//h1/div[contains(text(),'Manage Booking')]")) {
            $this->logger->error("error: " . $error);

            return $error;
        }*/
        try {
            $data = $selenium->driver->executeScript("return sessionStorage.getItem('order');");
            $this->http->SetBody($data);
        } catch (Exception $e) {
            $this->logger->error($e);
            $this->sendNotification('check retry exception // MI');
            $selenium->waitForElement(WebDriverBy::xpath("//h1/div[contains(text(),'Manage Booking')] | //span[contains(@class,'message-title ng-star-inserted')]"), 10);
            $data = $selenium->driver->executeScript("return sessionStorage.getItem('order');");
            $this->http->SetBody($data);
        }

        if ($this->http->FindPreg('/"ids":\[\],"entities":\{\}/')) {
            $error = $selenium->waitForElement(WebDriverBy::xpath("//span[contains(@class,'message-title ng-star-inserted')]"), 0);

            if ($error) {
                $this->logger->error("error: " . $error->getText());

                return $error->getText();
            }

            return false;
        }

        return true;
    }

    private function retrieveSeleniumForm($selenium, $arFields, $host = 'lufthansa.com')
    {
        $this->logger->notice(__METHOD__);
        // Needed for reservations
        $selenium->http->GetURL("https://www.{$host}/de/en/login?deeplinkRedirect=true");

        $btnRetrieve = $selenium->waitForElement(WebDriverBy::xpath("//button[span[contains(text(),'Enter booking data')]]"), 7);

        if ($cookie = $selenium->waitForElement(WebDriverBy::id('cm-acceptAll'), 0)) {
            $cookie->click();
        }

        if (!$btnRetrieve) {
            $selenium->http->GetURL("https://www.{$host}/de/en/login?deeplinkRedirect=true");
            $btnRetrieve = $selenium->waitForElement(WebDriverBy::xpath("//button[span[contains(text(),'Enter booking data')]]"), 10);

            if (!$btnRetrieve) {
                $this->logger->error("something went wrong");

                return null;
            }
        }

        $btnRetrieve->click();
        sleep(1);
        $bookingCode = $selenium->waitForElement(WebDriverBy::xpath("//input[@name='loginPNRFormQuery.j_bookingcode']"), 0);
        //$firstname = $selenium->waitForElement(WebDriverBy::xpath("//input[@name='loginPNRFormQuery.j_firstname']"), 0);
        $lastname = $selenium->waitForElement(WebDriverBy::xpath("//input[@name='loginPNRFormQuery.j_lastname']"), 0);
        $button = $selenium->waitForElement(WebDriverBy::xpath("//button[@type='submit' and (contains(.,'Login') or contains(.,'Submit'))]"), 0);
        $this->savePageToLogs($selenium);

        if (!$bookingCode /*|| !$firstname */ || !$lastname || !$button) {
            $this->logger->error('Something went wrong');

            return false;
        }

        $this->increaseTimeLimit();

        $this->logger->debug(var_export($arFields, true));
        $bookingCode->sendKeys($arFields['ConfNo']);
        //$firstname->sendKeys($arFields['FirstName']);
        $lastname->sendKeys($arFields['LastName']);
        $button = $selenium->waitForElement(WebDriverBy::xpath("//button[@type='submit' and (contains(.,'Login') or contains(.,'Submit'))]"), 0);
        $button->click();

        $selenium->waitForElement(WebDriverBy::xpath("//h1/div[contains(text(),'Manage Booking')] | //span[contains(@class,'message') and normalize-space(.) != ''] | //a[contains(@aria-label,'Flight details') and not(contains(@class, 'disable'))] | //span[contains(text(), 'This trip has been canceled!')]"), 20);
        $this->savePageToLogs($selenium);

        if ($error = $this->http->FindSingleNode("//span[contains(@class,'message')]")) {
            $this->logger->error("error: " . $error);

            return $error;
        }

        if ($error = $this->http->FindSingleNode("//span[contains(text(), 'This trip has been canceled!')]")) {
            $flight = $this->itinerariesMaster->add()->flight();
            $flight->general()->confirmation($arFields['ConfNo']);
            $flight->general()->cancelled();

            $this->logger->debug('Parsed Itinerary:');
            $this->logger->debug(var_export($flight->toArray(), true), ['pre' => true]);

            return $error;
        }
        $success = true;

        /*$details = $selenium->waitForElement(WebDriverBy::xpath("//a[contains(@aria-label,'Flight details') and not(contains(@class, 'disable'))]"), 5);
        $this->savePageToLogs($selenium);

        if (!$details && $selenium->waitForElement(WebDriverBy::xpath("//div[contains(text(),'Loading…')]"), 0)) {
            $details = $selenium->waitForElement(WebDriverBy::xpath("//a[contains(@aria-label,'Flight details') and not(contains(@class, 'disable'))] | //span[contains(text(), 'This trip has been canceled!')]"), 30);
            $this->savePageToLogs($selenium);
        }

        if (!$details) {
            $this->logger->debug('details button not found');
            $selenium->driver->executeScript('
                try {
                    document.querySelector("a[aria-label *=\'Flight details\']:not([class *= \'disable\'])").click()
                } catch (e) {}
            ');

            $success = (bool) $selenium->waitForElement(WebDriverBy::xpath("//span[contains(.,'Your Booking:')]"), 3);

            if ($error = $this->http->FindSingleNode("//span[contains(text(), 'This trip has been canceled!')]")) {
                $flight = $this->itinerariesMaster->add()->flight();
                $flight->general()->confirmation($arFields['ConfNo']);
                $flight->general()->cancelled();

                $this->logger->debug('Parsed Itinerary:');
                $this->logger->debug(var_export($flight->toArray(), true), ['pre' => true]);

                return $error;
            }

            if (!$success) {
                $this->logger->error('Skip: not found button detail');

                return false;
            }
        } else {
            $details->click();
        }

        // MAM
        $success = (bool) $selenium->waitForElement(WebDriverBy::xpath("//span[contains(.,'Your Booking:')]"), 7);

        if ($success) {
            $this->savePageToLogs($selenium);

            return true;
        } elseif (isset($this->AccountFields['Login'], $this->AccountFields['Pass'])) {
            $selenium->http->GetURL('https://www.miles-and-more.com/de/en/account/my-bookings/my-bookings.html');

            $username = $selenium->waitForElement(WebDriverBy::id("id-mamLoginStepOne-textfield"), 5);

            if ($username) {
                $this->logger->debug('auth mam // MI');
                $username->sendKeys($this->AccountFields['Login']);
                $btn = $selenium->waitForElement(WebDriverBy::xpath("//form[contains(@class,'travelid-login__form--mamLogin')]//button[contains(.,'Next')]"), 0);
                $btn->click();
                sleep(3);
                $pass = $selenium->waitForElement(WebDriverBy::xpath("//input[@name='mamLoginStepTwoPassword']"), 7);

                if (!$pass) {
                    return false;
                }

                $pass->sendKeys($this->AccountFields['Pass']);
                $btn = $selenium->waitForElement(WebDriverBy::xpath("//form[contains(@class,'travelid-login__form--mamLogin')]//button[contains(.,'Log in')]"), 0);
                $btn->click();
                $selenium->waitForElement(WebDriverBy::xpath("//h1[contains(text(),'My booking summary')]"), 7);
                sleep(2);
                $selenium->http->GetURL('https://api.miles-and-more.com/v1/user/me/sso/ams');
                $success = 'is_mam';
            }
        }

        $this->savePageToLogs($selenium);
        */

        return $success;
    }

    private function ParsePageHistory($startIndex, $startDate)
    {
        $result = [];
        $response = $this->http->JsonLog(null, 3);
        $activities = $response->maminfo->activities ?? $response->milesInfo->activities ?? [];
        $this->logger->debug("Total " . count($activities) . " history items were found");

        foreach ($activities as $activity) {
            $dateStr = $activity->activityDate ?? null;

            if ($dateStr === null && isset($result[$startIndex - 1]['Date'])) {
                $postDate = $result[$startIndex - 1]['Date'];
            } else {
                $postDate = strtotime($dateStr);
            }

            if (isset($startDate) && $postDate < $startDate) {
                $this->logger->notice("break at date {$dateStr} ($postDate)");
                $this->endHistory = true;

                break;
            }// if (isset($startDate) && $postDate < $startDate)
            $result[$startIndex]['Date'] = $postDate;
            $result[$startIndex]['Description'] = $activity->activityDescription;

            if (!empty($activity->promotions)) {
                foreach ($activity->promotions as $promotion) {
                    $result[$startIndex]['Description'] .= ' | ' . $promotion->activityDescription;

                    $activityDescription = $promotion->activityDescription;

                    switch ($activityDescription) {
                        case 'Executive Bonus':
                            if (isset($promotion->statusMilesSign, $promotion->statusMiles)) {
                                $result[$startIndex]['Executive Bonus'] = $promotion->statusMilesSign . $promotion->statusMiles;
                            }

                            break;

                        case 'Status&HONCircle Miles Promotion':
                            if (isset($promotion->statusMilesSign, $promotion->statusMiles)) {
                                $result[$startIndex]['Status&HONCircle Miles'] = $promotion->statusMilesSign . $promotion->statusMiles;
                            }

                            break;
//                        case 'over 3 years old':

                        default:
                            $this->logger->notice("Unknown activity: {$activityDescription}");

                            if (!empty($promotion->amount)) {
                                $this->sendNotification("need to check history // RR");
                            }

                            break;
                    }
                }
            }

            if (isset($activity->marketingPartnerCode, $activity->marketingFlightNumber)) {
                $result[$startIndex]['Description'] .= " | $activity->marketingPartnerCode $activity->marketingFlightNumber";

                if (isset($activity->operatingPartnerCode, $activity->operatingFlightNumber)) {
                    $result[$startIndex]['Description'] .= "/{$activity->operatingPartnerCode} {$activity->operatingFlightNumber}";
                }

                if (isset($activity->serviceClass)) {
                    $result[$startIndex]['Description'] .= " | {$activity->serviceClass}";
                }
            }// if (isset($activity->marketingPartnerCode, $activity->marketingFlightNumber))
            elseif (isset($activity->marketingPartnerCode)) {
                $result[$startIndex]['Description'] .= ' | ' . $activity->marketingPartnerCode;
            }

            foreach ($activity->amounts as $amount) {
                switch ($amount->currency) {
                    case 'AWD':
                        $result[$startIndex]['Award miles'] = $amount->amount;

                        break;

                    case 'STA':
                        $result[$startIndex]['Status miles'] = $amount->amount;
                }
            }

            $startIndex++;
        }// foreach ($activities as $activity)

        return $result;
    }

    private function ParsePageHistoryV2($startIndex, $startDate)
    {
        $result = [];
        $response = $this->http->JsonLog(null, 3);
        $activities = $response->bloomInfo->records ?? [];
        $this->logger->debug("Total " . count($activities) . " history items were found");

        if (count($activities)) {
            $this->sendNotification('check history // MI');
        }
        foreach ($activities as $activity) {
            $dateStr = $activity->date ?? null;

            if ($dateStr === null && isset($result[$startIndex - 1]['Date'])) {
                $postDate = $result[$startIndex - 1]['Date'];
            } else {
                $postDate = strtotime($dateStr);
            }

            if (isset($startDate) && $postDate < $startDate) {
                $this->logger->notice("break at date {$dateStr} ($postDate)");
                $this->endHistory = true;

                break;
            }// if (isset($startDate) && $postDate < $startDate)
            $result[$startIndex]['Date'] = $postDate;
            $result[$startIndex]['Description'] = $activity->description->default;
            if (isset($activity->description->flightInfo))
                $result[$startIndex]['Description'] = ' | ' . $activity->description->flightInfo ?? null;

            foreach ($activity->currencyAmounts as $amount) {
                switch ($amount->currency) {
                    case 'AWD':
                        $result[$startIndex]['Award miles'] = $amount->amount;

                        break;

                    case 'STA':
                        $result[$startIndex]['Status miles'] = $amount->amount;
                }
            }

            $startIndex++;
        }// foreach ($activities as $activity)

        return $result;
    }
}
