<?php

namespace AwardWallet\Engine\kayak\Email;

class It19 extends \TAccountCheckerExtended
{
    public $rePlain = "#\n[>\s]*From\s*:[^\n]*?kayak#i";
    public $rePlainRange = "";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $rePDF = "";
    public $rePDFRange = "";
    public $reSubject = "#Your KAYAK booking#i";
    public $langSupported = "en";
    public $typesCount = "1";
    public $reFrom = "";
    public $reProvider = "";
    public $xPath = "";
    public $mailFiles = "kayak/it-1564218.eml, kayak/it-1570586.eml, kayak/it-1876279.eml, kayak/it-19.eml";
    public $pdfRequired = "";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    return [$text];
                },

                "#.*?#" => [
                    "ConfirmationNumber" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Confirmation number\s*:\s*([\w\d\-]+)#i");
                    },

                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "R";
                    },

                    "HotelName" => function ($text = '', $node = null, $it = null) {
                        $r = orval(
                            nodes("//*[contains(text(), 'details')]/ancestor-or-self::table[1]//text()"),
                            nodes("//img[contains(@src, 'stars')]/ancestor-or-self::table[1]//text()")
                        );

                        $r = filter($r);

                        return [
                            'HotelName' => array_shift($r),
                            'Address'   => trim(clear("#\s+details\s*#", glue($r)), ', '),
                        ];
                    },

                    "CheckInDate" => function ($text = '', $node = null, $it = null) {
                        $anchor = strtotime(re("#Booking Created:\s*([^\n]+)#"));
                        $year = date('Y', $anchor);

                        $dateIn = re("#\n\s*Check\-in:\s*([^\n]+)#");

                        if (!preg_match("#\b\d{4}\b#", $dateIn) && $year > 2000) {
                            $dateIn .= ' ' . $year;
                        }

                        if (preg_match("#\b\d{4}\b#", $dateIn)) {
                            $in = strtotime($dateIn);
                        } else {
                            $in = false;
                        }

                        $dateOut = re("#\n\s*Check\-out:\s*([^\n]+)#");

                        if (!preg_match("#\b\d{4}\b#", $dateOut) && $year > 2000) {
                            $dateOut .= ' ' . $year;
                        }

                        if (preg_match("#\b\d{4}\b#", $dateOut)) {
                            $out = strtotime($dateOut);
                        } else {
                            $out = false;
                        }

                        if (!empty($in) && !empty($out) && $in < $anchor) {
                            $in = strtotime('+1 year', $in);
                            $out = strtotime('+1 year', $out);
                        }

                        return [
                            'CheckInDate'  => $in,
                            'CheckOutDate' => $out,
                        ];
                    },

                    "GuestNames" => function ($text = '', $node = null, $it = null) {
                        if (preg_match_all('#Room\s+\d+:\s+(.*?)\s+/#i', re('#Guest\s*\n\s*(.*)#is'), $m)) {
                            return $m[1];
                        } else {
                            return re("#Guest\s+([^\n]+)#");
                        }
                    },

                    "Guests" => function ($text = '', $node = null, $it = null) {
                        return re("#(\d+)\s*adult#");
                    },

                    "Rooms" => function ($text = '', $node = null, $it = null) {
                        return re("#(\d+)\s*room#");
                    },

                    "Rate" => function ($text = '', $node = null, $it = null) {
                        return re("#(.[\d.]+\s*avg\.*\s*per\s*night)#");
                    },

                    "CancellationPolicy" => function ($text = '', $node = null, $it = null) {
                        return nice(glue(re("#\n\s*Cancellation\s+(.*?)\s+Terms & Conditions#ms")));
                    },

                    "RoomType" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Room\s+([^\n]+)#");
                    },

                    "Cost" => function ($text = '', $node = null, $it = null) {
                        return cost(re("#per\s*night\)\s+([^\n]+)#"));
                    },

                    "Taxes" => function ($text = '', $node = null, $it = null) {
                        return cost(re("#\n\s*Tax Recovery Charge & Service Fees\s+([^\n]+)#"));
                    },

                    "Total" => function ($text = '', $node = null, $it = null) {
                        return cost(re("#\n\s*Total Charged\s+([^\n]+)#"));
                    },

                    "Currency" => function ($text = '', $node = null, $it = null) {
                        return currency(re("#\n\s*Total Charged\s+([^\n]+)#"));
                    },

                    "ReservationDate" => function ($text = '', $node = null, $it = null) {
                        return strtotime(re("#Booking Created:\s*([^\n]+)#"));
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
