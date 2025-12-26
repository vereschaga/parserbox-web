<?php

namespace AwardWallet\Engine\tport\Email;

class ItineraryDetailedPlain extends \TAccountChecker
{
    public $mailFiles = "tport/it-6150245.eml, tport/it-6240642.eml";

    public $reBody = 'Travelport ViewTrip';
    public $reBody2 = [
        'en' => 'Itinerary Information',
    ];

    public static $dictionary = [
        'en' => [],
    ];

    public $lang = 'en';

    public function findСutSection($input, $searchStart, $searchFinish)
    {
        $inputResult = null;
        $left = mb_strstr($input, $searchStart);

        if (is_array($searchFinish)) {
            $i = 0;

            while (empty($inputResult)) {
                if (isset($searchFinish[$i])) {
                    $inputResult = mb_strstr($left, $searchFinish[$i], true);
                } else {
                    return false;
                }
                $i++;
            }
        } else {
            $inputResult = mb_strstr($left, $searchFinish, true);
        }

        return mb_substr($inputResult, mb_strlen($searchStart));
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName(".*htm");

        if (isset($pdfs) && count($pdfs) > 0) {
            return null;
        } //exclude intersection with It1842856.php

        if (empty(trim($parser->getHTMLBody())) !== true) {
            $textBody = text($parser->getHTMLBody());
        } else {
            $textBody = text($parser->getPlainBody());
        }

        foreach ($this->reBody2 as $lang => $re) {
            if (strpos($textBody, $re) !== false) {
                $this->lang = $lang;

                break;
            }
        }

        $itineraries = $this->parseEmail($textBody);

        return [
            'parsedData' => ['Itineraries' => $itineraries],
            'emailType'  => 'ItineraryDetailedPlain',
        ];
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@travelport.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return stripos($headers['subject'], 'viewtrip-admin@travelport.com') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if (empty(trim($parser->getHTMLBody())) !== true) {
            $body = text($parser->getHTMLBody());
        } else {
            $body = text($parser->getPlainBody());
        }

        if (stripos($body, $this->reBody) === false) {
            return false;
        }

        foreach ($this->reBody2 as $re) {
            if (stripos($body, $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
    }

    protected function parseEmail($textBody)
    {
        $plainText = $this->findСutSection($textBody, 'Itinerary Information', 'is a means of displaying your reservation via the');
        $its = [];
        $passengers[] = $this->re('/[=]{3,}[>\s\n]+Trave[l]{1,2}er[>\s\n]+([^\n]+)(?:P-ADT|[>\s\n]+[=]{3,})/m', $plainText);

        if (preg_match_all("#Flight\s+-(.+?)===#s", $plainText, $m)) {
            foreach ($m[0] as $value) {
                $its[] = $this->parseTrip($value);
            }

            $its2 = $its;

            foreach ($its2 as $i => $it) {
                if ($i && ($j = $this->findRL($i, $it['RecordLocator'], $its)) != -1) {
                    $its[$j]['TripSegments'] = array_merge($its[$j]['TripSegments'], $its[$i]['TripSegments']);
                    $its[$j]['TripSegments'] = array_map('unserialize', array_unique(array_map('serialize', $its[$j]['TripSegments'])));
                    unset($its[$i]);
                }
            }

            foreach ($its as &$it) {
                $it['Passengers'] = $passengers;
            }
        }

        return array_values($its);
    }

    protected function findRL($g_i, $rl, $its)
    {
        foreach ($its as $i => $it) {
            if ($g_i != $i && $it['RecordLocator'] === $rl) {
                return $i;
            }
        }

        return -1;
    }

    protected function parseTrip($plainText)
    {
        $patterns = [
            'time'     => '(?<time>\d{1,2}:\d{2}(?:\s*AM|\s*PM)?)',
            'date'     => '(?:\S{3,}[,\s]+(?<date>\d{1,2}\s+\S{3,}\s+\d{4}|\S{3,}\s+\d{1,2}[,\s]+\d{4}))?',
            'name'     => '(?<name>.+?)',
            'code'     => '\((?<code>[A-Z]{3})\)',
            'terminal' => '(?:[,\s]+Terminal\s*(?<terminal>[A-Z\d]{1,2}))?',
        ];
        $it = [];
        $it['Kind'] = 'T';
        $it['RecordLocator'] = $this->re('/Confirmation\s+Number[s]?\s*:\s*([A-Z\d]{5,7})/', $plainText);
        $it['TripSegments'] = [];
        $seg = [];

        if (preg_match("/Flight\s+.+?\((?<airlinename>[A-Z\d]{2})\)\s+-\s+(?<flightnumber>\d+)\s*{$patterns['date']}/", $plainText, $matches)) {
            $seg['AirlineName'] = $matches['airlinename'];
            $seg['FlightNumber'] = $matches['flightnumber'];
            $date = $matches['date'];
        }

        if (preg_match("/Depart\s*:\s*{$patterns['time']}\s*{$patterns['date']}\s*{$patterns['name']}\s*{$patterns['code']}{$patterns['terminal']}/", $plainText, $matches)) {
            $dateDep = str_replace("\n", ' ', $matches['date'] ? $matches['date'] : $date);
            $seg['DepDate'] = strtotime($matches['time'], strtotime($dateDep));
            $seg['DepName'] = $matches['name'];
            $seg['DepCode'] = $matches['code'];

            if (isset($matches['terminal'])) {
                $seg['DepartureTerminal'] = $matches['terminal'];
            }
        }

        if (preg_match("/Arrive\s*:\s*{$patterns['time']}\s*{$patterns['date']}\s*{$patterns['name']}\s*{$patterns['code']}{$patterns['terminal']}/", $plainText, $matches)) {
            $dateArr = str_replace("\n", ' ', $matches['date'] ? $matches['date'] : $date);
            $seg['ArrDate'] = strtotime($matches['time'], strtotime($dateArr));
            $seg['ArrName'] = $matches['name'];
            $seg['ArrCode'] = $matches['code'];

            if (isset($matches['terminal'])) {
                $seg['ArrivalTerminal'] = $matches['terminal'];
            }
        }
        $seg['Cabin'] = $this->re("#Class\s+of\s+Service:\s+(.+?)\s+\(#", $plainText);
        $seg['BookingClass'] = $this->re("#Class\s+of\s+Service:.+?\(([A-Z]{1,2})\)#", $plainText);
        $seg['Operator'] = $this->re("#Flight\s+Operated\s+By:\s+(.+?)\n#", $plainText);
        $seg['Aircraft'] = $this->re("#Equipment:\s+(.+?)\s+Flying\s+Time#", $plainText);
        $seg['Duration'] = $this->re("#Flying\s+Time:\s+(.+?)\n#", $plainText);
        $seg['Meal'] = $this->re("#Meal\s+Service:\s+(.+?)\n#", $plainText);
        $seg = array_filter($seg);

        if (preg_match('/Flight\s+' . $seg['FlightNumber'] . '\s+Non-stop\s*\n/i', $plainText)) {
            $seg['Stops'] = 0;
        }

        if (preg_match('/In-Flight\s+Services:[^:]*Non-smoking/i', $plainText)) {
            $seg['Smoking'] = false;
        }
        $it['TripSegments'][] = $seg;
        $it['Status'] = $this->re("#\nStatus\s+(.+?)\n#", $plainText);

        return $it;
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
}
