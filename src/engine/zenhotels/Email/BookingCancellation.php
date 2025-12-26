<?php

namespace AwardWallet\Engine\zenhotels\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class BookingCancellation extends \TAccountChecker
{
    public $mailFiles = "zenhotels/it-182625017.eml";
    public $subjects = [
        'Booking cancellation',
    ];

    public $lang = '';
    public $subject = '';

    public $detectLang = [
        'en' => ['Your booking'],
    ];

    public static $dictionary = [
        "en" => [
        ],

        "pt" => [
        ],

        "de" => [
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@news.zenhotels.com') !== false) {
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
        $this->assignLang();

        if ($this->http->XPath->query("//text()[contains(normalize-space(), 'Zenhotels')]")->length > 0
            || $this->http->XPath->query("//text()[contains(normalize-space(), 'support@zenhotels.com')]")->length > 0) {
            return $this->http->XPath->query("//text()[{$this->contains($this->t('Your booking has been cancelled'))}]")->length > 0
                && $this->http->XPath->query("//text()[{$this->contains($this->t('View hotel'))}]")->length > 0;
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]news\.zenhotels\.com$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();
        $this->subject = $parser->getSubject();
        $this->ParseHotel($email);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function ParseHotel(Email $email)
    {
        $h = $email->add()->hotel();

        $h->general()
            ->confirmation($this->re("/[№]\s*([A-Z\d\-\/]+)/", $this->subject))
            ->traveller($this->http->FindSingleNode("//text()[{$this->starts($this->t('Dear'))}]", null, true, "/^{$this->opt($this->t('Dear'))}\s*(\D+)\,/"), true);

        if ($this->http->XPath->query("//text()[{$this->eq($this->t('Your booking has been cancelled'))}]")->length > 0) {
            $h->general()
                ->cancelled();
        }

        $h->hotel()
            ->name($this->http->FindSingleNode("//text()[{$this->eq($this->t('View hotel'))}]/preceding::text()[normalize-space()][2]"))
            ->address($this->http->FindSingleNode("//text()[{$this->eq($this->t('View hotel'))}]/preceding::text()[normalize-space()][3]"));

        $chekInText = $this->http->FindSingleNode("//text()[{$this->eq($this->t('View hotel'))}]/preceding::text()[normalize-space()][4]");

        if (preg_match("/^(?<dayIn>\d+)[\s\-]+(?<dayOut>\d+)\s*(?<month>\w+)\,\s*(?<year>\d{4})$/", $chekInText, $m)) {
            $h->booked()
                ->checkIn($this->normalizeDate($m['dayIn'] . ' ' . $m['month'] . ' ' . $m['year']))
                ->checkOut($this->normalizeDate($m['dayOut'] . ' ' . $m['month'] . ' ' . $m['year']));
        }

        $roomType = $this->http->FindSingleNode("//text()[{$this->eq($this->t('View hotel'))}]/preceding::text()[normalize-space()][1]");

        if (!empty($roomType)) {
            $room = $h->addRoom();
            $room->setType($roomType);
        }
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
    }

    protected function eq($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false';
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

    private function normalizeDate($str)
    {
        $in = [
            "#^\D+\,\s*(\d+\s*\D+\s*\d{4})\D+([\d\:]+)$#u", //Thu, 25 August 2022 from 12:00
        ];
        $out = [
            "$1, $2",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = \AwardWallet\Engine\MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return strtotime($str);
    }

    private function assignLang()
    {
        foreach ($this->detectLang as $lang => $words) {
            foreach ($words as $word) {
                if ($this->http->XPath->query("//*[{$this->contains($word)}]")->length > 0) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
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
