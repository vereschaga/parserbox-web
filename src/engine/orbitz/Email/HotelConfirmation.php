<?php

namespace AwardWallet\Engine\orbitz\Email;

class HotelConfirmation extends \TAccountCheckerExtended
{
    public $mailFiles = "orbitz/it-1746917.eml";

    public $reFrom = "#orbitz#i";
    public $reProvider = "#orbitz#i";
    public $rePlain = "#Orbitz\s+record\s+locator\s+.*\s+Hotel\s+Confirmation#i";
    public $rePlainRange = "";
    public $typesCount = "1";
    public $langSupported = "en";
    public $reSubject = "";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $xPath = "";
    public $rePDF = "";
    public $rePDFRange = "";
    public $pdfRequired = "0";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    return [$text];
                },

                "#.*?#" => [
                    "ConfirmationNumber" => function ($text = '', $node = null, $it = null) {
                        $regex = '#Hotel\s+confirmation\s+for\s+room\s+held\s+under\s+(.*?)\s*:\s+([\w\-]+)#i';

                        if (preg_match_all($regex, $text, $matches)) {
                            $res = null;
                            $res['GuestNames'] = $matches[1];
                            $res['ConfirmationNumber'] = $matches[2][0];

                            if (count($matches[1]) > 1) {
                                $res['ConfirmationNumbers'] = implode(',', $matches[2]);
                            }

                            return $res;
                        }
                    },

                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "R";
                    },

                    "HotelName" => function ($text = '', $node = null, $it = null) {
                        return nice(re('#(.*)\s+hotel\s+details#'));
                    },

                    "CheckInDate" => function ($text = '', $node = null, $it = null) {
                        $regex = '#Check-in:.*\s+(\w+\s+\d+,\s+\d+)\s+\|.*\s+(\w+\s+\d+,\s+\d+)\s+.*:\s+(\d{1,2})(\d{2})\s+(\d{1,2})(\d{2})#';

                        if (preg_match($regex, $text, $m)) {
                            return [
                                'CheckInDate'  => strtotime($m[1] . ', ' . $m[3] . ':' . $m[4]),
                                'CheckOutDate' => strtotime($m[2] . ', ' . $m[5] . ':' . $m[6]),
                            ];
                        }
                    },

                    "Address" => function ($text = '', $node = null, $it = null) {
                        if (preg_match('#(.*)\s+Phone:\s+(.*)\s+\|\s+Fax:\s+(.*)#', $text, $m)) {
                            return [
                                'Address' => nice($m[1]),
                                'Phone'   => $m[2],
                                'Fax'     => $m[3],
                            ];
                        }
                    },

                    "Guests" => function ($text = '', $node = null, $it = null) {
                        return re('#Guest\(s\)\s*:?\s+(\d+)#');
                    },

                    "Rooms" => function ($text = '', $node = null, $it = null) {
                        return re('#Room\(s\)\s*:?\s+(\d+)#');
                    },

                    "CancellationPolicy" => function ($text = '', $node = null, $it = null) {
                        $subj = node('//td[contains(., "Hotel policies and additional billing information") and not(.//td)]');
                        $regex = '#';
                        $regex .= 'The\s+following\s+policies\s+apply(?:(?s).*?)\s+';
                        $regex .= 'Cancellation:\s+((?s).*?than\s+through\s+the\s+hotel\s+directly\.)';
                        $regex .= '#i';

                        if (preg_match_all($regex, $subj, $matches, PREG_SET_ORDER)) {
                            $cancellationPolicies = [];

                            foreach ($matches as $m) {
                                $key = nice(re('#policies\s+apply\s+to\s+the\s+room\s+held\s+for\s+(.*?)\s*\.#i', $m[0]));
                                $cancellationPolicies[$key] = $m[1];
                            }
                            $cancellationPoliciesWithoutDuplicates = array_unique($cancellationPolicies);

                            if (count($cancellationPoliciesWithoutDuplicates) == 1) {
                                return reset($cancellationPoliciesWithoutDuplicates);
                            } else {
                                $result = [];

                                foreach ($cancellationPolicies as $key => $value) {
                                    $result[] = $key . '\'s room: ' . $value;
                                }

                                return implode('|', $result);
                            }
                        }
                    },

                    "RoomType" => function ($text = '', $node = null, $it = null) {
                        $xpath = '//td[contains(., "must check in to this room") and not(.//td)]';
                        $roomInfoNodes = nodes($xpath);
                        $rooms = [];

                        foreach ($roomInfoNodes as $n) {
                            $rooms[] = re('#Room\s+description\s*:\s+(.*?)\s+Special\s+requests\s*:#');
                        }

                        return implode('|', $rooms);
                    },

                    "Taxes" => function ($text = '', $node = null, $it = null) {
                        return cost(cell('Taxes and fees', +1));
                    },

                    "Total" => function ($text = '', $node = null, $it = null) {
                        $subj = cell('Total due at booking', +1);

                        if ($subj) {
                            return [
                                'Total'    => cost($subj),
                                'Currency' => currency($subj),
                            ];
                        }
                    },

                    "ReservationDate" => function ($text = '', $node = null, $it = null) {
                        return strtotime(re('#This\s+reservation\s+was\s+made\s+on\s+\w+,\s+(.*)#'));
                    },
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
