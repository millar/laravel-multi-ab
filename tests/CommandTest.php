<?php
require_once 'TestCase.php';

use Millar\AB\Tester;
use Millar\AB\Models\Experiment;
use Millar\AB\Models\Variant;
use Millar\AB\Models\Goal;
use Millar\AB\Commands\InstallCommand;

class CommandTest extends TestCase {

    public function testInstall()
    {
        Artisan::call('ab:install');

        $this->assertTrue(Schema::hasTable('experiments'));
        $this->assertTrue(Schema::hasTable('variants'));
        $this->assertTrue(Schema::hasTable('goals'));
    }

    public function testFlush()
    {
        Artisan::call('ab:install');

        Variant::where('experiment', 'logo')->where('variant', 'a')->update(['visitors' => 153, 'engagement' => 35]);

        Artisan::call('ab:flush');

        $variant = Variant::where('experiment', 'logo')->where('name', 'a')->first();

        $this->assertEquals(0, $variant->visitors);
        $this->assertEquals(0, $variant->engagement);
    }

    public function testReport()
    {
        Artisan::call('ab:install');

        Variant::where('experiment', 'logo')->where('name', 'a')->update(['visitors' => 153, 'engagement' => 35]);
        Goal::create(['name'=>'foo', 'experiment'=>'logo', 'variant'=>'a', 'count'=>42]);

        $output = new Symfony\Component\Console\Output\BufferedOutput;
        Artisan::call('ab:report', [], $output);
        $report = $output->fetch();

        $this->assertContains('Foo', $report);
        $this->assertContains('153', $report);
        $this->assertContains('35', $report);
        $this->assertContains('42', $report);
    }

    public function testExport()
    {
        Artisan::call('ab:install');

        Variant::where('experiment', 'logo')->where('name', 'a')->update(['visitors' => 153, 'engagement' => 35]);
        Goal::create(['name'=>'foo', 'experiment'=>'logo', 'variant'=>'a', 'count'=>42]);

        $output = new Symfony\Component\Console\Output\BufferedOutput;
        Artisan::call('ab:export', [], $output);
        $report = $output->fetch();

        $this->assertContains('Foo', $report);
        $this->assertContains('153', $report);
        $this->assertContains('35', $report);
        $this->assertContains('42', $report);

        $output = new Symfony\Component\Console\Output\BufferedOutput;
        Artisan::call('ab:export', ['file' => '/tmp/test.csv'], $output);
        $report = $output->fetch();

        $this->assertContains('Creating /tmp/test.csv', $report);
    }

}
