<?php

namespace AwardWallet\Engine\onetravel\Email;

class BookingReceipt extends \TAccountChecker
{
    public $mailFiles = "onetravel/it-1.eml, onetravel/it-1881104.eml, onetravel/it-2.eml, onetravel/it-2141604.eml, onetravel/it-6045956.eml";

    public $reBody = [
        'en' => ['Booking Details', 'Booking Number'],
    ];
    public $reSubject = [
        '#OneTravel\.com.+?Booking receipt\s+-\s+Trip#',
    ];
    public $lang = '';
    public static $dict = [
        'en' => [
        ],
    ];
    private $year;

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->year = date('Y', strtotime($parser->getDate()));
        $body = $this->http->Response['body'];
        $this->AssignLang($body);

        $its = $this->parseEmail();

        if (count($its) === 1) {
            $tot = $this->getTotalCurrency(str_replace("US$", "USD", $this->http->FindSingleNode('//*[contains(text(), "Price Details")]/following::text()[contains(., "Total Cost")][1]/ancestor::td[1]/following-sibling::td[1]')));

            if (!empty($tot['Total'])) {
                return [
                    'parsedData' => ['Itineraries' => $its, 'TotalCharge' => ['Amount' => $tot['Total'], 'Currency' => $tot['Currency']]],
                    'emailType'  => "BookingReceipt",
                ];
            }
        }

        return [
            'parsedData' => ['Itineraries' => $its],
            'emailType'  => "BookingReceipt",
        ];
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//a[contains(@href,'onetravel.com')]")->length > 0) {
            $body = $parser->getHTMLBody();

            return $this->AssignLang($body);
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($this->reSubject)) {
            foreach ($this->reSubject as $reSubject) {
                if (preg_match($reSubject, $headers["subject"])) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, "onetravel.com") !== false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    private function parseFlights()
    {
        $flights = [];
        $tripNum = $this->http->FindSingleNode('//*[contains(text(), "' . $this->t('Flight Booking Details') . '")]/ancestor::table[1]/following-sibling::table[descendant::text()[contains(normalize-space(.),"Booking Number")]]', null, true, "#:\s+([A-Z\d]{5,})#");
        $resDate = strtotime($this->normalizeDate($this->http->FindSingleNode('//*[contains(text(), "Booked on")]/ancestor-or-self::td[1]/following-sibling::td[1]')));

        $xpath = '//*[contains(text(), "Flight Booking Details")]/following::text()[normalize-space(.)="From"]/ancestor::tr[1][contains(.,"Flight")]';
        $nodes = $this->http->XPath->query($xpath);
        $airs = [];

        foreach ($nodes as $root) {
            $rl = $this->http->FindSingleNode('./descendant::text()[contains(.,"Airline confirmation:")]/following::text()[string-length(normalize-space(.))>4][1]', $root, true, "#[A-Z\d]{5,}#");

            if (!empty($rl)) {
                $airs[$rl][] = $root;
            } else {
                $airs[$tripNum][] = $root;
            }
        }

        foreach ($airs as $rl => $roots) {
            $it = ['Kind' => 'T', 'TripSegments' => []];
            $it['RecordLocator'] = $rl;
            $it['TripNumber'] = $tripNum;
            $it['ReservationDate'] = $resDate;

            foreach ($roots as $root) {
                $seg = [];
                $date = strtotime($this->normalizeDate($this->http->FindSingleNode('./preceding-sibling::tr[1][contains(normalize-space(.),"Departing Flight") or contains(normalize-space(.),"Return Flight")]', $root, true, "#-\s*(.+)$#")));

                if ($date) {
                    $this->year = date('Y', $date);
                }

                $node = $this->http->FindSingleNode("./descendant::td[1]", $root);

                if (preg_match("#(.+?)\s*Flight\s*(\d+)\s*(.+?)\s*(?:Seat\(s\)|Airline|Select)#", $node, $m)) {
                    $seg['AirlineName'] = $m[1];
                    $seg['FlightNumber'] = $m[2];
                    $seg['Aircraft'] = $m[3];
                }

                if (preg_match("#Seat\(s\)(.+?)\sAirline#", $node, $m)) {
                    if (preg_match_all("#\s*(\d+[A-Z]{1})\s*#i", $m[1], $v)) {
                        $seg['Seats'] = implode(",", $v[1]);
                    }
                }
                $node = $this->http->FindSingleNode("./descendant::td[2]", $root);

                if (preg_match("#^\s*From\s*(.+?)\s*\(([A-Z]{3})\)\s*(\d+:\d+\s*(?:[ap]m)?)\s*-\s*(.+)#is", $node, $m)) {
                    $seg['DepName'] = $m[1];
                    $seg['DepCode'] = $m[2];
                    $seg['DepDate'] = strtotime($this->normalizeDate($m[4] . ' ' . $m[3]));
                }
                $node = implode("\n", $this->http->FindNodes("./descendant::td[3]//text()[string-length(normalize-space(.))>0]", $root));

                if (preg_match("#^\s*To\s*(.+?)\s*\(([A-Z]{3})\)\s*(\d+:\d+\s*(?:[ap]m)?)\s*-\s*(.+?)(?:\n|$)#is", $node, $m)) {
                    $seg['ArrName'] = $m[1];
                    $seg['ArrCode'] = $m[2];
                    $seg['ArrDate'] = strtotime($this->normalizeDate($m[4] . ' ' . $m[3]));
                }
                $node = $this->http->FindSingleNode('./following-sibling::tr[normalize-space(.)][position()<3][contains(.,"Operated by")]', $root);
                $seg['Operator'] = $this->re("#Operated by\s+(.+)#", $node);
                $it['TripSegments'][] = $seg;
            }
            $flights[] = $it;
        }

        if (count($flights) === 1) {
            $tot = $this->getTotalCurrency(str_replace("US$", "USD", $this->http->FindSingleNode('//*[contains(text(), "Flight Price Details")]/following::text()[contains(., "Subtotal")][1]/ancestor::td[1]/following-sibling::td[1]')));

            if (!empty($tot['Total'])) {
                $flights[0]['TotalCharge'] = $tot['Total'];
                $flights[0]['Currency'] = $tot['Currency'];
            }
        }

        return $flights;
    }

    private function parseHotels()
    {
        $hotels = [];
        $tripNum = $this->http->FindSingleNode('//*[contains(text(), "Hotel Booking Details")]/ancestor::table[1]/following-sibling::table[descendant::text()[contains(normalize-space(.),"Booking Number")]]', null, true, "#:\s+([A-Z\d]{5,})#");
        $xpath = '//*[contains(text(), "Hotel Booking Details")]/ancestor::table[1]/following-sibling::table[contains(.,"Check-in")]';
        $resDate = strtotime($this->normalizeDate($this->http->FindSingleNode('//*[contains(text(), "Booked on")]/ancestor-or-self::td[1]/following-sibling::td[1]')));
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $rl => $root) {
            $it = ['Kind' => 'R'];
            $it['ReservationDate'] = $resDate;
            $it['HotelName'] = $this->http->FindSingleNode('./descendant::tr[count(descendant::tr)=0][string-length(normalize-space(.))>1][1]//b[1]', $root);
            $it['Address'] = $this->http->FindSingleNode('./descendant::tr[count(descendant::tr)=0][string-length(normalize-space(.))>1][1]//b[2]', $root);
            $it['Phone'] = $this->http->FindSingleNode('(//*[contains(text(), "Hotel Summary")]/following::text()[contains(normalize-space(.), "' . trim($it['HotelName']) . '")][1]/ancestor::b[1]//text()[string-length(normalize-space(.))>0])[last()]', null, true, "#([\d-\+\s\(\)]+)#");
            $it['ConfirmationNumber'] = $this->http->FindSingleNode('//*[contains(text(), "Hotel Summary")]/following::text()[contains(normalize-space(.), "' . trim($it['HotelName']) . '")][1]/ancestor::td[1]/following-sibling::td[contains(.,"Guest")]', $root, true, "#Hotel Confirmation:\s+([A-Z\d]+)#");
            $it['TripNumber'] = $tripNum;
            $it['CheckInDate'] = strtotime($this->normalizeDate($this->http->FindSingleNode('./descendant::tr[count(descendant::tr)=0][string-length(normalize-space(.))>1][2]/td[contains(.,"Check-in")]/following-sibling::td[1]', $root)));
            $it['CheckOutDate'] = strtotime($this->normalizeDate($this->http->FindSingleNode('./descendant::tr[count(descendant::tr)=0][string-length(normalize-space(.))>1][3]/td[contains(.,"Check-out")]/following-sibling::td[1]', $root)));
            $it['Rooms'] = $this->http->FindSingleNode('./descendant::tr[count(descendant::tr)=0][string-length(normalize-space(.))>1][2]/td[contains(.,"Number of Rooms")]/following-sibling::td[1]', $root);
            $it['RoomType'] = $this->http->FindSingleNode('./following-sibling::table[contains(.,"Guest and Room Details")]/descendant::tr[count(descendant::tr)=0][string-length(normalize-space(.))>1][3]/td[contains(.,"Room")]/following-sibling::td[1]', $root);
            $it['Guests'] = $this->http->FindSingleNode('//*[contains(text(), "Hotel Summary")]/following::text()[contains(normalize-space(.), "' . trim($it['HotelName']) . '")][1]/ancestor::td[1]/following-sibling::td[contains(.,"Guest")]', $root, true, "#Guest:\s+(\d+)#");
            $it['GuestNames'][] = $this->http->FindSingleNode('./following-sibling::table[contains(.,"Guest and Room Details")]/descendant::tr[count(descendant::tr)=0][string-length(normalize-space(.))>1][2]/td[contains(.,"Guest Name")]/following-sibling::td[1]', $root);
            $hotels[] = $it;
        }

        if (count($hotels) === 1) {
            $tot = $this->getTotalCurrency(str_replace("US$", "USD", $this->http->FindSingleNode('//*[contains(text(), "Hotel Price Details")]/following::text()[contains(., "Subtotal")][1]/ancestor::td[1]/following-sibling::td[1]')));

            if (!empty($tot['Total'])) {
                $hotels[0]['Total'] = $tot['Total'];
                $hotels[0]['Currency'] = $tot['Currency'];
            }
        }

        return $hotels;
    }

    private function parseEmail()
    {
        $its = [];

        if ($this->http->XPath->query('//*[contains(text(), "Flight Booking Details")]')->length > 0) {
            $flights = $this->parseFlights();

            foreach ($flights as $it) {
                $its[] = $it;
            }
        }

        if ($this->http->XPath->query('//*[contains(text(), "Hotel Booking Details")]')->length > 0) {
            $hotels = $this->parseHotels();

            foreach ($hotels as $it) {
                $its[] = $it;
            }
        }

        return $its;
    }

    private function normalizeDate($date)
    {
        $year = $this->year;
        $in = [
            //Wednesday, Aug 20, 2014
            '#^\s*\S+\s+(\S+)\s+(\d+),\s+(\d+)\s*$#',
            //Aug 20, Wed 07:30am
            '#^\s*(\S+)\s+(\d+),\s+\S+\s*(\d+:\d+(?:[ap]m)?)\s*$#i',
        ];
        $out = [
            '$2 $1 $3',
            "$2 $1 {$year}, $3",
        ];
        $str = preg_replace($in, $out, $date);

        return $str;
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function AssignLang($body)
    {
        if (isset($this->reBody)) {
            foreach ($this->reBody as $lang => $reBody) {
                if (stripos($body, $reBody[0]) !== false && stripos($body, $reBody[1]) !== false) {
                    $this->lang = $lang;

                    break;
                }
            }
        }

        return true;
    }

    private function getTotalCurrency($node)
    {
        $tot = '';
        $cur = '';

        if (preg_match("#(?<c>[A-Z]{3})\s*(?<t>\d[\.\d\,\s]*\d*)#", $node, $m) || preg_match("#(?<t>\d[\.\d\,\s]*\d*)\s*(?<c>[A-Z]{3})#", $node, $m)) {
            if (strpos($m['t'], ',') !== false && strpos($m['t'], '.') !== false) {
                if (strpos($m['t'], ',') < strpos($m['t'], '.')) {
                    $m['t'] = str_replace(',', '', $m['t']);
                } else {
                    $m['t'] = str_replace('.', '', $m['t']);
                    $m['t'] = str_replace(',', '.', $m['t']);
                }
            }
            $tot = str_replace(",", ".", str_replace(' ', '', $m['t']));
            $cur = $m['c'];
        }

        return ['Total' => $tot, 'Currency' => $cur];
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
