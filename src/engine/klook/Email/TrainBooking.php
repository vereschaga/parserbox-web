<?php

namespace AwardWallet\Engine\klook\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class TrainBooking extends \TAccountChecker
{
    public $mailFiles = "klook/it-466764149.eml, klook/it-762619291.eml, klook/it-764596613.eml";
    public $lang = 'en';

    public static $dictionary = [
        "en" => [
            'confNumber'     => ['confirmation no.', 'Confirmation no.'],
            'seeTicketGuide' => ['See the ticket collection guide', 'See pick-up guide', 'See express e-ticket'],
            'costNames'      => ['Adult x ∆', 'Child x ∆'],
            'feeNames'       => ['Fulfillment fee', 'Booking fee'],
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (!array_key_exists('from', $headers) || $this->detectEmailFromProvider(rtrim($headers['from'], '> ')) !== true) {
            return false;
        }

        return array_key_exists('subject', $headers) && preg_match("/(?:^|:\s*)Your booking confirmation for\s+.{7,}\. Keep this handy(?:[.!]|$)/i", $headers['subject']) > 0;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query('//a[contains(@href,".klook.com/") or contains(@href,"click.klook.com")]')->length === 0
            && $this->http->XPath->query('//text()[starts-with(normalize-space(),"©") and contains(normalize-space(),"Klook Travel")]')->length === 0
            && $this->http->XPath->query('//*[contains(normalize-space(),"Website: www.klook.com")]')->length === 0
        ) {
            return false;
        }

        return $this->findSegments()->length > 0;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]klook\.com$/', $from) > 0;
    }

    public function ParseTrain(Email $email): void
    {
        $patterns = [
            'time'    => '\d{1,2}[:：]\d{2}(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?)?', // 4:19PM  |  2:00 p. m.
            'eTicket' => '\d{3}(?: | ?- ?)?\d{5,}(?: | ?[-\/] ?)?\d{1,3}', // 175-2345005149-23  |  1752345005149/23
        ];

        $t = $email->add()->train();

        $otaConfirmation = $otaConfirmationTitle = null;

        if (preg_match($pattern = "/^({$this->opt($this->t('Booking reference ID'))})[:\s]+([-A-Z\d]{4,40})$/", $this->http->FindSingleNode("//text()[{$this->starts($this->t('Booking reference ID'))}]"), $m)
            || preg_match($pattern, $this->http->FindSingleNode("//text()[starts-with(normalize-space(),'Thanks for booking with Klook')]/following::text()[{$this->starts($this->t('Booking reference ID'))}]"), $m)
        ) {
            $otaConfirmationTitle = $m[1];
            $otaConfirmation = $m[2];
        }

        $email->ota()->confirmation($otaConfirmation, $otaConfirmationTitle);

        $confirmation = $this->http->FindSingleNode("//text()[{$this->contains($this->t('confNumber'))}]");

        if (preg_match("/^(.*{$this->opt($this->t('confNumber'))})[:\s]+([-A-Z\d]{4,40})$/", $confirmation, $m)) {
            $t->general()->confirmation($m[2], $m[1]);
        } elseif ($this->http->XPath->query("//text()[{$this->contains($this->t('confNumber'))}]")->length === 0 && $otaConfirmation) {
            $t->general()->noConfirmation();
        }

        $status = $this->http->FindSingleNode("//text()[contains(normalize-space(), 'Hooray!')]", null, true, "/\s*is\s*(\D+)\.\s*Hooray[!]/")
            ?? $this->http->FindSingleNode("//text()[{$this->contains($this->t('booking has been'))}]", null, true, "/{$this->opt($this->t('booking has been'))}/");

        if (!empty($status)) {
            $t->general()
                ->status($status);
        }

        $tickets = array_filter(array_merge(
            $this->http->FindNodes("//text()[{$this->contains($this->t('seeTicketGuide'))}]/preceding::*[ count(node()[normalize-space() and not(.//img)])=1 and count(node()[self::img or normalize-space()='' and descendant::img])=1 and node()[normalize-space() and not(.//img)] ]/node()[normalize-space() and not(.//img)]", null, "/^{$patterns['eTicket']}$/"),
            $this->http->FindNodes("//text()[normalize-space()='Ticket is out for delivery']/following::text()[normalize-space()][1]", null, "/^{$patterns['eTicket']}$/")
        ));

        if (count($tickets) > 0) {
            $t->setTicketNumbers(array_unique($tickets), false);
        }

        $date = null;
        $segments = $this->findSegments();

        foreach ($segments as $i => $root) {
            if ($i === 0) {
                $date = strtotime($this->http->FindSingleNode("preceding::text()[starts-with(normalize-space(),'Passengers')]/preceding::text()[contains(normalize-space(),'-')][1]", $root));
            }

            $segText = implode("\n", $this->http->FindNodes("./descendant::text()[normalize-space()]", $root));

            $reg = "/^(?<preText>.+)\n(?<depTime>{$patterns['time']})\n(?<depName>.+)"
            . "\n(?<duration>.+[hm])\n(?<arrTime>{$patterns['time']})\n(?<arrName>.+)\n(?<postText>.+)$/i";

            if (preg_match($reg, $segText, $m)) {
                $s = $t->addSegment();

                if (preg_match("/^(?<service>.+?)\s+(?<number>\d+)$/", $m['preText'], $m2)) {
                    // Nozomi 17
                    $s->setServiceName($m2['service'])->setNumber($m2['number']);
                } elseif (preg_match("/^[-A-Z\d]+$/", $m['preText']) && preg_match("/\d/", $m['preText'])) {
                    // G6514
                    $s->setNumber($m['preText']);
                }

                $s->departure()->date(strtotime($m['depTime'], $date))->name($m['depName']);
                $s->arrival()->date(strtotime($m['arrTime'], $date))->name($m['arrName']);
                $s->extra()->duration($m['duration']);

                if (preg_match("/^(?:.+\(\s*)?([^)(]+{$this->opt($this->t('class'))})[\s)]*$/i", $m['postText'], $m2)) {
                    // 1st class    |    Reserved seat - Ordinary car (2nd class)
                    $s->extra()->cabin($m2[1]);
                }
            }
        }

        $carsValues = $seatsValues = [];
        $seatTexts = array_filter($this->http->FindNodes("//text()[{$this->contains($this->t('Seat.'))}]", null, "/^{$this->opt($this->t('Car.'))}.*{$this->opt($this->t('Seat.'))}[:\s]*[^:\s].*$/i"));

        foreach ($seatTexts as $seatTxt) {
            if (preg_match("/^{$this->opt($this->t('Car.'))}[:\s]*(?<car>.*?)\s*{$this->opt($this->t('Seat.'))}[:\s]*(?<seat>[^:\s].*)$/i", $seatTxt, $m)) {
                // Car.8 Seat. 7-B
                $carsValues[] = empty($m['car']) ? null : $m[1];
                $seatsValues[] = $m['seat'];
            } elseif (preg_match("/^{$this->opt($this->t('Seat.'))}[:\s]*(?<seat>[^:\s].*)$/i", $seatTxt, $m)) {
                // Seat. 7-B
                $carsValues[] = null;
                $seatsValues[] = $m['seat'];
            }
        }

        if ($segments->length === 1) {
            if (count(array_unique($carsValues)) === 1) {
                $s->extra()->car($carsValues[0], false, true)->seats(array_unique($seatsValues));
            } elseif (count($seatTexts) > 0) {
                $s->extra()->seats(array_unique($seatTexts));
            }
        }

        /* Price */

        $feeCurrencies = $fees = [];

        $feeRows = $this->http->XPath->query("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->starts($this->t('costNames'), "translate(.,'0123456789','∆∆∆∆∆∆∆∆∆∆')")} or {$this->eq($this->t('feeNames'), "translate(.,':','')")}] ]");

        if ($feeRows->length === 0) {
            $feeRows = $this->http->XPath->query("//text()[{$this->starts($this->t('costNames'), "translate(.,'0123456789','∆∆∆∆∆∆∆∆∆∆')")} or {$this->starts($this->t('feeNames'))}]");
        }

        $feeTexts = [];

        foreach ($feeRows as $feeRow) {
            $feeTexts[] = implode(' ', $this->http->FindNodes("descendant-or-self::text()[normalize-space()]", $feeRow));
        }

        foreach ($feeTexts as $feeText) {
            if (preg_match("/^(?<name>(?:{$this->opt($this->t('Adult'))}|{$this->opt($this->t('Child'))})\s*x\s*\d{1,3}|{$this->opt($this->t('feeNames'))})[:\s]+(?<value>.*\d.*)$/i", $feeText, $m)
                && preg_match('/^(?<currency>[^\-\d)(]+?)[ ]*(?<amount>\d[,.‘\'\d ]*)$/u', $m['value'], $m2)
            ) {
                // US$ 204.00
                $currency = $this->normalizeCurrency($m2['currency']);
                $currencyCode = preg_match('/^[A-Z]{3}$/', $currency) ? $currency : null;
                $feeCurrencies[] = $currency;
                $fees[] = [
                    'name'   => $m['name'],
                    'charge' => PriceHelper::parse($m2['amount'], $currencyCode),
                ];
            }
        }

        if (count(array_unique($feeCurrencies)) === 1) {
            $t->price()->currency($feeCurrencies[0]);

            foreach ($fees as $fee) {
                $t->price()->fee($fee['name'], $fee['charge']);
            }
        }
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->ParseTrain($email);

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

    private function findSegments(): \DOMNodeList
    {
        $xpathTime = '(starts-with(translate(normalize-space(),"0123456789： ","∆∆∆∆∆∆∆∆∆∆::::"),"∆:∆∆") or starts-with(translate(normalize-space(),"0123456789： ","∆∆∆∆∆∆∆∆∆∆::::"),"∆∆:∆∆"))';

        return $this->http->XPath->query("//tr[ count(*[normalize-space() or .//img])>1 and count(*[normalize-space() or .//img])<4 and *[1][not(.//img)] and *[last()][not(.//img)] and *[{$xpathTime}] ]/ancestor::*[ preceding-sibling::*[normalize-space()] or following-sibling::*[normalize-space()] ][1]/..");
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

    private function opt($field): string
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s, '/'));
        }, $field)) . ')';
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
            'SGD' => ['S$'],
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
