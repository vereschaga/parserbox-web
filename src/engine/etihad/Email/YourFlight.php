<?php

namespace AwardWallet\Engine\etihad\Email;

use AwardWallet\Schema\Parser\Email\Email;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Common\Parser\Util\EmailDateHelper;

class YourFlight extends \TAccountChecker
{
    public $mailFiles = "etihad/it-419101182.eml, etihad/it-419368004.eml, etihad/it-420152872.eml, etihad/it-420691170.eml, etihad/it-681320762-fr.eml, etihad/it-777639639-it.eml, etihad/it-788482287-es.eml, etihad/it-798674592-it.eml, etihad/it-804284259.eml, etihad/it-809022821-pt.eml, etihad/it-817535952-pt.eml, etihad/it-844742956.eml";
    public $lang = 'en';
    public static $dictionary = [
        'pt' => [
            'confNumber' => ['Referência da reserva:', 'Referência da reserva :'],
            // 'Ticket number' => '',
            // 'Your ticket(s) is/are:' => '',
            'originalFlightDetails' => 'Detalhes do voo original',
            'Direct' => 'Direto',
            // 'Non-stop' => '',
            'From' => 'De',
            // 'Terminal' => '',
            'Operated by:' => 'Operado por:',
            'Cabin:' => 'Cabine:',
            'Aircraft:' => 'Aeronave:',
            'Seat' => 'Assento',
            'guest-single' => 'Passageiros:',
            // 'guests-total' => '',
            // 'loyalty-in-segment' => '',
            // 'loyalty-under-segments' => '',
            // 'nameSubjectStart' => '',
            // 'nameSubjectEnd' => '',
            'headersFromPdf' => '/^[ ]*De[ ]+Para[ ]+Voo[ ]+Partida[ ]+Chegada$/im',
        ],
        'es' => [
            'confNumber' => ['Código de reserva:', 'Código de reserva :'],
            // 'Ticket number' => '',
            // 'Your ticket(s) is/are:' => '',
            // 'originalFlightDetails' => '',
            'Direct' => 'Sin paradas',
            // 'Non-stop' => '',
            'From' => 'Desde',
            // 'Terminal' => '',
            'Operated by:' => 'Operado por:',
            'Cabin:' => 'Cabina:',
            'Aircraft:' => 'Avión:',
            // 'Seat' => '',
            'guest-single' => 'Pasajeros:',
            'guests-total' => 'Pasajeros',
            // 'loyalty-in-segment' => '',
            // 'loyalty-under-segments' => '',
            // 'nameSubjectStart' => '',
            // 'nameSubjectEnd' => '',
            // 'headersFromPdf' => '/^[ ]*?????[ ]+?????[ ]+?????[ ]+?????[ ]+?????$/im',
        ],
        'it' => [
            'confNumber' => ['Numero di prenotazione:', 'Numero di prenotazione :'],
            'Ticket number' => 'numero del biglietto',
            // 'Your ticket(s) is/are:' => '',
            'originalFlightDetails' => 'Dettagli volo originale',
            'Direct' => 'Senza scalo',
            // 'Non-stop' => '',
            'From' => 'Da',
            // 'Terminal' => '',
            'Operated by:' => 'Operato da:',
            'Cabin:' => 'Classe di viaggio:',
            'Aircraft:' => 'Aeromobile:',
            'Seat' => 'Posto',
            'guest-single' => 'Passeggeri:',
            'guests-total' => 'Passeggeri',
            // 'loyalty-in-segment' => '',
            'loyalty-under-segments' => 'Numero di fedeltà',
            // 'nameSubjectStart' => '',
            // 'nameSubjectEnd' => '',
            'headersFromPdf' => '/^[ ]*Da[ ]+A[ ]+Volo[ ]+Partenza[ ]+Arrivo$/im',
        ],
        'fr' => [
            'confNumber' => ['Référence de la réservation:', 'Référence de la réservation :'],
            'Ticket number' => 'numéro de billet',
            // 'Your ticket(s) is/are:' => '',
            // 'originalFlightDetails' => '',
            // 'Direct' => '',
            // 'Non-stop' => '',
            'From'     => 'De',
            'Terminal' => ['Aerogare', 'Terminal'],
            'Operated by:' => 'Opéré par:',
            'Cabin:'    => 'Cabine:',
            'Aircraft:' => 'Appareil:',
            'Seat' => 'Siège',
            'guest-single' => 'Passagers:',
            'guests-total' => 'Passagers',
            // 'loyalty-in-segment' => '',
            'loyalty-under-segments' => 'Numéro de fidélité',
            'nameSubjectStart'       => 'Votre vol Etihad Airways',
            'nameSubjectEnd'         => 'référence',
            'headersFromPdf'         => '/^[ ]*De[ ]+À[ ]+Vol[ ]+Départ[ ]+Arrivée$/im',
        ],
        'de' => [
            'confNumber' => ['Buchungsnummer:', 'Buchungsnummer :'],
            // 'Ticket number' => '',
            // 'Your ticket(s) is/are:' => '',
            // 'originalFlightDetails' => '',
            // 'Direct' => '',
            // 'Non-stop' => '',
            'From'     => 'Von',
            // 'Terminal' => '',
            // 'Operated by:' => '',
            'Cabin:'    => 'Reiseklasse:',
            'Aircraft:' => 'Flugzeug:',
            // 'Seat' => '',
            // 'guest-single' => '',
            // 'guests-total' => '',
            // 'loyalty-in-segment' => '',
            // 'loyalty-under-segments' => '',
            'nameSubjectStart'       => 'Ihr Etihad Airways-Flug',
            'nameSubjectEnd'         => 'Referenz',
            'headersFromPdf'         => '/^[ ]*Von[ ]+Nach[ ]+Flug[ ]+Abflug[ ]+Ankunft/im',
        ],
        'zh' => [
            'confNumber' => ['预订号:', '预订号 :', '预订号：'],
            // 'Ticket number' => '',
            // 'Your ticket(s) is/are:' => '',
            // 'originalFlightDetails' => '',
            // 'Direct' => '',
            // 'Non-stop' => '',
            'From'     => '出发地',
            // 'Terminal' => '',
            // 'Operated by:' => '',
            'Cabin:'    => '客舱:',
            'Aircraft:' => '飞机:',
            // 'Seat' => '',
            // 'guest-single' => '',
            // 'guests-total' => '',
            // 'loyalty-in-segment' => '',
            // 'loyalty-under-segments' => '',
            'nameSubjectStart'       => '您的阿提哈德航空航班',
            'nameSubjectEnd'         => '參考編號',
            'headersFromPdf'         => '/^[ ]*從[ ]+至[ ]+航班[ ]+出發地[ ]+目的地/im',
        ],
        'en' => [
            'confNumber' => ['Booking reference:', 'Booking reference :'],
            // 'Ticket number' => '',
            // 'Your ticket(s) is/are:' => '',
            'originalFlightDetails' => 'Original flight details',
            // 'Direct' => '',
            // 'Non-stop' => '',
            // 'From' => '',
            // 'Terminal' => '',
            // 'Operated by:' => '',
            // 'Cabin:' => '',
            // 'Aircraft:' => '',
            // 'Seat' => '',
            'guest-single'           => ['Guest:', 'Guests:'],
            'guests-total'           => 'Guest(s)',
            'loyalty-in-segment'     => 'Loyalty programme number',
            'loyalty-under-segments' => 'Loyalty Number',
            'nameSubjectStart'       => 'Your Etihad Airways flight',
            'nameSubjectEnd'         => 'reference',
            'headersFromPdf'         => '/^[ ]*From[ ]+To[ ]+Flight[ ]+Departure[ ]+Arrival$/im',
        ],
    ];

    private $detectSubject = [
        // pt
        'O seu voo da Etihad Airways, referência:',
        'o seu voo da Etihad Airways referência',
        // it
        'il tuo volo Etihad Airways ',
        // fr
        'Votre vol Etihad Airways,',
        // de
        'Ihr Etihad Airways-Flug,',
        // zh
        '您的阿提哈德航空航班,',
        // en
        'Online check-in is open',
        'Your Etihad Airways flight ',
        'Your Etihad Airways flight,',
        'days to go before your flight to ',
        'Start preparing for your flight to ',
    ];

    private $detectBody = [
        'pt' => [
            'Gostaríamos de agradecer por escolher',
            'seu voo sofreu alterações',
            'voos afetados por esta alteração são indicados de seguida',
        ],
        'es' => [
            'Deseamos darle la bienvenida a bordo',
        ],
        'it' => [
            'Ti aspettiamo a bordo',
            'il tuo prossimo volo è cambiato',
        ],
        'fr' => [
            'Nous nous réjouissons de vous accueillir bientôt à bord',
        ],
        'de' => [
            'Wir freuen uns darauf, Sie bald an Bord begrüßen zu dürfen.',
        ],
        'zh' => [
            '我们期待着迎接您的到来。',
        ],
        'en' => [
            "your flight's been cancelled", "your flight's been canceled",
            'Your upcoming flight has been cancelled', 'Your upcoming flight has been canceled',
            'Start preparing for your flight to',
            'Start your journey by checking in online',
            'We look forward to welcoming you on board',
        ],
    ];

    // Main Detects Methods
    public function detectEmailFromProvider($from)
    {
        return preg_match("/[@.]etihad\.com$/", $from) > 0;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if ((!array_key_exists('from', $headers) || $this->detectEmailFromProvider(rtrim($headers['from'], '> ')) !== true)
            && (!array_key_exists('subject', $headers) || strpos($headers['subject'], 'Etihad Airways') === false)
        ) {
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
        if ($this->http->XPath->query("//a[{$this->contains(['.etihad.com/', 'www.etihad.com', 'bookings.etihad.com', 'digital.etihad.com'], '@href')}]")->length === 0
            && $this->http->XPath->query("//*[{$this->contains(['Kind regards, Etihad Airways', 'view your full trip itinerary, visit etihad.com'])}]")->length === 0
        ) {
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
        $this->assignLang();

        $subject = $parser->getSubject();
        $this->parseEmailHtml($email, $subject);

        $pdfs = $parser->searchAttachmentByName('.*pdf');

        foreach ($pdfs as $pdf) {
            $textPdf = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (!$textPdf) {
                continue;
            }

            foreach (self::$dictionary as $phrases) {
                if (empty($phrases['headersFromPdf'])) {
                    continue;
                }

                if (preg_match($phrases['headersFromPdf'], $textPdf)) {
                    $this->logger->debug('Found Pdf-attachment! Go to parser panorama/TicketEMDPdf');
                    $email->add()->flight(); // for 100% fail

                    break 2;
                }
            }
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

    private function parseEmailHtml(Email $email, string $subject): void
    {
        $xpathNoHide = "not(ancestor-or-self::*[contains(translate(@style,' ',''),'mso-hide:all')])";

        $patterns = [
            'travellerName' => '[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]]', // Mr. Hao-Li Huang
            'eTicket'       => '\d{3}(?: | ?- ?)?\d{5,}(?: | ?[-\/] ?)?\d{1,3}', // 175-2345005149-23  |  1752345005149/23
            'loyaltyNo'     => 'EY[ ]*(?<number>\d{5,})(?:[ ]+\D{3,})?', // EY 500083966391 BRNZ
        ];

        $f = $email->add()->flight();

        // General
        $confirmation = $this->http->FindSingleNode("//text()[{$this->eq($this->t('confNumber'))}]/following::text()[normalize-space()][1]", null, true, '/^[A-Z\d]{5,7}$/');

        if ($confirmation) {
            $confirmationTitle = $this->http->FindSingleNode("//text()[{$this->eq($this->t('confNumber'))}]", null, true, '/^(.+?)[\s:：]*$/u');
            $f->general()->confirmation($confirmation, $confirmationTitle);
        }

        $travellersText = implode('/', $this->http->FindNodes("//text()[{$this->eq($this->t('guest-single'))}]/ancestor::*[../self::tr][1]", null, "/:\s*(.+)/"));
        $travellers = preg_split('/(\s*\/\s*)+/', $travellersText);
        $travellers = preg_replace('/\s*\(.*\)\s*$/', '', $travellers);
        $travellers = array_map(function ($item) {
            return $this->normalizeTraveller($item);
        }, $travellers);
        $travellers = array_filter($travellers);

        if (count($travellers) === 0
            && preg_match("/{$this->opt($this->t('nameSubjectStart'))}\s*,\s*({$patterns['travellerName']})\s*,\s*{$this->opt($this->t('nameSubjectEnd'))}/iu", $subject, $m)
        ) {
            $travellers = array_filter([$this->normalizeTraveller($m[1])]);
        }

        if (count($travellers) > 0) {
            $f->general()->travellers(array_unique($travellers), true);
        }

        $tickets = [];
        $ticketRows = $this->http->XPath->query("//tr[{$this->eq($this->t('guests-total'))}]/following::text()[{$this->starts($this->t('Ticket number'))}]");

        foreach ($ticketRows as $tktRow) {
            $passengerName = $this->normalizeTraveller($this->http->FindSingleNode("preceding::text()[normalize-space()][1]", $tktRow, true, "/^{$patterns['travellerName']}$/u"));
            $tktValue = $this->http->FindSingleNode(".", $tktRow, true, "/^{$this->opt($this->t('Ticket number'))}[: ]+([^: ].+)$/");
            $tktParts = preg_split('/(?:\s*,\s*)+/', $tktValue);

            foreach ($tktParts as $tktItem) {
                if (preg_match("/^(?:INF[-\s]*)?({$patterns['eTicket']})$/", $tktItem, $m)
                    && !in_array($m[1], $tickets)
                ) {
                    $f->issued()->ticket($m[1], false, $passengerName);
                    $tickets[] = $m[1];
                }
            }
        }

        if (count($tickets) === 0) {
            $ticketsText = implode("\n", $this->http->FindNodes("//text()[{$this->eq($this->t('Your ticket(s) is/are:'))}]/ancestor::*[not({$this->eq($this->t('Your ticket(s) is/are:'))})][1]//text()[normalize-space()]"));
            preg_match_all("/^\s*(?<passenger>{$patterns['travellerName']})\s*[:]+\s*(?<ticket>{$patterns['eTicket']})$/mu", $ticketsText, $ticketMatches, PREG_SET_ORDER);

            foreach ($ticketMatches as $matches) {
                $f->issued()->ticket($matches['ticket'], false, $this->normalizeTraveller($matches['passenger']));
            }
        }

        $etihadPresence = false;
        $loyaltyNumbers = [];

        $xpathSegFilter = "[not(preceding::node()[{$this->eq($this->t('originalFlightDetails'))}])]";
        // $xpath = "//text()[{$this->starts($this->t('guest-single'))}]{$xpathSegFilter}/ancestor::*[.//text()[{$this->starts($this->t('From'))}]][count(.//text()[{$this->starts($this->t('guest-single'))}])=1][1]";
        $xpath = "//text()[{$this->eq($this->tPlusEn('Direct'))} or {$this->eq($this->t('Non-stop'))}]{$xpathSegFilter}/ancestor::table[ descendant::text()[{$this->starts($this->t('From'))}] ][1]";
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $codeDep = $dateDepFinal = $nameDep = $terminalDep = null;
            $codeArr = $dateArrFinal = $nameArr = $terminalArr = null;

            $patternFlNo = "/^.*\(\s*(?<al>[A-Z][A-Z\d]|[A-Z\d][A-Z]) ?(?<fn>\d+)\s*\)\s*$/";
            $dateVal = $this->http->FindSingleNode("descendant::text()[normalize-space()][1]", $root);

            if (preg_match($patternFlNo, $dateVal)) {
                $year = $this->http->FindSingleNode("//text()[{$this->contains($this->t('All rights reserved. Copyright'))}]", null, true, "/©\s*(2\d{3})$/");

                if ($year) {
                    $dateVal = '01 Jan ' . $year;
                }
            }

            $date = strtotime($this->normalizeDate($dateVal));

            $status = $this->http->FindSingleNode("descendant::text()[normalize-space()][3][ancestor::*[contains(@style, 'background-color')]]", $root, true, "/^[[:alpha:]]+(?:[-\s][[:alpha:]]+){0,2}$/u");

            $routeText = implode("\n", $this->http->FindNodes(".//text()[{$this->starts($this->t('From'))}]/following::*[normalize-space()][1]/descendant::*[self::td or self::th][not(.//tr)][normalize-space()][{$xpathNoHide}]", $root));

            // remove duplicate rows
            $routeText = preg_replace('/(^.+$)(?:\n+\1)+/m', '$1', $routeText);

            $re = "/^\s*(?<dCode>[A-Z]{3})\n(?<duration>.+)\n(?<dTime>.+)\n.+\n(?<dDate>.+)\n(?<dName>.+)(?<dTerminal>\n.*(?i){$this->opt($this->t('Terminal'))}(?-i).*)?(?:\n.+\n.+)?"
                . "\n(?<aCode>[A-Z]{3})\n(?<aTime>.+?)( *[+-] ?\d ?[[:alpha:]]+)?\n(?<aDate>.+)\n(?<aName>.+)(?<aTerminal>\n.*(?i){$this->opt($this->t('Terminal'))}(?-i).*)?\s*$/u";

            //no duration
            $re2 = "/^\s*(?<dCode>[A-Z]{3})\n(?<dTime>\d+\:\d+)(?:\nNon\-stop)?\n(?<dDate>.+)\n(?<dName>.+)(?<dTerminal>\n.*(?i){$this->opt($this->t('Terminal'))}(?-i).*)?(?:\nNon\-stop)?"
                . "\n(?<aCode>[A-Z]{3})\n(?<aTime>.+?)( *[+-] ?\d ?[[:alpha:]]+)?\n(?<aDate>.+)\n(?<aName>.+)(?<aTerminal>\n.*(?i){$this->opt($this->t('Terminal'))}(?-i).*)?\s*$/u";

            if (preg_match($re, $routeText, $m) || preg_match($re2, $routeText, $m)) {
                $dateDep = $dateArr = null;
                $dateDepNormal = $this->normalizeDate($m['dDate']) ?? '';
                $dateArrNormal = $this->normalizeDate($m['aDate']) ?? '';

                if ($dateDepNormal && preg_match("/^\s*\w+[-,.\s]+\w+[.\s]*$/u", $dateDepNormal)) {
                    if ($date) {
                        $dateDep = EmailDateHelper::parseDateRelative($dateDepNormal, strtotime('-5 days', $date), true, '%D% %Y%');
                    }
                } else {
                    $dateDep = strtotime($dateDepNormal);
                }

                if ($dateArrNormal && preg_match("/^\s*\w+[-,.\s]+\w+[.\s]*$/u", $dateArrNormal)) {
                    if ($date) {
                        $dateArr = EmailDateHelper::parseDateRelative($dateArrNormal, strtotime('-5 days', $date), true, '%D% %Y%');
                    }
                } else {
                    $dateArr = strtotime($dateArrNormal);
                }

                // Departure
                $codeDep = $m['dCode'];
                $dateDepFinal = strtotime($m['dTime'], $dateDep);
                $nameDep = $m['dName'];
                $terminalDep = empty($m['dTerminal']) ? null : preg_replace(["/^\s*{$this->opt($this->t('Terminal'))}\s*/i", "/\s*{$this->opt($this->t('Terminal'))}\s*$/i"], '', trim($m['dTerminal']));

                // Arrival
                $codeArr = $m['aCode'];
                $dateArrFinal = strtotime($m['aTime'], $dateArr);
                $nameArr = $m['aName'];
                $terminalArr = empty($m['aTerminal']) ? null : preg_replace(["/^\s*{$this->opt($this->t('Terminal'))}\s*/i", "/\s*{$this->opt($this->t('Terminal'))}\s*$/i"], '', trim($m['aTerminal']));
            }

            $reAirport = "/\bAirport\b/i";
            $reGroundStation = "/\bBus Station\b/i";

            if (preg_match($reGroundStation, $nameDep . "\n" . $nameArr)) {
                // it-804284259.eml
                $t = $email->add()->transfer();
                $t->general()->noConfirmation();

                if (count($travellers) > 0) {
                    $t->general()->travellers(array_unique($travellers), true);
                }

                $s = $t->addSegment(); // transfer segment
                $s->extra()->status($status, false, true);

                if (preg_match($reAirport, $nameDep) && !preg_match($reGroundStation, $nameDep)) {
                    $s->departure()->code($codeDep);
                } else {
                    $nameDep .= ' (' . $codeDep . ')';
                }

                if (preg_match($reAirport, $nameArr) && !preg_match($reGroundStation, $nameArr)) {
                    $s->arrival()->code($codeArr);
                } else {
                    $nameArr .= ' (' . $codeArr . ')';
                }

                $s->departure()->date($dateDepFinal)->name($nameDep);
                $s->arrival()->date($dateArrFinal)->name($nameArr);

                continue;
            }

            // flight continue

            $s = $f->addSegment();

            $flight = $this->http->FindSingleNode("descendant::text()[normalize-space()][contains(.,'(')][1]", $root);

            if (preg_match($patternFlNo, $flight, $m)) {
                $s->airline()->name($m['al'])->number($m['fn']);

                $loyaltyNumber = $this->http->FindSingleNode("descendant::tr[not(.//tr) and {$this->starts($this->t('loyalty-in-segment'))}]", $root, true, "/^{$this->opt($this->t('loyalty-in-segment'))}[:\s]+([^:]+)$/");

                if (preg_match("/^{$patterns['loyaltyNo']}$/", $loyaltyNumber, $matches)
                    || preg_match("/^(?<number>\d{5,})(?:[ ]+\D{3,})?$/", $loyaltyNumber, $matches) && $m['al'] === 'EY'
                ) {
                    $loyaltyNumbers[] = $matches['number'];
                }

                if ($m['al'] === 'EY') {
                    $etihadPresence = true;
                }
            }

            $s->extra()->status($status, false, true);

            $s->departure()->code($codeDep)->date($dateDepFinal)->name($nameDep)->terminal($terminalDep === '' ? null : $terminalDep, false, true);
            $s->arrival()->code($codeArr)->date($dateArrFinal)->name($nameArr)->terminal($terminalArr === '' ? null : $terminalArr, false, true);

            $cabinVal = $this->http->FindSingleNode(".//td[not(.//tr)][descendant::text()[normalize-space()][1][{$this->eq($this->t('Cabin:'))}]]", $root, false, "/:\s*(.*?)\s*$/");

            if (preg_match("/^(?<cabin>.+?)[(\s]+(?<bookingCode>[A-Z]{1,2})[)\s]*$/", $cabinVal, $m)) {
                // Economy (Q)
                $s->extra()->cabin($m['cabin'])->bookingCode($m['bookingCode']);
            } elseif (preg_match("/^[(\s]*([A-Z]{1,2})[)\s]*$/", $cabinVal, $m)) {
                // (Q)  |  Q
                $s->extra()->bookingCode($m[1]);
            } else {
                // Economy
                $s->extra()->cabin($cabinVal, false, true);
            }

            $s->extra()
                ->aircraft($this->http->FindSingleNode(".//td[not(.//td)][descendant::text()[normalize-space()][1][{$this->eq($this->t('Aircraft:'))}]]", $root, true, "/:\s*(.+)\s*$/"), false, true)
            ;

            $s->airline()->operator($this->http->FindSingleNode(".//td[not(.//tr)][descendant::text()[normalize-space()][1][{$this->eq($this->t('Operated by:'))}]]", $root, true, "/:\s*(.+?)\s*$/"), false, true);

            if (preg_match("/\bCancell?ed\b/i", $status ?? '')) {
                $s->extra()
                    ->cancelled();
            }

            $seatNodes = $this->http->XPath->query("descendant::text()[{$this->contains($this->t('Seat'))} and contains(.,'(') and contains(.,')')]", $root);

            foreach ($seatNodes as $seatRoot) {
                $passengerName = $this->normalizeTraveller($this->http->FindSingleNode("preceding::text()[normalize-space()][1]", $seatRoot, true, "/^[\/\s]*(.*?)[\/\s]*$/"));

                if (!in_array($passengerName, $travellers)) {
                    $passengerName = null;
                }

                $seat = $this->http->FindSingleNode(".", $seatRoot, true, "/{$this->opt($this->t('Seat'))}\s*(\d+[A-Z])\b/i");

                if ($seat) {
                    $s->extra()->seat($seat, false, false, $passengerName);
                }
            }
        }

        if (count($loyaltyNumbers) === 0) {
            $loyaltyNoValues = $this->http->FindNodes("//tr[{$this->eq($this->t('guests-total'))}]/following::text()[{$this->starts($this->t('loyalty-under-segments'))}]", null, "/^{$this->opt($this->t('loyalty-under-segments'))}[: ]+([^: ].{4,})$/");

            foreach ($loyaltyNoValues as $loyaltyNumber) {
                if (preg_match("/^{$patterns['loyaltyNo']}$/", $loyaltyNumber, $matches)
                    || preg_match("/^(?<number>\d{5,})(?:[ ]+\D{3,})?$/", $loyaltyNumber, $matches) && $etihadPresence
                ) {
                    $loyaltyNumbers[] = $matches['number'];
                }
            }
        }

        if (count($loyaltyNumbers) > 0) {
            $f->program()->accounts(array_unique($loyaltyNumbers), false);
        }
    }

    private function assignLang(): bool
    {
        if (!isset(self::$dictionary, $this->lang)) {
            return false;
        }

        foreach (self::$dictionary as $lang => $phrases) {
            if (!is_string($lang) || empty($phrases['confNumber'])) {
                continue;
            }

            if ($this->http->XPath->query("//*[{$this->contains($phrases['confNumber'])}]")->length > 0) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
    }

    private function t(string $phrase, string $lang = '')
    {
        if ( !isset(self::$dictionary, $this->lang) ) {
            return $phrase;
        }
        if ($lang === '') {
            $lang = $this->lang;
        }
        if ( empty(self::$dictionary[$lang][$phrase]) ) {
            return $phrase;
        }
        return self::$dictionary[$lang][$phrase];
    }

    private function tPlusEn(string $s): array
    {
        return array_unique(array_merge((array) $this->t($s), (array) $this->t($s, 'en')));
    }

    // additional methods

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

    /**
     * Dependencies `use AwardWallet\Engine\MonthTranslate` and `$this->lang`
     * @param string|null $text Unformatted string with date
     * @return string|null
     */
    private function normalizeDate(?string $text): ?string
    {
        if ( preg_match('/^(\d{1,2})[-,.\s]*([[:alpha:]]+)[-,.\s]*(\d{4})$/u', $text, $m) ) {
            // 04 Sep 2024
            $day = $m[1];
            $month = $m[2];
            $year = $m[3];
        } elseif ( preg_match('/^(\d{1,2})[-,.\s]*([[:alpha:]]+)[.\s]*$/u', $text, $m) ) {
            // 04 sep.
            $day = $m[1];
            $month = $m[2];
            $year = '';
        }
        if ( isset($day, $month, $year) ) {
            if ( preg_match('/^\s*(\d{1,2})\s*$/', $month, $m) )
                return $m[1] . '/' . $day . ($year ? '/' . $year : '');
            if ( ($monthNew = MonthTranslate::translate($month, $this->lang)) !== false )
                $month = $monthNew;
            return $day . ' ' . $month . ($year ? ' ' . $year : '');
        }
        return null;
    }

    private function normalizeTraveller(?string $s): ?string
    {
        if (empty($s)) {
            return null;
        }

        $namePrefixes = '(?:MSTR|MISS|MRS|MR|MS|DR)';

        return preg_replace([
            "/^(.{2,}?)\s+(?:{$namePrefixes}[.\s]*)+$/is",
            "/^(?:{$namePrefixes}[.\s]+)+(.{2,})$/is",
            '/^([^\/]+?)(?:\s*[\/]+\s*)+([^\/]+)$/',
        ], [
            '$1',
            '$1',
            '$2 $1',
        ], $s);
    }

    private function opt($field, $delimiter = '/'): string
    {
        $field = (array) $field;

        if (empty($field)) {
            $field = ['false'];
        }

        return '(?:' . implode("|", array_map(function ($s) use ($delimiter) {
            return str_replace(' ', '\s+', preg_quote($s, $delimiter));
        }, $field)) . ')';
    }
}
