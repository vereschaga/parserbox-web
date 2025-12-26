<?php

namespace AwardWallet\Engine\goldpassport\Email;

class It1839304 extends \TAccountCheckerExtended
{
    public $rePlain = [
        ['#\n[>\s*]*From\s*:[^\n]*?@t[.]hyatt[.]com#i', 'us', ''],
    ];
    public $reHtml = "";
    public $rePDF = "";
    public $reSubject = "";
    public $reFrom = [
        ['#@t[.]hyatt[.]com#i', 'us', ''],
    ];
    public $reProvider = [
        ['#@t[.]hyatt[.]com#i', 'us', ''],
    ];
    public $fnLanguage = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $isAggregator = "";
    public $caseReference = "";
    public $upDate = "27.08.2015, 13:39";
    public $crDate = "";
    public $xPath = "";
    public $mailFiles = "";
    public $re_catcher = "#.*?#";
    public $pdfRequired = "0";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    return null; // covered by ReservationConfirmation
                    $st = xpath("//img[contains(@src, 'brand_hyatthouse_logo.gif')]");

                    if ($st->length) {
                        return [$text];
                    }
                },

                "#.*?#" => [
                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "R";
                    },

                    "ConfirmationNumber" => function ($text = '', $node = null, $it = null) {
                        return re("#Confirmation\s*Number:\s*([\w-]+)#i");
                    },

                    "HotelName" => function ($text = '', $node = null, $it = null) {
                        $name = node("//*[contains(text(), 'Tel:')]/preceding::strong[1]");
                        $info = node("//*[contains(text(), 'Tel:')]/ancestor::td[1]");
                        $addr = re("#$name(.+)Tel:#is", $info);

                        return [
                            'HotelName' => nice($name),
                            'Address'   => nice($addr),
                        ];
                    },

                    "CheckInDate" => function ($text = '', $node = null, $it = null) {
                        if (preg_match('/Check-In\s*Date:(.+)Hotel\s*Check-In\s*Time:\s*(\d+:\d+\s*(?:pm|am)?)/is', $text, $ms)) {
                            $date = nice($ms[1]);
                            $time = nice($ms[2]);
                            $dt = uberDateTime("$date $time");

                            return totime($dt);
                        }
                    },

                    "CheckOutDate" => function ($text = '', $node = null, $it = null) {
                        if (preg_match('/Check-Out\s*Date:(.+)Hotel\s*Check-Out\s*Time:\s*(\d+:\d+\s*(?:pm|am)?)/is', $text, $ms)) {
                            $date = nice($ms[1]);
                            $time = nice($ms[2]);
                            $dt = uberDateTime("$date $time");

                            return totime($dt);
                        }
                    },

                    "Phone" => function ($text = '', $node = null, $it = null) {
                        $q = 'Tel:';
                        $tel = node("//*[contains(text(), '$q')]");
                        $tel = re("#$q(.+)#s", $tel);

                        return nice($tel);
                    },

                    "Fax" => function ($text = '', $node = null, $it = null) {
                        $q = 'Fax:';
                        $fax = node("//*[contains(text(), '$q')]");
                        $fax = re("#$q(.+)#s", $fax);

                        return nice($fax);
                    },

                    "GuestNames" => function ($text = '', $node = null, $it = null) {
                        $name = re("#Guest\s*Name:(.+)Number\s*of\s*Adults:#is");

                        return [nice($name)];
                    },

                    "Guests" => function ($text = '', $node = null, $it = null) {
                        return re("#Number\s*of\s*Adults:\s*(\d+)#i");
                    },

                    "Kids" => function ($text = '', $node = null, $it = null) {
                        return re("#Number\s*of\s*Children:\s*(\d+)#i");
                    },

                    "Rooms" => function ($text = '', $node = null, $it = null) {
                        return re("#Number\s*of\s*Rooms:\s*(\d+)#i");
                    },

                    "Rate" => function ($text = '', $node = null, $it = null) {
                        return re("#(\d+[.]\d+\s*\w+\s*\w+)\s*Type\s*of\s*Rate:#is");
                    },

                    "CancellationPolicy" => function ($text = '', $node = null, $it = null) {
                        $cancel = re("#CANCELLATION\s*POLICY:(.+)Additional Tax#is");

                        return nice($cancel);
                    },

                    "RoomType" => function ($text = '', $node = null, $it = null) {
                        $type = re("#Room[(]s[)]\s*Booked:\s*(.+)\s*Room\s*Description:#is");

                        return nice($type);
                    },

                    "RoomTypeDescription" => function ($text = '', $node = null, $it = null) {
                        $desc = re("#Room\s*Description:(.+)Nightly\s*Rate\s*per\s*Room:#is");

                        return nice($desc);
                    },

                    "AccountNumbers" => function ($text = '', $node = null, $it = null) {
                        return re('#Membership\s+Number\s*:\s+(.*)#');
                    },

                    "Status" => function ($text = '', $node = null, $it = null) {
                        if (re('/RESERVATION\s*CONFIRMATION/i')) {
                            return 'confirmed';
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

    public function IsEmailAggregator()
    {
        return false;
    }
}
