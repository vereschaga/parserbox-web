<?php

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Common\Parsing\Html;
use AwardWallet\Engine\ProxyList;
use AwardWallet\Engine\WeekTranslate;

class TAccountCheckerRentalcars extends TAccountChecker
{
    use DateTimeTools;
    use SeleniumCheckerHelper;
    use ProxyList;

    private $currentItin = 0;

    /** @var CaptchaRecognizer */
    private $recognizer;

    public function GetRedirectParams($targetURL = null)
    {
        $arg = parent::GetRedirectParams($targetURL);
        $arg['CookieURL'] = 'https://www.rentalcars.com/';

        return $arg;
    }

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        /*
        $this->setProxyDOP();
        */
        $this->setProxyMount();
    }

    public function IsLoggedIn()
    {
        $this->http->RetryCount = 0;
        $this->http->GetURL('https://secure.rentalcars.com/account/Dashboard.do', [], 20);
        $this->http->RetryCount = 2;

        if ($this->loginSuccessful()) {
            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        // Invalid email address or password
        if (filter_var($this->AccountFields['Login'], FILTER_VALIDATE_EMAIL) === false) {
            throw new CheckException("Invalid email address or password", ACCOUNT_INVALID_PASSWORD);
        }

        $this->http->removeCookies();
        $this->http->RetryCount = 1;
        $this->http->GetURL('https://secure.rentalcars.com/CRMLogin.do');

        if (!$this->http->ParseForm('loyaltySignInForm')) {
            $this->checkCookies();
            $this->http->GetURL('https://secure.rentalcars.com/CRMLogin.do');
        }

        $this->http->RetryCount = 2;

        if (!$this->http->ParseForm('loyaltySignInForm')) {
            return $this->checkErrors();
        }

        $this->http->SetInputValue('j_username', $this->AccountFields['Login']);
        $this->http->SetInputValue('j_password', $this->AccountFields['Pass']);
        $this->http->SetInputValue('remember-me', 'true');
        $this->http->SetInputValue('submitted', 'true');
        $this->http->SetInputValue('promoCode', '');
        $this->http->SetInputValue('crmOrigin', 'https://secure.rentalcars.com/CRMLogin.do');
        $this->http->unsetInputValue('');

        return true;
    }

    public function Login()
    {
        if (!$this->http->PostForm()) {
            return $this->checkErrors();
        }
        // Change language
        if (!$this->http->FindSingleNode("//em[@id='language' and (@class='us-crm' or @class='en-crm')]/@class")) {
            $this->logger->notice('Change language to EN');

            if ($this->http->ParseForm('langCurrencyForm')) {
                $this->http->SetInputValue('preflang', '521');
                $this->http->PostForm();
            }
        }
        // Please help us confirm your email address
        if ($this->http->FindSingleNode('//h2[contains(text(), "Please help us confirm your email address")]')) {
            $this->throwProfileUpdateMessageException();
        }

        if ($this->loginSuccessful()) {
            return true;
        }

        if ($message = $this->http->FindSingleNode('//p[contains(text(), "Invalid email address or password")]')) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        // We are sorry, an error has occurred processing your request.
        if ($message = $this->http->FindSingleNode('//p[contains(text(), "We are sorry, an error has occurred processing your request.")]')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        $this->http->GetURL('https://secure.rentalcars.com/account/Settings.do');

        if (
            $this->http->currentUrl() == 'https://secure.rentalcars.com/account/SettingsRedesign.do?'
            && $this->http->Response['code'] == 302
        ) {
            if ($this->AccountFields['Login'] == 'ladonpariion@gmail.com') {
                throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
            }

            throw new CheckRetryNeededException(2, 0);
        }

        // Name
        $this->SetProperty('Name', trim(beautifulName($this->http->FindSingleNode('//div[@id=\'account_holder_name\']//p[@class=\'settings_data\']//span[@class=\'text_data\']'))));
        // check property email
        if (!empty($this->Properties['Name']) || $this->http->FindSingleNode('//div[@class=\'email_form\']//span[@class=\'text_data\']')) {
            $this->SetBalanceNA();
        }

        // AccountId: 4735347
        if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR) {
            // Name
            $this->SetProperty('Name', trim(beautifulName($this->http->FindSingleNode("//input[@id='firstName']/@value") . ' '
                . $this->http->FindSingleNode("//input[@id='surname']/@value"))));
            // check property email
            if (!empty($this->Properties['Name']) || $this->http->FindSingleNode("//label[contains(text(), 'Primary email address')]/following-sibling::span", null, false)) {
                $this->SetBalanceNA();
            }
        }
    }

    public function ParseItineraries()
    {
        $result = [];
        $this->http->GetURL('https://secure.rentalcars.com/account/Bookings.do');

        if ($this->http->FindSingleNode('//p[contains(text(), "You don\'t have any upcoming trips. Would you like to search for a car now?")]')) {
            return $this->noItinerariesArr();
        }
        // get list Itineraries (tags <div>)
        $nodes = $this->http->XPath->query("//div[contains(@class,'col-9')]//div[contains(@class ,'bookingbox box group booking-tile')]");
        $this->logger->debug("Total {$nodes->length} reservations were found");
        // getting the number of active reservations
        $arrRes = [];
        $baseHref = trim($this->http->FindPreg("/<base href=\"(https:.+)\">/"), '/');

        foreach ($nodes as $node) {
            $status = $this->http->FindSingleNode(".//div[@class = 'booking-ref-status']/div[contains(., 'Booking Status:')]", $node, true, '/Booking Status: ([^<]+)/');
            $bookURL = $this->http->FindSingleNode(".//div[@class = 'cta']//a[@name = 'view_booking']/@href", $node);
            $REF = $this->http->FindSingleNode(".//div[@class='ref']/text()[normalize-space()!='']", $node);

            if ($bookURL) {
                if (strpos($bookURL, '/') === 0 && !empty($baseHref)) {
                    $bookURL = $baseHref . $bookURL;
                } else {
                    $this->http->NormalizeURL($bookURL);
                }
            }

            if (
                in_array($status, ['Confirmed', 'Cancelled', 'Deposit Paid'])
                || ($this->ParsePastIts == true && $status === 'Completed')
            ) {
                $arr = ['STATUS' => $status, 'URL' => $bookURL, 'REF' => $REF, 'Parsed' => $this->parseItineraryPre($node)];

                if ($this->ParsePastIts == false && strtotime($arr['Parsed']['doTime']) < time()) {
                    $this->logger->notice("skip old it: #$REF - {$status}");

                    continue;
                }

                $arrRes[] = $arr;
            } elseif (!in_array($status, ['Confirmed', 'Cancelled', 'Completed', 'Quote', 'Deposit Paid'])) {
                $this->sendNotification("refs #13477. Unknown itinerary status was found: {$status}");
            }
        }

        if (empty($arrRes) && $this->ParsePastIts == false) {
            return $this->noItinerariesArr();
        }
        // get and foreach list link on the page detail (tags <a>)
        $this->logger->debug("Total " . count($arrRes) . " parse reservations were found");

        foreach ($arrRes as $value) {
            $this->http->setMaxRedirects(10);
            $this->http->RetryCount = 0;
            $this->http->GetURL($value['URL']);
            $this->http->RetryCount = 2;

            if ($this->http->FindSingleNode("//h1[contains(.,'Pardon Our Interruption')]")) {
                sleep(3);
//                $this->http->GetURL($value['URL']);
                $this->http->GetURL($this->http->currentUrl(), ['Referer' => $this->http->currentUrl()]);
            }
            $this->http->setMaxRedirects(5);

            if (
                $this->http->FindSingleNode("//h1[contains(.,'Pardon Our Interruption')]")
                || $this->http->FindPreg("/The details you entered were incorrect, please check them and try again/")
            ) {
                $it = $value['Parsed'];

                $this->logger->info("[{$this->currentItin}] Parse Itinerary #{$value['REF']} (broken link)", ['Header' => 3]);
                $this->currentItin++;

                // canceled old avis reservations workaround
                if (!$this->http->FindPreg("/The details you entered were incorrect, please check them and try again/")) {
                    $this->logger->notice("[NOTICE] Parse from main page, without details");
                }

                $r = $this->itinerariesMaster->add()->rental();
                $r->general()
                    ->confirmation($value['REF'])
                    ->status($value['STATUS']);
                $r->car()
                    ->type($it['carType'])
                    ->model($it['carModel'])
                    ->image($it['carImageUrl']);
                $r->extra()->company($it['company']);
                $r->pickup()
                    ->location($it['puLoc'])
                    ->date2($it['puTime']);
                $r->dropoff()
                    ->location($it['doLoc'])
                    ->date2($it['doTime']);
            } elseif (strstr($this->http->currentUrl(), '/my-booking/')) {
                $props = $this->http->FindSingleNode("//div[@data-mb-react-component-name='App']/@data-mb-props");
                $data = $this->http->JsonLog(urldecode($props), 1);
                $this->parseItineraryNew($data, $value['STATUS'], $value['REF']);
            } else {
                $this->parseItinerary($value['STATUS'], $value['REF']);
            }
            $this->logger->notice(' ');
            sleep(rand(3, 5));
        }

        return $result;
    }

    protected function parseCaptcha()
    {
        $this->logger->notice(__METHOD__);
        $key = $this->http->FindSingleNode("//div[contains(@class, 'g-recaptcha')]/@data-sitekey");
        $this->logger->debug("data-sitekey: {$key}");

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

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);

        if ($this->http->FindSingleNode('//a[contains(text(), "Sign out")]')) {
            return true;
        }

        return false;
    }

    private function checkErrors()
    {
        $this->logger->notice(__METHOD__);

        return false;
    }

    private function parseItineraryNew($data, $status = null, $ref = null)
    {
        $this->logger->notice(__METHOD__);
        $r = $this->itinerariesMaster->add()->rental();
        $bookNumber = $data->bookingInfo->bookingReference ?: $ref;
        $this->logger->info("[{$this->currentItin}] Parse Itinerary #{$bookNumber}", ['Header' => 3]);
        $this->currentItin++;

//        $r->ota()
//            ->confirmation($bookNumber, 'Booking number', true);

        $confNo = $data->bookingInfo->bookingReference;

        if ($confNo) {
            $r->general()->confirmation($confNo, 'Confirmation', true);
        } else {
            $r->general()->noConfirmation();
        }
        $r->general()
            ->status(beautifulName($data->bookingInfo->statusDescription));

        if (stripos($data->bookingInfo->statusDescription, 'Cancelled') !== false) {
            $r->general()->cancelled();
        }
        $r->general()->traveller("{$data->bookingInfo->firstName} {$data->bookingInfo->lastName}");

        $r->pickup()->date2($data->bookingInfo->pickUpDateTime);
        $r->dropoff()->date2($data->bookingInfo->dropOffDateTime);

        if (empty($data->bookingInfo->pickUpLocation)) {
            $data->bookingInfo->pickUpLocation = $data->bookingInfo->pickUpDepot->locationType;
        }

        if (empty($data->bookingInfo->dropOffLocation)) {
            $data->bookingInfo->dropOffLocation = $data->bookingInfo->dropOffDepot->locationType;
        }
        $r->pickup()->location($data->bookingInfo->pickUpLocation);
        $r->dropoff()->location($data->bookingInfo->dropOffLocation);
        $r->pickup()->detailed()->address($data->bookingInfo->pickUpDepot->address);
        $r->dropoff()->detailed()->address($data->bookingInfo->dropOffDepot->address);

        $r->pickup()->phone($data->bookingInfo->pickUpDepot->phoneNumber);
        $r->dropoff()->phone($data->bookingInfo->dropOffDepot->phoneNumber);

        $r->extra()->company($data->bookingInfo->suppliersName);

        // $r->car()->type($data->pickUpDepot->phoneNumber);
        $r->car()->model($data->bookingInfo->makeAndModel);
        //$r->car()->image($data->pickUpDepot->phoneNumber);

        $r->price()->currency($data->bookingInfo->vehicle->price->payableNow->currency);
        $r->price()->total($data->bookingInfo->vehicle->price->payableNow->value);

        $this->logger->debug('Parsed itinerary:');
        $this->logger->debug(var_export($r->toArray(), true), ['pre' => true]);
    }

    private function parseItinerary($status = null, $ref = null)
    {
        $this->logger->notice(__METHOD__);
        $r = $this->itinerariesMaster->add()->rental();
        $bookNumber =
            $this->http->FindSingleNode('//div[@id = "primary-content"]//p[contains(text(), "Booking number:")]/strong')
            ?: $this->http->FindSingleNode('//div[contains(text(), "Your reference number is:")]/strong')
            ?: $this->http->FindSingleNode('//p[contains(text(), "Booking number:")]/following-sibling::div[1]/p[1]')
            ?: $ref
        ;
        $this->logger->info("[{$this->currentItin}] Parse Itinerary #{$bookNumber}", ['Header' => 3]);
        $this->currentItin++;

        $r->ota()
            ->confirmation($bookNumber, 'Booking number', true);

        $confNo = $this->http->FindSingleNode('//div[contains(text(), "Confirmation:")]/strong'); // Your booking has been cancelled

        if ($confNo) {
            $r->general()->confirmation($confNo, 'Confirmation', true);
        } else {
            $r->general()->noConfirmation();
        }

        $r->general()
            ->status(beautifulName($status));

        if (stripos($status, 'cancelled') !== false) {
            $r->general()->cancelled();
        }
        $pickUpDateTime = (
            $this->http->FindSingleNode('//div[@id = "primary-content"]//div[@class = "summary__locationPickUp"]//span[@class = "summary__locationDateTime"]')
            ?: $this->http->FindSingleNode('//td[contains(text(), "Pick-Up:")]/following-sibling::td[1]')
            ?: $this->normalizeDate($this->http->FindSingleNode("//button[contains(text(),'View pick-up instructions')]/preceding-sibling::div[1]"))
        );

        if (is_numeric($pickUpDateTime)) {
            $r->pickup()->date($pickUpDateTime);
        } elseif ($pickUpDateTime = strtotime($pickUpDateTime)) {
            $r->pickup()->date($pickUpDateTime);
        }

        $pickupLoc1 = $this->http->FindSingleNode('//div[@id = "primary-content"]//div[@class = "summary__pickUp"]');
        $pickupLoc2 = $this->http->FindSingleNode('//div[@id = "summary__moreDetailsContainer-pickUp"]//small[contains(@class, "summary__locationPlace")]');

        if ($pickupLoc1 && $pickupLoc2) {
            $pickupLoc = "{$pickupLoc1} {$pickupLoc2}";
        } else {
            $pickupLoc = $this->http->FindSingleNode('//td[contains(text(), "Pick-Up Location:")]/following-sibling::td[1]')
                ?: $this->http->FindSingleNode("//button[contains(text(),'View pick-up instructions')]/preceding-sibling::p[1]", null, false, '/^.{13,}$/');
        }

        if ($pickupLoc) {
            $r->pickup()->location($pickupLoc);
        }
        $pickupPhone = $this->http->FindSingleNode('//div[@id = "summary__moreDetailsContainer-pickUp"]//small[contains(@class, "summary__location-phone-ltr")]');
        $r->pickup()->phone($pickupPhone, false, true);

        $dropOffDateTime = (
            $this->http->FindSingleNode('//div[@id = "primary-content"]//div[contains(@class, "summary__locationDropOff")]//span[@class = "summary__locationDateTime"]')
            ?: $this->http->FindSingleNode('//td[contains(text(), "Drop-Off:")]/following-sibling::td[1]')
            ?: $this->normalizeDate($this->http->FindSingleNode("//button[contains(text(),'View drop-off instructions')]/preceding-sibling::div[1]"))
        );

        if (is_numeric($dropOffDateTime)) {
            $r->dropoff()->date($dropOffDateTime);
        } elseif ($dropOffDateTime = strtotime($dropOffDateTime)) {
            $r->dropoff()->date($dropOffDateTime);
        }

        $dropoffLoc1 = $this->http->FindSingleNode('//div[@id = "primary-content"]//div[@class = "summary__dropOff"]');
        $dropoffLoc2 = $this->http->FindSingleNode('//div[@id = "summary__moreDetailsContainer-dropOff"]//small[contains(@class, "summary__locationPlace")]');

        if ($dropoffLoc1 && $dropoffLoc2) {
            $dropoffLoc = "{$dropoffLoc1} {$dropoffLoc2}";
        } else {
            $dropoffLoc = $this->http->FindSingleNode('//td[contains(text(), "Drop-Off Location:")]/following-sibling::td[1]')
                ?: $this->http->FindSingleNode("//button[contains(text(),'View drop-off instructions')]/preceding-sibling::p[1]", null, false, '/^.{13,}$/');
        }

        if ($dropoffLoc) {
            $r->dropoff()->location($dropoffLoc);
        }
        $dropoffPhone = $this->http->FindSingleNode('//div[@id = "summary__moreDetailsContainer-dropOff"]//small[contains(@class, "summary__location-phone-ltr")]');
        $r->dropoff()->phone($dropoffPhone, false, true);

        $carModel1 = (
            $this->http->FindSingleNode('//h2[@id = "myres-carcard-summary-carModel"]/text()[1]')
            ?: $this->http->FindSingleNode('//div[@class = "summary_car_name"]')
        );
        $carModel2 = $this->http->FindSingleNode('//small[@id = "myres-carcard-summary-carModel-caveat"]');
        $carModel = (
            Html::cleanXMLValue("{$carModel1} {$carModel2}")
                ?: $this->http->FindSingleNode('//span[normalize-space(text()) = "or similar"]/ancestor::p[1]')
                ?: $this->http->FindSingleNode('//span[normalize-space(text()) = "or similar"]/preceding-sibling::div[1]')
        );
        $r->car()->model($carModel, false, true);
        $carType = (
            $this->http->FindSingleNode('//small[@id="myres-carcard-summary-carcategory"]')
            ?: $this->http->FindSingleNode('//td[contains(text(), "Car Type:")]/following-sibling::td[1]')
        );
        $r->car()->type($carType, false, true);
        $carImageUrl = (
            $this->http->FindSingleNode('//div[@id = "myres-carcard-summary-imageholder"]/img[1]/@src')
                ?: $this->http->FindSingleNode('//div[@class = "summary_car_img"]/img/@src')
                ?: $this->http->FindSingleNode('//img[@class = "package-card-car-image"]/@src')
        );

        if ($carImageUrl) {
            $this->http->NormalizeURL($carImageUrl);
            $r->car()->image($carImageUrl);
        }
        $carCompany = (
            $this->http->FindSingleNode('//div[@id="myres-carcard-summary-detailsholder"]//span[contains(@class,"summary__carSupplierName")]/strong')
            ?: $this->http->FindSingleNode('//td[contains(text(), "Rental Partner:")]/following-sibling::td[1]')
        );
        $r->extra()->company($carCompany, false, true);
        $traveller = trim(beautifulName(
            $this->http->FindSingleNode('//div[@id = "primary-content"]/div[5]//div[contains(@class, "summary__driver")]//strong')
                ?: $this->http->FindSingleNode('//td[contains(text(), "Driver:")]/following-sibling::td[1]')
                ?: $this->http->FindSingleNode("//p[contains(text(), 'Main driver')]/following-sibling::div[1]//p[contains(@class,'bui-f-font-strong')]")
        ));

        if ($traveller) {
            $r->general()->traveller($traveller, true);
        }

        $pickupHours = $this->http->XPath->query('//div[@id = "summary__moreDetailsContainer-pickUp"]//div[4]//li');
        $this->logger->debug("Total {$pickupHours->length} pickup hours nodes found");

        if ($pickupHours->length == 1) {
            $r->pickup()->openingHours($this->http->FindSingleNode("span", $pickupHours->item(0)));
        } else {
            $pHours = [];

            foreach ($pickupHours as $pickupHour) {
                $pHours[] = $this->http->FindSingleNode("strong", $pickupHour) . ": " . $this->http->FindSingleNode("span", $pickupHour);
            }
            $r->pickup()->openingHours(implode("; ", $pHours) ?: null, false, true);
        }

        $dropoffHours = $this->http->XPath->query('//div[@id = "summary__moreDetailsContainer-dropOff"]//div[4]//li');
        $this->logger->debug("Total {$dropoffHours->length} dropOff hours nodes found");

        if ($dropoffHours->length == 1) {
            $r->dropoff()->openingHours($this->http->FindSingleNode("span", $dropoffHours->item(0)));
        } else {
            $dHours = [];

            foreach ($dropoffHours as $dropoffHour) {
                $dHours[] = $this->http->FindSingleNode("strong", $dropoffHour) . ": " . $this->http->FindSingleNode("span", $dropoffHour);
            }
            $r->dropoff()->openingHours(implode("; ", $dHours) ?: null, false, true);
        }

        $nodesTableMoney = $this->http->XPath->query('//div[@id="primary-content"]//div[contains(@class,"my-res-layout__section-panel priceBreakdownWrapper")]//div[contains(@class, "table m") and position()=1]//div[@class="table-row"]');
        $this->logger->debug("Total {$nodesTableMoney->length} prices were found");

        if ($nodesTableMoney->length == 0) {
            $nodesTableMoney = $this->http->XPath->query('//div[@id="primary-content"]//div[contains(@class,"my-res-layout__section-panel priceBreakdownWrapper")]//div[contains(@class, "table m") and position()=2]//div[@class="table-row"]');
            $this->logger->debug("Total {$nodesTableMoney->length} prices were found");
        }

        if ($nodesTableMoney->length == 0) {
            $nodesTableMoney = $this->http->XPath->query('//div[contains(@class, "charges")]/table[contains(@class, "striped")]//tr[td]');
            $this->logger->debug("Total {$nodesTableMoney->length} prices were found (cancelled itinerary)");
        }
        $properties = [];
        $valueRegExp = "/[A-Z$]*([^€£₩\(]+)/";
        $valueRegExpBrackets = "/\([A-Z$]*\s*([\d.,\s]+)\)/";
        $currencyRegExp = "/(?:^[A-Z]{3}|[A-Z]{2}\\$|€|£|₩)/";
        $specialCurrency = [
            'EUR',
            'BRL',
        ];

        foreach ($nodesTableMoney as $node) {
            $name = $this->http->FindSingleNode('.//p[1]/strong', $node);
            $value = $this->http->FindSingleNode('.//p[2]/strong/text()[1]', $node, true, $valueRegExp);
            $currency = $this->currency($this->http->FindSingleNode('.//p[2]/strong/span', $node));

            if (!$currency) {
                $currency = $this->currency($this->http->FindSingleNode('.//p[2]/strong', $node, true, $currencyRegExp));
            }

            if (!$name && !$value) {
                $name = $this->http->FindSingleNode('./strong', $node);
                $value = $this->http->FindSingleNode('./p', $node, true, $valueRegExp);
                $currency = $this->currency($this->http->FindSingleNode('./p', $node, true, $currencyRegExp));
            }
            // cancelled itinerary
            if (!$name && !$value) {
                $name = str_replace(':', '', $this->http->FindSingleNode('td[1]', $node));
                $value = (
                    $this->http->FindSingleNode('td[2]', $node, true, $valueRegExpBrackets)
                    ?: $this->http->FindSingleNode('td[2]', $node, true, $valueRegExp)
                );
                $currency = $this->currency($this->http->FindSingleNode('td[2]', $node, true, $currencyRegExp));
            }
            $this->logger->debug("[$name]: {$value} [{$currency}]");

            if (strpos($name, 'Amount Paid') !== false || strpos($name, 'Payment Currency') !== false) {
                $this->logger->debug("skip -> {$name}");

                continue;
            }

            if (in_array($name, ['Total', 'Total for your rental', 'Amount Due at Pick-up'])) {
                $r->price()->currency($currency);

                if (in_array($currency, $specialCurrency)) {
                    $r->price()->total(PriceHelper::cost($value, ".", ","));
                } else {
                    $r->price()->total(PriceHelper::cost($value));
                }

                continue;
            }
            $properties[$name] = [
                'value'    => $value,
                'currency' => $currency,
            ];
        }

        foreach ($properties as $key => $value) {
            $this->logger->debug("[$key]: {$value['value']} {$value['currency']}");

            if ($r->obtainPrice()->getCurrencyCode() != $value['currency']) {
                $this->logger->notice("skip value in other currency");

                continue;
            }

            if ($value['value'] == 0) {
                $this->logger->notice("skip zero value  ");

                continue;
            }
            // cancelled itinerary
            if (strpos($key, 'Daily Rate (') !== false) {
                $r->price()->cost(PriceHelper::cost($value['value']));

                continue;
            }

            if (in_array($value['currency'], $specialCurrency)) {
                $r->price()->fee($key, PriceHelper::cost($value['value'], ".", ","));

                continue;
            }
            $r->price()->fee($key, PriceHelper::cost($value['value']));
        }

        if ($nodesTableMoney->length == 0) {
            $total = $this->getTotalCurrency($this->http->FindSingleNode("//dt[contains(text(),'Car hire charge')]/following-sibling::dd[1]"));

            if (!empty($total['Total'])) {
                $r->price()->total($total['Total']);
                $r->price()->currency($total['Currency']);
            }
        }

        $this->logger->debug('Parsed itinerary:');
        $this->logger->debug(var_export($r->toArray(), true), ['pre' => true]);
    }

    private function parseItineraryPre($root)
    {
        $imgUrl = $this->http->FindSingleNode(".//div[contains(@class,'booking-img')]/descendant::img[1]/@src", $root);
        $this->http->NormalizeURL($imgUrl);
        $it['carImageUrl'] = $imgUrl;
        $it['company'] = $this->http->FindSingleNode(".//div[contains(@class,'booking-img')]/descendant::img[2]/@title",
            $root);
        $it['carModel'] = $this->http->FindSingleNode(".//div[contains(@class,'booking-info')]/p/strong", $root);
        $it['carType'] = implode(',', array_map(function ($s) {return trim($s, ','); },
            $this->http->FindNodes(".//div[contains(@class,'booking-info')]/ul//text()[string-length(normalize-space())>2]", $root)));
        $it['puTime'] = $this->http->FindSingleNode(".//div[@class='pu']//p[last()]", $root);
        $it['doTime'] = $this->http->FindSingleNode(".//div[@class='do']//p[last()]", $root);
        $it['puLoc'] = implode(', ', $this->http->FindNodes(".//div[@class='pu']//p[position()!=last()]", $root));
        $it['doLoc'] = implode(', ', $this->http->FindNodes(".//div[@class='do']//p[position()!=last()]", $root));

        return $it;
    }

    private function getTotalCurrency($node)
    {
        $node = str_replace(["€", "£", "₹"], ["EUR", "GBP", "INR"], $node);
        $tot = '';
        $cur = '';

        if (preg_match("#(?<c>[A-Z]{3})\s*(?<t>\d[.\d,\s]*\d*)#", $node, $m)
            || preg_match("#^(?<c>[^\s\d])\s*(?<t>\d[.\d,\s]*\d*)$#", trim($node), $m)
        ) {
            $cur = $m['c'];
            $tot = PriceHelper::cost($m['t']);
        }

        return ['Total' => $tot, 'Currency' => $cur];
    }

    private function normalizeDate($date)
    {
        $in = [
            // Fri, Dec 11 · 1:00 PM
            '#^(\w{3}), (\w+ \d+)\s*·\s*(\d+:\d+(?:\s*[AP]M)?)$#',
        ];
        $out = [
            '$2, $3',
        ];
        $outWeek = [
            '$1',
        ];

        if (preg_match('#^[A-z]{3}$#', $week = preg_replace($in, $outWeek, $date))) {
            $weekNum = WeekTranslate::number1(WeekTranslate::translate($week, 'en'));
            $str = $this->dateStringToEnglish(preg_replace($in, $out, $date));
            $str = EmailDateHelper::parseDateUsingWeekDay($str, $weekNum);
        } else {
            $str = strtotime($this->dateStringToEnglish(preg_replace($in, $out, $date)));
        }

        return $str;
    }

    private function currency($currency)
    {
        if (strpos($currency, 'US$') !== false) {
            $currency = 'USD';
        }

        if (strpos($currency, '€') !== false) {
            $currency = 'EUR';
        }

        if (strpos($currency, '£') !== false) {
            $currency = 'GBP';
        }

        if (strpos($currency, 'R$') !== false) {
            $currency = 'BRL';
        }

        if (strpos($currency, '₩') !== false) {
            $currency = $this->http->FindSingleNode("//div[strong[contains(text(), 'Please note')] and contains(., 'You’ll still pay for your car in ')]", null, true, "/in ([A-Z]{3})\./");
        }

        return $currency;
    }

    private function checkCookies()
    {
        $this->logger->notice(__METHOD__);

        $selenium = clone $this;
        $retry = false;
        $this->http->brotherBrowser($selenium->http);

        try {
            $this->logger->notice("Running Selenium...");
            $selenium->UseSelenium();

            if ($this->attempt == 1) {
                $selenium->useFirefox(SeleniumFinderRequest::FIREFOX_59);
            } else {
//                $selenium->useFirefox(SeleniumFinderRequest::FIREFOX_84);
//                $selenium->useGoogleChrome(\SeleniumFinderRequest::CHROME_95);
//                $selenium->seleniumOptions->addHideSeleniumExtension = false;

                $selenium->useChromePuppeteer();
                $selenium->setProxyMount();
                $selenium->seleniumOptions->addHideSeleniumExtension = false;
                $selenium->seleniumOptions->userAgent = null;
            }
//            $selenium->disableImages();
//            $selenium->useCache();
            $selenium->http->start();
            $selenium->Start();
            $selenium->http->saveScreenshots = true;

            $selenium->http->removeCookies();
            $selenium->http->GetURL('https://secure.rentalcars.com');
            sleep(rand(3, 10));
            $selenium->http->GetURL('https://secure.rentalcars.com/CRMLogin.do');

            $selenium->waitForElement(WebDriverBy::xpath('//input[@id = "loyaltySignUpEmail"] | //div[contains(text(), "This check is taking longer than expected.")] | //div[not(contains(@style, "display: none;")) and @class="hcaptcha-box"]//a[contains(text(), "Proxy IP is banned by target service") or contains(text(), "Could not connect to proxy related to the task")] | //input[@value = "Verify you are human"] | //p[contains(text(), "After completing the CAPTCHA below, you will immediately regain access to the site again.")] | //p[@id = "extraUnblock"]'));
            $this->savePageToLogs($selenium);

            if ($this->http->FindSingleNode('//p[contains(text(), "After completing the CAPTCHA below, you will immediately regain access to the site again.")]')) {
                $captcha = $this->parseCaptcha();
                $selenium->driver->executeScript('solvedCaptcha("' . $captcha . '");');

                $res = $selenium->waitForElement(WebDriverBy::xpath('//input[@id = "loyaltySignUpEmail"]'), 20);
                $this->savePageToLogs($selenium);

                if ($res) {
                    $this->captchaReporting($this->recognizer);
                } else {
                    $this->captchaReporting($this->recognizer, false);
                }
            }

            $cookies = $selenium->driver->manage()->getCookies();

            foreach ($cookies as $cookie) {
                $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
            }

            $this->savePageToLogs($selenium);
            $this->logger->debug("[Current URL]: {$selenium->http->currentUrl()}");

            if ($this->http->FindSingleNode('//li[contains(text(), "You\'re a power user moving through this website with super-human speed.")]')) {
                $this->markProxyAsInvalid();
                $retry = true;
            }
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
                throw new CheckRetryNeededException(2, 0);
            }
        }

        return true;
    }
}
