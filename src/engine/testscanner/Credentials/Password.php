<?php

namespace AwardWallet\Engine\testscanner\Credentials;

class Password extends \TAccountChecker
{
    public const PASSWORD_FROM = "awtestscanner-pass@fake.com";
    public const LP_FROM = "awtestscanner-lp@fake.com";

    protected $headerPlaceholders = [
        "from", "to", "subject", "messageId", "date",
    ];

    public function getRetrieveFields()
    {
        return ["MailLogin", "MailPass", "MailServer", "PasswordKey"];
    }

    public function RetrieveCredentials($data)
    {
        return $this->appendMessage($data["MailServer"], $data["MailLogin"], $data["MailPass"], $this->getRetrievedPasswordEmail($data["MailLogin"], $data["PasswordKey"]));
    }

    public function getCredentialsImapFrom()
    {
        return [self::PASSWORD_FROM];
    }

    public function getCredentialsSubject()
    {
        return ["Retrieve password email"];
    }

    public function getParsedFields()
    {
        return ["Password"];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        return $this->ParseGeneral();
    }

    public function makeEmail($type, $headerValues, $parseValues)
    {
        $values = [];

        if (!isset($headerValues["messageId"])) {
            $headerValues["messageId"] = implode("", array_rand(range(0, 50), 10)) . "." . rand(10000, 99999) . ".mail.fake.com";
        }

        if (isset($headerValues["date"]) && is_numeric($headerValues["date"])) {
            $headerValues["date"] = date(DATE_RFC822, $headerValues["date"]);
        }
        $placeholders = [];

        foreach ($this->headerPlaceholders as $key) {
            if (!isset($headerValues[$key])) {
                throw new \Exception('invalid header values');
            }
            $placeholders[] = "{{" . $key . "}}";
            $values[] = $headerValues[$key];
        }
        $headers = [
            "To: {{to}}",
            "From: {{from}}",
            "Date: {{date}}",
            "Message-ID: {{messageId}}",
            "Subject: {{subject}}",
            "Content-Type: text/html",
        ];
        $header = str_replace($placeholders, $values, implode("\r\n", $headers));
        $body = "<table class=\"parse-table\" data-type=\"{$type}\"><tbody>";

        if ($type == 'it') {
            foreach ($parseValues as $it) {
                $body .= "<tr><td><table class=\"itinerary\"><tbody>";

                foreach ($it as $key => $val) {
                    $body .= sprintf("<tr>%s</tr>", $this->cellElement($key, $val));
                }
                $body .= "</tbody></table></td></tr>";
            }
        } else {
            foreach ($parseValues as $key => $val) {
                $body .= sprintf("<tr><td>%s</td><td class=\"%s\">%s</td></tr>", $key, $key, $val);
            }
        }
        $body .= "</tbody></table>";

        return $header . "\r\n\r\n" . $body;
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

        if (!empty($result["IsMember"])) {
            $result["IsMember"] = true;
        } else {
            unset($result["IsMember"]);
        }

        return $result;
    }

    protected function appendMessage($server, $login, $password, $email)
    {
        $imap = imap_open($server, $login, $password);

        if ($imap) {
            $this->http->Log("testscanner: connected to " . $login);
            imap_append($imap, $server . "Inbox", $email);
            imap_close($imap);

            return true;
        } else {
            $this->http->Log("testscanner: didn't connect to " . $login);

            return false;
        }
    }

    protected function getRetrievedPasswordEmail($to, $key)
    {
        $headers = [
            "to"      => $to,
            "from"    => self::PASSWORD_FROM,
            "date"    => date(DATE_RFC822),
            "subject" => "Retrieve password email for $key",
        ];
        $data = ["password" => "RetrievedPassword$key"];

        return $this->makeEmail("cr", $headers, $data);
    }

    protected function cellElement($key, $val)
    {
        if (!is_array($val)) {
            $type = "scalar";
            $cell = $val;
        } else {
            if (isset($val[0])) {
                $type = "num";
            } else {
                $type = "assoc";
            }
            $cell = "<table><tbody>";

            foreach ($val as $i => $v) {
                $cell .= "<tr>" . $this->cellElement($i, $v) . "</tr>";
            }
            $cell .= "</tbody></table>";
        }

        return sprintf("<td>%s</td><td class=\"%s\" data-type=\"%s\">%s</td>", $key, $key, $type, $cell);
    }
}
