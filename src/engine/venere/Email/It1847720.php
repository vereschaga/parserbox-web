<?php

namespace AwardWallet\Engine\venere\Email;

class It1847720 extends \TAccountCheckerExtended
{
    public $rePlain = "#\n[>\s*]*From\s*:[^\n]*?@venere[.]com#i";
    public $rePlainRange = "";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $rePDF = "";
    public $rePDFRange = "";
    public $reSubject = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $reFrom = "#[@]venere[.]com#i";
    public $reProvider = "#[@.]venere[.]com#i";
    public $xPath = "";
    public $mailFiles = "venere/it-1847720.eml";
    public $pdfRequired = "0";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    return [$text];
                },

                "#.*?#" => [
                    "ConfirmationNumber" => function ($text = '', $node = null, $it = null) {
                        return re("#RESERVATION\s*NUMBER:\s*([\w-]+)#i");
                    },

                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "R";
                    },

                    "HotelName" => function ($text = '', $node = null, $it = null) {
                        return re("#Hotel:\s*(.+?)\s*City:#is");
                    },

                    "CheckInDate" => function ($text = '', $node = null, $it = null) {
                        $date = re("#CHECK-IN\s*DATE:\s*(.+?)\s*CHECK-OUT\s*DATE:#is");

                        return strtotime(uberDateTime($date));
                    },

                    "CheckOutDate" => function ($text = '', $node = null, $it = null) {
                        $date = re("#CHECK-OUT\s*DATE:\s*(.+?)\s*NUMBER\s*OF\s*NIGHTS:#is");

                        return strtotime(uberDateTime($date));
                    },

                    "Address" => function ($text = '', $node = null, $it = null) {
                        if (preg_match('/ADDRESS:\s*(.+?)\s*CITY:\s*(.+?)\s*Hotel\s*EMAIL:/is', $text, $ms)) {
                            return "{$ms[2]}, {$ms[1]}";
                        }
                    },

                    "Phone" => function ($text = '', $node = null, $it = null) {
                        return re("#TELEPHONE:\s*(.+?)\s*FAX:#is");
                    },

                    "Fax" => function ($text = '', $node = null, $it = null) {
                        return re("#FAX:\s*(.+?)\s*-----#is");
                    },

                    "GuestNames" => function ($text = '', $node = null, $it = null) {
                        $name = re("#NAME:\s*(.+?)\s*EMAIL:#is");

                        return [nice($name)];
                    },

                    "CancellationPolicy" => function ($text = '', $node = null, $it = null) {
                        $cancel = re("#Cancellation\s*Policy\s*-\s*Penalty\s*[-]{5,}\s*(.+?)\s*-----#is");

                        return nice($cancel);
                    },

                    "RoomType" => function ($text = '', $node = null, $it = null) {
                        // though what should we do if there is a number of different rooms booked?
                        $type = re("#ROOMS\s*BOOKED:\s*(\d+\s*\d+\s*.+?)\s*Daily\s*total#is");

                        if (preg_match('/(\d+)\s*(.+?):(.+)/', $type, $ms)) {
                            return [
                                'Rooms'               => $ms[1],
                                'RoomType'            => $ms[2],
                                'RoomTypeDescription' => $ms[3],
                            ];
                        }

                        return $type;
                    },

                    "Total" => function ($text = '', $node = null, $it = null) {
                        $tot = re("#Total\s*price\s*:\s*(.+?)\s*[(]#is");

                        return total($tot, 'Total');
                    },

                    "Status" => function ($text = '', $node = null, $it = null) {
                        return re("#RESERVATION\s*STATUS:\s*(.+?)\s*CHECK-IN DATE:#is");
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
