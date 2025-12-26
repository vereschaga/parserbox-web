<?php

namespace AwardWallet\Engine\marriott\Email;

class YourVillaAwaits extends \TAccountCheckerExtended
{
    public $reFrom = "#[@]marriott#i";
    public $reProvider = "#[@]marriott#i";
    public $rePlain = "#We\s+look\s+forward\s+to\s+seeing\s+you\s+soon\s+at\s+Marriott#i";
    public $rePlainRange = "/1";
    public $typesCount = "1";
    public $langSupported = "en";
    public $reSubject = "#Your\s+Villa\s+Awaits!\s+Marriott.*?Confirmation\s+Details#i";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $xPath = "";
    public $mailFiles = "marriott/it-1795387.eml";
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
                        return re('#Confirmation\s+Number\(s\):\s+([\w\-]+)#i');
                    },

                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "R";
                    },

                    "HotelName" => function ($text = '', $node = null, $it = null) {
                        $res['HotelName'] = re('#We\s+look\s+forward\s+to\s+seeing\s+you\s+soon\s+at\s+(.*?)\.#i');
                        $regex = '#' . $res['HotelName'] . '\s*\n\s*((?s).*?)\s+Phone:\s+(.*)\s+Fax:\s+(.*)#i';

                        if (preg_match($regex, $text, $m)) {
                            $res['Address'] = nice($m[1], ',');
                            $res['Phone'] = $m[2];
                            $res['Fax'] = $m[3];
                        }

                        return $res;
                    },

                    "CheckInDate" => function ($text = '', $node = null, $it = null) {
                        $res = null;

                        foreach (['CheckIn' => 'Arrival', 'CheckOut' => 'Departure'] as $key => $value) {
                            $regex = '#' . $value . ':\s+\w+,\s+(\w+\s+\d+,\s+\d+)\s+.*\s+(\d+:\d+\s*[ap]\.m\.)#';

                            if (preg_match($regex, $text, $m)) {
                                $res[$key . 'Date'] = strtotime($m[1] . ', ' . $m[2]);
                            }
                        }

                        return $res;
                    },

                    "RoomType" => function ($text = '', $node = null, $it = null) {
                        return re('#Accommodations:\s+(.*?)\s+-\s+For\s+details#is');
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
