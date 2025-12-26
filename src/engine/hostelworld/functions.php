<?php

use AwardWallet\Schema\Parser\Component\Field\Field;

class TAccountCheckerHostelworld extends TAccountChecker
{
    use PriceTools;

    private const REWARDS_PAGE_URL = "https://tsecure.hostelworld.com/en/account";

    private $session;
    private $customerId = null;
    /**
     * @var CaptchaRecognizer
     */
    private $recognizer;

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

        if ($this->http->FindSingleNode("(//a[contains(text(),'My Account')])[1]")) {
            return true;
        }

        return false;
    }

    public function GetRedirectParams($targetURL = null)
    {
        $arg = parent::GetRedirectParams($targetURL);
        $arg['CookieURL'] = 'https://tsecure.hostelworld.com/en/myworld';
        $arg['SuccessURL'] = self::REWARDS_PAGE_URL;

        return $arg;
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
//        $this->http->GetURL('https://www.hostelworld.com/pwa/login?iss=https://www.hostelworld.com/pwa/account');
        $this->http->GetURL('https://www.hostelworld.com/');
        $this->http->GetURL('https://hostelworld-2021-production.eu.auth0.com/authorize?client_id=u01Hp9cuOXXmmq88XfRDVZdA7AjA5uel&response_type=code&redirect_uri=https%3A%2F%2Fwww.hostelworld.com%2Fpwa%2Fredirect&audience=https%3A%2F%2Fhostelworld-2021-production.eu.auth0.com%2Fapi%2Fv2%2F&ui_locales=en&gtm_options%5Bpage%5D%5Bpath%5D=%2F&gtm_options%5Bgtm_id%5D=GTM-NV4Z79H&gtm_options%5Bgtm_site_lang%5D=en&gtm_options%5Bgtm_hw_release_version%5D=1.2129.0&gtm_options%5Bgtm_application_env%5D=production&gtm_options%5Bgtm_search_currency%5D=USD&gtm_options%5Bgtm_user_status%5D=logged%20out&gtm_options%5Bgtm_cust_no%5D=0&gtm_options%5Bgtm_user_id%5D=0&gtm_options%5Bgtm_domain_name%5D=hostelworld.com&gtm_options%5Bgtm_crotest_variations%5D=variation&gtm_options%5Bgtm_crotest_variations_2%5D=control&gtm_options%5Bgtm_crotest_variations_3%5D=control&gtm_options%5Bgtm_ckcrovariable_1%5D=variation&scope=profile%20email%20openid%20offline_access&platform=web&ga=GA1.2.1871036457.1676534728&state=aHR0cHM6Ly93d3cuaG9zdGVsd29ybGQuY29tL3B3YS9yZWRpcmVjdD9sb2dpbj0xJm15d2xkPWZhbHNl&auth0Client=eyJuYW1lIjoiYXV0aDAuanMiLCJ2ZXJzaW9uIjoiOS4xOS4yIn0%3D');

        $currentUrl = $this->http->currentUrl();
        $clientId = $this->http->FindPreg("/client=([^&]+)/", false, $currentUrl);
        $state = $this->http->FindPreg("/state=([^&]+)/", false, $currentUrl);
        $scope = $this->http->FindPreg("/scope=([^&]+)/", false, $currentUrl);
        $redirect_uri = $this->http->FindPreg('/redirect_uri=(.+?)&/', false, $currentUrl);
        $csrf = $this->http->getCookieByName("_csrf", "hostelworld-2021-production.eu.auth0.com", "/usernamepassword/login", true);

        if (!isset($csrf, $clientId, $state, $redirect_uri, $scope)) {
            return $this->checkErrors();
        }

        $headers = [
            'Accept'          => '*/*',
            'Content-Type'    => 'application/json',
            'Auth0-Client'    => 'eyJuYW1lIjoiYXV0aDAuanMtdWxwIiwidmVyc2lvbiI6IjkuMTYuNCJ9',
            'Accept-Encoding' => 'gzip, deflate, br',
        ];
        $data = [
            'state' => $state,
        ];
        $this->http->RetryCount = 0;
        $this->http->PostURL("https://hostelworld-2021-production.eu.auth0.com/usernamepassword/challenge", json_encode($data), $headers);
        $this->http->RetryCount = 2;
        $response = $this->http->JsonLog();

        if (isset($response->provider) && $response->provider == 'auth0') {
            $captcha = $this->parseCaptcha($response->image);
        } elseif (isset($response->required) && $response->required == false) {
        } else {
            return false;
        }

        $data = [
            "audience"      => "https://hostelworld-2021-production.eu.auth0.com/api/v2/",
            "client_id"     => $clientId,
            "connection"    => "Username-Password-Authentication",
            "password"      => $this->AccountFields['Pass'],
            "redirect_uri"  => urldecode($redirect_uri),
            "response_type" => "code",
            "scope"         => "profile email openid offline_access",
            "state"         => $state,
            "tenant"        => "hostelworld-2021-production",
            "username"      => $this->AccountFields['Login'],
            "_csrf"         => "QNDXbQVz-sFUYNVoEoHki4JcEI9K5zg0qZms",
            "_intstate"     => "deprecated",
        ];

        if (isset($captcha)) {
            $data['captcha'] = $captcha;
        }

        $this->http->RetryCount = 0;
        $this->http->PostURL("https://hostelworld-2021-production.eu.auth0.com/usernamepassword/login", json_encode($data), $headers);
        $this->http->RetryCount = 2;

        return true;
    }

    public function checkErrors()
    {
        // Sorry, an error has occured. We apologise for the inconvenience.
        if ($message = $this->http->FindSingleNode("
                //p[contains(text(), 'Sorry, an error has occured. We apologise for the inconvenience.')]
                | //h2[contains(text(), 're usually up for adventure 24/7, but everyone deserves a little switch-off time!')]
            ")
        ) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
        $response = $this->http->JsonLog();

        if (isset($response->description)) {
            $message = $response->description;
            $this->logger->error("[Error]: {$message}");

            if ($message == 'Invalid captcha value') {
                $this->captchaReporting($this->recognizer, false);

                throw new CheckRetryNeededException(3, 0, self::CAPTCHA_ERROR_MSG);
            }

            $this->captchaReporting($this->recognizer);

            if (
                $message == 'Wrong email or password.'
                || $message == 'wrong email or password'
                || strstr($message, "There isn't an account associated with this email or the password is incorrect.")
            ) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            if ($message == '<UNKNOWN_ERROR>') {
                throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
            }

            $this->DebugInfo = $message;

            return false;
        // AccountID: 6606127
        } elseif (isset($response->message) && $response->message == 'Request to Webtask exceeded allowed execution time') {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        if ($this->http->ParseForm("hiddenform")) {
            $this->http->PostForm();
        }

        $headers = [
            'Accept'          => '*/*',
        ];
//        $this->http->GetURL("https://www.hostelworld.com/api/session", $headers);
        $this->http->GetURL("https://www.hostelworld.com/pwa/account", $headers);
        $this->session = $this->http->JsonLog();
        $this->customerId = $this->http->FindPreg("/customerId:\s*(\d+)/");

        if (
            !empty($this->session->profile->name)
            || (!empty($this->customerId) && $this->http->getCookieByName("hw_access_token", ".hostelworld.com", "/", true))
        ) {
            $this->captchaReporting($this->recognizer);

            return true;
        }

        // broken accounts
        if (in_array($this->AccountFields['Login'], [
            "fbtlopes@gmail.com",
            "rodrigoegm@gmail.com",
        ])) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        // Name
        $this->SetProperty('Name', beautifulName($this->session->profile->name ?? $this->http->FindPreg("/,name:\"([^\"]+)/")));
        $headers = [
            'Accept'          => 'application/json',
            'Authorization'   => $this->session->tokens->accessToken ?? $this->http->getCookieByName("hw_access_token", ".hostelworld.com", "/", true),
            'Origin'          => 'https://www.hostelworld.com',
        ];

        $this->customerId =
            $this->customerId
            ?? $this->session->userId
        ;

        $this->http->GetURL("https://api.m.hostelworld.com/2.2/users/{$this->customerId}/credits/?application=web", $headers);
        $response = $this->http->JsonLog();

        foreach ($response->promo as $promo) {
            if ($promo->currency == 'USD') {
                $this->SetBalance($promo->value);

                break;
            }
        }
    }

    public function ParseItineraries()
    {
        $headers = [
            'Accept'          => 'application/json',
            'Authorization'   => $this->session->tokens->accessToken ?? $this->http->getCookieByName("hw_access_token", ".hostelworld.com", "/", true),
            'Origin'          => 'https://www.hostelworld.com',
            'Accept-Encoding' => 'gzip, deflate, br',
        ];

        // Future
        $this->http->GetURL("https://api.m.hostelworld.com/2.2/users/{$this->customerId}/bookings/?state=future&page=1&per-page=100&application=web",
            $headers);
        $futureNoIt = $this->http->FindPreg('/\{"bookings":\[\],/');
        $response = $this->http->JsonLog(null, 1);

        foreach ($response->bookings as $booking) {
            $this->http->GetURL("https://api.m.hostelworld.com/2.2/users/{$this->customerId}/bookings/{$booking->id}/?application=web",
                $headers);
            $this->ParseItinerary('Future', $booking->reference);
        }

        // Cancelled
        $this->http->GetURL("https://api.m.hostelworld.com/2.2/users/{$this->customerId}/bookings/?state=cancelled&page=1&per-page=100&application=web",
            $headers);
        $cancelledNoIt = $this->http->FindPreg('/\{"bookings":\[\],/');
        $response = $this->http->JsonLog(null, 1);

        foreach ($response->bookings as $booking) {
            $this->http->RetryCount = 0;
            $this->http->GetURL("https://api.m.hostelworld.com/2.2/users/{$this->customerId}/bookings/{$booking->id}/?application=web",
                $headers);
            $this->http->RetryCount = 2;
            $this->ParseItinerary('Cancelled', $booking->reference);
        }

        // Past
        if ($this->ParsePastIts) {
            $this->http->RetryCount = 0;
            $this->http->GetURL("https://api.m.hostelworld.com/2.2/users/{$this->customerId}/bookings/?state=past&page=1&per-page=100&application=web",
                $headers);
            $this->http->RetryCount = 2;
            $response = $this->http->JsonLog();

            foreach ($response->bookings as $booking) {
                $this->http->RetryCount = 0;
                $this->http->GetURL("https://api.m.hostelworld.com/2.2/users/{$this->customerId}/bookings/{$booking->id}/?application=web",
                    $headers);
                $this->http->RetryCount = 2;
                $this->ParseItinerary('Past', $booking->reference);
            }

            if ($futureNoIt && $cancelledNoIt && $this->http->FindPreg('/\{"bookings":\[\],/')) {
                $this->itinerariesMaster->setNoItineraries(true);
            }
        } elseif ($futureNoIt && $cancelledNoIt) {
            $this->itinerariesMaster->setNoItineraries(true);
        }

        return [];
    }

    public function ParseItinerary($type, $reference)
    {
        $this->logger->notice(__METHOD__);
        $response = $this->http->JsonLog(null, 1);
        $confNo = $response->reference ?? null;

        $this->logger->info(sprintf('[%s] Parse Itinerary #%s', $type, $reference), ['Header' => 3]);

        if (!$confNo && isset($response->description[0]->message)) {
            $this->logger->error("[Error]: {$response->description[0]->message}");

            return;
        }

        $h = $this->itinerariesMaster->add()->hotel();
        $h->general()
            ->confirmation($confNo)
            ->date2($response->createdDateTime)
        ;

        $address = $response->property->address1;

        if (!empty($response->property->address2)) {
            $address .= ', ' . $response->property->address2;
        }

        if (!empty($response->property->city)) {
            $address .= ', ' . $response->property->city->name . ', ' . $response->property->city->country;
        }
        $h->hotel()
            ->name($response->property->name)
            ->address($address);

        if (!empty($response->property->phone) && strlen($response->property->phone) > 4) {
            $phones = preg_split('/[;]/', $response->property->phone);

            foreach ($phones as $phone) {
                $phone = trim($phone);

                if ($this->http->FindPreg(Field::PHONE_REGEXP, false, $phone)) {
                    $h->hotel()->phone($phone);
                }
            }
        }
        $h->booked()
            ->checkIn(strtotime($response->arrivalDate . ', ' . $response->checkInTime))
            ->checkOut(strtotime($response->departureDate . ', ' . $response->checkInTime))
            ->guests($response->guests);

        if ($response->status == 'CANX') {
            $h->setCancelled(true);
        }

        foreach ($response->rooms->dorms as $dorm) {
            $r = $h->addRoom();
            $r->setType(beautifulName($dorm->ratePlanType), true, true);
            $r->setDescription($dorm->name);

            if (isset($dorm->payment->total->propertyCurrency)) {
                $r->setRate($dorm->payment->total->propertyCurrency->value . ' ' . $dorm->payment->total->propertyCurrency->currency);
            }
        }

        if (!empty($response->cancellationPolicies[0]->description)) {
            $h->setCancellation($response->cancellationPolicies[0]->description);

            if ($this->http->FindPreg('/^Non-refundable/', false, $response->cancellationPolicies[0]->label)) {
                $h->setNonRefundable(true);
            }
        }

        $h->price()->currency($response->payment->total->currency);
        $h->price()->total($response->payment->total->value);

        if (isset($response->payment->tax)) {
            $h->price()->tax($response->payment->tax->value);
        }

        $this->logger->info('Parsed Itinerary:');
        $this->logger->info(var_export($h->toArray(), true), ['pre' => true]);
    }

    protected function parseCaptcha($captcha)
    {
        $this->logger->debug('captcha: ' . $captcha);
        $marker = 'data:image/svg+xml;base64,';

        if (strpos($captcha, $marker) !== 0) {
            $this->logger->debug('no marker');

            return false;
        }
        $captcha = substr($captcha, strlen($marker));
        $file = tempnam(sys_get_temp_dir(), 'captcha');
        $this->logger->debug('captcha file: ' . $file);
        file_put_contents($file, base64_decode($captcha));

        $image = new Imagick();
        $image->readImageBlob(file_get_contents($file));
        //$image->resizeImage(150, 50, imagick::FILTER_LANCZOS, 1);
        $image->writeImage($file . '.jpg');

        $this->recognizer = $this->getCaptchaRecognizer();
        $this->recognizer->reRecognizeTimeout = 100;
        $code = $this->recognizeCaptcha($this->recognizer, $file . '.jpg', ["regsense" => 1]);
        unlink($file);
        unlink($file . '.jpg');

        return $code;
    }
}
