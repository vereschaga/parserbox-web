<?php

namespace AwardWallet\Engine\lanpass\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class UpdatedBoardingPass extends \TAccountChecker
{
    public $mailFiles = "lanpass/it-700592841.eml, lanpass/it-705676749.eml";

    private $detectSubject = [
        // pt
        'Cartão de embarque atualizado',
        // es
        'Tarjeta de embarque actualizada',
    ];

    private $lang = '';

    private static $dictionary = [
        'pt' => [
            'Está tudo pronto para seu voo para' => 'Está tudo pronto para seu voo para',
            'Ver cartão'                         => 'Ver cartão',
        ],
        'es' => [
            'Está tudo pronto para seu voo para' => 'Está todo listo para tu vuelo a',
            'Ver cartão'                         => 'Ver tarjeta',
        ],
    ];

    public function detectEmailFromProvider($from)
    {
        return stripos($from, 'info@mail.latam.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->detectEmailFromProvider($headers['from']) !== true) {
            return false;
        }

        foreach ($this->detectSubject as $detectSubject) {
            if (stripos($headers['subject'], $detectSubject) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query('//a[contains(@href,"latamairlines")]')->length === 0) {
            return false;
        }

        foreach (self::$dictionary as $dict) {
            if (!empty($dict['Está tudo pronto para seu voo para'])
                && !empty($dict['Ver cartão'])
                && $this->http->XPath->query("//node()[" . $this->starts($dict['Está tudo pronto para seu voo para']) . "]")->length > 0
                && $this->http->XPath->query("//node()[" . $this->eq($dict['Ver cartão']) . "]")->length > 0
            ) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        foreach (self::$dictionary as $lang => $dict) {
            if (!empty($dict['Ver cartão'])
                && $this->http->XPath->query("//*[" . $this->eq($dict['Ver cartão']) . "]")->length > 0
            ) {
                $this->lang = $lang;

                break;
            }
        }

        if (empty($this->lang)) {
            $this->logger->debug("Can't determine a language!");

            return $email;
        }

        $this->parseFlight($parser, $email);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

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

    public function parseFlight(\PlancakeEmailParser $parser, Email $email)
    {
        $f = $email->add()->flight();

        $f->general()
            ->confirmation($this->http->FindSingleNode("(//a/@href[{$this->contains($this->t('orderId'))}])[1]",
                null, true, "/(?:\?|%3F)orderId(?:=|%3D)(LA[A-Z\d]{8,})(?:\&|%26)/"))
            ->travellers($this->http->FindNodes("//text()[{$this->contains($this->t('Ver cartão'))}]/preceding::text()[normalize-space()][1]"))
        ;

        $s = $f->addSegment();

        $text = implode("\n", $this->http->FindNodes(" //text()[{$this->eq($this->t('Está tudo pronto para seu voo para'))}]/ancestor::*[count(.//text()[normalize-space()][1]) > 1][1]//text()[normalize-space()]"));
        // $this->logger->debug('$text = '.print_r( $text,true));

        // Está tudo pronto para seu voo para Rio de Janeiro (LA3532), de quarta-feira, 31 de julho de 2024 às 13:25 .
        // Está todo listo para tu vuelo a Temuco (LA23), del sábado, 20 de julio de 2024 a las 08:37.

        if (preg_match("/\((?<al>[A-Z][A-Z\d]|[A-Z\d][A-Z]) ?(?<fn>\d{1,5})\)\s*.+\n*(?<date>.*\b\d{4}\b.*)\n\s*.+\n\s*(?<time>\d{1,2}:\d{2}.*)/", $text, $m)) {
            $s->airline()
                ->name($m['al'])
                ->number($m['fn']);

            $s->departure()
                ->noCode()
                ->name($this->http->FindSingleNode("//img[contains(@src, 'airplane-departure.png')]/preceding::text()[normalize-space()][1]"))
                ->date($this->normalizeDate($m['date'] . ', ' . $m['time']))
            ;
            $s->arrival()
                ->noCode()
                ->name($this->http->FindSingleNode("//img[contains(@src, 'airplane-departure.png')]/following::text()[normalize-space()][1]"))
                ->noDate()
            ;
        }
    }

    private function t(string $phrase)
    {
        if (!isset(self::$dictionary, $this->lang) || empty(self::$dictionary[$this->lang][$phrase])) {
            return $phrase;
        }

        return self::$dictionary[$this->lang][$phrase];
    }

    private function normalizeDate($date)
    {
        $this->logger->debug('$date 1 = ' . print_r($date, true));
        $in = [
            // viernes, 26 de julio de 2024, 17:20
            "/^\s*[[:alpha:]\-]+,\s*(\d+)\.?\s+(?:de\s+)?([[:alpha:]]+)\.?\s+(?:de\s+)?(\d{4})[\s\,]+(\d{1,2}:\d{2}(?:\s*[AP]M)?)\s*$/iu",
        ];
        $out = [
            "$1 $2 $3, $4",
        ];

        $str = preg_replace($in, $out, $date);
        $this->logger->debug('$date 1 = ' . print_r($date, true));

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return strtotime($str);
    }

    private function contains($field, $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'contains(normalize-space(' . $node . '),"' . $s . '")';
        }, $field)) . ')';
    }

    private function eq($field, $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'normalize-space(' . $node . ')="' . $s . '"';
        }, $field)) . ')';
    }

    private function starts($field, $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'starts-with(normalize-space(' . $node . '),"' . $s . '")';
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

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }
}
