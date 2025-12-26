<?php

namespace AwardWallet\Engine\virgin\Email;

class BoardingPass extends \TAccountCheckerExtended
{
    public $rePlain = "#\n[>\s*]*From\s*:[^\n]*?virgin#i";
    public $rePlainRange = "";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $rePDF = "#Ticket\s+type\s+E-ticket.*?www\.virginatlantic\.com#is";
    public $rePDFRange = "/1";
    public $reSubject = "#Virgin Atlantic Airways Boarding Pass#i";
    public $langSupported = "en";
    public $typesCount = "1";
    public $reFrom = "#do_not_reply@fly\.virgin\.com#i";
    public $reProvider = "#@virgin\.com#i";
    public $caseReference = "";
    public $xPath = "";
    public $mailFiles = "virgin/it-1896928.eml, virgin/it-1899313.eml, virgin/it-1911779.eml, virgin/it-1918504.eml, virgin/it-1965500.eml";
    public $pdfRequired = "0";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    $pdfs = $this->parser->searchAttachmentByName('.*pdf');
                    $html = null;

                    foreach ($pdfs as $p) {
                        $name = trim(re('#name=(.*)#i', $this->parser->getAttachmentHeader($p, 'content-type')), '"');
                        $html .= $this->getDocument("#{$name}#i", 'simpletable');
                    }
                    $text = $this->setDocument('source', $html);

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
                        return [node('(//td[normalize-space(.) = "Name"]/ancestor::tr[1]/following-sibling::tr[1]/td[string-length(normalize-space(.)) > 1][1])[1]')];
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        return xpath('//td[normalize-space(.) = "From"]/ancestor::tr[1]');
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            if (isset($this->segmentIndex)) {
                                $this->segmentIndex++;
                            } else {
                                $this->segmentIndex = 0;
                            }

                            $infoNodes = null;
                            $nodes = xpath('//td[normalize-space(.) = "Name"]/ancestor::tr[1]/following-sibling::tr[1]');

                            foreach ($nodes as $n) {
                                $arr = array_values(array_filter(nodes('./td', $n)));

                                if (count($arr) == 3) {
                                    $infoNodes[] = $arr;
                                }
                            }

                            //$infoNodes = xpath('//td[normalize-space(.) = "Name"]/ancestor::tr[1]/following-sibling::tr[1][count(./td[string-length(normalize-space(.)) > 1]) = 3]');
                            if (isset($infoNodes[$this->segmentIndex])) {
                                $subj = $infoNodes[$this->segmentIndex];

                                if (count($subj) == 3) {
                                    return [
                                        'AirlineName'  => 'Virgin Atlantic Airways',
                                        'FlightNumber' => re('#^\d+$#i', $subj[1]),
                                        'Seats'        => re('#^\d+\w$#i', $subj[2]),
                                    ];
                                }
                            }
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            $res = null;

                            foreach (['Dep' => 'From', 'Arr' => 'To'] as $key => $value) {
                                $infoNodes = xpath('//td[normalize-space(.) = "' . $value . '"]/ancestor::tr[1]/following-sibling::tr[1]');

                                if (($n = $infoNodes->item($this->segmentIndex)) != null) {
                                    $subj = array_values(array_filter(nodes('./td', $n)));
                                    $airportInfo = null;

                                    if ($key == 'Dep') {
                                        if (count($subj) == 3) {
                                            $res[$key . 'Date'] = strtotime(str_replace('/', ' ', $subj[1]) . ', ' . $subj[2]);
                                            $airportInfo = $subj[0];
                                        }
                                    } else {
                                        if (count($subj) == 2) {
                                            $res[$key . 'Date'] = MISSING_DATE;
                                            $airportInfo = $subj[0];

                                            if (preg_match('#(\d+\w+|\w+\d+)\s+(.*)#i', $subj[1], $m)) {
                                                $res['Cabin'] = $m[2];
                                            } else {
                                                $res['Cabin'] = $subj[1];
                                            }
                                        }
                                    }
                                    $subj = array_values(array_filter(nodes('./following-sibling::tr[1]/td', $n)));

                                    if (count($subj) == 1) {
                                        $airportInfo .= ' ' . $subj[0];
                                    } elseif (count($subj) == 2 and preg_match('#\(\w{3}\)#i', $subj[0])) {
                                        $airportInfo .= ' ' . $subj[0];
                                        $res['Cabin'] .= ' ' . $subj[1];
                                    }

                                    if (preg_match('#(.*)\s*\((\w{3})\)#i', $airportInfo, $m)) {
                                        $res[$key . 'Name'] = nice($m[1]);
                                        $res[$key . 'Code'] = $m[2];
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

    public static function getEmailTypesCount()
    {
        return 1;
    }

    public static function getEmailLanguages()
    {
        return ["en"];
    }
}
