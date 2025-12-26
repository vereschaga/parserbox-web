<?php

namespace AwardWallet\Engine\national\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class InvoicePDF extends \TAccountChecker
{
    public $mailFiles = "national/it-147779182.eml, national/it-148794453.eml";
    public $subjects = [
        'Invoice from National Car Rental',
    ];

    public $lang = 'es';
    public $pdfNamePattern = ".*pdf";
    public $company;

    public $detectLang = [
        "es" => ["Salida"],
        "en" => ["Start"], // always last
    ];
    public static $dictionary = [
        "en" => [
        ],
        "es" => [
            /*'Reservation #:' => 'Reserva/Reservation #:',
            'Driver:' => 'Conductor/Driver:',
            'Check Out:' => 'Salida/Check Out:',
            'Check In:' => 'Entrada/Check In:',
            'Location:' => 'Sucursal/Location:',*/
            'Start' => 'Salida',
            'End'   => 'Entrada',
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@ehi.com') !== false) {
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

            if (!empty($this->company = $this->re("/(National) Car Rental/", $parser->getSubject()))
                && strpos($text, 'Reservation #:') !== false
                && strpos($text, 'RENTAL INFORMATION') !== false
                && strpos($text, 'Reserved Car Class:') !== false
            ) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]ehi\.com$/', $from) > 0;
    }

    public function ParseRentalPDF(Email $email, $text)
    {
        $r = $email->add()->rental();

        $r->general()
            ->traveller($this->re("/{$this->opt($this->t('Driver:'))}\s*([[:alpha:]][-.\,'[:alpha:] ]*[[:alpha:]])/", $text))
            ->confirmation($this->re("/{$this->opt($this->t('Reservation #:'))}\s*(\d{6,})/", $text));

        if (preg_match("/Total\s*\(([A-Z]{3})\)\s*([\d\.\,]+)/", $text, $m)) {
            if (preg_match("/^\d+\,\d+$/", $m[2])) {
                $r->price()
                    ->total(PriceHelper::cost($m[2], '.', ','));
            } else {
                $r->price()
                    ->total(PriceHelper::cost($m[2], ',', '.'));
            }
            $r->price()
                ->currency($m[1]);

            $dateIn = $this->re("/{$this->opt($this->t('Check Out:'))}\s*(\d+\/\d+\/\d{4}\s*[\d\:]+)/u", $text);
            $dateOut = $this->re("/{$this->opt($this->t('Check In:'))}\s*(\d+\/\d+\/\d{4}\s*[\d\:]+)/u", $text);

            if ($m[1] == 'USD' || $m[1] == 'CAD') {
                $r->pickup()
                    ->date($this->normalizeDate($dateIn));
                $r->dropoff()
                    ->date($this->normalizeDate($dateOut));
            } elseif (preg_match("/^[A-Z]{3}$/", $m[1])) {
                $r->pickup()
                    ->date(strtotime(str_replace('/', '.', $dateIn)));
                $r->dropoff()
                    ->date(strtotime(str_replace('/', '.', $dateOut)));
            }
        }

        $r->pickup()
            ->location($this->re("/{$this->opt($this->t('Location:'))}(.+)\n.+{$this->opt($this->t('Check In:'))}/su", $text));

        $r->dropoff()
            ->location($this->re("/{$this->opt($this->t('Location:'))}\s*(.+)\n/u", $this->re("/{$this->opt($this->t('Check In:'))}(.+){$this->opt($this->t('Start'))}/su", $text)));

        $carModel = $this->re("/{$this->opt($this->t('Start'))}\s*{$this->opt($this->t('End'))}\n.+\d\s+\d{4}\s*(.+)\s[A-Z\d]{4}\s*[A-Z]+\s*\d+\/\d+\s*\d+\/\d+/", $text);

        if (!empty($carModel)) {
            $r->car()
                ->model($carModel);
        }

        if (!empty($this->company)) {
            $r->setCompany($this->company);
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
                if (!empty($this->re("/($word)/", $text))) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }
}
