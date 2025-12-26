<?php

namespace AwardWallet\Engine\priceline\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Engine\WeekTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

// TODO: merge with parsers priceline/Reservations (in favor of priceline/Reservations)

class It2487277 extends \TAccountChecker
{
    public $mailFiles = "priceline/it-13890533.eml, priceline/it-17024059.eml, priceline/it-2478990.eml, priceline/it-2487277.eml, priceline/it-2489245.eml, priceline/it-2533229.eml, priceline/it-2585586.eml, priceline/it-2586501.eml, priceline/it-2603350.eml, priceline/it-2605080.eml, priceline/it-2613674.eml, priceline/it-26716378.eml, priceline/it-2696542.eml, priceline/it-2698453.eml, priceline/it-2700326.eml, priceline/it-2890975.eml";

    public $reSubject = [
        "en" => ["Your priceline itinerary for"],
    ];

    private $langDetectors = [
        "en" => ["Your Hotel Reservation for", "Congratulations, your hotel for", "Congrats, your hotel for", "Have a great time", "Congratulations, your hotel for", "Check-out:", 'WE GUARANTEE THE LOWEST PRICE ON EVERYTHING YOU BOOK', 'Here are the details on where'],
    ];
    private static $dict = [
        'en' => [
            'Priceline Trip Number:' => ['Priceline Trip Number:', 'PRICELINE TRIP NUMBER:'],
            'Confirmation Number'    => ['Hotel Confirmation Number:', 'Booking Number and PIN Code', 'Confirmation Number', 'CONFIRMATION NUMBER:', 'Agoda booking ID:'],
            'Hotel Address:'         => ['Hotel Address:', 'HOTEL ADDRESS:'],
            'Hotel Phone Number:'    => ['Hotel Phone Number:', 'HOTEL PHONE NUMBER:'],
            'Number of Rooms:'       => ['Number of Rooms:', 'NUMBER OF'],
            'Check-in:'              => ['Check-in:', 'CHECK-IN:'],
            'Check-out:'             => ['Check-out:', 'CHECK-OUT:'],
            'Reservation Name'       => ['Reservation Name', 'RESERVATION'],
            'Room Type:'             => ['Room Type:', 'ROOM TYPE:'],
            'Cancellation Policy'    => ['Cancellation Policy:', 'CANCELLATION POLICY:'],
            'Room Subtotal'          => ['Room Subtotal', 'ROOM SUBTOTAL:'],
            'Taxes & Fees:'          => ['Taxes & Fees:', 'TAXES & FEES:'],
            'Total Charged:'         => ['Total Charged:', 'Total Cost:', 'TOTAL:', 'TOTAL COST:'],
        ],
    ];
    private $date;
    private $lang = '';

    public function detectEmailFromProvider($from)
    {
        return stripos($from, 'Priceline.com Customer Service') !== false
            || stripos($from, '@trans.priceline.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        foreach ($this->reSubject as $phrases) {
            foreach ($phrases as $phrase) {
                if (stripos($headers["subject"], $phrase) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $condition1 = $this->http->XPath->query('//node()[contains(normalize-space(.),"Your priceline itinerary for") or contains(normalize-space(.),"email from priceline.com") or contains(.,"@trans.priceline.com") or contains(.,"@priceline.com")]')->length === 0;
        $condition2 = $this->http->XPath->query('//a[contains(@href,"//www.priceline.com")]')->length === 0;

        if ($condition1 && $condition2) {
            return false;
        }

        return $this->assignLang();
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        if (!$this->assignLang()) {
            $this->logger->debug("Can't determine a language!");

            return $email;
        }

        $this->date = strtotime($parser->getDate());

        $email->ota()->code('priceline'); // because Priceline is travel agency
        $tripNumber = $this->http->FindSingleNode("(//text()[{$this->starts($this->t('Priceline Trip Number:'))}])[1]", null, true, "#^{$this->opt($this->t('Priceline Trip Number:'))}\s*([-A-z\d]{5,})$#");

        if (!$tripNumber) {
            $tripNumber = $this->http->FindSingleNode("(//text()[{$this->starts($this->t('Priceline Trip Number:'))}])[1]/following::text()[normalize-space()][1]", null, true, "#^[-A-z\d]{5,}$#");
        }

        if ($tripNumber && $this->http->XPath->query("//text()[{$this->eq('View Property Details')}]")->length === 0) {
            $email->ota()->confirmation($tripNumber, 'Priceline Trip Number', true);
        }

        $email->setType('YourItinerary' . ucfirst($this->lang));
        $this->parseEmail($email);

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

    public function IsEmailAggregator()
    {
        return false;
    }

    public function dateStringToEnglish($date)
    {
        if (preg_match('#[[:alpha:]]+#iu', $date, $m)) {
            $monthNameOriginal = $m[0];

            if ($translatedMonthName = MonthTranslate::translate($monthNameOriginal, $this->lang)) {
                return preg_replace("#$monthNameOriginal#i", $translatedMonthName, $date);
            }
        }

        return $date;
    }

    private function parseEmail(Email $email)
    {
        $patterns = [
            'travellerName' => '[A-z][-.\'A-z ]*[A-z]', // Mr. Hao-Li Huang
        ];

        $h = $email->add()->hotel();

        // confirmationNumbers
        $confNumber = $this->http->FindSingleNode("(//text()[{$this->contains($this->t('Confirmation Number'))}])[last()]/following::text()[normalize-space(.)!=''][1]", null, true, "/([-A-Z\d]{4,})/");

        if (!isset($confNumber)) {
            $confNumber = $this->http->FindSingleNode("(//text()[{$this->contains($this->t('Confirmation Number'))}])/ancestor::td[1]/following::a[normalize-space(.)!=''][1]", null, true, "/([-A-Z\d]{4,})/");
        }

        $confNumberDesc = trim($this->http->FindSingleNode("(//text()[{$this->contains($this->t('Confirmation Number'))}])[last()]"), ": ");

        if ($confNumber === null && $this->http->XPath->query("//text()[{$this->eq('View Property Details')}]")->length > 0) {
            $h->general()->noConfirmation();
        } else {
            $h->general()->confirmation($confNumber, $confNumberDesc);
        }

        $name = $this->http->FindSingleNode("(//img[contains(@src, 'star')])[1]/ancestor::tr[1]");

        if (empty($name)) {
            $name = $this->http->FindSingleNode("//*[(self::a or self::span) and contains(normalize-space(),'See Hotel Details')]/ancestor::tr[2]/preceding-sibling::tr[1]/descendant::text()[normalize-space()][1]");
        }

        if (empty($name)) {
            $name = $this->http->FindSingleNode("//text()[contains(normalize-space(), 'Nights, ') and contains(normalize-space(), 'Room')]/preceding::text()[normalize-space()][1]");
        }
        $h->hotel()
            ->name($name)
            ->address($this->http->FindSingleNode("//text()[{$this->eq($this->t('Hotel Address:'))}]/ancestor::td[1]/following-sibling::td[1]"))
            ->phone($this->http->FindSingleNode("//text()[{$this->eq($this->t('Hotel Phone Number:'))}]/ancestor::td[1]/following-sibling::td[1]"),
                false, true);

        // roomsCount
        $roomsCount = $this->http->FindSingleNode("(//text()[{$this->starts($this->t('Number of Rooms:'))}]/ancestor::td[1]/following-sibling::td[1])[1]", null, true, "#(\d+)\s+Room#i");

        if (!$roomsCount) {
            $roomsCount = $this->http->FindSingleNode("(//img[contains(@src, 'star')])[1]/ancestor::tr[1]/following::tr[normalize-space(.)][1]", null, true, '/(\d+) *Room/i');
        }

        $guests = array_sum($this->http->FindPregAll("#Room\s+\d+:.*?\bFor\s+(\d+)\s+(?:Guests|Adults)#is"));

        if (empty($guests)) {
            $guests = $this->http->FindPreg("#(\d+)\s+(?:Guests|Adults)#i");
        }

        $h->booked()
            ->checkIn($this->normalizeDate($this->http->FindSingleNode("//text()[{$this->eq($this->t('Check-in:'))}]/ancestor::td[1]/following-sibling::td[1]", null, true, "#(?:{$this->opt($this->t('Check-in:'))})?\s*(.+)#"), 'in'))
            ->checkOut($this->normalizeDate($this->http->FindSingleNode("//text()[{$this->eq($this->t('Check-out:'))}]/ancestor::td[1]/following-sibling::td[1]", null, true, "#(?:{$this->opt($this->t('Check-out:'))})?\s*(.+)#i"), 'out'))
            ->rooms($roomsCount)
            ->guests($guests, false, true)
            ;

        $h->general()
            ->cancellation(preg_replace("#{$this->opt('Cancellation Policy')}[:\s]+#i", '',
                $this->http->FindSingleNode("//text()[{$this->contains($this->t('Cancellation Policy'))}]/ancestor::td[1]/following-sibling::td[1]")),
                true);

        // travellers
        $info = trim(preg_replace("#Room\s+\d+:\s+for\s+(\d+)\s+(?:Guests|Adults)#i", '',
            preg_replace("#{$this->opt($this->t('Reservation Name'))}\s*.*:\s*#", '', implode("\n",
                $this->http->FindNodes("//text()[{$this->starts($this->t('Reservation Name'))}]/ancestor::td[1]/following-sibling::td[1]//text()[normalize-space(.)!='']")))));
        $names = [];
        preg_replace_callback("#Room\s+\d+:\s*([^\n]+)#im", function ($m) use (&$names) {
            $names[$m[1]] = 1;
        }, $info);
        $pax = $names ? array_keys($names) : (array) $info;

        if (empty($pax[0])) {
            $traveller = $this->http->FindSingleNode("//text()[{$this->contains('Here are the details on where')}]", null, true, "/{$this->opt('Here are the details on where')}\s*({$patterns['travellerName']})\s*{$this->opt('will be staying')}/");
            $pax = [$traveller];
        }
        $h->general()
            ->travellers($pax);

        $cnt = $h->getRoomsCount();

        for ($i = 1; $i <= $cnt; $i++) {
            $r = $h->addRoom();
            $r->setRate($this->http->FindSingleNode("//text()[{$this->eq('Room Price:')}]/ancestor::td[1]/following-sibling::td[1]"),
                false, true);

            if ($cnt > 1) {
                $text = 'Room ' . $i;

                if (!empty($confNo = $this->http->FindSingleNode("(//text()[contains(normalize-space(.),'{$text}')])[last()]",
                    null, true, "#\d+:\s+([A-Z\d\-]{4,})#"))
                ) {
                    $r->setConfirmation($confNo);
                }
            }

            $type = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Room Type:'))}]/ancestor::td[1]/following-sibling::td[1]/descendant::text()[normalize-space(.)!=''][not({$this->contains($this->t('Room Type:'))})][1]", null, true, "/(.+?)\s*(?: - |Room|ROOM|Door|Suite|\.|\n)/i");

            if (!$type) {
                $type = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Room Type:'))}]/ancestor::td[1]/following-sibling::td[1]/descendant::text()[normalize-space(.)!=''][not({$this->contains($this->t('Room Type:'))})][1]");
            }

            if ($type) {
                $r->setType($type);
            }

            $r->setDescription(implode(" ",
                $this->http->FindNodes("//text()[{$this->eq($this->t('Room Type:'))}]/ancestor::td[1]/following-sibling::td[1]/descendant::text()[normalize-space(.)!=''][not({$this->contains($this->t('Room Type:'))})][position()>1]")), true);
        }
        $tot = $this->getTotalCurrency($this->http->FindSingleNode("//text()[{$this->contains($this->t('Room Subtotal'))}]/ancestor::td[1]/following-sibling::td[1]"));

        if (!empty($tot['Total'])) {
            $h->price()
                ->cost($tot['Total'])
                ->currency(($tot['Currency']));
        }
        $tot = $this->getTotalCurrency($this->http->FindSingleNode("//text()[{$this->contains($this->t('Taxes & Fees:'))}]/ancestor::td[1]/following-sibling::td[1]"));

        if (!empty($tot['Total'])) {
            $h->price()
                ->tax($tot['Total'])
                ->currency(($tot['Currency']));
        }
        $tot = $this->getTotalCurrency($this->http->FindSingleNode("(//text()[{$this->starts($this->t('Total Charged:'))}]/ancestor::td[1]/following-sibling::td[1])[1]"));

        if (!empty($tot['Total'])) {
            $h->price()
                ->total($tot['Total'])
                ->currency(($tot['Currency']));
        }

        if (!empty($status = $this->http->FindPreg("#You\s+have\s+now\s+(.*?)\s+your\s+reservation#is"))) {
            $h->general()->status($status);
        }

        $this->detectDeadLine($h);
    }

    /**
     * Here we describe various variations in the definition of dates deadLine.
     */
    private function detectDeadLine(\AwardWallet\Schema\Parser\Common\Hotel $h)
    {
        if (empty($cancellationText = $h->getCancellation())) {
            return;
        }

        if (preg_match("#This reservation qualifies for free cancellation up until (?<time>.+?) local hotel time on (?<date>.+? \d{4})\. #i",
            $cancellationText, $m)
        ) {
            $h->booked()
                ->deadline($this->normalizeDate($m['date'] . ' at ' . $m['time']));
        } elseif (preg_match("#^If cancelled or modified up to (?<prior>\d+) days? before date of arrival, 100 percent of the#i",
            $cancellationText, $m)
        ) {
            $h->booked()
                ->deadlineRelative($m['prior'] . ' days', '00:00');
        }

        $h->booked()
            ->parseNonRefundable("#This booking is Non-Refundable and cannot be amended or modified#")
            ->parseNonRefundable("#Remember, your Priceline Hotel Reservation is non-refundable#i")
        ;
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

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function getTotalCurrency($node)
    {
        $node = str_replace("€", "EUR", $node);
        $node = str_replace("$", "USD", $node);
        $node = str_replace("£", "GBP", $node);
        $tot = '';
        $cur = '';

        if (preg_match("#(?<c>[A-Z]{3})\s*(?<t>\d[\.\d\,\s]*\d*)#", $node,
                $m) || preg_match("#(?<t>\d[\.\d\,\s]*\d*)\s*(?<c>[A-Z]{3})#", $node,
                $m) || preg_match("#(?<c>\-*?)(?<t>\d[\.\d\,\s]*\d*)#", $node, $m)
        ) {
            $m['t'] = preg_replace('/\s+/', '', $m['t']);            // 11 507.00	->	11507.00
            $m['t'] = preg_replace('/[,.](\d{3})/', '$1', $m['t']);    // 2,790		->	2790		or	4.100,00	->	4100,00
            $m['t'] = preg_replace('/^(.+),$/', '$1', $m['t']);    // 18800,		->	18800
            $m['t'] = preg_replace('/,(\d{2})$/', '.$1', $m['t']);    // 18800,00		->	18800.00
            $tot = $m['t'];
            $cur = $m['c'];
        }

        return ['Total' => $tot, 'Currency' => $cur];
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

    private function normalizeDate($date, $type = 'in')
    {
        $year = date('Y', $this->date);
        $in = [
            //Sat June 02 - 12:00 PM  |
            '#^([\w\-]+)\s+(\w+)\s+(\d+)\s+\-\s+(\d+:\d+(?:\s*[ap]m)?)$#ui',
            //Saturday, June 02, 2018 (03:00 PM)
            '#^([\w\-]+),?\s+(\w+)\s+(\d+),?\s+(\d{4})\s+\(\s*(\d+:\d+(?:\s*[ap]m)?\s*)\)$#ui',
            //Wed, April 15, 2015 - after 15:00
            '#^([\w\-]+),?\s+(\w+)\s+(\d+),?\s+(\d{4})\s+\-\s+after\s+(\d+:\d+(?:\s*[ap]m)?)$#ui',
            //Thu, March 19, 2015
            '#^([\w\-]+),?\s+(\w+)\s+(\d+),?\s+(\d{4})$#ui',
            // Sat, February 21, 2015  at 12:00 PM 
            '#^([\w\-]+),?\s+(\w+)\s+(\d+),?\s+(\d{4})\s+at\s*(\d+:\d+(?:\s*[ap]m)?\s*)$#ui',
            //Sunday, May 20, 2018 (07:00 12:00)
            $type === 'in'
                ? '#^([\w\-]+),?\s+(\w+)\s+(\d+),?\s+(\d{4})\s+\(\s*(\d+:\d+(?:\s*[ap]m)?).+?\s*\)$#ui'
                : '#^([\w\-]+),?\s+(\w+)\s+(\d+),?\s+(\d{4})\s+\.?[ ]*\(\s*.*?(\d{2}:\d+(?:\s*[ap]m)?\s*)\)$#ui',
        ];
        $out = [
            '$3 $2 ' . $year . ' $4',
            '$3 $2 $4 $5',
            '$3 $2 $4 $5',
            '$3 $2 $4',
            '$3 $2 $4 $5',
            '$3 $2 $4 $5',
        ];
        $outWeek = [
            '$1',
            '',
            '',
            '',
            '',
            '',
        ];

        if (!empty($week = preg_replace($in, $outWeek, $date))) {
            $weeknum = WeekTranslate::number1(WeekTranslate::translate($week, $this->lang));
            $str = $this->dateStringToEnglish(preg_replace($in, $out, $date));
            $str = EmailDateHelper::parseDateUsingWeekDay($str, $weeknum);
        } else {
            $str = $this->dateStringToEnglish(preg_replace($in, $out, $date));
            $str = strtotime($str);
        }

        return $str;
    }
}
