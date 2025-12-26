<?php

namespace AwardWallet\Engine\triprewards\Email;

class ConfirmationHTML extends \TAccountCheckerExtended
{
    public $reFrom = "#room.reservations@wyndhamworldwide\.com#i";
    public $reProvider = "#wyndhamworldwide\.com#i";
    public $rePlain = "#www.wyndhamrewards.com#i";
    public $rePlainRange = "/1";
    public $typesCount = "1";
    public $langSupported = "en";
    public $reSubject = "";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $xPath = "";
    public $mailFiles = "triprewards/it-1732419.eml";
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
                        return re('#Confirmation\s+Number:\s+([\w\-]+)#i');
                    },

                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "R";
                    },

                    "HotelName" => function ($text = '', $node = null, $it = null) {
                        $xpath = '//text()[contains(., "Hotel Details")]/ancestor::td[2]/following-sibling::td[last()]//text()';
                        $subj = trim(implode("\n", nodes($xpath)));

                        if (preg_match('#^(.*)\s+((?s).*)\s+(.*)$#', $subj, $m)) {
                            return [
                                'HotelName' => $m[1],
                                'Address'   => nice(trim($m[2]), ','),
                                'Phone'     => $m[3],
                            ];
                        }
                    },

                    "CheckInDate" => function ($text = '', $node = null, $it = null) {
                        $subj = nodes('//tr[contains(., "Room Description") and not(.//tr)]/following-sibling::tr[string-length(.) > 1]/td[normalize-space()]');

                        if ($subj) {
                            $res['RoomType'] = $subj[0];

                            foreach (['CheckIn' => 1, 'CheckOut' => 2] as $key => $value) {
                                $s = preg_replace('#(\d+)(.*?)(\d+)#', '\1 \2 \3', $subj[$value]);
                                $res["${key}Date"] = strtotime($s);
                            }
                            $res['Rooms'] = $subj[3];

                            if (preg_match('#(\d+)/(\d+)/\d+#', $subj[4], $m)) {
                                $res['Guests'] = $m[1];
                                $res['Kids'] = $m[2];
                            }

                            return $res;
                        }
                    },

                    "Rate" => function ($text = '', $node = null, $it = null) {
                        return nice(re('#Total\s+room\s+rate:\s+(.*)#i'), ',');
                    },

                    "CancellationPolicy" => function ($text = '', $node = null, $it = null) {
                        return re('#Cancellation\s+policy:\s+(.*)#i');
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
