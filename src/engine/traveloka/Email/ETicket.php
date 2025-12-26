<?php

namespace AwardWallet\Engine\traveloka\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Engine\WeekTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class ETicket extends \TAccountChecker
{
    public $mailFiles = "traveloka/it-26518501.eml, traveloka/it-27142031.eml, traveloka/it-27190713.eml, traveloka/it-27565841.eml, traveloka/it-32992558.eml, traveloka/it-33346348.eml, traveloka/it-33586852-vi.eml, traveloka/it-39944061.eml, traveloka/it-51554867.eml, traveloka/it-69065927-vi.eml, traveloka/it-73636011.eml, traveloka/it-125778433-id.eml";

    public $reFrom = ["booking@traveloka.com"];
    public $reBody = [
        'vi' => ['Vé điện tử', 'Mã đặt vé'],
        'id' => ['E-tiket', 'Kode Booking Maskapai'],
        'en' => ['E-ticket', 'Airline Booking Code'], // last
    ];
    public $reSubject = [
        'en' => '#\[Traveloka\] Your .+? E-ticket - Booking ID \d+#',
        'id' => '#\[Traveloka\] E-tiket .+? Anda - No. Pesanan \d+#',
    ];
    public $lang = 'en';
    public $subject;
    private $isEnglish = false;

    public $pdfNamePattern = ".*.df";
    public static $dict = [
        'en' => [
            // Html
            //            'Traveloka Booking ID' => '',
            //            'Need Help?' => '',
            //            'Your flight reservation has been successfully' => '',
            'Passenger(s):' => ['Passenger(s):', 'PASSENGER(S):'],
            //            'Airline Booking Code (PNR):' => '',
            //            'direct' => '',
            //            'transit' => '',
            //            'Operated by' => '',

            // Pdf
            'detectFormat'               => ['Show e-ticket in your Traveloka App or mobile web at check-in', 'No Need to Print'],
            'seatsStart'                 => ['Facilities (Baggage, seat)', 'Flight Facilities'],
            'seatsEnd'                   => ['Traveloka COVID-19', 'Passenger Details', 'Customer Service'],
            'endInfoBlock'               => ['Check-in at least', 'Present e-ticket and'],
            'Airline Booking Code (PNR)' => ['Airline Booking Code (PNR)', 'Airline Booking Code', 'PNR'],
            //            'Return Flight' => '',
            'sSeparator' => ['Transit', 'Stop and check in', 'Stop to change'],
            // 'Passenger Details' => '',
            // 'Passenger(s)' => '',
            // 'Route' => '',
            // 'Ticket Number' => '',

            'RECEIPT'     => 'RECEIPT',
            'P.O. NUMBER' => 'P.O. NUMBER',
            //            'Flight Ticket' => '',
            //            'Total' => '',
            //            'TOTAL' => '',
        ],
        'vi' => [
            // Html
            //            'Your flight reservation has been successfully' => '',
            'Airline Booking Code (PNR):' => 'Mã đặt vé (PNR):',
            'direct'                      => 'Bay thẳng',
            'transit'                     => 'điểm dừng',
            'Operated by'                 => 'Khai thác bởi',
            'Passenger(s):'               => 'Tên hành khách:',
            'Need Help?'                  => 'Quý khách cần hỗ trợ?',
            'Traveloka Booking ID'        => 'Mã đặt chỗ Traveloka',

            // Pdf
            'detectFormat' => ['Vui lòng xuất trình vé điện tử tại quầy thủ tục bằng cách truy', 'Không cần in'],
            // 'seatsStart' => '',
            // 'seatsEnd' => '',
            'endInfoBlock' => ['Trình CMND/hộ chiếu và', 'Xuất trình hộ chiếu và vé', 'Làm thủ tục ít nhất'],
            // 'Airline Booking Code (PNR)' => '',
            //            'Return Flight' => '',
            'sSeparator' => 'Quá cảnh',
            'Passenger Details' => 'Thông tin hành khách',
            'Passenger(s)' => 'Tên hành khách',
            'Route' => 'Chặng',
            'Ticket Number' => 'Số vé',

            'RECEIPT'       => 'BIÊN NHẬN THANH TOÁN',
            'P.O. NUMBER'   => 'Mã đặt chỗ',
            'Flight Ticket' => 'Vé máy bay',
            'Total'         => 'Tổng cộng',
            'TOTAL'         => 'TỔNG CỘNG',
        ],
        'id' => [
            // Html
            //            'Traveloka Booking ID' => '',
            //            'Need Help?' => '',
            //            'Your flight reservation has been successfully' => '',
            //            'Passenger(s):' => '',
            //            'Airline Booking Code (PNR):' => '',
            //            'direct' => '',
            //            'transit' => '',
            //            'Operated by' => '',

            // Pdf
            'detectFormat' => ['Tunjukkan e-tiket dan', 'Tidak Perlu Dicetak'],
            'seatsStart'   => 'Fasilitas Penerbangan',
            'seatsEnd'     => ['Tiket sudah termasuk', 'Detail Penumpang'],
            'endInfoBlock' => ['Tunjukkan e-tiket dan', 'Check-in paling lambat'],
            // 'Airline Booking Code (PNR)' => '',
            //            'Return Flight' => '',
            'sSeparator' => ['Berhenti untuk ganti pesawat di', 'Berhenti dan check-in ulang di'],
            'Passenger Details' => 'Detail Penumpang',
            'Passenger(s)' => 'Nama Penumpang',
            'Route' => 'Rute',
            'Ticket Number' => 'Nomor Tiket',

            'RECEIPT'     => 'BUKTI PEMBELIAN',
            'P.O. NUMBER' => 'P.O. NUMBER',
            //            'Flight Ticket' => '',
            'Total' => 'Total',
            'TOTAL' => 'TOTAL',
        ],
    ];

    private $patterns = [
        'time' => '\d{1,2}[:：]\d{2}(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?)?', // 4:19PM  |  2:00 p. m.
        'travellerName' => '[[:alpha:]][-.\'’[:alpha:]\s]*[[:alpha:]]', // Mr. Hao-Li Huang
    ];

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->subject = $parser->getSubject();
        $parsed = false;
        $email->obtainTravelAgency();

        $type = 'Pdf';
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        if (isset($pdfs) && count($pdfs) > 0) {
            foreach ($pdfs as $i => $pdf) {
                $receiptDetect = [];

                foreach (self::$dict as $lang => $d) {
                    $receiptDetect[$lang] = array_filter([$d["P.O. NUMBER"] ?? null, $d["RECEIPT"] ?? null]);
                }

                if (($text = \PDF::convertToText($parser->getAttachmentBody($pdf))) !== null) {
                    if ($this->assignLang($text, $this->reBody)) {
                        $result = $this->parsePdf($text, $email);

                        if ($result === null) {
                            $this->logger->debug("can't parse attach-" . $i);

                            continue;
                        }

                        if ($result === false) {
                            // reset data
                            $email->clearItineraries();
                            $email->removeTravelAgency();
                            $email->obtainTravelAgency();
                        } else {
                            $parsed = true;
                        }
                    } elseif (
                        (!empty($this->lang) && stripos($text, 'P.O. NUMBER') !== false && stripos($text, 'RECEIPT') !== false)
                        || $this->assignLang($text, $receiptDetect)
                    ) {
                        $receiptText = $text;
                    } else {
                        $this->logger->debug('can\'t determine a language by attach-' . $i);
                    }
                }
            }
        }

        if (!$parsed) {
            if (!$this->assignLang($this->http->Response['body'], $this->reBody)) {
                $this->logger->debug('can\'t determine a language by Body');
            } else {
                $this->parseHtml($email);
                $type = 'Html';
            }
        }

        if (!empty($receiptText)) {
            $confs = $email->getTravelAgency()->getConfirmationNumbers();

            if (!empty($confs) && preg_match("#" . $this->opt($this->t('P.O. NUMBER')) . "[ ]*:[ ]*" . $this->opt(array_column($confs, 0)) . "\b#", $receiptText)) {
                $total = $this->amount($this->re("#\n[ ]{30,}" . $this->opt($this->t("TOTAL")) . "[ ]*(\d[\d\., ]*)\n#", $receiptText))
                    ?? $this->amount(str_replace('.', '', end(preg_split('/\s{2,}/', $this->re('/' . $this->opt($this->t('Flight Ticket')) . '.+\s+\d+\.\d{3}\s+(\d+\.\d{3})/i', $receiptText)))))
                ;

                if ($total !== null) {
                    $email->price()
                        ->total($total)
                        ->currency($this->currency($this->re("#\n[ ]*\S+.*[ ]{3,}" . $this->opt($this->t("Total")) . "[ ]*([^\d\s]{1,5})\n#", $receiptText)));
                }
            }
        }

        $a = explode('\\', __CLASS__);
        $class = end($a);
        $email->setType($class . $type . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//a[contains(@href, 'traveloka.com')]")->length > 0) {
            if ($this->assignLang($this->http->Response['body'], $this->reBody)) {
                return true;
            }
        }
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if ((stripos($text, 'traveloka') !== false)
                && $this->assignLang($text, $this->reBody)
            ) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && isset($headers["subject"])) {
            $flag = false;

            foreach ($this->reFrom as $reFrom) {
                if (stripos($headers['from'], $reFrom) !== false) {
                    $flag = true;
                }
            }

            foreach ($this->reSubject as $reSubject) {
                if (preg_match($reSubject, $headers["subject"]) && ($flag || stripos($reSubject,
                            'traveloka') !== false)
                ) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        foreach ($this->reFrom as $reFrom) {
            if (stripos($from, $reFrom) !== false) {
                return true;
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
        $formats = 2; // html | pdf
        $cnt = $formats * count(self::$dict);

        return $cnt;
    }

    /**
     * @deprecated
     */
    private function parsePdfPassengers($textPDF, array &$travellers, array &$tickets): bool
    {
        $paxBlock = strstr($textPDF, 'Passenger Details');

        if (preg_match("/^(.+?)\n+[ ]*(?:Airline Conditions of Carriage|Special COVID-19|Traveloka COVID-19|Included Benefits)/s", $paxBlock, $m)) {
            $paxBlock = $m[1];
        }

        if (strpos($paxBlock, 'Ticket Number') !== false) {
            $this->logger->debug('Passenger table: type 1');
            $tableText = $this->re("#Ticket Number *" . ((!$this->isEnglish) ? "(?:\n[^\n]+)" : "") . "\n(.+)#s", $paxBlock);

            // Mrs. NOVITA HENDRINA Makassar - Palu    ->    Mrs. NOVITA HENDRINA  Makassar - Palu
            $table = preg_replace('/([A-Z][-.\'A-Z ]*[A-Z])[ ]([A-Z][a-z])/', '$1  $2', $tableText);
            $table = $this->splitCols($table, $this->colsPos($table));

            if (count($table) !== 4) {
                $tableText = preg_replace("/^([ ]*No. Nama Penumpang.+\n+\s*)/m", "", $paxBlock);
                $tableText = preg_replace("/(Passenger Details\nDetail Penumpang\n*)/m", "", $tableText);
                $rowForDetectPosition = $this->re("/(No. Passenger(s).+)/", $tableText);
                $tableText = preg_replace("/(No. Passenger\(s\).+\n)/", "", $tableText);
                $table = $this->splitCols($tableText, $this->colsPos($rowForDetectPosition));
            }

            if (count($table) !== 4) {
                $tableText = preg_replace('/(\s*\(Adult\)\n\s*[*].*\n.+\n*\s*\d+\s*KG.*\n?)/', '', $tableText);
                $tableText = preg_replace('/(\n*Passenger Details\n*\s*\n)/', '', $tableText);
                $table = $this->splitCols($tableText);
            }

            if (count($table) !== 4) {
                // it-51554867.eml
                // Ms. NUR SYAFIQAH BINTI IDZHARSingapore - Kuala Lumpur    ->    Ms. NUR SYAFIQAH BINTI IDZHAR  Singapore - Kuala Lumpur
                $table = preg_replace('/([A-Z][-.\'A-Z ]*[A-Z])([A-Z][a-z])/', '$1  $2', $tableText);
                $table = $this->splitCols($table, $this->colsPos($table));

                if (count($table) !== 4) {
                    $this->logger->debug('other format pax-table');

                    return false;
                }
            }

            // Names in 2 or more rows
            /*
                1     Ông ROBBINS JR DAVID                 Bali / Denpasar - Kuala Lumpur                  8162117125562
                        BEAUDIN
                2     Bà THI HUONG PHAM                    Bali / Denpasar - Kuala Lumpur                  8162117125563
            */

            $t0 = explode("\n", $table[0]);
            $t1 = explode("\n", $table[1]);

            foreach ($t1 as $i => $row) {
                $t1[$i] = str_pad(array_key_exists($i, $t0) ? $t0[$i] : '', 7) .  $t1[$i];
            }
            $table[1] = implode("\n", $t1);

            //FE: some bad emails contains (has join columns)
            /*
                No. Passenger(s)                        Route                                       Ticket Number
                1     Ms. NOVITA HENDRINA LENAHATU Medan - Jakarta                                  1262106590051
                                                        Jakarta - Makassar                          1262106590051
            */
            $travellers = array_filter(preg_split("/(?:^|\s*\n)[ ]{0,10}\d{1,2}[ ]+/", $table[1]));

            $travellers = array_filter(array_map(function ($s) {
                return trim(preg_replace("/(\w+\.?.+?)(?:\s+[A-Z][a-z]|$)/su", '$1', trim($s)));
            }, $travellers));

            //. Mr. NIWIT SUWAN (Adult)CEI - DMK
            $travellers = array_filter(array_map(function ($s) {
                return trim(preg_replace("/^\.\s*\w+\.*\s*([A-Z\s]{3,})\s*(?:\(|\-).+/su", '$1', trim($s)));
            }, $travellers));

            $travellers = array_map(function ($item) {
                return $this->normalizeTraveller(preg_replace('/\s+/', ' ', $item));
            }, $travellers);
            $tickets = array_unique(array_filter(array_map("trim", explode("\n", $table[3]))));
        } else {
            $table = $this->re("#(?:Route|Flight Facilities) *" . ((!$this->isEnglish) ? "(?:\n[^\n]+)" : "") . "\n(.+)#s", $paxBlock);
            $table = preg_replace([
                "/(.+[*]Dimensi.+\n.*\n.*\d+\s*KG.*)/",
                "/(.+KrisFlyer for.+\n.*\d+\s*KG.*)/",
            ], '', $table);
            $table = preg_replace([
                "/^([ ]{0,10}\d{1,2})([ ]*)\.([ ]*)([[:alpha:]])/mu",
                "/^([ ]{0,10}\d{1,2})([ ]{1,10}) ([[:alpha:]])/mu",
            ], [
                '$2$3$1 $4', // 1 . Ms. AMALITA TIDAYOH    ->    1 Ms. AMALITA TIDAYOH
                '$2$1 $3',   // 1   Ms. AMALITA TIDAYOH    ->    1 Ms. AMALITA TIDAYOH
            ], $table);
            
            $table = $this->splitCols($table, $this->colsPos($table));

            if (count($table) !== 2 && !preg_match("/[A-Z]{3}[-\s]*[A-Z]{3}/", $table[1])) {
                $this->logger->debug('other format pax-table');

                return false;
            }

            $travellerRows = $this->splitText($table[0], "/^[ ]*\d{1,2}[. ]+({$this->patterns['travellerName']}(?:\s*\(|$))/mu", true);

            foreach ($travellerRows as $tRow) {
                if (preg_match("/^({$this->patterns['travellerName']})\s*(?:\(|$)/u", $tRow, $m)) {
                    $travellers[] = $this->normalizeTraveller(preg_replace('/\s+/', ' ', $m[1]));
                } else {
                    $travellers = [];

                    break;
                }
            }
        }
        $tickets = array_values($tickets);

        return true;
    }

    /**
     * @return bool|null If parsed then true, if parsed failed then false, if can't determine format then null
     */
    private function parsePdf($textPDF, Email $email): ?bool
    {
        $this->logger->debug(__FUNCTION__);
        //check format
        if ((strpos($textPDF, 'Traveloka Booking ID') == false
            || $this->mb_strposAll($textPDF, $this->t('detectFormat')) == false)
                && !preg_match("#^(.*\n){0,3}\s*Return Flight#", $textPDF)) {
            return null;
        }

        if (is_array($this->t('endInfoBlock'))) {
            foreach ($this->t('endInfoBlock') as $end) {
                $infoBlock = mb_strstr($textPDF, $end, true);

                if (!empty($infoBlock)) {
                    break;
                }
            }
        }

        if (empty($infoBlock)) {
            return null;
        }

        $this->isEnglish = ($this->lang === 'en') ? true : false;
        $date = null;

        // Travel Agency
        if (preg_match("#\n(.+)[ ]{3,}.*Email.*(?:\n[ ]*(?:hours|jam))?\n+([\d\-\+\(\) ]+?) {5,}{$this->opt('cs@traveloka.com')}#ui", $textPDF, $matches)) {
            $names = array_filter(array_map("trim", preg_split("# {3,}#", trim($matches[1]))));
            $phs = array_filter(array_map("trim", preg_split("# {3,}#", trim($matches[2]))));
            $phonesAdd = array_diff($phs, array_column($email->getTravelAgency()->getProviderPhones(), 0));

            foreach ($phonesAdd as $i => $ph) {
                if (count($phs) == count($names)) {
                    $email->ota()->phone($ph, $names[$i]);
                } else {
                    $email->ota()->phone($ph, 'Customer Service');
                }
            }
        }

        // it-125778433-id.eml
        $seatsByRoutes = [];
        $seatsEndVariantsEn = empty(self::$dict['en']) || empty(self::$dict['en']['seatsEnd'])
            ? [] : (array) self::$dict['en']['seatsEnd']
        ;
        $patternSeatsEnd = "(?:\n[ ]*{$this->opt(array_merge($seatsEndVariantsEn, (array) $this->t('seatsEnd')))}|\n{4})";

        if (preg_match("/\n(.+ {$this->opt($this->t('seatsStart'))}.*\n[\s\S]+?){$patternSeatsEnd}/", $textPDF, $matches)) {
            $seatsParts = $this->splitText($matches[1], "/(.+ [A-Z]{3}[ ]{1,4}-[ ]{1,4}[A-Z]{3}(?: |\n|$))/", true);

            foreach ($seatsParts as $seatsText) {
                if (preg_match("/ ([A-Z]{3})[ ]{1,4}-[ ]{1,4}([A-Z]{3})(?: |\n|$)/", $seatsText, $m)
                    && preg_match_all("/.{4}[ ]{4}(\d+[A-Z])$/m", $seatsText, $seatMatches)
                ) {
                    if (empty($seatsByRoutes[$m[1] . '-' . $m[2]])) {
                        $seatsByRoutes[$m[1] . '-' . $m[2]] = $seatMatches[1];
                    } else {
                        $seatsByRoutes[$m[1] . '-' . $m[2]] = array_merge($seatsByRoutes[$m[1] . '-' . $m[2]], $seatMatches[1]);
                    }
                }
            }
        }

        $travellers = $tickets = [];
        
        if (!$this->parsePdfPassengers($textPDF, $travellers, $tickets)) {
            return false;
        }

        if (count($tickets) === 0) {
            foreach ($email->getItineraries() as $it) {
                /** @var \AwardWallet\Schema\Parser\Common\Flight $it */
                if ($it->getType() === 'flight' && empty($it->getTicketNumbers())) {
                    $r = $it;
                }
            }
        } else {
            foreach ($email->getItineraries() as $it) {
                /** @var \AwardWallet\Schema\Parser\Common\Flight $it */
                if ($it->getType() === 'flight' && !empty($iTickets = array_column($it->getTicketNumbers(), 0)) && strncasecmp($tickets[0], $iTickets[0], 3) === 0) {
                    $r = $it;
                    $ticketAdd = array_diff($tickets, $iTickets);

                    if (!empty($ticketAdd)) {
                        $r->issued()->tickets($ticketAdd, false);
                    }
                }
            }
        }

        if (!isset($r)) {
            $r = $email->add()->flight();

            // General
            $r->general()
                ->travellers($travellers);
            // issued
            if (count($tickets) > 0) {
                $r->issued()->tickets($tickets, false);
            }
        } else {
            $trAdd = array_diff($travellers, array_column($r->getTravellers(), 0));

            if (!empty($trAdd)) {
                $r->general()
                    ->travellers($travellers);
            }
        }

        $bonusPrograms = ['GarudaMiles']; // hard-coded

        if (preg_match_all("#^[ ]*{$this->opt($bonusPrograms)}[- ]*(\d{7,})(?:[ ]{2}|$)#im", $textPDF, $accMatches)) {
            $r->program()->accounts(array_unique($accMatches[1]), false);
        }

        if (preg_match("#{$this->opt($this->t('Traveloka Booking ID'))}\s+" . (!$this->isEnglish ? "(?:[^\n]*\s+)" : "") . "([^\n]+)\s{3,}(\d+) *\n(.+)#su", $infoBlock, $matches)
            || preg_match("#{$this->opt($this->t('Traveloka Booking ID'))}\s+" . (!$this->isEnglish ? "(?:[^\n]*\s+)" : "") . "([^\n]+)\s(\d+)\s*\n(.+)#su", $infoBlock, $matches)
            || stripos($infoBlock, $this->t('Traveloka Booking ID')) === false && preg_match("#{$this->opt($this->t('Booking ID'))}\s+" . (!$this->isEnglish ? "(?:[^\n]*\s+)" : "") . "([^\n]+)\s{3,}(\d+) *\n(.+)#su", $infoBlock, $matches)
        ) {
            if (!in_array($matches[2], array_column($email->getTravelAgency()->getConfirmationNumbers(), 0))) {
                $email->ota()->confirmation($matches[2], $this->t('Traveloka Booking ID'));
            }

            if (empty(trim($matches[1])) && !empty($matches[2]) && !empty($matches[3])) {
                $matches[1] = $this->re("/(?:Traveloka\s+Booking\s+ID)\s+(.+)/", $infoBlock);
            }

            $date = strtotime($this->dateStringToEnglish(preg_replace("#(.*\d{4}.*) / .*\d{4}.*#", '$1', $matches[1])));
            $separator = explode("\n", $this->re("#\n[ ]*({$this->opt($this->t("sSeparator"))}.+?)\n\n\n#su", $matches[3]));

            if (count($separator) > 7) {
                $segments = preg_split("#\n[ ]*{$this->opt($this->t("sSeparator"))}.+?\n\n#su", $matches[3]);
            } else {
                $segments = preg_split("#\n[ ]*{$this->opt($this->t("sSeparator"))}.+?\n\n\n#su", $matches[3]);
            }

            foreach ($segments as $segment) {
                if (trim($segment) == 'IMPORTANT:') {
                    continue;
                }
                $s = $r->addSegment();

                $tablePos = [0];

                if (preg_match("/^((.+? ){$this->patterns['time']}[ ]{1,20})\S/m", $segment, $m)) {
                    $tablePos[1] = mb_strlen($m[2]);
                    $tablePos[2] = mb_strlen($m[1]);
                } elseif (preg_match("/^((.+? ){$this->patterns['time']}) /m", $segment, $m)
                    || preg_match("/^((.+? ){$this->patterns['time']})$/m", $segment, $m)
                ) {
                    // it-125778433-id.eml
                    $tablePos[1] = mb_strlen($m[2]);
                    $tablePos[2] = mb_strlen($m[1]) + 4;
                }

                if (preg_match("/^(.+ ){$this->opt($this->t('Airline Booking Code (PNR)'))}$/im", $segment, $m)) {
                    $tablePos[3] = mb_strlen($m[1]);
                }
                $tablePos = array_unique($tablePos);
                $table = $this->splitCols($segment, $tablePos);

                if (count($table) !== 3 && count($table) !== 4) {
                    //hard code it-73636011
                    $table = $this->splitCols($segment, [0, 28, 40, 100]);
                    //$this->logger->debug(var_export($table, true));

                    if (preg_match("/^\d+\:\d+\s*[A-z]{1,}/", $table[1])) { //18:55 Ba
                        $table = $this->splitCols($segment, [0, 26, 35, 100]);
                        $this->logger->debug(var_export($table, true));
                    }

                    if (count($table) !== 3 && count($table) !== 4) {
                        $this->logger->debug('failed parse: other format pdf');

                        return false;
                    }
                }

                if (preg_match("#(.+)\s*\n[^\n]+? ([A-Z]{1,2}) +\( *(.+?) *\)#s", $table[0], $m)) {
                    $s->airline()
                        ->name($this->nice($m[1]))
                        ->noNumber(); // a picture in PDF with a number
                    $s->extra()
                        ->bookingCode($m[2])
                        ->cabin($m[3]);
                } elseif (preg_match("#\n*(.+)\s+\n[ ]*((?:Economy|PROMO|OTHERS))\s*(?:Check-in|Dioperasikan|Operated|\w+|$)#", $table[0], $m)) {
                    $m[1] = preg_replace("/^(.+Check-in ul)/su", "", $m[1]);
                    $s->airline()
                        ->name($this->nice($m[1]))
                        ->noNumber(); // a picture in PDF with a number
                    $s->extra()
                        ->cabin($m[2]);
                } elseif (preg_match("#^\s*(.+)\s+\n[ ]*Operated by#s", $table[0], $m)) {
                    $s->airline()
                        ->name($this->nice($m[1]))
                        ->noNumber(); // a picture in PDF with a number
                } elseif (preg_match('/^\s*(\w+|\w+(?: \w+)+)\s*$/', $table[0], $m)) {
                    $s->airline()
                        ->name($m[1])
                        ->noNumber(); // a picture in PDF with a number
                } elseif (preg_match('/^\s*(\w+|\w+(?: \w+)+)\s+OTHERS\s*$/su', $table[0], $m)) {
                    $s->airline()
                        ->name($m[1])
                        ->noNumber(); // a picture in PDF with a number
                }

                if (preg_match("#Operated by ([A-Z\d][A-Z]|[A-Z][A-Z\d]) - .+#", $table[0], $m)) {
                    $s->airline()
                        ->operator($m[1]);
                }

                if (preg_match("#(\d+:\d+)\s+(\d+:\d+)\s*(?:(.+))?\s*$#", trim($table[1]), $m)) {
                    $s->departure()->date(strtotime($m[1], $date));

                    if (isset($m[3]) && !empty($m[3]) && preg_match("/[\d\:]+/", $m[3])) {
                        $s->arrival()->date(strtotime($this->dateStringToEnglish($m[3]) . ' ' . date('Y',
                                $date) . ', ' . $m[2]));
                    } else {
                        $s->arrival()->date(strtotime($m[2], $date));
                    }
                }

                $points = $this->splitText(trim($table[2]), "/^(.*\(\s*[A-Z]{3}\s*\))(?:\s*\/\s*.*)?$/m", true);

                if (count($points) === 2
                    && preg_match($pattern = "/^(?<city>.*?)[ ]*\(\s*(?<code>[A-Z]{3})\s*\)(?:\s*\/\s*.*)?\n{1,2}.+?(?i)(?:Terminal[ ]*(?<terminal>.*(?:\s+Domestic|\s+International)?))?(?:\n|$)/u", $points[0], $mDep)
                    && preg_match($pattern, $points[1], $mArr)
                ) {
                    $s->departure()->name($this->nice($mDep['city']))->code($mDep['code']);

                    if (!empty($mDep['terminal'])) {
                        $s->departure()->terminal($this->nice($mDep['terminal']));
                    }

                    $s->arrival()->name($this->nice($mArr['city']))->code($mArr['code']);

                    if (!empty($mArr['terminal'])) {
                        $s->arrival()->terminal($this->nice($mArr['terminal']));
                    }

                    if (array_key_exists($mDep['code'] . '-' . $mArr['code'], $seatsByRoutes)) {
                        $s->extra()->seats($seatsByRoutes[$mDep['code'] . '-' . $mArr['code']]);
                    }
                }

                if (count($table) > 3) {
                    $conf = $this->re("#{$this->opt($this->t('Airline Booking Code (PNR)'))}" . ($this->isEnglish ? '' : '\s*.*') . "\s+([A-Z\d]{5,10})$#m", $table[3]);

                    if (!empty($conf) && !in_array($conf, array_column($r->getConfirmationNumbers(), 0))) {
                        $r->general()->confirmation($conf);
                    }
                }
            }
        }

        return true;
    }

    private function parseHtml(Email $email): void
    {
        $this->logger->debug(__FUNCTION__);
        $ta = $email->ota(); // because Traveloka is company that provides airline ticketing and hotel booking services online

        $conf = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Traveloka Booking ID'))}]/following::text()[normalize-space()!=''][1]");

        if (empty($conf)) {
            $conf = $this->re("/Booking ID\s*(\d+)/", $this->subject);
        }
        $ta->confirmation($conf, $this->t('Traveloka Booking ID'));

        $phone = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Need Help?'))}]/following::text()[normalize-space()!=''][1]");

        if (!empty($phone) && preg_match("/^[+\d\(\)\-\s]+$/ui", $phone)) {
            $ta->phone($phone, $this->t('Need Help?'));
        }

        $status = $this->http->FindSingleNode(
            "//text()[{$this->starts($this->t('Your flight reservation has been successfully'))}]",
            null,
            false,
            "#{$this->opt($this->t('Your flight reservation has been successfully'))}\s*(.+?)(?:\.|$)#"
        );
        $pax = array_filter($this->http->FindNodes(
            "//text()[{$this->eq($this->t('Passenger(s):'))}]/ancestor::td[1]/descendant::text()[normalize-space()!=''][position()>1]",
            null, "#(.+?)\s*\(#"
        ));

        $xpath = "//text()[{$this->eq($this->t('Airline Booking Code (PNR):'))}]/ancestor::table[count(./following-sibling::table)>0][1]";
        $this->logger->debug("[XPATH]: " . $xpath);
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            if ($this->http->XPath->query("./following-sibling::table[1]/descendant::text()[normalize-space()!=''][2][" . $this->contains($this->t("direct")) . "]",
                    $root)->length > 0
            ) {
                $cntSegments = 1;
            } elseif (!empty($cnt = $this->http->FindSingleNode("./following-sibling::table[1]/descendant::text()[normalize-space()!=''][2][" . $this->contains($this->t("transit")) . "]",
                    $root, false, "#(\d+) " . $this->opt($this->t("transit")) . "#"))
            ) {
                $cntSegments = $cnt + 1;
            } else {
                $this->logger->debug('need check format');

                return;
            }
            $r = $email->add()->flight();
            $r->general()
                ->confirmation(
                    $this->http->FindSingleNode("./descendant::text()[normalize-space()!=''][last()]", $root))
                ->travellers(preg_replace("/^(Mr\.|Mrs\.|Ms\.)/", "", $pax));

            if (!empty($status)) {
                $r->general()->status($status);
            }

            $date = $this->normalizeDate($this->http->FindSingleNode("./following-sibling::table[1]/descendant::text()[normalize-space()!=''][1]",
                $root));
            $duration = null;
            $node = $this->http->FindSingleNode("./following-sibling::table[1]/descendant::text()[normalize-space()!=''][2]",
                $root);

            if (preg_match("#^\d+:\d+ *\- *\d+:\d+ \((.+?), " . $this->opt($this->t("direct")) . "\)#", $node, $m)) {
                $duration = $m[1];
            }

            $xpathSeg = "./following-sibling::table[normalize-space()!=''][position()>1][count(./descendant::table)>2][position()<={$cntSegments}]";
            $segments = $this->http->XPath->query($xpathSeg, $root);
            $this->logger->debug("[XPATH-seg]: " . $xpathSeg);

            foreach ($segments as $segment) {
                $s = $r->addSegment();

                if (isset($duration)) {
                    $s->extra()
                        ->duration($duration)
                        ->stops(0);
                }

                $s->airline()
                    ->name($this->http->FindSingleNode("./descendant::text()[normalize-space()!=''][1]",
                        $segment))
                    ->noNumber(); //a picture in email with a number;

                if ($operator = $this->http->FindSingleNode("./descendant::text()[normalize-space()!=''][2][{$this->eq($this->t('Operated by'))}]/following::text()[normalize-space()!=''][1]",
                    $segment)) {
                    $s->airline()->operator($operator);
                    $num = 4;
                } else {
                    $num = 2;
                }
                $depTime = $this->http->FindSingleNode("./descendant::text()[normalize-space()!=''][{$num}]/ancestor::tr[1]/td[1]/descendant::text()[normalize-space()!=''][1]",
                    $segment);
                $arrTime = $this->http->FindSingleNode("./descendant::text()[normalize-space()!=''][{$num}]/ancestor::tr[1]/following-sibling::tr[normalize-space()!=''][1]/td[1]/descendant::text()[normalize-space()!=''][1]",
                    $segment);

                if (!empty($date)) {
                    $s->departure()->date(strtotime($depTime, $date));
                    $s->arrival()->date(strtotime($arrTime, $date));
                }
                //maybe not necessary - service could calculate by Codes
                $weekDayDepart = $this->http->FindSingleNode("./descendant::text()[normalize-space()!=''][{$num}]/ancestor::tr[1]/td[1]/descendant::text()[normalize-space()!=''][last()]",
                    $segment);
                $weekDayArrive = $this->http->FindSingleNode("./descendant::text()[normalize-space()!=''][{$num}]/ancestor::tr[1]/following-sibling::tr[normalize-space()!=''][1]/td[1]/descendant::text()[normalize-space()!=''][last()]",
                    $segment);

                if ($weekDayDepart !== $weekDayArrive) {
                    if (in_array($this->lang, ['en'])) {
                        $weekNumArr = WeekTranslate::number1(WeekTranslate::translate($weekDayArrive, $this->lang));
                    } elseif (in_array($this->lang, ['vi'])) {
                        $weekNumArr = preg_match("/^\s*[[:alpha:]]{1,5} (\d)\s*$/u", $weekDayArrive, $m) ? (int) $m[1] : null;
                    }
                    $correctedArrDate = $this->calcDayArrive($s->getArrDate(), $weekNumArr);
                    $s->arrival()->date($correctedArrDate);
                }

                $node = implode("\n",
                    $this->http->FindNodes("./descendant::text()[normalize-space()!=''][{$num}]/ancestor::tr[1]/td[string-length(normalize-space())>2][2]/descendant::text()[normalize-space()!='']",
                        $segment));

                if (preg_match("#\(([A-Z]{3})\)\s+(.+)\s*(?:Terminal (.+))?$#", $node, $m)) {
                    $s->departure()
                        ->code($m[1])
                        ->name($m[2]);

                    if (isset($m[3]) && !empty($m[3])) {
                        $s->departure()->terminal($m[3]);
                    }
                }
                $node = implode("\n",
                    $this->http->FindNodes("./descendant::text()[normalize-space()!=''][{$num}]/ancestor::tr[1]/following-sibling::tr[normalize-space()!=''][1]/td[string-length(normalize-space())>2][2]/descendant::text()[normalize-space()!='']",
                        $segment));

                if (preg_match("#\(([A-Z]{3})\)\s+(.+)\s*(?:Terminal (.+))?$#", $node, $m)) {
                    $s->arrival()
                        ->code($m[1])
                        ->name($m[2]);

                    if (isset($m[3]) && !empty($m[3])) {
                        $s->arrival()->terminal($m[3]);
                    }
                }

                $seats = array_filter($this->http->FindNodes("//text()[{$this->eq($s->getDepCode() . ' - ' . $s->getArrCode())}]/ancestor::div[1]/following::div[1]", null, "/\s(\d{1,2}[A-Z])/"));

                if (count($seats) > 0) {
                    $s->setSeats($seats);
                }
            }
        }
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function assignLang($body, $detectBody): bool
    {
//        foreach ($this->reBody as $lang => $reBody) {
        foreach ($detectBody as $lang => $dBody) {
            if (count($dBody) == 2 && mb_stripos($body, $dBody[0]) !== false && mb_stripos($body, $dBody[1]) !== false) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
    }

    private function dateStringToEnglish($date)
    {
        if (preg_match('#[[:alpha:]]+#iu', $date, $m)) {
            $monthNameOriginal = $m[0];

            if ($translatedMonthName = MonthTranslate::translate($monthNameOriginal, $this->lang)) {
                return preg_replace("#$monthNameOriginal#i", $translatedMonthName, $date);
            }
        }

        return $date;
    }

    private function re(string $re, ?string $str, $c = 1): ?string
    {
        if (preg_match($re, $str ?? '', $m) && array_key_exists($c, $m)) {
            return $m[$c];
        }

        return null;
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

    private function opt($field): string
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
    }

    private function splitText(?string $textSource, string $pattern, bool $saveDelimiter = false): array
    {
        $result = [];
        if ($saveDelimiter) {
            $textFragments = preg_split($pattern, $textSource, -1, PREG_SPLIT_DELIM_CAPTURE);
            array_shift($textFragments);
            for ($i=0; $i < count($textFragments)-1; $i+=2)
                $result[] = $textFragments[$i] . $textFragments[$i+1];
        } else {
            $result = preg_split($pattern, $textSource);
            array_shift($result);
        }
        return $result;
    }

    private function splitCols(?string $text, ?array $pos = null): array
    {
        $cols = [];
        if ($text === null)
            return $cols;
        $rows = explode("\n", $text);
        if ($pos === null || count($pos) === 0) $pos = $this->rowColsPos($rows[0]);
        arsort($pos);
        foreach ($rows as $row) {
            foreach ($pos as $k => $p) {
                $cols[$k][] = rtrim(mb_substr($row, $p, null, 'UTF-8'));
                $row = mb_substr($row, 0, $p, 'UTF-8');
            }
        }
        ksort($cols);
        foreach ($cols as &$col) $col = implode("\n", $col);
        return $cols;
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

    private function colsPos($table, $delta = 5): array
    {
        $pos = [];
        $rows = explode("\n", $table);
        foreach ($rows as $row)
            $pos = array_merge($pos, $this->rowColsPos($row));
        $pos = array_unique($pos); sort($pos); $pos = array_merge([], $pos);
        foreach ($pos as $i => $p) {
            for ($j=$i-1; $j>=0; $j=$j-1) {
                if (isset($pos[$j])) {
                    if (isset($pos[$i])) {
                        if ($pos[$i] - $pos[$j] < $delta)
                            unset($pos[$i]);
                    }
                    break;
                }
            }
        }
        sort($pos); $pos = array_merge([], $pos);
        return $pos;
    }

    private function nice($str)
    {
        return trim(preg_replace("#\s+#", ' ', $str), ' .-');
    }

    private function calcDayArrive($arrDate, $dayNumber)
    {
        if (is_string($arrDate) || $arrDate === false || $arrDate < strtotime('01/01/2010')) {
            return null;
        }

        for ($i = 0; $i <= 3; $i++) {
            $try = strtotime(sprintf('+%d days', $i), $arrDate);

            if ((int) date('N', $try) === $dayNumber) {
                return $try;
            }

            if ($i === 0) {
                continue;
            }
            $try = strtotime(sprintf('-%d days', $i), $arrDate);

            if ((int) date('N', $try) === $dayNumber) {
                return $try;
            }
        }

        return null;
    }

    private function mb_strposAll($text, $needle, $caseSensitive = false): bool
    {
        if (empty($text) || empty($needle)) {
            return false;
        }

        if (is_array($needle)) {
            foreach ($needle as $n) {
                if (($caseSensitive === true && mb_stripos($text, $n) !== false)
                        || mb_strpos($text, $n) !== false) {
                    return true;
                }
            }
        } elseif (is_string($needle) && ($caseSensitive === true && mb_stripos($text, $needle) !== false
                || mb_strpos($text, $needle) !== false)) {
            return true;
        }

        return false;
    }

    private function amount($price): ?float
    {
        $price = str_replace(",", ".", preg_replace("#[., ](\d{3})#", "$1", $this->re("/(\d[\d,. ]*)/", $price)));

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
            'US$' => 'USD',
            '€'   => 'EUR',
            'RM'  => 'MYR',
            '$'   => 'USD',
            '£'   => 'GBP',
            'S$'  => 'SGD',
            'Rp'  => 'IDR',
            'AU$' => 'AUD',
        ];

        foreach ($sym as $f => $r) {
            if ($s == $f) {
                return $r;
            }
        }

        return null;
    }

    private function normalizeDate($str)
    {
//        $this->logger->debug("Date: {$str}");
        $in = [
            //Thứ Hai, 18 thg 11 2019
            "/^\s*\D*,\s*(\d+)[ ]*[[:alpha:]]+[ ]*(\d{1,2})[ ]+(\d{4})\s*$/u",
        ];
        $out = [
            "$1.$2.$3",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return strtotime($str);
    }

    private function normalizeTraveller(?string $s): ?string
    {
        if (empty($s)) {
            return null;
        }

        $namePrefixes = '(?:MASTER|MSTR|MISS|MRS|MR|MS|DR|Ông|Bà|Cô|Nn)';

        return preg_replace([
            "/^(?:{$namePrefixes}[.\s]+)+(.{2,})$/is",
        ], [
            '$1',
        ], $s);
    }
}
