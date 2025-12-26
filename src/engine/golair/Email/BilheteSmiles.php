<?php

namespace AwardWallet\Engine\golair\Email;

use AwardWallet\Schema\Parser\Common\Flight;
use AwardWallet\Schema\Parser\Email\Email;

class BilheteSmiles extends \TAccountChecker
{
    public $mailFiles = "golair/it-29585578.eml, golair/it-29730879.eml, golair/it-29855974.eml, golair/it-62981047.eml, golair/it-73112262.eml, golair/it-73179874.eml";

    public $reFrom = ["@comunicado.smiles.", "Smiles"];
    public $reBody = [
        'pt' => [
            ['Localizador Smiles', 'Informações sobre sua reserva', 'solicitou o cancelamento', 'solicitação de cancelamento'],
            ['Passageiro(s)', 'informações importantes sobre o cancelamento', 'abaixo os dados do seu pedido'],
        ],
        'es' => [
            ['Código localizador Smiles', 'Estos son los detalles de tu pasaje y código de reserva', '¡Reservaste un pasaje usando Viaje Fácil!', 'Datos de tu reserva'],
            ['Pasajero'],
        ],
    ];
    public $reSubject = [
        'Bilhete Smiles',
    ];
    public $lang = '';
    public static $dictionary = [
        'pt' => [
            'DIRETO/PARADA'  => ['DIRETO', 'PARADA', 'PARADAS'],
            'PARADA'         => ['PARADA', 'PARADAS'],
            'Ida'            => ['Ida', 'Volta'],
            'Número Smiles:' => ['Número Smiles:', 'Seu número Smiles:', 'Seu número Smiles é:'], // depends on parser golair/TravelStatement
        ],
        'es' => [
            'Localizador Smiles'                    => 'Código localizador Smiles',
            'Localizador na'                        => 'Código localizador',
            'Ida'                                   => ['Ida', 'Volta', 'Vuelta'],
            'DIRETO/PARADA'                         => ['DIRECTO', 'ESCALA'],
            'PARADA'                                => 'ESCALA',
            'Cabine'                                => 'Cabina',
            'Informações sobre sua reserva'         => 'Datos de tu reserva',
            'Você cancelou sua reserva de passagem' => 'Cancelaste tu reserva de pasaje realizada',
            'Passageiro(s)'                         => 'Pasajero',

            'Dinheiro'               => 'Pesos:',
            'Milhas'                 => 'Millas:',
            'Bilhete Ida'            => 'Pasaje aéreo Ida',
            'Taxa de embarque Ida'   => 'Tasas e impuestos Ida',
            'Bilhete Volta'          => 'Pasaje aéreo Vuelta',
            'Taxa de embarque Volta' => 'Tasas e impuestos Vuelta',
            'Total pago'             => 'Total:',

            // Statement (depends on parser golair/TravelStatement)
            'Número Smiles:' => ['Número Smiles:'], // depends on parser golair/TravelStatement
            'Olá,'           => ['Hola,', 'Hola;,'],
            'Categoria:'     => 'Categoría:',
            'Saldo em'       => 'Saldo al',
        ],
    ];

    public function normalizeDate(?string $text): string
    {
        if (!is_string($text) || empty($text)) {
            return '';
        }
        $in = [
            // 31/12/2018 14:05
            '/^\s*(\d{1,2})\/(\d{1,2})\/(\d{4})\s+(\d{1,2}:\d{1,2})\s*$/',
            // 28/12
            '/^(\d{1,2})\/(\d{1,2})$/',
        ];
        $out = [
            '$3-$2-$1 $4',
            '$2/$1',
        ];

        return preg_replace($in, $out, $text);
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();

        if (empty($this->lang)) {
            $this->logger->debug("Can't determine a language!");

            return $email;
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        $dateRelative = strtotime($parser->getDate());
        $this->parseEmail($email, $dateRelative);

        Statement\TravelStatement::parseStatement($email, $this, $dateRelative);

        return $email;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//a[contains(@href,'.smiles.com')] | //img[@alt='GOL' or contains(@src,'.smiles.com') or contains(@src,'smiles-mkt')]")->length > 0) {
            return $this->assignLang();
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

    public function detectEmailByHeaders(array $headers)
    {
        $fromProv = (isset($headers['from']) && $this->detectEmailFromProvider($headers['from']) !== false);

        if (isset($headers["subject"])) {
            foreach ($this->reSubject as $reSubject) {
                if (($fromProv || strpos($reSubject, 'Smiles') !== false)
                    && stripos($headers["subject"], $reSubject) !== false
                ) {
                    return true;
                }
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

    public function t(string $phrase)
    {
        if (!isset(self::$dictionary, $this->lang) || empty(self::$dictionary[$this->lang][$phrase])) {
            return $phrase;
        }

        return self::$dictionary[$this->lang][$phrase];
    }

    /**
     * Formatting over 3 steps:
     * 11 507.00  ->  11507.00
     * 2,790      ->  2790    |    4.100,00  ->  4100,00    |    1'619.40  ->  1619.40
     * 18800,00   ->  18800.00  |  2777,0    ->  2777.0.
     *
     * @param string $s Unformatted string with amount
     * @param string|null $decimals Symbols floating-point when non-standard decimals (example: 1,258.943)
     */
    public function normalizeAmount(?string $s, ?string $decimals = null): ?float
    {
        if (!empty($decimals) && preg_match_all('/(?:\d+|' . preg_quote($decimals, '/') . ')/', $s, $m)) {
            $s = implode('', $m[0]);

            if ($decimals !== '.') {
                $s = str_replace($decimals, '.', $s);
            }
        } else {
            $s = preg_replace('/\s+/', '', $s);
            $s = preg_replace('/[,.\'](\d{3})/', '$1', $s);
            $s = preg_replace('/,(\d{1,2})$/', '.$1', $s);
        }

        return is_numeric($s) ? (float) $s : null;
    }

    public function starts($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'starts-with(normalize-space(' . $node . '),"' . $s . '")';
        }, $field)) . ')';
    }

    public function contains($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'contains(normalize-space(' . $node . '),"' . $s . '")';
        }, $field)) . ')';
    }

    public function opt($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return '';
        }

        return '(?:' . implode('|', array_map(function ($s) {
            return preg_quote($s, '/');
        }, $field)) . ')';
    }

    private function parseEmail(Email $email, &$dateRelative): void
    {
        // email price
        $xpathMoney = "//text()[{$this->eq($this->t('Dinheiro'))}]/ancestor::tr[{$this->contains($this->t('Milhas'))}][1]/following-sibling::tr[{$this->contains($this->t('Total pago'))}]";
        $totalPrice = $this->http->FindSingleNode($xpathMoney . '/td[2]');

        if ($totalPrice === null) {
            $totalPrice = $this->http->FindSingleNode("//tr[count(*[normalize-space()])=2]/*[{$this->starts($this->t('Total pago'))}]/following-sibling::*[normalize-space()]");
        }

        if ($totalPrice !== null) {
            $total = $this->getTotalCurrency($totalPrice);
            $email->price()
                ->total($total['Total'], true, true)
                ->currency($total['Currency']);
            $fullSum = $total['Total'];
            $awards = $this->http->FindSingleNode($xpathMoney . '/td[3]');

            if (!empty($awards)) {
                $email->price()
                    ->spentAwards($this->normalizeAmount($awards) . ' ' . trim($this->t('Milhas'), ':'));
            }
        }

        $xpath = "//tr[not(.//tr)][{$this->starts($this->t('Localizador Smiles'))} or {$this->starts($this->t('Informações sobre sua reserva'))}]";
        //$this->logger->debug("[XPATH]: " . $xpath);
        $itineraries = $this->http->XPath->query($xpath);
        $airs = [];

        foreach ($itineraries as $flightIndex => $root) {
            if (preg_match("/^\s*{$this->opt($this->t('Localizador Smiles'))}[:\s]+([A-Z\d]{5,})\s*$/", $root->nodeValue, $m)) {
                $pnr = $m[1];
            } elseif (preg_match("/^\s*{$this->opt($this->t('Informações sobre sua reserva'))}\s*$/", $root->nodeValue)) {
                $pnr = 'none';
            } else {
                $pnr = null;
            }
            $airs[$pnr][$flightIndex] = $root;
        }

        foreach ($airs as $pnr => $roots) {
            $r = $email->add()->flight();

            // recordLocator
            if ($pnr === 'none') {
                $r->general()->noConfirmation();
            } else {
                $r->general()->confirmation($pnr);
            }

            if (!empty($this->http->FindSingleNode("(//*[" . $this->contains($this->t("Você cancelou sua reserva de passagem")) . "])[1]"))) {
                $r->general()->cancelled();
            }
            // accountNumber
            $acc = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Número Smiles:'))}]", null, false,
                "/: *([\w\-]+)$/");

            if (!empty($acc)) {
                $r->program()
                    ->account($acc, false);
            }

            $passengers = [];
            $currency = '';
            $spentAwardsSum = $costSum = $taxSum = 0.0;

            foreach ($roots as $flightIndex => $root) {
                // get Direct
                $firstOnly = $itineraries->length > 1 ? '[1]' : false; // crutch for it-62981047.eml
                $xpathDirect = "following::text()[{$this->starts($this->t('Ida'))}]{$firstOnly}/ancestor::*[ following-sibling::table[normalize-space()] ][1]/following-sibling::table[{$this->contains($this->t('DIRETO/PARADA'))}][1]";
                $directs = $this->http->XPath->query($xpathDirect, $root);
                $tag = 'table';

                if ($directs->length === 0) {
                    $xpathDirect = "following::text()[{$this->starts($this->t('Ida'))}]{$firstOnly}/ancestor::*[ following-sibling::div[normalize-space()] ][1]/following-sibling::div[{$this->contains($this->t('DIRETO/PARADA'))}][1]";
                    $directs = $this->http->XPath->query($xpathDirect, $root);
                    $tag = 'div';
                }

                if ($directs->length !== 1 && $firstOnly) {
                    $this->logger->debug("other format");

                    return;
                }
                $this->logger->debug("[+DIRECT]: " . $xpathDirect);

                foreach ($directs as $direct) {
                    $this->parseSegments($r, $direct, $flightIndex, $root, $tag, $passengers, $currency, $costSum, $spentAwardsSum, $taxSum);
                }
            }

            if (!empty($passengers = array_filter(array_unique($passengers)))) {
                $r->general()
                    ->travellers($passengers);
            }

            if (!empty($spentAwardsSum)) {
                $r->price()
                    ->spentAwards($spentAwardsSum . ' ' . trim($this->t('Milhas'), ':'));
            }

            if (!empty($currency)) {
                $r->price()
                    ->cost($costSum)
                    ->currency($currency);
            }

            if (!empty($taxSum)) {
                $r->price()
                    ->tax($taxSum);
            }

            if (count($airs) === 1 && isset($fullSum)) {
                $r->price()->total($fullSum);
            }
        }

        if (count($email->getItineraries()) && $email->getItineraries()[0]->getType() === 'flight'
            && count($email->getItineraries()[0]->getSegments())
            && ($depDateFirst = $email->getItineraries()[0]->getSegments()[0]->getDepDate())
        ) {
            $dateRelative = $depDateFirst;
        }
    }

    private function parseSegments(Flight $r, \DOMNode $segmentsGroup, int $flightIndex, \DOMNode $root, string $tag, &$passengers, &$currency, &$costSum, &$spentAwardsSum, &$taxSum): void
    {
        // get count segments
        $cntSegs = $this->http->FindSingleNode("./descendant::text()[normalize-space()!=''][{$this->contains($this->t('DIRETO/PARADA'))}]",
            $segmentsGroup, false, "/(\d+)\s+{$this->opt($this->t('PARADA'))}/");
        $cntSegs = empty($cntSegs) ? 1 : $cntSegs + 1;
        $num = $cntSegs + 2;

        // get Passengers
        $xpathPax = "following-sibling::table[normalize-space()!=''][{$num}][{$this->contains($this->t('Passageiro(s)'))}]";
        $paxRoots = $this->http->XPath->query($xpathPax, $segmentsGroup);

        if ($paxRoots->length === 0) {
            $xpathPax = "following-sibling::div[normalize-space()!=''][{$num}][{$this->contains($this->t('Passageiro(s)'))}]";
            $paxRoots = $this->http->XPath->query($xpathPax, $segmentsGroup);
        }

        if ($paxRoots->length === 0) {
            $num += 2;
            $xpathPax = "following-sibling::table[normalize-space()!=''][{$num}][{$this->contains($this->t('Passageiro(s)'))}]";
            $paxRoots = $this->http->XPath->query($xpathPax, $segmentsGroup);
        }

        if ($paxRoots->length === 0 && empty($this->http->FindSingleNode("(./following-sibling::table[normalize-space()!=''][{$this->contains($this->t('Passageiro(s)'))}])[1]"))) {
            $xpathPax = "(./following::tr[not(.//tr) and descendant::*[1][{$this->eq($this->t('Passageiro(s)'))}]])[1]/ancestor::table[count(.//tr[not(.//tr)])=1]/following::text()[normalize-space()][1]/ancestor::*[descendant::text()[normalize-space()][1][{$this->eq($this->t('Passageiro(s)'))}]]";
            $paxRoots = $this->http->XPath->query($xpathPax, $segmentsGroup);
        }

        if ($paxRoots->length === 0 && empty($this->http->FindSingleNode("(./following-sibling::table[normalize-space()!=''][position()<={$num}][{$this->contains($this->t('Passageiro(s)'))}])[1]"))) {
            $xpathPax = "following-sibling::table[normalize-space()!=''][last()]/following::table[normalize-space()!=''][1][{$this->contains($this->t('Passageiro(s)'))} and count(.//tr[not(.//tr)=1])]/following::table[normalize-space()!=''][2]/descendant::text()[normalize-space()][1]/ancestor::table[1]";
        }

        $paxRoots = $this->http->XPath->query($xpathPax, $segmentsGroup);
        $paxRoot = $paxRoots->length > 0 ? $paxRoots->item(0) : null;

        // differents position of tables (xpath), so get text
        $paxSector = $paxRoot === null ? '' : text($this->http->FindHTMLByXpath('.', null, $paxRoot));

        if (strpos($paxSector, 'Assento') !== false) {
            $paxSector = $this->re("/\s+Trecho[^\n]*(.+)/s", strstr($paxSector, 'Bagagens', true));
        } elseif (strpos($paxSector, 'Bagagem') !== false) {
            $paxSector = $this->re("/\s+Bagagem\n(?:[^\n])*\n*(.+?)(?:Outras informações importantes|Volta|$)/s",
                $paxSector);
        }
        $paxSector = preg_replace("/(.*Equipaje\s*incluido\s*en\s*tu\s*pasaje\s+)/s", '', $paxSector);
        $paxSector = str_replace('assentos', '', $paxSector);
        $paxSector = str_replace('asientos', '', $paxSector);

        if (preg_match_all("/^[\t ]*?([[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]])(?:\s{5,}|$)/mu", $paxSector, $m)) {
            $passengers = array_merge($passengers, array_unique(array_filter(array_map(
                function ($v) {
                    if (preg_match("/\b(Não elegivel|gratuita)\b/u", $v)) {
                        return null;
                    }

                    return trim($v);
                }, $m[1]))));
        }
        $this->logger->debug("[---PAX]: " . $xpathPax);

        // price of flight
        if ($flightIndex === 0) {
            $costText = $this->t('Bilhete Ida');
            $taxText = $this->t('Taxa de embarque Ida');
        } else {
            $costText = $this->t('Bilhete Volta');
            $taxText = $this->t('Taxa de embarque Volta');
        }
        $sum = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Dinheiro'))}]/ancestor::tr[{$this->contains($this->t('Milhas'))}][1]/following-sibling::tr[{$this->contains($costText)}]/td[2]");

        if (!empty($sum)) {
            $total = $this->getTotalCurrency($sum);
            $currency = $total['Currency'];
            $costSum += $total['Total'];
        }
        $awards = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Dinheiro'))}]/ancestor::tr[{$this->contains($this->t('Milhas'))}][1]/following-sibling::tr[{$this->contains($costText)}]/td[3]");

        if (!empty($awards)) {
            $spentAwardsSum += str_replace('.', '', $awards);
        }
        $awards = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Dinheiro'))}]/ancestor::tr[{$this->contains($this->t('Milhas'))}][1]/following-sibling::tr[{$this->contains($taxText)}]/td[3]");

        if (!empty($awards)) {
            $spentAwardsSum += str_replace('.', '', $awards);
        }
        $sum = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Dinheiro'))}]/ancestor::tr[{$this->contains($this->t('Milhas'))}][1]/following-sibling::tr[{$this->contains($taxText)}]/td[2]");

        if (!empty($sum)) {
            $total = $this->getTotalCurrency($sum);
            $taxSum += $total['Total'];
        }

        // parse Segments info
        for ($i = 1; $i <= $cntSegs; $i++) {
            $xpathSeg = "following-sibling::{$tag}[string-length(normalize-space())>1][{$i}]";
            $this->logger->debug("[+SEG]: " . $xpathSeg);
            $rootSeg = $this->http->XPath->query($xpathSeg, $segmentsGroup);

            if ($rootSeg->length == 0) {
                $xpathSeg = ($i == 1 ? '' : "following-sibling::{$tag}[string-length(normalize-space())>1][" . ($i - 1) . "]") . "/following::{$tag}[string-length(normalize-space())>1][descendant::tr[ *[3] ]][{$i}]";
                $rootSeg = $this->http->XPath->query($xpathSeg, $segmentsGroup);
            }

            if ($rootSeg->length !== 1) {
                $this->logger->debug("other format (segments)");

                return;
            } else {
                $rootSeg = $rootSeg->item(0);
            }
            $s = $r->addSegment();

            $airline = $this->http->FindSingleNode("./descendant::img/@alt", $rootSeg);
            $airlineArr = [ucwords($airline), ucfirst($airline), strtolower($airline), strtoupper($airline)];
            $cnt = $cntSegs * 2;
            $sConfirmation = $this->http->FindSingleNode("following::text()[normalize-space()][position()<{$cnt}][({$this->starts($this->t('Localizador na'))}) and ({$this->contains($airlineArr)})]/following::text()[normalize-space()][1]", $root, true, '/^[A-Z\d]{5,6}$/');

            if ($sConfirmation) {
                $s->airline()->confirmation($sConfirmation);
            }

            $col1 = implode("\n", $this->http->FindNodes("descendant::tr[ *[3] ]/*[normalize-space()][position()=1 and position()!=last()]", $rootSeg));
            $col2 = implode("\n", $this->http->FindNodes("descendant::tr[ *[3] ]/*[normalize-space()][position()!=1 and position()!=last()]/descendant::text()[normalize-space()]", $rootSeg));
            $col3 = implode("\n", $this->http->FindNodes("descendant::tr[ *[3] ]/*[normalize-space()][position()>1 and position()=last()]", $rootSeg));

            /*
                SSA
                Salvador
                23/03/2021 13:30
            */
            $pattern = "/^\s*"
                . "(?<code>[A-Z]{3})[ ]*\n+"
                . "[ ]*(?<name>.{3,}?)[ ]*\n+"
                . "[ ]*(?<dateTime>.{10,}?)"
                . "\s*$/";

            // departure
            if (preg_match($pattern, $col1, $m)) {
                $s->departure()
                    ->code($m['code'])
                    ->name($m['name'])
                    ->date2($this->normalizeDate($m['dateTime']));
            } else {
                $this->logger->debug('Departure fields not found!');

                return;
            }

            // airline info
            if (preg_match("/^([A-Z\d][A-Z]|[A-Z][A-Z\d])\s*-\s*(\d+)[-\s]+(.+)$/", $col2, $m)) {
                $s->airline()
                    ->name($m[1])
                    ->number($m[2]);
                $s->extra()->cabin(trim(preg_replace("/(^\s*" . $this->opt($this->t("Cabine")) . "\s+|\s+" . $this->opt($this->t("Cabine")) . "\s*$)/ui", '', $m[3])));
            } elseif (preg_match("/^\s*-\s*(\d{1,5})[-\s]+(.+)$/", $col2, $m)) {
                if (!empty($airline) && !empty($sConfirmation)) {
                    $s->airline()
                        ->name($airline);
                } else {
                    $s->airline()
                        ->noName();
                }
                $s->airline()
                    ->number($m[1]);
                $s->extra()->cabin(trim(preg_replace("/(^\s*" . $this->opt($this->t("Cabine")) . "\s+|\s+" . $this->opt($this->t("Cabine")) . "\s*$)/ui", '', $m[2])));
            } else {
                $this->logger->debug('Flight number not found!');

                return;
            }

            // arrival
            if (preg_match($pattern, $col3, $m)) {
                $s->arrival()
                    ->code($m['code'])
                    ->name($m['name'])
                    ->date2($this->normalizeDate($m['dateTime']));
            } else {
                $this->logger->debug('Arrival fields not found!');

                return;
            }

            // seats
            if (!empty($s->getDepCode()) && !empty($s->getArrCode())) {
                $seekStr = $s->getDepCode() . '/' . $s->getArrCode();
                $seats = $this->http->FindNodes("descendant::text()[contains(.,'{$seekStr}')]/ancestor::td[1]/preceding-sibling::td[1]", $paxRoot);

                if (!empty($seats)) {
                    $s->extra()->seats($seats);
                }
            }

            // duration
            if ($cntSegs === 1) {
                $duration = $this->http->FindSingleNode("./descendant::tr[last()]/descendant::text()[normalize-space()!=''][2]",
                    $segmentsGroup);
                $s->extra()->duration($duration);
            }
        }
    }

    private function assignLang(): bool
    {
        if (isset($this->reBody)) {
            foreach ($this->reBody as $lang => $reBody) {
                if ($this->http->XPath->query("//*[{$this->contains($reBody[0])}]")->length > 0
                    && $this->http->XPath->query("//*[{$this->contains($reBody[1])}]")->length > 0
                ) {
                    $this->lang = substr($lang, 0, 2);

                    return true;
                }
            }
        }

        return false;
    }

    private function getTotalCurrency($node): array
    {
        $tot = null;
        $cur = null;
        $node = str_replace("€", "EUR", $node);
        $node = str_replace("R$", "BRL", $node);
//        $node = str_replace("$", "USD", $node);
        $node = str_replace("£", "GBP", $node);

        if (preg_match("#^\s*(?<c>\D{1,5})\s*(?<t>\d[\.\d\,\s]*\d*)\s*$#", $node, $m)
            || preg_match("#^\s*(?<t>\d[\.\d\,\s]*\d*)\s*(?<c>\D{1,5})\s*$#", $node, $m)
        ) {
            $cur = trim($m['c']);
            $tot = $this->normalizeAmount($m['t']);
        } elseif (preg_match("#^\s*(?<c>\D{1,5})\s*$#", $node, $m)) {
            $cur = trim($m['c']);
        }

        return ['Total' => $tot, 'Currency' => $cur];
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function eq($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'normalize-space(' . $node . ')="' . $s . '"';
        }, $field)) . ')';
    }
}
