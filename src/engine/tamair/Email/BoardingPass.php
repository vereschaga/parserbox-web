<?php

namespace AwardWallet\Engine\tamair\Email;

class BoardingPass extends \TAccountCheckerExtended
{
    public $mailFiles = "tamair/it-4953023.eml, tamair/it-2789566.eml, tamair/it-4953023.eml";

    public $rePlain = [
        ['using TAM online#i', 'blank', '/1'],
    ];
    public $reHtml = "";
    public $rePDF = "";
    public $reSubject = "";
    public $reFrom = [
        ['#[@.]tam[.]#i', 'blank', ''],
    ];
    public $reProvider = "";
    public $fnLanguage = "";
    public $caseReference = "";
    public $upDate = "08.06.2015, 13:35";
    public $crDate = "08.06.2015, 13:28";
    public $xPath = "";
    public $re_catcher = "#.*?#";
    public $pdfRequired = "0";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    $rePdf = ['tam.com.br', 'check-in Tam.', 'check in Tam.'];
                    $rePdf2 = [
                        'en' => 'Boarding Pass',
                        'pt' => 'Cartão de Embarque',
                    ];
                    $textPdf = $this->getDocument('application/pdf', 'text');
                    $check1 = false;

                    foreach ($rePdf as $phrase1) {
                        if (stripos($textPdf, $phrase1) !== false) {
                            $check1 = true;

                            break;
                        }
                    }

                    if (!$check1) {
                        return [$text];
                    }

                    foreach ($rePdf2 as $phrase2) {
                        if (strpos($textPdf, $phrase2) !== false) {
                            return null;
                        }
                    }

                    return [$text];
                },

                "#.*?#" => [
                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "T";
                    },

                    "RecordLocator" => function ($text = '', $node = null, $it = null) {
                        return reni('(?:Booking Reference|Código da reserva)\s*:\s*(\w+)');
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        $name = nice(nodes("//*[contains(text(), 'Passenger') or contains(text(), 'Passageiro')]/following::span[1]"));

                        return array_unique($name);
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        return xpath('//*[contains(text(), "Passenger:") or contains(text(), "Passageiro:")]/ancestor::table[1]');
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            $fl = reni('(?:Flight|Voo)\s*: (\w+\d+)');

                            return uberAir($fl);
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
                        },

                        "DepName" => function ($text = '', $node = null, $it = null) {
                            return nice(node(".//*[normalize-space(text()) = 'From:' or normalize-space(text()) = 'De:']/following::span[1]"));
                        },

                        "DepDate" => function ($text = '', $node = null, $it = null) {
                            $info = node(".//*[normalize-space(text()) = 'From:' or normalize-space(text()) = 'De:']/following::span[2]");
                            $dt = uberDateTime($info);
                            $dt = timestamp_from_format($dt, 'd/m/Y , H:i');

                            return $dt;
                        },

                        "ArrCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
                        },

                        "ArrName" => function ($text = '', $node = null, $it = null) {
                            return nice(node(".//*[normalize-space(text()) = 'To:' or normalize-space(text()) = 'Para:']/following::span[1]"));
                        },

                        "ArrDate" => function ($text = '', $node = null, $it = null) {
                            $info = node(".//*[normalize-space(text()) = 'To:' or normalize-space(text()) = 'Para:']/following::span[2]");
                            $dt = uberDateTime($info);
                            $dt = timestamp_from_format($dt, 'd/m/Y , H:i');

                            return $dt;
                        },
                    ],
                ],
            ],
        ];
    }

    public static function getEmailLanguages()
    {
        return ['en', 'pt'];
    }

    public static function getEmailTypesCount()
    {
        return 2;
    }

    public function IsEmailAggregator()
    {
        return false;
    }
}
