<?php

namespace AwardWallet\Engine\civitatis\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use AwardWallet\Schema\Parser\Common\Event;

class YourBooking extends \TAccountChecker
{
    public $mailFiles = "civitatis/it-651252822.eml, civitatis/it-659650293-es.eml, civitatis/it-713794350-es.eml, civitatis/it-332539723-it.eml, civitatis/it-706178876-pt.eml, civitatis/it-705362703-pt.eml, civitatis/it-780631409-es.eml, civitatis/it-784633203-junk.eml, civitatis/it-844861226-es.eml";

    public $lang = '';

    public static $dictionary = [
        'pt' => [
            'How to get there' => ['Como chegar'],
            'collectionPoint'  => ['Ponto de recolha:', 'Ponto de recolha :'],
            // 'locatedIn' => '',
            'otaConfNumber'    => ['Reserva:', 'Reserva :'],
            'confNumber'       => [
                'Número da reserva:', 'Número da reserva :',
                'Nï¿½mero da reserva:', 'Nï¿½mero da reserva :',
            ],
            'requestPhrases'   => ['já está quase', 'jï¿½ estï¿½ quase'],
            'statusPhrases'    => ['a sua reserva está', 'a sua reserva estï¿½'],
            'statusVariants'   => ['confirmada'],
            // 'Event:' => [''],
            // 'at' => '',
            'duration'         => ['Duração:', 'Duraï¿½ï¿½o:'],
            'hour'             => ['horas', 'hora'],
            'minute'           => ['minutos'],
            // 'Hour:' => '',
            // 'returnTime' => '',
            'person'                    => ['pessoas', 'pessoa'],
            'numberOfGuests' => [
                'Número de pessoas:', 'Número de pessoas :',
                'Nï¿½mero de pessoas:', 'Nï¿½mero de pessoas :',
            ],
            'Your personal information' => 'Os seus dados pessoais',
            'More information here'     => ['Informação adicional', 'Informaï¿½ï¿½o adicional'],
            'totalPrice'                => ['Preço total', 'Preï¿½o total'],
        ],
        'es' => [
            'How to get there' => ['Cómo llegar', 'Cï¿½mo llegar'],
            'collectionPoint'  => [
                'Dirección punto recogida:', 'Dirección punto recogida :',
                'Direcciï¿½n punto recogida:', 'Direcciï¿½n punto recogida :',
                'Punto de recogida:', 'Punto de recogida :',
                'Puntos de encuentro', 'Punto de encuentro',
            ],
            'locatedIn' => ['ubicada en la', 'punto de encuentro:'],
            'otaConfNumber'    => ['Reserva:', 'Reserva :'],
            'confNumber'       => [
                'Número de reserva:', 'Número de reserva :',
                'Nï¿½mero de reserva:', 'Nï¿½mero de reserva :',
            ],
            'requestPhrases' => 'confirma tu reserva',
            'statusPhrases'    => ['tu reserva está', 'tu reserva estï¿½'],
            'statusVariants'   => ['confirmada'],
            // 'Event:' => [''],
            // 'at' => '',
            'duration'         => ['Duración:', 'Duraciï¿½n:'],
            'hour'             => ['horas', 'hora'],
            // 'minute' => '',
            // 'Hour:' => '',
            // 'returnTime' => '',
            'person'                    => ['personas', 'person'],
            // 'numberOfGuests' => '',
            'Your personal information' => 'Tus datos personales',
            'More information here'     => ['Información adicional', 'Informaciï¿½n adicional'],
            'totalPrice'                => 'Precio total',
        ],
        'it' => [
            'How to get there' => ['Come arrivare'],
            // 'collectionPoint' => '',
            // 'locatedIn' => '',
            'otaConfNumber' => ['Prenotazione:', 'Prenotazione :'],
            'confNumber'       => [
                'Codice identificativo della prenotazione:', 'Codice identificativo della prenotazione :',
            ],
            // 'requestPhrases' => '',
            'statusPhrases'    => ['la tua prenotazione è', 'la tua prenotazione ï¿½'],
            'statusVariants'   => ['confermata'],
            // 'Event:' => [''],
            // 'at' => '',
            'duration'         => 'Durata:',
            'hour'             => 'ore',
            // 'minute' => '',
            // 'Hour:' => '',
            // 'returnTime' => '',
            // 'person' => '',
            // 'numberOfGuests' => '',
            'Your personal information' => 'I tuoi dati personali',
            'More information here'     => 'Informazione aggiuntiva',
            'totalPrice'                => 'Prezzo totale',
        ],
        'en' => [
            'How to get there' => ['How to get there'],
            'collectionPoint'  => [
                'Pick up point:', 'Pick up point :',
                'Pickup point:', 'Pickup point :',
            ],
            // 'locatedIn' => '',
            'otaConfNumber'    => ['Booking:', 'Booking :'],
            'confNumber'       => [
                'Reservation number:', 'Reservation number :',
                'Confirmation Code:', 'Confirmation Code :',
                'Order #:', 'Order # :',
            ],
            'requestPhrases'   => 'please confirm your booking',
            'statusPhrases'    => ['your booking is'],
            'statusVariants'   => ['confirmed'],
            'Event:'           => ['Event:', 'Event :'],
            // 'at' => '',
            'duration'         => 'Duration:',
            'hour'             => ['hours', 'hour'],
            'minute'           => ['minutes', 'minute'],
            // 'Hour:' => '',
            'returnTime' => ['Return Time:', 'Return Time :'],
            'person'     => ['person', 'people'],
            // 'numberOfGuests' => '',
            // 'Your personal information' => '',
            // 'More information here' => '',
            'totalPrice' => 'Total price',
        ],
    ];

    private $subjects = [
        'pt' => ['Complete a reserva', 'Mensagem sobre a reserva'],
        'es' => ['Reserva confirmada', 'Confirma tu reserva'],
        'it' => ['Prenotazione confermata'],
        'en' => ['Reservation confirmed', 'Confirm your booking'],
    ];

    private $junkNames = [
        'Lisboa Card',
        'Excursión a Guatapé',
        'Excursiï¿½n a Guatapï¿½',
        'Tour privado por Tokio',
        'Ingresso do Mini-Europe',
        'Ingresso do Parque Warner',
        'Seguro de viaje Civitatis',
        'Paseo en barco por el Sena',
        'Autobús turístico de Halifax',
        'Autobï¿½s turï¿½stico de Halifax',
        'Ingresso para KÀ, Cirque du Soleil',
        'Excursão à praia de Canoa Quebrada',
        'Ingresso para Kï¿½, Cirque du Soleil',
        'Tarjeta eSIM Civitatis Estados Unidos',
        'Excursão a Machu Picchu com ingressos',
        'Excursï¿½o ï¿½ praia de Canoa Quebrada',
        'Excursï¿½o a Machu Picchu com ingressos',
        'Autobús turístico de Londres, Big Bus',
        'Autobús turístico de Ciudad de Panamá',
        'Autobï¿½s turï¿½stico de Ciudad de Panamï¿½',
        'Autobï¿½s turï¿½stico de Londres, Big Bus',
        'Tour privado por Montreal com guia em português',
        'Tour privado por Montreal com guia em portuguï¿½s',
        'Entrada al Museo Van Gogh + Crucero por los canales',
        'Donación para los afectados de la DANA, Civitatis duplica tu aportación',
        'Donaciï¿½n para los afectados de la DANA, Civitatis duplica tu aportaciï¿½n',
    ];

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[.@]civitatis\.com$/i', $from) > 0;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->detectEmailFromProvider(rtrim($headers['from'], '> ')) !== true && strpos($headers['subject'], 'Civitatis.com') === false) {
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
        if ($this->detectEmailFromProvider($parser->getCleanFrom()) !== true
            && $this->http->XPath->query('//img[contains(@src,"civitatis.com/") and contains(@src,"/youtube")]')->length === 0
            && $this->http->XPath->query('//*[contains(normalize-space(),"Gracias por confiar en Civitatis")]')->length === 0
        ) {
            return false;
        }

        return $this->findRoot()->length > 0 || $this->isJunk();
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();
        $email->setType('YourBooking' . ucfirst($this->lang));

        $ev = $this->parseEvent($email);

        if ((empty($ev->getAddress()) || empty($ev->getStartDate())) && $this->isJunk()) {
            $email->clearItineraries();
            $email->setIsJunk(true, 'Event address or start date is empty');
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

    private function parseEvent(Email $email): Event
    {
        $patterns = [
            'date'          => '.{3,}\b\d{4}\b', // 12 March 2022
            'time'          => '\d{1,2}(?:[:：]\d{2})?(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?)?', // 4:19PM    |    2:00 p. m.    |    3pm
            'travellerName' => '[[:alpha:]ï¿½][-.\'’[:alpha:]ï¿½ ]*[[:alpha:]ï¿½]', // Mr. Hao-Li Huang
        ];

        $ev = $email->add()->event();
        $ev->type()->event();

        $statusTexts = array_filter($this->http->FindNodes("//text()[{$this->contains($this->t('statusPhrases'))}]", null, "/{$this->opt($this->t('statusPhrases'))}[:\s]+({$this->opt($this->t('statusVariants'))})(?:\s*[,.;:!?]|$)/i"));

        if (count(array_unique(array_map('mb_strtolower', $statusTexts))) === 1) {
            $status = array_shift($statusTexts);
            $ev->general()->status($status);
        }

        $otaConfirmation = $this->http->FindSingleNode("//text()[{$this->starts($this->t('otaConfNumber'))}]/following::text()[normalize-space()][1]", null, true, '/^[-A-Z\d]{5,}$/');

        if ($otaConfirmation) {
            $otaConfirmationTitle = $this->http->FindSingleNode("//text()[{$this->starts($this->t('otaConfNumber'))}]", null, true, '/^(.+?)[\s:：]*$/u');
            $email->ota()->confirmation($otaConfirmation, $otaConfirmationTitle);
        }

        $confNoRoots = $this->http->XPath->query("//text()[{$this->eq($this->tPlusEn('confNumber'))}]");

        if ($confNoRoots->length > 0) {
            foreach ($confNoRoots as $confNoRoot) {
                $confirmation = $this->http->FindSingleNode("following::text()[normalize-space()][1]", $confNoRoot, true, '/^[-A-Z\d]{4,40}$/');

                if ($confirmation) {
                    $confirmationTitle = $this->http->FindSingleNode(".", $confNoRoot, true, '/^(.+?)[\s:：]*$/u');
                    $ev->general()->confirmation($confirmation, $confirmationTitle);
                }
            }
        } elseif ($otaConfirmation) {
            $ev->general()->noConfirmation();
        }

        $totalPrice = $this->http->FindSingleNode("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('totalPrice'), "translate(.,':','')")}] ]/*[normalize-space()][2]", null, true, '/^.*\d.*$/');

        if (preg_match('/^(?<currency>[^\-\d)(]+?)[ ]*(?<amount>\d[,.‘\'\d ]*?)(?:\s*\(|$)/u', $totalPrice, $matches)
            || preg_match('/^(?<amount>\d[,.‘\'\d ]*?)[ ]*(?<currency>[^\-\d)(]+?)(?:\s*\(|$)/u', $totalPrice, $matches)
        ) {
            // € 0.00    |    0 £    |    R$ 495,6 (€83)
            $currency = $this->normalizeCurrency($matches['currency']);
            $currencyCode = preg_match('/^[A-Z]{3}$/', $currency) ? $currency : null;
            $ev->price()->currency($currency)->total(PriceHelper::parse($matches['amount'], $currencyCode));
        }

        $traveller = $this->http->FindSingleNode("//*[ count(*[normalize-space()])>1 and count(*[normalize-space()])<5 and *[normalize-space()][1][{$this->eq($this->t('Your personal information'))}] ]/*[normalize-space()][2]", null, true, "/^{$patterns['travellerName']}$/u");
        $isNameFull = true;

        if (!$traveller) {
            $travellerNames = array_filter($this->http->FindNodes("//text()[{$this->contains($this->t('requestPhrases'))} or {$this->contains($this->t('statusPhrases'))}]", null, "/^({$patterns['travellerName']})\s*,\s*(?:{$this->opt($this->t('requestPhrases'))}|{$this->opt($this->t('statusPhrases'))})/u"));

            if (count(array_unique($travellerNames)) === 1) {
                $traveller = array_shift($travellerNames);
                $isNameFull = null;
            }
        }

        if ($this->http->XPath->query("//*[{$this->contains($this->t('Your personal information'))} or {$this->contains($this->t('requestPhrases'))} or {$this->contains($this->t('statusPhrases'))}]")->length > 0) {
            $ev->general()->traveller($traveller, $isNameFull);
        }

        $moreInfo = $this->http->FindSingleNode("//*[ count(*[normalize-space()])>1 and count(*[normalize-space()])<11 and *[normalize-space()][1][{$this->eq($this->t('More information here'))}] ]", null, true, "/^{$this->opt($this->t('More information here'))}[:\s]*(.{5,})$/");
        $ev->general()->notes($moreInfo);

        $roots = $this->findRoot();

        if ($roots->length !== 1) {
            $this->logger->debug('Root-node not found!');

            return $ev;
        }
        $root = $roots->item(0);

        $guests = null;
        $guestsTexts = array_filter($this->http->FindNodes("tr[{$this->contains($this->tPlusEn('person'))}]", $root, "/\b(\d{1,3})\s*{$this->opt($this->tPlusEn('person'))}/i"));

        if (count(array_unique($guestsTexts)) === 1) {
            $guests = array_shift($guestsTexts);
        } else {
            $guests = $this->http->FindSingleNode("//text()[{$this->eq($this->t('numberOfGuests'))}]/following::text()[normalize-space()][1]", null, true, '/^\d{1,3}$/');
        }

        if ($guests !== null) {
            $ev->booked()->guests($guests);
        }

        $duration = ['hours' => null, 'minutes' => null];
        $durationVal = $this->http->FindSingleNode("//text()[{$this->eq($this->t('duration'))}]/following::text()[normalize-space()][1]", null, true, "/^\d{1,3}\s*[^\d\s].*/");

        if (preg_match("/^(?<h>\d{1,3})\s*(?:h|{$this->opt($this->tPlusEn('hour'))})\W+(?<m>\d{1,3})\s*(?:m|min|{$this->opt($this->tPlusEn('minute'))})$/iu", $durationVal, $m)) {
            // 2h 15m
            $duration['hours'] = $m['h'];
            $duration['minutes'] = $m['m'];
        } elseif (preg_match("/^(?<h>\d{1,3})\s*(?:h|{$this->opt($this->tPlusEn('hour'))})$/iu", $durationVal, $m)) {
            // 2 h
            $duration['hours'] = $m['h'];
        } elseif (preg_match("/^(?<m>\d{1,3})\s*(?:m|min|{$this->opt($this->tPlusEn('minute'))})$/iu", $durationVal, $m)) {
            // 15 m
            $duration['minutes'] = $m['m'];
        }

        $date = strtotime(self::normalizeDate($this->http->FindSingleNode("tr[normalize-space()][1]", $root, true, "/^{$patterns['date']}$/"), $this->lang));

        $timeStart = $this->http->FindSingleNode("tr[normalize-space()][2]", $root, true, "/^({$patterns['time']})(?:\s*\(|$)/")
            ?? $this->http->FindSingleNode("//text()[{$this->eq($this->tPlusEn('Event:'))}]/following::text()[normalize-space()][1]", null, true, "/^.{2,}\s{$this->opt($this->tPlusEn('at'))}\s+({$patterns['time']})$/")
            ?? $this->http->FindSingleNode("//text()[{$this->eq($this->t('Hour:'))}]/following::text()[normalize-space()][1]", null, true, "/^({$patterns['time']})(?:\s*\(|$)/")
        ;

        $timeEnd = $this->http->FindSingleNode("//text()[{$this->eq($this->tPlusEn('returnTime'))}]/following::text()[normalize-space()][1]", null, true, "/^({$patterns['time']})(?:\s*\(|$)/");

        $eventName = implode('. ', $this->http->FindNodes("ancestor-or-self::*[ preceding-sibling::*[normalize-space()] ][1][count(preceding-sibling::*[normalize-space()])<3]/preceding-sibling::*[normalize-space()]", $root));
        $address = null;

        // it-705362703-pt.eml
        foreach ($this->tPlusEn('collectionPoint') as $phrase) {
            $address_temp = $this->http->FindSingleNode("//text()[{$this->eq($phrase)}]/following::text()[normalize-space()][1][not(ancestor::*[self::li or self::p]/descendant::text()[normalize-space()][2])]", null, true, "/^(.{3,130}?)[,.;\s]*$/");

            if (preg_match("/^({$patterns['time']})[-\s]+([^\-\s].{2,})$/", $address_temp ?? '', $m)) {
                if (empty($timeStart)) {
                    $timeStart = $m[1];
                }
                $address_temp = $m[2];
            }

            if ($address_temp) {
                $address = $address_temp;

                break;
            }
        }

        if (!$address) {
            // it-780631409-es.eml
            foreach ((array) $this->t('collectionPoint') as $phrase) {
                $address_temp = $this->http->FindSingleNode("//text()[{$this->eq($phrase)}]/following::text()[normalize-space()][1]/ancestor::li[1]/descendant::text()[ normalize-space() and preceding::text()[normalize-space()][1][{$this->contains($this->t('locatedIn'))}] ]", null, true, "/^(.{3,115}?)[,.;\s]*$/")
                ?? $this->http->FindSingleNode("//p[{$this->eq($phrase)}]/following-sibling::p/descendant::text()[ normalize-space() and preceding::text()[normalize-space()][1][{$this->contains($this->t('locatedIn'))}] ]", null, true, "/^(.{3,115}?)[,.;\s]*$/");

                if ($address_temp) {
                    $address = $address_temp;

                    break;
                }
            }
        }

        if (!$address) {
            $address = $this->http->FindSingleNode("tr[ descendant::node()[{$this->starts($this->t('How to get there'))}] ]", $root, true, "/^(.{3,115}?)[.\s]*{$this->opt($this->t('How to get there'))}/");
        }

        $ev->place()->name($eventName)->address($address);

        if ($date && $timeStart) {
            $ev->booked()->start(strtotime($timeStart, $date));
        }

        if ($date && $timeEnd) {
            // it-713794350-es.eml
            $ev->booked()->end(strtotime($timeEnd, $date));
        } elseif (!empty($ev->getStartDate()) && ($duration['hours'] !== null || $duration['minutes'] !== null)) {
            $dateEnd = $ev->getStartDate();

            if ($duration['hours'] !== null) {
                $dateEnd = strtotime("+{$duration['hours']} hours", $dateEnd);
            }

            if ($duration['minutes'] !== null) {
                $dateEnd = strtotime("+{$duration['minutes']} minutes", $dateEnd);
            }

            $ev->booked()->end($dateEnd);
        } elseif (!empty($ev->getStartDate())) {
            $ev->booked()->noEnd();
        }

        return $ev;
    }

    private function findRoot(): \DOMNodeList
    {
        return $this->http->XPath->query("//*[count(tr[ normalize-space() and count(*)=3 and *[1][descendant::img and normalize-space()=''] ])>1]");
    }

    private function isJunk(): bool
    {
        $xpathColored = "ancestor-or-self::*[{$this->contains(['color:#f70759', 'color:#F70759', 'color:rgb(247,7,89)'], "translate(@style,' ','')")}]";
        $reservName = $this->http->FindSingleNode("//tr[ not(.//tr[normalize-space()]) and normalize-space() and count(descendant::text()[normalize-space()])=count(descendant::text()[normalize-space() and {$xpathColored}]) and following::tr[not(.//tr[normalize-space()]) and normalize-space()][1][*[1][descendant::img and normalize-space()=''] and count(*)=3] ]");

        if ($reservName !== null) {
            foreach ($this->junkNames as $junkName) {
                if (strcasecmp($junkName, $reservName) === 0) {
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

        // pass of finding #1: total price

        foreach (self::$dictionary as $lang => $phrases) {
            if (!is_string($lang)) {
                continue;
            }

            if (!empty($phrases['totalPrice']) && $this->http->XPath->query("//tr/*[{$this->eq($phrases['totalPrice'], "translate(.,':','')")}]")->length > 0
            ) {
                $this->lang = $lang;

                return true;
            }
        }

        // pass of finding #2: address phrase

        foreach (self::$dictionary as $lang => $phrases) {
            if (!is_string($lang)) {
                continue;
            }

            if (!empty($phrases['How to get there']) && $this->http->XPath->query("//*[{$this->contains($phrases['How to get there'])}]")->length > 0
                || !empty($phrases['collectionPoint']) && $this->http->XPath->query("//*[{$this->contains($phrases['collectionPoint'])}]")->length > 0
            ) {
                // because address is required field for event
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

    private function tPlusEn(string $s): array
    {
        return array_unique(array_merge((array) $this->t($s), (array) $this->t($s, 'en')));
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
    public static function normalizeDate(?string $text, string $lang): ?string
    {
        if (preg_match('/\b(\d{1,2})[.\s]+(?:de\s+)?([[:alpha:]]{3,})[.\s]+(?:de\s+)?(\d{4})$/iu', $text, $m)) {
            // 12 March 2022    |    Sábado, 27 de abril de 2024
            $day = $m[1];
            $month = $m[2];
            $year = $m[3];
        } elseif (preg_match('/\b([[:alpha:]]{3,})[.\s]+(\d{1,2})[,.\s]+(\d{4})$/iu', $text, $m)) {
            // March 12, 2022    |    Sábado, abril 27, 2024
            $month = $m[1];
            $day = $m[2];
            $year = $m[3];
        }

        if (isset($day, $month, $year)) {
            if (preg_match('/^\s*(\d{1,2})\s*$/', $month, $m)) {
                return $m[1] . '/' . $day . ($year ? '/' . $year : '');
            }

            if (($monthNew = MonthTranslate::translate($month, $lang)) !== false) {
                $month = $monthNew;
            }

            return $day . ' ' . $month . ($year ? ' ' . $year : '');
        }

        return null;
    }

    /**
     * @param string $string Unformatted string with currency
     */
    private function normalizeCurrency(string $string): string
    {
        $string = trim($string);
        $currencies = [
            // do not add unused currency!
            'USD' => ['US$'],
            'CLP' => ['CL$'],
        ];

        foreach ($currencies as $currencyCode => $currencyFormats) {
            foreach ($currencyFormats as $currency) {
                if ($string === $currency) {
                    return $currencyCode;
                }
            }
        }

        return $string;
    }
}
