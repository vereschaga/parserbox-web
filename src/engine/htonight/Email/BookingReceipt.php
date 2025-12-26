<?php

namespace AwardWallet\Engine\htonight\Email;

class BookingReceipt extends \TAccountCheckerExtended
{
    public $reFrom = "#HotelTonight#i";
    public $reProvider = "#hoteltonight\.com#i";
    public $rePlain = "#Thank\s+you\s+for\s+booking\s+with\s+HotelTonight#i";
    public $rePlainRange = "1000";
    public $typesCount = "1";
    public $langSupported = "en";
    public $reSubject = "#Your\s+HotelTonight\s+Booking\s+Receipt#i";
    public $reHtml = "";
    public $reHtmlRange = "1000";
    public $xPath = "";
    public $mailFiles = "htonight/it-1692706.eml, htonight/it-1692707.eml, htonight/it-1692708.eml";
    public $rePDF = "";
    public $rePDFRange = "";
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
                        return re('#HotelTonight\s+booking\s+ID:\s+([\w\-]+)#');
                    },

                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "R";
                    },

                    "HotelName" => function ($text = '', $node = null, $it = null) {
                        $regex = '#';
                        $regex .= 'give\s+your\s+name\s+to\s+the\s+front\s+desk\s+along\s+with\s+a\s+photo\s+ID.\s+';
                        $regex .= '(.*)\s+';
                        $regex .= '((?s).*)\s+';
                        $regex .= '\d+\s+night\s+stay';
                        $regex .= '#';

                        if (preg_match($regex, $text, $m)) {
                            return [
                                'HotelName' => $m[1],
                                'Address'   => nice($m[2], ','),
                            ];
                        }
                    },

                    "CheckInDate" => function ($text = '', $node = null, $it = null) {
                        return strtotime(re('#Check-in:\s+(.*)#'));
                    },

                    "CheckOutDate" => function ($text = '', $node = null, $it = null) {
                        return strtotime(re('#Check-out:\s+(.*)#'));
                    },

                    "GuestNames" => function ($text = '', $node = null, $it = null) {
                        return [re('#Guest\s+name:\s+(.*)#')];
                    },

                    "Rooms" => function ($text = '', $node = null, $it = null) {
                        $subj = re('#\d+\s+room#');

                        if ($subj) {
                            return (int) $subj;
                        }
                    },

                    "Cost" => function ($text = '', $node = null, $it = null) {
                        $xpath = "//td[contains(., 'Room') and not(.//td)]/following-sibling::td[1]//text()";
                        $nodes = array_values(array_filter(nodes($xpath)));

                        if (count($nodes) == 3) {
                            return [
                                'Cost'     => cost($nodes[0]),
                                'Taxes'    => cost($nodes[1]),
                                'Total'    => cost($nodes[2]),
                                'Currency' => currency($nodes[2]),
                            ];
                        }
                    },

                    "ReservationDate" => function ($text = '', $node = null, $it = null) {
                        return strtotime(re('#Booked\s+(.*)#'));
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
