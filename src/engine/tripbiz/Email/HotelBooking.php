<?php

namespace AwardWallet\Engine\tripbiz\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;

class HotelBooking extends \TAccountChecker
{
    public $mailFiles = "tripbiz/it-230208071.eml, tripbiz/it-620666029.eml";
    public $detectFrom = ['ct_rsv@trip.com', 'bytedance@trip.com'];
    public $detectSubject = [
        // en
        "Hotel Booking Confirmation: Booking no.",
        // zh
        '酒店预订确认单',
    ];
    public $detectBody = [
        'en'   => 'Good news! Your booking at',
        'zh'   => '已预订成功。',
    ];

    public static $dictionary = [
        "en" => [
            'bookingNo'           => ['Booking No', 'Confirmation No', 'Booking number:'],
            'Booking Information' => 'Booking Information',
            'statusPhrases'       => ['has been'],
            'statusVariants'      => ['confirmed'],
            'cancellationPhrases' => [
                'If you need to modify or cancell your booking',
                'If you need to modify or cancel your booking',
                'If you want to cancell or modify your booking',
                'If you want to cancel or modify your booking',
                'booking cannot be cancelled or modified',
                'booking cannot be canceled or modified',
                'to cancell or change the booking',
                'to cancel or change the booking',
            ],
            //            'Check-in Date' => '',
            'Check-out Date' => 'Check-out Date',
            //            'Guests' => '',
            ////            'Room Type' => '',
            //            'Price Summary' => '',
            // '/Room' => '',
            //            'Total:' => '',
            //            'Check-in and Cancellation' => '',
            //            'Check-in:' => '',
            //            'Check-out:' => '',
            //            '报名字入住' => '', // to translate: Зарегистрируйтесь по имени
        ],
        "zh" => [
            'bookingNo'           => ['订单号:', '酒店确认号'],
            'Booking Information' => '预订信息',
            // 'statusPhrases'       => [],
            // 'statusVariants'      => [],
            // 'cancellationPhrases' => [
            //     ''
            // ],
            'Check-in Date'             => '入住日期',
            'Check-out Date'            => '离店日期',
            'Guests'                    => '入住人',
            'Hotel confirmation number' => '酒店确认号', // to translate
            'noConfirmation'            => ['报名字入住', '报名字'],
            'Room Type'                 => '房型',
            'Price Summary'             => '费用明细',
            '/Room'                     => '/间',
            'Total:'                    => '总价:',
            'Check-in and Cancellation' => '入住和取消',
            'Check-in:'                 => '入住时间：',
            'Check-out:'                => '退房时间：',
        ],
    ];

    public $lang = '';

    public function detectEmailFromProvider($from)
    {
        foreach ($this->detectFrom as $reFrom) {
            if (stripos($from, $reFrom) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->detectEmailFromProvider($headers['from']) !== true) {
            return false;
        }

        foreach ($this->detectSubject as $re) {
            if (stripos($headers["subject"], $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query('//a[contains(@href,".ctrip.com")] | //img[contains(@src,"c-ctrip.com")]')->length == 0
            && $this->http->XPath->query("//text()[{$this->contains(['Trip.Biz user', 'ctrip.com. all rights reserved', 'ct_rsv@trip.com'])}]")->length == 0
        ) {
            return false;
        }

        foreach (self::$dictionary as $dict) {
            if (!empty($dict['Booking Information']) && !empty($dict['Check-out Date'])
                && $this->http->XPath->query('//node()[' . $this->contains($dict['Booking Information']) . ']')->length > 0
                && $this->http->XPath->query('//node()[' . $this->contains($dict['Check-out Date']) . ']')->length > 0
            ) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        foreach (self::$dictionary as $lang => $dict) {
            if (!empty($dict['Booking Information']) && !empty($dict['Check-out Date'])
                && $this->http->XPath->query('//node()[' . $this->contains($dict['Booking Information']) . ']')->length > 0
                && $this->http->XPath->query('//node()[' . $this->contains($dict['Check-out Date']) . ']')->length > 0
            ) {
                $this->lang = $lang;

                break;
            }
        }

        if (empty($this->lang)) {
            $this->logger->debug("Can't determine a language!");

            return null;
        }

        $this->parseHtml($email);

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

    private function parseHtml(Email $email): void
    {
        $xpathNoEmpty = '(normalize-space() and normalize-space()!=" ")';
        $xpathBold = '(self::b or self::strong or ancestor-or-self::*[contains(@style,"bold")])';

        $patterns = [
            'phone' => '[+(\d][-+. \d)(]{5,}[\d)]', // +377 (93) 15 48 52    |    (+351) 21 342 09 07    |    713.680.2992
        ];

        // Travel Agency
        $bookingNo = null;
        $bookingNumbers = array_filter($this->http->FindNodes("//text()[{$this->eq($this->t('Booking Information'))}]/preceding::text()[{$this->starts($this->t('bookingNo'))}]/following::text()[normalize-space()][1]", null, '/^\s*([A-Z\d]{5,})\s*$/'));

        if (count(array_unique($bookingNumbers)) === 1) {
            $bookingNo = array_shift($bookingNumbers);
        }
        $email->ota()->confirmation($bookingNo);

        $h = $email->add()->hotel();

        // General
        $confirmationNumbers = [];
        $confirmationValues = $this->http->FindNodes($xpathConfNo = "//text()[{$this->eq($this->t('Booking Information'))}]/following::text()[{$this->starts($this->t('bookingNo'))}]/following::text()[normalize-space()][1][not(ancestor::*[{$xpathBold}])][not({$this->eq($this->t('noConfirmation'))})]");

        /* examples */
        // $confirmationValues = ['3240487684(Password:3649)', '21800266340 (集团确认号:C04112736)', 'nirvana_999222097199831', '310045_6278204414', '104940144792-337826', '(B)CTP-159824', 'f23a040013', 'F23A160004,05', '88571,88572'];

        foreach ($confirmationValues as $confVal) {
            $confParts = preg_split("/[,]+/", str_replace(' ', '', $confVal));

            foreach ($confParts as $i => $confPart) {
                $confPart = preg_replace('/(^\s*\[.+\]\s*|\s*\[.+\]\s*$)/', '', $confPart); // [WANDA]P2312130861
                $confPart = preg_replace('/^([^)(]+)\([^)(]+[:]+[^)(]+\)$/', '$1', $confPart); // 3240487684(Password:3649)
                $confPart = str_replace(['(', ')'], '', $confPart); // (B)CTP-159824

                if ($i > 0) {
                    $prevConfNo = $confParts[$i - 1];

                    if (preg_match("/^\d$/", $confPart) && preg_match("/^(.+)\d$/", $prevConfNo, $m)
                        || preg_match("/^\d{2}$/", $confPart) && preg_match("/^(.+)\d{2}$/", $prevConfNo, $m)
                        || preg_match("/^\d{3}$/", $confPart) && preg_match("/^(.+)\d{3}$/", $prevConfNo, $m)
                    ) {
                        $confPart = $m[1] . $confPart;
                    }
                }
                $confirmationNumbers[] = $confPart;
            }
        }

        $confirmationNumbers = array_unique($confirmationNumbers);

        foreach ($confirmationNumbers as $confNo) {
            if (!preg_match("/^[-_A-z\d]{2,}$/", $confNo)) {
                $confirmationNumbers = [];
                $this->logger->debug('confirmation number (' . $confNo . ') is wrong!');

                break;
            }
        }

        if (count($confirmationNumbers) === 0 && $this->http->XPath->query($xpathConfNo)->length === 0) {
            $h->general()->noConfirmation();
        } elseif (strlen(implode('', $confirmationValues)) < 3) {
            $h->general()->noConfirmation();
        } else {
            foreach ($confirmationNumbers as $confNo) {
                $h->general()->confirmation($confNo);
            }
        }

        $travellers = $this->http->FindNodes("//tr[td[1][{$this->eq($this->t('Guests'))}]]/following-sibling::tr/td[1]",
            null, "/(.+?)\s*(?:\(|$)/");
        $h->general()->travellers($travellers, true);

        $status = $this->http->FindSingleNode("//tr[not(.//tr) and {$this->contains('Your booking at')} and {$this->contains($this->t('statusPhrases'))}]", null, true, "/{$this->opt($this->t('statusPhrases'))}[:\s]+({$this->opt($this->t('statusVariants'))})(?:\s*[,.;:!?]|$)/i");

        if ($status) {
            $h->general()->status($status);
        }

        $cancellation = $this->http->FindSingleNode("//tr[not(.//tr) and {$this->contains($this->t('cancellationPhrases'))}]");
        $h->general()->cancellation($cancellation, false, true);

        // Hotel
        $hotelInfo = implode("\n", $this->http->FindNodes("//text()[{$this->eq($this->t('Booking Information'))}]/following::text()[{$xpathNoEmpty}][1]" .
            "/ancestor::*[descendant::img][1]/descendant::text()[normalize-space()]"));

        if (preg_match("/^\s*(?<name>.{2,})\n(?<address>([\s\S]{3,}?))\n\(\s*(?<phone>{$patterns['phone']})\s*\)/", $hotelInfo, $m)) {
            $h->hotel()->name($m['name'])->address($m['address'])->phone($m['phone']);
        }

        // Booked
        $checkinDate = $this->normalizeDate($this->http->FindSingleNode("//*[ *[normalize-space()][1][{$this->eq($this->t('Check-in Date'))}] ]/*[normalize-space()][2]", null, true, '/^.*\d.*$/'));

        $time = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Check-in and Cancellation'))}]/following::text()[normalize-space()][position() < 5][{$this->contains($this->t('Check-in:'))}]",
            null, true, "/{$this->opt($this->t('Check-in:'))}(?: *[^,.\d]+)?\b(\d{1,2}:\d{2}(?:\s*[ap]m)?)\b/i");

        if (!empty($time) && !empty($checkinDate)) {
            $checkinDate = strtotime($time, $checkinDate);
        }
        $h->booked()
            ->checkIn($checkinDate);

        $checkOutDate = $this->normalizeDate($this->http->FindSingleNode("//*[ *[normalize-space()][1][{$this->eq($this->t('Check-out Date'))}] ]/*[normalize-space()][2]", null, true, '/^.*\d.*$/'));

        $time = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Check-in and Cancellation'))}]/following::text()[normalize-space()][position() < 5][{$this->contains($this->t('Check-out:'))}]",
            null, true, "/{$this->opt($this->t('Check-out:'))} [^,.\d]* (\d{1,2}:\d{2}(?:\s*[ap]m|后|前)?)\b/i");

        if (!empty($time) && !empty($checkOutDate)) {
            $checkOutDate = strtotime($time, $checkOutDate);
        }

        $h->booked()
            ->checkOut($checkOutDate);

        /// Rooms
        $type = $this->http->FindSingleNode("//td[not(.//td)][descendant::text()[normalize-space()][1][{$this->eq($this->t("Room Type"))}]]",
            null, true, "/{$this->opt($this->t("Room Type"))}\s*(.+)/");

        if (!empty($type)) {
            $room = $h->addRoom();
            $room->setType($type);
        }

        $rates = array_filter($this->http->FindNodes("//text()[{$this->contains($this->t('/Room'))}]/ancestor::tr[1][preceding::text()[{$this->eq($this->t('Price Summary'))}] and following::text()[{$this->eq($this->t('Total:'))}]]",
            null, "/^\s*\d+\s*\*([^\(\)\\/]*\d+[^\(\)\\/]*)\s*(\(.*?\))?\s*{$this->opt($this->t('/Room'))}\s*$/u"));

        if (!empty($rates)) {
            if (!isset($room)) {
                $room = $h->addRoom();
            }
            $room->setRates($rates);
        }

        $totalPayment = $this->nextText($this->t("Total:"));

        if (preg_match('/^(?<currency>[A-Z]{3})[ ]*(?<amount>\d[,.\'\d ]*)$/', $totalPayment, $m)
            || preg_match('/^(?<amount>\d[,.\'\d ]*?)[ ]*(?<currency>[A-Z]{3})$/', $totalPayment, $m)
            || preg_match('/^(?<currency>[^\s\d]{1,5}) ?(?<amount>\d[,.\'\d ]*)$/', $totalPayment, $m)
            || preg_match('/^\s*(?<amount>\d[,.\'\d ]*?) ?(?<currency>[^\s\d]{1,5})\s*$/u', $totalPayment, $m)
        ) {
            // HKD 1,575.92
            $currency = $this->currency($m['currency']);
            $h->price()
                ->currency($currency)
                ->total(PriceHelper::parse($m['amount']), $currency);
        }
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function normalizeDate($str)
    {
        // $this->logger->debug('$date = ' . print_r($str, true));
        $in = [
            // Sep 8, 2017
            "/^\s*([[:alpha:]]+)\s+(\d+),\s+(\d{4})\s*$/",
            // 2023年12月14日
            '/^\s*(\d{4})\s*年\s*(\d{1,2})\s*月\s*(\d{1,2})\s*日\s*$/u',
        ];
        $out = [
            "$2 $1 $3",
            "$1-$2-$3",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = \AwardWallet\Engine\MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        // $this->logger->debug('$date 2  = ' . print_r($str, true));
        return strtotime($str);
    }

    private function re($re, $str, $c = 1): ?string
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function currency($s): ?string
    {
        $sym = [
            '€'   => 'EUR',
            '$'   => 'USD',
            '£'   => 'GBP',
            'บาท' => 'THB',
            '₩'   => 'KRW',
            '¥'   => 'CNY',
        ];

        if ($code = $this->re("#(?:^|\s)([A-Z]{3})(?:$|\s)#", $s)) {
            return $code;
        }
        $s = $this->re("#([^\d\,\.]+)#", $s);

        foreach ($sym as $f=> $r) {
            if (strpos($s, $f) !== false) {
                return $r;
            }
        }

        return null;
    }

    private function nextText($field, $root = null)
    {
        $rule = $this->eq($field);

        return $this->http->FindSingleNode("(.//text()[{$rule}])[1]/following::text()[normalize-space(.)][1]", $root);
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) { return "normalize-space(.)=\"{$s}\""; }, $field));
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) { return "starts-with(normalize-space(.), \"{$s}\")"; }, $field));
    }

    private function contains($field, $node = '.'): string
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

        return '(?:' . implode('|', array_map(function ($s) {
            return preg_quote($s, '/');
        }, $field)) . ')';
    }
}
