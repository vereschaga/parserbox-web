<?php

namespace AwardWallet\Engine\skywards\Email;

class It9 extends \TAccountCheckerExtended
{
    public $reFrom = "#@emirates.com#i";
    public $reProvider = "#[.@]emirates.com#i";
    public $rePlain = "#\n[>\s]*From\s*:[^\n]*@emirates.com#i";
    public $rePlainRange = "";
    public $typesCount = "1";
    public $langSupported = "en";
    public $reSubject = "";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $xPath = "";
    public $mailFiles = "skywards/it-9.eml";
    public $rePDF = "";
    public $rePDFRange = "";
    public $pdfRequired = "";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    return xPath("//hr/following-sibling::table[contains(., 'Departure') or contains(., 'Service date')]");
                },

                ".//*[contains(.,'Service date')]" => [
                    "RecordLocator" => function ($text = '', $node = null, $it = null) {
                        return CONFNO_UNKNOWN;
                    },

                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "B";
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        return nodes("//*[contains(text(), 'Name')]/ancestor-or-self::tr[1]/following-sibling::tr/td[1]");
                    },

                    "AccountNumbers" => function ($text = '', $node = null, $it = null) {
                        return implode(",", nodes("//*[contains(text(), 'Name')]/ancestor-or-self::tr[1]/following-sibling::tr/td[position()=2 and text()]"));
                    },

                    "Status" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Status\s+([^\n]+)#");
                    },

                    "ReservationDate" => function ($text = '', $node = null, $it = null) {
                        return totime(re("#(\d+[A-Z]{3}\d{4}\s+\d+:\d+):\d+\s+Please do not reply#", $this->text()));
                    },

                    "TripCategory" => function ($text = '', $node = null, $it = null) {
                        return TRIP_CATEGORY_TRANSFER;
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        return xpath('.');
                    },

                    "TripSegments" => [
                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
                        },

                        "DepDate" => function ($text = '', $node = null, $it = null) {
                            $type = re("#CHAUFFEUR DRIVE ON\s+(\w+)#", text($text));

                            $date = cell('Service date', +1);
                            $remark = cell('Remark', +1);

                            $name = cell('Service city', +1);
                            $r = [];

                            if (re("#P/U\s+(\d+:\d+)\s+(.+)#", $remark)) {
                                $address = re(2);
                                $time = re(1);
                            }

                            if (re("#D/O\s+(.+)$#", $remark)) {
                                $address = re(1);
                                $time = re("#^(\d+)(\d{2})#", $remark) . ':' . re(2);
                            }

                            if ($type == 'DEPARTURE') {
                                $r['DepName'] = null;
                                $r['DepAddress'] = $address;
                                $r['ArrName'] = $name;
                                $r['ArrDate'] = MISSING_DATE;
                                $r['DepDate'] = totime($date . ', ' . $time);
                            } elseif ($type == 'ARRIVAL') {
                                $r['ArrName'] = null;
                                $r['ArrAddress'] = $address;
                                $r['DepName'] = $name;
                                $r['DepDate'] = totime($date . ', ' . $time);
                                $r['ArrDate'] = MISSING_DATE;
                            }

                            return $r;
                        },

                        "ArrCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
                        },
                    ],
                ],

                ".//*[contains(., 'Departure')]" => [
                    "RecordLocator" => function ($text = '', $node = null, $it = null) {
                        $airlineName = re("#\b[A-Z\d]{2}\b#", node('.//tr[1]/th[2]'));

                        return cell($airlineName . ' Booking Reference', +1, 0, '', null);
                    },

                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "T";
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        return nodes("//*[contains(text(), 'Name')]/ancestor-or-self::tr[1]/following-sibling::tr/td[1]");
                    },

                    "AccountNumbers" => function ($text = '', $node = null, $it = null) {
                        return implode(",", nodes("//*[contains(text(), 'Name')]/ancestor-or-self::tr[1]/following-sibling::tr/td[position()=2 and text()]"));
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        return xPath('.');
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            return re("#(\d+)#", node('.//tr[1]/th[2]'));
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
                        },

                        "DepName" => function ($text = '', $node = null, $it = null) {
                            return trim(cell("From", +1) . ', ' . cell("Departure Airport", +1), ',- ');
                        },

                        "DepDate" => function ($text = '', $node = null, $it = null) {
                            return strtotime(re("#(\d+\s*\w+\s*\d+)#", cell("Departure date", +1)) . ', ' . cell("Departure time", +1));
                        },

                        "ArrCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
                        },

                        "ArrName" => function ($text = '', $node = null, $it = null) {
                            return trim(cell("To", +1) . ', ' . cell("Arrival Airport", +1), ',- ');
                        },

                        "ArrDate" => function ($text = '', $node = null, $it = null) {
                            return strtotime(re("#(\d+\s*\w+\s*\d+)#", cell("Arrival date", +1)) . ', ' . cell("Arrival time", +1));
                        },

                        "AirlineName" => function ($text = '', $node = null, $it = null) {
                            return re("#\b[A-Z\d]{2}\b#", node('.//tr[1]/th[2]'));
                        },

                        "Aircraft" => function ($text = '', $node = null, $it = null) {
                            return cell('Aircraft', +1);
                        },

                        "Cabin" => function ($text = '', $node = null, $it = null) {
                            return node('.//tr[1]/th[3]');
                        },

                        "Seats" => function ($text = '', $node = null, $it = null) {
                            $seats = [];
                            $seg = implode(', ', nodes("following-sibling::table[position()=1 and contains(.,'Seat')]", $node));
                            re("#seat\s+(\d{2,}[A-Z])#", function ($m) use (&$seats) {
                                $seats[] = $m[1];
                            }, $seg);

                            return implode(', ', $seats);
                        },

                        "Duration" => function ($text = '', $node = null, $it = null) {
                            return cell('Flying Time', +1);
                        },

                        "Stops" => function ($text = '', $node = null, $it = null) {
                            return intval(trim(cell('Stops', +1), ',-'));
                        },
                    ],
                ],
            ],
        ];
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
