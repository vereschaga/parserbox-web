<?php

namespace AwardWallet\Engine\mta\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;

//similar format AsiaEscapePdf.php, InfinityPdf.php
class QantasHolidaysPdf extends \TAccountChecker
{
    public $mailFiles = "mta/it-19781501.eml, mta/it-20267324.eml, mta/it-230094435.eml, mta/it-26518799.eml, mta/it-26796971.eml, mta/it-26941379.eml, mta/it-27528179.eml, mta/it-29623664.eml, mta/it-30487153.eml, mta/it-30642164.eml, mta/it-33058648.eml, mta/it-33169058.eml, mta/it-34141370.eml, mta/it-35311747.eml, mta/it-44229737.eml, mta/it-44262649.eml, mta/it-49692075.eml, mta/it-62167075.eml";

    public $reFrom = ["MTA Travel", "mtatravel.com.au", "@qantasholidays.com.au", "vivaholidays.com.au"];

    public $reBody = [
        'en'   => ['PREPARED ON', 'www.qantas.com/holidays'],
        'en2'  => ['COST OF TOUR BY PASSENGER', 'PAYMENT DETAILS'],
        'en21' => ['Passenger Pricing Details', 'M T A TRAVEL'],
        'en3'  => ['PREPARED ON', 'www.vivaholidays.com.au'],
        'en4'  => ['PREPARED ON', 'MTA TRAVEL'],
        'en5'  => ['Issue Date', 'GOGO Vacations'],
    ];
    public $reSubject = [
        '#[A-Z\d\/]{6,} Booking itinerary: .+? for \w+\/\w+ \w+#',
        '#[A-Z\d\/]{6,}  Booking confirmation: .+? for \w+\/\w+ \w+#',
    ];
    public $lang = '';
    public $pdf;
    public $pdfNamePattern = ".*(?<!_agt)\.pdf"; //exclude ".*_agt.pdf" FE: it-27528179.eml
//    public $pdfNamePattern = ".+_(?:itn|adv|pax)\.pdf";
    public static $dict = [
        'en' => [
            'Booking'            => ['Booking', 'Booking Number:', 'Booking Number'],
            'Booking Reference:' => ['Booking Reference:', 'Booking Number', 'Quote Number'],
            'Issued'             => ['Issued', 'Date of Issue:', 'Issue Date'],
            'Arrive'             => ['Arrive', 'Arrives'],
        ],
    ];
    private $keywords = [
        'avis'         => ['Avis', 'AVIS CANADA'],
        'perfectdrive' => ['BUDGET AUSTRALIA', 'BUDGET CAR RENTAL'],
        'hertz' => ['HERTZ'],
    ];
    private $pax;
    private $dateRes;
    private $otaConfNo;
    private $parsedHotels = [];
    private $currentFlight = null;
    private $currentFlightSegment = null;

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->date = strtotime($parser->getDate());
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);
        $type = '';

        if (isset($pdfs) && count($pdfs) > 0) {
            foreach ($pdfs as $pdf) {
                if (($text = \PDF::convertToText($parser->getAttachmentBody($pdf))) !== null) {
                    if (!$this->assignLang($text)) {
                        $this->logger->debug('can\'t determine a language');

                        continue;
                    }
//                    $this->logger->debug('$text = '.print_r( $text,true));

                    if (strpos($text, 'PREPARED ON') !== false) {
                        if (!$this->parseEmail_1($text, $email)) {
                            break;
                        } else {
                            $type = '1';
                        }
                    } else {
                        if (!$this->parseEmail_2($text, $email)) {
                            break;
                        } else {
                            $type = '2';
                        }
                    }


                } else {
                    break;
//                    return null;
                }
            }
//        } else {
//            return null;
        }

        $a = explode('\\', __CLASS__);
        $class = end($a);
        $email->setType($class . ucfirst($this->lang) . $type);

        return $email;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (((stripos($text, 'Qantas Holidays') !== false)
                    || (stripos($text, 'www.qantas.com/holidays') !== false)
                    || (stripos($text, 'M T A TRAVEL') !== false)
                    || (stripos($text, 'GOGO Vacations') !== false)
                    || (stripos($text, 'www.infinityholidays.com.au/') !== false)
                    || (stripos($text, '.vivaholidays.com.au') !== false)
                    || (stripos($text, 'adventureworld.com') !== false)
                    || (stripos($text, 'by Brendan Vacations') !== false)
                ) && $this->assignLang($text)
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

            if ($flag) {
                foreach ($this->reSubject as $reSubject) {
                    if (preg_match($reSubject, $headers["subject"])) {
                        return true;
                    }
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
        $types = 7; //flights(1,2) | hotels(1,2) | events(1,2???) | car(1,2)
        $formats = 2; // parseEmail_1 | parseEmail_2
        $cnt = $types * $formats * count(self::$dict);

        return $cnt;
    }

    private function parseEmail_1($textPDF, Email $email)
    {
//        $this->logger->notice(__METHOD__);
        $confNo = $descr = null;
        $textPDF = preg_replace("#\n *MTA TRAVEL *Page +\d+.+?Issued[^\n]+#s",
            '', $textPDF);
        $textPDF = preg_replace("#\n *MTA TRAVEL *P a g e +\| +\d+.+? {5,}http:[^\n]+#",
            '', $textPDF);

        if (!empty($str = $this->re("#^(.+?)\n *Airline Details +Class#s", $textPDF))) {
            $textPDF = $str;
        }
        $node = $this->re("#^(.+?)\s+PREPARED ON#s", $textPDF);
        $node = str_replace("Itinerary for", ' ', $node);

        if (preg_match("#({$this->opt($this->t('Booking'))}) +([A-Z\d]{2} */ *[A-Z\d]{5,})#", $node, $m)) {
            $confNo = str_replace(' ', '', $m[2]);

            if (strpos($textPDF, 'vivaholidays') !== false) {
                $descr = 'Viva Holidays ' . $m[1];
            } else {
                $descr = 'Qantas ' . $m[1];
            }
            $node = preg_replace("#{$this->opt($this->t('Booking'))}.+#", '', $node);
        }

        if (preg_match("#{$this->opt($this->t('Issued'))} +(.+)#", $node, $m)) {
            $this->dateRes = strtotime($m[1]);
            $node = preg_replace("#{$this->opt($this->t('Issued'))}.+#", '', $node);
        }

        if (!empty($str = strstr($textPDF, 'YOUR TRAVEL SUMMARY', true))) {
            $node = $str;
        }

        if (strpos($node, 'YOUR TRAVEL ITINERARY') !== false
            && preg_match(
                "#\n\n([^\n]+? {5,}{$this->opt($this->t('Booking'))} +[A-Z\d]{2} */ *[A-Z\d]{5,}.+)#s",
                $node,
                $m)
        ) {
            $node = $m[1];
        }

        if (preg_match_all("#^ *(\w.+?)(?: {2,}|$)#m", $node, $m)) {
            $this->pax = array_filter($m[1], function ($s) {
                return preg_match("#^[A-Z ]+$#", $s);
            });
            $this->pax = preg_replace("/^\s*(MR|MRS|MISS|MS|MSTR|DR)\s+/i", '', $this->pax);
        }

        if (!isset($this->otaConfNo) || !in_array($confNo, $this->otaConfNo)) {
            $email->ota()
                ->confirmation($confNo, $descr);
            $this->otaConfNo[] = $confNo;
        }

        return $this->parseEmail_main($textPDF, $email);
    }

    private function parseEmail_2($textPDF, Email $email)
    {
        $this->logger->notice(__METHOD__);

        if (strpos($textPDF, 'COST OF TOUR BY PASSENGER') === false
            && strpos($textPDF, 'Passenger Pricing Details') === false
            && strpos($textPDF, 'Payment Details') === false
        ) {
            $this->logger->debug("other format 2");

            return false;
        }

        $confNo = $descr = null;
//        $node = $this->re("#^(.+?)\s+(?:PAYMENT DETAILS|Payment Schedule|Payments:)#s", $textPDF);
//        $node = $this->re("#^(.+?)\s+(?:ITINERARY|Agent Information|IMPORTANT:|Notes)#s", $textPDF);
        $node = $textPDF;

        if (preg_match("#({$this->opt($this->t('Booking Reference:'))}) +([A-Z\d]{2} *\/ *[A-Z\d]{5,})#", $node, $m)) {
            $confNo = str_replace(' ', '', $m[2]);

            if (strpos($textPDF, 'vivaholidays') !== false) {
                $descr = 'Viva Holidays ' . $m[1];
            } elseif (strpos($textPDF, 'infinityholidays') !== false) {
                $descr = 'infinity Holidays ' . $m[1];
            } else {
                $descr = 'Qantas ' . $m[1];
            }
        }

        if ($str = $this->re("#(.+)\s*\n[^\n]*({$this->opt($this->t('Booking Reference:'))}) +[A-Z\d]{2} *\/ *[A-Z\d]{5,}#",
            $node)
        ) {
            $date = $this->re("#(?:.+? {5,})?(.+)$#", $str);

            if (preg_match("#(\d+)\s*(\D+)\s*\d{2}(\d{2})#", $date, $m)) {
                $this->dateRes = strtotime($m[1] . ' ' . $m[2] . ' 20' . $m[3]);
            } else {
                $this->dateRes = strtotime($date);
            }
        }

        if (!empty($tot = $this->re("#(?:TOTAL PRICE|PASSENGER TOTAL|Passenger Total:) +(.+)#", $node))) {
            $tot = $this->getTotalCurrency($tot);
            $email->price()
                ->total($tot['Total'])
                ->currency($tot['Currency']);
        }

        $node = $this->findСutSection($node, 'COST OF TOUR BY PASSENGER', 'PAYMENT DETAILS');

        if (empty($node)) {
            $node = $this->findСutSection($textPDF, 'Passenger Pricing Details', 'Payment Schedule');
        }

        if (empty($node)) {
            $node = $this->findСutSection($textPDF, 'Passenger Details', 'Payment Details');
        }

        if (preg_match_all("#^ *(\w.+?)(?: {2,}|$)#m", $node, $m)) {
            $this->pax = array_filter($m[1], function ($s) {
                return preg_match("#^[A-Z ]+$#", $s);
            });
        }

        if (empty($this->pax)) {
            $s = $this->re("/BOOKING ADVICE PREPARED FOR:\s+([\s\S]+)\n\s*ATTENTION:/", $textPDF);
            if (preg_match_all("#^ *(\w.+?)(?: {2,}|$)#m", $s, $m)) {
                $this->pax = array_filter($m[1], function ($s) {
                    return preg_match("#^[A-Z ]+$#", $s);
                });
            }
        }
        $this->pax = preg_replace("/^\s*(MR|MRS|MISS|MS|MSTR|DR)\s+/i", '', $this->pax);

        if (preg_match("#Issue Date +(.+)#", $textPDF, $m)) {
            $this->dateRes = strtotime($m[1]);
        }


        if (!isset($this->otaConfNo) || !in_array($confNo, $this->otaConfNo)) {
            $email->ota()
                ->confirmation($confNo, trim($descr, " :"));
            $this->otaConfNo[] = $confNo;
        }

        $textPDF = preg_replace("#\n *Qantas Holidays Limited[^\n]+ *P[ ]*a[ ]*g[ ]*e[ \|]+\d+[^\n]+#s",
            '', $textPDF);
        $textPDF = preg_replace("#\n *Viva Holidays Limited[^\n]+ *P[ ]*a[ ]*g[ ]*e[ \|]+\d+[^\n]+#s",
            '', $textPDF);
        $textPDF = preg_replace("#\n *[^\n]+ *Page +\d+\s+Issue Date\s+.+?Agent Code[^\n]+(?:\n *Your Reference)?[^\n]+#s",
            '', $textPDF);
        $textPDF = preg_replace("#\n *Page +\d+\b.*#", '', $textPDF);
        $textPDF = preg_replace("#\n.+ \| *Page \d+ of \d+(?:\n|$)#", "\n\n", $textPDF);

        if (!empty($str = $this->re("#^(.+?)\n *Airline Details +Class#s", $textPDF))) {
            $textPDF = $str;
        }
//        $this->logger->critical($textPDF);
        return $this->parseEmail_main($textPDF, $email);
    }

    private function parseEmail_main($textPDF, Email $email)
    {
        //grouping segments & added date on it
        $arrs = $this->splitter("#^( *\w+ \d+ \w+ \d{4} *)$#m", "ControlStr\n" . $textPDF);
//        $this->logger->debug('$arrs = '.print_r( $arrs,true));
        $arr = [];
        $segs = [];

        foreach ($arrs as $root) {
            $dateStr = $this->re("#(.+)#", $root);
            $newRoot = preg_replace("#(.+)(\n+)(^ *(?:TRANSFER|ACCOMMODATION|FLIGHT|Flight - Departing|CRUISE|BUS/SHUTTLE TRANSFER|SIGHTSEEING|PRIVATE CAR|TICKET|Own Arrangements|CAR|Tour|Tickets|TripSecure|ACTIVITY|FLIGHT - DEPARTING .*|FLIGHT - ARRIVING) *\n)#mi",
                '$1' . "\n{$dateStr}\n" . '$3',
                $root);
            $newRoot = preg_replace("#({$dateStr})(\n)($dateStr)#", '$1', $newRoot);

            $arr = array_merge($arr, $this->splitter("#^( *\w+ \d+ \w+ \d{4} *)$#m", "ControlStr\n" . $newRoot));
        }

//        $this->logger->debug('$arr = '.print_r( $arr,true));

        for ($i = 0; $i < count($arr); $i++) {
            if ((preg_match("#^ +(?:Flight - Departing)#m", $arr[$i])
                    && (strpos($arr[$i], 'Arriving') === false)
                    && isset($arr[$i + 1])
                    && (strpos($arr[$i + 1], 'Arriving') !== false)
                )
                || (preg_match("#^ +(?:FLIGHT)$#m", $arr[$i])
                    && (strpos($arr[$i], 'Arrives') === false)
                    && isset($arr[$i + 1])
                    && (strpos($arr[$i + 1], 'Arrives') !== false)
                )
                || (preg_match("#^ +(?:FLIGHT - DEPARTING\s+)#m", $arr[$i])
                    && (strpos($arr[$i], 'FLIGHT - ARRIVING') === false)
                    && isset($arr[$i + 1])
                    && (strpos($arr[$i + 1], 'FLIGHT - ARRIVING') !== false)
                )
            ) {
                $segs[] = $arr[$i] . $arr[$i + 1];
                $i++;
            } else {
                $segs[] = $arr[$i];
            }
        }

        //main parsing
//        $this->logger->debug('$segs = '.print_r( $segs,true));
        foreach ($segs as $i => $root) {

            //accommodation, flights, transfers, car hire  and tours
            if ((preg_match("#^.+\n\s*(.* )?(TOUR) *\n#i", $root, $m)
            || preg_match("#^.+\n\s*.+\n\s*.* Tour\b#i", $root, $m))
                && strpos($root, 'Start point:') !== false
                && strpos($root, 'Departure time:') !== false
                && strpos($root, 'Booking Details') === false
            ) {
                $this->logger->debug('parseEvent');
                if (!$this->parseEvent_3($root, $email)) {
//                    return false;
                    // for error
                    $email->add()->event();
//                        continue;
                }
                continue;
            }
            if (preg_match("#.+\n\s*(TRANSFER|CRUISE|BUS/SHUTTLE TRANSFER|PRIVATE CAR|TICKET|MODULES|Tour|Tickets) *\n#i", $root,
                $m)) {
                $this->logger->info("skip {$m[1]}: need examples with more info");

                continue;
            }

            if (preg_match("#.+\n\s*(Own Arrangements) *\n *(?:Own arrangements |.+?)(?:from .+? to .+|in .+? for .+)#i",
                $root,
                $m)) {
                $this->logger->info("skip {$m[1]}: not reservation");

                continue;
            }

            if (preg_match("#.+\n\s*(TripSecure) *\n#i", $root, $m)) {
                $this->logger->info("skip {$m[1]}: not reservation - insurance");

                continue;
            }

            if (preg_match("#.+\n\s*(?:Flight - Departing)#", $root)) {
                $this->logger->debug('parseFlight_1');
                if (!$this->parseFlight_1($root, $email)) {
//                    return false;
                    // for error
                    $email->add()->flight();
//                    continue;
                }

                continue;
            }
            if (preg_match("#.+\n\s*(?:FLIGHT - DEPARTING)#", $root)) {
                $this->logger->debug('parseFlight_3');
//           WED 10 MAY 2023
//           FLIGHT - DEPARTING                                                                          Airline Reference: AM / 3BHOEP
//           Depart CHARLESTON SC at 13:57 on UNITED AIRLINES flight UA1886                                                             Confirmed
//           operated by United Airlines
//           FLIGHT - ARRIVING
//           Arrive NEWARK INTL at 15:54

                if (!$this->parseFlight_3($root, $email)) {
//                    return false;
                    // for error
                    $email->add()->flight();
//                    continue;
                }

                continue;
            }

            if (preg_match("#.+\n\s*FLIGHT *\n#", $root)) {
                $this->logger->debug('parseFlight_2');
                if (!$this->parseFlight_2($root, $email)) {
//                    return false;
                    // for error
                    $email->add()->flight();
//                    continue;
                }

                continue;
            }

            if (preg_match("#.+\n\s*ACCOMMODATION#i", $root)) {
                $this->logger->debug('parseHotel');
                if (strpos($root, 'Booking Details') === false) {
                    if (!$this->parseHotel_1($root, $email)) {
//                    return false;
                        // for error
                        $email->add()->hotel();
//                        continue;
                    }
                } else {
                    if (!$this->parseHotel_2($root, $email)) {
//                    return false;
                        // for error
                        $email->add()->hotel();
//                        continue;
                    }
                }

                continue;
            }

            if (preg_match("#.+\n\s*(?:CAR|Car Rental)\b#", $root)) {
                $this->logger->debug('parseCar');
                if (strpos($root, 'Pick up at') !== false) {
                    if (!$this->parseCar_3($root, $email)) {
//                    return false;
                        // for error
                        $email->add()->rental();
//                        continue;
                    }
                } elseif (strpos($root, 'Booking Details') === false) {
                    if (!$this->parseCar_1($root, $email)) {
//                    return false;
                        // for error
                        $email->add()->rental();
//                        continue;
                    }
                } else {
                    if (!$this->parseCar_2($root, $email)) {
//                    return false;
                        // for error
                        $email->add()->rental();
//                        continue;
                    }
                }

                continue;
            }

            if (preg_match("#.+\n\s*(?:SIGHTSEEING|ACTIVITY)#", $root)) {
                $this->logger->debug('parseEvent');
                if (strpos($root, 'Booking Details') === false) {
                    if (!$this->parseEvent_1($root, $email)) {
//                    return false;
                        // for error
                        $email->add()->event();
//                        continue;
                    }
                } else {
                    if (!$this->parseEvent_2($root, $email)) {
//                    return false;
                        // for error
                        $email->add()->event();
//                        continue;
                    }
                }

                continue;
            }
            $this->logger->debug("other format rootSegment: {$i}");
            $this->logger->debug($root);

            continue;
//            return false;
        }

        return true;
    }

    private function parseEvent_1($textPDF, Email $email)
    {
        $this->logger->debug('event type 1');

        if (preg_match("#(?:DEPARTS: Daily|Operates Daily:)#", $textPDF)
            || !preg_match("# *Departs.+?: +(\d+)[:\.](\d+(?:\s*[ap]m)?)#", $textPDF)
        ) {
            $type = $this->re("#.+\n *(.+)#", $textPDF);
            $this->logger->info("skip {$type}: no startDateTime");

            return true;
        }
        $date = strtotime($this->re("#(.+)#", $textPDF));

        $r = $email->add()->event();
        $r->general()
            ->travellers($this->pax)
            ->date($this->dateRes);

        if (preg_match("# {5,}Confirmed\n#", $textPDF)) {
            $textPDF = str_replace("Confirmed", '', $textPDF);
            $r->general()
                ->confirmation($this->re("#Supplier Reference: +(.+)#", $textPDF));
            $r->general()
                ->status('Confirmed');
        } else {
            $r->general()->noConfirmation();
        }

        if (preg_match("#SIGHTSEEING\s+([^\n]+)\n *For (\d+) adult#", $textPDF, $m)) {
            $r->place()
                ->name($m[1])
                ->type(EVENT_EVENT);
            $r->booked()->guests($m[2]);
        }

        if (preg_match("#\n\n(.+?)\s+TEL: ([ \d\-\+\(\)]{7,})#s", $textPDF, $m)) {
            $r->place()
                ->address($this->nice($this->re("#[^\n]+\n(.+)#s", $m[1])))
                ->phone($m[2]);
        }

        /*
                     Departs Tue,Thu,Sat : 2.00pm (07Oct-31Mar19)
                     1.00pm (01Apr-06Oct18)
                     Finish Time     : 10.30pm approx

         * */
        if (preg_match("# *Departs.+?: +(\d+)[:\.](\d+(?:\s*[ap]m)?)#", $textPDF,
            $m)) {//getting first time - $date reservation in period  (07Oct-31Mar19)
            $r->booked()
                ->start(strtotime($m[1] . ":" . $m[2], $date));
        }

        if (preg_match("# *Finish Time.*?: +(\d+)[:\.](\d+(?:\s*[ap]m)?)#", $textPDF, $m)) {
            $r->booked()
                ->end(strtotime($m[1] . ":" . $m[2], $date));
        }

        return true;
    }

    private function parseEvent_2($textPDF, Email $email)
    {
        $this->logger->debug('event type 2');
        $infoBlock = strstr($textPDF, 'Booking Details', true);
        $detailsBlock = strstr($textPDF, 'Booking Details');

        if (empty($infoBlock)) {
            $this->logger->debug('other format Event_2');

            return false;
        }
        $infoBlock = preg_replace("/^[\s\S]*(SIGHTSEEING|ACTIVITY)/", '$1', $infoBlock);

        if (!empty($str = strstr($detailsBlock, 'Other Information', true))) {
            $otherBlock = strstr($detailsBlock, 'Other Information');
            $detailsBlock = $str;
        }

        if (preg_match('#LOCATIONS AND HOURS OF OPERATION#', $textPDF)) {
            $this->logger->debug("skip Event SIGHTSEEING: multi event");

            return true;
        }

        if (preg_match('/TOUR DEPARTURES/', $textPDF)) {
            $this->logger->debug("skip Event SIGHTSEEING: not enough data");

            return true;
        }

        if (preg_match("#\n\n(.+?)\s+TEL: ([ \d\-+()]{7,})#s", $detailsBlock, $m)) {
            $address = $this->nice($this->re("#[^\n]+\n(.+)#s", $m[1]));
            $phone = $m[2];
        }

        if (empty($address)
            && preg_match("#\n *Start point: +(.+)#", $detailsBlock, $m)
        ) {
            $address = $this->nice($m[1]);
        }

        if (!isset($address)
            && (preg_match('/Hop on Hop Off Dual Pass/', $detailsBlock)
                || preg_match('/Booking Details\n[ ]*Description:.+\n[ ]*Provided By:.+\n[ ]*For Passengers:.+\s*$/', $detailsBlock))
        ) {
            $this->logger->debug("skip Event SIGHTSEEING: not enough data");

            return true;
        }

        if (!isset($address) && preg_match("#SIGHTSEEING\s+\w+#", $infoBlock)) {
            $this->logger->debug("skip Event SIGHTSEEING: not address and date");

            return true;
        }

        $r = $email->add()->event();
        $r->general()
            ->noConfirmation()
            ->travellers($this->pax)
            ->date($this->dateRes);

        if (preg_match("#(?:SIGHTSEEING|ACTIVITY)\s+([^\n]+)#", $infoBlock, $m)) {
            $r->place()
                ->name($m[1])
                ->type(EVENT_EVENT);
//            // Mon 11 Nov An Uluru Sunset tour
//            if (preg_match("/\b([A-z]{3} \d{1,2} [A-z]{3})\s+{$r->getName()}/i", $textPDF, $m)) {
//                $this->logger->notice(var_export($m, true));
//            } else
//                $r->general()->date($this->dateRes);
        }
        $date = strtotime($this->re("#^(.+)#", $textPDF));
        $time = $this->re("#\s*Departure time: *(.+)#", $detailsBlock);
        if (!empty($date) && !empty($time)) {
            $r->booked()
                ->start(strtotime($time, $date));

            $duration = $this->re("#\s*Duration: *Approx\. *(.+)#i", $detailsBlock);

            if (!empty($duration) && !empty($r->getStartDate())) {
                $hours = $this->re("/\b(\d+(?:\.\d+)?)\s+hours?\b/i", $duration) * 60;
                $minutes = $this->re("/\b(\d+)\s+minutes?\b/i", $duration);

                if (!empty($hours)) {
                    $r->booked()
                        ->end(strtotime("+" . $hours . ' minutes', $r->getStartDate()));
                }

                if (!empty($minutes)) {
                    $r->booked()
                        ->start(strtotime("+" . $minutes . ' minutes', $r->getStartDate()));
                }
            }
        }

        if (isset($address)) {
            $r->place()
                ->address($address)
                ->phone($phone??null, true, true);
        }
        // need more examples
        return true;
    }

    private function parseEvent_3($textPDF, Email $email)
    {
        $this->logger->debug('event type 3');

        $r = $email->add()->event();
        $r->general()
            ->noConfirmation()
            ->travellers($this->pax)
            ->date($this->dateRes);

        if (preg_match("#^.+\n\s*.+\n\s*(\S.+?)(?: {5,}.*)\n#", $textPDF, $m)) {
            $r->place()
                ->name($m[1])
                ->address($this->nice($this->re("/\n\s*Start point:([\s\S]+?)\n[\w ]+:/", $textPDF)))
                ->type(EVENT_EVENT);
        }
        $date = strtotime($this->re("#^(.+)#", $textPDF));
        $time = $this->re("#\s*Departure time: *(.+)#", $textPDF);
        if (!empty($date) && !empty($time)) {
            $r->booked()
                ->start(strtotime($time, $date));

            $duration = $this->re("#\s*Duration: *(?:Approx\. *)?(.+)#i", $textPDF);

            if (!empty($duration) && !empty($r->getStartDate())) {
                $hours = $this->re("/\b(\d+(?:\.\d+)?)\s+(?:hours?|hrs)\b/i", $duration) * 60;
                $minutes = $this->re("/\b(\d+)\s+(?:minutes?|mins)\b/i", $duration);

                if (!empty($hours)) {
                    $r->booked()
                        ->end(strtotime("+" . $hours . ' minutes', $r->getStartDate()));
                }

                if (!empty($minutes)) {
                    $r->booked()
                        ->start(strtotime("+" . $minutes . ' minutes', $r->getStartDate()));
                }
            }
        }

        if (isset($address)) {
            $r->place()
                ->address($address)
                ->phone($phone??null, true, true);
        }
        // need more examples
        return true;
    }

    private function parseCar_1($textPDF, Email $email)
    {
        $this->logger->debug('car 1');
        //$this->logger->debug($textPDF);
        $date = strtotime($this->re("#(.+)#", $textPDF));

        $r = $email->add()->rental();
        $r->general()
            ->travellers($this->pax)
            ->date($this->dateRes);

        if (preg_match("# {5,}Confirmed\n#", $textPDF)) {
            $textPDF = str_replace("Confirmed", '', $textPDF);
            $r->general()
                ->status('Confirmed');
        }

        if ($conf = $this->re("#Supplier Reference: +(.+)#", $textPDF)) {
            $r->general()->confirmation($conf);
            $company = $this->re("#Supplier Reference:.+\n *(.+?)(?:[ ]{5,}|\n)#", $textPDF);
            $r->extra()->company($company);

            $rentalProvider = $this->getRentalProviderByKeyword($company);

            if (!empty($rentalProvider)) {
                $r->program()->code($rentalProvider);
            }
//        else {
//            $r->program()->keyword($company);
//        }
            $acc = $this->re("#Supplier Reference:.+\n *{$company}[ ]{5,}([\w\-]+)\n#", $textPDF);

            if (!empty($acc)) {
                $r->program()
                    ->account($acc, false);
            }
        } else {
            $r->general()->noConfirmation();
        }

        if (empty($type = $this->re("#Vehicle details:\s+\*.+?\- (.+ or similar)#", $textPDF))) {
            if (empty($type = $this->re("# *CAR\s+(.+) for \d+ day#", $textPDF))) {
                //Compact SUV - Automatic for 11 days
                $type = $this->re("#\s{3,}(.+?) for \d+ day#", $textPDF);
            }
        }
        $r->car()->type($type);

        if (preg_match("#Vehicle to be picked up at (\d+:\d+(?:(?i)\s*[ap]m)?) on (\d+ \w+) in (.+?)(?: {3,}([\d\.\,]+)|\n)#",
            $textPDF,
            $m)) {
            $r->pickup()
                ->date(strtotime($m[1], EmailDateHelper::parseDateRelative($m[2], $this->dateRes)))
                ->location($m[3]);

            if (isset($m[4]) && !empty($m[4]) && null !== ($email->getPrice()) && !empty($currency = $email->getPrice()->getCurrencyCode())) {
                $r->price()
                    ->total($m[4])
                    ->currency($currency);
            }
        }

        if (preg_match("#Vehicle to be returned at (\d+:\d+(?:(?i)\s*[ap]m)?) on (\d+ \w+) in (.+)#", $textPDF, $m)) {
            $r->dropoff()
                ->date(strtotime($m[1], EmailDateHelper::parseDateRelative($m[2], $this->dateRes)))
                ->location($m[3]);
        }

        if (preg_match("#Pick up from +(.+)#", $textPDF, $m)) {
            $r->pickup()->location($m[1]);
        }

        return true;
    }

    private function parseCar_2($textPDF, Email $email)
    {
        $this->logger->debug('car 2');
        $infoBlock = strstr($textPDF, 'Booking Details', true);
        $detailsBlock = strstr($textPDF, 'Booking Details');

        if (empty($infoBlock)) {
            $this->logger->debug('other format Car_2');

            return false;
        }
        $infoBlock = strstr($infoBlock, 'CAR');

        if (!empty($str = strstr($detailsBlock, 'Other Information', true))) {
            $otherBlock = strstr($detailsBlock, 'Other Information');
            $detailsBlock = $str;
        }

        $date = strtotime($this->re("#(.+)#", $textPDF));

        $r = $email->add()->rental();
        $r->general()
            ->travellers($this->pax)
            ->date($this->dateRes);

        if (preg_match("#CAR\s+([^\n]+)#", $infoBlock, $m)) {
            $company = $m[1];
        }

        if (!empty($c = $this->re("#\n[ ]*Provided By:\s+\#?(.+)#", $detailsBlock))) {
            $company = $c;
        }

        if (isset($company)) {
            $company = preg_replace("/ for \d+ days?\s*$/i", '', $company);
            $r->extra()->company($company);
            $rentalProvider = $this->getRentalProviderByKeyword($company);

            if (!empty($rentalProvider)) {
                $r->program()->code($rentalProvider);
            }
//            else {
//                $r->program()->keyword($company);
//            }
        }

        if (preg_match("#\n(([ ]*Pickup Details[ ]{4,})Dropoff Details.+)#s", $detailsBlock, $m)
            || preg_match("#\n(([ ]*Pick Up[ ]{4,})Drop Off.+)#s", $detailsBlock, $m)
        ) {
            $pos = [0, strlen($m[2])];
            $table = $this->splitCols($m[1], $pos);

            //pickup
            if (strpos($table[0], 'Date:') !== false) {
                $date = strtotime($this->re("#Date:\s+(.+)#", $table[0]));
                $date = strtotime($this->re("#Time:\s+(.+)#", $table[0]), $date);
                $r->pickup()
                    ->location($this->nice($this->re("#\n[ ]*Depot:\s+(.+?)(?:\n[ ]*Tel:|$)#s", $table[0])))
                    ->phone($this->re("#\n[ ]*Tel:\s+([\d\+\-\(\) ]+)#", $table[0]), false, true)
                    ->date($date);

                //dropoff
                $date = strtotime($this->re("#Date:\s+(.+)#", $table[1]));
                $date = strtotime($this->re("#Time:\s+(.+)#", $table[1]), $date);
                $r->dropoff()
                    ->location($this->nice($this->re("#\n[ ]*Depot:\s+(.+?)(?:\n[ ]*Tel:|$)#s", $table[1])))
                    ->phone($this->re("#\n[ ]*Tel:\s+([\d\+\-\(\) ]+)#", $table[1]), false, true)
                    ->date($date);
            } else {
                if (preg_match("/At (?<time>\d{1,2}:\d{2}(?:\s*[ap]m)?) on (?<date>.+)\s+from\s+(?<location>[\s\S]+?)\n\s*Tel: *(?<tel>.+)/i", $table[0], $mat)) {
                    $r->pickup()
                        ->location($this->nice($mat['location']))
                        ->phone($mat['tel'])
                        ->date(strtotime($mat['date'].', '. $mat['time']));
                }

                //dropoff
             if (preg_match("/By (?<time>\d{1,2}:\d{2}(?:\s*[ap]m)?) on (?<date>.+)\s+at\s+(?<location>[\s\S]+?)\n\s*Tel: *(?<tel>.+)/i", $table[1], $mat)) {
                    $r->dropoff()
                        ->location($this->nice($mat['location']))
                        ->phone($mat['tel'])
                        ->date(strtotime($mat['date'].', '. $mat['time']));
                }
            }
        }

        $confNoTexts = ['Reference', 'Booking Code', 'Confirmation Ref'];

        foreach ($confNoTexts as $confNoText) {
            if (!empty($confNo = $this->re("#\n[ ]*{$confNoText}:\s+\#?(.+?)(?::|\n|$)#", $detailsBlock))) {
                $r->general()
                    ->confirmation($confNo, $confNoText);
            }
        }

        if (preg_match("#Vehicle details:\s*(?:.\s+)?(.+)\s+\-\s+(.+ or similar)\n#iu", $otherBlock ?? '', $m)
            || preg_match("#Vehicle details:\s*(?:.\s+)?(.+) +\- +(.+ or similar)\n#iu", $detailsBlock, $m)
        ) {
            $r->car()
                ->type($m[1])
                ->model($m[2]);
        }

        return true;
    }

    private function parseCar_3($textPDF, Email $email)
    {
        $this->logger->debug('car 3');

        $date = strtotime($this->re("#(.+)#", $textPDF));

        $r = $email->add()->rental();
        $r->general()
            ->confirmation($this->re("/\s+Confirmed: *([A-Z\d]{5,})\n/", $textPDF))
            ->travellers($this->pax)
            ->date($this->dateRes);

        if (preg_match("#\n *CAR HIRE(?: {3,}.*)?\n+(?<company>.+?) - (?<type>.+?) - (?<model>.+?) or similar#", $textPDF, $m)) {
            $company = $m[1];

            $r->car()
                ->type($m['type'])
                ->model($m['model']);
        }

        if (isset($company)) {
            $r->extra()->company($company);
            $rentalProvider = $this->getRentalProviderByKeyword($company);

            if (!empty($rentalProvider)) {
                $r->program()->code($rentalProvider);
            }
        }

        $detailsBlock = $this->re("/\n( *Pick up at (.*\n+)+?) *LEAD NAME:/", $textPDF);
        if (preg_match("#^(([ ]*Pick up at .*[ ]{4,})Drop off by.+)#", $detailsBlock, $m)
        ) {
            $pos = [0, strlen($m[2])];
            $table = $this->splitCols($detailsBlock, $pos);

            //pickup
            if (preg_match("/Pick up at (?<time>\d{1,2}:\d{2}(?: *[apAP][mM])?) on (?<date>.+?) from\s+(?<address>[\s\S]+?)\n\s*Tel: *(?<tel>.+)/", $table[0], $pu)
            ) {
                $pudate = EmailDateHelper::parseDateRelative($pu['date'], strtotime("-5 days", $date));
                $r->pickup()
                    ->location($this->nice($pu['address']))
                    ->phone($this->nice($pu['tel']))
                    ->date(strtotime($pu['time'], $pudate));
            }
            //dropoff
            if (preg_match("/Drop off by (?<time>\d{1,2}:\d{2}(?: *[apAP][mM])?) on (?<date>.+?) from\s+(?<address>[\s\S]+?)\n\s*Tel: *(?<tel>.+)/", $table[1], $do)
            ) {
                $dodate = EmailDateHelper::parseDateRelative($do['date'], strtotime("-5 days", $date));
                $r->dropoff()
                    ->location($this->nice($do['address']))
                    ->phone($this->nice($do['tel']))
                    ->date(strtotime($do['time'], $dodate));
            }
        }

        return true;
    }

    private function parseHotel_1($textPDF, Email $email)
    {
        $this->logger->debug('hotel 1');
        $date = strtotime($this->re("#(.+)#", $textPDF));

        $h = $email->add()->hotel();
        $h->general()
            ->travellers($this->pax)
            ->date($this->dateRes);

        if (preg_match("# {5,}Confirmed\n#", $textPDF)) {
            $textPDF = str_replace("Confirmed", '', $textPDF);
            $h->general()
                ->status('Confirmed');
        }

        if (!empty($str = $this->re("#Cancellation Policy\s+(.+?)\n\n#s", $textPDF))) {
            $h->general()->cancellation(preg_replace("#\s+#", ' ', $str));
        }

        $r = $h->addRoom();
        $roomType = 'IfNotFoundThenFail';

        if (preg_match("#(?:(?i)ACCOMMODATION)(?: {30,}[^\n]+\n)?\s*([^\n]+)\s+(.+(?:TEL:[^\n]+)?)\n(?:Request room[^\n]*\n)? *(?:In +(?:(\d+))?|(\d+ *x *\d+[^\n]+))#s",
            $textPDF,
            $m)) {
            $roomType = $this->re("#(.+?)(?: {2,}|$)#", $m[1]);
            $r->setType($roomType);
            $couldBeConfNo = $this->re("#.+? {2,}([\w\-\/]+)[ ]*$#", $m[1]);

            if (empty($couldBeConfNo)) {
                $price = $this->re("#.+? {2,}([\d\.\,]+)[ ]*$#", $m[1]);
            }

            if (preg_match("#([^\n]+)\ns*(.+)\s+TEL: ([\+\-\)\(\d ]{5,})#s", $m[2], $v)) {
                $h->hotel()
                    ->name($this->re("#(.+?)(?: {2,}|$)#", $v[1]))
                    ->address($this->nice($v[2]))
                    ->phone(trim($v[3]));
                $couldBeConfNo = $this->re("#.+? {2,}([\w\-\/]+)[ ]*$#", $v[1]);

                if (empty($couldBeConfNo)) {
                    $price = $this->re("#.+? {2,}([\d\.\,]+)[ ]*$#", $v[1]);
                }
            } elseif (preg_match("#^\s*([A-Z\d\W\s]+)\n\s*(.+)#s", $m[2], $v)) {

                $h->hotel()
                    ->name($this->nice(preg_replace("/^( *\S.+?) {5,}\S.*/m", '$1', $v[1])));

                if (preg_match("/^([\s\S]+)\n([\+\-\)\(\d ]{5,})(?:\n\n+[^\n]+)?\s*$/", $v[2], $addr)) {
                    $h->hotel()
                        ->address($this->nice($addr[1]))
                        ->phone($this->nice($addr[2]))
                    ;
                } else {
                    $h->hotel()
                        ->address($this->nice($v[2]));
                }
                $couldBeConfNo = $this->re("#.+? {2,}([\w\-\/]+)[ ]*$#", $v[1]);

                if (empty($couldBeConfNo) && !isset($price)) {
                    $price = $this->re("#.+? {2,}([\d\.\,]+)[ ]*$#", $v[1]);
                }
            } elseif (strpos($m[2], "\n") === false) {
                $h->hotel()
                    ->name($m[2]);

                if (!empty($this->parsedHotels) && in_array($m[2], array_keys($this->parsedHotels))) {
                    if (isset($this->parsedHotels[$m[2]]['address']) && !empty($this->parsedHotels[$m[2]]['address'])) {
                        $h->hotel()
                            ->address($this->parsedHotels[$m[2]]['address']);
                    }

                    if (isset($this->parsedHotels[$m[2]]['phone']) && !empty($this->parsedHotels[$m[2]]['phone'])) {
                        $h->hotel()
                            ->phone($this->parsedHotels[$m[2]]['phone']);
                    }
                } else {
                    $h->hotel()
                        ->noAddress();
                }
            }

            if (isset($m[3]) && !empty($m[3])) {
                $h->booked()
                    ->rooms($m[3]);
            }

            if (isset($m[4]) && !empty($m[4]) && preg_match("#(\d+) *x *(\d+) +ADULTS +rooms#", $m[4], $v)) {
                $h->booked()
                    ->rooms($v[1]);
                $h->booked()->guests($v[1] * $v[2]);
            }
        }
        $hotelName = $h->getHotelName();

        if (!empty($hotelName) && !in_array($hotelName, array_keys($this->parsedHotels))) {
            $this->parsedHotels[$hotelName] = [
                'address' => $h->getAddress(),
                'phone'   => $h->getPhone(),
            ];
        }
        $roomTypeReg = preg_quote($roomType);
        $node = $this->re("#Reservation:\s+(.+?)\s+{$roomTypeReg}#is", $textPDF);

        if (!empty($node)) {
            $resNos = array_filter(array_map("trim", explode("\n", $node)), function ($s) {
                return preg_match("#^[\w\-]+$#", $s);
            });

            if (count($resNos) === $h->getRoomsCount()) {
                $r->setConfirmation(array_shift($resNos));

                foreach ($resNos as $cn) {
                    $r = $h->addRoom();
                    $r->setType($roomType);
                    $r->setConfirmation($cn);
                }
            } else {
                $resNos = array_unique($resNos);

                foreach ($resNos as $cn) {
                    $h->general()
                        ->confirmation($cn);
                }
            }
        } else {
            $confNo = $this->re("#(?:Supplier Reference|Booking code): +(.+)#", $textPDF);

            if (preg_match_all("#([\w\-\/]+)#", $confNo, $m) && count($m[1]) > 1) {
                $h->general()
                    ->noConfirmation();

                foreach ($m[1] as $i => $cn) {
                    if ($i !== 0) {
                        $r = $h->addRoom();
                    }
                    $r->setType($roomType);
                    $r->setConfirmation($cn);
                }
            } elseif (!empty($confNo)) {
                $h->general()
                    ->confirmation($confNo);
            } elseif (isset($couldBeConfNo) && !empty($couldBeConfNo)) {
                $h->general()
                    ->confirmation($confNo);
            } else {
                $h->general()
                    ->noConfirmation();
            }
        }

        if (preg_match("#^ *In +(?:a )?(\d+) *(.+)#m", $textPDF, $m)) {
            if ($g = $this->re("#x (\d+) Adults?#", $m[2])) {
                $h->booked()
                    ->guests($h->getRoomsCount() * $g);
            } elseif ($m[1] === '1' && (stripos($m[2], 'adult') === false)) {
                $h->booked()
                    ->rooms($m[1]);
                $r->setDescription($m[2]);
            } elseif (preg_match("#^adult#i", $m[2])) {
                $h->booked()
                    ->guests($m[1]);
            }
        }

        if (preg_match("#\n[^\n]*(?:(?-i)In|For)[^\n]* (\d+) Child(?:ren)?#i", $textPDF, $m)) {
            $h->booked()->kids($m[1]);
        }

        // For 8 nights In: 07 SEP Out: 15 SEP
        if (preg_match("#^ *For \d+ .+? In: (.+) Out: (.+)#m", $textPDF, $m)) {
            $h->booked()
                ->checkIn(strtotime($m[1], $date))
                ->checkOut(strtotime($m[2], $date));
        }

        if (!empty($time = $this->re("#Checkout required by (\d+:\d+) h#",
                $textPDF)) && !empty($h->getCheckOutDate())
        ) {
            $h->booked()
                ->checkOut(strtotime($time, $h->getCheckOutDate()));
        }

        if (!empty($time = $this->re("#(?:Check-in|Checkin from)[\. ]+(\d+:\d+(?:\s*[ap]m)?)#i",
                $textPDF)) && !empty($h->getCheckInDate())
        ) {
            $h->booked()
                ->checkIn(strtotime($time, $h->getCheckInDate()));
        }

        if (isset($price)) {
            $h->price()
                ->cost(PriceHelper::cost($price));
        }

        if (!empty($node = $h->getCancellation())) {
            $this->detectDeadLine($h, $node);
        }

        return true;
    }

    private function parseHotel_2($textPDF, Email $email)
    {
        $this->logger->debug('hotel 2');
        $infoBlock = strstr($textPDF, 'Booking Details', true);
        $detailsBlock = strstr($textPDF, 'Booking Details');

        if (empty($infoBlock)) {
            $this->logger->debug('other format Hotel_2');

            return false;
        }
        $infoBlock = strstr($infoBlock, 'ACCOMMODATION');

        if (!empty($str = strstr($detailsBlock, 'Other Information', true))) {
            $otherBlock = strstr($detailsBlock, 'Other Information');
            $detailsBlock = $str;
        }
        $date = strtotime($this->re("#(.+)#", $textPDF));

        $h = $email->add()->hotel();

        if (isset($detailsBlock) && !empty($paxStr = strstr($detailsBlock, 'For Passengers:'))
            && preg_match("#For Passengers:\s+([^:]+)\n[ ]*[^:]+:#", $paxStr, $m)
        ) {
            $pax = array_filter(array_map("trim", explode("\n", $m[1])));

            if (in_array("All Passengers", $pax)) {
                $h->general()->travellers($this->pax);
            } else {
                $h->general()->travellers(preg_replace("/^\s*(MR|MRS|MISS|MS|MSTR|DR)\s+/i", '', $pax));
            }
        } else {
            $h->general()->travellers($this->pax);
        }

        $h->general()->date($this->dateRes);

        if (preg_match("#ACCOMMODATION\s+([^\n]+)\s+(?:Address\s+)?(.+)\s+Tel: *([^\n]+)#s",
            $infoBlock,
            $m)) {
            $h->hotel()
                ->name($m[1])
                ->address($this->nice($m[2]))
                ->phone(trim($m[3]));
        } elseif (preg_match("#ACCOMMODATION\s+([^\n]+)\s+Address\s+(.+?)(?:(Tel)|$)#s", $infoBlock, $m)) {
            $h->hotel()
                ->name($m[1])
                ->address($this->nice($m[2]));

            if (isset($m[3])) {
                $this->logger->debug("it can parse phone of hotel");

                return false;
            }
        } elseif (preg_match("#ACCOMMODATION\s+([^\n]+)\s*$#s", $infoBlock, $m)) {
            $h->hotel()
                ->name($m[1]);

            if (preg_match("/\n *Address: +(.+)/", $textPDF, $mat)) {
                $h->hotel()
                    ->address($mat[1]);
            } elseif (!empty($this->parsedHotels) && in_array($m[1], array_keys($this->parsedHotels))) {
                if (isset($this->parsedHotels[$m[1]]['address']) && !empty($this->parsedHotels[$m[1]]['address'])) {
                    $h->hotel()
                        ->address($this->parsedHotels[$m[1]]['address']);
                }

                if (isset($this->parsedHotels[$m[1]]['phone']) && !empty($this->parsedHotels[$m[1]]['phone'])) {
                    $h->hotel()
                        ->phone($this->parsedHotels[$m[1]]['phone']);
                }
            } else {
                $h->hotel()
                    ->noAddress();
            }
        }
        $hotelName = $h->getHotelName();
        $hotelName = preg_replace("/ for \d+ nights?\s*$/i", '', $hotelName);
        $h->hotel()->name($hotelName);

        if (!empty($hotelName) && !in_array($hotelName, array_keys($this->parsedHotels))) {
            $this->parsedHotels[$hotelName] = [
                'address' => $h->getAddress(),
                'phone'   => $h->getPhone(),
            ];
        }
        $confNo = $this->re("#(?:Reference|Booking Code|Confirmation Ref):\s+\#?(.+)#", $detailsBlock);

        if (preg_match("#(confirmed) \#\s*([\w\-]{5,})$#i", $confNo, $m)) {
            $h->general()
                ->confirmation($m[2])
                ->status($m[1]);

            if (!empty($confNo2 = $this->re("#Booking Code:\s+(.+)#", $detailsBlock)) && $confNo2 !== $confNo) {
                $h->general()
                    ->confirmation($confNo2);
            }
        } elseif (preg_match('/(\w+)[ ]*\(/', $confNo, $m)) {
            $h->general()
                ->confirmation($m[1]);
        } else {
            if (empty($confNo)) {
                $confNos = array_filter(array_map("trim",
                    explode("\n", $this->re("#References:\s+\#?([^:]+?)(?:\n[ ]*\n|$)#s", $detailsBlock))));

                if (!empty($confNos)) {
                    foreach ($confNos as $confNo) {
                        $h->general()->confirmation($confNo);
                    }
                } else {
                    $h->general()->noConfirmation();
                }
            } else {
                $h->general()
                    ->confirmation(str_replace(" ", '-', $confNo));
            }
        }
        $room = $h->addRoom();
        $room->setType($this->re("#Room (?:Name|Type): +(.+)#", $detailsBlock));

        $h->booked()
            ->checkIn(strtotime(str_replace('from', '', $this->re("#Check[- ]?in: +(.+?) *(?:hrs|\n)#", $detailsBlock))))
            ->checkOut(strtotime($this->re("#Check[- ]?out: +(.+?) *(?:hrs|\n)#", $detailsBlock)));

        $rooming = $this->re("#Rooming:[ ]+([^:]+?)\s+Meals:#s", $detailsBlock);
        if (empty($rooming)) {
            $rooming = $this->re("#Rooming:[ ]+([^:]+?)\n *[[:alpha:] ]+: +\S+#s", $detailsBlock);
        }

        if (preg_match_all("#^[ ]*(\d{1,3}) x (\d{1,3}) Adults?(?: Rooms?$|, (\d+) Child(?:ren) Room$)#im", $rooming, $matches, PREG_SET_ORDER)) {
            // it-44262649.eml
            // 2 x 3 Adults Rooms
            $roomsCount = 0;
            $adultsCount = 0;
            $kidsCount = 0;
//            1 x 2 Adults Room
//            1 x 2 Adults, 2 Children Room

            foreach ($matches as $m) {
                $roomsCount += $m[1];
                $adultsCount += $m[1] * $m[2];

                if (isset($m[3])) {
                    $kidsCount += $m[3];
                }
            }
            $h->booked()
                ->rooms($roomsCount)
                ->guests($adultsCount)
            ;

            if (!empty($kidsCount)) {
                $h->booked()->kids($kidsCount);
            }
        } elseif (!empty($rooming)) {
            // it-34141370.eml
            $h->booked()->guests($this->re("#\b(\d{1,3}) ADULT#i", $rooming));
            $kids = $this->re("#\b(\d{1,3}) CHILD#i", $rooming); //??? guess text

            if ($kids !== null) {
                $h->booked()->kids($kids);
            }
        }

        if (isset($otherBlock)) {
            if (!empty($time = $this->re("#Check-in[\. :]+(\d+[\.:]\d+(?:\s*[ap]m)?)#i",
                    $otherBlock)) && !empty($h->getCheckInDate())
            ) {
                $h->booked()
                    ->checkIn(strtotime(str_replace(".", ":", $time), $h->getCheckInDate()));
            } elseif (!empty($time = $this->re("#Check in(?: time)?[: ]+(\d+:\d+(?:\s*[ap]m)?)#i",
                    $otherBlock)) && !empty($h->getCheckInDate())
            ) {
                $h->booked()
                    ->checkIn(strtotime($time, $h->getCheckInDate()));
            }
        }

        if (!empty($node = $h->getCancellation())) {
            $this->detectDeadLine($h, $node);
        }

        return true;
    }

    private function detectDeadLine(\AwardWallet\Schema\Parser\Common\Hotel $h, string $cancellationText)
    {
        //Here we describe various variations in the definition of dates deadLine
        if (preg_match("#If cancelled on or after (\d+)\s*(\w+)\s*(\d{2}) a [\d\,\.]+ fee applies#i",
            $cancellationText, $m)
        ) {
            $h->booked()
                ->deadline2($m[1] . ' ' . $m[2] . ' 20' . $m[3]);
        }
    }

    private function parseFlight_1($textPDF, Email $email)
    {
        $this->logger->debug('flight 1');
        $date = strtotime($this->re("#(.+)#", $textPDF));

        $f = $email->add()->flight();
        $f->general()
            ->travellers($this->pax)
            ->date($this->dateRes);

        if (preg_match("# {5,}Confirmed[ ]*\n#", $textPDF)) {
            //Reservation Number: Jetstar BCCGNI  |  Reservation Number: BCCGNI
            $f->general()
                ->confirmation($this->re("#(?:PNR Number|Reservation Number): +(?:[A-z ]+?)?\b([A-Z\d]{5,})#", $textPDF))
                ->status('Confirmed');
        } else {
            $f->general()
                ->noConfirmation();
        }

        $s = $f->addSegment();

        $depTime = $this->re("#Flight - Departing\s+(\d+:\d+(?:\s*[AaPp][Mm])?)#", $textPDF);
        $arrTime = $this->re("#Flight - Arriving\s+(\d+:\d+(?:\s*[AaPp][Mm])?)#", $textPDF);
        $s->extra()
            ->cabin($this->re("#Cabin Type: +(.+)#", $textPDF), false, true)
            ->bookingCode($this->re("#Booking Class: +\b([A-Z]{1,2})\b#", $textPDF), false, true);

        if (preg_match("#{$this->opt($this->t('Depart'))} +(.+) on (.+?)\s+flight ([A-Z\d][A-Z]|[A-Z][A-Z\d])\s*(\d+)#",
            $textPDF, $m)) {
            $s->airline()
                ->name($m[3])
                ->number($m[4]);

            if (preg_match("#(.+)\s*Terminal +(.+)#", $m[1], $v)) {
                $s->departure()
                    ->name($v[1])
                    ->terminal($v[2]);
            } else {
                $s->departure()
                    ->name($m[1]);
            }
        }

        if (preg_match("#Operated by +(.+)#", $textPDF, $m)) {
            $s->airline()->operator($m[1]);
        }

        $s->departure()
            ->noCode()
            ->date(strtotime($depTime, $date));

        if (empty($s->getDepTerminal()) && !empty($dTerm = $this->re("#Departure Terminal: +(\S.*)#", $textPDF))) {
            $s->departure()->terminal($dTerm);
        }

        if (preg_match("#{$this->opt($this->t('Arrive'))} +(.+)\s*Terminal +(.+)#", $textPDF, $v)) {
            $s->arrival()
                ->name($v[1])
                ->terminal($v[2]);
        } else {
            $s->arrival()
                ->name($this->re("#{$this->opt($this->t('Arrive'))} +(.+)#", $textPDF));
        }
        $s->arrival()
            ->noCode()
            ->date(strtotime($arrTime, $date));

        if (empty($s->getArrTerminal()) && !empty($dTerm = $this->re("#Arrival Terminal: +(\S.*)#", $textPDF))) {
            $s->arrival()->terminal($dTerm);
        }

        return true;
    }

    private function parseFlight_2($textPDF, Email $email)
    {
        $this->logger->debug('flight 2');

        $infoBlock = strstr($textPDF, 'Flight Details', true);
        $detailsBlock = strstr($textPDF, 'Flight Details');

        if (empty($infoBlock)) {
            $this->logger->debug('other format Flight_2');

            return false;
        }

        if (strpos($detailsBlock, 'Arrives') !== false && strpos($detailsBlock, 'FLIGHT') !== false) {
            $strArr = $this->re("/(.+)\n[ ]+FLIGHT\n/", $detailsBlock);
            $infoBlock .= "\n" . strstr($detailsBlock, $strArr);
            $detailsBlock = strstr($detailsBlock, $strArr, true);
        }
        $infoBlock = strstr($infoBlock, 'FLIGHT');
        $date = strtotime($this->re("#(.+)#", $textPDF));

        $confNo = $this->re("#Airline Reference: +[A-Z\d]{2}\/([A-Z\d]{5,})#", $detailsBlock);

        if (empty($confNo)) {
            $confNo = $this->re("#PNR: +.+? ([A-Z\d]{5,})#", $detailsBlock);
        }

        $airline = $flightNumber = $operator = $airportDep = $dateDep = $terminalDep = $airportArr = $dateArr = $terminalArr = null;

        if (preg_match("#FLIGHT\s+[^\n]+? ([A-Z\d][A-Z]|[A-Z][A-Z\d]) *(\d+)(?:\s*Operated by (.+))?\s*\n( *.[\s\S]+)#",
            $infoBlock, $m)
        ) {
            $airline = $m[1];
            $flightNumber = $m[2];
            $operator = empty($m[3]) ? null : $m[3];

            $table = $this->splitCols($m[4], $this->colsPos($this->re("#(.+)#", $m[4])));

            if (count($table) !== 1 && count($table) !== 2) {
                $this->logger->debug('other format table info Flight_2');

                return false;
            }

            if (preg_match("#Departs\s+(.+)\s+Departure Time:\s+(\d+:\d+(?:\s*[AaPp][Mm])?)\s*(?:Terminal:\s+(.+?))?\s*(?:\n\n|$)#s",
                $table[0], $v)
            ) {
                $airportDep = $this->nice($v[1]);
                $dateDep = empty($date) ? null : strtotime($v[2], $date);
                $terminalDep = empty($v[3]) ? null : $this->nice($v[3]);
            }

            if (preg_match("#Arrives\s+(.+)\s+Arrival Time:\s+(\d+:\d+(?:\s*[AaPp][Mm])?)\s*(?:Terminal:\s+(.+?))?\s*(?:\n\n|$)#s",
                empty($table[1]) ? $table[0] : $table[1], $v)
            ) {
                $airportArr = $this->nice($v[1]);

                if (isset($strArr) && strtotime($strArr)) {
                    $date = strtotime($strArr);
                }
                $dateArr = empty($date) ? null : strtotime($v[2], $date);
                $terminalArr = empty($v[3]) ? null : $this->nice($v[3]);
            }
        }

        if ($this->currentFlightSegment !== null
            && $airline !== null && $this->currentFlightSegment->getAirlineName() === $airline
            && $flightNumber !== null && $this->currentFlightSegment->getFlightNumber() === $flightNumber
            && empty($this->currentFlightSegment->getArrName())
        ) {
            // it-44262649.eml
            $this->currentFlightSegment->arrival()
                ->name($airportArr)
                ->date($dateArr)
                ->terminal($terminalArr, false, true)
                ->noCode();

            return true;
        }

        $this->currentFlight = $email->add()->flight();
        $this->currentFlightSegment = $this->currentFlight->addSegment();

        $this->currentFlightSegment->airline()
            ->name($airline)
            ->number($flightNumber);

        if ($operator) {
            $this->currentFlightSegment->airline()->operator($operator);
        }

        $this->currentFlightSegment->departure()
            ->name($airportDep)
            ->date($dateDep)
            ->terminal($terminalDep, false, true)
            ->noCode();

        if (!empty($airportArr)) {
            $this->currentFlightSegment->arrival()
                ->name($airportArr)
                ->date($dateArr)
                ->terminal($terminalArr, false, true)
                ->noCode();
        }

        $this->currentFlightSegment->extra()
            ->cabin($this->re("#Cabin Name: +(.+)#", $textPDF), false, true)
            ->bookingCode($this->re("#Booking Class: +\b([A-Z]{1,2})\b#", $textPDF), false, true);

        $this->currentFlight->general()
            ->date($this->dateRes)
            ->travellers($this->pax);

        if (!empty($confNo)) {
            $this->currentFlight->general()->confirmation($confNo);
        } elseif (empty($confNo)) {
            $this->currentFlight->general()->noConfirmation();
        }

        return true;
    }

    private function parseFlight_3($textPDF, Email $email)
    {
        $this->logger->debug('flight 3');
        $date = strtotime($this->re("#(.+)#", $textPDF));

        $f = $email->add()->flight();
        $f->general()
            ->travellers($this->pax)
            ->date($this->dateRes);

        if (preg_match("# {5,}Confirmed[ ]*\n#", $textPDF)) {
            $f->general()
                ->confirmation($this->re("#Airline Reference: +.* *\\/ *([A-Z\d]{5,7})\n#", $textPDF))
                ->status('Confirmed');
        } else {
            $f->general()
                ->noConfirmation();
        }

        $s = $f->addSegment();

        if (preg_match("#{$this->opt($this->t('Depart'))} +(?<name>.+) at (?<time>\d+:\d+(?:\s*[AaPp][Mm])?) on .+?\s+flight (?<airline>[A-Z\d][A-Z]|[A-Z][A-Z\d])\s*(?<number>\d+)(?: {3,}|\n)#",
            $textPDF, $m)) {
            $s->airline()
                ->name($m['airline'])
                ->number($m['number']);

            $s->departure()
                ->noCode()
                ->name($m['name'])
                ->date(strtotime($m['time'], $date));
        }

        if (preg_match("#Operated by +(.+)#", $textPDF, $m)) {
            $s->airline()->operator($m[1]);
        }

        if (preg_match("#{$this->opt($this->t('Arrive'))} +(?<name>.+) at (?<time>\d+:\d+(?:\s*[AaPp][Mm])?)(?: {3,}|\n)#",
            $textPDF, $m)) {
            $s->arrival()
                ->noCode()
                ->name($m['name'])
                ->date(strtotime($m['time'], $date));
        }

        return true;
    }


    private function getTotalCurrency($node)
    {
        $node = str_replace("€", "EUR", $node);
        $node = str_replace("$", "USD", $node);
        $node = str_replace("£", "GBP", $node);
        $tot = '';
        $cur = '';

        if (preg_match("#(?<c>[A-Z]{3})\s*(?<t>\d[\.\d\,\s]*\d*)#", $node, $m)
            || preg_match("#(?<t>\d[\.\d\,\s]*\d*)\s*(?<c>[A-Z]{3})#", $node, $m)
        ) {
            $cur = $m['c'];
            $tot = PriceHelper::cost($m['t']);
        }

        return ['Total' => $tot, 'Currency' => $cur];
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function assignLang($body)
    {
        if (isset($this->reBody)) {
            foreach ($this->reBody as $lang => $reBody) {
                if (stripos($body, $reBody[0]) !== false && stripos($body, $reBody[1]) !== false) {
                    $this->lang = substr($lang, 0, 2);

                    return true;
                }
            }
        }

        return false;
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function splitter($regular, $text)
    {
        $result = [];

        $array = preg_split($regular, $text, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
        array_shift($array);

        for ($i = 0; $i < count($array) - 1; $i += 2) {
            $result[] = $array[$i] . $array[$i + 1];
        }

        return $result;
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
    }

    private function findСutSection($input, $searchStart, $searchFinish)
    {
        $inputResult = null;

        if ($searchStart) {
            $left = mb_strstr($input, $searchStart);
        } else {
            $left = $input;
        }

        if (is_array($searchFinish)) {
            $i = 0;

            while (empty($inputResult)) {
                if (isset($searchFinish[$i])) {
                    $inputResult = mb_strstr($left, $searchFinish[$i], true);
                } else {
                    return false;
                }
                $i++;
            }
        } else {
            if (!empty($searchFinish)) {
                $inputResult = mb_strstr($left, $searchFinish, true);
            } else {
                $inputResult = $left;
            }
        }

        return mb_substr($inputResult, mb_strlen($searchStart));
    }

    private function getRentalProviderByKeyword(?string $keyword)
    {
        if (!empty($keyword)) {
            foreach ($this->keywords as $code => $kws) {
                if (in_array($keyword, $kws)) {
                    return $code;
                }
            }
        }

        return null;
    }

    private function splitCols($text, $pos = false)
    {
        $cols = [];
        $rows = explode("\n", $text);

        if (!$pos) {
            $pos = $this->rowColsPos($rows[0]);
        }
        arsort($pos);

        foreach ($rows as $row) {
            foreach ($pos as $k => $p) {
                $cols[$k][] = trim(mb_substr($row, $p, null, 'UTF-8'));
                $row = mb_substr($row, 0, $p, 'UTF-8');
            }
        }
        ksort($cols);

        foreach ($cols as &$col) {
            $col = implode("\n", $col);
        }

        return $cols;
    }

    private function rowColsPos($row)
    {
        $head = array_filter(array_map('trim', explode("|", preg_replace("#\s{2,}#", "|", $row))));
        $pos = [];
        $lastpos = 0;

        foreach ($head as $word) {
            $pos[] = mb_strpos($row, $word, $lastpos, 'UTF-8');
            $lastpos = mb_strpos($row, $word, $lastpos, 'UTF-8') + mb_strlen($word, 'UTF-8');
        }

        return $pos;
    }

    private function colsPos($table, $correct = 5)
    {
        $pos = [];
        $rows = explode("\n", $table);

        foreach ($rows as $row) {
            $pos = array_merge($pos, $this->rowColsPos($row));
        }
        $pos = array_unique($pos);
        sort($pos);
        $pos = array_merge([], $pos);

        foreach ($pos as $i => $p) {
            if (isset($pos[$i], $pos[$i - 1])) {
                if ($pos[$i] - $pos[$i - 1] < $correct) {
                    unset($pos[$i]);
                }
            }
        }
        sort($pos);
        $pos = array_merge([], $pos);

        return $pos;
    }

    private function nice($str)
    {
        return trim(preg_replace("#\s+#", ' ', $str), ' .-');
    }
}
