<?php

namespace AwardWallet\Engine\priceline\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class HotelTips extends \TAccountChecker
{
    public $mailFiles = "priceline/it-860244911.eml, priceline/it-860245358.eml";

    public $lang = 'en';

    public $detectSubjects = [
        'en' => ['Family: Arrival Tips'],
    ];

    public $detectBody = [
        'en' => [],
    ];

    public static $dictionary = [
        "en" => [
            'Adults'   => ['Adult', 'Adults'],
            'Children' => ['Children', 'Children*'],
        ],
    ];

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]priceline\.com$/', $from) > 0;
    }

    public function detectEmailByHeaders(array $headers)
    {
        // detect Provider
        if (empty($headers['from']) || stripos($headers['from'], 'fwd.priceline.com') === false) {
            return false;
        }

        // detect Format
        foreach ($this->detectSubjects as $detectSubjects) {
            foreach ($detectSubjects as $dSubjects) {
                if (stripos($headers['subject'], $dSubjects) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        // detect Provider
        if (
            $this->http->XPath->query("//a/@href[{$this->contains(['priceline.com'])}]")->length === 0
            && stripos($parser->getHeader('subject'), 'Priceline') !== false
        ) {
            return false;
        }

        // detect Format
        if ($this->http->XPath->query("//text()[{$this->contains($this->t('help you and your family'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->eq($this->t('Check In'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->starts($this->t('REMINDER'))}]")->length > 0
        ) {
            return true;
        }

        return false;
    }

    public function parseHotel(PlancakeEmailParser $parser, Email $email)
    {
        $h = $email->add()->hotel();

        // collect ota reservation confirmation
        if (preg_match("/^\s*{$this->opt($this->t('Your Priceline itinerary'))}\s*\((?<desc>Trip ?#)\s*(?<number>[\d\-]{8,})\)\D*$/", $parser->getSubject(), $m)) {
            $email->ota()->confirmation($m['number'], $m['desc']);
        }

        // collect reservation confirmation
        $confText = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Confirmation Number'))}]/ancestor::table[1]");

        if (preg_match("/^\s*(?<desc>{$this->opt($this->t('Confirmation Number'))})\s*(?<number>\d+)\s*$/", $confText, $m)) {
            $h->general()
                ->confirmation($m['number'], $m['desc']);
        }

        // collect traveller name
        $travellerName = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Guest Name'))}]/following::text()[normalize-space()][1]", null, true, "/^\s*([[:alpha:]][-.\'[:alpha:] ]*[[:alpha:]])\s*$/u");

        if (!empty($travellerName)) {
            $h->general()
                ->traveller($travellerName, true);
        }

        // collect check-in and check-out dates
        $timeOfClockPattern = "\d+(?:\s*\:\s*\d+)?\s*(?:[AP]M)?"; // use with 'insensitive' regex flag
        $datePattern = "/^\s*\w+\,\s+(\w+\s+\d+\,\s+\d{4})\s*$/";
        $dateCheckIn = strtotime($this->http->FindSingleNode("//text()[{$this->eq($this->t('Check In'))}]/following::text()[normalize-space()][1]", null, true, $datePattern));
        $dateCheckOut = strtotime($this->http->FindSingleNode("//text()[{$this->eq($this->t('Check Out'))}]/following::text()[normalize-space()][1]", null, true, $datePattern));
        $timeCheckIn = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Check In'))}]/following::text()[normalize-space()][2]", null, true, "/^($timeOfClockPattern)(?:\s|$)/i");
        $timeCheckOut = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Check Out'))}]/following::text()[normalize-space()][2]", null, true, "/\s($timeOfClockPattern)$/i");

        if (!empty($dateCheckIn) && !empty($timeCheckIn)) {
            $h->booked()->checkIn(strtotime($timeCheckIn, $dateCheckIn));
        }

        if (!empty($dateCheckOut) && !empty($timeCheckOut)) {
            $h->booked()->checkOut(strtotime($timeCheckOut, $dateCheckOut));
        }

        // collect hotel name and address
        $h->hotel()
            ->name($this->http->FindSingleNode("//text()[{$this->eq($this->t('Plan Your Arrival'))}]/following::text()[normalize-space()][1]"))
            ->address($this->http->FindSingleNode("//text()[{$this->eq($this->t('Plan Your Arrival'))}]/following::text()[normalize-space()][2]"));

        // collect guests and kids
        $guestsText = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Guests'))}]/following::text()[normalize-space()][1]");

        if (preg_match("/^\s*{$this->opt($this->t('Adults'))}[\s\-\–]+(?<guestsCount>\d+)\s*(?:{$this->opt($this->t('Children'))}[\s\-\–]+(?<kidsCount>\d+)\s*)?$/i", $guestsText, $m)) {
            if (!empty($m['guestsCount'])) {
                $h->booked()->guests($m['guestsCount']);
            }

            if (!empty($m['kidsCount'])) {
                $h->booked()->kids($m['kidsCount']);
            }
        }

        // collect room type
        $roomType = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Room Type'))}]/following::text()[normalize-space()][1]");

        if (!empty($roomType)) {
            $h->addRoom()
                ->setType($roomType);
        }

        // collect phone
        $phoneText = $this->http->FindSingleNode("//text()[{$this->contains($this->t('to call us at'))}]/ancestor::td[1]", null, true, "/^.+?{$this->opt($this->t('to call us at'))}\s*(.+?)\.\s*$/");
        $h->hotel()->phone($phoneText);
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->parseHotel($parser, $email);
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

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(" or ", array_map(function ($s) {
            return "normalize-space(.)=\"{$s}\"";
        }, $field)) . ')';
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

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
    }
}
