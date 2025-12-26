<?php

namespace AwardWallet\Engine\reserveam\Email;

use AwardWallet\Schema\Parser\Email\Email;

class Junk extends \TAccountChecker
{
    public $mailFiles = "reserveam/it-855685602.eml, reserveam/it-857916735.eml, reserveam/it-858306521.eml, reserveam/it-863971169.eml";

    public $lang = 'en';

    public $detectSubjects = [
        'en' => [
            'Confirmation Letter Email',
        ],
    ];

    public $detectBody = [
        'en' => [
            'Order #',
        ],
    ];

    public static $dictionary = [
        'en' => [
            'Purchase Date:'          => ['Purchase Date:'],
            'ORDER DETAILS'           => ['ORDER DETAILS'],
            'POS Confirmation Letter' => ['POS Confirmation Letter'],
            'RESERVATION DETAILS'     => ['RESERVATION DETAILS', 'TICKET INFO', 'PERMIT INFO'],
            'checkIn'                 => ['Arrival Date:', 'Arrival Date :', 'Date:'],
            'checkOut'                => ['Departure Date:', 'Departure Date :'],
            'Primary Occupant:'       => ['Primary Occupant:', 'PRIMARY OCCUPANTS:'],
            'Site:'                   => ['Site:', 'SITE:'],
            'occupants'               => ['# of Occupants:', 'Number of Occupants', 'Occupants'],
        ],
    ];

    public function detectEmailFromProvider($from)
    {
        return preg_match("/[@.]reserveamerica\.com\b/", $from) > 0;
    }

    public function detectEmailByHeaders(array $headers)
    {
        // detect Provider
        if (empty($headers['from']) || stripos($headers['from'], 'reserveamerica.com') === false) {
            return false;
        }

        // detect Format
        foreach ($this->detectSubjects as $detectSubjects) {
            foreach ($detectSubjects as $dSubjects) {
                if (stripos($headers['subject'], $dSubjects) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        // detect Provider
        if (
            $this->http->XPath->query("//a/@href[{$this->contains(['reserveamerica.com'])}]")->length === 0
        ) {
            return false;
        }

        // detect Format
        foreach ($this->detectBody as $lang => $detectBody) {
            if ($this->http->XPath->query("//*[{$this->contains($detectBody)}]")->length > 0
                && !empty(self::$dictionary[$lang]) && !empty(self::$dictionary[$lang]['Purchase Date:'])
                && (!empty(self::$dictionary[$lang]['ORDER DETAILS']) || !empty(self::$dictionary[$lang]['POS Confirmation Letter']))
                && $this->http->XPath->query("//text()[{$this->eq(self::$dictionary[$lang]['Purchase Date:'])}]")->length > 0
                && ($this->http->XPath->query("//text()[{$this->eq(self::$dictionary[$lang]['ORDER DETAILS'])}]")->length > 0
                    || $this->http->XPath->query("//text()[{$this->eq(self::$dictionary[$lang]['POS Confirmation Letter'])}]")->length > 0)
            ) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
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

    private function parseEmailHtml(Email $email)
    {
        if (
            $this->http->XPath->query("//text()[{$this->eq($this->t('RESERVATION DETAILS'))}]")->length === 0
            && $this->http->XPath->query("//text()[{$this->eq($this->t('DIRECTIONS'))}]")->length === 0
            && $this->http->XPath->query("//text()[{$this->starts($this->t('Campground:'))}]")->length === 0
            && $this->http->XPath->query("//text()[{$this->starts($this->t('Sales Location Address'))}]")->length === 0
            && $this->http->XPath->query("//text()[{$this->starts($this->t('checkIn'))}]")->length === 0
            && $this->http->XPath->query("//text()[{$this->starts($this->t('checkOut'))}]")->length === 0
            && $this->http->XPath->query("//text()[{$this->starts($this->t('Check-In Time:'))}]")->length === 0
            && $this->http->XPath->query("//text()[{$this->starts($this->t('Check-Out Time:'))}]")->length === 0
            && $this->http->XPath->query("//text()[{$this->starts($this->t('Site:'))}]")->length === 0
            && $this->http->XPath->query("//text()[{$this->starts($this->t('Primary Occupant:'))}]")->length === 0
            && $this->http->XPath->query("//text()[{$this->starts($this->t('occupants'))}]")->length === 0
        ) {
            $email->setIsJunk(true, "Hotel reservation without name, address, check-in and check-out");
        }

        return true;
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

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
}
