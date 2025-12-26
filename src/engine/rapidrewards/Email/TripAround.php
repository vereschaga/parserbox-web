<?php

namespace AwardWallet\Engine\rapidrewards\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Engine\WeekTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class TripAround extends \TAccountChecker
{
    public $mailFiles = "rapidrewards/it-11171650.eml, rapidrewards/it-12217623.eml, rapidrewards/it-12288520.eml, rapidrewards/it-12290411.eml, rapidrewards/it-12298077.eml, rapidrewards/it-12430484.eml, rapidrewards/it-12445124.eml, rapidrewards/it-12454983.eml, rapidrewards/it-13317319.eml, rapidrewards/it-13513489.eml, rapidrewards/it-2258966.eml, rapidrewards/it-2267179.eml, rapidrewards/it-3017645.eml, rapidrewards/it-6304797.eml, rapidrewards/it-13642304.eml";

    public $lang = '';

    private $reSubject = [
        'en' => ['Passenger Itinerary', 'Flight reservation (', 'Confirmation:'],
    ];

    private $langDetectors = [
        'en' => ['AIR Itinerary', 'Air itinerary', "Arrive in"],
    ];

    private static $dictionary = [
        'en' => [
            'This e-mail contains Southwest Airlines' => [
                'This e-mail contains Southwest Airlines',
                'Southwest Airlines Electronic Ticket Travel',
            ],
            'AIR Confirmation:'  => ['AIR Confirmation:', 'AIR Cancellation:'],
            'Confirmation Date:' => ['Confirmation Date:', 'Cancellation Date:'],
        ],
    ];
    private $date = null;

    public function detectEmailFromProvider($from)
    {
        return strpos($from, 'Southwest Airlines') !== false
            || stripos($from, '@luv.southwest.com') !== false
            || stripos($from, '@global.southwest.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (!isset($headers['from'])) {
            return false;
        }

        if (self::detectEmailFromProvider($headers['from']) !== true && strpos($headers['subject'], 'Southwest Airlines') === false) {
            return false;
        }

        foreach ($this->reSubject as $phrases) {
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
        $body = $parser->getHTMLBody();

        if (stripos($body, 'Southwest.com') === false && stripos($body, 'Thanks for choosing Southwest') === false) {
            return false;
        }

        return $this->assignLang();
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        if (!$this->assignLang()) {
            $this->logger->debug('can\'t determine a language');

            return $email;
        }

        $a = explode('\\', __CLASS__);
        $class = end($a);
        $email->setType($class . ucfirst($this->lang));

        $this->date = strtotime($parser->getHeader('date'));

        $this->parseHtml($email);

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

    public function dateStringToEnglish($date)
    {
        if (preg_match('#[[:alpha:]]+#iu', $date, $m)) {
            $monthNameOriginal = $m[0];

            if ($translatedMonthName = \AwardWallet\Engine\MonthTranslate::translate($monthNameOriginal, $this->lang)) {
                return preg_replace("#$monthNameOriginal#i", $translatedMonthName, $date);
            }
        }

        return $date;
    }

    private function parseHtml(Email $email)
    {
        $patterns = [
            'time' => '\d{1,2}:\d{2}(?:\s*[AaPp][Mm])?',
        ];

        $email->ota()->code('rapidrewards');

        $f = $email->add()->flight();

        // status
        $status = $this->http->FindSingleNode('(//td[not(.//td) and ' . $this->contains($this->t('Your reservation has been')) . '])[1]', null, true, '/' . $this->opt($this->t('Your reservation has been')) . '\s+(\w+)(?:[,.]|$)/um');

        if ($status) {
            $f->general()->status($status);
            // cancelled
            if (strcasecmp($status, 'cancelled') === 0) {
                $f->general()->cancelled();
            }
        }

        // confirmationNumber
        $confirmationCodeTitle = $this->http->FindSingleNode('//text()[' . $this->eq($this->t('AIR Confirmation:')) . ']');
        $f->general()->confirmation($this->nextText($this->t('AIR Confirmation:')), trim($confirmationCodeTitle, ': '));

        $passengers = [];
        $accountNumbers = [];
        $ticketNumbers = [];
        $earnedPoints = [];
        $passengerRows = $this->http->XPath->query('//table[ ./preceding-sibling::table[not(.//table) and starts-with(normalize-space(.),"Passenger(s)")] and ./following::table[not(.//table) and ' . $this->starts([
            'Date Flight',
            'DateFlight',
        ]) . '] ]/descendant::tr[not(.//tr) and count(./*[normalize-space(.)])>3 and not(' . $this->starts($this->t('Date')) . ') and not(' . $this->contains($this->t('Departure/Arrival')) . ')]');

        foreach ($passengerRows as $passengerRow) {
            $xpathFragment1 = './td[normalize-space(.)][1][not(.//a)]';

            if (($passenger = $this->http->FindSingleNode($xpathFragment1, $passengerRow, true,
                    '/^([A-z][-.\'A-z\s\/]*[.A-z])$/'))
            ) {
                $passengers[] = $passenger;
            }

            if ($accountNumber = $this->http->FindSingleNode($xpathFragment1 . '/following-sibling::td[1]',
                $passengerRow, true, '/^(\d[\d\s]{5,})$/')
            ) {
                $accountNumbers[] = $accountNumber;
            }

            if ($ticketNumber = $this->http->FindSingleNode($xpathFragment1 . '/following-sibling::td[2]',
                $passengerRow, true, '/^(\d[\d\s]{6,})$/')
            ) {
                $ticketNumbers[] = $ticketNumber;
            }
            $points = $this->http->FindSingleNode($xpathFragment1 . '/following-sibling::td[4]', $passengerRow, true,
                '/^(\d[.\d]*)$/');

            if ($points !== null) {
                $earnedPoints[] = $points;
            }
        }

        if (!empty($passengers[0])) {
            $f->general()->travellers($passengers);
        }

        if (!empty($accountNumbers[0])) {
            $email->ota()->accounts($accountNumbers, false);
        }

        if (!empty($ticketNumbers[0])) {
            $f->issued()->tickets($ticketNumbers, false);
        }

        if (count($earnedPoints)) {
            $email->ota()->earnedAwards(array_sum($earnedPoints));
        }

        $totalPaymentTexts = $this->http->FindNodes("//text()[{$this->eq("Total Air Cost")}]/ancestor::td[1]/following-sibling::td[normalize-space(.)]");

        if (count($totalPaymentTexts)) {
            $totalPaymentText = implode(' ', $totalPaymentTexts);
            $tot = $this->getTotalCurrency($totalPaymentText);

            if (!empty($tot['Total'])) {
                $email->price()
                    ->total($tot['Total'])
                    ->currency($tot['Currency']);
            }
        }

        $basePaymentTexts = $this->http->FindNodes("//text()[{$this->eq("Base Fare")}]/ancestor::td[1]/following-sibling::td[normalize-space(.)]");

        if (count($basePaymentTexts)) {
            $basePaymentText = implode(' ', $basePaymentTexts);
            $tot = $this->getTotalCurrency($basePaymentText);

            if (!empty($tot['Total'])) {
                $email->price()
                    ->cost($tot['Total'])
                    ->currency($tot['Currency']);
            }
        }

        $confirmationDate = $this->http->FindSingleNode("//text()[" . $this->starts($this->t('Confirmation Date:')) . "]", null,
            true, '/' . $this->opt($this->t('Confirmation Date:')) . '\s+(.+)/');

        if ($confirmationDate) {
            if ($resDate = $this->normalizeDate($confirmationDate)) {
                $f->general()->date($resDate);
                $this->date = $resDate;
            }
        }

        $xpath = "//text()[{$this->starts("Arrive in")}]/ancestor::tr[1]";
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $datestr = $this->http->FindSingleNode("./td[1][count(./descendant::text()[normalize-space(.)!=''])=1]",
                $root);

            if (!$datestr) {
                $datestr = $this->http->FindSingleNode("./preceding::tr[td[1][count(./descendant::text()[normalize-space(.)!=''])=1]][1]/td[1]",
                    $root);
            }
            $date = $this->normalizeDate($datestr);

            $s = $f->addSegment();

            $s->airline()
                ->number($this->http->FindSingleNode('./td[2]', $root, true, '/^(\d+)/'));

            $deparr = implode(' ',
                $this->http->FindNodes("./td[normalize-space(.)!=''][last()]/descendant::text()[normalize-space(.)!='']",
                    $root));

            if (
                preg_match("#Changes? planes to (?<AirlineName>.*?)[ ]*in[ ]*(?<Name>[A-Z]{2}.*?) \((?<Code>[A-Z]{3})\)[ ]*at[ ]*(?<Time>{$patterns['time']})#", $deparr, $m)
                || preg_match("#Depart (?<Name>.*?) \((?<Code>[A-Z]{3})\)[ ]*on[ ]*(?<AirlineName>.*?)(?:[ ]*at)?[ ]+(?<Time>{$patterns['time']})#", $deparr, $m)
            ) {
                $s->departure()
                    ->code($m['Code'])
                    ->name($m['Name'])
                    ->date($this->normalizeDate($m['Time'], $date));
                $airlineName = $m['AirlineName'];
            } elseif (
                preg_match("#Depart (?<Name>.*?)[ ]*at[ ]*(?<Time>{$patterns['time']})#", $deparr, $m)
                || preg_match("#Changes? planes in (?<Name>.*?)[ ]*at[ ]*(?<Time>{$patterns['time']})#", $deparr, $m)
            ) {
                $s->departure()
                    ->noCode()
                    ->name($m['Name'])
                    ->date($this->normalizeDate($m['Time'], $date));
            }

            if (preg_match("#Arrive in (?<Name>.*?) \((?<Code>[A-Z]{3})\)[ ]*at[ ]*(?<Time>{$patterns['time']})#", $deparr,
                $m)) {
                $s->arrival()
                    ->code($m['Code'])
                    ->name($m['Name'])
                    ->date($this->normalizeDate($m['Time'], $date));
            } elseif (preg_match("#Arrive in (?<Name>.*?)[ ]*at[ ]*(?<Time>{$patterns['time']})#", $deparr, $m)) {
                $s->arrival()
                    ->noCode()
                    ->name($m['Name'])
                    ->date($this->normalizeDate($m['Time'], $date));
            }

            // Operator
            $operator = $this->http->FindSingleNode('./td[2]', $root, true, '/Operated\s*by\s*(.*?)\s*#/');

            if ($operator) {
                $s->airline()->operator($operator);
            }

            // Duration
            $travelTime = $this->http->FindSingleNode('.//text()[' . $this->starts("Travel Time") . ']', $root, true,
                '/Travel Time (.+)/');

            if ($travelTime) {
                $s->extra()->duration($travelTime);
            }

            if (0 < $this->http->XPath->query("//node()[{$this->contains($this->t('This e-mail contains Southwest Airlines'))}]")->length) {
                $airlineName = 'WN';
            }

            if (isset($airlineName)) {
                $s->airline()->name($airlineName);
            }
        }
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

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function normalizeDate($instr, $relDate = false)
    {
        if ($relDate === false) {
            $relDate = $this->date;
        }
        // $this->logger->info($instr);
        $in = [
            "#^(?<week>[^\s\d]+) ([^\s\d]+) (\d+)$#", //Fr 23. Mrz 17:00 Uhr
        ];
        $out = [
            "$3 $2 %Y%",
        ];
        $str = preg_replace($in, $out, $instr);

        if (preg_match("#\d+\s+([^\d\s]+)\s+(?:\d{4}|%Y%)#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        foreach ($in as $re) {
            if (preg_match($re, $instr, $m) && isset($m['week'])) {
                $str = str_replace("%Y%", date('Y', $relDate), $str);
                $dayOfWeekInt = WeekTranslate::number1($m['week'], $this->lang);

                return EmailDateHelper::parseDateUsingWeekDay($str, $dayOfWeekInt);
            }
        }

        if (strpos($str, "%Y%") !== false) {
            return EmailDateHelper::parseDateRelative(null, $relDate, true, $str);
        }

        return strtotime($str, $relDate);
    }

    private function nextText($field, $root = null)
    {
        $rule = $this->eq($field);

        return $this->http->FindSingleNode("(.//text()[{$rule}])[1]/following::text()[normalize-space(.)!=''][1]", $root);
    }

    private function eq($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false';
        }

        return '(' . implode(' or ', array_map(function ($s) {
            return 'normalize-space(.)="' . $s . '"';
        }, $field)) . ')';
    }

    private function starts($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false';
        }

        return '(' . implode(' or ', array_map(function ($s) {
            return 'starts-with(normalize-space(.),"' . $s . '")';
        }, $field)) . ')';
    }

    private function contains($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false';
        }

        return '(' . implode(' or ', array_map(function ($s) {
            return 'contains(normalize-space(.),"' . $s . '")';
        }, $field)) . ')';
    }

    private function opt($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return '';
        }

        return '(?:' . implode('|', array_map(function ($s) { return preg_quote($s, '/'); }, $field)) . ')';
    }

    private function getTotalCurrency($node)
    {
        $node = str_replace("₹", "INR", $node);
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
}
