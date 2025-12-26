<?php

namespace AwardWallet\Engine\spg\Email;

class YourRoomHasBeenUpgraded extends \TAccountCheckerExtended
{
    public $rePlain = [
        ['#Reservation\s+confirmation\s+number\s*:.+?\sHotel\s*:.+?\sRequested\s+arrival\s+time\s*:.+?\sSPG\s#si', 'blank', '/1'],
        ['#request\s+has\s+been\s+fulfilled\s+and\s+an\s+upgraded\s+room\s+is\s+awaiting\s+your\s+arrival.*?Starwood\s+Hotels\s+&\s+Resorts\s+Worldwide,\s+Inc.#is', 'blank', '10000'],
    ];
    public $reHtml = "";
    public $rePDF = "";
    public $reSubject = [
        ['Status Update: Your Request Has Been Approved', 'blank', ''],
    ];
    public $reFrom = [
        ['#@\w*\.?STARWOODHOTELS\.COM#i', 'blank', ''],
    ];
    public $reProvider = [
        ['#\bSTARWOODHOTELS\.COM#i', 'blank', ''],
    ];
    public $fnLanguage = "";
    public $langSupported = "en";
    public $typesCount = "2";
    public $isAggregator = "";
    public $caseReference = "";
    public $upDate = "06.08.2015, 11:31";
    public $crDate = "";
    public $xPath = "";
    public $mailFiles = "spg/it-1787329.eml, spg/it-1845406.eml, spg/it-2955159.eml";
    public $re_catcher = "#.*?#";
    public $pdfRequired = "0";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    if (is_string($this->rePlain)) {
                        $this->rePlain = [[$this->rePlain]];
                    }
                    $fIgnore = true;

                    foreach ($this->rePlain as $rPlain) {
                        if (preg_match($rPlain[0], $text)) {
                            $fIgnore = false;
                        }
                    }

                    if ($fIgnore) {
                        // Ignore emails of other formats
                        return null;
                    }

                    return [$text];
                },

                "#.*?#" => [
                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "R";
                    },

                    "ConfirmationNumber" => function ($text = '', $node = null, $it = null) {
                        return re('#Reservation\s+confirmation\s+number\s*:\s*([\w\-]+)#');
                    },

                    "HotelName" => function ($text = '', $node = null, $it = null) {
                        $res['HotelName'] = orval(
                            re('#Hotel:\s+(.*?)\s+Stay dates:#'),
                            re('#\n\s*Hotel:\s+(.+?)\s+Check\-in\s+date:#i')
                        );
                        $res['Address'] = $res['HotelName'];

                        return $res;
                    },

                    "CheckInDate" => function ($text = '', $node = null, $it = null) {
                        $res = null;
                        $regex = '#(\w+\s+\d+,\s+\d+)\s+through\s+(\w+\s+\d+,\s+\d+)#i';

                        if (preg_match($regex, $text, $m)) {
                            $res['CheckInDate'] = strtotime($m[1]);
                            $res['CheckOutDate'] = strtotime($m[2]);
                        } elseif (re("#Check\-in\s+date\s*:\s*(\w+\s+\d+,\s+\d+).+?Requested\s+arrival\s+time\s*:\s*(\d+:\d+\s+\w+)#si")) {
                            $res['CheckInDate'] = strtotime(re(1) . " " . re(2));
                        }

                        return $res;
                    },

                    "CheckOutDate" => function ($text = '', $node = null, $it = null) {
                        if (!isset($it['CheckOutDate'])) {
                            return strtotime(re("#Check\-?out\s+date\s*:\s*(\w+\s+\d+,\s+\d+).+?Requested\s+departure\s+time\s*:\s*(\d+:\d+\s+\w+)#si") . " " . re(2));
                        }

                        return $it['CheckOutDate'];
                    },

                    "GuestNames" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*([^\n]+?) *(?:\n|\|) *Member\s+Number:#i");
                    },

                    "CancellationPolicy" => function ($text = '', $node = null, $it = null) {
                        $regex = '#Please note, now that(?:(?s).*?)cancel(?:(?s).*?)This is in addition to any other cancellation.*#';

                        return nice(orval(
                            re($regex),
                            re("#\n\s*(If you need to cancel.+?\.)\s*\n#s")
                        ));
                    },

                    "SpentAwards" => function ($text = '', $node = null, $it = null) {
                        return re('#Suite\s+Night\s+Award\(s\)\s+being\s+used\s+for\s+this\s+stay\s*:\s+(\d+)#');
                    },

                    "Status" => function ($text = '', $node = null, $it = null) {
                        return orval(
                            re("#(\w+) room is awaiting #"),
                            re("#request has been (\w+):#")
                        );
                    },
                ],
            ],
        ];
    }

    public static function getEmailTypesCount()
    {
        return 2;
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
