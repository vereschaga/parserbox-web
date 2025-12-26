<?php

namespace AwardWallet\Engine\mileageplus\Email;

class FlightDelayOrReschedule extends \TAccountChecker
{
    public $mailFiles = "mileageplus/it-12461933.eml, mileageplus/it-12588121.eml, mileageplus/it-2191488.eml, mileageplus/it-2191519.eml, mileageplus/it-28480320.eml, mileageplus/it-28484140.eml, mileageplus/it-6314471.eml";

    private $provider = "united.com";

    private $detectBody = [
        'es'  => 'Para información de último momento del estado de vuelo, vaya a united.com',
        'en'  => 'For up-to-the-minute flight status information, go to united.com',
        'en2' => 'We\'ll see you soon! Your flight to',
    ];

    private $year = '';

    private $subject = '';

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->year = date('Y', strtotime($parser->getDate()));
        $this->subject = $parser->getSubject();
        $text = $parser->getPlainBody() ? text($parser->getPlainBody()) : text($parser->getHTMLBody());

        return [
            'parsedData' => ['Itineraries' => $this->parseEmail($text)],
            'emailType'  => 'FlightDelayOrReschedule',
        ];
    }

    public static function getEmailTypesCount()
    {
        return 2;
    }

    public static function getEmailLanguages()
    {
        return ['es', 'en'];
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->provider) != false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return stripos($headers['from'], $this->provider) !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody() ? $parser->getHTMLBody() : $parser->getPlainBody();

        foreach ($this->detectBody as $lang => $dt) {
            if (stripos($body, $dt) !== false) {
                return true;
            }
        }

        return false;
    }

    private function parseEmail($text)
    {
        /** @var \AwardWallet\ItineraryArrays\AirTrip $it */
        $it = ['Kind' => 'T'];

        if (preg_match('/(?:Número\s+de\s+confirmación|Confirmation number|Confirmation)\s*:\s+([\w\-]+)/i', $text, $m)) {
            $it['RecordLocator'] = $m[1];
        }
        /** @var \AwardWallet\ItineraryArrays\AirTripSegment $seg */
        $seg = [];

        if (preg_match('/(?:vuelo\s+United|United flight)\s+([A-Z]{2})?\s*(\d+)/i', $text, $m)) {
            $seg['AirlineName'] = !empty($m[1]) ? $m[1] : 'UA';
            $seg['FlightNumber'] = $m[2];
        }
        if (empty($seg['AirlineName']) &&
            preg_match('/Your flight information:\s+([A-Z\d][A-Z]|[A-Z][A-Z\d])?\s*(\d{1,5})\s+Departs:/i', $text, $m)
        ) {
            $seg['AirlineName'] = !empty($m[1]) ? $m[1] : 'UA';
            $seg['FlightNumber'] = $m[2];
        }

        if (empty($seg['AirlineName']) && !empty($this->subject)) {
            if (preg_match("#:\s*([A-Z\d]{2})(\d{1,5})\s+to\s+#", $this->subject, $m)) {
                $seg['AirlineName'] = $m[1];
                $seg['FlightNumber'] = $m[2];
            } elseif (preg_match("#-\s*([A-Z\d]{2})(\d{1,5})\s+departing#", $this->subject, $m)) {
                $seg['AirlineName'] = $m[1];
                $seg['FlightNumber'] = $m[2];
            }
        }
        $node = $this->http->FindSingleNode("//text()[starts-with(normalize-space(.), 'For flight status updates, go to')]");

        if (preg_match('/united\.com\/en\/us\/flightstatus\/details\/(\d+)\/.+\/([A-Z]{3})\/([A-Z]{3})\/([A-Z\d]{2})/', $node, $m)) {
            $seg['DepCode'] = $m[2];
            $seg['ArrCode'] = $m[3];

            if (empty($seg['FlightNumber']) && empty($seg['AirlineName'])) {
                $seg['FlightNumber'] = $m[1];
                $seg['AirlineName'] = $m[4];
            }
        }

        foreach (['Dep' => '(?:sale|Departs)', 'Arr' => '(?:llega|Arrives)'] as $key => $value) {
            $re = [];
            $r = '#';
            $r .= 'Ahora\s+' . $value . '\s*:\s+';
            $r .= '(?P<Time>\d+:\d+\s*(?:am|pm)?)\s+';
            $r .= 'a\s+las\s+(?P<Month>\w+)\s+(?P<Day>\d+)\s+(?:en|desde\s+la\s+sala\s+\w+,)\s+';
            $r .= '(?P<Name>.*?)\s+\((?P<Code>\w{3})(?:\s+-\s+(?P<NameAdd>.*))?\)';
            $r .= '#i';
            $re[] = $r;
            // Departs: Gate 64, San Francisco, CA (SFO) at 7:05 a.m. on May 12
            $r = '/' . $value . '\s*:\s+(?:\w+\s+\d+,\s+)?(?<Name>[\w\,\s\/]+)\s+\((?<Code>(?-i)[A-Z]{3})(?:\s+-\s+(?<NameAdd>\w+))?\)';
            $r .= '\s+at\s+(?<Time>\d{1,2}:\d{2}\s+[pam\.]{2,4})\s+on\s+(?<Month>\w+)\s+(?<Day>\d{1,2})/iu';
            $re[] = $r;
            // Now departs: 3:15 p.m. on July 18 from gate C31, Houston, TX (IAH - Intercontinental)
            $r = '/' . $value . '\s*:\s*(?<Time>\d{1,2}:\d{2}\s+[pam\.]{2,4})\s+on\s+(?<Month>\w+)\s+(?<Day>\d{1,2})\s+(?:\w+\s+\d+,\s+)?(?<Name>[\w\,\s\/]+)\s+\((?<Code>(?-i)[A-Z]{3})(?:\s+-\s+(?<NameAdd>\w+))?\)/iu';
            $re[] = $r;
            // Departs: Denver at 9:48 a.m. on September 5 from Gate B44
            $r = '/' . $value . '\s*:\s*(?<Name>.+?)\s*at\s*(?<Time>\d{1,2}:\d{2}\s+[pam\.]{2,4})\s+on\s+(?<Month>\w+)\s+(?<Day>\d{1,2})/iu';
            $re[] = $r;
            // Now departs: 9:45 p.m. on June 20 from YYZ
            $r = '/' . $value . '\s*:\s*(?<Time>\d{1,2}:\d{2}\s+[pam\.]{2,4})\s+on\s+(?<Month>\w+)\s+(?<Day>\d{1,2})\s+from\s*(?<Code>(?-i)[A-Z]{3})/iu';
            $re[] = $r;
            // Now arrives: 6:39 p.m. on July 18 at MCO
            $r = '/' . $value . '\s*:\s*(?<Time>\d{1,2}:\d{2}\s+[pam\.]{2,4})\s+on\s+(?<Month>\w+)\s+(?<Day>\d{1,2})\s+at\s+(?<Code>(?-i)[A-Z]{3})/iu';
            $re[] = $r;
            // Departs: Honolulu, Gate 8 at 8:57 p.m.
            // Arrives: Denver at 7:29 a.m. (Apr. 13)
            $r = '/' . $value . '\s*:\s*(?<Name>[\w\,\s\/]+?)(?:,\s*Gate.+?)?\s+at\s+(?<Time>\d{1,2}:\d{2}\s+[pam\.]{2,4})\s*(\((?<Month>\w+)[\.]*\s+(?<Day>\d{1,2})\))?/iu';
            $re[] = $r;

            foreach ($re as $r) {
                if (preg_match($r, $text, $m)) {
                    if (!empty($m['Day']) && !empty($m['Month'])) {
                        $seg[$key . 'Date'] = strtotime($m['Day'] . ' ' . en($m['Month']) . ' ' . $this->year . ', ' . $m['Time']);
                    } else {
                        $seg[$key . 'Date'] = MISSING_DATE;
                    }

                    if (!empty($m['Name'])) {
                        $seg[$key . 'Name'] = $m['Name'] . (!empty($m['NameAdd']) ? ' (' . $m['NameAdd'] . ')' : '');
                    }

                    if (!empty($m['Code']) && empty($seg[$key . 'Code'])) {
                        $seg[$key . 'Code'] = $m['Code'];
                    }

                    if (empty($m['Code']) && empty($seg[$key . 'Code'])) {
                        $seg[$key . 'Code'] = TRIP_CODE_UNKNOWN;
                    }

                    break;
                }
            }
                if (empty($seg['ArrCode']) && empty($seg['ArrCode']) && stripos($text, 'you know it s time to check in for your flight to') !== false) {
                    $seg['ArrCode'] = TRIP_CODE_UNKNOWN;
                    $seg['ArrDate'] = MISSING_DATE;

                }
        }
        $it['TripSegments'][] = $seg;

        return [$it];
    }
}
