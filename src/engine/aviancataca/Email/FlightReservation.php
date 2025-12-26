<?php

namespace AwardWallet\Engine\aviancataca\Email;

use AwardWallet\Schema\Parser\Email\Email;
use AwardWallet\Common\Parser\Util\PriceHelper;

class FlightReservation extends \TAccountChecker
{
    public $mailFiles = "aviancataca/it-829808387-es.eml, aviancataca/it-831204149-es.eml";

    private $subjects = [
        'es' => ['aquí están los detalles de tu reserva']
    ];

    public $lang = '';

    public static $dictionary = [
        'es' => [
            'confNumber' => ['Código de reserva'],
            'direction' => ['Salida', 'Regreso'],
            'statusPhrases' => ['Tu reserva ha sido'],
            'statusVariants' => ['confirmada'],
            'cabinValues' => ['flex', 'Tarifa XS', 'Tarifa BASIC', 'basic', 'classic'],
        ]
    ];

    private $patterns = [
        'time' => '\d{1,2}[:：]\d{2}(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?)?', // 4:19PM  |  2:00 p. m.
        'travellerName' => '[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]]', // Mr. Hao-Li Huang
    ];

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[.@]avianca\.com$/i', $from) > 0;
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
        if ($this->detectEmailFromProvider($parser->getCleanFrom()) !== true
            && $this->http->XPath->query('//a[contains(@href,".avianca.com/") or contains(@href,"www.avianca.com") or contains(@href,"cambiatuitinerario.avianca.com")]')->length === 0
            && $this->http->XPath->query('//text()[starts-with(normalize-space(),"Avianca") and contains(normalize-space(),"Copyright")]')->length === 0
            && $this->http->XPath->query('//*[normalize-space()="políticas de Avianca"]')->length === 0
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
            return $email;
        }
        $email->setType('FlightReservation' . ucfirst($this->lang));

        $patterns = [
            'travellerName' => '[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]]', // Mr. Hao-Li Huang
        ];

        $f = $email->add()->flight();

        $statusTexts = array_filter($this->http->FindNodes("//text()[{$this->contains($this->t('statusPhrases'))}]", null, "/{$this->opt($this->t('statusPhrases'))}[:\s]+({$this->opt($this->t('statusVariants'))})(?:\s*[,.;:!?]|$)/i"));

        if (count(array_unique(array_map('mb_strtolower', $statusTexts))) === 1) {
            $status = array_shift($statusTexts);
            $f->general()->status($status);
        }

        $confirmation = $this->http->FindSingleNode("//tr[ *[normalize-space()][1][{$this->eq($this->t('confNumber'), "translate(.,':','')")}] ]/following-sibling::tr[normalize-space()][1]/*[normalize-space()][1]", null, true, '/^[A-Z\d]{5,}$/');
        if ($confirmation) {
            $confirmationTitle = $this->http->FindSingleNode("//tr[ following-sibling::tr[normalize-space()] ]/*[normalize-space()][1][{$this->eq($this->t('confNumber'), "translate(.,':','')")}]", null, true, '/^(.+?)[\s:：]*$/u');
            $f->general()->confirmation($confirmation, $confirmationTitle);
        }

        $travellers = $this->http->FindNodes("//*[ *[normalize-space()][1][{$this->eq($this->t('Lista de pasajeros'))}] ]/following-sibling::*[normalize-space()][1]/descendant::tr[ count(*)=2 and *[1][normalize-space()=''] ]/*[2][normalize-space()]", null, "/^({$patterns['travellerName']})(?:\s+-|$)/u");
        $f->general()->travellers($travellers, true);

        $xpath = "//*[ not(.//tr[normalize-space()]) and descendant::text()[normalize-space()][1][{$this->eq($this->t('direction'), "translate(.,'-','')")}] and {$this->contains($this->t('De'))} and {$this->contains($this->t('hacia'))} ]/ancestor::*[ following-sibling::*[normalize-space()] ][1]";
        $segments = $this->http->XPath->query($xpath);
        $this->logger->debug('$xpath = ' . $xpath);

        foreach ($segments as $root) {
            // for 2in1-segments see parser aviancataca/FlightChanges

            $s = $f->addSegment();

            $routeText = implode(' ', $this->http->FindNodes("descendant::text()[normalize-space()]", $root));

            if (preg_match("/(?:^|[-\s]){$this->opt($this->t('De'))}\s+(.{2,}?)\s+{$this->opt($this->t('hacia'))}\s+(.{2,})$/", $routeText, $m)) {
                $s->departure()->name($m[1])->noCode();
                $s->arrival()->name($m[2])->noCode();
            }

            $dateRowText = implode("\n", $this->http->FindNodes("following-sibling::*[normalize-space()][1]/descendant::text()[normalize-space()]", $root));

            if (preg_match("/^(.{4,}\b\d{4})(?:\n|$)/", $dateRowText, $m)) {
                $dateDep = strtotime(FlightChanges::normalizeDate($m[1], $this->lang));
                $s->departure()->day($dateDep)->noDate();
                $s->arrival()->noDate();
            }

            if (preg_match("/^{$this->opt($this->t('cabinValues'))}$/im", $dateRowText, $m)) {
                $s->extra()->cabin($m[0]);
            }

            $seatSections = $this->http->XPath->query("following-sibling::*[normalize-space()][1]/following::text()[string-length(normalize-space())>3][position()<5][{$this->eq($this->t('Selección de asiento'), "translate(.,':','')")}]/ancestor::*[ preceding-sibling::*[normalize-space()] ][1]/../descendant::*[ count(tr[normalize-space()])>1 and tr[normalize-space()][1][{$this->eq($this->t('Selección de asiento'), "translate(.,':','')")}] ]", $root);

            if ($seatSections->length > 1) {
                $this->logger->debug('Wrong flight segment!');
                continue;
            } elseif ($seatSections->length === 1) {
                FlightChanges::parseCodesAndSeats($s, $seatSections->item(0), $this->http, $this->eq($this->t('Selección de asiento'), "translate(.,':','')"), $this->patterns['travellerName']);
            }

            if (!empty($s->getDepName()) && !empty($s->getArrName())) {
                $s->airline()->noName()->noNumber();
            }
        }

        $totalPrice = $this->http->FindSingleNode("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('Gran total'))}] ]/*[normalize-space()][2]", null, true, '/^.*\d.*$/');

        if (preg_match('/^(?<currency>[^\-\d)(]+?)[ ]*(?<amount>\d[,.‘\'\d ]*?)[ ]*(?<currencyCode>[A-Z]{3})$/u', $totalPrice, $matches)) {
            // $ 1,162.08 USD  |  $ 365,180 COP
            $currencyCode = $matches['currencyCode'];
            $f->price()->currency($matches['currencyCode'])->total(PriceHelper::parse($matches['amount'], $currencyCode));
        }

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
            if ( !is_string($lang) || empty($phrases['confNumber']) || empty($phrases['direction']) ) {
                continue;
            }
            if ($this->http->XPath->query("//*[{$this->contains($phrases['confNumber'])}]/following::*[{$this->contains($phrases['direction'])}]")->length > 0) {
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

    private function contains($field, string $node = ''): string
    {
        $field = (array)$field;
        if (count($field) === 0)
            return 'false()';
        return '(' . implode(' or ', array_map(function($s) use($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';
            return 'contains(normalize-space(' . $node . '),' . $s . ')';
        }, $field)) . ')';
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
}
