<?php

namespace AwardWallet\Engine\transavia\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class ATicket extends \TAccountChecker
{
    public $mailFiles = "transavia/it-4004320.eml, transavia/it-4018899.eml";
    public static $dict = [
        'en' => [
            //            'Booking number' => '',
            //            'Flightnumber' => '',
            //            'From:' => '',
            //            'To:' => '',
            //            'Departure date' => '',
            //            'Flightnumber' => '',
            'Dear ' => ['Dear Mr ', 'Dear Mrs '],
        ],
        'es' => [
            'Booking number' => 'Número de reserva',
            'Flightnumber'   => 'Número de vuelo',
            'From:'          => 'De:',
            'To:'            => 'A:',
            'Departure date' => 'Fecha de salida',
            'Dear '          => ['Estimada señora ', 'Estimado señor '],
        ],
        'nl' => [
            'Booking number' => 'Boekingsnummer',
            'Flightnumber'   => 'Vluchtnummer',
            'From:'          => 'Van',
            'To:'            => 'Naar',
            'Departure date' => 'Vertrekdatum',
            'Dear '          => 'Beste meneer ',
        ],
        'de' => [
            'Booking number' => 'Buchungsnummer',
            'Flightnumber'   => 'Flugnummer',
            'From:'          => 'Von:',
            'To:'            => 'Nach:',
            'Departure date' => 'Abflugdatum',
            'Dear '          => ['Sehr geehrter Herr ', 'Sehr geehrte Frau '],
        ],
        'pt' => [
            'Booking number' => 'Número da reserva',
            'Flightnumber'   => 'Número do voo',
            'From:'          => 'De:',
            'To:'            => 'Para:',
            'Departure date' => 'Data da partida',
            'Dear '          => ['Caro Sr. '],
        ],
        'fr' => [
            'Booking number' => 'Numéro de réservation',
            'Flightnumber'   => 'Numéro de vol',
            'From:'          => 'De',
            'To:'            => 'à',
            'Departure date' => 'Date de départ',
            'Dear '          => ['Cher Monsieur ', 'Chère Madame '],
        ],
        'it' => [
            'Booking number' => 'Numero di prenotazione:',
            'Flightnumber'   => 'Numero di volo:',
            'From:'          => 'Da:',
            'To:'            => 'A:',
            'Departure date' => 'Data di partenza:',
            'Dear '          => ['Gentile signora '],
        ],
    ];

    private $detectSubject = [
        'en' => 'You can now check in online for your flight to',
        'es' => 'Ya puede facturar online para su vuelo a ',
        'nl' => 'Je kunt nu online inchecken voor je vlucht naar ',
        'de' => 'Sie können jetzt online für Ihren Flug nach',
        'pt' => 'Pode agora efetuar o check-in online para o seu voo para',
        'fr' => 'Important : Enregistrez-vous en ligne dès maintenant pour votre vol vers',
        'it' => ' Check-in online aperto per il volo diretto a',
    ];

    private $detectBody = [
        'es' => ['antes de la salida en', 'facturar online'],
        'nl' => ['Dit is een automatisch gegenereerde e-mail', 'online inchecken'],
        'de' => ['online einchecken'],
        'pt' => ['Data da partida'],
        'it' => ['il check-in online'],
        'en' => ['check-in online'],// after pt, it
        'fr' => ['Enregistrez-vous'],
    ];

    private $detectLang = [
        'pt' => ['Número do voo'],
        'en' => ['Flightnumber'],
        'es' => ['Fecha de salida'],
        'nl' => ['Vluchtnummer'],
        'de' => ['Flugnummer'],
        'fr' => ['Numéro de vol'],
        'it' => ['Numero di volo'],
    ];

    private $lang = 'en';

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $body = $this->http->Response['body'];

        if (stripos($body, "ISO-8859-1")) {
            $body = iconv("utf-8", "iso-8859-1//IGNORE", $body);
            $this->http->SetEmailBody($body);
        }


        foreach ($this->detectLang as $lang => $detectLang) {
            foreach ($detectLang as $dLang) {
                if (stripos($body, $dLang) !== false || $this->http->XPath->query("//text()[contains(., '{$dLang}')]")->length > 0) {
                    $this->lang = $lang;

                    break 2;
                }
            }
        }

        $this->parseFlight($email);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $body = $this->http->Response['body'];

        if (stripos($body, 'Transavia') === false && $this->http->XPath->query("//a[contains(@href, 'transavia.com')]")->length === 0) {
            return false;
        }

        foreach ($this->detectBody as $lang => $detectBody) {
            foreach ($detectBody as $dBody) {
                if (stripos($body, $dBody) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers['from']) && (stripos($headers['from'], "transavia.com") !== false);
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, 'transavia.com') !== false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    private function parseFlight(Email $email)
    {
        $f = $email->add()->flight();

        // General
        $f->general()
            ->confirmation($this->getNode('Booking number'))
        ;
        $traveller = $this->http->FindSingleNode("//text()[" . $this->starts($this->t('Dear ')) . "]", null, true, "#" . $this->preg_implode($this->t("Dear ")) . "\s*(.+?)[,.!]#");

        if (!empty($traveller)) {
            $f->general()->traveller($traveller);
        }

        $xpath = "//*[contains(normalize-space(text()), '{$this->t('Booking number')}')]";
        $roots = $this->http->XPath->query($xpath);

        if ($roots->length === 0) {
            $this->logger->debug("roots not found by xpath: {$xpath}");
        }

        foreach ($roots as $root) {
            $s = $f->addSegment();

            // Airline
            $flight = $this->getNode('Flightnumber', null, $root);

            if (preg_match("#(\w{2})\s*(\d+)#", $flight, $m)) {
                $s->airline()
                    ->name($m[1])
                    ->number($m[2])
                ;
            }

            // Departure
            $dep = $this->getNode('From:');

            if (preg_match("#(.+)\s+\(\s*([A-Z]{3})\s*\)#", $dep, $m)) {
                $s->departure()
                    ->name($m[1])
                    ->code($m[2])
                ;
            }
            $s->departure()
                ->noDate()
                ->day($this->normalizeDate($this->getNode('Departure date')))
            ;

            // Arrival
            $arr = $this->getNode('To:');

            if (preg_match("#(.+)\s+\(\s*([A-Z]{3})\s*\)#", $arr, $m)) {
                $s->arrival()
                    ->name($m[1])
                    ->code($m[2])
                ;
            }
            $s->arrival()->noDate();
        }

        return $email;
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function normalizeDate($date)
    {
//        $this->http->log('$date = '.print_r( $date,true));
        $in = [
            "#\S*\s*(\w+)\s+(\d+),\s+(\d{4})#",
            //27-10-2018
            "#^(\d+)\-(\d+)\-(\d{4})$#",
        ];
        $out = [
            "$2 $1 $3",
            "$1.$2.$3",
        ];
        $date = preg_replace($in, $out, $date);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#u", $date, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $date = str_replace($m[1], $en, $date);
            }
        }

        return strtotime($date);
    }

    private function getNode($str, $regexp = null, $root = null)
    {
        return $this->http->FindSingleNode("//text()[" . $this->starts($this->t($str)) . "]/following::*[normalize-space(.)!=''][1]", $root, true, $regexp);
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

        return '(?:' . implode("|", array_map(function ($v) {return preg_quote($v, '#'); }, $field)) . ')';
    }
}
