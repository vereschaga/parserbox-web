<?php

namespace AwardWallet\Engine\qantas\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class HotelsBookingConfirmation extends \TAccountChecker
{
    public $mailFiles = "qantas/it-1890751.eml, qantas/it-40187875.eml, qantas/it-40240687.eml, qantas/it-5921634.eml, qantas/it-71830321.eml, qantas/it-72255854.eml, qantas/it-74024502.eml, qantas/it-74079285.eml, qantas/it-74158922.eml, qantas/it-74447740.eml";
    public static $dictionary = [
        "en" => [
            "Booking reference number"         => ["Booking reference number", "Qantas Hotels reference", "Jetstar Hotels reference"],
            'Hotel'                            => ['Hotel', 'Hotel details'],
            'Awarded to Frequent Flyer Number' => ['Awarded to Frequent Flyer Number', 'Awarded to Frequent Flyer number'],
            'Booking reference:'               => ['Booking reference:', 'Booking reference'],
        ],
    ];

    private static $detectProvider = [
        'qantas' => [
            'from'    => 'qantas.booking@hooroo.com',
            'subject' => [
                // check the 'detectEmailByHeaders' and detect provider in ParsePlanEmailExternal if the subject does not contain the provider name
                'Qantas Hotels Booking',
            ],
            'body' => [
                'Qantas Hotels reference',
                'Qantas Hotels booking amendment',
            ],
        ],
        'jetstar' => [
            'from'    => 'jetstar.booking@hooroo.com',
            'subject' => [
                // check the 'detectEmailByHeaders' and detect provider in ParsePlanEmailExternal if the subject does not contain the provider name
                'Jetstar Hotels Booking',
            ],
            'body' => [
                'Jetstar Hotels reference',
                'your accommodation with Jetstar',
            ],
        ],
    ];

    private $detectBody = [
        "en" => ["Hotel Booking Confirmation", "Hotel Booking Amendment", "Hotels Booking Confirmation"],
    ];

    private $providerCode;
    private $lang = "en";
    private $subject;

    public function detectEmailFromProvider($from)
    {
        return strpos($from, 'qantas.booking@hooroo.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        foreach (self::$detectProvider as $code => $provider) {
            if (!empty($provider['from']) && $this->striposAll($headers["from"], $provider['from']) !== false) {
                $this->providerCode = $code;
            }

            if (!empty($provider['subject']) && $this->striposAll($headers["subject"], $provider['subject']) !== false) {
                $this->providerCode = $code;

                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $body = $this->http->Response['body'];

        foreach (self::$detectProvider as $code => $provider) {
            $foundProvider = false;

            if (!empty($provider['body'])) {
                foreach ($provider['body'] as $prov) {
                    if (strpos($body, $prov) !== false) {
                        $this->providerCode = $code;
                        $foundProvider = true;

                        break;
                    }
                }
            }

            if ($foundProvider === false) {
                continue;
            }

            foreach ($this->detectBody as $detectBody) {
                foreach ($detectBody as $dBody) {
                    if (stripos($body, $dBody) !== false) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->date = strtotime($parser->getHeader('date'));
        $this->subject = $parser->getSubject();

        $body = $this->http->Response['body'];

        foreach ($this->detectBody as $lang => $detectBody) {
            foreach ($detectBody as $dBody) {
                if (stripos($body, $dBody) !== false) {
                    $this->lang = $lang;

                    break;
                }
            }
        }

        $type = '';

        if (!empty($this->http->FindSingleNode("//text()[" . $this->contains($this->t("Your booking details")) . " or " . $this->contains($this->t("Cancellation details")) . "]"))) {
            $this->parseHtml2($email);
            $type = '2';
        } else {
            $this->parseHtml1($email);
            $type = '1';
        }

        if (empty($this->providerCode)) {
            foreach (self::$detectProvider as $code => $provider) {
                if (!empty($provider['from']) && $this->striposAll($parser->getCleanFrom(), $provider['from']) !== false) {
                    $this->providerCode = $code;

                    break;
                }

                if (!empty($provider['subject']) && $this->striposAll($parser->getSubject(), $provider['subject']) !== false) {
                    $this->providerCode = $code;

                    break;
                }

                if (!empty($provider['subject']) && $this->http->XPath->query("//text()[" . $this->contains($provider['subject']) . "]")->length > 0) {
                    $this->providerCode = $code;

                    break;
                }

                if (!empty($provider['body']) && $this->http->XPath->query("//text()[" . $this->contains($provider['body']) . "]")->length > 0) {
                    $this->providerCode = $code;

                    break;
                }
            }
        }

        if (!empty($this->providerCode)) {
            $email->setProviderCode($this->providerCode);
        }
        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang) . $type);

        return $email;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary) * 2;
    }

    public static function getEmailProviders()
    {
        return array_keys(self::$detectProvider);
    }

    private function parseHtml1(Email $email)
    {
        $this->logger->debug(__METHOD__);
        // Travel Agency
        $email->ota()
            ->confirmation($this->nextText($this->t("Booking reference number")));

        $h = $email->add()->hotel();

        // General
        $h->general()
            ->noConfirmation()
            ->traveller($this->nextText($this->t("Guest name")));

        $cancellation = $this->re("#Need to change or cancel your booking\?\s*(.+?)(\s*Use of Qantas Points|$)#", implode(" ", $this->http->FindNodes("//text()[normalize-space() = 'Need to change or cancel your booking?']/following::text()[not(ancestor::a)][normalize-space()][1]/ancestor::tr[1]//text()[normalize-space()][not(normalize-space() = 'CANCEL BOOKING')]")));

        if (strlen($cancellation) > 2000 && strpos($cancellation, 'The cost of the payment') !== false) {
            $cancellation = strstr($cancellation, 'The cost of the payment', true);
        }
        $h->general()
            ->cancellation($cancellation);

        $dateReservation = $this->http->FindSingleNode("//text()[normalize-space()='Date of issue:']/following::text()[normalize-space()][1]");

        if (!empty($dateReservation)) {
            $h->general()
                ->date(strtotime($dateReservation));
        }

        // Hotel
        $h->hotel()
            ->name($this->http->FindSingleNode("//text()[" . $this->eq("Hotel") . "]/ancestor::td[1]/following-sibling::td[2]/descendant::text()[normalize-space(.)][1]"))
            ->address(implode(", ", $this->http->FindNodes("//text()[" . $this->eq("View property information & map") . "]/ancestor::tr[1]/following-sibling::tr[normalize-space(.)][1]/descendant::text()[normalize-space(.)]")))
        ;

        // Booked
        $h->booked()
            ->checkIn(strtotime($this->normalizeDate($this->nextText($this->t("Check-in")) . ', ' . $this->http->FindSingleNode("//text()[" . $this->contains("Check-in time:") . "]/following::text()[normalize-space(.)][1]"))))
            ->checkOut(strtotime($this->normalizeDate($this->nextText($this->t("Check-out")) . ', ' . $this->http->FindSingleNode("//text()[" . $this->contains("Check-out time:") . "]/following::text()[normalize-space(.)][1]"))))
            ->guests($this->re("#(\d+)\s*adult#", $this->nextText($this->t("Guests"))))
            ->kids($this->re("#(\d+)\s*child#", $this->nextText($this->t("Guests"))), true, true)
            ->rooms($this->re("#(\d+)\s+x\s+#", $this->nextText($this->t("Room(s)"))))
        ;

        $this->detectDeadLine($h);

        // Rooms
        $h->addRoom()
            ->setType($this->re("#\d+\s+x\s+(.*?):#", $this->nextText($this->t("Room(s)"))))
        ;

        // Price
        $h->price()
            ->total($this->amount($this->http->FindSingleNode("//text()[" . $this->starts("Total (") . "]/ancestor::*[name()='td' or name()='th'][1]/following-sibling::*[normalize-space(.)][last()]")))
            ->currency($this->http->FindSingleNode("//text()[" . $this->starts("Total (") . "]", null, true, "#([A-Z]{3})\)#"))
        ;

        $spentAwards = $this->http->FindSingleNode("//text()[" . $this->starts("Total (") . "]/ancestor::*[name()='td' or name()='th'][1]/following-sibling::*[1]");

        if (!empty($spentAwards)) {
            $h->price()
                ->spentAwards($spentAwards);
        }

        // Program
        $account = $this->nextText($this->t("Frequent Flyer Number"));

        if (!empty($account)) {
            $email->ota()->account($account, false);
        }
        $email->ota()
            ->earnedAwards($this->nextText("Qantas Points Earned"), true, true);
    }

    private function parseHtml2(Email $email)
    {
        //$this->logger->warning();
        $this->logger->debug(__METHOD__);
        // Travel Agency
        $otaConfirmation = $this->nextText($this->t("Booking reference:"));

        if (empty($otaConfirmation)) {
            $otaConfirmation = $this->re("/Hotels Booking Confirmation\:\s*([A-Z\d]{6,})/", $this->subject);
        }

        $email->ota()
            ->confirmation($otaConfirmation);

        // Program
        $account = $this->http->FindSingleNode("//text()[" . $this->starts($this->t("Awarded to Frequent Flyer Number")) . "]", null, true, "#" . $this->preg_implode($this->t("Awarded to Frequent Flyer Number")) . "\s*(\S+)\b#");

        if (!empty($account)) {
            $email->ota()->account($account, false);
        }
        $eardenAwards = $this->http->FindSingleNode("//text()[" . $this->contains($this->t("Qantas points earned")) . "]", null, true, "#^\s*([\d,. ]+?)\s+" . $this->preg_implode($this->t("Qantas points earned")) . "#");

        if (empty($eardenAwards)) {
            $eardenAwards = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'You have earned')]", null, true, "/^You have earned\s*([\d\,]+)/");
        }

        if (!empty($eardenAwards)) {
            $email->ota()
                ->earnedAwards($eardenAwards, true, true);
        }

        $h = $email->add()->hotel();

        // General
        $h->general()
            ->noConfirmation()
            ->traveller($this->http->FindSingleNode("//text()[" . $this->starts($this->t("Reserved for")) . "]", null, true, "#" . $this->preg_implode($this->t("Reserved for")) . "\s*(.+)#"))
        ;
        $cancellation = $this->http->FindSingleNode("//tr[not(.//tr) and " . $this->starts("Changes and cancellations are ") . " and " . $this->contains("permitted") . "]/following-sibling::tr[1]");

        if (strlen($cancellation) > 2000 && strpos($cancellation, 'Use of voucher If you use a') !== false) {
            $cancellation = strstr($cancellation, 'Use of voucher If you use a', true);
        }

        if (strlen($cancellation) >= 2000) {
            $cancellation = $this->http->FindSingleNode("//text()[normalize-space()='Changes and cancellations are permitted']/following::text()[starts-with(normalize-space(), 'Free cancellation up to')]");
        }
        $h->general()
            ->cancellation($cancellation, true, true);

        if (!empty($this->http->FindSingleNode("(//*[" . $this->eq($this->t("Cancellation details")) . "])[1]"))) {
            $h->general()
                ->status('Cancelled')
                ->cancelled();
        }

        // Hotel
        $name = $this->http->FindSingleNode("//text()[" . $this->eq("Hotel") . "]/ancestor::tr[1]/following-sibling::tr[1]");

        if (empty($name)) {
            $name = $this->http->FindSingleNode("//text()[" . $this->eq($this->t('Hotel')) . "]/ancestor::tr[1]/following::tr[1]");
        }

        $address = $this->http->FindSingleNode("//text()[" . $this->eq($this->t("Hotel")) . "]/ancestor::tr[1]/following-sibling::tr[2]");

        if (empty($address)) {
            $address = $this->http->FindSingleNode("//text()[" . $this->eq($this->t("Email:")) . "]/ancestor::tr[1]/preceding::tr[1]",
                null, true, "/^(.+)Get directions/");
        }

        if (empty($address)) {
            $address = $this->http->FindSingleNode("//text()[" . $this->eq($this->t("Get directions")) . "]/ancestor::tr[1][not(" . $this->starts($this->t("Get directions")) . ")]",
                null, true, "/^(.+?)Get directions/");
        }

        $address = str_replace(['Address:'], '', $address);

        if ($h->getCancelled() !== true) {
            $h->hotel()
                ->name($name)
                ->address($address);
        } else {
            $h->hotel()
                ->name($this->http->FindSingleNode("//text()[" . $this->starts("Your booking at") . " and " . $this->contains("has been cancelled") . "]", null, true,
                    "/Your booking at (.+?) has been cancelled/"))
                ->noAddress();
        }
        $phone = $this->http->FindSingleNode("//text()[normalize-space()='Phone:']/following::text()[normalize-space()][1]");

        if (!empty($phone)) {
            $h->hotel()
                ->phone($phone);
        }

        // Booked

        $h->booked()
            ->checkIn(strtotime($this->normalizeDate(
                    $this->http->FindSingleNode("//tr[not(.//tr) and " . $this->eq("Check-in date") . "]/following-sibling::tr[1]") . ', '
                    . $this->http->FindSingleNode("//tr[not(.//tr) and " . $this->eq("Check-in date") . "]/following-sibling::tr[2]", null, true, "#^\D*(\d+:\d+.*)#"))))
            ->checkOut(strtotime($this->normalizeDate(
                    $this->http->FindSingleNode("//tr[not(.//tr) and " . $this->eq("Check-out date") . "]/following-sibling::tr[1]") . ', '
                    . $this->http->FindSingleNode("//tr[not(.//tr) and " . $this->eq("Check-out date") . "]/following-sibling::tr[2]", null, true, "#^\D*(\d+:\d+.*)#"))))
            ->guests($this->re("#(\d+)\s*adult#", $this->nextText($this->t("Guests"))))
            ->kids($this->re("#(\d+)\s*child#", $this->nextText($this->t("Guests"))), true, true)
        ;

        if ($h->getCancelled() === true) {
            return $email;
        }

        $room = $this->re("#(\d+)\s+x\s+#", $this->nextText($this->t("Room")));

        if (empty($room)) {
            $room = $this->http->FindSingleNode("//text()[normalize-space()='Email:']/following::text()[normalize-space()][string-length()> 2][2]",
                null, true, "/^(\d+)\s*x\b/");
        }

        if (empty($room)) {
            $room = $this->http->FindSingleNode("//text()[normalize-space()='Get directions']/following::tr[not(" . $this->starts('Phone:') . ")][normalize-space()][1]",
                null, true, "/^(\d+)\s*x\b/");
        }

        $h->booked()
            ->rooms($room);

        $this->detectDeadLine($h);

        // Rooms
        $roomType = $this->re("#\d+\s+x\s+(.+)#", $this->http->FindSingleNode("//tr[not(.//tr) and " . $this->eq("Room") . "]/following-sibling::tr[1]"));

        if (empty($roomType)) {
            $roomType = $this->http->FindSingleNode("//text()[normalize-space()='Email:']/following::text()[normalize-space()][string-length()> 2][2]", null, true, "/^\d+\s*x\s*(.+)/");
        }

        if (empty($roomType)) {
            $roomType = $this->http->FindSingleNode("//text()[normalize-space()='Get directions']/following::tr[not(" . $this->starts('Phone:') . ")][normalize-space()][1]/descendant::text()[normalize-space()][1]", null, true, "/^\s*\d+\s*x\s*(.+)/");
        }
        $h->addRoom()
            ->setType($roomType)
        ;

        // Price
        if ($this->http->XPath->query("//text()[{$this->contains($this->t('Tax invoice / Adjustment note'))}]")->length == 0) {
            $xpathTotal = "//text()[" . $this->starts("Total (") . " and not(ancestor::*[contains(@style, 'display: none') or contains(@style, 'display:none')])]/ancestor::tr[1]/*[normalize-space()]";
            $nodes = $this->http->XPath->query($xpathTotal);

            if ($nodes->length == 0) {
                $prices = $this->http->FindNodes("//text()[" . $this->starts(["Total paid", "Pay later "]) . "]/ancestor::tr[1]/descendant::td[2]");

                if ((!preg_match("/(\+|\bpts\b)/", implode("\n", $prices)))
                    && !empty($this->http->FindSingleNode("//text()[" . $this->eq("Booking total") . "]/following::text()[normalize-space()][2][" . $this->starts(["Total paid"]) . "]"))
                ) {
                    $prices = [$this->http->FindSingleNode("//text()[normalize-space(.) = 'Booking total']/ancestor::tr[1]/descendant::td[2]")];
                }

                $total = 0;
                $currency = '';
                $spentAwards = 0;

                foreach ($prices as $price) {
                    $total += $this->amount($this->re("/(?:^|\+)\D*([\d\.]+)\s*[A-Z]{3}/", $price));
                    $currency = $this->re("/(?:^|\+)\D*[\d\.]+\s*([A-Z]{3})/", $price);
                    $sa = str_replace([',', ' '], '', $this->re("/^([\d\,]+)\s*pts/", $price));

                    if (!empty($sa)) {
                        $spentAwards += $sa;
                    }
                }

                if (!empty($total) && !empty($currency)) {
                    $h->price()
                        ->total($total)
                        ->currency($currency);
                }

                if (!empty($spentAwards)) {
                    $h->price()
                        ->spentAwards($spentAwards);
                }
            }

            foreach ($nodes as $root) {
                $totals[] = $root->nodeValue;
            }
        }

        if (!empty($totals)) {
            if (!empty($this->http->FindSingleNode($xpathTotal . "/preceding::text()[" . $this->contains("Points redeemed") . "]"))) {
                $points = $totals[1];
                $cost = $totals[2];
                $tax = $totals[3];
                $total = $totals[4];
            } else {
                $points = '';
                $cost = $totals[1];
                $tax = $totals[2];
                $total = $totals[3];
            }
            $h->price()
                ->cost($this->amount($cost))
                ->currency($this->re("#([A-Z]{3})\)#", $totals[0]))
                ->total($this->amount($total))
                ->tax($this->amount($tax))
                ->spentAwards((!empty($points)) ? $points . ' points' : null, true, true)
            ;
        }
    }

    private function detectDeadLine(\AwardWallet\Schema\Parser\Common\Hotel $h)
    {
        if (empty($cancellationText = $h->getCancellation())) {
            return;
        }

        if (
               preg_match("#Booking cancellations made at least (?<days>\d{1,2} days?) prior to the local check in date and time will not incur any fees or charges and will be eligible for a full refund#ui", $cancellationText, $m)
        ) {
            $h->booked()->deadlineRelative($m['days']);

            return true;
        }

        if (
                preg_match("#Cancellations or changes made after (?<time>\d{1,2}:\d{1,2}(?:\s?[ap]m)?) \([^\)]*\) on (?<date>.+?) are subject to#i", $cancellationText, $m) //en
        ) {
            $h->booked()
                ->deadline(strtotime($m['date'] . ', ' . $m['time']));

            return true;
        }

        if (preg_match("/Free cancellation up to (\d+) hours before check-in/iu", $cancellationText, $m)
        ) {
            $h->booked()
                ->deadlineRelative($m[1] . ' hours', '00:00');
        }

        if (
            preg_match("#This rate is non-refundable#i", $cancellationText, $m)
            || preg_match("#Non-refundable: Non-refundable unless you are entitled to a refund or other remedy under#i", $cancellationText, $m)
        ) {
            $h->booked()->nonRefundable();
        }
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
        $this->logger->warning('IN-' . $str);
        $year = date("Y", $this->date);
        $in = [
            "#^[^\d\s]+,?\s+(\d+)\s+([^\d\s]+)\,\s+(\d{4}),\s+([\d\:]+)\s*([AP]M)$#i", //Sat, 24 Jun 2017, 2 PM
            "#^\w+\s*(\d+\s*\w+)\,\s*(\d{4})\s*(?:From|Until)\s*([\d\:]+\s*A?P?M)\,\s*$#", //Thu 03 Dec, 2020 From 02:00 PM,
        ];
        $out = [
            "$1 $2 $3, $4:00 $5",
            "$1 $2, $3",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }
        //$this->logger->warning('OUT-'.$str);
        return $str;
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
            '€'=> 'EUR',
            '$'=> 'USD',
            '£'=> 'GBP',
        ];

        if ($code = $this->re("#(?:^|\s)([A-Z]{3})(?:$|\s)#", $s)) {
            return $code;
        }
        $s = $this->re("#([^\d\,\.]+)#", $s);

        foreach ($sym as $f=> $r) {
            if (strpos($s, $f) !== false) {
                return $r;
            }
        }

        return null;
    }

    private function nextText($field, $root = null)
    {
        $rule = $this->eq($field);

        return $this->http->FindSingleNode("(.//text()[{$rule}])[1]/following::text()[normalize-space(.)][1]", $root);
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(" or ", array_map(function ($s) { return "normalize-space(.)=\"{$s}\""; }, $field)) . ')';
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(" or ", array_map(function ($s) { return "starts-with(normalize-space(.), \"{$s}\")"; }, $field)) . ')';
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(" or ", array_map(function ($s) { return "contains(normalize-space(.), \"{$s}\")"; }, $field)) . ')';
    }

    private function preg_implode($field)
    {
        if (!is_array($field)) {
            $field = [$field];
        }

        return '(?:' . implode("|", array_map(function ($v) {return preg_quote($v, '#'); }, $field)) . ')';
    }

    private function striposAll($text, $needle): bool
    {
        if (empty($text) || empty($needle)) {
            return false;
        }

        if (is_array($needle)) {
            foreach ($needle as $n) {
                if (stripos($text, $n) !== false) {
                    return true;
                }
            }
        } elseif (is_string($needle) && stripos($text, $needle) !== false) {
            return true;
        }

        return false;
    }
}
