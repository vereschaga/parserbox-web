<?php

namespace AwardWallet\Engine\jetsmart\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Common\Flight;
use AwardWallet\Schema\Parser\Email\Email;

class ItineraryConfirmation extends \TAccountChecker
{
    public $mailFiles = "jetsmart/it-669568601.eml, jetsmart/it-674622046.eml, jetsmart/it-676875712.eml, jetsmart/it-701227132.eml, jetsmart/it-702682589.eml, jetsmart/it-709882832.eml, jetsmart/it-710101872.eml, jetsmart/it-712638537.eml, jetsmart/it-840231343.eml";

    public $lang;
    public static $dictionary = [
        'en' => [
            'Booking Code'       => 'Booking Code',
            'PASSENGER NAME'     => 'PASSENGER NAME',
            '№ TICKET'           => '№ TICKET',
            'BOOKING DETAILS'    => 'BOOKING DETAILS',
            'Date:'              => 'Date:',
            'Time of departure:' => 'Time of departure:',
            'Time of arrival:'   => 'Time of arrival:',
            'Flight'             => 'Flight',
            'Operated by'        => 'Operated by',
            // 'stop'        => '',
            // 1 passenger payment
            'Fare:'      => 'Fare:',
            'Optionals:' => 'Optionals:',
            'Total:'     => 'Total:',
            // total payment
            'Ticket:'         => 'Ticket:',
            'Ticket ('        => 'Ticket (',
            'TOTAL Payment:'  => 'TOTAL Payment:',
            //'Departure:'     => '',
            //'Arrival:'     => '',
        ],
        'pt' => [
            'Booking Code'       => 'Itinerário de sua reserva',
            'PASSENGER NAME'     => 'NOME PASSAGEIRO',
            '№ TICKET'           => '№ BILHETE',
            'BOOKING DETAILS'    => 'DETALHE RESERVA',
            'Date:'              => 'Data:',
            'Time of departure:' => 'Hora de partida:',
            'Time of arrival:'   => 'Hora de chegada:',
            'Flight'             => 'Voo',
            'Operated by'        => 'Operado por',
            // 'stop'        => '',
            // 1 passenger payment
            'Fare:'      => 'Tarifa base:',
            'Optionals:' => 'Opcionais:',
            'Total:'     => 'Total:',
            // total payment
            'Ticket:'         => 'Bilhete:',
            'Ticket ('        => 'Ticket (',
            'TOTAL Payment:'  => 'TOTAL de Pago:',
            //'Departure:'     => '',
            //'Arrival:'     => '',
        ],
        'es' => [
            'Booking Code'       => 'Itinerario de su Reserva',
            'PASSENGER NAME'     => 'NOMBRE PASAJERO',
            '№ TICKET'           => '№ TICKET',
            'BOOKING DETAILS'    => 'DETALLE RESERVA',
            'Date:'              => 'Fecha:',
            'Time of departure:' => 'Hora de salida:',
            'Time of arrival:'   => 'Hora de llegada:',
            'Flight'             => 'Vuelo',
            'Operated by'        => 'Operado por',
            'stop'               => 'escala',
            // 1 passenger payment
            'Fare:'      => 'Tarifa base:',
            'Optionals:' => 'Opcionales:',
            'Total:'     => 'Total:',
            // total payment
            'Ticket:'         => 'Ticket:',
            'Ticket ('        => 'Ticket (',
            'TOTAL Payment:'  => 'TOTAL de Pago:',
            'Departure:'      => 'Origen:',
            'Arrival:'        => 'Destino:',
        ],
    ];

    private $detectFrom = "jetsmart@mg.jetsmart.com";
    private $detectSubject = [
        // en
        'Itinerary Confirmation',
        // pt
        'Confirmação de itinerário',
        // es
        'Confirmación Itinerario',
    ];

    public function detectEmailFromProvider($from)
    {
        return preg_match("/[@.]jetsmart\.com$/", $from) > 0;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (stripos($headers["from"], $this->detectFrom) === false) {
            return false;
        }

        foreach ($this->detectSubject as $dSubject) {
            if (stripos($headers["subject"], $dSubject) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if (
            $this->http->XPath->query("//a[{$this->contains(['/jetsmart.com/'], '@href')}]")->length === 0
            && $this->http->XPath->query("//*[{$this->contains(['Thank you for choosing JetSMART', 'JetSMART SpA /JetSMART S.A.', 'jetsmart.com', 'JETSMART AIRLINES S.A.'])}]")->length === 0
        ) {
            return false;
        }

        return $this->assignLang();
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();
        // if (empty($this->lang)) {
        //     $this->logger->debug("can't determine a language");
        //     return $email;
        // }
        $this->parseEmailHtml($email);

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

    public function niceTravellers($name)
    {
        return preg_replace("/^\s*(Mr|Ms|Mstr|Miss|Mrs)\s+/i", '', $name);
    }

    private function assignLang()
    {
        foreach (self::$dictionary as $lang => $dict) {
            if (!empty($dict["№ TICKET"]) && !empty($dict["BOOKING DETAILS"])) {
                if ($this->http->XPath->query("//*[{$this->contains($dict['№ TICKET'])}]")->length > 0
                    && $this->http->XPath->query("//*[{$this->contains($dict['BOOKING DETAILS'])}]")->length > 0
                ) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function parseEmailHtml(Email $email)
    {
        $emailConfirmations = [];
        $xpathEmail = "//text()[{$this->eq($this->t('Booking Code'))}]/ancestor::*[count(.//text()[{$this->eq($this->t('Booking Code'))}]) = 1][last()]";
        $emails = $this->http->XPath->query($xpathEmail);

        foreach ($emails as $emailRoot) {
            $conf = $this->http->FindSingleNode(".//text()[{$this->eq($this->t('Booking Code'))}]/following::text()[normalize-space()][1]",
                $emailRoot, true, "/^\s*([A-Z\d]{5,})\s*$/");

            if (in_array($conf, $emailConfirmations)) {
                continue;
            } else {
                $emailConfirmations[] = $conf;
            }
            $f = $email->add()->flight();

            // General
            $f->general()
                ->confirmation($conf)
                ->travellers($this->niceTravellers($this->http->FindNodes(".//tr[*[1][{$this->eq($this->t('PASSENGER NAME'))}]]/following::tr[normalize-space()][1]/*[1]", $emailRoot)));

            // Issued
            $xpath = ".//tr[*[1][{$this->eq($this->t('PASSENGER NAME'))}] and *[3][{$this->eq($this->t('№ TICKET'))}]]/following::tr[normalize-space()][1]";

            foreach ($this->http->XPath->query($xpath, $emailRoot) as $tRoot) {
                $f->issued()
                    ->ticket($this->http->FindSingleNode("*[3]", $tRoot, true, "/^\s*(\d{8,})\s*$/"), false,
                        $this->niceTravellers($this->http->FindSingleNode("*[1]", $tRoot)));
            }

            // Segments
            $xpath = ".//tr[*[1][{$this->contains($this->t('Time of departure:'))}] and *[2]//img and *[3][{$this->contains($this->t('Time of arrival:'))}]]";
            $nodes = $this->http->XPath->query($xpath, $emailRoot);

            if ($nodes->length > 0) {
                $this->parseSegment1($f, $nodes);
            } else {
                $xpath = ".//tr[*[1][{$this->contains($this->t('Departure:'))}] and *[2]//img and *[3][{$this->contains($this->t('Time of arrival:'))}]]";
                $nodes = $this->http->XPath->query($xpath, $emailRoot);
                //it-840231343.eml
                $this->parseSegment2($f, $nodes);
            }

            // Price
            $currencyStr = '';
            $currency = '';
            $total = $this->http->FindSingleNode(".//tr[count(*) = 2 and *[1][{$this->eq($this->t('TOTAL Payment:'))}]]/*[2]", $emailRoot);

            if (preg_match("/^\s*(?<currencyStr>(?<currency>[A-Z]{3})(?: \S{1,5})?)\s*(?<amount>\d[\d\., ]*)\s*$/",
                $total, $m)) {
                $currencyStr = trim($m['currencyStr']);
                $currency = trim($m['currency']);
                $f->price()
                    ->total(PriceHelper::parse($m['amount'], $m['currency']))
                    ->currency($m['currency']);
            } else {
                $f->price()
                    ->total(null);
            }

            if ($currencyStr === 'BRL R$') {
                $currencyStr = ['BRL R$', 'BRL $'];
            }
            $cost = $this->http->FindSingleNode(".//text()[{$this->eq($this->t('TOTAL Payment:'))}]/preceding::tr[count(*) = 2 and *[1][{$this->eq($this->t('Ticket:'))}]]/*[2]", $emailRoot);

            if (empty($cost)) {
                $cost = $this->http->FindSingleNode(".//text()[{$this->eq($this->t('TOTAL Payment:'))}]/preceding::tr[count(*) = 2 and *[1][{$this->starts($this->t('Ticket ('))}][contains(., '):')]]/*[2]", $emailRoot);
            }

            if (preg_match("/^\s*{$this->opt($currencyStr)}\s*(?<amount>\d[\d\., ]*)\s*$/", $cost, $m)) {
                $f->price()
                    ->cost(PriceHelper::parse($m['amount'], $currency));
            } else {
                $f->price()
                    ->cost(null);
            }

            $pXpath = ".//tr[count(*) = 2][*[1][{$this->eq($this->t('Total:'))}]]/preceding-sibling::tr[contains(@style, 'bold')][not({$this->contains($this->t('Fare:'))})]";
            $pXpath2 = ".//tr[count(*) = 2][*[1][{$this->eq($this->t('Optionals:'))}]][contains(@style, 'bold')][not({$this->contains($this->t('Fare:'))})]";
            $pNodes = $this->http->XPath->query($pXpath . ' | ' . $pXpath2, $emailRoot);
            $fees = [];

            foreach ($pNodes as $pRoot) {
                $name = trim($this->http->FindSingleNode("*[1]", $pRoot), ':');
                $value = $this->http->FindSingleNode("*[2]", $pRoot);

                if (preg_match("/^\s*{$this->opt($currencyStr)}\s*(?<amount>\d[\d\., ]*)\s*$/", $value, $m)) {
                    $fees[$name] = ($fees[$name] ?? 0.0) + PriceHelper::parse($m['amount'], $currency);
                } else {
                    $fees = [];

                    break;
                }
            }

            foreach ($fees as $name => $value) {
                $f->price()
                    ->fee($name, $value);
            }
        }

        return true;
    }

    private function parseSegment1(Flight $f, \DOMNodeList $nodes)
    {
        foreach ($nodes as $root) {
            $s = $f->addSegment();

            // Airline
            $flight = $this->http->FindSingleNode("following::tr[normalize-space()][1]", $root);

            if (preg_match("/^\s*\*?\s*{$this->opt($this->t('Flight'))}\s+(?<al>[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(?<fn>\d{1,5})(?:\s*\([^\(\)]+\))?\s*-\s*(?:{$this->opt($this->t('Operated by'))}\s*(?<operator>.+?))?\s*$/",
                $flight, $m)) {
                $s->airline()
                    ->name($m['al'])
                    ->number($m['fn'])
                    ->operator($m['operator'] ?? null, true, true);
            }

            $date = $this->normalizeDate($this->http->FindSingleNode("preceding::tr[normalize-space()][1]", $root, true,
                "/^\s*{$this->opt($this->t('Date:'))}\s*(.+?)\s*$/"));

            if (empty($date) && !empty($this->http->FindSingleNode("preceding::tr[normalize-space()][1]", $root, true,
                    "/^\s*\d+\s+{$this->opt($this->t('stop'))}/"))) {
                $date = $this->normalizeDate($this->http->FindSingleNode("preceding::tr[not(.//tr)][{$this->contains($this->t('Date:'))}][1]", $root, true,
                    "/^\s*{$this->opt($this->t('Date:'))}\s*(.+?)\s*$/"));
            }

            $re = "/^\s*(?<name>.+?)\s*\n*\s*(?<code>[A-Z]{3})\s+(?:{$this->opt($this->t('Time of departure:'))}|{$this->opt($this->t('Time of arrival:'))})\s*(?<time>\d{1,2}:\d{2})\s*$/s";
            // $this->logger->debug('$re = '.print_r( $re,true));

            // Departure
            $depart = implode("\n", $this->http->FindNodes("*[1]//text()[normalize-space()]", $root));

            if (preg_match($re, $depart, $m)) {
                $s->departure()
                    ->name($m['name'])
                    ->code($m['code'])
                    ->date((!empty($date)) ? strtotime($m['time'], $date) : null);
            }

            // Arrival
            $arrive = implode("\n", $this->http->FindNodes("*[3]//text()[normalize-space()]", $root));

            if (preg_match($re, $arrive, $m)) {
                $s->arrival()
                    ->name($m['name'])
                    ->code($m['code'])
                    ->date((!empty($date)) ? strtotime($m['time'], $date) : null);
            }

            $segments = $f->getSegments();

            foreach ($segments as $segment) {
                if ($segment->getId() !== $s->getId()) {
                    if (serialize($segment->toArray()) === serialize($s->toArray())) {
                        $f->removeSegment($s);

                        break;
                    }
                }
            }
        }
    }

    private function parseSegment2(Flight $f, \DOMNodeList $nodes)
    {
        foreach ($nodes as $root) {
            $s = $f->addSegment();

            // Airline
            $flight = $this->http->FindSingleNode("./preceding::text()[normalize-space()][1]", $root);

            if (preg_match("/{$this->opt($this->t('Flight'))}\s+(?<al>[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*\-\s*(?<fn>\d{1,5})\s*$/",
                $flight, $m)) {
                $s->airline()
                    ->name($m['al'])
                    ->number($m['fn']);
            }

            $date = $this->normalizeDate($this->http->FindSingleNode("./preceding::text()[normalize-space()][1]/ancestor::tr[1]", $root, true,
                "/^\s*{$this->opt($this->t('Date:'))}\s*([\d\/]+)/"));

            // Departure
            $departArrivalInfo = implode("\n", $this->http->FindNodes(".", $root));

            if (preg_match("/{$this->opt($this->t('Departure:'))}\s+(?<depName>.+)\s+\((?<depCode>[A-Z]{3})\)\s+{$this->opt($this->t('Arrival:'))}\s+(?<arrName>.+)\s+\((?<arrCode>[A-Z]{3})\)\s+{$this->opt($this->t('Time of departure:'))}\s+(?<depTime>[\d\:]+)\s+{$this->opt($this->t('Time of arrival:'))}\s+(?<arrTime>[\d\:]+)/", $departArrivalInfo, $m)) {
                $s->departure()
                    ->name($m['depName'])
                    ->code($m['depCode'])
                    ->date((!empty($date)) ? strtotime($m['depTime'], $date) : null);

                $s->arrival()
                    ->name($m['arrName'])
                    ->code($m['arrCode'])
                    ->date((!empty($date)) ? strtotime($m['arrTime'], $date) : null);
            }

            $segments = $f->getSegments();

            foreach ($segments as $segment) {
                if ($segment->getId() !== $s->getId()) {
                    if (serialize($segment->toArray()) === serialize($s->toArray())) {
                        $f->removeSegment($s);

                        break;
                    }
                }
            }
        }
    }

    private function t($s)
    {
        if (!isset($this->lang, self::$dictionary[$this->lang], self::$dictionary[$this->lang][$s])) {
            return $s;
        }

        return self::$dictionary[$this->lang][$s];
    }

    private function contains($field, $text = 'normalize-space(.)')
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($text) {
            return 'contains(' . $text . ',"' . $s . '")';
        }, $field)) . ')';
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
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

        return '(' . implode(' or ', array_map(function ($s) {
            return 'starts-with(normalize-space(.),"' . $s . '")';
        }, $field)) . ')';
    }

    private function normalizeDate(?string $date): ?int
    {
        // $this->logger->debug('date begin = ' . print_r( $date, true));
        $in = [
            // 13/06/2024
            '/^\s*(\d{1,2})\/(\d{1,2})\/(\d{4})\s*$/ui',
        ];
        $out = [
            '$1.$2.$3',
        ];

        $date = preg_replace($in, $out, $date);

//        $this->logger->debug('date replace = ' . print_r( $date, true));

        return strtotime($date);
    }

    private function opt($field, $delimiter = '/')
    {
        $field = (array) $field;

        if (empty($field)) {
            $field = ['false'];
        }

        return '(?:' . implode("|", array_map(function ($s) use ($delimiter) {
            return str_replace(' ', '\s+', preg_quote($s, $delimiter));
        }, $field)) . ')';
    }
}
