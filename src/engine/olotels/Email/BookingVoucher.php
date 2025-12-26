<?php

namespace AwardWallet\Engine\olotels\Email;

class BookingVoucher extends \TAccountCheckerExtended
{
    public $reFrom = "#olotels#i";
    public $reProvider = "#olotels#i";
    public $rePlain = "#CLIENT\s+VOUCHER\s+Guaranteed\s+by\s+JUMBO#i";
    public $rePlainRange = "";
    public $typesCount = "1";
    public $langSupported = "en";
    public $reSubject = "";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $xPath = "";
    public $mailFiles = "olotels/it-1747234.eml";
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
                        return cell('REFERENCE:', +1);
                    },

                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "R";
                    },

                    "HotelName" => function ($text = '', $node = null, $it = null) {
                        if (preg_match('#^(.*?),\s+(.*)$#', cell('Hotel:', +1), $m)) {
                            return [
                                'HotelName' => $m[1],
                                'Address'   => $m[2],
                            ];
                        }
                    },

                    "CheckInDate" => function ($text = '', $node = null, $it = null) {
                        $res = null;

                        foreach (['CheckIn' => 'Check-in date', 'CheckOut' => 'Check-out date'] as $key => $value) {
                            $res[$key . 'Date'] = strtotime(re('#\d+\s+\w+\s+\d+#', cell($value, +1)));
                        }

                        return $res;
                    },

                    "Phone" => function ($text = '', $node = null, $it = null) {
                        return cell('Phone number:', +1);
                    },

                    "Rooms" => function ($text = '', $node = null, $it = null) {
                        $xpath = '//td[contains(., "Type of room")]/following-sibling::td[1]//text()';
                        $roomInfoNodes = array_values(array_filter(nodes($xpath)));
                        $roomsCount = 0;
                        $rooms = [];
                        $guestNames = [];

                        foreach ($roomInfoNodes as $n) {
                            if (preg_match('#(\d+)\s*x\s*(.*)#', $n, $m)) {
                                $roomsCount += $m[1];
                                $rooms[] = $m[2];
                            } else {
                                $guestNames[] = $n;
                            }
                        }

                        return [
                            'GuestNames' => $guestNames,
                            'Guests'     => count($guestNames),
                            'Rooms'      => $roomsCount,
                            'RoomType'   => implode('|', $rooms),
                        ];
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
