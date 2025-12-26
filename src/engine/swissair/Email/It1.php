<?php

namespace AwardWallet\Engine\swissair\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class It1 extends \TAccountChecker
{
    public $reFrom = "#swiss#i";

    public $langSupported = "en, fr, de";
    public $mailFiles = "swissair/it-1.eml, swissair/it-3.eml, swissair/it-4681311.eml, swissair/it-4685232.eml";

    public $reBody = [
        'fr' => ['référence', 'de réservation'],
        'de' => ['Fluginformationen', 'Buchungsreferenz'],
        'en' => ['Flight information', 'Hotels in'],
    ];
    public $reSubject = [
        'en' => ['Your SWISS flight', 'All the information'],
        'fr' => ['les informations', 'Votre vol SWISS'],
        'de' => ['Alle Informationen', 'Ihr SWISS Flug'],
    ];
    public $lang = 'en';
    public static $dict = [
        'en' => [
            'booking reference is:' => ['Your booking number is:', 'booking reference is:', 'Your booking reference is:'],
        ],
        'fr' => [
            'booking reference is:' => ['référence de réservation est:', 'Votre référence de réservation est:'],
            'Grand total'           => 'Montant total',
            'Price in'              => 'Prix en',
            'Airport taxes'         => 'Taxes aéroportuaires',
            'Fare'                  => 'Tarif',
            'Selected services'     => 'Prestations choisies',
            'Flight information'    => 'Information sur le vol',
            'Operated by'           => 'Opéré par',
        ],
        'de' => [
            'booking reference is:' => ['Buchungsreferenz lautet:', 'Ihre Buchungsreferenz lautet:'],
            'Grand total'           => 'Gesamtpreis',
            'Price in'              => 'Preis in',
            'Airport taxes'         => 'Flughafentaxen',
            'Fare'                  => 'Flugtarif',
            'Selected services'     => 'Gewählte Leistungen',
            'Flight information'    => 'Fluginformationen',
            'Operated by'           => 'Durchgeführt von',
        ],
    ];

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $body = $this->http->Response['body'];
        $this->AssignLang($body);

        $this->parseFlight($email);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function parseFlight(Email $email)
    {
        $f = $email->add()->flight();

        $f->general()
            ->confirmation($this->http->FindSingleNode("//text()[{$this->starts($this->t('booking reference is:'))}]", null, true, "/{$this->opt($this->t('booking reference is:'))}\s*([A-Z\d]{5,})$/"))
            ->travellers($this->http->FindNodes("//text()[{$this->starts($this->t('E-Ticket:'))}]/preceding::text()[normalize-space()][1]"), true);

        $tickes = $this->http->FindNodes("//text()[{$this->starts($this->t('E-Ticket:'))}]/ancestor::tr[1]", null, "/{$this->opt($this->t('E-Ticket:'))}\s*(\d{5,})/");

        if (count($tickes) > 0) {
            $f->setTicketNumbers($tickes, false);
        }

        $currency = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Flight information'))}]/following::text()[{$this->starts($this->t('Price in'))}][1]", null, true, "/{$this->opt($this->t('Price in'))}\s*([A-Z]{3})/");
        $total = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Grand total'))}]/ancestor::tr[1]", null, true, "/{$this->opt($this->t('Grand total'))}\s*([\d\.\,\']+)/");

        if (!empty($currency) && !empty($total)) {
            $f->price()
                ->total(PriceHelper::parse($total, $currency))
                ->currency($currency);
        }

        $nodes = $this->http->XPath->query("//text()[{$this->eq($this->t('Flight information'))}]/ancestor::tr[1]/following-sibling::tr[contains(normalize-space(), ':')]");

        foreach ($nodes as $root) {
            $s = $f->addSegment();

            $flightText = implode(' ', $this->http->FindNodes("./descendant::text()[normalize-space()]", $root));

            if (preg_match("/\s(?<date>[\d\.]+\d{4})\s*(?<depTime>[\d\:]+)\s*(?<depCode>[A-Z]{3})[\s\-]+(?<arrTime>[\d\:]+)\s*(?:[+](?<nextDay>\d+))?\s*(?<arrCode>[A-Z]{3})\s*(?<airName>[A-Z\d]{2})\s*(?<flNumber>\d{2,4})\s*\*?\s*(?<cabin>\w+)[\s\-]+(?<bookingCode>[A-Z])\s*(?:{$this->opt($this->t('Operated by'))}\s*(?<operator>.+))?$/u", $flightText, $m)) {
                $s->airline()
                    ->name($m['airName'])
                    ->number($m['flNumber']);

                $s->departure()
                    ->code($m['depCode'])
                    ->date(strtotime($m['date'] . ', ' . $m['depTime']));

                $s->arrival()
                    ->code($m['arrCode']);

                if (isset($m['nextDay']) && !empty($m['nextDay'])) {
                    $s->arrival()
                        ->date(strtotime('+1 day', strtotime($m['date'] . ', ' . $m['arrTime'])));
                } else {
                    $s->arrival()
                        ->date(strtotime($m['date'] . ', ' . $m['arrTime']));
                }

                $s->extra()
                    ->cabin($m['cabin'])
                    ->bookingCode($m['bookingCode']);

                if (isset($m['operator']) && !empty($m['operator'])) {
                    $s->airline()
                        ->operator($m['operator']);
                }
            }
        }
    }

    public function detectEmailFromProvider($from)
    {
        if (preg_match($this->reFrom, $from)) {
            return true;
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();
        $this->AssignLang($body);

        return stripos($body, $this->reBody[$this->lang][0]) !== false && stripos($body, $this->reBody[$this->lang][1]) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['subject'])) {
            foreach ($this->reSubject as $ss) {
                if (stripos($headers['subject'], $ss[0]) !== false || stripos($headers['subject'], $ss[1]) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    protected function eq($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false';
        }

        return '(' . implode(' or ', array_map(function ($s) {
            return 'normalize-space(.)="' . $s . '"';
        }, $field)) . ')';
    }

    private function AssignLang($body)
    {
        if (isset($this->reBody)) {
            foreach ($this->reBody as $lang => $reBody) {
                if (stripos($body, $reBody[0]) !== false && stripos($body, $reBody[1]) !== false) {
                    $this->lang = $lang;

                    break;
                }
            }
        }

        return true;
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) {
            return "starts-with(normalize-space(.), \"{$s}\")";
        }, $field));
    }
}
