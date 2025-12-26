<?php

namespace AwardWallet\Engine\thaiair\Email;

class It2174234 extends \TAccountCheckerExtended
{
    public $rePlain = "#\n[>\s*]*From\s*:[^\n]*?[@.]thaismileair[.]com#i";
    public $rePlainRange = "";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $rePDF = "";
    public $rePDFRange = "";
    public $reSubject = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $reFrom = "#[@.]thaismileair[.]com#i";
    public $reProvider = "#[@.]thaismileair[.]com#i";
    public $caseReference = "";
    public $isAggregator = "0";
    public $xPath = "";
    public $mailFiles = "thaiair/it-2174234.eml";
    public $pdfRequired = "0";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    return [$text];
                },

                "#.*?#" => [
                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "T";
                    },

                    "RecordLocator" => function ($text = '', $node = null, $it = null) {
                        $x = node("//*[contains(text(), 'Booked on')]/preceding::strong[1]");

                        return nice($x);
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        $ppl = nodes("
							//*[contains(text(), 'Passenger Name')]/ancestor::tr[1]
							/following-sibling::tr/td[1]
						");
                        $ppl = array_map(function ($x) { return clear('/[(].*[)]/', $x); }, $ppl);

                        return nice($ppl);
                    },

                    "TotalCharge" => function ($text = '', $node = null, $it = null) {
                        $x = re_white('TOTAL  ([\d.,]+ \w+)');

                        return total($x);
                    },

                    "BaseFare" => function ($text = '', $node = null, $it = null) {
                        $x = re_white('Airfare  ([\d.,]+ \w+)');

                        return cost($x);
                    },

                    "ReservationDate" => function ($text = '', $node = null, $it = null) {
                        $date = between('Booked on', 'Contact');

                        return totime($date);
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        return xpath("//*[contains(text(), 'DEPARTURE')]/ancestor::tr[1]");
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            $fl = re_white('(\w+ \d+) \|');

                            return uberAir($fl);
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            return re_white('^ (\w+) - (?:\w+)');
                        },

                        "DepDate" => function ($text = '', $node = null, $it = null) {
                            $date = uberDate(1);
                            $time = uberTime(1);

                            $dt = "$date, $time";

                            return totime($dt);
                        },

                        "ArrCode" => function ($text = '', $node = null, $it = null) {
                            return re_white('^ (?:\w+) - (\w+)');
                        },

                        "ArrDate" => function ($text = '', $node = null, $it = null) {
                            $date = uberDate(2);
                            $time = uberTime(2);

                            $dt = "$date, $time";

                            return totime($dt);
                        },

                        "BookingClass" => function ($text = '', $node = null, $it = null) {
                            return re_white('Class (\w+)');
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

    public function IsEmailAggregator()
    {
        return false;
    }
}
