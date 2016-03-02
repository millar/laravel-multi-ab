<?php
require_once 'TestCase.php';

use Millar\AB\Tester;
use Millar\AB\Models\Experiment;
use Millar\AB\Models\Variant;
use Millar\AB\Models\Goal;
use Millar\AB\Commands\InstallCommand;

class GeneralTest extends TestCase {

    public function setUp()
    {
        parent::setUp();

        // Run the install command.
        Artisan::call('ab:install');
    }

    public function tearDown()
    {
        try
        {
            Experiment::truncate();
            Goal::truncate();
        }
        catch (Exception $e) {}
    }

    public function testConstruct()
    {
        $ab = App::make('ab');

        $this->assertInstanceOf('Millar\AB\Tester', $ab);
        $this->assertInstanceOf('Millar\AB\Session\SessionInterface', $ab->getSession());
    }

    public function testTracking()
    {
        Route::enableFilters();
        $request = Request::instance();

        $ab = Mockery::mock('Millar\AB\Tester');
        $ab->shouldReceive('track')->with($request)->once();

        $this->app['ab'] = $ab;
        $this->app->events->fire('router.before', [$request]);
    }

    public function testAutoCreateExperiments()
    {
        DB::table('experiments')->delete();

        $ab = App::make('ab');
        $ab->variant('logo');

        $this->assertEquals(6, Variant::count());
    }

    public function testNewVariant()
    {
        $ab = App::make('ab');
        $variant = $ab->variant('logo');

        $this->assertEquals('a', $variant);
        $this->assertEquals($variant, $ab->getSession()->getExperiment('logo', 'variant'));
        $this->assertEquals(1, Variant::where('experiment', 'logo')->where('name', 'a')->first()->visitors);
    }

    public function testExistingVariant()
    {
        $session = Mockery::mock('Millar\AB\Session\SessionInterface');
        $session->shouldReceive('getExperiment')->with('font', 'variant')->andReturn('b');

        $ab = new Tester($session);
        $variant = $ab->variant('font');

        $this->assertEquals('b', $variant);
        $this->assertEquals($variant, $ab->getSession()->getExperiment('font', 'variant'));
    }

    public function testVariantCompare()
    {
        $ab = App::make('ab');
        $variant = $ab->variant('logo');

        $this->assertEquals('a', $variant);
        $this->assertTrue($ab->variant('logo', 'a'));
        $this->assertFalse($ab->variant('logo', 'b'));
    }

    public function testPageview()
    {
        $session = Mockery::mock('Millar\AB\Session\SessionInterface');
        $session->shouldReceive('getExperiment')->with('logo', 'variant')->andReturn('a');
        $session->shouldReceive('getExperiment')->with('logo', 'pageview')->andReturn(null)->once();
        $session->shouldReceive('setExperiment')->with('logo', 'pageview', 1)->once();
        $session->shouldReceive('get')->with('variant', [])->andReturn(['logo' => 'a'])->once();

        $ab = new Tester($session);
        $ab->pageview();

        $this->assertEquals(1, Variant::where('experiment', 'logo')->where('name', 'a')->first()->visitors);
    }

    public function testInteract()
    {
        $session = Mockery::mock('Millar\AB\Session\SessionInterface');
        $session->shouldReceive('getExperiment')->with('logo', 'variant')->andReturn('a');
        $session->shouldReceive('getExperiment')->with('logo', 'interacted')->andReturn(null)->once();
        $session->shouldReceive('setExperiment')->with('logo', 'interacted', 1)->once();
        $session->shouldReceive('get')->with('variant', [])->andReturn(['logo' => 'a'])->once();

        $ab = new Tester($session);
        $ab->interact();

        $this->assertEquals(1, Variant::where('experiment', 'logo')->where('name', 'a')->first()->engagement);
    }

    public function testComplete()
    {
        $session = Mockery::mock('Millar\AB\Session\SessionInterface');
        $session->shouldReceive('getExperiment')->with('logo', 'variant')->andReturn('a');
        $session->shouldReceive('getExperiment')->with('logo', 'completed_register')->andReturn(null)->once();
        $session->shouldReceive('setExperiment')->with('logo', 'completed_register', 1)->once();
        $session->shouldReceive('get')->with('variant', [])->andReturn(['logo' => 'a'])->once();

        $ab = new Tester($session);
        $ab->complete('register');

        $this->assertEquals(1, Goal::where('name', 'register')->where('experiment', 'logo')->where('variant', 'a')->first()->count);
    }

    public function testTrackWithoutExperiment()
    {
        $request = Request::instance();

        $ab = App::make('ab');
        $ab->track($request);

        $this->assertEquals(0, Variant::find('a')->visitors);
        $this->assertEquals(0, Variant::find('a')->engagement);
    }

    public function testTrackWithExperiment()
    {
        $request = Request::instance();

        $ab = App::make('ab');
        $ab->variant('logo');
        $ab->track($request);

        $this->assertEquals(1, Variant::where('experiment', 'logo')->where('name', 'a')->first()->visitors);
        $this->assertEquals(0, Variant::where('experiment', 'logo')->where('name', 'a')->first()->engagement);
    }

    public function testTrackEngagement()
    {
        $headers = Request::instance()->server->getHeaders();
        $headers['HTTP_REFERER'] = 'http://localhost';
        $request = Request::create('http://localhost/info', 'get', [], [], [], $headers);

        $ab = App::make('ab');
        $ab->variant('font');
        $ab->track($request);

        $this->assertEquals(1, Variant::where('experiment', 'font')->where('name', 'a')->first()->visitors);
        $this->assertEquals(1, Variant::where('experiment', 'font')->where('name', 'a')->first()->engagement);
    }

    public function testTrackGoal()
    {
        $headers = Request::instance()->server->getHeaders();
        $headers['HTTP_REFERER'] = 'http://localhost';
        $request = Request::create('http://localhost/buy', 'get', [], [], [], $headers);

        $ab = App::make('ab');
        $ab->variant('font');
        $ab->track($request);

        $this->assertEquals(1, Variant::where('experiment', 'font')->where('name', 'a')->first()->visitors);
        $this->assertEquals(1, Variant::where('experiment', 'font')->where('name', 'a')->first()->engagement);
        $this->assertEquals(1, Goal::where('name', 'buy')->where('experiment', 'font')->where('variant', 'a')->first()->count);
    }

    public function testTrackRouteGoal()
    {
        // Register fake named route
        Route::any('/foobar', ['as' => 'buy', function()
        {
            return 'hello world';
        }]);

        $headers = Request::instance()->server->getHeaders();
        $headers['HTTP_REFERER'] = 'http://localhost';
        $request = Request::create('http://localhost/foobar', 'get', [], [], [], $headers);
        Route::dispatch($request);

        $ab = App::make('ab');
        $ab->variant('font');
        $ab->track($request);

        $this->assertEquals(1, Variant::where('experiment', 'font')->where('name', 'a')->first()->visitors);
        $this->assertEquals(1, Variant::where('experiment', 'font')->where('name', 'a')->first()->engagement);
        $this->assertEquals(1, Goal::where('name', 'buy')->where('experiment', 'font')->where('variant', 'a')->first()->count);
    }

    public function testSetSession()
    {
        $session = Mockery::mock('Millar\AB\Session\SessionInterface');

        $ab = App::make('ab');
        $ab->setSession($session);

        $this->assertEquals($session, $ab->getSession());
    }

    public function testGoalWithoutReferer()
    {
        // Register fake named route
        Route::any('/foobar', ['as' => 'buy', function()
        {
            return 'hello world';
        }]);

        $headers = Request::instance()->server->getHeaders();
        $request = Request::create('http://localhost/foobar', 'get', [], [], [], $headers);
        Route::dispatch($request);

        $ab = App::make('ab');
        $ab->variant('font');
        $ab->track($request);

        $this->assertEquals(1, Variant::where('experiment', 'font')->where('name', 'a')->first()->visitors);
        $this->assertEquals(1, Variant::where('experiment', 'font')->where('name', 'a')->first()->engagement);
        $this->assertEquals(1, Goal::where('name', 'buy')->where('experiment', 'font')->where('variant', 'a')->first()->count);
    }

    public function testFirstPageView()
    {
        // Register fake named route
        Route::any('/foobar', function()
        {
            return 'hello world';
        });

        $headers = Request::instance()->server->getHeaders();
        $request = Request::create('http://localhost/foobar', 'get', [], [], [], $headers);
        Route::dispatch($request);

        $ab = App::make('ab');
        $ab->track($request);
        $ab->variant('font');

        $this->assertEquals(1, Variant::where('experiment', 'font')->where('name', 'a')->first()->visitors);
        $this->assertEquals(0, Variant::where('experiment', 'font')->where('name', 'a')->first()->engagement);
    }

}
