<?php

namespace Pckg\Parser\Driver;

use Pckg\Parser\Source\HeadlessHelper;

abstract class Headless extends AbstractDriver implements DriverInterface, HeadlessInterface
{
    use HeadlessHelper;
}
