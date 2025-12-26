<?php

namespace AwardWallet\Engine\venere\Email;

class It2437515 extends \TAccountCheckerExtended
{
    public $rePlain = [
        ['#\n[>\s*]*From\s*:[^\n]*?venere#i', 'us', ''],
    ];
    public $reHtml = "";
    public $rePDF = "";
    public $reSubject = "";
    public $reFrom = [
        ['#venere#i', 'us', ''],
    ];
    public $reProvider = [
        ['#venere#i', 'us', ''],
    ];
    public $fnLanguage = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $isAggregator = "0";
    public $caseReference = "";
    public $upDate = "05.02.2015, 23:42";
    public $crDate = "05.02.2015, 23:32";
    public $xPath = "";
    public $mailFiles = "venere/it-2437515.eml";
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
                        return re("#\n\s*RESERVATIONSNUMMER\s*:\s*([A-Z\d\-]+)#");
                    },

                    "HotelName" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*HOTEL\s*:\s*([^\n]+)#");
                    },

                    "CheckInDate" => function ($text = '', $node = null, $it = null) {
                        $times = clear("#\.#", ure("#Check\-in\s*:\s*([^\n]+)#"), ':');

                        return totime(en(re("#\n\s*ANREISETAG\s*:\s*([^\n]+)#")) . ',' . uberTime($times));
                    },

                    "CheckOutDate" => function ($text = '', $node = null, $it = null) {
                        $times = clear("#\.#", ure("#Check\-out\s*:\s*([^\n]+)#"), ':');

                        return totime(en(re("#\n\s*ABREISETAG\s*:\s*([^\n]+)#")) . ',' . uberTime($times));
                    },

                    "Address" => function ($text = '', $node = null, $it = null) {
                        return nice(re("#\n\s*ADRESSE\s*:\s*([^\n]+)#") . ',' . re("#\n\s*ORT\s*:\s*([^\n]+)#"));
                    },

                    "Phone" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*TELEFONNUMMER\s*:\s*([^\n]+)#");
                    },

                    "Fax" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*FAXNUMMER\s*:\s*([^\n]+)#");
                    },

                    "GuestNames" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*NAME\s*:\s*([^\n]+)#");
                    },

                    "Rooms" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*ERWACHSENE\s*:\s*(\d+)#");
                    },

                    "CancellationPolicy" => function ($text = '', $node = null, $it = null) {
                        return node("//*[contains(text(), 'Stornobedingungen')]/ancestor-or-self::td[1]", null, true, "#^Stornobedingungen\s*(.+)$#s");
                    },

                    "Total" => function ($text = '', $node = null, $it = null) {
                        return total(re("#\n\s*Preis\s+([\d.,]+\s*[A-Z]+)#"), 'Total');
                    },

                    "Status" => function ($text = '', $node = null, $it = null) {
                        return re("#(Bestätigte)#i", $this->parser->getSubject()) ? re(1) : null;
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
