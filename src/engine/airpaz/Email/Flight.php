<?php

namespace AwardWallet\Engine\airpaz\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class Flight extends \TAccountChecker
{
    public $mailFiles = "airpaz/it-786466089.eml, airpaz/it-789638304.eml, airpaz/it-797026739.eml, airpaz/it-801275446.eml, airpaz/it-803757935.eml";
    public $subjects = [
        'Airpaz.com - order',
    ];

    public $lang = '';
    public $detectLang = [
        "en" => ['Depart'],
        "pt" => ['Partida'],
        "zh" => ['飛行詳情'],
        "th" => ['เที่ยวบิน'],
    ];

    public static $dictionary = [
        "en" => [
            'Airpaz.com - Order'                                         => ['Airpaz.com - Order', 'Airpaz.com - order'],
            'Price detail'                                               => ['Price detail', 'Price Detail'],
            'This Change Flight request has been processed successfully' => [
                'This Change Flight request has been processed successfully',
                'this change flight request has been processed successfully',
            ],
        ],

        "pt" => [
            'Flight Details'  => 'Detalhes do Voo',
            'Online Check-in' => ['Check-in Online', 'Online Check-in'],
            'Price detail'    => 'Detalhe do preço',

            'Airpaz.com - Order' => 'Airpaz.com - Encomenda',
            'Booking Code :'     => 'Código de Reserva :',
            'Passengers'         => 'Passageiros',
            'Departure'          => 'Partida',
            //'Return' => '',
            'Child' => 'Criança',
            //'Travelers' => '',
            'Total'      => 'Total',
            'Fare Price' => 'Preço da Tarifa',
            'Discount'   => 'Desconto',
            //'Stop' => '',
            'Direct' => 'Direct',
            'Depart' => 'Partida',
        ],

        "zh" => [
            'Flight Details'  => '飛行詳情',
            'Online Check-in' => ['線上簽到'],
            'Price detail'    => '價格詳情',

            'Airpaz.com - Order' => 'Airpaz.com - 預訂',
            'Booking Code :'     => '預訂號碼 :',
            'Passengers'         => '乘客',
            'Departure'          => '出發',
            //'Return' => '',
            //'Child' => '',
            //'Travelers' => '',
            'Total'      => '總金額',
            'Fare Price' => '票價',
            'Discount'   => '折扣',
            //'Stop' => '',
            'Direct'   => 'Direct',
            'Depart'   => '出發',
            'Terminal' => '航廈',
        ],

        "th" => [
            'Flight Details'  => 'การจองของคุณกับรหัส',
            'Online Check-in' => ['จัดเส้นทางใหม่:'],
            'Price detail'    => 'รายละเอียดราคา',

            'Airpaz.com - Order' => 'Airpaz.com คำสั่งซื้อ',
            'Booking Code :'     => 'รหัส Airpaz :',
            'Travelers'          => 'ผู้เดินทาง',
            //'Departure'          => '出發',
            //'Return' => '',
            //'Child' => '',
            //'Travelers' => '',
            'Total'      => 'ทั้งหมด',
            'Fare Price' => 'ราคาค่าโดยสาร',
            'Discount'   => 'ส่วนลด',
            //'Stop' => '',
            'Direct'   => 'Direct',
            //'Depart'   => '出發',
            //'Terminal' => '航廈',
            'kg' => 'ก.ก',
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], 'airpaz.com') !== false) {
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
        $this->AssignLang();

        if ($this->http->XPath->query("//text()[contains(normalize-space(), 'Airpaz.com')]")->length === 0) {
            return false;
        }

        if ($this->http->XPath->query("//text()[{$this->contains($this->t('Flight Details'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Online Check-in'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Price detail'))}]")->length > 0) {
            return true;
        }

        if ($this->http->XPath->query("//text()[{$this->contains($this->t('this cancellation request needs your approval.'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('One step closer to start processing your request.'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Approve My Request'))}]")->length > 0) {
            return true;
        }

        if ($this->http->XPath->query("//text()[{$this->contains($this->t('This Change Flight request has been processed successfully'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Please visit our website/app and open'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Check My Request'))}]")->length > 0) {
            return true;
        }

        if ($this->http->XPath->query("//text()[{$this->contains($this->t('Please make a payment for your order'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Flight :'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('If your payment does not succeed please go to Manage Booking'))}]")->length > 0) {
            return true;
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]\D*\.*airpaz.com$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->AssignLang();

        $result = array_filter($this->http->FindNodes("//text()[{$this->contains($this->t('Stop'))} or {$this->contains($this->t('Direct'))} or {$this->contains($this->t('Transit'))}]/ancestor::table[normalize-space()][1][not({$this->contains($this->t('Depart'))} or {$this->contains($this->t('Return'))})]/following::text()[normalize-space()][1]", null, "/^(\d+\:\d+)/"));

        if (count($result) > 0) {
            $this->ParseFlight($email);
        } else {
            $this->ParseFlight2($email);
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function ParseFlight(Email $email)
    {
        $this->logger->debug(__METHOD__);
        $f = $email->add()->flight();

        $info = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Airpaz.com - Order'))}]/ancestor::tr[1]");

        if (preg_match("/{$this->opt($this->t('Airpaz.com - Order'))}\s+(?<otaConf>\d+)\s+(?<status>.+)/", $info, $m)) {
            $email->ota()
                ->confirmation($m['otaConf']);

            $f->setStatus($m['status']);
        }

        $confs = array_unique($this->http->FindNodes("//text()[{$this->starts($this->t('Booking Code :'))}]", null, "/\:\s*([A-Z\d]{6,10})\s*$/"));

        foreach ($confs as $conf) {
            $f->general()
                ->confirmation($conf);
        }

        $tickets = [];
        $travellers = [];

        $paxs = $this->http->FindNodes("//text()[{$this->contains($this->t('Passengers'))}]/following::text()[{$this->starts($this->t('Departure'))} or {$this->starts($this->t('Return'))}]/ancestor::table[1]/descendant::text()[starts-with(translate(normalize-space(), '0123456789', 'dddddddddd'), 'd.')]/ancestor::td[1]");

        if (count($paxs) > 0) {
            foreach ($paxs as $pax) {
                if (preg_match("/^\d+\.\s+(?:Mrs|Mr|Ms|Sr\.|Sra\.|先生|女士)?\s*(?<traveller>[[:alpha:]][-.\/\'’[:alpha:] ]*[[:alpha:]])\s*(?:\({$this->opt($this->t('Child'))}\))?(?:[\s\-]+(?<tickets>[\d\s\,]+))?(?:[\s\-]+[A-Z\d\,\s]+)?$/u", $pax, $m)) {
                    $travellers[] = $m['traveller'];

                    if (isset($m['tickets'])) {
                        $tickets = array_merge($tickets, explode(', ', trim($m['tickets'], ',')));
                    }
                }
            }
        } else {
            $travellers = $this->http->FindNodes("//text()[{$this->contains($this->t('Passengers'))}]/following::table[1]/descendant::text()[starts-with(translate(normalize-space(), '0123456789', 'dddddddddd'), 'd.')]", null, "/^\d+\.(.+)/");
        }

        if (count($travellers) === 0) {
            $travellers = $this->http->FindNodes("//text()[{$this->eq($this->t('Travelers'))}]/ancestor::tr[1]/following::table[1]/descendant::text()[normalize-space()][not(contains(normalize-space(), '-') or contains(normalize-space(), 'kg') or contains(normalize-space(), ':'))]");
        }

        if (count(array_filter($tickets)) > 0) {
            $f->setTicketNumbers(array_filter(array_unique($tickets)), false);
        }

        if (count($travellers) > 0) {
            $f->general()
                ->travellers(array_unique(preg_replace("#(?:\({$this->t('Child')}\)|Mrs|Mr|Ms|Sr\.|Sra\.)#", "", $travellers)));
        }

        $price = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Total'))}]/ancestor::tr[1]", null, true, "/^{$this->opt($this->t('Total'))}\s*(\D+[\.\,\'\d]+)$/");

        if (preg_match("/(?<currency>\D+)\s+(?<total>[\.\,\'\d]+)/", $price, $m)) {
            $currency = $this->normalizeCurrency($m['currency']);
            $f->price()
                ->total(PriceHelper::parse($m['total'], $currency))
                ->currency($currency);

            $cost = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Fare Price'))}]/ancestor::tr[1]", null, true, "/{$this->opt($this->t('Fare Price'))}\s*\D+([\d\.\,\']+)/");

            if ($cost !== null) {
                $f->price()
                    ->cost(PriceHelper::parse($cost, $currency));
            }

            $priceNode = $this->http->XPath->query("//text()[{$this->eq($this->t('Fare Price'))}]/ancestor::div[2]/following-sibling::div[not({$this->contains($this->t('Total'))} or {$this->contains($this->t('Discount'))})]/descendant::tr[last()]");

            foreach ($priceNode as $rootPrice) {
                $name = $this->http->FindSingleNode("./descendant::text()[normalize-space()][1]", $rootPrice);
                $summ = $this->http->FindSingleNode("./descendant::text()[normalize-space()][2]", $rootPrice, true, "/\D+([\d\.\,\']+)/");

                if (!empty($name) && $summ !== null) {
                    $f->price()
                        ->fee($name, PriceHelper::parse($summ, $currency));
                }
            }

            $discount = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Discount'))}]/ancestor::tr[1]", null, true, "/{$this->opt($this->t('Discount'))}\s*\-?\s*\D{1,3}\s*([\d\,\.]+)/");

            if (!empty($discount)) {
                $f->price()
                    ->discount(PriceHelper::parse($discount, $currency));
            }
        }

        $nodes = $this->http->XPath->query("//text()[{$this->contains($this->t('Stop'))} or {$this->contains($this->t('Direct'))}]/ancestor::table[normalize-space()][1][not({$this->contains($this->t('Depart'))} or {$this->contains($this->t('Return'))})]");

        foreach ($nodes as $root) {
            $s = $f->addSegment();

            $airlineInfo = implode("\n", $this->http->FindNodes("./descendant::text()[normalize-space()]", $root));

            if (preg_match("/^.+\n(?<aName>(?:[A-Z][A-Z\d]|[A-Z\d][A-Z]))(?<fNumber>\d{1,4})[\n\|]+(?:Direct|\d+\s*Stops?)[\n\|]+(?<duration>.+(?:h|m))[\n\|]*(?<cabin>.+)?$/", $airlineInfo, $m)) {
                $s->airline()
                    ->name($m['aName'])
                    ->number($m['fNumber']);

                $s->extra()
                    ->duration($m['duration']);

                if (isset($m['cabin'])) {
                    $s->extra()
                        ->cabin($m['cabin']);
                }
            }

            $depDate = implode(" ", $this->http->FindNodes("./following::table[1]/descendant::tr[1]/descendant::td[2]/descendant::text()[normalize-space()]", $root));
            $depPoints = implode("\n", $this->http->FindNodes("./following::table[1]/descendant::tr[1]/descendant::td[3]/descendant::text()[normalize-space()]", $root));

            if (preg_match("/^(?<name>.+)\s+\((?<code>[A-Z]{3})\)\n.*(?:\n\(?{$this->opt($this->t('Terminal'))}\s*(?<terminal>.+)\))?$/", $depPoints, $m)) {
                $s->departure()
                    ->name($m['name'])
                    ->code($m['code'])
                    ->date($this->normalizeDate($depDate));

                if (isset($m['terminal'])) {
                    $s->setDepTerminal($m['terminal']);
                }
            }

            $arrDate = implode(" ", $this->http->FindNodes("./following::table[1]/descendant::tr[3]/descendant::td[1]/descendant::text()[normalize-space()]", $root));
            $arrPoints = implode("\n", $this->http->FindNodes("./following::table[1]/descendant::tr[3]/descendant::td[2]/descendant::text()[normalize-space()]", $root));

            if (preg_match("/^(?<name>.+)\s+\((?<code>[A-Z]{3})\)\n.*(?:\n\(?{$this->opt($this->t('Terminal'))}\s*(?<terminal>.+)\))?$/", $arrPoints, $m)) {
                $s->arrival()
                    ->name($m['name'])
                    ->code($m['code'])
                    ->date($this->normalizeDate($arrDate));

                if (isset($m['terminal'])) {
                    $s->setArrTerminal($m['terminal']);
                }
            }

            $seats = $this->http->FindNodes("//text()[{$this->eq($this->t('Depart'))} or {$this->eq($this->t('Return'))}]/following::text()[{$this->starts($s->getDepCode())}][1]/following::text()[normalize-space()][1][{$this->eq($s->getArrCode())}]/ancestor::table[3]/descendant::text()[contains(normalize-space(), ' (')][not(contains(normalize-space(), 'x'))]");

            foreach ($seats as $seat) {
                if (preg_match("/^\d+\.\s*(?<pax>.+)\s+\((?<seat>\d+[A-Z])\)\,?$/", $seat, $m)) {
                    $s->extra()
                        ->seat($m['seat'], true, true, $m['pax']);
                }
            }
        }
    }

    public function ParseFlight2(Email $email)
    {
        $this->logger->debug(__METHOD__);
        $f = $email->add()->flight();

        $info = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Airpaz.com - Order'))}]/ancestor::tr[1]");

        if (preg_match("/{$this->opt($this->t('Airpaz.com - Order'))}\s+(?<otaConf>\d+)\s+(?<status>.+)/", $info, $m)) {
            $email->ota()
                ->confirmation($m['otaConf']);

            $f->setStatus($m['status']);
        }

        $confs = array_unique($this->http->FindNodes("//text()[{$this->starts($this->t('Booking Code :'))}]", null, "/\:\s*([A-Z\d]{6,10})\s*$/"));

        foreach ($confs as $conf) {
            $f->general()
                ->confirmation($conf);
        }

        if ($this->http->XPath->query("//text()[{$this->contains($this->t('Please make a payment for your order'))}]")->length > 0
            && count($confs) == 0) {
            $f->general()
                ->noConfirmation();
        }

        $travellers = $this->http->FindNodes("//text()[{$this->eq($this->t('Travelers'))}]/following::table[1]/descendant::text()[normalize-space()][not(contains(normalize-space(), '-') or {$this->contains($this->t('kg'))} or contains(normalize-space(), ':'))]");

        if (count($travellers) > 0) {
            $f->general()
                ->travellers(array_unique(preg_replace("#(?:\({$this->t('Child')}\)|Mrs|Mr|Ms|Sr\.|Sra\.)#", "", $travellers)));
        }

        $price = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Total'))}]/ancestor::tr[1]", null, true, "/^{$this->opt($this->t('Total'))}\s*(\D+[\.\,\'\d]+)$/");

        if (preg_match("/(?<currency>\D+)\s+(?<total>[\.\,\'\d]+)/", $price, $m)) {
            $currency = $this->normalizeCurrency($m['currency']);
            $f->price()
                ->total(PriceHelper::parse($m['total'], $currency))
                ->currency($currency);

            $cost = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Fare Price'))}]/ancestor::tr[1]", null, true, "/{$this->opt($this->t('Fare Price'))}\s*\D+([\d\.\,\']+)/");

            if ($cost !== null) {
                $f->price()
                    ->cost(PriceHelper::parse($cost, $currency));
            }

            $priceNode = $this->http->XPath->query("//text()[{$this->eq($this->t('Fare Price'))}]/ancestor::div[2]/following-sibling::div[not({$this->contains($this->t('Total'))} or {$this->contains($this->t('Discount'))})]/descendant::tr[last()]");

            foreach ($priceNode as $rootPrice) {
                $name = $this->http->FindSingleNode("./descendant::text()[normalize-space()][1]", $rootPrice);
                $summ = $this->http->FindSingleNode("./descendant::text()[normalize-space()][2]", $rootPrice, true, "/\D+([\d\.\,\']+)/");

                if (!empty($name) && $summ !== null) {
                    $f->price()
                        ->fee($name, PriceHelper::parse($summ, $currency));
                }
            }

            $discount = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Discount'))}]/ancestor::tr[1]", null, true, "/{$this->opt($this->t('Discount'))}\s*\-?\s*\D{1,3}\s*([\d\,\.]+)/");

            if (!empty($discount)) {
                $f->price()
                    ->discount(PriceHelper::parse($discount, $currency));
            }
        }

        $nodes = $this->http->XPath->query("//text()[{$this->contains($this->t('Stop'))} or {$this->contains($this->t('Direct'))} or {$this->contains($this->t('Transit'))}]/ancestor::table[normalize-space()][1][not({$this->contains($this->t('Depart'))} or {$this->contains($this->t('Return'))})][not({$this->starts($this->t('Transit'))})]");

        if ($nodes->length == 0) {
            $nodes = $this->http->XPath->query("//img[contains(@src, 'ic-clock')]/ancestor::table[contains(normalize-space(), 'รหัส Airpaz :')][1]/descendant::img[1]/ancestor::tr[1]");
        }

        foreach ($nodes as $root) {
            $s = $f->addSegment();

            $airlineInfo = implode("\n", $this->http->FindNodes("./descendant::text()[normalize-space()]", $root));

            if (preg_match("/^(?<aName>(?:[A-Z][A-Z\d]|[A-Z\d][A-Z]))(?<fNumber>\d{1,4})[\n\|]+.+\n(?:Direct|\d+\s*Stops?|Transit)[\n\|\s]+(?<duration>.+(?:h|m))$/", $airlineInfo, $m)
            || preg_match("/^(?<aName>(?:[A-Z][A-Z\d]|[A-Z\d][A-Z]))(?<fNumber>\d{1,4})$/", $airlineInfo, $m)) {
                $s->airline()
                    ->name($m['aName'])
                    ->number($m['fNumber']);

                if (isset($m['duration'])) {
                    $s->extra()
                        ->duration($m['duration']);
                }
            }

            $flightInfo = implode("\n", $this->http->FindNodes("./following::table[normalize-space()][1]/descendant::text()[normalize-space()]", $root));

            if (preg_match("/^(?<depName>.+)\s+\((?<depCode>[A-Z]{3})\)\n.+(\nTerminal\s*(?<depTerminal>.+))?\n+(?<depTime>\d+[\.\:]\d+)\n(?<depDate>.+\d{4})\n(.*\d+(?:h|m))\n(?<arrName>.+)\s+\((?<arrCode>[A-Z]{3})\)\n.+(\nTerminal\s*(?<arrTerminal>.+))?\n+(?<arrTime>\d+[\.\:]\d+)\n(?<arrDate>.+\d{4})/", $flightInfo, $m)) {
                $s->departure()
                    ->name($m['depName'])
                    ->date($this->normalizeDate($m['depDate'] . ', ' . $m['depTime']))
                    ->code($m['depCode']);

                if (isset($m['depTerminal']) && !empty($m['depTerminal'])) {
                    $s->departure()
                        ->terminal($m['depTerminal']);
                }

                $s->arrival()
                    ->name($m['arrName'])
                    ->date($this->normalizeDate($m['arrDate'] . ', ' . $m['arrTime']))
                    ->code($m['arrCode']);

                if (isset($m['arrTerminal']) && !empty($m['arrTerminal'])) {
                    $s->arrival()
                        ->terminal($m['arrTerminal']);
                }
            } elseif (preg_match("/^(?<depTime>[\d\.]+)\n(?<depDay>\w+\,\s+\d+\s*\w+\s*\d{4})\n(?<depName>.+)\n(?<duration>\d+(?:h|m).+)\n(?<arrTime>[\d\.]+)\n(?<arrDay>\w+\,\s+\d+\s*\w+\s*\d{4})\n(?<arrName>.+)$/", $flightInfo, $m)) {
                $s->departure()
                    ->name($m['depName'])
                    ->date($this->normalizeDate($m['depDay'] . ', ' . $m['depTime']))
                    ->noCode();

                $s->arrival()
                    ->name($m['arrName'])
                    ->date($this->normalizeDate($m['arrDay'] . ', ' . $m['arrTime']))
                    ->noCode();
            }
        }
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
    }

    public function AssignLang()
    {
        foreach ($this->detectLang as $lang => $array) {
            foreach ($array as $word) {
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
            return str_replace(' ', '\s+', preg_quote($s, '/'));
        }, $field)) . ')';
    }

    private function normalizeDate($str)
    {
        $in = [
            //17:55 Wed, 13 November 2024
            "#^([\d\:]+)\s+\w+\,\s*(\d+\s*\w+\s*\d{4})$#",
        ];
        $out = [
            "$2, $1",
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
            'BRL' => ['R$'],
            'PHP' => ['₱'],
            'VND' => ['₫'],
            'THB' => ['฿'],
            'AUD' => ['A$'],
            'EUR' => ['€', 'Euro'],
            'USD' => ['US Dollar', 'US$'],
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
