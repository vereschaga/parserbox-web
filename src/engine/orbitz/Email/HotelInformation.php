<?php

namespace AwardWallet\Engine\orbitz\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class HotelInformation extends \TAccountChecker
{
    public $mailFiles = "orbitz/it-83253154.eml";
    public $subjects = [
        '/Your trip to \w+\. Here\'s your itinerary/',
    ];

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@ac.orbitz.com') !== false) {
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
        return $this->http->XPath->query("//text()[contains(normalize-space(), 'Orbitz')]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Your hotel information'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Itinerary #'))}]")->length > 0;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]ac\.orbitz\.com$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $h = $email->add()->hotel();

        $h->general()
            ->confirmation($this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Itinerary #:')]", null, true, "/{$this->opt($this->t('Itinerary #:'))}\s*(\d+)/"))
            ->traveller($this->http->FindSingleNode("//a[normalize-space()='Cars']/following::a[1]/preceding::text()[contains(normalize-space(), 'use')][1]", null, true, "/^(\D+)\,\s*/"), false);

        $h->hotel()
            ->name($this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Itinerary #')]/ancestor::tr[1]/preceding::tr[2]"))
            ->address($this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Itinerary #')]/ancestor::tr[1]/preceding::tr[1]"));

        $h->booked()
            ->checkIn(strtotime($this->http->FindSingleNode("//text()[normalize-space()='Check-in']/following::text()[normalize-space()][1]")))
            ->checkOut(strtotime($this->http->FindSingleNode("//text()[normalize-space()='Check-out']/following::text()[normalize-space()][1]")));

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
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
