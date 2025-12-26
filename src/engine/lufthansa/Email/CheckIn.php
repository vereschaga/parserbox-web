<?php

namespace AwardWallet\Engine\lufthansa\Email;

class CheckIn extends \TAccountCheckerExtended
{
    public $reFrom = "#lufthansa#i";
    public $reProvider = "#lufthansa#i";
    public $rePlain = "#(?:Your\s+flight.*is\s+now\s+ready\s+for\s+Check-in|Ihr\s+Flug.*steht\s+jetzt\s+zum\s+Check-in\s+bereit).*Lufthansa#is";
    public $typesCount = "2";
    public $langSupported = "en,de";
    public $reSubject = "";
    public $reHtml = "";
    public $xPath = "";
    public $mailFiles = "lufthansa/it-1615957.eml, lufthansa/it-1604038.eml, lufthansa/it-1630016.eml";
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
                        return re('#Passenger:\s+(.*)#');
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        $xpath = "//text()[contains(., 'Flight number') or contains(., 'Flugnummer')]/ancestor::tr/following-sibling::tr[position() >= 2 and position() < last()]";

                        return $this->http->XPath->query($xpath);
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            if (preg_match('#(\w+?)(\d+)#', node('./td[1]'), $m)) {
                                return ['AirlineName' => $m[1], 'FlightNumber' => $m[2]];
                            }
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
                        },

                        "ArrCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
                        },

                        "DepName" => function ($text = '', $node = null, $it = null) {
                            return node('./td[2]');
                        },

                        "ArrName" => function ($text = '', $node = null, $it = null) {
                            return node('./td[3]');
                        },

                        "DepDate" => function ($text = '', $node = null, $it = null) {
                            $date = strtotime(node('./td[4]') . ', ' . re('#\d+:\d+#', node('./td[5]')));

                            return ['DepDate' => $date, 'ArrDate' => MISSING_DATE];
                        },
                    ],
                ],
            ],
        ];
    }

    public static function getEmailTypesCount()
    {
        return 2;
    }

    public static function getEmailLanguages()
    {
        return ["en", "de"];
    }
}
