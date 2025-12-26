<?php

namespace AwardWallet\Engine\tzell\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Engine\WeekTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

// html parser in amextravel/TicketedItinerary
class ItineraryPdf extends \TAccountChecker
{
    public $mailFiles = "tzell/it-16758341.eml, tzell/it-3041076.eml, tzell/it-3041080.eml, tzell/it-32392541.eml, tzell/it-3759999.eml, tzell/it-3760032.eml, tzell/it-3760484.eml, tzell/it-3763275.eml, tzell/it-3765779.eml, tzell/it-3766100.eml, tzell/it-3767104.eml, tzell/it-3779668.eml, tzell/it-3785055.eml, tzell/it-3785763.eml, tzell/it-3786091.eml, tzell/it-3786589.eml, tzell/it-3786925.eml, tzell/it-3790324.eml, tzell/it-3793875.eml, tzell/it-3793917.eml, tzell/it-3794027.eml, tzell/it-3795378.eml, tzell/it-3797927.eml, tzell/it-3801176.eml, tzell/it-3801177.eml, tzell/it-3802166.eml, tzell/it-3803990.eml, tzell/it-3804485.eml, tzell/it-3809736.eml, tzell/it-3814313.eml, tzell/it-3817483.eml, tzell/it-3824905.eml, tzell/it-3826536.eml, tzell/it-3854371.eml, tzell/it-3858990.eml, tzell/it-3897081.eml, tzell/it-3906237.eml, tzell/it-3928380.eml, tzell/it-3934490.eml, tzell/it-44275141.eml, tzell/it-5592951.eml, tzell/it-5593168.eml, tzell/it-5596687.eml, tzell/it-5676813.eml";

    public static $detectProvider = [
        'ctmanagement' => [
            'from' => ['@travelctm.com'],
            'body' => ['travelctm.com', 'IHS MARKIT'],
        ],
        'uob' => [
            'from' => ['@uobtravel.com'],
            'body' => ['uobtravel.com', 'UOB Travel'],
        ],
        'uniglobe' => [
            'from' => ['southwesttravel.be', '@uniglobe'], //@uniglobealliancetravel.nl
            'body' => ['Uniglobe'],
        ],
        'tzell' => [
            'from' => ['@tzell.com', 'zs.com'],
            'body' => ['Tzell', 'TRAVEL AGENCY USE ONLY'],
        ],
        'royalcaribbean' => [
            'from' => ['@rccl.com'],
            'body' => [],
        ],
        'frosch' => [
            'from' => ['@frosch.com'],
            'body' => ['FROSCH', 'OmegaTravel.com'],
        ],
        'directravel' => [
            'from' => ['@dt.com'],
            'body' => ['Direct Travel'],
        ],
        'aaatravel' => [
            'from' => ['aaane.com'],
            'body' => ['notify AAA of any'],
        ],
        'toneinc' => [
            'from' => ['traveloneinc'],
            'body' => ['Travel One, Inc'],
        ],
        'wtravel' => [
            'from' => ['worldtrav.com', 'globalknowledge.com'],
            'body' => ['Travel One, Inc', 'Global Knowledge Travel Center'],
        ],
        'travelinc' => [
            'from' => ['@worldtvl.com'],
            'body' => ['World Travel Service', 'viewtrip.travelport.com'],
        ],
        'tport' => [
            'from' => ['@stellartravel.com', '@travelport.'],
            'body' => ['viewtrip.travelport.com'],
        ],
        'amextravel' => [// or other without provider code
            'from' => ['@luxetm.com', '@travelwithvista.com', '@accenttravel.com', '@nextgen.com', '@vistat.com', '@traveltrust.com',
                '@casto.com', '@totus.com', '@plazatravel.com', '@sanditz.com', '@montrosetravel.com', '@travelwithvista.com', '@youngstravel.com', '@ALTOUR.COM', 'aspentravel.com', ],
            'body' => ['American Express Travel', 'Traveltrust Corporation', 'ALTOUR ', 'TravelStore', 'Meetings & Incentives Worldwide, Inc.'],
        ],
    ];

    public $lang = "en";

    private $detectSubject = [
        "en"  => "Ticketed itinerary for",
        "en2" => "Eticket/s and itinerary for",
    ];

    private $detectBody = [
        "en" => ["Agency Record Locator:", "Agency Reference Number:", 'Booking locator:', 'Record Locator:', 'Agency Record Locator (For Agency Use Only):'],
    ];

    private static $dictionary = [
        "en" => [
            "Agency Record Locator:" => ["Agency Record Locator:", "Agency Reference Number:", 'Booking locator:', 'Record Locator:', 'Agency Record Locator (For Agency Use Only):'],
            "Ticket Nbr"             => ["Ticket Nbr", "Ticket Number"],
            "Operated By:"           => ["Operated By:", "OPERATED BY"],
        ],
    ];
    private $date = null;
    private $providerCode = '';
    private $cur;
    private $test;

    private $pdfPattern = ".*\.pdf";

    public function parsePdf(Email $email, string $text)
    {
        $patterns = [
            'phone' => '[+(\d][-. \d)(]{5,}[\d)]', // +377 (93) 15 48 52    |    713.680.2992
        ];

        $text = str_ireplace(['&shy;', '&173;', '­'], ' ', $text);

        $pages = preg_split("#\s+Page \d+ of \d+.*\n#", $text); // delete double string after text "Page \d+ of \d+"

        for ($i = 0; $i < count($pages) - 1; $i++) {
            $pageEnds = explode("\n", trim($this->re("#((?:\n.*){7})$#", $pages[$i])));
            $pageNextBegins = explode("\n", $this->re("#^(?:\s*\n)?((?:.*\n){8})#", $pages[$i + 1]));

            if (preg_match("#(?:^|\n)([ ]{0,5}(?:AIR|HOTEL|Rail|CAR)[ ]+.*\n)\n#", implode("\n", $pageNextBegins), $m)
                    && preg_match("#(?:^|\n)" . preg_replace("#[ ]+#", '[ ]+', $m[1]) . "\n#", implode("\n", $pageEnds))) {
                $pageNextBegins = explode("\n", str_replace($m[1] . "\n", $m[1], implode("\n", $pageNextBegins)));
            }

            $nextRows = array_diff(str_replace(' ', '', $pageNextBegins), str_replace(' ', '', $pageEnds));

            $nextRows = array_unique(array_reverse($nextRows, true));
            $next = array_keys($nextRows);
            $nextNew = array_filter($pageNextBegins, function ($v, $k) use ($next) {
                if (empty($v) || in_array($k, $next)) {
                    return true;
                }

                return false;
            }, ARRAY_FILTER_USE_BOTH);
            $pages[$i + 1] = preg_replace("#^((?:.*\n){8})#", implode("\n", $nextNew), $pages[$i + 1]);
            $pages[$i + 1] = "--- next_page ---\n" . $pages[$i + 1];
        }
        $text = implode("\n", $pages);

        $travellersText = trim($this->re("#Passengers:[ ]+(.+\n([ ]{10,}.+?(?:[ ]{2,}.*\n|\n)){0,10})#", $text));
        $travellers = array_filter(array_map(function ($v) {return trim(preg_replace("#\s*\(.*\)\s*$#", '', $v)); }, explode("\n", $travellersText)));

        // Travel Agency
        if (!empty($this->providerCode)) {
            $email->ota()->code($this->providerCode);
        }
        $email->ota()
            ->confirmation(str_replace("­", "-", $this->re("#" . str_replace(" ", "\s*", $this->preg_implode($this->t('Agency Record Locator:'))) . "[ ]*(.+)#", $text)));

        $airs = [];
        $rails = [];
        $hotels = [];
        $cars = [];
        $transfers = [];

        $segments = $this->split("#^[ ]{0,5}((?:AIR|HOTEL|Rail|CAR|MISC|TOUR|OTHER)[ ]+[^\n]+\n)#ms", $text);

        foreach ($segments as $stext) {
            $type = $this->re("#^(\S+)#", $stext);
            // del garbage
            $sub = $this->re("#^(.+?)\n\n(?:FOR \w+|Total \w+|YOUR \w+|OUR \w+|THANK YOU \w+|\*{2,}|FIRST LEVEL CONTACT)#s", $stext);

            if (!empty($sub)) {
                $stext = $sub;
            }

            switch ($type) {
                case 'AIR':
                    $airs[0][] = $stext;

                    if ($rl = $this->re("#Confirmation Number: (.*?)(?:\s{2.}|\n|$)#", $stext)) {
                        $airsConfirmations[] = $rl;
                    } elseif ($rl = $this->re("#Confirmation Number is (.*?)(?:\s{2.}|\n|$)#i", $stext)) {
                        $airsConfirmations[] = $rl;
                    }

                    break;

                case 'HOTEL':
                    $hotels[] = $stext;

                    break;

                case 'Rail':
                    $type = $this->re("#\n[ ]{0,30}(\S.+?)([ ]{2,}.*\n|\n)\s*From:#", $stext);

                    if (!empty($type)) {
                        $provider = [
                            "amtrak" => "Amtrak",
                        ];
                        $findProvider = false;

                        foreach ($provider as $key => $value) {
                            if (stripos($type, $value) !== false) {
                                $rails[$key][] = $stext;
                                $findProvider = true;

                                break;
                            }
                        }

                        if ($findProvider === false) {
                            $rails[0][] = $stext;
                        }
                    } else {
                        $rails[0][] = $stext;
                    }

                    $rl = $this->re("#Confirmation\s*Number:[ ]*(.+?)(?:\s{2,}|\n)#", $text);

                    if (empty($rl)) {
                        $rl = $this->re("#{$this->opt($this->t('Agency Record Locator:'))} (.+)#", $text);
                    }

                    if (!empty($rl)) {
                        $railsComfirmations[] = str_replace("­", "-", $rl);
                    }

                    break;

                case 'CAR':
                    if (preg_match('/(?:\W+LIMO\W+|PICK UP ON)/', $stext)) {
                        $transfers[] = $stext;
                    } else {
                        $cars[] = $stext;
                    }

                    break;

                case 'MISC':
                case 'TOUR':
                case 'OTHER':
                    break;

                default:
                    $this->logger->info("unknown type " . $type);

                    return;
            }
        }

        //###############
        //##   AIRS   ###
        //###############
        // $this->http->log('$airs = '.print_r( $airs,true));
        foreach ($airs as $segments) {
            $f = $email->add()->flight();

            // General
            if (!empty($airsConfirmations)) {
                $airsConfirmations = array_unique($airsConfirmations);

                foreach ($airsConfirmations as $conf) {
                    $f->general()->confirmation($conf);
                }
            } else {
                $f->general()->noConfirmation();
            }
            $f->general()->travellers($travellers);

            // Issued
            if (preg_match_all("/\s+{$this->opt($this->t('Ticket Nbr'))}:[ ]*((?:[A-Z]{2})?\d{10,})\s/", $text, $ticketMatches)) {
                $f->issued()->tickets(array_unique($ticketMatches[1]), false);
            }

            $ffNumbers = [];
            // Segments
            foreach ($segments as $stext) {
                $s = $f->addSegment();

                $tablestr = $table = $this->re("#AIR[^\n]+(?:\s*\n)+(.*?)(?:\n\s*DEPARTS|\n\s*Frequent Flyer Number|\n\s*ARRIVES|\n*\s*Ticket\/Invoice Information|$)#s", $stext);
                $tablestr = preg_replace("#\n\s*Stops[ ]*:[ ]*\d+.*\n.+#", '', $tablestr);

                // $tablestr = preg_replace("/(\S) (Depart: ?\d{1,2}:)/", '$1  $2', $tablestr);
                if (strpos($tablestr, '--- next_page ---') !== false) {
                    $tableStrs = preg_split("#--- next_page ---#", $tablestr);
                    $table = ['', '', ''];

                    foreach ($tableStrs as $str) {
                        $t = $this->splitCols($str, $this->ColsPos($str));

                        if (count($t) > 3) {
                            $this->logger->info("incorrect parse table air");

                            return false;
                        }
                        $table[0] .= $t[0] ?? '';
                        $table[1] .= $t[1] ?? '';
                        $table[2] .= $t[2] ?? '';
                    }
                } else {
                    $table = $this->splitCols($tablestr, $this->ColsPos($tablestr));

                    if (count($table) != 3) {
                        $this->logger->info("incorrect parse table air");

                        return false;
                    }
                }

                $this->logger->debug($stext);

                $date = $this->normalizeDate($this->re("#^AIR\s+(.+)#", $stext));

                if (empty($date)) {
                    $this->logger->info("incorrect parse date");

                    return false;
                }

                if (stripos($stext, 'Arrive:') == false && preg_match("/Page\s*\d+\s*of\s*\d+/", $stext)) {
                    $f->removeSegment($s);

                    continue;
                }

                if (stripos($tablestr, 'Depart:') !== false && stripos($table[1], 'Depart:') === false) {
                    // United Airlines (UA)                                       Flight Number: 2341                 Class: J  Business
                    // From: (IAH) George Bush Intercontinental Houston, TX Depart: 6:15 PM
                    // To: (LAX) Los Angeles CA, USA                              Arrive: 8:03 PM
                    $row = explode("\n", $table[0])[1] . ' ' . explode("\n", $table[1])[1];

                    if (preg_match("/^(.+?) +(Depart ?: ?\d{1,2}:\d{2}.*)/", $row, $m)) {
                        $table[0] = preg_replace("/^(.+\n).+/", '$1' . $m[1], $table[0]);
                        $m[2] = str_replace("Depart :", 'Depart:', $m[2]);
                        $table[1] = preg_replace("/^(.+\n).+/", '$1' . $m[2], $table[1]);
                    }
                }

                $airline = $this->re("#^\s*([^\n]+)#", $table[0]);

                if (preg_match("#\s+\(([A-Z\d][A-Z]|[A-Z][A-Z\d])\)\s*$#", $airline, $m)) {
                    $airline = $m[1];
                }
                // Airline
                $s->airline()
                    ->name($airline)
                    ->number($this->re("#Flight Number:\s+(.+)#", $table[1]))
                    ->operator(preg_replace(['/(.+) (?:AS|DBA) \S.+/s', '/\s+/'], ['$1', ' '], $this->re("#{$this->opt($this->t('Operated By:'))} (.+(?:\n.+)?)\s+From:#i", $table[0])), true, true)
                ;

                // Departure
                unset($dCode);
                $from = $this->re("#From: (.+)#", $table[0]);

                if (preg_match("#\(([A-Z]{3})\) (.+)#", $from, $m)) {
                    $dCode = $m[1];
                    $s->departure()->name($m[2]);
                } else {
                    $s->departure()->name($from);
                }

                if (empty($dCode)) {
                    $dCode = $this->re("#DEPARTS ([A-Z]{3}) #", $stext);
                }

                if (!empty($dCode)) {
                    $s->departure()->code($dCode);
                } else {
                    $s->departure()->noCode();
                }

                $s->departure()
                    ->date($this->normalizeDate($this->re("#Depart: (.+)#", $table[1]), $date))
                    ->terminal(trim(preg_replace("#\s*TERMINAL\s*#", ' ', $this->re("#DEPARTS [A-Z]{3} (.*?TERMINAL.*?)(?:\W+ARRIVES|\n)#", $stext))), true, true)
                ;

                // Arrival
                unset($aCode);
                $to = $this->re("#To: (.+)#", $table[0]);

                if (preg_match("#\(([A-Z]{3})\) (.+)#", $to, $m)) {
                    $aCode = $m[1];
                    $s->arrival()->name($m[2]);
                } else {
                    $s->arrival()->name($to);
                }

                if (empty($aCode)) {
                    $aCode = $this->re("#ARRIVES ([A-Z]{3}) #", $stext);
                }

                if (!empty($aCode)) {
                    $s->arrival()->code($aCode);
                } else {
                    $s->arrival()->noCode();
                }

                $s->arrival()
                    ->date($this->normalizeDate($this->re("#Arrive: (.+)#", $table[1]), $date))
                    ->terminal(trim(preg_replace("#\s*TERMINAL\s*#", ' ', $this->re("#ARRIVES [A-Z]{3} (.*?TERMINAL.*?)\n#", $stext))), true, true)
                ;

                // Extra
                $s->extra()
                    ->aircraft($this->re("#Equipment: (.+)#", $table[0]), true, true)
                    ->miles($this->re("#Miles: \s*(\d+)\s*\/#", $table[2]), true, true)
                    ->duration($this->re("#Duration: (.+)#", $table[1]), true, true)
                    ->meal($this->re("#MEAL: (.+)#", $table[1]), true, true)
                ;
                $cabin = $this->re("#Class: (.+)#u", $table[2]);

                if (preg_match("#^([A-Z]{1,2})[^\w](.+)$#", $cabin, $m)) {
                    $s->extra()
                        ->cabin($m[2])
                        ->bookingCode($m[1])
                    ;
                } else {
                    $s->extra()
                        ->cabin($cabin);
                }

                $seatsText = $this->re("#Seats: (.+)#", $table[0]);

                if (!empty($seatsText) && preg_match_all("#(?:^|,)\s*(\d{1,3}[A-Z])\b#", $seatsText, $m)) {
                    $s->extra()->seats($m[1]);
                }

                if (!empty($this->re("#Stops: (non[\-]*stop)#i", $table[0]))) {
                    $s->extra()->stops(0);
                } elseif ($stops = $this->re("#Stops: (\d)\b#i", $table[0])) {
                    $s->extra()->stops($stops);
                }

                if (preg_match("#\n[ ]*Frequent Flyer Number: ([\s\S]+)#", $stext, $m)
                    && preg_match_all("#^[ ]*([A-Z\d]{7,})(?: applied to | for |$)#m", $m[1], $matches)
                ) {
                    $ffNumbers = array_merge($ffNumbers, $matches[1]);
                }
            }

            if (count($ffNumbers)) {
                $f->program()->accounts(array_unique($ffNumbers), false);
            }
        }

        //#################
        //##   HOTELS   ###
        //#################
//        $this->http->log('$hotels = '.print_r( $hotels,true));

        foreach ($hotels as $htext) {
            $cancellation = trim($this->re('/Hotel cancellation policy:(.+)/', $htext));

            $h = $email->add()->hotel();
            $tablestr = $this->re("#\n([^\n\S]*Number of Rooms:.+)#s", $htext);

            if (strpos($tablestr, '--- next_page ---') !== false) {
                $tableStrs = preg_split("#--- next_page ---#", $tablestr);
                $table = ['', '', ''];

                foreach ($tableStrs as $str) {
                    $t = $this->splitCols($str, $this->ColsPos($str));

                    if (count($this->ColsPos($str)) >= 4) {
                        $t = $this->splitCols($str);
                    }

                    if (count($t) > 2) {
                        $this->logger->info("incorrect parse table air");

                        return false;
                    }
                    $table[0] .= $t[0] ?? '';
                    $table[1] .= $t[1] ?? '';
                    $table[2] .= $t[2] ?? '';
                }
            } else {
                $table = $this->splitCols($tablestr, $this->ColsPos($tablestr));

                if (count($table) != 2) {
                    $tablestr = stristr($tablestr, "Hotel cancellation policy", true);
                    $table = $this->splitCols($tablestr, $this->ColsPos($tablestr));

                    if (count($table) != 2) {
                        $this->logger->info("incorrect parse table hotel");

                        return false;
                    }
                }
            }

            $htext = str_replace("--- next_page ---", "", $htext);

            // General
            $h->general()
                ->confirmation(str_replace(["­", ' '], ["-", ''], $this->re("#Confirmation Number:\s+(?:\/[A-Z]{2}\s+)?((?:[\dA-Z]{2} )?[\w]+)\b#", $table[1])))
                ->travellers($travellers)
            ;

            // Program
            $account = $this->re("#Hotel membership:[ ]+(.+)#", $table[0]);

            if (!empty($account)) {
                $h->program()
                    ->account($account, false);
            }

            // Hotel
            $h->hotel()
                ->name(trim($this->re("#HOTEL[^\n]+\n+([^\n]+)\n#", $htext)))
                ->address(trim($this->re("#HOTEL[^\n]+\n+[^\n]+\n([^\n]+)#", $htext)))
                ->phone($this->re("/^[ ]*Phone:\s*({$patterns['phone']})$/m", $table[0]), false, true)
                ->fax($this->re("/^[ ]*Fax:\s*({$patterns['phone']})$/m", $table[1]), false, true)
            ;

            // Booked
            $h->booked()
                ->checkIn($this->normalizeDate($this->re("#^HOTEL\s+(.+)#", $htext)))
                ->checkOut($this->normalizeDate($this->re("#Check Out:\s+(.+)#", $table[0])))
                ->rooms($this->re("#Number of Rooms:\s+(.+)#", $table[0]))
            ;

            // Rooms
            $roomType = $this->re("/Room Type:[ ]*(.+?)(?:\n|$)/", $table[0]);
            $roomRate = $this->re("/Rate:\s+(.+)/", $table[0]);

            if ($roomType || $roomRate !== null) {
                $room = $h->addRoom();

                if ($roomType) {
                    $room->setType($roomType);
                }

                if ($roomRate !== null) {
                    $room->setRate($roomRate);
                }
            }

            //Price
            $cur = $this->re("#Rate:\s?(?<currency>[^\d)(]+?)\s*\d[,.\'\d]*#", $table[0]);

            if (empty($this->cur) && !empty($cur)) {
                $this->cur = $cur;
            }

            if (preg_match("#^[ ]*Approximate total:[ ]*(.+)$#m", $table[0], $u)
                && (preg_match('/^(?<total>\d[,.\'\d]*)[ ]*(?<currency>[^\d)(]+)?$/', $u[1], $m)
                    || preg_match('/^(?<currency>[^\d)(]+)?[ ]*(?<total>\d[,.\'\d]*)$/', $u[1], $m)
                    || preg_match('/^\D+(?<total>\d[,.\'\d]*)[ ]*(?<currency>[^\d)(]+)?$/', $u[1], $m))
            ) {
                $h->price()->total($m['total']);

                if (!empty($m['currency']) || !empty($this->cur)) {
                    $h->price()->currency($m['currency'] ?? $this->cur);
                }
            }

            //Hotel cancellation policy: CXL 2 DAYS PRIOR TO ARRIVAL
            if (!empty($cancellation)) {
                $this->detectDeadLine($h, $cancellation);
                $h->general()
                    ->cancellation($cancellation);
            }
        }

        //################
        //##   RAILS   ###
        //################
//        $this->http->log('$rails = '.print_r( $rails,true));
        foreach ($rails as $prov => $segments) {
            $t = $email->add()->train();

            // General
            if (!empty($railsComfirmations)) {
                $railsComfirmations = array_unique($railsComfirmations);

                foreach ($railsComfirmations as $conf) {
                    $conf = str_replace(" ", "-", $conf);
                    $t->general()->confirmation(trim($conf));
                }
            } else {
                $t->general()->noConfirmation();
            }

            $t->general()->travellers($travellers);

            if (!empty($prov)) {
                $t->setProviderCode($prov);
            }

            // Segments
            foreach ($segments as $stext) {
                $s = $t->addSegment();

                $tablestr = $table = $this->re("#Rail[^\n]+\n\n(.+)#s", $stext);
                $table = $this->splitCols($tablestr, $this->ColsPos($tablestr));

                if (count($table) != 3) {
                    $this->logger->info("incorrect parse table rail");
                    $email->removeItinerary($t);

                    return;
                }

                $date = $this->normalizeDate($this->re("#^Rail\s+(.+)#", $stext));

                if (empty($date)) {
                    $this->logger->info("incorrect parse date");

                    return false;
                }

                // Departure
                $s->departure()
                    ->name($this->re("#From: (.+)#", $table[0]))
                    ->date($this->normalizeDate($this->re("#Depart: (.+)#", $table[1]), $date))
                ;

                // Arrival
                $s->arrival()
                    ->name($this->re("#To:\s+(.+)#", $table[0]))
                    ->date($this->normalizeDate($this->re("#Arrive: (.+)#", $table[1]), $date))
                ;

                // Extra
                $s->extra()
                    ->number($this->re("#Train Number:\s+(.+)#", $table[1]))
                    ->cabin($this->re("#Class:\s+(.+)#", $table[2]))
                ;
            }
        }

        //###############
        //##   CARS   ###
        //###############
//        $this->http->log('$cars = '.print_r( $cars,true));
        foreach ($cars as $ctext) {
            $r = $email->add()->rental();

            $tablestr = $table = $this->re("#CAR[^\n]+\n(.+)#s", $ctext);

            if (strpos($tablestr, '--- next_page ---') !== false) {
                $tableStrs = preg_split("#--- next_page ---#", $tablestr);
                $table = ['', '', ''];

                foreach ($tableStrs as $str) {
                    $t = $this->splitCols($str, $this->ColsPos($str));

                    if (count($t) > 2) {
                        $this->logger->info("incorrect parse table car");

                        return false;
                    }
                    $table[0] .= $t[0] ?? '';
                    $table[1] .= $t[1] ?? '';
                    $table[2] .= $t[2] ?? '';
                }
            } else {
                $table = $this->splitCols($tablestr, $this->ColsPos($tablestr));

                if (count($table) != 2) {
                    $this->logger->info("incorrect parse table car");

                    return false;
                }
            }

            // General
            $r->general()
                ->confirmation(str_replace("­", "-", $this->re("#Confirmation Number: ([A-Z\d]{5,})\b#", $table[1])))
                ->travellers($travellers)
            ;

            // Program
            $account = $this->re("#Car membership Nbr:[ ]+(.+)#", $table[0]);

            if (!empty($account)) {
                $r->program()
                    ->account($account, false);
            }

            $date = $this->normalizeDate($this->re("#^CAR\s+(.+)#", $ctext));

            // Pick Up
            if (!empty($date)) {
                $time = $this->re("#Pick up Time: (.+)#", $table[1]);

                if (!empty($time)) {
                    $r->pickup()->date($this->normalizeDate($time, $date));
                } else {
                    $r->pickup()->date($date);
                }
            }

            $phone = $this->re("#(?:Phone:|TEL:) ([\d\+\-\(\) ]{5,})#", $table[0]);

            $location = $this->re("#Location: (.+)#", $table[0]);
            $pickup = $this->re("#Pickup: (.+)#", $table[0]);

            if (preg_match("#^\s*([A-Z]{4}\d{2})\s*$#", $pickup)) {
                $r->pickup()->location($location);
            } else {
                $r->pickup()->location($location . ', ' . $pickup);
            }

            if (!empty($phone)) {
                $r->pickup()->phone($phone);
            }
            $fax = $this->re("#FAX: ([\d\+\-\(\) ]{5,})#", $table[0]);

            if (!empty($fax)) {
                $r->pickup()->fax($fax);
            }

            // Drop Off
            if (!empty($date)) {
                $r->dropoff()->date($this->normalizeDate($this->re("#Return: (.+)#", $table[1]), $date));
            }

            $dropoff = $this->re("#Drop Off: (.+)#", $table[0]);

            if (!empty($dropoff)) {
                if ($pickup === $dropoff) {
                    $r->dropoff()->same();
                } else {
                    $r->dropoff()->location($dropoff);
                }
            }

            // Car
            $r->car()
                ->type($this->re("#Type: (.+)#", $table[0]))
            ;

            $r->extra()
                ->company($this->re("#(?:^|\n)\s*(.+)\s*\n\s*Pickup:#", $table[0]));

            //Price
            $cur = $this->re("#Rate:\s?(?<currency>[^\d)(]+?)\s*\d[,.\'\d]*#", $table[0]);

            if (empty($this->cur) && !empty($cur)) {
                $this->cur = $cur;
            }

            if (preg_match("#^[ ]*Approximate total:[ ]*(.+)$#m", $table[0], $u)
                && (preg_match('/^(?<total>\d[,.\'\d]*)[ ]*(?<currency>[^\d)(]+)?$/', $u[1], $m)
                    || preg_match('/^(?<currency>[^\d)(]+)?[ ]*(?<total>\d[,.\'\d]*)$/', $u[1], $m)
                    || preg_match('/^\D+(?<total>\d[,.\'\d]*)[ ]*(?<currency>[^\d)(]+)?$/', $u[1], $m))
            ) {
                $r->price()->total($m['total']);
                $r->price()->currency($m['currency'] ?? $this->cur);
            }
        }

        //####################
        //##   TRANSFERS   ###
        //####################
//        $this->http->log('$cars = '.print_r( $cars,true));
        foreach ($transfers as $ctext) {
            $tr = $email->add()->transfer();

            $tablestr = $table = $this->re("#CAR[^\n]+\n(.+)#s", $ctext);

            if (strpos($tablestr, '--- next_page ---') !== false) {
                $tableStrs = preg_split("#--- next_page ---#", $tablestr);
                $table = ['', '', ''];

                foreach ($tableStrs as $str) {
                    $t = $this->splitCols($str, $this->ColsPos($str));

                    if (count($t) > 2) {
                        $this->logger->info("incorrect parse table car");

                        return false;
                    }
                    $table[0] .= $t[0] ?? '';
                    $table[1] .= $t[1] ?? '';
                    $table[2] .= $t[2] ?? '';
                }
            } else {
                $table = $this->splitCols($tablestr, $this->ColsPos($tablestr));

                if (count($table) != 2) {
                    $this->logger->info("incorrect parse table car");

                    return false;
                }
            }

            // General
            $tr->general()
                ->confirmation(str_replace("­", "-", $this->re("#Confirmation Number: ([A-Z\d]{5,})\b#", $table[1])))
                ->travellers($travellers)
            ;

            // Program
            $account = $this->re("#Car membership Nbr:[ ]+(.+)#", $table[0]);

            if (!empty($account)) {
                $tr->program()
                    ->account($account, false);
            }

            $s = $tr->addSegment();
            $date = $this->normalizeDate($this->re("#^CAR\s+(.+)#", $ctext));

            // Departure, Arrival
            if (!empty($date)) {
                $time = preg_replace('/^(\d{1,2})(\d{2})$/', '$1:$2', $this->re("#PICK UP ON .+ AT (\d{1,2}\d{2})\n#", $ctext));

                if (!empty($time)) {
                    $s->departure()
                        ->date($this->normalizeDate($time, $date));
                    $s->arrival()
                        ->noDate();
                }
            }

            if (preg_match("#\n *LOCATION (.+)\n *TO (.+)#", $ctext, $m)) {
                $s->departure()
                    ->name($m[1]);

                if (preg_match("/^\s*([A-Z]{3}) AIRPORT\s*$/", $m[1], $mat)) {
                    $s->departure()
                        ->code($mat[1]);
                }
                $s->arrival()
                    ->name($m[2]);

                if (preg_match("/^\s*([A-Z]{3}) AIRPORT\s*$/", $m[2], $mat)) {
                    $s->arrival()
                        ->code($mat[1]);
                }
            }
            // Extra
            $s->extra()
                ->type($this->re("#Type: (.+)#", $table[0]));

            if (preg_match("#^[ ]*ESTIMATED TOTAL QUOTE[ ]+(.+)$#m", $ctext, $u)
                && (preg_match('/^(?<total>\d[,.\'\d]*)[ ]*(?<currency>[^\d)(]+)?$/', $u[1], $m)
                    || preg_match('/^(?<currency>[^\d)(]+)?[ ]*(?<total>\d[,.\'\d]*)$/', $u[1], $m)
                    || preg_match('/^\D+(?<total>\d[,.\'\d]*)[ ]*(?<currency>[^\d)(]+)?$/', $u[1], $m))
            ) {
                $tr->price()->total($m['total']);
                $tr->price()->currency($m['currency'] ?? $this->cur);
            }
        }

        // price
        $totalAmount = $totalCurrency = $totalTickets = $totalFees = null;
        $priceText = preg_match("#\n[ ]*Ticket/Invoice Information\n+([\s\S]+)#", $text, $m) ? $m[1] : null;

        if (preg_match("#^[ ]*Total Amount:[ ]*(.+)$#m", $priceText, $u)
            && (preg_match('/^(?<amount>\d[,.\'\d]*)[ ]*(?<currency>[^\d)(]+)?$/', $u[1], $m)
                || preg_match('/^(?<currency>[^\d)(]+)?[ ]*(?<amount>\d[,.\'\d]*)$/', $u[1], $m)
                || preg_match('/^\D+(?<amount>\d[,.\'\d]*)[ ]*(?<currency>[^\d)(]+)?$/', $u[1], $m))
        ) {
            // p.total
            $totalAmount = $this->normalizeAmount($m['amount']);
            // p.currency
            if (!empty($m['currency'])) {
                $totalCurrency = $m['currency'];
            }

            if (empty($totalCurrency)
                && preg_match("# Tax:[ ]*\d[,.\'\d]*[ ]*(?<currency>[A-Z]{3})(?: |$)#m", $priceText, $m)
            ) {
                $totalCurrency = $m['currency'];
            }

            // p.cost
            if (preg_match("#^[ ]*Total Tickets:[ ]*(.+)$#m", $priceText, $u)
                && (preg_match('/^(?<amount>\d[,.\'\d]*)[ ]*(?<currency>[^\d)(]+)?$/', $u[1], $m)
                    || preg_match('/^(?<currency>[^\d)(]+)?[ ]*(?<amount>\d[,.\'\d]*)$/', $u[1], $m))
                && (empty($m['currency']) || $m['currency'] === $totalCurrency)
            ) {
                $totalTickets = $this->normalizeAmount($m['amount']);
            }

            // p.tax
            if (preg_match("#^[ ]*Total Fees:[ ]*(.+)$#mi", $priceText, $u)
                && (preg_match('/^(?<amount>\d[,.\'\d]*)[ ]*(?<currency>[^\d)(]+)?$/', $u[1], $m)
                    || preg_match('/^(?<currency>[^\d)(]+)?[ ]*(?<amount>\d[,.\'\d]*)$/', $u[1], $m))
                && (empty($m['currency']) || $m['currency'] === $totalCurrency)
            ) {
                $totalFees = $this->normalizeAmount($m['amount']);
            }
        }

        if (empty($totalCurrency) && !empty($this->cur)) {
            $totalCurrency = $this->cur;
        }

        if ($totalAmount !== null && !empty($totalCurrency)
            && (count($airs) >= 1 || count($rails) >= 1)
        ) {
            foreach ($email->getItineraries() as $key => $it) {
                if ($it->getType() === 'flight' || $it->getType() === 'train') {
                    $email->getItineraries()[$key]->price()
                        ->total($totalAmount)
                        ->currency($totalCurrency);

                    if ($totalTickets !== null) {
                        $email->getItineraries()[$key]->price()->cost($totalTickets);
                    }

                    if ($totalFees !== null) {
                        $email->getItineraries()[$key]->price()->tax($totalFees);
                    }

                    break;
                }
            }
        }

        return $email;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->date = strtotime($parser->getDate());

        $pdfs = $parser->searchAttachmentByName($this->pdfPattern);

        foreach ($pdfs as $pdf) {
            if (($body = \PDF::convertToText($parser->getAttachmentBody($pdf))) === null) {
                return false;
            }

            foreach ($this->detectBody as $lang => $detectBody) {
                foreach ($detectBody as $dBody) {
                    if (strpos($body, $dBody) !== false) {
                        $this->lang = $lang;

                        break 2;
                    }
                }
            }
            $this->parsePdf($email, $body);
        }
        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailFromProvider($from)
    {
        foreach (self::$detectProvider as $code => $values) {
            if (!empty($values['from'])) {
                foreach ($values['from'] as $dFrom) {
                    if (stripos($from, $dFrom) !== false) {
                        $this->providerCode = $code;

                        return true;
                    }
                }
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (empty($headers["from"]) || empty($headers["subject"])) {
            return false;
        }

        $foundFroms = false;

        foreach (self::$detectProvider as $code => $values) {
            if (!empty($values['from'])) {
                foreach ($values['from'] as $dFrom) {
                    if (stripos($headers["from"], $dFrom) !== false) {
                        $foundFroms = true;
                        $this->providerCode = $code;

                        break 2;
                    }
                }
            }
        }

        if ($foundFroms === false) {
            return false;
        }

        foreach ($this->detectSubject as $re) {
            if (stripos($headers["subject"], $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfPattern);

        foreach ($pdfs as $pdf) {
            if (($body = \PDF::convertToText($parser->getAttachmentBody($pdf))) === null) {
                return false;
            }

            foreach (self::$detectProvider as $code => $values) {
                if (empty($this->providerCode) && !empty($values['from'])) {
                    foreach ($values['from'] as $dFrom) {
                        if (stripos($parser->getCleanFrom(), $dFrom) !== false || stripos($body, ltrim($dFrom, '@')) !== false || $this->http->XPath->query("//text()[contains(normalize-space(.), '$dFrom')]")->length > 0) {
                            $this->providerCode = $code;

                            break 2;
                        }
                    }
                }

                if (empty($this->providerCode) && !empty($values['body'])) {
                    foreach ($values['body'] as $dBody) {
                        if (stripos($body, $dBody) !== false) {
                            $this->providerCode = $code;

                            break 2;
                        }
                    }
                }
            }

            if (empty($this->providerCode)) {
                return false;
            }

            foreach ($this->detectBody as $detectBody) {
                foreach ($detectBody as $dBody) {
                    if (strpos($body, $dBody) !== false) {
                        return true;
                    }
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
        return count(self::$dictionary);
    }

    public static function getEmailProviders()
    {
        return array_keys(self::$detectProvider);
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function normalizeDate($instr, $relDate = false)
    {
        //$this->logger->debug($instr);

        if ($relDate === false) {
            $relDate = $this->date;
        }
        $in = [
            "#^[^\s\d]+, (\d+)([^\s\d.,]+) (\d{4})$#", //Wednesday, 27APR 2016
            "#^[^\s\d]+, (\d+)\s*([^\s\d,.]+)[,]?\s*(\d{4})\s*(\d+:\d+(?:\s*[ap]m)?)\s*$#i", //Wednesday, 13 Feb,2019 13:50
            "#^[^\s\d]+,\s*(\d{1,2})/(\d{2})/(\d{2})\s*$#", //Monday, 21/01/19
            "#^\s*(\d{1,2}:\d{2}\s*(?:[ap]m)?)\s+(\d{1,2})/(\d{2})/(\d{2})\s*$#i", //Monday, 21/01/19
            "#^[^\s\d]+,\s*(\d{1,2})/(\d{2})/(\d{4})\s*$#", //Monday, 21/01/2019
        ];
        $out = [
            "$1 $2 $3",
            "$1 $2 $3, $4",
            "$1.$2.20$3",
            "$2.$3.20$4, $1",
            "$1.$2.$3",
        ];
        $str = preg_replace($in, $out, $instr);

        //$this->logger->debug($str);

        if (preg_match("/^(\d{1,2})\.(\d{1,2})\.(\d{4})$/", $str, $m)) {
            if ($m[2] > 12) {
                $str = $m[2] . '.' . $m[1] . '.' . $m[3];
            }
        }

        if (preg_match("#\d+\s+([^\d\s]+)\s+(?:\d{4}|%Y%)#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        foreach ($in as $re) {
            if (preg_match($re, $instr, $m) && isset($m['week'])) {
                $str = str_replace("%Y%", date('Y', $relDate), $str);
                $dayOfWeekInt = WeekTranslate::number1($m['week'], $this->lang);

                return EmailDateHelper::parseDateUsingWeekDay($str, $dayOfWeekInt);
            }
        }

        if (strpos($str, "%Y%") !== false) {
            return EmailDateHelper::parseDateRelative(null, $relDate, true, $str);
        }

        return strtotime($str, $relDate);
    }

    /**
     * Formatting over 3 steps:
     * 11 507.00  ->  11507.00
     * 2,790      ->  2790    |    4.100,00  ->  4100,00    |    1'619.40  ->  1619.40
     * 18800,00   ->  18800.00  |  2777,0    ->  2777.0.
     *
     * @param string $s Unformatted string with amount
     */
    private function normalizeAmount(string $s): string
    {
        $s = preg_replace('/\s+/', '', $s);
        $s = preg_replace('/[,.\'](\d{3})/', '$1', $s);
        $s = preg_replace('/,(\d{1,2})$/', '.$1', $s);

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

    private function rowColsPos($row)
    {
        $row = str_replace('|', '-', $row);
        $head = array_filter(array_map('trim', explode("|", preg_replace("#\s{4,}#", "|", $row))));
        $pos = [];
        $lastpos = 0;

        foreach ($head as $word) {
            $pos[] = mb_strpos($row, $word, $lastpos, 'UTF-8');
            $lastpos = mb_strpos($row, $word, $lastpos, 'UTF-8') + mb_strlen($word, 'UTF-8');
        }

        return $pos;
    }

    private function ColsPos($table, $correct = 5)
    {
        $pos = [];
        $rows = explode("\n", $table);

        foreach ($rows as $row) {
            $pos = array_merge($pos, $this->rowColsPos($row));
        }
        $pos = array_unique($pos);
        sort($pos);
        $pos = array_merge([], $pos);

        foreach ($pos as $i=> $p) {
            for ($j = $i - 1; $j >= 0; $j = $j - 1) {
                if (isset($pos[$j])) {
                    if (isset($pos[$i])) {
                        if ($pos[$i] - $pos[$j] < $correct) {
                            unset($pos[$i]);
                        }
                    }

                    break;
                }
            }
        }

        sort($pos);
        $pos = array_merge([], $pos);

        return $pos;
    }

    private function SplitCols($text, $pos = false)
    {
        $cols = [];
        $rows = explode("\n", $text);

        if (!$pos) {
            $pos = $this->rowColsPos($rows[0]);
        }
        arsort($pos);

        foreach ($rows as $row) {
            foreach ($pos as $k=>$p) {
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

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
    }

    private function preg_implode($field)
    {
        if (!is_array($field)) {
            $field = [$field];
        }

        return '(?:' . implode("|", array_map(function ($v) {return preg_quote($v, '#'); }, $field)) . ')';
    }

    private function detectDeadLine(\AwardWallet\Schema\Parser\Common\Hotel $h, string $cancellationText)
    {
        if (preg_match("/^CXL (\d+) DAYS? PRIOR TO ARRIVAL$/", $cancellationText, $m)
            || preg_match("/^cancel (\d+) days? prior to arrival date$/", $cancellationText, $m)
        ) {
            $h->booked()->deadlineRelative($m[1] . " days");

            return true;
        }

        if (preg_match("/^cancel no refund$/", $cancellationText, $m)
        ) {
            $h->booked()
                ->nonRefundable();

            return true;
        }

        return false;
    }
}
