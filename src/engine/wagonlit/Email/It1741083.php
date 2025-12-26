<?php

namespace AwardWallet\Engine\wagonlit\Email;

class It1741083 extends \TAccountCheckerExtended
{
    public $mailFiles = "";

    public $reFrom = "#wagonlit#i";
    public $reProvider = "#wagonlit#i";
    public $rePlain = "#\n[>\s*]*From\s*:[^\n]*?wagonlit#i";
    public $rePlainRange = "";
    public $typesCount = "1";
    public $langSupported = "pt";
    public $reSubject = "";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $xPath = "";
    public $rePDF = "";
    public $rePDFRange = "";
    public $pdfRequired = "0";
    public $isAggregator = "1";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    //$text = clear("#\(\s*OPERATED\s+BY[^\)]+\)#", $text);
                    return splitter("#([\dA-Z\-]+\s+Voo\s+[^\n]+\s+[A-Z\d]{2}\d+\s+PARTIDA|[^\n]+\s+Confirma.*?o[:\s]+[\d\w\-]+\s+Hotel\s+|[^\n]+\s+Confirma.*?o[:\s]+[\d\w\-]+\s+Carro\s+)#", $text);
                },

                "#\s+Hotel\s+#" => [
                    "ConfirmationNumber" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Confirma.*?o:\s+([\d\w\-]+)#");
                    },

                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return 'R';
                    },

                    "HotelName" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Hotel\s+([^\n]+)#ms");
                    },

                    "CheckInDate" => function ($text = '', $node = null, $it = null) {
                        return totime(en(re("#\n\s*Check\-In\s+([^\n]+)#")));
                    },

                    "CheckOutDate" => function ($text = '', $node = null, $it = null) {
                        return totime(en(re("#\n\s*Check\-Out\s+([^\n]+)#")));
                    },

                    "Address" => function ($text = '', $node = null, $it = null) {
                        $addr = re("#\n\s*LOCAL\s+CONTATO\s+(.*)\s+Reservado\s+#ms");

                        $phone = re("#\n\s*Tel[\s:]+([\d\-+ \(\)]+)#", $addr);
                        $fax = re("#\n\s*Fax[\s:]+([\d\-+ \(\)]+)#", $addr);

                        $addr = clear("#\s+(?:Tel|Fax)[\s:]+[\d\-+ \(\)]+\s*$#ms", $addr);

                        return [
                            'Address' => nice(glue($addr)),
                            'Phone'   => $phone,
                            'Fax'     => $fax,
                        ];
                    },

                    "GuestNames" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Reservado Para\s+([^\n]+)#");
                    },

                    "Rooms" => function ($text = '', $node = null, $it = null) {
                        return re("#de Quartos\s+(\d+)#");
                    },

                    "Rate" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Taxa\s+([^\n]+)#");
                    },

                    "CancellationPolicy" => function ($text = '', $node = null, $it = null) {
                        return re("#ica de Cancelamento\s*([^\n]+)#");
                    },

                    "Status" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Status\s+([^\n]+)#");
                    },
                ],

                "#\s+Voo\s+#" => [
                    "RecordLocator" => function ($text = '', $node = null, $it = null) {
                        return re("#^([A-Z\d\-]+)#");
                    },

                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return 'T';
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Viajante\s+([^\n]+)#", $this->text());
                    },

                    "TotalCharge" => function ($text = '', $node = null, $it = null) {
                        return cost(re("#\n\s*Valor\s+Total[:\s]+([^\n]+)#", $this->text()));
                    },

                    "Currency" => function ($text = '', $node = null, $it = null) {
                        return currency(re(1));
                    },

                    "ReservationDate" => function ($text = '', $node = null, $it = null) {
                        $date = re("#Localizador:\s+[A-Z\d\-]+\s+Data:\s*([^\n]+)#", $this->text());

                        if ($tr = en($date)) {
                            $date = $tr;
                        } else {
                            $date = explode('/', $date);

                            if (isset($date[2])) {
                                $date = $date[1] . '/' . $date[0] . '/' . $date[2];
                            }
                        }

                        return totime($date);
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        return [$text];
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            return [
                                'AirlineName'  => re("#\n\s*Voo\s+([^\n]*?)\s+(\d+)#"),
                                'FlightNumber' => re(2),
                            ];
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            return ure("#\n\s*([A-Z]{3})\s*\-#", 1);
                        },

                        "DepDate" => function ($text = '', $node = null, $it = null) {
                            $date = ure("#(\d+/\d+/\d+)#");

                            if ($date) {
                                $dates = explode("/", $date);

                                if (isset($dates[2])) {
                                    $date = $dates[1] . '/' . $dates[0] . '/' . $dates[2];
                                    $date = $date . ', ' . uberTime();
                                }
                            } else {
                                $date = re("#\n\s*(\d+:\d+)\s*,\s*([^\n]+)#");
                                $date = en(re(2)) . ', ' . re(1);
                            }

                            return totime($date);
                        },

                        "ArrCode" => function ($text = '', $node = null, $it = null) {
                            return ure("#\n\s*([A-Z]{3})\s*\-#", 2);
                        },

                        "ArrDate" => function ($text = '', $node = null, $it = null) {
                            $date = ure("#(\d+/\d+/\d+)#", 2);

                            if ($date) {
                                $dates = explode("/", $date);

                                if (isset($dates[2])) {
                                    $date = $dates[1] . '/' . $dates[0] . '/' . $dates[2];
                                    $date = $date . ', ' . uberTime(2);
                                }
                            } else {
                                $date = re("#\s+\d{2}:\d{2}.*?\s+(\d+:\d+)\s*,\s*([^\n]+)#ms");
                                $date = en(re(2)) . ', ' . re(1);
                            }

                            return totime($date);
                        },

                        "Aircraft" => function ($text = '', $node = null, $it = null) {
                            return re("#\n\s*Equipamento\s+([^\n]+)#");
                        },

                        "Cabin" => function ($text = '', $node = null, $it = null) {
                            return [
                                'Cabin'        => trim(re("#\n\s*Classe\s+(.*?)\s*\-\s*([A-Z])\s*\n#")),
                                'BookingClass' => re(2),
                            ];
                        },

                        "Seats" => function ($text = '', $node = null, $it = null) {
                            return re("#\n\s*Assentos\s+Reservados\s+(\d+[A-Z]+)#");
                        },

                        "Duration" => function ($text = '', $node = null, $it = null) {
                            return re("#\n\s*Dura.*?o\s+([^\n]+)#");
                        },

                        "Meal" => function ($text = '', $node = null, $it = null) {
                            return re("#\n\s*Refei.*?o\s+([^\n]+)#");
                        },
                    ],
                ],

                "PostProcessing" => function ($text = '', $node = null, $it = null) {
                    return correctItinerary($it, true);
                },

                "#\s+Carro\s+#" => [
                    "Number" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Confirma.*?o:\s+([\d\w\-]+)#");
                    },

                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return 'L';
                    },

                    "PickupLocation" => function ($text = '', $node = null, $it = null) {
                        $r = filter(explode("\t", re("#DROP\-OFF\s+(?:\d+:\d+,[^\n]+\s+)+(.*?)\n\s*((?:[\d+\(\) \-]+)+)\s+Reservado Para#ms")));
                        $phone = filter(explode("\t", re(2)));

                        return [
                            'PickupLocation'  => reset($r),
                            'DropoffLocation' => end($r),
                            'PickupPhone'     => reset($phone),
                            'DropoffPhone'    => end($phone),
                        ];
                    },

                    "PickupDatetime" => function ($text = '', $node = null, $it = null) {
                        return totime(en(uberDate(2)) . ',' . uberTime(1));
                    },

                    "DropoffDatetime" => function ($text = '', $node = null, $it = null) {
                        return totime(en(uberDate(3)) . ',' . uberTime(2));
                    },

                    "RentalCompany" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Carro\s+([^\n]+)#");
                    },

                    "CarType" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Tipo de Carro\s*([^\n]+)#");
                    },

                    "RenterName" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Reservado Para\s+([^\n]+)#");
                    },

                    "TotalCharge" => function ($text = '', $node = null, $it = null) {
                        return cost(re("#\n\s*Total Aproximado\s+([A-Z]+\s*[\d.,]+)#"));
                    },

                    "Currency" => function ($text = '', $node = null, $it = null) {
                        return currency(re(1));
                    },

                    "Status" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Status\s*([^\n]+)#");
                    },
                ],
            ],
        ];
    }

    public static function getEmailLanguages()
    {
        return ['pt'];
    }

    public static function getEmailTypesCount()
    {
        return 1;
    }

    public function IsEmailAggregator()
    {
        return true;
    }
}
