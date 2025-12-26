<?php

namespace AwardWallet\Engine\maketrip\Email;

use AwardWallet\Engine\MonthTranslate;

class ETicketInvoice extends \TAccountChecker
{
    public $mailFiles = "maketrip/it-4807189.eml, maketrip/it-4830239.eml, maketrip/it-4868870.eml, maketrip/it-5089369.eml, maketrip/it-69648865.eml";

    public $reSubject = [
        'E-Ticket & Invoice for your',
        'E-Ticket for Booking',
        'ETicket for Car Booking ID',
    ];
    public $lang = 'en';

    public static $dict = [
        'en' => [
            // FLIGHT
            'ARRIVE' => 'ARRIVE',
            'DEPART' => 'DEPART',
            // TRANSFER
            'PICK UP DETAILS' => 'PICK UP DETAILS',
            'CAR TYPE'        => 'CAR TYPE',
        ],
    ];

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $body = $this->http->Response['body'];
        $this->assignLang($body);

        $result = [
            'emailType'  => 'ETicketInvoice' . ucfirst($this->lang),
            'parsedData' => ['Itineraries' => $this->parseEmail()],
        ];

        $totalPrice = $this->http->FindSingleNode("//tr[ *[1][{$this->eq($this->t('Total Booking Amount'))}] ]/*[2]");

        if (preg_match('/^(?<currency>[^\d)(]+?)[ ]*(?<amount>\d[,.\'\d ]*)$/', $totalPrice, $m)) {
            // ₹ 31731
            $result['parsedData']['TotalCharge']['Currency'] = $m['currency'];
            $result['parsedData']['TotalCharge']['Amount'] = $this->normalizeAmount($m['amount']);
        }

        return $result;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//a[contains(@href,'.makemytrip.com/')]")->length === 0) {
            return false;
        }
        $body = $parser->getHTMLBody();

        return $this->assignLang($body);
    }

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->detectEmailFromProvider($headers['from']) !== true && strpos($headers['subject'], 'MakeMyTrip') === false) {
            return false;
        }

        foreach ($this->reSubject as $re) {
            if (stripos($headers['subject'], $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@makemytrip.com') !== false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    private function parseEmail(): ?array
    {
        $its = [];

        $bookingId = $this->http->FindSingleNode("//tr[not(.//tr) and {$this->starts($this->t('MAKEMYTRIP BOOKING ID'))}]", null, true, "/^{$this->opt($this->t('MAKEMYTRIP BOOKING ID'))}[:\s]+([-A-Z\d]{5,})$/");

        $dateRes = $this->http->FindSingleNode("//tr[not(.//tr) and {$this->starts($this->t('BOOKING DATE'))}]", null, true, "/^{$this->opt($this->t('BOOKING DATE'))}[:\s]+(.{6,})$/");
        $dateRes = str_replace(",", " ", $dateRes);

        $flights = array_unique($this->http->FindNodes("//td[contains(normalize-space(.),'PNR -')]", null, "#\-\s*(\w+)#"));

        foreach ($flights as $recLoc) {
            $it = ['Kind' => 'T', 'TripSegments' => []];

            if ($bookingId) {
                $it['TripNumber'] = $bookingId;
            }

            $it['RecordLocator'] = trim($recLoc);

            $it['ReservationDate'] = strtotime($dateRes);

            $it['Passengers'] = array_unique($this->http->FindNodes("//text()[contains(.,'{$recLoc}') and contains(.,'PNR')]/ancestor::tr[1]/ancestor::table[2]/ancestor::tr[1]/following-sibling::tr[1]//tr[count(descendant::tr)=0 and contains(.,'PASSENGERS')]/following-sibling::tr/td[1]", null, "#\/\s*(.+)#"));

            $it['TicketNumbers'] = array_unique($this->http->FindNodes("//text()[contains(.,'{$recLoc}') and contains(.,'PNR')]/ancestor::tr[1]/ancestor::table[2]/ancestor::tr[1]/following-sibling::tr[1]//tr[count(descendant::tr)=0 and contains(.,'PASSENGERS')]/following-sibling::tr/td[2]"));
            $xpath = "//text()[contains(.,'{$recLoc}') and contains(.,'PNR')]/ancestor::tr[1]";
            $nodes = $this->http->XPath->query($xpath);

            if ($nodes->length === 0) {
                $this->logger->info('Segments not found by xpath: ' . $xpath);

                return null;
            }

            //#################################################
            $this->logger->info('Segments found by: ' . $xpath);
            //#################################################

            foreach ($nodes as $root) {
                $seg = [];

                $dateFly = $this->http->FindSingleNode(".//td[2]", $root);
                $dateFly = str_replace(",", " ", $dateFly);

                if (stripos($dateFly, 'date') !== false) {
                    if (preg_match("#Dept\s*Date\s*\:\s*(.+)\s*Arrival\s*Date\s*\:\s*(.+)#", $dateFly, $m)) {
                        $seg['DepDate'] = strtotime($this->normalizeDate($m[1] . ' ' . $this->http->FindSingleNode("./ancestor::tr[1]/following-sibling::tr[1]//td[contains(normalize-space(text()),'DEPART')]/following::td[1]", $root)));
                        $seg['ArrDate'] = strtotime($this->normalizeDate($m[2] . ' ' . $this->http->FindSingleNode("./ancestor::tr[1]/following-sibling::tr[1]//td[contains(normalize-space(text()),'ARRIVE')]/following::td[1]", $root)));
                    }
                } else {
                    $seg['DepDate'] = strtotime($this->normalizeDate($dateFly . ' ' . $this->http->FindSingleNode("./ancestor::tr[1]/following-sibling::tr[1]//td[contains(normalize-space(text()),'DEPART')]/following::td[1]", $root)));
                    $seg['ArrDate'] = strtotime($this->normalizeDate($dateFly . ' ' . $this->http->FindSingleNode("./ancestor::tr[1]/following-sibling::tr[1]//td[contains(normalize-space(text()),'ARRIVE')]/following::td[1]", $root)));
                }

                $temp = $this->http->FindSingleNode("./ancestor::tr[1]/following-sibling::tr[1]/td[1]", $root);

                if (isset($temp) && preg_match("#(?<DepName>.+?)\s*\-\s*(?<ArrName>.+?)\s*(?<AirlineName>[A-Z\d]{2})\s*\-\s*(?<FlightNumber>\d+)\s*(?<Operator>.+)#", $temp, $m)) {
                    $seg['Operator'] = $m['Operator'];
                    $seg['AirlineName'] = $m['AirlineName'];
                    $seg['FlightNumber'] = $m['FlightNumber'];
                    $seg['DepName'] = $m['DepName'];
                    $seg['ArrName'] = $m['ArrName'];
                    $node = $this->http->FindSingleNode("./preceding::img[contains(@alt,'one_way_arrow')][1]/ancestor::tr[2]/td[1]//tr[2]", $root);

                    if (mb_strtolower($seg['DepName']) === mb_strtolower($node)) {
                        $seg['DepCode'] = $this->http->FindSingleNode("./preceding::img[contains(@alt,'one_way_arrow')][1]/ancestor::tr[2]/td[1]//tr[2]/preceding::td[1]", $root);
                    }

                    $node = $this->http->FindSingleNode("./preceding::img[contains(@alt,'one_way_arrow')][1]/ancestor::tr[2]/td[3]//tr[2]", $root);

                    if (mb_strtolower($seg['ArrName']) === mb_strtolower($node)) {
                        $seg['ArrCode'] = $this->http->FindSingleNode("./preceding::img[contains(@alt,'one_way_arrow')][1]/ancestor::tr[2]/td[3]//tr[2]/preceding::td[1]", $root);
                    }
                }

                if (!empty($seg['DepDate']) && !empty($seg['ArrDate']) && !empty($seg['FlightNumber'])) {
                    $seg['DepCode'] = $seg['ArrCode'] = TRIP_CODE_UNKNOWN;
                }

                $seg['DepartureTerminal'] = $this->http->FindSingleNode("./ancestor::tr[2]/following-sibling::tr[2]//td[contains(.,'Terminal') and not(descendant::td)]", $root, true, '/terminal\s*([A-Z\d]{1,3})/i');

                $seg['Cabin'] = $this->http->FindSingleNode("./ancestor::tr[2]/following-sibling::tr[2]//td[contains(.,'Class') and not(descendant::td)]", $root, true, '/class\s*(\w+)/i');

                $it['TripSegments'][] = $seg;
            }
            $its[] = $it;
        }

        // it-69648865.eml
        $transfers = $this->http->XPath->query("//tr[ *[1][{$this->starts($this->t('PICK UP DETAILS'))}] and *[2][{$this->starts($this->t('CAR TYPE'))}] ]");

        foreach ($transfers as $rootTransfer) {
            $it = [];
            $it['Kind'] = 'T';
            $it['TripCategory'] = TRIP_CATEGORY_TRANSFER;

            if ($bookingId) {
                $it['TripNumber'] = $bookingId;
            }

            if ($dateRes) {
                $it['ReservationDate'] = strtotime($dateRes);
            }

            $it['RecordLocator'] = CONFNO_UNKNOWN;

            $it['TripSegments'] = [];
            $seg = [];

            $route = implode("\n", $this->http->FindNodes("preceding-sibling::tr[{$this->starts($this->t('JOURNEY'))}]/descendant::text()[normalize-space()]", $rootTransfer));

            if (preg_match("/^{$this->opt($this->t('JOURNEY'))}\n+(?<name1>.{3,})[ ]+{$this->opt($this->t('to'))}[ ]+(?<name2>.{3,})(?:\n|$)/i", $route, $m)) {
                $seg['DepName'] = $m['name1'];
                $seg['ArrName'] = $m['name2'];
            }

            $pickUp = implode("\n", $this->http->FindNodes('*[1]/descendant::text()[normalize-space()]', $rootTransfer));

            if (preg_match("/^{$this->opt($this->t('PICK UP DETAILS'))}\s+(?<location>.{3,}?)\s+{$this->opt($this->t('on'))}\s+(?<dateTime>.{6,}?)(?:\s*hrs)?$/i", $pickUp, $m)) {
                $seg['DepName'] = $m['location'];
                $seg['DepDate'] = strtotime($m['dateTime']);
                $seg['ArrDate'] = MISSING_DATE;
                $seg['DepCode'] = TRIP_CODE_UNKNOWN;
                $seg['ArrCode'] = TRIP_CODE_UNKNOWN;
            }

//            $carType = implode("\n", $this->http->FindNodes('*[2]/descendant::text()[normalize-space()]', $rootTransfer));
//            if ( preg_match("/^{$this->opt($this->t('CAR TYPE'))}\n+(?<type>.{2,}?)[ ]*-\n+(?<model>.+{$this->opt($this->t('or similar'))})(?:\n|$)/i", $carType, $m) ) {
//                $it['CarType'] = $m['type'];
//                $it['CarModel'] = $m['model'];
//            }

            $it['TripSegments'][] = $seg;

            $traveller = $this->http->FindSingleNode("following-sibling::tr[normalize-space()][1]/descendant-or-self::*[count(*[normalize-space()])>1 and *[1][{$this->eq($this->t('TRAVELLER DETAILS'))}]][1]/*[normalize-space()][2]", $rootTransfer, true, "/^[[:alpha:]][-.\'[:alpha:] ]*[[:alpha:]]$/u");
            $it['Passengers'] = [$traveller];

            $its[] = $it;
        }

        return $its;
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function assignLang($body): bool
    {
        foreach (self::$dict as $lang => $reBody) {
            if (stripos($body, $reBody['ARRIVE']) !== false && stripos($body, $reBody['DEPART']) !== false
                || stripos($body, $reBody['PICK UP DETAILS']) !== false && stripos($body, $reBody['CAR TYPE']) !== false
            ) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
    }

    private function normalizeDate($str)
    {
        $in = [
            "#^([^\d\s]+)\s+(\d+)\s+(\d{4})\s+(\d+:\d+\s+[AP]M)$#",
            "#^(\d+\s+[^\d\s]+\s+\d{4}),\s+00:(\d+\s+[AP]M)$#", //00:20 AM is not valid
        ];
        $out = [
            "$2 $1 $3, $4",
            "$1, 12:$2",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return $str;
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

    private function contains($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';

            return 'contains(normalize-space(' . $node . '),' . $s . ')';
        }, $field)) . ')';
    }

    private function eq($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';

            return 'normalize-space(' . $node . ')=' . $s;
        }, $field)) . ')';
    }

    private function starts($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';

            return 'starts-with(normalize-space(' . $node . '),' . $s . ')';
        }, $field)) . ')';
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
}
