<?php

namespace AwardWallet\Engine\tapportugal\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class LeftForYourTrip extends \TAccountChecker
{
    public $mailFiles = "tapportugal/it-73649513.eml, tapportugal/it-73850730.eml, tapportugal/it-73853884.eml";
    public static $dictionary = [
        'en' => [
            //            'Booking Code' => '',
            //            'Hello,' => '',
            'Duration' => 'Duration',
            //            '' => '',
        ],
        'pt' => [
            'Booking Code' => ['Código de Reserva', 'Booking Code', 'Código de reserva'],
            'Hello,'       => ['Olá,', 'Hello,'],
            'Duration'     => 'Duração',
        ],
    ];

    private $detectFrom = 'flytap.com';
    private $detectSubject = [
        // en
        'Booking',
        // pt
        'Reserva',
    ];
    private $detectBody = [
        'en' => ['Time left'],
        'pt' => ['Faltam'],
    ];

    private $lang = 'en';

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        foreach (self::$dictionary as $lang => $dict) {
            if (isset($dict['Duration']) && $this->http->XPath->query("//*[" . $this->eq($dict['Duration']) . "]")->length > 0) {
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
        if (!isset($headers['from']) || self::detectEmailFromProvider($headers['from']) === false) {
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
        if ($this->http->XPath->query("//a[contains(@href, 'flytap.com')]")->length === 0) {
            return false;
        }

        foreach ($this->detectBody as $dBody) {
            if ($this->http->XPath->query("//*[" . $this->eq($dBody) . "]")->length > 0) {
                return true;
            }
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

    private function parseHtml(Email $email)
    {
        $f = $email->add()->flight();

        // General
        $f->general()
            ->confirmation($this->http->FindSingleNode("//text()[" . $this->eq($this->t("Booking Code")) . "]/following::text()[normalize-space()][1]", null, true,
                "/^\s*([A-Z\d]{5,7})\s*$/"),
                $this->http->FindSingleNode("//text()[" . $this->eq($this->t("Booking Code")) . "]"))
            ->traveller(trim($this->http->FindSingleNode("//text()[" . $this->starts($this->t("Hello,")) . "]", null, true,
                "/" . $this->preg_implode($this->t("Hello,")) . "\s*([[:alpha:] \-]+)\s*[.!]?\s*$/u")), false);

        // Segments
        $xpath = "//text()[" . $this->eq($this->t("Duration")) . "]/ancestor::tr[1]";
//        $this->logger->debug($xpath);
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $s = $f->addSegment();

            $date = null;

            $column = 1;

            // Airline
            $node = implode(" ", $this->http->FindNodes("./td[normalize-space()][{$column}]//text()[normalize-space()]", $root));

            if (preg_match("/^\s*(?<al>[A-Z\d][A-Z]|[A-Z][A-Z\d]) (?<fl>\d{1,5})\s+(?<date>.+)/", $node, $m)) {
                $s->airline()
                    ->name($m['al'])
                    ->number($m['fl'])
                ;
                $date = $m['date'];
            } elseif (preg_match("/^\s*(?<al>[A-Z\d][A-Z]|[A-Z][A-Z\d]) (?<fl>\d{1,5})\s*$/", $node, $m)) {
                $s->airline()
                    ->name($m['al'])
                    ->number($m['fl'])
                ;
                $column++;
                $date = $this->http->FindSingleNode("./td[normalize-space()][{$column}]/descendant::text()[normalize-space()][1]", $root);
            }
            $column++;

            // Departure
            $node = implode(" ", $this->http->FindNodes("./td[normalize-space()][{$column}]//text()[normalize-space()]", $root));

            if (preg_match("/^\s*(?<time>\d{1,2}h\d{2})\s*(?<code>[A-Z]{3})\s*$/", $node, $m)) {
                $s->departure()
                    ->code($m['code'])
                    ->date((!empty($date)) ? $this->normalizeDate($date . ', ' . $m['time']) : null);
            }
            $column++;

            // Arrival
            $node = implode(" ", $this->http->FindNodes("./td[normalize-space()][{$column}]//text()[normalize-space()]", $root));

            if (preg_match("/^\s*(?<time>\d{1,2}h\d{2})\s*(?<code>[A-Z]{3})\s*$/", $node, $m)) {
                $s->arrival()
                    ->code($m['code'])
                    ->date((!empty($date)) ? $this->normalizeDate($date . ', ' . $m['time']) : null);
            }
            $column++;

            // Extra
            $s->extra()
                ->duration($this->http->FindSingleNode("./td[normalize-space()][{$column}]/descendant::text()[normalize-space()][1]", $root));
        }

        return $email;
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function normalizeDate($date)
    {
//        $this->logger->debug('$date = '.print_r( $date,true));
        $in = [
            // 13 Dec 2020, 18h30
            "/^\s*(\d{1,2})\s+([[:alpha:]]+)\s+(\d{4})\s*,\s*(\d{1,2})h(\d{2})\s*$/iu",
        ];
        $out = [
            "$1 $2 $3, $4:$5",
        ];
        $date = preg_replace($in, $out, $date);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $date, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $date = str_replace($m[1], $en, $date);
            }
        }

        return strtotime($date);
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'normalize-space(.)="' . $s . '"'; }, $field)) . ')';
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'starts-with(normalize-space(.),"' . $s . '")'; }, $field)) . ')';
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'contains(normalize-space(.),"' . $s . '")'; }, $field)) . ')';
    }

    private function preg_implode($field)
    {
        if (!is_array($field)) {
            $field = [$field];
        }

        return '(?:' . implode("|", array_map(function ($v) {return preg_quote($v, '/'); }, $field)) . ')';
    }
}
