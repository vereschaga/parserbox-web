<?php

namespace AwardWallet\Engine\ichotelsgroup\Email;

// TODO: rewrite on objects

class YourReservation extends \TAccountCheckerExtended
{
    public $rePlain = "#Thank\s+you\s+for\s+choosing\s+the\s+InterContinental#is";
    public $rePlainRange = "/1";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $rePDF = "";
    public $rePDFRange = "";
    public $reSubject = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $reFrom = "#reservations[^@]+@ihg\.com#i";
    public $reProvider = "#ihg\.com#i";
    public $caseReference = "";
    public $isAggregator = "";
    public $xPath = "";
    public $mailFiles = "ichotelsgroup/it-1904549.eml, ichotelsgroup/it-1904581.eml, ichotelsgroup/it-2017632.eml, ichotelsgroup/it-2143265.eml";
    public $pdfRequired = "0";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    return [$text];
                },

                "#.*?#" => [
                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "R";
                    },

                    "ConfirmationNumber" => function ($text = '', $node = null, $it = null) {
                        $subj = re('#Confirmation\s+(?:No\.|Number)\s+(.*)#i');

                        if (stripos($subj, ',')) {
                            $res['ConfirmationNumbers'] = explode(', ', $subj);
                            $res['ConfirmationNumber'] = $res['ConfirmationNumbers'][0];
                        } else {
                            $res = $subj;
                        }

                        return $res;
                    },

                    "HotelName" => function ($text = '', $node = null, $it = null) {
                        $hotelName = re('#for\s+choosing\s+the\s+(.*?)\s+for\s+(?:the\s+stay|your\s+next\s+stay|your\s+upcoming\s+stay)#i');

                        if (!$hotelName) {
                            $hotelName = re('#require any further information about the\s+(.*?)\s+prior to your stay#i');
                        }

                        return $hotelName;
                    },

                    "Address" => function ($text = '', $node = null, $it = null) {
                        $result['Address'] = null;

                        $addressEnd = ['T:', 'Tel:', 'Tel :', 'Tel.:', 'Phone:', 'Phone :'];
                        $contactsHtml = $this->http->FindHTMLByXpath("descendant::text()[{$this->starts(['Your reservation details', 'YOUR RESERVATION DETAILS'])}]/following::text()[{$this->starts($addressEnd)}]/ancestor::*[ (self::table or self::tr or self::div or self::p) and preceding-sibling::*[normalize-space()] ][1]");
                        $contactsText = text($contactsHtml);

                        if (preg_match("/^\s*(.{3,}?)[ ]*\n+[ ]*([\s\S]{3,}?)\s*({$this->opt($addressEnd)}[\s\S]*)/", $contactsText, $m)) {
                            if (!empty($it['HotelName']) && stripos($m[1], $it['HotelName']) === 0) {
                                $result['Address'] = preg_replace('/\s+/', ' ', $m[2]);
                            }

                            if (preg_match("/\b{$this->opt(['Tel:', 'Tel :', 'Tel.:', 'Phone:', 'Phone :'])}\s*([+(\d][-. \d)(]{5,}[\d)])/", $m[3], $matches)) {
                                $result['Phone'] = $matches[1];
                            }

                            if (preg_match("/\b{$this->opt(['Fax:', 'Fax :', 'Fax.:'])}\s*([+(\d][-. \d)(]{5,}[\d)])/", $m[3], $matches)) {
                                $result['Fax'] = $matches[1];
                            }
                        }

                        return $result;
                    },

                    "CheckInDate" => function ($text = '', $node = null, $it = null) {
                        $checkInDate = cell('Arrival', +1);

                        if (!$checkInDate) {
                            $checkInDate = cell('We will welcome you', +1);
                        }

                        $checkInTime = re('#Check\-in time is ([^.]+)#ix');

                        if (!$checkInTime && preg_match("/^[[:alpha:]]{2,}, (\d{1,2} [[:alpha:]]{3,} \d{4})[ ]*-[ ]*Check-in:(?: After)? (\d{1,2}:\d{2}(?:[ ]*[AP]M)?)$/iu", $checkInDate, $m)) {
                            // Thursday, 23 May 2019 - Check-in: After 02:00 PM
                            $checkInDate = $m[1];
                            $checkInTime = $m[2];
                        }

                        return totime($checkInDate . ', ' . nice($checkInTime));
                    },

                    "CheckOutDate" => function ($text = '', $node = null, $it = null) {
                        $checkOutDate = cell('Departure', +1);

                        if (!$checkOutDate) {
                            $checkOutDate = cell('We will say goodbye to you', +1);
                        }

                        $checkInTime = re('#Check\-out time is ([^.]+)#ix');

                        if (!$checkInTime && preg_match("/^[[:alpha:]]{2,}, (\d{1,2} [[:alpha:]]{3,} \d{4})[ ]*-[ ]*Check-Out:(?: Before)? (\d{1,2}:\d{2}(?:[ ]*[AP]M)?)$/iu", $checkOutDate, $m)) {
                            // Friday, 24 May 2019 - Check-Out: Before 12:00 PM
                            $checkOutDate = $m[1];
                            $checkInTime = $m[2];
                        }

                        return totime($checkOutDate . ', ' . uberTime(nice($checkInTime)));
                    },

                    "GuestNames" => function ($text = '', $node = null, $it = null) {
                        return explode(', ', re('#Guest\s+Name\s+(.*)#i'));
                    },

                    "Guests" => function ($text = '', $node = null, $it = null) {
                        return orval(
                            re('#\b(\d{1,3})\s+Adults?#i'),
                            cell('Number of guest(s)', +1)
                        );
                    },

                    "Kids" => function ($text = '', $node = null, $it = null) {
                        return re('#\b(\d{1,3})\s+Child#i');
                    },

                    "Rate" => function ($text = '', $node = null, $it = null) {
                        return cell("Room rate", +1);
                    },

                    "CancellationPolicy" => function ($text = '', $node = null, $it = null) {
                        return orval(
                            node("//text()[{$this->contains(['Cancellation / Guarantee policy', 'CANCELLATION OR GUARANTEE'])}]/ancestor::*[ (self::tr or self::p) and following-sibling::*[normalize-space()] ][1]/following-sibling::*[normalize-space()][1]"),
                            node("//text()[contains(normalize-space(), 'Cancellation / Guarantee policy')]/ancestor::tr[1]", null, true, "#Cancellation\s*/\s*Guarantee\s+policy\s+(.+)#is"),
                            node("//text()[contains(normalize-space(), 'Cancellation and Guarantee policies')]/ancestor::tr[1]", null, true, "#Cancellation\s+and\s+Guarantee\s+policies\s+(.+)#is")
                        );
                    },

                    "RoomType" => function ($text = '', $node = null, $it = null) {
                        $roomType = cell('Type of room', +1);

                        if (!$roomType) {
                            $roomType = cell('Room type', +1);
                        }

                        $subj = nice(clear("#view\s+room#i", $roomType));

                        if (preg_match('#^(\d{1,3})[ ]+(.*\D.*)$#i', $subj, $m)) {
                            return [
                                'Rooms'    => $m[1],
                                'RoomType' => $m[2],
                            ];
                        } else {
                            return $subj;
                        }
                    },

                    "Status" => function ($text = '', $node = null, $it = null) {
                        if (re('#We\s+are\s+pleased\s+to\s+confirm\s+(?:your|the)\s+reservation\s+as\s+follows#')) {
                            return 'Confirmed';
                        }
                    },
                ],
            ],
        ];
    }

    public function detectEmailByHeaders(array $headers)
    {
        $subjects = [
            'Your reservation at the InterContinental Dusseldorf',
            'Your reservation at the InterContinental Hong Kong',
            'Your reservation confirmation at Holiday Inn Brussels Airport',
        ];

        foreach ($subjects as $phrases) {
            foreach ((array) $phrases as $phrase) {
                if (is_string($phrase) && stripos($headers['subject'], $phrase) !== false) {
                    return true;
                }
            }
        }

        return false;
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

    private function contains($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'contains(normalize-space(' . $node . '),"' . $s . '")';
        }, $field)) . ')';
    }

    private function starts($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'starts-with(normalize-space(' . $node . '),"' . $s . '")';
        }, $field)) . ')';
    }

    private function opt($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return '';
        }

        return '(?:' . implode('|', array_map(function ($s) {
            return preg_quote($s, '/');
        }, $field)) . ')';
    }
}
