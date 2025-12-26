<?php

namespace AwardWallet\Engine\jetblue\Email;

use PlancakeEmailParser;

class FlightDelayed extends \TAccountCheckerExtended
{
    public $mailFiles = "jetblue/it-1764194.eml, jetblue/it-1764197.eml";

    private $subjects = [
        'Jetblue Airways Flight Status Notification - Flight Delayed',
        'JetBlue Airways Flight Status Notification: FLIGHT DELAYED: ARRIVAL',
    ];

    private $detects = [
        'This email is to inform you of a departure delay for JetBlue Flight',
        'This email is to inform you of an arrival delay for JetBlue Flight',
    ];

    private $from = '/[@\.]jetblue\.com/i';

    private $prov = 'jetblue';

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    return [$text];
                },

                "#.*?#" => [
                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "T";
                    },

                    "RecordLocator" => function ($text = '', $node = null, $it = null) {
                        return CONFNO_UNKNOWN;
                    },

                    "Status" => function ($text = '', $node = null, $it = null) {
                        $regex = '#This\s+email\s+is\s+to\s+inform\s+you\s+of\s+an?\s+(.*?)\s+for\s+JetBlue\s+Flight#i';

                        return re($regex);
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        return [$text];
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            return [
                                'AirlineName'  => re('#\s+for\s+(.*?)\s+Flight\s*\#(\d+)#i'),
                                'FlightNumber' => re(2),
                            ];
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            $res = null;

                            foreach (['Dep' => 'departing', 'Arr' => 'arriving\s+in'] as $key => $value) {
                                if (preg_match('#' . $value . '\s+(.*?)\s+\((\w+)\)#', $text, $m)) {
                                    $res[$key . 'Name'] = $m[1];
                                    $res[$key . 'Code'] = $m[2];
                                }
                            }

                            return $res;
                        },

                        "DepDate" => function ($text = '', $node = null, $it = null) {
                            $res = null;

                            if (preg_match('#Flight.*?on\s+(\d+)(\w+?)\s+at\s+(\d+:\d+(?:am|pm)?)#i', $text, $m)) {
                                $depDateStr = $m[1] . ' ' . $m[2] . ' ' . $this->getEmailYear();
                                $depTimeStr = $m[3];
                                $res['DepDate'] = strtotime($depDateStr . ', ' . $depTimeStr);

                                foreach (['Dep' => 'departure', 'Arr' => 'arrival'] as $key => $value) {
                                    $regex = '#The\s+new\s+estimated\s+time\s+of\s+' . $value . '\s+.*is\s+(\d+:\d+(?:am|pm))#i';

                                    if (preg_match($regex, $text, $m)) {
                                        $res[$key . 'Date'] = strtotime($depDateStr . ', ' . $m[1]);
                                    } elseif ($key == 'Arr') {
                                        $res[$key . 'Date'] = MISSING_DATE;
                                    }
                                }
                            }

                            return $res;
                        },
                    ],
                ],
            ],
        ];
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match($this->from, $from);
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['subject'], $headers['from'])) {
            if (!preg_match($this->from, $headers['from'])) {
                return false;
            }

            foreach ($this->subjects as $subject) {
                if (false !== stripos($headers['subject'], $subject)) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $body = !empty($parser->getHTMLBody()) ? $parser->getHTMLBody() : $parser->getPlainBody();

        if (false === stripos($body, $this->prov)) {
            return false;
        }

        foreach ($this->detects as $detect) {
            if (false !== stripos($body, $detect)) {
                return true;
            }
        }

        return false;
    }
}
