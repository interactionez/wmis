<?php

namespace Interactionez\WMIS\Authentication;

use Psr\Http\Message\RequestInterface;

interface AuthenticationProviderInterface
{
    public function authenticate();

    public function handleRequest(&$headers);
}