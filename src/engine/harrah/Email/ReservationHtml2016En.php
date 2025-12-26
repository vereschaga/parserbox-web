<?php

namespace AwardWallet\Engine\harrah\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class ReservationHtml2016En extends \TAccountChecker
{
    public $mailFiles = "harrah/it-64850350.eml, harrah/it-64920033.eml"; // +3 bcdtravel(html)[en]

    public $lang = 'en';

    public static $dictionary = [
        "en" => [],
    ];

    private $detectFrom = '@email.caesars-marketing.com';
    private $detectSubject = [
        "en" => ["Reservation Confirmation"],
    ];

    private $detectBody = [
        'en'=> [
            'TRIP SUMMARY', 'MANAGE ITINERARY',
        ],
    ];

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->parseHtml($email);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->detectFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (empty($headers['from']) || empty($headers['subject'])) {
            return false;
        }

        if (stripos($headers['from'], $this->detectFrom) === false) {
            return false;
        }

        foreach ($this->detectSubject as $detectSubject) {
            foreach ($detectSubject as $dSubject) {
                if (stripos($headers["subject"], $dSubject) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        foreach ($this->detectBody as $detectBody) {
            if ($this->http->XPath->query("//a[contains(@href,'.caesars-marketing.com') and " . $this->contains($detectBody) . "]")->length > 0) {
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

    private function parseHtml(Email $email)
    {
        $h = $email->add()->hotel();

        // General
        $conf = $this->http->FindSingleNode("//text()[" . $this->eq($this->t("CONFIRMATION")) . "]/following::text()[normalize-space()][" . $this->eq($this->t("NUMBER")) . "]/following::text()[normalize-space()][1]", null, false, '/^\s*([A-Z\d]+)\s*$/');

        if (!empty($conf)) {
            $h->general()
                ->confirmation($conf, "CONFIRMATION NUMBER")
            ;
        }
        $cancConf = $this->http->FindSingleNode("//text()[" . $this->eq($this->t("CANCELLATION")) . "]/following::text()[normalize-space()][" . $this->eq($this->t("NUMBER")) . "]/following::text()[normalize-space()][1]", null, false, '/^\s*([A-Z\d]+)\s*$/');

        if (!empty($cancConf)) {
            $h->general()
                ->noConfirmation()
                ->cancellationNumber($cancConf)
                ->status('Cancelled')
                ->cancelled()
            ;
        }

        $h->general()
            ->traveller($this->http->FindSingleNode("//text()[" . $this->eq($this->t("Guest Name")) . "]/following::text()[normalize-space()][1]", null, true, "/^\s*:\s*(.+)/"))
        ;
        $cancellation = $this->http->FindSingleNode("//text()[" . $this->starts($this->t("CANCELLATION POLICY")) . "]", null, true, "/" . $this->opt($this->t("CANCELLATION POLICY")) . "\s*(.{10,})/i");

        if (empty($cancellation)) {
            $cancellation = $this->http->FindSingleNode("//text()[" . $this->eq($this->t("CANCELLATION POLICY")) . "]/following::text()[normalize-space()][1]", null, true, "/" . $this->opt($this->t("CANCELLATION POLICY")) . "\s*(.{10,})/i");
        }
        $h->general()
            ->cancellation($cancellation)
        ;

        // Hotel
        $name = $this->http->FindSingleNode("//text()[" . $this->eq($this->t("TRIP SUMMARY")) . "]/following::text()[normalize-space()][1]");

        if (!empty($name) && !empty($this->http->FindSingleNode("//text()[" . $this->starts($this->t("Thank you for choosing")) . " and " . $this->contains($name) . "]", null, true, "/" . $this->opt($this->t("Thank you for choosing")) . "\s*" . preg_quote($name) . "/"))) {
            $h->hotel()
                ->name($name)
            ;
            $address = implode("\n", $this->http->FindNodes("//text()[" . $this->eq($this->t("TRIP SUMMARY")) . "]/following::text()[" . $this->eq($name) . "]/following::text()[normalize-space()][position() < 7]"));

            if (preg_match("/(?<address>[\s\S]+?)\n\s*(?<phone>[\d\+\(]{1,2}[\d\-\)\(\+ ]{4,})\n[\s\S]*" . $this->opt($this->t("Guest Name")) . "/", $address, $m)) {
                $h->hotel()
                    ->address(preg_replace("/\s+/", ' ', trim($m['address'])))
                    ->phone($m['phone'])
                ;
            }
        }

        // Booked
        $h->booked()
            ->checkIn(strtotime($this->http->FindSingleNode("//text()[" . $this->eq($this->t("Check In Date")) . "]/following::text()[normalize-space()][1]", null, true, "/^\s*:\s*(.+)/")))
            ->checkOut(strtotime($this->http->FindSingleNode("//text()[" . $this->eq($this->t("Check Out Date")) . "]/following::text()[normalize-space()][1]", null, true, "/^\s*:\s*(.+)/")))
            ->rooms($this->http->FindSingleNode("//text()[" . $this->eq($this->t("Number of Rooms")) . "]/following::text()[normalize-space()][1]", null, true, "/^\s*:\s*(.+)/"))
            ->guests($this->http->FindSingleNode("//text()[" . $this->eq($this->t("Adults")) . "]/following::text()[normalize-space()][1]", null, true, "/^\s*:\s*(.+)/"))
            ->kids($this->http->FindSingleNode("//text()[" . $this->eq($this->t("Children")) . "]/following::text()[normalize-space()][1]", null, true, "/^\s*:\s*(.+)/"))
        ;

        // Rooms
        $h->addRoom()
            ->setType($this->http->FindSingleNode("//text()[" . $this->eq($this->t("Room Type")) . "]/following::text()[normalize-space()][1]", null, true, "/^\s*:\s*(.+)/"))
        ;

        // Total
        $total = $this->http->FindSingleNode("//text()[" . $this->starts($this->t("GRAND TOTAL:")) . "]", null, true, "/" . $this->opt($this->t("GRAND TOTAL:")) . "\s*(.+)/");

        if (preg_match("#^\s*(?<curr>[^\d\s]{1,5})\s*(?<amount>\d[\d\., ]*)\s*$#", $total, $m)
            || preg_match("#^\s*(?<amount>\d[\d\., ]*)\s*(?<curr>[^\d\s]{1,5})\s*$#", $total, $m)) {
            $h->price()
                ->total($this->amount($m['amount']))
                ->currency($this->currency($m['curr']))
            ;
        }

        // Program
        $account = $this->http->FindSingleNode("//text()[" . $this->starts($this->t("Total Rewards #")) . "]", null, true, "/" . $this->opt($this->t("Total Rewards #")) . "\s*(\d{4,})\s*$/");

        if (empty($account)) {
            $account = $this->http->FindSingleNode("//text()[" . $this->eq($this->t("Total Rewards #")) . "]/following::text()[normalize-space()][1]", null, true, "/^\s*(\d{4,})\s*$/");
        }

        if (!empty($account)) {
            $h->program()
                ->account($account, false);
        }

        $earned = $this->http->FindSingleNode("//text()[" . $this->starts($this->t("You earned at least")) . "]/ancestor::*[" . $this->starts($this->t("You earned at least")) . "][last()]", null, true,
            "/least (\d+ Reward Credits)/");

        if (!empty($earned)) {
            $h->program()
                ->earnedAwards($earned);
        }

//        $this->detectDeadLine($h);

        return $email;
    }

    private function detectDeadLine(\AwardWallet\Schema\Parser\Common\Hotel $h)
    {
        $cancellationText = $h->getCancellation();

        if (empty($cancellationText)) {
            return false;
        }
        /*
        if (
        preg_match("/This rate allows booking modifications or cancellation without charges up to (?<date>.*\b\d{4}\b.*) local time\./", $cancellationText, $m) // en
        ) {
            $h->booked()
                ->deadline($this->normalizeDate($m['date']));
            return true;
        }

        if (   preg_match("/No free cancellation is allowed for this rate, special conditions apply\./", $cancellationText)
            || preg_match("/This is a last minute booking\. Last minute bookings cannot be modified or cancelled without penalty\./", $cancellationText)
        ) {
            $h->booked()->nonRefundable();
        }
        */

        return false;
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
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

    private function eq($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'normalize-space(' . $node . ')="' . $s . '"';
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
