<?php

namespace AwardWallet\Engine\priceline\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class HotelConfirmation extends \TAccountChecker
{
    public $mailFiles = "priceline/it-860788207.eml";

    public $lang = 'en';

    public $detectSubjects = [
        'en' => [
            'Thank you for choosing',
        ],
    ];

    public $detectBody = [
        'en' => [],
    ];

    public static $dictionary = [
        'en' => [],
    ];

    public function detectEmailFromProvider($from)
    {
        return preg_match("/[@.]priceline\.com$/", $from) > 0;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (empty($headers['from']) || stripos($headers['from'], 'fwd.priceline.com') === false) {
            return false;
        }

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
        if ($this->http->XPath->query("//text()[{$this->contains($this->t('Thank you for choosing'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->eq($this->t('Check-in Time:'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->starts($this->t('Subtotal*'))}]")->length > 0
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
        $confDesc = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Confirmation Number'))}]");
        $confNumber = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Confirmation Number'))}]/following::text()[normalize-space()][1]", null, true, "/^\s*(\d+)\s*$/");

        if (!empty($confDesc) && !empty($confNumber)) {
            $h->general()
                ->confirmation($confNumber, $confDesc);
        }

        // collect reservation status
        $status = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Your reservation is'))}]", null, true, "/^\s*{$this->opt($this->t('Your reservation is'))}\s*(\D+)\s*[!\.]\s*$/");

        if (!empty($status)) {
            $h->general()
                ->status($status);
        }

        // collect traveller name
        $travellerName = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Dear'))}]", null, true, "/^\s*{$this->opt($this->t('Dear'))}\s*([[:alpha:]][-.\'[:alpha:] ]*[[:alpha:]])\s*[,!]\s*$/u");

        if (!empty($travellerName)) {
            $h->general()
                ->traveller($travellerName, false);
        }

        // collect check-in and check-out dates
        $datePattern = "/^\s*(\w+\s+\d+\,\s+\d{4})\s*$/"; // January 28, 2025
        $timeOfClockPattern = "/^\s*(\d+(?:\s*\:\s*\d+)?\s*(?:[AP]M)?)(?:\s|$)/i";
        $dateCheckIn = strtotime($this->http->FindSingleNode("//text()[{$this->eq($this->t('Arrival Date'))}]/following::text()[normalize-space()][1]", null, true, $datePattern));
        $dateCheckOut = strtotime($this->http->FindSingleNode("//text()[{$this->eq($this->t('Departure Date'))}]/following::text()[normalize-space()][1]", null, true, $datePattern));
        $timeCheckIn = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Check-in Time:'))}]/following::text()[normalize-space()][1]", null, true, $timeOfClockPattern);
        $timeCheckOut = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Check-out Time:'))}]/following::text()[normalize-space()][1]", null, true, $timeOfClockPattern);

        if (!empty($dateCheckIn) && !empty($timeCheckIn)) {
            $h->booked()->checkIn(strtotime($timeCheckIn, $dateCheckIn));
        }

        if (!empty($dateCheckOut) && !empty($timeCheckOut)) {
            $h->booked()->checkOut(strtotime($timeCheckOut, $dateCheckOut));
        }

        // collect hotel name
        $hotelName = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Thank you for choosing'))}]", null, true, "/^\s*{$this->opt($this->t('Thank you for choosing'))}\s+(.+)\s+{$this->opt($this->t('for your upcoming stay.'))}.*$/");
        $h->hotel()->name($hotelName);

        //collect address and phone
        $hotelContacts = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Ready to book your next stay?'))}]/preceding::table[normalize-space()][1]");

        if (preg_match("/^\s*{$h->getHotelName()}\s+(?<address>.+?)\s+(?<phone>[+(\d][-+. \d)(]{5,}[\d)])\s*$/", $hotelContacts, $m)) {
            $h->hotel()
                ->address($m['address'])
                ->phone($m['phone']);
        }

        $r = $h->addRoom();

        // collect room type
        $roomType = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Room Type'))}]/following::text()[normalize-space()][1]", null, true, "/^\s*(\w+)\s*$/");

        if (!empty($roomType)) {
            $r->setType($roomType);
        }

        // collect room rate
        $roomRate = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Rate'))}]/following::text()[normalize-space()][1]", null, true, "/^\s*([\d\.\,\']+)\s*$/");

        if (!empty($roomRate)) {
            $r->setRate("{$roomRate} per night");
        }
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
