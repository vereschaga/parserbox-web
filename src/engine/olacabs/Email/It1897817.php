<?php

namespace AwardWallet\Engine\olacabs\Email;

class It1897817 extends \TAccountCheckerExtended
{
    public $rePlain = "#\n[>\s*]*From\s*:[^\n]*?olacabs#i";
    public $rePlainRange = "";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $rePDF = "";
    public $rePDFRange = "";
    public $reSubject = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $reFrom = "#olacabs#i";
    public $reProvider = "#olacabs#i";
    public $xPath = "";
    public $mailFiles = "olacabs/it-1897817.eml";
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
                        return re("#\n\s*Confirmed CRN\s*([^\n]+)#");
                    },

                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "L";
                    },

                    "PickupLocation" => function ($text = '', $node = null, $it = null) {
                        $address = cell("Pickup address", +1, 0);

                        return [
                            'PickupLocation'  => $address,
                            'DropoffLocation' => $address,
                        ];
                    },

                    "PickupDatetime" => function ($text = '', $node = null, $it = null) {
                        return totime(uberdatetime(cell("Pickup time", +1, 0)));
                    },

                    "DropoffDatetime" => function ($text = '', $node = null, $it = null) {
                        return MISSING_DATE;
                    },

                    "RentalCompany" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Thank you for using \s*([A-Za-z\n]+)!#");
                    },

                    "CarType" => function ($text = '', $node = null, $it = null) {
                        return cell("Car type", +1, 0);
                    },

                    "CarModel" => function ($text = '', $node = null, $it = null) {
                        return cell("Car Details", +1, 0);
                    },

                    "RenterName" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Hi \s*([^\n]+).#");
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
