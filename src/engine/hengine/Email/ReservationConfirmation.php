<?php

namespace AwardWallet\Engine\hengine\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class ReservationConfirmation extends \TAccountChecker
{
    public $mailFiles = "";

    public $detectSubject = [
        'Your Reservation Confirmation for',
    ];

    public $detectBody = [
        'Use this number during check-in at the Hotel',
        'you experience any issues during the check-in process please call',
    ];

    public $lang = 'en';
    public static $dictionary = [
        'en' => [
            'Hotel Confirmation #:' => ['Hotel Confirmation #:', 'Hotel Engine #:', 'Engine #:'],
            'Room:'                 => ['Room:', 'Room Subtotal:', 'Rooms:'],
            'Check-In:'             => ['Check-In:', 'Check-in:'],
            'Check-Out:'            => ['Check-Out:', 'Checkout:'],
            'Guests:'               => ['Guests:', 'Total Guests:'],
            'Trip Information'      => 'Trip Information',
            'Traveler Details'      => 'Traveler Details',
        ],
    ];

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->parseHotel($email);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, 'noreply@hotelengine.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (empty($headers["from"]) || empty($headers["subject"])) {
            return false;
        }

        if (stripos($headers['from'], 'noreply@hotelengine.com') === false) {
            return false;
        }

        foreach ($this->detectSubject as $re) {
            if (stripos($headers["subject"], $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//a/@href[{$this->contains(['hotelengine.com', '.engine.com'])}]")->length === 0) {
            return false;
        }

        foreach (self::$dictionary as $lang => $dict) {
            if (!empty($dict['Trip Information']) && $this->http->XPath->query("//*[{$this->eq($dict['Trip Information'])}]")->length > 0
                && !empty($dict['Traveler Details']) && $this->http->XPath->query("//*[{$this->eq($dict['Traveler Details'])}]")->length > 0
            ) {
                $this->lang = $lang;

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

        // General
        $h->general()
            ->confirmation($this->nextTD($this->t('Hotel Confirmation #:')),
                rtrim($this->http->FindSingleNode("//td[not(.//td)][{$this->eq($this->t('Hotel Confirmation #:'))}]"), ': '))
            ->travellers(preg_split('/\s*,\s*/', $this->nextTD($this->t('Guest Names:'))))
        ;

        // Hotel
        $hotelInfo = implode("\n",
            $this->http->FindNodes("//*[count(*[normalize-space()]) = 1 and *[1][not(normalize-space()) and .//img] and *[2]//text()[{$this->eq($this->t('View Itinerary'))}]]//text()[normalize-space()]"));

        if (preg_match("/^\s*(?<name>.+)\n(?<address>.+)\n(?<phone>[\d\W ]+)\n{$this->preg_implode($this->t('View Itinerary'))}/", $hotelInfo, $m)) {
            $h->hotel()
                ->name($m['name'])
                ->address($m['address'])
                ->phone($m['phone']);
        }

        // Booked
        $h->booked()
            ->checkIn($this->normalizeDate($this->http->FindSingleNode("//td[not(.//td)][descendant::text()[normalize-space()][1][{$this->eq($this->t('Check-In:'))}]]",
                null, true, "/{$this->preg_implode($this->t('Check-In:'))}\s*(.+)/")))
            ->checkOut($this->normalizeDate($this->http->FindSingleNode("//td[not(.//td)][descendant::text()[normalize-space()][1][{$this->eq($this->t('Check-Out:'))}]]",
                null, true, "/{$this->preg_implode($this->t('Check-Out:'))}\s*(.+)/")))
            ->guests($this->nextTD($this->t('Guests:')))
            ->rooms($this->nextTD($this->t('Rooms:'), null, null, $this->t('Trip Expenses')))
        ;

        // Rooms
        $h->addRoom()
            ->setType($this->nextTD('Room Type:'));

        // Price
        $total = $this->getTotal($this->nextTD($this->t('Total Charge:'), null, $this->t('Trip Expenses')));
        $h->price()
            ->currency($total['currency'])
            ->total($total['amount']);

        $tax = $this->getTotal($this->nextTD($this->t('Taxes & Fees:*'), null, $this->t('Trip Expenses')));
        $h->price()
            ->tax($tax['amount']);

        $cost = $this->getTotal($this->nextTD($this->t('Room:'), null, $this->t('Trip Expenses')));
        $h->price()
            ->cost($cost['amount']);

        // Cancellation
        // Deadline
        $cancellationText = $this->http->FindSingleNode("//tr[" . $this->eq($this->t("Cancellation Policy")) . "]/following-sibling::tr[normalize-space()][1]");

        $h->general()->cancellation($cancellationText, true, true);

        $this->detectDeadLine($h, $cancellationText);

        return $email;
    }

    private function detectDeadLine(\AwardWallet\Schema\Parser\Common\Hotel $h)
    {
        if (empty($cancellationText = $h->getCancellation())) {
            return;
        }

        if (preg_match("/you can cancel your reservation for a full refund up until (?<time>\d+:\d+.*?) on (?<date>[\d\\/]+)\./", $cancellationText, $m)
        ) {
            $h->booked()->deadline(strtotime($m['date'] . ', ' . $m['time']));

            return true;
        }
//        if (preg_match("#NON-REFUNDABLE - 100% of payment will be taken#i", $cancellationText)) {
//            $h->booked()->nonRefundable();
//        }
    }

    private function t(string $phrase)
    {
        if (!isset(self::$dictionary, $this->lang) || empty(self::$dictionary[$this->lang][$phrase])) {
            return $phrase;
        }

        return self::$dictionary[$this->lang][$phrase];
    }

    private function nextTD($field, $regexp = null, $beforeFields = null, $afterFields = null)
    {
        if (!empty($beforeFields)) {
            $added = "//text()[{$this->eq($beforeFields)}]/following::";
        } else {
            $added = '//';
        }

        if (!empty($afterFields)) {
            $added2 = "[following::text()[{$this->eq($afterFields)}]]";
        } else {
            $added2 = '';
        }

        return $this->http->FindSingleNode($added . "td[not(.//td)][{$this->eq($field)}]/following-sibling::td[normalize-space(.)!=''][1]" . $added2,
            null, true, $regexp);
    }

    private function getTotal($text)
    {
        $result = ['amount' => null, 'currency' => null];

        if (preg_match("#^\s*(?<currency>[^\d\s]{1,5})\s*(?<amount>\d[\d\., ]*)\s*$#", $text, $m)
            || preg_match("#^\s*(?<amount>\d[\d\., ]*)\s*(?<currency>[^\d\s]{1,5})\s*$#", $text, $m)
            // $232.83 USD
            || preg_match("#^\s*\D{1,5}(?<amount>\d[\d\., ]*)\s*(?<currency>[A-Z]{3})\s*$#", $text, $m)
        ) {
            $m['currency'] = $this->currency($m['currency']);
            $m['amount'] = PriceHelper::parse($m['amount']);

            if (is_numeric($m['amount'])) {
                $m['amount'] = (float) $m['amount'];
            } else {
                $m['amount'] = null;
            }
            $result = ['amount' => $m['amount'], 'currency' => $m['currency']];
        }

        return $result;
    }

    private function currency(string $string): string
    {
        $string = trim($string);
        $currences = [
            'GBP' => ['£'],
            'EUR' => ['€'],
            'USD' => ['$', 'US$'],
        ];

        foreach ($currences as $currencyCode => $currencyFormats) {
            foreach ($currencyFormats as $currency) {
                if ($string === $currency) {
                    return $currencyCode;
                }
            }
        }

        return $string;
    }

    private function normalizeDate($str)
    {
//        $this->logger->debug('$str = '.print_r( $str,true));
        $in = [
            // 04-13-2023 11:00 AM
            "/^\s*(\d+)\-(\d+)\-(\d{4})\s+(\d+:\d{2}(?:\s*[AP]M)?)\s*$/iu",
            // 03/01/2025 4:00 PM PST
            "/^\s*(\d+\/\d+\/\d{4})\s+(\d+:\d{2}(?:\s*[AP]M)?)(?:\s+[A-Z]{3,4})?\s*$/iu",
        ];
        $out = [
            "$2.$1.$3, $4",
            "$1, $2",
        ];
        $str = preg_replace($in, $out, $str);
//        $this->logger->debug('$str = '.print_r( $str,true));

//        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
//            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
//                $str = str_replace($m[1], $en, $str);
//            }
//        }

        return strtotime($str);
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

    private function nextText($field, $regexp = null, $root = null)
    {
        $result = $this->http->FindSingleNode('descendant::text()[' . $this->eq($this->t($field)) . '][1]/following::text()[normalize-space()][1]', $root, true, $regexp);

        if ($result === null) {
            $result = $this->http->FindSingleNode("//text()[{$this->eq(preg_replace('/\s*:\s*$/', '', $this->t($field)))}]/following::text()[normalize-space() and not(normalize-space() = ':')][1]", $root, true, $regexp);
        }

        return $result;
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
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
