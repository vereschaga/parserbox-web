<?php

namespace AwardWallet\Engine\ticketse\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Engine\WeekTranslate;

class AirTicket extends \TAccountChecker
{
    public $mailFiles = "ticketse/it-12234198.eml, ticketse/it-27285476.eml, ticketse/it-27465950.eml, ticketse/it-47252147.eml, ticketse/it-6426853.eml, ticketse/it-6487709.eml, ticketse/it-6613285.eml, ticketse/it-6696075.eml, ticketse/it-672973959.eml, ticketse/it-674034508.eml, ticketse/it-674436189.eml, ticketse/it-7572239.eml, ticketse/it-7606509.eml";

    public static $dictionary = [
        "sv" => [
            "Prisspecifikation"       => ["Prisspecifikation", "Pris och betalning"],
            "Färdhandlingar"          => ["Färdhandlingar", "Biljetter", 'Beskrivning'],
            "Din beställning - Order" => ["Din beställning - Order", "Din bokning - Order"],

            // HOTEL
            //            "Boknr:" => "",
            //            "Antal nätter" => "",
            //            "Avgiften är ca" => "",
            //            "per natt" => "",
        ],
        "no" => [
            //			"Ombokning" => "",
            "Avgång"         => "Avgang",
            "Ankomst"        => "Ankomst",
            "Färdhandlingar" => "Reisedokumenter",
            //			"Beskrivning" => "",
            "Bokningsnummer"          => "Bestillingsnummer",
            "Din beställning - Order" => "Din bestilling - Ordre",
            "Resenärer"               => "Reisende",
            "Utresa"                  => "Utreise",
            "Hemresa"                 => "Hjemreise",
            "Prisspecifikation"       => "Pris og betaling",
            "Totalt"                  => "Totalt",
            "Betalt"                  => "Betalt",
            // HOTEL
            //			"Boknr:" => "",
            //			"Antal nätter" => "",
            //			"Avgiften är ca" => "",
            //			"per natt" => "",
        ],
        'en' => [
            //			"Ombokning" => "",
            "Avgång"                  => "Departure",
            "Ankomst"                 => "Arrival",
            "Prisspecifikation"       => "Price and payment",
            "Färdhandlingar"          => "Travel documents",
            "Beskrivning"             => "Description",
            "Bokningsnummer"          => ["Booking number", "booking number"],
            "Din beställning - Order" => "Your order - Order",
            "Resenärer"               => "travelers",
            "Utresa"                  => "Departure",
            "Hemresa"                 => "Returning",
            "Totalt"                  => ["Overall", "Total"],
            "Betalt"                  => "Paid",
            // HOTEL
            //			"Boknr:" => "",
            //			"Antal nätter" => "",
            //			"Avgiften är ca" => "",
            //			"per natt" => "",
        ],
        'da' => [
            //			"Ombokning" => "",
            "Avgång"  => "Afgang",
            "Ankomst" => "Ankomst",
            //            "Prisspecifikation" => "",
            "Färdhandlingar" => "Rejsedokumenter",
            //			"Beskrivning" => "",
            "Bokningsnummer"          => ["Bookingnummer", "Bokningsnummer"],
            "Din beställning - Order" => "Din booking - Ordre",
            "Resenärer"               => "Rejsende",
            "Utresa"                  => "Udrejse",
            "Hemresa"                 => "Hjemrejse",
            "Totalt"                  => "Totalt",
            "Betalt"                  => "Betalt",
            // HOTEL
            //			"Boknr:" => "",
            //			"Antal nätter" => "",
            //			"Avgiften är ca" => "",
            //			"per natt" => "",
        ],
    ];

    private $subjects = [
        'sv' => ['Betalningsbekräftelse Order', 'Tack för din beställning av order'],
        'da' => ['Information om Order'],
        'no' => ['Din billett - ordre', 'Takk for din betaling! Ordre'],
    ];

    private static $bodyDetects = [
        'sv' => ['För att se din order online anger du även', 'Här kommer viktig information om din order', 'Tack för din beställning hos Ticket', 'Tack för att du valt Ticket'],
        'no' => ['Takk for at du valgte Ticket', 'Her kommer viktig informasjon om din ordre', 'Fullstendig informasjon om din ordre vil bli sendt', 'Takk for at du velger Ticket.'],
        'en' => 'Thank you for choosing Ticketmaster',
        'da' => ['Tak fordi du har valgt Ticket', 'Tak for din betaling!'],
    ];

    private $lang = '';
    private $emailDate;

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->detectBody();

        $result = [
            'emailType' => 'AirTicket' . ucfirst($this->lang),
        ];

        return $this->parseEmail($result);
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        return $this->detectBody();
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, 'ticket.se') !== false
            || stripos($from, 'ticket.no') !== false
            || stripos($from, 'ticket.dk') !== false
            || stripos($from, 'ticket.fi') !== false
            || stripos($from, 'Charter.se') !== false
            ;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (self::detectEmailFromProvider($headers['from']) !== true) {
            return false;
        }

        foreach ($this->subjects as $phrases) {
            foreach ($phrases as $phrase) {
                if (stripos($headers['subject'], $phrase) !== false) {
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
        return count(self::$dictionary);
    }

    private function parseEmail($result): array
    {
        $xpathTime = '(starts-with(translate(normalize-space(),"0123456789：.Hh ","∆∆∆∆∆∆∆∆∆∆::::"),"∆:∆∆") or starts-with(translate(normalize-space(),"0123456789：.Hh ","∆∆∆∆∆∆∆∆∆∆::::"),"∆∆:∆∆"))';

        $its = [];

        $tripNumber = $this->http->FindSingleNode("//text()[{$this->contains($this->t("Din beställning - Order"))}]", null, true, "/{$this->opt($this->t("Din beställning - Order"))}:?\s+(\d+)/");

        $travellers = $this->http->FindNodes("//div[{$this->contains($this->t("Resenärer"))}]/following-sibling::table[1]/descendant::img[contains(@src, 'mail/ticket/mail-bullet') or contains(@src, 'ticket/mail-traveller')]/ancestor::td[1]/following-sibling::td[1]");

        ////////////
        // FLIGHT //
        ////////////

        $xpath = "//*[not(.//tr[normalize-space()]) and count(*[normalize-space()])<4]"
            . "[ *[normalize-space()][1]/descendant::text()[{$xpathTime}] ]"
            . "[ *[normalize-space()][2]/descendant::text()[{$xpathTime}] ]"
            . "[not(*[normalize-space()][3]/descendant::text()[{$xpathTime}])]"
        ;
        $segments = $this->http->XPath->query($xpath);

        if ($segments->length === 0) {
            $this->logger->debug('Segments not found by xpath: ' . $xpath);
        }

        if ($segments->length > 0) {
            // it-7572239.eml
            $recLocs = $this->http->FindNodes("//div[{$this->contains($this->t("Färdhandlingar"))}]/following-sibling::*[self::div or self::table][descendant::img[contains(@src,'ticket/mail-bullet') or contains(@src,'ticket/mail-flight')] and position()=1]/descendant::text()[{$this->contains($this->t("Bokningsnummer"))}]/following-sibling::node()[1]",
                null, '/^\s*([A-Z\d]{5,7})\s*$/');
            $recLocs = array_filter(array_unique($recLocs));

            // it-27465950.eml
            if (count($recLocs) === 0) {
                $priceRows = $this->http->XPath->query("//tr[not(.//tr) and ./descendant::text()[{$this->eq($this->t("Beskrivning"))}] and ./descendant::text()[{$this->eq($this->t("Bokningsnummer"))}]]/following-sibling::tr[ ./*[4] ]");

                foreach ($priceRows as $priceRow) {
                    $description = $this->http->FindSingleNode("./*[2]", $priceRow);
                    $bookingNumber = $this->http->FindSingleNode("./*[3]", $priceRow, true, '/^([A-Z\d]{5,7})$/');

                    if ($this->http->XPath->query('//node()[contains(normalize-space(.),"' . $description . '")]')->length > 0 && $bookingNumber) {
                        $recLocs[] = $bookingNumber;
                    }
                }
            }

            // it-47252147.eml
            if (count($recLocs) === 0) {
                $recLocs = $this->http->FindNodes("//tr/*[normalize-space()][1]/descendant::text()[normalize-space()][1][{$this->starts($this->t("Bokningsnummer"))}]/following::text()[normalize-space()][1]",
                    null, '/^[A-Z\d]{5,}$/');
                $recLocs = array_filter(array_unique($recLocs));
            }

            // it-6613285.eml
            if (count($recLocs) === 0) {
                $recLocs = [CONFNO_UNKNOWN];
            }

            $segmentsByRecLocs = array_fill_keys($recLocs, []);

            foreach ($segments as $root) {
                $rl = $this->http->FindSingleNode("following-sibling::tr[" . $this->contains($this->t("Bokningsnummer")) . " and not(descendant::tr)][1]",
                    $root, true, '/:\s*\b([A-Z\d]{5,7})\b/');

                if (empty($rl)) {
                    // it-47252147.eml
                    $rl = $this->http->FindSingleNode("ancestor::table[ preceding-sibling::table[normalize-space()] ][1]/preceding-sibling::table[normalize-space()][1]/descendant::tr/*[normalize-space()][1]/descendant::text()[normalize-space()][1][{$this->starts($this->t("Bokningsnummer"))}]/following::text()[normalize-space()][1]",
                        $root, true, '/^[A-Z\d]{5,}$/');
                }

                if (empty($rl) && count($recLocs) == 1) {
                    $rl = $recLocs[0];
                }

                if (empty($rl)) {
                    $rl = CONFNO_UNKNOWN;
                }
                $segmentsByRecLocs[$rl][] = $root;
            }
            $segmentsByRecLocs = array_filter($segmentsByRecLocs);

            // $this->logger->debug('$segmentsByRecLocs = '.print_r( $segmentsByRecLocs,true));

            foreach ($segmentsByRecLocs as $recLoc => $nodes) {
                /** @var \AwardWallet\ItineraryArrays\AirTrip $it */
                $it = ['Kind' => 'T', 'TripSegments' => []];

                $it['RecordLocator'] = $recLoc;

                if (empty($it['RecordLocator'])) {
                    $it['RecordLocator'] = CONFNO_UNKNOWN;
                }

                if ($tripNumber) {
                    $it['TripNumber'] = $tripNumber;
                }

                if (!empty($travellers)) {
                    $it['Passengers'] = $travellers;
                }

                $this->emailDate = $this->normalizeDate($this->http->FindNodes("//tr[not(.//tr)]/*[normalize-space()][1][descendant::text()[normalize-space()][1][{$this->eq($this->t("Utresa"))} or {$this->eq($this->t("Hemresa"))}]]",
                        null, "/^\s*(?:{$this->opt($this->t('Utresa'))}|{$this->opt($this->t('Hemresa'))})\s*(.*\d{4}.*)/u")[0] ?? null);

                $rlCond = "[{$this->contains($recLoc)}]";

                if (count($segmentsByRecLocs) == 1) {
                    $rlCond = '';
                } elseif ($recLoc === CONFNO_UNKNOWN) {
                    $rlCond = "[{$this->contains(array_diff(array_keys($segmentsByRecLocs)), [CONFNO_UNKNOWN])}]";
                }
                $total = $this->http->FindSingleNode("ancestor::*[name() = 'table' or name() = 'div']{$rlCond}/following-sibling::table[not(descendant::table) and {$this->contains($this->t("Betalt"))}][1]",
                    $nodes[0]);

                if (empty($total)) {
                    $total = $this->http->FindSingleNode("ancestor::*[name() = 'table' or name() = 'div']{$rlCond}/following-sibling::table[not(descendant::table) and {$this->contains($this->t("Totalt"))}][1]",
                        $nodes[0]);
                }

                if (preg_match('/\b([\d ]+)\s+([A-Z]{3})\b/', $total, $m)) {
                    $it['TotalCharge'] = PriceHelper::parse(trim($m[1]), $m[2]);
                    $it['Currency'] = $m[2];
                }

                foreach ($nodes as $root) {
                    /** @var \AwardWallet\ItineraryArrays\AirTripSegment $seg */
                    $seg = [];

                    $depTime = null;
                    $arrTime = null;

                    $depart = implode("\n", array_merge(
                        $this->http->FindNodes('*[1]/descendant::text()[normalize-space()]', $root),
                        $this->http->FindNodes('following-sibling::*[1]/*[1]/descendant::text()[normalize-space()]',
                            $root)
                    ));

                    $arrive = implode("\n", array_merge(
                        $this->http->FindNodes('*[2]/descendant::text()[normalize-space()]', $root),
                        $this->http->FindNodes('following-sibling::*[1]/*[2]/descendant::text()[normalize-space()]',
                            $root)
                    ));

                    $re = '/^\s*(?:[^\d\n]+\n)?(?<time>\d{1,2}:\d{2})\s+(?<name>.+?)\s+\((?<code>[A-Z]{3})\)\n\s*(?<date>\d{1,2}\s+[[:alpha:]]+)[.]?(?:\s+(?<al>[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(?<fn>\d+))?\s*(\n|$)/u';

                    if (preg_match($re, $depart, $m)) {
                        $seg['DepName'] = $m['name'];
                        $seg['DepCode'] = $m['code'];
                        $seg['DepDate'] = $this->normalizeDate($m['date'] . ', ' . $m['time']);

                        if (!empty($m['al'])) {
                            $seg['AirlineName'] = $m['al'];
                            $seg['FlightNumber'] = $m['fn'];
                        }
                    }

                    if (empty($seg['AirlineName'])) {
                        $flight = implode("\n",
                            array_merge($this->http->FindNodes('*[3]/descendant::text()[normalize-space()]', $root)));

                        if (preg_match("/^\s*(?<al>[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(?<fn>\d{1,5})\s*$/m", $flight, $m)) {
                            $seg['AirlineName'] = $m['al'];
                            $seg['FlightNumber'] = $m['fn'];
                        }
                    }

                    if (preg_match($re, $arrive, $m)) {
                        $seg['ArrName'] = $m['name'];
                        $seg['ArrCode'] = $m['code'];
                        $seg['ArrDate'] = $this->normalizeDate($m['date'] . ', ' . $m['time']);
                    }

                    if (empty($seg['AirlineName'])) {
                        // it-27465950.eml
                        $airlineFull = $this->http->FindSingleNode("./ancestor::table[ ./preceding-sibling::table[normalize-space(.)] ][1]/preceding-sibling::table[normalize-space(.)][last()]",
                            $root);
                        $seg['AirlineName'] = preg_replace("/^{$this->opt($this->t("Ombokning"))}\s*/", '',
                            $airlineFull);
                    }

                    if (empty($seg['FlightNumber']) && !empty($seg['AirlineName'])) {
                        $seg['FlightNumber'] = FLIGHT_NUMBER_UNKNOWN;
                    }

                    $it['TripSegments'][] = $seg;
                }

                $its[] = $it;
            }
        }
        /////////////
        // HOTEL 1 //
        /////////////

        // it-27285476.eml
        $hotels = $this->http->XPath->query("//tr[not(.//tr) and {$this->contains($this->t("Ankomst"))} and {$this->contains($this->t("Antal nätter"))}]");

        foreach ($hotels as $root) {
            $it = [];
            $it['Kind'] = 'R';

            // TripNumber
            if ($tripNumber) {
                $it['TripNumber'] = $tripNumber;
            }

            // GuestNames
            if (!empty($travellers)) {
                $it['GuestNames'] = $travellers;
            }

            // HotelName
            $hotelName = $this->http->FindSingleNode("./preceding::tr[normalize-space(.)][position()<4][{$this->contains($this->t("Boknr:"))}]/descendant::text()[normalize-space(.)][1]", $root, true, '/^([^(]{3,}\b)/');
            $it['HotelName'] = $hotelName;

            // ConfirmationNumber
            $confNumber = $this->http->FindSingleNode("./preceding::text()[normalize-space(.)][position()<8][{$this->eq($this->t("Boknr:"))}]/following::text()[normalize-space(.)][1]", $root, true, '/^([A-Z\d]{5,})$/');
            $it['ConfirmationNumber'] = $confNumber;

            $year = '';
            $patterns['date'] = '\d{1,2} +[^\d\W]{3,} +\d{4}'; // 3 feb 2019

            if ($confNumber) {
                $dates = $this->http->FindSingleNode("//tr[not(.//tr) and {$this->contains($this->t("Bokningsnummer"))} and {$this->contains($confNumber)}]", null, true, "/\b({$patterns['date']} *- *{$patterns['date']})\b/u");
//                $datesParts = preg_split('/ *- */', $dates);
                if (preg_match('/\D(\d{4}) *-/', $dates, $matches)) {
                    $year = $matches[1];
                }
            }

            $xpathFragmentDates = "./following::tr[ ./*[3][normalize-space(.)] ][1]";
            $patterns['dateTime'] = '(?<day>\d{1,2})\s+(?<month>[^\d\W]{3,})\s*(?<time>\d{1,2}(?::\d{2})?(?:\s*[AaPp]\.?[Mm]\.?|\s*noon|\s*午[前後])?)'; // 3 feb 15:00

            // CheckInDate
            $dateTimeCheckIn = $this->http->FindSingleNode($xpathFragmentDates . '/*[1]', $root);

            if ($year && preg_match("/^({$patterns['dateTime']})/u", $dateTimeCheckIn, $m)) {
                $it['CheckInDate'] = $this->getDate($m['day'], $m['month'], $year, $m['time']);
            }

            // CheckOutDate
            $dateTimeCheckOut = $this->http->FindSingleNode($xpathFragmentDates . '/*[2]', $root);

            if ($year && preg_match("/^({$patterns['dateTime']})/u", $dateTimeCheckOut, $m)) {
                $it['CheckOutDate'] = $this->getDate($m['day'], $m['month'], $year, $m['time']);
            }

            $xpathFragmentDescription = "./ancestor::tr[ ./descendant::text()[{$this->starts($this->t("Boknr:"))}] ][1]";

            // Address
            $address = $this->http->FindSingleNode($xpathFragmentDescription . "/descendant::text()[{$this->starts($this->t("Adress:"))}]", $root, true, "/{$this->opt($this->t("Adress:"))}\s*(.+)/");
            $it['Address'] = $address;

            // Rate
            $rate = $this->http->FindSingleNode($xpathFragmentDescription . "/descendant::text()[{$this->contains($this->t("per natt"))}]", $root, true, "/{$this->opt($this->t("Avgiften är ca"))}\s*(.+?\s*{$this->opt($this->t("per natt"))})/");

            if ($rate) {
                // Avgiften är ca 10-20 AED per natt och rum
                $it['Rate'] = $rate;
            }

            // Total
            // Currency
            $payment = $this->http->FindSingleNode("/descendant::table[not(.//table) and {$this->contains($this->t("Totalt"))}]", $root);

            if (preg_match('/([\d ]+)\s+([A-Z]{3})\b/', $payment, $m)) {
                $it['Total'] = PriceHelper::parse(trim($m[1]), $m[2]);
                $it['Currency'] = $m[2];
            }

            $its[] = $it;
        }
        /////////////
        // HOTEL 2 //
        /////////////

        // it-27285476.eml
        $hotels = $this->http->XPath->query("//*[count(*[normalize-space()]) = 2][*[1]//text()[{$this->eq($this->t('Adress:'))}]][*[last()]//text()[{$this->eq($this->t('Incheckning:'))}]]");

        foreach ($hotels as $root) {
            $it = [];
            $it = ['Kind' => 'R'];

            // TripNumber
            if ($tripNumber) {
                $it['TripNumber'] = $tripNumber;
            }

            // GuestNames
            if (!empty($travellers)) {
                $it['GuestNames'] = $travellers;
            }

            $confNumber = $this->http->FindSingleNode(".//text()[{$this->eq($this->t("Boknr:"))}]/following::text()[normalize-space(.)][1]", $root, true, '/^([A-Z\d\-]{5,})$/');
            $it['ConfirmationNumber'] = $confNumber;
            $it['HotelName'] = $this->http->FindSingleNode(".//img[contains(@src, 'mail-star')][1]/preceding-sibling::node()[1]", $root);
            $it['CheckInDate'] = strtotime($this->http->FindSingleNode(".//*[contains(text(), 'Incheckning')]/following-sibling::node()[1]", $root));
            $it['CheckOutDate'] = strtotime($this->http->FindSingleNode(".//*[contains(text(), 'Utcheckning')]/following-sibling::node()[1]", $root));
            $it['Address'] = implode(' ', $this->http->FindNodes(".//p[{$this->eq($this->t('Adress:'))}]/following-sibling::p[1]//text()[normalize-space()]", $root));
            $it['Phone'] = $this->http->FindSingleNode(".//a[contains(., 'Mer info')]/following-sibling::*[1]", $root);

            $roomXpath = "//node()[{$this->eq($this->t('Rum %'), 'translate(normalize-space(),"0123456789","%%%%%%%%%%")')} or {$this->eq($this->t('Rum'))}]";
            $it['RoomType'] = $this->http->FindSingleNode($roomXpath . "/following::text()[normalize-space()][1]", $root, true, '/^\s*(?:\d+\s+x\s+)?(.+)/');

            $total = $this->http->FindSingleNode(".//td[{$this->starts($this->t('Totalt'))}][not(descendant::td)]", $root);

            if (empty($total)) {
                $total = $this->http->FindSingleNode("./following::text()[normalize-space()][{$this->eq($this->t('Totalt'))}]/ancestor::td[not(descendant::td)][{$this->starts($this->t('Totalt'))}]", $root);
            }

            if (preg_match('/(\d+)\s+([A-Z]{3})/', $total, $m)) {
                $it['Total'] = $m[1];
                $it['Currency'] = $m[2];
            }

            $its[] = $it;
        }

        $result['parsedData']['Itineraries'] = $its;

        // Amount
        // Currency
        $totalPrice = $this->http->FindSingleNode("//text()[{$this->eq($this->t("Prisspecifikation"))}]/following::tr[{$this->starts($this->t("Totalt"))}]");
        // it-27465950.eml
        if (preg_match("/^{$this->opt($this->t("Totalt"))}\s*(?<amount>\d[,.\'\d ]*)\s+(?<currency>[A-Z]{3})\b/", $totalPrice, $matches)) {
            // 1 350 SEK
            $result['parsedData']['TotalCharge']['Amount'] = PriceHelper::parse(trim($matches[1]), $matches[2]);
            $result['parsedData']['TotalCharge']['Currency'] = $matches[2];
        }

        return $result;
    }

    /**
     * @param type $day
     * @param type $month
     * @param type $year
     * @param type $time
     */
    private function getDate($day, $month, $year, $time)
    {
        if ($nm = MonthTranslate::translate($month, $this->lang)) {
            $month = $nm;
        }

        return strtotime($day . ' ' . $month . ' ' . $year . ', ' . $time);
    }

    // In order to determine by what year the segment applies
    private function outboundFlight($str, \DOMNode $root)
    {
        if ($this->http->XPath->query("ancestor::table[descendant::table and not({$this->contains($this->t("Utresa"))} or {$this->contains($this->t("Hemresa"))})][last()]/preceding-sibling::table[{$this->contains($str)} and position()=1]", $root)->length === 1) {
            return true;
        }

        return false;
    }

    private function detectBody(): bool
    {
        if ($this->http->XPath->query("//*[{$this->contains(['ticket.no', 'ticket.se', 'ticket.dk'])}] | //a/@href[{$this->contains(['ticket.no', 'ticket.se', 'ticket.dk'])}]")->length === 0
        ) {
            return false;
        }

        foreach (self::$bodyDetects as $lang => $bodyDetect) {
            if ($this->http->XPath->query("//*[{$this->contains($bodyDetect)}]")->length > 0
            ) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
    }

    private function normalizeDate($date)
    {
        // $this->logger->debug('$date in = '.print_r( $date,true));
        $year = date("Y", $this->emailDate);

        $in = [
            // Friday, August 4, 2017
            '/^\s*[[:alpha:]]+,\s*([[:alpha:]]+)\s+(\d+),\s*(\d{4})\s*$/iu',
            // Måndag 3 juli 2017
            '/^\s*[[:alpha:]]+\s+(\d+)\s+([[:alpha:]]+)\s+(\d{4})\s*$/iu',
            // 4 aug, 17:00
            '/^\s*(\d+)\s*([[:alpha:]]+)\,\s*(\d{1,2}:\d{2})\s*$/iu',
        ];
        // $year - for date without year and with week
        // %year% - for date without year and without week
        $out = [
            '$2 $1 $3',
            '$1 $2 $3',
            '$1 $2 %year%, $3',
        ];
        $date = preg_replace($in, $out, $date);
        // $this->logger->debug('$date out = '.print_r( $date,true));

        if (preg_match("#\d+\s+([^\d\s]+)\s+(?:\d{4}|%year%)#", $date, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $date = str_replace($m[1], $en, $date);
            }
        }

        if (!empty($this->emailDate) && $this->emailDate > strtotime('01.01.2000') && strpos($date, '%year%') !== false
            && preg_match('/^\s*(?<date>\d+ \w+) %year%(?:\s*,\s(?<time>\d{1,2}:\d{2}.*))?$/', $date, $m)) {
            // $this->logger->debug('$date (no week, no year) = '.print_r( $m['date'],true));
            $date = EmailDateHelper::parseDateRelative($m['date'], $this->emailDate);

            if (!empty($date) && !empty($m['time'])) {
                return strtotime($m['time'], $date);
            }

            return $date;
        } elseif ($year > 2000 && preg_match("/^(?<week>\w+), (?<date>\d+ \w+ .+)/u", $date, $m)) {
            // $this->logger->debug('$date (week no year) = '.print_r( $string,true));
            $weeknum = WeekTranslate::number1(WeekTranslate::translate($m['week'], $this->lang));

            return EmailDateHelper::parseDateUsingWeekDay($m['date'], $weeknum);
        } elseif (preg_match("/^\d+ [[:alpha:]]+ \d{4}(,\s*\d{1,2}:\d{2}(?: ?[ap]m)?)?$/ui", $date)) {
            // $this->logger->debug('$date (year) = '.print_r( $str,true));
            return strtotime($date);
        } else {
            return null;
        }

        return null;
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function eq($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) { return 'normalize-space(' . $node . ')="' . $s . '"'; }, $field)) . ')';
    }

    private function starts($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) { return 'starts-with(normalize-space(' . $node . '),"' . $s . '")'; }, $field)) . ')';
    }

    private function contains($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) { return 'contains(normalize-space(' . $node . '),"' . $s . '")'; }, $field)) . ')';
    }

    private function opt($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return '';
        }

        return '(?:' . implode('|', array_map(function ($s) { return preg_quote($s, '/'); }, $field)) . ')';
    }
}
