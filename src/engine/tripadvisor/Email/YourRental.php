<?php

namespace AwardWallet\Engine\tripadvisor\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class YourRental extends \TAccountChecker
{
    public $mailFiles = "tripadvisor/it-75247337.eml";

    public $reSubject = [
        "en" => "Confirmed! Your", "rental is booked",
    ];
    public $reBody = 'TripAdvisor';
    public $reBody2 = [
        "en"  => "BOOKING REF",
        'en2' => 'To keep your payment secure, pay through the Tripadvisor website with your credit/debit card or PayPal account',
        'en3' => 'To keep your payment secure, pay through the TripAdvisor website with your credit/debit card or PayPal account',
    ];

    public static $dictionary = [
        "en" => [
            "Your Booking" => ["Your Booking", "Your booking", 'Your enquiry'],
            "AMOUNT PAID:" => ["AMOUNT PAID:", "TOTAL RENTAL COST:"],
            "Good Evening" => ["Good Evening", "Hi"],
        ],
    ];

    public $lang = "en";

    private $date;

    public function parseHtml(Email $email): void
    {
        $h = $email->add()->hotel();

        // ConfirmationNumber
        $confirmation = $this->nextText($this->t("BOOKING REF"));

        if (empty($confirmation) && 0 < $this->http->XPath->query("//td[contains(normalize-space(.), 'to your Rental Inbox') and not(.//td)]")->length) {
            $h->general()
                ->noConfirmation();
        } else {
            $h->general()
                ->confirmation($confirmation);
        }

        $h->general()->cancellation($this->http->FindSingleNode("//text()[{$this->eq($this->t('Cancellation policy:'))}]/ancestor::td[1]", null, true, "/{$this->opt($this->t('Cancellation policy:'))}\s*(.+)/"), false, true);

        $traveller = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Good Evening'))}]", null, true, "/{$this->opt($this->t('Good Evening'))}\s+(.+)/");

        if (!empty($traveller)) {
            $h->general()
                ->traveller(trim($traveller, ','));
        }

        // Hotel Name
        $hotelName = $this->nextText($this->t("Your Booking"));

        if (empty($hotelName) || stripos($hotelName, '@media') !== false) {
            $hotelName = $this->http->FindSingleNode("//text()[{$this->eq($this->t("Your Booking"))}]/following::text()[normalize-space()][not(contains(normalize-space(),'media'))][1]");
        }

        $address = $this->http->FindSingleNode("//a[" . $this->eq($hotelName) . "]/following::tr[1]", null, true, "#(.*?)\s+\|#");

        if (empty($address)) {
            $address = $this->http->FindSingleNode("//a[" . $this->starts($this->re("/^(.+)\-/", $hotelName)) . "]/following::tr[1]", null, true, "#(.*?)\s+\|#");
        }

        $h->hotel()
            ->name($hotelName)
            ->address($address);

        $this->logger->error("//a[" . $this->eq($hotelName) . "]/following::tr[1]");
        $h->booked()
            ->checkIn(strtotime($this->normalizeDate($this->re("#(.*?)\s+-#", $this->nextText($this->t("DATES"))))))
            ->checkOut(strtotime($this->normalizeDate($this->re("#\s+-\s+(.+)#", $this->nextText($this->t("DATES"))))))
            ->guests($this->re("#(\d+)\s+GUEST#", $this->nextText($this->t("GUESTS"))));

        // Total
        $total = $this->amount($this->nextText($this->t("AMOUNT PAID:")));

        if (empty($total)) {
            $t1 = str_replace(',', '', $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'DEPOSIT PAID:')]/following::text()[normalize-space()][1]", null, true, "/^\D([\d\.\,]+)/"));
            $t2 = str_replace(',', '', $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'BALANCE DUE*:')]/following::text()[normalize-space()][1]", null, true, "/^\D([\d\.\,]+)/"));

            if (!empty($t1) && !empty($t2)) {
                $total = $t1 + $t2;
            }
        }
        $currency = $this->currency($this->nextText($this->t("AMOUNT PAID:")));

        if (empty($currency)) {
            $currency = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'DEPOSIT PAID:')]/following::text()[normalize-space()][1]", null, true, "/^(\D)[\d\.\,]+/");
        }

        if (!empty($total) && !empty($currency)) {
            $h->price()
                ->total($total)
                ->currency($currency);
        }
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.](?:tripadvisor|housetrip)\.com/i', $from) > 0;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->detectEmailFromProvider($headers['from']) !== true) {
            return false;
        }

        foreach ($this->reSubject as $re) {
            if (stripos($headers["subject"], $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        if (strpos($body, $this->reBody) === false) {
            return false;
        }

        foreach ($this->reBody2 as $re) {
            if ($this->http->XPath->query("//*[contains(normalize-space(),\"{$re}\")]")->length > 0) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->date = strtotime($parser->getHeader('date'));

        $this->http->FilterHTML = true;

        foreach ($this->reBody2 as $lang=>$re) {
            if (strpos($this->http->Response["body"], $re) !== false) {
                $this->lang = substr($lang, 0, 2);

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
        return (float) str_replace(",", ".", preg_replace("#[.,](\d{3})#", "$1", $this->re("#([\d\.\,]+)#", $s)));
    }

    private function currency($s)
    {
        return $this->re("#^([A-Z]{3})\s*\d#", $s);
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

        return implode(" or ", array_map(function ($s) { return "normalize-space(.)=\"{$s}\""; }, $field));
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) { return "starts-with(normalize-space(.), \"{$s}\")"; }, $field));
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) { return "contains(normalize-space(.), \"{$s}\")"; }, $field));
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
    }
}
