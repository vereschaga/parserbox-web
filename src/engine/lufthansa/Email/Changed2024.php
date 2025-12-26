<?php

namespace AwardWallet\Engine\lufthansa\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class Changed2024 extends \TAccountChecker
{
    public $mailFiles = "lufthansa/it-750622051.eml, lufthansa/it-753348557.eml, lufthansa/it-755484758.eml, lufthansa/it-755500822.eml";

    public $providerCode;
    public static $providers = [
        'lufthansa' => [
            'from'     => '.lufthansa.com',
            'bodyText' => ['Votre équipe Lufthansa'],
            'bodyUrl'  => ['.lufthansa.com'],
        ],

        'swissair' => [
            'from'     => '.swiss.com',
            'bodyText' => ['Your SWISS Team', 'Votre équipe SWISS'],
            'bodyUrl'  => ['.swiss.com'],
        ],

        'austrian' => [
            'from'     => '.austrian.com',
            'bodyText' => ['Your Austrian Airlines Team', 'Votre équipe Austrian Airlines'],
            'bodyUrl'  => ['.austrian.com'],
        ],

        'brussels' => [
            'from'     => '.brusselsairlines.com',
            'bodyText' => [],
            'bodyUrl'  => ['.brusselsairlines.com'],
        ],
    ];

    private $detectSubject = [
        // en
        'Delay of your flight to ',
        'Departure from gate ',
        'Update: Your flight to ',
        // de
        'Aktualisierung: Ihr Flug nach',
        'Abflug von Gate ',
        'Verspätung Ihres Fluges nach',
        // fr
        'Départ de la porte ',
        'Mise à jour : votre vol vers ',
        'Retard de votre vol vers ',
        // es
        'Cambio de puerta de embarque: salida desde la puerta de embarque ',
        'Actualización: su vuelo con destino a',
        'sufre un retraso',
        'Salida desde la puerta de embarque',
        // nl
        'Vertrek vanaf gate',
        // it
        'Partenza da gate ',
        'Ritardo del suo volo per',
        'Variazione gate: partenza da gate',
        'Aggiornamento: il suo volo per',
        // pt
        'Atraso do seu voo para',
    ];

    private $detectBody = [
        'en' => [
            'Your flight is delayed',
            'Your flight departs from gate',
        ],
        'de' => [
            'Ihr Flug startet von Gate',
            'Ihr Flug verspätet sich',
        ],
        'fr' => [
            'Votre vol est retardé',
            'Votre vol partira de la porte d’embarquement',
        ],
        'es' => [
            'Su vuelo sufre un retraso',
            'Su vuelo saldrá de la puerta de embarque',
        ],
        'nl' => [
            'Je vlucht vertrekt vanaf gate',
        ],
        'it' => [
            'Il suo volo partirà dal gate',
            'Il suo volo ha un ritardo',
        ],
        'pt' => [
            'O seu voo está atrasado',
        ],
    ];

    private $lang;
    private static $dictionary = [
        'en' => [
            'Booking Code:' => ['Booking Code:', 'Booking code:'],
            // 'Dear ' => '',
            // 'namePrefix' => '',
            'will depart on ' => ['will depart on ', 'will depart from gate'],
            'textRes'         => [
                // Your Austrian Airlines flight OS654 from Chisinau to Vienna will depart on 17 June 2024 at 19:05.
                '/flight (?<al>[A-Z][A-Z\d]|[A-Z\d][A-Z]) ?(?<fn>\d+) from (?<from>.+?) to (?<to>.+?) will depart on (?<date>.+?\d{1,2}:\d{2}.*?)\./',
                // Your flight LX188 from Zurich to Shanghai on 27 September 2024 at 13:10 will depart from gate D25.
                '/flight (?<al>[A-Z][A-Z\d]|[A-Z\d][A-Z]) ?(?<fn>\d+) from (?<from>.+?) to (?<to>.+?) on (?<date>.+?) will depart from/',
            ],
        ],
        'de' => [
            'Booking Code:'   => 'Buchungscode:',
            'Dear '           => ['Grüezi ', 'Guten Tag '],
            'namePrefix'      => ['Herr', 'Frau'],
            'will depart on ' => ['startet von Gate', 'startet am '],
            'textRes'         => [
                // Ihr Lufthansa Flug LH2026 von München nach Düsseldorf startet am 26 September 2024 um 19:15 Uhr.
                '/Flug (?<al>[A-Z][A-Z\d]|[A-Z\d][A-Z]) ?(?<fn>\d+) von (?<from>.+?) nach (?<to>.+?) startet am (?<date>.+?\d{1,2}:\d{2}) Uhr\./u',
                // Ihr Flug LX2140 von Zürich nach Valencia am 27 September 2024 um 09:30 Uhr startet von Gate A51.
                '/Flug (?<al>[A-Z][A-Z\d]|[A-Z\d][A-Z]) ?(?<fn>\d+) von (?<from>.+?) nach (?<to>.+?) am (?<date>.+?) Uhr startet von/u',
            ],
        ],
        'fr' => [
            'Booking Code:'   => 'Code de réservation:',
            'Dear '           => ['Bonjour '],
            'namePrefix'      => ['Monsieur', 'Madame', 'Mademoiselle'],
            'will depart on ' => ['partira de la porte d’embarquement', 'partira le '],
            'textRes'         => [
                // Votre vol Lufthansa LH1299 de Istanbul à Francfort partira le 11 juin 2024 à 14:30.
                '/vol .+? (?<al>[A-Z][A-Z\d]|[A-Z\d][A-Z]) ?(?<fn>\d+) de (?<from>.+?) à (?<to>.+?) partira le (?<date>.+?\d{1,2}:\d{2}.*?)\./u',
                // Votre vol LH1930 de Munich à Berlin le 28 septembre 2024 à 08:00 partira de la porte d’embarquement G34.
                '/vol (?<al>[A-Z][A-Z\d]|[A-Z\d][A-Z]) ?(?<fn>\d+) de (?<from>.+?) à (?<to>.+?) le (?<date>.+?) partira de la porte/u',
            ],
        ],
        'es' => [
            'Booking Code:'   => 'Código de reserva:',
            'Dear '           => ['Buenos días,', 'Estimado '],
            'namePrefix'      => ['Señor', 'Señora'],
            'will depart on ' => ['saldrá desde la puerta de embarque', 'está prevista para'],
            'textRes'         => [
                // La salida de su vuelo EN8852 de Air Dolomiti, de Francfort a Florencia, está prevista para las 08:20 horas del 27 septiembre 2024.
                '/vuelo (?<al>[A-Z][A-Z\d]|[A-Z\d][A-Z]) ?(?<fn>\d+) de .+?, de (?<from>.+?) a (?<to>.+?), está prevista para las (?<date>.+?\d{4})\./u',
                // Su vuelo LH1622 de Munich a Cracovia con fecha de 02 julio 2024 a las 11:25 horas saldrá desde la puerta de embarque G04.
                '/vuelo (?<al>[A-Z][A-Z\d]|[A-Z\d][A-Z]) ?(?<fn>\d+) de (?<from>.+?) a (?<to>.+?) con fecha de (?<date>.+?) horas saldrá desde/u',
            ],
        ],
        'nl' => [
            'Booking Code:'   => 'Boekingscode:',
            'Dear '           => ['Beste '],
            'namePrefix'      => ['Dhr'],
            'will depart on ' => ['vertrekt aan gate'],
            'textRes'         => [
                // La salida de su vuelo EN8852 de Air Dolomiti, de Francfort a Florencia, está prevista para las 08:20 horas del 27 septiembre 2024.
                // '/vuelo (?<al>[A-Z][A-Z\d]|[A-Z\d][A-Z]) ?(?<fn>\d+) de .+?, de (?<from>.+?) a (?<to>.+?), está prevista para las (?<date>.+?\d{4})\./u',
                // Je vlucht LX93 van São Paulo naar Zürich op 19 september 2024 om 18:30 uur vertrekt aan gate 313.
                '/vlucht (?<al>[A-Z][A-Z\d]|[A-Z\d][A-Z]) ?(?<fn>\d+) van (?<from>.+?) naar (?<to>.+?) op (?<date>.+?) uur vertrekt aan gate/u',
            ],
        ],
        'it' => [
            'Booking Code:'   => 'Codice di prenotazione:',
            'Dear '           => ['Buongiorno '],
            'namePrefix'      => ['Signor'],
            'will depart on ' => ['partirà dal gate', 'partirà il '],
            'textRes'         => [
                // Il suo volo Lufthansa LH493 da Vancouver per Francoforte partirà il 29 settembre 2024 alle 16:40.
                // Il suo volo EN8244 da Monaco di Baviera per Bologna partirà il 19 aprile 2024 alle 21:00.
                '/volo(?: .+)? (?<al>[A-Z][A-Z\d]|[A-Z\d][A-Z]) ?(?<fn>\d+) da (?<from>.+?) per (?<to>.+?) partirà il (?<date>.+?\d{1,2}:\d{2})\./u',
                // Il suo volo LX1664 da Zurigo per Venezia del 29 settembre 2024 alle 17:30 partirà dal gate A84.
                '/volo (?<al>[A-Z][A-Z\d]|[A-Z\d][A-Z]) ?(?<fn>\d+) da (?<from>.+?) per (?<to>.+?) del (?<date>.+?) partirà dal gate/u',
            ],
        ],
        'pt' => [
            'Booking Code:'   => 'Código da reserva:',
            'Dear '           => ['Prezado ', 'Olá, '],
            'namePrefix'      => ['Senhor', 'Senhora'],
            'will depart on ' => ['está agendada para'],
            'textRes'         => [
                // A partida do seu voo LH1783 de Lufthansa, de Porto para Munique está agendada para 07 outubro 2024 às 11:35.
                '/voo (?<al>[A-Z][A-Z\d]|[A-Z\d][A-Z]) ?(?<fn>\d+) de .+?, de (?<from>.+?) para (?<to>.+?) está agendada para (?<date>.+?\d{1,2}:\d{2})\./u',
                // Il suo volo LX1664 da Zurigo per Venezia del 29 settembre 2024 alle 17:30 partirà dal gate A84.
                // '/volo (?<al>[A-Z][A-Z\d]|[A-Z\d][A-Z]) ?(?<fn>\d+) da (?<from>.+?) per (?<to>.+?) del (?<date>.+?) partirà dal gate/u',
            ],
        ],
    ];

    public static function getEmailProviders()
    {
        return array_keys(self::$providers);
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match("/[@\.]lufthansa\.com$/", $from) > 0;
    }

    public function detectEmailByHeaders(array $headers)
    {
        $detectedFrom = false;

        foreach (self::$providers as $code => $fDetect) {
            if (!empty($fDetect['from']) && $this->containsText($headers['from'], $fDetect['from']) === true) {
                $detectedFrom = true;
                $this->providerCode = $code;

                break;
            }
        }

        if ($detectedFrom !== true) {
            return false;
        }

        foreach ($this->detectSubject as $dSubject) {
            if (stripos($headers["subject"], $dSubject) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $detectedProv = false;

        foreach (self::$providers as $code => $pDetect) {
            if (!empty($pDetect['bodyText']) && $this->http->XPath->query("//*[{$this->contains($pDetect['bodyText'])}]")->length > 0
                || !empty($pDetect['bodyUrl']) && $this->http->XPath->query("//a[{$this->contains($pDetect['bodyUrl'], '@href')}]")->length > 0
            ) {
                $detectedProv = true;
                $this->providerCode = $code;

                break;
            }
        }

        if ($detectedProv !== true) {
            return false;
        }

        foreach ($this->detectBody as $detectBody) {
            if ($this->http->XPath->query("//*[{$this->contains($detectBody)}]")->length > 0) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        if (!$this->assignLang()) {
            $this->logger->debug("can't determine a language");

            return $email;
        }

        $this->parseHtml($email);

        if (empty($this->providerCode)) {
            foreach (self::$providers as $code => $pDetect) {
                if (!empty($fDetect['from']) && $this->containsText($parser->getCleanFrom(), $fDetect['from']) === true) {
                    $this->providerCode = $code;

                    break;
                }

                if (!empty($pDetect['bodyText']) && $this->http->XPath->query("//*[{$this->contains($pDetect['bodyText'])}]")->length > 0
                    || !empty($pDetect['bodyUrl']) && $this->http->XPath->query("//a[{$this->contains($pDetect['bodyUrl'], '@href')}]")->length > 0
                ) {
                    $this->providerCode = $code;

                    break;
                }
            }
        }

        if (!empty($this->providerCode)) {
            $email->setProviderCode($this->providerCode);
        }

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

        // General
        $f->general()
            ->confirmation($this->http->FindSingleNode("//text()[{$this->eq($this->t('Booking Code:'))}]/following::text()[normalize-space()][1]",
                null, true, "/^\s*([A-Z\d]{5,7})\s*$/"))
            ->traveller(preg_replace("/^\s*{$this->opt($this->t('namePrefix'))}\s+/", '',
                $this->http->FindSingleNode("//text()[{$this->starts($this->t('Dear '))}]",
                null, true, "/^\s*{$this->opt($this->t('Dear '))}(?:[[:alpha:]]{1,4}\. )?([[:alpha:] \-]+?)\s*[,]?\s*$/u")))
        ;

        $s = $f->addSegment();

        $info = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Dear '))}]/following::text()[{$this->contains($this->t('will depart on '))}]/ancestor::td[1]");
        // $this->logger->debug('$info = '.print_r( $info,true));
        foreach ((array) $this->t('textRes') as $re) {
            if (preg_match($re, $info, $m)) {
                $s->airline()
                    ->name($m['al'])
                    ->number($m['fn']);

                $s->departure()
                    ->noCode()
                    ->name($m['from'])
                    ->date($this->normalizeDate($m['date']));

                $s->arrival()
                    ->noCode()
                    ->name($m['to'])
                    ->noDate();
            }
        }

        return true;
    }

    private function assignLang()
    {
        foreach ($this->detectBody as $lang => $detectBody) {
            if ($this->http->XPath->query("//*[{$this->contains($detectBody)}]")->length > 0) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
    }

    private function normalizeDate($date)
    {
        // $this->logger->debug('$date = '.print_r($date,true));
        $in = [
            // 17 June 2024 at 19:05
            "/^\s*(\d{1,2})\s+([[:alpha:]]+)\s+(\d{4})\s+[[:alpha:]]+\s+(\d{1,2}:\d{2}(?: *[ap]m)?)\s*$/ui",
            // 08:20 horas del 27 septiembre 2024
            "/^\s*(\d{1,2}:\d{2}) horas del (\d{1,2})\s+([[:alpha:]]+)\s+(\d{4})\s*$/ui",
        ];
        $out = [
            "$1 $2 $3, $4",
            "$2 $3 $4, $1",
        ];
        $date = preg_replace($in, $out, $date);
        // $this->logger->debug('$date = '.print_r($date,true));

        if (preg_match("/\d+\s+([[:alpha:]]+)\s+\d{4}/u", $date, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $date = str_replace($m[1], $en, $date);
            }
        }

        return strtotime($date);
    }

    private function t($s)
    {
        if (!isset($this->lang, self::$dictionary[$this->lang], self::$dictionary[$this->lang][$s])) {
            return $s;
        }

        return self::$dictionary[$this->lang][$s];
    }

    private function contains($field, $text = 'normalize-space(.)')
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($text) {
            return 'contains(' . $text . ',"' . $s . '")';
        }, $field)) . ')';
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) {
            return 'normalize-space(.)="' . $s . '"';
        }, $field)) . ')';
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) {
            return 'starts-with(normalize-space(.),"' . $s . '")';
        }, $field)) . ')';
    }

    private function opt($field, $delimiter = '/')
    {
        $field = (array) $field;

        if (empty($field)) {
            $field = ['false'];
        }

        return '(?:' . implode("|", array_map(function ($s) use ($delimiter) {
            return str_replace(' ', '\s+', preg_quote($s, $delimiter));
        }, $field)) . ')';
    }

    private function containsText($text, $needle): bool
    {
        if (empty($text) || empty($needle)) {
            return false;
        }

        if (is_array($needle)) {
            foreach ($needle as $n) {
                if (stripos($text, $n) !== false) {
                    return true;
                }
            }
        } elseif (is_string($needle) && stripos($text, $needle) !== false) {
            return true;
        }

        return false;
    }
}
