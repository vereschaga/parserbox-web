<?php

namespace AwardWallet\Engine\klook\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class SimJunk extends \TAccountChecker
{
    public $mailFiles = "klook/it-466877913.eml";
    public $subjects = [
        'SIM Card',
        'SIM/eSIM',
        'SIM (eSIM)',
        'SIM +',
    ];

    public $lang = 'en';
    public $pdfNamePattern = ".*pdf";

    public static $dictionary = [
        "en" => [
            'SIM Card Validity:'          => ['SIM Card Validity:', 'SIM card validity:'],
            'See voucher'                 => ['See voucher', 'Voucher anzeigen'],
            'Unit:'                       => ['Unit:', 'Artikel:'],
            'Or view your booking online' => ['Or view your booking online', 'See full booking details'],
        ],
    ];
    private $detectFrom = "booking@klook.com";

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        if ($this->detectEmailByBody($parser) === true) {
            $email->setIsJunk(true);
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class));

        return $email;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->detectFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], $this->detectFrom) !== false) {
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
            // for PDF-format see parser klook/BookingConfirmation
            return false;
        }

        if ($this->http->XPath->query("//text()[{$this->contains($this->t('Klook Travel Technology Limited'))}]")->length > 0) {
            return ($this->http->XPath->query("//text()[{$this->contains($this->t('See voucher'))}]")->length > 0
                || $this->http->XPath->query("//text()[{$this->contains($this->t('Or view your booking online'))}]")->length > 0)
                && $this->http->XPath->query("//text()[{$this->starts($this->t('Unit:'))} and {$this->contains($this->t('SIM'))}]")->length > 0
                && $this->http->XPath->query("//text()[{$this->contains($this->t('SIM Card Validity:'))}]")->length > 0;
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
