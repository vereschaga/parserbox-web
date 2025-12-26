<?php

namespace AwardWallet\Engine\hotwire\Email;

class It1627138 extends \TAccountCheckerExtended
{
    public $reFrom = "#hotwire#i";
    public $reProvider = "#hotwire#i";
    public $rePlain = "#\n[>\s*]*From\s*:[^\n]*?hotwire#i";
    public $typesCount = "1";
    public $langSupported = "en";
    public $reSubject = "#Car Itinerary from Hotwire#";
    public $reHtml = "";
    public $xPath = "";
    public $mailFiles = "hotwire/it-1627138.eml";
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
                        return re("#\n\s*Hertz confirmation code:\s*([\d\w\-]+)#");
                    },

                    "RentalCompany" => function ($text = '', $node = null, $it = null) {
                        return re("#car rental is confirmed with\s+(.*?)(?:\.|\n)#");
                    },

                    "PickupDatetime" => function ($text = '', $node = null, $it = null) {
                        return totime(uberDateTime(cell('Pick up details')));
                    },

                    "DropoffDatetime" => function ($text = '', $node = null, $it = null) {
                        return totime(uberDateTime(cell('Drop off details')));
                    },

                    "PickupLocation" => function ($text = '', $node = null, $it = null) {
                        return nice(glue(clear("#\s+Map\s*#", cell('Pick up details', -1))));
                    },

                    "DropoffLocation" => function ($text = '', $node = null, $it = null) {
                        return orval(
                            nice(glue(clear("#\s+Map\s*#", cell('Pick up details', +1)))), // guessed dropoff location
                            $it['PickupLocation']
                        );
                    },

                    "CarModel" => function ($text = '', $node = null, $it = null) {
                        return clear("#^Models\s+#i", cell('Models'));
                    },

                    "CarType" => function ($text = '', $node = null, $it = null) {
                        return cell('Pick up details', -1, -1);
                    },

                    "RenterName" => function ($text = '', $node = null, $it = null) {
                        return clear("#Driver name\s+#i", cell('Driver name'));
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
