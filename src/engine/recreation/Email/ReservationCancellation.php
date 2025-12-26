<?php

namespace AwardWallet\Engine\recreation\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class ReservationCancellation extends \TAccountChecker
{
    public $mailFiles = "recreation/it-309672994.eml";
    public $subjects = [
        'Reservation Cancellation - Location Closure',
    ];

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@recreation.gov') !== false) {
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
        return $this->http->XPath->query("//text()[contains(normalize-space(), 'Recreation.gov')]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Reservation Cancellation - Location Closure'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('A location closure has been issued for'))}]")->length > 0;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]recreation\.gov$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $h = $email->add()->hotel();

        $h->general()
            ->traveller($this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Hi')]", null, true, "/{$this->opt($this->t('Hi'))}(\D+)\,/"));

        $info = $this->http->FindSingleNode("//text()[contains(normalize-space(), 'your Recreation.gov reservation,')]/ancestor::p[1]");

        if (preg_match("/{$this->opt($this->t('of your Recreation.gov reservation,'))}\s+(?<confNumber>[\d\-]+)\,\s*at\s*(?<hotelName>\D+)\s+for\s+(?<inDate>.+)\s*\-\s*(?<outDate>.+)\./", $info, $m)) {
            $h->general()
                ->confirmation($m['confNumber']);

            $h->hotel()
                ->name($m['hotelName'])
                ->noAddress();

            $h->booked()
                ->checkIn(strtotime($m['inDate']))
                ->checkOut(strtotime($m['outDate']));
        }

        if ($this->http->XPath->query("//text()[{$this->contains($this->t('Reservation Cancellation - Location Closure'))}]")->length > 0) {
            $h->general()
                ->cancelled();
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
