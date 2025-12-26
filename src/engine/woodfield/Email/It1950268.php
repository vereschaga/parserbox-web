<?php

namespace AwardWallet\Engine\woodfield\Email;

class It1950268 extends \TAccountCheckerExtended
{
    public $rePlain = "#\n[>\s*]*From\s*:[^\n]*?LaQuinta#i";
    public $rePlainRange = "";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $rePDF = "";
    public $rePDFRange = "";
    public $reSubject = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $reFrom = "#LaQuinta#i";
    public $reProvider = "#LaQuinta#i";
    public $caseReference = "";
    public $xPath = "";
    public $mailFiles = "woodfield/it-1950268.eml";
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
                        return re("#\n\s*Confirmation[\#:\s]+([A-Z\d\-]+)\s*\n#");
                    },

                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "R";
                    },

                    "HotelName" => function ($text = '', $node = null, $it = null) {
                        return [
                            'HotelName' => re("#\n\s*(La Quinta Inn[^\n]+)\s+(.*?)\n\s*([\d\-+\(\) ]+)#ims"),
                            'Address'   => nice(re(2), ','),
                            'Phone'     => re(3),
                        ];
                    },

                    "CheckInDate" => function ($text = '', $node = null, $it = null) {
                        return totime(re("#\n\s*Arrival Date\s*:\s*([^\n]+)#"));
                    },

                    "CheckOutDate" => function ($text = '', $node = null, $it = null) {
                        return totime(re("#\n\s*Departure Date\s*:\s*([^\n]+)#"));
                    },

                    "GuestNames" => function ($text = '', $node = null, $it = null) {
                        return nodes("//*[contains(text(), 'Confirmation#')]/ancestor::tr[1]/following-sibling::tr[1]/td[contains(.,' ')][1]");
                    },

                    "Guests" => function ($text = '', $node = null, $it = null) {
                        return [
                            'Guests' => re("#\n\s*Adults/Children\s+(\d+)/(\d+)#"),
                            'Kids'   => re(2),
                        ];
                    },

                    "RoomType" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*RoomType Reserved\s*:\s*([^\n]+)#");
                    },

                    "Cost" => function ($text = '', $node = null, $it = null) {
                        return [
                            'Cost'  => cost(re("#\n\s*Room Charges\s*:\s*Taxes\s*:\s*([^\n]+)\s+([^\n]+)#")),
                            'Taxes' => cost(re(2)),
                        ];
                    },

                    "Total" => function ($text = '', $node = null, $it = null) {
                        return cost(re("#\n\s*Total Estimated Stay\s*:\s*([^\n]+)#"));
                    },

                    "Currency" => function ($text = '', $node = null, $it = null) {
                        return currency(re(1));
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
