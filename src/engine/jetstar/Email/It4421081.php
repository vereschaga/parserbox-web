<?php

namespace AwardWallet\Engine\jetstar\Email;

use AwardWallet\Schema\Parser\Email\Email;

class It4421081 extends \TAccountChecker
{
    use \DateTimeTools;
    use \PriceTools;
    public $mailFiles = "jetstar/it-4335545.eml, jetstar/it-4421081.eml, jetstar/it-60848535.eml";

    public $reFrom = "no-reply@jetstar.com";

    public $reSubject = [
        "en"=> "Changes to your Jetstar Itinerary",
    ];

    public $reBody = 'Jetstar';

    public $reBody2 = [
        "en" => ["Check out your new flight and seat",
            "affected by this change a Jetstar credit voucher",
            "There have been some changes to your upcoming flight on booking", ],
    ];

    public static $dictionary = [
        "en" => [
            'Booking reference:' => ['Booking reference:', ' on booking '],
        ],
    ];

    public $lang = "en";
    public $subject;

    public function parseHtml(Email $email)
    {
        $f = $email->add()->flight();

        //General
        $confirmation = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Booking reference:'))}]", null, true, "#\s+([A-Z\d]{5,6})\b#");

        if (!empty($confirmation)) {
            $f->general()
                ->confirmation($confirmation);
        }

        $traveller = $this->http->FindNodes("//text()[normalize-space(.)='Passengers:']/ancestor::strong/following-sibling::span/text()[normalize-space(.)]");

        if (!$traveller) {
            $traveller = $this->http->FindNodes("//text()[normalize-space(.)='Passengers:']/ancestor::strong/following-sibling::text()[normalize-space(.)]");
        }

        if (!$traveller) {
            $traveller[] = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Dear')]", null, true, "/Dear\s*(.+)\,/");
        }

        $f->general()
            ->travellers($traveller);

        $xpath = "//text()[normalize-space(.)='Depart']/ancestor::tr[1]/following-sibling::tr[1]/td[2]/descendant::text()[normalize-space(.)]";
        $nodes = $this->http->XPath->query($xpath);

        if ($nodes->length == 0) {
            $this->logger->alert("segments root not found: $xpath");
        }

        foreach ($nodes as $root) {
            $s = $f->addSegment();

            $flights = array_flip($this->http->FindNodes("./ancestor::td[1]/descendant::text()[normalize-space(.)]", $root));
            $i = $flights[$this->http->FindSingleNode(".", $root)] + 1;
            $root = $this->http->XPath->query("./ancestor::tr[1]", $root)->item(0);

            $date = strtotime($this->normalizeDate($this->http->FindSingleNode("./td[1]/descendant::text()[normalize-space(.)][{$i}]", $root)));

            // Airline
            $s->airline()
                ->number($this->http->FindSingleNode("./td[2]/descendant::text()[normalize-space(.)][{$i}]", $root, true, "#^\w{2}\s*(\d+)$#"))
                ->name($this->http->FindSingleNode("./td[2]/descendant::text()[normalize-space(.)][{$i}]", $root, true, "#^(\w{2})\s*\d+$#"));

            // Departure
            $s->departure()
                ->noCode()
                ->name($this->http->FindSingleNode("./td[3]/descendant::text()[normalize-space(.)][{$i}]", $root))
                ->date(strtotime($this->http->FindSingleNode("./td[4]/descendant::text()[normalize-space(.)][{$i}]", $root), $date));

            //Arrival
            $s->arrival()
                ->noCode()
                ->name($this->http->FindSingleNode("./td[5]/descendant::text()[normalize-space(.)][{$i}]", $root));

            $arrDate = strtotime($this->http->FindSingleNode("./td[6]/descendant::text()[normalize-space(.)][{$i}]", $root), $date);

            if ($arrDate < $s->getDepDate()) {
                $arrDate = strtotime("+1 day", $arrDate);
            }

            $s->arrival()
                ->date($arrDate);

            if (!empty($this->re("/(Changes to your Jetstar Itinerary)/u", $this->subject))) {
                $f->general()
                    ->noConfirmation()
                    ->status('changed');
            }
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

        foreach ($this->reBody2 as $reBodys) {
            foreach ($reBodys as $re) {
                if (strpos($body, $re) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->subject = $parser->getSubject();
        $this->date = strtotime($parser->getHeader('date'));

        $this->http->FilterHTML = false;
        $this->http->setBody(str_replace(" ", " ", $this->http->Response["body"])); // bad fr char " :"

        foreach ($this->reBody2 as $lang => $bodys) {
            foreach ($bodys as $re) {
                if (strpos($this->http->Response["body"], $re) !== false) {
                    $this->lang = $lang;

                    break;
                }
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
            "#^(\d+)([^\d\s]+)(\d{2})$#",
        ];
        $out = [
            "$1 $2 20$3",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#[^\d\W]#", $str)) {
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

    private function contains($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'contains(normalize-space(' . $node . "),'" . $s . "')";
        }, $field)) . ')';
    }
}
