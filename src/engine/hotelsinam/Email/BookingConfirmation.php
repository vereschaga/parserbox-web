<?php

namespace AwardWallet\Engine\hotelsinam\Email;

// TODO: delete what not use
use AwardWallet\Schema\Parser\Email\Email;

class BookingConfirmation extends \TAccountChecker
{
    public $mailFiles = "hotelsinam/it-670488551.eml, hotelsinam/it-671896266.eml, hotelsinam/it-671900761.eml, hotelsinam/it-675292696.eml";

    public $lang;
    public static $dictionary = [
        'es' => [
            'Booking Details' => 'Detalles de reserva',
            // 'Booking Cancelled' => [''],
            'Confirmation Number:'       => 'Número de confirmación:',
            'Check-In:'                  => 'Check-in:',
            'Check-Out:'                 => 'Salida:',
            'No. of nights:'             => 'No de noches:',
            'Room Type:'                 => 'Tipo de habitación:',
            'Address:'                   => 'Dirección:',
            'Guest Name:'                => 'Nombre del invitado:',
            'Refund Policy Flexibility:' => 'Refund Policy Flexibility:',
        ],
        'pt' => [
            'Booking Details' => 'Detalhes da reserva',
            // 'Booking Cancelled' => [''],
            'Confirmation Number:'       => 'Número de confirmação:',
            'Check-In:'                  => 'Check-in:',
            'Check-Out:'                 => 'Verificação de saída:',
            'No. of nights:'             => 'No. de noites:',
            'Room Type:'                 => 'Tipo de sala:',
            'Address:'                   => 'Endereço:',
            'Guest Name:'                => 'Nome do convidado:',
            'Refund Policy Flexibility:' => 'Refund Policy Flexibility:',
        ],
        'en' => [ // last
            'Booking Details'   => 'Booking Details',
            'Booking Cancelled' => ['has been cancelled', 'Cancellation Number:'],
            //            'Confirmation Number:' => '',
            //            'Check-In:' => '',
            //            'Check-Out:' => '',
            'No. of nights:' => 'No. of nights:',
            //            'Room Type:' => '',
            //            'Address:' => '',
            //            'Guest Name:' => '',
            //            'Refund Policy Flexibility:' => '',
        ],
    ];

    private $detectFrom = "do-not-reply@hotelsinamerica.com";
    private $detectSubject = [
        // en
        'Your HotelsInAmerica Booking Confirmation for',
        'Your HotelsInAmerica Booking Cancellation for',
        // es
        'Su HotelsInAmerica Confirmación de reserva para',
        // pt
        'Sua HotelsInAmerica confirmação de reserva para',
    ];

    public function detectEmailFromProvider($from)
    {
        return preg_match("/[@.]hotelsinamerica\.com$/", $from) > 0;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (stripos($headers["from"], $this->detectFrom) === false
            && strpos($headers["subject"], 'HotelsInAmerica') === false
        ) {
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
        if (
            $this->http->XPath->query("//a[{$this->contains(['hotelsinamerica.com'], '@href')}]")->length === 0
            && $this->http->XPath->query("//*[{$this->contains(['@hotelsinamerica.com'])}]")->length === 0
        ) {
            return false;
        }

        foreach (self::$dictionary as $lang => $dict) {
            if (!empty($dict["Booking Details"]) && !empty($dict["No. of nights:"])) {
                if ($this->http->XPath->query("//*[{$this->contains($dict['Booking Details'])}]")->length > 0
                    && $this->http->XPath->query("//*[{$this->contains($dict['No. of nights:'])}]")->length > 0
                ) {
                    $this->lang = $lang;

                    return true;
                }
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

    private function assignLang()
    {
        foreach (self::$dictionary as $lang => $dict) {
            if (!empty($dict["Booking Details"])) {
                if ($this->http->XPath->query("//*[{$this->contains($dict['Booking Details'])}]")->length > 0) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function parseEmailHtml(Email $email)
    {
        $h = $email->add()->hotel();

        // General
        $h->general()
            ->confirmation($this->http->FindSingleNode("//text()[{$this->starts($this->t('Confirmation Number:'))} or {$this->starts($this->t('Cancellation Number:'))}]",
                null, true, "/^\s*(?:{$this->opt($this->t('Confirmation Number:'))}|{$this->opt($this->t('Cancellation Number:'))})\s*([A-Z\d_]{5,})$/"))
            ->traveller($this->http->FindSingleNode("//text()[{$this->eq($this->t('Guest Name:'))}]/following::text()[normalize-space()][1]",
                null, true, "/^\s*(\D+)$/"), true)
            ->cancellation(implode(' ', $this->http->FindNodes("//text()[{$this->eq($this->t('Refund Policy Flexibility:'))}]/ancestor::tr[1][{$this->starts($this->t('Refund Policy Flexibility:'))}]/descendant::text()[normalize-space()][position() > 1]")),
                true, true)
        ;

        if ($this->http->XPath->query("//node()[{$this->contains($this->t('Booking Cancelled'))}]")->length > 0) {
            $h->general()
                ->status('Cancelled')
                ->cancelled();
        }

        // Hotel
        $hotelInfo = $this->http->FindNodes("//text()[{$this->eq($this->t('Address:'))}]/ancestor::tr[1][{$this->starts($this->t('Address:'))}]//text()[normalize-space()]");

        if (count($hotelInfo) === 3) {
            $h->hotel()
                ->name($hotelInfo[1])
                ->address($hotelInfo[2]);
        }

        // Booked
        $h->booked()
            ->checkIn($this->normalizeDate($this->http->FindSingleNode("//text()[{$this->eq($this->t('Check-In:'))}]/following::text()[normalize-space()][1]")))
            ->checkOut($this->normalizeDate($this->http->FindSingleNode("//text()[{$this->eq($this->t('Check-Out:'))}]/following::text()[normalize-space()][1]")))
        ;

        // Rooms
        $h->addRoom()
            ->setType($this->http->FindSingleNode("//text()[{$this->eq($this->t('Room Type:'))}]/following::text()[normalize-space()][1]"));

        $this->detectDeadLine($h);

        return true;
    }

    /**
     * Here we describe various variations in the definition of dates deadLine.
     */
    private function detectDeadLine(\AwardWallet\Schema\Parser\Common\Hotel $h)
    {
        if (empty($cancellationText = $h->getCancellation())) {
            return;
        }

        if (
            preg_match("/Your booking is fully refundable until (?<date>\w+[\s,.]*\w+[\s,.]*\w+)\./i", $cancellationText, $m)
        ) {
            $h->booked()
                ->deadline($this->normalizeDate($m['date']));
        }

        if (preg_match("/Your booking is 100% non-refundable\./i", $cancellationText, $m)
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

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) {
            return 'starts-with(normalize-space(.),"' . $s . '")';
        }, $field)) . ')';
    }

    private function normalizeDate(?string $date): ?int
    {
        $this->logger->debug('date begin = ' . print_r($date, true));
        $in = [
            // Fri May 03, 2024 (3 PM)
            '/^\s*[[:alpha:]]+\s+([[:alpha:]]+)\s+(\d+),\s*(\d{4})\s*\(\s*(\d{1,2})\s*([ap]m)\s*\)\s*$/ui',
        ];
        $out = [
            '$2 $1 $3, $4:00 $5',
        ];

        $date = preg_replace($in, $out, $date);

//        $this->logger->debug('date replace = ' . print_r( $date, true));

        $this->logger->debug('date end = ' . print_r($date, true));

        return strtotime($date);
    }

    private function opt($field, $delimiter = '/')
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
