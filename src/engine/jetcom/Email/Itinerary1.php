<?php

namespace AwardWallet\Engine\jetcom\Email;

class Itinerary1 extends \TAccountChecker
{
    public $mailFiles = "jetcom/it-1.eml, jetcom/it-2.eml";

    public function detectEmailFromProvider($from)
    {
        return preg_match("#@jet2\.com#i", $from);
    }

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers['from']) && preg_match("#Jet2\.Com#i", $headers['from']);
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        return $this->http->XPath->query("//*[contains(., 'Thank you for booking your flights with Jet2.com')]")->length > 0;
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        return [
            "emailType"  => "reservation",
            "parsedData" => [
                "Itineraries" => [$this->A_email()],
            ],
        ];
    }

    public function A_email()
    {
        $itineraries['Kind'] = 'T';
        $itineraries['RecordLocator'] = $this->http->FindSingleNode("//text()[contains(normalize-space(.),'Your booking reference is:')]/following::text()[normalize-space(.)!=''][1]");
        $itineraries['Passengers'] = array_unique($this->http->FindNodes("//text()[contains(.,'Arrives')]/ancestor::tr[1][contains(.,'Flight')]/ancestor::table[1]/following-sibling::table[contains(.,'Passengers')]/descendant::tr[not(contains(.,'Passengers'))]/td[2]"));
        $itineraries['TotalCharge'] = $this->http->FindSingleNode("//text()[contains(normalize-space(.),'Total amount charged:')]/ancestor::tr[1]", null, false, '#charged:s*\D*(.*)#');
        $currency = $this->http->FindSingleNode("//text()[contains(normalize-space(.),'Total amount charged:')]/ancestor::tr[1]", null, false, '#charged:s*\D?(\D*).*#');
        $currencies = ['£' => 'GBP', '€' => 'EUR'];
        $itineraries["Currency"] = ArrayVal($currencies, $currency, $currency);

        $nodes = $this->http->XPath->query("//text()[contains(.,'Arrives')]/ancestor::tr[1][contains(.,'Flight')]");

        foreach ($nodes as $root) {
            $seg = [];

            $node = $this->http->FindSingleNode("./td[contains(.,'Flight')]", $root);

            if (preg_match("#Flight[:\s]+([A-Z\d]{2})\s*(\d+)#", $node, $m)) {
                $seg['AirlineName'] = $m[1];
                $seg['FlightNumber'] = $m[2];
            }
            $date = strtotime($this->http->FindSingleNode("./td[contains(.,'Flight')]/preceding-sibling::td[normalize-space(.)!=''][1]", $root));
            $seg['DepDate'] = strtotime($this->http->FindSingleNode("./td[contains(.,'Departs')]", $root, false, "#(\d+:\d+.+)#"), $date);
            $seg['ArrDate'] = strtotime($this->http->FindSingleNode("./td[contains(.,'Arrives')]", $root, false, "#(\d+:\d+.+)#"), $date);

            $seg['DepName'] = $this->http->FindSingleNode("./preceding-sibling::tr[1]", $root, false, '#(.*?)\s*to#');
            $seg['ArrName'] = $this->http->FindSingleNode("./preceding-sibling::tr[1]", $root, false, '#to\s*(.*)#');
            $seg['DepCode'] = TRIP_CODE_UNKNOWN;
            $seg['ArrCode'] = TRIP_CODE_UNKNOWN;

            $seg['Seats'] = implode(",", $this->http->FindNodes("./ancestor::table[1]/following-sibling::table[contains(.,'Passengers')]/descendant::tr[not(contains(.,'Passengers'))]/td[3][normalize-space(.)!='-']", $root));
            $seg['Meal'] = implode(",", array_unique($this->http->FindNodes("./ancestor::table[1]/following-sibling::table[contains(.,'Passengers')]/descendant::tr[not(contains(.,'Passengers'))]/td[5][normalize-space(.)!='-']", $root)));

            $itineraries['TripSegments'][] = $seg;
        }

        return $itineraries;
    }
}
