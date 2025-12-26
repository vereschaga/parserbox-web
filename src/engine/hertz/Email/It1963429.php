<?php

namespace AwardWallet\Engine\hertz\Email;

// TODO: merge with parser Itinerary1

class It1963429 extends \TAccountCheckerExtended
{
    public $rePlain = "#(\n[>\s*]*From\s*:[^\n]*?hertz|Thank you for placing your reservation with Hertz|Hertz Reservations)#i";
    public $rePlainRange = "10000";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $rePDF = "";
    public $rePDFRange = "";
    public $reSubject = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $reFrom = "#hertz#i";
    public $reProvider = "#hertz#i";
    public $caseReference = "6681";
    public $xPath = "";
    public $mailFiles = "hertz/it-12.eml, hertz/it-13.eml, hertz/it-1963429.eml, hertz/it-23209054.eml, hertz/it-3977868.eml";
    public $pdfRequired = "0";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    return [str_replace(["\xc2\xa0", "\r"], '', $this->setDocument('plain'))];
                },

                "#.*?#" => [
                    "Number" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Your (?:reservation|confirmation) number is\s*([A-Z\d\-]+)#");
                    },

                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "L";
                    },

                    "PickupLocation" => function ($text = '', $node = null, $it = null) {
                        return
                            re("#\n\s*(?:Renting|Pickup)\s*\n\s*City\s*:\s*([^\n]+).*?\n\s*Return#ims") . ', ' .
                            re("#\n\s*(?:Renting|Pickup)\s*\n.*?\n\s*Location\s*:\s*([^\n]+).*?\n\s*Return#ims") . ', ' .
                            re("#\n\s*(?:Renting|Pickup)\s*\n.*?\n\s*Address\s*:\s*([^\n]+).*?\n\s*Return#ims");
                    },

                    "PickupDatetime" => function ($text = '', $node = null, $it = null) {
                        $time = uberDateTIme(re("#\n\s*(?:Renting|Pickup)\s*\n.*?\n\s*Date/Time\s*:\s*([^\n]+).*?\n\s*Return#ims"));
                        $time = orval(
                            totime($time),
                            totime(clear("#[APMapm]{2}\s*$#", $time))
                        );

                        return $time;
                    },

                    "DropoffLocation" => function ($text = '', $node = null, $it = null) {
                        return
                            re("#\n\s*Return\s*\n\s*City\s*:\s*([^\n]+).*?\n\s*Vehicle\s*:#ims") . ', ' .
                            re("#\n\s*Return\s*\n.*?\n\s*Location\s*:\s*([^\n]+).*?\n\s*Vehicle\s*:#ims") . ', ' .
                            re("#\n\s*Return\s*\n.*?\n\s*Address\s*:\s*([^\n]+).*?\n\s*Vehicle\s*:#ims");
                    },

                    "DropoffDatetime" => function ($text = '', $node = null, $it = null) {
                        $time = uberDateTIme(re("#\n\s*Return\s*\n.*?\n\s*Date/Time\s*:\s*([^\n]+).*?\n\s*Vehicle\s*:#ims"));
                        $time = orval(
                            totime($time),
                            totime(clear("#[APMapm]{2}\s*$#", $time))
                        );

                        return $time;
                    },

                    "PickupPhone" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*(?:Renting|Pickup)\s*\n.*?\n\s*Phone Number\s*:\s*([^\n]+).*?\n\s*Return#ims");
                    },

                    "PickupHours" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*(?:Renting|Pickup)\s*\n.*?\n\s*Location\s+Hours\s*:\s*([^\n]+).*?\n\s*Return\s*#ims");
                    },

                    "DropoffPhone" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Return\s*\n.*?\n\s*Phone Number\s*:\s*([^\n]+).*?\n\s*Vehicle\s*:#ims");
                    },

                    "DropoffHours" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Return\s*\n.*?\n\s*Location\s+Hours\s*:\s*([^\n]+).*?\n\s*Vehicle\s*:#ims");
                    },

                    "RentalCompany" => function ($text = '', $node = null, $it = null) {
                        return re("#(?:your\s+reservation|Thank you for booking)\s+with\s+(\w+)#i");
                    },

                    "CarType" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Vehicle\s*:\s*([^\n]+)#");
                    },

                    "CarModel" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Vehicle\s*:\s*[^\n]+\n\s*([^\n]+? OR\s+SIMILAR|[ ]*[A-Z\d\- \(\)]+(?=\n))#i");
                    },

                    "RenterName" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Customer Name\s*:\s*([^\n]+)#");
                    },

                    "TotalCharge" => function ($text = '', $node = null, $it = null) {
                        return cost(re("#\s+RENTAL CHARGE\s*:\s*([^\n]+)#"));
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
