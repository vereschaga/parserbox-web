<?php

namespace AwardWallet\Engine\parkingspot\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class Parking extends \TAccountChecker
{
    public $mailFiles = "parkingspot/it-1.eml, parkingspot/it-164260495.eml, parkingspot/it-2351938.eml, parkingspot/it-66020419.eml";
    private $lang = 'en';
    private $reFrom = ['@theparkingspot.com'];
    private $reProvider = 'Parking Spot';
    private $reSubject = [
        'The Parking Spot North Reservation',
        'The Parking Spot South Reservation',
        'The Parking Spot Reservation',
    ];
    private $reBody = [
        'en' => [
            ['Thank you for making a reservation at The Parking Spot', 'Parking type'],
            ['Thank you for your reservation at The Parking Spot', 'Parking type'],
            ['The Parking Spot', 'Parking type'],
        ],
    ];
    private static $dictionary = [
        'en' => [
            'Confirmation#'            => ['Confirmation#', 'Confirmation #', 'Confirmation Number:', 'Confirmation Number :', 'Certificate Number #:'],
            'Spot Club Account Number' => ['Spot Club Account Number', 'SPOT CLUB ACCOUNT NUMBER', 'Spot Club Card Number'],
            'Phone:'                   => ['Phone:', 'Phone :'],
            'priceHeader'              => ['Paid Now', 'Estimated due at exit'],
            'totalPrice'               => ['Total Paid', 'Total Due Now', 'Total Due at Exit'],
        ],
    ];

    private $patterns = [
        'phone'         => '[+(\d][-+. \d)(]{5,}[\d)]', // +377 (93) 15 48 52    |    (+351) 21 342 09 07    |    713.680.2992
        'travellerName' => '[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]]', // Mr. Hao-Li Huang
    ];

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        if (!$this->assignLang()) {
            $this->logger->debug('can\'t determine a language');

            return $email;
        }
        $email->setType('Parking' . ucfirst($this->lang));

        $e = $email->add()->parking();

        $xpathBold = '(self::b or self::strong or ancestor-or-self::*[contains(@style,"bold")])';

        $confirmation = $this->http->FindSingleNode("//text()[{$this->contains('Confirmation#')}]/following::text()[normalize-space()][not(contains(normalize-space(), ':'))][1]", null, false, '/^\d{5,}$/')
        ?? $this->http->FindSingleNode("//text()[{$this->eq($this->t('Image above not displaying? Click'))}]/following::text()[normalize-space()][1]/ancestor::a[@href][last()]/@href", null, true, "/[?&;]confirmationId=(\d{5,})(?:[?&;_]|$)/i");

        if ($confirmation) {
            $confirmationTitle = $this->http->FindSingleNode("//text()[{$this->contains('Confirmation#')}]", null, true, '/^(.+?)[\s:：]*$/u');
            $e->general()->confirmation($confirmation, $confirmationTitle);
        } elseif ($this->http->XPath->query("//text()[contains(normalize-space(),'Certificate Number #')]/following::text()[normalize-space() and ancestor::*[{$xpathBold}]][1][{$this->contains('Date & time of reservation:')}]/following::text()[normalize-space() and ancestor::*[{$xpathBold}]][1][{$this->contains('Facility')}]")->length > 0) {
            // ???
            $e->general()->noConfirmation();
        }

        $name = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Dear'))}]", null, true,
            "/{$this->opt($this->t('Dear'))}\s+({$this->patterns['travellerName']})(?:\s*[,;:!?]|$)/u");
        $e->general()->traveller($name);

        if ($date = $this->http->FindSingleNode("//text()[{$this->contains('Date & time of reservation:')}]/following::text()[normalize-space()][1]")) {
            $e->general()->date2($date);
        }

        if ($account = $this->http->FindSingleNode("//text()[{$this->contains('Spot Club Account Number')}]/following::text()[normalize-space()][1]",
            null, false, '/^\d{5,}$/')) {
            $e->program()->account($account, false);
        }

        // Place
        $parkingType = $this->http->FindSingleNode("(//text()[{$this->contains('Parking type:')}]/following::text()[normalize-space()])[1]");
        $e->place()->location($parkingType);

        $address = $this->http->FindSingleNode("//tr[ count(*)=2 and *[1][{$this->eq($this->t('Address:'))}] ]/*[2]", null, true, '/^.*\d.*$/')
            ?? $this->http->FindSingleNode("//text()[{$this->contains('Address:')}]/following::text()[normalize-space()][1]");

        $phone = $this->http->FindSingleNode("//text()[{$this->contains('Phone:')}]/following::text()[normalize-space()][1]", null, true, "/^{$this->patterns['phone']}$/");

        if (!$address) {
            // it-164260495.eml
            $addressText = implode("\n", $this->http->FindNodes("//text()[{$this->eq('Parking type:')}]/preceding::text()[normalize-space()][1]/ancestor::td[1]/descendant::text()[normalize-space()]"));

            if (preg_match("/\n{$this->opt('Facility')}\n+((?:.+\n+){1,4}){$this->opt('Parking type:')}/", $addressText, $m)) {
                if (preg_match("/^\s*(?<address>\S[\s\S]+?\S)\n+[ ]*(?<phone>{$this->patterns['phone']})(?:\n|\s*$)/", $m[1], $m2)) {
                    $address = preg_replace('/\s+/', ' ', $m2['address']);
                    $phone = $m2['phone'];
                } else {
                    $address = preg_replace('/\s+/', ' ', trim($m[1]));
                }
            }
        }

        if ($address) {
            $e->place()->address(preg_replace('/([%]{2,}[^%\s]+[%]{2,}[,\s]*)+/', '', $address));
        }

        if ($phone) {
            $e->place()->phone(str_replace('.', '-', $phone));
        }

        // Booked
        $val = $this->http->FindSingleNode("//text()[{$this->contains('Check in date/time:')}]/following::text()[normalize-space()][1]");
        $e->booked()->start2($val);
        $val = $this->http->FindSingleNode("//text()[{$this->contains('Check out date/time:')}]/following::text()[normalize-space()][1]");
        $e->booked()->end2($val);

        $totalPrice = null;
        $xpathPriceContainer = "";

        foreach ((array) $this->t('priceHeader') as $phrase) {
            $totalPrice = $this->http->FindSingleNode("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('totalPrice'))}] ][ ancestor::*[descendant::text()[normalize-space()][1][{$this->eq($phrase)}]] ]/*[normalize-space()][2]", null, true, '/^.*\d.*$/');

            if ($totalPrice !== null) {
                $xpathPriceContainer = "(//text()[{$this->eq($phrase)}]/ancestor::*[ descendant::text()[normalize-space()][2] ][1])[1]/";

                break;
            }
        }

        if ($totalPrice !== null && preg_match("/^(\d[,.\'\d ]*{$this->opt($this->t('points'))})\s+[+]\s+(.*\d.*)$/", $totalPrice, $m)) {
            // 380 points + $24.46
            $e->price()->spentAwards($m[1]);
            $totalPrice = $m[2];
        } elseif ($totalPrice !== null && preg_match("/^\d[,.\'\d ]*{$this->opt($this->t('points'))}$/", $totalPrice, $m)) {
            // 380 points
            $e->price()->spentAwards($m[0]);
        }

        if (preg_match('/^(?<currency>[^\d)(]+?)[ ]*(?<amount>\d[,.\'\d ]*)$/', $totalPrice, $matches)) {
            // $70.65
            $currencyCode = preg_match('/^[A-Z]{3}$/', $matches['currency']) ? $matches['currency'] : null;
            $e->price()->currency($matches['currency'])->total(PriceHelper::parse($matches['amount'], $currencyCode));

            $tax = $this->http->FindSingleNode("descendant::tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('Taxes and Fees'))}] ]/*[normalize-space()][2]", null, true, '/^.*\d.*$/');

            if (preg_match('/^(?:' . preg_quote($matches['currency'], '/') . ')?[ ]*(?<amount>\d[,.\'\d ]*?)$/', $tax, $m)) {
                $e->price()->tax(PriceHelper::parse($m['amount'], $currencyCode));
            }

            $discountAmounts = [];

            $xpathDigits = "contains(translate(.,'0123456789','∆'),'∆')";
            $xpathPriceRow = "table[descendant::tr[ count(*[normalize-space()])=2 and *[normalize-space()][2][{$xpathDigits}] ]]";
            $priceRows = $this->http->XPath->query($xpathPriceContainer . "descendant-or-self::*[count({$xpathPriceRow})>1][1]/*[normalize-space()]");

            foreach ($priceRows as $pRow) {
                $priceCharge = $this->http->FindSingleNode("descendant-or-self::*[count(*[normalize-space()])=2][1]/*[normalize-space()][2]", $pRow);

                if (preg_match('/^-\s*(?:' . preg_quote($matches['currency'], '/') . ')?[ ]*(?<amount>\d[,.\'\d ]*)$/', $priceCharge, $m)) {
                    $discountAmounts[] = PriceHelper::parse($m['amount'], $currencyCode);
                }
            }

            if (count($discountAmounts) > 0) {
                $e->price()->discount(array_sum($discountAmounts));
            }
        }

        if (!$account || !$address || !$phone) {
            $this->parsePdf($e, $parser);
        }

        return $email;
    }

    public function detectEmailFromProvider($from)
    {
        return $this->arrikey($from, $this->reFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->detectEmailFromProvider($headers['from']) !== true) {
            return false;
        }

        return $this->arrikey($headers['subject'], $this->reSubject) !== false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//text()[{$this->contains($this->reProvider)}]")->length === 0) {
            return false;
        }

        if ($this->assignLang()) {
            return true;
        }

        return false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
    }

    private function parsePdf(\AwardWallet\Schema\Parser\Common\Parking $e, PlancakeEmailParser $parser): void
    {
        $text = '';
        $pdfs = $parser->searchAttachmentByName('.*pdf');

        foreach ($pdfs as $pdf) {
            $textPdf = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (!$textPdf) {
                continue;
            }

            if ($this->arrikey($textPdf, $this->t('Spot Club Account Number')) !== false) {
                $text .= $textPdf . "\n\n";
            }
        }

        if (!$text) {
            return;
        }

        /*
            ACCOUNTS
        */

        $accounts = [];

        if (count($e->getAccountNumbers()) === 0 && $e->getStartDate() && $e->getEndDate()) {
            $dateStartVal = date('F j h:i A', $e->getStartDate());
            $dateEndVal = date('F j h:i A', $e->getEndDate());
            $accText = preg_match_all("/((?:.+\n+){1,5})[ ]*{$dateStartVal}[ ]+{$dateEndVal}\n/", $text, $accTextMatches) ? implode("\n", $accTextMatches[1]) : '';
            $accounts = preg_match_all("/{$this->opt($this->t('Spot Club Account Number'))}\n+(?:[ ]*|.*\S[ ]{2,})(\d{11,})\n/i", $accText, $accMatches) ? $accMatches[1] : [];
        }

        if (count($accounts) > 0) {
            $e->program()->accounts(array_unique($accounts), false);
        }

        /*
            ADDRESS and PHONE
        */

        $address = $phone = null;

        if (preg_match("/\n[ ]*{$this->opt($this->t('The Parking Spot'))}.*\n+((?:.+\n+){1,4}?)[ ]*{$this->opt($this->t('CONTACT INFORMATION'))}/", $text, $m)) {
            if (preg_match("/^\s*(?<address>\S[\s\S]+?\S)\n+[ ]*(?<phone>{$this->patterns['phone']})(?:\n|\s*$)/", $m[1], $m2)) {
                $address = preg_replace('/\s+/', ' ', $m2['address']);
                $phone = $m2['phone'];
            } else {
                $address = preg_replace('/\s+/', ' ', trim($m[1]));
            }
        }

        if (empty($e->getAddress()) && $address) {
            $e->place()->address($address);
        }

        if (empty($e->getPhone()) && $phone) {
            $e->place()->phone(str_replace('.', '-', $phone));
        }
    }

    private function assignLang(): bool
    {
        foreach ($this->reBody as $lang => $values) {
            foreach ($values as $value) {
                if ($this->http->XPath->query("//text()[{$this->contains($value[0])}]")->length > 0
                    && $this->http->XPath->query("//text()[{$this->contains($value[1])}]")->length > 0) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function contains($field)
    {
        $field = (array) $this->t($field);

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(' or ', array_map(function ($s) {
            return 'contains(normalize-space(.),"' . $s . '")';
        }, $field));
    }

    private function eq($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'normalize-space(' . $node . ")='" . $s . "'";
        }, $field)) . ')';
    }

    private function starts($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'starts-with(normalize-space(' . $node . '),"' . $s . '")';
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

    private function arrikey($haystack, array $arrayNeedle)
    {
        foreach ($arrayNeedle as $key => $needles) {
            if (is_array($needles)) {
                foreach ($needles as $needle) {
                    if (stripos($haystack, $needle) !== false) {
                        return $key;
                    }
                }
            } else {
                if (stripos($haystack, $needles) !== false) {
                    return $key;
                }
            }
        }

        return false;
    }
}
