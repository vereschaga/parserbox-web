<?php

namespace AwardWallet\Engine\mileageplus\Email;

class YourUnitedFlightConfirmationPlainText extends \TAccountCheckerExtended
{
    public $rePlain = "#Thank\s+you\s+for\s+choosing\s+United!\s+Your\s+ticket\s*\(s\)\s+have\s+been\s+issued\s+as\s+an\s+E-Ticket#i";
    public $rePlainRange = "/1";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $rePDF = "";
    public $rePDFRange = "";
    public $reSubject = "#Your\s+United\s+flight\s+confirmation#i";
    public $langSupported = "en";
    public $typesCount = "1";
    public $reFrom = "#UNITED-CONFIRMATION@UNITED\.COM#i";
    public $reProvider = "#UNITED\.COM#i";
    public $caseReference = "";
    public $isAggregator = "0";
    public $upDate = "22.12.2014, 10:58";
    public $crDate = "22.12.2014, 10:25";
    public $xPath = "";
    public $mailFiles = "mileageplus/it-2267855.eml, mileageplus/it-2267860.eml, mileageplus/it-2267867.eml";
    public $pdfRequired = "0";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    $text = $this->setDocument('plain');

                    return [$text];
                },

                "#.*?#" => [
                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "T";
                    },

                    "RecordLocator" => function ($text = '', $node = null, $it = null) {
                        return re('#Confirmation\s*\#\s*([\w\-]+)#i');
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        if (preg_match_all('#Name\s+(.*?)\s+Type\s+#i', $text, $m)) {
                            return $m[1];
                        }
                    },

                    "TotalCharge" => function ($text = '', $node = null, $it = null) {
                        return total(re('#Total:\s+(.*)#'));
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        $r = '#(?:\w+,\s+\w+\s+\d+,\s+\d{4}\s+.*|connecting\s+to)\s+[A-Z]{2}\s+\d+\s+(?:Operated\s+by:\s+.*\s+)?Depart:(?:(?s).*?Flight\s+details)#i';

                        if (preg_match_all($r, $text, $m)) {
                            return $m[0];
                        }
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            if (preg_match('#\n\s*([A-Z]{2})\s+(\d+)\s*\n#', $text, $m)) {
                                return [
                                    'FlightNumber' => $m[2],
                                    'AirlineName'  => $m[1],
                                ];
                            }
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            static $prevDateStr = null;
                            $dateStr = re('#\w+,\s+(\w+\s+\d+,\s+\d{4})#i');

                            if (!$dateStr and $prevDateStr) {
                                $dateStr = $prevDateStr;
                            }

                            if (!$dateStr) {
                                return null;
                            }

                            if ($dateStr) {
                                $prevDateStr = $dateStr;
                            }
                            $res = null;

                            foreach (['Dep' => 'Depart', 'Arr' => 'Arrive'] as $key => $value) {
                                if (preg_match('#' . $value . ':\s+([A-Z]{3})\s+(\d+:\d+(?:am|pm)?)#i', $text, $m)) {
                                    $res[$key . 'Code'] = $m[1];
                                    $res[$key . 'Date'] = strtotime($dateStr . ', ' . $m[2]);
                                }
                            }

                            return $res;
                        },

                        "Aircraft" => function ($text = '', $node = null, $it = null) {
                            return re('#\d+h\s+\d+m\s*\n\s*(.*)#');
                        },

                        "TraveledMiles" => function ($text = '', $node = null, $it = null) {
                            $tm = re('#([\d,]+)\s+miles\s+traveled#');

                            if ($tm) {
                                $tm = str_replace(',', '', $tm);
                            }

                            return $tm;
                        },

                        "BookingClass" => function ($text = '', $node = null, $it = null) {
                            if (preg_match('#Booking\s+class:\s+(\w{1,2})\s*\n\s*(Economy)#', $text, $m)) {
                                return [
                                    'BookingClass' => $m[1],
                                    'Cabin'        => $m[2],
                                ];
                            }
                        },

                        "Seats" => function ($text = '', $node = null, $it = null) {
                            return re('#\n\s*(\d+[A-Z])\s*\n#');
                        },

                        "Duration" => function ($text = '', $node = null, $it = null) {
                            return re('#\n\s*(\d+h\s+\d+m)\s*\n#');
                        },

                        "Meal" => function ($text = '', $node = null, $it = null) {
                            return re('#No\s+Meal\s+Service|Food\s+for\s+Purchase#');
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
