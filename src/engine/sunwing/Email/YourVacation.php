<?php

namespace AwardWallet\Engine\sunwing\Email;

use AwardWallet\Schema\Parser\Email\Email;
use AwardWallet\Common\Parser\Util\PriceHelper;

class YourVacation extends \TAccountChecker
{
    public $mailFiles = "sunwing/it-842052079.eml, sunwing/it-853610205.eml";

    public $lang = '';

    public static $dictionary = [
        'en' => [
            'Hotel details' => ['Hotel details'],
            'Flight Details' => ['Flight Details'],
            'statusPhrases' => ['Your Sunwing vacation is'],
            'statusVariants' => ['confirmed'],
            'otaConfNumber' => ['Confirmation number'],
            'feeNames' => ['Taxes and Fees'],
        ]
    ];

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[.@]sunwing\.ca$/i', $from) > 0;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return array_key_exists('subject', $headers) && preg_match('/Your Sunwing reservation details/i', $headers['subject']) > 0;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query('//a[contains(@href,".sunwing.ca/") or contains(@href,"www.sunwing.ca") or contains(@href,"www2.sunwing.ca")]')->length === 0
            && $this->http->XPath->query('//text()[starts-with(normalize-space(),"Copyright") and contains(normalize-space(),"Sunwing")]')->length === 0
            && $this->http->XPath->query('//*[contains(normalize-space(),"Thank you for choosing Sunwing")]')->length === 0
        ) {
            return false;
        }
        return $this->assignLang();
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();
        if ( empty($this->lang) ) {
            $this->logger->debug("Can't determine a language!");
            return $email;
        }
        $email->setType('YourVacation' . ucfirst($this->lang));

        $patterns = [
            'time' => '\d{1,2}[:：]\d{2}(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?)?', // 4:19PM  |  2:00 p. m.
            'travellerName' => '[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]]', // Mr. Hao-Li Huang
        ];

        $statusTexts = array_filter($this->http->FindNodes("//text()[{$this->contains($this->t('statusPhrases'))}]", null, "/{$this->opt($this->t('statusPhrases'))}[:\s]+({$this->opt($this->t('statusVariants'))})(?:\s*[,.;:!?]|$)/i"));

        if (count(array_unique(array_map('mb_strtolower', $statusTexts))) === 1) {
            $status = array_shift($statusTexts);
        } else {
            $status = $this->http->FindSingleNode("//tr/*[{$this->eq($this->t('Booking status'), "translate(.,':','')")}]/following-sibling::*[string-length(translate(normalize-space(),':',''))>1]");
        }

        $otaConfirmation = $this->http->FindSingleNode("//tr/*[{$this->eq($this->t('otaConfNumber'), "translate(.,':','')")}]/following-sibling::*[string-length(normalize-space())>2]", null, true, '/^[:\s]*([-A-Z\d]{4,30})$/');

        if ($otaConfirmation) {
            $otaConfirmationTitle = $this->http->FindSingleNode("//tr/*[{$this->eq($this->t('otaConfNumber'), "translate(.,':','')")}]", null, true, '/^(.+?)[\s:：]*$/u');
            $email->ota()->confirmation($otaConfirmation, $otaConfirmationTitle);
        }

        $confirmationDate = strtotime($this->normalizeDate($this->http->FindSingleNode("//tr/*[{$this->eq($this->t('Confirmation date'), "translate(.,':','')")}]/following-sibling::*[string-length(normalize-space())>2]", null, true, "/^[:\s]*(.*\b\d{4}\b.*)$/")));
        /*
        $startDate = strtotime($this->normalizeDate($this->http->FindSingleNode("//tr/*[{$this->eq($this->t('Departing date'), "translate(.,':','')")}]/following-sibling::*[string-length(normalize-space())>2]", null, true, "/^[:\s]*(.*\b\d{4}\b.*)$/")));
        $endDate = strtotime($this->normalizeDate($this->http->FindSingleNode("//tr/*[{$this->eq($this->t('Return date'), "translate(.,':','')")}]/following-sibling::*[string-length(normalize-space())>2]", null, true, "/^[:\s]*(.*\b\d{4}\b.*)$/")));
        */
        $passengers = $this->http->FindNodes("//*/tr[normalize-space()][1][{$this->eq($this->t('Passengers'))}]/following-sibling::tr/descendant::*[normalize-space() and ../self::tr and not(.//tr[normalize-space()])][1]", null, "/^{$patterns['travellerName']}$/u");

        $hotelCheckIn = $hotelCheckOut = null;

        $f = $email->add()->flight();
        $f->general()->status($status)->date($confirmationDate)->travellers($passengers, true);

        if ($otaConfirmation) {
            $f->general()->noConfirmation();
        }

        $segments = $this->http->XPath->query("//tr[ {$this->starts($this->t('Departing:'))} and following-sibling::tr[normalize-space()][1][{$this->starts($this->t('Arriving:'))}] ]");

        foreach ($segments as $i => $root) {
            $s = $f->addSegment();

            /*
                Flight #WG4210 | Class P
                    [OR]
                Flight #WG744 | Class Economy
            */
            $flightVal = $this->http->FindSingleNode("preceding-sibling::tr[normalize-space()][position()<3][{$this->starts($this->t('Flight'))}]", $root);

            if (preg_match("/^{$this->opt($this->t('Flight'))}[ #]+(?<name>[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(?<number>\d+)(?:\s*\||$)/", $flightVal, $m)) {
                $s->airline()->name($m['name'])->number($m['number']);
            }

            if (preg_match("/{$this->opt($this->t('Class'))}[-\s]+([A-Z]{1,2})$/i", $flightVal, $m)) {
                $s->extra()->bookingCode($m[1]);
            } elseif (preg_match("/{$this->opt($this->t('Class'))}[-\s]+([^\-\s].+)$/i", $flightVal, $m)) {
                $s->extra()->cabin($m[1]);
            }

            $airportsText = implode("\n", $this->http->FindNodes("preceding-sibling::tr[normalize-space()][1]/descendant::text()[normalize-space()]", $root));

            if (preg_match("/\(\s*([A-Z]{3})\s*\).*\n.*\(\s*([A-Z]{3})\s*\)/", $airportsText, $m)) {
                $s->departure()->code($m[1]);
                $s->arrival()->code($m[2]);
            }

            $dateDep = $dateArr = null;
            $dateDepVal = $this->http->FindSingleNode(".", $root, true, "/^{$this->opt($this->t('Departing:'))}[:\s]*(.*\b\d{4}\b.*)$/");
            $dateArrVal = $this->http->FindSingleNode("following-sibling::tr[normalize-space()][1]", $root, true, "/^{$this->opt($this->t('Arriving:'))}[:\s]*(.*\b\d{4}\b.*)$/");

            // Tue. 14 Jan, 2025 3:30pm
            $pattern = "/^(?<date>.{6,}?)[-,.;\|\s]+(?<time>{$patterns['time']})/";

            if (preg_match($pattern, $dateDepVal, $m)) {
                $dateDep = strtotime($this->normalizeDate($m['date']));
                $s->departure()->date(strtotime($m['time'], $dateDep));
            }

            if (preg_match($pattern, $dateArrVal, $m)) {
                $dateArr = strtotime($this->normalizeDate($m['date']));
                $s->arrival()->date(strtotime($m['time'], $dateArr));
            }

            if (empty($s->getCabin())) {
                $cabin = $this->http->FindSingleNode("following-sibling::tr[normalize-space()][1][{$this->starts($this->t('Arriving:'))}]/following-sibling::tr[normalize-space()]", $root);
                $s->extra()->cabin($cabin, false, true);
            }

            if ($segments->length > 1 && $i === 0 && $dateArr) {
                $hotelCheckIn = $dateArr;
            }
        }

        if ($segments->length > 1 && $dateDep) {
            $hotelCheckOut = $dateDep;
        }

        $hotelText = implode("\n", $this->http->FindNodes("//*/tr[normalize-space()][1][{$this->eq($this->t('Hotel details'))}]/following-sibling::tr[normalize-space()]"));

        if ($hotelText) {
            $this->logger->info('Hotel details:');
            $this->logger->debug($hotelText);
            $h = $email->add()->hotel();
            $h->general()->status($status)->date($confirmationDate)->travellers($passengers, true);

            if ($otaConfirmation) {
                $h->general()->noConfirmation();
            }

            if (preg_match("/^(?<name>.{2,})\n(?<region>.{2,}?)(?:[ ]*\||\n\d{1,3}[ ]*{$this->opt($this->t('room'))}|$)/", $hotelText, $m)) {
                $hotelName = trim($m['name'], ',.; ');
                $address = $hotelName . ', ' . trim($m['region'], ',.; ');
                $h->hotel()->name($hotelName)->address($address);
            }

            $h->booked()->checkIn($hotelCheckIn)->checkOut($hotelCheckOut);

            if (preg_match("/\n(\d{1,3})[ ]*{$this->opt($this->t('room'))}/i", $hotelText, $m)) {
                $h->booked()->rooms($m[1]);
            }

            if (preg_match("/\n\d{1,3}[ ]*{$this->opt($this->t('room'))}.*\n(.{2,})(?:\n.*{$this->opt($this->t('Adult'))}|$)/i", $hotelText, $m)
                && !preg_match("/{$this->opt($this->t('Adult'))}/i", $m[1])
            ) {
                $room = $h->addRoom();
                $room->setType($m[1]);
            }

            if (preg_match("/\n(\d{1,3})[ ]*{$this->opt($this->t('Adult'))}/i", $hotelText, $m)) {
                $h->booked()->guests($m[1]);
            }
        }

        $totalPrice = $this->http->FindSingleNode("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('Grand Total'), "translate(.,':','')")}] ]/*[normalize-space()][2]", null, true, '/^.*\d.*$/');

        if (preg_match('/^(?<currency>[^\-\d)(]+?)[ ]*(?<amount>\d[,.’‘\'\d ]*)$/u', $totalPrice, $matches)) {
            // $10824.40
            $currencyCode = preg_match('/^[A-Z]{3}$/', $matches['currency']) ? $matches['currency'] : null;
            $email->price()->currency($matches['currency'])->total(PriceHelper::parse($matches['amount'], $currencyCode));

            // it-853610205.eml
            $feeRows = $this->http->XPath->query("//tr[ preceding-sibling::tr[ *[1][{$this->eq($this->t('Price'))}] and *[3][{$this->eq($this->t('Adult'))}] ] and following-sibling::tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('Grand Total'), "translate(.,':','')")}] ] ]");

            foreach ($feeRows as $feeRow) {
                $feeCharge = $this->http->FindSingleNode('*[3]', $feeRow, true, '/^(.*?\d.*?)\s*(?:\(|$)/');

                if ( preg_match('/^(?:' . preg_quote($matches['currency'], '/') . ')?[ ]*(?<amount>\d[,.‘\'\d ]*)$/u', $feeCharge, $m) ) {
                    $feeName = $this->http->FindSingleNode('*[1]', $feeRow, true, '/^(.+?)[\s:：]*$/u');
                    $email->price()->fee($feeName, PriceHelper::parse($m['amount'], $currencyCode));
                }
            }

            if ($feeRows->length === 0) {
                // it-842052079.eml
                $feeRows = $this->http->XPath->query("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('feeNames'), "translate(.,':','')")}] ]");

                foreach ($feeRows as $feeRow) {
                    $feeCharge = $this->http->FindSingleNode('*[2]', $feeRow, true, '/^(.*?\d.*?)\s*(?:\(|$)/');
    
                    if ( preg_match('/^(?:' . preg_quote($matches['currency'], '/') . ')?[ ]*(?<amount>\d[,.‘\'\d ]*)$/u', $feeCharge, $m) ) {
                        $feeName = $this->http->FindSingleNode('*[1]', $feeRow, true, '/^(.+?)[\s:：]*$/u');
                        $email->price()->fee($feeName, PriceHelper::parse($m['amount'], $currencyCode));
                    }
                }
            }
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
        if ( !isset(self::$dictionary, $this->lang) ) {
            return false;
        }
        foreach (self::$dictionary as $lang => $phrases) {
            if ( !is_string($lang) ) {
                continue;
            }
            if (!empty($phrases['Hotel details']) && $this->http->XPath->query("//*[{$this->eq($phrases['Hotel details'])}]")->length > 0
                || !empty($phrases['Flight Details']) && $this->http->XPath->query("//*[{$this->eq($phrases['Flight Details'])}]")->length > 0
            ) {
                $this->lang = $lang;
                return true;
            }
        }
        return false;
    }

    private function t(string $phrase, string $lang = '')
    {
        if ( !isset(self::$dictionary, $this->lang) ) {
            return $phrase;
        }
        if ($lang === '') {
            $lang = $this->lang;
        }
        if ( empty(self::$dictionary[$lang][$phrase]) ) {
            return $phrase;
        }
        return self::$dictionary[$lang][$phrase];
    }

    private function contains($field, string $node = ''): string
    {
        $field = (array)$field;
        if (count($field) === 0)
            return 'false()';
        return '(' . implode(' or ', array_map(function($s) use($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';
            return 'contains(normalize-space(' . $node . '),' . $s . ')';
        }, $field)) . ')';
    }

    private function eq($field, string $node = ''): string
    {
        $field = (array)$field;
        if (count($field) === 0)
            return 'false()';
        return '(' . implode(' or ', array_map(function($s) use($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';
            return 'normalize-space(' . $node . ')=' . $s;
        }, $field)) . ')';
    }

    private function starts($field, string $node = ''): string
    {
        $field = (array)$field;
        if (count($field) === 0)
            return 'false()';
        return '(' . implode(' or ', array_map(function($s) use($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';
            return 'starts-with(normalize-space(' . $node . '),' . $s . ')';
        }, $field)) . ')';
    }

    private function opt($field): string
    {
        $field = (array)$field;
        if (count($field) === 0)
            return '';
        return '(?:' . implode('|', array_map(function($s) {
            return preg_quote($s, '/');
        }, $field)) . ')';
    }

    /**
     * @param string|null $text Unformatted string with date
     * @return string
     */
    private function normalizeDate(?string $text): string
    {
        if ( !is_string($text) || empty($text) )
            return '';
        $in = [
            // Tue. 14 Jan, 2025
            '/^[-[:alpha:]]+[,.\s]+(\d{1,2})[,.\s]*([[:alpha:]]+)[,.\s]+(\d{4})$/u',
        ];
        $out = [
            '$1 $2 $3',
        ];
        return preg_replace($in, $out, $text);
    }
}
