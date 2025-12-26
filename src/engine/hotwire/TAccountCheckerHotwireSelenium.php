<?php

use AwardWallet\Common\Parser\Util\PriceHelper;

class TAccountCheckerHotwireSelenium extends TAccountChecker
{
    use SeleniumCheckerHelper;
    use PriceTools;

    private $recognizer;

    private $browser;

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->UseSelenium();
        $this->useChromium();
        $this->disableImages();
        $this->http->saveScreenshots = true;
        $this->usePacFile(false);

        if ($this->attempt > 1) {
            $this->usePacFile(false);
            $this->keepCookies(false);
        } else {
            $this->useCache();
        }
    }

    public function LoadLoginForm()
    {
        $this->http->GetURL('https://www.hotwire.com/checkout/#!/account');

        $email = $this->waitForElement(WebDriverBy::cssSelector('input#sign-in-email'), 10);
        $password = $this->waitForElement(WebDriverBy::cssSelector('input#sign-in-password'), 0);
        $signin = $this->waitForElement(WebDriverBy::xpath('//button[@data-bdd = "do-login"]'), 0);

        if (!$email || !$password || !$signin) {
            return false;
        }

        $this->saveResponse();
        $this->logger->debug("execute");
        $this->driver->executeScript("
            window.grecaptcha = {};
            window.grecaptcha.execute = () => Promise.resolve('');
            window.grecaptcha.render = () => {};
        ");
        $captcha = $this->parseReCaptcha();

        if (!$captcha) {
            return false;
        }
        $this->logger->debug("do login");
        $this->logger->debug("pass: '{$this->AccountFields['Pass']}'"); //todo: issue with '&'

//        $this->driver->executeScript('
//            let oldXHROpen = window.XMLHttpRequest.prototype.open;
//            window.XMLHttpRequest.prototype.open = function(method, url, async, user, password) {
//                this.addEventListener("load", function() {
//                    if (/errorSet/g.exec( this.responseText )) {
//                        localStorage.setItem("responseData", this.responseText);
//                    }
//                });
//                return oldXHROpen.apply(this, arguments);
//            };
//        ');
        $this->driver->executeScript("
            hotwireMe.login('{$this->AccountFields['Login']}', '{$this->AccountFields['Pass']}', '7407019928304672924', '1000', 'true', 'f');
            window.meHotwireComTokenCallback('{$captcha}');
        ");

        return true;
    }

    public function Login()
    {
        sleep(5);
        $this->saveResponse();
        $this->waitForElement(WebDriverBy::xpath('
            //h1[contains(@class,"panel-profile__name")]
            | //span[contains(text(), "Hi, ")]
            | //div[contains(@class, "hw-alert-error")]
        '), 10);
        $this->saveResponse();

//        $responseData = $this->driver->executeScript("return localStorage.getItem('responseData');");
//        $this->logger->info("[Form responseData]: " . $responseData);

        $success = false;
        $cookies = $this->driver->manage()->getCookies();

        foreach ($cookies as $cookie) {
            if ($cookie['name'] == 'me_token') {
                $success = true;
            }
        }

        if ($success) {
            $this->http->GetURL("https://www.hotwire.com/checkout/account/myaccount/myinfo");
            $this->saveResponse();
            $greet = $this->waitForElement(WebDriverBy::xpath('//h1[contains(@class,"panel-profile__name")]'), 5);
            $this->saveResponse();

            if (!$greet && $this->waitForElement(WebDriverBy::xpath('//span[contains(text(), "Hi, ")]'), 0)) {
                $this->http->GetURL("https://www.hotwire.com/checkout/account/myaccount");
                $greet = $this->waitForElement(WebDriverBy::xpath('//h1[contains(@class,"panel-profile__name")]'), 5);
                $this->saveResponse();
            }

            if ($greet) {
                $this->logger->info('Successful login');

                return true;
            }
        }

        $message = $this->http->FindSingleNode('//div[contains(@class, "hw-alert-error")]');

        if ($message) {
            $this->logger->error("[Error]: {$message}");

            if (
                $message == 'The email or password you have entered is incorrect. Please try again.'
            ) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            return false;
        }// if ($message)

        // AccountID: 5390645
        // hard code
        if (
            in_array($this->AccountFields['Login'], [
                'regaltlc@yahoo.comr',
                'mcb0576@yahoo.com',
                'ystark@gmail.com',
                'emil.griffin@gmail.com',
                'muxiaofan0602@gmail.com',
                'bcsnyc@aol.com',
            ])
        ) {
            throw new CheckException("The email or password you have entered is incorrect. Please try again.", ACCOUNT_INVALID_PASSWORD);
        }

        if (
            in_array($this->AccountFields['Login'], [
                'kalsim@yahoo.com',
                'riesinger@comcast.net',
                'sunkuranganath@gmail.com',
                'bessonov@hotmail.com',
            ])
        ) {
            throw new CheckException("Your account is locked due to incorrect attempts. Please wait and try again later.", ACCOUNT_LOCKOUT);
        }

        return false;
    }

    public function Parse()
    {
        $this->browser = new HttpBrowser("none", new CurlDriver());
        $this->browser->setUserAgent($this->http->userAgent);
        $this->http->brotherBrowser($this->browser);
        $cookies = $this->driver->manage()->getCookies();

        foreach ($cookies as $cookie) {
            $this->browser->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'],
                $cookie['expiry'] ?? null);
        }
        /*$headers = [
            'Accept' => '* / *',
            'Content-Type' => 'application/json;charset=UTF-8',
            'Accept-Encoding' => 'gzip, deflate, br',
            'Origin' => 'https://me.hotwire.com',
        ];
        $this->browser->GetURL('https://www.hotwire.com/checkout/account/myaccount/myinfo');
        $this->browser->GetURL('https://www.hotwire.com/api2/login?from=https://www.hotwire.com&apikey=h9hdz5gumhw228csvau3pepx&sig=fee5a5b99d8d257d76570bf07785d4b8',
            $headers);
        $response = $this->browser->JsonLog();
        if (!isset($response->firstName)) {
            return;
        }*/
        $name = $this->waitForElement(WebDriverBy::xpath('//h1[contains(@class,"panel-profile__name")]'), 0);

        if ($name) {
            $this->SetProperty("Name", $name->getText());
            // set balanceNA
            if (!empty($this->Properties['Name'])) {
                $this->SetBalanceNA();
            }
        }
    }

    public function ParseItineraries()
    {
        $this->logger->notice(__METHOD__);
        $result = [];
        $this->parseItinerariesSelenium($result);

        // https://www.hotwire.com/api/account/secure/trip-summary/trip/2319426950?apikey=8yb2vueumutzdt5qngrts48r&completionDateAfter=03%2F18%2F2021&limit=50&sig=a0e0bc3342f940356bda12e7901e8f5c&useCluster=2
        return $result;
    }

    private function parseReCaptcha()
    {
        $this->logger->notice(__METHOD__);
        $key = $this->http->FindPreg('/"siteKey":"([^\"]+)/') ?? '6LfALSEUAAAAAE7yBRtT5pyunsHWgCb7KldyereX'; // https://me.hotwire.com/me/hotwireMe.js?v=1.216.1

        if (!$key) {
            return false;
        }
        $this->recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_RUCAPTCHA);
        $this->recognizer->RecognizeTimeout = 120;
        $parameters = [
            "pageurl" => $this->http->currentUrl(),
            "proxy"   => $this->http->GetProxy(),
        ];

        return $this->recognizeByRuCaptcha($this->recognizer, $key, $parameters);
    }

    private function parseItinerariesSelenium(array &$result): void
    {
        $this->logger->notice(__METHOD__);
        $this->http->GetURL('https://www.hotwire.com/checkout/#!/account/mytrips/upcoming');
        $this->waitForElement(WebDriverBy::xpath('//div[contains(@class, "trip-summary-panel")]'), 15);
        $this->SaveResponse();
        $summaries = $this->http->FindNodes('//div[contains(@class, "trip-summary-panel")]');

        $confSet = [];

        foreach ($result as $itin) {
            $conf = $itin['Number'] ?? null;

            if ($conf) {
                $confSet[$conf] = true;
            }
        }

        foreach ($summaries as $i => $summary) {
            $this->logger->debug('summary:');
            $this->logger->debug($summary);
            $script = "
                let summaries = document.querySelectorAll('div.trip-summary-panel');
                if (summaries.length > 0) {
                    let view = summaries[{$i}].querySelectorAll('a[ng-click = \"viewDetails()\"]');
                    if (view.length > 0) {
                        view[0].click();
                    }
                }
            ";
            $this->driver->executeScript($script);

            try {
                $this->waitForElement(WebDriverBy::xpath('//div[contains(text(), "Reservation details")]'), 5, false);
            } catch (StaleElementReferenceException $e) {
                $this->logger->error('StaleElementReferenceException');
                sleep(5);
            }
            $this->SaveResponse();
            $this->http->SetBody($this->driver->executeScript('return document.documentElement.innerHTML'));

            if ($this->http->FindPreg('/Pick up/', false, $summary)) {
                $itin = $this->parseCarCheckout($summary);
                $exists = $confSet[$itin['Number']] ?? false;

                if (!$exists) {
                    $result[] = $itin;
                }
            }

            $this->http->GetURL('https://www.hotwire.com/checkout/#!/account/mytrips/upcoming');
            $this->waitForElement(WebDriverBy::xpath('//div[contains(@class, "trip-summary-panel")]'), 5);
            $this->waitForElement(WebDriverBy::xpath('//a[contains(@href, "https://vacation.hotwire.com/user/forgotitin")]'), 5);
        }
    }

    private function parseCarCheckout(string $summary): array
    {
        $this->logger->notice(__METHOD__);
        $result = ['Kind' => 'L'];
        // Number
        $result['Number'] = $this->http->FindSingleNode('(//div[@data-bdd = "reservation-detail-itinerary-text"])[1]');
        $this->logger->info("Parse Car #{$result['Number']}", ['Header' => 3]);
        // TripNumber
        $result['TripNumber'] = $this->http->FindSingleNode('(//div[@data-bdd = "reservation-detail-enterprise-text"])[1]');
        // PickupDatetime
        $date1 = $this->http->FindSingleNode('(//span[@data-bdd = "pickup-date-reservation-detail"])[1]');
        $time1 = $this->http->FindSingleNode('(//span[@data-bdd = "pickup-time-reservation-detail"])[1]');
        $result['PickupDatetime'] = strtotime($time1, strtotime($date1));
        // PickupLocation
        $result['PickupLocation'] = $this->http->FindSingleNode('//span[@data-bdd = "pickup-address"]');

        if (empty($result['PickupLocation'])) {
            $result['PickupLocation'] = $this->http->FindSingleNode('(//div[@data-bdd="pickup-location-reservation-detail"])[1]');
        }
        // DropoffDatetime
        $date2 = $this->http->FindSingleNode('(//span[@data-bdd = "dropoff-date-reservation-detail"])[1]');
        $time2 = $this->http->FindSingleNode('(//span[@data-bdd = "dropoff-time-reservation-detail"])[1]');
        $result['DropoffDatetime'] = strtotime($time2, strtotime($date2));
        // DropoffLocation
        $result['DropoffLocation'] = $this->http->FindSingleNode('//span[@data-bdd = "dropoff-address"]');

        if (empty($result['DropoffLocation'])) {
            $result['DropoffLocation'] = $this->http->FindSingleNode('(//div[@data-bdd="dropoff-location-reservation-detail"])[1]');
        }
        // CarType
        $result['CarType'] = $this->http->FindSingleNode('(//strong[contains(text(), "Model -")])[1]');
        // CarModel
        $result['CarModel'] = $this->http->FindSingleNode('(//span[contains(text(), " or similar")])[1]');
        // RenterName
        $result['RenterName'] = $this->http->FindSingleNode('(//div[@data-bdd = "reservation-detail-driver-text"])[1]');
        // TotalCharge
        $totalStr = $this->http->FindSingleNode('(//div[@data-bdd = "summary-of-charges-confirm-total-before-insurance"]/div[2])[1]');
        $total = $this->http->FindPreg('/([\d.,]+)/', false, $totalStr);
        $result['TotalCharge'] = PriceHelper::cost($total);
        $result['Currency'] = $this->currency($totalStr);
        // TotalTaxAmount
        $taxStr = $this->http->FindSingleNode('(//div[@data-bdd = "summary-of-charges-confirm-car-taxes-and-fees"])[1]');
        $tax = $this->http->FindPreg('/([\d.,]+)/', false, $taxStr);
        $result['TotalTaxAmount'] = PriceHelper::cost($tax);
        // BaseFare
        $costStr = $this->http->FindSingleNode('(//div[@data-bdd = "summary-of-charges-confirm-car-daily-rate"])[1]');
        $cost = $this->http->FindPreg('/([\d.,]+)/', false, $costStr);
        $result['BaseFare'] = PriceHelper::cost($cost);
        // ReservationDate
        $result['ReservationDate'] = strtotime($this->http->FindSingleNode('(//div[@data-bdd = "summary-of-charges-confirm-car-date-booked"])[1]'));
        // CarImageUrl
        $result['CarImageUrl'] = $this->http->FindSingleNode('(//img[contains(@class, "car-image")]/@src)[1]');

        if ($this->http->FindPreg('/Car rental reservation CANCELED/', false, $summary)) {
            $result['Cancelled'] = true;
        }

        $this->logger->debug('Parsed Car:');
        $this->logger->debug(var_export($result, true), ['pre' => true]);

        return $result;
    }
}
