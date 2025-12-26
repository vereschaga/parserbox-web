<?php

namespace AwardWallet\Engine\civitatis\Email;

use AwardWallet\Schema\Parser\Email\Email;

class DidYouAttend extends \TAccountChecker
{
    public $mailFiles = "civitatis/it-845804489.eml, civitatis/it-828992380-es.eml";

    private $subjects = [
        'es' => ['Confirma tu reserva:'],
        'en' => ['Confirm your booking:'],
    ];

    public $lang = '';

    public static $dictionary = [
        'es' => [
            'question' => [
                'Podrías confirmar esta información', 'Podrï¿½as confirmar esta informaciï¿½n',
                'Asististeis al tour todas las personas que reservasteis',
            ],
            'otaConfNumber' => ['Número de reserva', 'Nï¿½mero de reserva'],
            'Hello' => 'Hola',
            'Date' => 'Fecha',
            'Hour' => 'Hora',
        ],
        'en' => [
            'question' => ['Did all of the people who booked take part', 'Could you confirm this information'],
            'otaConfNumber' => ['Reservation number'],
            // 'Hello' => '',
            // 'Date' => '',
            'Hour' => ['Hour', 'Time'],
        ],
    ];

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[.@]civitatis\.com$/i', $from) > 0;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if ((!array_key_exists('from', $headers) || $this->detectEmailFromProvider(rtrim($headers['from'], '> ')) !== true)
            && (!array_key_exists('subject', $headers) || strpos($headers['subject'], 'Civitatis.com') === false)
        ) {
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
            && $this->http->XPath->query('//img[contains(@src,"civitatis.com/") and contains(@src,"/youtube")]')->length === 0
            && $this->http->XPath->query('//*[contains(normalize-space(),"Gracias por confiar en Civitatis")]')->length === 0
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
        $email->setType('DidYouAttend' . ucfirst($this->lang));

        $xpathNoEmpty = 'string-length(normalize-space())>1';

        $patterns = [
            'time' => '\d{1,2}(?:[:：]\d{2})?(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?)?', // 4:19PM  |  2:00 p. m.  |  3pm
            'travellerName' => '[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]]', // Mr. Hao-Li Huang
        ];

        $ev = $email->add()->event();
        $ev->type()->event();

        $traveller = null;
        $travellerNames = array_filter($this->http->FindNodes("//text()[{$this->starts($this->t('Hello'))}]", null, "/^{$this->opt($this->t('Hello'))}[,\s]+([[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]])(?:\s*[,;:!?]|$)/u"));
        if (count(array_unique($travellerNames)) === 1) {
            $traveller = array_shift($travellerNames);
        }
        $ev->general()->traveller($traveller);

        $mainRows = $this->http->FindNodes("//*[ count(*[{$xpathNoEmpty}])=2 and preceding::text()[normalize-space()][1][{$this->contains($this->t('question'))}] and following::text()[normalize-space()][1][{$this->contains($this->t('otaConfNumber'))}] ]/*[{$xpathNoEmpty}]");

        if (count($mainRows) === 2) {
            $ev->place()->name($mainRows[0])->address($mainRows[1]);
        }

        $otaConfirmation = $this->http->FindSingleNode("//text()[{$this->eq($this->t('otaConfNumber'), "translate(.,':','')")}]/following::text()[normalize-space()][1]", null, true, '/^[-A-Z\d]{5,}$/');

        if ($otaConfirmation) {
            $otaConfirmationTitle = $this->http->FindSingleNode("//text()[{$this->eq($this->t('otaConfNumber'), "translate(.,':','')")}]", null, true, '/^(.+?)[\s:：]*$/u');
            $email->ota()->confirmation($otaConfirmation, $otaConfirmationTitle);
        }

        if ($otaConfirmation) {
            $ev->general()->noConfirmation();
        }

        $date = strtotime(YourBooking::normalizeDate($this->http->FindSingleNode("//text()[{$this->eq($this->t('Date'), "translate(.,':','')")}]/following::text()[normalize-space()][1]"), $this->lang));
        $time = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Hour'), "translate(.,':','')")}]/following::text()[normalize-space()][1]", null, true, "/^({$patterns['time']})(?:\s*[Hh])?$/");

        if ($date && $time) {
            $ev->booked()->start(strtotime($time, $date))->noEnd();
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
            if ( !is_string($lang) || empty($phrases['question']) || empty($phrases['otaConfNumber']) ) {
                continue;
            }
            if ($this->http->XPath->query("//*[{$this->contains($phrases['question'])}]")->length > 0
                && $this->http->XPath->query("//*[{$this->contains($phrases['otaConfNumber'])}]")->length > 0
            ) {
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

    private function starts($field, string $node = ''): string
    {
        $field = (array)$field;
        if (count($field) === 0)
            return 'false()';
        return '(' . implode(' or ', array_map(function($s) use($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';
            return 'starts-with(normalize-space(' . $node . '),' . $s . ')';
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
