<?php

namespace AwardWallet\Engine\lufthansa\Email;

use AwardWallet\Engine\MonthTranslate;

class Itinerary1 extends \TAccountCheckerExtended
{
    public $mailFiles = "lufthansa/it-1.eml, lufthansa/it-10.eml, lufthansa/it-13.eml, lufthansa/it-1655815.eml, lufthansa/it-1655821.eml, lufthansa/it-1772594.eml, lufthansa/it-2.eml, lufthansa/it-4.eml";

    public $date;
    public $year;

    private $langDetectors = [
        'de' => ['Ihre Flugdaten'],
        'it' => ['Codice di prenotazione:'],
        'en' => ['Your Booked Travel'],
    ];

    private $lang = '';

    private static $dict = [
        'de' => [],
        'it' => [],
        'en' => [],
    ];

    public function ParseMailReservation()
    {
        $result = [
            "Kind"         => "T",
            "TripSegments" => [],
        ];
        $confNo = $this->http->FindPreg("/:\s([A-Z0-9]{6})[^A-Z0-9]/");

        if (!$confNo) {
            $confNo = $this->http->FindPreg("/[^A-Z0-9]([A-Z0-9]{6})[^A-Z0-9]/");
        }

        if ($confNo) {
            $result["RecordLocator"] = $confNo;
        }
        $rows = $this->http->XPath->query("//tr[td[@width='225']][td[@width='70']]");

        if ($rows->length > 0) {
            $flights = $this->http->XPath->query("following-sibling::tr", $rows->item(0));

            for ($i = 0; $i < $flights->length; $i++) {
                $row = $flights->item($i);
                $segment = [];
                $date = $this->http->FindSingleNode("td[1]", $row);

                if ($this->year and preg_match("/(\d\d)\.(\d\d)/", $date, $m)) {
                    $date = $m[2] . '/' . $m[1] . '/' . $this->year;
                }
                $time = $this->http->FindSingleNode("td[2]", $row, true, "/\d\d\:\d\d/");
                $segment["DepName"] = $this->http->FindSingleNode("td[2]", $row, true, "/\d\s+(\S.*)$/");

                if ($date and $time) {
                    $segment["DepDate"] = strtotime("$date $time", $this->date);
                }
                $segment["DepCode"] = TRIP_CODE_UNKNOWN;
                $time = $this->http->FindSingleNode("td[3]", $row, true, "/\d\d\:\d\d/");
                $segment["ArrName"] = $this->http->FindSingleNode("td[3]", $row, true, "/\d\s+(\S.*)$/");

                if ($date and $time) {
                    $segment["ArrDate"] = strtotime("$date $time", $this->date);
                }
                $segment["ArrCode"] = TRIP_CODE_UNKNOWN;

                if ($segment["ArrDate"] > 0 && $segment["ArrDate"] < $segment["DepDate"]) {
                    $segment["ArrDate"] += SECONDS_PER_DAY;
                }
                //				if ($segment["DepDate"] > 0 && $segment["DepDate"] < strtotime('- 6 months'))
                //					$segment["DepDate"] = strtotime('+ 1 year', $segment["DepDate"]);
                //				if ($segment["ArrDate"] > 0 && $segment["ArrDate"] < strtotime('- 6 months'))
                //					$segment["ArrDate"] = strtotime('+ 1 year', $segment["ArrDate"]);
                //$segment["DepDateHuman"] = date(\DateTime::ISO8601, $segment["DepDate"]);
                //$segment["ArrDateHuman"] = date(\DateTime::ISO8601, $segment["ArrDate"]);
                $segment["FlightNumber"] = $this->http->FindSingleNode("td[4]", $row);
                $segment["Cabin"] = $this->http->FindSingleNode("td[5]", $row);

                $result["TripSegments"][] = $segment;
            }
        }

        return ["Properties" => [], "Itineraries" => [$result]];
    }

    public function parsePlace($s)
    {
        if (preg_match("/\s[A-Z]{2}\s/", $s, $m)) {
            $s = str_replace($m[0], " - ", $s);
        }

        return $s;
    }

    public function ParseMailReservationLink()
    {
        $result = ["Kind" => "T"];

        $result['Passengers'][] = $this->http->FindSingleNode("//*[contains(text(), 'Travel dates for:')]/following::text()[normalize-space(.)!=''][1]");
        $result['TicketNumbers'][] = $this->http->FindSingleNode("//*[contains(text(), 'Ticket number:')]/following::text()[normalize-space(.)!=''][1]");

        $link = $this->http->FindSingleNode("//a[contains(@href, 'my_account/my_bookings')][contains(@href, 'filekey=')][contains(@href, 'lastname=')]/@href");

        if (preg_match("/filekey=([^\&]+)/", $link, $m)) {
            $result["RecordLocator"] = $confno = $m[1];
        }

        if (preg_match("/lastname=([^\&]+)/", $link, $m)) {
            $lname = $m[1];
        }

        if (!isset($result["RecordLocator"]) || !$result["RecordLocator"]) {
            $result["RecordLocator"] = $this->http->FindSingleNode("//*[contains(text(), 'Reservation code:')]/ancestor-or-self::td[1]", null, true, "#Reservation code:\s*([\w\d\-]+)#");
        }

        $result["TripSegments"] = [];
        $k = 2;

        $nodes = $this->http->XPath->query("//*[contains(text(), 'flight itinerary')]/ancestor::table[1]/following-sibling::table");

        if ($nodes->length == 0) {
            $nodes = $this->http->XPath->query("//*[contains(text(), 'Arrival')]/ancestor::table//tr");
            $k = 1;
        }

        for ($i = 1; $i < $nodes->length; $i++) {
            $row = $nodes->item($i);

            $flight = $this->http->FindSingleNode(".//td[$k]", $row);

            if (!$flight || !preg_match("#^((?-i)[A-Z\d]+)\s+(\d+)\s*(?:Operated by:\s*(.*?))*$#ims", $flight, $m)) {
                continue;
            }

            $seg = [];
            $seg['FlightNumber'] = $m[2];
            $seg['AirlineName'] = $m[1];

            if (isset($m[3])) {
                $seg['Operator'] = $m[3];
            }

            $date = explode("-", preg_replace("#[^\d\w\s\-]#", '', $this->http->FindSingleNode(".//td[" . ($k + 1) . "]", $row)));
            $from = reset($date);
            $to = end($date);

            $time = preg_replace("#[^\d:]#", '', $this->http->FindSingleNode(".//td[" . ($k + 4) . "]", $row));
            $seg['DepDate'] = strtotime($from . ', ' . $time, $this->date);

            $seg['DepName'] = $this->http->FindSingleNode(".//td[" . ($k + 2) . "]", $row);

            if (preg_match("#(.+)\s*(TERMINAL.+)#i", $seg['DepName'], $m)) {
                $seg['DepName'] = $m[1];
                $seg['DepartureTerminal'] = $m[2];
            }
            $seg['DepCode'] = TRIP_CODE_UNKNOWN;

            $time = preg_replace("#[^\d:]#", '', $this->http->FindSingleNode(".//td[" . ($k + 5) . "]", $row));
            $seg['ArrDate'] = strtotime($to . ', ' . $time, $this->date);

            $seg['ArrName'] = $this->http->FindSingleNode(".//td[" . ($k + 3) . "]", $row);

            if (preg_match("#(.+)\s*(TERMINAL.+)#i", $seg['ArrName'], $m)) {
                $seg['ArrName'] = $m[1];
                $seg['ArrivalTerminal'] = $m[2];
            }
            $seg['ArrCode'] = TRIP_CODE_UNKNOWN;

            if (preg_match("#^(.*?)\s*(?:confirmed|cancel+ed)*(?:\s*seat:\s*([\w\d]+)\s*\**)*$#ims", $this->http->FindSingleNode(".//td[" . ($k + 6) . "]", $row), $m)) {
                if (preg_match("#(.+?)\s*(?:\(\s*([A-Z]{1,2})\s*\)|$)#", $m[1], $v)) {
                    $seg['Cabin'] = $v[1];
                    $seg['BookingClass'] = $v[2];
                }

                if (isset($m[2])) {
                    $seg['Seats'] = $m[2];
                }
            }

            $result['TripSegments'][] = $seg;
        }

        $result['TotalCharge'] = $this->http->FindSingleNode("//*[contains(text(), 'Price for all passengers')]/ancestor-or-self::td[1]/following-sibling::td[3]");
        $result['Currency'] = $this->http->FindSingleNode("//*[contains(text(), 'Price for all passengers')]/ancestor-or-self::td[1]/following-sibling::td[2]");

        return ["Properties" => [], "Itineraries" => [$result]];
    }

    public function ParseMailReservationLinkDE()
    {
        $result = ["Kind" => "T"];

        $result['Passengers'] = $this->http->FindNodes("//*[contains(text(),'Reisedaten für:')]/following::text()[normalize-space(.)][1]");
        $result['TicketNumbers'] = $this->http->FindNodes("//*[contains(text(),'Ticketnummer:')]/following::text()[normalize-space(.)][1]");

        $link = $this->http->FindSingleNode("//a[contains(@href, 'my_account/my_bookings')][contains(@href, 'filekey=')][contains(@href, 'lastname=')]/@href");

        if (preg_match("/filekey=([^\&]+)/", $link, $m)) {
            $result["RecordLocator"] = $confno = $m[1];
        }

        if (preg_match("/lastname=([^\&]+)/", $link, $m)) {
            $lname = $m[1];
        }

        $result["TripSegments"] = [];
        $k = 2;

        $nodes = $this->http->XPath->query("//*[contains(text(), 'Ihre Flugdaten')]/ancestor::table[1]/following-sibling::table");

        if ($nodes->length == 0) {
            $nodes = $this->http->XPath->query("//*[contains(text(), 'Nach')]/ancestor::table//tr");
            $k = 1;
        }

        for ($i = 1; $i < $nodes->length; $i++) {
            $row = $nodes->item($i);

            $flight = $this->http->FindSingleNode("(.//td[$k])[1]", $row);

            if (!$flight || !preg_match("#^((?-i)[A-Z\d]+)\s+(\d+)\s*(?:durchgeführt von:\s*(.*?))*$#ims", $flight, $m)) {
                continue;
            }

            $seg = [];
            $seg['FlightNumber'] = $m[2];
            $seg['AirlineName'] = $m[1];

            if (isset($m[3])) {
                $seg['Operator'] = $m[3];
            }

            $date = explode("-", preg_replace("#[^\d\w\s\-]#", '', $this->http->FindSingleNode(".//td[" . ($k + 1) . "]", $row)));
            $from = reset($date);
            $to = end($date);

            $time = preg_replace("#[^\d:]#", '', $this->http->FindSingleNode(".//td[" . ($k + 4) . "]", $row));

            if ($this->year && $from && $time) {
                $seg['DepDate'] = strtotime($this->de2en($from) . ' ' . $this->year . ', ' . $time, $this->date);
            }

            $seg['DepName'] = $this->http->FindSingleNode(".//td[" . ($k + 2) . "]", $row);

            if (preg_match("#(.+)\s*TERMINAL\s*:?(.+)#i", $seg['DepName'], $m)) {
                $seg['DepName'] = $m[1];
                $seg['DepartureTerminal'] = trim($m[2]);
            }
            $seg['DepCode'] = TRIP_CODE_UNKNOWN;

            $time = preg_replace("#[^\d:]#", '', $this->http->FindSingleNode(".//td[" . ($k + 5) . "]", $row));

            if ($this->year && $to && $time) {
                $seg['ArrDate'] = strtotime($this->de2en($to) . ' ' . $this->year . ', ' . $time, $this->date);
            }

            $seg['ArrName'] = $this->http->FindSingleNode(".//td[" . ($k + 3) . "]", $row);

            if (preg_match("#(.+)\s*TERMINAL\s*:?(.+)#i", $seg['ArrName'], $m)) {
                $seg['ArrName'] = $m[1];
                $seg['ArrivalTerminal'] = trim($m[2]);
            }
            $seg['ArrCode'] = TRIP_CODE_UNKNOWN;

            if (preg_match("#^(.*?)\s*(?:bestätigt)*(?:\s*Sitzplatz:\s*([\w\d]+)\s*\**)*$#ims", $this->http->FindSingleNode(".//td[" . ($k + 6) . "]", $row), $m)) {
                if (preg_match("#(.+?)\s*(?:\(\s*([A-Z]{1,2})\s*\)|$)#", $m[1], $v)) {
                    $seg['Cabin'] = $v[1];
                    $seg['BookingClass'] = $v[2];
                }

                if (isset($m[2])) {
                    $seg['Seats'] = $m[2];
                }
            }

            $result['TripSegments'][] = $seg;
        }

        $xpathFragment1 = '//*[contains(text(),"Gesamtpreis für alle Reisenden")]/ancestor-or-self::td[1]';

        // Currency
        $currency = $this->http->FindSingleNode($xpathFragment1 . '/following-sibling::td[2]', null, true, '/^([A-Z]{3})$/');

        if ($currency) {
            $result['Currency'] = $currency;
        }

        // TotalCharge
        $payment = $this->http->FindSingleNode($xpathFragment1 . '/following-sibling::td[3]', null, true, '/^(\d[,.\d\s]*)$/');

        if ($payment) {
            $result['TotalCharge'] = $this->normalizeAmount($payment);
        }

        return ["Properties" => [], "Itineraries" => [$result]];
    }

    public function de2en(string $string)
    {
        if (preg_match('/^\s*(\d{1,2})\s+([^\d\W]{3,})\s*$/u', $string, $matches)) {
            $day = $matches[1];
            $month = $matches[2];
            $year = '';
        }

        if (isset($day) && isset($month) && isset($year)) {
            if (preg_match('/^\s*(\d{1,2})\s*$/', $month, $m)) {
                return $day . '.' . $m[1] . ($year ? '.' . $year : '');
            }

            if (($monthNew = MonthTranslate::translate($month, 'de')) !== false) {
                $month = $monthNew;
            }

            return $day . ' ' . $month . ($year ? ' ' . $year : '');
        }

        return false;
    }

    public function ParseType1()
    {
        $it = ['Kind' => 'T'];

        $it['RecordLocator'] = $this->http->FindSingleNode("//*[contains(text(), 'Booking code')]/strong");

        $names = [];
        $seats = [];
        $meals = [];

        $nodes = $this->http->XPath->query("//*[contains(text(), 'Passengers')]/ancestor::tr[2]/following-sibling::tr[1]//table[1]//tr");

        $num = $this->http->XPath->query("//*[contains(text(), 'Passengers')]/ancestor::tr[2]/following-sibling::tr[1]//table[1]//tr[1]/td")->length;

        for ($i = 2; $i < $nodes->length; $i += 2) {
            $row = $nodes->item($i);
            $names[$this->http->FindSingleNode("td[3]", $row)] = 1;

            for ($j = 6; $j <= $num - 2; $j++) {
                $flight = $this->http->FindSingleNode("//*[contains(text(), 'Passengers')]/ancestor::tr[2]/following-sibling::tr[1]//table[1]//tr[1]/td[" . $j . "]");
                $seat = $this->http->FindSingleNode("td[" . $j . "]", $row);
                $seats[$flight] = $seat;

                $meal = $this->http->FindSingleNode("td[" . ($num) . "]", $row);
                $meals[$flight] = $meal;
            }
        }

        $codes = [];

        foreach ($this->http->FindNodes("//*[contains(text(), 'Departing')]") as $line) {
            if (preg_match("#^(.*?)\s+\((\w{3})\)\s+to\s+(.*?)\s+\((\w{3})\)#", $line, $m)) {
                $codes[$m[1]] = $m[2];
                $codes[$m[3]] = $m[4];
            }
        }

        $airline = $this->http->FindSingleNode("//*[contains(text(), 'This flight will be operated by')]", null, true, "#operated by\s+(.*?)\.*$#");

        $it['Passengers'] = implode(', ', array_keys($names));
        $it['TripSegments'] = [];

        $nodes = $this->http->XPath->query("//*[contains(text(), 'Departure')]/ancestor::tr[1]/following-sibling::tr");

        for ($i = 1; $i < $nodes->length; $i += 2) {
            $row = $nodes->item($i);
            $seg = [];

            $flight = $seg['FlightNumber'] = $this->http->FindSingleNode("td[7]", $row);

            $seg['DepDate'] = strtotime($this->http->FindSingleNode("td[2]", $row) . ', ' . $this->http->FindSingleNode("td[3]", $row), $this->date);
            $dep = $seg['DepName'] = $this->http->FindSingleNode("td[4]", $row);
            $seg['DepCode'] = $codes[$dep] ?? TRIP_CODE_UNKNOWN;

            $seg['ArrDate'] = strtotime($this->http->FindSingleNode("td[2]", $row) . ', ' . $this->http->FindSingleNode("td[5]", $row), $this->date);
            $arr = $seg['ArrName'] = $this->http->FindSingleNode("td[6]", $row);
            $seg['ArrCode'] = $codes[$arr] ?? TRIP_CODE_UNKNOWN;

            $seg['Cabin'] = $this->http->FindSingleNode("td[8]", $row);

            if ($airline) {
                $seg['AirlineName'] = $airline;
            }

            if (isset($meals[$flight])) {
                $seg['Meal'] = $meals[$flight];
            }

            if (isset($seats[$flight])) {
                $seg['Seats'] = $seats[$flight];
            }

            $it['TripSegments'][] = $seg;
        }

        return ["Properties" => [], "Itineraries" => [$it]];
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        if (strpos($parser->GetSubject(), "Your ticket details & travel information") !== false
            || strpos($parser->GetSubject(), "Travel Information, Departure") !== false
            || strpos($parser->GetSubject(), "Booking details, Departure") !== false) {
            return null;
        }
        $this->date = strtotime($parser->getHeader('date'));
        $this->http->SetBody($parser->getHTMLBody());
        $type = $this->getEmailType();
        $this->year = re('#\d{4}#i', $parser->getHeader('date'));

        switch ($type) {
            case "Type1":
                $result = $this->ParseType1($parser);

                break;

            case "MailReservation":
                $result = $this->ParseMailReservation();

                break;

            case "MailReservationLink":
                $result = $this->ParseMailReservationLink();

                break;

            case "MailReservationLinkDE":
                $result = $this->ParseMailReservationLinkDE();

                break;

            default:
                $result = 'Undefined email type';

                break;
        }

        return [
            'parsedData' => $result,
            'emailType'  => $type,
        ];
    }

    public function getEmailType()
    {
        //if ($this->http->XPath->query("//a[contains(@href, 'my_account/my_bookings')][contains(@href, 'filekey=')][contains(@href, 'lastname=')]")->length > 0){
        if ($this->http->XPath->query("//*[contains(text(), 'Reservation code:') or contains(text(), 'Buchungscode:')]")->length > 0) {
            if ($this->http->FindPreg("#Ihre Flugdaten#")) {
                return 'MailReservationLinkDE';
            } else {
                return 'MailReservationLink';
            }
        }

        if ($this->http->XPath->query("//*[contains(text(), 'Your Booked Travel')]")->length > 0) {
            return 'Type1';
        }

        if ($this->http->XPath->query('//img[@alt="Your personal travel companion"]')->length > 0) {
            return 'MailReservation';
        }

        return 'Undefined';
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $condition1 = $this->http->XPath->query('//node()[contains(.,"www.lufthansa.com") or contains(.,"@booking-lufthansa.com")]')->length === 0;
        $condition2 = $this->http->XPath->query('//a[contains(@href,"//www.lufthansa.com") or contains(@href,"//konzern.lufthansa.com/") or contains(@href,"//newsletter.lufthansa.com/")]')->length === 0;

        if ($condition1 && $condition2) {
            return false;
        }

        return $this->assignLang();
    }

    public function detectEmailByHeaders(array $headers)
    {
        return (isset($headers["from"]) && preg_match("/cytric@ifao.net|myidtravel@services.lhsystems.com|preflight_information@newsletter.lufthansa.com|online@booking\-lufthansa.com/i", $headers['from']) > 0)
            || (isset($headers['subject']) && preg_match("/Your ticket details & travel information|Lufthansa/i", $headers['subject']) > 0);
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[-.@]lufthansa.com/i', $from) > 0;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    /**
     * @param string $string Unformatted string with amount
     */
    private function normalizeAmount($string): string
    {
        $string = preg_replace('/\s+/', '', $string);             // 11 507.00  ->  11507.00
        $string = preg_replace('/[,.\'](\d{3})/', '$1', $string); // 2,790      ->  2790    |    4.100,00  ->  4100,00    |    1'619.40  ->  1619.40
        $string = preg_replace('/,(\d{2})$/', '.$1', $string);    // 18800,00   ->  18800.00

        return $string;
    }

    private function assignLang(): bool
    {
        foreach ($this->langDetectors as $lang => $phrases) {
            foreach ($phrases as $phrase) {
                if ($this->http->XPath->query('//node()[contains(normalize-space(.),"' . $phrase . '")]')->length > 0) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }
}
