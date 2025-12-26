<?php

namespace AwardWallet\Engine\jejuair\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class eTicket extends \TAccountChecker
{
    public $mailFiles = "jejuair/it-579844733.eml, jejuair/it-701641477.eml, jejuair/it-703395851.eml";
    public $subjects = [
        ', here’s your JEJUAIR e-Ticket Itinerary confirmation',
    ];

    public $lang = '';

    public $detectLang = [
        "en" => ['Itinerary'],
        "ja" => ['旅程情報'],
        "ko" => ['여정 정보'],
    ];

    public static $dictionary = [
        "en" => [
            'travellers'         => ['Adult', 'dult', 'Child'],
            'Originating Flight' => ['Originating Flight', 'Journey1'],
            'Return Flight'      => ['Return Flight', 'Journey2'],
        ],

        "ja" => [
            'View My Bookings in Detail' => '予約状況の詳細を見る',
            'Itinerary'                  => '旅程情報',
            'Booking reference'          => '予約番号',
            'travellers'                 => '大人',
            'Booking date'               => '予約日',
            'Total'                      => '合計金額',
            'Flight '                    => '便名 ',
            'Terminal'                   => 'ターミナル',
            'Additional Services'        => '付加サービス',
            'Originating Flight'         => '往路',
            //'Return Flight' => '',
            'Booked Seats' => '事前座席指定',
        ],

        "ko" => [
            "View My Bookings in Detail" => '나의 예약현황 자세히 보기',
            'Itinerary'                  => '여정 정보',
            'Booking reference'          => '예약번호',
            'travellers'                 => '성인',
            'Booking date'               => '예약일',
            'Total'                      => '총 금액',
            'Flight '                    => '편명 ',
            'Terminal'                   => '터미널',
            //'Additional Services' => '',
            'Originating Flight' => '가는편',
            'Return Flight'      => '오는편',
            //'Booked Seats' => '',
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@jejuair.net') !== false) {
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
        $this->assignLang();

        return $this->http->XPath->query("//text()[contains(normalize-space(), 'JEJUAIR')]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('View My Bookings in Detail'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Itinerary'))}]")->length > 0;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]jejuair.net$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();
        $this->ParseFlight($email);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function ParseFlight(Email $email)
    {
        $f = $email->add()->flight();

        $f->general()
            ->confirmation($this->http->FindSingleNode("//text()[{$this->eq($this->t('Booking reference'))}]/ancestor::tr[1]", null, true, "/{$this->opt($this->t('Booking reference'))}\s+([A-Z\d]{6})/"))
            ->travellers(str_replace("KRW", "", $this->http->FindNodes("//text()[{$this->contains($this->t('travellers'))}]/ancestor::p[1]", null, "/{$this->opt($this->t('travellers'))}\s*\d+\s*(.+)\s+\d+/")));

        $bookingDate = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Booking date'))}]/ancestor::tr[1]", null, true, "/{$this->opt($this->t('Booking date'))}\s*(\d{4}\.\d+\.\d+)/");

        if (!empty($bookingDate)) {
            $f->general()
                ->date($this->normalizeDate($bookingDate));
        }

        $price = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Total'))}]/following::text()[normalize-space()][1]");

        if (preg_match("/^(?<currency>\D{1,3})\s*(?<total>[\d\.\,]+)$/", $price, $m)
           || preg_match("/^(?<total>[\d\.\,]+)\s*(?<currency>\D{1,3})$/", $price, $m)) {
            $f->price()
                ->currency($this->normalizeCurrency($m['currency']))
                ->total(PriceHelper::parse($m['total'], $this->normalizeCurrency($m['currency'])));
        }

        $nodes = $this->http->XPath->query("//text()[{$this->starts($this->t('Flight '))}]");

        foreach ($nodes as $key => $root) {
            $s = $f->addSegment();

            $airlineInfo = $this->http->FindSingleNode(".", $root, true, "/{$this->opt($this->t('Flight '))}((?:[A-Z][A-Z\d]|[A-Z\d][A-Z])\d{1,4})/");

            if (preg_match("/^(?<aName>(?:[A-Z][A-Z\d]|[A-Z\d][A-Z]))(?<fNumber>\d{1,4})$/", $airlineInfo, $m)) {
                $s->airline()
                    ->name($m['aName'])
                    ->number($m['fNumber']);
            }

            $depTerminal = $this->http->FindSingleNode("./ancestor::tr[1]/preceding::tr[1]/descendant::td[2]", $root, true, "/{$this->opt($this->t('Terminal'))}\s*(.+)/");

            if (!empty($depTerminal)) {
                $s->departure()
                    ->terminal($depTerminal);
            }

            $codeInfo = $this->http->FindSingleNode("./ancestor::tr[1]/following::tr[1]", $root);

            if (preg_match("/\((?<depCode>[A-Z]{3})\).+\((?<arrCode>[A-Z]{3})\)/", $codeInfo, $m)) {
                $s->departure()
                    ->code($m['depCode']);

                $s->arrival()
                    ->code($m['arrCode']);
            }

            $dateInfo = $this->http->FindSingleNode("./ancestor::tr[1]/following::tr[2]", $root);

            if (preg_match("/(?<depDate>\d{4}\.\d+\.\d+)\s*\D+(?<depTime>[\d\:]+).*(?<arrDate>\d{4}\.\d+\.\d+)\s*\D+(?<arrTime>[\d\:]+)/", $dateInfo, $m)) {
                $s->departure()
                    ->date($this->normalizeDate($m['depDate'] . ', ' . $m['depTime']));

                $s->arrival()
                    ->date($this->normalizeDate($m['arrDate'] . ', ' . $m['arrTime']));
            }

            if ($key === 0) {
                $seats = $this->http->FindNodes("//text()[{$this->starts($this->t('Additional Services'))}]/following::text()[{$this->eq($this->t('Originating Flight'))}]/ancestor::tr[1]/following::tr[1]", null, "/{$this->opt($this->t('Booked Seats'))}\s*(\d+[A-Z])/");
                $this->addSeat($seats, $s);
            } elseif ($key === 1) {
                $seats = $this->http->FindNodes("//text()[{$this->starts($this->t('Additional Services'))}]/following::text()[{$this->eq($this->t('Return Flight'))}]/ancestor::tr[1]/following::tr[1]", null, "/{$this->opt($this->t('Booked Seats'))}\s*(\d+[A-Z])/");
                $this->addSeat($seats, $s);
            }
        }
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public function addSeat(array $seats, \AwardWallet\Schema\Parser\Common\FlightSegment $s)
    {
        foreach ($seats as $seat) {
            $pax = $this->http->FindSingleNode("//text()[{$this->eq($seat)}]/preceding::text()[{$this->contains($this->t('travellers'))}]/ancestor::span[1]/following::span[1]", null, true, "/^([[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]])$/");

            if (!empty($pax)) {
                $s->extra()
                    ->seat($seat, false, false, $pax);
            } else {
                $s->extra()
                    ->seat($seat);
            }
        }
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
    }

    public function assignLang()
    {
        foreach ($this->detectLang as $lang => $words) {
            foreach ($words as $word) {
                if ($this->http->XPath->query("//text()[{$this->contains($word)}]")->length > 0) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    protected function eq($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false';
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

    private function normalizeDate($str)
    {
        $in = [
            // 2023.10.30
            '/^(\d{4})\.(\d+)\.(\d+)$/su',
            // 2023.10.30 15:53
            '/^(\d{4})\.(\d+)\.(\d+)\,\s+([\d\:]+)$/su',
        ];
        $out = [
            "$3.$2.$1",
            "$3.$2.$1, $4",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return strtotime($str);
    }

    private function normalizeCurrency($string)
    {
        $string = trim($string);
        $currencies = [
            'GBP' => ['£'],
            'AUD' => ['A$'],
            'EUR' => ['€', 'Euro'],
            'USD' => ['US Dollar'],
            'KWR' => ['원'],
        ];

        foreach ($currencies as $code => $currencyFormats) {
            foreach ($currencyFormats as $currency) {
                if ($string === $currency) {
                    return $code;
                }
            }
        }

        return $string;
    }
}
