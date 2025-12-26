<?php

namespace AwardWallet\Engine\hipcamp\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class CancelledBooking extends \TAccountChecker
{
    public $mailFiles = "emails2parse/it-92581177.eml";
    public $subjects = [
        '/Cancellation confirmation for Hipcamp booking \#\s*\d+\s*at/u',
    ];

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
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
            && $this->http->XPath->query("//text()[{$this->contains($this->t('View receipt'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('You successfully canceled your booking'))}]")->length > 0;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]hipcamp\.com$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $h = $email->add()->hotel();

        $h->general()
            ->confirmation($this->re("/for Hipcamp booking\s*[#]\s*(\d+)/u", $parser->getSubject()));

        if ($this->http->XPath->query("//text()[contains(normalize-space(), 'You successfully canceled your booking')]")->length > 0) {
            $h->general()
                ->cancelled()
                ->status('canceled');
        }

        $h->hotel()
            ->name($this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Your booking at')]/following::a[normalize-space()][1]"));

        $dataText = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Your booking at') and contains(normalize-space(), 'for')]");

        if (preg_match("/for\s*(\d{4})\-(\d+)\-(\d+)/", $dataText, $m)) {
            $h->booked()
                ->checkIn(strtotime($m[3] . '.' . $m[2] . '.' . $m[1]))
                ->noCheckOut();
        }

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
