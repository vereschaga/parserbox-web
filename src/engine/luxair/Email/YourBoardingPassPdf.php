<?php

namespace AwardWallet\Engine\luxair\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Common\Flight;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

// parsers with similar formats: aviancataca/BoardingPassPdf, airmalta/BoardingPassPdf

class YourBoardingPassPdf extends \TAccountChecker
{
    public $mailFiles = "luxair/it-7489624.eml, luxair/it-7574451.eml, luxair/it-7661360.eml, luxair/it-7694763.eml, luxair/it-7976363.eml, luxair/it-8017119.eml, luxair/it-8061336.eml, luxair/it-828103912.eml";

    public $reFrom = "checkin.noreply@luxair.lu";
    public $detectSubject = [
        "Boarding Pass", //Boarding Pass Confirmation
        'Boarding Cards for',
        'Your Email Confirmation',
        'Carte d’embarquement',
        'Document de confirmation',
        'Conferma della prenotazione',
        // es
        'Confirmación de Pase a Bordo',
    ];
    public $detectBody = [
        "en" => "FLIGHT ",
        "fr" => "VOL ",
        "de" => "FLUG ",
        "pt" => "VOO ",
        "it" => "VOLO ",
        "es" => "Vuelo ",
    ];
    public $pdfPattern = "Boarding\s*Pass.pdf";
//    public $pdfPattern = ".*\.pdf";

    public static $dictionary = [
        "en" => [
            "BOARDING PASS" => ["BOARDING PASS", "Boarding Pass", 'Boarding Pass Exchange Coupon', 'Confirmation'],
            //            "FROM" => '',
            //            "TO" => '', ??
            "TAKE-OFF" => ["TAKE-OFF", "DEPARTURE"],
            //            "Terminal" => '',
            //            "FLIGHT" => '',
            //            "Operated via" => '',
            //            "BOOKING REFERENCE" => '',
            //            "CLASS OF TRAVEL" => '',
            //            "ETKT" => '',
            //            "FREQUENT FLYER" => '',
            //            "TRAVEL INFORMATION" => '',
            "NEXT STEPS" => ["NEXT STEPS", "Name:"],
        ],
        "fr" => [
            "BOARDING PASS"     => ["Carte d'embarquement", "CARTE D'EMBARQUEMENT", "Document de confirmation"],
            "FROM"              => "DE",
            "TAKE-OFF"          => "DECOLLAGE",
            //            "Terminal" => '',
            "FLIGHT"            => "VOL",
            //            "Operated via" => '',
            "BOOKING REFERENCE" => "N° RESERVATION",
            "CLASS OF TRAVEL"   => 'CLASSE',
            "ETKT"              => "ETKT",
            "FREQUENT FLYER"    => "VOYAGEUR FREQUENT",
            "TRAVEL INFORMATION"=> "INFORMATIONS SUR LE VOYAGE",
            "NEXT STEPS"        => "PROCHAINES ETAPES",
        ],
        "de" => [
            "FREQUENT FLYER"    => "VIELFLIEGERNUMMER",
            "NEXT STEPS"        => "Nächster Abschnitt",
            "BOOKING REFERENCE" => "BUCHUNGS NR",
            "BOARDING PASS"     => ["BORDKARTE"],
            "ETKT"              => "ETKT",
            "TAKE-OFF"          => "Abflug",
            "FROM"              => 'VON',
            "FLIGHT"            => "FLUG",
            "TRAVEL INFORMATION"=> "REISEINFORMATIONEN",
            "CLASS OF TRAVEL"   => 'KLASSE',
        ],
        "pt" => [
            "FREQUENT FLYER"     => "PASSAGEIRO FREQUENTE",
            "NEXT STEPS"         => "PRÓXIMOS PASSOS",
            "BOOKING REFERENCE"  => "REFERÊNCIA DA RESERVA",
            "BOARDING PASS"      => ["CARTÃO DE EMBARQUE"],
            "ETKT"               => "ETKT",
            "FROM"               => 'DE',
            "TAKE-OFF"           => "DESCOLAGEM",
            "FLIGHT"             => "VOO",
            "TRAVEL INFORMATION" => "INFORMAÇÕES SOBRE A VIAGEM",
            "CLASS OF TRAVEL"    => 'CLASSE DE VIAGEM',
        ],
        "it" => [
            "FREQUENT FLYER"     => "FREQUENT FLYER",
            "NEXT STEPS"         => "FASI SUCCESSIVE",
            "BOOKING REFERENCE"  => "CODICE PRENOTAZIONE",
            "BOARDING PASS"      => ["CARTA D'IMBARCO"],
            "ETKT"               => "ETKT",
            "TAKE-OFF"           => "DECOLLO",
            "FROM"               => 'DA',
            "FLIGHT"             => "VOLO",
            "TRAVEL INFORMATION" => "INFORMAZIONI SUL VIAGGIO",
            "CLASS OF TRAVEL"    => "CLASSE DI VIAGGIO",
        ],
        "es" => [
            "FREQUENT FLYER"     => "Viajero Frecuente",
            "NEXT STEPS"         => "NEXT STEPS",
            "BOOKING REFERENCE"  => "BOOKING REFERENCE",
            "BOARDING PASS"      => ["Pase a Bordo"],
            "ETKT"               => "ETKT",
            "TAKE-OFF"           => "Despegue",
            "FROM"               => 'DESDE',
            "FLIGHT"             => "Vuelo",
            "TRAVEL INFORMATION" => "TRAVEL INFORMATION",
            "CLASS OF TRAVEL"    => "Clase de Reserva",
        ],
    ];

    public $lang = "en";

    private $providerCode;
    private static $detectsProvider = [
        'airtransat' => [
            'from'           => ['@airtransat.com'],
            'subjUniqueName' => ['Air Transat'],
            'bodyHtml'       => [
                'www.airtransat.com',
            ],
            'bodyPdf' => [
                'www.airtransat.com',
            ],
        ],
        'luxair' => [
            'from'           => ['checkin.noreply@luxair.lu'],
            'subjUniqueName' => ['Luxair'],
            'bodyHtml'       => [],
            'bodyPdf'        => [
                'Luxair',
            ],
        ],
        'vistara' => [
            'from'           => ['@airvistara.com'],
            'subjUniqueName' => [],
            'bodyHtml'       => [
                'Team Vistara',
            ],
            'bodyPdf' => [],
        ],
        'bulgariaair' => [
            'from'           => ['@air.bg'],
            'subjUniqueName' => [],
            'bodyHtml'       => [],
            'bodyPdf'        => [
                'Bulgaria Air',
            ],
        ],
        'asiana' => [
            'from'           => ['@flyasiana.com'],
            'subjUniqueName' => [],
            'bodyHtml'       => [
                'Asiana airlines',
            ],
            'bodyPdf' => [],
        ],
        'cape' => [
            'from'           => ['@capeair.com'],
            'subjUniqueName' => [
                'Cape Air',
            ],
            'bodyHtml' => [
                'choosing Cape Air',
                "//a[contains(@href, 'capeair.com')]",
            ],
            'bodyPdf' => [
                'Cape Air',
            ],
        ],
        'kuwait' => [
            'from' => ['e-booking@kuwaitairways.com'],
            //            'subjUniqueName' => [],
            'bodyHtml' => ['www.kuwaitairways.com',
                'choosing Kuwait Airways',
                '//a[contains(@href, "www.kuwaitairways.com")]',
            ],
            'bodyPdf' => ['Kuwait Airways'],
        ],
        'aireuropa' => [
            'from' => ['@air-europa.com'],
            //            'subjUniqueName' => [],
            'bodyHtml' => ['.air-europa.com',
                'choosing Kuwait Airways',
                '//a[contains(@href, "www.air-europa.com")]',
            ],
            'bodyPdf' => ['Air Europa'],
        ],
        'tunisair' => [
            'from' => ['@tunisair.com'],
            //            'subjUniqueName' => [],
            'bodyHtml' => ['tunisair.com',
                'choosing tunisair',
                '//a[contains(@href, "www.tunisair.com")]',
            ],
            'bodyPdf' => ['Tunisair'],
        ],
        'boliviana' => [
            'from' => ['@boa.bo'],
            // 'subjUniqueName' => [],
            'bodyHtml' => [
                'Boliviana de Aviacion',
                '//a[contains(@href, "www.boa.bo/")]',
            ],
            'bodyPdf' => ['Boliviana de Aviacion'],
        ],
        //        '' => [
        //            'from' => [''],
        //            'subjUniqueName' => [],
        //            'bodyHtml' => [],
        //            'bodyPdf' => [],
        //        ],
    ];

    public static function getEmailProviders()
    {
        return array_keys(self::$detectsProvider);
    }

    public function parsePdf(Email $email, $text)
    {
        $bps = $this->split("/(" . $this->opt($this->t("BOARDING PASS")) . "(?: *\\/.+)?\s*\n)/", "\n\n" . $text);

        $confirmationError = false;

        foreach ($bps as $i => $text) {
            unset($f);
            $infoTable = $this->re("/\n( *(?:{$this->opt($this->t("TRAVEL INFORMATION"))}).*?)\n *" . $this->opt($this->t("NEXT STEPS")) . "/ms", $text);

            if (empty($infoTable)) {
                $infoTable = $this->re("/\n( *(?:{$this->opt($this->t("TRAVEL INFORMATION"))}).*?({$this->opt($this->t("FREQUENT FLYER"))}|{$this->opt($this->t("CLASS OF TRAVEL"))})?)\n\n\n/ms", $text);
            }
//            $this->logger->debug('$infoTable = '.print_r( $infoTable,true));

            $headerText = $this->inOneRow($infoTable);
            $pos = $this->TableHeadPos($headerText);
            $infoTable = array_merge([], array_filter(array_map('trim', $this->splitCols($infoTable, $pos))));

            if (count($infoTable) < 2) {
                $email->add()->flight();
                $this->logger->debug("incorrect columns count infoTable");

                break;
            }
            $bookingInfo = $infoTable[count($infoTable) - 1];
//            $this->logger->debug('$pos = '.print_r( $bookingInfo,true));

            $ticket = $this->re("/" . $this->opt($this->t("ETKT")) . "\s+([^\n]+)/ms", $bookingInfo);

            foreach ($email->getItineraries() as $it) {
                $iTickets = array_column($it->getTicketNumbers(), 0);

                if (!empty($iTickets) && !empty($ticket) && strncasecmp($ticket, $iTickets[0], 3) === 0) {
                    /** @var Flight $f */
                    $f = $it;
                } elseif (empty($iTickets) && empty($ticket)) {
                    $f = $it;
                }
            }

            if (!isset($f)) {
                $f = $email->add()->flight();
            }

            if (!empty($ticket) && !in_array($ticket, array_column($f->getTicketNumbers(), 0))) {
                $f->issued()
                    ->ticket($ticket, false);
            }

            $conf = $this->re("/" . $this->opt($this->t("BOOKING REFERENCE")) . "[: ]*\s+(\w+)/ms", $bookingInfo);

            if (!empty($conf) && in_array($conf, array_column($f->getConfirmationNumbers(), 0))) {
            } elseif (!empty($conf)) {
                $f->general()
                    ->confirmation($conf);
            } else {
                $conf = $this->re("/^ *([A-Z\d]{5,7}) *$/m", $bookingInfo);

                if (!empty($conf) && !in_array($conf, ['TICKET'])) {
                    $confirmationError = true;
                }
            }

            $bpTitle = (array) $this->t("BOARDING PASS");

            foreach (self::$dictionary as $dict) {
                $bpTitle = array_merge($bpTitle, (array) $dict["BOARDING PASS"] ?? []);
            }
            $bpTitle = array_filter(array_unique($bpTitle));
            $traveller = $this->re("/{$this->opt($bpTitle)}(?: *\\/.+)?\n+(.+)\n+ *(?:{$this->opt($this->t('FROM'))}|{$this->opt($this->t('TAKE-OFF'))})/", $text);
            $traveller = preg_replace("/\s+(?:Mrs|Ms|Mr|Miss|Mstr|Dr)\s*$/ui", '', $traveller);
            $traveller = preg_replace("/^\s*(.+?)\s*\\/\s*(.+?)\s*$/", '$2 $1', $traveller);

            if (!empty($traveller) && !in_array($traveller, array_column($f->getTravellers(), 0))) {
                $f->general()
                    ->traveller($traveller);
            }

            $account = $this->re("/" . $this->opt($this->t("FREQUENT FLYER")) . "[ :]*\s+([^\n]+\d[^\n]+)/ms", $bookingInfo);

            if (!empty($account) && !in_array($account, array_column($f->getAccountNumbers(), 0))) {
                $f->program()
                    ->account($account, false);
            }

            $routeTableTextRe = "/\n( *(?:{$this->opt($this->t("FROM"))}|{$this->opt($this->t("TAKE-OFF"))}).* {4,}\w+.*\s*\n[\s\S]*?)\n *" . $this->opt($this->t("FLIGHT")) . "/";
//            $this->logger->debug('$routeTableTextRe = '.print_r( $routeTableTextRe,true));
//            $this->logger->debug('$routeTableText = '.print_r( $routeTableText,true));
            $routeTableText = $this->re($routeTableTextRe, $text);

            $rowone = $this->inOneRow($routeTableText);
            $spaces = preg_split("/a+/", trim($rowone));

            if (count($spaces) < 2) {
                $email->add()->flight();
                $this->logger->debug("incorrect columns count routeTable");

                break;
            }

            usort($spaces, function ($a, $b) {
                return strlen($a) < strlen($b);
            });

            if (isset($spaces[0], $spaces[1]) && strlen($spaces[0]) > strlen($spaces[1])) {
                $halfPos = strpos($rowone, $spaces[0], strlen($spaces[0]) - 10) + strlen($spaces[0]);
            }
            $routeTable = $this->splitCols($routeTableText, [0, $halfPos], false);
//            $this->logger->debug('$routeTable = '.print_r( $routeTable,true));

            $flightInfoTableText = $this->re("/\n( *" . $this->opt($this->t("FLIGHT")) . " .*?)" . $this->opt($this->t("TRAVEL INFORMATION")) . "/msu", $text);
            // $this->logger->debug('$flightInfoTableText = '.print_r( $flightInfoTableText,true));

            $flightInfoTable = $this->splitCols($flightInfoTableText, $this->TableHeadPos($this->inOneRow($flightInfoTableText)));

            if (preg_match("/^\s*([[:alpha:]\/ °]+\s*\n\s*\w{2} ?\d+) +(\d{1,3}[A-Z])(?: \d{1,2}:\d{2})?\s*$/u", $flightInfoTable[0], $m0)
                && preg_match("/^\s*[[:alpha:]\/ °]+\s*$/u", $flightInfoTable[1])
            ) {
                // FLIGHT                  SEAT          .....
                // OB607 7E                              .....
                $flightInfoTable[0] = $m0[1];
                $flightInfoTable[1] = $flightInfoTable[1] . "\n" . $m0[2];
            }

            if (count($flightInfoTable) < 3) {
                $email->add()->flight();
                $this->http->log("incorrect cols count $flightInfoTable");

                continue;
            }
            // Segments

            $s = $f->addSegment();

            // Airline
            $s->airline()
                ->name($this->re("/^\s*[[:alpha:]\\/ °]+\s*\n\s*(\w{2}) ?\d+\s*(?:\n|$)/u", trim($flightInfoTable[0])))
                ->number($this->re("/^\s*[[:alpha:]\\/ °]+\s*\n\s*\w{2} ?(\d+)(?:\n|$)/u", trim($flightInfoTable[0])));

            $re = "/^\s*\S.*?(?: {3,}|\s*\n\s*)\S.+\s*\n\s*(?<time>\d{1,2}[:]?\d{2}(?: *[apAP][mM])?)\s*\n\s*(?<date>.*\d{4}.*)\s*\n\s*(?<airport>[\s\S]+)/";
            $re2 = "/^\s*\S.*?(?: {3,}|\s*\n\s*)\S.+\s*\n(?<airport1>[\s\S]+?\n)\s*(?<time>\d{1,2}[:]?\d{2}(?: *[apAP][mM])?)\s*\n\s*(?<date>.*\d{4}.*)\s*\n\s*(?<airport2>[\s\S]+)/";

            // Departure
            $code = $this->re("/.*\n.* {3,}([A-Z]{3}) *\n/", $routeTable[0]);

            if (!empty($code)) {
                $routeTable[0] = preg_replace("/(.*\n.*) {3,}[A-Z]{3} *\n/", "$1\n", $routeTable[0]);
            }

            if (empty($code)) {
                $code = $this->re("/^(?:\s*(?:{$this->opt($this->t("FROM"))}|{$this->opt($this->t("TAKE-OFF"))})\s*){2,}(?:\n.*)+? {3,}([A-Z]{3}) {3,}/", $routeTable[0]);

                if (!empty($code)) {
                    $routeTable[0] = preg_replace("/^((?:\s*(?:{$this->opt($this->t("FROM"))}|{$this->opt($this->t("TAKE-OFF"))})\s*){2,}(?:\n.*)+?) {3,}[A-Z]{3} {3,}/", "$1\n", $routeTable[0]);
                }
            }
            $s->departure()
                ->code($code);

            if (preg_match($re, $routeTable[0], $m) || preg_match($re2, $routeTable[0], $m)) {
                $m['airport'] = $m['airport'] ?? $m['airport1'] . $m['airport2'];

                if (preg_match("/(?:^|\n)(.*" . $this->opt($this->t("Terminal")) . ".*)(?:$|\n)/i", $m['airport'], $mat)) {
                    $s->departure()
                        ->terminal(trim(preg_replace("/\s*" . $this->opt($this->t("Terminal")) . "\s*/", ' ', $mat[1])), true);
                    $m['airport'] = preg_replace("/(?:^|\n).*" . $this->opt($this->t("Terminal")) . ".*(?:$|\n)/", '', $m['airport']);
                }
                $m['airport'] = preg_replace("/\s+/", ' ', trim($m['airport']));

                if (!empty($m['airport'])) {
                    $s->departure()
                        ->name($m['airport']);
                }
                $s->departure()
                    ->date(strtotime($this->normalizeDate($m['date'] . ', ' . $m['time'])))
                ;
            }

            // Arrival
            $s->arrival()
                ->code($this->re("/\n *([A-Z]{3})(?: {3,}| *\n)/", $routeTable[1]));
            $routeTable[1] = preg_replace("/\n *([A-Z]{3})(?: {3,}| *\n)/", "\n", $routeTable[1]);

            if (preg_match($re, $routeTable[1], $m) || preg_match($re2, $routeTable[1], $m)) {
                $m['airport'] = $m['airport'] ?? $m['airport1'] . $m['airport2'];

                if (preg_match("/(?:^|\n)(.*" . $this->opt($this->t("Terminal")) . ".*)(?:$|\n)/i", $m['airport'], $mat)) {
                    $s->arrival()
                        ->terminal(trim(preg_replace("/\s*" . $this->opt($this->t("Terminal")) . "\s*/", ' ', $mat[1])), true);
                    $m['airport'] = preg_replace("/(?:^|\n).*" . $this->opt($this->t("Terminal")) . ".*(?:$|\n)/", '', $m['airport']);
                }
                $m['airport'] = preg_replace("/\s+/", ' ', trim($m['airport']));

                if (!empty($m['airport'])) {
                    $s->arrival()
                        ->name($m['airport']);
                }
                $s->arrival()
                    ->date(strtotime($this->normalizeDate($m['date'] . ', ' . $m['time'])))
                ;
            }

            // Extra
            $cabin = $this->re("/" . $this->opt($this->t("CLASS OF TRAVEL")) . "[ :]*\s*\n\s*(\S[^\n]+)/ms", $bookingInfo);
            $cabin = preg_replace("/(?:^|\s*\n)\s*({$this->opt($this->t("FREQUENT FLYER"))}|{$this->opt($this->t("CLASS OF TRAVEL"))}|{$this->opt($this->t("BOOKING REFERENCE"))}|\n.*\n{$this->opt($this->t("ETKT"))})[\s\S]*/", '', $cabin);
            $s->extra()
                ->cabin($cabin, true, true)
            ;

            $seat = $this->re("/^\s*[[:alpha:]\\/ ]+\s*\n\s*(\d{1,3}[A-Z])\s*$/u", $flightInfoTable[1]);

            if (!empty($seat)) {
                $s->extra()
                    ->seat($seat);
            }

            $segments = $f->getSegments();

            foreach ($segments as $segment) {
                if ($segment->getId() !== $s->getId()) {
                    if (serialize(array_diff_key($segment->toArray(),
                            ['seats' => []])) === serialize(array_diff_key($s->toArray(), ['seats' => []]))) {
                        if (!empty($s->getSeats())) {
                            $segment->extra()->seats(array_unique(array_merge($segment->getSeats(), $s->getSeats())));
                        }
                        $f->removeSegment($s);

                        break;
                    }
                }
            }
        }

        foreach ($email->getItineraries() as $it) {
            if (empty($it->getConfirmationNumbers()) && $confirmationError === false) {
                $conf = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Booking Reference:'))}]/following::text()[normalize-space()][1]",
                    null, true, "/^\s*([A-Z\d]{5,7})\s*$/");

                if (!empty($conf)) {
                    $it->general()
                        ->confirmation($conf);
                } else {
                    $it->general()
                        ->noConfirmation();
                }
            }
        }

        return true;
    }

    public function detectEmailFromProvider($from)
    {
        return strpos($from, $this->reFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        foreach (self::$detectsProvider as $code => $detect) {
            if (!empty($detect['subjUniqueName']) && $this->containsText($headers['subject'], $detect['subjUniqueName']) === true
                || !empty($detect['from']) && $this->containsText($headers['from'], $detect['from']) === true
            ) {
                $this->providerCode = $code;

                foreach ($this->detectSubject as $dSubject) {
                    if (stripos($headers["subject"], $dSubject) !== false) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfPattern);

        if (!isset($pdfs[0])) {
            $pdfs = $parser->searchAttachmentByName(".*\.pdf");
        }

        if (!isset($pdfs[0])) {
            return false;
        }
        $pdf = $pdfs[0];

        if (($text = \PDF::convertToText($parser->getAttachmentBody($pdf))) === null) {
            return false;
        }

        foreach (self::$detectsProvider as $code => $detect) {
            $detectedProvider = false;

            if (!empty($detect['bodyPdf']) && $this->containsText($text, $detect['bodyPdf']) === true
            ) {
                $this->providerCode = $code;
                $detectedProvider = true;
            }

            if ($detectedProvider === false && !empty($detect['bodyHtml'])) {
                foreach ($detect['bodyHtml'] as $search) {
                    if ((stripos($search, '//') === 0 && $this->http->XPath->query($search)->length > 0)
                        || (stripos($search, '//') === false && $this->http->XPath->query("//node()[{$this->contains($search)}]")->length > 0)
                    ) {
                        $this->providerCode = $code;
                        $detectedProvider = true;

                        break;
                    }
                }
            }

            if ($detectedProvider === false) {
                continue;
            }

            foreach ($this->detectBody as $db) {
                if ($this->containsText($text, $db) === true) {
                    return true;
                }
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->date = strtotime($parser->getHeader('date'));

        $pdfs = $parser->searchAttachmentByName($this->pdfPattern);

        if (!isset($pdfs[0])) {
            $pdfs = $parser->searchAttachmentByName(".*\.pdf");
        }

        if (!isset($pdfs[0])) {
            return null;
        }

        $pdf = $pdfs[0];

        if (($text = \PDF::convertToText($parser->getAttachmentBody($pdf))) === null) {
            return null;
        }

        foreach ($this->detectBody as $lang => $db) {
            if ($this->containsText($text, $db) === true) {
                $this->lang = $lang;

                break;
            }
        }

        $this->parsePdf($email, $text);

        $email->setProviderCode($this->providerCode);

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

    private function t($word)
    {
        // $this->http->log($word);
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function normalizeDate($str)
    {
//        $this->logger->debug('$str 1 = '.print_r( $str,true));
        $year = date("Y", $this->date);
        $in = [
            // 15Feb2023, 1300
            "/^\s*(\d{1,2})\s*([[:alpha:]]+)\s*(\d{4})\s*[,\s]\s*(\d{1,2}):?(\d{2}(?:\s*[ap]m)?)\s*$/i",
        ];
        $out = [
            "$1 $2 $3, $4:$5",
        ];
        $str = preg_replace($in, $out, $str);

//        $this->logger->debug('$str 2 = '.print_r( $str,true));

        if (preg_match("/\d+\s+([^\d\s]+)\s+\d{4}/", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return $str;
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function split($re, $text)
    {
        $r = preg_split($re, $text, -1, PREG_SPLIT_DELIM_CAPTURE);
        $ret = [];

        if (count($r) > 1) {
            array_shift($r);

            for ($i = 0; $i < count($r) - 1; $i += 2) {
                $ret[] = $r[$i] . $r[$i + 1];
            }
        } elseif (count($r) == 1) {
            $ret[] = reset($r);
        }

        return $ret;
    }

    private function TableHeadPos($row)
    {
        $head = array_filter(array_map('trim', explode("|", preg_replace("/\s{2,}/", "|", $row))));
        $pos = [];
        $lastpos = 0;

        foreach ($head as $word) {
            $pos[] = mb_strpos($row, $word, $lastpos, 'UTF-8');
            $lastpos = mb_strpos($row, $word, $lastpos, 'UTF-8') + mb_strlen($word, 'UTF-8');
        }

        return $pos;
    }

    private function SplitCols($text, $pos = false, $trim = true)
    {
        $cols = [];
        $rows = explode("\n", $text);

        if (!$pos) {
            $pos = $this->TableHeadPos($rows[0]);
        }
        arsort($pos);

        foreach ($rows as $row) {
            foreach ($pos as $k=>$p) {
                if ($trim) {
                    $cols[$k][] = trim(mb_substr($row, $p, null, 'UTF-8'));
                } else {
                    $cols[$k][] = mb_substr($row, $p, null, 'UTF-8');
                }
                $row = mb_substr($row, 0, $p, 'UTF-8');
            }
        }
        ksort($cols);

        foreach ($cols as &$col) {
            $col = implode("\n", $col);
        }

        return $cols;
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) { return preg_quote($s, '/'); }, $field)) . ')';
    }

    private function amount($s)
    {
        return (float) str_replace(",", ".", preg_replace("/[.,\s](\d{3})/", "$1", $s));
    }

    private function inOneRow($text)
    {
        $textRows = array_filter(explode("\n", $text));

        if (empty($textRows)) {
            return '';
        }
        $pos = [];
        $length = [];

        foreach ($textRows as $key => $row) {
            $length[] = mb_strlen($row);
        }
        $length = max($length);
        $oneRow = '';

        for ($l = 0; $l < $length; $l++) {
            $notspace = false;

            foreach ($textRows as $key => $row) {
                $sym = mb_substr($row, $l, 1);

                if ($sym !== false && trim($sym) !== '') {
                    $notspace = true;
                    $oneRow[$l] = 'a';
                }
            }

            if ($notspace == false) {
                $oneRow[$l] = ' ';
            }
        }

        return $oneRow;
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

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(" or ", array_map(function ($s) {
            return "contains(normalize-space(.), \"{$s}\")";
        }, $field)) . ')';
    }

    private function eq($field, $text = 'normalize-space(.)')
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($text) {
            return $text . '="' . $s . '"';
        }, $field)) . ')';
    }
}
