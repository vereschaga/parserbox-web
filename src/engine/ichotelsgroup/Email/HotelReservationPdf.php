<?php

namespace AwardWallet\Engine\ichotelsgroup\Email;

// use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class HotelReservationPdf extends \TAccountChecker
{
    public $mailFiles = "ichotelsgroup/it-110707037.eml";
    public $reFrom = "reservaciones@cpqueretaro.com.mx";
    public $reSubject = [
        "es"=> "Reservaciones de hotel",
    ];
    public $reBody = ['Ampacet Services', 'www.holidayinn.com', 'Costado Norte del Parque La Sabana', 'InterContinental', 'www.intercontinental.com'];
    public $reBody2 = [
        "es"  => "Llegada",
        "es2" => "Fecha de llegada",
        "en"  => "Departure",
    ];
    public static $dictionary = [
        "es" => [
            "Confirmación de Reserva No." => ["Confirmación de Reserva No.", "Confirmación:"],
            "Llegada"                     => ["Llegada", "Fecha de llegada"],
            "Salida"                      => ["Salida", "Fecha de salida"],
            "Huésped"                     => ["Huésped", "Para"],
            //            "Habitación" => "",
            //            "para" => "",
            //            "Tarifa Diaria" => "",
            //            "Forma de Pago" => "",
            //            "No Shows y Cancelaciones" => "",
        ],
        "en" => [
            "Confirmación de Reserva No." => "Reservation Confirmation No.",
            "Llegada"                     => "Arrival",
            "Salida"                      => "Departure",
            "Huésped"                     => "Guest",
            "Habitación"                  => "Room Info",
            "para"                        => "for",
            "Tarifa Diaria"               => "Rate Info",
            "Forma de Pago"               => ["Payment Info", "Add Taxes"],
            "No Shows y Cancelaciones"    => "No Shows & Cancellations",
        ],
    ];

    public $lang = "es";

    public $pdfPattern = "\w+(\s+\w+)+.pdf";
    /** @var \PlancakeEmailParser */
    private $parser;

    public function parsePdf(Email $email): void
    {
        $text = $this->text;

        $patterns = [
            'time'          => '\d{1,2}(?:[:：]\d{2})?(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?)?',
            'phone'         => '[+(\d][-. \d)(]{5,}[\d)]', // +377 (93) 15 48 52    |    713.680.2992
            'travellerName' => '[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]]', // Mr. Hao-Li Huang
        ];

        $h = $email->add()->hotel();

        if (preg_match("/(?:^[ ]*|[ ]{2})({$this->opt($this->t("Confirmación de Reserva No."))})\s+(\d+)$/m", $text, $m)) {
            $h->general()->confirmation($m[2], rtrim($m[1], ': '));
        }

        $hotelRow = $this->re("/\n((?:\n[ ]*.{2,}){1,3})\s*$/", $text);

        $hotelName = $address = null;

        if (preg_match("/Urbanizacion y Boulevard Santa Elena, Esquina Calle El Pital Antiguo Cuscatlan /i", $text, $m)) {
            $hotelName = "Holiday Inn San Salvador";
            $address = "Urbanizacion y Boulevard Santa Elena, Esquina Calle El Pital Antiguo Cuscatlan - San Salvador";
        } elseif (preg_match("/Costado Norte del Parque La Sabana - San Jose/i", $text, $m)) {
            $hotelName = "Crowne Plaza San Jose Corobici";
            $address = "Costado Norte del Parque La Sabana - San Jose";
        } elseif (preg_match("/InterContinental São Paulo/i", $text, $m) && preg_match("/Alameda Santos, 1123/i", $text, $m)) {
            $hotelName = "Crowne Plaza San Jose Corobici";
            $address = "Alameda Santos, 1123 - Sao Paulo/SP 01419001";
        } elseif (preg_match("/Campos Eliseos 218/i", $text, $m) && preg_match("/Colonia Polanco – México DF 11560/i", $text, $m)) {
            $hotelName = "InterContinental Presidente Mexico City";
            $address = "Campos Eliseos 218, Colonia Polanco – México DF 11560";
        }
        $h->hotel()
            ->name($hotelName ?? "Crown Plaza")
            ->address($address ?? $this->re("/(.*?\s+-\s+.*?)\s+-/", $hotelRow))
            ->phone($this->re("/Tel\s+({$patterns['phone']})/", $hotelRow))
            ->fax($this->re("/Fax\s+({$patterns['phone']})/", $hotelRow))
        ;

        $dateCheckIn = $this->normalizeDate($this->re("/{$this->opt($this->t("Llegada"))}\s*:\s*(.{6,})/", $text));
        $timeCheckIn = $this->re("/(?:Check in|Check In)[.:\s]+({$patterns['time']})/", $text);

        $dateCheckOut = $this->normalizeDate($this->re("/{$this->opt($this->t("Salida"))}\s*:\s*(.{6,})/", $text));
        $timeCheckOut = $this->re("/(?:Check out|Check Out)[.:\s]+({$patterns['time']})/", $text);

        $h->booked()
            ->checkIn2($dateCheckIn . ', ' . $timeCheckIn)
            ->checkOut2($dateCheckOut . ', ' . $timeCheckOut)
        ;

        $guests = $rooms = $roomTypes = [];

        $pdfs = $this->parser->searchAttachmentByName($this->pdfPattern);

        foreach ($pdfs as $pdf) {
            if ($doctext = \PDF::convertToText($this->parser->getAttachmentBody($pdf))) {
                $h->general()->traveller($this->re("/{$this->opt($this->t("Huésped"))}\s*:\s*({$patterns['travellerName']})$/mu", $doctext));

                $g = $this->re("/{$this->opt($this->t("Habitación"))}\s*:\s*.*?\s+{$this->opt($this->t("para"))}\s+(\d+)\s+/", $doctext);

                if ($g !== null) {
                    $guests[] = $g;
                }

                $r = $this->re("/{$this->opt($this->t("Habitación"))}\s*:\s*(\d+)/", $doctext);

                if ($r !== null) {
                    $rooms[] = $r;
                }

                $rT = $this->re("/{$this->opt($this->t("Habitación"))}\s*:\s*\d+\s+(.*?)\s+{$this->opt($this->t("para"))}/", $doctext);

                if ($rT) {
                    $roomTypes[] = $rT;
                }
            }
        }

        if (count($guests)) {
            $h->booked()->guests(array_sum($guests));
        }

        if (count($rooms)) {
            $h->booked()->rooms(array_sum($rooms));
        }

        $roomType = $rate = null;

        if (count($roomTypes)) {
            $roomType = implode('; ', $roomTypes);
        }

        $rateValue = trim($this->re("/{$this->opt($this->t("Tarifa Diaria"))}\s*:\s*(.*?)(?:\n\n|{$this->opt($this->t("Forma de Pago"))})/s", $text));

        if ($rateValue) {
            $rate = preg_match_all("/\d+-\d+-\d+\s+\d[,.\'\d ]*\s+[A-Z]{3}\b/", $rateValue, $rateMatches) ? implode('; ', $rateMatches[0])
                : (preg_match("/^\D*\d[,.\'\d ]*\s+[A-Z]{3}\b/", $rateValue, $m) ? $m[0] : null)
            ;
        }

        if ($roomType || $rate) {
            $room = $h->addRoom();

            if ($roomType) {
                $room->setType($roomType);
            }

            if ($rate) {
                $room->setRate($rate);
            }
        }

        $h->general()->status($this->re("/Status[ ]*:[ ]*(.+?)(?:[ ]{2}|$)/m", $text));

        /*
        $notes = preg_replace('/\s+/', ' ', $this->re("/^[ ]*Notas\s*:\s*(.+?)\n\n/ms", $text));

        if (!empty($h->getCheckOutDate())
            && preg_match("/Check in\s+(?<date>.{3,}?)\s*, aprox\s+(?<time>{$patterns['time']})/i", $notes, $m) // es
        ) {
            $m['date'] = $this->normalizeDate($m['date']);
            $date = EmailDateHelper::parseDateRelative($m['date'], $h->getCheckOutDate(), false);

            if ($date) {
                $h->booked()->checkIn(strtotime($m['time'], $date));
            }
        }

        if (!empty($h->getCheckInDate())
            && preg_match("/Late check out garantizado el\s+(?<date>.{3,}?)\s+por la noche/i", $notes, $m) // es
        ) {
            $m['date'] = $this->normalizeDate($m['date']);
            $date = EmailDateHelper::parseDateRelative($m['date'], $h->getCheckInDate());

            if ($date) {
                $h->booked()->checkOut(strtotime('23:59', $date));
            }
        }
        */

        $cancellation = preg_replace("/\s+/", ' ', trim($this->re("/{$this->opt($this->t("No Shows y Cancelaciones"))}\s*:\s*([\s\S]+?)\n\n/", $text)));

        if ($cancellation) {
            $h->general()->cancellation($cancellation);

            if (preg_match("/Las (?i)reservaciones garantizadas podrán hacer cambios o cancelaciones sin cargo adicional (?<prior>\d{1,3}) hrs antes de la fecha de llegada\./", $cancellation, $m) // es
            ) {
                $h->booked()->deadlineRelative($m['prior'] . ' hours', '00:00');
            }
        }
    }

    public function detectEmailFromProvider($from)
    {
        return strpos($from, $this->reFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (empty($headers["from"]) || empty($headers["subject"])) {
            return false;
        }

        if (strpos($headers["from"], $this->reFrom) === false) {
            return false;
        }

        foreach ($this->reSubject as $re) {
            if (stripos($headers["subject"], $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfPattern);

        if (!isset($pdfs[0])) {
            return false;
        }
        $pdf = $pdfs[0];

        if (($text = \PDF::convertToText($parser->getAttachmentBody($pdf))) === null) {
            return false;
        }

        $findReBody = false;

        foreach ($this->reBody as $re) {
            if (strpos($text, $re) !== false) {
                $findReBody = true;

                break;
            }
        }

        if ($findReBody !== true) {
            return false;
        }

        foreach ($this->reBody2 as $re) {
            if (strpos($text, $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfPattern);

        if (!isset($pdfs[0])) {
            return null;
        }
        $pdf = $pdfs[0];

        if (($this->text = \PDF::convertToText($parser->getAttachmentBody($pdf))) === null) {
            return $email;
        }

        foreach ($this->reBody2 as $lang=>$re) {
            if (strpos($this->text, $re) !== false) {
                $this->lang = substr($lang, 0, 2);

                break;
            }
        }

        $email->setType('HotelReservationPdf' . ucfirst($this->lang));
        $this->parser = $parser;
        $this->parsePdf($email);

        return $email;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function normalizeDate($str)
    {
        $in = [
            // Lunes, 17 de Julio de 2017    |    04 de Septiembre, 2021.
            "/\b(\d{1,2})(?:\s+de)?\s+([[:alpha:]]+)(?:\s+de)?[,\s]+(\d{4})[.\s]*$/u",
            // 5 de Septiembre
            "/^(\d{1,2})(?:\s+de)?\s+([[:alpha:]]+)$/",
        ];
        $out = [
            '$1 $2 $3',
            '$1 $2',
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("/^\d{1,2}\s+([[:alpha:]]+)\s+\d{4}$/u", $str, $m) || preg_match("/^\d{1,2}\s+([[:alpha:]]+)$/u", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return $str;
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function opt($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return '';
        }

        return '(?:' . implode('|', array_map(function ($s) {
            return preg_quote($s, '/');
        }, $field)) . ')';
    }
}
