<?php

namespace AwardWallet\Engine\swissair\Email;

// parsers with similar formats: ETicketConfirmationPdf

class It2871579 extends \TAccountCheckerExtended
{
    public $mailFiles = "swissair/it-2974875.eml, swissair/it-2974878.eml, swissair/it-4736182.eml, swissair/it-4736193.eml, swissair/it-4781269.eml, swissair/it-4875029.eml, swissair/it-5077539.eml, swissair/it-5090680.eml, swissair/it-5098670.eml, swissair/it-6206273.eml";

    public $rePlain = [
        ['#Swiss.+?Your\s+e\-ticket\s+is\s+attached\s+in this\s+PDF\s+document#si', 'blank', '10000'],
    ];
    public $reHtml = "";
    public $rePDF = "";
    public $reFrom = [
        ['#[.@]swiss\.com#', 'blank', ''],
    ];
    public $reProvider = "swiss.com";
    public $fnLanguage = "";
    public $langSupported = "";
    public $typesCount = "";
    public $isAggregator = "";
    public $caseReference = "";
    public $upDate = "12.08.2015, 16:25";
    public $crDate = "";
    public $xPath = "";
    public $re_catcher = "#.*?#";
    public $pdfRequired = "";
    public $pdfComplexText = "";

    public $pdf;

    public $reSubject = [
        'es'             => ['E-TICKET CONFIRMATION', 'Your electronic document(s)'],
        'en, de, pt, fr' => ['E-TICKET CONFIRMATION'],
    ];

    public $lang = '';

    //	public $reBody = [
    //		'es' => ['Su billete electrónico y otros resguardos se adjuntan al presente correo electrónico', 'Adjunto le enviamos su confirmación de billete electrónico en formato PDF'],
    //		'de' => ['Anbei erhalten Sie Ihre elektronische Flugscheinbestätigung als PDF-Dokument', 'Bitte drucken Sie die Reisebestätigung aus und führen Sie diese während der ganzen Reise mit'],
    //		'pt' => ['Enviamos em anexo a sua confirmação etix sob a forma de documento PDF', 'Obrigado por viajar com a SWISS Estamos'],
    //		'fr' => ["Conformément à votre demande, voici votre confirmation d'émission de billet électronique au format PDF", 'Veuillez imprimer la confirmation de voyage et la conserver pendant toute la durée de votre séjour', 'Encore merci à toi et bonne journée', 'de billet électronique au format PDF'],
    //		'en' => ['Your e-ticket is attached in this PDF document', 'Please print your travel itinerary and keep it'],
    //	];

    public static $dict = [
        'es' => [
            //			'Frequent flyer number' => '',
            'Ticket number'      => 'Número de bil ette',
            'Reservation number' => 'Referencia de reserva',
            'Passenger'          => 'Nombre del pasajero',
            'Document'           => 'Documento',
            'Grand total'        => 'Suma total',
            'Item'               => 'Posición',
            'VAT'                => 'Tarifa',
            'Flight'             => 'Vuelo',
            'From'               => 'de',
            'To'                 => 'A',
            'Departure date'     => 'Fecha de salida',
            'Departure time'     => 'Hora de salida',
            'Arrival date'       => 'Llegada Fecha',
            'Arrival time'       => 'Hora de llegada',
            'Travel class'       => 'Clase',
        ],
        'de' => [
            //			'Frequent flyer number' => '',
            'Ticket number'      => 'Ticketnummer',
            'Reservation number' => 'Buchungsreferenz',
            'Passenger'          => 'Passagiername',
            'Document'           => 'Dokument',
            'Grand total'        => 'Gesamtbetrag',
            'Item'               => 'Element:',
            'VAT'                => 'Tarif',
            'Flight'             => 'Flug',
            'From'               => 'Von',
            'To'                 => 'Nach',
            'Departure date'     => 'Abflugsdatum',
            'Departure time'     => 'Abflugzeit',
            'Arrival date'       => 'Ankunftsdatum',
            'Arrival time'       => 'Ankunftszeit',
            'Travel class'       => 'Reiseklasse',
        ],
        'pt' => [
            'Frequent flyer number' => 'Nº Passageiro Frequente',
            'Ticket number'         => 'Número da passagem',
            'Reservation number'    => 'Código da reserva',
            'Passenger'             => 'Nome do passageiro',
            'Document'              => 'Documento',
            'Grand total'           => 'Total final',
            'Item'                  => 'Item',
            'VAT'                   => 'Tarifa',
            'Flight'                => 'Voo',
            'From'                  => 'De',
            'To'                    => 'Para',
            'Departure date'        => 'Data de partida',
            'Departure time'        => 'Hora de partida',
            'Arrival date'          => 'Data de chegada',
            'Arrival time'          => 'Hora de chegada',
            'Travel class'          => 'Classe de viagem',
        ],
        'fr' => [
            //			'Frequent flyer number' => '',
            'Ticket number'      => 'Numéro de billet',
            'Reservation number' => 'Référence de réservation',
            'Passenger'          => 'Nom du passager',
            'Document'           => 'Document',
            'Grand total'        => 'Montant total',
            'Item'               => 'Élément',
            'VAT'                => 'Tarif',
            'Flight'             => 'Vol',
            'From'               => 'De',
            'To'                 => 'A',
            'Departure date'     => 'Date de départ',
            'Departure time'     => 'Heure de départ',
            'Arrival date'       => "Date d'arrivée",
            'Arrival time'       => "Heure d'arrivée",
            'Travel class'       => 'Classe de voyage',
        ],
        'en' => [
            'Departure date' => 'Departure date',
            'Arrival time'   => 'Arrival time',
        ],
    ];

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName('.*pdf');

        foreach ($pdfs as $pdf) {
            $textPdf = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (strpos($textPdf, 'to flights operated by SWISS') === false && stripos($textPdf, 'SWISS Golf Traveller') === false && stripos($textPdf, 'SWISS wishes you a pleasant flight') === false && stripos($textPdf, 'on swiss.com') === false) {
                continue;
            }

            if ($this->assignLang($textPdf)) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (stripos($headers['from'], '@tripsource.com') === false) {
            return false;
        }

        foreach ($this->reSubject as $phrases) {
            foreach ($phrases as $phrase) {
                if (strpos($headers['subject'], $phrase) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    $text = $this->setDocument('application/pdf', 'text');

                    //					$this->pdfComplexText = $this->getDocument('application/pdf', 'complex');

                    $pdf = $this->parser->searchAttachmentByName('e-ticket.+\.pdf');
                    $pdfText = '';

                    foreach ($pdf as $p) {
                        $pdfText .= \PDF::convertToText($this->parser->getAttachmentBody($p));
                        $this->pdfComplexText .= \PDF::convertToHtml($this->parser->getAttachmentBody($p), \PDF::MODE_COMPLEX);
                    }

                    $arCleaner = [
                        ["#<html.+?<\/head>#si", ""],
                        ["#(?>&nbsp;|&\#160;|\s\s)+#u", " "],
                        ["#<\w+(?>[^>]+)?\/>#", "\n"],
                        ["#<(\w+)(?>[^>]+)?>([^<]*)<\/\g{1}>#", "\n\\2"],
                        ["#\n\s*l\s*?\n#", "\n"],
                    ];

                    foreach ($arCleaner as $cleanStaff) {
                        $replaced = 1;

                        while ($replaced) {
                            $this->pdfComplexText = preg_replace($cleanStaff[0], $cleanStaff[1], $this->pdfComplexText, -1, $replaced);
                        }
                    }

                    if ($this->assignLang($text) === false) {
                        return null;
                    }

                    return [$text];
                },

                "#.*?#" => [
                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "T";
                    },

                    "RecordLocator" => function ($text = '', $node = null, $it = null) {
                        return preg_replace("#^n\/a$#i", CONFNO_UNKNOWN, re("#" . $this->t('Reservation number') . "\s+(\S+)#ms"));
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        $result = [];

                        $passenger = re("#" . $this->t('Passenger') . "\s+" . $this->t('Document') . "\s+(\S+)#ms");

                        if ($passenger) {
                            $result['Passengers'] = [$passenger];
                        }

                        $ffNumber = re("#" . $this->t('Frequent flyer number') . "\s+([A-Z]{2}[-\s\/]+[A-Z\s]*[\d\s]{5,})#msi");

                        if ($ffNumber) {
                            $result['AccountNumbers'] = [trim($ffNumber)];
                        }

                        $ticketNumber = re("#" . $this->t('Ticket number') . "\s+(\d+)#msi");

                        if ($ticketNumber) {
                            $result['TicketNumbers'] = [$ticketNumber];
                        }

                        return $result;
                    },

                    "TotalCharge" => function ($text = '', $node = null, $it = null) {
                        return total(re("#" . $this->t('Grand total') . "\s+([,.\d]+)#ms"));
                    },

                    "Currency" => function ($text = '', $node = null, $it = null) {
                        if (!isset($it['Currency'])) {
                            $cur = re("#" . $this->t('Grand total') . "\s+([A-Z]+)#ms");

                            if (!isset($cur)) {
                                $cur = re("#" . $this->t('Item') . "\s+(\w+)(?:\s+" . $this->t('VAT') . "|$)#ms");
                            }

                            return $cur;
                        }

                        return $it['Currency'];
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        $itsRaw = re("#" . $this->t('Reservation number') . "(.+?\n)\s*(?:" . $this->t('Receipt') . "|" . $this->t('Baggage provisions') . "|" . $this->t('Important information') . ")\n\s*#si", $this->pdfComplexText);

                        return splitter("#(\s*" . $this->t('Flight') . "\s*[A-Z\d]{2}\d+\s*" . $this->t('From') . ".+?\s*\((?:\([A-Z]{3}\))?\s*[A-Z]{3}\))#s", $itsRaw);
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            $data = [];
                            $data['AirlineName'] = re("#\n\s*" . $this->t('Flight') . "\s+([A-Z\d]{2})(\d+)#i");
                            $data['FlightNumber'] = re(2);

                            return $data;
                        },

                        "DepName" => function ($text = '', $node = null, $it = null) {
                            return [
                                'DepName' => re("#\s*" . $this->t('From') . "\s+(.+?)\s*\(+\s*[A-Z]{3}\)#su"),
                                'DepCode' => TRIP_CODE_UNKNOWN,
                            ];
                        },

                        "DepDate" => function ($text = '', $node = null, $it = null) {
                            return strtotime(en(re("#" . $this->t('Departure date') . "\s+(\d+\s+\w+\s+\d+)#iu") . " " . re("#" . $this->t('Departure time') . "\s+(\d+:\d+)#i"), $this->lang));
                        },

                        "ArrName" => function ($text = '', $node = null, $it = null) {
                            return [
                                'ArrName' => re("#\s*" . $this->t('To') . "\s+(.+?)\s*\(+[A-Z]{3}\)#su"),
                                'ArrCode' => TRIP_CODE_UNKNOWN,
                            ];
                        },

                        "ArrDate" => function ($text = '', $node = null, $it = null) {
                            $dateArr = re("#" . $this->t('Arrival date') . "\s+(\d+\s+\w+\s+\d+)#iu");
                            $timeArr = re("#" . $this->t('Arrival time') . "\s+(\d+:\d+)#iu");

                            if (($dateArr == null) && ($timeArr == null)) {
                                return MISSING_DATE;
                            }

                            return strtotime(en($dateArr . " " . $timeArr));
                        },

                        "Cabin" => function ($text = '', $node = null, $it = null) {
                            $result = [];

                            $text = preg_replace('/(' . $this->t('Travel class') . ')\s*\1/', '$1', $text);

                            $result['Cabin'] = re("#" . $this->t('Travel class') . "\s+([^\n]+?)\s*(?>\((\w{1,2})\))?\n#i", $text);
                            $result['BookingClass'] = re(2);

                            return $result;
                        },
                    ],
                ],
            ],
        ];
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function assignLang($text)
    {
        foreach (self::$dict as $lang => $phrases) {
            if (stripos($text, $phrases['Departure date']) !== false || stripos($text, $phrases['Arrival time']) !== false) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
    }
}
