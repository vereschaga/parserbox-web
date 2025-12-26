<?php

namespace AwardWallet\Engine\tripact\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class FlightItinerary extends \TAccountChecker
{
    public $mailFiles = "tripact/it-37849824.eml, tripact/it-38102493.eml, tripact/it-38541365.eml, tripact/it-38818254.eml";

    public $lang = "en";
    public static $dictionary = [
        "en" => [],
    ];

    private $detectFrom = "@tripactions.com";
    private $detectSubject = [
        "en" => "- Flight to ", //eTicket - Flight to New York (LGA) confirmed itinerary and receipt | Deborah A Affinito (BEODSB)
    ];

    private $detectCompany = 'tripactions.com';

    private $detectBody = [
        "en" => ["re good to go!", "Your ticket is in the works", "You canceled your flight"],
    ];

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
//        $body = $this->http->Response['body'];
//        foreach ($this->detectBody as $lang => $detectBody){
//            foreach ($detectBody as $dBody){
//                if (strpos($body, $dBody) !== false) {
//                    $this->lang = $lang;
//                    break;
//                }
//            }
//        }

        // Travel Agency
        $email->obtainTravelAgency();
        $conf = $this->http->FindSingleNode("//text()[" . $this->starts("Record locator") . "]", null, true, "#^\s*Record locator\s+([A-Z\d]{4,})\s*$#");

        if (empty($conf) && preg_match("#\s*\(\s?([A-Z\d]{4,})\s?\)\s*$#", $parser->getSubject(), $m)) {
            $conf = $m[1];
        }
        $email->ota()->confirmation($conf, "Record locator");

        $this->parseFlight($email);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailFromProvider($from)
    {
        return strpos($from, $this->detectFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (empty($headers["from"]) || empty($headers["subject"])) {
            return false;
        }

        if (strpos($headers["from"], $this->detectFrom) === false) {
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
        if ($this->http->XPath->query("//a[contains(@href,'{$this->detectCompany}')] | //*[contains(.,'{$this->detectCompany}')]")->length === 0) {
            return false;
        }

        $body = $this->http->Response['body'];

        foreach ($this->detectBody as $detectBody) {
            foreach ($detectBody as $dBody) {
                if (strpos($body, $dBody) !== false) {
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

    private function parseFlight(Email $email)
    {
        // Price
        $total = implode("\n", $this->http->FindNodes("//text()[" . $this->eq($this->t("Total Net Charge:")) . "][1]/following::text()[normalize-space()][1]/ancestor::td[1]//text()[normalize-space()]"));

        if (preg_match("#^\s*(?<curr>[^\d\s]{1,5})\s*(?<amount>\d[\d\., ]*)\s*$#", $total, $m)
                || preg_match("#^\s*(?<amount>\d[\d\., ]*)\s*(?<curr>[^\d\s]{1,5})\s*$#", $total, $m)) {
            $email->price()
                ->total($this->amount($m['amount']))
                ->currency($this->currency($m['curr']))
            ;
        }
        $tax = implode("\n", $this->http->FindNodes("//text()[" . $this->eq($this->t("Tax:")) . "][1]/following::text()[normalize-space()][1]/ancestor::td[1]//text()[normalize-space()]"));

        if (preg_match("#^\s*(?<curr>[^\d\s]{1,5})\s*(?<amount>\d[\d\., ]*)\s*$#", $tax, $m)
                || preg_match("#^\s*(?<amount>\d[\d\., ]*)\s*(?<curr>[^\d\s]{1,5})\s*$#", $tax, $m)) {
            $currency = $this->currency($m['curr']);

            if ((!empty($email->getPrice()) && $email->getPrice()->getCurrencyCode() === $currency) || (empty($email->getPrice()))) {
                $email->price()
                    ->currency($this->currency($m['curr']))
                    ->fee("Tax", $this->amount($m['amount']));
            }
        }
        $fXpath = "//text()[" . $this->eq(["Trip fee:"]) . "][1]";
        $feeNodes = $this->http->XPath->query($fXpath);

        foreach ($feeNodes as $fRoot) {
            $feeName = $this->http->FindSingleNode(".", $fRoot);
            $fee = implode("\n", $this->http->FindNodes("./following::text()[normalize-space()][1]/ancestor::td[1]//text()[normalize-space()]", $fRoot));

            if (preg_match("#^\s*(?<curr>[^\d\s]{1,5})\s*(?<amount>\d[\d\., ]*)\s*$#", $fee, $m)
                    || preg_match("#^\s*(?<amount>\d[\d\., ]*)\s*(?<curr>[^\d\s]{1,5})\s*$#", $fee, $m)) {
                $currency = $this->currency($m['curr']);

                if ((!empty($email->getPrice()) && $email->getPrice()->getCurrencyCode() === $currency) || (empty($email->getPrice()))) {
                    $email->price()
                        ->currency($this->currency($m['curr']))
                        ->fee(trim($feeName, ': '), $this->amount($m['amount']));
                }
            }
        }

        $xpath = "//img[@alt = 'flight logo']/ancestor::tr[2]";
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $foundIt = false;
            $tickets = $this->http->FindSingleNode("./following-sibling::tr[normalize-space()][1]//text()[" . $this->eq($this->t("e-Ticket:")) . "]/following::text()[normalize-space()][1]/ancestor::td[1]", $root, false, '/Ticket:\s*(.+)/');
            $tickets = array_filter(array_map('trim', explode(',', $tickets)));

            if (empty($tickets)) {
                foreach ($email->getItineraries() as $key => $it) {
                    if (empty($it->getTicketNumbers())) {
                        /* @var \AwardWallet\Schema\Parser\Common\Flight $f */
                        $f = $email->getItineraries()[$key];
                        $foundIt = true;

                        break;
                    }
                }
            } else {
                foreach ($email->getItineraries() as $key => $it) {
                    $segTickets = array_column($it->getTicketNumbers(), 0);

                    foreach ($tickets as $tic) {
                        if (in_array($tic, $segTickets)) {
                            /* @var \AwardWallet\Schema\Parser\Common\Flight $f */
                            $f = $email->getItineraries()[$key];
                            $foundIt = true;
                            $ticketDiff = array_diff($segTickets, $tickets);

                            if (!empty($ticketDiff)) {
                                $f->issued()->tickets($ticketDiff, false);
                            }

                            break 2;
                        }
                    }
                }
            }

            if ($foundIt == false) {
                $f = $email->add()->flight();

                // General
                $travellers = array_unique($this->http->FindNodes("//text()[" . $this->eq($this->t("Passenger:")) . "]/following::text()[normalize-space()][1]"));
                $f->general()
                    ->noConfirmation()
                    ->travellers($travellers, true)
                ;

                if (!empty($this->http->FindSingleNode("(//text()[" . $this->contains($this->t("Your flight has been ticketed")) . "])[1]"))) {
                    $f->general()->status("Confirmed");
                } elseif (!empty($this->http->FindSingleNode("(//text()[" . $this->contains($this->t("Your ticket is in the works")) . "])[1]"))) {
                    $f->general()->status("Processing");
                } elseif (!empty($this->http->FindSingleNode("(//text()[" . $this->contains($this->t("You canceled your flight")) . "])[1]"))) {
                    $f->general()
                        ->status("Canceled")
                        ->cancelled();
                }

                $f->issued()->tickets($tickets, false);
            }

            // Segment
            $s = $f->addSegment();

            $dateText = $this->http->FindSingleNode("(./preceding-sibling::tr[normalize-space()])[1]", $root, true, "#^\s*(?:Canceled\s+)?(.+)#");

            if (!empty($dateText)) {
                $date = $this->normalizeDate($dateText);
            }

            $nextTr = "following-sibling::tr[normalize-space()][1]/descendant::tr[1]/ancestor::*[1]";

            $air = implode("\n", $this->http->FindNodes(".//text()[normalize-space()]", $root));

            if (preg_match("#(?<fullname>.+?)\n(?<al>[A-Z\d][A-Z]|[A-Z][A-Z\d])\s*(?<fn>\d{1,5})\s*\n\s*(?<cabin>.+)#s", $air, $m)) {
                // Airline
                $s->airline()
                    ->name($m['al'])
                    ->number($m['fn'])
                ;

                $conf = $this->http->FindSingleNode("(./preceding::text()[" . $this->eq($m['fullname']) . " and following::text()[" . $this->eq('Confirmation:') . "]])[1]/following::text()[normalize-space()][2]", $root, true, "#^\s*([A-Z\d]{5,7})\s*$#");

                if (!empty($conf)) {
                    $s->airline()->confirmation($conf);
                }

                // Extra
                if (preg_match("#(.+?)\s*\(([A-Z]{1,2})\)#", $m['cabin'], $mat)) {
                    $s->extra()
                        ->cabin(trim(preg_replace("#\s*Cabin\s*#i", '', $mat[1])))
                        ->bookingCode($mat[2])
                    ;
                } else {
                    $s->extra()->cabin($m['cabin']);
                }
            }

            // Departure
            $dep = $this->http->FindNodes($nextTr . "/tr[normalize-space()][1]/descendant::tr[not(.//tr)]/td[position() < (count(ancestor::tr[1]/td) div 2)]//text()[normalize-space()]", $root);
            $dep = implode("\n", $dep);
            //Oct 02
            //1:15 PM
            //SFO
            //San Francisco
            if (preg_match("#^(?:\w{3} \d+)?\s*(?<time>\d{1,2}:\d{1,2}.*)\s*\n\s*(?<code>[A-Z]{3})\s*\n\s*(?<name>.+)#", $dep, $m)) {
                $s->departure()->code($m['code'])->name($m['name']);

                if (!empty($date)) {
                    $s->departure()->date(strtotime($m['time'], $date));
                }
            }

            // Arrival
            $arr = $this->http->FindNodes($nextTr . "/tr[normalize-space()][1]/descendant::tr[not(.//tr)]/td[position() > (count(ancestor::tr[1]/td) div 2)]//text()[normalize-space()]", $root);
            $arr = implode("\n", $arr);
            //Oct 02
            //9:45 PM
            //Duration:
            //5h 30m
            //EWR
            //Newark
            if (preg_match("#^\s*(?<overnight>[\-+]\s*\d\s*days?\s+)?(?:\w{3} \d+\s*)?(?<time>\d{1,2}:\d{1,2}(?:\s*[AP]M)?).*?(?<code>[A-Z]{3})\s+(?<name>.+)#s", $arr, $m)) {
                $s->arrival()->code($m['code'])->name($m['name']);

                if (!empty($date)) {
                    $aDate = strtotime($m['time'], $date);

                    if (!empty($aDate) && !empty($m['overnight'])) {
                        $aDate = strtotime(preg_replace("#\s+#", ' ', $m['overnight']), $aDate);
                    }
                    $s->arrival()->date($aDate);
                }
            }

            // Extra
            $s->extra()->seat($this->http->FindSingleNode($nextTr . "//text()[" . $this->eq("Seat:") . "][1]/following::text()[normalize-space()][1]", $root, true, "#^\s*(\d{1,3}[A-Z])\s*(?: |$)#"), true, true);

            // Program
            $ff = $this->http->FindSingleNode("./following-sibling::tr[normalize-space()][1]//text()[" . $this->eq($this->t("Known Traveler Number:")) . "]/following::text()[normalize-space()][1]", $root);

            if (!empty($ff) && !in_array($ff, array_column($f->getAccountNumbers(), 0))) {
                $f->program()->account($ff, true);
            }
        }

        return $email;
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
//        $this->http->log($str);
//        $in = [
//            "#^[^\s\d]+,\s*([^\s\d]+)\s*(\d{1,2})[a-z]{2}?,\s*(\d{4})\s*$#iu",// Friday, February 9th, 2018
//        ];
//        $out = [
//            "$2 $1 $3",
//        ];
//        $str = preg_replace($in, $out, $str);
//        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
//            if ($en = MonthTranslate::translate($m[1], $this->lang))
//                $str = str_replace($m[1], $en, $str);
//        }

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

    private function amount($price)
    {
        if (preg_match("#^\s*\d{1,3}(,\d{1,3})?\.\d{2}\s*$#", $price)) {
            $price = str_replace([',', ' '], '', $price);
        }

        if (is_numeric($price)) {
            return (float) $price;
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

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'normalize-space(.)="' . $s . '"'; }, $field)) . ')';
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'starts-with(normalize-space(.),"' . $s . '")'; }, $field)) . ')';
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'contains(normalize-space(.),"' . $s . '")'; }, $field)) . ')';
    }
}
