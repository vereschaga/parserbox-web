<?php

namespace AwardWallet\Engine\ctrip\Email;

// TODO: delete what not use
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class ImportantAboutFlight extends \TAccountChecker
{
    public $mailFiles = "ctrip/it-807773153.eml, ctrip/it-812420763.eml, ctrip/it-820186253.eml";

    public $lang;
    public static $dictionary = [
        'en' => [
            'Booking No.'               => 'Booking No.',
            'Name'                      => 'Name',
            'Airline Booking Reference' => 'Airline Booking Reference',
            'Ticket Number'             => 'Ticket Number',
        ],
        'ru' => [
            'Booking No.'               => 'Номер бронирования',
            'Name'                      => 'Полное имя',
            'Airline Booking Reference' => 'Код бронирования (PNR)',
            'Ticket Number'             => 'Номер билета',
        ],
        'es' => [
            'Booking No.'               => 'N.º de reserva',
            'Name'                      => 'Nombre',
            'Airline Booking Reference' => 'Localizador de la reserva',
            'Ticket Number'             => 'N.º de billete',
        ],
        'it' => [
            'Booking No.'               => 'Prenotazione n.',
            'Name'                      => 'Nome',
            'Airline Booking Reference' => 'Codice di prenotazione della compagnia aerea',
            'Ticket Number'             => 'Numero del biglietto',
        ],
        'pt' => [
            'Booking No.'               => 'N.º da reserva',
            'Name'                      => 'Nome',
            'Airline Booking Reference' => 'Referência de reserva da companhia aérea/Localizador',
            'Ticket Number'             => 'Número da passagem',
        ],
        'fr' => [
            'Booking No.'               => 'N° réservation',
            'Name'                      => 'Nom',
            'Airline Booking Reference' => 'Numéro du dossier passager',
            'Ticket Number'             => 'Numéro(s) de billet',
        ],
    ];

    private $detectFrom = "_noreply@trip.com";
    private $detectSubject = [
        // en ,  Important: About Your Qatar Airways Flight
        'Important: About Your ',
        //  Your Ryanair Flight - Important Information
        ' Flight - Important Information',
        // ru
        'Важно: о вашем рейсе с',
        // es
        'Importante: Sobre tu vuelo con',
        '- información importante',
        // it
        'Importante: informazioni sul tuo volo',
        // pt
        'Importante: sobre o seu voo na',
        // fr
        'Important : A propos de votre vol avec',
    ];
    private $detectBody = [
        'en' => [
            'the following information will be of great help for a smooth journey',
            'To ensure a smooth journey, please read the important information below',
        ],
        'ru' => [
            'следующая информация будет очень полезна для беспроблемного путешествия',
        ],
        'es' => [
            'la siguiente información será de gran ayuda para un viaje sin problemas',
            'Para garantizar un viaje sin problemas, por favor lea la información importante a continuación',
        ],
        'it' => [
            'le seguenti informazioni saranno di grande aiuto per un viaggio senza intoppi',
        ],
        'pt' => [
            'as informações a seguir serão de grande ajuda para uma viagem tranquila',
        ],
        'fr' => [
            'les informations suivantes seront d\'une grande utilité pour un voyage sans encombre',
        ],
    ];

    public function detectEmailFromProvider($from)
    {
        return preg_match("/[@.]trip\.com$/", $from) > 0;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (stripos($headers["from"], $this->detectFrom) === false) {
            return false;
        }

        foreach ($this->detectSubject as $dSubject) {
            if (mb_stripos($headers["subject"], $dSubject) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        // detect Provider
        if (
            $this->http->XPath->query("//a/@href[{$this->contains(['www.trip.com', '.trip.com/'])}]")->length === 0
            && $this->http->XPath->query("//*[{$this->contains([' Trip.com ', ' Trip.com. '])}]")->length === 0
        ) {
            return false;
        }
        // detect Format
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

    private function assignLang()
    {
        foreach (self::$dictionary as $lang => $dict) {
            if (!empty($dict["Booking No."]) && !empty($dict["Airline Booking Reference"])) {
                if ($this->http->XPath->query("//*[{$this->contains($dict['Booking No.'])}]")->length > 0
                    && $this->http->XPath->query("//*[{$this->contains($dict['Airline Booking Reference'])}]")->length > 0
                ) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function parseEmailHtml(Email $email)
    {
        // Travel Agency
        $conf = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Booking No.'))}]/following::text()[normalize-space()][1]",
            null, true, "/^\s*(\d{5,})\s*$/");
        $email->ota()
            ->confirmation($conf);

        // Flights
        $f = $email->add()->flight();

        // General
        $f->general()
            ->noConfirmation();

        $tNodes = $this->http->XPath->query("//tr[*[1][{$this->eq($this->t('Name'))}]][*[3][{$this->eq($this->t('Ticket Number'))}]]/following-sibling::tr[normalize-space()]");

        foreach ($tNodes as $tRoot) {
            $name = trim(preg_replace("/\s*\([^\(\)]+(?:\([^\(\)]+\))?[^\(\)]+\)\s*/", ' ',
                $this->http->FindSingleNode("*[1]", $tRoot)));

            if (!in_array($name, array_column($f->getTravellers(), 0))) {
                $f->general()
                    ->traveller($name, true);
            }

            $ticket = $this->http->FindSingleNode("*[3]", $tRoot, true, "/^\s*(\d+[\d\-]+)\s*$/");

            if (!empty($ticket) && !in_array($ticket, array_column($f->getTicketNumbers(), 0))) {
                $f->issued()
                    ->ticket($ticket, false, $name);
            }
        }

        // Segments
        $nodes = $this->http->XPath->query("//tr[*[1][{$this->eq($this->t('Name'))}]]/preceding::text()[normalize-space()][1]/ancestor::tr[contains(., '|')][1]");

        foreach ($nodes as $root) {
            $s = $f->addSegment();

            $text = implode("\n", $this->http->FindNodes(".//text()[normalize-space()]", $root));
            // $this->logger->debug('$text = '.print_r( $text,true));

            // Airline
            if (preg_match("/\n\s*(?<al>[A-Z][A-Z\d]|[A-Z\d][A-Z]) ?(?<fn>\d{1,4})\s*$/", $text, $m)) {
                $s->airline()
                    ->name($m['al'])
                    ->number($m['fn']);
            } elseif (preg_match("/\n\s*(?<al>[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*$/", $text, $m)) {
                $s->airline()
                    ->name($m['al'])
                    ->noNumber();
            }
            $conf = $this->http->FindSingleNode("following::text()[normalize-space()][1][{$this->eq($this->t('Name'))}]/ancestor::tr[*[2][{$this->eq($this->t('Airline Booking Reference'))}]]/following-sibling::tr[normalize-space()][1]/*[2]",
                $root, true, "/^\s*[A-Z\d]{5,7}\s*$/");
            $s->airline()
                ->confirmation($conf);

            //Depart, Arrival
            if (preg_match("/^\s*(?<d>.+) - (?<a>.+)\s*\|\s*(?<date>.+)\n/", $text, $m)) {
                $s->departure()
                    ->noCode()
                    ->name($m['d'])
                    ->date($this->normalizeDate($m['date']));
                $s->arrival()
                    ->noCode()
                    ->name($m['a'])
                    ->noDate();
            }
        }

        return true;
    }

    private function t($s)
    {
        if (!isset($this->lang, self::$dictionary[$this->lang], self::$dictionary[$this->lang][$s])) {
            return $s;
        }

        return self::$dictionary[$this->lang][$s];
    }

    // additional methods
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
        // $this->logger->debug('date begin = ' . print_r( $date, true));

        $in = [
            // 17:30, October 1, 2024
            '/^\s*(\d{1,2}:\d{2}(?:\s*[ap]m)?)\s*[,\s]\s*([[:alpha:]]+)\s+(\d{1,2})\s*[,\s]\s*(\d{4})\s*$/ui',
            // 22:10 16 декабря 2024 г.
            '/^\s*(\d{1,2}:\d{2}(?:\s*[ap]m)?)\s*[,\s]\s*(\d{1,2})\s+([[:alpha:]]+)\s*[,\s]\s*(\d{4})(?:\s*г\.)?\s*$/ui',
            // 18 de diciembre de 2024, 11:25
            // 18 janvier 2025, 16 h 50
            '/^\s*(\d{1,2})\s+(?:de\s+)?([[:alpha:]]+)\s+(?:de\s+)?(\d{4})\s*[,\s]\s*(\d{1,2})(?::| h )(\d{2}(?:\s*[ap]m)?)\s*$/ui',
        ];
        $out = [
            '$3 $2 $4, $1',
            '$2 $3 $4, $1',
            '$1 $2 $3, $4:$5',
        ];
        // $this->logger->debug('date replace = ' . print_r( $date, true));

        $date = preg_replace($in, $out, $date);

        if (preg_match("#\d+\s+([^\d\s]+)\s+(?:\d{4}|%year%)#", $date, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $date = str_replace($m[1], $en, $date);
            }
        }

        // $this->logger->debug('date end = ' . print_r( $date, true));

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
