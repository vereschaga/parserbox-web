<?php

namespace AwardWallet\Engine\iberia\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Common\FlightSegment;
use AwardWallet\Schema\Parser\Email\Email;

class BookingConfirmationHtml extends \TAccountChecker
{
    public $mailFiles = "iberia/it-1624084.eml, iberia/it-2392368.eml, iberia/it-2844651.eml, iberia/it-2844666.eml, iberia/it-34051957.eml, iberia/it-5945938.eml, iberia/it-5952195.eml";
    public static $body = [
        'en' => ['Flight Details'],
        'de' => ['Details der Passagiere.', 'Daten des Passagiers'],
        'es' => ['Detalles del vuelo', 'Detalle del vuelo'],
    ];
    public $lang = '';
    public static $dict = [
        'en' => [
            'confirmText'      => ['This email confirms', 'Purchase confirmed'],
            'Locator'          => ['Locator', 'Reservation code', 'Booking reference'],
            'Departure'        => ['Departure', 'Exit'],
            'TOTAL PRICE'      => ['TOTAL PRICE', 'Total price'],
            'Number of Ticket' => ['Number of Ticket', 'Ticket No.', 'Ticket no.'],
        ],
        'de' => [
            'confirmText'        => ['Kauf bestätigt', 'Buchung bestätigt'],
            'Locator'            => ['Buchungsnummer', 'Buchungscode'],
            'Loyalty Card'       => ['Loyalty Card', 'Treuekarte'],
            'Flight operated by' => ['Flug betrieben', 'Durchgeführt von'],
            'Departure'          => ['Abflug', 'Ausgang'],
            'Arrival'            => 'Ankunft',
            'TOTAL PRICE'        => ['Endpreis', 'Gesamtpreis'],
            'Flight'             => 'Flug',
            'Passenger'          => ['Passagiere', 'Passagier'],
            'Number of Ticket'   => ['N Ticket', 'Nr. des Flugscheins'],
        ],
        'es' => [
            'confirmText'        => ['Compra confirmada'],
            'Locator'            => 'Localizador',
            'Loyalty Card'       => 'Tarjeta de fidelización',
            'Flight operated by' => 'Vuelo operado por',
            'Departure'          => 'Salida',
            'Arrival'            => 'Llegada',
            'TOTAL PRICE'        => ['PRECIO TOTAL', 'Precio total'],
            'Flight'             => 'Vuelo',
            'Passenger'          => 'Pasajero',
            'Number of Ticket'   => ['N° de Billete', 'Nº de Billete'],
        ],
    ];
    private $subject = [
        'en' => ['Booking confirmation'],
        'de' => ['Kaufbestätigung'],
        'es' => ['Confirmación de reserva', 'Reserva confirmada'],
    ];

    //region Standard methods
    public function detectEmailByHeaders(array $headers)
    {
        return self::detectEmailFromProvider($headers['from']) === true
            && $this->detect($headers['subject'], $this->subject) !== null;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        // used in iberia/BookingConfirmationPdf by reason of it-1624084.eml
        return stripos($parser->getHTMLBody(), 'iberia') !== false
            && self::detect($parser->getHTMLBody(), self::$body) !== null;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]iberiaexpress\./i', $from) > 0;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->lang = $this->detect($this->http->Response['body'], self::$body);

        if (!isset($this->lang)) {
            $this->logger->debug('can\'t determine a language');

            return $email;
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        if (!$this->parseEmail($email)) {
            return null;
        }

        return $email;
    }

    //endregion

    private function parseEmail(Email $email)
    {
        $r = $email->add()->flight();
        $r->general()
            ->confirmation($this->http->FindSingleNode("//text()[{$this->starts($this->t('Locator'))}]/ancestor::td[1]",
                null, false, "/{$this->opt($this->t('Locator'))}[ :]+([A-Z\d]{5,6})\b/"));

        if ($this->http->XPath->query("//text()[{$this->eq($this->t('Loyalty Card'))}]/ancestor::tr[1][{$this->contains($this->t('Passenger'))}]")->length > 0) {
            //format 1
            $r->general()
                ->travellers(array_filter($this->http->FindNodes("//text()[{$this->eq($this->t('Loyalty Card'))}]/ancestor::tr[1]/following-sibling::tr/td[1]")));
            $r->issued()
                ->tickets(array_unique(array_filter($this->http->FindNodes("//text()[{$this->eq($this->t('Loyalty Card'))}]/ancestor::tr[1]/following-sibling::tr/td[3]",
                    null, "/^(\d[\-\d]+)$/"))), false);
            $acc = array_filter($this->http->FindNodes("//text()[{$this->eq($this->t('Loyalty Card'))}]/ancestor::tr[1]/following-sibling::tr/td[2]"));

            if (!empty($acc)) {
                $r->program()
                    ->accounts($acc, false);
            }
        } else {
            //format 2
            $r->general()
                ->travellers(array_filter($this->http->FindNodes("//text()[{$this->eq($this->t('Passenger'))}]/ancestor::tr[1]/following-sibling::tr/td[1]")));
            $r->issued()
                ->tickets(array_unique(array_filter($this->http->FindNodes("//text()[{$this->eq($this->t('Number of Ticket'))}]/ancestor::tr[1]/following-sibling::tr/td[1]",
                    null, "/^(\d[\-\d]+)$/"))), false);
            $acc = array_filter(array_filter($this->http->FindNodes("//text()[{$this->eq($this->t('Loyalty Card'))}]/ancestor::tr[1]/following-sibling::tr/td[1]")));

            if (!empty($acc)) {
                $r->program()
                    ->accounts($acc, false);
            }
        }

        if ($this->http->XPath->query("//text()[{$this->contains($this->t('confirmText'))}]")->length > 0) {
            $r->general()->status('confirmed');
        }

        $sum = $this->http->FindSingleNode("//text()[({$this->starts($this->t('TOTAL PRICE'))}) and not({$this->contains($this->t('includes'))})]/ancestor::*[self::p/*[2] or self::tr][1]", null,
            false, "/{$this->opt($this->t('TOTAL PRICE'))}\s*(\d[,.\'\d\s]* ?\D)[ ]*\d?(?:\n|$)/u");

        if ($sum) {
            $sum = $this->getTotalCurrency($sum);
            $r->price()
                ->total($sum['Total'])
                ->currency($sum['Currency']);
        }
        $ruleTime = "contains(translate(normalize-space(.),'0123456789','dddddddddd'),'d:dd')";

        $xpath = "//text()[{$ruleTime}]/ancestor::tr[count(.//text()[{$ruleTime}])=2][1]";
        $this->logger->debug("[XPATH]: " . $xpath);
        $segments = $this->http->XPath->query($xpath);

        foreach ($segments as $root) {
            $s = $r->addSegment();

            if ($this->http->XPath->query("./descendant::text()[{$this->eq($this->t('Arrival'))}]",
                    $root)->length === 0
            ) {
                $this->parseSegment_1($s, $root);
            } else {
                $this->parseSegment_2($s, $root);
            }
        }

        return true;
    }

    private function parseSegment_1(FlightSegment $s, \DOMNode $root)
    {
        $node = $this->http->FindSingleNode("./td[3]", $root);

        if (preg_match("/([A-Z\d][A-Z]|[A-Z][A-Z\d])\s*(\d+)/", $node, $m)) {
            $s->airline()
                ->name($m[1])
                ->number($m[2])
                ->operator($this->http->FindSingleNode("./following::text()[normalize-space()!=''][1]", $root, false,
                    "/{$this->opt($this->t('Flight operated by'))}\s*(.+)/"));
        }
        $node = implode(" ", $this->http->FindNodes("./td[1]//text()[normalize-space()!='']", $root));

        if (preg_match("/^(.+\b\d{4}\b.+)\/(.+?)[,\s]*(?:Terminal (.+?))?\s*(?:Seats:\s*(\d+[A-z],?.*))?$/", $node,
            $m)) {
            $s->departure()
                ->date($this->normalizeDate($m[1]))
                ->noCode()
                ->name($m[2]);

            if (isset($m[3]) && !empty($m[3])) {
                $s->departure()->terminal($m[3]);
            }

            if (isset($m[4]) && !empty($m[4])) {
                $s->extra()->seats(array_map("trim", explode(",", $m[4])));
            }
        }
        $node = $this->http->FindSingleNode("./td[2]", $root);

        if (preg_match("/^(.+\b\d{4}\b.+)\/(.+?)[,\s]*(?:Terminal (.+?))?$/", $node, $m)) {
            $s->arrival()
                ->date($this->normalizeDate($m[1]))
                ->noCode()
                ->name($m[2]);

            if (isset($m[3]) && !empty($m[3])) {
                $s->arrival()->terminal($m[3]);
            }
        }
    }

    private function parseSegment_2(FlightSegment $s, \DOMNode $root)
    {
        $node = implode(" ",
            $this->http->FindNodes("./descendant::text()[{$this->starts($this->t('Flight'))}]/ancestor::tr[1]/following-sibling::tr[1]",
                $root));

        if (preg_match("/([A-Z\d][A-Z]|[A-Z][A-Z\d])\s*(\d+)/", $node, $m)) {
            $s->airline()
                ->name($m[1])
                ->number($m[2])
                ->operator($this->http->FindSingleNode("./following::text()[normalize-space()!=''][1]", $root, false,
                    "/{$this->opt($this->t('Flight operated by'))}[:\s]*(.+)/"), false, true);
        }
        $node = implode(" ",
            $this->http->FindNodes("./descendant::text()[{$this->eq($this->t('Departure'))}]/ancestor::tr[1]/following-sibling::tr[1]",
                $root));

        if (preg_match("/^(.+\b\d{4}\b.+)\/(.+?)[,\s]*(?:Terminal (.+?))?\s*(?:Seats:\s*(\d+[A-z],?.*))?$/", $node,
            $m)) {
            $s->departure()
                ->date($this->normalizeDate($m[1]))
                ->noCode()
                ->name($m[2]);

            if (isset($m[3]) && !empty($m[3])) {
                $s->departure()->terminal($m[3]);
            }

            if (isset($m[4]) && !empty($m[4])) {
                $s->extra()->seats(array_map("trim", explode(",", $m[4])));
            }
        }
        $node = implode(" ",
            $this->http->FindNodes("./descendant::text()[{$this->eq($this->t('Arrival'))}]/ancestor::tr[1]/following-sibling::tr[1]",
                $root));

        if (preg_match("/^(.+\b\d{4}\b.+)\/(.+?)[,\s]*(?:Terminal (.+?))?$/", $node, $m)) {
            $s->arrival()
                ->date($this->normalizeDate($m[1]))
                ->noCode()
                ->name($m[2]);

            if (isset($m[3]) && !empty($m[3])) {
                $s->arrival()->terminal($m[3]);
            }
        }
    }

    private function normalizeDate($date)
    {
        $in = [
            //Sunday March 15, 2015 10:25 h
            '/.+?(\w+) (\d+), (\d{4}) (\d+:\d+(\s*[ap]m)?).+/i',
            // Freitag, 12. Juni 2015 19:45h
            '/.+?(\d+)\.? (\w+) (\d{4}) (\d+:\d+(\s*[ap]m)?).+/i',
            // viernes 25 de marzo de 2016 15:45h
            '/.+?(\d+) de (\w+) de (\d{4}) (\d+:\d+(\s*[ap]m)?).+/i',
        ];
        $out = [
            '$2 $1 $3, $4',
            '$1 $2 $3, $4',
            '$1 $2 $3, $4',
        ];
        $str = strtotime($this->dateStringToEnglish(preg_replace($in, $out, $date)));

        return $str;
    }

    /**
     * @param string $haystack
     * @param array $arrayNeedle
     *
     * @return string|null
     */
    private function detect($haystack, $arrayNeedle)
    {
        foreach ($arrayNeedle as $lang => $needles) {
            if (is_array($needles)) {
                foreach ($needles as $needle) {
                    if (stripos($haystack, $needle) !== false) {
                        return $lang;
                    }
                }
            } else {
                if (stripos($haystack, $needles) !== false) {
                    return $lang;
                }
            }
        }

        return null;
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function dateStringToEnglish($date)
    {
        if (preg_match('#[[:alpha:]]+#iu', $date, $m)) {
            $monthNameOriginal = $m[0];

            if ($translatedMonthName = MonthTranslate::translate($monthNameOriginal, $this->lang)) {
                return preg_replace("#$monthNameOriginal#i", $translatedMonthName, $date);
            }
        }

        return $date;
    }

    private function getTotalCurrency($node)
    {
        $node = str_replace("€", "EUR", $node);
        $node = str_replace("$", "USD", $node);
        $node = str_replace("£", "GBP", $node);
        $tot = '';
        $cur = '';

        if (preg_match("#(?<c>[A-Z]{3})\s*(?<t>\d[\.\d\,\s]*\d*)#", $node, $m)
            || preg_match("#(?<t>\d[\.\d\,\s]*\d*)\s*(?<c>[A-Z]{3})#", $node, $m)
        ) {
            $m['t'] = trim($m['t'], ' ,.');
            $cur = $m['c'];
            $tot = PriceHelper::cost($m['t']);

            if (!isset($tot)) {
                $tot = PriceHelper::cost($m['t'], '.', ',');
            }
        }

        return ['Total' => $tot, 'Currency' => $cur];
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(' or ', array_map(function ($s) {
            return 'normalize-space(.)="' . $s . '"';
        }, $field));
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(' or ', array_map(function ($s) {
            return 'starts-with(normalize-space(.),"' . $s . '")';
        }, $field));
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(' or ', array_map(function ($s) {
            return 'contains(normalize-space(.),"' . $s . '")';
        }, $field));
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
    }
}
