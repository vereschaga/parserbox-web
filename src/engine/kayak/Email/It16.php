<?php

namespace AwardWallet\Engine\kayak\Email;

class It16 extends \TAccountCheckerExtended
{
    public $rePlain = "#\d{4}\s+KAYAK\.com#i";
    public $rePlainRange = "-100";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $rePDF = "";
    public $rePDFRange = "";
    public $reSubject = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $reFrom = "#@kayak.com#i";
    public $reProvider = "#[@.]kayak.com#i";
    public $caseReference = "7293";
    public $xPath = "";
    public $mailFiles = "kayak/it-16.eml, kayak/it-18.eml, kayak/it-1996515.eml, kayak/it-3.eml";
    public $pdfRequired = "";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    return [$text];
                },

                "#.*?#" => [
                    "RecordLocator" => function ($text = '', $node = null, $it = null) {
                        return orval(
                            re("#\s*Record Locator\s*:\s*([\dA-Z\-]{3,})#"),
                            re("#\s*confirmation number\s*:\s*([\dA-Z\-]{3,})#"),
                            re("#\s*Trip ID\s*:\s*([\dA-Z\-]{3,})#")
                        );
                    },

                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "T";
                    },

                    "TotalCharge" => function ($text = '', $node = null, $it = null) {
                        return cost(re("#\n\s*([^\d\w\s][\d.]+)\s+\b[A-Z]{3}\b#"));
                    },

                    "Currency" => function ($text = '', $node = null, $it = null) {
                        return currency(re("#\n\s*([^\d\w\s][\d.]+)\s+\b[A-Z]{3}\b#"));
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        //return splitter("#(\n\s*(?:Departure|Return Flight|Connection))#");
                        return xpath("//*[contains(text(), 'Landing:')]/ancestor-or-self::tr[1]/preceding-sibling::tr[1]");
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            return re("#\s+Flight\s+(\d+)#");
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            return re("#\b([A-Z]{3})\b\s*:\s*#");
                        },

                        "DepDate" => function ($text = '', $node = null, $it = null) {
                            $date = re("#(?:^|\n)\s*(?:Flight\s+\d+\s*\-|Return\s+Flight|Departure|This\s+flight\s+leaves\s+on)\s+\w+,?\s+(\w+\s+\d+(?:\s+\d{4})?)#", text(xpath('preceding-sibling::tr[string-length(normalize-space(.))>1][position()<3]')));

                            if ($date and !preg_match('#\d{4}#i', $date)) {
                                $date .= ', ' . re('#\d{4}#', $this->parser->getHeader('date'));
                            }

                            if (!$date) {
                                $date = $this->cache('date');
                            } else {
                                $this->cache('dep', null);
                                $this->cache('arr', null);
                            }

                            $prevDep = $this->cache('dep');
                            $prevArr = $this->cache('arr');

                            $dep = totime($date . ', ' . re("#Take-off:\s*(\d+:\d+\w)#") . 'm');
                            $arr = totime($date . ', ' . re("#Landing:\s*(\d+:\d+\w)#", text(xpath('following-sibling::tr[1]'))) . 'm');

                            if ($prevArr && $dep < $prevArr) {
                                $dep += 24 * 3600;
                                $arr += 24 * 3600;
                                $date = date('Y-m-d', totime($date) + 24 * 3600);
                            }

                            if ($dep > $arr) {
                                $arr += 24 * 3600;
                            }

                            $this->cache('dep', $dep);
                            $this->cache('arr', $arr);
                            $this->cache('date', $date);

                            return [
                                'DepDate' => $dep, //date(DATE_RFC822, $dep),
                                'ArrDate' => $arr, //date(DATE_RFC822, $arr)
                            ];
                        },

                        "ArrCode" => function ($text = '', $node = null, $it = null) {
                            return re("#Landing.*?\b([A-Z]{3})\b\s*:\s*#ms", node('following-sibling::tr[1]'));
                        },

                        "AirlineName" => function ($text = '', $node = null, $it = null) {
                            return re("#(?:^|\n)\s*([^\n]*?)\s+Flight\s+(\d+)#");
                        },

                        "Aircraft" => function ($text = '', $node = null, $it = null) {
                            $text = text(xpath("following-sibling::tr[2]"));

                            return re("#(?:\|\s*Fare\s+code\s*:\s*[A-Z\d\-]+\s*)?\|\s*([^\n]+)#", $text);
                        },

                        "Cabin" => function ($text = '', $node = null, $it = null) {
                            $text = text(xpath("following-sibling::tr[2]"));

                            return re("#^\s*(.*?)(?:\|\s*Fare\s+code\s*:\s*[A-Z\d\-]+\s*)#", $text);
                        },

                        "Duration" => function ($text = '', $node = null, $it = null) {
                            return re("#\d+h\s+\d+m#");
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
}
