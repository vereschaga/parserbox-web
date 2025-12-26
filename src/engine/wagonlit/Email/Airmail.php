<?php

namespace AwardWallet\Engine\wagonlit\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Common\Hotel;
use AwardWallet\Schema\Parser\Email\Email;

class Airmail extends \TAccountChecker
{
    public $mailFiles = "wagonlit/it-10831160.eml, wagonlit/it-10831823.eml, wagonlit/it-126685152.eml, wagonlit/it-127079034.eml";

    public $reFrom = ["@carlsonwagonlit.com", "trondent.com"];
    public $reBody = [
        'en'  => ['contactcwt.com', 'ELECTRONIC RECEIPT'],
        'en2' => ['CWT/Carlson Global', 'Invoice Details'],
        'en3' => ['contactcwt.com', 'ITINERARY ONLY'],
    ];
    public $reSubject = [
        'DO NOT DELETE - E-RECEIPT for',
    ];
    public $lang = '';
    /** @var \HttpBrowser */
    public $pdf;
    public $pdfNamePattern = "Airmail.pdf";
    public static $dict = [
        'en' => [
            'Pick up'  => ['Pick up', 'Pick Up'],
            'Drop off' => ['Drop off', 'Drop Off'],
        ],
    ];

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        if (empty($pdf)) {
            $pdfs = $parser->searchAttachmentByName(".*\.pdf");
        }
        $foundPdf = false;

        if (count($pdfs) > 0) {
            $this->pdf = clone $this->http;

            foreach ($pdfs as $pdf) {
                $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

                if ($text == null) {
                    continue;
                }

                if ($this->assignLang($text) !== true) {
                    continue;
                }
                $html = \PDF::convertToHtml($parser->getAttachmentBody($pdf), \PDF::MODE_SIMPLE);

                if ($html == null) {
                    continue;
                }
                $NBSP = chr(194) . chr(160);
                $this->pdf->SetBody(str_replace($NBSP, ' ', html_entity_decode($html)));
                $this->parseEmailPdf($email, $text);
                $foundPdf = true;
                $type = 'Pdf';
            }
        }

        if ($foundPdf === false) {
            $text = $this->http->Response['body'];
            $this->assignLang($text);
            $this->parseEmail($email);
            $type = 'Html';
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . $type . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $pdf = $parser->searchAttachmentByName($this->pdfNamePattern);

        if (empty($pdf)) {
            $pdf = $parser->searchAttachmentByName(".*\.pdf");
        }

        if (isset($pdf[0])) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf[0]));

            return $this->assignLang($text);
        }

        if ($this->http->XPath->query("//a[contains(@href,'carlsonwagonlit.com')]")->length > 0) {
            $body = $parser->getHTMLBody();

            return $this->assignLang($body);
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        foreach ($this->reFrom as $reFrom) {
            if (isset($headers['from']) && stripos($headers['from'], $reFrom) !== false && isset($this->reSubject)) {
                foreach ($this->reSubject as $reSubject) {
                    if (stripos($headers["subject"], $reSubject) !== false) {
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
        return count(self::$dict);
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

    /**
     * <pre>
     * Example:
     * abc SPLIT text\n text1\n acv SPLIT text\n text1
     * /(SPLIT)/
     * [0] => SPLIT text text1 acv
     * [1] => SPLIT text text1
     * <pre>.
     */
    protected function splitter($regular, $text)
    {
        $result = [];

        $array = preg_split($regular, $text, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
        $result[] = array_shift($array);

        for ($i = 0; $i < count($array) - 1; $i += 2) {
            $result[] = $array[$i] . $array[$i + 1];
        }

        return $result;
    }

    private function parseEmailPdf(Email $email, $textPDF)
    {
//         $this->logger->debug('$textPDF = '.print_r( $textPDF,true));

        $email->obtainTravelAgency();

        //##################
        //##  SEGMENTS  ####
        //##################

        $textPDF = preg_replace("/\n *Page \d+ of \d+ *\n/", "\n", $textPDF);
        $parts = $this->splitter("#((?:Flight Details|Hotel Details|Car Details|Other Details|Limo Details)\s)#", $textPDF);
        $date = null;

        if (count($parts) > 1 && preg_match("/\n *([[:alpha:]]+, \d{1,2} [[:alpha:]]+ \d{4})\s*$/", $parts[0], $m)) {
            $date = $m[1];
            array_shift($parts);
        }

        $flights = [];
        $hotels = [];
        $cars = [];
        $limos = [];

        foreach ($parts as $part) {
            if (preg_match("/\n *([[:alpha:]]+, \d{1,2} [[:alpha:]]+ \d{4})\s*$/", $part, $m)) {
                $part = str_replace($m[0], '', $part);
                $nextDate = $m[1];
            }

            if (preg_match("#^(.+?)\n *Information#s", $part, $m)) {
                $part = $m[1];
            }

            if (strpos($part, $this->t("Flight Details")) === 0) {
                $flights[] = ['date' => $date, 'text' => $part];
            } elseif (strpos($part, $this->t("Hotel Details")) === 0) {
                $hotels[] = ['date' => $date, 'text' => $part];
            } elseif (strpos($part, $this->t("Car Details")) === 0) {
                $cars[] = ['date' => $date, 'text' => $part];
            } elseif (strpos($part, $this->t("Limo Details")) === 0) {
                $limos[] = ['date' => $date, 'text' => $part];
            } else {
                if (strpos($part, $this->t("Other Details")) === false) {
                    $this->logger->debug("Type not detected");

                    return null;
                }
            }

            if (!empty($nextDate)) {
                $date = $nextDate;
                $nextDate = null;
            }
        }
        //##################
        //## MAIN DATA  ####
        //##################
        $pax = [];
        $recordLocator = $this->re("#Record Locator[\s:]+([A-Z\d]{5,})#", $textPDF);

        if (preg_match_all("#Travell?er\s{3,}(.+)#", $textPDF, $m)) {
            $pax = $m[1];
        } elseif (preg_match("#Travell?ers (.+\n(?: {20,}[[:alpha:] \-]+\n)+) {0,20}\S.+#", $textPDF, $m)) {
            $pax = preg_split("/\s*\n\s*/", trim($m[1]));
        }

        //##################
        //##  FLIGHTS   ####
        //##################
        if (!empty($flights)) {
            $f = $email->add()->flight();

            // General
            $f->general()
                ->travellers($pax);

            // Issued
            if (preg_match_all("#Airline Code +(\d{3}) +.*\s*\n(?: {40,}.*\n)?\s*Ticket Number[ :]+(\d{10})\s+#", $textPDF, $ticketMatches)) {
                $tickets = [];

                foreach ($ticketMatches[0] as $i => $v) {
                    $tickets[] = $ticketMatches[1][$i] . $ticketMatches[2][$i];
                }
                $f->issued()
                    ->tickets($tickets, false);
            }

            // Price
            if (!preg_match("/{$this->opt($this->t('*** EXCHANGE ***'))}/", $textPDF)
                && preg_match("#" . $this->opt($this->t("Expense this amount:")) . "#", $textPDF)
            ) {
                $currency = $this->re("#" . $this->opt($this->t("Expense this amount:")) . " +\d[\d\.\, ]* *([A-Z]{3})\n#", $textPDF);
                $f->price()
                    ->currency($currency)
                    ->total(PriceHelper::parse($this->re("#" . $this->opt($this->t("Expense this amount:")) . " +(\d[\d\.\, ]*) *[A-Z]{3}\n#", $textPDF), $currency))
                ;
                $cost = 0;

                if (preg_match_all("#\s{2,}Ticket Base Fare +(\d[\d\.\, ]*)\n#", $textPDF, $costMatches)) {
                    foreach ($costMatches[1] as $v) {
                        $c = PriceHelper::parse($v, $currency);

                        if (!empty($c) && is_numeric($c)) {
                            $cost += PriceHelper::parse($v, $currency);
                        } else {
                            $cost = 0;

                            break;
                        }
                    }
                }

                if (!empty($cost)) {
                    $f->price()
                        ->cost($cost);
                }
                $tax = 0;

                if (preg_match_all("#\s{2,}Ticket Tax Fare +(\d[\d\.\, ]*)\n#", $textPDF, $taxMatches)) {
                    foreach ($taxMatches[1] as $v) {
                        $c = PriceHelper::parse($v, $currency);

                        if (!empty($c) && is_numeric($c)) {
                            $tax += PriceHelper::parse($v, $currency);
                        } else {
                            $tax = 0;

                            break;
                        }
                    }
                }

                if (!empty($tax)) {
                    $f->price()
                        ->tax($tax);
                }
            }
        }
        // Segments
        $usedAirlines = $airlineConfirmations = [];

        foreach ($flights as $segment) {
            $sText = $segment['text'] ?? null;
            $sDate = $segment['date'] ?? null;
            $s = $f->addSegment();

            $date = null;
            // Airline
            if (preg_match("#{$this->opt($this->t('Flight'))}\s+([A-Z][A-Z\d]|[A-Z\d][A-Z])[ ]*(\d+)\s+#", $sText, $m)) {
                $s->airline()
                    ->name($m[1])
                    ->number($m[2]);

                $date = strtotime($this->normalizeDate($this->pdf->FindSingleNode("//text()[normalize-space() = '" . $m[1] . $m[2] . "']/preceding::text()[normalize-space(.)!=''][1][{$this->eq($this->t('Flight'))} or {$this->contains($this->t('OPERATED BY'))}]/preceding::text()[contains(.,',') and contains(translate(.,'0123456789','dddddddddd'),'dddd')][1]")));

                if (empty($date)) {
                    $date = strtotime($this->normalizeDate($sDate));
                }

                if (!empty($operator = $this->pdf->FindSingleNode("//text()[normalize-space() = '" . $m[1] . $m[2] . "']/preceding::text()[normalize-space(.)!=''][1][{$this->contains($this->t('OPERATED BY'))}]", null, true,
                    "#{$this->opt($this->t('OPERATED BY'))}[\s\/]+(.+?)\s*(?: DBA .*|$)#"))) {
                    $s->airline()
                        ->operator($operator);
                }
            }

            if (preg_match("/^[ ]*(.{2,}?)[ ]{2,}{$this->opt($this->t('Airline Reference'))}.+\n+[ ]*{$this->opt($this->t('Flight'))}\s+(?:[A-Z][A-Z\d]|[A-Z\d][A-Z])[ ]*\d+\s+/m", $sText, $m)) {
                $usedAirlines[] = $m[1];
            }

            if (preg_match("/{$this->opt($this->t('Airline Reference'))}[# ]+([A-Z\d]{5,7})\n/", $sText, $m)) {
                $airlineConfirmations[] = $m[1];
                $s->airline()
                    ->confirmation($m[1]);
            }

            $table = $this->re("#\n({$this->opt($this->t("Departing"))}.+)#ms", $sText);
            $table = $this->splitCols($table, $this->colsPos($table, 12));

            if (count($table) < 2) {
                $this->logger->debug("Flight. other format");

                return null;
            }
            $table = array_map(function ($s) {
                return preg_replace("#(.+)\n{3,}.+$#s", '$1', $s);
            }, array_slice($table, 0, 2));
//            $this->logger->debug('$table = '.print_r( $table,true));

            // Departure
            if (preg_match("#{$this->opt($this->t('Departing'))}[\s:]+(.+)\n([\s\S]+?)(?:\s*\n\s*([\w ]*{$this->opt($this->t('Terminal'))}[\w ]*)|\s*\n\s*CHECK-IN|\s*$)#i", $table[0], $m)) {
                $s->departure()
                    ->noCode()
                    ->name(preg_replace("/\s+/", ' ', trim($m[2])))
                    ->date(strtotime($m[1], $date))
                    ->terminal((!empty($m[3])) ? trim(preg_replace("/terminal/i", ' ', $m[3])) : null, true, true)
                ;
            }
            // Arrival
            if (preg_match("#{$this->opt($this->t('Arriving'))}[\s:]+(.+)\n([\s\S]+?)(?:\s*\n\s*([\w ]*{$this->opt($this->t('Terminal'))}[\w ]*)|\s*$)#i", $table[1], $m)) {
                if (preg_match("#^\s*(\d+:\d+(?:\s*[ap]m)?)\s+(\d+\s+\w+)\s*$#i", $m[1], $v)) {
                    $date = EmailDateHelper::parseDateRelative($this->dateStringToEnglish($v[2]), strtotime("-2 days", $date));
                    $s->arrival()->date(strtotime($v[1], $date));
                } else {
                    $s->arrival()->date(strtotime($m[1], $date));
                }
                $s->arrival()
                    ->noCode()
                    ->name(preg_replace("/\s+/", ' ', trim($m[2])))
                    ->terminal((!empty($m[3])) ? trim(preg_replace("/terminal/i", ' ', $m[3])) : null, true, true)
                ;
            }

            // Extra
            if (preg_match("#{$this->opt($this->t('Seats'))} +(\d{1,3}[A-Z](?: +\d{1,3}[A-Z])+) +(.+?)(?: {3,}(.+?))?(\n.*)?\s+{$this->opt($this->t('Departing'))}#", $sText, $m)) {
                $s->extra()
                    ->cabin($m[2])
                ;
                $seats = [];
                $seats = array_merge($seats, preg_split("/\s+/", $m[1]));
                if (!empty($m[4]) && preg_match("/\n {20,}(\d{1,3}[A-Z](?: \d{1,3}[A-Z])+)( {3,}|\n|$)/",$m[4], $mat)) {
                    $seats = array_merge($seats, preg_split("/\s+/", $mat[1]));
                }
                $s->extra()
                    ->seats($seats);

                if (isset($m[3]) && preg_match("#Non[- ]stop#i", $m[3])) {
                    $s->extra()
                        ->stops(0);
                }
            } elseif (preg_match("#{$this->opt($this->t('Seat'))} +(\w+) {3,}(.+?)(?: {3,}(.+?))?(?:\n.*)?\s+{$this->opt($this->t('Departing'))}#", $sText, $m)) {

                $s->extra()
                    ->seat(preg_match("/^\d{1,3}[A-Z]$/", $m[1]) ? $m[1] : null, true, true)
                    ->cabin($m[2])
                ;

                if (isset($m[3]) && preg_match("#Non[- ]stop#i", $m[3])) {
                    $s->extra()
                        ->stops(0);
                }
            }
            // Duration
            $s->extra()
                ->duration($this->re("#{$this->opt($this->t("Flight Duration"))}\s+(.+)#", $sText))
                ->miles($this->re("#{$this->opt($this->t("Distance"))}\s+(.+)#", $sText))
                ->meal($this->re("#{$this->opt($this->t("Meal"))}\s+(.+)#", $sText))
                ->aircraft($this->re("#{$this->opt($this->t("Equipment"))}\s+(.+)#", $sText))
            ;
        }

        // Program
        $usedAirlines = array_unique($usedAirlines);

        if (count($usedAirlines)
            && preg_match_all("#^{$this->opt($usedAirlines)}\s+(?-i)([-A-Z\d]{5,})$#im", $this->re("#Frequent Flyer[\s\#]+(.+?)\n+[ ]*(?:AIRLINE PASSENGER RECEIPT|Special Notes)#s", $textPDF), $accMatches)
        ) {
            $f->program()->accounts($accMatches[1], false);
        }

        if (isset($f)) {
            if (!in_array($recordLocator, $airlineConfirmations)) {
                $f->general()
                    ->confirmation($recordLocator);
            } else {
                $f->general()
                    ->noConfirmation();
            }
        }

        //##################
        //##   HOTELS   ####
        //##################
        foreach ($hotels as $segment) {
            $sText = $segment['text'] ?? null;
            $sDate = $segment['date'] ?? null;

            $h = $email->add()->hotel();

            // General
            $confirmation = $this->re("#Confirmation[ \#]+([A-Z\d]{5,})\n#", $sText);
            $h->general()
                ->confirmation($confirmation)
                ->travellers($pax)
            ;
            $cancellationPolicy = preg_replace('/[ ]*\n+[ ]*/', ' ', $this->re("#\n[ ]*CANCELLATION RULES\s+(.{2,}(?:\n.{2,}){0,2})\n+[ ]*ROOM RATE#", $sText));

            if (empty($cancellationPolicy)) {
                $cancellationPolicy = $this->re("#CANCELLATION RULES\s+.+#", $sText);
            }

            if (empty($cancellationPolicy)) {
                $cancellationPolicy = trim($this->re("#([^\n]*CANCEL[^\n]*)\s+Room Type#", $sText));
            }
            $h->general()
                ->cancellation($cancellationPolicy, true, true);

            // Program
            $account = $this->re("#Frequent Guest\s+(?-i)([-A-Z\d]+?)(?:[ ]{2}|\n|$)#i", $sText)
                ?? $this->re("#\n[ ]*MEMBERSHIP (?-i)([-A-Z\d]{5,}?)(?:[ ]{2}|\n|$)#i", $sText)
            ;

            if (!empty($account)) {
                $h->program()
                    ->account($account, false);
            }

            $table = $this->re("#\n({$this->opt($this->t("Check in"))}.+\n(?: {25.}.*\n)?.+)\n#", $sText);
            $table = $this->splitCols($table, $this->colsPos($table, 10));

            if (count($table) < 2) {
                $this->logger->debug("Hotel. other format");

                return null;
            }
            $table = array_map(function ($s) {
                return preg_replace("#(.+)\n{3,}.+$#s", '$1', $s);
            }, array_slice($table, 0, 2));

            // Booked
            $h->booked()
                ->checkIn(strtotime($this->normalizeDate($this->re("#{$this->opt($this->t('Check in'))}[\s:]+(.+)#i", $table[0]))))
                ->checkOut(strtotime($this->normalizeDate($this->re("#{$this->opt($this->t('Check out'))}[\s:]+(.+)#i", $table[1]))))
            ;

            if (preg_match("/^THIS BOOKING CAN BE CANCELLED (?<prior>\d{1,3} HOURS?) BEFORE (?<hour>[\d: APM]+) HOURS AT THE LOCAL HOTEL TIME ON THE DATE OF ARRIVAL/i", $cancellationPolicy, $m)
            ) {
                $m['hour'] = preg_replace('/^(\d{1,2}) (\d{2})/', '$1:$2', $m['hour']);
                $this->parseDeadlineRelative($h, $m['prior'], $m['hour']);
            }

            // Hotel
            $name = $this->re("#{$this->opt($this->t('Hotel Details'))}\s+([^\n]+?)\s+Confirmation#", $sText);
            $hotelHtmlHeader = $this->pdf->FindNodes("//text()[{$this->eq('Hotel Details')}][ following::text()[normalize-space()][1][{$this->eq($name)}] and following::text()[normalize-space()][position()<8][{$this->contains($confirmation)}] ]/following::text()[normalize-space()][position()<8][ following::text()[normalize-space() and not({$this->eq('*H01*¤')}) and not({$this->starts('Page ')})][position()<8][{$this->eq('Check in')}] ]/ancestor::b");
            $hotelHtmlName = '';

            foreach ($hotelHtmlHeader as $hr) {
                if (preg_match("/^ {0,30}" . preg_quote($hr) . "/m", $sText)) {
                    $hotelHtmlName .= "\n" . $hr;
                }
            }

            $hotelTextHeader = preg_replace("/^(.{50,}) {3,}.*/", '$1', $this->re("/Hotel Details\s*\n([\s\S]+)\n\s*Check in/", $sText));

            if (preg_match("/^\s*" . preg_replace('/\s+/', '\s+', preg_quote($hotelHtmlName)) . "\s*\n([\s\S]+)/", $hotelTextHeader, $m)) {
                $h->hotel()
                    ->name($this->prettyPrint($hotelHtmlName))
                    ->address($this->prettyPrint(preg_replace("/^[ ]*{$this->opt('*H01*¤')}[ ]*$/im", '', $m[1])))
                ;
            }

            $h->hotel()
                ->phone($this->re("#Phone +([\d\- ]+)#i", $sText))
                ->fax($this->re("#Fax +([\d\- ]+)\n#i", $sText), true, true)
            ;

            // Rooms
            $h->addRoom()
                ->setType($this->re("#Room Type\s+(.+)#i", $sText))
                ->setRate($this->re("# Rate(?:\s+per\s+night)? +(.+)#", $sText)
                    ?? $this->re("#\n[ ]*COST/NIGHT ROOM (.*?\d.*?)(?:[ ]{2}|\n|$)#i", $sText), false, true)
            ;
        }

        //##################
        //##     CARS   ####
        //##################
        foreach ($cars as $segment) {
            $sText = $segment['text'] ?? null;
            $sDate = $segment['date'] ?? null;

            $r = $email->add()->rental();

            // General
            $r->general()
                ->confirmation($this->re("#Confirmation[ \#]+([A-Z\d]{5,})\n#", $sText))
                ->travellers($pax)
            ;

            // Program
            $account = $this->re("#Membership\s+([A-Z\d\-]+)(?:\n|$)#", $sText);

            if (!empty($account)) {
                $r->program()
                    ->account($account, false);
            }

            $table = $this->re("#\n({$this->opt($this->t("Pick up"))}.+)#ms", $sText);
            $table = $this->splitCols($table, $this->colsPos($table, 10));

            if (count($table) < 2) {
                $this->logger->debug("Rental. other format");

                return null;
            }
            $table = array_map(function ($s) {
                return preg_replace("#(.+)\n{3,}.+$#s", '$1', $s);
            }, array_slice($table, 0, 2));

            // Pickup
            $date = strtotime($this->normalizeDate($this->re("#{$this->opt($this->t('Pick up'))}[\s:]+.+\n{1,2}(.+)\n#i", $table[0])));
            $time = $this->re("#{$this->opt($this->t('Pick up'))}[\s:]+(.+)\n#i", $table[0]);

            if (!empty($date) && !empty($time)) {
                $r->pickup()
                    ->date(strtotime($time, $date));
            }

            // Dropoff
            $time = $this->re("#{$this->opt($this->t('Drop off'))}[\s:]+(.+)\n#i", $table[1]);
            $date = strtotime($this->normalizeDate($this->re("#{$this->opt($this->t('Drop off'))}[\s:]+.+\n{1,2}(.+)\n#i", $table[1])));

            if (!empty($date) && !empty($time)) {
                $r->dropoff()
                    ->date(strtotime($time, $date));
            }

            $name = $this->re("#{$this->opt($this->t('Car Details'))}\s+([^\n]+?)\s+Confirmation#s", $sText) . '';

            if (preg_match("#Confirmation[^\n]+\s+([^\n]*{$name}?[^\n]*)?\s+(.+?)\s+(?:Pick up:|Reserved)#s", $sText, $m)) {
                if (isset($m[1]) && !empty($m[1])) {
                    $name = $m[1];
                }

                $r->extra()
                    ->company($name);
                $r->pickup()
                    ->location(preg_replace("#\s+#", ' ', $m[2]));
                $r->dropoff()
                    ->location(preg_replace("#\s+#", ' ', $m[2]));
            }

            $r->car()
                ->type(implode("; ", [
                    'Category: ' . $this->re("#Category +(.+)#", $sText),
                    'Size: ' . $this->re("#Size +(.+)#", $sText),
                    'Transmission: ' . $this->re("#Transmission +(.+)#", $sText),
                ]));
        }

        //##################
        //##    LIMOS   ####
        //##################
        foreach ($limos as $segment) {
            $sText = $segment['text'] ?? null;
            $sDate = $segment['date'] ?? null;
            $date = strtotime($sDate);
            $t = $email->add()->transfer();

            // General
            $t->general()
                ->confirmation($this->re("#Confirmation[ \-]+([A-Z\d]{5,})\n#", $sText))
                ->travellers($pax)
            ;

            // Segments
            $s = $t->addSegment();

            // Departure
            $location = $this->re("/{$this->opt($this->t('Pick up'))}[ \-]+(?:HOME[ \-]+)?(.+?)(?: AT \d{3,4}.*)\n/", $sText);

            if (preg_match("/^\s*([A-Z]{3}) AIRPORT\s*$/", $location, $m)) {
                $s->departure()
                    ->code($m[1])
                    ->name($location)
                ;
            } else {
                $s->departure()
                    ->address($location)
                ;
            }

            if (!empty($date) && preg_match("#{$this->opt($this->t('Pick up'))}[ \-]+(.+?) AT (?<h>\d{1,2})(?<m>\d{2})(?<t>[AP])?\n#", $sText, $m)) {
                $s->departure()
                    ->date(strtotime($m['h'] . ':' . $m['m'] . (!empty($m['t']) ? ' ' . $m['t'] . 'M' : ''), $date));
                $s->arrival()
                    ->noDate();
            }

            // Arrival
            $location = $this->re("/{$this->opt($this->t('Drop off'))}[ \-]+(?:HOME[ \-]+)?(.+)\n/", $sText);

            if (preg_match("/^\s*([A-Z]{3}) AIRPORT\s*$/", $location, $m)) {
                $s->arrival()
                    ->code($m[1])
                    ->name($location)
                ;
            } else {
                $s->arrival()
                    ->address($location)
                ;
            }
        }

        return $email;
    }

    private function parseEmail(Email $email)
    {
        //##################
        //## MAIN DATA  ####
        //##################
        $pax = $this->http->FindNodes("//text()[{$this->eq($this->t('Traveler'))}]/ancestor::td[1]/following-sibling::td[normalize-space(.)!=''][1]//text()[normalize-space(.)!='']");
        if (empty($pax)) {
            $pax = [];
            $pax = array_merge($pax, $this->http->FindNodes("//text()[{$this->eq($this->t('Travelers'))}]/ancestor::td[1]/following-sibling::td[normalize-space(.)!=''][1]//text()[normalize-space(.)!='']"));
            $paxNodes = $this->http->XPath->query("//text()[{$this->eq($this->t('Travelers'))}]/ancestor::tr[1]/following-sibling::tr");
            foreach ($paxNodes as $pRoot) {
                $names = $this->http->FindNodes("td[normalize-space()]", $pRoot);
                if (count($names) === 1) {
                    $pax[] = array_shift($names);
                } else {
                    break;
                }
            }
        }
        //##################
        //##  FLIGHTS   ####
        //##################
        $nodes = $this->http->XPath->query("//text()[{$this->contains($this->t('Flight Details'))}]/ancestor::tr[1]");

        if ($nodes->length > 0) {
            $recordLocator = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Record Locator'))}]", null, true, "#[\s:]+([A-Z\d]{5,})#");

            $f = $email->add()->flight();

            // General
            $f->general()
                ->travellers($pax);

            // Program
            $accounts = [];
            $node = implode("\n", $this->http->FindNodes("//text()[{$this->starts($this->t('Frequent Flyer'))}]/ancestor::tr[1]/preceding-sibling::tr[1]/following-sibling::tr[normalize-space(.)!=''][position()<5]"));

            if (!empty($acc = $this->re("#{$this->opt($this->t('Frequent Flyer'))}[\s\#]+(.+?){$this->opt($this->t('AIRLINE PASSENGER RECEIPT'))}#s", $node))) {
                $accounts = array_unique(array_map(function ($s) {
                    return $this->re("#([A-Z\-\d]{5,})$#", $s);
                }, array_filter(array_map("trim", explode("\n", $acc)))));
            }

            if (!empty($accounts)) {
                $f->program()
                    ->accounts($accounts, false);
            }
        }
        // Issued
        $ticketsNodes = $this->http->XPath->query("//tr[td[normalize-space()][1][{$this->eq($this->t('Ticket Number'))}]]");
        $tickets = [];
        foreach ($ticketsNodes as $tRoot) {
            $ac = $this->http->FindSingleNode("preceding-sibling::tr[1]/td[normalize-space()][2]", $tRoot, null, "/^\s*(\d{3}) \w+/");
            $number = $this->http->FindSingleNode("td[normalize-space()][2]", $tRoot, null, "/^\s*(\d{10}(?:[\-\/]\d+)?)\s*$/");
            $tickets[] = $ac.$number;
        }


        $airlineConfirmations = [];

        foreach ($nodes as $root) {
            $date = strtotime($this->normalizeDate($this->http->FindSingleNode("./preceding-sibling::tr[normalize-space(.)!=''][count(descendant::td)<3][contains(translate(.,'0123456789','dddddddddd'),'dddd')][1]", $root)));

            $s = $f->addSegment();

            // Airline
            $node = $this->http->FindSingleNode("(./following-sibling::tr[normalize-space(.)!=''][position()<10]/descendant::text()[normalize-space(.)='Flight'])[1]/following::text()[normalize-space(.)!=''][1]", $root);

            if (preg_match("#([A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(\d+)#", $node, $m)) {
                $s->airline()
                    ->name($m[1])
                    ->number($m[2]);
            }
            $confirmation = $this->http->FindSingleNode("(./following-sibling::tr[normalize-space(.)!=''][position()<10]/descendant::text()[starts-with(normalize-space(.), 'Airline Reference #')])[1]", $root, null,
                "/#\s*([A-Z\d]{5,7})\s*$/");

            if (!empty($confirmation)) {
                $airlineConfirmations[] = $confirmation;
                $s->airline()
                    ->confirmation($confirmation);
            }
            $operator = $this->http->FindSingleNode("(./following-sibling::tr[normalize-space(.)!=''][position()<10]/descendant::text()[starts-with(normalize-space(.), 'OPERATED BY')])[1]", $root, null,
                "/OPERATED BY\s+\/?(?: *OPERATED BY +)?(.+?)\s*(?: DBA .*)?$/");

            if (!empty($operator)) {
                $s->airline()
                    ->operator($operator);
            }

            // Departure
            $s->departure()
                ->noCode()
                ->name($this->http->FindSingleNode("(./following-sibling::tr[normalize-space(.)!=''][position()<10]/descendant::text()[starts-with(normalize-space(.),'Departing')])[1]/ancestor::tr[1]/following-sibling::tr[normalize-space(.)!=''][2]/td[normalize-space(.)!=''][1]", $root))
            ;
            $time = $this->http->FindSingleNode("(./following-sibling::tr[normalize-space(.)!=''][position()<10]/descendant::text()[starts-with(normalize-space(.),'Departing')])[1]/ancestor::tr[1]/following-sibling::tr[normalize-space(.)!=''][1]/td[normalize-space(.)!=''][1]", $root);

            if (!empty($date) && !empty($time)) {
                $s->departure()
                    ->date(strtotime($time, $date));
            }

            // Arrival
            $s->arrival()
                ->noCode()
                ->name($this->http->FindSingleNode("(./following-sibling::tr[normalize-space(.)!=''][position()<10]/descendant::text()[starts-with(normalize-space(.),'Departing')])[1]/ancestor::tr[1]/following-sibling::tr[normalize-space(.)!=''][2]/td[normalize-space(.)!=''][2]", $root))
            ;
            $time = $this->http->FindSingleNode("(./following-sibling::tr[normalize-space(.)!=''][position()<10]/descendant::text()[starts-with(normalize-space(.),'Departing')])[1]/ancestor::tr[1]/following-sibling::tr[normalize-space(.)!=''][1]/td[normalize-space(.)!=''][2]", $root);

            if (!empty($date) && !empty($time)) {
                if (preg_match("#^\s*(\d+:\d+(?:\s*[ap]m)?)\s+(\d+\s+\w+)\s*$#i", $time, $v)) {
                    $date = EmailDateHelper::parseDateRelative($this->dateStringToEnglish($v[2]),
                        strtotime("-2 days", $date));
                    $s->arrival()
                        ->date(strtotime($v[1], $date));
                } else {
                    $s->arrival()
                        ->date(strtotime($time, $date));
                }
            }

            // Extra
            $s->extra()
                ->cabin($this->http->FindSingleNode("(./following-sibling::tr[normalize-space(.)!=''][position()<10]/td[not(.//td)][".$this->eq($this->t('Seat'))." or ".$this->eq($this->t('Seats'))."])[1]/following-sibling::td[normalize-space(.)!=''][2]", $root))
                ->duration($this->http->FindSingleNode("(./following-sibling::tr[normalize-space(.)!=''][position()<10]/descendant::text()[starts-with(normalize-space(.),'Flight Duration')])[1]/ancestor::td[1]/following-sibling::td[normalize-space(.)!=''][1]", $root))
                ->miles($this->http->FindSingleNode("(./following-sibling::tr[normalize-space(.)!=''][position()<10]/descendant::text()[starts-with(normalize-space(.),'Distance')])[1]/ancestor::td[1]/following-sibling::td[normalize-space(.)!=''][1]", $root))
                ->meal($this->http->FindSingleNode("(./following-sibling::tr[normalize-space(.)!=''][position()<10]/descendant::text()[starts-with(normalize-space(.),'Meal')])[1]/ancestor::td[1]/following-sibling::td[normalize-space(.)!=''][1]", $root))
                ->aircraft($this->http->FindSingleNode("(./following-sibling::tr[normalize-space(.)!=''][position()<10]/descendant::text()[starts-with(normalize-space(.),'Equipment')])[1]/ancestor::td[1]/following-sibling::td[normalize-space(.)!=''][1]", $root))

            ;
            $seats = $this->http->FindSingleNode("(./following-sibling::tr[normalize-space(.)!=''][position()<10]/td[not(.//td)][".$this->eq($this->t('Seat'))." or ".$this->eq($this->t('Seats'))."])[1]/following-sibling::td[normalize-space(.)!=''][1]", $root);
            if (preg_match("/^\s*\d{1,3}[A-Z]( +\d{1,3}[A-Z])*\s*$/", $seats)) {
                $s->extra()
                    ->seats(preg_split("/\s+/", trim($seats)));
            }

            $node = $this->http->FindSingleNode("(./following-sibling::tr[normalize-space(.)!=''][position()<10]/td[not(.//td)][".$this->eq($this->t('Seat'))." or ".$this->eq($this->t('Seats'))."])[1]/following-sibling::td[normalize-space(.)!=''][3]", $root);

            if (preg_match("#Non[- ]stop#i", $node)) {
                $s->extra()
                    ->stops(0);
            }
        }

        if (isset($f)) {
            if (!in_array($recordLocator, $airlineConfirmations)) {
                $f->general()
                    ->confirmation($recordLocator);
            } else {
                $f->general()
                    ->noConfirmation();
            }
        }

        //##################
        //##   HOTELS   ####
        //##################
        $nodes = $this->http->XPath->query("//text()[{$this->contains($this->t('Hotel Details'))}]/ancestor::tr[1]");

        foreach ($nodes as $root) {
            $h = $email->add()->hotel();

            // General
            $h->general()
                ->confirmation($this->http->FindSingleNode("./following-sibling::tr[normalize-space(.)!=''][position()<25]/descendant::text()[{$this->starts($this->t('Confirmation'))}][1]", $root, true, "#\s+([A-Z\d\-]{5,})\s*$#"))
                ->travellers($pax)
            ;

            $cancellation = trim(preg_replace("/\s+/", ' ', $this->re("/^\s*CANCELLATION RULES ((?:.*\n)+)\s*ROOM RATE/", implode("\n",
                $this->http->FindNodes("./following-sibling::tr[normalize-space(.)!=''][position()<25]/descendant::text()[{$this->starts($this->t('CANCELLATION RULES'))}][1]/ancestor::tr[1]/preceding-sibling::tr[1]/following-sibling::tr[position() < 6]", $root)
            ))));
            $h->general()
                ->cancellation($cancellation, true, true);

            // Program
            $account = $this->http->FindSingleNode("./following-sibling::tr[normalize-space(.)!=''][position()<25]/descendant::text()[{$this->starts($this->t('Frequent Guest'))}][1]/ancestor::td[1]/following-sibling::td[normalize-space(.)!=''][1]", $root);

            if (!empty($account)) {
                $h->program()
                    ->account($account, false);
            }

            // Booked
            $h->booked()
                ->checkIn(strtotime($this->normalizeDate(
                    $this->http->FindSingleNode("./following-sibling::tr[normalize-space(.)!=''][position()<25]/descendant::text()[{$this->starts($this->t('Check in'))}][1]/ancestor::tr[1]/following-sibling::tr[normalize-space(.)!=''][1]/td[normalize-space(.)!=''][1]", $root))))
                ->checkOut(strtotime($this->normalizeDate(
                    $this->http->FindSingleNode("./following-sibling::tr[normalize-space(.)!=''][position()<25]/descendant::text()[{$this->starts($this->t('Check in'))}][1]/ancestor::tr[1]/following-sibling::tr[normalize-space(.)!=''][1]/td[normalize-space(.)!=''][2]", $root))))
            ;

            // Hotel
            $name = $this->http->FindSingleNode("./following-sibling::tr[normalize-space(.)!=''][position()<25]/descendant::text()[{$this->starts($this->t('Confirmation'))}][1]/ancestor::tr[1]/td[.//span][1]", $root);
            $node = implode("\n", $this->http->FindNodes("./following-sibling::tr[normalize-space(.)!=''][position()<25]/descendant::text()[{$this->starts($this->t('Confirmation'))}][1]/ancestor::tr[1]/preceding-sibling::tr[1]/following-sibling::tr[normalize-space(.)!=''][position()<10]//text()", $root));

            if (preg_match("#{$this->opt($this->t(''))}Confirmation.+?\s+([^\n]*{$name}?[^\n]*)?\s+(.+?)\s+Check in#s", $node, $m)) {
                if (isset($m[1]) && !empty($m[1])) {
                    $name = $m[1];
                }
                $h->hotel()
                    ->name($name)
                    ->address($this->prettyPrint($m[2]))
                ;
            }
            $h->hotel()
                ->phone($this->http->FindSingleNode("./following-sibling::tr[normalize-space(.)!=''][position()<25]/descendant::text()[{$this->starts($this->t('Phone'))}][1]/ancestor::td[1]/following-sibling::td[normalize-space(.)!=''][1]", $root))
                ->fax($this->http->FindSingleNode("./following-sibling::tr[normalize-space(.)!=''][position()<25]/descendant::text()[{$this->starts($this->t('Fax'))}][1]/ancestor::td[1]/following-sibling::td[normalize-space(.)!=''][1]", $root))
            ;

            // Rooms
            $h->addRoom()
                ->setType($this->http->FindSingleNode("./following-sibling::tr[normalize-space(.)!=''][position()<25]/descendant::text()[{$this->starts($this->t('Room Type'))}][1]/ancestor::td[1]/following-sibling::td[normalize-space(.)!=''][1]", $root))
                ->setRate($this->http->FindSingleNode("./following-sibling::tr[normalize-space(.)!=''][position()<25]/descendant::text()[{$this->starts($this->t('Rate'))}][1]/ancestor::td[1]/following-sibling::td[normalize-space(.)!=''][1]", $root), true, true)
            ;
        }

        //##################
        //##     CARS   ####
        //##################
        $nodes = $this->http->XPath->query("//text()[{$this->contains($this->t('Car Details'))}]/ancestor::tr[1]");

        foreach ($nodes as $root) {
            $r = $email->add()->rental();
            // General
            $r->general()
                ->confirmation($this->http->FindSingleNode("(./following-sibling::tr[normalize-space(.)!=''][position()<25]/descendant::text()[{$this->starts($this->t('Confirmation'))}])[1]/ancestor::td[1]", $root, true, "#\s+([A-Z\d\-]{5,})\s*$#"))
                ->travellers($pax)
            ;
            // Program
            $account = $this->http->FindSingleNode("(./following-sibling::tr[normalize-space(.)!=''][position()<25]/descendant::text()[{$this->starts($this->t('Membership'))}])[1]", $root, true, "#\s+([A-Z\d\-]{5,})\s*$#");

            if (!empty($account)) {
                $h->program()
                    ->account($account, false);
            }

            // Pickup
            $time = $this->http->FindSingleNode("(./following-sibling::tr[normalize-space(.)!=''][position()<25]/descendant::text()[{$this->starts($this->t('Pick up'))}])[1]/ancestor::tr[1]/following-sibling::tr[normalize-space(.)!=''][position()<3][contains(.,':')][1]/td[normalize-space(.)!=''][1]", $root);
            $date = $this->http->FindSingleNode("(./following-sibling::tr[normalize-space(.)!=''][position()<25]/descendant::text()[{$this->starts($this->t('Pick up'))}])[1]/ancestor::tr[1]/following-sibling::tr[normalize-space(.)!=''][position()<5][contains(.,',')][1]/td[normalize-space(.)!=''][1]", $root);

            if (!empty($date) && !empty($time)) {
                $r->pickup()
                    ->date(strtotime($time, strtotime($date)));
            }

            // Dropoff
            $time = $this->http->FindSingleNode("(./following-sibling::tr[normalize-space(.)!=''][position()<25]/descendant::text()[{$this->starts($this->t('Pick up'))}])[1]/ancestor::tr[1]/following-sibling::tr[normalize-space(.)!=''][position()<3][contains(.,':')][1]/td[normalize-space(.)!=''][2]", $root);
            $date = $this->http->FindSingleNode("(./following-sibling::tr[normalize-space(.)!=''][position()<25]/descendant::text()[{$this->starts($this->t('Pick up'))}])[1]/ancestor::tr[1]/following-sibling::tr[normalize-space(.)!=''][position()<5][contains(.,',')][1]/td[normalize-space(.)!=''][2]", $root);

            if (!empty($date) && !empty($time)) {
                $r->dropoff()
                    ->date(strtotime($time, strtotime($date)));
            }
            $name = $this->http->FindSingleNode("(./following-sibling::tr[normalize-space(.)!=''][position()<10]/descendant::text()[{$this->starts($this->t('Confirmation'))}])[1]/ancestor::tr[1]/td[.//span][1]", $root);
            $node = implode("\n", $this->http->FindNodes("(./following-sibling::tr[normalize-space(.)!=''][position()<10]/descendant::text()[{$this->starts($this->t('Confirmation'))}])[1]/ancestor::tr[1]/preceding-sibling::tr[1]/following-sibling::tr[normalize-space(.)!=''][position()<10]//text()", $root));

            if (!empty($name) && preg_match("#{$this->opt($this->t('Confirmation'))}\W*(?:[A-Z\d\-]{5,})\s+([^\n]*{$name}?[^\n]*)?\s*(.+?)\s+Pick up:#s", $node, $m)) {
                if (isset($m[1]) && !empty($m[1])) {
                    $name = $m[1];
                }
                $r->extra()
                    ->company($name);
                $r->pickup()
                    ->location($this->prettyPrint($m[2]));
                $r->dropoff()
                    ->location($this->prettyPrint($m[2]));
            }

            $r->car()
                ->type(implode("; ", [
                    'Category: ' . $this->http->FindSingleNode("(./following-sibling::tr[normalize-space(.)!=''][position()<10]/descendant::td[{$this->eq($this->t('Category'))}])[1]/following-sibling::td[normalize-space(.)][1]", $root),
                    'Size: ' . $this->http->FindSingleNode("(./following-sibling::tr[normalize-space(.)!=''][position()<10]/descendant::td[{$this->eq($this->t('Size'))}])[1]/following-sibling::td[normalize-space(.)][1]", $root),
                    'Transmission: ' . $this->http->FindSingleNode("(./following-sibling::tr[normalize-space(.)!=''][position()<10]/descendant::td[{$this->eq($this->t('Transmission'))}])[1]/following-sibling::td[normalize-space(.)][1]", $root),
                ]));
        }

        return $email;
    }

    private function normalizeDate($date)
    {
        $in = [
            '#^\s*\w+,\s+(\d+\s+\w+\s+\d+)\s*$#',
        ];
        $out = [
            '$1',
        ];
        $str = $this->dateStringToEnglish(preg_replace($in, $out, $date));

        return $str;
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

    private function re($re, $str, $c = 1): ?string
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
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

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return "(?:" . preg_quote($s) . ")";
        }, $field)) . ')';
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

    private function prettyPrint(?string $string): string
    {
        return preg_replace(["/\s*\n\s*/", '/\s+/'], [", ", ' '], trim($string, ", \n"));
    }

    private function parseDeadlineRelative(Hotel $h, $prior, $hour = null): bool
    {
        $checkInDate = $h->getCheckInDate();

        if (empty($checkInDate)) {
            return false;
        }

        if (empty($hour)) {
            $deadline = strtotime('-' . $prior, $checkInDate);
            $h->booked()->deadline($deadline);

            return true;
        }

        $base = strtotime('-' . $prior, $checkInDate);

        if (empty($base)) {
            return false;
        }
        $deadline = strtotime($hour, strtotime(date('Y-m-d', $base)));

        if (empty($deadline)) {
            return false;
        }
        $priorUnix = strtotime($prior);

        if (empty($priorUnix)) {
            return false;
        }
        $priorSeconds = $priorUnix - strtotime('now');

        while ($checkInDate - $deadline < $priorSeconds) {
            $deadline = strtotime('-1 day', $deadline);
        }
        $h->booked()->deadline($deadline);

        return true;
    }
}
