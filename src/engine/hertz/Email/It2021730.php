<?php

namespace AwardWallet\Engine\hertz\Email;

class It2021730 extends \TAccountCheckerExtended
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
    public $caseReference = "";
    public $xPath = "";
    public $mailFiles = "hertz/it-2021730.eml";
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
                        return re("#\n\s*Confirmation\s+number\s+([A-Z\d\-]+)#i");
                    },

                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "L";
                    },

                    "PickupLocation" => function ($text = '', $node = null, $it = null) {
                        return clear("#Pick-up station\s*#", node("//*[contains(text(), 'Pick-up station')]/ancestor-or-self::td[1]"));
                    },

                    "PickupDatetime" => function ($text = '', $node = null, $it = null) {
                        $time = re("#\n\s*Pick-(?:u|U)p\s*([^\n]+)#");

                        if ($dr = strpos($time, 'Drop-')) {
                            $time = trim(substr($time, 0, $dr));
                        }

                        return totime($time);
                    },

                    "DropoffLocation" => function ($text = '', $node = null, $it = null) {
                        $drop = clear("#Drop-off station\s*#", node("//*[contains(text(), 'Drop-off station')]/ancestor-or-self::td[1]"));

                        if (re("#same as pick#i", $drop)) {
                            $drop = $it['PickupLocation'];
                        }

                        return $drop;
                    },

                    "DropoffDatetime" => function ($text = '', $node = null, $it = null) {
                        //						return totime(re("#\n\s*Drop-(?:o|O)ff\s*([^\n]+)#"));
                        return totime(re("#(?:\n|\s{2,})\s*Drop-(?:O|o)ff\s*(?!stat)([^\n]+)#"));
                    },

                    "PickupPhone" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Phone number\s*([\d\-\(\) +]+)#");
                    },

                    "DropoffPhone" => function ($text = '', $node = null, $it = null) {
                        $drop = re("#\n\s*Phone number.*?\n\s*Phone number\s*([\d\-\(\) +]+|same as pick)#is");

                        if (re("#same as pick#", $drop)) {
                            $drop = $it['PickupPhone'];
                        }

                        return $drop;
                    },

                    "CarType" => function ($text = '', $node = null, $it = null) {
                        $category = re("#\n\s*Car (?:c|C)ategory\s*([^\n]+)#");

                        if ($pos = strpos($category, 'Mileage')) {
                            $category = trim(substr($category, 0, $pos));
                        }

                        return $category . ', ' . re("#(?:\n|\s{2,})\s*Car (?:t|T)ype\s*([^\n]+)#");
                    },

                    "CarModel" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*([^\n]*?\s+or\s+similar)#");
                    },

                    "RenterName" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Driver information\s+([^\n]+)#");
                    },

                    "TotalCharge" => function ($text = '', $node = null, $it = null) {
                        $total = re("#\n\s*Total price to be paid\s*([^\n]+(?:\n\s*[A-Z]{3}\b)?)#");

                        if (empty($total)) {
                            $total = re("#\n\s*Total price\s*([^\n]+(?:\n\s*[A-Z]{3}\b)?)#");
                        }

                        return cost($total);
                    },

                    "Currency" => function ($text = '', $node = null, $it = null) {
                        return currency(re(1));
                    },

                    "AccountNumbers" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Miles & More card number\s*:\s*([A-Z\d\-]+)#");
                    },

                    "Status" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Confirmation (?:for|of) your#") ? 'Confirmed' : null;
                    },

                    "ReservationDate" => function ($text = '', $node = null, $it = null) {
                        return totime(re("#\n\s*Date of reservation\s+([^\n]+)#i"));
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
