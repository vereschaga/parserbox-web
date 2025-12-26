<?php

namespace AwardWallet\Engine\suntrans\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class YourBooking extends \TAccountChecker
{
    public $mailFiles = "suntrans/it-12250596.eml, suntrans/it-139152351.eml, suntrans/it-656637496.eml";
    public $subjects = [
        'Changes to your booking: SUNTR_',
        'Your booking confirmation: SUNTR_',
    ];

    public $lang = 'en';
    public $pdfNamePattern = ".*pdf";
    public $subject;

    public $detectLang = [
        'en' => ['BOOKING VOUCHER'],
        'es' => ['COMPROBANTE DE RESERVA'],
    ];

    public static $dictionary = [
        "en" => [
        ],

        "es" => [
            //'Booking reference: SUNTR_' => '',
            'Transfer details'   => 'Datos del traslado',
            'BOOKING VOUCHER'    => 'COMPROBANTE DE RESERVA',
            'to your booking'    => 'de reserva',
            'DRIVER COPY'        => 'COPIA PARA EL CONDUCTOR',
            'Booking reference:' => 'Código de reserva:',
            'Lead passenger:'    => 'Titular de la reserva:',
            'Vehicle:'           => 'Vehículo:',
            'Total passengers:'  => 'Total de pasajeros:',
            //'Total adults:' => '',
            //'Total children:' => '',
            'FROM:'          => 'DESDE:',
            'Accommodation:' => 'Alojamiento:',
            'TO:'            => 'A:',
            'Pickup time:'   => 'Hora de recogida:',
            //'Email' => '',
            'Flight arrival info (local):' => 'Información de salida del vuelo (local): ',
            'at'                           => 'a las', //from date
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@suntransfers.com') !== false) {
            foreach ($this->subjects as $subject) {
                if (stripos($headers['subject'], $subject) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $this->subject = $parser->getSubject();

        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

            $this->assignLang($text);

            if (strpos($text, 'Suntransfers.com') !== false && strpos($text, $this->t('Booking reference:')) !== false && strpos($text, $this->t('Transfer details')) !== false
            ) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]suntransfers\.com$/', $from) > 0;
    }

    public function ParseTranferPDF(Email $email, $text)
    {
        $status = $this->re("/(\w+)\s*{$this->opt($this->t('to your booking'))}/us", $this->subject);

        $transferText = $this->re("/\n(\s*{$this->opt($this->t('DRIVER COPY'))}.+)$/su", $text);
        $tranfers = array_filter(preg_split("/\s+{$this->opt($this->t('DRIVER COPY'))}\s+/", $transferText));

        foreach ($tranfers as $transfer) {
            $transfer = preg_replace("/^.+\n+{$this->opt($this->t('Booking reference:'))}/s", "{$this->t('Booking reference:')}", $transfer);

            $t = $email->add()->transfer();

            if (!empty($status)) {
                $t->general()
                    ->status($status);
            }

            $traveller = $this->re("/{$this->opt($this->t('Lead passenger:'))}\s*(\D+)[ ]{10,}/", $text);

            if (empty($traveller)) {
                $traveller = $this->re("/{$this->opt($this->t('Lead passenger:'))}\s*(\D+)\n/", $text);
            }

            $t->general()
                ->confirmation($this->re("/{$this->opt($this->t('Booking reference:'))}\s*(SUNTR[_][A-Z\d]{6})/", $transfer))
                ->traveller($traveller);

            $s = $t->addSegment();

            $s->setCarType($this->re("/{$this->opt($this->t('Vehicle:'))}\s*([A-Z\s]+)[ ]{5,}/", $transfer));
            $adults = $this->re("/{$this->opt($this->t('Total passengers:'))}\s*(\d+)/", $transfer);

            if (empty($adults)) {
                $adults = $this->re("/{$this->opt($this->t('Total adults:'))}\s*(\d+)/", $transfer);
            }
            $s->setAdults($adults);

            $kids = $this->re("/{$this->opt($this->t('Total children:'))}\s*(\d+)/", $transfer);

            if ($kids !== null) {
                $s->setKids($kids);
            }

            //Departure info - for hotels
            if (preg_match("/{$this->opt($this->t('FROM:'))}.+{$this->opt($this->t('Accommodation:'))}.+{$this->opt($this->t('TO:'))}/s", $transfer)) {
                $from = $this->re("/{$this->opt($this->t('FROM:'))}\s*(.+)\n/", $transfer);
                $depName = preg_replace("/\n\s+/s", ' ', $this->re("/{$this->opt($this->t('Accommodation:'))}\s*(.+){$this->opt($this->t('Vehicle:'))}/s", $transfer));

                if (stripos($depName, $this->t('Pickup time:')) !== false) {
                    $depName = preg_replace("/{$this->opt($this->t('Pickup time:'))}.+/", "", $depName);
                }

                if (!empty($depName)) {
                    $s->departure()
                        ->name($from . ', ' . $depName);
                }

                $depDate = $this->re("/{$this->opt($this->t('Pickup time:'))}\s*(.+)\n/", $transfer);

                if (!empty($depDate)) {
                    $s->departure()
                        ->date($this->normalizeDate($depDate));
                }

                //Departure info - for flight
            } elseif (preg_match("/{$this->opt($this->t('FROM:'))}\s*(?<depName>\D+)\((?<depCode>[A-Z]{3})\)/", $transfer, $m)
            || preg_match("/{$this->opt('FROM:')}\s*(?<depName>\D+)\s*Email/", $transfer, $m)
            || preg_match("/{$this->opt('FROM:')}\s*(?<depName>\D+)\s*{$this->opt($this->t('Accommodation:'))}/", $transfer, $m)) {
                $s->departure()
                    ->name($m['depName']);

                if (isset($m['depCode'])) {
                    $s->departure()
                        ->code($m['depCode']);
                }

                $depDate = $this->re("/{$this->opt($this->t('Flight arrival info (local):'))}\s*(.+)\n/", $transfer);

                if (!empty($depDate)) {
                    $s->departure()
                        ->date($this->normalizeDate($depDate));
                }
            }

            //Arrival info - for hotels
            if (preg_match("/{$this->opt($this->t('TO:'))}.+{$this->opt($this->t('Accommodation:'))}/s", $transfer)) {
                $to = $this->re("/{$this->opt($this->t('TO:'))}\s*(.+)\n/", $transfer);
                $arrName = $this->re("/{$this->opt($this->t('Accommodation:'))}\s*(.+)(?:\n|$)/", $transfer);

                if (!empty($arrName)) {
                    $s->arrival()
                        ->name($to . ', ' . $arrName)
                        ->noDate();
                }
                //Arrival info - for flight
            } elseif (preg_match("/{$this->opt($this->t('TO:'))}\s*(?<arrName>\D+)\s+\((?<arrCode>[A-Z]{3})\)/", $transfer, $m)
                || preg_match("/{$this->opt('TO:')}\s*(?<arrName>\D+)\s*Email/", $transfer, $m)
                || preg_match("/{$this->opt('TO:')}\s*(?<arrName>\D+)\s*{$this->opt($this->t('Accommodation:'))}/", $transfer, $m)) {
                $s->arrival()
                    ->name($m['arrName'])
                    ->noDate();

                if (isset($m['arrCode'])) {
                    $s->arrival()
                        ->code($m['arrCode']);
                }
            }
        }

        if (count($tranfers) === 0) {
            $tranfers = array_filter(preg_split("/\n +{$this->opt($this->t('BOOKING VOUCHER'))}\s*\n/", "\n\n" . $text));

            foreach ($tranfers as $transfer) {
                $t = $email->add()->transfer();

                $t->general()
                    ->confirmation($this->re("/{$this->opt($this->t('Booking reference:'))}\s*(SUNTR[_][A-Z\d]{6})\s+/", $transfer))
                    ->traveller($this->re("/{$this->opt($this->t('Lead passenger:'))} *(\S.+?)( {2,}|\n)/", $text));

                $segments = preg_split("/\n *{$this->opt($this->t('Transfer details'))}/", $text);
                array_shift($segments);

                foreach ($segments as $stext) {
                    $tableText = $this->re("/\n( *{$this->opt($this->t('FROM:'))}[\s\S]+?)\n{$this->opt($this->t('Where to locate your driver'))}/", $stext);
                    $table = $this->createTable($tableText, $this->rowColumnPositions($this->inOneRow($tableText)));

                    $s = $t->addSegment();

                    $s->setCarType($this->re("/{$this->opt($this->t('Vehicle:'))}\s*([A-Z ]+)\n/", $table[2] ?? ''));
                    $adults = $this->re("/{$this->opt($this->t('Total passengers:'))} (\d+)/", $table[2] ?? '');

                    if (empty($adults)) {
                        $adults = $this->re("/{$this->opt($this->t('Total adults:'))} *(\d+)/", $table[2] ?? '');
                    }
                    $s->setAdults($adults);

                    $kids = $this->re("/{$this->opt($this->t('Total children:'))} *(\d+)/", $table[2] ?? '');

                    if ($kids !== null) {
                        $s->setKids($kids);
                    }

                    if (preg_match("/{$this->opt($this->t('FROM:'))}([\s\S]+?)\n\s*(?:{$this->opt($this->t('Accommodation:'))}|{$this->opt($this->t('Flight arrival info (local):'))})/", $table[0] ?? '', $m)) {
                        $depName = trim($m[1]);
                        $address = $this->re("/\n\s*{$this->opt($this->t('Accommodation:'))} *([\s\S]+?)\n\s*{$this->opt($this->t('Pickup time:'))}/", $table[0] ?? '');

                        if (!empty($address)) {
                            $depName .= ', ' . trim($address);
                        }
                        $depName = preg_replace("/\s+/", ' ', $depName);
                        $s->departure()
                            ->name($depName);
                    }

                    $depDate = $this->re("/{$this->opt($this->t('Pickup time:'))} *(\S[\s\S]+?\])\n/", $table[0] ?? '');

                    if (empty($depDate)) {
                        $depDate = $this->re("/{$this->opt($this->t('Flight arrival info (local):'))} *(\S[\s\S]+?\])\n/", $table[0] ?? '');
                    }

                    if (!empty($depDate)) {
                        $s->departure()
                            ->date($this->normalizeDate($depDate));
                    }

                    if (preg_match("/{$this->opt($this->t('TO:'))}([\s\S]+?)\n\s*(?:{$this->opt($this->t('Accommodation:'))}|{$this->opt($this->t('Flight departure info (local):'))})/", $table[1] ?? '', $m)) {
                        $arrName = trim($m[1]);
                        $address = $this->re("/\n\s*{$this->opt($this->t('Accommodation:'))} *([\s\S]+?)\s*$/", $table[1] ?? '');

                        if (!empty($address)) {
                            $arrName .= ', ' . trim($address);
                        }
                        $arrName = preg_replace("/\s+/", ' ', $arrName);

                        $s->arrival()
                            ->name($arrName)
                            ->noDate();
                    }
                }
            }
        }
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

            $this->assignLang($text);

            if (stripos($text, $this->t('BOOKING VOUCHER')) !== false) {
                $this->ParseTranferPDF($email, $text);
            }

            if (preg_match("/[ ]{10,}([\d\.\,]+)(\S)\n+{$this->opt($this->t('Transfer details'))}/su", $text, $m)) {
                $email->price()
                    ->total(PriceHelper::cost($m[1], ',', '.'))
                    ->currency($this->normalizeCurrency($m[2]));
            }

            if (preg_match("/ {3,}{$this->opt($this->t('Total amount'))}\n.*[ ]{10,}([\d\.\,]+)(\S)\n+/u", $text, $m)) {
                $email->price()
                    ->total(PriceHelper::cost($m[1], ',', '.'))
                    ->currency($this->normalizeCurrency($m[2]));
            }
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

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

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) {
            return "starts-with(normalize-space(.), \"{$s}\")";
        }, $field));
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) {
            return "contains(normalize-space(.), \"{$s}\")";
        }, $field));
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return preg_quote($s, '/');
        }, $field)) . ')';
    }

    private function normalizeCurrency($string)
    {
        $string = trim($string);
        $currencies = [
            'GBP' => ['£'],
            'EUR' => ['€'],
            'INR' => ['Rs.', 'Rs'],
            'CAD' => ['CA $'],
        ];

        foreach ($currencies as $code => $currencyFormats) {
            foreach ($currencyFormats as $currency) {
                if ($string === $currency) {
                    return $code;
                }
            }
        }

        return $string;
    }

    private function normalizeDate($date)
    {
        // $this->logger->debug('$date = '.print_r( $date,true));
        $in = [
            // 16/01/2022 at 16:20 [4.20 pm]
            "/^(\d+)\/(\d+)\/(\d{4})\s*{$this->opt($this->t('at'))}\s*([\d\:]+)\s*\[[\d\.]+\s*a?p?m?\]$/iu",
        ];
        $out = [
            "$1.$2.$3, $4",
        ];
        $date = preg_replace($in, $out, $date);

        // $this->logger->debug('$date = '.print_r( $date,true));
        if (preg_match("/\d+\s+([[:alpha:]]+)\s+\d{4}/u", $date, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $date = str_replace($m[1], $en, $date);
            }
        }

        return strtotime($date);
    }

    private function assignLang($text)
    {
        foreach ($this->detectLang as $lang => $words) {
            foreach ($words as $word) {
                if (stripos($text, $word) !== false) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function createTable(?string $text, $pos = []): array
    {
        $cols = [];
        $rows = explode("\n", $text);

        if (!$pos) {
            $pos = $this->rowColumnPositions($this->inOneRow($text));
        }
        arsort($pos);

        foreach ($rows as $row) {
            foreach ($pos as $k => $p) {
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

    private function rowColumnPositions(?string $row): array
    {
        $head = array_filter(array_map('trim', explode("|", preg_replace("/\s{2,}/", "|", $row))));
        $pos = [];
        $lastpos = 0;

        foreach ($head as $word) {
            $pos[] = mb_strpos($row, $word, $lastpos, 'UTF-8');
            $lastpos = mb_strpos($row, $word, $lastpos, 'UTF-8') + mb_strlen($word, 'UTF-8');
        }

        return $pos;
    }

    private function inOneRow($text)
    {
        $textRows = array_filter(explode("\n", $text));
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

    private function split($re, $text)
    {
        $r = preg_split($re, $text, -1, PREG_SPLIT_DELIM_CAPTURE);
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
}
