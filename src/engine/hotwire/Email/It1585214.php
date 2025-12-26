<?php

namespace AwardWallet\Engine\hotwire\Email;

class It1585214 extends \TAccountCheckerExtended
{
    public $rePlain = [
        ['#\n[>\s*]*From\s*:[^\n]*?hotwire#i', 'us', ''],
    ];
    public $reHtml = [
        ['#From: Hotwire Customer#i', 'us', ''],
    ];
    public $rePDF = "";
    public $reSubject = [
        ['#Hotwire\s+Car\s+Purchase\s+Confirmation#i', 'blank', ''],
    ];
    public $reFrom = [
        ['#hotwire#i', 'us', ''],
    ];
    public $reProvider = [
        ['#hotwire#i', 'us', ''],
    ];
    public $fnLanguage = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $isAggregator = "";
    public $caseReference = "";
    public $upDate = "30.04.2015, 14:09";
    public $crDate = "";
    public $xPath = "";
    public $mailFiles = "hotwire/it-1585214.eml, hotwire/it-1693320.eml, hotwire/it-1698426.eml, hotwire/it-2663000.eml, hotwire/it-2663243.eml, hotwire/it-4.eml, hotwire/it-6.eml, hotwire/it-8.eml";
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
                        return re("#confirmation code:\s*([\d\w\-]+)#");
                    },

                    "PickupLocation" => function ($text = '', $node = null, $it = null) {
                        $text = text(xpath("//*[contains(text(), 'Pick-up details') or contains(text(), 'Pick up details')]/ancestor-or-self::td[1]"));
                        $location = $date = null;
                        $r = '#Pick[\-\s]+up\s+details\s+(.*?)\s*\w+\s*,\s*(\w+\s+\d+,\s*\d{4})\s+at\s+(\d+:\d+\s*[A-Z]{2})#msi';

                        if (preg_match($r, $text, $m)) {
                            $location = nice(glue($m[1]));
                            $date = totime($m[2] . ', ' . $m[3]);
                        }

                        if (!$location) {
                            $location = text(xpath("//*[contains(text(), 'Pick-up details') or contains(text(), 'Pick up details')]/ancestor-or-self::td[1]/preceding-sibling::td[contains(.,'Map')]"));
                            $location = nice(glue(clear("#\s+Map\s*#", $location)));
                        }

                        return [
                            'PickupLocation' => $location,
                            'PickupDatetime' => $date,
                        ];
                    },

                    "DropoffLocation" => function ($text = '', $node = null, $it = null) {
                        $text = text(xpath("//*[contains(text(), 'Drop-off details') or contains(text(), 'Drop off details')]/ancestor-or-self::td[1]"));
                        $location = $date = null;
                        $r = '#Drop[\-\s]+off\s+details\s+(.*?)\s*\w+\s*,\s*(\w+\s+[\d\s]+,\s*\d{4})\s+at\s+(\d+:\d+\s*[A-Z]{2})#msi';

                        if (preg_match($r, $text, $m)) {
                            $location = nice(glue($m[1]));
                            $d = preg_replace('#(\d)\s+(\d)#i', '\1\2', $m[2]);
                            $t = $m[3];
                            $date = totime($d . ', ' . $t);
                        }

                        if (!$location) {
                            $location = text(xpath("//*[contains(text(), 'Pick-up details') or contains(text(), 'Pick up details')]/ancestor-or-self::td[1]/preceding-sibling::td[contains(.,'Map')]"));
                            $location = nice(glue(clear("#\s+Map\s*#", $location)));
                        }

                        return [
                            'DropoffLocation' => $location,
                            'DropoffDatetime' => $date,
                        ];
                    },

                    "RentalCompany" => function ($text = '', $node = null, $it = null) {
                        return trim(re("#([^\n]+)\s+confirmation\s+code:#"));
                    },

                    "CarType" => function ($text = '', $node = null, $it = null) {
                        return node("//*[contains(@alt, 'carSupplierIcon')]/ancestor-or-self::td[1]/following-sibling::td[1]");
                    },

                    "CarModel" => function ($text = '', $node = null, $it = null) {
                        return clear("#\s*&\w+;#", re("#\n\s*Models\s+([^\n]+)#"));
                    },

                    "RenterName" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Driver name\s+([^\n]+)#");
                    },

                    "TotalCharge" => function ($text = '', $node = null, $it = null) {
                        return cost(re("#\n\s*Estimated trip total:\s*([^\n]+)#"));
                    },

                    "Currency" => function ($text = '', $node = null, $it = null) {
                        return currency(re("#\n\s*Estimated trip total:\s*([^\n]+)#"));
                    },

                    "TotalTaxAmount" => function ($text = '', $node = null, $it = null) {
                        return cost(re("#\n\s*Estimated taxes and fees:\s*([^\n]+)#"));
                    },

                    "Status" => function ($text = '', $node = null, $it = null) {
                        return re("#Your car rental is\s+(\w+)#");
                    },

                    "ReservationDate" => function ($text = '', $node = null, $it = null) {
                        return totime(re("#\n\s*Date reserved:\s+([^\n]+)#"));
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
