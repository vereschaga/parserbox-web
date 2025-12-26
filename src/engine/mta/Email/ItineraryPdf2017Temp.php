<?php

namespace AwardWallet\Engine\mta\Email;

class ItineraryPdf2017Temp extends \AwardWallet\Engine\tport\Email\ItineraryPdf2017
{
    public $mailFiles = "mta/it-25177743.eml";

    private $code;
    private static $headers = [
        'mta' => [
            'from' => ['@mtatravel.com.au'],
            'subj' => [
                'en' => 'View Your Itinerary: ',
                'it' => 'Visualizza il tuo itinerario: ',
                'pt' => 'Visualizar o seu itinerário: ',
            ],
        ],
    ];
    private static $bodies = [
        'mta' => [
            'en' => ['To see the details of your trip'],
            'it' => ['Per visualizzare i dettagli del'],
            'pt' => ['Para ver as informações da sua'],
        ],
    ];

    private static $dict = [
        'en' => [
            //			'IMPORTANT INFORMATION FOR TRAVELERS' => "",
            //			'Ticket Issue Date:' => "",
            //			'Flight' => "",
            //			'Confirmation Number:' => "",
            //			'Depart:' => "",
            //			'Arrive:' => "",
            //			'Class Of Service:' => "",
        ],
        'it' => [
            'IMPORTANT INFORMATION FOR TRAVELERS' => "INFORMAZIONI IMPORTANTI PER I VIAGGIATORI",
            'Ticket Issue Date:'                  => "Data di emissione del biglietto:",
            'Flight'                              => "Volo",
            'Confirmation Number:'                => "Numero di conferma:",
            'Depart:'                             => "Partenza:",
            'Arrive:'                             => "Arrivo:",
            'Class Of Service:'                   => "Classe di servizio:",
        ],
        'pt' => [
            'IMPORTANT INFORMATION FOR TRAVELERS' => "INFORMAÇÃO IMPORTANTE PARA VIAJANTES",
            'Ticket Issue Date:'                  => "Data de emissão do bilhete:",
            'Flight'                              => "Voo",
            'Confirmation Number:'                => "Número de confirmação:",
            'Depart:'                             => "Partida:",
            'Arrive:'                             => "Chegada:",
            'Class Of Service:'                   => "Classe de serviço:",
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (empty($headers['from']) || empty($headers['subject'])) {
            return false;
        }

        foreach (self::$headers as $code => $arr) {
            $byFrom = $bySubj = false;

            if ($this->arrikey($headers['from'], $arr['from']) !== false) {
                $byFrom = true;
            }

            if ($this->arrikey($headers['subject'], $arr['subj']) !== false) {
                $bySubj = true;
            }

            if ($byFrom && $bySubj) {
                $this->code = $code;

                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if (parent::detectEmailByBody($parser) === true) {
            return true;
        } else {
            foreach (self::$headers as $code => $arrHeaders) {
                foreach ($arrHeaders['from'] as $arr) {
                    if ($this->http->FindSingleNode("(//a[contains(., '" . trim($arr, '@') . "')])[1]") !== false) {
                        $this->code = $code;

                        break;
                    }
                }
            }

            if (empty($this->code)) {
                return false;
            }
            $pdfs = $parser->searchAttachmentByName($this->pdfPattern);

            if (empty($pdfs)) {
                $pdfs = $parser->searchAttachmentByName($this->pdfPatternAddition);
            }

            foreach ($pdfs as $pdf) {
                $textPdf = \PDF::convertToText($parser->getAttachmentBody($pdf));

                if ((null === ($code = $this->getProvByText($textPdf))) && (null === ($code = $this->getProvByText($parser->getHTMLBody())))) {
                    return false;
                }

                foreach (self::$detect as $lang => $detect) {
                    if (is_array($detect)) {
                        foreach ($detect as $d) {
                            if (false !== stripos($textPdf, $d)) {
                                return true;
                            }
                        }
                    } elseif (strpos($textPdf, $detect) !== false) {
                        return true;
                    }
                }
            }

            return false;
        }
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    public static function getEmailProviders()
    {
        return array_unique(array_merge(array_keys(self::$headers), array_keys(self::$bodies)));
    }

    protected function getProvByText($text)
    {
        foreach (self::$bodies as $code=>$body) {
            switch ($code) {
                case 'mta':
                    if (stripos($text, 'mta travel') !== false) {
                        $this->code = $code;

                        return $code;
                    }

                    break;

                default:
                    return null;
            }
        }

        return null;
    }

    protected function getProvider(\PlancakeEmailParser $parser)
    {
        $this->detectEmailByHeaders(['from' => $parser->getCleanFrom(), 'subject' => $parser->getSubject()]);

        foreach (self::$bodies as $code=>$body) {
            switch ($code) {
                case 'mta':
                    if (stripos($parser->getHTMLBody(), 'mta travel') !== false) {
                        return $code;
                    }

                    break;

                default:
                    return null;
            }
        }

        return null;
    }

    private function arrikey($haystack, array $arrayNeedle)
    {
        foreach ($arrayNeedle as $key => $needles) {
            if (is_array($needles)) {
                foreach ($needles as $needle) {
                    if (stripos($haystack, $needle) !== false) {
                        return $key;
                    }
                }
            } elseif (stripos($haystack, $needles) !== false) {
                return $key;
            }
        }

        return false;
    }

    //if turn on for traxo then delete parser and ADD mta in \AwardWallet\Engine\tport\Email\ItineraryPdf2017
}
