<?php

namespace AwardWallet\Engine\mango\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class BookingConfirmation extends \TAccountChecker
{
    public $mailFiles = "mango/it-44834752.eml, mango/it-44904961.eml";

    public $reFrom = "mango.com";

    public $reSubject = [
        'en' => 'Booking confirmation from Mango',
    ];

    public $reBody = 'Mango';

    public $reBody2 = [
        'en' => ['Email Confirmation'],
    ];

    public static $dictionary = [
        'en' => [
            //'confirmation' => ['Confirmation Number:'],
        ],
    ];

    public $lang = 'en';

    public function detectEmailFromProvider($from)
    {
        return strpos($from, $this->reFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (!isset($headers["from"], $headers["subject"])) {
            return false;
        }

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
        if ($this->http->XPath->query("//*[{$this->contains($this->reBody)}]")->length > 0) {
            foreach ($this->reBody2 as $re) {
                if ($this->http->XPath->query("//*[{$this->contains($re)}]")->length > 0) {
                    return true;
                }
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

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->date = strtotime($parser->getHeader('date'));
        $this->http->FilterHTML = false;

        foreach ($this->reBody2 as $lang => $re) {
            if ($this->http->XPath->query("//*[{$this->contains($re)}]")->length > 0) {
                $this->lang = $lang;

                break;
            }
        }
        $this->parseHtml($email);
        $a = explode('\\', __CLASS__);
        $email->setType($a[count($a) - 1] . ucfirst($this->lang));

        return $email;
    }

    private function parseHtml(Email $email)
    {
        $f = $email->add()->flight();
        $f->general()->confirmation($this->http->FindSingleNode("//text()[contains(.,'Reference Number:')]/ancestor::tr[1]/following-sibling::tr", null, false, '/^[A-Z\d]{5,6}$/'));

        $travellers = $this->http->XPath->query("//text()[contains(.,'Date of birth:')]/ancestor::tr[1]/ancestor::table[1]");
        $this->logger->debug("Total {$travellers->length} travellers found");

        foreach ($travellers as $traveller) {
            $f->general()->traveller($this->http->FindSingleNode(".//td[contains(.,'Adult ') or contains(.,'Child ')]/following-sibling::td[1]", $traveller));

            if ($account = $this->http->FindSingleNode(".//td[contains(.,'Mobile no:')]/following-sibling::td[1]", $traveller)) {
                $f->program()->account($account, false);
            }
        }

        $seats = [];
        $seatsNode = $this->http->XPath->query("//text()[contains(.,'Seat')]/ancestor::td[1]//table[1]");
//        $this->logger->debug("Total {$travellers->length} seats found");
        /**
         * Seat.
        SHACKLETON AVRILLEIGH MS        2A              2A
         */
        foreach ($seatsNode as $seat) {
            $keys = $this->http->FindNodes(".//tr[1]/th[position() > 1]|.//tr[1]/td[position() > 1]", $seat);

            foreach ($keys as $i => $key) {
                $i++;
                $seats[$key] = $this->http->FindNodes("(.//tr[position() > 1]/td[position() > 1])[{$i}]", $seat);
            }
        }
        //$this->logger->debug(var_export($seats, true));

        $ruleStartsTime = "(starts-with(translate(normalize-space(.),'0123456789','dddddddddd--'),'d:dd') or starts-with(translate(normalize-space(.),'0123456789','dddddddddd--'),'dd:dd'))";
        $segments = $this->http->XPath->query($xpath = "//td[{$ruleStartsTime} and ./following-sibling::td[normalize-space()!=''][{$ruleStartsTime}]]/ancestor::table[1]");
        $this->logger->debug($xpath);
        $this->logger->debug("Total {$segments->length} reservations found");

        foreach ($segments as $segment) {
            $s = $f->addSegment();
            $this->logger->debug($segment->nodeValue);
            // Cape Town (CPT)      Bloemfontein (BFN)
            if (preg_match('/^(.+?) \(([A-Z]{3})\)\s*(.+?) \(([A-Z]{3})\)/', $segment->nodeValue, $m)) {
                $s->departure()->name($m[1]);
                $s->departure()->code($m[2]);
                $s->arrival()->name($m[3]);
                $s->arrival()->code($m[4]);

                if (!empty($seats)) {
                    $s->extra()->seats($seats["{$s->getDepCode()} - {$s->getArrCode()}"]);
                }
            }
            // 09:10   Mon, 16 Sep 2019      10:40
            if (preg_match('/(\d+:\d+)\s+(?<date>.+?)\s+(\d+:\d+)/', $segment->nodeValue, $m)) {
                $s->departure()->date2("{$m['date']}, {$m[1]}");
                $s->arrival()->date2("{$m['date']}, {$m[3]}");
            }
            // 1 h   30 min     Non-stop     Flight JE402 Mango Lowest
            if (preg_match('/([\dhmin\s+]{2,15})\s+Non-stop\s+Flight ([A-Z]{2})\s*(\d{2,4})\s+([\w\s]+)/', $segment->nodeValue, $m)) {
                $s->extra()->duration(preg_replace('/\s{2,}/', ' ', $m[1]));
                $s->airline()->name($m[2]);
                $s->airline()->number($m[3]);
                $s->extra()->cabin($m[4]);
            }
        }

        $total = $this->http->FindSingleNode("//text()[normalize-space(.)='Amount:']/ancestor::td[1]/following-sibling::td");
        // R1598.00
        if ($total && preg_match('/^\s*R([\d\s.]+)$/', $total, $m)) {
            $f->price()
                ->total($m[1])
                ->currency('ZAR');
        }

        $f->price()->cost($this->http->FindSingleNode("//text()[normalize-space(.)='Airfare:']/ancestor::td[1]/following-sibling::td", null, false, '/^\s*R([\d\s.]+)$/'), false, true);

        if ($tax = $this->http->FindSingleNode("//text()[normalize-space(.)='Total Taxes']/ancestor::td[1]/following-sibling::td", null, false, '/^\s*R([\d\s.]+)$/')) {
            $f->price()->tax($tax);
        }
    }

    private function getField($field)
    {
        return $this->http->FindSingleNode("//text()[{$this->eq($this->t($field))}]/following::text()[normalize-space(.)!=''][1]");
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return null;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function opt($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return '';
        }

        return '(?:' . implode('|', array_map(function ($s) {
            return preg_quote($s, '/');
        }, $field)) . ')';
    }

    private function eq($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ',
                array_map(function ($s) use ($node) {
                    return 'normalize-space(' . $node . ')="' . $s . '"';
                }, $field)) . ')';
    }

    private function contains($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ',
                array_map(function ($s) use ($node) {
                    return 'contains(normalize-space(' . $node . '),"' . $s . '")';
                }, $field))
            . ')';
    }

    private function normalizeDate($str)
    {
//        $in = [
//            "#^(\w+)\s+(\d+),\s+(\d{4})$#",
//            "#^(\w+)\s+(\d+),\s+(\d{4}),\s+(\d+:\d+\s*[ap]m)$#i",
//        ];
//        $out = [
//            "$2 $1 $3",
//            "$2 $1 $3, $4",
//        ];
//        return strtotime($this->dateStringToEnglish(preg_replace($in, $out, $str)));
        return strtotime($str, false);
    }

    private function dateStringToEnglish($date)
    {
        if (preg_match('#[[:alpha:]]+#iu', $date, $m)) {
            $monthNameOriginal = $m[0];

            if ($translatedMonthName = MonthTranslate::translate($monthNameOriginal, $this->lang)) {
                return preg_replace("#$monthNameOriginal#i", $translatedMonthName, $date);
            }
        }

        return $date;
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function getTotalCurrency($node)
    {
        $node = str_replace(["€", "£", "$", "₹"], ["EUR", "GBP", "USD", "INR"], $node);
        $tot = '';
        $cur = '';

        if (preg_match("#(?<c>[A-Z]{3})\s*(?<t>\d[\.\d\,\s]*\d*)#", $node, $m)
            || preg_match("#(?<t>\d[\.\d\,\s]*\d*)\s*(?<c>[A-Z]{3})#", $node, $m)
        ) {
            $cur = $m['c'];
            $tot = PriceHelper::cost($m['t']);
        }

        return ['Total' => $tot, 'Currency' => $cur];
    }
}
