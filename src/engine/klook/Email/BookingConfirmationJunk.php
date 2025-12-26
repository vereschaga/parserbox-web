<?php

namespace AwardWallet\Engine\klook\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class BookingConfirmationJunk extends \TAccountChecker
{
    public $mailFiles = "klook/it-527062848.eml";
    public $subjects = [
        'Your booking confirmation for',
    ];

    public $lang = '';
    public $pdfNamePattern = ".*pdf";

    public $detectLand = [
        'de' => ['Paket', 'Datum'],
        'en' => ['Package', 'Date'],
    ];

    public static $dictionary = [
        "en" => [
            'Passenger' => ['Passenger', 'passenger'],
        ],

        "de" => [
            'Package'     => 'Paket',
            'Voucher no.' => 'Voucher-Nr.',
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@klook.com') !== false) {
            foreach ($this->subjects as $subject) {
                if (stripos($headers['subject'], $subject) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        if (count($pdfs) > 0) {
            return false;
        }

        $this->assignLang();

        if ($this->http->XPath->query("//text()[{$this->contains($this->t('Klook Travel Technology Limited'))}]")->length > 0) {
            return ($this->http->XPath->query("//text()[{$this->contains($this->t('Manage booking'))}]")->length > 0)
                && $this->http->XPath->query("//text()[{$this->starts($this->t('Booked by:'))}]")->length > 0
                && $this->http->XPath->query("//text()[{$this->starts($this->t('Package:'))}]")->length > 0
                && $this->http->XPath->query("//text()[{$this->starts($this->t('Participation date:'))}]")->length > 0
                && $this->http->XPath->query("//text()[{$this->contains($this->t('Passenger'))}]")->length == 0;
        }

        return false;
    }

    public function assignLang()
    {
        foreach ($this->detectLand as $lang => $words) {
            foreach ($words as $word) {
                if ($this->http->XPath->query("//text()[{$this->contains($word)}]")->length > 0) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]klook\.com$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        if ($this->detectEmailByBody($parser) === true) {
            $email->setIsJunk(true);
        }

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

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) {
            return "starts-with(normalize-space(.), \"{$s}\")";
        }, $field));
    }

    private function contains($field, $text = 'normalize-space(.)')
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($text) { return 'contains(' . $text . ',"' . $s . '")'; }, $field)) . ')';
    }
}
