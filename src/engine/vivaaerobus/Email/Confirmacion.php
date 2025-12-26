<?php

namespace AwardWallet\Engine\vivaaerobus\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Common\Flight;
use AwardWallet\Schema\Parser\Email\Email;

class Confirmacion extends \TAccountChecker
{
    public $mailFiles = "vivaaerobus/it-118631567.eml, vivaaerobus/it-120645175.eml, vivaaerobus/it-49000427.eml, vivaaerobus/it-49345128.eml, vivaaerobus/it-50371041.eml, vivaaerobus/it-63153503.eml, vivaaerobus/it-201564859.eml";

    public $reFrom = ["@vivaaerobus.com"];
    public $reBody = [
        'en' => ['Prices per flight', 'Price details'],
        'es' => ['Precios por vuelo', 'Detalles de compra'],
    ];
    public $reSubject = [
        'Confirmacion de reservación de VivaAerobus',
    ];
    public $lang = '';
    public static $dict = [
        'en' => [
            'Departure'     => 'Departure',
            'Return'        => 'Return',
            'Adults X'      => ['Adults X', 'Adults x'],
            'Discount'      => ['Discount', 'discount'],
            'Booking Type:' => ['Booking Type:', 'Combo', 'Fare'],
        ],
        'es' => [
            'Departure'          => 'Salida',
            'Return'             => 'Regreso',
            'Reference number:'  => ['Clave de reservación:', 'Clave de reservaciï¿½n:', 'Tiquete electrónico'],
            'Booking Type:'      => ['Modalidad:', 'Combo', 'Tarifa'],
            'Total amount:'      => 'Monto total:',
            'Adults X'           => ['Adultos X', 'Adultos x'],
            'Children X'         => ['Menores X', 'Menores x'],
            'Total fare'         => 'Total tarifa',
            'Discount'           => ['Descuento', 'descuento'],
            'Airport taxes'      => 'Impuestos aeroportuarios',
            'Prices per benefit' => 'Precios por beneficio',
            'Airport charges'    => 'Cargos aeroportuarios',
            'Total taxes'        => 'Impuestos totales',
            'Flight'             => 'Vuelo',
        ],
    ];
    private $keywordProv = 'VivaAerobus';

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        if (!$this->assignLang()) {
            $this->logger->debug('can\'t determine a language');

            return $email;
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        $this->parseEmail($email);

        return $email;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//img[contains(@src,'.vivaaerobus.com')] | //a[contains(@href,'.vivaaerobus.com')]")->length > 0) {
            foreach ($this->reBody as $reBody) {
                if ($this->http->XPath->query("//*[{$this->contains($reBody)}]")->length > 0) {
                    return $this->assignLang();
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

    public function detectEmailByHeaders(array $headers)
    {
        $fromProv = true;

        if (!isset($headers['from']) || self::detectEmailFromProvider($headers['from']) === false) {
            $fromProv = false;
        }

        if (isset($headers["subject"])) {
            foreach ($this->reSubject as $reSubject) {
                if (($fromProv || preg_match("#\b{$this->opt($this->keywordProv)}\b#i", $headers["subject"]) > 0)
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
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    private function parseEmail(Email $email): void
    {
        $xpathNoEmpty = 'string-length(normalize-space())>1';

        $r = $email->add()->flight();

        // General
        $r->general()
            ->confirmation($this->http->FindSingleNode("//text()[{$this->eq($this->t('Reference number:'))}]/following::text()[normalize-space()!=''][2][{$this->eq($this->t('Booking Type:'))}]/preceding::text()[normalize-space()!=''][1]"))
            ->travellers($this->http->FindNodes("//text()[{$this->eq($this->t('Departure'))}]/ancestor::table[count(./descendant::text()[{$this->contains($this->t('Return'))}])=2][1]/following::tr[normalize-space()!=''][count(./td)=3][1]/ancestor::tr[1]/../tr/descendant::tr[1]/td[normalize-space()!='']/descendant::tr[count(./td[normalize-space()])>1][1]/td[1]"),
                true);

        // Price
        $this->collectSums($r);

        // Get seats
        $seats = [];
        $sXpath = "//text()[{$this->eq($this->t('Departure'))}]/ancestor::table[count(./descendant::text()[{$this->contains($this->t('Return'))}])=2][1]/following::tr[normalize-space()!=''][count(./td)=3][1]/ancestor::tr[1]/../tr/descendant::tr[1]/td[normalize-space()!='']/descendant::tr[count(./td[normalize-space()])>1][1]/td";

        foreach (['0' => $sXpath . '[last()-1]', '1' => $sXpath . '[last()]'] as $key => $xpath) {
            foreach ($this->http->XPath->query($xpath) as $sRoot) {
                $sSeats = $this->http->FindNodes(".//td[not(.//td)][normalize-space()]", $sRoot);

                if (!isset($segCount)) {
                    $segCount = count($sSeats);
                }

                if (count($sSeats) !== $segCount) {
                    $seats = [];

                    break 2;
                }

                foreach ($sSeats as $i => $ss) {
                    $seats[$key][$i][] = $ss;
                }
            }
            unset($segCount);
        }
//        $this->logger->debug('$seats = '.print_r( $seats,true));

        // Segments
        $ruleTime = 'contains(translate(normalize-space(),"0123456789：.Hh","∆∆∆∆∆∆∆∆∆∆::::"),"∆:∆∆")';
        $ruleTime2 = 'starts-with(translate(normalize-space(),"0123456789：.Hh","∆∆∆∆∆∆∆∆∆∆::::"),"∆∆:∆∆")';

        $roots = $this->http->XPath->query($xpath = "//img[contains(@src,'arriba_1.png')]/ancestor::tr[2]");

        if ($roots->length === 0) {
            // it-201564859.eml
            $xpathPoint = "not(.//tr) and *[descendant::img and string-length(normalize-space())<2] and *[{$xpathNoEmpty}][last()][{$ruleTime2}]";
            $roots = $this->http->XPath->query($xpath = "//tr[ {$xpathPoint} and ancestor::*[ following-sibling::*[{$xpathNoEmpty}] ][1]/following-sibling::*[{$xpathNoEmpty}][1]/descendant-or-self::tr[{$xpathPoint}] ]/ancestor::tr[1]");
        }
        $this->logger->debug($xpath);

        if ($roots->length !== count($seats)) {
            $seats = [];
        }

        foreach ($roots as $i => $node) {
            $xpathSeg = ".//text()[contains(translate(normalize-space(.),'0123456789','dddddddddd--'),'dddd')]/ancestor::table[1]";
            $segRoots = $this->http->XPath->query($xpathSeg, $node);

            if (isset($seats[$i]) && count($seats[$i]) !== $segRoots->length) {
                $seats = [];
            }

            foreach ($segRoots as $j => $root) {
                $s = $r->addSegment();

                $date = $this->normalizeDate($this->http->FindSingleNode("descendant::tr[count(*[normalize-space()])=2][1]/*[normalize-space()][1]", $root));

                // Airline
                $flight = $this->http->FindSingleNode("descendant::tr[count(*[normalize-space()])=2][1]/*[normalize-space()][2]", $root);

                if (preg_match("/^\s*(?:{$this->opt($this->t('Flight'))}\s+)?([A-Z\d][A-Z]|[A-Z][A-Z\d])\s*(\d{1,5})\s*$/", $flight, $m)) {
                    $s->airline()
                        ->name($m[1])
                        ->number($m[2]);
                }

                $preg = "/^(?<name>.+?)\s*\n+\s*(?:{$this->opt($this->t('Terminal'))}\s+(?<term>\w{1,5})\s*\n+\s*)?(?<date>\d+:.+)/u";

                // Departure
                $depText = join("\n", array_filter($this->http->FindNodes("./following-sibling::table[1]//text()[{$ruleTime}]/ancestor::tr[1]//text()", $root)));

                // Cancún
                // Terminal 4
                // 01:45 PM
                if (preg_match($preg, $depText, $m)) {
                    $s->departure()
                        ->noCode()
                        ->terminal($m['term'], true, false)
                        ->name($m['name'])
                        ->date(strtotime($m['date'], $date));
                }

                // Arrival
                $arrText = join("\n", array_filter($this->http->FindNodes("./following-sibling::table[2]//text()[{$ruleTime}]/ancestor::tr[1]//text()", $root)));

                if (preg_match($preg, $arrText, $m)) {
                    $s->arrival()
                        ->noCode()
                        ->terminal($m['term'], true, false)
                        ->name($m['name'])
                        ->date(strtotime($m['date'], $date));
                }

                // Extra
                if (isset($seats[$i]) && isset($seats[$i][$j])) {
                    $segSeats = array_filter($seats[$i][$j], function ($v) {
                        if (preg_match('/^\s*-+\s*$/', $v)) {
                            return false;
                        }

                        return true;
                    });

                    if (!empty($segSeats)) {
                        $s->extra()->seats($segSeats);
                    }
                }
            }
        }

        if ($roots->count() === 0) {
            $this->logger->debug('save exsamples');

            $xpath = "//text()[{$ruleTime}]/ancestor::tr[count(./descendant::text()[{$ruleTime}])=4][1][.//img]/descendant::text()[contains(normalize-space(), 'Flight')]/ancestor::td[1]";
            $roots = $this->http->XPath->query($xpath);

            foreach ($roots as $i => $root) {
                $s = $r->addSegment();

                // seeats
                $seats = null;
                $seats = $this->http->FindNodes("//text()[normalize-space(.)='Departure']/ancestor::table[count(./descendant::text()[contains(normalize-space(.),'Return')])=2][1]/following::tr[normalize-space()!=''][count(./td)=3][1]/../tr/td[normalize-space()!='']/descendant::text()[normalize-space()!=''][" . ($i + 2) . "]/ancestor::td[1]");

                $date = $this->normalizeDate($this->http->FindSingleNode("./preceding-sibling::td[1]",
                    $root));

                if (!empty($seats)) {
                    $s->extra()->seats($seats);
                }

                // departure
                $s->departure()
                    ->noCode()
                    ->terminal($this->http->FindSingleNode("./following::td[2]/descendant::text()[1]", $root, false, "/{$this->opt($this->t('Terminal'))}\s*(.+)/"), false, true)
                    ->name($this->http->FindSingleNode("./following::td[2]/descendant::text()[1]",
                        $root))
                    ->date(strtotime($this->http->FindSingleNode("./ancestor::tr[1]/following::tr[1]/descendant::td[normalize-space()][2]",
                        $root), $date));

                // arrival
                $s->arrival()
                    ->noCode()
                    ->terminal($this->http->FindSingleNode("./ancestor::tr[1]/following::tr[2]/descendant::text()[2]",
                        $root, false, "/{$this->opt($this->t('Terminal'))}\s*(.+)/"), false, true)
                    ->name($this->http->FindSingleNode("./ancestor::tr[1]/following::tr[2]/descendant::text()[1]",
                        $root))
                    ->date(strtotime($this->http->FindSingleNode("./ancestor::tr[1]/following::tr[2]/descendant::td[normalize-space()][2]",
                        $root), $date));

                // airline
                if (preg_match("/^(?:{$this->opt($this->t('Flight'))}\s+)?(?<name>[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(?<number>\d+)/", $this->http->FindSingleNode("descendant::text()[normalize-space()][1]", $root), $m)) {
                    $s->airline()->name($m['name'])->number($m['number']);
                }
            }
        }
    }

    private function collectSums(Flight $r): void
    {
        // total
        $sum = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Total amount:'))}]/following::text()[normalize-space()!=''][1]");
        $sum = $this->getTotalCurrency($sum);
        $r->price()
            ->total($sum['total'])
            ->currency($sum['currency']);

        // cost
        $costs = $this->http->FindNodes("//text()[{$this->starts($this->t('Adults X'))} or {$this->starts($this->t('Children X'))}]/ancestor::td[1]/following-sibling::td");
        $sum = 0.0;

        foreach ($costs as $cost) {
            $cost = $this->getTotalCurrency($cost);
            $sum += $cost['total'];

            if (($r->getPrice()->getCurrencyCode() && $cost['currency'] !== $r->getPrice()->getCurrencyCode())
                || ($r->getPrice()->getCurrencySign() && $cost['currency'] !== $r->getPrice()->getCurrencySign())
            ) {
                $sum = null;

                break;
            }
        }

        if (isset($sum) && !empty($sum)) {
            $r->price()->cost($sum);
        }

        // discount
        $discounts = $this->http->FindNodes("//text()[{$this->eq($this->t('Total fare'))}]/ancestor::tr[1]/preceding::tr[{$this->contains($this->t('Discount'))}]/td[2][contains(.,'-')]");
        $sum = 0.0;

        foreach ($discounts as $cost) {
            $cost = str_replace("-", '', $cost);
            $cost = $this->getTotalCurrency($cost);
            $sum += $cost['total'];

            if (($r->getPrice()->getCurrencyCode() && $cost['currency'] !== $r->getPrice()->getCurrencyCode())
                || ($r->getPrice()->getCurrencySign() && $cost['currency'] !== $r->getPrice()->getCurrencySign())
            ) {
                $sum = null;

                break;
            }
        }

        if (isset($sum) && !empty($sum)) {
            $r->price()->discount($sum);
        }

        // tax
        $taxes = $this->http->FindNodes("//text()[{$this->eq($this->t('Airport taxes'))}]/ancestor::td[1]/following-sibling::td");
        $sum = 0.0;

        foreach ($taxes as $cost) {
            $cost = $this->getTotalCurrency($cost);
            $sum += $cost['total'];

            if (($r->getPrice()->getCurrencyCode() && $cost['currency'] !== $r->getPrice()->getCurrencyCode())
                || ($r->getPrice()->getCurrencySign() && $cost['currency'] !== $r->getPrice()->getCurrencySign())
            ) {
                $sum = null;

                break;
            }
        }

        if (isset($sum) && !empty($sum)) {
            $r->price()->tax($sum);
        }

        // fees
        $fee = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Prices per benefit'))}]/ancestor::table[1]/following::text()[normalize-space()!=''][1]");
        $fee = $this->getTotalCurrency($fee);

        if (($r->getPrice()->getCurrencyCode() && $fee['currency'] === $r->getPrice()->getCurrencyCode())
            || ($r->getPrice()->getCurrencySign() && $fee['currency'] === $r->getPrice()->getCurrencySign())
        ) {
            $r->price()->fee($this->t('Prices per benefit'), $fee['total']);
        }
        $feeRoots = $this->http->XPath->query("//text()[{$this->eq($this->t('Total taxes'))}]/ancestor::table[count(./following-sibling::table)=1]/following-sibling::table//tr[count(.//tr)=0][count(./td[normalize-space()!=''])=2]");
        $fees = [];

        foreach ($feeRoots as $root) {
            $name = $this->http->FindSingleNode("./td[1]", $root);
            $fee = $this->getTotalCurrency($this->http->FindSingleNode("./td[2]", $root));

            if (($r->getPrice()->getCurrencyCode() && $fee['currency'] !== $r->getPrice()->getCurrencyCode())
                || ($r->getPrice()->getCurrencySign() && $fee['currency'] !== $r->getPrice()->getCurrencySign())
            ) {
                $fees = [];

                break;
            }

            if (isset($fees[$name])) {
                $fees[$name] += $fee['total'];
            }
            $fees[$name] = $fee['total'];
        }

        foreach ($fees as $name => $fee) {
            $r->price()->fee($name, $fee);
        }

        $airportsFee = $this->http->FindSingleNode("//text()[" . $this->eq($this->t("Airport charges")) . "]/ancestor::table[count(./following-sibling::table)=1]/following-sibling::table/descendant::td[not(.//td)][last()]/ancestor::tr[1][preceding::tr[not(.//tr)][1][count(td[normalize-space()]) = 1]]");

        if (!empty($airportsFee)) {
            $fee = $this->getTotalCurrency($airportsFee);

            if (($r->getPrice()->getCurrencyCode() && $fee['currency'] === $r->getPrice()->getCurrencyCode())
                || ($r->getPrice()->getCurrencySign() && $fee['currency'] === $r->getPrice()->getCurrencySign())
            ) {
                $r->price()->fee($this->http->FindSingleNode("//text()[" . $this->eq($this->t("Airport charges")) . "]"), $fee['total']);
            }
        }
    }

    private function normalizeDate($date)
    {
        $this->logger->debug($date);
        $in = [
            // SAB. 30 NOV. 2019
            '/^([-[:alpha:]]+)[.\s]+(\d{1,2})\s+([[:alpha:]]+)[.\s]+(\d{4})$/u',
        ];
        $out = [
            '$2 $3 $4',
        ];
        $this->logger->debug(preg_replace($in, $out, $date));
        $str = strtotime($this->dateStringToEnglish(preg_replace($in, $out, $date)));

        return $str;
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function assignLang(): bool
    {
        foreach (self::$dict as $lang => $words) {
            if (isset($words['Departure'], $words['Return'])) {
                if ($this->http->XPath->query("//*[{$this->contains($words['Departure'])}]")->length > 0
                    && $this->http->XPath->query("//*[{$this->contains($words['Return'])}]")->length > 0
                ) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function dateStringToEnglish($date)
    {
        if (preg_match('#[[:alpha:]]+#iu', $date, $m)) {
            $monthNameOriginal = $m[0];

            if ($translatedMonthName = MonthTranslate::translate($monthNameOriginal, $this->lang)) {
                return preg_replace("#$monthNameOriginal#i", $translatedMonthName, $date);
            }
        }

        return $date;
    }

    private function getTotalCurrency($node): array
    {
        $node = str_replace(["€", "£", "₹", "USD $", "MXN $"], ["EUR", "GBP", "INR", "USD", "MXN"], $node);
        $tot = '';
        $cur = '';

        if (preg_match("#(?<c>[A-Z]{3})\s*(?<t>\d[\.\d\,\s]*\d*)#", $node, $m)
            || preg_match("#(?<t>\d[\.\d\,\s]*\d*)\s*(?<c>[A-Z]{3})#", $node, $m)
            || preg_match("#^(?<c>[^\s\d])\s*(?<t>\d[\.\d\,\s]*\d*)$#", trim($node), $m)
            || preg_match("#^(?<t>\d[\.\d\,\s]*\d*)\s*(?<c>[^\s\d])$#", trim($node), $m)
        ) {
            $cur = $m['c'];
            $currencyCode = preg_match('/^[A-Z]{3}$/', $cur) ? $cur : null;
            $tot = PriceHelper::parse($m['t'], $currencyCode);
        }

        return ['total' => $tot, 'currency' => $cur];
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

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) {
            return 'contains(normalize-space(.),"' . $s . '")';
        }, $field)) . ')';
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
    }
}
