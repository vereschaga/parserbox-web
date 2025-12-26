<?php

namespace AwardWallet\Engine\localiza\Email;

use AwardWallet\Schema\Parser\Email\Email;

class It6150358 extends \TAccountChecker
{
    public $mailFiles = "localiza/it-104575223.eml, localiza/it-45763361.eml, localiza/it-46058944.eml, localiza/it-76575570.eml";

    public $reSubject = [
        "pt"=> "Informações sobre a Reserva",
        "en"=> "Reservation details",
    ];
    public $reBody = 'Localiza';
    public $reBody2 = [
        "pt"  => "RETIRADA E DEVOLUÇÃO",
        "pt2" => "RETIRADA",
        "en"  => "PICKUP",
    ];

    public static $dictionary = [
        'pt' => [
            'feeNames' => ['Cobertura para terceiros', 'Cadeira de bebê', 'Cobertura do carro'],
        ],
        'en' => [
            'Obrigado,'                                                      => 'Thank you,',
            'Sua reserva foi concluída com sucesso e o Código da Reserva é:' => 'Your reservation has been successfully completed and the code is:',
            'Data e hora de retirada:'                                       => 'Pick up date and time:',
            'Endereço:'                                                      => ['Address:', 'Endereço:'],
            'Telefone:'                                                      => 'Phone Number:',
            'Horário de funcionamento:'                                      => 'Hours:',
            'Data e hora de devolução:'                                      => 'Return date and time:',
            'RETIRADA E DEVOLUÇÃO'                                           => 'PICKUP AND RETURN',
            'DADOS DA RESERVA'                                               => 'RESERVATION DATA',
            'Valor total previsto:'                                          => 'Total estimate:',
            'Diária'                                                         => 'Daily',
            //            'Total' => '',
            'Taxa de aluguel'          => 'Tax',
            'Opcionais'                => 'Options',
            'feeNames'                 => ['Car Coverage', 'Liability Insurance Supplement'],
            'BENEFÍCIOS DESTA RESERVA' => 'BENEFITS OF THIS RESERVATION',
            'Desconto de'              => 'Discount of',

            'RETIRADA'                                              => 'PICKUP',
            'DEVOLUÇÃO'                                             => 'RETURN',
            'A confirmação desta reserva foi enviada para o email:' => 'The confirmation of this booking has be sent to the email:',
            'Saldo de pontos disponíveis'                           => 'Points available:',
        ],
    ];

    public $lang = '';

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@localiza.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (self::detectEmailFromProvider($headers['from']) !== true) {
            return false;
        }

        foreach ($this->reSubject as $re) {
            if (stripos($headers["subject"], $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        if (empty($body)) {
            $body = $parser->getPlainBody();
        }

        if (strpos($body, $this->reBody) === false) {
            return false;
        }

        foreach ($this->reBody2 as $re) {
            if (strpos($body, $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        if (empty($parser->getHTMLBody())) {
            $this->http->SetEmailBody($parser->getPlainBody());
        }

        foreach ($this->reBody2 as $lang=>$re) {
            if (strpos($this->http->Response['body'], $re) !== false) {
                $this->lang = $lang;

                break;
            }
        }

        if ($this->http->XPath->query("//text()[{$this->eq($this->t('RETIRADA E DEVOLUÇÃO'))}]")->length > 0) {
            $this->parseCar($email);
        } elseif ($this->http->XPath->query("//text()[{$this->eq($this->t('RETIRADA'))}]")->length > 0) {
            $this->parseCar2($email);
        }

        $email->setType('ReservationDetails' . ucfirst($this->lang));

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

    private function parseCar(Email $email)
    {
        $this->logger->warning('parseCar');

        $car = $email->add()->rental();

        $driverName = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Obrigado,'))}]/following::text()[normalize-space()][1]", null, true, '/^([[:alpha:]][-.\'[:alpha:] ]*[[:alpha:]])[,.;!?]*$/u');
        $car->general()->traveller($driverName);

        $confirmation = $this->nextText($this->t('Sua reserva foi concluída com sucesso e o Código da Reserva é:'));
        $car->general()->confirmation($confirmation);

        $car->pickup()
            ->date2($this->normalizeDate($this->http->FindSingleNode("//text()[" . $this->eq($this->t('Data e hora de retirada:')) . "]/ancestor::tr[1]/following-sibling::tr[1]/td[normalize-space()][1]")))
            ->location($this->nextText($this->t('Endereço:')))
            ->phone($this->http->FindSingleNode("//text()[" . $this->eq($this->t('Telefone:')) . "]/ancestor::tr[1]/following-sibling::tr[1]/td[normalize-space()][2]"))
            ->openingHours(implode('; ', $this->http->FindNodes("//text()[" . $this->eq($this->t('Horário de funcionamento:')) . "]/ancestor::tr[1]/following-sibling::tr[position()<5]/td[normalize-space()][last()]")));

        $car->dropoff()
            ->date2($this->normalizeDate($this->http->FindSingleNode("//text()[" . $this->eq($this->t('Data e hora de devolução:')) . "]/ancestor::tr[1]/following-sibling::tr[1]/td[normalize-space()][1]")))
            ->same();

        $car->extra()->company($this->nextText($this->t('RETIRADA E DEVOLUÇÃO')));

        $car->car()
            ->type($this->nextText($this->t('DADOS DA RESERVA')))
            ->model($this->nextText($this->t('DADOS DA RESERVA'), null, 2));

        $totalPrice = $this->nextText($this->t('Valor total previsto:'));

        if (preg_match('/^(?<currency>[^\d)(]+) ?(?<amount>\d[,.\'\d]*)$/', $totalPrice, $m)) {
            $car->price()
                ->currency($this->currency($m['currency']))
                ->total($this->amount($m['amount']));
            $m['currency'] = trim($m['currency']);

            $cost = $this->http->FindSingleNode("//tr[ *[1][{$this->eq($this->t('Diária'))}] and *[2][{$this->eq($this->t('Total'))}] ]/following-sibling::tr[normalize-space()][1]/*[2]");

            if (preg_match('/^' . preg_quote($m['currency'], '/') . ' ?(?<amount>\d[,.\'\d]*)$/', $cost, $matches)) {
                $car->price()->cost($this->amount($matches['amount']));
            }

            $tax = $this->http->FindSingleNode("//tr[{$this->eq($this->t('Taxa de aluguel'))}]/following-sibling::tr[normalize-space()][1]/*[2]");

            if (empty($tax)) {
                $tax = $this->http->FindSingleNode("//tr[{$this->eq($this->t('Taxa de aluguel'))}]/following-sibling::tr[normalize-space()][1]");
            }

            if (preg_match('/^' . preg_quote($m['currency'], '/') . ' ?(?<amount>\d[,.\'\d]*)$/', $tax, $matches)) {
                $car->price()->tax($this->amount($matches['amount']));
            }

            $feeRows = $this->http->XPath->query("//tr[{$this->eq($this->t('Opcionais'))}]/following-sibling::tr[{$this->eq($this->t('feeNames'))}]");

            foreach ($feeRows as $feeRow) {
                $feeCharge = $this->http->FindSingleNode('following-sibling::tr[normalize-space()][1]/*[2]', $feeRow);

                if (preg_match('/^' . preg_quote($m['currency'], '/') . ' *(?<amount>\d[,.\'\d]*)/', $feeCharge, $matches)) {
                    $feeName = $this->http->FindSingleNode('.', $feeRow);
                    $car->price()->fee($feeName, $this->amount($matches['amount']));
                }
            }

            $discount = $this->http->FindSingleNode("//text()[{$this->eq($this->t('BENEFÍCIOS DESTA RESERVA'))}]/following::text()[{$this->eq($this->t('Desconto de'))}]/following::text()[normalize-space()][1]");

            if (preg_match('/^' . preg_quote($m['currency'], '/') . ' ?(?<amount>\d[,.\'\d]*)$/', $discount, $matches)) {
                $car->price()->discount($this->amount($matches['amount']));
            }
        }
    }

    private function parseCar2(Email $email)
    {
        $this->logger->warning('parseCar2');

        $car = $email->add()->rental();

        $driverName = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Obrigado,'))}]/following::text()[normalize-space()][1]", null, true, '/^([[:alpha:]][-.\'[:alpha:] ]*[[:alpha:]])[,.;!?]*$/u');
        $car->general()->traveller($driverName);

        $confirmation = $this->nextText($this->t('Sua reserva foi concluída com sucesso e o Código da Reserva é:'));
        $car->general()->confirmation($confirmation);

        $car->pickup()
            ->date2($this->normalizeDate($this->http->FindSingleNode("//text()[" . $this->eq($this->t('Data e hora de retirada:')) . "]/ancestor::tr[1]/following-sibling::tr[1]/td[normalize-space()][1]")))
            ->location($this->http->FindSingleNode("//text()[{$this->eq($this->t('RETIRADA'))}]/following::text()[{$this->eq($this->t('Endereço:'))}][1]/following::text()[normalize-space()][1]"))
            ->phone($this->http->FindSingleNode("//text()[{$this->eq($this->t('RETIRADA'))}]/following::text()[{$this->eq($this->t('Telefone:'))}][1]/following::text()[normalize-space()][2]", null, true, "/([\d]+)$/"))
            ->openingHours(implode('; ', $this->http->FindNodes("//text()[{$this->eq($this->t('RETIRADA'))}]/following::text()[" . $this->eq($this->t('Horário de funcionamento:')) . "][1]/ancestor::tr[1]/following-sibling::tr[position()<5]/td[normalize-space()][last()]")));

        $car->dropoff()
            ->date2($this->normalizeDate($this->http->FindSingleNode("//text()[" . $this->eq($this->t('Data e hora de devolução:')) . "]/ancestor::tr[1]/following-sibling::tr[1]/td[normalize-space()][1]")))
            ->location($this->http->FindSingleNode("//text()[{$this->eq($this->t('DEVOLUÇÃO'))}]/following::text()[{$this->eq($this->t('Endereço:'))}][1]/following::text()[normalize-space()][1]"))
            ->phone($this->http->FindSingleNode("//text()[{$this->eq($this->t('DEVOLUÇÃO'))}]/following::text()[{$this->eq($this->t('Telefone:'))}][1]/following::text()[normalize-space()][2]", null, true, "/([\d]+)$/"))
            ->openingHours(implode('; ', $this->http->FindNodes("//text()[{$this->eq($this->t('DEVOLUÇÃO'))}]/following::text()[" . $this->eq($this->t('Horário de funcionamento:')) . "][1]/ancestor::tr[1]/following-sibling::tr[position()<5]/td[normalize-space()][last()]")));

        $car->extra()->company($this->nextText($this->t('RETIRADA')));

        $car->car()
            ->type($this->nextText($this->t('DADOS DA RESERVA')))
            ->model($this->nextText($this->t('DADOS DA RESERVA'), null, 2));

        $totalPrice = $this->nextText($this->t('Valor total previsto:'));

        if (preg_match('/^(?<currency>[^\d)(]+) ?(?<amount>\d[,.\'\d]*)$/', $totalPrice, $m)) {
            $car->price()
                ->currency($this->currency($m['currency']))
                ->total($this->amount($m['amount']));
            $m['currency'] = trim($m['currency']);

            $cost = $this->http->FindSingleNode("//tr[ *[1][{$this->eq($this->t('Diária'))}] and *[2][{$this->eq($this->t('Total'))}] ]/following-sibling::tr[normalize-space()][1]/*[2]");

            if (preg_match('/^' . preg_quote($m['currency'], '/') . ' ?(?<amount>\d[,.\'\d]*)$/', $cost, $matches)) {
                $car->price()->cost($this->amount($matches['amount']));
            }

            $tax = $this->http->FindSingleNode("//tr[{$this->eq($this->t('Taxa de aluguel'))}]/following-sibling::tr[normalize-space()][1]/*[2]");

            if (empty($tax)) {
                $tax = $this->http->FindSingleNode("//tr[{$this->eq($this->t('Taxa de aluguel'))}]/following-sibling::tr[normalize-space()][1]");
            }

            if (preg_match('/^' . preg_quote($m['currency'], '/') . ' ?(?<amount>\d[,.\'\d]*)$/', $tax, $matches)) {
                $car->price()->tax($this->amount($matches['amount']));
            }

            if ($returnCar = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Retorno do Carro Alugado entre Agências'))}]/following::text()[normalize-space()][1]", null, true, "/([\d\,]+)$/")) {
                $car->price()->fee('Retorno do Carro Alugado entre Agências', $this->amount($returnCar));
            }

            $feeRows = $this->http->XPath->query("//tr[{$this->eq($this->t('Opcionais'))}]/following-sibling::tr[{$this->eq($this->t('feeNames'))}]");

            foreach ($feeRows as $feeRow) {
                $feeCharge = $this->http->FindSingleNode('following-sibling::tr[normalize-space()][1]/*[2]', $feeRow);

                if (preg_match('/^' . preg_quote($m['currency'], '/') . ' *(?<amount>\d[,.\'\d]*)/', $feeCharge, $matches)) {
                    $feeName = $this->http->FindSingleNode('.', $feeRow);
                    $car->price()->fee($feeName, $this->amount($matches['amount']));
                }
            }

            $discount = $this->http->FindSingleNode("//text()[{$this->eq($this->t('BENEFÍCIOS DESTA RESERVA'))}]/following::text()[{$this->eq($this->t('Desconto de'))}]/following::text()[normalize-space()][1]");

            if (preg_match('/^' . preg_quote($m['currency'], '/') . ' ?(?<amount>\d[,.\'\d]*)$/', $discount, $matches)) {
                $car->price()->discount($this->amount($matches['amount']));
            }

            $earnedPoint = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Você poderá conquistar nesta reserva até'))}]/following::b[1]", null, true, "/^(\d+)\s/");

            if (!empty($earnedPoint)) {
                $car->setEarnedAwards($earnedPoint);
            }

            $login = $this->http->FindSingleNode("//text()[{$this->eq($this->t('A confirmação desta reserva foi enviada para o email:'))}]/following::b[1]");
            $balance = str_replace('.', '', $this->http->FindSingleNode("//text()[{$this->eq($this->t('Saldo de pontos disponíveis'))}]/following::b[1]", null, true, "/^([\d\.]+)\s/"));

            if (!empty($login) && $balance !== null) {
                $st = $email->add()->statement();
                $st->setLogin($login);

                if ($balance != null) {
                    $st->setBalance($balance);
                } else {
                    $st->setNoBalance(true);
                }
            }
        }

        return true;
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
            "#^(\d+)/(\d+)/(\d{4})\s+(\d+:\d+:\d+)$#", //22/03/2017 19:00:00
        ];
        $out = [
            "$1.$2.$3, $4",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = \AwardWallet\Engine\MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return $str;
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function amount($s)
    {
        return (float) str_replace(",", ".", preg_replace("#[.,](\d{3})#", "$1", $s));
    }

    private function currency($s)
    {
        $sym = [
            'R$'=> 'BRL',
        ];

        if ($code = $this->re("#(?:^|\s)([A-Z]{3})(?:$|\s)#", $s)) {
            return $code;
        }

        foreach ($sym as $f=>$r) {
            if (strpos($s, $f) !== false) {
                return $r;
            }
        }

        return null;
    }

    private function nextText($field, $root = null, $n = 1)
    {
        $rule = $this->eq($field);

        return $this->http->FindSingleNode("(.//text()[{$rule}])[1]/following::text()[string-length(normalize-space())>1][{$n}]", $root);
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) { return "normalize-space(.)=\"{$s}\""; }, $field));
    }
}
