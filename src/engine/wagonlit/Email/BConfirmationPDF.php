<?php

namespace AwardWallet\Engine\wagonlit\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

// html parser: cytric/BookingConfirmation
class BConfirmationPDF extends \TAccountChecker
{
    public $mailFiles = "wagonlit/it-5702657.eml, wagonlit/it-5702694.eml";

    public $detectBody = [
        'de'  => ['Referenz der Fluggesellschaft', 'Bestätigung für'],
        'de2' => ['Hotel Referenz:', 'Bestätigung für'],
        'de3' => ['Wir danken Ihnen für diese Buchung!', 'Bestätigung für'],
        'de4' => ['Bestätigungsnummer Mietwagenanbiet', 'Bestätigung für'],
        'de5' => ['Reiseplan', 'Bestätigung für'],
        'en'  => ['Airline Reference', 'Confirmation for'],
        'en2' => ['Confirmation Number of the Car Vendor', 'Confirmation for'],
        'da'  => ['Rejseplan', 'Reservationsnr'],
    ];
    public static $detectHeaders = [
        'cytric' => [
            'from' => ["cytric.net"],
            'subj' => [
                'Booking Confirmation', // en
                'Buchungs-Bestätigung', // de
                'Buchungs-Änderung', // de
            ],
        ],
        'wagonlit' => [
            'from' => ["contactcwt.com", "cwtsatotravel.com"],
            'subj' => [
                'Booking Confirmation', // en
                'Buchungs-Bestätigung', // de
                'Buchungs-Änderung', // de
            ],
        ],
    ];

    public static $dict = [
        'en' => [
            'Telephone' => ['Tel', 'Telephone'],
            'Telefax'   => ['Fax', 'Telefax'],
            //Car
            'Confirmation Number of the Car Vendor:' => 'Confirmation Number of the Car Vendor:',
            'Type of Car:'                           => 'Type of Car:',
        ],
        'de' => [
            'Total Cost of the complete Trip in'                    => 'Gesamtbetrag der gesamten Reise in',
            'Confirmation for'                                      => 'Bestätigung für',
            'Airline Reference'                                     => ['Referenz der Fluggesellschaft', 'Bestätigungsnummer bei'],
            'Booking Date'                                          => 'Buchungsdatum',
            'Booking Code'                                          => 'Buchungscode',
            'Status'                                                => 'Status',
            'to'                                                    => 'nach',
            'operated by'                                           => 'durchgeführt von',
            'Flight Duration'                                       => 'Flugdauer',
            'Miles:'                                                => 'Meilen:',
            "The Ticket Number is:"                                 => "Die Ticketnummer ist:",
            'Total fare for all travellers for all Air segments in' => "Gesamtpreis für alle Reisenden für alle Flugsegmente in",

            //Hotel
            'Hotel Reference'             => ['Hotel Reference', 'Hotel Referenz', 'Hotelreferenz'],
            'Telephone'                   => 'Telefon',
            'Telefax'                     => ['Fax', 'Telefax'],
            'Nights'                      => ['Nächte', 'Nächt', 'Nacht'],
            'Room Description'            => ['Room Description', 'Room description'],
            'Total rate amount in'        => 'Gesamtbetrag in',
            'The average rate per day in' => 'Der durchschnittliche Preis pro Tag in',
            'Cost Free Cancellation'      => 'Kostenfreie Stornierung',
            //Car
            'Confirmation Number of the Car Vendor:' => 'Bestätigungsnummer Mietwagenanbieter',
            'Type of Car:'                           => 'Mietwagen-Typ:',
            'Flight Number'                          => 'Flugnummer',
        ],
        'da' => [
            'Traveller'                         => 'Rejsende',
            'Total Cost of the complete Trip in'=> 'Totalpris for hele rejsen i',
            'Confirmation for'                  => 'Bekræftelse for',
            'Airline Reference'                 => ['Fly reference'],
            'Booking Date'                      => 'Reservationsdato',
            'Booking Code'                      => 'Reservationsnr',
            'Status'                            => 'Status',
            'to'                                => 'til',
            //			'operated by'=>'',
            'Flight Duration'                                       => 'Flyvetid',
            'Miles:'                                                => 'Mil:',
            "The Ticket Number is:"                                 => " Billetnummer er::",
            'Total fare for all travellers for all Air segments in' => 'Totalpris for alle flysegmenter for alle rejsende i',

            //Hotel
            'Hotel Reference'       => ['Hotel Reference'],
            'Telephone'             => 'Tlf.',
            'Telefax'               => 'Fax',
            'Nights'                => ['Nat'],
            'Room Description'      => 'Room facilities',
            'Total rate amount in'  => 'Totalrate inkl. skatter og gebyrer (22.4%) i',
            // 'The average rate per day in' => '',
            'Cost Free Cancellation'=> 'Rate Description',
            //Car
            //			'Confirmation Number of the Car Vendor:' => '',
            //			'Type of Car:' => '',
            //            'Flight Number' => '',
        ],
    ];

    private $providerCode;
    private $detectCompany = [//only text, seek in both html & pdf-attach
        'cytric' => [
            'noreply@cytric.net',
            '//mega.cytric.net',
            'Sales Channel: cytric Buchung',
        ],
        'wagonlit' => [
            'Carlson Wagonlit',
        ],
    ];

    private $lang = '';

    /** @var \HttpBrowser */
    private $pdf;
    private $pdfNamePattern = ".*pdf";

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);
        $cur = [];
        $sum = [];

        if (isset($pdfs) && count($pdfs) > 0) {
            $this->pdf = clone $this->http;

            foreach ($pdfs as $pdf) {
                if (($html = \PDF::convertToHtml($parser->getAttachmentBody($pdf), \PDF::MODE_COMPLEX)) !== null) {
                    $NBSP = chr(194) . chr(160);
                    $html = str_replace($NBSP, ' ', html_entity_decode($html));

                    if (!$this->assignLang($html)) {
                        $this->logger->notice("Can't determine a language!");

                        continue;
                    }
                    $this->pdf->SetEmailBody($html);
                    $this->parseEmail($email);

                    $sum[] = $this->normalizeAmount($this->pdf->FindSingleNode("(//text()[{$this->contains($this->t('Total Cost of the complete Trip in'))}]/following::text()[normalize-space(.)!=''][1])[last()]"));
                    $cur[] = $this->pdf->FindSingleNode("(//text()[{$this->contains($this->t('Total Cost of the complete Trip in'))}])[last()]", null, true, "#{$this->opt($this->t('Total Cost of the complete Trip in'))}\s+([A-Z]{3})#");
                } else {
                    continue;
                }
            }
        } else {
            return $email;
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        if ($code = $this->getProvider($parser)) {
            $email->setProviderCode($code);
        }

        $sum = array_filter($sum);

        if (!empty($sum) && (count($sum) == count($pdfs)) && (count(array_unique($cur)) == 1)) {
            $email->price()
                ->total(array_sum($sum))
                ->currency($cur[0])
            ;
        }

        return $email;
    }

    public function detectEmailFromProvider($from)
    {
        foreach (self::$detectHeaders as $code => $arr) {
            foreach ($arr['from'] as $f) {
                if (stripos($from, $f) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (empty($headers['from']) || empty($headers['subject'])) {
            return false;
        }

        foreach (self::$detectHeaders as $code => $arr) {
            $byFrom = $bySubj = false;

            foreach ($arr['from'] as $f) {
                if (stripos($headers['from'], $f) !== false) {
                    $byFrom = true;
                }
            }

            foreach ($arr['subj'] as $subj) {
                if (stripos($headers['subject'], $subj) !== false) {
                    $bySubj = true;
                }
            }

            if ($byFrom && $bySubj) {
                $this->providerCode = $code;

                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if ($this->assignLang($text)) {
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
        $types = 2; //flight | hotel
        $cnt = $types * count(self::$dict);

        return $cnt;
    }

    public static function getEmailProviders()
    {
        return array_keys(self::$detectHeaders);
    }

    private function parseEmail(Email $email): Email
    {
        $pax = array_filter(array_unique($this->pdf->FindNodes("//text()[contains(translate(normalize-space(),'0123456789','dddddddddd'),'d. {$this->t('Traveller')}:') or contains(normalize-space(),'{$this->t('Traveller')}:')]",
            null, "#{$this->t('Traveller')}:\s+(.+)#")));

        if (empty($pax)) {
            $pax = [];
            $texts = $this->pdf->FindNodes("//text()[{$this->contains($this->t('Confirmation for'))}]", null,
                "#{$this->opt($this->t('Confirmation for'))}\s+(.+)#");

            foreach ($texts as $text) {
                $pax = array_merge($pax, array_filter(array_map("trim", explode(",", $text))));
            }
            $pax = array_unique($pax);
        }

        //############
        //# FLIGHTS ##
        //############

        $xpath = "//text()[{$this->contains($this->t('Airline Reference'))}]";
        $nodes = $this->pdf->XPath->query($xpath);
        $airs = [];

        foreach ($nodes as $root) {
            $rl = $this->pdf->FindSingleNode(".", $root, true, "#" . $this->opt($this->t("Airline Reference")) . "\s*:\s*([A-Z\d]{5,})#");

            if (empty($rl)) {
                $rl = $this->pdf->FindSingleNode(".", $root, true, "#" . $this->opt($this->t("Airline Reference")) . "[^:]{0,30}\s*:\s*([A-Z\d]{5,})#");
            }
            $airs[$rl][] = $root;
        }

        foreach ($airs as $rl => $roots) {
            $f = $email->add()->flight();

            $xpathFragmentStatus = "./ancestor::p[1]/following::p[position()<12][{$this->contains($this->t('Status:'))}]";

            $f->general()
                ->confirmation($rl)
                ->travellers($pax)
                ->date($this->normalizeDate($this->pdf->FindSingleNode("(./following::text()[{$this->contains($this->t('Booking Date'))}])[1]", $roots[0], true, "#:\s*(.+)#")))
                ->status($this->pdf->FindSingleNode($xpathFragmentStatus, $roots[0], true, "#{$this->opt($this->t('Status'))}:\s*([^,]+?)\s*(?:,|$)#"));

            $date = null;
            $ticketsAll = [];

            foreach ($roots as $root) {
                $s = $f->addSegment();

                $dateFlight = $this->normalizeDate($this->pdf->FindSingleNode("./ancestor::p[1]/preceding::p[2]", $root,
                    null, "#(.+?)\s+{$this->opt($this->t('to'))}#"));

                if (!empty($dateFlight)) {
                    $date = $dateFlight;
                }
                $node = $this->pdf->FindSingleNode("./ancestor::p[1]", $root);

                if (preg_match("#^\s*([A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(\d+)\s+(.*?),?\s*{$this->opt($this->t('Airline Reference'))}#i", $node, $m)) {
                    $s->airline()
                        ->name($m[1])
                        ->number($m[2])
                    ;
                    $s->extra()
                        ->cabin($m[3]);

                    if (!empty($this->pdf->FindSingleNode("(//text()[" . $this->contains($this->t("The Ticket Number is:")) . "])[1]"))) {
                        $tickets = array_filter($this->pdf->FindNodes("//text()[" . $this->contains($s->getAirlineName() . ' ' . $s->getFlightNumber()) . " and " . $this->contains($this->t("The Ticket Number is:")) . "]", null, "#" . $s->getAirlineName() . ' ' . $s->getFlightNumber() . ".*?" . $this->opt($this->t("The Ticket Number is:")) . "\s*([\d\-]{10,})#"));
                        $ticketsAll = array_merge($ticketsAll, $tickets);
                        $tickets = array_filter($this->pdf->FindNodes("//text()[" . $this->contains($s->getAirlineName() . ' ' . $s->getFlightNumber()) . " and not(" . $this->contains($this->t("The Ticket Number is:")) . ")]/following::text()[normalize-space()][1][" . $this->contains($this->t("The Ticket Number is:")) . "]", null, "#" . $this->opt($this->t("The Ticket Number is:")) . "\s*([\d\-]{10,})#"));
                        $ticketsAll = array_merge($ticketsAll, $tickets);
                        $tickets = array_filter($this->pdf->FindNodes("//text()[" . $this->contains($s->getAirlineName() . ' ' . $s->getFlightNumber()) . "]/following::text()[normalize-space()][1][" . $this->contains($this->t("The Ticket Number is:")) . "]/following::text()[normalize-space()][1]", null, "#^\s*(\d{3}-[\d\-]{10,})#"));
                        $ticketsAll = array_merge($ticketsAll, $tickets);
                    }
                }

                $s->airline()
                    ->operator($this->pdf->FindSingleNode("./following::text()[normalize-space(.)][1]", $root, null, "#{$this->opt($this->t('operated by'))}\s+(.+)#i"), true, true);

                $xpathFragmentNotOperator = "not({$this->contains($this->t('operated by'))})";

                if (!empty($date)) {
                    $s->departure()
                        ->date(strtotime($this->pdf->FindSingleNode("./ancestor::p[1]/following::p[$xpathFragmentNotOperator][1]", $root, null, "#\d+:\d+#"), $date));
                    $s->arrival()
                        ->date(strtotime($this->pdf->FindSingleNode("./ancestor::p[1]/following::p[$xpathFragmentNotOperator][3]", $root, null, "#\d+:\d+#"), $date));
                }

                $node = $this->pdf->FindSingleNode("./ancestor::p[1]/following::p[$xpathFragmentNotOperator][2]", $root);

                if (preg_match("#(.+)\s+\(([A-Z]{3})\)(?:,\s+(.+)|)$#", $node, $m)) {
                    $s->departure()
                        ->name($m[1])
                        ->code($m[2])
                        ->terminal(!empty($m[3]) ? preg_replace('/^Terminal\s*(.+)/i', '$1', $m[3]) : null, true, true);
                }
                $node = $this->pdf->FindSingleNode("./ancestor::p[1]/following::p[$xpathFragmentNotOperator][4]", $root);

                if (preg_match("#(.+)\s+\(([A-Z]{3})\)(?:,\s+(.+)|)$#", $node, $m)) {
                    $s->arrival()
                        ->name($m[1])
                        ->code($m[2])
                        ->terminal(!empty($m[3]) ? preg_replace('/^Terminal\s*(.+)/i', '$1', $m[3]) : null, true, true);
                }

                $s->extra()
                    ->duration($this->pdf->FindSingleNode("./ancestor::p[1]/following::p[position()<16][{$this->contains($this->t('Flight Duration'))}]", $root, true, "#{$this->opt($this->t('Flight Duration'))}:\s+(.+?)\s*,#"))
                    ->miles($this->pdf->FindSingleNode("./ancestor::p[1]/following::p[position()<16][{$this->contains($this->t('Miles:'))}]", $root, true, "#{$this->opt($this->t('Miles:'))}\s+(.+?)\s*,#"), true, true)
                ;

                $seatP1 = $this->pdf->FindSingleNode($xpathFragmentStatus, $root);
                $seatP2 = $this->pdf->FindSingleNode($xpathFragmentStatus . '/following::text()[normalize-space(.)][1]', $root);
                $node = $this->pdf->FindPreg("#{$this->opt($this->t('Status:'))}\s+[^,]+, (.+)#s", false, $seatP1 . ' ' . $seatP2);

                if (preg_match_all("#\b(\d{1,3}[A-Z])\b#", $node, $m)) {
                    $s->extra()->seats($m[1]);
                }
            }
            $ticketsAll = array_unique($ticketsAll);

            if (!empty($ticketsAll)) {
                $f->issued()->tickets($ticketsAll, false);
            }

            if (count($airs) === 1) {
                $sum = $this->normalizeAmount($this->pdf->FindSingleNode("(//text()[{$this->contains($this->t('Total Cost of the complete Trip in'))}]/following::text()[normalize-space(.)!=''][1])[last()]"));
                $cur = $this->pdf->FindSingleNode("(//text()[{$this->contains($this->t('Total fare for all travellers for all Air segments in'))}])[last()]",
                    null, true,
                    "#{$this->opt($this->t('Total fare for all travellers for all Air segments in'))}\s+([A-Z]{3})#");

                if (!empty($sum)) {
                    $f->price()
                        ->total($sum)
                        ->currency($cur);
                }
            }
        }

        //###########
        //# HOTELS ##
        //###########

        $xpath = "//text()[{$this->contains($this->t('Hotel Reference'))}]";
        $nodes = $this->pdf->XPath->query($xpath);

        foreach ($nodes as $root) {
            $h = $email->add()->hotel();

            if (preg_match("/({$this->opt($this->t('Hotel Reference'))})\s*:\s*([-A-Z\d]{5,})(?:\s*[,;]|$)/", $this->pdf->FindSingleNode('.', $root), $m)) {
                $h->general()->confirmation($m[2], $m[1]);
            } elseif ($this->pdf->FindSingleNode('.', $root, true, "#{$this->opt($this->t('The hotel reference number is not available'))}#")) {
                $h->general()->noConfirmation();
            }

            $h->general()
                ->date(strtotime($this->pdf->FindSingleNode("./following::text()[{$this->contains($this->t('Booking Date'))}][1]", $root, true, "#:\s*(.+)#")))
            ;

            $pers = $this->pdf->FindSingleNode("./ancestor::p[1]/preceding::p[normalize-space()!=''][position()<=6 and not(normalize-space()='TripAdvisor')][4]",
                $root, false, "/^\D+$/");
            $pers = array_filter(array_map("trim", explode(",", $pers)));

            if (empty($pers)) {
                $h->general()->travellers($pax);
            } else {
                $h->general()->travellers($pers);
            }

            // hotel name not by /ancestor::p[1]/preceding-sibling::p[3] cause separate pages
            // [normalize-space()][position()<=6 and not(normalize-space()='TripAdvisor')]  - kostyl between paages - bcd
            $h->hotel()
                ->name($this->pdf->FindSingleNode("./ancestor::p[1]/preceding::p[normalize-space()!=''][position()<=6 and not(normalize-space()='TripAdvisor')][3]", $root))
                ->address($this->pdf->FindSingleNode("./ancestor::p[1]/preceding::p[normalize-space()!=''][position()<=6 and not(normalize-space()='TripAdvisor')][2]", $root))
            ;
            $node = $this->pdf->FindSingleNode("./ancestor::p[1]/preceding-sibling::p[1]", $root);

            if (preg_match("/{$this->opt($this->t('Telephone'))}\s*:\s*([+(\d][-. \d)(]{5,}[\d)])(?:\s*,\s+{$this->opt($this->t('Telefax'))}|$)/", $node, $m)) {
                $h->hotel()->phone($m[1]);
            }

            if (preg_match("/{$this->opt($this->t('Telefax'))}\s*:\s*([+(\d][-. \d)(]{5,}[\d)])$/", $node, $m)) {
                $h->hotel()->fax($m[1]);
            }

            //  not by /ancestor::p[1]/preceding-sibling::p[5] cause separate pages
            // [normalize-space()][position()<=6 and not(normalize-space()='TripAdvisor')]  - kostyl between paages - bcd
            $node = $this->pdf->FindSingleNode("./ancestor::p[1]/preceding::p[normalize-space()!=''][position()<=6 and not(normalize-space()='TripAdvisor')][5]", $root);

            if (preg_match("#(\S+\s+\d+\s+\S+\s+\d+).+?\s+(\d+)\s+{$this->opt($this->t('Nights'))}#i", $node, $m)) {
                $h->booked()
                    ->checkIn($this->normalizeDate($m[1]))
                    ->checkOut(strtotime('+' . $m[2] . ' days', $this->normalizeDate($m[1])))
                ;
            }

            $roomDescription = $this->pdf->FindSingleNode("following::text()[{$this->contains($this->t('Room Description'))}]", $root, true, "#{$this->opt($this->t('Room Description'))}\s*:\s+(.+)#");

            $totalCurrency = $this->pdf->FindSingleNode("following::text()[{$this->contains($this->t('Total rate amount in'))}]", $root, true, "#{$this->opt($this->t('Total rate amount in'))}\s+([A-Z]{3})\b#");
            $totalPrice = $this->pdf->FindSingleNode("following::text()[{$this->contains($this->t('Total rate amount in'))}]/following::text()[normalize-space()][1]");

            if ($totalPrice !== null) {
                $h->price()
                    ->total($this->normalizeAmount($totalPrice))
                    ->currency($totalCurrency)
                ;
            }

            $rateCurrency = $this->pdf->FindSingleNode("following::text()[{$this->contains($this->t('The average rate per day in'))}]", $root, true, "#{$this->opt($this->t('The average rate per day in'))}\s+([A-Z]{3})\b#");
            $rate = $this->pdf->FindSingleNode("following::text()[{$this->contains($this->t('The average rate per day in'))}]/following::text()[normalize-space()][1]", $root);

            if ($roomDescription || $rate) {
                $room = $h->addRoom();

                if ($roomDescription) {
                    $room->setDescription($roomDescription);
                }

                if ($rate) {
                    $room->setRate($rate . ' ' . $rateCurrency);
                }
            }

            $cancel = $this->pdf->FindSingleNode("./following::text()[{$this->contains($this->t('Cost Free Cancellation'))}]", $root, true, "#{$this->opt($this->t('Cost Free Cancellation'))}:\s*(.+)#");

            if (!empty($cancel)) {
                $h->general()
                    ->cancellation($cancel);

                if (preg_match("# (?:until|bis) (.+?)\(#", $cancel, $m)) {
                    $h->booked()->deadline($this->normalizeDate($m[1]));
                }
                $h->booked()
                    ->parseNonRefundable("#\- Ikke\-refunderbart \-#");
            }
        }

        //#########
        //# CARS ##
        //#########

        $xpath = "//text()[{$this->contains($this->t('Confirmation Number of the Car Vendor:'))}]";
        $nodes = $this->pdf->XPath->query($xpath);

        foreach ($nodes as $root) {
            $r = $email->add()->rental();

            $r->general()
                ->confirmation($this->pdf->FindSingleNode(".", $root, true, "#{$this->opt($this->t('Confirmation Number of the Car Vendor:'))}\s*([A-Z\d]+)#"))
                ->date(strtotime($this->pdf->FindSingleNode("./following::text()[{$this->contains($this->t('Booking Date'))}][1]", $root, true, "#:\s*(.+)#")))
                ->travellers($pax)
            ;

            // Pick Up
            $location = implode(", ", $this->pdf->FindNodes("./ancestor::p[1]/following::p[2]//text()", $root));
            $location = preg_replace("#, " . $this->opt($this->t("Flight Number")) . ":\s*[A-Z\d]{2}\d{1,5}.*#", '', $location);
            /*	Schweiz
                ZUERICH KLOTEN FLUGHAFEN, ZURICH, ZURICH AIRPORT, PARKING 3 G1 (06:30 - 23:30),
                Flugnummer: EW9768
             */
            if (preg_match("#(.+)\(\s*(\d+:\d+\s*-\s*\d+:\d+)\s*\)#", $location, $m)) {
                $r->pickup()
                    ->location(preg_replace("#,+#", ',', trim($m[1])))
                    ->openingHours($m[2]);
            } else {
                $r->pickup()
                    ->location(preg_replace("#,+#", ',', trim($location)));
            }
            $date = $this->normalizeDate($this->pdf->FindSingleNode("./ancestor::p[1]/preceding::p[2]", $root,
                null, "#(.+),[^,]+$#"));

            if (!empty($date)) {
                $r->pickup()
                    ->date(strtotime($this->pdf->FindSingleNode("./ancestor::p[1]/following::p[1]", $root, null, "#\d+:\d+#"), $date));
            }

            // Drop Off

            $location = implode(", ", array_merge(
                    [$this->pdf->FindSingleNode("./ancestor::p[1]/following::p[4]//text()[normalize-space()][1]", $root, null, "#.+,\s*([^,]+)$#")],
                    $this->pdf->FindNodes("./ancestor::p[1]/following::p[4]//text()[normalize-space()][position()>1]", $root)));
            $location = preg_replace("#, " . $this->opt($this->t("Flight Number")) . ":\s*[A-Z\d]{2}\d{1,5}.*#", '', $location);

            if (preg_match("#(.+)\(\s*(\d+:\d+\s*-\s*\d+:\d+)\s*\)#", $location, $m)) {
                $r->dropoff()
                    ->location(preg_replace("#,+#", ',', trim($m[1])))
                    ->openingHours($m[2]);
            } else {
                $r->dropoff()
                    ->location(preg_replace("#,+#", ',', trim($location)));
            }
            $dDate = $this->normalizeDate($this->pdf->FindSingleNode("./ancestor::p[1]/following::p[4]//text()[normalize-space()][1]", $root, null, "#(.+?),\s+[A-z]+#")); //"#(.+),[^,]+$#"));

            if (!empty($dDate)) {
                $r->dropoff()
                    ->date(strtotime($this->pdf->FindSingleNode("./ancestor::p[1]/following::p[3]", $root, null, "#\d+:\d+#"), $dDate));
            }

            // Car
            $type = implode(' ', $this->pdf->FindNodes("./following::text()[{$this->starts($this->t('Type of Car:'))}][1]/ancestor::p[1]//text()", $root));

            if (preg_match("#:\s*(.+)#", $type, $m)) {
                $r->car()
                    ->type($m[1]);
            }
            // Extra
            $r->extra()
                ->company($this->pdf->FindSingleNode(".", $root, true, "#(.+), {$this->opt($this->t('Confirmation Number of the Car Vendor:'))}#"));

            $cur = $this->pdf->FindSingleNode("./following::text()[{$this->contains($this->t('Total rate amount in'))}][1]",
                $root, true, "#{$this->opt($this->t('Total rate amount in'))}\s+([A-Z]{3})#");
            $sum = $this->pdf->FindSingleNode("./following::text()[{$this->contains($this->t('Total rate amount in'))}][1]/following::text()[normalize-space(.)!=''][1]", $root);

            if (!empty($sum)) {
                $r->price()
                    ->total($this->normalizeAmount($sum))
                    ->currency($cur)
                ;
            }
        }

        return $email;
    }

    private function getProvider(PlancakeEmailParser $parser)
    {
        $this->detectEmailByHeaders(['from' => $parser->getCleanFrom(), 'subject' => $parser->getSubject()]);

        if (isset($this->providerCode)) {
            if ($this->providerCode === 'sabre') {
                return null;
            } else {
                return $this->providerCode;
            }
        }

        foreach ($this->detectCompany as $code => $criteria) {
            if (count($criteria) > 0) {
                foreach ($criteria as $search) {
                    if (stripos($this->http->Response['body'], $search) !== false
                        || isset($this->pdf) && stripos($this->pdf->Response['body'], $search) !== false
                    ) {
                        return $code;
                    }
                }
            }
        }
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

            foreach ($this->detectCompany as $code => $criteria) {
                if (count($criteria) > 0) {
                    foreach ($criteria as $search) {
                        if (stripos($text, $search) !== false) {
                            return $code;
                        }
                    }
                }
            }
        }

        return null;
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function assignLang($body): bool
    {
        if (isset($this->detectBody)) {
            foreach ($this->detectBody as $lang => $reBody) {
                if (stripos($body, $reBody[0]) !== false && stripos($body, $reBody[1]) !== false) {
                    $this->lang = substr($lang, 0, 2);

                    return true;
                }
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

    private function normalizeDate($date)
    {
        //		$year = date('Y', $this->date);
        $in = [
            //Tuesday, 29 May 2018 | Tuesday, 29May2018
            '#^(\w+),\s+(\d+)\s*([\D\S]+)\s*(\d{4})$#u',
            //01.08.2018 | 30/08/2018
            '#^\s*(\d+)[\.\/](\d+)[\.\/](\d{4})\s*$#',
            //06JUL18
            '#^\s*(\d+)(\D+)(\d{2})\s*$#',
        ];
        $out = [
            '$2 $3 $4',
            '$3-$2-$1',
            '$1 $2 $3',
        ];
        $str = strtotime($this->dateStringToEnglish(preg_replace($in, $out, $date)));

        return $str;
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) {
            return 'contains(normalize-space(.),"' . $s . '")';
        }, $field)) . ')';
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'starts-with(normalize-space(.),"' . $s . '")'; }, $field)) . ')';
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) { return str_replace(' ', '\s+', preg_quote($s)); }, $field)) . ')';
    }

    /**
     * @param string $string Unformatted string with amount
     */
    private function normalizeAmount(?string $s): string
    {
        $s = preg_replace('/\s+/', '', $s);             // 11 507.00  ->  11507.00
        $s = preg_replace('/[,.\'](\d{3})/', '$1', $s); // 2,790      ->  2790    |    4.100,00  ->  4100,00    |    1'619.40  ->  1619.40
        $s = preg_replace('/,(\d{1,2})$/', '.$1', $s);    // 18800,00   ->  18800.00

        return $s;
    }
}
