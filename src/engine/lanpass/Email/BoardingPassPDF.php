<?php

namespace AwardWallet\Engine\lanpass\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Engine\MonthTranslate;

class BoardingPassPDF extends \TAccountChecker
{
    public $mailFiles = "lanpass/it-10231075.eml, lanpass/it-2212558.eml, lanpass/it-4692770.eml, lanpass/it-8863230.eml, lanpass/it-8936820.eml, lanpass/it-8987806.eml, lanpass/it-9017579.eml";

    /** @var \HttpBrowser */
    public $pdf;
    public $dateRelative;

    public $subjects = [
        'fr' => ["Carte d'embarquement"],
        'es' => ['Tarjeta de Embarque', 'Confirmacion de compra'],
    ];

    public $reBody = [
        'fr' => ['HEURE DE PRÉSENTATION'],
        'pt' => ['HORA DE APRESENTAÇÃO'],
        'es' => ['HORA PRESENTACIÓN'],
        'en' => ['PRESENTATION TIME'],
        'de' => ['DAS IST IHR BORDKARTE'],
    ];

    public $lang = '';

    public static $dict = [
        'fr' => [
            'PASSENGER NAME'     => 'NOM DU PASSAGER',
            'FREQUENT FLYER'     => 'PASSAGER FREQUENT',
            'TICKET NUMBER'      => 'N° DE BILLET',
            'FLIGHT'             => 'VOL',
            'FROM'               => 'À PARTIR DE',
            'TO'                 => 'VERS',
            'ROW'                => 'RANGÉE',
            'SEAT'               => 'SIÈGE',
            'DEPARTURE'          => 'SORTIE',
            'CLASS'              => 'CLASSE',
            'FLIGHT OPERATED BY' => 'VOL OPÉRÉ PAR',
        ],
        'pt' => [
            'PASSENGER NAME'     => 'NOME DE PASSAGEIRO',
            'FREQUENT FLYER'     => 'PASSAGEIRO FREQUENTE',
            'TICKET NUMBER'      => 'N° DA PASSAGEM',
            'FLIGHT'             => 'VOO',
            'FROM'               => 'DESDE',
            'TO'                 => 'COM DESTINO À',
            'ROW'                => 'FILA',
            'SEAT'               => 'FILEIRA',
            'DEPARTURE'          => 'SAÍDA',
            'CLASS'              => 'CLASSE',
            'FLIGHT OPERATED BY' => 'VOO OPERADO POR',
        ],
        'es' => [
            'PASSENGER NAME'     => 'NOMBRE PASAJERO',
            'FREQUENT FLYER'     => 'PASAJERO FRECUENTE',
            'TICKET NUMBER'      => 'N° DE TICKET',
            'FLIGHT'             => 'VUELO',
            'FROM'               => 'DESDE',
            'TO'                 => 'HACIA',
            'ROW'                => 'FILA',
            'SEAT'               => 'ASIENTO',
            'DEPARTURE'          => 'SALIDA',
            'CLASS'              => 'CLASE',
            'FLIGHT OPERATED BY' => 'VUELO OPERADO POR',
        ],
        'de' => [
            'PASSENGER NAME'     => 'PASSAGIERNAME',
            'FREQUENT FLYER'     => 'VIELFLIEGER',
            'TICKET NUMBER'      => 'TICKETNR',
            'FLIGHT'             => 'FLUG',
            'FROM'               => 'VON',
            'TO'                 => 'NACH',
            'ROW'                => 'REIHE',
            'SEAT'               => 'SITZ',
            'DEPARTURE'          => 'ABFLUG',
            'CLASS'              => 'KLASSE',
            'FLIGHT OPERATED BY' => 'FLUG DURCHGEFÜHRT VON',
        ],
        'en' => [],
    ];

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName('.*pdf');

        if (isset($pdfs) && count($pdfs) > 0) {
            $htmlPdf = '';

            foreach ($pdfs as $pdf) {
                if (($htmlPdf .= \PDF::convertToHtml($parser->getAttachmentBody($pdf), \PDF::MODE_COMPLEX)) !== null) {
                } else {
                    return null;
                }
            }
            $this->pdf = clone $this->http;
            $this->pdf->SetEmailBody($htmlPdf);
        } else {
            return false;
        }

        if ($this->assignLang($htmlPdf) === false) {
            return false;
        }

        $this->dateRelative = EmailDateHelper::calculateOriginalDate($this, $parser);

        if (empty($this->dateRelative)) {
            $this->dateRelative = strtotime(preg_replace('/ (?:UTC|UT)/', ' UTC', $parser->getDate()) . ' -1 days');
        } // it-2212558.eml

        if (preg_match("/ (\d{1,2}( de)? [[:alpha:]]+ 20\d{2}) .+ - /", $parser->getSubject(), $m)) {
            $temp = $this->normalizeDate($m[1]);

            if (!empty($temp)) {
                $this->dateRelative = strtotime("-5 days", $temp);
            }
        }
        $its = $this->parsePdf();

        // not mobile bp
        // $bps = [];
        //
        // foreach ($its as $it) {
        //     if ($it['Kind'] !== 'T') {
        //         continue;
        //     }
        //     $bpFlight = $this->parseBp($it);
        //     $bpFlight['AttachmentFileName'] = $this->getAttachmentName($parser, $pdf);
        //     $bps[] = $bpFlight;
        // }

        return [
            'parsedData' => [
                'Itineraries'  => $its,
                // 'BoardingPass' => $bps,
            ],
            'emailType' => 'BoardingPassPDF_' . $this->lang,
        ];
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName('.*pdf');

        foreach ($pdfs as $pdf) {
            $textPdf = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (stripos($textPdf, 'www.lan.com') === false && strpos($textPdf, 'LAN.com') === false && stripos($textPdf, 'latam') === false) {
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
        if (self::detectEmailFromProvider($headers['from']) === false) {
            return false;
        }

        foreach ($this->subjects as $phrases) {
            foreach ($phrases as $phrase) {
                if (stripos($headers['subject'], $phrase) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@lan.com') !== false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    public function dateStringToEnglish($date)
    {
        if (preg_match('#[[:alpha:]]+#iu', $date, $m)) {
            $monthNameOriginal = $m[0];

            if ($translatedMonthName = MonthTranslate::translate($monthNameOriginal, $this->lang)) {
                return preg_replace("#$monthNameOriginal#i", $translatedMonthName, $date);
            }
        }

        return $date;
    }

    private function parsePdf()
    {
        $it = ['Kind' => 'T', 'TripSegments' => []];
        $it['RecordLocator'] = CONFNO_UNKNOWN;

        $passenger = $this->pdf->FindSingleNode('//p[contains(normalize-space(.),"' . $this->t('PASSENGER NAME') . '")]/following::p[1][contains(.,"/")]');

        if ($passenger) {
            $it['Passengers'] = [$passenger];
        }

        // LA 5427417461K RUBY
        $accountNumber = $this->pdf->FindSingleNode('//p[contains(normalize-space(.),"' . $this->t('FREQUENT FLYER') . '")]/following::p[1]',
            null, true, '/^([-A-Z\d\/ ]*\d{5}[\d\/ ]*)[A-Z]?(?: [A-Z]+)?$/');

        if ($accountNumber) {
            $it['AccountNumbers'] = [$accountNumber];
        }
        $ticketNumber = $this->pdf->FindSingleNode('//p[contains(normalize-space(.),"' . $this->t('TICKET NUMBER') . '")]/following::p[1]', null, true, '/^([-\d\/ ]*\d{4}[\d\/ ]*)$/');

        if ($ticketNumber) {
            $it['TicketNumbers'] = [$ticketNumber];
        }
        $node = $this->pdf->FindSingleNode('//p[normalize-space(.)="' . $this->t('FLIGHT') . '"]/following::p[1]');

        if (isset($node) && preg_match("#([A-Z\d]{2})\s*(\d+)#", $node, $m)) {
            $segs['FlightNumber'] = $m[2];
            $segs['AirlineName'] = $m[1];
        }
        $node = $this->pdf->FindSingleNode('//p[normalize-space(.)="' . $this->t('FROM') . '"]/following::p[1]');

        if (isset($node) && preg_match("#(.+)\s+\(([A-Z]{3})\)#", $node, $m)) {
            $segs['DepCode'] = $m[2];
            $segs['DepName'] = $m[1];
        }
        $node = $this->pdf->FindSingleNode('//p[normalize-space(.)="' . $this->t('FROM') . '"]/following::p[3]');

        if (isset($node)) {
            $segs['DepName'] = $node;
        }
        $node = $this->pdf->FindSingleNode('//p[normalize-space(.)="' . $this->t('FROM') . '"]/following::p[5]');

        if (isset($node)) {
            $segs['DepartureTerminal'] = $node;
        }
        $node = $this->pdf->FindSingleNode('//p[normalize-space(.)="' . $this->t('TO') . '"]/following::p[1]');

        if (isset($node) && preg_match("#(.+)\s+\(([A-Z]{3})\)#", $node, $m)) {
            $segs['ArrCode'] = $m[2];
            $segs['ArrName'] = $m[1];
        }
        $node = $this->pdf->FindSingleNode('//p[normalize-space(.)="' . $this->t('TO') . '"]/following::p[3]');

        if (isset($node)) {
            $segs['ArrName'] = $node;
        }
        $node = $this->pdf->FindSingleNode('//p[normalize-space(.)="' . $this->t('TO') . '"]/following::p[5]');

        if (isset($node)) {
            $segs['ArrivalTerminal'] = $node;
        }
        $node = $this->pdf->FindSingleNode('//p[contains(normalize-space(.),"' . $this->t('ROW') . '") and contains(normalize-space(.),"' . $this->t('SEAT') . '")]/following::p[1]', null, false, '/\)\s*(.+)/');

        if (isset($node)) {
            $segs['Seats'] = [preg_replace('/^(\d{1,2})\s*\/\s*([A-Z])$/', '$1$2', $node)];
        } // 10 / K  ->  10K

        $dateDep = $this->pdf->FindSingleNode('//p[contains(normalize-space(.),"' . $this->t('DEPARTURE') . '")]/following::p[2]', null, true, '/\((.+)\)/');
        $dateDep = $this->dateStringToEnglish(trim(str_replace('/', ' ', $dateDep), " ."));
        $timeDep = $this->pdf->FindSingleNode('//p[contains(normalize-space(.),"' . $this->t('DEPARTURE') . '")]/following::p[1]');

        if ($this->dateRelative && $dateDep && $timeDep) {
            $dateDep = EmailDateHelper::parseDateRelative($dateDep, $this->dateRelative);
            $segs['DepDate'] = strtotime($timeDep, $dateDep);
        }

        $segs['ArrDate'] = MISSING_DATE;

        $segs['Cabin'] = $this->pdf->FindSingleNode('//p[contains(normalize-space(.),"' . $this->t('CLASS') . '")]/following::p[1]');

        $operator = $this->pdf->FindSingleNode('//text()[contains(normalize-space(.),"' . $this->t('FLIGHT OPERATED BY') . '")]/following::text()[normalize-space(.)][1][contains(.,"LAN") or contains(.,"LATAM") or contains(.,"AIRLINES")]');

        if ($operator) {
            $segs['Operator'] = $operator;
        }

        $it['TripSegments'][] = $segs;

        return [$it];
    }

    // private function parseBp($it)
    // {
    //     $bp = [];
    //     $bp['FlightNumber'] = $it['TripSegments'][0]['FlightNumber'];
    //     $bp['DepCode'] = $it['TripSegments'][0]['DepCode'];
    //     $bp['DepDate'] = $it['TripSegments'][0]['DepDate'];
    //     $bp['RecordLocator'] = $it['RecordLocator'];
    //     $bp['Passengers'] = $it['Passengers'];
    //
    //     return $bp;
    // }

    // private function getAttachmentName(\PlancakeEmailParser $parser, $pdf)
    // {
    //     $header = $parser->getAttachmentHeader($pdf, 'Content-Type');
    //
    //     if (preg_match('/name=[\"\']*(.+\.pdf)[\'\"]*/i', $header, $matches)) {
    //         return $matches[1];
    //     }
    //
    //     return false;
    // }

    private function t($phrase)
    {
        if (!isset(self::$dict[$this->lang]) || !isset(self::$dict[$this->lang][$phrase])) {
            return $phrase;
        }

        return self::$dict[$this->lang][$phrase];
    }

    private function assignLang($text)
    {
        foreach ($this->reBody as $lang => $phrases) {
            foreach ($phrases as $phrase) {
                if (strpos($text, $phrase) !== false) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function normalizeDate(?string $date): ?int
    {
        // $this->logger->debug('date begin = ' . print_r($date, true));

        $in = [
            // 17 de abril 2019
            '/^\s*(\d{1,2})(?: de)? ([[:alpha:]]+) (\d{4})\s*$/ui',
        ];
        $out = [
            "$1 $2 $3",
        ];

        $date = preg_replace($in, $out, $date);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $date, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $date = str_replace($m[1], $en, $date);
            }
        }

//        $this->logger->debug('date end = ' . print_r( $date, true));
        if (preg_match("/\b\d{4}\b/", $date)) {
            $date = strtotime($date);
        } else {
            $date = null;
        }

        return $date;
    }
}
