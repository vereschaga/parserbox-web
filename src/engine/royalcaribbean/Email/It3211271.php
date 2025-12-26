<?php

namespace AwardWallet\Engine\royalcaribbean\Email;

class It3211271 extends \TAccountCheckerExtended
{
    public $mailFiles = "royalcaribbean/it-3211271.eml, royalcaribbean/it-3514852.eml, royalcaribbean/it-23366726.eml";

    private $providerCode = '';
    private $lang = '';

    private $langDetectors = [
        'en' => ['Outbound Travel Arrangements:', 'Cruise Itinerary:', 'Inbound Travel Arrangements:'],
    ];

    public function __construct()
    {
        parent::__construct();

        $this->processors = [
            '/View Your (?:Air2Sea|FLIGHTS by Celebrity) Booking/i' => function (&$itineraries) {
                $guests = [];
                $guestCells = $this->http->XPath->query("//*[contains(text(), 'GUEST')]/ancestor::tr[1]/following-sibling::tr[contains(./td[1], 'Name:')]/td[position()>1][string-length(normalize-space(.))>1]");

                foreach ($guestCells as $guestCell) {
                    $guestTexts = $this->http->FindNodes('./descendant::text()[normalize-space(.)]', $guestCell);
                    $guests[] = implode(' ', $guestTexts);
                }

                //## FLIGHTS ###

                $rls = [];

                foreach ($this->http->FindNodes("//*[normalize-space(text())='Record Locator|Booking Reference:']/ancestor-or-self::td[1]/following-sibling::td[1]/descendant::text()[normalize-space(.)]") as $node) {
                    if (re("/^([A-Z][A-Z\d]|[A-Z\d][A-Z])\|(\w+)/", $node)) {
                        $rls[re(1)][] = re(2);
                    }
                }

                $xpath = "//*[normalize-space(text())='Outbound Travel Arrangements:' or normalize-space(text())='Inbound Travel Arrangements:']/ancestor::tr[1]/following-sibling::tr[1]";
                $nodes = $this->http->XPath->query($xpath);

                $used = [];
                $airs = [];

                foreach ($nodes as $root) {
                    $fcount = count($this->http->FindNodes("./td[1]//text()[normalize-space(.)]", $root)) / 2;

                    for ($i = 0; $i < $fcount; $i++) {
                        $data = $this->getAir($root, $i);

                        if ($airline = re("/^([A-Z][A-Z\d]|[A-Z\d][A-Z])\s+\d+/", $data['Dep'][1])) {
                            if (!isset($used[$airline])) {
                                $used[$airline] = 0;
                            }

                            if (isset($rls[$airline][$used[$airline]])) {
                                $airs[$rls[$airline][$used[$airline]]][] = $data;
                                $used[$airline]++;
                            } elseif (isset($rls[$airline][$used[$airline] - 1])) {
                                $airs[$rls[$airline][$used[$airline] - 1]][] = $data;
                            } else {
                                $airs[CONFNO_UNKNOWN][] = $data;
                            }
                        }
                    }
                }

                foreach ($airs as $rl=>$segments) {
                    $it = [];
                    $it['Kind'] = "T";
                    // RecordLocator

                    $it['RecordLocator'] = $rl;

                    // Passengers
                    if (!empty($guests)) {
                        $it['Passengers'] = $guests;
                    }

                    // AccountNumbers
                    // Cancelled
                    // TotalCharge
                    // BaseFare
                    // Currency
                    // Tax
                    // SpentAwards
                    // EarnedAwards
                    // Status
                    // ReservationDate
                    // NoItineraries

                    foreach ($segments as $data) {
                        $year = date("Y", $this->date);
                        // print_r( $data);
                        $itsegment = [];
                        // FlightNumber
                        $itsegment['FlightNumber'] = re("#^\w{2}\s+(\d+)#", $data['Dep'][1]);

                        // DepCode
                        $itsegment['DepCode'] = re("#\(([A-Z]+)\)#", $data['Dep'][1]);

                        // DepName
                        // DepDate
                        $itsegment['DepDate'] = strtotime(re("#\d+\s+\S{3}#", $data['Dep'][0]) . ', ' . re("#\d+:\d+\s*[ap]m#i", $data['Dep'][3]), $this->date);

                        // ArrCode
                        $itsegment['ArrCode'] = re("#\(([A-Z]+)\)#", $data['Arr'][1]);

                        // ArrDate
                        $itsegment['ArrDate'] = strtotime(re("#\d+\s+\S{3}#", $data['Arr'][0]) . ', ' . re("/\d+:\d+(?:\s*[ap]m)?/i", preg_replace("/^0:(\d+)\s+AM/", "00:$1", $data['Arr'][2])), $this->date);

                        // AirlineName
                        $itsegment['AirlineName'] = re("/^([A-Z][A-Z\d]|[A-Z\d][A-Z])\s+\d+/", $data['Dep'][1]);

                        // Aircraft
                        // TraveledMiles
                        // Cabin
                        // BookingClass
                        // PendingUpgradeTo
                        // Seats
                        // Duration
                        // Meal
                        // Smoking
                        // Stops
                        $it['TripSegments'][] = $itsegment;
                    }
                    $itineraries[] = $it;
                }

                //## CRUISE ###

                $it = [];
                $it['Kind'] = "T";
                $it['TripCategory'] = TRIP_CATEGORY_CRUISE;

                //RecordLocator
                $it['RecordLocator'] = $this->getField("Cruise Reservation ID:");

                //Passengers
                if (!empty($guests)) {
                    $it['Passengers'] = $guests;
                }

                //AccountNumbers
                //Cancelled
                //ShipName
                $it['ShipName'] = $this->getField("Ship:");
                //ShipCode
                //CruiseName
                $it['CruiseName'] = $this->http->FindSingleNode("//*[normalize-space(text())='Cruise Reservation ID:']/ancestor::tr[1]/preceding-sibling::tr[1]");
                //Deck
                //RoomNumber
                $it['RoomNumber'] = re("#^\w+\s+\d+#", $this->getField("Stateroom:"));
                //RoomClass
                $it['RoomClass'] = re("#^\w+\s+\d+\s+(.+)#", $this->getField("Stateroom:"));
                //Status
                //TripSegments

                $segments = [];
                $data = $this->getCruise();

                foreach ($data as $row) {
                    $segment = [];

                    if (!empty($row[3])) {
                        $segment['DepDate'] = strtotime($row[0] . ', ' . $row[3], $this->date);
                    }

                    if (!empty($row[2])) {
                        $segment['ArrDate'] = strtotime($row[0] . ', ' . $row[2], $this->date);
                    }
                    $segment['Port'] = $row[1];

                    $segments[] = $segment;
                }

                $converter = new \CruiseSegmentsConverter();
                $it['TripSegments'] = $converter->Convert($segments);

                $itineraries[] = $it;
            },
        ];
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        // Detecting Provider
        if ($this->assignProvider($parser->getHeaders()) === false) {
            return false;
        }

        // Detecting Language
        return $this->assignLang();
    }

    public function detectEmailByHeaders(array $headers)
    {
        return stripos($headers['subject'], 'Air2Sea Confirmation for Reservation ID') !== false;
    }

    public function detectEmailFromProvider($from)
    {
        return strpos($from, 'Royal Carribbean') !== false
            || stripos($from, '@rccl.com') !== false;
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        // Detecting Provider
        if ($this->assignProvider($parser->getHeaders()) === false) {
            return false;
        }

        // Detecting Language
        $this->assignLang();

        $this->date = strtotime($parser->getHeader("date"));

        $this->http->FilterHTML = false;

        $body = $parser->getHTMLBody();

        if (empty($body)) {
            $body = $parser->getPlainBody();
            $this->http->SetEmailBody($body);
        }

        $itineraries = [];

        foreach ($this->processors as $re => $processor) {
            if (preg_match($re, $body)) {
                $processor($itineraries);

                break;
            }
        }

        $result = [
            'emailType'  => 'Flight' . ucfirst($this->lang),
            'parsedData' => [
                'Itineraries' => $itineraries,
            ],
            'providerCode' => $this->providerCode,
        ];

        return $result;
    }

    private function assignProvider($headers)
    {
        $condition1 = $this->http->XPath->query('//node()[contains(normalize-space(.),"you are a Celebrity Cruises guest") or contains(normalize-space(.),"FLIGHTS by Celebrity") or contains(normalize-space(.),"@celebritycruises.com")]')->length > 0;
        $condition2 = stripos($headers['from'], '@celebritycruises.com') !== false;

        if ($condition1 || $condition2) {
            $this->providerCode = 'celebritycruises';

            return true;
        }

        $condition1 = $this->http->XPath->query('//node()[contains(normalize-space(.),"you are a Royal Caribbean® guest") or contains(normalize-space(.),"Royal Caribbean Cruises") or contains(normalize-space(.),"@rccl.com")]')->length > 0;
        $condition2 = stripos($headers['from'], 'Royal Carribbean') !== false || stripos($headers['from'], '@rccl.com') !== false;

        if ($condition1 || $condition2) {
            $this->providerCode = 'royalcaribbean';

            return true;
        }

        return false;
    }

    public static function getEmailProviders()
    {
        return ['royalcaribbean', 'celebritycruises'];
    }

    private function assignLang()
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

    private function getAir($root, $num = 0)
    {
        $table = [];

        foreach ($this->http->XPath->query("./td", $root) as $c=>$col) {
            foreach (preg_split("/\s*<br[^>]*>\s*/i", re("/<td.*?>\s*(.*?)\s*<\/td>/is", $col->ownerDocument->saveHTML($col))) as $r => $rowHtml) {
                $table[$r][$c] = $this->htmlToText($rowHtml);
            }
        }

        return [
            'Dep' => $table[$num * 2],
            'Arr' => $table[$num * 2 + 1],
        ];
    }

    private function getCruise()
    {
        $TDs = $this->http->XPath->query("//*[normalize-space(text())='Cruise Itinerary:']/ancestor::tr[1]/following-sibling::tr[1]/td");
        $res = [];

        foreach ($TDs as $c => $root) {
            $rows = preg_split("/\s*<br[^>]*>\s*/i", re("/<td[^>]+>(.*?)<\/td>/is", $root->ownerDocument->saveHTML($root)));

            foreach ($rows as $r => $rowHtml) {
                $res[$r][$c] = $this->htmlToText($rowHtml);
            }
        }

        return $res;
    }

    private function getField($str)
    {
        return $this->http->FindSingleNode("//*[normalize-space(text())='{$str}']/ancestor-or-self::td[1]/following-sibling::td[1]");
    }

    private function htmlToText($string = ''): string
    {
        $string = str_replace("\n", '', $string);
        $string = preg_replace('/<br\b[ ]*\/?>/i', "\n", $string); // only <br> tags
        $string = preg_replace('/<[A-z]+\b.*?\/?>/', '', $string); // opening tags
        $string = preg_replace('/<\/[A-z]+\b[ ]*>/', '', $string); // closing tags
        $string = htmlspecialchars_decode($string);

        return trim($string);
    }
}
