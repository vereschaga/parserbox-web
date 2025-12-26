<?php

namespace AwardWallet\Engine\hertz\Email;

class It1920683 extends \TAccountCheckerExtended
{
    public $rePlain = "#\n[>\s*]*From\s*:[^\n]*?hertz#i";
    public $rePlainRange = "";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $rePDF = "";
    public $rePDFRange = "";
    public $reSubject = "#Your Hertz Reservation#";
    public $langSupported = "en";
    public $typesCount = "1";
    public $reFrom = "#hertz#i";
    public $reProvider = "#hertz#i";
    public $xPath = "";
    public $mailFiles = "hertz/it-1920683.eml";
    public $pdfRequired = "0";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    $this->date = strtotime($this->parser->getHeader("date"));

                    return [$text];
                },

                "#.*?#" => [
                    "Number" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Confirmation Number\s*:\s*([A-Z\d\-]+)#");
                    },

                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "L";
                    },

                    "PickupLocation" => function ($text = '', $node = null, $it = null) {
                        return clear("#Terminal\s+\d+#", re("#\n\s*Pick Up Location\s*:\s*([^\n]+)#"));
                    },

                    "PickupDatetime" => function ($text = '', $node = null, $it = null) {
                        return strtotime(uberDateTime(re("#\n\s*Pick Up time\s*:\s*([^\n]+)#")), $this->date);
                    },

                    "DropoffLocation" => function ($text = '', $node = null, $it = null) {
                        return clear("#Terminal\s+\d+#", re("#\n\s*Return Location\s*:\s*([^\n]+)#"));
                    },

                    "DropoffDatetime" => function ($text = '', $node = null, $it = null) {
                        return strtotime(uberDateTime(re("#\n\s*Return time\s*:\s*([^\n]+)#")), $this->date);
                    },

                    "PickupPhone" => function ($text = '', $node = null, $it = null) {
                        return re("#Pick Up Location.*?\n\s*Phone Number\s*:+\s*([\d\-\(\) +]+)#ims");
                    },

                    "PickupFax" => function ($text = '', $node = null, $it = null) {
                        return re("#Pick Up Location.*?\n\s*Fax Number\s*:+\s*([\d\-\(\) +]+)#ims");
                    },

                    "PickupHours" => function ($text = '', $node = null, $it = null) {
                        return re("#Pick Up Location.*?\n\s*Hours of Operation\s*:\s*([^\n]+)#ims");
                    },

                    "DropoffPhone" => function ($text = '', $node = null, $it = null) {
                        return re("#Return Location.*?\n\s*Phone Number\s*:\s*([\d\-\(\) +]+)#ims");
                    },

                    "DropoffHours" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Return Location.*?\n\s*Hours of Operation\s*:\s*([^\n]+)#msi");
                    },

                    "DropoffFax" => function ($text = '', $node = null, $it = null) {
                        return re("#Return Location.*?\n\s*Fax Number\s*:+\s*([\d\-\(\) +]+)#ims");
                    },

                    "CarModel" => function ($text = '', $node = null, $it = null) {
                        return [
                            'CarModel' => re("#\n\s*Your Vehicle\s*:\s*([^\n]+)\s+([^\n]+)#"),
                            'CarType'  => re(2),
                        ];
                    },

                    "RenterName" => function ($text = '', $node = null, $it = null) {
                        return trim(beautifulName(re("#\n\s*Thanks\s+(.*?), you have successfully#")));
                    },

                    "TotalCharge" => function ($text = '', $node = null, $it = null) {
                        return cost(re("#\n\s*Total Estimated Charge\s*([^\n]+)#"));
                    },

                    "Currency" => function ($text = '', $node = null, $it = null) {
                        return currency(re(1));
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
