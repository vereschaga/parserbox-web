<?php

namespace AwardWallet\Engine\thaiair\Email;

class It1736614 extends \TAccountCheckerExtended
{
    public $reFrom = "#thaiair#i";
    public $reProvider = "#thaiair#i";
    public $rePlain = "#\n[>\s*]*From\s*:[^\n]*?thaiair|^FROM\s*/TO\s+FLIGHT#i";
    public $rePlainRange = "";
    public $typesCount = "1";
    public $langSupported = "en";
    public $reSubject = "";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $xPath = "";
    public $mailFiles = "thaiair/it-1736614.eml";
    public $rePDF = "";
    public $rePDFRange = "";
    public $pdfRequired = "0";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    $this->date = strtotime($this->parser->getHeader("date"));
                    $text = $this->setDocument("plain");

                    return [$text];
                },

                "#.*?#" => [
                    "RecordLocator" => function ($text = '', $node = null, $it = null) {
                        return CONFNO_UNKNOWN;
                    },

                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "T";
                    },

                    "Tax" => function ($text = '', $node = null, $it = null) {
                        $tax = 0;

                        foreach (explode(' ', clear("#[^\d.]+#", re("#\s*TAX\s*:((?:\s*\w{3}\s*[\d,]+\w*)+)#"), ' ')) as $item) {
                            $tax += $item;
                        }

                        if (!$tax) {
                            foreach (explode(' ', clear("#[^\d.]+#", re("#\s*TAXES AND AIRLINE\s*:\s*((?:[\d,.]+[A-Z]*\s+[A-Z\s]*)+)#"), ' ')) as $item) {
                                $tax += $item;
                            }
                        }

                        return $tax;
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        $trip = re("#FROM\s*/TO\s*FLIGHT\s*CL\s*DATE\s*DEP\s*FARE\s*BASIS\s*NVB\s*NVA\s*BAG\s*ST\s+(.+)#sm", $text);
                        $its = preg_split("#\n{2,}|\-Basic\-#ms", $trip);

                        return $its;
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            re("#\s+\b([\dA-Z]{2})\s*(\d+)\s+#");

                            return [
                                'FlightNumber' => re(2),
                                'AirlineName'  => re(1),
                            ];
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
                        },

                        "DepName" => function ($text = '', $node = null, $it = null) {
                            return trim(re("#^\s*(.*?)\s+([A-Z\d]{2}\s*\d+)\s+#"));
                        },

                        "DepDate" => function ($text = '', $node = null, $it = null) {
                            $year = date('Y', $this->date);

                            $dep = re("#(.*?)\s+([A-Z\d]{2}\s*\d+)\s+([A-Z])*\s+(\d{2}\w+)\s+(\d+[APM]*)\s+(.*?)\s+(\d{2}\w+\s+)*(\d{2}\w{3}\s+)*(\d+\w)*\s*(\w+)*#", $text, 4) . $year . ',' . re(5);
                            $arr = re(4) . $year . ',' . re("#^\s*([^\n]+)\s+(SEAT\s*:\s*(\d+\w)\s+)*ARRIVAL\s*TIME\s*:\s*(\d+[AP]*)#ms", $text, 4);

                            correctDates($dep, $arr);

                            return [
                                'DepDate' => $dep,
                                'ArrDate' => $arr,
                            ];
                        },

                        "ArrCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
                        },

                        "ArrName" => function ($text = '', $node = null, $it = null) {
                            return trim(re("#\s*([\d\w ]+)\s+(SEAT\s*:\s*\d+\w)*\s+ARRIVAL\s*TIME\s*:\s*(\d+)#ms", $text));
                        },

                        "BookingClass" => function ($text = '', $node = null, $it = null) {
                            return re("#(.*?)\s+(\w{2}\s*\d+)\s+(\w)*\s+(\d{2}\w+)\s+(\d+[AP]*)\s+([^\n]+)\s+(\d{2}\w+\s+)*(\d{2}\w+\s+)*(\d+\w)*\s*(\w+)*#", $text, 3);
                        },

                        "Seats" => function ($text = '', $node = null, $it = null) {
                            return re("#^\s*([^\n]+)\s{2,}SEAT\s*:\s*(\d+\w)\s+ARRIVAL\s*TIME\s*:\s*(\d+[AP]*)#ms", $text, 2);
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
