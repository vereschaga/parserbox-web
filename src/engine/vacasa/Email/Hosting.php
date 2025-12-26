<?php

namespace AwardWallet\Engine\vacasa\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class Hosting extends \TAccountChecker
{
    public $mailFiles = "vacasa/it-67520701.eml";
    private $lang = '';
    private $reFrom = ['info@vacasa.com'];
    private $reProvider = ['Vacasa'];
    private $reSubject = [
        'Your trip to ',
    ];
    private $reBody = [
        'en' => [
            ['We\'re looking forward to hosting you at', 'Your trip to'],
            ['We looking forward to hosting you at', 'Your trip to'],
        ],
    ];
    private static $dictionary = [
        'en' => [
        ],
    ];

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();
        $this->logger->notice("Lang: {$this->lang}");
        $h = $email->add()->hotel();

        $confirmation = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Confirmation')]", null, true, "/\:\s*([A-Z\d]{8,})/");

        if (!empty($confirmation)) {
            $h->general()
                ->confirmation($confirmation);
        } else {
            $h->general()->noConfirmation();
        }

        $h->hotel()->name($this->http->FindSingleNode("(//text()[{$this->contains($this->t('looking forward to hosting you at'))}])[1]", null, true,
            "/{$this->opt($this->t('looking forward to hosting you at'))}\s*(.+?)\./"));

        $address = $this->http->FindSingleNode("(//a[{$this->contains($this->t('https://www.google.com/maps/place/'), '@href')}])[1]", null, true, '/^.{20,300}$/');

        if (empty($address)) {
            $address = $this->http->FindSingleNode("//img[contains(@alt, 'Get there')]/following::text()[normalize-space()][1]/ancestor::*[1]");
        }

        $h->hotel()->address($address);

        $checkIn = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Check in'))}]/ancestor::span[1]/following-sibling::p[1]");

        if (empty($checkIn)) {
            $checkIn = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Check in'))}]/ancestor::span[1]/following::p[1]");
        }
        $h->booked()->checkIn($this->normalizeDate($checkIn));

        $checkOut = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Check out'))}]/ancestor::span[1]/following-sibling::p[1]");

        if (empty($checkOut)) {
            $checkOut = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Check out'))}]/ancestor::span[1]/following::p[1]");
        }
        $h->booked()->checkOut($this->normalizeDate($checkOut));

        return $email;
    }

    public function detectEmailFromProvider($from)
    {
        return $this->arrikey($from, $this->reFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (self::detectEmailFromProvider($headers['from']) !== true) {
            return false;
        }

        return $this->arrikey($headers['subject'], $this->reSubject) !== false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//text()[{$this->contains($this->reProvider)}]")->length === 0) {
            return false;
        }

        if ($this->assignLang()) {
            return true;
        }

        return false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
    }

    private function assignLang()
    {
        foreach ($this->reBody as $lang => $values) {
            foreach ($values as $value) {
                if ($this->http->XPath->query("//text()[{$this->contains($value[0])}]")->length > 0
                    && $this->http->XPath->query("//text()[{$this->contains($value[1])}]")->length > 0) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function contains($field, $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'contains(normalize-space(' . $node . '),"' . $s . '")';
        }, $field)) . ')';
    }

    private function opt($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return '';
        }

        return '(?:' . implode('|', array_map(function ($s) {
            return preg_quote($s, '/');
        }, $field)) . ')';
    }

    private function arrikey($haystack, array $arrayNeedle)
    {
        foreach ($arrayNeedle as $key => $needles) {
            if (is_array($needles)) {
                foreach ($needles as $needle) {
                    if (stripos($haystack, $needle) !== false) {
                        return $key;
                    }
                }
            } else {
                if (stripos($haystack, $needles) !== false) {
                    return $key;
                }
            }
        }

        return false;
    }

    private function normalizeDate($str)
    {
        $in = [
            // 4:00 PM, Sunday, September 20, 2020
            '#^(\d+:\d+(?:\s*[AP]M)?), (\w+, \w+ \d+, \d{4})$#',
        ];
        $out = [
            '$2, $1',
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return strtotime($str, false);
    }
}
