<?php

namespace AwardWallet\Engine\turkish\Email;

class It1701666 extends \TAccountCheckerExtended
{
    public $reFrom = "#thy\.#i";
    public $reProvider = "#@thy\.#i";
    public $rePlain = "#\n[>\s*]*From\s*:[^\n]*?thy.com#i";
    public $rePlainRange = "";
    public $typesCount = "1";
    public $langSupported = "en";
    public $reSubject = "";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $xPath = "";
    public $mailFiles = "turkish/it-1701664.eml, turkish/it-1701666.eml";
    public $rePDF = "";
    public $rePDFRange = "";
    public $pdfRequired = "0";

    private $subjects = [
        'en' => ['Reservations Information', 'Ticket Information'],
    ];

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    $text = $this->setDocument("text/html", "html");

                    return [$text];
                },

                "#.*?#" => [
                    "RecordLocator" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Reservation Code\s+([\dA-Z\-]+)#");
                    },

                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "T";
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        return nodes("//*[contains(text(), 'Name and Surname')]/ancestor-or-self::thead[1]/following-sibling::tbody/tr/td[1]");
                    },

                    "TicketNumbers" => function ($text = '', $node = null, $it = null) {
                        $ticketNumbers = $this->http->FindNodes("//*[contains(text(), 'Name and Surname')]/ancestor-or-self::thead[1]/following-sibling::tbody/tr/td[2]", null, '/^\d{3}[- ]*\d{5,}[- ]*\d{1,2}$/');

                        return array_filter($ticketNumbers);
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        return xpath("//*[contains(text(), 'From To')]/ancestor::table[1]/tbody/tr[ *[7] and count(*[normalize-space()])>3 ]");
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            return [
                                'AirlineName'  => re("#([A-Z\d]{2})\s*(\d+)#", node('td[3]')),
                                'FlightNumber' => re(2),
                            ];
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
                        },

                        "DepName" => function ($text = '', $node = null, $it = null) {
                            $r = nodes("td[1]//text()");

                            return [
                                'DepName' => reset($r),
                                'ArrName' => end($r),
                            ];
                        },

                        "DepDate" => function ($text = '', $node = null, $it = null) {
                            $date = implode('-', array_reverse(explode('.', node('td[2]'))));

                            $dep = $date . ', ' . node('td[4]');
                            $arr = $date . ', ' . node('td[5]');

                            correctDates($dep, $arr);

                            return [
                                'DepDate' => $dep,
                                'ArrDate' => $arr,
                            ];
                        },

                        "ArrCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
                        },

                        "Cabin" => function ($text = '', $node = null, $it = null) {
                            return node("td[7]");
                        },

                        "BookingClass" => function ($text = '', $node = null, $it = null) {
                            return node("td[6]");
                        },
                    ],
                ],
            ],
        ];
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@thy.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (self::detectEmailFromProvider($headers['from']) !== true && strpos($headers['subject'], 'Turkish Airlines') === false) {
            return false;
        }

        foreach ($this->subjects as $phrases) {
            foreach ((array) $phrases as $phrase) {
                if (is_string($phrase) && stripos($headers['subject'], $phrase) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if (self::detectEmailFromProvider($parser->getHeader('from')) !== true
            && $this->http->XPath->query('//a[contains(@href,".thy.com/")]')->length === 0
            && $this->http->XPath->query('//node()[contains(normalize-space(),"Thank you for choosing Turkish Airlines") or contains(.,"@thy.com")]')->length === 0
        ) {
            return false;
        }

        return $this->http->XPath->query('//node()[contains(normalize-space(),"Detailed information about your booking is shown below") or contains(normalize-space(),"Detailed information about your reservation is shown below")]')->length > 0
            && $this->http->XPath->query('//node()[contains(normalize-space(),"Name and Surname") or contains(normalize-space(),"Name and surname")]')->length > 0;
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
