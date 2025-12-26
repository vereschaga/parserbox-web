<?php

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\nordic\QuestionAnalyzer;
use AwardWallet\Engine\ProxyList;
use AwardWallet\Engine\WeekTranslate;

class TAccountCheckerNordic extends TAccountChecker
{
    use SeleniumCheckerHelper;
    use ProxyList;

    private const REWARDS_PAGE_URL = "https://www.strawberryhotels.com/my-page/";
    /**
     * @var CaptchaRecognizer
     */
    private $recognizer;
    private HttpBrowser $http2;

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->setHttp2(true);
    }

    public function IsLoggedIn()
    {
        $this->http->RetryCount = 0;
        $this->http->GetURL(self::REWARDS_PAGE_URL, [], 20);
        $this->http->RetryCount = 2;

        if ($this->loginSuccessful()) {
            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->GetURL("https://www.strawberryhotels.com/login/?redirectUrl=/my-page/");

        if (!$this->http->ParseForm(null, "//form[contains(@action, 'login')]", false)) {
            return $this->checkErrors();
        }

//        $this->seleniumAuth();
//
//        return true;

        $captcha = $this->parseCaptcha();

        if ($captcha === false) {
            return false;
        }

        $data = [
            "username" => $this->AccountFields['Login'],
            "password" => $this->AccountFields['Pass'],
            "remember" => true,
            "token"    => $captcha,
        ];
        $headers = [
            "Accept"       => "*/*",
            "Content-Type" => "application/json",
        ];
        $this->http->RetryCount = 0;
        $this->http->PostURL("https://www.strawberryhotels.com/login/", json_encode($data), $headers);
        $this->http->RetryCount = 2;

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);

        if ($message = $this->http->FindSingleNode('
                //p[contains(text(), "We apologise, but something went wrong.")]
                | //p[contains(text(), "The website is currently unavailable.")]
                | //p[contains(text(), "We are sorry to inform you that we are currently experiencing some technical problems. Please try to refresh the page.")]
            ')
        ) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        if (
            $this->http->FindSingleNode('//h1[
                contains(text(), "503 Service Temporarily Unavailable")
                or contains(text(), "502 Bad Gateway")
            ]
            | //h2[contains(text(), "The request could not be satisfied.")]
            ')
        ) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
        $response = $this->http->JsonLog();

        if (isset($response->userId)) {
            $this->http->GetURL("https://www.strawberryhotels.com/my-page/");
        }

        if (/*isset($response->userId) && */ $this->loginSuccessful()) {
            $this->captchaReporting($this->recognizer);

            return true;
        }

        if (isset($response->isMfa, $response->sessionToken) && $response->isMfa == true) {
            $this->State['sessionToken'] = $response->sessionToken;
            $question = "For your safety we have implemented a two-step verification. A verification code that is valid for 15 minutes has been sent to your email address.";

            if (!QuestionAnalyzer::isOtcQuestion($question)) {
                $this->sendNotification("need to fix QuestionAnalyzer");
            }

            $this->AskQuestion($question, null, "Question");

            return false;
        }

        if ($message = $this->http->FindSingleNode('//div[contains(@class, "sds-c-banner--error")]/p')) {
            $this->logger->error($message);

            // captcha issue
            if (
                strstr($message, 'Oops! Something went wrong.')
            ) {
                $this->captchaReporting($this->recognizer, false);

                throw new CheckRetryNeededException(2, 0, self::CAPTCHA_ERROR_MSG);
            }
            $this->captchaReporting($this->recognizer);

            if (
                strstr($message, 'The details provided are incorrect')
            ) {
//                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            $this->DebugInfo = $message;

            return false;
        }// if ($message = $this->http->FindSingleNode('//div[contains(@class, "sds-c-banner--error")]/p'))

        if ($this->http->Response['code'] == 401 && $this->http->FindPreg("/^Unauthorized$/")) {
            $this->captchaReporting($this->recognizer, false);
            throw new CheckRetryNeededException(2, 0, "The details provided are incorrect", ACCOUNT_INVALID_PASSWORD);
        }

        return $this->checkErrors();
    }

    public function ProcessStep($step)
    {
        $answer = $this->Answers[$this->Question];
        unset($this->Answers[$this->Question]);

        if (strlen($answer) != 6) {
            $this->AskQuestion($this->Question, 'Incorrect or expired verification code.', "Question");

            return false;
        }

        $headers = [
            "Accept"       => "*/*",
            "Content-Type" => "application/json",
        ];
        $data = [
            "username"     => $this->AccountFields['Login'],
            "first"        => $answer[0],
            "second"       => $answer[1],
            "third"        => $answer[2],
            "fourth"       => $answer[3],
            "fifth"        => $answer[4],
            "sixth"        => $answer[5],
            "sessionToken" => $this->State['sessionToken'],
        ];
        $this->http->RetryCount = 0;
        $this->http->PostURL('https://www.strawberryhotels.com/login/complete', json_encode($data), $headers);
        $this->http->RetryCount = 2;

        if ($this->http->FindPreg("/^Unprocessable Entity/")) {
            $this->AskQuestion($this->Question, 'Incorrect or expired verification code.', "Question");

            return false;
        }

        $response = $this->http->JsonLog();

        if (isset($response->userId)) {
            $this->http->GetURL("https://www.strawberryhotels.com/my-page/");
        }

        return true;
    }

    // refs #16033
    public function Parse()
    {
        if ($this->http->Response['code'] == 500 && $this->http->FindSingleNode("//p[contains(text(),'We apologise, but something went wrong.')]")) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }
        // Balance - Spenn
        $this->SetBalance($this->http->FindSingleNode('//div[contains(@class, "-spenn")]/p[1]'));
        // Name
        $this->SetProperty("Name", beautifulName($this->http->FindSingleNode('//h1[contains(text(), "Good ") or contains(text(), "Hello,")]', null, true, "/,\s*([^!]+)/")));
        // Membership number
        $this->SetProperty("MemberNumber", $this->http->FindSingleNode('//button[contains(@aria-label, "Membership number:")]//span'));
        // Your membership level
        $this->SetProperty("Status", beautifulName($this->http->FindSingleNode('//a[contains(@href, "my-membership")]/p')));
        // NIGHTS EARNED
        $this->SetProperty("NumberOfNights", $this->http->FindSingleNode('//div[contains(@aria-label, "qualifying nights")]/@aria-label', null, true, "/collected (.+) qualifying night/"));

        $this->AddSubAccount([
            'Code'           => 'nordicBonusPoints',
            'DisplayName'    => "Bonus points",
            'Balance'        => $this->http->FindSingleNode('//a[contains(@href, "use-bonus-points")]/div/div'),
        ]);

        $this->http->GetURL("https://www.strawberryhotels.com/my-page/transactions/");
        // Setup Expiration Date and Expiring Points /*checked*/
        $expNodes = $this->http->XPath->query('//h2[contains(text(), "Expiring points")]/following-sibling::div');
        $this->logger->debug("Total {$expNodes->length} exp nodes were found");
        $noExpBalance = 0;

        foreach ($expNodes as $expNode) {
            $expDate = $this->http->FindSingleNode('p[1]', $expNode);
            $expBalance = $this->http->FindSingleNode('p[2]', $expNode, true, "/(.+)\s+point/");
            $this->logger->debug('Exp Date: ' . $expDate);
            $this->logger->debug('Exp Balance: ' . $expBalance);

            if (
                (
                    !isset($exp)
                    || strtotime($expDate) < $exp
                )
                && $expBalance > 0
            ) {
                $exp = strtotime($expDate);
                $this->SetExpirationDate($exp);
                // Points to expire
                $this->SetProperty("ExpiringBalance", $expBalance);
            } elseif ($expBalance == 0) {
                $noExpBalance++;
            }
        }// foreach ($expNodes as $expNode)

        if (!isset($this->Properties['PointsToExpire']) && $noExpBalance == 4) {
            $this->ClearExpirationDate();
        }

        // AccountID: 1527963
        if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR) {
            $this->http->GetURL("https://www.strawberryhotels.com/membership/");
            // Join Nordic Choice Club
            if ($this->http->FindSingleNode("//p[contains(text(), 'Join the club and get some great benefits. We already have more than 1.9 million members in the Nordic Choice Club!')]")) {
                $this->SetWarning(self::NOT_MEMBER_MSG);
            }
        }// if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR)
    }

    public function ParseItineraries()
    {
        $result = [];
        $this->http->GetURL("https://www.strawberryhotels.com/my-bookings/");

        if (
            $this->http->FindSingleNode("//h3[contains(text(), 'No bookings found')]")
            && !$this->ParsePastIts
        ) {
            $this->itinerariesMaster->setNoItineraries(true);

            return [];
        }

        $this->parseItinerariesPages("upcoming", "//div[contains(@class,'bookings-list')]/a[starts-with(@href, '/my-bookings/')]");

        // Past reservations
        if ($this->ParsePastIts) {
            $this->http->GetURL("https://www.strawberryhotels.com/my-bookings/?status=previous");
            $this->parseItinerariesPages("previous", '//div[contains(@class, "bookings-list")]/a');
        }

        // Cancelled itineraries
        $this->logger->info('Parse "cancelled" itineraries', ['Header' => 2]);
        $this->http->GetURL("https://www.strawberryhotels.com/my-bookings/?status=cancelled");
        $cancelledIts = $this->http->XPath->query('//div[contains(@class, "bookings-list")]/a');
        $this->logger->debug("Total {$cancelledIts->length} cancelled itineraries were found");

        foreach ($cancelledIts as $cancelledIt) {
            $confNo = $this->http->FindSingleNode("@href", $cancelledIt, false, "/\/my-bookings\/([\w\d]+)\//");
            $this->logger->info('Parse "Cancelled" Itinerary #' . $confNo, ['Header' => 3]);

            $h = $this->itinerariesMaster->add()->hotel();
            $h->general()
                ->confirmation($confNo, 'Booking number', true)
                ->cancelled();

            $h->hotel()
                ->name($this->http->FindSingleNode('.//h3', $cancelledIt))
            ;

            $checkIn = $this->http->FindSingleNode('.//h3/following-sibling::div[1]', $cancelledIt, false, '/([^\-]+)\s-/');
            $checkInDay = $this->http->FindPreg("/([^,]+)/", false, $checkIn);
            $checkIn = $this->http->FindPreg("/,(.+)/", false, $checkIn);
            $checkInWeekNum = WeekTranslate::number1($checkInDay);
            $checkInDate = EmailDateHelper::parseDateUsingWeekDay($checkIn, $checkInWeekNum);
            $this->logger->debug("[checkInDate]: checkInDay: {$checkInDay} / checkIn: {$checkIn} / checkInWeekNum: {$checkInWeekNum} / checkInDate: {$checkInDate}");

            $checkOut = $this->http->FindSingleNode('.//h3/following-sibling::div[1]', $cancelledIt, false, '/-\s(.+)/');
            $checkOutDay = $this->http->FindPreg("/([^,]+)/", false, $checkOut);
            $checkOut = $this->http->FindPreg("/,(.+)/", false, $checkOut);
            $checkOutWeekNum = WeekTranslate::number1($checkOutDay);
            $checkOutDate = EmailDateHelper::parseDateUsingWeekDay($checkOut, $checkOutWeekNum);
            $this->logger->debug("[checkOutDate]: checkOutDay: {$checkOutDay} / checkOut: {$checkOut} / checkOutWeekNum: {$checkOutWeekNum} / checkOutDate: {$checkOutDate}");

            $h->booked()
                ->checkIn($checkInDate)
                ->checkOut($checkOutDate)
            ;

            $this->logger->debug('Parsed Cancelled Itinerary:');
            $this->logger->debug(var_export($h->toArray(), true), ['pre' => true]);
        }

        return $result;
    }

    public function ParseItinerary($booking)
    {
        $this->logger->notice(__METHOD__);

        if (
            ($this->http2->FindSingleNode("//p[contains(text(), 'We apologise, but something went wrong.')]")
            && $this->http2->Response['code'] == 500)
            || $this->http2->Response['code'] == 404
        ) {
            // TODO: we should parse main info!
            /*
            $this->logger->error("skip itinerary without details, provider bug");

            return;
            */
        }

        $confNo = $this->http2->FindSingleNode('//div[contains(., "Booking number")]/following-sibling::div/text()[last()]');

        $this->logger->info('Parse Itinerary #' . $confNo, ['Header' => 3]);
        $h = $this->itinerariesMaster->add()->hotel();

        if ($total = $this->http2->FindSingleNode('//div[p[contains(., "Price")]]/following-sibling::div/text()[1]')) {
            if (strstr($total, 'Bonuspoeng') || strstr($total, 'Poäng') || strstr($total, 'Points')) {
                $h->price()->spentAwards($this->http2->FindPreg('/([\d,.]+\s*(?:Poäng|Bonuspoeng|Points))/', false, $total), false, true);
            } else {
                $h->price()
                    ->total(PriceHelper::cost($this->http2->FindPreg('/([\d,.]+)\s*[A-Z]{3}/', false, $total)))
                    ->currency($this->http2->FindPreg('/\s*([A-Z]{3})/', false, $total));
            }
        }

        $travelers = array_merge($this->http2->FindNodes('//div/p[contains(text(), "night")]'));
        $travelers = array_unique(array_map(function ($item) {
            return beautifulName($item);
        }, $travelers));

        $cancellation = $this->http2->FindSingleNode('//div[contains(text(), "Cancellation")]/following-sibling::div'); // todo: not found
        if ($confNo == 'TRIMBLE BRUKERMØTE')
            $h->general()->noConfirmation();
        else
            $h->general()
                ->confirmation($confNo, "Booking number", true);

        $h->general()
            ->travellers($travelers, true)
            ->cancellation($cancellation, true, true);

        if (!empty($confNos)) {
            foreach ($confNos as $c) {
                $h->general()->confirmation($c, "Booking number");
            }
        }

        $countOfAdults = 0;
        $countOfKids = 0;
        $countOfRooms = 0;

        if ($line = $this->http2->FindSingleNode('//div/p[contains(text(), "night")]')) {
            if ($num = $this->http2->FindPreg('/(\d+) adult/i', false, $line)) {
                $countOfAdults = intval($num);
            }

            if ($num = $this->http2->FindPreg('/(\d+) (?:kid|child)/s', false, $line)) {
                $countOfKids = intval($num);
            }
        }

        $checkInDay = $this->http2->FindSingleNode('//div[contains(., "Check-in")]/following-sibling::div', null, true, "/([^,]+)/");
        $checkIn = $this->http2->FindSingleNode('//div[contains(., "Check-in")]/following-sibling::div', null, true, "/\,(.+)/");
        $checkInWeekNum = WeekTranslate::number1($checkInDay);
        $checkInDate = EmailDateHelper::parseDateUsingWeekDay($checkInDay, $checkInWeekNum);
        $this->logger->debug("[checkInDate]: checkIn: {$checkIn} / checkInDay: {$checkInDay} / checkInWeekNum: {$checkInWeekNum} / checkInDate: {$checkInDate}");
        // 11 Sept 3:00pm
        $checkIn = preg_replace("#^\s*(\d+ \w{3,5})#", "$1,", preg_replace('/12 noon/i', '12am', $checkIn));
        $this->logger->debug("[checkIn]: {$checkIn}");

        $checkOutDay = $this->http2->FindSingleNode('//div[contains(., "Check-out")]/following-sibling::div', null, true, "/([^,]+)/");
        $checkOut = $this->http2->FindSingleNode('//div[contains(., "Check-out")]/following-sibling::div', null, true, "/\,(.+)/");
        $checkOutWeekNum = WeekTranslate::number1($checkOutDay);
        $checkOutDate = EmailDateHelper::parseDateUsingWeekDay($checkOutDay, $checkOutWeekNum);
        $this->logger->debug("[checkOutDate]: checkOut: {$checkOut} / checkOutDay: {$checkOutDay} / checkOutWeekNum: {$checkOutWeekNum} / checkOutDate: {$checkOutDate}");
        // 12 Sept 12am
        $checkOut = preg_replace("#^\s*(\d+ \w{3,5})#", "$1,", preg_replace('/12 noon/i', '12am', $checkOut));
        $this->logger->debug("[checkOut]: {$checkOut}");

        /*$checkInDate = strtotime($checkIn, $checkInDate);
        $checkOutDate = strtotime($checkOut, $checkOutDate);
        if ($checkOutDate < $checkInDate) {*/
            $checkInDate = strtotime($booking->hotel->checkIn->time, strtotime($this->http->FindPreg('/(\d+-\d+-\d+T\d+:\d+)/', false, $booking->arrivalDate)));
            $checkOutDate = strtotime($booking->hotel->checkOut->time, strtotime($this->http->FindPreg('/(\d+-\d+-\d+T\d+:\d+)/', false, $booking->departureDate)));
//        }

        $h->booked()
            ->checkIn($checkInDate)
            ->checkOut($checkOutDate)
            ->guests($countOfAdults)
            ->kids($countOfKids)
            ->rooms($countOfRooms)
        ;

        $deadline = $this->http2->FindPreg("/until ([\d.]+) on the scheduled/i", false, $cancellation); // todo

        if ($deadline) {
            $this->logger->debug($deadline);
            $h->booked()->deadlineRelative('0 days', $deadline);
            $this->logger->debug($deadline);
        }

        $h->hotel()
            ->name($this->http2->FindSingleNode('//h3[@class = "sds-c-info-card__heading"]'))
            ->phone($this->http2->FindSingleNode('//div[contains(., "Contact")]/following-sibling::div/a[@title="Call us"]'), true, true)
            ->fax($this->http2->FindSingleNode('//div[contains(., "Contact")]/following-sibling::div/a[@title="Fax"]'), true, true) //todo
            ->address(implode(', ', $this->http2->FindNodes('//div[contains(., "Address")]/following-sibling::div')))
        ;

        $r = $h->addRoom();
        $description = $this->http2->FindSingleNode('//div[p[contains(text(), "night")]]/preceding-sibling::div');
        $r->setType($description);

        $this->logger->debug('Parsed Itinerary:');
        $this->logger->debug(var_export($h->toArray(), true), ['pre' => true]);
    }

    protected function parseCaptcha()
    {
        $this->logger->notice(__METHOD__);
        $key = "6LeXQwIaAAAAAOxl4IY4EkGJm3fkyHuIEXOFzL25";
        $this->logger->debug("data-sitekey: {$key}");

        if (!$key) {
            return false;
        }

        $postData = [
            "type"         => "RecaptchaV3TaskProxyless",
            "websiteURL"   => $this->http->currentUrl(),
            "websiteKey"   => $key,
            "isInvisible"   => true,
            "minScore"     => 0.7,
            "pageAction"   => "contact_form",
            "isEnterprise"   => true,
        ];
        $this->recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_ANTIGATE_API_V2);
        $this->recognizer->RecognizeTimeout = 120;

        return $this->recognizeAntiCaptcha($this->recognizer, $postData);

        $this->recognizer = $this->getCaptchaRecognizer();
        $this->recognizer->RecognizeTimeout = 30;
        $parameters = [
            "pageurl"   => $this->http->currentUrl(),
            "proxy"     => $this->http->GetProxy(),
            "invisible" => 1,
//            "version"   => "enterprise",
            "enterprise"   => 1,
            "action"    => "contact_form",
            "min_score" => 0.7,
        ];

        return $this->recognizeByRuCaptcha($this->recognizer, $key, $parameters);
    }

    private function parseItinerariesPages($type, $xpath)
    {
        $this->logger->notice(__METHOD__);
        $page = 1;

        do {
            $this->logger->info('Parse "' . $type . '" itineraries Page #' . $page, ['Header' => 2]);

            if ($page > 1 && isset($nextPage)) {
                $this->sendNotification("debug page // MI");
                $this->http->NormalizeURL($nextPage);
                $this->http->GetURL($nextPage);
            }

            $bookings = $this->http->FindPreg('#\{"statusFilter":"'.$type.'","page":\d+,"filteredBookingsPagination":\{"pageCount":\d+,"filteredBookings":(\[.+?\])},"hotelsList":#');
            if (!$bookings) {
                return;
            }
            $bookings = $this->http->JsonLog($bookings);

            //$links = $this->http->XPath->query($xpath);
            $this->logger->debug("Total " . count($bookings) . " {$type} itineraries were found");
            $this->http2 = clone $this->http;

            foreach ($bookings as $booking) {
                $this->increaseTimeLimit();
                $this->http2->FilterHTML = false;
                $this->http2->RetryCount = 0;
                $this->http2->GetURL($url = "https://www.strawberryhotels.com/my-bookings/{$booking->bookingId}/{$booking->hash}/");

                // It helps
                if (in_array($this->http2->Response['code'], [500])) {
                    sleep(5);
                    $this->http2->GetURL($url);

                    if (in_array($this->http2->Response['code'], [500])) {
                        sleep(5);
                        $this->http2->GetURL($url);
                    }
                }
                $this->http2->RetryCount = 2;
                if (in_array($this->http2->Response['code'], [404, 500])) {
                    $this->ParseItineraryMinimal($url, $booking);
                } else {
                    $this->ParseItinerary($booking);
                }
                $page++;
            }
        } while (
            $page < 30
            && ($nextPage = $this->http->FindSingleNode("//a[contains(@class, '__page-number') and contains(text(), '{$page}')]/@href"))
        );
    }

    public function ParseItineraryMinimal($url, $booking)
    {
        $this->logger->notice(__METHOD__);
        // https://www.strawberryhotels.com/my-bookings/1132SERVICE48944/e00c72095e4ee50ac8a89329ddbdf806
        // https://www.strawberryhotels.com/my-bookings/1097R276235/5f0f3ff8aa6ae5459c82300d44cfbfa3
        if (empty($booking)) {
            return false;
        }
        $address = "{$booking->hotel->address->streetAddress}, {$booking->hotel->address->postalCode} {$booking->hotel->address->city}";

//        $json = $this->http->FindPreg('#\{("bookingId":"'.$bookingId.'",.+?),"isDayBooking":#');
//        $this->logger->debug('{'.$json.'}');
//        $json = $this->http->JsonLog('{'.$json.'}');

        $this->logger->info('Parse Minimal Itinerary #' . $booking->externalBookingId ?? '-', ['Header' => 3]);
        $h = $this->itinerariesMaster->add()->hotel();
        if ($booking->externalBookingId) {
            $h->general()->confirmation($booking->externalBookingId);
        } elseif ($booking->externalBookingId === null) {
            $h->general()->noConfirmation();
        }
        $h->general()->status($booking->status);

        if (isset($booking->primaryGuest)) {
            $h->general()->traveller("{$booking->primaryGuest->firstName} {$booking->primaryGuest->lastName}");
        }

        $h->hotel()->name($booking->hotel->name);
        $h->hotel()->address($address);
        $h->booked()->checkIn2($booking->arrivalDate);
        $h->booked()->checkOut2($booking->departureDate);

        $this->logger->debug('Parsed Itinerary:');
        $this->logger->debug(var_export($h->toArray(), true), ['pre' => true]);

        return true;
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);

        if ($this->http->FindNodes('//span[contains(text(), "Sign out")]')) {
            return true;
        }

        return false;
    }

    private function seleniumAuth()
    {
        $this->logger->notice(__METHOD__);

        $selenium = clone $this;
        $retry = false;
        $this->http->brotherBrowser($selenium->http);

        try {
            $this->logger->notice("Running Selenium...");
            $selenium->UseSelenium();

            $selenium->useGoogleChrome(SeleniumFinderRequest::CHROME_99);
            $selenium->seleniumOptions->addHideSeleniumExtension = false;
            $selenium->seleniumOptions->userAgent = null;
            $selenium->usePacFile(false);

            $selenium->http->start();
            $selenium->Start();
            $selenium->http->saveScreenshots = true;

            $selenium->http->removeCookies();
            $selenium->http->GetURL("https://www.strawberryhotels.com/login/?redirectUrl=/my-page/");

            $selenium->waitForElement(WebDriverBy::xpath("//input[@value = 'Verify you are human'] | //div[@class = 'hcaptcha-box']/iframe | //input[@name = 'username'] | //button[contains(text(), 'Sign in')]"), 100);
            $this->savePageToLogs($selenium);

            if ($verify = $selenium->waitForElement(WebDriverBy::xpath("//input[@value = 'Verify you are human']"), 0)) {
                $verify->click();
            }

            if ($iframe = $selenium->waitForElement(WebDriverBy::xpath("//div[@class = 'hcaptcha-box']/iframe"), 5)) {
                $selenium->driver->switchTo()->frame($iframe);

                $this->savePageToLogs($selenium);

                if ($captcha = $selenium->waitForElement(WebDriverBy::xpath("//div[@class = 'ctp-checkbox-container']/label"), 10)) {
                    $this->savePageToLogs($selenium);
                    $captcha->click();
                    $this->logger->debug("delay -> 15 sec");
                    $this->savePageToLogs($selenium);
                    sleep(15);

                    $selenium->driver->switchTo()->defaultContent();
                    $this->savePageToLogs($selenium);
                }
            }

            $loginInput = $selenium->waitForElement(WebDriverBy::xpath('//input[@name = "username"]'), 10);
            $passwordInput = $selenium->waitForElement(WebDriverBy::xpath('//input[@name = "password"]'), 0);
            $button = $selenium->waitForElement(WebDriverBy::xpath('//div[@class = "submit-element"]/button[contains(text(), "Sign in")]'), 0);
            $this->savePageToLogs($selenium);

            if (!$loginInput || !$passwordInput || !$button) {
                return false;
            }

            if ($allowCookies = $selenium->waitForElement(WebDriverBy::xpath('//button[@id = "CybotCookiebotDialogBodyLevelButtonLevelOptinAllowAll"]'), 0)) {
                $allowCookies->click();
                sleep(2);
                $this->savePageToLogs($selenium);
            }

            $selenium->driver->executeScript('let remmemberMe = document.querySelector(\'input[name = "remember"]\'); if (remmemberMe != null) remmemberMe.checked = true;');

            $loginInput->click();
            $loginInput->sendKeys($this->AccountFields['Login']);
            $loginInput->click();
            $passwordInput->sendKeys($this->AccountFields['Pass']);
            $this->savePageToLogs($selenium);

            $this->logger->debug("click by btn");
            $button->click();

            $selenium->waitForElement(WebDriverBy::xpath('//span[contains(text(), "Sign out")] | //div[contains(@class, "sds-c-banner--error")]/p'), 10);
            $this->savePageToLogs($selenium);

            $cookies = $selenium->driver->manage()->getCookies();

            foreach ($cookies as $cookie) {
                $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
            }

            $this->savePageToLogs($selenium);
            $this->logger->debug("[Current URL]: {$selenium->http->currentUrl()}");
        } catch (Facebook\WebDriver\Exception\UnknownErrorException $e) {
            $this->logger->error("Exception: " . $e->getMessage());
            $retry = true;
        } catch (ScriptTimeoutException $e) {
            $this->logger->error("ScriptTimeoutException exception: " . $e->getMessage());
            $this->DebugInfo = "ScriptTimeoutException";
            // retries
            if (strstr($e->getMessage(), 'timeout: Timed out receiving message from renderer')) {
                $retry = true;
            }
        }// catch (ScriptTimeoutException $e)
        finally {
            // close Selenium browser
            $selenium->http->cleanup();
            // retries
            if ($retry && ConfigValue(CONFIG_SITE_STATE) != SITE_STATE_DEBUG) {
                throw new CheckRetryNeededException(3);
            }
        }

        return true;
    }
}
