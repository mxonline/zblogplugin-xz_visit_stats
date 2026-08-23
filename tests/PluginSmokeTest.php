<?php

use PHPUnit\Framework\TestCase;

class PluginSmokeTest extends TestCase
{
    public function testPluginFilesExist(): void
    {
        $root = dirname(__DIR__);

        $this->assertFileExists($root . '/plugin.xml');
        $this->assertFileExists($root . '/include.php');
        $this->assertFileExists($root . '/main.php');
    }

    public function testBotDetectorFileExists(): void
    {
        $this->assertFileExists(dirname(__DIR__) . '/inc/bot.php');
    }
}
