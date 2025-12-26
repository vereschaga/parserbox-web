<?php

namespace AwardWallet\Engine\panpacific\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class It1771471 extends \TAccountCheckerExtended
{
    public $mailFiles = "panpacific/it-1771471.eml";

    public $detectSubject = [
        'Reservation Confirmation For',
    ];

    public $detectBody = [
        "en" => ['Your Stay With Us',],
    ];

    public $lang = "";
    public static $dictionary = [
        "en" => [],
    ];

    public function parseHtml(Email $email)
    {
        $h = $email->add()->hotel();

        // General
        $h->general()
            ->confirmation($this->http->FindSingleNode("//text()[" . $this->starts($this->t("Confirmation Number")) . "]",
                null, true, "/^\s*" . $this->opt($this->t("Confirmation Number")) . "\s*([A-Z\d]{5,})\s*$/"))
            ->traveller($this->http->FindSingleNode("//text()[" . $this->starts($this->t("Dear ")) . "]",
                null, true, "/^\s*{$this->opt($this->t("Dear "))}\s*(?:(?:Mr|Mrs) )?([[:alpha:] \-]+?)[,!]\s*$/"), false)
            ->cancellation($this->http->FindSingleNode("//td[descendant::text()[normalize-space()][1][" . $this->eq($this->t("Cancellation Charges")) . "]]",
                null, true, "/^\s*{$this->opt($this->t("Cancellation Charges"))}\s*(.+)$/"))
        ;

        // Hotel
        $h->hotel()
            ->name($this->http->FindSingleNode("//tr[./td/*[contains(text(), 'Your Stay With Us')]]/following-sibling::tr[1]/td/text()[1]"))
            ->address($this->http->FindSingleNode("//tr[./td[1][contains(text(), 'Address')]]/td[2]/text()"))
            ->phone($this->http->FindSingleNode("//tr[./td[1][contains(text(), 'Telephone')]]/td[2]/text()"), true, true)
        ;

        // Booked
        $datesStr = $this->http->FindSingleNode("//tr[./td/*[contains(text(), 'Your Stay With Us')]]/following-sibling::tr[1]/td/text()[2]");
        if (preg_match("/(.+) - (.+)/", $datesStr, $m)) {
            $h->booked()
                ->checkIn(strtotime($m[1]))
                ->checkOut(strtotime($m[2]));
        }
        $time = $this->http->FindSingleNode("//tr[./td[1][contains(text(), 'Hotel Check-In')]]/td[2]/text()");
        if (!empty($time) && !empty($h->getCheckInDate())) {
            $h->booked()
                ->checkIn(strtotime($time, $h->getCheckInDate()));
        }
        $time = $this->http->FindSingleNode("//tr[./td[1][contains(text(), 'Hotel Check-Out')]]/td[2]/text()");
        if (!empty($time) && !empty($h->getCheckOutDate())) {
            $h->booked()
                ->checkOut(strtotime($time, $h->getCheckOutDate()));
        }

        $h->booked()
            ->guests($this->http->FindSingleNode("//tr[./td/*[contains(text(), 'Your Stay With Us')]]/following-sibling::tr[1]/td/text()[5]", null, true, "/(\d+)\s+Adult/ui"))
            ->kids($this->http->FindSingleNode("//tr[./td/*[contains(text(), 'Your Stay With Us')]]/following-sibling::tr[1]/td/text()[5]", null, true, "/,\s*(\d+)\s+Children/ui"))
            ->rooms($this->http->FindSingleNode("//tr[./td/*[contains(text(), 'Your Stay With Us')]]/following-sibling::tr[1]/td/text()[4]", null, true, "/(\d+)\s+Room/ui"))
        ;

        $h->addRoom()
            ->setType($this->http->FindSingleNode("//table/*[./tr[1]/td/*[contains(text(), 'Your Room(s)')]]/tr[2]/td/text()[1]"))
            ->setRate($this->http->FindSingleNode("//table/*[./tr[1]/td/*[contains(text(), 'Payment Details')]]/tr[3]/td[2]/text()"))
        ;

        // Price
        $cost = $this->http->FindSingleNode("//tr[./td[1][contains(text(), 'Total Room Rate')]]/td[2]/text()");
        if (preg_match("#^\s*(?<currency>[^\d\s]{1,5})\s*(?<amount>\d[\d\., ]*)\s*$#", $cost, $m)
            || preg_match("#^\s*(?<amount>\d[\d\., ]*)\s*(?<currency>[^\d\s]{1,5})\s*$#", $cost, $m)){
            $h->price()
                ->cost(PriceHelper::parse($m['amount'], $m['currency']));
        }
        $taxes = $this->http->FindSingleNode("(//tr[./td[1][contains(text(), 'Tax and Service Charges')]])[1]/td[2]/text()");
        if (preg_match("#^\s*(?<currency>[^\d\s]{1,5})\s*(?<amount>\d[\d\., ]*)\s*$#", $taxes, $m)
            || preg_match("#^\s*(?<amount>\d[\d\., ]*)\s*(?<currency>[^\d\s]{1,5})\s*$#", $taxes, $m)){
            $h->price()
                ->tax(PriceHelper::parse($m['amount'], $m['currency']));
        }
        $total = $this->http->FindSingleNode("//tr[./td[1][contains(text(), 'Grand Total')]]/td[2]/text()");
        if (preg_match("#^\s*(?<currency>[^\d\s]{1,5})\s*(?<amount>\d[\d\., ]*)\s*$#", $total, $m)
            || preg_match("#^\s*(?<amount>\d[\d\., ]*)\s*(?<currency>[^\d\s]{1,5})\s*$#", $total, $m)){
            $h->price()
                ->total(PriceHelper::parse($m['amount'], $m['currency']))
                ->currency($m['currency'])
            ;
        }
    }

    public function detectEmailFromProvider($from)
    {
        return strpos($from, '@panpacific.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->detectEmailFromProvider($headers['from']) !== true) {
            return false;
        }

        foreach ($this->detectSubject as $re) {
            if (strpos($headers["subject"], $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//a[contains(@href, '.panpacific.com')]")->length === 0) {
            return false;
        }

        foreach ($this->detectBody as $lang => $dBody) {
            if ($this->http->XPath->query("//node()[{$this->contains($dBody)}]")->length > 0) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        foreach ($this->detectBody as $lang => $dBody) {
            if ($this->http->XPath->query("//node()[{$this->contains($dBody)}]")->length > 0) {
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
        $rule = $this->eq($field);

        return $this->http->FindSingleNode("(.//text()[{$rule}])[1]/following::text()[normalize-space(.)][{$n}]", $root);
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
        $in = [
            "/^\s*(\d{2})\\/(\d{2})\\/(\d{2})\s*$/", //12:05, Thursday 19 June
        ];
        $out = [
            '$1.$2.20$3',
        ];
        $str = preg_replace($in, $out, $str);

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

    private function amount($s)
    {
        return (float) str_replace(",", ".", preg_replace("#[.,](\d{3})#", "$1", $this->re("#([\d\,\.]+)#", $s)));
    }

    private function currency($s)
    {
        $sym = [
            '€' => 'EUR',
            '$' => 'USD',
            '£' => 'GBP',
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

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", $field) . ')';
    }
}
