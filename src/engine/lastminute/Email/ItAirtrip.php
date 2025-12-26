<?php

namespace AwardWallet\Engine\lastminute\Email;

class ItAirtrip extends \TAccountCheckerExtended
{
    public $reBody = 'Thanks for using lastminute.com.au. Happy travels!';
    public $rePlainRange = "/1";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $rePDF = "";
    public $rePDFRange = "";
    public $reSubject = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $reFrom = "";
    public $reProvider = "";
    public $caseReference = "";
    public $xPath = "";
    public $mailFiles = "lastminute/it-1441649.eml, lastminute/it-2030925.eml";
    public $pdfRequired = "";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    $this->date = strtotime($this->parser->getHeader("date"));
                    $this->fullText = $text;
                    $passengersSrc = nodes("//text()[contains(., 'Adult')]/ancestor::tr[1]");
                    $passengers = [];

                    foreach ($passengersSrc as $p) {
                        $passengers[] = re('#:\s+(.*)#s', $p);
                    }
                    $this->passengers = array_unique($passengers);
                    $this->status = re('#woo\s+hoo\s+-\s+your\s+booking\s+is\s+(\w+)#');
                    $this->reservationDate = strtotime(str_replace(',', '', re('#Booked\s+by\s+.*\s+on\s+\w+\s+(\d+\s+\w+,\s+\d+)#i')));

                    $this->reservationInfo = null;

                    foreach (nodes('//tr[contains(., "booking reference:") and not(.//tr)]') as $n) {
                        $regex = '#\s*(.*)\s+booking\s+reference:\s+([\w\-]+)\s+.*:\s+(.*?)\s*\*?$#i';

                        if (preg_match($regex, $n, $m)) {
                            $this->reservationInfo[$m[2]]['AirlineName'] = $m[1];
                            $this->reservationInfo[$m[2]]['Total'] = $m[3];
                        }
                    }

                    return xpath('//text()[contains(., "Arrives")]/ancestor::tr[1]/preceding-sibling::tr[1][contains(., "Departs")]');
                },

                "#.*?#" => [
                    "RecordLocator" => function ($text = '', $node = null, $it = null) {
                        $an = re('#(.*)\s+Fare\s+rules#i', node('./ancestor::td[1]/preceding-sibling::td[1]'));

                        foreach ($this->reservationInfo as $recordLocator => $info) {
                            if ($info['AirlineName'] == $an) {
                                return $recordLocator;
                            }
                        }
                    },

                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "T";
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        return $this->passengers;
                    },

                    "Status" => function ($text = '', $node = null, $it = null) {
                        return $this->status;
                    },

                    "ReservationDate" => function ($text = '', $node = null, $it = null) {
                        return $this->reservationDate;
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        return xpath('.');
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            if (preg_match('#(\w{2})(\d+)#i', node('./td[1]'), $m)) {
                                return [
                                    'AirlineName'  => $m[1],
                                    'FlightNumber' => $m[2],
                                ];
                            }
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            $res = [];

                            foreach (['Dep' => 'Departs', 'Arr' => 'Arrives'] as $key => $value) {
                                $subj = ($key == 'Dep') ? node('.') : node('./following-sibling::tr[1]');
                                $r = '#';
                                $r .= $value . '\s[\w\s]+\s+';					// City
                                $r .= '(\d{2}:\d{2})(am|pm)\s+';				// Time
                                $r .= '\w+\s+(\d+)\s+(\w+)\s+';					// Date
                                $r .= '(\w+)\s+\((.*)\)';	// Code
                                $r .= '#';

                                if (preg_match($r, $subj, $m)) {
                                    [$time, $ampm, $day, $month, $code, $name] = array_slice($m, 1);
                                    $res[$key . 'Code'] = $code;
                                    $res[$key . 'Name'] = $name;
                                    $res[$key . 'Date'] = strtotime("$day $month, $time $ampm", $this->date);
                                }
                            }

                            return $res;
                        },

                        "Aircraft" => function ($text = '', $node = null, $it = null) {
                            return node('./following-sibling::tr[1]/td[1]');
                        },

                        "Cabin" => function ($text = '', $node = null, $it = null) {
                            return node('./following-sibling::tr[2]');
                        },

                        "Duration" => function ($text = '', $node = null, $it = null) {
                            return re("#= (.*)#", node("./following-sibling::tr[1]//text()[contains(., 'Total trip time')]"));
                        },
                    ],
                ],

                "PostProcessing" => function ($text = '', $node = null, $it = null) {
                    $itNew = uniteAirSegments($it);

                    $regex = '#TOTAL\s+COST\s+\((\w+)\s+including\s+taxes\s+and\s+GST\)\s*:\s+(.*)#i';

                    if (preg_match($regex, $this->fullText, $m)) {
                        $currency = $m[1];
                        $totalTotal = cost($m[2]);
                    }

                    if (count($itNew) == 1) {
                        if (isset($currency) and isset($totalTotal)) {
                            $itNew[0]['TotalCharge'] = $totalTotal;
                        }
                        $itNew[0]['Currency'] = $currency;
                    } else {
                        foreach ($itNew as &$i) {
                            if (isset($this->reservationInfo[$i['RecordLocator']])) {
                                $totalStr = $this->reservationInfo[$i['RecordLocator']]['Total'];
                                $i['TotalCharge'] = cost($totalStr);
                                $i['Currency'] = (isset($currency)) ? $currency : null;
                            }
                        }
                    }

                    return $itNew;
                },
            ],
        ];
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        return $this->http->XPath->query("//td[contains(., '" . $this->reBody . "')]")->length > 0;
    }

    public static function getEmailTypesCount()
    {
        return 1;
    }

    public static function getEmailLanguages()
    {
        return ["en"];
    }
}
