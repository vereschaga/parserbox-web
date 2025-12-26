<?php

namespace AwardWallet\Engine\jrg\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class Notification extends \TAccountChecker
{
    public $mailFiles = "jrg/it-502678077.eml, jrg/it-504891806.eml, jrg/it-768299325.eml";

    public $detectFrom = "@japanrailpass-reservation.net";
    public $detectSubject = [
        // en
        '[JAPAN RAIL PASS Reservation]Seat Reservation Complete Notification',
        '[JAPAN RAIL PASS Reservation]Seat Cancellation Notification',
        // zh
        '[JAPAN RAIL PASS Reservation]指定座席票預訂成功通知',
        '[JAPAN RAIL PASS Reservation]指定座席票預訂變更成功通知',
    ];

    public $lang;
    public static $dictionary = [
        'en' => [
            'Boarding Date' => 'Boarding Date',
            'Seat/Room'     => 'Seat/Room',
            'cancelledText' => 'reserved seat tickets have been canceled',
        ],
        'zh' => [
            'Dear'             => '先生/女士',
            'Boarding Date'    => '乘車日',
            'Train Name'       => '列車名',
            'Section'          => '區間',
            'Seat/Room'        => '設備',
            'Seating Location' => '座位位置',
            'Car No.'          => '第',
            // 'cancelledText' => '',
        ],
    ];

    public function detectEmailFromProvider($from)
    {
        return preg_match("/[@.]japanrailpass-reservation\.net$/", $from) > 0;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (stripos($headers["from"], $this->detectFrom) === false
            && strpos($headers["subject"], 'JAPAN RAIL PASS') === false
        ) {
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
        $text = $parser->getPlainBody();

        if (
            $this->http->XPath->query("//a[{$this->contains(['/japanrailpass.net/', '.japanrailpass-reservation.net/'], '@href')}]")->length === 0
            && $this->http->XPath->query("//*[{$this->contains(['(C) JAPAN RAILWAYS GROUP'])}]")->length === 0
            && $this->containsText($text, '(C) JAPAN RAILWAYS GROUP') === false
        ) {
            return false;
        }

        return $this->assignLang($text);
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $text = $parser->getPlainBody();
        $text = preg_replace("/^>+ /m", '', $text);
        $text = preg_replace("/\n *\xEF\xBB\xBF/", "\n", $text);
        $this->assignLang($text);

        if (empty($this->lang)) {
            $this->logger->debug("can't determine a language");

            return $email;
        }
        $this->parseEmail($email, $text);

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

    private function assignLang($text)
    {
        foreach (self::$dictionary as $lang => $dict) {
            if (
                !empty($dict['Boarding Date']) && $this->containsText($text, $dict['Boarding Date']) === true
                && !empty($dict['Seat/Room']) && $this->containsText($text, $dict['Seat/Room']) === true
            ) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
    }

    private function parseEmail(Email $email, $text)
    {
        $t = $email->add()->train();

        $t->general()
            ->noConfirmation();

        if (in_array($this->lang, ['zh'])) {
            $traveller = $this->re("/(?:^|\n)\s*([[:alpha:]][[:alpha:] \-]+) *{$this->opt($this->t('Dear'))}\s*\n/u", $text);
        } else {
            $traveller = $this->re("/(?:^|\n) *{$this->opt($this->t('Dear'))} +([[:alpha:]][[:alpha:] \-]+),\n/u", $text);
        }
        $t->general()
            ->traveller($traveller);

        if (preg_match("/{$this->opt($this->t('cancelledText'))}/ui", $text)) {
            $t->general()
                ->cancelled()
                ->status('Cancelled');
        }

        $segments = $this->split("/(\n *{$this->opt($this->t('Boarding Date'))}[：:])/u", $text);

        foreach ($segments as $sText) {
            $s = $t->addSegment();
            $date = $this->normalizeDate($this->re("/\n *{$this->opt($this->t('Boarding Date'))}[：:] *(.+)/u", $sText));

            if (preg_match("/\n *{$this->opt($this->t('Train Name'))}[：:] *(.+?) (\d+)\s*\n/u", $sText, $m)) {
                $s->extra()
                    ->service($m[1])
                    ->number($m[2]);
            }

            if (preg_match("/\n *{$this->opt($this->t('Section'))}[：:] *(?<dName>.+?) *[(（] *(?<dTime>\d+:\d+) *[)）] *⇒ *(?<aName>.+?) *[(（] *(?<aTime>\d+:\d+) *[)）]\s*\n/u", $sText, $m)) {
                $s->departure()
                    ->name($m['dName'])
                    ->geoTip('jp')
                    ->date($date ? strtotime($m['dTime'], $date) : null);

                $s->arrival()
                    ->name($m['aName'])
                    ->geoTip('jp')
                    ->date($date ? strtotime($m['aTime'], $date) : null);
            }

            if (preg_match("/\n *{$this->opt($this->t('Seating Location'))}[：:] *{$this->opt($this->t('Car No.'))}[^\S\n]*(\w+?)[^\S\n]*(?:號車)?[^\S\n]+(.+)\s*(?:\n|$)/u", $sText, $m)) {
                $s->extra()
                    ->car($m[1])
                    ->seats(preg_split("/\s+/u", trim($m[2])));
            }
        }

        return true;
    }

    private function t($s)
    {
        if (!isset($this->lang, self::$dictionary[$this->lang], self::$dictionary[$this->lang][$s])) {
            return $s;
        }

        return self::$dictionary[$this->lang][$s];
    }

    // additional methods

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

    private function contains($field, $text = 'normalize-space(.)')
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($text) {
            return 'contains(' . $text . ',"' . $s . '")';
        }, $field)) . ')';
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) {
            return 'normalize-space(.)="' . $s . '"';
        }, $field)) . ')';
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) {
            return 'starts-with(normalize-space(.),"' . $s . '")';
        }, $field)) . ')';
    }

    private function dateTranslate($date)
    {
        if (preg_match('/[[:alpha:]]+/iu', $date, $m)) {
            $monthNameOriginal = $m[0];

            if ($translatedMonthName = MonthTranslate::translate($monthNameOriginal, $this->lang)) {
                return preg_replace("/$monthNameOriginal/i", $translatedMonthName, $date);
            }
        }

        return $date;
    }

    private function normalizeDate(?string $date): ?int
    {
        // $this->logger->debug('date begin = ' . print_r( $date, true));
        $in = [
            // 10/17/2023(MM/DD/YYYY)
            // 08/02/2024（月/日/年）
            '/^\s*(\d{2})\\/(\d{2})\\/(\d{4})\s*(?:\(MM\\/DD\\/YYYY\)|（月\\/日\\/年）)\s*$/iu',
        ];
        $out = [
            '$2.$1.$3',
        ];

        $date = preg_replace($in, $out, $date);
        // $this->logger->debug('date replace = ' . print_r( $date, true));

        // $this->logger->debug('date end = ' . print_r( $date, true));

        return strtotime($date);
    }

    private function opt($field, $delimiter = '/')
    {
        $field = (array) $field;

        if (empty($field)) {
            $field = ['false'];
        }

        return '(?:' . implode("|", array_map(function ($s) use ($delimiter) {
            return preg_quote($s, $delimiter);
        }, $field)) . ')';
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

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }
}
