<?php

namespace AwardWallet\Engine\zoetic\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Common\Event;
use AwardWallet\Schema\Parser\Common\Hotel;
use AwardWallet\Schema\Parser\Email\Email;

// parser similar tzell/ItineraryRows
class ItineraryPlain extends \TAccountChecker
{
    public $mailFiles = "zoetic/it-102579338.eml, zoetic/it-16203797.eml, zoetic/it-16211225.eml, zoetic/it-16222326.eml, zoetic/it-16310989.eml, zoetic/it-19523289.eml, zoetic/it-19941257.eml, zoetic/it-26551090.eml, zoetic/it-26751671.eml, zoetic/it-344838024.eml, zoetic/it-40394364.eml, zoetic/it-41047556.eml, zoetic/it-41234053.eml, zoetic/it-41803488.eml, zoetic/it-42954451.eml, zoetic/it-44634430.eml, zoetic/it-46300785.eml, zoetic/it-46350451.eml, zoetic/it-47248046.eml, zoetic/it-47884795.eml, zoetic/it-49279173.eml, zoetic/it-49508736.eml, zoetic/it-50489630.eml, zoetic/it-53544359.eml";

    public $reFrom = ["zoetictravel.com"];
    public $reBody = [
        'en'   => ['SALES PERSON', 'CUSTOMER NBR'],
        'en1'  => ['FOR:', 'HOTEL'], //not good detect but it could be so (no 16211225)
        'en2'  => ['FOR:', 'AIR'],
        'en3'  => ['AIR', 'FLT:'], //not good detect but it could be so (no 16310989)
        'en4'  => ['HOTEL', 'NT/S'],
        'en5'  => [' AR:', ' LV:'],
        'en6'  => [' RATE', ' NT/S - OUT'],
        'en7'  => ['EQP:', 'through Protravel'],
        'en8'  => [' FONE ', 'RATE-'], // email 344838024
        'en9'  => ['SALES  PERSON:', 'FLT:'],
    ];
    public $lang = '';
    public static $dict = [
        'en' => [
            'PHONE' => ['FONE', 'PHONE'],
        ],
    ];
    private $subjects = [
        'en' => ['Ticketed Itinerary for', 'ETKTs:'],
    ];

    private $pax = [];
    private $airTickets = [];
    private $lastRecLoc;

    private $flightArray = [];

    private $patterns = [
        'time'  => '\d{1,2}(?:[:：]\d{2})?(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?)?', // 4:19PM  |  2:00 p. m.  |  3pm
        'phone' => '[+(\d][-+. \d)(]{5,}[\d)]', // +377 (93) 15 48 52  |  (+351) 21 342 09 07  |  713.680.2992
    ];

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $text = $this->getText($parser);

        if (!$this->assignLang($text)) {
            $this->logger->debug("Can't determine a language!");
        }

        if (!$this->parseEmail($text, $email)) {
            return $email;
        }

        $a = explode('\\', __CLASS__);
        $class = end($a);
        $email->setType($class . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->assignLang($parser->getPlainBody()) || $this->assignLang($parser->getHTMLBody())) {
            $body = $this->getText($parser);

            return preg_match('/^[ ]*\d+\s*\w+\s*\d+[ ]+(?:-[ ]+)?(?:MONDAY|TUESDAY|WEDNESDAY|THURSDAY|FRIDAY|SATURDAY|SUNDAY)\b/m', $body) > 0;
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->detectEmailFromProvider($headers['from']) !== true) {
            return false;
        }

        foreach ($this->subjects as $phrases) {
            foreach ((array) $phrases as $phrase) {
                if (is_string($phrase) && stripos($headers['subject'], $phrase) !== false) {
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
        $types = 2; //air | hotel;

        return $types * count(self::$dict);
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

    private function splitter($regular, $text): array
    {
        $result = [];

        $array = preg_split($regular, $text, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
        array_shift($array);

        for ($i = 0; $i < count($array) - 1; $i += 2) {
            $result[] = $array[$i] . $array[$i + 1];
        }

        return $result;
    }

    private function parseEmail($textPDF, Email $email): bool
    {
        $textPDF = preg_replace("#^[\t\r ]+CONTINUED ON PAGE.+?[\t\r ]*\n[\t\r ]*\n[\t\r ]*\n#ms", '', $textPDF);

        if (strpos($textPDF, 'CUSTOMER NBR') !== false) {
            $email->ota()->confirmation($this->re("#CUSTOMER NBR:[ ]+\w+(?:[ ]+DUPLICATE)?[ ]+([A-Z\d]{5,})[ ]+PAGE#", $textPDF));
        }

        // travellers
        if (preg_match_all("/^[\r\t ]*FOR: *([\s\w\/]+?)\n[\r\t ]*\n/m", $textPDF, $m, PREG_SET_ORDER)) {
            foreach ($m as $v) {
                $pax = array_filter(array_map("trim", preg_split('/\s*\n\s*/', $v[1])));
                $pax = array_map(function ($item) {
                    return preg_replace('/\s+/', ' ', $item);
                }, $pax);
                $this->pax = array_unique(array_merge($this->pax, $pax));
            }
        }

        // 30 SEP 19 - MONDAY
        $patterns['dateRow'] = '\d{1,2}\s*[[:alpha:]]{3,}\s*\d{2,4}[ ]+(?:-[ ]+)?(?:MONDAY|TUESDAY|WEDNESDAY|THURSDAY|FRIDAY|SATURDAY|SUNDAY)\b';

        $itineraryParts = [];
        $sections = $this->splitter('/^([ ]*' . $patterns['dateRow'] . ')/mu', "ControlStr\n" . $textPDF);

        foreach ($sections as $sText) {
            // get date row
            $dateStr = $this->re("#(.+)#", $sText);

            // remove room headers
            $sText = preg_replace('/^[> ]*Room \d{1,3}[:]*\n/im', '', $sText);

            // insert date row
            $re = "/(.+)\s+(^[> ]*(?:AIR[ ]{2}|HOTEL.+OUT-|.*(?:HOTELS?|RESORTS?).+[ ]*-[ ]*OUT\b|HOTEL.+\n+.+OUT-|.+ \d+ NT\/S \- OUT\b|CAR |.+\n[ ]*PICK[- ]*UP[ ]*-|OTHER |TOUR ).*)\n*/m";
            // it-26551090.eml, it-19941257.eml, it-19523289.eml, it-16222326.eml, it-16211225.eml, it-16203797.eml
            $sText = preg_replace($re, '$1' . "\n{$dateStr}\n" . "$2\n", $sText);
            // it-49279173.eml (hotel-1)
            $sText = preg_replace("/(^\n|\n{2})([> ]*.+[[:upper:]].+[ ]{2}[A-Z\d] ROOM[ \/].+)/", '$1' . "\n{$dateStr}\n" . '$2', $sText);
            // it-50489630.eml
            $sText = preg_replace("/(^.*$)[>\s]+((?:^.*\b(?:RECORD LOCATOR|CONFIRMATION)\b.+$[>\s]+)?^.+\d.*$[>\s]+^[> ]*(?:DEPARTING|DEPART) .+[[:alpha:]].+\d.+$[>\s]+^[> ]*(?:ARRIVING|ARRIVE) .+[[:alpha:]].+\d.+$)/m", '$1' . "\n{$dateStr}\n" . '$2', $sText);

            // remove double date
            $sText = preg_replace('/(' . preg_quote($dateStr, '/') . ')\n+' . preg_quote($dateStr, '/') . '/', '$1', $sText);

            // remove travellers
            foreach ($this->pax as $p) {
                $sText = str_replace($p, '', $sText);
            }

            $itineraryParts = array_merge($itineraryParts, $this->splitter('/^([ ]*' . $patterns['dateRow'] . ')/mu', "ControlStr\n" . $sText));
        }

        $itineraryPartsImproved = [];

        for ($i = 0, $l = count($itineraryParts); $i < $l; $i++) {
            if (
                !empty($itineraryParts[$i + 1])
                && preg_match('/(.+?\s+OTHER\s+.+?)\s+(SEATS?[^\n]+)\s*(.*)/s', $itineraryParts[$i], $matches)
            ) {
                // it-26551090.eml
                $itineraryParts[$i] = $matches[1] . "\n" . $matches[3];
                $itineraryParts[$i + 1] = $itineraryParts[$i + 1] . "\n" . $matches[2];
            }

            if (
                !empty($itineraryParts[$i + 1])
                && preg_match("#^[ ]*AIR[ ]+.+?[ ]+FLT:#m", $itineraryParts[$i])
                && !preg_match("#.+\n[ ]*AR #", $itineraryParts[$i])
                && preg_match("#.+\n[ ]*AR #", $itineraryParts[$i + 1])
            ) {
                $itineraryPartsImproved[] = $itineraryParts[$i] . $itineraryParts[$i + 1];
                $i++;
            } else {
                $itineraryPartsImproved[] = $itineraryParts[$i];
            }
        }

        // TODO: questionably =(
        // it-46300785.eml
        if (count($itineraryPartsImproved) > 0) {
            $lastPart = $itineraryPartsImproved[count($itineraryPartsImproved) - 1];

            if (preg_match("/(?:^[ ]*|[ ]{2}){$this->opt($this->t('NET CC BILLING'))}[ ]+(?<amount>\d[,.\'\d]*?)[ *]*$/m", $lastPart, $m)) {
                // 1,536.60
                $email->price()->total($this->getTotalCurrency($m['amount'])['Total']);
            }

            if (preg_match_all("/^[ ]*{$this->opt($this->t('AIR TICKET'))}[ ]+((?:[A-Z][A-Z\d]|[A-Z\d][A-Z])?\d{3}[- ]*\d{5,}[- ]*\d{1,2})(?:[ ]{2}|$)/m", $lastPart, $m)) {
                // LX7463348368
                $this->airTickets = $m[1];
            }
        }

        //look at it-19523289.eml - for what lastRecLoc
        $this->lastRecLoc = null;

        foreach ($itineraryPartsImproved as $key => $root) {
            if (preg_match('/^\s*' . $patterns['dateRow'] . '\s+HOTEL\s+.+\s+OUT-\d{1,2}\s*\w+$/u', $root)) {
                $this->logger->debug("partition-{$key}: 1");

                continue;
            }

            if (empty($text = strstr($root, 'CHECKED BAGGAGE FEES MAY APPLY AT AIRPORT', true))) {
                $text = $root;
            }

            if (preg_match("/^ *CAR /m", $text)
                || preg_match("/^(?:.+\n){2}[ ]*PICK[- ]*UP[ ]*-/", $text)
            ) {
                $this->logger->debug("partition-{$key}: Car");

                if (!$this->parseCar($text, $email)) { // it-26551090.eml
                    return false;
                }
                $this->lastRecLoc = null;

                continue;
            }

            if (preg_match("#^ *HOTEL #m", $text)
                || preg_match("#^\s*.*HOTEL(.*\n){1,3}.* \d+ ROOM#m", $text)
                || preg_match("#\bNT\/S[ ]*\-[ ]*OUT#", $text) // it-44634430.eml
                || preg_match("#^.+\n[> ]*.+[[:upper:]].+[ ]{2}[A-Z\d] ROOM[ \/].+#", $text) // it-49279173.eml
            ) {
                $this->logger->debug("partition-{$key}: Hotel");

                if (!$this->parseHotel($text, $email)) {
                    return false;
                }
                $this->lastRecLoc = null;

                continue;
            }

            if ((preg_match("#^ *AIR[ ]{1}#m", $text)
                    && !preg_match('/\n[ ]*(?:AIR TICKET|AIR EXTRAS|AIR TRANSPORTATION|AIRLINE LOCATOR)[ ]*/', $text)
                )
                || preg_match("#^ *AIR[ ]{1,}.+?[ ]FLT:[\t\r ]*\d+#m", $text)
            ) {
                $this->logger->debug("partition-{$key}: Flight");

                if (!$this->parseAir($text, $email)) {
                    return false;
                }

                continue;
            }

            if (preg_match("#^ *RAIL #m", $text)) {
                $this->logger->debug("partition-{$key}: Rail");

                if (!$this->parseRail($text, $email)) {
                    return false;
                }

                continue;
            }

            if (preg_match("#^ *SHIP(?:[ ]+|\n)#m", $text)
                && count(explode("\n", trim($text))) > 3
            ) {
                $this->logger->debug("partition-{$key}: Cruise");
                //TOUR skip - not enough data
                // before flight with preg_match("#^ *LV(?:\:|\s)#m", $text) && preg_match('#^[ ]*AR(?:\:|\s)#m', $text)
                /*    SHIP  ROYAL CARIBBEAN                 CONFIRMATION 3364746
                      LV ROME FIUMICINO
                      AR ATHENS
                      SS VOYAGER OF THE SEA            CABIN 7694
                      EARLY DINING CONFIRMED
                */
                $this->lastRecLoc = null;

                continue;
            }

            if (preg_match("#^ *LV(?:\:|\s)#m", $text) && preg_match('#^[ ]*AR(?:\:|\s)#m', $text)) {
                $this->logger->debug("partition-{$key}: Flight2");

                if (!$this->parseAir2($text, $email)) {
                    return false;
                }

                continue;
            }

            if (preg_match("/^[> ]*(?:DEPARTING|DEPART) .+[[:alpha:]].+\d.+$[>\s]+^[> ]*(?:ARRIVING|ARRIVE) .+[[:alpha:]].+\d.+$/m", $text)) {
                // it-50489630.eml
                $this->logger->debug("partition-{$key}: Flight3");

                if (!$this->parseAir3($text, $email, $textPDF)) {
                    return false;
                }

                continue;
            }

            if (preg_match("/^[> ]*APPOINTMENTS BEGIN .+ THROUGH .+/m", $text)) {
                // it-102579338.eml
                $this->logger->debug("partition-{$key}: Event");

                if (!$this->parseEvent($text, $email)) {
                    return false;
                }

                continue;
            }

            if (
                preg_match("#^ *OTHER #m", $text) && count(explode("\n", trim($text))) === 3
                || strpos($text, 'TRANSFER') !== false
                || preg_match('/^[ ]*THANK YOU FOR USING [\w ]+$/m', $text)
            ) {
                $this->logger->debug("partition-{$key}: Other/Transfer");
                //TODO:
                //TRANSFER skip - not enough data or don't know how to parse
                $this->lastRecLoc = $this->re("#RECORD LOCATOR +([A-Z\d]{5,})#", $text);

                continue;
            }

            if (preg_match("#^ *TOUR(?:[ ]+|\n)#m", $text)
                && (count(explode("\n", trim($text))) === 3
                    || strpos($text, 'RENTAL CAR') !== false // it-44634430.eml
                    || strpos($text, 'ENTRANCE TICKET') !== false
                    || strpos($text, 'RENTAL CAR') !== false
                )
            ) {
                $this->logger->debug("partition-{$key}: Tour");
                //TOUR skip - not enough data
                $this->lastRecLoc = null;

                continue;
            }

            if (preg_match("/^ *[*]{2}/m", $text) && count(explode("\n", trim($text))) === 3) {
                $this->logger->debug("partition-{$key}: **");
                //SOME INFO - not enough data
                $this->lastRecLoc = $this->re("#RECORD LOCATOR +([A-Z\d]{5,})#", $text);

                continue;
            }

            if (preg_match('/\n[ ]*(?:AIR TICKET|AIR EXTRAS)[ ]*/', $text)) {
                $this->logger->debug("partition-{$key}: Other/Air ticket/Air extras");
                //AIR TIKET INFO - not enough data
                $this->lastRecLoc = $this->re("#RECORD LOCATOR +([A-Z\d]{5,})#", $text);

                continue;
            }

            $this->logger->debug("partition-{$key}: supported format not found, parsing aborted!");

            //return false;
        }

        return true;
    }

    private function parseCar($textPDF, Email $email): bool
    {
        $this->logger->debug(__FUNCTION__ . '()');

        $patterns = [
            'pickUpDateTime'  => '\bPICK[- ]+UP[ ]*-[ ]*(?<pickupTime>\d{1,4}[APM]*)\b', // PICK UP-1206P
            'dropOffDateTime' => '\bRETURN *- *(?<dropoffDate>\w{4,}) *\/ *(?<dropoffTime>\d+[APM]*)\b', // RETURN-28OCT/0700
        ];

        $r = $email->add()->rental();
        $date = $this->normalizeDate($this->re("#(.+)#", $textPDF));

        $r->general()
            ->confirmation($this->re("/(?:CONFIRMATION NUMBER|CONFIRMATION)[-\s]+(?-i)([A-Z\d][-A-Z\d\/]{4,})/i", $textPDF));

        if (($status = $this->re("/^.+\n+.+?[ ]{2}(CONFIRMED)\n/", $textPDF))) {
            $r->general()->status($status);
        }

        // pickUpDateTime
        if (preg_match("/{$patterns['pickUpDateTime']}/", $textPDF, $m) && $date) {
            // it-26551090.eml
            $r->pickup()->date($this->normalizeDate($m['pickupTime'], $date));
        } elseif (preg_match("/^[ ]*PICKUP-([A-Z]{3,}|.*(?:STREET|WAY).*)[ ]*$/m", $textPDF, $m) && $date) {
            // it-44634430.eml
            $r->pickup()
                ->date($date)
                ->location(preg_replace('/\s+/', ' ', $m[1]));

            if (preg_match("/^[ ]*PHONE[-: ]({$this->patterns['phone']})(?:[ ]{2}|$)/m", $textPDF, $matches)) {
                $r->pickup()->phone($matches[1]);
            }
        }

        // dropOffDateTime
        if (!empty($r->getPickUpDateTime()) && preg_match("/{$patterns['dropOffDateTime']}/", $textPDF, $matches)) {
            // it-26551090.eml
            $dateDropoff = $this->normalizeDate($matches['dropoffDate'], $r->getPickUpDateTime());

            if ($dateDropoff) {
                $r->dropoff()->date($this->normalizeDate($matches['dropoffTime'], $dateDropoff));
            }
        } elseif (preg_match("/(?:^[ ]*|[ ]{2})DROP-(\d{1,2}[A-Z]{3})(?: |$)/m", $textPDF, $m)) {
            // it-44634430.eml
            $r->dropoff()->date($this->normalizeDate($m[1], $date));
        }

//        $marginRight = 3; // it-26551090.eml

        // pickUpLocation
        if (preg_match("/{$patterns['pickUpDateTime']}[^\n]*\n+(?<location>.+?)\s+{$patterns['dropOffDateTime']}/s", $textPDF, $matches)) {
            if (count(preg_split('/\n+/', $matches['location'])) > 4) {
                $this->logger->debug('wrong pickUpLocation');

                return false;
            }
//            $tablePos = [0];
//            if ( preg_match('/^\s*?([ ]*.+?(?:[ ]{2,}|\n))/', $matches['location'], $m) ) {
//                $tablePos[] = mb_strlen($m[1]) + $marginRight;
//            }
//            $table = $this->splitCols($matches['location'], $tablePos);
//            if (count($table) !== 2) {
//                $this->logger->debug('wrong pickUpLocation');
//                return false;
//            }
//            $r->pickup()->location( preg_replace('/\s+/', ' ', $table[0]) );
            $r->pickup()->location(preg_replace('/\s+/', ' ', preg_replace('/([ ]*\S.+\S)[ ]{10,}\S.+\S\s*$/', '$1', $matches['location'])));
        }

        // dropOffLocation
        if (preg_match("/{$patterns['dropOffDateTime']}[^\n]*\n+(?<location>.+?)\s+RATE +PLAN\b/s", $textPDF, $matches)) {
            if (count(preg_split('/\n+/', $matches['location'])) > 4) {
                $this->logger->debug('wrong pickUpLocation');

                return false;
            }
//            $tablePos = [0];
//            if ( preg_match('/^\s*?([ ]*.+?(?:[ ]{2,}|\n))/', $matches['location'], $m) ) {
//                $tablePos[] = mb_strlen($m[1]) + $marginRight;
//            }
//            $table = $this->splitCols($matches['location'], $tablePos);
//            if (count($table) !== 2) {
//                $this->logger->debug('wrong dropOffLocation');
//                return false;
//            }
//            $r->dropoff()->location( preg_replace('/\s+/', ' ', $table[0]) );
            $r->dropoff()->location(preg_replace('/\s+/', ' ', preg_replace('/([ ]*\S.+\S)[ ]{10,}\S.+\S\s*$/', '$1', $matches['location'])));
        }

        if (empty($r->getDropOffLocation()) && preg_match("/(?:^[ ]*|[ ]{2})PICKUP-/m", $textPDF)
            && preg_match("/(?:^[ ]*|[ ]{2})DROP-/m", $textPDF)
        ) {
            // it-44634430.eml
            $r->dropoff()->noLocation();
        }

        return true;
    }

    private function parseHotel($textPDF, Email $email): bool
    {
        $this->logger->debug(__FUNCTION__ . '()');

        $h = $email->add()->hotel();
        $date = $this->normalizeDate($this->re("#(.+)#", $textPDF));

        $h->general()
            ->confirmation($this->re("/\b(?:CONFIRMATION|CONF)[- ]+(?-i)([^\-\s][-A-Z\d\/]{2,})/i", $textPDF));

        if (!empty($acc = $this->re("#\d+ NIGHTS ID\-([A-Z\d]{5,})#", $textPDF))) {
            $h->program()
                ->account($acc, false);
        }

        if (!empty($this->pax)) {
            $h->general()
                ->travellers($this->pax);
        } else {
            $guest = $this->re("/(?:\n|[ ]{2,}){$this->opt($this->t('NAME'))}[- ]+([A-Z][A-Z ]{3,}[A-Z])(?:[ ]{2}|[ ]*\(|\n)/", $textPDF);

            if (!empty($guest)) {
                $h->general()
                    ->traveller($guest);
            }
        }

        if ($status = $this->re("/^.+\n+.+?[ ]{2}(CONFIRMED)\n/", $textPDF)) {
            $h->general()->status($status);
        }

        if (preg_match_all('/^[> ]*(?:CANCEL POLICY:|CXL:)[ ]*(.{2,}?)[ ]*$/m', $textPDF, $cancellationMatches)) {
            $cancellation = implode('; ', $cancellationMatches[1]);
        }

        if (!isset($cancellation) || empty($cancellation)) {
            $cancellation = $this->re("/(?:^|{$this->patterns['phone']}) *((?:(?:MUST )?CANCEL +|CXL:).+)/m", $textPDF);
        }
        $h->general()->cancellation($cancellation, false, true);

        if (preg_match("#^[ ]*HOTEL[^\n]+\n.*OUT\-#m", $textPDF)) {
            // it-19941257.eml
            $tableText = $this->re("#(.+\bOUT.+\n[\s\S]+?)CONFIRMATION#", $textPDF);
            $table = $this->splitCols($tableText, $this->colsPos($this->re("#(.+)OUT\-#", $tableText)));

            if (count($table) !== 2) {
                $this->logger->debug('wrong format: hotel 1');

                return false;
            }
            $h->hotel()
                ->name($this->re("/^[ ]*HOTEL +(.+?)(?: {2,}.+|$)\s^.*OUT-/m", $textPDF));
            $this->parseHotel_1($table, $h, $email);
        } else {
            // it-16211225.eml
            $tableText = $this->re("/ .+OUT.+\n+([\s\S]+?)(?:CONFIRMATION|CONF[ ]*-)/", $textPDF)
                ?? $this->re("/^(.+[[:upper:]].+[ ]{2}[A-Z\d] ROOM[ \/].+\n[\s\S]+?)(?:CONFIRMATION|CONF[ ]*-)/m", $textPDF) // it-49279173.eml
            ;
            $tablePos = [0];
//            $this->logger->debug('$tableText = '.print_r( $tableText,true));
            if (preg_match('/^([> ]*.+?[ ]{3,})[^ ]/m', $tableText, $matches)
                    && preg_match('/^.*\n([> ]*.+?[ ]{3,})[^ ]/m', $tableText, $matches2)) {
                $tablePos[] = min(mb_strlen($matches[1]), mb_strlen($matches2[1]));
            } elseif (preg_match('/^([> ]*.+?[ ]{2,})[^ ]/m', $tableText, $matches)
                && preg_match('/^.*\n([> ]*.+?[ ]{2,})[^ ]/m', $tableText, $matches2)) {
                $tablePos[] = mb_strlen(min($matches[1], $matches2[1]));
            }
            $table = $this->splitCols($tableText, $tablePos);

            if (count($table) !== 2) {
                $this->logger->debug('wrong format: hotel 2');

                return false;
            }
            $this->parseHotel_2($table, $h);
        }

        $tot = $this->getTotalCurrency($this->re("#([^\n]+?) +APPROXIMATE TOTAL PRICE#", $textPDF));

        if ($tot['Total'] !== '') {
            $h->price()
                ->total($tot['Total'])
                ->currency($tot['Currency']);
        }

        $dateOut = $this->normalizeDate($this->re("#OUT[\- ](.+?)(?:[ ]{2,}|CORP\s+ID|\n)#", $textPDF), $date);

        if (empty($dateOut) && !empty($h->getHotelName())) {
            // it-49279173.eml
            $prevItineraries = array_reverse($email->getItineraries());
            array_shift($prevItineraries);

            /** @var \AwardWallet\Schema\Parser\Common\Hotel $itinerary */
            foreach ($prevItineraries as $itinerary) {
                if ($itinerary->getType() === 'hotel' && $itinerary->getHotelName() === $h->getHotelName()) {
                    $dateOut = $itinerary->getCheckOutDate();
                }
            }
        }
        $h->booked()
            ->checkIn($date)
            ->checkOut($dateOut);

        if (!empty($h->getCancellation())) {
            if (preg_match('/^(?:MUST )?CANCEL +(?<prior>\d{1,3} HOURS?) PRIOR TO ARRIVAL$/i', $h->getCancellation(), $m)
                || preg_match('/^(?:MUST )?CANCEL +(?<prior>\d{1,3} DAYS?) PRIOR TO ARRIVAL$/i', $h->getCancellation(), $m)
                || preg_match('/^CANCEL +UP TO (?<prior>\d{1,3} HOURS?) PRIOR LOCAL HOTEL TIME$/i', $h->getCancellation(), $m)
            ) {
                $h->booked()->deadlineRelative($m['prior'], '00:00');
            } elseif (preg_match('/^CANCEL POLICY (?<hPrior>\d+) HR PRIOR LOCAL/i', $h->getCancellation(), $m)
                || preg_match('/^MUST CANCEL (?<hPrior>\d+) HOURS PRIOR TO ARRIVAL TO AVOID BILLING/i', $h->getCancellation(), $m)
            ) {
                $h->booked()->deadlineRelative($m['hPrior'] . ' hours', '00:00');
            } elseif (preg_match('/^CANCEL BY (.{4,}?) HOTEL TIME TO AVOID CHARGE$/i', $h->getCancellation(), $m)
                || preg_match('/^(?:CXL:\s*)?TO AVOID BEING BILLED CANCEL BY (.+)$/i', $h->getCancellation(), $m)) {
                $h->booked()->deadline($this->normalizeDate($m[1], $h->getCheckInDate(), false));
            } elseif (
                preg_match('/^CANCEL POLICY (?<time>.+?) LOCAL HOTEL TIME (?<date>.+?) TO AVOID/i', $h->getCancellation(), $m)
                || preg_match("/^WITHOUT PENALTY UP TO (?<time>{$this->patterns['time']}) (?<date>.+?) HOTEL TIME(?:[;]|$)/i", $h->getCancellation(), $m)
                || preg_match("/^(?<time>{$this->patterns['time']}) (?<date>.+?) WITHOUT CHARGE(?:[;]|$)/i", $h->getCancellation(), $m)
                || preg_match("/^(?<time>{$this->patterns['time']}|NOON) HOTEL TIME (?<date>.+?)\s*- OR 50\s*PERCENT PENALTY(?:[;]|$)/i", $h->getCancellation(), $m)
            ) {
                $h->booked()->deadline(strtotime($m['time'], $this->normalizeDate($m['date'], $h->getCheckInDate(), false)));
            } elseif (preg_match('/^(?:CANCEL POLICY: )?(?<time>.+?) LOCAL HOTEL TIME DAY PRIOR TO ARRIVAL/i', $h->getCancellation(), $m)) {
                $h->booked()->deadlineRelative('1 day', $m['time']);
            }
        }

        // phone
        if (empty($h->getPhone())) {
            $phone = $this->re("/{$this->opt($this->t('PHONE'))}[-: ]+({$this->patterns['phone']})/", $textPDF);
            $h->hotel()->phone($phone, false, true);
        }

        // fax
        if (empty($h->getFax())) {
            $fax = $this->re("/{$this->opt($this->t('FAX'))}[-: ]+({$this->patterns['phone']})/", $textPDF);
            $h->hotel()->fax($fax, false, true);
        }

        return true;
    }

    private function parseHotel_1(array $table, Hotel $h, Email $email): void
    {
        $this->logger->debug(__FUNCTION__ . '()');

//        $this->logger->debug('$table = '.print_r( $table,true));
        $unionColumn = false;
        // address
        // phone
        // fax
        $pattern1 = '/'
            . '(?<address>.+?)'
            . "\s*{$this->opt($this->t('PHONE'))} +(?<phone>{$this->patterns['phone']})"
            . "\s*{$this->opt($this->t('FAX'))} +(?<fax>{$this->patterns['phone']})"
            . '/s';
        $pattern2 = '/'
            . '(?<address>.+?)'
            . "\s*{$this->opt($this->t('PHONE'))} +(?<phone>{$this->patterns['phone']})"
            . '/s';
        $pattern3 = '/'
            . '(?<address>.+?)'
            . "\s*{$this->opt($this->t('FAX'))} +(?<fax>{$this->patterns['phone']})"
            . '/s';

        if (preg_match($pattern1, $table[0], $matches)) {
            $h->hotel()
                ->address($this->nice($matches['address']))
                ->phone($matches['phone'])
                ->fax($matches['fax']);
        } elseif (preg_match($pattern2, $table[0], $matches)) {
            $h->hotel()
                ->address($this->nice($matches['address']))
                ->phone($matches['phone']);
        } elseif (preg_match($pattern3, $table[0], $matches)) {
            $h->hotel()
                ->address($this->nice($matches['address']))
                ->fax($matches['fax']);
        } elseif (preg_match("/^(?<address>.*\n)? *\d+ ROOM.*\n(?:RATE-|GUARANTEED )/", $table[0], $matches)) {
            $address = trim($matches['address']);
            $unionColumn = true;

            if ($address == 'DBL') {
                if (!empty($h->getHotelName())) {
                    foreach ($email->getItineraries() as $it) {
                        /** @var \AwardWallet\Schema\Parser\Common\Hotel $it */
                        if ($it->getId() !== $h->getId() && $it->getType() === 'hotel' && $it->getHotelName() === $h->getHotelName()) {
                            if (!empty($it->getAddress())) {
                                $h->hotel()->address($it->getAddress());
                            }

                            if (!empty($it->getPhone())) {
                                $h->hotel()->phone($it->getPhone());
                            }

                            if (!empty($it->getNoAddress())) {
                                $h->hotel()->noAddress();
                            }
                        }
                    }
                } else {
                    $h->hotel()
                        ->address($this->nice($address));
                }
            } elseif (!empty($address)) {
                $h->hotel()
                    ->address($this->nice($address));
            } else {
                $h->hotel()
                    ->noAddress();
            }
        } else {
            $h->hotel()->address($this->nice($table[0]));
        }

        // phone
        if (empty($h->getPhone())) {
            $phone = $this->re("/{$this->opt($this->t('PHONE'))} +({$this->patterns['phone']})/", $table[1]);
            $h->hotel()->phone($phone, false, true);
        }

        // fax
        if (empty($h->getFax())) {
            $fax = $this->re("/{$this->opt($this->t('FAX'))} +({$this->patterns['phone']})/", $table[1]);
            $h->hotel()->fax($fax, false, true);
        }

        if ($unionColumn) {
            $table[1] .= "\n" . $table[0];
        }

        $h->booked()
            ->rooms($this->re("#^ *(\d+) ROOMS?(?: +|\n)#m", $table[1]));
        $type = $this->re("#^ *\d+ ROOM +(.+)#m", $table[1]);
        $desc = $this->nice($this->re("#^ *\d+ ROOM +[^\n]+\n(.+?)^ *(?:RATE|CANCEL)#sm", $table[1]));
        $rate = $this->re("#^ *RATE[\- ]+(.+?)(?:{$this->opt($this->t('PHONE'))}|$)#m", $table[1]);

        if (!empty($type) || !empty($desc) || !empty($rate)) {
            $r = $h->addRoom();
            $r->setType($type, false, true);
            $r->setDescription($desc, true, true);
            $r->setRate($rate, true, true);
        }
    }

    private function parseHotel_2(array $table, Hotel $h): void
    {
        $this->logger->debug(__FUNCTION__ . '()');

//        $this->logger->debug('$table = '.print_r( $table,true));

        $h->hotel()
            ->name($this->nice($this->re("/(.+)/", $table[0])))
            ->address($this->nice($this->re("/.+\n([\s\S]+?)\b(?:{$this->opt($this->t('PHONE'))}|{$this->opt($this->t('FAX'))}|\n[ ]*\n|$)/", $table[0])))
            ->phone($this->re("/{$this->opt($this->t('PHONE'))}[: ]+({$this->patterns['phone']})/", $table[0]), false, true)
            ->fax($this->re("/{$this->opt($this->t('FAX'))}[: ]+({$this->patterns['phone']})/", $table[0]), false, true);

        $h->booked()->rooms($this->re("#^ *(\d+) ROOM\/?S?(?: +|\n)#m", $table[1]), false, true);

        $type = $this->re("#^ *\d+ ROOM(?:\/S)?[ \/]+(.+?)\s*(GUARANTEE|$)#m", $table[1]);
        $roomDescription = trim($this->re("#^ *\d+ ROOM +[^\n]+\n(.+?)^ *(?:RATE|CANCEL)#sm", $table[1]));
        $rate = $this->re("#^ *RATE[\- ]+(.+)#m", $table[1]);

        if (!empty($type) || !empty($roomDescription) || !empty($rate)) {
            $r = $h->addRoom();
            $r->setType($type, false, true);
            $r->setDescription($roomDescription, true, true);
            $r->setRate($rate, true, true);
        }
    }

    private function parseAir($textPDF, Email $email): bool
    {
        $this->logger->debug(__FUNCTION__ . '()');

        $tempFlightNumber = $this->re("/FLT:\s*(\d+)/", $textPDF);
        $depDate = $this->re("/^(\d{1,2}[ ]*[[:alpha:]]+[ ]*\d{2,4})\s*-/u", $textPDF);

        if (in_array($tempFlightNumber . '-' . $depDate, $this->flightArray) === false) {
            $this->flightArray[] = $tempFlightNumber . '-' . $depDate;
        } else {
            return false;
        }

        $f = $email->add()->flight();

        /*$this->logger->error("------------------------------------------------------------------");
        $this->logger->debug("Text: {$textPDF}");
        $this->logger->error("------------------------------------------------------------------");*/

        $date = $this->normalizeDate($this->re("#(.+)#", $textPDF));
        $confNo = $this->re("#REF:\s*([\w\-]{5,})#", $textPDF);

        if (empty($confNo)) {
            $confNo = $this->lastRecLoc;
        }

        if (empty($confNo)) {
            $f->general()->noConfirmation();
        } else {
            $f->general()
                ->confirmation($confNo);
        }

        if (!empty($this->pax)) {
            $f->general()
                ->travellers($this->pax);
        } elseif (preg_match_all("/^[\r\t ]*(.+)\s+SEAT(?:-| RESERVATION)/m", $textPDF, $travellerMatches)) {
            $f->general()
                ->travellers($travellerMatches[1]);
        }

        // accountNumbers
        if (preg_match_all("/\b((?:[A-Z\d][A-Z]|[A-Z][A-Z\d])-[A-Z\d]{5,})/", $textPDF, $matches)) {
            $f->program()
                ->accounts($matches[1], false);
        }

        $s = $f->addSegment();

        if (preg_match("#AIR +(.+) FLT: *[A-Z]*(\d+)(?: {3,}(.+?) {3,}(.+))?#", $textPDF, $m)) {
            if (!empty(trim($m[1]))) {
                $s->airline()
                    ->name($m[1]);
            }

            $s->airline()
                ->number($m[2]);

            if (isset($m[3], $m[4])) {
                if (!empty(trim($m[3]))) {
                    $s->extra()
                        ->cabin($m[3]);
                }

                if (!preg_match('/\d/', $m[4])) {
                    $s->extra()
                        ->meal($m[4]);
                }
            }
        }

        if (preg_match("#OPERATED BY +(.+?)(?:AS |DBA |$)#m", $textPDF, $m)) {
            $s->airline()
                ->operator(trim($m[1], " \\"));

            if (empty($s->getAirlineName())) {
                $s->setAirlineName(trim($m[1], " \\"));
            }
        }

        if (empty($s->getAirlineName())) {
            $s->airline()
                ->noName();
        }

        if (preg_match("/\bLV[ ]+(?<airport>\S.+?)(?:[ ]{3,}|[ ]*\n)[ ]*(?<time>\d{1,4}[APN])(?:[ ]{3,}EQP[ ]*[:]+[ ]*(?<aircraft>[^:\s].+))?/", $textPDF, $m)) {
            if (preg_match("/^\s*(?<code>[A-Z]{3})\s*-\s*(?<name>\S.+)$/", $m['airport'], $match)) {
                $s->departure()->code($match['code'])->name($match['name']);
            } else {
                $s->departure()
                    ->noCode()
                    ->name(preg_replace('/[ ]{2,}/', ', ', $m['airport']));
            }

            $s->departure()->date($this->normalizeDate($m['time'], $date));

            if (!empty($m['aircraft'])) {
                $s->extra()->aircraft($m['aircraft']);
            }
        }

        if (preg_match("/\bLV\s+[\s\S]+?\s*AR[ ]+(?<airport>\S.+?)(?:[ ]{3,}|\n)[ ]*(?<time>\d{1,4}[APN])(?:[ ]{3,}(?<nonStop>NON-STOP))?/", $textPDF, $m)) {
            if ($str = $this->normalizeDate($this->re("#^[ ]*(\d.+?-[ ]+.+)\n[ ]*AR#m", $textPDF))) {
                $date = $str;
            }

            if (preg_match("/^\s*(?<code>[A-Z]{3})\s*-\s*(?<name>\S.+)$/", $m['airport'], $match)) {
                $s->arrival()->code($match['code'])->name($match['name']);
            } else {
                $s->arrival()
                    ->noCode()
                    ->name(preg_replace('/[ ]{2,}/', ', ', $m['airport']));
            }

            $s->arrival()->date($this->normalizeDate($m['time'], $date));

            if (!empty($m['nonStop'])) {
                $s->extra()->stops(0);
            }
        }

        if (preg_match("# *DEPART: +(.+?)(?: {3,}(\d.+)| {3,}.*)?\n#", $textPDF, $m)) {
            $s->departure()
                ->terminal(trim(preg_replace("#\bTERM\b#", '', str_replace("TERMINAL", '', $m[1]))));

            if (!empty($m[2])) {
                $s->extra()
                    ->duration($m[2]);
            }
        } else {
            $s->extra()
                ->duration($this->re("#(?:^[ ]*| {3,})(\d{1,3}HR[ ]*\d.+|\d{1,4}MIN)#m", $textPDF));
        }

        if (preg_match("/\bLV[\s\S]+\s*ARRIVE:[ ]+(.+?)(?:[ ]{3,}(REF.+)|[ ]{3,}.*)?\n/", $textPDF, $m)) {
            $s->arrival()
                ->terminal(trim(preg_replace("#\bTERM\b#", '', str_replace("TERMINAL", '', $m[1]))));
        }

        // seats
        $seats = [];

        if (preg_match_all("/(\bSEATS? *.+)/", $textPDF, $m, PREG_SET_ORDER)) {
            foreach ($m as $v) {
                $seatsText = $v[1];

                if (preg_match("/\bSEATS?[^A-z].*?\b(\d{1,5})([A-z]) +AND +([A-z])\b/i", $seatsText, $m)) {
                    // 27A AND B
                    $seats = array_merge($seats, [$m[1] . $m[2], $m[1] . $m[3]]);
                } elseif (preg_match_all("/\bSEATS?[^A-z].*?\b(\d{1,5}[A-z])\b/", $seatsText, $m)) {
                    $seats = array_merge($seats, $m[1]);
                }
            }
        }

        if (preg_match_all("/\bSEATS?-\b(\d{1,5}[A-z])\b/", $textPDF, $m)) {
            $seats = array_merge($seats, $m[1]);
        }
        $seats = array_unique($seats);

        if (!empty($seats)) {
            $s->extra()->seats($seats);
        }

        if (empty($f->getTicketNumbers()) && !empty($this->airTickets)) {
            $f->setTicketNumbers($this->airTickets, false);
        }

        return true;
    }

    private function parseAir2($textPDF, Email $email): bool
    {
        $this->logger->debug(__FUNCTION__ . '()');

        $f = $email->add()->flight();
        //$this->logger->debug("Text:\n{$textPDF}");
        $date = $this->normalizeDate($this->re("#(.+)#", $textPDF));
        $confNo = $this->re("#CONFIRMATION NUMBER[ :]*(\w{5,})#", $textPDF);

        if (empty($confNo)) {
            $confNo = $this->re("#^[ ]*(?:AIRLINE|[A-Z][A-Z ]+) LOCATOR[ :]*(?:[A-Z\d][A-Z]|[A-Z][A-Z\d])?[ \-]+(\w{5,})\b#m", $textPDF);
        }

        if (empty($confNo)) {
            $f->general()
                ->noConfirmation();
        } else {
            $f->general()
                ->confirmation($confNo);
        }

        if (!empty($this->pax)) {
            $f->general()
                ->travellers($this->pax);
        }

        $segs = $this->splitter("#(.{2,}\s*\n(?:[ ]*DEPART TERMINAL[^\n]+)?\s*LV[ :]+)#", $textPDF);

        foreach ($segs as $text) {
            $s = $f->addSegment();

            if (preg_match("/^\s*(?<name>\S.+?)[ ]+(?<number>\d{1,5})(?<other>.*)\n\s*(?:DEPART TERMINAL[^\n]+\s*?\n)?+[ ]*LV:/", $text, $m)) {
                // BRITISH AWYS   638 BUSINESS   AIRBUS A320 JET
                // ALASKA   3430 COACH CLASS   OPERATED BY-SKYWEST AIRLINES A
                $s->airline()
                    ->name($m['name'])
                    ->number($m['number']);
                $m['other'] = trim($m['other']);

                if (preg_match("/^(?<cabin>.+?\b)[ ]{3,}(?<other2>\S.+)$/", $m['other'], $m2)) {
                    $s->extra()->cabin($m2['cabin']);

                    if (preg_match("/^OPERATED BY[- ]+(.+?)(?:[ ]+AS |[ ]+DBA |$)/i", $m2['other2'], $m3)) {
                        $s->airline()->operator($m3[1]);
                    } else {
                        $s->extra()->aircraft($m2['other2']);
                    }
                } elseif (preg_match("/^.*CLASS.*$/i", $m['other'])) {
                    // COACH CLASS
                    $s->extra()->cabin($m['other']);
                }
            }

            if (preg_match("/\n[ ]*DEPART TERMINAL[ ]*\-[ ]*(.+)/", $text, $m)) {
                $s->departure()->terminal(trim(str_ireplace('TERMINAL', '', $m[1])));
            }

            if (preg_match("/\n[ ]*ARRIVAL TERMINAL[ ]*\-[ ]*(.+)/", $text, $m)) {
                $s->arrival()->terminal(trim(str_ireplace('TERMINAL', '', $m[1])));
            }

            if (empty($s->getOperatedBy())
                && preg_match("/^OPERATED BY[- ]+(.+?)(?:[ ]+AS |[ ]+DBA |$)/m", $text, $m)
            ) {
                // to check
                $s->airline()
                    ->operator(trim($m[1], " \\"));
            }

            if (($status = $this->re("/^[> ]*LV[: ]*.+[ ]{2}(CONFIRMED)[ ]*\n/m", $text))) {
                $s->extra()->status($status);
            }

            if (preg_match("# *LV[: ]*(.+?) {3,}(\d+[APN])( {3,}NON\-?STOP)?#", $text, $m)) {
                $s->departure()
                    ->noCode()
                    ->name($m[1])
                    ->date($this->normalizeDate($m[2], $date));

                if (!empty($m[3])) {
                    $s->extra()
                        ->stops(0);
                }
            }

            if (preg_match("#LV[: ]*[\s\S]+?\s*?\n[ ]*AR[: ]*(.+?)(?: {3,}|\n+[ ]*)(\d+[APN])#", $text, $m)) {
                if ($str = $this->normalizeDate($this->re("#^[ ]*(\d.+?-[ ]+.+)\n[ ]*AR#m", $text))) {
                    $date = $str;
                }
                $s->arrival()
                    ->noCode()
                    ->name($m[1])
                    ->date($this->normalizeDate($m[2], $date));
            }

            if (preg_match("/\s+MILES[ -]+(\d+)\s+/i", $text, $m)) {
                $s->extra()->miles($m[1]);
            }

            if (empty($s->getAircraft()) && preg_match("/\s+(?:EQUIPMENT|EQUIP|EQP)[ -]+(.+?)(?:[ ]{2}|[ ]*$)/im", $text, $m)) {
                $s->extra()->aircraft($m[1]);
            }

            if (preg_match("/\s+(?:JOURNEY TIME|ELAPSED(?: FLYING)? TIME)[ -]+(\d*:\d+)\s+/i", $text, $m)) {
                $s->extra()->duration($m[1]);
            }

            if (preg_match("/^(?:^[ ]*|[ ]{2})(MEAL|SNACK|LUNCH|FOOD TO PURCHASE)(?:[ ]{2}|[ ]*$)/im", $text, $m)) {
                $s->extra()->meal($m[1]);
            }

            // seats
            $seatsText = preg_match("/(?:^|\s)SEATS?[- :]+(.+?)(?:[ ]{0,1}[*])?(?:[ ]{2}|$)/m", $text, $m)
                ? preg_replace('/([A-Z])(\d+)/', '$1 $2', $m[1]) : null; // 19D19E    ->    19D 19E

            if (preg_match_all('/\b(\d{1,5}[A-Z])\b/i', $seatsText, $m)) {
                // 7C 7A
                $s->extra()->seats($m[1]);
            } elseif (preg_match('/^(\d{1,5})([A-Z]+)$/i', $seatsText, $m)) {
                // 11JK
                for ($i = 0; $i < strlen($m[2]); $i++) {
                    $s->extra()->seat($m[1] . $m[2][$i]);
                }
            }
        }

        if (empty($f->getTicketNumbers()) && !empty($this->airTickets)) {
            $f->setTicketNumbers($this->airTickets, false);
        }

        return true;
    }

    private function parseAir3($text, Email $email, $textFull): bool
    {
        $this->logger->debug(__FUNCTION__ . '()');

        $f = $email->add()->flight();

        if (!empty($this->pax)) {
            $f->general()->travellers($this->pax);
        }

        $date = $this->normalizeDate($this->re('/(.+)/', $text));

        $s = $f->addSegment();

        if (preg_match("/^[> ]*(?<name>.{2,}?)(?:[ ]+(?:FLIGHT|FLT))?[ ]*(?<number>\d+)$[>\s]+^[> ]*(?:DEPARTING|DEPART) .+[[:alpha:]].+\d.+$/m", $text, $m)) {
            // SOUTHWEST 1865    |    SOUTHWEST FLIGHT 4050    |    SOUTHWEST FLT1638
            $s->airline()
                ->name($m['name'])
                ->number($m['number']);

            $re = "/^[> ]*" . preg_quote($m['name'], '/') . "[ ]+(RECORD LOCATOR|CONFIRMATION)[-: ]+([A-Z\d]{5,})$/m";

            if (preg_match($re, $text, $m) || preg_match($re, $textFull, $m)) {
                $f->general()->confirmation($m[2], $m[1]);
            }
        }

        if (preg_match("/^[> ]*(?:DEPARTING|DEPART)[ ]+(?<airport>.+[[:alpha:]].+?)[ ]+(?<time>\d{3,4}[AaPp][Mm]{0,1})$/m", $text, $m)) {
            // DEPARTING PHOENIX 250PM
            $s->departure()
                ->noCode()
                ->name($m['airport'])
                ->date($this->normalizeDate($m['time'], $date));
        }

        if (preg_match("/^[> ]*(?:ARRIVING|ARRIVE)[ ]+(?<airport>.+[[:alpha:]].+?)[ ]+(?<time>\d{3,4}[AaPp][Mm]{0,1})[ ]*(?<date>.{3,})?$/m", $text, $m)) {
            // ARRIVE INDIANAPOLIS AT 1230AM 27 JAN
            if (!empty($m['date'])) {
                $date = $this->normalizeDate($m['date'], $date);
            }
            $s->arrival()
                ->noCode()
                ->name($m['airport'])
                ->date($this->normalizeDate($m['time'], $date));
        }

        return true;
    }

    private function parseRail($textPDF, Email $email): bool
    {
        $this->logger->debug(__FUNCTION__ . '()');

        $f = $email->add()->train();
//        $this->logger->debug("Text: {$textPDF}");
        $date = $this->normalizeDate($this->re("#(.+)#", $textPDF));
        $confNo = $this->re("#CONFIRMATION\s*([\w\-]{5,})#", $textPDF);

        if (empty($confNo)) {
            $confNo = $this->lastRecLoc;
        }

        if (empty($confNo)) {
            $confNo = CONFNO_UNKNOWN;
        }
        $f->general()
            ->confirmation($confNo);

        if (!empty($this->pax)) {
            $f->general()
                ->travellers($this->pax);
        } elseif (preg_match_all("#^[ ]*(.+) +SEAT\-#m", $textPDF, $m)) {
            $f->general()
                ->travellers($m[1]);
        }

        // accountNumbers
        if (preg_match_all("/\b((?:[A-Z\d][A-Z]|[A-Z][A-Z\d])-[A-Z\d]{5,})/", $textPDF, $matches)) {
            $f->program()
                ->accounts($matches[1], false);
        }

        $s = $f->addSegment();

        $re = '/RAIL\s+.+\s+CONFIRMATION\s+[A-Z\d]+\s+LV\s+(?<DCode>[A-Z]+)\s+(?<DTime>\d+)\s+(?<Dep>.+)\s+AR\s+(?<ACode>[A-Z]+)\s+(?<ATime>\d+)\s+(?<AName>.+)\s+(?<Num>\d+)\s+(?<SName>.+)\s+(?<Class>\w+)\s+CLASS/';

        if (preg_match($re, $textPDF, $m)) {
            $s->extra()
                ->number($m['Num'])
                ->service($m['SName'])
                ->cabin($m['Class']);
            $s->departure()
                ->code($m['DCode'])
                ->name($m['Dep'])
                ->date(strtotime(preg_replace('/(\d{1,2})(\d{2})/', '$1:$2', $m['DTime'])), $date);
            $s->arrival()
                ->code($m['ACode'])
                ->name($m['AName'])
                ->date(strtotime(preg_replace('/(\d{1,2})(\d{2})/', '$1:$2', $m['ATime'])), $date);
        }

        // seats
        $seatsText = $this->re("/\bSEATS? +(.+)/", $textPDF);

        if (preg_match("/\b(\d{1,5})([A-z]) +AND +([A-z])\b/i", $seatsText, $m)) {
            // 27A AND B
            $s->extra()->seats([$m[1] . $m[2], $m[1] . $m[3]]);
        } elseif (preg_match_all("/\b(\d{1,5}[A-z])\b/", $seatsText, $m)) {
            $s->extra()->seats($m[1]);
        } elseif (preg_match_all("/\bSEATS?-\b(\d{1,5}[A-z])\b/", $textPDF, $m)) {
            $s->extra()->seats($m[1]);
        }

        return true;
    }

    private function parseEvent($textPDF, Email $email): bool
    {
        $this->logger->debug(__FUNCTION__ . '()');

        $event = $email->add()->event();

        if (!empty($this->pax)) {
            $event->general()->travellers($this->pax);
        }

        $date = $this->normalizeDate($this->re("/(.+)/", $textPDF));

        if (preg_match("/^\s*(?:.+\n+){1,2}[> ]*(?<name>.{3,}?)[ ]+IN[ ]+(?<address>.{3,}?)[ ]*\n/", $textPDF, $m)) {
            $event->place()
                ->name($m['name'])
                ->address($m['address'])
                ->type(Event::TYPE_EVENT)
            ;
        }

        if ($date && preg_match("/(?<prevRow>.+)\s+^[> ]*APPOINTMENTS BEGIN (?<start>{$this->patterns['time']}) THROUGH (?<end>{$this->patterns['time']})/m", $textPDF, $m)) {
            $event->booked()->start(strtotime($m['start'], $date))->end(strtotime($m['end'], $date));

            if (!empty($event->getStartDate()) && preg_match("/^[> ]*ARRIVE AT LEAST (\d{1,3}[ ]*(?:MINUTES?|HOURS?)) PRIOR TO APPOINTMENT/m", $m['prevRow'], $m2)) {
                $event->booked()->start(strtotime('-' . $m2[1], $event->getStartDate()));
            }
            $event->general()->noConfirmation();
        }

        if (preg_match("/^[> ]*(.+ CANCELLATION)[ ]*$/m", $textPDF, $m)) {
            $event->general()->cancellation($m[1]);
        }

        return true;
    }

    private function normalizeDate($strDate, $dateRelative = null, $after = true)
    {
        $in = [
            //01 JUL 18  -  SUNDAY
            '/^\s*(\d{1,2})\s+([[:alpha:]]{3,})\s+(\d{2})\s+-\s+\w+\s*$/u',
            //01JUL
            '/^\s*(\d{1,2})\s*([[:alpha:]]{3,})\s*$/u',
            //130PM
            '/^(\d{1,2})(\d{2})[ ]*([ap]m)$/i',
            //730P    1130A    1206P
            '#^(\d{1,2})(\d{2})([AP])$#',
            // 9P
            '/^(\d{1,2})([AP])$/',
            //1200N    0700
            '/^(\d{1,2})(\d{2})(N)?$/',
            //OCTOBER 14
            '/^(\w+)\.?\s+(\d+)$/',
            // 4PM 10/03/19
            '/^(\d+)\s*([ap]m)\s+(\d+\/\d+\/\d+)$/i', //M/d/Y
        ];
        $out = [
            '$1 $2 20$3',
            '$1 $2',
            '$1:$2 $3',
            '$1:$2 $3M',
            '$1:00 $2M',
            '$1:$2',
            '$2 $1',
            '$3, $1:00$2',
        ];

        $str = preg_replace($in, $out, trim($strDate));

        if (!empty($dateRelative) && preg_match('/^\d+:\d+(?:\s*[ap]m)?$/i', $str)) {
            return strtotime($str, $dateRelative);
        } elseif (!empty($dateRelative) && preg_match('/^\d{1,2} [[:alpha:]]{3,}$/u', $str)) {
            return EmailDateHelper::parseDateRelative($str, $dateRelative, $after);
        }

        return strtotime($str);
    }

    private function htmlToText(?string $s, bool $brConvert = true): string
    {
        if (!is_string($s) || $s === '') {
            return '';
        }
        $s = str_replace("\r", '', $s);
        $s = preg_replace('/<!--.*?-->/s', '', $s); // comments

        if ($brConvert) {
            $s = str_replace("\n", '', $s);
            $s = preg_replace('/<[Bb][Rr]\b.*?\/?>/', "\n", $s); // only <br> tags
        }
        $s = preg_replace('/<[A-z][A-z\d:]*\b.*?\/?>/', '', $s); // opening tags
        $s = preg_replace('/<\/[A-z][A-z\d:]*\b[ ]*>/', '', $s); // closing tags
        $s = html_entity_decode($s);
        $s = str_replace(chr(194) . chr(160), ' ', $s); // NBSP to SPACE

        return trim($s);
    }

    private function getText(\PlancakeEmailParser $parser): string
    {
        // NBSP to SPACE and other
        $this->http->SetEmailBody(str_replace([chr(194) . chr(160), "\r"], [' ', ''], $parser->getHTMLBody()));

        $paragraphs = $this->http->XPath->query('//p[preceding-sibling::p[normalize-space()] or following-sibling::p[normalize-space()]]');

        if ($paragraphs->length < 30) {
            $paragraphs = $this->http->XPath->query('//div[not(.//div)][preceding-sibling::div[normalize-space()] or following-sibling::div[normalize-space()]]');
        }

        if ($paragraphs->length >= 30) {
            $text = '';

            foreach ($paragraphs as $p) {
                $text .= "\n" . $this->htmlToText($this->http->FindHTMLByXpath('.', null, $p));
            }

            return $text;
        }

        if ($this->http->XPath->query('//br[preceding-sibling::br or following-sibling::br]')->length > 30) {
            $htmlText = $parser->getHTMLBody();

            return $this->htmlToText($htmlText);
        }

        $plain = $parser->getPlainBody();

        if (!empty($plain) && count(array_filter(array_map("trim", explode("\n", $plain)))) > 30) {
            $text = $plain;
        } else {
            $text = $parser->getHTMLBody();
        }
        $text = strip_tags($text);
        $NBSP = chr(194) . chr(160);
        $text = str_replace($NBSP, ' ', html_entity_decode($text));
        $text = str_replace("\n>", "\n", $text);
        $text = str_replace("\n>", "\n", $text);

        return $text;
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
            $NBSP = chr(194) . chr(160);
            $body = str_replace($NBSP, ' ', html_entity_decode($body));

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

    private function opt($field): string
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s, '/'));
        }, $field)) . ')';
    }

    private function nice($str)
    {
        return trim(preg_replace("#\s+#", ' ', $str));
    }

    private function splitCols($text, $pos = false): array
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

    private function rowColsPos($row): array
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

    private function colsPos($table, $correct = 5): array
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

    private function getTotalCurrency($node): array
    {
        $tot = '';
        $cur = '';

        if (preg_match("#(?<c>[A-Z]{3})\s*(?<t>\d[\.\d\,\s]*\d*)#", $node,
                $m) || preg_match("#(?<t>\d[\.\d\,\s]*\d*)\s*(?<c>[A-Z]{3})#", $node,
                $m) || preg_match("#(?<c>\-*?)(?<t>\d[\.\d\,\s]*\d*)#", $node, $m)
        ) {
            $m['t'] = preg_replace('/\s+/', '', $m['t']);            // 11 507.00	->	11507.00
            $m['t'] = preg_replace('/[,.](\d{3})/', '$1', $m['t']);    // 2,790		->	2790		or	4.100,00	->	4100,00
            $m['t'] = preg_replace('/^(.+),$/', '$1', $m['t']);    // 18800,		->	18800
            $m['t'] = preg_replace('/,(\d{1,2})$/', '.$1', $m['t']);    // 18800,00		->	18800.00
            $tot = $m['t'];
            $cur = $m['c'];
        }

        return ['Total' => $tot, 'Currency' => $cur];
    }
}
