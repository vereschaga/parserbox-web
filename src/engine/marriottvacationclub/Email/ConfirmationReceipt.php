<?php

namespace AwardWallet\Engine\marriottvacationclub\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class ConfirmationReceipt extends \TAccountChecker
{
    public $mailFiles = "marriottvacationclub/it-88088604.eml";
    public $subjects = [
        '/Marriott Vacation Club Confirmation Receipt/',
    ];

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@vacationclub.com') !== false) {
            foreach ($this->subjects as $subject) {
                if (preg_match($subject, $headers['subject'])) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        return $this->http->XPath->query("//text()[contains(normalize-space(), 'Your Reservation for Owner Deposit is Confirmed')]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Transaction Summary'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Reservation Details'))}]")->length > 0;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]vacationclub\.com$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $h = $email->add()->hotel();

        $h->general()
            ->confirmation($this->http->FindSingleNode("//text()[{$this->starts($this->t('Your Confirmation Number:'))}]/following::text()[normalize-space()][1]"));

        $h->hotel()
            ->name($this->http->FindSingleNode("//text()[{$this->starts($this->t('Resort Details'))}]/ancestor::td[1]/descendant::a[1]"));

        $addressText = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Resort Details'))}]/ancestor::td[1]/descendant::p[not(contains(normalize-space(), 'Resort Details'))]");
        $address = $this->re("/{$h->getHotelName()}\s*(.+)/", $addressText);

        if (!empty($address)) {
            $h->hotel()
                ->address($address);
        }

        $dateText = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Weeks Deposited:'))}]/following::text()[normalize-space()][1]");

        if (preg_match("/(\w+\s*\d+\,\s*\d{4})[\s\-]+(\w+\s*\d+\,\s*\d{4})\s*/", $dateText, $m)) {
            $h->booked()
                ->checkIn(strtotime($m[1]))
                ->checkOut(strtotime($m[2]));
        }

        $roomType = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Villa Portion:')]/following::text()[normalize-space()][1]");
        $roomDescription = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Floor Plan:')]/following::text()[normalize-space()][1]");

        if (!empty($roomType) || !empty($roomDescription)) {
            $room = $h->addRoom();

            if (!empty($roomType)) {
                $room->setType($roomType);
            }

            if (!empty($roomDescription)) {
                $room->setDescription($roomDescription);
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

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }
}
