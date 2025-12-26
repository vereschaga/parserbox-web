<?php

namespace AwardWallet\Engine\kayak\Email;

class It1585951 extends \TAccountCheckerExtended
{
    public $rePlain = "#\n[>\s]*From\s*:\s*Your KAYAK reservation receipt#i";
    public $rePlainRange = "";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $rePDF = "";
    public $rePDFRange = "";
    public $reSubject = "#Your KAYAK reservation receipt#i";
    public $langSupported = "en";
    public $typesCount = "1";
    public $reFrom = "";
    public $reProvider = "";
    public $xPath = "";
    public $mailFiles = "kayak/it-1585951.eml";
    public $pdfRequired = "";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    return [$text];
                },

                "#.*?#" => [
                    "Number" => function ($text = '', $node = null, $it = null) {
                        return orval(
                            re("#\n\s*Your confirmation number\s*:\s*([\d\w\-]+)#"),
                            re("#\n\s*Confirmation number\s*:\s*([\d\w\-]+)#")
                        );
                    },

                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "L";
                    },

                    "PickupLocation" => function ($text = '', $node = null, $it = null) {
                        $up = filter(nodes("//*[contains(text(), 'Pick up')]/ancestor-or-self::tr[1]/following-sibling::tr[2]/td[1]//text()"));
                        $off = filter(nodes("//*[contains(text(), 'Drop off')]/ancestor-or-self::tr[1]/following-sibling::tr[2]/td[2]//text()"));

                        array_shift($up);

                        if (re("#same location#i", reset($off))) {
                            $off = $up;
                        } else {
                            array_shift($off);
                        }

                        return [
                            'PickupLocation'  => glue($up),
                            'DropoffLocation' => glue($off),
                        ];
                    },

                    "PickupDatetime" => function ($text = '', $node = null, $it = null) {
                        return strtotime(uberDateTime(node("//*[contains(text(), 'Pick up')]/ancestor-or-self::tr[1]/following-sibling::tr[1]/td[1]")));
                    },

                    "DropoffDatetime" => function ($text = '', $node = null, $it = null) {
                        return strtotime(uberDateTime(node("//*[contains(text(), 'Drop off')]/ancestor-or-self::tr[1]/following-sibling::tr[1]/td[2]")));
                    },

                    "PickupPhone" => function ($text = '', $node = null, $it = null) {
                        $text = glue(nodes("//*[contains(text(), 'Pick up')]/ancestor-or-self::tr[1]/following-sibling::tr[contains(.,'Phone')]/td[1]/node()"), "\n");

                        return [
                            'PickupPhone' => re("#\s*Phone:\s*([^\n]+)#", $text),
                            'PickupHours' => re("#Operating hours:\s*([^\n]+)#", $text),
                        ];
                    },

                    "DropoffPhone" => function ($text = '', $node = null, $it = null) {
                        $text = glue(nodes("//*[contains(text(), 'Drop off')]/ancestor-or-self::tr[1]/following-sibling::tr[contains(.,'Phone')]/td[2]/node()"), "\n");

                        return [
                            'DropoffPhone' => re("#\s*Phone:\s*([^\n]+)#", $text),
                            'DropoffHours' => re("#Operating hours:\s*([^\n]+)#", $text),
                        ];
                    },

                    "RentalCompany" => function ($text = '', $node = null, $it = null) {
                        return reset(filter(nodes("//*[contains(text(), 'Pick up')]/ancestor-or-self::tr[1]/following-sibling::tr[2]//text()")));
                    },

                    "CarType" => function ($text = '', $node = null, $it = null) {
                        $r = filter(nodes("//img[contains(@class, 'carthumb')]/ancestor::td[1]/node()"));

                        return [
                            'CarType'  => array_shift($r),
                            'CarModel' => array_shift($r),
                        ];
                    },

                    "CarImageUrl" => function ($text = '', $node = null, $it = null) {
                        return node("//img[contains(@class, 'carthumb')]/@src");
                    },

                    "RenterName" => function ($text = '', $node = null, $it = null) {
                        $subj = node('//tr[normalize-space(.) = "Renter" and not(.//tr)]/following-sibling::tr[1]');

                        return trim(clear("#(?:\s{2,})+$#", re('#^(.*?)(?:–|\n|\s{2,})#', $subj)));
                    },

                    "TotalCharge" => function ($text = '', $node = null, $it = null) {
                        return cost(cell("Rental Car Total", +2, 0));
                    },

                    "Currency" => function ($text = '', $node = null, $it = null) {
                        return currency(cell("Rental Car Total", +2, 0));
                    },

                    "TotalTaxAmount" => function ($text = '', $node = null, $it = null) {
                        return cost(cell("Taxes, Fees and Surcharges", +2, 0));
                    },

                    "ReservationDate" => function ($text = '', $node = null, $it = null) {
                        return strtotime(re("#\n\s*Reservation Created\s*:\s*([^\n]+)#"));
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
