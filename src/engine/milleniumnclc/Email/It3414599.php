<?php

namespace AwardWallet\Engine\milleniumnclc\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class It3414599 extends \TAccountChecker
{
    public $mailFiles = "milleniumnclc/it-3414599.eml, milleniumnclc/it-745505638.eml, milleniumnclc/it-82076982.eml";
    public $lang = 'en';

    public static $dictionary = [
        "en" => [
            // 'YOUR RESERVATION IS' => '',
            'statuses' => 'CONFIRMED',
            // 'Dear ' => '',
            // 'T. ' => '',
            // 'Thank you for choosing to stay at' => '',
            // "Booking reference:" => '',
            // 'Loyalty Member ID:' => '',
            // 'Room Charges' => '',
            // 'Taxes & Fees' => '',
            // 'Total Cost:' => '',
            'Cancellation information' => ['Cancellation information', 'CANCELLATION POLICY'],
            // 'Check-in Time:' => '',
            // 'Check-out Time:' => '',
            // 'Check in:' => '',
            // 'Check out:' => '',
            // 'Room Type:' => '',
            // "Rate Code:" => '',
            // "Occupancy:" => '',
            // 'Adults' => '',
            // 'Children' => '',
            // "Includes: Type:" => '',
            // "Comments:" => '',
        ],
        "zh" => [
            // 'YOUR RESERVATION IS' => '',
            // 'statuses' => 'CONFIRMED',
            'Dear '                             => '尊貴的 ',
            'T. '                               => 'T. ',
            'Thank you for choosing to stay at' => '',
            "Booking reference:"                => '預訂編號：',
            'Loyalty Member ID:'                => '忠誠計畫會員號：',
            'Room Charges'                      => '客房費用',
            'Taxes & Fees'                      => '稅費與小費',
            'Total Cost:'                       => '總費用：',
            'Cancellation information'          => ['取消政策'],
            'Check-in Time:'                    => '登記入住時間：',
            'Check-out Time:'                   => '退房離店時間：',
            'Check in:'                         => '登記入住：',
            'Check out:'                        => '退房離店：',
            'Room Type:'                        => '客房類型：',
            "Rate Code:"                        => '房價代碼：',
            "Occupancy:"                        => '入住人數：',
            'Adults'                            => '成人',
            'Children'                          => '兒童',
            // "Includes: Type:" => '',
            "Comments:" => '備註：',
        ],
    ];

    private $patterns = [
        'time' => '\d{1,2}(?:[:：]\d{2})?(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?|[ ]*noon)?', // 4:19PM    |    2:00 p. m.    |    3pm    |    12 noon
    ];

    private $detectBody = [
        'en' => [
            'YOUR RESERVATION IS CONFIRMED',
            'YOUR BOOKING HAS BEEN MODIFIED',
            'WE LOOK FORWARD TO YOUR ARRIVAL',
            'WE LOOK FORWARD TO WELCOMING YOU',
        ],
        'zh' => [
            '我們誠摯歡迎您的到來',
        ],
    ];

    public function ParseEmail(Email $email): void
    {
        foreach ($this->detectBody as $lang => $detectBody) {
            if ($this->http->XPath->query("//*[{$this->contains($detectBody)}]")->length > 0) {
                $this->lang = $lang;

                break;
            }
        }

        $h = $email->add()->hotel();

        $statusTexts = array_filter($this->http->FindNodes("//text()[{$this->starts($this->t('YOUR RESERVATION IS'))}]", null, "/{$this->opt($this->t('YOUR RESERVATION IS'))}[:\s]+({$this->opt($this->t('statuses'))})(?:\s*[,.;:!?]|$)/i"));

        if (count(array_unique($statusTexts)) === 1) {
            $status = array_shift($statusTexts);
            $h->general()->status($status);
        }

        $traveller = null;
        $travellerNames = array_filter($this->http->FindNodes("//text()[{$this->starts($this->t('Dear '))}]", null,
            "/^{$this->opt($this->t('Dear '))}\s*([[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]])(?:\s*[,：]|$)/u"));

        if (count(array_unique($travellerNames)) === 1) {
            $traveller = array_shift($travellerNames);
        }
        $h->general()->traveller($traveller);

        $h->general()
            ->confirmation($this->getField($this->t("Booking reference:")))
            ->cancellation($this->http->FindSingleNode("//tr[{$this->eq($this->t('Cancellation information'))}]/following-sibling::tr[normalize-space()][1]"), false, true);

        $loyaltyMemberID = $this->http->FindSingleNode("//tr[{$this->eq($this->t('Loyalty Member ID:'))}]/following-sibling::tr[normalize-space()][1]", null, true, '/^\d{8,}$/');

        if ($loyaltyMemberID) {
            $h->program()->account($loyaltyMemberID, false);
        }

        $xpathHotelInfo = "//img[contains(@src, '/Location1.png') or @alt='Image removed by sender. location']/ancestor::td[1]/following-sibling::td[1]";

        $dateFormat = '\d{1,2}[:\.]\d{2}(?:[ ]*[AP]M)?';
        $chekhInDate = strtotime($this->getField($this->t("Check in:")));
        $time = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Check-in Time:'))}]", null, true, "/{$this->opt($this->t('Check-in Time:'))}\s*({$dateFormat})\s*$/i")
            ?? $this->http->FindSingleNode("//text()[{$this->eq($this->t('Check-in Time:'))}]/following::text()[normalize-space()][1]", null, true, "/^{$dateFormat}\s*$/i");

        if (!empty($time) && !empty($chekhInDate)) {
            $chekhInDate = strtotime($time, $chekhInDate);
        }

        $chekhOutDate = strtotime($this->getField($this->t("Check out:")));
        $time = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Check-out Time:'))}]", null, true, "/{$this->opt($this->t('Check-out Time:'))}\s*({$dateFormat})\s*$/i")
            ?? $this->http->FindSingleNode("//text()[{$this->eq($this->t('Check-out Time:'))}]/following::text()[normalize-space()][1]", null, true, "/^{$dateFormat}\s*$/i");

        if (!empty($time) && !empty($chekhOutDate)) {
            $chekhOutDate = strtotime($time, $chekhOutDate);
        }

        $name = $this->http->FindSingleNode("(" . $xpathHotelInfo . "//text()[normalize-space(.)])[1]");

        if (empty($name)) {
            $name = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Thank you for choosing to stay at'))}]", null, true, "/{$this->opt($this->t('Thank you for choosing to stay at'))}\s*(.+)\./");
        }
        $address = nice(implode("", $this->http->FindNodes("(" . $xpathHotelInfo . "//text()[normalize-space(.)])[position()=2 or position()=3]")));

        if (empty($address)) {
            $address = $this->http->FindSingleNode("//text()[normalize-space()='The Lakefront Anchorage']/following::text()[normalize-space()][1]/ancestor::*[1]", null, true, "/^(.+)\s*T\./");
        }
        $phone = $this->http->FindSingleNode($xpathHotelInfo . "//text()[{$this->contains($this->t('T. '))}]", null, true, "#T\.\s+([\d\-\+]+)#");

        if (empty($phone)) {
            $phone = $this->http->FindSingleNode("//text()[normalize-space()='The Lakefront Anchorage']/following::text()[normalize-space()][1]/ancestor::*[1]", null, true, "/T\.\s*([\d\-]+)/");
        }
        $h->hotel()
            ->name($name)
            ->address($address)
            ->phone($phone);

        $occupancy = $this->getField($this->t("Occupancy:"));

        $h->booked()
            ->guests(re("/\b(\d{1,3})\s+{$this->opt($this->t('Adults'))}/i", $occupancy))
            ->kids(re("/\b(\d{1,3})\s+{$this->opt($this->t('Children'))}/i", $occupancy))
            ->checkIn($chekhInDate)
            ->checkOut($chekhOutDate);

        $rateType = $this->getField($this->t("Rate Code:"));
        $roomType = $this->getField($this->t("Includes: Type:"))
            ?? $this->http->FindSingleNode("//tr[{$this->eq($this->t('Room Type:'))}]/following-sibling::tr[normalize-space()][1]");
        // $roomTypeDescription = $this->getField($this->t("Comments:"));

        if (!empty($rateType) || !empty($roomType) || !empty($roomTypeDescription)) {
            $room = $h->addRoom();

            if (!empty($rateType)) {
                $room->setRateType($rateType);
            }

            if (!empty($roomType)) {
                $room->setType($roomType);
            }

            // if (!empty($roomTypeDescription)) {
            //     $room->setDescription($roomTypeDescription);
            // }
        }

        $cost = cost($this->http->FindSingleNode("//text()[{$this->eq($this->t('Room Charges'))}]/ancestor::table[1]/following-sibling::table[1]"));

        if (!empty($cost)) {
            $h->price()
                ->cost($cost);
        }

        $taxes = cost($this->http->FindSingleNode("//text()[{$this->eq($this->t('Taxes & Fees'))}]/ancestor::table[1]/following-sibling::table[1]"));

        if (!empty($taxes)) {
            $h->price()
                ->tax($taxes);
        }

        $total = cost($this->http->FindSingleNode("//text()[{$this->eq($this->t('Total Cost:'))}]/ancestor::table[1]/following-sibling::table[1]"));
        $currency = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Total Cost:'))}]/ancestor::table[1]/following-sibling::table[1]",
            null, true, "/\b([A-Z]{3})\s*$/");

        $this->logger->debug('$total = ' . print_r($total, true));
        $this->logger->debug('$currency = ' . print_r($currency, true));

        if (!empty($total) && !empty($currency)) {
            $h->price()
                ->total($total)
                ->currency($currency);
        }

        $this->detectDeadLine($h);
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query('//a[contains(@href,".millenniumhotels.com/") or contains(@href,"www.millenniumhotels.com")]')->length === 0
            && $this->http->XPath->query('//*[contains(normalize-space(),"Millennium Hotels and Resorts. All rights reserved")]')->length === 0
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

    public function detectEmailByHeaders(array $headers)
    {
        return stripos($headers['from'], '@millenniumhotels.com') !== false
            && preg_match('/[,|]\s*Confirmation number:/i', $headers['subject']) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $body = $parser->getHTMLBody();

        if (empty($body)) {
            $body = $parser->getPlainBody();
            $this->http->SetEmailBody($body);
        }
        $this->ParseEmail($email);

        $email->setType('It3414599' . ucfirst($this->lang));

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

    private function getField($str): ?string
    {
        return $this->http->FindSingleNode("//*[normalize-space(text())='{$str}']/ancestor::tr[1]/following-sibling::tr[1]");
    }

    private function detectDeadLine(\AwardWallet\Schema\Parser\Common\Hotel $h): void
    {
        if (empty($cancellationText = $h->getCancellation())) {
            return;
        }

        if (preg_match("/No Cancellations and No Changes are allowed/i", $cancellationText, $m)) {
            $h->booked()->nonRefundable();

            return;
        }

        $dayWords = ['zero', 'one', 'two', 'three', 'four', 'five', 'six', 'seven', 'eight', 'nine', 'ten'];

        if (preg_match("/Free Cancell?ation\* Cancell? your reservation by (?<hour>{$this->patterns['time']}) local hotel time (?<prior1>\d{1,3}) (?<prior2>days?) prior to arrival to avoid 1 night charge(?:[.!]|$)/i", $cancellationText, $m)
            || preg_match("/Please (?i)cancell? your reservation by (?<hour>{$this->patterns['time']}) local hotel time (?<prior1>\w{1,99}) (?<prior2>days?) prior to arrival to avoid a one night charge/", $cancellationText, $m)
            || preg_match("/Free (?i)cancell?ation until (?<hour>{$this->patterns['time']}) on the (?<prior2>day) of arrival(?:[.!]|$)/", $cancellationText, $m)
            || preg_match("/^CANCELL? PERMITTED UP TO (?<prior1>\d{1,3}) (?<prior2>DAYS?) BEFORE ARRIVAL. [\d\.]+ CANCELL? FEE PER ROOM(?:[.!]|$)/i", $cancellationText, $m)
        ) {
            if (empty($m['prior1'])) {
                $m['prior1'] = '1';
            }

            $m['prior1'] = strtolower($m['prior1']);

            $prior1 = in_array($m['prior1'], $dayWords) ? array_search($m['prior1'], $dayWords) : $m['prior1'];
            $hour = empty($m['hour']) ? '00:00' : $m['hour'];
            $h->booked()->deadlineRelative($prior1 . ' ' . $m['prior2'], $hour);

            return;
        }
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
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

    private function contains($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';

            return 'contains(normalize-space(' . $node . '),' . $s . ')';
        }, $field)) . ')';
    }

    private function eq($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';

            return 'normalize-space(' . $node . ')=' . $s;
        }, $field)) . ')';
    }

    private function starts($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';

            return 'starts-with(normalize-space(' . $node . '),' . $s . ')';
        }, $field)) . ')';
    }
}
