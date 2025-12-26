<?php

namespace AwardWallet\Engine\ryanair\Email;

class RyanairTravelItineraryPlain extends \TAccountCheckerExtended
{
    public $rePlain = "#\n*[>\s]*From\s*:[^\n]*?ryanair#i";
    public $rePlainRange = "1000";
    public $reHtml = "";
    public $reHtmlRange = "1000";
    public $rePDF = "";
    public $rePDFRange = "";
    public $reSubject = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $reFrom = "#ryanair#i";
    public $reProvider = "#ryanair#i";
    public $xPath = "";
    public $mailFiles = "ryanair/it-1701626.eml, ryanair/it-1900566.eml, ryanair/it-4026258.eml, ryanair/it-4027920.eml, ryanair/it-4043662.eml, ryanair/it-4045539.eml, ryanair/it-4045540.eml, ryanair/it-4059511.eml, ryanair/it-4089677.eml, ryanair/it-4089685.eml, ryanair/it-4092124.eml, ryanair/it-4092131.eml, ryanair/it-4092133.eml, ryanair/it-4102461.eml, ryanair/it-4102540.eml, ryanair/it-4102545.eml, ryanair/it-4102559.eml, ryanair/it-4148678.eml, ryanair/it-4149137.eml, ryanair/it-4153159.eml, ryanair/it-4153161.eml, ryanair/it-4153163.eml, ryanair/it-4153166.eml, ryanair/it-4153167.eml, ryanair/it-4153858.eml";
    public $pdfRequired = "0";

    private $detects = [
        'THANK YOU FOR BOOKING WITH RYANAIR',
        'If you are transferring to a new Ryanair flight',
        'On behalf of Ryanair, we sincerely apologise for the delay to your recent flight',
    ];

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        if (stripos($body, 'RYANAIR') === false) {
            return false;
        }

        foreach ($this->detects as $detect) {
            if (false !== stripos($body, $detect)) {
                return true;
            }
        }

        return false;
    }

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    $pdfs = $this->parser->searchAttachmentByName('Delay Confirmation.pdf');

                    if (isset($pdfs[0])) {
                        $pdf = $pdfs[0];

                        if (($html = \PDF::convertToHtml($this->parser->getAttachmentBody($pdf), \PDF::MODE_SIMPLE)) !== null) {
                            return [text($html)];
                        }
                    }

                    if (empty($text)) {
                        $text = $this->parser->getPlainBody();
                    }

                    return [$text];
                },

                "#.*?#" => [
                    "RecordLocator" => function ($text = '', $node = null, $it = null) {
                        return orval(
                            re("#YOUR\s+(?:RESERVATION|CONFIRMATION|CONFIRMED RESERVATION)\s+NUMBER\s+IS\s*:\s*(\w+)#"),
                            re("#\n\s*Booking Confirmation\s*:\s*([\w\-]+)#"),
                            re("#Reservation Number\s*:\s*([\w\-]+)#"),
                            re("#\n\s*O SEU NÚMERO DE RESERVA É\s*:\s*([\w\-]+)#"),
                            re("#\n\s*SU NÚMERO DE CONFIRMACIÓN ES\s*:\s*([\w\-]+)#"),
                            re("#CONFIRMATION\s+NUMBER\s*:\s*([\w\-]+)#"),
                            re("#\n\s*DIT BEKRÆFTELSESNUMMER ER\s*:\s*([\w\-]+)#"),
                            re("#\n\s*IL NUMERO DI PRENOTAZIONE\s*:\s*([\w\-]+)#")
                        );
                    },

                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "T";
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        $passengers = trim(re("#\n\s*(?:PASSENGERS|PASSENGER DETAILS:|PASSAGERER|PASSEGGERI|Passenger name\(s\):)\s+(.*?)\s+(?:PAYMENT DETAILS|GOING OUT|UDREJSE|DETTAGLI DI PAGAMENTO)#msi"));

                        if (re("#(.*?)\n\s*\n\s*#msi", $passengers)) {
                            $passengers = re(1);
                        }
                        $passengers = preg_replace("#\s+ADT(?:\s+|$)#", " ", $passengers);

                        $passengers = nice(array_filter(preg_split("#(?:\d+\.|,)#", $passengers)));
                        $passengers = preg_replace("#^\s*(.{10,}?):.+#s", '$1', $passengers);

                        return $passengers;
                    },

                    "TotalCharge" => function ($text = '', $node = null, $it = null) {
                        return cost(orval(
                            re("#\*+([^\n]+)\s+(?:Total Paid|Totale pagato)\n#"),
                            re("#Total Paid(.*?)PAYMENT DETAILS:#ms")
                        ));
                    },

                    "BaseFare" => function ($text = '', $node = null, $it = null) {
                        return cost(orval(
                            re("#\*+([^\n]+)\s+(?:Total Fare|Tariffa totale)\n#"),
                            re("#Total Fare\s+(.+)#")
                        ));
                    },

                    "Currency" => function ($text = '', $node = null, $it = null) {
                        return currency(orval(
                            re("#\*+([^\n]+)\s+(?:Total Paid|Totale pagato)\n#"),
                            re("#Total Paid(.*?)PAYMENT DETAILS:#ms")
                        ));
                    },

                    "Tax" => function ($text = '', $node = null, $it = null) {
                        return cost(orval(
                            re("#\*+([^\n]+)\s+(?:Taxes, Fees & Charges|Tasse, tariffe e spese)\n#"),
                            re("#Taxes Fees and Charges\s+(.+)#")
                        ));
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        return splitter("#\n\s*(GOING OUT|COMING BACK|IDA|VOLTA|SALIDA|UDREJSE|HJEMREJSE|ANDATA)#");
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            if (re("#\s+(?:Flight|Flyvning|Volo)\s+(\d+)\s#")) {
                                return [
                                    'AirlineName'  => 'FR',
                                    'FlightNumber' => re(1),
                                ];
                            }

                            return [
                                'AirlineName'  => re("#\s+(?:Flight|Flyvning|Volo)\s+([A-Z\d]{2})\s*(\d+)#"),
                                'FlightNumber' => re(2),
                            ];
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            if (re("#From:#")) {
                                return [
                                    'DepCode'=> TRIP_CODE_UNKNOWN,
                                    'DepName'=> re("#From:\s+(.*?)\s+To:#"),
                                ];
                            }

                            return orval(
                                ure("#\(([A-Z]{3})\)#"),
                                re("#Depart ([A-Z]{3}) at#")
                            );
                        },

                        "DepDate" => function ($text = '', $node = null, $it = null) {
                            $date = orval(
                                re("#\s+(\d{2}[A-Za-z]{3}(?:\d{2}|\d{4}))\s+#"),
                                str_replace("/", ".", re("#(\d+/\d+/\d{4})#")),
                                str_replace("-", ".", re("#(\d+-\d+-\d{4})#")),
                                en(str_replace("/", " ", re("#(\d+/\w+/\d{4})#"))),
                                uberDate()
                            );
                            // if(!$date) $date = str_replace("/", ".", re("#\s+(\d+/\d+/\d{4})\s+#"));
                            $dep = strtotime($date . ',' . re("#^(\d{1,2}):?(\d{2})$#", re("#(?:at|den|alle)\s+(\d{1,2}:?\d{2})(\s+|$)#")) . ':' . re(2));
                            $arr = strtotime($date . ',' . re("#^(\d{1,2}):?(\d{2})$#", ure("#(?:at|den|alle)\s+(\d{1,2}:?\d{2})(?:\s+|$)#", 2)) . ':' . re(2));

                            if (!ure("#(?:at|den|alle)\s+(\d{1,2}:?\d{2})(?:\s+|$)#", 2)) {
                                $arr = MISSING_DATE;
                            } elseif ($arr < $dep) {
                                $arr = strtotime("+1 day", $arr);
                            }

                            // correctDates($dep, $arr);

                            return [
                                'DepDate' => $dep,
                                'ArrDate' => $arr,
                            ];
                        },

                        "ArrCode" => function ($text = '', $node = null, $it = null) {
                            if (re("#To:#")) {
                                return [
                                    'ArrCode'=> TRIP_CODE_UNKNOWN,
                                    'ArrName'=> re("#To:\s+(.*?)\s+on\s+#"),
                                ];
                            }

                            return orval(
                                ure("#\(([A-Z]{3})\)#", 2),
                                re("#arrive ([A-Z]{3}) at#")
                            );
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
        return ["en", "it", "da", "es", "pt"];
    }
}
