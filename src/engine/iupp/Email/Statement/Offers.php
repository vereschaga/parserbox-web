<?php

namespace AwardWallet\Engine\iupp\Email\Statement;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;

class Offers extends \TAccountChecker
{
    public $mailFiles = "iupp/statements/it-152988843.eml";

    private $subjects = [
        'pt' => [', chegaram ofertas especiais'],
    ];

    private $enDatesInverted = true;

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@comunicado.iupp.com.br') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->detectEmailFromProvider($headers['from']) !== true) {
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
            && $this->http->XPath->query('//*[contains(.,"iupp.com.br") or contains(normalize-space(),"Escreva para o iupp")]')->length === 0
        ) {
            return false;
        }

        return $this->findRoot()->length === 1;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $roots = $this->findRoot();

        if ($roots->length !== 1) {
            return $email;
        }
        $root = $roots->item(0);

        $patterns['travellerName'] = '[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]]';

        $st = $email->add()->statement();

        $name = $balanceDate = $balance = null;

        $userNames = array_filter($this->http->FindNodes("//text()[starts-with(normalize-space(),'Olá')]/following::text()[normalize-space()][1]", null, "/^[,\s]*({$patterns['travellerName']})(?:\s*[,;:!?]|$)/u"));

        if (count(array_unique($userNames)) === 1) {
            $name = array_shift($userNames);
        }

        $balanceDateVal = $this->normalizeDate($this->http->FindSingleNode(".", $root, true, "/^Seu saldo iupp em\s*(.*\d.*?)[ ]*[:]+$/i"));

        if (preg_match("/^\d{1,2}\/\d{1,2}$/", $balanceDateVal)) {
            $relativeDate = strtotime($this->normalizeDate($this->http->FindSingleNode("//text()[contains(.,'Ofertas válidas até 15/04/22')]", $root, true, "/Ofertas válidas até\s*(\d{1,2}\/\d{1,2}\/\d{2,4})/i")));

            if ($relativeDate) {
                $balanceDate = EmailDateHelper::parseDateRelative($balanceDateVal, $relativeDate, false, '%D%/%Y%');
            }
        } elseif ($balanceDateVal) {
            $balanceDate = strtotime($balanceDateVal);
        }

        $balanceVal = $this->http->FindSingleNode("following::text()[normalize-space()][1]", $root);

        if (preg_match('/^(?<amount>\d[,.\'\d ]*?)[ ]*PTS$/i', $balanceVal, $matches)) {
            // 14470 pts
            $balance = PriceHelper::parse($matches['amount']);
        }

        $st
        ->addProperty('Name', $name)
            ->setBalanceDate($balanceDate)
            ->setBalance($balance)
        ;

        return $email;
    }

    public static function getEmailLanguages()
    {
        return ['pt'];
    }

    public static function getEmailTypesCount()
    {
        return 0;
    }

    private function findRoot(): \DOMNodeList
    {
        return $this->http->XPath->query("//text()[starts-with(normalize-space(),'Seu saldo iupp em') and contains(.,':')]");
    }

    /**
     * @param string|null $text Unformatted string with date
     */
    private function normalizeDate(?string $text): string
    {
        if (!is_string($text) || empty($text)) {
            return '';
        }
        $in = [
            // 15/04/22
            '/^(\d{1,2})\s*\/\s*(\d{1,2})\s*\/\s*(\d{2,4})$/',
            // 15/04
            '/^(\d{1,2})\s*\/\s*(\d{1,2})$/',
        ];
        $out[0] = $this->enDatesInverted ? '$2/$1/$3' : '$1/$2/$3';
        $out[1] = $this->enDatesInverted ? '$2/$1' : '$1/$2';

        return preg_replace($in, $out, $text);
    }
}
