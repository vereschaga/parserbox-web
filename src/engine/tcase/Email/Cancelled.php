<?php

namespace AwardWallet\Engine\tcase\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class Cancelled extends \TAccountChecker
{
    public $mailFiles = "tcase/it-58184557.eml, tcase/it-58184606.eml, tcase/it-58184617.eml, tcase/it-58616894.eml";
    public static $dictionary = [
        "en" => [
            //            "Hi " => "",
            //            "Booking confirmation:" => "",
            //            "CANCELLED" => "",
        ],
        "es" => [
            "Hi "                   => "Hola, ",
            "Booking confirmation:" => "Localizador de reserva:",
            "CANCELLED"             => "CANCELADO",
        ],
        "pt" => [
            "Hi "                   => "Olá,",
            "Booking confirmation:" => "Confirmação da reserva:",
            "CANCELLED"             => "CANCELADO",
        ],
    ];

    private $detectFrom = 'tripcase.';

    private $detectSubject = [
        'en' => 'Compensation for your cancelled flight to',
        'Reminder about your cancelled flight to',
        'es' => 'Compensación por tu vuelo',
        'pt' => 'Compensação pelo seu voo cancelado para',
    ];

    private $detectBody = [
        'en' => ['has been reported as cancelled.'],
        'es' => ['ha sido retrasado.', 'ha sido cancelado.'],
        'pt' => ['consta como cancelado.', 'consta como atrasado.', 'Confirmação da reserva'],
    ];

    private $lang = 'en';

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        foreach ($this->detectBody as $lang => $body) {
            if ($this->http->XPath->query('//*[' . $this->contains($body) . ']')->length > 0) {
                $this->lang = $lang;

                break;
            }
        }
        $this->parseHtml($email);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->detectFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (stripos($headers['from'], $this->detectFrom) === false) {
            return false;
        }

        foreach ($this->detectSubject as $dSubject) {
            if (strpos($headers['subject'], $dSubject) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query('//a[' . $this->contains(['tripcase'], '@href') . '] | //img[' . $this->contains(['tripcase'], '@src') . ']')->length === 0) {
            return false;
        }

        foreach ($this->detectBody as $detectBody) {
            if ($this->http->XPath->query('//*[' . $this->contains($detectBody) . ']')->length > 0) {
                return true;
            }
        }

        return false;
    }

    public static function getEmailTypesCount()
    {
        return 1;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public function IsEmailAggregator()
    {
        return false;
    }

    private function parseHtml(Email $email)
    {
        $f = $email->add()->flight();

        // General
        $confs = array_filter(preg_split("#\s*,\s*#", $this->http->FindSingleNode("//text()[" . $this->eq($this->t("Booking confirmation:")) . "]/following::text()[normalize-space()][1]", null, true, "#^\s*\#\s*(.+)#")));

        foreach ($confs as $conf) {
            $f->general()->confirmation($conf);
        }
        $tr = $this->http->FindSingleNode("//text()[" . $this->starts($this->t("Hi ")) . "][1]", null, true, "#^" . $this->opt($this->t("Hi ")) . "\s*(([^\W\d]+[ \-]*)+)[,:]?\s*$#u");
        $f->general()
            ->traveller($tr, false);

        // Segments

        $xpath = "//img[contains(@src, '/plane')]/ancestor::tr[normalize-space()][1]";
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $s = $f->addSegment();

            // Airline
            $s->airline()
                ->noNumber()
                ->noName()
            ;

            // Depart
            $depart = implode("\n", $this->http->FindNodes("./*[normalize-space()][1]//text()[normalize-space()]", $root));

            if (preg_match("#(?<name>.+?)\s*\((?<code>[A-Z]{3})\)\s+(?<time>.+)#", $depart, $m)) {
                $s->departure()
                    ->code($m['code'])
                    ->name($m['name'])
                    ->date($this->normalizeDate($m['time']))
                ;
            }

            // Arrival
            $arrival = implode("\n", $this->http->FindNodes("./*[normalize-space()][last()]//text()[normalize-space()]", $root));

            if (preg_match("#(?<name>.+?)\s*\((?<code>[A-Z]{3})\)\s+(?<time>.+)#", $arrival, $m)) {
                $s->arrival()
                    ->code($m['code'])
                    ->name($m['name'])
                    ->date($this->normalizeDate($m['time']))
                ;
            }

            if (!empty($this->http->FindSingleNode("./preceding::text()[normalize-space()][1][" . $this->contains($this->t("CANCELLED")) . "]", $root))) {
                $s->extra()->status($this->t("CANCELLED"));
                $s->extra()->cancelled();
            }
        }
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function eq($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'normalize-space(.)="' . $s . '"'; }, $field)) . ')';
    }

    private function starts($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'starts-with(normalize-space(.),"' . $s . '")'; }, $field)) . ')';
    }

    private function contains($field, $text = 'normalize-space(.)'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($text) { return 'contains(' . $text . ',"' . $s . '")'; }, $field)) . ')';
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode('|', array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
    }

    private function normalizeDate($date)
    {
//        $this->logger->debug("Date: {$date}");
        $in = [
            "#^\s*(\d{1,2})/(\d{1,2})/(\d{4})\s+(\d{1,2}:\d{2}(?: ?[ap]m)?)\s*$#ui", //25/06/2019 11:55AM
        ];
        $out = [
            "$1.$2.$3, $4",
        ];
        $date = preg_replace($in, $out, $date);

//        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $date, $m)) {
//            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
//                $date = str_replace($m[1], $en, $date);
//            }
//        }
        return strtotime($date);
    }
}
