<?php

namespace AwardWallet\Engine\golair\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;

class ConfiraOsDetalhes extends \TAccountChecker
{
    public $mailFiles = "golair/it-162659106.eml, golair/it-162848596.eml, golair/it-748217753.eml";

    public $detectFrom = ['transacional@comunicado.smiles.com.br'];
    public $detectBody = [
        'pt' => [
            'Aqui está a confirmação da sua passagem aérea:',
            'Veja os detalhes do cancelamento do seu voo',
        ],
    ];
    public $detectSubject = [
        'Confira os detalhes do seu bilhete Smiles.',
    ];
    public $lang = '';
    public static $dictionary = [
        'pt' => [
            'Localizador Smiles:'     => ['Localizador Smiles:'],
            'Número de voo:'          => ['Número de voo:'],
            'cancelamento do seu voo' => 'cancelamento do seu voo',
        ],
    ];

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
        if ($this->http->XPath->query("//a[contains(@href,'.smiles.com')] | //img[@alt='GOL' or contains(@src,'.smiles.com') or contains(@src,'smiles-mkt')]")->length === 0) {
            return false;
        }

        foreach ($this->detectBody as $dBody) {
            if ($this->http->XPath->query("//*[" . $this->contains($dBody) . "]")->length > 0) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        foreach ($this->detectFrom as $reFrom) {
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
            foreach ($this->detectSubject as $dSubject) {
                if (($fromProv || strpos($dSubject, 'Smiles') !== false)
                    && stripos($headers["subject"], $dSubject) !== false
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

    private function parseEmail(Email $email, &$dateRelative)
    {
//         email price
//        $xpathMoney = "//text()[{$this->eq($this->t('Dinheiro'))}]/ancestor::tr[{$this->contains($this->t('Milhas'))}][1]/following-sibling::tr[{$this->contains($this->t('Total pago'))}]";
//        $totalPrice = $this->http->FindSingleNode($xpathMoney . '/td[2]');
//
//        if ($totalPrice === null) {
//            $totalPrice = $this->http->FindSingleNode("//tr[count(*[normalize-space()])=2]/*[{$this->starts($this->t('Total pago'))}]/following-sibling::*[normalize-space()]");
//        }
//
//        if ($totalPrice !== null) {
//            $total = $this->getTotalCurrency($totalPrice);
//            $email->price()
//                ->total($total['Total'], true, true)
//                ->currency($total['Currency']);
//            $fullSum = $total['Total'];
//            $awards = $this->http->FindSingleNode($xpathMoney . '/td[3]');
//
//            if (!empty($awards)) {
//                $email->price()
//                    ->spentAwards($this->normalizeAmount($awards) . ' ' . trim($this->t('Milhas'), ':'));
//            }
//        }
        $f = $email->add()->flight();

        // General
        $confsSmile = array_unique($this->http->FindNodes("//text()[{$this->eq($this->t('Localizador Smiles:'))}]/following::text()[normalize-space()][1]", null, "/^\s*([A-Z\d]{5,7})\s*$/"));

        foreach ($confsSmile as $conf) {
            $f->general()
                ->confirmation($conf);
        }

        if ($this->http->XPath->query("//text()[{$this->contains($this->t('cancelamento do seu voo'))}]")->length > 0) {
            $f->general()
                ->cancelled();
        }

        $tRoots = $this->http->XPath->query("//tr[*[1][{$this->eq($this->t('Passageiro(s)'))}]]/following::tr[not(.//tr)][normalize-space()][position() < 15]");
        $colCount = 0;
        $travellers = [];

        foreach ($tRoots as $i => $root) {
            $cc = count($this->http->FindNodes("*[normalize-space()]", $root));

            if ($i == 0) {
                $colCount = $cc;
            } elseif ($colCount !== $cc) {
                break;
            }

            $travellers[] = $this->http->FindSingleNode("*[normalize-space()][1]", $root);
        }

        if (count($travellers) > 0) {
            $f->general()
                ->travellers(array_unique($travellers));
        }

        $xpath = "//text()[{$this->eq($this->t('Número de voo:'))}]/ancestor::*[{$this->contains($this->t('Cabine:'))}][1]";
        $this->logger->debug("[XPATH]: " . $xpath);
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $i => $root) {
            $s = $f->addSegment();

            // Airline
            $node = $this->http->FindSingleNode(".//text()[{$this->eq($this->t('Número de voo:'))}]/following::text()[normalize-space()][1]", $root);

            if (preg_match("/^\s*(?<al>[A-Z][A-Z\d]|[A-Z\d][A-Z])[-\s]*(?<fn>\d{1,5})\s*$/", $node, $m)) {
                $s->airline()
                    ->name($m['al'])
                    ->number($m['fn'])
                ;

                $conf = $this->http->FindSingleNode("(preceding::text()[{$this->eq($this->t('Localizador cia aérea:'))}])[last()]/following::text()[normalize-space()][1]", $root, true, "/^\s*([A-Z\d]{5,7})\s*$/");

                if (!empty($conf) && !in_array($conf, $confsSmile)) {
                    $s->airline()
                        ->confirmation($conf);
                }
            }

            // Departure
            $node = $this->http->FindSingleNode(".//text()[{$this->eq($this->t('Partida:'))}]/following::text()[normalize-space()][1]", $root);

            if (preg_match("/^\s*(?<name>.+?)\s*\((?<code>[A-Z]{3})\)\s*\D*(?<date>\b\d.+)?\s*$/", $node, $m)) {
                $s->departure()
                    ->code($m['code'])
                    ->name($m['name']);

                if (!empty($m['date'])) {
                    $s->departure()
                        ->date($this->normalizeDate($m['date']));

                    if ($i == 0 && !empty($s->getDepDate())) {
                        $dateRelative = $s->getDepDate();
                    }
                }
            }

            // Arrival
            $node = $this->http->FindSingleNode(".//text()[{$this->eq($this->t('Destino:'))}]/following::text()[normalize-space()][1]", $root);

            if (preg_match("/^\s*(?<name>.+?)\s*\((?<code>[A-Z]{3})\)\s*\D*(?<date>\b\d.+)?\s*$/", $node, $m)) {
                $s->arrival()
                    ->code($m['code'])
                    ->name($m['name']);

                if (!empty($m['date'])) {
                    $s->arrival()
                        ->date($this->normalizeDate($m['date']));
                } else {
                    $s->arrival()
                        ->noDate();
                }
            }

            // Extra
            $s->extra()
                ->cabin($this->http->FindSingleNode(".//text()[{$this->eq($this->t('Cabine:'))}]/following::text()[normalize-space()][1]", $root));

            $route = $s->getDepCode() . '/' . $s->getArrCode();

            if (strlen($route) === 7) {
                $seats = array_filter($this->http->FindNodes("(./following::text()[{$this->eq($this->t('Passageiro(s)'))}])[1]/ancestor::*[position() < 5][" . $this->contains($this->t("Assento")) . "][1]/following::tr[not(.//tr)][normalize-space()][position() < 20]//td[normalize-space()='" . $route . "']/following-sibling::td[normalize-space()][1]",
                    $root, "/^\s*(\d{1,3}[A-Z])\s*$/"));

                if (!empty($seats)) {
                    foreach ($seats as $seat) {
                        $pax = $this->http->FindSingleNode("//text()[{$this->eq($seat)}]/ancestor::tr[1][{$this->contains($s->getDepCode() . '/' . $s->getArrCode())}]/descendant::td[1]");

                        if (!empty($pax)) {
                            $s->extra()
                                ->seat($seat, false, false, $pax);
                        } else {
                            $s->extra()
                                ->seat($seat);
                        }
                    }
                }
            }
        }

        // Price
        if ($this->http->FindSingleNode("//text()[{$this->starts($this->t('Total de passageiros'))}]/ancestor::tr[1]/following::tr[{$this->starts($this->t('Bilhete '))}]")) {
            $totalMiles = $this->http->FindSingleNode("//td[not(.//td)][{$this->eq($this->t('Total pago'))}]/following-sibling::td[{$this->contains($this->t('milhas'))}]");

            if (!empty($totalMiles)) {
                $f->price()
                    ->spentAwards($totalMiles);
            }

            $totalCash = $this->http->FindSingleNode("//td[not(.//td)][{$this->eq($this->t('Total pago'))}]/following-sibling::td[not({$this->contains($this->t('milhas'))})][normalize-space()][last()]");

            if (preg_match("#^\s*(?<curr>[^\d\s]{1,5})\s*(?<amount>\d[\d\., ]*)\s*$#", $totalCash, $m)
                || preg_match("#^\s*(?<amount>\d[\d\., ]*)\s*(?<curr>[^\d\s]{1,5})\s*$#", $totalCash, $m)) {
                $f->price()
                    ->total($this->amount($m['amount']))
                    ->currency($this->currency($m['curr']));
            }
        }
    }

    private function assignLang(): bool
    {
        foreach (self::$dictionary as $lang => $dict) {
            if (!empty($dict['Localizador Smiles:']) && $this->http->XPath->query("//*[{$this->contains($dict['Localizador Smiles:'])}]")->length > 0
                && ((!empty($dict['Número de voo:']) && $this->http->XPath->query("//*[{$this->contains($dict['Número de voo:'])}]")->length > 0)
                || (!empty($dict['cancelamento do seu voo']) && $this->http->XPath->query("//*[{$this->contains($dict['cancelamento do seu voo'])}]")->length > 0))
            ) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
    }

    private function normalizeDate(?string $date)
    {
        if (empty($date)) {
            return null;
        }
        $in = [
            // 31/12/2018 14:05
            '/^\s*(\d{1,2})\/(\d{1,2})\/(\d{4})\s+(\d{1,2}:\d{1,2})\s*$/',
        ];
        $out = [
            '$3-$2-$1 $4',
        ];
        $date = preg_replace($in, $out, $date);

        return strtotime($date);
    }

    private function amount(?string $price): ?float
    {
        $price = PriceHelper::cost($price, '.', ',');

        return is_numeric($price) ? (float) $price : null;
    }

    private function currency($s)
    {
        if ($code = $this->re("#^\s*([A-Z]{3})\s*$#", $s)) {
            return $code;
        }
        $sym = [
            'R$' => 'BRL',
        ];

        foreach ($sym as $f => $r) {
            if ($s == $f) {
                return $r;
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
