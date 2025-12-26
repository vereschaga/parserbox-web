<?php

namespace AwardWallet\Engine\tcase\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class It2570216 extends \TAccountChecker
{
    public $mailFiles = "tcase/it-2570216.eml, tcase/it-58138029.eml, tcase/it-58459818.eml";
    public static $dictionary = [
        "en" => [
            "Dear" => ["Dear", "Hi "],
            //            "originally scheduled to depart from" => "",
            "flightRe" => ".* ([A-Z\d][A-Z]|[A-Z][A-Z\d]) ?(\d{1,5})",
            //            "on" => "",
            //            "has" => "",
            "new time" => ["new time", "NEW Time"],
            //            "Terminal & Gate" => "",
            "New" => ["NEW", "new"],
        ],
        "es" => [
            "Dear"                                => "Hola",
            "originally scheduled to depart from" => "que originalmente debía partir de",
            "flightRe"                            => "El vuelo ([A-Z\d][A-Z]|[A-Z][A-Z\d])(\d{1,5}).*?",
            "on"                                  => "el",
            "has"                                 => "ha",
            "new time"                            => "NUEVA hora",
            "Terminal & Gate"                     => "Terminal y puerta de embarque",
            "New"                                 => ["NUEVA", "NUEVO"],
        ],
        "pt" => [
            "Dear"                                => "Olá,",
            "originally scheduled to depart from" => "com saída de",
            "flightRe"                            => ", ([A-Z\d][A-Z]|[A-Z][A-Z\d])(\d{1,5})",
            "on"                                  => "originalmente programada para",
            "has"                                 => "foi",
            "new time"                            => "NOVO Horário",
            "Terminal & Gate"                     => "Terminal & Portão",
            "New"                                 => ["NOVO"],
        ],
        "fr" => [
            "Dear"                                => "Bonjour ",
            "originally scheduled to depart from" => "départ initialement prévu de",
            "flightRe"                            => ".* ([A-Z\d][A-Z]|[A-Z][A-Z\d])(\d{1,5})",
            "on"                                  => "le",
            "has"                                 => "a été",
            "new time"                            => "NOUVEL horaire",
            // "Terminal & Gate"                     => "Terminal & Portão",
            "New"                                 => ["NOUVEL"],
        ],
        "it" => [
            "Dear"                                => "Gentile ",
            "originally scheduled to depart from" => "previsto in partenza da",
            "flightRe"                            => ".* ([A-Z\d][A-Z]|[A-Z][A-Z\d]) ?(\d{1,5})",
            "on"                                  => "in data",
            "has"                                 => "ha subito una",
            // "new time"                            => "NOUVEL horaire",
            "Terminal & Gate"                     => "Terminal e gate",
            "New"                                 => ["NUOVO"],
        ],
    ];

    private $detectFrom = 'tripcase.';

    private $detectSubject = [
        'en' => 'flight has changed.',
        'es' => ' ha cambiado.',
        'pt' => ' foi alterado.',
        'O número do portão/terminal de embarque/desembarque do voo do(a) ',
        'fr' => 'votre vol a été modifié.',
        'it' => 'è stato modificato.',
    ];

    private $detectBody = [
        'en' => ['Your flight time has changed to', 'Your Flight time has changed to'],
        'es' => ['La partida de tu vuelo se cambió para las', 'La terminal y la puerta de tu vuelo se cambiaron por:'],
        'pt' => ['O horário de seu voo foi alterado para', 'Seu Terminal & Portão foram alterados para:'],
        'fr' => ['Votre horaire de vol a changé pour'],
        'it' => ['Il tuo terminal/gate è stato modificato in:'],
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
        if (stripos($headers['from'], $this->detectFrom) !== false) {
            return true;
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
        if ($this->http->XPath->query('//a[' . $this->contains(['tripcase.'], '@href') . ']')->length === 0) {
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
        $f->general()
            ->noConfirmation()
        ;
        $tr = $this->http->FindSingleNode("//text()[" . $this->eq($this->t("Dear")) . "][1]/following::text()[normalize-space()][1]", null, true, "#^\s*(([^\W\d]+[ \-]*)+),?\s*$#u");

        if (empty($tr)) {
            $tr = $this->http->FindSingleNode("//text()[" . $this->starts($this->t("Dear")) . "][1]", null, true, "#^" . $this->opt($this->t("Dear")) . "\s*(([^\W\d]+[ \-]*)+),?\s*$#u");
        }

        if (!preg_match("/^\s*travell?er\s*$/i", $tr)) {
            $f->general()
                ->traveller($tr, false);
        }

        // Segments

        $s = $f->addSegment();

        // Airline
        $text = $this->http->FindSingleNode("//text()[" . $this->contains($this->t("originally scheduled to depart from")) . "]/ancestor::*[1]");
        $re = "#" . $this->t("flightRe") . ",.*" . $this->opt($this->t("originally scheduled to depart from")) . " (.+) \(([A-Z]{3})\) " . $this->opt($this->t("on")) . " (.+?),?\s+" . $this->opt($this->t("has")) . " #u";
        // $this->logger->debug('$re = '.print_r( $re,true));
        // $this->logger->debug('$text = '.print_r( $text,true));

        if (preg_match($re, $text, $m)) {
            $s->airline()
                ->name($m[1])
                ->number($m[2])
            ;

            $s->departure()
                ->code($m[4])
                ->name($m[3])
            ;

            $date = $m[5];
        }

        $time = $this->http->FindSingleNode("//text()[" . $this->eq($this->t("new time")) . "]/following::text()[normalize-space()][1]/ancestor::td[" . $this->contains($this->t("new time")) . "][1]", null, true, "#" . $this->opt($this->t("new time")) . "\s*(.+)#");

        if (!empty($time)) {
            $s->departure()
                ->date($this->normalizeDate($time))
            ;
        }

        $terminal = $this->http->FindSingleNode("//tr[" . $this->eq($this->t("Terminal & Gate")) . "][preceding::text()[" . $this->eq($this->t("New")) . "]]/following::text()[normalize-space()][1]");

        if (!empty($terminal)) {
            $s->departure()
                ->date($this->normalizeDate($date))
            ;

            if (preg_match("/^\s*([^-]+)\s*\/.*$/", $terminal, $m)) {
                $s->departure()->terminal($m[1]);
            }
        }

        $s->arrival()
            ->noDate()
            ->noCode()
        ;
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
        // $this->logger->debug("Date: {$date}");
        $in = [
            "#^\s*[^\d\s]+,\s+(\w+)\s+(\d+)[\s,]+(\d{4})\s+(?:\D*\s+)?(\d{1,2}:\d{2}(?: ?[ap]m)?)\s*$#ui", //Tue, Mar 17, 2015 12:35PM; Monday, January 13, 2020 at 4:40PM
            "#^\s*[^\d\s]+[,.]?\s+(\d+)\s+(\w+)[.]?[\s,]+(\d{4})\s+(?:\D*\s+)?(\d{1,2}:\d{2}(?: ?[ap]m)?)\s*$#ui", //Seg, 6 jan, 2020 4:30PM; mar. 9 janv. 2024 12:35PM
            "#^\s*[^\d\s]+[\s\.,]+(\d+)\s+de\s+(\w+)\s+de\s+(\d{4})\s+(?:\D*\s+)?(\d{1,2}+:\d{2}(?: ?[ap]m)?)\s*$#ui", //lun. 2 de sep de 2019 11:00, Terça-feira, 17 de Dezembro de 2019 a(às) 9:30PM
        ];
        $out = [
            "$2 $1 $3, $4",
            "$1 $2 $3, $4",
            "$1 $2 $3, $4",
        ];
        $date = preg_replace($in, $out, $date);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $date, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $date = str_replace($m[1], $en, $date);
            }
        }

        return strtotime($date);
    }
}
