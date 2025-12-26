<?php

namespace AwardWallet\Engine\yatra\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class ETicketDetails extends \TAccountChecker
{
    public $mailFiles = "yatra/it-197706503.eml, yatra/it-28251051.eml, yatra/it-49072666.eml, yatra/it-53733658.eml";
    private $subjects = [
        'en' => ['Electronic Ticket Details', 'Hotel Confirmation Voucher'],
    ];
    private $langDetectors = [
        'en' => ['Electronic Ticket Details', 'Hotel Confirmation Voucher', 'Cart Details'],
    ];
    private $lang = '';
    private static $dict = [
        'en' => [
            'Reference Number' => ['Yatra Reference Number', 'Reference Number', 'Test Reference Number'],
            'Inclusions:'      => ['Inclusions:', 'Inclusions :'],
        ],
    ];
    private static $providers = [
        'yatra' => [
            'from' => ['@yatra.com'],
            'body' => [
                '//a[contains(@href,"//corporate.yatra.com")]',
            ],
        ],
        'thomascook' => [
            'from' => ['@in.thomascook.com'],
            'body' => [
                '//a[contains(@href,".thomascook.in")]',
            ],
        ],
        'fcmtravel' => [
            'from' => ['@fcmonline.in'],
            'body' => [
                '//a[contains(@href,".fcmonline.in")]',
            ],
        ],
    ];

    public function detectEmailFromProvider($from)
    {
        foreach (self::$providers as $code => $arr) {
            foreach ($arr['from'] as $f) {
                if (stripos($from, $f) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (self::detectEmailFromProvider($headers['from']) !== true) {
            return false;
        }

        foreach ($this->subjects as $phrases) {
            foreach ($phrases as $phrase) {
                if (stripos($headers['subject'], $phrase) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        // Detect Provider
        if (null === $this->getProvider(implode(' ', $parser->getFrom()))) {
            return false;
        }

        return $this->assignLang();
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        if (!$this->assignLang()) {
            $this->logger->notice("Can't determine a language!");

            return $email;
        }

        if ($this->http->XPath->query("//text()[{$this->eq('Hotel Confirmation Voucher')}]/ancestor::*[1]")->length > 0) {
            $this->parseEmailHotel($email);
        } else {
            $this->parseEmail($email);
        }
        $email->setType('ETicketDetails' . ucfirst($this->lang));

        if ($provider = $this->getProvider(implode(' ', $parser->getFrom()))) {
            $email->setProviderCode($provider);
        }

        return $email;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        $types = 2; // flight + hotels
        $cnt = $types * count(self::$dict);

        return $cnt;
    }

    public static function getEmailProviders()
    {
        return array_keys(self::$providers);
    }

    private function parseEmail(Email $email)
    {
        $xpathFragmentTd = '(self::td or self::th)';

        // ta.confirmation number
        $referenceNumberTitle = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Reference Number'))}]");
        $referenceNumber = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Reference Number'))}]/ancestor::*[{$xpathFragmentTd}][1]/following-sibling::*[normalize-space(.)][last()]", null, true, '/^([A-Z\d]{5,})$/');
        $email->ota()->confirmation($referenceNumber, $referenceNumberTitle);

        $f = $email->add()->flight();

        // reservationDate
        $generationTime = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Generation Time'))}]/ancestor::*[{$xpathFragmentTd}][1]/following-sibling::*[normalize-space(.)][last()]");
        $f->general()->date2($generationTime);

        // status
        $bookingStatus = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Booking Status'))}]/ancestor::*[{$xpathFragmentTd}][1]/following-sibling::*[normalize-space(.)][last()]");
        $f->general()->status($bookingStatus);

        // segments
        $this->logger->debug("//tr[ ./descendant::text()[normalize-space(.)][1][{$this->eq($this->t('Flight'))}] and ./following-sibling::*[normalize-space(.)][1]/descendant::text()[normalize-space(.)][1][{$this->eq($this->t('Departure'))}] ]");
        $segments = $this->http->XPath->query("//tr[ ./descendant::text()[normalize-space(.)][1][{$this->eq($this->t('Flight'))}] and ./following-sibling::*[normalize-space(.)][1]/descendant::text()[normalize-space(.)][1][{$this->eq($this->t('Departure'))}] ]");

        foreach ($segments as $segment) {
            $s = $f->addSegment();

//            $header = $this->http->FindSingleNode("./preceding-sibling::*[ ./descendant::h1[{$this->contains($this->t(' to '))}] ][1]", $segment);
//            if ( preg_match('/^(.{6,}?)\s+-\s+/', $header, $m) ) { // 03/05/2016 - Mumbai to Chandigarh - by Air
//            }

            // airlineName
            // flightNumber
            $flight = $this->http->FindSingleNode("./descendant::text()[normalize-space(.)][1][{$this->eq($this->t('Flight'))}]/ancestor::*[{$xpathFragmentTd}][1]/following-sibling::*[normalize-space(.)][last()]", $segment);

            if (preg_match('/\b(?<airline>[A-Z][A-Z\d]|[A-Z\d][A-Z])[-\s]*(?<flightNumber>\d+)$/', $flight, $m)) {
                $s->airline()
                    ->name($m['airline'])
                    ->number($m['flightNumber'])
                ;
            }

            // 15:05, Tue 03-May : Mumbai, IN (Chhatrapati Shivaji Intl - BOM) : Terminal 1B
            // 07:30 13-Jan-2020 : Mumbai, IN (Chhatrapati Shivaji - BOM) : Terminal 2
            // 16:5:0 17-Feb-2020 : Chennai, IN (Chennai Arpt - MAA) : Terminal 1 ||||||| checked in fs 16:05
            $patterns['dateAirport'] = '/^(?<time>\d{1,2}:\d{1,2}(?::\d+)?(?:\s*[AaPp]\.?[Mm]\.?)?)\s*,?\s*'
                . '(?<date>.{6,}?)\s*:\s*[^:]{3,}?\([^)(]*(?<airport>[A-Z]{3})\s*\)(?:\s*:\s*Terminal(?:\s+Terminal)?\s+(?<terminal>[A-z\d\s]+))?$/';

            // depDate
            // depCode
            // depTerminal
            $departure = $this->http->FindSingleNode("./following-sibling::*[normalize-space(.)][1]/descendant::text()[normalize-space(.)][1][{$this->eq($this->t('Departure'))}]/ancestor::*[{$xpathFragmentTd}][1]/following-sibling::*[normalize-space(.)][last()]", $segment);

            if (preg_match($patterns['dateAirport'], $departure, $m)) {
                $dateDep = $this->normalizeDate($m['date']);

                if (!empty($f->getReservationDate()) && $dateDep) {
                    $dateDep = EmailDateHelper::parseDateRelative($dateDep, $f->getReservationDate());
                    $m['time'] = preg_replace("/\b(\d+:\d+):\d+/", '$1', $m['time']);
                    $s->departure()->date(strtotime($m['time'], $dateDep));
                }
                $s->departure()->code($m['airport']);

                if (isset($m['terminal']) && !empty($m['terminal'])) {
                    $s->departure()->terminal($m['terminal']);
                }
            }

            // arrDate
            // arrCode
            // arrTerminal
            $arrival = $this->http->FindSingleNode("./following-sibling::*[normalize-space(.)][2]/descendant::text()[normalize-space(.)][1][{$this->eq($this->t('Arrival'))}]/ancestor::*[{$xpathFragmentTd}][1]/following-sibling::*[normalize-space(.)][last()]", $segment);

            if (preg_match($patterns['dateAirport'], $arrival, $m)) {
                $dateArr = $this->normalizeDate($m['date']);

                if (!empty($f->getReservationDate()) && $dateArr) {
                    $dateArr = EmailDateHelper::parseDateRelative($dateArr, $f->getReservationDate());
                    $m['time'] = preg_replace("/\b(\d+:\d+):\d+/", '$1', $m['time']);
                    $s->arrival()->date(strtotime($m['time'], $dateArr));
                }
                $s->arrival()->code($m['airport']);

                if (isset($m['terminal']) && !empty($m['terminal'])) {
                    $s->arrival()->terminal($m['terminal']);
                }
            }

            // cabin
            // bookingCode

            $meal = $this->http->FindSingleNode("./following::tr[starts-with(normalize-space(), 'Name') and contains(normalize-space(), 'Airline PNR')][1]/descendant::text()[contains(normalize-space(), 'Meal:')]", $segment, true, "/{$this->opt($this->t('Meal:'))}\s*(\D+)\,\s*{$this->opt($this->t('Seat:'))}/");

            if (!empty($meal)) {
                $s->extra()
                    ->meal($meal);
            }

            $seat = $this->http->FindSingleNode("./following::tr[starts-with(normalize-space(), 'Name') and contains(normalize-space(), 'Airline PNR')][1]/descendant::text()[contains(normalize-space(), 'Seat:')]", $segment, true, "/{$this->opt($this->t('Seat:'))}\s*(\d+[A-Z])\s*\,/u");

            if (!empty($seat)) {
                $s->extra()
                    ->seat($seat);
            }

            $class = $this->http->FindSingleNode("./following-sibling::*[normalize-space(.)][3]/descendant::text()[normalize-space(.)][1][{$this->eq($this->t('Class'))}]/ancestor::*[{$xpathFragmentTd}][1]/following-sibling::*[normalize-space(.)][last()]", $segment);

            if (preg_match('/^(.{3,})\s*\(\s*([A-Z]{1,2})\s*\)$/', $class, $m)) {
                $s->extra()
                    ->cabin($m[1])
                    ->bookingCode($m[2])
                ;
            } elseif ($class) {
                $s->extra()->cabin($class);
            }
        }

        $xpathFragment1 = "//tr[ ./*[1][{$this->contains($this->t('Name'))}] and ./*[3][{$this->contains($this->t('Airline PNR'))}] and ./*[5][{$this->contains($this->t('Ticket No'))}] ]/following-sibling::tr[normalize-space(.)][count(*[normalize-space()]) > 1]";
        $this->logger->debug($xpathFragment1);

        // travellers
        $traveller = $this->http->FindNodes($xpathFragment1 . '/*[1]', null, '/^\s*(?:(?:Mr|Ms|Miss|Mstr|Dr) )?([[:alpha:]][-.\'[:alpha:]\s]*[[:alpha:]])\s*(?:\s*\([^)(]+\))?$/u');
        $f->general()->travellers(array_unique($traveller));

        // confirmation number
        $confirmationNumberTitle = $this->http->FindSingleNode("(//tr/*[3][{$this->contains($this->t('Airline PNR'))}])[1]");
        $confirmationNumber = array_unique($this->http->FindNodes($xpathFragment1 . '/*[3]', null, '/^([A-Z\d]{5,})$/'));

        foreach ($confirmationNumber as $item) {
            $f->general()->confirmation($item, $confirmationNumberTitle);
        }

        // ticketNumbers
        $ticketNo = array_filter($this->http->FindNodes($xpathFragment1 . '/*[5]', null, '/^\d{3}[-\s]*\d{4,}$/'));

        if (!empty($ticketNo)) {
            $f->issued()->tickets(array_unique($ticketNo), false);
        }

        // p.total
        // p.currencyCode
        // p.discount
        $payment = $this->http->FindSingleNode("//descendant::text()[{$this->starts($this->t('Total Price'))}]/ancestor::*[{$xpathFragmentTd}][1]/following-sibling::*[normalize-space(.)][last()]");

        if (preg_match('/^(?<amount>\d[,.\'\d]*)\s*(?<currency>[A-Z]{3})\b/', $payment, $matches)) {
            // 6,966 INR
            $f->price()
                ->total($this->normalizeAmount($matches['amount']))
                ->currency($matches['currency'])
            ;

            $discount = $this->http->FindSingleNode("//descendant::text()[{$this->eq($this->t('Discount'))}]/ancestor::*[{$xpathFragmentTd}][1]/following-sibling::*[normalize-space(.)][last()]");

            if (preg_match('/^-?(?<amount>\d[,.\'\d]*)\s*' . preg_quote($matches['currency'], '/') . '\b/', $discount, $m)) {
                $f->price()->discount($this->normalizeAmount($m['amount']));
            }
        }

        // cancellation
        $termsConditions = $this->http->FindNodes("//text()[{$this->eq($this->t('Terms and Conditions'))}]/following::tr[normalize-space(.)][1]/descendant::li[normalize-space(.)]", null, '/^(.+?)[,.!?;]+$/');

        if (count($termsConditions)) {
            $termsConditions = array_filter($termsConditions, function ($item) {
                return stripos($item, 'cancel') !== false;
            });
            $cancellationText = implode('. ', $termsConditions);

            if (!empty($cancellationText)) {
                $f->general()->cancellation($cancellationText);
            }
        }
    }

    private function parseEmailHotel(Email $email)
    {
        $xpathFragmentTd = '(self::td or self::th)';

        // ta.confirmation number
        $referenceNumberTitle = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Reference Number'))}]");
        $referenceNumber = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Reference Number'))}]/ancestor::*[{$xpathFragmentTd}][1]/following-sibling::*[normalize-space(.)][last()]", null, true, '/^([A-Z\d]{5,})$/');
        $email->ota()->confirmation($referenceNumber, $referenceNumberTitle);

        $f = $email->add()->hotel();

        // reservationDate
        $generationTime = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Generation Time'))}]/ancestor::*[{$xpathFragmentTd}][1]/following-sibling::*[normalize-space(.)][last()]");
        $f->general()->date2($generationTime);

        // status
        $bookingStatus = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Booking Status'))}]/ancestor::*[{$xpathFragmentTd}][1]/following-sibling::*[normalize-space(.)][last()]");
        $f->general()->status($bookingStatus);

        $xpathFragment1 = "//tr[ ./*[1][{$this->contains($this->t('Room Type'))}] and ./*[2][{$this->contains($this->t('Guest Name'))}] and ./*[4][{$this->contains($this->t('CheckIn Date'))}] ]/following-sibling::tr[normalize-space(.)][1]";

        // travellers
        $traveller = $this->http->FindNodes($xpathFragment1 . '/*[2]', null, '/^([[:alpha:]][-.\'[:alpha:]\s]*[[:alpha:]])\s*(?:\s*\([^)(]+\))?$/u');
        $f->general()->travellers(array_unique($traveller));

        // confirmation number
        $confirmationNumberTitle = $this->http->FindSingleNode("(//tr/*[6][{$this->contains($this->t('Confirmation Id'))}])[1]");
        $confirmationNumber = array_unique($this->http->FindNodes($xpathFragment1 . '/*[6]', null, '/^([A-Z\d]{5,})$/'));

        foreach ($confirmationNumber as $item) {
            $f->general()->confirmation($item, $confirmationNumberTitle);
        }

        $xpathFragment2 = "//tr[ ./*[1][{$this->contains($this->t('Hotel Name'))}] and ./*[3][{$this->contains($this->t('Address'))}] and ./*[4][{$this->contains($this->t('Phone Numbers'))}] ]/following-sibling::tr[normalize-space(.)][1]";
        $f->hotel()
            ->name($this->http->FindSingleNode("{$xpathFragment2}/td[1]"))
            ->address($this->http->FindSingleNode("{$xpathFragment2}/td[3]"))
            ->phone($this->http->FindSingleNode("{$xpathFragment2}/td[4]"));

        $room = $f->addRoom();
        $room
            ->setType($this->http->FindSingleNode("{$xpathFragment1}/td[1]"));
        $descr = $this->http->FindSingleNode("{$xpathFragment1}/following::text()[normalize-space()!=''][1][{$this->starts($this->t('Inclusions:'))}]",
            null, false, "/:\s*(.+)/");

        if (!empty($descr)) {
            $room->setDescription($descr);
        }

        $guests = $this->http->FindSingleNode("{$xpathFragment1}/td[3]", null, false, "/(\d+) Adult/");
        $f->booked()
            ->guests($guests)
            ->checkIn2($this->normalizeDate($this->http->FindSingleNode("{$xpathFragment1}/td[4]")))
            ->checkOut2($this->normalizeDate($this->http->FindSingleNode("{$xpathFragment1}/td[5]")));

        // p.total
        // p.currencyCode
        // p.discount
        $payment = $this->http->FindSingleNode("//descendant::text()[{$this->starts($this->t('Total Price'))}]/ancestor::*[{$xpathFragmentTd}][1]/following-sibling::*[normalize-space(.)][last()]");

        if (preg_match('/^(?<amount>\d[,.\'\d]*)\s*(?<currency>[A-Z]{3})\b/', $payment, $matches)) {
            // 6,966 INR
            $f->price()
                ->total($this->normalizeAmount($matches['amount']))
                ->currency($matches['currency'])
            ;

            $discount = $this->http->FindSingleNode("//descendant::text()[{$this->eq($this->t('Discount'))}]/ancestor::*[{$xpathFragmentTd}][1]/following-sibling::*[normalize-space(.)][last()]");

            if (preg_match('/^-?(?<amount>\d[,.\'\d]*)\s*' . preg_quote($matches['currency'], '/') . '\b/', $discount, $m)) {
                $f->price()->discount($this->normalizeAmount($m['amount']));
            }
        }

        // cancellation
        $cancellationText = implode("; ", $this->http->FindNodes("{$xpathFragment1}/following::text()[normalize-space()!=''][position()<3][{$this->starts($this->t('Hotel Policies and Cancellation Rules'))}]/ancestor::*[1]/following-sibling::ul/descendant::text()[normalize-space()!='']"));
        $f->general()->cancellation($cancellationText);
        $this->detectDeadline($f);
    }

    /**
     * Here we describe various variations in the definition of dates deadLine.
     */
    private function detectDeadLine(\AwardWallet\Schema\Parser\Common\Hotel $h)
    {
        if (empty($cancellationText = $h->getCancellation())) {
            return;
        }

        if (preg_match("/(\d+)HRS PRIOR TO CHECKIN TO AVOID 1NT FEE/ui", $cancellationText, $m)
        ) {
            $h->booked()
                ->deadlineRelative($m[1] . 'hours');
        }
    }

    /**
     * Dependencies `use AwardWallet\Engine\MonthTranslate` and `$this->lang`.
     *
     * @return string|bool
     */
    private function normalizeDate(string $string)
    {
        //$this->logger->notice($string);
        // Tue 03-May
        if (preg_match('/^[^\d\W]{2,}\s+(\d{1,2})-?([^\d\W]{3,})$/u', $string, $matches)) {
            $day = $matches[1];
            $month = $matches[2];
            $year = '';
        }
        // 13-Jan-2020
        if (preg_match('/^(\d{1,2})-(\w{3})-(\d{4})$/', $string, $matches)) {
            $day = $matches[1];
            $month = $matches[2];
            $year = $matches[3];
        }
        // 29/02/2020
        if (preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/', $string, $matches)) {
            $day = $matches[1];
            $month = $matches[2];
            $year = $matches[3];

            return $year . '-' . $month . '-' . $day;
        }

        if (isset($day) && isset($month) && isset($year)) {
            if (preg_match('/^\s*(\d{1,2})\s*$/', $month, $m)) {
                return $m[1] . '/' . $day . ($year ? '/' . $year : '');
            }

            if ($this->lang !== 'th') {
                $month = str_replace('.', '', $month);
            }

            if (($monthNew = MonthTranslate::translate($month, $this->lang)) !== false) {
                $month = $monthNew;
            }

            return $day . ' ' . $month . ($year ? ' ' . $year : '');
        }

        return false;
    }

    /**
     * @param string $string Unformatted string with amount
     */
    private function normalizeAmount(string $s): string
    {
        $s = preg_replace('/\s+/', '', $s);             // 11 507.00  ->  11507.00
        $s = preg_replace('/[,.\'](\d{3})/', '$1', $s); // 2,790      ->  2790    |    4.100,00  ->  4100,00    |    1'619.40  ->  1619.40
        $s = preg_replace('/,(\d{2})$/', '.$1', $s);    // 18800,00   ->  18800.00

        return $s;
    }

    private function eq($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) { return 'normalize-space(' . $node . ")='" . $s . "'"; }, $field)) . ')';
    }

    private function starts($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) { return 'starts-with(normalize-space(' . $node . "),'" . $s . "')"; }, $field)) . ')';
    }

    private function contains($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) { return 'contains(normalize-space(' . $node . "),'" . $s . "')"; }, $field)) . ')';
    }

    private function opt($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return '';
        }

        return '(?:' . implode('|', array_map(function ($s) { return preg_quote($s, '/'); }, $field)) . ')';
    }

    private function t($phrase)
    {
        if (!isset(self::$dict[$this->lang]) || !isset(self::$dict[$this->lang][$phrase])) {
            return $phrase;
        }

        return self::$dict[$this->lang][$phrase];
    }

    private function assignLang(): bool
    {
        foreach ($this->langDetectors as $lang => $phrases) {
            foreach ($phrases as $phrase) {
                if ($this->http->XPath->query("//node()[{$this->contains($phrase)}]")->length > 0) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function getProvider($from)
    {
        foreach (self::$providers as $code => $arr) {
            if (!empty($arr['from'])) {
                foreach ($arr['from'] as $aFrom) {
                    if (stripos($from, $aFrom) !== false) {
                        return $code;
                    }
                }
            }
            $criteria = $arr['body'];

            if (count($criteria) > 0) {
                foreach ($criteria as $search) {
                    if ((stripos($search, '//') === 0 && $this->http->XPath->query($search)->length > 0)
                        || (stripos($search, '//') === false && strpos($this->http->Response['body'],
                                $search) !== false)
                    ) {
                        return $code;
                    }
                }
            }
        }

        return null;
    }
}
