<?php

namespace AwardWallet\Engine\hertz\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class It1903741 extends \TAccountChecker
{
    public $mailFiles = "hertz/it-151271333.eml, hertz/it-1680452.eml, hertz/it-1680522.eml, hertz/it-1898144.eml, hertz/it-1903741.eml, hertz/it-37438953.eml";

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
            'Your reservation confirmation number is'     => ['Your reservation confirmation number is', 'Confirmation Number', 'Your Confirmation Number is'],
            'Thanks for Travelling at the Speed of Hertz' => ['Thanks for Travelling at the Speed of Hertz', 'Thanks for Traveling at the Speed of Hertz™'],
            'Total'                                       => ['Total', 'Total Approximate Charge', 'Total Estimated Charge'],
            'Pick Up time'                                => ['Pick Up time', 'Pickup Time', 'Pick-up Time'],
            'Return time'                                 => ['Return time', 'Return Time'],
            'YOUR VEHICLE'                                => ['YOUR VEHICLE', 'Your selected car class', 'Your Vehicle'],
            'PAYMENT METHOD'                              => ['PAYMENT METHOD', 'Details', 'Payment Method'],
        ],
    ];

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $body = $parser->getPlainBody();

        $r = $email->add()->rental();

        $r->general()
            ->confirmation($this->re("/{$this->opt($this->t('Your reservation confirmation number is'))}\s*\:\s*([A-Z\d]+)/u", $body), 'confirmation number');

        $traveller = $this->re("/\s*{$this->opt($this->t('Thanks for Travelling at the Speed of Hertz'))}\,\s+(\D+)(?:\!|\n)/u", $body);

        if (!empty($traveller)) {
            $r->general()
                ->traveller($traveller);
        }

        if (stripos($body, "Pickup and Return Location") !== false) {
            $r->pickup()
                ->location(preg_replace("/\s+/", " ", str_replace("Address", " ", $this->re("/\n\s*Pickup and Return Location[.\s:]+(.*?)\s+(?:Hours of Operation|Location Type)/ims", $body))))
                ->openingHours($this->re("/\s+Hours of Operation\s*:*\s*([^\n]+?)[ ]*(?:Location Type|Phone Number|$)/m", $body))
                ->phone($this->re("/\s+Phone Number[\s:]+([+(\d][-. \d)(]{5,}[\d)])/", $body))
                ->date(strtotime($this->normalizeDate(trim($this->re("/\n\s*{$this->opt($this->t('Pick Up time'))}\s*\:?\s*([^\n]+)/u", $body)))));

            $fax = $this->re("/\s+Fax Number[:\s]+([+(\d][-. \d)(]{5,}[\d)])/", $body);

            if (!empty($fax)) {
                $r->pickup()
                    ->fax($fax);
            }

            $r->dropoff()
                ->date(strtotime($this->normalizeDate(trim($this->re("/\n\s*{$this->opt($this->t('Return time'))}\s*\:*\s*([^\n]+)/u", $body)))))
                ->same();
        } else {
            if (preg_match("/Pick-up Location.+Address\n(?<adress>.+)Hours of Operation:\n(?<hours>.+)Location Type:.+Phone Number:\n(?<phone>.+)Fax Number:\n(?<fax>.+)Driving Instructions.+Return Location/su", $body, $m)) {
                $r->pickup()
                    ->date(strtotime($this->normalizeDate(trim($this->re("/\n\s*{$this->opt($this->t('Pick Up time'))}\s*\:?\s*([^\n]+)/u", $body)))))
                    ->location(str_replace("\n", " ", $m['adress']))
                    ->openingHours($m['hours'])
                    ->phone($m['phone'])
                    ->fax($m['fax']);
            }

            if (preg_match("/Return Location.+Address\n(?<adress>.+)Hours of Operation:\n(?<hours>.+)Location Type:.+Phone Number:\n(?<phone>.+)Fax Number:\n(?<fax>.+)Driving Instructions/su", $body, $m)) {
                $r->dropoff()
                    ->date(strtotime($this->normalizeDate(trim($this->re("/\n\s*{$this->opt($this->t('Return time'))}\s*\:*\s*([^\n]+)/u", $body)))))
                    ->location(str_replace("\n", " ", $m['adress']))
                    ->openingHours($m['hours'])
                    ->phone($m['phone'])
                    ->fax($m['fax']);
            }
        }

        if (preg_match("/\s*{$this->opt($this->t('YOUR VEHICLE'))}\s*\n(.+)\n(.+)\n{$this->opt($this->t('PAYMENT METHOD'))}/u", $body, $m)) {
            $r->car()
                ->type($m[1])
                ->model($m[2]);
        } elseif (preg_match("/Your Vehicle\:?\s*\n(.+or similar)\n(.+)\s*\n\s*The total rate/u", $body, $m)) {
            $r->car()
                ->type($m[2])
                ->model($m[1]);
        }

        $r->price()
            ->total($this->re("/{$this->opt($this->t('Total'))}\s*([\d\.]+)\s*[A-Z]{3}/u", $body))
            ->currency($this->re("/{$this->opt($this->t('Total'))}\s*[\d\.]+\s*([A-Z]{3})/u", $body));

        return $email;
    }

    public function detectEmailFromProvider($from)
    {
        return strpos($from, 'Hertz Reservations') !== false
            || stripos($from, '@hertz.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return preg_match('/\b(?:My|Your) Hertz Reservation [A-Z\d]{5,}\b/', $headers['subject']) > 0
            || preg_match('/\bHertz-[[:alpha:]][-.\'[:alpha:] ]*[[:alpha:]] - [A-Z\d]{5,}\b/u', $headers['subject']) > 0;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if (empty($textBody = $parser->getPlainBody())) {
            $textBody = $parser->getHTMLBody();
        }

        if (self::detectEmailFromProvider($parser->getHeader('from')) !== true
            && strpos($textBody, 'Thanks for Travelling at the Speed of Hertz') === false
            && strpos($textBody, 'Thanks for Traveling at the Speed of Hertz') === false
            && strpos($textBody, 'The Hertz Corporation') === false
            && strpos($textBody, 'www.hertz.co') === false
            && strpos($textBody, 'Supplier: Hertz') === false
        ) {
            return false;
        }

        if (strpos($textBody, 'Pickup and Return Location') !== false) {
            return true;
        }

        if (strpos($textBody, 'Pick-up Location') !== false) {
            return true;
        }

        return false;
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) {
            return 'normalize-space(.)="' . $s . '"';
        }, $field)) . ')';
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) {
            return 'starts-with(normalize-space(.),"' . $s . '")';
        }, $field)) . ')';
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) {
            return 'contains(normalize-space(.),"' . $s . '")';
        }, $field)) . ')';
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
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
            "#^\w+\,\s*(\d+)\s*(\w+)\,\s*(\d{4})\s*at\s*([\d\:]+\s*A?P?M?)$#", //Wed, 30 Dec, 2020 at 16:40
            "#^\w+\,\s*(\w+)\s*(\d+)\s*\,\s*(\d{4})\s*at\s*([\d\:]+\s*A?P?M)$#", //Fri, May 23, 2014  at 02:00 PM
        ];
        $out = [
            "$1 $2 $3, $4",
            "$2 $1 $3, $4",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return $str;
    }
}
