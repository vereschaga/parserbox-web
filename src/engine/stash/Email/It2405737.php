<?php

namespace AwardWallet\Engine\stash\Email;

class It2405737 extends \TAccountCheckerExtended
{
    public $reBody = "Stash Hotel";
    public $reBody2 = "Reservation Confirmation";
    public $reFrom = "reservations@cedarbrooklodge.com";
    public $reSubject = "Reservation Confirmation";

    public $mailFiles = "stash/it-2405737.eml, stash/it-2863183.eml, stash/it-3494916.eml";

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
                        return re("#Confirmation(?>\s+Number)?:\s*([\w\-]+)#");
                    },

                    "HotelName" => function ($text = '', $node = null, $it = null) {
                        $hotelName = null;

                        if ($buf = re("#\'ve\s+booked\s+([^\n]+?)\s+for\s+your\s+upcoming\s+stay\s+in\s+([^\n]+?)\.#")) {
                            $hotelName = $buf . ", " . re(2);
                        }
                        $hotelName = orval(
                            $hotelName,
                            nice(re("#\n\s*Thank\s+you\s+for\s+choosing\s+(.{1,150}?)\s+for\s+your\s+upcoming#s")),
                            null
                        );

                        return $hotelName;
                    },

                    "CheckInDate" => function ($text = '', $node = null, $it = null) {
                        $date = re("#\n\s*(?>Date\s+of\s+Arrival|Arrival\s+Date):\s*(?>\w+,\s*)?([^\n]+?\d{4})\s+#i");
                        $time = preg_replace("#^(\d+)\s+(\w+)$#", "0\\1:00 \\2", re("#\n\s*Check-in\s+Time:\s*([^\n]+?\s\w+)\s*?\n#i"));
                        $time = preg_replace("#\b\d*(\d{2}:\d{2})\b#", "\\1", $time);
                        $time = preg_replace("#NOON$#i", "PM", $time);

                        return strtotime($date . " " . $time);
                    },

                    "CheckOutDate" => function ($text = '', $node = null, $it = null) {
                        $date = re("#\n\s*(?>Date\s+of\s+Departure|Departure\s+Date):\s*(?>\w+,\s*)?([^\n]+?\d{4})\s+#i");
                        $time = preg_replace("#^(\d+)\s+(\w+)$#", "0\\1:00 \\2", re("#\n\s*Check-out\s+Time:\s*([^\n]+?\s\w+)\s*?\n#i"));
                        $time = preg_replace("#\b\d*(\d{2}:\d{2})\b#", "\\1", $time);
                        $time = preg_replace("#NOON$#i", "PM", $time);

                        return strtotime($date . " " . $time);
                    },

                    "Address" => function ($text = '', $node = null, $it = null) {
                        if ($location = node("//a[normalize-space(text())='found here']/@href")) {
                            $http2 = clone $this->http;
                            $http2->getUrl($location);

                            if ($address = $http2->FindSingleNode("(//*[contains(@class, 'Address') or contains(@class, 'address')])[1]")) {
                                return $address;
                            }
                        }

                        return orval(
                            nice(re("#\n\s*The\s+Shores\s+Reservation\s+Team\s+(.+?)\s+\(Tel\)#is"), ","),
                            null
                        );
                    },

                    "Phone" => function ($text = '', $node = null, $it = null) {
                        return orval(
                            re("#any\s+changes\s+at\s+(.+?)\.#"),
                            preg_replace("#\.#", "-", re("#\n\s*\(Tel\)\s+([\d.\-\(\)]+)#"))
                        );
                    },

                    "Fax" => function ($text = '', $node = null, $it = null) {
                        if ($buf = re("#\n\s*\(Tel\)\s+.+?\(Fax\)\s+([\d.\-\(\)]+)#")) {
                            return preg_replace("#\.#", "-", $buf);
                        }
                    },

                    "GuestNames" => function ($text = '', $node = null, $it = null) {
                        return re("#Guest\s+Name:\s+([^\n]+?)\s*?\n#");
                    },

                    "Guests" => function ($text = '', $node = null, $it = null) {
                        if ($num = re("#Number\s+of\s+Guests:\s*(\d+)#")) {
                            return intval($num);
                        }
                    },

                    "Rate" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Room\s+Rate:\s*([^\n]+?)\s*?\n#i");
                    },

                    "CancellationPolicy" => function ($text = '', $node = null, $it = null) {
                        return nice(orval(
                            re("#\n\s*Cancellation:\s*(.+?\.)\s+[^:\n]{1,25}:\s*?\n#s"),
                            re("#\n\s*Check-out\s+Time:\s*[^\n]+\s+.+?\n\s*?\n\s*([^\n]*?cancel.+?)(?:\s*?\n){2}#is")
                        ));
                    },

                    "RoomType" => function ($text = '', $node = null, $it = null) {
                        return re("#Room\s+Type:\s*([^\n]+)\s*?\n#");
                    },

                    "Total" => function ($text = '', $node = null, $it = null) {
                        return total(re("#\n\s*Total\s+Cost(?>\s+including\s+tax)?\s*:\s*([^\n]+?)\s*?\n#"), 'Total');
                    },

                    "Currency" => function ($text = '', $node = null, $it = null) {
                        return currency(orval(
                            re("#\n\s*Self\s+Parking:\s*(.+?\d)\s#i"),
                            re("#\n\s*Room\s+Rate:\s*([^\n]+?)\s*?\n#i")
                        ));
                    },
                ],
            ],
        ];
    }

    public function detectEmailFromProvider($from)
    {
        return strpos($from, $this->reFrom) !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        return strpos($body, $this->reBody) !== false && strpos($body, $this->reBody2) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return strpos($headers["from"], $this->reFrom) !== false && strpos($headers["subject"], $this->reSubject) !== false;
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
