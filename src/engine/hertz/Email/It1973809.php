<?php

namespace AwardWallet\Engine\hertz\Email;

class It1973809 extends \TAccountCheckerExtended
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
    public $caseReference = "6681";
    public $xPath = "";
    public $mailFiles = "hertz/it-1973809.eml";
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
                        return re("#Please\s+review\s+the\s+details\s+of\s+your\s+reservation\s+below[:\s]+([A-Z\d\-]+)#i");
                    },

                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "L";
                    },

                    "PickupLocation" => function ($text = '', $node = null, $it = null) {
                        return cell("Pick-Up", +3, 0);
                    },

                    "PickupDatetime" => function ($text = '', $node = null, $it = null) {
                        return totime(cell("Pick-Up", +1, 0) . ',' . cell("Pick-Up", +2, 0));
                    },

                    "DropoffLocation" => function ($text = '', $node = null, $it = null) {
                        return cell("Dropoff", +3, 0);
                    },

                    "DropoffDatetime" => function ($text = '', $node = null, $it = null) {
                        return totime(cell("Dropoff", +1, 0) . ',' . cell("Dropoff", +2, 0));
                    },

                    "RentalCompany" => function ($text = '', $node = null, $it = null) {
                        return trim(re("#\n\s*Thank you for choosing\s*([^\n]+)#"), " .,");
                    },

                    "RenterName" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Hello\s+([^\n,]+)#");
                    },

                    "Status" => function ($text = '', $node = null, $it = null) {
                        return re("#reservation\s+has\s+been\s+(\w+)#i");
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
