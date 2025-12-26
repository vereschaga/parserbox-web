<?php

namespace AwardWallet\Engine\solmelia\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class Booking extends \TAccountChecker
{
    public $mailFiles = "solmelia/it-83171992.eml, solmelia/it-678321056.eml, solmelia/it-731009615-pt.eml";

    public $lang = '';

    public static $dictionary = [
        "pt" => [
            'Booking reference:'        => 'Localizador:',
            'Guest name'                => 'Nome do hóspede',
            'Booking confirmation date' => 'Data de confirmação da reserva',
            'Length of stay'            => 'Duração estadia',
            'Telephone:'                => 'Telefone:',
            // 'Fax:' => '',
            'Room'            => 'Apartamento',
            'guest'           => 'convidado',
            'Adult'           => 'Adulto',
            'Arrival date:'   => 'Data de entrada:',
            'Departure date:' => 'Data de saída:',
            // 'Check-in' => '',
            // 'Check-out' => '',
            'Category' => 'Categoria',
        ],
        "en" => [
            // 'Booking reference:' => '',
            // 'Guest name' => '',
            // 'Booking confirmation date' => '',
            'Length of stay' => 'Length of stay',
            // 'Telephone:' => '',
            // 'Fax:' => '',
            // 'Room' => '',
            // 'guest' => '',
            // 'Adult' => '',
            // 'Arrival date:' => '',
            // 'Departure date:' => '',
            // 'Check-in' => '',
            // 'Check-out' => '',
            'Category' => 'Category',
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        return stripos($headers['subject'], 'meliaPro;EMAIL_ASUNTO_ALTA_RESERVA') !== false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if ($this->detectEmailFromProvider(rtrim($parser->getHeader('from'), '> ')) !== true
            && $this->http->XPath->query('//a[contains(@href,".melia.com/") or contains(@href,"www.melia.com") or contains(@href,"app.melia.com")]')->length === 0
            && $this->http->XPath->query('//*[contains(.,"www.meliapro.com")]')->length === 0
        ) {
            return false;
        }

        return $this->assignLang();
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]melia.com$/i', $from) > 0;
    }

    public function ParseEmail(Email $email): void
    {
        $patterns = [
            'date'          => '.+\b\d{4}\b', // Sunday, 26 December 2021
            'time'          => '\d{1,2}(?:[:：]\d{2})?(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?)?', // 4:19PM    |    2:00 p. m.    |    3pm
            'phone'         => '[+(\d][-+. \d)(]{5,}[\d)]', // +377 (93) 15 48 52    |    (+351) 21 342 09 07    |    713.680.2992
            'travellerName' => '[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]]', // Mr. Hao-Li Huang
        ];

        $h = $email->add()->hotel();

        $xpathGeneral = "//*[ count(node()[normalize-space()])>1 and count(node()[normalize-space()])<4 and node()[normalize-space()][1][{$this->eq($this->t('Booking reference:'))}] ]";

        $confirmation = $this->http->FindSingleNode($xpathGeneral . "/node()[normalize-space()][2]", null, true, '/^[A-Z\d]{5,}$/');

        if ($confirmation) {
            $confirmationTitle = $this->http->FindSingleNode($xpathGeneral . "/node()[normalize-space()][1]", null, true, '/^(.+?)[\s:：]*$/u');
            $h->general()->confirmation($confirmation, $confirmationTitle);
        }

        $travelInfo = implode("\n", $this->http->FindNodes($xpathGeneral . "/ancestor-or-self::*[ following-sibling::*[normalize-space()] ][1]/following-sibling::*[normalize-space()][1]/descendant::text()[normalize-space()]"));

        /*
            Hotel de Mar Gran Meliá
            Guest name:
            Rochelle Albert
            Booking confirmation date:
            14/05/24
        */

        $pattern = "/"
            . "(?<hotelName>.{2,})\n+"
            . "(?:{$this->opt($this->t('Guest name'))})?[ ]*[:]+\s*(?<traveller>{$patterns['travellerName']})\n+"
            . "(?:{$this->opt($this->t('Booking confirmation date'))})?[ ]*[:]+\s*(?<dateRes>\d{1,2}\s*[-\/]\s*\d{2}\s*[-\/]\s*\d{2,4})(?:\n|$)"
            . "/u";

        if (preg_match($pattern, $travelInfo, $m)) {
            $h->general()
                ->traveller($m['traveller'], true)
                ->date(strtotime($this->normalizeDate($m['dateRes'])));

            $h->hotel()
                ->name($m['hotelName']);
        }

        $h->hotel()->address($this->http->FindSingleNode("//text()[{$this->starts($this->t('Telephone:'))}]/ancestor::tr[1]/preceding::tr[normalize-space()][1]"));

        $phoneInfo = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Telephone:'))}]/ancestor::tr[1]");

        if (preg_match("/{$this->opt($this->t('Telephone:'))}[:\s]*({$patterns['phone']})/", $phoneInfo, $m)) {
            $h->hotel()->phone($m[1]);
        }

        if (preg_match("/{$this->opt($this->t('Fax:'))}[:\s]*({$patterns['phone']})/", $phoneInfo, $m)) {
            $h->hotel()->fax($m[1]);
        }

        $h->booked()
            ->rooms($this->http->FindSingleNode("//text()[{$this->eq($this->t('Room'))}]/ancestor::tr[1]", null, true, "/{$this->opt($this->t('Room'))}\s*(\d{1,3})\b/"))
            ->guests($this->http->FindSingleNode("//*[ count(node()[normalize-space()])=2 and node()[normalize-space()][1][{$this->eq($this->t('guest'))}] ]/node()[normalize-space()][2]", null, true, "/\b(\d{1,3})\s*{$this->opt($this->t('Adult'))}/i"));

        $checkInInfo = implode(' ', $this->http->FindNodes("//text()[{$this->starts($this->t('Arrival date:'))}]/ancestor::tr[1]/descendant::text()[normalize-space()]"));

        if (preg_match("/{$this->opt($this->t('Arrival date:'))}\s+(?<date>{$patterns['date']})\s+(?:{$this->opt($this->t('Check-in'))}.+\s)?(?<time>{$patterns['time']})/iu", $checkInInfo, $m)) {
            $h->booked()->checkIn(strtotime($m['time'], strtotime($this->normalizeDate($m['date']))));
        }

        $checkOutInfo = implode(' ', $this->http->FindNodes("//text()[{$this->starts($this->t('Departure date:'))}]/ancestor::tr[1]/descendant::text()[normalize-space()]"));

        if (preg_match("/{$this->opt($this->t('Departure date:'))}\s+(?<date>{$patterns['date']})\s+(?:{$this->opt($this->t('Check-out'))}.+\s)?(?<time>{$patterns['time']})/iu", $checkOutInfo, $m)) {
            $h->booked()->checkOut(strtotime($m['time'], strtotime($this->normalizeDate($m['date']))));
        }

        $roomType = $this->http->FindSingleNode("//*[ count(node()[normalize-space()])=2 and node()[normalize-space()][1][{$this->eq($this->t('Category'))}] ]/node()[normalize-space()][2]");

        if (!empty($roomType)) {
            $room = $h->addRoom();
            $room->setType($roomType);
        }
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();
        $this->ParseEmail($email);

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

    private function assignLang(): bool
    {
        if (!isset(self::$dictionary, $this->lang)) {
            return false;
        }

        foreach (self::$dictionary as $lang => $phrases) {
            if (!is_string($lang) || empty($phrases['Length of stay']) || empty($phrases['Category'])) {
                continue;
            }

            if ($this->http->XPath->query("//text()[{$this->contains($phrases['Length of stay'])}]/following::text()[{$this->contains($phrases['Category'])}]")->length > 0) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
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

    /**
     * Dependencies `use AwardWallet\Engine\MonthTranslate` and `$this->lang`.
     *
     * @param string|null $text Unformatted string with date
     */
    private function normalizeDate(?string $text): ?string
    {
        if (preg_match('/^(\d{1,2})\s*[-\/]\s*(\d{1,2})\s*[-\/]\s*(\d{4})$/', $text, $m)) {
            // 20/03/2025  |  20-03-2025
            $day = $m[1];
            $month = $m[2];
            $year = $m[3];
        } elseif (preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{2})$/', $text, $m)) {
            // 14/05/24
            $day = $m[1];
            $month = $m[2];
            $year = '20' . $m[3];
        } elseif (preg_match('/^[-[:alpha:]]{2,}[,.\s]+(\d{1,2})\s+(?:de\s+)?([[:alpha:]]{3,})\s+(?:de\s+)?(\d{4})$/u', $text, $m)) {
            // Sunday, 26 December 2021  |  SEGUNDA-FEIRA, 30 SETEMBRO 2024
            $day = $m[1];
            $month = $m[2];
            $year = $m[3];
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
}
