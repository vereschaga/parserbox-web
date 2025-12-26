<?php

namespace AwardWallet\Engine\hotels\Email;

class YourHotelBookingConfirmation extends \TAccountCheckerExtended
{
    public $rePlain = "#Hotels\.com\s+Confirmation\s+Number#i";
    public $rePlainRange = "/1";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $rePDF = "";
    public $rePDFRange = "";
    public $reSubject = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $reFrom = "#reply@hotels\.com#i";
    public $reProvider = "#\bHotels\.com#i";
    public $caseReference = "";
    public $isAggregator = "1";
    public $xPath = "";
    public $mailFiles = "hotels/it-2125050.eml, hotels/it-2125388.eml, hotels/it-2125399.eml, hotels/it-2244205.eml, hotels/it-2252402.eml, hotels/it-2806392.eml";
    public $pdfRequired = "0";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    $sent = totime($this->parser->getHeader('date'));
                    $this->sent = $sent;

                    return [$text];
                },

                "#.*?#" => [
                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "R";
                    },

                    "ConfirmationNumber" => function ($text = '', $node = null, $it = null) {
                        return re('#(?:Confirmation\s+Number|Itinerary\s+No)\s+(\d+)#i');
                    },

                    "HotelName" => function ($text = '', $node = null, $it = null) {
                        $r = '#\s*(.*)\s+((?s).*)#i';
                        $subj = implode("\n", nodes('//a[contains(@href, "http://www.hotels.com/hotel/details.html") and .//img]/ancestor::table[3][not(contains(., "Arrive:"))]//text()'));

                        if (preg_match($r, $subj, $m)) {
                            return [
                                'HotelName' => $m[1],
                                'Address'   => nice($m[2], ','),
                            ];
                        }
                    },

                    "CheckInDate" => function ($text = '', $node = null, $it = null) {
                        $date = re_white('Arrive: (.+?) \n');
                        $date = preg_replace("#\s+#", "", $date);
                        $date1 = totime($date);
                        $date2 = timestamp_from_format($date, 'd / m / Y|');

                        // easy, just one correct
                        if (!$date1 || !$date2) {
                            return orval($date1, $date2);
                        }

                        if ($date1 < 0 || $date2 < 0) {
                            if ($date1 > 0) {
                                return $date1;
                            } elseif ($date2 > 0) {
                                return $date2;
                            }
                        }

                        // choose the closest to sent date
                        // though we could kinda-sorta detect date format, looking at arr and dep dates together
                        if ($date1 - $this->sent < $date2 - $this->sent) {
                            return $date1;
                        } else {
                            return $date2;
                        }
                    },

                    "CheckOutDate" => function ($text = '', $node = null, $it = null) {
                        $date = re_white('Depart: (.+?) \n');
                        $date = preg_replace("#\s+#", "", $date);
                        $date1 = totime($date);
                        $date2 = timestamp_from_format($date, 'd/m/Y|');

                        // easy, just one correct
                        if (!$date1 || !$date2) {
                            return orval($date1, $date2);
                        }

                        if ($date1 < 0 || $date2 < 0) {
                            if ($date1 > 0) {
                                return $date1;
                            } elseif ($date2 > 0) {
                                return $date2;
                            }
                        }

                        // choose the closest to sent date
                        if ($date1 - $this->sent < $date2 - $this->sent) {
                            return $date1;
                        } else {
                            return $date2;
                        }
                    },

                    "GuestNames" => function ($text = '', $node = null, $it = null) {
                        return [re('#Dear\s+(.*?),#')];
                    },

                    "Rooms" => function ($text = '', $node = null, $it = null) {
                        if (preg_match_all('#Room\s+\d+\s*:#i', $text, $m)) {
                            $res['Rooms'] = count($m[0]);
                            $res['GuestNames'] = null;
                            $res['Guests'] = null;
                            $res['RoomType'] = null;

                            for ($i = 1; $i <= $res['Rooms']; $i++) {
                                $s = node('//td[contains(., "Room") and contains(., "' . $i . '") and contains(., ":") and not(.//td)]/following-sibling::td[1][normalize-space(.)]');

                                if (preg_match('#(.*)\s+(\d+)\s+adults?\s+Non\s+smoking\**\s+(.*)#i', $s, $m)) {
                                    $res['GuestNames'][] = $m[1];
                                    $res['Guests'] += $m[2];
                                    $res['RoomType'][] = $m[3];
                                }
                            }
                            //	if ($res['RoomType']) $res['RoomType'] = implode('|', $res['RoomType']);
                            return $res;
                        }
                    },

                    "CancellationPolicy" => function ($text = '', $node = null, $it = null) {
                        return node('//tr[normalize-space(.) = "Cancellation Policy"]/following-sibling::tr[1]');
                    },

                    "Total" => function ($text = '', $node = null, $it = null) {
                        return total(re('#Total\s+Price\s*:\s+(.*)#i'), 'Total');
                    },
                ],
            ],
        ];
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $result = parent::ParsePlanEmail($parser);

        return $result;
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
        return true;
    }
}
