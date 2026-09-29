<?php

use StringKe\LakePhp\Laravel\Tests\IntegrationTestCase;
use StringKe\LakePhp\Laravel\Tests\TestCase;

pest()->extend(TestCase::class)->in('Unit');
pest()->extend(IntegrationTestCase::class)->in('Integration');
