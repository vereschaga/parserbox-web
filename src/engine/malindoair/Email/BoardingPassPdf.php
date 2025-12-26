<?php

namespace AwardWallet\Engine\malindoair\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Common\Flight;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class BoardingPassPdf extends \TAccountChecker
{
    public $mailFiles = "malindoair/it-13608023.eml, malindoair/it-13711266.eml, malindoair/it-28967167.eml, malindoair/it-30144426.eml, malindoair/it-30162124.eml, malindoair/it-30301800.eml, malindoair/it-8263467.eml, malindoair/it-8263509.eml";

    public static $detectHeaders = [
        'malindoair' => [
            'froms'   => ['malindo.com'],
            'subject' => ['Your Boarding Pass for Malindo Air'],
        ],
        'aerolineas' => [
            'froms'   => ['aerolineas'],
            'subject' => ['Boarding Pass for'],
        ],
        'alitalia' => [
            'froms'   => ['alitalia'],
            'subject' => ['Alitalia-Boarding Pass from'],
        ],
        'etihad' => [
            'froms'   => ['etihad'],
            'subject' => ['Votre carte d\'embarquement en ligne de', 'Your web boarding pass from'],
        ],
        'cayman' => [
            'froms'   => ['@caymanairways.sabre.com'],
            'subject' => ['KX Reservation:'],
        ],
    ];
    public static $dict = [
        'en' => [
            "BOARDING PASS"   => "BOARDING PASS",
            "Record Locator:" => ["Record Locator:", "Reference Number"],
            "eTicket"         => ["eTicket", "Ticket number", "Ticket Number"],
            //            " to " => "",
            //            "Flight" => "",
            //            "Terminal/Gate" => "",
            //            "Departure Time:" => "",
            //            "Seat" => "",
        ],
        'es' => [
            "BOARDING PASS"   => "Tarjetas de embarque",
            "Record Locator:" => "Código de reserva:",
            "eTicket"         => "N.º billete electrónico",
            " to "            => " a ",
            "Flight"          => "Vuelo",
            "Terminal/Gate"   => "Terminal/Puerta",
            "Departure Time"  => "Hora de salida",
            "Seat"            => "Asiento",
        ],
        'pt' => [
            "BOARDING PASS"   => "Cartão de embarque",
            "Record Locator:" => "Código de reserva:",
            "eTicket"         => "e-Ticket",
            " to "            => " para ",
            "Flight"          => "Voo",
            "Terminal/Gate"   => "Terminal/Portão",
            "Departure Time"  => "Horário da partida",
            "Seat"            => "Assento",
        ],
        'it' => [
            "BOARDING PASS"   => ["Carta d'imbarco", "Boarding Pass"],
            "Record Locator:" => ["Codice prenotazione:", "Codice riferimento prenotazione:"],
            "eTicket"         => ["Biglietto elettronico", "eTicket"],
            " to "            => [" a ", " to "],
            "Flight"          => "Volo",
            "Terminal/Gate"   => ["Terminal/Gate", "Terminal/Gate"],
            "Departure Time"  => "Orario di partenza",
            "Seat"            => "Posto",
        ],
    ];
    private $detectCompany = [
        'malindoair' => [
            'MALINDO AIR', 'malindoair.com',
        ],
        'aerolineas' => [
            'aerolineas.com',
        ],
        'alitalia' => [
            'alitalia',
        ],
        'etihad' => [
            'etihad',
        ],
        'cayman' => [
            'Cayman', 'caymanairways.',
        ],
    ];
    private $detectBody = [
        'es' => ['Tarjetas de embarque'],
        'pt' => ['Cartão de embarque'],
        'it' => ["Carta d'imbarco", " di partenza"],
        'en' => ['BOARDING PASS'],
    ];
    private $pdfPattern = '.*\.pdf';
    private $providerCode;
    private $lang = 'en';

    public function parseEmail(Email $email, string $text)
    {
        foreach ($email->getItineraries() as $value) {
            if ($value->getType() == 'flight') {
                /** @var Flight $flight */
                $f = $value;

                break;
            }
        }

        if (!isset($f)) {
            $f = $email->add()->flight();
            $f->general()->noConfirmation();
        }
        $regexp = "#((?:(?:^|\n)(?: {0,15}\w+\s*\n\s*)?{$this->preg_implode($this->t('BOARDING PASS'))})?\s*{$this->preg_implode($this->t("Record Locator:"))}.+?\n)#i";
        $segments = $this->splitText($regexp, $text);
        $travellersArr = [];

        foreach ($segments as $segment) {
            $s = $f->addSegment();

            // Airline
            if (preg_match("#" . $this->preg_implode($this->t("Record Locator:")) . "[ ]*([A-Z\d]{5,7})\s+#", $segment, $m)) {
                $s->airline()->confirmation($m[1]);
            }
            unset($date);

            /*
            Flight                          Terminal/Gate              Boarding Time               Posto/Seat
            AZ 718                          1 /B4                      14:55                       9E
            Sun May 20, 2018                                           Departure Time: 15:35
            ---------------
            Flight                         Terminal/Gate             Boarding Time           Seat
                                                         Departure Time: 17:30
            AZ 615                         E /E7                                             25C
            Thu Nov 09, 2017
            */
            if (
                preg_match("#{$this->preg_implode($this->t('Seat'))}(?:/Seat)?\s+([A-Z\d]{2})\s*(\d{1,4})\s+.+?[A-Z\d]{1,3}\n\s*(.+?)\s{2,}{$this->preg_implode($this->t('Departure Time'))}#",
                    $segment, $m)
                || preg_match("#{$this->preg_implode($this->t('Seat'))}(?:/Seat)?.+?([A-Z\d]{2})\s*(\d{1,4})\s+.+?[A-Z\d]{1,3}\n\s*(.+?)\s{5,}#s",
                    $segment, $m)
            ) {
                $s->airline()
                    ->name($m[1])
                    ->number($m[2]);
                $date = $m[3];
            }

            if (preg_match("#\n.{50,}[ ]+(?<dCode>[A-Z]{3})[ ]{1,20}(?<aCode>[A-Z]{3})\s*\n#", $segment, $m)) {
                $s->departure()
                    ->code($m["dCode"]);
                $s->arrival()
                    ->code($m["aCode"]);
            }

            if (preg_match("#\n.{50,}[ ]+[A-Z]{3}[ ]{1,20}[A-Z]{3}\s*\n(?:.*\n)*?.{40}[ ]{2,}(?<dName>.+)" . $this->preg_implode($this->t(" to ")) . "(?<aName>.+)#", $segment, $m)) {
                $s->departure()
                    ->name($m["dName"]);
                $s->arrival()
                    ->name($m["aName"]);
            }

            if (!empty($date) && preg_match("#\s+" . $this->preg_implode($this->t("Departure Time")) . "[: ]*(\d+:\d+)#", $segment, $m)) {
                $s->departure()
                    ->date($this->normalizeDate($date . ' ' . $m[1]));
                $s->arrival()
                    ->noDate();
            }

            if (preg_match("#\s+" . $this->preg_implode($this->t("Terminal/Gate")) . "(?:\s{2,}.*|\s*)\n(?:[^\/]*\n)?.*[ ]{2,}(\w+)[ ]?\/.*?(?:[ ]{2,}|\s*\n)#", $segment, $m)) {
                $s->departure()
                    ->terminal($m[1]);
            }

            if (preg_match("#(?:^|\n)[ ]{0,15}(\w.+?)(?:[ ]{2,}.*\n|\n)[ ]{0,15}" . $this->preg_implode($this->t("BOARDING PASS")) . "#i", $segment, $m)// order is matter
            || preg_match("#(?:^|\n)[ ]{0,15}(\w.+?)(?:[ ]{2,}.*\n|\n){2}[ ]{0,15}" . $this->preg_implode($this->t("BOARDING PASS")) . "#i", $segment, $m)) {
                $s->extra()->cabin($m[1]);
            }

            if (preg_match("#" . $this->preg_implode($this->t("Seat")) . "(?:/[ ]?\w+)?\s*\n(?:.*\n)?.+[ ]{2,}(\d{1,3}[A-Z])\s*\n#u", $segment, $m)) {
                $s->extra()->seat($m[1]);
            }

            foreach ($f->getSegments() as $key => $seg) {
                if ($seg === $s) {
                    continue;
                }

                if ($s->getAirlineName() == $seg->getAirlineName()
                        && $s->getFlightNumber() == $seg->getFlightNumber()
                        && $s->getDepCode() == $seg->getDepCode()
                        && $s->getDepDate() == $seg->getDepDate()) {
                    if (!empty($s->getSeats())) {
                        $seg->extra()->seats(array_unique(array_merge($seg->getSeats(), $s->getSeats())));
                    }
                    $f->removeSegment($s);

                    break;
                }
            }

            if (preg_match("#(?:^|\n)\s*(?i:" . $this->preg_implode($this->t("BOARDING PASS")) . ").*\n(?:(?:[ ]{15,}.*|\s*)\n)*[ ]{0,15}([A-Z][A-Za-z\. \/]+?)(?:[ ]{2,}.*)?\n#", $segment, $m)) {
                if (!in_array($m[1], array_column($f->getTravellers(), 0))) {
                    $travellersArr[] = trim($m[1]);
                }
            } elseif (preg_match('/ADULT:\s*([A-Z\s]{5,30})/', $segment, $m)) {
                $travellersArr[] = trim($m[1]);
            }

            if (preg_match("#" . $this->preg_implode($this->t("eTicket")) . "[: ]*(\d{7,})#", $segment, $m)) {
                if (!in_array($m[1], array_column($f->getTicketNumbers(), 0))) {
                    $f->issued()->ticket($m[1], false);
                }
            }
        }

        $travellersArr = array_unique($travellersArr);

        for ($i = 0; $i < count($travellersArr); $i++) {
            foreach ($travellersArr as $traveller2) {
                if (stripos($traveller2, $travellersArr[$i]) !== false
                    && mb_strlen($travellersArr[$i]) < mb_strlen($traveller2)) {
                    unset($travellersArr[$i]);

                    break;
                }
            }
        }

        if (!empty($travellersArr)) {
            $f->general()->travellers($travellersArr);
        }

        return $email;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfPattern);

        if (empty($pdfs)) {
            $this->http->Log('Pdf is not found');

            return false;
        }

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

            foreach ($this->detectBody as $lang => $detectBody) {
                foreach ($detectBody as $dBody) {
                    if (stripos($text, $dBody) !== false) {
                        $this->lang = $lang;

                        break 2;
                    }
                }
            }

            $this->parseEmail($email, $text);

            if (empty($this->providerCode)) {
                $this->providerCode = $this->detectProvider(implode(" ", $parser->getFrom()), $text);
            }
        }
        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        if (!empty($this->providerCode)) {
            $email->setProviderCode($this->providerCode);
        }

        return $email;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (empty($headers['from']) || empty($headers['subject'])) {
            return false;
        }

        foreach (self::$detectHeaders as $providerCode => $detectHeaders) {
            if (empty($detectHeaders['froms']) || empty($detectHeaders['subject'])) {
                continue;
            }
            $foundFrom = false;

            foreach ($detectHeaders['froms'] as $pFrom) {
                if (stripos($headers['from'], $pFrom) !== false) {
                    $foundFrom = true;

                    if (empty($this->providerCode)) {
                        $this->providerCode = $providerCode;
                    }

                    break;
                }
            }

            if ($foundFrom == false) {
                continue;
            }

            foreach ($detectHeaders['subject'] as $pSubject) {
                if (stripos($headers['subject'], $pSubject) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfPattern);

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (empty($text)) {
                continue;
            }
            $foundCompany = false;

            foreach ($this->detectCompany as $providerCode => $detectCompany) {
                $foundCompany = false;

                foreach ($detectCompany as $dCompany) {
                    if (stripos($text, $dCompany) !== false) {
                        $foundCompany = true;
                        $this->providerCode = $providerCode;

                        break 2;
                    }
                }
            }

            if ($foundCompany === false) {
                // TODO: Skip if you cannot find the prov
                if (stripos($text, 'Departure Time') === false) {
                    continue;
                }
            }

            foreach ($this->detectBody as $detectBody) {
                foreach ($detectBody as $dBody) {
                    if (stripos($text, $dBody) !== false) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        foreach (self::$detectHeaders as $provider) {
            if (!empty($provider['froms'])) {
                foreach ($provider['froms'] as $pFrom) {
                    if (stripos($from, $pFrom) !== false) {
                        return true;
                    }
                }
            }
        }

        return false;
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
        return array_keys(self::$detectHeaders);
    }

    public function splitText($pattern, $text)
    {
        if (empty($text)) {
            return $text;
        }

        $r = preg_split($pattern, $text, -1, PREG_SPLIT_DELIM_CAPTURE);
        $ret = [];

        if (count($r) > 1) {
            array_shift($r);

            for ($i = 0; $i < count($r) - 1; $i += 2) {
                $ret[] = $r[$i] . $r[$i + 1];
            }
        } elseif (count($r) == 1) {
            $ret[] = reset($r);
        }

        return $ret;
    }

    private function detectProvider($from, $text)
    {
        foreach (self::$detectHeaders as $providerCode => $detectHeaders) {
            if (empty($detectHeaders['froms'])) {
                continue;
            }

            foreach ($detectHeaders['froms'] as $pFrom) {
                if (stripos($from, $pFrom) !== false) {
                    return $providerCode;
                }
            }
        }

        foreach ($this->detectCompany as $providerCode => $detectCompany) {
            foreach ($detectCompany as $dCompany) {
                if (stripos($text, $dCompany) !== false) {
                    return $providerCode;
                }
            }
        }

        return null;
    }

    private function t($word)
    {
        if (!isset(self::$dict[$this->lang]) || !isset(self::$dict[$this->lang][$word])) {
            return $word;
        }

        return self::$dict[$this->lang][$word];
    }

    private function inOneRow($text)
    {
        $textRows = array_filter(explode("\n", $text));
        $pos = [];
        $length = [];

        foreach ($textRows as $key => $row) {
            $length[] = mb_strlen($row);
        }
        $length = max($length);
        $oneRow = '';

        for ($l = 0; $l < $length; $l++) {
            $notspace = false;

            foreach ($textRows as $key => $row) {
                $sym = mb_substr($row, $l, 1);

                if ($sym !== false && trim($sym) !== '') {
                    $notspace = true;
                    $oneRow[$l] = 'a';
                }
            }

            if ($notspace == false) {
                $oneRow[$l] = ' ';
            }
        }

        return $oneRow;
    }

    private function TableHeadPos($row)
    {
        $head = array_filter(array_map('trim', explode("%", preg_replace("#\s{2,}#", "%", $row))));
        $pos = [];
        $lastpos = 0;

        foreach ($head as $word) {
            $pos[] = mb_strpos($row, $word, $lastpos, 'UTF-8');
            $lastpos = mb_strpos($row, $word, $lastpos, 'UTF-8') + mb_strlen($word, 'UTF-8');
        }

        return $pos;
    }

    private function SplitCols($text, $pos = false)
    {
        $cols = [];
        $rows = explode("\n", $text);

        if (!$pos) {
            $pos = $this->TableHeadPos($rows[0]);
        }
        arsort($pos);

        foreach ($rows as $row) {
            foreach ($pos as $k=>$p) {
                $cols[$k][] = trim(mb_substr($row, $p, null, 'UTF-8'));
                $row = mb_substr($row, 0, $p, 'UTF-8');
            }
        }
        ksort($cols);

        foreach ($cols as &$col) {
            $col = implode("\n", $col);
        }

        return $cols;
    }

    private function preg_implode($field)
    {
        if (!is_array($field)) {
            $field = [$field];
        }

        return '(?:' . implode("|", array_map(function ($v) {return preg_quote($v, '#'); }, $field)) . ')';
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function normalizeDate($str)
    {
//        $this->http->log('$str = '.print_r( $str,true));
        $in = [
            "#^\s*([^\d\s\.\,]+)\s*(\d{1,2})\w{1,2}\s+(\d{4})\s+(\d{1,2}:\d{2})\s*$#u", // December 2nd 2018 17:15
            "#^\s*[^\d\s\.\,]+\s*(\d{1,2})\s*([^\d\s\.\,]+)[,\s]*(\d{4})\s+(\d{1,2}:\d{2})\s*$#u", // vie 09 nov, 2018 15:25
            "#^\s*[^\d\s\.\,]+\s*([^\d\s\.\,]+)\s*(\d{1,2})[,\s]*(\d{4})\s+(\d{1,2}:\d{2})\s*$#u", // lun mag 21, 2018 19:15
        ];
        $out = [
            "$2 $1 $3, $4",
            "$1 $2 $3, $4",
            "$2 $1 $3, $4",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return strtotime($str);
    }
}
