<?php

namespace AwardWallet\Engine\spg\Email;

class It2072926 extends \TAccountCheckerExtended
{
    public $rePlain = "#\n[>\s*]*From\s*:[^\n]*?starwoodhotels#i";
    public $rePlainRange = "";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $rePDF = "";
    public $rePDFRange = "";
    public $reSubject = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $reFrom = "#DoNotReply@starwoodhotels.com#i";
    public $reProvider = "#starwoodhotels#i";
    public $caseReference = "6710";
    public $isAggregator = "0";
    public $xPath = "";
    public $mailFiles = "spg/it-2072926.eml";
    public $pdfRequired = "0";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    return [$text = $this->setDocument("plain")];
                },

                "#.*?#" => [
                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "R";
                    },

                    "ConfirmationNumber" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Reservation Number\s*:\s*([A-Z\d-]+)#");
                    },

                    "HotelName" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*(.*?) is delighted to confirm#x");
                    },

                    "CheckInDate" => function ($text = '', $node = null, $it = null) {
                        return totime(re("#\s{2,}Arrival Date\s*:\s*([^\n]+)#x"));
                    },

                    "CheckOutDate" => function ($text = '', $node = null, $it = null) {
                        return totime(re("#\s{2,}Departure Date\s*:\s*([^\n]+)#"));
                    },

                    "Address" => function ($text = '', $node = null, $it = null) {
                        return nice(re("#([^\n]+\s+[^\n]+)\n\s*Phone\s*:\s*([^\n]+)#"), ',');
                    },

                    "Phone" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Phone\s*:\s*([\d-\(\) +]+)#");
                    },

                    "Fax" => function ($text = '', $node = null, $it = null) {
                        return re("#Fax\s*:\s*([\d-\(\) +]+)#");
                    },

                    "GuestNames" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Guest Name(?:\(s\))?\s+([^\n]*?)\s{2,}#x");
                    },

                    "Guests" => function ($text = '', $node = null, $it = null) {
                        return re("#\s+Number of Guests\s*:\s*(\d+)#");
                    },

                    "Rooms" => function ($text = '', $node = null, $it = null) {
                        return re("#\s+Number of Rooms\s*:\s*(\d+)#");
                    },

                    "Rate" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Daily Room Rate\s*:\s*(.*?)\s{3,}#");
                    },

                    "RoomType" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Accommodation\s*:\s*([^\n]*?)\s{2,}#");
                    },

                    "Status" => function ($text = '', $node = null, $it = null) {
                        return re("#\s+Status\s*:\s*([^\n]+)#");
                    },

                    "ReservationDate" => function ($text = '', $node = null, $it = null) {
                        return totime(re("#\s+Date\s*:\s*([^\n]+)#") . ',' . re("#\s+Time:\s*([^\n]+)#"));
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
