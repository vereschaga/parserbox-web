<?php

namespace AwardWallet\Engine\jetairways\Email;

class It1953834 extends \TAccountCheckerExtended
{
    public $mailFiles = "jetairways/it-1953834.eml, jetairways/it-1953844.eml, jetairways/it-1966469.eml, jetairways/it-1979226.eml";

    public $rePDF = "#Jet&\#160;Airways#i";
    public $rePDFRange = "5000";

    private $name = '';

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    $text = $this->getDocument("application/pdf", "html");

                    if (!re($this->rePDF, $text)) { // should be removed after update
                        return null;
                    }

                    $text = $this->setDocument("application/pdf", "simpletable");

                    $nodes = xpath("//*[contains(text(), 'CUSTOMER') and contains(text(), 'COPY')]/ancestor::tr[1]");
                    $res = [];

                    foreach ($nodes as $node) {
                        $res[] = "<table>" . html(xpath("following-sibling::tr[position()<27]", $node)) . "</table>";
                    }

                    $this->setDocument('source', implode('', $res));

                    return xpath('//table');
                },

                "#.*?#" => [
                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "T";
                    },

                    "RecordLocator" => function ($text = '', $node = null, $it = null) {
                        $td = filter(nodes(".//*[contains(text(), 'SEQ') and contains(text(), 'No')]/ancestor::tr[1]/following-sibling::tr[1]/td[string-length(normalize-space(.))>1]"));

                        return re("#^[A-Z\d\-]+$#", next($td));
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        $name = text(xpath("(//*[contains(text(), 'FFP') and contains(text(), 'Number')]/ancestor::tr[1]/following-sibling::tr[1]/td[string-length(normalize-space(.))>1][1])[1]"));
                        $name = beautifulName(nice($name));
                        $name = preg_replace_callback("#^([^\s]*?)mr(?:\s+|$)#", function ($m) {
                            return $m[1] . ' Mr ';
                        }, $name);
                        $this->name = $name;

                        return [$name];
                    },

                    "TicketNumbers" => function ($text = '', $node = null, $it = null) {
                        $ticketNumbers = $this->http->FindNodes('//tr[ ./td[string-length(normalize-space(.))>1][position()=1 and normalize-space(.)="Eticket"] ]/following-sibling::tr[string-length(normalize-space(.))>1][1]/td[string-length(normalize-space(.))>1][1]', null, '/^([-\d\s]+)$/');
                        $ticketNumberValues = array_values(array_filter($ticketNumbers));

                        return array_unique($ticketNumberValues);
                    },

                    "AccountNumbers" => function ($text = '', $node = null, $it = null) {
                        return [$this->http->FindSingleNode(".//tr[contains(normalize-space(.), 'Number') and contains(normalize-space(.), 'FFP')]/following-sibling::tr[1]", null, true, "/{$this->name}\s+(\w{5,12})/i")];
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        return [$node];
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            $td = filter(nodes(".//*[contains(text(), 'Flight') and contains(text(), 'No')]/ancestor::tr[1]/following-sibling::tr[1]/td[string-length(normalize-space(.))>1]"));
                            array_shift($td);

                            return [
                                'DepCode'      => re("#^([A-Z]{3})\s*/\s*([A-Z]{3})$#", end($td)),
                                'ArrCode'      => re(2),
                                'AirlineName'  => re("#^([A-Z\d]{2})\s*(\d+)$#", reset($td)),
                                'FlightNumber' => re(2),
                                'DepDate'      => $this->normalizeDate(next($td) . ',' . next($td)),
                            ];
                        },

                        "ArrDate" => function ($text = '', $node = null, $it = null) {
                            return MISSING_DATE;
                        },

                        "BookingClass" => function ($text = '', $node = null, $it = null) {
                            $td = filter(nodes(".//*[contains(text(), 'Boarding') and contains(text(), 'Time')]/ancestor::tr[1]/following-sibling::tr[1]/td[string-length(normalize-space(.))>=1]"));

                            return [
                                'Seats'        => re("#^\d+[A-Z]+$#", end($td)),
                                'BookingClass' => re("#\n([A-Z])\n#", implode("\n", $td)),
                            ];
                        },
                    ],
                ],

                "PostProcessing" => function ($text = '', $node = null, $it = null) {
                    return correctItinerary($it, true, true);
                },
            ],
        ];
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@jetairways.com') !== false
            || stripos($from, 'Jet Airways') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (stripos($headers['from'], 'info@jetairways.com') !== false || stripos($headers['from'], 'Jet Airways eBoarding Pass') !== false) {
            return true;
        }

        if (stripos($headers['subject'], 'Jet Airways') !== false && stripos($headers['subject'], 'e-Boarding Pass') !== false) {
            return true;
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName('.*pdf');

        foreach ($pdfs as $pdf) {
            $textPdf = \PDF::convertToText($parser->getAttachmentBody($pdf));
            $textPdf = preg_replace("#[^\w\d,. \t\r\n_/+:;\"'’<>«»?~`!@\#$%^&*\[\]=\(\)\-–{}£¥₣₤₧€\$\|]#imsu", ' ', $textPdf);

            if (stripos($textPdf, 'our Jet Airways') === false) {
                continue;
            }

            if (stripos($textPdf, 'From/To') !== false || stripos($textPdf, 'From / To') !== false) {
                return true;
            }
        }

        return false;
    }

    private function normalizeDate($str)
    {
        $in = [
            '/(\d{1,2})\s+(\w+)\s+(\d{2,4})\s*\,?\s*(\d{2})(\d{2}),?\s*.*/',
        ];
        $out = [
            '$1 $2 $3, $4:$5',
        ];

        return strtotime(preg_replace($in, $out, $str));
    }
}
