<?php

namespace AwardWallet\Engine\hotwire\Email;

class It2540306 extends \TAccountCheckerExtended
{
    public $rePlain = [
        ['#\n[>\s*]*(?:Von|From)\s*:[^\n]*?hotwire\.com#i', 'blank', ''],
    ];
    public $reHtml = "";
    public $rePDF = "";
    public $reSubject = "";
    public $reFrom = [
        ['#[@.]hotwire#i', 'us', ''],
    ];
    public $reProvider = [
        ['#[@.]hotwire#i', 'us', ''],
    ];
    public $fnLanguage = "";
    public $langSupported = "de";
    public $typesCount = "1";
    public $isAggregator = "0";
    public $caseReference = "";
    public $upDate = "16.03.2015, 15:39";
    public $crDate = "16.03.2015, 15:28";
    public $xPath = "";
    public $mailFiles = "hotwire/it-2540306.eml";
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
                        return re("#\n\s*Hotelbestätigung\s*:\s*([A-Z\d-]+)#");
                    },

                    "HotelName" => function ($text = '', $node = null, $it = null) {
                        return [
                            'HotelName' => re("#\n\s*Hotel\s*\n\s*([^\n]+)\s+(.*?)\n\s*Telefon[:\s]+([\d+\-(\) ]+)\n#s"),
                            'Address'   => nice(re(2)),
                            'Phone'     => re(3),
                        ];
                    },

                    "CheckInDate" => function ($text = '', $node = null, $it = null) {
                        $dates = node("//*[normalize-space(text()) = 'Reisezeitraum']/ancestor::tr[1]/following-sibling::tr[1]/td[1]");

                        return totime(en(uberDateTime($dates)));
                    },

                    "CheckOutDate" => function ($text = '', $node = null, $it = null) {
                        $dates = node("//*[normalize-space(text()) = 'Reisezeitraum']/ancestor::tr[1]/following-sibling::tr[1]/td[1]");

                        return totime(en(uberDateTime($dates, 2)));
                    },

                    "GuestNames" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*(.*?) muss beim Check-in anwesend sein#ix");
                    },

                    "Guests" => function ($text = '', $node = null, $it = null) {
                        return node("//*[normalize-space(text()) = 'Reisezeitraum']/ancestor::tr[1]/following-sibling::tr[1]/td[2]", null, true, "#^(\d+)$#");
                    },

                    "Kids" => function ($text = '', $node = null, $it = null) {
                        return node("//*[normalize-space(text()) = 'Reisezeitraum']/ancestor::tr[1]/following-sibling::tr[1]/td[3]", null, true, "#^(\d+)$#");
                    },

                    "Rooms" => function ($text = '', $node = null, $it = null) {
                        return node("//*[normalize-space(text()) = 'Reisezeitraum']/ancestor::tr[1]/following-sibling::tr[1]/td[4]", null, true, "#^(\d+)#");
                    },

                    "Rate" => function ($text = '', $node = null, $it = null) {
                        return nice(re("#[^\s\d]+[\d,.]+\s*/\s*Nacht#"));
                    },

                    "CancellationPolicy" => function ($text = '', $node = null, $it = null) {
                        return re("#Stornierungsrichtlinie des Hotels\s+([^\n]+)#xi");
                    },

                    "Cost" => function ($text = '', $node = null, $it = null) {
                        return cost(re("#\n\s*Zwischensumme[:\s]+([^\n]+)#ix"));
                    },

                    "Taxes" => function ($text = '', $node = null, $it = null) {
                        return cost(re("#\n\s*Tax recovery charges & fees[:\s]+([^\n]+)#ix"));
                    },

                    "Total" => function ($text = '', $node = null, $it = null) {
                        return total(re("#\n\s*Reise\-Gesamtpreis[:\s]+([^\n]+)#i"), 'Total');
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
        return ["de"];
    }

    public function IsEmailAggregator()
    {
        return false;
    }
}
