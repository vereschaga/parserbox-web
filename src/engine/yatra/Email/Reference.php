<?php

namespace AwardWallet\Engine\yatra\Email;

class Reference extends \TAccountChecker
{
    public $mailFiles = "yatra/it-12424554.eml, yatra/it-28785380.eml";

    public $reFrom = "yatra.com";
    public $reSubject = [
        'Yatra Reference',
        'Yatra complete booking',
    ];
    public $reBody = [
        'en' => ['Depart City'],
    ];
    public $lang = '';
    public static $dict = [
        'en' => [
            'Yatra Reference' => ['Yatra Reference', 'Yatra ref no', 'Booking Ref. Number'],
            'Total Cost:'     => ['Total Cost:', 'Total Cost :'],
        ],
    ];

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $body = $this->http->Response['body'];
        $this->assignLang($body);

        $its = $this->parseEmail();

        return [
            'emailType'  => $a[count($a = explode('\\', __CLASS__)) - 1] . ucfirst($this->lang),
            'parsedData' => ['Itineraries' => $its],
        ];
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//a[contains(@href,'yatra.com')]")->length > 0
                && $this->http->XPath->query("//text()[normalize-space() = 'Depart City']/ancestor::tr[1][contains(normalize-space(.), 'Depart Time')]")->length > 0) {
            return true;
            //			$body = $parser->getHTMLBody();
//			return $this->AssignLang($body);
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        //		if (isset($headers['from']) && stripos($headers['from'], $this->reFrom) === false {
        //			return false;
        //		}
        foreach ($this->reSubject as $reSubject) {
            if (stripos($headers["subject"], $reSubject) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->reFrom) !== false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    private function parseEmail()
    {
        $it = ['Kind' => 'T'];

        // TripNumber
        $it['TripNumber'] = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Yatra Reference'))}]/following::text()[normalize-space(.)][1]", null, true, '/^([A-Z\d]{7,})$/');

        // RecordLocator
        $it['RecordLocator'] = CONFNO_UNKNOWN;

        $it['Passengers'] = $this->http->FindNodes("//text()[normalize-space() = 'Passenger']/ancestor::tr[1][normalize-space(./td[2]) = 'Name' or normalize-space(./th[2]) = 'Name']/following-sibling::tr[count(td[normalize-space()])>4]/td[2]");

        $xpath = "//text()[normalize-space() = 'Depart City']/ancestor::tr[1][normalize-space(./td[3]) = 'Depart Time']/following-sibling::tr[count(td)>1]";
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            if (preg_match("#^\s*[A-Z]{3}\s*[A-Z]{3}#", $root->nodeValue, $m) == false && preg_match("#^\d+:\d{2}:\d{2}#", $root->nodeValue, $m) == false) {
                continue;
            }
            $seg = [];

            $node = $this->http->FindSingleNode("./td[normalize-space()][last()]", $root);

            if (preg_match("/^(?:(?<airline>[A-Z][A-Z\d]|[A-Z\d][A-Z])-)?(?<flightNumber>\d+)\b/", $node, $m)) {
                $seg['AirlineName'] = empty($m['airline']) ? AIRLINE_UNKNOWN : $m['airline'];
                $seg['FlightNumber'] = $m['flightNumber'];
            }
            $seg['DepDate'] = strtotime($this->normalizeDate($this->http->FindSingleNode("./td[3]", $root)));
            $seg['ArrDate'] = strtotime($this->normalizeDate($this->http->FindSingleNode("./td[4]", $root)));

            $seg['DepCode'] = $this->http->FindSingleNode("./td[1]", $root);
            $seg['ArrCode'] = $this->http->FindSingleNode("./td[2]", $root);
            $seg['BookingClass'] = $this->http->FindSingleNode("./td[7]", $root, true, "#^\s*([A-Z]{1,2})\s*$#");
            $it['TripSegments'][] = $seg;
        }

        // Currency
        // TotalCharge
        $payment = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Total Cost:'))}]/ancestor::td[1]/following-sibling::*[normalize-space(.)][1]");

        if (preg_match('/^(?<currency>[A-Z]{3})\s*(?<amount>\d[,.\'\d]*)/', $payment, $matches)) {
            $it['Currency'] = $matches['currency'];
            $it['TotalCharge'] = $this->normalizeAmount($matches['amount']);
        }

        return [$it];
    }

    private function normalizeDate($date)
    {
        $in = [
            '#^\s*(\d{4})-(\d+)-(\d+)\s+(\d+:\d+):\d+\s*$#', //2018-01-08 04:00:00
        ];
        $out = [
            '$3.$2.$1 $4',
        ];
        $str = preg_replace($in, $out, $date);

        return $str;
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

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function assignLang($body)
    {
        if (isset($this->reBody)) {
            foreach ($this->reBody as $lang => $reBody) {
                foreach ($reBody as $re) {
                    if (stripos($body, $re) !== false) {
                        $this->lang = $lang;

                        return true;
                    }
                }
            }
        }

        return false;
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
}
