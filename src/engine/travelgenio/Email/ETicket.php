<?php

namespace AwardWallet\Engine\travelgenio\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class ETicket extends \TAccountChecker
{
    public $mailFiles = "travelgenio/it-195998053.eml, travelgenio/it-198688562.eml, travelgenio/it-199756081.eml, travelgenio/it-235382960.eml";
    public $subjects = [
        // en
        'Your E-Ticket from Travelgenio booking ID',
        'Your E-Ticket from Travel2be booking ID',
        'Confirmation of your Travel2be booking',
        // da
        'Din E-billett fra Travelgenio bestillings-ID',
        // fr
        'Confirmation de votre réservation',
        // de
        'Ihr E-Ticket von Travel2be, Buchungsnummer',
        'Bestätigung Ihrer Buchung',
        // es
        'Confirmación de tu reserva',
        // no
        'Din E-billett fra Tripmonster bestillings-ID',
        // sv
        'Din elektroniska biljett från Tripmonster för bokning'
    ];

    public $lang = '';
    private static $detectsFrom = [
        'traveltobe'  => 'travel2be.com',
        'travelgenio' => 'travelgenio.com',
        'tripmonster' => '.tripmonster.com',
    ];

    private $detectCompany = [
        'traveltobe'  => ['Travel2Be', 'Travel2be'],
        'travelgenio' => ['TravelGenio', 'Travelgenio'],
        'tripmonster' => 'Tripmonster',
    ];

    private $providerCode;

    public $detectLang = [
        'en' => ['Flights'],
        'da' => ['Flyvninger'],
        'ja' => ['ご出発'],
        'fr' => ['Vols'],
        'de' => ['Flüge'],
        'es' => ['Vuelos'],
        'no' => ['Flygninger'],
        'sv' => ['Flyg'],
    ];

    public static $dictionary = [
        "en" => [
            'We are pleased to send you your e-ticket' => [
                'We are pleased to send you your e-ticket',
                'Your %company% booking is confirmed with booking ID',
            ],
            'Adult' => ['Adult', 'Child'],
        ],
        "da" => [
            'booking ID'                   => 'bestillings-ID', //from subject
            'We are pleased to send you your e-ticket' => [
                'Vi er glade for at kunne sende dig din(e) e-billet',
            ],
            'Personal details'                         => 'Personlige detaljer',
            'Booked flights'                           => 'Udvalgte flyvninger',
            'Airline booking code:'                    => 'Flyselskabets reservationsnummer:',
            'Adult'                                    => ['Voksen', 'Børn'],
            'E-ticket(s)'                              => 'E-billet(er)',
            'Departure'                                => 'Afgang',
            'Duration'                                 => 'Varighed',
        ],
        "ja" => [
            'booking ID'                   => 'Travelgenio', //from subject
            'We are pleased to send you your e-ticket' => 'おめでとうございます！お客様の航空会社%company%',
            'Personal details'                         => '個人情報',
            'Booked flights'                           => '選択済みのフライト',
            'Airline booking code:'                    => '航空会社予約番号',
            'Adult'                                    => '大人',
            //'E-ticket(s)' => '',
            'Departure' => 'ご出発',
            'Duration'  => 'フライト時間',
        ],
        "fr" => [
            'booking ID'                   => 'Confirmation de votre réservation', //from subject
            'We are pleased to send you your e-ticket' => 'Votre réservation %company% est confirmée avec le numéro',
            'Personal details'                         => 'Informations personnelles',
            'Booked flights'                           => 'Vols sélectionnés',
            'Airline booking code:'                    => ['Code de la compagnie aérienne:'],
            'Adult'                                    => ['Adulte', 'Enfant'],
            //'E-ticket(s)' => '',
            'Departure' => 'Départ',
            'Duration'  => 'Durée',
            'Seats'  => 'Sièges',
        ],
        "de" => [
            'booking ID'                   => 'Buchungsnummer', //from subject
            'We are pleased to send you your e-ticket' => [
                'gerne übermitteln wir Ihnen Ihr(e) E-Ticket(s)',
                'Ihre Buchung bei Travelgenio ist mit der Buchungsnummer'
            ],
            'Personal details'                         => 'Persönliche Angaben',
            'Booked flights'                           => 'Ausgewählte Flüge',
            'Airline booking code:'                    => ['Buchungscode der Fluggesellschaft:'],
            'Adult'                                    => ['Erwachsener', 'Kinder'],
            'E-ticket(s)' => 'E-Ticket(s)',
            'Departure' => 'Abflug',
            'Duration'  => 'Dauer',
            'Seats'  => 'Sitzplätze',
        ],
        "es" => [
            'booking ID'                   => 'Confirmación de tu reserva', //from subject
            'We are pleased to send you your e-ticket' => 'Tu reserva con %company% está confirmada con el localizador de reserva',
            'Personal details'                         => 'Información personal',
            'Booked flights'                           => 'Vuelos seleccionados',
            'Airline booking code:'                    => ['Código de reserva de la aerolínea:'],
            'Adult'                                    => ['Adulto', 'Niño', 'Bambino'],
//            'E-ticket(s)' => '',
            'Departure' => 'Salida',
            'Duration'  => 'Duración',
            'Seats'  => 'Asientos',
        ],
        "no" => [
            'booking ID'                   => 'bestillings-ID', //from subject
            'We are pleased to send you your e-ticket' => [
                'Vi er glade for å sende deg e-billetten(e) dine(e)',
            ],
            'Personal details'                         => 'Personlige opplysninger',
            'Booked flights'                           => 'Valgte flygninger',
            'Airline booking code:'                    => 'Flyselskapets kode:',
            'Adult'                                    => ['Voksen', 'Barn'],
            'E-ticket(s)'                              => 'E-billett(er)',
            'Departure'                                => 'Avgang',
            'Duration'                                 => 'Varighet',
        ],
        "sv" => [
            'booking ID'                   => 'för bokning', //from subject
            'We are pleased to send you your e-ticket' => [
                'Här kommer din e-biljett(er)',
            ],
            'Personal details'                         => 'Resenärer',
            'Booked flights'                           => 'Valt flyg',
            'Airline booking code:'                    => 'Flygbolagets bokningskod:',
            'Adult'                                    => ['Vuxen', 'Barn'],
            'E-ticket(s)'                              => 'E-biljett(er)',
            'Departure'                                => 'Avgång',
            'Duration'                                 => 'Restid',
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        $foundCompany = false;

        if (!empty($headers["from"])) {
            foreach (self::$detectsFrom as $prov => $dfrom) {
                if (stripos($headers["from"], $dfrom) !== false) {
                    $foundCompany = true;
                    $this->providerCode = $prov;

                    break;
                }
            }
        }

        if ($foundCompany === false) {
            foreach ($this->detectCompany as $prov => $dCompany) {
                if ($this->containsText($headers["subject"], $dCompany) !== false) {
                    $foundCompany = true;
                    $this->providerCode = $prov;

                    break;
                }
            }
        }

        if ($foundCompany === false) {
            return false;
        }

        foreach ($this->subjects as $subject) {
            if (stripos($headers['subject'], $subject) !== false) {
                return true;
            }
        }

        return false;
    }
    public function detectEmailByBody(PlancakeEmailParser $parser)

    {
        $foundCompany = false;

        foreach ($this->detectCompany as $dCompany) {
            if ($this->http->XPath->query("//text()[{$this->contains($dCompany)}]")->length > 0) {
                $foundCompany = $dCompany;

                break;
            }
        }

        if ($foundCompany == false) {
            return false;
        }

        $this->detectLang();

        $phrases = [];
        foreach ((array)$foundCompany as $fc) {
            $phrases = array_merge($phrases, str_replace('%company%', $fc, (array)$this->t('We are pleased to send you your e-ticket')));
        }
        $phrases = array_unique($phrases);
        return $this->http->XPath->query("//text()[{$this->contains($phrases)}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Booked flights'))}]")->length > 0;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.].+travelgenio\.com$/', $from) > 0;
    }

    public function ParseFlight(Email $email)
    {
        $f = $email->add()->flight();

        $f->general()
            ->noConfirmation()
            ->travellers($this->http->FindNodes("//text()[{$this->contains($this->t('Adult'))}]/following::text()[normalize-space()][1]"), true);

        $tickets = array_unique($this->http->FindNodes("//text()[{$this->eq($this->t('E-ticket(s)'))}]/ancestor::tr[1]/following-sibling::tr[normalize-space()]/descendant::td[2]"));

        if (count($tickets) > 0) {
            $f->setTicketNumbers($tickets, false);
        }

        $nodes = $this->http->XPath->query("//text()[{$this->contains($this->t('Booked flights'))}]/following::text()[{$this->contains($this->t('Airline booking code:'))}]/ancestor::tr[1]");

        foreach ($nodes as $root) {
            $s = $f->addSegment();

            $s->airline()
                ->confirmation($this->http->FindSingleNode("./descendant::text()[{$this->starts($this->t('Airline booking code:'))}][1]", $root, true, "/{$this->opt($this->t('Airline booking code:'))}\s*([A-Z\d]+)/"))
                ->name($this->http->FindSingleNode("./descendant::tr[normalize-space()][3]/td[1]", $root, true, "/^([A-Z\d]{2})\s\d{2,4}/"))
                ->number($this->http->FindSingleNode("./descendant::tr[normalize-space()][3]/td[1]", $root, true, "/^[A-Z\d]{2}\s(\d{2,4})/"));

            $s->departure()
                ->code($this->http->FindSingleNode("./descendant::text()[contains(normalize-space(), '(')]/ancestor::tr[1]/descendant::td[2]", $root, true, "/\(([A-Z]{3})\)/"))
                ->date($this->normalizeDate($this->http->FindSingleNode("./descendant::text()[{$this->starts($this->t('Departure'))}]/ancestor::tr[1]/following::tr[1]/descendant::td[2]", $root)));

            $s->arrival()
                ->code($this->http->FindSingleNode("./descendant::text()[contains(normalize-space(), '(')]/ancestor::tr[1]/descendant::td[3]", $root, true, "/\(([A-Z]{3})\)/"))
                ->date($this->normalizeDate($this->http->FindSingleNode("./descendant::text()[{$this->starts($this->t('Departure'))}]/ancestor::tr[1]/following::tr[1]/descendant::td[3]", $root)));

            $s->extra()
                ->duration($this->http->FindSingleNode("./descendant::text()[{$this->eq($this->t('Duration'))}]/ancestor::tr[1]/following::tr[1]/descendant::td[4]", $root))
                ->cabin($this->http->FindSingleNode("./descendant::text()[{$this->eq($this->t('Duration'))}]/ancestor::tr[1]/following::tr[2]/descendant::td[4]", $root), true, true);
            if (!empty($s->getDepCode()) && !empty($s->getArrCode())) {
                $seats = array_filter($this->http->FindNodes("//tr[*[1][normalize-space()='{$s->getDepCode()}-{$s->getArrCode()}']][preceding-sibling::tr[*[last()][{$this->eq($this->t('Seats'))}]]]/*[last()]",
                    null, "/^\s*(\d{1,3} ?[A-Z])\s*$/"));
                if (!empty($seats)) {
                    $s->extra()
                        ->seats(str_replace(' ', '', $seats));
                }
            }
        }
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->detectLang();

        $otaConf = $this->re("/{$this->opt($this->t('booking ID'))}\s*(\d+)/", $parser->getSubject());

        if (!empty($otaConf)) {
            $email->ota()
                ->confirmation($otaConf);
        }

        $this->ParseFlight($email);

        if (empty($this->providerCode)) {
            foreach ($this->detectCompany as $provider => $dCompany) {
                if ($this->http->XPath->query("//text()[{$this->contains($dCompany)}]")->length > 0) {
                    $this->providerCode = $provider;

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

    public static function getEmailProviders()
    {
        return array_keys(self::$detectsFrom);
    }

    public function detectLang()
    {
        foreach ($this->detectLang as $lang => $words) {
            foreach ($words as $word) {
                if ($this->http->XPath->query("//text()[{$this->contains($word)}]")->length > 0) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    protected function eq($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false';
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

        return implode(" or ", array_map(function ($s) {
            return "starts-with(normalize-space(.), \"{$s}\")";
        }, $field));
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) {
            return "contains(normalize-space(.), \"{$s}\")";
        }, $field));
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
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
            "#^(\d+)\s*(\d+)[月]\s*(\d{4})\s*([\d\:]+)$#u", //8 10月 2022 09:30
            "#^(\d+)\s*(\w+)\.?\s*(\d{4})\s*([\d\:]+)$#u", //1 Dec 2022 05:00
        ];
        $out = [
            "$1.$2.$3, $4",
            "$1 $2 $3, $4",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = \AwardWallet\Engine\MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return strtotime($str);
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
