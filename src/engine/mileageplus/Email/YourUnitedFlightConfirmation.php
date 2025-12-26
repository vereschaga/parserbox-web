<?php

namespace AwardWallet\Engine\mileageplus\Email;

class YourUnitedFlightConfirmation extends \TAccountCheckerExtended
{
    public $rePlain = "#United\s+Confirmation\s+\##i";
    public $rePlainRange = "/1";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $rePDF = "";
    public $rePDFRange = "";
    public $reSubject = "#Your\s+United\s+flight\s+confirmatio#i";
    public $langSupported = "en";
    public $typesCount = "1";
    public $reFrom = "#UNITED-CONFIRMATION@UNITED\.COM#i";
    public $reProvider = "#UNITED\.COM#i";
    public $caseReference = "";
    public $isAggregator = "0";
    public $upDate = "30.12.2014, 12:56";
    public $crDate = "";
    public $xPath = "";
    public $mailFiles = "mileageplus/it-2139866.eml, mileageplus/it-2179815.eml, mileageplus/it-2181216.eml, mileageplus/it-2192112.eml, mileageplus/it-2195804.eml, mileageplus/it-2195806.eml, mileageplus/it-2195809.eml, mileageplus/it-2244176.eml, mileageplus/it-2248554.eml, mileageplus/it-2306653.eml, mileageplus/it-2308215.eml";
    public $pdfRequired = "0";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    $r = '#\s*(?:E-Ticket\s+Receipt\s+and\s+Itinerary\s+Confirmation\s*\#:?|(.*)\s+Confirmation\s*\#|Confirmation\s+number:?)\s*([\w\-]+)#i';
                    $this->recordLocators = [];

                    if (preg_match_all($r, $text, $matches, PREG_SET_ORDER)) {
                        foreach ($matches as $m) {
                            $this->recordLocators[$m[1] ? $m[1] : '[GENERAL]'] = $m[2];
                        }
                    }

                    return xpath('//tr[contains(., "Equipment:")]/preceding-sibling::tr[1]');
                },

                "#.*?#" => [
                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "T";
                    },

                    "RecordLocator" => function ($text = '', $node = null, $it = null) {
                        $airlineNames[] = re('#Operated\s+by:\s+(.*)#i');
                        $airlineNames[] = re('#(.*)\s+\d+#i');
                        $rl = null;

                        foreach ($airlineNames as $an) {
                            if (!$an) {
                                continue;
                            }

                            if (isset($this->recordLocators[$an])) {
                                $rl = $this->recordLocators[$an];
                            } elseif ($k = preg_replace('#\s*Airlines\s*#i', '', $an)
                                        and isset($this->recordLocators[$k])) {
                                $rl = $this->recordLocators[$k];
                            } elseif ($k = preg_replace('#\s*Express/\w+\s+Airlines\s*#i', '', $an)
                                        and isset($this->recordLocators[$k])) {
                                $rl = $this->recordLocators[$k];
                            } elseif ($k = '[GENERAL]'
                                        and isset($this->recordLocators[$k])) {
                                $rl = $this->recordLocators[$k];
                            }

                            if ($rl) {
                                return $rl;
                            }
                        }
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        return nodes('//tr[contains(., "Name") and contains(., "Frequent flyer")]/following-sibling::tr[count(./td) > 2]/td[1] | //tr[contains(., "Passenger information") and contains(., "Fare details")]/following-sibling::tr//b');
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        return xpath('.');
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            if (preg_match('#^(.*?)\s+(\d+)\s*(?:Operated\s+by:\s+(.*))?#i', node('./td[1]'), $m)) {
                                return [
                                    'AirlineName'  => (isset($m[3]) and $m[3]) ? $m[3] : $m[1],
                                    'FlightNumber' => $m[2],
                                ];
                            }
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            $res = null;

                            foreach (['Dep' => 2, 'Arr' => 3] as $key => $value) {
                                $r = '#(\w{3})\s+(\d+:\d+\s*(?:am|pm)?)\s*\w+,\s+(\w+\s+\d+,\s+\d+)#i';

                                if (preg_match($r, node('./td[' . $value . ']'), $m)) {
                                    $res[$key . 'Code'] = $m[1];
                                    $res[$key . 'Date'] = strtotime($m[3] . ', ' . $m[2]);
                                }
                            }

                            return $res;
                        },

                        "Aircraft" => function ($text = '', $node = null, $it = null) {
                            $r = '#';
                            $r .= 'Equipment\s*:\s*(.*)\s+\|\s+';
                            $r .= 'Duration\s*:\s*((?:\d+h\s+)?\d+m)\s+\|\s+';
                            $r .= 'Non-stop\s+\|.*';
                            $r .= 'Traveled\s+miles\s*:\s*([\d,]+)\s+\|\s+';
                            $r .= '(?:Award\s+miles\s*:\s*([\d,]*)\s+\|\s+)?';
                            $r .= '(.*)';
                            $r .= '#i';
                            $subj = node('./following-sibling::tr[1][contains(., "Equipment:")]');
                            $res = null;

                            if (preg_match($r, $subj, $m)) {
                                return [
                                    'Aircraft'      => str_replace('_', ' ', $m[1]),
                                    'Duration'      => $m[2],
                                    'TraveledMiles' => $m[3],
                                    'Meal'          => strtolower($m[5]) != 'no meal service' ? $m[5] : null,
                                ];
                            }
                        },

                        "Cabin" => function ($text = '', $node = null, $it = null) {
                            if (preg_match('#(.*)\s+\((\w{1,2})\)#i', node('./td[4]'), $m)) {
                                return [
                                    'Cabin'        => $m[1],
                                    'BookingClass' => $m[2],
                                ];
                            }
                        },

                        "Seats" => function ($text = '', $node = null, $it = null) {
                            return re('#^(?:\d+\w,?\s*)+$#', node('./td[5]'));
                        },
                    ],
                ],

                "PostProcessing" => function ($text = '', $node = null, $it = null) {
                    $itNew = uniteAirSegments($it);

                    if (count($itNew) == 1) {
                        $r = '#total\s*:\s+([\d,]+\s+miles)(?:\s+\+\s+([\d.,]+\s+\w{3}))#i';

                        if (preg_match($r, $this->text(), $m)) {
                            $itNew[0]['SpentAwards'] = str_replace(', ', ',', nice($m[1]));
                            $itNew[0]['TotalCharge'] = isset($m[2]) ? cost($m[2]) : null;
                            $itNew[0]['Currency'] = isset($m[2]) ? currency($m[2]) : null;
                        } elseif ($t = re('#total\s*:\s+([,\d\.]+\s+\w{3})#i', $this->text())) {
                            $itNew[0]['TotalCharge'] = cost($t);
                            $itNew[0]['Currency'] = currency($t);
                        }
                    }

                    return $itNew;
                },
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
