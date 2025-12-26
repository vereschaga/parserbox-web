<?php

namespace AwardWallet\Engine\virgin\Email;

class It2169707 extends \TAccountCheckerExtended
{
    public $rePlain = "#\n[>\s*]*From\s*:[^\n]*?[@.]virgin-atlantic[.]com#i";
    public $rePlainRange = "";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $rePDF = "";
    public $rePDFRange = "";
    public $reSubject = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $reFrom = "#[@.]virgin-atlantic[.]com#i";
    public $reProvider = "#[@.]virgin-atlantic[.]com#i";
    public $caseReference = "";
    public $isAggregator = "0";
    public $xPath = "";
    public $mailFiles = "virgin/it-2169707.eml, virgin/it-6294513.eml";
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
                        return re_white('Your Booking Reference:	(\w+)');
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        $ppl = nodes("
							//*[contains(text(), 'Your seats')]/following::table[1]/descendant::tr/td[3]
						");
                        $ppl = filter(nice($ppl));
                        $ppl = array_values(array_unique($ppl));

                        return $ppl;
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        $q = white('
							\w+ \s+ \d+
							[\w\s]+? \(\w+\)
							\w+,
						');

                        return splitter("/($q)/isu");
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            $fl = re_white('^ (\w{2} \d+)');
                            $result = uberAir($fl);

                            if (isset($result['AirlineName']) && isset($result['FlightNumber']) && !empty($result['AirlineName']) && !empty($result['FlightNumber'])) {
                                $seats = implode(",", nodes("//*[contains(text(), 'Your seats')]/following::table[1]
								/descendant::tr/td[2][contains(.,'{$result['AirlineName']}') and contains(.,'{$result['FlightNumber']}')]/following-sibling::td[2]", null, "#Seat\s+(\d+\w)#"));

                                if (!empty($seats)) {
                                    $result['Seats'] = $seats;
                                }
                            }

                            return $result;
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            return re_white('^ .+? \( (\w+) \)');
                        },

                        "DepDate" => function ($text = '', $node = null, $it = null) {
                            $date = uberDate(1);
                            $time = uberTime(1);

                            $dt = "$date, $time";

                            return totime($dt);
                        },

                        "ArrCode" => function ($text = '', $node = null, $it = null) {
                            // second in brackets
                            return re_white('
								^ .+? \( (?:\w+) \)
								.+? \( (\w+) \)
							');
                        },

                        "ArrDate" => function ($text = '', $node = null, $it = null) {
                            $date = uberDate(2);
                            $time = uberTime(2);

                            $dt = "$date, $time";

                            return totime($dt);
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
