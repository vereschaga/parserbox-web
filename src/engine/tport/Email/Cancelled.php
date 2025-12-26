<?php

namespace AwardWallet\Engine\tport\Email;

class Cancelled extends \TAccountCheckerExtended
{
    public $rePlain = "#itinerary\s+has\s+been\s+cancelled\s+as\s+requested.*Travelport#is";
    public $rePlainRange = "1000";
    public $reHtml = "";
    public $reHtmlRange = "1000";
    public $rePDF = "";
    public $rePDFRange = "";
    public $reSubject = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $reFrom = "#OTR@travelport\.com#i";
    public $reProvider = "#OTR@travelport\.com#i";
    public $caseReference = "";
    public $isAggregator = "";
    public $xPath = "";
    public $mailFiles = "tport/it-1680989.eml";
    public $pdfRequired = "0";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    $userEmail = strtolower(re("#\n\s*To\s*:[^\n]*?([^\s@\n]+@[\w\-.]+)#"));

                    if ($userEmail) {
                        $this->parsedValue('userEmail', $userEmail);
                    }

                    return [$text];
                },

                "#Confirmation\s+Number:\s+\w{6}\s#i" => [
                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "T";
                    },

                    "RecordLocator" => function ($text = '', $node = null, $it = null) {
                        return re('#Confirmation\s+Number:\s+(\w{6})\s#');
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        return [re('#Traveler\s+Name:\s+(.*?)\s+Trip#')];
                    },

                    "Cancelled" => function ($text = '', $node = null, $it = null) {
                        return true;
                    },

                    "Status" => function ($text = '', $node = null, $it = null) {
                        return 'Cancelled';
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        return [$text];
                    },

                    "TripSegments" => [
                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
                        },

                        "DepName" => function ($text = '', $node = null, $it = null) {
                            if (preg_match('#Trip\s+Name:\s+(.*?),\s+(.*?)\s+Trip#', $text, $m)) {
                                return ['DepName' => $m[1], 'ArrName' => $m[2]];
                            }
                        },

                        "DepDate" => function ($text = '', $node = null, $it = null) {
                            $res = null;
                            $regex = '#';
                            $regex .= 'Trip\s+Dates:\s+';
                            $regex .= '\w+,\s+(?P<DepDate>\w+\s+\d+,\s+\d+)\s*-';
                            $regex .= '\w+,\s+(?P<ArrDate>\w+\s+\d+,\s+\d+)\s*';
                            $regex .= '#';

                            if (preg_match($regex, $text, $m)) {
                                foreach (['DepDate', 'ArrDate'] as $key) {
                                    $res[$key] = strtotime($m[$key]);
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

    public function IsEmailAggregator()
    {
        return false;
    }
}
