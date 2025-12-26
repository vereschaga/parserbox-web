<?php

namespace AwardWallet\Engine\malaysia\Email;

class Itinerary1 extends \TAccountChecker
{
    public const DATE_FORMAT = 'D, d M Y, H:i';
    public $mailFiles = "malaysia/it-1635855.eml, malaysia/it-1716327.eml, malaysia/it-1716383.eml, malaysia/it.eml";

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers["from"]) && (
            $headers["from"] === "noreply@malaysiaairlines.com.my"
            || preg_match("#Malaysia Airlines#", $headers["from"]));
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if (preg_match("#From[^\n]+@malaysiaairlines\.com|Thank you for choosing Malaysia Airlines#i", $parser->getHtmlBody())) {
            return true;
        }

        return $this->http->FindSingleNode('//span[contains(text(), "Thank you for choosing Malaysia Airlines.")]') !== null;
    }

    public function ParseEmailType1(\PlancakeEmailParser $parser)
    {
        $itineraries = [];
        $itineraries['Kind'] = 'T';
        $itineraries['RecordLocator'] = $this->http->FindSingleNode('//*[contains(text(), "Reference")]/ancestor-or-self::td[1]/following-sibling::td[1]');
        $itineraries['Passengers'] = $this->http->FindNodes("//*[contains(text(), 'Passengers')]/ancestor-or-self::h2[1]/following-sibling::table[1]/tbody/tr/td[1][contains(.,' ')]");

        $total = $this->http->FindSingleNode("//*[contains(text(), 'TOTAL:')]/ancestor-or-self::tr[1]//td[position()=last()]", null, true, "#:\s*(.+)#");
        $num = intval($this->http->FindSingleNode("//*[contains(text(), 'Total Fare')]/ancestor-or-self::tr[1]/following-sibling::tr[1]//td[position()=last()-1]", null, true, "#(\d+)#"));

        $itineraries['TotalCharge'] = preg_replace("#[^.\d]#", '', $total);
        $itineraries['Currency'] = preg_replace("#[\s.\d,]#", '', $total);

        $fare = $this->http->FindSingleNode("//*[contains(text(), 'Total Fare')]/ancestor-or-self::tr[1]/following-sibling::tr[1]//td[position()=last()]");
        $itineraries['BaseFare'] = preg_replace("#[^.\d]#", '', $fare);
        // if (!$itineraries['BaseFare'])
        // $itineraries['BaseFare'] = $num * preg_replace("#[^\d.]+#", '', $this->http->FindSingleNode("//*[contains(text(), 'Total Fare')]/ancestor-or-self::tr[1]/following-sibling::tr[1]//td[position()=last()-5]"));

        // $itineraries['Tax'] = $num * preg_replace("#[^\d.]+#", '', $this->http->FindSingleNode("//*[contains(text(), 'Total Fare')]/ancestor-or-self::tr[1]/following-sibling::tr[1]//td[position()=last()-3]"));

        if ($cost = $this->http->FindPreg("#TOTAL:\s*([\d.,]+\s+[A-Z]{3})#")) {
            if (preg_match("#TOTAL:\s*([\d.,]+)\s+([A-Z]{3})#", $cost, $m)) {
                $itineraries['TotalCharge'] = $m[1];
                $itineraries['Currency'] = $m[2];
            }
        }

        if ($cost = $this->http->FindPreg("#Total taxes and Surcharges:\s*([\d.]+)#")) {
            $itineraries['Tax'] = $cost;
        }

        if ($cost = $this->http->FindPreg("#Net price:\s*([\d.]+)#")) {
            $itineraries['BaseFare'] = $cost;
        }

        $segments = [];

        $tripRows = $this->http->XPath->query("//*[contains(text(), 'Air Itinerary Details')]/ancestor-or-self::h2[1]/following-sibling::table//tr[contains(.,'Seats')]");

        foreach ($tripRows as $row) {
            $tripSegment = [];
            $dep = preg_split('#\(|\)#', $this->http->FindSingleNode('.//td[1]//strong', $row));

            if (count($dep) < 2) {
                $dep = preg_split('#\(|\)#', $this->http->FindSingleNode('.//td[1]', $row));
            }

            $tripSegment['DepName'] = trim($dep[0]);
            $tripSegment['DepCode'] = $dep[1];

            $depDate = strtotime($this->http->FindSingleNode('.//td[1]', $row, true, "#\w{3}\s*,\s*(\d+\s+\w{3}\s+\d{4}s*,\s*\d+:\d+)#msi"));
            $tripSegment['DepDate'] = $depDate;
            $arr = preg_split('/\(|\)/', $this->http->FindSingleNode('.//td[2]//strong', $row));

            if (count($arr) < 2) {
                $arr = preg_split('#\(|\)#', $this->http->FindSingleNode('.//td[2]', $row));
            }

            $tripSegment['ArrName'] = trim($arr[0]);
            $tripSegment['ArrCode'] = $arr[1];
            $arrDate = strtotime($this->http->FindSingleNode('.//td[2]', $row, true, "#\w{3}\s*,\s*(\d+\s+\w{3}\s+\d{4}s*,\s*\d+:\d+)#msi"));
            $tripSegment['ArrDate'] = $arrDate;

            $flightInfo = $this->http->FindSingleNode('.//td[3]', $row);
            $matches = [];

            if (preg_match('/([A-Z\d]{2})\s*(\d+)/', $flightInfo, $matches)) {
                $tripSegment['FlightNumber'] = $matches[2];
                $tripSegment['AirlineName'] = $matches[1];
            } elseif (preg_match('/\w+\s*(\d+)\s*(.*)/', $flightInfo, $matches)) {
                $tripSegment['FlightNumber'] = $matches[1];
                $tripSegment['AirlineName'] = $matches[2];
            }

            $flightInfo = $this->http->FindSingleNode('.//td[4]', $row);

            if (preg_match('/Stop:\s*(\d+)/', $flightInfo, $matches)) {
                $tripSegment['Stops'] = intval($matches[1]);
            }

            if (preg_match('#\s+Seats:\s*([^\n]*?)\s+Equipment#', $flightInfo, $matches)) {
                if ($matches[1] != 'Web Check-in*') {
                    $tripSegment['Seats'] = $matches[1];
                }
            }

            if (preg_match('/Equipment type:\s*(.*?)$/', $flightInfo, $matches)) {
                $tripSegment['Aircraft'] = $matches[1];
            }

            $segments[] = $tripSegment;
        }

        $itineraries['TripSegments'] = $segments;

        return $itineraries;
    }

    public function ParseEmailType2(\PlancakeEmailParser $parser)
    {
        $it = [];
        $it['Kind'] = 'T';

        $it['Passengers'] = implode(', ', $this->http->FindNodes("//*[contains(text(), 'E-Ticket No')]/ancestor::div[2]/preceding-sibling::span[1]"));
        $it['RecordLocator'] = $this->http->FindSingleNode("//*[contains(text(), 'Booking Reference:')]/following-sibling::span[1]");

        $it['AccountNumbers'] = implode(', ', $this->http->FindNodes("//*[contains(text(), 'Enrich #')]/ancestor::td[1]/following-sibling::td[1]"));

        $it['Currency'] = $this->http->FindSingleNode("//*[contains(text(), 'Total:')]/following-sibling::span[1]");
        $it['TotalCharge'] = $this->http->FindSingleNode("//*[contains(text(), 'Total:')]/following-sibling::span[2]");

        $it['TripSegments'] = [];

        $this->http->FindSingleNode("//*[contains(text(), 'From: ')]");

        $nodes = $this->http->XPath->Query("//*[contains(text(), 'From:')]/ancestor::td[1]");

        for ($i = 0; $i < $nodes->length; $i++) {
            $item = $nodes->item($i);
            $seg = [];

            $seg['FlightNumber'] = $this->http->FindSingleNode("*/label[contains(text(), 'Flight:')]/following-sibling::span[1]", $item);

            $seg['DepName'] = $this->http->FindSingleNode("*/label[contains(text(), 'From:')]/following-sibling::span[1]", $item);
            $seg['ArrName'] = $this->http->FindSingleNode("*/label[contains(text(), 'To:')]/following-sibling::span[1]", $item);

            $seg['DepDate'] = strtotime($this->http->FindSingleNode("*/label[contains(text(), 'Depart:')]/following-sibling::span[1]", $item));
            $seg['ArrDate'] = strtotime($this->http->FindSingleNode("*/label[contains(text(), 'Arrive:')]/following-sibling::span[1]", $item));

            $seg['DepCode'] = TRIP_CODE_UNKNOWN;
            $seg['ArrCode'] = TRIP_CODE_UNKNOWN;

            $it['TripSegments'][] = $seg;
        }

        return $it;
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        if ($this->http->FindSingleNode("//span[contains(text(), 'PASSENGER ELECTRONIC TICKET')]")) {
            $it = $this->ParseEmailType2($parser);
        } else {
            $it = $this->ParseEmailType1($parser);
        }

        return [
            'emailType'  => 'reservations',
            'parsedData' => [
                'Itineraries' => [$it],
            ],
        ];
    }

    public function buildDate($parsedDate)
    {
        return mktime($parsedDate['hour'], $parsedDate['minute'], 0, $parsedDate['month'], $parsedDate['day'], $parsedDate['year']);
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[\.@]malaysiaairlines\.com\.my$/ims', $from);
    }

    public static function getEmailTypesCount()
    {
        return 2;
    }
}
