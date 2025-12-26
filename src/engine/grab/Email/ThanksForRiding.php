<?php

namespace AwardWallet\Engine\grab\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;

class ThanksForRiding extends \TAccountChecker
{
    public $mailFiles = "grab/it-58133711.eml";

    public $detectFrom = '@grab.com';

    public $detectSubject = [
        'Your Grab E-Receipt',
    ];

    public $detectBody = [
        'en' => ['Thanks for riding with', 'Hope you enjoyed your ride'],
        'zh' => ['希望您此次出行愉快！'],
        'vi' => ['Hy vọng bạn đã có một chuyến đi vui vẻ'],
        'id' => ['Semoga kamu menikmati perjalananmu ya!'],
    ];

    public $lang = 'en';
    public static $dictionary = [
        'en' => [
            'Picked up on' => 'Picked up on',
            // 'Booking ID:' => '',
            // 'Total Paid' => '',
            'Fare' => ['Fare', 'Metered fare'],
            // 'Breakdown' => '',
            // 'Passenger' => '',
            // 'Points earned' => '',
            // 'GrabRewards Points' => '',
            // 'Your Trip' => '',
        ],
        'zh' => [
            'Picked up on' => '于接载乘客',
            'Booking ID:'  => '预订号:',
            'Total Paid'   => '总计',
            'Fare'         => ['车费', 'Fare', 'Base fare'],
            'Breakdown'    => '细分',
            'Passenger'    => '乘客',
            // 'Points earned' => '',
            // 'GrabRewards Points' => '',
            'Your Trip' => '您的行程',
        ],
        'vi' => [
            'Picked up on' => 'Ngày đi',
            'Booking ID:'  => 'Mã đặt xe:',
            'Total Paid'   => 'Tổng cộng',
            'Breakdown'    => 'Chi tiết',
            'Fare'         => 'Giá cước',
            'Passenger'    => 'Khách đi xe',
            // 'Points earned' => '',
            // 'GrabRewards Points' => '',
            'Your Trip' => 'Chuyến đi của bạn',
        ],
        'id' => [
            'Picked up on' => 'Dijemput pada',
            'Booking ID:'  => 'Kode booking:',
            'Total Paid'   => 'Total',
            'Breakdown'    => 'Rincian',
            'Fare'         => 'Total Tarif',
            'Passenger'    => 'Penumpang',
            // 'Points earned' => '',
            // 'GrabRewards Points' => '',
            'Your Trip' => 'Perjalananmu',
        ],
    ];

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        foreach (self::$dictionary as $lang => $dict) {
            if (!empty($dict['Picked up on'])
                 && $this->http->XPath->query("//text()[{$this->starts($dict['Picked up on'])}]")->length > 0) {
                $this->lang = $lang;

                break;
            }
        }

        $t = $email->add()->transfer();

        $traveller = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Passenger'))}]/ancestor::td[1]/descendant::text()[normalize-space()][2]");

        if (!empty($traveller)) {
            $t->general()
                ->traveller($traveller);
        }

        $t->general()
            ->confirmation($this->http->FindSingleNode("//text()[{$this->starts($this->t('Booking ID:'))}]", null, true, "/^\s*{$this->opt($this->t('Booking ID:'))}\s*([A-Z\d\-]{5,})\s*$/"));

        // Price
        $priceTotal = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Breakdown'))}]/preceding::text()[{$this->eq($this->t('Total Paid'))}]/following::text()[normalize-space()][1]");

        if (preg_match('/^(?<currency>[^\d)(]+?)[ ]*(?<amount>\d[,.\'\d]*)$/', $priceTotal, $m)) {
            // RP 27.000    |    KHR 4,200
            $currency = $this->normalizeCurrency($m['currency']);
            $t->price()
                ->currency($currency)
                ->total($this->normalizeAmount($m['amount'], $currency));
        } else {
            $t->price()
                ->total(null);
        }

        $fare = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Breakdown'))}]/following::text()[normalize-space()][1]/ancestor::tr[count(*[normalize-space()]) = 2][*[normalize-space()][1][{$this->eq($this->t('Fare'))}]]/*[normalize-space()][2]");

        if (!empty($currency) && !empty($fare)) {
            $t->price()
                ->cost($this->normalizeAmount($fare, $currency));

            $fees = $this->http->XPath->query("//tr[count(*[normalize-space()]) = 2][preceding::text()[{$this->eq($this->t('Breakdown'))}]][preceding::tr[{$this->starts($this->t('Fare'))}]][following::tr[{$this->starts($this->t('Total Paid'))}]][not(*[contains(@style, '#818181')])]");
            $discount = 0.0;

            foreach ($fees as $fRoot) {
                $value = $this->http->FindSingleNode("*[normalize-space()][2]", $fRoot);

                if (preg_match("/^\s*-\s*(\d.+)/", $value, $m)) {
                    $discount += $this->normalizeAmount($m[1], $currency);
                } else {
                    $t->price()
                        ->fee($this->http->FindSingleNode("*[normalize-space()][1]", $fRoot),
                            $this->normalizeAmount($value, $currency));
                }
            }

            if (!empty($discount)) {
                $t->price()
                    ->discount($discount);
            }
        }

        $earnedAwards = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Points earned'))}]/following::text()[normalize-space()][1]/ancestor::*[not(.//text()[{$this->eq($this->t('Points earned'))}])][last()]//text()[{$this->eq($this->t('GrabRewards Points'))}]/following::text()[normalize-space()][1]",
            null, true, "/^\s*\+\s*(.+)/");

        if (!empty($earnedAwards)) {
            $t->program()
                ->earnedAwards($earnedAwards);
        }

        // Segment
        $segment = $t->addSegment();
        $dateTransef = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Picked up on'))}]", null, true, "/^\s*{$this->opt($this->t('Picked up on'))}\s+(\d+\s+\w+\s+\d{4})$/");
        $timeDep = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Your Trip'))}]/following::text()[contains(normalize-space(), 'AM') or contains(normalize-space(), 'PM')][1]");

        if (empty($timeDep)) {
            $timeDep = $this->http->FindSingleNode("//img[contains(@src, 'icon_pick_up_2x')]/ancestor::tr[1]/descendant::td[2]/descendant::text()[normalize-space()][2]");
        }

        $timeArr = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Your Trip'))}]/following::text()[contains(normalize-space(), 'AM') or contains(normalize-space(), 'PM')][2]");

        if (empty($timeArr)) {
            $timeArr = $this->http->FindSingleNode("//img[contains(@src, 'icon_drop_off_2x')]/ancestor::tr[1]/descendant::td[2]/descendant::text()[normalize-space()][2]");
        }

        $depName = $this->http->FindSingleNode("//img[contains(@src, 'icon_pick_up_2x')]/ancestor::tr[1]/descendant::td[2]/descendant::text()[normalize-space()][1]");

        if (empty($depName)) {
            $depName = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Your Trip'))}]/following::text()[contains(normalize-space(), 'AM') or contains(normalize-space(), 'PM')][1]/preceding::text()[normalize-space()][1]");
        }

        $segment->departure()
            ->date((!empty($dateTransef) && !empty($timeDep)) ? strtotime($dateTransef . ', ' . $timeDep) : null)
            ->name($depName);

        $arrName = $this->http->FindSingleNode("//img[contains(@src, 'icon_drop_off_2x')]/ancestor::tr[1]/descendant::td[2]/descendant::text()[normalize-space()][1]");

        if (empty($arrName)) {
            $arrName = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Your Trip'))}]/following::text()[contains(normalize-space(), 'AM') or contains(normalize-space(), 'PM')][2]/preceding::text()[normalize-space()][1]");
        }

        $dateArrival = null;

        if (!empty($dateTransef) && !empty($timeArr)) {
            $dateArrival = strtotime($dateTransef . ', ' . $timeArr);

            if ($segment->getDepDate() > $dateArrival) {
                $dateArrival = strtotime('+1 day', $dateArrival);
            }
        }
        $segment->arrival()
            ->date($dateArrival)
            ->name($arrName);

        $region = array_values(array_unique(array_filter($this->http->FindNodes("//a[contains(@href, 'help.grab.com') and contains(@href, 'passenger')]/@href",
            null, "/help\.grab\.com(?:%2F|\*2F|\/)passenger(?:%2F|\*2F|\/)[a-z]{2}-([a-z]{2})(?:%3F|\*3F|\?)/"))));

        if (count($region) === 1) {
            $segment->departure()
                ->geoTip($region[0]);
            $segment->arrival()
                ->geoTip($region[0]);
        }

        $milesDuration = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Your Trip'))}]/ancestor::tr[1]/descendant::text()[normalize-space()][2]");

        $segment->extra()
            ->miles($this->re('/^([\d\.]+\s\D+)\s[•]\s+/u', $milesDuration))
            ->duration($this->re('/^.+[•]\s+(.+)$/u', $milesDuration));

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailFromProvider($from)
    {
        return strpos($from, $this->detectFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        foreach ($this->detectSubject as $dSubject) {
            if (stripos($headers['subject'], $dSubject) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query('//a[contains(@href,".grab.com/") or contains(@href,"help.grab.com")]')->length === 0
            && $this->http->XPath->query("//tr[{$this->eq(['GrabCar', 'GrabTukTuk', 'GrabBike', 'GrabTaxi', 'GrabSUV', 'GrabRemorque'])}]")->length === 0
        ) {
            return false;
        }

        foreach ($this->detectBody as $dBody) {
            if ($this->http->XPath->query("//text()[" . $this->contains($dBody) . "]")->length > 0) {
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
        return count(self::$dictionary);
    }

    private function t($s)
    {
        if (!isset($this->lang, self::$dictionary[$this->lang], self::$dictionary[$this->lang][$s])) {
            return $s;
        }

        return self::$dictionary[$this->lang][$s];
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(' or ', array_map(function ($s) {
            return 'normalize-space(.)="' . $s . '"';
        }, $field));
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(' or ', array_map(function ($s) {
            return 'starts-with(normalize-space(.),"' . $s . '")';
        }, $field));
    }

    private function contains($field, $text = '.')
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($text) { return 'contains(normalize-space(' . $text . '),"' . $s . '")'; }, $field)) . ')';
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

        if (count($field) === 0) {
            return '';
        }

        return '(?:' . implode('|', array_map(function ($s) {
            return preg_quote($s, '/');
        }, $field)) . ')';
    }

    private function normalizeAmount(string $s, ?string $currency = null): ?float
    {
        $s = PriceHelper::parse($s, $currency);

        return is_numeric($s) ? (float) $s : null;
    }

    private function normalizeCurrency(string $string): string
    {
        $string = trim($string);
        $currencies = [
            'IDR' => ['RP'],
            'PHP' => ['P'],
            'MYR' => ['RM'],
        ];

        foreach ($currencies as $currencyCode => $currencyFormats) {
            foreach ($currencyFormats as $currency) {
                if ($string === $currency) {
                    return $currencyCode;
                }
            }
        }

        return $string;
    }
}
