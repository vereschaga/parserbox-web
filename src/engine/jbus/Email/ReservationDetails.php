<?php

namespace AwardWallet\Engine\jbus\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class ReservationDetails extends \TAccountChecker
{
    public $mailFiles = "jbus/it-62385817.eml";

    public $lang = '';

    public static $dictionary = [
        'ja' => [
            'reservationDetails' => ['予約内容'],
            'confNumber'         => ['予約番号'],
            'cancelledTexts'     => '下記の取消予約内容をご確認下さい。',
        ],
    ];

    private $subjects = [
        'ja' => ['（予約取消確認）'],
    ];

    private $detectors = [
        'ja' => ['下記の取消予約内容をご確認下さい。'],
    ];

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@mail.j-bus.co.jp') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (self::detectEmailFromProvider($headers['from']) !== true) {
            return false;
        }

        foreach ($this->subjects as $phrases) {
            foreach ((array) $phrases as $phrase) {
                if (is_string($phrase) && stripos($headers['subject'], $phrase) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $textPlain = $parser->getPlainBody();

        if (self::detectEmailFromProvider($parser->getHeader('from')) !== true
            && strpos($textPlain, '@mail.j-bus.co.jp') === false
        ) {
            return false;
        }

        return $this->detectBody($textPlain) && $this->assignLang($textPlain);
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $textPlain = $parser->getPlainBody();

        $this->assignLang($textPlain);

        if (empty($this->lang)) {
            $this->logger->debug("Can't determine a language!");

            return $email;
        }

        $this->parseBus($email, $textPlain);
        $email->setType('ReservationDetails' . ucfirst($this->lang));

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

    private function parseBus(Email $email, string $text): void
    {
        $text = str_replace('　', ' ', $text);

        $bus = $email->add()->bus();

        if (preg_match("/{$this->opt($this->t('cancelledTexts'))}/", $text)) {
            $bus->general()->cancelled();
        }

        $text = preg_replace("/[\s\S]*^[【 ]*{$this->opt($this->t('reservationDetails'))}[ 】]*\n([\s\S]+)/m", '$1', $text);

        if (preg_match("/^[ ]*({$this->opt($this->t('confNumber'))})[ ]*[:：]+[ ]*([A-Z\d]{5,})[ ]*$/m", $text, $m)) {
            $bus->general()->confirmation($m[2], $m[1]);
        }

        if (preg_match("/^[ ]*{$this->opt($this->t('料  金'))}[ ]*[:：]+[ ]*(.+)$/m", $text, $matches)
            && preg_match('/^(?<amount>\d[,.\'\d]*)[ ]*(?<currency>[^\d)(]+)$/', $matches[1], $m)
        ) {
            // 2600円
            $bus->price()
                ->total($this->normalizeAmount($m['amount']))
                ->currency($this->normalizeCurrency($m['currency']));
        }

        if (!preg_match("/^[ ]*({$this->opt($this->t('往  路'))})[ ]*[:：]+[ ]*(?<date>.{6,}?)[ ]*(?<segments>(?:\n+.+→.+)+)/m", $text, $m)) {
            $this->logger->debug('Segments not found!');

            return;
        }

        $date = $this->normalizeDate($m['date']);

        $patterns['time'] = '\d{1,2}(?:[:：]\d{2})?(?:\s*[AaPp]\.?[Mm]\.?)?';

        // 新千歳空港(15:30)発 →ニセコアンヌプリ(18:26)着
        preg_match_all("/^[ ]*(?<nameDep>.+?)[ ]*\([ ]*(?<timeDep>{$patterns['time']})[ ]*\)[ ]*発[ ]*→[ ]*(?<nameArr>.+?)[ ]*\([ ]*(?<timeArr>{$patterns['time']})[ ]*\)[ ]*着[ ]*$/m", $m['segments'], $matches, PREG_SET_ORDER);

        foreach ($matches as $m) {
            $s = $bus->addSegment();
            $s->departure()->name($m['nameDep']);
            $s->arrival()->name($m['nameArr']);

            if ($date) {
                $s->departure()->date2($date . $m['timeDep']);
                $s->arrival()->date2($date . $m['timeArr']);
            }
        }
    }

    private function detectBody(?string $text): bool
    {
        if (empty($text) || !isset($this->detectors)) {
            return false;
        }

        foreach ($this->detectors as $phrases) {
            foreach ((array) $phrases as $phrase) {
                if (!is_string($phrase)) {
                    continue;
                }

                if (strpos($text, $phrase) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    private function assignLang(?string $text): bool
    {
        if (empty($text) || !isset(self::$dictionary, $this->lang)) {
            return false;
        }

        foreach (self::$dictionary as $lang => $phrases) {
            if (!is_string($lang) || empty($phrases['reservationDetails']) || empty($phrases['confNumber'])) {
                continue;
            }

            if ($this->strposArray($text, $phrases['reservationDetails']) !== false
                && $this->strposArray($text, $phrases['confNumber']) !== false
            ) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
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

    private function t(string $phrase)
    {
        if (!isset(self::$dictionary, $this->lang) || empty(self::$dictionary[$this->lang][$phrase])) {
            return $phrase;
        }

        return self::$dictionary[$this->lang][$phrase];
    }

    private function opt($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return '';
        }

        return '(?:' . implode('|', array_map(function ($s) {
            return preg_quote($s, '/');
        }, $field)) . ')';
    }

    /**
     * Dependencies `use AwardWallet\Engine\MonthTranslate` and `$this->lang`.
     */
    private function normalizeDate(?string $text): ?string
    {
        if (preg_match('/^(\d{4})[ ]*年[ ]*(\d{1,2})[ ]*月[ ]*(\d{1,2})[ ]*日$/', $text, $m)) {
            // 2019年02月02日
            $year = $m[1];
            $month = $m[2];
            $day = $m[3];
        }

        if (isset($day, $month, $year)) {
            if (preg_match('/^\s*(\d{1,2})\s*$/', $month, $m)) {
                return $m[1] . '/' . $day . ($year ? '/' . $year : '');
            }

            if (($monthNew = MonthTranslate::translate($month, $this->lang)) !== false) {
                $month = $monthNew;
            }

            return $day . ' ' . $month . ($year ? ' ' . $year : '');
        }

        return null;
    }

    /**
     * Formatting over 3 steps:
     * 11 507.00  ->  11507.00
     * 2,790      ->  2790    |    4.100,00  ->  4100,00    |    1'619.40  ->  1619.40
     * 18800,00   ->  18800.00  |  2777,0    ->  2777.0.
     *
     * @param string $s Unformatted string with amount
     * @param string|null $decimals Symbols floating-point when non-standard decimals (example: 1,258.943)
     */
    private function normalizeAmount(string $s, ?string $decimals = null): ?float
    {
        if (!empty($decimals) && preg_match_all('/(?:\d+|' . preg_quote($decimals, '/') . ')/', $s, $m)) {
            $s = implode('', $m[0]);

            if ($decimals !== '.') {
                $s = str_replace($decimals, '.', $s);
            }
        } else {
            $s = preg_replace('/\s+/', '', $s);
            $s = preg_replace('/[,.\'](\d{3})/', '$1', $s);
            $s = preg_replace('/,(\d{1,2})$/', '.$1', $s);
        }

        return is_numeric($s) ? (float) $s : null;
    }

    /**
     * @param string $string Unformatted string with currency
     */
    private function normalizeCurrency(string $string): string
    {
        $string = trim($string);
        $currences = [
            // do not add unused currency!
            'JPY' => ['円'],
        ];

        foreach ($currences as $currencyCode => $currencyFormats) {
            foreach ($currencyFormats as $currency) {
                if ($string === $currency) {
                    return $currencyCode;
                }
            }
        }

        return $string;
    }
}
