<?php

namespace AwardWallet\Engine\nexus\Email;

use AwardWallet\Engine\MonthTranslate;

class Itinerary extends \TAccountChecker
{
    public $mailFiles = "nexus/it-11801293.eml, nexus/it-11817267.eml, nexus/it-11832033.eml";

    public $reFrom = "@nexuselite.in";
    public $reBody = [
        'en' => ['Airline PNR', 'Ticket Status'],
    ];
    public $reSubject = [
        '#TICKET COPY#i',
        '#Your Ticket\([A-Z\d]+\) confirmation#',
        '#cancel & refund#i',
        '#REFUND#',
        '#REISSUE#',
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

        return [
            'parsedData' => ['Itineraries' => $its],
            'emailType'  => $a[count($a) - 1] . ucfirst($this->lang),
        ];
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//text()[contains(normalize-space(.),'Nexus Elite Lifestyle Pvt Ltd')] | //a[contains(@href,'nexuselite.in')]")->length > 0) {
            return $this->AssignLang();
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], $this->reFrom) !== false && isset($this->reSubject)) {
            foreach ($this->reSubject as $reSubject) {
                if (preg_match($reSubject, $headers["subject"])) {
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
        $mainRoots = $this->http->XPath->query("//text()[normalize-space(.)='Airline PNR']/ancestor::table[2][contains(.,'Passenger Name')]");

        if ($mainRoots->length === 0) {
            $mainRoots = $this->http->XPath->query(".");
        }

        foreach ($mainRoots as $mainRoot) {
            /** @var \AwardWallet\ItineraryArrays\AirTrip $it */
            $it = ['Kind' => 'T', 'TripSegments' => []];
            $it['RecordLocator'] = $this->http->FindSingleNode(".//text()[{$this->eq($this->t('Airline PNR'))}]/following::text()[normalize-space(.)!=''][1]",
                $mainRoot, true, "#([A-Z\d]{5,})#");
            $it['TripNumber'] = $this->http->FindSingleNode(".//text()[{$this->eq($this->t('R PNR'))}]/following::text()[normalize-space(.)!=''][1]",
                $mainRoot, true, "#([A-Z\d]{5,})#");
            $it['Status'] = $this->http->FindSingleNode(".//text()[{$this->starts($this->t('Ticket Status'))}]/following::text()[normalize-space(.)!=''][1]",
                $mainRoot);
            $it['ReservationDate'] = $this->normalizeDate($this->http->FindSingleNode(".//text()[{$this->starts($this->t('Issued on'))}]",
                $mainRoot, true, "#{$this->opt($this->t('Issued on'))}[\s:]+(.+)#"));

            $tot = $this->getTotalCurrency($this->http->FindSingleNode(".//text()[{$this->starts($this->t('Basic Fare'))}]/ancestor::td[1]/following-sibling::td[1]",
                $mainRoot));

            if (!empty($tot['Total'])) {
                $it['BaseFare'] = $tot['Total'];
                $it['Currency'] = $tot['Currency'];
            }
            $tot = $this->getTotalCurrency($this->http->FindSingleNode(".//text()[{$this->starts($this->t('Taxes & Other Charges'))}]/ancestor::td[1]/following-sibling::td[1]",
                $mainRoot));

            if (!empty($tot['Total'])) {
                $it['Tax'] = $tot['Total'];
                $it['Currency'] = $tot['Currency'];
            }
            $tot = $this->getTotalCurrency($this->http->FindSingleNode(".//text()[starts-with(normalize-space(.),'Gross Fare')]/ancestor::td[1]/following-sibling::td[1]",
                $mainRoot));

            if (!empty($tot['Total'])) {
                $it['TotalCharge'] = $tot['Total'];
                $it['Currency'] = $tot['Currency'];
            }

            $rootPax = $this->http->XPath->query(".//text()[{$this->eq($this->t('Passenger Name'))}]/ancestor::tr[{$this->contains($this->t('Type'))}]/following-sibling::tr",
                $mainRoot);

            $seats = [];

            if ($rootPax->length > 0) {
                if (null !== ($pos = $this->findPosField($this->t('Passenger Name'), $rootPax->item(0)))) {
                    foreach ($rootPax as $root) {
                        $it['Passengers'][] = $this->http->FindSingleNode("./td[{$pos}]", $root);
                    }
                }

                if (null !== ($pos = $this->findPosField($this->t('Ticket No.'), $rootPax->item(0)))) {
                    $it['TicketNumbers'] = [];

                    foreach ($rootPax as $root) {
                        $node = $this->http->FindSingleNode("./td[{$pos}]", $root);

                        if (strpos($node, $it['TripNumber']) === false) {
                            $it['TicketNumbers'][] = $node;
                        }
                    }
                    $it['TicketNumbers'] = array_values(array_filter(array_unique($it['TicketNumbers'])));
                }

                if (null !== ($pos = $this->findPosField($this->t('Frequent Flyer'), $rootPax->item(0)))) {
                    $it['AccountNumbers'] = [];

                    foreach ($rootPax as $root) {
                        $it['AccountNumbers'][] = trim($this->http->FindSingleNode("./td[{$pos}]", $root), " -");
                    }
                    $it['AccountNumbers'] = array_values(array_filter(array_unique($it['AccountNumbers'])));
                }

                if (null !== ($pos = $this->findPosField($this->t('Seat No.'), $rootPax->item(0)))) {
                    foreach ($rootPax as $root) {
                        $seats[] = $this->http->FindSingleNode("./td[{$pos}]", $root);
                    }
                }
            }

            $orderSeats = [];

            foreach ($seats as $seat) {
                $arr = array_map("trim", explode('/', $seat));

                foreach ($arr as $i => $s) {
                    if (preg_match("#^\d+\w$#", $s)) {
                        $orderSeats[$i][] = $s;
                    }
                }
            }

            $xpath = ".//text()[{$this->eq($this->t('Origin'))}]/ancestor::tr[{$this->contains($this->t('Destination'))}]/following-sibling::tr";
            $nodes = $this->http->XPath->query($xpath, $mainRoot);

            foreach ($nodes as $i => $root) {
                /** @var \AwardWallet\ItineraryArrays\AirTripSegment $seg */
                $seg = [];

                if ($nodes->length === count($orderSeats)) {
                    $seg['Seats'] = $orderSeats[$i];
                }

                if (null !== ($pos = $this->findPosField($this->t('Origin'), $root))) {
                    $seg['DepName'] = $this->http->FindSingleNode("./td[{$pos}]", $root);
                }

                if (null !== ($pos = $this->findPosField($this->t('Destination'), $root))) {
                    $seg['ArrName'] = $this->http->FindSingleNode("./td[{$pos}]", $root);
                }

                if (null !== ($pos = $this->findPosField($this->t('Class'), $root))) {
                    $seg['Cabin'] = $this->http->FindSingleNode("./td[{$pos}]", $root);
                }

                if (null !== ($pos = $this->findPosField($this->t('Fl.No.'), $root))) {
                    $node = $this->http->FindSingleNode("./td[{$pos}]", $root);

                    if (preg_match("#([A-Z\d]{2})\s*(\d+)#", $node, $m)) {
                        $seg['AirlineName'] = $m[1];
                        $seg['FlightNumber'] = $m[2];
                    }
                }

                if (null !== ($pos = $this->findPosField($this->t('Dep.Terminal'), $root))) {
                    $node = $this->http->FindSingleNode("./td[{$pos}]", $root);

                    if ($node !== '-') {
                        $seg['DepartureTerminal'] = $node;
                    }
                }

                if (null !== ($pos = $this->findPosField($this->t('Arr.Terminal'), $root))) {
                    $node = $this->http->FindSingleNode("./td[{$pos}]", $root);

                    if ($node !== '-') {
                        $seg['DepartureTerminal'] = $node;
                    }
                }

                if (null !== ($pos = $this->findPosField($this->t('Dep.Date&Time'), $root))) {
                    $seg['DepDate'] = $this->normalizeDate($this->http->FindSingleNode("./td[{$pos}]", $root));
                }

                if (null !== ($pos = $this->findPosField($this->t('Arr.Date&Time'), $root))) {
                    $seg['ArrDate'] = $this->normalizeDate($this->http->FindSingleNode("./td[{$pos}]", $root));
                }
                $seg['DepCode'] = TRIP_CODE_UNKNOWN;
                $seg['ArrCode'] = TRIP_CODE_UNKNOWN;

                $it['TripSegments'][] = $seg;
            }
            $its[] = $it;
        }

        return $its;
    }

    private function findPosField($field, $root = null)
    {
        if ($this->http->XPath->query("./preceding-sibling::tr[last()]/td[{$this->eq($field)}]", $root)->length > 0) {
            return $this->http->XPath->query("./preceding-sibling::tr[last()]/td[{$this->eq($field)}]/preceding-sibling::td",
                    $root)->length + 1;
        }

        return null;
    }

    private function normalizeDate($date)
    {
        $in = [
            //13-03-2018 14:15 , Tue
            '#^\s*(\d+)\-(\d+)\-(\d{4})\s+(\d+:\d+(?:\s*[ap]m)?)\s*,\s*\w+\s*$#',
            //12/03/2018 10:52:32
            '#^\s*(\d+)\/(\d+)\/(\d{4})\s+(\d+:\d+.*)\s*$#',
        ];
        $out = [
            '$3-$2-$1, $4',
            '$3-$2-$1, $4',
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

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
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

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return "(?:" . preg_quote($s) . ")";
        }, $field)) . ')';
    }
}
