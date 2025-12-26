<?php

namespace AwardWallet\Engine\mta\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class TransferVoucher2 extends \TAccountChecker
{
    public $mailFiles = "mta/it-512771930.eml, mta/it-512772171.eml";
    public $subjects = [
        'Transfer Booking Confirmation #',
    ];

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], 'mtatravel.com.au') !== false) {
            foreach ($this->subjects as $subject) {
                if (stripos($headers['subject'], $subject) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//text()[contains(normalize-space(), 'MTA Travel')]")->length > 0) {
            return ($this->http->XPath->query("//text()[{$this->contains($this->t('Pickup Time:'))}]")->length > 0 || $this->http->XPath->query("//text()[{$this->contains($this->t('Arrival Time:'))}]")->length > 0)
                && $this->http->XPath->query("//text()[{$this->contains($this->t('Number of Passengers:'))}]")->length > 0
                && $this->http->XPath->query("//text()[{$this->contains($this->t('From:'))}]")->length > 0;
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]mtatravel\.com\.au$/', $from) > 0;
    }

    public function parseTransfer(Email $email)
    {
        $t = $email->add()->transfer();

        $traveller = $this->http->FindSingleNode("//text()[normalize-space()='Customer Name:']/ancestor::tr[1]/descendant::td[normalize-space()][2]");

        $t->general()
            ->confirmation($this->http->FindSingleNode("//text()[normalize-space()='Customer Reference Number:']/ancestor::tr[1]/descendant::td[normalize-space()][2]", null, true, "/^([A-Z\d]{5,})$/"))
            ->traveller($traveller);

        $s = $t->addSegment();

        $s->departure()
            ->name($this->http->FindSingleNode("//text()[normalize-space()='From:']/ancestor::tr[1]/descendant::td[normalize-space()][2]"));

        $s->arrival()
            ->name($this->http->FindSingleNode("//text()[normalize-space()='To:']/ancestor::tr[1]/descendant::td[normalize-space()][2]"));

        if ($this->http->XPath->query("//text()[contains(normalize-space(), 'Departure Time:')]")->length > 0) {
            $depDate = $this->http->FindSingleNode("//text()[normalize-space()='Departure Time:']/ancestor::tr[1]/descendant::td[normalize-space()][2]");

            $s->departure()
                ->date($this->normalizeDate($depDate));

            $s->arrival()
                ->noDate();
        } else {
            $s->departure()
                ->noDate();

            $arrDate = $this->http->FindSingleNode("//text()[normalize-space()='Arrival Time:']/ancestor::tr[1]/descendant::td[normalize-space()][2]");
            $s->arrival()
                ->date($this->normalizeDate($arrDate));
        }

        $price = $this->http->FindSingleNode("//text()[normalize-space()='Payment Details:']/ancestor::tr[1]/descendant::td[normalize-space()][2]", null, true, "/^(\D{1,3}[\d\.\,]+)\s+/");
        $earnedPoints = $this->http->FindSingleNode("//text()[normalize-space()='You Earned:']/ancestor::tr[1]/descendant::td[normalize-space()][2]");

        if (!empty($earnedPoints)) {
            $t->setEarnedAwards($earnedPoints);
        }

        if (preg_match("/^(?<currency>\D+)(?<total>[\d\.\,]+)$/", $price, $m)) {
            $t->price()
                ->total(PriceHelper::parse($m['total'], $m['currency']))
                ->currency($m['currency']);
        }

        $s->setAdults($this->http->FindSingleNode("//text()[normalize-space()='Number of Passengers:']/ancestor::tr[1]/descendant::td[normalize-space()][2]", null, true, "/^(\d+)$/"));
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $email->ota()
            ->confirmation($this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Room-Res Booking Id:')]/ancestor::tr[1]/descendant::td[2]", null, true, "/^([A-Z\d]{5,})$/"));

        $this->parseTransfer($email);

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

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) {
            return "starts-with(normalize-space(.), \"{$s}\")";
        }, $field));
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) {
            return "contains(normalize-space(.), \"{$s}\")";
        }, $field));
    }

    private function normalizeDate($str)
    {
        $in = [
            "#^(\d+)\-(\w+)\-(\d{4})\s*at\s*([\d\:]+)$#u", //23-Oct-2023 at 11:15
        ];
        $out = [
            "$1 $2 $3, $4",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = \AwardWallet\Engine\MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return strtotime($str);
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
    }
}
