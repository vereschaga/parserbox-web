<?php

namespace AwardWallet\Engine\hoggrob\Email;

class It1746188 extends \TAccountCheckerExtended
{
    public $reFrom = "#trondent#i";
    public $reProvider = "#trondent#i";
    public $rePlain = "#\n[>\s*]*From\s*:[^\n]*?trondent.com#i";
    public $rePlainRange = "";
    public $typesCount = "1";
    public $langSupported = "en";
    public $reSubject = "";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $xPath = "";
    public $mailFiles = "hoggrob/it-1746188.eml";
    public $rePDF = "";
    public $rePDFRange = "";
    public $pdfRequired = "0";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    return splitter("#\n\s*((?:Car|Hotel)\s+Information)#");
                },

                "#^Hotel#" => [
                    "ConfirmationNumber" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Confirmation\s*:\s*([A-Z\d\-]+)#");
                    },

                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "R";
                    },

                    "HotelName" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Reserved\s*:\s*([^\n]+)#");
                    },

                    "CheckInDate" => function ($text = '', $node = null, $it = null) {
                        return totime(re("#\n\s*Check-In\s*:\s*([^\n]+)#"));
                    },

                    "CheckOutDate" => function ($text = '', $node = null, $it = null) {
                        return totime(re("#\n\s*Check-Out\s*:\s*([^\n]+)#"));
                    },

                    "Address" => function ($text = '', $node = null, $it = null) {
                        return nice(glue(re("#\n\s*Address\s*:\s*(.*?)\s+Phone:#ims")));
                    },

                    "Phone" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Phone\s*:\s*([\d\(\)\-+ ]+)#");
                    },

                    "Fax" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Fax\s*:\s*([\d\(\)\-+ ]+)#");
                    },

                    "GuestNames" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Traveler\s*:\s*([^\n]+)#", $this->text());
                    },

                    "Rate" => function ($text = '', $node = null, $it = null) {
                        return re("#\s+(\w+)\s+Rate#");
                    },

                    "RoomType" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Room Type\s*:\s*([^\n]+)#");
                    },

                    "Total" => function ($text = '', $node = null, $it = null) {
                        return cost(re("#\n\s*Price\s*:.*?([A-Z]{3}\s+[\d.,]+)#"));
                    },

                    "Currency" => function ($text = '', $node = null, $it = null) {
                        return currency(re(1));
                    },
                ],

                "#^Car#" => [
                    "Number" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Confirmation\s*:\s*([A-Z\d\-]+)#");
                    },

                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "L";
                    },

                    "PickupDatetime" => function ($text = '', $node = null, $it = null) {
                        return totime(uberDateTime(1));
                    },

                    "DropoffDatetime" => function ($text = '', $node = null, $it = null) {
                        return totime(uberDateTime(2));
                    },

                    "RentalCompany" => function ($text = '', $node = null, $it = null) {
                        return [
                            'RentalCompany'   => re("#\n\s*Reserved\s*:\s*([A-Z\d ]*?)\s+([A-Z][a-z]+.+)#"),
                            'PickupLocation'  => re(2),
                            'DropoffLocation' => re(2),
                        ];
                    },

                    "CarType" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Car Size\s*:\s*(.*?)\s+Car#") . ', ' . re("#\s+Car Category\s*:\s*([^\n]+)#");
                    },

                    "RenterName" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Traveler\s*:\s*([^\n]+)#", $this->text());
                    },
                ],

                "PostProcessing" => function ($text = '', $node = null, $it = null) {
                    return correctItinerary($it, true);
                },
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
