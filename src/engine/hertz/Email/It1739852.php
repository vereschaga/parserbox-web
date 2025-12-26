<?php

namespace AwardWallet\Engine\hertz\Email;

class It1739852 extends \TAccountCheckerExtended
{
    public $reFrom = "#hertz#i";
    public $reProvider = "#hertz#i";
    public $rePlain = "#\n[>\s*]*From\s*:[^\n]*?hertz#i";
    public $rePlainRange = "";
    public $typesCount = "1";
    public $langSupported = "en";
    public $reSubject = "";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $xPath = "";
    public $mailFiles = "";
    public $rePDF = "";
    public $rePDFRange = "";
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
                        return re("#\n\s*Confirmation Number\s*:\s*([A-Z\-\d]+)#");
                    },

                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "L";
                    },

                    "PickupDatetime" => function ($text = '', $node = null, $it = null) {
                        $info = trim(text(xpath("//*[contains(normalize-space(text()), 'Pickup Date/Time')]/ancestor::tr[1]/td[1]")));
                        $info = trim(clear("#Pickup Date/Time#", $info));

                        return [
                            'PickupDatetime' => totime(re("#^([\d\-]+)\s+at\s+([\d:]+)\s*\n(.+)#ms", $info) . "," . re(2)),
                            'PickupLocation' => nice(glue(re(3))),
                        ];
                    },

                    "DropoffDatetime" => function ($text = '', $node = null, $it = null) {
                        $info = trim(text(xpath("//*[contains(normalize-space(text()), 'Return Date/Time')]/ancestor::tr[1]/td[2]")));
                        $info = trim(clear("#Return Date/Time#", $info));

                        return [
                            'DropoffDatetime' => totime(re("#^([\d\-]+)\s+at\s+([\d:]+)\s*\n(.+)#ms", $info) . "," . re(2)),
                            'DropoffLocation' => nice(glue(re(3))),
                        ];
                    },

                    "CarType" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Class Code:\s*([^\n]+)#");
                    },

                    "CarModel" => function ($text = '', $node = null, $it = null) {
                        return reset(explode("\n", text(xpath("//img[contains(@src, '/gfx')]/ancestor-or-self::td[1]/following-sibling::*[contains(.,'Class')][1]"))));
                    },

                    "CarImageUrl" => function ($text = '', $node = null, $it = null) {
                        return node("//img[contains(@src, '/client_static')]/@src");
                    },

                    "RenterName" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Renters Name\s*:\s*([^\n]+)#");
                    },

                    "TotalCharge" => function ($text = '', $node = null, $it = null) {
                        return cost(re("#\n\s*Total\s+([A-Z]{3}\s*[\d.,]+)#"));
                    },

                    "Currency" => function ($text = '', $node = null, $it = null) {
                        return currency(re(1));
                    },

                    "TotalTaxAmount" => function ($text = '', $node = null, $it = null) {
                        return cost(re("#\n\s*VAT\s+([\d.,]+)#"));
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
