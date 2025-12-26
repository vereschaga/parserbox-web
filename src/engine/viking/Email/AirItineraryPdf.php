<?php

namespace AwardWallet\Engine\viking\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class AirItineraryPdf extends \TAccountChecker
{
    public $mailFiles = "viking/it-148879890-oceania.eml, viking/it-153210529-oceania.eml, viking/it-177809857-oceania.eml, viking/it-20548342.eml, viking/it-213750628.eml, viking/it-21582261.eml, viking/it-43242805.eml, viking/it-564086959.eml, viking/it-805523164.eml";

    public static $dictionary = [
        "en" => [
            'Oceania Club Number' => ['Oceania Club Number', 'Seven Seas Society#'],
        ],
    ];

    private $detectFrom = '@vikingcruises.com';
    private $detectSubject = [
        'en'  => 'Air Itinerary for',
        'en2' => 'Viking Air Schedule',
    ];

    private $detectBody = [
        "en" => ['AIR ITINERARY', 'AIR SCHEDULE CHANGE NOTIFICATION', 'Booking Number:'],
    ];
    private $otaConfNumbers = [];
    private $parseAgencyDocument = false;
    private $parseGuestDocument = false;
    private $pdfPattern = '(?!EDOC).+\.pdf';
    private $pdfPatternExclude = 'EDOC.+\.pdf';
    private $lang = 'en';
    private $providerCode = '';
    private $patterns = [
        'time'           => '\d{2}[:：]\d{2}(?:[AP])?', // 06:40A
        'time2'          => '\d{1,2}[:：]\d{2}(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?)?', // 4:45 PM
        'travellerName'  => '[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]]', // Mr. Hao-Li Huang
        'travellerName2' => '[[:upper:]][-.\'’`[:upper:] ]*[[:upper:]]', // MRS JENNI WELLINGS
    ];

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfPattern);
        $pdfsExclude = $parser->searchAttachmentByName($this->pdfPatternExclude); // go to Parse EDocument.php
        $pdfs = array_diff($pdfs, $pdfsExclude);

        foreach ($pdfs as $pdf) {
            if (($text = \PDF::convertToText($parser->getAttachmentBody($pdf))) === null) {
                continue;
            }

            foreach ($this->detectBody as $lang => $detectBody) {
                foreach ($detectBody as $dBody) {
                    if (strpos($text, $dBody) !== false && $this->assignProvider($text, $parser->getHeaders())) {
                        $this->lang = $lang;
                        $this->parsePdf($email, $text);

                        continue 3;
                    }
                }
            }
        }
        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));
        $email->setProviderCode($this->providerCode);

        return $email;
    }

    public function parsePdf(Email $email, string $text): void
    {
        if (preg_match("/^[ ]*Agency Statement\n+[ ]*Booking Number[ ]*:/i", $text)) {
            $documentType = 'agency';
        } elseif (preg_match("/^[ ]*Guest Statement\n+[ ]*Booking Number[ ]*:/i", $text)) {
            $documentType = 'guest';
        } else {
            $documentType = 'other';
        }

        $otaConfirmation = $otaConfirmationTitle = null;

        if (preg_match("/^[ ]*(Booking Number)[ ]*:[ ]*(\d{5,})(?:[ ]{2}|\n)/im", $text, $m)) {
            $otaConfirmation = $m[2];
            $otaConfirmationTitle = $m[1];

            if (in_array($otaConfirmation, $this->otaConfNumbers)
                && ($documentType === 'guest' && $this->parseAgencyDocument
                    || $documentType === 'agency' && $this->parseGuestDocument
                )
            ) {
                // it-148879890-oceania.eml
                return;
            }
        }

        $travellers = $accounts = [];

        if (preg_match_all("/\b{$this->opt($this->t('Oceania Club Number'))}[ ]*:[ ]*([-A-Z\d]{5,}?)(?:[ ]{2}| Level[ ]*:|\n)/", $text, $accMatches)) {
            $accounts = $accMatches[1];
        }

        $paxInfo = $this->re("/\s+(.+\n\s+{$this->opt($this->t('Oceania Club Number'))}.+\n)/", $text);
        $paxTable = $this->splitCols($paxInfo);

        foreach ($paxTable as $paxColumn) {
            if (preg_match("/([[:alpha:]][-.\/\'’[:alpha:] ]*[[:alpha:]])\n\s*({$this->opt($this->t('Oceania Club Number'))})\:?\s*(\d{5,})/u", $paxColumn, $m)) {
                $email->ota()
                    ->account($m[3], false, preg_replace("/^(?:MRS|MR|MS)\s+/", "", $m[1]), $m[2]);
            }
        }

        ///////////////
        /// FLIGHTS ///
        ///////////////

        if (preg_match("/\n[ ]*Passenger #\d{1,3}[ ]*:/", $text) > 0) {
            $this->logger->debug('Found flight type-1.');
            $travellers = $this->parseFlights1($email, $text);
        } elseif (preg_match("/\n[ ]*Name[ ]{2,}Reservation Code\n/", $text) > 0) {
            $this->logger->debug('Found flight type-2.');
            $travellers = $this->parseFlights2($email, $text);
        } elseif (preg_match("/\n[ ]*Name(?:\s*eTicket Numbers)?\n+[ ]*{$this->patterns['travellerName']}(?:\s*\d{10,}\D\d{10,})?\n+[ ]*Day[ ]+Date[ ]+Flight/", $text) > 0) {
            $this->logger->debug('Found flight type-3.');
            $travellers = $this->parseFlights3($email, $text);
        }

        //////////////
        /// CRUISE ///
        //////////////

        if (preg_match("/Cruise Name/", $text) > 0 || preg_match("/Embarkation Date/", $text) > 0) {
            $c = $email->add()->cruise();

            // Details
            $c->details()
                ->description($this->re("#\s{2,}Cruise:[ ]*(.+)#",
                        $text) . ' ' . $this->re("#\s{2,}Cruise Name:[ ]*(.+)#", $text))
                ->room($this->re("#\s{2,}Suite/Stateroom:[ ]*(.+?)(?: {3,}|\n)#", $text))
                ->ship($this->re("#\s{2,}Ship:?[ ]*(.+)#", $text));

            $travelDate = strtotime($this->re("/\s+Embarkation Date[ ]*:[ ]*(.*?\d.*?)(?: {2,}.*|)\n/", $text));

            // Segments
            $itineraryText = preg_replace("/\n *Page \d+ of \d+ (?:.*\n){1,5}\s*Invoice Issue Date.+\n/",
                "\n" . "----newPage" . "\n", $text);

            if (preg_match("/\n[ ]*Date[ ]+Port Description[ ]+Arrive[ ]+Depart\b.*\n+(?<body>.+[\s\S]*?(?:\n+----newPage\n *[[:alpha:]]{3} \d{1,2} {2,}[\s\S]+?)*)\n\n/m",
                    $itineraryText, $matches) > 0
            ) {
                // it-148879890-oceania.eml

                preg_match_all("/\n[ ]*({$this->patterns['travellerName2']})\n+.*{$this->opt($this->t('Oceania Club Number'))}[ ]*:/u", $text,
                    $tRowMatches);

                foreach ($tRowMatches[1] as $tRow) {
                    $travellers = array_merge($travellers, preg_split('/[ ]{2,}/', $tRow));
                }

                $tables = [];
                $tablePages = preg_split("/\n+----newPage\n+/", $matches['body']);

                foreach ($tablePages as $i => $page) {
                    if (preg_match("/^( {0,10}[[:alpha:]]{3} \d{1,2} {2,}.+? {2})[[:alpha:]]{3} \d{1,2} {2,}/", $page, $m)) {
                        $table = $this->SplitCols($page, [0, strlen($m[1])]);
                    } else {
                        $email->add()->cruise();
                        $this->logger->debug('parsing segment error');
                    }

                    if (count($table) == 2) {
                        $tables['0' . $i] = $table[0];
                        $tables['1' . $i] = $table[1];
                    } else {
                        $email->add()->cruise();
                        $this->logger->debug('parsing segment error');
                    }
                }

                $segmentStatus = null;
                ksort($tables);

                foreach ($tables as $tableKey => $table) {
                    $table = preg_replace("/(\w+\s*\d+\s*\D+)\n\s+(\s[\d\:]+\s*A?P?M\s*[\d\:]+\s*A?P?M)/", "$1$2", $table);
                    $table = preg_replace("/^(\w+\s*\d+\s+.+)\n(\s+\d+\:\d+\s*A?P?M)$/m", "$1$2", $table);
                    $tableRows = explode("\n", trim($table));

                    //it-564086959.eml
                    $firsrtPort = $this->re("/\w+\s*\d+\s*(\D+\b)\s+[\d\:]+\s*A?P?M/", $tableRows[0]);
                    $secondPort = $this->re("/\w+\s*\d+\s*(\D+\b)\s+[\d\:]+\s*A?P?M/", $tableRows[1]);

                    foreach (array_filter($tableRows) as $key => $rowText) {
                        if ($firsrtPort === $secondPort && $tableKey == '00' && $key === 0) {
                            if (preg_match("/[\d\:]+\s*A?P?M\s*[\d\:]+\s*A?P?M$/", $rowText)) {
                                $rowText = preg_replace("/([\d\:]+\s*A?P?M)(\s*[\d\:]+\s*A?P?M$)/", "$2", $rowText);
                            } else {
                                continue;
                            }
                        }

                        if (!preg_match("/(?: *(?<time1>{$this->patterns['time2']}))?(?:[ ]+(?<time2>{$this->patterns['time2']}))?$/u", $rowText)) {
                            $newRowText = $this->re("/({$rowText}\n*\s+[\d\:]+\s*A?P?M\s*[\d\:]+\s*A?P?M)/", $table);
                            $rowText = str_replace("\n", " ", $newRowText);
                        }

                        if (preg_match("/^\w+\s+\d+\s+\D+\,\s*\D+$/", $rowText)) {
                            continue;
                        }

                        if (preg_match("/^[ ]*(?<date>[[:alpha:]]{3} \d{2})[ ]+(?<port>\S.*?\S)(?: *(?<time1>{$this->patterns['time2']}))?(?:[ ]+(?<time2>{$this->patterns['time2']}))?$/u",
                            $rowText, $m)
                        ) {
                            if (preg_match("/^(Cruising .+|Cross The International Date Line|Panama Canal Daylight Transit|Crossing The)/i", $m['port']) > 0
                            && !preg_match("/\s\d+\:\d+/", $rowText)) {
                                continue;
                            }

                            if ($segmentStatus === null
                                || isset($s) && $s->getName() !== $m['port']
                            ) {
                                if (!preg_match("/At Sea/", $m['port'])) {
                                    $s = $c->addSegment();
                                    $s->setName($m['port']);
                                }
                            }

                            $date = 0;

                            if ($travelDate) {
                                $date = EmailDateHelper::parseDateRelative($m['date'], $travelDate, true, '%D%, %Y%');
                            }

                            if (empty($date)) {
                                $this->logger->debug('Wrong date in cruise segment!');

                                continue;
                            } elseif (empty($m['time1'])) {
                                $this->logger->debug('Wrong time in cruise segment!');

                                continue;
                            }

                            if (empty($m['time2'])) {
                                if ($segmentStatus === null || $segmentStatus === 'Ashore') {
                                    $s->setAboard(strtotime($m['time1'], $date));
                                    $segmentStatus = 'Abord';
                                } elseif ($segmentStatus === 'Abord') {
                                    $s->setAshore(strtotime($m['time1'], $date));
                                    $segmentStatus = 'Ashore';
                                }
                            } else {
                                $s->setAshore(strtotime($m['time1'], $date));
                                $s->setAboard(strtotime($m['time2'], $date));
                                $segmentStatus = 'Abord';
                            }
                        } else {
                            $this->logger->debug('Wrong date in cruise segment!');
                            $c->addSegment(); // for 100% fail
                        }
                    }
                }
            } elseif (preg_match("/\s+Day\s+Date\s+Description\s+Port Arrival\s+Port\s+Depart/", $text, $matches)) {
                $travellers = (explode("&", $this->re("/Invoice Issue Date:.+\n+\s+(.+)\n+\s+Day\s+Date/", $text)));

                $textCruise = $this->re("/^([ ]+[A-Z]{3}\s+\w+\s*\d+\,\s*\d{4}\s+Embark In.+Disembark In\D{5,20}\s+\d+\:\d+\s*A?P?M[ ]{3,}\d+\:\d+\s*A?P?M)\n/msu", $text);
                $segmentsRows = explode("\n", $textCruise);

                foreach ($segmentsRows as $rowText) {
                    if (preg_match("/^(Cruising .+|Cross The International Date Line|Panama Canal Daylight Transit|Crossing The|Penthouse Veranda)/i", $rowText)
                        && !preg_match("/\d+\:\d+/", $rowText)) {
                        continue;
                    }

                    if (preg_match("/[ ]{5,}(?<date>\w+\s*\d+\,\s*\d{4})[ ]{5,}Embark In\s+(?<port>.+)\b[ ]{2,}(?<timeStart>[\d\:]+\s*A?P?M)/u", $rowText, $m)) {
                        $s = $c->addSegment();
                        $s->setName(str_replace(' on Viking Vela', '', $m['port']));
                        $s->setAboard($this->normalizeDate($m['date'] . ', ' . $m['timeStart']));
                    } elseif (preg_match("/[ ]{5,}(?<date>\w+\s*\d+\,\s*\d{4})[ ]{5,}Disembark In\s+(?<port>.+)\b[ ]{2,}(?<timeEnd>[\d\:]+\s*A?P?M)\s+/u", $rowText, $m)) {
                        $s = $c->addSegment();
                        $s->setName($m['port']);
                        $s->setAshore($this->normalizeDate($m['date'] . ', ' . $m['timeEnd']));
                    } elseif (preg_match("/[ ]{5,}(?<date>\w+\s*\d+\,\s*\d{4})[ ]{5,}\s+(?<port>.+)\b[ ]{2,}(?<timeStart>[\d\:]+\s*A?P?M)\s+(?<timeEnd>[\d\:]+\s*A?P?M)/u", $rowText, $m)) {
                        $s = $c->addSegment();
                        $s->setName($m['port']);
                        $s->setAshore($this->normalizeDate($m['date'] . ', ' . $m['timeStart']));
                        $s->setAboard($this->normalizeDate($m['date'] . ', ' . $m['timeEnd']));
                    }
                }
            } else {
                $c->addSegment()
                        ->setName($this->re("#\s{2,}Embarkation Date:[ ]*\S+?[ ]+(.+)#", $text))
                        ->setAboard($this->normalizeDate($this->re("#\s{2,}Embarkation Date:[ ]*(\S+?)[ ]+.+#",
                            $text)));
                $c->addSegment()
                        ->setName($this->re("#\s{2,}Disembarkation Date:[ ]*\S+?[ ]+(.+)#", $text))
                        ->setAshore($this->normalizeDate($this->re("#\s{2,}Disembarkation Date:[ ]*(\S+?)[ ]+.+#",
                            $text)));
            }

            // General
            $c->general()
                    ->noConfirmation()
                    ->travellers(preg_replace("/^(?:MRS|MR|MS)\s+/", "", $travellers), true);
//            }
        }

        // Travel Agency
        $email->ota()->confirmation($otaConfirmation, $otaConfirmationTitle);

        if ($otaConfirmation) {
            $this->otaConfNumbers[] = $otaConfirmation;
        }

        if (preg_match("/\n[ ]*Viking Cruises .* • ([+(\d][-+. \d)(]{5,}[\d)]) • www.vikingcruises.com/i", $text, $m)) {
            $email->ota()->phone($m[1]);
        }

        /*if (count($accounts) > 0) {
            $email->ota()->accounts($accounts, false);
        }*/

        // Price
        if (preg_match("/\s+Gross\b.*\s*\n[ ]*Grand Total {10,}\\$ ?(\d[\d,. ]*)(?:\s{2,}|\n)/", $text, $m)) {
            $email->price()
                ->total(PriceHelper::parse(trim($m[1]), 'USD'))
                ->currency('USD')
            ;
        }

        if ($documentType === 'agency') {
            $this->parseAgencyDocument = true;
        } elseif ($documentType === 'guest') {
            $this->parseGuestDocument = true;
        }
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->detectFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (stripos($headers['from'], $this->detectFrom) === false) {
            return false;
        }

        foreach ($this->detectSubject as $subject) {
            if (stripos($headers['subject'], $subject) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfPattern);
        $pdfsExclude = $parser->searchAttachmentByName($this->pdfPatternExclude); // go to Parse EDocument.php
        $pdfs = array_diff($pdfs, $pdfsExclude);

        foreach ($pdfs as $pdf) {
            $textPdf = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if ($this->assignProvider($textPdf, $parser->getHeaders()) === false) {
                continue;
            }

            foreach ($this->detectBody as $detectBody) {
                foreach ($detectBody as $dBody) {
                    if (stripos($textPdf, $dBody) !== false) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    public static function getEmailProviders()
    {
        return ['oceania', 'viking', 'regentcruises'];
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
    }

    private function parseFlights1(Email $email, string $text): ?array
    {
        $this->logger->debug(__METHOD__);
        // it-20548342.eml, it-21582261.eml, it-43242805.eml

        $passengers = $this->res("/\n[ ]*Passenger #\d{1,3}[ ]*:[ ]*({$this->patterns['travellerName']})\n/u", $text);

        // TODO: add collecting all `Viking Air #` with grouping flights by PNR (example: it-43242805.eml)

        $f = $email->add()->flight();

        // General
        $f->general()
            ->confirmation($this->re("#Viking Air \#:[ ]*([A-Z\d]{5,})\s+#", $text), "Viking Air #")
            ->travellers(preg_replace("/^(?:MRS|MR|MS)\s+/", "", $passengers), true);

        // Issued
        if (preg_match_all("#Airline Ticket \#:[ ]*(\d{10,})[ ]+(.+)#", $text, $ticketMatches)) {
            $f->issued()->tickets($ticketMatches[1], false);

            if (count(array_unique($ticketMatches[2]))) {
                $f->issued()->name($ticketMatches[2][0]);
            }
        }

        // Segments
        $segments = [];
        $passengerItineraries = $this->split("#\n\s*Passenger \#\d+:[ ]*(.+)#", $text);

        foreach ($passengerItineraries as $value) {
            $segments = array_merge($segments, $this->split("#\n\s*(Flight \d+ .+\n\s*Flight\s*Departs)#", $value));
        }

        foreach ($segments as $stext) {
            $stext = preg_replace("#(Airline Booking \#:.+)[\s\S]*#", '$1', $stext);

            if (preg_match("/[A-z][ ]{1,3}Seat\:/", $stext)) {
                $stext = preg_replace("/([A-z][ ]{1,3})(Seat\:)/", "$1           $2", $stext);
            }

            $s = $f->addSegment();

            if (preg_match("#Flight \d+ (.+)\s*\n([ ]*\S[\s\S]+)#", $stext, $m)) {
                $s->extra()
                    ->cabin($m[1]);
                $tableText = $m[2];
            }

            if (empty($tableText)) {
                $this->logger->debug("Bad segment $stext!");

                return null;
            }

            $table = $this->splitCols($tableText);

            if (count($table) !== 3) {
                $this->logger->debug("Error in parsing table $tableText!");

                return null;
            }

            // Airline
            if (preg_match("#Flight\s+(.+)\s+Flight\s+(\d+)\b#s", $table[0], $m)) {
                $s->airline()
                    ->name(preg_replace("#\s+#", ' ', $m[1]))
                    ->number($m[2]);
            }

            if (preg_match("#Operated By\s*(/)?([\s\S]+?)(?:\s+OR\s+[\s\S]*)?$#i", $table[0], $m)) {
                $s->airline()
                    ->operator(preg_replace("#\s+#", ' ', trim($m[2])));

                if (!empty($m[1])) {
                    $s->airline()
                        ->wetlease();
                }
            }
            $s->airline()
                ->confirmation($this->re("#Airline Booking \#:[ ]*([A-Z\d]{5,7})\b#", $table[1]), "Airline Booking");

            // Departure
            if (preg_match("#Departs\s+(?<name>[\s\S]+?)\((?<code>[A-Z]{3})\)\s+(?<date>.+)#", $table[1], $m)) {
                $s->departure()
                    ->code($m['code'])
                    ->name(preg_replace("#\s+#", ' ', $m['name']))
                    ->date($this->normalizeDate($m['date']));
            }

            // Arrival
            if (preg_match("#Arrives\s+(?<name>[\s\S]+?)\((?<code>[A-Z]{3})\)\s+(?<date>.+)#", $table[2], $m)) {
                $s->arrival()
                    ->code($m['code'])
                    ->name(preg_replace("#\s+#", ' ', $m['name']))
                    ->date($this->normalizeDate($m['date']));
            }

            // Extra
            $duration = $this->re("#(?:Travel )?Time:[ ]*(.+)#", $table[1]);

            if (!empty($duration)) {
                $s->extra()
                    ->duration($duration);
            }

            $aircraft = $this->re("/Equipment:[ ]*(.+)/", $table[2]);

            if (!empty(trim($aircraft))) {
                $s->extra()
                    ->aircraft($aircraft);
            }

            $seat = $this->re("#Seat:[ ]*(\d{1,3}[A-Z])\b#", $table[1]);

            if (!empty($seat)) {
                $s->extra()
                    ->seat($seat);
            }

            $count = count($f->getSegments());

            foreach ($f->getSegments() as $key => $seg) {
                if ($key == $count - 1) {
                    continue;
                }

                if ($s->getAirlineName() == $seg->getAirlineName()
                        && $s->getFlightNumber() == $seg->getFlightNumber()
                        && $s->getDepName() == $seg->getDepName()
                        && $s->getDepDate() == $seg->getDepDate()) {
                    if (!empty($s->getSeats())) {
                        $seg->extra()->seats(array_unique(array_merge($seg->getSeats(), $s->getSeats())));
                    }
                    $f->removeSegment($s);
                }
            }
        }

        return $passengers;
    }

    private function parseFlights2(Email $email, string $text): ?array
    {
        $this->logger->debug(__METHOD__);
        // it-153210529-oceania.eml

        $passengersAll = $flightsByPnr = [];

        $flightTexts = $this->split("/\n([ ]*Name[ ]{2,}Reservation Code\n)/", $text);

        foreach ($flightTexts as $fText) {
            if (preg_match("/^[ ]*Name[ ]{2,}Reservation Code\n+[ ]*(?<name>{$this->patterns['travellerName']})[ ]{2,}(?<pnr>[A-Z\d]{5,7})\n/u", $fText, $m)) {
                $passenger = $m['name'];
                $pnr = $m['pnr'];
            } else {
                $this->logger->debug('Flight is wrong!');

                return null;
            }

            if (array_key_exists($pnr, $flightsByPnr)) {
                $flightsByPnr[$pnr][] = $fText;
            } else {
                $flightsByPnr[$pnr] = [$fText];
            }

            $passengersAll[] = $passenger;
        }

        foreach ($flightsByPnr as $pnr => $fTexts) {
            $f = $email->add()->flight();

            if (preg_match("/^[ ]*Name[ ]{2,}(Reservation Code)\n+[ ]*{$this->patterns['travellerName']}[ ]{2,}({$pnr})\n/u", $fTexts[0], $m)) {
                $f->general()->confirmation($m[2], $m[1]);
            }

            foreach ($fTexts as $fText) {
                $f->general()->traveller(preg_replace("/^(?:MRS|MR|MS)\s+/", "", $this->re("/^[ ]*Name[ ]{2,}Reservation Code\n+[ ]*({$this->patterns['travellerName']})[ ]{2,}[A-Z\d]{5,7}\n/u", $fText)), true);

                $segments = $this->split("/^([ ]*[A-Z]{3}[ ]+\d{2}[A-Z]{3}\d{2}[ ]+.{5,7}[ ]+(?:[A-Z][A-Z\d]|[A-Z\d][A-Z]) \d+[ ]+\S.+\S[ ]+{$this->patterns['time']})/m", $fText);

                foreach ($segments as $sText) {
                    $pattern = "/^[ ]*[A-Z]{3}[ ]+(?<dateDep>\d{2}[A-Z]{3}\d{2})[ ]+(?<refNo>.{5,7})[ ]+(?<airline>[A-Z][A-Z\d]|[A-Z\d][A-Z]) (?<fNum>\d+)[ ]+(?<nameDep>\S.+\S)[ ]+(?<timeDep>{$this->patterns['time']})(?:[ ]{8,}(?<operator>\S.*\S))?\n+"
                        . "[ ]*[A-Z]{3}[ ]+(?<dateArr>\d{2}[A-Z]{3}\d{2})[ ]{12,}(?<nameArr>\S.+\S)[ ]+(?<timeArr>{$this->patterns['time']})(?:\n|$)"
                    . "/";

                    if (preg_match($pattern, $sText, $m)) {
                        $dateDep = strtotime($m['dateDep'] . ' ' . $this->normalizeTime($m['timeDep']));
                        $dateArr = strtotime($m['dateArr'] . ' ' . $this->normalizeTime($m['timeArr']));

                        if (empty($dateDep) || empty($dateArr)) {
                            $this->logger->debug('Wrong date in flight segment!');
                            $f->addSegment(); // for 100% fail

                            continue;
                        }

                        foreach ($f->getSegments() as $seg) {
                            if ($seg->getAirlineName() === $m['airline']
                                && $seg->getFlightNumber() === $m['fNum']
                                && $seg->getDepName() === $m['nameDep']
                                && $seg->getDepDate() === $dateDep
                            ) {
                                continue 2;
                            }
                        }

                        $s = $f->addSegment();
                        $s->airline()->name($m['airline'])->number($m['fNum']);

                        if (!empty($m['refNo']) && preg_match('/^[ ]*([-A-Z\d]{5,})[ ]*$/', $m['refNo'], $m2) > 0) {
                            $s->airline()->confirmation($m2[1]);
                        }
                        $s->departure()
                            ->date($dateDep)
                            ->name($m['nameDep'])
                            ->noCode()
                        ;
                        $s->arrival()
                            ->date($dateArr)
                            ->name($m['nameArr'])
                            ->noCode()
                        ;

                        if (!empty($m['operator'])) {
                            $s->airline()->operator($m['operator']);
                        }
                    } else {
                        $this->logger->debug('Wrong flight segment!');
                        $f->addSegment(); // for 100% fail
                    }
                }
            }
        }

        return $passengersAll;
    }

    private function parseFlights3(Email $email, string $text): ?array
    {
        $this->logger->debug(__METHOD__);
        // it-177809857-oceania.eml

        $passengersAll = [];

        $flightTexts = $this->split("/\n([ ]*Name(?:\s*eTicket Numbers)?\n+[ ]*{$this->patterns['travellerName']}(?:\s*\d{10,}\D\d{10,})?\n+[ ]*Day[ ]+Date[ ]+Flight)/", $text);

        foreach ($flightTexts as $fText) {
            if (preg_match("/^[ ]*Name(?:\s*eTicket Numbers)?\n+[ ]*(?<name>{$this->patterns['travellerName']})(?:\s*(?<ticket>\d{10,}\D\d{10,}))?\n+[ ]*Day[ ]+Date[ ]+Flight/", $fText, $m)) {
                $passenger = $m['name'];

                if (isset($m['ticket']) && !empty($m['ticket'])) {
                    $ticket = $m['ticket'];
                }
            } else {
                $this->logger->debug('Flight is wrong!');

                return null;
            }

            $f = $email->add()->flight();
            $f->general()->noConfirmation();
            $f->general()->traveller(preg_replace("/^(?:MRS|MR|MS)\s+/", "", $passenger), true);

            if (!empty($ticket)) {
                $f->addTicketNumber($ticket, false);
            }

            $segments = $this->split("/^([ ]*[A-Z]{3}[ ]+\d{2}[A-Z]{3}\d{2}[ ]+(?:[A-Z][A-Z\d]|[A-Z\d][A-Z]) \d+[ ]+\S.+\S[ ]+{$this->patterns['time']}(?: .+|\n))/m", $fText);

            foreach ($segments as $sText) {
                $pattern = "/^[ ]*[A-Z]{3}[ ]+(?<dateDep>\d{2}[A-Z]{3}\d{2})[ ]+(?<airline>[A-Z][A-Z\d]|[A-Z\d][A-Z]) (?<fNum>\d+)[ ]+(?<nameDep>\S.+\S)[ ]+(?<timeDep>{$this->patterns['time']})(?:[ ]{1,16}(?<refNo>[A-Z\d]{5,7})(?:[ ]{2,}\S.*\S)?)?"
                    . "(?:\n.*){0,2}"
                    . "\n[ ]*[A-Z]{3}[ ]+(?<dateArr>\d{2}[A-Z]{3}\d{2})[ ]{6,}(?<nameArr>\S.+\S)[ ]{6,}(?<timeArr>{$this->patterns['time']})(?:\n|$)"
                . "/";

                if (preg_match($pattern, $sText, $m)) {
                    $dateDep = strtotime($m['dateDep'] . ' ' . $this->normalizeTime($m['timeDep']));
                    $dateArr = strtotime($m['dateArr'] . ' ' . $this->normalizeTime($m['timeArr']));

                    if (empty($dateDep) || empty($dateArr)) {
                        $this->logger->debug('Wrong date in flight segment!');
                        $f->addSegment(); // for 100% fail

                        continue;
                    }

                    $s = $f->addSegment();

                    $s->airline()->name($m['airline'])->number($m['fNum']);

                    if (!empty($m['refNo']) && preg_match('/^[ ]*([-A-Z\d]{5,})[ ]*$/', $m['refNo'], $m2) > 0) {
                        $s->airline()->confirmation($m2[1]);
                    }
                    $s->departure()
                        ->date($dateDep)
                        ->name($m['nameDep'])
                        ->noCode()
                    ;
                    $s->arrival()
                        ->date($dateArr)
                        ->name($m['nameArr'])
                        ->noCode()
                    ;
                } else {
                    $this->logger->debug('Wrong flight segment!');
                    $f->addSegment(); // for 100% fail
                }
            }

            $passengersAll[] = $passenger;
        }

        return $passengersAll;
    }

    private function assignProvider($text, $headers): bool
    {
        //$this->logger->debug($text);

        if (strpos($headers['subject'], 'Oceania') !== false
            || stripos($text, 'Oceania Cruises, Inc') !== false
            || stripos($text, 'www.oceaniacruises.com') !== false
            || stripos($text, 'Oceania Club Number') !== false
        ) {
            $this->providerCode = 'oceania';

            return true;
        }

        if (strpos($headers['subject'], 'Regent Seven Seas Cruises') !== false
            || stripos($text, 'Regent Seven Seas Cruises') !== false
        ) {
            $this->providerCode = 'regentcruises';

            return true;
        }

        if (self::detectEmailFromProvider($headers['from']) === true
            || stripos($text, 'Viking Cruises') !== false
            || stripos($text, 'vikingcruises') !== false
            || stripos($text, 'viking.com') !== false
            || stripos($text, 'vikingrivercruises.com') !== false
        ) {
            $this->providerCode = 'viking';

            return true;
        }

        return false;
    }

    private function normalizeDate($str)
    {
        //$this->logger->warning($str);
        $in = [
            "#^\s*(\d{1,2})\-([^\d\s]+)\-(\d{2})\s*$#", // 21-Oct-18
        ];
        $out = [
            "$1 $2 20$3",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return strtotime($str);
    }

    private function normalizeTime(?string $s): string
    {
        $s = preg_replace('/^(\d{2}:\d{2})([AP])$/', '$1 $2M', $s); // 09:05A    ->    09:05 AM

        return $s;
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function res($re, $str, $c = 1): ?array
    {
        if (preg_match_all($re, $str, $m) && array_key_exists($c, $m)) {
            return $m[$c];
        }

        return null;
    }

    private function split($re, $text): array
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

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
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
