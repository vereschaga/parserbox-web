<?php

namespace AwardWallet\Engine\pana\Email;

use AwardWallet\Engine\MonthTranslate;

class YourConfirmation extends \TAccountChecker
{
    public $mailFiles = "pana/it-10768172.eml, pana/it-9782131.eml, pana/it-9782157.eml, pana/it-9782314.eml, pana/it-9782959.eml";

    public $from = 'hi@pana.com';
    public $provider = '@pana.com';

    public $reBody = [
        'en' => ['Here are the details:', 'to book with Pana'],
    ];
    public $reSubject = [
        'Your confirmation from Pana (',
    ];
    public $lang = 'en';
    public static $dict = [
        'en' => [],
    ];

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        //		$body = $this->http->Response['body'];
        //		$this->AssignLang($body);

        $its = $this->parseEmail();

        return [
            'emailType'  => "YourConfirmation",
            'parsedData' => ['Itineraries' => $its],
        ];
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        foreach ($this->reBody as $value) {
            if (stripos($body, $value[0]) !== false && stripos($body, $value[1]) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (stripos($headers['from'], $this->from) === false) {
            return false;
        }

        foreach ($this->reSubject as $reSubject) {
            if (stripos($headers['subject'], $reSubject) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->provider) !== false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    private function parseEmail()
    {
        $its = [];

        /* ------------- Flights ------------- */
        $nodes = $this->http->XPath->query("//text()[contains(., 'Flight from ')]/ancestor::table[contains(normalize-space(.), 'Departure')][1]");

        if ($nodes->length > 0) {
            $it = ['Kind' => 'T'];
            $it['RecordLocator'] = $this->http->FindSingleNode("//text()[contains(., 'Airline Record Locator')]/following::text()[normalize-space(.)][1]", null, true, "#[A-Z\d]{5,7}#i");
            $it['Passengers'][] = $this->http->FindSingleNode("//text()[contains(., 'Travelers')]/following::text()[normalize-space(.)][1]");
            $it['Passengers'] = array_unique($it['Passengers']);
            $it['TripSegments'] = [];
        }

        foreach ($nodes as $root) {
            $segment = [];

            $route = $this->http->FindSingleNode(".//text()[contains(., 'Flight from')]/following::text()[normalize-space(.)][1]", $root);

            if (preg_match("#([A-Z]{3})\W+([A-Z]{3})#", $route, $m)) {
                $segment['DepCode'] = $m[1];
                $segment['ArrCode'] = $m[2];
            }

            $route = $this->http->FindSingleNode(".//text()[contains(normalize-space(.), 'Flight from')]", $root);
            $this->logger->info($route);

            if (preg_match("#Flight\s+from\s+(.+)\s+to\s+(.+)#", $route, $m)) {
                $segment['DepName'] = $m[1];
                $segment['ArrName'] = $m[2];
            }

            $flight = $this->http->FindSingleNode(".//text()[contains(., 'Flight Number(s)')]/following::text()[normalize-space(.)][1]", $root);

            if (preg_match_all("#\b([A-Z\d]{2})\s*(\d{1,5})\b#", $flight, $FlightNumbers, PREG_SET_ORDER)) {
                $segment['AirlineName'] = $FlightNumbers[0][1];
                $segment['FlightNumber'] = $FlightNumbers[0][2];
            }

            $segment['DepDate'] = strtotime($this->normalizeDate($this->http->FindSingleNode(".//text()[contains(., 'Departure')]/following::text()[normalize-space(.)][1]", $root)));
            $segment['ArrDate'] = strtotime($this->normalizeDate($this->http->FindSingleNode(".//text()[contains(., 'Arrival')]/following::text()[normalize-space(.)][1]", $root)));

            $segment['Cabin'] = $this->http->FindSingleNode(".//text()[contains(., 'Class of Service')]/following::text()[normalize-space(.)][1]", $root);

            $layovers = $this->http->FindSingleNode(".//text()[contains(., 'Layovers')]/following::text()[normalize-space(.)][1]", $root);

            if (stripos($layovers, 'Laying over in ') === false) {
                $it['TripSegments'][] = $segment;
            } else {
                if (preg_match_all("#\s+([A-Z]{3})\s*\(#", $layovers, $m)) {
                    $defaultSegment = $segment;
                    $segment['ArrCode'] = $m[1][0];
                    $segment['ArrName'] = '';
                    $segment['ArrDate'] = MISSING_DATE;
                    $segments = [];
                    $segments[0] = $segment;
                    $layoverCounts = count($m[1]);

                    for ($i = 0; $i <= $layoverCounts - 1; $i++) {
                        $segment2 = $defaultSegment;
                        $segment2['DepCode'] = $m[1][$i];
                        $segment2['DepName'] = '';
                        $segment2['DepDate'] = MISSING_DATE;

                        if (count($FlightNumbers) == $layoverCounts + 1) {
                            $segment2['AirlineName'] = $FlightNumbers[$i + 1][1];
                            $segment2['FlightNumber'] = $FlightNumbers[$i + 1][2];
                        } elseif (count($FlightNumbers) !== 1 || count($FlightNumbers) !== $layoverCounts + 1) {
                            unset($segment2['AirlineName']);
                            unset($segment2['FlightNumber']);
                        }
                        $segments[$i]['ArrCode'] = $m[1][$i];
                        $segments[$i]['ArrName'] = '';
                        $segments[$i]['ArrDate'] = MISSING_DATE;
                        $segments[] = $segment2;
                    }
                    $it['TripSegments'] = array_merge($it['TripSegments'], $segments);
                }
            }
        }

        if (isset($it)) {
            $its[] = $it;
        }

        /* ------------- Hotels ------------- */
        $nodes = $this->http->XPath->query("//text()[normalize-space(.)='Hotel']/ancestor::table[contains(normalize-space(.), 'Check In')][1]");

        foreach ($nodes as $root) {
            $it = ['Kind' => 'R'];
            $it['ConfirmationNumber'] = CONFNO_UNKNOWN;
            $it['GuestNames'][] = trim($this->http->FindSingleNode("//text()[contains(normalize-space(.), 'Hi ')]", null, true, "#Hi\s+(.+),#"));
            $it['HotelName'] = $this->http->FindSingleNode("(.//text()[contains(normalize-space(.), 'Hotel')]/following::text()[normalize-space(.)][1])[1]", $root);
            $it['Address'] = $this->http->FindSingleNode(".//text()[contains(normalize-space(.), 'Address')]/following::text()[normalize-space(.)][1]", $root);
            $it['RoomType'] = $this->http->FindSingleNode(".//text()[contains(normalize-space(.), 'Room Type')]/following::text()[normalize-space(.)][1]", $root);
            $it['CheckInDate'] = strtotime($this->normalizeDate($this->http->FindSingleNode(".//text()[contains(normalize-space(.), 'Check In')]/following::text()[normalize-space(.)][1]", $root)));
            $it['CheckOutDate'] = strtotime($this->normalizeDate($this->http->FindSingleNode(".//text()[contains(normalize-space(.), 'Check Out')]/following::text()[normalize-space(.)][1]", $root)));
            $its[] = $it;
        }

        return $its;
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
                if (stripos($body, $reBody[0]) !== false || stripos($body, $reBody[1]) !== false) {
                    $this->lang = $lang;

                    break;
                }
            }
        }

        return true;
    }

    private function normalizeDate($str)
    {
        $in = [
            '#^\s*(\w+)\s*(\d{1,2}),\s*(\d{4})\s+at\s+(\d+:\d+\s*[AP]M)\s*$#u', //October 8, 2017 at 3:00 PM
        ];
        $out = [
            '$2 $1 $3 $4',
        ];
        $str = preg_replace($in, $out, $str);
        //		if(preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)){
        //			if($en = MonthTranslate::translate($m[1], $this->lang))
        //				$str = str_replace($m[1], $en, $str);
        //		}
        return $str;
    }
}
