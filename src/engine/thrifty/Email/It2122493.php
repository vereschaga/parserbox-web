<?php

namespace AwardWallet\Engine\thrifty\Email;

class It2122493 extends \TAccountCheckerExtended
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
    public $mailFiles = "thrifty/it-2122493.eml, thrifty/it-2125000.eml";
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
                        return re("#\n\s*Confirmation\s*\#[\s:]*([A-Z\d-]+)#");
                    },

                    "PickupLocation" => function ($text = '', $node = null, $it = null) {
                        $addr = cell("Pickup Location", +2, 0, "//text()");

                        return [
                            'PickupPhone'    => detach("#\n\s*([\(\)\d +\-]{5,})(?:\n|$)#", $addr),
                            'PickupLocation' => nice($addr, ','),
                        ];
                    },

                    "PickupDatetime" => function ($text = '', $node = null, $it = null) {
                        return totime(uberDateTime(cell("Pickup Date", +2, 0)));
                    },

                    "DropoffLocation" => function ($text = '', $node = null, $it = null) {
                        $addr = cell("Return Location", +2, 0, "//text()");

                        return [
                            'DropoffPhone'    => detach("#\n\s*([\(\)\d +\-]{5,})(?:\n|$)#", $addr),
                            'DropoffLocation' => nice($addr, ','),
                        ];
                    },

                    "DropoffDatetime" => function ($text = '', $node = null, $it = null) {
                        return totime(uberDateTime(cell("Return Date", +2, 0)));
                    },

                    "RentalCompany" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Thank you for visiting our (.*?) interactive website#ix");
                    },

                    "CarType" => function ($text = '', $node = null, $it = null) {
                        $text = text(xpath("//*[contains(text(), 'VEHICLE TYPE')]/ancestor::tr[1]/following-sibling::tr[1]/td[2]"));

                        return re("#(?:^|\n)\s*([^\n]+)#", $text);
                    },

                    "CarModel" => function ($text = '', $node = null, $it = null) {
                        return node("//*[contains(text(), 'VEHICLE TYPE')]/ancestor::tr[1]/following-sibling::tr[1]/td[3]");
                    },

                    "CarImageUrl" => function ($text = '', $node = null, $it = null) {
                        return node("//*[contains(text(), 'VEHICLE TYPE')]/ancestor::tr[1]/following-sibling::tr[1]//img[1]/@src");
                    },

                    "RenterName" => function ($text = '', $node = null, $it = null) {
                        return niceName(re("#reservation is for\s*:\s*([^\n]+)#x"));
                    },

                    "TotalCharge" => function ($text = '', $node = null, $it = null) {
                        return total(cell("Estimated Grand Total", +1, 0));
                    },

                    "TotalTaxAmount" => function ($text = '', $node = null, $it = null) {
                        return cost(cell("SALES TAX", +1, 0));
                    },

                    "Discount" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*\d+%\s+DISCOUNT\s+([^\n]+)#");
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
