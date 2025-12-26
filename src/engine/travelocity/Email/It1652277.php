<?php

namespace AwardWallet\Engine\travelocity\Email;

class It1652277 extends \TAccountCheckerExtended
{
    public $reFrom = "#travelocity#i";
    public $reProvider = "#travelocity#i";
    public $rePlain = "#\n[>\s*]*From\s*:[^\n]*?travelocity#i";
    public $rePlainRange = "300";
    public $typesCount = "1";
    public $langSupported = "en";
    public $reSubject = "";
    public $reHtml = "";
    public $reHtmlRange = "1000";
    public $xPath = "";
    public $mailFiles = "travelocity/it-1.eml, travelocity/it-1652277.eml";
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
                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "R";
                    },

                    "ConfirmationNumber" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Confirmation number:\s*([\d\w\-]+)#");
                    },

                    "HotelName" => function ($text = '', $node = null, $it = null) {
                        $r = filter(nodes("//*[contains(text(), 'Check in:')]/ancestor-or-self::td[1]/preceding-sibling::td[1]//text()"));

                        return [
                            'HotelName' => array_shift($r),
                            'Address'   => nice(re("#^(.*?)\s+((?:\d+\.)+\d+)\s*\n#ms", glue($r, "\n"))),
                            'Phone'     => re(2),
                        ];
                    },

                    "CheckInDate" => function ($text = '', $node = null, $it = null) {
                        return totime(re("#\n\s*Check[\s\-]+in\s*:\s*([^\n]+)#"));
                    },

                    "CheckOutDate" => function ($text = '', $node = null, $it = null) {
                        return totime(re("#\n\s*Check[\s\-]+out\s*:\s*([^\n]+)#"));
                    },

                    "GuestNames" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Contact:\s*([^\n]+)#");
                    },

                    "Guests" => function ($text = '', $node = null, $it = null) {
                        return re("#(\d+)\s+adult#");
                    },

                    "CancellationPolicy" => function ($text = '', $node = null, $it = null) {
                        return node("//*[contains(text(), 'Room Policies')]/ancestor-or-self::tr[1]/following-sibling::tr[1]");
                    },

                    "RoomType" => function ($text = '', $node = null, $it = null) {
                        $td = text(xpath("//*[contains(text(), 'Check in:')]/ancestor-or-self::td[1]"));
                        $type = [];
                        $desc = [];

                        re("#\n\s*Room\s+\d+:\s*([^\n]+)#ms", function ($m) use (&$type, &$desc) {
                            if (re("#^(.*?)\s+with\s+(.+)#", $m[1])) {
                                $type[] = re(1);
                                $desc[] = clear("#\(\d+\s*adult\)#", re(2));
                            } else {
                                $type[] = $m[1];
                                $desc[] = "";
                            }
                        }, $td);

                        return [
                            'RoomType'            => implode("|", $type),
                            'RoomTypeDescription' => implode("|", $desc),
                        ];
                    },

                    "Cost" => function ($text = '', $node = null, $it = null) {
                        return cost(re("#\n\s*Room\s+\d+\s*\-\s*Total:\s*([^\n]+)#"));
                    },

                    "Total" => function ($text = '', $node = null, $it = null) {
                        return cost(re("#\n\s*Total:\s*([^\n]+)#"));
                    },

                    "Currency" => function ($text = '', $node = null, $it = null) {
                        return currency(re(1));
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
