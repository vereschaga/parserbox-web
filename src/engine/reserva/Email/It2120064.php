<?php

namespace AwardWallet\Engine\reserva\Email;

use AwardWallet\Schema\Parser\Email\Email;

class It2120064 extends \TAccountChecker
{
    public $mailFiles = "reserva/it-1809199.eml, reserva/it-1932450.eml, reserva/it-2118556.eml, reserva/it-2118559.eml, reserva/it-2118561.eml, reserva/it-2118636.eml, reserva/it-2118637.eml, reserva/it-2118638.eml, reserva/it-2119349.eml, reserva/it-2119353.eml, reserva/it-2119606.eml, reserva/it-2119824.eml, reserva/it-2120064.eml, reserva/it-2120085.eml, reserva/it-2123389.eml, reserva/it-2123488.eml, reserva/it-2198162.eml, reserva/it-2413494.eml, reserva/it-2413499.eml, reserva/it-2413500.eml, reserva/it-2712771.eml, reserva/it-2712773.eml, reserva/it-2712774.eml, reserva/it-2713247.eml, reserva/it-2713351.eml, reserva/it-2713352.eml, reserva/it-2713353.eml, reserva/it-2713355.eml, reserva/it-2793776.eml, reserva/it-2846713.eml, reserva/it-3495700.eml, reserva/it-62284846.eml, reserva/it-72071984.eml";

    public static $dictionary = [
        "pt" => [
            "Passenger name"      => ["Nome do Passageiro"],
            "Confirmation number" => ["LOC (Localizador da reserva)"],
            "Ticket number"       => "Número do bilhete",
            "Cost"                => "Valor Tarifas",
            "Taxe"                => "Taxa de embarque",
            "Total"               => ["Total", "Valor Total"],
            "Date reservation"    => "Data de emissão",
            "Flight"              => "Voo",
            "Depart"              => "Origem",
            "Arrive"              => "Destino",
            "Date"                => "Data",
            "Time"                => "Saída",
            "Seats"               => "Assento",
            "Code"                => "Classe",
            "segConf"             => "LOC Cia",
        ],
    ];

    public $lang = "pt";
    private static $providers = [
        'aviancataca' => [
            'from' => ['aviancataca'],
            'body' => [
                "pt"  => 'Avianca emitido para imprimir',
                "pt2" => 'app.reservafacil.tur.br/etkt/imagens/cias/hdr/O6.png',
            ],
            'subject' => [
                "pt" => 'Bilhete eletr. Avianca - Emitido',
            ],
        ],

        'azul' => [
            'from' => ['@reservafacil.tur.br'],
            'body' => [
                "pt"  => 'SAC AZUL BRASIL',
                "pt2" => 'app.reservafacil.tur.br/etkt/imagens/cias/hdr/AD.png',
            ],
            'subject' => [
                "pt" => ' - Emitido - ',
            ],
        ],

        'tamair' => [
            'from' => ['@reservafacil.tur.br'],
            'body' => [
                "pt"  => 'SAC TAM BRASIL',
                "pt2" => 'app.reservafacil.tur.br/etkt/imagens/cias/hdr/JJ.png',
                "pt3" => 'LATAM AIRLINES BRASIL',
                "pt4" => 'SAC LAN BRASIL',
                "pt5" => 'imagensrf-prod.s3.amazonaws.com/cias/hdr/TK.png',
            ],
            'subject' => [
                "pt" => ' - Emitido - ',
            ],
        ],

        'golair' => [
            'from' => ['@reservafacil.tur.br'],
            'body' => [
                "pt"  => 'SAC GOL BRASIL',
                "pt2" => 'app.reservafacil.tur.br/etkt/imagens/cias/hdr/G3.png',
            ],
            'subject' => [
                "pt" => ' - Emitido - ',
            ],
        ],

        'tapportugal' => [
            'from' => ['@reservafacil.tur.br'],
            'body' => [
                "pt3" => 'app.reservafacil.tur.br/etkt/imagens/cias/hdr/TP.png',
            ],
            'subject' => [
                "pt" => ' - Emitido - ',
            ],
        ],

        'swissair' => [
            'from' => [],
            'body' => [
                "pt" => 'SAC SWISS BRASIL',
                "pt2" => '/cias/hdr/LX.png',
            ],
            'subject' => [
//                "pt" => '',
            ],
        ],
        'turkish' => [
            'from' => [],
            'body' => [
                "pt" => '/cias/hdr/TK.png',
            ],
            'subject' => [
                "pt" => 'E-Ticket de',
            ],
        ],
        'iberia' => [
            'from' => [],
            'body' => [
                "pt" => '/cias/hdr/IB.png',
            ],
            'subject' => [
//                "pt" => '',
            ],
        ],
    ];

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->http->FilterHTML = true;
        $this->assignLang();
        $flight = $email->add()->flight();

        if (!empty($this->getProvider())) {
            $flight->setProviderCode($this->getProvider());
        }

        $travellers = $this->http->FindNodes("//text()[{$this->starts($this->t('Passenger name'))}]/ancestor::tr[1]/descendant::td[2]");
        $dateReservation = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Date reservation'))}]/ancestor::tr[1]/descendant::td[2]");

        if (empty($dateReservation)) {
            $dateReservation = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Total'))}]/following::text()[{$this->starts($this->t('Date reservation'))}][1]/ancestor::tr[1]/descendant::td[2]");
        }

        $confirmation = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Date reservation'))}]/ancestor::tr[1]/following::tr[{$this->starts($this->t('Confirmation number'))}][1]/descendant::td[normalize-space()][2]");

        if (empty($confirmation)) {
            $confirmation = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Total'))}]/following::text()[{$this->starts($this->t('Date reservation'))}][1]/ancestor::tr[1]/following::tr[{$this->starts($this->t('Confirmation number'))}][1]/descendant::td[normalize-space()][2]");
        }

        $descriptionConf = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Date reservation'))}]/ancestor::tr[1]/following::tr[{$this->starts($this->t('Confirmation number'))}][1]", null, true, '/[(](.+)[)]/');

        if (empty($descriptionConf)) {
            $descriptionConf = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Total'))}]/following::text()[{$this->starts($this->t('Date reservation'))}][1]/ancestor::tr[1]/following::tr[{$this->starts($this->t('Confirmation number'))}][1]", null, true, '/[(](.+)[)]/');
        }

        $flight->general()
            ->travellers(array_unique($travellers), true)
            ->confirmation($confirmation, $descriptionConf)
            ->date(strtotime(str_replace(['/', ' - '], ['.', ', '], $dateReservation)));

        $ticketNumbers = $this->http->FindNodes("//text()[{$this->starts($this->t('Ticket number'))}]/ancestor::tr[1]/descendant::td[2]");

        $flight->issued()
            ->tickets(array_unique($ticketNumbers), false);

        $xpathSegments = "//tr[{$this->starts($this->t('Flight'))} and {$this->contains($this->t('Depart'))} and {$this->contains($this->t('Arrive'))}]/ancestor::table[1]/descendant::tr[not({$this->contains($this->t('Arrive'))})]";
        $segments = $this->http->XPath->query($xpathSegments);

        foreach ($segments as $i => $root) {
            $allColumns = $this->http->FindNodes("./ancestor::table[1]/descendant::tr[1]/descendant::td", $root);

            $flightColumn = 0;
            $departColumn = 0;
            $arriveColumn = 0;
            $dateColumn = 0;
            $timeColumn = 0;
            $seatsColumn = 0;
            $codeColumn = 0;
            $segConfColumn = 0;

            $numberColumn = 1;

            foreach ($allColumns as $column) {
                if (!empty($this->re("/({$this->opt($this->t('Flight'))})/", $column))) {
                    $flightColumn = $numberColumn;
                }

                if (!empty($this->re("/({$this->opt($this->t('Depart'))})/", $column))) {
                    $departColumn = $numberColumn;
                }

                if (!empty($this->re("/({$this->opt($this->t('Arrive'))})/", $column))) {
                    $arriveColumn = $numberColumn;
                }

                if (!empty($this->re("/({$this->opt($this->t('Date'))})/", $column))) {
                    $dateColumn = $numberColumn;
                }

                if (!empty($this->re("/({$this->opt($this->t('Time'))})/", $column))) {
                    $timeColumn = $numberColumn;
                }

                if (!empty($this->re("/({$this->opt($this->t('Seats'))})/", $column))) {
                    $seatsColumn = $numberColumn;
                }

                if (!empty($this->re("/({$this->opt($this->t('Code'))})/", $column))) {
                    $codeColumn = $numberColumn;
                }
                if (!empty($this->re("/({$this->opt($this->t('segConf'))})/", $column))) {
                    $segConfColumn = $numberColumn;
                }
                $numberColumn++;
            }

            $segment = $flight->addSegment();

            $segment->airline()
                ->name($this->http->FindSingleNode("./td[{$flightColumn}]", $root, true, '/([A-Z\d]{2})\s+/'))
                ->number($this->http->FindSingleNode("./td[{$flightColumn}]", $root, true, '/[A-Z\d]{2}\s+(\d{2,4})/'));

            $conf = $arrTime = $this->http->FindSingleNode("./td[{$segConfColumn}]", $root, true, '/^\s*([A-Z\d]{5,7})\s*$/');
            if (!empty($conf)) {
                $segment->airline()
                    ->confirmation($conf);
            }
            $depTime = $this->http->FindSingleNode("./td[{$timeColumn}]", $root, true, '/([\d\:]+)\//');
            $depDateOnly = str_replace('/', '.', $this->http->FindSingleNode("./td[{$dateColumn}]", $root));
            $depDate = strtotime($depDateOnly . ', ' . $depTime);

            if (empty($depDate)) {
                $depTdCount = $this->http->XPath->query("//td[normalize-space()='Saída']/preceding-sibling::td")->count() + 1;
                $depDate = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Saída')]/ancestor::tr[1]/following-sibling::tr[$i+1]/td[$depTdCount]", $root, true, "/^(\d+\/\d+\/\d{4}\s+[\d\:]+)$/u");

                if (empty($depDate)) {
                    $depDate = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Saída')]/ancestor::tr[1]/following-sibling::tr[$i+1]/td[6]", $root, true, "/^(\d+\/\d+\/\d{4}\s+[\d\:]+)$/u");
                }
                $depDate = strtotime(str_replace(['/', ' - '], ['.', ', '], $depDate));
            }

            $arrTime = $this->http->FindSingleNode("./td[{$timeColumn}]", $root, true, '/\/([\d\:]+)/');
            $arrDateOnly = str_replace('/', '.', $this->http->FindSingleNode("./td[{$dateColumn}]", $root));
            $arrDate = strtotime($arrDateOnly . ', ' . $arrTime);

            if (empty($arrDate)) {
                $arrTdCount = $this->http->XPath->query("//td[normalize-space()='Chegada']/preceding-sibling::td")->count() + 1;
                $arrDate = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Chegada')]/ancestor::tr[1]/following-sibling::tr[$i+1]/td[$arrTdCount]", null, true, "/^(\d+\/\d+\/\d{4}\s+[\d\:]+)$/");
                $arrDate = strtotime(str_replace(['/', ' - '], ['.', ', '], $arrDate));
            }
            $segment->departure()
                ->code($this->http->FindSingleNode("./td[{$departColumn}]", $root, true, '/([A-Z]{3})\s+[-]/'))
                ->date($depDate);

            $segment->arrival()
                ->code($this->http->FindSingleNode("./td[{$arriveColumn}]", $root, true, '/([A-Z]{3})\s+[-]/'))
                ->date($arrDate);

            $bookingCode = $this->http->FindSingleNode("./td[{$codeColumn}]", $root);

            if (!empty($bookingCode)) {
                $segment->extra()
                    ->bookingCode($bookingCode);
            }

            $seat = $this->http->FindSingleNode("./td[{$seatsColumn}]", $root);

            if (!empty($seat)) {
                $segment->extra()
                    ->seat($seat);
            }
        }

        $total = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Total'))}]/ancestor::tr[1]/descendant::td[2]", null, true, '/\D{2,3}\s+([\d\,\.]+)/');
        $currency = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Total'))}]/ancestor::tr[1]/descendant::td[2]", null, true, '/(\D{2,3})\s+/');

        if (empty($total)) {
            $total = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Total'))}][1]/following::text()[{$this->starts($this->t('Total'))}][1]/ancestor::tr[1]/descendant::td[normalize-space()][2]", null, true, '/\D{2,3}\s+([\d\,\.]+)/');
            $currency = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Total'))}][1]/following::text()[{$this->starts($this->t('Total'))}][1]/ancestor::tr[1]/descendant::td[normalize-space()][2]", null, true, '/(\D{2,3})\s+/');
        }

        $cost = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Cost'))}]/ancestor::tr[1]/descendant::td[2]", null, true, '/\D{2,3}\s+([\d\,\.]+)/');
        $tax = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Taxe'))}]/ancestor::tr[1]/descendant::td[2]", null, true, '/\D{2,3}\s+([\d\,\.]+)/');

        if (!empty($total)) {
            $flight->price()
                ->total($this->normalizePrice($total))
                ->currency($this->normalizeCurrency($currency));
        }

        if (!empty($cost)) {
            $flight->price()
                ->cost($this->normalizePrice($cost))
                ->tax($this->normalizePrice($tax));
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailFromProvider($from)
    {
        foreach (self::$providers as $key => $option) {
            foreach ($option['from'] as $reFrom) {
                return strpos($from, $reFrom) !== false;
            }
        }
    }

    public function detectEmailByHeaders(array $headers)
    {
        foreach (self::$providers as $prov => $option) {
            foreach ($option['subject'] as $lang=>$subject) {
                if (strpos($headers["subject"], $subject) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        foreach (self::$providers as $prov => $option) {
            foreach ($option['body'] as $lang=>$body) {
                if (strpos($this->http->Response["body"], $body) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public static function getEmailProviders()
    {
        return array_keys(self::$providers);
    }

    protected function normalizePrice($string = '')
    {
        if (empty($string)) {
            return $string;
        }
        $string = preg_replace('/\s+/', '', $string);			// 11 507.00	->	11507.00
        $string = preg_replace('/[,.](\d{3})/', '$1', $string);	// 2,790		->	2790		or	4.100,00	->	4100,00
        $string = preg_replace('/,(\d{2})$/', '.$1', $string);	// 18800,00		->	18800.00

        return $string;
    }

    private function assignLang()
    {
        foreach (self::$providers as $key => $option) {
            foreach ($option['body'] as $lang=>$re) {
                if (strpos($this->http->Response["body"], $re) !== false) {
                    $this->lang = substr($lang, 0, 2);
                    //$this->logger->notice($this->lang);
                    break;
                }
            }
        }
    }

    private function getProvider()
    {
        foreach (self::$providers as $prov => $option) {
            foreach ($option['body'] as $lang=>$body) {
                if (strpos($this->http->Response["body"], $body) !== false) {
                    return $prov;
                }
            }
        }
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) { return "starts-with(normalize-space(.), \"{$s}\")"; }, $field));
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) { return "contains(normalize-space(.), \"{$s}\")"; }, $field));
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function normalizeCurrency(string $string)
    {
        // $this->logger->notice('Currency '.$string);

        $string = trim($string);
        $currencies = [
            'GBP' => ['£'],
            'EUR' => ['€'],
            'USD' => ['$'],
            'BRL' => ['R$'],
        ];

        foreach ($currencies as $code => $currencyFormats) {
            foreach ($currencyFormats as $currency) {
                if ($string === $currency) {
                    return $code;
                }
            }
        }

        return null;
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", $field) . ')';
    }
}
