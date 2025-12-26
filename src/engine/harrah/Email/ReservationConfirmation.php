<?php

namespace AwardWallet\Engine\harrah\Email;

class ReservationConfirmation extends \TAccountCheckerExtended
{
    public $rePlain = [
        ['#\n[>\s*]*From\s*:[^\n]*?groupcampaigns@pkghlrss\.com#i', 'blank', ''],
    ];
    public $reHtml = "";
    public $rePDF = "";
    public $reSubject = "";
    public $reFrom = [
        ['#groupcampaigns@pkghlrss\.com#i', 'blank', ''],
    ];
    public $reProvider = [
        ['#[@.]harrah#i', 'us', ''],
    ];
    public $fnLanguage = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $isAggregator = "0";
    public $caseReference = "";
    public $upDate = "28.10.2015, 21:09";
    public $crDate = "28.10.2015, 20:40";
    public $xPath = "";
    public $mailFiles = "harrah/it-3177889.eml";
    public $re_catcher = "#.*?#";
    public $pdfRequired = "0";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    return [$text];
                },

                "#.*?#" => [
                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "R";
                    },

                    "ConfirmationNumber" => function ($text = '', $node = null, $it = null) {
                        return re('#Confirmation\s+Number:\s+(\w+)#');
                    },

                    "HotelName" => function ($text = '', $node = null, $it = null) {
                        $r = '#';
                        $r .= 'for\s+more\s+information\s+on\s+our\s+resort\.\s+';
                        $r .= '(.*)';
                        $r .= '.*\s+';
                        $r .= '((?s).*?)\s*';
                        $r .= '\n(\d.*)\s*\n\s*';
                        $r .= 'Reservation\s+Information';
                        $r .= '#';

                        if (preg_match($r, $this->text(), $m)) {
                            return [
                                'HotelName' => $m[1],
                                'Address'   => nice($m[2], ', '),
                                'Phone'     => $m[3],
                            ];
                        }
                    },

                    "CheckInDate" => function ($text = '', $node = null, $it = null) {
                        return strtotime(re('#Check-In\s+Date:\s+(.*)#'));
                    },

                    "CheckOutDate" => function ($text = '', $node = null, $it = null) {
                        return strtotime(re('#Check-Out\s+Date:\s+(.*)#'));
                    },

                    "GuestNames" => function ($text = '', $node = null, $it = null) {
                        return re('#Guest\s+Name:\s+(.*)#');
                    },

                    "Guests" => function ($text = '', $node = null, $it = null) {
                        return re('#Number\s+of\s+Adults:\s+(\d+)#');
                    },

                    "Kids" => function ($text = '', $node = null, $it = null) {
                        return re('#Number\s+of\s+Children:\s+(\d+)#');
                    },

                    "Rate" => function ($text = '', $node = null, $it = null) {
                        $r = '#';
                        $r .= 'Rates Per Room\*\s+';
                        $r .= 'Date\s+Guest\(s\)\s+Status\s+Rate\s+';
                        $r .= '((?s).*?)';
                        $r .= 'Additional\s+Guest\s+Rate';
                        $r .= '#';
                        $s = re($r);

                        if ($s and preg_match_all('#[\d,]+\.\d{2}#', $s, $m)) {
                            if (count(array_unique($m[0])) == 1) {
                                return $m[0][1];
                            }
                        }
                    },

                    "RoomType" => function ($text = '', $node = null, $it = null) {
                        return re('#Room Selection & Preferences\s+(.*)#');
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

    public function IsEmailAggregator()
    {
        return false;
    }
}
