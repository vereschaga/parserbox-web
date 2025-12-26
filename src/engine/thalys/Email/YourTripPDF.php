<?php

namespace AwardWallet\Engine\thalys\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class YourTripPDF extends \TAccountChecker
{
    public $mailFiles = "thalys/it-272516094.eml, thalys/it-272516260.eml";
    public $lang = '';
    public $pdfNamePattern = ".*pdf";

    public $detectLang = [
        'fr' => ["CONDITIONS D'ACCÈS AU TRAIN", 'CONDITIONS D’ACCÈS AU TRAIN'],
        'en' => ['ACCESS CONDITIONS TO THE TRAIN'],
    ];

    public static $dictionary = [
        "fr" => [
            'YOUR TRIP'                      => 'VOTRE VOYAGE',
            'ACCESS CONDITIONS TO THE TRAIN' => ["CONDITIONS D'ACCÈS AU TRAIN", 'CONDITIONS D’ACCÈS AU TRAIN'],
            'PASSENGER'                      => 'PASSAGER',
            'TICKET ISSUE DATE'              => "DATE D'ÉMISSION DU BILLET",
            'TRAVEL DATE'                    => 'DATE DE VOYAGE',
            'REFERENCE/PNR'                  => 'RÉF. VOYAGE/ PNR',
            'PRICE INCL. VAT'                => 'PRIX TTC',
            'DEPARTURE AT'                   => 'DÉPART À',
            'ARRIVAL AT'                     => 'ARRIVÉE À',
            'TRAIN N°'                       => 'N° DE TRAIN',
            'CLASS'                          => 'CLASSE',
            'COACH'                          => 'VOITURE',
            'SEAT'                           => 'SIÈGE',
        ],
        "en" => [
            'YOUR TRIP'                      => 'YOUR TRIP',
            'ACCESS CONDITIONS TO THE TRAIN' => 'ACCESS CONDITIONS TO THE TRAIN',
        ],
    ];
    private $providerCode = '';

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        foreach ($pdfs as $pdf) {
            $textPdf = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (empty($textPdf) || !$this->assignProvider($textPdf)) {
                continue;
            }

            if ($this->assignLang($textPdf)) {
                return true;
            }
        }

        return false;
    }

    public function ParseTrainPDF(Email $email, $text): void
    {
        $patterns = [
            'time'          => '\d{1,2}[:：]\d{2}(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?)?', // 4:19PM    |    2:00 p. m.
            'travellerName' => '[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]]', // Mr. Hao-Li Huang
        ];

        $t = $email->add()->train();

        $t->general()->traveller($this->re("/{$this->opt($this->t('PASSENGER'))}\n+\s*({$patterns['travellerName']})\n+\s*{$this->opt($this->t('TICKET ISSUE DATE'))}/u", $text));

        $date = 0;

        if (preg_match("/\s*{$this->opt($this->t('TICKET ISSUE DATE'))}\s*{$this->opt($this->t('TRAVEL DATE'))}\s*(?<confTitle>{$this->opt($this->t('REFERENCE/PNR'))})\n+[ ]*{$this->opt($this->t('YOUR TRIP'))}\s*(?<date>[\d.\/]+)\s*(?<depDate>[\d.\/]+)\s*(?<conf>[A-Z\d]{6})\n/", $text, $m)) {
            $t->general()
                ->date(strtotime(str_replace('/', '.', $m['date'])))
                ->confirmation($m['conf'], $m['confTitle']);

            $date = strtotime(str_replace('/', '.', $m['depDate']));
        }

        if (preg_match("/{$this->opt($this->t('PRICE INCL. VAT'))}\n.+\s(?<total>[\d\.\,]+)\s*(?<currency>[A-Z]{3})/", $text, $m)) {
            $t->price()
                ->total(PriceHelper::parse($m['total'], $m['currency']))
                ->currency($m['currency']);
        }

        $stationInfo = $this->re("/{$this->opt($this->t('YOUR TRIP'))}.+\n+((?:.+\n*){2,10})/", $text);

        $tablePos = [0];

        if (preg_match("/^(.+? ){$this->opt($this->t('ARRIVAL AT'))}/m", $stationInfo, $matches)) {
            $tablePos[] = mb_strlen($matches[1]);
        }

        if (preg_match("/^(.+? ){$this->opt($this->t('PRICE INCL. VAT'))}/m", $stationInfo, $matches)) {
            $tablePos[] = mb_strlen($matches[1]);
        }

        $table = $this->SplitCols($stationInfo, $tablePos);

        if (count($table) < 2) {
            $this->logger->debug('Wrong main table!');

            return;
        }

        $s = $t->addSegment();

        if (preg_match("/^\s*(?<depName>[\s\S]{2,}?)\n+[ ]*{$this->opt($this->t('DEPARTURE AT'))}\n+[ ]*(?<depTime>{$patterns['time']})\n/u", $table[0], $m)) {
            $s->departure()
                ->name(preg_replace(['/(\/)\n+[ ]*/', '/\s+/'], ['$1', ' '], $m['depName']))
                ->date(strtotime($m['depTime'], $date));
        }

        if (preg_match("/^\s*(?<arrName>[\s\S]{2,}?)\n+[ ]*{$this->opt($this->t('ARRIVAL AT'))}\n+[ ]*(?<arrTime>{$patterns['time']})\n/u", $table[1], $m)) {
            $s->arrival()
                ->name(preg_replace(['/(\/)\n+[ ]*/', '/\s+/'], ['$1', ' '], $m['arrName']))
                ->date(strtotime($m['arrTime'], $date));
        }

        $trainInfo = $this->re("/\n(\s*{$this->opt($this->t('TRAIN N°'))}\s*{$this->opt($this->t('CLASS'))}\s*{$this->opt($this->t('COACH'))}\s*{$this->opt($this->t('SEAT'))}.+(?:\n.+)?\s*\d{2,}.+)/u", $text);
        $trainInfo = preg_replace("/^\s*\n+/", "", $trainInfo);
        $table = $this->SplitCols($trainInfo);
        $s->setNumber($this->re("/{$this->opt($this->t('TRAIN N°'))}\s*(\d+)$/u", $table[0]));

        $cabin = $this->re("/{$this->opt($this->t('CLASS'))}\s*(\d+)$/su", $table[1]);

        if (!empty($cabin)) {
            $s->extra()
                ->cabin($cabin);
        }

        $coach = $this->re("/{$this->opt($this->t('COACH'))}\s*(\d+)$/su", $table[2]);

        if (!empty($coach)) {
            $s->setCarNumber($coach);
        }

        $seat = $this->re("/{$this->opt($this->t('SEAT'))}\s*(\d+)$/su", $table[3]);

        if (!empty($seat)) {
            $s->extra()
                ->seat($seat);
        }
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $textPdfFull = '';

        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (empty($text)) {
                continue;
            }

            if ($this->assignLang($text)) {
                $textPdfFull .= $text . "\n\n";
            }

            $this->ParseTrainPDF($email, $text);
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        $this->assignProvider($textPdfFull);
        $email->setProviderCode($this->providerCode);

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

    public static function getEmailProviders()
    {
        return ['eurostar', 'thalys'];
    }

    private function assignProvider(string $text): bool
    {
        if (strpos($text, 'Eurostar') !== false || strpos($text, 'EUROSTAR') !== false) {
            $this->providerCode = 'eurostar';

            return true;
        }

        if (strpos($text, 'Thalys') !== false || strpos($text, 'THALYS') !== false) {
            $this->providerCode = 'thalys';

            return true;
        }

        return false;
    }

    private function assignLang(?string $text): bool
    {
        if (empty($text) || !isset(self::$dictionary, $this->lang)) {
            return false;
        }

        foreach (self::$dictionary as $lang => $phrases) {
            if (!is_string($lang) || empty($phrases['YOUR TRIP']) || empty($phrases['ACCESS CONDITIONS TO THE TRAIN'])) {
                continue;
            }

            if ($this->strposArray($text, $phrases['YOUR TRIP']) !== false
                && $this->strposArray($text, $phrases['ACCESS CONDITIONS TO THE TRAIN']) !== false
            ) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
    }

    private function re($re, $str, $c = 1): ?string
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

    private function opt($field): string
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s, '/'));
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

    private function SplitCols(?string $text, ?array $pos = null): array
    {
        $cols = [];
        $rows = explode("\n", $text);

        if (!$pos) {
            $pos = $this->TableHeadPos($rows[0]);
        }
        arsort($pos);

        foreach ($rows as $row) {
            foreach ($pos as $k=>$p) {
                $cols[$k][] = rtrim(mb_substr($row, $p, null, 'UTF-8'));
                $row = mb_substr($row, 0, $p, 'UTF-8');
            }
        }
        ksort($cols);

        foreach ($cols as &$col) {
            $col = implode("\n", $col);
        }

        return $cols;
    }

    private function strposArray(?string $text, $phrases, bool $reversed = false)
    {
        if (empty($text)) {
            return false;
        }

        foreach ((array) $phrases as $phrase) {
            if (!is_string($phrase)) {
                continue;
            }
            $result = $reversed ? strrpos($text, $phrase) : strpos($text, $phrase);

            if ($result !== false) {
                return $result;
            }
        }

        return false;
    }
}
