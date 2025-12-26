<?php

namespace AwardWallet\Engine\vueling\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class PDF extends \TAccountChecker
{
    public $mailFiles = "vueling/it-4233518.eml, vueling/it-4308939.eml, vueling/it-5035833.eml, vueling/it-5281324.eml, vueling/it-7382613.eml, vueling/it-8542597.eml, vueling/it-94412351.eml";

    public $reBody = [
        'es' => 'TU PLAN DE VUELO',
        'fr' => 'VOTRE PLAN DE VOL',
        'nl' => 'JE VLUCHTSCHEMA',
        'ru' => 'ВАШ ПЛАН ПОЛЕТА',
        'en' => 'YOUR FLIGHT PLAN',
        'pt' => 'O TEU PLANO DE VOO',
        'de' => 'IHR FLUGPLAN',
        'it' => ['TARJETA DE EMBARQUE', 'CODICE PRENOTAZIONE'],
        'ca' => 'EL TEU PLA DE VOL',
    ];

    public $flightArray = [];

    public static $dict = [
        'es' => [ // it-4233518.eml
            'Reservation' => 'RESERVA',
            'Flight'      => 'VUELO',
            'Departure'   => 'SALIDA',
            // 'TERMINAL' => '',
            'Seats' => 'ASIENTO',
        ],
        'fr' => [ // it-4308939.eml
            'Reservation' => 'CODE RÉSERVATION',
            'Flight'      => 'VOL',
            'Departure'   => 'DÉPART',
            // 'TERMINAL' => '',
            'Seats' => 'PLACE',
        ],
        'nl' => [ // it-5035833.eml
            'Reservation' => 'RESERVERINGSNUMMER',
            'Flight'      => 'VLUCHTNR.',
            'Departure'   => 'VERTREK',
            // 'TERMINAL' => '',
            'Seats' => 'STOEL',
        ],
        'ru' => [ // it-94412351.eml
            'Reservation' => 'Код бронирования',
            'Flight'      => '№ РЕЙСА',
            'Departure'   => 'ВЫЛЕТ',
            'TERMINAL'    => 'ТЕРМИНАЛ',
            'Seats'       => 'МЕСТО',
        ],
        'en' => [ // it-5281324.eml
            'Reservation' => 'RESERVATION',
            'Flight'      => ['FLIGHT', 'FLIGHT NO.'],
            'Departure'   => 'DEPARTURE',
            'Seats'       => 'SEAT',
        ],
        'pt' => [ // it-7382613.eml
            'Reservation' => 'CÓDIGO RESERVA',
            'Flight'      => 'Nº VOO',
            'Departure'   => 'SAÍDA',
            // 'TERMINAL' => '',
            'Seats' => 'ASSENTO',
        ],
        'de' => [ // it-8542597.eml
            'Reservation' => 'BUCHUNGSCODE',
            'Flight'      => 'FLUGNUMMER',
            'Departure'   => 'ABFLUG',
            // 'TERMINAL' => '',
            'Seats' => 'SITZPLATZ',
        ],
        'it' => [
            'Reservation' => 'CODICE PRENOTAZIONE',
            'Flight'      => 'Nº VOLO',
            'Departure'   => 'PARTENZA',
            // 'TERMINAL' => '',
            'Seats' => 'POSTO',
        ],
        'ca' => [
            'Reservation' => 'CODI DE RESERVA',
            'Flight'      => 'NÚM. VOL',
            'Departure'   => 'SORTIDA',
            // 'TERMINAL' => '',
            'Seats' => 'SEIENT',
        ],
    ];

    /** @var \HttpBrowser */
    public $pdf;
    public $pdfName;

    public $lang = '';

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $pdfs = $parser->searchAttachmentByName('.*pdf');

        if (isset($pdfs) && count($pdfs) > 0) {
            foreach ($pdfs as $pdf) {
                if (($html = \PDF::convertToHtml($parser->getAttachmentBody($pdf), \PDF::MODE_COMPLEX)) !== null) {
                    $this->pdf = clone $this->http;
                    $this->pdfName = $this->getAttachmentName($parser, $pdf);
                    $this->pdf->SetEmailBody(str_replace(['&nbsp;', ' ', '&#160;', '  '], ' ', $html));
                } else {
                    return null;
                }
            }
        } else {
            return null;
        }
        $body = $this->pdf->Response['body'];

        if (isset($this->reBody)) {
            foreach ($this->reBody as $lang => $reBody) {
                if (is_string($reBody) && stripos($body, $reBody) !== false) {
                    $this->lang = $lang;

                    break;
                } elseif (is_array($reBody)) {
                    foreach ($reBody as $re) {
                        if (stripos($body, $re) !== false) {
                            $this->lang = $lang;

                            break 2;
                        }
                    }
                }
            }
        }
        $this->parseEmail($email);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $pdf = $parser->searchAttachmentByName('.*pdf');

        if (isset($pdf[0])) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf[0]));

            foreach ($this->reBody as $reBody) {
                if (is_string($reBody) && stripos($text, $reBody) !== false) {
                    return true;
                } elseif (is_array($reBody)) {
                    foreach ($reBody as $re) {
                        if (stripos($text, $re) !== false) {
                            return true;
                        }
                    }
                }
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers['from']) && stripos($headers['from'], "@vueling.com") !== false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, "@vueling.com") !== false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    private function parseEmail(Email $email)
    {
        $f = $email->add()->flight();
        $xpath = "//div[contains(@id, 'page')]";
        $roots = $this->pdf->XPath->query($xpath);

        if ($roots->length === 0) {
            $this->logger->info('Segments not found: ' . $xpath);

            return null;
        }

        $confs = [];
        $travellers = [];

        foreach ($roots as $root) {
            $traveller = $this->pdf->FindSingleNode('./p[3]', $root);
            $travellers[] = $traveller;
            $confs[] = $this->pdf->FindSingleNode("./p[contains(text(), '" . $this->t('Reservation') . "')]/following-sibling::p[1]", $root);

            $flight = $this->pdf->FindSingleNode("(./p[" . $this->contains($this->t('Flight')) . "]/following-sibling::p[1])[1]", $root, true, '/^(?:[A-Z\d][A-Z]|[A-Z][A-Z\d])\s*\d+$/');

            if (empty($flight)) {
                $flight = $this->pdf->FindSingleNode('p[not(contains(normalize-space(.), "BOARDING PASS"))][last()]', $root, true, '/^(?:[A-Z\d][A-Z]|[A-Z][A-Z\d])\s*\d+$/');
            }

            if (empty($flight)) {
                $flight = $this->pdf->FindSingleNode("p[contains(normalize-space(.), 'Boarding')]/following-sibling::p[4]", $root, true, '/^(?:[A-Z\d][A-Z]|[A-Z][A-Z\d])\s*\d+$/');
            }

            if (empty($flight)) {
                $flight = $this->pdf->FindSingleNode("p[contains(normalize-space(.), 'Boarding')]/following-sibling::p[4]", $root, true, '/^(?:[A-Z\d][A-Z]|[A-Z][A-Z\d])\s*\d+\/((?:[A-Z\d][A-Z]|[A-Z][A-Z\d])\s*\d+)$/');
            }

            if (empty($flight)) {
                $flight = $this->pdf->FindSingleNode("(./p[{$this->eq($this->t('Seats'))}]/following-sibling::p[2])[2]", $root, true, '/^(?:[A-Z\d][A-Z]|[A-Z][A-Z\d])\s*\d+$/');
            }

            $aName = '';
            $fNumber = '';

            if (preg_match('#(\D{2})(\d+)#', $flight, $m)) {
                $aName = $m[1];
                $fNumber = $m[2];
            }

            $depCode = $this->pdf->FindSingleNode('p[1]', $root, true, '/\b(\D{3})\b/');

            if (in_array($aName . $fNumber . $depCode, $this->flightArray) === false) {
                $s = $f->addSegment();
            }

            $s->airline()
                ->name($aName)
                ->number($fNumber);

            $s->departure()
                ->date($this->normalizeDate(implode(' ', $this->pdf->FindNodes("p[{$this->eq($this->t('Departure'))}]/following-sibling::p[position() = 2 or position() = 4]", $root))))
                ->code($depCode);

            $s->arrival()
                ->date($this->normalizeDate(implode(' ', $this->pdf->FindNodes("p[{$this->eq($this->t('Departure'))}]/following-sibling::p[position() = 3 or position() = 5]", $root))))
                ->code($this->pdf->FindSingleNode('p[2]', $root, true, '/\b(\D{3})\b/'));

            $depTerminal = $this->pdf->FindSingleNode("./p[contains(text(), '" . $this->t('TERMINAL') . "')]/following-sibling::p[2]", $root, true, '/([A-Z\d]{1,5})/');

            if (!empty($depTerminal)) {
                $s->departure()
                    ->terminal($depTerminal);
            }

            $seat = $this->pdf->FindSingleNode("p[contains(text(), '" . $this->t('Seats') . "')][2]/following-sibling::p[1]", $root, true, "/^(\d+[A-Z])/");

            if (empty($seat)) {
                $seat = $this->pdf->FindSingleNode("p[contains(text(), '" . $this->t('RESERVATION') . "')][1]/following::p[normalize-space()='" . $this->t('SEAT') . "'][1]/following::p[1]", $root, true, "/^(\d+[A-Z])/");
            }

            if (count($this->flightArray) === 0) {
                $s->extra()
                    ->seat($seat, false, false, $traveller);
            } else {
                $segs = $f->getSegments();

                foreach ($segs as $seg) {
                    if ($seg->getAirlineName() === $aName && $seg->getFlightNumber() === $fNumber && $s->getDepCode() === $depCode) {
                        if (in_array($seat, $s->getSeats()) === false) {
                            $seg->extra()
                                ->seat($seat, false, false, $traveller);
                        }
                    }
                }
            }

            $this->flightArray[] = $s->getAirlineName() . $s->getFlightNumber() . $s->getDepCode();
        }

        $f->general()
            ->travellers(array_unique($travellers));

        foreach (array_unique($confs) as $conf) {
            $f->general()
                ->confirmation($conf);
        }

        $segs = $f->getSegments();

        foreach ($segs as $seg) {
            foreach ($travellers as $traveller) {
                $bp = $email->add()->bpass();

                $bp->setDepCode($seg->getDepCode())
                    ->setTraveller($traveller)
                    ->setFlightNumber($seg->getAirlineName() . $seg->getFlightNumber())
                    ->setDepDate($seg->getDepDate())
                    ->setAttachmentName($this->pdfName);

                if (count($confs) === 1) {
                    $bp->setRecordLocator($confs[0]);
                }
            }
        }
    }

    private function contains($field, string $text = 'normalize-space(text())'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) use ($text) { return "contains($text, \"{$s}\")"; }, $field));
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(' or ', array_map(function ($s) {
            return 'normalize-space(.)="' . $s . '"';
        }, $field));
    }

    private function normalizeDate($date)
    {
        $in = [
            '#^(\d+)[\/\.](\d+)[\/\.](\d+)\s+(\d+:\d+)\s*\w*$#',
        ];
        $out = [
            "$2/$1/$3 $4",
        ];

        return strtotime(preg_replace($in, $out, $date));
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function getAttachmentName(PlancakeEmailParser $parser, $pdf)
    {
        $header = $parser->getAttachmentHeader($pdf, 'Content-Type');

        if (preg_match('/name=[\"\']*(.+\.pdf)[\'\"]*/i', $header, $m)) {
            return $m[1];
        }

        if (preg_match("/application\/octet-stream/", $header)) {
            return 'application/pdf';
        }

        return null;
    }
}
