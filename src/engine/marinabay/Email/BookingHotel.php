<?php

namespace AwardWallet\Engine\marinabay\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class BookingHotel extends \TAccountChecker
{
    public $mailFiles = "marinabay/it-27160530.eml, marinabay/it-624018226.eml, marinabay/it-89236468.eml, marinabay/it-672074344.eml, marinabay/it-674823003-cancelled.eml";

    public $lang = 'en';

    public static $dictionary = [
        'en' => [
            'CONFIRMATION CODE'    => ['CONFIRMATION CODE', 'Your confirmation code'],
            'Guest Name'           => ['Guest Name', 'GUEST NAME'],
            'travellerEnd_subject' => ['your reservation', 'your stay', 'skip the queue', 'we look', 'see you'],
            'Adults'               => ['Adults', 'No. of Adults'],
            'Check-in Date'        => ['Check-in Date', 'CHECK-IN'],
            'Check-out Date'       => ['Check-out Date', 'CHECK-OUT'],
            'Check-in starts from' => ['Check-in starts from', 'Check-in from'],
            'Check-out time is by' => ['Check-out time is by', 'Check-out time is'],
            'Room Type'            => ['Room Type', 'ROOM TYPE'],
            'Children'             => ['Children', 'No. of Children'],
            'statusPhrases'        => ['YOUR RESERVATION HAS BEEN'],
            'statusVariants'       => ['CONFIRMED', 'cancelled', 'canceled'],
        ],
    ];

    private $emailSubject;

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->emailSubject = $parser->getSubject();

        $class = explode('\\', __CLASS__);
        $email->setType(end($class));

        $this->hotel($email);

        return $email;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, 'marinabaysands.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (array_key_exists('subject', $headers) && stripos($headers['subject'], 'your stay + exclusive privileges at Marina Bay Sands begin on') !== false) {
            return true;
        }

        return array_key_exists('from', $headers) && $this->detectEmailFromProvider(rtrim($headers['from'], '> ')) === true
            && array_key_exists('subject', $headers) && stripos($headers['subject'], 'your reservation has been confirmed for check-in on') !== false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        return $this->http->XPath->query("//text()[contains(normalize-space(.), 'marinabaysands.com')] | //a[contains(@href, 'marinabaysands.com')]/@href")->length > 0
            || stripos($this->http->Response['body'], 'marinabaysands.com') !== false || stripos($this->http->Response['body'], '+65 6688 9999') !== false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
    }

    private function hotel(Email $email): void
    {
        $patterns = [
            'time'          => '\d{1,2}(?:[:：]\d{2})?(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?)?', // 4:19PM    |    2:00 p. m.    |    3pm
            'travellerName' => '[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]]', // Mr. Hao-Li Huang
        ];

        $h = $email->add()->hotel();

        // General
        $status = null;
        $statuses = array_filter($this->http->FindNodes("//text()[{$this->contains($this->t('statusPhrases'))}]", null, "/{$this->opt($this->t('statusPhrases'))}\s+({$this->opt($this->t('statusVariants'))})(?:[ ]*[,:;!?]|$)/i"));

        if (count(array_unique($statuses)) === 1) {
            $status = array_shift($statuses);
        }

        if (empty($status) && !empty($this->emailSubject)
            && preg_match("/your reservation has been\s+({$this->opt($this->t('statusVariants'))})(?:\s+for\s|$)/i", $this->emailSubject, $m)
        ) {
            $status = $m[1];
        }

        if ($status) {
            $h->general()->status($status);
        }

        $cancellNo = $this->http->FindSingleNode("//text()[{$this->eq($this->t('CANCELLATION CODE'), 'translate(.,":","")')}]/following::text()[normalize-space()][1]", null, true, "/^[-A-Z\d]{5,}$/")
            ?? $this->http->FindSingleNode("//text()[{$this->starts($this->t('CANCELLATION CODE'))}]", null, true, "/^{$this->opt($this->t('CANCELLATION CODE'))}\s*[:]+\s*([-A-Z\d]{5,})$/i");

        if ($cancellNo) {
            $h->general()->cancellationNumber($cancellNo)->cancelled();
        }

        $confNo = $this->http->FindSingleNode("//text()[{$this->eq($this->t('CONFIRMATION CODE'), 'translate(.,":","")')}]/following::text()[normalize-space()][1]", null, true, "/^[-A-Z\d]{5,}$/") // it-27160530.eml
            ?? $this->http->FindSingleNode("//text()[{$this->starts($this->t('CONFIRMATION CODE'))}]", null, true, "/^{$this->opt($this->t('CONFIRMATION CODE'))}\s*[:]+\s*([-A-Z\d]{5,})$/i") // it-89236468.eml, it-624018226.eml
        ;

        if ($confNo) {
            $confNoTitle = $this->http->FindSingleNode("//text()[{$this->eq($this->t('CONFIRMATION CODE'), 'translate(.,":","")')}]", null, true, '/^(.+?)[\s:：]*$/u')
                ?? $this->http->FindSingleNode("//text()[{$this->starts($this->t('CONFIRMATION CODE'))}]", null, true, "/^({$this->opt($this->t('CONFIRMATION CODE'))})\s*[:]+\s*[-A-Z\d]{5,}$/i");
            $h->general()->confirmation($confNo, $confNoTitle);
        }

        $isNameFull = true;
        $traveller = $this->getNode('Guest Name', "/^({$patterns['travellerName']})(?:\s*#|$)/u") // Tai Theen Cheong #154453
            ?? $this->http->FindSingleNode("//text()[{$this->starts($this->t('Guest Name'))}]/ancestor::*[count(descendant::text()[normalize-space()])>1][1]", null, true, "/^{$this->opt($this->t('Guest Name'))}[:\s]*({$patterns['travellerName']})$/u");

        if (empty($traveller) && !empty($this->emailSubject)
            && preg_match("/{$this->opt($this->t('Dear'))}[,\s]+({$patterns['travellerName']})\s*,\s*{$this->opt($this->t('travellerEnd_subject'))}/u", $this->emailSubject, $m)
        ) {
            $isNameFull = null;
            $traveller = $m[1];
        }

        if (empty($traveller)) { // it-624018226.eml
            $isNameFull = null;
            $traveller = $this->http->FindSingleNode("//text()[ normalize-space() and preceding::img[contains(@src,'/CONFIRMED_E')] and following::img[contains(@src,'/line1')] ]", null, true, "/^(?:{$this->opt($this->t('Dear'))}[,\s]+)?({$patterns['travellerName']})$/u");
        }

        if (empty($traveller) && !empty($this->emailSubject)
            && preg_match("/(?:^|:\s*)({$patterns['travellerName']})\s*,\s*{$this->opt($this->t('travellerEnd_subject'))}/u", $this->emailSubject, $m)
        ) {
            $isNameFull = null;
            $traveller = $m[1];
        }

        $h->general()->traveller($traveller, $isNameFull);

        // Program
        $account = $this->getNode('Guest Name', "#.+?\#\s*(\d+)#"); // Tai Theen Cheong #154453

        if (empty($account)) {
            $account = $this->getNode_alt('Guest Name', "#.+?\#\s*(\d+)#");
        }

        if (empty($account)) {
            $account = $this->http->FindSingleNode("//text()[normalize-space()='MEMBERSHIP ID']/ancestor::tr[1]/descendant::td[normalize-space()][2]", null, true, "/^(\d+)$/");
        }

        if (!empty($account)) {
            $h->program()->account($account, false);
        }

        // Hotel
        $hotelName = $this->http->FindSingleNode("descendant::text()[{$this->starts($this->t('Getting to '))}][1]", null, true, "/{$this->opt($this->t('Getting to '))}\s*(.{3,75})$/") // it-27160530.eml
            ?? $this->http->FindSingleNode("//text()[{$this->eq($this->t('Get Directions'))}]/following::text()[normalize-space()][1]", null, true, "/^{$this->opt($this->t('to'))}\s+(.{3,75})$/") // it-89236468.eml
            ?? $this->getNode('Check-in Location') // ???
        ;

        if (empty($hotelName) && !empty($this->emailSubject)
            && preg_match("/your stay(?:\s.+)?\s+at\s+(\S.{3,70}\S)\s+begins? on/i", $this->emailSubject, $m)
        ) {
            // "your stay + exclusive privileges at" or "your stay at"
            $hotelName = $m[1];
        }

        $address1 = $this->http->FindSingleNode(".//img[contains(@src, 'http://f.em.marinabaysands.com/i/44/2075868905/20141121_icon_house.jpg')]/ancestor::tr[1]/following-sibling::tr[1]//p/em");
        $address2 = $this->http->FindSingleNode(".//img[contains(@src, 'http://f.em.marinabaysands.com/i/44/2075868905/20141121_icon_house.jpg')]/ancestor::tr[1]/following-sibling::tr[1]//p/i/span/em[1]");

        if (empty($address1)) {
            $address1 = $this->http->FindSingleNode("//text()[starts-with(normalize-space(),'MARINABAYSANDS.COM')]/ancestor::td[1]", null, true, "/MARINABAYSANDS[.]COM.?\n?(\d{2,3}+[,].+)[+]/");
        }

        if (empty($address2)) {
            $address2 = implode("", $this->http->FindNodes("(//img[contains(@src, 'http://f.em.marinabaysands.com/i/44/2075868905/20141121_icon_house.jpg')]/ancestor::tr[1]/following-sibling::tr[1]//text()[normalize-space(.)])[position()<last()]"));
        }

        $address = implode(', ', array_filter([$address1, $address2]));

        $phone = $this->http->FindSingleNode(".//img[contains(@src, 'http://f.em.marinabaysands.com/i/44/2075868905/20141121_icon_house.jpg')]/ancestor::tr[1]/following-sibling::tr[1]//p/i/span/em[2]");

        if (empty($phone)) {
            $phone = $this->http->FindSingleNode("(//img[contains(@src, 'http://f.em.marinabaysands.com/i/44/2075868905/20141121_icon_house.jpg')]/ancestor::tr[1]/following-sibling::tr[1]//text()[normalize-space(.)])[last()]");
        }

        if (empty($phone)) {
            $phone = $this->http->FindSingleNode("//text()[starts-with(normalize-space(),'MARINABAYSANDS.COM')]/ancestor::td[1]", null, true, "/MARINABAYSANDS[.]COM.?\n?\d{2,3}+[,].+([+].*\d.*)$/");
        }

        if ($this->http->FindSingleNode("//img[normalize-space(@alt)='MARINA BAY SANDS SINGAPORE']") !== null
            || $this->http->FindSingleNode("//img[@width>500 and contains(@src,'.marinabaysands.com/') and ancestor::a[contains(@href,'.marinabaysands.com/') or contains(@href,'.marinabaysands.com%2F')]]/@src", null, true, "/\/[-_A-z\d]*logo[-_A-z\d]*\.[A-z]/i") !== null
        ) {
            $hotelName = empty($hotelName) ? 'Marina Bay Sands' : $hotelName;
            $address = empty($address) ? '10, Bayfront Avenue, Singapore 018956' : $address;
            $phone = empty($phone) ? '+65 6688 8888' : $phone;
        }
        $h->hotel()
            ->name($hotelName)
            ->address($address)
            ->phone($phone)
        ;

        // Booked
        $checkInDate = strtotime($this->getNode('Check-in Date'));

        if (empty($checkInDate)) {
            $checkInDate = $this->normalizeDate($this->getNode_alt('Check-in Date'));
        }

        if (!empty($checkInDate)
            && $timeCheckIn = $this->http->FindSingleNode("descendant::text()[{$this->starts($this->t('Check-in starts from'))} or {$this->eq($this->t('From'))}][1]/ancestor::*[1]", null, true, "/(?:{$this->opt($this->t('Check-in starts from'))}|{$this->opt($this->t('From'))})\s*({$patterns['time']})/")
        ) {
            // it-89236468.eml
            $checkInDate = strtotime($timeCheckIn, $checkInDate);
        }

        if (!empty($checkInDate)) {
            $h->booked()->checkIn($checkInDate);
        }

        $checkOutDate = strtotime($this->getNode('Check-out Date'));

        if (empty($checkOutDate)) {
            $checkOutDate = $this->normalizeDate($this->getNode_alt('Check-out Date'));
        }

        if (!empty($checkOutDate)
            && $timeCheckOut = $this->http->FindSingleNode("descendant::text()[{$this->starts($this->t('Check-out time is by'))}][1]/ancestor::*[1]", null, true, "/{$this->opt($this->t('Check-out time is by'))}\s*({$patterns['time']})/")
        ) {
            $checkOutDate = strtotime($timeCheckOut, $checkOutDate);
        }

        if (!empty($checkOutDate)) {
            $h->booked()->checkOut($checkOutDate);
        }

        $guests = $this->getNode('Adults');

        if (empty($guests)) {
            $guests = $this->getNode_alt('Adults');
        }

        if (empty($guests)) {
            $guests = $this->http->FindSingleNode("//text()[{$this->starts($this->t('NO. OF ADULTS'))}]/ancestor::tr[1]/following-sibling::tr[1]/td[1]");
        }

        if (empty($guests)) {
            $guests = $this->http->FindSingleNode("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][normalize-space()='NUMBER OF GUEST'] ]/*[normalize-space()][2]", null, true, "/\b(\d{1,3})\s*{$this->opt($this->t('ADULT'))}/i");
        }

        $h->booked()->guests($guests, true, true);

        $kids = $this->getNode('Children');

        if (strlen($kids) === 0) {
            $kids = $this->getNode_alt('Children');
        }

        if (strlen($kids) === 0) {
            $kids = $this->http->FindSingleNode("//text()[{$this->starts($this->t('NO. OF CHILDREN'))}]/ancestor::tr[1]/following-sibling::tr[1]/td[2]", null, true, '/(\d+)/');
        }

        if ($kids === null) {
            $kids = $guests = $this->http->FindSingleNode("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][normalize-space()='NUMBER OF GUEST'] ]/*[normalize-space()][2]", null, true, "/\b(\d{1,3})\s*{$this->opt($this->t('CHILD'))}/i");
        }

        $h->booked()->kids($kids, true, true);

        // Room
        $roomType = $this->http->FindSingleNode("//tr[count(*)=3]/*[{$this->contains($this->t('Room Type'))}]/following-sibling::*[2]/descendant::text()[normalize-space()][1]")
            ?? $this->getNode('Room Type') // it-27160530.eml
            ?? $this->http->FindSingleNode("//text()[{$this->eq($this->t('Room Type'))}]/ancestor::tr[count(descendant::text()[normalize-space()])>1][1][count(descendant::text()[normalize-space()])<4]/descendant::text()[normalize-space()][2]") // it-89236468.eml
            ?? $this->http->FindSingleNode("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][normalize-space()='NUMBER OF GUEST'] ]/preceding::text()[normalize-space()][2]/ancestor::tr[1][count(descendant::text()[normalize-space()])=1]") // it-624018226.eml
        ;
        $description = $this->http->FindSingleNode("//tr[count(*)=3]/*[{$this->contains($this->t('Room Type'))}]/following-sibling::*[2]/descendant::text()[normalize-space()][2]");

        if (!empty($roomType)) {
            $room = $h->addRoom();

            $room->setType($roomType);
            $description = $description ?? $this->http->FindSingleNode("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][normalize-space()='NUMBER OF GUEST'] ]/preceding::text()[normalize-space()][1]/ancestor::tr[1][count(descendant::text()[normalize-space()])=1]");

            if (!empty($description)) {
                $room->setDescription(preg_replace('/^\(\s*([^)(]+)\s*\)$/', '$1', $description));
            }
        }

        // Price
        $total = $this->getNode('Total');

        if (empty($total)) {
            $total = $this->getNode_alt('Total');
        }

        if (empty($total)) {
            $total = $this->getNode_alt('TOTAL PRICE (INCLUSIVE OF TAXES)');
        }

        if (empty($total)) {
            $total = $this->getNode_alt('TOTAL PRICE');
        }

        if (preg_match("#(?:^|\s)([\D\S]{1,2})([\d.\,]+).+#", $total, $m)) {
            if ($m[1] === '$') {
                $m[1] = 'USD';
            }

            if ($m[1] === 'S$') {
                $m[1] = 'SGD';
            }
            $h->price()
                ->total(PriceHelper::parse($m[2], $m[1]))
                ->currency($m[1])
            ;
        }

        // Cancellation
        $cancellation = $this->http->FindSingleNode("//text()[contains(normalize-space(.), 'Cancellation of and/or amendments') or contains(.,'taken upon booking is non-refundable')]/ancestor::td[1]");

        if (!empty($cancellation)) {
            $h->general()->cancellation($cancellation);

            if (preg_match("#must be made (\d+ days) \(i\.e\., by (\d+)([ap]m) Singapore time\) prior to your arrival date#", $cancellation, $m)) {
                $h->booked()->deadlineRelative($m[1], $m[2] . ":00 " . $m[3]);
            } elseif (preg_match("#booking is non-refundable#", $cancellation, $m)) {
                $h->booked()->nonRefundable();
            }
        }
    }

    /*private function getNode($str, $regexp = null){
        return $this->http->FindSingleNode(".//tr[count(td)=3]/td[contains(normalize-space(.), '{$str}')]/following-sibling::td[2]/p", null, true, $regexp);
    }*/

    private function getNode($str, $regexp = null): ?string
    {
        return $this->http->FindSingleNode("//tr[count(*)=3]/*[{$this->contains($this->t($str))}]/following-sibling::*[2]", null, true, $regexp);
    }

    private function getNode_alt($str, $regexp = null): ?string
    {
        return $this->http->FindSingleNode("//text()[{$this->starts($this->t($str))}]/following::text()[normalize-space() and normalize-space()!=':'][1]", null, true, $regexp);
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

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
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

    private function normalizeDate($str)
    {
//        $this->logger->debug('$str = ' . print_r($str, true));

        $in = [
            "#^(\w+)\s*(\d+)\,\s*(\d{4})\s*\(\w+\)$#u", //Miércoles, 19 de mayo de 2021
        ];
        $out = [
            "$2 $1 $3",
        ];
        $str = preg_replace($in, $out, $str);
//        $this->logger->debug('$str = '.print_r( $str,true));

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = \AwardWallet\Engine\MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return strtotime($str);
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
}
