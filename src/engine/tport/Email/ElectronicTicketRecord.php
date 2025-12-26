<?php

namespace AwardWallet\Engine\tport\Email;

class ElectronicTicketRecord extends \TAccountCheckerExtended
{
    public $rePlain = "#This\s+Electronic\s+Ticket\s+Record\s+has\s+been\s+brought\s+to\s+you\s+by\s+Travelport\s+ViewTrip#i";
    public $rePlainRange = "/1";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $rePDF = "";
    public $rePDFRange = "";
    public $reSubject = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $reFrom = "#mytripandmore@travelport\.com#i";
    public $reProvider = "#mytripandmore@travelport\.com#i";
    public $caseReference = "";
    public $xPath = "";
    public $mailFiles = "tport/it-2035842.eml";
    public $pdfRequired = "0";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    $userEmail = strtolower(re("#\n\s*To\s*:[^\n]*?([^\s@\n]+@[\w\-.]+)#"));

                    if (!$userEmail) {
                        $userEmail = niceName(re("#\n\s*To\s*:\s*([^\n]+)#"));
                    }

                    if (!$userEmail) {
                        $userEmail = strtolower(re("#([a-zA-Z0-9_.+-]+@[a-zA-Z0-9-.]+)#", $this->parser->getHeader("To")));
                    }

                    if (!$userEmail) {
                        $userEmail = strtolower($this->parser->getHeader("To"));
                    }

                    if ($userEmail) {
                        $this->parsedValue('userEmail', $userEmail);
                    }

                    $this->passengers = [re('#Traveler\s+(.*)#i')];
                    $this->fullText = $text;

                    return xpath('//tr[contains(., "Depart") and ./following-sibling::tr[2][contains(., "Arrive")]]/ancestor::table[1]');
                },

                "#.*?#" => [
                    "RecordLocator" => function ($text = '', $node = null, $it = null) {
                        return re('#Reservation\s+Number\s*:\s+([\w\-]+)#i');
                    },

                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "T";
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        return $this->passengers;
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        return xpath('.');
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            if (preg_match('#\s*\d+,\s+\d{4}\s+(.*)\s+-\s+Flight\s+(\d+)\s+(Confirmed)#i', CleanXMLValue($node->nodeValue), $m)) {
                                return [
                                    'AirlineName'  => $m[1],
                                    'FlightNumber' => $m[2],
                                    'Status'       => $m[3],
                                ];
                            }
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            $res = null;
                            $dateStr = re('#\w+\s+\d+,\s+\d{4}#i');

                            if (!$dateStr) {
                                return;
                            }

                            foreach (['Dep' => 'Depart', 'Arr' => 'Arrive'] as $key => $value) {
                                if (preg_match('#(.*)\s*\((\w{3})\)#i', cell($value, +1), $m)) {
                                    $res[$key . 'Name'] = $m[1];
                                    $res[$key . 'Code'] = $m[2];
                                }
                                $res[$key . 'Date'] = strtotime($dateStr . ', ' . cell($value, +2));
                            }

                            return $res;
                        },
                    ],
                ],

                "PostProcessing" => function ($text = '', $node = null, $it = null) {
                    $itNew = uniteAirSegments($it);

                    if (count($itNew) == 1) {
                        $totalStr = re('#Total\s*:\s+(.*?)\s+Endorsement#s', $this->fullText);
                        $itNew[0]['TotalCharge'] = cost($totalStr);
                        $itNew[0]['Currency'] = currency($totalStr);
                        $itNew[0]['BaseFare'] = cost(re('#Fare\s*:\s+(.*)#i', $this->fullText));
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
}
