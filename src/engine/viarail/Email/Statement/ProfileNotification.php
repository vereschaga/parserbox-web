<?php

namespace AwardWallet\Engine\viarail\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;

class ProfileNotification extends \TAccountChecker
{
    public $mailFiles = "viarail/statements/it-65612575.eml, viarail/statements/it-65313153.eml";

    private $subjects = [
        'en' => ['Confirm your profile activation', 'account personal information modification notification'],
        'fr' => ['Notification de modification à votre compte VIA Rail'],
    ];

    private $detectors = [
        'en' => [
            'This is to notify you that your profile is currently pending activation.',
            'To activate your profile, please click on here',
            'log into your Reservia account',
        ],
        'fr' => [
            'vous avez modifié vos informations personnelles.',
        ],
    ];

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@viarail.ca') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (self::detectEmailFromProvider($headers['from']) !== true
            && strpos($headers['subject'], 'Via Rail') === false
        ) {
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
        if (self::detectEmailFromProvider($parser->getHeader('from')) !== true
            && $this->http->XPath->query('//a[contains(@href,".viarail.ca/") or contains(@href,"reservia.viarail.ca")]')->length === 0
            && $this->http->XPath->query('//img[contains(@src,".viarail.ca/images/confirmation/vialog.")]')->length === 0
            && $this->http->XPath->query('//node()[contains(.,"reservia.viarail.ca")]')->length === 0
        ) {
            return false;
        }

        return $this->detectBody();
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $st = $email->add()->statement();

        $name = $this->http->FindSingleNode("//text()[{$this->starts(['Hello', 'Dear', 'Cher'])}]", null, true,
            "/{$this->opt(['Hello', 'Dear', 'Cher'])}\s+([[:alpha:]][-.\'[:alpha:] ]*[[:alpha:]])(?:\s*[,;:!?]|$)/u");

        if (stripos($name, 'Customer') !== false || stripos($name, 'client') !== false) {
            // Dear Customer,
            // Cher client,
            $name = null;
        }

        if ($name) {
            $st->addProperty('Name', $name);
        }

        $isMembership = $this->detectBody();

        if ($isMembership) {
            $st->setMembership(true);
        }

        if ($name && $isMembership) {
            $st->setNoBalance(true);
        }

        return $email;
    }

    public static function getEmailTypesCount()
    {
        return 0;
    }

    private function detectBody(): bool
    {
        if (!isset($this->detectors)) {
            return false;
        }

        foreach ($this->detectors as $phrases) {
            foreach ((array) $phrases as $phrase) {
                if (!is_string($phrase)) {
                    continue;
                }

                if ($this->http->XPath->query("//node()[{$this->contains($phrase)}]")->length > 0) {
                    return true;
                }
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
            return 'contains(normalize-space(' . $node . '),"' . $s . '")';
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
}
