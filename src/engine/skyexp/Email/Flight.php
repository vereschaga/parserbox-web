<?php

namespace AwardWallet\Engine\skyexp\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class Flight extends \TAccountChecker
{
    public $mailFiles = "skyexp/it-109439916.eml, skyexp/it-109440994.eml";
    public $subjects = [
        'Skyexpress.gr flight confirmation',
    ];

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@skyexpress.gr') !== false) {
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
        if (stripos($parser->getBody(), 'www.skyexpress.gr') !== false
            || stripos($parser->getBody(), 'Passport for the Sky Express check-in process') !== false) {
            return $this->http->XPath->query("//text()[contains(normalize-space(), 'Receipt and Itinerary as of')]")->length > 0;
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]skyexpress\.gr$/', $from) > 0;
    }

    public function ParseFlight(Email $email, $text)
    {
        $f = $email->add()->flight();

        $f->general()
            ->confirmation($this->re("/{$this->opt($this->t('Confirmation Number:'))}\s*([A-Z]+)/u", $text), 'Confirmation Number')
            ->date(strtotime($this->re("/{$this->opt($this->t('Receipt and Itinerary as of'))}\s*\w+[\s\-]+(\d+\s*\w+\s*\d{4}\s*[\d\:]+)/", $text)));

        $passText = $this->re("/Payment Summary\n+(.+)Voucher reference:/s", $text);

        if (!empty($passText)) {
            if (preg_match_all("/(?:\n+|^)([A-Z\s]{3,})\n/u", $passText, $m)) {
                $f->general()
                    ->travellers(array_unique($m[1]));
            }
        } elseif (preg_match_all("/Passengers Fare\/Taxes and Fees Total\n\s*([A-Z\s]+\n)/", $text, $m)) {
            $f->general()
                ->travellers(array_unique($m[1]));
        }

        $cabin = '';

        if (preg_match_all("/Adult\s*\(\s*(\w+)\)/", $text, $m)) {
            if (count(array_unique($m[1])) == 1) {
                $cabin = array_unique($m[1])[0];
            }
        }

        /*GQ - 460 Eleftherios Venizelos Airport ( ATH) Karpathos Airport ( AOK) 1 Hour 10 Minute s Mon - 20 Sep 2021 06:55 Mon - 20 Sep 2021 08:05*/
        if (preg_match_all("/(?<aName>[A-Z\d]{2})[\s\-]+(?<fNumber>\d{2,4}).+\(\s*(?<depCode>[A-Z]{3})\).+\(\s*(?<arrCode>[A-Z]{3})\)\s*(?<duration>\d+\s*Hour\s*\d+\s*Minute)\D+(?<depDate>\d+\s*\w+\s*\d{4}\s*[\d\:]+)\s*\w+[\s\-]+(?<arrDare>\d+\s*\w+\s*\d{4}\s*[\d\:]+)/u", $text, $match)
            /*GQ - 461
            Karpathos Airport  ( AOK)
            Sun - 19  Sep   2021  16:30 Eleftherios Venizelos Airport  ( ATH)
            Sun - 19  Sep   2021  17:40 1 Hour   10 Minute s*/
            || preg_match_all("/\n\s*(?<aName>[A-Z\d]{2})[\s\-]+(?<fNumber>\d{2,4})\s*\n.+\(\s*(?<depCode>[A-Z]{3})\)\s*\n\s*\w+[\s\-]+(?<depDate>\d+\s*\w+\s*\d{4}\s*[\d\:]+).+\(\s*(?<arrCode>[A-Z]{3})\)\s*\n\s*\w+[\s\-]+(?<arrDare>\d+\s*\w+\s*\d{4}\s*[\d\:]+)\s*(?<duration>\d+\s*Hour\s*\d+\s*Minute)/u", $text, $match)) {
            foreach ($match[1] as $key => $m) {
                $s = $f->addSegment();

                $s->airline()
                    ->name($match['aName'][$key])
                    ->number($match['fNumber'][$key]);

                $s->departure()
                    ->date(strtotime($match['depDate'][$key]))
                    ->code($match['depCode'][$key]);

                $s->arrival()
                    ->date(strtotime($match['arrDare'][$key]))
                    ->code($match['arrCode'][$key]);

                if (!empty($cabin)) {
                    $s->extra()
                        ->cabin($cabin);
                }
            }
        }

        if (preg_match("/([\d\.]+)\s*([A-Z]{3})\s*Reservation Totals/", $text, $m)) {
            $f->price()
                ->total($m[1])
                ->currency($m[2]);
        }
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $text = $parser->getBody();

        $this->ParseFlight($email, $text);

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

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }
}
