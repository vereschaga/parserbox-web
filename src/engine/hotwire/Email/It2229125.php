<?php

namespace AwardWallet\Engine\hotwire\Email;

class It2229125 extends \TAccountCheckerExtended
{
    public $rePlain = "#\n[>\s*]*From\s*:[^\n]*?hotwire#i";
    public $rePlainRange = "";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $rePDF = "";
    public $rePDFRange = "";
    public $reSubject = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $reFrom = "#hotwire#i";
    public $reProvider = "#hotwire#i";
    public $caseReference = "6934";
    public $isAggregator = "0";
    public $fnDateFormat = "";
    public $xPath = "";
    public $mailFiles = "hotwire/it-2229125.eml";
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
                        return re("#reservation\s+no[.:\s]+([A-Z\d-]+)#i");
                    },

                    "PickupLocation" => function ($text = '', $node = null, $it = null) {
                        return glue(nodes("//*[contains(text(), 'must be picked up and dropped')]/ancestor::table[1]/preceding::table[1]/following-sibling::text()"));
                    },

                    "PickupDatetime" => function ($text = '', $node = null, $it = null) {
                        return totime(node("//*[contains(text(), 'Pick-up date')]/ancestor-or-self::td[1]", null, true, "#Pick-up date[:\s]+(.+)#i"));
                    },

                    "DropoffLocation" => function ($text = '', $node = null, $it = null) {
                        return $it['PickupLocation'];
                    },

                    "DropoffDatetime" => function ($text = '', $node = null, $it = null) {
                        return totime(node("//*[contains(text(), 'Drop-off date')]/ancestor-or-self::td[1]", null, true, "#Drop-off date[:\s]+(.+)#i"));
                    },

                    "CarType" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Car\s+details\s+([^\n]+)#");
                    },

                    "CarImageUrl" => function ($text = '', $node = null, $it = null) {
                        return node("//*[contains(text(), 'Car details')]/ancestor-or-self::td[1]/preceding::td[1]//img[1]/@src", null, true, "#^(?:http|/).+#i");
                    },

                    "RenterName" => function ($text = '', $node = null, $it = null) {
                        return niceName(node("//*[contains(text(), 'Driver name')]/ancestor-or-self::td[1]", null, true, "#Driver name[:\s]+(.+)#i"));
                    },

                    "TotalCharge" => function ($text = '', $node = null, $it = null) {
                        $text = text(xpath("//text()[contains(., 'Estimated car total')]/ancestor-or-self::td[1]/following-sibling::td[1]"));

                        return total(re("#\n\s*([^\n]+)$#", $text));
                    },

                    "TotalTaxAmount" => function ($text = '', $node = null, $it = null) {
                        $text = text(xpath("//text()[contains(., 'Estimated taxes and fees')]/ancestor-or-self::td[1]/following-sibling::td[1]"));

                        return cost(re("#\n\s*([^\n]+)\s+[^\n]+$#", $text));
                    },

                    "Status" => function ($text = '', $node = null, $it = null) {
                        return re("#reservation has been (\w+)#ix");
                    },

                    "ReservationDate" => function ($text = '', $node = null, $it = null) {
                        return totime(cell("Date reserved", +1, 0, "//text()[1]"));
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
