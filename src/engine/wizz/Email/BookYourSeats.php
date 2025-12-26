<?php

namespace AwardWallet\Engine\wizz\Email;

use AwardWallet\Schema\Parser\Email\Email;

class BookYourSeats extends \TAccountChecker
{
    public $mailFiles = "wizz/it-60297402.eml";

    public $lang = '';

    public static $dictionary = [
        'pl' => [
            'yourFlight' => ['Twój lot:', 'Twój lot :'],
            'confNumber' => ['Kod potwierdzenia:', 'Kod potwierdzenia :'],
            'Passenger'  => 'Pasażer',
            'Seat'       => 'Miejsce',
        ],
        'fr' => [
            'yourFlight' => ['Votre vol :'],
            'confNumber' => ['Code de confirmation :'],
            'Passenger'  => 'Passager',
            'Seat'       => 'Siège',
        ],
        'bg' => [
            'yourFlight' => ['Zborul dumneavoastră:'],
            'confNumber' => ['Codul de confirmare:'],
            'Passenger'  => 'Pasager',
            'Seat'       => 'Loc',
        ],
        'hu' => [
            'yourFlight' => ['Az Ön járata:'],
            'confNumber' => ['Foglalási kód:'],
            'Passenger'  => 'Utas',
            'Seat'       => 'Ülőhely',
        ],
        'he' => [
            'yourFlight' => ['הטיסה שלך:'],
            'confNumber' => ['קוד אישור:'],
            'Passenger'  => 'נוסע',
            'Seat'       => 'מושב',
        ],
        'uk' => [
            'yourFlight' => ['Ваш рейс:'],
            'confNumber' => ['Код підтвердження:'],
            'Passenger'  => 'Пасажир',
            'Seat'       => 'Місце',
        ],
        'ru' => [
            'yourFlight' => ['Ваш рейс:'],
            'confNumber' => ['Код подтверждения:'],
            'Passenger'  => 'Пассажир',
            'Seat'       => 'Место',
        ],
        'it' => [
            'yourFlight' => ['Il tuo volo:'],
            'confNumber' => ['Codice di conferma:'],
            'Passenger'  => 'Passeggero',
            'Seat'       => 'Posto',
        ],
        'es' => [
            'yourFlight' => ['Su vuelo:'],
            'confNumber' => ['Código de confirmación:'],
            'Passenger'  => 'Pasajero',
            'Seat'       => 'Asiento',
        ],
        'de' => [
            'yourFlight' => ['Ihr Flug:'],
            'confNumber' => ['Bestätigungscode:'],
            'Passenger'  => 'Passagier',
            'Seat'       => 'Sitzplatz',
        ],
        'sv' => [
            'yourFlight' => ['Ditt flyg:'],
            'confNumber' => ['Bekräftelsekod:'],
            'Passenger'  => 'Passagerare',
            'Seat'       => 'Stol',
        ],
    ];

    private $subjects = [
        'pl' => ['Przydatne informacje o miejscach'],
        'fr' => ['Informations utiles à propos de votre siège'],
        'bg' => ['Informații utile despre locurile dumneavoastră'],
        'hu' => ['Hasznos információk az ülőhelyekről'],
        'he' => [' מידע שימושי לגבי המושבים שלך'],
        'uk' => ['Корисна інформація про ваші місця'],
        'ru' => ['Информация о Ваших местах'],
        'it' => ['Informazioni utili sui posti disponibili in volo'],
        'es' => ['Información útil sobre sus asientos'],
        'de' => ['Nützliche Informationen über Ihre Sitzplätze'],
        'sv' => [' Användbar information om dina platser'],
    ];

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@wizzair.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (self::detectEmailFromProvider($headers['from']) !== true) {
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
        if ($this->http->XPath->query('//a[contains(@href,".wizzair.com/") or contains(@href,"www.wizzair.com")]')->length === 0
            && $this->http->XPath->query('//node()[contains(normalize-space(),"Wizz Air Hungary Ltd.")]')->length === 0
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

        $this->parseFlight($email);
        $email->setType('BookYourSeats' . ucfirst($this->lang));

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

    private function parseFlight(Email $email): void
    {
        $f = $email->add()->flight();

        $segments = $this->http->XPath->query("//tr[ *[1][{$this->eq($this->t('yourFlight'))}] ]");

        foreach ($segments as $root) {
            $s = $f->addSegment();

            $s->departure()->code($this->http->FindSingleNode('*[2]', $root));
            $s->arrival()->code($this->http->FindSingleNode('*[4]', $root));

            $datesValue = $this->http->FindSingleNode('*[5]', $root);
            $dates = preg_split('/\s+[-–]+\s+/', trim($datesValue, ')( '));

            if (count($dates) == 2) {
                $s->departure()->date2($this->normalizeDate($dates[0]));
                $s->arrival()->date2($this->normalizeDate($dates[1]));

                $s->airline()
                    ->noName()
                    ->noNumber();
            } elseif (count($dates) == 1 && !empty($this->normalizeDate($dates[0]))) {
                $s->departure()->date2($this->normalizeDate($dates[0]));
                $s->arrival()->noDate();

                $s->airline()
                    ->noName()
                    ->noNumber();
            } else {
                continue;
            }

        }

        $confirmation = $this->http->FindSingleNode("//text()[{$this->starts($this->t('confNumber'))}]/following::text()[normalize-space()][1]", null, true, '/^[A-Z\d]{5,}$/');

        if ($confirmation) {
            $confirmationTitle = $this->http->FindSingleNode("//text()[{$this->starts($this->t('confNumber'))}]", null, true, '/^(.+?)[\s:]*$/');
            $f->general()->confirmation($confirmation, $confirmationTitle);
        }

        $passengersRows = $this->http->XPath->query("//tr[ *[1][{$this->eq($this->t('Passenger'))}] and *[2][{$this->eq($this->t('Seat'))}] ]/following-sibling::tr[normalize-space()]");

        foreach ($passengersRows as $pRow) {
            if (($passenger = $this->http->FindSingleNode('*[1]', $pRow, true, '/^[[:alpha:]][-.\'[:alpha:] ]*[[:alpha:]]$/u'))) {
                $f->general()->traveller($passenger);
            }
        }
    }

    private function normalizeDate(?string $text): string
    {
        if (!is_string($text) || empty($text)) {
            return '';
        }
        $in = [
            // 2020.06.11 6:0
            '/^(\d{4})[-.](\d{1,2})[-.](\d{1,2})\s+(\d{1,2}(?:[:：]\d{1,2})?(?:\s*[AaPp]\.?[Mm]\.?)?)$/',
        ];
        $out = [
            '$2/$3/$1 $4',
        ];

        return preg_replace($in, $out, $text);
    }

    private function assignLang(): bool
    {
        if (!isset(self::$dictionary, $this->lang)) {
            return false;
        }

        foreach (self::$dictionary as $lang => $phrases) {
            if (!is_string($lang) || empty($phrases['yourFlight']) || empty($phrases['confNumber'])) {
                continue;
            }

            if ($this->http->XPath->query("//node()[{$this->contains($phrases['yourFlight'])}]")->length > 0
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

    private function contains($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'contains(normalize-space(' . $node . '),"' . $s . '")';
        }, $field)) . ')';
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

    private function starts($field, string $node = ''): string
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
}
