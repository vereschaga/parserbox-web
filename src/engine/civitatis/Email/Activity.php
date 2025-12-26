<?php

namespace AwardWallet\Engine\civitatis\Email;

use AwardWallet\Schema\Parser\Email\Email;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Common\Parser\Util\PriceHelper;

class Activity extends \TAccountChecker
{
    public $mailFiles = "civitatis/it-788212737-es.eml, civitatis/it-783099788-es.eml, civitatis/it-782767583-es.eml";

    private $subjects = [
        'es' => ['Nueva reserva', 'Nuevo mensaje de cliente sobre su reserva']
    ];

    public $lang = '';

    public static $dictionary = [
        'es' => [
            'activity' => ['Actividad'],
            'confNumber' => ['Número de reserva', 'Nï¿½mero de reserva'],
            'city' => 'Ciudad',
            'fullName' => 'Nombre completo',
            'date' => 'Fecha',
            'timeStart' => 'Hora recogida',
            'collectionPoint' => 'Punto de recogida',
            'persons' => 'Personas',
            'adults' => ['Adults', 'Adulti', 'Adultos'],
            'totalPrice' => ['Precio total', 'Precio de venta'],
            'clientInfo' => 'Datos del cliente',
            'name' => 'Nombre',
            'subname' => 'Apellidos',
            'statusPhrases' => 'La reserva ha quedado',
            'statusVariants' => ['confirmada automáticamente', 'confirmada automï¿½ticamente', 'confirmada'],
        ]
    ];

    private function parseEvent(Email $email): void
    {
        $patterns = [
            'time'          => '\d{1,2}(?:[:：]\d{2})?(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?)?', // 4:19PM    |    2:00 p. m.    |    3pm
            'travellerName' => '[[:alpha:]ï¿½][-.\'’[:alpha:]ï¿½ ]*[[:alpha:]ï¿½]', // Mr. Hao-Li Huang
        ];

        $ev = $email->add()->event();
        $ev->type()->event();

        $activityVal = $this->getField('activity');

        $confirmation = $this->http->FindSingleNode("//*[ count(node()[normalize-space() and not(self::comment())])>1 and node()[normalize-space() and not(self::comment())][1][{$this->eq($this->t('confNumber'), "translate(.,':','')")}] ]");

        if (preg_match("/^({$this->opt($this->t('confNumber'))})[:\s]+([-A-Z\d]{4,40})$/", $confirmation, $m)) {
            $ev->general()->confirmation($m[2], $m[1]);
        }

        $date = strtotime($this->normalizeDate($this->getField('date')));
        $timeStart = $this->http->FindSingleNode("//*[ count(node()[normalize-space() and not(self::comment())])>1 and node()[normalize-space() and not(self::comment())][1][{$this->eq($this->t('timeStart'), "translate(.,':','')")}] ]", null, true, "/^{$this->opt($this->t('timeStart'))}[:\s]+({$patterns['time']})(?:\s*\(|$)/");

        if (!$timeStart && preg_match("/^.{2,}?\s+({$patterns['time']})$/u", $activityVal, $m)) {
            $timeStart = $m[1];
        }

        if ($date && $timeStart) {
            $ev->booked()->start(strtotime($timeStart, $date))->noEnd();
        }

        $city = $this->getField('city');
        $address = $this->getField('collectionPoint');

        if ($city && $address && stripos($address, $city) === false) {
            $address = trim($address, ',.;! ') . ', ' . $city;
        }

        $ev->place()->name($activityVal)->address($address);

        $xpathTotalPrice = "tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('totalPrice'))}] ]";
        $personsText = implode("\n", $this->http->FindNodes("//text()[ normalize-space() and preceding::*[{$this->eq($this->t('persons'))}] and following::{$xpathTotalPrice} ]"));
        
        if (preg_match_all("/^(\d{1,3})\s*{$this->opt($this->t('adults'))}/im", $personsText, $adultsMatches)
            && count($adultsMatches[1]) === 1
        ) {
            $ev->booked()->guests($adultsMatches[1][0]);
        }

        $totalPrice = $this->http->FindSingleNode("//{$xpathTotalPrice}/*[normalize-space()][2]", null, true, '/^.*\d.*$/');

        if (preg_match('/^(?<amount>\d[,.‘\'\d ]*?)[ ]*(?<currency>[^\-\d)(]+?)(?:\s*\(|$)/u', $totalPrice, $matches)
            || preg_match('/^(?<currency>[^\-\d)(]+?)[ ]*(?<amount>\d[,.‘\'\d ]*?)(?:\s*\(|$)/u', $totalPrice, $matches)
        ) {
            // 340 US$    |    442,4 € (480 US$)    |    US$ 140.00 (2x70USD)
            $currency = $this->normalizeCurrency($matches['currency']);
            $currencyCode = preg_match('/^[A-Z]{3}$/', $currency) ? $currency : null;
            $ev->price()->currency($currency)->total(PriceHelper::parse($matches['amount'], $currencyCode));
        }

        $traveller = $isNameFull = null;
        $fullName = $this->getField('fullName'); // it-783099788-es.eml

        if (preg_match("/^{$patterns['travellerName']}$/u", $fullName)) {
            $traveller = $fullName;
            $isNameFull = true;
        }

        if (!$traveller) {
            // it-788212737-es.eml
            $travellerParts = [];
            $nameVal = $this->http->FindSingleNode("//*[{$this->eq($this->t('clientInfo'))}]/following::node()[{$this->eq($this->t('name'), "translate(.,':','')")}]/following::text()[normalize-space()][1]", null, true, "/^{$patterns['travellerName']}$/u");
            $subnameVal = $this->http->FindSingleNode("//*[{$this->eq($this->t('clientInfo'))}]/following::node()[{$this->eq($this->t('subname'), "translate(.,':','')")}]/following::text()[normalize-space()][1]", null, true, "/^{$patterns['travellerName']}$/u");
    
            if ($nameVal) {
                $travellerParts[] = $nameVal;
            }
    
            if ($subnameVal) {
                $travellerParts[] = $subnameVal;
            }
    
            if (count($travellerParts) > 0) {
                $traveller = implode(' ', $travellerParts);
                $isNameFull = count($travellerParts) > 1;
            }
        }

        if ($traveller) {
            $ev->general()->traveller($traveller, $isNameFull);
        }

        $status = $this->http->FindSingleNode("//text()[{$this->eq($this->t('statusPhrases'))}]/following::text()[normalize-space()][1]", null, true, "/^({$this->opt($this->t('statusVariants'))})[,.;!\s]*$/iu");

        if ($status) {
            $ev->general()->status($status);
        }
    }

    private function getField(string $name): ?string
    {
        return $this->http->FindSingleNode("//*[ count(node()[normalize-space() and not(self::comment())])>1 and node()[normalize-space() and not(self::comment())][1][{$this->eq($this->t($name), "translate(.,':','')")}] ]", null, true, "/^{$this->opt($this->t($name))}[:\s]+([^:\s].*)$/");
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[.@]civitatis\.com$/i', $from) > 0;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (!array_key_exists('from', $headers) || $this->detectEmailFromProvider(rtrim($headers['from'], '> ')) !== true) {
            return false;
        }

        foreach ($this->subjects as $phrases) {
            foreach ((array)$phrases as $phrase) {
                if (is_string($phrase) && array_key_exists('subject', $headers) && stripos($headers['subject'], $phrase) !== false)
                    return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->detectEmailFromProvider( rtrim($parser->getHeader('from'), '> ') ) !== true
            && $this->http->XPath->query('//*[contains(normalize-space(),"Gracias por confiar en Civitatis") or contains(normalize-space(),"Un saludo,El equipo de civitatis")]')->length === 0
        ) {
            return false;
        }
        return $this->assignLang();
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();
        if ( empty($this->lang) ) {
            $this->logger->debug("Can't determine a language!");
        }
        $email->setType('Activity' . ucfirst($this->lang));

        $this->parseEvent($email);
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

    private function assignLang(): bool
    {
        if ( !isset(self::$dictionary, $this->lang) ) {
            return false;
        }
        foreach (self::$dictionary as $lang => $phrases) {
            if ( !is_string($lang) || empty($phrases['activity']) ) {
                continue;
            }
            if ($this->http->XPath->query("//*[{$this->eq($phrases['activity'], "translate(.,':','')")}]")->length > 0) {
                $this->lang = $lang;
                return true;
            }
        }
        return false;
    }

    private function t(string $phrase, string $lang = '')
    {
        if ( !isset(self::$dictionary, $this->lang) ) {
            return $phrase;
        }
        if ($lang === '') {
            $lang = $this->lang;
        }
        if ( empty(self::$dictionary[$lang][$phrase]) ) {
            return $phrase;
        }
        return self::$dictionary[$lang][$phrase];
    }

    private function eq($field, string $node = ''): string
    {
        $field = (array)$field;
        if (count($field) === 0)
            return 'false()';
        return '(' . implode(' or ', array_map(function($s) use($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';
            return 'normalize-space(' . $node . ')=' . $s;
        }, $field)) . ')';
    }

    private function opt($field): string
    {
        $field = (array)$field;
        if (count($field) === 0)
            return '';
        return '(?:' . implode('|', array_map(function($s) {
            return preg_quote($s, '/');
        }, $field)) . ')';
    }

    /**
     * Dependencies `use AwardWallet\Engine\MonthTranslate` and `$this->lang`
     * @param string|null $text Unformatted string with date
     * @return string|null
     */
    private function normalizeDate(?string $text): ?string
    {
        if ( preg_match('/\b(\d{1,2})\s+(?:de\s+)?([[:alpha:]]{3,})\s+(?:de\s+)?(\d{4})$/u', $text, $m) ) {
            // Viernes, 27 de diciembre de 2024
            $day = $m[1];
            $month = $m[2];
            $year = $m[3];
        }
        if ( isset($day, $month, $year) ) {
            if ( preg_match('/^\s*(\d{1,2})\s*$/', $month, $m) )
                return $m[1] . '/' . $day . ($year ? '/' . $year : '');
            if ( ($monthNew = MonthTranslate::translate($month, $this->lang)) !== false )
                $month = $monthNew;
            return $day . ' ' . $month . ($year ? ' ' . $year : '');
        }
        return null;
    }

    /**
     * @param string $string Unformatted string with currency
     * @return string
     */
    private function normalizeCurrency(string $string): string
    {
        $string = trim($string);
        $currencies = [
            // do not add unused currency!
            'USD' => ['US$'],
        ];
        foreach ($currencies as $currencyCode => $currencyFormats) {
            foreach ($currencyFormats as $currency) {
                if ($string === $currency)
                    return $currencyCode;
            }
        }
        return $string;
    }
}
