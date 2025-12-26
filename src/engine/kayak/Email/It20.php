<?php

namespace AwardWallet\Engine\kayak\Email;

class It20 extends \TAccountCheckerExtended
{
    public $rePlain = "#\n[>\s]*From\s*:[^\n]*?@kayak.com#i";
    public $rePlainRange = "";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $rePDF = "";
    public $rePDFRange = "";
    public $reSubject = "#Your KAYAK#i";
    public $langSupported = "en";
    public $typesCount = "1";
    public $reFrom = "#@kayak.com#i";
    public $reProvider = "#[@.]kayak.com#i";
    public $caseReference = "";
    public $xPath = "";
    public $mailFiles = "kayak/it-1586156.eml, kayak/it-20.eml, kayak/it-2013342.eml";
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
                        $r = explode("\n", cell(["Pick up", "Pick-up"], 0, 0, "/ancestor::td[1]//img[1]/ancestor::table[1]//text()"));
                        $r = array_filter($r, function ($item) {return $item ? true : false; });

                        return [
                            'RentalCompany'  => array_shift($r),
                            'PickupLocation' => glue($r),
                        ];
                    },

                    "PickupDatetime" => function ($text = '', $node = null, $it = null) {
                        $up = cell(["Pick up", "Pick-up"], 0, 0, '/ancestor::td[1]');

                        return [
                            'PickupDatetime' => strtotime(uberDateTime($up)),
                            'PickupPhone'    => trim(re("#\s+Phone:\s*([^\n]+)#")),
                            'PickupHours'    => nice(re("#\s+Operating hours:\s*(.*?)\n{2,}#ms"), ','),
                        ];
                    },

                    "DropoffLocation" => function ($text = '', $node = null, $it = null) {
                        $r = explode("\n", cell(["Drop off", "Drop-off"], 0, 0, "/ancestor::td[1]//img[1]/ancestor::table[1]//text()"));
                        $r = array_filter($r, function ($item) { return $item ? true : false; });
                        array_shift($r);

                        if (!count($r)) {
                            $off = cell(["Drop off", "Drop-off"], 0, 0, '/ancestor::td[1]');

                            if (re("#same\s*location#i", $off)) {
                                /* this code is old: */
                                $r = explode("\n", cell(["Pick up", "Pick-up"], 0, 0, "/ancestor::td[1]//img[1]/ancestor::table[1]//text()"));
                                $r = array_filter($r, function ($item) {return $item ? true : false; });
                                array_shift($r);

                                return glue($r);
                                /* replace by this: */
                                /*return $it['PickupLocation'];*/
                            }
                        }

                        return glue($r);
                    },

                    "DropoffDatetime" => function ($text = '', $node = null, $it = null) {
                        $off = cell(["Drop off", "Drop-off"], 0, 0, '/ancestor::td[1]');

                        return [
                            'DropoffDatetime' => strtotime(uberDateTime($off)),
                            'DropoffPhone'    => trim(re("#\s+Phone:\s*([+\d+\s\(\)\-]+)#")),
                            'DropoffHours'    => nice(re("#Operating hours:.*?\s+Operating hours:\s*(.*?)\n{2,}#ms"), ','),
                        ];
                    },

                    "CarType" => function ($text = '', $node = null, $it = null) {
                        $r = filter(nodes("//img[contains(@class, 'carthumb')]/ancestor::table[1]//text()"));
                        $r = array_shift(array_filter($r, function ($item) {return $item ? true : false; }));

                        if ($r == 'Car') {
                            $r = re("#\n\s*([^\n]*?\s+or\s+similar)#");
                        }

                        $r = explode('-', $r, 2);

                        return [
                            'CarType'  => trim(reset($r)),
                            'CarModel' => trim(end($r)),
                        ];
                    },

                    "CarImageUrl" => function ($text = '', $node = null, $it = null) {
                        return node("//img[contains(@class, 'carthumb')]/@src");
                    },

                    "RenterName" => function ($text = '', $node = null, $it = null) {
                        return orval(
                            node("(//*[contains(text(), 'Renter')]/ancestor-or-self::table[1]//tr[contains(., 'Name')])[last()]/following-sibling::tr[1]/td[1]"),
                            node("//*[contains(text(), 'Hirer')]/ancestor::tr[1]/following-sibling::tr[string-length(normalize-space(.))>1][1]//tr[contains(.,'Name')][1]/following-sibling::tr[1]/td[1]")
                        );
                    },

                    "TotalCharge" => function ($text = '', $node = null, $it = null) {
                        return cost(cell(["Car Hire Total", "Rental Car Total"], +2, 0));
                    },

                    "Currency" => function ($text = '', $node = null, $it = null) {
                        return currency(cell(["Car Hire Total", "Rental Car Total"], +2, 0));
                    },

                    "TotalTaxAmount" => function ($text = '', $node = null, $it = null) {
                        return cost(cell("Taxes, Fees and Surcharges", +2, 0));
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
