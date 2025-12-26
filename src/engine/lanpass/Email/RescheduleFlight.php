<?php

namespace AwardWallet\Engine\lanpass\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class RescheduleFlight extends \TAccountChecker
{
    public $mailFiles = "lanpass/it-42840658.eml, lanpass/it-51529537.eml, lanpass/it-52104909.eml, lanpass/it-56605195.eml, lanpass/it-63213562.eml, lanpass/it-64003301.eml";

    public $lang = '';

    public static $dictionary = [
        'pt' => [
            'formatDetectors' => ['Reprograme seu voo', 'Reprogramar o seu voo', 'Este é seu novo itinerário'],
            'confNumber'      => ['Código de reserva:', 'Código de reserva :', 'Seu código de reserva é:'],
            'route'           => ['Ida', 'Volta'],
            'to'              => ['para', 'paraa', 'a'],
            'Direct flight'   => 'Voo direto',
            'Connection'      => 'Conexão',
        ],
        'es' => [
            'formatDetectors' => ['Reprograma tu vuelo'],
            'confNumber'      => ['Código de reserva:', 'Código de reserva :'],
            'route'           => ['Ida', 'Vuelta'],
            'to'              => 'a',
            'Direct flight'   => 'Vuelo Directo',
            //            'Connection' => '',
        ],
        'en' => [
            'formatDetectors' => ['Reschedule your flight', 'Reprogram Your Flight'],
            'confNumber'      => ['Reservation code:', 'Reservation code :'],
            'route'           => ['Inbound', 'Outbound'],
        ],
    ];

    private $subjects = [
        'pt' => [
            'Sua alteração foi feita com sucesso',
            'A confirmação do seu novo itinerário através Reprogramar o seu voo foi realizada com sucesso',
        ],
        'es' => ['Tu cambio se realizó con éxito'],
        'en' => ['Your new itinerary has been confirmed successfully'],
    ];

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[-.@]latam\.com/i', $from) > 0;
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

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query('//a[contains(@href,".latam.com/") or contains(@href,"mail.latam.com")]')->length === 0
            && $this->http->XPath->query('//node()[contains(normalize-space(),"LATAM Airlines Group S.A.") or contains(.,"@mail.latam.com")]')->length === 0
        ) {
            return false;
        }

        return $this->assignLang();
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();

        if (empty($this->lang)) {
            $this->logger->debug("Can't determine a language!");

            return $email;
        }

        $this->parseFlight($parser, $email);
        $email->setType('RescheduleFlight' . ucfirst($this->lang));

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

    private function parseFlight(\PlancakeEmailParser $parser, Email $email)
    {
        $xpathTime = '(starts-with(translate(normalize-space(),"0123456789：","dddddddddd:"),"d:dd") or starts-with(translate(normalize-space(),"0123456789：","dddddddddd:"),"dd:dd"))';

        $f = $email->add()->flight();

        $confirmation = $this->http->FindSingleNode("//text()[{$this->starts($this->t('confNumber'))}]/following::text()[normalize-space()][1]", null, true, '/^[A-Z\d]{5,}$/');

        if ($confirmation) {
            $confirmationTitle = $this->http->FindSingleNode("//text()[{$this->starts($this->t('confNumber'))}]", null, true, '/^(.+?)[\s:]*$/');
            $f->general()->confirmation($confirmation, $confirmationTitle);
        }

        $segments = $this->http->XPath->query($xpath = "//*/tr[normalize-space()][2][not(.//tr) and {$xpathTime}]/ancestor::td[count(preceding-sibling::td[normalize-space()])=1][1]/preceding-sibling::td[normalize-space()]/descendant::tr[not(.//tr) and {$this->contains($this->t('to'))}][last()]");
        $this->logger->debug($xpath);

        foreach ($segments as $segment) {
            $s = $f->addSegment();

            $route = $this->htmlToText($this->http->FindHTMLByXpath('.', null, $segment));

            if (preg_match("/^(.{3,})\s+{$this->opt($this->t('to'))}\s+(.{3,})$/", $route, $m)) {
                if (preg_match("/^[A-Z]{3}$/", $m[1])) {
                    $s->departure()->code($m[1]);
                } else {
                    $s->departure()
                        ->name($m[1])
                        ->noCode();
                }

                if (preg_match("/^[A-Z]{3}$/", $m[2])) {
                    $s->arrival()->code($m[2]);
                } else {
                    $s->arrival()
                        ->name($m[2])
                        ->noCode();
                }

                $s->airline()
                    ->noName()
                    ->noNumber();
            }

            $xpathRight = "ancestor::td[ following-sibling::td[normalize-space()] ][1]/following-sibling::td[normalize-space()][1]/table//table";

            // 21 mai | 31/10
            $patterns['date'] = '(?:\d{1,2}\s+[[:alpha:]]{3}|\d+/\d+)';

            $dates = $this->http->FindSingleNode($xpathRight . "//tr[1]", $segment);
            $dateDep = $dateArr = '';

            if (preg_match("#^({$patterns['date']})\s+({$patterns['date']})$#u", $dates, $m)
                || preg_match("#^({$patterns['date']})$#u", $dates, $m)
            ) {
                $dateDep = $this->normalizeDate($m[1]);
                $dateArr = empty($m[2]) ? $dateDep : $this->normalizeDate($m[2]);
            }

            $patterns['time'] = '\d{1,2}(?:[:]+\d{2})?(?:[:]+\d{2})?(?:\s*[AaPp]\.?[Mm]\.?)?';

            $times = $this->http->FindSingleNode($xpathRight . "//tr[2]", $segment);
            $timeDep = $timeArr = '';

            if (preg_match("/^({$patterns['time']})\s+-\s+({$patterns['time']})$/", $times, $m)) {
                $timeDep = $m[1];
                $timeArr = $m[2];
            }

            if ($dateDep && $timeDep) {
                $dateDep = EmailDateHelper::calculateDateRelative($dateDep, $this, $parser);
                $s->departure()->date(strtotime($timeDep, $dateDep));
            }

            if ($dateArr && $timeArr) {
                $dateArr = EmailDateHelper::calculateDateRelative($dateArr, $this, $parser);
                $s->arrival()->date(strtotime($timeArr, $dateArr));
            }

            $fInfo = $this->http->FindSingleNode($xpathRight . "//tr[3]", $segment);

            if (preg_match("/^(\d[HhMm \d]+)(?: - |$)/", $fInfo, $m)) {
                // 3h 05m - Voo direto
                $s->extra()->duration($m[1]);
            }

            if (preg_match("/\b{$this->opt($this->t('Direct flight'))}\b/", $fInfo)) {
                $s->extra()->stops(0);
            } elseif (preg_match("/\b(\d{1,3})\s*{$this->opt($this->t('Connection'))}/", $fInfo, $m)) {
                // 1 Conexão    |    2 Conexãoes
                $s->extra()->stops($m[1]);
            }
        }
    }

    private function assignLang(): bool
    {
        if (!isset(self::$dictionary, $this->lang)) {
            return false;
        }

        foreach (self::$dictionary as $lang => $phrases) {
            if (!is_string($lang) || empty($phrases['formatDetectors']) || empty($phrases['confNumber'])) {
                continue;
            }

            if ($this->http->XPath->query("//node()[{$this->contains($phrases['formatDetectors'])}]")->length > 0
                && $this->http->XPath->query("//node()[{$this->contains($phrases['confNumber'])}]")->length > 0
            ) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
    }

    private function t(string $phrase)
    {
        if (!isset(self::$dictionary, $this->lang) || empty(self::$dictionary[$this->lang][$phrase])) {
            return $phrase;
        }

        return self::$dictionary[$this->lang][$phrase];
    }

    /**
     * Dependencies `use AwardWallet\Engine\MonthTranslate` and `$this->lang`.
     */
    private function normalizeDate(?string $text): ?string
    {
        // 29 mar
        if (preg_match('/^(\d{1,2})\s+([[:alpha:]]{3,})$/u', $text, $m)) {
            $day = $m[1];
            $month = $m[2];
            $year = '';
        }
        // 31/10
        elseif (preg_match('#^(\d{1,2})/(\d{1,2})$#u', $text, $m)) {
            $day = $m[1];
            $month = $m[2];
            $year = '';
        }

        if (isset($day, $month, $year)) {
            if (preg_match('/^\s*(\d{1,2})\s*$/', $month, $m)) {
                return $m[1] . '/' . $day . ($year ? '/' . $year : '');
            }

            if (($monthNew = MonthTranslate::translate($month, $this->lang)) !== false) {
                $month = $monthNew;
            }

            return $day . ' ' . $month . ($year ? ' ' . $year : '');
        }

        return null;
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

    private function htmlToText(?string $s, bool $brConvert = true): string
    {
        if (!is_string($s) || $s === '') {
            return '';
        }
        $s = str_replace("\r", '', $s);
        $s = preg_replace('/<!--.*?-->/s', '', $s); // comments

        if ($brConvert) {
            $s = preg_replace('/\s+/', ' ', $s);
            $s = preg_replace('/<[Bb][Rr]\b.*?\/?>/', "\n", $s); // only <br> tags
        }
        $s = preg_replace('/<[A-z][A-z\d:]*\b.*?\/?>/', '', $s); // opening tags
        $s = preg_replace('/<\/[A-z][A-z\d:]*\b[ ]*>/', '', $s); // closing tags
        $s = html_entity_decode($s);
        $s = str_replace(chr(194) . chr(160), ' ', $s); // NBSP to SPACE

        return trim($s);
    }
}
