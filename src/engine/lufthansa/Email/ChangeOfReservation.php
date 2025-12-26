<?php

namespace AwardWallet\Engine\lufthansa\Email;

class ChangeOfReservation extends \TAccountCheckerExtended
{
    public $mailFiles = "lufthansa/it-12.eml, lufthansa/it-1626884.eml, lufthansa/it-1631933.eml, lufthansa/it-1680760.eml, lufthansa/it-1730826.eml, lufthansa/it-1731014.eml, lufthansa/it-2584965.eml, lufthansa/it-6407911.eml";

    public $reFrom = "online@booking-lufthansa.com";
    public $reSubject = [
        "en"=> "Booking details, Departure",
    ];
    public $reBody = 'Lufthansa';
    public $reBody2 = [
        "de"=> "Flight information",
    ];

    public function detectEmailFromProvider($from)
    {
        return strpos($from, $this->reFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (!isset($headers['from'],$headers['subject'])) {
            return false;
        }

        if (strpos($headers["from"], $this->reFrom) === false) {
            return false;
        }

        foreach ($this->reSubject as $re) {
            if (strpos($headers["subject"], $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName('.*pdf');

        if (!isset($pdfs[0])) {
            return false;
        }
        $pdf = $pdfs[0];

        if (($text = \PDF::convertToText($parser->getAttachmentBody($pdf))) === null) {
            return false;
        }

        if (strpos($text, $this->reBody) === false) {
            return false;
        }

        foreach ($this->reBody2 as $re) {
            if (strpos($text, $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    $this->year = re('#\d{4}#i', $this->parser->getHeader('date'));
                    $text = $this->setDocument('application/pdf', 'complex');

                    return [$text];
                },

                "#.*?#" => [
                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "T";
                    },

                    "RecordLocator" => function ($text = '', $node = null, $it = null) {
                        return re('#Reservation\s+code:\s+([\w\-]+)#');
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        return [re('#Travel\s+dates\s+for:\s+(.*)#')];
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        return splitter('#(Flight\s+\w{2}\s+\d+)#', $text);
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            if (preg_match('#Flight\s+\w{2}\s+(\d+)\s+operated\s+by:\s+(.*)#', $text, $m)) {
                                return ['FlightNumber' => $m[1], 'AirlineName' => $m[2]];
                            }
                        },
                        "Operator" => function ($text = '', $node = null, $it = null) {
                            return re('#operated by:\s+(.+)#');
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
                        },

                        "DepName" => function ($text = '', $node = null, $it = null) {
                            $res = [];

                            foreach (['Dep' => 'from', 'Arr' => 'to'] as $key => $value) {
                                if (preg_match('#' . $value . '\s+(.*?)\n\s*\n#ms', $text, $m)) {
                                    $res[$key . 'Name'] = nice($m[1], ', ');
                                }
                            }

                            return $res;
                        },

                        "DepDate" => function ($text = '', $node = null, $it = null) {
                            if (!$this->year) {
                                return null;
                            }
                            $res = [];
                            $dateStr = '';

                            if (preg_match('#Date\s+(\d+)\.?\s+(\w+)(?:\s+-\s+(\d+)\.?\s+(\w+))?#', $text, $m)) {
                                $dateStr = $m[1] . ' ' . $m[2] . ' ' . $this->year;

                                if (isset($m[3])) {
                                    $arrDateStr = $m[3] . ' ' . $m[4] . ' ' . $this->year;
                                }
                            }

                            if (empty(trim($dateStr))) {
                                return;
                            }

                            foreach (['Dep' => 'Departure', 'Arr' => 'Arrival'] as $key => $value) {
                                if (preg_match('#' . $value . '\s+(\d+:\d+)#', $text, $m)) {
                                    if ($key == 'Arr' and isset($arrDateStr)) {
                                        $dateStr = $arrDateStr;
                                    }
                                    $res[$key . 'Date'] = strtotime($dateStr . ', ' . $m[1]);
                                }
                            }

                            return $res;
                        },

                        "ArrCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
                        },

                        "Cabin" => function ($text = '', $node = null, $it = null) {
                            if (preg_match('#Reservation\s+(.*?)\s+\((\w)\)#', $text, $m)) {
                                return ['Cabin' => $m[1], 'BookingClass' => $m[2]];
                            }
                        },

                        "Seats" => function ($text = '', $node = null, $it = null) {
                            return str_replace("/", ", ", re("#seat:\s+((?:\d+\w/?)+)#"));
                        },
                    ],
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
