<?php

namespace AwardWallet\Engine\woodfield\Email;

class It2210739 extends \TAccountCheckerExtended
{
    public $rePlain = "#\n[>\s*]*From\s*:[^\n]*?woodfield|La\s+Quinta#i";
    public $rePlainRange = "";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $rePDF = "";
    public $rePDFRange = "";
    public $reSubject = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $reFrom = "#woodfield#i";
    public $reProvider = "#woodfield#i";
    public $caseReference = "8276";
    public $isAggregator = "0";
    public $fnDateFormat = "";
    public $xPath = "";
    public $mailFiles = "woodfield/it-2210739.eml";
    public $pdfRequired = "0";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    return [$text = $this->setDocument("plain")];
                },

                "#.*?#" => [
                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "R";
                    },

                    "ConfirmationNumber" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Confirmation\s+Number[.\s*:]+([A-Z\d-]+)#");
                    },

                    "HotelName" => function ($text = '', $node = null, $it = null) {
                        return [
                            'HotelName' => re("#^([^\n:]+)\s+(.*?)\s+Phone\s*:\s*([^\n]+)\s+Fax\s*:\s*([^\n]+)#is"),
                            'Address'   => nice(re(2)),
                            'Phone'     => re(3),
                            'Fax'       => re(4),
                        ];
                    },

                    "CheckInDate" => function ($text = '', $node = null, $it = null) {
                        return totime(re("#\n\s*Arrival Date[.\s*:]+([^\n]+)#"));
                    },

                    "CheckOutDate" => function ($text = '', $node = null, $it = null) {
                        return totime(re("#\n\s*Departure Date[.\s*:]+([^\n]+)#"));
                    },

                    "GuestNames" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Reservation Confirmation for ([^\n]+)#");
                    },

                    "Guests" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Adults/Children[.\s*:]+(\d+)/\d+#");
                    },

                    "Kids" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Adults/Children[.\s*:]+\d+/(\d+)#");
                    },

                    "RoomType" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Room Type[.\s*:]+([^\n]+)#");
                    },

                    "Cost" => function ($text = '', $node = null, $it = null) {
                        return cost(re("#\n\s*Room Charges[.\s*:]+([^\n]+)#"));
                    },

                    "Taxes" => function ($text = '', $node = null, $it = null) {
                        return cost(re("#\n\s*Taxes[.\s*:]+([^\n]+)#"));
                    },

                    "Total" => function ($text = '', $node = null, $it = null) {
                        return total(re("#\n\s*Total Estimated Stay[.\s*:]+([^\n]+)#"), "Total");
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
