<?php

use AwardWallet\Engine\ProxyList;

class TAccountCheckerKaligo extends TAccountChecker
{
    use ProxyList;
    /**
     * @var CaptchaRecognizer
     */
    private $recognizer;

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->SetProxy($this->proxyReCaptcha());
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
        $this->http->GetURL('https://www.kaligo.com/tml/modals/login_modal');
        $token = $this->http->getCookieByName('XSRF-TOKEN');

        if (is_null($token) && !$this->http->FindSingleNode('//div[contains(@id, "login-signup")]')) {
            return false;
        }
        // Captcha key
        $key = $this->parseCaptcha();

        if (!$key) {
            return false;
        }

        $data = [
            'user' => [
                'email'       => $this->AccountFields['Login'],
                'password'    => $this->AccountFields['Pass'],
                'remember_me' => '1',
            ],
            'recaptcha_response' => $key,
        ];
        $headers = [
            'APP-VERSION'     => '2.2.0',
            "Accept"          => "application/json, text/plain, */*",
            'Content-Type'    => 'application/json',
            'X-XSRF-TOKEN'    => urldecode($token),
        ];
        $this->http->RetryCount = 0;
        $this->http->PostURL("https://www.kaligo.com/sign_in", json_encode($data), $headers);
        $this->http->RetryCount = 2;

        return true;
    }

    public function Login()
    {
        $response = $this->http->JsonLog(null, 2, true);

        if (isset($response['email'])) {
            $this->captchaReporting($this->recognizer);

            return $this->loginSuccessful();
        }

        $message = $response['errors'][0] ?? null;

        // invalid credentials
        if ($message == 'Incorrect login credentials') {
            $this->captchaReporting($this->recognizer);

            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        $json = $this->http->JsonLog(null, 0, true);
        // Name
        $this->SetProperty('Name', beautifulName($json['first_name'] . ' ' . $json['last_name']));

        $this->http->GetURL('https://www.kaligo.com/api/user/referrals');
        $json = $this->http->JsonLog(null, 0, true);
        // Balance - Total claimed: ... miles / points
        $this->SetBalance($json['claimed_points'] ?? null);
    }

    public function ParseItineraries()
    {
        $this->logger->notice(__METHOD__);
        $this->http->GetURL('https://www.kaligo.com/api/bookings');

        if ($this->http->FindPreg("/^\[\]$/")) {
            return $this->itinerariesMaster->setNoItineraries(true);
        }
        $response = $this->http->JsonLog(null, 2);

        foreach ($response as $item) {
            if (strtotime($item->checkInDate) > time()) {
                $this->sendNotification("Future itinerary // MI");
            }

            if (!$this->ParsePastIts && strtotime($item->checkInDate) < time()) {
                $this->logger->debug("Skip past itinerary: $item->itinerary_id");

                continue;
            }
            $this->parseItinerary($item);
        }

        return [];
    }

    protected function parseItinerary($item)
    {
        $this->logger->notice(__METHOD__);
        $this->logger->info("Parse Hotel #{$item->itinerary_id}", ['Header' => 3]);
        $h = $this->itinerariesMaster->createHotel();
        $h->ota()->confirmation($item->itinerary_id, 'Itinerary ID');
        $h->general()->noConfirmation();
        $h->general()->date2($item->created_at);
        $h->general()->status(beautifulName($item->status_description));

        if ($item->cancelled === true) {
            $h->general()->cancelled();
        }
        $h->hotel()
            ->name($item->hotel_name)
            ->address($item->hotel_address)
        ;

        $h->booked()
            ->checkIn2("$item->checkInDate")
            ->checkOut2("$item->checkOutDate")
        ;

        $h->general()->traveller("$item->first_name $item->last_name");
        $h->booked()->guests($item->numOfGuests);
        $h->booked()->rooms($item->numOfRooms);

        $r = $h->addRoom();
        $r->setType($item->roomType);
        $h->price()
            ->total($item->converted_amount)
            ->currency($item->currency)
        ;
        $h->program()->earnedAwards($item->base_points + $item->bonus_points);
        $this->logger->debug('Parsed Itinerary:');
        $this->logger->debug(var_export($h->toArray(), true), ['pre' => true]);
    }

    protected function parseCaptcha()
    {
        $this->logger->notice(__METHOD__);
        $key = '6Lfa6wEaAAAAAGde53kVaXMCOhr7RGu4Wpy3T3a-';
        $this->recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_RUCAPTCHA);
        $this->recognizer->RecognizeTimeout = 120;
        $parameters = [
            "pageurl"   => $this->http->currentUrl(),
            "proxy"     => $this->http->GetProxy(),
            "invisible" => 1,
        ];

        return $this->recognizeByRuCaptcha($this->recognizer, $key, $parameters);
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);
        $this->http->GetURL('https://www.kaligo.com/api/user');
        $json = $this->http->JsonLog(null, 3, true);

        if (
            isset($json['email'])
            && strtolower($json['email']) == strtolower($this->AccountFields['Login'])
        ) {
            return true;
        }

        return false;
    }

    private function checkErrors()
    {
        $this->logger->notice(__METHOD__);

        return false;
    }
}
