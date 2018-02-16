<?php

namespace Interactionez\WMIS\Tests;

use Interactionez\WMIS\Service;
use PHPUnit\Framework\TestCase;

class CreateInstanceTest extends TestCase {
    public function testNoOptions() {
        $service = new Service([]);
        $service->start();
    }
}
