<?php
/**
 * Created by PhpStorm.
 * User: Roman.
 */

namespace AwardWallet\Engine\vueling\Email;

class FlightTicket extends \TAccountChecker
{
    use \DateTimeTools;
    public $mailFiles = "";
    public $reBody = [
        'en' => 'Booking code',
    ];
    public $lang = '';
    public static $dict = [
        'en' => [],
    ];

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $body = $this->http->Response['body'];

        if (isset($this->reBody)) {
            foreach ($this->reBody as $lang => $reBody) {
                if (stripos($body, $reBody) !== false) {
                    $this->lang = $lang;

                    break;
                }
            }
        }
        $its = $this->parseEmail();

        return [
            'parsedData' => ['Itineraries' => $its],
        ];
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        return $this->http->XPath->query("//*[contains(text(), 'Vueling')]")->length > 0;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers['from']) && stripos($headers['from'], "@vueling.com") !== false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, "@vueling.com") !== false;
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
        $it = ['Kind' => 'T', 'TripSegments' => []];
        $it['RecordLocator'] = $this->http->FindSingleNode("//*[contains(text(), 'Booking code')]/following::*[normalize-space(.)!=''][1]");
        $xpath = "//*[contains(text(), 'flight itinerary')]/ancestor::tr[1]/following-sibling::tr[ancestor::tbody[1]]";
        $roots = $this->http->XPath->query($xpath);

        if ($roots->length === 0) {
            $this->http->Log("segments not found: {$xpath}", LOG_LEVEL_NORMAL);
        }

        foreach ($roots as $root) {
            $seg = null;
            $seg = $this->processingSegments($this->http->FindSingleNode("descendant::span", $root));
            $it['TripSegments'][] = $seg;
        }

        return [$it];
    }

    private function processingSegments($str)
    {
        $regExp = '#(?<AirName>\D{2})(?<FlightN>\d+) (?<Day>\d{2})\/(?<Month>\d{2})\/(?<Year>\d{4}) \((?<DepCode>\w{3})\)\s*-\s*';
        $regExp .= '\((?<ArrCode>\w{3})\) (?<DepTime>\d+:\d+)\s*-\s*(?<ArrTime>\d+:\d+)#';

        if (preg_match($regExp, $str, $m)) {
            $date = $m['Month'] . '/' . $m['Day'] . '/' . $m['Year'];

            return [
                'AirlineName'  => $m['AirName'],
                'FlightNumber' => $m['FlightN'],
                'DepCode'      => $m['DepCode'],
                'ArrCode'      => $m['ArrCode'],
                'DepDate'      => strtotime($date . ' ' . $m['DepTime']),
                'ArrDate'      => strtotime($date . ' ' . $m['ArrTime']),
            ];
        }

        return null;
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }
}
