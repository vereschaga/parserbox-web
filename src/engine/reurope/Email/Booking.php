<?php

namespace AwardWallet\Engine\reurope\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class Booking extends \TAccountChecker
{
    public $mailFiles = "reurope/it-15868019.eml, reurope/it-15995148.eml, reurope/it-21981346.eml, reurope/it-30818296.eml";

    //	private $reFrom = "";
    private $reSubject = [
        "en" => "Rail Europe Booking #",
    ];

    private $reBody2 = [
        "en" => ["This email is not a valid travel document", "THIS DOCUMENT IS NOT VALID FOR TRAVEL"],
    ];

    private static $dictionary = [
        "en" => [
            "PNR:"   => ["PNR:", "Booking File Reference:"],
            "Seats:" => ["Seats:", "Seat:"],
        ],
    ];
    private $lang = "en";
    private $bookingDate;
    private $dateFormatUS = true;
    private $patterns = [
        'confNumber' => '[A-Z\d]{5,}', // 11986371476    |    M5GPQK
    ];

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        foreach ($this->reBody2 as $lang => $phrases) {
            foreach ($phrases as $phrase) {
                if (strpos($this->http->Response["body"], $phrase) !== false) {
                    $this->lang = $lang;

                    break 2;
                }
            }
        }
        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        $trip = $this->http->FindSingleNode("//text()[starts-with(normalize-space(.),'Booking #:')]", null, true, "/:\s*({$this->patterns['confNumber']})\s*$/");

        if (empty($trip)) {
            $trip = $this->http->FindSingleNode("//text()[starts-with(normalize-space(.),'Booking #:')]/following::text()[normalize-space(.)][1]", null, true, "/^\s*({$this->patterns['confNumber']})\s*$/");
        }

        if (empty($trip) && preg_match("#\#\s*([A-Z\d]{5,})\s*$#", $parser->getSubject(), $m)) {
            $trip = $m[1];
        }

        if (!empty($trip)) {
            $email->ota()
                ->confirmation($trip, 'Booking #');
        }

        $this->train($email);

        return $email;
    }

    public function detectEmailFromProvider($from)
    {
        //		return strpos($from, $this->reFrom)!==false;
        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        //		if (strpos($headers["from"], $this->reFrom)===false)
        //			return false;

        foreach ($this->reSubject as $re) {
            if (stripos($headers["subject"], $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $body = $this->http->Response["body"];

        if (stripos($body, 'www.raileurope.com') === false && stripos($body, 'agent.raileurope.com') === false && stripos($body, 'www.railplus.com.au') === false && stripos($body, '@railplus.com.au') === false) {
            return false;
        }

        foreach ($this->reBody2 as $phrases) {
            foreach ($phrases as $phrase) {
                if (stripos($body, $phrase) !== false) {
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

    protected function train(Email $email)
    {
        $f = $email->add()->train();

        // General
        $confNumberNodes = $this->http->XPath->query('//text()[' . $this->starts($this->t('PNR:')) . ']');

        foreach ($confNumberNodes as $confNumberNode) {
            $confNumberText = $this->http->FindSingleNode('./following::text()[normalize-space(.)][1]', $confNumberNode, true, "/^({$this->patterns['confNumber']})\b/");

            if ($this->confNumberInArray($confNumberText, $f->getConfirmationNumbers()) === false) {
                $f->general()->confirmation($confNumberText, preg_replace('/\s*:\s*$/', '', $confNumberNode->nodeValue));
            }
        }

        $date = $this->http->FindSingleNode("//text()[starts-with(normalize-space(.),'Booking Date:')]", null, true, "/:\s*(.+)\s*$/");

        if (empty($date)) {
            $date = $this->http->FindSingleNode("//text()[starts-with(normalize-space(.),'Booking Date:')]/following::text()[normalize-space(.)][1]");
        }

        if (!empty($date)) {
            $this->bookingDate = $this->normalizeDate($date, true);

            if (empty($this->bookingDate)) {
                $this->bookingDate = $this->normalizeDate($date, false);

                if (!empty($this->bookingDate)) {
                    $this->dateFormatUS = false;
                }
            }
        }

        // ticketNumbers
        // travellers
        $ticketNumbers = [];
        $travellers = $this->http->FindNodes("//text()[starts-with(normalize-space(.),'Departure:')]/following::table[contains(.//tr/td[2], 'Adult')][1]//tr/td[1]", null, "#^[^\d:]+$#u"); // it-15868019.eml, it-15995148.eml
        $travellerValues = array_values(array_filter($travellers));
        $passengerRows = $this->http->XPath->query('//text()[' . $this->eq($this->t('DOCUMENTS ISSUED')) . ']/following::tr[ ./td[2][' . $this->starts($this->t('Ticket No')) . '] and ./td[4][' . $this->starts($this->t('Passenger')) . '] ]/following-sibling::tr[normalize-space(.)]'); // it-21981346.eml

        foreach ($passengerRows as $passengerRow) {
            $ticketNumber = $this->http->FindSingleNode('./td[2]', $passengerRow, true, '/^([-\d]*\d{7,}[-\d]*)$/');

            if ($ticketNumber) {
                $ticketNumbers[] = $ticketNumber;
            }
            $passenger = $this->http->FindSingleNode('./td[4]', $passengerRow, true, '/^([A-z][-.\'A-z ]*[A-z])(?:\s*\(|\s*$)/');

            if ($passenger) {
                $travellerValues[] = $passenger;
            }
        }

        if (!empty($ticketNumbers[0])) {
            $f->setTicketNumbers(array_unique($ticketNumbers), false);
        }

        if (!empty($travellerValues[0])) {
            $f->general()->travellers(array_unique($travellerValues));
        }

        // Price
        $total = $this->http->FindSingleNode("//tr[normalize-space(.)='SUMMARY']/following::td[normalize-space()='Total']/following-sibling::td[2]");

        if (!empty($total)) {
            $f->price()
                ->total($this->amount($total))
                ->currency($this->http->FindSingleNode("//tr[normalize-space(.)='SUMMARY']/following::td[normalize-space()='Total']/following-sibling::td[1]"));
        }
        $firstDate = false;
        // Segments
        $xpath = '//text()[starts-with(normalize-space(.),"Departure:")]/ancestor::*[ (self::tr or self::table) and ./following-sibling::*[ ./descendant::text()[' . $this->starts($this->t('PNR:')) . '] ] ][1]';
        $segments = $this->http->XPath->query($xpath);

        foreach ($segments as $i => $root) {
            $s = $f->addSegment();

            $xpathFragmentBefore = "./following-sibling::*[ ./descendant::text()[{$this->starts($this->t('Departure:'))}] ]";

            if ($this->http->XPath->query($xpathFragmentBefore, $root)->length > 0) {
                // it-15995148.eml
                $this->logger->debug("Segment-$i: type-1");
                $nodesBefore = $this->http->XPath->query('./preceding-sibling::*', $root)->length;
                $nodesBeforeNext = $this->http->XPath->query($xpathFragmentBefore . '/preceding-sibling::*', $root)->length;
                $nodesLength = $nodesBeforeNext - $nodesBefore;
            } else {
                // it-15868019.eml
                $this->logger->debug("Segment-$i: type-2");
                $nodesLength = $this->http->XPath->query('./following-sibling::*', $root)->length;
            }

            if ($nodesLength < 2) {
                $this->logger->alert("Segment-$i: wrong format!");

                return $email;
            }

            // TRAIN VT522000    |    7384738
            $number = $this->http->FindSingleNode("./following-sibling::*[position()<{$nodesLength}]/descendant::text()[starts-with(normalize-space(.),'Train No:')]/following::text()[normalize-space(.)][1]", $root);

            if (empty($number)) {
                $number = $this->http->FindSingleNode("./descendant::text()[starts-with(normalize-space(.),'Train No:')]/following::text()[normalize-space(.)][1]", $root);
            }

            if (preg_match('/^\s*(\d{1,6})\s*$/', $number, $matches) || preg_match('/^\s*TRAIN\s+[\-A-Z]*(\d{1,6})\s*$/i', $number, $matches)
                    || preg_match('/^\s*[A-Z]*(\d{1,6})\s*$/i', $number, $matches)) {
                $s->extra()->number($matches[1]);
            } elseif ($number === null || strcasecmp($number, 'UNDERGROUND') === 0) {
                $s->extra()->noNumber();
            }

            $departure = $this->http->FindSingleNode("./descendant-or-self::tr[not(.//tr) and contains(normalize-space(.),'Departure:')][1]", $root);

            if (preg_match("#Departure:\s*(?<name>.+?)\s+on\s+\w+\s+(?<date>[\d\/]{6,}.+)#", $departure, $m)) {
                if ($firstDate == false) {
                    $date = $this->normalizeDate($m['date'], $this->dateFormatUS);

                    if (!empty($this->bookingDate) && !empty($date) && $this->bookingDate > $date) {
                        $this->dateFormatUS = !$this->dateFormatUS;
                    } elseif (empty($date)) {
                        $date = $this->normalizeDate($m['date'], (!$this->dateFormatUS));

                        if (!empty($this->bookingDate) && !empty($date)) {
                            $this->dateFormatUS = !$this->dateFormatUS;
                        }
                    }
                    $firstDate = true;
                }
                $s->departure()
                    ->name($m['name'])
                    ->date($this->normalizeDate($m['date'], $this->dateFormatUS));
            }

            $arrival = $this->http->FindSingleNode("./descendant::tr[not(.//tr) and contains(normalize-space(.),'Arrival:')][1] | ./following-sibling::tr[normalize-space(.)][1]", $root);

            if (preg_match("#Arrival:\s*(?<name>.+?)\s+on\s+\w+\s+(?<date>[\d\/]{6,}.+)#", $arrival, $m)) {
                $s->arrival()
                    ->name($m['name'])
                    ->date($this->normalizeDate($m['date'], $this->dateFormatUS));
            }

            // cabin
            $cabinTexts = $this->http->FindNodes("./following::table[contains(.//tr/td[2], 'Adult')][1]//tr/td[3]", $root, "#(.+?)(?:Adults?|Childs?|Infants?)?\s*$#i");
            $cabinValues = array_values(array_filter($cabinTexts));

            if (!empty($cabinValues[0])) {
                $s->extra()->cabin(implode(', ', array_unique($cabinValues)));
            }

            $coach = $this->http->FindSingleNode("./following-sibling::*[position()<={$nodesLength}]/descendant::text()[starts-with(normalize-space(.),'Coach:')]/following::text()[normalize-space(.)][1]", $root, true, "/^\s*([A-Z\d]{1,3})\s*(?:\s|$)/");

            if (!empty($coach)) {
                $s->extra()->car($coach);
                $seats = $this->http->FindSingleNode("./following-sibling::*[position()<={$nodesLength}]/descendant::text()[{$this->starts($this->t('Seats:'))}]/following::text()[normalize-space(.)][1]", $root);

                if (preg_match_all("#(?:^|,|\))\s*([A-Z\d]{1,4})(?:\s|\(|$)#", $seats, $m)) {
                    $s->extra()->seats($m[1]);
                }
            }
        }

        return $email;
    }

    protected function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
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

    private function confNumberInArray($pnr, $confNumbers)
    {
        foreach ($confNumbers as $key => $value) {
            if ($value[0] === $pnr) {
                return $key;
            }
        }

        return false;
    }

    private function normalizeDate($str, $isUS = true)
    {
//        $this->http->log('$str = '.print_r($str ,true));
        if ($isUS == true) {
            $in = [
                "#^\s*(\d{1,2})/(\d{1,2})/(\d{4})\s+at\s+(\d+:\d+)\s*$#", //07/30/2018 at 12:30
            ];
            $out = [
                "$2.$1.$3 $4",
            ];
        } else {
            $in = [
                "#^\s*(\d{1,2})/(\d{1,2})/(\d{4})\s+at\s+(\d+:\d+)\s*$#", //07/30/2018 at 12:30
            ];
            $out = [
                "$1.$2.$3 $4",
            ];
        }
        $str = preg_replace($in, $out, $str);

        return strtotime($str);
    }

    private function amount($s)
    {
        $s = str_replace(",", ".", preg_replace("#[., ](\d{3})#", "$1", $this->re("#(\d[\d\,\. ]*)#", $s)));

        if (is_numeric($s)) {
            return (float) $s;
        }

        return null;
    }

    private function currency($s)
    {
        $sym = [
            '€'=> 'EUR',
            '$'=> 'USD',
            '£'=> 'GBP',
        ];

        if ($code = $this->re("#(?:^|\s|\d)([A-Z]{3})(?:$|\s|\d)#", $s)) {
            return $code;
        }
        $s = $this->re("#([^\d\,\. ]+)#", $s);

        foreach ($sym as $f=> $r) {
            if (strpos($s, $f) !== false) {
                return $r;
            }
        }

        return null;
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
}
