<?php

namespace AwardWallet\Engine\tiket\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class ETicketPdf2 extends \TAccountChecker
{
    public $mailFiles = "tiket/it-203087174.eml, tiket/it-203684793.eml, tiket/it-30048260.eml, tiket/it-30505421.eml, tiket/it-30819707.eml, tiket/it-30955247.eml, tiket/it-31001490.eml, tiket/it-31116346.eml, tiket/it-714821706.eml, tiket/it-717017833-id.eml";
    public static $dictionary = [
        "en" => [
            "statusPhrases"  => ["Your flight booking is"],
            "statusVariants" => ["confirmed"],

            // Html(2017)
            "detectFlightHtml" => "Your Flight Details",
            "detectTrainHtml"  => "Your Train Details",
            //            "Booking Code" => "",
            //            "Passengers" => "",

            // Html(2021)
            // "Airline Booking Code" => "",
            "cabinValues" => ["Economy", "Business"],
            // "Direct" => "",
            // "stop" => "",
            // "Passenger Details" => "",

            // Pdf(2017)
            //            "Flight E-ticket" => "",
            //            "Train E-ticket" => "",
            "Your Booking Code" => ["Your booking code", "Your Booking Code"],
            //            "Train Number" => "",
            //            "Train Name" => "",
            //            "Terminal" => "",
            //            "Passenger Details" => "",
            //            "Ticket Type" => "",
            //            "Ticket Number" => "",
            //            "Seat Number" => "",
            //            "Seat" => "",

            // Pdf(2021)
            "directionHeaders" => ["Departure", "Return"],
            "segmentsEnd"      => ["Passenger Details", "Extra Protection", "Important Notes"],
            "passengersStart"  => ["Passenger Details"],
            "passengersEnd"    => ["Extra Protection", "Important Notes", "Terms or Conditions"],
            // "Reference Number" => "",
            // "Booking Code (PNR)" => "",
            "flightAndTicket" => ["Flight & Ticket Number", "Flight & Ticket"],
            // "Facility" => "",
            "transitPhrases" => "transit di",

            // Pdf-Receipt(2017)
            //            "Total Payment" => "",
        ],
        "id" => [
            "statusPhrases"  => ["Pemesanan pesawatmu sudah"],
            "statusVariants" => ["dikonfirmasi"],

            // Html(2017)
            "detectFlightHtml"    => "Detail Penerbangan Anda",
            "detectTrainHtml"     => "Detail Kereta Anda",
            "Booking Code"        => "Kode Booking",
            "Passengers"          => "Penumpang",

            // Html(2021)
            "Airline Booking Code" => "Kode Booking Maskapai",
            "cabinValues"          => ["Ekonomi", "Bisnis"],
            "Direct"               => "Langsung",
            // "stop" => "",
            "Passenger Details" => "Detail Penumpang",

            // Pdf(2017)
            "Flight E-ticket"   => "E-tiket Pesawat",
            "Train E-ticket"    => "E-tiket Kereta",
            "Your Booking Code" => "Kode Booking Anda",
            "Train Number"      => "Nomor Kereta",
            "Train Name"        => "Nama Kereta",
            //            "Terminal" => "",
            "Passenger Details" => "Detail Penumpang",
            "Ticket Type"       => ["Jenis Tiket", "Tipe Tiket"],
            "Ticket Number"     => "Nomor Tiket",
            "Seat Number"       => "Nomor Kursi",
            "Seat"              => "Kursi",

            // Pdf(2021)
            "directionHeaders"   => ["Pergi", "Pulang"],
            "segmentsEnd"        => ["Detail Penumpang", "Catatan Penting"],
            "passengersStart"    => ["Detail Penumpang"],
            "passengersEnd"      => ["Catatan Penting", "Syarat atau ketentuan"],
            "Reference Number"   => "Code (PNR)",
            "Booking Code (PNR)" => "Airline Booking Code (PNR)",
            "flightAndTicket"    => ["No. Penerbangan & Tiket", "No. Penerbangan &"],
            "Facility"           => "Fasilitas",
            "transitPhrases"     => "transit di",

            // Pdf-Receipt(2017)
            "Total Payment" => "Total Pembayaran",
        ],
    ];

    private $detectFrom = "info@tiket.com";

    private $detectSubject = [
        "en" => "eTicket - tiket.com - Order ID", // +id
    ];
    private $detectCompany = "tiket.com";
    private $detectLangHtml = [
        "id" => [
            "Detail Penerbangan Anda",
            "Detail Kereta Anda",
            "Penerbangan Pergi",
            "Penerbangan Pulang",
        ],
        "en" => [
            "Your Flight Details",
            "Your flight booking is confirmed",
            "Your Train Details",
            "Departure Flight",
            "Return Flight",
        ],
    ];

    private $detectBodyPdf = [
        "id" => [
            "E-tiket Pesawat",
            "E-tiket Kereta",
            "Penerbangan Pergi", // type: 2021
        ],
        "en" => [ // "en" always last!!!
            "Flight E-ticket",
            "Train E-ticket",
            "Departure Flight", // type: 2021
        ],
    ];

    private $detectBodyPdfReceipt = [
        "en" => [
            "Payment Detail",
        ],
        "id" => [
            "Total Pembayaran",
        ],
    ];

    private $pdfPattern = ".*\.pdf";
    private $lang = "";

    private $xpath = [
        'time' => '(starts-with(translate(normalize-space(),"0123456789：Hh ","∆∆∆∆∆∆∆∆∆∆:::"),"∆:∆∆") or starts-with(translate(normalize-space(),"0123456789：Hh ","∆∆∆∆∆∆∆∆∆∆:::"),"∆∆:∆∆"))',
    ];

    private $patterns = [
        'time'          => '\d{1,2}[:：]\d{2}(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?)?', // 4:19PM  |  2:00 p. m.
        'travellerName' => '[[:alpha:]][-.\'’[:alpha:]\s]*[[:alpha:]]', // Mr. Hao-Li Huang
        'eTicket'       => '\d{3}(?: | ?- ?)?\d{5,}(?: | ?- ?)?\d{1,3}', // 075-2345005149-02  |  0167544038003-004
    ];

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        // Travel Agency
        if (preg_match("/(Order ID)[:\s]+(\d+)(?:\s|$)/", $parser->getSubject(), $m)) {
            $email->ota()->confirmation($m[2], $m[1]);
        } else {
            $email->obtainTravelAgency();
        }

        $error = false;
        $pdfs = $parser->searchAttachmentByName($this->pdfPattern);

        foreach ($pdfs as $pdf) {
            if (($text = \PDF::convertToText($parser->getAttachmentBody($pdf))) === null) {
                continue;
            }

            foreach ($this->detectBodyPdf as $lang => $detectBody) {
                foreach ($detectBody as $dBody) {
                    if (strpos($text, $dBody) !== false) {
                        $this->lang = $lang;

                        if (!$this->parsePdf($email, $text)) {
                            $this->logger->info("parsePdf is failed");
                            $error = true;
                        }

                        continue 3;
                    }
                }
            }

            foreach ($this->detectBodyPdfReceipt as $lang => $detectBody) {
                foreach ($detectBody as $dBody) {
                    if (strpos($text, $dBody) !== false) {
                        $this->lang = $lang;
                        $this->parsePdfPrice($email, $text);

                        break 2;
                    }
                }
            }
        }

        if ($error === true || count($email->getItineraries()) === 0) {
            $email->clearItineraries();
            $body = html_entity_decode($this->http->Response["body"]);

            foreach ($this->detectLangHtml as $lang => $detectBody) {
                foreach ($detectBody as $dBody) {
                    if (stripos($body, $dBody) !== false
                        || $this->http->XPath->query("//*[{$this->contains($dBody)}]")->length > 0
                    ) {
                        $this->lang = $lang;

                        break 2;
                    }
                }
            }

            $this->parseHtml($email);
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailFromProvider($from)
    {
        return strpos($from, $this->detectFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (empty($headers["from"]) || empty($headers["subject"])) {
            return false;
        }

        if (stripos($headers["from"], $this->detectFrom) === false) {
            return false;
        }

        foreach ($this->detectSubject as $dSubject) {
            if (stripos($headers["subject"], $dSubject) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $body = $this->http->Response['body'];

        if (strpos($body, $this->detectCompany) === false) {
            return false;
        }

        foreach ($this->detectLangHtml as $detectBody) {
            foreach ($detectBody as $dBody) {
                if (strpos($body, $dBody) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        $pdfCount = 2;
        $htmlCount = 2;

        return count(self::$dictionary) * ($pdfCount + $htmlCount);
    }

    private function parsePdfPrice(Email $email, string $text): void
    {
        // Price
        $total = $this->re("#" . $this->preg_implode($this->t("Total Payment")) . "(?:\s+.+?\s*)[ ]{2,}(.*\d.*)\n#", $text);

        if (preg_match("#^\s*(?<currency>[A-Z]{3})\s*(?<amount>\d[\d\., ]*)\s*$#", $total, $m)
                || preg_match("#^\s*(?<amount>\d[\d\., ]*)\s*(?<currency>[A-Z]{3})\s*$#", $total, $m)) {
            $email->price()
                ->total($this->amount($m['amount']))
                ->currency($this->currency($m['currency']))
            ;
        }
    }

    private function parsePdf(Email $email, string $text): bool
    {
        if (preg_match("#{$this->preg_implode($this->tPlusEn("Flight E-ticket"))}#", $text)
            && ($segmentsText2021 = $this->re("#^([ ]*{$this->preg_implode($this->t("directionHeaders"))}\n+.+?)\n+[ ]*{$this->preg_implode($this->t("segmentsEnd"))}#ms", $text))
        ) {
            return $this->parseFlightPdf2021($email, $text, $segmentsText2021);
        } elseif (preg_match("#{$this->preg_implode($this->tPlusEn("Flight E-ticket"))}#", $text)) {
            return $this->parseFlightPdf2017($email, $text);
        }

        if (preg_match("#{$this->preg_implode($this->tPlusEn("Train E-ticket"))}#", $text)) {
            return $this->parseTrainPdf2017($email, $text);
        }

        return false;
    }

    private function parseFlightPdf2017(Email $email, string $text): bool
    {
        $this->logger->debug(__FUNCTION__);
        $f = $email->add()->flight();

        $f->general()
            ->confirmation($this->re("#" . $this->preg_implode($this->t("Your Booking Code")) . "\s+([A-Z\d]{5,7})\s+#s", $text))
        ;

        $orderId = $this->re("/{$this->t('Order ID')}[\s\:]+(\d+)/s", $text);

        if (!empty($orderId)) {
            $f->ota()->confirmation($orderId, $this->t('Order ID'));
        }

        $passBegin = strpos($text, $this->t("Passenger Details"));

        if (!empty($passBegin)) {
            $passengersText = substr($text, $passBegin);
            $passengersText = $this->re("#(.+[ ]*" . $this->preg_implode($this->t("Passengers")) . "[ ]*" . $this->preg_implode($this->t("Ticket Type")) . ".+\s+\n(?:[ ]*\d+[ ]+.+\s*\n|[ ]{10,}.*\n+)+)#", $passengersText);
            $passTable = $this->splitCols($passengersText);

            foreach ($passTable as $key => $column) {
                if (stripos($column, $this->t("Ticket Number")) !== false
                        && preg_match_all("#(?:^|\n)\s*(\d{5,})\s*?(?:\n|$)#", $column, $m)) {
                    $f->issued()->tickets(array_unique($m[1]), false);
                }

                if (stripos($column, $this->t("Passengers")) !== false) {
                    $passColumn = $key;
                }
            }

            if (preg_match_all("#\n\s*\d+[ ]+(?<name>.+?)[ ]{2,}.+(?:\n[ ]{0,20}(?<name2>[^\d\W].+?)(?:[ ]{2,}|(?=\n|$)))?#u", $passengersText, $m)
                    && $passColumn === 1) {
                foreach ($m[0] as $key => $value) {
                    $f->general()->traveller(trim($m['name'][$key] . ' ' . ($m['name2'][$key] ?? '')));
                }
            } elseif (isset($passColumn) && array_key_exists($passColumn, $passTable)) {
                $f->general()->travellers(array_filter(array_map('trim', explode("\n\n", $this->re("#^\s*" . $this->t("Passengers") . "\s*([\s\S]+)#", $passTable[$passColumn])))));
            }
        }

        $flightText = $text;

        if (!empty($passBegin)) {
            $flightText = substr($flightText, 0, $passBegin);
        }

        $segments = $this->splitText($flightText, "#\n(.*[ ]{5,}\d{1,2} [^\d\W]{3,12} \d{4}[ ]{5,}\d{1,2} [^\d\W]{3,12} \d{4}[ ]*\n)#", true);

        foreach ($segments as $stext) {
            $s = $f->addSegment();

            $stext = $this->re("#([\s\S]+?)(?:\n\n\n|$)#", $stext);
            $stext = preg_replace("/(\(\s*PNR\s*\))\s{3}([A-Z\d]{6})\b/", "$1 $2", $stext);

            $fTable = $this->splitCols($stext, $this->rowColsPos($this->inOneRow($stext)));

            if (count($fTable) < 4) {
                $this->logger->debug('Pdf: parse flight table is failed:');
                $this->logger->info($stext);

                return false;
            }

            if (preg_match("#.+\s+(?<al>[A-Z\d][A-Z]|[A-Z][A-Z\d])[ ]*(?<fn>\d{1,5})(?:\n|$)#s", $fTable[0], $m)) {
                $s->airline()
                    ->name($m['al'])
                    ->number($m['fn'])
                ;
            } elseif (preg_match("#.+\s+(?<al>[A-Z\d][A-Z]|[A-Z][A-Z\d])[ ]*(?<fn>\d{1,5})(?:\n|$)#s", $fTable[1], $m)) {
                $s->airline()
                    ->name($m['al'])
                    ->number($m['fn'])
                ;
                $c0 = array_shift($fTable);

                if (preg_match("#\([^\)]+ ([A-Z]{1,2})[ ]*\)#", $c0, $mat)) {
                    $s->extra()
                        ->bookingCode($mat[1])
                    ;
                } elseif (preg_match("#\s*(.+)\s*#", $c0, $mat)) {
                    $s->extra()
                        ->cabin($mat[1])
                    ;
                }
            }

            /*
                04 Oktober 2022
                10:40
                Medan (KNO)
                Kuala Namu, Terminal 1 Domestik
            */
            $patternAirport = "/^\s*"
                . "(?<date>.{4,}?)\s+(?<time>{$this->patterns['time']}).*\n+"
                . "[ ]*(?<city>.+?)\s*\(\s*(?<code>[A-Z]{3})\s*\)"
                . "(?:\n+[ ]*(?<airport>.{2,}?)(?:\s*,\s*(?<terminal>[^,]*(?i){$this->preg_implode($this->t("Terminal"))}[^,]*?))?)?"
            . "\s*$/";

            if (preg_match($patternAirport, $fTable[1], $m)) {
                $s->departure()
                    ->code($m['code'])
                    ->name((empty($m['airport']) ? '' : $m['airport'] . ', ') . $m['city'])
                    ->date(strtotime($m['time'], $this->normalizeDate($m['date'])))
                ;

                if (!empty($m['terminal'])) {
                    $s->departure()
                        ->terminal(trim(preg_replace(['/\s+/', "#\s*{$this->preg_implode($this->t("Terminal"))}\s*#i"], ' ', $m['terminal'])));
                }
            } else {
                //return false;
            }

            if (preg_match($patternAirport, $fTable[3], $m)) {
                $s->arrival()
                    ->code($m['code'])
                    ->name((empty($m['airport']) ? '' : $m['airport'] . ', ') . $m['city'])
                    ->date(strtotime($m['time'], $this->normalizeDate($m['date'])))
                ;

                if (!empty($m['terminal'])) {
                    $s->arrival()
                        ->terminal(trim(preg_replace("#\s*" . $this->preg_implode($this->t("Terminal")) . "\s*#i", " ", $m['terminal'])));
                }
            }

            if (preg_match("#^\s*(\S.+)#", $fTable[2], $m)) {
                $s->extra()->duration($m[1]);
            }
        }

        return true;
    }

    private function parseFlightPdf2021(Email $email, string $text, ?string $segmentsText): bool
    {
        // examples: it-714821706.eml, it-717017833-id.eml
        $this->logger->debug(__FUNCTION__);

        // remove garbage

        /*
        $text = preg_replace([
            "#.*\bPage \d+ of \d+\b.*#i",
        ], '', $text);
        */

        $f = $email->add()->flight();

        if (preg_match_all("#(?:^[ ]*|[ ]{2})({$this->preg_implode($this->tPlusEn("Reference Number"))})[ ]*[:]+[ ]*([A-Z\d]{5,10})$#m", $text, $confNoMatches)
            && count(array_unique($confNoMatches[2])) === 1
        ) {
            $f->general()->confirmation($confNoMatches[2][0], $confNoMatches[1][0]);
        }

        $extraTextByPax = $tickets = [];
        $passengersText = $this->re("#\n[ ]*{$this->preg_implode($this->t("passengersStart"))}\n+(.+?)\n+[ ]*{$this->preg_implode($this->t("passengersEnd"))}#s", $text);

        $tablePaxPos_C2 = [0];

        if (preg_match("#^(.{10,}? ){$this->preg_implode($this->t("flightAndTicket"))}#m", $passengersText, $matches)) {
            $tablePaxPos_C2[] = mb_strlen($matches[1]);
        }

        $tablePaxPos_C3 = $tablePaxPos_C2;

        if (preg_match("#^(.{20,}? ){$this->preg_implode($this->t("Facility"))}#m", $passengersText, $matches)) {
            $tablePaxPos_C3[] = mb_strlen($matches[1]);
        }

        $paxRows = $this->splitText($passengersText, "#^([ ]{0,15}\d{1,3}[. ]+{$this->patterns['travellerName']})#m", true);

        foreach ($paxRows as $pRow) {
            $passengerName = null;

            $tablePax_C2 = $this->splitCols($pRow, $tablePaxPos_C2);
            $tablePax_C3 = $this->splitCols($pRow, $tablePaxPos_C3);

            if (count($tablePax_C2) === 2) {
                $passengerName = $this->normalizeTraveller(preg_replace('/\s+/', ' ', $this->re("#^[ ]{0,15}\d{1,3}[. ]+({$this->patterns['travellerName']})(?:\s*\(|\n{3}|\s*$)#u", $tablePax_C2[0])));
                $f->general()->traveller($passengerName, true);

                if ($passengerName) {
                    $extraTextByPax[$passengerName] = $tablePax_C2[1];
                }

                if (count($tablePax_C3) === 3) {
                    $tablePax_C3[1] = preg_replace([
                        '/^(.*?\S.*?)\n{3}.*$/s',
                        '/^[ ]*(?:[A-Z][A-Z\d]|[A-Z\d][A-Z]) \d{1,6}[ ]*$/m',
                    ], [
                        '$1',
                        '',
                    ], $tablePax_C3[1]);

                    $ticketValues = preg_split("/([ ]*[,\n][ ]*)+/", trim($tablePax_C3[1]));

                    $ticketRelative = null;

                    foreach ($ticketValues as $tktVal) {
                        $ticket = null;

                        if (preg_match("/^[A-Z\d]{2}$/", $tktVal) && $ticketRelative) {
                            // C2
                            $ticket = preg_replace('/^(.+).{2}$/', '$1' . $tktVal, $ticketRelative);
                        } elseif (preg_match("/^(?:{$this->patterns['eTicket']}(?:[A-Z]\d+)?|[A-Z\d]{5,10})$/", $tktVal)) {
                            // 9771084819659C1  |  K2MV7L
                            $ticket = str_replace([' ', '-'], '', $tktVal);
                        }

                        $ticketRelative = $ticket;

                        if ($ticket && !in_array($ticket, $tickets)) {
                            $f->issued()->ticket($ticket, false, $passengerName);
                            $tickets[] = $ticket;
                        }
                    }
                }
            }
        }

        $segments = $this->splitText($segmentsText, "#(?:^[ ]*{$this->preg_implode($this->t("directionHeaders"))}|.+{$this->preg_implode($this->t("transitPhrases"))}.+)\n+#m");

        if (count($segments) === 0) {
            $this->logger->debug('Pdf: flight segments not found!');

            return false;
        }

        foreach ($segments as $sText) {
            $s = $f->addSegment();

            if (preg_match("#^\s*{$this->preg_implode($this->tPlusEn("Booking Code (PNR)"))}[: ]+([A-Z\d]{5,10})(?:[ ]{2}.+)?\n+([\s\S]+)$#", $sText, $m)) {
                $s->airline()->confirmation($m[1]);
                $sText = $m[2];
            }

            $tablePos = [0];

            if (preg_match("#^((.+) {$this->patterns['time']}[ ]+) \S.+#", $sText, $matches)) {
                $tablePos[] = mb_strlen($matches[2]);
                $tablePos[] = mb_strlen($matches[1]);
            }

            $table = $this->splitCols($sText, $tablePos);

            if (count($table) !== 3) {
                $this->logger->debug('Pdf: wrong segment table!');

                continue;
            }

            /*
                Nam Air
                IN-280
                Economy
            */

            if (preg_match("/^[ ]*(?<name>[A-Z][A-Z\d]|[A-Z\d][A-Z])[- ]*(?<number>\d+)[ ]*$/m", $table[0], $matches)) {
                $s->airline()->name($matches['name'])->number($matches['number']);

                foreach ($extraTextByPax as $passengerName => $extraText) {
                    if (preg_match("#^{$matches['name']}[- ]?{$matches['number']}[ ]{2}.+[ ]{2}(\d+[A-Z])$#m", $extraText, $m)) {
                        $s->extra()->seat($m[1], false, false, $passengerName);
                    }
                }
            }

            if (preg_match("#^[ ]*({$this->preg_implode($this->t("cabinValues"))})[ ]*$#im", $table[0], $matches)) {
                $s->extra()->cabin(preg_replace('/\s+/', ' ', $matches[1]));
            }

            /*
                09:00
                Wed, 25 Sep 2024

                10:00
                Wed, 25 Sep 2024
            */
            $patternCol1 = "/^\s*"
                . "(?<time1>{$this->patterns['time']}).*\n{1,2}"
                . "[ ]*(?<date1>.{4,}\b\d{4})\n+"
                . "[ ]*(?<time2>{$this->patterns['time']}).*\n{1,2}"
                . "[ ]*(?<date2>.{4,}\b\d{4})"
            . "\s*$/";

            if (preg_match($patternCol1, $table[1], $matches)) {
                $s->departure()->date(strtotime($matches['time1'], $this->normalizeDate($matches['date1'])));
                $s->arrival()->date(strtotime($matches['time2'], $this->normalizeDate($matches['date2'])));
            }

            $airports = $this->splitText($table[2], "#(.{2,}\(\s*[A-Z]{3}\s*\))$#m", true);

            if (count($airports) !== 2) {
                $this->logger->debug('Pdf: bad airport count!');

                continue;
            }

            /*
                Jakarta (CGK)
                Soekarno Hatta, Terminal 2F Internasional
            */
            $patternAirport = "/^\s*"
                . "[ ]*(?<city>.+?)\s*\(\s*(?<code>[A-Z]{3})\s*\)"
                . "(?:\n+[ ]*(?<airport>.{2,}?)(?:\s*,\s*(?<terminal>[^,]*(?i){$this->preg_implode($this->t("Terminal"))}[^,]*?))?)?"
            . "\s*$/";

            if (preg_match($patternAirport, $airports[0], $m)) {
                $s->departure()
                    ->code($m['code'])
                    ->name((empty($m['airport']) ? '' : $m['airport'] . ', ') . $m['city'])
                ;

                if (!empty($m['terminal'])) {
                    $s->departure()->terminal(trim(preg_replace(['/\s+/', "#\s*{$this->preg_implode($this->t("Terminal"))}\s*#i"], ' ', $m['terminal'])));
                }
            }

            if (preg_match($patternAirport, $airports[1], $m)) {
                $s->arrival()
                    ->code($m['code'])
                    ->name((empty($m['airport']) ? '' : $m['airport'] . ', ') . $m['city'])
                ;

                if (!empty($m['terminal'])) {
                    $s->arrival()->terminal(trim(preg_replace(['/\s+/', "#\s*{$this->preg_implode($this->t("Terminal"))}\s*#i"], ' ', $m['terminal'])));
                }
            }
        }

        return true;
    }

    private function parseTrainPdf2017(Email $email, string $text): bool
    {
        $this->logger->debug(__FUNCTION__);
        $t = $email->add()->train();

        $t->general()
            ->confirmation($this->re("#" . $this->preg_implode($this->t("Your Booking Code")) . "\s+([A-Z\d]{5,7})\s+#i", $text))
        ;

        $passBegin = strpos($text, $this->t("Passenger Details"));

        if (!empty($passBegin)) {
            $passengersText = substr($text, $passBegin);
            $passengersText = $this->re("#(.+[ ]*" . $this->preg_implode($this->t("Passengers")) . "[ ]*" . $this->preg_implode($this->t("Ticket Type")) . ".+\s+\n(?:[ ]*\d+[ ]+.+\s*\n|[ ]{10,}.*\n)+)#", $passengersText);
            $passTable = $this->splitCols($passengersText);

            foreach ($passTable as $column) {
                if (stripos($column, $this->t("Seat Number")) !== false) {
                    $trainsSeats = $this->re("#^\s*" . $this->preg_implode($this->t("Seat Number")) . "\s+(.+)#s", $column);
                }
            }

            if (preg_match_all("#\n\s*\d+[ ]+(?<name>.+?)[ ]{2,}.+(?:\n[ ]{0,20}(?<name2>[^\d\W].+?)(?:[ ]{2,}|(?=\n|$)))?#", $passengersText, $m)) {
                foreach ($m[0] as $key => $value) {
                    $t->general()->traveller(trim($m['name'][$key] . ' ' . ($m['name2'][$key] ?? '')));
                }
            }
        }

        $trainText = $text;

        if (!empty($passBegin)) {
            $trainText = substr($trainText, 0, $passBegin);
        }

        $segments = $this->splitText($trainText, "#([ ]+{$this->preg_implode($this->t("Train Number"))}[ ]?: )#", true);

        foreach ($segments as $stext) {
            $s = $t->addSegment();

            if (preg_match("#[ ]+" . $this->preg_implode($this->t("Train Number")) . "[ ]?: (\d+)#", $stext, $m)) {
                $s->extra()->number($m[1]);
            }

            $stext = $this->re("#" . $this->preg_implode($this->t("Train Number")) . ".*\s*\n([\s\S]+)#", $stext);
            $tTable = $this->splitCols($stext, $this->rowColsPos($this->inOneRow($stext)));

            if (count($tTable) < 4) {
                $this->logger->debug("Pdf: parse flight table is failed: $stext");

                return false;
            }

            if (preg_match("#" . $this->preg_implode($this->t("Train Name")) . "\s+(?<name>.+)\s*\n\s*(?<cabin>.+) - Subclass (?<class>[A-Z]{1,2})(\s+|$)#s", $tTable[0], $m)) {
                $s->extra()
                    ->service($m['name'])
                    ->cabin($m['cabin'])
                    ->bookingCode($m['class'])
                ;
            }

            $patternStation = "/(?<date>.+?)\s+(?<time>{$this->patterns['time']}).*\n\s*(?<name>[\s\S]+?)$/";

            if (preg_match($patternStation, $tTable[1], $m)) {
                $s->departure()
                    ->name($m['name'])
                    ->date(strtotime($m['time'], $this->normalizeDate($m['date'])))
                ;
            }

            if (preg_match($patternStation, $tTable[3], $m)) {
                $s->arrival()
                    ->name($m['name'])
                    ->date(strtotime($m['time'], $this->normalizeDate($m['date'])))
                ;
            }

            if (preg_match("#^\s*(\S.+)#", $tTable[2], $m)) {
                $s->extra()->duration($m[1]);
            }

            if (!empty($trainsSeats) && count($segments) == 1
                    && preg_match_all("#" . $this->preg_implode($this->t("Seat")) . "[ ]+([\dA-Z]+)\b#", $trainsSeats, $m)) {
                $s->extra()->seats($m[1]);
            }
        }

        return true;
    }

    private function parseHtml(Email $email): void
    {
        $segmentsType2021 = $this->findSegmentsHtml2021();

        if ($segmentsType2021->length > 0) { // && $flight === true
            $this->parseFlightHtml2021($email, $segmentsType2021);
        } elseif ($this->http->XPath->query("//*[{$this->contains($this->t("detectFlightHtml"))}]")->length > 0) {
            $this->parseFlightHtml2017($email);
        }

        if ($this->http->XPath->query("//*[{$this->contains($this->t("detectTrainHtml"))}]")->length > 0) {
            $this->parseTrainHtml2017($email);
        }
    }

    private function parseFlightHtml2017(Email $email): void
    {
        // examples: it-30048260.eml
        $this->logger->debug(__FUNCTION__);
        $f = $email->add()->flight();

        // General
        $travellers = array_values(array_filter($this->http->FindNodes("//text()[" . $this->eq($this->t("Passengers")) . "]/following::table[1]//tr/td[1]",
                null, "#^\s*\d+\.\s*(?:Mrs|Mr)?[.]?\s+(\D+)\s*$#")));
        $f->general()
            ->confirmation($this->http->FindSingleNode("//text()[" . $this->eq($this->t("Booking Code")) . "]/following::text()[normalize-space()][1]", null, true, "#^\s*([A-Z\d]{5,7})\s*$#"))
            ->travellers($travellers, true);

        // Segments
        $xpath = "//img[contains(@src, 'icon-flight-dest')]/ancestor::tr[contains(translate(., '0123456789', 'dddddddddd'),'d:dd')][1]";
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $s = $f->addSegment();

            $node = implode("\n", $this->http->FindNodes("./preceding::table[normalize-space()][1]//text()[normalize-space()]", $root));

            if (preg_match("#\s+(?<al>[A-Z\d][A-Z]|[A-Z][A-Z\d])[ ]*(?<fn>\d{1,5})(?:\s*\|\s*[A-Z\d]{2}[ ]*\d{1,5})?\s*(\n\s*(?<cabin>.+))?\s*$#", $node, $m)) {
                // Airline
                $s->airline()
                    ->name($m['al'])
                    ->number($m['fn']);

                if (!empty($m['cabin']) && preg_match("#\([^\)]+ ([A-Z]{1,2})[ ]*\)#", $m['cabin'], $mat)) {
                    $s->extra()
                        ->bookingCode($mat[1]);
                } elseif (!empty($m['cabin'])) {
                    $s->extra()
                        ->cabin($m['cabin']);
                }
            }

            $node = implode("\n", $this->http->FindNodes("./td[1]//text()[normalize-space()]", $root));

            if (preg_match("/^\s*(?<time1>{$this->patterns['time']})\s*(?<date1>[\s\S]+?)\n\s*(?<time2>{$this->patterns['time']})\s*(?<date2>[\s\S]+)$/", $node, $m)) {
                $s->departure()->date(strtotime($m['time1'], $this->normalizeDate($m['date1'])));
                $s->arrival()->date(strtotime($m['time2'], $this->normalizeDate($m['date2'])));
            }

            $node = implode("\n", $this->http->FindNodes("./td[3]//text()[normalize-space()]", $root));

            if (preg_match("#^\s*(?<dName>.+)\((?<dCode>[A-Z]{3})\)\s+[\s\S]+?\n\s*(?<duration>\d+[^\d\W]{1,3}[ ]*\d+[^\d\W]{1,3}\s*\n)?"
                    . "\s*(?<aName>.+)\((?<aCode>[A-Z]{3})\)\s+#", $node, $m)) {
                // Departure
                $s->departure()
                    ->code($m['dCode'])
                    ->name($m['dName'])
                ;

                // Arrival
                $s->arrival()
                    ->code($m['aCode'])
                    ->name($m['aName'])
                ;

                if (!empty($m['duration'])) {
                    $s->extra()->duration($m['duration']);
                }
            }
        }
    }

    private function parseFlightHtml2021(Email $email, \DOMNodeList $segments): void
    {
        // examples: it-203087174.eml, it-203684793.eml, it-714821706.eml
        $this->logger->debug(__FUNCTION__);
        $f = $email->add()->flight();
        $airlineBookingCodes = [];

        $statusTexts = array_filter($this->http->FindNodes("//text()[{$this->contains($this->t('statusPhrases'))}]", null, "/{$this->preg_implode($this->t('statusPhrases'))}[:\s]+({$this->preg_implode($this->t('statusVariants'))})(?:\s*[,.;:!?]|$)/i"));

        if (count(array_unique(array_map('mb_strtolower', $statusTexts))) === 1) {
            $status = array_shift($statusTexts);
            $f->general()->status($status);
        }

        foreach ($segments as $root) {
            $s = $f->addSegment();

            $segConfirmation = $this->http->FindSingleNode("ancestor::tr[ preceding-sibling::tr[normalize-space()] ][1]/preceding-sibling::tr[normalize-space()][position()<3]/descendant::text()[normalize-space()][{$this->eq($this->t("Airline Booking Code"), "translate(.,':','')")}]/following::text()[normalize-space()][1]", $root, true, "/^[A-Z\d]{5,10}$/");

            if ($segConfirmation) {
                $s->airline()->confirmation($segConfirmation);
                $airlineBookingCodes[] = $segConfirmation;
            }

            $flightText = $this->htmlToText($this->http->FindHTMLByXpath("ancestor::tr[ preceding-sibling::tr[normalize-space()] ][1]/preceding-sibling::tr[normalize-space()][1]", null, $root));
            $flight = $this->re("/^\s*(\S.*\S)/", $flightText);
            $flightsValues = preg_split("/(\s*\|\s*)+/", $flight);
            $cabin = $this->re("/\n({$this->preg_implode($this->t("cabinValues"))})(?:\n|$)/", $flightText);

            $dateDep = $dateArr = $codeDep = $codeArr = $nameDep = $nameArr = null;

            $column1Rows = $column2Rows = [];
            $segmentRows = $this->http->XPath->query("tr[normalize-space()]", $root);

            foreach ($segmentRows as $segRow) {
                $column1Rows[] = implode(' ', $this->http->FindNodes("descendant-or-self::*[ *[2] ][1]/*[1]/descendant::text()[normalize-space()]", $segRow));
                $column2Rows[] = implode(' ', $this->http->FindNodes("descendant-or-self::*[ *[2] ][1]/*[normalize-space()][last()]/descendant::text()[normalize-space()]", $segRow));
            }

            $column1 = implode("\n", $column1Rows);
            $column2 = implode("\n", $column2Rows);

            /*
                09:00
                25 September 2024

                10:00
                25 September 2024
            */
            $patternCol1 = "/^\s*"
                . "(?<time1>{$this->patterns['time']}).*\n{1,2}"
                . "[ ]*(?<date1>.{4,}\b\d{4})\n+"
                . "[ ]*(?<time2>{$this->patterns['time']}).*\n{1,2}"
                . "[ ]*(?<date2>.{4,}\b\d{4})"
            . "\s*$/";

            if (preg_match($patternCol1, $column1, $matches)) {
                $dateDep = strtotime($matches['time1'], $this->normalizeDate($matches['date1']));
                $dateArr = strtotime($matches['time2'], $this->normalizeDate($matches['date2']));
            }

            /*
                Denpasar-Bali (DPS)
                Ngurah Rai
                    1h 0m Direct
                Tambolaka (TMC)
                Tambolaka
            */
            $patternCol2 = ".+\(\s*(?<code>[A-Z]{3})\s*\)\n(?<name>\D.+)";

            if (preg_match("/^{$patternCol2}\n/", $column2, $matches)) {
                $codeDep = $matches['code'];
                $nameDep = $matches['name'];
            }

            if (preg_match("/\n{$patternCol2}$/", $column2, $matches)) {
                $codeArr = $matches['code'];
                $nameArr = $matches['name'];
            }

            if (count($flightsValues) > 2) {
                $this->logger->debug('Wrong flight segment!');

                continue;
            }

            if (count($flightsValues) === 2) {
                // it-203684793.eml

                if (preg_match("/^(?:.{2,}?\s+)?(?<name>[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(?<number>\d+)$/", $flightsValues[0], $m)) {
                    $s->airline()->name($m['name'])->number($m['number']);
                }

                $s->extra()->cabin($cabin, false, true);
                $s->departure()->date($dateDep)->code($codeDep)->name($nameDep);

                // 13j 30m    1 Stop (DOH)
                $transitAirport = preg_match("/\b1\s*{$this->preg_implode($this->t("stop"))}s?\s*\(\s*([A-Z]{3})\s*\)\n/i", $column2, $m) ? $m[1] : null;
                $s->arrival()->code($transitAirport)->noDate();

                $s = $f->addSegment();

                if ($segConfirmation) {
                    $s->airline()->confirmation($segConfirmation);
                }

                if (preg_match("/^(?:.{2,}?\s+)?(?<name>[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(?<number>\d+)$/", $flightsValues[1], $m)) {
                    $s->airline()->name($m['name'])->number($m['number']);
                }

                $s->extra()->cabin($cabin, false, true);
                $s->departure()->code($transitAirport)->noDate();
                $s->arrival()->date($dateArr)->code($codeArr)->name($nameArr);

                continue;
            }

            if (preg_match("/^(?:.{2,}?\s+)?(?<name>[A-Z][A-Z\d]|[A-Z\d][A-Z])[ ]?(?<number>\d+)$/", $flight, $m)) {
                // Batik Air Indonesia ID 7151
                $s->airline()->name($m['name'])->number($m['number']);
            }

            $s->extra()->cabin($cabin, false, true);
            $s->departure()->date($dateDep)->code($codeDep)->name($nameDep);
            $s->arrival()->date($dateArr)->code($codeArr)->name($nameArr);

            if (preg_match("/\n(?<duration>(?:[ ]*\d{1,3}[ ]*[hmj]+)+)[ ]*(?<stops>\S.*)?\n/i", $column2, $matches)) {
                $s->extra()->duration(trim($matches['duration']));

                if (!empty($matches['stops'])) {
                    if (preg_match("/^{$this->preg_implode($this->t("Direct"))}$/iu", $matches['stops']) > 0) {
                        // Direct
                        $s->extra()->stops(0);
                    } elseif (preg_match("/^(\d{1,3})\s*{$this->preg_implode($this->t("stop"))}s?(?:\s*\(|$)/iu", $matches['stops'], $m)) {
                        // 1 Stop
                        $s->extra()->stops($m[1]);
                    }
                }
            }
        }

        $travellerRows = $this->http->XPath->query("//*/tr[normalize-space()][1][{$this->eq($this->t("Passenger Details"))}]/following-sibling::tr[normalize-space()]");

        foreach ($travellerRows as $tRow) {
            $passengerNameVal = implode("\n", $this->http->FindNodes("descendant::text()[normalize-space()]", $tRow));

            if (preg_match("/^\d{1,3}[.\s]+({$this->patterns['travellerName']})(?:\n|$)/u", $passengerNameVal, $m)) {
                $f->general()->traveller($m[1], true);
            }
        }

        if (count($airlineBookingCodes) > 0) {
            $f->general()->noConfirmation();
        }
    }

    private function parseTrainHtml2017(Email $email): void
    {
        $this->logger->debug(__FUNCTION__);
        $t = $email->add()->train();

        // General
        $travellers = array_values(array_filter($this->http->FindNodes("//text()[" . $this->eq($this->t("Passengers")) . "]/following::table[1]//tr/td[1]",
                null, "#^\s*\d+\.\s*(?:Mrs|Mr)?[.]?\s+(\D+)\s*$#")));
        $t->general()
            ->confirmation($this->http->FindSingleNode("//text()[" . $this->eq($this->t("Booking Code")) . "]/following::text()[normalize-space()][1]", null, true, "#^\s*([A-Z\d]{5,7})\s*$#"))
            ->travellers($travellers, true);

        // Segments
        $xpath = "//img[contains(@src, 'icon-flight-dest')]/ancestor::tr[contains(translate(., '0123456789', 'dddddddddd'),'d:dd')][1]";
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $s = $t->addSegment();

            $node = implode("\n", $this->http->FindNodes("./preceding::table[normalize-space()][1]//text()[normalize-space()]", $root));

            if (preg_match("#^\s*(?<name>.+)\n\s*(?<cabin>.+)#", $node, $m)) {
                $s->extra()
                    ->service($m['name'])
                    ->noNumber();
            }

            if (preg_match("#^\s*(?<name>.+)\s*\n\s*(?<cabin>.+) - Subclass (?<class>[A-Z]{1,2})(\s+|$)#s", $node, $m)) {
                $s->extra()
                    ->service($m['name'])
                    ->cabin($m['cabin'])
                    ->bookingCode($m['class'])
                ;
            }

            $node = implode("\n", $this->http->FindNodes("./td[1]//text()[normalize-space()]", $root));

            if (preg_match("/^\s*(?<time1>{$this->patterns['time']})\s*(?<date1>[\s\S]+?)\n\s*(?<time2>{$this->patterns['time']})\s*(?<date2>[\s\S]+)$/", $node, $m)) {
                $s->departure()->date(strtotime($m['time1'], $this->normalizeDate($m['date1'])));
                $s->arrival()->date(strtotime($m['time2'], $this->normalizeDate($m['date2'])));
            }

            $node = implode("\n", $this->http->FindNodes("./td[3]//text()[normalize-space()]", $root));

            if (preg_match("#^\s*(?<dName>.+\([^\)]+\))?\n\s*(?<duration>\d+[^\d\W]{1,3}[ ]*\d+[^\d\W]{1,3}\s*\n)?"
                    . "\s*(?<aName>.+\([^\)]+\))(\s+|$)#", $node, $m)) {
                // Departure
                $s->departure()
                    ->name($m['dName'])
                ;

                // Arrival
                $s->arrival()
                    ->name($m['aName'])
                ;

                if (!empty($m['duration'])) {
                    $s->extra()->duration($m['duration']);
                }
            }
        }
    }

    private function findSegmentsHtml2021(): \DOMNodeList
    {
        $xpathTimeCell = "descendant::*[normalize-space() and not(.//tr[normalize-space()])][1][{$this->xpath['time']}]";

        return $this->http->XPath->query("//*[ tr[normalize-space()][1][{$xpathTimeCell}] and tr[normalize-space()][last()-1][{$xpathTimeCell}] and tr[normalize-space()][4] ]");
    }

    private function t(string $phrase, string $lang = '')
    {
        if (!isset(self::$dictionary, $this->lang)) {
            return $phrase;
        }

        if ($lang === '') {
            $lang = $this->lang;
        }

        if (empty(self::$dictionary[$lang][$phrase])) {
            return $phrase;
        }

        return self::$dictionary[$lang][$phrase];
    }

    private function tPlusEn(string $s): array
    {
        return array_unique(array_merge((array) $this->t($s), (array) $this->t($s, 'en')));
    }

    private function normalizeDate($str)
    {
        //		$this->http->log('normalizeDate = '.print_r( $str,true));
        $in = [
            // Wednesday, 26 Dec 2018  |  24 Desember 2018
            "/^\s*(?:[-[:alpha:]]+[,.\s]+)?(\d{1,2})[,.\s]*([[:alpha:]]+)[,.\s]*(\d{4})\s*$/u",
        ];
        $out = [
            '$1 $2 $3',
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("/\d+\s+([[:alpha:]]+)\s+\d{4}/u", $str, $m)) {
            if (($en = MonthTranslate::translate($m[1], $this->lang))
                || ($en = MonthTranslate::translate($m[1], 'id'))
            ) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return strtotime($str);
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function amount($price)
    {
        $price = str_replace(['.'], '', $price);

        if (is_numeric($price)) {
            return (float) $price;
        }

        return null;
    }

    private function currency($s)
    {
        if ($code = $this->re("#^\s*([A-Z]{3})\s*$#", $s)) {
            return $code;
        }
        $sym = [
            '€'=> 'EUR',
            '$'=> 'USD',
            '£'=> 'GBP',
        ];

        foreach ($sym as $f => $r) {
            if ($s == $f) {
                return $r;
            }
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
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';

            return 'normalize-space(' . $node . ')=' . $s;
        }, $field)) . ')';
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) { return "contains(normalize-space(.), \"{$s}\")"; }, $field));
    }

    private function preg_implode($field)
    {
        if (!is_array($field)) {
            $field = [$field];
        }

        return '(?:' . implode("|", array_map(function ($v) {return preg_replace('/[ ]+/', '\s+', preg_quote($v, '#')); }, $field)) . ')';
    }

    private function rowColsPos(?string $row): array
    {
        $pos = [];
        $head = preg_split('/\s{2,}/', $row, -1, PREG_SPLIT_NO_EMPTY);
        $lastpos = 0;

        foreach ($head as $word) {
            $pos[] = mb_strpos($row, $word, $lastpos, 'UTF-8');
            $lastpos = mb_strpos($row, $word, $lastpos, 'UTF-8') + mb_strlen($word, 'UTF-8');
        }

        return $pos;
    }

    private function splitCols(?string $text, ?array $pos = null): array
    {
        $cols = [];

        if ($text === null) {
            return $cols;
        }
        $rows = explode("\n", $text);

        if ($pos === null || count($pos) === 0) {
            $pos = $this->rowColsPos($rows[0]);
        }
        arsort($pos);

        foreach ($rows as $row) {
            foreach ($pos as $k => $p) {
                $cols[$k][] = rtrim(mb_substr($row, $p, null, 'UTF-8'));
                $row = mb_substr($row, 0, $p, 'UTF-8');
            }
        }
        ksort($cols);

        foreach ($cols as &$col) {
            $col = implode("\n", $col);
        }

        return $cols;
    }

    private function splitText(?string $textSource, string $pattern, bool $saveDelimiter = false): array
    {
        $result = [];

        if ($saveDelimiter) {
            $textFragments = preg_split($pattern, $textSource, -1, PREG_SPLIT_DELIM_CAPTURE);
            array_shift($textFragments);

            for ($i = 0; $i < count($textFragments) - 1; $i += 2) {
                $result[] = $textFragments[$i] . $textFragments[$i + 1];
            }
        } else {
            $result = preg_split($pattern, $textSource);
            array_shift($result);
        }

        return $result;
    }

    private function inOneRow($text)
    {
        $textRows = array_filter(explode("\n", $text));

        if (empty($textRows)) {
            return '';
        }
        $length = [];

        foreach ($textRows as $row) {
            $length[] = mb_strlen($row);
        }
        $length = max($length);
        $oneRow = '';

        for ($l = 0; $l < $length; $l++) {
            $notspace = false;

            foreach ($textRows as $row) {
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

    private function htmlToText(?string $s, bool $brConvert = true): string
    {
        if (!is_string($s) || $s === '') {
            return '';
        }
        $s = str_replace("\r", '', $s);
        $s = preg_replace('/<!--.*?-->/s', '', $s); // comments

        if ($brConvert) {
            $s = preg_replace('/\s+/', ' ', $s);
            $s = preg_replace('/<[Bb][Rr]\b.*?\/?>/', "\n", $s); // only <br> tags
        }
        $s = preg_replace('/<[A-z][A-z\d:]*\b.*?\/?>/', '', $s); // opening tags
        $s = preg_replace('/<\/[A-z][A-z\d:]*\b[ ]*>/', '', $s); // closing tags
        $s = html_entity_decode($s);
        $s = str_replace(chr(194) . chr(160), ' ', $s); // NBSP to SPACE

        return trim($s);
    }

    private function normalizeTraveller(?string $s): ?string
    {
        if (empty($s)) {
            return null;
        }

        $namePrefixes = '(?:MSTR|MISS|MRS|MR|MS)';

        return preg_replace([
            "/^(.{2,}?)\s+{$namePrefixes}[.\s]*$/i",
            "/^{$namePrefixes}[.\s]+(.{2,})$/i",
        ], [
            '$1',
            '$1',
        ], $s);
    }
}
