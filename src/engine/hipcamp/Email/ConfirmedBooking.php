<?php

namespace AwardWallet\Engine\hipcamp\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class ConfirmedBooking extends \TAccountChecker
{
    public $mailFiles = "hipcamp/it-93230468.eml";
    public $subjects = [
        '/Confirmed\: Hipcamp booking \#\s*\d+\s*at/u',
    ];

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
            'View itinerary' => ['View itinerary', 'View Full Itinerary'],
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@hipcamp.com') !== false) {
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
        return $this->http->XPath->query("//text()[contains(normalize-space(), 'Hipcamp')]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('View itinerary'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Property address:'))}]")->length > 0;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]hipcamp\.com$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $h = $email->add()->hotel();

        $confirmation = $this->re("/(?:booking\s*[#]|Booking No\.)\s*(\d+)/u", $parser->getSubject());

        if (!empty($confirmation)) {
            $h->general()
                ->confirmation($confirmation);
        } elseif (empty($confirmation) && $this->http->XPath->query("//text()[contains(normalize-space(), 'None of your invites have been accepted yet')]")->length > 0) {
            $h->general()
                ->noConfirmation();
        }

        $status = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Your stay at')]", null, true, "/{$this->opt($this->t('Your stay at'))}.+is\s*(\w+)\./");

        if (!empty($status)) {
            $h->general()
                ->status($status);
        }

        $h->hotel()
            ->name($this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Check in:')]/preceding::text()[normalize-space()][1]"));

        $address = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'The address is:')]", null, true, "/{$this->opt($this->t('The address is:'))}\s*(.+)/");

        if (empty($address)) {
            $address = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Property address:')]", null, true, "/{$this->opt($this->t('Property address:'))}\s*(.+)/");
        }

        if (empty($address)) {
            $address = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Property address:')]/following::text()[normalize-space()][1]");
        }

        if (!empty($address) && stripos($address, 'GPS') == false) {
            $h->hotel()
                ->address($address);
        } else {
            $h->hotel()
                ->noAddress();
        }

        $h->booked()
            ->checkIn($this->normalizeDate($this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Check in:')]/following::text()[normalize-space()][1]")))
            ->checkOut($this->normalizeDate($this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Check out:')]/following::text()[normalize-space()][1]")));

        $h->booked()
            ->guests($this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Group size')]/following::text()[normalize-space()][1]", null, true, "/^(\d+)$/"));

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
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

    private function normalizeDate($str)
    {
        $in = [
            // Saturday, May 22, 2021 after 03:00 PM
            '/^\w+\,\s*(\w+)\s*(\d+)\,\s*(\d{4})\s*(?:before|after)\s*([\d\:]+\s*A?P?M)$/su',
        ];
        $out = [
            "$2 $1 $3, $4",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return strtotime($str);
    }
}
