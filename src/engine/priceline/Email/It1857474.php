<?php

namespace AwardWallet\Engine\priceline\Email;

class It1857474 extends \TAccountCheckerExtended
{
    public $rePlain = "#Your Reservation Is Canceled#";
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
    public $xPath = "";
    public $mailFiles = "priceline/it-1857474.eml";
    public $pdfRequired = "0";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    return [$text];
                },

                "#.*?#" => [
                    "Number" => function ($text = '', $node = null, $it = null) {
                        return re("#([A-Z\d\-]+)\s+has\s+been\s+successfully\s+canceled#i");
                    },

                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "L";
                    },

                    "Status" => function ($text = '', $node = null, $it = null) {
                        return re("#Your\s+Reservation\s+Is\s+(\w+)#i");
                    },

                    "Cancelled" => function ($text = '', $node = null, $it = null) {
                        return re("#Your\s+Reservation\s+Is\s+Canceled#i") ? true : false;
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
