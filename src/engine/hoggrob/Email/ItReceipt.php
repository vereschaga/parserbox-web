<?php

namespace AwardWallet\Engine\hoggrob\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Engine\WeekTranslate;

class ItReceipt extends \TAccountChecker
{
    public $mailFiles = "hoggrob/it-1.eml, hoggrob/it-11230060.eml, hoggrob/it-11230074.eml, hoggrob/it-11230090.eml, hoggrob/it-1585880.eml, hoggrob/it-1683597.eml, hoggrob/it-1683602.eml, hoggrob/it-4.eml, hoggrob/it-6351477.eml";

    public $reFrom = ["TRXCORREX.COM", "hrgworldwide.com"];
    public $reBody = [
        'en' => [
            "Information for Trip Locator",
        ],
    ];
    public $reSubject = [
        '#E-TICKET\s+ITINERARY\s+RECEIPT\s+FOR#',
        '#Travel\s+Confirmation:\s+.+?\s+\-\s+Ref:\s+[A-Z\d]+\s+\-\s+Travel\s+Date:#',
        '#Your\s+travel\s+reservation\s+is\s+booked#',
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

        $this->AssignLang();

        $its = $this->parseEmail();

        return [
            'parsedData' => ['Itineraries' => $its],
            'emailType'  => $a[count($a = explode('\\', __CLASS__)) - 1] . ucfirst($this->lang),
        ];
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//a[contains(@href,'hrgworldwide.com')] | //img[contains(translate(@src,'HRGWORLDWIDE.COM','hrgworldwide.com'),'hrgworldwide.com') or contains(@alt,'HRG')] | //text()[contains(translate(.,'HRGWORLDWIDE.COM','hrgworldwide.com'),'hrgworldwide.com')]")->length > 0) {
            return $this->AssignLang();
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        $flag = false;

        if (isset($headers['from'])) {
            foreach ($this->reFrom as $from) {
                if (stripos($headers['from'], $from) !== false) {
                    $flag = true;
                }
            }
        }

        if ($flag && isset($this->reSubject)) {
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
        return stripos($from, $this->reFrom[0]) !== false || stripos($from, $this->reFrom[1]) !== false;
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
        $tripNum = $this->http->FindSingleNode("//text()[starts-with(normalize-space(.),'" . $this->t('Information for Trip Locator') . "')]", null, true, "#:\s+([A-Z\d]+)#");
        $pax = $this->http->FindNodes("//text()[starts-with(normalize-space(.),'Passengers')]/ancestor::tr[contains(.,'Frequent Flyer')][1]/following-sibling::tr//td[normalize-space(.)!=''][1]");
        $ff = array_map("trim", array_values(array_unique(array_filter(explode(",", implode(",", $this->http->FindNodes("//text()[starts-with(normalize-space(.),'Passengers')]/ancestor::tr[contains(.,'Frequent Flyer')][1]/following-sibling::tr//td[normalize-space(.)!=''][position()!=1 and position()=last()]")))))));
        $its = [];
        //###FLIGHT###########

        $xpath = "//text()[starts-with(normalize-space(.),'AIR -')]/ancestor::tr[1]";

        if ($this->http->XPath->query($xpath . "/following-sibling::tr[contains(.,'From')]")->length === 0) {
            $xpath .= "/ancestor::table[contains(.,'From')][1]/descendant::tr[1]";
        }

        if ($this->http->XPath->query($xpath)->length > 0) {
            $airRL = [];
            $airRL1 = [];
            $nRL = $this->http->FindNodes("//text()[starts-with(normalize-space(.),'General Remarks')]/ancestor::tr[1]/following-sibling::tr[contains(.,'LOCATOR')]", null, "#(.+?\*[A-Z\d]{2}\s+LOCATOR\s+[A-Z\d]+)#");

            foreach ($nRL as $value) {
                if (preg_match("#^\s*(\w+)\s+.+?([A-Z\d]{2})\s+LOCATOR\s+([A-Z\d]+)#", $value, $m)) {
                    $airRL[$m[2]] = $m[3];
                    $airRL1[$m[1]] = $m[3];
                }
            }

            $flights = $this->http->XPath->query($xpath);
            $airs = [];

            foreach ($flights as $flight) {
                $airline = $this->http->FindSingleNode("./following-sibling::tr[position()<8][contains(.,'YOUR FLIGHT NUMBER IS')][1]", $flight, true, "#YOUR FLIGHT NUMBER IS\s+([A-Z\d]{2})#");

                if (empty($airline)) {
                    $airline = strtoupper($this->http->FindSingleNode("./following-sibling::tr[1]", $flight, true, "#^\s*(\w+)#"));
                }

                if (isset($airRL[$airline])) {
                    $airs[$airRL[$airline]][] = $flight;
                } elseif (isset($airRL1[$airline])) {
                    $airs[$airRL1[$airline]][] = $flight;
                } else {
                    $airs[$tripNum][] = $flight;
                }
            }

            $node = $this->http->FindSingleNode("(//text()[starts-with(normalize-space(.),'DATE OF ISSUE')])[1]", null, true, "#:\s+(.+)#");

            if (!empty($node)) {
                $this->date = $this->normalizeDate($node);
            }

            foreach ($airs as $rl => $roots) {
                $it = ['Kind' => 'T', 'TripSegments' => []];
                $it['RecordLocator'] = $rl;
                $it['Passengers'] = $pax;
                $it['TripNumber'] = $tripNum;
                $it['AccountNumbers'] = $ff;
                $airline = $this->http->FindSingleNode("./following-sibling::tr[1]", $roots[0], true, "#(.+)\s+Flight\s+\d+#");

                $it['TicketNumbers'] = $this->http->FindNodes("//text()[starts-with(normalize-space(.),'E-TICKET')]/ancestor::table[1]/following::text()[starts-with(normalize-space(.),'{$airline}')]/following-sibling::text()[starts-with(normalize-space(.),'TICKET NUMBER')]", null, "#TICKET NUMBER\s*:\s*(.+)#");

                if (count($it['TicketNumbers']) > 0) {
                    $node = $this->http->FindSingleNode("//text()[starts-with(normalize-space(.),'E-TICKET')]/ancestor::table[1]/following::text()[starts-with(normalize-space(.),'{$airline}')]/following-sibling::text()[starts-with(normalize-space(.),'FARE AMOUNT')]", null, true, "#:\s+(.+)#");
                    $tot = $this->getTotalCurrency(str_replace("$", "USD", $node));

                    if (!empty($tot['Total'])) {
                        $it['BaseFare'] = $tot['Total'];
                        $it['Currency'] = $tot['Currency'];
                    }
                    $node = $this->http->FindSingleNode("//text()[starts-with(normalize-space(.),'E-TICKET')]/ancestor::table[1]/following::text()[starts-with(normalize-space(.),'{$airline}')]/following-sibling::text()[starts-with(normalize-space(.),'TICKETING AMOUNT')]", null, true, "#:\s+(.+)#");
                    $tot = $this->getTotalCurrency(str_replace("$", "USD", $node));

                    if (!empty($tot['Total'])) {
                        $it['TotalCharge'] = $tot['Total'];
                        $it['Currency'] = $tot['Currency'];
                    }
                }

                foreach ($roots as $root) {
                    $seg = [];
                    $node = $this->http->FindSingleNode("./following-sibling::tr[position()<3][contains(.,'Operated by')]", $root, true, "#Operated by\s+(.+)#");

                    if (!empty($node)) {
                        $seg['Operator'] = $node;
                    }

                    $node = $this->http->FindSingleNode("./following-sibling::tr[1]", $root);

                    if (preg_match("#(.+?)\s+Flight\s+(\d+)\s+(.+)#", $node, $m)) {
                        $seg['AirlineName'] = $m[1];
                        $seg['FlightNumber'] = $m[2];
                        $seg['Cabin'] = $m[3];
                    } elseif (preg_match("#^(.+?)\s+Flight\s+([A-Z\d]{2})\s*(\d+)\s+(.+)#m", $node, $m)) {
                        $seg['AirlineName'] = $m[2];
                        $seg['FlightNumber'] = $m[3];
                        $seg['Cabin'] = $m[4];
                    }
                    $node = $this->http->FindSingleNode("./following-sibling::tr[position()<8][contains(.,'YOUR FLIGHT NUMBER IS')]", $root);

                    if (preg_match("#YOUR FLIGHT NUMBER IS\s+([A-Z\d]{2})\s*(\d+)#", $node, $m)) {
                        $seg['FlightNumber'] = $m[2];
                        $seg['AirlineName'] = $m[1];
                    }
                    $seg['DepCode'] = TRIP_CODE_UNKNOWN;
                    $seg['ArrCode'] = TRIP_CODE_UNKNOWN;

                    if ($this->http->XPath->query("./following-sibling::tr[position()<8]/descendant::text()[contains(.,'From')]/ancestor::tr[1][contains(.,'Equipment')]", $root)->length > 0) {
                        $node = implode("\n", $this->http->FindNodes("./following-sibling::tr[position()<8]/descendant::text()[contains(.,'From')]/ancestor::td[1]/following-sibling::td[1]//text()[string-length(normalize-space(.))>2]", $root));

                        if (preg_match("#(.+)\n(.+)(?:\n\s*(.*Terminal.*))?#i", $node, $m)) {
                            $seg['DepName'] = $m[1];
                            $seg['DepDate'] = $this->normalizeDate($m[2]);

                            if (isset($m[3]) && !empty($m[3])) {
                                $seg['DepartureTerminal'] = trim(str_ireplace("Terminal", "", $m[3]));
                            }
                        }
                        $node = implode("\n", $this->http->FindNodes("./following-sibling::tr[position()<8]/descendant::text()[contains(.,'To:')]/ancestor::td[1]/following-sibling::td[1]//text()[string-length(normalize-space(.))>2]", $root));

                        if (preg_match("#(.+)\n(.+)(?:\n\s*(.*Terminal.*))?#i", $node, $m)) {
                            $seg['ArrName'] = $m[1];
                            $seg['ArrDate'] = $this->normalizeDate($m[2]);

                            if (isset($m[3]) && !empty($m[3])) {
                                $seg['ArrivalTerminal'] = trim(str_ireplace("Terminal", "", $m[3]));
                            }
                        }
                    } else {
                        $seg['DepName'] = $this->http->FindSingleNode("./following-sibling::tr[position()<8]/descendant::text()[contains(.,'From')]/ancestor::td[1]/following-sibling::td[1]//text()[string-length(normalize-space(.))>2]", $root);
                        $node = implode("\n", $this->http->FindNodes("./following-sibling::tr[position()<8]/descendant::text()[contains(.,'From')]/ancestor::tr[1]/following-sibling::tr[1]/descendant::td[1]/following-sibling::td[1]//text()[string-length(normalize-space(.))>2]", $root));

                        if (preg_match("#(.+)(?:\n(.*Terminal.*))?#i", $node, $m)) {
                            $seg['DepDate'] = $this->normalizeDate($m[1]);

                            if (isset($m[2]) && !empty($m[2])) {
                                $seg['DepartureTerminal'] = trim(str_ireplace("Terminal", "", $m[2]));
                            }
                        }
                        $seg['ArrName'] = $this->http->FindSingleNode("./following-sibling::tr[position()<8]/descendant::text()[contains(.,'To')]/ancestor::td[1]/following-sibling::td[1]//text()[string-length(normalize-space(.))>2]", $root);
                        $node = implode("\n", $this->http->FindNodes("./following-sibling::tr[position()<8]/descendant::text()[contains(.,'To')]/ancestor::tr[1]/following-sibling::tr[1]/descendant::td[1]/following-sibling::td[1]//text()[string-length(normalize-space(.))>2]", $root));

                        if (preg_match("#(.+)(?:\n(.*Terminal.*))?#i", $node, $m)) {
                            $seg['ArrDate'] = $this->normalizeDate($m[1]);

                            if (isset($m[2]) && !empty($m[2])) {
                                $seg['ArrivalTerminal'] = $m[2];
                            }
                        }
                    }
                    $seg['Aircraft'] = $this->http->FindSingleNode("./following-sibling::tr[position()<8]/descendant::text()[contains(.,'Equipment')]/ancestor::td[1]/following-sibling::td[1]", $root);
                    $seg['Duration'] = $this->http->FindSingleNode("./following-sibling::tr[position()<8]/descendant::text()[contains(.,'Duration')]/ancestor::td[1]/following-sibling::td[1]", $root);
                    $seg['Meal'] = $this->http->FindSingleNode("./following-sibling::tr[position()<8]/descendant::text()[contains(.,'Meals')]/ancestor::td[1]/following-sibling::td[1]", $root);
                    $it['Status'] = $this->http->FindSingleNode("./following-sibling::tr[position()<8]/descendant::text()[contains(.,'Status')]/ancestor::td[1]/following-sibling::td[1]", $root);

                    $it['TripSegments'][] = $seg;
                }

                $its[] = $it;
            }

            if (count($its) === 1 && count($its[0]['TicketNumbers']) < 2) {
                $node = $this->http->FindSingleNode("//text()[starts-with(normalize-space(.),'FARE AMOUNT')]", null, true, "#:\s+(.+)#");
                $tot = $this->getTotalCurrency(str_replace("$", "USD", $node));

                if (!empty($tot['Total'])) {
                    $its[0]['BaseFare'] = $tot['Total'];
                    $its[0]['Currency'] = $tot['Currency'];
                }
                $node = $this->http->FindSingleNode("//text()[starts-with(normalize-space(.),'TICKETING AMOUNT')]", null, true, "#:\s+(.+)#");
                $tot = $this->getTotalCurrency(str_replace("$", "USD", $node));

                if (!empty($tot['Total'])) {
                    $its[0]['TotalCharge'] = $tot['Total'];
                    $its[0]['Currency'] = $tot['Currency'];
                }
            }
        }

        //###HOTEL###########
        $xpath = "//text()[starts-with(normalize-space(.),'HOTEL -')]/ancestor::tr[1]";

        if ($this->http->XPath->query($xpath . "/following-sibling::tr[contains(.,'Address')]")->length === 0) {
            $xpath .= "/ancestor::table[contains(.,'Address')][1]/descendant::tr[1]";
        }

        if ($this->http->XPath->query($xpath)->length > 0) {
            $nodes = $this->http->XPath->query($xpath);

            foreach ($nodes as $root) {
                /** @var \AwardWallet\ItineraryArrays\Hotel $it */
                $it = ['Kind' => 'R'];
                $it['TripNumber'] = $tripNum;
                $it['ConfirmationNumber'] = $this->http->FindSingleNode("./following-sibling::tr[position()<9]/descendant::text()[contains(.,'Confirmation')]/ancestor::td[1]/following-sibling::td[1]", $root);
                $it['HotelName'] = $this->http->FindSingleNode("./following-sibling::tr[1]", $root);
                $it['Address'] = $this->http->FindSingleNode("./following-sibling::tr[position()<9]/descendant::text()[contains(.,'Address')]/ancestor::td[1]/following-sibling::td[1]", $root);
                $it['Phone'] = $this->http->FindSingleNode("./following-sibling::tr[position()<9]/descendant::text()[contains(.,'Telephone')]/ancestor::td[1]/following-sibling::td[1]", $root);
                $it['Fax'] = $this->http->FindSingleNode("./following-sibling::tr[position()<9]/descendant::text()[contains(.,'Fax')]/ancestor::td[1]/following-sibling::td[1]", $root);
                $it['CheckInDate'] = $this->normalizeDate($this->http->FindSingleNode(".", $root, true, "#HOTEL\s*\-\s*(.+)#"));
                $it['CheckOutDate'] = $this->normalizeDate($this->http->FindSingleNode("./following-sibling::tr[position()<9]/descendant::text()[contains(.,'Check out')]/ancestor::td[1]/following-sibling::td[1]", $root));
                $it['Rate'] = $this->http->FindSingleNode("./following-sibling::tr[position()<9]/descendant::text()[contains(.,'Rate')]/ancestor::td[1]/following-sibling::td[1]", $root);
                $it['Status'] = $this->http->FindSingleNode("./following-sibling::tr[position()<9]/descendant::text()[contains(.,'Status')]/ancestor::td[1]/following-sibling::td[1]", $root);
                $it['CancellationPolicy'] = $this->http->FindSingleNode("./following-sibling::tr[position()<9]/descendant::text()[contains(.,'CANCEL')]", $root);

                if (empty($it['CancellationPolicy'])) {
                    $it['CancellationPolicy'] = $this->http->FindSingleNode("./following-sibling::tr[position()<9]/descendant::text()[contains(.,'Cancellation Policy')]/ancestor::td[1]/following-sibling::td[1]", $root);
                }
                $it['GuestNames'] = $pax;

                $its[] = $it;
            }
        }

        //###CAR#############
        $xpath = "//text()[starts-with(normalize-space(.),'CAR -')]/ancestor::tr[1]";

        if ($this->http->XPath->query($xpath . "/following-sibling::tr[contains(.,'Pick up')]")->length === 0) {
            $xpath .= "/ancestor::table[contains(.,'Pick up')][1]/descendant::tr[1]";
        }

        if ($this->http->XPath->query($xpath)->length > 0) {
            $nodes = $this->http->XPath->query($xpath);

            foreach ($nodes as $root) {
                /** @var \AwardWallet\ItineraryArrays\CarRental $it */
                $it = ['Kind' => 'L'];
                $it['TripNumber'] = $tripNum;
                $it['Number'] = $this->http->FindSingleNode("./following-sibling::tr[position()<9]/descendant::text()[contains(.,'Confirmation')]/ancestor::td[1]/following-sibling::td[1]", $root);
                $it['RentalCompany'] = $this->http->FindSingleNode("./following-sibling::tr[1]", $root);
                $it['RenterName'] = $this->http->FindSingleNode("./following-sibling::tr[position()<9]/descendant::text()[contains(.,'Name')]/ancestor::td[1]/following-sibling::td[1]", $root);
                $node = implode("\n", $this->http->FindNodes("./following-sibling::tr[position()<10]/descendant::text()[contains(.,'Pick up')]/ancestor::td[1]/following-sibling::td[1]//text()[normalize-space(.)!='']", $root));

                if (preg_match("#(?:Pick up hours[\s:]+([^\n]+))?\s+(.+?)(?:\s+Tel[\s:]+([\d \-\(\)\+]+))?(?:\s+Fax[\s:]\s+([\d \-\(\)\+]+))?\s+(\d{4}\s+[hrs]+.+)$#is", $node, $m)) {
                    if (isset($m[1]) && !empty($m[1])) {
                        $it['PickupHours'] = $m[1];
                    }
                    $it['PickupLocation'] = trim(preg_replace("#\s+#", ' ', $m[2]));

                    if (isset($m[3]) && !empty($m[3])) {
                        $it['PickupPhone'] = $m[3];
                    }

                    if (isset($m[4]) && !empty($m[4])) {
                        $it['PickupFax'] = $m[4];
                    }
                    $it['PickupDatetime'] = $this->normalizeDate($m[5]);
                }
                $node = implode("\n", $this->http->FindNodes("./following-sibling::tr[position()<10]/descendant::text()[contains(.,'Drop Off')]/ancestor::td[1]/following-sibling::td[1]//text()[normalize-space(.)!='']", $root));

                if (preg_match("#(?:Pick up hours[\s:]+([^\n]+)\n)?(.+?)(?:\s+Tel[\s:]+([\d \-\(\)\+]+))?(?:\s+Fax[\s:]\s+([\d \-\(\)\+]+))?(?:\s+Drop off hours[\s:]+([^\n]+)){0,2}\s+(\d{4}\s+[hrs]+.+)$#is", $node, $m)) {
                    if (isset($m[1]) && !empty($m[1])) {
                        $it['PickupHours'] = $m[1];
                    }
                    $it['DropoffLocation'] = trim(preg_replace("#\s+#", ' ', $m[2]));

                    if (isset($m[3]) && !empty($m[3])) {
                        $it['DropoffPhone'] = $m[3];
                    }

                    if (isset($m[4]) && !empty($m[4])) {
                        $it['DropoffFax'] = $m[4];
                    }

                    if (isset($m[5]) && !empty($m[5])) {
                        $it['DropoffHours'] = $m[5];
                    }
                    $it['DropoffDatetime'] = $this->normalizeDate($m[6]);
                }
                $it['CarType'] = $this->http->FindSingleNode("./following-sibling::tr[position()<9]/descendant::text()[contains(.,'Type')]/ancestor::td[1]/following-sibling::td[1]", $root);
                $it['Status'] = $this->http->FindSingleNode("./following-sibling::tr[position()<9]/descendant::text()[contains(.,'Status')]/ancestor::td[1]/following-sibling::td[1]", $root);

                if (!empty($code = $this->http->FindSingleNode("./following-sibling::tr[position()<9]/descendant::text()[contains(.,'Corporate Discount')]/ancestor::td[1]/following-sibling::td[1]", $root))) {
                    $it['Discounts'][] = [
                        'Code' => $code,
                        'Name' => 'Corporate Discount',
                    ];
                }
                $its[] = $it;
            }
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

    private function normalizeDate($date)
    {
        //		$this->http->Log($date);
        $year = date('Y', $this->date);

        $in = [
            //07AUG15
            '#^\s*(\d{2})\s*(\w+)\s*(\d{2})\s*$#',
            //0910 hrs, Monday, August 10
            '#^\s*(\d{2})(\d{2})\s*[hrs]+\s*,\s+(\w+),\s+(\w+)\s+(\d+)\s*$#iu',
            //Monday, August 10
            '#^\s*(\w+),\s+(\w+)\s+(\d+)\s*$#iu',
        ];
        $out = [
            '$1 $2 20$3',
            '$5 $4 ' . $year . ' $1:$2',
            '$3 $2 ' . $year,
        ];
        $outWeek = [
            '',
            '$3',
            '$1',
        ];

        if (!empty($week = preg_replace($in, $outWeek, $date))) {
            $weeknum = WeekTranslate::number1(WeekTranslate::translate($week, $this->lang));
            $str = $this->dateStringToEnglish(preg_replace($in, $out, $date));
            $str = EmailDateHelper::parseDateUsingWeekDay($str, $weeknum);
        } else {
            $str = strtotime($this->dateStringToEnglish(preg_replace($in, $out, $date)));
        }
        //		$this->http->Log(date('Y-m-d H:i',$str));

        return $str;

        //		$str = $this->dateStringToEnglish(preg_replace($in, $out, $date));
//		return $str;
    }
}
