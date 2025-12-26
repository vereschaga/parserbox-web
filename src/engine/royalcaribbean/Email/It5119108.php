<?php

namespace AwardWallet\Engine\royalcaribbean\Email;

use CruiseSegmentsConverter as CruiseSegmentsConverter;

class It5119108 extends \TAccountChecker
{
    use \DateTimeTools;
    use \PriceTools;

    public $reFrom = "no-reply@royalcaribbean.de";
    public $reSubject = [
        "de"=> "Festbuchungsbestätigung - Royal Caribbean International",
    ];
    public $reBody = 'Royal Caribbean International';
    public $reBody2 = [
        "de"=> "Ihr Kreuzfahrtverlauf",
    ];

    public static $dictionary = [
        "de" => [],
    ];

    public $lang = "de";

    public function parseHtml(&$itineraries)
    {
        $it = [];
        $it['Kind'] = 'T';
        // RecordLocator
        $it['RecordLocator'] = $this->nextText("Ihre Buchungsnummer lautet:");

        // TripNumber
        // Passengers
        $it['Passengers'] = $this->http->FindNodes("//text()[normalize-space(.)='Name']/ancestor::tr[2]/following-sibling::tr[.//td[6] and not(.//tr[2])]/descendant::text()[normalize-space()][2]");

        // AccountNumbers
        // Cancelled
        // ShipName
        $it['ShipName'] = $this->nextText("Schiff:");

        // ShipCode
        // CruiseName
        $it['CruiseName'] = $this->nextText("Kreuzfahrt:");

        // Deck
        $it['Deck'] = $this->re("#/\s*(.+)#", $this->nextText("Kabine/Deck:", null, 2));

        // RoomNumber
        $it['RoomNumber'] = $this->nextText("Kabine/Deck:");

        // RoomClass
        $it['RoomClass'] = $this->re("#-\s*(.*?)\s*/#", $this->nextText("Kabine/Deck:", null, 2));

        // Status

        // TotalCharge
        $it['TotalCharge'] = $this->amount($this->nextText("Gesamtpreis"));

        // Currency
        $it['Currency'] = $this->http->FindSIngleNode("//text()[starts-with(normalize-space(.), 'Kosten in')]", null, true, "#Kosten in\s+([A-Z]{3})#");

        // TripCategory
        $it['TripCategory'] = TRIP_CATEGORY_CRUISE;

        // TripSegments

        $xpath = "//text()[normalize-space(.)='Anlaufhafen']/ancestor::tr[1]/following-sibling::tr";
        $nodes = $this->http->XPath->query($xpath);

        if ($nodes->length == 0) {
            $this->http->Log("segments root not found: $xpath", LOG_LEVEL_NORMAL);
        }

        foreach ($nodes as $root) {
            $itsegment = [];

            if ($this->http->FindSingleNode("./td[4]", $root) || $this->http->FindSingleNode("./td[5]", $root)) {
                $date = strtotime($this->normalizeDate($this->http->FindSingleNode("./td[2]", $root)));
                // Port
                $itsegment['Port'] = $this->http->FindSingleNode("./td[3]", $root);

                // DepDate
                if ($time = $this->http->FindSingleNode("./td[5]", $root)) {
                    $itsegment['DepDate'] = strtotime($time, $date);
                }

                // ArrDate
                if ($time = $this->http->FindSingleNode("./td[4]", $root)) {
                    $itsegment['ArrDate'] = strtotime($time, $date);
                }

                $it['TripSegments'][] = $itsegment;
            }
        }
        $converter = new CruiseSegmentsConverter();
        $it['TripSegments'] = $converter->Convert($it['TripSegments']);

        if (empty($it['TripSegments'])) {
            $itineraries[] = [];
        } else {
            $itineraries[] = $it;
        }
    }

    public function detectEmailFromProvider($from)
    {
        return strpos($from, $this->reFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (strpos($headers["from"], $this->reFrom) === false) {
            return false;
        }

        foreach ($this->reSubject as $re) {
            if (strpos($headers["subject"], $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        if (strpos($body, $this->reBody) === false) {
            return false;
        }

        foreach ($this->reBody2 as $re) {
            if (strpos($body, $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->date = strtotime($parser->getHeader('date'));

        $this->http->FilterHTML = false;
        $itineraries = [];
        $this->http->setBody(str_replace(" ", " ", $this->http->Response["body"])); // bad fr char " :"

        foreach ($this->reBody2 as $lang=> $re) {
            if (strpos($this->http->Response["body"], $re) !== false) {
                $this->lang = $lang;

                break;
            }
        }

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

    private function nextText($field, $root = null, $n = 1)
    {
        return $this->http->FindSingleNode("(.//text()[normalize-space(.)='{$field}'])[1]/following::text()[normalize-space(.)][{$n}]", $root);
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function normalizeDate($str)
    {
        $year = date("Y", $this->date);
        $in = [
            "#^(\d+)\s+([^\d\s]+)\s+(\d{4})$#",
        ];
        $out = [
            "$1 $2 $3",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#[^\d\s-\./:]#", $str)) {
            $str = $this->dateStringToEnglish($str);
        }

        return $str;
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
