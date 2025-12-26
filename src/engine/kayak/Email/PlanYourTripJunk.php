<?php

namespace AwardWallet\Engine\kayak\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class PlanYourTripJunk extends \TAccountChecker
{
    public $mailFiles = "kayak/it-313880780.eml";

    public $detectFrom = "noreply-trips@message.kayak.com";
    public $detectSubject = [
        // en
        'Plan your trip to ',
    ];

    public $lang = "en";
    public static $dictionary = [
        "en" => [
            "Thanks for using KAYAK Trips to plan your trip to"  => [
                "Thanks for using KAYAK Trips to plan your trip to ",
                "Thanks for using momondo Trips to plan your trip to ",
            ],
            "We’ll send you real-time alerts letting you know when prices either rise, drop or stay the same" => [
                "We’ll send you real-time alerts letting you know when prices either rise, drop or stay the same",
            ],
            "Your saved details" => ["Your saved stay details", "Your saved flight details"],
            "Finish booking"     => 'Finish booking',
        ],
    ];

    public function detectEmailFromProvider($from)
    {
        return strpos($from, $this->detectFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $detectedSubject = false;

        foreach ($this->detectSubject as $dSubject) {
            if (stripos($parser->getSubject(), $dSubject) !== false) {
                $detectedSubject = true;

                break;
            }
        }

        if ($detectedSubject === false) {
            return false;
        }

        if ($this->http->XPath->query("//a[contains(@href, '.kayak.')]")->length < 3) {
            return false;
        }

        foreach (self::$dictionary as $dict) {
            if (!empty($dict['Thanks for using KAYAK Trips to plan your trip to']) && !empty($dict['We’ll send you real-time alerts letting you know when prices either rise, drop or stay the same'])
                && $this->http->XPath->query("//node()[{$this->contains($dict['Thanks for using KAYAK Trips to plan your trip to'])}]")->length > 0
                && $this->http->XPath->query("//node()[{$this->contains($dict['We’ll send you real-time alerts letting you know when prices either rise, drop or stay the same'])}]")->length > 0
                && !empty($dict['Finish booking'])
                && $this->http->XPath->query("//a[{$this->eq($dict['Finish booking'])}][contains(@href, '.kayak.')]")->length > 0
            ) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        foreach (self::$dictionary as $dict) {
            if (!empty($dict['Finish booking'])
                && $this->http->XPath->query("//a[{$this->eq($dict['Finish booking'])}][contains(@href, '.kayak.')]")->length > 0
            ) {
                $email->setIsJunk(true);
            }
        }

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

    private function parseHtml(Email $email)
    {
        $f = $email->add()->flight();

        // General
        $conf = $this->http->FindSingleNode("//td[descendant::text()[normalize-space()][1][{$this->eq($this->t('Flight confirmation'))}] and count(descendant::text()[normalize-space()]) = 2]/descendant::text()[normalize-space()][2]",
            null, true, "/^\s*([A-Z\d, \-]{5,})\s*$/");

        if (empty($conf) && $this->http->FindSingleNode("//text()[{$this->eq($this->t('Flight confirmation'))}]/following::text()[normalize-space()][1][{$this->eq($this->t('Add manually'))}]")) {
            $f->general()
                ->noConfirmation();
        } else {
            $conf = array_filter(preg_split("/\s*,\s*/", $conf));

            foreach ($conf as $c) {
                $f->general()
                    ->confirmation($c);
            }
        }

        // Segments

        $s = $f->addSegment();

        // Airline
        $flight = $this->http->FindSingleNode("//td[descendant::text()[normalize-space()][1][{$this->eq($this->t('Flight'))}] and count(descendant::text()[normalize-space()]) = 2]/descendant::text()[normalize-space()][2]");

        if (preg_match("/^\s*(?<al>[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(?<fn>\d{1,5})\s*$/", $flight, $m)) {
            $s->airline()
                ->name($m['al'])
                ->number($m['fn'])
            ;
        }

        $operator = $this->http->FindSingleNode("//td[descendant::text()[normalize-space()][1][{$this->eq($this->t('Operated by'))}] and count(descendant::text()[normalize-space()]) = 2]/descendant::text()[normalize-space()][2]");

        if (preg_match("/.+ ([A-Z\d][A-Z]|[A-Z][A-Z\d])[ ]?(\d{1,5})\s*$/", $operator, $m)) {
            $s->airline()
                ->carrierName($m[1])
                ->carrierNumber($m[2]);
        } elseif (!empty($operator)) {
            $s->airline()->operator($operator);
        }

        // Route
        $airports = implode("\n",
            $this->http->FindNodes("//text()[{$this->eq($this->t('Departure time'))}]/preceding::tr[not(.//tr)][2]/ancestor-or-self::*[count(.//text()[normalize-space()]) = 4 and count(.//img) = 1][1]//text()[normalize-space()]"));

        if (preg_match("/^\s*(?<depCode>[A-Z]{3})\n(?<arrCode>[A-Z]{3})\n(?<depName>.+)\n(?<arrName>.+)$/", $airports, $m)) {
            $s->departure()
                ->code($m['depCode'])
                ->name($m['depName']);

            $s->arrival()
                ->code($m['arrCode'])
                ->name($m['arrName']);
        }

        $date = implode("\n", $this->http->FindNodes("//text()[{$this->eq($this->t('Departure time'))}]/ancestor::*[not({$this->contains($this->t('Arrival time'))})][last()]//text()[normalize-space()][not(ancestor::*[contains(@style, 'line-through')])]"));

        if (preg_match("/^\s*{$this->preg_implode($this->t('Departure time'))}\s*(?:\n.+){0,2}\n(.*\d{4}.*\n\s*\d{1,2}:\d{2}.*)\s*$/ui", $date, $m)) {
            // Departure time
            // Thu Mar 16 2023
            // 9:55 am CDT
            $s->departure()
                ->date($this->normalizeDate($m[1]));
        }

        $date = implode("\n", $this->http->FindNodes("//text()[{$this->eq($this->t('Arrival time'))}]/ancestor::*[not({$this->contains($this->t('Departure time'))})][last()]//text()[normalize-space()][not(ancestor::*[contains(@style, 'line-through')])]"));

        if (preg_match("/^\s*{$this->preg_implode($this->t('Arrival time'))}\s*(?:\n.+){0,2}\n(.*\d{4}.*\n\s*\d{1,2}:\d{2}.*)$/ui", $date, $m)) {
            $s->arrival()
                ->date($this->normalizeDate($m[1]));
        }

        return $email;
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
        // $this->logger->debug('normalizeDate $str = ' . print_r($str, true));
        $in = [
            // Thu Mar 16 2023  9:55 am
            "/^\s*[[:alpha:]]+\s+([[:alpha:]]+)\s+(\d+)\s+(\d{4})\s+(\d+:\d+(?:\s*[ap][m])?)(\s+[A-Z]{3,4})?\s*$/ui",
            // mer. 15 mars 2023 14:25
            "/^\s*[[:alpha:]]+[.,]?\s+(\d+)\s+([[:alpha:]]+)\.\s+(\d{4})\s+(\d+:\d+(?:\s*[ap]\.?[m]\.?)?)(\s+[A-Z]{3,4})?\s*$/ui",
        ];
        $out = [
            "$2 $1 $3, $4",
            "$1 $2 $3, $4",
        ];
        $str = preg_replace($in, $out, $str);
        $str = str_replace('.', '', $str);

        // $this->logger->debug('normalizeDate $str 2 = ' . print_r($str, true));

        if (preg_match("#^\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        if (preg_match("/^\d{1,2}\s+[[:alpha:]]+\s+\d{4}\s*,\s*\d{1,2}:\d{2}(?:\s*[ap]m)?$/i", $str)) {
            return strtotime($str);
        }

        return null;
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
        $sym = [
            '€'=> 'EUR',
            '$'=> 'USD',
            '£'=> 'GBP',
            '₹'=> 'INR',
        ];

        if ($code = $this->re("#(?:^|\s)([A-Z]{3})(?:$|\s)#", $s)) {
            return $code;
        }
        $s = $this->re("#([^\d\,\.]+)#", $s);

        foreach ($sym as $f=> $r) {
            if (strpos($s, $f) !== false) {
                return $r;
            }
        }

        return null;
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
            return 'false()';
        }

        return '(' . implode(" or ", array_map(function ($s) { return "normalize-space(.)=\"{$s}\""; }, $field)) . ')';
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(" or ", array_map(function ($s) { return "starts-with(normalize-space(.), \"{$s}\")"; }, $field)) . ')';
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(" or ", array_map(function ($s) { return "contains(normalize-space(.), \"{$s}\")"; }, $field)) . ')';
    }

    private function preg_implode($field)
    {
        if (!is_array($field)) {
            $field = [$field];
        }

        return '(?:' . implode("|", array_map(function ($v) {return preg_quote($v, '#'); }, $field)) . ')';
    }
}
