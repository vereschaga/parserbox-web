<?php

namespace AwardWallet\Engine\louvre\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;

class YourOrder extends \TAccountChecker
{
    public $mailFiles = "louvre/it-389682464.eml, louvre/it-408769896.eml, louvre/it-400125360.eml, louvre/it-400496906.eml, louvre/it-401814697.eml";

    public $lang = '';

    public static $dictionary = [
        'en' => [
            'confNumber'    => ['Booking number:', 'Booking number :'],
            'Order details' => ['Order details'],
            'buyer'         => ['Buyer:', 'Buyer :'],
        ],
    ];

    private $subjects = [
        'en' => ['E-ticket booking confirmation'],
    ];

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[.@]louvre\.fr\b/i', $from) > 0;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (stripos($headers['subject'], 'Musée du Louvre - E-ticket booking confirmation') !== false) {
            return true;
        }

        if ($this->detectEmailFromProvider($headers['from']) !== true && strpos($headers['subject'], 'Musée du Louvre') === false) {
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
        if ($this->http->XPath->query('//a[contains(@href,".louvre.fr/") or contains(@href,".ticketlouvre.fr/") or contains(@href,"www.louvre.fr") or contains(@href,"www.ticketlouvre.fr")]')->length === 0
            && $this->http->XPath->query('//text()[normalize-space()="The Louvre team"]')->length === 0
        ) {
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
        $email->setType('YourOrder' . ucfirst($this->lang));

        $patterns = [
            'time' => '\d{1,2}(?:[:：]\d{2})?(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?)?', // 4:19PM    |    2:00 p. m.    |    3pm
            'date' => '\d{1,2}[-,. ]+[[:alpha:]]+[-,. ]+(?:\d{2}|\d{4})', // 17 June 2023
        ];

        /* Price for all events */

        $totalPrice = $this->http->FindSingleNode("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('Total paid online'))}] ]/*[normalize-space()][2]", null, true, '/^.*\d.*$/');

        if (preg_match('/^(?<amount>\d[,.‘\'\d ]*?)[ ]*(?<currency>[^\-\d)(]+)$/u', $totalPrice, $matches)) {
            // 119,00 €
            $currencyCode = preg_match('/^[A-Z]{3}$/', $matches['currency']) ? $matches['currency'] : null;
            $email->price()->total(PriceHelper::parse($matches['amount'], $currencyCode))->currency($matches['currency']);
        }

        /* Step 1/3: Pre-parsing general information */

        $confirmation = $this->http->FindSingleNode("//text()[{$this->eq($this->t('confNumber'))}]/following::text()[normalize-space()][1]", null, true, '/^[A-Z\d]{5,}$/');
        $confirmationTitle = $this->http->FindSingleNode("//text()[{$this->eq($this->t('confNumber'))}]", null, true, '/^(.+?)[\s:：]*$/u');
        $traveller = $this->http->FindSingleNode("//text()[{$this->eq($this->t('buyer'))}]/following::text()[normalize-space()][1]", null, true, '/^([[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]])(?:\s*\(|$)/u');
        $contacts = $this->htmlToText($this->http->FindHTMLByXpath("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('Total paid online'))}] ]/following::text()[normalize-space()='Musée du Louvre']/ancestor::*[ descendant::text()[normalize-space()][2] ][1]"));
        $address = preg_match("/(?:^|\n)[ ]*Musée du Louvre[ ]*\n+[ ]*(.{3,}?)[ ]*\n+[ ]*{$this->opt($this->t('Siret:'))}/i", $contacts, $m) ? $m[1] : null;

        /* Step 2/3: Parsing all order rows */

        $orderId = $orderContent = [];
        $orderRows = $this->http->XPath->query("//tr[{$this->eq($this->t('Order details'))}]/following-sibling::tr[normalize-space()][1]/descendant::*[ tr[normalize-space()][2] ][1]/tr[count(*[normalize-space()])=3]");

        foreach ($orderRows as $oRow) {
            $cell1Text = $this->htmlToText($this->http->FindHTMLByXpath("*[normalize-space()][1]", null, $oRow));
            // $this->logger->debug($cell1Text);
            $cell1Rows = preg_split("/[ ]*\n+[ ]*/", $cell1Text);

            $cell2Text = $this->http->FindSingleNode("*[normalize-space()][2]", $oRow);
            // $this->logger->debug($cell2Text);

            $cell3Text = $this->htmlToText($this->http->FindHTMLByXpath("*[normalize-space()][3]", null, $oRow));
            $cell3Text = preg_replace("/[ ]*\n+[ ]*/", ' ', $cell3Text); // multi-line >> single-line
            $cell3Text = preg_replace("/({$patterns['date']}[ ]+(?:{$this->opt($this->t('at'))}[ ]+{$patterns['time']}|{$this->opt($this->t('Valid all day'))}))[ ]+(\S)/", "$1\n$2", $cell3Text); // single-line >> multi-line
            // $this->logger->debug($cell3Text);
            $cell3Rows = preg_split("/[ ]*\n+[ ]*/", $cell3Text);

            foreach ($cell3Rows as $c3Row) {
                $orderId[] = $cell1Rows[0] . ' ∆ ' . $c3Row;
                $orderContent[] = [$cell1Text, $cell2Text, $c3Row];
            }
        }

        $orderUniqueId = array_unique($orderId);

        /* Step 3/3: Creating unique events */

        foreach ($orderUniqueId as $position => $id) {
            $ev = $email->add()->event();
            $ev->type()->event();

            $ev->general()->confirmation($confirmation, $confirmationTitle);
            $ev->general()->traveller($traveller, true);

            $cell1Text = $orderContent[$position][0];
            $cell2Text = $orderContent[$position][1];
            $cell3Text = $orderContent[$position][2];

            // $cell1Rows = preg_split("/[ ]*\n+[ ]*/", $cell1Text);
            // $under18s = count($cell1Rows) > 1 && preg_match("/^{$this->opt($this->t('Under18s'))}$/i", $cell1Rows[1]) > 0;
            // $ticketCount = preg_match("/^[^x:]+[ ]*x[ ]*(\d{1,3})[ ]*[:]+[ ]*$/i", $cell2Text, $m) ? $m[1] : null;

            if (preg_match("/^(?<name>.{2,}?)[ ]+{$this->opt($this->t('on'))}[ ]+(?<date>{$patterns['date']})[ ]+{$this->opt($this->t('at'))}[ ]+(?<time>{$patterns['time']})$/iu", $cell3Text, $m)) {
                // Musée Web on 17 June 2023 at 14:30
                $ev->place()->name($m['name']);
                $ev->booked()->start(strtotime($m['time'], strtotime($m['date'])))->noEnd();
            } elseif (preg_match("/^(?<name>.{2,}?)[ ]+{$this->opt($this->t('on'))}[ ]+(?<date>{$patterns['date']})[ ]+{$this->opt($this->t('Valid all day'))}$/iu", $cell3Text, $m)) {
                // Musée Web on 17 June 2023 Valid all day
                $ev->place()->name($m['name']);
                $date = strtotime($m['date']);
                $ev->booked()->start(strtotime('00:00', $date))->end(strtotime('23:59', $date));
            }

            $ev->place()->address($address);
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

    private function assignLang(): bool
    {
        if (!isset(self::$dictionary, $this->lang)) {
            return false;
        }

        foreach (self::$dictionary as $lang => $phrases) {
            if (!is_string($lang) || empty($phrases['confNumber']) || empty($phrases['Order details'])) {
                continue;
            }

            if ($this->http->XPath->query("//*[{$this->contains($phrases['confNumber'])}]")->length > 0
                && $this->http->XPath->query("//tr[{$this->eq($phrases['Order details'])}]")->length > 0
            ) {
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
