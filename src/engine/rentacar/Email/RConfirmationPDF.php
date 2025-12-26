<?php

namespace AwardWallet\Engine\rentacar\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class RConfirmationPDF extends \TAccountChecker
{
    public $mailFiles = "rentacar/it-148944549.eml";
    public $subjects = [
        'Enterprise Rent-A-Car Reservation Confirmation',
    ];

    public $lang = '';
    public $pdfNamePattern = ".*pdf";
    public $company;

    public $detectLang = [
        "tr" => ["Driver Name/Sürücü:"],
        "es" => ["Nombre del Conductor:"],
    ];
    public static $dictionary = [
        "tr" => [
            'Reservation Number' => 'Reservation Number/Rezervasyon Numarası:',
            'Your Info'          => 'Your Info/Sizin Bilgileriniz:',
            'Car Summary'        => 'Car Summary/Araç Detayları:',
            'Driver Name'        => 'Driver Name/Sürücü:',
            'Your Options'       => 'Your Options/Ekstralar:',
            'Pickup'             => 'Pickup/Çıkış:',
            'Drop Off'           => 'Drop Off/Dönüş:',
            'Total Charges'      => 'Total Charges/Toplam Bedel',
            //'Rate Charges & Taxes' => '',
        ],
        "es" => [
            'Reservation Number'                                => ['Numero de reserva:', 'NO. DE RESERVA:'],
            'Your Info'                                         => 'Su Información:',
            'Car Summary'                                       => 'Resumen de Auto:',
            'Driver Name'                                       => 'Nombre del Conductor:',
            'Your Options'                                      => 'Sus Opciones:',
            'Pickup'                                            => 'Entrega:',
            'Drop Off'                                          => 'Devolución:',
            'Rate Charges & Taxes'                              => 'Costos estimados de tarifas',
            'Thank you for choosing Enterprise for your rental' => 'Gracias por preferir Enterprise para sus necesidades de arrendamiento',
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@enterprise.') !== false) {
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
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

            $this->detectLang($text);

            if (stripos($text, $this->t('Thank you for choosing Enterprise for your rental')) !== false
                && strpos($text, $this->t('Car Summary')) !== false
                && strpos($text, $this->t('Your Options')) !== false
            ) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]enterprise\.$/', $from) > 0;
    }

    public function ParseRentalPDF(Email $email, $text)
    {
        $r = $email->add()->rental();

        $r->general()
            ->traveller($this->re("#{$this->opt($this->t('Driver Name'))}\s*([[:alpha:]][-.\,'[:alpha:] ]*[[:alpha:]])[ ]{15,}#", $text))
            ->confirmation($this->re("#{$this->opt($this->t('Reservation Number'))}\s*(\d{6,})#", $text));

        $table = $this->SplitCols($text, [0, 65]);

        $pickUpText = $this->re("#{$this->opt($this->t('Pickup'))}(.+){$this->opt($this->t('Drop Off'))}#su", $table[0]);

        if (preg_match("#\s*(?<month>\d+)\/(?<day>\d+)\/(?<year>\d{4})\s*(?<time>[\d\:]+\s*A?P?M)\s*(?<location>.+)\n^(?<phone>[\d\s\-]{12,})#msu", $pickUpText, $m)) {
            $r->pickup()
                ->date(strtotime($m['day'] . '.' . $m['month'] . '.' . $m['year'] . ', ' . $m['time']))
                ->location(str_replace("\n", " ", $m['location']))
                ->phone($m['phone']);
        }

        $dropOffText = $this->re("#{$this->opt($this->t('Drop Off'))}(.+){$this->opt($this->t('Rate Charges & Taxes'))}#su", $table[0]);

        if (preg_match("#\s*(?<month>\d+)\/(?<day>\d+)\/(?<year>\d{4})\s*(?<time>[\d\:]+\s*A?P?M)\s*(?<location>.+)\n^(?<phone>[\d\s\-]{12,})#msu", $dropOffText, $m)) {
            $r->dropoff()
                ->date(strtotime($m['day'] . '.' . $m['month'] . '.' . $m['year'] . ', ' . $m['time']))
                ->location(str_replace("\n", " ", $m['location']))
                ->phone($m['phone']);
        }

        $carType = $this->re("#{$this->opt($this->t('Car Summary'))}(.+){$this->opt($this->t('Your Options'))}#su", $table[1]);

        if (!empty($carType)) {
            $r->setCarType(str_replace("\n", " ", $carType));
        }

        $currency = $this->re("/Costo Total \(([A-Z]{3})\)/u", $text);
        $total = $this->re("/Cargos Totales\s*([\d\.\,]+)/", $text);

        if (!empty($total) && !empty($currency)) {
            $r->price()
                ->total(PriceHelper::parse($total, $currency))
                ->currency($currency);
        }
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);
        $this->emailSubject = $parser->getSubject();

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

            $this->detectLang($text);

            $this->ParseRentalPDF($email, $text);
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
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
    }

    private function normalizeDate($date)
    {
        //$this->logger->debug($date);
        $in = [
            // 29/03/2022 11:57
            "/^(\d+)\/(\d+)\/(\d{4})\s*([\d\:]+)$/iu",
        ];
        $out = [
            "$2.$1.$3, $4",
        ];
        $date = preg_replace($in, $out, $date);

        if (preg_match("/\d+\s+([[:alpha:]]+)\s+\d{4}/u", $date, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $date = str_replace($m[1], $en, $date);
            }
        }

        return strtotime($date);
    }

    private function detectLang($text)
    {
        foreach ($this->detectLang as $lang => $words) {
            foreach ($words as $word) {
                if (!empty($this->re("#($word)#", $text))) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
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

    private function TableHeadPos($text)
    {
        $row = explode("\n", $text)[0];
        $head = array_filter(array_map('trim', explode("|", preg_replace("#\s{2,}#", "|", $row))));
        $pos = [];
        $lastpos = 0;

        foreach ($head as $word) {
            $pos[] = mb_strpos($row, $word, $lastpos, 'UTF-8');
            $lastpos = mb_strpos($row, $word, $lastpos, 'UTF-8') + mb_strlen($word, 'UTF-8');
        }

        return $pos;
    }
}
