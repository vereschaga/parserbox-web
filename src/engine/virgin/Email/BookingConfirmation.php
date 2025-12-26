<?php

namespace AwardWallet\Engine\virgin\Email;

class BookingConfirmation extends \TAccountChecker
{
    public $mailFiles = "virgin/it-10606623.eml, virgin/it-10708202.eml, virgin/it-10822061.eml, virgin/it-1654252.eml, virgin/it-1691343.eml, virgin/it-1693254.eml, virgin/it-2758408.eml, virgin/it-5719955.eml, virgin/it-8752616.eml, virgin/it-8841299.eml, virgin/it-8859100.eml, virgin/it-8892365.eml";
    public $reFrom = 'booking@virgin-atlantic.com';
    public $reProvider = 'virgin-atlantic.com';
    public $reBody = 'Thanks for booking with Virgin Atlantic';
    public $reSubject = "Virgin Atlantic Airways Booking Confirmation";

    public function parseEmail()
    {
        $it = [];

        $it['Kind'] = "T";

        // RecordLocator
        $it['RecordLocator'] = $this->http->FindSingleNode("//text()[contains(., 'your booking referenc')]/following::text()[normalize-space(.)][1]");

        // TripNumber
        // Passengers
        $it['Passengers'] = $this->http->FindNodes("//*[contains(text(), 'Passenger Name')]/ancestor::table[1]/tr/td[1]");

        if (empty($it['Passengers'])) {
            $it['Passengers'] = $this->http->FindNodes("//*[contains(text(), 'Passenger Name')]/ancestor::table[1]/tbody/tr/td[normalize-space(.)][1]");
        }

        // AccountNumbers
        $it['AccountNumbers'] = $this->http->FindNodes("//*[contains(text(), 'Passenger Name')]/ancestor::table[1]/tr/td[2]");

        if (empty($it['AccountNumbers'])) {
            $it['AccountNumbers'] = array_filter($this->http->FindNodes("//*[contains(text(), 'Passenger Name')]/ancestor::table[1]/tbody/tr/td[normalize-space(.)][2]", null, "#^(\d+)$#"));
        }

        // Cancelled
        // TotalCharge
        // SpentAwards
        // Currency
        $total = $this->http->FindSingleNode("//*[contains(text(), 'Total')]/ancestor::td[1]/following-sibling::td[normalize-space(.)][1]");

        if (preg_match("#^\s*((?<awards>miles\s*\d+)\s*\+\s*)?(?<curr>[A-Z]{3})\s*(?<money>[\d,. ]+)#", $total, $m)) {
            $it['TotalCharge'] = (float) str_replace(',', '', $m['money']);
            $it['Currency'] = $m['curr'];

            if (!empty($m['awards'])) {
                $it['SpentAwards'] = $m['awards'];
            }
        }

        // BaseFare
        $BaseFares = array_filter($this->http->FindNodes("//text()[normalize-space(.) = 'HOW MUCH']/following::*[(contains(text(), 'Adult') or contains(text(), 'Child') or contains(text(), 'Infant')) and contains(text(), ':')]/ancestor::tr[1]", null, '#(^\s*\d+\s*(?:Adult|Child|Infant)s?:.*)#'));
        $fare = 0.0;

        foreach ($BaseFares as $BaseFare) {
            if (preg_match("#(\d+)\s*(?:Adult|Child|Infant)s?:\s*[A-Z]{3}\s*([\d,. ]+)#", $BaseFare, $m)) {
                $fare += $m[1] * (float) str_replace(',', '', $m[2]);
            }
        }

        if ($fare > 0) {
            $it['BaseFare'] = $fare;
        }

        // Tax
        $it['Tax'] = (float) str_replace(',', '', $this->http->FindSingleNode("//*[contains(text(), 'Taxes')]/ancestor::td[1]/following-sibling::td[normalize-space(.)][1]", null, true, "#[A-Z]+\s+([0-9\.,]+)#ms"));

        // SpentAwards
        // EarnedAwards
        // Status
        // ReservationDate
        // NoItineraries
        // TripCategory

        $xpath = "//*[contains(text(),'Depart')]/ancestor::tr[1]//*[contains(text(),'Arrive')]/ancestor::table[1]/tr[normalize-space(.)]";
        $nodes = $this->http->XPath->query($xpath);

        if ($nodes->length == 0) {
            $xpath = "//*[contains(text(),'Depart')]/ancestor::tr[1]//*[contains(text(),'Arrive')]/ancestor::table[1]/tbody/tr[normalize-space(.)]";
            $nodes = $this->http->XPath->query($xpath);

            if ($nodes->length == 0) {
                $this->http->Log("segments root not found: $xpath", LOG_LEVEL_NORMAL);
            }
        }

        $issetTerminals = false;
        $terminalsArr = $this->http->FindNodes("//*[contains(text(),'TURN UP, CHECK IN')]/following::text()[contains(.,'Check in:') or contains(.,'Arrival:')]");

        for ($i = 0; $i < count($terminalsArr); $i += 2) {
            if (!isset($terminalsArr[$i + 1])) {
                break;
            }

            if (!$issetTerminals && (stripos($terminalsArr[$i], 'terminal') || stripos($terminalsArr[$i + 1], 'terminal'))) {
                $issetTerminals = true;
            }
            $terminals[] = [
                'dep' => $terminalsArr[$i],
                'arr' => $terminalsArr[$i + 1],
            ];
        }

        if ($issetTerminals) {
            if ($nodes->length > count($terminals)) {
                $issetTerminals = false;
            } else {
                $terminals = array_slice($terminals, 0, $nodes->length);
            }
        }

        foreach ($nodes as $root) {
            $itsegment = [];
            $flightInfoPos = 1;

            // Operator
            $row = $this->http->FindSingleNode("./ancestor::table[1]/preceding-sibling::*[normalize-space(.)][1]", $root, true, "#Operated\s+by\s*(.+?)\s*(?:on behalf of|$)#msi");
            // $this->logger->info($this->http->FindSingleNode("./ancestor::table[1]/preceding-sibling::*[normalize-space(.)][1]", $root));
            if (!empty($row)) {
                $flightInfoPos = 2;
                $itsegment['Operator'] = $row;
            }

            // FlightNumber
            $itsegment['FlightNumber'] = $this->http->FindSingleNode("./ancestor::table[1]/preceding::text()[normalize-space(.)][{$flightInfoPos}]", $root, true, "#Flight:\s+[A-Z]+(\d+)#ms");

            // DepCode
            $itsegment['DepCode'] = TRIP_CODE_UNKNOWN;

            // DepName
            $itsegment['DepName'] = $this->http->FindSingleNode("./ancestor::table[1]/preceding::text()[normalize-space(.)][{$flightInfoPos}]", $root, true, "#^(.*?)\s+to#ms");

            // DepDate
            $itsegment['DepDate'] = strtotime(preg_replace("#(\d+:\d+)\s+[^,]+,\s+(\d+\s+[^,]+),\s+(\d+)#", "$2 $3, $1", $this->http->FindSingleNode("./td[normalize-space(.)][1]", $root)));

            // ArrCode
            $itsegment['ArrCode'] = TRIP_CODE_UNKNOWN;

            // ArrName
            $itsegment['ArrName'] = $this->http->FindSingleNode("./ancestor::table[1]/preceding::text()[normalize-space(.)][{$flightInfoPos}]", $root, true, "#to\s+(.*?)\s+/#ms");

            // ArrDate
            $itsegment['ArrDate'] = strtotime(preg_replace("#(\d+:\d+)\s+[^,]+,\s+(\d+\s+[^,]+),\s+(\d+)#", "$2 $3, $1", $this->http->FindSingleNode("./td[normalize-space(.)][2]", $root)));

            // AirlineName
            $itsegment['AirlineName'] = $this->http->FindSingleNode("./ancestor::table[1]/preceding::text()[normalize-space(.)][{$flightInfoPos}]", $root, true, "#Flight:\s+([A-Z]+)\d+#ms");

            // Aircraft
            // TraveledMiles
            // Cabin
            $itsegment['Cabin'] = $this->http->FindSingleNode("./td[normalize-space(.)][3]", $root);

            // DepartureTerminal
            // ArrivalTerminal
            if ($issetTerminals) {
                $term = array_shift($terminals);

                if (stripos($term['dep'], 'terminal')) {
                    $deps = explode(',', $term['dep']);

                    foreach ($deps as $value) {
                        if (stripos($value, 'terminal') or stripos($value, 'zone')) {
                            $itsegment['DepartureTerminal'][] = trim($value);
                        }
                    }
                    $itsegment['DepartureTerminal'] = implode(',', $itsegment['DepartureTerminal']);
                }

                if (stripos($term['arr'], 'terminal')) {
                    $deps = explode(',', $term['arr']);

                    foreach ($deps as $value) {
                        if (stripos($value, 'terminal') or stripos($value, 'zone')) {
                            $itsegment['ArrivalTerminal'][] = trim($value);
                        }
                    }
                    $itsegment['ArrivalTerminal'] = implode(',', $itsegment['ArrivalTerminal']);
                }
            }
            // BookingClass
            // PendingUpgradeTo
            // Seats
            // Duration
            // Meal
            // Smoking
            // Stops
            $it['TripSegments'][] = $itsegment;
        }

        return [$it];
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        if (empty($body)) {
            $body = $parser->getPlainBody();
        }

        return strpos($body, $this->reBody) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (stripos($headers['from'], $this->reFrom) === false) {
            return false;
        }

        return strpos($headers["subject"], $this->reSubject) !== false;
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        if (empty($body)) {
            $body = $parser->getPlainBody();
            $this->http->SetBody($body);
        }

        $itineraries = $this->parseEmail();
        $result = [
            'emailType'  => 'BookingConfirmation',
            'parsedData' => [
                'Itineraries' => $itineraries,
            ],
        ];

        return $result;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->reProvider) !== false;
    }

    public static function getEmailLanguages()
    {
        return ['en'];
    }

    public static function getEmailTypesCount()
    {
        return 1;
    }
}
