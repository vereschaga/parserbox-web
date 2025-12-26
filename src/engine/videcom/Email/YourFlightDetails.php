<?php

namespace AwardWallet\Engine\videcom\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;

class YourFlightDetails extends \TAccountChecker
{
    public $mailFiles = "videcom/it-763296695.eml";

    public $detectFrom = "no-reply@videcom.com";
    public $detectSubject = [
        // en
        'Fly The Whale: Your flight details.',
    ];

    public $lang;
    public static $dictionary = [
        'en' => [
            'Est. Travel Time:' => 'Est. Travel Time:',
            'FLIGHT'            => 'FLIGHT',
            'DEPARTS'           => 'DEPARTS',
            'ARRIVES'           => 'ARRIVES',
        ],
    ];

    public function detectEmailFromProvider($from)
    {
        return preg_match("/[@.]videcom\.com$/", $from) > 0;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (stripos($headers["from"], $this->detectFrom) === false) {
            return false;
        }

        foreach ($this->detectSubject as $dSubject) {
            if (stripos($headers["subject"], $dSubject) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if (
            $this->http->XPath->query("//a[{$this->contains(['.videcom.com'], '@href')}]")->length === 0
            && $this->http->XPath->query("//*[{$this->contains(['@videcom.com'])}]")->length === 0
        ) {
            return false;
        }

        foreach (self::$dictionary as $lang => $dict) {
            if (!empty($dict["Est. Travel Time:"]) && !empty($dict["FLIGHT"])
                && !empty($dict["DEPARTS"]) && !empty($dict["ARRIVES"])
                && $this->http->XPath->query("//text()[{$this->starts($dict['Est. Travel Time:'])}]/following::tr[1][ *[1]//text()[{$this->contains($dict['FLIGHT'])}]"
                    . "and *[2]//text()[{$this->contains($dict['DEPARTS'])}] and  *[3]//text()[{$this->contains($dict['ARRIVES'])}] ]")->length > 0
            ) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->parseEmailHtml($email);

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

    private function parseEmailHtml(Email $email)
    {
        $email->obtainTravelAgency();

        $f = $email->add()->flight();

        $f->general()
            ->confirmation($this->http->FindSingleNode("//text()[{$this->eq($this->t('Booking Reference:'))}]/following::text()[normalize-space()][1]",
                null, true, "/^\s*([A-Z\d]{5,7})\s*$/"))
            ->travellers(preg_replace('/\s*(DR|MS|MR)\s*$/', '',
                $this->http->FindNodes("//text()[{$this->eq($this->t('PASSENGER'))}]/following::text()[normalize-space()][1]")), true)
        ;
        $f->issued()
            ->tickets($this->http->FindNodes("//text()[{$this->eq($this->t('TICKET #'))}]/ancestor::tr[1]",
                null, "/^\s*{$this->opt($this->t('TICKET #'))}\D*\s+(\d.+)\s*$/"), false)
        ;

        $nodes = $this->http->XPath->query("//tr[ *[1]//text()[{$this->eq($this->t('FLIGHT'))}] and *[2]//text()[{$this->contains($this->t('DEPARTS'))}] and  *[3]//text()[{$this->contains($this->t('ARRIVES'))}] ]/following-sibling::tr[normalize-space()][1]");

        foreach ($nodes as $root) {
            $s = $f->addSegment();

            // Airline
            if (preg_match("/^\s*(?<name>[A-Z\d][A-Z]|[A-Z][A-Z\d])\s*(?<number>\d{1,5})\s*$/u", $this->http->FindSingleNode("*[1]", $root), $m)) {
                $s->airline()
                    ->name($m['name'])
                    ->number($m['number']);
            }

            $date = $this->http->FindSingleNode("preceding-sibling::tr[2]/descendant::td[not(.//td)][normalize-space()][1]", $root);
            $re = "/^\s*(?<code>[A-Z]{3})\s*(?<time>\d{1,2}:\d{2}.*)\n\s*(?<name>[\s\S]+?)\s*$/";

            // Departure
            $depart = implode("\n", $this->http->FindNodes("*[2]//text()[normalize-space()]", $root));

            if (preg_match($re, $depart, $m)) {
                $s->departure()
                    ->code($m['code'])
                    ->name($m['name'])
                    ->date(!empty($date) ? $this->normalizeDate($date . ', ' . $m['time']) : null)
                ;
            }
            // Arrival
            $depart = implode("\n", $this->http->FindNodes("*[3]//text()[normalize-space()]", $root));

            if (preg_match($re, $depart, $m)) {
                $s->arrival()
                    ->code($m['code'])
                    ->name($m['name'])
                    ->date(!empty($date) ? $this->normalizeDate($date . ', ' . $m['time']) : null)
                ;
            }

            $cabin = $this->http->FindSingleNode("following-sibling::tr[1]/text()[{$this->eq($this->t('CLASS'))}]", $root);

            if (preg_match("/^\s*(.+?)\s*\(([A-Z]{1,2})\)\s*$/", $cabin, $m)) {
                $s->extra()
                    ->cabin($m[1])
                    ->bookingCode($m[2]);
            }
        }

        // Price
        $xpathPrice = "//tr[ *[1][{$this->eq($this->t('Charges'))}] and *[2][{$this->eq($this->t('Price'))}] ]/ancestor-or-self::tr[ following-sibling::tr[normalize-space()] ][1]/following-sibling::tr[normalize-space()]";
        $totalPrice = $this->http->FindSingleNode($xpathPrice . "/descendant-or-self::tr[ *[1][{$this->eq($this->t('Total'))}] ][1]/*[2]"); // 1635.90

        if (preg_match("/^\s*(?<currency>[^\d\s]{1,5})\s*(?<amount>\d[\d\., ]*)\s*$/", $totalPrice, $m)
            || preg_match("/^\s*(?<amount>\d[\d\., ]*)\s*(?<currency>[^\d\s]{1,5})\s*$/", $totalPrice, $m)
        ) {
            $currency = $m['currency'];
            $currencyCode = preg_match('/^[A-Z]{3}$/', $currency) ? $currency : null;
            $f->price()
                ->currency($currencyCode ?? $currency)
                ->total(PriceHelper::parse($m['amount'], $currencyCode));

            $fareAmount = $this->http->FindSingleNode($xpathPrice . "/descendant-or-self::tr[ *[1][{$this->eq($this->t('Fare'))}] ][1]/*[2]", null, true, '/^\D{0,4}\s*(\d[,.‘\'\d ]*)\s*\D{0,4}$/u');

            if ($fareAmount !== null) {
                $f->price()->cost(PriceHelper::parse($fareAmount, $currencyCode));
            }

            $feeRows = $this->http->XPath->query($xpathPrice . "/descendant-or-self::tr[ preceding-sibling::tr[descendant-or-self::tr[*[1][{$this->eq($this->t('Fare'))}]]] and following-sibling::tr[descendant-or-self::tr[*[1][{$this->eq($this->t('Total'))}]]] ]");

            foreach ($feeRows as $feeRow) {
                $feeAmount = $this->http->FindSingleNode("descendant-or-self::tr[ *[2] ][1]/*[2]", $feeRow, true, '/^\D{0,4}\s*(\d[,.‘\'\d ]*)\s*\D{0,4}$/u');

                if ($feeAmount !== null) {
                    $feeName = $this->http->FindSingleNode("descendant-or-self::tr[ *[2] ][1]/*[1]", $feeRow);
                    $f->price()->fee($feeName, PriceHelper::parse($feeAmount, $currencyCode));
                }
            }
        }

        return true;
    }

    private function t($s)
    {
        if (!isset($this->lang, self::$dictionary[$this->lang], self::$dictionary[$this->lang][$s])) {
            return $s;
        }

        return self::$dictionary[$this->lang][$s];
    }

    private function contains($field, $text = 'normalize-space(.)')
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($text) {
            return 'contains(' . $text . ',"' . $s . '")';
        }, $field)) . ')';
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) {
            return 'normalize-space(.)="' . $s . '"';
        }, $field)) . ')';
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) {
            return 'starts-with(normalize-space(.),"' . $s . '")';
        }, $field)) . ')';
    }

    private function normalizeDate(?string $date): ?int
    {
        $in = [
            // Mon 28 Dec 20, 12:00
            "/^\s*[[:alpha:]]+\s+(\d{1,2})\s+([[:alpha:]]+)\s+(\d{2})\s*,\s*(\d{1,2}:\d{2})\s*$/iu",
        ];
        $out = [
            "$1 $2 20$3, $4",
        ];

        $date = preg_replace($in, $out, $date);

        return strtotime($date);
    }

    private function opt($field, $delimiter = '/')
    {
        $field = (array) $field;

        if (empty($field)) {
            $field = ['false'];
        }

        return '(?:' . implode("|", array_map(function ($s) use ($delimiter) {
            return str_replace(' ', '\s+', preg_quote($s, $delimiter));
        }, $field)) . ')';
    }
}
