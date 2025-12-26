<?php

namespace AwardWallet\Engine\santander\Email;

class It2072930 extends \TAccountCheckerExtended
{
    public $rePlain = "#atendimento@superbonusviagens.com.br#i";
    public $rePlainRange = "";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $rePDF = "";
    public $rePDFRange = "";
    public $reSubject = "";
    public $langSupported = "pt";
    public $typesCount = "1";
    public $reFrom = "";
    public $reProvider = "#santander#i";
    public $caseReference = "";
    public $xPath = "";
    public $mailFiles = "santander/it-2072930.eml";
    public $pdfRequired = "0";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    return [$text];
                },

                "#.*?#" => [
                    "RecordLocator" => function ($text = '', $node = null, $it = null) {
                        return node("//*[contains(text(), 'Localizador')]/ancestor-or-self::tr[1]/following-sibling::tr/td[1]");
                    },

                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "T";
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        return nodes("//*[contains(text(), 'Nome')]/ancestor-or-self::tr[1]/following-sibling::tr/td[1]");
                    },

                    "Status" => function ($text = '', $node = null, $it = null) {
                        return node("//*[contains(text(), 'Status')]/ancestor-or-self::tr[1]/following-sibling::tr[1]/td[5]");
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        return xpath("//*[contains(text(), 'Localizador')]/ancestor-or-self::tr[1]/following-sibling::tr");
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            $node = node("./td[3]");
                            $airline = re("#([^\n]+)-#", $node);
                            $flnumber = re("#-([^\n]+)#", $node);

                            return [
                                'FlightNumber' => $flnumber,
                                'AirlineName'  => trim($airline),
                            ];
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
                        },

                        "DepName" => function ($text = '', $node = null, $it = null) {
                            $node = node("./td[2]");
                            $node = re("#([^\n]+)[0-9][0-9]:#", $node);

                            return $node;
                        },

                        "DepDate" => function ($text = '', $node = null, $it = null) {
                            $node = node("./td[2]");

                            return totime(uberDatetime($node));
                        },

                        "ArrCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
                        },

                        "ArrName" => function ($text = '', $node = null, $it = null) {
                            $node = node("./td[4]");
                            $node = re("#([^\n]+)[0-9][0-9]:#", $node);

                            return $node;
                        },

                        "ArrDate" => function ($text = '', $node = null, $it = null) {
                            $node = node("./td[4]");

                            return totime(uberDatetime($node));
                        },

                        "Duration" => function ($text = '', $node = null, $it = null) {
                            $node = node("./td[5]");

                            return $node;
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
        return ["pt"];
    }
}
