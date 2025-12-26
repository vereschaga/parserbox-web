<?php

namespace AwardWallet\Engine\traveluro\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class ConfirmedBooking extends \TAccountChecker
{
    public $mailFiles = "traveluro/it-631950469.eml, traveluro/it-636286606.eml, traveluro/it-636571989.eml";

    public $lang;
    public static $dictionary = [
        'en' => [
            // 'Traveluro booking number:' => '',
            'Guest name' => 'Guest name',
            // 'Check-in' => '',
            // 'Check-out' => '',
            // 'Room' => '',
            // 'Guests' => '',
            'Room_Count' => 'Room',
            // 'Adult' => '',
            // 'Child' => '',
            // 'Cancellation policy' => '',
        ],
        'pt' => [
            'Traveluro booking number:' => 'Número de reserva da Traveluro:',
            'Guest name'                => 'Nome do convidado',
            'Check-in'                  => 'Check-in',
            'Check-out'                 => 'Check-out',
            'Room'                      => 'Quartos',
            'Guests'                    => 'Convidados',
            'Room_Count'                => ['Quarto', 'Room'],
            'Adult'                     => 'Adult',
            // 'Child' => '',
            'Cancellation policy' => 'Política de cancelamento',
        ],
    ];

    private $detectSubject = [
        // en
        'Thanks! Your booking is confirmed at',
        "You're booked and ",
        // pt
        'Obrigado! A sua reserva está confirmada em',
    ];
    private $detectBody = [
        'en' => [
            'Your booking is confirmed.', ', We hope you enjoy your stay in ',
        ],
        'pt' => [
            'Sua reserva está confirmada.',
        ],
    ];

    private $patterns = [
        'time' => '\d{1,2}[:：]\d{2}(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?)?', // 4:19PM  |  2:00 p. m.
    ];

    public function detectEmailFromProvider($from)
    {
        return preg_match("/[@.]traveluro\.com\b/", $from) > 0;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (!array_key_exists('from', $headers) || $this->detectEmailFromProvider(rtrim($headers['from'], '> ')) !== true) {
            return false;
        }

        foreach ($this->detectSubject as $dSubject) {
            if (stripos($headers["subject"], $dSubject) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//a[{$this->contains(['.traveluro.com'], '@href')}]")->length === 0
            && $this->http->XPath->query("//*[{$this->contains(['Holisto LTD.'])}]")->length === 0
        ) {
            return false;
        }

        foreach ($this->detectBody as $detectBody) {
            if ($this->http->XPath->query("//*[{$this->contains($detectBody)}]")->length > 0) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();

        if (empty($this->lang)) {
            $this->logger->debug("can't determine a language");

            return $email;
        }
        $this->parseEmailHtml($email);

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
        foreach (self::$dictionary as $lang => $dict) {
            if (!empty($dict["Guest name"])
                && $this->http->XPath->query("//*[{$this->contains($dict['Guest name'])}]")->length > 0
            ) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
    }

    private function parseEmailHtml(Email $email): void
    {
        // Travel Agency
        $email->ota()
            ->confirmation($this->http->FindSingleNode("//text()[{$this->eq($this->t('Traveluro booking number:'))}]/ancestor::tr[1]",
                null, true, "/{$this->opt($this->t('Traveluro booking number:'))}\s*(\d{5,})\s*$/"));

        // HOTEL

        $h = $email->add()->hotel();

        // General
        $h->general()
            ->noConfirmation()
            ->traveller($this->http->FindSingleNode("//text()[{$this->eq($this->t('Guest name'))}]/ancestor::tr[1]",
                null, true, "/{$this->opt($this->t('Guest name'))}\s*(.+)\s*$/"), true)
            ->cancellation($this->http->FindSingleNode("//text()[{$this->eq($this->t('Cancellation policy'))}]/ancestor::tr[1]",
                null, true, "/^\s*{$this->opt($this->t('Cancellation policy'))}\s*(.+)\s*$/"))
        ;

        // Hotel
        $h->hotel()
            ->name($this->http->FindSingleNode("//text()[{$this->eq($this->t('Traveluro booking number:'))}]/preceding::text()[normalize-space()][1]/ancestor::tr[1]/preceding::text()[normalize-space()][1]/ancestor::tr[1]"))
            ->address($this->http->FindSingleNode("//text()[{$this->eq($this->t('Traveluro booking number:'))}]/preceding::text()[normalize-space()][1]/ancestor::tr[1]"));

        // Booked
        $h->booked()
            ->checkIn($this->normalizeDate($this->http->FindSingleNode("//text()[{$this->eq($this->t('Check-in'))}]/ancestor::tr[1]",
                null, true, "/{$this->opt($this->t('Check-in'))}\s*(.+)\s*$/")))
            ->checkOut($this->normalizeDate($this->http->FindSingleNode("//text()[{$this->eq($this->t('Check-out'))}]/ancestor::tr[1]",
                null, true, "/{$this->opt($this->t('Check-out'))}\s*(.+)\s*$/")))
            ->rooms($this->http->FindSingleNode("//text()[{$this->eq($this->t('Guests'))}]/ancestor::tr[1]",
                null, true, "/(\d+)\s+{$this->opt($this->t('Room_Count'))}\s*/"))
            ->guests($this->http->FindSingleNode("//text()[{$this->eq($this->t('Guests'))}]/ancestor::tr[1]",
                null, true, "/(\d+)\s+{$this->opt($this->t('Adult'))}\s*/"))
            ->kids($this->http->FindSingleNode("//text()[{$this->eq($this->t('Guests'))}]/ancestor::tr[1]",
                null, true, "/(\d+)\s+{$this->opt($this->t('Child'))}\s*/"), true, true)
        ;

        // Rooms
        $h->addRoom()
            ->setType($this->http->FindSingleNode("//text()[{$this->eq($this->t('Room'))}]/ancestor::tr[1]",
                null, true, "/{$this->opt($this->t('Room'))}\s*(.+)\s*$/"))
        ;

        $this->detectDeadLine($h);
    }

    private function detectDeadLine(\AwardWallet\Schema\Parser\Common\Hotel $h): void
    {
        if (empty($cancellationText = $h->getCancellation())) {
            return;
        }

        if (
           preg_match("/^\s*Cancellations before (.+?) \(UTC\) are fully refundable\./i", $cancellationText, $m)
        ) {
            $h->booked()
               ->deadline($this->normalizeDate($m[1]));
        }

        if (preg_match("/^\s*Non-refundable[\s.;!]*$/i", $cancellationText, $m)
            || preg_match("/^\s*Your reservation is non-refundable[\s.;!]*$/i", $cancellationText, $m)
            || preg_match("/^\s*Non-refundable reservations are also non-amendable and /i", $cancellationText, $m)
            // pt
            || preg_match("/^\s*As reservas não reembolsáveis também não são corrigíveis e/i", $cancellationText, $m)
        ) {
            $h->booked()
               ->nonRefundable();
        }
    }

    private function t($s)
    {
        if (!isset($this->lang, self::$dictionary[$this->lang], self::$dictionary[$this->lang][$s])) {
            return $s;
        }

        return self::$dictionary[$this->lang][$s];
    }

    private function contains($field, $text = 'normalize-space(.)')
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($text) {
            return 'contains(' . $text . ',"' . $s . '")';
        }, $field)) . ')';
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

    private function normalizeDate(?string $date)
    {
        // $this->logger->debug('date begin = ' . print_r( $date, true));
        if (empty($date)) {
            return null;
        }

        $in = [
            // 29 Sep 2024 Sunday, N/A
            "/^\s*(\d{1,2})\s+([[:alpha:]]+)\s+(\d{4})\s+[-[:alpha:]]+\s*,\s*\D{0,7}\bN\/A\s*$/iu",
            // 18 Apr 2024 Thursday, 16:00:00  |  01 Jul 2023 Saturday, from 15:00  |  29 Sep 2024 Sunday, 3:00 PM-midnight
            "/^\s*(\d{1,2})\s+([[:alpha:]]+)\s+(\d{4})\s+[-[:alpha:]]+\s*,\s*\D{0,7}\b({$this->patterns['time']}).*$/u",
        ];
        $out = [
            '$1 $2 $3',
            '$1 $2 $3, $4',
        ];

        $date = preg_replace($in, $out, $date);

//        $this->logger->debug('date replace = ' . print_r( $date, true));
        if (preg_match("/^\s*(\d+\s+)([^\d\s]+)(\s+\d{4}.*)/u", $date, $m)) {
            if ($en = MonthTranslate::translate($m[2], $this->lang)) {
                $date = $m[1] . $en . $m[3];
            }
        }

        // $this->logger->debug('date end = ' . print_r( $date, true));

        return strtotime($date);
    }

    private function opt($field, $delimiter = '/'): string
    {
        $field = (array) $field;

        if (empty($field)) {
            $field = ['false'];
        }

        return '(?:' . implode("|", array_map(function ($s) use ($delimiter) {
            return str_replace(' ', '\s+', preg_quote($s, $delimiter));
        }, $field)) . ')';
    }
}
