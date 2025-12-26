<?php

namespace AwardWallet\Engine\uniglobe\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Engine\WeekTranslate;

class Invoice extends \TAccountCheckerExtended
{
    public $rePlain = [
        ['#Contact\s+Us:\s+UNIGLOBE(?:\s+Bon\s+Voyage)?\s+Travel#i', 'us', '/1'],
    ];
    public $reHtml = "";
    public $rePDF = "";
    public $reSubject = "";
    public $reFrom = [
        ['#paul@uniglobeone\.com#i', 'us', ''],
    ];
    public $reProvider = [
        ['#uniglobeone\.com#i', 'us', ''],
    ];
    public $fnLanguage = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $isAggregator = "";
    public $caseReference = "";
    public $upDate = "18.02.2016, 12:35";
    public $crDate = "";
    public $xPath = "";
    public $mailFiles = "uniglobe/it-1.eml, uniglobe/it-2.eml, uniglobe/it-3.eml, uniglobe/it-3545694.eml, uniglobe/it-4.eml, uniglobe/it-5.eml, uniglobe/it-6.eml";
    public $re_catcher = "#.*?#";
    public $pdfRequired = "0";

    private $accountNumbers = [];
    private $passengers = [];
    private $date;

    public function processors()
    {
        return [
            "#.*?#" => [
                "#AIR -#" => [
                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "T";
                    },

                    "RecordLocator" => function ($text = '', $node = null, $it = null) {
                        return re('#Check\s+In\s+Confirmation:\s+([\w\-]+)#');
                    },

                    "AccountNumbers" => function ($text = '', $node = null, $it = null) {
                        return $this->accountNumbers;
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        return $this->passengers;
                    },

                    "Status" => function ($text = '', $node = null, $it = null) {
                        return nice(re('#Status:\s+(.*)#'));
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        return [$text];
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            // AIR - Air Canada Flight AC246 Economy Class
                            if (preg_match('/AIR\s+-\s+(?<airlineFull>.*?)\s+Flight\s+(?<airline>[A-Z][A-Z\d]|[A-Z\d][A-Z])(?<flightNumber>\d+)\s+(?<cabin>.*)\s+Class/', $text, $m)) {
                                return ['AirlineName' => $m['airline'], 'FlightNumber' => $m['flightNumber'], 'Cabin' => $m['cabin']];
                            }
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
                        },

                        "DepName" => function ($text = '', $node = null, $it = null) {
                            $res = null;

                            foreach (['Dep' => 'Depart', 'Arr' => 'Arrive'] as $key => $value) {
                                $regex = "#{$value}:\s+(?P<Time>\d{1,2}:\d{2}(?:\s*[AaPp][Mm])?),\s+(?<week>\w+),\s+(?P<Month>\w+)\s+(?P<Day>\d+)\s+(?P<Name>.*)#";

                                if (preg_match($regex, $text, $m)) {
                                    $weeknum = WeekTranslate::number1(WeekTranslate::translate($m['week'], 'en'));
                                    $dateStr = $m['Day'] . ' ' . $m['Month'] . ' ' . date('Y', $this->date);
                                    $date = EmailDateHelper::parseDateUsingWeekDay($dateStr, $weeknum);
                                    $res[$key . 'Date'] = strtotime($m['Time'], $date);

                                    $airportName = nice(trim($m['Name'], '>'));

                                    if (preg_match('/(.+)-\s*Terminal\s+([A-Z\d\s]+?)\s*$/i', $airportName, $matches)) { // Vancouver Intl Airport-Terminal M
                                        $res[$key . 'Name'] = $matches[1];
                                        $res[($key === 'Dep' ? 'Departure' : 'Arrival') . 'Terminal'] = $matches[2];
                                    } else {
                                        $res[$key . 'Name'] = $airportName;
                                    }
                                }
                            }

                            return $res;
                        },

                        "ArrCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
                        },

                        "BookingClass" => function ($text = '', $node = null, $it = null) {
                            $result = [];

                            $bookingClass = nice(re('/Booking\s+Class:\s+(.*)/'));

                            if (preg_match('/^([A-Z]{1,2})\s*\(([\w\s]+)\)/u', $bookingClass, $matches)) { // S (Economy)
                                $result['BookingClass'] = $matches[1];
                                $result['Cabin'] = $matches[2];
                            } elseif (preg_match('/^[A-Z]{1,2}$/', $bookingClass)) {
                                $result['BookingClass'] = $bookingClass;
                            }

                            return $result;
                        },

                        "Aircraft" => function ($text = '', $node = null, $it = null) {
                            return nice(re('#Equipment:\s+(.*)#'));
                        },

                        "Seats" => function ($text = '', $node = null, $it = null) {
                            $seat = re('#Seat:\s+(\S+)#');

                            return $seat ? [$seat] : null;
                        },

                        "Duration" => function ($text = '', $node = null, $it = null) {
                            return nice(re('#Duration:\s+(.*)#'));
                        },

                        "Meal" => function ($text = '', $node = null, $it = null) {
                            return nice(re('#Meal:\s+(.*)#'));
                        },

                        "Stops" => function ($text = '', $node = null, $it = null) {
                            $subj = re('#Stops:\s+(.*)#');

                            if ($subj) {
                                return preg_match('/Non[-\s]*stop/i', $subj) ? 0 : (int) $subj;
                            }
                        },
                    ],
                ],

                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    $this->date = strtotime($this->parser->getHeader("date"));
                    $text = $this->setDocument('plain');

                    // Frequent flyer ac - 560477648
                    if (preg_match_all('/Frequent flyer.*?-\s*(\d{5,})\s*$/im', $text, $ffNumberMatches)) {
                        $this->accountNumbers = array_unique($ffNumberMatches[1]);
                    }

                    $this->passengers = [reni('Passenger\(s\) : (.+?) \n')];
                    $q = white('AIR - | CAR - | HOTEL -');
                    $res = splitter("/($q)/i");

                    return $res;
                },

                "PostProcessing" => function ($text = '', $node = null, $it = null) {
                    return uniteAirSegments($it);
                },

                "#CAR -#" => [
                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "L";
                    },

                    "Number" => function ($text = '', $node = null, $it = null) {
                        return reni('Check In Confirmation: (\w+)');
                    },

                    "PickupLocation" => function ($text = '', $node = null, $it = null) {
                        return reni('Pick Up : .+? \n (.+?) , Phone :');
                    },

                    "PickupDatetime" => function ($text = '', $node = null, $it = null) {
                        $res = null;

                        foreach (['Pickup' => 'Pick', 'Dropoff' => 'Drop'] as $key => $value) {
                            $regex = "#{$value}.+?:\s+(?P<Time>\d{1,2}:\d{2}(?:\s*[AaPp][Mm])?),\s+(?<week>\w+),\s+(?P<Month>\w+)\s+(?P<Day>\d+)\s+(?P<Name>.*)#";

                            if (preg_match($regex, $text, $m)) {
                                $weeknum = WeekTranslate::number1(WeekTranslate::translate($m['week'], 'en'));
                                $dateStr = $m['Day'] . ' ' . $m['Month'] . ' ' . date('Y', $this->date);
                                $date = EmailDateHelper::parseDateUsingWeekDay($dateStr, $weeknum);
                                $res[$key . 'Datetime'] = strtotime($m['Time'], $date);
                            }
                        }

                        return $res;
                    },

                    "DropoffLocation" => function ($text = '', $node = null, $it = null) {
                        return reni('Drop Off : .+? \n (.+?) , Phone :');
                    },

                    "PickupPhone" => function ($text = '', $node = null, $it = null) {
                        $q = white('Phone : (.+?) \n');

                        return ure("/$q/", 1);
                    },

                    "DropoffPhone" => function ($text = '', $node = null, $it = null) {
                        $q = white('Phone : (.+?) \n');

                        return ure("/$q/", 2);
                    },

                    "CarType" => function ($text = '', $node = null, $it = null) {
                        return reni('Car Type : (.+?) \n');
                    },

                    "TotalCharge" => function ($text = '', $node = null, $it = null) {
                        $x = rew('Approx. Total (.+?) \n');

                        return total($x);
                    },

                    "Status" => function ($text = '', $node = null, $it = null) {
                        if (rew('Confirmed')) {
                            return 'confirmed';
                        }
                    },
                ],

                "#HOTEL -#" => [
                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "R";
                    },

                    "ConfirmationNumber" => function ($text = '', $node = null, $it = null) {
                        return reni('Check In Confirmation: (\w+)');
                    },

                    "HotelName" => function ($text = '', $node = null, $it = null) {
                        return reni('HOTEL - (.+?) \n');
                    },

                    "CheckInDate" => function ($text = '', $node = null, $it = null) {
                        $res = null;

                        foreach (['CheckIn' => 'Check[ \-]*In', 'CheckOut' => 'Check[ \-]*Out'] as $key => $value) {
                            $regex = "#{$value}:\s+(?<week>\w+),\s+(?P<Month>\w+)\s+(?P<Day>\d+)\s+(?P<Name>.*)#";

                            if (preg_match($regex, $text, $m)) {
                                $weeknum = WeekTranslate::number1(WeekTranslate::translate($m['week'], 'en'));
                                $dateStr = $m['Day'] . ' ' . $m['Month'] . ' ' . date('Y', $this->date);
                                $date = EmailDateHelper::parseDateUsingWeekDay($dateStr, $weeknum);
                                $res[$key . 'Date'] = $date;
                            }
                        }

                        return $res;
                    },

                    "Address" => function ($text = '', $node = null, $it = null) {
                        return reni('Address : (.+?) Phone');
                    },

                    "Phone" => function ($text = '', $node = null, $it = null) {
                        return reni('Phone : ([+()\s\d-]+)');
                    },

                    "Fax" => function ($text = '', $node = null, $it = null) {
                        return reni('Fax : ([+()\s\d-]+)');
                    },

                    "Rooms" => function ($text = '', $node = null, $it = null) {
                        return reni('No. of Rooms : (\d+)');
                    },

                    "Rate" => function ($text = '', $node = null, $it = null) {
                        return reni('Rate : (\w+ [\d.]+)');
                    },

                    "CancellationPolicy" => function ($text = '', $node = null, $it = null) {
                        return reni('Cancel Policy : (.+?) \n');
                    },

                    "RoomType" => function ($text = '', $node = null, $it = null) {
                        $q = white('Room Type : (?P<RoomType> \w+ \w+) (?P<RoomTypeDescription> .+?) \n');
                        $res = re2dict($q, $text);

                        return $res;
                    },

                    "Status" => function ($text = '', $node = null, $it = null) {
                        if (rew('Confirmed')) {
                            return 'confirmed';
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

    public function IsEmailAggregator()
    {
        return false;
    }
}
