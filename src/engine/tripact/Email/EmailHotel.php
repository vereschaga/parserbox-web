<?php

namespace AwardWallet\Engine\tripact\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class EmailHotel extends \TAccountChecker
{
    public $mailFiles = "tripact/it-40652601.eml, tripact/it-40652602.eml, tripact/it-82541419.eml";

    public $reFrom = ["@tripactions.com"];
    public $reBody = [
        'en' => ['Hotel Information'],
    ];
    public $reSubject = [
        '#\S+\@\S+$#',
    ];
    public $lang = '';
    public static $dict = [
        'en' => [
            'Hotel Name:'     => 'Hotel Name:',
            'Address:'        => 'Address:',
            'Canceled'        => ['Canceled', 'Cancelled'],
            'Taxes and Fees:' => ['Taxes and Fees:', 'Taxes and fee:'],
        ],
    ];
    private $keywordProv = 'TripActions';

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        if (!$this->assignLang()) {
            $this->logger->debug('can\'t determine a language');

            return $email;
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        $this->parseEmail($email);

        return $email;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//img[contains(@src,'.tripactions.com')]| //a[contains(@href,'.tripactions.com')]")->length > 0) {
            foreach ($this->reBody as $lang => $reBody) {
                if ($this->http->XPath->query("//*[{$this->contains($reBody)}]")->length > 0) {
                    return $this->assignLang();
                }
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        foreach ($this->reFrom as $reFrom) {
            if (stripos($from, $reFrom) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        $fromProv = true;

        if (!isset($headers['from']) || self::detectEmailFromProvider($headers['from']) === false) {
            $fromProv = false;
        }

        if (isset($headers["subject"])) {
            foreach ($this->reSubject as $reSubject) {
                if (($fromProv || stripos($headers["subject"], $this->keywordProv) !== false)
                    && preg_match($reSubject, $headers["subject"]) > 0
                ) {
                    return true;
                }
            }
        }

        return false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    private function parseEmail(Email $email)
    {
        $r = $email->add()->hotel();

        $r->ota()
            ->phone($phone = $this->http->FindSingleNode("//text()[{$this->starts($this->t('for live travel support from our travel team 24/7'))}]/preceding::text()[normalize-space()!=''][1]"),
                $this->t('for live travel support from our travel team 24/7'));
        $phones[] = str_replace(' ', '', $phone);
        $node = implode("\n",
            $this->http->FindNodes("//text()[{$this->eq($this->t('International support:'))}]/ancestor::div[1]/descendant::text()[normalize-space()!='']"));
        $node = $this->re("#{$this->t('International support:')}(.+)#s", $node);

        if (preg_match_all("#^(.+?):\n([+\-\d() ]+)\n#m", $node, $m, PREG_SET_ORDER)) {
            foreach ($m as $value) {
                $phone = str_replace(' ', '', $value[2]);

                if (!in_array($phone, $phones)) {
                    $r->ota()
                        ->phone($phone, $this->t('International support:') . $value[1]);
                    $phones[] = $phone;
                }
            }
        }

        $confirmation = $this->nextTd($this->t('Trip Number:'));

        if (empty($confirmation)) {
            $confirmation = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Trip Number:'))}]/following::text()[normalize-space()][1]");
        }

        $r->general()
            ->confirmation($confirmation);

        $traveller = $this->nextTd($this->t('Guest Name:'), null, true);

        if (empty($traveller)) {
            $traveller = array_unique($this->http->FindNodes("//text()[{$this->eq($this->t('Guest Name:'))}]/following::text()[normalize-space()][1]"));
        }

        if (!is_array($traveller)) {
            $r->general()
                ->traveller($traveller, true);
        } else {
            $r->general()
                ->travellers($traveller, true);
        }

        $status = $this->nextTd($this->t('Booking Status:'));

        if (empty($status)) {
            $status = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Booking Status:'))}]/following::text()[normalize-space()][1]");
        }

        $r->general()
            ->status($status);

        if ($cancel = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Cancellation Policy'))}]/ancestor::*[1]/following::*[normalize-space()!=''][1][not({$this->eq($this->t('Need help?'))})]")) {
            $r->general()
                ->cancellation($cancel);
        }

        if (in_array($status, (array) $this->t('Canceled'))) {
            $r->general()->cancelled();
        }

        $hotelName = $this->nextTd($this->t('Hotel Name:'));

        if (empty($hotelName)) {
            $hotelName = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Hotel Name:'))}]/following::text()[normalize-space()][1]");
        }

        $address = $this->nextTd($this->t('Address:'));

        if (empty($address)) {
            $address = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Address:'))}]/following::text()[normalize-space()][1]");
        }

        $phone = $this->nextTd($this->t('Phone:'));

        if (empty($phone)) {
            $phone = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Phone:'))}]/following::text()[normalize-space()][1]");
        }

        $r->hotel()
            ->name($hotelName)
            ->address($address)
            ->phone($phone);

        $room = $r->addRoom();

        $roomType = $this->nextTd($this->t('Room Type:'));

        if (empty($roomType)) {
            $roomType = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Room Type:'))}]/following::text()[normalize-space()][1]");
        }

        $roomRate = $this->nextTd($this->t('Room cost per night:'));

        if (empty($roomRate)) {
            $roomRate = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Room cost per night:'))}]/following::text()[normalize-space()][1]/ancestor::tr[1]");
        }

        $room
            ->setType($roomType)
            ->setRate(preg_replace("#(\d+\.\d{2})\d{10,}#", '$1', $roomRate));

        $room = $this->nextTd($this->t('Rooms:'));

        if (empty($room)) {
            $room = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Rooms:'))}]/following::text()[normalize-space()][1]/ancestor::tr[1]");
        }

        $guests = $this->nextTd($this->t('Guests:'));

        if (empty($guests)) {
            $guests = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Guests:'))}]/following::text()[normalize-space()][1]/ancestor::tr[1]");
        }

        $checkIn = $this->nextTd($this->t('Check In:'));

        if (empty($checkIn)) {
            $checkIn = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Check In:'))}]/following::text()[normalize-space()][1]");
        }

        $checkOut = $this->nextTd($this->t('Check Out:'));

        if (empty($checkOut)) {
            $checkOut = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Check Out:'))}]/following::text()[normalize-space()][1]");
        }

        $r->booked()
            ->rooms($this->re("#(\d+)\s+{$this->opt($this->t('room'))}#", $room))
            ->guests($this->re("#(\d+)\s*{$this->opt($this->t('guest'))}#", $guests))
            ->checkIn($this->normalizeDate($checkIn))
            ->checkOut($this->normalizeDate($checkOut));

        $total = $this->nextTd($this->t('Total Price:'));

        if (empty($total)) {
            $total = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Total Price:'))}]/following::text()[normalize-space()][1]/ancestor::tr[1]", null, true, "/\s*([\d\.]+)/u");
        }

        $tax = $this->nextTd($this->t('Taxes and Fees:'));

        if (empty($tax)) {
            $tax = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Taxes and Fees:'))}]/following::text()[normalize-space()][1]/ancestor::tr[1]", null, true, "/\s*([\d\.]+)/u");
        }

        $currency = $this->http->FindSingleNode("//text()[normalize-space(.)='Total Price:']/ancestor::tr[1]/descendant::td[last()]", null, true, "/([A-Z]{3})/");

        if (empty($currency)) {
            $currency = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Total Price:'))}]/following::text()[normalize-space()][1]/ancestor::tr[1]", null, true, "/([A-Z]{3})/u");
        }

        $r->price()
            ->total(cost($total))
            ->tax(cost($tax))
            ->currency($currency);

        $this->detectDeadLine($r);

        return true;
    }

    private function nextTd($field, $root = null, $onlyFirst = false)
    {
        if ($onlyFirst) {
            return $this->http->FindSingleNode("(//text()[{$this->eq($field)}])[1]/ancestor::td[1]/following-sibling::td[normalize-space()!=''][1]",
                $root);
        } else {
            return $this->http->FindSingleNode("//text()[{$this->eq($field)}]/ancestor::td[1]/following-sibling::td[normalize-space()!=''][1]",
                $root);
        }
    }

    private function normalizeDate($date)
    {
        $in = [
            //Wed, Jun 19, 2019
            '#^(\w+),\s+(\w+)\s+(\d+),\s+(\d{4})$#u',
            // Jul 18, 2019, 4:00 pm
            '#^(\w+)\s+(\d+),\s+(\d{4}),\s+(\d+:\d+(?:\s*[ap]m)?)$#ui',
        ];
        $out = [
            '$3 $2 $4',
            '$2 $1 $3, $4',
        ];
        $str = strtotime($this->dateStringToEnglish(preg_replace($in, $out, $date)));

        return $str;
    }

    /**
     * Here we describe various variations in the definition of dates deadLine.
     */
    private function detectDeadLine(\AwardWallet\Schema\Parser\Common\Hotel $h)
    {
        if (empty($cancellationText = $h->getCancellation())) {
            return;
        }

        if (preg_match("#If cancelled before (?<month>\d{2})\/(?<day>\d{2}) (?<time>\d+:\d+), no fee will be charged\.#i",
            $cancellationText, $m)
        ) {
            $date = strtotime(date('Y', $h->getCheckInDate()) . '-' . $m['month'] . '-' . $m['day']);

            if ($date > $h->getCheckInDate()) {
                $date = strtotime("-1 year", $date);
            }
            $h->booked()
                ->deadline(strtotime($m['time'], $date));
        } elseif (
        preg_match("#we are required to pass on: Cancellations or changes made after (?<time>.+?) \(.+\) on (?<date>.+) are subject to a 1 Night Room#",
            $cancellationText, $m)
        ) {
            $h->booked()
                ->deadline($this->normalizeDate($m['date'] . ', ' . $m['time']));
        }

        $h->booked()
            ->parseNonRefundable("#^This rate is non-refundable\.#")
            ->parseNonRefundable("#^You will be charged \d+\% of the total price if you cancel your booking.$#");
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function assignLang()
    {
        foreach (self::$dict as $lang => $words) {
            if (isset($words['Hotel Name:'], $words['Address:'])) {
                if ($this->http->XPath->query("//*[{$this->contains($words['Hotel Name:'])}]")->length > 0
                    && $this->http->XPath->query("//*[{$this->contains($words['Address:'])}]")->length > 0
                ) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function dateStringToEnglish($date)
    {
        if (preg_match('#[[:alpha:]]+#iu', $date, $m)) {
            $monthNameOriginal = $m[0];

            if ($translatedMonthName = MonthTranslate::translate($monthNameOriginal, $this->lang)) {
                return preg_replace("#$monthNameOriginal#i", $translatedMonthName, $date);
            }
        }

        return $date;
    }

    private function getTotalCurrency($node)
    {
        $node = str_replace(["€", "£", "$", "₹"], ["EUR", "GBP", "USD", "INR"], $node);
        $tot = '';
        $cur = '';

        if (preg_match("#(?<c>[A-Z]{3})\s*(?<t>\d[\.\d\,\s]*\d*)#", $node, $m)
            || preg_match("#(?<t>\d[\.\d\,\s]*\d*)\s*(?<c>[A-Z]{3})#", $node, $m)
        ) {
            $cur = $m['c'];
            $tot = PriceHelper::cost($m['t']);
        }

        return ['Total' => $tot, 'Currency' => $cur];
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(' or ', array_map(function ($s) {
            return 'normalize-space(.)="' . $s . '"';
        }, $field));
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(' or ', array_map(function ($s) {
            return 'starts-with(normalize-space(.),"' . $s . '")';
        }, $field));
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(' or ', array_map(function ($s) {
            return 'contains(normalize-space(.),"' . $s . '")';
        }, $field));
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
    }
}
