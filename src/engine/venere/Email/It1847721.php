<?php

namespace AwardWallet\Engine\venere\Email;

class It1847721 extends \TAccountCheckerExtended
{
    public $rePlain = "#Venere\s*Net\s*Srl,\s*Via\s*della\s*Camilluccia#i";
    public $rePlainRange = "-1000";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $rePDF = "";
    public $rePDFRange = "";
    public $reSubject = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $reFrom = "#[@]venere[.]com#i";
    public $reProvider = "#[@.]venere[.]com#i";
    public $xPath = "";
    public $mailFiles = "venere/it-1847721.eml, venere/it-1880871.eml";
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
                        if (preg_match("#(\w+)\s*Venere[.]com\s*Reservation:\s*([\w-]+)#is", $text, $ms)) {
                            return [
                                'Status'             => nice($ms[1]),
                                'ConfirmationNumber' => $ms[2],
                            ];
                        }
                    },

                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "R";
                    },

                    "HotelName" => function ($text = '', $node = null, $it = null) {
                        $name = node("//img[contains(@src, 'star_')]/preceding::a[1]");

                        return nice($name);
                    },

                    "CheckInDate" => function ($text = '', $node = null, $it = null) {
                        $date = cell('Day of Arrival:', +1);

                        return strtotime(uberDateTime($date));
                    },

                    "CheckOutDate" => function ($text = '', $node = null, $it = null) {
                        $date = cell('Day of Departure:', +1);

                        return strtotime(uberDateTime($date));
                    },

                    "Address" => function ($text = '', $node = null, $it = null) {
                        $addr = node("//img[contains(@src, 'star_')]/following::tr[1]");
                        $name = nice(node("//img[contains(@src, 'star_')]/preceding::a[1]"));

                        // remove hotel name from address if present
                        if (preg_match("/$name\s*(.+)/", $addr, $ms)) {
                            return $ms[1];
                        }

                        return $addr;
                    },

                    "Phone" => function ($text = '', $node = null, $it = null) {
                        $phone = node("//img[contains(@src, 'category/star')]/following::tr[2]//text()[2]");

                        return nice($phone);
                    },

                    "Rooms" => function ($text = '', $node = null, $it = null) {
                        $type = cell('Type of room', +1);

                        return re("#^\s*(\d+)\s+#", $type);
                    },

                    "RoomType" => function ($text = '', $node = null, $it = null) {
                        $type = cell('Type of room', +1);
                        $type_ = re('/\d+\s*.\s*(.+)/u', $type);

                        if ($type_) {
                            $type = $type_;
                        }

                        if (preg_match('/\s*(.+?)\s*[-](.+)/is', $type, $ms)) {
                            return [
                                'RoomType'            => nice($ms[1]),
                                'RoomTypeDescription' => nice($ms[2]),
                            ];
                        }

                        return $type;
                    },

                    "Total" => function ($text = '', $node = null, $it = null) {
                        $tot = cell('Total price:', +1);

                        return total($tot, 'Total');
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
