<?php

namespace AwardWallet\Engine\wagonlit\Email;

class It7 extends \TAccountCheckerExtended
{
    public $rePlain = "#\n[>\s*]*From\s*:[^\n]*?wagonlit|\bCWT\b#i";
    public $rePlainRange = "";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $rePDF = "";
    public $rePDFRange = "";
    public $reSubject = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $reFrom = "#wagonlit#i";
    public $reProvider = "#wagonlit#i";
    public $caseReference = "";
    public $isAggregator = "1";
    public $xPath = "";
    public $mailFiles = "wagonlit/it-7.eml";
    public $pdfRequired = "0";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    return xpath("//*[contains(text(), 'Depart:')]/ancestor::table[2]");
                },

                "#.*?#" => [
                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "T";
                    },

                    "RecordLocator" => function ($text = '', $node = null, $it = null) {
                        return cell("Confirmation Number:", +1, 0);
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        return node("(.//*[normalize-space(text())='Seat'][1]/following::tr[1]//td[last()])[last()]");
                    },

                    "Status" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Status\s+([^\n]+)\s*\(#", $this->text());
                    },

                    "ReservationDate" => function ($text = '', $node = null, $it = null) {
                        return strtotime(nice(re("#\n\s*Today's\s*Date\s*:\s*(\w+,\s+\w+\s+\d+,\s+\d+)#msi", $this->text())));
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        return xpath(".//*[contains(text(), 'Depart:')]/ancestor::table[1]");
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            return re("#\n\s*Flight\s+(\d+)#");
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            return re("#^.*?\(([A-Z]{3})\)#", cell("Depart:", +2));
                        },

                        "DepName" => function ($text = '', $node = null, $it = null) {
                            return trim(re("#^[^\n\(]+#", cell("Depart:", +2)));
                        },

                        "DepDate" => function ($text = '', $node = null, $it = null) {
                            return totime(uberDateTime(cell("Depart:", +1, 0)));
                        },

                        "ArrCode" => function ($text = '', $node = null, $it = null) {
                            return re("#^.*?\(([A-Z]{3})\)#", cell("Arrive:", +2));
                        },

                        "ArrName" => function ($text = '', $node = null, $it = null) {
                            return trim(re("#^[^\n\(]+#", cell("Arrive:", +2)));
                        },

                        "ArrDate" => function ($text = '', $node = null, $it = null) {
                            return totime(uberDateTime(cell("Arrive:", +1, 0)));
                        },

                        "AirlineName" => function ($text = '', $node = null, $it = null) {
                            return re("#^(.*?)\s+Confirmation Number#ix");
                        },

                        "Aircraft" => function ($text = '', $node = null, $it = null) {
                            return node("following::table[1]//text()[contains(., 'Equipment')]/ancestor-or-self::td[1]/following-sibling::td[1]");
                        },

                        "Cabin" => function ($text = '', $node = null, $it = null) {
                            return re("#[^\n\(]+#", cell("Class of Service:", +1, 0));
                        },

                        "BookingClass" => function ($text = '', $node = null, $it = null) {
                            return re("#\(([A-Z])\)#", cell("Class of Service:", +1, 0));
                        },

                        "Seats" => function ($text = '', $node = null, $it = null) {
                            return node("following::table[1]//*[normalize-space(text()) ='Seat'][1]/following::tr[1]/td[1]", $node, true, "#^(\d+[A-Z]+)#");
                        },

                        "Duration" => function ($text = '', $node = null, $it = null) {
                            return node("following::table[1]//text()[contains(., 'Flying Time')]/ancestor-or-self::td[1]/following-sibling::td[1]");
                        },

                        "Meal" => function ($text = '', $node = null, $it = null) {
                            return node("following::table[1]//text()[contains(., 'Meal Service')]/ancestor-or-self::td[1]/following-sibling::td[1]");
                        },

                        "Smoking" => function ($text = '', $node = null, $it = null) {
                            return node("following::table[1]//*[normalize-space(text()) ='Seat'][1]/following::tr[1]/td[1]", $node, true, "#^Non\-smoking#i") ? false : null;
                        },

                        "Stops" => function ($text = '', $node = null, $it = null) {
                            return re("#Non[\s-]*stop#i") ? 0 : null;
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
        return true;
    }
}
