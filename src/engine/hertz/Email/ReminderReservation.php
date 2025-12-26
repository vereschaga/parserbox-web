<?php

namespace AwardWallet\Engine\hertz\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class ReminderReservation extends \TAccountChecker
{
    public $mailFiles = "hertz/it-151334072.eml";
    public $subjects = [
        'Reminder: Happy Tours - Hertz Reservation #',
    ];

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@rentacarserver.com') !== false) {
            foreach ($this->subjects as $subject) {
                if (strpos($headers['subject'], $subject) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//text()[contains(normalize-space(), 'Hertz')]")->length > 0) {
            return $this->http->XPath->query("//text()[{$this->contains($this->t('Congratulations, your reservation has been successfully completed'))}]")->length > 0
                && $this->http->XPath->query("//text()[{$this->contains($this->t('confirmation voucher for your car rental reservation with'))}]")->length > 0;
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]rentacarserver\.com$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $r = $email->add()->rental();

        $r->general()
            ->confirmation($this->http->FindSingleNode("//text()[normalize-space()='Confirmation #']/ancestor::td[1]", null, true, "/{$this->opt($this->t('Confirmation #'))}\s*([A-Z\d]+)/"))
            ->traveller($this->http->FindSingleNode("//text()[normalize-space()='Name:']/ancestor::td[1]", null, true, "/{$this->opt($this->t('Name:'))}\s*(.+)/"));

        $pickupText = $this->http->FindSingleNode("//text()[normalize-space()='PICKUP:']/ancestor::tr[1]/following::tr[1]/descendant::td[1]");

        if (preg_match("/^(.+)Date\:(.+)$/", $pickupText, $m)) {
            $r->pickup()
                ->location($m[1])
                ->date(strtotime($m[2]));
        }

        $dropOffText = $this->http->FindSingleNode("//text()[normalize-space()='PICKUP:']/ancestor::tr[1]/following::tr[1]/descendant::td[2]");

        if (preg_match("/^(.+)Date\:(.+)$/", $dropOffText, $m)) {
            $r->dropoff()
                ->location($m[1])
                ->date(strtotime($m[2]));
        }

        $car = $this->http->FindSingleNode("//text()[normalize-space()='Car Description:']/ancestor::td[1]", null, true, "/{$this->opt($this->t('Car Description:'))}\s*(.+)/");

        if (!empty($car)) {
            $r->car()
                ->model($car);
        }

        $total = $this->http->FindSingleNode("//text()[normalize-space()='Total Due:']/ancestor::td[1]", null, true, "/{$this->opt($this->t('Total Due:'))}\s*(.+)/");

        if (preg_match("/^\D([\d\.\,]+)\s*([A-Z]{3})$/", $total, $m)) {
            $r->price()
                ->total(PriceHelper::parse($m[1], $m[2]))
                ->currency($m[2]);
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
