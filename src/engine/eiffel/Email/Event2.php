<?php

namespace AwardWallet\Engine\eiffel\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class Event2 extends \TAccountChecker
{
	public $mailFiles = "eiffel/it-779849377.eml, eiffel/it-782969630.eml, eiffel/it-785007498.eml, eiffel/it-785785149.eml";
    public $subjects = [
        'Eiffel Tower ticket',
        'Eiffel Tower Ticket',
        'Billet Tour Eiffel',
        'Billete Torre Eiffel',
    ];

    public $detectLang = [
        'en' => ['Order'],
        'es' => ['Orden'],
        'fr' => ['Achat']
    ];

    public $ticketPdfNamePattern = "(?:tickets|Tickets|Billetes).*pdf";
    public $receiptPdfNamePattern = "(?:receipt|justificatif|Justificante).*pdf";

    public $lang = '';

    public static $dictionary = [
        'es' => [

        ],
        'fr' => [

        ],
        'en' => [

        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@toureiffel.paris') !== false) {
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
        $ticketsPdfs = $parser->searchAttachmentByName($this->ticketPdfNamePattern);
        $receiptPdfs = $parser->searchAttachmentByName($this->receiptPdfNamePattern);

        foreach ($receiptPdfs as $receipt){
            $this->assignLang(\PDF::convertToText($parser->getAttachmentBody($receipt)));
        }

        foreach ($ticketsPdfs as $pdf) {
            $ticketsText = \PDF::convertToText($parser->getAttachmentBody($pdf));

            $this->assignLang($ticketsText);

            if (strpos($ticketsText, $this->t('serviceclients@toureiffel.paris')) !== false
                && strpos($ticketsText, $this->t('Achat n°')) !== false
                && strpos($ticketsText, $this->t('Your ticket is personal and cannot be given nor sold.')) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]toureiffel\.paris$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $ticketsPdfs = $parser->searchAttachmentByName($this->ticketPdfNamePattern);
        $receiptPdfs = $parser->searchAttachmentByName($this->receiptPdfNamePattern);

        foreach ($ticketsPdfs as $ticket) {
            $ticketsText = \PDF::convertToText($parser->getAttachmentBody($ticket));

            foreach ($receiptPdfs as $receipt){
                $receiptText = \PDF::convertToText($parser->getAttachmentBody($receipt));

                $this->assignLang($receiptText);

                $this->Event2($email, $ticketsText, $receiptText);
            }
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function Event2(Email $email, $ticketsText, $receiptText)
    {
        $e = $email->add()->event();

        $ticketsText = preg_split("/((?:{$this->t('LET’S KEEP THEM CLEAN')}|serviceclients@toureiffel\.paris)\n+)/u", $ticketsText);

        $e->general()
            ->confirmation($this->re("/{$this->t('Achat')}\s*n\°\s*\:\s*(\d+)\s*/u", $ticketsText[0]), "{$this->t('Order')} n°");

        $purchaseDate = $this->re("/{$this->opt($this->t('Date d’achat'))}\s*\:\s*(\d+[\/\.\-]\d+[\/\.\-]\d+\s*\w+\s*[\d\:]+)[\s\n]*/u", $ticketsText[0]);

        if (preg_match("/^(?<startDate>\d+[\/\.\-]\d+[\/\.\-]\d+)\s*\D+\s*(?<startTime>[\d\:]+)$/u", $purchaseDate, $m)){
            $e->general()
                ->date(strtotime($this->normalizeDate($m['startDate']) . ' ' . $m['startTime']));
        }

        $e->type()
            ->event();

        $e->place()
            ->address('5 Av. Anatole France, 75007 Paris, France');

        $e->booked()
            ->noEnd();

        $adultsCount = 0;
        $kidsCount = 0;

        foreach ($ticketsText as $ticket){
            if (!empty($ticket)){
                $ticketText = $this->splitCols(preg_replace("/([\n\s]*(?:COMMENT UTILISER VOTRE E-TICKET|Imprimez intégralement votre billet sur feuille).+)$/su", '',  $ticket), [0, 50]);

                $traveller = $this->re("/(?:A|J|E)\n+\s*([[:alpha:]][-.\/\'’[:alpha:] ]*[[:alpha:]])[\n\s]+{$this->t('Type')}/u", $ticketText[1]);
                if (!empty($traveller)){
                    $e->general()
                        ->traveller($traveller, true);

                    $travellerType = $this->re("/\n*{$this->t('Type')}\s*\:\s*(.+)\n*/u", $ticketText[1]);

                    if (strpos($travellerType, 'adulte') !== false){
                        $adultsCount++;
                    } elseif (strpos($travellerType, 'enfant') !== false || strpos($travellerType, 'jeune') !== false){
                        $kidsCount++;
                    }
                }

                if(preg_match("/(.+)\n*(?:\d{18})?\n+(\d+[\/\.\-]\d+[\/\.\-]\d+\s*[\d\:]+)[\s\n]*/u", $ticketText[1], $m)){
                    $e->place()
                        ->name($m[1]);

                    if (preg_match("/^[\s\n]*(?<startDate>\d+[\/\.\-]\d+[\/\.\-]\d+)\s*(?<startTime>[\d\:]+)$/u", $m[2], $date)){
                        $e->booked()
                            ->start(strtotime($this->normalizeDate($date['startDate']) . ' ' . $date['startTime']));
                    }
                }
            }
        }

        if ($adultsCount > 0){
            $e->booked()
                ->guests($adultsCount);
        }

        if ($kidsCount > 0){
            $e->booked()
                ->kids($kidsCount);
        }


        $totalInfo = $this->re("/Total\s*TTC\s*([\d\.\,\']+\s*\D{1,3})\n+/", $receiptText);
        if (preg_match("/^(?<price>[\d\.\,\']+)\s*(?<currency>\D{1,3})$/", $totalInfo, $m)){
            $e->price()
                ->currency($this->normalizeCurrency($m['currency']))
                ->total(PriceHelper::parse($m['price'], $m['currency']));

            $costInfo = $this->re("/Total\s*HT\s*([\d\.\,\']+)\s*\D{1,3}\n+/", $receiptText);
            $e->price()
                ->cost(PriceHelper::parse($costInfo, $m['currency']));
        }

    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
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
        $head = array_filter(array_map('trim', explode("|", preg_replace("/\s{2,}/", "|", $row))));
        $pos = [];
        $lastpos = 0;

        foreach ($head as $word) {
            $pos[] = mb_strpos($row, $word, $lastpos, 'UTF-8');
            $lastpos = mb_strpos($row, $word, $lastpos, 'UTF-8') + mb_strlen($word, 'UTF-8');
        }

        return $pos;
    }

    private function assignLang($text)
    {
        foreach ($this->detectLang as $lang => $dBody) {
            foreach ($dBody as $word) {
                if (stripos($text, $word) !== false) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function normalizeCurrency($string)
    {
        $string = trim($string);
        $currencies = [
            'EUR' => ['€'],
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

    private function normalizeDate($str)
    {
        $in = [
            "#^(\d{2})\/(\d{2})\/(\d{2})$#", //01/01/25
            "#^(\d{2})\/(\d{2})\/(\d{4})$#" //01/01/2025
        ];
        $out = [
            '$1.$2.20$3',
            '$1.$2.$3'
        ];
        $str = preg_replace($in, $out, $str);

        /*if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }*/

        return $str;
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
                return str_replace(' ', '\s+', preg_quote($s, '/'));
            }, $field)) . ')';
    }
}
