<?php

namespace AwardWallet\Engine\priceline\Email;

class It2266308 extends \TAccountCheckerExtended
{
    public $rePlain = "#\n[>\s*]*From\s*:[^\n]*?priceline#i";
    public $rePlainRange = "";
    public $reHtml = "#been\s+cancel+ed.*priceline#i";
    public $reHtmlRange = "/1";
    public $rePDF = "";
    public $rePDFRange = "";
    public $reSubject = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $reFrom = "#priceline#i";
    public $reProvider = "#priceline#i";
    public $caseReference = "";
    public $isAggregator = "0";
    public $xPath = "";
    public $mailFiles = "priceline/it-2266308.eml";
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
                        return re("#hotel reservation\s*\#\s*([A-Z\d-]{4,})#ix");
                    },

                    "Status" => function ($text = '', $node = null, $it = null) {
                        return re("#Your hotel reservation\s*\#\s*[A-Z\-\d]+ has been (\w+)#ix");
                    },

                    "Cancelled" => function ($text = '', $node = null, $it = null) {
                        return re("#been\s+cancel+ed#") ? true : false;
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
