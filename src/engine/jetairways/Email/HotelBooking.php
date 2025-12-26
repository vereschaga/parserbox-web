<?php

namespace AwardWallet\Engine\jetairways\Email;

use AwardWallet\Schema\Parser\Common\Hotel;
use AwardWallet\Schema\Parser\Email\Email;

class HotelBooking extends \TAccountChecker
{
    public $mailFiles = "jetairways/it-152611368.eml";

    private $detectSubject = [
        // en
        'Thanks! Your Redemption Hotel Booking',
    ];

    private $detectBody = [
        'en' => [
            'Your booking is confirmed!',
            'Below are your cancelled booking details',
        ],
    ];

    public $lang;
    public static $dictionary = [
        'en' => [
        ],
    ];

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '.intermiles.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->detectEmailFromProvider($headers['from']) !== true) {
            return false;
        }

        foreach ($this->detectSubject as $dSubject) {
            if (stripos($headers["subject"], $dSubject) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//a[contains(@href, '.intermiles.com')]")->length === 0) {
            return false;
        }

        foreach ($this->detectBody as $detectBody) {
            if ($this->http->XPath->query("//*[{$this->contains($detectBody)}]")->length > 0) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->parseEmailHtml($email);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
    }

    private function parseEmailHtml(Email $email)
    {
        // Travel Agency
        $email->ota()
            ->confirmation($this->http->FindSingleNode("//text()[".$this->starts($this->t("InterMiles Booking ID"))."]/ancestor::td[1]",
                null, true, "/InterMiles Booking ID\s*:\s*([A-Z\d]{5,})\s*$/"));

        // HOTEL
        $h = $email->add()->hotel();

        // General
        $h->general()
            ->confirmation($this->http->FindSingleNode("//text()[".$this->starts($this->t("Confirmation ID"))."]/ancestor::td[1]",
                null, true, "/Confirmation ID\s*:\s*([A-Z\d]{5,})\s*$/"))
            ->cancellation($this->http->FindSingleNode("//tr[.//strong[{$this->eq($this->t("Cancellation Policy"))}]]/following::tr[normalize-space()][1][not(.//strong)]"), true, true)
        ;
        $travellers = $this->http->FindNodes("//text()[{$this->eq($this->t("Primary guest"))}]/following::text()[normalize-space()][1]");
        if (empty($travellers)) {
            $travellers[] = $this->http->FindSingleNode("//text()[{$this->starts($this->t("Dear "))}]",
                null, true, "/Dear(?: Mr\.?| Ms\.?)? (.+),\s*$/");
        }
        $h->general()
            ->travellers($travellers);
        if ($this->http->FindSingleNode("(//node()[{$this->contains(['Below are your cancelled booking details'])}])[1]")) {
            $h->general()
                ->cancelled()
                ->status('Cancelled');
        }

        // Hotel
        $h->hotel()
            ->name($this->http->FindSingleNode("//text()[".$this->eq($this->t("Address:"))."]/preceding::text()[normalize-space()][1]"))
            ->address($this->http->FindSingleNode("//text()[".$this->eq($this->t("Address:"))."]/following::text()[normalize-space()][1]"))
            ->phone($this->http->FindSingleNode("//text()[".$this->starts($this->t("Phone"))."]/ancestor::td[1]",
                null, true, "/Phone\s*:\s*([\d., \+\-()]{5,20})\s*$/"))
        ;

        // Booked
        $stayPeriod = explode("-", $this->http->FindSingleNode("//text()[{$this->eq($this->t("Stay Period:"))}]/following::text()[normalize-space()][1]"));
        $ciTime = $this->http->FindSingleNode("//text()[{$this->eq($this->t("Check-in time:"))}]/following::text()[normalize-space()][1]");
        $coTime = $this->http->FindSingleNode("//text()[{$this->eq($this->t("Check-out time"))}]/following::text()[normalize-space()][1]");
        if (count($stayPeriod) == 2) {
            $h->booked()
                ->checkIn($this->normalizeDate($stayPeriod[0] . ', ' . $ciTime))
                ->checkOut($this->normalizeDate($stayPeriod[1] . ', ' . $coTime))
            ;
        }

        if ($h->getCancelled() === true) {
            return;
        }
        $h->booked()
            ->rooms($this->http->FindSingleNode("//text()[{$this->eq($this->t("Number of room(s)"))}]/following::text()[normalize-space()][1]"))
            ->guests(array_sum($this->http->FindNodes("//text()[{$this->eq($this->t("Number of guest(s)"))}]/following::text()[normalize-space()][1]",
                null, "/Adult:\s*(\d+)\b/")))
            ->kids(array_sum($this->http->FindNodes("//text()[{$this->eq($this->t("Number of guest(s)"))}]/following::text()[normalize-space()][1]",
                null, "/Child:\s*(\d+)\b/")), true, true)
        ;

        // Rooms
        $h->addRoom()
            ->setType($this->http->FindSingleNode("//text()[{$this->eq($this->t("Room type"))}]/following::text()[normalize-space()][1]"))
        ;

        // Price
        $spent = $this->http->FindSingleNode("//text()[{$this->eq($this->t("InterMiles redeemed"))}]/following::text()[normalize-space()][1]",
            null, true, "/^\s*\d[\d,. ]*\s*$/");
        if (!empty($spent)) {
            $h->price()
                ->spentAwards($spent);
        }
//        $total = $this->http->FindSingleNode("//text()[{$this->eq($this->t("Total price:"))}]/following::text()[normalize-space()][1]");
//        if (preg_match("#^\s*(?<currency>[^\d\s]{1,5})\s*(?<amount>\d[\d\., ]*)\s*$#", $total, $m)
//            || preg_match("#^\s*(?<amount>\d[\d\., ]*)\s*(?<currency>[^\d\s]{1,5})\s*$#", $total, $m)
//        ){
//            $currency = $this->currency($m['currency']);
//            $h->price()
//                ->total(PriceHelper::parse($m['amount'], $currency))
//                ->currency($currency)
//            ;
//        }

        // Deadline
        $this->detectDeadLine($h);

        return true;
    }

    private function detectDeadLine(Hotel $h)
    {
        if (empty($cancellationText = $h->getCancellation())) {
            return;
        }

        if (
            preg_match("/If you cancel from [\d\W]+ *.{0,4} to ([\d\W]+ *.{0,4}), you will be charged InterMiles 0\./u", $cancellationText, $m)
        ) {
            $h->booked()
                ->deadline($this->normalizeDate($m[1]));
        }
    }

    private function t($s)
    {
        if (!isset($this->lang, self::$dictionary[$this->lang], self::$dictionary[$this->lang][$s])) {
            return $s;
        }
        return self::$dictionary[$this->lang][$s];
    }

    private function contains($field, $text = 'normalize-space(.)')
    {
        $field = (array)$field;
        if (count($field) == 0) {
            return 'false()';
        }
        return '(' . implode(' or ', array_map(function ($s) use ($text) {
            return 'contains(' . $text . ',"' . $s . '")';
        }, $field)) . ')';
    }


    private function eq($field)
    {
        $field = (array)$field;
        if (count($field) == 0) {
            return 'false()';
        }
        return '(' . implode(' or ', array_map(function ($s) {
            return 'normalize-space(.)="' . $s . '"';
        }, $field)) . ')';
    }

    private function starts($field)
    {
        $field = (array)$field;
        if (count($field) == 0) {
            return 'false()';
        }
        return '(' . implode(' or ', array_map(function ($s) {
            return 'starts-with(normalize-space(.),"' . $s . '")';
        }, $field)) . ')';
    }

    private function normalizeDate(?string $date): ?int
    {
//        $this->logger->debug('date begin = ' . print_r( $date, true));

        $in = [
            // 13-04-2022 11:59 AM
            '/^\s*(\d+)\s*-\s*(\d+)\s*-\s*(\d{4})\s*,\s*(\d{1,2}:\d{2}(?:\s*[ap]m)?)$/iu',
        ];
        $out = [
            '$1.$2.$3, $4',
        ];

        $date = preg_replace($in, $out, $date);
        $this->logger->debug('date replace = ' . print_r( $date, true));

        return strtotime($date);
    }


    private function opt($field, $delimiter = '/')
    {
        $field = (array)$field;
        if (empty($field)) {
            $field = ['false'];
        }
        return '(?:' . implode("|", array_map(function ($s) use($delimiter) {
                return str_replace(' ', '\s+', preg_quote($s, $delimiter));
            }, $field)) . ')';
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function currency($s)
    {
        if ($code = $this->re("#^\s*([A-Z]{3})\s*$#", $s)) return $code;
        $sym = [
            '€' => 'EUR',
            '$' => 'USD',
            '£' => 'GBP',
        ];
        foreach($sym as $f => $r)
            if ($s == $f) return $r;
        return null;
    }

}