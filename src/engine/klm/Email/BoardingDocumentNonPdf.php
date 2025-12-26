<?php

namespace AwardWallet\Engine\klm\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class BoardingDocumentNonPdf extends \TAccountChecker
{
    public $mailFiles = "klm/it-8916847.eml, klm/it-28175281.eml";

    public static $dictionary = [
        'es' => [
            'Departure' => 'Salida',
        ],
        'nl' => [
            'Departure' => 'Vertrek',
        ],
        'en' => [],
        'fr' => [
            'Departure' => 'Départ',
        ],
    ];

    public $lang = "en";
    public $date;

    private $reSubject = [
        'es' => 'Su(s) documento(s) de embarque de KLM',
        'nl' => 'Uw KLM-boardingpass',
        'en' => 'Your KLM boarding document(s)',
        'fr' => 'Votre/vos document(s) d’’embarquement KLM',
    ];

    private $reBody = 'www.klm.com';

    private $reBody2 = [
        'es' => 'Información sobre embarque',
        'nl' => 'Boardinginformatie',
        'en' => 'Boarding information',
        'fr' => 'Information sur l’embarquement',
    ];

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@klm.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (self::detectEmailFromProvider($headers['from']) !== true) {
            return false;
        }

        foreach ($this->reSubject as $re) {
            if (stripos($headers["subject"], $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        if (strpos($body, $this->reBody) === false) {
            return false;
        }

        foreach ($this->reBody2 as $re) {
            if (strpos($body, $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $pdfs = $parser->searchAttachmentByName(".*\.pdf");

        if (isset($pdfs[0])) {
            return null;
        }

        $this->date = strtotime($parser->getHeader('date'));

        foreach ($this->reBody2 as $lang=>$re) {
            if (strpos($this->http->Response["body"], $re) !== false) {
                $this->lang = $lang;

                break;
            }
        }

        $this->parseHtml($email);

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

    private function parseHtml(Email $email)
    {
        $f = $email->add()->flight();

        $xpath = "//text()[{$this->eq($this->t("Departure"))}]/ancestor::table[1]/tbody/tr";
        $nodes = $this->http->XPath->query($xpath);

        if ($nodes->length == 0) {
            return $email;
        }

        // General
        $f->general()
            ->noConfirmation()
        ;

        // Segments
        foreach ($nodes as $root) {
            $s = $f->addSegment();

            // Airline
            $flight = $this->http->FindSingleNode("./td[4]", $root);

            if (preg_match('/^(?<airline>[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(?<flightNumber>\d+)$/', $flight, $matches)) {
                $s->airline()
                    ->name($matches['airline'])
                    ->number($matches['flightNumber'])
                ;
            }
            $s->airline()
                ->confirmation($this->http->FindSingleNode("./td[5]", $root, true, "#^\s*([A-Z\d]{5,7})\s*$#"));

            // Departure
            $s->departure()
                ->code($this->http->FindSingleNode("./td[1]/descendant::text()[normalize-space(.)][1]", $root, true, "#\(([A-Z]{3})\)#"))
                ->name($this->http->FindSingleNode("./td[1]/descendant::text()[normalize-space(.)][1]", $root, true, "#(.*?)\s+\([A-Z]{3}\)#"))
                ->noDate()
                ->day($this->normalizeDate($this->http->FindSingleNode("./td[3]/descendant::text()[normalize-space(.)][1]", $root)))
            ;

            // Arrival
            $s->arrival()
                ->code($this->http->FindSingleNode("./td[2]/descendant::text()[normalize-space(.)][1]", $root, true, "#\(([A-Z]{3})\)#"))
                ->name($this->http->FindSingleNode("./td[2]/descendant::text()[normalize-space(.)][1]", $root, true, "#(.*?)\s+\([A-Z]{3}\)#"))
                ->noDate()
            ;
        }
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function eq($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) { return 'normalize-space(' . $node . ")='" . $s . "'"; }, $field)) . ')';
    }

    private function starts($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) { return 'starts-with(normalize-space(' . $node . "),'" . $s . "')"; }, $field)) . ')';
    }

    private function contains($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) { return 'contains(normalize-space(' . $node . "),'" . $s . "')"; }, $field)) . ')';
    }

    private function opt($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return '';
        }

        return '(?:' . implode('|', array_map(function ($s) { return preg_quote($s, '/'); }, $field)) . ')';
    }

    private function normalizeDate($date, $correct = false)
    {
//        $this->http->log('$date = '.print_r( $date,true));
        $year = date('Y', $this->date);
        $in = [
            '#^\s*(\d{1,2})\s+([^\d\s]+)\s*$#u', //10 Feb 2019
        ];
        $out = [
            '$1 $2 ' . $year,
        ];
        $date = preg_replace($in, $out, $date);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#u", $date, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $date = str_replace($m[1], $en, $date);
            }
        }
        $date = strtotime($date);

        if ($correct === true) {
            if ($date < strtotime("-1 month", $this->date)) {
                $date = strtotime("+1 year", $date);
            }
        }

        return $date;
    }
}
