<?php

namespace AwardWallet\Engine\lanpass\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class BoardingPassNoFlightNumber extends \TAccountChecker
{
    public $mailFiles = "lanpass/it-126860111.eml, lanpass/it-127831837.eml, lanpass/it-536505665.eml, lanpass/it-540414849.eml, lanpass/it-571429313.eml";

    private $detectSubject = [
        // pt
        'Aqui está o seu cartão de embarque',
        'Confira os horários do seu voo a',
        //es
        'Aquí está tu tarjeta de embarque',
        //en
        "Here's your boarding pass",
    ];

    private $detectBody = [
        // pt
        'pt' => ['Aqui está o seu cartão de embarque', 'Complete a documentação para sua viagem', 'Aqui estão as informações necessárias para que você comece sua viagem'],
        //es
        'es' => ['Revisa tus tarjetas de embarque'],
        //en
        'en' => ['Now you can check your boarding pass', 'Get your Automatic Check-in'],
    ];

    private $lang = '';

    private static $dictionary = [
        'pt' => [
            'Ver cartão'      => ['Ver cartão', 'Conferir o cartão de embarque'],
        ],
        'es' => [
            'Ver cartão'                      => 'Ver tarjeta',
            'Revise seus cartões de embarque' => 'Revisa tus tarjetas de embarque',
            'às'                              => 'a las',
        ],
        'en' => [
            'Ver cartão'                      => ['See boarding pass'],
            'Revise seus cartões de embarque' => 'Check your boarding passes',
            'às'                              => 'to',
        ],
    ];

    public function detectEmailFromProvider($from)
    {
        return stripos($from, 'info@mail.latam.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->detectEmailFromProvider($headers['from']) !== true) {
            return false;
        }

        foreach ($this->detectSubject as $detectSubject) {
            if (stripos($headers['subject'], $detectSubject) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query('//a[contains(@href,".latamairlines.com")]')->length === 0) {
            return false;
        }

        foreach ($this->detectBody as $detectBody) {
            foreach ($detectBody as $dBody) {
                if ($this->http->XPath->query("//*[" . $this->contains($dBody) . "]")->length > 0) {
                    return $this->assignLang();
                }
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();

        if (empty($this->lang)) {
            $this->logger->debug("Can't determine a language!");

            return $email;
        }

        $this->parseFlight($parser, $email);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
    }

    public function genUuid()
    {
        return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            // 32 bits for "time_low"
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            // 16 bits for "time_mid"
            mt_rand(0, 0xffff),
            // 16 bits for "time_hi_and_version",
            // four most significant bits holds version number 4
            mt_rand(0, 0x0fff) | 0x4000,
            // 16 bits, 8 bits for "clk_seq_hi_res",
            // 8 bits for "clk_seq_low",
            // two most significant bits holds zero and one for variant DCE1.1
            mt_rand(0, 0x3fff) | 0x8000,
            // 48 bits for "node"
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
    }

    private function parseFlight(\PlancakeEmailParser $parser, Email $email)
    {
        $emailUrl = $this->http->FindSingleNode("//a[{$this->eq($this->t('Ver cartão'))}]/@href");
        $url = '';

        // https://www.latamairlines.com/br/pt/cartao-de-embarque?orderId=LA9575434ONAB&passengerId=1&segmentIndex=0&itineraryId=1&lastName=CATAO%20JUNIOR&utm_campaign=br_latam_eim_email_preflight_meta_bpt_v2_EventPassengerCheckInSuccess_20230831&utm_medium=email&utm_source=eim
        // https://www.latamairlines.com/ar/es/boarding-pass?orderId=LA4698908IYQU&passengerId=1&segmentIndex=0&itineraryId=1&lastName=HERNANDEZ%20BRICENO&utm_campaign=ar_latam_eim_email_preflight_meta_bpt_v2_EventPassengerCheckInSuccess_20231010&utm_medium=email&utm_source=eim
        if (preg_match("/\.latamairlines\.com\\/(?<country>[a-z]{2})\\/(?<lang>[a-z]{2})\\/(?:cartao-de-embarque|boarding-pass).*orderId=(?<order>[A-Z\d]+)(?:&.+)?&lastName=(?<name>[^&]+)&/u", $emailUrl, $m)) {
            $url = "https://www.latamairlines.com/bff/web/preflight/boarding-passes/orders/{$m['order']}?lastName={$m['name']}&action=view";
            $siteCountry = $m['country'];
            $siteLang = $m['lang'];
        }

        if (!empty($url)) {
            $http2 = clone $this->http;

            $headers = [
                'User-Agent'                  => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/116.0.0.0 Safari/537.36',
                'Authority'                   => 'www.latamairlines.com',
                'Accept'                      => 'application/json, text/plain, */*',
                'Accept-Encoding'             => 'gzip',
                'Referer'                     => $emailUrl,
                'X-latam-Application-Name'    => 'web-boarding-pass',
                'X-latam-Action-Name'         => 'boarding-pass.home.initialload',
                'X-latam-Client-Name'         => 'web-boarding-pass',
                'X-latam-Application-Country' => $siteCountry,
                'X-latam-Home-Request'        => strtoupper($siteCountry),
                'X-latam-Application-Lang'    => $siteLang,
                // 'X-latam-request-id'          => '08cd9484-d7bc-4f11-9c93-a93e26d720b4',
                // 'X-latam-App-Session-Id'      => '257290c0-3474-4c6a-9651-775ecde495a7',
                // 'X-latam-track-id'            => '7404d2aa-e31a-4d84-a643-ed77d1d19774',
                'X-latam-request-id'          => $this->genUuid(),
                'X-latam-App-Session-Id'      => $this->genUuid(),
                'X-latam-track-id'            => $this->genUuid(),
            ];

            $http2->getUrl($url, $headers);
            // $this->logger->debug('$date = '.print_r( $http2->Response['body'],true));

            $json = json_decode($http2->Response['body'], true);
            // $json = '{"reloc":"INTWGT","orderId":"LA9573634SUFY","emergencyContactRequired":false,"orderCharacteristics":[],"itineraryParts":[{"segments":[{"arrival":{"city":{"code":"GRU","name":"São Paulo"},"airport":{"airportCode":"GRU","airportName":"Guarulhos Intl."},"dateTime":{"date":"08/12/23","fullDate":"08 de dez. de 2023","time":"16:35"}},"departure":{"city":{"code":"POA","name":"Porto Alegre"},"airport":{"airportCode":"POA","airportName":"Salgado Filho"},"dateTime":{"date":"08/12/23","fullDate":"08 de dez. de 2023","time":"14:40"}},"flightNumber":"LA4677","gate":"-","segmentId":"4677-POA-GRU","terminal":"-","open":"","close":"","ancillariesEnabled":true,"stops":[],"operatingFlightNumber":null,"flightOperator":"JJ","operatingAirlineCode":"LA","operatingAirlineReloc":"","cabinClass":"Economy","operatingAirline":{"code":"JJ","airline":"LATAM Airlines Brasil"},"deltaTime":"1 h 15 min.","segmentIndex":"0"},{"arrival":{"city":{"code":"CDG","name":"Paris"},"airport":{"airportCode":"CDG","airportName":"Charles De Gaulle"},"dateTime":{"date":"09/12/23","fullDate":"09 de dez. de 2023","time":"9:20"}},"departure":{"city":{"code":"GRU","name":"São Paulo"},"airport":{"airportCode":"GRU","airportName":"Guarulhos Intl."},"dateTime":{"date":"08/12/23","fullDate":"08 de dez. de 2023","time":"17:50"}},"flightNumber":"LA8068","gate":"-","segmentId":"8068-GRU-CDG","terminal":"-","open":"","close":"","ancillariesEnabled":true,"stops":[],"operatingFlightNumber":null,"flightOperator":"JJ","operatingAirlineCode":"LA","operatingAirlineReloc":"","cabinClass":"Economy","operatingAirline":{"code":"JJ","airline":"LATAM Airlines Brasil"},"deltaTime":"","segmentIndex":"1"}],"stop":"1","totalDuration":"880"},{"segments":[{"arrival":{"city":{"code":"GRU","name":"São Paulo"},"airport":{"airportCode":"GRU","airportName":"Guarulhos Intl."},"dateTime":{"date":"31/12/23","fullDate":"31 de dez. de 2023","time":"20:00"}},"departure":{"city":{"code":"CDG","name":"Paris"},"airport":{"airportCode":"CDG","airportName":"Charles De Gaulle"},"dateTime":{"date":"31/12/23","fullDate":"31 de dez. de 2023","time":"12:00"}},"flightNumber":"LA8067","gate":"-","segmentId":"8067-CDG-GRU","terminal":"-","open":"","close":"","ancillariesEnabled":true,"stops":[],"operatingFlightNumber":null,"flightOperator":"JJ","operatingAirlineCode":"LA","operatingAirlineReloc":"","cabinClass":"Economy","operatingAirline":{"code":"JJ","airline":"LATAM Airlines Brasil"},"deltaTime":"2 h 20 min.","segmentIndex":"0"},{"arrival":{"city":{"code":"POA","name":"Porto Alegre"},"airport":{"airportCode":"POA","airportName":"Salgado Filho"},"dateTime":{"date":"01/01/24","fullDate":"01 de jan. de 2024","time":"0:05"}},"departure":{"city":{"code":"GRU","name":"São Paulo"},"airport":{"airportCode":"GRU","airportName":"Guarulhos Intl."},"dateTime":{"date":"31/12/23","fullDate":"31 de dez. de 2023","time":"22:20"}},"flightNumber":"LA3428","gate":"-","segmentId":"3428-GRU-POA","terminal":"-","open":"","close":"","ancillariesEnabled":true,"stops":[],"operatingFlightNumber":null,"flightOperator":"JJ","operatingAirlineCode":"LA","operatingAirlineReloc":"","cabinClass":"Economy","operatingAirline":{"code":"JJ","airline":"LATAM Airlines Brasil"},"deltaTime":"","segmentIndex":"1"}],"stop":"1","totalDuration":"965"}],"passengers":[{"id":"ADT_1","index":1,"category":"LATAM","firstName":"pedro","lastName":"rossa","middleName":"","passengerId":1,"prefix":"MR","seqNumber":null,"passengerNameNumber":"01.01","emergencyContacts":[],"profile":{},"type":"ADT"},{"id":"ADT_2","index":2,"category":"","firstName":"clarissa","lastName":"sponchiado da rocha","middleName":"","passengerId":2,"prefix":"MS","seqNumber":null,"passengerNameNumber":"02.01","emergencyContacts":[],"profile":{},"type":"ADT"}],"boardingPasses":[{"hasLegalAgeRestriction":false,"active":true,"barcode":"","checkinStatus":false,"checked":"PENDING","group":"","passengerId":1,"seatNumber":"23A","segmentId":"4677-POA-GRU","seqNumber":"","vidi":false,"isTSA":false,"seatStatus":"ASSIGNED_FREE_SEAT","waitingForEmergencySeat":false,"checkInDeepLink":null,"baggage":[{"maximumWeight":0,"totalUnits":1,"baggageAllowanceType":"PERSONAL_ITEM"},{"maximumWeight":10,"totalUnits":1,"baggageAllowanceType":"CARRYON_SMALL"},{"maximumWeight":23,"totalUnits":1,"baggageAllowanceType":"CHECKED_BAGGAGE"}],"needsDocumentation":true,"isExitSeat":false,"askForEmergencySeat":false,"checkInError":false,"seatAfterStopNumber":null,"cabinClass":"Economy"},{"hasLegalAgeRestriction":false,"active":true,"barcode":"","checkinStatus":false,"checked":"PENDING","group":"","passengerId":1,"seatNumber":"28C","segmentId":"8068-GRU-CDG","seqNumber":"","vidi":false,"isTSA":false,"seatStatus":"ASSIGNED_FREE_SEAT","waitingForEmergencySeat":false,"checkInDeepLink":null,"baggage":[{"maximumWeight":0,"totalUnits":1,"baggageAllowanceType":"PERSONAL_ITEM"},{"maximumWeight":10,"totalUnits":1,"baggageAllowanceType":"CARRYON_SMALL"},{"maximumWeight":23,"totalUnits":1,"baggageAllowanceType":"CHECKED_BAGGAGE"}],"needsDocumentation":true,"isExitSeat":false,"askForEmergencySeat":false,"checkInError":false,"seatAfterStopNumber":null,"cabinClass":"Economy"},{"hasLegalAgeRestriction":false,"active":true,"barcode":"","checkinStatus":false,"checked":"PENDING","group":"","passengerId":1,"seatNumber":"42C","segmentId":"8067-CDG-GRU","seqNumber":"","vidi":false,"isTSA":false,"seatStatus":"ASSIGNED_FREE_SEAT","waitingForEmergencySeat":false,"checkInDeepLink":null,"baggage":[{"maximumWeight":0,"totalUnits":1,"baggageAllowanceType":"PERSONAL_ITEM"},{"maximumWeight":10,"totalUnits":1,"baggageAllowanceType":"CARRYON_SMALL"},{"maximumWeight":23,"totalUnits":1,"baggageAllowanceType":"CHECKED_BAGGAGE"}],"needsDocumentation":true,"isExitSeat":false,"askForEmergencySeat":false,"checkInError":false,"seatAfterStopNumber":null,"cabinClass":"Economy"},{"hasLegalAgeRestriction":false,"active":true,"barcode":"","checkinStatus":false,"checked":"PENDING","group":"","passengerId":1,"seatNumber":"10A","segmentId":"3428-GRU-POA","seqNumber":"","vidi":false,"isTSA":false,"seatStatus":"ASSIGNED_FREE_SEAT","waitingForEmergencySeat":false,"checkInDeepLink":null,"baggage":[{"maximumWeight":0,"totalUnits":1,"baggageAllowanceType":"PERSONAL_ITEM"},{"maximumWeight":10,"totalUnits":1,"baggageAllowanceType":"CARRYON_SMALL"},{"maximumWeight":23,"totalUnits":1,"baggageAllowanceType":"CHECKED_BAGGAGE"}],"needsDocumentation":true,"isExitSeat":false,"askForEmergencySeat":false,"checkInError":false,"seatAfterStopNumber":null,"cabinClass":"Economy"},{"hasLegalAgeRestriction":false,"active":true,"barcode":"","checkinStatus":false,"checked":"PENDING","group":"","passengerId":2,"seatNumber":"23B","segmentId":"4677-POA-GRU","seqNumber":"","vidi":false,"isTSA":false,"seatStatus":"ASSIGNED_FREE_SEAT","waitingForEmergencySeat":false,"checkInDeepLink":null,"baggage":[{"maximumWeight":0,"totalUnits":1,"baggageAllowanceType":"PERSONAL_ITEM"},{"maximumWeight":10,"totalUnits":1,"baggageAllowanceType":"CARRYON_SMALL"},{"maximumWeight":23,"totalUnits":1,"baggageAllowanceType":"CHECKED_BAGGAGE"}],"needsDocumentation":true,"isExitSeat":false,"askForEmergencySeat":false,"checkInError":false,"seatAfterStopNumber":null,"cabinClass":"Economy"},{"hasLegalAgeRestriction":false,"active":true,"barcode":"","checkinStatus":false,"checked":"PENDING","group":"","passengerId":2,"seatNumber":"28B","segmentId":"8068-GRU-CDG","seqNumber":"","vidi":false,"isTSA":false,"seatStatus":"ASSIGNED_FREE_SEAT","waitingForEmergencySeat":false,"checkInDeepLink":null,"baggage":[{"maximumWeight":0,"totalUnits":1,"baggageAllowanceType":"PERSONAL_ITEM"},{"maximumWeight":10,"totalUnits":1,"baggageAllowanceType":"CARRYON_SMALL"},{"maximumWeight":23,"totalUnits":1,"baggageAllowanceType":"CHECKED_BAGGAGE"}],"needsDocumentation":true,"isExitSeat":false,"askForEmergencySeat":false,"checkInError":false,"seatAfterStopNumber":null,"cabinClass":"Economy"},{"hasLegalAgeRestriction":false,"active":true,"barcode":"","checkinStatus":false,"checked":"PENDING","group":"","passengerId":2,"seatNumber":"42B","segmentId":"8067-CDG-GRU","seqNumber":"","vidi":false,"isTSA":false,"seatStatus":"ASSIGNED_FREE_SEAT","waitingForEmergencySeat":false,"checkInDeepLink":null,"baggage":[{"maximumWeight":0,"totalUnits":1,"baggageAllowanceType":"PERSONAL_ITEM"},{"maximumWeight":10,"totalUnits":1,"baggageAllowanceType":"CARRYON_SMALL"},{"maximumWeight":23,"totalUnits":1,"baggageAllowanceType":"CHECKED_BAGGAGE"}],"needsDocumentation":true,"isExitSeat":false,"askForEmergencySeat":false,"checkInError":false,"seatAfterStopNumber":null,"cabinClass":"Economy"},{"hasLegalAgeRestriction":false,"active":true,"barcode":"","checkinStatus":false,"checked":"PENDING","group":"","passengerId":2,"seatNumber":"10B","segmentId":"3428-GRU-POA","seqNumber":"","vidi":false,"isTSA":false,"seatStatus":"ASSIGNED_FREE_SEAT","waitingForEmergencySeat":false,"checkInDeepLink":null,"baggage":[{"maximumWeight":0,"totalUnits":1,"baggageAllowanceType":"PERSONAL_ITEM"},{"maximumWeight":10,"totalUnits":1,"baggageAllowanceType":"CARRYON_SMALL"},{"maximumWeight":23,"totalUnits":1,"baggageAllowanceType":"CHECKED_BAGGAGE"}],"needsDocumentation":true,"isExitSeat":false,"askForEmergencySeat":false,"checkInError":false,"seatAfterStopNumber":null,"cabinClass":"Economy"}],"orderWithDerivableCkinError":false}';
            // $this->logger->debug('$json = '.print_r( $json,true));

            if (is_array($json) && isset($json['itineraryParts'])) {
                $f = $email->add()->flight();

                // General
                $travellers = array_map(function ($v) {
                    return ucwords(implode(' ', array_filter([$v['firstName'], $v['middleName'], $v['lastName']])));
                }, $json['passengers']);

                $f->general()
                    ->confirmation($json['reloc'], 'Recordlocator')
                    ->confirmation($json['orderId'], 'Order Id')
                    ->travellers($travellers, true)
                ;

                $seats = [];

                foreach ($json['boardingPasses'] as $bp) {
                    $seats[$bp['segmentId']][] = $bp['seatNumber'];
                }

                // Segments
                foreach ($json['itineraryParts'] as $sParts) {
                    foreach ($sParts['segments'] as $segArray) {
                        $s = $f->addSegment();

                        // Airline
                        $s->airline()
                            ->name($this->re('/^([A-Z\d]{2})\d{1,5}$/', $segArray['flightNumber']))
                            ->number($this->re('/^[A-Z\d]{2}(\d{1,5})$/', $segArray['flightNumber']))
                        ;

                        if (!empty($segArray['operatingFlightNumber'])) {
                            $s->airline()
                                ->carrierName($this->re('/^([A-Z\d]{2})\d{1,5}$/', $segArray['operatingFlightNumber']))
                                ->carrierNumber($this->re('/^[A-Z\d]{2}(\d{1,5})$/', $segArray['operatingFlightNumber']));

                            if (!empty($segArray['operatingAirlineReloc'])) {
                                $s->airline()
                                    ->carrierConfirmation($segArray['operatingAirlineReloc']);
                            }
                        } else {
                            $s->airline()
                                ->operator($segArray['flightOperator'] ?? null, true, true);
                        }

                        // Departure
                        $s->departure()
                            ->code($segArray['departure']['airport']['airportCode'])
                            ->name($segArray['departure']['airport']['airportName'])
                            ->date($this->normalizeDate($segArray['departure']['dateTime']['date'] . ', ' . $segArray['departure']['dateTime']['time']))
                        ;

                        // Arrival
                        $s->arrival()
                            ->code($segArray['arrival']['airport']['airportCode'])
                            ->name($segArray['arrival']['airport']['airportName'])
                            ->date($this->normalizeDate($segArray['arrival']['dateTime']['date'] . ', ' . $segArray['arrival']['dateTime']['time']))
                        ;

                        // Extra
                        $s->extra()
                            ->cabin($segArray['cabinClass'])
                        ;

                        if (isset($seats[$segArray['segmentId']])) {
                            $s->extra()
                                ->seats($seats[$segArray['segmentId']]);
                        }

                        foreach ($travellers as $traveller) {
                            $bp = $email->add()->bpass();

                            $bp->setTraveller($traveller);
                            $bp->setFlightNumber($s->getAirlineName() . $s->getFlightNumber());
                            $bp->setDepDate($s->getDepDate());
                            $bp->setUrl($this->http->FindSingleNode("//a[{$this->contains($this->t('Ver cartão'))}]/@href"));
                        }
                    }
                }
            }
        }

        if (!isset($f)) {
            $f = $email->add()->flight();

            $confs = array_filter(array_unique($this->http->FindNodes("//a[{$this->eq($this->t('Ver cartão'))}]/@href", null, "/orderId=([A-Z\d]+)&/")));

            foreach ($confs as $conf) {
                $f->general()
                    ->confirmation($conf);
            }

            if (empty($confs)) {
                $f->general()
                    ->noConfirmation();
            }
            $f->general()
                ->travellers($this->http->FindNodes("//text()[{$this->contains($this->t('Ver cartão'))}]/preceding::text()[normalize-space()][1]"))
            ;

            $s = $f->addSegment();

            $text = implode(' ', $this->http->FindNodes(" //text()[{$this->eq($this->t('Revise seus cartões de embarque'))}]/following::text()[normalize-space()][following::text()[normalize-space()][position()][{$this->eq($this->t('Ver cartão'))}] and not(preceding::text()[{$this->eq($this->t('Ver cartão'))}]) and not({$this->eq($this->t('Ver cartão'))})][position() <= last()-1]"));

            if (preg_match("/(.+) a (.+)\s+\W*\s+([\d\/].+\d{1,2}:\d{2})\s*$/", $text, $m)) {
                $s->departure()
                    ->noCode()
                    ->name($m[1])
                    ->date($this->normalizeDate($m[3]))
                ;
                $s->arrival()
                    ->noCode()
                    ->name($m[2])
                    ->noDate()
                ;

                $s->airline()
                    ->noName()
                    ->noNumber();
            }
        }
    }

    private function assignLang(): bool
    {
        foreach (self::$dictionary as $lang => $phrases) {
            if (empty($phrases['Ver cartão'])) {
                continue;
            }

            if ($this->http->XPath->query("//node()[{$this->contains($phrases['Ver cartão'])}]")->length > 0) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
    }

    private function t(string $phrase)
    {
        if (!isset(self::$dictionary, $this->lang) || empty(self::$dictionary[$this->lang][$phrase])) {
            return $phrase;
        }

        return self::$dictionary[$this->lang][$phrase];
    }

    private function normalizeDate($date)
    {
        // $this->logger->debug('$date 1 = '.print_r( $date,true));
        $in = [
            // 13/12/21 às 09:05
            // 08/12/23, 16:35
            "/^\s*(\d+)\/(\d+)\/(\d+)(?: {$this->opt($this->t('às'))} |\s*,\s*)(\d{1,2}:\d{2})\s*$/u",
            //24/12/23, 7:45 am
            "/^(\d+)\/(\d+)\/(\d+)\,\s*([\d\:]+\s*a?p?m)$/u",
        ];
        $out = [
            "$1.$2.20$3, $4",
            "$1.$2.20$3, $4",
        ];

        $str = preg_replace($in, $out, $date);
        // $this->logger->debug('$date 1 = '.print_r( $date,true));

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return strtotime($str);
    }

    private function contains($field, $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'contains(normalize-space(' . $node . '),"' . $s . '")';
        }, $field)) . ')';
    }

    private function eq($field, $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'normalize-space(' . $node . ')="' . $s . '"';
        }, $field)) . ')';
    }

    private function starts($field, $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'starts-with(normalize-space(' . $node . '),"' . $s . '")';
        }, $field)) . ')';
    }

    private function opt($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return '';
        }

        return '(?:' . implode('|', array_map(function ($s) {
            return preg_quote($s, '/');
        }, $field)) . ')';
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }
}
