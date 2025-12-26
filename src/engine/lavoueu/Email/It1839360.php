<?php

namespace AwardWallet\Engine\lavoueu\Email;

class It1839360 extends \TAccountCheckerExtended
{
    public $rePlain = "#\n[>\s*]*De\s*:[^\n]*?Lávoueu\s*Viagens|Lávoueu\s*Viagens\s*Usuário:#i";
    public $rePlainRange = "2000";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $rePDF = "";
    public $rePDFRange = "";
    public $reSubject = "";
    public $langSupported = "pt";
    public $typesCount = "1";
    public $reFrom = "#travel@gomytravel[.]com[.]br#i";
    public $reProvider = "#travel@gomytravel[.]com[.]br#i";
    public $caseReference = "";
    public $xPath = "//img[contains(@src, 'logomarcas/agencia992.jpg')]";
    public $mailFiles = "lavoueu/it-1838735.eml, lavoueu/it-1839025.eml, lavoueu/it-1839027.eml, lavoueu/it-1839028.eml, lavoueu/it-1839030.eml, lavoueu/it-1839031.eml, lavoueu/it-1839105.eml, lavoueu/it-1839106.eml, lavoueu/it-1839107.eml, lavoueu/it-1839112.eml, lavoueu/it-1839360.eml, lavoueu/it-1839702.eml, lavoueu/it-1839707.eml, lavoueu/it-1839708.eml, lavoueu/it-1839719.eml, lavoueu/it-1839720.eml, lavoueu/it-1839775.eml, lavoueu/it-1913975.eml, lavoueu/it-1929363.eml, lavoueu/it-1996938.eml, lavoueu/it-1996943.eml, lavoueu/it-2037835.eml";
    public $pdfRequired = "0";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    return [$text];
                },

                "#.*?#" => [
                    "RecordLocator" => function ($text = '', $node = null, $it = null) {
                        return re("#Localizador\s*([\w-]+)#i");
                    },

                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "T";
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        return [re("#Passageiro\s*(.+)$#im")];
                    },

                    "TotalCharge" => function ($text = '', $node = null, $it = null) {
                        $xpath = "//span[contains(text(), 'Bilhete') and ./parent::p/parent::td]/ancestor::td[1]/following-sibling::td[5]";
                        $totals = nodes($xpath);

                        if (sizeof($totals) === 1) { // otherwise too many
                            return total($totals[0]);
                        }
                    },

                    "Status" => function ($text = '', $node = null, $it = null) {
                        if (re('/Confirmação\s*de\s*Emissão/i')) {
                            return 'confirmed';
                        }

                        $subj = $this->parser->getHeader('subject');

                        if (re('/Confirmação\s*de\s*Emissão/i', $subj)) {
                            return 'confirmed';
                        }
                    },

                    "ReservationDate" => function ($text = '', $node = null, $it = null) {
                        $date = re("#Data\s*Emissão\s*(\d+/\d+/\d+)#");
                        $date = \DateTime::createFromFormat('d/m/Y', $date);

                        if (!$date) {
                            return;
                        }

                        $date->setTime(0, 0);

                        return $date->getTimestamp();
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        // yes weird, but seems to work.
                        return xpath("
							//*[contains(@src, 'logomarcas') or @alt = 'Imagem removida pelo remetente.' and ./parent::span]
							/ancestor-or-self::tr[1][count(./td) = 7]
						");
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            $fl = node('td[4]');

                            return uberAir($fl);
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            $info = node('td[1]');

                            $code = re('/[(]([a-z]+)[)]/i', $info);
                            $date = re('/(\d+\/\d+\/\d+)/', $info);
                            $time = re('/[(](\d+:\d+)[)]/', $info);

                            $dt = "$date, $time";
                            $dt = \DateTime::createFromFormat('d/m/Y, H:i', $dt);

                            return [
                                'DepCode' => $code,
                                'DepDate' => $dt ? $dt->getTimestamp() : '',
                            ];
                        },

                        "ArrCode" => function ($text = '', $node = null, $it = null) {
                            $info = node('td[2]');

                            $code = re('/[(]([a-z]+)[)]/i', $info);
                            $date = re('/(\d+\/\d+\/\d+)/', $info);
                            $time = re('/[(](\d+:\d+)[)]/', $info);

                            $dt = "$date, $time";
                            $dt = \DateTime::createFromFormat('d/m/Y, H:i', $dt);

                            return [
                                'ArrCode' => $code,
                                'ArrDate' => $dt ? $dt->getTimestamp() : '',
                            ];
                        },

                        "BookingClass" => function ($text = '', $node = null, $it = null) {
                            return node('td[5]');
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
        return ["pt"];
    }
}
