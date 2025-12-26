<?php

namespace AwardWallet\Engine\nordic\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;

class PdfInBody extends \TAccountChecker
{
    public $mailFiles = "nordic/it-30207493.eml, nordic/it-30257927.eml";

    public $reFrom = ["@choicehotels.com"];
    public $reSubject = [
        'Quality Inn Colchester Burlington',
    ];
    public $reBody = [
        'en' => ['Post Date', 'ChoiceHotels.com', 'Folio Summary'],
    ];
    public static $dict = [
        'en' => [],
    ];
    private $lang = '';

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $textPdf = $this->getMainText($parser);

        if ($textPdf === false) {
            return $email;
        }

        $a = explode('\\', __CLASS__);
        $class = end($a);
        $email->setType($class . ucfirst($this->lang));

        if (!$this->parseEmail($email, $textPdf)) {
            return null;
        }

        return $email;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $textPdf = $this->getMainText($parser);

        if ($textPdf === false) {
            return false;
        } else {
            return true;
        }
    }

    public function detectEmailFromProvider($from)
    {
        foreach ($this->reFrom as $reFrom) {
            if (stripos($from, $reFrom) !== false) {
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
                    || strpos($headers["subject"], 'TAP') !== false
                ) {
                    return true;
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

    private function parseEmail(Email $email, $textPdf)
    {
        $text = strstr($textPdf, 'Post Date', true);

        if (empty($text)) {
            $this->logger->debug('other format pdf');

            return false;
        }
        $text = $this->re("/{$this->opt($this->t('Account'))}:[^\n]+\n(.+)/s", $text);
        $pos[0] = 0;
        $pos[1] = mb_strlen($this->re("/\n(.+){$this->opt($this->t('You were checked out by'))}/", $text));
        $table = $this->splitCols($text, $pos);
        $info = $table[0];

        $r = $email->add()->hotel();

        $r->general()
            ->noConfirmation();

        $acc = $this->re("/{$this->opt($this->t('Rewards Program ID'))}:[ ]+([\w\-]+)/", $text);

        if (!empty($acc)) {
            $r->program()
                ->account($acc, false);
        }

        if (preg_match("/(.+?)\s*\([A-Z\d]+\)\s+(.+?)\s*\n(\(\d{3}\)[\d \-]+)/s", $info, $m)) {
            $r->hotel()
                ->name($this->nice($m[1]))
                ->address($this->nice($m[2]))
                ->phone($this->nice($m[3]));
        }
        $checkIn = strtotime($this->re("/{$this->opt($this->t('Check In Time'))}:[ ]*(.+)/", $text));

        if (empty($checkIn)) {
            $checkIn = strtotime($this->re("/{$this->opt($this->t('Arrival Date'))}:[ ]*(.+)/", $text));
        }
        $checkOut = strtotime($this->re("/{$this->opt($this->t('Check Out Time'))}:[ ]*(.+)/", $text));

        if (empty($checkOut)) {
            $checkOut = strtotime($this->re("/{$this->opt($this->t('Departure Date'))}:[ ]*(.+)/", $text));
        }
        $r->booked()
            ->checkIn($checkIn)
            ->checkOut($checkOut);

        $priceText = strstr($textPdf, 'Folio Summary');
        $sum = $this->re("/[ ]{5,}\( *(.?[\d\,\.]+) *\)/", $priceText);

        if (!empty($sum) && strpos($sum, '$') === 0) {
            $sum = substr($sum, 1);
            $r->price()
                ->currency('USD');
        }

        $r->price()
            ->total(PriceHelper::cost($sum), false, true);

        return true;
    }

    private function getMainText(\PlancakeEmailParser $parser)
    {
        $pdf = $this->http->FindSingleNode(
            "//embed/@src",
            null,
            false,
            "/^data:application\/pdf;base64,(.+)/");

        if (empty($pdf)) {
            $pdfs = $parser->searchAttachmentByName('.*pdf');

            if (!isset($pdfs[0])) {
                return false;
            }
            $text = null;

            foreach ($pdfs as $pdf) {
                $pdfBody = $parser->getAttachmentBody($pdf);

                if (($text = \PDF::convertToText($pdfBody)) === null) {
                    continue;
                }

                if (!$this->assignLang($text)) {
                    continue;
                }
                //get only first pdf...
                $parse = true;

                break;
            }

            if (!isset($parse) || !isset($text)) {
                return false;
            } else {
                $textPdf = $text;
            }
        } else {
            $pdf = imap_base64($pdf);
            $textPdf = \PDF::convertToText($pdf);

            if (!$this->assignLang($textPdf)) {
                return false;
            }
        }

        return $textPdf;
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function assignLang($text)
    {
        if (isset($this->reBody)) {
            foreach ($this->reBody as $lang => $reBody) {
                if (stripos($text, $reBody[0]) !== false
                    && stripos($text, $reBody[1]) !== false
                    && stripos($text, $reBody[2]) !== false
                ) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
    }

    private function splitCols($text, $pos = false)
    {
        $cols = [];
        $rows = explode("\n", $text);

        if (!$pos) {
            $pos = $this->rowColsPos($rows[0]);
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

    private function rowColsPos($row)
    {
        $head = array_filter(array_map('trim', explode("|", preg_replace("#\s{2,}#", "|", $row))));
        $pos = [];
        $lastpos = 0;

        foreach ($head as $word) {
            $pos[] = mb_strpos($row, $word, $lastpos, 'UTF-8');
            $lastpos = mb_strpos($row, $word, $lastpos, 'UTF-8') + mb_strlen($word, 'UTF-8');
        }

        return $pos;
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function nice($str)
    {
        return trim(preg_replace("/\s+/", ' ', $str));
    }
}
