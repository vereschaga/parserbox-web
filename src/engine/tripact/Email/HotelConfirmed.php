<?php

namespace AwardWallet\Engine\tripact\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;

class HotelConfirmed extends \TAccountChecker
{
    public $mailFiles = "";

    private $detectFrom = "@tripactions.com";
    private $detectSubject = [
        // en
        ' stay at ', // Your Jan 19 stay at The Line Austin is confirmed
    ];
    private $detectBody = [
        'en' => [
            'Here is what you booked:',
        ],
    ];

    public $lang = 'en';
    public static $dictionary = [
        'en' => [
        ],
    ];

    // Main Detects Methods
    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->detectFrom) !== false;
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
        if ($this->http->XPath->query("//a[{$this->contains(['.tripactions.com'], '@href')}]")->length === 0) {
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
            ->confirmation($this->http->FindSingleNode("//text()[".$this->eq($this->t("TripActions booking ID:"))."]/following::text()[normalize-space()][1]", null, true, "/^\s*([A-Z\d]{5,})\s*$/"));

        $h = $email->add()->hotel();

        $h->general()
            ->confirmation($this->http->FindSingleNode("//text()[".$this->eq($this->t("Confirmation:"))."]/following::text()[normalize-space()][1]"))
            ->traveller($this->http->FindSingleNode("//text()[".$this->eq($this->t("Traveler"))."]/following::text()[normalize-space()][1]/ancestor::tr[position() < 4][count(*)=2 and *[1][not(normalize-space()) or string-length(normalize-space()) < 3]]/*[2]/descendant::text()[normalize-space()][1]"))
        ;
        $cancellations = $this->http->FindNodes("//text()[normalize-space()='Cancelation policy']/following::text()[normalize-space()][1]/ancestor::*[following::text()[normalize-space()][1][normalize-space()='Traveler']][1][1]//td[not(.//td)]");
        $cancellations = preg_replace("/^(\s*\S+.*\w\s*)$/", '$1.', $cancellations);
        $h->general()
            ->cancellation(implode(' ', $cancellations));

        // Price
        $total = $this->http->FindSingleNode("//td[".$this->eq($this->t("Total"))."]/following-sibling::td[normalize-space()][1]");
        if (preg_match("/^\s*(?<curr>[^\d\s]{1,5})\s*(?<amount>\d[\d\., ]*)\s*$/", $total, $m)
            || preg_match("/^\s*[^\d\s]{0,5}\s*(?<amount>\d[\d\., ]*)\s*(?<curr>[^\d\s]{1,5})\s*$/", $total, $m)) {
            $h->price()
                ->total(PriceHelper::parse($m['amount'], $m['curr']))
                ->currency($m['curr'])
            ;
        }
        $cost = $this->http->FindSingleNode("//td[".$this->eq($this->t("Subtotal"))."]/following-sibling::td[normalize-space()][1]");
        if (preg_match("/^\s*(?<curr>[^\d\s]{1,5})\s*(?<amount>\d[\d\., ]*)\s*$/", $cost, $m)
            || preg_match("/^\s*[^\d\s]{0,5}\s*(?<amount>\d[\d\., ]*)\s*(?<curr>[^\d\s]{1,5})\s*$/", $cost, $m)) {
            $h->price()
                ->cost(PriceHelper::parse($m['amount'], $m['curr']))
            ;
        }
        $feesXpath = $this->http->XPath->query("//td[".$this->eq($this->t("Taxes and Fees"))."][following-sibling::td[normalize-space()][1]]");
        foreach ($feesXpath as $fx) {
            $name = $this->http->FindSingleNode(".", $fx);
            $amount = $this->http->FindSingleNode("following-sibling::td[normalize-space()][1]", $fx);
            if (preg_match("/^\s*(?<curr>[^\d\s]{1,5})\s*(?<amount>\d[\d\., ]*)\s*$/", $amount, $m)
                || preg_match("/^\s*[^\d\s]{0,5}\s*(?<amount>\d[\d\., ]*)\s*(?<curr>[^\d\s]{1,5})\s*$/", $amount, $m)) {
                $h->price()
                    ->fee($name, PriceHelper::parse($m['amount'], $m['curr']));
            }
        }

        // Hotel
        $hotelsText = $this->http->FindNodes("//text()[{$this->eq($this->t("Booking details"))}]/following::tr[not(.//tr)][normalize-space()][position() < 4]");
        if (count($hotelsText) == 3 && (preg_match("/^\s*[\d\. \,\+\(\)\-]{5,}\s*$/", $hotelsText[2], $m)
                || preg_match("/^\s*{$this->opt($this->t("Room type:"))}\s*$/", $hotelsText[2]))
        ) {
            $h->hotel()
                ->name($hotelsText[0])
                ->address($hotelsText[1])
            ;
            if (!empty($m[0])) {
                $h->hotel()
                    ->phone(trim($m[0]));
            }
        }

        // Booked
        $h->booked()
            ->checkIn($this->normalizeDate($this->http->FindSingleNode("//text()[{$this->eq($this->t("Check-in:"))}]/following::text()[normalize-space()][1]/ancestor::tr[1]")))
            ->checkOut($this->normalizeDate($this->http->FindSingleNode("//text()[{$this->eq($this->t("Check-out:"))}]/following::text()[normalize-space()][1]/ancestor::tr[1]")))
            ->rooms($this->http->FindSingleNode("//text()[{$this->eq($this->t("Number of rooms"))}]/following::text()[normalize-space()][1]"))
        ;

        $h->addRoom()
            ->setRate($this->http->FindSingleNode("//td[{$this->eq($this->t("Price per night"))}]/following-sibling::td[normalize-space()][1]"))
            ->setType($this->http->FindSingleNode("//tr[{$this->eq($this->t("Room type:"))}]/following::text()[normalize-space()][1]/ancestor::tr[1]"))
        ;

        $this->detectDeadLine($h);

        return true;
    }

    private function detectDeadLine(\AwardWallet\Schema\Parser\Common\Hotel $h): void
    {
        if (empty($cText = $h->getCancellation())) {
            return;
        }

        if (preg_match("/^\s*(?:Change or cancel for free|Fully refundable) until (?<date>.+?)\./", $cText, $m)
        ) {
            $h->booked()
                ->deadline(strtotime($m['date']));
        } elseif (preg_match("/^\s*Non-refundable after booking/", $cText, $m)
        ) {
            $h->booked()
                ->nonRefundable();
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
            // Wed, Jan 04, 2023•3:00 PM
            '/^\s*[[:alpha:]\-]+[\s,]+([[:alpha:]]+)\s+(\d+),\s*(\d{4})\W+(\d{1,2}:\d{2}(?:\s*[ap]m)?)\s*$/ui',
        ];
        $out = [
            '$2 $1 $3, $4',
        ];

        $date = preg_replace($in, $out, $date);
//        $this->logger->debug('date replace = ' . print_r( $date, true));


//        $this->logger->debug('date end = ' . print_r( $date, true));

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
}