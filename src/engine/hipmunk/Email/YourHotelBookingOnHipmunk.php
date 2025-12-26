<?php

namespace AwardWallet\Engine\hipmunk\Email;

class YourHotelBookingOnHipmunk extends \TAccountCheckerExtended
{
    public $rePlain = "#Reservation\s+Details.*?by\s+Hipmunk,\s+Inc\.#is";
    public $rePlainRange = "/1";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $rePDF = "";
    public $rePDFRange = "";
    public $reSubject = "#Your hotel booking on Hipmunk#i";
    public $langSupported = "en";
    public $typesCount = "1";
    public $reFrom = "#contact@hipmunk\.com#i";
    public $reProvider = "#hipmunk\.com#i";
    public $caseReference = "";
    public $xPath = "";
    public $mailFiles = "hipmunk/it-1.eml, hipmunk/it-1675353.eml, hipmunk/it-1739839.eml, hipmunk/it-1938562.eml, hipmunk/it-1966206.eml";
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
                        return re('#(?:Booking Confirmation|Confirmation Code)\s*\#?:\s*\#?([\w\-]+)#i');
                    },

                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "R";
                    },

                    "HotelName" => function ($text = '', $node = null, $it = null) {
                        if (preg_match('#\s+(.*)\s+(.*)\s+hotel\s+details\s*.\s*get\s+directions#i', $text, $m)) {
                            return [
                                'HotelName' => $m[1],
                                'Address'   => nice($m[2], ','),
                            ];
                        }
                    },

                    "CheckInDate" => function ($text = '', $node = null, $it = null) {
                        return strtotime(re('#Check In:\s+(.*)#i'));
                    },

                    "CheckOutDate" => function ($text = '', $node = null, $it = null) {
                        return strtotime(re('#Check Out:\s+(.*)#i'));
                    },

                    "Guests" => function ($text = '', $node = null, $it = null) {
                        return re('#\s+(\d+)\s+adult#i');
                    },

                    "Rooms" => function ($text = '', $node = null, $it = null) {
                        return re('#\s+(\d+)\s+room#i');
                    },

                    "Rate" => function ($text = '', $node = null, $it = null) {
                        return nice(re('#@\s+(.{5,20}\s+per\s+night)\)#is'));
                    },

                    "CancellationPolicy" => function ($text = '', $node = null, $it = null) {
                        $subj = re('#Cancellation Policy\s+(.*)#s', cell('Cancellation Policy'));

                        if ($subj) {
                            $subj = preg_replace('#Terms and Conditions.*#is', '', $subj);
                        }

                        return $subj;
                    },

                    "RoomType" => function ($text = '', $node = null, $it = null) {
                        $subj = orval(
                            node('//tr[contains(., "per night)") and not(.//tr)]/preceding-sibling::tr[1]'),
                            node('(//td[contains(., "per night)") and not(.//td)]//text()[1])[1]')
                        );

                        return preg_replace('#\s+with\s+.*#i', '', $subj);
                    },

                    "RoomTypeDescription" => function ($text = '', $node = null, $it = null) {
                        $subj = re('#Room Details\s+Room\s+(.*)#', cell('Room Details'));

                        if ($subj) {
                            $subj = preg_replace('#Check-In Instructions\s+.*#is', '', $subj);
                            $subj = preg_replace('#Guest\s+Details\s+.*#is', '', $subj);
                        }

                        return $subj;
                    },

                    "Cost" => function ($text = '', $node = null, $it = null) {
                        return orval(
                            cost(node('//td[contains(., "per night)") and not(.//td)]/ancestor::td[1]/following-sibling::td[1]')),
                            cost(re('#per night\)\s+\$\s*(.*)#i'))
                        );
                    },

                    "Taxes" => function ($text = '', $node = null, $it = null) {
                        return cost(cell('Tax Recovery Charges & Service Fees', +1));
                    },

                    "Total" => function ($text = '', $node = null, $it = null) {
                        return cost(cell('Total in', +1));
                    },

                    "Currency" => function ($text = '', $node = null, $it = null) {
                        return re('#Total in (\w{3})\s+#');
                    },

                    "Status" => function ($text = '', $node = null, $it = null) {
                        if ($st = re('#Your hotel has been (booked)#i')) {
                            return $st;
                        } elseif ($st = re('#This booking has been (cancelled)#i')) {
                            return [
                                'Status'    => $st,
                                'Cancelled' => true,
                            ];
                        }
                    },

                    "ReservationDate" => function ($text = '', $node = null, $it = null) {
                        return strtotime(re('#Booking created:\s+(.*)#i'));
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
