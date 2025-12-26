<?php

namespace AwardWallet\Engine\hertz\Email;

class It2134882 extends \TAccountCheckerExtended
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
    public $isAggregator = "0";
    public $xPath = "";
    public $mailFiles = "hertz/it-2134882.eml, hertz/it-2134884.eml";
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
                        return orval(
                            re("#reference number is[:\s]*([A-Z\d-]+)#xi"),
                            CONFNO_UNKNOWN
                        );
                    },

                    "PickupLocation" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Pick-up Location\s*:\s*([^\n]+)#ix");
                    },

                    "PickupDatetime" => function ($text = '', $node = null, $it = null) {
                        return totime(uberDateTime(re("#\n\s*Pick-up Date and Time\s*:\s*([^\n]+)#ix")));
                    },

                    "DropoffLocation" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Drop-off Location\s*:\s*([^\n]+)#ix");
                    },

                    "DropoffDatetime" => function ($text = '', $node = null, $it = null) {
                        return totime(uberDateTime(re("#\n\s*Drop-off Date and Time\s*:\s*([^\n]+)#ix")));
                    },

                    "RentalCompany" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Thank you for choosing ([^\n.]+)#ix");
                    },

                    "CarType" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Vehicle\s*:\s*([^\n]+)#");
                    },

                    "RenterName" => function ($text = '', $node = null, $it = null) {
                        return re("#(?:^|\n)\s*Hello\s+([^\n,]+),#");
                    },

                    "Status" => function ($text = '', $node = null, $it = null) {
                        return re("#Your reservation has been (\w+)#ix");
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
