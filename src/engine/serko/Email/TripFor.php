<?php

namespace AwardWallet\Engine\serko\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Engine\WeekTranslate;

class TripFor extends \TAccountChecker
{
    public $mailFiles = "serko/it-8916930.eml, serko/it-8916931.eml, serko/it-8916933.eml, serko/it-8916977.eml, serko/it-9005167.eml, serko/it-9005168.eml";

    public $reFrom = ["@serko.travel", "@serko.com"];
    public $reBody = [
        'en' => ['This trip has been created', 'This trip has been approved'],
    ];
    public $reSubject = [
        'Serko Confirmation Email',
        'Trip for',
    ];
    public $lang = '';
    public $date;
    public static $dict = [
        'en' => [
        ],
    ];

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->date = strtotime($parser->getDate());
        $its = [];

        if ($this->assignLang()) {
            $flights = $this->parseFlights();

            foreach ($flights as $flight) {
                $its[] = $flight;
            }
            $hotels = $this->parseHotels();

            foreach ($hotels as $hotel) {
                $its[] = $hotel;
            }
            $cars = $this->parseCars();

            foreach ($cars as $car) {
                $its[] = $car;
            }
        }
        $result = [
            'parsedData' => ['Itineraries' => $its],
            'emailType'  => 'TripFor' . ucfirst($this->lang),
        ];
        $tot = $this->getTotalCurrency($this->http->FindSingleNode("//text()[{$this->eq($this->t('TOTAL'))}]/following::text()[normalize-space(.)!=''][1]"));

        if (!empty($tot['Total'])) {
            $result['parsedData']['TotalCharge'] = [
                'Amount' => $tot['Total'], 'Currency' => $tot['Currency'],
            ];
        }

        return $result;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//img[@alt='Automated email from Serko Online'] | //a[contains(@href,'serko.travel')]")->length > 0) {
            return $this->assignLang();
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && $this->detectEmailFromProvider($headers['from']) && isset($this->reSubject)) {
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
        foreach ($this->reFrom as $reFrom) {
            if (stripos($from, $reFrom) !== false) {
                return true;
            }
        }

        return false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        $types = 3;
        $cnt = $types * count(self::$dict);

        return $cnt;
    }

    private function parseFlights()
    {
        $its = [];
        $airs = [];
        $pax = $this->http->FindNodes("//text()[{$this->eq($this->t('Traveller:'))}]/following::text()[normalize-space(.)!=''][1]");
        $tripNum = $this->http->FindSingleNode("//text()[{$this->eq($this->t('PNR:'))}]/following::text()[normalize-space(.)!=''][1]");
        $status = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Status:'))}]/following::text()[normalize-space(.)!=''][1]");
        $nodes = $this->http->XPath->query("//text()[{$this->eq($this->t('Departure:'))}]/ancestor::table[1]");

        foreach ($nodes as $root) {
            $rl = $this->http->FindSingleNode("./preceding::table[normalize-space(.)!=''][1]/descendant::text()[{$this->starts($this->t('Ref:'))}]", $root, null, "#[\s:]+([A-Z\d]{5,})#");

            if (!empty($rl)) {
                $airs[$rl][] = $root;
            } else {
                $airs[CONFNO_UNKNOWN][] = $root;
            }
        }

        foreach ($airs as $rl => $roots) {
            $it = ["Kind" => "T", "TripSegments" => []];
            $it['RecordLocator'] = $rl;
            $it['TripNumber'] = $tripNum;
            $it['Passengers'] = $pax;
            $it['Status'] = $status;
            $totalSum = 0.0;
            $totalCur = '';
            $oneCurrency = true;

            foreach ($roots as $root) {
                $seg = [];
                $node = $this->http->FindSingleNode("./preceding::table[normalize-space(.)!=''][1]/descendant::text()[normalize-space(.)!=''][1]", $root);

                if (preg_match("#([A-Z\d]{2})\s*(\d+)$#", $node, $m)) {
                    $seg['AirlineName'] = $m[1];
                    $seg['FlightNumber'] = $m[2];
                }
                $seg['DepCode'] = $seg['ArrCode'] = TRIP_CODE_UNKNOWN;
                $node = $this->http->FindSingleNode("./preceding::table[normalize-space(.)!=''][1]/preceding::table[contains(.,'-')][1]//td[normalize-space(.)!=''][1]", $root);

                if (preg_match("#(.+?)[\s\-]+(.+)#", $node, $m)) {
                    $seg['DepName'] = $m[1];
                    $seg['ArrName'] = $m[2];
                }
                $seg['DepDate'] = $this->normalizeDate($this->http->FindSingleNode("./descendant::text()[{$this->eq($this->t('Departure:'))}]/following::text()[normalize-space(.)!=''][1]", $root));
                $seg['ArrDate'] = $this->normalizeDate($this->http->FindSingleNode("./descendant::text()[{$this->eq($this->t('Arrival:'))}]/following::text()[normalize-space(.)!=''][1]", $root));
                $node = $this->http->FindSingleNode("./preceding::table[normalize-space(.)!=''][1]/descendant::text()[{$this->starts($this->t('Travel Class'))}]", $root, true, "#{$this->opt($this->t('Travel Class'))}[\s:]+(.+)#");

                if (preg_match("#(.+?)\s*(?:\(([A-Z]{1,2})\)|$)#", $node, $m)) {
                    $seg['Cabin'] = $m[1];

                    if (isset($m[2]) && !empty($m[2])) {
                        $seg['BookingClass'] = $m[2];
                    }
                }
                $tot = $this->getTotalCurrency($this->http->FindSingleNode("./preceding::table[normalize-space(.)!=''][1]/preceding::table[contains(.,'-')][1]//td[normalize-space(.)!=''][2]", $root));

                if (!empty($tot['Total'])) {
                    if (empty($totalCur) || $totalCur == $tot['Currency']) {
                        $totalSum += $tot['Total'];
                        $totalCur = $tot['Currency'];
                    } else {
                        $oneCurrency = false;
                    }
                } else {
                    $oneCurrency = false;
                }
                $it['TripSegments'][] = $seg;
            }

            if ($oneCurrency) {
                $it['TotalCharge'] = $totalSum;
                $it['Currency'] = $totalCur;
            }
            $its[] = $it;
        }

        return $its;
    }

    private function parseHotels()
    {
        $its = [];
        $pax = $this->http->FindNodes("//text()[{$this->eq($this->t('Traveller:'))}]/following::text()[normalize-space(.)!=''][1]");
        $tripNum = $this->http->FindSingleNode("//text()[{$this->eq($this->t('PNR:'))}]/following::text()[normalize-space(.)!=''][1]");
        $status = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Status:'))}]/following::text()[normalize-space(.)!=''][1]");
        $nodes = $this->http->XPath->query("//text()[{$this->eq($this->t('Check in:'))}]/ancestor::table[1]");

        foreach ($nodes as $root) {
            $it = ["Kind" => "R"];
            $it['ConfirmationNumber'] = $this->http->FindSingleNode("./preceding::table[normalize-space(.)!=''][1]/descendant::text()[{$this->starts($this->t('Ref:'))}]", $root, null, "#[\s:]+([A-Z\d]{5,})#");
            $it['GuestNames'] = $pax;

            if (count($pax) > 0) {
                $it['Guests'] = count($pax);
            }
            $it['TripNumber'] = $tripNum;
            $it['Status'] = $status;
            $tot = $this->getTotalCurrency($this->http->FindSingleNode("./preceding::table[normalize-space(.)!=''][1]/preceding::table[normalize-space(.)!=''][1]//td[normalize-space(.)!=''][2]", $root));

            if (!empty($tot['Total'])) {
                $it['Total'] = $tot['Total'];
                $it['Currency'] = $tot['Currency'];
            }
            $it['HotelName'] = $this->http->FindSingleNode("./preceding::table[normalize-space(.)!=''][1]/descendant::text()[normalize-space(.)!=''][1]", $root);
            $node = implode("\n", $this->http->FindNodes("./preceding::table[normalize-space(.)!=''][1]/descendant::text()[normalize-space(.)!=''][position() > 1]", $root));

            if (preg_match("#(.+?)\s*([\+\d \-\(\)]+)?\s*{$this->opt($this->t('Ref:'))}#s", $node, $m)) {
                $it['Address'] = preg_replace("#\s+#", ' ', $m[1]);

                if (isset($m[2]) && !empty($m[2])) {
                    $it['Phone'] = $m[2];
                }
            }
            $it['CheckInDate'] = $this->normalizeDate($this->http->FindSingleNode("./descendant::text()[{$this->eq($this->t('Check in:'))}]/following::text()[normalize-space(.)!=''][1]", $root));
            $it['CheckOutDate'] = $this->normalizeDate($this->http->FindSingleNode("./descendant::text()[{$this->eq($this->t('Check out:'))}]/following::text()[normalize-space(.)!=''][1]", $root));
            $it['RoomType'] = $this->http->FindSingleNode("./descendant::text()[{$this->eq($this->t('Room:'))}]/following::text()[normalize-space(.)!=''][1]", $root);

            $its[] = $it;
        }

        return $its;
    }

    private function parseCars()
    {
        $its = [];
        $pax = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Traveller:'))}]/following::text()[normalize-space(.)!=''][1]");
        $tripNum = $this->http->FindSingleNode("//text()[{$this->eq($this->t('PNR:'))}]/following::text()[normalize-space(.)!=''][1]");
        $status = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Status:'))}]/following::text()[normalize-space(.)!=''][1]");
        $nodes = $this->http->XPath->query("//text()[{$this->eq($this->t('Pick-up:'))}]/ancestor::table[1]");

        foreach ($nodes as $root) {
            $it = ["Kind" => "L"];
            $it['RentalCompany'] = $this->http->FindSingleNode("./preceding::table[normalize-space(.)!=''][1]", $root);
            $it['Number'] = $this->http->FindSingleNode("./preceding::table[normalize-space(.)!=''][1]/descendant::text()[{$this->starts($this->t('Ref:'))}]", $root, null, "#[\s:]+([A-Z\d]{5,})#");

            if (empty($it['Number'])) {
                $it['Number'] = CONFNO_UNKNOWN;
            }
            $it['TripNumber'] = $tripNum;
            $it['RenterName'] = $pax;
            $it['Status'] = $status;
            $tot = $this->getTotalCurrency($this->http->FindSingleNode("./preceding::table[normalize-space(.)!=''][1]/preceding::table[normalize-space(.)!=''][1]//td[normalize-space(.)!=''][2]", $root));

            if (!empty($tot['Total'])) {
                $it['TotalCharge'] = $tot['Total'];
                $it['Currency'] = $tot['Currency'];
            }
            $city = $this->http->FindSingleNode("./preceding::table[normalize-space(.)!=''][1]/preceding::table[normalize-space(.)!=''][1]//td[normalize-space(.)!=''][1]", $root, true, "#{$this->opt($this->t('in'))}\s*(.+)#");
            $node = $this->http->FindSingleNode("./descendant::text()[{$this->eq($this->t('Pick-up:'))}]/following::text()[normalize-space(.)!=''][1]", $root);

            if (preg_match("#(.+)\s*{$this->opt($this->t('from'))}\s*(.+)#", $node, $m)) {
                $it['PickupDatetime'] = $this->normalizeDate($m[1]);
                $it['PickupLocation'] = trim($city . '; ' . $m[2]);
            }
            $node = $this->http->FindSingleNode("./descendant::text()[{$this->eq($this->t('Drop-off:'))}]/following::text()[normalize-space(.)!=''][1]", $root);

            if (preg_match("#(.+)\s*{$this->opt($this->t('at'))}\s*(.+)#", $node, $m)) {
                $it['DropoffDatetime'] = $this->normalizeDate($m[1]);
                $it['DropoffLocation'] = trim($city . '; ' . $m[2]);
            }
            $its[] = $it;
        }

        return $its;
    }

    private function normalizeDate($date)
    {
        $year = date('Y', $this->date);
        $in = [
            //Wednesday, 8th Nov at 06:45 a.m.
            //Wednesday 8 Nov 06:45 pm
            '#^\s*(\w+),?\s+(\d+)(?:th|nd|rd|st)?\s+(\w+)\s+(?:at)?\s*(\d+:\d+)\s+([ap])\.?(m)\.?\s*$#i',
            //Wednesday, 8th Nov at 06:45
            //Wednesday 8 Nov 06:45
            '#^\s*(\w+),?\s+(\d+)(?:th|nd|rd|st)?\s+(\w+)\s+(?:at)?\s*(\d+:\d+)\s*$#i',
        ];
        $out = [
            '$2 $3 ' . $year . ' $4 $5$6',
            '$2 $3 ' . $year . ' $4',
        ];
        $outWeek = [
            '$1',
        ];
        $weeknum = WeekTranslate::number1(WeekTranslate::translate(preg_replace($in, $outWeek, $date), $this->lang));
        $str = $this->dateStringToEnglish(preg_replace($in, $out, $date));
        $str = EmailDateHelper::parseDateUsingWeekDay($str, $weeknum);

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
        if (isset($this->reBody)) {
            foreach ($this->reBody as $lang => $reBody) {
                foreach ($reBody as $re) {
                    if ($this->http->XPath->query("//*[contains(normalize-space(.),'{$re}')]")->length > 0) {
                        $this->lang = $lang;

                        return true;
                    }
                }
            }
        }

        return false;
    }

    private function dateStringToEnglish($date)
    {
        if (preg_match('#[[:alpha:]]+#iu', $date, $m)) {
            $monthNameOriginal = $m[0];

            if ($translatedMonthName = MonthTranslate::translate($monthNameOriginal, $this->lang)) {
                return preg_replace("#$monthNameOriginal#i", $translatedMonthName, $date);
            }
        }

        return $date;
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

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", $field) . ')';
    }
}
