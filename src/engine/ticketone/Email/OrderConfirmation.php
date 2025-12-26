<?php

namespace AwardWallet\Engine\ticketone\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Common\Event;
use AwardWallet\Schema\Parser\Email\Email;

class OrderConfirmation extends \TAccountChecker
{
    public $mailFiles = "ticketone/it-109024407.eml, ticketone/it-109669100.eml";

    public $lang = '';

    public static $dictionary = [
        'it' => [ // it-109024407.eml
            'confNumber'       => ['Numero ordine:'],
            'venue'            => ['Luogo evento:'],
            'Invoice address:' => 'Indirizzo di fatturazione:',
            'Date:'            => 'Data:',
            'Total incl. VAT'  => 'Totale IVA incl.',
            'Subtotal'         => 'Subtotale',
            'Full Price'       => 'INTERO',
            'feeNames'         => ['Costi di consegna', 'Prevendita'],
        ],
        'en' => [ // it-109669100.eml
            'confNumber' => ['Order number:'],
            'venue'      => ['Venue:'],
            'feeNames'   => ['Delivery costs', 'Presale fee'],
        ],
    ];

    private $subjects = [
        'it' => ['Conferma di Acquisto'],
        'en' => ['Your order confirmation from'],
    ];

    private $detectors = [
        'it' => ['Conferma Ordine'],
        'en' => ['Order confirmation'],
    ];

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@ticketone.it') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->detectEmailFromProvider($headers['from']) !== true && strpos($headers['subject'], 'TicketOne.it') === false) {
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
        if ($this->http->XPath->query('//a[contains(@href,".ticketone.it/") or contains(@href,"www.ticketone.it")]')->length === 0
            && $this->http->XPath->query('//*[contains(.,"www.ticketone.it")]')->length === 0
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
        $email->setType('OrderConfirmation' . ucfirst($this->lang));

        $this->parseEvent($email);

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

    private function parseEvent(Email $email): void
    {
        $e = $email->add()->event();
        $e->place()->type(Event::TYPE_EVENT);

        $confirmation = $this->http->FindSingleNode("//text()[{$this->starts($this->t('confNumber'))}]/following::text()[normalize-space()][1]", null, true, '/^[A-Z\d]{5,}$/');

        if ($confirmation) {
            $confirmationTitle = $this->http->FindSingleNode("//text()[{$this->starts($this->t('confNumber'))}]", null, true, '/^(.+?)[\s:：]*$/u');
            $e->general()->confirmation($confirmation, $confirmationTitle);
        }

        $namePrefixes = ['Signora', 'Mr', 'Ms'];

        $invoiceAddress = $this->htmlToText($this->http->FindHTMLByXpath("//tr/*[ not(.//tr) and descendant::text()[normalize-space()][1][{$this->eq($this->t('Invoice address:'))}] ]"));

        if (preg_match("/^\s*{$this->opt($this->t('Invoice address:'))}\s*([[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]])[ ]*\n+.{3,}/u", $invoiceAddress, $m)) {
            $e->general()->traveller(preg_replace("/^{$this->opt($namePrefixes)}\s*[.]*\s+/", '', $m[1]));
        }

        $roots = $this->http->XPath->query("//tr[ descendant::text()[normalize-space()][1][{$this->eq($this->t('Date:'))}] and following::tr[normalize-space()][1]/descendant::text()[normalize-space()][1][{$this->eq($this->t('venue'))}] ]");
        $root = $roots->length > 0 ? $roots->item(0) : null;

        $name = $this->http->FindSingleNode("preceding::tr[normalize-space()][1]", $root);
        $address = $this->http->FindSingleNode("following::tr[normalize-space()][1]/descendant-or-self::tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('venue'))}] ]/*[normalize-space()][2]", $root);
        $e->place()->name($name)->address($address);

        $dateStart = $this->http->FindSingleNode("descendant-or-self::tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('Date:'))}] ]/*[normalize-space()][2]", $root);

        if (preg_match("/^(?<date>.{6,}?)\s*,\s*(?<time>\d{1,2}(?:[:：]\d{2})?(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?)?)$/", $dateStart, $m)) {
            // Sat., 28/08/2021, 9:00 AM
            $e->booked()->start2($this->normalizeDate($m['date']) . ' ' . $m['time'])->noEnd();
        }

        $totalPrice = $this->http->FindSingleNode("//tr/*[{$this->eq($this->t('Total incl. VAT'))}]/following-sibling::*[normalize-space()][last()]");

        if (preg_match('/^(?<currency>[^\d)(]+?)[ ]*(?<amount>\d[,.\'\d ]*)$/', $totalPrice, $matches)) {
            // € 32,00
            $e->price()->total(PriceHelper::parse($matches['amount']))->currency($matches['currency']);

            $baseFare = $this->http->FindSingleNode("//tr[ *[normalize-space()][1][{$this->eq($this->t('Subtotal'))}] ]/ancestor::*[ preceding-sibling::*[normalize-space()] ][1]/preceding-sibling::*/descendant-or-self::tr/*[{$this->contains($this->t('Full Price'))}]/following-sibling::*[normalize-space()][last()]");

            if (preg_match('/^(?:' . preg_quote($matches['currency'], '/') . ')?[ ]*(?<amount>\d[,.\'\d ]*?)$/', $baseFare, $m)) {
                $e->price()->cost(PriceHelper::parse($m['amount']));
            }

            $feeRows = $this->http->XPath->query("//tr[ *[normalize-space()][1][{$this->eq($this->t('feeNames'))}] ]");

            foreach ($feeRows as $feeRow) {
                $feeCharge = $this->http->FindSingleNode('*[normalize-space()][last()]', $feeRow, true, '/^(.+?)\s*(?:\(|$)/');

                if (preg_match('/^(?:' . preg_quote($matches['currency'], '/') . ')?[ ]*(?<amount>\d[,.\'\d ]*)$/', $feeCharge, $m)) {
                    $feeName = $this->http->FindSingleNode('*[normalize-space()][1]', $feeRow, true, '/^(.+?)[\s:：]*$/u');
                    $e->price()->fee($feeName, PriceHelper::parse($m['amount']));
                }
            }
        }
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
            if (!is_string($lang) || empty($phrases['confNumber']) || empty($phrases['venue'])) {
                continue;
            }

            if ($this->http->XPath->query("//*[{$this->contains($phrases['confNumber'])}]")->length > 0
                && $this->http->XPath->query("//*[{$this->contains($phrases['venue'])}]")->length > 0
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

    /**
     * @param string|null $text Unformatted string with date
     */
    private function normalizeDate(?string $text): string
    {
        if (!is_string($text) || empty($text)) {
            return '';
        }
        $in = [
            // Sat., 28/08/2021
            '/^[-[:alpha:]]+[,.\s]+(\d{1,2})\/(\d{1,2})\/(\d{2,4})$/u',
        ];
        $out = [
            '$2/$1/$3',
        ];

        return preg_replace($in, $out, $text);
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

    private function htmlToText(?string $s, bool $brConvert = true): string
    {
        if (!is_string($s) || $s === '') {
            return '';
        }
        $s = str_replace("\r", '', $s);
        $s = preg_replace('/<!--.*?-->/s', '', $s); // comments

        if ($brConvert) {
            $s = preg_replace('/\s+/', ' ', $s);
            $s = preg_replace('/<[Bb][Rr]\b.*?\/?>/', "\n", $s); // only <br> tags
        }
        $s = preg_replace('/<[A-z][A-z\d:]*\b.*?\/?>/', '', $s); // opening tags
        $s = preg_replace('/<\/[A-z][A-z\d:]*\b[ ]*>/', '', $s); // closing tags
        $s = html_entity_decode($s);
        $s = str_replace(chr(194) . chr(160), ' ', $s); // NBSP to SPACE

        return trim($s);
    }
}
