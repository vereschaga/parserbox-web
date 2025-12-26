<?php

namespace AwardWallet\Engine\marriott\Email;

class It1883053 extends \TAccountCheckerExtended
{
    public $rePlain = "#\n[>\s*]*From\s*:[^\n]*?[@]marriott#i";
    public $rePlainRange = "";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $rePDF = "";
    public $rePDFRange = "";
    public $reSubject = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $reFrom = "#[@]marriott#i";
    public $reProvider = "#[@]marriott#i";
    public $xPath = "";
    public $mailFiles = "marriott/it-1883053.eml, marriott/it-1883057.eml";
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
                        return re("#\n\s*Confirmation[\s\#:]+([A-Z\d\-]+)#");
                    },

                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "R";
                    },

                    "ConfirmationNumbers" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Marriott Rewards number\s*:\s*([A-Z\d\-]+)#");
                    },

                    "HotelName" => function ($text = '', $node = null, $it = null) {
                        return re("#Your\s+reservation\s+\#\d+\s+at\s+the\s+(.*?)\s+begins\s+soon#i");
                    },

                    "CheckInDate" => function ($text = '', $node = null, $it = null) {
                        return totime(uberDateTime(re("#\n\s*Check\-in\s*:\s*([^\n]+)#")));
                    },

                    "CheckOutDate" => function ($text = '', $node = null, $it = null) {
                        return totime(uberDateTime(re("#\n\s*Check\-out\s*:\s*([^\n]+)#")));
                    },

                    "Address" => function ($text = '', $node = null, $it = null) {
                        return [
                            'Address' => nice(re("#{$it['HotelName']} >>\s*(.*?)\s+Phone:\s*([\d\-\(\)+ ]+)\s+Fax:\s*([\d\-\(\)+ ]+)#ims"), ','),
                            'Phone'   => re(2),
                            'Fax'     => re(3),
                        ];
                    },

                    "Guests" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Guests in room\s*:\s*(\d+)#");
                    },

                    "Rooms" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Number of rooms\s*:\s*(\d+)#");
                    },

                    "Rate" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*(?:BEST AVAILABLE RATE|REGULAR RATE)\s*:\s*([^\n]+)#");
                    },

                    "CancellationPolicy" => function ($text = '', $node = null, $it = null) {
                        return nice(re("#\n\s*Cancellation Policy\s*:\s*(.*?)$#is", text(xpath("//*[contains(text(), 'Cancellation policy:')]/ancestor-or-self::td[1]"))));
                    },

                    "RoomType" => function ($text = '', $node = null, $it = null) {
                        $res = null;
                        $type = re("#\n\s*Room type\s*:\s*([^\n]*?)\s{2,}#");

                        for ($i = 0; $i < intval($it['Rooms']); $i++) {
                            $res[] = $type;
                        }

                        return ($res) ? implode('|', $res) : null;
                    },

                    "RoomTypeDescription" => function ($text = '', $node = null, $it = null) {
                        return clear("#\d+\s+Room\s*:\s*#", implode('|', nodes("//*[contains(text(), '1 Room:')]/ancestor-or-self::td[1]")));
                    },
                ],
            ],
        ];
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $parser->getBody();

        return strpos($body, "Marriott Rewards number") !== false;
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
