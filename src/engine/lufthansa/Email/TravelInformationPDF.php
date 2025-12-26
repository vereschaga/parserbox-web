<?php

namespace AwardWallet\Engine\lufthansa\Email;

class TravelInformationPDF extends \TAccountCheckerExtended
{
    public $rePlain = [
        ['#Lufthansa\s+Travel\s+Information\s+as\s+PDF\s+document#i', 'us', '1000'],
    ];
    public $reHtml = "";
    public $rePDF = "";
    public $reSubject = "";
    public $reFrom = [
        ['#lufthansa#i', 'us', ''],
    ];
    public $reProvider = [
        ['#lufthansa#i', 'us', ''],
    ];
    public $fnLanguage = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $isAggregator = "";
    public $caseReference = "";
    public $upDate = "13.04.2015, 13:23";
    public $crDate = "";
    public $xPath = "";
    public $mailFiles = "";
    public $re_catcher = "#.*?#";
    public $pdfRequired = "0";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    $this->year = re('#\d{4}#i', $this->parser->getHeader('date'));
                    $text = $this->getDocument('application/pdf', 'complex');
                    $this->fullText = text($text);
                    $text = $this->setDocument('application/pdf', 'simpletable');

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
                        $xpath = '//text()[contains(., "Travel dates for:")]/ancestor::td[1]/following-sibling::td[string-length(normalize-space(.)) > 1][1]';

                        return [node($xpath)];
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        $xpath = "//text()[contains(., 'Departure')]/ancestor::tr[1]/following-sibling::tr[1]";

                        return $this->http->XPath->query($xpath);
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            $this->segmentNodes = array_values(array_filter(nodes('./td')));

                            if (preg_match('#\w+\s+(\d+)\s+operated\s+by:\s+(.*)#', arrayVal($this->segmentNodes, 0), $m)) {
                                return ['FlightNumber' => $m[1], 'AirlineName' => $m[2]];
                            }
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
                        },

                        "DepName" => function ($text = '', $node = null, $it = null) {
                            return $this->segmentNodes[2];
                        },

                        "DepDate" => function ($text = '', $node = null, $it = null) {
                            if (!$this->year) {
                                return null;
                            }
                            $res = [];
                            $dateStrs = [];
                            $subj = $this->segmentNodes[1];

                            if (preg_match('#^(\d+\.?\s+\w+)(?:\s+-\s+(\d+\.\s+\w+))?$#', $subj, $m)) {
                                $dateStrs['Dep'] = $m[1];
                                $dateStrs['Arr'] = $m[2] ?? $dateStrs['Dep'];

                                foreach (['Dep' => 4, 'Arr' => 5] as $key => $value) {
                                    $timeStr = re('#\d+:\d+#', $this->segmentNodes[$value]);
                                    $dateStrs[$key] .= ' ' . $this->year;
                                    $res[$key . 'Date'] = strtotime($dateStrs[$key] . ', ' . $timeStr);
                                }
                            }

                            return $res;
                        },

                        "ArrCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
                        },

                        "ArrName" => function ($text = '', $node = null, $it = null) {
                            return $this->segmentNodes[3];
                        },

                        "Cabin" => function ($text = '', $node = null, $it = null) {
                            $subj = $this->segmentNodes[6];

                            if (preg_match('#(.*)\s+\((\w)\)#', $subj, $m)) {
                                return [
                                    'Cabin'        => $m[1],
                                    'BookingClass' => $m[2],
                                ];
                            } elseif (preg_match('#^(\w)\s+.*#', $subj, $m)) {
                                return ['BookingClass' => $m[1]];
                            }
                        },

                        "Seats" => function ($text = '', $node = null, $it = null) {
                            $xpath = './following-sibling::tr[position() <= 2]/td[contains(., "seat") and string-length(normalize-space(.)) > 1][1]';
                            $subj = implode(' ', nodes($xpath));

                            return re('#:\s+(.*)#', $subj);
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
