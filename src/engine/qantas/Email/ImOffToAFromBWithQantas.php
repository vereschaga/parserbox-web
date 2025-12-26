<?php

namespace AwardWallet\Engine\qantas\Email;

use PlancakeEmailParser;

class ImOffToAFromBWithQantas extends \TAccountCheckerExtended
{
    public $mailFiles = "qantas/it-1884552.eml, qantas/it-1886630.eml";

    private $subjects = [
        '/I\'m\s+off\s+to\s+.*?\s+from\s+.*?\s+with\s+@Qantas/',
    ];

    private $detects = [
        'If you want to check the status of your flight please, follow this link',
        'If you want to check the status of the flight please, follow this link',
    ];

    private $from = '/[@\.]qantas\.com/i';

    private $prov = 'qantas';

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    return [$text];
                },

                "#.*?#" => [
                    "RecordLocator" => function ($text = '', $node = null, $it = null) {
                        $conf = re('#Booking\s+Reference\s*:\s*([\w\-]+)#i');

                        if (!empty($conf)) {
                            return $conf;
                        } elseif (empty(re('#Booking\s+Reference#i'))) {
                            return CONFNO_UNKNOWN;
                        }

                        return null;
                    },

                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "T";
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        $subj = re('#Passenger\s+details\s+-+\s+(.*?)\s*\n\s*\n#is');

                        return nice(explode("\n", $subj));
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        if (preg_match_all('#Flight\s+\w+\s+\d+\s+(?:from|departing).*\s+(?:to|arriving).*#i', $text, $m)) {
                            return $m[0];
                        }
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            if (preg_match('#Flight\s+(\w+)\s+(\d+)#i', $text, $m)) {
                                return [
                                    'AirlineName'  => $m[1],
                                    'FlightNumber' => $m[2],
                                ];
                            }
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            $res = null;

                            foreach (['Dep' => 'from', 'Arr' => 'to'] as $key => $value) {
                                $regex = '#' . $value . '\s+(.*)\s+\((\w{3})\)\s+on\s+(.*)\s+(\d+:\d+\s*(?:am|pm)?)#i';

                                if (preg_match($regex, $text, $m)) {
                                    $res[$key . 'Name'] = $m[1];
                                    $res[$key . 'Code'] = $m[2];
                                    $res[$key . 'Date'] = strtotime($m[3] . ', ' . $m[4]);
                                }
                            }

                            if (empty($res)) {
                                foreach (['Dep' => 'departing', 'Arr' => 'arriving'] as $key => $value) {
                                    $regex = '#' . $value . '\s+(.*)\s+\((\w{3})\)\s+on\s+(.*)\s+at\s+(\d+:\d+\s*(?:am|pm)?)#i';

                                    if (preg_match($regex, $text, $m)) {
                                        $res[$key . 'Name'] = $m[1];
                                        $res[$key . 'Code'] = $m[2];
                                        $res[$key . 'Date'] = strtotime($m[3] . ', ' . $m[4]);
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
                if (preg_match($subject, $headers['subject']) > 0) {
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
