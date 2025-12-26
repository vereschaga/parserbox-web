<?php

namespace AwardWallet\Engine\panorama\Email;

class It2093002 extends \TAccountCheckerExtended
{
    public $rePlain = "#FlyUIA#i";
    public $rePlainRange = "";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $rePDF = "";
    public $rePDFRange = "";
    public $reSubject = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $reFrom = "#flyuia#i";
    public $reProvider = "#panorama#i";
    public $caseReference = "9257";
    public $isAggregator = "0";
    public $xPath = "";
    public $mailFiles = "";
    public $pdfRequired = "0";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    $text = $this->setDocument("application/pdf", "simpletable");

                    return [$text];
                },

                "#.*?#" => [
                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "T";
                    },

                    "RecordLocator" => function ($text = '', $node = null, $it = null) {
                        $node = re("#Номер бронирования:\s*([A-Z\d-]+)#");

                        return $node;
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        return node("//*[contains(text(), 'Пассажир')]/ancestor-or-self::tr[1]/following-sibling::tr[1]/td[5]");
                    },

                    "TotalCharge" => function ($text = '', $node = null, $it = null) {
                        $node = re("#Итоговая стоимость\s*([0-9\s*A-Z.]+)#");

                        return total(trim($node));
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        $xpath = "//*[contains(text(), 'Выполняет')]/ancestor-or-self::tr[1]";

                        return xpath($xpath);
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            $node = node("./preceding-sibling::tr[2]/td[6]");

                            $fl = re("#-([\d]+)#", $node);
                            $an = re("#([A-Z\d]+)-#", $node);

                            return [
                                'FlightNumber' => $fl,
                                'AirlineName'  => $an,
                            ];
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
                        },

                        "DepName" => function ($text = '', $node = null, $it = null) {
                            $node = node("./preceding-sibling::tr[3]/td[15]");

                            if ($node == '') {
                                $node = node("./preceding-sibling::tr[4]/td[last()]");
                            }

                            return $node;
                        },

                        "DepDate" => function ($text = '', $node = null, $it = null) {
                            $time = node("./preceding-sibling::tr[2]/td[last()]");

                            if (!re("#[0-9:]#", $time)) {
                                $time = node("./preceding-sibling::tr[1]/td[19]");
                            }
                            $day = node("./preceding-sibling::tr[4]/td[5]");
                            $date = uberDatetime($day . " " . $time);

                            return totime($date);
                        },

                        "ArrCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
                        },

                        "ArrName" => function ($text = '', $node = null, $it = null) {
                            $node = node("./preceding-sibling::tr[2]/td[last()]");

                            if (re("#[\d]#", $node)) {
                                $node = node("./preceding-sibling::tr[3]/td[last()]");
                            }

                            return $node;
                        },

                        "ArrDate" => function ($text = '', $node = null, $it = null) {
                            $time = node(".//td[last()]");
                            $day = node("./preceding-sibling::tr[4]/td[5]");
                            $date = uberDatetime($day . " " . $time);

                            return totime($date);
                        },

                        "Aircraft" => function ($text = '', $node = null, $it = null) {
                            $node = node(".//td[5]");

                            return $node;
                        },

                        "Cabin" => function ($text = '', $node = null, $it = null) {
                            $node = node("./following-sibling::tr[1]/td[5]");

                            return $node;
                        },

                        "Duration" => function ($text = '', $node = null, $it = null) {
                            $node = node("./preceding-sibling::tr[1]/td[last()-6]");

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
        return ["en"];
    }

    public function IsEmailAggregator()
    {
        return false;
    }
}
