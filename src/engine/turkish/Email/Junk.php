<?php
namespace AwardWallet\Engine\turkish\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class Junk extends \TAccountChecker
{
    public $mailFiles = "turkish/it-148759692.eml, turkish/it-152495585.eml, turkish/it-154934313.eml";

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        if ($this->detectJunk($parser)) {
            $email->setIsJunk(true);
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class));

        return $email;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if (
            $this->http->XPath->query("//a[contains(@href,'turkishairlines.com')]")->length > 0
            || stripos($parser->getCleanFrom(), '.turkishairlines.com')
            || stripos($parser->getCleanFrom(), '@thy.com')
        ) {
            return $this->detectJunk($parser);
        }

        return false;
    }

    public function detectJunk(PlancakeEmailParser $parser)
    {
        // format 1
        if (strpos($parser->getSubject(), 'Turkish Airlines - Online Ticket - Information Message') !== false
            && $this->http->XPath->query("//*[starts-with(normalize-space(), 'Dear Guest, As Turkish Airlines, we are happy to see you. Please click to display your online boarding pass. We wish you a good flight.')][count(following::text()[normalize-space()]) < 10]")->length > 0
        ) {
            return true;
        }
        // format 2.en
        if (strpos($parser->getSubject(), 'Turkish Airlines - Online Ticket - Information Message') !== false
            && $this->http->XPath->query("//text()[contains(normalize-space(), 'You can continue your transaction with the verification code')][count(following::text()[normalize-space()]) < 10]")->length > 0
            && $this->http->XPath->query("*[count(following::text()[normalize-space()]) < 10]")->length > 0
        ) {
            return true;
        }
        // format 2.tr
        if (strpos($parser->getSubject(), 'İade/iletişim bilgisi güncelleme onay kodu') !== false
            && $this->http->XPath->query("//text()[contains(normalize-space(), 'doğrulama kodunuzla işleminize devam edebilirsiniz.')][count(following::text()[normalize-space()]) < 10]")->length > 0
            && $this->http->XPath->query("*[count(following::text()[normalize-space()]) < 10]")->length > 0
        ) {
            return true;
        }
        // format 2.pt
        if (strpos($parser->getSubject(), 'Turkish Airlines - Bilhete online - mensagem informativa') !== false
            && $this->http->XPath->query("//text()[contains(normalize-space(), 'Pode continuar a sua transação com o código de verificação')][count(following::text()[normalize-space()]) < 10]")->length > 0
            && $this->http->XPath->query("*[count(following::text()[normalize-space()]) < 10]")->length > 0
        ) {
            return true;
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@thy.com') !== false
            || stripos($from, '@mail.turkishairlines.com') !== false;
    }

    public static function getEmailTypesCount()
    {
        return 0;
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

    private function contains($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
                return 'contains(normalize-space(' . $node . '),"' . $s . '")';
            }, $field)) . ')';
    }

    private function eq($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
                return 'normalize-space(' . $node . ')="' . $s . '"';
            }, $field)) . ')';
    }

    private function starts($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
                return 'starts-with(normalize-space(' . $node . '),"' . $s . '")';
            }, $field)) . ')';
    }
}
