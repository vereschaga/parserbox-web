<?php

namespace AwardWallet\Engine\hawaiian\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Common\Flight;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class ReservationConfirm extends \TAccountChecker
{
    public $mailFiles = "hawaiian/it-11439727.eml, hawaiian/it-116373678.eml, hawaiian/it-12584533.eml, hawaiian/it-19931763.eml, hawaiian/it-247527492.eml, hawaiian/it-258744607.eml, hawaiian/it-260919346.eml, hawaiian/it-69034063.eml, hawaiian/it-724057559.eml, hawaiian/it-8129503.eml, hawaiian/it-890652155.eml";

    private $subject = '';

    private $subjects = [
        'ja' => ['予約確認', '予約コード：'],
        'en' => ['Reservation Confirmation', 'Check in for your flight to', 'Mobile Boarding Pass(es) for', 'has been canceled'],
    ];

    private $langDetectors = [
        'ja' => ['旅程の確認、管理、印刷をする', 'ご搭乗者のチェックインの受付は出発の 24 時間前から開始いたします。', 'ご旅行後にお客様のご意見をお聞かせ下さい。'],
        'en' => ['Review, manage and print your itinerary.', 'Check-in begins 24 hours prior to departure', 'Guest'],
    ];

    private $lang = '';

    private static $dict = [
        'ja' => [
            'Your confirmation code is:' => ['ご予約番号：', '予約コード：'],
            //            'Reservation Confirmation' => '',
            'Guest' => 'ご搭乗者',
            //            'HawaiianMiles member:' => '',
            //            'eTicket#:' => '',
            'Total Travel Cost' => ['合計旅費:', '合計金額:'],
            'Taxes and Fees:'   => '税金および手数料:',
            'Flight #'          => '便名',
            // 'Operated by:'          => '',
            'Route'             => '区間',
            'Depart'            => '出発',
            'Arrive'            => '到着',
            'Flight'            => 'フライト',
            'Seat'              => '座席',
        ],
        'en' => [
            'Your confirmation code is:' => ['Your confirmation code is:', 'Confirmation Code:', 'Confirmation Code'],
            'eTicket#:'                  => ['eTicket#:', 'eTicket #'],
            'HawaiianMiles member:'      => ['HawaiianMiles member:', 'HawaiianMiles number:'],
            'member:'                    => ['member:', 'number:'],
            'feeNames'                   => 'Seat Cost:',
        ],
    ];

    public function detectEmailFromProvider($from)
    {
        return strpos($from, 'Hawaiian Airline') !== false
            || preg_match('/[.@]hawaiianairlines\.com/', $from) > 0;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->detectEmailFromProvider($headers['from']) !== true) {
            return false;
        }

        foreach ($this->subjects as $phrases) {
            foreach ($phrases as $phrase) {
                if (strpos($headers['subject'], $phrase) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $condition1 = $this->http->XPath->query('//node()[contains(normalize-space(.),"departing on a Hawaiian Airlines") or contains(normalize-space(.),"service from Hawaiian Airlines") or contains(.,"HawaiianMiles")]')->length === 0;
        $condition2 = $this->http->XPath->query('//a[contains(@href,".hawaiianairlines.com/")]')->length === 0;

        if ($condition1 && $condition2) {
            return false;
        }

        return $this->assignLang();
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        if ($this->assignLang() === false) {
            return false;
        }

        $this->subject = $parser->getSubject();
        $its = [];
        $this->parseHtml($email);
        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    private function parseHtml(Email $email): void
    {
        $patterns = [
            'travellerName' => '[[:alpha:]][-.\'[:alpha:] ]*[[:alpha:]]',
        ];

        $f = $email->add()->flight();

        if ($this->http->XPath->query("//text()[{$this->contains($this->t('This email confirms that your itinerary has been canceled'))}]")->length > 0) {
            $f->general()
                ->cancelled()
                ->status('canceled');
        }

        $confirmation = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Your confirmation code is:'))}]/following::text()[normalize-space()][1]", null, true, "#^\s*([A-Z\d]{5,6})\s*$#");

        if (empty($confirmation)) {
            $confirmation = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Your confirmation code is:'))}]/following::text()[normalize-space()][1]", null, true, "#^\s*([A-Z\d]{5,6})\s*$#");
        }

        if (empty($confirmation)) {
            $confirmation = $this->re("#(?:Reservation\s+Confirmation| Fwd: 予約コード：)\s+([A-Z\d]{5,})\s*$#u", $this->subject);
        }

        if (empty($confirmation)) {
            $confirmation = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Reservation Confirmation'))}]", null, true, "#\s+([A-Z\d]{5,})\s*$#");
        }

        if (empty($confirmation)) {
            $confirmation = $this->http->FindSingleNode("//img[{$this->contains($this->t('Your confirmation code is:'), '@alt')}]/@alt", null, true, "#{$this->opt($this->t('Your confirmation code is:'))}\s*([A-Z\d]{5,})#");
        }

        $f->general()
            ->confirmation($confirmation);

        $passengers = [];
        $nodes = $this->http->XPath->query("//text()[{$this->eq($this->t('Guest'))}]/ancestor::table[1]/following-sibling::table/descendant::text()[{$this->starts($this->t('Seat'))}]/ancestor::td[1]/preceding::td[1]");

        foreach ($nodes as $root) {
            $passengers[] = implode(" ", $this->http->FindNodes("./descendant::text()[normalize-space()]", $root));
        }

        if (empty($passengers)) {
            $passengers = array_unique(array_filter($this->http->FindNodes("//text()[starts-with(normalize-space(), 'eTicket')]/preceding::text()[normalize-space()][not({$this->contains($this->t('member:'))})][1]", null, "/^{$patterns['travellerName']}$/u")));
        }

        if (empty($passengers)) {
            $nodes = $this->http->FindNodes("//text()[{$this->eq($this->t('Guest'))}]/ancestor::*[1]/following-sibling::text()[normalize-space(.)]");

            if ((count($nodes) % 2) === 0) {//even
                foreach ($nodes as $i => $value) {
                    if ($i & 1) {//odd
                        continue;
                    }
                    $passengers[] = $nodes[$i] . ' ' . $nodes[$i + 1];
                }
            }
        }

        if (empty($passengers)) {
            $passengers = array_filter($this->http->FindNodes("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][2][{$this->contains($this->t('Boarding Pass'))}] ]/*[normalize-space()][1]", null, "/^{$patterns['travellerName']}$/u"));
        }

        if (empty($passengers)) {
            $passengers = array_unique(array_filter($this->http->FindNodes("//text()[{$this->eq($this->t('Guest'))}]/ancestor::table[1][count(.//text()[normalize-space()])=2 and .//text()[{$this->eq($this->t('Details'))}]]/following-sibling::table//tr[count(*[normalize-space()])=2]/*[normalize-space()][1]", null, "/^{$patterns['travellerName']}$/u")));
        }

        $f->general()
            ->travellers(array_values(array_unique($passengers)));

        $accounts = array_unique($this->http->FindNodes("//text()[{$this->contains($this->t('HawaiianMiles member:'))}]"));

        foreach ($accounts as $account) {
            if (preg_match("#^\s*(.+?)\s*:\s*(\w[\w \-]+)\s*$#", $account, $m)) {
                $name = $this->http->FindSingleNode("//text()[contains(., '{$account}')]/preceding::text()[normalize-space()][position() < 4][{$this->eq(array_column($f->getTravellers(), 0))}][1]");
                $f->program()
                    ->account($m[2], false, $name, $m[1]);
            }
        }

        $tickets = array_unique(array_filter($this->http->FindNodes("//text()[{$this->contains($this->t('eTicket#:'))}]", null, "#\:?\s*([\d \-]+)$#s")));

        foreach ($tickets as $ticket) {
            $name = $this->http->FindSingleNode("//text()[contains(., '{$ticket}')]/preceding::text()[normalize-space()][position() < 5][{$this->eq(array_column($f->getTravellers(), 0))}][1]");
            $f->issued()
                ->ticket($ticket, false, $name);
        }

        $spentAwards = $this->http->FindSingleNode("//text()[contains(normalize-space(), 'You used')]/following::text()[normalize-space()][1][contains(normalize-space(), 'HawaiianMiles')]", null, true, "/^([\d\.\,]+\s*{$this->opt($this->t('HawaiianMiles'))})/");

        if (empty($spentAwards)) {
            $spentAwards = $this->http->FindSingleNode("//text()[contains(normalize-space(), 'Mileage used:')]", null, true, "/^{$this->opt($this->t('Mileage used:'))}\s*([\d\.\,]+.*)/");
        }

        if (!empty($spentAwards)) {
            $f->price()
                ->spentAwards($spentAwards);
        }

        $basefare = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Base Airfare:'))}]/following::*[string-length(normalize-space())>0][1]", null, true, "#\D*([\d,. ]+)#");

        if ($basefare !== null) {
            $f->price()
                ->cost($this->normalizeAmount($basefare));
        }

        $totalTravelCost = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Total Travel Cost'))}]/following::*[string-length(normalize-space())>0 and not({$this->eq($this->t('Travel Credit Applied'))})][1]", null, true, "#\D*([\d,. ]+)#");

        if ($totalTravelCost !== null) {
            $f->price()
                ->total($this->normalizeAmount($totalTravelCost));
        }

        $taxesFees = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Taxes and Fees:'))}]/following::*[string-length(normalize-space())>0][1]", null, true, "#\D*([\d,. ]+)#");

        if ($taxesFees !== null) {
            $f->price()
                ->tax($this->normalizeAmount($taxesFees));
        }

        foreach ((array) $this->t('feeNames') as $feeName) {
            $value = $this->http->FindSingleNode("//text()[{$this->contains($feeName)}]/following::*[string-length(normalize-space())>0][1]",
                null, true, "#\D*(\d[\d,. ]+)#");

            if ($value !== null) {
                $f->price()
                    ->fee(trim($this->http->FindSingleNode("//text()[{$this->contains($feeName)}]"), ':'), $this->normalizeAmount($value));
            }
        }

        $currency = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Flight #'))}]/ancestor::td[{$this->contains($this->t('Route'), './following-sibling::td')}]/following-sibling::td[last()]", null, true, "#\b[A-Z]{3}\b#");

        if (empty($currency)) {
            $currency = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Total Travel Cost'))}]/following::*[string-length(normalize-space())>0 and not({$this->eq($this->t('Travel Credit Applied'))})][1]", null, true, "#^\s*\D{1,3}\s*\d[\d,. ]*\s*([A-Z]{3})\s*$#");
        }

        if (empty($currency)) {
            $currency = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Total Travel Cost'))}]/following::*[string-length(normalize-space())>0 and not({$this->eq($this->t('Travel Credit Applied'))})][1]", null, true, "#(\D*)\s*[\d,. ]+#");
        }

        if (!empty($currency)) {
            $f->price()
                ->currency($currency);
        }

        $segments = $this->http->XPath->query($xpath = "//tr[ count(*)=3 and *[1][normalize-space()=''] and *[3][{$this->contains($this->t('Depart'))} and {$this->contains($this->t('Arrive'))} and descendant::text()[normalize-space()][1][{$this->starts($this->t('Flight'))}]] ]/*[3]");

        if ($segments->length > 0) {
            $this->logger->debug('Found segment type-1: ' . $xpath);
            $this->parseSegments1($segments, $f);
        } elseif (($segments = $this->http->XPath->query($xpath = "//tr[ count(*)=3 and *[1][normalize-space()=''] and *[3][{$this->contains($this->t('Depart'))} and {$this->contains($this->t('Arrive'))}] ]/*[3]"))->length > 0) {
            $this->logger->debug('Found segment type-2: ' . $xpath);
            $this->parseSegments2($segments, $f);
        }
    }

    private function parseSegments1($segments, Flight $f): void
    {
        $this->logger->debug(__METHOD__);
        // it-69034063.eml

        /*
            Flight HA 652
            Honolulu → Molokai
            Depart 10/25/2020 02:21 PM
            Arrive 10/25/2020 02:52 PM
        */
        $patterns['segment'] = "/^\s*"
            . "Flight (?<airlineName>[A-Z][A-Z\d]|[A-Z\d][A-Z])[ ]*(?<flightNumber>\d+)[ ]*\n+"
            . "[ ]*(?<depName>.{3,}?)[ ]+→[ ]+(?<arrName>.{3,}?)[ ]*\n+"
            . "[ ]*Depart (?<depDate>.{6,}?)[ ]*\n+"
            . "[ ]*Arrive (?<arrDate>.{6,}?)[ ]*(?:\n|$)"
            . "/";

        foreach ($segments as $root) {
            $s = $f->addSegment();
            $segmentHtml = $this->http->FindHTMLByXpath('.', null, $root);
            $segmentText = $this->htmlToText($segmentHtml);

            if (preg_match($patterns['segment'], $segmentText, $m)) {
                $s->airline()
                    ->name($m['airlineName'])
                    ->number($m['flightNumber']);

                $s->departure()
                    ->name($m['depName'])
                    ->date(strtotime($m['depDate']))
                    ->noCode();

                $s->arrival()
                    ->name($m['arrName'])
                    ->date(strtotime($m['arrDate']))
                    ->noCode();
            }
        }
    }

    private function parseSegments2($segments, Flight $f): void
    {
        $this->logger->debug(__METHOD__);
        // it-11439727.eml

        $patterns = [
            'airportCode' => '/^[^<>\{\}]+$/',
            'dateTime'    => '(?<date>.+)\s+(?<time>\d{1,2}(?::\d{2})?(?:\s*[AaPp午]\.?[Mm前後]\.?|\s*noon)?)',
        ];

        $patterns['segment'] = "#(?<dCode>[A-Z]{3})\s*\W+\s*(?<aCode>[A-Z]{3})"
            . "\s+{$this->opt($this->t('Flight'))}\s*(?<al>[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(?<fn>\d{1,4})"
            . "\s+(?:{$this->opt($this->t('Operated by:'))}(?<operator>.*)\n)?"
            . "[^:]+?"
            . "\n\s*{$this->opt($this->t('Depart'))}\s+(?<dDate>.+)\s+{$this->opt($this->t('Arrive'))}\s+(?<aDate>.+)#u";

        foreach ($segments as $root) {
            $s = $f->addSegment();

            $depInfo = implode("\n", $this->http->FindNodes(".//td[{$this->contains($this->t('Depart'))}]//text()", $root));

            if (preg_match($patterns['segment'], $depInfo, $m)) {
                $s->departure()
                    ->code($m['dCode']);

                $s->arrival()
                    ->code($m['aCode']);

                $s->airline()
                    ->name($m['al'])
                    ->number($m['fn'])
                    ->operator($m['operator'] ?? null, true, true)
                ;

                // DepDate
                if (preg_match('/^' . $patterns['dateTime'] . '$/u', $m['dDate'], $matches)) {
                    $dateDepNormal = $this->normalizeDate($matches['date']);

                    if ($dateDepNormal) {
                        $s->departure()
                            ->date(strtotime($dateDepNormal . ' ' . $this->normalizeTime($matches['time'])));
                    }
                }

                // ArrDate
                if (preg_match('/^' . $patterns['dateTime'] . '$/u', $m['aDate'], $matches)) {
                    $dateArrNormal = $this->normalizeDate($matches['date']);

                    if ($dateArrNormal) {
                        $s->arrival()
                            ->date(strtotime($dateArrNormal . ' ' . $this->normalizeTime($matches['time'])));
                    }
                }
            }

            // Seats
            $seatsNodes = $this->http->XPath->query("./descendant::text()[{$this->starts($this->t('Seat'))}]", $root);

            foreach ($seatsNodes as $sRoot) {
                $seat = $this->http->FindSingleNode(".", $sRoot, true, "#{$this->opt($this->t('Seat'))}\s*(\d{1,3}[A-Z])#");
                $name = implode(' ', $this->http->FindNodes("ancestor::td[1][{$this->starts($this->t('Seat'))}]/preceding-sibling::*[1]//text()[normalize-space()]", $sRoot, "#^\D+$#"));

                if (!empty($seat)) {
                    $s->extra()
                        ->seat($seat, true, true, $name);
                }
            }

            if (!empty($seats)) {
                $s->extra()
                    ->seats($seats);
            }

            // Cabin
            $cabinTexts = $this->http->FindNodes("./descendant::text()[{$this->starts($this->t('Seat'))}]/following-sibling::text()[string-length(normalize-space(.))>1][not(contains(normalize-space(), 'available'))][1]", $root);
            $cabinValues = array_values(array_unique(array_filter($cabinTexts)));

            if (empty($cabinValues) && empty($this->http->FindNodes("./descendant::text()[{$this->starts($this->t('Seat'))}]", $root))
            ) {
                $cabinTexts = $this->http->FindNodes("./descendant::text()[{$this->starts($this->t('Details'))}]/ancestor::*[count(.//text()[normalize-space()]) = 2 and descendant::text()[{$this->eq($this->t("Guest"))}]]/following-sibling::*/descendant-or-self::tr[count(*) = 2]/*[2]", $root);
                $cabinValues = array_values(array_unique(array_filter($cabinTexts)));
            }

            if (count($cabinValues) === 1) {
                $s->extra()
                    ->cabin($cabinValues[0]);
            }
        }
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

    private function normalizeDate(string $string)
    {
        if (preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/', $string, $matches)) { // 12/21/2017
            $month = $matches[1];
            $day = $matches[2];
            $year = $matches[3];
        } elseif (preg_match('/^(\d{4})\D+(\d{1,2})\D+(\d{1,2})\D+$/u', $string, $matches)) { // 2018年08月27日
            $year = $matches[1];
            $month = $matches[2];
            $day = $matches[3];
        }

        if (isset($day, $month, $year)) {
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

    private function normalizeTime(string $string): string
    {
        if (preg_match('/^(?:12)?\s*noon$/i', $string)) {
            return '12:00';
        }

        if (preg_match('/^((\d{1,2})[ ]*:[ ]*\d{2})\s*[AaPp][Mm]$/', $string, $m) && (int) $m[2] > 12) {
            $string = $m[1];
        } // 21:51 PM    ->    21:51
        $string = preg_replace('/^(0{1,2}[ ]*:[ ]*\d{2})\s*[AaPp][Mm]$/', '$1', $string); // 00:25 AM    ->    00:25
        $string = preg_replace('/(\d)[ ]*-[ ]*(\d)/', '$1:$2', $string); // 01-55 PM    ->    01:55 PM
        $string = str_replace(['午前', '午後'], ['AM', 'PM'], $string); // 10:36 午前    ->    10:36 AM

        return $string;
    }

    /**
     * Formatting over 3 steps:
     * 11 507.00  ->  11507.00
     * 2,790      ->  2790    |    4.100,00  ->  4100,00    |    1'619.40  ->  1619.40
     * 18800,00   ->  18800.00  |  2777,0    ->  2777.0.
     *
     * @param string|null $s Unformatted string with amount
     * @param string|null $decimals Symbols floating-point when non-standard decimals (example: 1,258.943)
     */
    private function normalizeAmount(?string $s, ?string $decimals = null): ?float
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

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function eq($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) { return 'normalize-space(' . $node . ')="' . $s . '"'; }, $field)) . ')';
    }

    private function starts($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) { return 'starts-with(normalize-space(' . $node . '),"' . $s . '")'; }, $field)) . ')';
    }

    private function contains($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) { return 'contains(normalize-space(' . $node . '),"' . $s . '")'; }, $field)) . ')';
    }

    private function opt($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return '';
        }

        return '(?:' . implode('|', array_map(function ($s) { return preg_quote($s, '/'); }, $field)) . ')';
    }

    private function htmlToText(?string $s, bool $brConvert = true): string
    {
        if (!is_string($s) || $s === '') {
            return '';
        }
        $s = str_replace("\r", '', $s);
        $s = preg_replace('/<!--.*?-->/s', '', $s); // comments

        if ($brConvert) {
            $s = preg_replace('/\s+/', ' ', $s);
            $s = preg_replace('/<[Bb][Rr]\b.*?\/?>/', "\n", $s); // only <br> tags
        }
        $s = preg_replace('/<[A-z][A-z\d:]*\b.*?\/?>/', '', $s); // opening tags
        $s = preg_replace('/<\/[A-z][A-z\d:]*\b[ ]*>/', '', $s); // closing tags
        $s = html_entity_decode($s);
        $s = str_replace(chr(194) . chr(160), ' ', $s); // NBSP to SPACE

        return trim($s);
    }
}
