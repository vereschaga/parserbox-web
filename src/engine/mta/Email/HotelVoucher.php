<?php

namespace AwardWallet\Engine\mta\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;

class HotelVoucher extends \TAccountChecker
{
    public $mailFiles = "mta/it-153141434.eml, mta/it-153147048.eml, mta/it-170528502.eml";

    public $detectSubject = [
        // en
        'Hotel Voucher - Ref', //FW: Hotel Voucher - Ref 701600489 - Ms Christine Dawson
    ];
    public $detectBodyProvider = [
        // en
        'MTA Travel - ',
        '@mtatravel.com.au',
    ];
    public $detectBody = [
        'en'  => ['Prepaid Voucher -', 'Booking Confirmation / Proforma Invoice'],
    ];
    public $lang = 'en';
    public static $dict = [
        'en' => [
            'Prepaid Voucher - ' => ['Prepaid Voucher - ', 'Booking Confirmation / Proforma Invoice -'],
        ],
    ];

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $bookingConfirmation = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Prepaid Voucher - '))}]");

        if (preg_match("/^(.{2,}?)\s+-\s+(\d{5,})\s*$/", $bookingConfirmation, $m)) {
            $email->ota()->confirmation($m[2], $m[1]);
        }

        $yourReference = $this->http->FindSingleNode("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('Your Reference'))}] ]/*[normalize-space()][2]", null, true, "/^[-A-Z\d]{5,}$/");

        if ($yourReference) {
            $yourReferenceTitle = $this->http->FindSingleNode("//tr[count(*[normalize-space()])=2]/*[normalize-space()][1][{$this->eq($this->t('Your Reference'))}]", null, true, '/^(.+?)[\s:：]*$/u');
            $email->ota()->confirmation($yourReference, $yourReferenceTitle);
        }

        $hotelNodes = $this->http->XPath->query("//*[ tr[normalize-space()][1]/descendant::tr/*[{$this->eq($this->t('Address'))}] and tr[normalize-space()][2]/descendant::tr/*[{$this->eq($this->t('Check In'))}] ]");

        foreach ($hotelNodes as $hNode) {
            $this->parseHotel($email, $hNode);
        }

        if ($hotelNodes->length === 0) {
            // it-153141434.eml
            $this->parseHotel($email);
        }

        $totalPrice = $this->http->FindSingleNode("//tr[{$this->eq($this->t('PAYMENT DETAILS'))}]/following::tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('Total'))}] ]/*[normalize-space()][2]", null, true, '/^.*\d.*$/');

        if (preg_match('/^(?<currency>[^\-\d)(]+?)[ ]*(?<amount>\d[,.\'\d ]*)$/', $totalPrice, $matches)
            || preg_match('/^(?<amount>\d[,.\'\d ]*?)[ ]*(?<currency>[^\-\d)(]+)$/', $totalPrice, $matches)
        ) {
            // A$8,320.49
            $currency = $this->normalizeCurrency($matches['currency']);
            $currencyCode = preg_match('/^[A-Z]{3}$/', $currency) ? $currency : null;
            $email->price()->currency($currency)->total(PriceHelper::parse($matches['amount'], $currencyCode));
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (stripos($headers['from'], 'mtatravel.com.au') !== false) {
            return false;
        }

        foreach ($this->detectSubject as $re) {
            if (stripos($headers['subject'], $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//text()[" . $this->contains($this->detectBodyProvider) . "]")->length == 0) {
            return false;
        }

        foreach ($this->detectBody as $dBody) {
            if ($this->http->XPath->query("//text()[" . $this->contains($dBody) . "]")->length > 0) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, "mtatravel.com.au") !== false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    private function parseHotel(Email $email, ?\DOMNode $root = null): void
    {
        $h = $email->add()->hotel();

        // General
        $guestNamesVal = $this->http->FindSingleNode("descendant::tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('Guest Name(s)'))}] ]/*[normalize-space()][2]", $root);
        // Mr Karl Baron | Mrs Barbara Baron | Mstr Thomas Baron(Child, Age 16) | Ms Piper Baron(Child, Age 14)
        $guestNames = preg_split("/\s+\|\s+/", $guestNamesVal);
        $h->general()
            ->noConfirmation()
            ->travellers(preg_replace("/^\s*(?:Mstr|Miss|Mrs|Mr|Ms)[.\s]+(.{2,}?)(?:\s*\([^)(]*\))?$/i", '$1', $guestNames), true)
        ;
        $status = $this->http->FindSingleNode("descendant::tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('Status'))}] ]/*[normalize-space()][2]", $root);

        if ($status) {
            $h->general()->status($status);
        }

        // Hotel
        $hotelName = $this->http->FindSingleNode("descendant::tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('Address'))}] ]/preceding-sibling::tr[normalize-space()][1]", $root);
        $h->hotel()
            ->name($hotelName)
            ->address($this->http->FindSingleNode("descendant::tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('Address'))}] ]/*[normalize-space()][2]", $root))
            ->phone($this->http->FindSingleNode("descendant::tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('Address'))}] ]/following-sibling::tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('Phone'))}] ]/*[normalize-space()][2]", $root, true, "/^[+(\d][-+. \d)(]{5,}[\d)]$/"), true, true)
        ;

        // Booked
        $checkInVal = $this->http->FindSingleNode("descendant::tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('Check In'))}] ]/*[normalize-space()][2]", $root, true, "/^\s*([^|]{6,}?)\s+\|/");
        $h->booked()
            ->checkIn($this->normalizeDate($checkInVal))
            ->checkOut($this->normalizeDate($this->http->FindSingleNode("descendant::td[not(.//tr)][{$this->eq($this->t('Check In'))}]/following-sibling::td[normalize-space()][1]", $root, true, "/{$this->opt($this->t('Check Out'))}\s+([^|]{6,}?)\s+\|/")))
        ;

        $h->addRoom()
            ->setType($this->http->FindSingleNode("descendant::td[not(.//tr)][{$this->eq('Room Type')}]/following-sibling::td[normalize-space()][1]", $root))
        ;

        if ($checkInVal && $hotelName && !empty($guestNames)) {
            $deadlineVal = $this->http->FindSingleNode("//tr[ *[10][{$this->eq($this->t('Cancel Deadline'))}] ]/following-sibling::tr[ *[2][{$this->starts($checkInVal)}] and *[4][{$this->starts($hotelName)}] and *[8][{$this->starts($guestNames)}] ]/*[10]", null, true, '/^.*\d.*$/');

            if ($deadlineVal) {
                $h->booked()->deadline2($deadlineVal);
            }

            $priceStatusVal = $this->htmlToText($this->http->FindHTMLByXpath("//tr[ *[12][{$this->eq($this->t('Price/Status'))}] ]/following-sibling::tr[ *[2][{$this->starts($checkInVal)}] and *[4][{$this->starts($hotelName)}] and *[8][{$this->starts($guestNames)}] ]/*[12]"));
            $price = $this->re("/^\s*(.*\d.*?)[ ]*/", $priceStatusVal);

            if (preg_match('/^(?<currency>[^\-\d)(]+?)[ ]*(?<amount>\d[,.\'\d ]*)$/', $price, $matches)
                || preg_match('/^(?<amount>\d[,.\'\d ]*?)[ ]*(?<currency>[^\-\d)(]+)$/', $price, $matches)
            ) {
                // A$2,844.90
                $currency = $this->normalizeCurrency($matches['currency']);
                $currencyCode = preg_match('/^[A-Z]{3}$/', $currency) ? $currency : null;
                $h->price()->currency($currency)->total(PriceHelper::parse($matches['amount'], $currencyCode));
            }

            if (!$status && preg_match("/^\s*.*\d.*?[ ]*\n+[ ]*(.{2,}?)[ ]*(?:\n|$)/", $priceStatusVal, $m)) {
                $h->general()->status($m[1]);
            }
        }
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    /**
     * @param string $string Unformatted string with currency
     */
    private function normalizeCurrency(string $string): string
    {
        $string = trim($string);
        $currencies = [
            // do not add unused currency!
            'AUD' => ['A$'],
        ];

        foreach ($currencies as $currencyCode => $currencyFormats) {
            foreach ($currencyFormats as $currency) {
                if ($string === $currency) {
                    return $currencyCode;
                }
            }
        }

        return $string;
    }

    private function normalizeDate($date)
    {
//        $this->logger->debug("date in: " . $date);
        $in = [
            //18 jul, 2019
            //            '#^(\d+)\-(\w+)\-(\d{4})$#u',
        ];
        $out = [
            //            '$1 $2 $3',
        ];

        $date = preg_replace($in, $out, $date);
//        $this->logger->debug("date out: " . $date);

        return strtotime($date);
    }

    private function htmlToText(?string $s, bool $brConvert = true): string
    {
        if (!is_string($s) || $s === '') {
            return '';
        }
        $s = str_replace("\r", '', $s);
        $s = preg_replace('/<!--.*?-->/s', '', $s); // comments

        if ($brConvert) {
            $s = preg_replace('/\s+/', ' ', $s);
            $s = preg_replace('/<[Bb][Rr]\b.*?\/?>/', "\n", $s); // only <br> tags
        }
        $s = preg_replace('/<[A-z][A-z\d:]*\b.*?\/?>/', '', $s); // opening tags
        $s = preg_replace('/<\/[A-z][A-z\d:]*\b[ ]*>/', '', $s); // closing tags
        $s = html_entity_decode($s);
        $s = str_replace(chr(194) . chr(160), ' ', $s); // NBSP to SPACE

        return trim($s);
    }

    private function re($re, $str, $c = 1): ?string
    {
        if (preg_match($re, $str, $m) && array_key_exists($c, $m)) {
            return $m[$c];
        }

        return null;
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
