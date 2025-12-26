<?php

namespace AwardWallet\Engine\virgin\Email;

class Reservation extends \TAccountChecker
{
    public $mailFiles = "virgin/it-6398593.eml";

    public $reFrom = "virginamerica.com";
    public $reBody = [
        'en' => ["your flight itinerary. Please retain this confirmation", 'Virgin America Reservation'],
    ];
    public $reSubject = [
        'Virgin America Reservation',
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

        return [
            'parsedData' => ['Itineraries' => $its],
            'emailType'  => 'Reservation' . ucfirst($this->lang),
        ];
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//img[contains(@alt,'virginamerica.com') or contains(@src,'virginamerica.com')]")->length > 0) {
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

            if ($translatedMonthName = \AwardWallet\Engine\MonthTranslate::translate($monthNameOriginal, $this->lang)) {
                return preg_replace("#$monthNameOriginal#i", $translatedMonthName, $date);
            }
        }

        return $date;
    }

    private function parseEmail()
    {
        $it = ['Kind' => 'T', 'TripSegments' => []];
        $it['RecordLocator'] = $this->http->FindSingleNode("//text()[normalize-space(.)='Your Confirmation Code:']/following::text()[string-length(normalize-space(.))>3][1]", null, true, "#[A-Z\d]+#");
        $it['Passengers'] = $this->http->FindNodes("//text()[starts-with(normalize-space(.),'Traveler')]/ancestor::td[1]", null, "#\d+\s*:\s*(.+)#");
        $tot = $this->getTotalCurrency($this->http->FindSingleNode("//text()[starts-with(normalize-space(.),'TOTAL')]/ancestor::td[1]/following-sibling::td[1]"));

        if (!empty($tot['Total'])) {
            $it['TotalCharge'] = $tot['Total'];
            $it['Currency'] = $tot['Currency'];
        }
        $xpath = "//text()[normalize-space(.)='Depart:']";
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $seg = [];
            $date = strtotime($this->normalizeDate($this->getText("Date:", $root, false)));

            $node = $this->getText("Flight:", $root, false);

            if (preg_match("#([A-Z\d]{2})\s*(\d+)#", $node, $m)) {
                $seg['AirlineName'] = $m[1];
                $seg['FlightNumber'] = $m[2];
            }
            $seg['DepDate'] = strtotime($this->http->FindSingleNode("./ancestor::td[1]/following-sibling::td[1]", $root), $date);
            $seg['ArrDate'] = strtotime($this->getText("Arrive:", $root), $date);
            $seg['Stops'] = $this->getText("Stops:", $root);

            $node = $this->http->FindSingleNode("./preceding::text()[string-length(normalize-space(.))>2][position()<10][contains(.,'(') and contains(.,')') and contains(.,' to ')]", $root);
            //San Francisco CA (SFO) to Las Vegas NV (LAS)
            if (preg_match("#(.+?)\s+\(([A-Z]{3})\)\s+to\s+(.+?)\s+\(([A-Z]{3})\)#", $node, $m)) {
                $seg['DepName'] = $m[1];
                $seg['DepCode'] = $m[2];
                $seg['ArrName'] = $m[3];
                $seg['ArrCode'] = $m[4];
            }

            $it['TripSegments'][] = $seg;
        }

        return [$it];
    }

    private function getText($field, $root = null, $after = true)
    {
        if ($after) {
            return $this->http->FindSingleNode("./following::text()[string-length(normalize-space(.))>2][position()<10][normalize-space(.)='{$field}']/ancestor::td[1]/following-sibling::td[1]", $root);
        }

        return $this->http->FindSingleNode("./preceding::text()[string-length(normalize-space(.))>2][position()<10][normalize-space(.)='{$field}']/ancestor::td[1]/following-sibling::td[1]", $root);
    }

    private function normalizeDate($date)
    {
        $in = [
            '#^\s*(\d+)\s*(\D+)\s*(\d+)\s*$#',
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

    private function AssignLang()
    {
        $body = $this->http->Response['body'];

        if (isset($this->reBody)) {
            foreach ($this->reBody as $lang => $reBody) {
                if (stripos($body, $reBody[0]) !== false && stripos($body, $reBody[1]) !== false) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function getTotalCurrency($node)
    {
        $node = str_replace("$", "USD", $node);
        $tot = '';
        $cur = '';

        if (preg_match("#(?<c>[A-Z]{3})\s*(?<t>\d[\.\d\,\s]*\d*)#", $node, $m) || preg_match("#(?<t>\d[\.\d\,\s]*\d*)\s*(?<c>[A-Z]{3})#", $node, $m)) {
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
}
