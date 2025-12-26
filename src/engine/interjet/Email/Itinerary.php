<?php

namespace AwardWallet\Engine\interjet\Email;

class Itinerary extends \TAccountChecker
{
    public $mailFiles = "interjet/it-2007257.eml, interjet/it-2389221.eml, interjet/it-2537016.eml, interjet/it-2759684.eml, interjet/it-2783073.eml, interjet/it-2965420.eml, interjet/it-3151852.eml, interjet/it-4596278.eml, interjet/it-5370253.eml, interjet/it-6211918.eml, interjet/it-6285982.eml, interjet/it-6325870.eml, interjet/it-6432172.eml, interjet/it-6432173.eml, interjet/it-6977498.eml, interjet/it-12893541.eml, interjet/it-12893579.eml";

    public $reFrom = '@interjet.com';
    public $reSubject = [
        'en' => 'Interjet Itinerary',
        'es' => 'Interjet Itinerario',
    ];

    public $reBody2 = [
        'es' => 'Destino',
        'en' => 'Arrive',
    ];

    public static $dictionary = [
        'es' => [
            "Confirmation Number :" => "Código de Confirmación:",
            //			"Booking Date:" => "",
            "Name"            => "Nombre",
            "Depart"          => "Origen",
            "Arrive"          => "Destino",
            "date"            => "fecha",
            "flight"          => "vuelo",
            "fare_class"      => "clase_tarifa",
            "depart"          => "origen",
            "depart_time"     => "salida",
            "arrive"          => "destino",
            "arrive_time"     => "llegada",
            "Total"           => ["Total MXN:", "Total USD:", "Total:"],
            "Base Fare:"      => "Total Tarifa Base:",
            "Taxes and Fees:" => "Total impuestos:",
        ],
        'en' => [
            "Confirmation Number :" => ["Confirmation Number :", "Confirmation Number:", "Confirmation code :", "Confirmation code:", "Booking Number:"],
            "Total"                 => ["Total MXN:", "Total USD:", "Total Amount"],
            "Base Fare:"            => ["Base Fare:", "Total Fare Price"],
            "Taxes and Fees:"       => ["Taxes and Fees:", "Total Fees"],
        ],
    ];

    public $lang = 'en';

    private $providerCode = '';

    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->reFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        foreach ($this->reSubject as $re) {
            if (stripos($headers['subject'], $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        // Detecting Provider
        if ($this->assignProvider() === false) {
            return false;
        }

        // Detecting Format
        foreach ($this->reBody2 as $re) {
            if (stripos($this->http->Response['body'], $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->date = strtotime($parser->getHeader('date'));

        $this->http->FilterHTML = false;
        $this->http->SetEmailBody('<meta http-equiv="Content-Type" content="text/html; charset=utf-8">' . $parser->getHTMLBody());

        // Detecting Provider
        if ($this->assignProvider() === false) {
            return false;
        }

        // Detecting Language
        foreach ($this->reBody2 as $lang => $re) {
            if (strpos($this->http->Response['body'], $re) !== false) {
                $this->lang = $lang;

                break;
            }
        }

        $itineraries = [];
        $this->parseHtml($itineraries);

        $classParts = explode('\\', __CLASS__);
        $result = [
            'providerCode' => $this->providerCode,
            'emailType'    => end($classParts) . ucfirst($this->lang),
            'parsedData'   => [
                'Itineraries' => $itineraries,
            ],
        ];

        return $result;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
    }

    private function parseHtml(&$itineraries)
    {
        $patterns = [
            'airport' => '/(.+)\(([A-Z]{3})\)$/',
            'time'    => '/^(\d{1,2}:\d{2}(?:\s*[AP]M)?)\s*(?:\s*hrs)?$/i',
        ];

        $it = [];
        $it['Kind'] = 'T';

        // Status
        if ($this->http->XPath->query('//node()[contains(normalize-space(.),"Your booking has been confirmed")]')->length > 0) {
            $it['Status'] = 'confirmed';
        }

        // RecordLocator
        $it['RecordLocator'] = orval(
            $this->nextText($this->t("Confirmation Number :")),
            CONFNO_UNKNOWN
        );

        // ReservationDate
        $bookingDate = $this->nextText($this->t("Booking Date:"));

        if ($bookingDate) {
            $it['ReservationDate'] = strtotime($bookingDate);
        }

        // Passengers
        $it['Passengers'] = $this->http->FindNodes("//text()[" . $this->eq($this->t("Name")) . "]/ancestor::tr[1]/following-sibling::tr[./td[2]]/td[1]", null, "#^\s*(?:ADT |CHD )?(.+)#");

        $seats = [];

        foreach ($this->http->FindNodes("//text()[" . $this->eq($this->t("Name")) . "]/ancestor::tr[1]/following-sibling::tr[ ./td[3] ]/td[3]") as $item) {
            if (preg_match_all("#(?<flight>\d+)/(?<seat>\d+[A-Z])#", $item, $matches, PREG_SET_ORDER)) {
                foreach ($matches as $m) {
                    $seats[$m['flight']][] = $m['seat'];
                }
            }
        }

        if (count($seats) === 0) {
            $inflightServices = $this->http->XPath->query('//text()[' . $this->eq($this->t("Inflight Services:")) . ']/following::text()[' . $this->eq($this->t("Guest")) . ']/ancestor::tr[1]');

            foreach ($inflightServices as $root) {
                $flightNumber = $this->http->FindSingleNode('./preceding-sibling::tr[normalize-space(.)][1]', $root, true, '/^(?:[A-Z][A-Z\d]|[A-Z\d][A-Z])?\s*(?<flightNumber>\d+)\s+[A-Z]{3}\s*-\s*[A-Z]{3}/');
                $seatTexts = [];
                $seatRows = $this->http->XPath->query('./following-sibling::tr', $root);

                foreach ($seatRows as $seatRow) {
                    if ($this->http->XPath->query('./descendant::text()[' . $this->eq($this->t("Guest")) . ']', $seatRow)->length > 0) {
                        break;
                    }

                    if ($seatText = $this->http->FindSingleNode('./td[2]', $seatRow, true, '/^(\d{1,3}[A-z])$/')) {
                        $seatTexts[] = $seatText;
                    }
                }

                if ($flightNumber && count($seatTexts)) {
                    $seats[$flightNumber] = $seatTexts;
                }
            }
        }

        $xpathFragment1 = '//tr[not(.//tr) and ./td[' . $this->eq($this->t('Depart')) . '] and ./td[' . $this->eq($this->t('Arrive')) . ']]';

        $departFieldPosition = 3; // default
        $departPreFields = $this->http->XPath->query($xpathFragment1 . '/td[' . $this->eq($this->t('Depart')) . ']/preceding-sibling::td[normalize-space(.)]')->length;

        if ($departPreFields > 0) {
            $departFieldPosition = $departPreFields + 1;
        }

        $stopsFieldPosition = null; // default
        $stopsFields = $this->http->XPath->query($xpathFragment1 . '/td[' . $this->eq($this->t('Stops')) . ']');

        if ($stopsFields->length > 0) {
            $stopsPreFields = $this->http->XPath->query('./following-sibling::td[normalize-space(.)]', $stopsFields->item(0))->length;
            $stopsFieldPosition = "last() - $stopsPreFields";
        }

        $segments = $this->http->XPath->query($xpathFragment1 . '/following-sibling::tr[ ./td[6] ]');

        foreach ($segments as $segment) {
            $seg = [];

            $date = $this->http->FindSingleNode('./td[1]', $segment);

            // AirlineName
            // FlightNumber
            // Seats
            $flight = $this->http->FindSingleNode('./td[2]', $segment);

            if (preg_match('/^(?<airline>[A-Z][A-Z\d]|[A-Z\d][A-Z])?\s*(?<flightNumber>\d+)$/', $flight, $matches)) {
                if (!empty($matches['airline'])) {
                    $seg['AirlineName'] = $matches['airline'];
                } elseif ($this->providerCode === '' || $this->providerCode === 'interjet') {
                    $seg['AirlineName'] = '4O';
                }
                $seg['FlightNumber'] = $matches['flightNumber'];

                if (!empty($seats[$seg['FlightNumber']])) {
                    $seg['Seats'] = $seats[$seg['FlightNumber']];
                }
            }

            $fareClass = $this->http->FindSingleNode('./td[position()>2 and count(./following-sibling::td)=4]', $segment);

            if (preg_match('/^([A-Z]{1,2})$/', $fareClass, $matches)) {
                $seg['BookingClass'] = $matches[1];
            } elseif (preg_match('/^([\w\s]{3,})\(([A-Z]{1,2})\)$/', $fareClass, $matches)) {
                $seg['Cabin'] = $matches[1];
                $seg['BookingClass'] = $matches[2];
            } elseif (preg_match('/^([\w\s]{3,})$/', $fareClass, $matches)) {
                $seg['Cabin'] = $matches[1];
            }

            $airportDep = $this->http->FindSingleNode("./td[$departFieldPosition]", $segment);

            if (preg_match($patterns['airport'], $airportDep, $matches)) {
                $seg['DepName'] = trim($matches[1]);
                $seg['DepCode'] = $matches[2];
            } else {
                $seg['DepName'] = $airportDep;
            }

            $timeDep = $this->http->FindSingleNode("./td[$departFieldPosition + 1]", $segment, true, $patterns['time']);

            $airportArr = $this->http->FindSingleNode("./td[$departFieldPosition + 2]", $segment);

            if (preg_match($patterns['airport'], $airportArr, $matches)) {
                $seg['ArrName'] = trim($matches[1]);
                $seg['ArrCode'] = $matches[2];
            } else {
                $seg['ArrName'] = $airportDep;
            }

            $timeArr = $this->http->FindSingleNode("./td[$departFieldPosition + 3]", $segment, $patterns['time']);

            if ($date && $timeDep && $timeArr) {
                $seg['DepDate'] = strtotime($this->normalizeDate($date . ', ' . $timeDep));
                $seg['ArrDate'] = strtotime($this->normalizeDate($date . ', ' . $timeArr));
            }

            // Stops
            if ($stopsFieldPosition !== null) {
                $stops = $this->http->FindSingleNode("./td[$stopsFieldPosition]", $segment, true, '/^(\d{1,3})$/');

                if ($stops !== null) {
                    $seg['Stops'] = $stops;
                }
            }

            $it['TripSegments'][] = $seg;
        }

        // Currency
        // TotalCharge
        // BaseFare
        // Tax
        $totalPayment = $this->http->FindSingleNode('//text()[' . $this->starts($this->t("Total")) . ']/ancestor::tr[1]');

        if (
            preg_match('/^[^:]+(?<currency>[A-Z]{3})\s*:\s*\$(?<charge>\d[,.\d\s]*)$/', $totalPayment, $matches) // Total MXN: $9615.77
            || preg_match('/^[^:]+:\s*(?<charge>\d[,.\d\s]*?)\s*(?<currency>[A-Z]{3})$/', $totalPayment, $matches) // Total Amount : 1398.00 MYR
        ) {
            $it['Currency'] = $matches['currency'];
            $it['TotalCharge'] = $this->amount($matches['charge']);
            $baseFare = $this->http->FindSingleNode('//text()[' . $this->starts($this->t("Base Fare:")) . ']/ancestor::tr[1]');

            if (
                preg_match('/^[^:]+:\s*\$(?<charge>\d[,.\d\s]*)$/', $baseFare, $m)
                || preg_match('/^[^:]+:\s*(?<charge>\d[,.\d\s]*?)\s*' . preg_quote($matches['currency'], '/') . '$/', $baseFare, $m)
            ) {
                $it['BaseFare'] = $this->amount($m['charge']);
            }
            $taxes = $this->http->FindSingleNode('//text()[' . $this->starts($this->t("Taxes and Fees:")) . ']/ancestor::tr[1]');

            if (
                preg_match('/^[^:]+:\s*\$(?<charge>\d[,.\d\s]*)$/', $taxes, $m)
                || preg_match('/^[^:]+:\s*(?<charge>\d[,.\d\s]*?)\s*' . preg_quote($matches['currency'], '/') . '$/', $taxes, $m)
            ) {
                $it['Tax'] = $this->amount($m['charge']);
            }
        }

        $itineraries[] = $it;
    }

    private function assignProvider()
    {
        $rule = $this->contains(['Thanks for purchasing with Interjet', 'Gracias por comprar con Interjet']);
        $condition1 = $this->http->XPath->query('//node()[' . $rule . ' or contains(.,"www.interjet.com") or contains(.,"@interjet.com")]')->length > 0;
        $condition2 = $this->http->XPath->query('//a[contains(@href,"//www.interjet.com")]')->length > 0;

        if ($condition1 || $condition2) {
            $this->providerCode = 'interjet';

            return true;
        }

        $condition1 = $this->http->XPath->query('//node()[contains(normalize-space(.),"Thank you for choosing AirAsia") or contains(.,"www.airasia.com")]')->length > 0;
        $condition2 = $this->http->XPath->query('//a[contains(@href,"//www.airasia.com")]')->length > 0;

        if ($condition1 || $condition2) {
            $this->providerCode = 'airasia';

            return true;
        }

        return false;
    }

    public static function getEmailProviders()
    {
        return ['interjet', 'airasia'];
    }

    private function normalizeDate($string)
    {
        if (preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{4}), (\d{1,2}:\d{2})(?:\s*[AP]M|\s*hrs)?$/i', $string, $matches)) {
            $day = $matches[1];
            $month = $matches[2];
            $year = $matches[3];
            $time = $matches[4];
        }

        if (isset($day, $month, $year, $time)) {
            return $day . '.' . $month . '.' . $year . ' ' . $time;
        }

        return $string;
    }

    private function nextText($field, $root = null, $n = 1)
    {
        $rule = $this->eq($field);

        return $this->http->FindSingleNode("(.//text()[{$rule}])[1]/following::text()[string-length(normalize-space(.))>1][{$n}]", $root);
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function amount($s)
    {
        return (float) str_replace(",", ".", preg_replace("#[.,](\d{3})#", "$1", $this->re("#([\d\.,]+)#", $s)));
    }

    private function currency($s)
    {
        $sym = [
            '€'=> 'EUR',
            '$'=> 'USD',
        ];

        if ($code = $this->re("#(?:^|\s)([A-Z]{3})(?:$|\s)#", $s)) {
            return $code;
        }

        foreach ($sym as $f=>$r) {
            if (strpos($s, $f) !== false) {
                return $r;
            }
        }

        return null;
    }

    private function eq($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'normalize-space(.)="' . $s . '"'; }, $field)) . ')';
    }

    private function starts($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'starts-with(normalize-space(.),"' . $s . '")'; }, $field)) . ')';
    }

    private function contains($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'contains(normalize-space(.),"' . $s . '")'; }, $field)) . ')';
    }

    private function opt($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return '';
        }

        return '(?:' . implode('|', array_map(function ($s) { return preg_quote($s, '/'); }, $field)) . ')';
    }
}
