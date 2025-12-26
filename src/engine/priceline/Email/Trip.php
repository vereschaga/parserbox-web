<?php

namespace AwardWallet\Engine\priceline\Email;

class Trip extends \TAccountChecker
{
    public $mailFiles = "priceline/it-15.eml, priceline/it-16.eml, priceline/it-1657418.eml, priceline/it-1849699.eml, priceline/it-1849703.eml, priceline/it-1854072.eml, priceline/it-2157921.eml, priceline/it-2167057.eml, priceline/it-2167061.eml, priceline/it-2348435.eml, priceline/it-2352703.eml, priceline/it-2401327.eml, priceline/it-26.eml, priceline/it-27.eml, priceline/it-3037696.eml, priceline/it-4379214.eml, priceline/it-6649645.eml";

    public $reFrom = ["@trans.priceline.com", "travel@priceline.com", "@production.priceline.com"];
    public $reBody = [
        'en' => [
            "Here are the details of the trip",
            "Here are the details on where I'll be staying",
            "Print your confirmation and show at check-in",
            "As a courtesy below is a copy of the hotel itinerary you recently reviewed on-line at priceline",
            "A copy of the itinerary is shown below",
        ],
    ];
    public $reSubject = [
        '#.*?\s*trip\s+to.+?\s+on\s+.+?\d{2}$#i',
        '#Rental Car Information$#',
        '#Your\s+priceline\.com\s+Itinerary\s+for\s+(.+?)\d+\s+\(Itinerary\s+\#.+\)#',
        '#priceline\.com\s+Hotel\s+Confirmation\s+for\s+(.+?)\d+\s+\(Itinerary\s+\#.+\)#',
    ];
    public $lang = '';

    public static $dict = [
        'en' => [
            'passengerInformation' => 'Passenger Information',
            '_flights'             => ['Multi-Destination Information', 'Your Flight Itinerary', 'Departing Flight Information'],
            '_hotels'              => 'Hotel Details',
            '_cars'                => ['your rental car reservation', 'Your Rental Car Reservation'],
        ],
    ];

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->AssignLang();

        $its = $this->parseEmail();

        if (count($its) > 1) {
            $node = $this->http->FindSingleNode("//text()[normalize-space(.)='Total Trip Cost:']/ancestor::td[1]/following-sibling::td[1]");
            $tot = $this->getTotalCurrency(str_replace("$", "USD", $node));

            if (!empty($tot['Total'])) {
                return [
                    'parsedData' => ['Itineraries' => $its, 'TotalChatge' => ['Amount' => $tot['Total'], 'Currency' => $tot['Currency']]],
                    'emailType'  => end(explode('\\', __CLASS__)) . ucfirst($this->lang),
                ];
            }
        }
        $name = explode('\\', __CLASS__);

        return [
            'parsedData' => ['Itineraries' => $its],
            'emailType'  => end($name) . ucfirst($this->lang),
        ];
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//a[contains(@href,'priceline.com')]")->length > 0) {
            return $this->AssignLang();
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        $flag = false;

        foreach ($this->reFrom as $from) {
            if (stripos($headers['from'], $from) !== false) {
                $flag = true;
            }
        }

        if ($flag && isset($this->reSubject)) {
            foreach ($this->reSubject as $reSubject) {
                if (preg_match($reSubject, $headers['subject'])) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->reFrom[0]) !== false || stripos($from, $this->reFrom[1]) !== false;
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
        $tripNum = $this->http->FindSingleNode("//text()[contains(.,'Priceline Trip Number')]/ancestor::td[1]/following-sibling::td[1]");

        if (empty($tripNum)) {
            $tripNum = $this->http->FindSingleNode("//text()[contains(.,'Request Number:')]", null, true, "#.+?:\s*(.+)#");
        }

        if (empty($tripNum)) {
            $tripNum = $this->http->FindSingleNode("(//text()[contains(.,'Request Number:')]/following::text()[string-length(normalize-space(.))>3])[1]");
        }

        $xpathFragment = "//*[normalize-space(.)='Passenger Information']/following::*/*[starts-with(normalize-space(.),'Passenger') and not(.//*)]";

        $pax = $this->http->FindNodes($xpathFragment . "/following::text()[string-length(normalize-space(.))>1][1]");

        $ff = $this->http->FindNodes($xpathFragment . "/following::text()[contains(normalize-space(.),'Frequent flier')]", null, '/Frequent\s+flier[#:\s]*(.+)/i');

        $its = [];

        //### FLIGHT ####

        $rule = $this->getRule($this->t('_flights'));

        if ($this->http->XPath->query("//text()[{$rule}]")->length > 0) {
            $xpath = "//text()[contains(.,'Flight')]/ancestor::tr[1][contains(.,'From') and contains(.,'To') and count(./td)>2]";

            $recLocs = array_filter(array_map('trim', explode(',', $this->http->FindSingleNode("//text()[contains(.,'Airline Record Locator (PNR)')]", null, true, "#:\s*(.+)#"))));

            if (count($recLocs) === 0) {
                $recLocs[] = empty($tripNum) ? CONFNO_UNKNOWN : $tripNum;
            }
            $airlines = array_unique($this->http->FindNodes($xpath . "/descendant::text()[contains(.,'Flight')]/preceding::text()[string-length(normalize-space(.))>3][1]"));

            $flights = $this->http->XPath->query($xpath);
            $airs = [];

            if (count($recLocs) !== count($airlines)) {
                $recLoc = array_shift($recLocs);

                foreach ($flights as $root) {
                    $airs[$recLoc][] = $root;
                }
            } else {
                $getRL = array_combine($airlines, $recLocs);

                foreach ($flights as $root) {
                    $airline = $this->http->FindSingleNode("./descendant::text()[contains(.,'Flight')]/preceding::text()[string-length(normalize-space(.))>3][1]", $root);
                    $airs[$getRL[$airline]][] = $root;
                }
            }

            foreach ($airs as $rl => $roots) {
                $it = ['Kind' => 'T', 'TripSegments' => []];
                $it['RecordLocator'] = $this->http->FindSingleNode(".//td[1]//text()[contains(.,'Conf')]", $roots[0], true, "#\w+[\#:\s]*([A-Z\d]+)#");

                if (empty($it['RecordLocator'])) {
                    $it['RecordLocator'] = $rl;
                }
                $it['Passengers'] = $pax;
                $it['TripNumber'] = $tripNum;
                $it['AccountNumbers'] = $ff;

                foreach ($roots as $root) {
                    $seg = [];
                    $node = $this->http->FindSingleNode("./ancestor::table[1]/preceding-sibling::*[(self::div or self::table) and contains(.,'Flight Information')][1]", $root);

                    if (preg_match("#Flight Information\s+-\s+(.+?\s+\d{4})\s+\(Arrives\s+(.+?\s+\d{4})\)#", $node, $m)) {
                        $dateDep = strtotime($this->normalizeDate($m[1]));
                        $dateArr = strtotime($this->normalizeDate($m[2]));
                    } else {
                        $dateDep = null;
                        $dateArr = null;
                    }

                    $node = implode("\n", $this->http->FindNodes("./td[1]//text()[string-length()>2]", $root));

                    if (preg_match("#(.+?)\s+Flight\s+(\d+)\s+(.+)#", $node, $m)) {
                        $seg['AirlineName'] = $m[1];
                        $seg['FlightNumber'] = $m[2];

                        if (preg_match('/(\d{1,3}h\s*\d{1,2}m)/i', $m[3], $matches)) {
                            $seg['Duration'] = $matches[1];
                        }

                        if (preg_match('/([.\d]+)mi/i', $m[3], $matches)) {
                            $seg['TraveledMiles'] = $matches[1];
                        }
                    }
                    $node = implode("\n", $this->http->FindNodes("./td[2]//text()[string-length()>0]", $root));

                    if (preg_match("#From\s+(.+?)\s+\(([A-Z]{3})\)\s(.+)\n(?:Departs[\s:]*)?\s*(.+)#", $node, $m)) {
                        $seg['DepName'] = $m[1] . ' (' . $m[3] . ')';
                        $seg['DepCode'] = $m[2];
                        $seg['DepDate'] = strtotime($this->normalizeDate($m[4]), $dateDep);
                    }
                    $node = implode("\n", $this->http->FindNodes("./td[3]//text()[string-length()>0]", $root));

                    if (preg_match("#To\s+(.+?)\s+\(([A-Z]{3})\)\s(.+)\n(?:Arrives[\s:]*)?\s*(.+)#", $node, $m)) {
                        $seg['ArrName'] = $m[1] . ' (' . $m[3] . ')';
                        $seg['ArrCode'] = $m[2];
                        $seg['ArrDate'] = strtotime($this->normalizeDate($m[4]), $dateArr);
                    }
                    $node = implode("\n", $this->http->FindNodes("./td[4]//text()[string-length()>2]", $root));

                    if (preg_match("#Aircraft\s+(.+)\n(.+)\s+Class#", $node, $m)) {
                        $seg['Aircraft'] = $m[1];
                        $seg['Cabin'] = $m[2];
                    }

                    $it['TripSegments'][] = $seg;
                }

                $its[] = $it;
            }

            if (count($its) === 1) {
                $node = $this->http->FindSingleNode("//text()[normalize-space(.)='Airfare Subtotal:']/ancestor::td[1]/following-sibling::td[1]");
                $tot = $this->getTotalCurrency(str_replace('$', 'USD', $node));

                if (!empty($tot['Total'])) {
                    $its[0]['BaseFare'] = $tot['Total'];
                    $its[0]['Currency'] = $tot['Currency'];
                }
                $node = $this->http->FindSingleNode("//text()[normalize-space(.)='Total Trip Cost:']/ancestor::td[1]/following-sibling::td[1]");
                $tot = $this->getTotalCurrency(str_replace('$', 'USD', $node));

                if (!empty($tot['Total'])) {
                    $its[0]['TotalCharge'] = $tot['Total'];
                    $its[0]['Currency'] = $tot['Currency'];
                }
            }
        }

        //### HOTEL ####

        $rule = $this->getRule($this->t('_hotels'));

        if ($this->http->XPath->query("//text()[{$rule}]")->length > 0) {
            $xpath = "//text()[$rule]/ancestor::*[self::div or self::table][1]/following-sibling::table";
            $nodes = $this->http->XPath->query($xpath);

            foreach ($nodes as $root) {
                $it = ['Kind' => 'R'];
                $it['TripNumber'] = $tripNum;
                $node = array_values(array_filter(array_unique($this->http->FindNodes(".//text()[contains(.,'Confirmation')]", $root, "#\w+[\#:\s]*([A-Z\d]+)#"))));

                if (count($node) === 1) {
                    $it['ConfirmationNumber'] = $node[0];
                } elseif (count($node) > 1) {
                    $it['ConfirmationNumber'] = $node[0];
                    $it['ConfirmationNumbers'] = $node;
                } elseif (!empty($tripNum)) {
                    $it['ConfirmationNumber'] = $tripNum;
                } else {
                    $it['ConfirmationNumber'] = CONFNO_UNKNOWN;
                } // priceline/it-27.eml

                $it['HotelName'] = $this->http->FindSingleNode("(./descendant::tr[1]/td[1]//text()[string-length(normalize-space(.))>3])[1]", $root);
                $it['Address'] = implode(" ", $this->http->FindNodes("(./descendant::tr[1]/td[1]//text()[string-length(normalize-space(.))>3])[position()>1 and position()<last()]", $root));
                $it['Phone'] = $this->http->FindSingleNode("(./descendant::tr[1]/td[1]//text()[string-length(normalize-space(.))>3])[position()=last()]", $root, true, "#^\s*([\d\-\s\(\)]+)\s*$#");

                if (empty($it['Phone'])) {
                    $it['Address'] .= ' ' . $this->http->FindSingleNode("(./descendant::tr[1]/td[1]//text()[string-length(normalize-space(.))>3])[position()=last()]", $root);
                }
                $it['CheckInDate'] = strtotime($this->normalizeDate($this->http->FindSingleNode("./descendant::tr[1]/td[2]//text()[contains(.,'Check-In')]/ancestor::td[1]/following-sibling::td[1]", $root)));
                $it['CheckOutDate'] = strtotime($this->normalizeDate($this->http->FindSingleNode("./descendant::tr[1]/td[2]//text()[contains(.,'Check-Out')]/ancestor::td[1]/following-sibling::td[1]", $root)));
                $it['GuestNames'] = $this->http->FindNodes("./descendant::tr[1]/td[2]//text()[contains(.,'Room')]/ancestor::td[1]/following-sibling::td[1]//text()[not(contains(.,'Confirmation')) and string-length(normalize-space(.))>2]", $root);
                $it['Rooms'] = count($this->http->FindNodes("./descendant::tr[1]/td[2]//text()[contains(.,'Room')]", $root));

                $its[] = $it;
            }

            if (count($its) === 1) {
                $node = $this->http->FindSingleNode("//text()[normalize-space(.) = 'Total Room Cost:']/ancestor::td[1]/following-sibling::td[1]");
                $tot = $this->getTotalCurrency(str_replace("$", "USD", $node));

                if (!empty($tot['Total'])) {
                    $its[0]['Total'] = $tot['Total'];
                    $its[0]['Currency'] = $tot['Currency'];
                }
            }
        }

        //### CAR ####

        $rule = $this->getRule($this->t('_cars'));

        if ($this->http->XPath->query("//text()[{$rule}]")->length > 0) {
            $xpath = "//text()[{$rule}]/ancestor::*[self::div or self::table or self::p][1]/following-sibling::div/descendant::table[1]";
            $nodes = $this->http->XPath->query($xpath);

            foreach ($nodes as $root) {
                $it = ['Kind' => 'L'];
                $it['TripNumber'] = $tripNum;
                $it['Number'] = $this->http->FindSingleNode(".//descendant::td[contains(.,'Confirmation Number')]/following-sibling::td[1]", $root, true, "#([A-Z\d]+)#");
                $it['RentalCompany'] = $this->http->FindSingleNode(".//descendant::td[contains(.,'Rental Partner')]/following-sibling::td[1]", $root);
                $it['CarType'] = $this->http->FindSingleNode(".//descendant::td[contains(.,'Car Type')]/following-sibling::td[1]", $root);
                $it['PickupDatetime'] = strtotime($this->normalizeDate($this->http->FindSingleNode(".//descendant::td[contains(.,'Pick-Up Date')]/following-sibling::td[1]", $root)));
                $it['DropoffDatetime'] = strtotime($this->normalizeDate($this->http->FindSingleNode(".//descendant::td[contains(.,'Drop-Off Date')]/following-sibling::td[1]", $root)));
                $it['PickupLocation'] = $this->http->FindSingleNode(".//descendant::td[contains(.,'Pick-Up Location')]/following-sibling::td[1]", $root);
                $it['DropoffLocation'] = $this->http->FindSingleNode(".//descendant::td[contains(.,'Drop-Off Location')]/following-sibling::td[1]", $root);
                $it['RenterName'] = $this->http->FindSingleNode(".//descendant::td[contains(.,'Driver')]/following-sibling::td[1]", $root);
                $its[] = $it;
            }
        }

        return $its;
    }

    private function getRule($fields, $type = 0)
    {
        $str = "";
        $w = (array) $fields;

        switch ($type) {
            case 1:  //for XPath return: "contains(normalize-space(.),'value1') or .. or contains(normalize-space(.),'valueN')"
                $str = implode(" or ", array_map(function ($s) {
                    return "contains(normalize-space(.),'{$s}')";
                }, $w));

                break;

            case 2:  //for XPath return: "contains(normalize-space(text()),'value1') or .. or contains(normalize-space(text()),'valueN')"
                $str = implode(" or ", array_map(function ($s) {
                    return "contains(normalize-space(text()),'{$s}')";
                }, $w));

                break;

            case 3:  //for XPath return: "starts-with(normalize-space(.),'value1') or .. or starts-with(normalize-space(.),'valueN')"
                $str = implode(" or ", array_map(function ($s) {
                    return "starts-with(normalize-space(.),'{$s}')";
                }, $w));

                break;

            case 4:  //for XPath return: "starts-with(normalize-space(text()),'value1') or .. or starts-with(normalize-space(text()),'valueN')"
                $str = implode(" or ", array_map(function ($s) {
                    return "starts-with(normalize-space(text()),'{$s}')";
                }, $w));

                break;

            case 5:  //for RegExp return: "(?:value1|valie2|..|valueN)"
                $str = "(?:" . implode("|", $w) . ")";

                break;

            default: //for XPath return: "normalize-space(.)='value1' or .. or normalize-space(.)='valueN'"
                $str = implode(" or ", array_map(function ($s) {
                    return "normalize-space(.)='{$s}'";
                }, $w));
        }

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
            foreach ($this->reBody as $lang => $reBody) {
                foreach ($reBody as $re) {
                    if ($this->http->XPath->query('//*[contains(normalize-space(.),"' . $re . '")]')->length > 0) {
                        $this->lang = $lang;

                        return true;
                    }
                }
            }
        }

        return false;
    }

    private function getTotalCurrency($node)
    {
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

    private function normalizeDate($date)
    {
        $in = [
            // 05/17/2010 at 10:30 P.M.
            '#^\s*(\d+)\/(\d+)\/(\d{4})\s*(?:at|\-)\s*(\d+:\d+)\s*([ap])[\.\s]?(m)[\.\s]?\s*$#i',
            '#^\s*(\d+)\/(\d+)\/(\d{4})\s*(?:at|\-)\s*(\d+:\d+)\s*$#i',
            // 10:30 P.M.
            '#^\s*(\d+:\d+)\s*([ap])[\.\s]?(m)[\.\s]?\s*$#i',
            // Thursday, January 1, 2009
            '#^\s*\w+,\s+(\w+)\s+(\d+),\s+(\d{4})\s*$#',
            // Thu, Apr 14, 2016 at 08:02 AM
            '#^\s*\w+,\s+(\w+)\s+(\d+),\s+(\d{4})\s*(?:at|\-)\s*(\d+:\d+)\s*([ap])[\.\s]?(m)[\.\s]?\s*$#i',
            '#^\s*\w+,\s+(\w+)\s+(\d+),\s+(\d{4})\s*(?:at|\-)\s*(\d+:\d+)\s*$#i',
            // March 29, 2014 - 11:00am
            '#^\s*(\w+)\s+(\d+),\s+(\d{4})\s*(?:at|\-)\s*(\d+:\d+)\s*([ap])[\.\s]?(m)[\.\s]?\s*$#i',
            '#^\s*(\w+)\s+(\d+),\s+(\d{4})\s*(?:at|\-)\s*(\d+:\d+)\s*$#i',
            // 2017/05/26 - 07:15 PM
            '/(\d{4})\/(\d{1,2})\/(\d{1,2})\s*-\s*(\d{1,2}:\d{2}\s*[AP]M)/i',
        ];
        $out = [
            '$3-$1-$2 $4 $5$6',
            '$3-$1-$2 $4',
            '$1 $2$3',
            '$2 $1 $3',
            '$2 $1 $3 $4 $5$6',
            '$2 $1 $3 $4',
            '$2 $1 $3 $4 $5$6',
            '$2 $1 $3 $4',
            '$1-$2-$3 $4',
        ];
        $str = $this->dateStringToEnglish(preg_replace($in, $out, $date));

        return $str;
    }
}
