<?php

namespace AwardWallet\Engine\klm\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class FlightInformation extends \TAccountChecker
{
    public $mailFiles = "klm/it-771669527.eml, klm/it-779948681.eml";
    public $detectSubjects = [
        // en
        'Please check your details for your flight to',
        // nl
        'Controleer uw gegevens alstublieft voor uw vlucht naar',
        // pt
        'Por favor, confira seus dados para pagamento',
        // no
        'Vennligst sjekk opplysningene dine',
    ];

    public $lang = '';
    public static $dictionary = [
        'en' => [
            'Please check your details' => 'Please check your details',
            'Flight information:'       => 'Flight information:',
            'Booking code:'             => 'Booking code:',
            'Dear '                     => 'Dear ',
            'nameTitle'                 => ['Mr.', 'Ms.', 'Mrs/Mr', 'Mr', 'Miss', 'Mrs'],
        ],
        'nl' => [
            'Please check your details' => 'Controleer uw gegevens alstublieft',
            'Flight information:'       => 'Vluchtinformatie:',
            'Booking code:'             => 'Boekingscode:',
            'Dear '                     => ['Beste ', 'Geachte '],
            'nameTitle'                 => ['heer', 'mevrouw', 'heer/mevrouw', 'meneer'],
        ],
        'pt' => [
            'Please check your details' => 'Por favor, confira seus dados para pagamento',
            'Flight information:'       => 'Informações do voo:',
            'Booking code:'             => 'Código da reserva:',
            'Dear '                     => ['Bom dia '],
            // 'nameTitle' => ['heer', 'mevrouw', 'heer/mevrouw'],
        ],
        'no' => [
            'Please check your details' => 'Vennligst sjekk opplysningene dine',
            'Flight information:'       => 'Flyinformasjon:',
            'Booking code:'             => 'Referansenummer:',
            'Dear '                     => ['Kjære '],
            // 'nameTitle' => ['heer', 'mevrouw', 'heer/mevrouw'],
        ],
    ];

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        if (empty($this->http->Response['body'])) {
            $this->http->SetEmailBody($parser->getPlainBody());
        }

        foreach (self::$dictionary as $lang => $dict) {
            if (!empty($dict['Please check your details']) && !empty($dict['Flight information:'])
                && $this->http->XPath->query("//text()[{$this->contains($dict['Please check your details'])}]")->length > 0
                && $this->http->XPath->query("//text()[{$this->contains($dict['Flight information:'])}]")->length > 0
            ) {
                $this->lang = $lang;

                break;
            }
        }

        $this->parseFlight($email);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function parseFlight(Email $email)
    {
        $flight = $email->add()->flight();

        // General
        $flight->general()
            ->confirmation($this->http->FindSingleNode("//text()[{$this->eq($this->t('Booking code:'))}]/following::text()[normalize-space()][1]",
                null, true, "/^\s*[A-Z\d]{5,7}\s*$/"));

        $traveller = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Dear '))}]",
            null, false, "/{$this->opt($this->t('Dear '))}\s*(?:{$this->opt($this->t('nameTitle'))}\s+)?([[:alpha:] \.\-]{4,}?)\s*,\s*$/");
        $traveller = preg_replace("/^\s*([[:alpha:]]{1,4}\.|Mr|Ms)\s+/ui", '', $traveller);

        $flight->general()
            ->traveller($traveller, str_word_count($traveller) == 1 ? false : true)
        ;

        // Segments
        $s = $flight->addSegment();

        $text = implode("\n", $this->http->FindNodes("//text()[{$this->eq($this->t('Flight information:'))}]/ancestor::*[{$this->contains($this->t('Booking code:'))}][1]//text()[normalize-space()]"));
        // $this->logger->debug('$text = '.print_r( $text,true));

        $re = "/{$this->opt($this->t('Flight information:'))}\s+(?<date>.+)\s*\n\s*(?<dCode>[A-Z]{3})\s*-\s*(?<aCode>[A-Z]{3})\s+{$this->opt($this->t('Booking code:'))}/u";

        if (preg_match($re, $text, $m)) {
            // Airline
            $s->airline()
                ->name('KL')
                ->noNumber();

            // Departure
            $s->departure()
                ->code($m['dCode'])
                ->date($this->normalizeDate($m['date']));

            // Arrival
            $s->arrival()
                ->code($m['aCode'])
                ->noDate();
        }

        return $email;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//a/@href[{$this->contains(['.klm.', '.klm-info.', '.infos-klm.'])}]")->length === 0
            && $this->http->XPath->query("//text()[{$this->contains(['KLM Privacy Policy', 'KLM Customer care'])}]")->length === 0
        ) {
            return false;
        }

        foreach (self::$dictionary as $dict) {
            if (!empty($dict['Please check your details']) && !empty($dict['Flight information:'])
                && $this->http->XPath->query("//text()[{$this->contains($dict['Please check your details'])}]")->length > 0
                && $this->http->XPath->query("//text()[{$this->contains($dict['Flight information:'])}]")->length > 0
            ) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@]klm[-]info\.com/', $from) > 0;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (empty($headers['from']) || stripos($headers['from'], 'klm-info.com') === false) {
            return false;
        }

        foreach ($this->detectSubjects as $dSubject) {
            if (stripos($headers['subject'], $dSubject) !== false) {
                return true;
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

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'normalize-space(.)="' . $s . '"'; }, $field)) . ')';
    }

    private function normalizeDate($date)
    {
        $in = [
            // Thu 31 October 24 - 20:30
            "/^\s*[[:alpha:]\-]+\s+(\d{1,2})\s+([[:alpha:]\-]+)\s*(\d{2})\s*\W\s*(\d{1,2}:\d{2}(?:\s*[ap]m)?)$/ui",
        ];
        $out = [
            "$1 $2 20$3, $4",
        ];
        $date = preg_replace($in, $out, $date);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $date, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $date = str_replace($m[1], $en, $date);
            }
        }

        return strtotime($date);
    }
}
