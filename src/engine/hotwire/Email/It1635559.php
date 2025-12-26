<?php

namespace AwardWallet\Engine\hotwire\Email;

class It1635559 extends \TAccountCheckerExtended
{
    public $reFrom = "#hotwire#i";
    public $reProvider = "#hotwire#i";
    public $rePlain = "#\n[>\s*]*From\s*:[^\n]*?hotwire|itinerary booked on Hotwire#i";
    public $typesCount = "1";
    public $langSupported = "en";
    public $reSubject = "";
    public $reHtml = "";
    public $xPath = "";
    public $mailFiles = "hotwire/it-1635559.eml";
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
                        return re("#confirmation\s+code:\s*([\d\w\-]+)#");
                    },

                    "PickupLocation" => function ($text = '', $node = null, $it = null) {
                        $r = filter(nodes("(//img[@alt = 'carSupplierIcon']/ancestor-or-self::td[1]/following-sibling::td[1]//tr/td[1])[2]/ancestor-or-self::tr[1]/following-sibling::tr[1]/td[1]//text()"));

                        return [
                            'PickupLocation'  => reset($r),
                            'DropoffLocation' => next($r),
                        ];
                    },

                    "PickupDatetime" => function ($text = '', $node = null, $it = null) {
                        return totime(clear("#\s+at\s+#", re("#Pick up details\s+([^\n]+)#"), ", "));
                    },

                    "DropoffDatetime" => function ($text = '', $node = null, $it = null) {
                        return totime(clear("#\s+at\s+#", re("#Drop off details\s+([^\n]+)#"), ", "));
                    },

                    "RentalCompany" => function ($text = '', $node = null, $it = null) {
                        return re("#Your car rental is confirmed with\s+([^\n.]+)#");
                    },

                    "CarType" => function ($text = '', $node = null, $it = null) {
                        return node("(//img[@alt = 'carSupplierIcon']/ancestor-or-self::td[1]/following-sibling::td[1]//tr/td[1])[2]");
                    },

                    "CarModel" => function ($text = '', $node = null, $it = null) {
                        return clear("#&[\w]+;#", re("#\n\s*Models\s+([^\n]+)#"));
                    },

                    "RenterName" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Driver name\s+([^\n]+)#");
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
