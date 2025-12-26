<?php

namespace AwardWallet\Engine\lanpass\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class ArriveAtAirport extends \TAccountChecker
{
    public $mailFiles = "lanpass/it-126706593.eml";

    private $detectSubject = [
        // pt + en
        'nós queremos que você chegue a tempo para seu vôo para',
        // es
        ', queremos que llegues a tiempo para tu vuelo a',
    ];

    private $detectBody = [
        'pt' => ['É importante que você chegue com tempo ao aeroporto para seu voo'],
        'es' => ['Es importante que llegues con tiempo al aeropuerto para tu vuelo de'],
        'en' => ['It\'s important that you get to the airport on time for your flight'],
    ];

    private $lang = '';

    private static $dictionary = [
        'pt' => [
            //            'Olá,' => '',
            //            'Nº Compra:' => '',
            //            'É importante que você chegue com tempo ao aeroporto para seu voo' => '',
        ],
        'es' => [
            'Olá,'                                                             => 'Hola',
            'Nº Compra:'                                                       => 'Nº Orden:',
            'É importante que você chegue com tempo ao aeroporto para seu voo' =>
                'Es importante que llegues con tiempo al aeropuerto para tu vuelo de',
        ],
        'en' => [
            'Olá,'                                                             => 'Hi,',
            'Nº Compra:'                                                       => 'Order Number:',
            'É importante que você chegue com tempo ao aeroporto para seu voo' =>
                'It\'s important that you get to the airport on time for your flight',
        ],
    ];

    public function detectEmailFromProvider($from)
    {
        return stripos($from, 'info@info.latam.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->detectEmailFromProvider($headers['from']) !== true) {
            return false;
        }

        foreach ($this->detectSubject as $detectSubject) {
            if (stripos($headers['subject'], $detectSubject) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query('//a[contains(@href, "latamairlines.com")]')->length === 0) {
            return false;
        }

        foreach ($this->detectBody as $detectBody) {
            if ($this->http->XPath->query("//*[" . $this->contains($detectBody) . "]")->length > 0) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        foreach ($this->detectBody as $lang =>  $detectBody) {
            if ($this->http->XPath->query("//*[" . $this->contains($detectBody) . "]")->length > 0) {
                $this->lang = $lang;

                break;
            }
        }

        if (empty($this->lang)) {
            $this->logger->debug("Can't determine a language!");

            return $email;
        }

        $this->parseFlight($parser, $email);

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

    private function parseFlight(\PlancakeEmailParser $parser, Email $email)
    {
        $f = $email->add()->flight();

        $f->general()
            ->confirmation($this->http->FindSingleNode("//text()[" . $this->eq($this->t("Nº Compra:")) . "]/following::text()[normalize-space()][1]",
                null, true, "/^\s*(LA[\d[A-Z]{5,})\s*$/u"),
                trim($this->http->FindSingleNode("//text()[" . $this->eq($this->t("Nº Compra:")) . "]"), ":"))
            ->traveller($this->http->FindSingleNode("//text()[" . $this->eq($this->t("Olá,")) . "]/following::text()[normalize-space()][1]",
                null, true, "/^\s*([[:alpha:] \-]+),\s*$/u"), false)
        ;

        $s = $f->addSegment();

        $text = implode(' ', $this->http->FindNodes("//text()[" . $this->contains($this->t("É importante que você chegue com tempo ao aeroporto para seu voo")) . "]/ancestor::td[1]//text()[normalize-space()]"));
        $this->logger->debug('$text = ' . print_r($text, true));

        $regex = [
            'pt' => "/ seu voo (?<al>[A-Z\d][A-Z]|[A-Z][A-Z\d])(?<fn>\d+) de (?<from>.{2,}) a (?<to>.{2,}?) de (?<date>\d+.+?), programado para as (?<time>\d{1,2}[:：]\d{2}(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?)?) horas\.$/u",
            'es' => "/ tu vuelo de (?<from>.{2,}) a (?<to>.{2,}?)\s*\((?<al>[A-Z\d][A-Z]|[A-Z][A-Z\d])(?<fn>\d+)\), del (?<date>\d+.+?) a las (?<time>\d{1,2}[:：]\d{2}(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?)?) horas\.$/u",
            // It's important that you get to the airport on time for your flight LA8118 departing from São Paulo to Montevidéu on Mar 15, 2023, scheduled for 7:10 AM.
            'en' => "/ for your flight (?<al>[A-Z\d][A-Z]|[A-Z][A-Z\d])(?<fn>\d+) departing from (?<from>.{2,}) to (?<to>.{2,}?)\s*on (?<date>.+\d+.+?), scheduled for (?<time>\d{1,2}[:：]\d{2}(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?)?)\.$/u",
        ];

        if (!empty($regex[$this->lang]) && preg_match($regex[$this->lang], $text, $m)) {
            $s->departure()
                ->noCode()
                ->name($m['from'])
                ->date($this->normalizeDate($m['date'] . ', ' . $m['time']))
            ;
            $s->arrival()->noCode()->noDate();

            if (mb_strtolower($m['from']) !== mb_strtolower($m['to'])) {
                $s->arrival()->name($m['to']);
            }

            $s->airline()
                ->name($m['al'])
                ->number($m['fn']);
        }
    }

    private function assignLang(): bool
    {
        foreach (self::$dictionary as $lang => $phrases) {
            if (empty($phrases['Ver cartão'])) {
                continue;
            }

            if ($this->http->XPath->query("//node()[{$this->contains($phrases['Ver cartão'])}]")->length > 0) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
    }

    private function t(string $phrase)
    {
        if (!isset(self::$dictionary, $this->lang) || empty(self::$dictionary[$this->lang][$phrase])) {
            return $phrase;
        }

        return self::$dictionary[$this->lang][$phrase];
    }

    private function normalizeDate($date)
    {
//        $this->logger->debug('$date = '.print_r( $date,true));
        $in = [
            // 03 de dez de 2021, 15:05
            "/^\s*(\d+) de (\w+) de (\d{4})[,\s]+(\d{1,2}:\d{2})\s*$/u",
        ];
        $out = [
            "$1 $2 $3, $4",
        ];

        $str = preg_replace($in, $out, $date);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return strtotime($str);
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

    private function eq($field, $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'normalize-space(' . $node . ')="' . $s . '"';
        }, $field)) . ')';
    }

    private function starts($field, $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'starts-with(normalize-space(' . $node . '),"' . $s . '")';
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
}
