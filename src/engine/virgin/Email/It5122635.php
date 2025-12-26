<?php

namespace AwardWallet\Engine\virgin\Email;

use AwardWallet\Schema\Parser\Email\Email;

class It5122635 extends \TAccountChecker
{
    use \DateTimeTools;
    use \PriceTools;
    public $mailFiles = "virgin/it-5122635.eml, virgin/it-5148937.eml, virgin/it-5704928.eml";

    public $reFrom = "@virginatlantic.com";
    public $reSubject = [
        "en"  => "Virgin Atlantic Airways e-Ticket",
        "en2" => "Your Virgin Atlantic itinerary confirmation",
    ];
    public $reBody = 'Virgin Atlantic';
    public $reBody2 = [
        "en" => ", here you come!",
    ];

    public static $dictionary = [
        "en" => [],
    ];

    public $lang = "en";

    public function parseHtml(Email $email)
    {
        $xpath = "//text()[normalize-space(.)='Passengers']/ancestor::tr[1]/following-sibling::tr[contains(., ')')]//tr[not(.//tr)]";
        $nodes = $this->http->XPath->query($xpath);
        $info = [];

        foreach ($nodes as $root) {
            $flights = $this->http->FindNodes("./td[1]/descendant::text()[normalize-space(.)]", $root);
            $seats = $this->http->FindNodes("./td[2]/descendant::text()[normalize-space(.)]", $root);
            $class = $this->http->FindNodes("./td[3]/descendant::text()[normalize-space(.)]", $root);

            foreach ($flights as $i=> $flight) {
                if (isset($seats[$i]) && $seat = $this->re("#\d+\w#", $seats[$i])) {
                    $info[$flight]['seats'][] = $seat;
                }

                if (isset($class[$i]) && $cabin = $this->re("#\(\s*(\w{2,})\s*\)#", $class[$i])) {
                    $info[$flight]['cabin'] = $cabin;
                }

                if (isset($class[$i]) && $bookingclass = $this->re("#\(\s*(\w)\s*\)#", $class[$i])) {
                    $info[$flight]['bookingclass'] = $bookingclass;
                }
            }
        }

        $f = $email->add()->flight();

        $f->general()
            ->confirmation($this->nextText("Your booking reference is"))
            ->travellers(array_filter([$this->nextText("Passengers")]));

        $total = $this->http->FindSingleNode("//text()[normalize-space(.)='Total']/ancestor::tr[1]/following-sibling::tr[1]", null, true, "#[A-Z]{3}\s*([\d\.,]+)#");

        if (!empty($total)) {
            $f->price()
                ->total($this->amount($total))
                ->currency($this->http->FindSingleNode("//text()[normalize-space(.)='Total']/ancestor::tr[1]/following-sibling::tr[1]", null, true, "#([A-Z]{3})\s*[\d\.,]+#"));
        }

        $xpath = "//text()[normalize-space(.)='Departing']/ancestor::tr[contains(., 'Arriving')][1]";
        $nodes = $this->http->XPath->query($xpath);

        if ($nodes->length == 0) {
            $this->http->Log("segments root not found: $xpath", LOG_LEVEL_NORMAL);
        }

        foreach ($nodes as $root) {
            $seg = $f->addSegment();
            $i = 1;

            $seg->airline()
                ->number($this->http->FindSingleNode("./following::text()[starts-with(., 'Flight Number')][1]", $root, true, "#\d+$#"))
                ->name($this->http->FindSingleNode("./following::text()[starts-with(., 'Operated by')][1]", $root, true, "#\(\s*(\w{2})\s*\)$#"));

            // DepCode
            $seg->departure()
                ->code($this->http->FindSingleNode("./preceding-sibling::tr[1]/descendant::text()[normalize-space(.)][1]", $root, true, "#\(\s*([A-Z]{3})\s*\)#"))
                ->date(strtotime($this->normalizeDate($this->http->FindSingleNode(".//text()[normalize-space(.)='Departing']/ancestor::tr[1]", $root, true, "#Departing\s*(.+)#"))));

            $depText = $this->http->FindSingleNode("./following::tr[1]/td[1]", $root);
            $depTerminal = $this->re("/Terminal\s(\S)\s?\,?/", $depText);

            if (empty($depTerminal)) {
                $depTerminal = $this->re("/(\w+)\sTerminal/", $depText);
            }

            if (!empty($depTerminal)) {
                $seg->departure()
                    ->terminal($depTerminal);
            }

            // ArrName
            // ArrDate
            $arrDate = strtotime($this->normalizeDate($this->http->FindSingleNode(".//text()[normalize-space(.)='Arriving']/ancestor::tr[1]", $root, true, "#Arriving\s*(.+)#")));

            if (empty($arrDate)) {
                $arrTimeOnly = $this->http->FindSingleNode(".//text()[normalize-space(.)='Arriving']/ancestor::tr[1]", $root, true, "#Arriving\s*(.+)#");
                $arrDateOnly = $this->http->FindSingleNode(".//text()[normalize-space(.)='Departing']/ancestor::tr[1]", $root, true, "#Departing\s*(.+)\s+at#");
                $arrDate = strtotime($this->normalizeDate($arrDateOnly . ' ' . $arrTimeOnly));
            }

            $seg->arrival()
                ->code($this->http->FindSingleNode("./preceding-sibling::tr[1]/descendant::text()[normalize-space(.)][2]", $root, true, "#\(\s*([A-Z]{3})\s*\)#"))
                ->date($arrDate);

            $arrText = $this->http->FindSingleNode("./following::tr[1]/td[3]", $root);
            $arrTerminal = $this->re("/Terminal\s(\S)\s?\,?/", $arrText);

            if (empty($arrTerminal)) {
                $arrTerminal = $this->re("/(\w+)\sTerminal/", $arrText);
            }

            if (!empty($arrTerminal)) {
                $seg->arrival()
                    ->terminal($arrTerminal);
            }

            if (isset($info["{$seg->getDepCode()} - {$seg->getArrCode()}"]) && isset($info["{$seg->getDepCode()} - {$seg->getArrCode()}"]['cabin'])) {
                $seg->extra()
                    ->cabin($info["{$seg->getDepCode()} - {$seg->getArrCode()}"]['cabin']);
            }

            if (empty($seg->getCabin()) && count($info) === 0) {
                $seg->extra()->cabin($this->http->FindSingleNode("//text()[normalize-space(.)='eTicket number']/following::table[1]/descendant::tr/descendant::text()[starts-with(normalize-space(), '(')][{$i}]", null, true, "/\((.+)\)/"));
            }

            // BookingClass
            if (isset($info["{$seg->getDepCode()} - {$seg->getArrCode()}"]) && isset($info["{$seg->getDepCode()} - {$seg->getArrCode()}"]['bookingclass'])) {
                $seg->extra()
                    ->bookingCode($info["{$seg->getDepCode()} - {$seg->getArrCode()}"]['bookingclass']);
            }

            // PendingUpgradeTo
            // Seats
            if (isset($info["{$seg->getDepCode()} - {$seg->getArrCode()}"]) && isset($info["{$seg->getDepCode()} - {$seg->getArrCode()}"]['seats'])) {
                $seg->extra()
                    ->seats(implode(",", $info["{$seg->getDepCode()} - {$seg->getArrCode()}"]['seats']));
            }

            $i++;
        }

        return true;
    }

    public function detectEmailFromProvider($from)
    {
        return strpos($from, $this->reFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
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

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->date = strtotime($parser->getHeader('date'));

        if ($date = $this->re("#(\w+,\s+\d+\s+\w+\s+\d{4}\s+\d+:\d+:\d+\s+\+\d{4})#", $this->http->Response["body"])) {
            $this->date = strtotime($date);
        }

        $this->http->FilterHTML = false;
        //$itineraries = array();
        $this->http->setBody(str_replace(" ", " ", $this->http->Response["body"])); // bad fr char " :"

        foreach ($this->reBody2 as $lang=> $re) {
            if (strpos($this->http->Response["body"], $re) !== false) {
                $this->lang = $lang;

                break;
            }
        }

        $this->parseHtml($email);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
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

    private function normalizeDate($str)
    {
        $year = date("Y", $this->date);
        $in = [
            "#^[^\d\s]+\s+(\d+)([^\d\s]+)\s+at\s+(\d+:\d+)$#",
            "#^[^\d\s]+(\d+)([A-Z]+)at\s*(\d+:\d+)$#",
        ];
        $out = [
            "$1 $2 $year, $3",
            "$1 $2 $year, $3",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#[^\d\s-\./:]#", $str)) {
            $str = $this->dateStringToEnglish($str);
        }

        if (strtotime($str) < $this->date) {
            $str = preg_replace("#\d{4}#", $year + 1, $str);
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
