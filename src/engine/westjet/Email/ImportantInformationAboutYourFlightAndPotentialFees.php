<?php

namespace AwardWallet\Engine\westjet\Email;

class ImportantInformationAboutYourFlightAndPotentialFees extends \TAccountCheckerExtended
{
    public $rePlain = [
        ['#WestJet\.\s+Tous\s+droits\s+réservés#i', 'blank', '9/10'],
    ];
    public $reHtml = "";
    public $rePDF = "";
    public $reSubject = [
        "Important information about your flight", "Avis important concernant votre vol", "Your trip starts here. Let's do this",
    ];
    public $reBody = "westjet";
    public $reBody2 = [
        "Important information about your upcoming flight - please read.", "A little planning. A little saving. Let's have fun.",
    ];
    public $reFrom = [
        ['#[@.]westjet#i', 'us', ''],
    ];
    public $reProvider = [
        ['#[@.]westjet#i', 'us', ''],
    ];
    public $fnLanguage = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $isAggregator = "0";
    public $caseReference = "";
    public $upDate = "29.04.2015, 15:39";
    public $crDate = "29.04.2015, 15:27";
    public $xPath = "";
    public $mailFiles = "westjet/it-2674091.eml";
    public $re_catcher = "#.*?#";
    public $pdfRequired = "0";

    public function detectEmailByHeaders(array $headers)
    {
        foreach ($this->reSubject as $re) {
            if (strpos($headers["subject"], $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        if (stripos($body, $this->reBody) === false) {
            return false;
        }

        foreach ($this->reBody2 as $re) {
            if (strpos($body, $re) !== false) {
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
                    return [$text];
                },

                "#.*?#" => [
                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "T";
                    },

                    "RecordLocator" => function ($text = '', $node = null, $it = null) {
                        return re('#Reservation\s+code:\s+(\w+)#');
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        return xpath('//tr[contains(., "Flights") and contains(., "Departs") and not(.//tr)]/following-sibling::tr');
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            if (preg_match('#(\w{2})\s+(\d+)#i', node('./td[1]'), $m)) {
                                return [
                                    'AirlineName'  => $m[1],
                                    'FlightNumber' => $m[2],
                                ];
                            }
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            $res = [];

                            foreach (['Dep' => 2, 'Arr' => 3] as $key => $value) {
                                $r = '#^(.*)\s+\((\w{3})\)\s+(\w+\s+\d+,\s+\d+)\s+(\d+:\d+\s+[ap]m)(\s+Terminal.*)?$#i';
                                $s = node("./td[$value]");

                                if (preg_match($r, $s, $m)) {
                                    $res[$key . 'Name'] = $m[1];
                                    $res[$key . 'Code'] = $m[2];
                                    $res[$key . 'Date'] = strtotime($m[3] . ', ' . $m[4]);

                                    if (isset($m[5])) {
                                        $res[$key . 'Name'] .= $m[5];
                                    }
                                }
                            }

                            return $res;
                        },

                        "Stops" => function ($text = '', $node = null, $it = null) {
                            return node('./td[last()]') == 'Non-stop' ? 0 : null;
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
