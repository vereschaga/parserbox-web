<?php

namespace AwardWallet\Engine\goldcrown\Email;

class It1956687 extends \TAccountCheckerExtended
{
    public $rePlain = "#\n(?:[>\s*]*From\s*:|.*?)[^\n]*?bestwestern#i";
    public $rePlainRange = "";
    public $reHtml = "Best Western";
    public $reHtmlRange = "";
    public $rePDF = "";
    public $rePDFRange = "";
    public $reSubject = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $reFrom = "#bestwestern#i";
    public $reProvider = "#bestwestern#i";
    public $caseReference = "6994";
    public $isAggregator = "";
    public $xPath = "";
    public $mailFiles = "goldcrown/it-1835667.eml, goldcrown/it-1955101.eml, goldcrown/it-1956687.eml, goldcrown/it-2.eml, goldcrown/it-2139880.eml, goldcrown/it-2139883.eml, goldcrown/it-2139891.eml, goldcrown/it-2139893.eml, goldcrown/it-3.eml, goldcrown/it-4.eml, goldcrown/it-6376203.eml, goldcrown/it-6411333.eml, goldcrown/it-8557723.eml";
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
                        return re("#\s+(?:Reservation Change number|Confirmation Number|Cancellation number)\s*:\s*([\d\w\-]+)#i");
                    },

                    "HotelName" => function ($text = '', $node = null, $it = null) {
                        return orval(
                            node("(//*[contains(text(), 'Check-in:')]/ancestor-or-self::td[2]/preceding-sibling::td[1]//*[self::strong or self::b])[1]"),
                            node("(//*[contains(text(), 'Total Rooms:')]/ancestor::tr[2]/td[1]/following-sibling::td[1]//*[self::b or self::strong])[1]")
                        );
                    },

                    "CheckInDate" => function ($text = '', $node = null, $it = null) {
                        return totime(uberDateTime(str_replace(".", "", re("#\n\s*Check-in\s*:\s*([^\n]+?)(?:\n|Check-out)#"))));
                    },

                    "CheckOutDate" => function ($text = '', $node = null, $it = null) {
                        return totime(uberDateTime(str_replace(".", "", re("#\s*Check-out\s*:\s*([^\n]+)#"))));
                    },

                    "Address" => function ($text = '', $node = null, $it = null) {
                        $addr = orval(
                            node("(//*[contains(text(), 'Check-in:')]/ancestor-or-self::td[2]/preceding-sibling::td[1]//*[self::strong or self::b])[1]/ancestor::*[1]"),
                            node("//*[contains(text(), 'Total Rooms:')]/ancestor::tr[2]/td[1]/following-sibling::td[1]")
                        );

                        detach("#{$it['HotelName']}\s*#", $addr);
                        $phone = detach("#\s*Phone\s*:\s*([\d+/\-]+).*#s", $addr);

                        return [
                            'Phone'   => $phone,
                            'Address' => $addr,
                        ];
                    },

                    "GuestNames" => function ($text = '', $node = null, $it = null) {
                        return [re("#-\s+(.+)#", node("//h2[starts-with(normalize-space(.),'Reservation Summary')]"))];
                    },

                    "Guests" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Total Occupants\s*:\s*(\d+)#");
                    },

                    "Rooms" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Total Rooms\s*:\s*(\d+)#");
                    },

                    "CancellationPolicy" => function ($text = '', $node = null, $it = null) {
                        return re("#\s*Cancellation Policy\s*:\s*([^\n]+?)(?:\n|Child Policy)#");
                    },

                    "RoomType" => function ($text = '', $node = null, $it = null) {
                        $text = text(xpath("//*[contains(text(), 'Room Subtotal')]/ancestor::tr[3][not(contains(.,'(Cancelled)'))]"));

                        if (!$text) { // all cancelled
                            $text = text(xpath("//*[contains(text(), 'Room Subtotal')]/ancestor::tr[3]"));
                        }

                        $types = [];
                        re("#(?:^|\n\s*)Room\s+\d+\s*\-\s*([^\n]+?)(?:\n|Room Subtotal)#", function ($m) use (&$types) {
                            $types[] = $m[1];
                        }, $text);

                        return implode('|', $types);
                    },

                    "RoomTypeDescription" => function ($text = '', $node = null, $it = null) {
                        $text = text(xpath("//*[contains(text(), 'Room Subtotal')]/ancestor::tr[3][not(contains(.,'(Cancelled)'))]"));

                        if (!$text) { // all cancelled
                            $text = text(xpath("//*[contains(text(), 'Room Subtotal')]/ancestor::tr[3]"));
                        }

                        $types = [];
                        re("#(?:^|\n\s*)Room Details\s*:\s*(.*?)\s+(?:Check\-in|Total\s+Occupants)\s*:#is", function ($m) use (&$types) {
                            $types[] = $m[1];
                        }, $text);

                        return implode('|', $types);
                    },

                    "Taxes" => function ($text = '', $node = null, $it = null) {
                        return cost(re("#\n\s*(?:Estimated|Other) Taxes & Fees\s*:\s*([^\n]+)#"));
                    },

                    "Total" => function ($text = '', $node = null, $it = null) {
                        return cost(re("#\n\s*Total Stay\s*:\s*([^\n]+)#"));
                    },

                    "Currency" => function ($text = '', $node = null, $it = null) {
                        return currency(re("#\n\s*Total Stay\s*:\s*([^\n]+)#"));
                    },

                    "Status" => function ($text = '', $node = null, $it = null) {
                        return orval(
                            re("#confirms\s+your\s+reservation\s+(\w+)#"),
                            re("#confirms\s+(\w+)\s+in\s+reservation#"),
                            re("#Your\s+reservation\s+is\s+(\w+)#")
                        );
                    },

                    "Cancelled" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Cancellation\s+number\s*:\s*[A-Z\d\-]+#") ? true : false;
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
