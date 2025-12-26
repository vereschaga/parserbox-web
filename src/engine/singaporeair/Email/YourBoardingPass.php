<?php

namespace AwardWallet\Engine\singaporeair\Email;

class YourBoardingPass extends \TAccountCheckerExtended
{
    public $reHtml = "#We\s+are\s+pleased\s+to\s+enclose\s+your\s+boarding\s+pass.*Yours\s+sincerely,\s*(?:<.*>)?\s*Singapore\s+Airlines#is";

    public $reSubject = "#SQ Mobile – Your Boarding Pass#i";

    public $reFrom = "#SQ_Mobile@singaporeair\.com\.sg#i";

    public $reProvider = "#singaporeair\.com\.sg#i";

    public $mailFiles = "singaporeair/it-1966408.eml";

    private $pdfText = '';

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    $pdfs = $this->parser->searchAttachmentByName('.*pdf');

                    if (isset($pdfs[0])) {
                        $this->pdfText = \PDF::convertToText($this->parser->getAttachmentBody($pdfs[0]));
                    }

                    return [$text];
                },

                "#.*?#" => [
                    "RecordLocator" => function ($text = '', $node = null, $it = null) {
                        return CONFNO_UNKNOWN;
                    },

                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "T";
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        return [re('/Dear\s+(.+)/')];
                    },

                    "TicketNumbers" => function ($text = '', $node = null, $it = null) {
                        return [re('/Ticket no\.\s+(.+)/', $this->pdfText)];
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        preg_match_all('/(Flight\s*:\s*[\D\d\s]+?\s+Seat\s*:\s*.+)/i', $text, $m);

                        if (!empty($m[1])) {
                            return $m[1];
                        }

                        return [$text];
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            if (preg_match('#Flight\s*:\s*(\w{2})\s+(\d+)#i', $text, $m)) {
                                return [
                                    'AirlineName'  => $m[1],
                                    'FlightNumber' => $m[2],
                                ];
                            }
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
                        },

                        "DepName" => function ($text = '', $node = null, $it = null) {
                            $regex = '#Departs\s*:\s*(.*)\s*(?:\(\s*.*\s*\))?,\s*\w+\s+(\d+\s+\w+)\s+(\d+:\d+\s*?(?:am|pm)?)#i';

                            if (preg_match($regex, $text, $m)) {
                                $dateStr = $m[2] . ' ' . re('#\s+\d{4}\s+#i', $this->parser->getHeader('Date')) . ', ' . $m[3];

                                if (preg_match('/(.+)\s*\(\s*([A-Z\d]{1,3})\s*\)/', $m[1], $math)) {
                                    return [
                                        'DepName'           => nice($math[1]),
                                        'DepDate'           => strtotime($dateStr),
                                        'DepartureTerminal' => $math[2],
                                    ];
                                }

                                return [
                                    'DepName' => nice($m[1]),
                                    'DepDate' => strtotime($dateStr),
                                ];
                            }
                        },

                        "ArrCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
                        },

                        "ArrName" => function ($text = '', $node = null, $it = null) {
                            if (preg_match('#Arrives\s*:\s*(.*)\s*(?:\(\s*.*\s*\))?#', $text, $m)) {
                                if (preg_match('/(.+)\s*\(\s*([A-Z\d]{1,3})\s*\)/', $m[1], $math)) {
                                    return [
                                        'ArrName'         => $math[1],
                                        'ArrivalTerminal' => $math[2],
                                    ];
                                }

                                return $m[1];
                            }
                        },

                        "ArrDate" => function ($text = '', $node = null, $it = null) {
                            return MISSING_DATE;
                        },

                        "Cabin" => function ($text = '', $node = null, $it = null) {
                            return re('#Cabin\s+Class\s*:\s*(.*)#i');
                        },

                        "Seats" => function ($text = '', $node = null, $it = null) {
                            return re('#Seat\s*:\s*(.*)#i');
                        },
                    ],
                ],
            ],
        ];
    }
}
