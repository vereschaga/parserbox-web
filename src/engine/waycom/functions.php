<?php

use AwardWallet\Common\Parsing\Html;

class TAccountCheckerWaycom extends TAccountChecker
{
    use AwardWallet\Engine\ProxyList;

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->RetryCount = 0;
        $this->http->setHttp2(true);
        $this->setProxyGoProxies();
        $this->http->setUserAgent('Mozilla/5.0 (X11; Linux x86_64; rv:109.0) Gecko/20100101 Firefox/115.0');
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
        $this->http->GetURL('https://www.way.com/app/modules/landingPage/login/loginLinksNew.tmpl.html?v1.0.491');

        if ($this->http->Response['code'] != 200) {
            return false;
        }

        $this->securityCheckWorkaround();

        $this->http->FormURL = 'https://www.way.com/way-auth/auth/login';
        $this->http->SetInputValue('grant_type', 'password');
        $this->http->SetInputValue('username', $this->AccountFields['Login']);
        $this->http->SetInputValue('password', $this->AccountFields['Pass']);

        return true;
    }

    public function Login()
    {
        $headers = [
            'Accept'        => 'application/json, text/plain, */*',
            'Authorization' => 'Basic ' . base64_encode('way-web-consumer:35413210-85ea-4c06-9204-94dc23ced73c'),
        ];
        $this->http->PostForm($headers);
        $data = $this->http->JsonLog(null, 3, true);
        $message = $data['message'] ?? null;
        $email = $data["email"] ?? '';
        $token = $this->http->Response['headers']['access_token'] ?? null;

        if (is_string($message)) {
            if ($message == 'Successfully Loggedin' && $token) {
                if (strtolower($email) !== strtolower($this->AccountFields['Login'])) {
                    $this->logger->error("the data does not match the requested account");

                    return false;
                }

                $this->http->setCookie('Bearer', $token);

                return $this->loginSuccessful();
            }

            if (
                $message == 'Your username or password is not correct'
                || $message == 'Your username or password is incorrect'
            ) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            $this->DebugInfo = $message;
        }

        return false;
    }

    public function Parse()
    {
        $data = $this->http->JsonLog(null, 0, true);
        // get currency info to store it in each itinerary info
        $this->currency = $data['currencybo']['currencyName'] ?? null;

        // Name
        $this->SetProperty('Name', beautifulName(Html::cleanXMLValue($data['userFullName'] ?? null)));

        $data = $this->getData('https://www.way.com/way-consumer/v1/user/waybucks', 3, true);
        // Balance - Way Bucks Available
        $this->SetBalance($data['waybuckAvailable'] ?? null);
        // Way Bucks Earned
        $this->SetProperty('WaybackEarned', $data['waybuckEarned'] ?? null);
        // Way Bucks Redeemed
        $this->SetProperty('WaybackRedeemed', $data['waybuckRedeemed'] ?? null);
    }

    public function ParseItineraries()
    {
        $upcomingItineraries = $this->fetchUpcomuingItineraries();
        $pastItineraries = $this->fetchPastItineraries();
        $cancelledItineraries = $this->fetchCancelledItineraries();

        $upcomingItinerariesIsPresent = $upcomingItineraries !== false;
        $pastItinerariesIsPresent = $pastItineraries !== false;
        $cancelledItinerariesIsPresent = $cancelledItineraries !== false;

        $this->logger->debug('Upcoming itineraries is present: ' . (int) $upcomingItinerariesIsPresent);
        $this->logger->debug('Previous itineraries is present: ' . (int) $pastItinerariesIsPresent);
        $this->logger->debug('Cancelled itineraries is present: ' . (int) $cancelledItinerariesIsPresent);

        // check for the no its
        $seemsNoIts = !$upcomingItinerariesIsPresent && !$pastItinerariesIsPresent;
        $this->logger->info('Seems no itineraries: ' . (int) $seemsNoIts);
        $this->logger->info('ParsePastIts: ' . (int) $this->ParsePastIts);

        if ($upcomingItinerariesIsPresent) {
            foreach ($upcomingItineraries as $node) {
                $this->parseItinerary($node);
            }
        }

        if ($pastItinerariesIsPresent && $this->ParsePastIts) {
            foreach ($pastItineraries as $node) {
                $this->parseItinerary($node);
            }
        }

        if ($seemsNoIts) {
            $this->itinerariesMaster->setNoItineraries(true);

            return [];
        }

        if (!$this->itinerariesMaster->getNoItineraries() && $cancelledItinerariesIsPresent) {
            foreach ($cancelledItineraries as $node) {
                $this->parseItinerary($node);
            }
        }

        return [];
    }

    protected function parseReCaptcha($key)
    {
        $this->logger->notice(__METHOD__);
        $key = $key ?? $this->http->FindSingleNode("//div[contains(@class, 'g-recaptcha')]/@data-sitekey");

        if (!$key) {
            return false;
        }

        $recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_RUCAPTCHA);
        $recognizer->RecognizeTimeout = 120;
        $parameters = [
            "pageurl" => $this->http->currentUrl(),
            "proxy"   => $this->http->GetProxy(),
        ];

        return $this->recognizeByRuCaptcha($recognizer, $key, $parameters);
    }

    private function parseItinerary($node)
    {
        $this->logger->notice(__METHOD__);

        if (is_string($node['serviceName'] ?? null) && $node['serviceName'] == 'Parking') {
            $this->parseParkingItinerary($node);
        } elseif (is_string($node['serviceName'] ?? null) && $node['serviceName'] != 'Parking') {
            $this->sendNotification("refs #23114 Reservation found based on {$node['serviceName']} type");
        }
    }

    private function parseParkingItinerary($node)
    {
        $this->logger->notice(__METHOD__);
        $p = $this->itinerariesMaster->createParking();

        $confNo = $node['confirmationNo'];
        $this->logger->info("Parse Itinerary #{$confNo}", ['Header' => 3]);

        $data = $this->getData("https://www.way.com/way-orders/v1/users/orders/$confNo/summary", 3, true);

        if ($data['earnedWaybucks'] ?? null) {
            $p->program()->earnedAwards($data['earnedWaybucks'] . ' waybucks');
        }
        $p->general()->confirmation($confNo, 'Confirmation #');
        $p->general()->date2($data['orderedOn'] ?? null);
        $p->general()->status($data['orderStatus'] ?? null);
        $p->general()->traveller($data['userName'] ?? null, true);

        if (is_string($data['orderStatus'] ?? null) && $data['orderStatus'] == 'Cancelled') {
            $p->general()->cancelled();
        }
        $p->general()->notes($data['specialInstructions'] ?? null);

        $p->price()->currency($this->currency);
        $p->price()->total($data['orderPrice']['total'] ?? null);
        $p->price()->cost($data['orderPrice']['total'] ?? null);

        if ($data['orderPrice']['taxFee']['splits'] ?? null) {
            foreach ($data['orderPrice']['taxFee']['splits'] as $od) {
                $p->price()->fee($od['name'] ?? null, $od['value'] ?? null);
            }
        }

        $p->price()->discount($data['orderPrice']['discount'] ?? null);

        $p->place()->address($data['listing']['address']['address'] ?? null);
        $p->place()->location($data['listing']['listingName'] ?? null);

        $dt = new DateTime($data['checkIn']);
        $dt->setTimezone(new DateTimeZone($data['listingTimezone']['timezone']));
        $p->booked()->start2($dt->format("Y-m-d H:i:s"));

        $dt = new DateTime($data['checkOut']);
        $dt->setTimezone(new DateTimeZone($data['listingTimezone']['timezone']));
        $p->booked()->end2($dt->format("Y-m-d H:i:s"));

        if (is_string($data['listing']['contactInfo']['contactType'] ?? null) && $data['listing']['contactInfo']['contactType'] == 'PHONE') {
            $p->place()->phone($data['listing']['contactInfo']['contactValue']);
        }

        if ($data['operatingHours'] ?? null) {
            foreach ($data['operatingHours'] as $oh) {
                if ($oh['operatingHours'] ?? null) {
                    foreach ($oh['operatingHours'] as $oh_nested) {
                        if ($oh_nested['operatingHourStatus'] === 'Active') {
                            $p->addOpeningHours($oh['day'] . ': ' . $oh_nested['from'] . '-' . $oh_nested['to']);
                        }
                    }
                }
            }
        }

        $carData = $this->prepareCarData($data);

        if ($carData) {
            $p->booked()->car($carData);
        }
    }

    private function prepareCarData($data)
    {
        $result = '';

        foreach (['carColor', 'carModel'] as $field) {
            if ($data['vehicleDetails'][$field] ?? null) {
                if ($result !== '') {
                    $result = $result . ' ';
                }

                $result = $result . $data['vehicleDetails'][$field];
            }
        }

        if ($result !== '') {
            return $result;
        }

        return null;
    }

    private function fetchUpcomuingItineraries()
    {
        return $this->fetchItineraries('UpcomingOngoing');
    }

    private function fetchPastItineraries()
    {
        return $this->fetchItineraries('Past');
    }

    private function fetchCancelledItineraries()
    {
        return $this->fetchItineraries('Cancelled');
    }

    private function fetchItineraries($type)
    {
        $params = [
            "categories" => [
                "Parking",
                "Dining",
                "Movies",
                "Events",
                "Activities",
                "Carwash",
            ],
            "orderType"     => $type,
            "paginationDto" => [
                "pageNumber" => 1,
                "pageSize"   => 100000,
            ],
        ];

        if (
            $orders = $this->postData(
                "https://www.way.com/way-orders/v1/users/orders",
                json_encode($params),
                3,
                true
            )
        ) {
            $ordersCount = $orders['totalRecords'] ?? null;

            if (is_numeric($ordersCount) && $ordersCount !== 0) {
                $this->logger->debug("Fetched {$type} orders. Count: {$ordersCount}");

                return $orders["rows"];
            }

            return false;
        }
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);

        $data = $this->getData('https://www.way.com/way-service/security/userProfileManagement/user', 3, true);
        $status = $data['status'] ?? null;

        if ($status == 'Success') {
            return true;
        }

        return false;
    }

    private function securityCheckWorkaround()
    {
        $this->logger->notice(__METHOD__);

        if ($this->http->ParseForm('challenge-form')) {
            $key = $this->http->FindSingleNode("//script[@data-sitekey]/@data-sitekey");

            if (!$key) {
                return false;
            }

            $captcha = $this->parseReCaptcha($key);

            if ($captcha === false) {
                return false;
            }

            $this->http->SetInputValue('g-recaptcha-response', $captcha);
            $this->http->PostForm();
        }

        return true;
    }

    private function getData(string $url, $logs = 0, $convertToArray = false)
    {
        $this->logger->notice(__METHOD__);
        $this->http->GetURL($url, [
            'Accept'        => 'application/json, text/plain, */*',
            'Authorization' => 'Bearer ' . $this->http->getCookieByName('Bearer', 'www.way.com'),
        ]);

        return $this->http->JsonLog(null, $logs, $convertToArray);
    }

    private function postData(string $url, $params = [], $logs = 0, $convertToArray = false)
    {
        $this->logger->notice(__METHOD__);
        $this->http->PostURL($url, $params, [
            'Accept'        => 'application/json, text/plain, */*',
            'Authorization' => 'Bearer ' . $this->http->getCookieByName('Bearer'),
            'Content-Type'  => 'application/json',
        ]);

        return $this->http->JsonLog(null, $logs, $convertToArray);
    }
}
