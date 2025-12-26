<?php

namespace AwardWallet\Engine\agoda\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class Flight2024 extends \TAccountChecker
{
    public $mailFiles = "agoda/it-713929586.eml, agoda/it-717460999.eml, agoda/it-728381212-th.eml, agoda/it-756166326.eml, agoda/it-757410792.eml";

    public $lang = '';
    public $detectSubject = [
        // en
        'Booking confirmation with Agoda - Booking ID',
        'Agoda is processing your booking - Booking ID',
        // th
        'ใบยืนยันการจองของท่านกับ Agoda - หมายเลขการจอง',
        // ja
        '【Agoda】ご予約確認書 - 予約ID：',
        // pt
        'Confirmação de reserva com Agoda - ID da Reserva:',
        // ko
        'Agoda 예약 확정서 - 예약 번호:',
        // zh
        'Agoda預訂確認信 - 訂單編號：',
        '您在 Agoda 的预订确认信 - 预订编码：',
        // es
        'Confirmación de la reserva con Agoda - ID de reserva:',
        // de
        'Buchungsbestätigung von Agoda – Buchungs-ID:',
    ];

    public static $dictionary = [
        'th' => [
            'otaConfNumber'  => ['เที่ยวบินขาไป หมายเลขการจอง', 'เที่ยวบินขากลับ หมายเลขการจอง'],
            // 'otaConfNumberInText' => '',
            'statusPhrases'  => ['การจองเที่ยวบินของท่านได้รับการ'],
            'statusVariants' => ['ยืนยันแล้ว'],
            // 'cabinValues' => [''],
            'Airline Reference' => 'หมายเลขอ้างอิงของสายการบิน',
            'Passenger details' => 'รายละเอียดผู้โดยสาร',
            'Adult'             => 'ผู้ใหญ่',
            'Birthdate'         => 'วันเกิด',
            'Nationality'       => 'สัญชาติ',
            // 'Ticket number' => '',
            // 'Seat selection' => '',
            'Total'      => 'ยอดรวมทั้งสิ้น',
            'feeHeaders' => ['บริการเสริม'],
            // 'FREE' => '',
        ],
        'en' => [
            'otaConfNumber'       => ['Booking ID', 'Departure Booking ID', 'Return Booking ID'],
            'otaConfNumberInText' => ['your booking ID is'],
            'statusPhrases'       => ['Your flight booking has been'],
            'statusVariants'      => ['confirmed'],
            'cabinValues'         => ['Economy Classic', 'Economy', 'ECO'],
            // 'Airline Reference' => '',
            'Passenger details' => 'Passenger details',
            // 'Adult' => '',
            // 'Birthdate' => '',
            // 'Nationality' => '',
            // 'Ticket number' => '',
            // 'Seat selection' => '',
            // 'Total' => '',
            'feeHeaders' => ['Add-ons'],
            // 'FREE' => '',
        ],
        'ja' => [
            'otaConfNumber'  => ['予約ID:', '往路 予約ID:', '復路 予約ID:'],
            // 'otaConfNumberInText' => '',
            // 'statusPhrases'  => ['Your flight booking has been'],
            // 'statusVariants' => ['confirmed'],
            'cabinValues'       => ['ECONOMY'],
            'Airline Reference' => '航空会社照会番号',
            'Passenger details' => '【お客様情報】',
            'Adult'             => '大人',
            'Birthdate'         => '生年月日：',
            'Nationality'       => '国籍',
            'Ticket number'     => '航空券番号',
            'Seat selection'    => '座席指定',
            'Total'             => '最終合計金額：',
            'feeHeaders'        => ['追加オプション'],
            'FREE'              => '無料',
        ],
        'pt' => [
            'otaConfNumber'  => ['Número da reserva:'],
            // 'otaConfNumberInText' => '',
            // 'statusPhrases'  => ['Your flight booking has been'],
            // 'statusVariants' => ['confirmed'],
            'cabinValues'       => ['ECONOMY'],
            'Airline Reference' => 'Código de reserva da companhia aérea',
            'Passenger details' => 'Dados do passageiro',
            'Adult'             => 'Adulto',
            'Birthdate'         => 'Data de nascimento',
            'Nationality'       => 'Nacionalidade',
            'Ticket number'     => 'Número do bilhete',
            // 'Seat selection' => '',
            'Total' => 'Total:',
            // 'feeHeaders' => ['追加オプション'],
            'FREE' => 'GRÁTIS',
        ],
        'ko' => [
            'otaConfNumber'  => ['예약 번호:'],
            // 'otaConfNumberInText' => '',
            // 'statusPhrases'  => ['Your flight booking has been'],
            // 'statusVariants' => ['confirmed'],
            'cabinValues'       => ['Business'],
            'Airline Reference' => '항공사 참조 번호',
            'Passenger details' => '탑승객 정보',
            'Adult'             => '성인',
            'Birthdate'         => '생년월일',
            'Nationality'       => '국적',
            'Ticket number'     => '항공권 번호',
            'Seat selection'    => '좌석 선택',
            'Total'             => '총계',
            'feeHeaders'        => ['부가 서비스'],
            'FREE'              => '무료',
        ],
        'zh' => [
            'otaConfNumber'  => ['去程 訂單編號:', '回程 訂單編號:', '訂單編號:', '预订ID:'],
            // 'otaConfNumberInText' => '',
            // 'statusPhrases'  => ['Your flight booking has been'],
            // 'statusVariants' => ['confirmed'],
            'cabinValues'       => ['ECONOMY'],
            'Airline Reference' => ['航空公司參考編號', '航司编号'],
            'Passenger details' => ['乘客資料', '乘客详情'],
            'Adult'             => ['大人', '成人'],
            'Birthdate'         => ['生日', '出生日期'],
            'Nationality'       => ['國籍', '国籍（国家/地区）'],
            'Ticket number'     => '機票號碼',
            // 'Seat selection' => '좌석 선택',
            'Total' => ['總金額：', '总价：'],
            // 'feeHeaders' => ['부가 서비스'],
            'FREE' => ['免費', '免费'],
        ],
        'es' => [
            'otaConfNumber'     => ['Id. de la Reserva:'],
            // 'otaConfNumberInText' => '',
            'statusPhrases'     => ['Su reserva de vuelo está'],
            'statusVariants'    => ['confirmada'],
            'cabinValues'       => ['Economy'],
            'Airline Reference' => 'Referencia de la aerolínea',
            'Passenger details' => 'Datos del pasajero',
            'Adult'             => 'Adult',
            'Birthdate'         => 'Fecha de nacimiento',
            'Nationality'       => 'Nacionalidad',
            'Ticket number'     => 'Número de billete',
            // 'Seat selection' => '좌석 선택',
            'Total' => 'Total',
            // 'feeHeaders' => ['부가 서비스'],
            'FREE' => 'GRATIS',
        ],
        'de' => [
            'otaConfNumber'     => ['Buchungsnummer:'],
            // 'otaConfNumberInText' => '',
            'statusPhrases'     => ['Ihre Flugbuchung wurde'],
            'statusVariants'    => ['bestätigt'],
            'cabinValues'       => ['Economy'],
            'Airline Reference' => 'Buchungsreferenz',
            'Passenger details' => 'Passagierdetails',
            'Adult'             => 'Erwachsener',
            'Birthdate'         => 'Geburtsdatum',
            'Nationality'       => 'Staatsangehörigkeit',
            'Ticket number'     => 'Ticketnummer',
            // 'Seat selection' => '좌석 선택',
            'Total' => 'Total',
            // 'feeHeaders' => ['부가 서비스'],
            'FREE' => 'KOSTENLOS',
        ],
    ];

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[.@]agoda\.com$/i', $from) > 0;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (empty($headers['subject'])) {
            return false;
        }

        foreach ($this->detectSubject as $dSubject) {
            if (stripos($headers['subject'], $dSubject) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query('//a[contains(@href,".agoda.com/") or contains(@href,"www.agoda.com")]')->length === 0
            && $this->http->XPath->query('//*[contains(normalize-space(),"Thank you for choosing Agoda") or contains(normalize-space(),"This email was sent by: Agoda")]')->length === 0
        ) {
            return false;
        }

        return $this->assignLang() && $this->findSegments()->length > 0;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();
        $email->setType('Flight2024' . ucfirst($this->lang));

        $patterns = [
            'date'          => '(?:.*\D|)\d{4}(?:\D.*|)', // Sunday, September 22, 2024    |    วันเสาร์ที่ 14 กันยายน ค.ศ. 2024 | 水曜日 2024年12月25日
            'time'          => '\d{1,2}[:：]\d{2} *(?:[AaPp]\.? ?[Mm]\.?|오전|오후|午前|午後|下午|上午|หลังเที่ยง|ก่อนเที่ยง)?', // 4:19PM    |    2:00 p. m.
            'travellerName' => '[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]]', // Mr. Hao-Li Huang
            'eTicket'       => '\d{3}(?: | ?- ?)?\d{5,}(?: | ?- ?)?\d{1,3}', // 075-2345005149-02    |    0167544038003-004
        ];

        $email->obtainTravelAgency();

        $f = $email->add()->flight();
        $confNumbers = $confNumbersSecondary = [];

        $statusTexts = array_filter($this->http->FindNodes("//text()[{$this->contains($this->t('statusPhrases'))}]", null, "/{$this->opt($this->t('statusPhrases'))}[:\s]*({$this->opt($this->t('statusVariants'))})(?:\s*[,.;:!?]|$)/i"));

        if (count(array_unique(array_map('mb_strtolower', $statusTexts))) === 1) {
            $status = array_shift($statusTexts);
            $f->general()->status($status);
        }

        $otaConfNumbers = $this->http->XPath->query("//text()[{$this->eq($this->t('otaConfNumber'), "translate(.,':','')")} or {$this->eq($this->t('otaConfNumber'))}]");

        foreach ($otaConfNumbers as $ocn) {
            $otaConfirmation = $this->http->FindSingleNode("following::text()[normalize-space()][1]", $ocn, true, '/^[-A-Z\d]{5,25}$/');

            if ($otaConfirmation) {
                $otaConfirmationTitle = $this->http->FindSingleNode(".", $ocn, true, '/^(.+?)[\s:：]*$/u');
                $email->ota()->confirmation($otaConfirmation, $otaConfirmationTitle);
            }
        }
        $otaConfNumbers = $this->http->XPath->query("//text()[{$this->starts($this->t('otaConfNumber'), "translate(.,':','')")} or {$this->starts($this->t('otaConfNumber'))}]");

        foreach ($otaConfNumbers as $ocn) {
            if (preg_match("/({$this->opt($this->t('otaConfNumber'))})[\s:：]*([-A-Z\d]{5,25})\s*$/", $ocn->nodeValue, $m)) {
                $email->ota()->confirmation($m[2], $m[1]);
            }
        }

        if ($otaConfNumbers->length === 0 && empty($email->getTravelAgency()->getConfirmationNumbers())) {
            $confs = $this->http->FindNodes("//a/@href[contains(., '.agoda.com')][{$this->contains(['/bookingdetails?BookingId=', '%2Fbookingdetails%3FBookingId%3D'])}]",
                null, "/bookingdetails(?:\?|%3F)BookingId(?:=|%3D)(\d{5,})\s*(?:&|$|__;)/");

            if (count(array_unique($confs)) === 1) {
                $email->ota()->confirmation($confs[0]);
            }
        }

        if ($otaConfNumbers->length === 0 && empty($email->getTravelAgency()->getConfirmationNumbers())) {
            $confs = $this->http->FindNodes("//text()[{$this->contains($this->t('otaConfNumberInText'))}]",
                null, "/{$this->opt($this->t('otaConfNumberInText'))}\s*(\d{5,})\b/");

            if (count(array_unique($confs)) === 1) {
                $email->ota()->confirmation($confs[0]);
            }
        }

        if (empty($email->getTravelAgency()->getConfirmationNumbers())) {
            $email->ota()->confirmation(null);
        }

        /*
            Manila (MNL)
            Sunday, September 22, 2024 · 8:15 PM
            Ninoy Aquino International Airport
        */
        $pattern = "/^"
        . "(?<point>(?<name2>.*(?:\n.*)?)\b(?<code>[A-Z]{3})[\s)]*)\n"
        . "(?<date>{$patterns['date']})\s+[-–·]+\s+(?<time>{$patterns['time']})\n"
        . "(?<name>.{2,})"
        . "$/u";
        // $this->logger->debug('$pattern = ' . print_r($pattern, true));

        $segments = $this->findSegments();
        $noConf = null;

        foreach ($segments as $root) {
            $s = $f->addSegment();
            $point1 = $point2 = null;

            $flight = implode("\n", $this->http->FindNodes("preceding::tr[normalize-space() and not(.//tr[normalize-space()])][1]/descendant::text()[normalize-space()]", $root));

            if (preg_match("/^(?<name>[A-Z][A-Z\d]|[A-Z\d][A-Z])[ ]*(?<number>\d+)$/m", $flight, $m)) {
                $s->airline()
                    ->name($m['name'])
                    ->number($m['number']);
                $noConf = false;
            } elseif (preg_match("/^(?<name>.+)\n/", $flight, $m)) {
                $s->airline()
                    ->name($m['name'])
                    ->noNumber();
                $noConf = ($noConf === false) ? false : true;
            }

            if (preg_match("/^{$this->opt($this->tPlusEn('cabinValues'))}$/im", $flight, $m)) {
                $s->extra()->cabin($m[0]);
            }

            if (preg_match("/^({$this->opt($this->t('Airline Reference'))})[:\s：]+(?:$\n\s*)?((?:[A-Z\d]{5,10}[,\s;]*)+)(?:\n.*[^\sA-Z]+.*)?$/mu", $flight, $m)) {
                $referenceList = preg_split('/(?:\s*[,;]\s*)+/', $m[2]);
                $airlineReference = array_shift($referenceList);
                $s->airline()->confirmation($airlineReference);
                $confNumbers[] = $airlineReference;

                foreach ($referenceList as $ref) {
                    if (!in_array($ref, $confNumbers) && !in_array($ref, $confNumbersSecondary)) {
                        $f->general()->confirmation($ref, $m[1]);
                        $confNumbersSecondary[] = $ref;
                    }
                }
            }

            $departure = implode("\n", $this->http->FindNodes("*[1]/descendant::text()[normalize-space()]", $root));

            if (preg_match($pattern, $departure, $matches)) {
                $point1[] = trim(preg_replace("/\s+/", ' ', $matches['point']));
                $point1[] = trim(preg_replace("/\s+/", ' ', trim($matches['name2'], '('))) . ' ' . $matches['code'];
                $dateDep = strtotime($this->normalizeDate($matches['date']));
                $matches['time'] = $this->normalizeTime($matches['time']);

                if ($dateDep) {
                    $s->departure()->date(strtotime($matches['time'], $dateDep));
                }

                $s->departure()->code($matches['code'])->name($matches['name']);
            }

            $arrival = implode("\n", $this->http->FindNodes("*[3]/descendant::text()[normalize-space()]", $root));

            if (preg_match($pattern, $arrival, $matches)) {
                $point2[] = trim(preg_replace("/\s+/", ' ', $matches['point']));
                $point2[] = trim(preg_replace("/\s+/", ' ', trim($matches['name2'], '('))) . ' ' . $matches['code'];
                $matches['time'] = $this->normalizeTime($matches['time']);
                $dateArr = strtotime($this->normalizeDate($matches['date']));

                if ($dateArr) {
                    $s->arrival()->date(strtotime($matches['time'], $dateArr));
                }

                $s->arrival()->code($matches['code'])->name($matches['name']);
            }

            $duration = $this->http->FindSingleNode("*[2]", $root, true, '/^(?:\s*\d{1,3}\s*(?:[hm]|ชม|นาที|時間|分|시간|분)[.\s]*)+$/iu');
            $s->extra()->duration($duration, false, true);

            if ($point1 && $point2) {
                $seatNodes = $this->http->XPath->query("//*[{$this->eq($this->t('Seat selection'))}]/following::*[ count(tr[normalize-space()])=2 and tr[normalize-space()][1][count(descendant::text()[normalize-space()])=2 and descendant::text()[normalize-space()][1][{$this->eq($point1)}] and descendant::text()[normalize-space()][2][{$this->eq($point2)}]] ]/tr[normalize-space()][2]/descendant::text()[normalize-space()]");

                if ($seatNodes->length === 0) {
                    $route = [];

                    foreach ($point1 as $i => $v) {
                        $route[] = $point1[$i] . '' . $point2[$i];
                        $route[] = $point1[$i] . ' ' . $point2[$i];
                    }
                    $seatNodes = $this->http->XPath->query("//*[{$this->eq($this->t('Seat selection'))}]/following::*[count(tr[normalize-space()])=2 and tr[1][{$this->eq($route)}]]/tr[normalize-space()][2]/descendant::text()[normalize-space()]");
                }

                foreach ($seatNodes as $seatRoot) {
                    if (preg_match("/^(?<passenger>{$patterns['travellerName']})\s*[:]+\s*(?<seat>\d+[A-Z])$/u", $this->http->FindSingleNode(".", $seatRoot), $m)) {
                        $s->extra()->seat($m['seat'], false, false, $m['passenger']);
                    }
                }
            }
        }

        if (count($confNumbersSecondary) === 0 && count($confNumbers) > 0) {
            $f->general()->noConfirmation();
        } elseif (count($confNumbersSecondary) === 0 && count($confNumbers) === 0 && $noConf === true) {
            $f->general()->noConfirmation();
        }

        $travellers = array_filter($this->http->FindNodes("//*[{$this->eq($this->t('Passenger details'))}]/following::tr[ normalize-space() and not(.//tr[normalize-space()]) and following-sibling::tr[normalize-space()][1][{$this->starts($this->t('Birthdate'))} or {$this->contains($this->t('Nationality'))}] ]", null, "/^({$patterns['travellerName']})(?:\s*·|[·\s]+(?i){$this->opt($this->t('Adult'))}|$)/u"));

        if (count($travellers) > 0) {
            $f->general()->travellers(array_values(array_unique($travellers)), true);
        }

        $ticketNodes = $this->http->XPath->query("//text()[{$this->starts($this->t('Ticket number'))}]");
        $tickets = [];

        foreach ($ticketNodes as $tktRoot) {
            $passengerName = $this->http->FindSingleNode("ancestor::*[ preceding-sibling::*[normalize-space()] ][1]/preceding-sibling::*[normalize-space()][1][{$this->starts($this->t('Birthdate'))} or {$this->contains($this->t('Nationality'))}]/preceding-sibling::*[normalize-space()][1]", $tktRoot, true, "/^({$patterns['travellerName']})(?:\s*·|[·\s]+(?i){$this->opt($this->t('Adult'))}|$)/u");
            $ticket = $this->http->FindSingleNode(".", $tktRoot, true, "/^{$this->opt($this->t('Ticket number'))}[:\s]+({$patterns['eTicket']})$/");

            if ($ticket && !in_array($ticket, $tickets)) {
                $f->issued()->ticket($ticket, false, $passengerName);
                $tickets[] = $ticket;
            }
        }

        $xpathTotalPrice = "count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('Total'))}]";
        $totalPrice = $this->http->FindSingleNode("//tr[{$xpathTotalPrice}]/*[normalize-space()][2]", null, true, '/^.*\d.*$/');

        if (preg_match('/^(?<currency>[^\-\d)(]+?)[ ]*(?<amount>\d[,.‘\'\d ]*)$/u', $totalPrice, $matches)) {
            // PHP 10,168.57
            $currencyCode = preg_match('/^[A-Z]{3}$/', $matches['currency']) ? $matches['currency'] : null;
            $f->price()->currency($matches['currency'])->total(PriceHelper::parse($matches['amount'], $currencyCode));

            $discountAmounts = [];
            $feeRows = $this->http->XPath->query("//tr[ count(*[normalize-space()])=2 and following-sibling::tr[{$xpathTotalPrice}] and not(*[normalize-space()][1][{$this->eq($this->t('feeHeaders'))}]) ]");

            foreach ($feeRows as $i => $feeRow) {
                $feeName = $this->http->FindSingleNode("*[normalize-space()][1]", $feeRow, true, '/^(.+?)[\s:：]*$/u');
                $feeValue = $this->http->FindSingleNode("*[normalize-space()][2]", $feeRow);

                if (preg_match("/^{$this->opt($this->t('FREE'))}$/i", $feeValue)) {
                    $f->price()->fee($feeName, 0);

                    continue;
                }

                if (preg_match('/^(?:' . preg_quote($matches['currency'], '/') . ')?[ ]*-[ ]*(?<amount>\d[,.‘\'\d ]*)$/u', $feeValue, $m)) {
                    // PHP -319.90
                    $discountAmounts[] = PriceHelper::parse($m['amount'], $currencyCode);

                    continue;
                }

                if (preg_match('/^(?:' . preg_quote($matches['currency'], '/') . ')?[ ]*(?<amount>\d[,.‘\'\d ]*)$/u', $feeValue, $m)) {
                    if ($i === 0) {
                        $f->price()->cost(PriceHelper::parse($m['amount'], $currencyCode));
                    } else {
                        $f->price()->fee($feeName, PriceHelper::parse($m['amount'], $currencyCode));
                    }
                }
            }

            if (count($discountAmounts) > 0) {
                $f->price()->discount(array_sum($discountAmounts));
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

    private function findSegments(): \DOMNodeList
    {
        $xpathTime = 'contains(translate(.,"0123456789： ","∆∆∆∆∆∆∆∆∆∆:"),"∆:∆∆")';

        return $this->http->XPath->query("//tr[ count(*)=3 and *[1][{$xpathTime} and not(descendant::img)] and *[2]/descendant::img and *[3][{$xpathTime} and not(descendant::img)] ]");
    }

    private function assignLang(): bool
    {
        if (!isset(self::$dictionary, $this->lang)) {
            return false;
        }

        foreach (self::$dictionary as $lang => $phrases) {
            if (!empty($phrases['otaConfNumber']) && $this->http->XPath->query("//*[{$this->contains($phrases['otaConfNumber'])}]")->length > 0
            ) {
                $this->lang = $lang;

                return true;
            }

            if (!empty($phrases['Passenger details']) && $this->http->XPath->query("//*[{$this->contains($phrases['Passenger details'])}]")->length > 0) {
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

    private function tPlusEn(string $s): array
    {
        return array_unique(array_merge((array) $this->t($s), (array) $this->t($s, 'en')));
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

    private function normalizeTime($time)
    {
        $time = str_replace(['오전', '午前', '上午', 'ก่อนเที่ยง'], 'AM', $time);
        $time = str_replace(['오후', '午後', '下午', 'หลังเที่ยง'], 'PM', $time);

        return $time;
    }

    /**
     * Dependencies `use AwardWallet\Engine\MonthTranslate` and `$this->lang`.
     *
     * @param string|null $text Unformatted string with date
     */
    private function normalizeDate(?string $text): ?string
    {
        // $this->logger->debug('$text = ' . print_r($text, true));

        if (preg_match('/\b(\d{1,2})\s+([^\d\s,.]{3,})(?:\s+[^\d\s,.]\.?\s*[^\d\s,.]\.?)?\s+(\d{4})$/u', $text, $m)) {
            // วันอังคารที่ 17 กันยายน ค.ศ. 2024
            $day = $m[1];
            $month = $m[2];
            $year = $m[3];
        } elseif (preg_match('/\b([[:alpha:]\p{Thai}]{3,})[,.\s]+(\d{1,2})[,.\s]+(\d{4})$/u', $text, $m)) {
            // Monday, September 2, 2024
            $month = $m[1];
            $day = $m[2];
            $year = $m[3];
        // } elseif (preg_match('/\b([[:alpha:]]{3,})[,.\s]+(\d{1,2})[,.\s]+(\d{4})$/u', $text, $m)) {
        } elseif (preg_match('/^(?:[[:alpha:]]+ )?(\d{4}) ?[年년] ?(\d{1,2}) ?[月월] ?(\d{1,2}) ?[日일]/u', $text, $m)) {
            // 水曜日 2024年12月25日
            // 목요일 2024년 11월 07일
            // 2024年10月14日月曜日
            $year = $m[1];
            $month = $m[2];
            $day = $m[3];
        }

        if (isset($day, $month, $year)) {
            // $this->logger->debug('$day = ' . print_r($day, true));
            // $this->logger->debug('$month = ' . print_r($month, true));
            // $this->logger->debug('$year = ' . print_r($year, true));

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
