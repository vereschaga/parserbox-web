<?php

namespace AwardWallet\Engine\marriott\Email;

class Confirmation extends \TAccountCheckerExtended
{
    public $reFrom = "#[@]marriott#i";
    public $reProvider = "#[@]marriott#i";
    public $rePlain = "#We\s+are\s+pleased\s+to\s+confirm\s+your\s+group\s+reservation\s+with\s+[^\.]+\s+by\s+Marriott#i";
    public $typesCount = "1";
    public $langSupported = "en";
    public $reSubject = "#SpringHill\s+Suites\s+Louisville\s+Downtown#i";
    public $reHtml = "";
    public $xPath = "";
    public $mailFiles = "marriott/it-1567243.eml";
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
                        return re('#Tracking\s+number:\s+([\w\-]+)#');
                    },

                    "HotelName" => function ($text = '', $node = null, $it = null) {
                        return re('#We\s+are\s+pleased\s+to\s+confirm\s+your\s+group\s+reservation\s+with\s+([^\.]+)\s+by\s+Marriott#i');
                    },

                    "CheckInDate" => function ($text = '', $node = null, $it = null) {
                        $res = [];

                        foreach (['CheckIn' => 'Arrival', 'CheckOut' => 'Departure'] as $key => $value) {
                            $regex = '#';
                            $regex .= $value . '\s+date:\s+';
                            $regex .= '\w+,\s+(\w+\s+\d+,\s+\d+)\s+';
                            $regex .= '\((\d+:\d+\s+(?:AM|PM))\)';
                            $regex .= '#';

                            if (preg_match($regex, $text, $m)) {
                                $res[$key . 'Date'] = strtotime($m[1] . ', ' . $m[2]);
                            }
                        }

                        return $res;
                    },

                    "Phone" => function ($text = '', $node = null, $it = null) {
                        $regex = '#\n(.*\s+\n.*)\s+Phone:\s+([\d\-\s]+)#';

                        if (preg_match($regex, $text, $m)) {
                            return ['Address' => nice($m[1]), 'Phone' => nice($m[2])];
                        }
                    },

                    "GuestNames" => function ($text = '', $node = null, $it = null) {
                        return [re('#Dear\s+(.*),#U')];
                    },

                    "Rooms" => function ($text = '', $node = null, $it = null) {
                        return (int) re('#Number\s+of\s+Rooms:\s*(\d+)#');
                    },

                    "Total" => function ($text = '', $node = null, $it = null) {
                        $subj = re('#Estimated\s+Total:\s+([\d,\.]+\s+\w+)#');

                        return ['Total' => cost($subj), 'Currency' => currency($subj)];
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
