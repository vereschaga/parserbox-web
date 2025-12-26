<?php

namespace AwardWallet\Engine\tleaders\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class HotelConfirmation extends \TAccountChecker
{
    public $mailFiles = "tleaders/it-87186582.eml, tleaders/it-87264241.eml, tleaders/it-87269512.eml";
    public $lang = "en";

    public static $dictionary = [
        "en" => [
            'Confirmation Invoice' => ['Confirmation Invoice', 'Agent Invoice'],
            "Supplier Con. #"      => ["Supplier Con. #", "Confirmation #"],
        ],
    ];

    private $detectFrom = 'travelleaders.com';
    private $detectCompany = 'travelleaders.com';

    private $detectSubject = [
        // en
        "Order status email : Confirmation#",
        "Hotel Voucher Email",
    ];

    private $detectBody = [
        "en" => ["Hotel Reservation Confirmation", "YOUR TRIP IS BOOKED!", "It is our pleasure to confirm that your booking has been completed"],
    ];

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
//        foreach ($this->detectBody as $lang => $detectBody){
//            if ($this->http->XPath->query("//text()[".$this->contains($detectBody)."]")->length > 0) {
//                $this->lang = $lang;
//                break;
//            }
//        }

        $type = '';

        if (!empty($this->http->FindSingleNode("//text()[" . $this->starts($this->t("Confirmation Invoice")) . "]"))) {
            // Jun 01 2021 - Jun 07 2021 | 6 Nights
            $this->parseHtml1($email);
            $type = '1';
        } else {
            // Aug 23 2019 - Aug 26 2019 | Room, 2 Twin Beds, Non Smoking-Free Breakfast | 3 Nights
            $this->parseHtml2($email);
            $type = '2';
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . $type . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->detectFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (stripos($headers["from"], $this->detectFrom) === false) {
            return false;
        }

        foreach ($this->detectSubject as $dSubject) {
            if (stripos($headers["subject"], $dSubject) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//a[" . $this->contains($this->detectCompany, '@href') . "] | //img[" . $this->contains($this->detectCompany, '@src') . "] | //*[" . $this->contains($this->detectCompany) . "]")->length === 0) {
            return false;
        }

        foreach ($this->detectBody as $detectBody) {
            if ($this->http->XPath->query("//text()[" . $this->contains($detectBody) . "]")->length > 0) {
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
        return 2 * count(self::$dictionary);
    }

    private function parseHtml1(Email $email)
    {
        $email->obtainTravelAgency();

        $conf = $this->http->FindSingleNode("//text()[" . $this->starts($this->t("Your Trip ID")) . "]", null, true,
            "/^\s*" . $this->preg_implode($this->t("Your Trip ID")) . "[\s\-]+(\d+)/");
        $email->ota()
            ->confirmation($conf);

        // Price
        $total = $this->http->FindSingleNode("//text()[" . $this->eq($this->t("Grand Total:")) . "]/ancestor::td[1][" . $this->starts($this->t("Grand Total:")) . "]", null, true,
            "/^\s*" . $this->preg_implode($this->t("Grand Total:")) . "\s*(.+)/");

        if (preg_match("/^\s*([A-Z]{3})\s*(\d[\d., ]*)\s*$/", $total, $m)) {
            $email->price()
                ->total(PriceHelper::cost($m[2]))
                ->currency($m[1]);
        }

        $xpath = "//text()[" . $this->contains($this->t("Night")) . " and contains(., '|')]/ancestor::tr[1][count(preceding-sibling::tr[normalize-space()]) = 2]";
//        $this->logger->debug('$xpath = '.print_r( $xpath,true));
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $h = $email->add()->hotel();

            // Hotel
            $h->hotel()
                ->name($this->http->FindSingleNode("preceding-sibling::tr[normalize-space()][2]", $root))
                ->address($this->http->FindSingleNode("preceding-sibling::tr[normalize-space()][1]", $root))
            ;

            // Booked
            $h->booked()
                ->checkIn($this->normalizeDate(
                    $this->http->FindSingleNode(".", $root, true, "/^(.+?) - .+? \| \d+/")))
                ->checkOut($this->normalizeDate(
                    $this->http->FindSingleNode(".", $root, true, "/^.+? - (.+?) \| \d+/")))
            ;

            $xpathRooms = "./ancestor::tr[1]/following-sibling::tr[normalize-space()]";
            $roomNodes = $this->http->XPath->query($xpathRooms, $root);
            $guests = 0;
            $kids = 0;
            $baseFare = 0.0;
            $taxes = 0.0;
            $total = 0.0;
            $currency = null;

            foreach ($roomNodes as $rRoot) {
                if (empty($this->http->FindSingleNode("self::*[" . $this->starts($this->t("Room #")) . "]", $rRoot))) {
                    break;
                }

                // General
                $h->general()
                    ->confirmation($this->http->FindSingleNode(".//text()[" . $this->eq($this->t("Supplier Con. #")) . "]/following::text()[normalize-space()][1]", $rRoot))
                    ->status($this->http->FindSingleNode(".//text()[" . $this->eq($this->t("Booking Status")) . "]/following::text()[normalize-space()][1]", $rRoot))
                    ->traveller($this->http->FindSingleNode(".//text()[" . $this->eq($this->t("Supplier Con. #")) . "]/ancestor::td[1]/following-sibling::td[normalize-space()][1]/descendant::text()[normalize-space()][1][following::text()[normalize-space()][1][" . $this->contains($this->t("Adult")) . "]]", $rRoot), true)
                ;

                $cancelXpath = ".//text()[" . $this->eq($this->t("Cancellation Policies")) . "]/following::text()[normalize-space()][1]/ancestor::table[1][not(.//text()[" . $this->eq($this->t("Cancellation Policies")) . "])]";
                $cancellation = $this->http->FindSingleNode($cancelXpath, $rRoot);

                if (!empty($cancellation)) {
                    $h->general()
                        ->cancellation($cancellation);
                }

                $deadline = $cancellation = $this->http->FindSingleNode($cancelXpath . "//td[" . $this->eq($this->t("Free cancellation")) . "]/preceding-sibling::td[1]", $rRoot);

                if (preg_match("/ to (.+)/u", $deadline, $m)) {
                    $h->booked()
                        ->deadline(strtotime($m[1]));
                }

                // Booked
                $guests += $this->http->FindSingleNode(".//text()[" . $this->eq($this->t("Supplier Con. #")) . "]/ancestor::td[1]/following-sibling::td[normalize-space()][1]/descendant::text()[" . $this->contains($this->t("Adult")) . "]",
                        $rRoot, true, "/^\s*(\d+)\b/") ?? 0;
                $kids += $this->http->FindSingleNode(".//text()[" . $this->eq($this->t("Supplier Con. #")) . "]/ancestor::td[1]/following-sibling::td[normalize-space()][1]/descendant::text()[" . $this->contains($this->t("Child")) . "]",
                        $rRoot, true, "/\b(\d+)\s*Child/") ?? 0;

                // Rooms
                $h->addRoom()
                    ->setType($this->http->FindSingleNode(".//text()[" . $this->starts($this->t('Room #')) . "]", $rRoot,true,
                        "/^" . $this->preg_implode($this->t('Room #')) . "\d+ - (.+)/"));

                // Price
                $baseFareText = $this->http->FindSingleNode(".//td[" . $this->eq($this->t("Total base fare")) . "]/following-sibling::td[normalize-space()][1]", $rRoot);

                if (preg_match("#^\s*([A-Z]{3})\s*(\d[\d., ]*)\s*$#", $baseFareText, $m)) {
                    $baseFare += PriceHelper::cost($m[2]);
                    $currency = $m[1];
                }
                $taxesText = $this->http->FindSingleNode(".//td[" . $this->eq($this->t("Total Taxes & Fees")) . "]/following-sibling::td[normalize-space()][1]", $rRoot);

                if (preg_match("#^\s*([A-Z]{3})\s*(\d[\d., ]*)\s*$#", $taxesText, $m)) {
                    $taxes += PriceHelper::cost($m[2]);
                    $currency = $m[1];
                }
                $totalText = $this->http->FindSingleNode(".//td[" . $this->eq($this->t("Room Price")) . "]/following-sibling::td[normalize-space()][1]", $rRoot);

                if (preg_match("#^\s*([A-Z]{3})\s*(\d[\d., ]*)\s*$#", $totalText, $m)) {
                    $total += PriceHelper::cost($m[2]);
                    $currency = $m[1];
                }

                $this->detectDeadLine($h);
            }

            if (!empty($guests)) {
                $h->booked()->guests($guests);
            }

            if (!empty($kids)) {
                $h->booked()->kids($kids);
            }

            if (!empty($baseFare)) {
                $h->price()
                    ->cost($baseFare);
            }

            if (!empty($taxes)) {
                $h->price()
                    ->tax($taxes);
            }

            if (!empty($total)) {
                $h->price()
                    ->total($total);
            }

            if (!empty($currency)) {
                $h->price()
                    ->currency($currency);
            }
        }

        return $email;
    }

    private function parseHtml2(Email $email)
    {
        $email->obtainTravelAgency();

        $conf = $this->http->FindSingleNode("(//text()[" . $this->starts($this->t("Trip ID")) . "])[1]", null, true,
            "/^\s*" . $this->preg_implode($this->t("Trip ID")) . "[\s\-]+(\d+)/");
        $email->ota()
            ->confirmation($conf);

        $xpath = "//text()[" . $this->contains($this->t("Night")) . " and contains(., '|')]/ancestor::tr[1][count(preceding-sibling::tr[normalize-space()]) = 2]";
//        $this->logger->debug('$xpath = '.print_r( $xpath,true));
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $h = $email->add()->hotel();
            // Hotel
            $h->hotel()
                ->name($this->http->FindSingleNode("./preceding-sibling::tr[normalize-space()][2]", $root))
                ->address($this->http->FindSingleNode("./preceding-sibling::tr[normalize-space()][1]", $root));

            $dopXpath = "./ancestor::tr[1]/following-sibling::tr[normalize-space()]";

            // General
            $h->general()
                ->confirmation($this->http->FindSingleNode($dopXpath . "[1]//text()[" . $this->eq($this->t("Confirmation #")) . "]/following::text()[normalize-space()][1]", $root))
                ->status($this->http->FindSingleNode("./following-sibling::tr[normalize-space()][1][" . $this->starts($this->t("Booking Status")) . "]",
                    $root, true, "/" . $this->preg_implode($this->t("Booking Status")) . "[\s\-]+(.+)/"))//            ->traveller($this->http->FindSingleNode(".//text()[" . $this->eq($this->t("Supplier Con. #")) . "]/ancestor::td[1]/following-sibling::td[normalize-space()][1]/descendant::text()[normalize-space()][1][following::text()[normalize-space()][1][" . $this->contains($this->t("Adult")) . "]]", $rRoot),true)
            ;
            $travellers = implode("\n",
                $this->http->FindNodes($dopXpath . "[1]//text()[" . $this->eq($this->t("Confirmation #")) . "]/ancestor::td[1]/preceding-sibling::td[1]//text()[normalize-space()][1]", $root));

            if (preg_match("/([\s\S]+?)\n.*Adult/", $travellers, $m)) {
                $h->general()->travellers(explode("\n", trim($m[1])), true);
            }

            $cancelXpath = $dopXpath . "[2]//text()[" . $this->eq($this->t("Cancellation Policies")) . "]/ancestor::p[1]/following-sibling::p[normalize-space()][1]";
            $cancellation = $this->http->FindSingleNode($cancelXpath, $root);

            if (!empty($cancellation)) {
                $h->general()
                    ->cancellation($cancellation);
            }

            if (preg_match("/\b(\d+) *Adult/", $travellers, $m)) {
                $h->booked()->guests($m[1]);
            }

            if (preg_match("/\b(\d+) *Child/", $travellers, $m)) {
                $h->booked()->kids($m[1]);
            }

            // Booked
            $h->booked()
                ->checkIn($this->normalizeDate(
                    $this->http->FindSingleNode(".", $root, true, "/^([^|]+?) - [^|]+? \| /")))
                ->checkOut($this->normalizeDate(
                    $this->http->FindSingleNode(".", $root, true, "/^[^|]+? - ([^|]+?) \| /")));

            // Rooms
            $h->addRoom()
                ->setType($this->http->FindSingleNode(".", $root, true, "/^[^|]+? - [^|]+? \| ([^|]+) \|/"));
        }

        return $email;
    }

    private function detectDeadLine(\AwardWallet\Schema\Parser\Common\Hotel $h)
    {
        if (empty($cancellationText = $h->getCancellation())) {
            return;
        }

        if (preg_match("/Cancellations made (?<priorD>\d+ hours?) or more prior to check-in will receive a full refund\./i",
            $cancellationText, $m)
        ) {
            $h->booked()
                ->deadlineRelative($m['priorD']);
        }
    }

    private function getField($field, $regexp = null, $n = 1)
    {
        return $this->http->FindSingleNode("(//text()[{$this->eq($field)}]/following::text()[normalize-space(.)][1])[{$n}]", null, true, $regexp);
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
//        $this->logger->debug('$str = '.print_r( $str,true));

        $in = [
            //            "#^[\w|\D]+\s+(\d+)\s+(\D+)\s+(\d{4})$#",
        ];
        $out = [
            //            "$1 $2 $3",
        ];
        $str = preg_replace($in, $out, $str);
//        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
//            if ($en = MonthTranslate::translate($m[1], $this->lang))
//                $str = str_replace($m[1], $en, $str);
//        }

        return strtotime($str);
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

    private function contains($field, $text = 'normalize-space(.)')
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($text) { return 'contains(' . $text . ',"' . $s . '")'; }, $field)) . ')';
    }

    private function preg_implode($field)
    {
        if (!is_array($field)) {
            $field = [$field];
        }

        return '(?:' . implode("|", array_map(function ($v) {return preg_quote($v, '#'); }, $field)) . ')';
    }
}
