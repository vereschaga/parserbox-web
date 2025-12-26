<?php

namespace AwardWallet\Engine\hotelslash\Email;

// TODO: delete what not use
use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;

class ReservationConfirmed extends \TAccountChecker
{
    public $mailFiles = "hotelslash/it-739554962.eml, hotelslash/it-745805115.eml, hotelslash/it-746086401.eml, hotelslash/it-747470639.eml, hotelslash/it-747505742.eml, hotelslash/it-749266595.eml, hotelslash/it-751353208.eml";

    public $lang;
    public static $dictionary = [
        'en' => [
            'Address:'        => 'Address:',
            'Guest names:'    => 'Guest names:',
            'Paid:'           => ['Total for the trip', 'Paid:'],
            'Checkin begins:' => ['Checkin begins:', 'checkin begin time:'],
            'Checkout:'       => ['Checkout:', 'checkout time:'],
            'canceledText'    => ['Your reservation has been canceled', 'Below are the details of your canceled reservation', 'Reservation canceled on'],
        ],
    ];

    private $detectFrom = "hotelslash.com";
    private $detectSubject = [
        // en
        'Reservation confirmed for your',
        'Your upcoming stay at ',
        'has been canceled',
        'Reservation voucher for',
    ];
    private $detectBody = [
        'en' => [
            'Below are the details of your current reservation.',
            'Below are the details of your canceled reservation.',
            'requested the following reservation voucher to be sent to you',
        ],
    ];

    // Main Detects Methods
    public function detectEmailFromProvider($from)
    {
        return preg_match("/[@.]hotelslash\.com$/", $from) > 0;
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

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if (
            $this->http->XPath->query("//a[{$this->contains(['.hotelslash.com'], '@href')}]")->length === 0
            && $this->http->XPath->query("//*[{$this->contains(['©  HotelSlash', 'Your friends at HotelSlash'])}]")->length === 0
        ) {
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
        $this->assignLang();

        if (empty($this->lang)) {
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

    private function assignLang()
    {
        foreach (self::$dictionary as $lang => $dict) {
            if (!empty($dict["Address:"]) && !empty($dict["Guest names:"])) {
                if ($this->http->XPath->query("//*[{$this->contains($dict['Address:'])}]")->length > 0
                    && $this->http->XPath->query("//*[{$this->contains($dict['Guest names:'])}]")->length > 0
                ) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function parseEmailHtml(Email $email)
    {
        // Travel Agency
        $confs = explode('.', $this->div($this->t('Order ID:'), '/^\s*([\w\.]+)\s*$/'));

        foreach ($confs as $conf) {
            $email->ota()
                ->confirmation($conf);
        }

        // Hotels
        $h = $email->add()->hotel();

        // General
        $conf = $this->div($this->t('Hotel confirmation #:'), '/^\s*([\w\.\-]+)\s*$/');

        if (!empty($conf)) {
            $h->general()
                ->confirmation($conf);
        } else {
            $h->general()
                ->noConfirmation();
        }
        $h->general()
            ->travellers(preg_split('/\s*,\s*/', $this->div($this->t('Guest names:'))), true)
            ->cancellation($this->http->FindSingleNode("(//*[descendant::text()[normalize-space()][1][{$this->eq($this->t('Cancellation policy:'))}]])[1]",
                null, true, "/^\s*{$this->opt($this->t('Cancellation policy:'))}\s*(\S.+)/"), true, true)
        ;

        if ($this->http->XPath->query("//*[{$this->contains($this->t('canceledText'))}]")->length > 0) {
            $h->general()
                ->status('Canceled')
                ->cancelled();
        }

        // Hotel
        $h->hotel()
            ->name($this->http->FindSingleNode("//text()[{$this->eq($this->t('Address:'))}]/preceding::text()[normalize-space()][1]/ancestor::*[following-sibling::*[normalize-space()][1][{$this->starts($this->t('Address:'))}]]/descendant::text()[normalize-space()][1]"))
            ->address($this->div($this->t('Address:'), "/^\s*(.+?)\s*{$this->opt($this->t('Get directions'))}/"))
            ->phone($this->div($this->t('Phone:')))
        ;

        // Booked
        $h->booked()
            ->checkIn(strtotime($this->div($this->t('Check in:'))))
            ->checkOut(strtotime($this->div($this->t('Check out:'))))
            ->guests($this->div($this->t('Guests:'), "/^\s*(\d+)\s*{$this->opt($this->t('adult'))}/"))
            ->kids($this->div($this->t('Guests:'), "/,\s*(\d+)\s*{$this->opt($this->t('child'))}/"), true, true)
        ;

        $time = array_filter($this->http->FindNodes("//text()[{$this->starts($this->t('Checkin begins:'))}]", null, "/{$this->opt($this->t('Checkin begins:'))}\s*(\d{1,2}:\d{2}(?:\s*[ap]m)?)\b/i"));

        if (count($time) && !empty($h->getCheckInDate())) {
            $h->booked()
                ->checkIn(strtotime(array_shift($time), $h->getCheckInDate()));
        }
        $time = array_filter($this->http->FindNodes("//text()[{$this->starts($this->t('Checkout:'))}]", null, "/{$this->opt($this->t('Checkout:'))}\s*(\d{1,2}:\d{2}(?:\s*[ap]m)?)\b/i"));

        if (count($time) && !empty($h->getCheckOutDate())) {
            $h->booked()
                ->checkOut(strtotime(array_shift($time), $h->getCheckOutDate()));
        }

        // Rooms
        $h->addRoom()
            ->setType($this->div($this->t('Room:')))
        ;

        // Price
        $total = $this->http->FindSingleNode("//tr[count(*) = 2][*[1][{$this->eq($this->t('Paid:'))}]]/*[2]");

        if (empty($total)) {
            $total = $this->http->FindSingleNode("//tr[not(.//tr)][count(*) = 2][*[1][{$this->starts($this->t('Total for '))}][{$this->contains($this->t('night'))}]]/*[2]");
        }

        if (empty($total)) {
            $total = $this->http->FindSingleNode("//tr[not(.//tr)][count(*) = 2][*[1][{$this->eq($this->t('Taxes and fees'))}]]/following-sibling::tr[count(*) = 2][*[1][not(normalize-space())]]/*[2]");
        }

        if (preg_match("#^\s*(?<currency>[^\d\s]{1,5})\s*(?<amount>\d[\d\., ]*)\s*$#", $total, $m)
            || preg_match("#^\s*(?<amount>\d[\d\., ]*)\s*(?<currency>[^\d\s]{1,5})\s*$#", $total, $m)
        ) {
            $h->price()
                ->total(PriceHelper::parse($m['amount']))
                ->currency($m['currency']);
        }
        $tax = $this->http->FindSingleNode("//tr[count(*) = 2][*[1][{$this->eq($this->t('Taxes and fees'))}]]/*[2]");

        if (preg_match("#^\s*(?<currency>[^\d\s]{1,5})\s*(?<amount>\d[\d\., ]*)\s*$#", $tax, $m)
            || preg_match("#^\s*(?<amount>\d[\d\., ]*)\s*(?<currency>[^\d\s]{1,5})\s*$#", $tax, $m)
        ) {
            $h->price()
                ->tax(PriceHelper::parse($m['amount']));
        }
        $cost = $this->http->FindSingleNode("//tr[not(.//tr)][count(*) = 2][*[1][{$this->starts($this->t('Room rate - '))}][{$this->contains($this->t('night'))}]]/*[2]");

        if (preg_match("#^\s*(?<currency>[^\d\s]{1,5})\s*(?<amount>\d[\d\., ]*)\s*$#", $cost, $m)
            || preg_match("#^\s*(?<amount>\d[\d\., ]*)\s*(?<currency>[^\d\s]{1,5})\s*$#", $cost, $m)
        ) {
            $h->price()
                ->cost(PriceHelper::parse($m['amount']));
        }

        $this->detectDeadLine($h);

        return true;
    }

    private function div($title, $regexp = null)
    {
        $result = $this->http->FindSingleNode("//text()[{$this->eq($title)}]/ancestor::div[1]", null, true,
            "/^\s*{$this->opt($title)}\s*(.+)/");

        if (!empty($regexp)) {
            preg_match($regexp, $result, $m);
            $result = $m[1] ?? null;
        }

        return $result;
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

    private function detectDeadLine(\AwardWallet\Schema\Parser\Common\Hotel $h)
    {
        if (empty($cancellationText = $h->getCancellation())) {
            return;
        }

        if (
           preg_match("/If the reservation is canceled by (?<date>.+?) \((?<time>\d+:\d+(?: *[AP]M)?) [A-Z]+\), you will receive a full refund\./", $cancellationText, $m)
           || preg_match("/If the reservation is canceled by (?<date>\w+ \w+[, ]+20\d{2}) [A-Z]+, you will receive a full refund\./", $cancellationText, $m)
        ) {
            $h->booked()
               ->deadline(strtotime($m['date'] . (!empty($m['time']) ? ', ' . $m['time'] : '')));
        }

        if (preg_match("/^\s*Non-refundable\s*$/i", $cancellationText, $m)) {
            $h->booked()
               ->nonRefundable();
        }
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
}
