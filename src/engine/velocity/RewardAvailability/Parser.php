<?php

namespace AwardWallet\Engine\velocity\RewardAvailability;

use AwardWallet\Engine\ProxyList;

class Parser extends \TAccountChecker
{
    use \SeleniumCheckerHelper;
    use \PriceTools;
    use ProxyList;

    public $isRewardAvailability = true;
    private $inCabin;
    private $brandDir;
    private $twice;

    public static function getRASearchLinks(): array
    {
        return ['https://www.virginaustralia.com/au/en/#/' => 'search page'];
    }

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->setHttp2(true);

        $array = ['fr', 'es', 'de', 'us', 'au', 'uk'];
        $targeting = $array[random_int(0, count($array) - 1)];
//        $this->setProxyBrightData(null, 'static', $targeting);
        $this->setProxyGoProxies(null, $targeting);

//        $this->setProxyDOP();

        $this->http->setCookie('virginCookiesAccepted', 'accepted', 'virginaustralia.com');
    }

    public function LoadLoginForm()
    {
        return true;
    }

    public function IsLoggedIn()
    {
        return false;
    }

    public function Login()
    {
        return true;
    }

    public function getRewardAvailabilitySettings()
    {
        return [
            'supportedCurrencies'      => ['USD'],
            'supportedDateFlexibility' => 0, // 1
            'defaultCurrency'          => 'USD',
        ];
    }

    public function ParseRewardAvailability(array $fields)
    {
        $this->logger->info("Parse Reward Availability", ['Header' => 2]);
        $this->logger->debug("params: " . var_export($fields, true));

        $this->inCabin = $fields['Cabin'];
        $fields['Cabin'] = $this->getCabinFields(false)[$fields['Cabin']];
        $supportedCurrencies = $this->getRewardAvailabilitySettings()['supportedCurrencies'];

//        if (!$this->validRoute($fields)) {
        if (!$this->validRouteWithPartners($fields)) {
            return ['routes' => []];
        }

        if (!in_array($fields['Currencies'][0], $supportedCurrencies)) {
            $fields['Currencies'][0] = $this->getRewardAvailabilitySettings()['defaultCurrency'];
            $this->logger->notice("parse with defaultCurrency: " . $fields['Currencies'][0]);
        }

        $fields['DepDate'] = [
            'm-d-Y' => date("m-d-Y", $fields['DepDate']),
            'Y-m-d' => date("Y-m-d", $fields['DepDate']),
        ];
        // NB!!! everytime class First
        $url = "https://book.virginaustralia.com/dx/VADX/#/flight-selection?journeyType=one-way&activeMonth={$fields['DepDate']['m-d-Y']}&locale=en-US&awardBooking=true&class=First&ADT={$fields['Adults']}&CHD=0&INF=0&origin={$fields['DepCode']}&destination={$fields['ArrCode']}&date={$fields['DepDate']['m-d-Y']}&promoCode=&execution=undefined";
        $responseData = $this->selenium($url);

        if ($responseData === 'no flight') {
            return ['routes' => []];
        }

        if ($msg = $this->http->FindSingleNode("(//span[starts-with(normalize-space(),'Sorry, there are no flight or seat available for the dates')])[1]")) {
            $this->SetWarning($msg);

            return ['routes' => []];
        }

        if ($msg = $this->http->FindSingleNode("(//span[starts-with(normalize-space(),'No flights found for your search')])[1]")) {
            $this->SetWarning($msg);

            return ['routes' => []];
        }

        if ($msg = $this->http->FindSingleNode("(//span[starts-with(normalize-space(),'Flights for selected date are not available')])[1]")) {
            $this->SetWarning($msg);

            return ['routes' => []];
        }

        if ($msg = $this->http->FindSingleNode("(//span[starts-with(normalize-space(),'We’re unable to process your request')])[1]")) {
            $this->SetWarning($msg);

            return ['routes' => []];
        }

        if ($msg = $this->http->FindSingleNode("//div[contains(@class,'error')]//span[@class='title']")) {
            $this->logger->error($msg);
            $this->sendNotification("check error msg // ZM");
        }

        if ($msg = $this->http->FindSingleNode("
            //span[contains(normalize-space(text()),'Flights for selected date are not available. Please select another dates to proceed')]
            | //span[normalize-space(text())='No available flights']
            ")) {
            $this->SetWarning($msg);

            return ['routes' => []];
        }

        if (!isset($this->twice) && !$this->http->FindSingleNode('//h2[contains(.,"Choose flights")]')
            && !$this->http->FindSingleNode('//span[@data-translation="flightSelection.selectFlightsTitle"][contains(.,"Choose Flights")]')
            && !$this->http->FindSingleNode('(//div[normalize-space()="Choose your flights"])[1]')) {
            throw new \CheckException("can't determine loading", ACCOUNT_ENGINE_ERROR);
        }

        if (!empty($responseData)) {
            $data = $this->http->JsonLog(null, 1, true);
            $result = $this->parseRewardFlights($data, $fields);
        } else {
            $result = $this->ParseRewardNew($fields);
        }

        return ['routes' => $result];
    }

    private function getCabinFields($onlyKeys = true): array
    {
        $cabins = [
            'economy'        => ['class' => 'Economy', 'execution' => 'e3s1'],
            'premiumEconomy' => ['class' => 'Economy', 'execution' => 'e3s1'], // has no
            'firstClass'     => ['class' => 'First', 'execution' => 'e2s1'],
            // it has the residence ['class' => 'First', 'execution' => 'e1s1']
            'business' => ['class' => 'Business', 'execution' => 'e3s1'],
        ];

        if ($onlyKeys) {
            return array_keys($cabins);
        }

        return $cabins;
    }

    private function convertToStdCabin(string $str)
    {
        $cabins = $this->getCabinFields(false);

        foreach ($cabins as $stdWord => $data) {
            if ($data['class'] === $str) {
                return $stdWord;
            }
        }
        $array = [
            'Economy'             => 'economy',
            'United Premium Plus' => 'premiumEconomy',
            'PremiumEconomy'      => 'premiumEconomy',
            'First'               => 'firstClass',
            'Business'            => 'business',
        ];

        if (isset($array[$str])) {
            return $array[$str];
        }
        $this->sendNotification("check cabin {$str} // ZM");

        return null;
    }

    private function ParseReward($fields = [])
    {
        $this->logger->notice(__METHOD__);
        $this->logger->info("ParseReward [" . $fields['DepDate']['m-d-Y'] . "-" . $fields['DepCode'] . "-" . $fields['ArrCode'] . "]",
            ['Header' => 2]);

        $this->http->GetURL("https://book.virginaustralia.com/dx/VADX/");
        $script = $this->http->FindSingleNode("//script[contains(.,'var sabre = {};')]");

        if ($this->badProxy()) {
            throw new \CheckRetryNeededException(5, 0);
        }

        if (!$script) {
            throw new \CheckException("can't get script with token etc", ACCOUNT_ENGINE_ERROR);
        }
        $cid = $this->http->FindPreg("/sabre\['cid'\] = '([^']+)'/", false, $script);
        $access_token = $this->http->FindPreg("/sabre\['access_token'\] = '([^']+)'/", false, $script);

        if (!isset($cid, $access_token)) {
            throw new \CheckException("can't get token data", ACCOUNT_ENGINE_ERROR);
        }

        $headers = [
            'Accept'          => 'application/json',
            'Authorization'   => 'Bearer ' . $access_token,
            'Content-Type'    => 'application/json',
            'conversation-id' => $cid,
            'Origin'          => 'https://book.virginaustralia.com',
            'Referer'         => 'https://book.virginaustralia.com/',
        ];
        $payload = '{"cabinClass":"First","awardBooking":"true","promoCodes":[""],"searchType":"BRANDED","itineraryParts":[{"from":{"useNearbyLocations":false,"code":"' . $fields['DepCode'] . '"},"to":{"useNearbyLocations":false,"code":"' . $fields['ArrCode'] . '"},"when":{"date":"' . $fields['DepDate']['Y-m-d'] . '"}}],"passengers":{"ADT":' . $fields['Adults'] . '}}';
        $this->http->RetryCount = 0;
//        $this->http->PostURL("https://dc.virginaustralia.com/v6.3/ssw/products/air/search?execution=e2s1&jipcc=VADX", $payload, $headers);

//        if ($this->http->Response['code'] == 400 || $this->http->FindPreg("/conversation e2s1 was completed or doesn't exist/")) {
        $this->http->PostURL("https://dc.virginaustralia.com/v6.3/ssw/products/air/search?jipcc=VADX", $payload, $headers);
//        }
        // NB - если будет чо ,то https://dc.virginaustralia.com/v4.3/dc/products/init?jipcc=VADX и там в responseheaders взять execution
        // и его в урл https://dc.virginaustralia.com/v4.3/dc/products/air/search?execution=f08ed39a-5378-49c8-80cb-622c76da7828&jipcc=VADX
        $data = $this->http->JsonLog(null, 1, true);

        if (isset($data['unbundledOffers'][0]) && is_array($data['unbundledOffers'][0]) && empty($data['unbundledOffers'][0])) {
            $this->http->PostURL("https://dc.virginaustralia.com/v4.3/dc/products/air/search?jipcc=VADX", $payload, $headers);
            $data = $this->http->JsonLog(null, 1, true);
        }

        if (isset($data['unbundledOffers'][0]) && is_array($data['unbundledOffers'][0]) && empty($data['unbundledOffers'][0])) {
            $payload = str_replace('BRANDED', 'CALENDAR30', $payload);
            $this->http->PostURL("https://dc.virginaustralia.com/v4.3/dc/products/air/search?jipcc=VADX", $payload,
                $headers);
            $dataExt = $this->http->JsonLog(null, 1, true);

            if (isset($dataExt['warnings']) && empty($dataExt['warnings'])) {
                $this->http->PostURL("https://dc.virginaustralia.com/v4.3/dc/products/air/search?jipcc=VADX", $payload,
                    $headers);
                $dataExt = $this->http->JsonLog(null, 1, true);
            }

            if (isset($dataExt['warnings']) && in_array('NO_AVAILABILITY_FOUND', $dataExt['warnings'])) {
                $this->SetWarning('No flights found for your search');

                return [];
            }

            if ($this->http->FindPreg('/"unbundledAlternateDateOffers":\[\[{"status":.+{"status":"(?:UNAVAILABLE|NONE_SCHEDULED)","departureDates":\["' . $fields['DepDate']['Y-m-d'] . '"\]}(?:,{"status":|\]\])/')) {
                $this->SetWarning('No flights found for your search');

                return [];
            }

            throw new \CheckRetryNeededException(5, 0);
        }

        $this->http->RetryCount = 0;
        // routes
        // https://book.virginaustralia.com/dx/VADX/4.7.21-248.R6-2-2-1-PROD/data/global/routes.json

        return $this->parseRewardFlights($data, $fields);
    }

    private function ParseRewardNew($fields = [])
    {
        $this->logger->notice(__METHOD__);
        $this->logger->info("ParseReward [" . $fields['DepDate']['m-d-Y'] . "-" . $fields['DepCode'] . "-" . $fields['ArrCode'] . "]",
            ['Header' => 2]);

        $this->http->GetURL("https://book.virginaustralia.com/dx/VADX/");
        $script = $this->http->FindSingleNode("//script[contains(.,'var sabre = {};')]");

        if ($this->badProxy()) {
            throw new \CheckRetryNeededException(5, 0);
        }

        if (!$script) {
            throw new \CheckException("can't get script with token etc", ACCOUNT_ENGINE_ERROR);
        }
        $cid = $this->http->FindPreg("/sabre\['cid'\] = '([^']+)'/", false, $script);
        $access_token = $this->http->FindPreg("/sabre\['access_token'\] = '([^']+)'/", false, $script);

        if (!isset($cid, $access_token)) {
            $access_token = 'anNvbl91c2VyOmpzb25fcGFzc3dvcmQ='; // base64_encode('json_user:json_password');
            // throw new \CheckException("can't get token data", ACCOUNT_ENGINE_ERROR);
            $headers = [
                'Accept'             => '*/*',
                'ADRUM'              => 'isAjax:true',
                'Authorization'      => 'Bearer Basic ' . $access_token,
                'Content-Type'       => 'application/json',
                'Origin'             => 'https://book.virginaustralia.com',
                'Referer'            => 'https://book.virginaustralia.com/dx/VADX/',
                'x-sabre-storefront' => 'VADX',
            ];
        } else {
            $headers = [
                'Accept'             => '*/*',
                'ADRUM'              => 'isAjax:true',
                'Authorization'      => 'Bearer Basic ' . $access_token,
                'Content-Type'       => 'application/json',
                'conversation-id'    => $cid,
                'Origin'             => 'https://book.virginaustralia.com',
                'Referer'            => 'https://book.virginaustralia.com/dx/VADX/',
                'x-sabre-storefront' => 'VADX',
            ];
        }
        $payload = '{"operationName":"bookingAirSearch","variables":{"airSearchInput":{"cabinClass":"First","awardBooking":true,"promoCodes":[],"searchType":"BRANDED","itineraryParts":[{"from":{"useNearbyLocations":false,"code":"' . $fields['DepCode'] . '"},"to":{"useNearbyLocations":false,"code":"' . $fields['ArrCode'] . '"},"when":{"date":"' . $fields['DepDate']['Y-m-d'] . '"}}],"passengers":{"ADT":' . $fields['Adults'] . '}}},"extensions":{},"query":"query bookingAirSearch($airSearchInput: CustomAirSearchInput) {\n  bookingAirSearch(airSearchInput: $airSearchInput) {\n    originalResponse\n    __typename\n  }\n}\n"}';
        $this->http->RetryCount = 0;
        $this->http->PostURL("https://book.virginaustralia.com/api/graphql", $payload, $headers, 30);

        if ($this->http->Response['code'] == 403) {
            throw new \CheckException('blocks', ACCOUNT_PROVIDER_ERROR);
        }

        if (strpos($this->http->Response['body'], '"message":"Request failed with status code 400"') !== false) {
            $this->SetWarning('No flights found for your search');

            return [];
        }

        if (strpos($this->http->Error, 'Network error 28 - Operation timed out after') !== false
            || strpos($this->http->Error, 'Network error 92 - HTTP/2 stream') !== false
            || $this->http->Response['code'] == 502
            || $this->http->Error === 'empty body'
            || strpos($this->http->Response['body'], '"message":"Request failed with status code 500"') !== false
            || strpos($this->http->Response['body'], '"message":"Request failed with status code 502"') !== false
            || strpos($this->http->Response['body'], '"message":"Request failed with status code 503"') !== false
            || strpos($this->http->Response['body'], '"message":"Request failed with status code 504"') !== false
            || strpos($this->http->Response['body'], '"message":"Request failed with status code 429"') !== false
        ) {
            sleep(2);
            $this->http->PostURL("https://book.virginaustralia.com/api/graphql", $payload, $headers, 30);
        }

        if (strpos($this->http->Response['body'], '"message":"Request failed with status code 404"') !== false
        ) {
            $this->sendNotification('check retry // ZM');
            sleep(2);
            $this->http->PostURL("https://book.virginaustralia.com/api/graphql", $payload, $headers, 30);
        }

        if (strpos($this->http->Response['body'], '"message":"Request failed with status code 404"') !== false
            || strpos($this->http->Response['body'], '"message":"Request failed with status code 502"') !== false
        ) {
            throw new \CheckException("Service Not Available", ACCOUNT_PROVIDER_ERROR);
        }

        if (strpos($this->http->Error, 'Network error 28 - Operation timed out after') !== false
            || strpos($this->http->Error, 'Network error 92 - HTTP/2 stream') !== false
            || strpos($this->http->Error, 'Network error 56 - Recv failure') !== false
            || strpos($this->http->Response['body'], '"message":"Request failed with status code 500"') !== false
            || strpos($this->http->Response['body'], '"message":"Request failed with status code 503"') !== false
            || strpos($this->http->Response['body'], '"message":"Request failed with status code 504"') !== false
            || strpos($this->http->Response['body'], '"message":"Request failed with status code 429"') !== false
            || strpos($this->http->Response['body'], '"message":"Request failed with status code 400"') !== false
            || $this->http->Response['code'] == 502
            || $this->http->Error === 'empty body'
        ) {
            throw new \CheckRetryNeededException(5, 0);
        }

        $data = $this->http->JsonLog(null, 1, true);

        if (!isset($data['data']['bookingAirSearch']['originalResponse'])) {
            if ($this->http->Response['code'] !== 200) {
                throw new \CheckRetryNeededException(5, 0);
            }
            $this->sendNotification('check graphql // ZM');

            throw new \CheckException('other format', ACCOUNT_ENGINE_ERROR);
        }
        $data = $data['data']['bookingAirSearch']['originalResponse'];

        /*        if (isset($data['unbundledOffers'][0]) && is_array($data['unbundledOffers'][0]) && empty($data['unbundledOffers'][0])) {
                    $this->http->PostURL("https://dc.virginaustralia.com/v4.3/dc/products/air/search?jipcc=VADX", $payload, $headers);
                    $data = $this->http->JsonLog(null, 1, true);
                }*/

        if (isset($data['unbundledOffers'][0]) && is_array($data['unbundledOffers'][0]) && empty($data['unbundledOffers'][0])) {
            if (!isset($this->twice)) {
                $payload = str_replace('BRANDED', 'CALENDAR30', $payload);
                $this->http->PostURL("https://dc.virginaustralia.com/v4.3/dc/products/air/search?jipcc=VADX", $payload,
                    $headers);
                $dataExt = $this->http->JsonLog(null, 1, true);

                if (isset($dataExt['warnings']) && empty($dataExt['warnings'])) {
                    $this->http->PostURL("https://dc.virginaustralia.com/v4.3/dc/products/air/search?jipcc=VADX",
                        $payload,
                        $headers);
                    $dataExt = $this->http->JsonLog(null, 1, true);
                }

                if (isset($dataExt['warnings']) && in_array('NO_AVAILABILITY_FOUND', $dataExt['warnings'])) {
                    $this->SetWarning('No flights found for your search');

                    return [];
                }
            }

            if ($this->http->FindPreg('/"unbundledAlternateDateOffers":\[\[{"status":.+{"status":"UNAVAILABLE","departureDates":\["' . $fields['DepDate']['Y-m-d'] . '"\]}(?:,{"status":|\]\])/')) {
                $this->SetWarning('No flights found for your search');

                return [];
            }

            throw new \CheckRetryNeededException(5, 0);
        }

        $this->http->RetryCount = 0;
        // routes
        // https://book.virginaustralia.com/dx/VADX/4.7.21-248.R6-2-2-1-PROD/data/global/routes.json

        return $this->parseRewardFlights($data, $fields);
    }

    private function getBrands(): array
    {
        $brands = \Cache::getInstance()->get('ra_va_brand_list');

        if ($brands === false || !is_array($brands)) {
            // TODO: тут же есть и др справочная инфа, airport, aircraft, currency etc
            if (isset($this->brandDir)) {
                $this->logger->debug("brandDir: " . $this->brandDir);
                $this->http->GetURL("https://book.virginaustralia.com/dx/VADX/{$this->brandDir}/data/translations/en-GB.json");
            } else {
                $this->http->GetURL("https://book.virginaustralia.com/dx/VADX/4.7.21-248.R6-2-2-1-PROD/data/translations/en-GB.json");
            }
            $dict = $this->http->JsonLog(null, 0, true);

            if (!$dict) {
                $this->sendNotification("check js-dictionary // ZM");
                // hard code
                return [
                    "AI"        => "All In",
                    "AT"        => "Anytime",
                    "BS"        => "Business Saver",
                    "BU"        => "Business",
                    "BZ"        => "Business",
                    "DA"        => "Velocity Reward",
                    "DD"        => "Velocity Reward",
                    "DE"        => "Velocity Reward",
                    "DG"        => "Velocity Reward",
                    "DH"        => "Velocity Reward",
                    "DJ"        => "Velocity Reward",
                    "DM"        => "Velocity Reward",
                    "DN"        => "Velocity Reward",
                    "DS"        => "Velocity Reward",
                    "DV"        => "Velocity Reward",
                    "EV"        => "Elevate",
                    "FC"        => "First Class",
                    "FD"        => "Freedom",
                    "FI"        => "First Suite Freedom",
                    "FL"        => "Fully-flex",
                    "GF"        => "Guest First",
                    "GJ"        => "Guest Business",
                    "GM"        => "Get More",
                    "GO"        => "Go",
                    "GP"        => "Go Plus",
                    "GV"        => "Getevate",
                    "GW"        => "Getaway",
                    "GY"        => "Guest Economy",
                    "JB"        => "Business Breaking Deals",
                    "JF"        => "Just Fly",
                    "JS"        => "Business Saver",
                    "PA"        => "Plan Ahead",
                    "PE"        => "Premium",
                    "PF"        => "Premium",
                    "PS"        => "Premium Saver",
                    "PV"        => "Premium Saver",
                    "RB"        => "Business Reward",
                    "RE"        => "Economy Reward",
                    "RF"        => "First Reward",
                    "RP"        => "Premium Reward",
                    "RS"        => "Standard",
                    "SF"        => "Semi-flex",
                    "TR"        => "The Residence",
                    "UB"        => "Unbundled",
                    "W0"        => " ",
                    "X0"        => " ",
                    "YB"        => "Economy Breaking Deals",
                    "YF"        => "Economy Freedom",
                    "YI"        => "First Freedom",
                    "YS"        => "Economy Saver",
                    "YV"        => "Economy Value",
                    "anySeat"   => "Any Seat",
                    "brand"     => "Brand",
                    "undefined" => "",
                ];
            }
            $brands = array_filter($dict['brand'], function ($s) {
                return $s !== '';
            }, ARRAY_FILTER_USE_KEY);
            $this->logger->notice(var_export($brands, true), ['pre' => true]);

            if (!empty($brands)) {
                \Cache::getInstance()->set('ra_va_brand_list', $brands, 60 * 60 * 24);
            }
        }

        return $brands;
    }

    private function parseRewardFlights($data, $fields): array
    {
        $this->logger->notice(__METHOD__);
        $routes = [];

        if (isset($data['unbundledOffers'][0]) && is_array($data['unbundledOffers'][0])) {
            $unbundledOffers = $data['unbundledOffers'][0];
        }

        if (!isset($unbundledOffers)) {
            throw new \CheckException("check unbundledOffers //ZM");
        }
//        $fareFamilies = $this->getBrands();
        $fareFamilies = $this->getFareFamilies($data['fareFamilies']);

        $itineraryParts = [];
        $listSegments = [];

        foreach ($unbundledOffers as $num => $unbundledOffer) {
            if ($unbundledOffer['soldout'] || $unbundledOffer['itineraryPart'][0]['@type'] !== 'ItineraryPart'
                || (isset($unbundledOffer['status'])
                    && in_array($unbundledOffer['status'], ['SOLD_OUT', 'UNAVAILABLE']))
            ) {
                continue;
            }
            $itineraryParts[$unbundledOffer['itineraryPart'][0]['@id']] = $unbundledOffer['itineraryPart'][0];

            foreach ($unbundledOffer['itineraryPart'][0]['segments'] as $segment) {
                if (isset($segment['@type']) && $segment['@type'] === 'Segment') {
                    $listSegments[$segment['@id']] = $segment;
                }
            }
        }

        // unbundledAlternateDateOffers - там на другие дни, но лишь по одному маршруту

        $itineraryPartBrands = $data['brandedResults']['itineraryPartBrands'][0];
        $this->logger->debug("Found " . count($itineraryPartBrands) . " routes");

        foreach ($itineraryPartBrands as $itineraryPartBrand) {
            if (!isset($itineraryParts[$itineraryPartBrand['itineraryPart']['@ref']])) {
                $undefItineraryPart = true;
                // skipped - soldout

                continue;
            }

            $it = $itineraryParts[$itineraryPartBrand['itineraryPart']['@ref']];
            $this->logger->debug("Found " . count($itineraryPartBrand['brandOffers']) . " offers");

            foreach ($itineraryPartBrand['brandOffers'] as $brandOffers) {
                $cabin = $this->convertToStdCabin($brandOffers['cabinClass']);

                if ($brandOffers['cabinClass'] !== 'First'
                    && (in_array($brandOffers['brandId'], ['FI', 'YI'])
                        || strpos(($fareFamilies[$brandOffers['brandId']] ?? ''), 'First') === 0)
                ) {
                    $this->logger->error("skip brandOffers cabinClass = {$brandOffers['cabinClass']} and AwardType = {$brandOffers['brandId']}/" . ($fareFamilies[$brandOffers['brandId']] ?? ''));

                    continue;
                }

                if ($brandOffers['cabinClass'] === 'First'
                    && in_array($brandOffers['brandId'], ['BU', 'RB'])
                ) {
                    $cabin = 'business';
                } elseif ($brandOffers['cabinClass'] !== 'Business'
                    && (in_array($brandOffers['brandId'], ['BU', 'RB'])
                        || strpos(($fareFamilies[$brandOffers['brandId']] ?? ''), 'Business') === 0)
                ) {
                    $this->logger->error("skip brandOffers cabinClass = {$brandOffers['cabinClass']} and AwardType = {$brandOffers['brandId']}/" . ($fareFamilies[$brandOffers['brandId']] ?? ''));

                    continue;
                }

                if ($brandOffers['cabinClass'] !== 'Economy'
                    && (in_array($brandOffers['brandId'], ['RE', 'GY'])
                        || preg_match("/^Economy\b/", ($fareFamilies[$brandOffers['brandId']] ?? ''))
                        || preg_match("/\bEconomy$/", ($fareFamilies[$brandOffers['brandId']] ?? ''))
                    )
                ) {
                    $this->logger->error("skip brandOffers cabinClass = {$brandOffers['cabinClass']} and AwardType = {$brandOffers['brandId']}/" . ($fareFamilies[$brandOffers['brandId']] ?? ''));

                    continue;
                }

                $result = ['connections' => []];
                $layover = null;
                $totalFlight = null;
                $noTransfer = true;
                $miles = $taxes = $currency = null;

                foreach ($brandOffers['total']['alternatives'] as $alternatives) {
                    if (count($alternatives) > 1) {
                        foreach ($alternatives as $alternative) {
                            if ($alternative['currency'] === 'FFCURRENCY') {
                                $miles = $alternative['amount'];
                            } else {
                                $taxes = $alternative['amount'];
                                $currency = $alternative['currency'];
                            }
                        }
                    }
                }

                //$miles = $brandOffers['total']['alternatives'][1][0]['amount'];
                if (!isset($miles)) {
                    foreach ($brandOffers['fare']['alternatives'] as $alternatives) {
                        if (count($alternatives) === 1) {
                            foreach ($alternatives as $alternative) {
                                if ($alternative['currency'] === 'FFCURRENCY') {
                                    $miles = $alternative['amount'];

                                    break 2;
                                }
                            }
                        } else {
                            $this->logger->notice('check miles - empty: get from fare+taxes');
                            $milesEmpty = true;
                        }
                    }

                    foreach ($brandOffers['taxes']['alternatives'] as $alternatives) {
                        if (count($alternatives) === 1) {
                            foreach ($alternatives as $alternative) {
                                if ($alternative['currency'] !== 'FFCURRENCY') {
                                    $taxes = $alternative['amount'];
                                    $currency = $alternative['currency'];
                                }
                            }
                        } else {
                            $this->logger->notice('check miles - empty: get from fare+taxes');
                            $milesEmpty = true;
                        }
                    }
                }

                if (!isset($miles)) {
                    $this->sendNotification('error with miles // ZM');

                    throw new \CheckRetryNeededException(5, 0, 'wrong collecting miles', ACCOUNT_ENGINE_ERROR);
                }
                $headData = [
                    'num_stops'       => $it['stops'],
                    'times'           => ['flight' => null, 'layover' => null],
                    'tickets'         => $brandOffers['seatsRemaining']['count'],
                    'award_type'      => $fareFamilies[$brandOffers['brandId']] ?? null,
                    'classOfService'  => $fareFamilies[$brandOffers['brandId']] ?? null,
                    'redemptions'     => [
                        'miles'   => $miles,
                        'program' => $this->AccountFields['ProviderCode'],
                    ],
                    'payments' => ['currency' => $currency ?? 'USD', 'taxes' => $taxes ?? null, 'fees' => null],
                    // $data['currency'] - там что угодно..разницы нет, т.к. сумм нет
                ];

                if (isset($headData['classOfService'])) {
                    $headData['classOfService'] = $this->clearCOS($headData['classOfService']);
                }

                foreach ($it['segments'] as $segment) {
                    if (count($segment) === 1) {
                        $segment = $listSegments[$segment['@ref']] ?? null;
                    }

                    if (!$segment['flight']) {
                        throw new \CheckException("something wrong with segments", ACCOUNT_ENGINE_ERROR);
                    }
                    $seg = [
                        'departure' => [
                            'date'     => date('Y-m-d H:i', strtotime($segment['departure'])),
                            'dateTime' => strtotime($segment['departure']),
                            'airport'  => $segment['origin'],
                            'terminal' => $segment['flight']['departureTerminal'] ?? null,
                        ],
                        'arrival' => [
                            'date'     => date('Y-m-d H:i', strtotime($segment['arrival'])),
                            'dateTime' => strtotime($segment['arrival']),
                            'airport'  => $segment['destination'],
                            'terminal' => $segment['flight']['arrivalTerminal'] ?? null,
                        ],
                        'meal'       => null,
                        'cabin'      => $cabin, //$segment show selected always
                        'fare_class' => $segment['bookingClass'],
                        'aircraft'   => $segment['equipment'],
                        'flight'     => [$segment['flight']['airlineCode'] . $segment['flight']['flightNumber']],
                        'airline'    => $segment['flight']['airlineCode'],
                        'operator'   => $segment['flight']['operatingAirlineCode'],
                        'times'      => ['flight' => null, 'layover' => null],
                    ];
                    $result['connections'][] = $seg;
                }

                $res = array_merge($headData, $result);
                $this->logger->debug(var_export($res, true), ['pre' => true]);
                $routes[] = $res;
            }
        }

        if (isset($milesEmpty)) {
            $this->sendNotification('check miles - empty // ZM');
        }

        if (empty($routes) && isset($undefItineraryPart)) {
            $this->sendNotification("undefined itineraryPart// ZM");
        }
        $this->logger->debug('Parsed data:');
        $this->logger->debug(var_export($routes, true), ['pre' => true]);

        return $routes;
    }

    private function clearCOS(string $cos): string
    {
        if (preg_match("/^(.+\w+) (?:cabin|class|standard|reward)$/i", $cos, $m)) {
            $cos = $m[1];
        }

        if (preg_match("/^Reward (Ec.+)$/i", $cos, $m)) {
            $cos = $m[1];
        }

        return $cos;
    }

    private function getFareFamilies($data)
    {
        $result = [];

        foreach ($data as $item) {
            foreach ($item['brandLabel'] as $brandLabel) {
                if ($brandLabel['languageId'] === 'en_GB') {
                    $result[$item['brandId']] = $brandLabel['marketingText'];

                    break;
                }
            }
        }

        return $result;
    }

    private function selenium($url): ?string
    {
        $this->logger->notice(__METHOD__);
        $selenium = clone $this;
        $this->http->brotherBrowser($selenium->http);
        $responseData = null;

        try {
            $this->logger->notice("Running Selenium...");
            $selenium->UseSelenium();
//            $selenium->useGoogleChrome(\SeleniumFinderRequest::CHROME_100);
//            $selenium->seleniumOptions->recordRequests = true;
            $selenium->useChromePuppeteer(\SeleniumFinderRequest::CHROME_PUPPETEER_103);
            $selenium->seleniumRequest->setOs(\SeleniumFinderRequest::OS_MAC);
            $selenium->seleniumOptions->addHideSeleniumExtension = false;
            $selenium->disableImages();
            $selenium->http->saveScreenshots = true;

            $selenium->http->start();
            $selenium->Start();

            try {
                $selenium->http->GetURL('https://www.virginaustralia.com/');
//                    $selenium->driver->manage()->addCookie(['name' => 'virginCookiesAccepted', 'value' => 'accepted', 'domain' => ".virginaustralia.com"]);
            } catch (\NoSuchCollectionException | \UnexpectedJavascriptException $e) {
                $this->logger->error('NoSuchCollectionException: ' . $e->getMessage());
                $retry = true;

                return null;
            }
            $cookieBtn = $selenium->waitForElement(\WebDriverBy::xpath("//button[normalize-space()='I accept']"), 10);

            if ($cookieBtn) {
                $cookieBtn->click();
            }
            sleep(5);

            $selenium->http->GetURL($url);

            $selenium->waitFor(function () use ($selenium) {
                return !$selenium->waitForElement(\WebDriverBy::xpath("
                    //span[normalize-space()='This page is coming in for landing']
                    | //div[normalize-space()='Loading...']
                    "),
                        0);
            }, 20);
            $cookieBtn = $selenium->waitForElement(\WebDriverBy::xpath("//button[normalize-space()='I accept']"), 3);

            if ($cookieBtn) {
                $cookieBtn->click();
                sleep(1);
                $selenium->http->GetURL($url);

                $selenium->waitFor(function () use ($selenium) {
                    return !$selenium->waitForElement(\WebDriverBy::xpath("
                    //span[normalize-space()='This page is coming in for landing']
                    | //div[normalize-space()='Loading...']
                    "),
                        0);
                }, 20);
                $this->twice = true;
            }
            sleep(2);
            //div[contains(normalize-space(text()),\"Book early & save\")] - когда twice - иногда редиректит на главную, но курл потом работает
            $selenium->waitFor(function () use ($selenium) {
                return $selenium->waitForElement(\WebDriverBy::xpath("
                    //span[@data-translation='flightSelection.selectYourDepartingFlight']
                    | // span[normalize-space()='Flight not found']
                    | //span[@data-translation='app.failure.description']
                    | //div[contains(normalize-space(text()),\"Book early & save\")]
                "),
                        0);
            }, 20);

            if ($msg = $selenium->waitForElement(\WebDriverBy::xpath("//span[normalize-space()='Flight not found']"), 0)) {
                $this->SetWarning($msg->getText());

                return "no flight";
            }

            if ($msg = $selenium->waitForElement(\WebDriverBy::xpath("
                //p[contains(.,'There seems to be an error with the Velocity login')]
                | //span[contains(normalize-space(),'re sorry, something seems to have gone wrong. Please try to refresh the browser')]
                | //spa[@data-translation='app.failure.description']
                "), 2)) {
                $this->logger->error($msg->getText());
                $retry = true;

                return null;
            }
            $cookieBtn = $selenium->waitForElement(\WebDriverBy::xpath("//button[normalize-space()='I accept']"), 10);

            try {
                $selenium->http->SaveResponse();
            } catch (\WebDriverCurlException $e) {
                $this->logger->error("WebDriverCurlException: " . $e->getMessage());
                $retry = true;

                return null;
            }

            if ($selenium->http->FindSingleNode('//span[contains(text(), "This site can’t be reached") or contains(text(), "This page isn’t working") or contains(text(), "No internet")]')) {
                $retry = true;

                return null;
            }

            if ($cookieBtn) {
                $selenium->http->SaveResponse();

                if ($this->attempt === 2) {
                    $this->logger->error('walks in circles with cookies');

                    throw new \CheckException('The website is experiencing technical difficulties, please try to check later.', ACCOUNT_PROVIDER_ERROR);
                }

                throw new \CheckRetryNeededException(5, 0);
            }

            /*            if (isset($this->twice)) {
                            $this->sendNotification('check restart // ZM');

                            if ($selenium->waitForElement(\WebDriverBy::xpath("//div[contains(normalize-space(text()),\"Book early & save\")]"),
                                5)) {
                                throw new \CheckRetryNeededException(5, 0);
                            }
                        }*/

            if (!isset($this->twice)) {
                $this->waitFor(function () use ($selenium) {
                    return $selenium->driver->findElement(\WebDriverBy::xpath("//span[starts-with(normalize-space(),'Sorry, there are no flight or seat available for the dates')]"))
                    || $selenium->driver->findElement(\WebDriverBy::xpath("//span[starts-with(normalize-space(),'No flights found for your search')]"))
                    || $selenium->driver->findElement(\WebDriverBy::xpath("//h3[contains(.,\"Select your outbound flight\")]"));
                }, 30);
            }

            if ($selenium->waitForElement(\WebDriverBy::xpath("//div[normalize-space()='Loading...']"), 0)) {
                // incapsula
                $retry = true;

                return null;
            }

            // save page to logs
            $selenium->http->SaveResponse();

            $cookies = $selenium->driver->manage()->getCookies();

            foreach ($cookies as $cookie) {
                $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'],
                    $cookie['expiry'] ?? null);
            }

            $this->http->SetBody($selenium->driver->executeScript('return document.documentElement.innerHTML'));
            $this->http->SaveResponse();

            /*try {
                /** @var \SeleniumDriver $seleniumDriver * /
                $seleniumDriver = $selenium->http->driver;
                $requests = $seleniumDriver->browserCommunicator->getRecordedRequests();
no XHR cause its fetch
                foreach ($requests as $n => $xhr) {
                    if (strpos($xhr->request->getUri(), '/products/air/search') !== false) {
                        $responseData = $xhr->response->getBody();
                    }
                }

                if (empty($responseData)) {
                    $this->logger->error('no XHR');
                }
            } catch (\AwardWallet\Common\Selenium\BrowserCommunicatorException | \ErrorException $e) {
                $this->logger->error($e->getMessage());
            }*/

            $this->brandDir = $this->http->FindPreg('/src="\/dx\/VADX\/([^\/]+?)\/js\/\w+\.js/');
        } catch (\ScriptTimeoutException | \TimeOutException $e) {
            $this->logger->error("ScriptTimeoutException exception: " . $e->getMessage());
            $this->DebugInfo = "ScriptTimeoutException";
            $retry = true;
        } catch (\UnknownServerException | \SessionNotCreatedException | \WebDriverCurlException | \WebDriverException | \NoSuchDriverException $e) {
            $this->logger->error("exception: " . $e->getMessage());
            $this->DebugInfo = "exception";
            $retry = true;
        } finally {
            // close Selenium browser
            $selenium->http->cleanup();
            // retries
            if (isset($retry) && ConfigValue(CONFIG_SITE_STATE) != SITE_STATE_DEBUG) {
                $this->logger->debug("[attempt]: {$this->attempt}");

                throw new \CheckRetryNeededException(5, 0);
            }
        }

        return $responseData;
    }

    private function validRoute($fields): bool
    {
        $this->logger->notice(__METHOD__);

        $checkRouteUrl = "https://www.virginaustralia.com/json/flight-route/{$fields['DepCode']}/{$fields['ArrCode']}/destination-port";
        $this->http->RetryCount = 0;
        $this->http->GetURL($checkRouteUrl);

        if ($this->badProxy()) {
            throw new \CheckRetryNeededException(5, 0);
        }

        if ($this->http->currentUrl() === 'https://www.virginaustralia.com/eu/en/_cookiesAcceptance/') {
            $this->http->setCookie('virginCookiesAccepted', 'accepted', 'www.virginaustralia.com');
            $this->http->setCookie('loginData', '', '.virginaustralia.com');
            $this->http->GetURL($checkRouteUrl);
        }
        $this->http->RetryCount = 2;
        $data = $this->http->JsonLog();

        if (!isset($data->domestic)) {
            // second type to check
            $this->http->GetURL("https://www.virginaustralia.com/fragment/ports/vipr_only/origins/AU/");
            $codes = $this->http->FindNodes("//li/a/@data-iata");

            if (!empty($codes)) {
                if (!in_array($fields['DepCode'], $codes)) {
                    $this->SetWarning('no ' . $fields['DepCode'] . ' in list of origins');

                    return false;
                }
                $this->http->GetURL("https://www.virginaustralia.com/fragment/ports/vipr_only/{$fields['DepCode']}/destinations/AU/");
                $codes = $this->http->FindNodes("//li/a/@data-iata");

                if (!empty($codes)) {
                    if (!in_array($fields['ArrCode'], $codes)) {
                        $this->SetWarning('no ' . $fields['ArrCode'] . ' in list of destinations');

                        return false;
                    }

                    return true;
                }
            }

            if ($this->attempt === 0) {
                $this->sendNotification('check retry (routes) // ZM');
            }

            throw new \CheckRetryNeededException(5, 0);
        }

        if (!$data->domestic) {
            $this->SetWarning('Not domestic flight');
        }

        return $data->domestic === true;
    }

    private function validRouteWithPartners($fields): bool
    {
        $this->logger->notice(__METHOD__);

        $originsUrl = "https://www.virginaustralia.com/vacloud/refd/v1/search-airport/origins/";
        $this->http->RetryCount = 0;
        $this->http->GetURL($originsUrl);

        if ($this->badProxy()) {
            throw new \CheckRetryNeededException(5, 0);
        }

        if ($this->http->currentUrl() === 'https://www.virginaustralia.com/eu/en/_cookiesAcceptance/') {
            $this->http->setCookie('virginCookiesAccepted', 'accepted', 'www.virginaustralia.com');
            $this->http->setCookie('loginData', '', '.virginaustralia.com');
            $this->http->GetURL($originsUrl);
        }
        $this->http->RetryCount = 2;

        $data = $this->http->JsonLog(null, 1, true);

        if (!isset($data['geographicalRegions'])) {
            // проверить на всякий маршрут, если был сбой на проверке
            return true;
        }
        $codes = [];

        foreach ($data['geographicalRegions'] as $region) {
            foreach ($region['airports'] as $airport) {
                $codes[] = $airport['code'];
            }
        }

        if (!empty($codes)) {
            if (!in_array($fields['DepCode'], $codes)) {
                $this->SetWarning('no ' . $fields['DepCode'] . ' in list of origins');

                return false;
            }
            $this->http->GetURL("https://www.virginaustralia.com/vacloud/refd/v1/search-airport/destinations/" . $fields['DepCode']);
            $data = $this->http->JsonLog(null, 1, true);

            if (!isset($data['geographicalRegions'])) {
                // проверить на всякий маршрут, если был сбой на проверке
                return true;
            }
            $codes = [];

            foreach ($data['geographicalRegions'] as $region) {
                foreach ($region['airports'] as $airport) {
                    $codes[] = $airport['code'];
                }
            }

            if (!empty($codes)) {
                if (!in_array($fields['ArrCode'], $codes)) {
                    $this->SetWarning('no ' . $fields['ArrCode'] . ' in list of destinations');

                    return false;
                }

                return true;
            }
        }

        if ($this->attempt === 0) {
            $this->sendNotification('check retry (routes) // ZM');
        }

        return true;
    }

    private function badProxy()
    {
        return strpos($this->http->Error, 'Network error 56 - Received HTTP code 407 from proxy after CONNECT') !== false
            || strpos($this->http->Error, 'Network error 56 - Received HTTP code 503 from proxy after CONNECT') !== false
            || strpos($this->http->Error, 'Network error 56 - Received HTTP code 400 from proxy after CONNECT') !== false
            || strpos($this->http->Error, 'Network error 56 - Received HTTP code 403 from proxy after CONNECT') !== false
            || strpos($this->http->Error, 'Network error 28 - Connection timed out after') !== false
            || strpos($this->http->Error, 'Network error 56 - Recv failure') !== false
            || strpos($this->http->Error, 'Network error 7 - Failed to connect to') !== false;
    }
}
