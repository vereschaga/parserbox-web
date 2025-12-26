<?php

namespace AwardWallet\Engine\rurubu\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class ContentOfReservation extends \TAccountChecker
{
    public $mailFiles = "rurubu/it-35742046.eml, rurubu/it-35944586.eml, rurubu/it-39869437.eml, rurubu/it-499961199.eml";

    public $reFrom = ["support@rurubu.travel"];
    public $reBody = [
        'ja1' => ['るるぶトラベル FOR ビジネスプランをご利用のお客様', '【ご予約内容】'],
        'ja2' => ['ご予約いただきありがとうございます。', '＜＜ご予約内容・注意事項＞＞'],
        'ja3' => ['自動的に配信しています', 'のご予約取消が完了いたしました'],
        'ja4' => ['るるぶトラベルをご利用のお客様', '【ご予約内容】'],
    ];
    public $reSubject = [
        '出発前のご確認[',
    ];
    public $lang = '';
    public static $dict = [
        'ja' => [
            'formatsKeyWord' => [
                '1' => ['【ご予約内容】'],
                '2' => ['＜＜ご予約内容・注意事項＞＞', '＜＜取消内容・注意事項＞＞'],
            ],
            'Your reservation has been canceled for' => 'のご予約取消が完了いたしました',
            'contactPhone'                           => '宿泊施設の連絡先',
            'receptionNumber'                        => '受付番号',
            'receptionDate'                          => '受付日時',
            'itemNumber'                             => '商品番号',
            'hotelName'                              => ['宿名', '宿泊施設名'],
            'checkin'                                => ['チェックイン日', 'ご宿泊日'],
            'from'                                   => 'より',
            'night'                                  => '泊',
            'scheduledCheckIn'                       => ['チェックイン予定', '到着予定時刻'],
            'roomsInfo'                              => ['人数部屋数', '人数・部屋数'],
            'cnt'                                    => '人',
            'men'                                    => '男',
            'women'                                  => '女',
            'kids'                                   => '子供',
            'pers'                                   => '名',
            'rooms'                                  => '室',
            'roomCondition'                          => '部屋条件',
            'mealCondition'                          => '食事条件',
            'accommodationFee'                       => ['宿泊代金', '宿泊料金合計', '宿泊合計料金', 'お支払い額'],
            //format 2;
            'guestName'            => '宿泊者名',
            'whereToStay'          => 'ご宿泊先',
            'accommodationAddress' => 'ご宿泊先住所',
        ],
    ];
    private $keywordProv = ['るるぶトラベル', 'rurubu travel'];

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
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

        if (!empty($body)) {
            $formats = $this->t('formatsKeyWord');

            foreach ($formats as $format => $keyWords) {
                if ($this->stripos($body, $keyWords) !== false) {
                    switch ($format) {
                        case '1':
                            $type = $format;
                            $this->parseEmail_1($email, $body);

                            break;

                        case '2':
                            $type = $format;
                            $this->parseEmail_2($email, $body);

                            break;

                        default:
                            $this->parseEmail_1($email, $body);

                            break;
                    }
                }
            }
        }
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
        $fromProv = true;

        if (!isset($headers['from']) || self::detectEmailFromProvider($headers['from']) === false) {
            $fromProv = false;
        }

        if (isset($headers["subject"])) {
            foreach ($this->reSubject as $reSubject) {
                if (($fromProv && stripos($headers["subject"], $reSubject) !== false)
                    || $this->stripos($headers["subject"], $this->keywordProv) !== false
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
        $formats = 2;
        $cnt = $formats * count(self::$dict);

        return $cnt;
    }

    private function parseEmail_1(Email $email, string $text)
    {
        $this->logger->debug(__METHOD__);

        $r = $email->add()->hotel();
        $r->program()
            ->phone($this->nextText($this->t('contactPhone'), $text), $this->t('contactPhone'));

        $r->general()
            ->confirmation($this->nextText($this->t('receptionNumber'), $text), $this->t('receptionNumber'), true)
            ->date($this->normalizeDate($this->nextText($this->t('receptionDate'), $text)));

        if (!empty($item = $this->nextText($this->t('itemNumber'), $text))) {
            $r->general()
                ->confirmation($item, $this->t('itemNumber'));
        }
        $r->hotel()
            ->name($this->nextText($this->t('hotelName'), $text))
            ->noAddress();

        $node = $this->nextText($this->t('checkin'), $text);

        if (preg_match("#(.+){$this->t('from')}(\d+){$this->t('night')}#", $node, $m)) {
            $date = $this->normalizeDate($m[1]);
            $r->booked()
                ->checkIn($date)
                ->checkOut(strtotime('+' . $m[2] . ' days', $date));
        }
        $node = $this->nextText($this->t('scheduledCheckIn'), $text);

        if ($r->getCheckInDate() && preg_match("#\d+:\d+#", $node)) {
            $r->booked()
                ->checkIn(strtotime($node, $r->getCheckInDate()));
        }

        $comma = "[,、]";
        $openBr = "[\(（]";
        $closeBr = "[\)）]";
        $node = $this->nextText($this->t('roomsInfo'), $text);

        $regExp = "#\d+{$this->t('pers')}\D*(?<rooms>\d+){$this->t('rooms')}\s*" .
            "{$openBr}(?:{$this->t('men')}:?(?<men>\d+){$this->t('cnt')})?(?:{$comma})?" .
            "(?:{$this->t('women')}:?(?<women>\d+){$this->t('cnt')})?(?:{$comma})?" .
            "(?:{$this->t('kids')}:?(?<kids>\d+){$this->t('cnt')})?{$closeBr}\s*$#u";

        if (preg_match($regExp, $node, $m)) {
            $r->booked()
                ->rooms($m['rooms']);
            $guest = 0;

            if (isset($m['men'])) {
                $guest = (int) $m['men'];
            }

            if (isset($m['women'])) {
                $guest += (int) $m['women'];
            }
            $r->booked()
                ->guests($guest);

            if (isset($m['kids'])) {
                $r->booked()
                    ->kids($m['kids']);
            }
        }

        $room = $r->addRoom();
        $room->setType($this->nextText($this->t('roomCondition'), $text));
        $room->setDescription($this->nextText($this->t('mealCondition'), $text));

        $total = $this->re("#(.+?)(?:\(|$)#", $this->nextText($this->t('accommodationFee'), $text));
        $total = $this->getTotalCurrency($total);
        $r->price()
            ->total($total['Total'])
            ->currency($total['Currency']);

        return true;
    }

    private function parseEmail_2(Email $email, string $text)
    {
        $this->logger->debug(__METHOD__);

        $r = $email->add()->hotel();

        $r->general()
            ->confirmation($this->nextText($this->t('receptionNumber'), $text), $this->t('receptionNumber'), true)
            ->traveller($this->nextText($this->t('guestName'), $text), true)
            ->date($this->normalizeDate($this->nextText($this->t('receptionDate'), $text)));

        if (!empty($item = $this->nextText($this->t('itemNumber'), $text))) {
            $r->general()
                ->confirmation($item, $this->t('itemNumber'));
        }

        if ($this->http->XPath->query("//text()[{$this->contains($this->t('Your reservation has been canceled for'))}]")->length > 0) {
            $r->general()
                ->cancelled()
                ->status('canceled');
        }

        $node = $this->nextText($this->t('whereToStay'), $text);

        if (preg_match("#(.+)\s*\(TEL:(.+)\)#", $node, $m)) {
            $r->hotel()
                ->name($m[1])
                ->phone($m[2]);
        }
        $address = $this->nextText($this->t('accommodationAddress'), $text);

        if (!$address) {
            $r->hotel()->noAddress();
        } else {
            $r->hotel()->address($address);
        }

        $node = $this->nextText($this->t('checkin'), $text);

        if (preg_match("#(.+){$this->t('from')}(\d+){$this->t('night')}#", $node, $m)) {
            $date = $this->normalizeDate($m[1]);
            $r->booked()
                ->checkIn($date)
                ->checkOut(strtotime('+' . $m[2] . ' days', $date));
        }
        $node = $this->nextText($this->t('scheduledCheckIn'), $text);

        if ($r->getCheckInDate() && preg_match("#\d+:\d+#", $node)) {
            $r->booked()
                ->checkIn(strtotime($node, $r->getCheckInDate()));
        }

        $comma = "[,、]";
        $openBr = "[\(（]";
        $closeBr = "[\)）]";
        $node = $this->nextText($this->t('roomsInfo'), $text);

        $regExp = "#\d+{$this->t('pers')}\D*(?<rooms>\d+){$this->t('rooms')}\s*" .
            "{$openBr}(?:{$this->t('men')}:?(?<men>\d+){$this->t('cnt')})?(?:{$comma})?" .
            "(?:{$this->t('women')}:?(?<women>\d+){$this->t('cnt')})?(?:{$comma})?" .
            "(?:{$this->t('kids')}:?(?<kids>\d+){$this->t('cnt')})?{$closeBr}\s*$#u";

        if (preg_match($regExp, $node, $m)) {
            $r->booked()
                ->rooms($m['rooms']);
            $guest = 0;

            if (isset($m['men'])) {
                $guest = (int) $m['men'];
            }

            if (isset($m['women'])) {
                $guest += (int) $m['women'];
            }
            $r->booked()
                ->guests($guest);

            if (isset($m['kids'])) {
                $r->booked()
                    ->kids($m['kids']);
            }
        }

        $room = $r->addRoom();
        $room->setType($this->nextText($this->t('roomCondition'), $text));
        $room->setDescription($this->nextText($this->t('mealCondition'), $text));

        $total = $this->re("#(.+?)(?:\(|$)#", $this->nextText($this->t('accommodationFee'), $text));
        $total = $this->getTotalCurrency($total);
        $r->price()
            ->total($total['Total'])
            ->currency($total['Currency']);

        return true;
    }

    private function nextText($field, $text)
    {
        $colon = "[:：]";

        return $this->re("#\n[ ]*{$this->opt($field)}\s*{$colon}\s*(.+)#u", $text);
    }

    private function normalizeDate($date)
    {
        $in = [
            //2019/03/28 17:37:00
            '#^\s*(\d{4})\/(\d{2})\/(\d{2})\s+(\d+:\d+)(?::\d+)?\s*$#u',
            //2019/03/28
            '#^\s*(\d{4})\/(\d{2})\/(\d{2})\s*$#u',
            //2019年5月14日(火)
            '#^\s*(\d{4})年(\d+)月(\d+)日\(.+\)\s*$#u',
        ];
        $out = [
            '$3-$2-$1, $4',
            '$3-$2-$1',
            '$3-$2-$1',
            '$3-$2-$1',
        ];
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
        if ($this->stripos($body, $this->keywordProv) === false) {
            return false;
        }

        if (isset($this->reBody)) {
            foreach ($this->reBody as $lang => $reBody) {
                if (stripos($body, $reBody[0]) !== false && stripos($body, $reBody[1]) !== false) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function assignLang($body)
    {
        foreach (self::$dict as $lang => $words) {
            if (isset($words["receptionDate"], $words["receptionNumber"])) {
                if (stripos($body, $words["receptionDate"]) !== false && stripos($body,
                        $words["receptionNumber"]) !== false
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

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) {
            return "contains(normalize-space(.), \"{$s}\")";
        }, $field));
    }
}
