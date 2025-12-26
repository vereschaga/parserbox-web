<?php

namespace AwardWallet\Engine\jtb\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Common\Flight;
use AwardWallet\Schema\Parser\Common\Train;
use AwardWallet\Schema\Parser\Common\TrainSegment;
use AwardWallet\Schema\Parser\Common\Transfer;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class Cwt extends \TAccountChecker
{
    public $mailFiles = "jtb/it-23218852.eml, jtb/it-23219072.eml, jtb/it-23219171.eml, jtb/it-23219323.eml, jtb/it-26531129.eml, jtb/it-26531284.eml, jtb/it-26761193.eml, jtb/it-28183881.eml, jtb/it-29138808.eml, jtb/it-29622516.eml, jtb/it-29932908.eml, jtb/it-30081272.eml, jtb/it-32576015.eml, jtb/it-36076254.eml, jtb/it-42293958.eml";
    public static $dictionary = [
        'ja' => [
            '旅程' => '▼旅程',
            //			'ホテル' => '',
            //			'レンタカー・送迎' => '',
            'passengerRow' => ['パスポート名(姓/名)', '出張者氏名'],
            '予約番号'         => ['予約番号', '確認番号', 'confirmation reference number'],
            //			'ターミナル' => '',
            //			'機材' => '',
            //			'飛行時間' => '',
            //			'キャンセル期限' => '',
            //			'チェックイン' => '',
            //			'チェックアウト' => '',
            //			'名様' => '',
            '概算合計料金' => ['航空券代金概算', '概算合計料金'],
            //			'概算料金' => '',
            //			'1泊あたり料金' => '',
            //			'同行者' => '',
            '料金'         => ['料金', '合計：'],
            'ANAお帰りハイヤー' => ['ANAお帰りハイヤー', 'JALお帰りお車サー', 'QF 送迎手配', 'ANAお帰りお車サービス', 'ＡＮＡハイヤーサービス', 'ANA/JALお帰りハイヤー'],
        ],
        'en' => [
            '旅程'  => 'Transportation',
            'ホテル' => 'Hotel',
            //			'レンタカー・送迎' => '', //rental car
            'passengerRow' => 'Last Name/First Name',
            '予約番号'         => ['Reference Number', 'Confirmation number', 'confirmation reference number'],
            'ターミナル'        => 'Terminal',
            '機材'           => 'Equipment',
            '飛行時間'         => 'Journey time',
            'キャンセル期限'      => 'Cancel Limit',
            'チェックイン'       => 'Check-in',
            'チェックアウト'      => 'Check-out',
            //			'名様' => '',
            '概算合計料金' => 'Estimated Total Amount',
            '概算料金'   => 'Approx:',
            //			'1泊あたり料金' => '',
            '同行者'        => '同行者',
            'ANAお帰りハイヤー' => 'ANA Free Limousine',
        ],
    ];

    private $detectFrom = '@jtb-cwt.com';
    private $detectSubject = [
        '【予約内容回答】',
        '【最終確認のお願い】',
        '[Confirmation] Dep:',
        '[Final Confirmation] Dep:',
    ];

    private $detectCompany = [
        'JTB-CWT',
        '▼旅程━', '▼ホテル━', // dirty hacks
    ];

    private $detectBody = [
        'ja' => [
            '旅程',
            'ANA便の重複予約の期限が来ました。',
            '飛行機',
            'JTB-CWTビジネストラベルソリューションズ',
        ],
        'en' => [
            'Transportation',
        ],
    ];

    private $lang = 'ja';
    private $parseError;

    private $pattern = [
        'trainSegment' => "/^\s*"
            . "(?<dDate>\S.+?)[ ]*(?<dTime>\d+:\d+)[ ]+(?<dName>.+?)\s*\n+"
            . "[ ]*(?<aDate>\S.+?[ ]*)?(?<aTime>\d+:\d+)\s+(?<aName>.+?)\s*\n"
            . "/u",
    ];

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $body = html_entity_decode($this->http->Response['body']);

        if (empty($body)) {
            $body = $parser->getPlainBody();
        }

        foreach ($this->detectBody as $lang => $detectBody) {
            foreach ($detectBody as $detect) {
                if (stripos($body, $detect) !== false
                    || $this->http->XPath->query('//node()[contains(normalize-space(),"' . $detect . '")]')->length > 0
                ) {
                    $this->lang = $lang;

                    break;
                }
            }
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        $this->parseError = false;

        $body = $parser->getPlainBody();

        if (!empty($body) && $this->striposArray($body, [$this->t('旅程'), $this->t('決めていただきたくお願い致します'), $this->t('飛行機'), $this->t('ました。'), $this->t('ホテル'), $this->t('レンタカー・送迎')]) == true
            && preg_match("#(^|\W)" . $this->preg_implode([$this->t('旅程'), $this->t('決めていただきたくお願い致します'), $this->t('飛行機'), $this->t('ました。'), $this->t('ホテル'), $this->t('レンタカー・送迎')]) . "\W#u", $body)
        ) {
            $this->parseEmail($email, $body);
        }

        if ($this->parseError == true || empty($email->getItineraries())) {
            foreach ($email->getItineraries() as $value) {
                $email->removeItinerary($value);
            }
            $body = html_entity_decode($parser->getHTMLBody());
            $body = preg_replace("#(>[^<>\n\r]+)[\r\n]+([^<>\n\r]+<)#", "$1 $2", $body);
            $body = str_replace("&nbsp;", " ", $body);
            $body = strip_tags($body);

            if (empty($body)) {
                return $email;
//                $body = $parser->getPlainBody();
            }
            $this->parseEmail($email, $body);
        }

        foreach ($email->getItineraries() as $itinerary) {
            if ($itinerary->getType() === 'train') {
                $segs = $itinerary->getSegments();

                foreach ($segs as $seg) {
                    if ($seg->getDepName() === 'HOTEL' || $seg->getDepName() === 'AIRPORT') {
                        $email->removeItinerary($itinerary);
                    }
                }
            }
        }

        return $email;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->detectFrom) !== false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $body = $this->http->Response['body'];

        if (empty($body)) {
            $body = $body = $parser->getPlainBody();
        }
        $foundCompany = false;
        $reply = '';

        if (!empty($parser->getHeader('in-reply-to'))) {
            $reply = $parser->getHeader('in-reply-to');
        }

        if (empty($reply) && !empty($parser->getHeader('references'))) {
            $reply = $parser->getHeader('references');
        }

        if (preg_match("/(?:^|:[ ]*)JTB(?: |$)/i", $parser->getSubject())
            || false !== stripos($reply, 'JTB')
            || false !== stripos($parser->getSubject(), '[Confirmation] Dep:')) {
            $foundCompany = true;
        }

        foreach ($this->detectCompany as $detectBody) {
            if (stripos($body, $detectBody) !== false) {
                $foundCompany = true;

                break;
            }
        }

        if (!$foundCompany) {
            return false;
        }

        foreach ($this->detectBody as $detectBody) {
            foreach ($detectBody as $detect) {
                if (stripos($body, $detect) !== false
                    || $this->http->XPath->query('//node()[contains(normalize-space(),"' . $detect . '")]')->length > 0
                ) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (stripos($headers['from'], $this->detectFrom) === false) {
            return false;
        }

        foreach ($this->detectSubject as $detectSubject) {
            if (stripos($headers['subject'], $detectSubject) !== false) {
                return true;
            }
        }

        return false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        $types = 6; //flight + train + hotel + rental + transfer + event
        $cnt = $types * count(self::$dictionary);

        return $cnt;
    }

    private function parseEmail(Email $email, string $body): void
    {
        $NBSP = chr(194) . chr(160);
        $body = str_replace($NBSP, ' ', html_entity_decode($body));

        $body = preg_replace("#^([\> ]*\>)#m", '', $body);
        // it-29622516.eml
        $body = preg_replace("/[ ]{10,}/", "\n", $body);

        $typeDelimiter = '(?:━|=|-|\/)';
        $dateFormats = [
            'ja' => '\d+年',
            'en' => '\d{1,2},\w+\.\d{4}',
        ];

        if (empty($dateFormats[$this->lang])) {
            $this->logger->debug("date format is not found");

            return;
        }

        if (preg_match_all("/({$this->preg_implode($this->t('Reservation System Reference Number'))})\/([A-Z\d]{5,6})\b/",
            $body, $m, PREG_SET_ORDER)) {
            foreach ($m as $k) {
                $otaConfNo[$k[1]] = $k[2];
            }

            if (isset($otaConfNo)) {
                $otaConfNo = array_unique($otaConfNo);

                foreach ($otaConfNo as $k => $v) {
                    $email->ota()->confirmation($v, $k);
                }
            }
        }

        // Transport
        $posBegin = $this->str_rpos($body, $this->t('旅程'));

        if (0 === $posBegin) {
            $posBegin = mb_strpos($body, $this->t('旅程'));
        }

        if (0 === strpos(trim($body), '-----Original Message-----') && false !== mb_strpos($body, '旅程が未確定の場合は料金は概算です') && 1400 < $posBegin) { // dirty hack
            $posBegin = mb_strpos($body, '-----Original Message-----');
        }

        if (false === $posBegin) {
            $posBegin = mb_strpos($body, $this->t('決めていただきたくお願い致します'));
        }

        if (false === $posBegin) {
            $posBegin = mb_strpos($body, $this->t('飛行機'));
        }

        if (false === $posBegin) {
            $posBegin = mb_strpos($body, $this->t('ました。'));
        }

        if ($posBegin !== false && preg_match("#(^|\W)" . $this->preg_implode([$this->t('旅程'), $this->t('決めていただきたくお願い致します'), $this->t('飛行機'), '最終確認のお願い']) . "\W#u", $body)) {
            $posBegin = $posBegin < 20 ? 0 : $posBegin - 20;

            if (preg_match("/(?:\n|^).*(?:{$this->t('旅程')}|{$this->t('決めていただきたくお願い致します')}|{$this->t('飛行機')}[】]?){$typeDelimiter}{10,}\s*\n([\s\S]+?)\n\s*(?:\W*(?:{$this->preg_implode($this->t('ホテル'))}|{$this->preg_implode($this->t('レンタカー・送迎'))}){$typeDelimiter}{10,}|-----Original Message-----)\s*\n/u", mb_substr($body, $posBegin), $m)) {
                $transportText = $m[1];
            } elseif (preg_match("#(?:\n|^).*(?:" . $this->t('旅程') . "[\)]?|" . $this->t('決めていただきたくお願い致します。') . "|" . $this->t('飛行機') . "[】]?||最終確認のお願い.+)\s*\n([\s\S]+?)\n\s*" . $typeDelimiter . "{10,}\s*\n#u", mb_substr($body, $posBegin), $m)) { // it-35424183.eml
                $transportText = $m[1];
            } elseif (mb_strpos($body, $this->t('ホテル')) === false
                && mb_strpos($body, $this->t('レンタカー・送迎')) === false
                && $this->mb_strposAll($body, $this->t('概算合計料金')) === false
            ) {
                $transportText = preg_replace("#^.+\n#", '', mb_substr($body, $posBegin));
            } else {
                $this->logger->debug("transport itineraries is not detected");
                $email->add()->flight(); // for 100% failed
                $this->parseError = true;

                return;
            }
        } else {
            $this->logger->debug("transport itineraries is not found");
        }
//        $this->logger->debug($transportText);
        if (isset($transportText) && !empty($transportText)) {
            //$this->logger->debug($transportText);
//            $spaceCount = strlen($this->re("#^([ ]+)\S#", $transportText))??18;
            $flightsReg = $dateFormats[$this->lang] . ".+?\s*\d+:\d+.+?\/[A-Z]{3}(?:\s*" . $this->t('ターミナル') . ".+)?\n[ ]*" . $dateFormats[$this->lang] . ".+?\s*\d+:\d+.+?\/[A-Z]{3}";

            $otherReg = $dateFormats[$this->lang] . ".+(?:\n.*)?\n\s*(?:" . $dateFormats[$this->lang] . "|\d+:\d+).+\s*\n\s*(?!(?:" . $dateFormats[$this->lang] . "|\d+:\d+))";
            $splitRegexp = "#^([ ]*(?:{$flightsReg}|{$otherReg}))#um";
            //			$splitRegexp = "#(?:^|\n)([ ]{0,".($spaceCount+2)."}".$dateFormats[$this->lang].".+\n\s*(?:".$dateFormats[$this->lang]."|\d+:\d+).+\s*\n\s*(?!(?:".$dateFormats[$this->lang]."|\d+:\d+)))#u";
            $segments = $this->split($splitRegexp, $transportText);

            foreach ($segments as $key => $stext) {
                if (preg_match("#^[ ]*.*?\/[A-Z]{3}\s+#su", $stext) && preg_match('/\d{1,2}:\d{2}/', $stext)) {
                    $this->flight($email, $stext);

                    continue;
                }

                if (preg_match("#^\s*.+\d+:\d+ .+\n+.+\d+:\d+ .+\n.*\d.*\n.* \d{1,3}[A-Z]\s+#u", $stext)) {
                    if (preg_match($this->pattern['trainSegment'], $stext, $mm) && isset($mm['dName'], $mm['aName'])
                        && (preg_match("/^{$this->preg_implode($this->t('ターミナル'))}\s*(\w+)$/iu", trim($mm['dName']))
                            || preg_match("/^{$this->preg_implode($this->t('ターミナル'))}\s*(\w+)$/iu", trim($mm['aName'])))
                    ) {
                        $this->logger->debug('train segment has point(depName/arrName) as terminal - skip');
                        $this->logger->alert($stext);

                        continue;
                    }
                    $this->train($email, $stext);

                    continue;
                }

                if (preg_match("#^\s*[^\n]+\d+:\d+\s*\S[^\n]+\s*\n+[^\n]+\d+:\d+\s*\S.+ツアー.+#us", $stext)) {
                    $this->event($email, $stext);

                    continue;
                }

                if (preg_match("#^\s*[^\n]+\d+:\d+ [^\n]+\n+[^\n]+\d+:\d+ .+{$this->preg_implode($this->t("ANAお帰りハイヤー"))}#su", $stext)) {
                    $this->transfer($email, $stext);

                    continue;
                }

                if (preg_match("#^\s*.+\d+:\d+\s.+\s*\n+\s*.+\d+:\d+\s{1,2}.+#u", $stext)) {
                    if (preg_match($this->pattern['trainSegment'], $stext, $mm) && isset($mm['dName'], $mm['aName'])
                        && (preg_match("/^{$this->preg_implode($this->t('ターミナル'))}\s*(\w+)$/iu", trim($mm['dName']))
                            || preg_match("/^{$this->preg_implode($this->t('ターミナル'))}\s*(\w+)$/iu", trim($mm['aName'])))
                    ) {
                        $this->logger->debug('train segment has point(depName/arrName) as terminal - skip');
                        $this->logger->alert($stext);

                        continue;
                    }
                    $this->train($email, $stext);

                    continue;
                }

                if (preg_match("#^[ ]*.*?\/[A-Z]{3}\s+#su", $stext) && !preg_match('/\d{1,2}:\d{2}/', $stext)) {
                    $this->logger->debug('flight segment does not contains times');

                    continue;
                }

                if (preg_match('/\d+\D+\d+\D+\d+\D+\s+\d+:\d+\s+.+\s+\d+\D+\d+\D+\d+\D+OK/', $stext)) {
                    $this->logger->debug('flight segment does not contains arrTime, arrName, arrCode');

                    continue;
                }

                if (false !== stripos($stext, 'ご自宅') || false !== stripos($stext, '送迎先：')) {
                    $this->logger->debug('flight segment does not contains depTime, arrTime');

                    continue;
                }

                if (preg_match("#^\s*.+\n.+\n\s*鉄道#us", $stext) && !preg_match('/\d{1,2}:\d{2}/', $stext)) {
                    $this->logger->debug('train segment does not contains depTime, arrTime');

                    continue;
                }

                if (preg_match("#.+{$this->preg_implode($this->t("ANAお帰りハイヤー"))}#su", $stext) && !preg_match('/\d{1,2}:\d{2}/', $stext)) {
                    $this->logger->debug('transfer segment does not contains times');

                    continue;
                }

                if (preg_match("#フライトのご選択＆発券のご指示、お待ち致しております。#su", $stext)) {
                    $this->logger->debug('not reservation block');

                    continue;
                }
                // 19年08月05日(月)   伊丹空港
                if (preg_match('/\d+\D+\d+\D+\d+\D+\(\D\)\s{3,}.+?\D+/u', $stext)) {
                    $this->logger->debug('flight segment does not contains arrTime, arrName, arrCode');

                    continue;
                }

                $this->logger->debug("transport segment[{$key}] is not detecteded");
                $email->add()->flight(); // for 100% failed
                $this->parseError = true;

                return;
            }
        }

        // Hotel
        $posBegin = mb_strpos($body, $this->t('ホテル'));

        if ($posBegin !== false && preg_match("#(?:^|[^\w\s]){$this->preg_implode($this->t('ホテル'))}{$typeDelimiter}+[ ]*[\n\r]#u", $body)) {
            $posBegin = ($posBegin < 20) ? 0 : $posBegin - 20;

            if (preg_match("#(?:^|[\n\r])\W*{$this->preg_implode($this->t('ホテル'))}{$typeDelimiter}{10,}[ ]*[\n\r]+([\s\S]+?)(?:[\n\r]+\s*.*{$typeDelimiter}{15,}|$)#u", mb_substr($body, $posBegin), $m)) {
                $hotelText = $m[1];
                // it-42293958.eml (bug fix - empty hotel)
                if ($str = mb_strstr($hotelText, $this->t('【海外航空券宅配サービス廃止のご案内】'), true)) {
                    $hotelText = $str;
                }

                if (!preg_match("#[[:alpha:]]#u", $hotelText)) {
                    $hotelText = null;
                }
            } else {
                $this->logger->debug("hotel itineraries is not detected");
                $email->add()->hotel(); // for 100% failed
                $this->parseError = true;

                return;
            }
        } else {
            $this->logger->debug("hotel itineraries is not found");
        }

        if (isset($hotelText) && !empty($hotelText)) {
            $spaceCount = strlen($this->re("#^(?:\s*\n)*([ ]+)\S#u", $hotelText)) ?? 18;
            $splitRegexp = "#(?:^|\s*\n)([ ]{0," . ($spaceCount + 2) . "}.*\s*" . $this->t('チェックイン') . "\b.*\n.*\n\s*.+)#u";
            $segments = $this->split($splitRegexp, $hotelText);

            foreach ($segments as $stext) {
                if (false !== stripos($stext, '現在、未予約') || false !== stripos($stext, 'では未手配')) { // not reserved
                    continue;
                }
                $this->hotel($email, $stext);
            }
        }

        // Rental car
        $posBegin = mb_strpos($body, $this->t('レンタカー・送迎'));

        if ($posBegin !== false) {
            if (preg_match("#(?:^|\n).*" . $this->t('レンタカー・送迎') . $typeDelimiter . "{10,}\s*\n([\s\S]+?)\n\s*.*" . $typeDelimiter . "{15,}\s*\n#u", mb_substr($body, $posBegin - 20), $m)) {
                $rentalText = $m[1];
            } else {
                $this->logger->debug("rental car itineraries is not detected");
                $email->add()->rental(); // for 100% failed
                $this->parseError = true;

                return;
            }
        } else {
            $this->logger->debug("rental car itineraries is not found");
        }

        if (!empty($rentalText)) {
            $spaceCount = strlen($this->re("#^([ ]+)\S#", $rentalText)) ?? 18;
            $splitRegexp = "#(?:^|\n\s*\n)([ ]{0," . ($spaceCount + 2) . "}\S)#";
            $rentalText = preg_replace("/\n{2}/", "\n", $rentalText);

            $segments = $this->split($splitRegexp, $rentalText);

            foreach ($segments as $stext) {
                if (false !== stripos($stext, '【重要】') || false !== stripos($stext, 'ハーツレンタカーのご予約につきましては、下記URLから詳細な予約情報を')) { // trash
                    continue;
                }
                $this->rental($email, $stext);
            }
        }

        $travellers = $this->res("#\n\s*\W?{$this->preg_implode($this->t("passengerRow"))}.*?\W([[:alpha:]][-.\'[:alpha:] ]*[[:alpha:]][ ]*\/[ ]*[[:alpha:]][-.\'[:alpha:] ]*[[:alpha:]])\b#u", $body);
        $travellers = array_map(function ($s) {return trim(preg_replace("/\s+/", ' ', $s)); }, $travellers);

        if (!empty($travellers)) {
            foreach ($email->getItineraries() as $value) {
                $value->general()->travellers($travellers);
            }
        }

        if (preg_match("#\n\s*\W?" . $this->preg_implode($this->t("概算合計料金")) . "\W*[ ]*(?<amount>\d[\d,. ]+)[ ]*(?<curr>\D{1,4})\b#u", $body, $m)) {
            $email->price()
                ->total($this->amount($m['amount']))
                ->currency($this->currency($m['curr']), true, true);
        }
    }

    private function flight(Email $email, string $stext): void
    {
        foreach ($email->getItineraries() as $value) {
            if ($value->getType() == 'flight') {
                /** @var Flight $flight */
                $flight = $value;

                break;
            }
        }

        if (!isset($flight)) {
            $f = $email->add()->flight();
            // General
            $f->general()
                ->noConfirmation();

            $s = $f->addSegment();
        } else {
            $s = $flight->addSegment();
        }

        $confNo = $this->re("#" . $this->preg_implode($this->t("予約番号")) . "[:/]([A-Z\d]{5,7})\b#u", $stext);

        if (!empty($confNo)) {
            $s->airline()->confirmation($confNo);
        }

        if (preg_match("#^\s*(?<dDate>.+?\s*\d{1,2}:\d{1,2})\s+(?<dname>.+?)\/(?<dCode>[A-Z]{3})\s*(?<dTerm>{$this->t('ターミナル')}.+)?\s+"
                . "(?<aDate>.+\s*\d{1,2}:\d{1,2})\s+(?<aName>.+)/(?<aCode>[A-Z]{3})\s*(?<aTerm>{$this->t('ターミナル')}.+)?\s+(?<fn>.+)#u", $stext, $m)) {
            // Departure
            $s->departure()
                ->code($m['dCode'])
                ->name($m['dname'])
                ->date($this->normalizeDate($m['dDate']));

            if (!empty($m['dTerm'])) {
                $s->departure()->terminal(trim(str_ireplace($this->t('ターミナル'), '', $m['dTerm'])));
            }

            // Arrival
            $s->arrival()
                ->code($m['aCode'])
                ->name($m['aName']);
//            if (preg_match("#^\s*\d{1,2}:\d{1,2}#", $m['aDate'])) {
//                $s->arrival()->date(strtotime($m['aDate'], $s->getDepDate()));
//            } else {
            $s->arrival()->date($this->normalizeDate($m['aDate']));
//            }

            if (!empty($m['aTerm'])) {
                $s->arrival()->terminal(trim(str_ireplace($this->t('ターミナル'), '', $m['aTerm'])));
            }

            if (preg_match("#\((?<al>[A-Z][A-Z\d]|[A-Z\d][A-Z])\)(?<fn>\d{1,5})\s*(?<cabin>.+)\s+\(.*?\s*\n?.*(?<class>[A-Z]{1,2})\s*\)#", $stext, $mat)) {
                // Airline
                $s->airline()
                    ->name($mat['al'])
                    ->number($mat['fn']);
                $s->extra()
                    ->cabin($mat['cabin'])
                    ->bookingCode($mat['class']);
            } elseif (preg_match('/\((?<al>[A-Z][A-Z\d]|[A-Z\d][A-Z])\)\s*(?<cabin>.+) \(.*?\s*\n?.*(?<class>[A-Z]{1,2})\s*\)/', $stext, $mat)) {
                $s->airline()
                    ->name($mat['al'])
                    ->noNumber();
                $s->extra()
                    ->cabin($mat['cabin'])
                    ->bookingCode($mat['class']);
            } elseif (preg_match('/\((?<al>[A-Z][A-Z\d]|[A-Z\d][A-Z])\)\s*(?<fn>\d{1,5})\s*予約OK/', $stext, $mat)) {
                $s->airline()
                    ->name($mat['al'])
                    ->number($mat['fn']);
            }

            if (preg_match("#(" . $this->t("機材") . ":(?<aircraft>.+))?\s+" . $this->t("飛行時間") . ":(?<duration>.+?)(?:\s{2,}|\n|$)#", $stext, $m)) {
                $s->extra()
                    ->aircraft($m['aircraft'] ?? null, true, true)
                    ->duration($m['duration']);
            }

            if (preg_match('/ (?<seat>\d{1,5}[A-Z]\b)/', $stext, $m)) {
                $s->extra()
                    ->seat($m[1]);
            }
        } else {
            $this->parseError = true;
        }
    }

    private function findTrainSegment(Email $email, string $stext, $name = ''): TrainSegment
    {
        if (preg_match("#^\s*JR #", $name)) {
            $name = 'jrg';
        } elseif (preg_match("#^\s*(?:京成|KEISEI) #", $name)) {
            $name = 'keisei';
        } else {
            $name = '';
        }

        foreach ($email->getItineraries() as $value) {
            if ($value->getType() == 'train') {
                /** @var Train $value */
                if ((empty($name) && empty($value->getProviderCode()))
                    || (empty($name) && $name == $value->getProviderCode())) {
                    return $value->addSegment();
                }
            }
        }
        $t = $email->add()->train();

        if (!empty($name)) {
            $t->setProviderCode($name);
        }

        // General
        if (preg_match_all("#\W?({$this->preg_implode($this->t("予約番号"))})\W([A-Z\d]{4,})\b#u", $stext, $m)) {
            foreach ($m[2] as $key => $number) {
                $t->general()->confirmation($number, $m[1][$key]);
            }
        } else {
            $t->general()->noConfirmation();
        }

        return $t->addSegment();
    }

    private function train(Email $email, string $stext): void
    {
        $region = '';

        if (preg_match("#^\s*.+?[ ]*\d+:\d+ .+\s*\n+\s*(.+?[ ]*)?\d+:\d+\s*.+\n+"
                . "\s*(?<service>.+?)(?<num>\d+)(?:[ ]*号.*|[^\d\n]+OK)\n+\s*.* (?<seat>(?:\d{1,3}-)?\d{1,3}[A-Z])\s+#u", $stext, $m)) {
            $s = $this->findTrainSegment($email, $stext, $m['service']);
            $s->extra()
                ->service($m['service'])
                ->number($m['num'])
                ->seat($m['seat']) // 22A    |    06-10A
            ;
        } elseif (preg_match("#^\s*.+?[ ]*\d+:\d+ .+\n+\s+.*?[ ]*\d+:\d+[ ]*\n?.+\n+"
                . "\s*(?<service>.+?)(?<num>\d+)(?:[ ]*号.*|[^\d\n]+OK)\n+#ui", $stext, $m)) {
            $s = $this->findTrainSegment($email, $stext, $m['service']);
            $s->extra()
                ->service($m['service'])
                ->number($m['num']);
        } else {
            $s = $this->findTrainSegment($email, $stext);
            $s->extra()->noNumber();
        }

        if (preg_match($this->pattern['trainSegment'], $stext, $m)) {
            if ($s->getServiceName() === '京成 スカイライナー') {
                // for Google, to help find correct address
                $region = ', JP';
            }

            // Departure
            $s->departure()
                ->date($this->normalizeDate($m['dDate'] . ' ' . $m['dTime']));

            if (preg_match("/^\s*([A-Z]{3})\s*$/", $m['dName'], $matches)) {
                $s->departure()->code($matches[1]);
            } else {
                $s->departure()->name($m['dName'] . $region);
            }

            // Arrival
            $s->arrival()
                ->date((!empty($m['aDate'])) ? $this->normalizeDate($m['aDate'] . ' ' . $m['aTime']) : $this->normalizeDate($m['dDate'] . ' ' . $m['aTime']));

            if (preg_match("/^\s*([A-Z]{3})\s*$/", $m['aName'], $matches)) {
                $s->arrival()->code($matches[1]);
            } else {
                $s->arrival()->name($m['aName'] . $region);
            }
        } else {
            $this->parseError = true;
        }
    }

    private function hotel(Email $email, string $stext): void
    {
        $h = $email->add()->hotel();

        // General
        if (preg_match("#\W?" . $this->preg_implode($this->t('予約番号')) . "\W([A-Z\d]{5,})\b#u", $stext, $m)) {
            $h->general()
                ->confirmation($m[1]);
        } else {
            $h->general()
                ->noConfirmation();
        }

        $h->general()
            ->cancellation($this->re("#\n\s*\W?" . $this->t('キャンセル期限') . "\W(.+)#u", $stext), true, true);

        $re1 = "#^\s*(?<inDate>.+)\s*" . $this->t('チェックイン') . "\s+(?<outDate>.+)\s*" . $this->t('チェックアウト') . "\s*\n"
            . "[ ]*(?<nameStr>\S.+?)(?:\n|[ ]{3,})(?<addr>[\S\s]+?)\n\s*(?:TEL|FAX)#u";
        $re2 = "#^\s*" . $this->t('チェックイン') . "(?<inDate>.+)\s+" . $this->t('チェックアウト') . "(?<outDate>.+)\s*\n"
            . "[ ]*(?<nameStr>\S.+?)(?:\n|[ ]{3,})(?<addr>[\S\s]+?)\n\s*(?:TEL|FAX)#um";
        $re3 = '/^\s*(?<inDate>.+)\s*チェックイン\s+(?<outDate>.+)\s*チェックアウト\s*\n[ ]*(?<nameStr>\S.+?)(?:\n|[ ]{3,})[\S\s]+?\n\s*(?:住所\:|住所：)(?<addr>.+)/ui';
        $re4 = '/^\s*(?<inDate>.+)\s*チェックイン\s+(?<outDate>.+)\s*チェックアウト\s*\n[ ]*(?<nameStr>\S.+?)(?:\n|[ ]{3,}[^\n]+)\n\n/ui';

        if (preg_match($re1, $stext, $m) || preg_match($re2, $stext, $m) || preg_match($re3, $stext, $m) || preg_match($re4, $stext, $m)) {
            if (isset($m['addr']) && !empty($m['addr'])) {
                $addrArr = array_filter(array_map("trim", explode("\n", $m['addr'])));
            } else {
                $addrArr = [];
            }
            // last elem is address
            if (!isset($m['addr'])) {
                $h->hotel()
                    ->noAddress();
            } else {
                $h->hotel()
                    ->address(array_pop($addrArr));
            }

            if (count($addrArr) > 0) {
                if (count($addrArr) > 2) {
                    $this->logger->debug("need to correct or extended parsing of hotels");

                    return;
                }
                $info = implode("\n", $addrArr);

                if (preg_match("#(?<guest>\d+)" . $this->t('名様') . ".*$#", $info, $mat)) {
                    $h->booked()
                        ->guests($mat['guest']);
                }

                if (preg_match("#(?<type>\w+)\d+" . $this->t('名様') . "#u", $info, $mat)) {
                    $r = $h->addRoom();
                    $r->setType($mat['type']);

                    if (preg_match('/1泊あたり料金[ ]*\:[ ]*[A-Z]{3} RATE ([\d\.]+[ ]*[A-Z]{3})/u', $stext, $match)) {
                        $r->setRate($match[1]);
                    }
                }

                if (preg_match("#(予約OK|Confirmaed)#", $info, $mat)) {
                    $h->general()->status($mat[1]);
                }
            }
            $h->hotel()
                ->phone($this->re("#\n\s*TEL:([\d \-\+\(\)\.]{5,})#", $stext) ?? null, true, true)
                ->fax($this->re("#\n\s*FAX:([\d \-\+\(\)\.]{5,})#", $stext) ?? null, true, true);

            $h->booked()
                ->checkIn($this->normalizeDate($m['inDate']))
                ->checkOut($this->normalizeDate($m['outDate']));

            if (preg_match("#^(?<name>.+\w) (?<type>\w+)?(?<guest>\d+)" . $this->t('名様') . "#u", $m['nameStr'], $mat)) {
                $h->hotel()
                    ->name($mat['name']);
                $h->booked()
                    ->guests($mat['guest']);

                if (isset($mat['type']) && !empty($mat['type'])) {
                    $r = $h->addRoom();
                    $r->setType($mat['type']);
                }
            } else {
                $h->hotel()
                    ->name($m['nameStr']);
            }
        } else {
            $this->parseError = true;
        }

        $this->detectDeadLine($h);

        if (preg_match("#\n\s*\W*" . $this->preg_implode($this->t("料金")) . "\W*[ ]*(?<amount>\d[\d,. ]+)[ ]*(?<curr>\D{1,4})\b#u", $stext, $m)) {
            $h->price()
                ->total($this->amount($m['amount']))
                ->currency($this->currency($m['curr']), true, true);
        }
    }

    private function detectDeadLine(\AwardWallet\Schema\Parser\Common\Hotel $h): bool
    {
        if (empty($cancellationText = $h->getCancellation())) {
            return false;
        }

        if (preg_match("#取消料発生日：(?<month>\d{1,2})/(?<day>\d{1,2})$#u", $cancellationText, $m)
            || preg_match("#^現地時間(?<month>\d{1,2})月(?<day>\d{1,2})日(?<time>\d{1,2})時まで$#u", $cancellationText, $m)
        ) {
            $dDate = strtotime($m['day'] . '.' . $m['month'] . '.' . date("Y", $h->getCheckInDate())
                . (empty($m['time']) ? '' : ' ' . $m['time'] . ':00'));

            if (!empty($dDate) && strtotime("-1 month", $dDate) > $h->getCheckInDate()) {
                $dDate = strtotime("-1 year", $dDate);
            }
            $h->booked()->deadline($dDate);

            return true;
        }

        return false;
    }

    private function rental(Email $email, string $stext): void
    {
        $r = $email->add()->rental();

        // General
        if (preg_match("#\W?" . $this->preg_implode($this->t("予約番号")) . "\W([A-Z\d]{5,})\b#u", $stext, $m)) {
            $r->general()
                ->confirmation($m[1]);
        } else {
            $r->general()
                ->noConfirmation();
        }

        if (preg_match("#^\s*(?<inDate>.+\s*\d+:\d+)[ ]*(?<inAdrr>.+)\s+(?<outDate>.+\s*\d+:\d+)[ ]*(?<outAdrr>.+)\s+(?<company>.+?)[ ]{2,}(?<type>.+?)[ ]{2,}\s*.*\n#u", $stext, $m)
                || preg_match("#^\s*(?<inDate>.+\s*\d+:\d+)[ ]*(?<inAdrr>.+)\s+(?<outDate>.+\s*\d+:\d+)[ ]*(?<outAdrr>.+)\s+(?<company>.+?)[ ](?<type>.+?)[ ]\w+\n#u", $stext, $m)) {
            $r->pickup()
                ->date($this->normalizeDate($m['inDate']))
                ->location($m['inAdrr']);

            $r->dropoff()
                ->date($this->normalizeDate($m['outDate']))
                ->location($m['outAdrr']);

            $r->car()
                ->type(trim($m['type']), true, true);

            $r->extra()
                ->company($m['company']);
        } else {
            $this->parseError = true;
        }

        if (preg_match("#\n\s*\W*" . $this->t("概算料金") . "\W*(?:BAR RATE)?[ ]*(?<amount>\d[\d,. ]+)[ ]*(?<curr>\D{1,4})\b#u", $stext, $m)) {
            $r->price()
                ->total($this->amount($m['amount']))
                ->currency($this->currency($m['curr']), true, true);
        }
    }

    private function transfer(Email $email, string $stext): void
    {
        foreach ($email->getItineraries() as $value) {
            if ($value->getType() == 'transfer') {
                /** @var Transfer $transfer */
                $transfer = $value;

                break;
            }
        }

        if (!isset($transfer)) {
            $t = $email->add()->transfer();
        } else {
            $t = $transfer;
        }
        $s = $t->addSegment();

        // General
        if (preg_match("#\W?" . $this->preg_implode($this->t("予約番号")) . "\W([A-Z\d]{5,})\b#u", $stext, $m)) {
            $t->general()
                ->confirmation($m[1]);
        } else {
            $t->general()
                ->noConfirmation();
        }

        if (preg_match("#^\s*(?<dDate>.+\d+:\d+)[ ]*(?<dAdrr>.+)\s+(?<aDate>.+\d+:\d+)[ ]*(?<aAdrr>.+)#u", $stext, $m)) {
            $s->departure()
                ->date($this->normalizeDate($m['dDate']));

            if (preg_match("#^\s*[A-Z]{3}\s*$#", $m['dAdrr'])) {
                $s->departure()
                    ->code(trim($m['dAdrr']));
            } else {
                $s->departure()
                    ->name($m['dAdrr']);
            }

            $s->arrival()
                ->date($this->normalizeDate($m['aDate']));

            if (preg_match("#^\s*[A-Z]{3}\s*$#", $m['aAdrr'])) {
                $s->arrival()
                    ->code(trim($m['aAdrr']));
            } else {
                $s->arrival()
                    ->name($m['aAdrr']);
            }
        } else {
            $this->parseError = true;
        }
    }

    private function event(Email $email, string $stext): void
    {
        $ev = $email->add()->event();

        // General
        if (preg_match("#\W?" . $this->preg_implode($this->t("予約番号")) . "\W([A-Z\d]{5,})\b#u", $stext, $m)) {
            $ev->general()
                ->confirmation($m[1]);
        } else {
            $ev->general()
                ->noConfirmation();
        }

        if (preg_match("#" . $this->preg_implode($this->t("同行者")) . "[：: ]+([A-Z ]+/[A-Z ]+)\b#u", $stext, $m)) {
            $ev->general()
                ->traveller($m[1]);
        }
        $ev->place()->type(EVENT_EVENT);

        if (preg_match("#^\s*(?<dDate>.+\d+:\d+)\s*(?<dAdrr>.+)\s*\n(?<aDate>.+\d+:\d+)\s*(?<aAdrr>.+)\n\s*[^\w\s*]*(?<name>.+?)[^\w\s*]*(\s{2,}|$)#u", $stext, $m)) {
            $ev->booked()
                ->start($this->normalizeDate($m['dDate']))
                ->end($this->normalizeDate($m['aDate']));

            $ev->place()
                ->address($m['aAdrr'])
                ->name($m['name']);
        } else {
            $this->parseError = true;
        }

        if (preg_match("#\W" . $this->t("概算料金") . "\W*[ ]*(?<amount>\d[\d,. ]+)[ ]*(?<curr>\D{1,4})\b#u", $stext, $m)) {
            $ev->price()
                ->total($this->amount($m['amount']))
                ->currency($this->currency($m['curr']), true, true);
        }
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

    private function res($re, $str, $c = 1)
    {
        preg_match_all($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function preg_implode($field)
    {
        if (!is_array($field)) {
            $field = [$field];
        }

        return '(?:' . implode("|", array_map(function ($v) {return preg_quote($v, '#'); }, $field)) . ')';
    }

    private function normalizeDate($str)
    {
        $in = [
            "#^\s*(\d+)\s*年\s*(\d+)\s*月\s*(\d+)\s*日\s*\(\w*\)\s+(\d+:\d+)\s*$#u", // 19年01月23日(水)  12:50
            "#^\s*(\d+)\s*年\s*(\d+)\s*月\s*(\d+)\s*日\s*\(\w*\)\s*$#u", // 19年01月23日(水)
            "#^\s*(\d+)[\s,.]+([^\d\s\.\,]+)[\s\.\,]+(\d{4})\s*[^\d\s]+\s+(\d+:\d+)\s*$#u", // 23,Jan.2019 Wed.  12:50
            "#^\s*(\d+)[\s,.]+([^\d\s\.\,]+)[\s\.\,]+(\d{4})\s*[^\d\s]+\s*$#u", // 23,Jan.2019 Wed.
        ];
        $out = [
            "$3.$2.20$1 $4",
            "$3.$2.20$1",
            "$1 $2 $3 $4",
            "$1 $2 $3",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return strtotime($str);
    }

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

    private function currency($currency)
    {
        $sym = [
            '円'=> 'JPY',
            '€'=> 'EUR',
            '$'=> 'USD',
            '£'=> 'GBP',
        ];

        if ($code = $this->re("#(?:^|\s)([A-Z]{3})(?:$|\s)#", $currency)) {
            return $code;
        }

        foreach ($sym as $f => $r) {
            if (strpos($currency, $f) !== false) {
                return $r;
            }
        }

        return null;
    }

    private function amount($price)
    {
        $s = trim(str_replace(",", "", $price));

        if (is_numeric($s)) {
            return (float) $s;
        }

        return null;
    }

    private function striposArray($haystack, $arrayNeedle)
    {
        foreach ($arrayNeedle as $needle) {
            if (stripos($haystack, $needle) !== false) {
                return true;
            }
        }

        return false;
    }

    private function mb_strposAll($haystack, $needle)
    {
        if (is_string($needle)) {
            return mb_strpos($haystack, $needle);
        } elseif (is_array($needle)) {
            foreach ($needle as $ndl) {
                if (mb_strpos($haystack, $ndl) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    // search last pos
    private function str_rpos($haystack, $needle, $start = 0)
    {
        $tempPos = mb_strpos($haystack, $needle, $start);

        if ($tempPos === false) {
            if ($start == 0) {
                //Needle not in string at all
                return false;
            } else {
                //No more occurances found
                return $start - mb_strlen($needle);
            }
        } else {
            //Find the next occurance
            return $this->str_rpos($haystack, $needle, $tempPos + mb_strlen($needle));
        }
    }
}
