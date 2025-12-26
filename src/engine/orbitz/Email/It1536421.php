<?php

namespace AwardWallet\Engine\orbitz\Email;

class It1536421 extends \TAccountCheckerExtended
{
    public $mailFiles = "orbitz/it-1536378.eml, orbitz/it-1536421.eml";

    public $reFrom = "#orbitz#i";
    public $reProvider = "#orbitz#i";
    public $rePlain = "#\n[>\s*]*From\s*:[^\n]*?orbitz#i";
    public $typesCount = "1";
    public $langSupported = "en";
    public $reSubject = "";
    public $reHtml = "";
    public $xPath = "";
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
                        return re("#\n\s*Confirmation Number:\s*([\d\w\-]+)#i");
                    },

                    "PickupDatetime" => function ($text = '', $node = null, $it = null) {
                        return strtotime(re("/\n[\t ]*Pick-up Date\s*:\s*(.{6,}\s+\d+:\d+\s+[AP]M)/i"));
                    },

                    "PickupLocation" => function ($text = '', $node = null, $it = null) {
                        return nice(glue(
                            re("/\n[\t ]*Pick-up Location\s*:\s*(.*?)\s*\n+[\t ]*Drop-off/is")
                        ));
                    },

                    "DropoffDatetime" => function ($text = '', $node = null, $it = null) {
                        return strtotime(re("/\n[\t ]*Drop-off Date\s*:\s*(.{6,}\s+\d+:\d+\s+[AP]M)/i"));
                    },

                    "DropoffLocation" => function ($text = '', $node = null, $it = null) {
                        $off = nice(glue(
                            re("/\n[\t ]*Drop-off Location\s*:\s*(.*?)\s*(?:\n{2,}|Base rate:)/is")
                        ));

                        if (preg_match("#same as pick[\s\-]*up#i", $off)) {
                            $off = $it['PickupLocation'];
                        }

                        return $off;
                    },

                    "PickupPhone" => function ($text = '', $node = null, $it = null) {
                        return node("//th[normalize-space(text())='Pick-up']/following-sibling::*[2]", null, true, "#Phone\s*:\s*(\d+)#");
                    },

                    "DropoffPhone" => function ($text = '', $node = null, $it = null) {
                        if ($it['PickupLocation'] == $it['DropoffLocation']) {
                            return $it['PickupPhone'];
                        }

                        return node("//th[normalize-space(text())='Drop-off']/following-sibling::*[2]", null, true, "#Phone\s*:\s*(\d+)#");
                    },

                    "PickupHours" => function ($text = '', $node = null, $it = null) {
                        return node("//th[normalize-space(text())='Pick-up']/following-sibling::*[2]", null, true, "#Hours\s*:\s*(.+)#");
                    },

                    "DropoffHours" => function ($text = '', $node = null, $it = null) {
                        if ($it['PickupLocation'] == $it['DropoffLocation']) {
                            return $it['PickupHours'];
                        }

                        return node("//th[normalize-space(text())='Drop-off']/following-sibling::*[2]", null, true, "#Hours\s*:\s*(.+)#");
                    },

                    "TotalCharge" => function ($text = '', $node = null, $it = null) {
                        return cost(orval(
                            re("#\n\s*Total car rental estimate\s*([^\n]+)#i"),
                            node("//th[normalize-space(text())='Total']/following-sibling::*[1]")
                        ));
                    },

                    "Currency" => function ($text = '', $node = null, $it = null) {
                        return currency(orval(
                            re("#\n\s*Total car rental estimate\s*([^\n]+)#i"),
                            node("//th[normalize-space(text())='Total']/following-sibling::*[1]")
                        ));
                    },

                    "TotalTaxAmount" => function ($text = '', $node = null, $it = null) {
                        return cost(orval(
                            re("#\n\s*Taxes\s*&\s*fees:\s*([^\n]+)#i"),
                            node("//td[normalize-space(text())='Taxes and fees']/following-sibling::*[1]")
                        ));
                    },

                    "CarType" => function ($text = '', $node = null, $it = null) {
                        return nice(orval(
                            re("#\n\s*Car Type:\s*([^\n]+)#i"),
                            node("//th[normalize-space(text())='Pick-up']/ancestor::tr[2]/preceding-sibling::*[1]/td[1]/span")
                        ));
                    },

                    "RentalCompany" => function ($text = '', $node = null, $it = null) {
                        return orval(
                            re("#\n\s*Rental Car Company:\s*([^\n]+)#i"),
                            node("//th[normalize-space(text())='Pick-up']/ancestor::tr[2]/preceding-sibling::*[1]/td[1]/text()[1]")
                        );
                    },

                    "RenterName" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*(?:Driver|Primary driver)\s*:\s*([^\n]+)#i");
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
