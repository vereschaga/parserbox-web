<?php

namespace AwardWallet\Engine\hopper\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class BookedCancellation extends \TAccountChecker
{
    public $mailFiles = "hopper/it-89489123.eml";
    public $subjects = [
        '/Your E-ticket[\s\d\-]+for Your Hopper Booking[\sA-Z]+is Now Resolved/',
    ];

    public $lang = 'en';
    public $date;

    public static $dictionary = [
        "en" => [
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@hopper.com') !== false) {
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
        return $this->http->XPath->query("//text()[contains(normalize-space(), 'Hopper')]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Future Travel Credit'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('your cancelation request'))}]")->length > 0;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]hopper\.com$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $f = $email->add()->flight();

        $f->general()
            ->confirmation($this->http->FindSingleNode("//text()[contains(normalize-space(), 'Future Travel Credit')]/following::text()[starts-with(normalize-space(), 'Hopper')][1]", null, true, "/{$this->opt($this->t('Hopper'))}\s*\(([A-Z\d]+)\)/u"));

        $info = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Hi')]/preceding::text()[normalize-space()][1]");

        if (preg_match("/(\D+)\s\(([\d\-]+)\)/", $info, $m)) {
            $f->general()
                ->traveller($m[1]);
            $f->issued()
                ->ticket($m[2], false);
        }

        if ($this->http->XPath->query("//text()[contains(normalize-space(), 'your cancelation request')]")->length > 0) {
            $f->general()
                ->cancelled();
        }

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

    private function normalizeDate($str)
    {
        $year = date('Y', $this->date);
        $in = [
            // Sep 19, 05:30 AM
            "#^(\w+)\s*(\d+)\,\s*([\d\:]+\s*A?P?M)$#u",
        ];
        $out = [
            "$2 $1 $year, $3",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return strtotime($str);
    }

    private function normalizeCurrency(string $string)
    {
        $string = trim($string);
        $currencies = [
            'GBP' => ['£'],
            'EUR' => ['€'],
            'INR' => ['Rs.', 'Rs'],
            'USD' => ['US$'],
        ];

        foreach ($currencies as $code => $currencyFormats) {
            foreach ($currencyFormats as $currency) {
                if ($string === $currency) {
                    return $code;
                }
            }
        }

        return null;
    }
}
