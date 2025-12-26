<?php

namespace AwardWallet\Engine\rentacar\Email;

class It2097374 extends \TAccountCheckerExtended
{
    public $rePlain = "#\n[>\s*]*From\s*:[^\n]*?rentacar#i";
    public $rePlainRange = "";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $rePDF = "";
    public $rePDFRange = "";
    public $reSubject = "#Enterprise Car Rental Confirmation#i";
    public $langSupported = "en";
    public $typesCount = "1";
    public $reFrom = "#[@.]rentacar\.#i";
    public $reProvider = "#[@.]rentacar\.#i";
    public $caseReference = "";
    public $isAggregator = "0";
    public $xPath = "";
    public $mailFiles = "";
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
                        return re("#Your confirmation number is:\s*([A-Z\d-]+)#");
                    },

                    "PickupLocation" => function ($text = '', $node = null, $it = null) {
                        $node = cell("Pickup Location", +1);
                        $phone = re("#Phone\s*\#:\s*([^\n]+)#", $node);
                        $node = str_replace("Phone #: " . $phone, "", $node);

                        return [
                            'PickupLocation'  => $node,
                            'DropoffLocation' => $node,
                            'PickupPhone'     => $phone,
                        ];
                    },

                    "PickupDatetime" => function ($text = '', $node = null, $it = null) {
                        $node = uberDatetime(cell("Pickup Date and Time", +1));

                        return totime($node);
                    },

                    "DropoffDatetime" => function ($text = '', $node = null, $it = null) {
                        $node = uberDatetime(cell("Return Date and Time", +1));

                        return totime($node);
                    },

                    "CarType" => function ($text = '', $node = null, $it = null) {
                        return cell("Vehicle Information", +1);
                    },

                    "CarModel" => function ($text = '', $node = null, $it = null) {
                        $node = node("//*[contains(text(), 'Total Cost Estimate')]/ancestor-or-self::tr[1]/td[1]");

                        return re("#Fullsize\s*([A-Za-z\d\s*,]+\s*or similar)#", $node);
                    },

                    "RenterName" => function ($text = '', $node = null, $it = null) {
                        return node("//h2[contains(text(), 's Information')]/following-sibling::table[1]//tr[1]/td[1]");
                    },

                    "TotalCharge" => function ($text = '', $node = null, $it = null) {
                        return total(cell("Total Charges", +1));
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
