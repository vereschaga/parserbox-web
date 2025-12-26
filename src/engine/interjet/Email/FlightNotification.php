<?php

namespace AwardWallet\Engine\interjet\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class FlightNotification extends \TAccountChecker
{
    public $mailFiles = "interjet/it-28722070.eml, interjet/it-28913768.eml";

    public $detectFrom = '@interjet.com';
    public $detectSubject = [
        'en' => 'Flight Notification',
        'es' => 'Notificación de Vuelo',
    ];

    private $detectCompany = 'Interjet';

    private $detectBody = [
        'en' => ['We just wanted to remind you that on'],
        'es' => ['Recuerda que el'],
    ];

    private $lang = 'en';
    private static $dict = [
        'en' => [
            //            "Booking code" => "",
            //            "Operated by:" => "",
            //            "Date and time" => "",
            //            "Departure date" => "",
            //            "Arrival date" => "",
            //            "Flight number" => "",
            //            "Passengers" => "",
        ],
        'es' => [
            "Booking code"   => "Clave de reservación",
            "Operated by:"   => "Operado por:",
            "Date and time"  => "Fecha y horario",
            "Departure date" => "Sale",
            "Arrival date"   => "Llega",
            "Flight number"  => "Número de Vuelo",
            "Passengers"     => "Pasajeros",
        ],
    ];

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $body = html_entity_decode($this->http->Response['body']);

        foreach ($this->detectBody as $lang => $detectBody) {
            foreach ($detectBody as $dBody) {
                if (strpos($body, $dBody) !== false) {
                    $this->lang = $lang;

                    break 2;
                }
            }
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        $this->parseEmail($email);

        return $email;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->detectFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (self::detectEmailFromProvider($headers['from']) === false) {
            return false;
        }

        foreach ($this->detectSubject as $dSubject) {
            if (strpos($headers['subject'], $dSubject) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $this->http->Response['body'];

        if (stripos($body, $this->detectCompany) === false) {
            return false;
        }

        foreach ($this->detectBody as $lang => $detectBody) {
            foreach ($detectBody as $dBody) {
                if (strpos($body, $dBody) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    private function parseEmail(Email $email)
    {
        $f = $email->add()->flight();

        // General
        $f->general()->confirmation($this->http->FindSingleNode("//text()[" . $this->eq($this->t("Booking code")) . "]/following::text()[normalize-space()][1]", null, true, "#^\s*([\dA-Z]{5,})\s*$#"));

        $passengers = array_filter($this->http->FindNodes("//text()[" . $this->eq($this->t("Passengers")) . "]/ancestor::td[1]//table//td[not(.//td) and not(contains(., '('))]"));
        $f->general()
            ->travellers($passengers, true);

        // Segments
        $xpath = "//text()[" . $this->eq($this->t("Date and time")) . "]/ancestor::tr[1]";
        $segments = $this->http->XPath->query($xpath);

        foreach ($segments as $root) {
            $s = $f->addSegment();

            $nodes = implode("\n", $this->http->FindNodes("./ancestor::td[1]//text()[normalize-space()]", $root));
            $this->logger->warning($nodes);
            $regexp = "#" . $this->preg_implode($this->t("Departure date")) . "\s*:\s*(?<dDate>.+)\s*"
                    . $this->preg_implode($this->t("Arrival date")) . "\s*:\s*(?<aDate>.+)\s*"
                . $this->preg_implode($this->t("Flight number")) . "\s*(?<fn>\d{1,5})\s+(?<aircraft>.+)?,#s";

            $this->logger->warning($regexp);

            if (preg_match($regexp, $nodes, $m)) {
                // Airline
                $s->airline()
                    ->name($this->http->FindSingleNode("./ancestor::tr[1]/preceding-sibling::tr[1]/td[last()]", $root, true,
                            "#^\s*" . $this->preg_implode($this->t("Operated by:")) . "\s*(.+)#"))
                    ->number($m['fn'])
                ;

                $node = implode("\n", $this->http->FindNodes("./ancestor::tr[1]/preceding-sibling::tr[1]/td[1]//text()", $root));

                if (preg_match("#^\s*([A-Z]{3})\s+([A-Z]{3})#", $node, $mat)) {
                    // Departure
                    $s->departure()
                        ->code(trim($mat[1]))
                        ->date($this->normalizeDate($m['dDate']))
                    ;
                    // Arrival
                    $s->arrival()
                        ->code($mat[2])
                        ->date($this->normalizeDate($m['aDate']))
                    ;

                    // Extra
                    if (isset($m['aircraft']) && !empty($m['aircraft'])) {
                        $s->extra()
                            ->aircraft($m['aircraft'])
                    ;
                    }
                }
            }
        }

        return $email;
    }

    private function t($phrase)
    {
        if (!isset(self::$dict[$this->lang]) || !isset(self::$dict[$this->lang][$phrase])) {
            return $phrase;
        }

        return self::$dict[$this->lang][$phrase];
    }

    private function normalizeDate($str)
    {
        $in = [
            "#^\s*[^\s\d]+,\s*(\d{1,2})\s*/\s*([^\s\d]+)\s*/\s*(\d{4}),?\s+(\d+:\d+(?:\s*[AP]M)?)\s*hrs?\s*$#", // Sat, 10/Nov/2018 15:30 hr, jueves, 11/oct/2018, 21:55 hrs
        ];
        $out = [
            "$1 $2 $3, $4",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if (($en = MonthTranslate::translate($m[1], $this->lang)) || ($en = MonthTranslate::translate($m[1], 'da')) || ($en = MonthTranslate::translate($m[1], 'no'))) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return strtotime($str);
    }

    private function eq($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'normalize-space(.)="' . $s . '"'; }, $field)) . ')';
    }

    private function starts($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'starts-with(normalize-space(.),"' . $s . '")'; }, $field)) . ')';
    }

    private function contains($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'contains(normalize-space(.),"' . $s . '")'; }, $field)) . ')';
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function preg_implode($field)
    {
        if (!is_array($field)) {
            $field = [$field];
        }

        return '(?:' . implode("|", array_map(function ($v) {return preg_quote($v, '#'); }, $field)) . ')';
    }
}
