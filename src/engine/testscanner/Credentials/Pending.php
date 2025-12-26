<?php

namespace AwardWallet\Engine\testscanner\Credentials;

class Pending extends \TAccountChecker
{
    public const LP_FROM = "awtestscanner-lp@fake.com";
    public const STATEMENT_FROM = "awtestscanner-stat@fake.com";

    protected $headerPlaceholders = [
        "from", "to", "subject", "messageId", "date",
    ];

    public function getCredentialsImapFrom()
    {
        return [self::LP_FROM];
    }

    public function getCredentialsSubject()
    {
        return ["Pending Credentials:"];
    }

    public function getParsedFields()
    {
        return ["Login", "Name", "Pending"];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        return $this->ParseGeneral();
    }

    protected function ParseGeneral($root = null)
    {
        $result = [];

        if (!isset($root)) {
            $nodes = $this->http->XPath->query("//table[@class='parse-table']");

            if ($nodes->length > 0) {
                $root = $nodes->item(0);
            }
        }
        $nodes = $this->http->XPath->query("tbody/tr", $root);

        foreach ($nodes as $node) {
            $class = $this->http->FindSingleNode("td[2]/@class", $node);
            $type = $this->http->FindSingleNode("td[2]/@data-type", $node);

            if ($class && (!isset($type) || $type == 'scalar')) {
                $result[ucfirst($class)] = $this->http->FindSingleNode("td[2]", $node);
            }
        }

        return $result;
    }
}
