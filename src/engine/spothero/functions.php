<?php

class TAccountCheckerSpothero extends TAccountChecker
{
    use AwardWallet\Engine\ProxyList;

    private const clientId = "E5LUulB6sqmsH5tAfn7FqaCzPYZEIBy6";
    private const auth0Client = "eyJuYW1lIjoiYXV0aDAtcmVhY3QiLCJ2ZXJzaW9uIjoiMS42LjAifQ==";

    public static function FormatBalance($fields, $properties)
    {
        return formatFullBalance($fields['Balance'], $fields['ProviderCode'], "$%0.2f");
    }

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->http->SetHttp2(true);
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
        $this->http->GetURL('https://accounts.spothero.com/authorize?audience=https%3A%2F%2Fapi.spothero.com%2Fv1%2F&scope=openid%20profile%20email%20read%3Acurrent_user%20update%3Acurrent_user_metadata&client_id=' . self::clientId . '&redirect_uri=https%3A%2F%2Fspothero.com%2Fauth%2Fpending&response_type=code&response_mode=query&state=LlEweldIOHNpOE1kNDZxUU1EbVlLcGtPZ2hQM1h5U3ZfVE5TNEpkZ2pxUQ==&nonce=Slc3SDVjSThFWkJhd0RYMVlGVzNZZHhCUVhrVWRVOTIwQlJVT3BCQjZQMQ==&code_challenge=xifOwAMOpJIOtpkrAYtcDSKzuQKrAEotMDrMcNxGjW0&code_challenge_method=S256&auth0Client=' . self::auth0Client);

        if (!$this->http->ParseForm(null, '//form[contains(@class, "form-login-id")]')) {
            return $this->checkErrors();
        }

        $this->http->SetInputValue('username', $this->AccountFields['Login']);
        $this->http->SetInputValue('action', "default");

        $captcha = $this->parseReCaptcha();

        if ($captcha !== false) {
            $this->http->SetInputValue('captcha', $captcha);
        }

        return true;
    }

    public function Login()
    {
        $this->http->RetryCount = 0;

        if (!$this->http->PostForm() && $this->http->Response['code'] != 400) {
            return $this->checkErrors();
        }

        $message = $this->http->FindSingleNode('//span[@class="ulp-input-error-message"] | //div[@id="prompt-alert"]/p');

        if ($message) {
            $this->logger->error("[Error]: {$message}");
            $this->DebugInfo = $message;

            return $this->checkErrors();
        }

        if (!$this->http->ParseForm(null, '//form[contains(@class, "form-login-password")]')) {
            return $this->checkErrors();
        }

        $this->http->SetInputValue('password', $this->AccountFields['Pass']);
        $this->http->SetInputValue('action', "default");

        if (!$this->http->PostForm() && $this->http->Response['code'] != 400) {
            return $this->checkErrors();
        }

        $message = $this->http->FindSingleNode('//span[@class="ulp-input-error-message"] | //div[@id="prompt-alert"]/p');

        if ($message) {
            $this->logger->error("[Error]: {$message}");

            if (strstr($message, "Wrong email or password")) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            if (strstr($message, "Something went wrong, please try again later")) {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }

            if (strstr($message, "Your account has been blocked after multiple consecutive login attempts")) {
                throw new CheckException($message, ACCOUNT_LOCKOUT);
            }

            $this->DebugInfo = $message;

            return $this->checkErrors();
        }

        $this->http->RetryCount = 2;

        $code = $this->http->FindPreg("/\?code=([^&]+)/", false, $this->http->currentUrl());

        if ($code) {
            $data = [
                "client_id"     => self::clientId,
                "code"          => $code,
                "code_verifier" => "4FWlSD4d-PGS96eD4ZpAJX8JXjgF1M3JbIxW58Knsvi",
                "grant_type"    => "authorization_code",
                "redirect_uri"  => "https://spothero.com/auth/pending",
            ];
            $headers = [
                'Accept'       => '*/*',
                'Content-Type' => 'application/json',
                'Auth0-Client' => self::auth0Client,
            ];

            $this->http->PostURL("https://accounts.spothero.com/oauth/token", json_encode($data), $headers);
            $response = $this->http->JsonLog();

            if (isset($response->access_token)) {
                $this->http->setCookie('AUTH_TOKEN', $response->access_token, '.spothero.com');
            }

            $log = $this->getData('https://spothero.com/api/v1/users/logged-in/', 3, true);
            $logged_in = $log['data']['logged_in'] ?? null;

            if ($logged_in !== true) {
                return $this->checkErrors();
            }

            $this->http->RetryCount = 0;

            $this->http->PostURL('https://spothero.com/auth/set-cookie/', null, [
                'Accept'        => 'application/json, text/plain, */*',
                'Authorization' => 'Bearer ' . $this->http->getCookieByName('AUTH_TOKEN', '.spothero.com'),
            ]);

            if ($this->http->Response['code'] == 200) {
                return $this->loginSuccessful();
            }
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        $this->http->RetryCount = 2;

        $this->http->GetURL('https://spothero.com/api/v1/users/me/');
        $log = $this->http->JsonLog(null, 3, true);

        $firstName = $log['data']['first_name'] ?? null;
        $lastName = $log['data']['first_name'] ?? null;
        $displayName = $log['data']['display_name'] ?? null;

        $name = "";

        if (!empty($firstName)) {
            $name = $name . $firstName;
        }

        if (!empty($firstName) && !empty($lastName)) {
            $name = $name . " ";
        }

        if (!empty($lastName)) {
            $name = $name . $lastName;
        }

        if (empty($name) && !empty($displayName)) {
            $name = $displayName;
        }

        // Name
        $this->SetProperty('Name', !empty($name) ? beautifulName($name) : null);

        // Balance - USD
        $this->SetBalance($this->prepare_price($log['data']['spothero_credit']));
    }

    public function ParseItineraries()
    {
        $this->http->GetURL('https://spothero.com/account-reservations');

        $upcomingItinerariesIsPresent = $this->upcomingItinerariesIsPresent();
        $previousItinerariesIsPresent = $this->previousItinerariesIsPresent();

        $itinerariesData = $this->prepareIteneraries();

        if ($upcomingItinerariesIsPresent) {
            foreach ($itinerariesData['upcomingTransient'] as $ut) {
                $this->parseItinerary($ut);
            }
        }

        if ($previousItinerariesIsPresent && $this->ParsePastIts) {
            foreach ($itinerariesData['previousTransient'] as $pt) {
                $this->parseItinerary($pt);
            }
        }

        // check for the no its
        $seemsNoIts = !$upcomingItinerariesIsPresent && !$previousItinerariesIsPresent;

        $this->logger->debug('Upcoming itineraries is present: ' . (int) $upcomingItinerariesIsPresent);
        $this->logger->debug('Past itineraries is present: ' . (int) $previousItinerariesIsPresent);
        $this->logger->debug('ParsePastIts: ' . (int) $this->ParsePastIts);
        $this->logger->debug('Seems no itineraries: ' . (int) $seemsNoIts);

        if (!$upcomingItinerariesIsPresent && !$this->ParsePastIts) {
            $this->itinerariesMaster->setNoItineraries(true);
        }

        if ($seemsNoIts && !$this->ParsePastIts) {
            $this->itinerariesMaster->setNoItineraries(true);
        }

        if ($seemsNoIts && $this->ParsePastIts && !$previousItinerariesIsPresent) {
            $this->itinerariesMaster->setNoItineraries(true);
        }

        return [];
    }

    protected function parseReCaptcha()
    {
        $this->logger->notice(__METHOD__);
        $key = $this->http->FindSingleNode('//div[@data-captcha-provider="recaptcha_enterprise"]/@data-captcha-sitekey');

        if (!$key) {
            return false;
        }

        $this->recognizer = $this->getCaptchaRecognizer();
        $this->recognizer->RecognizeTimeout = 120;
        $parameters = [
            "pageurl"   => $this->http->currentUrl(),
            "proxy"     => $this->http->GetProxy(),
        ];

        return $this->recognizeByRuCaptcha($this->recognizer, $key, $parameters);
    }

    private function parseItinerary($node)
    {
        $this->logger->notice(__METHOD__);

        $p = $this->itinerariesMaster->createParking();

        $confNo = $node['rentalId'];
        $this->logger->info("Parse Itinerary #{$confNo}", ['Header' => 3]);

        $this->http->GetURL('https://spothero.com/api/v1/reservations/' . $node['id'] . '/?access_key=undefined&include=phone_number,facility.phone_number_required,vehicle,event,access_hours,rate_title,access_hours_comment,monthly_agreement_link,amenities_full,rule_reservation_type');

        $passData = $this->http->JsonLog(null, 3, true);

        $p->general()->confirmation($confNo, 'Rental ID');
        $p->general()->notes(strip_tags($passData['data']['facility']['getting_here'] ?? null));

        $reservationStatus = $passData['data']['reservation_status'] ?? null;

        if ($reservationStatus === 'cancelled') {
            $p->general()->cancelled();
        }

        $p->general()->status($reservationStatus);

        $street_address = $passData['data']['facility']['street_address'] ?? null;
        $city = $passData['data']['facility']['city'] ?? null;
        $state = $passData['data']['facility']['state'] ?? null;

        $p->place()->address(beautifulName($street_address . ', ' . $city . ', ' . $state));

        $p->place()->location($node['spot']['title'] ?? null);

        // The date on the website is presented taking into account the time difference. Examples: 2020-01-24 17:00:00-05:00, 2022-05-06 20:00:00-04:00
        $p->booked()->start2($this->http->FindPreg('<[0-9]{4}.[0-9]{2}.[0-9]{2}.[0-9]{2}.[0-9]{2}.[0-9]{2}>', false, $node['starts']));
        $p->booked()->end2($this->http->FindPreg('<[0-9]{4}.[0-9]{2}.[0-9]{2}.[0-9]{2}.[0-9]{2}.[0-9]{2}>', false, $node['ends']));

        if ($node['licensePlate'] ?? null) {
            $p->booked()->plate($node['licensePlate']);
        }

        if ($node['type'] ?? null) {
            $p->booked()->rate($node['type']);
        }

        if ($passData['data']['vehicle']['info']['display_description'] ?? null) {
            $p->booked()->car($passData['data']['vehicle']['info']['display_description']);
        }

        if ($passData['data']['price_breakdown']['total_price'] ?? null) {
            $p->price()->total($this->prepare_price($passData['data']['price_breakdown']['total_price']));
        }
        $feeData = $passData['data']['price_breakdown']['items'] ?? null;

        if ($feeData) {
            foreach ($feeData as $fd) {
                if ($fd['type'] == 'rental') {
                    $p->price()->cost($this->prepare_price($fd['price'] ?? null));

                    continue;
                }
                $p->price()->fee($fd['short_description'] ?? null, $this->prepare_price($fd['price'] ?? null));
            }
        }

        foreach ($feeData as $fd) {
            if ($fd['type'] == 'rental') {
                $p->price()->cost($this->prepare_price($fd['price'] ?? null));

                continue;
            }
            $p->price()->fee($fd['short_description'] ?? null, $this->prepare_price($fd['price'] ?? null));
        }

        if ($passData['data']['currency_type'] ?? null) {
            $p->price()->currency(strtoupper($passData['data']['currency_type']));
        }

        if ($passData['data']['currency_code'] ?? null) {
            $p->price()->currency($passData['data']['currency_code']);
        }

        if ($passData['data']['discount_amount'] ?? null) {
            $p->price()->discount($this->prepare_price($passData['data']['discount_amount'] ?? null));
        }

        $p->place()->phone($passData['data']['facility']['airport']['shuttle']['contact_phone_number'] ??
            $this->http->FindPreg('<[0-9]{3}.[0-9]{3}.[0-9]{4}>', false, $passData['data']['facility']['support_description'])
            ?? null);

        $accessHoursData = $passData['data']['access_hours'] ?? null;

        if ($accessHoursData) {
            foreach ($accessHoursData as $ahd) {
                $p->addOpeningHours($ahd['start_dow'] . '-' . $ahd['end_dow'] . ': ' . $ahd['start_time'] . '-' . $ahd['end_time']);
            }
        }

        $this->logger->debug('Parsed itinerary:');
        $this->logger->debug(var_export($p->toArray(), true), ['pre' => true]);
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);
        $this->http->GetURL('https://spothero.com/account-settings/');

        if ($this->http->FindSingleNode('//a[@href="/logout"]')) {
            return true;
        }

        return false;
    }

    private function checkErrors()
    {
        $this->logger->notice(__METHOD__);

        return false;
    }

    private function getData(string $url, $logs = 0, $convertToArray = false)
    {
        $this->logger->notice(__METHOD__);
        $this->http->GetURL($url, [
            'Accept'        => 'application/json, text/plain, */*',
            'Authorization' => 'Bearer ' . $this->http->getCookieByName('AUTH_TOKEN', '.spothero.com'),
        ]);

        return $this->http->JsonLog(null, $logs, $convertToArray);
    }

    private function upcomingItinerariesIsPresent()
    {
        return !(bool) $this->http->FindPreg('/upcomingTransient:\s*\[\s*\]/') && (bool) $this->http->FindPreg('/upcomingTransient:\s*\[[\s\{\}\W\w]*\]/') === true;
    }

    private function previousItinerariesIsPresent()
    {
        return !(bool) $this->http->FindPreg('/previousTransient:\s*\[\s*\]/') && (bool) $this->http->FindPreg('/previousTransient:\s*\[[\s\{\}\W\w]*\]/') === true;
    }

    private function prepareIteneraries()
    {
        $reservationsJsonTag = $this->http->FindSingleNode('//script[contains(text(), "window.AccountReservations = ")]/text()');
        $reservationsJson = preg_replace(['/\s*window./', '/\<!\[CDATA\[/', '/\]\]\>/'], '', $reservationsJsonTag);

        $script = /** @lang JavaScript */ "{$reservationsJson};const validateData=n=>(data=AccountReservations.initObject,!!data.hasOwnProperty(n)&&JSON.stringify(data[n])!==JSON.stringify([])),main=()=>{const n={};return n.upcomingIsPresent=validateData('upcomingTransient'),n.previousIsPresent=validateData('previousTransient'),n.upcomingIsPresent&&(n.upcomingTransient=AccountReservations.initObject.upcomingTransient),n.previousIsPresent&&(n.previousTransient=AccountReservations.initObject.previousTransient),n};sendResponseToPhp(JSON.stringify(main()));";
        $jsExecutor = $this->services->get(\AwardWallet\Common\Parsing\JsExecutor::class);

        $reservationsJsonPrepared = $jsExecutor->executeString($script);

        return $this->http->JsonLog($reservationsJsonPrepared, 3, true);
    }

    private function prepare_price($price)
    {
        if (!is_numeric($price)) {
            return null;
        }

        return $price / 100;
    }
}
