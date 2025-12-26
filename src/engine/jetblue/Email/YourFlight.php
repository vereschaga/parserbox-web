<?php

namespace AwardWallet\Engine\jetblue\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Engine\WeekTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class YourFlight extends \TAccountChecker
{
    public $mailFiles = "jetblue/it-30202841.eml, jetblue/it-30176369.eml, jetblue/it-36479639.eml";
    public $date;
    public $lang = '';
    public static $dict = [
        'en' => [
            'Your flight confirmation code:' => ['Your flight confirmation code:', 'Your flight confirmation code is'],
            //            'DATE' => '',
            //            'DEPARTS / ARRIVE' => '',
            //            'ROUTE' => '',
            //            'FLIGHTS / OPERATED BY' => '',
            //            'TERMINAL' => '',
            //            ' to ' => '',
            //            'Operated by' => '',
            'TRAVELERS' => ['TRAVELER', 'TRAVELERS', 'TRAVELLERS'],
            //            'SEATS' => '',
        ],
    ];
    private $subjects = [
        'en' => ['Your JetBlue itinerary', 'Your itinerary for your upcoming JetBlue Vacations trip'],
    ];
    private $langDetectors = [
        'en' => ['FLIGHTS / OPERATED BY'],
    ];

    // Standard Methods

    public function detectEmailFromProvider($from)
    {
        return strpos($from, 'JetBlue Reservations') !== false
            || stripos($from, '@email.jetblue.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (self::detectEmailFromProvider($headers['from']) !== true && strpos($headers['subject'], 'JetBlue') === false) {
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
        $condition1 = $this->http->XPath->query('//node()[contains(normalize-space(.),"Download the JetBlue Mobile App") or contains(.,"www.jetblue.com") or contains(.,"@jetblue.com") or contains(.,"@email.jetblue.com")]')->length === 0;
        $condition2 = $this->http->XPath->query('//a[contains(@href,"//email.jetblue.com")]')->length === 0;

        if ($condition1 && $condition2) {
            return false;
        }

        return $this->assignLang();
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();

        if (empty($this->lang)) {
            $this->logger->debug("Can't determine a language!");

            return $email;
        }

        $this->date = strtotime($parser->getDate());

        $nodesToStip = $this->http->XPath->query('//*[contains(@style,"display:none")]');

        foreach ($nodesToStip as $nodeToStip) {
            $nodeToStip->parentNode->removeChild($nodeToStip);
        }

        $this->parseEmail($email);

        $totalPaid = $this->http->FindSingleNode("//tr[ *[6][{$this->eq($this->t('TOTAL PAID'))}] ]/following-sibling::tr[normalize-space()][1]/*[6]");

        if (preg_match('/^(?<currency1>[^\d)(]+?)[ ]*(?<amount>\d[,.\'\d]*)[ ]*(?<currency2>[A-Z]{3})\b/', $totalPaid, $m)) {
            // $5071.01 USD
            $email->price()
                ->total($this->normalizeAmount($m['amount']))
                ->currency($m['currency2'])
            ;

            $basePrice = $this->http->FindSingleNode("//tr[ *[2][{$this->eq($this->t('BASE PRICE'))}] ]/following-sibling::tr[normalize-space()][1]/*[2]");

            if (preg_match('/^' . preg_quote($m['currency1'], '/') . '[ ]*(?<amount>\d[,.\'\d]*)/', $basePrice, $matches)) {
                $email->price()->cost($this->normalizeAmount($matches['amount']));
            }

            $taxes = $this->http->FindSingleNode("//tr[ *[3][{$this->eq($this->t('TAXES & FEES'))}] ]/following-sibling::tr[normalize-space()][1]/*[3]");

            if (preg_match('/^' . preg_quote($m['currency1'], '/') . '[ ]*(?<amount>\d[,.\'\d]*)/', $taxes, $matches)) {
                $email->price()->tax($this->normalizeAmount($matches['amount']));
            }

            $extras = $this->http->FindSingleNode("//tr[ *[5][{$this->eq($this->t('EXTRAS'))}] ]/following-sibling::tr[normalize-space()][1]/*[5]");

            if (preg_match('/^' . preg_quote($m['currency1'], '/') . '[ ]*(?<amount>\d[,.\'\d]*)/', $extras, $matches)) {
                $extrasTitle = $this->http->FindSingleNode("//tr/*[5][{$this->eq($this->t('EXTRAS'))}]");
                $email->price()->fee($extrasTitle, $this->normalizeAmount($matches['amount']));
            }
        }

        $email->setType('YourFlight' . ucfirst($this->lang));

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

    private function parseEmail(Email $email)
    {
        // 4:19PM    |    2:00 p.m.
        $patterns['time'] = '\d{1,2}:\d{2}(?:\s*[AaPp]\.?[Mm]\.?)?';

        /////////////
        // FLIGHTS //
        /////////////

        $f = $email->add()->flight();

        // confirmation number
        $confirmationCodeTitle = $this->http->FindSingleNode("descendant::node()[{$this->eq($this->t('Your flight confirmation code:'))}][1]");
        $confirmationCode = $this->http->FindSingleNode("descendant::node()[{$this->eq($this->t('Your flight confirmation code:'))}][1]/following::text()[normalize-space()][1]", null, true, '/^[A-Z\d]{5,}$/');
        $f->general()->confirmation($confirmationCode, preg_replace('/\s*:\s*$/', '', $confirmationCodeTitle));

        $travellers = [];
        $ffNumbers = [];

        $segments = $this->http->XPath->query("//tr[ ./*[4]/descendant::tr[not(.//tr) and normalize-space(.)][{$this->contains($this->t('DEPARTS / ARRIVE'))}] and ./*[6]/descendant::tr[not(.//tr) and normalize-space(.)][{$this->contains($this->t('ROUTE'))}] ]");

        foreach ($segments as $key => $segment) {
            $s = $f->addSegment();

            $date = 0;
            $dateText = $this->http->FindSingleNode("./*[2]/descendant::tr[not(.//tr) and normalize-space(.)][{$this->contains($this->t('DATE'))}]/following-sibling::tr[normalize-space(.)][1]", $segment);

            if (preg_match('/^(?<wday>[^\d\W]{2,})\s*,\s*(?<date>.{3,})$/u', $dateText, $matches)) {
                // Fri, Jan 04
                $weekDayNumber = WeekTranslate::number1($matches['wday']);
                $dateDep = $this->normalizeDate($matches['date']);

                if ($weekDayNumber && $dateDep) {
                    $dateDep .= date("Y", $this->date);
                    $date = EmailDateHelper::parseDateUsingWeekDay($dateDep, $weekDayNumber);
                }
            }

            $xpathFragmentTime = "./*[4]/descendant::tr[not(.//tr) and normalize-space(.)][{$this->contains($this->t('DEPARTS / ARRIVE'))}]/following-sibling::tr[normalize-space(.)][1]/descendant::text()[normalize-space(.)]";

            // depDate
            $timeDep = $this->http->FindSingleNode($xpathFragmentTime . '[1]', $segment, true, "/^{$patterns['time']}$/");

            if ($date && $timeDep) {
                $s->departure()->date(strtotime($timeDep, $date));
            }

            // arrDate
            $timeArr = $this->http->FindSingleNode($xpathFragmentTime . '[2]', $segment, true, "/^{$patterns['time']}$/");

            if ($date && $timeArr) {
                $s->arrival()->date(strtotime($timeArr, $date));
            }

            $route = $this->http->FindSingleNode("./*[6]/descendant::tr[not(.//tr) and normalize-space(.)][{$this->contains($this->t('ROUTE'))}]/following-sibling::tr[normalize-space(.)][1]", $segment);
            $airports = preg_split("/{$this->opt($this->t(' to '))}/", $route);

            if (count($airports) !== 2) {
                $this->logger->alert("Segment-$key: wrong ROUTE!");

                continue;
            }

            $patterns['airport'] = '/^(.*?)\s*\(\s*([A-Z]{3})\s*\)$/'; // Boston, MA (BOS)

            // depName
            // depCode
            if (preg_match($patterns['airport'], $airports[0], $m)) {
                if (preg_match('/^\w.+/u', $m[1]) > 0) {
                    $s->departure()->name($m[1]);
                }
                $s->departure()->code($m[2]);
            } else {
                $s->departure()
                    ->name($airports[0])
                    ->noCode();
            }

            // arrName
            // arrCode
            if (preg_match($patterns['airport'], $airports[1], $m)) {
                if (preg_match('/^\w.+/u', $m[1]) > 0) {
                    $s->arrival()->name($m[1]);
                }
                $s->arrival()->code($m[2]);
            } else {
                $s->arrival()
                    ->name($airports[1])
                    ->noCode();
            }

            $xpathFragmentFlight = "./*[8]/descendant::tr[not(.//tr) and normalize-space(.)][{$this->contains($this->t('FLIGHTS / OPERATED BY'))}]/following-sibling::tr[normalize-space(.)][1]/descendant::text()[normalize-space()]";

            // airlineName
            // flightNumber
            $flight = $this->http->FindSingleNode($xpathFragmentFlight . '[1]', $segment);

            if (preg_match('/^(?<airline>[A-Z][A-Z\d]|[A-Z\d][A-Z])?\s*(?<flightNumber>\d+)$/', $flight, $m)) {
                if (empty($m['airline'])) {
                    $s->airline()->noName();
                } else {
                    $s->airline()->name($m['airline']);
                }
                $s->airline()->number($m['flightNumber']);
            }

            // operatedBy
            $operator = $this->http->FindSingleNode($xpathFragmentFlight . "[{$this->contains($this->t('Operated by'))}]", $segment, true, "/{$this->opt($this->t('Operated by'))}\s*(.+)/");
            $s->airline()->operator($operator, false, true);

            // depTerminal
            $terminal = $this->http->FindSingleNode("./*[10]/descendant::tr[not(.//tr) and normalize-space(.)][{$this->contains($this->t('TERMINAL'))}]/following-sibling::tr[normalize-space(.)][1]", $segment, true, '/^[A-z\d\s]+$/');
            $s->departure()->terminal($terminal, false, true);

            $seats = [];

            $travellerRows = $this->http->XPath->query("ancestor::*[ (self::table or self::tr) and following-sibling::*[not({$this->contains($this->t('ROUTE'))}) and string-length(normalize-space())>1] ][1]/following-sibling::*[string-length(normalize-space())>1][position()<3]/descendant::tr[ *[2][{$this->contains($this->t('TRAVELERS'))}] and *[6][{$this->contains($this->t('SEATS'))}] ]/following-sibling::tr[ *[6] ]", $segment);

            foreach ($travellerRows as $row) {
                $traveller = $this->http->FindSingleNode('./*[2]', $row, true, '/^[[:alpha:]][-.\'[:alpha:]\s]*[[:alpha:]]$/u');

                if ($traveller) {
                    $travellers[] = $traveller;
                }

                // B6 3895676463
                $ffNumber = $this->http->FindSingleNode('./*[4]', $row, true, '/^(?:[A-Z][A-Z\d]|[A-Z\d][A-Z])?[-\/\s]*\d{7,}$/');

                if ($ffNumber) {
                    $ffNumbers[] = $ffNumber;
                }

                $seat = $this->http->FindSingleNode('./*[6]', $row, true, '/^\d{1,5}[A-Z]$/');

                if ($seat) {
                    $seats[] = $seat;
                }
            }

            if (count($seats)) {
                $s->extra()->seats($seats);
            }
        }

        // travellers
        if (count($travellers)) {
            $f->general()->travellers(array_unique($travellers));
        }

        // accountNumbers
        if (count($ffNumbers)) {
            $f->program()->accounts(array_unique($ffNumbers), false);
        }

        ////////////
        // HOTELS //
        ////////////

        $bookingCode = $this->http->FindSingleNode("descendant::node()[{$this->eq($this->t('Your vacations booking code is'))}][1]/following::text()[normalize-space()][1]", null, true, '/^[A-Z\d]{5,}$/');

        $hotels = $this->http->XPath->query("//tr[ *[2][{$this->contains($this->t('CHECK IN'))}] and *[6][{$this->contains($this->t('PROPERTY'))}] ]");

        foreach ($hotels as $root) {
            $h = $email->add()->hotel();

            if ($bookingCode) {
                $bookingCodeTitle = $this->http->FindSingleNode("descendant::node()[{$this->eq($this->t('Your vacations booking code is'))}][1]");
                $h->general()->confirmation($bookingCode, preg_replace('/\s*:\s*$/', '', $bookingCodeTitle));
            }

            $checkInText = $this->http->FindSingleNode("following-sibling::tr[normalize-space()][1]/*[2]", $root);

            if (preg_match("/^(?<wday>[[:alpha:]]{2,})\s*,\s*(?<date>.{3,}?)\s*(?<time>{$patterns['time']})$/u", $checkInText, $matches)) {
                // Sun, Apr 21 03:00 PM
                $weekDayNumber = WeekTranslate::number1($matches['wday']);
                $checkInDate = $this->normalizeDate($matches['date']);

                if ($weekDayNumber && $checkInDate) {
                    $checkIn = EmailDateHelper::parseDateUsingWeekDay($checkInDate, $weekDayNumber);
                    $h->booked()->checkIn(strtotime($matches['time'], $checkIn));
                }
            }

            $checkOutText = $this->http->FindSingleNode("following-sibling::tr[normalize-space()][1]/*[4]", $root);

            if (preg_match("/^(?<wday>[[:alpha:]]{2,})\s*,\s*(?<date>.{3,}?)\s*(?<time>{$patterns['time']})$/u", $checkOutText, $matches)) {
                // Sun, Apr 21 03:00 PM
                $weekDayNumber = WeekTranslate::number1($matches['wday']);
                $checkOutDate = $this->normalizeDate($matches['date']);

                if ($weekDayNumber && $checkOutDate) {
                    $checkOut = EmailDateHelper::parseDateUsingWeekDay($checkOutDate, $weekDayNumber);
                    $h->booked()->checkOut(strtotime($matches['time'], $checkOut));
                }
            }

            $xpathProperty = "following-sibling::tr[normalize-space()][1]/*[6]";

            $hotelName = $this->http->FindSingleNode($xpathProperty . "/descendant::br/preceding::text()[normalize-space()][1][ ancestor::*[self::b or self::strong] ]", $root);
            $h->hotel()->name($hotelName);

            $address = $this->http->FindSingleNode($xpathProperty . "/descendant::br/following-sibling::node()[normalize-space()][1][ not(ancestor-or-self::*[self::b or self::strong]) ]", $root);
            $h->hotel()->address($address);

            $xpathGuests = "ancestor::*[ (self::table or self::tr) and following-sibling::*[not({$this->contains($this->t('CHECK IN'))}) and string-length(normalize-space())>1] ][1]/following-sibling::*[string-length(normalize-space())>1][position()<3]/descendant::tr[ *[4][{$this->contains($this->t('ROOM TYPE'))}] and *[8][{$this->starts($this->t('ADULT'))}] ]/following-sibling::*[string-length(normalize-space())>1]";

            $leadGuest = $this->http->FindSingleNode($xpathGuests . "/*[2]", $root, true, "/^[[:alpha:]][-.\'[:alpha:] ]*[[:alpha:]]$/u");
            $h->general()->traveller($leadGuest);

            $roomType = $this->http->FindSingleNode($xpathGuests . "/*[4]", $root);
            $h->addRoom()->setType($roomType);

            $adult = $this->http->FindSingleNode($xpathGuests . "/*[8]", $root, true, '/^\d{1,3}$/');
            $h->booked()->guests($adult);

            $children = $this->http->FindSingleNode($xpathGuests . "/*[10]", $root, true, '/^\d{1,3}$/');
            $h->booked()->kids($children);
        }
    }

    /**
     * Dependencies `use AwardWallet\Engine\MonthTranslate` and `$this->lang`.
     *
     * @return string|bool
     */
    private function normalizeDate(string $string)
    {
        if (preg_match('/^([^\d\W]{3,})[.\s]+(\d{1,2})$/u', $string, $matches)) {
            // Jan 04
            $month = $matches[1];
            $day = $matches[2];
            $year = '';
        }

        if (isset($day) && isset($month) && isset($year)) {
            if (preg_match('/^\s*(\d{1,2})\s*$/', $month, $m)) {
                return $m[1] . '/' . $day . ($year ? '/' . $year : '');
            }

            if (($monthNew = MonthTranslate::translate($month, $this->lang)) !== false) {
                $month = $monthNew;
            }

            return $day . ' ' . $month . ($year ? ' ' . $year : '');
        }

        return false;
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
}
