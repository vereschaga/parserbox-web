<?php

namespace AwardWallet\Engine\priceline\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Engine\WeekTranslate;
use AwardWallet\Schema\Parser\Email\Email;

// TODO: merge with parsers priceline/It2487277 (in favor of priceline/Reservations)

class Reservations extends \TAccountChecker
{
    public $mailFiles = "priceline/it-54383174.eml, priceline/it-61709595.eml, priceline/it-2997583.eml, priceline/it-3375099.eml, priceline/it-3861785.eml, priceline/it-48328467.eml, priceline/it-6784417.eml, priceline/it-2849569.eml, priceline/it-2852582.eml, priceline/it-3038518.eml, priceline/it-3048966.eml, priceline/it-33175468.eml, priceline/it-4950365.eml, priceline/it-5021653.eml, priceline/it-5021654.eml, priceline/it-58520665.eml, priceline/it-80749212.eml";

    private static $detectors = [
        'en' => [
            "Rental Location",
            "Driver Name:",
            "Your Flight Confirmation",
            "Thanks for booking your flight with priceline.com.",
            "Congrats, your trip from",
            "Your Rental Car Cancellation number:",
            "Your Package Confirmation for ",
            "Congrats, your flight on ",
            "Airline Confirmation Number(s)",
        ],
        "es" => ["Ubicación del alquiler:"],
    ];

    private static $dictionary = [
        "en" => [
            "firstLangDetect" => [
                "Confirmation Number:",
                "CONFIRMATION NUMBER:",
                "Cancellation Number:",
                "Airline Confirmation Number",
                "airline confirmation number",
            ],
            "lastLangDetect"               => ["Rental Location:", "Flight", "Your Rental Car"],
            "flightDetect"                 => ["Airline Confirmation Number", "airline confirmation number"],
            "rentalDetect"                 => ["Driver Name:", "Your Rental Car Cancellation number"],
            "hotelDetect"                  => ["Hotel Confirmation Number:", "CHECK-OUT:"],
            "Airline Confirmation Numbers" => ["Airline Confirmation Numbers", "Airline Confirmation Number(s)"],
            "Hotel Address:"               => ["Hotel Address:", "HOTEL ADDRESS:"],
            "Check-in:"                    => ["Check-in:", "CHECK-IN:"],
            "Check-out:"                   => ["Check-out:", "CHECK-OUT:"],
            "Hotel Phone Number:"          => ["Hotel Phone Number:", "HOTEL PHONE NUMBER:"],
            "Number of Rooms:"             => ["Number of Rooms:", "NUMBER OF ROOMS:", "NUMBER OFROOMS:"],
            "Reservation Name:"            => ["Reservation Name:", "RESERVATION NAME:", "RESERVATIONNAME:"],
            "Hotel Confirmation Number:"   => ["Hotel Confirmation Number:", "CONFIRMATION NUMBER:"],
            "Room Type:"                   => ["Room Type:", "ROOM TYPE:"],
            "Room Price:"                  => ["Room Price:", "ROOM PRICE:"],

            "Rental Location:"                 => ["Rental Location:", "Car Hire Location:"],
            "Summary of Charges"               => ["Summary of Charges", "Summary of charges", "SUMMARY OF CHARGES"],
            "Total Price:"                     => ["Total charged:", "Total Charged:", "Estimated Total:", "Total:", "TOTAL:", "Total Price:", "Total Cost:", "TOTAL COST:"],
            "Driver Name:"                     => "Driver Name:",
            "Ticket Cost:"                     => ["Ticket Cost:", "Ticket cost:", "TICKET COST:", "Room Subtotal:", "ROOM SUBTOTAL:"],
            "Taxes & Fees:"                    => ["Taxes & Fees:", "Taxes and fees:", "TAXES & FEES:"],
            "Your Rental Car Confirmation for" => [
                "Congrats, your rental car",
                "Congrats, your car hire for",
                "Your Rental Car Confirmation for",
            ],
            "statusPhrases"  => ["Congrats, your flight on", "Congrats, your car hire for", "Your Rental Car Reservation with"],
            "statusVariants" => ["confirmed", "cancelled", "canceled"],
        ],
        "es" => [
            "firstLangDetect" => ["Número de confirmación:"],
            "lastLangDetect"  => ["Ubicación del alquiler:"],
            // "flightDetect" => [""],
            "rentalDetect" => ["¡Felicitaciones! ¡El alquiler de su automóvil"],
            //            "Airline Confirmation Numbers" => "",

            "Rental Location:" => ["Ubicación del alquiler:"],
            //            "Summary of Charges" => "",
            "Total Price:"           => ["Estimado Total:"],
            "Confirmation Number:"   => "Número de confirmación:",
            "Priceline Trip Number:" => "Número de viaje de Priceline:",
            "Pick-up:"               => "Recoger:",
            "Drop-off:"              => "Entregar:",
            "Car Type:"              => "Tipo de automóvil:",
            "Driver Name:"           => "Nombre del conductor:",
            "Prices are in"          => "Los precios se expresan en",
            //            "Ticket Cost:" => "",
            "Taxes & Fees:"                    => "Impuestos & Cargos:",
            "Your Rental Car Confirmation for" => "¡Felicitaciones! ¡El alquiler de su automóvil para",
            //            "statusPhrases" => "",
            //            "statusVariants" => "",
        ],
    ];

    private $body = "priceline.com";

    private $subject = [
        "en" => "Your priceline itinerary for", "Your Itinerary for",
        "es" => "Su itinerario de priceline para",
    ];

    private $lang;
    private $year;
    private $rDate;

    private static $providers = [
        'avis'         => ['Avis Rent a Car'],
        'hertz'        => ['Hertz Corporation'],
        'foxrewards'   => ['Fox Rent-A-Car'],
        'national'     => ['National Car Rental'],
        'dollar'       => ['Dollar Rent A Car'],
        'alamo'        => ['Alamo Rent a Car'],
        'rentacar'     => ['Enterprise Rent-A-Car'],
        'payless'      => ['Payless Car Rental'],
        'perfectdrive' => ['Budget Rent a Car'],
        'sixt'         => ['Sixt Rent a Car'],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->detectEmailFromProvider($headers['from']) !== true) {
            return false;
        }

        foreach ($this->subject as $re) {
            if (stripos($headers["subject"], $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@production.priceline.com') !== false
            || stripos($from, '@travel.priceline.com') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $text = $this->http->Response['body'];

        if (stripos($text, $this->body) === false) {
            return false;
        }

        if ($this->detectBody()) {
            return $this->assignLang();
        }

        return false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary) * 3;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        if (!$this->assignLang()) {
            $this->logger->debug("Can't determine a language");

            return $email;
        }

        if (!$this->detectBody()) {
            $this->logger->debug("Can't determine a Body");

            return $email;
        }
        $email->setType("Reservations");

        $date = $this->http->FindSingleNode("//text()[" . $this->contains($this->t('Confirmation for')) . "]/ancestor::span[1]/span");

        if (empty($date)) {
            $date = $this->http->FindSingleNode("//text()[" . $this->contains($this->t('Confirmation for')) . "]/ancestor::span[1]/following::span[contains(translate(normalize-space(), '0123456789', 'dddddddddd'), 'dddd')][1]");
        }

        if (preg_match("/([A-z]+?[,]?\s[A-z]+?\s\d{1,2}[,]?\s(?<year>\d{4}))/", $date, $m)) {
            $this->year = $m["year"];
            //$this->rDate = $this->normalizeDate($m[1]);
        }

        if (empty($this->year)) {
            // Tuesday, July 14, 2020 - Friday, July 24, 2020
            // Tuesday, July 14, 2020
            $this->year = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Passengers :'))}]/ancestor::table[1]/preceding::table[1]/descendant::tr[contains(translate(normalize-space(),'0123456789','dddddddddd'),'dd') and contains(translate(normalize-space(),'0123456789','dddddddddd'),'dddd')][1]",
                null, true, "/[A-z]{3,}[,\s]+[A-z]{3,}\s+\d{1,2}[,\s]+(\d{4})(?:\s+-\s+[A-z]{3,}[,\s]+[A-z]{3,}\s+\d{1,2}[,\s]+\d{4}|\s*$)/");
        }

        // TripNumber
        $tripNumber = $this->nextText($this->t("Priceline Trip Number:"));

        if (!empty($tripNumber)) {
            $email->ota()->confirmation($tripNumber, rtrim($this->t("Priceline Trip Number:"), ': '));
        } elseif (preg_match("/({$this->opt($this->t("Priceline Trip Number:"))})\s*([\-A-Z\d]{5,})$/",
            $this->http->FindSingleNode("//text()[{$this->starts($this->t("Priceline Trip Number:"))}]"), $m)
        ) {
            $email->ota()->confirmation($m[2], rtrim($m[1], ': '));
        }

        if ($this->http->XPath->query("//*[" . $this->contains($this->t("rentalDetect")) . "]")->length > 0) {
            $this->parseRental($email);
        }

        $hotels = $this->http->XPath->query("//tr[not(.//tr)]/*[1][{$this->starts($this->t("Check-in:"))}]/ancestor::table[ descendant::tr[not(.//tr)]/*[1][{$this->starts($this->t("Room Type:"))}] ][1]");

        foreach ($hotels as $hotel) {
            // it-54383174.eml, it-80749212.eml
            $this->parseHotel($email, $hotel);
        }

        if ($this->http->XPath->query("//*[" . $this->contains($this->t("flightDetect")) . "]")->length > 0) {
            $this->parseFlight($email);
        }

        $totalPrice = implode("\n", $this->http->FindNodes("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->starts($this->t('Total Price:'))}] ]/*[normalize-space()][2]/descendant::text()[normalize-space()]"));

        if (preg_match('/^(?<currency>[^\d)(]+)?\s*(?<amount>\d[,.\'\d]*)(?:[ ]*\(|\n|$)/', $totalPrice, $m)
            || preg_match('/^(?<amount>\d[,.\'\d]*)[ ]*(?<currency>[^\d)(]+)?(?:[ ]*\(|\n|$)/', $totalPrice, $m)
        ) {
            if (!empty($m['currency'])) {
                $m['currency'] = trim($m['currency']);
                $email->price()->currency($m['currency']);
            }

            // $1879.95    |    £1,683.63 ($2,581.50)
            $email->price()->total(PriceHelper::parse($m['amount'], $m['currency']));

            // it-61709595.eml
            $travellerCount = $this->http->FindSingleNode("//tr[{$this->starts($this->t('Summary of Charges'))}]/following::tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->starts($this->t('Number of travelers:'))}] ]/*[normalize-space()][2]", null, true, "/^x[ ]*(\d{1,3})$/i");

            $xpathCost = "//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->starts($this->t('Ticket Cost:'))}] ]/*[normalize-space()][2]";

            if ($this->http->XPath->query($xpathCost)->length === 1) {
                $baseFare = implode("\n", $this->http->FindNodes($xpathCost . "/descendant::text()[normalize-space()]"));

                if (preg_match('/^(?:' . preg_quote($m['currency'], '/') . ')?[ ]*(?<amount>\d[,.\'\d]*)(?:[ ]*\(|\n|$)/', $baseFare, $matches)
                    || preg_match('/^(?<amount>\d[,.\'\d]*)[ ]*(?:' . preg_quote($m['currency'], '/') . ')?(?:[ ]*\(|\n|$)/', $baseFare, $matches)
                ) {
                    $cost = $this->normalizeAmount($matches['amount']);

                    if ($cost !== null && $travellerCount) {
                        $cost *= $travellerCount;
                    }
                    $email->price()->cost($cost);
                }
            }

            $xpathTax = "//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->starts($this->t('Taxes & Fees:'))}] ]/*[normalize-space()][2]";

            if ($this->http->XPath->query($xpathTax)->length === 1) {
                $tax = implode("\n", $this->http->FindNodes($xpathTax . "/descendant::text()[normalize-space()]"));

                if (preg_match('/^(?:' . preg_quote($m['currency'], '/') . ')?[ ]*(?<amount>\d[,.\'\d]*)(?:[ ]*\(|\n|$)/', $tax, $matches)
                    || preg_match('/^(?<amount>\d[,.\'\d]*)[ ]*(?:' . preg_quote($m['currency'], '/') . ')?(?:[ ]*\(|\n|$)/', $tax, $matches)
                ) {
                    $tax = $this->normalizeAmount($matches['amount']);

                    if ($tax !== null && $travellerCount) {
                        $tax *= $travellerCount;
                    }
                    $email->price()->tax($tax);
                }
            }
        }

        return $email;
    }

    private function parseFlight(Email $email): void
    {
        $xpathTime = 'starts-with(translate(normalize-space(),"0123456789：","dddddddddd:"),"dd:dd")';
        $xpathBold = '(self::b or self::strong or self::span[contains(@style,"bold")])';

        $r = $email->add()->flight();

        $status = $this->http->FindSingleNode("//tr[not(.//tr) and {$this->contains($this->t('statusPhrases'))}]", null, true, "/is\s+({$this->opt($this->t("statusVariants"))})[ ]*[.!]/i");

        if (!empty($status)) {
            $r->general()->status($status);
        }

        if (!empty($this->rDate)) {
            $r->general()->date($this->rDate);
        }

        $pax = array_filter($this->http->FindNodes("//*[{$this->contains($this->t('Passengers'))}]/ancestor-or-self::td[1]/following-sibling::td[1]//*[{$xpathBold} and string-length(normalize-space())>3]", null, '/^[[:alpha:]][-.\'[:alpha:] ]*[[:alpha:]]$/u'));

        if (!empty($pax)) {
            $r->general()->travellers($pax, true);
        }

        if ($this->http->XPath->query("//tr[not(.//tr) and {$this->eq($this->t('Airline Confirmation Numbers'))}]/following-sibling::tr[normalize-space()][1][contains(.,':')]")->length > 0) {
            // it-61709595.eml
            $pnrsValue = '';

            foreach ($this->http->XPath->query("//tr[not(.//tr) and {$this->eq($this->t('Airline Confirmation Numbers'))}]/following-sibling::tr[normalize-space()]") as $pnrRow) {
                $pnrRowText = $this->htmlToText($this->http->FindHTMLByXpath(".", null, $pnrRow));

                if (preg_match("/^[^:\n]{2,}[ ]*:[ ]*[A-Z\d]{5,}$/", $pnrRowText, $m)) {
                    $pnrsValue .= $pnrRowText . "\n";
                } else {
                    continue;
                }
            }
        } else {
            $pnrsHtml = $this->http->FindHTMLByXpath("//tr[not(.//tr) and {$this->starts($this->t('Airline Confirmation Numbers'))}][not(contains(normalize-space(),'trip'))]");
            $pnrsValue = preg_replace("/{$this->opt($this->t('Airline Confirmation Numbers'))}[:\s]*([\s\S]*?)\s*$/", '$1', $this->htmlToText($pnrsHtml));
        }
        $confNumbers = array_filter(preg_split('/(?:\s*,\s*|[ ]*\n+[ ]*)/', $pnrsValue));

        if (count($confNumbers) == 1) {
            if (preg_match_all("/(\D+\:\s*[A-Z\d]+)/", $pnrsValue, $pnrMatches)) {
                $confNumbers = $pnrMatches[1];
            }
        }

        if (count($confNumbers) === 0) {
            // it-61709595.eml
            $airlineRows = $this->http->XPath->query("//tr[{$this->eq($this->t('Airline Contact Information'))}]/following-sibling::tr[normalize-space()]");

            foreach ($airlineRows as $aRow) {
                $confNo = implode(' ', $this->http->FindNodes("descendant::*[count(table)=3][1]/table[1]/descendant::text()[normalize-space()]", $aRow));

                if ($confNo) {
                    $confNumbers[] = $confNo;
                }
            }
        }

        if (count($confNumbers)) {
            $airlines = [];
            $PNRs = [];

            foreach ($confNumbers as $value) {
                if (preg_match("/^(?<desc>.+)\s*[:]+\s*(?<confNo>[A-Z\d]{5,8})$/", $value, $m)) {
                    $airlines[] = $m['desc'];
                    $PNRs[] = $m['confNo'];
                }
            }
            $airlines = array_unique($airlines);
            $PNRs = array_unique($PNRs);

            if (count($airlines) == 0 && count($PNRs) == 0) {
                if (preg_match_all("/\s*(?<desc>[A-Za-z\s]+)\s*[:]+\s*(?<confNo>[A-Z\d]{5,8})\s\s/u", $pnrsValue, $pnrMatches)) {
                    $airlines = array_unique($pnrMatches['desc']);
                    $PNRs = array_unique($pnrMatches['confNo']);
                }
            }

            if (count($airlines) === count($PNRs)) {
                foreach ($PNRs as $key => $v) {
                    $r->general()->confirmation($v, $airlines[$key]);
                }
            } else {
                foreach ($PNRs as $v) {
                    $r->general()->confirmation($v);
                }
            }
        } elseif ($this->http->XPath->query("//*[{$this->starts($this->t("To view your airline confirmation numbers, "))}]")->length > 0) {
            // it-3038518.eml
            $r->general()->noConfirmation();
        } else {
            $r->general()->noConfirmation();
        }

        $patterns['eTicket'] = '(?:\d{3}[- ]*\d{5,}|[A-Z\d]{5,})[- ]*\d{1,2}'; // 075-2345005149-02    |    FR1R5Q8SP-01

        $ticketNumbers = array_filter($this->http->FindNodes("//*[{$this->contains($this->t("Passengers"))}]/ancestor-or-self::td[1]/following-sibling::td[1]/descendant::node()[{$this->starts($this->t("Ticket Number:"))}]", null, "/{$this->opt($this->t("Ticket Number:"))}\s*({$patterns['eTicket']})$/"));

        if (empty($ticketNumbers)) {
            $ticketNumbers = array_filter($this->http->FindNodes("//*[{$this->contains($this->t("Passengers"))}]/ancestor-or-self::td[1]/following-sibling::td[1]/descendant::text()[normalize-space()][contains(normalize-space(),': ')]", null, "/:\s*({$patterns['eTicket']})$/"));
        }

        if (!empty($ticketNumbers)) {
            $r->issued()->tickets(array_unique($ticketNumbers), false);
        }

        $dates = array_values(array_unique(array_filter($this->http->FindNodes("//img[contains(@src,'/images/r-arrow_black')]/ancestor::tr[1]/*[1]",
        null, "/^([A-z]{3}\s+[A-z]{3}\s+\d{1,2})(?:\D|$)/"))));

        foreach ($dates as $date) {
            $nodes = $this->http->XPath->query($xpath = "//img[contains(@src,'/images/r-arrow_black')]/ancestor::table[starts-with(normalize-space(.),'" . $date . "')][2]/descendant::table[" . $this->contains($this->t('Flight')) . "]");
            $this->logger->debug($xpath);

            foreach ($nodes as $root) {
                $s = $r->addSegment();
                $it = [];
                $depCode = $this->http->FindSingleNode(".//*[" . $this->contains($this->t('Depart:')) . "]/ancestor-or-self::td[1]/following-sibling::td[normalize-space(.)][1]",
                    $root, true, "/\(([A-Z]+)\)/");

                if (empty($depCode)) {
                    $depCode = $this->http->FindSingleNode("(./td/text())[1]", $root, true, "/^\s*([A-Z]{3})\s*$/");
                }

                if (!empty($depCode)) {
                    $s->departure()->code($depCode);
                }

                $depName = $this->http->FindSingleNode(".//*[" . $this->contains($this->t('Depart:')) . "]/ancestor-or-self::td[1]/following-sibling::td[normalize-space(.)][1]",
                    $root, true, "/\([A-Z]+\),\s*(.+)/");

                if (!empty($depName)) {
                    $s->departure()->name($depName);
                }

                $arrCode = $this->http->FindSingleNode(".//*[" . $this->contains($this->t('Arrive:')) . "]/ancestor-or-self::td[1]/following-sibling::td[normalize-space(.)][1]",
                    $root, true, "/\(([A-Z]+)\)/");

                if (empty($arrCode)) {
                    $arrCode = $this->http->FindSingleNode("(./td/text())[2]", $root, true, "/^\s*([A-Z]{3})\s*$/");
                }

                if (!empty($arrCode)) {
                    $s->arrival()->code($arrCode);
                }

                $arrName = $this->http->FindSingleNode(".//*[" . $this->contains($this->t('Arrive:')) . "]/ancestor-or-self::td[1]/following-sibling::td[normalize-space(.)][1]",
                    $root, true, "/\([A-Z]+\),\s*(.+)/");

                if (!empty($arrName)) {
                    $s->arrival()->name($arrName);
                }

                $aircraft = $this->http->FindSingleNode(".//tr[" . $this->contains($this->t('Arrive:')) . " and not(.//tr)]/following::text()[contains(normalize-space(.), ' - ') or starts-with(normalize-space(.), '- ')][1]",
                    $root, true, "/-\s+(.+)/");

                if (empty($aircraft)) {
                    $aircraft = $this->http->FindSingleNode(".//*[" . $this->contains($this->t('Arrive:')) . "]/ancestor::table[1]/following::*[contains(normalize-space(.), ' - ') or starts-with(normalize-space(.), '- ')][1]",
                        $root, true, "/-\s+(.+)/");
                }

                if (!empty($aircraft) && strcasecmp($aircraft, 'Unknown') !== 0) {
                    $s->extra()->aircraft($aircraft);
                }

                $traveledMiles = $this->http->FindSingleNode(".//text()[" . $this->contains($this->t('miles')) . "]",
                    $root, true,
                    "/\b(\d[\d.,]+)\s+miles/");

                if (!empty($traveledMiles)) {
                    $s->extra()->miles($traveledMiles);
                }

                $cabin = $this->http->FindSingleNode(".//text()[{$this->contains($this->t('Arrive:'))}]/ancestor::table[1]/following::*[contains(normalize-space(),'Class -') or contains(normalize-space(),'Economy -')][1]", $root, true, "/^(.*?)\s*-/");

                if (empty($cabin)) {
                    $cabin = $this->http->FindSingleNode(".//tr[" . $this->contains($this->t('Arrive:')) . " and not(.//tr)]/following::text()[contains(normalize-space(.), ' - ') or starts-with(normalize-space(.), '- ')][1]",
                        $root, true, "/^(.*?)\s*-/");
                }

                if (!empty($cabin)) {
                    $s->extra()->cabin($cabin);
                }
                $duration = $this->http->FindSingleNode(".//text()[" . $this->contains($this->t('miles')) . "]", $root,
                    true, "/^(\d[\d mh]+)[ ]*?,/i");

                if (!empty($duration)) {
                    $s->extra()->duration($duration);
                }

                $flight = $this->http->FindSingleNode(".//text()[" . $this->contains($this->t('Flight ')) . "]/ancestor::span[1]",
                    $root);

                if (empty($flight)) {
                    $flight = $this->http->FindSingleNode(".//text()[" . $this->contains($this->t('Flight ')) . "]",
                        $root);
                }

                if (preg_match("/^(?:(?<depTime>\d{1,2}:\d{1,2}\s[APM]{2})\s-\s(?<arrTime>\d{1,2}:\d{1,2}\s[APM]{2})|)(?:\s|^)(?:Overnight Flight|)(?<airName>.+?)\sFlight\s(?<flightNo>[\d,]+)(?:\s|$)/",
                    $flight, $m)) {
                    $s->airline()->name($m['airName'])->number(str_replace(",", "", $m['flightNo']));

                    if (!empty($m['depTime'])) {
                        $it["depTime"] = $m['depTime'];
                        $it["arrTime"] = $m['arrTime'];
                    } else {
                        $time = $this->http->FindSingleNode(".//text()[" . $this->contains($this->t('Flight')) . "]/ancestor::span[1]/preceding::span[contains(translate(normalize-space(), '0123456789', 'dddddddddd'), 'dd:dd')][1]",
                            $root);

                        if (empty($time)) {
                            $time = $this->http->FindSingleNode(".//text()[" . $this->contains($this->t('Flight')) . "]/ancestor::td[1][contains(translate(normalize-space(), '0123456789', 'dddddddddd'), 'dd:dd')][1]",
                                $root);
                        }

                        if (preg_match("/(?<depTime>\d{1,2}:\d{1,2}\s[APM]{2})\s-\s(?<arrTime>\d{1,2}:\d{1,2}\s[APM]{2})/",
                            $time, $m)) {
                            $it["depTime"] = $m['depTime'];
                            $it["arrTime"] = $m['arrTime'];
                        }
                    }
                }

                if (!empty($date)) {
                    $dateNormal = $this->normalizeDate($date);

                    if (!empty($it["depTime"]) && $dateNormal) {
                        $s->departure()->date(strtotime($it["depTime"], $dateNormal));
                    } elseif ($dateNormal) {
                        $s->departure()->day($dateNormal);
                    }

                    if (!empty($it["arrTime"]) && $dateNormal) {
                        $s->arrival()->date(strtotime($it["arrTime"], $dateNormal));
                    } elseif ($dateNormal) {
                        $s->arrival()->day($dateNormal);
                    }
                }
            }
        }

        if (count($r->getSegments()) > 0) {
            return;
        }

        $date = empty($dates[0]) ? null : $dates[0];

        // it-61709595.eml
        $nodes = $this->http->XPath->query($xpath = "//img[contains(@src,'/images/r-arrow_black')]/ancestor::tr[1]/following-sibling::tr[" . $this->contains($this->t('Flight')) . "]");

        if ($nodes->length === 0) {
            // for emails with Image removed by sender
            $nodes = $this->http->XPath->query($xpath = "//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1]/descendant::text()[normalize-space()][1][string-length(normalize-space())=3] and *[normalize-space()][2][{$this->contains($this->t('Flight'))}]/descendant::text()[{$xpathTime}] ]");
        }
        $this->logger->debug($xpath);

        if (empty($this->year)
            && ($str = $this->http->FindSingleNode("//text()[contains(., 'Congrats, your ') and contains(., 'is confirmed')]"))
            && preg_match('/^Congrats, your (flight|trip) (on|from) \w+, \w+ \d{1,2}, (?<y>\d{4})\b/', $str, $m) > 0) {
            $this->year = $m['y'];
        }

        foreach ($nodes as $root) {
            $s = $r->addSegment();
            $it = [];
            $depCode = $this->http->FindSingleNode("*[normalize-space()][1]/descendant::text()[normalize-space()][1]", $root, true, "/^[A-Z]{3}$/");

            if (!empty($depCode)) {
                $s->departure()->code($depCode);
            }

            $arrCode = $this->http->FindSingleNode("*[normalize-space()][1]/descendant::text()[normalize-space()][2]", $root, true, "/^[A-Z]{3}$/");

            if (!empty($arrCode)) {
                $s->arrival()->code($arrCode);
            }

            $segmentText = $this->htmlToText($this->http->FindHTMLByXpath('*[normalize-space()][2]', null, $root));

            $date = $this->http->FindSingleNode("preceding-sibling::tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][not(descendant::img)] and *[normalize-space()][2][descendant::img[contains(@src,'/images/r-arrow_black')]] ][1]/*[normalize-space()][1]", $root, true, "/^.*\d.*$/")
                ?? $this->http->FindSingleNode("preceding-sibling::tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][not(descendant::img)] and *[normalize-space()][2][descendant::img] ][1]/*[normalize-space()][1]", $root, true, "/^.*\d.*$/")
            ;

            if ($date && preg_match("/(?<depTime>\d{1,2}:\d{1,2}\s[APM]{2})\s+-\s+(?<arrTime>\d{1,2}:\d{1,2}\s[APM]{2})/", $segmentText, $m)) {
                $dateNormal = $this->normalizeDate($date);
                $s->departure()->date(strtotime($m["depTime"], $dateNormal));
                $s->arrival()->date(strtotime($m["arrTime"], $dateNormal));
            }

            if ($str = $this->http->FindSingleNode("./td[2]//text()[{$this->contains($this->t('Operated by'))}]", $root, null, "/{$this->opt($this->t('Operated by'))}\s+(.+)/")) {
                $s->airline()->operator($str);
            }

            if ($str = $this->http->FindSingleNode("./td[2]//text()[{$this->contains($this->t(' Flight '))}]", $root)) {
                if (preg_match("/^(.+?)\s*{$this->opt($this->t(' Flight '))}\s*(\d+)/", $str, $m)) {
                    $s->airline()->name($m[1]);
                    $s->airline()->number($m[2]);
                }
            }

            if (preg_match("/{$this->opt($this->t(' Flight '))}[\s\S]+(?i)^[ ]*(\d{1,3}[ ]*[hm][\d hm]*?)[ ]*$/m", $segmentText, $m)) {
                // 5h 25m    |    55m
                $s->extra()->duration($m[1]);
            }

            if (preg_match("/{$this->opt($this->t(' Flight '))}[\s\S]+^[ ]*(?<cabin>.{2,}?)[ ]+-[ ]+(?<aircraft>.{2,}?)[ ]*$/m", $segmentText, $m)) {
                // Economy Class - Airbus A350-1000
                $s->extra()->cabin($m[1])->aircraft($m[2]);
            }
        }
    }

    private function parseRental(Email $email): void
    {
        $r = $email->add()->rental();

        if (!empty($this->rDate)) {
            $r->general()->date($this->rDate);
        }

        $otaNumber = $this->http->FindSingleNode("//text()[{$this->eq($this->t("Your Rental Car Cancellation number:"))}]/following::text()[normalize-space()][1]", null, true, "/^[-A-Z\d]{5,}$/");

        if ($otaNumber) {
            $otaNumberTitle = $this->http->FindSingleNode("//text()[{$this->eq($this->t("Your Rental Car Cancellation number:"))}]", null, true, "/^{$this->opt($this->t("Your Rental Car"))}\s+(.+?)[\s:]*$/");
            $email->ota()->confirmation($otaNumber, $otaNumberTitle);

            if (stripos($otaNumberTitle, 'cancel') !== false) {
                // it-58520665.eml
                $r->general()->cancelled();
            }
        }

        // Status
        $status = $this->http->FindSingleNode("//tr[not(.//tr) and {$this->contains($this->t('statusPhrases'))}]", null, true, "/is\s+({$this->opt($this->t("statusVariants"))})[ ]*[.!]/i");

        if (!empty($status)) {
            $r->general()->status($status);
        }

        // Number
        $number = $this->http->FindSingleNode("(//text()[{$this->contains($this->t("Confirmation Number:"))}])[last()]/following::text()[normalize-space(.)][1]");

        if (!empty($number)) {
            $r->general()->confirmation($number, trim($this->t("Confirmation Number:", ":")));
        }

        // company
        // cancellationNumber
        $company = null;
        $cancellationRow2 = $this->http->FindSingleNode("//text()[{$this->contains($this->t("Cancellation Number:"))}]");

        if (preg_match("/^(.{2,}?)\s*{$this->opt($this->t("Cancellation Number:"))}\s*([-A-Z\d]{5,})$/", $cancellationRow2, $m)) {
            // it-58520665.eml
            $company = $m[1];
            $r->general()->cancellationNumber($m[2], false, true);
        }

        if (empty($company)) {
            $company = $this->http->FindSingleNode("(//text()[{$this->contains($this->t("Confirmation Number:"))}])[1]",
                null, true, "/^(.{2,}?)\s*{$this->t("Confirmation Number:")}/");
        }

        if (!empty($company)) {
            if (($code = $this->normalizeProvider($company))) {
                $r->program()->code($code);
            } else {
                $r->extra()->company($company);
            }
        }

        // PickupDatetime
        $pickupDatetime = $this->normalizeDate($this->http->FindSingleNode("(//text()[" . $this->eq($this->t("Pick-up:")) . "])[last()]/following::text()[normalize-space(.)][1]"));

        if (!empty($pickupDatetime)) {
            $r->pickup()->date($pickupDatetime);
        }

        // PickupLocation
        // for hidden text in column
        if (!$pickupLocation = implode(", ",
            $this->http->FindNodes("(//text()[" . $this->eq($this->t("Rental Location:")) . "])[last()]/ancestor::td[1]/descendant::text()[normalize-space(.)][position()>2 and not(./ancestor::a)]"))) {
            if (!$pickupLocation = implode(", ",
                $this->http->FindNodes("//text()[" . $this->eq($this->t("Rental Location:")) . "]/ancestor::td[1]/following-sibling::td[1]/descendant::text()[normalize-space(.)][position()>1]"))) {
                $pickupLocation = implode(", ",
                    $this->http->FindNodes("//text()[" . $this->eq($this->t("Pick-up:")) . "]/ancestor::td[1]/following-sibling::td[1]/descendant::text()[normalize-space(.)][position()>2]"));
            }
        }

        if (!empty($pickupLocation)) {
            $r->pickup()->location($pickupLocation);
        }

        // DropoffDatetime
        $dropoffDatetime = $this->normalizeDate($this->http->FindSingleNode("(//text()[" . $this->eq($this->t("Drop-off:")) . "])[last()]/following::text()[normalize-space(.)][1]"));

        if (!empty($dropoffDatetime)) {
            $r->dropoff()->date($dropoffDatetime);
        }

        // DropoffLocation
        if (!$dropoffLocation = implode(", ",
            $this->http->FindNodes("(//text()[" . $this->eq($this->t("Rental Location:")) . "])[last()]/ancestor::td[1]/descendant::text()[normalize-space(.)][position()>2 and not(./ancestor::a)]"))) {
            if (!$dropoffLocation = implode(", ",
                $this->http->FindNodes("//text()[" . $this->eq($this->t("Rental Location:")) . "]/ancestor::td[1]/following-sibling::td[1]/descendant::text()[normalize-space(.)][position()>1]"))) {
                $dropoffLocation = implode(", ",
                    $this->http->FindNodes("//text()[" . $this->eq($this->t("Drop-off:")) . "]/ancestor::td[1]/following-sibling::td[1]/descendant::text()[normalize-space(.)][position()>2]"));
            }
        }

        if (!empty($dropoffLocation)) {
            $r->dropoff()->location($dropoffLocation);
        }

        // CarType
        $carType = preg_replace("/{$this->opt($this->t('Car Type:'))}[ ]*/", '',
            $this->http->FindSingleNode("//text()[" . $this->eq($this->t("Car Type:")) . "]/ancestor::td[1]/following-sibling::td[1]",
                null, true, "/^.+? or? similar/i"));

        if (!empty($carType)) {
            $r->car()->type($carType);
        }
        // CarImageUrl
        $carImageUrl = $this->http->FindSingleNode("//text()[{$this->eq($this->t("Pick-up:"))}]/following::img[position()<3][contains(@alt,'[CAR_TYPE]') or contains(@altx,'[CAR_TYPE]')]/@src",
            null, true, "/^(https?:\/\/\S+)$/");

        if (!$carImageUrl) {
            $carImageUrl = $this->http->FindSingleNode("//text()[{$this->eq($this->t("Pick-up:"))}]/preceding::img[position()<3][contains(@alt,'[CAR_TYPE]') or contains(@altx,'[CAR_TYPE]')]/@src",
                null, true, "/^(https?:\/\/\S+)$/");
        }

        if (!empty($carImageUrl)) {
            $r->car()->image($carImageUrl);
        }

        // RenterName
        $renterName = $this->http->FindSingleNode("(//text()[{$this->contains($this->t("Driver Name:"))}])[last()]/following::text()[normalize-space(.)][1]");

        if (!empty($renterName)) {
            $r->general()->traveller($renterName, true);
        }
    }

    private function parseHotel(Email $email, \DOMNode $rRoot): void
    {
        $r = $email->add()->hotel();

        if (!empty($this->rDate)) {
            $r->general()->date($this->rDate);
        }

        $name = $this->http->FindSingleNode("descendant::text()[{$this->starts($this->t("Hotel Address:"))}]/preceding::table[{$this->contains($this->t('Night'))}][1]/descendant::tr[not(.//tr) and normalize-space()][1]/descendant::text()[normalize-space()][1]", $rRoot);

        if (!empty($name)) {
            $r->hotel()->name($name);
        }

        $address = $this->http->FindSingleNode("descendant::td[{$this->starts($this->t("Hotel Address:"))}]/following-sibling::td[normalize-space()][1]", $rRoot);

        if (!empty($address)) {
            $r->hotel()->address($address);
        }

        $checkIn = $this->http->FindSingleNode("descendant::td[{$this->starts($this->t('Check-in:'))}]/following-sibling::td[normalize-space()][1]", $rRoot, true, "/(?:{$this->opt($this->t('Check-in:'))}|^)\s*(.{6,})$/");

        if (!empty($checkIn)) {
            $r->booked()->checkIn($this->normalizeDate($checkIn));
        }

        $checkOut = $this->http->FindSingleNode("descendant::td[{$this->starts($this->t('Check-out:'))}]/following-sibling::td[normalize-space()][1]", $rRoot, true, "/(?:{$this->opt($this->t('Check-out:'))}|^)\s*(.{6,})$/");

        if (!empty($checkOut)) {
            $r->booked()->checkOut($this->normalizeDate($checkOut));
        }

        $phone = $this->http->FindSingleNode("descendant::text()[" . $this->starts($this->t('Hotel Phone Number:')) . "]/following::td[1]", $rRoot);

        if (!empty($phone)) {
            $r->hotel()->phone($phone);
        }

        $rooms = $this->http->FindSingleNode("descendant::td[{$this->starts($this->t('Number of Rooms:'))}]/following-sibling::td[{$this->contains($this->t("Room"))} and not(preceding::text()[{$this->starts($this->t("Summary of Charges"))}])][1]", $rRoot, true, "/\b(\d{1,3})\s*{$this->opt($this->t("Room"))}/");

        if ($rooms !== null) {
            $r->booked()->rooms($rooms);
        }

        $reservationName = implode("\n", $this->http->FindNodes("descendant::td[{$this->starts($this->t('Reservation Name:'))}]/following-sibling::td[{$this->contains($this->t("Adult"))}][1]/descendant::text()[normalize-space()]", $rRoot));

        if (preg_match_all("/{$this->opt($this->t('Room'))}\s*\d{1,3}[:\s]+([[:alpha:]][-.\'[:alpha:] ]*[[:alpha:]])(?:[ (]|$)/mu", $reservationName, $matches)) {
            // Room 1: Ellen Howard
            $r->general()->travellers($matches[1]);
        }

        if (preg_match("/\b(\d{1,3})\s*{$this->opt($this->t("Adult"))}/", $reservationName, $m)) {
            $r->booked()->guests($m[1]);
        }

        $confNoValue = implode(' ', $this->http->FindNodes("descendant::tr[count(*)=2 and *[1][{$this->starts($this->t('Hotel Confirmation Number:'))}]]/descendant::text()[normalize-space()]", $rRoot));

        if (preg_match("/({$this->opt($this->t('Hotel Confirmation Number:'))})[:\s]+([-A-Z\d]{5,})(?:\s*\(|$)/", $confNoValue, $m)) {
            // 1771737356 (Pincode: 9539)
            $r->general()->confirmation($m[2], rtrim($m[1], ': '));
        } elseif (preg_match_all("/({$this->opt($this->t('Room'))}\s*\d{1,3})[:\s]+([-A-Z\d]{5,})(?:[ (]|$)/", $confNoValue, $matches, PREG_SET_ORDER)) {
            // Room 1: 71-2191869 (Pincode: 33HISN)
            foreach ($matches as $m) {
                if (!isset($r->getConfirmationNumbers()[0])) {
                    $r->general()->confirmation($m[2], $m[1]);
                } elseif (!in_array($m[2], $r->getConfirmationNumbers()[0])) {
                    $r->general()->confirmation($m[2], $m[1]);
                }
            }
        }

        $roomType = implode("\n", $this->http->FindNodes("descendant::td[{$this->starts($this->t('Room Type:'))}]/following-sibling::td[normalize-space()][1]/descendant::text()[normalize-space()]", $rRoot));
        $roomType = preg_replace("/^[\s\S]*{$this->opt($this->t('Room Type:'))}\s*([\s\S]{2,})$/", '$1', $roomType);

        $roomPrice = $this->http->FindSingleNode("descendant::text()[{$this->eq($this->t('Room Price:'))}]/ancestor::td[1]/following-sibling::td[1]", $rRoot);

        if ($roomType || $roomPrice !== null) {
            $cnt = $r->getRoomsCount();

            for ($i = 1; $i <= $cnt; $i++) {
                $prefix = 'Room ' . $i;
                $room = $r->addRoom();

                if ($cnt > 1) {
                    if (preg_match("/{$prefix}[:\s]+([-A-Z\d]{5,})(?:[ (]|$)/", $confNoValue, $m)) {
                        $room->setConfirmation($m[1]);
                    }
                }

                if (preg_match("/\b{$prefix}[:\s]+(.+?)(?:Room \d{1,3}|$)/m", $roomType, $m)) {
                    $room->setDescription($m[1]);
                } elseif ($roomType) {
                    $room->setDescription(preg_replace('/\s+/', ' ', $roomType));
                }

                $room->setRate($roomPrice, false, true);
            }
        }

        $cancellation = $this->http->FindSingleNode("descendant::tr[count(*)=2 and *[1][{$this->starts($this->t('CANCELLATION POLICY:'))}]]/*[2]", $rRoot, false);
        $r->general()->cancellation($cancellation, false, true);

        if ($cancellation) {
            $r->booked()->parseNonRefundable('your Priceline hotel reservation is non-refundable');
        }

        if (!empty($status = $this->http->FindPreg("/You\s+have\s+now\s+(.*?)\s+your\s+reservation/is"))) {
            $r->general()->status($status);
        }
    }

    private function detectBody(): bool
    {
        foreach (self::$detectors as $phrases) {
            foreach ($phrases as $phrase) {
                if ($this->http->XPath->query("//*[{$this->contains($phrase)}]")->length > 0) {
                    return true;
                }
            }
        }

        return false;
    }

    private function assignLang(): bool
    {
        foreach (self::$dictionary as $lang => $words) {
            if (isset($words["firstLangDetect"], $words["lastLangDetect"])) {
                if ($this->http->XPath->query("//*[{$this->contains($words['firstLangDetect'])}]")->length > 0
                    && $this->http->XPath->query("//*[{$this->contains($words['lastLangDetect'])}]")->length > 0
                ) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
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

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
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

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s, '/'));
        }, $field)) . ')';
    }

    private function normalizeDate($str)
    {
        // $this->logger->debug('$str in = '.print_r( $str,true));
        $in = [
            // Thursday, September 03, 2020 (15:00 - 00:00)
            "/^(\w+, \w+ \d+, \d{4}) \((?:\d+:\d+\s+-\s+)?(\d+:\d+)\)$/",
            // Tue., October 4, 2022 - 12:00 p.m.
            "/^[^\d\s]+,\s+([^\d\s]+)\s+(\d+),\s+(\d{4})[\s-]+[\(]?(\d+:\d+\s+[AP]\.?M\.?)[\)]?$/iu",
            // miércoles 1 de enero de 2020 04:00 PM
            "/^\w+ (\d+) de (\w+) de (\d{4}) (\d+:\d+(?:\s*[AP]M)?)$/u",
            "/^([A-z]{3})\s+([A-z]{3})\s+(\d{1,2})$/u",
        ];
        $out = [
            "$1, $2",
            "$2 $1 $3, $4",
            "$1 $2 $3, $4",
            "$1 $3 $2",
        ];
        $str = preg_replace($in, $out, $str);
        $str = preg_replace('/(\s*\+[ap])\.m\.\s*$/i', '$1m', $str);

        // $this->logger->debug('$str out = '.print_r( $str,true));

        if (preg_match("/\d+\s+([^\d\s]+)(?:\s+\d{4}|)/", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        if (preg_match("/^([A-z]{3})\s(\d{1,2}\s[A-z]+)$/", $str, $m)) {
            if ($this->year) {
                $weeknum = WeekTranslate::number1(WeekTranslate::translate($m[1], $this->lang));
                $str = EmailDateHelper::parseDateUsingWeekDay($m[2] . ' ' . $this->year, $weeknum);
            } else {
                $str = null;
            }
        }

        if (!is_string($str)) {
            return $str;
        }

        return strtotime($str);
    }

    /**
     * Formatting over 3 steps:
     * 11 507.00  ->  11507.00
     * 2,790      ->  2790    |    4.100,00  ->  4100,00    |    1'619.40  ->  1619.40
     * 18800,00   ->  18800.00  |  2777,0    ->  2777.0.
     *
     * @param string|null $s Unformatted string with amount
     * @param string|null $decimals Symbols floating-point when non-standard decimals (example: 1,258.943)
     */
    private function normalizeAmount(?string $s, ?string $decimals = null): ?float
    {
        if (!empty($decimals) && preg_match_all('/(?:\d+|' . preg_quote($decimals, '/') . ')/', $s, $m)) {
            $s = implode('', $m[0]);

            if ($decimals !== '.') {
                $s = str_replace($decimals, '.', $s);
            }
        } else {
            $s = preg_replace('/\s+/', '', $s);
            $s = preg_replace('/[,.\'](\d{3})/', '$1', $s);
            $s = preg_replace('/,(\d{1,2})$/', '.$1', $s);
        }

        return is_numeric($s) ? (float) $s : null;
    }

    private function nextText($field, $root = null)
    {
        $rule = $this->eq($field);

        return $this->http->FindSingleNode("(.//text()[{$rule}])[1]/following::text()[normalize-space(.)][1]", $root);
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

    /**
     * @param string|null $string Provider keyword
     *
     * @return string|null Provider code
     */
    private function normalizeProvider(?string $string): ?string
    {
        $string = trim($string);

        foreach (self::$providers as $code => $keywords) {
            foreach ($keywords as $keyword) {
                if (strcasecmp($string, $keyword) === 0) {
                    return $code;
                }
            }
        }

        return null;
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
}
