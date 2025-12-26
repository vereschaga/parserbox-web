<?php

namespace AwardWallet\Engine\jetstar\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class ManageBookingUrl extends \TAccountChecker
{
    public $mailFiles = "jetstar/it-15120911.eml, jetstar/it-15574959.eml";

    private $eFrom = '@email.jetstar.com';
    private $eSubject = [
        'en' => 'Important baggage information for',
    ];

    private $langDetectors = [
        'en' => ['Manage booking', 'costs more to add'],
    ];
    private $lang = 'en';
    private static $dict = [
        "en" => [],
    ];

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        foreach ($this->langDetectors as $lang => $value) {
            if ($this->http->XPath->query("//a[" . $this->eq($value[0]) . " and contains(@href, 'email.jetstar.com/pub/cc')]")->length > 0
                && $this->http->XPath->query("//*[" . $this->contains($value[1]) . "]")->length > 0) {
                $this->lang = $lang;

                break;
            }
        }

        $url = $this->http->FindSingleNode("//a[(" . $this->eq($this->t("Manage booking")) . ") and contains(@href, 'email.jetstar.com/pub/cc')]/@href");

        if (!empty($url)) {
            $res = $this->http->GetURL($url);
            $this->flight($email);
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        foreach ($this->langDetectors as $value) {
            if ($this->http->XPath->query("//a[" . $this->eq($value[0]) . " and contains(@href, 'email.jetstar.com/pub/cc')]")->length > 0
                && $this->http->XPath->query("//*[" . $this->contains($value[1]) . "]")->length > 0) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (stripos($headers['from'], $this->eFrom) === false) {
            return false;
        }

        foreach ($this->eSubject as $subject) {
            if (stripos($headers["subject"], $subject) !== false) {
                return true;
            }
        }
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->eFrom) !== false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    private function flight(Email $email)
    {
        $f = $email->add()->flight();

        $f->general()->confirmation($this->http->FindSingleNode("(//text()[" . $this->eq($this->t('Reservation')) . "]/following::text()[normalize-space()][1])[1]", null, true, "#^\s*([A-Z\d]{5,7})\s*$#"), $this->t('Reservation'));

        $f->general()->travellers(array_unique($this->http->FindNodes("//text()[" . $this->eq($this->t("Seats")) . "]/ancestor::thead[1][" . $this->contains($this->t("Meals")) . "]/following-sibling::tbody/tr/td[1]/a[1]")), true);

        $xpath = "//text()[" . $this->eq($this->t("Seats")) . "]/ancestor::thead[1][" . $this->contains($this->t("Meals")) . "]";
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $route = $this->http->FindSingleNode("./tr[1]/th[1]", $root);
            $seat['seats'] = array_filter($this->http->FindNodes("./following-sibling::tbody/tr/td[3]", $root, "#^\s*(\d{1,3}[A-Z])\s*$#"));

            if (!empty($seat['seats']) && preg_match("#(.+) to (.+)#", $route, $m)) {
                $seat['dep'] = $m[1];
                $seat['arr'] = $m[2];
                $seats[] = $seat;
            }
        }

        $xpath = "//text()[" . $this->eq($this->t("Departing")) . "]/ancestor::div[2][" . $this->contains($this->t("Arriving")) . "]";
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $s = $f->addSegment();

            $date = $this->http->FindSingleNode("(.//text()[" . $this->eq($this->t("Departing")) . "])[1]/ancestor::div[1]/preceding-sibling::div[1]/descendant::text()[normalize-space()][1]", $root);

            if (empty($date)) {
                break;
            }
            $s->airline()
                ->name($this->http->FindSingleNode("((.//text()[" . $this->eq($this->t("Departing")) . "])[1]/ancestor::div[1]/preceding-sibling::div[1]/descendant::text()[normalize-space()])[last()]", $root, true, "#^\s*([A-Z\d]{2})[ ]*\d{1,5}\s*$#"))
                ->number($this->http->FindSingleNode("((.//text()[" . $this->eq($this->t("Departing")) . "])[1]/ancestor::div[1]/preceding-sibling::div[1]/descendant::text()[normalize-space()])[last()]", $root, true, "#^\s*[A-Z\d]{2}[ ]*(\d{1,5})\s*$#"));

            $node = implode("\n", $this->http->FindNodes("(.//text()[" . $this->eq($this->t("Departing")) . "])[1]/ancestor::li[1]//text()[normalize-space()]", $root));

            if (preg_match("#" . $this->t("Departing") . "(.+)\n\s*(\d+:\d+)(?:\s*[AP]M)?\s*\n\s*(.*Terminal.*)?$#s", $node, $m)) {
                $s->departure()
                    ->noCode()
                    ->name(trim($m[1]))
                    ->date($this->normalizeDate($date . ' ' . $m[2]))
                    ->terminal(!empty($m[3]) ? trim(str_ireplace('Terminal', '', $m[3])) : null, true, true);
            }

            $node = implode("\n", $this->http->FindNodes("(.//text()[" . $this->contains($this->t("Arriving")) . "])[1]/ancestor::li[1]//text()[normalize-space()]", $root));

            if (preg_match("#" . $this->t("Arriving") . "\s+(.+)\n\s*(\d+:\d+)(?:\s*[AP]M)?\s*(?:\(\s*\+(\d)\s*day.*?\)\s*)?\n\s*(.*?Terminal.*)?$#s", $node, $m)) {
                $s->arrival()
                    ->noCode()
                    ->name(trim($m[1]))
                    ->date($this->normalizeDate($date . ' ' . $m[2]))
                    ->terminal(!empty($m[4]) ? trim(str_ireplace('Terminal', '', $m[4])) : null, true, true);

                if (!empty($m[3])) {
                    $s->arrival()->date(strtotime("+" . $m[3] . "day", $s->getArrDate()));
                }
            }
            $dopXpath = "//text()[" . $this->eq($this->t("Departing")) . "])[1]/ancestor::div[1]/following-sibling::div[1]/descendant::div[1]";
            $s->extra()
                ->duration($this->http->FindSingleNode("(." . $dopXpath . "//li[not(.//li) and " . $this->starts($this->t("Travel time")) . "]", $root, true, "#" . $this->t("Travel time") . "\s*(.+)#"))
                ->aircraft($this->http->FindSingleNode("(." . $dopXpath . "//li[not(.//li) and " . $this->starts($this->t("Aircraft")) . "]", $root, true, "#" . $this->t("Aircraft") . "\s*(.+)#"))
                ->cabin($this->http->FindSingleNode("(." . $dopXpath . "//li[not(.//li) and " . $this->starts($this->t("Fare Type")) . "]", $root, true, "#" . $this->t("Fare Type") . "\s*(.+)#"));

            if (!empty($s->getArrName()) && !empty($s->getDepName()) && !empty($seats)) {
                foreach ($seats as $value) {
                    if ($value['dep'] == $s->getDepName() && $value['arr'] == $s->getArrName()) {
                        $s->extra()->seats($value['seats']);
                    }
                }
            }
        }

        return $email;
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function normalizeDate($instr)
    {
        $instr = preg_replace("#\s+#", ' ', $instr);
        $in = [
            "#^\s*[^\d\s]+\s+(\d{1,2})\s+([^\d\s]+)\s+(\d{4})\s+(\d+:\d+)\s*$#i", // Friday 01 Jun 2018 09:50 AM
        ];
        $out = [
            "$1 $2 $3, $4",
        ];
        $str = preg_replace($in, $out, $instr);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return strtotime($str);
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'normalize-space(.)="' . $s . '"'; }, $field)) . ')';
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'starts-with(normalize-space(.),"' . $s . '")'; }, $field)) . ')';
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'contains(normalize-space(.),"' . $s . '")'; }, $field)) . ')';
    }
}
