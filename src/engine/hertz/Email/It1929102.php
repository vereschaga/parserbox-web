<?php

namespace AwardWallet\Engine\hertz\Email;

class It1929102 extends \TAccountCheckerExtended
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
    public $xPath = "";
    public $mailFiles = "hertz/it-1929102.eml";
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
                        return re("#confirmation Number is\s*:\s*([A-Z\d\-]+)#");
                    },

                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "L";
                    },

                    "PickupLocation" => function ($text = '', $node = null, $it = null) {
                        return nice(re("#\n\s*Renting Location\s*:\s*(.*?)\s+(?:Pickup Date|Location Type):#ims"), ',');
                    },

                    "PickupDatetime" => function ($text = '', $node = null, $it = null) {
                        return totime(uberDateTime(re("#\n\s*Pickup Date\s*:\s*([^\n]+)#")));
                    },

                    "DropoffLocation" => function ($text = '', $node = null, $it = null) {
                        return nice(re("#\n\s*Return Location\s*:\s*(.*?)\s+(?:Return Date|Location Type):#ims"), ',');
                    },

                    "DropoffDatetime" => function ($text = '', $node = null, $it = null) {
                        return totime(uberDateTime(re("#\n\s*Return Date\s*:\s*([^\n]+)#")));
                    },

                    "RentalCompany" => function ($text = '', $node = null, $it = null) {
                        return re("#The (Hertz) Corporation#i");
                    },

                    "CarModel" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Vehicle Type\s*:\s*([^\n]+)#");
                    },

                    "RenterName" => function ($text = '', $node = null, $it = null) {
                        return re("#Thank you for your reservation,\s*([^\n]+)#");
                    },

                    "PromoCode" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Promotional Coupon\s*:\s*([A-Z\d\-]+)#");
                    },

                    "TotalCharge" => function ($text = '', $node = null, $it = null) {
                        return cost(re("#\n\s*Total Approx.*?Charges\s*:\s*([^\n]+)#"));
                    },

                    "Currency" => function ($text = '', $node = null, $it = null) {
                        return currency(orval(
                            re("#\n\s*Day\s*:[^\n]*?([A-Z]{3})#"),
                            re("#A Frequent Flyer Surcharge.*?\s+([^\s]+[\d.,]+)#")
                        ));
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
