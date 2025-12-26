<?php

namespace AwardWallet\Engine\s7\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class AutomaticRegistration extends \TAccountChecker
{
    public $mailFiles = "s7/it-480132218-ru.eml";

    public $lang = '';

    public static $dictionary = [
        'ru' => [
            'confNumber' => ['Бронь'],
            'departure'  => ['Время вылета по расписанию'],
        ],
    ];

    private $subjects = [
        'ru' => ['Автоматическая регистрация'],
    ];

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@s7.ru') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->detectEmailFromProvider(rtrim($headers['from'], '> ')) !== true) {
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
        if ($this->http->XPath->query('//a[contains(@href,".s7.ru/") or contains(@href,"www.s7.ru")]')->length === 0
            && $this->http->XPath->query('//*[contains(normalize-space(),"Мобильное приложение S7 Airlines")]')->length === 0
        ) {
            return false;
        }

        return $this->assignLang();
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();

        if (empty($this->lang)) {
            $this->logger->debug("Can't determine a language!");

            return $email;
        }
        $email->setType('AutomaticRegistration' . ucfirst($this->lang));

        $patterns = [
            'time'          => '\d{1,2}[:：]\d{2}(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?)?', // 4:19PM    |    2:00 p. m.
            'travellerName' => '[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]]', // Mr. Hao-Li Huang
        ];

        $youSeatsText = $this->htmlToText($this->http->FindHTMLByXpath("//text()[ {$this->starts($this->t('Рейс'))} and not({$this->eq($this->t('Рейс'))}) and following::text()[normalize-space()][1][contains(.,'→')] ]/ancestor::*[../self::tr][1]"));
        $seatsText = '';

        $f = $email->add()->flight();
        $s = $f->addSegment();

        $confirmation = $this->http->FindSingleNode("//tr[{$this->eq($this->t('confNumber'))}]/following-sibling::tr[normalize-space()][1]", null, true, '/^[A-Z\d]{5,}$/');

        if ($confirmation) {
            $confirmationTitle = $this->http->FindSingleNode("//tr[{$this->eq($this->t('confNumber'))} and following-sibling::tr[normalize-space()]]", null, true, '/^(.+?)[\s:：]*$/u');
            $f->general()->confirmation($confirmation, $confirmationTitle);
        }

        $flight = $this->http->FindSingleNode("//tr[{$this->eq($this->t('Рейс'))}]/following-sibling::tr[normalize-space()][1]");

        if (preg_match("/^(?<name>[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(?<number>\d+)$/", $flight, $m)) {
            $s->airline()->name($m['name'])->number($m['number']);

            if (preg_match("/^{$this->opt($this->t('Рейс'))}[ ]+{$this->opt([$m['name'] . $m['number'], $m['name'] . ' ' . $m['number']])}[ ]*\n+[ ]*(?<airport1>.{3,}?)[ ]+[-–→]+[ ]+(?<airport2>.{3,}?)[ ]*(?:\n+(?<seats>[\s\S]+))?$/", $youSeatsText, $m2)) {
                if (preg_match($pattern = "/(?:^|[ (])([A-Z]{3})[) ]*$/", $m2['airport1'], $m3)) {
                    // Москва (DME)
                    $s->departure()->code($m3[1]);
                } else {
                    $s->departure()->name($m2['airport1']);
                }

                if (preg_match($pattern, $m2['airport2'], $m3)) {
                    $s->arrival()->code($m3[1]);
                } else {
                    $s->arrival()->name($m2['airport2']);
                }

                $seatsText = $m2['seats'];
            }
        }

        $travellers = $seats = [];
        $seatsRows = preg_split("/[ ]*\n+[ ]*/", $seatsText);

        foreach ($seatsRows as $sRow) {
            // • Vadim Shchelokov — место 10C
            if (preg_match("/^[-–•> ]*({$patterns['travellerName']})(?:[ ]*[-–—]+[ ]*{$this->opt($this->t('место'))}[ ]*(\d+[A-Z]))?[,; ]*$/iu", $sRow, $m)) {
                $travellers[] = $m[1];

                if (!empty($m[2])) {
                    $seats[] = $m[2];
                }
            } else {
                $travellers = $seats = [];
                $this->logger->debug('Wrong seat row!');

                break;
            }
        }

        if (count($travellers) > 0) {
            $f->general()->travellers($travellers, true);
        }

        if (count($seats) > 0) {
            $s->extra()->seats($seats);
        }

        $dateDepVal = $this->http->FindSingleNode("//tr[{$this->eq($this->t('departure'))}]/following-sibling::tr[normalize-space()][1]");

        if (preg_match("/^(?<time>{$patterns['time']})(?:\s*,\s*)+(?<date>.{6,})$/", $dateDepVal, $m)) {
            // 15:05, 17 авг. 2023
            $dateDep = strtotime($this->normalizeDate($m['date']));

            if ($dateDep) {
                $s->departure()->date(strtotime($m['time'], $dateDep));
                $s->arrival()->noDate();
            }
        }

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

    private function assignLang(): bool
    {
        if (!isset(self::$dictionary, $this->lang)) {
            return false;
        }

        foreach (self::$dictionary as $lang => $phrases) {
            if (!is_string($lang) || empty($phrases['confNumber']) || empty($phrases['departure'])) {
                continue;
            }

            if ($this->http->XPath->query("//tr[{$this->starts($phrases['confNumber'])}]")->length > 0
                && $this->http->XPath->query("//tr[{$this->starts($phrases['departure'])}]")->length > 0
            ) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
    }

    private function t(string $phrase, string $lang = '')
    {
        if (!isset(self::$dictionary, $this->lang)) {
            return $phrase;
        }

        if ($lang === '') {
            $lang = $this->lang;
        }

        if (empty(self::$dictionary[$lang][$phrase])) {
            return $phrase;
        }

        return self::$dictionary[$lang][$phrase];
    }

    private function eq($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';

            return 'normalize-space(' . $node . ')=' . $s;
        }, $field)) . ')';
    }

    private function starts($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';

            return 'starts-with(normalize-space(' . $node . '),' . $s . ')';
        }, $field)) . ')';
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

    private function htmlToText(?string $s, bool $brConvert = true): string
    {
        if (!is_string($s) || $s === '') {
            return '';
        }
        $s = str_replace("\r", '', $s);
        $s = preg_replace('/<!--.*?-->/s', '', $s); // comments

        if ($brConvert) {
            $s = preg_replace('/\s+/', ' ', $s);
            $s = preg_replace('/<[Bb][Rr]\b.*?\/?>/', "\n", $s); // only <br> tags
        }
        $s = preg_replace('/<[A-z][A-z\d:]*\b.*?\/?>/', '', $s); // opening tags
        $s = preg_replace('/<\/[A-z][A-z\d:]*\b[ ]*>/', '', $s); // closing tags
        $s = html_entity_decode($s);
        $s = str_replace(chr(194) . chr(160), ' ', $s); // NBSP to SPACE

        return trim($s);
    }

    /**
     * Dependencies `use AwardWallet\Engine\MonthTranslate` and `$this->lang`.
     *
     * @param string|null $text Unformatted string with date
     */
    private function normalizeDate(?string $text): ?string
    {
        if (preg_match('/^(\d{1,2})[.\s]+([[:alpha:]]+)[.\s]+(\d{4})$/u', $text, $m)) {
            // 17 авг. 2023
            $day = $m[1];
            $month = $m[2];
            $year = $m[3];
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
}
