<?php

namespace AwardWallet\Engine\omio\Email;

use AwardWallet\Schema\Parser\Email\Email;

class CancelledTickets extends \TAccountChecker
{
    public $mailFiles = "omio/it-103917925.eml, omio/it-106328187.eml, omio/it-123357798.eml";

    public $lang = '';

    public static $dictionary = [
        'es' => [
            'Ticket number:'   => ['Factura número:'],
            'Travel Date:'     => ['Fecha de Viaje:'],
            'Hello'            => 'Hola',
            // 'Flight:' => '',
            'Train:'           => 'Tren:',
            'Bus:'             => 'Autobús:',
            'statusPhrases'    => ['Tu billete ha sido', 'Tus billetes han sido'],
            'statusVariants'   => ['cancelado', 'cancelados'],
            'cancelledPhrases' => [
                'Tu billete ha sido cancelado',
                'Tus billetes han sido cancelados',
                'Billete cancelado:',
            ],
            'Carrier:' => 'Compañía de transporte:',
        ],
        'it' => [
            'Ticket number:'   => ['Numero biglietto:'],
            'Travel Date:'     => ['Data di Viaggio:'],
            'Hello'            => 'Ciao',
            // 'Flight:' => '',
            'Train:'           => 'Treno:',
            // 'Bus:'           => ':',
            'statusPhrases'    => ['Il biglietto è stato'],
            'statusVariants'   => ['cancellato'],
            'cancelledPhrases' => [
                'Il biglietto è stato cancellato',
                'Biglietto cancellato:',
            ],
            'Carrier:' => 'Operatore:',
        ],
        'en' => [
            'Ticket number:'   => ['Ticket number:'],
            'Travel Date:'     => ['Travel Date:'],
            'statusPhrases'    => ['Your tickets have been', 'Your ticket has been'],
            'statusVariants'   => ['cancelled', 'canceled'],
            'cancelledPhrases' => [
                'Your tickets have been cancelled', 'Your tickets have been canceled',
                'Your ticket has been cancelled', 'Your ticket has been canceled',
                'Cancelled ticket:', 'Canceled ticket:',
            ],
        ],
    ];

    private $subjects = [
        'es' => ['Confirmación de la cancelación de la reserva'],
        'en' => ['Booking cancellation confirmation', 'Booking cancelation confirmation'],
        'it' => ['Conferma di cancellazione della prenotazione'],
    ];

    private $detectors = [
        'es' => [
            'Tu billete ha sido cancelado',
            'Tus billetes han sido cancelados',
            'Billete cancelado:',
        ],
        'en' => [
            'Your tickets have been cancelled', 'Your tickets have been canceled',
            'Your ticket has been cancelled', 'Your ticket has been canceled',
            'Cancelled tickets:', 'Canceled tickets:',
        ],
        'it' => [
            'Il biglietto è stato cancellato.',
            'Biglietto cancellato:',
        ],
    ];

    private $isCancelled = false;
    private $traveller = '';

    public function detectEmailFromProvider($from)
    {
        return strpos($from, 'Omio Booking Team') !== false
            || stripos($from, '@omio.com') !== false;
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
        if ($this->http->XPath->query('//a[contains(@href,".omio.com/") or contains(@href,"emailservicelinks.omio.com")]')->length === 0
            && $this->http->XPath->query('//*[' . $this->contains(['Sincerely,The Omio Team', 'www.omio.com', 'Il Team di Omio']) . ']')->length === 0
        ) {
            return false;
        }

        return $this->detectBody() && $this->assignLang();
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();

        if (empty($this->lang)) {
            $this->logger->debug("Can't determine a language!");

            return $email;
        }
        $email->setType('CancelledTickets' . ucfirst($this->lang));

        if ($this->http->XPath->query("//*[{$this->contains($this->t('cancelledPhrases'))}]")->length > 0) {
            $this->isCancelled = true;
        }

        $this->traveller = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Hello'))}]", null, true, "/{$this->opt($this->t('Hello'))}[,\s]+([[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]])(?:\s*[,:;!?]|$)/u");

        $segmentsFlight = $this->http->XPath->query("//tr[ {$this->starts($this->t('Flight:'))} and following-sibling::tr[normalize-space()][1][{$this->starts($this->t('Travel Date:'))}] ]");
        $segmentsTrain = $this->http->XPath->query("//tr[ {$this->starts($this->t('Train:'))} and following-sibling::tr[normalize-space()][1][{$this->starts($this->t('Travel Date:'))}] ]");
        $segmentsBus = $this->http->XPath->query("//tr[ {$this->starts($this->t('Bus:'))} and following-sibling::tr[normalize-space()][1][{$this->starts($this->t('Travel Date:'))}] ]");

        // TRAINS
        if ($this->isCancelled && $segmentsFlight->length === 0 && ($segmentsTrain->length > 0 || $segmentsBus->length > 0)) {
            $email->setIsJunk(true, 'Empty confirmation number.');

            return $email;
        }

        // FLIGHTS
        $this->parseFlight($email, $segmentsFlight);

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

    private function parseFlight(Email $email, \DOMNodeList $segments): void
    {
        $f = $email->add()->flight();

        $status = $this->http->FindSingleNode("//text()[{$this->contains($this->t('statusPhrases'))}]", null, true, "/{$this->opt($this->t('statusPhrases'))}\s+({$this->opt($this->t('statusVariants'))})(?:\s*[,.:;?!]|$)/");
        $f->general()->status($status);

        if ($this->isCancelled) {
            $f->general()->cancelled();
        }

        $f->general()->traveller($this->traveller);

        $ticketNumbers = [];

        $segments = $this->http->XPath->query("//tr[ {$this->starts($this->t('Flight:'))} and following-sibling::tr[normalize-space()][1][{$this->starts($this->t('Travel Date:'))}] ]");

        foreach ($segments as $sRoot) {
            $s = $f->addSegment();

            $ticketNumber = $this->http->FindSingleNode("preceding-sibling::tr[{$this->starts($this->t('Ticket number:'))}]", $sRoot, true, "/{$this->opt($this->t('Ticket number:'))}\s*([- A-Z\d]{5,})$/");

            if ($ticketNumber) {
                $ticketNumbers[] = $ticketNumber;
            }

            $carrier = $this->http->FindSingleNode("preceding-sibling::tr[normalize-space()][1][{$this->starts($this->t('Carrier:'))}]", $sRoot, true, "/{$this->opt($this->t('Carrier:'))}\s*(.+)$/");
            $s->airline()->carrierName($carrier);

            $flight = $this->http->FindSingleNode('.', $sRoot);

            if (preg_match("/{$this->opt($this->t('Flight:'))}\s*(?<name>[A-z][A-z\d]|[A-z\d][A-z])\s*(?<number>\d+)$/", $flight, $m)) {
                // Flight: fr604
                $s->airline()->name($m['name'])->number($m['number']);
            }

            $date = $this->http->FindSingleNode("following-sibling::tr[normalize-space()][1][{$this->starts($this->t('Travel Date:'))}]", $sRoot, true, "/{$this->opt($this->t('Travel Date:'))}\s*(.*\d.*)$/");

            $route = implode(' ', $this->http->FindNodes("following-sibling::tr[normalize-space()][2]/descendant::text()[normalize-space()]", $sRoot));
            $routeParts = preg_split("/\s+-\s+/", $route);

            if (count($routeParts) !== 2) {
                $this->logger->debug('Wrong route row!');

                continue;
            }

            // 21:35 Copenhagen Airport (CPH)
            $pattern = "/^\s*(?<time>\d{1,2}(?:[:：]\d{2})?(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?)?)\s+(?<name>.{3,}?)\s*\(\s*(?<code>[A-Z]{3})\s*\)$/";

            if (preg_match($pattern, $routeParts[0], $m)) {
                $s->departure()->name($m['name'])->code($m['code'])->date2($date . ' ' . $m['time']);
            }

            if (preg_match($pattern, $routeParts[1], $m)) {
                $s->arrival()->name($m['name'])->code($m['code'])->date2($date . ' ' . $m['time']);
            }
        }

        $f->issued()->tickets(array_unique($ticketNumbers), false);

        $f->general()->noConfirmation();
    }

    private function detectBody(): bool
    {
        if (!isset($this->detectors)) {
            return false;
        }

        foreach ($this->detectors as $phrases) {
            foreach ((array) $phrases as $phrase) {
                if (!is_string($phrase)) {
                    continue;
                }

                if ($this->http->XPath->query("//*[{$this->contains($phrase)}]")->length > 0) {
                    return true;
                }
            }
        }

        return false;
    }

    private function assignLang(): bool
    {
        if (!isset(self::$dictionary, $this->lang)) {
            return false;
        }

        foreach (self::$dictionary as $lang => $phrases) {
            if (!is_string($lang) || empty($phrases['Ticket number:']) || empty($phrases['Travel Date:'])) {
                continue;
            }

            if ($this->http->XPath->query("//*[{$this->contains($phrases['Ticket number:'])}]")->length > 0
                && $this->http->XPath->query("//*[{$this->contains($phrases['Travel Date:'])}]")->length > 0
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
}
