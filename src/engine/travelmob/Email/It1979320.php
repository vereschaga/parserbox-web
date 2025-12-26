<?php

namespace AwardWallet\Engine\travelmob\Email;

class It1979320 extends \TAccountCheckerExtended
{
    public $rePlain = "#\n[>\s*]*From\s*:[^\n]*?travelmob#i";
    public $rePlainRange = "";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $rePDF = "";
    public $rePDFRange = "";
    public $reSubject = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $reFrom = "#travelmob#i";
    public $reProvider = "#travelmob#i";
    public $caseReference = "";
    public $xPath = "";
    public $mailFiles = "travelmob/it-1979320.eml";
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
                        $conf = null;

                        if ($conf == null) {
                            return CONFNO_UNKNOWN;
                        }
                    },

                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "R";
                    },

                    "HotelName" => function ($text = '', $node = null, $it = null) {
                        return cell("Property Name:", +1);
                    },

                    "CheckInDate" => function ($text = '', $node = null, $it = null) {
                        $node = cell("Travel Dates:", +1);
                        $year = re("#[0-9][0-9][0-9][0-9]#", $node);
                        $node = explode("-", $node);
                        $checkoutdate = explode(",", $node[1]);
                        $checkindate = $node[0] . ", " . $year;
                        $checkoutdate = $checkoutdate[0] . ", " . $year;

                        return [
                            'CheckInDate'  => totime(uberDate($checkindate)),
                            'CheckOutDate' => totime(uberDate($checkoutdate)),
                        ];
                    },

                    "Address" => function ($text = '', $node = null, $it = null) {
                        return cell("Address:", +1);
                    },

                    "Phone" => function ($text = '', $node = null, $it = null) {
                        return cell("Host contact number:", +1);
                    },

                    "GuestNames" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Hello\s*([^\n]+),#");
                    },

                    "Guests" => function ($text = '', $node = null, $it = null) {
                        return cell("No. of Guests:", +1);
                    },

                    "Rate" => function ($text = '', $node = null, $it = null) {
                        $node = cell("Total nightly rate:", +1);
                        $node = re("#[A-Z]+ [0-9]+ per night#", $node);

                        if ($node != null) {
                            return $node;
                        }
                    },

                    "CancellationPolicy" => function ($text = '', $node = null, $it = null) {
                        return cell("Cancellation policy:", +1);
                    },

                    "Total" => function ($text = '', $node = null, $it = null) {
                        $node = total(cell("Total:", +1), "Total");

                        return $node;
                    },

                    "Status" => function ($text = '', $node = null, $it = null) {
                        $node = re("#Your host has accepted your reservation request.#");

                        if ($node != null) {
                            return "confirmed";
                        }
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
