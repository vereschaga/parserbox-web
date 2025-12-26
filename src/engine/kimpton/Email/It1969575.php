<?php

namespace AwardWallet\Engine\kimpton\Email;

class It1969575 extends \TAccountCheckerExtended
{
    public $rePlain = "#(\n[>\s*]*From\s*:[^\n]*?@kimptonhotels[.]com|@kimptonhotels[.]com)#i";
    public $rePlainRange = "";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $rePDF = "";
    public $rePDFRange = "";
    public $reSubject = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $reFrom = "#@kimptonhotels[.]com#i";
    public $reProvider = "#[@.]kimptonhotels[.]com#i";
    public $caseReference = "7017";
    public $xPath = "";
    public $mailFiles = "kimpton/it-1969575.eml";
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
                        return re("#Cancellation\s+Number:\s*([\w-]+)#i");
                    },

                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "R";
                    },

                    "HotelName" => function ($text = '', $node = null, $it = null) {
                        $name2norm = [
                            'Hotel Vintage Seattle, a Kimpton Hotel' => 'Hotel Vintage, a Kimpton Hotel',
                            'The Prescott'                           => 'Prescott Hotel, a Kimpton Hotel',
                        ];

                        $name1 = between('Your reservation at', 'has been');

                        if (isset($name2norm[$name1])) {
                            $name1 = $name2norm[$name1];
                        }
                        $name2 = clear('/,\s*a\s+Kimpton\s+Hotel/i', $name1);
                        // two default versions and one canonical as last resort
                        return [
                            'HotelName' => orval($name1, $name2),
                            'Address'   => orval($name1, $name2),
                        ];
                    },

                    "CheckInDate" => function ($text = '', $node = null, $it = null) {
                        $date = cell('Your Arrival Date:', +1);

                        return strtotime($date);
                    },

                    "CheckOutDate" => function ($text = '', $node = null, $it = null) {
                        $date = cell('Your Departure Date:', +1);

                        return strtotime($date);
                    },

                    "Guests" => function ($text = '', $node = null, $it = null) {
                        $n = cell('Adults/Children:', +1);

                        return re('#(\d+)/#', $n);
                    },

                    "Kids" => function ($text = '', $node = null, $it = null) {
                        $n = cell('Adults/Children:', +1);

                        return re('#/(\d+)#', $n);
                    },

                    "Rooms" => function ($text = '', $node = null, $it = null) {
                        return cell('Rooms', +1);
                    },

                    "CancellationPolicy" => function ($text = '', $node = null, $it = null) {
                        $cancel = cell('Policy Information:', +1);

                        return nice($cancel);
                    },

                    "Status" => function ($text = '', $node = null, $it = null) {
                        return re('/has\s+been\s+(\w+)/i');
                    },

                    "Cancelled" => function ($text = '', $node = null, $it = null) {
                        return re("#has\s+been\s+cancel+ed#i") ? true : false;
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
