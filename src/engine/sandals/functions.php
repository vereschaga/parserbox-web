<?php

class TAccountCheckerSandals extends TAccountChecker
{
    private const REWARDS_PAGE_URL = 'https://www.sandalsselect.com/points/';
    /**
     * @var CaptchaRecognizer
     */
    private $recognizer;

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
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
        $this->http->removeCookies();
        $this->http->GetURL("https://www.sandalsselect.com/");

        if ($this->http->Response['code'] !== 200) {
            return $this->checkErrors();
        }

        $this->http->GetURL("https://www.sandalsselect.com/assets/js/master.js?v=1.2.0");
        $recaptchaKey = $this->http->FindPreg("/recaptcha\",sitekey:\"([^\"]+)\"/");

        if (!$recaptchaKey) {
            return false;
        }

        $responseCaptchaToken = $this->parseReCaptcha($recaptchaKey);

        if ($responseCaptchaToken === false) {
            return false;
        }

        $headers = [
            "Accept"       => "application/json, application/xml, text/plain, text/html, *.*",
            "Content-Type" => "application/x-www-form-urlencoded; charset=utf-8",
        ];

        $this->http->RetryCount = 0;
        $this->http->PostURL('https://www.sandalsselect.com/sbmtRecaptcha/', ['response' => $responseCaptchaToken], $headers);
        $response = $this->http->JsonLog();

        if ($response->status != 'success' || !isset($response->data->token)) {
            return false;
        }

        $data = [
            'csrfToken'      => '',
            'retoken'        => $response->data->token,
            'username'       => $this->AccountFields['Login'],
            'password'       => $this->AccountFields['Pass'],
        ];
        $this->http->PostURL('https://www.sandalsselect.com/sbmtLogin/', $data, $headers);
        $this->http->RetryCount = 2;

        return true;
    }

    public function Login()
    {
        $response = $this->http->JsonLog();

        if (isset($response->data->gnId)) {
            $this->captchaReporting($this->recognizer);

            return $this->loginSuccessful();
        }

        $message = $response->message ?? null;

        if ($message) {
            $this->logger->error("[Error]: {$message}");

            switch ($message) {
                case "We are sorry, but the login information provided does not match any records in our system. Please check and try again.":
                case "We are sorry, but the password provided does not match the login information. Please check and try again.":
                case strstr($message, 'We are sorry, but the credentials provided does not match the login information'):
                    $this->captchaReporting($this->recognizer);

                    throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);

                case "Error validating ReCaptcha!":
                    $this->captchaReporting($this->recognizer, false);

                    throw new CheckRetryNeededException(3, 0, self::CAPTCHA_ERROR_MSG);

                default:
                    $this->DebugInfo = $message;

                    return false;
            }
        }

        $needsChange = $response->data->needsChange ?? null; // password needs change

        if ($needsChange) {
            $this->throwProfileUpdateMessageException();
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        // Total Balance Points
        $this->SetBalance($this->http->FindSingleNode("//span[@class='total-number']"));
        // Name
        $this->SetProperty('Name', beautifulName($this->http->FindSingleNode("//div[@class='acct-info']/h2")));

        $memberSinceText = $this->http->FindSingleNode("//p[contains(text(),'Member Since:')]/strong");

        if ($memberSinceText) {
            // Member since
            $this->SetProperty('MemberSince', DateTime::createFromFormat('F d, Y', $memberSinceText)->getTimestamp());
        }
        // Membership
        $this->SetProperty('Membership', $this->http->FindSingleNode("//p[contains(text(),'Membership #:')]/strong"));
        // Total Paid Nights
        $this->SetProperty('TotalPaidNights', $this->http->FindSingleNode("//p[contains(text(),'Total Paid Nights:')]/strong"));
        // Your Level
        $this->SetProperty('Level', $this->http->FindSingleNode("//p[contains(text(),'Your Level:')]/strong"));
    }

    public function ParseItineraries()
    {
        $this->http->GetURL("https://www.sandalsselect.com/load-stays/");
        $response = $this->http->JsonLog();

        $pastItineraries = $response->data->pastStays ?? null;
        $upcomingItineraries = $response->data->futureStays ?? null;

        $upcomingItinerariesIsPresent = $upcomingItineraries !== null && !empty($response->data->futureStays);
        $pastItinerariesIsPresent = $pastItineraries !== null && !empty($response->data->pastStays);

        $this->logger->debug('Upcoming itineraries is present: ' . (int) $upcomingItinerariesIsPresent);
        $this->logger->debug('Previous itineraries is present: ' . (int) $pastItinerariesIsPresent);

        // check for the no its
        $seemsNoIts = !$upcomingItinerariesIsPresent && !$pastItinerariesIsPresent;
        $this->logger->info('Seems no itineraries: ' . (int) $seemsNoIts);
        $this->logger->info('ParsePastIts: ' . (int) $this->ParsePastIts);

        if (!$upcomingItinerariesIsPresent && !$this->ParsePastIts) {
            $this->itinerariesMaster->setNoItineraries(true);

            return [];
        }

        if ($seemsNoIts && !$this->ParsePastIts) {
            $this->itinerariesMaster->setNoItineraries(true);

            return [];
        }

        if ($seemsNoIts && $this->ParsePastIts && !$pastItinerariesIsPresent) {
            $this->itinerariesMaster->setNoItineraries(true);

            return [];
        }

        if ($upcomingItinerariesIsPresent) {
            foreach ($upcomingItineraries as $node) {
                $this->parseFutureItinerary($node);
            }
        }

        // if ($pastItinerariesIsPresent && $this->ParsePastIts) {
        if ($pastItinerariesIsPresent) {
            foreach ($pastItineraries as $node) {
                $this->parsePastItinerary($node);
            }
        }

        return [];
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);
        $this->http->RetryCount = 0;
        $this->http->GetURL(self::REWARDS_PAGE_URL, [], 20);
        $this->http->RetryCount = 2;

        if ($this->http->FindSingleNode("//div[@class='menu-welcome-message']")) {
            return true;
        }

        return false;
    }

    private function checkErrors()
    {
        $this->logger->notice(__METHOD__);

        if ($this->http->FindSingleNode('//h1[contains(text(), "Service Unavailable - DNS failure")]')) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    private function parseReCaptcha($key)
    {
        $this->logger->notice(__METHOD__);

        if (!$key) {
            return false;
        }

        $this->recognizer = $this->getCaptchaRecognizer();
        $this->recognizer->RecognizeTimeout = 120;
        $parameters = [
            "pageurl" => "https://www.sandalsselect.com/",
            "proxy"   => $this->http->GetProxy(),
        ];

        return $this->recognizeByRuCaptcha($this->recognizer, $key, $parameters);
    }

    private function parsePastItinerary($node)
    {
        $this->logger->notice(__METHOD__);
        $h = $this->itinerariesMaster->createHotel();

        $confNo = $node->bookingNumber;
        $this->logger->info("Parse Itinerary #{$confNo}", ['Header' => 3]);

        $h->general()->confirmation($confNo, 'Booking number');
        $h->general()->traveller(beautifulName($node->guestName), true);

        $h->general()->date2($node->insertDate, null, 'F d, Y');

        $h->hotel()->name($node->resortName);

        $h->program()->earnedAwards($node->pointsGained);

        $h->booked()->checkIn2($node->checkIn, null, 'F d, Y');
        $h->booked()->checkOut2($node->checkOut, null, 'F d, Y');

        $h->price()->total($node->dollarsSpent);
        $h->price()->spentAwards($node->pointsUsed);
        $h->hotel()->noAddress();
    }

    private function parseFutureItinerary($node)
    {
        $this->logger->notice(__METHOD__);
        $h = $this->itinerariesMaster->createHotel();

        $confNo = $node->bookingNumber;
        $this->logger->info("Parse Itinerary #{$confNo}", ['Header' => 3]);

        $h->general()->confirmation($confNo, 'Booking number');
        $h->general()->traveller(beautifulName($node->firstName), true);

        $h->hotel()->name($node->resortName);

        $h->program()->earnedAwards($node->pointsGained);

        $h->price()->spentAwards($node->pointsUsed);

        $h->hotel()->noAddress();

        $h->booked()->checkIn2($node->arrivalDate, null, 'F d, Y');
        $checkIn = DateTime::createFromFormat('F d, Y', $node->arrivalDate);
        $checkOut = $checkIn->add(new DateInterval('P' . $node->nights . 'D'));
        $this->logger->debug("Check Out: " . $checkOut->format('Y-m-d'));
        $h->booked()->checkOut2($checkOut->format('Y-m-d'));
    }
}
