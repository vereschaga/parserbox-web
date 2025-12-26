<?php

namespace AwardWallet\Engine\lufthansa\Email;

class TravelInformation2 extends \TAccountCheckerExtended
{
    public $mailFiles = "lufthansa/it-1738475.eml, lufthansa/it-1808110.eml, lufthansa/it-1812470.eml, lufthansa/it-1932134.eml, lufthansa/it-2229057.eml, lufthansa/it-4039193.eml, lufthansa/it-4047931.eml, lufthansa/it-4067598.eml, lufthansa/it-4791680.eml, lufthansa/it-5947426.eml";

    private $detect = 'Lufthansa';
    private $detectBody = [
        'Reise-Informationen für Ihren Flug',
        'Travel information for your flight',
        'Nos alegra que haya elegido',
        'Спасибо, что Вы выбрали',
        'wir freuen uns, dass Sie sich bei einem',
    ];

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    //goto parse by AirTravelPlane.php
                    if (xpath("//span[contains(@class, 'flight_nr')]/ancestor::table[1]/ancestor::td[1]/following-sibling::td[1]/descendant::table[1]/descendant::tr[1]/following-sibling::tr[1]")->length === 0) {
                        return null;
                    }

                    return [$text];
                },

                "#.*?#" => [
                    "RecordLocator" => function ($text = '', $node = null, $it = null) {
                        return re('#([\w-]+)#', re('#(?:BOOKING\s+CODE|Buchungscode|Código de reserva|Code de réservation|Код бронирования|CODICE DI PRENOTAZIONE|Código da reserva)\s*:\s+(\S+)#i', $text));
                    },

                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "T";
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        return [nice(re('#(?:Dear|Sehr\s+geehrter|Egregio Signor|Estimado Sr.|Caro)\s+([^,]+),#'))];
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        $xpath = "(//img[contains(@src, 'BOOKING_FLIGHT')]/ancestor::tr[3])[position() mod 2 = 0 and count(./td) = 1 and count(../tr) > 2 and not(contains(@style, 'display:none')) and descendant::tr[count(td)>=3]]";
                        $segments = $this->http->XPath->query($xpath);

                        if ($segments->length === 0) {
                            $xpath = "(//img[contains(@src, 'BOOKING_FLIGHT')]/ancestor::tr[3])[position() mod 2 = 0 and not(contains(@style, 'display:none'))]";
                            $segments = $this->http->XPath->query($xpath);
                        }

                        return $segments;
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            if (preg_match('#(\w+?)\s*(\d+)#', node('./td[1]'), $m)) {
                                $this->curFN = $m[2];

                                return ['AirlineName' => $m[1], 'FlightNumber' => $m[2]];
                            }
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            static $dayShift = false;
                            $res = [];

                            if (isset($this->currentSegmentDateStr)) {
                                $this->prevCurrentSegmentDateStr = $this->currentSegmentDateStr;
                            } else {
                                $this->prevCurrentSegmentDateStr = null;
                            }
                            $subj = node("./preceding-sibling::tr[contains(., '.')]");

                            if (!$subj) {
                                $subj = node("./ancestor::table[3]/preceding-sibling::table[contains(., '.')][1]");
                            }

                            if (!$subj) {
                                $subj = node("./ancestor::table[2]/preceding-sibling::table[contains(., '.')][1]");
                            }

                            if (preg_match('#\d+\.\d+\.\d+#', $subj, $m)) {
                                $this->currentSegmentDateStr = $m[0];
                            }

                            if (isset($this->prevCurrentSegmentDateStr) and $this->prevCurrentSegmentDateStr != $this->currentSegmentDateStr) {
                                $dayShift = false;
                            }
                            $regex = '#';
                            $regex .= '(?P<DepDate>\d+:\d+)\s+(?:\((?P<DepDateShift>\+\d)\)\s+)?';
                            $regex .= '(?P<DepName>.*?)\s+';

                            if (nodes('.//img[contains(@src, "ico_sitzplatz")]')) {
                                $regex .= '(?:(?P<Seats>\d+\w)?\s*)';
                                //								$regex .= '(?:(?P<Seats>\d+\w)?\s*|[\w\s\.]*)';
                            }
                            $regex .= '(?P<ArrDate>\d{2}:\d+)\s*(?:\((?P<ArrDateShift>\+\d)\)\s+)?';
                            $regex .= '(?P<ArrName>.*)\s*';
                            $regex .= '(?P<Class>Economy|Business|New\s+Business\s+Class|W|S|X|T|E|V|K|Q|L)';
                            $regex .= '(\s+Mileage\s+upgrade)?';
                            $regex .= '#i';
                            $subj = preg_replace('/[\x00-\x1F\x80-\xFF]/', '', node('.'));
                            $_subj = nodes(".//table[count(descendant::table)=1]//text()[normalize-space(.)]");

                            foreach ($_subj as $s) {
                                $ss = trim(preg_replace("#\s+#", ' ', $s));

                                if (!empty($ss) && !preg_match('#^\s*\d+\w\s*$#', $s)) {
                                    $subj = str_replace($s, '', $subj);
                                }
                            }

                            $subj = preg_replace('#Sitzplatz\s+reservieren#', '', $subj);
                            $subj = preg_replace('#Request\s+special\s+meal#i', '', $subj);

                            if (nodes(".//img[contains(@src, 'economy')]")) {
                                $subj .= ' ECONOMY';
                            } elseif (nodes(".//img[contains(@src, 'business')]") and !preg_match('#New\s+Business\s+Class#i', $subj)) {
                                $subj .= ' BUSINESS';
                            }

                            if (preg_match($regex, $subj, $m)) {
                                foreach (['Dep', 'Arr'] as $key) {
                                    $m[$key . 'Date'] = strtotime($this->currentSegmentDateStr . ', ' . re('#\d+:\d+#', $m[$key . 'Date']));

                                    if (isset($this->prevCurrentSegmentDateStr) && $this->prevCurrentSegmentDateStr == $this->currentSegmentDateStr && $dayShift) {
                                        $m[$key . 'Date'] = strtotime('+1 day', $m[$key . 'Date']);
                                    }

                                    if (isset($m[$key . 'DateShift']) && $m[$key . 'DateShift']) {
                                        $m[$key . 'Date'] = strtotime($m[$key . 'DateShift'] . ' day', $m[$key . 'Date']);
                                    }

                                    $m[$key . 'Code'] = TRIP_CODE_UNKNOWN;

                                    copyArrayValues($res, nice($m), [$key . 'Code', $key . 'Name', $key . 'Date']);
                                }

                                if (strlen($m['Class']) == 1) {
                                    $res['BookingClass'] = $m['Class'];
                                } else {
                                    $res['Cabin'] = $m['Class'];
                                }

                                if (isset($m['Seats']) and $m['Seats']) {
                                    $res['Seats'] = $m['Seats'];
                                }

                                if (isset($m['ArrDateShift']) && $m['ArrDateShift']) {
                                    $dayShift = true;
                                } else {
                                    $dayShift = false;
                                }
                            }

                            return $res;
                        },
                    ],
                ],
            ],
        ];
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        foreach ($this->detectBody as $dt) {
            if (is_string($dt) && stripos($body, $dt) && stripos($body, $this->detect) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers['from']) && stripos($headers['from'], $this->detect) !== false;
    }

    public function detectEmailFromProvider($from)
    {
        return isset($from) && stripos($from, $this->detect) !== false;
    }

    public static function getEmailTypesCount()
    {
        return 6;
    }

    public static function getEmailLanguages()
    {
        return ['en', 'de', 'fr', 'ru', 'it', 'pt'];
    }
}
