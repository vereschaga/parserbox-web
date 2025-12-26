<?php

namespace AwardWallet\Engine\hallmark\Email;

use AwardWallet\Schema\Parser\Email\Email;

class YourOrder extends \TAccountChecker
{
    public $mailFiles = "hallmark/it-91776702.eml, hallmark/it-91370472.eml";

    private $subjects = [', your order has shipped.'];

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@hallmarkonline.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (stripos($headers['subject'], 'Your Hallmark Shipping Confirmation -') !== false
            || stripos($headers['subject'], 'Thank you for your Hallmark order #') !== false
        ) {
            return true;
        }

        if ($this->detectEmailFromProvider($headers['from']) !== true && strpos($headers['subject'], 'Hallmark') === false) {
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
        if ($this->detectEmailFromProvider($parser->getHeader('from')) !== true
            && $this->http->XPath->query('//a[contains(@href,".hallmark.com/") or contains(@href,"www.hallmark.com") or contains(@href,"explore.hallmark.com")]')->length === 0
            && $this->http->XPath->query('//*[contains(normalize-space(),"orders@hallmarkonline.com")]')->length === 0
        ) {
            return false;
        }
        $phrases = [
            'Your order will be arriving soon.',
            'Your order information is below',
            'Your order has shipped, and you will be able to pick it up soon',
            'your Hallmark order will be arriving soon.',
        ];

        return $this->http->XPath->query("//*[{$this->contains($phrases)}]")->length > 0;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $patterns['travellerName'] = '[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]]';

        $name = null;
        $nameNodes = array_filter($this->http->FindNodes("//text()[starts-with(normalize-space(),'Hi')]", null, "/^Hi\s+({$patterns['travellerName']})(?:\s*[,;:!?]|$)/u"));

        if (count(array_unique($nameNodes)) === 1) {
            $name = array_shift($nameNodes);
        } else {
            $name = $this->http->FindSingleNode("//text()[contains(normalize-space(),', thank you for shopping with us')]", null, true, "/^({$patterns['travellerName']}), thank you for shopping with us/u");
        }

        if ($name
            && $this->http->XPath->query("//text()[starts-with(normalize-space(),'Order number:') or starts-with(normalize-space(),'Order Number:') or starts-with(normalize-space(),'Order number :') or starts-with(normalize-space(),'Order Number :')]")->length > 0
        ) {
            $email->setIsJunk(true);
        }

        return $email;
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
