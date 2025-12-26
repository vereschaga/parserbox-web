<?php

namespace AwardWallet\Engine\hertz\Email;

class It2030781 extends \TAccountCheckerExtended
{
    public $rePlain = "#\n[>\s*]*From\s*:[^\n]*?hertz#i";
    public $rePlainRange = "";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $rePDF = "";
    public $rePDFRange = "";
    public $reSubject = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $reFrom = "#hertz#i";
    public $reProvider = "#hertz#i";
    public $caseReference = "";
    public $xPath = "";
    public $mailFiles = "hertz/it-2030781.eml";
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
                        return CONFNO_UNKNOWN; //re("#\n\s*ENTRY\s*CODE[*\s]*:\s*([^\n]+)#");
                    },

                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "L";
                    },

                    "PickupLocation" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Location\s*:\s*([^\n\[]+)#");
                    },

                    "PickupDatetime" => function ($text = '', $node = null, $it = null) {
                        return totime(clear("#\s+at\s+|([A-Z]{3}$)#", re("#\n\s*Pick\-up\s*Date/Time\s*:\s*([^\n]+)#")));
                    },

                    "DropoffLocation" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Location\s*:\s*([^\n\[]+)#");
                    },

                    "DropoffDatetime" => function ($text = '', $node = null, $it = null) {
                        return totime(clear("#\s+at\s+|([A-Z]{3}$)#", re("#\n\s*Drop-off\s+Date/Time\s*:\s*([^\n]+)#"), ' '));
                    },

                    "RentalCompany" => function ($text = '', $node = null, $it = null) {
                        return re("#Your\s+([^\n]*?)\s+vehicle\s+is#");
                    },

                    "CarModel" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Vehicle\s*:\s*([^\n]+)#");
                    },

                    "RenterName" => function ($text = '', $node = null, $it = null) {
                        return re("#(?:^|\n)\s*Hello\s+([^,\n]+),#");
                    },

                    "Status" => function ($text = '', $node = null, $it = null) {
                        return re("#vehicle is\s+(\w+)\s+for pick up#");
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
