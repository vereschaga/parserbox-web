<?php

namespace AwardWallet\Engine\hkexpress\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class Itinerary extends \TAccountChecker
{
    public $mailFiles = "hkexpress/it-30231998.eml, hkexpress/it-47402936.eml, hkexpress/it-818517043-zh.eml";

    private $detectCompany = [
        'Hong Kong Express', 'HK Express',
    ];

    private $lang = '';
    private static $dictionary = [
        'zh' => [
            'Passenger details' => '旅客資料',
            'confNumber' => '訂單編號/ PNR:',
            'Depart' => ['出發', '去程'],
            'Return' => '回程',
            'reward-U member:' => 'reward-U 會員賬號:',
            'Payment details' => '付款詳情',
            'Total:' => '總計:',
            'Flight Depart Arrive' => '航班 出發 回程',
            'Seat' => '座位',
            'Terminal' => '客運大樓',
        ],
        'en' => [
            'Passenger details' => 'Passenger details',
            'confNumber' => 'Booking Reference/ PNR:',
            // 'Depart' => '',
            // 'Return' => '',
            // 'reward-U member:' => '',
            // 'Payment details' => '',
            // 'Total:' => '',
            // 'Flight Depart Arrive' => '',
            // 'Seat' => '',
            // 'Terminal' => '',
        ],
    ];

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();
        if ( empty($this->lang) ) {
            $this->logger->debug("Can't determine a language!");
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        $this->flight($email);

        return $email;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, 'hkexpress.com') !== false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $body = $this->http->Response['body'];

        $finded = false;

        foreach ($this->detectCompany as $dCompany) {
            if (stripos($body, $dCompany) !== false) {
                $finded = true;
            }
        }

        if ($finded == false) {
            return false;
        }

        return $this->assignLang();
    }

    public function detectEmailByHeaders(array $headers)
    {
        return stripos($headers['subject'], 'Your HK Express Itinerary - ') !== false
            || stripos($headers['subject'], '您的HK Express訂單 - ') !== false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
    }

    private function flight(Email $email): void
    {
        $patterns = [
            'time' => '\d{1,2}[:：]\d{2}(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?)?', // 4:19PM  |  2:00 p. m.
            'travellerName' => '[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]]', // Mr. Hao-Li Huang
        ];

        $f = $email->add()->flight();

        $xpathPassengers = "//tr[{$this->eq($this->t("Passenger details"))}]/following-sibling::tr[normalize-space()][1]";

        // General
        $travellers = array_map(function ($item) {
            return $this->normalizeTraveller($item);
        }, array_filter($this->http->FindNodes($xpathPassengers . "/descendant::tr[ descendant::text()[normalize-space()][1][{$this->eq($this->t('Depart'))} or {$this->eq($this->t('Return'))}] ]/preceding-sibling::tr[normalize-space()][last()]", null, "/^{$patterns['travellerName']}$/u")));

        $f->general()->travellers($travellers, true);

        $confirmation = $this->http->FindSingleNode("//text()[{$this->starts($this->t('confNumber'))}]/following::text()[normalize-space()][1]", null, true, '/^[A-Z\d]{5,}$/');

        if ($confirmation) {
            $confirmationTitle = $this->http->FindSingleNode("//text()[{$this->starts($this->t('confNumber'))}]", null, true, '/^(.+?)[\s:：]*$/u');
            $f->general()->confirmation($confirmation, $confirmationTitle);
        }

        // Program
        $accountNodes = $this->http->XPath->query("//text()[{$this->starts($this->t('reward-U member:'))}]");

        foreach ($accountNodes as $accNode) {
            $passengerName = $this->normalizeTraveller($this->http->FindSingleNode("ancestor::tr[ preceding-sibling::tr[normalize-space()] ][1]/preceding-sibling::tr[normalize-space()]", $accNode, true, "/^{$patterns['travellerName']}$/u"));

            if ($passengerName && !in_array($passengerName, $travellers)) {
                $passengerName = null;
            }

            if (preg_match("/^\s*({$this->opt($this->t('reward-U member:'))})[:\s]*(\d{5,})\s*$/", $this->http->FindSingleNode('.', $accNode), $m)) {
                $f->program()->account($m[2], false, $passengerName, trim($m[1], ': '));
            }
        }

        // Price
        $xpathPayment = "//tr[{$this->eq($this->t('Payment details'))}]/following-sibling::tr[normalize-space()][1]";
        $totalPrice = $this->http->FindSingleNode($xpathPayment . "/descendant::tr[{$this->starts($this->t('Total:'))}]", null, true, "/{$this->opt($this->t('Total:'))}\s*(.+)/");

        if (preg_match('/^(?<currency>[A-Z]{3})[ ]*(?<amount>\d[,.\'\d]*)$/', $totalPrice, $m)) {
            // JPY 62,120.00
            $currencyCode = preg_match('/^[A-Z]{3}$/', $m['currency']) ? $m['currency'] : null;
            $f->price()->currency($m['currency'])->total(PriceHelper::parse($m['amount'], $currencyCode));

            $feeRows = $this->http->XPath->query($xpathPayment . "/descendant::tr[{$this->starts($this->t('Total:'))}][1]/preceding-sibling::tr[normalize-space()][1]/descendant::tr[not(.//tr) and count(*[normalize-space()])=2]");

            foreach ($feeRows as $feeRow) {
                $feeCharge = $this->http->FindSingleNode('*[2]', $feeRow);

                if (preg_match('/^(?:' . preg_quote($m['currency'], '/') . ')?[ ]*(?<amount>\d[,.\'\d]*)$/', $feeCharge, $matches)) {
                    $feeName = $this->http->FindSingleNode('*[1]', $feeRow);

                    if (preg_match("/^\s*{$this->opt($this->t('Fare'))}\s*$/i", $feeName)) {
                        $f->price()->cost(PriceHelper::parse($matches['amount'], $currencyCode));
                    } else {
                        $f->price()->fee($feeName, PriceHelper::parse($matches['amount'], $currencyCode));
                    }
                }
            }
        }

        // Segments
        $xpathSegments = "descendant::tr[{$this->eq($this->t('Flight Depart Arrive'))}][1]/following-sibling::tr[normalize-space()][1]/descendant::tr[ count(*)=3 and *[1][normalize-space() and not(descendant::img)] and *[2][normalize-space() and descendant::img] and *[3][normalize-space() and not(descendant::img)] ]";
        $segments = $this->http->XPath->query($xpathSegments);

        if ($segments->length === 0) {
            $segments = $this->http->XPath->query("//text()[normalize-space()='Flight']/ancestor::tr[2][contains(normalize-space(), 'Depart')]/following-sibling::tr[normalize-space()][1]/descendant::tr[2]");
        }

        foreach ($segments as $root) {
            $s = $f->addSegment();

            // Airline
            $flight = $this->http->FindSingleNode("td[normalize-space()][1]", $root);

            if (preg_match("#^\s*([A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(\d{1,5})\s*$#", $flight, $m)) {
                $s->airline()
                    ->name($m[1])
                    ->number($m[2])
                ;

                $rule = $this->eq([$s->getAirlineName() . ' ' . $s->getFlightNumber(), $s->getAirlineName() . $s->getFlightNumber()]);
                $seats = array_filter($this->http->FindNodes($xpathPassengers . "/descendant::text()[{$rule}]/ancestor::td[1]/following-sibling::td[normalize-space()][1]", null, "/^\s*{$this->opt($this->t('Seat'))}[:\s]*(\d{1,5}[A-Z])\s*$/"));

                if (count($seats)) {
                    $s->extra()->seats($seats);
                }
            }

            $reRoute = "/^\s*(?<date>[\s\S]+?)(?<time>{$patterns['time']}).*\n(?<name>[\s\S]+?)\s*(?:{$this->opt($this->t('Terminal'))}\s+(?<terminal>\w+))?\s*$/i";
            // Departure
            $node = implode("\n", $this->http->FindNodes("td[normalize-space()][2]//text()[normalize-space()]", $root));

            if (preg_match($reRoute, $node, $m)) {
                $s->departure()
                    ->noCode()
                    ->name($m['name'])
                    ->date(strtotime($m['time'], strtotime($this->normalizeDate($m['date']))))
                    ->terminal(empty($m['terminal']) ? null : $m['terminal'], false, true)
                ;
            }

            // Arrival
            $node = implode("\n", $this->http->FindNodes("td[normalize-space()][3]//text()[normalize-space()]", $root));

            if (preg_match($reRoute, $node, $m)) {
                $s->arrival()
                    ->noCode()
                    ->name($m['name'])
                    ->date(strtotime($m['time'], strtotime($this->normalizeDate($m['date']))))
                    ->terminal(empty($m['terminal']) ? null : $m['terminal'], false, true)
                ;
            }
        }
    }

    private function assignLang(): bool
    {
        if ( !isset(self::$dictionary, $this->lang) ) {
            return false;
        }
        foreach (self::$dictionary as $lang => $phrases) {
            if ( !is_string($lang) || empty($phrases['Passenger details']) ) {
                continue;
            }
            if ($this->http->XPath->query("//*[{$this->contains($phrases['Passenger details'])}]")->length > 0) {
                $this->lang = $lang;
                return true;
            }
        }
        return false;
    }

    private function t(string $phrase, string $lang = '')
    {
        if ( !isset(self::$dictionary, $this->lang) ) {
            return $phrase;
        }
        if ($lang === '') {
            $lang = $this->lang;
        }
        if ( empty(self::$dictionary[$lang][$phrase]) ) {
            return $phrase;
        }
        return self::$dictionary[$lang][$phrase];
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'normalize-space(.)="' . $s . '"'; }, $field)) . ')';
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'starts-with(normalize-space(.),"' . $s . '")'; }, $field)) . ')';
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'contains(normalize-space(.),"' . $s . '")'; }, $field)) . ')';
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

    /**
     * Dependencies `use AwardWallet\Engine\MonthTranslate` and `$this->lang`
     * @param string|null $text Unformatted string with date
     * @return string|null
     */
    private function normalizeDate(?string $text): ?string
    {
        if ( preg_match('/\b(\d{1,2})[,.\s]+(?:de\s+)?([[:alpha:]]+)[,.\s]+(?:de\s+)?(\d{4})$/u', $text, $m) ) {
            // Sat, 16 Feb, 2019  |  週二, 29 五月 2018  |  Segunda-feira 19 de agosto de 2019
            $day = $m[1];
            $month = $m[2];
            $year = $m[3];
        }
        if ( isset($day, $month, $year) ) {
            if ( preg_match('/^\s*(\d{1,2})\s*$/', $month, $m) )
                return $m[1] . '/' . $day . ($year ? '/' . $year : '');
            if ( ($monthNew = MonthTranslate::translate($month, $this->lang)) !== false )
                $month = $monthNew;
            return $day . ' ' . $month . ($year ? ' ' . $year : '');
        }
        return null;
    }

    private function normalizeTraveller(?string $s): ?string
    {
        if (empty($s)) {
            return null;
        }

        $namePrefixes = '(?:MASTER|MSTR|MISS|MRS|MR|MS|DR|先生|小姐|女士)';

        return preg_replace([
            "/^(?:{$namePrefixes}[.\s]+)+(.{2,})$/is",
        ], [
            '$1',
        ], $s);
    }
}
