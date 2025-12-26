<?php

namespace AwardWallet\Engine\reserva\Email;

use AwardWallet\Schema\Parser\Email\Email;

class It2581410 extends \TAccountChecker
{
    public $mailFiles = "reserva/it-2581410.eml, reserva/it-2581411.eml, reserva/it-2581412.eml, reserva/it-50024156.eml";

    private static $detectors = [
        'pt' => ["Informações Gerais", "Segmentos"],
    ];

    private static $dictionary = [
        'pt' => [
            "General information" => ["Informações Gerais"],
            "Segments"            => ["Segmentos"],
            "Destino"             => ["Destino", "Destino(s)"],
        ],
    ];

    private $from = '@reservafacil.tur.br';

    private $body = "Segmentos";

    private $subject = ["Atualização automática de dados da reserva", "Reserva Aérea"];

    private $lang;

    private $year;

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->detectEmailFromProvider($headers['from']) !== true) {
            return false;
        }

        foreach ($this->subject as $re) {
            if (stripos($headers["subject"], $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->from) !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $text = $this->http->Response['body'];

        if (stripos($text, $this->body) === false) {
            return false;
        }

        if ($this->detectBody()) {
            return $this->assignLang();
        }

        return false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        if (!$this->assignLang()) {
            $this->logger->debug("Can't determine a language");

            return $email;
        }

        $email->setType('It2581410');
        $this->parseEmail($email);

        return $email;
    }

    private function parseEmail(Email $email)
    {
        if (!$this->detectBody()) {
            return false;
        }

        $r = $email->add()->flight();

        $inform = $this->getTitleAndRows('Informações Gerais');

        if (!empty($inform[0])) {
            foreach ($inform[1] as $row) {
                $this->year = $this->http->FindSingleNode("./td[" . $this->getColNum('Criação', $inform[0]) . "]", $row,
                    true,
                    '/\d{1,2}\/\d{1,2}\/(\d{4})/');

                $confNo = $this->http->FindSingleNode("./td[" . $this->getColNum('Loc', $inform[0]) . "]", $row, true,
                    '/([A-Z\d]{5,6})/');

                if (!empty($confNo)) {
                    $r->general()->confirmation($confNo, $this->t('Loc'));
                }

                $rDate = $this->http->FindSingleNode("./td[" . $this->getColNum('Criação', $inform[0]) . "]", $row,
                    true,
                    '/(\d{1,2}\/\d{1,2}\/\d{4})/');

                if (!empty($rDate)) {
                    $r->general()->date(strtotime(str_replace('/', '-', $rDate)));
                }
            }
        }

        $pax = $this->getTitleAndRows('Passageiros');

        if (!empty($pax[0])) {
            foreach ($pax[1] as $traveller) {
                $lastname = $this->http->FindSingleNode("./td[" . $this->getColNum('Sobrenome', $pax[0]) . "]",
                    $traveller);
                $firstname = $this->http->FindSingleNode("./td[" . $this->getColNum('Nome', $pax[0]) . "]", $traveller);

                if (!empty($firstname) && !empty($lastname)) {
                    $r->general()->traveller($lastname . " " . $firstname, true);
                }
            }
        }

        $prices = $this->getTitleAndRows('Tarifas', '[last()]');

        if (!empty($prices[0])) {
            foreach ($prices[1] as $price) {
                $total = $this->http->FindSingleNode("./td[" . $this->getColNum('Total', $prices[0]) . "]", $price);

                if (!empty($price)) {
                    $r->price()
                        ->total($this->normalizePrice($total))
                        ->currency($this->getCurrency($total));
                }

                $cost = $this->http->FindSingleNode("./td[" . $this->getColNum('Tarifa', $prices[0]) . "]", $price);

                if (!empty($cost)) {
                    $r->price()
                        ->cost($this->normalizePrice($cost));
                }

                $tax = $this->http->FindSingleNode("./td[" . $this->getColNum('Tax. Emb.', $prices[0]) . "]", $price);

                if (!empty($tax)) {
                    $r->price()
                        ->tax($this->normalizePrice($tax));
                }
            }
        }

        //Segment
        $seg = $this->getTitleAndRows('Segmentos');

        if (!empty($seg[0]) && !empty($seg[1])) {
            foreach ($seg[1] as $segment) {
                if (!empty($segment)) {
                    $s = $r->addSegment();

                    $flight = $this->http->FindSingleNode("./td[" . $this->getColNum('Voo', $seg[0]) . "]",
                        $segment);

                    if (stripos($flight, '(operado') !== false) {
                        if (preg_match("#(\d{1,5})\s*\(operado ([^\)]{2,})\)#", $flight, $m)) {
                            $s->airline()
                                ->name($m[2])
                                ->number($m[1]);
                        }
                    } elseif (preg_match("/^(\d{1,5})$/", $flight, $m)) {
                        $s->airline()
                            ->number($m[1])
                            ->noName();
                    }

                    // Departure
                    $depDate = $this->http->FindSingleNode("./td[" . $this->getColNum('Saída', $seg[0]) . "]", $segment,
                        true, '/^(?:(\d{1,2}\/\d{1,2}\/\d{4}\s\d{1,2}:\d{1,2})|(\d{1,2}\/\d{1,2}\/\d{4}))$/');

                    if (!empty($depDate)) {
                        $s->departure()->date(strtotime(str_replace("/", "-", $depDate)));
                    }

                    if (empty($depDate)) {
                        $depDate = $this->normalizeDate($this->http->FindSingleNode("./td[" . $this->getColNum('Saída',
                                $seg[0]) . "]", $segment, true, '/(\d{1,2}\/[A-z]{3}\s\d{1,2}:\d{1,2})/'));
                        $s->departure()->date($depDate);
                    }

                    $departure = $this->http->FindSingleNode("./td[" . $this->getColNum('Origem', $seg[0]) . "]",
                        $segment);

                    if (!empty($departure)) {
                        if (preg_match("/^([A-Z]{3})\s-\s(.+)$/", $departure, $m)) {
                            $s->departure()
                                ->code($m[1])
                                ->name($m[2]);
                        }
                    }

                    //Arrival
                    $arrDate = $this->http->FindSingleNode("./td[" . $this->getColNum('Chegada', $seg[0]) . "]",
                        $segment, true, '/^(?:(\d{1,2}\/\d{1,2}\/\d{4}\s\d{1,2}:\d{1,2})|(\d{1,2}\/\d{1,2}\/\d{4}))$/');

                    if (!empty($arrDate)) {
                        $s->arrival()->date(strtotime(str_replace("/", "-", $arrDate)));
                    }

                    if (empty($arrDate)) {
                        $arrDate = $this->normalizeDate($this->http->FindSingleNode("./td[" . $this->getColNum('Chegada',
                                $seg[0]) . "]", $segment, true, '/(\d{1,2}\/[A-z]{3}\s\d{1,2}:\d{1,2})/'));
                        $s->arrival()->date($arrDate);
                    }

                    $arrival = $this->http->FindSingleNode("./td[" . $this->getColNum('Destino(s)', $seg[0]) . "]",
                        $segment);

                    if (empty($arrival)) {
                        $arrival = $this->http->FindSingleNode("./td[" . $this->getColNum('Destino', $seg[0]) . "]",
                            $segment);
                    }

                    if (!empty($arrival)) {
                        if (preg_match("/^([A-Z]{3})\s-\s(.+)$/", $arrival, $m)) {
                            $s->arrival()
                                ->code($m[1])
                                ->name($m[2]);
                        }
                    }

                    //Extra
                    $duration = $this->http->FindSingleNode("./td[" . $this->getColNum('Duração', $seg[0]) . "]",
                        $segment, true, '/^(\d{1,2}:\d{1,2})$/');

                    if (!empty($duration)) {
                        $s->extra()->duration($duration);
                    }

                    $class = $this->http->FindSingleNode("./td[" . $this->getColNum('Classe', $seg[0]) . "]", $segment,
                        true, '/^([A-Z\d])$/');

                    if (!empty($class)) {
                        $s->extra()->bookingCode($class);
                    }
                }
            }
        }

        return $email;
    }

    private function getColNum(string $name, array $arrTitle): int
    {
        return array_search($name, $arrTitle) + 1;
    }

    private function getTitleAndRows(string $name, string $append = ''): array
    {
        $xpath = "//text()[normalize-space(.) = '" . $this->t($name) . "']/ancestor::div[1]/descendant::tr";
        $title = $this->http->FindNodes($xpath . "[1]/td");
        $rows = $this->http->XPath->query($xpath . '[position() > 1]' . $append);

        return [$title, $rows];
    }

    private function detectBody()
    {
        foreach (self::$detectors as $lang => $phrases) {
            foreach ($phrases as $phrase) {
                if ($this->http->XPath->query("//*[{$this->contains($phrase)}]")->length > 0) {
                    return true;
                }
            }
        }

        return false;
    }

    private function assignLang()
    {
        foreach (self::$dictionary as $lang => $words) {
            if (isset($words["General information"], $words["Segments"])) {
                if ($this->http->XPath->query("//*[{$this->contains($words['General information'])}]")->length > 0
                    && $this->http->XPath->query("//*[{$this->contains($words['Segments'])}]")->length > 0
                ) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(' or ', array_map(function ($s) {
            return 'contains(normalize-space(.),"' . $s . '")';
        }, $field));
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) {
            return 'starts-with(normalize-space(.),"' . $s . '")';
        }, $field)) . ')';
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
    }

    private function getCurrency($node)
    {
        $node = str_replace("R$", "BRL", $node);

        if (preg_match("#\s*([A-Z]{3})\s*#", $node, $m)) {
            return $m[1];
        }

        return null;
    }

    private function normalizePrice($price)
    {
        if (preg_match("#([.,])\d{2}($|[^\d])#", $price, $m)) {
            $delimiter = $m[1];
        } else {
            $delimiter = '.';
        }
        $price = preg_replace('/[^\d\\' . $delimiter . ']+/', '', $price);
        $price = (float) str_replace(',', '.', $price);

        return $price;
    }

    private function normalizeDate($date)
    {
        $in = [
            '/(\d{1,2})\/([A-z]{3})\s(\d{1,2}:\d{1,2})/',
        ];
        $out = [
            '$1 $2 ' . $this->year . ' $3',
        ];
        $str = preg_replace($in, $out, $date);

        return strtotime($str);
    }
}
