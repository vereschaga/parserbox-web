<?php

namespace AwardWallet\Engine\opentable\Email;

class It1638161 extends \TAccountCheckerExtended
{
    public $rePlain = "#noreply@opentable\.com#i";
    public $rePlainRange = "/1";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $rePDF = "";
    public $rePDFRange = "";
    public $reSubject = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $reFrom = "#noreply@opentable\.com#i";
    public $reProvider = "#opentable#i";
    public $caseReference = "";
    public $xPath = "";
    public $mailFiles = "opentable/it-1638161.eml";
    public $pdfRequired = "0";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    return [$text];
                },

                "#.*?#" => [
                    "ConfNo" => function ($text = '', $node = null, $it = null) {
                        return CONFNO_UNKNOWN;
                    },

                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "E";
                    },

                    "Name" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*(.*?)\s*\n.*?\n.*?\n.*?for (\d+) people#");
                    },

                    "StartDate" => function ($text = '', $node = null, $it = null) {
                        return totime(re("#(\n.*?\n.*?)for (\d+) people#"));
                    },

                    "Address" => function ($text = '', $node = null, $it = null) {
                        return node('(//td[@colspan="4"])[6]', null, false, '/(.*?)\s*\(/ims');
                    },

                    "Phone" => function ($text = '', $node = null, $it = null) {
                        return node('(//td[@colspan="4"])[6]', null, false, '/\([\d-\s\)]+/ims');
                    },

                    "DinerName" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*(.*?)\s*has invited you to dine#");
                    },

                    "Guests" => function ($text = '', $node = null, $it = null) {
                        return re("#for (\d+) people#");
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
