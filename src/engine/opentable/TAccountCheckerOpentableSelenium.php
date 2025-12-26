<?php

use AwardWallet\Common\Selenium\FingerprintFactory;
use AwardWallet\Common\Selenium\FingerprintRequest;
use AwardWallet\Engine\ProxyList;

class TAccountCheckerOpentableSelenium extends TAccountChecker
{
    use ProxyList;
    use SeleniumCheckerHelper;
    private const JSON_REGEXP = '/__INITIAL_STATE__\s*=\s*(.+);/';
    /** @var CaptchaRecognizer */
    private $recognizer;
    private $history = [];

    private $domain = 'com';

    private $regionOptions = [
        ""    => "Select your region",
        "CA"  => "Canada",
        "UK"  => "United Kingdom",
        "USA" => "United States",
    ];

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->useSelenium();
        $this->KeepState = true;
        /*
        $this->useGoogleChrome();
        */

        $this->http->saveScreenshots = true;

        $this->AccountFields['Login2'] = $this->checkRegionSelection($this->AccountFields['Login2']);

        if ($this->AccountFields['Login2'] == 'CA') {
            $this->domain = 'ca';
        } elseif ($this->AccountFields['Login2'] == 'UK') {
            $this->domain = 'co.uk';
            $this->http->SetProxy($this->proxyUK());
        }

        /*
        $request = FingerprintRequest::chrome();
        $request->browserVersionMin = \HttpBrowser::BROWSER_VERSION_MIN - 5;
        $request->platform = (random_int(0, 1)) ? 'MacIntel' : 'Win32';
        $fingerprint = $this->services->get(FingerprintFactory::class)->getOne([$request]);

        if (isset($fingerprint)) {
            $this->http->setUserAgent($fingerprint->getUseragent());
        }
        */
        $this->useFirefox();
        $this->setProxyMount();

        $this->seleniumOptions->fingerprintParams = FingerprintParams::vanillaFirefox();
        $this->seleniumOptions->addHideSeleniumExtension = false;
        $this->setKeepProfile(true);
    }

    public function IsLoggedIn()
    {
        $this->http->RetryCount = 0;

        try {
            $this->http->GetUrl("https://www.opentable.{$this->domain}/my/Profile");
        } catch (Facebook\WebDriver\Exception\UnknownErrorException $e) {
            $this->logger->error("UnknownErrorException: " . $e->getMessage(), ['HtmlEncode' => true]);
        }
        $this->http->RetryCount = 2;

        $this->waitForElement(WebDriverBy::xpath("//header//button[contains(@aria-label,'User account dropdown')]"), 2);
        $this->saveResponse();

        if ($this->loginSuccessful() && $this->http->currentUrl() != "https://www.opentable.{$this->domain}/my/login?ra=mp") {
            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        // Please enter a valid Email.
        if (filter_var($this->AccountFields['Login'], FILTER_VALIDATE_EMAIL) === false) {
            throw new CheckException('Please enter a valid email address.', ACCOUNT_INVALID_PASSWORD);
        }

        $this->http->removeCookies();

        // prevent code spam    // refs #6042
        if ($this->isBackgroundCheck() && !$this->getWaitForOtc()) {
            $this->Cancel();
        }
        $this->http->GetURL("https://www.opentable.{$this->domain}/authenticate/start?isPopup=true&rp=https://www.opentable.{$this->domain}/my/Profile&srs=1");
        $toEmail = $this->waitForElement(WebDriverBy::xpath("//button[contains(text(),'Use email instead')]"), 7);

        if ($toEmail) {
            $toEmail->click();
        }
        $email = $this->waitForElement(WebDriverBy::id('email'), 7);
        $button = $this->waitForElement(WebDriverBy::xpath("//form//button[normalize-space()='Continue']"), 0);

        if (!$email || !$button) {
            if ($this->waitForElement(WebDriverBy::id('emailVerificationCode'), 0)) {
                $this->parseQuestion();

                return false;
            }

            $this->saveResponse();

            if (
                $this->http->FindSingleNode('//h1[contains(text(), "Access Denied")]')
                || $this->http->FindSingleNode('//*[contains(text(), "This site can’t be reached")]', null, true, null, 0)
            ) {
                throw new CheckRetryNeededException();
            }

            return false;
        }

        $email->sendKeys($this->AccountFields['Login']);

        $button->click();

        return true;
    }

    public function Login()
    {
        $twoFa = $this->waitForElement(WebDriverBy::id('emailVerificationCode'), 7);
        $this->saveResponse();

        if ($twoFa) {
            return $this->parseQuestion();
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        $this->logger->debug("[Current URL]: {$this->http->currentUrl()}");
        $state = $this->http->JsonLog($this->http->FindPreg(self::JSON_REGEXP), 3, false, 'vipEligibilityWindowEnd');
        // Name
        $this->SetProperty('Name', beautifulName($this->http->FindPreg("/userProfile\":\{\"firstName\":\"([^\"]+)/") . " " . $this->http->FindPreg("/userProfile\":\{\"firstName\":\"[^\"]+\",\"lastName\":\"([^\"]+)\"/")));
        // Status - vip
        $status = $this->http->FindPreg("/userInfo.isVip\s*=\s*([a-z]+)/ims");
        $this->logger->debug("Status: {$status}");

        if (isset($status)) {
            $this->SetProperty('Status', ($status == 'true') ? 'Vip' : "Member");
        }

        if (!isset($state->diningDashboard)) {
            return;
        }
        // Balance
        $this->SetBalance($state->diningDashboard->points);
        // You are only 2,800 points away from a $50 reward!
        $this->SetProperty("NeededNextReward", $state->diningDashboard->nextReward->requiredPoints ?? null);
        // Expiring balance
        $this->SetProperty("ExpiringBalance", $state->diningDashboard->userExpiringPoints->expiringPoints ?? null);

        if (
            isset($state->diningDashboard->userExpiringPoints->expiringPoints)
            && $state->diningDashboard->userExpiringPoints->expiringPoints > 0
            && $state->diningDashboard->userExpiringPoints->eligibleForExpiration == true
        ) {
            $this->SetExpirationDate(strtotime($state->diningDashboard->userExpiringPoints->expirationDate));
        }

        $this->history = $state->diningDashboard->pastReservations;

        // refs #23698
        $lastActivity = null;

        foreach ($this->history as $pastReservation) {
            if (!$lastActivity || strtotime($pastReservation->dateTime) > $lastActivity) {
                $lastActivity = strtotime($pastReservation->dateTime);
                $this->SetProperty("LastActivity", date("M d, Y", $lastActivity));
            }
        }// foreach ($this->history as $pastReservation)

        if (
            !empty($lastActivity)
            && isset($state->diningDashboard->userExpiringPoints->expiringPoints, $state->diningDashboard->userExpiringPoints->expirationDate)
            && $state->diningDashboard->userExpiringPoints->expiringPoints == 0
        ) {
            $this->SetExpirationDate(strtotime("+12 month", $lastActivity));
        }
    }

    public function ProcessStep($step)
    {
        $this->logger->notice(__METHOD__);

        if ($step == 'Question') {
            $this->saveResponse();

            return $this->parseQuestion();
        }

        $this->saveResponse();

        return true;
    }

    public function ParseItineraries()
    {
        $this->http->FilterHTML = false;

        $result = $links = [];

        $state = $this->http->JsonLog($this->http->FindPreg(self::JSON_REGEXP), 3, false, 'upcomingReservations');
        $noLinkReservations = $state->diningDashboard->upcomingReservations;

        if ($noLinkReservations == []) {
            if ($this->ParsePastIts) {
                $pastItineraries = $this->parsePastItineraries();

                if (!empty($pastItineraries)) {
                    return $pastItineraries;
                }
            } else {
                return $this->noItinerariesArr();
            }
        }

        foreach ($noLinkReservations as $res) {
            $this->parseRestaurantMinimal($res);
        }

        if ($this->ParsePastIts) {
            $result = array_merge($result, $this->parsePastItineraries());
        }

        return $result;
    }

    public function GetHistoryColumns()
    {
        return [
            "Date"       => "PostingDate",
            "Restaurant" => "Description",
            "Points"     => "Miles",
        ];
    }

    public function ParseHistory($startDate = null)
    {
        $result = [];
        $this->http->FilterHTML = false;
        $this->logger->debug('[History start date: ' . (isset($startDate) ? date('Y/m/d H:i:s', $startDate) : 'all') . ']');
        $startTimer = $this->getTime();
        $startIndex = sizeof($result);
        $result = $this->ParseHistoryPage($startIndex, $startDate);
        $this->getTime($startTimer);

        return $result;
    }

    private function checkRegionSelection($region)
    {
        if (!in_array($region, array_flip($this->regionOptions))) {
            $region = 'USA';
        }

        return $region;
    }

    private function parseQuestion()
    {
        $this->logger->notice(__METHOD__);
        $emailVerificationCode = $this->waitForElement(WebDriverBy::id('emailVerificationCode'), 0);
        $question = $this->waitForElement(WebDriverBy::xpath("//p[contains(text(), 'Enter the code to continue.')]"), 3);
        $this->saveResponse();

        if (!$question || !$emailVerificationCode) {
            $this->logger->error("something went wrong");

            if (isset($this->Answers[$question])) {
                unset($this->Answers[$question]);
            }

            return false;
        }

        $question = $question->getText();

        if (!isset($this->Answers[$question])) {
            $this->holdSession();
            $this->AskQuestion($question, null, "Question");

            return false;
        }

        $emailVerificationCode->sendKeys($this->Answers[$question]);
        unset($this->Answers[$question]);
        $this->saveResponse();

        sleep(1);
        $loadingSuccess = $this->waitForElement(WebDriverBy::xpath("
            //header//button[contains(@aria-label,'User account dropdown')]
            | //h1[@data-testid=\"firstName\"]
            | //span[@id = 'emailVerificationCode-error']
        "), 10);

        $this->saveResponse();

        // Something went wrong. Request a new code.
        if ($error = $this->http->FindSingleNode('//span[@id = "emailVerificationCode-error"]')) {
            sleep(1);
            $this->waitForElement(WebDriverBy::xpath("//button[contains(text(),'Resend code')]"), 0)->click();
            sleep(1);
            $this->saveResponse();
            // Please wait a moment before resending a code
            if ($errorTwo = $this->http->FindSingleNode('//span[@id = "emailVerificationCode-error"]')) {
                $this->sendNotification('check question // MI');
                $this->logger->debug($errorTwo);

                if ($errorTwo == 'Please wait a moment before resending a code.') {
                    sleep(10);
                    $this->waitForElement(WebDriverBy::xpath("//button[contains(text(),'Resend code')]"), 0)->click();
                    sleep(1);
                }
                $error = $errorTwo;
            }

            $this->holdSession();
            $this->AskQuestion($question, $error, "Question");

            return false;
        }

        if (!$loadingSuccess) {
            return false;
        }

        return true;
    }

    private function checkErrors()
    {
        $this->logger->notice(__METHOD__);

        return false;
    }

    private function parseRestaurantMinimal(object $res)
    {
        $this->logger->notice(__METHOD__);
        $confNo = $res->confirmationNumber;
        $this->logger->info('Parse Itinerary #' . $confNo, ['Header' => 3]);

        if (empty($confNo) || $confNo == 0 || $confNo == '0') {
            $this->logger->error('Skip: bug itinerary');

            return;
        }
//        $date = $res->ReservationDate;
        $postDate = strtotime(preg_replace('/:\d+$/', '', $res->dateTime));

        // for Address / Phone
        $browser = clone $this;
        $this->http->brotherBrowser($browser->http);
        $browser->http->setMaxRedirects(0);

        $this->increaseTimeLimit();

        try {
            $browser->http->GetURL("https://www.opentable.{$this->domain}/book/view?rid={$res->restaurantId}&confnumber={$confNo}&token={$res->securityToken}");
        } catch (Facebook\WebDriver\Exception\WebDriverException $e) {
            $this->logger->error("WebDriverException: " . $e->getMessage(), ['HtmlEncode' => true]);
        }
        //$this->logger->debug(var_export($browser->http->Response['headers'], true), ['pre' => true]);
        if ($this->http->FindSingleNode("//p[contains(text(), 'requires a credit card number to hold this reservation')]")) {
            $this->logger->error('Skip: update itinerary');

            return;
        }

        if ($browser->http->Response['code'] == 500 || $this->http->FindSingleNode("//h1[contains(text(), 'Well, this is embarrassing.')]")) {
            sleep(3);
            $browser->http->GetURL("https://www.opentable.{$this->domain}/book/view?rid={$res->restaurantId}&confnumber={$confNo}&token={$res->securityToken}");

            if ($this->http->FindSingleNode("//h1[contains(text(), 'Well, this is embarrassing.')]")) {
                $this->logger->error('Skip: Well, this is embarrassing.');

                return;
            }

            if ($browser->http->Response['code'] != 500) {
                $this->sendNotification('success retry // MI');
            }
        }

        if (isset($browser->http->Response['headers']['location'])) {
            $location = $browser->http->Response['headers']['location'];
            $browser->http->setMaxRedirects(5);
            $browser->http->GetURL(strpos($location, 'http') === false ? $this->http->getCurrentScheme() . ":" . $location : $location);
        }
        $r = $this->itinerariesMaster->add()->event();
        $r->setEventType(EVENT_RESTAURANT);
        $r->general()->noConfirmation();

        if (isset($res->reservationState) && stristr($res->reservationState, 'Cancel')) {
            $r->general()
                ->status('Cancelled')
                ->cancelled();
        }
        $r->booked()
            ->start($postDate)
            ->noEnd()
            ->guests($res->partySize);
        $address = $browser->http->FindSingleNode("//span[@itemprop = 'streetAddress']")
            ?? $browser->http->FindNodes("//section[@data-test='map-section']/div/h3/following-sibling::p")
            ?? $browser->http->FindSingleNode("//a[contains(@href,'google.com/maps/')]/ancestor::div[./preceding-sibling::a[contains(@href,'google.com/maps/')]]//a");

        if (!empty($address) && is_array($address)) {
            $address = join(', ', array_filter($address));
        }

        if (empty($address)) {
            $arr = $browser->http->FindNodes('//a[@data-test="phone-number"]/preceding-sibling::p');

            if (!empty($arr)) {
                $address = implode(', ', $arr);
            }
        }

        if (empty($address)) {
            $this->sendNotification("addslashes // MI");
            $addressQuery = "//h3[contains(text(),'" . addslashes($res->restaurantName) . "')]/following-sibling::p";
            $this->logger->debug("Address query: $addressQuery");
            $address = join(', ', array_filter($browser->http->FindNodes($addressQuery)));
        }

        $r->place()
            ->name($res->restaurantName)
            ->address($address)
            ->phone(
                $browser->http->FindSingleNode("//div[span[contains(text(), 'Phone number')]]/following-sibling::div[1][string-length(normalize-space(.)) > 0]")
                ?? $browser->http->FindSingleNode('//a[@data-test="phone-number"]'), false, true);

        if (isset($res->points)) {
            $r->program()->earnedAwards($res->points);
        }

        $this->logger->info('Parsed Itinerary:');
        $this->logger->info(var_export($r->toArray(), true), ['pre' => true]);
    }

    private function parsePastItineraries()
    {
        $this->logger->notice(__METHOD__);
        $this->logger->info("Past Itineraries", ['Header' => 2]);
        $startTimer = $this->getTime();
        $result = [];

        /*
        if (empty($this->history)) {
            $pastIts = $this->getHistoryPage();
        } else {
        */
        $pastIts = $this->history;
//        }

        if (!isset($pastIts)) {
            return $result;
        }

        $this->logger->debug("Total " . count($pastIts) . " history items were found");

        /*
        // An error has occurred
        if (isset($pastIts->Message) && $pastIts->Message == 'An error has occurred.') {
            $this->logger->error($pastIts->Message);

            return $result;
        }// if (isset($response->Message) && $response->Message == 'An error has occurred.')
        */

        $i = 0;
        $cnt = 0;

        foreach ($pastIts as $pastIt) {
            if (!isset($pastIt->dateTime)) {
                $this->logger->error("Something went wrong with date");

                continue;
            }

            if ($pastIt->restaurantId == null) {
                $this->logger->notice("skip not itineraries {$pastIt->restaurantName}");

                continue;
            }
            $i++;

            $this->parseRestaurantMinimal($pastIt);
            $cnt++;

            if ($i >= 50) {
                break;
            }
        }
        $this->getTime($startTimer);
        $this->logger->debug("Total " . $cnt . " past reservations found");

        return $result;
    }

    private function ParseHistoryPage($startIndex, $startDate)
    {
        $this->logger->notice(__METHOD__);
        $result = [];

        $response = $this->history;

        if (isset($response)) {
            $this->logger->debug("Total " . ((is_array($response) || ($response instanceof Countable)) ? count($response) : 0) . " history items were found");

            // An error has occurred
            if (isset($response->Message) && $response->Message === 'An error has occurred.') {
                $this->logger->error($response->Message);

                return $result;
            }// if (isset($response->Message) && $response->Message == 'An error has occurred.')

            if (isset($response->message) && $response->message === 'Bad Gateway') {
                $this->logger->error($response->message);

                return $result;
            }// if (isset($response->message) && $response->message == 'Bad Gateway')

            foreach ($response as $transaction) {
                if (!isset($transaction->dateTime)) {
                    $this->sendNotification("history - Something went wrong with date");

                    continue;
                }

                $postDate = strtotime($transaction->dateTime);

                if (isset($startDate) && $postDate < $startDate) {
                    $this->logger->notice('Skip date ' . date('Y-m-d', $postDate) . "({$postDate})");

                    continue;
                }
                $result[$startIndex]['Date'] = $postDate;
                $result[$startIndex]['Restaurant'] = $transaction->restaurantName;
                $result[$startIndex]['Points'] = $transaction->points;
                $startIndex++;
            }
        }

        return $result;
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);
        // Access is allowed
        if ($this->http->FindNodes("//a[contains(@href, 'logout')]/@href | //a[contains(text(), 'Account Details')]")) {
            return true;
        }

        return false;
    }
}
