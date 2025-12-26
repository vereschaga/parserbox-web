<?php

namespace AwardWallet\Engine\starbucks\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class StarbucksCard extends \TAccountChecker
{
    public $mailFiles = "starbucks/statements/it-65912999.eml, starbucks/statements/it-65839550.eml";
    public $subjects = [
        'en' => '/^Thank You! Starbucks Card Reload Order [A-Z\d]+$/',
        'es' => '/^Contanos cómo estuvo tu última visita a Starbucks$/u',
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (stripos($headers['from'], '@starbucks.com') !== false
            || stripos($headers['from'], 'starbucks@express.medallia.com') !== false
        ) {
            foreach ($this->subjects as $subject) {
                if (preg_match($subject, $headers['subject'])) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if ($this->detectEmailFromProvider($parser->getHeader('from')) !== true
            && $this->http->XPath->query('//a[contains(@href,".starbucks.com/") or contains(@href,"www.starbucks.com") or contains(@href,"//starbucksrewards.com")]')->length === 0
            && $this->http->XPath->query('//*[contains(normalize-space(),"Starbucks Coffee Company. All rights reserved") or contains(.,"@starbucks.com")]')->length === 0
        ) {
            return false;
        }

        return $this->isMembership();
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/(?:[@.]starbucks\.com|starbucks[@.]express\.medallia\.com)$/i', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $st = $email->add()->statement();

        if ($this->isMembership()) {
            $st->setMembership(true);
        }

        return $email;
    }

    public static function getEmailTypesCount()
    {
        return 0;
    }

    private function isMembership(): bool
    {
        $phrases = [
            'Thank you for reloading your Starbucks Card',
            '¡Obtendrías un bonus de 5 Stars por tu respuesta',
        ];

        return $this->http->XPath->query("//*[{$this->contains($phrases)}]")->count() > 0;
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
}
