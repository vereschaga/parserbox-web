<?php

namespace AwardWallet\Engine\thalys\Email;

class Confirmation2017 extends \TAccountChecker
{
    public $mailFiles = "";

    public $reFrom = "izy.com";
    public $reBody = [
        'fr' => ['confirmation de votre réservation', 'Voici le détail de votre réservation ainsi que toutes les informations utiles au voyage'],
    ];
    public $reSubject = [
        'Votre confirmation de réservation',
    ];
    public $lang = '';
    public $pdf;
    public static $dict = [
        'fr' => [
        ],
    ];

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->AssignLang();

        $its = $this->parseEmail();

        return [
            'parsedData' => ['Itineraries' => $its],
            'emailType'  => end(explode('\\', __CLASS__)) . ucfirst($this->lang),
        ];
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//img[contains(@src,'izy.com')]")->length > 0) {
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
        $it['RecordLocator'] = $this->http->FindSingleNode("//text()[starts-with(normalize-space(.),'" . $this->t('numéro de réservation') . "')]", null, true, "#:\s*([\dA-Z]+)#");
        $it['Passengers'] = $this->http->FindNodes("//text()[normalize-space(.)='passagers']/following-sibling::*[normalize-space(.)]//text()[normalize-space(.) and not(contains(.,'adulte'))]");
        $tot = $this->getTotalCurrency($this->http->FindSingleNode("//text()[starts-with(normalize-space(.),'total')]", null, true, "#:\s*(.+)#"));

        if (!empty($tot['Total'])) {
            $it['TotalCharge'] = $tot['Total'];
            $it['Currency'] = $tot['Currency'];
        }
        $it['TripCategory'] = TRIP_CATEGORY_TRAIN;

        $xpath = "//text()[starts-with(normalize-space(.),'voyage du')]/ancestor::table[1]";
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $seg = [];
            $node = $this->http->FindNodes("./descendant::tr[1]//text()[string-length(normalize-space(.))>2]", $root);

            if (isset($node[0]) && preg_match("#voyage du\s+(.+)#", $node[0], $m)) {
                $date = strtotime($this->normalizeDate($m[1]));
            }

            if (isset($node[1]) && preg_match("#(.+?)\s+>\s+(.+)#", $node[1], $m)) {
                $seg['DepName'] = $m[1];
                $seg['ArrName'] = $m[2];
            }
            $seg['DepCode'] = TRIP_CODE_UNKNOWN;
            $seg['ArrCode'] = TRIP_CODE_UNKNOWN;
            $node = $this->http->FindSingleNode("./descendant::td[contains(.,'Heure de départ')]/following-sibling::td[1]", $root);

            if (isset($date)) {
                $seg['DepDate'] = strtotime($node, $date);
            }
            $node = $this->http->FindSingleNode('./descendant::td[contains(.,"Heure d\'arrivée")]/following-sibling::td[1]', $root);

            if (isset($date)) {
                $seg['ArrDate'] = strtotime($node, $date);
            }
            $seg['FlightNumber'] = $this->http->FindSingleNode("./descendant::td[contains(.,'Numéro de train')]/following-sibling::td[1]", $root);

            $node = $this->http->FindSingleNode("./following-sibling::table[1]/descendant::tr[contains(.,'prix')]/following-sibling::tr[1]/td[1]", $root);

            if (preg_match("#\d+\s+x\s+(.+)#", $node, $m)) {
                $seg['Cabin'] = $m[1];
            }
            $node = $this->http->FindSingleNode("./ancestor::tr[1]/following-sibling::tr[1]", $root, true, "#CN\s+SN \s*: \s*(.+)#");

            if (preg_match_all("#\d+\s+\d+\s+\-#", $node, $m, PREG_PATTERN_ORDER)) {
                $seg['Seats'] = '';

                foreach ($m[0] as $st) {
                    if (preg_match("#(\d+)\s+(\d+)#", $st, $v)) {
                        $seg['Seats'] .= ', ' . $v[1] . '-' . $v[2];
                    }
                }
                $seg['Seats'] = substr($seg['Seats'], 2);
            }

            $it['TripSegments'][] = $seg;
        }

        return [$it];
    }

    private function normalizeDate($date)
    {
        $in = [
            //29-05-2017
            '#^(\d+)-(\d+)-(\d+)$#',
        ];
        $out = [
            '$3-$2-$1',
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
        if (isset($this->reBody)) {
            $body = $this->http->Response['body'];

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
        $node = str_replace("€", "EUR", $node);
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
