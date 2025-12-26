<?php

namespace AwardWallet\Engine\orbitz\Email;

class It2371281 extends \TAccountCheckerExtended
{
    public $mailFiles = "orbitz/it-2371281.eml";

    public $rePlain = [
        ['#\n[>\s*]*From\s*:[^\n]*?orbitz#i', 'us', ''],
    ];
    public $reHtml = "";
    public $rePDF = "";
    public $reSubject = "";
    public $reFrom = [
        ['#orbitz#i', 'us', ''],
    ];
    public $reProvider = [
        ['#orbitz#i', 'us', ''],
    ];
    public $fnLanguage = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $isAggregator = "0";
    public $caseReference = "";
    public $upDate = "15.01.2015, 23:48";
    public $crDate = "15.01.2015, 23:25";
    public $xPath = "";
    public $re_catcher = "#.*?#";
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
                        return re("#\n\s*Confirmation number\s*:\s*([A-Z\d-]+)#");
                    },

                    "PickupLocation" => function ($text = '', $node = null, $it = null) {
                        $location = text(xpath("//*[normalize-space(.) = 'Car details']/ancestor::table[1]//*[normalize-space(.) = 'Location']/ancestor::td[1]"));
                        re("#^Location\s+(.*?)\n\s*Address\s+(.*?)\n\s*([\d\-\(\)+ ]+)\s*\n\s*([\d\-\(\)+ ]+)\s*$#is", $location);

                        return [
                            "PickupLocation" => nice(re(1) . ',' . re(2)),
                            "PickupPhone"    => re(3),
                            "PickupFax"      => re(4),
                        ];
                    },

                    "PickupDatetime" => function ($text = '', $node = null, $it = null) {
                        return totime(uberDateTime(re("#\n\s*([^\n]+)\s+Pick[\s-]+Up#")));
                    },

                    "DropoffLocation" => function ($text = '', $node = null, $it = null) {
                        return re("#same as pick[\s-]+up#ix") ? $it["PickupLocation"] : null;
                    },

                    "DropoffDatetime" => function ($text = '', $node = null, $it = null) {
                        return totime(re("#\n\s*([^\n]+)\s+Drop off#is"));
                    },

                    "DropoffPhone" => function ($text = '', $node = null, $it = null) {
                        return re("#same as pick[\s-]+up#ix") ? $it["PickupPhone"] : null;
                    },

                    "DropoffFax" => function ($text = '', $node = null, $it = null) {
                        return re("#same as pick[\s-]+up#ix") ? $it["PickupFax"] : null;
                    },

                    "RentalCompany" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Pick\s+up\n\s*([^\n]+)\s+Location\s*\n#i");
                    },

                    "CarModel" => function ($text = '', $node = null, $it = null) {
                        return [
                            'CarModel' => re("#\n\s*([^\n]*?\(or\s+similar\))\s+([^\n]+)#"),
                            'CarType'  => re(2),
                        ];
                    },

                    "RenterName" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Primary driver\s*:\s*([^\n]+)#");
                    },

                    "TotalCharge" => function ($text = '', $node = null, $it = null) {
                        return total(re("#\n\s*Total car rental estimate\s+([^\n]+)#"));
                    },

                    "TotalTaxAmount" => function ($text = '', $node = null, $it = null) {
                        return cost(re("#\n\s*Taxes and fees\s+([^\n]+)#"));
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
