<?php

namespace AwardWallet\Engine\mileageplus\Email;

class YourUnitedFlightConfirmation2 extends \TAccountCheckerExtended
{
    public $rePlain = "#Thank\s+you\s+for\s+choosing\s+United#i";
    public $rePlainRange = "/1";
    public $reHtml = "#Thank\s+you\s+for\s+choosing\s+United#i";
    public $reHtmlRange = "/1";
    public $rePDF = "";
    public $rePDFRange = "";
    public $reSubject = "#Your\s+United\s+flight\s+confirmation#i";
    public $langSupported = "en";
    public $typesCount = "1";
    public $reFrom = "#UNITED-CONFIRMATION@UNITED\.COM#i";
    public $reProvider = "#UNITED\.COM#i";
    public $caseReference = "";
    public $isAggregator = "0";
    public $xPath = "";
    public $mailFiles = "mileageplus/it-2179817.eml, mileageplus/it-2204921.eml";
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
                        return re('#Your\s+confirmation\s+number\s+is\s*:?\s+([\w\-]+)#i');
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        return orval(
                            nodes('//tr[contains(., "Passenger information")]/following-sibling::tr/td[1]//b'),
                            nodes('//*[normalize-space(.) = "Passenger(s)"]/following-sibling::table[1]//td[normalize-space(.) = "Name"]/following-sibling::td[1]')
                        );
                    },

                    "TotalCharge" => function ($text = '', $node = null, $it = null) {
                        return total(re('#Total\s*:\s+(.*)#'));
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        return xpath('//tr[contains(., "Depart:")]');
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            if (preg_match('#(.*)\s+(\d+)#i', node('./td[1]'), $m)) {
                                return [
                                    'AirlineName'  => $m[1],
                                    'FlightNumber' => $m[2],
                                ];
                            }
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            $res = null;
                            $dateStr = re('#\w+\s+\d+,\s+\d+#i', node('./preceding-sibling::tr[last()]'));

                            if (!$dateStr) {
                                return null;
                            }

                            foreach (['Dep' => 'Depart', 'Arr' => 'Arrive'] as $key => $value) {
                                $r = '#' . $value . ':\s+(\w{3})\s+(\d+:\d+\s*(?:am|pm)?)#i';

                                if (preg_match($r, node('./td[2]'), $m)) {
                                    $res[$key . 'Code'] = $m[1];
                                    $res[$key . 'Date'] = strtotime($dateStr . ', ' . $m[2]);
                                }
                            }

                            return $res;
                        },

                        "BookingClass" => function ($text = '', $node = null, $it = null) {
                            $r = '#Booking\s+class:\s+(\w)\s+(.*?)\s+([\d,]+\s+Award\s+miles)\s+(No\s+Meal\s+Service|Food\s+for\s+Purchase)#i';

                            if (preg_match($r, node('./td[4]'), $m)) {
                                return [
                                    'BookingClass' => $m[1],
                                    'Cabin'        => $m[2],
                                    'Meal'         => strtolower($m[4]) != 'no meal service' ? $m[4] : null,
                                ];
                            }
                        },

                        "Seats" => function ($text = '', $node = null, $it = null) {
                            $s = re('#Seats\s*:\s*((?:\d+\w\s*,?)*)#i', node('./td[last()]'));

                            if ($s) {
                                return $s;
                            }
                        },

                        "Stops" => function ($text = '', $node = null, $it = null) {
                            $r = '#(Non-stop)\s+(\d+h\s+\d+m)\s+(.*)\s+([\d,]+\s+miles)#i';

                            if (preg_match($r, node('./td[3]'), $m)) {
                                return [
                                    'Stops'         => strtolower($m[1]) == 'non-stop' ? 0 : null,
                                    'Duration'      => $m[2],
                                    'Aircraft'      => $m[3],
                                    'TraveledMiles' => $m[4],
                                ];
                            }
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
