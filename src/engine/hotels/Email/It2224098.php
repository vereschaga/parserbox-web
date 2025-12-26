<?php

namespace AwardWallet\Engine\hotels\Email;

class It2224098 extends \TAccountCheckerExtended
{
    public $rePlain = "#\n[>\s*]*From\s*:[^\n]*?[@.]hotels[.]com#i";
    public $rePlainRange = "";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $rePDF = "";
    public $rePDFRange = "";
    public $reSubject = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $reFrom = "#[@.]hotels[.]com#i";
    public $reProvider = "#[@.]hotels[.]com#i";
    public $caseReference = "";
    public $isAggregator = "1";
    public $xPath = "";
    public $mailFiles = "hotels/it-2224098.eml";
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
                        return re_white('Confirmation No	(\w+)');
                    },

                    "HotelName" => function ($text = '', $node = null, $it = null) {
                        $name = nice(node("//*[contains(text(), 'Itinerary No')]/following::strong[1]"));
                        $addr = between($name, 'Arrive');

                        return [
                            'HotelName' => $name,
                            'Address'   => $addr,
                        ];
                    },

                    "CheckInDate" => function ($text = '', $node = null, $it = null) {
                        $date = re_white('Arrive : (.+? \d{4})');

                        return totime($date);
                    },

                    "CheckOutDate" => function ($text = '', $node = null, $it = null) {
                        $date = re_white('Depart : (.+? \d{4})');

                        return totime($date);
                    },

                    "GuestNames" => function ($text = '', $node = null, $it = null) {
                        $name = re_white('Dear (.+?),');

                        return [nice($name)];
                    },

                    "CancellationPolicy" => function ($text = '', $node = null, $it = null) {
                        $s = node("//*[contains(text(), 'Cancellation Policy')]/following::tr[1]");

                        return nice($s);
                    },

                    "RoomType" => function ($text = '', $node = null, $it = null) {
                        $q = white('
							Room Type :
							(?P<RoomType> .+?) -
							(?P<RoomTypeDescription> .+)
							Room \d+
						');
                        $res = re2dict($q, $text);

                        return $res;
                    },

                    "Total" => function ($text = '', $node = null, $it = null) {
                        $x = re_white('Total Price : (.[\d.,]+)');

                        return total($x, 'Total');
                    },

                    "Status" => function ($text = '', $node = null, $it = null) {
                        if (re_white('Your reservation purchase has been completed')) {
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
        return true;
    }
}
