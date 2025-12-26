<?php

namespace AwardWallet\Engine\gwl\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;

class Reservation extends \TAccountChecker
{
    public $mailFiles = "gwl/it-659071377.eml, gwl/it-659265565.eml";

    public $lang;
    public static $dictionary = [
        'en' => [
            'VIEW MY RESERVATION' => ['VIEW MY RESERVATION', 'View My Reservation'],
            'Check In'            => 'Check In',
            'Check Out'           => 'Check Out',
        ],
    ];

    private $detectFrom = "@email.greatwolfmail.com";
    private $detectSubject = [
        // en
        'Confirmation for Reservation #',
        'Looking forward to your stay,',
    ];

    // Main Detects Methods
    public function detectEmailFromProvider($from)
    {
        return preg_match("/[@.]greatwolfmail\.com\b/", $from) > 0;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (stripos($headers["from"], $this->detectFrom) === false) {
            return false;
        }

        foreach ($this->detectSubject as $dSubject) {
            if (stripos($headers["subject"], $dSubject) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if (
            $this->http->XPath->query("//a[{$this->contains(['greatwolfmail.com'], '@href')}]")->length === 0
            && $this->http->XPath->query("//*[{$this->contains(['Great Wolf Resorts, Inc.'])}]")->length === 0
        ) {
            return false;
        }

        foreach (self::$dictionary as $dict) {
            if (!empty($dict['VIEW MY RESERVATION'])
                && $this->http->XPath->query("//a[{$this->eq($dict['VIEW MY RESERVATION'])}][contains(@href, 'greatwolfmail')]")->length > 0
                && !empty($dict['Check In']) && !empty($dict['Check Out'])
                && $this->http->XPath->query("//tr[*[normalize-space()][1]//*[{$this->contains($dict['Check In'])}]][*[normalize-space()][2]//*[{$this->contains($dict['Check Out'])}]]")->length > 0
            ) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();

        if (empty($this->lang)) {
            $this->logger->debug("can't determine a language");

            return $email;
        }
        $this->parseEmailHtml($email);

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

    private function assignLang()
    {
        foreach (self::$dictionary as $lang => $dict) {
            if (!empty($dict["VIEW MY RESERVATION"]) && $this->http->XPath->query("//*[{$this->eq($dict['VIEW MY RESERVATION'])}]")->length > 0) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
    }

    private function parseEmailHtml(Email $email)
    {
        $h = $email->add()->hotel();

        // General
        $h->general()
            ->confirmation($this->http->FindSingleNode("//text()[{$this->eq($this->t('Confirmation Number'))}]/following::text()[normalize-space()][1]",
                null, true, "/^\s*([A-Z\d]{5,})\s*$/"))
            ->traveller($this->http->FindSingleNode("//tr[{$this->eq($this->t('Guest Name'))}]/following-sibling::tr[normalize-space()][1]"))
        ;

        // Hotel
        $location = $this->http->FindSingleNode("//img[@alt='Great Wolf Lodge']/following::text()[normalize-space()][1]",
            null, true, "/^.*, [A-Z]{2,3}\s*$/");

        if (!empty($location)) {
            $h->hotel()
                ->name('Great Wolf Lodge, ' . $location)
                ->noAddress();
        }

        //Booked
        $ciDate = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Check In'))}]/following::text()[normalize-space()][1]",
            null, true, "/^\s*(.*\b\d{4}\b.*)\s*$/");
        $ciTime = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Check In'))}]/following::text()[normalize-space()][2]",
            null, true, "/^\s*(?:[[:alpha:] ]+ )?(\d{1,2}:\d{2}(?:\s*[ap]m)?)\s*$/i");

        if (!empty($ciDate) && !empty($ciTime)) {
            $h->booked()
                ->checkIn(strtotime($ciDate . ', ' . $ciTime));
        }
        $coDate = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Check Out'))}]/following::text()[normalize-space()][1]",
            null, true, "/^\s*(.*\b\d{4}\b.*)\s*$/");
        $coTime = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Check Out'))}]/following::text()[normalize-space()][2]",
            null, true, "/^\s*(?:[[:alpha:] ]+ )?(\d{1,2}:\d{2}(?:\s*[ap]m)?)\s*$/i");

        if (!empty($coDate) && !empty($coTime)) {
            $h->booked()
                ->checkOut(strtotime($coDate . ', ' . $coTime));
        }

        $guests = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Guests'))}]/following::text()[normalize-space()][1]");

        if ($guests) {
            $h->booked()
                ->guests($this->re("/^\s*{$this->opt($this->t('Adults'))}\s*–\s*(\d+)\b/i", $guests))
                ->kids($this->re("/\b{$this->opt($this->t('Children'))}[*]?\s*–\s*(\d+)\b/i", $guests), true, true)
            ;
        }

        $type = $this->http->FindSingleNode("//tr[{$this->eq($this->t('Room Type'))}]/following-sibling::tr[normalize-space()][1]");

        if ($type) {
            $h->addRoom()
                ->setType($type);
        }

        // Price
        $total = $this->http->FindSingleNode("//tr[{$this->eq($this->t('Total'))}]/following-sibling::tr[normalize-space()][1]");

        if (preg_match('#^\s*(?<currency>\D[^\d)(]{0,5})\s*(?<amount>\d[,.\d ]*)\s*$#', $total, $m)
            || preg_match('#^\s*(?<amount>\d[,.\d ]*)\s*(?<currency>\D[^\d)(]{0,5})\s*$#', $total, $m)
        ) {
            $h->price()
                ->total(PriceHelper::parse($m['amount']))
                ->currency(trim($m['currency']))
            ;
        }

        return true;
    }

    private function inTd($field)
    {
        return $this->http->FindSingleNode("//tr[not(.//tr)]/*[descendant::text()[normalize-space()][1][{$this->eq($field)}]]");
    }

    private function t($s)
    {
        if (!isset($this->lang, self::$dictionary[$this->lang], self::$dictionary[$this->lang][$s])) {
            return $s;
        }

        return self::$dictionary[$this->lang][$s];
    }

    // additional methods

    private function contains($field, $text = 'normalize-space(.)')
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($text) {
            return 'contains(' . $text . ',"' . $s . '")';
        }, $field)) . ')';
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) {
            return 'normalize-space(.)="' . $s . '"';
        }, $field)) . ')';
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) {
            return 'starts-with(normalize-space(.),"' . $s . '")';
        }, $field)) . ')';
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function opt($field, $delimiter = '/')
    {
        $field = (array) $field;

        if (empty($field)) {
            $field = ['false'];
        }

        return '(?:' . implode("|", array_map(function ($s) use ($delimiter) {
            return str_replace(' ', '\s+', preg_quote($s, $delimiter));
        }, $field)) . ')';
    }
}
