<?php

namespace AwardWallet\Engine\roomres\Email;

// TODO: delete what not use
use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Common\Hotel;
use AwardWallet\Schema\Parser\Email\Email;

class BookingConfirmation extends \TAccountChecker
{
    public $mailFiles = "roomres/it-153980640.eml, roomres/it-179893273.eml";

    public $lang;
    public static $dictionary = [
        'en' => [
            'Hotel Details:' => 'Hotel Details:',
        ],
    ];

    private $detectSubject = [
        // en
        'Booking Confirmation #', // Booking Confirmation #387044 for Anne Inglis
        'Confirmation of held booking #', // Confirmation of held booking #447930 for STEPHEN PRASSINAS
    ];

    private $detectProvider = [
        // en
        'The Room-Res.com team',
        'Thanks for using Room-res.com to process your booking',
    ];

    private $detectBody = [
        'en' => [
            'Order Confirmation and Invoice', 'Order Confirmation and Receipt',
            'Confirmation of held booking',
        ],
    ];

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@room-res.com') !== false;
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
        if ($this->http->XPath->query("//*[{$this->contains($this->detectProvider)}]")->length === 0) {
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
        if (!$this->assignLang()) {
            $this->logger->debug("can't determine a language");

            return $email;
        }
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
            ->confirmation($this->http->FindSingleNode("//text()[" . $this->eq($this->t("Room-res Booking ID:")) . "]/following::text()[normalize-space()][1]",
                null, true, "/^\s*([A-Z\d]{5,})\s*$/"));

        $earned = $this->nextTd($this->t("You Earned:"), null, "/^\s*(\d[\d ,.]*) Point.*/");

        if (!empty($earned)) {
            $email->ota()
                ->earnedAwards($earned);
        }

        $h = $email->add()->hotel();

        // General
        $h->general()
            ->confirmation($this->nextTd($this->t("Itinerary Code:"), null, "/^\s*([A-Z\d]{5,})\s*$/"))
            ->traveller($this->http->FindSingleNode("//text()[{$this->eq($this->t("Customer Details:"))}]/following::text()[normalize-space()][1]"))
            ->cancellation(preg_replace(["/^\s*{$this->opt($this->t("Cancellation Policies:"))}\s*/", "/\.?\s*\n\s*/"], ['', '. '], trim(implode("\n",
                $this->http->FindNodes("//text()[{$this->eq($this->t("Cancellation Policies:"))}]/ancestor::td[1]/node()")))))
        ;
        $confs = explode('|', $this->nextTd($this->t("Customer Reference No:"), null, "/^\s*([A-Z\d \|\-]{5,})\s*$/"));

        foreach ($confs as $conf) {
            $h->general()
                ->confirmation($conf);
        }

        // Hotel
        $h->hotel()
            ->name($this->nextTd($this->t("Hotel Details:"), null, "/^\s*(.+?)\s*\|/"))
            ->address($this->nextTd($this->t("Hotel Details:"), null, "/^\s*.+?\s*\|\s*(.+)/"))
        ;

        // Booked
        $stayDetails = implode("\n", $this->http->FindNodes("//text()[{$this->eq($this->t("Stay Details:"))}]/ancestor::td[1]/following-sibling::td[normalize-space()][1]//text()[normalize-space()]"));
        $h->booked()
            ->checkIn($this->normalizeDate($this->re("/\s*Check-in (.+?)\s*\|/", $stayDetails)))
            ->checkOut($this->normalizeDate($this->re("/\s*Check-out (.+?)\n/", $stayDetails)))
            ->rooms($this->re("/\n\s*[^\w\s]*\s*(\d+) *Rooms?\s*\n/", $stayDetails))
            ->guests($this->re("/for (\d+) adult/", $stayDetails))
            ->kids($this->re("/for .*\b(\d+) child/", $stayDetails), true, true)
        ;

        // Rooms
        $h->addRoom()
            ->setType($this->re("/booked a (.+) for \d+/", $stayDetails))
            ->setRate($this->http->FindSingleNode("//text()[{$this->eq($this->t("Payment Details:"))}]/following::text()[normalize-space()][1]",
                null, true, "/, *(.{0,5}[\d,. ]+ *.{1,5} *\\/ *night)/"))
        ;

        // Price
        $total = $this->http->FindSingleNode("//text()[{$this->eq($this->t("Total price:"))}]/following::text()[normalize-space()][1]");

        if (preg_match("#^\s*(?<currency>[^\d\s]{1,5})\s*(?<amount>\d[\d\., ]*)\s*$#", $total, $m)
            || preg_match("#^\s*(?<amount>\d[\d\., ]*)\s*(?<currency>[^\d\s]{1,5})\s*$#", $total, $m)
        ) {
            $currency = $this->currency($m['currency']);
            $h->price()
                ->total(PriceHelper::parse($m['amount'], $currency))
                ->currency($currency)
            ;
        }

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
            preg_match("/from now until ([\d\\/]+ \d{1,2}:\d{2}), cancellation charge=\\$0\.00\./u", $cancellationText, $m)
            || preg_match("/FREE Cancellation until ([\d\\/]+)/u", $cancellationText, $m)
        ) {
            $h->booked()
                ->deadline($this->normalizeDate($m[1]));
        }
    }

    private function assignLang()
    {
        foreach (self::$dictionary as $lang => $dict) {
            if (isset($dict["Hotel Details:"])) {
                if ($this->http->XPath->query("//*[{$this->contains($dict['Hotel Details:'])}]")->length > 0) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function t($s)
    {
        if (!isset($this->lang, self::$dictionary[$this->lang], self::$dictionary[$this->lang][$s])) {
            return $s;
        }

        return self::$dictionary[$this->lang][$s];
    }

    private function nextTd($field, $root = null, $regexp = null, $type = 'eq')
    {
        if ($type === 'contains') {
            $rule = $this->contains($field);
        } elseif ($type === 'starts') {
            $rule = $this->starts($field);
        } else {
            $rule = $this->eq($field);
        }

        return $this->http->FindSingleNode(".//text()[{$rule}]/ancestor::td[1]/following-sibling::td[normalize-space(.)!=''][1]", $root, true, $regexp);
    }

    private function contains($field, $text = 'normalize-space(.)')
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($text) {
            return 'contains(' . $text . ',"' . $s . '")';
        }, $field)) . ')';
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) {
            return 'normalize-space(.)="' . $s . '"';
        }, $field)) . ')';
    }

    private function starts($field)
    {
        $field = (array) $field;

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
            //            // 20-May-2022
            '/^\s*(\d+)\s*-\s*(\w+)\s*-\s*(\d{4})\s*$/iu',
            // 18/05/2022 12:00
            '/^\s*(\d{1,2})\/(\d{2})\/(\d{4})\s+(\d{1,2}:\d{1,2})\s*$/',
            // 18/05/2022
            '/^\s*(\d{1,2})\/(\d{2})\/(\d{4})\s*$/',
        ];
        $out = [
            '$1 $2 $3',
            '$1.$2.$3, $4',
            '$1.$2.$3',
        ];

        $date = preg_replace($in, $out, $date);
//        $this->logger->debug('date replace = ' . print_r( $date, true));

        return strtotime($date);
    }

    private function opt($field, $delimiter = '/')
    {
        $field = (array) $field;

        if (empty($field)) {
            $field = ['false'];
        }

        return '(?:' . implode("|", array_map(function ($s) use ($delimiter) {
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
