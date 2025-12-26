<?php

namespace AwardWallet\Engine\triprewards\Email;

class It2013417 extends \TAccountCheckerExtended
{
    public $rePlain = "#\n[>\s*]*From\s*:[^\n]*?[.@]wyn\.com#i";
    public $rePlainRange = "";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $rePDF = "";
    public $rePDFRange = "";
    public $reSubject = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $reFrom = "#wyn\.#i";
    public $reProvider = "#wyn\.#i";
    public $caseReference = "";
    public $xPath = "";
    public $mailFiles = "triprewards/it-2013417.eml, triprewards/it-2013421.eml";
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
                        return re("#\n\s*Booking reference number\s*:\s*([A-Z\d\-]+)#");
                    },

                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "R";
                    },

                    "CheckInDate" => function ($text = '', $node = null, $it = null) {
                        return totime(re("#\s+Arrival\s+date\s*:\s*([^\n]+)#i") . ',' . clear("#After|Until|\.#", re("#\s+Check-in\s+time\s*:\s*([^\n]+)#i")));
                    },

                    "CheckOutDate" => function ($text = '', $node = null, $it = null) {
                        return totime(re("#\s+Departure\s+date\s*:\s*([^\n]+)#i") . ',' . clear("#After|Until|\.#", re("#\s+Check-out\s+time\s*:\s*([^\n]+)#i")));
                    },

                    "Address" => function ($text = '', $node = null, $it = null) {
                        $text = text(xpath("//text()[contains(., 'Telephone:')]/ancestor-or-self::td[1]"));
                        detach("#See\s+map#", $text);

                        return [
                            'HotelName' => detach("#^\s*([^\n]+)#", $text),
                            'Phone'     => detach("#\n\s*Telephone\s*:\s*([\(\)+\d -]+)#", $text),
                            'Fax'       => detach("#\n\s*Fax\s*:\s*([\(\)+\d -]+)#", $text),
                            'Address'   => nice($text, ','),
                        ];
                    },

                    "GuestNames" => function ($text = '', $node = null, $it = null) {
                        return [re("#\n\s*Cardholder\s*:\s*([^\n]+)#")];
                    },

                    "Guests" => function ($text = '', $node = null, $it = null) {
                        return re("#for\s+(\d+)\s+adult#i");
                    },

                    "Rooms" => function ($text = '', $node = null, $it = null) {
                        return re("#Rooms\s*:\s*(\d+)\s+room#i");
                    },

                    "Rate" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Average daily rate\s*([^\n]+)#");
                    },

                    "CancellationPolicy" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Changes\s+or\s+cancellation\s+of\s+this\s+booking:\s+([^\n]+)#i");
                    },

                    "RoomType" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Room type\s*:\s*([^\n]+)#");
                    },

                    "RoomTypeDescription" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Comments\s*:\s*([^\n]+)#");
                    },

                    "Taxes" => function ($text = '', $node = null, $it = null) {
                        return cost(re("#\n\s*Other\s+taxes\s*\(local\s+taxes\)\s*([^\n]+)#i"));
                    },

                    "Total" => function ($text = '', $node = null, $it = null) {
                        return cost(re("#\n\s*Total cost\s*:\s*([^\n]+)#"));
                    },

                    "Currency" => function ($text = '', $node = null, $it = null) {
                        return currency(re(1));
                    },

                    "Status" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*(Confirmed)\s+booking\s+#");
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
