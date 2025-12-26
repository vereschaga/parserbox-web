<?php

namespace AwardWallet\Engine\jtb\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Engine\WeekTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class ItineraryPdf extends \TAccountChecker
{
    public $mailFiles = "jtb/it-29657829.eml, jtb/it-44485778.eml, jtb/it-46117484.eml, jtb/it-46117892.eml"; //+ emails with pdf fom jtb/Cwt

    public static $dictionary = [
        "en" => [
            //            "JTB-CWT Business Travel Solutions" => "",
            //            ":::Useful links! :::" => "",
            //            'Useful links' => '',
            //            "Transportation" => "",
            //            "Hotel" => "",
            "Transfer/CarRental etc." => ["Transfer/CarRental etc.", "Car Rental/Transfer etc."],
            //            "Others" => "",
            // Flight
            //            "Dep.Terminal:" => "",
            //            "Arr.Terminal:" => "",
            //            "Equipment" => "",
            //            "Journey time" => "",
            // Hotel
            //            "Confirmation#" => "",
            //            "Cancel Policy" => "",
            //            "In" => "",
            //            "Out" => "",
            //            "Tel" => "",
            //            "Fax" => "",
            //            "+TAX" => "",
            //            "■同室；" => "",// add guest
            // Transfer
            //            "ANA Free Limousine" => "ANA Free Limousine", // need to check
        ],
        "ja" => [
            "JTB-CWT Business Travel Solutions" => "株式会社JTBﾋﾞｼﾞﾈｽﾄﾗﾍﾞﾙｿﾘｭｰｼｮﾝｽﾞ",
            ":::Useful links! :::"              => ":::リンク集のご案内:::",
            'Useful links'                      => '払戻に関するご案内',
            "Transportation"                    => "交通機関",
            "Hotel"                             => "ホテル",
            "Transfer/CarRental etc."           => "レンタカー/送迎他",
            "Others"                            => "その他",
            // Flight
            "Dep.Terminal:" => "出発ターミナル:",
            "Arr.Terminal:" => "到着ターミナル:",
            "Equipment"     => "機材",
            "Journey time"  => "飛行時間",
            // Hotel
            "Confirmation#" => "Confirmation#",
            "Cancel Policy" => "キャンセル期限",
            "In"            => "In",
            "Out"           => "Out",
            "Tel"           => "Tel",
            "Fax"           => "Fax",
            "+TAX"          => "+TAX",
            // Transfer
            "ANA Free Limousine" => ["ANAお帰りハイヤー", 'の前でお待ちしてます', '弊社営業時間'],
            "■同室；"               => "■同室；",
            "Confirmed"          => "予約OK",
        ],
    ];

    private $detectSubject = [
        '【予約内容回答】',
        '【eチケットお客様控】',
    ];
    private $detectCompany = [
        '株式会社JTBﾋﾞｼﾞﾈｽﾄﾗﾍﾞﾙｿﾘｭｰｼｮﾝｽﾞ',
        '@jtb-cwt.com',
        'www.jtb-cwt.com',
        'JTB-CWT Business Travel',
    ];
    private $detectBody = [
        "ja" => ['スケジュール表'],
        "en" => ['Itinerary', 'Transportation'], // last
    ];
    private $pdfPattern = '.+\.pdf';
    private $lang = '';
    private $year;

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->year = date('Y', strtotime($parser->getHeader('date')));
        $pdfs = $parser->searchAttachmentByName($this->pdfPattern);

        foreach ($pdfs as $pdf) {
            $textPdf = \PDF::convertToText($parser->getAttachmentBody($pdf));
            // $this->logger->debug($textPdf);
            if (empty($textPdf)) {
                continue;
            }

            foreach ($this->detectBody as $lang => $detectBody) {
                foreach ($detectBody as $dBody) {
                    if (stripos($textPdf, $dBody) !== false && preg_match("#\b{$dBody}\b#u", $textPdf) > 0) {
                        $this->lang = $lang;
                        $this->parseEmail($email, $textPdf);

                        continue 3;
                    }
                }
            }
        }
        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@jtb-cwt.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (self::detectEmailFromProvider($headers['from']) !== true) {
            return false;
        }

        foreach ($this->detectSubject as $phrase) {
            if (is_string($phrase) && stripos($headers['subject'], $phrase) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfPattern);

        foreach ($pdfs as $pdf) {
            $textPdf = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (empty($textPdf)) {
                continue;
            }

            $detectProvider = false;

            foreach ($this->detectCompany as $phrase) {
                if (stripos($textPdf, $phrase) !== false) {
                    $detectProvider = true;

                    break;
                }
            }

            if (!$detectProvider) {
                continue;
            }

            foreach ($this->detectBody as $detectBody) {
                foreach ($detectBody as $dBody) {
                    if (stripos($textPdf, $dBody) !== false) {
                        return true;
                    }
                }
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
        return count(self::$dictionary);
    }

    private function parseEmail(Email $email, string $text)
    {
        $this->logger->notice(__METHOD__);
        // Travel agency
        $email->obtainTravelAgency();
        $phone = $this->re("#" . $this->preg_implode($this->t("JTB-CWT Business Travel Solutions")) . "\n\s*TEL[ ]([\d\+\-\(\) ]{5,})\s*\n#u", $text);

        if ((float) preg_replace("#[^\d]+#", '', $phone) == 0) {
            unset($phone);
        }

        if (!empty($phone)) {
            $email->ota()
                ->phone($phone, $this->t("JTB-CWT Business Travel Solutions"));
        }

        $textCut = strstr($text, $this->t(":::Useful links! :::"), true);

        if (false === $textCut) {
            $textCut = strstr($text, $this->t('Frequent Flyer Program'), true);
        }

        if (false === $textCut) {
            $textCut = strstr($text, $this->t('Useful links'), true);
        }

        if (false === $textCut) {
            $textCut = strstr($text, $this->t('Emergency Contact'), true);
        }

        if (false === $textCut) {
            $textCut = strstr($text, $this->t('【WEBチェックイン・飛行機の遅延情報が大好評】'), true);
        }

        if (false === $textCut) {
            $textCut = strstr($text, $this->t('渡航先ビザ情報(Visa Information)'), true);
        }

        $dateFormats = [
            'ja' => '\d+年',
            'en' => '[a-zA-Z]{3,4}/\d{1,2}/[a-zA-Z]{3,4}',
        ];
        // Transport
        $transportText = $this->re("#" . $this->preg_implode($this->t("Transportation")) . "(?:\([^\)\n]+\))?\s*\n([\s\S]+?)("
                . "\n\s*" . $this->preg_implode($this->t("Hotel")) . "(?:\([^\)\n]+\))?\s*\n|"
                . "\n\s*" . $this->preg_implode($this->t("Transfer/CarRental etc.")) . "(?:\([^\)\n]+\))?\s*\n|"
                . "\n\s*" . $this->preg_implode($this->t("Others")) . "(?:\([^\)\n]+\))?\s*\n|\n{3,}\-{10,}|"
                . "$)#u", $textCut);

//        $this->logger->debug('Transport Text = '.$transportText);
        if (!empty($transportText)) {
            $regexp = "#(?:^|\n)([ ]*" . $dateFormats[$this->lang] . ".* .+\n\s*.+)#u";

            if (false !== strpos($transportText, 'お客様手配')) {
                $transportText = strstr($transportText, 'お客様手配', true);
            } // its visa information block

            if (false !== strpos($transportText, '渡航先ビザ情報')) {
                $transportText = strstr($transportText, '渡航先ビザ情報', true);
            }
            $segments = $this->split($regexp, $transportText);

            // FE: it-46117484.eml
            foreach ($segments as $i => $stext) {
                $rows = explode("\n", $stext);

                if (count($rows) <= 2) {
                    $mem[] = $i;
                }
            }

            if (isset($mem)) {
                foreach ($mem as $num) {
                    if (isset($segments[$num], $segments[$num + 1])) {
                        $segments[$num] .= "\n" . $segments[$num + 1];
                        unset($segments[$num + 1]);
                    }
                }
            }
//            $this->logger->debug('Transport Regexp = '.$regexp);
//            $this->logger->debug('Transport Segments = '.var_export($segments, true));
            foreach ($segments as $stext) {
                if ((preg_match("#^[ ]*.*?/[A-Z]{3}\s+#u", $stext)
                        || preg_match("#" . $this->t("Dep.Terminal:") . "#u", $stext)
                        || preg_match("#" . $this->t("Arr.Terminal:") . "#u", $stext))
                    && preg_match('/\d{1,2}:\d{2}/', $stext)
                ) {
                    $this->flight($email, $stext);

                    continue;
                }

                if (preg_match("#^\s*.+\d+:\d+ .+\n+.+\d+:\d+ .+\n.*\d.*\n.* \d{1,3}[A-Z]\s+#u", $stext)) {
                    $this->train($email, $stext);

                    continue;
                }

                if (preg_match("#^\s*[^\n]+\d+:\d+ [^\n]+\n+[^\n]+(?:\d+:\d+)? .+" . $this->opt($this->t("ANA Free Limousine")) . "#us", $stext)) {
                    $this->transfer($email, $stext);

                    continue;
                }
                // 19年10月25日(金) 18:05 INDIANAPOLIS, IN US                デルタ航空
                //             19:57 SEATTLE, WA US                     DL 744 エコノミ－ (E) 予約OK
                if (preg_match("#^[ ]*.*\n\s*[ ]*\S.+?[ ]{2,}[A-Z\d]{2} \d{1,5}\b#u", $stext) /*&& !preg_match('/[ ]+\d{1,2}:\d{2}[ ]+/', $stext)*/) {
                    $this->flight($email, $stext);

                    continue;
                }

                if (preg_match("#^\s*.+\d+:\d+ .+\n+.+\d+:\d+ .+#u", $stext)) {
                    $this->train($email, $stext);

                    continue;
                }
                $this->logger->debug("transport segment is not detected " . $stext);
                $f = $email->add()->flight(); // for 100% failed

                return false;
            }
        }

        // Hotel
        $hotelText = $this->re("#" . $this->preg_implode($this->t("Hotel")) . "(?:\([^\)\n]+\))?\s*\n([\s\S]+?)("
                . "\n\s*" . $this->preg_implode($this->t("Transfer/CarRental etc.")) . "(?:\([^\)\n]+\))?\s*\n|"
                . "\n\s*" . $this->preg_implode($this->t("Others")) . "(?:\([^\)\n]+\))?\s*\n|\n{3,}\-{10,}|"
                . "$)#", $text);
//        $this->logger->debug('Hotel Text = '.$hotelText);
        if (!empty($hotelText)) {
            $regexp = "#(?:^|\s*\n)([ ]*.*[ ]+" . $this->t('In') . "[ ]{2,}.*\n.*\n?.*[ ]+" . $this->t('Out') . "[ ]{2,})#u";
            $segments = $this->split($regexp, $hotelText);
//            $this->logger->debug('Hotel Regexp = '.$regexp);
//            $this->logger->debug('Hotel Segments = '.$segments);
            foreach ($segments as $stext) {
                $this->hotel($email, $stext);
            }
        }

        // Rental
        $rentalText = $this->re("#" . $this->preg_implode($this->t("Transfer/CarRental etc.")) . "(?:\([^\)\n]+\))?\s*\n([\s\S]+?)("
                . "\n\s*" . $this->preg_implode($this->t("Others")) . "(?:\([^\)\n]+\))?\s*\n|\n{3,}\-{10,}|"
                . "$)#", $text);
//        $this->logger->debug('Rental Text = '.$rentalText);
        if (!empty($rentalText)) {
            $regexp = "#(.+ \d{1,2}:\d{2}.+\s+.*\b\d{1,2}:\d{2})#u";
            $segments = $this->split($regexp, $rentalText);
//            $this->logger->debug('Rental Regexp = '.$regexp);
//            $this->logger->debug('Rental Segments = '.$segments);
            foreach ($segments as $stext) {
                $this->rental($email, $stext);
            }
        }

        // Others
        $othersText = $this->re("#" . $this->preg_implode($this->t("Others")) . "(?:\([^\)\n]+\))?\s*\n([\s\S]+?)(?:\n{3,}\-{10,})?$#u", $text);

        $textCut = strstr($othersText, $this->t('渡航先ビザ情報(Visa Information)'), true);

        if (!empty($textCut)) {
            $othersText = $textCut;
        }
//        $this->logger->debug('Others Text = '.$othersText);
        if (!empty($othersText)) {
            $regexp = "#(.+ \d{1,2}:\d{2} .+\n+.+)#u";
            $segments = $this->split($regexp, $othersText);
//            $this->logger->debug('Others Regexp = '.$regexp);
//            $this->logger->debug('Others Segments = '.$segments);
            foreach ($segments as $stext) {
                if (preg_match("#(※お帰りハイヤー)#u", $stext)) {
                    $this->logger->debug('not detect type segment. Skip');

                    continue;
                }

                if (preg_match("#^\s*.+\d+:\d+ .+\n+.+\d+:\d+ .+\n.*\d.*\n.* \d{1,3}[A-Z]\s+#u", $stext)) {
                    $this->train($email, $stext);

                    continue;
                }

                if (preg_match("#^\s*[^\n]+\d+:\d+ .+\n+.+" . $this->opt($this->t("ANA Free Limousine")) . "#us", $stext)) {
                    $this->transfer($email, $stext);

                    continue;
                }

                if (preg_match("#^\s*.+\d+:\d+ .+\n+.+\d+:\d+ .+#u", $stext)) {
                    $this->train($email, $stext);

                    continue;
                }

                if (preg_match("#(お送り先のご住所をご指示下さい。|ビザアシストサービス手配済み|変更依頼中)#u", $stext)) {
                    $this->logger->debug('skip segment. not enough data');

                    continue;
                }
                $this->logger->debug("others segment is not detected " . $stext);
                $f = $email->add()->flight(); // for 100% failed

                return false;
            }
        }

        switch ($this->lang) {
            case 'en':
                $travellers = $this->res("#^\s*([A-Za-z \-]+) \(Departure Date: .+\)\s*$#um", $text);

                break;

            case 'ja':
                $travellers = $this->res("#^\s*[\w\s]+?\(([A-Za-z \-]+)\)[ ]*[\（\(].+ご出発[\）\)]\s*$#um", $text);

                break;
        }

        if (!empty($travellers)) {
            foreach ($email->getItineraries() as $value) {
                $value->general()->travellers($travellers);
            }
        }

        return $email;
    }

    private function flight(Email $email, string $stext)
    {
//        $this->logger->debug('Flight Segment = '.print_r( $stext,true));

        $finded = false;

        foreach ($email->getItineraries() as $value) {
            if ($value->getType() == 'flight') {
                $s = $value->addSegment();
                $finded = true;
                $f = $value;

                break;
            }
        }
        $tickets = [];

        foreach ($email->getItineraries() as $value) {
            if ($value->getType() == 'flight') {
                $ticket = [];

                foreach ($value->getTicketNumbers() as $ticketNumber) {
                    $ticket[] = $ticketNumber[0];
                }
                $tickets = array_merge($tickets, $ticket);
            }
        }

        if ($finded == false) {
            $f = $email->add()->flight();

            // General
            $f->general()
                ->noConfirmation();

            $s = $f->addSegment();
        }

        $regexp = "#^\s*(?<dDate>.+ \d{1,2}:\d{1,2})[ ]+(?<dname>.+?)[ ]*(" . $this->preg_implode($this->t("Dep.Terminal:")) . "(?<dTerm>.*?)|[ ]{2})([ ].*[\s\S]*?\n|\n[\s\S]*?\n|\n)"
                . "(?<aDate>.+ \d{1,2}:\d{1,2})[ ]+(?<aName>.+?)[ ]*(" . $this->preg_implode($this->t("Arr.Terminal:")) . "(?<aTerm>.*?)|[ ]{2})\s+(?<fn>[A-Z\d]{2}[ ]*(?:\d{1,5})?.+)#u";
//        $this->logger->debug('Flight Segment Regexp = '.print_r( $regexp,true));

        if (preg_match($regexp, $stext, $m)) {
            // Departure
            $s->departure()
                ->noCode()
                ->name($m['dname'])
                ->date($this->normalizeDate($m['dDate']))
                ->terminal($m['dTerm'] ?? null, true, true)
            ;

            // Arrival
            $s->arrival()
                ->noCode()
                ->name($m['aName'])
                ->terminal($m['aTerm'] ?? null, true, true)
            ;

            if (preg_match("#^\s*(\d+:\d+)\s*$#", $m['aDate']) && !empty($s->getDepDate())) {
                $s->arrival()
                    ->date(strtotime(trim($m['aDate']), $s->getDepDate()));
            } else {
                $s->arrival()
                    ->date($this->normalizeDate($m['aDate']));
            }
        } elseif (!preg_match("# \d{1,2}:\d{1,2} #", $stext) && preg_match("#^\s*(?<dDate>.+?)[ ]{2,}(?<dname>.+?)[ ]*(" . $this->preg_implode($this->t("Dep.Terminal:")) . "(?<dTerm>.*?)|)([ ]{2,}.*\s*\n|\s*\n)"
                . "([ ]{0,10}(?<aDate>\S.+?)|[ ]{10,})[ ]{2,}(?<aName>.+?)[ ]*(" . $this->preg_implode($this->t("Arr.Terminal:")) . "(?<aTerm>.*?)|)[ ]+(?<fn>[A-Z\d]{2}[ ]*\d{1,5}.+)#u", $stext, $m)) {
            // Departure
            $s->departure()
                ->noCode()
                ->name($m['dname'])
                ->noDate()
                ->terminal($m['dTerm'] ?? null, true, true)
            ;

            // Arrival
            $s->arrival()
                ->noCode()
                ->name($m['aName'])
                ->noDate()
                ->terminal($m['aTerm'] ?? null, true, true)
            ;
        }

        if (!empty($m['fn']) && preg_match("#(?<al>[A-Z\d]{2})[ ]*(?:(?<fn>\d{1,5}))?\b\s*(?<cabin>\D.+?)?\s*(?:\(\s*(?<class>[A-Z]{1,2})\s*\)|[ ]{1,})#", $m['fn'], $mat)) {
            // Airline
            $s->airline()
                ->name($mat['al']);

            if (!empty($mat['fn'])) {
                $s->airline()
                    ->number($mat['fn']);
            } else {
                $s->airline()
                    ->noNumber();
            }

            if (isset($mat['cabin'])) {
                $s->extra()->cabin($mat['cabin'] ?? null, true, true);
            }

            if (isset($mat['class'])) {
                $s->extra()->bookingCode($mat['class'], true, true);
            }
        }

        $table = $this->re("#\n([^\n]*?{$this->t("Equipment")}.+)#s", $stext);

        if (preg_match("#((.*{$this->t("Equipment")}.+?){$this->t("Journey time")}[:\s]+\d+[ ]+)#u", $table, $m)) {
            $pos[] = 0;
            $pos[] = mb_strlen($m[2]);
            $pos[] = mb_strlen($m[1]);
            $table = $this->SplitCols($table, $pos);

            if (preg_match("#" . $this->t("Equipment") . ":[ ]?(?<aircraft>.+?)\s*(?:Change:|TKT No:|\<現地|$)#uis", $table[0],
                $m)) {
                $rows = explode("\n", $m['aircraft']);

                if (count($rows) > 2) {
                    $rows = array_slice($rows, 0, 2);
                }
                $s->extra()
                    ->aircraft(implode($rows));
            }

            if (preg_match("#" . $this->t("Journey time") . ":[ ]?(?<duration>\d+?)\s+#iu", $table[1], $m)) {
                $s->extra()
                    ->duration($m['duration']);
            }

            if (preg_match("#TKT No:[ ]*(\d{5,})#", $table[0], $m)) {
                if (!in_array($m[1], $tickets)) {
                    $f->issued()->ticket($m[1], false);
                }
            }
        } else {
            if (preg_match("#TKT No:[ ]*(\d{5,})#", $stext, $m)) {
                if (!in_array($m[1], $tickets)) {
                    $f->issued()->ticket($m[1], false);
                }
            }
        }

        if (preg_match("#" . $this->t("Ref") . ":[ ]?([A-Z\d]{5,6})#ui", $stext, $m)) {
            $s->airline()->confirmation($m[1]);
        }

        if (preg_match("#\b(Confirmed|予約OK)\b#ui", $stext, $m)) {
            $s->extra()->status($m[1]);
        }

        if (preg_match('/\d+:\d+ [\s\S]+? \d+:\d+.+\n\s*\w+ (?<seat>\d{1,3}[A-Z]\b)/ui', $stext, $m)) {
            $s->extra()
                ->seat($m[1]);
        } elseif (preg_match("/^[ ]*{$this->t('禁煙通路側')} (?<seat>\d{1,3}[A-Z]\b)/mui", $stext, $m)) {
            $s->extra()
                ->seat($m[1]);
        }

        return $email;
    }

    private function train(Email $email, string $stext)
    {
//        $this->logger->debug('Train Segment = '.print_r( $stext,true));

        $finded = false;

        foreach ($email->getItineraries() as $value) {
            if ($value->getType() == 'train') {
                $s = $value->addSegment();
                $finded = true;

                break;
            }
        }

        if ($finded == false) {
            $t = $email->add()->train();

            // General
            $t->general()
                ->noConfirmation();

            $s = $t->addSegment();
        }

        if (preg_match("#\b(Confirmed|予約OK)\b#iu", $stext, $m)) {
            $s->extra()->status($m[1]);
        }

        $this->logger->warning($stext);

        if (preg_match("#^\s*.+ \d{1,2}:\d{2} .+\n+[ ]*.* \d{1,2}:\d{2} .+?[ ]{2,}(?<service>.+?)(?<num>\d+)(?:号|(?<class>[ ]*エコノミー)[ ]*\((?<bcode>[A-Z])\)).*\n.*[ ]+(?:(?<seat>\d{1,3}[A-Z]))?\s+#us", $stext, $m)) {
            $s->extra()
                ->service($m['service'])
                ->number($m['num'])
                ->seat(empty($m['seat']) ? null : $m['seat'], false, true);

            if (!empty($m['class'])) {
                $s->extra()
                    ->cabin($m['class']);
            }

            if (!empty($m['bcode'])) {
                $s->extra()
                    ->bookingCode($m['bcode']);
            }
        } elseif (preg_match("#^\s*.+ \d{1,2}:\d{2} .+\n+[ ]*.* \d{1,2}:\d{2} .+?[ ]{2,}"
                . "(?<service>.+?)(?<num>\d+)号.*\n#u", $stext, $m)) {
            $s->extra()
                ->service($m['service'])
                ->number($m['num']);
        } elseif (preg_match("#^\s*.+ \d{1,2}:\d{2} .+\n+[ ]*.* \d{1,2}:\d{2} .+\n+[ ]+(?<service>.+?)[ ]*(?<num>\d+)(?:\n|$)#", $stext, $m)) {
            /*
                19年06月25日(火) 15:13 PARIS NORD                                               予約OK
                19年06月25日(火) 16:39 LONDON ST-PANCRAS     2ND
                                   EURO STAR 9039
             */
            $s->extra()
                ->service($m['service'])
                ->number($m['num']);
        } elseif (preg_match("#^\s*.+ \d{1,2}:\d{2} .+\n+[ ]*(.+? )?\d{1,2}:\d{2} .+?[ ]{2,}\D+\n#u", $stext, $m)) {
            $s->extra()->noNumber();
        }

        if (preg_match("#^\s*(?<dDate>\S.+)[ ]+(?<dTime>\d+:\d+)[ ]+(?<dName>.+?)(?:[ ]{2,}.*\n+|\n+)"
                . "\s*(?<aDate>\S.*[ ]+)?(?<aTime>\d+:\d+)[ ]+(?<aName>.+?)(?:[ ]{2,}.*\n+|\n+)#u", $stext, $m)) {
            // Departure
            $s->departure()
                ->date($this->normalizeDate($m['dDate'] . ' ' . $m['dTime']));

            $s->departure()
                ->name($m['dName']);

            if (preg_match("#^\s*[A-Z]{3}\s*$#", $m['dName'])) {
                $s->departure()
                    ->code(trim($m['dName']));
            }

            // Arrival
            $s->arrival()
                ->date((!empty($m['aDate'])) ? $this->normalizeDate($m['aDate'] . ' ' . $m['aTime']) : $this->normalizeDate($m['dDate'] . ' ' . $m['aTime']));

            $s->arrival()
                ->name($m['aName']);

            if (preg_match("#^\s*[A-Z]{3}\s*$#", $m['aName'])) {
                $s->arrival()
                    ->code(trim($m['aName']));
            }
        }

        return $email;
    }

    private function hotel(Email $email, string $stext)
    {
//	    $this->logger->debug('Hotel Segment = '.print_r( $stext,true));

        $h = $email->add()->hotel();

        // General
        if (preg_match("#\W?" . $this->preg_implode($this->t('Confirmation#')) . "\W([A-Z\d]{5,})\b#iu", $stext, $m)) {
            $h->general()
                ->confirmation($m[1]);
        } else {
            $h->general()
                ->noConfirmation();
        }

        $h->general()->cancellation($this->re("#\s+\W?" . $this->t('Cancel Policy') . "\W?[ ]*(.+?)(?:[ ]{2,}|\n|$)#u", $stext), false, true);

        $regexp = "#(?<inDate>.+)[ ]+" . $this->t('In') . "[ ]+(?<city>.+?)[ ]{2,}(?<name>.+?)([ ]{2,}.*\n|\n)[\s\S]*?"
                . "(?<outDate>.+)[ ]+" . $this->t('Out') . "[ ]+(?<roomType>.+?)[ ]{2,}#u";
//        $this->logger->debug('Hotel Segment Regexp = '.print_r( $regexp,true));
        if (preg_match($regexp, $stext, $m)) {
            $this->logger->error($m['name']);

            if (preg_match("#\w#", $m['name']) === 0 || $m['name'] == $this->t('Confirmed')) {
                $h->hotel()
                    ->name($m['city']);
                $noAddress = true;
                $m['city'] = '';
            } else {
                $h->hotel()
                ->name($m['name']);
            }

            $city = $this->re("#(.+?)\s*(?:\(.*\))?\s*$#", $m['city']);

            $h->booked()
                ->checkIn($this->normalizeDate($m['inDate']))
                ->checkOut($this->normalizeDate($m['outDate']));

            $h->addRoom()->setType($m['roomType']);
        }

        if (!empty($city) && preg_match("#[ ]+(?:" . $this->t('Tel') . "|" . $this->t('Fax') . "):.*[\s\S]+?"
                . "[ ]{2,}(?<addr>.*" . $city . ".*)([ ]{2,}|\n)#ui", $stext, $m)) {
            $h->hotel()
                ->address($m['addr']);
        }

        if (empty($h->getAddress())) {
            if (preg_match("#[ ]+(?:" . $this->t('Tel') . "|" . $this->t('Fax') . "):.*[\s\S]+?[ ]{2,}(?<addr>.*)([ ]{2,}|\n)#ui", $stext, $m)) {
                $h->hotel()
                    ->address($m['addr']);
            } elseif (isset($noAddress)) {
                $h->hotel()->noAddress();
            }
        }

        if (preg_match("#[ ]+" . $this->t('Tel') . ":(.+?)(" . $this->t('Fax') . "|[ ]{2,}|\n)#u", $stext, $m)) {
            $h->hotel()
                ->phone($m[1]);
        }

        if (preg_match("#[ ]+" . $this->t('Fax') . ":(.+?)([ ]{2,}|\n)#u", $stext, $m)) {
            if (strlen(trim($m[1])) > 2) {
                $h->hotel()
                    ->fax($m[1]);
            }
        }
        $addGuest = $this->res("#[ ]+" . $this->t('■同室；') . "[ ]*([A-Z\- ]+)\b#ui", $stext);

        if (!empty($addGuest)) {
            $h->general()
                ->travellers($addGuest);
        }

        if (preg_match("#[ ]{2,}(?<currency>[A-Z]{3})(?<amount>\d+)" . $this->preg_implode($this->t('+TAX')) . "#", $stext, $m)) {
            $h->price()
                ->cost((float) $m['amount'])
                ->currency($m['currency'])
            ;
        }

        return $email;
    }

    private function rental(Email $email, string $stext)
    {
//        $this->logger->debug('Rental Segment = '.print_r( $stext,true));

        $r = $email->add()->rental();

        // General
        if (preg_match("#\W?" . $this->preg_implode($this->t("Confirmation#")) . "\W([A-Z\d]{5,})\b#u", $stext, $m)) {
            $r->general()
                ->confirmation($m[1]);
        } else {
            $r->general()
                ->noConfirmation();
        }

        if (preg_match("#^\s*(?<inDate>.+\d+:\d+)[ ]*(?<inAdrr>.+?)[ ]{2,}(?<company>.+?)[ ]{2,}(?<type>.+?)([ ]{2,}.*\n|\n)\s*(?<outDate>.+\d+:\d+)[ ]*(?<outAdrr>.+?)[ ]{2,}#u", $stext, $m)) {
            $r->pickup()
                ->date($this->normalizeDate($m['inDate']))
                ->location($m['inAdrr']);

            $r->dropoff()
                ->date($this->normalizeDate($m['outDate']))
                ->location($m['outAdrr']);

            $r->car()
                ->type($m['type']);

            $r->extra()
                ->company($m['company']);
        }

        if (preg_match("#\n\s*\W*" . $this->t("概算料金") . "\W*(?:BAR RATE)?[ ]*(?<amount>\d[\d,. ]+)[ ]*(?<curr>\D{1,4})\b#u", $stext, $m)) {
            $r->price()
                ->total($this->amount($m['amount']))
                ->currency($this->currency($m['curr']), true, true);
        }

        return $email;
    }

    private function transfer(Email $email, string $stext)
    {
//        $this->logger->debug('Transfer Segment = '.print_r( $stext,true));

        $finded = false;

        foreach ($email->getItineraries() as $value) {
            if ($value->getType() == 'transfer') {
                $t = $value;
                $s = $value->addSegment();
                $finded = true;

                break;
            }
        }

        if ($finded == false) {
            $t = $email->add()->transfer();

            $s = $t->addSegment();
        }

        // General
        if (preg_match("#\W?" . $this->preg_implode($this->t("予約番号")) . "\W([A-Z\d]{5,})\b#u", $stext, $m)) {
            $t->general()
                ->confirmation($m[1]);
        } else {
            $t->general()
                ->noConfirmation();
        }

        if (preg_match("#^\s*(?<dDate>.+\d+:\d+)[ ]*(?<dAdrr>.+?)(?:[ ]{2,}.*\n|\n)(?<aDate>.+?(?:\d+:\d+|[ ]{2,})) (?<aAdrr>.+?)(?:[ ]{2,}.*\n|\n)#u", $stext, $m)) {
            $s->departure()
                ->date($this->normalizeDate($m['dDate']));

            if (preg_match("#^\s*[A-Z]{3}\s*$#", $m['dAdrr'])) {
                $s->departure()
                    ->code(trim($m['dAdrr']));
            } else {
                $s->departure()
                    ->name($m['dAdrr']);
            }

            if (preg_match("#\d+:\d+#", $m['aDate'])) {
                $s->arrival()
                    ->date($this->normalizeDate($m['aDate']));
            } else {
                $s->arrival()
                    ->noDate();
            }

            if (preg_match("#^\s*[A-Z]{3}\s*$#", $m['aAdrr'])) {
                $s->arrival()
                    ->code(trim($m['aAdrr']));
            } else {
                $s->arrival()
                    ->name($m['aAdrr']);
            }
        }

        return $email;
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function normalizeDate($str)
    {
        $in = [
            "#^\s*([^\d\s]+)/(\d{1,2})/([^\d\s]+)\s+(\d+:\d+)\s*$#", // Nov/03/Sat        17:25
            "#^\s*([^\d\s]+)/(\d{1,2})/([^\d\s]+)\s*$#", // Nov/03/Sat
            "#^\s*(\d+)\s*年\s*(\d{1,2})\s*月\s*(\d{1,2})\s*日\s*(?:[^\d\s]+)\s*(\d+:\d+)\s*$#u", // 19年01月29日(火)  06:55
            "#^\s*(\d+)\s*年\s*(\d{1,2})\s*月\s*(\d{1,2})\s*日\s*(?:[^\d\s]+)\s*$#u", // 19年01月29日(火)
        ];
        $out = [
            "$3, $2 $1 $4",
            "$3, $2 $1",
            "$3.$2.20$1 $4",
            "$3.$2.20$1",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)(?:\s+\d{4})? \d+:\d+\s*$#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        if (preg_match("#^([^\d\s]+), (\d+ [^\d\s]+)( \d+:\d+)?\s*$#", $str, $m)) {
            $weeknum = WeekTranslate::number1(WeekTranslate::translate($m[1], $this->lang));
            $str = EmailDateHelper::parseDateUsingWeekDay($m[2] . ' ' . $this->year . ' ' . ($m[3] ?? ''), $weeknum);

            return $str;
        }

        return strtotime($str);
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

    private function TableHeadPos($row)
    {
        $head = array_filter(array_map('trim', explode("%", preg_replace("#\s{2,}#", "%", $row))));
        $pos = [];
        $lastpos = 0;

        foreach ($head as $word) {
            $pos[] = mb_strpos($row, $word, $lastpos, 'UTF-8');
            $lastpos = mb_strpos($row, $word, $lastpos, 'UTF-8') + mb_strlen($word, 'UTF-8');
        }

        return $pos;
    }

    private function SplitCols($text, $pos = false)
    {
        $cols = [];
        $rows = explode("\n", $text);

        if (!$pos) {
            $pos = $this->TableHeadPos($rows[0]);
        }
        arsort($pos);

        foreach ($rows as $row) {
            foreach ($pos as $k=>$p) {
                $cols[$k][] = trim(mb_substr($row, $p, null, 'UTF-8'));
                $row = mb_substr($row, 0, $p, 'UTF-8');
            }
        }
        ksort($cols);

        foreach ($cols as &$col) {
            $col = implode("\n", $col);
        }

        return $cols;
    }

    private function preg_implode($field)
    {
        if (!is_array($field)) {
            $field = [$field];
        }

        return '(?:' . implode("|", array_map(function ($v) {return preg_quote($v, '#'); }, $field)) . ')';
    }

    private function opt($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return '';
        }

        return '(?:' . implode('|', array_map(function ($s) { return preg_quote($s, '/'); }, $field)) . ')';
    }
}
