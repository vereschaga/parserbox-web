<?php

namespace AwardWallet\Engine\goibibo\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

// parsers with similar formats: maketrip/BookingVoucher, maketrip/HotelBooking

class HotelBookingVoucher extends \TAccountChecker
{
    public $mailFiles = "goibibo/it-68071960.eml, goibibo/it-117675539.eml, goibibo/it-641096079.eml";

    public $detectFrom = "noreply@goibibo.com";
    public $detectSubject = [
        'Hotel Booking Voucher',
    ];
    public $detectBody = [
        'en' => ['Hotel booked on', 'Edit Stay Dates'],
    ];
    public $lang = 'en';
    public static $dict = [
        'en' => [
            'Check In'       => ['Check In', 'Check-in'],
            'Check Out'      => ['Check Out', 'Check-out'],
            'otaConfNumber'  => ['Goibibo ID :', 'Booking ID'],
            'statusPhrases'  => ['is'],
            'statusVariants' => ['confirmed'],
        ],
    ];

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        $this->parseEmail($email);

        return $email;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query('//a[contains(@href,".goibibo.com/") or contains(@href,"go.ibi.bo")]')->length === 0
            && $this->http->XPath->query('(//*[contains(.,"Goibibo ID")])[1]')->length === 0) {
            return false;
        }

        return $this->assignLang();
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
            if (stripos($headers["subject"], $detectSubject) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->detectFrom) !== false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    public function dateStringToEnglish($date)
    {
        if (preg_match('#[[:alpha:]]+#iu', $date, $m)) {
            $monthNameOriginal = $m[0];

            if ($translatedMonthName = MonthTranslate::translate($monthNameOriginal, $this->lang)) {
                return preg_replace("#$monthNameOriginal#i", $translatedMonthName, $date);
            }
        }

        return $date;
    }

    private function parseEmail(Email $email): void
    {
        $xpathBold = '(self::b or self::strong or ancestor-or-self::*[contains(@style,"bold")])';

        $patterns = [
            'travellerName' => '[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]]',
        ];

        // Travel Agency
        $email->ota()->confirmation(
            $this->http->FindSingleNode("//text()[{$this->eq($this->t('otaConfNumber'))}]/following::text()[normalize-space()][1]", null, true, '/^[A-Z\d]{5,25}$/'),
            $this->http->FindSingleNode("//text()[{$this->eq($this->t('otaConfNumber'))}]", null, true, '/^(.+?)[\s:：]*$/u'), true);

        $email->ota()
            ->confirmation($this->http->FindSingleNode("//text()[{$this->eq($this->t('PNR :'))}]/following::text()[normalize-space(.)!=''][1]",
                null, false, "#^\s*([A-Z\d]+)\s*$#"), 'PNR');

        $h = $email->add()->hotel();

        // General
        $traveller = $this->http->FindSingleNode("//a[{$this->contains($this->t('Edit Guest Name'))}]/ancestor::td[ preceding-sibling::td[normalize-space()] ][1]/preceding-sibling::td[normalize-space()][1]/descendant::p[normalize-space()][descendant-or-self::*[{$xpathBold}]][1]", null, true, "/^({$patterns['travellerName']})(?:\s*\(|$)/u");

        if (empty($traveller)) {
            $traveller = $this->http->FindSingleNode("//a[{$this->contains($this->t('Edit Guest Name'))}]/ancestor::td[ preceding-sibling::td[normalize-space()] ][1]/preceding-sibling::td[normalize-space()][1]/descendant::text()[normalize-space()][2]", null, true,
                "/^({$patterns['travellerName']})\s+[+]\d+$/u");
        }
        $h->general()
            ->noConfirmation()
            ->date($this->normalizeDate($this->http->FindSingleNode("//text()[{$this->starts($this->t('Hotel booked on'))}]",
                null, false, "#{$this->opt($this->t('Hotel booked on'))}\s+(.+)#")))
            ->traveller($traveller)
            ->cancellation(implode('. ', array_unique($this->http->FindNodes("//td[{$this->eq($this->t('Cancellation Policy'))}]/following-sibling::td[normalize-space()][1]/descendant::tr[not(.//tr) and normalize-space()]", null, "/^[-\s•]*(.{2,}?)[,.!?;:\s]*$/"))))
        ;

        // Price
        $currency = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Total Price ('))}]/ancestor::td[1]", null, true,
            "/" . $this->opt($this->t('Total Price (')) . "[[:alpha:] \-]+?\b([A-Z]{3})\b/");
        $total = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Total Price ('))}]/ancestor::td[2]/following-sibling::td[normalize-space()][1]/descendant::text()[normalize-space()][1]");

        if (is_numeric($total)) {
            $h->price()
                ->total($total)
                ->currency($currency);
        }

        // Hotel
        $h->hotel()
            ->name($this->http->FindSingleNode("//img[contains(@src, 'star-icon.png')]/preceding::text()[normalize-space()][1]/ancestor::td[1]")
                ?? $this->http->FindSingleNode("//img[contains(@src, 'location-pin.png')]/ancestor::tr[2][count(preceding-sibling::tr[normalize-space()]) < 3]/preceding-sibling::tr[normalize-space()][last()]"))
            ->address($this->http->FindSingleNode("//img[contains(@src, 'location-pin.png')]/following::text()[normalize-space()][1]/ancestor::td[1]"))
            ->phone($this->http->FindSingleNode("//img[contains(@src, '/phone.png')and following::text()[{$this->eq($this->t('Check In'))}]]/following::text()[normalize-space()][1]/ancestor::td[1]", null, true, "/^[\s,:#]*([+(\d][-. \d)(]{5,}[\d)])(?:\s*[,:#]|$)/"))
        ;

        if (!empty($h->getHotelName())) {
            $statusTexts = array_filter($this->http->FindNodes("//text()[{$this->contains($h->getHotelName())}]", null, "/\s{$this->opt($this->t('statusPhrases'))}\s+({$this->opt($this->t('statusVariants'))})(?:\s*[,.;:!?]|$)/i"));

            if (count(array_unique(array_map('mb_strtolower', $statusTexts))) === 1) {
                $status = array_shift($statusTexts);
                $h->general()->status($status);
            }
        }

        // Booked
        $h->booked()
            ->checkIn($this->normalizeDate($this->re("/{$this->opt($this->t('Check In'))}\s*(.{6,})/", implode(' ',
                $this->http->FindNodes("//text()[{$this->eq($this->t('Check In'))}]/ancestor::td[1][{$this->starts($this->t('Check In'))}]//text()[normalize-space()]")))))
            ->checkOut($this->normalizeDate($this->re("/{$this->opt($this->t('Check Out'))}\s*(.{6,})/", implode(' ',
                $this->http->FindNodes("//text()[{$this->eq($this->t('Check Out'))}]/ancestor::td[1][{$this->starts($this->t('Check Out'))}]//text()[normalize-space()]")))))
        ;

        $xpathRoom = "descendant::tr[ (count(*[normalize-space()])=2 or count(*[normalize-space()])=3) and *[normalize-space()][last()]/descendant::text()[normalize-space()][1][{$this->starts($this->t('Add Guests'))}] ]";

        $h->booked()
            ->rooms($this->http->FindSingleNode($xpathRoom . "/*[normalize-space()][last()-1]/ancestor-or-self::td[ preceding-sibling::td[normalize-space()] ][1]/preceding-sibling::td[normalize-space()][1]", null, true, "/^\s*(\d{1,3})\s*{$this->opt($this->t('Room'))}/i"))
            ->guests($this->http->FindSingleNode("//a[{$this->starts($this->t('Edit Guest Name'))}]/ancestor::td[ preceding-sibling::td[normalize-space()] ][1]/preceding-sibling::td[normalize-space()][1]/ancestor-or-self::td[ preceding-sibling::td[normalize-space()] ][1]/preceding-sibling::td[normalize-space()][1]", null, true, "/^\s*(\d{1,3})\s*{$this->opt($this->t('Guest'))}/i"))
        ;

        // Rooms
        $roomType = $this->http->FindNodes($xpathRoom . "/*[normalize-space()][last()-1]/descendant::text()[normalize-space()][1]");
        $roomDesc = $this->http->FindNodes($xpathRoom . "/*[normalize-space()][last()-1]/descendant::text()[normalize-space()][2]", null, "/.+?\s*\+\s*\d+\s*$/");

        for ($i = 0; $i < count($roomType); $i++) {
            $r = $h->addRoom();
            $r->setType($roomType[$i]);

            if (count($roomType) === count($roomDesc) && !empty($roomDesc[$i])) {
                $r->setDescription($roomDesc[$i]);
            }
        }

        $account = $this->http->FindSingleNode("//a[{$this->contains($this->t('Edit Guest Name'))}]/ancestor::td[ preceding-sibling::td[normalize-space()] ][1]/preceding-sibling::td[normalize-space()][1]/descendant::text()[normalize-space()][last()]", null, true, "/^\d{10,}$/");

        if (!empty($account)) {
            $h->program()
                ->account($account, false);
        }
        $this->detectDeadLine($h);
    }

    private function detectDeadLine(\AwardWallet\Schema\Parser\Common\Hotel $h): void
    {
        if (empty($cancellationText = $h->getCancellation())) {
            return;
        }

        if (preg_match("/^\W*Booking is Non-Refundable(?:\.|\s+)/i", $cancellationText)
            || preg_match("/^This is a Non-refundable and non-amendable tariff(?:\s*[.!]|$)/i", $cancellationText)
            || preg_match("/^This tariff cannot be cancell?ed with zero fee(?:\s*[.!]|$)/i", $cancellationText)
        ) {
            $h->booked()->nonRefundable();
        }

        if (preg_match("/^Free Cancell?ation till\s+(.*?\b\d{4}\b.*?)\s*(?:[.!]|$)/i", $cancellationText, $m)) {
            $h->booked()->deadline($this->normalizeDate($m[1]));
        }
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function assignLang(): bool
    {
        foreach ($this->detectBody as $lang => $detectBody) {
            if ($this->http->XPath->query("//*[" . $this->contains($detectBody[0]) . "]")->length > 0
                && $this->http->XPath->query("//*[" . $this->contains($detectBody[1]) . "]")->length > 0
            ) {
                $this->lang = substr($lang, 0, 2);

                return true;
            }
        }

        return false;
    }

    private function normalizeDate($date)
    {
        //$this->logger->debug('$date = '.print_r( $date,true));
        $in = [
            // Fri, 16 Oct 2020 before 12:00 PM
            '/^\s*\w+,\s*(\d{1,2})\s+([[:alpha:]]{3,})\s+(\d{4})\s+\D+\s+(\d{1,2}(?::\d{2})?(?:\s*[AP][M])?)\s*$/ui',
        ];
        $out = [
            '$1 $2 $3, $4',
        ];

        $date = preg_replace($in, $out, $date);
        $date = preg_replace("/, (\d{1,2})\s*([ap]m)\s*$/ui", ', $1:00 $2', $date); // 2 PM -> 2:00 PM

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#u", $date, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $date = str_replace($m[1], $en, $date);
            }
        }

//        $this->logger->debug('$date = '.print_r( $date,true));

        return strtotime($date);
    }

    private function eq($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'normalize-space(' . $node . ')="' . $s . '"';
        }, $field)) . ')';
    }

    private function starts($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'starts-with(normalize-space(' . $node . '),"' . $s . '")';
        }, $field)) . ')';
    }

    private function contains($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'contains(normalize-space(' . $node . '),"' . $s . '")';
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

    private function re($re, $str, $c = 1): ?string
    {
        if (preg_match($re, $str, $m) && array_key_exists($c, $m)) {
            return $m[$c];
        }

        return null;
    }
}
