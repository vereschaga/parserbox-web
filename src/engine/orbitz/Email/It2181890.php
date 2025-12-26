<?php

namespace AwardWallet\Engine\orbitz\Email;

class It2181890 extends \TAccountCheckerExtended
{
    public $mailFiles = "orbitz/it-2181890.eml, orbitz/it-3526263.eml";

    public $rePlain = "#\n[>\s*]*From\s*:[^\n]*?[@.]orbitz[.]com#i";
    public $rePlainRange = "";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $rePDF = "";
    public $rePDFRange = "";
    public $reSubject = "";
    public $langSupported = "zh";
    public $typesCount = "1";
    public $reFrom = "#[@.]orbitz[.]com#i";
    public $reProvider = "#[@.]orbitz[.]com#i";
    public $caseReference = "";
    public $isAggregator = "0";
    public $xPath = "";
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
                        return re_white('(?:預訂|Reservation):	(\w+)');
                    },

                    "HotelName" => function ($text = '', $node = null, $it = null) {
                        $name = node("//*[contains(@alt, 'star')]/preceding::span[1]");

                        return nice($name);
                    },

                    "CheckInDate" => function ($text = '', $node = null, $it = null) {
                        $date = re_white('(?:入住|Check-in): (.+? \d{4})');

                        return totime($date);
                    },

                    "CheckOutDate" => function ($text = '', $node = null, $it = null) {
                        $date = re_white('(?:退房|Check-out): (.+? \d{4})');

                        return totime($date);
                    },

                    "Address" => function ($text = '', $node = null, $it = null) {
                        $q = white('
							(?:退房|Check-out): .+? \d{4}
							(?P<Address> .+?)
							(?P<Phone> [\d-]{5,})
						');
                        $res = re2dict($q, $text);

                        return $res;
                    },

                    "GuestNames" => function ($text = '', $node = null, $it = null) {
                        $name = cell(['旅客姓名:', 'Travelername(s):'], +1);

                        return [nice($name)];
                    },

                    "Guests" => function ($text = '', $node = null, $it = null) {
                        return re_white('(\d+) (?:住客|guests)');
                    },

                    "Rooms" => function ($text = '', $node = null, $it = null) {
                        return re_white('(\d+) (?:房間|Room\(s\))');
                    },

                    "CancellationPolicy" => function ($text = '', $node = null, $it = null) {
                        return orval(
                            between('取消政策', '酒店取消政策'),
                            between('Cancellation policy', 'Hotel cancellation policy')
                        );
                    },

                    "RoomType" => function ($text = '', $node = null, $it = null) {
                        $info = cell(['房間類型', 'Room Type'], +1);
                        $x = re_white('^ (.+?) (?:-|$)', $info);

                        return nice($x);
                    },

                    "RoomTypeDescription" => function ($text = '', $node = null, $it = null) {
                        $info = cell(['房間類型', 'Room Type'], +1);
                        $x = re_white('- (.+)', $info);

                        return nice($x);
                    },

                    "Cost" => function ($text = '', $node = null, $it = null) {
                        $x = node('//*[contains(text(), "稅款及費用:") or contains(text(), "Taxes and Fees:")]/preceding::td[1]');

                        return cost($x);
                    },

                    "Taxes" => function ($text = '', $node = null, $it = null) {
                        $x = cell(['稅款及費用:', 'Taxes and Fees:'], +1);

                        return cost($x);
                    },

                    "Total" => function ($text = '', $node = null, $it = null) {
                        $x = cell(['總金額:', 'Total Price:'], +1);

                        return total($x, 'Total');
                    },

                    "Status" => function ($text = '', $node = null, $it = null) {
                        if (re_white('酒店確認')) {
                            return 'confirmed';
                        }

                        if (re_white('Thank you for booking')) {
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
        return ["zh", "en"];
    }

    public function IsEmailAggregator()
    {
        return false;
    }
}
