<?php

namespace AwardWallet\Engine\sbtur\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Common\Flight;
use AwardWallet\Schema\Parser\Common\FlightSegment;
use AwardWallet\Schema\Parser\Email\Email;

class AirBooking extends \TAccountChecker
{
    public $mailFiles = "sbtur/it-10618889.eml, sbtur/it-11086772.eml, sbtur/it-38056895.eml, sbtur/it-617961584.eml, sbtur/it-7967876.eml, sbtur/it-7967935.eml, sbtur/it-8049235.eml, sbtur/it-8060685.eml, sbtur/it-8060686.eml, sbtur/it-885762807.eml, sbtur/it-9006302.eml, sbtur/it-9006477.eml, sbtur/it-9007712.eml";

    public static $dictionary = [
        'pt' => [
            'flight' => 'Voo',
            'seats'  => ['Assentos', 'ASSENTOS'],
        ],
    ];

    private $reSubject = [
        'Reserva Aérea', // pt
        'Aéreo - Confirmação de Emissão', // pt
    ];

    private $date;
    private $lang = '';
    private $params = [
        'Seats'         => [],
        'ConfNumber'    => '',
    ];
    private $type = '';
    private $providerCode = '';

    private static $providerDetect = [
        'uniglobe' => ['@uniglobeviajex.com.br'],
        'copaair'  => ['@estrelatur.com.br', 'cdn.portaldoagente.com.br/Logomarcas/companhia6.jpg'],
        'golair'   => ['Gol_GWS', 'cdn.portaldoagente.com.br/Logomarcas/companhia5.jpg'],
        'lanpass'  => ['pertence à companhia Latam', 'logo_iatas/iata_LA.png'],
        'reserva'  => ['@sakuraclick.com.br', 'www.sakuraclick.com.br', 'efacilplus.com.br', '.teresaperez.com.br', 'travellink.com.br', 'portaldoagente.com.br', 'wooba.voagov.com.br'],
        'azul'     => ['Tudo Azul'],
        'sbtur'    => [ // always last!!!
            '@sbtur.com', '@maisfly.com.br',
            'www.maisfly.com.br', '@conceitur.com.br', '@travelworkers.com.br', '@flytour.com.br', 'travelmania.com.br', '@confiancaturismo.com.br', '@flvviagens.com.br', '@allwaystravel.com.br',
            '@CONFIANCATURISMO.COM.BR', '@VOEBARATO.TUR.BR', '@2trip.com.br', 'Hopetourviagens', '@fronturbhz.com.br',
        ],
    ];

    private $patterns = [
        'travellerName' => '[[:alpha:]][-.\/\'’[:alpha:] ]*[[:alpha:]]', // Mr. Hao-Li Huang
        'eTicket'       => '\d{3}(?: | ?- ?)?\d{5,}(?: | ?[-\/] ?)?\d{1,3}', // 075-2345005149-02    |    0167544038003-004    |    055 5820289918 /19
    ];

    public function detectEmailFromProvider($from)
    {
        foreach (self::$providerDetect as $efrom) {
            foreach ($efrom as $email) {
                if (stripos($from, $email) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->detectEmailFromProvider(rtrim($headers['from'], '> ')) !== true) {
            return false;
        }

        foreach ($this->reSubject as $re) {
            if (strpos($headers['subject'], $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->assignLang() !== true) {
            return false;
        }

        if ($this->assignProvider($parser->getHeaders())) {
            return true;
        } elseif ($this->http->XPath->query("//tr[*[1][{$this->eq('Cia')}] and *[2][{$this->eq('Origem / Destino')}] and *[3][{$this->eq('Voo')}] and *[{$this->eq('Loc Cia')}]]")->length > 0) {
            return true;
        }

        return false;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();
        $this->assignProvider($parser->getHeaders());
        $this->date = strtotime("- 1 month", strtotime($parser->getHeader('date')));

        if (preg_match("/\bReserva\s*Aérea\s+([A-Z\d]{6,})\s*$/u", $parser->getSubject(), $m)) {
            $this->params['ConfNumber'] = $m[1];
        }
        $this->parseEmail($email);

        $root = $this->http->XPath->query("(//*[self::td or self::th][normalize-space()][normalize-space(.) = 'Total' and position() = last()])[1]/ancestor::table[1]");
        $items = $this->http->XPath->query("./descendant::tr[not(.//tr)][count(*) > 1][1]/*[self::td or self::th]", $root->item(0));
        $i = 0;

        foreach ($items as $item) {
            $i++;
            $th = trim($item->nodeValue);

            if ($th == 'Tarifa') {
                $email->price()->cost($this->normalizePrice($this->http->FindSingleNode("./descendant::tr[not(.//tr)][normalize-space(.)][last()]/td[{$i}]", $root->item(0))));
            }

            if (stripos($th, 'Tax') !== false || stripos($th, 'Tx ') === 0) {
                $email->price()->fee($th, $this->normalizePrice($this->http->FindSingleNode("./descendant::tr[not(.//tr)][normalize-space(.)][last()]/td[{$i}]", $root->item(0))));
            }

            if ($th == 'Total') {
                $totalStr = $this->http->FindSingleNode("./descendant::tr[not(.//tr)][normalize-space(.)][last()]/td[{$i}]", $root->item(0));

                if (preg_match("#(\D+)\s*([\d.,]+)#", $totalStr, $m)) {
                    $email->price()
                        ->total($this->normalizePrice($m[2]))
                        ->currency($this->getCurrency($m[1]));
                }
            }
        }
        $total = $email->getPrice();

        if (!isset($total)) {
            $totalStr = $this->http->FindSingleNode("(//*[self::td or self::th][normalize-space(.) = 'Total' and position() = last()])[1]/ancestor::tr[1]/following-sibling::tr[normalize-space()][last()]//td[normalize-space()][last()]");

            if (preg_match("#(\D+)\s*([\d.,]+)#", $totalStr, $m)) {
                $email->price()
                    ->total($this->normalizePrice($m[2]))
                    ->currency($this->getCurrency($m[1]));
            }
        }

        if (!empty($this->providerCode)) {
            $email->setProviderCode($this->providerCode);
        }

        $email->setType('AirBooking' . ucfirst($this->lang) . $this->type);

        return $email;
    }

    public static function getEmailLanguages()
    {
        return ['pt'];
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary) * 3;
    }

    public static function getEmailProviders()
    {
        return array_keys(self::$providerDetect);
    }

    private function parseEmail(Email $email): void
    {
        // Travel Agency
        $email->obtainTravelAgency();

        $f = $email->add()->flight();

        // Passengers
        $travellers = [];
        $xpath = "(//text()[normalize-space()='Reserva Aérea - Plano de Viagem']/following::text()[normalize-space() = 'Sobrenome']/ancestor::tr[not(normalize-space() = 'Sobrenome')][1])[1][descendant::*[normalize-space() = 'Nome']]/following-sibling::tr[normalize-space()]";
        $rows = $this->http->XPath->query($xpath);

        foreach ($rows as $proot) {
            $nameParts = [];

            $firstname = $this->normalizeTraveller($this->http->FindSingleNode("*[3]", $proot, true, "/^{$this->patterns['travellerName']}$/u"));

            if ($firstname) {
                $nameParts[] = $firstname;
            }

            $lastname = $this->normalizeTraveller($this->http->FindSingleNode("*[2]", $proot, true, "/^{$this->patterns['travellerName']}$/u"));

            if ($lastname) {
                $nameParts[] = $lastname;
            }

            if (count($nameParts) > 0) {
                $travellers[] = implode(' ', $nameParts);
            }
        }

        if (count($travellers) === 0) {
            $travellers = array_filter($this->http->FindNodes("(//tr[*[1][normalize-space() = 'Passageiro']])[1]/following-sibling::tr/td[1]", null, '/^\s*[A-Z]+\s+-\s+([A-Za-z\s]+\/[A-Za-z\s]+)\s*$/'));
        }

        if (count($travellers) === 0) {
            $travellers = array_filter($this->http->FindNodes("(//tr[*[1][normalize-space() = 'Passageiro']])[1]/ancestor::thead/following-sibling::tbody/tr/td[1]", null, '/^\s*[A-Z]+\s+-\s+([A-Za-z\s]+\/[A-Za-z\s]+)\s*$/'));
        }

        if (count($travellers) === 0) {
            $travellers = array_filter([$this->http->FindSingleNode("(//td[contains(., 'Passageiro') and not(.//td)]/following-sibling::td[1])[1]", null, true, '/-\s+([A-Z\s\/]+)/i')]);
        }

        if (count($travellers) === 0) {
            $column = 1 + count($this->http->FindNodes("//*[normalize-space() = 'Passageiro' and not(.//th)][1]/preceding-sibling::*"));
            $travellers = array_filter($this->http->FindNodes("//*[normalize-space() = 'Passageiro' and not(.//th)][1]/ancestor::tr[1]/following-sibling::tr[1]/*[$column]", null, '/-\s+([A-Z\s\/]+)/i'));
        }

        $travellers = array_map(function ($item) {
            return $this->normalizeTraveller($item);
        }, $travellers);

        if (count($travellers) > 0) {
            $f->general()->travellers(array_unique($travellers), true);
        }

        $status = $this->http->FindSingleNode("//tr[*[normalize-space()][1][normalize-space()='Localizador']]/following::tr[1]/descendant::td[3]");

        if (stripos($status, 'Cancelado') !== false && !empty($this->params['ConfNumber'])) {
            $f->general()
                ->confirmation($this->params['ConfNumber'])
                ->status('Cancelado')
                ->cancelled();

            return;
        }

        // TicketNumbers
        $xpath = "//text()[contains(.,'Número')]/ancestor::tr[contains(.,'Localizador') and not(.//tr)][1]/following-sibling::tr[count(td) > 3]";
        $rows = $this->http->XPath->query($xpath);

        if ($rows->length === 0) {
            $xpath = "//text()[contains(.,'Número')]/ancestor::tr[contains(.,'Localizador') and not(.//tr)][1]/ancestor::thead/following-sibling::tbody/tr[count(*)>3]";
            $rows = $this->http->XPath->query($xpath);
        }

        foreach ($rows as $troot) {
            $passengerName = $this->normalizeTraveller($this->http->FindSingleNode("*[3]/descendant::text()[normalize-space()]", $troot, true, "/^{$this->patterns['travellerName']}$/"));
            $ticket = $this->http->FindSingleNode("*[1]", $troot, true, "/^{$this->patterns['eTicket']}$/");

            if ($ticket) {
                $f->issued()->ticket($ticket, false, $passengerName);
            }
        }

        if (count($f->getTicketNumbers()) === 0) {
            $passengerName = $this->normalizeTraveller($this->http->FindSingleNode("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('Passageiro'))}] ]/*[normalize-space()][2]/descendant::text()[normalize-space()]", null, true, "/^{$this->patterns['travellerName']}$/u"));
            $ticket = $this->http->FindSingleNode("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('Número do bilhete'))}] ]/*[normalize-space()][2]", null, true, "/^{$this->patterns['eTicket']}$/");

            if ($ticket) {
                $f->issued()->ticket($ticket, false, $passengerName);
            }
        }

        // Seats
        $seatsTitle = $this->http->FindNodes("(//text()[{$this->contains($this->t('seats'))}])[1]/following::*[(self::td or self::th) and normalize-space()='Passageiro'][1]/ancestor::tr[1]/*");
        $xpath = "(//text()[{$this->contains($this->t('seats'))}])[1]/following::*[(self::td or self::th) and normalize-space()='Passageiro'][1]/ancestor::thead[1]/following-sibling::*[self::tbody or self::tr]/descendant-or-self::tr";
        $rows = $this->http->XPath->query($xpath);

        if ($rows->length == 0) {
            $xpath = "(//text()[{$this->contains($this->t('seats'))}])[1]/following::*[(self::td or self::th) and normalize-space()='Passageiro'][1]/ancestor::tr[1]/following-sibling::tr";
            $rows = $this->http->XPath->query($xpath);
        }
        $seatsValue = [];

        foreach ($rows as $sroot) {
            $seatsValue[] = preg_replace("/^\s*(\d{1,3})-([A-Z])\s*$/", '$1' . '$2',
                $this->http->FindNodes(".//td", $sroot, "#^\s*(\d{1,3}-?[A-Z])\b#"));
        }

        $this->params['Seats'] = [];

        if (!empty($seatsTitle) && !empty($seatsValue)) {
            if (count($seatsTitle) == count($seatsValue[0])) {
                foreach ($seatsTitle as $key => $value) {
                    if (preg_match("/\b([A-Z][A-Z\d]|[A-Z\d][A-Z])(\s*)(\d+)(\s*)([A-Z]{3})(\s+)([A-Z]{3})\b/", $value, $m)) {
                        $this->params['Seats'][$m[1] . preg_replace('/\s+/', ' ', $m[2]) . $m[3] . preg_replace('/\s+/', ' ', $m[4]) . $m[5] . preg_replace('/\s+/', ' ', $m[6]) . $m[7]] = array_filter(array_column($seatsValue, $key));
                    } elseif (preg_match("/^\s*([A-Z]{3})(\s+)([A-Z]{3})\s*$/", $value, $m)) {
                        $this->params['Seats'][$m[1] . preg_replace('/\s+/', ' ', $m[2]) . $m[3]] = array_filter(array_column($seatsValue, $key));
                    }
                }
            }
        }

        // segments
        $xpath = "(//td[normalize-space()='Chegada']/ancestor::tr[ *[{$this->eq($this->t('flight'))}] ][1])[1]/following-sibling::tr[not(./td/@colspan)]";
        $segments = $this->http->XPath->query($xpath);

        if ($segments->length > 0 && count($this->http->FindNodes($xpath . '[1]//td')) >= 9) {
            $this->parseEmailSegment_type1($f, $segments);
        } else {
            $xpath = "(//text()[contains(.,'Destino')])[1]/ancestor::tr[ *[{$this->eq($this->t('flight'))}] ][1]/following-sibling::tr[not(./td/@colspan)]";
            $segments = $this->http->XPath->query($xpath);

            if ($segments->length == 0) {
                $xpath = "(//text()[contains(.,'Destino')])[1]/ancestor::tr[ *[{$this->eq($this->t('flight'))}] ][1]/ancestor::thead[1]/following-sibling::tbody/tr[not(./td/@colspan)]";
                $segments = $this->http->XPath->query($xpath);
            }

            if ($segments->length > 0 && count($this->http->FindNodes($xpath . '[1]/td')) >= 8) {
                $this->parseEmailSegment_type2($f, $segments);
            } elseif ($segments->length > 0 && count($this->http->FindNodes($xpath . '[1]/td')) == 7) {
                $this->parseEmailSegment_type3($f, $segments);
            }
        }
    }

    // 9 or 10 columns
    private function parseEmailSegment_type1(Flight $f, \DOMNodeList $segments): void
    {
        $this->logger->debug(__FUNCTION__);
        $this->type = 1;

        // General
        $confs = array_filter($this->http->FindNodes("//*[contains(text(),'Localizador')]/ancestor::tr[contains(.,'Prazo')][1]/following-sibling::tr[normalize-space()][1]/td[1]", null, "#^\s*([A-Z\d]{5,})\s*$#"));

        if (empty($confs)) {
            $confs = array_filter($this->http->FindNodes("//text()[normalize-space() = 'Localizador da Reserva'][1]/following::text()[normalize-space()][1]", null, "#^\s*([A-Z\d]{5,})\s*$#"));
        }

        foreach ($confs as $conf) {
            $f->general()
                ->confirmation($conf, "Localizador")
            ;
        }

        // Segment
        $countTd = count($this->http->FindNodes('descendant::td', $segments->item(0)));

        foreach ($segments as $i => $root) {
            $s = $f->addSegment();

            // Airline
            $conf = $this->http->FindSingleNode(".//td[last()]", $root, true, "#^\s*([A-Z\d]{5,6})\s*$#");

            if (!empty($conf)) {
                $s->airline()
                    ->confirmation($conf);
            }

            $flight = $this->http->FindSingleNode(".//td[2]", $root);

            if (preg_match("/\b([A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(\d+)/", $flight, $m)) {
                $s->airline()
                    ->name($m[1])
                    ->number($m[2]);
            }

            if (empty($s->getConfirmation()) && empty($s->getAirlineName())) {
                continue;
            }

            // Departure
            $s->departure()
                ->date($this->normalizeDate($this->http->FindSingleNode(".//td[3]", $root), true));
            $DepName = $this->http->FindSingleNode(".//td[5]", $root);

            if (preg_match("#([A-Z]{3})\s*-\s*(.+)#", $DepName, $m)) {
                $s->departure()
                    ->code($m[1])
                    ->name($m[2])
                ;
            }
            unset($DepName);

            // Arrival
            $s->arrival()
                ->date($this->normalizeDate($this->http->FindSingleNode(".//td[4]", $root), true));
            $ArrName = $this->http->FindSingleNode(".//td[6]", $root);

            if (preg_match("#([A-Z]{3})\s*-\s*(.+)#", $ArrName, $m)) {
                $s->arrival()
                    ->code($m[1])
                    ->name($m[2])
                ;
            }
            unset($ArrName);

            if (empty($s->getDepCode()) && empty($s->getArrCode())) {
                if ($segments->length == count($this->params['Seats'])) {
                    $keys = array_keys($this->params['Seats']);

                    if (preg_match("/([A-Z]{3}) ([A-Z]{3})$/", $keys[$i], $m)) {
                        $s->departure()->code($m[1]);
                        $s->arrival()->code($m[2]);
                    }
                }
            }

            if (empty($s->getDepCode()) && empty($s->getArrCode())) {
                $s->departure()->noCode();
                $s->arrival()->noCode();
            }

            // Extra
            $s->extra()
                ->bookingCode($this->http->FindSingleNode(".//td[7]", $root, true, "#([A-Z]{1,2})#"));

            if ($countTd === 10) {
                $s->extra()
                    ->aircraft($this->http->FindSingleNode(".//td[8]", $root));
            }

            $this->parseSeats($s);
        }
    }

    // 8 or 9 columns
    private function parseEmailSegment_type2(Flight $f, \DOMNodeList $segments): void
    {
        $this->logger->debug(__FUNCTION__);
        $this->type = 2;

        // General
        $confs = $this->http->FindNodes("//*[contains(text(),'Localizador')]/ancestor::tr[contains(.,'Prazo')][1]/following-sibling::tr[1]//td[1]", null, "#^\s*([A-Z\d]{5,6})\s*$#");

        if (empty($confs)) {
            $confs = $this->http->FindNodes("//text()[normalize-space() = 'Localizador da Reserva'][1]/following::text()[normalize-space()][1]", null, "#^\s*([A-Z\d]{5,6})\s*$#");
        }

        foreach ($confs as $conf) {
            $f->general()
                ->confirmation($conf, "Localizador")
            ;
        }

        // Segment
        $countTd = count($this->http->FindNodes('descendant::td', $segments->item(0)));

        foreach ($segments as $i => $root) {
            $s = $f->addSegment();

            // Airline
            $conf = $this->http->FindSingleNode(".//td[last()]", $root, true, "#^\s*([A-Z\d]{5,6})\s*$#");

            if (!empty($conf)) {
                $s->airline()
                    ->confirmation($conf);
            }

            $flight = $this->http->FindSingleNode(".//td[2]", $root);

            if (preg_match("/\b([A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(\d+)/", $flight, $m)) {
                $s->airline()
                    ->name($m[1])
                    ->number($m[2]);
            }

            if (empty($s->getConfirmation()) && empty($s->getAirlineName())) {
                continue;
            }

            // Departure
            $dep = implode(" ", $this->http->FindNodes(".//td[3]//text()", $root));
            //$this->logger->debug($dep);
            if (preg_match("#([A-Z]{3})\s*-\s*(.+)\s+(\d+\s*\w+\s*\d+:\d+)#", $dep, $m)) {
                $s->departure()
                    ->code($m[1])
                    ->name($m[2])
                    ->date($this->normalizeDate($m[3], true));
            }
            unset($dep);

            // Arrival
            $arr = implode(" ", $this->http->FindNodes(".//td[5]//text()", $root));

            if (preg_match("#([A-Z]{3})\s*-\s*(.+)\s+(\d+\s*\w+\s*\d+:\d+)#", $arr, $m)) {
                $s->arrival()
                    ->code($m[1])
                    ->name($m[2])
                    ->date($this->normalizeDate($m[3], true));
            }
            unset($arr);

            if (empty($s->getDepCode()) && empty($s->getArrCode())) {
                if ($segments->length == count($this->params['Seats'])) {
                    $keys = array_keys($this->params['Seats']);

                    if (preg_match("/([A-Z]{3}) ([A-Z]{3})$/", $keys[$i], $m)) {
                        $s->departure()->code($m[1]);
                        $s->arrival()->code($m[2]);
                    }
                }
            }

            // Extra
            if ($countTd === 9) {
                $s->extra()->bookingCode($this->http->FindSingleNode(".//td[7]", $root, true, "#^\s*([A-Z]{1,2})\s*$#"));
            } else {
                $s->extra()->bookingCode($this->http->FindSingleNode(".//td[6]", $root, true, "#^\s*([A-Z]{1,2})\s*$#"));
            }

            $td = $this->http->FindSingleNode(".//td[8]", $root);

            if (preg_match("#([\D]*)\d[\s\S]*Avião:\s*(.+)#", $td, $m)) {
                $s->extra()
                    ->cabin($m[1], true, true)
                    ->aircraft($m[2]);
            }

            $this->parseSeats($s);
        }
    }

    // 7 columns
    private function parseEmailSegment_type3(Flight $f, \DOMNodeList $segments): void
    {
        $this->logger->debug(__FUNCTION__);
        $this->type = 3;

        // General
        $confs = array_filter($this->http->FindNodes("//*[contains(text(),'Localizador')]/ancestor::tr[contains(.,'Prazo')][1]/following-sibling::tr[1]//td[1]//text()[normalize-space()][1]", null, "#^\s*([A-Z\d]{5,})\s*$#"));

        if (count($confs) === 0) {
            $confs = array_filter($this->http->FindNodes("//text()[normalize-space() = 'Localizador da Reserva'][1]/following::text()[normalize-space()][1]", null, "#^\s*([A-Z\d]{5,})\s*$#"));
        }

        if (count($confs) === 0) {
            $column = 1 + count($this->http->FindNodes("(//*[normalize-space() = 'Localizador da Reserva' and not(.//th)])[1]/preceding-sibling::*"));
            $confs = array_filter($this->http->FindNodes("(//*[normalize-space() = 'Localizador da Reserva' and not(.//th)])[1]/ancestor::tr[1]/ancestor::thead[1]/following-sibling::tbody/tr[1]/*[$column]", null, "#^\s*([A-Z\d]{5,6})\s*$#"));

            if (count($confs) === 0) {
                $confs = array_filter($this->http->FindNodes("(//*[normalize-space()='Localizador da Reserva' and not(.//th)])[1]/ancestor::tr[1]/following-sibling::tr[1]/*[$column]/descendant::text()[normalize-space()]", null, "/^\s*([A-Z\d]{5,7})\s*$/"));
            }
        }

        $confs = array_unique($confs);

        foreach ($confs as $conf) {
            $f->general()
                ->confirmation($conf, "Localizador")
            ;
        }

        // Segments

        foreach ($segments as $i => $root) {
            $s = $f->addSegment();

            // Airline
            $conf = $this->http->FindSingleNode("./td[last()]", $root, true, "#^\s*([A-Z\d]{5,6})\s*$#");

            if (!empty($conf)) {
                $s->airline()
                    ->confirmation($conf);
            }

            $flight = $this->http->FindSingleNode("./td[3]", $root);

            if (preg_match("/^\s*([A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(\d+)\b/", $flight, $m)) {
                $s->airline()
                    ->name($m[1])
                    ->number($m[2]);
            }

            // Departure
            $dep = implode(" ", $this->http->FindNodes("./td[2]/descendant::td[normalize-space()][1]//text()", $root));

            if (preg_match("#([A-Z]{3})\s*-\s*(.+)\s+(\d+\s*[[:alpha:]]+(?:\s*\d{4})?\s+\d+:\d+)#", $dep, $m)) {
                $s->departure()
                    ->code($m[1])
                    ->name($m[2])
                    ->date($this->normalizeDate($m[3], true));
            }

            if (preg_match("#Terminal:\s*(\d+)#", $dep, $m)) {
                $s->departure()->terminal($m[1]);
            }
            unset($dep);

            // Arrival
            $arr = implode(" ", $this->http->FindNodes("./td[2]/descendant::td[normalize-space()][2]//text()", $root));

            if (preg_match("#([A-Z]{3})\s*-\s*(.+)\s+(\d+\s*[[:alpha:]]+(?:\s*\d{4})?\s+\d+:\d+)#", $arr, $m)) {
                $s->arrival()
                    ->code($m[1])
                    ->name($m[2])
                    ->date($this->normalizeDate($m[3], true));
            }

            if (preg_match("#Terminal:\s*(\d+)#", $arr, $m)) {
                $s->arrival()->terminal($m[1]);
            }
            unset($arr);

            if (empty($s->getDepCode()) && empty($s->getArrCode())) {
                if ($segments->length == count($this->params['Seats'])) {
                    $keys = array_keys($this->params['Seats']);

                    if (preg_match("/([A-Z]{3}) ([A-Z]{3})$/", $keys[$i], $m)) {
                        $s->departure()->code($m[1]);
                        $s->arrival()->code($m[2]);
                    }
                }
            }

            // Extra
            $s->extra()
                ->bookingCode($this->http->FindSingleNode("./td[5]", $root, true, "#^\s*([A-Z]{1,2})\s*$#"));

            $td = implode("\n", $this->http->FindNodes("./td[6][contains(normalize-space(), 'Avião:')]//text()[normalize-space()!='']", $root));

            if (preg_match("#Avião:\s*(.+)#", $td, $m)) {
                $s->extra()
                    ->aircraft($m[1]);
            }

            if (preg_match("#Família:\s*(.+)#", $td, $m)) {
                $s->extra()
                    ->cabin($m[1]);
            }

            $this->parseSeats($s);
        }
    }

    private function parseSeats(FlightSegment $s): void
    {
        if (count($this->params['Seats']) === 0 || empty($s->getDepCode()) || empty($s->getArrCode())) {
            return;
        }

        $codes = $s->getDepCode() . ' ' . $s->getArrCode();

        if (!empty($s->getAirlineName()) && !empty($s->getFlightNumber())
            && array_key_exists($key = ($s->getAirlineName() . $s->getFlightNumber() . ' ' . $codes), $this->params['Seats'])
            && is_array($this->params['Seats'][$key])
            || !empty($s->getAirlineName()) && !empty($s->getFlightNumber())
            && array_key_exists($key = ($s->getAirlineName() . ' ' . $s->getFlightNumber() . ' ' . $codes), $this->params['Seats'])
            && is_array($this->params['Seats'][$key])
            || array_key_exists($key = $codes, $this->params['Seats'])
            && is_array($this->params['Seats'][$key])
        ) {
            $seats = array_filter($this->params['Seats'][$key]);

            if (count($seats) > 0) {
                foreach ($seats as $seat) {
                    $passengerName = null;
                    $seatHeaders = $this->http->XPath->query("//tr[*[1][{$this->eq($this->t('Passageiro'))}] and not(preceding-sibling::*[normalize-space()])][following-sibling::*[normalize-space()] or ../self::thead]/*[{$this->starts($key)}]");

                    foreach ($seatHeaders as $seatHeader) {
                        $pos = $this->http->XPath->query('preceding-sibling::*', $seatHeader)->length + 1;
                        $passengerName = $this->normalizeTraveller(
                            $this->http->FindSingleNode("ancestor::tr[1]/../following-sibling::tbody/tr[ *[{$pos}][{$this->eq($seat)}] ]/*[1]", $seatHeader, true, "/^{$this->patterns['travellerName']}$/u")
                            ?? $this->http->FindSingleNode("ancestor::tr[ following-sibling::*[normalize-space()] ][1]/following-sibling::tr[ *[{$pos}][{$this->eq($seat)}] ]/*[1]", $seatHeader, true, "/^{$this->patterns['travellerName']}$/u")
                            ?? $this->http->FindSingleNode("ancestor::tr[ following-sibling::*[normalize-space()] ][1]/following-sibling::tbody/tr[ *[{$pos}][{$this->eq($seat)}] ]/*[1]", $seatHeader, true, "/^{$this->patterns['travellerName']}$/u")
                            ?? $this->http->FindSingleNode("ancestor::tr[1]/../following-sibling::tr[ *[{$pos}][{$this->eq($seat)}] ]/*[1]", $seatHeader, true, "/^{$this->patterns['travellerName']}$/u")
                        );
                    }

                    $s->extra()->seat($seat, false, false, $passengerName);
                }
            }
        }
    }

    private function assignProvider($headers): bool
    {
        if (!array_key_exists('from', $headers)) {
            $headers['from'] = '';
        }

        foreach (self::$providerDetect as $prov => $efrom) {
            foreach ($efrom as $email) {
                if (stripos($headers['from'], $email) !== false
                    || $this->http->XPath->query("//img[{$this->contains($email, '@src')}] | //a[{$this->contains($email, '@href')}] | //tr[{$this->contains($email)}]")->length > 0
                ) {
                    $this->providerCode = $prov;

                    return true;
                }
            }
        }

        $companies = [
            'copaair' => ['ESTRELATUR'],
            'sbtur'   => ['ALL WAYS TRAVEL'],
        ];

        foreach ($companies as $prov => $phrases) {
            foreach ($phrases as $phrase) {
                if ($this->http->XPath->query("//tr[ *[4][{$this->eq($this->t('Emissão'))}] ]/following-sibling::tr/*[4]/descendant::text()[normalize-space()][1][{$this->contains($phrase)}]")->length > 0) {
                    $this->providerCode = $prov;

                    return true;
                }
            }
        }

        return false;
    }

    private function assignLang(): bool
    {
        if (!isset(self::$dictionary, $this->lang)) {
            return false;
        }

        foreach (self::$dictionary as $lang => $phrases) {
            if (!is_string($lang) || empty($phrases['flight'])) {
                continue;
            }

            if ($this->http->XPath->query("//tr[ *[normalize-space()][3] ]/*[{$this->eq($phrases['flight'])}]")->length > 0) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
    }

    private function t(string $phrase, string $lang = '')
    {
        if (!isset(self::$dictionary, $this->lang)) {
            return $phrase;
        }

        if ($lang === '') {
            $lang = $this->lang;
        }

        if (empty(self::$dictionary[$lang][$phrase])) {
            return $phrase;
        }

        return self::$dictionary[$lang][$phrase];
    }

    private function normalizeTraveller(?string $s): ?string
    {
        if (empty($s)) {
            return null;
        }

        return preg_replace([
            '/^ADT\s+-\s+(.{2,})$/i',
            '/^(.{2,}?)\s+(?:MSTR|MRS|MR|MS)[.\s]*$/i',
            '/^([^\/]+?)(?:\s*[\/]+\s*)+([^\/]+)$/',
        ], [
            '$1',
            '$1',
            '$2 $1',
        ], $s);
    }

    private function normalizeDate($date, $correct = false)
    {
        $year = date('Y', $this->date);
        // $this->logger->debug('$date in = '.print_r( $date,true));
        $in = [
            '#^\s*(\d{1,2})\s*(\w+)\s*(\d+:\d+)\s*$#', //23 Out 12:10
        ];
        $out = [
            '$1 $2 ' . $year . ' $3',
        ];
        $str = preg_replace($in, $out, $date);
        // $this->logger->debug('$date out = '.print_r( $date,true));

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        if (preg_match("#\b\d{4}\b#", $str)) {
            $str = strtotime($str);

            if ($correct && $str < $this->date) {
                $str = strtotime("+1 year", $str);
            }

            return $str;
        }

        return null;
    }

    private function getCurrency($node): ?string
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

    private function contains($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';

            return 'contains(normalize-space(' . $node . '),' . $s . ')';
        }, $field)) . ')';
    }

    private function eq($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';

            return 'normalize-space(' . $node . ')=' . $s;
        }, $field)) . ')';
    }

    private function starts($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';

            return 'starts-with(normalize-space(' . $node . '),' . $s . ')';
        }, $field)) . ')';
    }
}
