<?php

namespace AwardWallet\Engine\triprewards\Email;

class ConfirmedReservationNotification extends \TAccountCheckerExtended
{
    public $reFrom = "#donotreply@wyn\.com#i";
    public $reProvider = "#wyn\.com#i";
    public $rePlain = "#Booking\s+Confirmation.*?Wyndham#is";
    public $rePlainRange = "";
    public $typesCount = "1";
    public $langSupported = "en";
    public $reSubject = "#Travelodge\s+Confirmed\s+Reservation\s+Notification#i";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $xPath = "";
    public $mailFiles = "triprewards/it-1787265.eml";
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
                        return re('#Confirmation\s*\#\s*:\s+([\w\-]+)#');
                    },

                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "R";
                    },

                    "HotelName" => function ($text = '', $node = null, $it = null) {
                        $xpath = '//tr[contains(., "Hotel Information") and not(.//tr)]/following-sibling::tr[1]/td[1]//text()';
                        $subj = implode("\n", nodes($xpath));

                        if (preg_match('#\n\s*(.*)\s+((?s).*)\s+Phone\s+(.*)\s+Fax\s+(.*)#', $subj, $m)) {
                            return [
                                'HotelName' => $m[1],
                                'Address'   => nice($m[2], ','),
                                'Phone'     => $m[3],
                                'Fax'       => $m[4],
                            ];
                        }
                    },

                    "CheckInDate" => function ($text = '', $node = null, $it = null) {
                        return strtotime(re('#Check-In:\s+(.*)#i'));
                    },

                    "CheckOutDate" => function ($text = '', $node = null, $it = null) {
                        return strtotime(re('#Check-Out:\s+(.*)#i'));
                    },

                    "GuestNames" => function ($text = '', $node = null, $it = null) {
                        $xpath = '//tr[contains(., "Guest Information") and not(.//tr)]/following-sibling::tr[1]/td[1]//text()';
                        $subj = implode("\n", nodes($xpath));

                        return [re('#\n\s*(.*)#', $subj)];
                    },

                    "CancellationPolicy" => function ($text = '', $node = null, $it = null) {
                        return node('//*[contains(., "Cancellation Policy")]/following-sibling::*[contains(., "If you need to cancel")]');
                    },

                    "RoomType" => function ($text = '', $node = null, $it = null) {
                        $subj = node('//*[contains(., "Check-Out")]/following-sibling::table[1]//td[1]');

                        if (preg_match('#(.*)\s+with\s+(.*)#i', $subj, $m)) {
                            return [
                                'RoomType'            => $m[1],
                                'RoomTypeDescription' => $m[2],
                            ];
                        }
                    },

                    "Taxes" => function ($text = '', $node = null, $it = null) {
                        return cost(re('#Taxes\s*&\s*Fees:\s+(.*)#'));
                    },

                    "Total" => function ($text = '', $node = null, $it = null) {
                        return total(re('#Total:\s+(.*)#'), 'Total');
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
