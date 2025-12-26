<?php

namespace AwardWallet\Engine\sandals\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class VacationInvoice extends \TAccountChecker
{
    public $mailFiles = "sandals/it-52194909.eml, sandals/it-54973061.eml, sandals/it-55552015.eml, sandals/it-56088798.eml";

    public $lang = "en";
    public static $dictionary = [
        "en" => [
            //            "Booking Information" => "",
            //            "Booking Number:" => "",
            //            "Booked Date:" => "",
            "No. of Adults:" => ["No. of Adults:", "Adults:"],
            //            "Children:" => "",
            // Hotel
            //            "Vacation Information" => "",
            //            "Resort: => "",
            //            "Accommodation:" => "",
            //            "Arrival Date:" => "",
            //            "Departure Date:" => "",
            //            "Guest Names:" => "",
            // Flight
            //            "Flight Information" => "",
            "Airline Record Locator" => ["Airline Record Locator", "Air Confirmation Number"],
            //            "GDS Number" => "",
            //            "Guest Name(s):" => "",
            //            "Departing:" => "",
            //            "Arriving:" => "",
            //            "FLIGHT #" => "",
            //            "SEATS" => "",
            // Price
            //            "Package Price:" => "",
            //            "Total Charges:" => "",
        ],
    ];

    private $detectFrom = "@e.sandalsmailings.com";
    private $detectSubject = [
        "Sandals Resorts Vacation Invoice - ",
        "Beaches Resorts Vacation Invoice - ",
        "Sandals Booking Confirmation",
    ];

    private $detectCompany = [
        '.sandals.com',
        '.beaches.com',
        '.sandalsmailings.com',
    ];

    private $detectBody = [
        "en" => "Vacation Information",
    ];

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        // Travel Agency
        $email->obtainTravelAgency();
        $email->ota()
            ->confirmation($this->http->FindSingleNode("//td[" . $this->eq($this->t("Booking Number:")) . " and not(.//td)]/following-sibling::td[normalize-space()][1]", null, true, '/^[-A-Z\d]{5,}$/'), rtrim($this->t("Booking Number:"), ': '))
        ;

        // Price
        $currency = null;

        $costStr = $this->http->FindSingleNode("//text()[" . $this->eq($this->t("Package Price:")) . "]/ancestor::td[1]/following-sibling::td[normalize-space()][1]");

        if (preg_match("/^(?<curr>[^\d)(]{1,5}?)\s*(?<amount>\d[,.\'\d ]*)$/", $costStr, $m)
            || preg_match("/^(?<amount>\d[,.\'\d ]*)\s*(?<curr>[^\d)(]{1,5})$/", $costStr, $m)
        ) {
            $currency = $currency ?? $m['curr'];

            if ($m['curr'] === $currency) {
                $cost = $this->normalizeAmount($m['amount']);
            }
        }

        $totalStr = $this->http->FindSingleNode("//text()[" . $this->eq($this->t("Total Charges:")) . "]/ancestor::td[1]/following-sibling::td[normalize-space()][1]");

        if (preg_match("/^(?<curr>[^\d)(]{1,5}?)\s*(?<amount>\d[,.\'\d ]*)$/", $totalStr, $m)
            || preg_match("/^(?<amount>\d[,.\'\d ]*)\s*(?<curr>[^\d)(]{1,5})$/", $totalStr, $m)
        ) {
            $currency = $currency ?? $m['curr'];

            if ($m['curr'] === $currency) {
                $total = $this->normalizeAmount($m['amount']);
            }
        }

        $pXpath = "//text()[" . $this->eq($this->t("Package Price:")) . "]/ancestor::tr[1]/following-sibling::tr[normalize-space()]";
        $pNodes = $this->http->XPath->query($pXpath);
        $discount = 0;
        $fees = [];
        $feesTotal = 0;

        foreach ($pNodes as $pRoot) {
            $feeName = $this->http->FindSingleNode("./td[normalize-space()][1]", $pRoot);
            $fee = $this->http->FindSingleNode("./td[normalize-space()][2]", $pRoot);

            if (preg_match("/^(?<sign>-)?\s*(?<curr>[^\d)(]{1,5}?)\s*(?<amount>\d[,.\'\d ]*)$/", $fee, $m)
                || preg_match("/^(?<sign>-)?\s*(?<amount>\d[,.\'\d ]*)\s*(?<curr>[^\d)(]{1,5})$/", $fee, $m)
            ) {
                $currency = $currency ?? $m['curr'];

                if (!empty($m['sign'])) {
                    $discount += $this->normalizeAmount($m['amount']);

                    continue;
                }

                if ($m['curr'] === $currency) {
                    $feesTotal += $this->normalizeAmount($m['amount']);
                    $fees[] = ['name' => trim($feeName, ': '), 'value' => $this->normalizeAmount($m['amount'])];
                }
            }
        }

        if (!empty($cost) && !empty($total) && $total = $cost + $feesTotal - $discount) {
            $email->price()
                ->cost($cost)
                ->total($total)
                ->discount($discount)
                ->currency($currency)
            ;

            foreach ($fees as $fee) {
                $email->price()->fee($fee['name'], $fee['value']);
            }
        }
        $this->parseFlight($email);
        $this->parseHotel($email);

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
        foreach ($this->detectSubject as $re) {
            if (stripos($headers["subject"], $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//*[" . $this->contains($this->detectCompany) . "]")->length === 0) {
            return false;
        }

        $body = $this->http->Response['body'];

        foreach ($this->detectBody as $detectBody) {
            if (strpos($body, $detectBody) !== false) {
                return true;
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

    private function parseHotel(Email $email)
    {
        $h = $email->add()->hotel();

        $h->program()->code('sandals');

        // General
        $travellersText = $this->tdXpath("Vacation Information", "Guest Names:");
        $travellers = preg_replace("#^\s*(Mr|Mrs|Ms|---)\.\s*#", '', array_filter(explode(";", $travellersText)));

        $h->general()
            ->noConfirmation()
            ->travellers($travellers)
            ->date($this->normalizeDate($this->tdXpath("Booking Information", "Booked Date:")))
        ;

        // Hotel
        $h->hotel()
            ->name($this->tdXpath("Vacation Information", "Resort:"))
            ->noAddress()
        ;

        // Booked
        $h->booked()
            ->checkIn($this->normalizeDate($this->tdXpath("Vacation Information", "Arrival Date:")))
            ->checkOut($this->normalizeDate($this->tdXpath("Vacation Information", "Departure Date:")))
            ->guests($this->tdXpath("Booking Information", "No. of Adults:"))
        ;

        if (!empty($this->http->FindSingleNode("//*[" . $this->eq($this->t("Children:")) . "]"))) {
            $h->booked()
                ->kids($this->tdXpath("Booking Information", "Children:"))
            ;
        }

        // Rooms
        $h->addRoom()
            ->setType($this->tdXpath("Vacation Information", "Accommodation:"))
        ;

        return $email;
    }

    /**
     * @param string $block Title of table
     * @param string $paramName Name of param
     *
     * @return string
     */
    private function tdXpath(string $block, string $paramName): ?string
    {
        $xpathRow = '(self::table or self::div)';

        return $this->http->FindSingleNode("//tr[ not(.//tr) and descendant::text()[{$this->eq($this->t($block))}] ]/ancestor::*[ {$xpathRow} and following-sibling::*[{$xpathRow} and normalize-space()] ][1]/following-sibling::*[{$xpathRow}]/descendant::td[not(.//td) and {$this->eq($this->t($paramName))}]/following-sibling::td[normalize-space()][1]");
    }

    private function parseFlight(Email $email)
    {
        if (empty($this->http->FindSingleNode("(//*[" . $this->starts($this->t("Flight Information")) . "])[1]"))) {
            return $email;
        }
        $f = $email->add()->flight();

        // General
        $gdsNumber = $this->http->FindSingleNode("//text()[" . $this->eq($this->t("Flight Information")) . "]//following::tr[normalize-space() and not(.//tr)][position()<7]/td[" . $this->starts($this->t("GDS Number")) . "]/following::td[normalize-space()][1]", null, true, "#^\s*([A-Z\d]{5,7})\s*$#");

        if (!empty($gdsNumber)) {
            $f->general()
                ->confirmation($gdsNumber, $this->t("GDS Number"));
        }

        $conf = $this->http->FindSingleNode("//text()[" . $this->eq($this->t("Flight Information")) . "]/following::td[" . $this->starts($this->t("Airline Record Locator")) . "]/following-sibling::td[normalize-space()][1]", null, true, "#^\s*([A-Z\d]{5,7})\s*$#");

        if (!empty($conf)) {
            $f->general()
                ->confirmation($conf, 'Airline Record Locator');
        } else {
            $f->general()
                ->noConfirmation();
        }

        $f->general()
            ->date($this->normalizeDate($this->tdXpath("Booking Information", "Booked Date:")));

        $travellers = $this->http->FindNodes("//text()[" . $this->contains($this->t("Flight Information")) . "]/following::tr[normalize-space() and not(.//tr)][position()<7]/td[" . $this->eq($this->t("Guest Name(s):")) . "]/ancestor::tr[1]/following-sibling::tr/descendant::text()[normalize-space()]");

        foreach ($travellers as $trav) {
            if (preg_match("#^[A-Za-z\. \-]{3,}$#", $trav)) {
                $f->general()->traveller(trim($trav));
            } else {
                break;
            }
        }

        // Segments
        $xpath = "//text()[" . $this->eq($this->t("Departing:")) . "]/ancestor::tr[2]";
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $s = $f->addSegment();

            $date = null;

            // Airline
            $info = $this->http->FindSingleNode(".//text()[" . $this->contains($this->t("FLIGHT #")) . "]/ancestor::td[normalize-space()][1]", $root);

            if (preg_match("#^\s*(?<al>[^|]+)\|\s*" . $this->preg_implode($this->t("FLIGHT #")) . "\s*(?<fn>\d{1,5})\s*\|\s*(?<date>[^|]+)\s*\|\s*" . $this->preg_implode($this->t("SEATS")) . "\s*(?<seats>[A-Z\d;\s]+)\s*(\||$)#", $info, $m)
                || preg_match("#^\s*(?<al>[^|]+)\|\s*" . $this->preg_implode($this->t("FLIGHT #")) . "\s*(?<fn>\d{1,5})\s*\|\s*(?<date>[^|]+)\s*(\||$)#", $info, $m)) {
                $s->airline()
                    ->name($m['al'])
                    ->number($m['fn'])
                ;
                $date = $m['date'];

                if (isset($m['seats'])) {
                    $seatsAll = array_filter(explode(';', $m['seats']));

                    if (!empty($seatsAll)) {
                        $seats = array_filter(array_map(function ($v) {
                            if (preg_match("#^\d{1,3}[A-Z]$#", trim($v))) {
                                return trim($v);
                            } else {
                                return false;
                            }
                        }, $seatsAll));

                        if (count($seatsAll) == count($seats)) {
                            $s->extra()->seats($seats);
                        }
                    }
                }
            }
            // Departure
            $depart = implode("\n", $this->http->FindNodes(".//text()[" . $this->eq($this->t("Departing:")) . "]/ancestor::td[normalize-space()][1]//text()[normalize-space()]", $root));

            if (preg_match("#:\s*\n\s*(?<name>.+)(?:\,\s|\s\()(?<code>[A-Z]{3})\)?\s*\n\s*(?<time>\d{1,2}:\d{1,2}.*)#", $depart, $m)) {
                $s->departure()
                    ->code($m['code'])
                    ->name(trim($m['name']))
                    ->date($this->normalizeDate($date . ', ' . trim($m['time'])))
                ;
            }

            // Arrival
            $arrival = implode("\n", $this->http->FindNodes(".//text()[" . $this->eq($this->t("Arriving:")) . "]/ancestor::td[normalize-space()][1]//text()[normalize-space()]", $root));

            if (preg_match("#:\s*\n\s*(?<name>.+)(?:\,\s|\s\()(?<code>[A-Z]{3})\)?\s*\n\s*(?<time>\d{1,2}:\d{1,2}.*)#", $arrival, $m)) {
                $s->arrival()
                    ->code($m['code'])
                    ->name(trim($m['name']))
                    ->date($this->normalizeDate($date . ', ' . trim($m['time'])))
                ;
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
        $in = [
            "#^\s*(\d{1,2})-([^\s\d]+)\-(\d{2})\s*$#iu", // 09-Mar-20
            "#^\s*(\d{1,2})-([^\s\d]+)\-(\d{2})\s*\(.+ at (\d{1,2})([ap]m)\)\s*$#iu", // 23-Oct-20 (Check-in is at 3pm); 28-Oct-20 (Check-out is at 11am);
            "#^\s*(\d{1,2})-([^\s\d]+)\-(\d{2})\s*,\s*(\d{1,2}:\d{1,2})\s*$#iu", // 23-Oct-20, 11:00;
        ];
        $out = [
            "$1 $2 20$3",
            "$1 $2 20$3, $4:00 $5",
            "$1 $2 20$3, $4",
        ];
        $str = preg_replace($in, $out, $str);

//        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
//            if ($en = MonthTranslate::translate($m[1], $this->lang))
//                $str = str_replace($m[1], $en, $str);
//        }

        return (!empty($str)) ? strtotime($str) : null;
    }

    /**
     * Formatting over 3 steps:
     * 11 507.00  ->  11507.00
     * 2,790      ->  2790    |    4.100,00  ->  4100,00    |    1'619.40  ->  1619.40
     * 18800,00   ->  18800.00  |  2777,0    ->  2777.0.
     *
     * @param string $s Unformatted string with amount
     * @param string|null $decimals Symbols floating-point when non-standard decimals (example: 1,258.943)
     */
    private function normalizeAmount(string $s, ?string $decimals = null): ?float
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

    private function preg_implode($field)
    {
        if (!is_array($field)) {
            $field = [$field];
        }

        return '(?:' . implode("|", array_map(function ($v) {return preg_quote($v, '#'); }, $field)) . ')';
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
