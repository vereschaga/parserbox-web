<?php

namespace AwardWallet\Engine\skywards\Email;

class It8 extends \TAccountCheckerExtended
{
    public $reFrom = "#@emirates.com#i";
    public $reProvider = "#[@.]emirates.com#i";
    public $rePlain = "#emirates#i";
    public $rePlainRange = "";
    public $typesCount = "1";
    public $langSupported = "en";
    public $reSubject = "#ET Receipt:#";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $xPath = "";
    public $mailFiles = "skywards/it-12.eml, skywards/it-3298082.eml, skywards/it-3418621.eml, skywards/it-8.eml, skywards/it-8935651.eml, skywards/it-9012859.eml";
    public $rePDF = "";
    public $rePDFRange = "";
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
                        return trim(explode("\n", cell('BOOKING REFERENCE', +1))[0]);
                    },

                    "TripNumber" => function ($text = '', $node = null, $it = null) {
                        return trim(explode("\n", cell('TRIPS NUMBER', +1))[0]);
                    },

                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "T";
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        return preg_replace("/^\s*(.+?) *\/ *(.+?) *(?:MR|MRS|MISS)\s*$/", '$2 $1', nice(explode("\n", cell('PASSENGER NAME', +1))));
                    },

                    "TicketNumbers" => function ($text = '', $node = null, $it = null) {
                        return [cell('E-TICKET NUMBER', +1)];
                    },

                    "TotalCharge" => function ($text = '', $node = null, $it = null) {
                        return cost(re("#\n\s*TOTAL\s+([A-Z\d+.,]+)#"));
                    },

                    "Currency" => function ($text = '', $node = null, $it = null) {
                        $result = [];

                        // Currency
                        $result['Currency'] = currency(re("#\n\s*TOTAL\s+([A-Z]+)#"));

                        if (empty($result['Currency'])) {
                            return null;
                        }

                        // BaseFare
                        $fareValue = re('/\n\s*FARE\s+(?:' . preg_quote($result['Currency'], '/') . ')?\s*(\d[,.\'\d]*)/')
                            ?? re('/\n\s*EQUIVALENT FARE\s+(?:' . preg_quote($result['Currency'], '/') . ')?\s*(\d[,.\'\d]*)/');
                        $result['BaseFare'] = cost($fareValue);

                        // Fees
                        $fees = [];
                        $feesValue = $this->http->FindSingleNode('//td[normalize-space()="TAXES/FEES/CHARGES"]/following::td[normalize-space()][1]');

                        if (preg_match_all('/\b' . preg_quote($result['Currency'], '/') . '(\d[,.\'\d]*)[-]*([A-Z][A-Z\d]|[A-Z\d][A-Z])\b/', $feesValue, $matches, PREG_SET_ORDER)) {
                            // AUD29.20-AE AUD13.70-F6 AUD2.00-TP    |    AED5ZR AED60CN
                            foreach ($matches as $m) {
                                $fees[] = ['Name' => $m[2], 'Charge' => $m[1]];
                            }
                        }

                        if (count($fees)) {
                            $result['Fees'] = $fees;
                        }

                        return $result;
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        return xPath("(//*[contains(text(), 'FLIGHT')]/ancestor-or-self::tr[1]/..)[1]//*[contains(text(), 'FLIGHT')]/ancestor-or-self::tr[1]");
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            return uberFlight(node("following-sibling::tr[3]/td[1]"));
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            return re("#\(([A-Z]{3})\)#", node("following-sibling::tr[normalize-space(.)][1]/td[3]"));
                        },

                        "DepartureTerminal" => function ($text = '', $node = null, $it = null) {
                            return re("#TERMINAL\s*(.*)#", node("following-sibling::tr[normalize-space(.)][1]/td[3]"));
                        },

                        "DepDate" => function ($text = '', $node = null, $it = null) {
                            return strtotime($this->normalizeDate(
                                implode(' ', $this->http->FindNodes("following-sibling::tr[normalize-space()][1]/td[2]/descendant::text()[normalize-space()]", $node))
                            ));
                        },

                        "ArrCode" => function ($text = '', $node = null, $it = null) {
                            return re("#\(([A-Z]{3})\)#", node("following-sibling::tr[normalize-space(.)][2]/td[3]"));
                        },

                        "ArrivalTerminal" => function ($text = '', $node = null, $it = null) {
                            return re("#TERMINAL\s*(.*)#", node("following-sibling::tr[normalize-space(.)][2]/td[3]"));
                        },

                        "ArrDate" => function ($text = '', $node = null, $it = null) {
                            return strtotime($this->normalizeDate(
                                implode(' ', $this->http->FindNodes("following-sibling::tr[normalize-space()][2]/td[2]/descendant::text()[normalize-space()]", $node))
                            ));
                        },

                        "AirlineName" => function ($text = '', $node = null, $it = null) {
                            return uberAirline(node("following-sibling::tr[3]/td[1]"));
                        },

                        "Cabin" => function ($text = '', $node = null, $it = null) {
                            return node("following-sibling::tr[3]/td[5]");
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

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        return stripos($body, 'emirates.com') !== false && strpos($body, 'DEPART/ARRIVE') !== false;
    }

    private function normalizeDate($date)
    {
        $in = [
            //22 MAR 2014 1430    |    22JAN20 0850
            '/^\s*(\d{1,2})\s*([[:alpha:]]{3,})\s*(\d{2})\s+(\d{2})[:]*(\d{2})\s*$/u',
            '/^\s*(\d{1,2})\s*([[:alpha:]]{3,})\s*(\d{4})\s+(\d{2})[:]*(\d{2})\s*$/u',
        ];
        $out = [
            '$1 $2 20$3 $4:$5',
            '$1 $2 $3 $4:$5',
        ];
        $str = preg_replace($in, $out, $date);

        return $str;
    }
}
