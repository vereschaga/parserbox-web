<?php

namespace AwardWallet\Engine\recreation\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Common\Event;
use AwardWallet\Schema\Parser\Email\Email;

class OrderReceipt extends \TAccountChecker
{
    public $mailFiles = "recreation/it-562826537.eml, recreation/it-564508485.eml, recreation/it-61570625.eml, recreation/it-61866466.eml, recreation/it-71046542.eml, recreation/it-71705757.eml, recreation/it-71762577.eml, recreation/it-72178406.eml";

    private $detectFrom = [
        '@recreation.gov',
    ];
    private $detectProvider = ['Recreation.gov'];
    private $detectSubject = [
        'Order Receipt',
    ];
    private $detectBody = [
        'en' => [
            'message serves as the receipt for your order',
            'reservation details to make sure you have all the necessary information as you prepare for your trip',
        ],
    ];

    private $lang = 'en';
    private static $dictionary = [
        'en' => [
            'Hi '        => ['Hi ', 'Hello '],
            'Check In:'  => ['Check In:'],
            'Check Out:' => ['Check Out:'],

            'Tour Start:' => ['Tour Start:', 'Reservation Start:', 'Start Date:', 'Entry Date:'],
            'Tour End:'   => ['Tour End:', 'Reservation End:', 'Valid Through:', 'Exit Date:'],
        ],
    ];

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();

        $xpathBold = '(self::b or self::strong or ancestor-or-self::*[contains(@style,"bold")])';

        $email->obtainTravelAgency();

        if ($conf = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Order Number:'))}]/ancestor::*[{$xpathBold}]/following-sibling::node()[normalize-space()]")) {
            $email->ota()->confirmation($conf, rtrim($this->t('Order Number:'), ': '));
        }

        $traveller = $this->http->FindSingleNode("//text()[ancestor::*[{$xpathBold}] and {$this->starts($this->t('Hi '))}]", null, false, "/{$this->opt($this->t('Hi '))}\s*(.+?),/");
        $date = $this->normalizeDate($this->http->FindSingleNode("//text()[{$this->contains($this->t('Order Date:'))}]/ancestor::*[{$xpathBold}]/following-sibling::node()[normalize-space()]"));

        // Hotel
        $nodes = $this->http->XPath->query("//text()[{$this->eq($this->t('Check In:'))}]/ancestor::tr[1]");

        foreach ($nodes as $root) {
            $checkIn = $this->normalizeDate($this->http->FindSingleNode(".//text()[" . $this->eq($this->t("Check In:")) . "]/following::text()[normalize-space()][1]", $root));
            $checkOut = $this->normalizeDate($this->http->FindSingleNode(".//text()[" . $this->eq($this->t("Check Out:")) . "]/following::text()[normalize-space()][1]", $root));

            $name = $this->http->FindSingleNode("./td[2][count(.//text()[normalize-space()])=3]/descendant::text()[normalize-space()][2]", $root);

            if (empty($name)) {
                $name = $this->http->FindSingleNode("./td[2][count(.//text()[normalize-space()])=2]/descendant::text()[normalize-space()][2]", $root);
            }

            if (empty($name)) {
                $name = $this->http->FindSingleNode("./td[2]", $root, true, "/\,(.+)/");
            }

            $place = $this->http->FindSingleNode("./td[2][count(.//text()[normalize-space()])<=3]/descendant::text()[normalize-space()][1]", $root);

            if (empty($place)) {
                $place = $this->http->FindSingleNode("./td[2]", $root, true, "/^(.+)\,/");
            }

            foreach ($email->getItineraries() as $it) {
                if ($it->getType() == 'hotel') {
                    if (
                           !empty($checkIn) && $checkIn == $it->getCheckInDate()
                        && !empty($checkOut) && $checkOut == $it->getCheckOutDate()
                        && !empty($name) && $name == $it->getHotelName()
                    ) {
                        $it->addRoom()->setType($place);

                        continue 2;
                    }
                }
            }

            $h = $email->add()->hotel();

            // General
            $h->general()
                ->noConfirmation()
                ->date($date);

            if (strlen($traveller) !== 1) {
                $h->general()
                    ->traveller($traveller, false);
            }

            $cancellation = implode(' ', $this->http->FindNodes("//text()[starts-with(normalize-space(), 'Changes and Cancellations')]/following::text()[normalize-space()][1]/ancestor::table[1]/descendant::text()[normalize-space()][not(contains(normalize-space(), 'Changes and Cancellations'))]"));

            if (!empty($cancellation)) {
                $h->general()
                    ->cancellation($cancellation);
            }
            // Hotel
            $h->hotel()
                ->name($name . ' Camping')
                ->address($name)
            ;

            // Booked
            $h->booked()
                ->checkIn($checkIn)
                ->checkOut($checkOut)
            ;

            $h->addRoom()->setType($place);
        }
        // Event
        $nodes = $this->http->XPath->query("//text()[{$this->eq($this->t('Tour Start:'))}]/ancestor::tr[1]");

        foreach ($nodes as $root) {
            $startText = $this->http->FindSingleNode(".//text()[" . $this->eq($this->t("Tour Start:")) . "]/following::text()[normalize-space()][1]", $root);
            $start = $this->normalizeDate($startText);
            $endText = $this->http->FindSingleNode(".//text()[" . $this->eq($this->t("Tour End:")) . "]/following::text()[normalize-space()][1]", $root);
            $end = $this->normalizeDate($endText);
            $emptyEnd = false;

            $name = $this->http->FindSingleNode("./td[2][count(.//text()[normalize-space()])=2]/descendant::text()[normalize-space()][2]", $root);

            if (!empty($start) && empty($end)
                && preg_match("/^\s*" . $this->re("/^\s*(.+?)\s*\b\d{1,2}:\d{2}\D*$/", $startText) . "\s*" . preg_replace("/\+00:00$/", 'Z', date('c', strtotime('00:00', $start))) . "\s*$/", $endText)
            ) {
                $emptyEnd = true;
            }

            if (!empty($start) && !empty($end)
            ) {
                if (preg_match("/^\s*" . $this->re("/^\s*(.+?)\s*\b\d{1,2}:\d{2}\D*$/", $startText) . "\s*$/", $endText)
                    || (strtotime('00:00', $start) == strtotime('00:00', $start) && preg_match("/ 1:00 AM\s*$/", $startText) && preg_match("/ 12:59 AM\s*$/", $endText))
                ) {
                    $emptyEnd = true;
                    $end = null;
                }
            }

            foreach ($email->getItineraries() as $it) {
                if ($it->getType() == 'event') {
                    if (
                           !empty($start) && $start == $it->getStartDate()
                        && ((!empty($end) && $end == $it->getEndDate())
                           || ($emptyEnd === true && $it->getNoEndDate() == true))
                        && !empty($name) && $name == $it->getName()
                    ) {
                        continue 2;
                    }
                }
            }

            $ev = $email->add()->event();

            // General
            $ev->general()
                ->noConfirmation()
                ->date($date);

            if (!empty($traveller) && strlen($traveller) !== 1) {
                $ev->general()
                    ->traveller($traveller, false);
            }

            // Place
            $ev->place()
                ->type(Event::TYPE_EVENT)
                ->name($name)
                ->address(preg_replace("# (Ticket|Timed Entry).*#i", '', $name))
            ;

            // Booked
            $ev->booked()
                ->start($start);

            if ($emptyEnd === true) {
                $ev->booked()
                    ->noEnd();
            } else {
                $ev->booked()
                    ->end($end);
            }
        }

        // Price
        $cost = $this->http->FindSingleNode("//td[not(.//td) and " . $this->eq($this->t("Subtotal:")) . "]/following::text()[normalize-space()][1]");

        if (preg_match("#^\s*(?<curr>[^\d\s]{1,5})\s*(?<amount>\d[\d\., ]*)\s*$#", $cost, $m)
            || preg_match("#^\s*(?<amount>\d[\d\., ]*)\s*(?<curr>[^\d\s]{1,5})\s*$#", $cost, $m)) {
            $email->price()
                ->cost($this->amount($m['amount']))
            ;
        }
        $tax = $this->http->FindSingleNode("//text()[" . $this->eq($this->t("Tax:")) . "]/following::text()[normalize-space()][1]");

        if (preg_match("#^\s*(?<curr>[^\d\s]{1,5})\s*(?<amount>\d[\d\., ]*)\s*$#", $tax, $m)
            || preg_match("#^\s*(?<amount>\d[\d\., ]*)\s*(?<curr>[^\d\s]{1,5})\s*$#", $tax, $m)) {
            $email->price()
                ->tax($this->amount($m['amount']))
            ;
        }
        $total = $this->http->FindSingleNode("//text()[" . $this->eq($this->t("Total:")) . "]/following::text()[normalize-space()][1]");

        if (preg_match("#^\s*(?<curr>[^\d\s]{1,5})\s*(?<amount>\d[\d\., ]*)\s*$#", $total, $m)
            || preg_match("#^\s*(?<amount>\d[\d\., ]*)\s*(?<curr>[^\d\s]{1,5})\s*$#", $total, $m)) {
            $email->price()
                ->total($this->amount($m['amount']))
                ->currency($this->currency($m['curr']))
            ;
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->detectEmailFromProvider($headers['from']) !== true) {
            return false;
        }

        return $this->arrikey($headers['subject'], $this->detectSubject) !== false;
    }

    public function detectEmailFromProvider($from)
    {
        return $this->arrikey($from, $this->detectFrom) !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//text()[{$this->contains($this->detectProvider)}]")->length === 0) {
            return false;
        }

        if ($this->assignLang()) {
            return true;
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

    private function assignLang()
    {
        foreach ($this->detectBody as $lang => $value) {
            if ($this->http->XPath->query("//text()[{$this->contains($value)}]")->length > 0) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
    }

    private function contains($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'contains(normalize-space(' . $node . '),"' . $s . '")';
        }, $field)) . ')';
    }

    private function starts($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'starts-with(normalize-space(' . $node . '),"' . $s . '")';
        }, $field)) . ')';
    }

    private function eq($field, $text = 'normalize-space(.)')
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($text) {
            return $text . '="' . $s . '"';
        }, $field)) . ')';
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
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

    private function arrikey($haystack, array $arrayNeedle)
    {
        foreach ($arrayNeedle as $key => $needles) {
            if (is_array($needles)) {
                foreach ($needles as $needle) {
                    if (stripos($haystack, $needle) !== false) {
                        return $key;
                    }
                }
            } else {
                if (stripos($haystack, $needles) !== false) {
                    return $key;
                }
            }
        }

        return false;
    }

    private function normalizeDate($str)
    {
        // $this->logger->debug('$date = '. $str);
        $in = [
            "#^\s*([^\d\s]+)\s+(\d+)\s+(\d{4})\s*$#",
            // Oct 25, 2023 2023-10-25T00:00:00Z
            "#^\s*([^\d\s]+)\s+(\d+)\s+(\d{4})\s*$#", //Wednesday, April 23, 2014 @ 9:00 PM
        ];
        $out = [
            "$2 $1 $3",
        ];
        $str = preg_replace($in, $out, $str);
        // $this->logger->debug('$str = '. $str);

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

    private function amount($price)
    {
        $price = str_replace([',', ' '], '', $price);

        if (is_numeric($price)) {
            return (float) $price;
        }

        return null;
    }

    private function currency($s)
    {
        if ($code = $this->re("#^\s*([A-Z]{3})\s*$#", $s)) {
            return $code;
        }
        $sym = [
            '€' => 'EUR',
            '$' => 'USD',
            '£' => 'GBP',
        ];

        foreach ($sym as $f => $r) {
            if ($s == $f) {
                return $r;
            }
        }

        return null;
    }
}
