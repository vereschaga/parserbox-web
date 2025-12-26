<?php

namespace AwardWallet\Engine\ichotelsgroup\Email;

class ModificationToReservation extends \TAccountCheckerExtended
{
    public $rePlain = "#Thank\s+you\s+for\s+choosing\s+Crowne\s+Plaza\.\s+Here\s+is\s+your\s+reservation\s+(?:modification\s+)?information#i";
    public $rePlainRange = "/1";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $rePDF = "";
    public $rePDFRange = "";
    public $reSubject = "#Modification to your Crowne Plaza Reservation#i";
    public $langSupported = "en";
    public $typesCount = "1";
    public $reFrom = "#groupcampaigns@pkghlrss\.com#i";
    public $reProvider = "";
    public $caseReference = "";
    public $xPath = "";
    public $mailFiles = "";
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
                        if (preg_match_all('#Confirmation\s+Number\s+is\s*:\s+([\w\-]+)#i', $text, $m)) {
                            return [
                                "ConfirmationNumbers" => implode("/", $m[1]),
                                "ConfirmationNumber"  => $m[1][0],
                            ];
                        } else {
                            return re('#Confirmation\s+Number\s+is\s*:\s+([\w\-]+)#i');
                        }
                    },

                    "ConfirmationNumbers" => function ($text = '', $node = null, $it = null) {
                    },

                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "R";
                    },

                    "HotelName" => function ($text = '', $node = null, $it = null) {
                        if (preg_match('#Hotel\s+Information\s+(.*)\s+((?s).*?)\s+(.*)\s+Please\s+click#i', $text, $m)) {
                            return [
                                'HotelName' => $m[1],
                                'Address'   => nice($m[2], ','),
                                'Phone'     => $m[3],
                            ];
                        }
                    },

                    "CheckInDate" => function ($text = '', $node = null, $it = null) {
                        return strtotime(re('#Check-In:\s+(.*)#i'));
                    },

                    "CheckOutDate" => function ($text = '', $node = null, $it = null) {
                        return strtotime(re('#Check-Out:\s+(.*)#i'));
                    },

                    "Guests" => function ($text = '', $node = null, $it = null) {
                        return cell('Number of Guests', +1);
                    },
                    "GuestNames" => function ($text = '', $node = null, $it = null) {
                        $main = re("#Guest Name:\s+(.+)#");
                        $guests = array_filter(explode("\n", re("#Additional Guests:\s*(.+?)\s*Check-In#")));
                        array_unshift($guests, $main);

                        return $guests;
                    },

                    "Rooms" => function ($text = '', $node = null, $it = null) {
                        return cell('Number of Rooms', +1);
                    },

                    "CancellationPolicy" => function ($text = '', $node = null, $it = null) {
                        if (empty($canc = re('#Cancellations\s+made.*#'))) {
                            $canc = re("#Any reservations cancelled within.+#");
                        }

                        return $canc;
                    },

                    "RoomType" => function ($text = '', $node = null, $it = null) {
                        return cell('Room Type', +1);
                    },

                    "Total" => function ($text = '', $node = null, $it = null) {
                        return cell('Estimated Total Price', +1);
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
