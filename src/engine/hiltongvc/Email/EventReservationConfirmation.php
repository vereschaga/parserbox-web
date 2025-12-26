<?php

namespace AwardWallet\Engine\hiltongvc\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class EventReservationConfirmation extends \TAccountChecker
{
    public $mailFiles = "";

    public $detectSubject = [
        'Your Reservation Confirmation for',
    ];

    public $providerCode;

    public static $detectProvider = [
        'hhonors'       => ['Hilton'],
        'marriott'      => ['Marriott', 'Renaissance'],
        'triprewards'   => ['Wyndham'],
        'ichotelsgroup' => ['Holiday Inn'],
        'drury'         => ['Drury Inn'],
    ];

    public $lang = 'en';
    public static $dictionary = [
        'en' => [
            'Reservation Confirmation'         => ['Reservation Confirmation'],
            'Event Name:'                      => ['Event Name:'],
            'Number of Nights:'                => ['Number of Nights:'],
            'Reservation Confirmation Number:' => ['Reservation Confirmation Number:'],
            'Guest Receipt Reference Number:'  => ['Guest Receipt Reference Number:'],
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
        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (empty($headers["from"]) || empty($headers["subject"])) {
            return false;
        }

        if (stripos($headers['from'], 'customerservice@mytravel.support') === false) {
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
        foreach (self::$dictionary as $lang => $dict) {
            if (!empty($dict['Reservation Confirmation']) && !empty($dict['Event Name:'])
                && !empty($dict['Number of Nights:']) && !empty($dict['Reservation Confirmation Number:']) && !empty($dict['Guest Receipt Reference Number:'])
                && $this->http->XPath->query("//node()[{$this->eq($dict['Reservation Confirmation'])}]/following::text()[normalize-space()][1][{$this->eq($dict['Event Name:'])}]")->length > 0
                && $this->http->XPath->query("//node()[{$this->eq($dict['Number of Nights:'])}]/following::text()[normalize-space()][2][{$this->eq($dict['Reservation Confirmation Number:'])}]/following::text()[normalize-space()][2][{$this->eq($dict['Guest Receipt Reference Number:'])}]")->length > 0
            ) {
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

    public static function getEmailProviders()
    {
        return array_keys(self::$detectProvider);
    }

    private function parseHotel(Email $email)
    {
        $h = $email->add()->hotel();

        // General
        $h->general()
            ->confirmation($this->nextText($this->t('Reservation Confirmation Number:'), "/^\s*(\w{5,})\s*$/"))
        ;
        $traveller = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Phone Number:'))}]/following::text()[normalize-space()][1][following::text()[normalize-space()][1][{$this->starts($this->t('Your reservation has been confirmed'))}]]");

        if (empty($traveller)) {
            $traveller = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Event Name:'))}]/following::text()[normalize-space()][3][following::text()[normalize-space()][1][{$this->starts($this->t('Your reservation has been confirmed'))}]]");
        }
        $h->general()
            ->traveller($traveller);

        // Hotel
        $name = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Event Name:'))}]/following::text()[normalize-space()][2][following::text()[normalize-space()][2][{$this->starts($this->t('Phone Number:'))}]]");

        if (!empty($name)) {
            $h->hotel()
                ->name($name)
                ->address($this->http->FindSingleNode("//text()[{$this->eq($this->t('Event Name:'))}]/following::text()[normalize-space()][3][following::text()[normalize-space()][1][{$this->starts($this->t('Phone Number:'))}]]"))
                ->phone($this->http->FindSingleNode("//text()[{$this->starts($this->t('Phone Number:'))}]",
                    null, true, "/Phone Number:\s*([\d\W ]{5,})$/"));
        }

        if (empty($name)) {
            $name = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Event Name:'))}]/following::text()[normalize-space()][2][following::text()[normalize-space()][2][{$this->starts($this->t('Your reservation has been confirmed'))}]]");

            if (!empty($name)) {
                $h->hotel()
                    ->name($name)
                    ->noAddress();
            }
        }

        foreach (self::$detectProvider as $code => $dProv) {
            if (preg_match("/\b{$this->opt($dProv)}\b/", $h->getHotelName())) {
                $this->providerCode = $code;
            }
        }

        if (!empty($this->providerCode)) {
            $email->setProviderCode($this->providerCode);
        } else {
            $email->add()->hotel();
            $this->logger->debug('not detect provider');

            return $email;
        }

        // Booked
        $h->booked()
            ->checkIn($this->normalizeDate($this->nextText($this->t('Check-in:'))))
            ->checkOut($this->normalizeDate($this->nextText($this->t('Checkout:'))))
        ;

        // Rooms
        $desc = $this->nextText('Room Description:');

        if (!empty($desc)) {
            $h->addRoom()
                ->setDescription();
        }

        // Cancellation
        // Deadline
        $cancellationText = $this->nextText($this->t("Cancellation Policy:"));

        $h->general()->cancellation($cancellationText);
        $this->detectDeadLine($h, $cancellationText);

        return $email;
    }

    private function detectDeadLine(\AwardWallet\Schema\Parser\Common\Hotel $h)
    {
        if (empty($cancellationText = $h->getCancellation())) {
            return;
        }

//        if (preg_match("/you can cancel your reservation for a full refund up until (?<time>\d+:\d+.*?) on (?<date>[\d\\/]+)\./", $cancellationText, $m)
//        ) {
//            $h->booked()->deadline(strtotime($m['date'] . ', ' . $m['time']));
//            return true;
//        }
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

    private function normalizeDate($str)
    {
//        $this->logger->debug('$str = '.print_r( $str,true));
        $in = [
            // 04-13-2023 11:00 AM
            "/^\s*(\d+)\-(\d+)\-(\d{4})\s+(\d+:\d{2}(?:\s*[AP]M)?)\s*$/iu",
        ];
        $out = [
            "$2.$1.$3, $4",
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
        $result = $this->http->FindSingleNode('//text()[' . $this->eq($field) . '][1]/following::text()[normalize-space()][1]', $root, true, $regexp);

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
