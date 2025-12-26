<?php

namespace AwardWallet\Engine\hotwire\Email;

class ConfirmationOfHotelReservation extends \TAccountCheckerExtended
{
    public $reFrom = "#customersupport@hotwire\.com#i";
    public $reProvider = "#hotwire\.com#i";
    public $rePlain = "#Dette\s+er\s+bekræftelsen\s+af\s+den\s+rejse,\s+du\s+har\s+reserveret.*?Hotwire,\c+Inc#is";
    public $rePlainRange = "";
    public $typesCount = "1";
    public $langSupported = "da";
    public $reSubject = "#Bekræftelse\s+af\s+hotelreservation\s+via\s+Hotwire#i";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $xPath = "";
    public $mailFiles = "hotwire/it-1813513.eml, hotwire/it-1813514.eml, hotwire/it-1813516.eml";
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
                        $subj = re('#Hotelbekræftelse\s*:\s*:?\s*((?:[\w\-]+,?\s*)+)\s+Hotwire-rejseplan#i');

                        if (stripos($subj, ',') !== false) {
                            $confNos = explode(',', $subj);

                            return [
                                'ConfirmationNumber'  => $confNos[0],
                                'ConfirmationNumbers' => nice($subj),
                            ];
                        } else {
                            return nice($subj);
                        }
                    },

                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "R";
                    },

                    "HotelName" => function ($text = '', $node = null, $it = null) {
                        if (preg_match('#\n\s*Hotel\s+(.*)\s+((?s).*?)\s+Telefon\s*:\s+(.*)#i', $text, $m)) {
                            return [
                                'HotelName' => $m[1],
                                'Address'   => nice($m[2], ','),
                                'Phone'     => $m[3],
                            ];
                        }
                    },

                    "CheckInDate" => function ($text = '', $node = null, $it = null) {
                        $xpath = '//tr[contains(., "Datoer") and contains(., "Voksne") and not(.//tr)]/following-sibling::tr[1]/td';
                        $reservationInfoNodes = nodes($xpath);

                        if (count($reservationInfoNodes) == 4) {
                            $dateStrs = explode('-', $reservationInfoNodes[0]);

                            if (count($dateStrs) == 2) {
                                [$ciDateStr, $coDateStr] = $dateStrs;

                                return [
                                    'CheckInDate'  => strtotime($ciDateStr),
                                    'CheckOutDate' => strtotime($coDateStr),
                                    'Guests'       => $reservationInfoNodes[1],
                                    'Kids'         => $reservationInfoNodes[2],
                                    'Rooms'        => re('#^(\d+)#', $reservationInfoNodes[3]),
                                ];
                            }
                        }
                    },

                    "Rate" => function ($text = '', $node = null, $it = null) {
                        return nice(re('#.*\s*/\s*overnatning#i'));
                    },

                    "CancellationPolicy" => function ($text = '', $node = null, $it = null) {
                        return re('#Hotellets\s+afbestillingspolitik\s+(.*)#i');
                    },

                    "Cost" => function ($text = '', $node = null, $it = null) {
                        return cost(cell('Subtotal', +1));
                    },

                    "Taxes" => function ($text = '', $node = null, $it = null) {
                        return cost(cell('Tax recovery charges & fees', +1));
                    },

                    "Total" => function ($text = '', $node = null, $it = null) {
                        return total(cell('Beløb i alt', +1), 'Total');
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
        return ["da"];
    }
}
