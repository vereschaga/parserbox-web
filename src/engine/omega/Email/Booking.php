<?php

namespace AwardWallet\Engine\omega\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Common\FlightSegment;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class Booking extends \TAccountChecker
{
    public $mailFiles = "omega/it-12151427.eml, omega/it-12186956.eml, omega/it-12390166.eml, omega/it-12652678.eml, omega/it-12668352.eml, omega/it-12676133.eml, omega/it-12718409.eml, omega/it-12719691.eml, omega/it-13099279.eml";

    public $reFrom = ["omegaflightstore.com"];
    public $reBody = [
        'en'  => ['Omega Flight Store', 'Online Booking Acknowledgement'],
        'en2' => ['Omega Travel', 'Online Booking Acknowledgement'],
        'es'  => ['Omega Flight Store', 'Su reserva confirmada en línea'],
        'es2' => ['Omega Flight Store', 'Recepción de reserva en línea'],
    ];
    public $reSubject = [
        'Your ticket at Omega Flight Store has been issued',
        'Your Omega Flight Store Acknowledgement',
        'Su boleto en Omega Flight Store se ha emitido',
        'Su Pedido de Omega Flight Store',
        'Your OmegaFlightstore.com Acknowledgement',
    ];
    public $lang = '';
    public static $dict = [
        'en' => [
            'Email Address:' => ['Email Address:', 'Email:', 'Email Address'],
            'From'           => ['From', 'Via:'],
            'to :'           => ['to :', 'To:', 'Via:'],
            'Adults'         => ['Adults', 'Adult'],
            'Total'          => ['Total', 'Total:'],
            'Card Fee'       => ['Card Fee', 'Credit Card Charge (Payment 1)'],
        ],
        'es' => [
            'Email Address:'                                                    => ['Dirección de correo electrónico:', 'Correo electrónico:'],
            'Your online booking is being processed, but is not yet confirmed!' => 'Su reserva está siendo procesada, ¡pero aún no está confirmada!',
            'is not yet confirmed'                                              => 'aún no está confirmada',
            'Main Booking Reference'                                            => 'Referencia principal de la reserva',
            'Airline Booking Reference:'                                        => 'Referencia de la aerolínea:',
            'Online Booking Acknowledgement'                                    => ['Su reserva confirmada en línea', 'Recepción de reserva en línea'],
            'Ticket Number'                                                     => 'Numero de ticket',
            'Passenger'                                                         => 'Pasajero',
            'Passenger Names'                                                   => 'Nombre de los pasajeros',
            'Flight:'                                                           => 'Vuelo:',
            'From'                                                              => 'Desde',
            'to :'                                                              => ['a :', 'A :'],
            'Departs'                                                           => ['Fecha De Salida', 'Fecha de salida'],
            'Arrives'                                                           => ['Fecha De Llegada', 'Fecha de llegada'],
            'Price Breakdown'                                                   => ['Información Sobre Precios', 'Desglose de tarifa'],
            'Total'                                                             => ['Total', 'Precio total'],
            'Card Fee'                                                          => 'Comisión de tarjeta',
            'Adults'                                                            => ['Adultos', 'Adulto(s)'],
            'fare'                                                              => 'Tarifas',
            'taxes'                                                             => 'Impuestos',
        ],
    ];
    private $tickets;
    private $pax;

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        if (!$this->assignLang()) {
            $this->logger->debug('can\'t determine a language');

            return $email;
        }

        $a = explode('\\', __CLASS__);
        $class = end($a);
        $email->setType($class . ucfirst($this->lang));
        $email->setProviderCode('omega');
        $email->setUserEmail($this->http->FindSingleNode("//text()[{$this->eq($this->t('Email Address:'))}]/following::text()[normalize-space(.)!=''][1]"));

        $ta = $email->ota();

        if ($this->http->XPath->query("//text()[{$this->contains($this->t('Your online booking is being processed, but is not yet confirmed!'))}]")->length === 0) {
            $ta->confirmation($this->http->FindSingleNode("//text()[{$this->starts($this->t('Main Booking Reference'))}]/following::text()[normalize-space(.)!=''][1]",
                null, true, "#([A-Z\d]{5,})#"), $this->t('Main Booking Reference'), true);
        }
        $node = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Online Booking Acknowledgement'))}]");

        if (preg_match("#({$this->opt($this->t('Online Booking Acknowledgement'))})[\s\-]+([A-Z\d]{5,})#", $node,
            $m)) {
            $ta->confirmation($m[2], $m[1]);
        }

        $this->tickets = [];
        $nodes = $this->http->XPath->query("//text()[{$this->eq($this->t('Ticket Number'))}]/ancestor::tr[1][{$this->contains($this->t('Passenger'))}]/following-sibling::tr");

        foreach ($nodes as $v) {
            $pax[] = $this->http->FindSingleNode("./td[2]", $v);
            $ticketsP = explode("|", $this->http->FindSingleNode("./td[1]", $v));
            $this->tickets = array_merge($this->tickets, $ticketsP);
        }
        $this->pax = [];
        $nodes = $this->http->XPath->query("//text()[{$this->eq($this->t('Passenger Names'))}]/ancestor::*[self::tr or self::div or self::h3][1]/following::table[normalize-space(.)!=''][1]/descendant::tr[position()>1]");

        if ($this->http->XPath->query("//text()[{$this->eq($this->t('Passenger Names'))}]/ancestor::*[self::tr or self::div or self::h3][1]/following::table[normalize-space(.)!=''][1]/descendant::tr[position()=1]/descendant::*[normalize-space(.)!='']")->length === 5) {
            //Title	First name	Last name	Seat Preference	Meal Preference
            $positionRule = "position()=3 or position()=4";
        } else {
            //	Título	Nombre	Apellido	Segundo Nombre
            $positionRule = "position()>2";
        }

        foreach ($nodes as $v) {
            $this->pax[] = trim(implode(" ", $this->http->FindNodes("./td[{$positionRule}]", $v)));
        }

        if (empty($this->pax) && isset($pax)) {
            $this->pax = $pax;
        }
        $this->pax = array_values(array_filter($this->pax));

        $xpath = "//text()[{$this->eq($this->t('Flight:'))}]/ancestor::table[1][{$this->contains($this->t('From'))}]";

        if ($this->http->XPath->query($xpath)->length > 0) {
            $this->flight($email, $xpath);
        }

        return $email;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//a[contains(@href,'omegaflightstore.')] | //img[contains(@src,'omegaflightstore.')]")->length > 0) {
            return $this->assignLang();
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && isset($headers["subject"])) {
            $flag = false;

            foreach ($this->reFrom as $reFrom) {
                if (stripos($headers['from'], $reFrom) !== false) {
                    $flag = true;
                }
            }

            if ($flag) {
                foreach ($this->reSubject as $reSubject) {
                    if (stripos($headers["subject"], $reSubject) !== false) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        foreach ($this->reFrom as $reFrom) {
            if (stripos($from, $reFrom) !== false) {
                return true;
            }
        }

        return false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        $types = 2;
        $cnt = $types * count(self::$dict);

        return $cnt;
    }

    public function dateStringToEnglish($date)
    {
        if (preg_match('#[[:alpha:]]+#iu', $date, $m)) {
            $monthNameOriginal = $m[0];

            if ($translatedMonthName = MonthTranslate::translate($monthNameOriginal, $this->lang)) {
                return preg_replace("#$monthNameOriginal#i", $translatedMonthName, $date);
            }
        }

        return $date;
    }

    protected function flight(Email $email, $xpath)
    {
        $nodes = $this->http->XPath->query($xpath);

        if ($nodes->length > 0) {
            $this->logger->debug('Segments for flight found by: ' . $xpath);
        }
        $f = $email->add()->flight();

        $sumAdded = false;
        $node = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Price Breakdown'))}]/ancestor::*[self::tr or self::div or self::h3][1]/following::tr[normalize-space(.)!=''][not(.//tr) and ({$this->contains($this->t('fare'))}) and ({$this->contains($this->t('taxes'))})][1]");

        if (preg_match("#^(\d+)\s*{$this->opt($this->t('Adults'))}\s*(.+)\s*{$this->opt($this->t('fare'))}\s*\+\s*(.+)\s*{$this->opt($this->t('taxes'))}\s*\=\s*.+?\s*\=\s*(.+)$#u",
            $node, $m)) {
            $persons = (int) $m[1];
            $totFare = $this->getTotalCurrency($m[2]);
            $totTax = $this->getTotalCurrency($m[3]);
            $totTotal = $this->getTotalCurrency($m[4]);

            if ($totFare['Currency'] == $totTax['Currency'] && $totTax['Currency'] == $totTotal['Currency']) {
                $left = ((float) $totFare['Total'] + (float) $totTax['Total']) * $persons;
                $right = (float) $totTotal['Total'];

                if (trim($left) == trim($right)) {
                    $sumAdded = true;
                    $f->price()
                        ->cost($persons * (float) $totFare['Total'])
                        ->tax($persons * (float) $totTax['Total'])
                        ->total($persons * (float) $totTotal['Total'])
                        ->currency($totFare['Currency']);
                }
            }
        }

        if (!$sumAdded) {
            $tot = $this->getTotalCurrency($this->http->FindSingleNode("//text()[{$this->eq($this->t('Total'))}]/ancestor::td[1]/following-sibling::td[normalize-space(.)!=''][1]"));

            if (!empty((float) $tot['Total'])) {
                $f->price()
                    ->total($tot['Total'])
                    ->currency($tot['Currency']);
            }
        }

        $node = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Card Fee'))}]/ancestor::td[1]/following-sibling::td[normalize-space(.)!=''][1]");
        $tot = $this->getTotalCurrency($node);

        if (!empty((float) $tot['Total'])) {
            $caption = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Card Fee'))}]");
            $f->price()->fee($caption, $tot['Total']);
        }

        if ($this->http->XPath->query("//text()[{$this->contains($this->t('Your online booking is being processed, but is not yet confirmed!'))}]")->length > 0) {
            $f->general()
                ->noConfirmation()
                ->status($this->t('is not yet confirmed'));
        } else {
            if (!empty($rl = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Airline Booking Reference:'))}]/following::text()[normalize-space(.)!=''][1]",
                null, true, "#([A-Z\d]{5,})#"))
            ) {
                $f->general()
                    ->confirmation($rl);
            } else {
                //FE: it-12668352.eml
                if (!empty($rl = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Airline Booking Reference:'))}]/ancestor::td[1]",
                    null, true, "#{$this->opt($this->t('Airline Booking Reference:'))}\s*([A-Z\d]+)$#"))
                ) {
                    $f->general()->noConfirmation();
                }
            }
        }

        $f->issued()
            ->tickets($this->tickets, false);

        $f->general()
            ->travellers($this->pax);

        foreach ($nodes as $i => $v) {
            //inbound - outbound
//            $xpathInOut = "descendant::text()[{$this->starts($this->t('From'))}]/ancestor::table[1][{$this->contains($this->t('Arrives'))}]";
            $xpathInOut = "descendant::text()[{$this->starts($this->t('From'))}]/ancestor::tr[1][{$this->contains($this->t('Departs'))}]";
            $roots = $this->http->XPath->query($xpathInOut, $v);

            if ($roots->length > 0) {
                $this->logger->debug('inbound - outbound for flight found by: ' . $xpath . '/' . $xpathInOut);
            }

            foreach ($roots as $root) {
                $s = $f->addSegment();

                $node = $this->http->FindSingleNode("./preceding::td[1][{$this->contains($this->t('Flight:'))}]",
                    $root);

                if (empty($node)) {
                    $node = $this->http->FindSingleNode("./descendant::td[{$this->contains($this->t('Flight:'))}][1]",
                        $root);
                }

                if (preg_match("#{$this->opt($this->t('Flight:'))}\s+([A-Z\d]{2})\s*(\d+)#", $node, $m)) {
                    $s->airline()
                        ->name($m[1])
                        ->number($m[2]);
                }

                if ($this->http->XPath->query("./descendant::td[({$this->starts($this->t('From'))}) and not(.//td)]/following-sibling::td[1][{$this->contains($this->t('Departs'))}]",
                        $root)->length === 0
                ) {
                    $this->segmentType1($s, $root);
                } else {
                    $this->segmentType2($s, $root);
                }
            }
        }
    }

    private function normalizeDate($date)
    {
        $in = [
            //Friday, November 3, 2017
            '#^[\w\-]+,\s+(\w+)\s+(\d+),\s+(\d{4})$#u',
            //viernes, 16 de noviembre de 2018
            '#^[\w\-]+,\s+(\d+)\s+de\s+(\w+)\s+de\s+(\d{4})$#u',
        ];
        $out = [
            '$2 $1 $3',
            '$1 $2 $3',
        ];
        $str = strtotime($this->dateStringToEnglish(preg_replace($in, $out, $date)));

        return $str;
    }

    private function segmentType1(FlightSegment $s, $root)
    {
        $node = $this->http->FindSingleNode("./descendant::td[({$this->starts($this->t('From'))}) and not(.//td)]/following-sibling::td[1]",
            $root);

        if (preg_match("#(.+)\s*\(\s*([A-Z]{3})\s*\)#", $node, $m)) {
            $s->departure()
                ->name($m[1])
                ->code($m[2]);
        }

        $terminal = $this->http->FindSingleNode("./descendant::td[({$this->starts($this->t('Departs'))}) and not(.//td)]/following-sibling::td[{$this->contains($this->t('Terminal'))}]/following-sibling::td[1]",
            $root);

        if (!empty($terminal)) {
            $s->departure()
                ->terminal($terminal);
        }

        $date = $this->normalizeDate($this->http->FindSingleNode("./descendant::td[({$this->starts($this->t('Departs'))}) and not(.//td)]/following-sibling::td[1]",
            $root));
        $node = $this->http->FindSingleNode("./descendant::td[({$this->starts($this->t('Departs'))}) and not(.//td)]/following-sibling::td[2]",
            $root);

        if (preg_match("#^(\d{2})(\d{2})$#", $node, $m)) {
            $s->departure()
                ->date(strtotime($m[1] . ':' . $m[2], $date));
        } else {
            $s->departure()
                ->date(strtotime($node, $date));
        }

        $node = $this->http->FindSingleNode("./following-sibling::tr[normalize-space(.)!=''][1]/descendant::text()[{$this->starts($this->t('to :'))}]/ancestor::td[1]/following-sibling::td[1]",
            $root);

        if (preg_match("#(.+)\s*\(\s*([A-Z]{3})\s*\)#", $node, $m)) {
            $s->arrival()
                ->name($m[1])
                ->code($m[2]);
        }

        $terminal = $this->http->FindSingleNode("./following-sibling::tr[normalize-space(.)!=''][1]/descendant::td[({$this->starts($this->t('Arrives'))}) and not(.//td)]/following-sibling::td[{$this->contains($this->t('Terminal'))}]/following-sibling::td[1]",
            $root);

        if (!empty($terminal)) {
            $s->arrival()
                ->terminal($terminal);
        }

        $date = $this->normalizeDate($this->http->FindSingleNode("./following-sibling::tr[normalize-space(.)!=''][1]/descendant::td[({$this->starts($this->t('Arrives'))}) and not(.//td)]/following-sibling::td[1]",
            $root));
        $node = $this->http->FindSingleNode("./following-sibling::tr[normalize-space(.)!=''][1]/descendant::td[({$this->starts($this->t('Arrives'))}) and not(.//td)]/following-sibling::td[2]",
            $root);

        if (preg_match("#^(\d{2})(\d{2})$#", $node, $m)) {
            $s->arrival()
                ->date(strtotime($m[1] . ':' . $m[2], $date));
        } else {
            $s->arrival()
                ->date(strtotime($node, $date));
        }
    }

    private function segmentType2(FlightSegment $s, $root)
    {
        $node = $this->http->FindSingleNode("./descendant::td[({$this->starts($this->t('From'))}) and not(.//td)]",
            $root, false, "#{$this->opt($this->t('From'))}[\s:]*(.+)#");

        if (preg_match("#(.+)\s*\(\s*([A-Z]{3})\s*\)#", $node, $m)) {
            $s->departure()
                ->name($m[1])
                ->code($m[2]);
        }

        $terminal = $this->http->FindPreg("#{$this->opt($this->t('Terminal'))}\s*(.+)#", false, $node);

        if (!empty($terminal)) {
            $s->departure()
                ->terminal($terminal);
        }

        $date = $this->normalizeDate($this->http->FindSingleNode("./descendant::td[({$this->starts($this->t('Departs'))}) and not(.//td)]",
            $root, false, "#{$this->opt($this->t('Departs'))}[\s:]*(.+)#"));
        $node = $this->http->FindSingleNode("./descendant::td[({$this->starts($this->t('Departs'))}) and not(.//td)]/following-sibling::td[1]",
            $root);

        if (preg_match("#^(\d{2})(\d{2})$#", $node, $m)) {
            $s->departure()
                ->date(strtotime($m[1] . ':' . $m[2], $date));
        } else {
            $s->departure()
                ->date(strtotime($node, $date));
        }

        $node = $this->http->FindSingleNode("./following-sibling::tr[1]/descendant::text()[{$this->starts($this->t('to :'))}]/ancestor::td[1]",
            $root, false, "#{$this->opt($this->t('to :'))}[\s:]*(.+)#");

        if (preg_match("#(.+)\s*\(\s*([A-Z]{3})\s*\)#", $node, $m)) {
            $s->arrival()
                ->name($m[1])
                ->code($m[2]);
        }

        $terminal = $this->http->FindPreg("#{$this->opt($this->t('Terminal'))}\s*(.+)#", false, $node);

        if (!empty($terminal)) {
            $s->arrival()
                ->terminal($terminal);
        }

        $date = $this->normalizeDate($this->http->FindSingleNode("./following-sibling::tr[1]/descendant::td[({$this->starts($this->t('Arrives'))}) and not(.//td)]",
            $root, false, "#{$this->opt($this->t('Arrives'))}[\s:]*(.+)#"));
        $node = $this->http->FindSingleNode("./following-sibling::tr[1]/descendant::td[({$this->starts($this->t('Arrives'))}) and not(.//td)]/following-sibling::td[1]",
            $root);

        if (preg_match("#^(\d{2})(\d{2})$#", $node, $m)) {
            $s->arrival()
                ->date(strtotime($m[1] . ':' . $m[2], $date));
        } else {
            $s->arrival()
                ->date(strtotime($node, $date));
        }
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function assignLang()
    {
        if (isset($this->reBody)) {
            foreach ($this->reBody as $lang => $reBody) {
                if ($this->http->XPath->query("//*[contains(normalize-space(.),'{$reBody[0]}')]")->length > 0
                    && $this->http->XPath->query("//*[contains(normalize-space(.),'{$reBody[1]}')]")->length > 0
                ) {
                    $this->lang = substr($lang, 0, 2);

                    return true;
                }
            }
        }

        return false;
    }

    private function getTotalCurrency($node)
    {
        $node = str_replace("€", "EUR", $node);
        $node = str_replace("$", "USD", $node);
        $node = str_replace("£", "GBP", $node);
        $tot = '';
        $cur = '';

        if (preg_match("#(?<c>[A-Z]{3})\s*(?<t>\d[\.\d\,\s]*\d*)#", $node,
                $m) || preg_match("#(?<t>\d[\.\d\,\s]*\d*)\s*(?<c>[A-Z]{3})#", $node,
                $m) || preg_match("#(?<c>\-*?)(?<t>\d[\.\d\,\s]*\d*)#", $node, $m)
        ) {
            $m['t'] = preg_replace('/\s+/', '', $m['t']);            // 11 507.00	->	11507.00
            $m['t'] = preg_replace('/[,.](\d{3})/', '$1', $m['t']);    // 2,790		->	2790		or	4.100,00	->	4100,00
            $m['t'] = preg_replace('/^(.+),$/', '$1', $m['t']);    // 18800,		->	18800
            $m['t'] = preg_replace('/,(\d{2})$/', '.$1', $m['t']);    // 18800,00		->	18800.00
            $tot = $m['t'];
            $cur = $m['c'];
        }

        return ['Total' => $tot, 'Currency' => $cur];
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
        }

        return implode(' or ', array_map(function ($s) {
            return 'normalize-space(.)="' . $s . '"';
        }, $field));
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
        }

        return implode(' or ', array_map(function ($s) {
            return 'starts-with(normalize-space(.),"' . $s . '")';
        }, $field));
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
        }

        return implode(' or ', array_map(function ($s) {
            return 'contains(normalize-space(.),"' . $s . '")';
        }, $field));
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
    }
}
