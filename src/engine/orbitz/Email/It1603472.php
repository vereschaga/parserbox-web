<?php

namespace AwardWallet\Engine\orbitz\Email;

class It1603472 extends \TAccountCheckerExtended
{
    public $mailFiles = "orbitz/it-1.eml, orbitz/it-1583501.eml, orbitz/it-1598365.eml, orbitz/it-1603472.eml, orbitz/it-1631526.eml, orbitz/it-1665521.eml, orbitz/it-3.eml";

    public $reFrom = "#orbitz#i";
    public $reProvider = "#orbitz#i";
    public $rePlain = "#mailto:travelercare@orbitz.com|\n[>\s*]*From\s*:[^\n]*?orbitz#i";
    public $rePlainRange = "";
    public $typesCount = "1";
    public $langSupported = "en";
    public $reSubject = "";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $xPath = "";
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
                        return [
                            'ConfirmationNumber' => re("#\n\s*Hotel\s+confirmation\s+(?:number|for\s+room\s+held\s+under[^:]+):\s*([\d\w\-]+)\s+([^:]*?)\s+Phone:\s*([+\d\-\(\) ]+)*(?:\s*\|\s*Fax:\s*([+\d\-\(\) ]+))*#ms"),
                            'Address'            => nice(re(2)),
                            'Phone'              => re(3),
                            'Fax'                => re(4),
                        ];
                    },

                    "HotelName" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Hotel\s+Information.*?\n\s*Hotel\s+(.*?)\s+hotel\s*details#ms");
                    },

                    "CheckInDate" => function ($text = '', $node = null, $it = null) {
                        return strtotime(re("#\n\s*Check\-in:\s*([^\n|]+)#"));
                    },

                    "CheckOutDate" => function ($text = '', $node = null, $it = null) {
                        return strtotime(re("#\s+Check\-out:\s*([^\n|]+)#"));
                    },

                    "GuestNames" => function ($text = '', $node = null, $it = null) {
                        $r = explode("\n", re("#Hotel reservations under:\s+([\w\-\s]+)\s+Hotel\s+Information#"));
                        $res = [];

                        foreach ($r as $guest) {
                            $guest = trim($guest);

                            if ($guest) {
                                $res[$guest] = 1;
                            }
                        }

                        return orval(
                            nodes("//text()[contains(., 'Hotel reservations under')]/ancestor-or-self::p[1]/following-sibling::ul[1]/li"),
                            array_keys($res),
                            re("#Room for\s+([^\n]*?)\s+cancelled#")
                        );
                    },

                    "Guests" => function ($text = '', $node = null, $it = null) {
                        return re("#\s+Guest\(s\)\s+(\d+)#");
                    },

                    "Rooms" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Room\(s\):\s*(\d+)#");
                    },

                    "Rate" => function ($text = '', $node = null, $it = null) {
                        return trim(re("#\s+[^\d+][\d,.]+\s+avg/night#"));
                    },

                    "CancellationPolicy" => function ($text = '', $node = null, $it = null) {
                        return nice(re("#\n\s*Cancellation:(.*?)\s+(?:Enjoy your trip|Car\s*information)#ms"));
                    },

                    "RoomTypeDescription" => function ($text = '', $node = null, $it = null) {
                        $desc = nodes("//*[contains(text(), 'Room description')]/ancestor-or-self::p[1]");
                        $type = [];
                        $info = [];

                        foreach ($desc as $item) {
                            $r = re("#Room description:\s*([^\n]+)#", $item);

                            re("#^(.*?\s+beds*)\s*(.+)$|^(.*?)\-(.+)$#ims", $r);
                            $r = [re(1), $r];

                            $type[] = trim($r[0]);

                            if (isset($r[1])) {
                                $info[] = trim($r[1]);
                            }
                        }

                        return [
                            'RoomType'            => implode("|", $type),
                            'RoomTypeDescription' => implode("|", $info),
                        ];
                    },

                    "Taxes" => function ($text = '', $node = null, $it = null) {
                        return cost(re("#\n\s*Taxes\s*and\s*fees\s+([^\d]+[\d.,]+)#"));
                    },

                    "Total" => function ($text = '', $node = null, $it = null) {
                        return cost(re("#Total\s*due\s*at\s*booking\s+([^\d]+[\d.,]+)#"));
                    },

                    "Currency" => function ($text = '', $node = null, $it = null) {
                        return currency(re("#Total\s*due\s*at\s*booking\s+([^\d]+[\d.,]+)#"));
                    },

                    "Status" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Reservation\s+(cancelled)\n#");
                    },

                    "Cancelled" => function ($text = '', $node = null, $it = null) {
                        return re("#\s+Reservation\s+cancelled\s+#") ? true : false;
                    },

                    "ReservationDate" => function ($text = '', $node = null, $it = null) {
                        return orval(
                            totime(re("#\n\s*This\s*reservation\s*was\s*made\s*on\s*\w+,\s*(\w{3}\s+\d+),\s*(\d{4})\s+(\d+:\d+\s*[APM]{2})#") . ' ' . re(2) . ', ' . re(2)),
                            null
                        );
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
