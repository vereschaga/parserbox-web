<?php

namespace AwardWallet\Engine\sabre\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Engine\WeekTranslate;

class It1812694 extends \TAccountCheckerExtended
{
    public $reFrom = "#sabre#i";
    public $reProvider = "#sabre#i";
    public $rePlain = "#itinerary\s+through\s+Sabre#i";
    public $rePlainRange = "1000";
    public $typesCount = "1";
    public $langSupported = "en";
    public $reSubject = "";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $xPath = "";
    public $mailFiles = "sabre/it-1812694.eml, sabre/it-1812900.eml";
    public $rePDF = "";
    public $rePDFRange = "";
    public $pdfRequired = "0";
    public $eDate;

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    $this->eDate = strtotime($this->parser->getHeader("date"));

                    return splitter("#\n\s*(\w{3},\s*\w{3}\s+\d+\s+Flights:|\w{3},\s*\w{3}\s+\d+[-\s]+\w{3},\s*\w{3}\s+\d+\s+Hotel\s+&\s+Lodging:)#");
                },

                "#Hotel & Lodging#" => [
                    "ConfirmationNumber" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Confirmation\s*:\s*([\d\w\-]+)#");
                    },

                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "R";
                    },

                    "HotelName" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Hotel & Lodging\s*:\s*([^\n]+)#");
                    },

                    "CheckInDate" => function ($text = '', $node = null, $it = null) {
                        $date = re("#\n\s*Check In\s*:\s*([^\n]+)#");

                        if (preg_match("#^\s*([^\d\s,.]+),\s*(\w+) (\d{1,2})\s*$#", $date, $m) && !empty($this->eDate)) {
                            $weeknum = WeekTranslate::number1(WeekTranslate::translate($m[1], 'en'));
                            $date = $m[3] . ' ' . $m[2] . ' ' . date('Y', $this->eDate);
                            $date = EmailDateHelper::parseDateUsingWeekDay($date, $weeknum);
                        } else {
                            $date = totime($date);
                        }

                        return $date;
                    },

                    "CheckOutDate" => function ($text = '', $node = null, $it = null) {
                        $date = re("#\n\s*Check Out\s*:\s*([^\n]+)#");

                        if (preg_match("#^\s*([^\d\s,.]+),\s*(\w+) (\d{1,2})\s*$#", $date, $m) && !empty($this->eDate)) {
                            $weeknum = WeekTranslate::number1(WeekTranslate::translate($m[1], 'en'));
                            $date = $m[3] . ' ' . $m[2] . ' ' . date('Y', $this->eDate);
                            $date = EmailDateHelper::parseDateUsingWeekDay($date, $weeknum);
                        } else {
                            $date = totime($date);
                        }

                        return $date;
                    },

                    "Address" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Address\s*:\s*([^\n]+)#");
                    },

                    "Phone" => function ($text = '', $node = null, $it = null) {
                        return trim(re("#\n\s*Phone\s*:\s*([\d\-+\(\) ]+)#"));
                    },

                    "Fax" => function ($text = '', $node = null, $it = null) {
                        return trim(re("#\s+FAX\s*:\s*([\d\-+\(\) ]+)#"));
                    },

                    "GuestNames" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Itinerary\s+([A-Z ./]+)#", $this->text());
                    },

                    "Rooms" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Room\(s\)\s*:\s*(\d+)#");
                    },

                    "Rate" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Rate\s*:\s*([^\n]+)#");
                    },

                    "CancellationPolicy" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Cancellation\s*:\s*([^\n]+)#");
                    },

                    "RoomType" => function ($text = '', $node = null, $it = null) {
                        return nice(glue(clear("#Room\(s\):[\s\d]+#", re("#\n\s*Room Details\s*:\s*(.*?)\s+Status:#ms"))));
                    },

                    "Status" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Status\s*:\s*([^\n]+)#");
                    },
                ],

                "#Flights:#" => [
                    "RecordLocator" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Airline Confirmation\s*:\s*([^\n]+)#", $this->text());
                    },

                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "T";
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Itinerary\s+([A-Z ./]+)#", $this->text());
                    },

                    "Status" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Status\s*:\s*([^\n]+)#");
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        return [$text];
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            return uberAir(re("#Flights:.*?,\s+([^,\n]+)#"));
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            return re("#\n\s*From\s*:.*?\(([A-Z]{3})\)#");
                        },

                        "DepDate" => function ($text = '', $node = null, $it = null) {
                            $date = re("#^\s*(.+)#");

                            if (preg_match("#^\s*([^\d\s,.]+),\s*(\w+) (\d{1,2})\s*$#", $date, $m) && !empty($this->eDate)) {
                                $weeknum = WeekTranslate::number1(WeekTranslate::translate($m[1], 'en'));
                                $date = $m[3] . ' ' . $m[2] . ' ' . date('Y', $this->eDate);
                                $date = EmailDateHelper::parseDateUsingWeekDay($date, $weeknum);
                            } else {
                                $date = totime($date);
                            }

                            if (!empty($date)) {
                                $dep = strtotime(uberTime(1), $date);
                                $arr = strtotime(uberTime(2), $date);
                            }

                            return [
                                'DepDate' => $dep,
                                'ArrDate' => $arr,
                            ];
                        },

                        "ArrCode" => function ($text = '', $node = null, $it = null) {
                            return re("#\n\s*To\s*:.*?\(([A-Z]{3})\)#");
                        },

                        "Aircraft" => function ($text = '', $node = null, $it = null) {
                            return re("#\n\s*Aircraft\s*:\s*([^\n]+)#");
                        },

                        "TraveledMiles" => function ($text = '', $node = null, $it = null) {
                            return re("#\n\s*Distance \(in Miles\)\s*:\s*(\d+)#");
                        },

                        "Cabin" => function ($text = '', $node = null, $it = null) {
                            return re("#\n\s*Class\s*:\s*([^\n]+)#");
                        },

                        "Duration" => function ($text = '', $node = null, $it = null) {
                            return re("#\n\s*Duration\s*:\s*([^\n]+)#");
                        },

                        "Meal" => function ($text = '', $node = null, $it = null) {
                            return re("#\n\s*Meal\s*:(.*?)\s{2,}#");
                        },

                        "Smoking" => function ($text = '', $node = null, $it = null) {
                            return re("#Smoking:\s*No#") ? false : null;
                        },
                    ],
                ],

                "PostProcessing" => function ($text = '', $node = null, $it = null) {
                    return correctItinerary($it, true);
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
