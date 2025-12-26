<?php

namespace AwardWallet\Engine\hongkongairlines\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class CheckYourOrder extends \TAccountChecker
{
    public $mailFiles = "hongkongairlines/it-22067196.eml, hongkongairlines/it-22126395.eml";

    private $detectFrom = ["@hkairlines.com", "@vip.hainanairlines.com"];
    private $detectSubject = [
        'Please check your order',
    ];

    private $detectCompany = [
        'Hong Kong Airlines',
    ];

    private $detectBody = [
        'en' => [
            'Your Confirmed Flight',
        ],
    ];

    private $lang = 'en';
    private static $dictionary = [
        'en' => [],
    ];

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $body = $this->http->Response['body'];

        foreach ($this->detectBody as $lang => $detectBody) {
            foreach ($detectBody as $detect) {
                if (stripos($body, $detect) !== false) {
                    $this->lang = $lang;

                    break;
                }
            }
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        $this->flight($email);

        return $email;
    }

    public function detectEmailFromProvider($from)
    {
        foreach ($this->detectFrom as $detectFrom) {
            if (stripos($from, $detectFrom) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $body = $this->http->Response['body'];

        $finded = false;

        foreach ($this->detectCompany as $dCompany) {
            if (stripos($body, $dCompany) !== false) {
                $finded = true;
            }
        }

        if ($finded == false) {
            return false;
        }

        foreach ($this->detectBody as $detectBody) {
            foreach ($detectBody as $detect) {
                if (stripos($body, $detect) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        $finded = false;

        foreach ($this->detectFrom as $detectFrom) {
            if (stripos($headers['from'], $detectFrom) !== false) {
                $finded = true;
            }
        }

        if ($finded == false) {
            return false;
        }

        foreach ($this->detectSubject as $detectSubject) {
            if (stripos($headers['subject'], $detectSubject) !== false) {
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

    protected function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function flight(Email $email)
    {
        $f = $email->add()->flight();

        // General
        $f->general()
            ->confirmation($this->http->FindSingleNode("//text()[" . $this->eq($this->t("Booking Reference No")) . "]/following::text()[normalize-space(.)][1]", null, true, "#^\s*([A-Z\d]{5,})\s*$#"), "Booking Reference No", true)
            ->confirmation($this->http->FindSingleNode("//text()[" . $this->eq($this->t("Order number:")) . "]/following::text()[normalize-space(.)][1]", null, true, "#^\s*([A-Z\d]{5,})\s*$#"), "Order Number")
            ->travellers($this->http->FindNodes("//text()[" . $this->eq($this->t("Passenger Names")) . "]/ancestor::tr[" . $this->contains($this->t("e-ticket numbers")) . "]/following-sibling::tr/td[1]"));

        // Program
        $account = $this->http->FindSingleNode("//text()[" . $this->eq($this->t("Points Earned")) . "]/ancestor::tr[" . $this->contains($this->t("Membership Number")) . "]/following-sibling::tr/td[2]", null);

        if (!empty($account)) {
            $f->program()
                ->account($account, false);
        }
        $earned = $this->http->FindSingleNode("//text()[" . $this->eq($this->t("Points Earned")) . "]/ancestor::tr[" . $this->contains($this->t("Membership Number")) . "]/following-sibling::tr/td[3]");

        if (!empty($earned)) {
            $f->program()
                ->earnedAwards($earned . ' Points');
        }

        // Issued
        $tickets = array_unique(array_filter($this->http->FindNodes("//text()[" . $this->eq($this->t("Passenger Names")) . "]/ancestor::tr[" . $this->contains($this->t("e-ticket numbers")) . "]/following-sibling::tr/td[2]", null, "#^\s*([\d\-]{6,})\s*$#")));

        if (!empty($tickets)) {
            $f->issued()
                ->tickets($tickets, false);
        }

        // Price
        $total = implode(" ", $this->http->FindNodes('//text()[' . $this->contains($this->t("Total for all passengers")) . ']/ancestor::*[1]//text()'));

        if (!empty($total) && (preg_match("#Total for all passengers\s+(?<curr>[A-Z]{3})\s*(?<total>\d[\d,. ]*)(\s+|$)#", $total, $m) || preg_match("#Total for all passengers\s+(?<total>\d[\d,. ]*)\s*(?<curr>[A-Z]{3})(?:\s+|$)#", $total, $m))) {
            $m['total'] = str_replace([',', ' '], '', $m['total']);

            if (is_numeric($m['total'])) {
                $f->price()
                    ->total((float) $m['total'])
                    ->currency($m['curr']);
            }
        }

        $xpath = "//text()[" . $this->starts('Departure:') . "]/ancestor::table[1][" . $this->starts('Flight:') . "]";
        //		$this->logger->debug($xpath);
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $s = $f->addSegment();

            $dateFormats = implode("|", ["\w+,\s*\w+\s+\d{1,2}\s*,\s*\d{4}\s*$"]);
            $date = $this->http->FindSingleNode("./preceding::text()[normalize-space()][1]", $root, null, "#(" . $dateFormats . ")#u");

            $nextTable = './following::table[1]';

            // Airline
            $node = $this->http->FindSingleNode(".//tr[starts-with(normalize-space(),'Flight:')][1]", $root);

            if (preg_match("#\s+([A-Z]\d|\d[A-Z]|[A-Z]{2})(\d{1,5})\s*$#", $node, $m)) {
                $s->airline()
                    ->name($m[1])
                    ->number($m[2])
                ;
            }

            $reRoute = '#:\s*(?<time>\d+:\d+)\s+(?<name1>.+?)\s*\((?<code>[A-Z]{3})\)(?<name2>.*),(?<term>.*)$#';

            // Departure
            $node = implode("\n", $this->http->FindNodes(".//tr[starts-with(normalize-space(),'Departure:')][1]//text()[normalize-space()]", $root));

            if ($date && preg_match($reRoute, $node, $m)) {
                $s->departure()
                    ->code($m['code'])
                    ->name($m['name1'] . $m['name2'])
                    ->date($this->normalizeDate($date . ' ' . $m['time']))
                    ->terminal(trim($m['term'], '- ') ?? null, true, true)
                ;
            }

            // Arrival
            $node = implode("\n", $this->http->FindNodes(".//tr[starts-with(normalize-space(),'Arrival:')][1]//text()[normalize-space()]", $root));

            if ($date && preg_match($reRoute, $node, $m)) {
                $s->arrival()
                    ->code($m['code'])
                    ->name($m['name1'] . $m['name2'])
                    ->date($this->normalizeDate($date . ' ' . $m['time']))
                    ->terminal(trim($m['term'], '- ') ?? null, true, true)
                ;
            }

            // Extra
            $s->extra()
                ->cabin($this->http->FindSingleNode(".//tr[starts-with(normalize-space(),'Class:')][1]", $root, true, "#:\s*(.+?)(?:/([A-Z]{1,2}))?\s*$#"))
                ->bookingCode($this->http->FindSingleNode(".//tr[starts-with(normalize-space(),'Class:')][1]", $root, true, "#:\s*.+?(?:/([A-Z]{1,2}))\s*$#") ?? null, true, true)
                ->duration($this->http->FindSingleNode(".//tr[starts-with(normalize-space(),'Duration:')][1]", $root, true, "#:\s*(.+)#"))
                ->aircraft($this->http->FindSingleNode(".//tr[starts-with(normalize-space(),'Aircraft:')][1]", $root, true, "#:\s*(.+)#"))
            ;

            if (!empty($s->getDepName()) && !empty($s->getArrName())) {
                $seats = array_filter($this->http->FindNodes("//text()[" . $this->eq('Seat Preference') . "]/ancestor::tr[1][" . $this->contains('Flight Info') . "]/following-sibling::tr["
                        . "td[contains(normalize-space(),'" . trim(explode(',', $s->getDepName())[0]) . "-" . trim(explode(',', $s->getArrName())[0]) . "')]]/td[3]", null, "#^\s*(\d{1,3}[A-Z])/#"));

                if (!empty($seats)) {
                    $s->extra()
                        ->seats($seats);
                }
            }
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

    private function amount($s)
    {
        $s = trim(str_replace(",", ".", preg_replace("#[., ](\d{3})#", "$1", $this->re("#(\d[\d\,\. ]*)#", $s))));

        if (is_numeric($s)) {
            return (float) $s;
        }

        return null;
    }

    private function currency($s)
    {
        $sym = [
            '€'=> 'EUR',
            '$'=> 'USD',
            '£'=> 'GBP',
        ];

        if ($code = $this->re("#(?:^|\s|\d)([A-Z]{3})(?:$|\s|\d)#", $s)) {
            return $code;
        }
        $s = $this->re("#([^\d\,\. ]+)#", $s);

        foreach ($sym as $f=> $r) {
            if (strpos($s, $f) !== false) {
                return $r;
            }
        }

        return null;
    }

    private function preg_implode($field)
    {
        if (!is_array($field)) {
            $field = [$field];
        }

        return '(?:' . implode("|", array_map(function ($v) {return preg_quote($v, '#'); }, $field)) . ')';
    }

    private function normalizeDate($str)
    {
        $in = [
            "#^\s*[^\s\d\,\.]+\s+(\d+)\s+([^\s\d\,\.]+)\s+(\d{4})\s+(\d+:\d+)\s*$#", // Fri 31 Aug 2018 17:50
        ];
        $out = [
            "$1 $2 $3, $4",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return strtotime($str);
    }
}
