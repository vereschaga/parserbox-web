<?php

namespace AwardWallet\Engine\localiza\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class RentalCar extends \TAccountChecker
{
    public $mailFiles = "localiza/it-104535774.eml, localiza/it-108373934.eml, localiza/it-125477821.eml, localiza/it-132322567.eml, localiza/it-136284264.eml";
    public $subjects = [
        // pt, es
        '/\, sua reserva [A-Z\d]+ está confirmada./',
    ];

    public $lang = 'pt';

    public static $dictionary = [
        "pt" => [
            'PRONTO PARA INICIAR A SUA JORNADA?' => ['PRONTO PARA INICIAR A SUA JORNADA?', 'Sua reserva foi efetuada com sucesso', 'A reserva indicada pelo localizador', 'É um prazer seguir com você neste caminho', 'você inicia sua viagem sem enfrentar filas'],
            'CONFIRA OS DADOS DA SUA RESERVA'    => ['CONFIRA OS DADOS DA SUA RESERVA', 'Confira abaixo informações importantes para a sua experiência com a Localiza', 'Tudo pronto para uma experiência completa', 'Confira os detalhes da sua reserva:', 'Informações detalhadas'],

            'thousands'                               => '.',
            'decimals'                                => ',',
            'O CÓDIGO DA SUA RESERVA É:'              => ['O CÓDIGO DA SUA RESERVA É:', 'O código da sua reserva é:', 'Sua reserva foi alterada com sucesso, o código é:', 'Localizador'],
            'Retirada do veículo:'                    => ['Retirada do veículo:', 'Retirada do veículo'],
            'Devolução do carro:'                     => ['Devolução do carro:', 'Devolução do veículo', 'Devolução do veículo:'],
            'Data:'                                   => ['Data:', 'Dia'],
            'Horário:'                                => ['Horário:', 'Horário', 'Hora:'],
            'Valor total previsto:'                   => ['Valor total previsto:', 'Valor total previsto', 'Valor total:'],
            'DIÁRIAS:'                                => ['DIÁRIAS:', 'Diárias', 'Diárias:'],
            'SERVIÇOS:'                               => ['SERVIÇOS:', 'Serviços', 'Serviços:'],
            'ADICIONAIS:'                             => ['ADICIONAIS:', 'Adicionais', 'Adicionais:'],
            'Horário de funcionamento:'               => ['Horário de funcionamento:', 'Funcionamento:'],
            'Após retirar o carro, você ganhará mais' => ['Após retirar o carro, você ganhará mais', 'Com essa reserva você pode ganhar'],
        ],
        "es" => [
            'PRONTO PARA INICIAR A SUA JORNADA?' => ['¿LISTO PARA EMPEZAR TU VIAJE?'],
            'CONFIRA OS DADOS DA SUA RESERVA'    => ['CONSULTA LOS DETALLES DE TU RESERVA'],

            'Olá,'                               => '¡Hola,',
            //            'Condutor'                               => '',
            'Veículo similar a:'                               => 'Vehículo similar a:',
            'O CÓDIGO DA SUA RESERVA É:'                       => ['SU CÓDIGO DE RESERVA ES:'],
            'Retirada do veículo:'                             => ['Recogida del vehículo:'],
            'Devolução do carro:'                              => ['Devolución de coche:'],
            'Data:'                                            => ['Data:'],
            'Horário:'                                         => ['Hora:'],
            'Valor total previsto:'                            => ['Valor total estimado:'],
            'DIÁRIAS:'                                         => ['TARIFAS DIARIAS:'],
            'SERVIÇOS:'                                        => ['SERVICIOS:'],
            'ADICIONAIS:'                                      => ['ADICIONAL:'],
            'Horário de funcionamento:'                        => ['Horario:'],
            'Após retirar o carro, você ganhará mais'          => ['Después de recoger el coche, ganarás'],
            'Agora você tem'                                   => ['ahora lo tienes'],
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@e.localiza.com') !== false) {
            foreach ($this->subjects as $subject) {
                if (preg_match($subject, $headers['subject'])) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//a[contains(@href, 'localiza.com') or contains(normalize-space(), 'localiza.com')]")->length === 0) {
            return false;
        }

        foreach (self::$dictionary as $lang => $dict) {
            if (!empty($dict['CONFIRA OS DADOS DA SUA RESERVA']) && $this->http->XPath->query("//text()[{$this->contains($dict['CONFIRA OS DADOS DA SUA RESERVA'])}]")->length > 0
                && !empty($dict['PRONTO PARA INICIAR A SUA JORNADA?']) && $this->http->XPath->query("//text()[{$this->contains($dict['PRONTO PARA INICIAR A SUA JORNADA?'])}]")->length > 0
            ) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@e.localiza.com') !== false;
    }

    public function ParseRental(Email $email)
    {
        $r = $email->add()->rental();

        // General
        $r->general()
            ->confirmation($this->http->FindSingleNode("//text()[{$this->eq($this->t('O CÓDIGO DA SUA RESERVA É:'))}]/following::text()[normalize-space()][1]", null, true, "/([A-Z\d]{8,})/"),
                trim($this->http->FindSingleNode("(//text()[{$this->eq($this->t('O CÓDIGO DA SUA RESERVA É:'))}])[1]"), ': '));

        $traveller = $this->http->FindSingleNode("//text()[" . $this->starts($this->t('Olá,')) . "]", null, true,
            "/{$this->opt($this->t('Olá,'))}\s*(\D+)(?:\!|\,|\.)/u");

        if (empty($traveller)) {
            $traveller = $this->http->FindSingleNode("//text()[" . $this->eq($this->t("Condutor")) . "]/following::text()[normalize-space()][1]", null, true, "/^(\D+)\s\-/u");
        }

        if (!empty($traveller)) {
            $r->general()
                ->traveller($traveller, true);
        }

        $carModel = $this->http->FindSingleNode("//text()[" . $this->starts($this->t('Veículo similar a')) . "]", null, true,
            "/{$this->opt($this->t('Veículo similar a'))}\:?\s*(.+)/");

        if (!empty($carModel)) {
            $r->car()
                ->model($carModel);
        }

        $carType = $this->http->FindSingleNode("//text()[" . $this->starts($this->t('Veículo similar a')) . "]/preceding::text()[normalize-space()][1]");

        if (!empty($carType)) {
            $r->car()
                ->type($carType);
        }

        $image = $this->http->FindSingleNode("//text()[" . $this->starts($this->t('Veículo similar a')) . "]/ancestor::table[1]/descendant::img[1]/@src");

        if (!empty($image)) {
            $r->car()
                ->image($image);
        }

        // Pick up
        $dateInText = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Retirada do veículo:'))}]/following::text()[{$this->eq($this->t('Data:'))}][1]/ancestor::tr[position() < 4][{$this->contains($this->t('Horário:'))}][1]");

        if (preg_match("/{$this->opt($this->t('Data:'))}\s*(\d+\,\s*\w+\s*\d{4})\s*{$this->opt($this->t('Horário:'))}\s+([\dh\:]+)/su", $dateInText, $m)) {
            $locationText = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Retirada do veículo:'))}]/following::text()[{$this->starts($this->t('Horário de funcionamento:'))}][1]/preceding::text()[normalize-space()][1]/ancestor::td[1]");

            if (stripos($locationText, 'Endereço:') !== false) {
                $location = $this->re("/{$this->opt($this->t('Endereço:'))}\s*(.+){$this->opt($this->t('Horário de funcionamento:'))}/", $locationText);
            } else {
                $location = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Retirada do veículo:'))}]/following::text()[{$this->starts($this->t('Horário de funcionamento:'))}][1]/preceding::text()[normalize-space()][1]");

                if (preg_match("/^\s*\,/u", $location)) {
                    $location = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Retirada do veículo:'))}]/following::text()[{$this->starts($this->t('Horário de funcionamento:'))}][1]/preceding::text()[normalize-space()][1]/ancestor::*[1]", null, true, "/(.+){$this->opt($this->t('Horário de funcionamento:'))}/");
                }
            }

            $hours = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Retirada do veículo:'))}]/following::text()[{$this->starts($this->t('Horário de funcionamento:'))}][1]", null, true, "/{$this->opt($this->t('Horário de funcionamento:'))}\s*(.+)/");

            if (empty($hours)) {
                $hours = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Retirada do veículo:'))}]/following::text()[{$this->starts($this->t('Horário de funcionamento:'))}][1]/ancestor::tr[1]", null, true, "/{$this->opt($this->t('Horário de funcionamento:'))}\s*(.+)/");
            }

            $r->pickup()
                ->date($this->normalizeDate($m[1] . ' ' . $m[2]))
                ->openingHours($hours)
                ->location(str_replace('Endereço: ', '', $location));
        }

        // Drop off
        $dateOutText = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Devolução do carro:'))}]/following::text()[{$this->eq($this->t('Data:'))}][1]/ancestor::tr[position() < 4][{$this->contains($this->t('Horário:'))}][1]");

        if (preg_match("/{$this->opt($this->t('Data:'))}\s*(\d+\,\s*\w+\s*\d{4})\s*{$this->opt($this->t('Horário:'))}\s+([\dh\:]+)/s", $dateOutText, $m)) {
            $locationText = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Devolução do carro:'))}]/following::text()[{$this->starts($this->t('Horário de funcionamento:'))}][1]/preceding::text()[normalize-space()][1]/ancestor::td[1]");

            if (stripos($locationText, 'Endereço:') !== false) {
                $location = $this->re("/{$this->opt($this->t('Endereço:'))}\s*(.+){$this->opt($this->t('Horário de funcionamento:'))}/", $locationText);
            } else {
                $location = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Devolução do carro:'))}]/following::text()[{$this->starts($this->t('Horário de funcionamento:'))}][1]/preceding::text()[normalize-space()][1]");

                if (preg_match("/^\s*\,/u", $location)) {
                    $location = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Devolução do carro:'))}]/following::text()[{$this->starts($this->t('Horário de funcionamento:'))}][1]/preceding::text()[normalize-space()][1]/ancestor::*[1]", null, true, "/(.+){$this->opt($this->t('Horário de funcionamento:'))}/");
                }
            }

            $hours = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Devolução do carro:'))}]/following::text()[{$this->starts($this->t('Horário de funcionamento:'))}][1]", null, true, "/{$this->opt($this->t('Horário de funcionamento:'))}\s*(.+)/");

            if (empty($hours)) {
                $hours = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Devolução do carro:'))}]/following::text()[{$this->starts($this->t('Horário de funcionamento:'))}][1]/ancestor::tr[1]", null, true, "/{$this->opt($this->t('Horário de funcionamento:'))}\s*(.+)/");
            }

            $r->dropoff()
                ->date($this->normalizeDate($m[1] . ' ' . $m[2]))
                ->openingHours($hours)
                ->location(str_replace('Endereço: ', '', $location));
        }

        $total = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Valor total previsto:'))}]/ancestor::td[1]", null, true, "/{$this->opt($this->t('Valor total previsto:'))}\s*\D+([\d\.\,]+)/");
        $currency = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Valor total previsto:'))}]/ancestor::td[1]", null, true, "/{$this->opt($this->t('Valor total previsto:'))}\s*(\D+)\s+[\d\.\,]+/");

        if (!empty($total) && !empty($currency)) {
            $currency = $this->normalizeCurrency($currency);
            $r->price()
                ->total(PriceHelper::parse($total, $currency))
                ->currency($currency);

            $cost = $this->http->FindSingleNode("//text()[{$this->eq($this->t('DIÁRIAS:'))}]/ancestor::td[1]/following-sibling::td[normalize-space()][1]", null, true, "/^\D*\s(\d[ \d\.\,]*)\D*$/");

            if (empty($cost)) {
                $cost = $this->http->FindSingleNode("//text()[{$this->eq($this->t('DIÁRIAS:'))}]/ancestor::tr[1]/following::tr[1]/descendant::td[2]", null, true, "/\s(\d[\d\.\, ]*)/");
            }

            if (!empty($cost)) {
                $r->price()
                    ->cost(PriceHelper::parse($cost, $currency));
            }

            $nodes = $this->http->XPath->query("(//text()[{$this->eq($this->t('ADICIONAIS:'))} or {$this->eq($this->t('SERVIÇOS:'))}])[1]/ancestor::tr[1]/following-sibling::tr[following::*[{$this->contains($this->t('Valor total previsto:'))}]]");

            if ($nodes->length > 0) {
                foreach ($nodes as $root) {
                    $feeName = $this->http->FindSingleNode(".//td[not(.//td)][normalize-space()][1]", $root, true, "/^(.+)\:/u");
                    $feeSum = $this->http->FindSingleNode(".//td[not(.//td)][normalize-space()][2]", $root, true, "/\D+\s+([\d\.\,]+)/");

                    if (!empty($feeName) && !empty($feeSum)) {
                        $r->price()
                            ->fee($feeName, PriceHelper::parse($feeSum, $currency));
                    }
                }
            }
        }

        $earned = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Após retirar o carro, você ganhará mais'))}]/ancestor::tr[1]", null, true,
            "/{$this->opt($this->t('Após retirar o carro, você ganhará mais'))}\s*([\d\.\,]+)/");

        if (!empty($earned)) {
            $r->setEarnedAwards(str_replace('.', '', $earned));
        }

        $balance = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Agora você tem'))}]/following::text()[normalize-space()][1]", null, true, "/([\d\.\,]+)/");

        if ($balance == null) {
            $balance = $this->http->FindSingleNode("//img[contains(@alt, 'Verde')]/following::text()[normalize-space()][2][contains(normalize-space(), 'pontos')]", null, true, "/([\d\.\,]+)/");
        }

        if ($balance !== null) {
            $st = $email->add()->statement();
            $st->addProperty('Name', $r->getTravellers()[0][0]);
            $st->setBalance(str_replace('.', '', $balance));
        }
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        foreach (self::$dictionary as $lang => $dict) {
            if (!empty($dict['CONFIRA OS DADOS DA SUA RESERVA']) && $this->http->XPath->query("//text()[{$this->contains($dict['CONFIRA OS DADOS DA SUA RESERVA'])}]")->length > 0
                && !empty($dict['PRONTO PARA INICIAR A SUA JORNADA?']) && $this->http->XPath->query("//text()[{$this->contains($dict['PRONTO PARA INICIAR A SUA JORNADA?'])}]")->length > 0
            ) {
                $this->lang = $lang;

                break;
            }
        }

        $this->ParseRental($email);

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

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
    }

    private function normalizeDate($str)
    {
        //$this->logger->debug('$str = '.print_r( $str,true));
        $in = [
            "#^\s*(\d+)\,\s*(\w+)\s*(\d{4})\s*([\d\:]+)h\s*$#",
            //29, jan 2022 13h30
            "#^\s*(\d+)\,\s*(\w+)\s*(\d{4})\s*(\d{1,2})h(\d{2})\s*$#",
            //03, mar 2022 11:00 am
            "#^\s*(\d+)\,\s*(\w+)\s*(\d{4})\s*(\d{1,2}:\d{2}(?:\s*[ap]m))\s*$#ui",
        ];
        $out = [
            "$1 $2 $3, $4",
            "$1 $2 $3, $4:$5",
            "$1 $2 $3, $4",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = \AwardWallet\Engine\MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return strtotime($str);
    }

    private function normalizeCurrency(string $string)
    {
        $string = trim($string);

        if ($code = $this->re("#^\s*([A-Z]{3})\s*$#", $string)) {
            return $code;
        }
        $currencies = [
            'GBP' => ['£'],
            'EUR' => ['€'],
            'INR' => ['Rs.', 'Rs'],
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

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }
}
