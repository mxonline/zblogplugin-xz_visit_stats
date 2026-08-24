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

    public function testPluginSupportFilesExist(): void
    {
        $root = dirname(__DIR__);

        $this->assertFileExists($root . '/inc/helpers.php');
        $this->assertFileExists($root . '/inc/bot.php');
    }

    public function testPluginXmlContainsIdentity(): void
    {
        $xml = file_get_contents(dirname(__DIR__) . '/plugin.xml');

        $this->assertIsString($xml);
        $this->assertStringContainsString('xz_visit_stats', $xml);
        $this->assertStringContainsString('访问统计', $xml);
    }
}
