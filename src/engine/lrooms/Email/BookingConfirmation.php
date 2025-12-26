<?php

namespace AwardWallet\Engine\lrooms\Email;

class BookingConfirmation extends \TAccountCheckerExtended
{
    public $reFrom = "#@LateRooms\.com#i";
    public $reProvider = "#LateRooms\.com#i";
    public $rePlain = "#This\s+is\s+confirmation\s+of\s+your\s+booking\s+made\s+through\s+LateRooms\.com#i";
    public $rePlainRange = "";
    public $typesCount = "1";
    public $langSupported = "en";
    public $reSubject = "#LateRooms\.com\s+Booking\s+Confirmation#i";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $xPath = "";
    public $mailFiles = "lrooms/it-1690860.eml, lrooms/it-1732874.eml, lrooms/it-1745898.eml, lrooms/it-1747236.eml, lrooms/it-1829321.eml";
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
                        return re('#(?:LateRooms.com|reservation)\s+reference:\s+([\w\-]+)#i');
                    },

                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "R";
                    },

                    "HotelName" => function ($text = '', $node = null, $it = null) {
                        if (preg_match('#Hotel\s+Details:\s+(.*)\s+((?s).*)\s+Tel:\s+(.*)\s+email#i', $text, $m)) {
                            return [
                                'HotelName' => $m[1],
                                'Address'   => nice($m[2], ','),
                                'Phone'     => $m[3],
                            ];
                        } else {
                            return [
                                'HotelName' => cell("Hotel Name", +1),
                                'Address'   => cell("Hotel Name", +1),
                            ];
                        }
                    },

                    "CheckInDate" => function ($text = '', $node = null, $it = null) {
                        $CIDateStr = cell('Arrival Date:', +1);
                        $CITimeStr = orval(
                            node("//td[normalize-space(.)='Check In From:' or normalize-space(.)='Check in from:']/following-sibling::td[1]"),
                            cell('Latest Check In:', +1),
                            re("#\n\s*Arrival Time\s*:\s*(\d+:\d+)#i")
                        );

                        $COTimeStr = cell('Latest Check Out:', +1);

                        if (!$COTimeStr) {
                            $COTimeStr = cell(['Check Out By:', 'Check out by:'], +1);
                        }

                        $nights = cell('No. Nights:', +1);

                        $res['CheckInDate'] = strtotime($CIDateStr . ', ' . $CITimeStr);
                        $res['CheckOutDate'] = strtotime($CIDateStr . ' +' . $nights . ' day, ' . $COTimeStr);

                        return $res;
                    },

                    "GuestNames" => function ($text = '', $node = null, $it = null) {
                        return orval(
                            nodes("//*[contains(text(), 'Room Details')]/ancestor::table[1]/following-sibling::table[1]//tr[contains(., 'Mr ') or contains(., 'Mrs ')]/td[1]"),
                            [cell('Booked By:', +1)]
                        );
                    },

                    "Rooms" => function ($text = '', $node = null, $it = null) {
                        return cell('No. Rooms:', +1);
                    },

                    "CancellationPolicy" => function ($text = '', $node = null, $it = null) {
                        return re('#Room\s+Cancellation\s+Policy\s*:\s+(.*)#i');
                    },

                    "RoomType" => function ($text = '', $node = null, $it = null) {
                        $regex = '#(.*Room)\s+sleeps\s+(\d+)(?:\s+Adults?)?(?:\s+(\d+)\s+child)?.*\s+(.*)#i';

                        if (preg_match($regex, $text, $m)) {
                            return [
                                'RoomType'            => nice($m[1]),
                                'Guests'              => $m[2],
                                'Kids'                => ($m[3]) ? $m[3] : null,
                                'RoomTypeDescription' => $m[4],
                            ];
                        }
                    },

                    "Total" => function ($text = '', $node = null, $it = null) {
                        if (preg_match('#Total\s+\(([A-Z]{3})\)\s*:\s+(.*)#', $text, $m)) {
                            return [
                                'Currency' => $m[1],
                                'Total'    => cost($m[2]),
                            ];
                        }
                    },

                    "Status" => function ($text = '', $node = null, $it = null) {
                        return orval(
                            re("#Thank you for your (request)#"),
                            re("#This is (confirmation)#")
                        );
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
