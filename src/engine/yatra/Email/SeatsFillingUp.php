<?php

namespace AwardWallet\Engine\yatra\Email;

class SeatsFillingUp extends \TAccountChecker
{
    public $mailFiles = "yatra/it-9821084.eml";

    public $reSubject = [
        'en' => ['Booking Ref#'],
    ];

    public $lang = '';

    public $reBody = [
        'en' => ['Flight Details'],
    ];

    public static $dict = [
        'en' => [],
    ];

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        if ($this->assignLang() === false) {
            return false;
        }

        $its = $this->parseEmail();

        $a = explode('\\', __CLASS__);

        return [
            'parsedData' => ['Itineraries' => $its],
            'emailType'  => $a[count($a) - 1] . ucfirst($this->lang),
        ];
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $condition1 = $this->http->XPath->query('//node()[contains(.,"Yatra.com") or contains(.,"TeamYatra") or contains(normalize-space(.),"Yatra Online Private Limited") or contains(.,"@yatra.com")]')->length === 0;
        $condition2 = $this->http->XPath->query('//a[contains(@href,"//secure.yatra.com")]')->length === 0;

        if ($condition1 && $condition2) {
            return false;
        }

        return $this->assignLang();
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (self::detectEmailFromProvider($headers['from']) === false) {
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

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@yatra.com') !== false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
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

    private function parseEmail()
    {
        $it = ['Kind' => 'T', 'TripSegments' => []];
        $it['RecordLocator'] = $this->http->FindSingleNode("//text()[{$this->contains($this->t('reference no.'))}]/ancestor::*[1]", null, true, "#{$this->opt($this->t('reference no.'))}\s+([A-Z\d]{5,})#");
        $tot = $this->getTotalCurrency($this->http->FindSingleNode("//text()[{$this->eq($this->t('Pay Now'))}]/ancestor::table[1]/descendant::tr[1]"));

        if (!empty($tot['Total'])) {
            $it['TotalCharge'] = $tot['Total'];
            $it['Currency'] = $tot['Currency'];
        }
        $xpath = "//text()[{$this->starts($this->t('Depart:'))}]/ancestor::table[1]";
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $seg = [];

            $node = $this->http->FindSingleNode("./descendant::tr[1]/td[normalize-space(.)!=''][1]", $root);

            if (preg_match("#(.+?)\s*\(([A-Z]{3})\)\s+(.+?)\s*\(([A-Z]{3})\)#", $node, $m)) {
                $seg['DepName'] = $m[1];
                $seg['DepCode'] = $m[2];
                $seg['ArrName'] = $m[3];
                $seg['ArrCode'] = $m[4];
            }
            $node = $this->http->FindSingleNode("./descendant::tr[normalize-space(.)!=''][last()-1]", $root);
            $node = explode("|", $node);

            if (count($node) === 2) {
                $date = strtotime($this->normalizeDate($node[0]));
                $seg['AirlineName'] = $node[1];
                $node = $this->http->FindSingleNode("./descendant::tr[normalize-space(.)!=''][last()]", $root);

                if (preg_match("#{$this->opt($this->t('Depart'))}[\s:]+(\d+:\d+)\s+{$this->opt($this->t('Arrive'))}[\s:]+(\d+:\d+)\s+{$this->opt($this->t('Duration'))}[\s:]+(.+)#", $node, $m)) {
                    $seg['DepDate'] = strtotime($m[1], $date);
                    $seg['ArrDate'] = strtotime($m[2], $date);
                    $seg['Duration'] = $m[3];
                }
            }

            if (!empty($seg['AirlineName']) && !empty($seg['DepDate']) && !empty($seg['ArrDate'])) {
                $seg['FlightNumber'] = FLIGHT_NUMBER_UNKNOWN;
            }

            $it['TripSegments'][] = $seg;
        }

        return [$it];
    }

    private function normalizeDate($date)
    {
        $in = [
            '#^\s*\w+,\s+(\d+)\s+(\w+),?\s+(\d{4})\s*$#', // Sun, 5 Nov, 2017
        ];
        $out = [
            '$1 $2 $3',
        ];
        $str = $this->dateStringToEnglish(preg_replace($in, $out, $date));

        return $str;
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
        foreach ($this->reBody as $lang => $phrases) {
            foreach ($phrases as $phrase) {
                if ($this->http->XPath->query('//node()[contains(normalize-space(.),"' . $phrase . '")]')->length > 0) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function getTotalCurrency($node)
    {
        $node = str_replace("€", "EUR", $node);
        $node = str_replace("$", "USD", $node);
        $node = str_replace("£", "GBP", $node);
        $node = str_replace("Rs.", "INR", $node);
        $tot = '';
        $cur = '';

        if (preg_match("#(?<c>[A-Z]{3})\s*(?<t>\d[\.\d\,\s]*\d*)#", $node, $m) || preg_match("#(?<t>\d[\.\d\,\s]*\d*)\s*(?<c>[A-Z]{3})#", $node, $m) || preg_match("#(?<c>\-*?)(?<t>\d[\.\d\,\s]*\d*)#", $node, $m)) {
            $m['t'] = preg_replace('/\s+/', '', $m['t']);            // 11 507.00	->	11507.00
            $m['t'] = preg_replace('/[,.](\d{3})/', '$1', $m['t']);    // 2,790		->	2790		or	4.100,00	->	4100,00
            $m['t'] = preg_replace('/,(\d{2})$/', '.$1', $m['t']);    // 18800,00		->	18800.00
            $tot = $m['t'];
            $cur = $m['c'];
        }

        return ['Total' => $tot, 'Currency' => $cur];
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'normalize-space(.)="' . $s . '"'; }, $field)) . ')';
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'starts-with(normalize-space(.),"' . $s . '")'; }, $field)) . ')';
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'contains(normalize-space(.),"' . $s . '")'; }, $field)) . ')';
    }

    private function opt($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return '';
        }

        return '(?:' . implode('|', array_map(function ($s) { return preg_quote($s, '/'); }, $field)) . ')';
    }
}
