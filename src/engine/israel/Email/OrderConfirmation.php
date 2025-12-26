<?php

namespace AwardWallet\Engine\israel\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class OrderConfirmation extends \TAccountChecker
{
    public $mailFiles = "israel/it-29595119.eml, israel/it-29595462.eml";

    public static $dictionary = [
        "he" => [],
    ];

    private $detectFrom = [
        "noreply@elal.co.il",
    ];
    private $detectSubject = [
        "he" => "אלעל כירטוס עובדים",
    ];
    private $detectCompany = [
        "תודה על שבחרת אלעל",
    ];
    private $detectBody = [
        "he" => "אישור להזמנה",
    ];

    private $lang = "he";

    public function flight(Email $email)
    {
        $f = $email->add()->flight();

        // General
        $confs = array_filter($this->http->FindNodes("//text()[" . $this->eq($this->t("מספר הזמנה")) . "]/following::text()[string-length(normalize-space())>1][1]", null, '#^\s*([A-Z\d]+)\s*$#'));
        $confs = array_unique(array_merge($confs, array_filter($this->http->FindNodes("//text()[" . $this->starts($this->t("מספר הזמנה")) . "]", null, '#:\s*([A-Z\d]+)\s*$#'))));

        foreach ($confs as $conf) {
            $f->general()->confirmation($conf);
        }

        $traveller = $this->http->FindSingleNode("//text()[" . $this->eq($this->t("שם הנוסע:")) . "]/following::text()[normalize-space()][1]", null, '#.+/.+#');

        if (!empty($traveller)) {
            $f->general()->traveller($traveller, true);
        }

        // Segments
        $xpath = "//text()[" . $this->eq($this->t("הטיסות שלך")) . "]/ancestor::tr[1]/following::tr[" . $this->starts($this->t("טיסה")) . " and following::tr[normalize-space()][1][" . $this->starts($this->t("המראה")) . "]]";
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $s = $f->addSegment();
            $date = null;
            // Airline
            $node = $this->http->FindSingleNode(".", $root);

            if (preg_match("#טיסה[ ]*\d+:[ ]*(?<dep>.+)\s*-\s*(?<arr>.+?)[ ]+(?<date>\d+ .+)#", $node, $m)) {
                $date = $this->normalizeDate($m['date']);
                $s->departure()
                    ->noCode()
                    ->name($m['dep'])
                ;
                $s->arrival()
                    ->noCode()
                    ->name($m['arr'])
                ;
            }

            $node = $this->http->FindSingleNode("(./following::tr[normalize-space()][position()<5][" . $this->starts($this->t("מספר טיסה")) . "])[1]", $root);

            if (preg_match("#:\s*(?<al>[A-Z\d][A-Z]|[A-Z][A-Z\d])\s*(?<fn>\d{1,5})\b#", $node, $m)) {
                $s->airline()
                    ->name($m['al'])
                    ->number($m['fn']);
            }

            if (!empty($date)) {
                $time = $this->http->FindSingleNode("./following::tr[normalize-space()][1][" . $this->starts($this->t("המראה")) . "]", $root, true, "#:\s*(.+)#");

                if (!empty($time)) {
                    $s->departure()
                        ->date(strtotime($time, $date))
                    ;
                }
                $time = $this->http->FindSingleNode("(./following::tr[normalize-space()][2][" . $this->starts($this->t("הגעה")) . "])[1]", $root, true, "#:\s*(.+)#");

                if (!empty($time)) {
                    $s->arrival()
                        ->date(strtotime($time, $date))
                    ;
                }
            }
        }

        return $email;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        //		$body = html_entity_decode($this->http->Response["body"]);
        //		foreach($this->detectBody as $lang => $dBody){
        //			if (stripos($body, $dBody) !== false) {
        //				$this->lang = $lang;
        //				break;
        //			}
        //		}

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        $this->flight($email);

        return $email;
    }

    public function detectEmailFromProvider($from)
    {
        foreach ($this->detectFrom as $dFrom) {
            if (strpos($from, $dFrom) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        $finded = false;

        foreach ($this->detectFrom as $dFrom) {
            if (strpos($headers["from"], $dFrom) !== false) {
                $finded = true;

                break;
            }
        }

        if (!$finded) {
            return false;
        }

        foreach ($this->detectSubject as $dSubject) {
            if (stripos($headers["subject"], $dSubject) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();
        $finded = false;

        foreach ($this->detectCompany as $dCompany) {
            if (strpos($body, $dCompany) !== false || $this->http->XPath->query("//*[contains(normalize-space(), '" . $dCompany . "')]")->length > 0) {
                $finded = true;

                break;
            }
        }

        if (!$finded) {
            return false;
        }

        foreach ($this->detectBody as $detectBody) {
            if (strpos($body, $detectBody) !== false) {
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

    private function t($word)
    {
        // $this->http->log($word);
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function normalizeDate($str)
    {
        //		$this->http->log($str);
        $in = [
            //			"#^\s*(\d{1,2})/(\d{1,2})/(\d{4})\s*(\d+:\d+)\s*$#",//29/11/2018 05:40
        ];
        $out = [
            //			"$1.$2.$3, $4",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#u", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return strtotime($str);
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
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
}
