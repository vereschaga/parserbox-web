<?php

namespace AwardWallet\Engine\priceline\Email;

class It2190326 extends \TAccountCheckerExtended
{
    public $rePlain = "#\n[>\s*]*From\s*:[^\n]*?priceline#i";
    public $rePlainRange = "";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $rePDF = "";
    public $rePDFRange = "";
    public $reSubject = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $reFrom = "#priceline#i";
    public $reProvider = "#priceline#i";
    public $caseReference = "6701";
    public $isAggregator = "0";
    public $fnDateFormat = "";
    public $xPath = "";
    public $mailFiles = "priceline/it-2190326.eml";
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
                        return re("#Trip Number ([A-Z\d-]+)#ix");
                    },

                    "HotelName" => function ($text = '', $node = null, $it = null) {
                        return re("#has been cancelled with ([^\n.]+)\.#ix");
                    },

                    "CheckInDate" => function ($text = '', $node = null, $it = null) {
                        return [
                            'CheckInDate'  => totime(re("#for (\d+\-\w{3}\-\d{4})\s*\-\s*(\d+\-\w{3}\-\d{4})#ix")),
                            'CheckOutDate' => totime(re(2)),
                        ];
                    },

                    "GuestNames" => function ($text = '', $node = null, $it = null) {
                        return nice(re("#(?:^|\n)\s*Dear ([^\n,]+),#ix"));
                    },

                    "Total" => function ($text = '', $node = null, $it = null) {
                        return total(re("#A refund of (.*?) has been#ix"), 'Total');
                    },

                    "Status" => function ($text = '', $node = null, $it = null) {
                        return re("#has been (cancel+ed) with#ix");
                    },

                    "Cancelled" => function ($text = '', $node = null, $it = null) {
                        return re("#has been cancel+ed with#ix") ? true : false;
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
