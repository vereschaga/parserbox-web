<?php

namespace AwardWallet\Engine\hertz\Email;

class It1553868 extends \TAccountCheckerExtended
{
    public $rePlain = [
        ['#[>\s*]*From\s*:[^\n]*?hertz#i', 'us', ''],
        ['#alpha@hertz.com[\s>]+wrote#i', 'us', ''],
    ];
    public $reHtml = "";
    public $rePDF = "";
    public $reSubject = "";
    public $reFrom = [
        ['#hertz#i', 'us', ''],
    ];
    public $reProvider = [
        ['#hertz#i', 'us', ''],
    ];
    public $fnLanguage = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $isAggregator = "";
    public $caseReference = "";
    public $upDate = "14.01.2015, 12:34";
    public $crDate = "";
    public $xPath = "";
    public $mailFiles = "hertz/it-1553868.eml, hertz/it-1772501.eml";
    public $re_catcher = "#.*?#";
    public $pdfRequired = "0";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    $x = xpath("//*[contains(text(), 'Itinerary:')]/ancestor-or-self::td[1]");

                    if ($x && $x->length) {
                        return null;
                    } // supports only plain type

                    $text = $this->setDocument("text/plain");

                    return [$text];
                },

                "#.*?#" => [
                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "L";
                    },

                    "Number" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Confirmation Number:\s*([\w\d-]+)#");
                    },

                    "PickupLocation" => function ($text = '', $node = null, $it = null) {
                        return nice(glue(re("#\n\s*(?:Pick Up Location|Pickup Location):\s+(.*?)\s+Location Type:#ms")));
                    },

                    "PickupDatetime" => function ($text = '', $node = null, $it = null) {
                        return totime(uberDateTime(re("#(?:Pickup Time|Pick Up):\s*([^\n]+)#")));
                    },

                    "DropoffLocation" => function ($text = '', $node = null, $it = null) {
                        return nice(glue(re("#\n\s*Return Location:\s+(.*?)\s+Location Type:#ms")));
                    },

                    "DropoffDatetime" => function ($text = '', $node = null, $it = null) {
                        return totime(uberDateTime(re("#(?:Return Time|Return):\s*([^\n]+)#")));
                    },

                    "PickupPhone" => function ($text = '', $node = null, $it = null) {
                        return re("#Pick[\s-]*up Location:.*?\n\s*Phone Number:\s*([^\n]+)#ims");
                    },

                    "PickupFax" => function ($text = '', $node = null, $it = null) {
                        return re("#Pick[\s-]*up Location:.*?\n\s*Fax Number[:\s]*([^\n]+)#ims");
                    },

                    "PickupHours" => function ($text = '', $node = null, $it = null) {
                        return re("#Pick[-\s]*up Location:.*?\n\s*Hours of Operation:\s*([^\n]+)#msi");
                    },

                    "DropoffPhone" => function ($text = '', $node = null, $it = null) {
                        return re("#Return Location:.*?\n\s*Phone Number:\s*([^\n]+)#ms");
                    },

                    "DropoffHours" => function ($text = '', $node = null, $it = null) {
                        return re("#Return Location:.*?\n\s*Hours of Operation:\s*([^\n]+)#ms");
                    },

                    "DropoffFax" => function ($text = '', $node = null, $it = null) {
                        return re("#Return Location:.*?\n\s*Fax Number[:\s]*([^\n]+)#ms");
                    },

                    "RentalCompany" => function ($text = '', $node = null, $it = null) {
                        return [
                            'RentalCompany' => re("#Thanks for Traveling at the Speed of\s+([^,]+),\s*([A-Z\d,. ]+)#"),
                            'RenterName'    => re(2),
                        ];
                    },

                    "CarType" => function ($text = '', $node = null, $it = null) {
                        return [
                            'CarModel' => nice(re("#\n\s*Your Vehicle\s+([^\n]+)\s+([^\n]+)#")),
                            'CarType'  => re(2),
                        ];
                    },

                    "TotalCharge" => function ($text = '', $node = null, $it = null) {
                        return orval(
                            cost(re("#Total Approximate Charge\s+([^\n]+)#")),
                            cost(re("#\n\s*Total[^\w\d]+([\d.,]+\s*[A-Z]+)#"))
                        );
                    },

                    "Currency" => function ($text = '', $node = null, $it = null) {
                        return orval(
                            currency(re("#Total Approximate Charge\s+([^\n]+)#")),
                            currency(re("#\n\s*Total[^\w\d]+([\d.,]+\s*[A-Z]+)#"))
                        );
                    },

                    "ServiceLevel" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Service Type:\s*([^\n]+)#");
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
