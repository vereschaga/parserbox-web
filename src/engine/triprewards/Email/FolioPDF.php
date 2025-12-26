<?php

namespace AwardWallet\Engine\triprewards\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;

class FolioPDF extends \TAccountChecker
{
    public $mailFiles = "triprewards/it-48622319.eml, triprewards/it-48927865.eml";
    public $reFrom = "@wyndham.com";
    public $reSubject = [
        '/Your\s+Wyndham\s+.+?\s+Folio\s*\/\s*Invoice$/',
    ];

    public $reBody = [
        'en' => ['View our Wyndham Hotels and Resorts website about privacy', 'Folio'],
    ];
    public $lang = '';
    public static $dict = [
        'en' => [
            'Tel:' => ['Tel:', 'Telephone:'],
        ],
    ];
    private $keywordProv = 'Wyndham';
    private $pdfNamePattern = ".*\.pdf";

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        if (isset($pdfs) && count($pdfs) > 0) {
            foreach ($pdfs as $pdf) {
                if (($text = \PDF::convertToText($parser->getAttachmentBody($pdf))) !== null) {
                    if ($this->assignLang($text)) {
                        $this->parseEmailPdf($text, $email);
                    }
                }
            }
        }
        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if ((stripos($text, $this->keywordProv) !== false)
                && $this->assignLang($text)
            ) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        $fromProv = true;

        if (!isset($headers['from']) || self::detectEmailFromProvider($headers['from']) === false) {
            $fromProv = false;
        }

        if (isset($headers["subject"])) {
            foreach ($this->reSubject as $reSubject) {
                if (($fromProv && stripos($headers["subject"], $reSubject) !== false)
                    || strpos($headers["subject"], $this->keywordProv) !== false
                ) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->reFrom) !== false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    private function parseEmailPdf($textPdf, Email $email)
    {
        $r = $email->add()->hotel();

        $confno = $this->re("/{$this->t('Invoice #')}[ :]+([\w\-]+)/", $textPdf);

        if (!empty($confno)) {
            $r->general()->confirmation($confno, 'Invoice #');
        }

        $r->general()
            ->confirmation($this->re("/{$this->t('Reference #')}[ :]+([\w\-]+)/", $textPdf), 'Reference #')
            ->confirmation($this->re("/{$this->t('Conf. No.')}[ :]+([\w\-]+)/", $textPdf), 'Conf. No.', true);

        $info = array_values(array_filter(array_map("trim",
            explode("\n", strstr($textPdf, 'INFORMATION INVOICE', true)))));

        if (count($info) > 1) {
            $r->hotel()
                ->name(array_shift($info));
            $address = implode(" ", $info);

            if (preg_match("/(.+)\s+{$this->opt($this->t('Tel:'))}\s*([\d\(\)\-\+ ]+?)\s*{$this->t('Fax:')}\s*([\d\(\)\-\+ ]+)$/",
                $address, $m)) {
                $r->hotel()->address($m[1])->phone($m[2])->fax($m[3]);
            }
        }

        $account = $this->re('/Membership No.[ :]+([\w\-]+)/', $textPdf);

        if (!empty($account)) {
            $r->program()->account($account, false);
        }

        $room = $r->addRoom();
        $room->setDescription('Room Number: ' . $this->re('/Room No.[ :]+(\d+)/', $textPdf));

        $dateArr = $this->re('/Arrival[ :]+([\-\d]+)/', $textPdf);
        $dateDep = $this->re('/Departure[ :]+([\-\d]+)/', $textPdf);

        if (preg_match("/^(\d{1,2}[\/\.\-]\d{1,2}[\.\/\-])(\d{2})$/", $dateArr, $m)) {
            $dateArr = str_replace(" ", "",
                preg_replace("/^(\d{1,2}[\/\.\-]\d{1,2}[\.\/\-])(\d{2})$/", "$1 " . "20$2", $dateArr));
        }

        if (preg_match("/^(\d{1,2}[\/\.\-]\d{1,2}[\.\/\-])(\d{2})$/", $dateDep, $m)) {
            $dateDep = str_replace(" ", "",
                preg_replace("/^(\d{1,2}[\/\.\-]\d{1,2}[\.\/\-])(\d{2})$/", "$1 " . "20$2", $dateDep));
        }

        if ($this->identifyDateFormat($dateArr, $dateDep) === 1) {
            $dateArr = preg_replace("/(\d+)[\/\.\-](\d+)[\/\.\-](\d+)$/", "$2.$1.$3", $dateArr);
            $dateDep = preg_replace("/(\d+)[\/\.\-](\d+)[\/\.\-](\d+)$/", "$2.$1.$3", $dateDep);
        } elseif (2 === $this->identifyDateFormat($dateDep, $dateArr)) {
            $dateDep = preg_replace("/(\d{4})[\/\.\-](\d{1,2})[\/\.\-](\d{2})$/", "$3.$2.$1", $dateDep);
            $dateArr = preg_replace("/(\d+)[\/\.\-](\d+)[\/\.\-](\d+)$/", "$3.$2.$1", $dateArr);
        } else {
            $dateArr = preg_replace("/(\d+)[\/\.\-](\d+)[\/\.\-](\d+)$/", "$1.$2.$3", $dateArr);
            $dateDep = preg_replace("/(\d+)[\/\.\-](\d+)[\/\.\-](\d+)$/", "$1.$2.$3", $dateDep);
        }

        $r->booked()
            ->checkIn(strtotime($dateArr))
            ->checkOut(strtotime($dateDep));

        $r->price()->total(PriceHelper::cost($this->re("/\n\s*Total[ ]{2,}(\d[\d\.\,]+)/", $textPdf)));

        return true;
    }

    private function identifyDateFormat($date1, $date2)
    {
//    define("DATE_YEAR_FIRST", "2");
//    define("DATE_MONTH_FIRST", "1");
//    define("DATE_DAY_FIRST", "0");
//    define("DATE_UNKNOWN_FORMAT", "-1");

        if (preg_match("/(\d{1,2})[\/\.\-](\d{1,2})[\.\/\-](\d{4})/", $date1,
                $m) && preg_match("/(\d{1,2})[\/\.\-](\d{1,2})[\.\/\-](\d{4})/", $date2, $m2)
        ) {
            if (intval($m[1]) > 12 || intval($m2[1]) > 12) {
                return 0;
            } elseif (intval($m[2]) > 12 || intval($m2[2]) > 12) {
                return 1;
            } else {
                //try to guess format
                $diff = [];

                foreach (['$3-$2-$1', '$3-$1-$2'] as $i => $format) {
                    $tempdate1 = preg_replace("/(\d{1,2})[\/\.\-](\d{1,2})[\.\/\-](\d{4})/", $format, $date1);
                    $tempdate2 = preg_replace("/(\d{1,2})[\/\.\-](\d{1,2})[\.\/\-](\d{4})/", $format, $date2);

                    if (($tstd1 = strtotime($tempdate1)) !== false && ($tstd2 = strtotime($tempdate2)) !== false && ($tstd2 - $tstd1 > 0)) {
                        $diff[$i] = $tstd2 - $tstd1;
                    }
                }
                $min = min($diff);

                return array_flip($diff)[$min];
            }
        } elseif (preg_match('/(\d{4}\-\d{1,2}\-\d{2})/', $date1) && preg_match('/(\d{4}\-\d{1,2}\-\d{2})/', $date2)) {
            return 2;
        }

        return -1;
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function assignLang($body)
    {
        if (isset($this->reBody)) {
            foreach ($this->reBody as $lang => $reBody) {
                if (stripos($body, $reBody[0]) !== false || stripos($body, $reBody[1]) !== false) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
    }
}
