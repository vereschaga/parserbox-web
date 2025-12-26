<?php

namespace AwardWallet\Engine\indigo\Email;

class HTML extends \TAccountCheckerExtended
{
    public $reFrom = "#reservations@goIndiGo\.in#i";
    public $reProvider = "#\bgoIndiGo\b#i";
    public $rePlain = "#For any queries please contact IndiGo#i";
    public $rePlainRange = "4000";
    public $typesCount = "1";
    public $langSupported = "en";
    public $reSubject = "";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $xPath = "";
    public $mailFiles = "indigo/it-1546813.eml";
    public $rePDF = "";
    public $rePDFRange = "";
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
                        return CONFNO_UNKNOWN;
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        $xpath = "//text()[normalize-space(.) = 'Passenger Name(s)']/ancestor::table[1]/descendant::tr[position() > 1]/td[1]";
                        $passengers = nodes($xpath);
                        array_walk($passengers, function (&$value, $key) { $value = re('#\d+\.\s+(.*)\s+\(.*#', $value); });

                        return $passengers;
                    },

                    "TotalCharge" => function ($text = '', $node = null, $it = null) {
                        $subj = re('#Total\s+Fare\s*:\s+(.*)#');

                        return ['TotalCharge' => cost($subj), 'Currency' => currency($subj)];
                    },

                    "Tax" => function ($text = '', $node = null, $it = null) {
                        return cost(re('#Tax\s+&\s+Charges\s*:\s+(.*)#'));
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        $xpath = "//text()[normalize-space(.) = 'Flight Number']/ancestor::table[1]/descendant::tr[2]";

                        return $this->http->XPath->query($xpath);
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            return re("#-\s+(\d+)#", node('./td[1]'));
                        },

                        "AirlineName" => function ($text = '', $node = null, $it = null) {
                            return re("#.*\s+([A-Z\d]{2})\s+-\s+\d+#", node('./td[1]'));
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
                        },

                        "DepName" => function ($text = '', $node = null, $it = null) {
                            $res = [];

                            foreach (['Dep' => 2, 'Arr' => 3] as $key => $value) {
                                $subj = node("./td[$value]");
                                $regex = "#(?P<Name>.*)\s+on\s+\w+\s+(?P<Date>\d+\s*/\s*\d+\s*/\s*\d+)\s+at\s+(?P<Time>\d+:\d+)\s+#";

                                if (preg_match($regex, $subj, $m)) {
                                    $res[$key . 'Name'] = $m['Name'];
                                    $datetimeStr = str_replace(' ', '', $m['Date']) . ', ' . $m['Time'];
                                    $res[$key . 'Date'] = \DateTime::createFromFormat('d/m/y, G:i', $datetimeStr)->getTimestamp();
                                }
                            }

                            return $res;
                        },

                        "ArrCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
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
