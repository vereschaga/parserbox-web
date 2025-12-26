<?php

namespace AwardWallet\Engine\jetblue\Email;

class It2036208 extends \TAccountCheckerExtended
{
    public $rePlain = "#\n[>\s*]*From\s*:[^\n]*?jetblue#i";
    public $rePlainRange = "";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $rePDF = "";
    public $rePDFRange = "";
    public $reSubject = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $reFrom = "#jetblue#i";
    public $reProvider = "#jetblue#i";
    public $caseReference = "";
    public $xPath = "";
    public $mailFiles = "jetblue/it-2010215.eml, jetblue/it-2036208.eml";
    public $pdfRequired = "0";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    return xpath("//*[contains(text(), 'Flight') or text() = 'Room']/ancestor::table[1]");
                },

                "#^Flight#" => [
                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "T";
                    },

                    "RecordLocator" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Confirmation\s*:\s*([A-Z\d\-]+)#");
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        return nodes("//*[contains(normalize-space(text()), 'Traveler Names')]/ancestor::thead[1]/following-sibling::tr/td[1]");
                    },

                    "AccountNumbers" => function ($text = '', $node = null, $it = null) {
                        return nodes("//*[contains(normalize-space(text()), 'Traveler Names')]/ancestor::thead[1]/following-sibling::tr/td[2]");
                    },

                    "ReservationDate" => function ($text = '', $node = null, $it = null) {
                        return totime(re("#\n\s*Booking Date\s*([^\n]+)#", $this->text()));
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        return [$text];
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            $in = text(xpath(".//*[contains(text(), 'Flight') or text() = 'Room']/ancestor::table[1]//text()[contains(.,'Plane:')]/ancestor::td[1]"));

                            return [
                                'AirlineName'  => re("#^.*?\s+([A-Z\d]{2})\s*(\d+)\s+(.*?)\s*\(([A-Z])(?: \*)?\)\s*\|\s*Plane\s*:\s*([^\n]+)#", $in),
                                'FlightNumber' => re(2),
                                'Cabin'        => re(3),
                                'BookingClass' => re(4),
                                'Aircraft'     => re(5),
                            ];
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            return re("#\s*\-\s*\(([A-Z]{3})\)#");
                        },

                        "DepDate" => function ($text = '', $node = null, $it = null) {
                            return totime(re("#\s+Departure\s*:\s*([^\n]+)#"));
                        },

                        "ArrCode" => function ($text = '', $node = null, $it = null) {
                            return ure("#\s*\-\s*\(([A-Z]{3})\)#", 2);
                        },

                        "ArrDate" => function ($text = '', $node = null, $it = null) {
                            return totime(re("#\s+Arrive\s*:\s*([^\n]+)#"));
                        },

                        "Seats" => function ($text = '', $node = null, $it = null) {
                            $r = nodes(".//*[contains(text(), 'Flight') or text() = 'Room']/ancestor::table[1]//text()[contains(.,'Plane:')]/ancestor::tr[1]/following-sibling::tr[contains(.,'(Adult)')]");
                            $seats = [];

                            foreach ($r as $seat) {
                                $seats[] = re("#\s+(\d+[A-Z])$#", $seat);
                            }

                            return $seats;
                        },

                        "Duration" => function ($text = '', $node = null, $it = null) {
                            return re("#\n\s*Duration\s*:\s*([^\n]+)#");
                        },
                    ],
                ],

                "#^Room#" => [
                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "R";
                    },

                    "ConfirmationNumber" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Booking Number\s*([A-Z\d\-]+)#", $this->text());
                    },

                    "HotelName" => function ($text = '', $node = null, $it = null) {
                        return [
                            'HotelName' => re("#^Room\s+(.*?)\n\s*Located\s+(.*?)\n\s*Room desc#ms"),
                            'Address'   => nice(re(2), ','),
                        ];
                    },

                    "CheckInDate" => function ($text = '', $node = null, $it = null) {
                        return totime(re("#\n\s*Check-in\s*:\s*([^\n]+)#"));
                    },

                    "CheckOutDate" => function ($text = '', $node = null, $it = null) {
                        return totime(re("#\n\s*Check-out\s*:\s*([^\n]+)#"));
                    },

                    "GuestNames" => function ($text = '', $node = null, $it = null) {
                        return nodes("//*[contains(normalize-space(text()), 'Traveler Names')]/ancestor::thead[1]/following-sibling::tr/td[1]");
                    },

                    "Guests" => function ($text = '', $node = null, $it = null) {
                        return re("#\s+(\d+)\s+Adults?\s+#");
                    },

                    "CancellationPolicy" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Change/Cancellation Policy\s*:\s*(.*?)\s+No changes will be#ms", $this->text());
                    },

                    "RoomType" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Room Type\s*:\s*([^\n]+)#");
                    },

                    "RoomTypeDescription" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Room description\s*([^\n]+)#");
                    },

                    "ReservationDate" => function ($text = '', $node = null, $it = null) {
                        return totime(re("#\n\s*Booking Date\s*([^\n]+)#", $this->text()));
                    },
                ],

                "PostProcessing" => function ($text = '', $node = null, $it = null) {
                    $it = uniteAirSegments($it);

                    $total = cost(re("#\n\s*Payments Received\s*:\s*([^\n]+)#", $this->text()));
                    $currency = currency(re(1));
                    $EarnedAwards = re("#\n\s*Total Package Points Earned\s*([^\n]+)#", $this->text());

                    if (!empty($total) && !empty($currency)) {
                        if (count($it) == 1) {
                            switch ($it[0]['Kind']) {
                                case "T": $it[0]['TotalCharge'] = $total;
                                // no break
                                case "R": $it[0]['Total'] = $total;
                            }
                            $it[0]['Currency'] = $currency;
                            $it[0]['Tax'] = cost(re("#\n\s*Tax and Fees\s*([^\n]+)#", $this->text()));
                            $it[0]['EarnedAwards'] = $EarnedAwards;
                        } elseif (count($it) > 1) {
                            $this->parsedValue('TotalCharge', ["Amount" => $total, "Currency" => $currency, "EarnedAwards" => $EarnedAwards]);
                        }
                    }

                    return $it;
                },
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
