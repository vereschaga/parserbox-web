<?php

namespace AwardWallet\Engine\japanair\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Engine\WeekTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class NoticeOfTicketing extends \TAccountChecker
{
    public $mailFiles = "japanair/it-35463300.eml, japanair/it-35749825.eml, japanair/it-35749978.eml, japanair/it-35811283.eml, japanair/it-35821135.eml, japanair/it-41901782.eml, japanair/it-42565269.eml, japanair/it-46254877.eml";

    public $reFrom = ['jalonline_auto@jal.com', 'JMB_AUTO@jal.com', 'auto@jal.co.jp'];
    public $reBody = [
        'ja'  => ['フライト詳細', '予約番号'],
        'ja2' => ['ご予約ありがとうございます', '予約番号'],
        'ja3' => ['ご利用ありがとうございます', '予約番号'],
        'ja4' => ['ご利用ありがと', '確認番号'],
        'ja5' => ['便情報', 'ご搭乗案内'],
        'en'  => ['Reserved flight details', 'Reservation no.'],
    ];
    public $reSubject = [
        'JALオンライン★発券内容のお知らせ★',
        'JAL国内線',
    ];
    public $lang = 'ja';
    public static $dict = [
        'ja' => [
            '航空券情報'    => ['航空券情報', '航空券番号', '航空券のご購入について'],
            'paxStart' => ['お客様氏名', 'ご予約ありがとうございます', 'ご利用ありがとうございます', '搭乗者'],
            'paxEnd'   => ['予約番号', 'この度はJALグループをお選びくださいまして', '予約番号'],
        ],
        'en' => [
            '予約番号'   => 'Reservation no.',
            'フライト詳細' => 'Reserved flight details',
            '合計'     => 'Total price:',
            //            '引き落としマイル数' => '',
            //            '航空券情報' => [''],
            'paxStart' => ['Names of passengers'],
            'paxEnd'   => ['Reservation no.'],
        ],
    ];
    private $keywordProv = 'JAL';
    private $date;

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $textBody = $parser->getPlainBody();
        $this->assignLang($textBody);

        if (empty($this->lang)) {
            $this->logger->debug("Can't determine a language!");

            return $email;
        }

        $this->date = strtotime($parser->getDate());
        $text = text($textBody);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        $this->parseEmail($email, $text);

        return $email;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query('//node()[contains(normalize-space(),"JAL Sky") or contains(.,"www.jal.co.jp")]')->length === 0
            && $this->http->XPath->query('//a[contains(@href,"//www.jal.co.jp")]')->length === 0
        ) {
            return false;
        }

        return $this->assignLang($parser->getPlainBody());
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
        if (self::detectEmailFromProvider($headers['from']) !== true
            && strpos($headers['subject'], $this->keywordProv) === false
        ) {
            return false;
        }

        if (isset($headers["subject"])) {
            foreach ($this->reSubject as $reSubject) {
                if (stripos($headers["subject"], $reSubject) !== false) {
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

    private function parseEmail(Email $email, string $text): void
    {
        $r = $email->add()->flight();
        $text = str_replace('>', '', $text);

        if (($reservationNumber = $this->re("#{$this->t('予約番号')}\s+([-A-Z\d]{3,})#", $text))) {
            $r->general()->confirmation($reservationNumber, $this->t('予約番号'), true);
        }

        if (preg_match("#\n[ ]*{$this->opt($this->t('フライト詳細'))}\n(.+?)\n[ ]*{$this->opt($this->t('航空券情報'))}#su", $text,
            $m)) {
            // it-35463300.eml
            $flights = $m[1];
        }

        if (!isset($flights)
            && preg_match("#\n[ ]*{$this->opt($this->t('フライト詳細'))}[ ]*\n(.+?)\n[ ]*{$this->opt($this->t('合計'))}#su",
                $text, $m)
        ) {
            // it-35749978.eml
            $flights = $m[1];
            $total = $this->re("#\n[ ]*{$this->opt($this->t('合計'))}\s+(.+)#", $text);

            if (preg_match("#^([\d\.\,]+)\s*円$#", $total, $m)) {
                $r->price()
                    ->total(PriceHelper::cost($m[1]))
                    ->currency('JPY');
            } elseif (preg_match("#^([\d\.\,]+)\s*yen$#i", $total, $m)) {
                $r->price()
                    ->total(PriceHelper::cost($m[1]))
                    ->currency('JPY');
            }
        }

        if (!isset($flights)
            && preg_match("#\n[ ]*{$this->opt($this->t('フライト詳細'))}[ ]*\n(.+?)\n[ ]*{$this->opt($this->t('引き落としマイル数'))}#su",
                $text, $m)
        ) {
            // it-35749825.eml
            $flights = $m[1];
            $total = $this->re("#\n[ ]*{$this->opt($this->t('引き落としマイル数'))}\s+(.+)#", $text);

            if (preg_match("#^([\d\.\,]+)\s*マイル$#", $total, $m)) {
                $r->price()
                    ->spentAwards(PriceHelper::cost($m[1]) . ' マイル');
            }
        }

        if (!isset($flights)
            && preg_match("#\n[ ]*{$this->opt($this->t('予約番号'))}[^\n]*\n(.+?)\n[ ]*{$this->opt($this->t('航空券情報'))}#s",
                $text, $m)
        ) {
            // it-35811283.eml
            $flights = $m[1];
        }

        if (!isset($flights)
            && preg_match("#\n[ ]*{$this->opt($this->t('予約番号'))}[^\n]*\n(.+?)\n[ ]*{$this->opt($this->t('ご案内'))}#s",
                $text, $m)
        ) {
            // it-35821135.eml
            $flights = $m[1];
        }

        if (!isset($flights)
            && preg_match("#{$this->opt($this->t('うございます'))}(.+?){$this->opt($this->t('お問い合わせ'))}#su",
                $text, $m)
        ) {
            // it-41901782.eml
            $flights = $m[1];
        }

        if (empty($flights)) {
            $this->logger->debug('empty $flights');

            return;
        }

        //$this->logger->debug($flights);

        $patterns['namePrefixes'] = '(?:様|MR|MS)';

        if (preg_match("#{$this->opt($this->t('航空券情報'))}\s+(?<pax>.+?)\s+{$this->t('確認番号')}:\s*(?<conf>.+?)\s+航空券番号:(?<tickets>.+?)予約作成者#us", $text, $m)) {
            $r->general()
                ->travellers(array_filter(array_map("trim", explode("\n", $m['pax']))), true);

            if (preg_match('/^[-A-z\d]+$/', $m['conf'])) {
                $r->general()->confirmation($m['conf'], $this->t('確認番号'));
            }

            if (preg_match_all("#区間\d\s*(\d{10,})$#m", $m['tickets'], $v)) {
                $r->issued()->tickets($v[1], false);
            }
        } elseif (preg_match("#{$this->opt($this->t('paxStart'))}[。\s]*([\s\S]+?)\s*{$this->opt($this->t('paxEnd'))}#u", $text, $m)
            || preg_match("#\n{3}([\s\S]+?)\s*{$this->opt($this->t('paxEnd'))}#u", $text, $m) && !preg_match('/\n{3}/', $m[1])
        ) {
            if (preg_match_all("/^[ ]*(.+?\s*{$patterns['namePrefixes']})(?:\s*\d{1,3}\s*歳)?[\s(]+確認番号[ ：:]+([-\w]{5,})(?:\)|[ ]*$)/mu", $m[1], $v)) {
                /*
                    KOBAYASHI HIROSHI 様 (確認番号 Z9965020)

                    or

                    KOBAYASHI HIROSHI  様　47歳
                    確認番号：Z9965020
                */
                foreach ($v[1] as $key => $value) {
                    $travellerName = preg_replace('/\s+/', ' ', $value);
                    $r->general()
                        ->traveller($travellerName, true)
                        ->confirmation($v[2][$key], $this->t('確認番号') . '(' . $travellerName . ')');
                }
            } elseif (preg_match_all("/^[ ]*([[:alpha:]][-.\'[:alpha:] ]*?[[:alpha:]]\s*{$patterns['namePrefixes']})[ ]*(?:\d{1,3}\s*歳|\(|$)/mu", $m[1], $v)) {
                // it-35821135.eml    |    NI MIK  様　47歳    |    KANAZ HAR 様(幼児)    |    HYLANDS STEVEN JAMES MR
                $r->general()->travellers(array_map(function ($item) { return preg_replace('/\s+/', ' ', $item); }, $v[1]), true);
            } else {
                // WTF?
//                $r->general()->travellers(array_map("trim", explode("\n", $m[1])), true);
            }
        }

        // 12,000マイル引き落とし
        if (preg_match("/([\d.,]+)マイル引き落とし/u", $text, $m)) {
            $r->price()->spentAwards($m[1]);
        }

        $segments = $this->splitter("#^([ ]*\(\d+\))#m", "ControlStr\n" . $flights);

        if (empty($segments)) {
            // it-35811283.eml, it-41901782.eml, it-42565269.eml
            $segments = $this->splitter("#^([ ]*\d+\/\d+.+?\s*\D{3}\d+\s*)#m", "ControlStr\n" . $flights);
        }

        if (empty($segments)) {
            // it-46254877.eml
            $segments = $this->splitter("#^([ ]*\d+\.[ ]+\w+\/\w+)#m", "ControlStr\n" . $flights);
        }

        if (empty($segments)) {
            $segments = $this->splitter("/^(.{6,}\d+[ ]*便)$/m", "ControlStr\n" . $flights);
        }

        foreach ($segments as $segment) {
            $s = $r->addSegment();
            /*
                2019/8/26
                JAL106
                大阪(伊丹) 8:30発
                東京(羽田) 9:40着
                普通席

                2019/9/25
                JAL913
                東京(羽田) 11:25発
                沖縄(那覇) 14:05着
                普通席
                41K,41H,41G
            */
            $regExp1 = "#^[ ]*(?:\(\d+\)\s+)?(?<date>\d+.+?)\s*(?<airline>\D{3})(?<flight>\d+)\s*便?\s+" .
                "(?<dep>.+?)\s*(?<depTime>\d+:\d+)\s*発?\s*(?<arr>.+?)\s*(?<arrTime>\d+:\d+)\s*着?\s+" .
                "(?:クラス\s+(?<bookingCode>[[:alpha:]]{1,2})[ ]*|普通席)(?:(?:[／ ]+座席番号|[ ]+座席指定)?\s*(?<seats>(?:\d+[A-z][ ]*[,.]*\s*)+))?(?:\n|$)#u";

            if (preg_match($regExp1, $segment, $m)) {
                // it-35463300.eml, it-35749825.eml, it-35749978.eml, it-35811283.eml, it-35821135.eml, it-41901782.eml, it-42565269.eml
                $this->logger->debug('Found segment type 1');

                if ($m['airline'] === 'JAL') {
                    $m['airline'] = 'JL';
                }
                $s->airline()
                    ->name($m['airline'])
                    ->number($m['flight']);

                $date = $this->normalizeDate($m['date']);
                $s->departure()
                    ->noCode()
                    ->date(strtotime($m['depTime'], $date))
                    ->name($m['dep']);

                $s->arrival()
                    ->noCode()
                    ->date(strtotime($m['arrTime'], $date))
                    ->name($m['arr']);

                if (!empty($m['bookingCode'])) {
                    if ($m['bookingCode'] === 'Ｊ') {
                        $m['bookingCode'] = 'J';
                        $s->extra()->bookingCode($m['bookingCode']);
                    }
                }

                if (!empty($m['seats'])) {
                    // it-35821135.eml
                    $s->extra()->seats(preg_split('/\s*[,.]\s*/', $m['seats']));
                }
            } else {
                /*
                1. JAL567/October 11
                   Departure: Tokyo (Haneda) 0:30 p.m.
                   Arrival: Memanbetsu 2:10 p.m.
                   Class J
                   Fare: TOKUBIN3 TypeC Advanced Purchase Fare/ 57,180
                */
                $regExp2 = "#^[ ]*\d+\.[ ]*(?<airline>\D{3})(?<flight>\d+)\/(?<date>.+?)\s+Departure:\s+(?<dep>.+?)\s+(?<depTime>\d+:\d+(?:\s*[ap]\.?m\.?)?)\s+Arrival:\s+(?<arr>.+?)\s+(?<arrTime>\d+:\d+(?:\s*[ap]\.?m\.?)?)\s+(?<cabin>.+)\s+Fare:#u";

                if (preg_match($regExp2, $segment, $m)) {
                    // it-46254877.eml
                    $this->logger->debug('Found segment type 2');

                    if ($m['airline'] === 'JAL') {
                        $m['airline'] = 'JL';
                    }
                    $s->airline()
                        ->name($m['airline'])
                        ->number($m['flight']);

                    $date = $this->normalizeDate($m['date']);
                    $s->departure()
                        ->noCode()
                        ->date(strtotime($this->normalizeTime($m['depTime']), $date))
                        ->name($m['dep']);

                    $s->arrival()
                        ->noCode()
                        ->date(strtotime($this->normalizeTime($m['arrTime']), $date))
                        ->name($m['arr']);

                    if (preg_match("#^Class\s+([A-Z]{1,2})$#", $m['cabin'], $v)) {
                        $s->extra()->bookingCode($v[1]);
                    } else {
                        $s->extra()->cabin($m['cabin']);
                    }
                }
            }
        }

        if (count($r->getConfirmationNumbers()) === 0 && $r->getNoConfirmationNumber() !== true
            && !preg_match("#{$this->opt($this->t('予約番号'))}#u", $flights)
            && preg_match("#{$this->opt($this->t('JALタッチ ゴーにてご搭乗いただけますので 当日は直接 保安検査場へお越しください'), true)}#u", $flights) > 0
        ) {
            $r->general()->noConfirmation();
        }
    }

    private function normalizeDate($date)
    {
        $year = date('Y', $this->date);
        $in = [
            // 5/16(木)
            '#^(\d+)\/(\d+)\((\w+)\)$#u',
            //3月28日(木)
            '#^(\d+)月(\d+)日\((\w+)\)$#u',
            //4月17日
            '#^(\d+)月(\d+)日$#u',
            //2019年4月6日
            '#^(\d{4})年(\d+)月(\d+)日$#u',
            // October 10
            '#^(\w+) (\d{1,2})$#',
            // 11/24
            '/^(\d{1,2})\/(\d{1,2})$/',
        ];
        $out = [
            $year . '-$1-$2',
            $year . '-$1-$2',
            $year . '-$1-$2',
            '$1-$2-$3',
            '$2 $1 ' . $year,
            '$1/$2',
        ];
        $outWeek = [
            '$3',
            '$3',
            '',
            '',
            '',
            '',
        ];

        if (!empty($week = preg_replace($in, $outWeek, $date))) {
            $weeknum = WeekTranslate::number1(WeekTranslate::translate($week, $this->lang));
            $str = $this->dateStringToEnglish(preg_replace($in, $out, $date));
            $str = EmailDateHelper::parseDateUsingWeekDay($str, $weeknum);
        } else {
            $date = $this->dateStringToEnglish(preg_replace($in, $out, $date));
            $str = EmailDateHelper::parseDateRelative($date, $this->date);
        }

        if (empty($str)) {
            return strtotime($date, false);
        }

        return $str;
    }

    private function normalizeTime($str)
    {
        //it-46254877.eml
        if (preg_match("#^0(:\d+\s*p\.m\.)#", $str, $m)) {
            $str = '12' . $m[1];
        }
        $str = str_replace(".", '', $str);

        return $str;
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function assignLang($body): bool
    {
        if (isset($this->reBody)) {
            foreach ($this->reBody as $lang => $reBody) {
                if (mb_stripos($body, $reBody[0]) !== false && mb_stripos($body, $reBody[1]) !== false) {
                    $this->lang = substr($lang, 0, 2);

                    return true;
                }
            }
        }

        return false;
    }

    private function dateStringToEnglish($date)
    {
        if (preg_match('#[[:alpha:]]+#iu', $date, $m)) {
            $monthNameOriginal = $m[0];

            if ($translatedMonthName = MonthTranslate::translate($monthNameOriginal, $this->lang)) {
                return preg_replace("#$monthNameOriginal#i", $translatedMonthName, $date);
            }
        }

        return $date;
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function splitter($regular, $text)
    {
        $result = [];

        $array = preg_split($regular, $text, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
        array_shift($array);

        for ($i = 0; $i < count($array) - 1; $i += 2) {
            $result[] = $array[$i] . $array[$i + 1];
        }

        return $result;
    }

    private function opt($field, bool $addSpaces = false): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return '';
        }

        return '(?:' . implode('|', array_map(function ($s) use ($addSpaces) {
            $s = str_replace(' ', '\s+', preg_quote($s));

            return $addSpaces ? $this->addSpacesWord($s) : $s;
        }, $field)) . ')';
    }

    private function addSpacesWord(?string $text): string
    {
        return preg_replace('/(\w)/u', '$1\s*', $text);
    }
}
