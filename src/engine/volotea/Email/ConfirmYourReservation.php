<?php

namespace AwardWallet\Engine\volotea\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Engine\WeekTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class ConfirmYourReservation extends \TAccountChecker
{
    public $mailFiles = "volotea/it-100012518.eml, volotea/it-29993538.eml, volotea/it-401714431.eml, volotea/it-42027891.eml, volotea/it-42332555.eml, volotea/it-707093141.eml";
    private $subjects = [
        'it' => ['Conferma della tua prenotazione:', 'Conferma della tua prenotazione :'],
        'fr' => ["Volotea · Carte d'embarquement:", 'Volotea • Confirmation de reservation:'],
        'en' => ['Your booking confirmation:', 'Boarding pass:'],
        'de' => [
            'Ihre Buchungsbestätigung:',
            'Erinnerung für Ihren Flug:',
        ],
        'pt' => [
            'Lembrete para o seu voo:',
            'Cartão de embarque:',
            'A sua confirmação de reserva:',
        ],
        'el' => [
            'κάρτας επιβίβασής:',
        ],
    ];
    private $langDetectors = [
        'it' => ['Questo è il tuo itinerario!', 'Prenotazione n.', 'scaricare la carta d\'imbarco per'],
        'fr' => ['votre réservation est confirmée', 'plus que 2 jours avant votre vol', 'télécharger votre carte d\'embarquement pour'],
        'es' => ['tu reserva está confirmada.', 'descarga tu tarjeta de embarque para', 'solo quedan 2 días para tu vuelo'],
        'en' => ['your booking is confirmed', 'only 2 days left to your flight!', 'download your boarding passes for'],
        'de' => ['Ihre Buchung ist bestätigt', 'nur noch 2 Tage bis zu Ihrem Flug'],
        'pt' => ['faltam apenas 2 dias para o seu voo', 'imprima o seu cartão de embarque para', 'a sua reserva está confirmada'],
        'el' => ['η κράτησή σου επιβεβαιώθηκε', 'μένουν μόνο 2 μέρες για την πτήση σου', 'εκτυπώστε το κάρτες επιβίβασής σας για Αθήνα'],
    ];
    private $subjectRoute;

    private $lang = '';
    private static $dict = [
        'it' => [
            'confirmMarkers' => ['la tua prenotazione è confermata!'],
            //            'confermato' => '',
            //            'Prenotazione n.' => '',
            //            'Partenza alle' => '',
            //            'Arrivo alle' => '',
            //            'Ecco i dettagli della tua prenotazione:' => '',
            'passengers' => ['Passeggeri(', 'Passeggeri (', 'Passeggeri:', 'Passeggeri :'],
            //            'Importo pagato con' => '',
            //            'Seats' => '',
            //'Ottieni le carte d’imbarco' => '',
            'in a phrase' => ["Ecco il tuo itinerario", "Ecco il vostro itinerario"],
        ],
        'fr' => [
            'confirmMarkers'                          => ['votre réservation est confirmée'],
            'confermato'                              => 'confirmée',
            'Prenotazione n.'                         => 'Réservation n°',
            'Partenza alle'                           => "Décolle à",
            'Arrivo alle'                             => 'Atterrit à',
            'Ecco i dettagli della tua prenotazione:' => 'Voici les détails de la réservation :',
            'passengers'                              => 'Passagers (',
            'Importo pagato con'                      => 'Montant payé avec',
            'Seats'                                   => 'Sièges choisis',
            'Ottieni le carte d’imbarco'              => 'Obtenez vos cartes d’embarquement',
            //'in a phrase' => "",
            'Passengers'                                 => 'Passagers',
            'Included for all passengers, all journeys.' => 'Inclus pour tous les passagers, tous les trajets.',
            'prefixName'                                 => ['Mme', 'Mr', 'Mrs', 'M', 'Hr', 'Κα', 'Fr', 'MR', 'MS'],
        ],
        'es' => [
            'confirmMarkers'                          => ['tu reserva está confirmada'],
            'confermato'                              => 'confirmada',
            'Prenotazione n.'                         => 'N.º de reserva',
            'Partenza alle'                           => "Salida a las",
            'Arrivo alle'                             => 'Llegada a las',
            'Ecco i dettagli della tua prenotazione:' => 'Estos son los detalles de tu reserva:',
            'passengers'                              => 'Pasajeros (',
            'Importo pagato con'                      => 'Importe abonado con',
            //            'Seats' => '',
            'Ottieni le carte d’imbarco' => 'Obtén tus tarjetas de embarque',
            'in a phrase'                => "¡Aquí tienes tu itinerario",
        ],
        'en' => [
            'confirmMarkers'                          => ['your booking is confirmed'],
            'confermato'                              => 'confirmed',
            'Prenotazione n.'                         => ['Booking no.', 'Booking'],
            'Partenza alle'                           => "Departs at",
            'Arrivo alle'                             => 'Arrives at',
            'Ecco i dettagli della tua prenotazione:' => 'These are your booking details:',
            'passengers'                              => 'Passengers (',
            'Importo pagato con'                      => 'Amount paid with',
            'Seats'                                   => 'Seats selected',
            "Ottieni le carte d’imbarco"              => 'Get your boarding passes',
            "in a phrase"                             => "left to your flight",
        ],
        'de' => [
            'confirmMarkers'                          => ['Ihre Buchung ist bestätigt'],
            'confermato'                              => 'bestätigt',
            'Prenotazione n.'                         => 'Buchungsnr.',
            'Partenza alle'                           => "Abflug um",
            'Arrivo alle'                             => 'Ankunft um',
            'Ecco i dettagli della tua prenotazione:' => 'Im Folgenden die Einzelheiten Ihrer Buchung:',
            'passengers'                              => 'Passagiere (',
            'Importo pagato con'                      => 'Betrag wurde bezahlt per',
            'Seats'                                   => 'Ausgewählte Sitzplätze',
            //'Ottieni le carte d’imbarco' => '',
            //'in a phrase' => ""
        ],
        'pt' => [
            'confirmMarkers'                          => ['a sua reserva está confirmada'],
            'confermato'                              => 'confirmada',
            'Prenotazione n.'                         => 'Reserva n.º',
            'Partenza alle'                           => "Partida a",
            'Arrivo alle'                             => 'Chegada a',
            'Ecco i dettagli della tua prenotazione:' => 'As informações da sua reserva:',
            'passengers'                              => 'Passageiros (',
            // 'Importo pagato con'                      => 'Amount paid with',
            'Seats'                                   => 'Lugares selecionados',
            "Ottieni le carte d’imbarco"              => 'Obter os seus cartões de embarque',
            'in a phrase'                             => "Este é o seu itinerário",
        ],
        'el' => [
            // 'confirmMarkers'                          => ['Ihre Buchung ist bestätigt'],
            // 'confermato'                              => 'bestätigt',
            'Prenotazione n.'                         => 'Αριθ. κράτησης',
            'Partenza alle'                           => "Αναχωρεί στις",
            'Arrivo alle'                             => 'Φτάνει στις',
            'Ecco i dettagli della tua prenotazione:' => 'Αυτά είναι τα στοιχεία της κράτησής σου:',
            'passengers'                              => 'Επιβάτες (',
            // 'Importo pagato con'                      => 'Amount paid with',
            // 'Seats'                                   => 'Lugares selecionados',
            //Ottieni le carte d’imbarco => '',
        ],
    ];

    private $year = 0;

    // Standard Methods

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[.@]volotea\.com/i', $from) > 0;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->detectEmailFromProvider($headers['from']) !== true && strpos($headers['subject'], 'Volotea') === false) {
            return false;
        }

        foreach ($this->subjects as $phrases) {
            foreach ($phrases as $phrase) {
                if (stripos($headers['subject'], $phrase) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query('//*[contains(normalize-space(),"Volotea Contact Center") or contains(.,"@promotion.volotea.com")]')->length === 0
            && $this->http->XPath->query('//a[contains(@href,".volotea.com/") or contains(@href,"www.volotea.com") or contains(@href,"booking.volotea.com") or contains(@originalsrc, "volotea")]')->length === 0
        ) {
            return false;
        }

        return $this->assignLang();
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        if (!$this->assignLang()) {
            $this->logger->debug("Can't determine a language!");

            return $email;
        }

        $subject = $parser->getSubject();

        if (preg_match_all("/=\?UTF-8\?B\?.*?\?=/iu", $subject, $m)) {
            // transform from: =?utf-8?B?Vm9sb3RlYSDigKIgzpcgzrXPgM65zrLOtc6yzrHOr8+Jz4PO?= =?utf-8?B?tyDPhM63z4IgzrrPgc6sz4TOt8+Dzq7PgiDPg86xz4I6IEFUSC1KTUst?= ATH • 17 Ιουνίου 2023 • N69WWS
            // to: Volotea • Η επιβεβαίωσ?? της κράτησής σας: ATH-JMK- ATH • 17 Ιουνίου 2023 • N69WWS
            foreach ($m[0] as $v) {
                $subject = str_replace($v, mb_decode_mimeheader($v), $subject);
            }
        }

        if (preg_match("/[·:•]\s*([A-Z]{3}( ?- ?[A-Z]{3})+)\s*(?:$|·|•)/", $subject, $m)) {
            $this->subjectRoute = preg_split('/\s*-\s*/', $m[1]);
        }
        $this->year = getdate(strtotime($parser->getHeader('date')))['year'];

        $this->parseEmail($email);
        $email->setType('ConfirmYourReservation' . ucfirst($this->lang));

        return $email;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    private function parseEmail(Email $email): void
    {
        $f = $email->add()->flight();

        // status
        if ($this->http->XPath->query("//*[{$this->contains($this->t('confirmMarkers'))}]")->length > 0) {
            $f->general()->status($this->t('confermato'));
        }

        // confirmation number
        $confirmation = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Partenza alle'))}]/preceding::text()[{$this->contains($this->t('Prenotazione n.'))}][1]");

        if (empty($confirmation)) {
            $confirmation = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Ottieni le carte d’imbarco'))}]/preceding::text()[{$this->contains($this->t('Prenotazione n.'))}][1]");
        }

        if (empty($confirmation)) {
            $confirmation = $this->http->FindSingleNode("//img[contains(@alt, '" . $this->t('Ottieni le carte d’imbarco') . "')]/preceding::text()[{$this->contains($this->t('Prenotazione n.'))}][1]");
        }

        if (preg_match("/({$this->opt($this->t('Prenotazione n.'))})\s*([A-Z\d]{5,})$/", $confirmation, $m)) {
            $f->general()->confirmation($m[2], preg_replace('/\s*:\s*$/', '', $m[1]));
        }

        // segments
        $segments = $this->http->XPath->query("//tr[ ./*[1]/descendant::text()[{$this->eq($this->t('Partenza alle'))}] and ./*[3]/descendant::text()[{$this->eq($this->t('Arrivo alle'))}] ]");

        if ($segments->length === 0) {
            $segments = $this->http->XPath->query("//text()[{$this->eq($this->t('Ottieni le carte d’imbarco'))}]/preceding::text()[contains(translate(normalize-space(.),'0123456789','dddddddddd--'),'d.ddh')]/ancestor::table[3]");
        }

        if ($segments->length === 0) {
            $segments = $this->http->XPath->query("//img[contains(@alt, '" . $this->t('Ottieni le carte d’imbarco') . "')]/preceding::text()[contains(translate(normalize-space(.),'0123456789','dddddddddd--'),'d.ddh')]/ancestor::table[3]");
        }

        foreach ($segments as $si => $segment) {
            $s = $f->addSegment();

            $xpathFragment1 = './preceding::tr[string-length(normalize-space(.))>1][1]/ancestor-or-self::tr[count(./*)>1][1]';

            $date = 0;
            $dateText = $this->http->FindSingleNode($xpathFragment1 . '/*[normalize-space(.)][1]', $segment);

            if (empty($dateText)) {
                $dateText = $this->http->FindSingleNode('preceding::*[self::td or self::th][not(.//td[normalize-space()]) and not(.//th[normalize-space()])][normalize-space()][position() < 3][contains(., ":")]/descendant::h4[1]',
                    $segment, null, "/^.+:\s*$/");
            }

            if (empty($dateText)) {
                $dateText = $this->http->FindSingleNode('./preceding::text()[string-length()>2][1]', $segment);
            }

            if (preg_match("/^(?<date>\d{1,2}[.\s]*[[:alpha:]]{3,})[.\s]*,\s*(?<wday>[-[:alpha:]]{2,})[:\s]*$/u", $dateText, $m)) {
                // 26 Dic, Mercoledì:    |    28 Fév., Lundi:
                $m['date'] = $this->normalizeDate($m['date']);

                $weekDayNumber = WeekTranslate::number1($m['wday']);

                if ($m['date'] && $weekDayNumber) {
                    $date = EmailDateHelper::parseDateUsingWeekDay($m['date'] . ' ' . $this->year, $weekDayNumber);
                }
            }

            // airlineName
            // flightNumber
            $flight = $this->http->FindSingleNode('./preceding::text()[string-length()>1][1]/ancestor::tr[1]', $segment, true, "/([A-Z][A-Z\d]\s*\d{2,4}|[A-Z\d][A-Z]\s*\d{2,4})/");

            if (empty($flight)) {
                $flight = $this->http->FindSingleNode("./following::text()[string-length()>4][1]", $segment, true, "/([A-Z][A-Z\d]\s*\d{2,4}|[A-Z\d][A-Z]\s*\d{2,4})/");
            }

            if (empty($flight)) {
                $flight = $this->http->FindSingleNode(".", $segment, true, "/([A-Z][A-Z\d]\s*\d{2,4}|[A-Z\d][A-Z]\s*\d{2,4})/");
            }

            if (preg_match('/[ ]*\b(?<airline>[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(?<flightNumber>\d+)$/', $flight, $m)) {
                $s->airline()
                    ->name($m['airline'])
                    ->number($m['flightNumber'])
                ;
            }

            /*
                23:35h
                Genova (GOA)
            */

            // OR

            /*
                06:15h
                Athens
            */
            $patterns['airport'] = "/"
                . "(?<time>\d{1,2}[\:\.]+\d{2}(?:\s*[AaPp]\.?[Mm]\.?)?)\s*[Hh]*"
                . "\s+(?<airport>.{3,})"
                . "/";

            $timeDep = null;
            $depText = implode(' ', $this->http->FindNodes('*[1]/descendant::text()[normalize-space()]', $segment));

            if (empty($depText)) {
                $depText = implode(' ', $this->http->FindNodes('./descendant::table[1]/descendant::text()[normalize-space()]', $segment));
            }

            if (preg_match($patterns['airport'], $depText, $m)) {
                $timeDep = $m['time'];

                if (preg_match("/\(\s*([A-Z]{3})\s*\)/", $m['airport'], $matches)) {
                    // depCode
                    $s->departure()->code($matches[1]);
                } elseif (!empty($this->subjectRoute) && count($this->subjectRoute) === $segments->length + 1) {
                    // depCode
                    $s->departure()->code($this->subjectRoute[$si]);

                    if (preg_match("/^(.+)\s+[A-Z\d]{2}\s+\d{1,4}\s+\d+/", $m['airport'], $match)) {
                        if (preg_match("/^(.+)\s*\(T(.+)\)$/", $match[1], $mt)) {
                            $s->departure()
                                ->terminal($mt[2])
                                ->name($mt[1]);
                        } else {
                            $s->departure()
                                ->name($match[1]);
                        }
                    }
                } else {
                    // depName
                    $s->departure()
                        ->name($m['airport'])
                        ->noCode();
                }
            }

            $timeArr = null;
            $arrText = implode(' ', $this->http->FindNodes('*[3]/descendant::text()[normalize-space()]', $segment));

            if (empty($arrText)) {
                $arrText = implode(' ', $this->http->FindNodes('./descendant::table[5]/descendant::text()[normalize-space()]', $segment));
            }

            if (preg_match($patterns['airport'], $arrText, $m)) {
                $timeArr = $m['time'];

                if (preg_match("/\(\s*([A-Z]{3})\s*\)/", $m['airport'], $matches)) {
                    // arrCode
                    $s->arrival()
                        ->code($matches[1]);
                } elseif (!empty($this->subjectRoute) && count($this->subjectRoute) === $segments->length + 1) {
                    // arrCode
                    $s->arrival()
                        ->name($m['airport'])
                        ->code($this->subjectRoute[$si + 1]);
                } else {
                    // arrName
                    $s->arrival()
                        ->name($m['airport'])
                        ->noCode();
                }
            }

            // depDate
            if ($date && $timeDep) {
                $s->departure()->date(strtotime($timeDep, $date));
            }

            // arrDate
            if ($date && $timeArr) {
                $s->arrival()->date(strtotime($timeArr, $date));
            }
        }

        $travellers = [];

        if ($segments->length == 1) {
            $rootSeats = $this->http->XPath->query("//text()[{$this->eq($this->t('Seats'))}]/ancestor::table[1]/following::table[1]/descendant::table[normalize-space()]");

            if ($rootSeats->length === 0) {
                $rootSeats = $this->http->XPath->query("//text()[{$this->eq($this->t('Seats'))}]/ancestor::table[./following-sibling::table][1]/following-sibling::table[string-length(normalize-space())>3]");
            }

            foreach ($rootSeats as $r) {
                if ($this->http->XPath->query(".//img", $r)->length > 0) {
                    break;
                }
                $rows = $this->http->FindNodes("./descendant::tr[not(.//tr)]//descendant::text()[normalize-space()!='']", $r);

                if (count($rows) % 2 == 0) {
                    foreach ($rows as $i=>$value) {
                        if ($i % 2 == 1) {
                            continue;
                        }
                        $value = preg_replace("/^(?:Mme|Mr|Mrs|M|Hr|Κα|Fr)\s*[\.\s]\s*(.{2,})$/i", '$1', $value);

                        $travellers[] = $value;

                        if (isset($rows[$i + 1]) && preg_match("#^\d+[A-z]$#", $rows[$i + 1])) {
                            $s = $f->getSegments()[0];

                            if (!empty($value)) {
                                $s->extra()->seat($rows[$i + 1], true, true, $value);
                            } else {
                                $s->extra()->seat($rows[$i + 1]);
                            }
                        }
                    }
                }
            }
        }

        // travellers
        if (count($travellers) === 0) {
            $passengers = $this->http->FindNodes("//p[{$this->starts($this->t('passengers'))}]/following-sibling::p/descendant::text()[normalize-space(.)!=''][1]",
                null, '/^[[:alpha:]][-.\'’[:alpha:]\s]*[[:alpha:]]$/u');
            $passengers = array_unique(array_values(array_filter($passengers)));

            if (!empty($passengers[0])) {
                $travellers = array_merge($travellers, $passengers);
            }
        }

        if (count($travellers) === 0) {
            $travellers = array_filter($this->http->FindNodes("//text()[{$this->starts($this->t('Passengers'))}]/ancestor::tr[1]/following::table[1]/descendant::tr[contains(normalize-space(), '(')]/descendant::text()[normalize-space()][1]"));
        }

        if (count($travellers) === 0) {
            $travellers = array_filter([$this->http->FindSingleNode("//text()[{$this->contains($this->t('in a phrase'))}]", null, true, "/^(\w+)\,/")]);
        }

        if (count($travellers) === 0) {
            $travellers = array_filter($this->http->FindNodes("//text()[{$this->eq($this->t('Included for all passengers, all journeys.'))}]/ancestor::tr[1]/following-sibling::tr/descendant::text()[{$this->starts($this->t('prefixName'))}]"));
        }

        if (count($travellers) === 0) {
            $travellers = array_filter([$this->http->FindSingleNode("//text()[{$this->contains($this->t('in a phrase'))}]", null, true, "/^(\w+)\,/")]);
        }

        if ($segments->length > 1) {
            foreach ($segments as $i => $segment) {
                $seats = [];

                foreach ($travellers as $pax) {
                    $seatPaxArray = explode(' • ', $this->http->FindSingleNode("//text()[{$this->eq($this->t('Seats'))}]/following::text()[{$this->eq($pax)}][1]/following::text()[normalize-space()][1]"));

                    if (isset($seatPaxArray[$i])) {
                        $seats[] = $seatPaxArray[$i];
                    }
                }
                $s = $f->getSegments()[$i];
                $s->extra()->seats($seats);
            }
        }

        foreach (array_unique($travellers) as $tName) {
            $f->general()->traveller(preg_replace("/^(?:Mme|Mr|Mrs|M|Hr|Κα|Fr)\s*[\.\s]\s*(.{2,})$/i", '$1', $tName));
        }

        // p.total
        // p.currencyCode
        $payments = $this->http->FindNodes("//p[{$this->starts($this->t('Importo pagato con'))}]/following-sibling::node()[string-length(normalize-space(.))>1][1]", null, '/^(?:\d[ ,.\'\d]*\s*[^\d)(]+|[^\d)(]+\s*\d[ ,.\'\d]*)$/');

        foreach ($payments as $payment) {
            if (preg_match('/^(?<amount>\d[ ,.\'\d]*)\s*(?<currency>[^\-\d)(]+)$/', $payment, $m)
                || preg_match('/^(?<currency>[^\-\d)(]+?)\s*(?<amount>\d[ ,.\'\d]*)$/', $payment, $m)
            ) {
                // 378,20€    |    €17.99
                if (!isset($cur)) {
                    $cur = $m['currency'];
                } elseif (isset($cur) && $cur !== $m['currency']) {
                    $cur = $sum = null;

                    break;
                }
                $currencyCode = preg_match('/^[A-Z]{3}$/', $m['currency']) ? $m['currency'] : null;
                $sum[] = PriceHelper::parse($m['amount'], $currencyCode);
            }
        }

        if (isset($cur, $sum)) {
            $f->price()
                ->total(array_sum($sum))
                ->currency($this->normalizeCurrency($cur));
        }
    }

    /**
     * Dependencies `use AwardWallet\Engine\MonthTranslate` and `$this->lang`.
     *
     * @return string|null
     */
    private function normalizeDate(string $string)
    {
        if (preg_match('/^(\d{1,2})\s*([^\d\W]{3,})$/u', $string, $matches)) {
            // 26 Dic
            $day = $matches[1];
            $month = $matches[2];
            $year = '';
        }

        if (isset($day, $month, $year)) {
            if (preg_match('/^\s*(\d{1,2})\s*$/', $month, $m)) {
                return $m[1] . '/' . $day . ($year ? '/' . $year : '');
            }

            if (($monthNew = MonthTranslate::translate($month, $this->lang)) !== false) {
                $month = $monthNew;
            }

            return $day . ' ' . $month . ($year ? ' ' . $year : '');
        }

        return null;
    }

    private function eq($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'normalize-space(' . $node . ')="' . $s . '"';
        }, $field)) . ')';
    }

    private function starts($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'starts-with(normalize-space(' . $node . '),"' . $s . '")';
        }, $field)) . ')';
    }

    private function contains($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'contains(normalize-space(' . $node . '),"' . $s . '")';
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

    private function t($phrase)
    {
        if (!isset(self::$dict[$this->lang]) || !isset(self::$dict[$this->lang][$phrase])) {
            return $phrase;
        }

        return self::$dict[$this->lang][$phrase];
    }

    private function assignLang(): bool
    {
        foreach ($this->langDetectors as $lang => $phrases) {
            foreach ($phrases as $phrase) {
                if ($this->http->XPath->query('//*[contains(normalize-space(),"' . $phrase . '")]')->length > 0) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function normalizeCurrency(string $string): string
    {
        $string = trim($string);
        $currencies = [
            'GBP' => ['£'],
            'EUR' => ['€'],
            'USD' => ['US$'],
            'BRL' => ['R$'],
        ];

        foreach ($currencies as $currencyCode => $currencyFormats) {
            foreach ($currencyFormats as $currency) {
                if ($string === $currency) {
                    return $currencyCode;
                }
            }
        }

        return $string;
    }
}
