<?php

namespace AwardWallet\Engine\priceline\Email;

use AwardWallet\Schema\Parser\Email\Email;

class HotelCancellation extends \TAccountChecker
{
    public $mailFiles = "priceline/it-860112998.eml";

    public $lang = 'en';

    public $detectSubjects = [
        'en' => ['Booking cancelled for'],
    ];

    public $detectBody = [
        'en' => [],
    ];

    public static $dictionary = [
        'en' => [
            'adults' => ['adult', 'adults'],
        ],
    ];

    public function detectEmailFromProvider($from)
    {
        return preg_match("/[@.]priceline\.com$/", $from) > 0;
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

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        // detect Provider
        if (
            $this->http->XPath->query("//a/@href[{$this->contains(['priceline.com'])}]")->length === 0
            && stripos($parser->getHeader('subject'), 'Priceline') !== false
        ) {
            return false;
        }

        // detect Format
        if ($this->http->XPath->query("//text()[{$this->contains($this->t('Your booking has been successfully cancelled'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Email property'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->eq($this->t('You booked for'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->starts($this->t('PIN code'))}]")->length > 0
        ) {
            return true;
        }

        return false;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
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

    private function parseHotel(\PlancakeEmailParser $parser, Email $email): void
    {
        $h = $email->add()->hotel();

        // collect ota confirmation
        if (preg_match("/^\s*{$this->opt($this->t('Your Priceline itinerary'))}\s*\((?<desc>Trip ?#)\s*(?<number>[\d\-]{8,})\).*$/", $parser->getSubject(), $m)) {
            $email->ota()
                ->confirmation($m['number'], $m['desc']);
        }

        // collect reservation confirmation
        $confText = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Booking number'))}]/ancestor::tr[1]");

        if (preg_match("/^\s*(?<desc>{$this->opt($this->t('Booking number'))})\s*(?<number>\d{8,})\s*$/", $confText, $m)) {
            $h->general()
                ->confirmation($m['number'], $m['desc']);
        }

        if ($this->http->XPath->query("//text()[{$this->eq($this->t('CANCELLED'))}]")->length > 0) {
            $h->general()
                ->status('CANCELLED')
                ->cancelled();
        }

        // collect traveller name
        $travellerName = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Dear'))}]", null, true, "/^\s*{$this->opt($this->t('Dear'))}\s*([[:alpha:]][-.\'[:alpha:] ]*[[:alpha:]])\s*[,!]\s*$/u");

        if (!empty($travellerName)) {
            $h->general()
                ->traveller($travellerName, true);
        }

        // collect hotel name and address
        $h->hotel()
            ->name($this->http->FindSingleNode("//text()[{$this->eq($this->t('CANCELLED'))}]/preceding::tr[normalize-space()][1]"))
            ->address($this->http->FindSingleNode("//text()[{$this->eq($this->t('CANCELLED'))}]/preceding::td[normalize-space()][1]"));

        // collect phone
        $phone = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Phone:'))}]/ancestor::tr[1]", null, true, "/^\s*{$this->opt($this->t('Phone:'))}\s*([+(\d][-+. \d)(]{5,}[\d)])\s*$/");
        $h->hotel()->phone($phone);

        // collect guests count
        $guestsText = $this->http->FindSingleNode("//text()[{$this->eq($this->t('You booked for'))}]/following::text()[normalize-space()][1]");

        if (preg_match("/^\s*(?<guestsCount>\d+)\s*{$this->opt($this->t('adults'))}\s*$/", $guestsText, $m)) {
            $h->booked()->guests($m['guestsCount']);
        }

        // collect check-in and check-out
        $checkIn = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Check-in'))}]/following::text()[normalize-space()][1]", null, true, "/^\s*\w+\s*(\d+\s*\w+\s*\d{4})\s*$/");
        $checkOut = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Check-out'))}]/following::text()[normalize-space()][1]", null, true, "/^\s*\w+\s*(\d+\s*\w+\s*\d{4})\s*$/");

        if (!empty($checkIn)) {
            $h->booked()->checkIn(strtotime($checkIn));
        }

        if (!empty($checkOut)) {
            $h->booked()->checkOut(strtotime($checkOut));
        }

        $pinCode = $this->http->FindSingleNode("//text()[{$this->eq($this->t('PIN code'))}]/ancestor::tr[1]", null, true, "/^\s*({$this->opt($this->t('PIN code'))}\s*\d+)\s*$/");

        if (!empty($pinCode)) {
            $h->general()->notes($pinCode);
        }
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
