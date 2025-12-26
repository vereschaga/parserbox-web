<?php

namespace AwardWallet\Engine\italiarail\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class YourOrder extends \TAccountChecker
{
    public $mailFiles = "italiarail/it-113013740.eml, italiarail/it-113564810.eml, italiarail/it-232765810.eml, italiarail/it-33856886.eml";
    public $subjects = [
        'Your ItaliaRail order number is ',
    ];

    public $lang = '';

    public static $dictionary = [
        "en" => [
            'Arrive:'   => ['Arrive:', 'Arrival:'],
            'Carriage:' => ['Carriage:', 'CARRIAGE'],
            'Seat:'     => ['Seat:', 'SEAT'],
        ],
        "zh" => [
            'Depart:'   => '出发：',
            'Arrive:'   => '抵达：',
            'Carriage:' => '马车：',
            'Seat:'     => '座位：',
            'Class:'    => '类别：',
            //'PASSENGER NAME(S)' => '',
            'YOUR RECEIPT'     => '您的电子机票',
            'Ticket:'          => '工单：',
            'TRAIN #:'         => '列车编号：',
            'to'               => '至',
            'ORDER #'          => '订单号：',
            'YOUR E-TICKET(S)' => '您的电子机票',
        ],
    ];

    public $detectLand = [
        'zh' => ['座位：'],
        'en' => ['Seat', 'SEAT'],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@italiarail.com') !== false) {
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

        if ($this->http->XPath->query("//text()[contains(normalize-space(), 'info@italiarail.com')]")->length > 0
        || $this->http->XPath->query("//a[contains(@href, 'italiarail.com')]")->length > 0) {
            return $this->http->XPath->query("//text()[{$this->contains($this->t('ORDER #'))}]")->length > 0
                && ($this->http->XPath->query("//text()[{$this->contains($this->t('SEAT ASSIGNMENT'))}]")->length > 0
                || $this->http->XPath->query("//text()[{$this->contains($this->t('YOUR E-TICKET(S)'))}]")->length > 0);
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]italiarail\.com$/', $from) > 0;
    }

    public function ParseEmail(Email $email)
    {
        $t = $email->add()->train();

        $travellers = array_unique($this->http->FindNodes("//text()[{$this->eq($this->t('PASSENGER NAME(S)'))}]/ancestor::tr[1]/descendant::text()[normalize-space()][not(contains(normalize-space(), ')'))]"));

        if (count($travellers) == 0) {
            $travellers = array_unique($this->http->FindNodes("//text()[{$this->eq($this->t('Class:'))}]/preceding::text()[normalize-space()][1]"));
        }

        if (empty($travellers) && empty($this->http->FindSingleNode("(//text()[{$this->contains($this->t('PASSENGER NAME(S)'))}])[1]"))) {
            $travellers[] = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Lead Passenger:'))}]/following::text()[normalize-space()][1]");
        }
        $t->general()
            ->travellers($travellers, true);

        $pnr = array_unique($this->http->FindNodes("//text()[{$this->eq($this->t('Lead Passenger:'))}]/preceding::text()[normalize-space()][1]", null, "/^([A-Z\d]+)$/"));

        if (isset($pnr[0]) && strlen($pnr[0]) < 3) {
            $pnr = array_unique($this->http->FindNodes("//text()[{$this->eq($this->t('Lead Passenger:'))}]/preceding::text()[normalize-space()][1]/ancestor::div[1]", null, "/^([A-Z\d]+)$/"));
        }

        if (count($pnr) == 1) {
            $t->general()
                ->confirmation($pnr[0], 'PNR');
        } elseif (count($pnr) > 1) {
            foreach ($pnr as $conf) {
                $t->general()
                    ->confirmation($conf, 'PNR');
            }
        } elseif (count($pnr) == 0) {
            $t->general()->noConfirmation();
        }

        $total = $this->http->FindSingleNode("//text()[{$this->eq($this->t('YOUR RECEIPT'))}]/following::text()[{$this->eq($this->t('Total'))}]/ancestor::tr[1]/descendant::td[2]", null, true, "/^\s*\D\s*([\d\,\.]+)/");
        $currency = $this->http->FindSingleNode("//text()[{$this->eq($this->t('YOUR RECEIPT'))}]/following::text()[{$this->eq($this->t('Total'))}]/ancestor::tr[1]/descendant::td[2]", null, true, "/^\s*(\D)\s*([\d\,\.]+)/");

        if (!empty($total) && !empty($currency)) {
            $t->price()
                ->total($total)
                ->currency($currency);

            $cost = $this->http->FindSingleNode("//text()[{$this->eq($this->t('YOUR RECEIPT'))}]/following::text()[normalize-space()='Subtotal']/ancestor::tr[1]/descendant::td[2]", null, true, "/^\s*\D\s*([\d\,\.]+)/");

            if (!empty($cost)) {
                $t->price()
                    ->cost($cost);
            }

            $fee = $this->http->FindSingleNode("//text()[{$this->eq($this->t('YOUR RECEIPT'))}]/following::text()[normalize-space()='Processing Fee']/ancestor::tr[1]/descendant::td[2]", null, true, "/^\s*\D\s*([\d\,\.]+)/");

            if (!empty($fee)) {
                $t->price()
                    ->fee('Processing Fee', $fee);
            }
        }

        $tickets = array_unique($this->http->FindNodes("//text()[{$this->eq($this->t('TICKET NUMBER(S)'))}]/ancestor::tr[1]/descendant::text()[normalize-space()][not(contains(normalize-space(), ')'))]"));

        if (count($tickets) == 0) {
            $tickets = array_filter(array_unique($this->http->FindNodes("//text()[{$this->eq($this->t('Ticket:'))}]/following::text()[normalize-space()][1]", null, "/^(\d{7,})$/")));
        }

        if (count($tickets) > 0) {
            $t->setTicketNumbers($tickets, false);
        }

        $nodes = $this->http->XPath->query("//text()[{$this->eq($this->t('Depart:'))}]/preceding::text()[{$this->contains($this->t('to'))}][1]/ancestor::tr[1]");

        foreach ($nodes as $root) {
            $segmentText = implode("\n", $this->http->FindNodes("./descendant::text()[normalize-space()]", $root));

            if (preg_match("/{$this->opt($this->t('TRAIN #:'))}\s*(?<trainNumber>\d+)\s*(?<companyName>.+)\n(?<depName>.+)\n{$this->opt($this->t('to'))}\n(?<arrName>.+)\n{$this->opt($this->t('Depart:'))}\n(?<depDate>.+)\n{$this->opt($this->t('Arrive:'))}\n(?<arrDate>.+)/", $segmentText, $m)
                || preg_match("/{$this->opt($this->t('TRAIN #:'))}\s*(?<trainNumber>\d+)\s*(?<companyName>.+)\n(?<depName>.+)\n{$this->opt($this->t('to'))}\n(?<arrName>.+)\n{$this->opt($this->t('Depart:'))}\n(?<depDate>.+\n[\d\:]+)\s*[A-Z]+\n(?:\(.+\))?\n*{$this->opt($this->t('Arrive:'))}\n(?<arrDate>.+\n[\d\:]+)/", $segmentText, $m)) {
                $s = $t->addSegment();

                $s->extra()
                    ->number($m['trainNumber'])
                    ->service($m['companyName']);

                $s->departure()
                    ->name($m['depName'] . ', Europe')
                    ->date($this->normalizeDate($m['depDate']));

                $s->arrival()
                    ->name($m['arrName'] . ', Europe')
                    ->date($this->normalizeDate($m['arrDate']));

                $cabin = array_unique(array_filter($this->http->FindNodes("./following::text()[{$this->eq($this->t('SEAT ASSIGNMENT'))}][1]/ancestor::tr[1]/descendant::text()[{$this->eq($this->t('Carriage:'))}]/following::text()[normalize-space()][2][not({$this->contains($this->t('Seat'))})]", $root)));

                if (count($cabin) == 0) {
                    $cabin = array_unique(array_filter($this->http->FindNodes("./following::text()[{$this->eq($this->t('SEAT ASSIGNMENT'))}][1]/ancestor::tr[1]/descendant::text()[{$this->eq($this->t('CARRIAGE'))}]/following::text()[normalize-space()][2][not({$this->contains($this->t('Seat'))})]", $root)));
                }

                if (count($cabin) == 1) {
                    $s->extra()
                        ->cabin($cabin[0]);
                }

                $seats = array_filter($this->http->FindNodes("./following::text()[{$this->eq($this->t('SEAT ASSIGNMENT'))}][1]/ancestor::tr[1]/descendant::text()[{$this->eq($this->t('Seat:'))}]/following::text()[normalize-space()][1]", $root, "/^(\d+\D?)$/"));

                if (count($seats) > 0) {
                    $s->setSeats($seats);
                } else {
                    foreach ($travellers as $traveller) {
                        $seats[] = $this->http->FindSingleNode("./following::text()[normalize-space()='{$traveller}'][1]/ancestor::table[1]/descendant::text()[{$this->eq($this->t('Seat:'))}]/following::text()[normalize-space()][1]", $root, true, "/^(\d+\D?)$/");
                        $cabin[] = $this->http->FindSingleNode("./following::text()[normalize-space()='{$traveller}'][1]/ancestor::table[1]/descendant::text()[{$this->eq($this->t('Class:'))}]/following::text()[normalize-space()][1]", $root);
                        $carNumber[] = $this->http->FindSingleNode("./following::text()[normalize-space()='{$traveller}'][1]/ancestor::table[1]/descendant::text()[{$this->eq($this->t('Carriage:'))}]/following::text()[normalize-space()][1]", $root, true, "/^(\d+\D?)$/");
                    }

                    $s->setSeats(array_filter(array_unique($seats)));

                    $s->setCabin(implode(', ', array_filter(array_unique($cabin))), true, true);

                    if (count(array_filter(array_unique($carNumber))) > 0) {
                        $s->setCarNumber(implode(', ', array_filter(array_unique($carNumber))));
                    }
                }

                $carNumber = array_unique(array_filter($this->http->FindNodes("./following::text()[{$this->eq($this->t('SEAT ASSIGNMENT'))}][1]/ancestor::tr[1]/descendant::text()[{$this->eq($this->t('Carriage:'))}]/following::text()[normalize-space()][1]", $root, "/^([A-Z\d]+)$/")));

                if (count($carNumber) == 1) {
                    $s->setCarNumber($carNumber[0]);
                }
            }
        }
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();

        $otaConf = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'ItaliaRail Order #')]", null, true, "/{$this->opt($this->t('ItaliaRail Order #'))}\s*([A-Z\d\-]{10,})/");

        if (empty($otaConf)) {
            $otaConf = $this->http->FindSingleNode("//text()[contains(normalize-space(), 'ItaliaRail Order #')]", null, true, "/{$this->opt($this->t('ItaliaRail Order #'))}\s*([A-Z\d\-]{10,})/");
        }

        if (!empty($otaConf)) {
            $email->ota()->confirmation($otaConf, 'ItaliaRail Order #');
        }

        $this->ParseEmail($email);

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
        return count(self::$dictionary);
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

    private function normalizeDate($date)
    {
        $in = [
            //November 05, 2021 16:18 CET
            '#^(\w+)\s*(\d+)\,\s*(\d{4})\s*([\d\:]+)\s*[A-Z]+$#i',
            //2023 年 1 月 12 日
            //14:52
            '#^(\d{4})\s*\D\s*(\d+)\s*\D\s*(\d+)\s*\D\s*([\d\:]+)$#us',
        ];
        $out = [
            '$2 $1 $3, $4',
            '$2.$3.$1, $4',
        ];
        $str = preg_replace($in, $out, $date);

        return strtotime($str);
    }

    private function assignLang()
    {
        foreach ($this->detectLand as $key => $words) {
            foreach ($words as $word) {
                if ($this->http->XPath->query("//text()[{$this->contains($word)}]")->length > 0) {
                    $this->lang = $key;

                    return true;
                }
            }
        }

        return false;
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(" or ", array_map(function ($s) {
            return "normalize-space(.)=\"{$s}\"";
        }, $field)) . ')';
    }
}
