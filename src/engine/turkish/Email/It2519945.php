<?php

namespace AwardWallet\Engine\turkish\Email;

class It2519945 extends \TAccountCheckerExtended
{
    public $rePlain = [
        ['#\n[>\s*]*From\s*:[^\n]*?[@.](?:thy|turkish)#i', 'blank', ''],
    ];
    public $reHtml = "";
    public $rePDF = "";
    public $reSubject = [
        ['#Turkish\s+Airlines#i', 'blank', ''],
    ];
    public $reFrom = [
        ['#[@.]thy\.#i', 'blank', ''],
    ];
    public $reProvider = [
        ['#[@.]thy\.#i', 'blank', ''],
    ];
    public $fnLanguage = "";
    public $langSupported = "tr";
    public $typesCount = "1";
    public $isAggregator = "0";
    public $caseReference = "";
    public $upDate = "03.03.2015, 23:23";
    public $crDate = "02.03.2015, 14:48";
    public $xPath = "";
    public $mailFiles = "turkish/it-2519945.eml";
    public $re_catcher = "#.*?#";
    public $pdfRequired = "0";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    $html = $this->parser->searchAttachmentByName("TR_mail.html");

                    if (isset($html) && count($html) > 0) {
                        $html = $html[0];
                        $text = text($this->parser->getAttachmentBody($html));
                    } else {
                        //?? goes to alternativeBodies
                        $this->logger->debug('other format');

                        return [];
                    }

                    $this->date = strtotime($this->parser->getHeader("date"));
                    //					$text = $this->setDocument("text/html", "text");
                    return [$text];
                },

                "#.*?#" => [
                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "T";
                    },

                    "RecordLocator" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Rezervasyon\s+Kodu\s+([A-Z\d-]+)#");
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        return niceName(re("#\s+Bilet\s+Numarası\s+([^\n]*?)\s{2,}#u"));
                    },

                    "Status" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Elektronik\s+(biletiniz\s+[^\n.]+)#ux");
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        return splitter("#\n\s*([A-Z]{3}\s+[A-Z]{3}\s+\d+\s*\w+\s+[A-Z\d]{2}\d+\s+\d+:\d+)#");
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            return [
                                'DepCode'      => re("#^([A-Z]{3})\s+([A-Z]{3})\s+(\d+\s*\w+)\s+([A-Z\d]{2})(\d+)\s+(\d+:\d+)\s+(\d+:\d+)\s+([A-Z])\s+(\w+)#"),
                                'ArrCode'      => re(2),
                                'AirlineName'  => re(4),
                                'FlightNumber' => re(5),
                                'DepDate'      => strtotime(re(3) . ',' . re(6), $this->date),
                                'ArrDate'      => strtotime(re(3) . ',' . re(7), $this->date),
                                'BookingClass' => re(8),
                                'Cabin'        => re(9),
                            ];
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
        return ["tr"];
    }

    public function IsEmailAggregator()
    {
        return false;
    }
}
