<?php

namespace AwardWallet\Engine\klm\Email;

class It1904098 extends \TAccountCheckerExtended
{
    public $rePlain = "";
    public $rePlainRange = "";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $rePDF = "#BOARDING\s+PASS.*?\s+KLM\s+#ims";
    public $rePDFRange = "";
    public $reSubject = "#BOARDING PASS.*?\s+KLM\s+#";
    public $langSupported = "en";
    public $typesCount = "1";
    public $reFrom = "#[^\w\d]klm[^\w\d]#i";
    public $reProvider = "#[^\w\d]klm[^\w\d]#i";
    public $caseReference = "6998";
    public $isAggregator = "";
    public $xPath = "";
    public $mailFiles = "klm/it-1897228.eml, klm/it-1904098.eml, klm/it-2106713.eml, klm/it-2106714.eml, klm/it-2107236.eml, klm/it-2107396.eml, klm/it-2107399.eml";
    public $pdfRequired = "1";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    $this->date = strtotime($this->parser->getHeader("date"));

                    $text = $this->getDocument("application/pdf", "text");

                    if (!re("#BOARDING\s+PASS.*?\s+KLM\s+#ims", $text)) {
                        return null;
                    }

                    // some boarding passess also include full itinerary, process it in another parser
                    if (re("#Itinerary\s+Information#ims", $text)) {
                        return null;
                    }

                    // preserve confNo (it's only in html format)
                    $this->confNo = node("(//*[contains(text(), 'Departure date:')]/ancestor::table[1]/following-sibling::table[1]//td[5])[1]");

                    return [$this->setDocument("application/pdf", "simpletable")];
                },

                "#.*?#" => [
                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "T";
                    },

                    "RecordLocator" => function ($text = '', $node = null, $it = null) {
                        return orval(
                            re("#Confirmation number\s*:\s*([A-Z\d\-]+)#"),
                            re("#^[A-Z\d\-]+$#", $this->confNo)
                        );
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        return nodes("//*[contains(text(), 'Name')]/ancestor-or-self::td[1]/following-sibling::td[contains(.,',')][1]");
                    },

                    "AccountNumbers" => function ($text = '', $node = null, $it = null) {
                        $res = [];
                        re("#\n\s*Frequent\s+flyer\s*([^\n]+)#", function ($m) use (&$res) {
                            $res[trim($m[1])] = 1;
                        }, $text);

                        return array_values(array_unique(array_map(function ($s) {return re("#([A-Z\d]{5,})#", $s); }, array_keys($res))));
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        $nodes = xpath("//*[contains(text(), 'DEPARTURE')]/ancestor::tr[contains(., 'FROM')][1]/following-sibling::tr[not(contains(., 'OPERATED')) and contains(., ':')][position()<50]");
                        $res = [];

                        foreach ($nodes as $node) {
                            $value = node('.', $node);

                            if (re("#^[A-Z\d]{2}\d+\s+\d{2}[A-Z]{3}#i", $value)) {
                                $res[] = $node;
                            }
                        }

                        return $res;
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            $return = [
                                'AirlineName'  => re("#^([A-Z\d]{2})\s*(\d+)\s+(\d{2}[A-Z]{3})\s+([^\t]*?)\s{2,}([^\t]*?)\s+(\d+:\d+)\s+\w+\s+(\d+:\d+\s+)*([A-Z])\s+(\d+[A-Z]+)#", text($node)),
                                'FlightNumber' => re(2),
                                'DepDate'      => strtotime(re(3) . ',' . re(6), $this->date),
                                'DepName'      => isset(explode(' I ', re(4))[0]) ? nice(explode(' I ', re(4))[0]) : '',
                                'BookingClass' => re(8),
                                'Seats'        => re(9),
                            ];

                            if (!empty(nice(re(5)))) {
                                $return['ArrName'] = re(5);
                            } elseif (isset(explode(' I ', re(4))[1])) {
                                $return['ArrName'] = nice(explode(' I ', re(4))[1]);
                            }

                            return $return;
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
                        },

                        "ArrCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
                        },

                        "ArrDate" => function ($text = '', $node = null, $it = null) {
                            return MISSING_DATE;
                        },
                    ],
                ],

                "PostProcessing" => function ($text = '', $node = null, $it = null) {
                    return correctItinerary($it, true, true);
                },
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
