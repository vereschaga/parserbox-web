<?php

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Engine\ProxyList;
use AwardWallet\Engine\WeekTranslate;

class TAccountCheckerTcase extends TAccountCheckerExtended
{
    use ProxyList;

    private $cruiseConverter;
    private $currentItin = 0;
    private $airsWithHDG = [];

    private $profileLanguage = '';

    private $hotelsHash = [];

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->SetProxy($this->proxyDOP());
    }

    public function IsLoggedIn()
    {
        $this->http->RetryCount = 0;
        $this->http->GetURL("https://www.tripcase.com/web2/trips", [], 20);
        $this->http->RetryCount = 2;

        if ($this->loginSuccessful()) {
            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        $this->logger->notice(__METHOD__);
        $this->http->removeCookies();
        $this->http->GetURL("https://www.tripcase.com/login");

        if (!$this->http->ParseForm(null, 1, true, "//form[contains(@action, '/sessions')]")) {
            return $this->checkErrors();
        }
        $this->http->SetInputValue("email", $this->AccountFields['Login']);
        $this->http->SetInputValue("password", $this->AccountFields['Pass']);
        $this->http->SetInputValue("commit", "Sign in");

        return true;
    }

    public function Login()
    {
        $this->logger->notice(__METHOD__);
        $formURL = $this->http->FormURL;
        $form = $this->http->Form;

        if (!$this->http->PostForm()) {
            return $this->checkErrors();
        }

        $retry = 0;

        while ($retry < 3 && $this->incapsula()) {
            $this->logger->notice("[Retry]: {$retry}");
            $retry++;
            $this->http->FormURL = $formURL;
            $this->http->Form = $form;

            if (!$this->http->PostForm()) {
                return $this->checkErrors();
            }
        }// while ($retry < 3 && $this->incapsula())

        if ($this->loginSuccessful()) {
            return true;
        }

        // Incorrect email and/or password.
        if ($message = $this->http->FindSingleNode("//li[contains(text(), 'Incorrect email and/or password.')]")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        $this->logger->notice(__METHOD__);

        // User Agreement
        if (strstr($this->http->currentUrl(), 'https://www.tripcase.com/users/terms') && $this->http->FindSingleNode('//h2[contains(text(), "User Agreement")]')) {
            $this->throwAcceptTermsMessageException();
        }

        // Name
        $this->SetProperty("Name", beautifulName($this->http->FindSingleNode("//a[@id = 'subnav-name']/text()[1]")));

        if (!empty($this->Properties['Name'])) {
            $this->SetBalanceNA();
        }
    }

    public function ParseItineraries()
    {
        $this->logger->notice(__METHOD__);

        try {
            $this->setEnglishLanguage();

            $result = [];
            // no Itineraries
            if ($this->http->FindNodes("//h2[contains(text(), 'You have no upcoming trips')]")
                    || $this->http->FindNodes('//h2[contains(text(), "No hay vuelos próximos")]')
                    || $this->http->FindNodes('//h2[contains(text(), "Vous n\'avez aucun voyage prévu")]')
                    || $this->http->FindNodes('//h2[contains(text(), "Você não tem nenhuma viagem futura")]')) {
                return $this->noItinerariesArr();
            }
            $this->printCookies();

            $trips = array_unique($this->http->FindNodes("//h3/a[contains(@href, 'itinerary')]/@href"));
            $tripsCount = count($trips);
            $this->logger->debug("Total {$tripsCount} trips were found");

            for ($i = 0; $i < $tripsCount; $i++) {
                $this->logger->notice("Open trip #{$i} -> {$trips[$i]}");
                $this->http->NormalizeURL($trips[$i]);
                $this->http->GetURL($trips[$i]);
                // TODO: Sometimes the language changes, we try to fix it so that it is English
                if (!$this->http->FindSingleNode("//a[contains(@class,'print-itinerary') and contains(.,'Print Itinerary')]")) {
                    $this->sendNotification('language changes // MI');
                    $this->setEnglishLanguage();
                }
                $result = array_merge($result, $this->ParseTrip());
            }// for ($i = 0; $i < $tripsCount; $i++)
        } finally {
            $this->restoreLanguage();
        }

        if (ConfigValue(CONFIG_SITE_STATE) === SITE_STATE_DEBUG) {
            $this->logger->info('Check Itineraries', ['Header' => 3]);
            $this->logger->info($this->checkItineraries($result, true));
        }

        return $result;
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);

        if ($this->http->FindSingleNode("//a[contains(@href, 'logout')]/@href")) {
            return true;
        }

        return false;
    }

    private function printCookies()
    {
        $cookies = array_merge(
            $this->http->GetCookies('www.tripcase.com', '/', false),
            $this->http->GetCookies('www.tripcase.com', '/', true)
        );
        $this->logger->info('cookies:');
        $this->logger->info(var_export($cookies, true));
    }

    private function incapsula()
    {
        $this->logger->notice(__METHOD__);
        $referer = $this->http->currentUrl();
        $incapsula = $this->http->FindSingleNode("//iframe[contains(@src, '/_Incapsula_Resource?')]/@src");

        if (isset($incapsula)) {
            sleep(2);
            $this->http->NormalizeURL($incapsula);
            $this->http->RetryCount = 0;
            $this->http->FilterHTML = false;
            $this->http->GetURL($incapsula);
            $this->http->RetryCount = 2;
        }// if (isset($distil))
        $this->logger->debug("parse captcha form");
        $action = $this->http->FindPreg("/xhr.open\(\"POST\", \"([^\"]+)/");

        if (!$action) {
            return false;
        }
        $captcha = $this->parseReCaptcha();

        if ($captcha === false) {
            return false;
        }
        $this->http->RetryCount = 0;
        $this->http->PostURL('https://www.tripcase.com' . $action, ['g-recaptcha-response' => $captcha], ["Referer" => $referer, "Content-Type" => "application/x-www-form-urlencoded"]);
        $this->http->RetryCount = 2;
        $this->http->FilterHTML = true;
        sleep(2);

        return true;
    }

    private function parseReCaptcha()
    {
        $this->logger->notice(__METHOD__);
        $key = $this->http->FindSingleNode("//div[@class = 'g-recaptcha']/@data-sitekey");
        $this->logger->debug("data-sitekey: {$key}");

        if (!$key) {
            return false;
        }
        $recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_RUCAPTCHA);
        $recognizer->RecognizeTimeout = 120;
        $parameters = [
            "pageurl" => $this->http->currentUrl(),
            "proxy"   => $this->http->GetProxy(),
        ];
        $captcha = $this->recognizeByRuCaptcha($recognizer, $key, $parameters);

        return $captcha;
    }

    private function checkErrors()
    {
        // Service Temporarily Unavailable
        if ($message = $this->http->FindSingleNode("//h1[contains(text(), 'Service Temporarily Unavailable')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    private function setEnglishLanguage()
    {
        $this->logger->notice(__METHOD__);
        $currentUrl = $this->http->currentUrl();
        $this->http->GetURL('https://www.tripcase.com/users/settings');
        $this->profileLanguage = $this->http->FindSingleNode('//*[@id = "user_settings_language_settings_preferred_language"]/option[@selected = "selected"]/@value');

        $this->http->ParseForm('new_user_settings_language_settings');
        $this->http->SetInputValue('user_settings_language_settings[preferred_language]', 'en-US');
        $this->http->PostForm();

        if ($this->http->FindNodes('//img[@src = "/assets/web2/images/flash-icon/icon_check.png"]')) {
            $this->logger->info('Successfully changed profile language to English.');
        } else {
            $this->logger->info('Failed to change profile language to English.');
        }

        $this->http->GetURL($currentUrl);
    }

    private function restoreLanguage()
    {
        $this->logger->notice(__METHOD__);

        $currentUrl = $this->http->currentUrl();
        $this->http->GetURL('https://www.tripcase.com/users/settings');

        $this->http->ParseForm('new_user_settings_language_settings');

        if (!$this->profileLanguage) {
            $this->logger->error('No profile language saved');

            return;
        }
        $this->http->SetInputValue('user_settings_language_settings[preferred_language]', $this->profileLanguage);
        $this->http->PostForm();

        if ($this->http->FindNodes('//img[@src = "/assets/web2/images/flash-icon/icon_check.png"]')) {
            $this->logger->info('Successfully restored profile language.');
        } else {
            $this->logger->info('Failed to restore profile language.');
        }

        $this->http->GetURL($currentUrl);
    }

    private function ParseTrip()
    {
        $this->logger->notice(__METHOD__);
        $this->cruiseConverter = new CruiseSegmentsConverter();
        $result = [];
        $tripId = $this->http->FindPreg('/\/(\d+)\/itinerary/', false, $this->http->currentUrl());
        $this->logger->info(sprintf('Parse Trip #%s', $tripId), ['Header' => 3]);

        //## CARS ###
        $cars = $this->http->XPath->query("//li[contains(@class, 'itinerary-item-li') and (contains(@class, 'vehicle') or contains(@class, 'ground_transportation'))]");
        $this->logger->debug("Total " . ($cars->length / 2) . " rentals were found");
        $carNumbers = [];

        for ($i = 0; $i < $cars->length; $i++) {
            $confNo = $this->http->FindSingleNode(".//p[contains(text(), 'Confirmation Number:') or contains(text(), 'Número de confirmación:')]", $cars->item($i), true, "/:\s*([^<]+)/ims");

            if (!empty($confNo)) {
                $confNo = preg_replace('/#\s*(.+)/', '$1', $confNo);
            }

            if (in_array($confNo, $carNumbers) && $confNo != 'Not Entered' && $confNo != 'No ingresado') {
                $this->logger->notice("Skip duplicate -> $confNo");

                continue;
            }// if (in_array($confNo, $carNumbers) && $confNo != 'Not Entered' && $confNo != 'No ingresado')

            if (strstr($confNo, ' GOLD')) {
                $confNo = str_replace(' GOLD', '', $confNo);
            }
            $carNumbers[] = $confNo;
            $result[] = $this->ParseCar($confNo, $cars->item($i));
        }// for ($i = 0; $i < $cars->length; $i++)

        //## HOTELS ###
        $hotels = $this->http->XPath->query("//li[contains(@class, 'itinerary-item-li hotel') or contains(@class, 'itinerary-item-li lodging')]");
        $this->logger->debug("Total " . ($hotels->length / 2) . " reservations were found");
        $hotelNumbers = [];

        for ($i = 0; $i < $hotels->length; $i++) {
            // Example: Confirmation Number:onPeak Group ID: 3677514
            $confNo = $this->http->FindSingleNode(".//p[contains(text(), 'Confirmation Number:') or contains(text(), 'Número de confirmación:')]", $hotels->item($i), true, "/:\s*\#?([^<]+)$/ims");

            if (in_array($confNo, $hotelNumbers) && $confNo != 'Not Entered' && $confNo != 'No ingresado') {
                $this->logger->notice("Skip duplicate -> $confNo");

                continue;
            }// if (in_array($confNo, $hotelNumbers) && $confNo != 'Not Entered' && $confNo != 'No ingresado')
            $hotelNumbers[] = preg_replace('/,\s*\d{7,}$/', '', $confNo);
            $hotel = $this->ParseHotel($confNo, $hotels->item($i));
            // do not collect duplicates
            if (!empty($hotel)) {
                $result[] = $hotel;
            }
        }// for ($i = 0; $i < $hotels->length; $i++)

        //## MEETINGS ###
        $meetings = $this->http->XPath->query("//li[contains(@class, 'itinerary-item-li meeting') or contains(@class, 'itinerary-item-li food_drink') or contains(@class, 'itinerary-item-li activity')]");
        $this->logger->debug("Total {$meetings->length} meetings were found");
        $meetingNumbers = [];

        for ($i = 0; $i < $meetings->length; $i++) {
            $confNo = $this->http->FindSingleNode(".//p[contains(text(), 'Confirmation Number:') or contains(text(), 'Número de confirmación:')]", $meetings->item($i), true, "/:\s*([^<]+)/ims");

            if (!preg_match('/^[\w\-\/\\\.?]+$/', $confNo)) {
                $this->logger->notice("Skip did not match -> $confNo");
                $confNo = 'Not Entered';
            }

            if (in_array($confNo, $meetingNumbers) && $confNo != 'Not Entered' && $confNo != 'No ingresado') {
                $this->logger->notice("Skip duplicate -> $confNo");

                continue;
            }// if (in_array($confNo, $meetingNumbers) && $confNo != 'Not Entered' && $confNo != 'No ingresado')

            $meetingNumbers[] = $confNo;
            $result[] = $this->ParseMeeting($confNo, $meetings->item($i), $meetings);
        }// for ($i = 0; $i < $meetings->length; $i++)

        //## TRAINS ###
        $trains = $this->http->XPath->query("//li[contains(@class, 'itinerary-item-li rail')]");
        $this->logger->debug("Total {$trains->length} rail were found");
        $trainNumbers = [];

        for ($i = 0; $i < $trains->length; $i++) {
            $confNo = $this->http->FindSingleNode(".//p[contains(text(), 'Confirmation Number:') or contains(text(), 'Número de confirmación:')]", $trains->item($i), true, "/:\s*([^<]+)/ims");

            if (in_array($confNo, $trainNumbers) && $confNo != 'Not Entered' && $confNo != 'No ingresado') {
                $this->logger->notice("Skip duplicate -> $confNo");

                continue;
            }// if (in_array($confNo, $trainNumbers) && $confNo != 'Not Entered' && $confNo != 'No ingresado')
            $trainNumbers[] = $confNo;
            $result[] = $this->ParseTrain($confNo, $trains->item($i));
        }// for ($i = 0; $i < $trains->length; $i++)

        //## CRUISES ###
        $cruises = $this->http->XPath->query("//li[contains(@class, 'itinerary-item-li cruise') or contains(@class, 'itinerary-item-li ferry')]");
        $this->logger->debug("Total {$cruises->length} cruises were found");
        $cruiseNumbers = [];

        for ($i = 0; $i < $cruises->length; $i++) {
            $confNo = $this->http->FindSingleNode(".//p[contains(text(), 'Confirmation Number:') or contains(text(), 'Número de confirmación:')]", $cruises->item($i), true, "/:\s*([^<]+)/ims");

            if (in_array($confNo, $cruiseNumbers) && $confNo != 'Not Entered' && $confNo != 'No ingresado') {
                $this->logger->notice("Skip duplicate -> $confNo");

                continue;
            }// if (in_array($confNo, $trainNumbers) && $confNo != 'Not Entered' && $confNo != 'No ingresado')
            $cruiseNumbers[] = $confNo;
            $result[] = $this->ParseCruise($confNo, $cruises->item($i));
        }// for ($i = 0; $i < $cruises->length; $i++)

        //## FLIGHTS ###
        $itineraries = $this->http->XPath->query("//li[contains(@class, 'itinerary-item-li air')]");
        $this->logger->debug("Total {$itineraries->length} segments were found");
        $airTrips = [];
        $confSet = [];
        $this->airsWithHDG = [];

        for ($i = 0; $i < $itineraries->length; $i++) {
            $confNo = $this->http->FindSingleNode(".//p[contains(text(), 'Confirmation Number:') or contains(text(), 'Número de confirmación:')]", $itineraries->item($i), true, "/:\s*([^<]+)/ims");
            $this->logger->debug("[Conf #]: {$confNo}");

            if ($confNo === 'COVID19') {
                $this->logger->error("Skipping flight: {$confNo}");

                continue;
            }

            if ($confNo) {
                // ConfirmationNumber
                $airTrips[$confNo]["Kind"] = "T";
                // ConfirmationNumber
                if (in_array($confNo, [
                    'Not Entered',
                    'No ingresado',
                    'RL', ]
                )) {
                    $airTrips[$confNo]['RecordLocator'] = CONFNO_UNKNOWN;
                } elseif (strstr($confNo, 'smiles/')) {
                    $this->logger->notice("tcase. Bad Conf # {$confNo}");
                    $airTrips[$confNo]['RecordLocator'] = $this->http->FindPreg('#smiles/[A-z]{2} (\w+)#', false, $confNo);
                    $this->logger->debug("[RecordLocator]: {$airTrips[$confNo]['RecordLocator']}");
                } else {
                    $airTrips[$confNo]['RecordLocator'] =
                        $this->http->FindPreg('/^\s*([\w]+)\s*\|/', false, $confNo) ?:
                        $this->http->FindPreg('/([A-z\d\-]{3,})/u', false, $confNo) ?:
                        $confNo;
                    $this->logger->debug("[RecordLocator]: {$airTrips[$confNo]['RecordLocator']}");
                }

                if (strstr($airTrips[$confNo]['RecordLocator'], 'and')) {
                    $this->logger->notice("tcase. Bad Conf # {$confNo}");
                    $airTrips[$confNo]['RecordLocator'] = preg_replace("/\s*and.+/", "", $airTrips[$confNo]['RecordLocator']);
                    $this->logger->debug("[RecordLocator]: {$airTrips[$confNo]['RecordLocator']}");
                }

                if (!isset($confSet[$confNo])) {
                    $confSet[$confNo] = $this->currentItin;
                    $this->logger->info("[$this->currentItin] Parse Flight #{$airTrips[$confNo]['RecordLocator']}", ['Header' => 4]);
                    $this->currentItin++;
                } else {
                    $this->logger->info("[{$confSet[$confNo]}] Parse Segment #{$airTrips[$confNo]['RecordLocator']}", ['Header' => 5]);
                }
                $segment = $this->ParseAirSegment($confNo, $itineraries->item($i));

                if (!empty($segment)) {
                    $airTrips[$confNo]['TripSegments'][] = $segment;
                }

                $this->logger->debug('Parsed Trip:');
                $this->logger->debug(var_export($airTrips[$confNo], true), ['pre' => true]);
            }// if ($confNo)
        }//  for ($i = 0; $i < $itineraries->length; $i++)

        if (!empty($airTrips)) {
            foreach ($airTrips as $confNo => $trip) {
                if (in_array($confNo, $this->airsWithHDG) && empty($trip['TripSegments'])) {
                    continue;
                }

                if (empty($trip['TripSegments'])) {
                    continue;
                }
                $result[] = $trip;
            }
        }

        // notifications
        $unknownItineraries = $this->http->XPath->query("//li[contains(@class, 'itinerary-item-li') and not(contains(@class, 'hotel')) and not(contains(@class, 'lodging')) and not(contains(@class, 'vehicle')) and not(contains(@class, 'air')) and not(contains(@class, 'meeting')) and not(contains(@class, 'food_drink')) and not(contains(@class, 'ground_transportation')) and not(contains(@class, 'activity')) and not(contains(@class, 'attraction')) and not(contains(@class, 'rail')) and not(contains(@class, 'cruise')) and not(contains(@class, 'ferry'))]");

        if ($unknownItineraries->length > 0) {
            $this->sendNotification("tcase - refs #11472. New itinerary type was found");
        }

        return $result;
    }

    private function ParseCruise($confNo, $node)
    {
        $this->logger->notice(__METHOD__ . " -> {$confNo}");
        $result = ["Kind" => "T"];
        // ConfirmationNumber
        if ($confNo == 'Not Entered' || $confNo == 'No ingresado') {
            $result['RecordLocator'] = CONFNO_UNKNOWN;
        } elseif ($conf = $this->http->FindPreg('/Priceline Cruises:\s*\d+\s*-\s*(\d+),/', false, $confNo)) {
            $result['RecordLocator'] = $conf;
        } elseif ($conf = $this->http->FindPreg('/Reference Number (\w+)/', false, $confNo)) {
            $result['RecordLocator'] = $conf;
        } elseif ($conf = $this->http->FindPreg('/RESERVATION NUMBER (\w+)/', false, $confNo)) {
            $result['RecordLocator'] = $conf;
        } elseif ($conf = $this->http->FindPreg('/^(\w+)\s+(?:and|\()/', false, $confNo)) {
            $result['RecordLocator'] = $conf;
        } else {
            $result['RecordLocator'] = preg_replace('/^#/', '', $confNo);
        }

        if (stristr($result['RecordLocator'], ', ')) {
            //$result['ConfirmationNumbers'] = $result['RecordLocator'];
            $result['RecordLocator'] = explode(', ', $result['RecordLocator'])[0];
        }

        if (stristr($result['RecordLocator'], ' - ')) {
            //$result['ConfirmationNumbers'] = $result['RecordLocator'];
            $result['RecordLocator'] = explode(' - ', $result['RecordLocator'])[0];
        }
        $result['RecordLocator'] = preg_replace('/\s+/', '', $result['RecordLocator']);

        $this->logger->info(sprintf('[%s] Parse Cruise #%s', $this->currentItin, $result['RecordLocator']), ['Header' => 4]);
        $this->currentItin++;
        $result['TripCategory'] = TRIP_CATEGORY_CRUISE;
        // CruiseName
        $result['CruiseName'] = $this->http->FindSingleNode(".//div[contains(@class, 'overview')]/h3", $node);
        // Segment
        $cruise = $segment = [];
        // DepName
        $segment['DepName'] = $this->http->FindSingleNode(".//p[contains(text(), 'Starting port')]/following::p[1]", $node);
        // ArrName
        $segment['ArrName'] = $this->http->FindSingleNode(".//p[contains(text(), 'Ending port')]/following::p[1]", $node);

        $date = $this->http->FindSingleNode("./ancestor::ul[@class = 'itinerary-list']/preceding-sibling::h2", $node, true, "/\,\s*([^<]+)/");
        $this->logger->debug("Date: {$date}");
        // DepDate
        $segment['DepDate'] = strtotime($date . ' ' . $this->http->FindSingleNode(".//tr[th[contains(text(), 'Start')]]/following-sibling::tr[1]/td[2]", $node));
        // ArrDate
        $year = $this->http->FindPreg("/\d{4}$/", false, $date);
        $segment['ArrDate'] = strtotime($this->http->FindSingleNode(".//tr[th[contains(text(), 'Date')]]/following-sibling::tr[1]/td[3]", $node) . ' ' . $year . ' ' . $this->http->FindSingleNode(".//tr[td[contains(text(), 'End')]]/following-sibling::tr[1]/td[2]", $node));
        $cruise[] = $segment;

//        $result['TripSegments'] = $this->converter->Convert($cruise);
        $result['TripSegments'] = $cruise;

        $this->logger->debug('Parsed Cruise:');
        $this->logger->debug(var_export($result, true), ['pre' => true]);

        return $result;
    }

    private function ParseMeeting($confNo, $node, $meetings)
    {
        $this->logger->notice(__METHOD__ . " -> {$confNo}");
        $result = ["Kind" => "E"];
        // ConfirmationNumber
        if (
            in_array($confNo, [
                'Not Entered',
                'No ingresado',
                'To be booked !!!',
                'need to confirm',
            ])
            || filter_var($confNo, FILTER_VALIDATE_URL, FILTER_FLAG_PATH_REQUIRED)
            || filter_var($confNo, FILTER_VALIDATE_EMAIL)
        ) {
            $result['ConfNo'] = CONFNO_UNKNOWN;
        } else {
            $result['ConfNo'] = $this->http->FindPreg('/^Nro\.\s+reserva\s+(.+)$/', false, $confNo) ?: preg_replace('/^VIA\s+-\s+/', '', $confNo);
        }
        $this->logger->info(sprintf('[%s] Parse Event #%s', $this->currentItin, $result['ConfNo']), ['Header' => 4]);
        $this->currentItin++;
        // Name
        $result['Name'] = $this->http->FindSingleNode(".//div[contains(@class, 'overview')]/h3", $node);

        if (empty($result['Name'])) {
            $result['Name'] = $this->http->FindSingleNode(".//div[contains(@class, 'overview')]/h3/following-sibling::p", $node);
        }

        if (empty($result['Name']) && $meetings->length == 1) {
            $result['Name'] = $this->http->FindSingleNode(".//h1/span[contains(@class, 'trip-name-text')]", $node);
        }

        // Address
        $result['Address'] = implode(', ', $this->http->FindNodes(".//p[contains(text(), 'Address')]/following-sibling::address/p", $node));
        // Phone
        $result['Phone'] = $this->http->FindSingleNode(".//dt[contains(text(), 'Phone')]/following-sibling::dd[1]", $node, true, "/([+\d\s()\-]+)/");
        // CheckInDate
        $date = $this->http->FindSingleNode("./ancestor::ul[@class = 'itinerary-list']/preceding-sibling::h2", $node, true, "/\,\s*([^<]+)/");
        $this->logger->debug("Date: {$date}");
        $startDateTime = $this->http->FindSingleNode(".//tr[th[contains(text(), 'Date')]]/following-sibling::tr[1]/td[2]", $node);
        $this->logger->debug("StartDate: " . $date . " " . $startDateTime);
        $result["StartDate"] = strtotime($date . " " . $startDateTime);

        $this->logger->debug('Parsed Event:');
        $this->logger->debug(var_export($result, true), ['pre' => true]);

        return $result;
    }

    private function ParseTrain($confNo, $node)
    {
        $this->logger->notice(__METHOD__ . " -> {$confNo}");
        $result = [
            'Kind'         => 'T',
            'TripCategory' => TRIP_CATEGORY_TRAIN,
        ];
        // RecordLocator
        if ($confNo == 'Not Entered' || $confNo == 'No ingresado') {
            $result['RecordLocator'] = CONFNO_UNKNOWN;
        } else {
            $result['RecordLocator'] = $confNo;
        }
        $result['RecordLocator'] = (
            $this->http->FindPreg('/\(PNR\)\s*:\s*([\w-]+)/', false, $result['RecordLocator']) ?:
            $this->http->FindPreg('/Ticket Code\s*:\s*([\w-]+)/', false, $result['RecordLocator']) ?:
            $result['RecordLocator']
        );
        $result['RecordLocator'] = str_replace(' ', '', $result['RecordLocator']);
        $this->logger->info(sprintf('[%s] Parse Train #%s', $this->currentItin, $result['RecordLocator']), ['Header' => 4]);
        $this->currentItin++;
        $segment = [];
        // FlightNumber
        $segment['FlightNumber'] = $this->http->FindSingleNode(".//div[contains(@class, 'overview')]/h3", $node, true, "/\s+(\d+)\s*$/");
        // AirlineName
        $segment['AirlineName'] = $this->http->FindSingleNode(".//div[contains(@class, 'overview')]/h3", $node, true, "/(.+)\s+(\d+)\s*$/");
        // DepName
        $segment['DepName'] = $this->http->FindSingleNode(".//p[contains(text(), 'Departure location')]/following::p[1]", $node);
        // DepCode
        $segment['DepCode'] = $this->http->FindSingleNode(".//p[contains(text(), 'Departure location')]/following::p[1]", $node, true, '/\(([A-Z]{3})\)/');

        if (empty($segment['DepCode'])) {
            $segment['DepCode'] = TRIP_CODE_UNKNOWN;
        }

        $date = $this->http->FindSingleNode("./ancestor::ul[@class = 'itinerary-list']/preceding-sibling::h2", $node, true, "/\,\s*([^<]+)/");
        $this->logger->debug("Date: {$date}");
        // DepDate
        $segment['DepDate'] = strtotime($date . ' ' . $this->http->FindSingleNode(".//tr[th[contains(text(), 'Start')]]/following-sibling::tr[1]/td[2]", $node));
        // ArrName
        $segment['ArrName'] = $this->http->FindSingleNode(".//p[contains(text(), 'Arrival location')]/following::p[1]", $node);
        // ArrCode
        $segment['ArrCode'] = $this->http->FindSingleNode(".//p[contains(text(), 'Arrival location')]/following::p[1]", $node, true, '/\(([A-Z]{3})\)/');

        if (empty($segment['ArrCode'])) {
            $segment['ArrCode'] = TRIP_CODE_UNKNOWN;
        }
        $segment['ArrDate'] = strtotime($date . ' ' . $this->http->FindSingleNode(".//tr[td[contains(text(), 'End')]]/following-sibling::tr[1]/td[2]", $node));

        $result['TripSegments'][] = $segment;

        return $result;
    }

    private function ParseHotel($confNo, $node): array
    {
        // Confirmation Number: +852 2588 1234
        if ($this->http->FindPreg('/^\+\d+ \d+ \d+$/', false, $confNo)) {
            $confNo = 'Not Entered';
        }
        // Confirmation Number: Confirmación: 2992745383 PIN: 7217
        if ($m = $this->http->FindPreg('/^Confirmación:\s*(\w+)\s*PIN:/', false, $confNo)) {
            $confNo = $m;
        } elseif ($m = $this->http->FindPreg('/^Numero da reserva\s*-\s*(\w+)\s*/', false, $confNo)) {
            $confNo = $m;
        } elseif ($m = $this->http->FindPreg('/^Reserva (\w+) despegar/', false, $confNo)) {
            $confNo = $m;
        }
        // Número de check-in 102-13814771 Confirmación: 1182601429084116706 BOOKING
        elseif ($m = $this->http->FindPreg('/^Número de check-in\s*(\w+)\s*/', false, $confNo)) {
            $confNo = $m;
        }
        // 90091691 Lou $ Bob, 88157134 & 88159208
        elseif ($m = $this->http->FindPreg('/^(\d+) \w+ /', false, $confNo)) {
            $confNo = $m;
        }
        $confNo = trim($confNo, ' -');
        $this->logger->notice(__METHOD__ . " -> {$confNo}");
        $nodeText = $node->nodeValue;
        $result = ["Kind" => "R"];
        // ConfirmationNumber
        if (
            in_array($confNo, ['Not Entered', 'No ingresado', 'No reservado aun', 'reservado por Randstad'])
            || $this->http->FindPreg('/[a-z]+[.][a-z]+/', false, $confNo)
            || $this->http->FindPreg('/\d+[.]\d+[.]\d+/', false, $confNo)
                // +7 123 456 789
            || $this->http->FindPreg('/\+\s*\d+\s+\d+\s+\d+/', false, $confNo)
            || mb_strlen($confNo) < 3
        ) {
            $result['ConfirmationNumber'] = CONFNO_UNKNOWN;
        } else {
            $result['ConfirmationNumber'] = preg_replace("/Orbitz Itinerary #|N° /", "", $confNo);
        }
        $result['ConfirmationNumber'] = (
            $this->http->FindPreg('/^([\w\-\s]+)(?:\s+|\.)/', false, $result['ConfirmationNumber'])
            ?: $result['ConfirmationNumber']
        );

        if (strstr($result['ConfirmationNumber'], 'pin')) {
            $result['ConfirmationNumber'] = preg_replace("/\. pin \d+$/", "", $result['ConfirmationNumber']);
        }
        // ConfirmationNumbers
        if ($this->http->FindPreg('/^\d+,\s*\d+,\s*\d+/', false, $result['ConfirmationNumber'])
        // RES021520-4023,RES021551-4023
        || $this->http->FindPreg('/^[\w\-]+,/', false, $result['ConfirmationNumber'])) {
            $confs = explode(',', $result['ConfirmationNumber']);
            $result['ConfirmationNumber'] = $confs[0];
            $result['ConfirmationNumbers'] = $confs;
        }
        $result['ConfirmationNumber'] = trim($result['ConfirmationNumber'], ' #-');
        $this->logger->info("[{$this->currentItin}] Parse Hotel #{$result['ConfirmationNumber']}", ['Header' => 4]);
        $this->currentItin++;
        // HotelName
        $result['HotelName'] = $this->http->FindSingleNode(".//div[contains(@class, 'overview')]/h3", $node);

        if (
            !$result['HotelName']
            && $this->http->FindSingleNode('.//div[@id = "notes-wrapper" and contains(text(), "Airbnb")]', $node)
        ) {
            $result['HotelName'] = 'Airbnb';
        }

        if (empty($result['HotelName']) && $result['ConfirmationNumber'] == CONFNO_UNKNOWN
            && $this->http->FindNodes(".//p[contains(text(), 'Address') or contains(text(), 'Dirección')]/following-sibling::address/p", $node)) {
            $this->logger->debug("Skip: hotel name empty");

            return [];
        }
        // CheckInDate
        $checkInDate = $this->http->FindSingleNode("./ancestor::ul[@class = 'itinerary-list']/preceding-sibling::h2", $node, true, "/\,\s*([^<]+)/");
        $checkInDate = en(preg_replace("#\s+de\s+#", " ", $checkInDate));
        $this->logger->debug("Date: {$checkInDate}");
        $checkInTime = preg_replace("#([ap])\.\s+m\.#", "$1m", $this->http->FindSingleNode(".//tr[th[contains(text(), 'Check-in') or contains(text(), 'Check in')]]/following-sibling::tr[1]/td[2]", $node));

        if (!$checkInTime) {
            $checkInTime = $this->http->FindPreg('/Start.+?(\d+:\d+\s*(?:PM|AM)?)/ims', false, $nodeText);
        }
        $this->logger->debug("CheckInDate: {$checkInDate} {$checkInTime}");
        $result["CheckInDate"] = strtotime("{$checkInDate} {$checkInTime}");
        // CheckOutDate
        $checkOutTime = (
            $this->http->FindSingleNode(".//tr[th[contains(text(), 'Check-out') or contains(text(), 'Check out')]]/following-sibling::tr[1]/td[2]", $node)
                ?: $this->http->FindSingleNode(".//tr[td[contains(text(), 'End')]]/following-sibling::tr[1]/td[2]", $node)
        );
        $checkOutDate = (
        $this->http->FindSingleNode(".//tr[th[contains(text(), 'Check-out') or contains(text(), 'Check out')]]/following-sibling::tr[1]/td[1]", $node, true, "/\\s*,([^<]+)/ims")
            ?: $this->http->FindSingleNode(".//tr[td[contains(text(), 'End')]]/following-sibling::tr[1]/td[1]", $node, true, "/\\s*,([^<]+)/ims")
        );
        $checkOutDate = preg_replace("#\s+de\s+#", " ", $checkOutDate);
        $checkOutWeek = (
            $this->http->FindSingleNode(".//tr[th[contains(text(), 'Check-out') or contains(text(), 'Check out')]]/following-sibling::tr[1]/td[1]", $node, true, "/(\w+)[.\s]*, \w+ \d+/ims")
                ?: $this->http->FindSingleNode(".//tr[td[contains(text(), 'End')]]/following-sibling::tr[1]/td[1]", $node, true, "/(\w+)[.\s]*, \w+ \d+/ims")
        );
        $checkOutDate .= " " . date("Y", $result["CheckInDate"]);
        $checkOutDate = en($checkOutDate);
        $this->logger->debug("parsed CheckOutDatetime: " . $checkOutDate . " " . $checkOutTime);
        $this->logger->debug("CheckOutWeek: " . $checkOutWeek);
        $weeknum = WeekTranslate::number1($checkOutWeek);
        $this->logger->debug("weeknum: " . $weeknum);
        $checkoutDateUnix = EmailDateHelper::parseDateUsingWeekDay($checkOutDate, $weeknum);

        if ($checkoutDateUnix !== false) {
            $checkOutDate = date('M d Y', $checkoutDateUnix);
            $this->logger->debug("corrected DropoffDatetime: " . $checkOutDate . " " . $checkOutTime);
        }
        $result["CheckOutDate"] = strtotime($checkOutDate . " " . $checkOutTime);

        if ($result["CheckOutDate"] < $result["CheckInDate"] && date('Y',
                $result["CheckOutDate"]) != '1970' && date('Y', $result["CheckInDate"]) != '1970') {
            $this->logger->debug("Skip: invalid dates");

            return [];
        }
        // Address
        $result['Address'] = implode(', ', $this->http->FindNodes(".//p[contains(text(), 'Address') or contains(text(), 'Dirección')]/following-sibling::address/p", $node));
        // Phone
        if ($phone = $this->http->FindSingleNode(".//dt[contains(text(), 'Phone') or contains(text(), 'Teléfono')]/following-sibling::dd[1]", $node, false, '/^.{7,18}$/')) {
            $result['Phone'] = $phone;
        }
        // Fax
        $result['Fax'] = $this->http->FindSingleNode(".//dt[contains(text(), 'Fax')]/following-sibling::dd[1]", $node);

        // do not collect duplicates
        if ($confNo == 'Not Entered') {
            $hash = $result['ConfirmationNumber'] . '-' . $result['HotelName'] . '-' . $result["CheckOutDate"] . '-' . $result['Address'];

            if (in_array($hash, $this->hotelsHash)) {
                $this->logger->error("Skip duplicate -> $hash");
                //$this->currentItin--;

                return [];
            }
            $this->hotelsHash[] = $hash;
        }// if ($confNo == 'Not Entered')

        if (strstr($checkInDate, $checkOutDate)) {
            $this->logger->error("Skipping hotel [{$result['HotelName']}] with the same checkin / checkout dates");
            $this->currentItin--;

            return [];
        }

        $this->logger->debug('Parsed Hotel:');
        $this->logger->debug(var_export($result, true), ['pre' => true]);

        return $result;
    }

    private function ParseCar($confNo, $node)
    {
        $this->logger->notice(__METHOD__ . " -> {$confNo}");
        $result = ["Kind" => "L"];
        // Number
        if ($confNo === 'Not Entered') {
            $result['Number'] = CONFNO_UNKNOWN;
        } else {
            $result["Number"] = $this->http->FindPreg('/^Confirmation:\s*([\w\-]{3,30})/', false, $confNo) ??
                $this->http->FindPreg('/^(.+?)[*\s]/', false, $confNo) ?: $confNo;
        }
        $this->logger->info(sprintf('[%s] Parse Car #%s', $this->currentItin, $result['Number']), ['Header' => 4]);
        $this->currentItin++;
        // PickupDatetime
        $date = $this->http->FindSingleNode("./ancestor::ul[@class = 'itinerary-list']/preceding-sibling::h2", $node, true, "/\,\s*([^<]+)/");
        $this->logger->debug("Date: {$date}");
        $pickupTime = (
            $this->http->FindSingleNode(".//tr[th[contains(text(), 'Pick-up')]]/following-sibling::tr[1]/td[2]", $node) ?:
            $this->http->FindSingleNode(".//tr[th[contains(text(), 'Start')]]/following-sibling::tr[1]/td[2]", $node)
        );
        $pickupDate = (
        $this->http->FindSingleNode(".//tr[th[contains(text(), 'Pick-up')]]/following-sibling::tr[1]/td[1]", $node, true, "/\\s*,([^<]+)/ims") ?:
            $this->http->FindSingleNode(".//tr[td[contains(text(), 'Start')]]/following-sibling::tr[1]/td[1]", $node, true, "/\\s*,([^<]+)/ims")
        );
        $this->logger->info("PickupDatetime: " . $pickupDate . " " . $pickupTime);
        $result["PickupDatetime"] = strtotime($pickupDate . " " . $pickupTime, strtotime($date));
        // DropoffDatetime
        $dropoffTime = (
            $this->http->FindSingleNode(".//tr[th[contains(text(), 'Drop-off')]]/following-sibling::tr[1]/td[2]", $node) ?:
            $this->http->FindSingleNode(".//tr[td[contains(text(), 'End')]]/following-sibling::tr[1]/td[2]", $node)
        );
        $dropoffDate = (
            $this->http->FindSingleNode(".//tr[th[contains(text(), 'Drop-off')]]/following-sibling::tr[1]/td[1]", $node, true, "/\\s*,([^<]+)/ims") ?:
            $this->http->FindSingleNode(".//tr[td[contains(text(), 'End')]]/following-sibling::tr[1]/td[1]", $node, true, "/\\s*,([^<]+)/ims")
        );
        $dropoffWeek = (
            $this->http->FindSingleNode(".//tr[th[contains(text(), 'Drop-off')]]/following-sibling::tr[1]/td[1]", $node, true, "/(\w+)\s*, \w+ \d+/ims") ?:
            $this->http->FindSingleNode(".//tr[td[contains(text(), 'End')]]/following-sibling::tr[1]/td[1]", $node, true, "/(\w+)\s*, \w+ \d+/ims")
        );
        $dropoffDate .= " " . date("Y", $result["PickupDatetime"]);
        $this->logger->debug("parsed DropoffDatetime: " . $dropoffDate . " " . $dropoffTime);
        $this->logger->debug("dropoffWeek: " . $dropoffWeek);
        $weeknum = WeekTranslate::number1($dropoffWeek);
        $this->logger->debug("weeknum: " . $weeknum);
        $dropoffDateUnix = EmailDateHelper::parseDateUsingWeekDay($dropoffDate, $weeknum);

        if ($dropoffDateUnix !== false) {
            $dropoffDate = date('M d Y', $dropoffDateUnix);
            $this->logger->debug("corrected DropoffDatetime: " . $dropoffDate . " " . $dropoffTime);
        }
        $result["DropoffDatetime"] = strtotime($dropoffDate . " " . $dropoffTime);

        if ($result["PickupDatetime"] == $result["DropoffDatetime"]) {
            $result["DropoffDatetime"] = strtotime('+1 minutes', $result["DropoffDatetime"]);
        }

        // PickupLocation
        $result["PickupLocation"] = $this->http->FindSingleNode(".//p[contains(text(), 'Pick-up')]/following-sibling::address", $node);
        // DropoffLocation
        $result["DropoffLocation"] = $this->http->FindSingleNode(".//p[contains(text(), 'Drop-off')]/following-sibling::address", $node);

        if (empty($result["DropoffLocation"])) {
            $result["DropoffLocation"] = $this->http->FindSingleNode(".//p[contains(text(), 'Drop-off')]/following-sibling::p", $node);

            if (stristr($result["DropoffLocation"], 'Same as pick-up')) {
                $this->logger->debug("DropoffLocation -> " . $result["DropoffLocation"]);
                $result["DropoffLocation"] = $result["PickupLocation"];
            }// if (stristr($result["DropoffLocation"], 'Same as pick-up'))
        }// if (empty($result["DropoffLocation"]))
        // PickupPhone, DropoffPhone
        $phone = $this->http->FindSingleNode(".//dt[contains(text(), 'Phone')]/following-sibling::dd[1]", $node);
        $phone = $this->http->FindPreg('/^(.+?)\s+Local Phone Number/i', false, $phone) ?: $phone;

        if (!empty($phone)) {
            $result["PickupPhone"] = $result["DropoffPhone"] = array_slice(explode(',', $phone), 0, 2);
        }
        // RentalCompany
        $result["RentalCompany"] = $this->http->FindSingleNode(".//div[contains(@class, 'overview')]/h3", $node);

        $this->logger->debug('Parsed Car:');
        $this->logger->debug(var_export($result, true), ['pre' => true]);

        return $result;
    }

    private function ParseAirSegment($confNo, $node)
    {
        $this->logger->notice(__METHOD__ . " -> {$confNo}");
        // Air trip segments
        $segment = [];
        // FlightNumber
        $segment['FlightNumber'] = $this->http->FindSingleNode(".//div[contains(@class, 'overview')]/h3", $node, true, "/(?:Flight|Vuelo)\s+(\d+)/");
        // AirlineName
        $segment['AirlineName'] = orval(
            $this->http->FindSingleNode(".//div[contains(@class, 'overview')]/h3", $node, true, "/(.+) Flight /"),
            $this->http->FindSingleNode(".//div[contains(@class, 'overview')]/h3", $node, true, "/(?:Flight|Vuelo)\s+\d+\s+de\s+(.+)/")
        );
        // Duration
        $segment['Duration'] = $this->http->FindSingleNode(".//p[contains(text(), 'Flight Duration:') or contains(text(), 'Duración del vuelo')]", $node, true, '/(?:Duration|Duración del vuelo):\s*(.*)/ims');
        // Aircraft
        $segment['Aircraft'] = $this->http->FindSingleNode(".//p[contains(text(), 'Aircraft:') or contains(text(), 'Aeronave')]", $node, true, '/(?:Aircraft|Aeronave):\s*(.*)/ims');
        // DepName
        $segment['DepName'] = $this->http->FindSingleNode(".//p[contains(text(), 'Departure') or contains(text(), 'Partida')]/following-sibling::p[1]", $node, true, "/(.+) \([A-Z]{3}\)/");
        // DepCode
        $segment['DepCode'] = $this->http->FindSingleNode(".//p[contains(text(), 'Departure') or contains(text(), 'Partida')]/following-sibling::p[1]", $node, true, '/\(([A-Z]{3})\)/');

        $date = $this->http->FindSingleNode("./ancestor::ul[@class = 'itinerary-list']/preceding-sibling::h2", $node, true, "/\,\s*([^<]+)/");
        $date = en(preg_replace("#\s+de\s+#", " ", $date));
        $this->logger->debug("Date: {$date}");
        // DepDate
        $depTime = orval(
            $this->http->FindSingleNode(".//tr[th[contains(text(), 'Departs') or contains(text(), 'Partido')]]/following-sibling::tr[1]/td[1]/text()[1]", $node),
            $this->http->FindSingleNode(".//th[contains(text(), 'Departed')]/ancestor::tr[1]/following-sibling::tr[1]/td[1]", $node, true, '#(\d+:\d+\s*(?:PM|AM))#i')
        );
        $depDateTime = strtotime($date . ' ' . preg_replace("#([ap])\.\s+m\.#", "$1m", $depTime));
        $segment['DepDate'] = $depDateTime ? $depDateTime : null;
        // ArrName
        $segment['ArrName'] = $this->http->FindSingleNode(".//p[contains(text(), 'Arrival') or contains(text(), 'Arribo')]/following-sibling::p[1]", $node, true, "/(.+) \([A-Z]{3}\)/");
        // ArrCode
        $segment['ArrCode'] = $this->http->FindSingleNode(".//p[contains(text(), 'Arrival') or contains(text(), 'Arribo')]/following-sibling::p[1]", $node, true, '/\(([A-Z]{3})\)/');
        $arrTime = orval(
            $this->http->FindSingleNode(".//tr[th[contains(text(), 'Arrives') or contains(text(), 'Arribado')]]/following-sibling::tr[1]/td[1]/text()[1]", $node),
            $this->http->FindSingleNode(".//th[contains(text(), 'Landed')]/ancestor::tr[1]/following-sibling::tr[1]/td[1]", $node, true, '#(\d+:\d+\s*(?:PM|AM))#i')
        );
        $arrDateTime = strtotime($date . ' ' . preg_replace("#([ap])\.\s+m\.#", "$1m", $arrTime));
        $segment['ArrDate'] = $arrDateTime ? $arrDateTime : null;

        if ($segment['ArrDate'] && $segment['DepDate'] && $segment['ArrDate'] < $segment['DepDate']) {
            $segment['ArrDate'] = strtotime("+1 day", $segment['ArrDate']);
        }

        if (!empty($segment['ArrCode']) && $segment['ArrCode'] == $segment['DepCode']) {
            $this->logger->notice("Skip wrong segment");

            return [];
        }

        if ((!empty($segment['DepCode']) && $segment['DepCode'] === 'HDQ') || (!empty($segment['ArrCode']) && $segment['ArrCode'] === 'HDQ')) {
            $this->logger->notice("Skip segment with HDQ (Headquarter)");
            $this->airsWithHDG[] = $confNo;

            return [];
        }

        $this->logger->debug('Parsed Air:');
        $this->logger->debug(var_export($segment, true), ['pre' => true]);

        return $segment;
    }
}
