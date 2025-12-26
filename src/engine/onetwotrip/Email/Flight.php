<?php

namespace AwardWallet\Engine\onetwotrip\Email;

class Flight extends \TAccountCheckerExtended
{
    public $rePlain = "#\n[>\s*]*From\s*:[^\n]*?onetwotrip#i";
    public $rePlainRange = "";
    public $reHtml = "#onetwotrip#";
    public $reHtmlRange = "";
    public $rePDF = "";
    public $rePDFRange = "";
    public $reSubject = "";
    public $langSupported = "ru";
    public $typesCount = "1";
    public $reFrom = "#onetwotrip#i";
    public $reProvider = "#onetwotrip#i";
    public $caseReference = "";
    public $isAggregator = "0";
    public $xPath = "";
    public $mailFiles = "onetwotrip/it-1667073.eml, onetwotrip/it-1694191.eml, onetwotrip/it-3327921.eml, onetwotrip/it-3331913.eml, onetwotrip/it-3332312.eml, onetwotrip/it-3351911.eml";
    public $pdfRequired = "0";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    $this->date = strtotime($this->parser->getHeader("date"));

                    return [$text];
                },

                "#.*?#" => [
                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "T";
                    },

                    "RecordLocator" => function ($text = '', $node = null, $it = null) {
                        return re("#(?:Информация по заказу|Booking No\.)\s+(\w+)#");
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        $passengers = preg_split("#(Пассажир|Passenger)\s*:?#ims", $text);
                        unset($passengers[0]);
                        $p = [];

                        foreach ($passengers as $passenger) {
                            $p[] = trim(re("#([^\n]*?),?\s*(?:БИЛЕТ|TICKET)#", $passenger));
                        }

                        return $p;
                    },

                    "EarnedAwards" => function ($text = '', $node = null, $it = null) {
                        return re("#(?:начислено|received)\s*([0-9]+)\s*(?:RUB|Bonus Points)#");
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        $trips = preg_split("#указано местное.|Flights:#ims", $text);

                        if (!isset($trips[1])) {
                            return null;
                        }
                        $trips = preg_split("#Для ознакомления|Please note#ims", $trips[1]);

                        if (!isset($trips[0])) {
                            return null;
                        }
                        $trips = preg_split("#\s*\n#ims", $trips[0]);
                        $count = count($trips);
                        unset($trips[0]);
                        unset($trips[$count - 1]);

                        return $trips;
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            return re("#-([0-9]+)\s*#");
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            $trips = explode(">", $text);

                            $depdatetime = re("#(\d+\s*\S+)\s+(\d+:\d+)#", $trips[0]) . ', ' . re(2);
                            $arrdatetime = re("#([0-9]+\s*\S+)\s+(\d+:\d+)#", $trips[1]) . ', ' . re(2);
                            $depname = re("#-[0-9]+\s+(.*?)$#", $trips[0]);
                            $arrname = re("#(.*?)\s+[0-9]+\s*[^\n]+[0-9]+:[0-9]+#", $trips[1]);

                            $depdatetime = en(mb_strtolower($depdatetime, "UTF-8"));

                            $arrdatetime = en(mb_strtolower($arrdatetime, "UTF-8"));

                            return [
                                "DepCode" => TRIP_CODE_UNKNOWN,
                                "DepName" => trim($depname),
                                "DepDate" => strtotime($depdatetime, $this->date),
                                "ArrCode" => TRIP_CODE_UNKNOWN,
                                "ArrName" => trim($arrname),
                                "ArrDate" => strtotime($arrdatetime, $this->date),
                            ];
                        },

                        "AirlineName" => function ($text = '', $node = null, $it = null) {
                            return re("#([0-9A-Z]+)-\s*#");
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
        return ["ru", "en"];
    }

    public function IsEmailAggregator()
    {
        return false;
    }
}
