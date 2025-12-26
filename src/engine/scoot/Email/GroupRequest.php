<?php
/**
 * Created by PhpStorm.
 * User: roman.
 */

namespace AwardWallet\Engine\scoot\Email;

class GroupRequest extends \TAccountChecker
{
    public $mailFiles = "scoot/it-10196775.eml";

    public $reFrom = "do-not-reply@flyscoot.com";

    public $reSubject = [
        'Approved group request',
    ];
    public $reBody = '@flyscoot.com';
    public $reBody2 = [
        "en"=> "pleased to offer you a quote based on your request",
    ];

    public static $dictionary = [
        "en" => [],
    ];
    public $lang = "en";

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        //		$body = $parser->getHTMLBody();
        //		foreach($this->reBody2 as $lang => $re){
        //			if(strpos($body, $re) !== false) {
        //				$this->lang = $lang;
        //			}
        //		}
        return [
            'emailType'  => 'GroupRequest' . ucfirst($this->lang),
            'parsedData' => [
                'Itineraries' => $this->parseEmail(),
            ],
        ];
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

    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->reFrom) !== false;
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

    private function parseEmail()
    {
        /** @var \AwardWallet\ItineraryArrays\AirTrip $it */
        $it['Kind'] = 'T';

        $it['RecordLocator'] = CONFNO_UNKNOWN;

        $it['Passengers'][] = $this->http->FindSingleNode("//text()[" . $this->starts($this->t("Dear")) . "][1]", null, true, "#Dear\s+(.+),#");

        $numPass = $this->http->FindSingleNode("//text()[" . $this->eq($this->t("Passengers")) . "]/ancestor::*[local-name()='td' or local-name()='th'][1]/following-sibling::*[1]", null, true, "#^\s*(\d+)\s*$#");
        $total = $this->http->FindSingleNode("//text()[" . $this->starts($this->t("Fare details")) . "][1]/following::text()[normalize-space()][1]");

        if (!empty($numPass) && preg_match('#([A-Z]{3})\s*([\d\.]+)\s*\(per pax\)\s*\(Base Fare\s*[A-Z]{3}\s*([\d\.]+)\s+#', $total, $m)) {
            $it['Currency'] = $m[1];
            $it['TotalCharge'] = $numPass * $m[2];
            $it['BaseFare'] = $numPass * $m[3];
        }

        $xpath = "//text()[" . $this->eq($this->t("Scheduled Departure")) . "]/ancestor::tr[1]/following-sibling::tr";
        $roots = $this->http->XPath->query($xpath);

        if ($roots->length === 0) {
            $this->logger->info('Segments not found by xpath: ' . $xpath);

            return false;
        }

        foreach ($roots as $root) {
            /** @var \AwardWallet\ItineraryArrays\AirTripSegment $seg */
            $seg = [];

            $date = $this->getNode($root, 5);

            if (preg_match("#(.+)\s*\+(\d)\s*$#", $date, $m)) {
                $arrDate = '+ ' . $m[2] . 'day';
                $date = $m[1];
            } else {
                $arrDate = '';
            }

            $seg['FlightNumber'] = $this->getNode($root, 4, "#\b[A-Z\d]{2}\s*-\s*(\d{1,5})\b#");
            $seg['AirlineName'] = $this->getNode($root, 4, "#\b([A-Z\d]{2})\s*-\s*\d{1,5}\b#");

            $seg['DepDate'] = strtotime($this->normalizeDate($date . ', ' . $this->getNode($root, 6)));

            $seg['DepCode'] = $this->getNode($root, 2);

            $seg['ArrDate'] = strtotime($this->normalizeDate($date . ', ' . $this->getNode($root, 7)));

            if (!empty($arrDate)) {
                $seg['ArrDate'] = strtotime($arrDate, $seg['ArrDate']);
            }
            $seg['ArrCode'] = $this->getNode($root, 3);

            $it['TripSegments'][] = $seg;
        }

        return [$it];
    }

    private function getNode(\DOMNode $root, $td = 1, $re = null)
    {
        return $this->http->FindSingleNode('descendant::td[' . $td . ']', $root, true, $re);
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
        }

        return implode(" or ", array_map(function ($s) { return "normalize-space(.)=\"{$s}\""; }, $field));
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
        }

        return implode(" or ", array_map(function ($s) { return "starts-with(normalize-space(.), \"{$s}\")"; }, $field));
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
        }

        return implode(" or ", array_map(function ($s) { return "contains(normalize-space(.), \"{$s}\")"; }, $field));
    }

    private function preg_implode($field)
    {
        if (!is_array($field)) {
            $field = [$field];
        }

        return implode("|", array_map(function ($s) { return preg_quote($s); }, $field));
    }

    private function normalizeDate($str)
    {
        // $this->http->log($str);
        $in = [
            "#^(\d+)\s+(\w+),\s+(\d{4}),\s+(\d+:\d+)$#", //10 Mar, 2018 15:15
        ];
        $out = [
            "$1 $2 $3 $4",
        ];
        $str = preg_replace($in, $out, $str);
        //		if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)){
        //			if($en = \AwardWallet\Engine\MonthTranslate::translate($m[1], $this->lang)) {
        //				$str = str_replace($m[1], $en, $str);
        //			}
        //		}
        return $str;
    }
}
