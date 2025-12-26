<?php

namespace AwardWallet\Engine\supershuttle\Email;

use AwardWallet\Engine\MonthTranslate;

class BookingConfirmation extends \TAccountChecker
{
    public $mailFiles = "supershuttle/it-11258124.eml";

    public $reFrom = "supershuttle.com";
    public $reBody = [
        'en' => ['Pickup Date & Time', 'Thank you for choosing SuperShuttle'],
    ];
    public $reSubject = [
        'SuperShuttle Booking Confirmation',
    ];
    public $lang = '';
    public static $dict = [
        'en' => [
        ],
    ];

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->AssignLang();

        $its = $this->parseEmail();
        $a = explode('\\', __CLASS__);

        $tot = $this->getTotalCurrency($this->http->FindSingleNode("//text()[{$this->eq($this->t('Total Charge'))}]/preceding::text()[string-length(normalize-space(.))>2][1]"));

        if (!empty($tot['Total'])) {
            return [
                'parsedData' => [
                    'Itineraries' => $its,
                    'TotalCharge' => [
                        'Amount'   => $tot['Total'],
                        'Currency' => $tot['Currency'],
                    ],
                ],
                'emailType' => $a[count($a) - 1] . ucfirst($this->lang),
            ];
        } else {
            return [
                'parsedData' => ['Itineraries' => $its],
                'emailType'  => $a[count($a) - 1] . ucfirst($this->lang),
            ];
        }
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//img[contains(@src,'supershuttle.com')] | //a[contains(@href,'supershuttle.com')]")->length > 0) {
            return $this->AssignLang();
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], $this->reFrom) !== false && isset($this->reSubject)) {
            foreach ($this->reSubject as $reSubject) {
                if (stripos($headers["subject"], $reSubject) !== false) {
                    return true;
                }
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

    private function parseEmail()
    {
        $its = [];
        $xpath = "//text()[{$this->eq($this->t('Pickup Date & Time:'))}]/ancestor::table[{$this->contains($this->t('Confirmation#'))}][1]";

        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $it = ['Kind' => 'T', 'TripSegments' => []];
            $it['TripCategory'] = TRIP_CATEGORY_TRANSFER;
            $it['RecordLocator'] = $this->http->FindSingleNode("./descendant::text()[{$this->starts($this->t('Confirmation#'))}]/following::text()[normalize-space(.)!=''][1]", $root, true, "#[A-Z\d]+#");

            $it['Passengers'][] = $this->http->FindSingleNode("./descendant::text()[{$this->starts($this->t('Passenger Name(s)'))}]/following::text()[string-length(normalize-space(.))>2][1]", $root);
            $tot = $this->getTotalCurrency($this->http->FindSingleNode("./descendant::text()[{$this->starts($this->t('Fare:'))}]/following::text()[string-length(normalize-space(.))>2][1]", $root));

            if (!empty($tot['Total'])) {
                $it['BaseFare'] = $tot['Total'];
                $it['Currency'] = $tot['Currency'];
            }
            $tot = $this->getTotalCurrency($this->http->FindSingleNode("./descendant::text()[{$this->starts($this->t('Gratuity:'))}]/following::text()[string-length(normalize-space(.))>2][1]", $root));

            if (!empty($tot['Total'])) {
                $it['Tax'] = $tot['Total'];
                $it['Currency'] = $tot['Currency'];
            }
            $tot = $this->getTotalCurrency($this->http->FindSingleNode("./descendant::text()[{$this->contains($this->t('Manage Trip'))}]/preceding::text()[string-length(normalize-space(.))>2][1]", $root));

            if (!empty($tot['Total'])) {
                $it['TotalCharge'] = $tot['Total'];
                $it['Currency'] = $tot['Currency'];
            }

            $seg = [];

            $node = $this->http->FindSingleNode("./descendant::text()[{$this->starts($this->t('Pickup Date & Time'))}]/following::text()[string-length(normalize-space(.))>2][1]", $root);
            $seg['DepDate'] = $this->normalizeDate($node);
            $seg['ArrDate'] = MISSING_DATE;

            $seg['DepCode'] = TRIP_CODE_UNKNOWN;
            $seg['ArrCode'] = TRIP_CODE_UNKNOWN;

            $seg['DepName'] = $this->http->FindSingleNode("./descendant::img[1]/following::text()[string-length(normalize-space(.))>2][1]", $root);
            $seg['ArrName'] = $this->http->FindSingleNode("./descendant::img[2]/following::text()[string-length(normalize-space(.))>2][1]", $root);

            if (preg_match("#^[A-Z]{3}$#", $seg['DepName'])) {
                $seg['DepCode'] = $seg['DepName'];
            }

            if (preg_match("#^[A-Z]{3}$#", $seg['ArrName'])) {
                $seg['ArrCode'] = $seg['ArrName'];
            }

            $it['TripSegments'][] = $seg;
            $its[] = $it;
        }

        return $its;
    }

    private function normalizeDate($date)
    {
        $in = [
            //			Saturday, February 10, 2018 02:50PM
            //			Friday, February 16, 2018 11:10AM - 11:25AM
            '#^\s*\w+,\s+(\w+)\s+(\d+),\s+(\d{4})\s+(\d+:\d+(?:\s*[AP]M)?).*?\s*$#i',
        ];
        $out = [
            '$2 $1 $3, $4',
        ];
        $str = $this->dateStringToEnglish(preg_replace($in, $out, $date));

        return strtotime($str);
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function AssignLang()
    {
        if (isset($this->reBody)) {
            foreach ($this->reBody as $lang => $reBody) {
                if ($this->http->XPath->query("//*[contains(normalize-space(.),'{$reBody[0]}')]")->length > 0
                    && $this->http->XPath->query("//*[contains(normalize-space(.),'{$reBody[1]}')]")->length > 0
                ) {
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
        $tot = '';
        $cur = '';

        if (preg_match("#(?<c>[A-Z]{3})\s*(?<t>\d[\.\d\,\s]*\d*)#", $node, $m) || preg_match("#(?<t>\d[\.\d\,\s]*\d*)\s*(?<c>[A-Z]{3})#", $node, $m) || preg_match("#(?<c>\-*?)(?<t>\d[\.\d\,\s]*\d*)#", $node, $m)) {
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
}
