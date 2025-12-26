<?php

namespace AwardWallet\Engine\thrifty\Email;

class It2126790 extends \TAccountCheckerExtended
{
    public $rePlain = "#\n[>\s*]*From\s*:[^\n]*?thrifty#i";
    public $rePlainRange = "";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $rePDF = "";
    public $rePDFRange = "";
    public $reSubject = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $reFrom = "#thrifty#i";
    public $reProvider = "#thrifty#i";
    public $caseReference = "7027";
    public $isAggregator = "0";
    public $xPath = "";
    public $mailFiles = "thrifty/it-2126790.eml";
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
                        return "L";
                    },

                    "Number" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Reservation Number\s*([A-Z\d-]+)#");
                    },

                    "RentalCompany" => function ($text = '', $node = null, $it = null) {
                        return re("#trading as (.*?) is#ix");
                    },

                    "RenterName" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Full Name\s*:\s*([^\n]+)#");
                    },

                    "Status" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*We've (\w+) your reservation#ix");
                    },

                    "Cancelled" => function ($text = '', $node = null, $it = null) {
                        return re("#We've cancel+ed#ix") ? true : false;
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
