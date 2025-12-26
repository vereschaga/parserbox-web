<?php

namespace AwardWallet\Engine\interval\Email;

use AwardWallet\Schema\Parser\Email\Email;

class ConfirmationCertificate extends \TAccountChecker
{
    public $mailFiles = "interval/it-111442750.eml, interval/it-474226848-es.eml";

    public $pdfNamePattern = ".*\.pdf";

    public $lang = '';
    public static $dictionary = [
        'es' => [
            'confNoOta'       => 'Número de Confirmación',
            'confNo'          => ['Número de la Reserva', 'Número de la reserva'],
            'Date'            => 'Fecha',
            'travellersStart' => ['No se requiere Certificado de Huésped si los ocupantes son los siguientes'],
            'Resort Name'     => 'Nombre del Complejo',
            'Phone'           => 'Teléfono',
            // 'Fax' => '',
            'Address'   => 'Dirección',
            'Check-In'  => 'Entrada',
            'Check-Out' => 'Salida',
            // 'VARIES' => '',
            'Accommodating' => 'Capacidad',
            'Privately'     => 'Privadamente',
        ],
        'en' => [
            'confNoOta' => 'Confirmation',
            'confNo'    => ['Reservation Number', 'Reservation number'],
            // 'Date' => '',
            'travellersStart' => ['No guest certificate required, when occupied by any of the following people'],
            // 'Resort Name' => '',
            // 'Phone' => '',
            // 'Fax' => '',
            // 'Address' => '',
            // 'Check-In' => '',
            // 'Check-Out' => '',
            // 'VARIES' => '',
            // 'Accommodating' => '',
            // 'Privately' => '',
        ],
    ];

    private $detectFrom = "confirmation@intervalintl.com";
    private $detectSubject = [
        // en
        'Your Resort Confirmation is Ready - Confirmation Number',
    ];

    private $detectBody = [
        'es' => [
            'Certificado de Confirmación',
        ],
        'en' => [
            'Confirmation Certificate',
        ],
    ];

    public function detectEmailFromProvider($from)
    {
        return stripos($from, "@intervalintl.com") !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (stripos($headers['from'], $this->detectFrom) === false) {
            return false;
        }

        foreach ($this->detectSubject as $dSubject) {
            if (stripos($headers["subject"], $dSubject) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if ($this->detectPdf($text) == true) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if ($this->detectPdf($text) == true) {
                $this->parseEmailPdf($email, $text);
            }
        }

        $email->setType('ConfirmationCertificate' . ucfirst($this->lang));

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

    public function detectPdf($text): bool
    {
        // detect provider
        if ($this->containsText($text, ['intervalworld.com']) === false) {
            return false;
        }

        // detect language
        foreach ($this->detectBody as $lang => $detectBody) {
            if ($this->containsText($text, $detectBody) !== false) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
    }

    private function parseEmailPdf(Email $email, ?string $text = null): void
    {
//        $this->logger->debug('Pdf text = ' . print_r($text, true));

        $patterns = [
            'time'  => '\d{1,2}[:：]\d{2}(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?)?', // 4:19PM    |    2:00 p. m.
            'phone' => '[+(\d][-+. \d)(]{5,}[\d)]', // +377 (93) 15 48 52    |    (+351) 21 342 09 07    |    713.680.2992
        ];

        // 4:OO    ->    4:00
        $text = preg_replace([
            '/\b[Oo](\w:\w\w)\b/',
            '/\b(\w?)[Oo](:\w\w)\b/',
            '/\b(\w?\w:)[Oo](\w)\b/',
            '/\b(\w?\w:\w)[Oo]\b/',
        ], [
            '0${1}',
            '${1}0$2',
            '${1}0$2',
            '${1}0',
        ], $text);

        // Travel Agency
        $email->ota()->confirmation($this->re("/^[ ]{0,80}{$this->opt($this->t('confNoOta'))}(?: | ?[:]+ ?)(\d{5,})(?:[ ]{3,}|\n)/m", $text));

        // HOTEL

        $h = $email->add()->hotel();

        // General
        if (preg_match("/(?:\n[ ]*|[ ]{3})({$this->opt($this->t('confNo'))})[ ]+([- A-z\d]{5,})\n/", $text, $m)) {
            // R5D07C    |    1-14856
            $h->general()->confirmation(str_replace(' ', '', $m[2]), $m[1]);
        } elseif (preg_match("/(?:\n[ ]*|[ ]{3}){$this->opt($this->t('confNo'))}[ ]+0\n/", $text)
            || !preg_match("/{$this->opt($this->t('confNo'))}/", $text)
        ) {
            $h->general()->noConfirmation();
        }

        $reservationDateVal = $this->re("/^[ ]{0,80}{$this->opt($this->t('Date'))}: ?(\S.+?\S)(?:[ ]{3}|\n)/m", $text);
        $h->general()->date($this->normalizeDate($reservationDateVal));

        $travellersText = $this->re("/(.+{$this->opt($this->t('travellersStart'))}[^\w\s]*\n(?:.*\n){1,15}?).*_{4,}/", $text);

        if (!empty($travellersText) && preg_match("/(.+){$this->opt($this->t('travellersStart'))}[^\w\s]*\n((?:.*\n)+)/", $travellersText, $m)) {
            $column = strlen($m[1]);

            if ($column > 30) {
                $tt = $m[2];
                $tt = preg_replace('/^.{0,' . $column . '}$/m', '', $tt);
                $tt = preg_replace('/^.{' . ($column - 10) . ',} {3,}(\S.*)/m', '$1', $tt);
                $tt = trim($tt);

                if (preg_match("/^[[:alpha:]\-,\s]+$/", $tt, $mat)) {
                    $h->general()
                        ->travellers(preg_split("/\s*,\s*/", $tt), true);
                }
            }
        }

        // Hotel
        $hotelText = $this->re("/(.+{$this->opt($this->t('Resort Name'))}.+\n(?:.*\n){1,15}?).* {3,}{$this->opt($this->t('Phone'))}\b.*/", $text);

        if (!empty($hotelText) && preg_match("/(.+){$this->opt($this->t('Resort Name'))}/", $hotelText, $m)) {
            $column = strlen($m[1]);

            if ($column > 30) {
                $hotelText = preg_replace('/^.{0,' . $column . '}$/m', '', $hotelText);
                $hotelText = preg_replace('/^.{' . ($column - 10) . ',' . ($column + 5) . '} {3,}(\S.*)/m', '$1', $hotelText);
                $h->hotel()
                    ->name(preg_replace(["/[,\s]*\n+\s*/", "/\s+/"], [', ', ' '], trim($this->re("/{$this->opt($this->t('Resort Name'))}\s+([\s\S]+)\n\s*{$this->opt($this->t('Address'))}\s+/", $hotelText))))
                    ->address(preg_replace(["/[,\s]*\n+\s*/", "/\s+/"], [', ', ' '], trim($this->re("/{$this->opt($this->t('Address'))}\s+([\s\S]+)/", $hotelText))))
                ;
            }
        }
        $h->hotel()
            ->phone($this->re("/ {3}{$this->opt($this->t('Phone'))}[ ]+({$patterns['phone']})\n/", $text))
            ->fax($this->re("/ {3}{$this->opt($this->t('Fax'))}[ ]+({$patterns['phone']})\n/", $text), false, true)
        ;

        // Booked
        $dateCheckIn = $dateCheckOut = $timeCheckIn = $timeCheckOut = null;
        $checkInVal = $this->re("/ {3}{$this->opt($this->t('Check-In'))}[ ]+(.+)\n/", $text);
        $checkOutVal = $this->re("/ {3}{$this->opt($this->t('Check-Out'))}[ ]+(.+)\n/", $text);

        if (preg_match($pattern1 = "/^(?<time>{$patterns['time']})\s+(?<date>.*\b\d{4}\b.*)$/", $checkInVal, $m)) {
            // 4:00 PM Friday November 05, 2021
            $timeCheckIn = $m['time'];
            $dateCheckIn = $this->normalizeDate($m['date']);
        } elseif (preg_match($pattern2 = "/^{$this->opt($this->t('VARIES'))}\s+(?<date>.*\b\d{4}\b.*)$/i", $checkInVal, $m)) {
            // VARIES Friday November 05, 2021
            $timeCheckIn = '00:00';
            $dateCheckIn = $this->normalizeDate($m['date']);
        }

        if (preg_match($pattern1, $checkOutVal, $m)) {
            $timeCheckOut = $m['time'];
            $dateCheckOut = $this->normalizeDate($m['date']);
        } elseif (preg_match($pattern2, $checkOutVal, $m)) {
            $timeCheckOut = '23:59';
            $dateCheckOut = $this->normalizeDate($m['date']);
        }

        if ($timeCheckIn && $dateCheckIn) {
            $h->booked()->checkIn(strtotime($timeCheckIn, $dateCheckIn));
        }

        if ($timeCheckOut && $dateCheckOut) {
            $h->booked()->checkOut(strtotime($timeCheckOut, $dateCheckOut));
        }

        $h->booked()
            ->guests(
                $this->re("/[ ]{3}{$this->opt($this->t('Accommodating'))}[ ]+(\d{1,3})[ ]*{$this->opt($this->t('Privately'))}/", $text)
                ?? $this->re("/ (\d{1,3})[ ]*{$this->opt($this->t('Privately'))}.*\n.*[ ]{3}{$this->opt($this->t('Accommodating'))}\n/", $text)
        );
    }

    private function normalizeDate($str)
    {
        $in = [
            // Friday November 05, 2021
            "/^\s*[-[:alpha:]]+\s+([[:alpha:]]+)\s+(\d{1,2})[\s,]*(\d{4})\s*$/u",
            // Jueves 07 septiembre 2023
            "/^\s*[-[:alpha:]]+\s+(\d{1,2})\s+([[:alpha:]]+)\s+(\d{4})\s*$/u",
        ];
        $out = [
            '$2 $1 $3',
            '$1 $2 $3',
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = \AwardWallet\Engine\MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return strtotime($str);
    }

    private function t(string $phrase, string $lang = '')
    {
        if (!isset(self::$dictionary, $this->lang)) {
            return $phrase;
        }

        if ($lang === '') {
            $lang = $this->lang;
        }

        if (empty(self::$dictionary[$lang][$phrase])) {
            return $phrase;
        }

        return self::$dictionary[$lang][$phrase];
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function containsText($text, $needle): bool
    {
        if (empty($text) || empty($needle)) {
            return false;
        }

        if (is_array($needle)) {
            foreach ($needle as $n) {
                if (stripos($text, $n) !== false) {
                    return true;
                }
            }
        } elseif (is_string($needle) && stripos($text, $needle) !== false) {
            return true;
        }

        return false;
    }

    private function opt($field, $delimiter = '/')
    {
        $field = (array) $field;

        if (empty($field)) {
            $field = ['false'];
        }

        return '(?:' . implode("|", array_map(function ($s) use ($delimiter) {
            return str_replace(' ', '\s+', preg_quote($s, $delimiter));
        }, $field)) . ')';
    }
}
