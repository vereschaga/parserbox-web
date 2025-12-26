<?php

namespace AwardWallet\Engine\maxmilhas\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Engine\WeekTranslate;
use PlancakeEmailParser;

class AirTicket extends \TAccountChecker
{
    public $mailFiles = "maxmilhas/it-11496786.eml, maxmilhas/it-11969467.eml, maxmilhas/it-16056490.eml, maxmilhas/it-48717308.eml, maxmilhas/it-52183008.eml, maxmilhas/it-749432822.eml";

    public $lang = '';

    public static $dictionary = [
        'pt' => [
            'otaConfNumber' => ['Concluindo a emissão da passagem da compra'],
            'directions'    => ['Voo de Ida', 'Voo de ida', 'Vôo de ida', 'Voo de Volta', 'Voo de volta', 'Vôo de volta'],
            'cabinVariants' => ['Econômica'],
        ],
    ];

    private $subjects = [
        'pt' => ['Detalhes do voo', 'Sua passagem foi emitida com sucesso - código', 'Compra em análise - código'],
    ];

    private $detects = [
        'Detalhes da Viagem', 'Detalhes da viagem',
    ];

    private $prov = 'maxmilhas';

    /** @var PlancakeEmailParser */
    private $parser;

    public function ParsePlanEmail(PlancakeEmailParser $parser)
    {
        $this->lang = 'pt';

        $this->parser = $parser;
        $class = explode('\\', __CLASS__);

        return [
            'emailType'  => end($class) . ucfirst($this->lang),
            'parsedData' => [
                'Itineraries' => $this->parseEmail(),
            ],
        ];
    }

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->detectEmailFromProvider($headers['from']) !== true) {
            return false;
        }

        foreach ($this->subjects as $phrases) {
            foreach ((array) $phrases as $phrase) {
                if (is_string($phrase) && stripos($headers['subject'], $phrase) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody() ?? $parser->getPlainBody();

        if (false === stripos($body, $this->prov)) {
            return false;
        }

        foreach ($this->detects as $detect) {
            if (false !== stripos($body, $detect)
                || $this->http->XPath->query('//node()[contains(normalize-space(),"' . $detect . '")]')->length > 0
            ) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]maxmilhas\.com\.br/i', $from) > 0;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
    }

    private function parseEmail(): array
    {
        $xpathTime = '(starts-with(translate(normalize-space(),"0123456789：.Hh ","∆∆∆∆∆∆∆∆∆∆::::"),"∆:∆∆") or starts-with(translate(normalize-space(),"0123456789：.Hh ","∆∆∆∆∆∆∆∆∆∆::::"),"∆∆:∆∆"))';

        $patterns = [
            'date'          => '(?:[-[:alpha:]]{2,}\s*,\s*\d{1,2}\s+[[:alpha:]]{3,}|\d{1,2}\s+(?:de\s+)[[:alpha:]]{3,}\s+(?:de\s+)\d{4})', // SEG, 2 JUL  |  Sábado , 23 de novembro de 2024
            'travellerName' => '[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]]', // Mr. Hao-Li Huang
        ];

        /** @var \AwardWallet\ItineraryArrays\AirTrip $it */
        $it = ['Kind' => 'T'];

        $totalPrice = $this->http->FindSingleNode('//tr[normalize-space()="Detalhes da compra:"]/following-sibling::tr[starts-with(normalize-space(),"Valor Total:")]', null, true, "/Valor Total:\s*(.+?)[*\s]*$/");

        if (preg_match('/^(?<currency>[^\d)(]+?)[ ]*(?<amount>\d[,.\'\d]*)$/', $totalPrice, $m)) {
            // R$ 2.489,22
            $currency = $this->normalizeCurrency($m['currency']);
            $currencyCode = preg_match('/^[A-Z]{3}$/', $currency) ? $currency : null;
            $it['Currency'] = $currency;
            $it['TotalCharge'] = PriceHelper::parse($m['amount'], $currencyCode);
        }

        $otaConfirmation = $this->http->FindSingleNode("//text()[{$this->contains($this->t('otaConfNumber'))}]/following::text()[normalize-space()][1]", null, true, '/^[-A-Z\d]{5,30}$/');

        if ($otaConfirmation) {
            $it['TripNumber'] = $otaConfirmation;
        }

        $confirmation = null;
        $locators = $this->http->FindNodes("descendant::text()[starts-with(normalize-space(),'Localizador')]/following::text()[normalize-space()][1]", null, '/^[A-Z\d]{5,}$/');
        $locators = array_values(array_unique(array_filter($locators)));

        if (count($locators) === 1) {
            $confirmation = $locators[0];
            // TODO: added support many Locators
        }

        if (!$confirmation
            && $this->http->XPath->query('//text()[contains(normalize-space(),"Sua compra está em processo de análise.")]')->length > 0
        ) {
            $confirmation = CONFNO_UNKNOWN;
        }
        $it['RecordLocator'] = $confirmation;

        $xpathP = '(self::p or self::div)';

        $travellers = $this->http->FindNodes("//text()[{$this->eq($this->t('Adultos'), "translate(.,':','')")} or {$this->eq($this->t('Crianças'), "translate(.,':','')")}]/ancestor-or-self::*[ following-sibling::*[{$xpathP} and normalize-space()] ][1]/following-sibling::*[{$xpathP} and normalize-space()]", null, "/^({$patterns['travellerName']})(?:\s+-|$)/u");
        $travellers = array_filter($travellers);

        if (count($travellers) === 0) {
            // it-749432822.eml
            $travellersTexts = $this->http->FindNodes("//*[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->starts($this->t('Adultos'))} or {$this->starts($this->t('Crianças'))}] ]/*[normalize-space()][2][not(contains(.,':'))]");

            foreach ($travellersTexts as $tText) {
                $travellers = array_merge($travellers, preg_split("/(?:\s*,\s*)+/", $tText));
            }

            foreach ($travellers as $passengerName) {
                if (!preg_match("/^{$patterns['travellerName']}$/u", $passengerName)) {
                    $travellers = [];

                    break;
                }
            }
        }

        if (count($travellers) > 0) {
            $it['Passengers'] = array_map(function ($item) {
                return $this->normalizeTraveller($item);
            }, $travellers);
        }

        $xpath = "//tr[ *[{$this->eq($this->t('Partida'), "translate(.,':','')")}] and *[{$this->eq($this->t('Chegada'), "translate(.,':','')")}] ]/following-sibling::tr[normalize-space() and count(*)>1]";
        $roots = $this->http->XPath->query($xpath);

        if (0 === $roots->length) {
            $this->logger->debug("Segments did not found by xpath: {$xpath}");

            // it-749432822.eml
            $xpath = "//tr[ *[2][{$xpathTime}] and *[4][{$xpathTime}] ]";
            $roots = $this->http->XPath->query($xpath);
        }

        if (0 === $roots->length) {
            $this->logger->debug("Segments did not found by xpath: {$xpath}");
        }

        foreach ($roots as $root) {
            /** @var \AwardWallet\ItineraryArrays\AirTripSegment $seg */
            $seg = [];

            if (preg_match('/([A-Z][A-Z\d]|[A-Z\d][A-Z])[ -]*(\d+)$/', $this->http->FindSingleNode('td[1]', $root), $m)) {
                $seg['AirlineName'] = $m[1];
                $seg['FlightNumber'] = $m[2];
            }

            $times = [];
            $td = 4;

            if (3 === $this->http->XPath->query("(//tr[contains(normalize-space(.), 'Partida') and contains(normalize-space(.), 'Chegada') and not(.//tr)]/following-sibling::tr[normalize-space(.)])[1]/td")->length) {
                $td = 3;
            }

            foreach ([
                'Dep' => $this->http->FindSingleNode('td[2]', $root),
                'Arr' => $this->http->FindSingleNode("td[last()]", $root),
            ] as $key => $value) {
                if (preg_match('/(\d{1,2}:\d{2})\s*([A-Z]{3})/', $value, $m)) {
                    $times[$key] = $m[1];
                    $seg[$key . 'Code'] = $m[2];
                }
            }

            $date = null;

            if (!empty($seg['DepCode']) && !empty($seg['ArrCode'])) {
                $date = $this->normalizeDate($this->http->FindSingleNode("preceding::*[ ../self::tr and {$this->eq($this->t('directions'), "translate(.,':','')")} and following-sibling::*[normalize-space()] ][1]/following-sibling::*[normalize-space()][1]", $root, true, "/\b({$patterns['date']})$/u"));
            }

            if (2 === count($times) && !empty($date)) {
                $seg['DepDate'] = strtotime($times['Dep'], $date);
                $seg['ArrDate'] = strtotime($times['Arr'], $date);
            }

            if (4 === $td) {
                $seg['Duration'] = $this->http->FindSingleNode('td[3]', $root, true, "/^\d.+$/");
            }

            $xpathSegBottom = "following-sibling::tr[not(contains(normalize-space(),'Espera de'))][1]";

            $class = $this->http->FindSingleNode($xpathSegBottom . "/descendant::text()[{$this->eq($this->t('Classe deste trecho'), "translate(.,':','')")}]/following::text()[normalize-space()][1]", $root, true, "/^{$this->opt($this->t('cabinVariants'))}$/u")
            ?? $this->http->FindSingleNode("ancestor::table[1]/following::text()[normalize-space()][position()<3][{$this->starts($this->t('Classe deste trecho'))}]", $root, true, "/^{$this->opt($this->t('Classe deste trecho'))}[: ]+({$this->opt($this->t('cabinVariants'))})$/u") // it-749432822.eml
            ;

            if ($class) {
                $seg['Cabin'] = $class;
            }

            $operator = $this->http->FindSingleNode($xpathSegBottom . "/descendant::text()[{$this->eq($this->t('Operado por'), "translate(.,':','')")}]/following::text()[normalize-space()][1]", $root, true, "/^[^:]{2,40}$/")
            ?? $this->http->FindSingleNode("ancestor::table[1]/following::text()[normalize-space()][position()<4][{$this->starts($this->t('Operado por'))}]", $root, true, "/^{$this->opt($this->t('Operado por'))}[: ]+([^:]{2,40})$/") // it-749432822.eml
            ;

            if ($operator) {
                $seg['Operator'] = $operator;
            }

            $it['TripSegments'][] = $seg;
        }

        return [$it];
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

    private function opt($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return '';
        }

        return '(?:' . implode('|', array_map(function ($s) {
            return preg_quote($s, '/');
        }, $field)) . ')';
    }

    private function normalizeDate(?string $str)
    {
//        $this->logger->debug($str);
        $regs = [
            // SEG, 2 JUL
            '/^(?<DayWeek>[-[:alpha:]]{2,})\s*,\s*(?<Day>\d{1,2})\s+(?<Month>[[:alpha:]]{3,})$/u',
            // 23 de novembro de 2024
            '/^(?<Day>\d{1,2})\s+(?:de\s+)(?<Month>[[:alpha:]]{3,})\s+(?:de\s+)(?<Year>\d{4})$/u',
        ];

        foreach ($regs as $reg) {
            if (preg_match($reg, $str, $m)) {
                if (!empty($m['Year'])) {
                    $month = MonthTranslate::translate($m['Month'], $this->lang);
                    $date = strtotime($m['Day'] . ' ' . $month . ' ' . $m['Year']);

                    return $date;
                } elseif (!empty($m['DayWeek'])) {
                    $dayOfWeek = WeekTranslate::number1($m['DayWeek'], $this->lang);
                    $month = MonthTranslate::translate($m['Month'], $this->lang);
                    $dateRel = EmailDateHelper::calculateDateRelative($m['Day'] . ' ' . $month, $this, $this->parser);
                    $date = EmailDateHelper::parseDateUsingWeekDay($dateRel, $dayOfWeek);

                    return $date;
                }
            }
        }

        return $str;
    }

    /**
     * @param string $string Unformatted string with currency
     */
    private function normalizeCurrency(string $string): string
    {
        $string = trim($string);
        $currences = [
            // do not add unused currency!
            'BRL' => ['R$'],
        ];

        foreach ($currences as $currencyCode => $currencyFormats) {
            foreach ($currencyFormats as $currency) {
                if ($string === $currency) {
                    return $currencyCode;
                }
            }
        }

        return $string;
    }

    private function normalizeTraveller(?string $s): ?string
    {
        if (empty($s)) {
            return null;
        }

        $namePrefixes = '(?:SRTA|SRA|SR)';

        return preg_replace([
            "/^(.{2,}?)\s+(?:{$namePrefixes}[.\s]*)+$/is",
            "/^(?:{$namePrefixes}[.\s]+)+(.{2,})$/is",
        ], [
            '$1',
            '$1',
        ], $s);
    }
}
