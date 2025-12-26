<?php

namespace AwardWallet\Engine\gha\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Common\Hotel;
use AwardWallet\Schema\Parser\Email\Email;

class Confirmation extends \TAccountChecker
{
    public $mailFiles = "gha/it-137103085.eml";

    public $reFrom = "@kempinski.com";
    public $reBody = [
        'en' => ['RESERVATION CONFIRMATION', 'Your confirmation number is'],
    ];
    public $reSubject = [
        'Your Reservation Modification', 'Your Reservation Confirmation',
    ];
    public $lang = '';

    public static $dict = [
        'en' => [
            'contactFields'       => ['Telephone', 'Fax', 'Email'],
            'dateCheckIn'         => ['Check-in', 'Check-In'],
            'dateCheckOut'        => ['Check-out', 'Check-Out'],
            'timeCheckIn'         => ['Check-in Time', 'Check-In Time'],
            'timeCheckOut'        => ['Check-out Time', 'Check-Out Time'],
            'Cancellation Policy' => ['Cancellation Policy', 'Cancel Policy'],
        ],
    ];

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();

        if (empty($this->lang)) {
            $this->logger->debug("Can't determine a language!");

            return $email;
        }
        $email->setType('Confirmation' . ucfirst($this->lang));

        $this->parseHotel($email);

        return $email;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query('//a[contains(@href,".kempinski.com/") or contains(@href,"www.kempinski.com")]')->length === 0
            && $this->http->XPath->query('//*[contains(.,"@kempinski.com")]')->length === 0
        ) {
            return false;
        }

        return $this->assignLang();
    }

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->detectEmailFromProvider($headers['from']) !== true) {
            return false;
        }

        if (isset($this->reSubject)) {
            foreach ($this->reSubject as $reSubject) {
                if (stripos($headers["subject"], $reSubject) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->reFrom) !== false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    private function parseHotel(Email $email): void
    {
        $patterns = [
            'time'  => '\d{1,2}(?:[:：]\d{2})?(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?)?',
            'phone' => '[+(\d][-+. \d)(]{5,}[\d)]',
        ];

        $h = $email->add()->hotel();

        $confirmation = $this->http->FindSingleNode("//tr[not(.//tr) and contains(normalize-space(),'Your confirmation number is')]");

        if (preg_match("/(Your confirmation number is)\s+([-A-Z\d]{5,})(?:\s*[,.!;]|$)/", $confirmation, $m)) {
            $m[1] = preg_replace("/^Your\s+/i", '', $m[1]);
            $m[1] = preg_replace("/\s+is$/i", '', $m[1]);
            $h->general()->confirmation($m[2], $m[1]);
        }

        $xpath = "//text()[contains(normalize-space(),'Your confirmation number is')]/ancestor::tr[ following-sibling::tr[normalize-space()] ][1]";
        $roots = $this->http->XPath->query($xpath);

        if ($roots->length > 0) {
            $root = $roots->item(0);
        } else {
            $root = null;
        }

        $xpathHotel = "(following-sibling::tr/descendant::tr[ count(*[normalize-space()])=2 and *[normalize-space()][2][{$this->contains($this->t('contactFields'))}] ])[1]";

        $addressText = $this->htmlToText($this->http->FindHTMLByXpath($xpathHotel . "/*[normalize-space()][1]", null, $root));

        if (preg_match("/^\s*(?<name>.{2,}?)[ ]*\n+[ ]*(?<address>[\s\S]{3,}?)\s*$/", $addressText, $m)) {
            $h->hotel()->name($m['name'])->address(preg_replace('/\s+/', ' ', $m['address']));
        }

        $h->hotel()
            ->phone($this->http->FindSingleNode($xpathHotel . "/*[normalize-space()][2]/descendant::tr[not(.//tr) and {$this->starts($this->t('Telephone'))}]", $root, true, "/^{$this->opt($this->t('Telephone'))}\s*:\s*({$patterns['phone']})$/"));

        $fax = $this->http->FindSingleNode($xpathHotel . "/*[normalize-space()][2]/descendant::tr[not(.//tr) and {$this->starts($this->t('Fax'))}]", $root, true, "/^{$this->opt($this->t('Fax'))}\s*:\s*({$patterns['phone']})$/");

        if (!empty($fax)) {
            $h->hotel()
                ->fax($fax);
        }

        $dateCheckIn = strtotime($this->getField($this->t('dateCheckIn'), $root));
        $dateCheckOut = strtotime($this->getField($this->t('dateCheckOut'), $root));
        $timeCheckIn = $this->http->FindSingleNode("//text()[contains(.,'BOOKING POLICIES')]/ancestor::tr[2]/following-sibling::tr[1]//td[count(descendant::td)=0 and {$this->contains($this->t('timeCheckIn'))}]/following-sibling::td[1]", null, false, "/^({$patterns['time']})(?:\s*\(|$)/");
        $timeCheckOut = $this->http->FindSingleNode("//text()[contains(.,'BOOKING POLICIES')]/ancestor::tr[2]/following-sibling::tr[1]//td[count(descendant::td)=0 and {$this->contains($this->t('timeCheckOut'))}]/following-sibling::td[1]", null, false, "/^({$patterns['time']})(?:\s*\(|$)/");
        $h->booked()
            ->checkIn($timeCheckIn ? strtotime($timeCheckIn, $dateCheckIn) : $dateCheckIn)
            ->checkOut($timeCheckOut ? strtotime($timeCheckOut, $dateCheckOut) : $dateCheckOut)
        ;

        $childrenValue = $this->getField('Children per Room', $root);

        if (preg_match("/^\d{1,3}$/", $childrenValue)) {
            $kids = $childrenValue;
        } elseif (preg_match_all("/\b\d+\s+years old\b/i", $childrenValue, $m)) {
            $kids = count($m[0]);
        } else {
            $kids = null;
        }
        $h->booked()
            ->guests($this->getField(['Adults per room', 'Adults per Room:'], $root, "/^\d{1,3}$/"))
            ->kids($kids)
            ->rooms($this->getField('Number of Rooms', $root, "/^\d{1,3}$/"))
        ;

        $room = $h->addRoom();

        $room->setType($this->getField('Type of room', $root));
        $room->setRate($this->getField('Average Rate per Night', $root));
        $room->setDescription($this->getField('Room Preference', $root), false, true);

        $totalPrice = $this->getField('TOTAL RATE', $root, "/^(.*?\d.*?)(?:\s*\(|$)/");

        if (preg_match('/^(?<currency>[^\d)(]+?)[ ]*(?<amount>\d[,.\'\d ]*)$/', $totalPrice, $matches)) {
            // SGD 1,218.20
            $currencyCode = preg_match('/^[A-Z]{3}$/', $matches['currency']) ? $matches['currency'] : null;
            $h->price()->currency($matches['currency'])->total(PriceHelper::parse($matches['amount'], $currencyCode));
        }

        $travellers = $this->http->FindNodes("//text()[contains(normalize-space(),'GUEST INFORMATION')]/ancestor::tr[2]/following-sibling::tr[1]//td[count(descendant::td)=0 and (contains(.,'Dr') or contains(.,'Mr') or contains(.,'Ms'))]");
        if (empty($travellers)) {
            $travellers = array_filter([$this->http->FindSingleNode("//text()[contains(normalize-space(),'GUEST INFORMATION')]/following::text()[normalize-space()][1]")]);
        }
        $h->general()
            ->travellers($travellers);

        $h->program()->accounts($this->http->FindNodes("//text()[contains(.,'DISCOVERY')]/ancestor::tr[2]/following-sibling::tr[1]//td[count(descendant::td)=0 and contains(normalize-space(),'Membership Number')]", null, "/Membership Number:\s+(.+)/"), false);

        $cancellation = $this->http->FindSingleNode("//text()[contains(normalize-space(),'BOOKING POLICIES')]/ancestor::tr[2]/following-sibling::tr[1]//td[count(descendant::td)=0 and {$this->contains($this->t('Cancellation Policy'))}]/following-sibling::td[1]");
        $h->general()->cancellation($cancellation);

        $h->booked()->parseNonRefundable('Non Cancellable');

        $this->detectDeadLine($h, $h->getCancellation());
    }

    private function getField($field, $root = null, $re = null): ?string
    {
        return $this->http->FindSingleNode("following::tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][not(.//tr) and {$this->starts($field)}] ][1]/*[normalize-space()][2]", $root, true, $re);
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function assignLang(): bool
    {
        foreach ($this->reBody as $lang => $phrases) {
            if ($this->http->XPath->query("//*[{$this->contains($phrases[0])}]")->length > 0
                && $this->http->XPath->query("//*[{$this->contains($phrases[1])}]")->length > 0
            ) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
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

    private function detectDeadLine(Hotel $h, $cancellationText)
    {
        if (preg_match("#Cancel by (?<time>\d+A?P?M) local hotel time on (?<date>(?:\d+\-\w+\-\d+|\w+\s*\d+\,\s*\d{4})) to avoid a penalty charge of#ius", $cancellationText, $m)) {
            $h->booked()
                ->deadline($this->normalizeDate($m['date'] . ', ' . $m['time']));
        }
    }

    private function normalizeDate($str)
    {
        $in = [
            "#(\d+)\-(\w+)\-(\d+)\,\s*(\d+)(A?P?M)#u", //04-MAR-22, 11PM
        ];
        $out = [
            "$1 $2 20$3, $4:00 $5",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = \AwardWallet\Engine\MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return strtotime($str);
    }
}
