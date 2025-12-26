<?php

namespace AwardWallet\Engine\bcd\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class TravelItineraryPlain extends \TAccountChecker
{
    public $mailFiles = "bcd/it-8728154.eml, bcd/it-8821475.eml";
    public $reFrom = "@bcdtravel.com";
    public $reSubject = [
        "en" => "Travel Itinerary for",
        'E-Ticket Receipt and Itinerary -',
    ];
    public $reProvider = ['BCD Travel', 'citravel.com', '.graspdata.com', 'travco.tv'];
    public $reBody2 = [
        "en" => "Travel Summary - ",
    ];
    public $text;
    public $date;
    public static $dictionary = [
        "en" => [],
    ];

    public $lang = "";

    private $rentalCompanies = [
        'sixt' => ['Sixt Rent a Car'],
        'avis' => ['Avis Rent A Car'],
    ];

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->date = strtotime($parser->getHeader('date'));

        $this->http->FilterHTML = false;

        $this->text = $parser->getPlainBody();

        if (empty($this->text)) {
            $this->text = $this->htmlToText($parser->getHTMLBody());
        }

        foreach ($this->reBody2 as $lang => $re) {
            if (strpos($this->text, $re) !== false) {
                $this->lang = $lang;

                break;
            }
        }

        $this->parsePlain($email);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function parsePlain(Email $email)
    {
        $text = $this->text;
        $text = preg_replace("/^(?:>+ |>+\n)/m", '', $text);
        $text = preg_replace("/\n *<?https?:\/\/\S+\>? *\n/", "\n", $text);

        $tripNumber = $this->re("/\n *Travel Summary - .* ([A-Z\d]{5,})\s*\n/", $text);

        $email->ota()
            ->confirmation($tripNumber);

        $travellers = $this->niceTravellers(preg_split('/\s*\n\s*/',
            preg_replace('/^( {0,5}\S.*?) {2,}.+/m', '$1',
            trim($this->re("/\n *Traveler(?: {2,}.*)\s*\n([\s\S]+?)\s*\n *Date /", $text)))));

        $mainTable = $this->re("/\n([^\n]*Flight\/Vendor.*?)\n\n/ms", $text);

        if (strpos($mainTable, "\n") === false) {
            $mainTable = $this->re("#\n([^\n]*Flight\/Vendor.*?)(?:\n\n\n|\n {0,5}\-{5,}\s*\n)#ms", $text);
            $mainTable = str_replace("\n\n", "\n", $mainTable);
        }
        $rows = explode("\n", $mainTable);
        $pos = $this->tableHeadPos(array_shift($rows));
        $summaryInfo = [];

        foreach ($rows as $row) {
            $table = $this->splitCols($row, $pos);

            if (count($table) != 6 && count($table) != 5) {
                $this->http->Log("incorrect parse table");

                continue;
            }

            if (preg_match("/^\s*[A-Z]{3} ?- ?[A-Z]{3}$/", $table[1])) {
                $summaryInfo[trim($table[2])][] = $table;
            }
        }
        // $this->logger->debug('$summaryInfo = '.print_r( $summaryInfo,true));

        $airs = $cars = $hotels = [];

        $segments = $this->split("/\n *([A-Z]+ - \w+[., ]{1,3}\w+[., ]{1,3}\w+[., ]{1,3}\d{4}.*\n\s*[\-]{5,})/", $text);

        foreach ($segments as $sText) {
            $sText = preg_replace("/^((?:.*\n){5}[\s\S]+?)\n[\-]{5,}[\s\S]+/", '$1', $sText);

            $type = $this->re("#^\s*(.+?)\s+- #", $sText);

            switch ($type) {
                case 'AIR':
                    $airs[] = $sText;

                    break;

                case 'CAR':
                    $cars[] = $sText;

                    break;

                case 'HOTEL':
                    $hotels[] = $sText;

                    break;

                case 'Travel Summary':
                    break;

                default:
                    $this->http->Log("unknown segment type {$type}");

                    return;
            }
        }

        if (!empty($airs)) {
            $f = $email->add()->flight();

            $f->general()
                ->travellers($travellers, true);

            if (preg_match_all("/\n *-{5,}\n *(?<name>[A-Z][A-Z\/\.\-]+):\s*\n *-{5,}\s*\n(.*\n){1,4}\s*E-Ticket Number: *(?<number>\d{10,})\n/", $text, $m)) {
                foreach ($m[0] as $i => $v) {
                    $f->issued()
                        ->ticket($m['number'][$i], true, $this->niceTravellers($m['name'][$i]));
                }
            }

            $total = PriceHelper::parse($this->re("/\n *Total of Tickets(?: and Service Fees)?: *(\d[\d\.]+)\s*\n/", $text), null);

            if (empty($total)) {
                $total = PriceHelper::parse($this->re("/\n *Total Amount: *(\d[\d\.]+)\s*\n/", $text), null);
            }

            if (!empty($total)) {
                $f->price()
                    ->total($total);
            }
        }

        foreach ($airs as $sText) {
            $extraTable = [];

            // General
            $conf = $this->re("/^.+ - .+ - .+ ([A-Z\d]{5,})\n/", $sText);

            if (!empty($conf) && $conf !== $tripNumber
                && !in_array($conf, array_column($f->getConfirmationNumbers(), 0))) {
                $f->general()
                    ->confirmation($conf);
            }

            if (preg_match("/\n *FF Number: *(?<name>Partner Mileage (?:[A-Z][A-Z\d]|[A-Z\d][A-Z]))(?<number>\d{5,}) - (?<traveller>[A-Z\-\/\.]+)\s*\n/", $sText, $m)
            ) {
                if (!in_array($m['number'], array_column($f->getAccountNumbers(), 0))) {
                    $f->program()
                        ->account($m['number'], false, $this->niceTravellers($m['traveller']), $m['name']);
                }
            }

            $s = $f->addSegment();

            // Airline
            if (preg_match("/\n\s*[\-]{5,}\s*\n\s*.+Flight (?<al>[A-Z][A-Z\d]|[A-Z\d][A-Z]) ?(?<fn>\d{1,5}) (?<class>.+)/", $sText, $m)) {
                $s->airline()
                    ->name($m['al'])
                    ->number($m['fn']);

                $s->extra()
                    ->cabin($m['class']);

                if (isset($summaryInfo[$m['al'] . ' ' . $m['fn']]) && count($summaryInfo[$m['al'] . ' ' . $m['fn']]) == 1) {
                    $extraTable = $summaryInfo[$m['al'] . ' ' . $m['fn']][0];
                }
            }
            $conf = $this->re("/ +Booking Reference:\s+([A-Z\d]{5,7})\s+/", $sText);

            if (!empty($conf)) {
                $s->airline()
                    ->confirmation($conf);
            }

            $s->airline()
                ->operator(preg_replace('/\s+/', ' ', trim(
                    $this->re("#\n *Operated By:\s+(?:OPERATED BY\s+)?(.+(?:\n.+)?)\s*\n\s*Seat:#", $sText))), true, true);

            $re = "/^\s*(?<name1>.+?)(?<terminal>, *.*Terminal.*)?\n(?<name2>.+)\n *(?<date>\S.+)\s*$/s";

            // Departure
            $depart = $this->re("/\n *Depart:([\s\S]+?)\s*\n *Arrive:/", $sText);

            if (preg_match($re, $depart, $m)) {
                $s->departure()
                    ->name(trim($m['name1']) . ', ' . trim($m['name2']))
                    ->terminal(trim(preg_replace(["/\s*\bterminal\b\s*/i", '/\s+/u'], ' ', trim($m['terminal'], ', '))), true, true)
                    ->date($this->normalizeDate($m['date']));

                $code = $this->re("/^\s*([A-Z]{3})-[A-Z]{3}\s*$/", $extraTable[1] ?? '');

                if (!empty($code)) {
                    $s->departure()
                        ->code($code);
                } else {
                    $s->departure()
                        ->noCode();
                }
            }
            $arrive = $this->re("/\n *Arrive:([\s\S]+?)\s*\n *Duration:/", $sText);

            if (preg_match($re, $arrive, $m)) {
                $s->arrival()
                    ->name(trim($m['name1']) . ', ' . trim($m['name2']))
                    ->terminal(trim(preg_replace(["/\s*\bterminal\b\s*/i", '/\s+/u'], ' ', trim($m['terminal'], ', '))), true, true)
                    ->date($this->normalizeDate($m['date']));

                $code = $this->re("/^\s*[A-Z]{3}-([A-Z]{3})\s*$/", $extraTable[1] ?? '');

                if (!empty($code)) {
                    $s->arrival()
                        ->code($code);
                } else {
                    $s->arrival()
                        ->noCode();
                }
            }

            // Extra
            $s->extra()
                ->aircraft($this->re("#\n *Equipment:\s+(.+)#", $sText), true, true)
                ->duration($this->re("#\n *Duration:\s+(.*?)(?:\s+Non-stop|\s+with\s+|\n)#", $sText))
                ->miles($this->re("#\n *(?:Distance|Flight Miles):\s+(.+)#", $sText), true, true)
                ->cabin($s->getCabin() ?? $this->re("/(.*?) \/ [A-Z]{1,2}/", $extraTable[5] ?? ''), true, true)
                ->bookingCode($this->re("/.*? \/ ([A-Z]{1,2})/", $extraTable[5] ?? ''), true, true)
                ->meal($this->re("#\n *Meal:\s+(.+)#", $sText), true, true)
                ->seat($this->re("#\n *Seat:\s+(\d{1,3}[A-Z])\s+#", $sText), true, true)
            ;

            if (preg_match("/Duration:\s+.*\s+(Non-stop)/", $sText)) {
                $s->extra()->stops(0);
            } elseif (preg_match("/Duration:\s+.*\s+with\s+(.+)/", $sText, $m)) {
                $s->extra()->stops($m[1]);
            } elseif (preg_match("/\n *Stop\(s\):\s+(.+)/", $sText, $m)) {
                $s->extra()->stops($m[1]);
            }
        }

        if (!empty($f) && empty($f->getConfirmationNumbers())) {
            $f->general()
                ->noConfirmation();
        }

        foreach ($cars as $sText) {
            // $this->logger->debug('CAR $sText = '.print_r( $sText,true));
            $r = $email->add()->rental();

            // General
            $r->general()
                ->confirmation($this->re("/\n *Confirmation: *(\S.+?)( [A-Z]+)?\s*\n/", $sText))
                ->travellers($travellers, true)
                ->status($this->re("/\n *Status: *(.+)/", $sText))
            ;

            $re = '/^\s*(?<name>[\S\s]+?)(?:;\s*Tel:\s*(?<tel>.+?))?\n(?<date>.+)\s*$/';
            // Pick Up
            $pickUp = $this->re("/\n *Pick Up: *([\s\S]+?)\n\s*Drop Off:/", $sText);

            if (preg_match($re, $pickUp, $m)) {
                $r->pickup()
                    ->location(preg_replace('/\s+/', ' ', trim($m['name'])))
                    ->phone($m['tel'], true, true)
                    ->date($this->normalizeDate($m['date']));
            }

            // Drop Off
            $dropOff = $this->re("/\n *Drop Off: *([\s\S]+?)\n\s*Type:/", $sText);

            if (preg_match($re, $dropOff, $m)) {
                $r->dropoff()
                    ->location(preg_replace('/\s+/', ' ', trim($m['name'])))
                    ->phone($m['tel'], true, true)
                    ->date($this->normalizeDate($m['date']));
            }

            // Car
            $r->car()
                ->type($this->re("/\n *Type: *(.+)/", $sText));

            // Extra
            $company = trim($this->re("/[\-]{5,}\n\s*(\S.+)\s*\n *Pick Up: *(.+)/", $sText));
            $foundRentalProvider = false;

            if (!empty($company)) {
                foreach ($this->rentalCompanies as $code => $companyNames) {
                    foreach ($companyNames as $name) {
                        if ($name === $company) {
                            $r->program()->code($code);
                            $foundRentalProvider = true;

                            break 2;
                        }
                    }
                }

                if ($foundRentalProvider == false) {
                    $r->extra()->company($company);
                }
            }

            $account = $this->re("/\n *FF Number: *(\S.+)/", $sText);

            if (!empty($account)) {
                $r->program()
                    ->account($account, false);
            }

            // Price
            if (preg_match("/\n *Approx total:\s*([A-Z]{3}) *(\d[\d.,]*)? /", $sText, $m)) {
                $r->price()
                    ->total(PriceHelper::parse($m[2], $m[1]))
                    ->currency($m[1])
                ;
            }
        }

        foreach ($hotels as $sText) {
            // $this->logger->debug('HOTEL $sText = '.print_r( $sText,true));
            $h = $email->add()->hotel();

            // General
            $h->general()
                ->confirmation($this->re("/\n *Confirmation: *(.+)/", $sText))
                ->travellers($travellers, true)
                ->status($this->re("/\n *Status: *(.+)/", $sText))
                ->cancellation($this->re("/\n *Cancellation Policy: *(.+)/", $sText))
            ;

            // Hotel
            $h->hotel()
                ->name($this->re("/\n *[\-]{5,}\s*\n\s*(.+)\s*\n *Address:/", $sText))
                ->address(preg_replace('/\s+/', ' ', $this->re("/\n *Address: *((?:.+\n){1,6}?) *Tel:/", $sText)))
                ->phone($this->re("/\n *Tel: *(\S.+)/", $sText), true, true)
                ->fax($this->re("/\n *Fax: *(\S.+)/", $sText), true, true)
            ;

            // Booked
            $h->booked()
                ->checkIn(strtotime($this->re("/\n *Check In\/Check Out: *(.+?) - .+/", $sText)))
                ->checkOut(strtotime($this->re("/\n *Check In\/Check Out: *.+? - (.+)/", $sText)))
            ;

            // Rooms
            $h->addRoom()
                ->setType($this->re("/\n *Room Type: *(.+)/", $sText))
                ->setRate($this->re("/\n *Rate Information: *(.+)/", $sText))
            ;

            // Price
            $h->price()
                ->total(PriceHelper::parse($this->re("/\n *Est\. Total Rate:\s*(\d[\d.,]*)\s*\n/", $sText), null), true, true)
            ;

            $account = $this->re("/\n *FF Number: *(\S.+)/", $sText);

            if (!empty($account)) {
                $h->program()
                    ->account($account, false);
            }
        }
    }

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
            if (strpos($headers["subject"], $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        if (empty($body)) {
            $body = $parser->getPlainBody();
        }

        // $detectedProvider = false;
        // foreach ($this->reProvider as $re) {
        //     if (strpos($body, $re) !== false) {
        //         $detectedProvider = true;
        //         break;
        //     }
        // }
        // if ($detectedProvider === false) {
        //     return false;
        // }

        foreach ($this->reBody2 as $re) {
            if (strpos($body, $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmail(PlancakeEmailParser $parser)
    {
        $this->date = strtotime($parser->getHeader('date'));

        $this->http->FilterHTML = false;
        $itineraries = [];
        $this->text = $parser->getPlainBody();

        if (empty($this->text)) {
            $this->text = $this->htmlToText($parser->getHTMLBody());
        }

        foreach ($this->reBody2 as $lang => $re) {
            if (strpos($this->text, $re) !== false) {
                $this->lang = $lang;

                break;
            }
        }

        $this->parsePlain($itineraries);

        $result = [
            'emailType'  => 'TravelItineraryPlain' . ucfirst($this->lang),
            'parsedData' => [
                'Itineraries' => $itineraries,
            ],
        ];

        return $result;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
    }

    public function niceTravellers($name)
    {
        return preg_replace(["/[\s\.]+/i", "/^\s*(.+?)\s*\/\s*(.+?)\s*$/"],
            [' ', '$2 $1'], $name);
    }

    protected function htmlToText($string)
    {
        $NBSP = chr(194) . chr(160);
        $string = str_replace($NBSP, ' ', html_entity_decode($string));
        $string = preg_replace('/<[^>]+>/', "\n", $string);

        return $string;
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function normalizeDate($str)
    {
        // $this->logger->debug('$str = '.print_r( $str,true));
        $year = date("Y", $this->date);
        $in = [
            // 07:30 PM Wednesday, August 28 2024
            "#^\s*(\d{1,2}:\d{2}(?:\s*[ap]m))\s+[[:alpha:]]+,\s*([[:alpha:]]+)\s+(\d{1,2})\s+(\d{4})\s*$#i",
            "#^([^\s\d]+)\s+(\d+),\s+(\d+:\d+)\s+([ap])\.m\.$#", //August 23, 7:50 p.m.
            // Wednesday, August 28 2024
        ];
        $out = [
            "$3 $2 $4, $1",
            "$2 $1 $year, $3 $4m",
        ];
        $str = preg_replace($in, $out, $str);
        // $this->logger->debug('$str = '.print_r( $str,true));

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return strtotime($str);
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
        return (float) str_replace(",", ".", preg_replace("#[.,](\d{3})#", "$1", $this->re("#([\d\,\.]+)#", $s)));
    }

    private function currency($s)
    {
        $sym = [
            '€' => 'EUR',
            '$' => 'USD',
            '£' => 'GBP',
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

    private function TableHeadPos($row)
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

    private function SplitCols($text, $pos = false)
    {
        $cols = [];
        $rows = explode("\n", $text);

        if (!$pos) {
            $pos = $this->TableHeadPos($rows[0]);
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
}
