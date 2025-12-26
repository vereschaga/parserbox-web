<?php

namespace AwardWallet\Engine\vayama\Email;

class It1958689 extends \TAccountCheckerExtended
{
    public $rePlain = "#\n[>\s*]*From\s*:[^\n]*?vayama#i";
    public $rePlainRange = "";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $rePDF = "";
    public $rePDFRange = "";
    public $reSubject = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $reFrom = "#vayama#i";
    public $reProvider = "#vayama#i";
    public $caseReference = "";
    public $xPath = "";
    public $mailFiles = "vayama/it-1958689.eml";
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
                        return re("#Trip ID\s*:\s*([A-Z\d\-]+)#");
                    },

                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "T";
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        return [re("#\n\s*Dear\s+([^\n,]+)#")];
                    },

                    "Cancelled" => function ($text = '', $node = null, $it = null) {
                        return re("#not able to complete#") ? true : false;
                    },

                    "Status" => function ($text = '', $node = null, $it = null) {
                        return re("#booking has been\s+(\w+)#");
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
