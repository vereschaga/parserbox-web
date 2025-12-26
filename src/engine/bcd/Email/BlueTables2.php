<?php

namespace AwardWallet\Engine\bcd\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Engine\WeekTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class BlueTables2 extends \TAccountChecker
{
    public $mailFiles = "bcd/it-1740221.eml, bcd/it-1794782.eml, bcd/it-1803266.eml, bcd/it-1901175.eml, bcd/it-2633998.eml, bcd/it-3023342.eml, bcd/it-5337720.eml, bcd/it-5422822.eml, bcd/it-119924517.eml";

    public $lang = "en";
    private $reFrom = "@bcdtravel.com";
    private $reSubject = [
        "en" => "Booking Confirmation",
        "de" => "Reiseplan für",
        "fr" => "Billet électronique pour",
        "sv" => "Resplan för",
    ];
    private $reBody = [
        'bcd' => ['BCD Travel', 'bcd.compleattrip.com'],
    ];
    private $reBody2 = [
        "en" => ["Itinerary"],
        "de" => ["Reiseplan"],
        "fr" => ["Reçu de billet électronique", "Itinéraire"],
        "sv" => ["Resplan"],
    ];

    private static $dictionary = [
        "en" => [
            /* Before itineraries */
            // "Please print this receipt" => '',
            // "E-Ticket Number" => '',
            // "Traveller(s)" => '',
            // "Booking Reference" => '',
            // "Itinerary Details" => '',
            // "Class" => '',

            /* All types */
            // "Reference:" => '',

            /* Flight */
            "Air -" => ["Air -", "AIR -"],
            // "Depart:" => '', // + bus, train
            // "Arrive:" => '', // + bus, train
            // "Operated by:" => '',
            // "Equipment:" => '',
            // "Distance:" => '',
            // "Duration:" => '',
            // "Non-stop" => '',
            // "Seat:" => '', // + train
            // "Loyalty Number:" => '',
            // 'Flight' => '',

            /* Hotel */
            // "Hotel -" => '',
            // "Address:" => '',
            "Tel.:" => ["Tel.:", "Tel:"], // + rental
            // "Fax:" => '', // + rental
            // "Check In / Check Out:" => '',
            // "Rate per night:" => '',
            // "Total:" => '',// + rental, transfer
            // "Cancellation Policy:" => '',
            // "Description:" => '',

            /* Rental */
            // "Car -" => '',
            // "Pick Up:" => '', // + transfer
            // "Drop Off:" => '', // + transfer

            /* Bus */
            // "Bus -" => '',

            /* Train */
            // "Rail -" => '',
            'Train Number' => ["Train Number", "Train number"],
            // "Coach:" => '',
            // "Class:" => '',
            // "Pick up/Drop off Location" => '',

            /* Transfer */
            // "Taxi -" => '',
            // "Additional Information:" => '',
            // "Taxi Type:" => '',

            /* All Price */
            // "Estimated Trip Total:" => '',
            // "Fare and Ticket Details" => '',
            // "Ticket Number:" => '',
        ],
        "de" => [ // it-1740221.eml, it-1794782.eml, it-1901175.eml, it-2633998.eml
            /* Before itineraries */
            "Please print this receipt" => "Bitte drucken Sie sich diesen Itinerary Receipt",
            "E-Ticket Number"           => "E-Ticket-Nummer",
            "Traveller(s)"              => "Reisende(r)",
            "Booking Reference"         => 'Buchungscode',
            "Itinerary Details"         => "Leistung",
            "Class"                     => "Klasse",

            /* All types */
            "Reference:" => "Buchungsreferenz:",

            /* Flight */
            "Air -"        => "Flug -",
            "Depart:"      => 'Abreise:', // + bus, train
            "Arrive:"      => 'Ankunft:', // + bus, train
            "Operated by:" => 'Durchgeführt von:',
            "Equipment:"   => 'Fluggerät:',
            // "Distance:" => '',
            "Duration:"       => 'Dauer:',
            "Non-stop"        => 'non-stop',
            "Seat:"           => 'Sitzplatz:', // + train
            "Loyalty Number:" => 'Mitgliedsnummer:',
            "Flight"          => "Flug",

            /* Hotel */
            "Hotel -"               => 'Hotel -',
            "Address:"              => 'Adresse:',
            "Tel.:"                 => ["Tel.:", "Tel:"], // + rental
            "Fax:"                  => 'Fax:', // + rental
            "Check In / Check Out:" => 'Anreise / Abreise:',
            "Rate per night:"       => 'Preis pro Nacht:',
            "Total:"                => ["Gesamtpreis:", "Gesamt:"], // + rental, transfer
            "Cancellation Policy:"  => 'Stornobedingung:',
            "Description:"          => 'Beschreibung:',

            /* Rental */
            "Car -"     => "Mietwagen -",
            "Pick Up:"  => ["Anmietung:", "Zustellung:"], // + transfer
            "Drop Off:" => ["Abgabe:", "Abholung:"], // + transfer

            /* Bus */
            // "Bus -" => '',

            /* Train */
            "Rail -"       => "Bahn -",
            'Train Number' => 'Zugnummer',
            // "Coach:" => '',
            "Class:" => 'Klasse:',
            // "Pick up/Drop off Location" => '',

            /* Transfer */
            // "Taxi -" => '',
            // "Additional Information:" => '',
            // "Taxi Type:" => '',

            /* All Price */
            "Estimated Trip Total:"     => 'Voraussichtlicher Gesamtreisepreis:',
            "Fare and Ticket Details"   => ["Tarif- und Ticketdetails", "Tarif und Ticketübersicht"],
            "Ticket Number:"            => 'Ticketnummer:',
        ],
        "fr" => [
            /* Before itineraries */
            // "Please print this receipt" => '',
            // "E-Ticket Number" => '',
            "Traveller(s)"      => 'Voyageur(s)',
            "Booking Reference" => 'Référence de réservation',
            "Itinerary Details" => 'Itinéraire détaillé',
            "Class"             => 'Classe',

            /* All types */
            "Reference:" => 'Référence:',

            /* Flight */
            "Air -"        => "AIR -",
            "Depart:"      => ["Départ :", "Départ:"], // + bus, train
            "Arrive:"      => "Arrivée:", // + bus, train
            "Operated by:" => 'Opéré par:',
            "Equipment:"   => 'Appareil:',
            // "Distance:" => '',
            "Duration:"       => 'Temps de trajet :',
            "Non-stop"        => 'non-stop',
            "Seat:"           => 'Siège:', // + train
            "Loyalty Number:" => 'Numéro de fidélité:',
            'Flight'          => 'Vol',

            /* Hotel */
            "Hotel -"               => 'HÔTEL -',
            "Address:"              => 'Adresse:',
            "Tel.:"                 => ["Tel.:", "Tel:"], // + rental
            "Fax:"                  => 'Fax:', // + rental
            "Check In / Check Out:" => 'Arrivée / Départ:',
            "Rate per night:"       => 'Tarif par nuit :',
            "Total:"                => 'Total:', // + rental, transfer
            "Cancellation Policy:"  => 'Politique d’annulation:',
            "Description:"          => 'Descriptif:',

            /* Rental */
            "Car -" => 'VOITURE -',
            // "Pick Up:" => '', // + transfer
            // "Drop Off:" => '', // + transfer

            /* Bus */
            // "Bus -" => '',

            /* Train */
            "Rail -"       => ["RAIL -", "RAIL - "],
            'Train Number' => ["Train Numéro"],
            "Coach:"       => 'Voiture:',
            "Class:"       => 'Classe:',
            // "Pick up/Drop off Location" => '',

            /* Transfer */
            // "Taxi -" => '',
            // "Additional Information:" => '',
            // "Taxi Type:" => '',

            /* All Price */
            "Estimated Trip Total:"   => 'Estimation du prix total du trajet:',
            "Fare and Ticket Details" => 'Détails des prix et billets',
            // "Ticket Number:" => '',
        ],
        "sv" => [
            /* Before itineraries */
            // "Please print this receipt" => "",
            // "E-Ticket Number"           => "",
            // "Traveller(s)"              => "",
            // "Booking Reference" => '',
            "Itinerary Details"         => "Resplan detaljer",
            "Class"                     => "Klass",

            /* All types */
            "Reference:" => "Referens:",

            /* Flight */
            "Air -"   => "FLYG -",
            "Depart:" => 'Avresa:', // + bus, train
            "Arrive:" => 'Ankomst:', // + bus, train
            // "Operated by:" => ':',
            "Equipment:" => 'Utrustning:',
            // "Distance:" => '',
            "Duration:" => 'Varaktighet:',
            // "Non-stop" => '',
            "Seat:" => 'Plats:', // + train
            // "Loyalty Number:" => ':',
            "Flight" => "Flyg",

            /* Hotel */
            "Hotel -" => 'HOTELL -',
            // "Address:" => '',
            "Tel.:" => "Telefon:", // + rental
            // "Fax:" => '', // + rental
            // "Check In / Check Out:" => '',
            // "Rate per night:" => '',
            "Total:" => 'Totalt:', // + rental, transfer
            // "Cancellation Policy:" => '',
            // "Description:" => '',

            /* Rental */
            "Car -" => 'BIL -',
            // "Pick Up:" => '', // + transfer
            // "Drop Off:" => '', // + transfer

            /* Bus */
            // "Bus -" => '',

            /* Train */
            "Rail -"       => 'Rail -',
            'Train Number' => 'Tågnummer',
            // "Coach:" => '',
            "Class:" => 'Klass:',
            // "Pick up/Drop off Location" => '',

            /* Transfer */
            // "Taxi -" => '',
            // "Additional Information:" => '',
            // "Taxi Type:" => '',

            /* All Price */
            "Estimated Trip Total:"   => 'Beräknat totalpris:',
            "Fare and Ticket Details" => 'Pris och biljettinformation',
            "Ticket Number:"          => 'Biljettnummer:',
        ],
    ];
    private $date = null;

    private $namePrefixes = ['MISS', 'MRS', 'MR', 'MS', 'DR'];
    private $travellers = [];

    private $patterns = [
        // +377 (93) 15 48 52    |    (+351) 21 342 09 07    |    713.680.2992    |    8/ 47 30 470
        'phone' => '[+(\d][-+. \/\d)(]{5,}[\d)]',
        // KOH / KIM LENG MR
        'travellerName' => '[[:upper:]]+(?: [[:upper:]]+)*[ ]*\/[ ]*(?:[[:upper:]]+ )*[[:upper:]]+',
        // 075-2345005149-02    |    0167544038003-004
        'eTicket' => '\d{3}(?: | ?- ?)?\d{5,}(?: | ?- ?)?\d{1,3}',
    ];

    public function detectEmailFromProvider($from)
    {
        return strpos($from, $this->reFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (strpos($headers["from"], $this->reFrom) === false) {
            return false;
        }

        foreach ($this->reSubject as $re) {
            if (stripos($headers["subject"], $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        if ($this->getProvider($body) === false) {
            return false;
        }

        foreach ($this->reBody2 as $lang => $reBody) {
            foreach ($reBody as $re) {
                if (stripos($body, $re) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->http->FilterHTML = true; // fixing damaged flight segments
        $this->http->SetEmailBody($this->http->Response['body']);

        $provider = $this->getProvider($parser->getHTMLBody());

        if ($provider === false) {
            $this->logger->error("provider not detected");

            return null;
        }
        $email->setProviderCode($provider);

        foreach ($this->reBody2 as $lang => $re1) {
            foreach ($re1 as $re) {
                if (strpos($this->http->Response["body"], $re) !== false) {
                    $this->lang = $lang;

                    break;
                }
            }
        }

        $this->parseHtml($email);

        $totalCurrency = $totalAmount = [];
        $totalPrice = array_filter($this->http->FindNodes("//text()[{$this->starts($this->t("Estimated Trip Total:"))}]", null, "/{$this->opt($this->t("Estimated Trip Total:"))}\s*(.+)$/"));

        foreach ($totalPrice as $tP) {
            if (preg_match('/^(?<amount>\d[,.\'\d ]*?)[ ]*(?<currency>[^\d)(]+)$/', $tP, $matches)
                || preg_match('/^(?<currency>[^\d)(]+?)[ ]*(?<amount>\d[,.\'\d ]*)$/', $tP, $matches)
            ) {
                // SEK 3980.00
                $currencyCode = preg_match('/^[A-Z]{3}$/', $matches['currency']) ? $matches['currency'] : null;
                $totalCurrency[] = $matches['currency'];
                $totalAmount[] = PriceHelper::parse($matches['amount'], $currencyCode);
            }
        }

        if (count($totalAmount) > 0 && count($totalAmount) === count($this->travellers) && count(array_unique($totalCurrency)) === 1) {
            $email->price()
                ->total(array_sum($totalAmount))
                ->currency($totalCurrency[0]);
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

    private function parseHtml(Email $email)
    {
        $xpathNoEmpty = 'string-length(normalize-space())>1';

        // Travel Agency
        $email->obtainTravelAgency();
        $conf = $this->http->FindSingleNode("//*[{$this->eq($this->t("Traveller(s)"))}]/following-sibling::*[{$this->contains($this->t('Booking Reference'))}]");

        if (preg_match("/^\s*(.*{$this->opt($this->t('Booking Reference'))})\s*([A-Z\d]{5,})\s*$/u", $conf, $m)) {
            $allConfs = $this->http->FindNodes("//text()[{$this->eq($this->t("Itinerary Details"))}]/ancestor::tr[1]/following-sibling::tr/descendant::*[{$xpathNoEmpty}][last()]/descendant::text()[normalize-space()][2]",
                null, "/^[A-Z\d\-]{5,}$/");
            $allConfs = array_merge($allConfs, $this->http->FindNodes(".//text()[" . $this->contains($this->t("Reference:")) . "]/ancestor::tr[1]",
                null, "#" . $this->opt($this->t("Reference:")) . "\s*([A-Z\d]+)#"));

            if (!in_array($m[2], $allConfs)) {
                $email->ota()
                    ->confirmation($m[2], $m[1]);
            }
        }

        $eTickets = [];
        $eTicketsText = $this->htmlToText($this->http->FindHTMLByXpath("//text()[{$this->starts($this->t("Please print this receipt"))}]/ancestor::td[count(descendant::text()[normalize-space()])>1][1]"));
        // E-Ticket Number: TF - 276-3854369685
        preg_match_all("/{$this->opt($this->t("E-Ticket Number"))}[: ]+(?<airline>[A-Z][A-Z\d]|[A-Z\d][A-Z])[ ]+-[ ]+(?<ticket>{$this->patterns['eTicket']})$/m", $eTicketsText, $ticketMatches, PREG_SET_ORDER);

        foreach ($ticketMatches as $m) {
            if (empty($eTickets[$m['airline']])) {
                $eTickets[$m['airline']] = [$m['ticket']];
            } else {
                $eTickets[$m['airline']][] = $m['ticket'];
            }
        }

        $this->travellers = [];
        $travellersText = $this->htmlToText($this->http->FindHTMLByXpath("//tr[ *[normalize-space()][2][{$this->eq($this->t("Fare and Ticket Details"))}] ]/*[normalize-space()][1]"));

        if (empty($travellersText)) {
            // it-3023342.eml
            $travellersText = $this->htmlToText($this->http->FindHTMLByXpath("//text()[{$this->eq($this->t("Traveller(s)"))}]/following::tr[normalize-space()][1][not(descendant::text()[{$this->eq($this->t('Flight'))}])]/ancestor-or-self::tr[1]/*[normalize-space()][1]"));
        }

        foreach (preg_split("/[ ]*\n+[ ]*/", $travellersText) as $tName) {
            if (preg_match("/^{$this->patterns['travellerName']}$/u", $tName)) {
                $this->travellers[] = preg_replace("/^(.{2,}?)\s+{$this->opt($this->namePrefixes)}$/", '$1', $tName);
            } else {
                $this->travellers = [];

                break;
            }
        }
        $this->travellers = array_unique($this->travellers);

        //##################
        //##   FLIGHTS   ###
        //##################

        $xpath = "//text()[{$this->eq($this->t("Itinerary Details"))}]/ancestor::tr[1]/following-sibling::tr[{$xpathNoEmpty}]";
        $nodes = $this->http->XPath->query($xpath);
        $codes = [];
        $cabin = [];

        //Vendor    Itinerary Details
        //CA936     Frankfurt (FRA)
        //          Shanghai (PVG)
        foreach ($nodes as $root) {
            $vendorText = $this->htmlToText($this->http->FindHTMLByXpath('*[2]', null, $root));

            if (preg_match('/^[ ]*(?<name>[A-Z][A-Z\d]|[A-Z\d][A-Z])[ ]*(?<number>\d+)[* ]*$/m', $vendorText, $m)) {
                // DY3088    |    EW4600*
                if (($dep = $this->http->FindSingleNode("./td[3]/descendant::text()[normalize-space(.)][1]", $root,
                        true, "#\(([A-Z]{3})\)#"))
                    && ($arr = $this->http->FindSingleNode("./td[3]/descendant::text()[normalize-space(.)][2]", $root,
                        true, "#\(([A-Z]{3})\)#"))) {
                    $codes[$m['number']] = [$dep, $arr];
                }

                if (($n = count($this->http->FindNodes("(./preceding-sibling::tr/td[" . $this->contains($this->t("Class")) . "])[1]/preceding-sibling::td",
                        $root))) > 0) {
                    $n++;
                    $cabin[$m['number']] = $this->http->FindSingleNode("./td[{$n}]", $root);
                }
            }
        }

        if (0 === count($codes)) {
            $nodes = $this->http->FindNodes("//td[contains(., '(') and contains(., ')') and not(.//td)]", null,
                '/(\([A-Z]{3}\)\s*.+\s*\([A-Z]{3}\))/');
            $nodes = array_values(array_unique(array_filter($nodes)));
            $n = $this->http->FindNodes("//td[contains(., '(') and contains(., ')') and not(.//td)]/preceding::td[normalize-space(.)]",
                null, '/^(?:[A-Z]\d{1,3}|[A-Z]{2})\s*(\d+)$/');
            $n = array_values(array_unique(array_filter($n)));
            $c = $this->http->FindNodes("//td[contains(., '(') and contains(., ')') and not(.//td)]/following::td[normalize-space(.)]",
                null, '/^((?:[Ee]conomy|[Bb]usiness|[Ff]irst [Cc]lass)\s*[A-Z])$/');
            $c = array_values(array_filter($c));

            foreach ($nodes as $i => $node) {
                if (isset($n[$i]) && preg_match('/\(([A-Z]{3})\)\s*.+\s*\(([A-Z]{3})\)/', $node, $m)) {
                    $codes[$n[$i]] = [$m[1], $m[2]];
                }
            }

            foreach ($c as $i => $cab) {
                if (isset($n[$i])) {
                    $cabin[$n[$i]] = $cab;
                }
            }
        }

        $xpath = "//text()[" . $this->starts($this->t("Air -")) . "]/ancestor::tr[./following-sibling::tr][1]/ancestor::table[" . $this->contains($this->t("Depart:")) . "][1]";
        $nodes = $this->http->XPath->query($xpath);
        $airs = [];

        foreach ($nodes as $root) {
            if ($rl = $this->http->FindSingleNode(".//text()[" . $this->contains($this->t("Reference:")) . "]/ancestor::tr[1]",
                $root, true, "#" . $this->opt($this->t("Reference:")) . "\s*([A-Z\d]+)#")) {
                $airs[$rl][] = $root;
            } elseif ($rl = $this->http->FindSingleNode("//text()[" . $this->contains("Buchungscode") . "]", null, true,
                "#Buchungscode ([A-Z\d]+)#")) {
                $airs[$rl][] = $root;
            } elseif ($this->http->XPath->query("descendant::tr[normalize-space(.)][not(" . $this->contains($this->t("Air -")) . ") and not(.//tr)][1]/td",
                    $root)->length === 3) {
                $airs[CONFNO_UNKNOWN][] = $root;
            } else {
                $this->logger->debug("RL not matched");

                return null;
            }
        }

        foreach ($airs as $rl => $roots) {
            $f = $email->add()->flight();

            // General
            $f->general()
                ->confirmation($rl)
                ->travellers($this->travellers)
            ;

            $ticketNumbers = [];
            $accounts = [];

            foreach ($roots as $root) {
                $date = $this->normalizeDate($this->http->FindSingleNode("./tr[normalize-space(.)][1]/td[normalize-space(.)][1]",
                    $root, true, "#" . $this->opt($this->t("Air -")) . "\s*(.+)$#"));

                if (!$date) {
                    $date = $this->normalizeDate($this->http->FindSingleNode("descendant::td[normalize-space(.)][not(.//td)][1]",
                        $root, true, "#" . $this->opt($this->t("Air -")) . "\s*(.+)$#"));
                }

                $s = $f->addSegment();

                // Airline
                $airline = $this->http->FindSingleNode("descendant::tr[normalize-space(.)][not(" . $this->contains($this->t("Air -")) . ") and not(.//tr)][1]/descendant::text()[normalize-space(.)][1]",
                    $root, true, "/^([A-Z][A-Z\d]|[A-Z\d][A-Z])\s*\d+[*\s]*$/");
                $s->airline()
                    ->name($airline);

                if (!empty($airline) && !empty($eTickets[$airline])) {
                    foreach ($eTickets[$airline] as $eT) {
                        $ticketNumbers[] = $eT;
                    }
                }
                $flightNumber = $this->http->FindSingleNode("descendant::tr[normalize-space(.)][not(" . $this->contains($this->t("Air -")) . ") and not(.//tr)][1]/descendant::text()[normalize-space(.)][1]",
                    $root, true, "/^(?:[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(\d+)[*\s]*$/");
                $s->airline()
                    ->number($flightNumber);

                $operator = $this->http->FindSingleNode(".//text()[" . $this->contains($this->t("Operated by:")) . "]/ancestor::td[1]/following-sibling::td[1]",
                    $root);

                if (!empty($operator)) {
                    $s->airline()
                        ->operator($operator);
                }

                // Departure
                $s->departure()
                    ->name($this->http->FindSingleNode(".//text()[" . $this->eq($this->t("Depart:")) . "]/ancestor::td[1]/following-sibling::td[1]",
                        $root, true, "#(.*?)(?:, Terminal|$)#i"))
                    ->date($this->normalizeDate($this->http->FindSingleNode(".//text()[" . $this->eq($this->t("Depart:")) . "]/ancestor::tr[1]/following-sibling::tr[normalize-space(.)][2]/td[2]",
                        $root), $date))
                    ->terminal($this->http->FindSingleNode(".//text()[" . $this->eq($this->t("Depart:")) . "]/ancestor::td[1]/following-sibling::td[1]",
                        $root, true, "#Terminal (.+)#i"), true, true);

                if (isset($codes[$flightNumber])) {
                    $s->departure()
                        ->code($codes[$flightNumber][0]);
                } else {
                    $s->departure()
                        ->noCode();
                }

                // Arrival
                $s->arrival()
                    ->name($this->http->FindSingleNode(".//text()[" . $this->eq($this->t("Arrive:")) . "]/ancestor::td[1]/following-sibling::td[1]",
                        $root, true, "#(.*?)(?:, Terminal|$)#i"))
                    ->terminal($this->http->FindSingleNode(".//text()[" . $this->eq($this->t("Arrive:")) . "]/ancestor::td[1]/following-sibling::td[1]",
                        $root, true, "#Terminal (.+)#i"), true, true);

                if (isset($codes[$flightNumber])) {
                    $s->arrival()
                        ->code($codes[$flightNumber][1]);
                } else {
                    $s->arrival()
                        ->noCode();
                }

                $dateArr = $this->normalizeDate($this->http->FindSingleNode(".//text()[" . $this->eq($this->t("Arrive:")) . "]/ancestor::tr[1]/following-sibling::tr[normalize-space(.)][2]/td[2]",
                    $root), $date);

                if (empty($dateArr)) {
                    $dateArr = $this->normalizeDate($this->http->FindSingleNode("descendant::td[" . $this->eq($this->t("Arrive:")) . " and not(.//td)]/following::td[normalize-space(.)][3]",
                        $root), $date);
                }
                $s->arrival()
                    ->date($dateArr);

                // Extra
                $s->extra()
                    ->aircraft($this->http->FindSingleNode(".//text()[" . $this->eq($this->t("Equipment:")) . "]/ancestor::td[1]/following-sibling::td[1]",
                        $root), true, true)
                    ->miles($this->http->FindSingleNode(".//text()[" . $this->eq($this->t("Distance:")) . "]/ancestor::td[1]/following-sibling::td[1]",
                        $root), true, true)
                    ->duration($this->http->FindSingleNode(".//text()[" . $this->eq($this->t("Duration:")) . "]/ancestor::td[1]/following-sibling::td[1]",
                        $root, true, "#(.*?)(?: " . $this->opt($this->t("Non-stop")) . "|$)#i"))
                ;

                if (isset($cabin[$flightNumber])) {
                    $s->extra()
                        ->cabin($this->re("#(.*?)\s*[A-Z]$#", $cabin[$flightNumber]))
                        ->bookingCode($this->re("#\s*([A-Z])$#", $cabin[$flightNumber]))
                    ;
                }
                // Seats
                if (preg_match_all("#\b(\d{1,2}[A-Z])\b#",
                    $this->http->FindSingleNode(".//text()[" . $this->eq($this->t("Seat:")) . "]/ancestor::td[1]/following-sibling::td[1]",
                        $root), $m)) {
                    $s->extra()
                        ->seats($m[1]);
                }

                $stopsValue = $this->http->FindSingleNode(".//text()[{$this->eq($this->t("Duration:"))}]/ancestor::td[1]/following-sibling::td[1]", $root);

                if (preg_match("/{$this->opt($this->t("Non-stop"))}/i", $stopsValue)) {
                    $s->extra()
                        ->stops(0);
                }

                $loyaltyNumber = $this->htmlToText($this->http->FindHTMLByXpath("descendant::text()[{$this->eq($this->t("Loyalty Number:"))}]/ancestor::td[1]/following-sibling::td[normalize-space()][1]", null, $root));

                foreach (preg_split('/[ ]*\n+[ ]*/', $loyaltyNumber) as $lNumber) {
                    if (preg_match("/^([-A-Z\d]{5,})\s+-\s+{$this->patterns['travellerName']}$/u", $lNumber, $m)) {
                        // SKEB21XXXX4336 - FRANSSON/SVEN TOMAS MR
                        $accounts[] = $m[1];
                    } elseif (preg_match("/^[-A-Z\d]{5,}$/", $lNumber)) {
                        // SKEB21XXXX4336
                        $accounts[] = $lNumber;
                    }
                }

                $fl = $this->http->FindSingleNode("descendant::tr[normalize-space(.)][not(" . $this->contains($this->t("Air -")) . ") and not(.//tr)][1]/descendant::text()[normalize-space(.)][1]",
                    $root, true, "/^((?:[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*\d+)[*\s]*$/");

                if (!empty($fl)
                    && count($tckts = array_filter($this->http->FindNodes("//td[not(.//tr) and {$this->starts($this->t("Air -"))} and {$this->contains($fl)}][not(preceding-sibling::td)]/following-sibling::td[{$this->contains($this->t("Ticket Number:"))}]", null, "/:\s*({$this->patterns['eTicket']})\s*(\D|$)/")))
                ) {
                    // it-1901175.eml
                    $ticketNumbers = array_merge($ticketNumbers, $tckts);
                }
            }

            $ticketNumbers = array_values(array_unique($ticketNumbers));

            if (count($ticketNumbers) > 0) {
                $f->issued()
                    ->tickets($ticketNumbers, false);
            }

            $accounts = array_unique(array_filter($accounts));

            if (!empty($accounts)) {
                foreach ($accounts as $account) {
                    $f->program()
                        ->account($account, preg_match("/XXX{3,}/", $account) > 0);
                }
            }
        }

        //#################
        //##   HOTELS   ###
        //#################
        $xpath = "//text()[" . $this->starts($this->t("Hotel -")) . "]/ancestor::tr[./following-sibling::tr][1]/..";
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $h = $email->add()->hotel();

            // General
            $h->general()
                ->confirmation($this->http->FindSingleNode(".//text()[" . $this->contains($this->t("Reference:")) . "]/ancestor::tr[1]",
                    $root, true, "#" . $this->opt($this->t("Reference:")) . "\s*([A-Z\d]+)#"))
                ->travellers($this->travellers)
                ->cancellation($this->http->FindSingleNode(".//text()[" . $this->eq($this->t("Cancellation Policy:")) . "]/ancestor::td[1]/following-sibling::td[1]",
                    $root), true, true)
            ;

            // Hotel
            $h->hotel()
                ->name($this->http->FindSingleNode("./tr[normalize-space(.)][2]/td[1]", $root));

            if (($adr = $this->http->XPath->query(".//text()[{$this->eq($this->t("Address:"))}]/ancestor::tr[1]", $root))->length !== 0) {
                $address = $this->http->FindSingleNode("td[2]", $adr->item(0));

                while (($adr = $this->http->XPath->query("following-sibling::tr[normalize-space()][1][normalize-space(td[1])='']", $adr->item(0)))->length !== 0) {
                    $address .= ' ' . $this->http->FindSingleNode("td[2]", $adr->item(0));
                }
                $h->hotel()
                    ->address($address);
            }

            $h->hotel()
                ->phone($this->http->FindSingleNode(".//text()[" . $this->eq($this->t("Tel.:")) . "]/ancestor::td[1]/following-sibling::td[1]",
                    $root))
                ->fax($this->http->FindSingleNode(".//text()[" . $this->eq($this->t("Fax:")) . "]/ancestor::td[1]/following-sibling::td[1]",
                    $root), true, true)
            ;

            // Booked
            $dates = explode(" - ",
                $this->http->FindSingleNode(".//text()[" . $this->eq($this->t("Check In / Check Out:")) . "]/ancestor::td[1]/following-sibling::td[1]",
                    $root));

            if (count($dates) == 2) {
                $h->booked()
                    ->checkIn($this->normalizeDate($dates[0]))
                    ->checkOut($this->normalizeDate($dates[1]))
                ;
            }

            $h->addRoom()
                ->setType($this->http->FindSingleNode("./tr[normalize-space(.)][2]/td[2][not(" . $this->contains($this->t("Reference:")) . ")]", $root))
                ->setRate($this->http->FindSingleNode(".//text()[" . $this->eq($this->t("Rate per night:")) . "]/ancestor::td[1]/following-sibling::td[1]",
                    $root))
                ->setDescription($this->http->FindSingleNode(".//text()[" . $this->eq($this->t("Description:")) . "]/ancestor::td[1]/following-sibling::td[1]",
                    $root), true, true)
            ;

            // Price
            $h->price()
                ->total($this->amount($this->http->FindSingleNode(".//text()[" . $this->eq($this->t("Total:")) . "]/ancestor::td[1]/following-sibling::td[1]",
                    $root)))
                ->currency($this->currency($this->http->FindSingleNode(".//text()[" . $this->eq($this->t("Total:")) . "]/ancestor::td[1]/following-sibling::td[1]",
                    $root)));
        }

        //###############
        //##   CARS   ###
        //###############
        $xpath = "//text()[" . $this->starts($this->t("Car -")) . "]/ancestor::tr[./following-sibling::tr[" . $this->contains($this->t("Pick Up:")) . "]][1]/..";
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $rental = $email->add()->rental();

            // General
            $rental->general()
                ->confirmation($this->http->FindSingleNode(".//text()[" . $this->contains($this->t("Reference:")) . "]/ancestor::tr[1]",
                    $root, true, "#" . $this->opt($this->t("Reference:")) . "\s*([A-Z\d]+)#"))
                ->travellers($this->travellers);

            // Pick Up
            $rental->pickup()
                ->date($this->normalizeDate($this->http->FindSingleNode("(.//text()[" . $this->eq($this->t("Pick Up:")) . "]/ancestor::tr[1]/following-sibling::tr/td[contains(translate(normalize-space(), '0123456789', 'dddddddddd'), 'dddd') and contains(translate(normalize-space(), '0123456789', 'dddddddddd'), 'dd:dd')])[1]",
                    $root)))
                ->location($this->http->FindSingleNode(".//text()[" . $this->eq($this->t("Pick Up:")) . "]/ancestor::td[1]/following-sibling::td[1]",
                    $root))
                ->phone($this->http->FindSingleNode(".//text()[{$this->eq($this->t("Pick Up:"))}]/ancestor::tr[1]/following-sibling::tr[1]/td[2]",
                    $root, true, "/{$this->opt($this->t("Tel.:"))}\s*({$this->patterns['phone']})[;,\s]*(?:{$this->opt($this->t("Fax:"))}|$)/i"), true, true)
                ->fax($this->http->FindSingleNode(".//text()[{$this->eq($this->t("Pick Up:"))}]/ancestor::tr[1]/following-sibling::tr[1]/td[2]",
                    $root, true, "/{$this->opt($this->t("Fax:"))}\s*({$this->patterns['phone']})[;,\s]*$/i"), true, true)
            ;

            // Drop Off
            $rental->dropoff()
                ->date($this->normalizeDate($this->http->FindSingleNode("(.//text()[" . $this->eq($this->t("Drop Off:")) . "]/ancestor::tr[1]/following-sibling::tr/td[contains(translate(normalize-space(), '0123456789', 'dddddddddd'), 'dddd') and contains(translate(normalize-space(), '0123456789', 'dddddddddd'), 'dd:dd')])[1]",
                    $root)))
                ->location($this->http->FindSingleNode(".//text()[" . $this->eq($this->t("Drop Off:")) . "]/ancestor::td[1]/following-sibling::td[1]",
                    $root))
                ->phone($this->http->FindSingleNode(".//text()[{$this->eq($this->t("Drop Off:"))}]/ancestor::tr[1]/following-sibling::tr[1]/td[2]",
                    $root, true, "/{$this->opt($this->t("Tel.:"))}\s*({$this->patterns['phone']})[;,\s]*(?:{$this->opt($this->t("Fax:"))}|$)/i"), true, true)
                ->fax($this->http->FindSingleNode(".//text()[{$this->eq($this->t("Drop Off:"))}]/ancestor::tr[1]/following-sibling::tr[1]/td[2]",
                    $root, true, "/{$this->opt($this->t("Fax:"))}\s*({$this->patterns['phone']})[;,\s]*$/i"), true, true)
            ;

            // RentalCompany
            $rental->extra()
                ->company($this->http->FindSingleNode("./tr[normalize-space(.)][2]/td[1]", $root));

            // CarType
            $car = $this->http->FindSingleNode("./tr[normalize-space(.)][2]/td[2]", $root);

            if (preg_match("#(.+)\(\s*[\w\. ]{1,5}:\s*(.+)\s*\)\s*$#", $car, $m)) {
                $rental->car()
                    ->type(trim($m[1]))
                    ->model($m[2]);
            } else {
                $rental->car()
                    ->type($car);
            }

            // Price
            $rental->price()
                ->total($this->amount($this->http->FindSingleNode(".//text()[" . $this->eq($this->t("Total:")) . "]/ancestor::td[1]/following-sibling::td[1]",
                    $root)))
                ->currency($this->currency($this->http->FindSingleNode(".//text()[" . $this->eq($this->t("Total:")) . "]/ancestor::td[1]/following-sibling::td[1]",
                    $root)))
            ;
        }

        //##################
        //##     BUS     ###
        //##################
        $xpath = "//text()[" . $this->starts($this->t("Bus -")) . "]/ancestor::tr[./following-sibling::tr][1]/ancestor::table[" . $this->contains($this->t("Depart:")) . "][1]";
//        $this->logger->debug("XPATH: {$xpath}");
        $nodes = $this->http->XPath->query($xpath);
        $buses = [];

        foreach ($nodes as $root) {
            if ($rl = $this->http->FindSingleNode(".//text()[" . $this->contains($this->t("Reference:")) . "]/ancestor::tr[1]",
                $root, true, "#" . $this->opt($this->t("Reference:")) . "\s*([A-Z\d]+)#")) {
                $buses[$rl][] = $root;
            } elseif ($rl = $this->http->FindSingleNode("//text()[" . $this->contains("Buchungscode") . "]", null, true,
                "#Buchungscode ([A-Z\d]+)#")) {
                $buses[$rl][] = $root;
            } else {
                $this->logger->debug("RL not matched");

                return null;
            }
        }

        foreach ($buses as $rl => $roots) {
            $bus = $email->add()->bus();

            // General
            $bus->general()
                ->confirmation($rl)
                ->travellers($this->travellers);

            foreach ($roots as $root) {
                $date = $this->normalizeDate($this->http->FindSingleNode("./tr[normalize-space(.)][1]/td[normalize-space(.)][1]",
                    $root, true, "#" . $this->opt($this->t("Bus -")) . "\s*(.+)$#"));

                if (!$date) {
                    $date = $this->normalizeDate($this->http->FindSingleNode("descendant::td[normalize-space(.)][not(.//td)][1]",
                        $root, true, "#" . $this->opt($this->t("Bus -")) . "\s*(.+)$#"));
                }

                $s = $bus->addSegment();

                // Departure
                $s->departure()
                    ->name($this->http->FindSingleNode(".//text()[" . $this->eq($this->t("Depart:")) . "]/ancestor::td[1]/following-sibling::td[1]",
                        $root, true, "#(.*?)(?:, Terminal|$)#i"))
                    ->date($this->normalizeDate($this->http->FindSingleNode(".//text()[" . $this->eq($this->t("Depart:")) . "]/ancestor::tr[1]/following-sibling::tr[normalize-space(.)][1]/td[2]",
                        $root), $date))
                ;

                // Arrival
                $s->arrival()
                    ->name($this->http->FindSingleNode(".//text()[" . $this->eq($this->t("Arrive:")) . "]/ancestor::td[1]/following-sibling::td[1]",
                        $root, true, "#(.*?)(?:, Terminal|$)#i"));

                $arrDate = $this->http->FindSingleNode(".//text()[" . $this->eq($this->t("Arrive:")) . "]/ancestor::tr[1]/following-sibling::tr[normalize-space(.)][1]/td[2]",
                    $root);

                if (empty($arrDate)) {
                    $arrDate = $this->http->FindSingleNode("descendant::td[" . $this->eq($this->t("Arrive:")) . " and not(.//td)]/following::td[normalize-space(.)][2]",
                        $root);
                }

                if (!empty($arrDate)) {
                    $s->arrival()
                        ->date($this->normalizeDate($arrDate, $date));
                }

                // Extra
                $s->extra()
                    ->number($this->http->FindSingleNode("descendant::tr[normalize-space(.)][not(" . $this->contains($this->t("Bus -")) . ") and not(.//tr)][1]/descendant::text()[normalize-space(.)][1]",
                        $root, true, "/^(?:[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(\d+)[*\s]*$/"))
                    ->type($this->http->FindSingleNode("descendant::tr[normalize-space(.)][not(" . $this->contains($this->t("Bus -")) . ") and not(.//tr)][1]/descendant::text()[normalize-space(.)][1]",
                        $root, true, "/^([A-Z][A-Z\d]|[A-Z\d][A-Z])\s*\d+[*\s]*$/"));
            }
        }

        //##################
        //##    TRAIN    ###
        //##################
        $ruleTimeNotOpen = ".//text()[" . $this->eq($this->t("Depart:")) . "]/ancestor::tr[1]/following-sibling::tr[normalize-space(.)][1]/td[2][not({$this->eq('open')})]";
        $xpath = "//text()[" . $this->starts($this->t("Rail -")) . "]/ancestor::tr[./following-sibling::tr][1]/ancestor::table[" . $this->contains($this->t("Depart:")) . "][1][{$ruleTimeNotOpen}]";
        $this->logger->debug("XPATH: {$xpath}");
        $nodes = $this->http->XPath->query($xpath);
        $rail = [];

        foreach ($nodes as $root) {
            if ($rl = $this->http->FindSingleNode(".//text()[" . $this->contains($this->t("Reference:")) . "]/ancestor::tr[1]",
                $root, true, "#" . $this->opt($this->t("Reference:")) . "\s*([A-Z\d]+)#")) {
                $rail[$rl][] = $root;
            } elseif ($rl = $this->http->FindSingleNode("//text()[" . $this->contains("Buchungscode") . "]", null, true,
                "#Buchungscode ([A-Z\d]+)#")) {
                $rail[$rl][] = $root;
            } else {
                $this->logger->debug("RL not matched");

                return null;
            }
        }

        foreach ($rail as $rl => $roots) {
            $train = $email->add()->train();

            // General
            $train->general()
                ->confirmation($rl)
                ->travellers($this->travellers)
            ;

            $ticketNumbers = [];

            foreach ($roots as $root) {
                $date = $this->normalizeDate($this->http->FindSingleNode("./tr[normalize-space(.)][1]/td[normalize-space(.)][1]",
                    $root, true, "#" . $this->opt($this->t("Rail -")) . "\s*(.+)$#"));

                if (!$date) {
                    $date = $this->normalizeDate($this->http->FindSingleNode("descendant::td[normalize-space(.)][not(.//td)][1]",
                        $root, true, "#" . $this->opt($this->t("Rail -")) . "\s*(.+)$#"));
                }

                $s = $train->addSegment();

                // Departure
                $s->departure()
                    ->name($this->http->FindSingleNode(".//text()[" . $this->eq($this->t("Depart:")) . "]/ancestor::td[1]/following-sibling::td[1]",
                        $root, true, "#(.*?)(?:, Terminal|$)#i"))
                    ->date($this->normalizeDate($this->http->FindSingleNode(".//text()[" . $this->eq($this->t("Depart:")) . "]/ancestor::tr[1]/following-sibling::tr[normalize-space(.)][1]/td[2]",
                        $root), $date))
                ;

                // Arrival
                $nameArr = $this->http->FindSingleNode(".//text()[{$this->eq($this->t("Arrive:"))}]/ancestor::td[1]/following-sibling::td[normalize-space()][1]", $root, true, "/(.*?)(?:, Terminal|$)/i");

                if (preg_match("/{$this->opt($this->t("Pick up/Drop off Location"))}/i", $nameArr)) {
                    $train->removeSegment($s);

                    continue;
                } else {
                    $s->arrival()
                        ->name($nameArr);
                }
                // ArrDate
                $dateArr = $this->http->FindSingleNode(".//text()[" . $this->eq($this->t("Arrive:")) . "]/ancestor::tr[1]/following-sibling::tr[normalize-space(.)][1]/td[2]", $root);

                if (empty($dateArr)) {
                    $dateArr = $this->http->FindSingleNode("descendant::td[" . $this->eq($this->t("Arrive:")) . " and not(.//td)]/following::td[normalize-space(.)][2]", $root);
                }

                if (!empty($dateArr)) {
                    $s->arrival()
                        ->date($this->normalizeDate($dateArr, $date));
                }

                // Extra
                $number = $this->http->FindSingleNode("descendant::tr[normalize-space(.)][not(" . $this->contains($this->t("Rail -")) . ") and not(.//tr)][1]/descendant::text()[normalize-space(.)][2][{$this->starts($this->t('Train Number'))}]",
                        $root, true, "/:\s*(\d+)(?:-|[*]*$)/");

                if (empty($number) && !empty($s->getDepName()) && !empty($s->getArrName())) {
                    $number = $this->http->FindSingleNode("//text()[{$this->eq($s->getDepName())}]/ancestor::td[1][./descendant::text()[normalize-space()!=''][last()][{$this->eq($s->getArrName())}]]/preceding-sibling::td[1]",
                        null, false, "/^\d+/");
                }
                $s->extra()
                    ->number($number);

                $s->extra()
                    ->service($this->http->FindSingleNode("descendant::tr[normalize-space(.)][not(" . $this->contains($this->t("Rail -")) . ") and not(.//tr)][1]/descendant::text()[normalize-space(.)][1][./following::text()[{$this->starts($this->t('Train Number'))}]]",
                        $root))
                    ->cabin($this->http->FindSingleNode(".//text()[" . $this->starts($this->t("Class:")) . "]/ancestor::td[1]/following-sibling::td[1]",
                        $root), true, true)
                ;

                switch ($s->getServiceName()) {
                    case 'SNCF':
                        $s->departure()->geoTip('europe');
                        $s->arrival()->geoTip('europe');

                        if ($s->getDepName() === 'PAU') {
                            $s->departure()
                                ->name('PAU, FRANCE')
                                ->geoTip('fr');
                        }

                        if ($s->getArrName() === 'PAU') {
                            $s->arrival()
                                ->name('PAU, FRANCE')
                                ->geoTip('fr');
                        }

                        break;
                }

                $seat = $this->http->FindSingleNode(".//text()[{$this->starts($this->t("Seat:"))}]/ancestor::td[1]/following-sibling::td[normalize-space()][1]", $root);

                if (preg_match("/^\s*{$this->opt($this->t("Coach:"))}\s*(?<car>[A-z\d]+)[,\s]+{$this->opt($this->t("Seat:"))}\s*(?<seat>[A-z\d]+)(?:\s+-\s+{$this->patterns['travellerName']})?$/u", $seat, $m)) {
                    // Coach: 2 Seat: 18 - DANIELSSON/CHARLOTTA MRS
                    $s->extra()
                        ->car($m['car'])
                        ->seat($m['seat'])
                    ;
                }

                if (!empty($s->getNumber())
                    && ($tckts = array_filter($this->http->FindNodes("//td[not(.//tr) and {$this->starts($this->t("Rail -"))} and {$this->contains($s->getNumber())}][not(preceding-sibling::td)]/following-sibling::td[{$this->contains($this->t("Ticket Number:"))}]", null, "/:\s*({$this->patterns['eTicket']}|[A-Z\d]{5,}\d)\s*(\D|$)/")))
                ) {
                    // ZAJ6174O0001
                    $ticketNumbers = array_merge($ticketNumbers, $tckts);
                }
            }

            $ticketNumbers = array_values(array_unique($ticketNumbers));

            if (count($ticketNumbers) > 0) {
                foreach ($ticketNumbers as $ticket) {
                    $train->addTicketNumber($ticket, false);
                }
            }
        }

        //////////////////////
        ///    TRANSFER    ///    example: it-119924517.eml
        //////////////////////
        $xpath = "//text()[{$this->starts($this->t("Taxi -"))}]/ancestor::tr[following-sibling::tr][1]/ancestor::table[{$this->contains($this->t("Pick Up:"))}][1]";
        $this->logger->debug("XPATH: {$xpath}");
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $transfer = $email->add()->transfer();

            // General
            $transfer->general()
                ->confirmation($this->http->FindSingleNode(".//text()[{$this->contains($this->t("Reference:"))}]/ancestor::tr[1]", $root, true, "/{$this->opt($this->t("Reference:"))}\s*([-A-Z\d]{5,})$/"))
                ->travellers($this->travellers, true);

            $date = $this->normalizeDate($this->http->FindSingleNode("./tr[normalize-space()][1]/td[normalize-space()][1]", $root, true, "/{$this->opt($this->t("Taxi -"))}\s*(.+)$/"));

            if (!$date) {
                $date = $this->normalizeDate($this->http->FindSingleNode("descendant::td[normalize-space()][not(.//td)][1]", $root, true, "/{$this->opt($this->t("Taxi -"))}\s*(.+)$/"));
            }

            $s = $transfer->addSegment();

            $s->departure()
                ->name($this->http->FindSingleNode(".//text()[{$this->eq($this->t("Pick Up:"))}]/ancestor::td[1]/following-sibling::td[1]", $root))
                ->date($this->normalizeDate($this->http->FindSingleNode(".//text()[{$this->eq($this->t("Pick Up:"))}]/ancestor::tr[1]/following-sibling::tr[normalize-space()][1][normalize-space(td[1])='']/td[2]", $root), $date))
            ;

            $s->arrival()
                ->name($this->http->FindSingleNode(".//text()[{$this->eq($this->t("Drop Off:"))}]/ancestor::td[1]/following-sibling::td[1]", $root));

            $xpathTaxiTimeArr = ".//text()[{$this->eq($this->t("Drop Off:"))}]/ancestor::tr[1]/following-sibling::tr[normalize-space()][1][normalize-space(td[1])='']/td[2]";

            if ($this->http->XPath->query($xpathTaxiTimeArr, $root)->length > 0) {
                $s->arrival()
                    ->date($this->normalizeDate($this->http->FindSingleNode($xpathTaxiTimeArr, $root), $date));
            } else {
                $s->arrival()
                    ->noDate();
            }

            $additionalInformation = $this->htmlToText($this->http->FindHTMLByXpath(".//text()[{$this->eq($this->t("Additional Information:"))}]/ancestor::td[1]/following-sibling::td[1]", null, $root));

            if (preg_match("/^[ ]*{$this->opt($this->t("Taxi Type:"))}[ ]*(.{2,}?)[ ]*$/m", $additionalInformation, $m)) {
                $s->extra()
                    ->type($m[1]);
            }

            $totalPrice = $this->http->FindSingleNode(".//text()[{$this->eq($this->t("Total:"))}]/ancestor::td[1]/following-sibling::td[1]", $root);

            if (preg_match('/^(?<currency>[^\d)(]+?)[ ]*(?<amount>\d[,.\'\d ]*)$/', $totalPrice, $matches)) {
                $currencyCode = preg_match('/^[A-Z]{3}$/', $matches['currency']) ? $matches['currency'] : null;
                $transfer->price()
                    ->total(PriceHelper::parse($matches['amount'], $currencyCode))
                    ->currency($matches['currency']);
            }
        }

        return true;
    }

    private function getProvider($body)
    {
        foreach ($this->reBody as $prov => $reBody) {
            foreach ($reBody as $re) {
                if (strpos($body, $re) !== false) {
                    return $prov;
                }
            }
        }

        return false;
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
        if ($relDate === false) {
            $relDate = $this->date;
        }
        //		$this->logger->debug($instr);
        $in = [
            "#^[^\s\d]+, (\d+ [^\s\d]+ \d{4})$#", //Thursday, 31 July 2014
            "#^(\d+:\d+), (\d+) ([^\s\d]+)$#", //12:55, 20 July
            "#^(\d+:\d+) [^\s\d]+, (\d+ [^\s\d]+ \d{4})$#", //07:30 Tuesday, 01 December 2015
            "#^(\d+:\d+), [^\s\d]+ (\d+ [^\s\d]+ \d{4})$#", //07:30 Tuesday, 01 December 2015
        ];
        $out = [
            "$1",
            "$2 $3 %Y%, $1",
            "$2 $1",
            "$2 $1",
        ];

        $str = preg_replace($in, $out, $instr);

        if (preg_match("#\d+\s+([^\d\s]+)\s+(?:\d{4}|%Y%)#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }
        // fix for short febrary
        if (strpos($str, "29 February") !== false && date('m/d',
                strtotime(str_replace("%Y%", date('Y', $relDate), $str))) == '03/01') {
            $str = str_replace("%Y%", date('Y', $relDate) + 1, $str);
        }

        foreach ($in as $re) {
            if (preg_match($re, $instr, $m) && isset($m['week'])) {
                $str = str_replace("%Y%", date('Y', $relDate), $str);
                $dayOfWeekInt = WeekTranslate::number1($m['week'], $this->lang);

                return EmailDateHelper::parseDateUsingWeekDay($str, $dayOfWeekInt);
            }
        }

        if (strpos($str, "%Y%") !== false) {
            return EmailDateHelper::parseDateRelative($str, $relDate, true, $str);
        }

        return strtotime($str, $relDate);
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function amount($s)
    {
        if (($s = $this->re("#([\d\,\.]+)#", $s)) === null) {
            return null;
        }

        return (float) str_replace(",", ".", preg_replace("#[.,](\d{3})#", "$1", $s));
    }

    private function currency($s)
    {
        $sym = [
            '€' => 'EUR',
            '$' => 'USD',
            '£' => 'GBP',
            '₹' => 'INR',
        ];

        if ($code = $this->re("#(?:^|\s)([A-Z]{3})(?:$|\s)#", $s)) {
            return $code;
        }
        $s = $this->re("#([^\d\,\.]+)#", $s);

        foreach ($sym as $f => $r) {
            if (strpos($s, $f) !== false) {
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

    private function starts($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';

            return 'starts-with(normalize-space(' . $node . '),' . $s . ')';
        }, $field)) . ')';
    }

    private function contains($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';

            return 'contains(normalize-space(' . $node . '),' . $s . ')';
        }, $field)) . ')';
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
