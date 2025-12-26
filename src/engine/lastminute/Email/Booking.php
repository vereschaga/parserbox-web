<?php

namespace AwardWallet\Engine\lastminute\Email;

class Booking extends \TAccountCheckerExtended
{
    public $reFrom = "#[.@]lastminute\.#i";
    public $reProvider = "#[.@]lastminute\.#i";
    public $rePlain = "#Thank\s+you\s+for\s+booking\s+your\s+holiday\s+through\s+lastminute\.com#i";
    public $rePlainRange = "";
    public $typesCount = "1";
    public $langSupported = "en";
    public $reSubject = "";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $xPath = "";
    public $mailFiles = "lastminute/it-1731862.eml";
    public $rePDF = "";
    public $rePDFRange = "";
    public $pdfRequired = "0";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    $text = $this->setDocument('application/pdf', 'simpletable');

                    $this->fullText = $text;

                    $reservations = [];

                    $xpath = '(//text()[contains(., "Passenger") and contains(., "List")])[1]/ancestor::tr[1]//text()[string-length(.) > 1 and not(contains(., "Passenger"))]';
                    $this->travelers = array_values(array_filter(nodes($xpath)));

                    $xpath = '//tr[contains(., "Departing")]/following-sibling::tr[./following-sibling::tr[contains(., "reference")] and not(./preceding-sibling::tr[contains(., "reference")]) and not(contains(., "reference"))]';
                    $airReservations = nodes($xpath);

                    foreach ($airReservations as &$value) {
                        $reservations[] = 'Flight ' . $value;
                    }
                    $this->flightReference = re('#Flight\s+reference\s+is\s+([\w\-]+)#i');

                    $this->bookingReference = re('#Booking\s+reference:\s+([\w\-]+)#i');

                    $xpath = '(//tr[contains(., "Accommodation")])[1]/following-sibling::tr[position() < 3]';
                    $hotelReservation = implode("\n", nodes($xpath));

                    if ($hotelReservation) {
                        $reservations[] = $hotelReservation;
                    }

                    return $reservations;
                },

                "#Rooms#i" => [
                    "ConfirmationNumber" => function ($text = '', $node = null, $it = null) {
                        return $this->bookingReference;
                    },

                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "R";
                    },

                    "HotelName" => function ($text = '', $node = null, $it = null) {
                        if (preg_match('#(\d+)/(\d+)/(\d+)\s+(\d+)\s+(.*)\s+\((.*)\)#', $text, $m)) {
                            $arrivalDate = strtotime($m[1] . '.' . $m[2] . '.20' . $m[3]);
                            $hotelName = $m[5];
                            $regex = '#Accommodation\s+Voucher\s+.*?' . $hotelName . '\s+(.*?)\s+Lead\s+Passenger#s';
                            $address = re($regex, $this->fullText);

                            return [
                                'CheckInDate'  => $arrivalDate,
                                'CheckOutDate' => strtotime('+' . $m[4] . ' days', $arrivalDate),
                                'HotelName'    => $hotelName,
                                'Address'      => $address,
                                'RoomType'     => $m[6],
                            ];
                        }
                    },
                ],

                "#Flight#i" => [
                    "RecordLocator" => function ($text = '', $node = null, $it = null) {
                        return $this->flightReference;
                    },

                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "T";
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        return $this->travelers;
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        return [$text];
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            $regex = '#';
                            $regex .= 'Flight\s+(\w+?)(\d+)\s+';
                            $regex .= '(.*)\s+';
                            $regex .= '(\d+\s+\w+\s*\'\d+\s+\d+:\d+)\s+';
                            $regex .= '(.*)\s+';
                            $regex .= '(\d+\s+\w+\s*\'\d+\s+\d+:\d+)';
                            $regex .= '#';

                            if (preg_match($regex, $text, $m)) {
                                return [
                                    'AirlineName'  => $m[1],
                                    'FlightNumber' => $m[2],
                                    'DepName'      => $m[3],
                                    'DepDate'      => strtotime(str_replace("'", '20', $m[4])),
                                    'ArrName'      => $m[5],
                                    'ArrDate'      => strtotime(str_replace("'", '20', $m[6])),
                                ];
                            }
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
                        },

                        "ArrCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
                        },
                    ],
                ],

                "#Flight#" => [
                    "RecordLocator" => function ($text = '', $node = null, $it = null) {
                        if (preg_match('#Flight\s+(\w+?)(\d+)#', $text, $m)) {
                            return [
                                'AirlineName'  => $m[1],
                                'FlightNumber' => $m[2],
                            ];
                        }
                    },

                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "T";
                    },
                ],

                "PostProcessing" => function ($text = '', $node = null, $it = null) {
                    return uniteAirSegments($it);
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
