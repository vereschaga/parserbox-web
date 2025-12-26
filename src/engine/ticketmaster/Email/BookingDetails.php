<?php

namespace AwardWallet\Engine\ticketmaster\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Common\Event;
use AwardWallet\Schema\Parser\Email\Email;

class BookingDetails extends \TAccountChecker
{
    public $mailFiles = "ticketmaster/it-144256928.eml";

    public $lang = '';

    public static $dictionary = [
        'en' => [
            'Your Booking Reference' => ['Your Booking Reference'],
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        return stripos($headers['subject'], 'Ticketmaster - Booking Confirmation') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query('//a[contains(@href,".ticketmaster.co.uk/") or contains(@href,"www.ticketmaster.co.uk") or contains(@href,"help.ticketmaster.co.uk") or contains(@href,"theatre.ticketmaster.co.uk") or contains(@href,"guides.ticketmaster.co.uk")]')->length === 0) {
            return false;
        }

        return $this->assignLang();
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();

        if (empty($this->lang)) {
            $this->logger->debug("Can't determine a language!");

            return $email;
        }
        $email->setType('BookingDetails' . ucfirst($this->lang));

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
        $ev = $email->add()->event();
        $ev->place()->type(Event::TYPE_SHOW);

        $confirmation = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Your Booking Reference'))}]/following::text()[normalize-space()][1]", null, true, '/^([-A-Z\d]{5,})\s*(?:$|\()/');

        if ($confirmation) {
            $confirmationTitle = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Your Booking Reference'))}]", null, true, '/^(.+?)[\s:：]*$/u');
            $ev->general()->confirmation($confirmation, $confirmationTitle);
        }

        $roots = $this->http->XPath->query("//tr[ *[1][{$this->eq($this->t('Event'))}] and *[2][{$this->eq($this->t('Tickets'))}] and not(preceding-sibling::tr[normalize-space()]) and following-sibling::tr[normalize-space()] ]");

        if ($roots->length === 1) {
            $root = $roots->item(0);
        } else {
            $this->logger->debug('Main content not found!');

            return;
        }

        $traveller = null;
        $travellerNames = array_filter($this->http->FindNodes("preceding::text()[{$this->starts($this->t('Dear'))}]", $root, "/^{$this->opt($this->t('Dear'))}[,\s]+([[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]])(?:\s*[,;:!?]|$)/u"));

        if (count(array_unique($travellerNames)) === 1) {
            $traveller = array_shift($travellerNames);
        }
        $ev->general()->traveller($traveller);

        $eventText = $this->htmlToText($this->http->FindHTMLByXpath("following-sibling::tr[normalize-space()][1]/*[1]", null, $root));

        if (preg_match("/^\s*(?<name>.{2,}?)[ ]*\n+[ ]*(?<address>[\s\S]{3,}?)[ ]*\n+[ ]*(?<date>.{6,}?)\s*$/", $eventText, $m)) {
            $ev->place()->name($m['name'])->address(preg_replace('/[ ]*\n+[ ]*/', ', ', $m['address']));
            $ev->booked()->start2($m['date'])->noEnd();
        }

        $seats = $this->http->FindNodes("following-sibling::tr[normalize-space()][1]/*[2]/descendant::node()[{$this->eq($this->t('Your Ticket numbers:'))}]/following-sibling::div/descendant::span[normalize-space()]", $root, "/^[-A-Z\d]+$/");
        $ev->booked()->seats($seats);

        $totalPrice = $this->http->FindSingleNode("following-sibling::tr[normalize-space()][1]/*[3]", $root, true, "/^.*\d.*$/");

        if (preg_match('/^(?<currency>[^\d)(]+?)[ ]*(?<amount>\d[,.\'\d ]*)(?<currencyCode>[A-Z]{3})$/', $totalPrice, $matches)) {
            // £178.50 GBP
            $currencyCode = empty($matches['currencyCode']) ? null : $matches['currencyCode'];
            $ev->price()->currency($currencyCode ?? $matches['currency'])->cost(PriceHelper::parse($matches['amount'], $currencyCode));
        }
    }

    private function assignLang(): bool
    {
        if (!isset(self::$dictionary, $this->lang)) {
            return false;
        }

        foreach (self::$dictionary as $lang => $phrases) {
            if (!is_string($lang) || empty($phrases['Your Booking Reference'])) {
                continue;
            }

            if ($this->http->XPath->query("//*[{$this->contains($phrases['Your Booking Reference'])}]")->length > 0) {
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
