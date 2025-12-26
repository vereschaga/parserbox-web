<?php

namespace AwardWallet\Engine\orbitwt\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Common\Event;
use AwardWallet\Schema\Parser\Common\FlightSegment;
use AwardWallet\Schema\Parser\Common\Hotel;
use AwardWallet\Schema\Parser\Common\Rental;
use AwardWallet\Schema\Parser\Common\Train;
use AwardWallet\Schema\Parser\Common\Transfer;
use AwardWallet\Schema\Parser\Email\Email;

class Itinerary extends \TAccountChecker
{
    public $mailFiles = "orbitwt/it-1.eml, orbitwt/it-10875355.eml, orbitwt/it-11.eml, orbitwt/it-11683656.eml, orbitwt/it-11683658.eml, orbitwt/it-11683671.eml, orbitwt/it-12.eml, orbitwt/it-13.eml, orbitwt/it-14.eml, orbitwt/it-14004595.eml, orbitwt/it-14465658.eml, orbitwt/it-15.eml, orbitwt/it-1583141.eml, orbitwt/it-18754479.eml, orbitwt/it-2.eml, orbitwt/it-200369354.eml, orbitwt/it-26184039.eml, orbitwt/it-26224635.eml, orbitwt/it-27278563.eml, orbitwt/it-27279008.eml, orbitwt/it-28390441.eml, orbitwt/it-28415003.eml, orbitwt/it-3.eml, orbitwt/it-36361827.eml, orbitwt/it-4.eml, orbitwt/it-5.eml, orbitwt/it-6.eml, orbitwt/it-6198036.eml, orbitwt/it-6198039.eml, orbitwt/it-7.eml, orbitwt/it-788280136.eml, orbitwt/it-8.eml, orbitwt/it-803285839.eml, orbitwt/it-8681078.eml, orbitwt/it-8913060.eml, orbitwt/it-9.eml";

    public $reFrom = [
        "orbit.co.nz", "two10degrees.com", "@jesims.com.au", "corporatetraveller.com",
        "traveltree.com", "executiveedge.com", "travelbeyond.com.au", "travelbeyondgroup.com.au", "mtatravel.com.au", ];
    public $reBody = [
        'en' => ['Itinerary for', 'Passport & Visa Requirements'],
    ];
    public $lang = '';
    public $pdfNamePattern = ".*pdf";
    public static $dict = [
        'en' => [
            'segments'                                         => '(?:Flight\s+Airline|Hotel\s+Hotel Name|Car\s+Car Company|Transfer\s+Transfer Company|Train\s+Train Name|Tour\s+Tour Company|Dinner[ ]{2,}[a-zA-Z ]+?|Cruise\s*Cruise Company\:)',
            'extSegments'                                      => '(?:Comment [^\n]+\n)',
            'ORBIT WORLD TRAVEL AFTER HOURS EMERGENCY SERVICE' => [
                'ORBIT WORLD TRAVEL AFTER HOURS EMERGENCY SERVICE',
                'CTM EMERGENCY AFTER HOURS',
                'TravelEdge Contact Details',
                'Passport Name',
                'Service',
                'Emergency Afterhours',
            ],
            'Rate'         => ['Rate', 'Local Rate'],
            'Cancellation' => ['Cancellation', 'Cancel Conditions'],
        ],
    ];
    private $textPDF;
    private $otaConfirmation;
    private static $bodies = [
        'orbitwt' => [
            'ORBIT WORLD TRAVEL',
            'Hawaiian Invitational Waterpolo',
            'deluxetravelandcruise', //while not this provider in our list
            'spencertravel.com', //while not this provider in our list
            '.ttfn.com.au', //while not this provider in our list
            'bictontravel.com.au', //while not this provider in our list
            'travelmanagers.com.au', //while not this provider in our list
            '@smartflyer.com.au', //while not this provider in our list
            '@bluefullservice.com.au', //while not this provider in our list
            'www.travelusa.co.nz', //while not this provider in our list
            '@traveladvocates.co.nz', //while not this provider in our list
            'anywheretravel.com.au', //while not this provider in our list
            'pulsetravel.com.au', //while not this provider in our list
            '@cbtravel.com.au', //while not this provider in our list
            'Complex Travel - Flat Beds', //while not this provider in our list
            'Reho Travel Pty Ltd', //while not this provider in our list
            'Goldman Travel Corporation Pty Ltd', //while not this provider in our list
        ],
        'stagescreen' => [
            'Stage and Screen Travel',
        ],
        'savenio' => [
            'Savenio',
        ],
        'ctmanagement' => [
            'Corporate Travel Management',
            'CTM EMERGENCY AFTER HOURS',
            'CTM Government',
        ],
        'ctraveller' => [
            'Corporate Traveller Contact',
            'corporatetraveller.com.au',
        ],
        'tedge' => [
            'TravelEdge Pty Ltd',
            'traveledge.com.au',
            'TravelEdge P/L',
        ],
        'traveltree' => [
            'Travel Tree Australasia Pty Ltd',
            'www.traveltree.com.au',
            '@traveltree.com.au',
        ],
        'ctc' => [
            'ctconnections.com',
            'CT Connections',
            'executiveedge.com.au',
            'Executive Edge Travel',
        ],
        'trbeyond' => [
            'travelbeyond.com.au',
            'travelbeyondgroup.com.au',
            'Travel Beyond Group',
        ],
        'tag' => [
            'www.tag-group.com',
            '@tag-group.com',
            ' by TAG\'s Emergency Team',
        ],
        'virtuoso' => [
            'www.fbitravel.com.au',
        ],
        'mta' => [
            'www.mtatravel.com.au',
        ],
    ];
    private $keywords = [
        'hertz' => [
            'HERTZ RENT A CAR',
        ],
        'avis' => [
            'AVIS RENT A CAR',
        ],
        'driveaway' => [
            'DRIVEAWAY HOLIDAYS PTY LTD',
        ],
    ];

    private $pax = [];
    private $currency;

    private $patterns = [
        'travellerName' => '[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]]', // Mr. Hao-Li Huang
        'travellerName2' => '[[:upper:]]+(?: [[:upper:]]+)*[ ]*\/[ ]*(?:[[:upper:]]+ )*[[:upper:]]+', // KOH / KIM LENG MR
    ];

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->textPDF = '';
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        if (isset($pdfs) && count($pdfs) > 0) {
            foreach ($pdfs as $pdf) {
                $this->textPDF = \PDF::convertToText($parser->getAttachmentBody($pdf));

                if ($this->textPDF !== null) {
                } else {
                    continue;
                }

                // Detect Language
                if (!$this->assignLang($this->textPDF)) {
                    $this->logger->debug("Can't determine a language!");

                    continue;
                } else {
                    $this->logger->debug('[LANG]: ' . $this->lang);
                }

                // Detect Provider
                if (null !== ($code = $this->getProviderByText($this->textPDF))) {
                    $email->ota()->code($code);
                    $email->setProviderCode($code);
                    $this->logger->debug('[providerCode]: ' . $code);
                } else {
                    $this->logger->debug("Can't determine providerCode!");

                    continue;
                }

                if (!$this->parseEmail($email)) {
                    return null;
                }
            }
        } else {
            return null;
        }

        $a = explode('\\', __CLASS__);
        $class = end($a);
        $email->setType($class . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (!empty($this->getProviderByText($text)) && $this->assignLang($text) === true) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
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
        return count(self::$dict);
    }

    public static function getEmailProviders()
    {
        return array_keys(self::$bodies);
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

    public function findCutSection($input, $searchStart, $searchFinish)
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

    protected function splitter($regular, $text)
    {
        $result = [];

        $array = preg_split($regular, $text, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
        array_shift($array);

        for ($i = 0; $i < count($array) - 1; $i += 2) {
            $result[] = $array[$i] . $array[$i + 1];
        }

        return $result;
    }

    private function getProviderByText($text)
    {
        foreach (self::$bodies as $code => $criteria) {
            if (count($criteria) > 0) {
                foreach ($criteria as $search) {
                    if (stripos($text, $search) !== false) {
                        return $code;
                    }
                }
            }
        }

        return null;
    }

    private function parseEmail(Email $email)
    {
        $this->otaConfirmation = [];

        $textPDF = $this->textPDF;

//        $this->logger->debug('$textPDF = '.print_r( $textPDF,true));

        $block = $this->re("#[^\n]+(Page\s+\d+\s+of\s+\d+.+?\s{60}\s+\w+\s+\d+\s+\w+\s+\d{4}[^\n]+)#s", $textPDF);

        if (empty($block) && !preg_match("#[^\n]+Page\s+1\s+of\s+\d+#", $textPDF)) {
            $this->logger->debug('other format');

            return $email;
        }

        //del block between pages
        $textPDF = preg_replace("#[^\n]+Page\s+\d+\s+of\s+\d+.+?\s{60}\s+\w+\s+\d+\s+\w+\s+\d{4}[^\n]+#s", '',
            $textPDF);

        $mainInfo = $this->findCutSection($textPDF, $this->t('Itinerary for'),
            $this->t('ORBIT WORLD TRAVEL AFTER HOURS EMERGENCY SERVICE'));
//        if (empty($mainInfo)) {
//            $mainInfo = $this->findСutSection($textPDF, $this->t('Itinerary for'),
//                $this->t('Passport Name'));
//        }

        $passText = $this->re("#^.+\n((([ ]{0,5}\S.*\n)+([ ]+.*\n)?)+)#", $mainInfo);

        if (preg_match_all("#^(\S(?: ?\S)+)(?: {5,}|$)#m", $passText, $m)) {
            $this->pax = array_filter($m[1], function ($s) {
                return $s !== $this->t('Itinerary for');
            });
        }

        if (preg_match("#({$this->opt($this->t('Booking Number'))})[\s:]+([-A-Z\d]+)$#m", $mainInfo, $m)) {
            // B7197
            $this->otaConfirmation[] = ['conf' => $m[2], 'name' => $m[1], 'primary' => true];
        }

        if (preg_match("#({$this->opt($this->t('PNR Reference'))})[ :]+(.+)$#m", $mainInfo, $m)) {
            if (preg_match("#^[-A-Z\d]{5,}[-, A-Z\d]*$#", $m[2])) {
                // 25J9SS, 2MF8ZK, 2X5ZSE, XJ2FD3
                foreach (preg_split('/\s*,\s*/', $m[2]) as $pnr) {
                    $this->otaConfirmation[] = ['conf' => $pnr, 'name' => $m[1], 'primary' => false];
                }
            } elseif (!preg_match("#^{$this->opt($this->t('NO PNR'))}$#", $m[2])) {
                $this->otaConfirmation[] = ['conf' => null, 'name' => $m[1], 'primary' => false];
            }
        }

        if (!empty($phone = trim($this->re("#Tel[\s:]+([\d \(\)\-\+]+)#", $block)))) {
            $email->ota()
                ->phone($phone);
        }

        $amount = $this->re("#Total Booking Cost Inc Pay Direct.*\s{2,}([\d\,\.]+)\n#", $textPDF);
        $this->currency = $this->re("#\s{2,}([A-Z]{3})\s+Total\s*\n#", $textPDF);

        if (!empty($amount) && !empty($this->currency)) {
            if ($email->getPrice() && ($email->getPrice()->getCurrencyCode() === $this->currency || $email->getPrice()->getCurrencySign() === $this->currency)) {
                $email->price()
                    ->total($email->getPrice()->getTotal() + $amount);
            } else {
                $email->price()
                    ->total($amount)
                    ->currency($this->currency);
            }
        }

        $ffInfo = strstr($textPDF, $this->t('Frequent Flyer Numbers'));

        if (!empty($ffInfo) && preg_match_all("#[ ]{3,}FF[_ ]+((?:[A-Z][A-Z\d]|[A-Z\d][A-Z]|.+?)(?:_AIR)? ?[\w\-]{5,})$#m", $ffInfo, $m)) {
            $accounts = array_unique(preg_replace("#_AIR\s*#", '', $m[1]));
        }

        $ticketTravellersName = [];
        $ticketInfo = strstr($textPDF, $this->t('Ticket Numbers'));

        if (preg_match_all("#^\s*TKT[ ]+([A-Z\d]{2})[ ]+(?<tkt>[\dA-Z]{5,}) - (?<name>.+?) -.*- (?<routes>[A-Z]{3}(?:(?:-|//)[A-Z]{3})+)#m", $ticketInfo, $m)) {
            /* Parse section:
                Ticket Numbers
                TKT EY 2994033009 - MADDREN/JESS ANN MS - ADULT - BNE-AUH-ORD
             */
            foreach ($m[0] as $key => $value) {
                $tickets[$m[1][$key]]['routes'][] = $m['routes'][$key];
                $tickets[$m[1][$key]]['routes'] = array_unique($tickets[$m[1][$key]]['routes']);
                $tickets[$m[1][$key]]['tkt'][] = $m['tkt'][$key];
                $ticketTravellersName[$m['tkt'][$key]] = $m['name'][$key];
            }
        }

        $ticketInfoDetail = strstr($textPDF, $this->t('E-TICKETS'));

        if (preg_match_all("#E-TICKET[ ]+(?<airline>[A-Z][A-Z\d]|[A-Z\d][A-Z])\s+(?:.*\n){1,5}\s*TKT[ ]*:[ ]*(?<tkt>[\dA-Z]{5,})[ ]+ISSUED[ ]*:[ ]*\d{1,2}[A-Z]+(?<year>\d{2})\s+(?:.*\n){0,3}.*[ ]+FROMTO[ ]+.+\n(?<flights>(?:\s*\d{1,2}[ ]+.* \d+:\d+ .+)+)#", $ticketInfoDetail, $m)) {
            /*Parse section:
                E-TICKETS
                E-TICKET CX
                ELECTRONIC TICKET RECORD
                INV : PNR: YLZCZV
                TKT: 1602857030119 ISSUED : 16AUG18 IATA : 02355802
                NAME: PATTENDEN/GARY ROY MR FF : QF_AIR 8786757/CX
                CPN A/L FLT CLS DATE FROMTO TIME ST F/BASIS STAT
                1 CX 0142 I 08OCT PERHKG 07:05 OK IAABRAU8 OPEN
             */
            foreach ($m[0] as $key => $value) {
                $flights = array_filter(explode("\n", $m['flights'][$key]));

                foreach ($flights as $flight) {
                    if (preg_match("#^\s*\d{1,2}[ ]+(?<al>[A-Z][A-Z\d]|[A-Z\d][A-Z])[ ]+(?<fn>\d{1,5})[ ]+.+?[ ]+(?<date>\d{1,2}[A-Z]{1,7})[ ]+(?<dName>[A-Z]{3})(?<aName>[A-Z]{3})[ ]+(?<time>\d+:\d+) .+#", $flight, $mat)) {
                        $ticketDetail[$mat['al'] . $mat['fn']]['airline'] = $m['airline'][$key];
                        $ticketDetail[$mat['al'] . $mat['fn']]['dep'] = $mat['dName'];
                        $ticketDetail[$mat['al'] . $mat['fn']]['arr'] = $mat['aName'];
                        $ticketDetailbyAirline[$m['airline'][$key]][] = $m['tkt'][$key];
                    }
                }
                $ticketTravellersName[$m['tkt'][$key]] = $this->re("/\n *NAME: (.+?)(?: +FF *:| {2,}.*|\n)/", $value);
            }
        }

        if (strpos($ticketInfo, 'Pre Pay') !== false) {
            $totalByTicket = [];

            if (preg_match_all("#^ *Ticket +(?<al>[A-Z][A-Z\d]|[A-Z\d][A-Z]) - [^\n]+? (?<total>\d[\d\.]*)$(?:\n.*)?\n +([\d\-]{8,})(?:.+\n){1,2}[ ]*(?<routes>[ A-Z\-]+)\n#m",
                $ticketInfo, $m)) {
                foreach ($m[0] as $key => $value) {
                    $totalInfo[$m['al'][$key]]['routes'][] = $m['routes'][$key];
                    $totalInfo[$m['al'][$key]]['totals'][] = $m['total'][$key];
                }

                foreach ($totalInfo as $airlines => $value) {
                    $total = 0;
                    $countflight = 0;
                    $tRoutes = array_unique($value['routes']);

                    foreach ($tRoutes as $v) {
                        $cf = count(preg_split("#[- ]{2,}#", trim($v))) - 1;

                        if ($cf > 0) {
                            $countflight += $cf;
                        } else {
                            $totalByTicket = [];

                            break 2;
                        }
                    }

                    foreach ($value['totals'] as $v) {
                        $total += (float) $v;
                    }
                    $totalByTicket[$airlines] = ['countFight' => $countflight, 'total' => $total];
                }
            }
        }

        $airs = [];

        $segments = $this->splitter("#^((?:[^\n]*?\w+ +{$this->t('segments')}| +{$this->t('extSegments')}))#mu", $textPDF);

        $airsCount = 0;

        foreach ($segments as $segment) {
            if (preg_match("#^[^\n]*?\w+\s+Flight\s+Airline#", $segment)) {
                if (preg_match("#Airline[\s:]+(?<airlineFull>.+?)\s+Flight\s+(?<airline>[A-Z][A-Z\d]|[A-Z\d][A-Z])?\s*(?<flightNumber>\d+)#", $segment, $m)) {
                    $airline = !empty($m['airline']) ? $m['airline'] : $m['airlineFull'];
                    $flightnumber = $m['flightNumber'];

                    if (isset($ticketDetail[$airline . $flightnumber])) {
                        $airs[$ticketDetail[$airline . $flightnumber]['airline']][$airsCount++] = $segment;
                    } elseif (isset($tickets[$airline])) {
                        $airs[$airline][$airsCount++] = $segment;
                    } else {
                        $airs['unknown'][$airsCount++] = $segment;
                    }
                } else {
                    $airs['unknown'][$airsCount++] = $segment;
                }
            } elseif (preg_match("#^[^\n]*?\w+\s+Hotel\s+Hotel Name#", $segment)) {
                $r = $email->add()->hotel();

                foreach ($this->otaConfirmation as $oc) {
                    $r->ota()->confirmation($oc['conf'], $oc['name'], $oc['primary']);
                }
                $this->parseHotel($r, $segment);
            } elseif (preg_match("#^[^\n]*?\w+\s+Car\s+Car\s+Company#", $segment)) {
                $r = $email->add()->rental();

                foreach ($this->otaConfirmation as $oc) {
                    $r->ota()->confirmation($oc['conf'], $oc['name'], $oc['primary']);
                }
                $this->parseCar($r, $segment);
            } elseif (preg_match("#^[^\n]*?\w+\s+Transfer\s+Transfer\s+Company#", $segment)) {
                $r = $email->add()->transfer();

                foreach ($this->otaConfirmation as $oc) {
                    $r->ota()->confirmation($oc['conf'], $oc['name'], $oc['primary']);
                }
                $this->parseTransfer($r, $segment);
            } elseif (preg_match("#^[^\n]*?\w+\s+Train\s+Train\s+Name#", $segment)) {
                $r = $email->add()->train();

                foreach ($this->otaConfirmation as $oc) {
                    $r->ota()->confirmation($oc['conf'], $oc['name'], $oc['primary']);
                }
                $this->parseTrain($r, $segment);
                $s = $r->getSegments()[0];

                if ($s->getArrDate() - $s->getDepDate() > 2 * 24 * 60 * 60 && preg_match("/^Eurail Italy Pass Promo/", $s->getServiceName())) {
                    $email->removeItinerary($r);
                }
            } elseif (preg_match("#^[^\n]*?\w+\s+Tour\s+Tour\s+Company#", $segment) || preg_match("#^[^\n]*?\w+[ ]+Dinner[ ]{2,}[a-zA-Z ]+?\n[ ]*\d{1,2} \w+ \d{2,4}#", $segment)) {
                $r = $email->add()->event();

                foreach ($this->otaConfirmation as $oc) {
                    $r->ota()->confirmation($oc['conf'], $oc['name'], $oc['primary']);
                }
                $this->parseEvent($r, $segment, $email);
            } elseif (preg_match("#^[^\n]*?\s+Comment#", $segment)) {
                continue;
            } elseif (preg_match("#Cruise#", $segment)) {
                $this->parseCruise($email, $segment);
            } else {
                $this->logger->debug('other format or type segment');

                return false;
            }
        }

        if (isset($tickets) && count($tickets) == 1 && count(reset($tickets)['routes']) == 1) {
            $routes = reset($tickets)['routes'][0];
            $routeSegments = explode("//", $routes);

            foreach ($routeSegments as $routeSegment) {
                $routeAirport = array_map(function ($v) {return preg_match("#^[A-Z]{3}$#", $v) ? $v : false; }, explode("-", $routeSegment));

                if (count($routeAirport) == count(array_filter($routeAirport)) && count($routeAirport) > 1) {
                    for ($i = 0; $i < (count($routeAirport)); $i++) {
                        if (isset($routeAirport[$i + 1])) {
                            $route[] = ['dep'=>$routeAirport[$i], 'arr' => $routeAirport[$i + 1]];
                        }
                    }
                }
            }

            if ($airsCount > 0 && $airsCount === count($route)) {
                $tickets['all']['tkt'] = reset($tickets)['tkt'];
                $tickets['all']['routes'] = reset($tickets)['routes'];
                $airs2 = $airs;
                $airs = [];

                foreach ($airs2 as $value) {
                    foreach ($value as $key => $a) {
                        $airs['all'][$key] = $a;
                    }
                }
                ksort($airs['all']);
            }
        }

        foreach ($airs as $airline => $sTexts) {
            $r = $email->add()->flight();

            foreach ($this->otaConfirmation as $oc) {
                $r->ota()->confirmation($oc['conf'], $oc['name'], $oc['primary']);
            }

            $r->general()->noConfirmation();

            if (!empty($accounts)) {
                foreach ($accounts as $account) {
                    $pax = $this->re("/([[:alpha:]][-.\/'’[:alpha:] ]*[[:alpha:]])[ ]{5,}(?:FF\s*)?\S*\s*$account/", $textPDF);

                    if (!empty($pax)) {
                        if (preg_match("/(?<desc>.+)\s+(?<number>\d+)/", $account, $m)) {
                            $r->addAccountNumber($m['number'], false, preg_replace("/(?:MRS|MR|MS|MSTR|MISS)$/", "", $pax), $m['desc']);
                        } else {
                            $r->addAccountNumber($account, false, preg_replace("/(?:MRS|MR|MS|MSTR|MISS)$/", "", $pax));
                        }
                    } else {
                        $r->addAccountNumber($account, false);
                    }
                }
            }

            if ($airline === 'all') {
                reset($tickets);
                $airline2 = key($tickets);
            } else {
                $airline2 = $airline;
            }

            if (isset($ticketDetailbyAirline[$airline2])) {
                $uniqueTickets = array_unique(array_filter($ticketDetailbyAirline[$airline2], function ($v) {return (preg_match("#^\d{7,}$#", $v)) ? true : false; }));

                foreach ($uniqueTickets as $ut) {
                    $r->issued()->ticket($ut, false, $this->normalizeTraveller($ticketTravellersName[$ut] ?? null));
                }
            } elseif (isset($tickets[$airline2]) && isset($tickets[$airline]['tkt'])) {
                $uniqueTickets = array_filter($tickets[$airline]['tkt'], function ($v) {return (preg_match("#^\d{7,}$#", $v)) ? true : false; });

                foreach ($uniqueTickets as $ut) {
                    $r->issued()->ticket($ut, false, $this->normalizeTraveller($ticketTravellersName[$ut] ?? null));
                }
            }

            $route = [];

            if (!isset($ticketDetail[$airline]) && isset($tickets[$airline]) && isset($tickets[$airline]['routes'])) {
                $routeAirport = [];

                foreach ($tickets[$airline]['routes'] as $routeRow) {
                    $routeSegments = explode("//", $routeRow);

                    foreach ($routeSegments as $routeSegment) {
                        $routeAirport = array_map(function ($v) {return preg_match("#^[A-Z]{3}$#", $v) ? $v : false; }, explode("-", $routeSegment));

                        if (count($routeAirport) == count(array_filter($routeAirport)) && count($routeAirport) > 1) {
                            for ($i = 0; $i < (count($routeAirport)); $i++) {
                                if (isset($routeAirport[$i + 1])) {
                                    $route[] = ['dep'=>$routeAirport[$i], 'arr' => $routeAirport[$i + 1]];
                                }
                            }
                        }
                    }
                }
            }

            $travellers = [];

            if ($airline !== 'all') {
                $sTexts = array_values($sTexts);
            }

            foreach ($sTexts as $key => $sText) {
                $sPassengers = $this->getPassengers($sText);

                if (count($sPassengers)) {
                    $travellers = array_merge($travellers, $sPassengers);
                }

                $s = $r->addSegment();
                $this->parseFlightSegment($s, $sText);

                if (isset($ticketDetail[$s->getAirlineName() . $s->getFlightNumber()])) {
                    $s->departure()->code($ticketDetail[$s->getAirlineName() . $s->getFlightNumber()]['dep']);
                    $s->arrival()->code($ticketDetail[$s->getAirlineName() . $s->getFlightNumber()]['arr']);

                    if (!empty($s->getDepName())) {
                        $airportCodes[$s->getDepName()] = $ticketDetail[$s->getAirlineName() . $s->getFlightNumber()]['dep'];
                    }

                    if (!empty($s->getArrName())) {
                        $airportCodes[$s->getArrName()] = $ticketDetail[$s->getAirlineName() . $s->getFlightNumber()]['arr'];
                    }
                } elseif (count($route) == count($sTexts) || (!empty($tickets) && count($tickets) == 1 && count($route) == $airsCount)) {
                    $s->departure()->code($route[$key]['dep']);
                    $s->arrival()->code($route[$key]['arr']);

                    if (!empty($s->getDepName())) {
                        $airportCodes[$s->getDepName()] = $route[$key]['dep'];
                    }

                    if (!empty($s->getArrName())) {
                        $airportCodes[$s->getArrName()] = $route[$key]['arr'];
                    }
                } else {
                    if ((!empty($s->getDepName())) && isset($airportCodes[$s->getDepName()])) {
                        $s->departure()->code($airportCodes[$s->getDepName()]);
                    } else {
                        $s->departure()->noCode();
                    }

                    if ((!empty($s->getArrName())) && isset($airportCodes[$s->getArrName()])) {
                        $s->arrival()->code($airportCodes[$s->getArrName()]);
                    } else {
                        $s->arrival()->noCode();
                    }
                }
            }

            // travellers
            foreach ((count($travellers) > 0 ? array_unique($travellers) : $this->pax) as $tName) {
                $r->general()->traveller($this->normalizeTraveller($tName), true);
            }

            if (isset($totalByTicket[$airline]) && $totalByTicket[$airline]['countFight'] == count($sTexts)) {
                $r->price()
                    ->total($totalByTicket[$airline]['total'])
                    ->currency($this->currency);
            } elseif ($airline == 'all' && isset($totalByTicket) && count($totalByTicket) == 1) {
                $r->price()
                    ->total(reset($totalByTicket)['total'])
                    ->currency($this->currency);
            }
        }

        if (count($email->getItineraries()) === 1 && $email->getPrice() && $email->getPrice()->getCurrencyCode() && $email->getPrice()->getTotal()) {
            foreach ($email->getItineraries() as $it) {
                if (!$it->getPrice()) {
                    $it->price()
                        ->total($email->getPrice()->getTotal())
                        ->currency($email->getPrice()->getCurrencyCode());
                }
            }
        }

        return true;
    }

    private function parseCruise(Email $email, $text): void
    {
        $this->logger->debug(__FUNCTION__ . '()');
        $c = $email->add()->cruise();

        $travellers = [];
        $travellersText = $this->re("/(?:\n[ ]*|[ ]{2})Passengers:\s+([\s\S]+?)\n+[ ]*(?:.*[ ]{2})?Notes:/i", $text) ?? '';
        
        foreach (preg_split('/(?:[ ]*\n[ ]*)+/', $travellersText) as $tRow) {
            if (preg_match("/^{$this->patterns['travellerName2']}$/u", $tRow, $m)) {
                $travellers[] = $this->normalizeTraveller($tRow);
            } else {
                $travellers = [];

                break;
            }
        }

        if (count($travellers) > 0) {
            $c->general()->travellers(array_unique($travellers), true);
        }

        $conf = $this->re("/(?:^[ ]*|[ ]{2})Booking Reference:[ ]*([-A-z\d]{3,})$/im", $text);

        if (!empty($conf)) {
            $c->general()
                ->confirmation($conf);
        } elseif (!preg_match("/(?:^[ ]*|[ ]{2})Booking Reference:/im", $text)) {
            $c->general()
                ->noConfirmation();
        }

        $c->setRoom($this->re("/(?:^[ ]*|[ ]{2})Cabin No:[ ]*([A-z\d]+)$/im", $text));
        $c->setDescription($this->re("/(?:^[ ]*|[ ]{2})Cruise Name:[ ]*(.{2,})$/im", $text));
        $c->setStatus($this->re("/(?:^[ ]*|[ ]{2})Status:[ ]*(\w[\w ]*)$/im", $text));

        if (preg_match("/(?:^[ ]*|[ ]{2})Embark Date:[ ]*(?<depDate>[-[:alpha:]]+[ ]*\d{1,2}[ ]*[[:alpha:]]+[ ]*\d{2,4})\s+at\s+(?<depTime>\d+:\d+)[ ]+(?<depName>.+)$/mu", $text, $m)) {
            $seg = $c->addSegment();
            $seg->setName($m['depName'])
                ->setAboard(strtotime($m['depDate'] . ', ' . $m['depTime']));
        }

        if (preg_match("/(?:^[ ]*|[ ]{2})Disembark Date:[ ]*(?<arrDate>[-[:alpha:]]+[ ]*\d{1,2}[ ]*[[:alpha:]]+[ ]*\d{2,4})\s+at\s+(?<arrTime>\d+:\d+)[ ]+(?<arrName>.+)$/mu", $text, $m)) {
            $seg = $c->addSegment();
            $seg->setName($m['arrName'])
                ->setAshore(strtotime($m['arrDate'] . ', ' . $m['arrTime']));
        }
    }

    private function parseFlightSegment(FlightSegment $s, $text)
    {
        $this->logger->debug(__FUNCTION__ . '()');

        if (preg_match("#Airline[\s:]+(?<airlineFull>.+?)\s+Flight\s+(?<airline>[A-Z][A-Z\d]|[A-Z\d][A-Z])?\s*(?<flightNumber>\d+)#",
            $text, $m)) {
            if (empty($m['airline'])) {
                $s->airline()->name($m['airlineFull']);
            } else {
                $s->airline()->name($m['airline']);
            }
            $s->airline()->number($m['flightNumber']);
        } elseif (preg_match("#\s+Airline[\s:]+(?<airlineFull>.+?)\s*\n {0,5}\S+(?: ?\S+)* {2,}Departure Date: #", $text, $m)
            && !preg_match("/flight/ui", $m['airlineFull'])
        ) {
            $s->airline()
                ->name($m['airlineFull'])
                ->noNumber()
            ;
        }
        $airlineReference = $this->re("#Airline Reference[\s:]+([A-Z\d]{5,})#", $text);

        if ($airlineReference) {
            $s->airline()
                ->confirmation($airlineReference);
        }

        if (preg_match("#Departure Date[\s:]+(.+?)\s{5,}(.+)#", $text, $m)) {
            $s->departure()
                ->date($this->normalizeDate($m[1]))
                ->name($m[2]);
        }

        if (preg_match("#Arrival Date[\s:]+(.+?)\s{5,}(.+)#", $text, $m)) {
            $s->arrival()
                ->date($this->normalizeDate($m[1]))
                ->name($m[2]);

            if (preg_match("/.{6,}\d:\d{2}/", $m[1])) {
                $s->arrival()
                    ->strict();
            }
        }

        // aircraft
        $s->extra()
            ->aircraft($this->re("#Aircraft[\s:]+(.+)#", $text), false,
                !empty($s->getDepName()) && !empty($s->getArrName()));

        // bookingCode
        // cabin
        if (preg_match("#Class[\s:]+([A-Z]{1,2})\s*\-\s*(.+)#", $text, $m)) {
            $s->extra()
                ->bookingCode($m[1])
                ->cabin($m[2]);
        } elseif (preg_match("#Class[\s:]+(.+)#", $text, $m)) {
            $s->extra()
                ->cabin($m[1]);
        }

        if (preg_match("#Seats[\s:]+(.+)#s", $text, $m)) {
            if (preg_match_all("#\b(\d+[A-Z])\b\s*\-\s*\w+#i", $m[1], $v)) {
                foreach ($v[1] as $seat) {
                    $passengerName = $this->normalizeTraveller($this->re("/(?:[ ]{2}|:[ ]*){$this->opt($seat)}\s+-\s+({$this->patterns['travellerName2']})$/mu", $text));
                    $s->extra()->seat($seat, false, false, $passengerName);
                }
            }
        }

        if (preg_match("#Stops[\s:]+(.+)#", $text, $m)) {
            if (preg_match("#non[\s\-]*stop#i", $m[1])) {
                $s->extra()->stops(0);
            } else {
                $s->extra()->stops($this->re("#(\d+)#", $m[1]));
            }
        }

        if (preg_match("#Details[\s:]+(.+)\s+\(T(?:erminal)?\s*\-\s*([\w\s\-\\/]*)\)\s+(.+)\s+\(T(?:erminal)?\s*\-\s*([\w\s\-\\/]*)\).+?Travelling\s+time[\s:]+#ius",
            $text, $m)) {
            if (!empty($m[2])) {
                $s->departure()->terminal(trim(preg_replace(["#\s*terminal\s*#i", "/\s+/"], ' ', $m[2])));
            }

            if (!empty($m[4])) {
                $s->arrival()->terminal(trim(preg_replace(["#\s*terminal\s*#i", "/\s+/"], ' ', $m[4])));
            }
        } else {
            if ($s->getDepName()
                && preg_match("#{$s->getDepName()}\s+\(T(?:erminal)?\s*\-\s*([\w\s\-\\/]*)\)#ius", $text, $m)
                && !empty($m[1])
            ) {
                $s->departure()->terminal(trim(preg_replace(["#\s*terminal\s*#i", "/\s+/"], ' ', $m[1])));
            }

            if ($s->getArrName()
                && preg_match("#{$s->getArrName()}\s+\(T(?:erminal)?\s*\-\s*([\w\s\-]*)\)#ius", $text, $m)
                && !empty($m[1])
            ) {
                $s->arrival()->terminal(trim(preg_replace(["#\s*terminal\s*#i", "/\s+/"], ' ', $m[1])));
            }
        }

        if (preg_match_all("#Travelling\s*time[\s:]+(\d+[hrs\s]+(?:\s*\d+[mins\s]+)?)(?:[\s\-]+|$)#ius", $text, $m)
                && count($m[0]) == 1) {
            $s->extra()->duration(preg_replace("#\s+#", ' ', $m[1][0]));
        }

        if (preg_match("#[\s\-]+Meal\s+Service[\s:]+([^\n]+)\s*Seats\:#iu", $text, $m)
        || preg_match("#[\s\-]+Meal\s+Service[\s:]+([^\n]+)#ius", $text, $m)) {
            if (!empty(trim($m[1]))) {
                $s->extra()->meal($m[1]);
            }
        }

        return true;
    }

    private function parseTransfer(Transfer $r, $text): void
    {
        $this->logger->debug(__FUNCTION__ . '()');
        $confNo = $this->re("#Booking Reference[\s:]+([A-Z\d]{5,})#", $text);

        if (empty($confNo)) {
            $r->general()
                ->noConfirmation();
        } else {
            $r->general()
                ->confirmation($confNo);
        }
        $r->general()
            ->status($this->re("#Status[\s:]+(.+)#", $text));

        $tot = $this->getTotalCurrency($this->re("#^\s*Rate[\s:]+(.+)#m", $text));

        if (!empty($tot['Total'])) {
            $r->price()
                ->total($tot['Total'])
                ->currency($tot['Currency']);
        } else {
            $tot = $this->getTotalCurrency($this->re("#^\s*Local Rate[\s:]+(.+)#m", $text));

            if (!empty($tot['Total'])) {
                $r->price()
                    ->total($tot['Total'])
                    ->currency($tot['Currency']);
            }
        }

        $s = $r->addSegment();

        // Pick Up
        if (preg_match("#Pick-Up Date[\s:]+(.+?)\s{5,}(.+)#", $text, $m)) {
            $s->departure()
                ->date($this->normalizeDate($m[1]))
                ->name(preg_replace('/^([[:alpha:]][-\'[:alpha:] ]{0,18}[[:alpha:]])\s*,\s*(\1[-\s]+AIRPORT)$/iu', '$2, $1', $m[2]));
        }

        if (preg_match("#Pick-Up Address[\s:]+((?:.+?\n){1,5}?)\s*(?:P-|F-|Status:)#", $text, $m)) {
            $s->departure()
                ->address(preg_replace("#\s*\n\s*#", ', ', trim($m[1])));
        }

        // Drop Off
        if (preg_match("#Drop-off date[\s:]+(.+?)\s{5,}(.+)#", $text, $m)) {
            if (preg_match("#\d+:\d+#", $m[1])) {
                $s->arrival()->date($this->normalizeDate($m[1]));
            } else {
                $s->arrival()->noDate();
            }
            $s->arrival()->name(preg_replace('/^([[:alpha:]][-\'[:alpha:] ]{0,18}[[:alpha:]])\s*,\s*(\1[-\s]+AIRPORT)$/iu', '$2, $1', $m[2]));
        }

        if (preg_match("#Drop-off Address[\s:]+((?:.+?\n){1,5}?)\s*(?:P-|F-|Status:)#", $text, $m) && ($addr = preg_replace(["#\s*\n\s*#", '/\s*OF\s*/'], [', ', ''], trim($m[1]))) && !empty($addr)) {
            $s->arrival()
                ->address($addr);
        }

        // 48 PATON ST, MERRYLANDS WEST, NSW, 2160, Australia
        $patternCountry = "/\D\d{4,}(?:\s*,\s*)+(?<country>[[:alpha:]][-\'[:alpha:] ]{0,18}[[:alpha:]])$/u";

        if (!empty($s->getDepName()) && empty($s->getDepAddress())
            && !empty($s->getArrAddress()) && preg_match($patternCountry, $s->getArrAddress(), $m)
        ) {
            $s->departure()->geoTip($m['country']);
        }

        if (!empty($s->getArrName()) && empty($s->getArrAddress())
            && !empty($s->getDepAddress()) && preg_match($patternCountry, $s->getDepAddress(), $m)
        ) {
            $s->arrival()->geoTip($m['country']);
        }

        // travellers
        $passengers = $this->getPassengers($text);

        foreach ((count($passengers) > 0 ? $passengers : $this->pax) as $tName) {
            $r->general()->traveller($this->normalizeTraveller($tName), true);
        }

        // Transfer Company - perfectly to provider - $r->program()->code(???);
        // but to much unknowns
        // $this->re("#Transfer Company[\s:]+(.+)#", $text);
        // $this->re("#Type[\s:]+(.+)#", $text);
    }

    private function parseTrain(Train $r, $text)
    {
        $this->logger->debug(__FUNCTION__ . '()');
        $confNo = $this->re("#Booking Reference[\s:]+([A-Z\d]{5,})#", $text);

        if (empty($confNo)) {
            $r->general()
                ->noConfirmation();
        } else {
            $r->general()
                ->confirmation($confNo);
        }
        $r->general()
            ->status($this->re("#Status[\s:]+(.+)#", $text));

        $tot = $this->getTotalCurrency($this->re("#^\s*Local\s+Rate[\s:]+(.+)#m", $text));

        if (!empty($tot['Total'])) {
            $r->price()
                ->total($tot['Total'])
                ->currency($tot['Currency']);
        }

        $s = $r->addSegment();

        if (preg_match("#Embark Date[\s:]+(.+?)\s{5,}(.+)#", $text, $m)) {
            $s->departure()
                ->date($this->normalizeDate($m[1]))
                ->name($m[2]);
        }

        if (preg_match("#Disembark Date[\s:]+(.+?)\s{5,}(.+)#", $text, $m)) {
            $s->arrival()
                ->date($this->normalizeDate($m[1]))
                ->name($m[2]);
        }

        if (preg_match("#Disembark Address[\s:]+(.+)#", $text, $m)) {
            $s->arrival()
                ->name(trim(($s->getArrName() ?? '') . ' ' . $m[1]));
        }

        $s->extra()
            ->service($this->re("#Train Name[\s:]+(.+)#", $text))
            ->noNumber();

        // travellers
        $passengers = $this->getPassengers($text);

        foreach ((count($passengers) > 0 ? $passengers : $this->pax) as $tName) {
            $r->general()->traveller($this->normalizeTraveller($tName), true);
        }
    }

    private function parseHotel(Hotel $r, $text)
    {
        $this->logger->debug(__FUNCTION__ . '()');
        // $this->logger->debug('$text = '.print_r( $text,true));
        if ($conf = $this->re("#Booking Reference[\s:\#]+(?:[A-Z\d][A-Z]\/)?([A-Z\d]{3,})#", $text)
        ) {
            // // 26302Y    |    IQ/26302Y
            $r->general()
                ->confirmation($conf);
        } elseif ($conf = $this->re("# {2,}Confirmation *: *([A-Z\d\-]{3,}(?:_[A-Z\d]+)?)\.?\n#", $text)) {
            // Notes:          Confirmation : 259244345_RN
            $r->general()
                ->confirmation($conf);
        } elseif ($conf = str_replace(" ", '-',
            trim($this->re("#Booking Reference[\s:\#]+([A-z \d]{3,})\n#", $text)))
        ) { // Giovanni    |    Sue Kang
            $r->general()
                ->confirmation($conf);
        } elseif (false === stripos($text, 'Booking Reference')) {
            $r->general()
                ->noConfirmation();
        } elseif (preg_match("/{$this->opt($this->t('Booking Reference'))}[\s:\#]+\d\s+/ui", $text)) {
            $r->general()
                ->noConfirmation();
        }

        $r->general()
            ->status($this->re("#Status[\s:]+(.+)#", $text));

        $r->booked()
            ->rooms($this->re("#Rooms[\s:]+(\d+)#", $text), false, true)
            ->checkIn($this->normalizeDate($this->re("#Check-In Date[\s:]+(.+)#", $text)))
            ->checkOut($this->normalizeDate($this->re("#Check-Out Date[\s:]+(.+)#", $text)));

        $r->hotel()
            ->name($this->re("#Hotel Name[\s:]+(.+)#", $text));

        if (preg_match("#Hotel Address[\s:]+(.+)\s+Room Type#s", $text, $m)) {
            $r->hotel()
                ->address(preg_replace("#\s*\n\s*#", ', ', $this->re("#^(.+?)\s*(?:P\s*\-\s*|F\s*\-\s*|$)#s", $m[1])))
                ->phone($this->re("#\s+P\s*\-\s*([+(\d][-. \d)(]{5,}[\d)])#", $m[1]), false, true)
                ->fax($this->re("#\s+F\s*\-\s*([+(\d][-. \d)(]{5,}[\d)])#", $m[1]), false, true);
            $rm = $r->addRoom();
            $rm->setType($this->re("#Room Type[\s:]+(.+)#", $text));
        } elseif (preg_match("#Hotel Address[\s:]+(.+)\s+Booking Reference#s", $text, $m)) {
            $r->hotel()
                ->address(preg_replace("#\s+#", ' ', $this->re("#^(.+?)\s*(?:P\s*\-\s*|F\s*\-\s*|$)#s", $m[1])))
                ->phone($this->re("#\s+P\s*\-\s*([+(\d][-. \d)(]{5,}[\d)])#", $m[1]), false, true)
                ->fax($this->re("#\s+F\s*\-\s*([+(\d][-. \d)(]{5,}[\d)])#", $m[1]), false, true);
        }
        $roomRate = '';
        $rateText = $this->re("#^\s*{$this->opt($this->t('Rate'))}[\s:]+(.+)#m", $text);

        if (stripos($rateText, 'night') !== false) {
            $roomRate = $rateText;
        } elseif (!empty($rateText)) {
            $roomDuration = $this->re("#^\s*Duration[\s:]+(.+)#m", $text);

            if ($roomDuration) {
                $roomRate = $rateText . ' / ' . str_replace(['(', ')'], '', $roomDuration);
            }
        }

        if (!empty($roomRate)) {
            if (!isset($rm)) {
                $rm = $r->addRoom();
            }
            $rm->setRate($roomRate);
        }

        // cancellation
        // nonRefundable
        // deadline
        $cancellationText = $this->re("#{$this->opt($this->t('Cancellation'))}[\s:]+(.+(?:\n.+){0,5})#", $text);
        $cancellationText = preg_replace("/\n +.+:(?: {3,}|\n)[\s\S]+/", '', $cancellationText);
        $cancellationText = preg_replace("/\n {0,15}\S[\s\S]+/", '', $cancellationText);
        $cancellationText = preg_replace("/\s*\n\s*/", ' ', $cancellationText);

        if (!empty($cancellationText)) {
            $r->general()->cancellation($cancellationText);
            $r->booked()->parseNonRefundable('NON-REFUNDABLE');
            $this->detectDeadLine($r, $cancellationText);
        }

        if (empty($r->getAddress()) && !empty($r->getHotelName())) {
            $r->hotel()->noAddress();

            $type = $this->re("#Room Type[\s:]+(.+)#", $text);

            if (!empty($type)) {
                if (!isset($rm)) {
                    $rm = $r->addRoom();
                }

                $rm->setType($type);
            }
        }

        if (!empty($r->getHotelName())
                && ((!empty($conf) && preg_match("#\n\s*(?:Pay Direct|Pre Pay) +Description [^\n]+\b[A-Z]{3}\b +Total\n(?:.*\n)*\s*Hotel +{$r->getHotelName()} *(?:\- *{$conf})? +[^\n]+ +([\d\.]+)\n#", $this->textPDF, $m))
                || preg_match("#\n\s*(?:Pay Direct|Pre Pay) +Description [^\n]+\b[A-Z]{3}\b +Total\n(?:.*\n)*\s*Hotel +{$r->getHotelName()} +[^\n]+ +([\d\.]+)\n#", $this->textPDF, $m))) {
            $r->price()
                ->total($m[1])
                ->currency($this->currency);
        }

        // travellers
        $passengers = $this->getPassengers($text);

        foreach ((count($passengers) > 0 ? $passengers : $this->pax) as $tName) {
            $r->general()->traveller($this->normalizeTraveller($tName), true);
        }
    }

    /**
     * Here we describe various variations in the definition of dates deadLine.
     */
    private function detectDeadLine(Hotel $h, string $cancellationText)
    {
        if (preg_match("#There is no charge for cancellations made before (?<time>\d{1,2}:\d{2}) \(property local time\) on (?<date>\w+ \w+ \w+)\.#i", $cancellationText, $m)) {
            $h->booked()
                ->deadline($this->normalizeDate($m['date'] . ', ' . $m['time']));
        } elseif (preg_match("#Cancel (?<prior>\d+ (?:hours?|days?)) prior to arrival#i", $cancellationText, $m)) {
            $h->booked()
                ->deadlineRelative($m['prior']);
        }
    }

    private function parseCar(Rental $r, $text)
    {
        $this->logger->debug(__FUNCTION__ . '()');
        $confNo = $this->re("#Booking Reference[\s:]+([A-Z\d]{5,})#", $text);

        if (!empty($confNo)) {
            $r->general()->confirmation($confNo);
        } else {
            $r->general()->noConfirmation();
        }
        $r->general()->status($this->re("#Status[\s:]+(.+)#", $text));

        if (preg_match("#Pick-Up Date[\s:]+(.+?)\s{5,}(.+)#", $text, $m)) {
            $r->pickup()
                ->date($this->normalizeDate($m[1]))
                ->location($m[2]);
        }

        if (preg_match("#Drop-off date[\s:]+(.+?)\s{5,}(.+)#", $text, $m)) {
            $r->dropoff()
                ->date($this->normalizeDate($m[1]))
                ->location($m[2]);
        }

        // carType
        $r->car()
            ->type($this->re("#Car Type[\s:]+(.+)#", $text), false,
                !empty($r->getPickUpLocation()) || !empty($r->getDropOffLocation()));

        if (preg_match("#Pick-Up Address[\s:]+(.+?)\n\s+(?:Drop-off Address|Status)#s", $text, $m)) {
            $r->pickup()
                ->location(preg_replace("#\s+#", ' ', $this->re("#^(.+?)\s*(?:P\s*\-\s*|F\s*\-\s*|$)#s", $m[1])))
                ->phone(trim($this->re("#\s+P\s*\-\s*([\d\(\)\+\- ]{5,})#", $m[1])), true, true)
                ->fax(trim($this->re("#\s+F\s*\-\s*([\d\(\)\+\- ]{5,})#", $m[1])), true, true);
        }

        if (preg_match("#Drop-off Address[\s:]+(.+?)\n\s+Status#s", $text, $m)) {
            $r->dropoff()
                ->location(preg_replace("#\s+#", ' ', $this->re("#^(.+?)\s*(?:P\s*\-\s*|F\s*\-\s*|$)#s", $m[1])))
                ->phone(trim($this->re("#\s+P\s*\-\s*([\d\(\)\+\- ]{5,})#", $m[1])), true, true)
                ->fax(trim($this->re("#\s+F\s*\-\s*([\d\(\)\+\- ]{5,})#", $m[1])), true, true);
        }

        $keyword = $this->re("#Car Company[\s:]+(.+)#", $text);
        $r->extra()->company($keyword);
        $rentalProvider = $this->getRentalProviderByKeyword($keyword);

        if (!empty($rentalProvider)) {
            $r->program()->code($rentalProvider);
        } /*else {
            $r->program()->keyword($this->re("#(.+?)\s*(?:RENT A CAR|$)#", $keyword));
        }*/

        if (preg_match("#Pay Direct +Description [^\n]+\b[A-Z]{3}\b +Total\n\s*Car +{$keyword} *- *{$confNo} +[^\n]+ +([\d.]+)\n#", $this->textPDF, $m)) {
            $r->price()
                ->total($m[1])
                ->currency($this->currency);
        }

        // travellers
        $passengers = $this->getPassengers($text);

        foreach ((count($passengers) > 0 ? $passengers : $this->pax) as $tName) {
            $r->general()->traveller($this->normalizeTraveller($tName), true);
        }
    }

    private function parseEvent(Event $r, $text, Email $email)
    {
        $this->logger->debug(__FUNCTION__ . '()');
        $confNo = $this->re("#Booking Reference[\s:]+([A-Z\d]{5,})#", $text);

        if (empty($confNo)) {
            $r->general()
                ->noConfirmation();
        } else {
            $r->general()
                ->confirmation($confNo);
        }

        if (!empty($status = $this->re("#Status[\s:]+(.+)#", $text))) {
            $r->general()
                ->status($status);
        }

        $tot = $this->getTotalCurrency($this->re("#^\s*Local\s+Rate[\s:]+(.+)#m", $text));

        if (!empty($tot['Total'])) {
            $r->price()
                ->cost($tot['Total'])
                ->currency($tot['Currency']);
        }

        $r->place()
            ->name($this->re("#(?:Tour Company|Dinner)[\s:]+(.+)#", $text))
            ->type(Event::TYPE_EVENT);

        if (preg_match("#Start Date[\s:]+(.+?)\s{5,}(.+)#", $text, $m)) {
            $r->booked()
                ->start($this->normalizeDate($m[1]));
            $r->place()
                ->address($m[2]);
        }

        if (preg_match("#Finish Date[\s:]+(.+?)\s{5,}(.+)#", $text, $m)) {
            $endDate = $this->normalizeDate($m[1]);

            if ($endDate < $r->getStartDate() || $endDate == $r->getStartDate()) {
                $email->removeItinerary($r);
            } else {
                $r->booked()
                    ->end($endDate);
            }
        }

        // travellers
        $passengers = $this->getPassengers($text);

        foreach ((count($passengers) > 0 ? $passengers : $this->pax) as $tName) {
            $r->general()->traveller($this->normalizeTraveller($tName), true);
        }
    }

    /**
     * for all types itineraries.
     *
     * @param string $text
     */
    private function getPassengers($text = ''): array
    {
        // examples: it-14465658.eml
        $passengers = [];
        $len = strlen($this->re("#\n( +Passengers *:) *.+#", $text));
        $passengerText = $this->re("#\n *Passengers *: *(.+(?:\n {{$len},}\S.+)*)#", $text . "\n");

        if ($passengerText && count($this->pax)) {
            foreach (preg_split('/[ ]*\n+[ ]*/', $passengerText) as $passengerRow) {
                if (in_array($passengerRow, $this->pax)) {
                    $passengers[] = $passengerRow;
                } else {
                    break;
                }
            }
        }

        return $passengers;
    }

    private function dateFix($date)
    {
        // Fri 12lul 24 -> Fri 12 Jul 24
        $date = preg_replace("/^(\w+ \d{1,2})lul( \d{2}(?: at \d{1,2}:\d{2}.*)?)$/", '$1 Jul $2', $date);
        // Mon OS Aug 24 -> Mon 05 Aug 24
        $date = preg_replace("/^(\w+ )OS ([[:alpha:]]+)( \d{2}(?: at \d{1,2}:\d{2}.*)?)$/", '$1 05 $2', $date);

        return $date;
    }

    private function normalizeDate($date)
    {
        $in = [
            //Sun 29 Oct 17 at 08:05
            '#^\s*\w+\s+(\d+)\s*([[:alpha:]]+)\s+(\d{2})\s+at\s+(\d+:\d+(?:\s*[ap]m)?)\s*$#ui',
            //Fri 07 Oct 16
            '#^\s*\w+\s+(\d+)\s*([[:alpha:]]+)\s+(\d{2})\s*$#ui',
        ];
        $out = [
            '$1 $2 20$3 $4',
            '$1 $2 20$3',
        ];
        $str = $this->dateStringToEnglish(preg_replace($in, $out, $date));

        $str = strtotime($str);

        if (empty($str)) {
            $date = $this->dateFix($date);
            $str = $this->dateStringToEnglish(preg_replace($in, $out, $date));
            $str = strtotime($str);
        }

        return $str;
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
                foreach ($reBody as $rB) {
                    if (stripos($body, $rB) !== false) {
                        $this->lang = substr($lang, 0, 2);

                        return true;
                    }
                }
            }
        }

        return false;
    }

    private function getTotalCurrency($node)
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
            $m['t'] = preg_replace('/\s+/', '', $m['t']);            // 11 507.00	->	11507.00
            $m['t'] = preg_replace('/[,.](\d{3})/', '$1', $m['t']);    // 2,790		->	2790		or	4.100,00	->	4100,00
            $m['t'] = preg_replace('/^(.+),$/', '$1', $m['t']);    // 18800,		->	18800
            $m['t'] = preg_replace('/,(\d{2})$/', '.$1', $m['t']);    // 18800,00		->	18800.00
            $tot = $m['t'];
            $cur = $m['c'];
        }

        return ['Total' => $tot, 'Currency' => $cur];
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return "(?:" . preg_quote($s) . ")";
        }, $field)) . ')';
    }

    private function getRentalProviderByKeyword(string $keyword)
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

    private function normalizeTraveller(?string $s): ?string
    {
        if (empty($s)) {
            return null;
        }

        $namePrefixes = '(?:MASTER|MSTR|MISS|MRS|MR|MS|DR)';

        return preg_replace([
            "/^(.{2,}?)\s*(?:{$namePrefixes}[.\s]*)+$/is",
            "/^(?:{$namePrefixes}[.\s]+)+(.{2,})$/is",
            '/^([^\/]+?)(?:\s*[\/]+\s*)+([^\/]+)$/',
        ], [
            '$1',
            '$1',
            '$2 $1',
        ], $s);
    }
}
