<?php

namespace AwardWallet\Engine\hawaiian\Email;

class It1991195 extends \TAccountCheckerExtended
{
    public $rePlain = "#\n[>\s*]*From\s*:[^\n]*?hawaiianairlines#i";
    public $rePlainRange = "";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $rePDF = "";
    public $rePDFRange = "";
    public $reSubject = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $reFrom = "#hawaiianairlines#i";
    public $reProvider = "#hawaiianairlines#i";
    public $caseReference = "6908";
    public $xPath = "";
    public $mailFiles = "hawaiian/it-12710454.eml, hawaiian/it-1991195.eml, hawaiian/it-1991196.eml";
    public $pdfRequired = "0";

    public $detectSubject = [
        'Mobile Boarding Pass(es) for',
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (empty($headers["from"]) || empty($headers["subject"])) {
            return false;
        }

        if (strpos($headers["from"], '.hawaiianairlines.com') === false) {
            return false;
        }

        foreach ($this->detectSubject as $detectSubject) {
            if (stripos($headers["subject"], $detectSubject) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//a[contains(@href, '.hawaiianair.com') or contains(@href, '.hawaiianairlines.com')]")->length === 0) {
            return false;
        }

        if ($this->http->XPath->query("//*[".$this->contains(['Passenger Information and Cost Breakdown'])."]")->length > 0) {
            return true;
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, 'hawaiianairlines.com') !== false;
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $result = parent::ParsePlanEmail($parser);
        return $result;
    }

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    return [$text];
                },

                "#.*?#" => [
                    "RecordLocator" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Confirmation\s*:\s*([A-Z\d\-]+)#");
                    },

                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "T";
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        $text = clear("#GET BOARDING PASS#", re("#\n\s*Passenger\(s\)\s*(.*?)\s+Confirmation:#ims"));
                        $names = [];
                        re("#(?:^|\n\s*)\d+\.([^\n]+)#", function ($m) use (&$names) {
                            $names[] = trim($m[1]);
                        }, $text);

                        return $names;
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        return [$text];
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            return uberAir(re("#\n\s*Flight\s*:\s*([^\n]+)#"));
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
                        },

                        "DepName" => function ($text = '', $node = null, $it = null) {
                            return re("#\n\s*From\s*:\s*([^\n]+)#");
                        },

                        "DepDate" => function ($text = '', $node = null, $it = null) {
                            $date = re("#\n\s*Departing\s*:\s*(.*?)\n\s*Arrival#ims");
                            $date = preg_replace("#^\s*(\d{1,2}):(\d[AP]M)#", '$1:0$2', $date);

                            return totime(uberDateTime(nice($date)));
                        },

                        "ArrCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
                        },

                        "ArrName" => function ($text = '', $node = null, $it = null) {
                            return re("#\n\s*To\s*:\s*([^\n]+)#");
                        },

                        "ArrDate" => function ($text = '', $node = null, $it = null) {
                            return totime(uberDateTime(preg_replace("#(.*:)(\d[AP]M.*)#", '${1}0$2', nice(re("#\n\s*Arrival\s*:\s*(.*?)\n\s*Helpful#ims")))));
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

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(" or ", array_map(function ($s) {
                return "starts-with(normalize-space(.), \"{$s}\")";
            }, $field)).')';
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(" or ", array_map(function ($s) {
                return "contains(normalize-space(.), \"{$s}\")";
            }, $field)).')';
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) {
                return 'normalize-space(.)="' . $s . '"';
            }, $field)).')';
    }

}
