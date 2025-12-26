<?php

namespace AwardWallet\Engine\viator\Email;

use AwardWallet\Engine\MonthTranslate;

class YourBooking extends \TAccountChecker
{
    public $mailFiles = "viator/it-6696574.eml, viator/it-6819399.eml";
    public $reFrom = "no-reply@viator.com";
    public $reSubject = [
        "en" => "Your Viator Booking",
    ];
    public $reBody = 'Viator';
    public $reBody2 = [
        "en" => ["Booking Information", "Booking Confirmation"],
    ];

    public static $dictionary = [
        "en" => [],
    ];

    public $lang = "en";

    public function parseHtml(&$itineraries)
    {
        $xpath = "//text()[" . $this->starts("Location:") . "]/ancestor::td[1]";
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $it = [];

            $it['Kind'] = "E";
            // ConfNo
            if (!$it['ConfNo'] = $this->http->FindSingleNode(".//text()[" . $this->starts("Booking Reference:") . "]", $root, true, "#:\s+(.+)#")) {
                $it['ConfNo'] = $this->nextText("Booking Reference:", $root);
            }

            // TripNumber
            // Name
            $it['Name'] = $this->http->FindSingleNode("./descendant::text()[normalize-space(.)][1]", $root);

            // StartDate
            if (!$it['StartDate'] = strtotime($this->normalizeDate($this->http->FindSingleNode(".//text()[" . $this->starts("Travel Date:") . "]", $root, true, "#:\s+(.+)#")))) {
                $it['StartDate'] = strtotime($this->normalizeDate($this->nextText("Travel Date:", $root)));
            }

            if (!$time = $this->http->FindSingleNode(".//text()[" . $this->starts("Travel Option:") . "]", $root, true, "#\d+(?:[:.]\d+)?\s*[ap]m#i")) {
                $time = $this->re("#(\d+(?:[:.]\d+)?\s*[ap]m)#i", $this->nextText("Travel Option:", $root));
            }

            if ($time) {
                $it['StartDate'] = strtotime($this->correctTimeString($time), $it['StartDate']);
            }

            // EndDate
            // Address
            if (!$it['Address'] = $this->http->FindSingleNode(".//text()[" . $this->starts("Location:") . "]", $root, true, "#:\s+(.+)#")) {
                $it['Address'] = $this->nextText("Location:", $root);
            }

            // Phone
            // DinerName
            if (!$it['DinerName'] = $this->http->FindSingleNode(".//text()[" . $this->starts("Lead Traveler:") . "]", $root, true, "#:\s+(.+)#")) {
                $it['DinerName'] = $this->nextText("Lead Traveler:", $root);
            }

            // Guests
            if (!$it['Guests'] = $this->http->FindSingleNode(".//text()[" . $this->starts("Number of Travelers:") . "]", $root, true, "#:\s+(\d+)#")) {
                $it['Guests'] = $this->re("#^(\d+)#", $this->nextText("Number of Travelers:", $root));
            }

            // TotalCharge
            if (!$it['TotalCharge'] = $this->amount($this->http->FindSingleNode(".//text()[" . $this->starts("Price:") . "]", $root, true, "#:\s+(.+)#"))) {
                $it['TotalCharge'] = $this->amount($this->nextText("Price:", $root));
            }

            // Currency
            if (!$it['Currency'] = $this->currency($this->http->FindSingleNode(".//text()[" . $this->starts("Price:") . "]", $root, true, "#:\s+(.+)#"))) {
                $it['Currency'] = $this->currency($this->nextText("Price:", $root));
            }

            // Tax
            // SpentAwards
            // EarnedAwards
            // AccountNumbers
            // Status
            // Cancelled
            // ReservationDate
            // NoItineraries
            // EventType
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
            if (stripos($headers["subject"], $re) !== false) {
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
            $reB = (array) $re;

            foreach ($reB as $r) {
                if (strpos($body, $r) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->date = strtotime($parser->getHeader('date'));

        $this->http->FilterHTML = true;
        $itineraries = [];

        foreach ($this->reBody2 as $lang => $re) {
            $reB = (array) $re;

            foreach ($reB as $r) {
                if (strpos($this->http->Response["body"], $r) !== false) {
                    $this->lang = $lang;

                    break 2;
                }
            }
        }

        $this->parseHtml($itineraries);

        $class = explode('\\', __CLASS__);
        $result = [
            'emailType'  => end($class) . ucfirst($this->lang),
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
            "#^([^\d\s]+)\s+(\d+),\s+(\d{4})$#",
        ];
        $out = [
            "$2 $1 $3",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
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
        return (float) str_replace(",", ".", preg_replace("#[.,](\d{3})#", "$1", $this->re("#([\d\,\.]+)#", $s)));
    }

    private function currency($s)
    {
        return $this->re("#^([A-Z]{3})#", $s);
    }

    private function nextText($field, $root = null)
    {
        $rule = $this->eq($field);

        return $this->http->FindSingleNode("(.//text()[{$rule}])[1]/following::text()[normalize-space(.)][1]", $root);
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
        }

        return implode(" or ", array_map(function ($s) {
            return "normalize-space(.)=\"{$s}\"";
        }, $field));
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
        }

        return implode(" or ", array_map(function ($s) {
            return "starts-with(normalize-space(.), \"{$s}\")";
        }, $field));
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
        }

        return implode(" or ", array_map(function ($s) {
            return "contains(normalize-space(.), \"{$s}\")";
        }, $field));
    }

    private function correctTimeString($time)
    {
        if (preg_match("#(\d+):(\d+)\s*([ap]m)#i", $time, $m)) {
            if (($m[1] == 0 && stripos($m[3], 'am') !== false) || $m[1] > 12) {
                return $m[1] . ":" . $m[2];
            }
        }

        return $time;
    }
}
