<?php

namespace AwardWallet\Engine\lanpass\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class Upgrade extends \TAccountChecker
{
    public $mailFiles = "lanpass/it-174988807.eml";

    public $detectFrom = "opcion@upgrade.lan.com";
    public $detectSubject = [
        // en
        'Get Upgraded on your LATAM Airlines flight!',
        // pt
        'Sua última oportunidade de realizar un upgrade no seu próximo voo da LATAM Airlines',
    ];

    public $detectBody = [
        'en' => [
            'We recently sent you an email advising that you may place a bid in order to obtain a seat',
            'Make your offer and opt for a seat in our Premium Economy or Premium Business',
            'We\'ve lowered our bet prices so it\'s easier to experience a flight in Premium Business',
        ],
        'pt' => [
            'seu actual itinerário de vôo é elegível para um upgrade para Premium',
            'Faça sua oferta por um assento na cabine superior (Premium Economy ou Premium Business)',
        ],
        'es' => [
            'Bajamos el valor de la apuesta para que no te pierdas la experiencia de viajar en Premium Business',
            'Haz tu oferta para optar a un asiento en nuestra cabina superior',
        ],
    ];
    public $lang = '';
    public static $dict = [
        'en' => [
            'Departure Date' => 'Departure Date',
        ],
        'pt' => [
            'Your Airline Booking Reference:' => 'Código de reserva:',
            'Departure Date'                  => 'Partida',
            'Flight'                          => 'Voo',
        ],
        'es' => [
            'Your Airline Booking Reference:' => 'Código de Reserva:',
            'Departure Date'                  => 'Fecha',
            'Flight'                          => 'Vuelo',
        ],
    ];

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        if (!$this->assignLang()) {
            $this->logger->debug('can\'t determine a language');

            return $email;
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        $this->parseEmail($email);

        return $email;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//img[contains(@src,'.latam.com')] | //a[contains(@href,'.latam.com')] | //img[contains(@alt,'LATAM Airlines')]")) {
            foreach ($this->detectBody as $lang => $detectBody) {
                if ($this->http->XPath->query("//*[{$this->contains($detectBody)}]")->length > 0) {
                    return $this->assignLang();
                }
            }
        }

        return false;
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

        if (isset($headers["subject"])) {
            foreach ($this->detectSubject as $detectSubject) {
                if (stripos($headers["subject"], $detectSubject) !== false) {
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

    private function parseEmail(Email $email): bool
    {
        $r = $email->add()->flight();

        $conf = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Your Airline Booking Reference:'))}]/following::text()[normalize-space()!=''][1]",
            null, true, "/^\s*([A-Z\d]{5,7})\s*$/");

        $r->general()
            ->confirmation($conf);

        $xpath = "//tr[*[4][{$this->contains($this->t('Departure Date'))}] and *[1][{$this->contains($this->t('Flight'))}]]/following::tr[1]/ancestor::*[1]/tr[normalize-space()]";
        $roots = $this->http->XPath->query($xpath);
        $columns = [
            'flight'    => 1,
            'departure' => 2,
            'arrival'   => 3,
            'date'      => 4,
        ];

        $this->logger->debug('Segments root: ' . $xpath);

        foreach ($roots as $root) {
            $s = $r->addSegment();

            $airline = $this->http->FindSingleNode('*[' . $columns['flight'] . ']', $root);

            if (preg_match('/^\s*([A-Z\d][A-Z]|[A-Z][A-Z\d])\s+(\d{1,5})\s*$/', $airline, $m)) {
                $s->airline()
                    ->name($m[1])
                    ->number($m[2]);
            }

            $s->departure()
                ->code($this->http->FindSingleNode('*[' . $columns['departure'] . ']', $root, true, "/.+\(([A-Z]{3})\)\s*$/"))
                ->name($this->http->FindSingleNode('*[' . $columns['departure'] . ']', $root, true, "/(.+?)\s*\([A-Z]{3}\)\s*$/"))
                ->noDate()
                ->day($this->normalizeDate($this->http->FindSingleNode('*[' . $columns['date'] . ']', $root)))
            ;

            $s->arrival()
                ->code($this->http->FindSingleNode('*[' . $columns['arrival'] . ']', $root, true, "/.+\(([A-Z]{3})\)\s*$/"))
                ->name($this->http->FindSingleNode('*[' . $columns['arrival'] . ']', $root, true, "/(.+?)\s*\([A-Z]{3}\)\s*$/"))
                ->noDate()
            ;
        }

        return true;
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function assignLang()
    {
        foreach (self::$dict as $lang => $words) {
            if (isset($words['Departure Date'])) {
                if ($this->http->XPath->query("//*[{$this->contains($words['Departure Date'])}]")->length > 0) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(' or ', array_map(function ($s) {
            return 'normalize-space(.)="' . $s . '"';
        }, $field));
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(' or ', array_map(function ($s) {
            return 'starts-with(normalize-space(.),"' . $s . '")';
        }, $field));
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(' or ', array_map(function ($s) {
            return 'contains(normalize-space(.),"' . $s . '")';
        }, $field));
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
        $in = [
            //            "#^\s*(\d{2})/([^\W\d]+)/(\d{4})\s*$#", // 14/Oct/2019
            //            "#^\s*(\d{2})/(\d{2})/(\d{4})\s*$#", // 04/05/2019
        ];

        $out = [
            //            '$1 $2 $3',
            //            '$2.$1.$3',
        ];

        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return strtotime($str);
    }
}
