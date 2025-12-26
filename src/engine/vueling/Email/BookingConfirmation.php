<?php

namespace AwardWallet\Engine\vueling\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class BookingConfirmation extends \TAccountChecker
{
    public $mailFiles = "vueling/it-10819552.eml, vueling/it-10820360.eml, vueling/it-10831402.eml, vueling/it-10831492.eml, vueling/it-10837864.eml, vueling/it-10848621.eml, vueling/it-10849061.eml, vueling/it-10850934.eml, vueling/it-10852103.eml, vueling/it-10853745.eml, vueling/it-10868838.eml, vueling/it-10911583.eml, vueling/it-10970631.eml, vueling/it-10978506.eml, vueling/it-11041649.eml, vueling/it-629469638.eml, vueling/it-636196051-es.eml, vueling/it-686007034.eml, vueling/it-859736174.eml, vueling/it-865155792.eml";

    public $reFrom = "vueling.com";

    public $reSubject = [
        'pt'  => '/Confirmação da sua reserva[- ]+[A-Z\d]{5,}[- ]+[A-Z]{3}\s*-\s*[A-Z]{3}\s+\d{1,2}\s+[[:alpha:]]+/u',
        'en'  => '/Your booking confirmation[- ]+[A-Z\d]{5,}[- ]+[A-Z]{3}\s*-\s*[A-Z]{3}\s+\d{1,2}\s+[[:alpha:]]+/',
        'en2' => '/Confirmation of changes to your booking[- ]+[A-Z]{3}\s*-\s*[A-Z]{3}\s+\d{1,2}\s+[[:alpha:]]+/u',
        'es'  => '/Confirmación de tu reserva[- ]+[A-Z\d]{5,}[- ]+[A-Z]{3}\s*-\s*[A-Z]{3}\s+\d{1,2}\s+[[:alpha:]]+/u',
        'es2' => '/Confirmación de cambios en tu reserva[- ]+[A-Z]{3}\s*-\s*[A-Z]{3}\s+\d{1,2}\s+[[:alpha:]]+/u',
        'ca'  => '/Confirmació de la reserva[- ]+[A-Z\d]{5,}[- ]+[A-Z]{3}\s*-\s*[A-Z]{3}\s+\d{1,2}\s+[[:alpha:]]+/u',
        'it'  => '/Conferma della prenotazione[- ]+[A-Z\d]{5,}[- ]+[A-Z]{3}\s*-\s*[A-Z]{3}\s+\d{1,2}\s+[[:alpha:]]+/u',
        'fr'  => '/Confirmation de votre réservation[- ]+[A-Z\d]{5,}[- ]+[A-Z]{3}\s*-\s*[A-Z]{3}\s+\d{1,2}\s+[[:alpha:]]+/u',
        'nl'  => '/Bevestiging van je reservering[- ]+[A-Z\d]{5,}[- ]+[A-Z]{3}\s*-\s*[A-Z]{3}\s+\d{1,2}\s+[[:alpha:]]+/u',
        'de'  => '/Bestätigung Ihrer Buchung[- ]+[A-Z\d]{5,}[- ]+[A-Z]{3}\s*-\s*[A-Z]{3}\s+\d{1,2}\s+[[:alpha:]]+/u',
    ];
    public $lang = '';
    public static $dict = [
        'pt' => [
            'statusPhrases'   => ['reserva', 'Reserva'],
            'statusVariants'  => ['confirmada'],
            'Outbound'        => ['Ida', 'Volta'],
            // 'connectionOf' => '',
            // 'at' => '',
            //			'Flight' => '',
            'Booking code'    => ['Código de reserva', 'Código de reserva:', 'Código de reserva :'],
            'Seat'            => 'Lugar',
            // 'nonTraveller' => '',
            'adult'           => 'adultos',
            'Payment details' => 'Detalhes do pagamento',
            'Passengers'      => 'Passageiros',
            // 'Status' => '',
        ],
        'es' => [
            'statusPhrases'     => ['reserva', 'Reserva'],
            'statusVariants'    => ['confirmada'],
            'Outbound'          => ['Ida', 'Vuelta', 'Vuelo', 'Anada'],
            // 'connectionOf' => '',
            // 'at' => '',
            'Flight'            => 'Vuelo',
            'Booking code'      => ['Código de reserva', 'Código de reserva:', 'Código de reserva :'],
            'Seat'              => 'Asiento',
            // 'nonTraveller' => '',
            'adult'             => 'adultos',
            'Payment details'   => ['Detalles del pago', 'Detalle de los pagos'],
            // 'Total' => '',
            'Passengers'        => 'Pasajeros',
            'Status'            => 'Estado',
        ],
        'ca' => [
            'statusPhrases'     => ['reserva', 'Reserva'],
            'statusVariants'    => ['confirmada'],
            'Outbound'          => ['Anada', 'Tornada', 'Basic'],
            // 'connectionOf' => '',
            // 'at' => '',
            'Flight'            => 'Vol',
            'Booking code'      => [
                'Codi de reserva', 'Codi de reserva:', 'Codi de reserva :',
                'Codi de la reserva', 'Codi de la reserva:', 'Codi de la reserva :',
            ],
            'Seat'              => 'Seient',
            // 'nonTraveller' => '',
            'adult'             => 'adults',
            'Payment details'   => ['Detalls del pagament', 'Detall dels pagaments'],
            // 'Total' => '',
            'Passengers'        => 'Passatgers',
            'Status'            => 'Estat',
        ],
        'it' => [
            'statusPhrases'     => ['prenotazione', 'Prenotazione'],
            'statusVariants'    => ['confermata'],
            'Outbound'          => ['Andata', 'Ritorno', 'Volo'],
            'connectionOf'      => 'Connessione di',
            'at'                => 'a',
            'Flight'            => 'Volo',
            'Booking code'      => ['Codice di prenotazione', 'Codice di prenotazione:', 'Codice di prenotazione :'],
            'Seat'              => 'Posto',
            // 'nonTraveller' => '',
            'adult'             => ['adulto', 'Adulto'],
            'Payment details'   => ['Dettaglio dei pagamenti', 'Dati pagamento'],
            'Total'             => 'Totale',
            'Passengers'        => 'Passeggeri',
            'Status'            => 'Stato',
        ],
        'fr' => [
            'statusPhrases'     => ['réservation', 'Réservation', 'votre réservation est', 'Votre réservation est'],
            'statusVariants'    => ['confirmée'],
            'Outbound'          => ['Aller', 'Retour', 'Vol'],
            // 'connectionOf' => '',
            // 'at' => '',
            'Flight'            => 'Vol',
            'Booking code'      => ['Code de réservation', 'Code de réservation:', 'Code de réservation :'],
            'Seat'              => 'Place',
            // 'nonTraveller' => '',
            'adult'             => 'adulte',
            'Payment details'   => ['Détails du paiement', 'Détail des paiements'],
            'Total'             => 'Total',
            'Passengers'        => 'Passagers',
            'Status'            => 'Statut',
        ],
        'nl' => [
            'statusPhrases'     => ['reservering', 'Reservering'],
            'statusVariants'    => ['bevestigd'],
            'Outbound'          => ['Heenvlucht', 'Terugvlucht', 'Heen', 'Terug'],
            // 'connectionOf' => '',
            // 'at' => '',
            //			'Flight' => '',
            'Booking code'    => ['Reserveringsnummer', 'Reserveringsnummer:', 'Reserveringsnummer :'],
            'Seat'            => 'Stoel',
            // 'nonTraveller' => '',
            'adult'           => 'volwassenen',
            'Payment details' => 'Betalingsdetails',
            'Total'           => 'Totaal',
            'Passengers'      => 'Passagiers',
            // 'Status' => '',
        ],
        'de' => [
            'statusPhrases'     => ['buchung', 'Buchung'],
            'statusVariants'    => ['bestätigt'],
            'Outbound'          => ['Hinflug', 'Rückflug', 'Flug'],
            'connectionOf'      => 'Umsteigezeit von',
            'at'                => 'in',
            'Flight'            => 'Flug',
            'Booking code'      => ['Buchungscode', 'Buchungscode:', 'Buchungscode :'],
            'Seat'              => 'Sitzplatz',
            // 'nonTraveller' => '',
            'adult'             => 'Erwachsene',
            'Payment details'   => 'Zahlungsdaten',
            'Total'             => 'Insgesamt',
            'Passengers'        => 'Passagieren',
            // 'Status' => '',
        ],
        'en' => [
            'statusPhrases'   => ['booking', 'Booking'],
            'statusVariants'  => ['confirmed'],
            'Outbound'        => ['Outbound', 'Return', 'Flight', 'Flight', 'One way'],
            'connectionOf'    => 'Connection of',
            // 'at' => '',
            'Booking code'    => ['Booking code', 'Booking code:', 'Booking code :', 'Price Lock code:', 'Booking:'],
            'nonTraveller'    => ['Check-in', 'Check-In'],
            'Payment details' => ['Payment details'],
        ],
    ];

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();

        if (empty($this->lang)) {
            $this->logger->debug("Can't determine a language!");

            return $email;
        }
        $this->parseEmail($email);

        $a = explode('\\', __CLASS__);
        $class = end($a);
        $email->setType($class . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//img[@alt='Vueling'] | //a[contains(@href,'vueling.com')] | //text()[{$this->contains('Vueling Airlines')}]")->length > 0) {
            return $this->assignLang();
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], $this->reFrom) !== false && isset($this->reSubject)) {
            foreach ($this->reSubject as $reSubject) {
                if (preg_match($reSubject, $headers["subject"])) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->reFrom) !== false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    public function dateStringToEnglish($date)
    {
        if (preg_match('#[[:alpha:]]+#iu', $date, $m)) {
            $monthNameOriginal = $m[0];

            if ($translatedMonthName = MonthTranslate::translate($monthNameOriginal, $this->lang)) {
                return preg_replace("#$monthNameOriginal#i", $translatedMonthName, $date);
            }
        }

        return $date;
    }

    private function parseEmail(Email $email): void
    {
        $xpathAirportCode = 'translate(normalize-space(),"ABCDEFGHIJKLMNOPQRSTUVWXYZ","∆∆∆∆∆∆∆∆∆∆∆∆∆∆∆∆∆∆∆∆∆∆∆∆∆∆")="∆∆∆"';

        $patterns = [
            'time'          => '\d{1,2}[:：]\d{2}(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?)?', // 4:19PM    |    2:00 p. m.
            'travellerName' => '[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]]', // Mr. Hao-Li Huang
        ];

        $f = $email->add()->flight();

        $statusTexts = array_filter($this->http->FindNodes("//text()[{$this->contains($this->t('statusPhrases'))}]", null, "/{$this->opt($this->t('statusPhrases'))}[:\s]+({$this->opt($this->t('statusVariants'))})(?:\s*[,.;:!?]|$)/i"));

        if (count(array_unique($statusTexts)) === 1) {
            $status = array_shift($statusTexts);
        } else {
            $status = $this->http->FindSingleNode("(//tr/*[{$this->starts($this->t('Status'))} and not(.//tr)]/following-sibling::*[normalize-space()][1])[1]", null, true, '/^[^:]+$/');
        }

        if ($status) {
            $f->general()->status($status);
        }

        $f->general()->confirmation($this->http->FindSingleNode("//text()[{$this->eq($this->t('Booking code'))} and not(preceding::tr[{$this->eq($this->t('Payment details'))}])]/following::text()[normalize-space()][1]"));

        $travellerTexts = $this->http->FindNodes("//text()[{$this->eq($this->t('Seat'), 'translate(.,"*","")')}]/ancestor::*[ following-sibling::*[self::td or self::th] ][1]/../ancestor-or-self::tr[ preceding-sibling::tr[normalize-space() and not({$this->contains($this->t('nonTraveller'))} or {$this->contains(['Check-in', 'Check-In'])})] ][1]/preceding-sibling::tr[normalize-space()][1]");
        $travellers = array_filter(array_map(function ($item) use ($patterns) {
            return preg_match("/^({$patterns['travellerName']})(?:\s*[+]\s*(?:infant|bebé|[[:alpha:]]{3,6}))?$/iu", $item, $m) > 0 ? $m[1] : null;
        }, $travellerTexts));

        if (count($travellers) > 0 && count($travellerTexts) === count($travellers)) {
            $f->general()->travellers($travellers, true);
        } elseif (count($travellers) === 0) {
            $travellerNames = array_filter($this->http->FindNodes("//text()[{$this->starts($this->t('Hi'))}]", null, "/^{$this->opt($this->t('Hi'))}[,\s]+({$patterns['travellerName']})(?:\s*[,;:!?]|$)/u"));

            if (count($travellerNames) === 0) {
                $travellerNames = array_filter($this->http->FindNodes("//text()[{$this->contains($this->t('check in online and get your seat'))}]/ancestor::tr[1]", null, "/^(.+)\,\s+{$this->opt($this->t('check in online and get your seat'))}/"));
            }

            if (count(array_unique($travellerNames)) === 1) {
                $traveller = array_shift($travellerNames);
                $f->general()->traveller($traveller);
            }
        }

        $cnt = $this->http->FindSingleNode("//text()[{$this->contains($this->t('adult'))}]/ancestor::tr[1]", null, true,
            "#(\d+)\s+{$this->opt($this->t('adult'))}#");

        if ($cnt == count($f->getTravellers())) {
            $tot = $this->getTotalCurrency($this->http->FindSingleNode("//text()[{$this->contains($this->t('adult'))}]/ancestor::tr[1]/td[normalize-space(.)!=''][2]"));

            if ($tot['Total'] !== '') {
                $f->price()
                    ->cost($tot['Total'])
                    ->currency($tot['Currency']);
            }
        }
        $tot = $this->getTotalCurrency($this->http->FindSingleNode("//tr[{$this->eq($this->t('Payment details'))}]/following::tr[not(.//tr) and {$this->starts($this->t('Total'))}][last()]", null, true, "/^{$this->opt($this->t('Total'))}[\s:]+(.*\d.*)$/"));

        if ($tot['Total'] !== '') {
            $f->price()
                ->total($tot['Total'])
                ->currency($tot['Currency']);
        }

        // segments
        $xpathTime = '(starts-with(translate(translate(.," ",""),"0123456789：","∆∆∆∆∆∆∆∆∆∆:"),"∆:∆∆") or starts-with(translate(translate(.," ",""),"0123456789：","∆∆∆∆∆∆∆∆∆∆:"),"∆∆:∆∆"))';
        $segments = $this->http->XPath->query("//tr[not(.//tr[normalize-space()]) and count(*[normalize-space()])=2 and count(*[{$xpathTime}])=2]");

        if ($segments->length === 0) {
            $segments = $this->http->XPath->query("//text()[starts-with(normalize-space(), 'Connection of')]/ancestor::tr[1]/following::img[contains(@src, 'plane_right')][1]/ancestor::tr[1]/descendant::img[contains(@src, 'plane_right')]/ancestor::tr[1]");
        }

        for ($i = 0; $i < $segments->length; $i++) {
            $root = $segments->item($i);

            $cabin = $this->http->FindSingleNode("(preceding::tr[{$this->contains($this->t('Outbound'))}][1]//text()[normalize-space()])[last()]", $root);
            $date = strtotime($this->normalizeDate($this->http->FindSingleNode("preceding::tr[{$this->starts($this->t('Outbound'))}][1]/following::tr[not(.//tr) and normalize-space()][1]", $root, true, "/^.*\b\d{4}\b.*$/")));

            if (empty($date)) {
                $date = strtotime($this->normalizeDate($this->http->FindSingleNode("./preceding::text()[contains(translate(translate(.,' ',''),'0123456789：','∆∆∆∆∆∆∆∆∆∆:'),'∆∆∆∆')][1]", $root, true, "/^.*\b\d{4}\b.*$/")));
            }

            $nameDep = $this->http->FindSingleNode("preceding::tr[not(.//tr[normalize-space()]) and normalize-space() and not(*[{$this->starts($this->t('Terminal'))}])][position()<3]/*[normalize-space()][1][not({$xpathAirportCode})]", $root);
            $nameArr = $this->http->FindSingleNode("preceding::tr[not(.//tr[normalize-space()]) and normalize-space() and not(*[{$this->starts($this->t('Terminal'))}])][position()<3]/*[normalize-space()][2][not({$xpathAirportCode})]", $root);
            $nameTerminalDep = $this->nameTerminal($nameDep);
            $nameTerminalArr = $this->nameTerminal($nameArr);

            $codeDep = $this->http->FindSingleNode("preceding::tr[ *[normalize-space()][1][not(.//tr[normalize-space()])] ][position()<3]/*[normalize-space()][1][{$xpathAirportCode}]", $root);
            $codeArr = $this->http->FindSingleNode("preceding::tr[ *[normalize-space()][1][not(.//tr[normalize-space()])] ][position()<3]/*[normalize-space()][2][{$xpathAirportCode}]", $root);

            $xpathTerminals = "preceding::tr[not(.//tr[normalize-space()]) and normalize-space()][1]";
            $terminalDep = $this->http->FindSingleNode($xpathTerminals . "/*[normalize-space() and normalize-space(@align)='left']", $root, true, $pattern = "/^{$this->opt($this->t('Terminal'))}[-\s]+([-A-z\d\s]+)$/i")
            ?? $this->http->FindSingleNode($xpathTerminals . "[count(*[normalize-space()])=2]/*[normalize-space()][1]", $root, true, $pattern);
            $terminalArr = $this->http->FindSingleNode($xpathTerminals . "/*[normalize-space() and normalize-space(@align)='right']", $root, true, $pattern)
            ?? $this->http->FindSingleNode($xpathTerminals . "[count(*[normalize-space()])=2]/*[normalize-space()][2]", $root, true, $pattern);

            $timeDep = $this->http->FindSingleNode("*[normalize-space()][1]", $root, true, "/{$patterns['time']}/");
            $timeArr = $this->http->FindSingleNode("*[normalize-space()][2]", $root, true, "/{$patterns['time']}/");
            $flight = $this->http->FindSingleNode("following::tr[ *[normalize-space()][1][not(.//tr)] ][1]/*[normalize-space()][1]", $root);

            $connectionCode = $this->http->FindSingleNode("preceding::tr[{$this->starts($this->t('Outbound'))}][1]/following::tr[not(.//tr) and normalize-space()][2][{$this->starts($this->t('connectionOf'))}]", $root, true, "/\s{$this->opt($this->t('at'))}\s+([A-Z]{3})[.\s]*$/");

            if ($connectionCode) { // it-629469638.eml
                $this->logger->debug('Segment type: CONNECTED.');

                $s = $f->addSegment();
                $s->extra()->cabin($cabin);

                $s->departure()
                    ->name($nameTerminalDep['name'])
                    ->terminal($terminalDep ?? $nameTerminalDep['terminal'], false, true)
                    ->code($codeDep)
                    ->date(strtotime($timeDep, $date));

                $s->arrival()
                    ->code($connectionCode)
                    ->date(strtotime($timeArr, $date));

                if (preg_match("/^([A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(\d+)$/", $flight, $m)) {
                    $s->airline()->name($m[1])->number($m[2]);
                }

                // + connected flight

                $i++;
                $s = $f->addSegment();

                if (!empty($cabin)) {
                    $s->extra()
                        ->cabin($cabin);
                }

                $xpathSeg2 = "ancestor::table[1]/ancestor-or-self::*[ following-sibling::*[normalize-space()] ][1]/following-sibling::*[normalize-space()][1][not(following-sibling::*[normalize-space()])]/descendant::tr[not(.//tr[normalize-space()]) and count(*[normalize-space()])=2 and count(*[{$xpathTime}])=2]";
                $timeDep2 = $this->http->FindSingleNode($xpathSeg2 . "/*[normalize-space()][1]", $root, true, "/{$patterns['time']}/");
                $timeArr2 = $this->http->FindSingleNode($xpathSeg2 . "/*[normalize-space()][2]", $root, true, "/{$patterns['time']}/");

                $s->departure()->code($connectionCode)->date(strtotime($timeDep2, $date));
                $s->arrival()->name($nameTerminalArr['name'])->terminal($terminalArr ?? $nameTerminalArr['terminal'], false, true)->code($codeArr)->date(strtotime($timeArr2, $date));

                $flight2 = $this->http->FindSingleNode($xpathSeg2 . "/following::tr[ *[normalize-space()][1][not(.//tr)] ][1]/*[normalize-space()][1]", $root);

                if (preg_match('/^([A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(\d+)$/', $flight2, $m)) {
                    $s->airline()->name($m[1])->number($m[2]);
                }
            } elseif (empty($connectionCode) && $this->http->XPath->query("./descendant::img[contains(@src, 'plane_right')]", $root)->length === 2) {
                $this->logger->debug('Segment type: CONNECTED 2.');
                $date = $this->http->FindSingleNode("./preceding::text()[{$this->starts($this->t('connectionOf'))}][1]/preceding::text()[contains(translate(translate(.,' ',''),'0123456789：','∆∆∆∆∆∆∆∆∆∆:'),'∆∆∆∆')][1]", $root);
                $s = $f->addSegment();
                $s2 = $f->addSegment();

                $codeText = $this->http->FindSingleNode(".", $root);

                if (preg_match("/^(?<fisrt>[A-Z]{3})\s*(?<connection>[A-Z]{3})\s*(?<last>[A-Z]{3})$/", $codeText, $m)) {
                    $s->departure()
                        ->code($m['fisrt']);
                    $s->arrival()
                        ->code($m['connection']);

                    $s2->departure()
                        ->code($m['connection']);
                    $s2->arrival()
                        ->code($m['last']);
                }

                $timeText = $this->http->FindSingleNode("./following::tr[contains(normalize-space(), ':')][1]", $root);

                if (preg_match("/^(?<fDepTime>[\d\:]+)h\s*(?<fArrTime>[\d\:]+)h\s*(?<sDepTime>[\d\:]+)h\s*(?<sArrTime>[\d\:]+)h$/", $timeText, $m)) {
                    $s->departure()
                        ->date(strtotime($date . ', ' . $m['fDepTime']));
                    $s->arrival()
                        ->date(strtotime($date . ', ' . $m['fArrTime']));

                    $s2->departure()
                        ->date(strtotime($date . ', ' . $m['sDepTime']));
                    $s2->arrival()
                        ->date(strtotime($date . ', ' . $m['sArrTime']));
                }

                $flightInfo = $this->http->FindSingleNode("./following::tr[contains(normalize-space(), ':')][1]/following::text()[normalize-space()][1]/ancestor::tr[1]/descendant::text()[normalize-space()][1]", $root);

                if (preg_match("/^(?<aName>([A-Z][A-Z\d]|[A-Z\d][A-Z]))(?<fNUmber>\d{1,4})$/", $flightInfo, $m)) {
                    $s->airline()
                        ->name($m['aName'])
                        ->number($m['fNUmber']);
                }
                $flightInfo2 = $this->http->FindSingleNode("./following::tr[contains(normalize-space(), ':')][1]/following::text()[normalize-space()][1]/ancestor::tr[1]/descendant::text()[normalize-space()][2]", $root);

                if (preg_match("/^(?<aName>([A-Z][A-Z\d]|[A-Z\d][A-Z]))(?<fNUmber>\d{1,4})$/", $flightInfo2, $m)) {
                    $s2->airline()
                        ->name($m['aName'])
                        ->number($m['fNUmber']);
                }
            } else {
                $this->logger->debug('Segment type: normal.');

                $s = $f->addSegment();

                if (!empty($cabin)) {
                    $s->extra()
                        ->cabin($cabin);
                }

                $s->departure()
                    ->name($nameTerminalDep['name'])
                    ->terminal($terminalDep ?? $nameTerminalDep['terminal'], false, true)
                    ->code($codeDep)
                    ->date(strtotime($timeDep, $date));

                $s->arrival()->name($nameTerminalArr['name'])
                    ->terminal($terminalArr ?? $nameTerminalArr['terminal'], false, true)
                    ->code($codeArr)
                    ->date(strtotime($timeArr, $date));

                if (preg_match('/^([A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(\d+)$/', $flight, $m)) {
                    $s->airline()->name($m[1])->number($m[2]);
                }
            }
        }

        // Seats parsing
        $allSeats = [];
        $seatRoots = $this->http->XPath->query("//text()[{$this->eq($this->t('Seat'), 'translate(.,"*","")')}]/ancestor::*[ following-sibling::*[self::td or self::th] ][1]/..");
        $seatSegCount = null;

        foreach ($seatRoots as $sRoot) {
            $pax = $this->http->FindSingleNode("preceding::text()[normalize-space()][not({$this->contains($this->t('nonTraveller'))})][1]", $sRoot);
            $seatsByPassenger = preg_split('/\s*\/\s*/', implode('/', $this->http->FindNodes("*[normalize-space() and not({$this->eq($this->t('Seat'), 'translate(.,"*","")')})]", $sRoot)));

            if (!empty($seatSegCount) && count($seatsByPassenger) !== $seatSegCount) {
                $allSeats = [];
                $this->logger->error('seats parsing error: $seatSegCount');

                break;
            }

            foreach ($seatsByPassenger as $i => $seat) {
                $allSeats[$i]['seats'][] = str_replace('-', '', $seat);
                $allSeats[$i]['travellers'][] = $pax;
            }
        }

        foreach ($allSeats as $i => $aSeats) {
            $allSeats[$i]['seats'] = array_filter($aSeats['seats']);
        }

        if (empty($allSeats) || count($allSeats) === count($f->getSegments())) {
        } elseif (count($allSeats) === (count($f->getSegments()) + 1) && empty($allSeats[count($allSeats) - 1]['seats'])) {
            unset($allSeats[count($allSeats) - 1]);
        }
        // $this->logger->debug('$allSeats = '.print_r( $allSeats,true));

        if (count($allSeats) !== count($f->getSegments())) {
            $this->logger->error('seats parsing error: count($allSeats)');
            $allSeats = [];
        }

        foreach ($f->getSegments() as $i => $s) {
            if (!empty($allSeats[$i]['seats'])) {
                foreach ($allSeats[$i]['seats'] as $j => $seat) {
                    $s->extra()->seat($seat, false, false, $allSeats[$i]['travellers'][$j]);
                }
            }
        }
    }

    private function nameTerminal(?string $name): array
    {
        $result = ['name' => $name, 'terminal' => null];

        if (preg_match('/^(?<name>.{2,}?)\s*\(\s*T(?<terminal>[A-Z\d]?)\s*\)$/', $name, $m)) {
            $result['name'] = $m['name'];

            if (!empty($m['terminal'])) {
                $result['terminal'] = $m['terminal'];
            }
        }

        return $result;
    }

    private function normalizeDate($date)
    {
        $in = [
            // Tuesday, 11 of June of 2024    |    Dimecres, 09 De Juny 2021
            '/^[-[:alpha:]]+[\s,]+(\d{1,2})\s*(?:[[:alpha:]]+\s+)?([[:alpha:]]+)(?:\s+[[:alpha:]]+)?\s*(\d{4})$/iu',
        ];
        $out = [
            '$1 $2 $3',
        ];
        $str = $this->dateStringToEnglish(preg_replace($in, $out, $date));

        return $str;
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function assignLang(): bool
    {
        if (!isset(self::$dict, $this->lang)) {
            return false;
        }

        $assignLanguages = array_keys(self::$dict);

        foreach ($assignLanguages as $i => $lang) {
            if (!is_string($lang) || empty(self::$dict[$lang]['Booking code'])
                || $this->http->XPath->query("//*[{$this->contains(self::$dict[$lang]['Booking code'])}]")->length === 0
            ) {
                unset($assignLanguages[$i]);
            }
        }

        if (count($assignLanguages) > 1) {
            foreach ($assignLanguages as $i => $lang) {
                if (!is_string($lang) || empty(self::$dict[$lang]['Passengers'])
                    || $this->http->XPath->query("//tr/*[{$this->eq(self::$dict[$lang]['Passengers'])}]")->length === 0
                ) {
                    unset($assignLanguages[$i]);
                }
            }
        }

        if (count($assignLanguages) === 1) {
            $this->lang = array_shift($assignLanguages);

            return true;
        }

        return false;
    }

    private function getTotalCurrency($node): array
    {
        $node = str_replace("€", "EUR", $node);
        $node = str_replace("$", "USD", $node);
        $node = str_replace("£", "GBP", $node);
        $tot = '';
        $cur = '';

        if (preg_match("#(?<c>[A-Z]{3})\s*(?<t>\d[\.\d\,\s]*\d*)#", $node,
                $m) || preg_match("#(?<t>\d[\.\d\,\s]*\d*)\s*(?<c>[A-Z]{3})#", $node,
                $m) || preg_match("#(?<c>\-*?)(?<t>\d[\.\d\,\s]*\d*)#", $node, $m)
        ) {
            $currencyCode = preg_match('/^[A-Z]{3}$/', $m['c']) ? $m['c'] : null;
            $tot = PriceHelper::parse($m['t'], $currencyCode);
            $cur = $m['c'];
        }

        return ['Total' => $tot, 'Currency' => $cur];
    }

    private function contains($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';

            return 'contains(normalize-space(' . $node . '),' . $s . ')';
        }, $field)) . ')';
    }

    private function eq($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';

            return 'normalize-space(' . $node . ')=' . $s;
        }, $field)) . ')';
    }

    private function starts($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';

            return 'starts-with(normalize-space(' . $node . '),' . $s . ')';
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
