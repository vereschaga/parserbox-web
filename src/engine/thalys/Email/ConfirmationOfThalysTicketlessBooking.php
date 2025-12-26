<?php

namespace AwardWallet\Engine\thalys\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class ConfirmationOfThalysTicketlessBooking extends \TAccountChecker
{
    public $mailFiles = "thalys/it-1.eml, thalys/it-1582690.eml, thalys/it-1582691.eml, thalys/it-2202000.eml, thalys/it-2267296.eml, thalys/it-2789219.eml";

    public $reFrom = ["thalysticketless.com", "thalys.com"];
    public $reBody = [
        'en' => ['We are pleased to confirm', 'Ticketless'],
        'fr' => ['Nous vous confirmons', 'Ticketless'],
        'de' => ['Wir bestätigen Ihnen hiermit', 'Ticketless'],
    ];
    public $reSubject = [
        'Confirmation of Thalys Ticketless booking',
        'Confirmation de reservation Thalys',
        'Ihre Thalys Ticketless-Reservierung',
    ];
    public $lang = '';
    public static $dict = [
        'en' => [
            'Departure'              => 'Departure',
            'Arrival'                => 'Arrival',
            'Booking File Reference' => ['Booking File Reference', 'Booking reference'],
            'Travel Date'            => ['Travel Date', 'Date of journey'],
            'Thalys train'           => ['Thalys train', 'Thalys train n', 'Thalys train nr'],
        ],
        'fr' => [
            'Booking File Reference'    => 'Dossier voyage',
            'Dear'                      => 'Cher(e)',
            'We are pleased to confirm' => 'Nous vous confirmons',
            'Price'                     => 'Prix',
            'Travel Date'               => 'Date de voyage',
            'Departure'                 => 'Départ',
            'Arrival'                   => 'Arrivée',
            'from'                      => 'de',
            'at'                        => 'à',
            'Thalys train'              => 'de train Thalys',
            'Seating'                   => 'Placement',
            'coach'                     => 'voiture',
            'seat'                      => 'siège',
            'Class'                     => 'Classe',
        ],
        'de' => [
            'Booking File Reference'    => 'Buchungsnummer',
            'Dear'                      => '',
            'We are pleased to confirm' => 'Wir bestätigen Ihnen hiermit',
            'Price'                     => 'Preis',
            'Travel Date'               => 'Reisetermin',
            'Departure'                 => 'Abfahrt',
            'Arrival'                   => 'Ankunft',
            'from'                      => 'ab',
            'at'                        => 'an',
            'Thalys train'              => 'Thalys-Zugnummer',
            'Seating'                   => 'Sitznummer',
            'coach'                     => 'Wagen',
            'seat'                      => 'Sitz',
            'Class'                     => 'Klasse',
        ],
    ];
    private $keywordProv = 'Thalys';

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        if (!$this->assignLang()) {
            $this->logger->debug('can\'t determine a language');

            return $email;
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        $this->parseEmail($email);

        return $email;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//a[contains(@href,'thalys')] | //img[contains(@src,'thalys')]")->length > 0) {
            if ($this->detectBody() && $this->assignLang()) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        foreach ($this->reFrom as $reFrom) {
            if (stripos($from, $reFrom) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        $fromProv = true;

        if (!isset($headers['from']) || self::detectEmailFromProvider($headers['from']) === false) {
            $fromProv = false;
        }

        if (isset($headers["subject"])) {
            foreach ($this->reSubject as $reSubject) {
                if (($fromProv && stripos($headers["subject"], $reSubject) !== false)
                    || strpos($headers["subject"], $this->keywordProv) !== false
                ) {
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

    public function IsEmailAggregator()
    {
        return false;
    }

    private function detectBody()
    {
        if (isset($this->reBody)) {
            foreach ($this->reBody as $lang => $reBody) {
                if ($this->http->XPath->query("//*[contains(normalize-space(.),'{$reBody[0]}')]")->length > 0
                    && $this->http->XPath->query("//*[contains(normalize-space(.),'{$reBody[1]}')]")->length > 0
                ) {
                    return true;
                }
            }
        }

        return false;
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function assignLang()
    {
        foreach (self::$dict as $lang => $words) {
            if (isset($words["Departure"], $words["Arrival"])) {
                if ($this->http->XPath->query("//*[{$this->contains($words['Departure'])}]")->length > 0
                    && $this->http->XPath->query("//*[{$this->contains($words['Arrival'])}]")->length > 0
                ) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function parseEmail(Email $email)
    {
        $r = $email->add()->train();

        $r->general()
            ->confirmation($this->http->FindSingleNode("//text()[{$this->starts($this->t('Booking File Reference'))}]",
                null, false, "#{$this->opt($this->t('Booking File Reference'))}[\s:]+([A-Z\d]{5,})#u"))
            ->traveller($this->http->FindSingleNode("//text()[{$this->contains($this->t('We are pleased to confirm'))}]/preceding::text()[normalize-space()!=''][1]",
                null, false, "#(?:{$this->opt($this->t('Dear'))}\s+)?(.+),#u"))
            ->status('Confirmed');

        $totalText = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Price'))}]", null, false,
            "#{$this->opt($this->t('Price'))}[\s:]+(.+)#u");

        if (strpos($totalText, '***') === false) {
            $total = $this->getTotalCurrency($totalText);
            $r->price()
                ->total($total['Total'])
                ->currency($total['Currency']);
        }

        $s = $r->addSegment();
        $date = strtotime(
            implode(
                '-',
                array_reverse(
                    explode(
                        "/",
                        $this->http->FindSingleNode(
                            "//text()[{$this->starts($this->t('Travel Date'))}]",
                            null,
                            false,
                            "#:\s*[^\s\d]*\s+(\S+)#")
                    )
                )
            )
        );

        if (!$date) {
            $this->logger->debug('other date format');

            return false;
        }

        $node = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Departure'))}]");

        if (preg_match("#{$this->opt($this->t('Departure'))}[\s:]+.*?\b(?<time>\d+:\d+)\s*.*{$this->opt($this->t('from'))}\s+(?<name>.+)#u",
            $node, $m)) {
            $s->departure()
                ->name($m['name'])
                ->date(strtotime($m['time'], $date));
        }

        $node = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Arrival'))}]");

        if (preg_match("#{$this->opt($this->t('Arrival'))}[\s:]+.*?\b(?<time>\d+:\d+)\s*.*{$this->opt($this->t('at'))}\s+(?<name>.+)#u",
            $node, $m)) {
            $s->arrival()
                ->name($m['name'])
                ->date(strtotime($m['time'], $date));
        }

        if ($s->getArrDate() && $s->getDepDate() && $s->getArrDate() < $s->getDepDate()) {
            $s->arrival()->date(strtotime("+1 day", $s->getArrDate()));

            // still invalid?
            if ($s->getArrDate() < $s->getDepDate()) {
                $this->logger->debug("something gone wrong");

                return false;
            }
        }

        $node = trim($this->http->FindSingleNode("//text()[{$this->starts($this->t('Seating'))}]", null, false,
            "#{$this->opt($this->t('Seating'))}[:\s]+(.+)#u"));

        if (preg_match("#^\w+[ ]+(\d+)[ ]+\w+[ ]+(\d+)$#iu", $node, $m)) {
            $s->extra()
                ->car($m[1])
                ->seat($m[2]);
        }
        $s->extra()
            ->number($this->http->FindSingleNode("//text()[{$this->contains($this->t('Thalys train'))}]", null, false,
                "#{$this->opt($this->t('Thalys train'))}.*?:\s*(.+)#u"))
            ->cabin($this->http->FindSingleNode("//text()[{$this->starts($this->t('Class'))}]", null, false,
                "#{$this->opt($this->t('Class'))}[\s:]+(.+)#u"));

        return true;
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

    private function getTotalCurrency($node)
    {
        $node = str_replace("Euro", "EUR", $node);
        $node = str_replace("€", "EUR", $node);
        $node = str_replace("euros", "EUR", $node);

        $tot = '';
        $cur = '';

        if (preg_match("#(?<c>[A-Z]{3})\s*(?<t>\d[\.\d\,\s]*\d*)#", $node, $m)
            || preg_match("#(?<t>\d[\.\d\,\s]*\d*)\s*(?<c>[A-Z]{3})#", $node, $m)
        ) {
            $cur = $m['c'];
            $tot = PriceHelper::cost($m['t']);
        }

        return ['Total' => $tot, 'Currency' => $cur];
    }
}
