<?php

namespace AwardWallet\Engine\olotels\Email;

class HotelVoucher extends \TAccountCheckerExtended
{
    public $reFrom = "#olotels#i";
    public $reProvider = "#olotels#i";
    public $rePlain = "#Please\s+find\s+below.*?your\s+voucher(?:(?s).*)pleasant\s+stay,\s+The\s+Olotels\s+team#i";
    public $rePlainRange = "";
    public $typesCount = "1";
    public $langSupported = "en";
    public $reSubject = "";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $xPath = "";
    public $mailFiles = "olotels/it-1747231.eml";
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
                        return cell('REFERENCE', +1);
                    },

                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "R";
                    },

                    "HotelName" => function ($text = '', $node = null, $it = null) {
                        return cell('Hotel :', +1);
                    },

                    "CheckInDate" => function ($text = '', $node = null, $it = null) {
                        $subj = nodes('//tr[contains(., "From") and contains(., "to")]/following-sibling::tr[1]/td');

                        if ($subj) {
                            return [
                                'CheckInDate'  => strtotime(re('#\d+\s+\w+\s+\d+#', $subj[0])),
                                'CheckOutDate' => strtotime(re('#\d+\s+\w+\s+\d+#', $subj[1])),
                                'Guests'       => $subj[2],
                                'Kids'         => $subj[3],
                            ];
                        }
                    },

                    "Address" => function ($text = '', $node = null, $it = null) {
                        return cell('Address', +1);
                    },

                    "Phone" => function ($text = '', $node = null, $it = null) {
                        return cell('Phone number', +1);
                    },

                    "RoomType" => function ($text = '', $node = null, $it = null) {
                        $subj = nodes('//tr[contains(., "Type of room")]/following-sibling::tr[1]/td');

                        if ($subj) {
                            return [
                                'RoomType' => $subj[0],
                                'Rooms'    => $subj[1],
                            ];
                        }
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
