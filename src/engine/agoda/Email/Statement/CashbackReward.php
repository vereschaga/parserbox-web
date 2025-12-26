<?php

namespace AwardWallet\Engine\agoda\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class CashbackReward extends \TAccountChecker
{
    public $mailFiles = "agoda/statements/it-64958097.eml, agoda/statements/it-65220485.eml";
    public $detectSubjects = [
        // en
        ', we are processing your Cashback Reward!',
        ', your Cashback Reward is expiring in 14 days!',
        ', you can request your Cashback Reward now!',
        ', your Cashback Reward is on the way!',
        ', we ran into a problem with your Cashback Reward request!',
        // zh
        '！您可以申請現金回饋了！',
        '！您的現金回饋將在14天內過期。',
        '！我們正在處理您的現金回饋申請。',
        '，你的現金回贈獎賞將在14天內過期！',
        '，我們正在處理你的現金回贈獎賞！',
        '，您的返现奖励现已开放申请！',
        '，現在你可申請現金回贈獎賞！',
        // ja
        'キャッシュバック特典の申請受付を開始いたしました！',
        'キャッシュバック特典の受取申請を受理しました！',
        'キャッシュバック特典の申請期限は残り14日です！',
        // th
        ' แคชแบ็กรีวอร์ดของท่านกำลังจะหมดอายุภายใน 14 วัน',
        ' แคชแบ็กรีวอร์ดของท่านอยู่ระหว่างการดำเนินการ',
        ' ขณะนี้ท่านสามารถส่งคำขอรับแคชแบ็กรีวอร์ดได้แล้ว',
        // id
        ', Anda bisa mengajukan permintaan Imbalan Cashback Anda sekarang!',
        // it
        ', ora puoi richiedere il Rimborso Cashback!',
        ', stiamo elaborando il Rimborso Cashback!',
        ', il tuo Rimborso Cashback scadrà fra 14 giorni!',
        // ko
        '. 이제 캐쉬백 리워드를 요청하실 수 있습니다!',
        '. 캐쉬백 리워드를 14일 이내에 만료됩니다!',
        '. 고객님의 캐쉬백 리워드가 처리되고 있습니다.',
        // de
        ', Ihre Cashback-Belohnung wird gerade bearbeitet.',
        ', Ihre Cashback-Belohnung verfällt in 14 Tagen!',
        // ms
        ', Ganjaran Pulangan Tunai anda akan luput dalam',
        // '',
        // '',
        // '',
    ];

    public $lang = '';

    public static $dictionary = [
        'en' => [
            'detectPhrases' => [
                ', we are processing your Cashback Reward!',
                ', your Cashback Reward is expiring in 14 days!',
                ', you can request your Cashback Reward now!',
                ', your Cashback Reward is on the way!',
                ', we ran into a problem with your Cashback Reward request!',
            ],
            'Plan your next trip' => [ // button name
                'Plan your next trip', 'See Cashback Reward details', 'Request Cashback Reward',
                'Submit another request',
            ],
        ],
        'ja' => [
            'detectPhrases' => [
                '-キャッシュバック特典の申請受付開始まで少々お待ちください！',
                'キャッシュバック特典の申請受付を開始いたしました！',
                'キャッシュバック特典の受取申請を受理しました！',
                'キャッシュバック特典の申請期限は残り14日です！',
            ],
            'Plan your next trip' => [ // button name
                'キャッシュバック特典プログラム詳細を確認する',
                'キャッシュバック特典を申請',
                'さっそく次の旅行を計画する',
            ],
        ],
        'zh' => [
            'detectPhrases' => [
                '！您可以申請現金回饋了！',
                '！您的現金回饋將在14天內過期。',
                '！我們正在處理您的現金回饋申請。',
                '，你的現金回贈獎賞將在14天內過期！',
                '，我們正在處理你的現金回贈獎賞！',
                '，您的返现奖励现已开放申请！',
                '，現在你可申請現金回贈獎賞！',
            ],
            'Plan your next trip' => [ // button name
                '申請現金回饋', '查看現金回饋詳情', '開始計畫下次旅行',
                '查看現金回贈獎賞詳情', '計劃下次旅程',
                '申请返现奖励',
                '要求現金回贈獎賞',
            ],
        ],
        'th' => [
            'detectPhrases' => [
                ' แคชแบ็กรีวอร์ดของท่านกำลังจะหมดอายุภายใน 14 วัน',
                ' แคชแบ็กรีวอร์ดของท่านอยู่ระหว่างการดำเนินการ',
                ' ขณะนี้ท่านสามารถส่งคำขอรับแคชแบ็กรีวอร์ดได้แล้ว',
            ],
            'Plan your next trip' => [ // button name
                'ดูรายละเอียดแคชแบ็กรีวอร์ด',
                'ค้นหาที่พักถูกใจสำหรับทริปต่อไปได้เลย',
                'ส่งคำขอรับแคชแบ็กรีวอร์ด',
            ],
        ],
        'id' => [
            'detectPhrases' => [
                ', Anda bisa mengajukan permintaan Imbalan Cashback Anda sekarang!',
            ],
            'Plan your next trip' => [ // button name
                'Ajukan imbalan Cashback',
            ],
        ],
        'it' => [
            'detectPhrases' => [
                ', il tuo Rimborso Cashback scadrà fra 14 giorni!',
                ', stiamo elaborando il Rimborso Cashback!',
                ', ora puoi richiedere il Rimborso Cashback!',
            ],
            'Plan your next trip' => [ // button name
                'Consulta i dettagli del Rimborso Cashback',
                'Organizza il tuo prossimo viaggio',
                'Richiedi Rimborso Cashback',
            ],
        ],
        'ko' => [
            'detectPhrases' => [
                ', 안녕하세요. 이제 캐쉬백 리워드를 요청하실 수 있습니다!',
                ', 안녕하세요. 캐쉬백 리워드를 14일 이내에 만료됩니다!',
                ', 안녕하세요. 고객님의 캐쉬백 리워드가 처리되고 있습니다.',
            ],
            'Plan your next trip' => [ // button name
                '캐쉬백 리워드 요청하기',
                '캐쉬백 리워드 상세 보기',
                '다음 여행 계획하기',
            ],
        ],
        'de' => [
            'detectPhrases' => [
                ', Ihre Cashback-Belohnung wird gerade bearbeitet.',
                ', Ihre Cashback-Belohnung verfällt in 14 Tagen!',
            ],
            'Plan your next trip' => [ // button name
                'Planen Sie Ihre nächste Reise',
                'Siehe Details zur Cashback-Belohnung',
            ],
        ],
        'ms' => [
            'detectPhrases' => [
                ', Ganjaran Pulangan Tunai anda akan luput dalam 14 hari!',
            ],
            'Plan your next trip' => [ // button name
                'Lihat maklumat Ganjaran Pulangan Tunai',
            ],
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], 'no-reply@agoda.com') !== false) {
            foreach ($this->detectSubjects as $subject) {
                if (strpos($headers['subject'], $subject) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        foreach (self::$dictionary as $lang => $dict) {
            // originalsrc
            if (!empty($dict['Plan your next trip']) && $this->http->XPath->query("//a[@*[{$this->contains('/agoda.onelink.me/')}]][{$this->eq($dict['Plan your next trip'])}]")->length > 0
                && !empty($dict['detectPhrases']) && $this->http->XPath->query("//node()[{$this->contains($dict['detectPhrases'])}]")->length > 0
            ) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]agoda\.com$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        foreach (self::$dictionary as $lang => $dict) {
            if (!empty($dict['Plan your next trip']) && $this->http->XPath->query("//a[@*[{$this->contains('/agoda.onelink.me/')}]][{$this->eq($dict['Plan your next trip'])}]")->length > 0
                && !empty($dict['detectPhrases']) && $this->http->XPath->query("//node()[{$this->contains($dict['detectPhrases'])}]")->length > 0
            ) {
                $this->lang = $lang;

                break;
            }
        }

        $st = $email->add()->statement();

        $name = $this->http->FindSingleNode("//td[count(*) = 2 and not(*[1][normalize-space()]) and *[2][normalize-space()]][*[1][.//a[@*[contains(., '/agoda.onelink.me/')]]//img/@src[contains(., 'agoda_logo_elements') and contains(., '.agoda.net/images')]]]/*[2]",
            null, true, "/^\s*[[:alpha:] \-]+\s*$/");

        $number = null;
        $numbers = array_filter($this->http->FindNodes("//a/@*[contains(., '/agoda.onelink.me/') and contains(., 'memberID')]",
            null, "/[%\*]2526memberID[%\*]253D(\d{5,})[%\*]2526/"));
        // $numberLinks = $this->http->FindNodes("//a/@*[contains(., '/agoda.onelink.me/') and contains(., 'memberID')]");
        // $numberLinks = array_map('urldecode', $numberLinks);
        // preg_match_all("//");
        // $this->logger->debug('$n2 = '.print_r($n2,true));
        // $this->logger->debug('$n2 = '.print_r(array_map('urldecode', $n2),true));

        if (count(array_unique($numbers)) == 1) {
            $number = $numbers[0];
        }

        if (!empty($name) && !empty($number)) {
            $st->addProperty('Name', trim($name, ','));
            $st->setNumber($number);
            $st->setNoBalance(true);
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
        return 0;
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

        return implode(" or ", array_map(function ($s) {
            return "starts-with(normalize-space(.), \"{$s}\")";
        }, $field));
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
}
