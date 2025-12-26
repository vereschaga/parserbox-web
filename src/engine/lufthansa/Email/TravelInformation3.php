<?php

namespace AwardWallet\Engine\lufthansa\Email;

class TravelInformation3 extends \TAccountCheckerExtended
{
    public $rePlain = "";
    public $reHtml = [
        ['#Lufthansa.*Ticket Service#i', 'us', '/1'],
    ];
    public $rePDF = "";
    public $reSubject = "";
    public $reFrom = [
        ['#Lufthansa#i', 'us', ''],
    ];
    public $reProvider = [
        ['#booking-lufthansa\.com#i', 'us', ''],
    ];
    public $fnLanguage = "";
    public $langSupported = "en, ja";
    public $typesCount = "2";
    public $isAggregator = "";
    public $caseReference = "";
    public $upDate = "13.04.2015, 13:17";
    public $crDate = "";
    public $xPath = "";
    public $mailFiles = "lufthansa/it-1648394.eml";
    public $re_catcher = "#.*?#";
    public $pdfRequired = "0";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    $this->year = re('#\d{4}#i', $this->parser->getHeader('date'));
                    $subj = $this->parser->getHtmlBody();
                    // Cutting out encoding meta definition which fails email parsing
                    $subj = preg_replace('#<META[^>]+>#', '', $subj);
                    $this->http->setBody($subj);

                    return [$text];
                },

                "#.*?#" => [
                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "T";
                    },

                    "RecordLocator" => function ($text = '', $node = null, $it = null) {
                        return re('#(?:Reservation\s+code|予約番号):\s+([\w\-]+)#');
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        $xpath = "//text()[contains(., 'Travel dates for:') or contains(., '搭乗者氏名:')]/ancestor::td[1]/following-sibling::td[1]";

                        return nodes($xpath);
                    },

                    "TotalCharge" => function ($text = '', $node = null, $it = null) {
                        $xpath = "//text()[contains(., '合計金額（全員分）')]/ancestor::td/following-sibling::td[position() > 1]";
                        $subj = join(' ', nodes($xpath));

                        return ['TotalCharge' => cost($subj), 'Currency' => currency($subj)];
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        $xpath = "//tr[(contains(., 'Departure') or contains(., '出発地')) and (contains(., 'Arrival') or contains(., '到着地'))]/following-sibling::tr[contains(., ':')]";
                        $segments = $this->http->XPath->query($xpath);

                        if ($segments->length == 0) {
                            $xpath = "//tr[(contains(., 'Departure') or contains(., '出発地')) and (contains(., 'Arrival') or contains(., '到着地'))]/ancestor::table[1]/following-sibling::table[following-sibling::table[contains(., '合計金額')]]/tr[contains(., ':')]";
                            $segments = $this->http->XPath->query($xpath);
                        }

                        return $segments;
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            $this->baseTdIndex = 1;
                            $subj = node('./td[' . $this->baseTdIndex . ']');

                            if (!$subj) {
                                $this->baseTdIndex = 2;
                                $subj = node('./td[' . $this->baseTdIndex . ']');
                            }

                            if (preg_match('#^\s*([A-Z\d]{2})\s+(\d+)\s*(?:operated\s+by|運航航空会社):*\s*(.*)$#u', $subj, $m)) {
                                if (isset($m[3])) {
                                    $res['Operator'] = $m[3];
                                }
                                $res['AirlineName'] = $m[1];
                                $res['FlightNumber'] = $m[2];

                                return $res;
                            }
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
                        },

                        "DepName" => function ($text = '', $node = null, $it = null) {
                            return node('./td[' . ($this->baseTdIndex + 2) . ']');
                        },

                        "DepDate" => function ($text = '', $node = null, $it = null) {
                            $dateStr = '';

                            if (!$this->year) {
                                return null;
                            }
                            $subj = preg_replace('/[\x00-\x1F\x80-\xFF]/', '', node('./td[' . ($this->baseTdIndex + 1) . ']'));

                            if (preg_match('#(\d+)\.?\s+(\d+)#', $subj, $m)) {
                                $dateStr = $m[2] . '.' . $m[1] . '.' . $this->year;

                                if (preg_match('#-\s+(\d+)\.?\s+(\d+)#', $subj, $m)) {
                                    $arrDateStr = $m[2] . '.' . $m[1] . '.' . $this->year;
                                }
                            } elseif (preg_match('#(\d+)\.?\s+(\w+)#', $subj, $m)) {
                                $dateStr = $m[1] . ' ' . $m[2];

                                if (preg_match('#-\s+(\d+)\.?\s+(\w+)#', $subj, $m)) {
                                    $arrDateStr = $m[1] . ' ' . $m[2] . ' ' . $this->year;
                                }
                            }
                            $res = [];

                            foreach (['Dep' => ($this->baseTdIndex + 4), 'Arr' => ($this->baseTdIndex + 5)] as $key => $value) {
                                $timeStr = re('#\d+:\d+#', preg_replace('/[\x00-\x1F\x80-\xFF]/', '', node('./td[' . $value . ']')));

                                if ($key == 'Arr' && isset($arrDateStr)) {
                                    $dateStr = $arrDateStr;
                                }
                                $res[$key . 'Date'] = strtotime($dateStr . ', ' . $timeStr);
                            }

                            return $res;
                        },

                        "ArrCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
                        },

                        "ArrName" => function ($text = '', $node = null, $it = null) {
                            return node('./td[' . ($this->baseTdIndex + 3) . ']');
                        },

                        "Class" => function ($text = '', $node = null, $it = null) {
                            $res = [];
                            $subj = node('./td[' . ($this->baseTdIndex + 6) . ']');
                            $regex = '#';
                            $regex .= '(?P<Cabin>\w+)\s+';
                            $regex .= '\((?P<BookingClass>\w)\)';
                            $regex .= '(.*:\s+(?P<Seats>\d+\w+))?';
                            $regex .= '#u';

                            if (preg_match($regex, $subj, $m)) {
                                copyArrayValues($res, $m, ['Cabin', 'BookingClass', 'Seats']);
                            }

                            return $res;
                        },
                    ],
                ],
            ],
        ];
    }

    public static function getEmailTypesCount()
    {
        return 2;
    }

    public static function getEmailLanguages()
    {
        return ["en", "ja"];
    }

    public function IsEmailAggregator()
    {
        return false;
    }
}
