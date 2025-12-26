<?php

namespace AwardWallet\Engine\belmond\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class ReservationConfirmation extends \TAccountChecker
{
    public $mailFiles = "belmond/it-122614452.eml, belmond/it-123787819.eml, belmond/it-133235485.eml, belmond/it-809706886.eml";
    public $subjects = [
        '/La Residencia Reservation Confirmation/',
        '/Belmond Cadogan Reservation Confirmation/',
        '/La Samanna Reservation Confirmation/',
        '/Palacio Nazarenas A Belmond Hotel Cusco Reservation Confirmation/',
    ];

    public $lang = 'en';
    public $subject;

    public static $dictionary = [
        "en" => [
            'BOOKING CONFIRMATION' => ['BOOKING CONFIRMATION', 'Booking confirmation', 'We are pleased to confirm', 'definitely confirm'],
            'RESERVATION DETAILS'  => ['RESERVATION DETAILS', 'Reservation details', 'YOUR RESERVATION'],

            'BOOKING NUMBER:'      => ['BOOKING NUMBER:', 'Booking number:', 'Confirmation number:', 'Reservation number:'],
            'GUEST NAME:'          => ['GUEST NAME:', 'Guest Name:', 'Guest name:'],
            'NUMBER OF GUESTS:'    => ['NUMBER OF GUESTS:', 'Number of guests:', 'Number of adults:'],
            'CHECK IN:'            => ['CHECK IN:', 'Check in:'],
            'CHECK OUT:'           => ['CHECK OUT:', 'Check out:'],
            'ROOM:'                => ['ROOM:', 'Room:'],
            'ROOM RATE PER NIGHT'  => ['ROOM RATE PER NIGHT', 'Room Rate per night', 'Virtuoso Daily room rate', 'ROOM RATE PER NIGHT (excl. taxes):'],
            'GRAND TOTAL:'         => ['GRAND TOTAL:', 'Grand total:', 'Grand Total (including VAT)'],
            'Accommodation total:' => ['Accommodation total:', 'Room total:'],
            'Accommodation tax:'   => ['Accommodation tax:', 'Room tax:'],
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@belmond.com') !== false) {
            foreach ($this->subjects as $subject) {
                if (preg_match($subject, $headers['subject'])) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//text()[contains(normalize-space(), 'Belmond')]")->length > 0) {
            return $this->http->XPath->query("//text()[{$this->contains($this->t('BOOKING CONFIRMATION'))}]")->length > 0
                && $this->http->XPath->query("//text()[{$this->contains($this->t('RESERVATION DETAILS'))}]")->length > 0
                && $this->http->XPath->query("//text()[{$this->contains($this->t('GRAND TOTAL:'))}]")->length > 0;
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]belmond\.com$/', $from) > 0;
    }

    public function ParseHotel(Email $email, string $subject): void
    {
        //it-809706886.eml
        $nodes = $this->http->XPath->query("//text()[{$this->eq($this->t('GUEST NAME:'))}]/ancestor::table[2]");

        foreach ($nodes as $root) {
            $h = $email->add()->hotel();

            $guestNameVal = $this->http->FindSingleNode("./descendant::tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('GUEST NAME:'))}] ]/*[normalize-space()][2]", $root);
            $guestNames = preg_split("/\s+{$this->opt($this->t('and'))}\s+/", $guestNameVal);

            foreach ($guestNames as $gName) {
                $h->general()->traveller(str_replace(['MS. ', 'MR. ', 'MRS.'], '', $gName), strpos($gName, ' ') !== false);
            }

            $bNumberSubject = preg_match("/{$this->opt($this->t('Reservation Confirmation'))}\s*:\s*([-A-Z\d]{5,})$/", $subject, $m) > 0 ? $m[1] : null;

            $bookingNumberVal = $this->http->FindSingleNode("./descendant::tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('BOOKING NUMBER:'))}] ]/*[normalize-space()][2]", $root);
            $bookingNumbers = preg_split('/\s*\/\s*/', $bookingNumberVal);
            $confirmationTitle = $this->http->FindSingleNode("./descendant::tr[count(*[normalize-space()])=2]/*[normalize-space()][1][{$this->eq($this->t('BOOKING NUMBER:'))}]", $root, true, '/^(.+?)[\s:：]*$/u');

            foreach ($bookingNumbers as $bNumber) {
                $h->general()->confirmation($bNumber, $confirmationTitle, count($bookingNumbers) > 1 && !empty($bNumberSubject) && $bNumber === $bNumberSubject);
            }

            $cancellation = $this->http->FindSingleNode("./following::text()[normalize-space()='CANCELLATION'][1]/following::text()[string-length()>3][1]", $root);

            if (empty($cancellation)) {
                $cancellation = $this->http->FindSingleNode("./descendant::text()[normalize-space()='CANCELLATION'][1]/following::text()[string-length()>3][1]", $root);
            }

            if (empty($cancellation)) {
                $cancellation = $this->http->FindSingleNode("./following::text()[normalize-space()='YOUR ACCOMMODATION'][1]/following::text()[starts-with(normalize-space(), 'Cancellation requests must')][1]", $root);
            }

            if (empty($cancellation)) {
                $cancellation = $this->http->FindSingleNode("./descendant::text()[normalize-space()='YOUR ACCOMMODATION'][1]/following::text()[starts-with(normalize-space(), 'Cancellation requests must')][1]", $root);
            }

            if (!empty($cancellation)) {
                $h->general()
                    ->cancellation($cancellation);
            }

            $h->booked()
                ->checkIn($this->normalizeDate($this->http->FindSingleNode("./descendant::text()[{$this->eq($this->t('CHECK IN:'))}]/ancestor::tr[1]/descendant::td[2]", $root)))
                ->checkOut($this->normalizeDate($this->http->FindSingleNode("./descendant::text()[{$this->eq($this->t('CHECK OUT:'))}]/ancestor::tr[1]/descendant::td[2]", $root)));

            $timeInOut = $this->http->FindSingleNode("./descendant::text()[starts-with(normalize-space(), 'Check in/out times:')]/ancestor::tr[1]", $root);

            if (preg_match("/from\s*(?<timeIn>[\d\:]+\s*a?p?m).+at\s*(?<timeOut>noon)\./", $timeInOut, $m)) {
                if ($m[2] == 'noon') {
                    $m[2] = '12:00';
                }

                $h->booked()
                    ->checkIn(strtotime($m[1], $h->getCheckInDate()))
                    ->checkOut(strtotime($m[2], $h->getCheckOutDate()));
            }

            $checkInTime = $this->http->FindSingleNode("./descendant::text()[starts-with(normalize-space(), 'Hotel check in time:')]/ancestor::tr[1]", $root, true, "/{$this->opt($this->t('Hotel check in time:'))}\s*(\d+\:\d+)\s*\(/");
            $checkOutTime = $this->http->FindSingleNode("./descendant::text()[starts-with(normalize-space(), 'Hotel check out time:')]/ancestor::tr[1]", $root, true, "/{$this->opt($this->t('Hotel check out time:'))}\s*(\d+\:\d+)\s*\(/");

            if (!empty($checkOutTime) && !empty($checkInTime)) {
                $h->booked()
                    ->checkIn(strtotime($checkInTime, $h->getCheckInDate()))
                    ->checkOut(strtotime($checkOutTime, $h->getCheckOutDate()));
            }

            $guests = $this->http->FindSingleNode("./descendant::text()[{$this->eq($this->t('NUMBER OF GUESTS:'))}]/ancestor::tr[1]/descendant::td[2]", $root, true, "/^\s*(\d+)/");

            if (!empty($guests)) {
                $h->booked()
                    ->guests($guests);
            }

            $kids = $this->http->FindSingleNode("./descendant::text()[{$this->eq($this->t('Number of children:'))}]/ancestor::tr[1]/descendant::td[2]", $root, true, "/^\s*(\d+)/");

            if ($kids !== null) {
                $h->booked()
                    ->kids($kids);
            }

            $roomType = $this->http->FindSingleNode("./descendant::text()[{$this->eq($this->t('ROOM:'))}]/ancestor::tr[1]/descendant::td[2]", $root);
            $rate = implode(', ', $this->http->FindNodes("./descendant::text()[{$this->eq($this->t('ROOM RATE PER NIGHT'))}]/ancestor::tr[1]/following-sibling::tr/descendant::tr", $root));

            if (empty($rate)) {
                $rate = $this->http->FindSingleNode("./descendant::text()[{$this->eq($this->t('ROOM RATE PER NIGHT'))}]/ancestor::tr[1]/descendant::td[2]", $root, true, "/^\s*(.*[A-Z]{3}\s*[\d\,\.]+.*)/");
            }

            if (!empty($rate) > 0 || !empty($roomType)) {
                $room = $h->addRoom();

                if (!empty($rate)) {
                    $room->setRate($rate);
                }

                if (!empty($roomType)) {
                    $room->setType($roomType);
                }
            }

            $price = $this->http->FindSingleNode("./descendant::text()[{$this->eq($this->t('GRAND TOTAL:'))}]/ancestor::tr[1]/descendant::td[2]", $root);

            if (preg_match("/^([A-Z]{3})\s+([\d\,\.]+)$/", $price, $m)
                || preg_match("/^(\D+)\s+([\d\.\,]+)/", $price, $m)
            ) {
                $h->price()
                    ->total(PriceHelper::parse($m[2], $this->normalizeCurrency($m[1])))
                    ->currency($this->normalizeCurrency($m[1]));

                $cost = $this->http->FindSingleNode("./descendant::text()[{$this->eq($this->t('Accommodation total:'))}]/ancestor::tr[1]/descendant::td[2]", $root, true, "/^[A-Z]{3}\s*([\d\.\,]+)/");

                if (!empty($cost)) {
                    $h->price()
                        ->cost(PriceHelper::parse($cost, $this->normalizeCurrency($m[1])));
                }

                $tax = $this->http->FindSingleNode("./descendant::text()[{$this->eq($this->t('Accommodation tax:'))}]/ancestor::tr[1]/descendant::td[2]", $root, true, "/^[A-Z]{3}\s*([\d\.\,]+)/");
                $this->logger->debug($tax);

                if (!empty($tax)) {
                    $h->price()
                        ->tax(PriceHelper::parse($tax, $this->normalizeCurrency($m[1])));
                }
            }

            $hotelName = $this->http->FindSingleNode("//text()[contains(normalize-space(), 'Your booking confirmation')]", null, true, "/^(.+)\s*\-\s*{$this->opt($this->t('Your booking confirmation'))}/");

            if (empty($hotelName)) {
                $hotelName = $this->http->FindSingleNode("//text()[contains(normalize-space(), 'the following reservation at ')]", null, true, "/\s+at\s+(.+)\:/");
            }

            if (empty($hotelName)) {
                $hotelName = $this->re("/^(.+)\s+{$this->opt($this->t('Confirmation:'))}/", $subject);
            }

            if (!empty($hotelName)) {
                $h->hotel()
                    ->name($hotelName);
            }

            $phone = $this->http->FindSingleNode("//img[contains(@src, 'instagram')]/preceding::text()[normalize-space()][not(contains(normalize-space(), '@'))][1]", null, true, "/^\s*([+][\s\d]+)$/");

            if (empty($phone)) {
                $phone = $this->http->FindSingleNode("//text()[normalize-space()='reservations.lrs@belmond.com']/preceding::text()[normalize-space()][1]", null, true, "/^\s*([+][\s\d]+)$/");
            }

            if (!empty($phone)) {
                $h->hotel()
                    ->phone($phone);

                $address = implode(' ', $this->http->FindNodes("//img[contains(@src, 'instagram')]/preceding::text()[normalize-space()][not(contains(normalize-space(), '@'))][2]/ancestor::td[1]/descendant::text()[normalize-space()]"));

                if (!empty($address)) {
                    $h->hotel()
                        ->address($address);
                }
            }

            if (empty($h->getAddress())) {
                $address = $this->http->FindSingleNode("//text()[normalize-space()='reservations.lrs@belmond.com']/preceding::text()[normalize-space()][2]/ancestor::tr[1]");
            }

            if (empty($h->getAddress()) && empty($address)) {
                $address = $this->http->FindSingleNode(" //text()[starts-with(normalize-space(), 'Registered address:')]", null, true, "/{$this->opt($this->t('Registered address:'))}\s*(.+)/");
            }

            if (!empty($address)) {
                $h->hotel()
                    ->address($address);
            } else {
                if ($this->http->XPath->query("//img[contains(@src, 'instagram')]/preceding::text()[normalize-space()][not(contains(normalize-space(), '@'))][2]/ancestor::td[1]/descendant::text()[normalize-space()]")->length == 0
                    && $this->http->XPath->query("//text()[starts-with(normalize-space(), 'Registered address:')]")->length == 0) {
                    $h->hotel()->noAddress();
                }
            }

            $this->detectDeadLine($h);
        }
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->subject = $parser->getSubject();

        $this->ParseHotel($email, $parser->getSubject());

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

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) {
            return "contains(normalize-space(.), \"{$s}\")";
        }, $field));
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s, '/'));
        }, $field)) . ')';
    }

    private function normalizeDate($date)
    {
        //$this->logger->debug($date);

        $in = [
            '#^\w+\,\s*(\d+\s*\w+\s*\d{4})\s\(([\d\:]+\s*A?P?M?)\)$#', //THURSDAY, 02 DEC 2021 (15:00)
            '#^([\d\:]+)\s+\([\d\:]+\s*A?P?M\)\s*\w+\,\s*(\w+)\s*(\d+)\,\s*(\d{4})$#', //14:00 (2:00 PM) Saturday, January 01, 2022
            '#^(\d+\s*\w+\s*\d{4})\s*\(\d+\D+$#', //10 July 2022 (4 nights)
            '#^\w+\,\s*(\d+\s*\w+\s*\d{4})\s*\((?:CHECK-IN:|CHECK-OUT:)\s*([\d\:]+)\)$#',
            '#^(\d+)th\s*(\w+)\s*(\d{4})\s+\(\d+\D+$#', //10th July 2022 (4 nights)
        ];
        $out = [
            '$1, $2',
            '$3 $2 $4, $1',
            '$1',
            '$1',
            '$1 $2 $3',
        ];
        $str = preg_replace($in, $out, $date);

        return strtotime($str);
    }

    private function detectDeadLine(\AwardWallet\Schema\Parser\Common\Hotel $h): void
    {
        if (empty($cancellationText = $h->getCancellation())) {
            return;
        }

        if (
            preg_match('/Cancel by (?<time>\d+a?p?m) local time (?<prior>\d+\s*hours?) prior to arrival or/', $cancellationText, $m)
            || preg_match('/Cancel by (?<time>\d+a?p?m) local time (?<prior>\d+\s*days?) prior to arrival or/i', $cancellationText, $m)
            || preg_match('/Cancel by (?<time>\d+a?p?m) (?<prior>\d+\s*days?) prior to arrival or pay full stay plus tax/', $cancellationText, $m)
            || preg_match('/Cancellation requests must be received by (?<time>[\d\:]+) local time (?<prior>\d+\s*days?) prior to/u', $cancellationText, $m)
        ) {
            $h->booked()->deadlineRelative($m['prior'], $m['time']);
        }
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(" or ", array_map(function ($s) {
            return "normalize-space(.)=\"{$s}\"";
        }, $field)) . ')';
    }

    private function normalizeCurrency($string)
    {
        $string = trim($string);
        $currencies = [
            'GBP' => ['£'],
            'EUR' => ['€', 'Euro'],
            'USD' => ['US Dollar'],
        ];

        foreach ($currencies as $code => $currencyFormats) {
            foreach ($currencyFormats as $currency) {
                if ($string === $currency) {
                    return $code;
                }
            }
        }

        return $string;
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }
}
