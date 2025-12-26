<?php

namespace AwardWallet\Engine\wtravel\Email;

class It2702846 extends \TAccountCheckerExtended
{
    public $rePlain = [
        ['Global Knowledge Travel Center#i', 'blank', '/1'],
        ['World Travel Service#i', 'blank', '/1'],
    ];
    public $reHtml = [
        ['World Travel Service#i', 'blank', '/1'],
    ];
    public $rePDF = "";
    public $reSubject = "";
    public $reFrom = [
        ['#[@.]globalknowledge[.]#i', 'blank', ''],
    ];
    public $reProvider = "";
    public $fnLanguage = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $isAggregator = "0";
    public $caseReference = "";
    public $upDate = "24.02.2016, 11:49";
    public $crDate = "05.06.2015, 11:32";
    public $xPath = "";
    public $mailFiles = "wtravel/it-2702846.eml, wtravel/it-3545695.eml, wtravel/it-3548782.eml, wtravel/it-3550147.eml, wtravel/it-3555031.eml, wtravel/it-3556765.eml, wtravel/it-3564416.eml";
    public $re_catcher = "#.*?#";
    public $pdfRequired = "0";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    $xpath = "//*[
						normalize-space(text()) = 'Depart:' or
						normalize-space(text()) = 'Room Type:' or
						normalize-space(text()) = 'Pick Up:'
					]/ancestor::table[2]";

                    $total = rew('Total Charges : (.+?) \n');

                    if ($total) {
                        $total = total($total, 'Amount');
                        $this->parsedValue('TotalCharge', $total);
                    }

                    // we will get airport codes from here by air segment number
                    $this->summary = rew('Date Codes (.+?) (?:Air - | Hotel - | Car -)');
                    $this->airCount = 1;

                    return xpath($xpath);
                },

                "#Room Type:#i" => [
                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "R";
                    },

                    "ConfirmationNumber" => function ($text = '', $node = null, $it = null) {
                        return reni('Confirmation: (\w+)');
                    },

                    "HotelName" => function ($text = '', $node = null, $it = null) {
                        return reni('^ (.+?) Address:');
                    },

                    "CheckInDate" => function ($text = '', $node = null, $it = null) {
                        $q = white('\b(\d+\w+\d{4})\b');
                        $date = ure("/$q/isu", 1);

                        $dt = timestamp_from_format($date, 'dMY|');

                        return $dt;
                    },

                    "CheckOutDate" => function ($text = '', $node = null, $it = null) {
                        $q = white('\b(\d+\w+\d{4})\b');
                        $date = ure("/$q/isu", 2);

                        $dt = timestamp_from_format($date, 'dMY|');

                        return $dt;
                    },

                    "Address" => function ($text = '', $node = null, $it = null) {
                        $addr = nodes("//*[contains(text(), 'Address:')]/ancestor::tbody[1]/tr/td[position() = 2]");
                        $addr = nice(implode(' ', $addr));
                        $name = arrayVal($it, 'HotelName');

                        return orval(
                            $addr,
                            reni('Address : (.+?) Tel :'),
                            $name
                        );
                    },

                    "Phone" => function ($text = '', $node = null, $it = null) {
                        return nice(cell('Tel:', +1));
                    },

                    "Fax" => function ($text = '', $node = null, $it = null) {
                        return nice(cell('Fax:', +1));
                    },

                    "Rooms" => function ($text = '', $node = null, $it = null) {
                        return reni('Number of Rooms:  (\d+)');
                    },

                    "Rate" => function ($text = '', $node = null, $it = null) {
                        return nice(cell('Rate per night:', +1));
                    },

                    "CancellationPolicy" => function ($text = '', $node = null, $it = null) {
                        return nice(cell('Cancellation Policy:', +1));
                    },

                    "RoomType" => function ($text = '', $node = null, $it = null) {
                        return nice(cell('Room Type:', +1));
                    },

                    "Status" => function ($text = '', $node = null, $it = null) {
                        if (rew('Status:  Confirmed')) {
                            return 'confirmed';
                        }
                    },
                ],

                "#Depart:#i" => [
                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "T";
                    },

                    "RecordLocator" => function ($text = '', $node = null, $it = null) {
                        return CONFNO_UNKNOWN;
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        $q = white('FF Number: \w+ - (.+?) \n');

                        if (preg_match_all("/$q/isu", text($text), $m)) {
                            return nice($m[1]);
                        }
                    },

                    "AccountNumbers" => function ($text = '', $node = null, $it = null) {
                        $q = white('FF Number: (\w+) -');

                        if (preg_match_all("/$q/isu", text($text), $m)) {
                            return nice($m[1]);
                        }
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        return xpath('.');
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            $fl = reni('^ .+? - (\w{2}\d+)');

                            return uberAir($fl);
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            $q = white('\d+ \/ \d+ \/ \d{4}  \b ([A-Z]{3}) -');
                            $code = ure("/$q/su", $this->summary, $this->airCount);

                            return orval($code, TRIP_CODE_UNKNOWN);
                        },

                        "DepName" => function ($text = '', $node = null, $it = null) {
                            $name1 = node(".//*[contains(text(), 'Depart:')]/ancestor::tr[1]/td[2]");
                            $name2 = node(".//*[contains(text(), 'Depart:')]/ancestor::tr[1]/following-sibling::tr[1]/td[2]");

                            return nice("$name1 $name2");
                        },

                        "DepDate" => function ($text = '', $node = null, $it = null) {
                            $q = white('Date:  (.+?\d{4})');
                            $date = ure("/$q/isu", 1);
                            $time = uberTime(1);

                            $dt = timestamp_from_format($date, 'dMY|');
                            $dt = strtotime($time, $dt);

                            return $dt;
                        },

                        "ArrCode" => function ($text = '', $node = null, $it = null) {
                            $q = white('\d+ \/ \d+ \/ \d{4}  .+? - ([A-Z]{3}) \b');
                            $code = ure("/$q/su", $this->summary, $this->airCount);
                            $this->airCount++;

                            return orval($code, TRIP_CODE_UNKNOWN);
                        },

                        "ArrName" => function ($text = '', $node = null, $it = null) {
                            $name1 = node(".//*[contains(text(), 'Arrive:')]/ancestor::tr[1]/td[2]");
                            $name2 = node(".//*[contains(text(), 'Arrive:')]/ancestor::tr[1]/following-sibling::tr[1]/td[2]");

                            return nice("$name1 $name2");
                        },

                        "ArrDate" => function ($text = '', $node = null, $it = null) {
                            $q = white('Date:  (.+?\d{4})');
                            $date = ure("/$q/isu", 2);
                            $time = uberTime(2);

                            $dt = timestamp_from_format($date, 'dMY|');
                            $dt = strtotime($time, $dt);

                            return $dt;
                        },

                        "Aircraft" => function ($text = '', $node = null, $it = null) {
                            $s = cell('Equipment', +1);

                            return nice($s);
                        },

                        "Seats" => function ($text = '', $node = null, $it = null) {
                            $q = white('Seat:  (\d+\w+)');

                            if (preg_match_all("/$q/isu", text($text), $m)) {
                                return nice($m[1]);
                            }
                        },

                        "Duration" => function ($text = '', $node = null, $it = null) {
                            return reni('Duration:  (\d+ hour \( s \) and \d+ minute \( s \) )');
                        },

                        "FlightLocator" => function ($text = '', $node = null, $it = null) {
                            return reni('Confirmation: (\w+)');
                        },
                    ],
                ],

                "#Pick Up:#i" => [
                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "L";
                    },

                    "Number" => function ($text = '', $node = null, $it = null) {
                        return reni('Confirmation: (\w+)');
                    },

                    "PickupLocation" => function ($text = '', $node = null, $it = null) {
                        $name1 = node(".//*[contains(text(), 'Pick Up:')]/ancestor::tr[1]/td[2]");
                        $name2 = node(".//*[contains(text(), 'Pick Up:')]/ancestor::tr[1]/following-sibling::tr[1]/td[2]");

                        return nice("$name1 $name2");
                    },

                    "PickupDatetime" => function ($text = '', $node = null, $it = null) {
                        $q = white('Date:  (.+?\d{4})');
                        $date = ure("/$q/isu", 1);
                        $time = uberTime(1);

                        $dt = timestamp_from_format($date, 'dMY|');
                        $dt = strtotime($time, $dt);

                        return $dt;
                    },

                    "DropoffLocation" => function ($text = '', $node = null, $it = null) {
                        $name1 = node(".//*[contains(text(), 'Drop Off:')]/ancestor::tr[1]/td[2]");
                        $name2 = node(".//*[contains(text(), 'Drop Off:')]/ancestor::tr[1]/following-sibling::tr[1]/td[2]");

                        return nice("$name1 $name2");
                    },

                    "DropoffDatetime" => function ($text = '', $node = null, $it = null) {
                        $q = white('Date:  (.+?\d{4})');
                        $date = ure("/$q/isu", 2);
                        $time = uberTime(2);

                        $dt = timestamp_from_format($date, 'dMY|');
                        $dt = strtotime($time, $dt);

                        return $dt;
                    },

                    "PickupPhone" => function ($text = '', $node = null, $it = null) {
                        return nice(node("(//*[contains(text(), 'Tel:')]/following::td[1])[1]"));
                    },

                    "DropoffPhone" => function ($text = '', $node = null, $it = null) {
                        return nice(node("(//*[contains(text(), 'Tel:')]/following::td[1])[2]"));
                    },

                    "RentalCompany" => function ($text = '', $node = null, $it = null) {
                        $s = reni('Confirmed - (.+?) Confirmation');

                        if (rew('rent', $s)) {
                            return $s;
                        }
                    },

                    "CarType" => function ($text = '', $node = null, $it = null) {
                        return nice(cell('Type:', +1));
                    },

                    "TotalCharge" => function ($text = '', $node = null, $it = null) {
                        $x = rew('Approx\. Total:  (\w+ [\d.,]+)');

                        return total($x);
                    },
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

    public function IsEmailAggregator()
    {
        return false;
    }
}
