<?php

namespace AwardWallet\Engine\triprewards\Email;

class It1970445 extends \TAccountCheckerExtended
{
    public $rePlain = "#\n[>\s*]*From\s*:[^\n]*?wyn.com#i";
    public $rePlainRange = "";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $rePDF = "";
    public $rePDFRange = "";
    public $reSubject = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $reFrom = "#@wyn.com#i";
    public $reProvider = "#@wyn.com#i";
    public $caseReference = "";
    public $xPath = "";
    public $mailFiles = "triprewards/it-1970445.eml";
    public $pdfRequired = "0";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    return [$text];
                },

                "#.*?#" => [
                    "ConfirmationNumber" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*CONFIRMATION\s*\-\s*([A-Z\d\-]+)#");
                    },

                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "R";
                    },

                    "HotelName" => function ($text = '', $node = null, $it = null) {
                        $addr = re("#\n\s*\d+\s+[A-Z]{3}\s+\d+\s*\-\s*[A-Z]+\s+(.*?\s+FAX\s*\-\s*[\d\-\(\)+ ]+)#ims");
                        $addr = clear("#\n\s+#ims", $addr, "\n");
                        $addr = clear("# {3,}[^\n]+#ims", $addr);

                        return [
                            'HotelName' => nice(re("#^([^\n]+\s+[^\n]+)\s+(.*?)\s+FAX\s*\-\s*([\d\-\(\)+ ]+)#ims", $addr), ','),
                            'Address'   => nice(re(2), ','),
                            'Fax'       => re(3),
                        ];
                    },

                    "CheckInDate" => function ($text = '', $node = null, $it = null) {
                        return strtotime(re("#\n\s*(\d+\s+[A-Z]{3}\s+\d+)\s*\-\s*[A-Z]+#"));
                    },

                    "CheckOutDate" => function ($text = '', $node = null, $it = null) {
                        return strtotime(re("#\-\s*OUT\s+(\d+\s*[A-Z]{3})\s+#"), $it["CheckInDate"]);
                    },

                    "Phone" => function ($text = '', $node = null, $it = null) {
                        return re("#\s{2,}PHONE\s*\-\s*([\d\(\)\-+ ]+)#");
                    },

                    "GuestNames" => function ($text = '', $node = null, $it = null) {
                        return [re("#\n\s*NAME\s*\-\s*([^\n]+)#")];
                    },

                    "Rooms" => function ($text = '', $node = null, $it = null) {
                        return re("#(\d+)\s+ROOM#");
                    },

                    "Rate" => function ($text = '', $node = null, $it = null) {
                        return re("#\s{3,}RATE\s*\-\s*([,.\d]+)#");
                    },

                    "CancellationPolicy" => function ($text = '', $node = null, $it = null) {
                        return re("#CXL:\s*([^\n]+)#");
                    },

                    "RoomType" => function ($text = '', $node = null, $it = null) {
                        return re("#ROOM/S\s*/\s*(.*?)\s{2,}#");
                    },

                    "ReservationDate" => function ($text = '', $node = null, $it = null) {
                        return totime(re("#\n\s*DATE\s*:\s*([^\n]*?)\s{2,}#"));
                    },
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
