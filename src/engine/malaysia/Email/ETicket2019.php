<?php

namespace AwardWallet\Engine\malaysia\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;

class ETicket2019 extends \TAccountChecker
{
    public $mailFiles = "malaysia/it-39237238.eml, malaysia/it-599892494.eml, malaysia/it-601112460.eml, malaysia/it-606510005.eml, malaysia/it-609486434.eml, malaysia/it-614727207.eml, malaysia/it-614861318.eml, malaysia/it-705423733.eml";

    public $lang = '';

    public $subjects = [
        'en' => [
            'E-Ticket',
            'Changes to passenger information for reservation',
            'Confirmation for reservation',
        ],
        'vi' => ['Xác nhận đặt chỗ'],
        'ja' => [
            'の特別リクエストは変更されました',
            'の乗客情報は変更されました。',
        ],
        'ko' => [
            '다음 예약에 대한 승객정보 변경:',
        ],
        'zh' => [
            '更改预订',
        ],
        'id' => [
            'Konfirmasi reservasi',
        ],
    ];

    public $providerCode;
    public static $detectProvider = [
        'malaysia' => [
            'from' => 'noreply@malaysiaairlines.com',
            'link' => 'malaysiaairlines.com',
        ],
        'srilankan' => [
            'from' => 'confirmations@srilankan.com',
            'link' => 'srilankan.com',
        ],
    ];
    public static $dictionary = [
        'en' => [
            'Booking Reference Number' => ['Booking Reference Number', 'Reservation number:'],
            'Itinerary Details'        => ['Itinerary Details', 'Itinerary'],
            'Terminal'                 => ['Terminal', 'terminal'],
            'DURATION:'                => ['DURATION:', 'DURATION'],
            'AIRLINE:'                 => ['AIRLINE:', 'AIRLINE'],
            // 'AIRCRAFT:' => '',
            // 'CABIN:' => '',
            // 'CLASS:' => '',
            'passengersHeader' => ['Passengers', 'Passenger', 'Travellers', 'Traveller'],
            'Adult(s)'         => ['Adult(s)', 'Adult', 'Child'],
            // 'E-ticket number:' => '',
            // 'Frequent Flyer number:' => '',
            'Service details' => ['Service details', 'Additional services breakdown'],
            'Seats('          => ['Seats(', 'Seat('],
            'Fare Details'    => ['Price Details', 'Fare Details'],
            // 'Base fare' => '',
            'Taxes, Fees and Charges'  => ['Taxes, Fees and Charges', 'Total Taxes, fees and charges'],
            'Total for all passengers' => ['Total for all passengers', 'Total for all travellers'],
            'Total'                    => ['Total', 'GRAND TOTAL'],
        ],
        'vi' => [
            'Booking Reference Number' => ['Số đặt chỗ'],
            'Itinerary Details'        => 'Lộ trình',
            'Terminal'                 => 'cửa',
            'DURATION:'                => 'THỜI GIAN:',
            'AIRLINE:'                 => 'HÃNG HÀNG KHÔNG:',
            'AIRCRAFT:'                => 'MÁY BAY:',
            'CABIN:'                   => 'KHOANG:',
            'CLASS:'                   => 'HẠNG:',
            'passengersHeader'         => ['Người đi'],
            'Adult(s)'                 => ['Người lớn'],
            'E-ticket number:'         => 'Số vé điện tử:',
            'Frequent Flyer number:'   => 'Frequent Flyer number:',
            'Service details'          => 'Chi tiết dịch vụ',
            'Seats('                   => 'Chỗ ngồi(',
            'Fare Details'             => 'Giá bán',
            'Base fare'                => 'Giá vé cơ sở',
            'Taxes, Fees and Charges'  => 'Thuế và phí',
            'Total for all passengers' => 'Tổng cho tất cả người đi',
            'Total'                    => 'Tổng',
        ],
        'ja' => [
            'Booking Reference Number' => ['予約番号'],
            'Itinerary Details'        => '旅程',
            'Terminal'                 => 'ターミナル',
            'DURATION:'                => '所要時間',
            'AIRLINE:'                 => '航空会社:',
            'AIRCRAFT:'                => '機材:',
            'CABIN:'                   => 'キャビン:',
            'CLASS:'                   => 'クラス:',
            'passengersHeader'         => ['ご旅行者'],
            'Adult(s)'                 => ['大人'],
            'E-ticket number:'         => 'Eﾁｹｯﾄ番号:',
            'Frequent Flyer number:'   => 'Frequent Flyer number:',
            'Service details'          => 'サービス詳細',
            'Seats('                   => '座席(',
            'Fare Details'             => '料金',
            'Base fare'                => 'ベースフェア',
            'Taxes, Fees and Charges'  => '税金と手数料',
            'Total for all passengers' => '全旅行者の合計金額',
            'Total'                    => '合計',
        ],
        'ko' => [
            'Booking Reference Number' => ['예약 번호'],
            'Itinerary Details'        => '여정',
            'Terminal'                 => '터미널',
            'DURATION:'                => '운항시간:',
            'AIRLINE:'                 => '항공사:',
            'AIRCRAFT:'                => '항공기:',
            'CABIN:'                   => '좌석 등급:',
            'CLASS:'                   => '운임 종류:',
            'passengersHeader'         => ['승객', '여행자'],
            'Adult(s)'                 => ['성인'],
            'E-ticket number:'         => '전자항공권 번호',
            'Frequent Flyer number:'   => 'Frequent Flyer number:',
            'Service details'          => 'サービス詳細',
            'Seats('                   => '座席(',
            'Fare Details'             => '서비스 상세정보',
            // 'Base fare' => '',
            // 'Taxes, Fees and Charges' => '',
            // 'Total for all passengers' => '全旅行者の合計金額',
            // 'Total' => '合計',
        ],
        'zh' => [
            'Booking Reference Number' => ['预订编号:'],
            'Itinerary Details'        => '行程',
            'Terminal'                 => '候机楼',
            'DURATION:'                => '飞行时间:',
            'AIRLINE:'                 => '航空公司：',
            'AIRCRAFT:'                => '飞机：',
            'CABIN:'                   => '机舱:',
            'CLASS:'                   => '舱位:',
            'passengersHeader'         => ['X 旅客'],
            'Adult(s)'                 => ['成人'],
            'E-ticket number:'         => '电子客票号码：',
            'Frequent Flyer number:'   => 'Frequent Flyer number:',
            'Service details'          => '服务详细信息',
            'Seats('                   => '座席(',
            'Fare Details'             => '서비스 상세정보',
            // 'Base fare' => '',
            // 'Taxes, Fees and Charges' => '',
            // 'Total for all passengers' => '全旅行者の合計金額',
            // 'Total' => '合計',
        ],
        'id' => [
            'Booking Reference Number' => ['Nomor reservasi:'],
            'Itinerary Details'        => 'Rencana perjalanan',
            'Terminal'                 => 'terminal',
            'DURATION:'                => 'DURASI',
            'AIRLINE:'                 => 'MASKAPAI:',
            'AIRCRAFT:'                => 'PESAWAT:',
            'CABIN:'                   => 'KABIN:',
            'CLASS:'                   => 'KELAS:',
            'passengersHeader'         => ['Wisatawan'],
            'Adult(s)'                 => ['Dewasa'],
            'E-ticket number:'         => 'Nomor tiket elektronik:',
            'Frequent Flyer number:'   => 'Frequent Flyer number:',
            'Service details'          => 'Layanan detail',
            // 'Seats('                   => 'Chỗ ngồi(',
            'Fare Details'             => 'Harga',
            'Base fare'                => 'Tarif basis',
            'Taxes, Fees and Charges'  => 'Pajak dan biaya',
            'Total for all passengers' => 'Total untuk semua wisatawan',
            'Total'                    => 'Total',
        ],
    ];

    public function detectEmailFromProvider($from)
    {
        return strpos($from, 'Malaysia Airlines') !== false
            || stripos($from, '@malaysiaairlines.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        $detectedFrom = false;

        foreach (self::$detectProvider as $code => $detect) {
            if (!empty($detect['from']) && stripos($headers['from'], $detect['from']) !== false) {
                $detectedFrom = true;
                $this->providerCode = $code;

                break;
            }
        }

        if ($detectedFrom === false) {
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
        foreach (self::$detectProvider as $code => $detect) {
            if (!empty($detect['link'])) {
                if ($this->http->XPath->query("//a/@href[{$this->contains($detect['link'])}]")->length === 0
                    && $this->http->XPath->query("//img/@src[{$this->contains($detect['link'])}][{$this->contains(['Barcode'])}]")->length === 0
                ) {
                    continue;
                }

                $this->providerCode = $code;

                if ($this->assignLang()) {
                    return true;
                }
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();

        if (empty($this->lang)) {
            $this->logger->debug("Can't determine a language!");

            return $email;
        }

        if (empty($this->providerCode)) {
            foreach (self::$detectProvider as $code => $detect) {
                if (!empty($detect['from']) && stripos($parser->getCleanFrom(), $detect['from']) !== false) {
                    $this->providerCode = $code;

                    break;
                }

                if (!empty($detect['link'])) {
                    if ($this->http->XPath->query("//a/@href[{$this->contains($detect['link'])}]")->length > 0
                        || $this->http->XPath->query("//img/@src[{$this->contains($detect['link'])}][{$this->contains(['Barcode'])}]")->length > 0
                    ) {
                        $this->providerCode = $code;

                        break;
                    }
                }
            }
        }

        if (!empty($this->providerCode)) {
            $email->setProviderCode($this->providerCode);
        }

        $this->parseFlight($email);
        $email->setType('ETicket2019' . ucfirst($this->lang));

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

    public static function getEmailProviders()
    {
        return array_keys(self::$detectProvider);
    }

    private function parseFlight(Email $email)
    {
        $f = $email->add()->flight();

        $confirmation = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Booking Reference Number'))}]/following::text()[normalize-space()][1]", null, true, '/^[A-Z\d]{5,}$/');

        if ($confirmation) {
            $confirmationTitle = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Booking Reference Number'))}]", null, true, '/^(.+?)[\s:：]*$/');
            $f->general()->confirmation($confirmation, $confirmationTitle);
        }

        $xpath = "//*[count(*[normalize-space()]) = 2][*[normalize-space()][2][{$this->starts($this->t('DURATION:'))}]]";
        // $this->logger->debug('$xpath = '.print_r( $xpath,true));
        $segments = $this->http->XPath->query($xpath);

        $seatsSegments = [];

        if ($this->http->XPath->query("//text()[{$this->contains($this->t('Seats('))}]")->length > 0) {
            $segmentsNameForSeat = [];
            $segmentsNameForSeat = array_merge($segmentsNameForSeat,
                $this->http->FindNodes($xpath . "/*[1]//text()[starts-with(normalize-space(translate(.,'0123456789','dddddddddd')), 'dd:dd')]", null, "/^\s*\d{2}:\d{2}\s*(.+)/"));
            $segmentsNameForSeat = array_merge($segmentsNameForSeat,
                $this->http->FindNodes($xpath . "/ancestor-or-self::*[ preceding-sibling::*[normalize-space()] ][1]/preceding-sibling::*[contains(.,'-') and contains(.,',') and string-length(normalize-space())>6][1]"));
            $segmentsNameForSeat = array_unique($segmentsNameForSeat);

            $text = implode("\n",
                $this->http->FindNodes("//text()[{$this->eq($this->t('Service details'))}]/following::td[not(.//td)][{$this->contains($segmentsNameForSeat)} or {$this->contains($this->t('Seats('))}]"));
            $text = preg_replace("/(?:^|\n).*\d{4}.*(\n.+ - .+\n)/", "$1", $text);

            $seatsSegments = $this->split("/^(.+ - .+)$/m", "\n\n" . $text);

            if (count($seatsSegments) !== $segments->length) {
                $seatsSegments = [];
            }
        }

        foreach ($segments as $si => $segment) {
            $s = $f->addSegment();

            $date = $this->normalizeDate($this->http->FindSingleNode("ancestor-or-self::*[ preceding-sibling::*[normalize-space()] ][1]/preceding-sibling::*[contains(.,'-') and contains(.,',') and string-length(normalize-space())>6][1]", $segment, true, '/^.{3,}-.{3,},\s*(.{6,})$/'));

            $cityFrom = $cityTo = '';
            /*
                03:25 Melbourne
                Melbourne Airport
                (MEL)
                Terminal 2
             */
            $patterns['timeAirport'] = "/^\s*"
                . "(?<time>\d{1,2}:\d{2}(?:[ ]*[AaPp][Mm])?)\s+(?<name>(?<city>[^\n]+).+?)\s*\((?<code>[A-Z]{3})\)"
                . "(?:\n\s*{$this->opt($this->t('Terminal'))}[ ]+(?<terminal>.+))?"
                . "\s*$/s";

            $segmentText = implode("\n", $this->http->FindNodes("*[normalize-space()][1]//text()[normalize-space()]", $segment));
            $departure = '';
            $arrival = '';

            if (preg_match("/^\s*(\d{1,2}:\d{2}.*\([A-Z]{3}\).*)\n\s*(\d{1,2}:\d{2}.+)/s", $segmentText, $m)) {
                $departure = $m[1];
                $arrival = $m[2];
            }

            if (preg_match($patterns['timeAirport'], $departure, $m)) {
                $s->departure()
                    ->date(!empty($date) ? strtotime($m['time'], $date) : null)
                    ->name(preg_replace('/\s+/', ' ', $m['name']))
                    ->code($m['code'])
                    ->terminal(empty($m['terminal']) ? null : $m['terminal'], false, true);
                $cityFrom = $m['city'];
            }

            if (preg_match($patterns['timeAirport'], $arrival, $m)) {
                $s->arrival()
                    ->date(!empty($date) ? strtotime($m['time'], $date) : null)
                    ->name(preg_replace('/\s+/', ' ', $m['name']))
                    ->code($m['code'])
                    ->terminal(empty($m['terminal']) ? null : $m['terminal'], false, true);
                $cityTo = $m['city'];
            }

            $xpathSegmentRight = "*[normalize-space()][2]";

            $flight = $this->http->FindSingleNode($xpathSegmentRight . "/descendant::td[{$this->eq($this->t('AIRLINE:'))}]/following-sibling::td[normalize-space()][1]", $segment);

            if (preg_match('/(?:^|\()(?<name>[A-Z][A-Z\d]|[A-Z\d][A-Z])[ ]*(?<number>\d+)(?:\)|$)/', $flight, $m)) {
                $s->airline()
                    ->name($m['name'])
                    ->number($m['number']);
            }

            $duration = $this->http->FindSingleNode($xpathSegmentRight . "/descendant::td[{$this->eq($this->t('DURATION:'))}]/following-sibling::td[normalize-space()][1]", $segment, true, "/^\d.+/");
            $aircraft = $this->http->FindSingleNode($xpathSegmentRight . "/descendant::td[{$this->eq($this->t('AIRLINE:'))}]/following::tr[not(.//tr) and normalize-space()][1][ count(*)=2 and *[1][normalize-space()=''] and *[2][normalize-space()] ]/*[2]", $segment);

            if (empty($aircraft)) {
                $aircraft = $this->http->FindSingleNode($xpathSegmentRight . "/descendant::td[{$this->eq($this->t('AIRCRAFT:'))}]/following-sibling::td[normalize-space()][1]", $segment);
            }
            $cabin = $this->http->FindSingleNode($xpathSegmentRight . "/descendant::td[{$this->eq($this->t('CABIN:'))}]/following-sibling::td[normalize-space()][1]", $segment);
            $class = $this->http->FindSingleNode($xpathSegmentRight . "/descendant::td[{$this->eq($this->t('CLASS:'))}]/following-sibling::td[normalize-space()][1]", $segment, true, '/^[A-Z]{1,2}$/');
            $s->extra()
                ->duration($duration)
                ->aircraft($aircraft)
                ->cabin($cabin)
                ->bookingCode($class, true, true);

            if (!empty($seatsSegments[$si]) && !empty($cityFrom) && !empty($cityTo)
                && preg_match("/^\s*{$cityFrom} - {$cityTo} *(\n|,)/i", $seatsSegments[$si])
                && preg_match_all("/\((\d{1,3}[A-Z])\)/", $seatsSegments[$si], $m)
            ) {
                $s->extra()
                    ->seats($m[1]);
            }
        }

        $passengers = $this->http->FindNodes("//img[contains(@src, '/adt.png') or contains(@src, '/chd.png')]/ancestor::*[normalize-space()][1][count(.//text()[normalize-space()]) > 1]/descendant::text()[normalize-space()][2]", null, '/^[[:alpha:]][-.\'[:alpha:] ]*[[:alpha:]]$/u');

        if (empty(array_filter($passengers))) {
            $passengers = $this->http->FindNodes("//text()[{$this->eq($this->t('E-ticket number:'))}]/preceding::text()[normalize-space()][1]", null, '/^[[:alpha:]][-.\'[:alpha:] ]*[[:alpha:]]$/u');
        }

        if (empty(array_filter($passengers))) {
            $passengers = $this->http->FindNodes("//tr[{$this->contains($this->t('passengersHeader'))}]/following::*[{$this->eq($this->t('Adult(s)'))}]/following-sibling::node()[normalize-space()][1]", null, '/^[[:alpha:]][-.\'[:alpha:] ]*[[:alpha:]]$/u');
        }
        $passengers = array_filter($passengers);

        if (count($passengers)) {
            $f->general()->travellers(array_unique($passengers));
        }

        $tXpath = "//text()[{$this->eq($this->t('E-ticket number:'))}]/ancestor::tr[1]";
        $tNodes = $this->http->XPath->query($tXpath);
        $tickets = array_filter($this->http->FindNodes($tXpath, null, "/{$this->opt($this->t('E-ticket number:'))}[:：\s]*(\d{3}[- ]*\d{5,}[- ]*\d{1,2})\s*$/u"));

        if (count($tickets) == array_unique($tickets)) {
            foreach ($tNodes as $tRoot) {
                $ticket = $this->http->FindSingleNode('.', $tRoot, true,
                    "/{$this->opt($this->t('E-ticket number:'))}[:：\s]*(\d{3}[- ]*\d{5,}[- ]*\d{1,2})\s*$/u");

                if (!empty($ticket)) {
                    $traveller = $this->http->FindSingleNode('preceding::text()[normalize-space()][1]', $tRoot);
                    $f->issued()
                        ->ticket($ticket, false, $traveller);
                }
            }
        } else {
            $f->issued()
                ->tickets(array_unique($tickets), false);
        }

        $fXpath = "//text()[{$this->eq($this->t('Frequent Flyer number:'))}]/ancestor::tr[1]";
        $fNodes = $this->http->XPath->query($fXpath);
        $accounts = array_filter($this->http->FindNodes($fXpath, null, "/{$this->opt($this->t('Frequent Flyer number:'))}[:：\s]*(.*[\dA-Z]{5,})\s*$/u"));

        if (count($accounts) == array_unique($accounts)) {
            foreach ($fNodes as $fRoot) {
                $account = $this->http->FindSingleNode('.', $fRoot, true,
                    "/{$this->opt($this->t('Frequent Flyer number:'))}[:：\s]*(.*[\dA-Z]{5,})\s*$/u");

                if (!empty($account)) {
                    $traveller = $this->http->FindSingleNode("preceding::text()[normalize-space()][position() < 5][{$this->eq($passengers)}]",
                        $fRoot);
                    $f->program()
                        ->account($account, false, $traveller);
                }
            }
        } else {
            $f->program()
                ->accounts(array_unique($accounts), false);
        }

        $payment = $this->http->FindSingleNode("//tr[{$this->eq($this->t('Fare Details'))}]/following::*[{$this->contains($this->t('Total for all passengers'))}]/following::td[{$this->eq($this->t('Total'))} and not(ancestor-or-self::*[contains(@class,'hidden') or contains(@style,'display:none')])]/following-sibling::td[normalize-space()][1]");

        if (preg_match('/^(?<currency>[A-Z]{3})[ ]*(?<amount>\d[,.\'\d]*)$/', $payment, $m)) {
            // MYR 3,891.45
            $f->price()
            ->currency($m['currency'])
            ->total(PriceHelper::parse($m['amount'], $m['currency']));
        }
        $points = $this->http->FindSingleNode("//tr[{$this->eq($this->t('Fare Details'))}]/following::*[{$this->contains($this->t('Total for all passengers'))}]/following::td[{$this->eq($this->t('Total'))} and not(ancestor-or-self::*[contains(@class,'hidden') or contains(@style,'display:none')])]/ancestor::tr[1]/following-sibling::tr[1]/td[normalize-space()][1][contains(., '+')]",
            null, true, "/^\s*\+\s*(\d[\d, ]*POINT?S?)\s*$/i");
        //  IDR 1.244.200,00 + 49.600 POIN // id

        if (!empty($points)) {
            $f->price()
                ->spentAwards($points);
        }
        $fares = $this->http->FindNodes("//tr[{$this->eq($this->t('Fare Details'))}]/following::td[{$this->eq($this->t('Base fare'))}]/following-sibling::td[normalize-space()][1]");
        $fare = 0.0;

        foreach ($fares as $frow) {
            if (preg_match('/^(?<currency>[A-Z]{3})[ ]*(?<amount>\d[,.\'\d]*)$/', $frow, $m)) {
                $fare += PriceHelper::parse($m['amount'], $m['currency']);
            } else {
                $fare = null;

                break;
            }
        }

        if (!empty($fare)) {
            $f->price()
                ->cost($fare);
        }

        $taxes = $this->http->FindNodes("//tr[{$this->eq($this->t('Fare Details'))}]/following::td[{$this->eq($this->t('Taxes, Fees and Charges'))}]/following-sibling::td[normalize-space()][1]");
        $tax = 0.0;

        foreach ($taxes as $trow) {
            if (preg_match('/^(?<currency>[A-Z]{3})[ ]*(?<amount>\d[,.\'\d]*)$/', $trow, $m)) {
                $tax += PriceHelper::parse($m['amount'], $m['currency']);
            } else {
                $tax = null;

                break;
            }
        }

        if (!empty($tax)) {
            $f->price()
                ->tax($tax);
        }
    }

    private function assignLang(): bool
    {
        if (!isset(self::$dictionary, $this->lang)) {
            return false;
        }

        foreach (self::$dictionary as $lang => $phrases) {
            if (!is_string($lang) || empty($phrases['Itinerary Details']) || empty($phrases['Booking Reference Number']) || empty($phrases['AIRLINE:'])) {
                continue;
            }

            if ($this->http->XPath->query("//node()[{$this->contains($phrases['Booking Reference Number'])}]")->length > 0
                && $this->http->XPath->query("//node()[{$this->contains($phrases['AIRLINE:'])}]")->length > 0
                && $this->http->XPath->query("//node()[{$this->eq($phrases['Itinerary Details'])}]")->length > 0
            ) {
                $this->lang = $lang;

                return true;
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

    private function contains($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'contains(normalize-space(' . $node . '),"' . $s . '")';
        }, $field)) . ')';
    }

    private function eq($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'normalize-space(' . $node . ')="' . $s . '"';
        }, $field)) . ')';
    }

    private function starts($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'starts-with(normalize-space(' . $node . '),"' . $s . '")';
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

    private function normalizeDate($str)
    {
        // $this->logger->debug('$date = '.print_r( $str,true));
        $in = [
            // 2023年 January 01日 Sunday
            "/\s*(\d{4})\s*年\s*([[:alpha:]]+)\s+(\d{1,2})\s*日\s+[[:alpha:]]+\s*$/iu",
            // 10日 08月2023年
            '/^\s*(\d{1,2})\s*日\s*(\d{1,2})\s*月\s*(\d{4})\s*年\s*$/',
        ];
        $out = [
            "$3 $2 $1",
            "$3-$2-$1",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = \AwardWallet\Engine\MonthTranslate::translate($m[1], $this->lang)) {
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
}
