<?php

namespace AwardWallet\Engine\booksy\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;

class Booking extends \TAccountChecker
{
    public $mailFiles = "booksy/it-788220778.eml, booksy/it-788366177.eml, booksy/it-789392274.eml, booksy/it-789409335.eml, booksy/it-791274875.eml, booksy/it-793172578.eml, booksy/it-795857067.eml, booksy/it-799667196.eml, booksy/it-800705491.eml";

    public $lang;
    public static $dictionary = [
        'en' => [
            'has confirmed your booking' => ['has confirmed your booking', 'like to remind you about your appointment on',
                'has been cancelled.', 'New date of appointment', ],
            'Get directions' => ['Get directions', 'Try another date'],
            'cancelledText'  => 'has been cancelled.',
        ],
        'fr' => [
            'has confirmed your booking' => 'vient de confirmer votre réservation pour',
            'Get directions'             => ["Voir l’itinéraire", 'Voir l\'itinéraire'],
            // 'cancelledText'  => 'has been cancelled.',
        ],
        'pt' => [
            'has confirmed your booking' => ['confirmou seu agendamento', 'reagendou sua reserva em', 'Nova data de agendamento'],
            'Get directions'             => ["Seja direcionado aqui", "Obter direções", 'Get directions'],
            // 'cancelledText'  => 'has been cancelled.',
        ],
        'es' => [
            'has confirmed your booking' => ['ha confirmado tu reserva', 'Nueva fecha de la cita', 'ha sido cancelada.', 'Tu solicitud de modificación de la reserva con'],
            'Get directions'             => ["Como llegar", 'Obtener direcciones', 'Prueba otra fecha'],
            'cancelledText'              => 'ha sido cancelada.',
        ],
    ];

    private $detectFrom = "no-reply@booksy.com";
    private $detectSubject = [
        // en
        'Your booking has been confirmed',
        'Reminder about the appointment with:',
        'Your booking has been cancelled',
        'Business has changed the date of your appointment and is waiting for confirmation',
        // fr
        'Votre réservation est confirmée',
        // pt
        'Seu agendamento foi confirmado',
        'reagendou sua reserva',
        'empresa alterou a data do seu agendamento e está aguardando confirmação',
        // es
        'Tu reserva ha sido confirmada',
        'Tu reserva ha sido cancelada',
        'El negocio ha cambiado la fecha de tu cita y está esperando confirmación',
        'El negocio ha cambiado la fecha de tu cita y está esperando la confirmación',
        'Se ha enviado tu solicitud de modificación de la reserva con',
    ];

    public function detectEmailFromProvider($from)
    {
        return preg_match("/[@.]booksy\.com$/", $from) > 0;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (stripos($headers["from"], $this->detectFrom) === false) {
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
        if ($this->http->XPath->query("//*[{$this->contains(['booksy.com'])}]")->length === 0) {
            return false;
        }

        foreach (self::$dictionary as $dict) {
            if (!empty($dict["has confirmed your booking"])) {
                if ($this->http->XPath->query("//*[{$this->contains($dict['has confirmed your booking'])}]")->length > 0
                ) {
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
            if (!empty($dict["Get directions"]) && !empty($dict["has confirmed your booking"])
                && $this->http->XPath->query("//*[{$this->eq($dict['Get directions'])}]")->length > 0
                && $this->http->XPath->query("//*[{$this->contains($dict['has confirmed your booking'])}]")->length > 0
            ) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
    }

    private function parseEmailHtml(Email $email)
    {
        $xpath = "//text()[{$this->eq($this->t('Get directions'))}]/preceding::text()[normalize-space()][1]/ancestor::*[not({$this->contains($this->t('has confirmed your booking'))})][last()]";
        // $this->logger->debug('$xpath = '.print_r( $xpath,true));

        $text = implode("\n", $this->http->FindNodes($xpath . "//text()[normalize-space()]"));
        // $this->logger->debug('$text = '.print_r( $text,true));

        if (preg_match("/^\s*(?<pName>.+)\n(?<pAddress>.+)\n(?<date>.+, )\d{1,2}:\d{2}.* - \d{1,2}:\d{2}.*\n/", $text, $m)) {
            $nodes = $this->http->XPath->query($xpath . "//tr[count(*) = 2][not(.//a)][not({$this->eq($this->t('Get directions'))})][*[1][not(normalize-space())][.//img]][*[2][normalize-space()]]");

            foreach ($nodes as $root) {
                $event = $email->add()->event();

                $event->type()->event();

                // General
                $event->general()
                    ->noConfirmation();

                if ($this->http->XPath->query("//node()[{$this->contains($this->t('cancelledText'))}]")->length > 0) {
                    $event->general()
                        ->cancelled()
                        ->status('Cancelled');
                }

                $sText = implode("\n", $this->http->FindNodes(".//text()[normalize-space()]", $root));
                // $this->logger->debug('$sText = ' . print_r($sText, true));
                if (preg_match("/^\s*(?<eventName>.+)\n(?:(?<price>.+),)? *(?<sTime>\d{1,2}:\d{2}.*) - (?<eTime>\d{1,2}:\d{2}.*)\n/", $sText, $mat)
                ) {
                    // Place
                    $event->place()
                        ->name(str_replace(['<', '{', '>', '}'], ['[', '[', ']', ']'], $mat['eventName'] . ' (' . $m['pName'] . ')'))
                        ->address($m['pAddress']);

                    // Booked
                    $event->booked()
                        ->start($this->normalizeDate($m['date'] . $mat['sTime']))
                        ->end($this->normalizeDate($m['date'] . $mat['eTime']));

                    $mat['price'] = trim($mat['price'] ?? '', '+');

                    if (preg_match("/^\s*(?<currency>[^\d\s]{1,5})\s*(?<amount>\d[\d\., ]*)\s*$/u", $mat['price'], $mp)
                        || preg_match("/^\s*(?<amount>\d[\d\., ]*)\s*(?<currency>[^\d\s]{1,5})\s*$/u", $mat['price'], $mp)
                    ) {
                        $currency = $this->currency($mp['currency']);
                        $value = PriceHelper::parse($mp['amount'], $currency);

                        if (is_numeric($value)) {
                            $value = (float) $value;
                        } else {
                            $value = null;
                        }
                        $event->price()
                            ->total($value)
                            ->currency($currency);
                    }
                }
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
            // jeudi 4 janvier 2024, 11:00
            '/^\s*[[:alpha:]\-]+\s*[\s,]+\s*(\d{1,2})\s+(?:de\s+)?([[:alpha:]]+)\s+(?:de\s+)?(\d{4})\s*,\s+(\d{1,2}:\d{2}(?:\s*[ap]m)?)\s*$/ui',
        ];
        $out = [
            '$1 $2 $3, $4',
        ];

        $date = preg_replace($in, $out, $date);

//        $this->logger->debug('date replace = ' . print_r( $date, true));

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $date, $m)) {
            if ($en = \AwardWallet\Engine\MonthTranslate::translate($m[1], $this->lang)) {
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

    private function currency($s)
    {
        $s = trim($s);

        if (preg_match("#^\s*([A-Z]{3})\s*$#", $s)) {
            return $s;
        }
        $sym = [
            '$'  => 'USD',
            '€'  => 'EUR',
            '£'  => 'GBP',
            'R$' => 'BRL',
            'zł' => 'PLN',
        ];

        foreach ($sym as $f => $r) {
            if ($s == $f) {
                return $r;
            }
        }

        return $s;
    }
}
