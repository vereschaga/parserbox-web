<?php

namespace AwardWallet\Common\Parsing\Web\Proxy;

class Proxy
{

    public string $host;
    public string $port;
    public ?string $username;
    public ?string $password;

    public function __construct(string $host, string $port, ?string $username = null, ?string $password = null)
    {
        $this->host = $host;
        $this->port = $port;
        $this->username = $username;
        $this->password = $password;
    }

}