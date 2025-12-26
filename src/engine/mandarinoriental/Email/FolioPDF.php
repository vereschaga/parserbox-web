<?php

namespace AwardWallet\Engine\mandarinoriental\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class FolioPDF extends \TAccountChecker
{
    public $mailFiles = "mandarinoriental/it-268325792.eml";
    public $subjects = [
        'Folio Letter for Mandarin Oriental',
    ];

    public $lang = 'en';
    public $pdfNamePattern = ".*pdf";

    public $hotelName = '';
    public $subject;

    public static $dictionary = [
        "en" => [
            'Telephone'           => ['Terms', 'Telephone', 'Property Code'],
            'Confirmation Number' => ['Confirmation Number', 'Confirmation No.'],
            'Room Number'         => 'Room',
        ],
        "zh" => [
            'Arrival'             => '到店',
            'Departure'           => '离店',
            'Room Number'         => '房号',
            'Confirmation Number' => ['确认号'],
        ],
    ];

    public $detectLang = [
        'zh' => ['到店'],
        'en' => ['Arrival'], //always last
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@mohg.com') !== false) {
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
            $this->AssignLang($text);

            if (strpos($text, 'Mandarin Oriental') !== false
                && (strpos($text, 'guest folio') !== false || strpos($text, 'Folio Report') !== false)
                && strpos($text, 'Arrival') !== false
            ) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]mohg.com$/', $from) > 0;
    }

    public function ParseHotelPDF(Email $email, $text)
    {
        $h = $email->add()->hotel();

        $confirmation = $this->re("/{$this->opt($this->t('Confirmation Number'))}\s*\:?\s*([\d\-]{5,})/", $text);

        if (!empty($confirmation)) {
            $h->general()
                ->confirmation($confirmation);
        } else {
            $h->general()
                ->noConfirmation();
        }
        $travellers = explode(", ", $this->re("/^\s*([[:alpha:]][-\.\,\'[:alpha:] ]*?[[:alpha:]])[ ]{20,}(?:Arrival|Page)/mu", $text));
        $h->general()
            ->travellers(preg_replace("/^(?:Mrs\.|Mr\.|Dr\.|Ms\.)/", "", $travellers));

        $hotelName = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Yours sincerely,')]/preceding::text()[starts-with(normalize-space(), 'Mandarin') or starts-with(normalize-space(), 'MANDARIN')][1]");

        if (empty($hotelName)) {
            $hotelName = $this->re("/{$this->opt($this->t('Folio Letter for'))}\s*(.+)/", $this->subject);
        }

        if (!empty($hotelName)) {
            if (preg_match("/$hotelName\,?\n*(?<address>.+)\n\s*{$this->opt($this->t('Telephone'))}\:?\s*(?<phone>[+\-\(\)\d\s]+)/iu", $text, $m)
            || preg_match("/$hotelName\,?.*\n*(?<address>.+)\n(?:.{10,}\n)?\s*{$this->opt($this->t('Telephone'))}\:?\s*(?<phone>[+\-\(\)\d\s]+)/iu", $text, $m)) {
                $h->hotel()
                    ->name($hotelName)
                    ->address($m['address'])
                    ->phone($m['phone']);
            }
        } elseif (preg_match("/\n\n *(?<name>Mandarin Oriental, [^,\n]+?)[,\n] *(?<address>.+)\n *Telephone: *(?<phone>[+\-\(\)\d\s]+).*\n.*mandarinoriental\.com/iu", $text, $m)) {
            $h->hotel()
                ->name($m['name'])
                ->address($m['address'])
                ->phone($m['phone']);
        }

        $h->booked()
            ->checkIn(strtotime($this->re("/{$this->opt($this->t('Arrival'))}\s*\:?\s*(\d.+)/", $text)))
            ->checkOut(strtotime($this->re("/{$this->opt($this->t('Departure'))}\s*\:?\s*(\d.+)/", $text)));

        $roomType = $this->re("/{$this->opt($this->t('Room Type'))}\s*\:?\s*(.+)/u", $text);

        if (!empty($roomType)) {
            $room = $h->addRoom();
            $room->setType($roomType);
        }

        $roomDescription = $this->re("/{$this->opt($this->t('Room Number'))}\s*\:?\s*(.+)/u", $text);

        if (!empty($roomDescription)) {
            if (isset($room)) {
                $room->setDescription($roomDescription);
            } else {
                $h->addRoom()->setDescription($roomDescription);
            }
        }

        $account = $this->re("/{$this->opt($this->t('Account'))}\s*\:?\s*([\d\-]{5,})/", $text);

        if (!empty($account)) {
            $h->program()
                ->account($account, false);
        }
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->subject = $parser->getSubject();
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));
            $this->AssignLang($text);
            $this->ParseHotelPDF($email, $text);
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

    private function TableHeadPos($row)
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

    private function AssignLang($text)
    {
        foreach ($this->detectLang as $lang => $words) {
            foreach ($words as $word) {
                if (stripos($text, $word) !== false) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }
    }
}
