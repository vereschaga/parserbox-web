<?php

namespace AwardWallet\Engine\lufthansa\Email;

use AwardWallet\Schema\Parser\Common\Flight;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class YourFlightTo extends \TAccountChecker
{
    public $mailFiles = "lufthansa/it-163225776.eml, lufthansa/it-163483646.eml, lufthansa/it-164950186.eml, lufthansa/it-69258623.eml, lufthansa/it-69697948.eml";
    public $subjects = [
        // en
        'Our range of services for your flight to',
        ' – Tips and Services for your journey',
        // de
        'Unser Service-Angebot für Ihren Flug nach',
        ' – Tipps und Services zu Ihrer Reise.',
        // pt
        ' - Dicas e serviços para a sua viagem.',
        // it
        'La nostra offerta di servizi per il suo volo a ',
        // ru
        'Наш спектр услуг для Вашего рейса в ',
    ];

    public $lang = '';

    public static $dictionary = [
        "en" => [],
        "de" => [
            'Hello'                    => ['Sehr geehrte', 'Sehr geehrter'],
            'Your flight'              => ['Ihr Flug', 'Ihr Hinflug', 'Ihr Rückflug'],
            'Your booking details'     => 'Alle Details zu Ihrer Buchung',
        ],
        "pt" => [
            'Hello'                => ['Olá'],
            'Your flight'          => ['O seu voo de ida', 'O seu voo de volta'],
            'Your booking details' => 'Todos os detalhes sobre a sua reserva',
        ],
        "it" => [
            'Hello'                => ['Gentile Signora'],
            'Your flight'          => ['Il suo volo'],
            'Your booking details' => 'Tutti i dettagli della sua prenotazione',
        ],
        "ru" => [
            'Hello'                => ['Уважаемый господин'],
            'Your flight'          => ['Ваш рейс'],
            'Your booking details' => 'Вся информация о Вашем бронировании',
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@your.lufthansa-group.com') !== false) {
            foreach ($this->subjects as $subject) {
                if (stripos($headers['subject'], $subject) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//a[contains(@href, 'lufthansa')]")->length === 0) {
            return false;
        }

        return $this->detectLang();
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]your\.lufthansa\-group\.com$/', $from) > 0;
    }

    public function parseFlight(Email $email)
    {
        $f = $email->add()->flight();

        $f->general()
            ->noConfirmation();

        $traveller = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Hello'))}]");

        if (preg_match("/{$this->opt($this->t('Hello'))}\s+(.+?)[,!]/", $traveller, $m)) {
            $f->general()
                ->traveller($m[1]);
        } elseif (preg_match("/{$this->opt($this->t('Hello'))}\s*,/", $traveller, $m)) {
        } else {
            $f->general()
                ->traveller('');
        }

        $xpath = "//text()[{$this->eq($this->t('Your flight'))}][not(following::text()[normalize-space()][1][{$this->eq($this->t('Your flight'))}])]";
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $this->parseFlightSegment($root, $f);
        }

        return true;
    }

    public function parseFlightSegment($root, Flight $f)
    {
        $s = $f->addSegment();

        $durationText = implode(' ', $this->http->FindNodes("./ancestor::table[2]/descendant::text()[normalize-space()]", $root));

        if (!empty($duration = $this->re("/{$this->opt($this->t('Your flight'))}\s+(.+)/", $durationText))) {
            $s->extra()
                ->duration($duration);
        }

        $row = 3;
        $confirmationText = implode(' ', $this->http->FindNodes("./ancestor::table[2]/following::table[1]/descendant::text()[normalize-space()]", $root));

        if (preg_match("/^(?<aircraft>.+)\s(?<airlineName>[A-Z\d]{2})(?<flightNumber>[\d]{1,5})\s(?<cabin>\D+)$/u", $confirmationText, $m)
            || preg_match("/^(?<airlineName>[A-Z\d]{2})(?<flightNumber>[\d]{1,5})\s(?<cabin>\D+)$/u", $confirmationText, $m)) {
            if (isset($m['cabin'])) {
                $s->extra()
                    ->cabin($m['cabin']);
            }

            if (isset($m['aircraft'])) {
                $s->extra()
                    ->aircraft($m['aircraft']);
            }

            $s->airline()
                ->name($m['airlineName'])
                ->number($m['flightNumber']);
        } elseif (!empty($this->http->FindSingleNode("following::text()[normalize-space()][2]", $root, true, "/^\s*[A-Z]{3}\s*$/"))
            && !empty($this->http->FindSingleNode("following::text()[normalize-space()][3]", $root, true, "/^\s*[A-Z]{3}\s*$/"))
        ) {
            $s->airline()
                ->noName()
                ->noNumber()
            ;
            $row = 1;
        }

        $codeText = implode(' ', $this->http->FindNodes("./ancestor::table[2]/following::table[{$row}]/descendant::text()[normalize-space()]", $root));

        if (preg_match("/^(?<depCode>[A-Z]{3})\s+(?<arrCode>[A-Z]{3})/u", $codeText, $m)) {
            $s->departure()
                ->code($m['depCode']);

            $s->arrival()
                ->code($m['arrCode']);
        }

        $row++;
        $dateText = implode(' ', $this->http->FindNodes("./ancestor::table[2]/following::table[{$row}]/descendant::text()[normalize-space()]", $root));

        if (preg_match("/^(?<depDate>[\d\.]+)\s*\|\s*(?<depTime>[\d\:]+)\s+(?<arrDate>[\d\.]+)\s*\|\s*(?<arrTime>[\d\:]+)$/u", $dateText, $m)) {
            $s->departure()
                ->date(strtotime($m['depDate'] . ', ' . $m['depTime']));

            $s->arrival()
                ->date(strtotime($m['arrDate'] . ', ' . $m['arrTime']));
        }
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->detectLang($parser->getPlainBody());

        $this->parseFlight($email);

        return $email;
    }

    public function detectLang()
    {
        foreach (self::$dictionary as $lang => $dict) {
            if (!empty($dict['Your flight']) && !empty($dict['Your booking details'])
                && $this->http->XPath->query("//text()[{$this->eq($dict['Your flight'])}]")->length > 0
                && $this->http->XPath->query("//text()[{$this->eq($dict['Your booking details'])}]")->length > 0
            ) {
                $this->lang = $lang;

                return true;
            }
        }

        return true;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
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

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) {
            return "contains(normalize-space(.), \"{$s}\")";
        }, $field));
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
        }

        return implode(' or ', array_map(function ($s) {
            return 'normalize-space(.)="' . $s . '"';
        }, $field));
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }
}
