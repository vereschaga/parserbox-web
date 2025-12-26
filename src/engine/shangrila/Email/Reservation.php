<?php

namespace AwardWallet\Engine\shangrila\Email;

use AwardWallet\Schema\Parser\Email\Email;

class Reservation extends \TAccountChecker
{
    public $mailFiles = "shangrila/it-1.eml, shangrila/it-2.eml, shangrila/it-1867689.eml, shangrila/it-61760912.eml";
    private $subjects = [
        'zh' => ['預訂確認香格'],
        'en' => ['Reservation Confirmation for', 'Reservation updated for'],
    ];
    private $langDetectors = [
        'zh' => ['入住/退房日期：'],
        'en' => ['Check-in/Check-out Date'],
    ];
    private $lang = '';
    private static $dict = [
        'zh' => [
            'Confirmation Number:'     => ['房間確認號碼：', '房間確認號碼:'],
            'Destination:'             => ['目的地：', '目的地:'],
            'Check-in/Check-out Date:' => ['入住/退房日期：', '入住/退房日期:'],
            'Number of Guests:'        => ['人數：', '人數:'],
            'Adult'                    => '多名成人',
            //            'Child' => '',
            'Room(s) Booked:'         => ['客房類型：', '客房類型:'],
            'Rate Selected:'          => ['所選價格：', '所選價格:'],
            'Nightly Rate:'           => ['每晚房價：', '每晚房價:'],
            'Total Cost:'             => ['全部費用：', '全部費用:'],
            'Room Cost:'              => ['房價：', '房價:'],
            'Service Charge and Tax:' => ['服務費及稅項：', '服務費及稅項:'],
            'Guarantee Information'   => '保證內容',
            'cancel'                  => '取消',
            'Guest Name:'             => ['客人姓名：', '客人姓名:'],
            'GC Membership Number:'   => ['貴賓金環會會籍號碼：', '貴賓金環會會籍號碼:'],
            'Address:'                => ['地址：', '地址:'],
            'Contact Numbers:'        => ['聯絡號碼：', '聯絡號碼:'],
            'Tel:'                    => ['電話：', '電話:'],
            'Fax:'                    => ['傳真：', '傳真:'],
        ],
        'en' => [
            'Confirmation Number:'     => ['Confirmation Number:', 'Confirmation Number :', 'Confirmation Number'],
            'Destination:'             => ['Destination:', 'Destination :', 'Destination'],
            'Check-in/Check-out Date:' => ['Check-in/Check-out Date:', 'Check-in/Check-out Date :', 'Check-in/Check-out Date'],
            'Number of Guests:'        => ['Number of Guests:', 'Number of Guests :', 'Number of Guests'],
            //            'Adult' => '',
            //            'Child' => '',
            'Room(s) Booked:'         => ['Room(s) Booked:', 'Room(s) Booked :', 'Room(s) Booked'],
            'Rate Selected:'          => ['Rate Selected:', 'Rate Selected :', 'Rate Selected'],
            'Nightly Rate:'           => ['Nightly Rate:', 'Nightly Rate :', 'Nightly Rate'],
            'Total Cost:'             => ['Total Cost:', 'Total Cost :', 'Total Cost'],
            'Room Cost:'              => ['Room Cost:', 'Room Cost :', 'Room Cost'],
            'Service Charge and Tax:' => ['Service Charge and Tax:', 'Service Charge and Tax :', 'Service Charge and Tax', 'Tax and Service Charges:', 'Tax and Service Charges'],
            //            'Guarantee Information' => '',
            //            'cancel' => '',
            'Guest Name:'           => ['Guest Name:', 'Guest Name :', 'Guest Name'],
            'GC Membership Number:' => ['GC Membership Number:', 'GC Membership Number :', 'GC Membership Number'],
            'Address:'              => ['Address:', 'Address :', 'Address'],
            'Contact Numbers:'      => ['Contact Numbers:', 'Contact Numbers :', 'Contact Numbers'],
            //            'Tel:' => '',
            //            'Fax:' => '',
        ],
    ];

    // Standard Methods

    public function detectEmailFromProvider($from)
    {
        return strpos($from, 'Shangri-La Hotels and Resorts') !== false
            || stripos($from, '@shangri-la.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (self::detectEmailFromProvider($headers['from']) !== true && strpos($headers['subject'], 'Shangri-La') === false) {
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
        $condition1 = $this->http->XPath->query('//node()[contains(normalize-space(.),"Thank you for choosing Shangri-La") or contains(normalize-space(.),"Thank you for your reservation at the Shangri-La") or contains(.,"@shangri-la.com")]')->length === 0;
        $condition2 = $this->http->XPath->query('//a[contains(@href,"//www.shangri-la.com")]')->length === 0;

        if ($condition1 && $condition2) {
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
        $this->parseEmail($email);
        $email->setType('Reservation' . ucfirst($this->lang));

        return $email;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    private function parseEmail(Email $email): void
    {
        $xpathFragmentCell = '(self::td or self::table)';
        $xpathFragmentNext = "/ancestor::*[ {$xpathFragmentCell} and ./following-sibling::*[{$xpathFragmentCell}][normalize-space(.)] ][1]/following-sibling::*[{$xpathFragmentCell}][normalize-space(.)][last()]";

        $h = $email->add()->hotel();

        // confirmation number
        $confirmationNumberTitle = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Confirmation Number:'))}]", null, true, '/^(.+?)[\s:：]*$/u');
        $confirmationNumber = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Confirmation Number:'))}]" . $xpathFragmentNext, null, true, '/^([A-Z\d]{5,})$/');
        $h->general()->confirmation($confirmationNumber, $confirmationNumberTitle);

        // hotelName
        $hotelName = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Destination:'))}]" . $xpathFragmentNext);
        $h->hotel()->name($hotelName);

        // 2 Adults 1 Child (0-11)
        $numberGuests = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Number of Guests:'))}]" . $xpathFragmentNext);

        // guestCount
        if (preg_match("/\b(\d{1,3})\s*{$this->opt($this->t('Adult'))}/i", $numberGuests, $m)) {
            $h->booked()->guests($m[1]);
        }

        // kidsCount
        if (preg_match("/\b(\d{1,3})\s*{$this->opt($this->t('Child'))}/i", $numberGuests, $m)) {
            $h->booked()->kids($m[1]);
        }

        $r = $h->addRoom();

        // roomsCount
        // r.type
        $roomBooked = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Room(s) Booked:'))}]" . $xpathFragmentNext);

        if (preg_match('/^(\d{1,3})\s+(.{3,})/', $roomBooked, $m)) {
            $h->booked()->rooms($m[1]);
            $r->setType($m[2]);
        } else {
            $r->setType($roomBooked);
        }

        // r.rateType
        $rateSelected = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Rate Selected:'))}]" . $xpathFragmentNext);
        $r->setRateType($rateSelected);

        // r.rate
        $nightlyRate = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Nightly Rate:'))}]" . $xpathFragmentNext . "/descendant::text()[normalize-space(.)][1]", null, true, '/^.*\d.*$/');

        if ($nightlyRate) {
            $r->setRate($nightlyRate . ' / night');
        }

        // p.currencyCode
        // p.total
        $totalCost = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Total Cost:'))}]" . $xpathFragmentNext);

        if (preg_match('/^(?<currency>[A-Z]{3})\s*(?<amount>\d[,.\'\d]*)/', $totalCost, $matches)) {
            // SGD 5,037.56
            $h->price()
                ->currency($matches['currency'])
                ->total($this->normalizeAmount($matches['amount']))
            ;

            // p.cost
            $roomCost = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Room Cost:'))}]" . $xpathFragmentNext);

            if (preg_match('/^' . preg_quote($matches['currency'], '/') . '\s*(?<amount>\d[,.\'\d]*)/', $roomCost, $m)) {
                $h->price()->cost($this->normalizeAmount($m['amount']));
            }

            // p.tax
            $serviceTax = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Service Charge and Tax:'))}]" . $xpathFragmentNext);

            if (preg_match('/^' . preg_quote($matches['currency'], '/') . '\s*(?<amount>\d[,.\'\d]*)/', $serviceTax, $m)) {
                $h->price()->tax($this->normalizeAmount($m['amount']));
            }
        }

        $xpathFragmentRow = '(self::tr or self::table)';

        // cancellation
        // deadline
        $cancellation = [];
        $guaranteeRows = $this->http->FindNodes("//text()[{$this->eq($this->t('Guarantee Information'))}]/ancestor::*[ {$xpathFragmentRow} and ./following-sibling::*[{$xpathFragmentRow}][normalize-space(.)] ][1]/following-sibling::*[{$xpathFragmentRow}][normalize-space(.)][1]/descendant::text()[normalize-space(.)]");

        foreach ($guaranteeRows as $guaranteeRow) {
            if (preg_match("/{$this->opt($this->t('cancel'))}/i", $guaranteeRow)) {
                $cancellation[] = $guaranteeRow;
            }
        }

        if (count($cancellation)) {
            $cancellationText = implode(' ', $cancellation);
            $h->general()->cancellation($cancellationText);
            // TODO: parse deadline
        }

        // travellers
        $guestName = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Guest Name:'))}]" . $xpathFragmentNext);
        $h->general()->traveller($guestName);

        // accountNumbers
        $membershipNumber = $this->http->FindSingleNode("//text()[{$this->eq($this->t('GC Membership Number:'))}]" . $xpathFragmentNext, null, true, '/.*\d{7,}.*/');

        if ($membershipNumber) {
            $h->addAccountNumber($membershipNumber, false);
        }

        // address
        $address = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Address:'))}]" . $xpathFragmentNext);
        $h->hotel()->address($address);

        $contactNumbers = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Contact Numbers:'))}]" . $xpathFragmentNext);

        $patterns['phone'] = '[+(\d][-. \d)(]{5,}[\d)]'; // +377 (93) 15 48 52    |    713.680.2992

        // phone
        if (preg_match("/{$this->opt($this->t('Tel:'))}\s*({$patterns['phone']})\b/i", $contactNumbers, $m)) {
            $h->hotel()->phone($m[1]);
        }

        // fax
        if (preg_match("/{$this->opt($this->t('Fax:'))}\s*({$patterns['phone']})\b/i", $contactNumbers, $m)) {
            $h->hotel()->fax($m[1]);
        }

        $dates = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Check-in/Check-out Date:'))}]" . $xpathFragmentNext);
        $dates = preg_split("/{$this->opt($this->t(' - '))}/", $dates);

        if (count($dates) !== 2) {
            $this->logger->alert('Incorrect Check-in and Check-out Date!');

            return;
        }

        // checkInDate
        $h->booked()->checkIn2($this->normalizeDate($dates[0]));

        // checkOutDate
        $h->booked()->checkOut2($this->normalizeDate($dates[1]));
    }

    private function normalizeDate(?string $text): string
    {
        if (!is_string($text) || empty($text)) {
            return '';
        }
        $in = [
            // 2020年8月17日
            '/^(\d{4})\s*年\s*(\d{1,2})\s*月\s*(\d{1,2})\s*日$/u',
        ];
        $out = [
            '$2/$3/$1',
        ];

        return preg_replace($in, $out, $text);
    }

    /**
     * Formatting over 3 steps:
     * 11 507.00  ->  11507.00
     * 2,790      ->  2790    |    4.100,00  ->  4100,00    |    1'619.40  ->  1619.40
     * 18800,00   ->  18800.00  |  2777,0    ->  2777.0.
     *
     * @param string $s Unformatted string with amount
     * @param string|null $decimals Symbols floating-point when non-standard decimals (example: 1,258.943)
     */
    private function normalizeAmount(string $s, ?string $decimals = null): ?float
    {
        if (!empty($decimals) && preg_match_all('/(?:\d+|' . preg_quote($decimals, '/') . ')/', $s, $m)) {
            $s = implode('', $m[0]);

            if ($decimals !== '.') {
                $s = str_replace($decimals, '.', $s);
            }
        } else {
            $s = preg_replace('/\s+/', '', $s);
            $s = preg_replace('/[,.\'](\d{3})/', '$1', $s);
            $s = preg_replace('/,(\d{1,2})$/', '.$1', $s);
        }

        return is_numeric($s) ? (float) $s : null;
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
                if ($this->http->XPath->query('//node()[contains(normalize-space(.),"' . $phrase . '")]')->length > 0) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }
}
