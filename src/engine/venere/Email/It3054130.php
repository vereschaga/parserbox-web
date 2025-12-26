<?php

namespace AwardWallet\Engine\venere\Email;

class It3054130 extends \TAccountCheckerExtended
{
    public $rePlain = [
        ['#\n[>\s*]*From\s*:[^\n]*?venere#i', 'blank', ''],
    ];
    public $reHtml = "";
    public $rePDF = "";
    public $reSubject = [
        ['Venere.com booking confirmation', 'blank', ''],
    ];
    public $reFrom = [
        ['#[@.]venere\.com#i', 'blank', ''],
    ];
    public $reProvider = [
        ['#[@.]venere\.com\b#i', 'blank', ''],
    ];
    public $fnLanguage = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $isAggregator = "0";
    public $caseReference = "";
    public $upDate = "21.09.2015, 11:09";
    public $crDate = "15.09.2015, 17:00";
    public $xPath = "";
    public $mailFiles = "venere/it-3054130.eml, venere/it-3070355.eml";
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
                        return re("#confirmation\s+number[:\s]+([\w-]+)#i");
                    },

                    "HotelName" => function ($text = '', $node = null, $it = null) {
                        $data['HotelName'] = re("#\d+\s*\-\s*([^\n]+?)\s*\-\s*[^\-]+$#", $this->parser->getHeader('subject'));
                        $data['HotelName'] = preg_replace("#([a-z])([A-Z])#", "\\1 \\2", $data['HotelName']);
                        $data['Address'] = nice(re("#\n\s*" . $data['HotelName'] . "[^\n]*\s+(.+?)\n\s*([\d\+\-\(\) ]*)\s*Venere\.com\s+confirmation\s+number#sui"));
                        $data['Phone'] = re(2);

                        return $data;
                    },

                    "CheckInDate" => function ($text = '', $node = null, $it = null) {
                        $date = re("#\n\s*Check\s+in\s+\w+,\s*(\w+\s+\d+,\s+\d+)\s*\(([\d:.]+\s*\w*)\)#i");
                        $data['CheckInDate'] = timestamp_from_format($date . " " . preg_replace("#^(\d+)\s+(\w+)$#", "\\1:00 \\2", re(2)), "F d, Y H:i A");

                        return $data;
                    },

                    "CheckOutDate" => function ($text = '', $node = null, $it = null) {
                        $date = re("#\n\s*Check\s+out\s+\w+,\s*(\w+\s+\d+,\s+\d+)\s*\(([\d:.]+\s*\w*)\)#i");
                        $data['CheckOutDate'] = timestamp_from_format($date . " " . preg_replace("#^(\d+)\s+(\w+)$#", "\\1:00 \\2", re(2)), "F d, Y H:i A");

                        return $data;
                    },

                    "GuestNames" => function ($text = '', $node = null, $it = null) {
                        $raw = re("#\n\s*Room\s+details(\s*?\n.+?\n\s*)Payment\s+details\s*?\n#si");

                        if (preg_match_all("#\n\s*Room\s+\d+\s+[^\n]+\s+([^\n]+?)\s*,\s*\d+\s+adult#i", $raw, $m)) {
                            return nice(array_unique($m[1]));
                        }
                    },

                    "Guests" => function ($text = '', $node = null, $it = null) {
                        $raw = re("#\n\s*Room\s+details(\s*?\n.+?\n\s*)Payment\s+details\s*?\n#si");

                        if (preg_match_all("#\n\s*Room\s+\d+\s+.+?(\d+)\s+adult#si", $raw, $m)) {
                            $cnt = 0;

                            foreach ($m[1] as $num) {
                                $cnt += $num;
                            }

                            return $cnt;
                        }
                    },

                    "Rooms" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Your\s+stay\s+.*?(\d+)\s+room#i");
                    },

                    "Rate" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Price per room per night\s+([^\n]+)#i");
                    },

                    "CancellationPolicy" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Cancellation\s+policy\s+([^\n]+)#i");
                    },

                    "RoomType" => function ($text = '', $node = null, $it = null) {
                        $raw = re("#\n\s*Room\s+details(\s*?\n.+?\n\s*)Payment\s+details\s*?\n#si");

                        if (preg_match_all("#\n\s*Room\s+\d+\s+([^\n]+)#i", $raw, $m)) {
                            return implode("|", $m[1]);
                        }
                    },

                    "Total" => function ($text = '', $node = null, $it = null) {
                        return total(re("#\n\s*Total\s+amount\s+to\s+pay[^\n]*\s+([^\n]+)#"), 'Total');
                    },

                    "Status" => function ($text = '', $node = null, $it = null) {
                        if (re("#booking is confirmed#")) {
                            return 'Confirmed';
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
