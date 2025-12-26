<?php

namespace AwardWallet\Engine\perfectdrive\Email;

class It1970012 extends \TAccountCheckerExtended
{
    public $rePlain = "#\n[>\s*]*From\s*:[^\n]*?budgetgroup#i";
    public $rePlainRange = "";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $rePDF = "";
    public $rePDFRange = "";
    public $reSubject = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $reFrom = "#budgetgroup#i";
    public $reProvider = "#budgetgroup#i";
    public $caseReference = "";
    public $xPath = "";
    public $mailFiles = "perfectdrive/it-1969717.eml, perfectdrive/it-1970012.eml, perfectdrive/it-1974253.eml";
    public $pdfRequired = "0";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    return [$this->setDocument('plain')];
                },

                "#.*?#" => [
                    "Number" => function ($text = '', $node = null, $it = null) {
                        return orval(
                            re("#confirmation number is\s*([A-Z\d\-]+)#"),
                            re("#Confirmation number\s+([A-Z\d\-]+)#")
                        );
                    },

                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "L";
                    },

                    "PickupLocation" => function ($text = '', $node = null, $it = null) {
                        return nice(re("#\n\s*pick-up[\s-]+.*?[^\n]+\s+(.*?)\n(?:phone|hours|location|(?:\n\s*){2})#ims"), ',');
                    },

                    "PickupDatetime" => function ($text = '', $node = null, $it = null) {
                        return totime(re("#\n\s*pick-up[\s-]+([^\n]+)#"));
                    },

                    "DropoffLocation" => function ($text = '', $node = null, $it = null) {
                        return nice(re("#\n\s*(?:return|drop-off)[\s-]+.*?[^\n]+\s+(.*?)\n(?:car|phone|hours|location|(?:\n\s*){2})#ims"), ',');
                    },

                    "DropoffDatetime" => function ($text = '', $node = null, $it = null) {
                        return totime(re("#\n\s*(?:return|drop-off)[\s-]+([^\n]+)#"));
                    },

                    "PickupPhone" => function ($text = '', $node = null, $it = null) {
                        return trim(re("#\n\s*pick-up[\s-]+.*?\n\s*phone\s+([^\n]+)#ims"));
                    },

                    "PickupHours" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*pick-up[\s-]+.*?\n(?:location\s+)?hours\s+([^\n]+)#ims");
                    },

                    "DropoffPhone" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*drop-off[\s-]+.*?\n\s*phone\s+([^\n]+)#ims");
                    },

                    "RentalCompany" => function ($text = '', $node = null, $it = null) {
                        return re("#Thanks for renting at (\w+)#");
                    },

                    "CarType" => function ($text = '', $node = null, $it = null) {
                        $info = re("#\n\s*car[\s-]+(.*?)\s*\noptions#ims");

                        return [
                            'CarType'  => re("#[^\n]+#", $info),
                            'CarModel' => nice(re("#([^\n]+\s+or similar|[^\n]+$)#", $info)),
                        ];
                    },

                    "RenterName" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*personal information[\s-]+([^\n]+)#");
                    },

                    "TotalCharge" => function ($text = '', $node = null, $it = null) {
                        return cost(re("#\n\s*(?:rental\s+)?total\s+([^\n]+)#"));
                    },

                    "Currency" => function ($text = '', $node = null, $it = null) {
                        return currency(re(1));
                    },

                    "TotalTaxAmount" => function ($text = '', $node = null, $it = null) {
                        return cost(re("#\n\s*taxes\s+([\d.,]+)#"));
                    },

                    "Status" => function ($text = '', $node = null, $it = null) {
                        return re("#rental car is\s+(\w+)#");
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
