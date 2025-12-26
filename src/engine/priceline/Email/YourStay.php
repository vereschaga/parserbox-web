<?php

namespace AwardWallet\Engine\priceline\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class YourStay extends \TAccountChecker
{
    public $mailFiles = "priceline/it-858327878.eml";

    public $lang = 'en';

    public $detectSubjects = [
        'en' => ['Complete Your Pre-Arrival Check-In Now'],
    ];

    public $detectBody = [
        'en' => [],
    ];

    public static $dictionary = [
        'en' => [],
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
        if ($this->http->XPath->query("//text()[{$this->starts($this->t('Your Stay'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('pre-arrival'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->eq($this->t('Sincerely,'))}]")->length > 0
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

        // collect main hotel info
        $mainContent = implode("\n", $this->http->FindNodes("//text()[{$this->contains($this->t('Confirmation'))}]/ancestor::td[1]/descendant::text()[normalize-space()]"));

        // collect reservation confirmation
        if (preg_match("/^\s*(?<desc>{$this->opt($this->t('Confirmation'))})[\:\s]*(?<number>\w{8,})\s*$/im", $mainContent, $m)) {
            $h->general()->confirmation($m['number'], $m['desc']);
        }

        // collect traveller
        $traveller = $this->re("/^\s*{$this->opt($this->t('Name'))}[\:\s]*([[:alpha:]][-.\/\'’[:alpha:] ]*[[:alpha:]])\s*$/im", $mainContent);

        if (!empty($traveller)) {
            $h->general()->traveller($traveller, true);
        }

        // collect check-in and check-out dates
        $datePattern = "\w+\s\d+\,\s+\d{4}";

        if (preg_match("/^\s*{$this->opt($this->t('Your Stay'))}[\:\s]*(?<checkIn>$datePattern)\s*\-\s*(?<checkOut>$datePattern)\s*$/im", $mainContent, $m)) {
            $h->booked()
                ->checkIn(strtotime($m['checkIn']))
                ->checkOut(strtotime($m['checkOut']));
        }

        // collect phone number
        if (preg_match("/\s*{$this->opt($this->t('call at:'))}\n\s*([+\-()\d\s]+?)\s*$/m", $mainContent, $m)) {
            $h->hotel()->phone($m[1]);
        }

        // collect hotel name and address
        $h->hotel()
            ->name($this->re("/\s*{$this->opt($this->t('Sincerely,'))}\n\s*(.+)\s*$/m", $mainContent))
            ->address($this->http->FindSingleNode("//text()[{$this->starts($h->getHotelName())} and {$this->contains($this->t('|'))}]", null, true, "/[|]\s*(.+?)\s*$/"));
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

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
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
