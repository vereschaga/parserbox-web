<?php

namespace AwardWallet\Engine\hoggrob\Email;

class It3292162 extends \TAccountCheckerExtended
{
    public $mailFiles = "hoggrob/it-3292162.eml, hoggrob/it-49782243.eml";
    private $reBody2 = 'Confirmation Number For';

    private $providerCode = '';

    public function __construct()
    {
        parent::__construct();

        $this->processors = [
            $this->reBody2 => function (&$itineraries) {
                $patterns = [
                    'travellerName'  => '[[:alpha:]][-.\'[:alpha:] ]*[[:alpha:]]', // Mr. Hao-Li Huang
                    'travellerName2' => '[[:upper:]]+(?: [[:upper:]]+)*\/(?:[[:upper:]]+ )*[[:upper:]]+', // KOH/KIM LENG MR
                ];

                $agencyRefNum = $this->http->FindSingleNode("//tr[starts-with(normalize-space(),'Agency Reference Number')]", null, true, '/^Agency Reference Number[:\s]+([-A-Z\d]{5,})$/');

                $travellers = $this->http->FindNodes("//table[ descendant::td[normalize-space()][1][normalize-space()='Travellers'] ][1]/descendant::td[normalize-space()='Travellers']/ancestor::tr[1]/following-sibling::tr/*[1]", null, "/^[*\s]*({$patterns['travellerName']}|{$patterns['travellerName2']})(?:\s*\(|$)/");
                $travellers = array_filter($travellers);

                $xpath = "//*[normalize-space(text())='Departs']/ancestor::tr[1]/following-sibling::tr[1][contains(./td[1], 'Arrives')]/..";
                $nodes = $this->http->XPath->query($xpath);
                $flights = [];

                foreach ($nodes as $root) {
                    if ($rl = $this->http->FindSingleNode("./tr[3]/td[3]", $root, true, "#^\w{6}$#")) {
                        $flights[$rl][] = $root;
                    }
                }

                foreach ($flights as $rl => $roots) {
                    $it = [];
                    $it['Kind'] = "T";

                    // RecordLocator
                    $it['RecordLocator'] = $rl;

                    // TripNumber
                    if ($agencyRefNum) {
                        $it['TripNumber'] = $agencyRefNum;
                    }

                    if (count($flights) == 1) {
                        // Currency
                        // TotalCharge
                        $total = $this->http->FindSingleNode("//*[normalize-space(text())='Total:']/ancestor::td[1]//text()[normalize-space(.)='Total:']/following::text()[normalize-space(.)][1]");

                        if ($total !== null) {
                            $it['Currency'] = currency($total);
                            $it['TotalCharge'] = cost($total);
                        }

                        // BaseFare
                        $fare = $this->http->FindSingleNode("//*[normalize-space(text())='Total:']/ancestor::td[1]//text()[normalize-space()='Fare:']/following::text()[normalize-space()][1]");

                        if ($fare !== null) {
                            $it['BaseFare'] = cost($fare);
                        }

                        // Tax
                    }

                    $passengers = [];

                    foreach ($roots as $root) {
                        $itsegment = [];

                        // AirlineName
                        // FlightNumber
                        $flight = $this->http->FindSingleNode('tr[2]/td[2]', $root);

                        if (preg_match('/^(?<name>[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(?<number>\d+)/', $flight, $m)) {
                            $itsegment['AirlineName'] = $m['name'];
                            $itsegment['FlightNumber'] = $m['number'];
                        }

                        // DepCode
                        $itsegment['DepCode'] = $this->http->FindSingleNode("./tr[5]/td[4]", $root, true, "#^([A-Z]{3})$#");

                        // DepartureTerminal
                        $itsegment['DepartureTerminal'] = $this->http->FindSingleNode("tr[5]/td[5]", $root, true, "/^Terminal\s*([-\w\s]+)$/i");

                        // DepDate
                        $itsegment['DepDate'] = strtotime($this->http->FindSingleNode("./tr[1]", $root) . ', ' . $this->getField("Departs", $root));

                        // ArrCode
                        $itsegment['ArrCode'] = $this->http->FindSingleNode("./tr[6]/td[4]", $root, true, "#^([A-Z]{3})$#");

                        // ArrivalTerminal
                        $itsegment['ArrivalTerminal'] = $this->http->FindSingleNode("tr[6]/td[5]", $root, true, "/^Terminal\s*([-\w\s]+)$/i");

                        // ArrDate
                        $itsegment['ArrDate'] = strtotime($this->http->FindSingleNode("./tr[1]", $root) . ', ' . $this->getField("Arrives", $root));

                        // Aircraft
                        $itsegment['Aircraft'] = $this->getField("Equipment", $root);

                        // Cabin
                        $itsegment['Cabin'] = $this->getField("Class", $root, true, "#\w\s+-\s+(.+)#");

                        // BookingClass
                        $itsegment['BookingClass'] = $this->getField("Class", $root, true, "#(\w)\s+-\s+#");

                        // Seats
                        $seat = $this->http->FindSingleNode("descendant::tr[ *[3][normalize-space()='Seat'] ]/following-sibling::tr[1]/td[3]", $root, true, '/^\d+[A-Z]$/');

                        if ($seat) {
                            $itsegment['Seats'] = [$seat];
                        }

                        $passenger = $this->http->FindSingleNode("descendant::tr[ *[3][normalize-space()='Seat'] ]/following-sibling::tr[1]/td[1]", $root, true, "/^[*\s]*({$patterns['travellerName']}|{$patterns['travellerName2']})(?:\s*\(|$)/");

                        if ($passenger) {
                            $passengers[] = $passenger;
                        }

                        // Duration
                        $itsegment['Duration'] = $this->getField("Flying Time", $root);

                        // Meal
                        $itsegment['Meal'] = $this->getField("Meal", $root);

                        // Stops
                        $stops = $this->http->FindSingleNode(".//*[normalize-space(text())='Class']/ancestor-or-self::td[1]/following-sibling::td[2]", $root);

                        if (preg_match('/^Non[-\s]*Stop$/i', $stops)) {
                            $itsegment['Stops'] = 0;
                        }

                        $it['TripSegments'][] = $itsegment;
                    }

                    // Passengers
                    if (count($passengers)) {
                        $it['Passengers'] = array_unique($passengers);
                    } elseif (count($travellers)) {
                        $it['Passengers'] = $travellers;
                    }

                    $itineraries[] = $it;
                }
            },
        ];
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        // Detecting Provider
        if ($this->assignProvider($parser->getHeaders()) === false) {
            return false;
        }

        // Detecting Format
        return $this->http->XPath->query('//node()[contains(normalize-space(),"' . $this->reBody2 . '")]')->length > 0;
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->http->FilterHTML = false;

        $body = $parser->getHTMLBody();

        if (empty($body)) {
            $body = $parser->getPlainBody();
            $this->http->SetEmailBody($body);
        }

        // Detecting Provider
        $this->assignProvider($parser->getHeaders());

        $itineraries = [];

        foreach ($this->processors as $re => $processor) {
            if ($this->http->XPath->query('//node()[contains(normalize-space(),"' . $re . '")]')->length > 0) {
                $processor($itineraries);

                break;
            }
        }

        $result = [
            'emailType'    => 'Flight',
            'providerCode' => $this->providerCode,
            'parsedData'   => [
                'Itineraries' => $itineraries,
            ],
        ];

        return $result;
    }

    public static function getEmailProviders()
    {
        return ['xlt', 'hoggrob'];
    }

    private function getField($s, $r = null, $b = true, $e = null)
    {
        return $this->http->FindSingleNode(".//*[normalize-space(text())='{$s}']/ancestor-or-self::td[1]/following-sibling::td[1]", $r, $b, $e);
    }

    private function assignProvider($headers): bool
    {
        if (stripos($headers['from'], '@xltravel-select.co.za') !== false
            || $this->http->XPath->query('//node()[contains(.,"@xltravel-select.co.za")]')->length > 0
        ) {
            $this->providerCode = 'xlt';

            return true;
        }

        if (stripos($headers['from'], '@renniestravel.com') !== false
            || $this->http->XPath->query('//node()[contains(normalize-space(),"HRG South Africa") or contains(.,"@renniestravel.com")]')->length > 0
            || $this->http->XPath->query('//a[contains(@href,".renniestravel.com/") or contains(@href,"www.renniestravel.com")]')->length > 0
        ) {
            $this->providerCode = 'hoggrob';

            return true;
        }

        return false;
    }
}
