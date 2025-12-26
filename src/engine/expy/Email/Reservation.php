<?php

namespace AwardWallet\Engine\expy\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class Reservation extends \TAccountChecker
{
    public $mailFiles = "expy/it-35659863.eml, expy/it-35659905.eml, expy/it-787743619.eml, expy/it-788414431.eml, expy/it-793100033.eml, expy/it-793622147.eml";

    public $reFrom = ["@expy.jp"];
    public $reBody = [
        'ja' => ['をご利用いただきありがとうございます。'],
        'en' => ['Thank you for using this service'],
    ];
    public $reSubject = [
        // ja
        '【EX】 新幹線予約内容',
        '【EX】 新幹線予約変更内容',
        '【EX予約】 新幹線予約内容',
        '【スマートEX】 新幹線予約内容',
        // en
        '[Shinkansen Reservation Service] IC Card Designation Details',
        '[Shinkansen Reservation Service] Reservation Confirmation',
        '[Shinkansen Reservation Service] Reservation for Boarding Date of',
        '[Shinkansen Reservation Service] Reservation for Tomorrow (Boarding Date of',
        '[Shinkansen Reservation Service] Reservation Change Details',
        '[Shinkansen Reservation Service] Trains/seats fainalized',
    ];
    public $lang = '';
    public static $dict = [
        'en' => [
            'Reservation number'     => 'Reservation number',
            // 'tripNum'      => '',
            'Departure date:' => 'Departure date:',
            'Non-Smoking'     => 'Non-Smoking',
            'Car'             => 'Car.',
            'Seat'            => 'Seat.',
            'Total'           => 'Total', // segment total
            'Total is'        => 'Total is', // email total
            // 'Handling fees:'          => '',
        ],
        'ja' => [
            'Reservation number'      => 'お預かり番号',
            'tripNum'                 => '出張番号',
            'Departure date:'         => '乗車日',
            'issue'                   => '号',
            'number'                  => '番',
            'Non-Smoking'             => '普通　禁煙',
            'Car'                     => '号車',
            'Seat'                    => '席',
            'Total'                   => '発売額', // segment total
            'Total is'                => '領収額は、合計', // email total
            'Handling fees:'          => '手数料',
            '-Before change'          => '■お申込内容　申込日時',
        ],
    ];
    private $date;

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->date = strtotime($parser->getDate());
        $body = html_entity_decode($this->http->Response['body']);

        if (empty($body)) {
            $body = $parser->getPlainBody();
        }

        if (!$this->assignLang($body)) {
            $this->logger->debug('can\'t determine a language');

            return $email;
        }

        $type = '';
        $body = $parser->getPlainBody();
        $this->parseEmail($email, $body);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . $type . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $this->http->Response['body'];

        if (empty($body)) {
            $body = $body = $parser->getPlainBody();
        }

        if ($this->detectBody($body)) {
            return $this->assignLang($body);
        }

        return false;
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
        if (empty($headers['from']) || empty($headers['subject'])) {
            return false;
        }

        $fromProv = false;

        if (self::detectEmailFromProvider($headers['from']) === true
            || $this->stripos($headers["subject"], '[Shinkansen Reservation Service]')
            || $this->stripos($headers["subject"], '【EX予約】')
            || $this->stripos($headers["subject"], '【スマートEX】')
        ) {
            $fromProv = true;
        }

        if ($fromProv === false) {
            return false;
        }

        foreach ($this->reSubject as $reSubject) {
            if (stripos($headers["subject"], $reSubject) !== false) {
                return true;
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
        $formats = 2;
        $cnt = $formats * count(self::$dict);

        return $cnt;
    }

    private function parseEmail(Email $email, string $text)
    {
        $text = preg_replace("/\n[ >]*{$this->opt($this->t('-Before change'))}[\s\S]+/", '', $text);
        $space = '[　　]';
        $colon = "[:：]";

        $headerText = $this->re("/^([\s\S]+?)(?:{$this->opt($this->t('Reservation number'))}|{$this->opt($this->t('Departure date:'))})/", $text);

        if (preg_match("#{$this->opt($this->t('Membership ID'))}: *(\d{5,})\n#", $headerText, $m)) {
            $account = $m[1];
        }
        $totalText = $this->re("#\n[ >]*{$this->opt($this->t('Total is'))}[ ]*(.+?)(?:\(|\n|$)#u", $headerText);

        $total = $this->getTotalCurrency($totalText);

        if (!empty($totalText)) {
            $email->price()
                ->total($total['Total'])
                ->currency($total['Currency']);
        }

        $segments = $this->split("/((?:\n[ >]*{$this->opt($this->t('Reservation number'))})?.+\s+.*{$this->opt($this->t('Departure date:'))})/u", $text);

        foreach ($segments as $stext) {
            $stext = "\n" . $stext . "\n";
            $r = $email->add()->train();

            $r->general()
                ->confirmation($this->re("#\n[ >]*{$this->opt($this->t('Reservation number'))}\s*[:：]?\s*(.+?)(?:{$space}{2,}|\n)#u",
                    $stext), $this->t('Reservation number'));

            if (!empty($item = $this->re("#\s+{$this->opt($this->t('tripNum'))}\s*{$colon}?\s*(.+?)(?:[ ]{2,}|\n)#u",
                $stext))
            ) {
                $r->general()
                    ->confirmation($item, $this->t('tripNum'));
            }

            if (isset($account)) {
                $r->program()
                    ->account($account, false);
            }

            $date = $this->normalizeDate($this->re("#\n[ >]*{$this->opt($this->t('Departure date:'))}{$space}*(.+?)(?:[ ]{2,}|\n)#u", $stext));
            $routes = $this->split("/(\n.*(?:→|-->).+(?:→|-->))/", $stext);
            // $this->logger->debug('$routes = '.print_r( $routes,true));

            foreach ($routes as $route) {
                $s = $r->addSegment();
                //新大阪(13:33)→のぞみ164号→東京(16:03)
                if (preg_match("#^\s*[ >]*(.+)[\(（](\d+:\d+)[\)）](?:→|-->)(.+?)(\d+)号?(?:→|-->)(.+)[\(（](\d+:\d+)[\)）]\s*[ >]*(.+?)\s*(?:[／\/]|$|\n)#u",
                    $route, $m)) {
                    $s->departure()
                        ->name($m[1])
                        ->date(strtotime($m[2], $date));
                    $s->extra()
                        ->service($m[3])
                        ->number($m[4]);
                    $s->arrival()
                        ->name($m[5])
                        ->date(strtotime($m[6], $date));
                    $s->extra()
                        ->type($m[7]);
                }

                if (preg_match("#\n[ >]*{$this->t('Non-Smoking')}\n#u", $route) > 0) {
                    $s->extra()->smoking(false);
                }

                if (
                    // 12号車8番E席
                    ($this->lang == 'ja' && preg_match_all("#^[ >]*(?<car>\d+){$this->opt($this->t('Car'))}(?<seat>[\w\-]+){$this->opt($this->t('Seat'))}\s*$#mu",
                            $route, $m))
                    //Car.10 Seat.1-B
                    || ($this->lang == 'en' && preg_match_all("#^[ >]*{$this->opt($this->t('Car'))}(?<car>\d+) *{$this->opt($this->t('Seat'))}(?<seat>[\w\-]+)\s*$#mu",
                            $route, $m))
                ) {
                    $s->extra()
                        ->car(implode(', ', array_unique($m['car'])))
                        ->seats(preg_replace('/番/u', '-', $m['seat']));
                }
            }

            $totalText = $this->re("#\n[ >]*{$this->t('Total')}[ ]*(.+?)(?:\(|\n|$)#", $stext);

            $total = $this->getTotalCurrency($totalText);

            if (!empty($totalText)) {
                $r->price()
                    ->total($total['Total'])
                    ->currency($total['Currency']);
            }
            $fee = $this->re("#\n[ >]*{$this->t('Handling fees:')}[ ]*(.+?)(?:\(|\n|$)#", $stext);

            if (!empty($fee)) {
                $descr = $this->re("#\n[ >]*({$this->t('Handling fees:')})[ ]*.+?(?:\(|\n|$)#", $stext);
                $total = $this->getTotalCurrency($fee);
                $r->price()
                    ->fee(trim($descr, ':'), $total['Total']);
            }
        }

        return true;
    }

    private function normalizeDate($date)
    {
        // $this->logger->debug('$date = '.print_r( $date,true));
        $year = date('Y', $this->date);
        $in = [
            //4月30日
            '#^\s*(\d+)月(\d+)日\s*$#u',
            // 2024年11月10日
            '#^\s*(\d{4})年(\d+)月(\d+)日\s*$#u',
        ];
        $out = [
            $year . '-$1-$2',
            '$1-$2-$3',
        ];
        // $this->logger->debug('$date = '.print_r( $date,true));
        $str = strtotime($this->dateStringToEnglish(preg_replace($in, $out, $date)));

        return $str;
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function detectBody($body)
    {
        if ($this->stripos($body, ['【EX】', 'expy.jp', 'smart-ex.jp']) === false) {
            return false;
        }

        foreach ($this->reBody as $lang => $reBody) {
            if ($this->stripos($body, $reBody) !== false
                && $this->assignLang($body) === true
            ) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
    }

    private function assignLang($body)
    {
        foreach (self::$dict as $lang => $words) {
            if (isset($words["Reservation number"], $words["Departure date:"])) {
                if (stripos($body, $words["Reservation number"]) !== false
                    && stripos($body, $words["Departure date:"]) !== false
                ) {
                    $this->lang = $lang;

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

    private function getTotalCurrency($node)
    {
        $node = str_replace("€", "EUR", $node);
        $node = str_replace("$", "USD", $node);
        $node = str_replace("£", "GBP", $node);
        $node = str_replace("円", "JPY", $node);
        $node = str_replace("Yen", "JPY", $node);
        $tot = '';
        $cur = '';

        if (preg_match("#(?<c>[A-Z]{3})\s*(?<t>\d[\.\d\,\s]*\d*)#", $node, $m)
            || preg_match("#(?<t>\d[\.\d\,\s]*\d*)\s*(?<c>[A-Z]{3})#", $node, $m)
        ) {
            $cur = $m['c'];
            $tot = PriceHelper::cost($m['t']);
        }

        return ['Total' => $tot, 'Currency' => $cur];
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

    private function stripos($haystack, $arrayNeedle)
    {
        $arrayNeedle = (array) $arrayNeedle;

        foreach ($arrayNeedle as $needle) {
            if (stripos($haystack, $needle) !== false) {
                return true;
            }
        }

        return false;
    }

    private function split($re, $text): array
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
}
