<?php

namespace AwardWallet\Engine\movenpick\Email;

use AwardWallet\Schema\Parser\Email\Email;

class ResConfirmation extends \TAccountChecker
{
    public $mailFiles = "movenpick/it-33278368.eml";

    public $reBody = [
        'en' => ['Your reservation is confirmed', 'Your Reservation Confirmation'],
    ];
    public $lang = '';
    public static $dict = [
        'en' => [
            "Confirmation number:"    => ["Confirmation number:", "Confirmation Number"],
            "Phone:"                  => ["Phone:", "Phone"],
            "Fax:"                    => ["Fax:", "Fax"],
            "Guest name:"             => ["Guest name:", "Guest Name"],
            "Arrival date:"           => ["Arrival date:", "Arrival Date"],
            "Departure date:"         => ["Departure date:", "Departure Date"],
            "Hotel Description:"      => ["Hotel Description:", "Hotel Policies"],
            "Check-in after"          => ["Check-in after", "Check-in: after"],
            "Check-out before"        => ["Check-out before", "Check-out: before"],
            "Number of guests:"       => ["Number of guests:", "No. of Persons"],
            "Adults"                  => ["Adults", "Adult"],
            "Room category:"          => ["Room category:", "Room Type"],
            "Room description:"       => ["Room description:", "Rate Description"],
            "Daily rate:"             => ["Daily rate:", "Room Rate"],
            "Rate name:"              => ["Rate name:", "Rate Description"],
            "Total Rate incl. taxes:" => ["Total Rate incl. taxes:", "Total Cost of Stay"],
        ],
    ];

    private $code;
    private static $providers = [
        'movenpick' => [
            'from' => ['@moevenpick.com'],
            'subj' => [
                'Reservation Confirmation #',
            ],
            'body' => [
                '//a[contains(@href,"movenpick.com")]',
            ],
        ],
        'tzell' => [
            'from' => ['@oneandonlypalmilla.com'],
            'subj' => [
                'Your One&Only Palmilla Reservation',
            ],
            'body' => [
                '//a[contains(@href,"oneandonlyresorts.com")]',
                'One&Only Palmilla',
            ],
        ],
    ];

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        if (!$this->assignLang()) {
            $this->logger->debug('can\'t determine a language');

            return $email;
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        if (null !== ($code = $this->getProvider($parser))) {
            $email->setProviderCode($code);
        }

        if (!$this->parseEmail($email)) {
            return null;
        }

        return $email;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if (null !== $this->getProviderByBody()) {
            return $this->assignLang();
        }

        return false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        $types = 2;
        $cnt = $types * count(self::$dict);

        return $cnt;
    }

    public static function getEmailProviders()
    {
        return array_keys(self::$providers);
    }

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
        if (empty($headers['from']) || empty($headers['subject'])) {
            return false;
        }

        foreach (self::$providers as $code => $arr) {
            $byFrom = $bySubj = false;

            foreach ($arr['from'] as $f) {
                if (stripos($headers['from'], $f) !== false) {
                    $byFrom = true;
                }
            }

            foreach ($arr['subj'] as $subj) {
                if (stripos($headers['subject'], $subj) !== false) {
                    $bySubj = true;
                }
            }

            if ($byFrom && $bySubj) {
                $this->code = $code;

                return true;
            }
        }

        return false;
    }

    private function getProviderByBody()
    {
        foreach (self::$providers as $code => $arr) {
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

    private function getProvider(\PlancakeEmailParser $parser)
    {
        $this->detectEmailByHeaders(['from' => $parser->getCleanFrom(), 'subject' => $parser->getSubject()]);

        if (isset($this->code)) {
            return $this->code;
        }

        return $this->getProviderByBody();
    }

    private function parseFormat_1(\AwardWallet\Schema\Parser\Common\Hotel $r)
    {
        $r->hotel()
            ->address(implode(" ",
                $this->http->FindNodes("//text()[{$this->eq($this->t('Phone:'))}]/ancestor::tr[1]/td[1]//text()[normalize-space(.)!=''][position()>1]")))
            ->phone($this->nextText("Phone:"), true, true)
            ->fax($this->nextText("Fax:"), true, true);

        $r->general()
            ->traveller($this->nextText("Guest name:"));

        $checkInDate = strtotime($this->normalizeDate($this->nextText("Arrival date:")));
        $checkOutDate = strtotime($this->normalizeDate($this->nextText("Departure date:")));
        $node = $this->nextText("Hotel Description:");

        if (preg_match("#\s+(\d+.{0,3}?\s*[AP]M|\d+:\d+)#i", $node, $m)) {
            $checkInDate = strtotime($this->correctTimeString($this->normalizeDate($m[1])), $checkInDate);
        }

        if (preg_match("#{$this->opt($this->t('Check-out before'))}\s+(\d+.{0,3}?\s*[AP]M|\d+:\d+)#i", $node, $m)) {
            $checkOutDate = strtotime($this->correctTimeString($this->normalizeDate($m[1])), $checkOutDate);
        }
        $r->booked()
            ->checkIn($checkInDate)
            ->checkOut($checkOutDate);

        $node = $this->nextText("Number of guests:");
        $guests = $this->re("#Adults\s*=\s*(\d+)#", $node);
        $kids = $this->re("#Child\s*=\s*(\d+)#", $node);

        if (empty($guests)) {
            $guests = $this->re("#(\d+)\s*Adults?#", $node);
            $kids = $this->re("#(\d+)\s*Child#", $node);
        }
        $r->booked()
            ->guests($guests)
            ->kids($kids, true, true);

        $room = $r->addRoom();
        $room
            ->setType($this->nextText("Room category:"))
            ->setDescription($this->nextText("Room description:"))
            ->setRateType($this->nextText("Rate name:"));
        $rate = $this->re("#\d+\s+(.+)#", $this->nextText("Daily rate:"));

        if (!empty($rate)) {
            $room->setRate($rate . ' per night');
        }
        $tot = $this->getTotalCurrency($this->nextText("Total Rate incl. taxes:"));

        if (!empty($tot['Total'])) {
            $r->price()
                ->total($tot['Total'])
                ->currency($tot['Currency']);
        }

        $cancellationPolicy = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Policies:'))}]/ancestor::td[1]/following::td[1]",
            null, true, "#cancellation policy[\.\s]+(.+)#i");

        if (!empty($cancellationPolicy)) {
            $r->general()->cancellation($cancellationPolicy);
        }

        return true;
    }

    private function parseFormat_2(\AwardWallet\Schema\Parser\Common\Hotel $r)
    {
        $hotelName = $r->getHotelName();

        if (empty($hotelName)) {
            return false;
        }
        $r->hotel()
            ->address(implode(" ",
                $this->http->FindNodes("//text()[normalize-space(.)='{$hotelName}']/ancestor::tr[1]/descendant::text()[normalize-space(.)!=''][position()>1]")));

        if ($this->http->XPath->query("//text()[normalize-space(.)='{$hotelName}']/ancestor::table[{$this->contains($this->t('Phone:'))}][1]")->length > 0) {
            $root = $this->http->XPath->query("//text()[normalize-space(.)='{$hotelName}']/ancestor::table[{$this->contains($this->t('Phone:'))}][1]")->item(0);
            $r->hotel()
                ->phone($this->nextText("Phone:", $root))
                ->fax($this->nextText("Fax:", $root), true, true);
        }

        $r->general()
            ->traveller($this->nextText("Guest name:"));

        $checkInDate = strtotime($this->normalizeDate($this->nextText("Arrival date:")));
        $checkOutDate = strtotime($this->normalizeDate($this->nextText("Departure date:")));
        $node = $this->nextText("Hotel Description:");

        if (preg_match("#{$this->opt($this->t('Check-in after'))}\s+(\d+.{0,3}?\s*[AP]M|\d+:\d+(?:\s*[AP]M)?)#i", $node,
            $m)) {
            $checkInDate = strtotime($this->correctTimeString($this->normalizeDate($m[1])), $checkInDate);
        }

        if (preg_match("#{$this->opt($this->t('Check-out before'))}\s+(\d+.{0,3}?\s*[AP]M|\d+:\d+(?:\s*[AP]M)?)#i",
            $node, $m)) {
            $checkOutDate = strtotime($this->correctTimeString($this->normalizeDate($m[1])), $checkOutDate);
        } else {
            //get next nextText
            $text = $this->nextText("Hotel Description:");
            $node = $this->nextText($text);

            if (preg_match("#{$this->opt($this->t('Check-out before'))}\s+(\d+.{0,3}?\s*[AP]M|\d+:\d+(?:\s*[AP]M)?)#i",
                $node, $m)) {
                $checkOutDate = strtotime($this->correctTimeString($this->normalizeDate($m[1])), $checkOutDate);
            }
        }
        $r->booked()
            ->checkIn($checkInDate)
            ->checkOut($checkOutDate);

        $node = $this->nextText("Number of guests:");
        $guests = $this->re("#{$this->opt($this->t('Adults'))}\s*=\s*(\d+)#", $node);

        if (empty($guests)) {
            $guests = $this->re("#(\d+)\s+{$this->opt($this->t('Adults'))}#i", $node);
        }

        if (!empty($guests)) {
            $r->booked()->guests($guests);
        }
        $kids = $this->re("#{$this->opt($this->t('Child'))}\s*=\s*(\d+)#", $node);

        if (empty($kids)) {
            $kids = $this->re("#(\d+)\s+{$this->opt($this->t('Child'))}#i", $node);
        }

        if (!empty($kids)) {
            $r->booked()->kids($kids);
        }
        $room = $r->addRoom();
        $room
            ->setType($this->nextText("Room category:"))
            ->setDescription($this->nextText("Room description:"))
            ->setRateType($this->nextText("Rate name:"));
        $rate = $this->re("#\d+\s+(.+)#", $this->nextText("Daily rate:"));

        if (empty($rate)) {
            $rate = $this->re("#([A-Z]{3}\s+[\d\.]+)#", $this->nextText("Daily rate:"));
        }

        if (!empty($rate)) {
            $room->setRate($rate . ' per night');
        }
        $cancellationPolicy = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Policies:'))}]/ancestor::td[1]/following::td[1]",
            null, true, "#cancellation policy[\.\s]+(.+)#i");

        if (empty($cancellationPolicy)) {
            $cancellationPolicy = $this->nextText("Guarantee");
        }

        if (!empty($cancellationPolicy)) {
            $r->general()->cancellation($cancellationPolicy);
        }

        return true;
    }

    private function parseEmail(Email $email)
    {
        $r = $email->add()->hotel();
        $r->general()
            ->confirmation($this->re("#([A-Z\d]{5,})#", $this->nextText("Confirmation number:")));

        $hotelName = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Phone:'))}]/ancestor::tr[1]/td[1]//text()[normalize-space(.)!=''][1]");

        if (empty($hotelName)) {
            $hotelName = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Thank you for choosing'))}]",
                null, true, "#{$this->opt($this->t('Thank you for choosing'))}\s+(.+?)(?:\.|$|, located)#");
        }

        if (!empty($hotelName)) {
            $r->hotel()->name($hotelName);

            return $this->parseFormat_1($r);
        }
        $hotelName = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Thank you for booking at'))}]",
            null, true, "#{$this->opt($this->t('Thank you for booking at'))}\s+(.+?)(?:\.|$)#");

        if (!empty($hotelName) && $this->http->XPath->query("//text()[normalize-space(.)='{$hotelName}']")->length == 1) {
            $r->hotel()->name($hotelName);

            return $this->parseFormat_2($r);
        }

        return false;
    }

    private function normalizeDate($date)
    {
        $in = [
            //Saturday, May 20, 2017
            '#^\s*\w+,\s+(\w+)\s+(\d+),\s+(\d{4})\s*$#i',
            //2PM
            '#^\s*(\d+)\s*([ap]m)\s*$#i',
            //1:30 PM
            '#^\s*(\d+:\d+\s*(?:[ap]m)?)\s*$#i',
        ];
        $out = [
            '$2 $1 $3',
            '$1:00 $2',
            '$1',
        ];
        $str = $this->dateStringToEnglish(preg_replace($in, $out, $date));

        return $str;
    }

    private function nextText($field, $root = null, $n = 1)
    {
        $rule = $this->eq($this->t($field));

        return $this->http->FindSingleNode("(.//text()[{$rule}])[{$n}]/following::text()[normalize-space(.)!=''][1]",
            $root);
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
        if (isset($this->reBody)) {
            $body = $this->http->Response['body'];

            foreach ($this->reBody as $lang => $reBody) {
                foreach ($reBody as $r) {
                    if (stripos($body, $r) !== false) {
                        $this->lang = $lang;

                        return true;
                    }
                }
            }
        }

        return false;
    }

    private function dateStringToEnglish($date)
    {
        if (preg_match('#[[:alpha:]]+#iu', $date, $m)) {
            $monthNameOriginal = $m[0];

            if ($translatedMonthName = \AwardWallet\Engine\MonthTranslate::translate($monthNameOriginal, $this->lang)) {
                return preg_replace("#$monthNameOriginal#i", $translatedMonthName, $date);
            }
        }

        return $date;
    }

    private function getTotalCurrency($node)
    {
        $node = str_replace("$", "USD", $node);
        $tot = '';
        $cur = '';

        if (preg_match("#(?<c>[A-Z]{3})\s*(?<t>\d[\.\d\,\s]*\d*)#", $node,
                $m) || preg_match("#(?<t>\d[\.\d\,\s]*\d*)\s*(?<c>[A-Z]{3})#", $node,
                $m) || preg_match("#(?<c>\-*?)(?<t>\d[\.\d\,\s]*\d*)#", $node, $m)
        ) {
            $m['t'] = preg_replace("#,(\d{3})$#", '$1', $m['t']);

            if (strpos($m['t'], ',') !== false && strpos($m['t'], '.') !== false) {
                if (strpos($m['t'], ',') < strpos($m['t'], '.')) {
                    $m['t'] = str_replace(',', '', $m['t']);
                } else {
                    $m['t'] = str_replace('.', '', $m['t']);
                    $m['t'] = str_replace(',', '.', $m['t']);
                }
            }
            $tot = str_replace(",", ".", str_replace(' ', '', $m['t']));
            $cur = $m['c'];
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

    private function correctTimeString($time)
    {
        if (preg_match("#(\d+):(\d+)\s*([ap]m)#i", $time, $m)) {
            if (($m[1] == 0 && stripos($m[3], 'am') !== false) || $m[1] > 12) {
                return $m[1] . ":" . $m[2];
            }
        }

        return $time;
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
        }

        return implode(' or ', array_map(function ($s) {
            return 'normalize-space(.)="' . $s . '"';
        }, $field));
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
        }

        return implode(' or ', array_map(function ($s) {
            return 'starts-with(normalize-space(.),"' . $s . '")';
        }, $field));
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
        }

        return implode(' or ', array_map(function ($s) {
            return 'contains(normalize-space(.),"' . $s . '")';
        }, $field));
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return "(?:" . preg_replace("# #", '\s+', preg_quote($s)) . ")";
        }, $field)) . ')';
    }
}
