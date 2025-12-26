<?php

namespace AwardWallet\Engine\supershuttle\Email;

class It extends \TAccountCheckerExtended
{
    public $rePlain = "#\n[>\s*]*From\s*:[^\n]*?supershuttle#i";
    public $rePlainRange = "";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $rePDF = "";
    public $rePDFRange = "";
    public $reSubject = "";
    public $langSupported = "en";
    public $typesCount = "3";
    public $reFrom = "#supershuttle#i";
    public $reProvider = "#supershuttle#i";
    public $caseReference = "";
    public $xPath = "";
    public $mailFiles = "supershuttle/it.eml";
    public $pdfRequired = "0";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    return null; // covered by 878 and 536

                    $arrive = stripos($text, "Arrival itinerary");
                    $depart = stripos($text, "Departure itinerary");

                    if ($arrive && $depart) {
                        return xpath("//*[contains(text(), 'itinerary') or contains(text(), 'Itinerary')]/ancestor-or-self::tr[1]/following-sibling::tr");
                    } else {
                        return [$text];
                    }
                },

                "#.*?#" => [
                    "Number" => function ($text = '', $node = null, $it = null) {
                        return cell("Confirmation Number", +1, 0);
                    },

                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "L";
                    },

                    "PickupDatetime" => function ($text = '', $node = null, $it = null) {
                        $pickup_date = cell("Pickup Date/Time:", +1, 0);
                        $flight_date = cell("Flight Date/Time", +1, 0);

                        $guest_location = node("//*[contains(text(), 'Guest Information')]/ancestor-or-self::tr[1]/following-sibling::tr[1]/td/table/tbody/tr[1]");

                        $guest_location = str_replace("Address ", "", $guest_location);

                        //$arrive = stripos($text, "Arrival itinerary");
                        //$depart = stripos($text, "Departure itinerary");

                        if ($pickup_date == null) {
                            return [
                                'PickupDatetime'  => totime(uberdatetime($flight_date)),
                                'PickupLocation'  => cell("Airport", +1, 0),
                                'DropoffLocation' => $guest_location,
                                'DropoffDatetime' => MISSING_DATE,
                            ];
                        } else {
                            return [
                                'PickupDatetime'  => totime(uberdatetime($pickup_date)),
                                'PickupLocation'  => $guest_location,
                                'DropoffLocation' => cell("Airport", +1, 0),
                                'DropoffDatetime' => totime(uberdatetime($flight_date)),
                            ];
                        }
                    },

                    "DropoffPhone" => function ($text = '', $node = null, $it = null) {
                        return node("//*[contains(text(), 'Guest Information')]/ancestor-or-self::tr[1]/following-sibling::tr[1]/td/table/tbody/tr[2]");
                    },

                    "RenterName" => function ($text = '', $node = null, $it = null) {
                        return node("//strong[contains(text(), 'Dear')]/following-sibling::strong[1]");
                    },

                    "TotalCharge" => function ($text = '', $node = null, $it = null) {
                        $total = cost(cell("Total", +1, 0)) ?: cost(cell("Roundtrip total fare", +1, 0));

                        return $total;
                    },

                    "Currency" => function ($text = '', $node = null, $it = null) {
                        return currency(cell("Total", +1, 0));
                    },

                    "Discount" => function ($text = '', $node = null, $it = null) {
                        return cost(cell("DISCOUNT", +1, 0));
                    },
                ],

                "#.*#" => [
                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "R";
                    },
                ],
            ],
        ];
    }

    public static function getEmailTypesCount()
    {
        return 3;
    }

    public static function getEmailLanguages()
    {
        return ["en"];
    }
}
