<?php

namespace AwardWallet\Engine\loews\Email;

class LoewsReservation extends \TAccountCheckerExtended
{
    public $rePlain = "#welcoming you to Loews|cancelled your reservation at Loews#i";
    public $rePlainRange = "/1";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $rePDF = "";
    public $rePDFRange = "";
    public $reSubject = "#Loews Reservation#i";
    public $langSupported = "en";
    public $typesCount = "1";
    public $reFrom = "#loewshotelsconfirmation@loewshotels\.com#i";
    public $reProvider = "#loewshotels\.com#i";
    public $caseReference = "";
    public $xPath = "";
    public $mailFiles = "loews/it-1.eml, loews/it-1938337.eml, loews/it-1942175.eml, loews/it-1945739.eml";
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
                        if ($confNo = re('#Reservation Confirmation Number:\s+([\w\-]+)#i')) {
                            return $confNo;
                        } elseif ($cancNo = re('#Reservation Cancellation Number#i')) {
                            return [
                                'ConfirmationNumber' => CONFNO_UNKNOWN,
                                'Status'             => 'Cancelled',
                                'Cancelled'          => true,
                            ];
                        }
                    },

                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "R";
                    },

                    "HotelName" => function ($text = '', $node = null, $it = null) {
                        return nice(re('#(.*)\s+Reservations\s+Department#'));
                    },

                    "CheckInDate" => function ($text = '', $node = null, $it = null) {
                        $nodes1 = nodes('//tr[contains(., "Arrival Date") and contains(., "Nights")]/following-sibling::tr/td');
                        $nodes2 = nodes('//tr[contains(., "Check In Time") and contains(., "Check Out Time")]/following-sibling::tr/td');

                        if (count($nodes1) == 4 and count($nodes2) == 5) {
                            if (preg_match('#(\d+)-(\d+)-(\d+)#i', $nodes1[0], $m)) {
                                $dateStr = $m[2] . '.' . $m[1] . '.' . (strlen($m[3]) == 2 ? '20' : '') . $m[3];
                            } else {
                                $dateStr = $nodes1[0];
                            }
                            $res['CheckInDate'] = strtotime($dateStr . ', ' . $nodes2[3]);

                            if ($res['CheckInDate']) {
                                $res['CheckOutDate'] = strtotime(' +' . $nodes1[1] . ' day, ' . $nodes2[4], $res['CheckInDate']);
                            }
                            $res['Guests'] = re('#(\d+)\s+Adult#i', $nodes1[2]);
                            $res['Kids'] = re('#(\d+)\s+Child#i', $nodes1[2]);
                            $res['RoomType'] = $nodes1[3];
                            $res['Rate'] = $nodes2[1];

                            return $res;
                        } elseif (preg_match('#arriving on (.*?) and departing on (.*?)\.#i', $text, $m)) {
                            return [
                                'CheckInDate'  => strtotime($m[1]),
                                'CheckOutDate' => strtotime($m[2]),
                            ];
                        }
                    },

                    "Address" => function ($text = '', $node = null, $it = null) {
                        if (preg_match('#(.*)\s+T\s+(.*)\s+F\s+(.*)\s+loewshotels\.com#i', $text, $m)) {
                            return [
                                'Address' => preg_replace('#\s\s+#i', ', ', trim($m[1])),
                                'Phone'   => nice($m[2]),
                                'Fax'     => nice($m[3]),
                            ];
                        }
                    },

                    "GuestNames" => function ($text = '', $node = null, $it = null) {
                        return [nice(re('#Dear\s+(.*?),#i'))];
                    },

                    "CancellationPolicy" => function ($text = '', $node = null, $it = null) {
                        return nice(re('#.*to cancel your reservation.*#i'));
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
