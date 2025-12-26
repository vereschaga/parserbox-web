<?php

namespace AwardWallet\Engine\jetstar\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Engine\WeekTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class BookingConfirmation extends \TAccountChecker
{
    public $mailFiles = "jetstar/it-115431656.eml, jetstar/it-724995235-zh.eml, jetstar/it-726048996-ja.eml";
    public $subjects = [
        'ジェットスター ご予約の確認', // ja
        '捷星预订确认', // zh
        '捷星預訂確認', // zh
        'Jetstar Booking Confirmation Email',
    ];

    public $lang = '';
    public $date;

    public static $dictionary = [
        "ja" => [
            'Booking reference:'           => 'ご予約番号:',
            'Your itinerary is on its way' => '旅程表は24時間以内にお送りいたします',
            'Passenger Information'        => '搭乗者名',
            'Flight details'               => 'フライト',
            'Flight number'                => '便名',
        ],
        "zh" => [
            'Booking reference:'           => ['预订编号:', '訂票號碼:'],
            'Your itinerary is on its way' => ['正在发送您的行程单', '你的行程表正在發送中'],
            'Passenger Information'        => ['乘客信息', '乘客資料'],
            'Flight details'               => ['航班详细信息', '航班資料'],
            'Flight number'                => ['航班号', '航班編號'],
        ],
        "en" => [
            // 'Booking reference:' => '',
            'Your itinerary is on its way' => 'Your itinerary is on its way',
            // 'Passenger Information' => '',
            'Flight details' => 'Flight details',
            // 'Flight number' => '',
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@jetstar.com') !== false) {
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
        if ($this->http->XPath->query("//text()[contains(normalize-space(),'Jetstar Airways Pty Ltd')]")->length === 0) {
            return false;
        }

        return $this->assignLang();
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]jetstar\.com$/', $from) > 0;
    }

    public function ParseFlight(Email $email, $html): void
    {
        $patterns = [
            'time'          => '\b\d{1,2}[:：]\d{2}(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?)?', // 4:19PM  |  2:00 p. m.
            'date'          => '.*\d.*', // Thu 09 Jun  |  2024年10月30日
            'travellerName' => '[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]]', // Mr. Hao-Li Huang
        ];

        $f = $email->add()->flight();

        $f->general()->confirmation($this->http->FindSingleNode("//text()[{$this->eq($this->t('Booking reference:'))}]/ancestor::tr[1]", null, true, "/{$this->opt($this->t('Booking reference:'))}\s*([A-Z\d]{5,})$/"));

        $travellers = [];
        $travellersHtml = $this->re("/{$this->opt($this->t('Passenger Information'))}(.+?){$this->opt($this->t('Flight details'))}/s", $html);
        $travellersHtml = str_replace('<span style="padding-left: 5px;"></span>', " ", $travellersHtml);
        $travellersText = $this->htmlToText($travellersHtml);
        $travellersRows = preg_split('/([ ]*\n[ ]*)+/', $travellersText);

        foreach ($travellersRows as $tRow) {
            if (preg_match("/^{$patterns['travellerName']}$/u", $tRow) > 0) {
                $travellers[] = $tRow;
            } else {
                $travellers = [];

                break;
            }
        }

        if (count($travellers) > 0) {
            $f->general()->travellers($travellers, true);
        }

        $nodes = $this->http->XPath->query("//text()[{$this->eq($this->t('Flight number'))}]/ancestor::table[1]/descendant::tr[not({$this->contains($this->t('Flight number'))})]");

        foreach ($nodes as $root) {
            $s = $f->addSegment();

            $flight = implode("\n", $this->http->FindNodes("*[normalize-space()][1]/descendant::text()[normalize-space()]", $root));

            if (preg_match("/^(?<name>[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(?<number>\d+)/", $flight, $m)) {
                $s->airline()->name($m['name'])->number($m['number']);
            }

            /*
                BNE
                3:20 PM
                Thu 09 Jun

                    [OR]

                東京（成田） (NRT)
                22:10
                2024年10月30日（水）
            */
            $pattern = "/^(?<airport>.{3,})\s+(?<time>{$patterns['time']})\s+(?<date>{$patterns['date']})\s*$/u";

            $departure = implode("\n", $this->http->FindNodes("*[normalize-space()][2]/descendant::text()[normalize-space()]", $root));

            if (preg_match($pattern, $departure, $m)) {
                if (preg_match("/^(?<name>.{2,}?)\s*\(\s*(?<code>[A-Z]{3})\s*\)$/", $m['airport'], $m2)) {
                    $s->departure()->name($m2['name'])->code($m2['code']);
                } elseif (preg_match("/^[(\s]*([A-Z]{3})[\s)]*$/", $m['airport'], $m2)) {
                    $s->departure()->code($m2[1]);
                }

                $s->departure()->date(strtotime(preg_replace('/^0:/', '12:', $m['time']), $this->normalizeDate($m['date'])));
            }

            $arrival = implode("\n", $this->http->FindNodes("*[normalize-space()][3]/descendant::text()[normalize-space()]", $root));

            if (preg_match($pattern, $arrival, $m)) {
                if (preg_match("/^(?<name>.{2,}?)\s*\(\s*(?<code>[A-Z]{3})\s*\)$/", $m['airport'], $m2)) {
                    $s->arrival()->name($m2['name'])->code($m2['code']);
                } elseif (preg_match("/^[(\s]*([A-Z]{3})[\s)]*$/", $m['airport'], $m2)) {
                    $s->arrival()->code($m2[1]);
                }

                $s->arrival()->date(strtotime(preg_replace('/^0:/', '12:', $m['time']), $this->normalizeDate($m['date'])));
            }
        }
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();

        $html = $parser->getHTMLBody();
        $this->date = strtotime($parser->getDate());
        $this->ParseFlight($email, $html);

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

    private function contains($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';

            return 'contains(normalize-space(' . $node . '),' . $s . ')';
        }, $field)) . ')';
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

    private function assignLang(): bool
    {
        if (!isset(self::$dictionary, $this->lang)) {
            return false;
        }

        foreach (self::$dictionary as $lang => $phrases) {
            if (!is_string($lang) || empty($phrases['Your itinerary is on its way']) || empty($phrases['Flight details'])) {
                continue;
            }

            if ($this->http->XPath->query("//text()[{$this->contains($phrases['Your itinerary is on its way'])}]")->length > 0
                && $this->http->XPath->query("//text()[{$this->contains($phrases['Flight details'])}]")->length > 0
            ) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function normalizeDate($date)
    {
        $year = date('Y', $this->date);
        $in = [
            '/^([-[:alpha:]]+)[,.\s]*(\d{1,2})[,.\s]*([[:alpha:]]+)$/u', // Fri 22 Oct
            '/^(\d{4})\s*年\s*(\d{1,2})\s*月\s*(\d{1,2})(?:\b|\D).*$/', // 2024年10月30日
        ];
        $out = [
            "$1 $2 $3 $year",
            '$1-$2-$3',
        ];
        $date = preg_replace($in, $out, $date);

        if (preg_match("/^\d{1,2}\s+([[:alpha:]]+)\s+\d{4}$/u", $date, $m)) {
            if (($en = MonthTranslate::translate($m[1], $this->lang))
                || ($en = MonthTranslate::translate($m[1], 'en'))
            ) {
                $date = str_replace($m[1], $en, $date);
            }
        }

        if (preg_match("/^(?<week>[-[:alpha:]]+)\s*(?<date>\d{1,2} [[:alpha:]]+ .+)/u", $date, $m)) {
            $weeknum = WeekTranslate::number1(WeekTranslate::translate($m['week'], $this->lang))
                ?? WeekTranslate::number1(WeekTranslate::translate($m['week'], 'en'));

            return EmailDateHelper::parseDateUsingWeekDay($m['date'], $weeknum);
        }

        return strtotime($date);
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
}
