<?php

namespace AwardWallet\Engine\marriott\Email;

class It2109008 extends \TAccountCheckerExtended
{
    public $rePlain = "#\n[>\s*]*From\s*:[^\n]*?[@](marriott|stayatmarriott)#i";
    public $rePlainRange = "";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $rePDF = "";
    public $rePDFRange = "";
    public $reSubject = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $reFrom = "#[@]marriott#i";
    public $reProvider = "#[@]marriott#i";
    public $caseReference = "";
    public $isAggregator = "0";
    public $xPath = "";
    public $mailFiles = "marriott/it-2109008.eml";
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
                        return "R";
                    },

                    "ConfirmationNumber" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Confirmation Number\s*:\s*([A-Z\d\-]+)#");
                    },

                    "ConfirmationNumbers" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Rewards Number\s*:\s*([^\n]+)#");
                    },

                    "HotelName" => function ($text = '', $node = null, $it = null) {
                        return [
                            'HotelName' => re("#^([^\n]+)\s+([^\n]+)#", text(xpath("//table[1]/tr[1]"))),
                            'Address'   => re(2),
                        ];
                    },

                    "CheckInDate" => function ($text = '', $node = null, $it = null) {
                        re("#\n\s*Check\-in[\s&]+Check\-out\s*:\s*(\w{3}\s+\d+)\s*\-\s*(\w{3}\s+\d+)\s*,\s*(\d{4})#");

                        return [
                            'CheckInDate'  => totime(re(1) . ',' . re(3)),
                            'CheckOutDate' => totime(re(2) . ',' . re(3)),
                        ];
                    },

                    "GuestNames" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Dear\s+([^\n,]+)#");
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

    public function IsEmailAggregator()
    {
        return false;
    }
}
