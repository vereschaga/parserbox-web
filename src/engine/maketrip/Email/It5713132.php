<?php

namespace AwardWallet\Engine\maketrip\Email;

// TODO: parser for junk mails

class It5713132 extends \TAccountChecker
{
    public $mailFiles = "maketrip/it-5713132.eml";

    public $reSubject = [
        'en' => ['MakeMyTrip E-Ticket'],
    ];

    public $langDetectors = [
        'en' => ['Itinerary and Reservation Details'],
    ];

    public static $dictionary = [
        'en' => [],
    ];

    public $lang = 'en';

    public function parseHtml(&$itineraries)
    {
        $text = $this->http->Response['body'];

        $it = [];
        $it['Kind'] = 'T';

        // RecordLocator
        $it['RecordLocator'] = $this->re("#MakeMyTrip Booking ID\s*-\s*(\w+)#", $text);

        // TripNumber
        // Passengers
        if (preg_match_all("#(?:^|\n)(.*?)\s+(?:Adult|Child)\s+\w+\s+\d+\s+(\d+)#", $this->re("#Passenger Name Type Airline PNR E-Ticket Number\s*\n\s*" .
            "((?:.*?\s+(?:Adult|Child)\s+\w+\s+\d+\s+\d+\s*\n\s*)*)" .
            "Important Information#", $text), $m)) {
            $it['Passengers'] = $m[1];
            $it['TicketNumbers'] = $m[2];
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
        // TripCategory

        preg_match_all("#(?<AirlineName>\w{2})\s*-\s*(?<FlightNumber>\d+)\s*\n\s*" .
                        "Departure\s*\n\s*" .
                        "(?<DepName>.*?)\s*\(\s*(?<DepCode>[A-Z]{3})\s*\)(?:\s*Terminal.+)?\s*\n\s*" .
                        "[^\d\s]+,\s+(?<DepDate>\d+\s+[^\d\s]+\s+\d{4},\s+\d+:\d+)\s+hrs\s+Arrival\s*\n\s*" .
                        "(?<ArrName>.*?)\s*\(\s*(?<ArrCode>[A-Z]{3})\s*\)(?:\s*Terminal.+)?\s*\n\s*" .
                        "[^\d\s]+,\s+(?<ArrDate>\d+\s+[^\d\s]+\s+\d{4},\s+\d+:\d+)\s+hrs\s*\n\s*" .
                        "Non\s*-Stop Flight\s*\n\s*" .
                        "Duration:\s*(?<Duration>.+)(?:\s*\n\s*)+" .
                        "(?:Cabin:\s*(?<Cabin>.+))?#iu", $text, $segments, PREG_SET_ORDER);

        foreach ($segments as $segment) {
            $itsegment = [];

            $keys = [
                "AirlineName",
                "FlightNumber",
                "DepName",
                "DepCode",
                "ArrName",
                "ArrCode",
                "Duration",
                "Cabin",
            ];

            foreach ($keys as $key) {
                if (!empty($segment[$key])) {
                    $itsegment[$key] = $segment[$key];
                }

                $itsegment['DepDate'] = strtotime($segment['DepDate']);
                $itsegment['ArrDate'] = strtotime($segment['ArrDate']);
            }

            $it['TripSegments'][] = $itsegment;
        }
        $itineraries[] = $it;
    }

    public function detectEmailFromProvider($from)
    {
        return strpos($from, 'MakeMyTrip') !== false
            || stripos($from, '@makemytrip.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (stripos($headers['subject'], 'reply@makemytrip.com') !== false) {
            return true;
        }

        foreach ($this->reSubject as $phrases) {
            foreach ($phrases as $phrase) {
                if (stripos($headers['subject'], $phrase) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $parser->getPlainBody();

        $condition1 = stripos($body, 'makemytrip.com') === false;
        $condition2 = stripos($body, 'MakeMyTrip Booking') === false;
        $condition3 = stripos($body, 'MakeMyTrip E-Ticket') === false;

        if ($condition1 && $condition2 && $condition3) {
            return false;
        }

        return $this->assignLang($body);
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->date = strtotime($parser->getHeader('date'));

        $this->http->FilterHTML = false;
        $this->http->SetEmailBody($parser->getPlainBody());

        $this->assignLang($this->http->Response['body']);

        $itineraries = [];
        $this->parseHtml($itineraries);

        $result = [
            'emailType'  => 'reservations',
            'parsedData' => [
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

    protected function assignLang($text)
    {
        foreach ($this->langDetectors as $lang => $phrases) {
            foreach ($phrases as $phrase) {
                if (stripos($text, $phrase) === false) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function nextText($field, $root = null, $n = 1)
    {
        return $this->http->FindSingleNode("(.//text()[normalize-space(.)='{$field}'])[{$n}]/following::text()[normalize-space(.)][1]", $root);
    }

    private function nextCol($field, $root = null, $n = 1)
    {
        return $this->http->FindSingleNode("(.//td[not(.//td) and normalize-space(.)='{$field}'])[{$n}]/following-sibling::td[1]", $root);
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
        return (float) str_replace(",", ".", preg_replace("#[.,](\d{3})#", "$1", $s));
    }
}
