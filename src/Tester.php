<?php namespace Millar\AB;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;

use Millar\AB\Session\SessionInterface;
use Millar\AB\Models\Experiment;
use Millar\AB\Models\Variant;
use Millar\AB\Models\Goal;

class Tester {

    /**
     * The Session instance.
     *
     * @var SessionInterface
     */
    protected $session;

    /**
     * Constructor.
     *
     * @param SessionInterface $session
     */
    public function __construct(SessionInterface $session)
    {
        $this->session = $session;
    }

    /**
     * Track clicked links and form submissions.
     *
     * @param  Request $request
     * @return void
     */
    public function track(Request $request)
    {
        // Don't track if there is no active experiment.
        if ( ! $this->session->get('variant')) return;

        // Since there is an ongoing experiment, increase the pageviews.
        // This will only be incremented once during the whole experiment.
        $this->pageview();

        // Check current and previous urls.
        $root = $request->root();
        $from = ltrim(str_replace($root, '', $request->headers->get('referer')), '/');
        $to = ltrim(str_replace($root, '', $request->getPathInfo()), '/');

        // Don't track refreshes.
        if ($from == $to) return;

        // Because the visitor is viewing a new page, trigger engagement.
        // This will only be incremented once during the whole experiment.
        $this->interact();

        $goals = $this->getGoals();

        // Detect goal completion based on the current url.
        if (in_array($to, $goals) or in_array('/' . $to, $goals))
        {
            $this->complete($to);
        }

        // Detect goal completion based on the current route name.
        if ($route = Route::currentRouteName() and in_array($route, $goals))
        {
            $this->complete($route);
        }
    }

    /**
     * Get or compare the current experiment variant for this session.
     *
     * @param  string  $experiment
     * @param  string  $target
     * @return bool|string
     */
    public function variant($experiment, $target = null)
    {
        $variant = $this->session->getExperiment($experiment, 'variant') ?: $this->nextVariant($experiment);

        if (is_null($target))
        {
            return $variant;
        }

        return $variant == $target;
    }

    /**
     * Increment the pageviews for the current experiment.
     *
     * @return void
     */
    public function pageview()
    {
        foreach ($this->session->get('variant', []) as $experiment => $variant){
            // Only interact once per experiment.
            if ($this->session->getExperiment($experiment, 'pageview')) return;

            $variant = Variant::firstOrNew(['experiment' => $experiment, 'name' => $this->variant($experiment), 'experiment_variant' => "$experiment.".$this->variant($experiment)]);
            $variant->visitors++;
            $variant->save();

            // Mark current experiment as interacted.
            $this->session->setExperiment($experiment, 'pageview', 1);
        }
    }

    /**
     * Increment the engagement for the current experiment.
     *
     * @return void
     */
    public function interact()
    {
        foreach ($this->session->get('variant', []) as $experiment => $variant){
            // Only interact once per experiment.
            if ($this->session->getExperiment($experiment, 'interacted')) return;

            $variant = Variant::firstOrNew(['experiment' => $experiment, 'name' => $this->variant($experiment), 'experiment_variant' => "$experiment.".$this->variant($experiment)]);
            $variant->engagement++;
            $variant->save();

            // Mark current experiment as interacted.
            $this->session->setExperiment($experiment, 'interacted', 1);
        }
    }

    /**
     * Mark a goal as completed for the current variants.
     *
     * @return void
     */
    public function complete($name, $experiment = null, $variant = null)
    {
        $experiments = ($experiment && $variant) ? [$experiment => $variant] : $this->session->get('variant', []);

        foreach ($experiments as $experiment => $v){
            // Only complete once per experiment.
            if ( ! Config::get('multi-ab::complete_multiple', false) && $this->session->getExperiment($experiment, "completed_$name")) return;

            $variant = $variant ?: $v;

            $goal = Goal::firstOrCreate(['name' => $name, 'experiment' => $experiment, 'variant' => $variant]);
            Goal::where('name', $name)->where('experiment', $experiment)->where('variant', $variant)->update(['count' => ($goal->count + 1)]);

            // Mark current experiment as completed.
            $this->session->setExperiment($experiment, "completed_$name", 1);
        }
    }

    /**
     * Set the current experiment variant for this session manually.
     *
     * @param string $experiment
     * @param string $variant
     */
    public function setVariant($experiment, $variant)
    {
        if ($this->session->getExperiment($experiment, 'variant') != $variant)
        {
            $this->session->setExperiment($experiment, 'variant', $variant);

            // Increase pageviews for new variant.
            $this->nextVariant($experiment, $variant);
        }
    }

    /**
     * Get all experiments.
     *
     * @return array
     */
    public function getExperiments()
    {
        return Config::get('multi-ab::experiments', []);
    }

    /**
     * Get all goals.
     *
     * @return array
     */
    public function getGoals()
    {
        return Config::get('multi-ab::goals', []);
    }

    /**
     * Get the session instance.
     *
     * @return SessionInterface
     */
    public function getSession()
    {
        return $this->session;
    }

    /**
     * Set the session instance.
     *
     * @param $session SessionInterface
     */
    public function setSession(SessionInterface $session)
    {
        $this->session = $session;
    }

    /**
     * Prepare an variant for this session and selected experiment.
     *
     * @return string
     */
    protected function nextVariant($experiment, $variant = null)
    {
        // Verify that the experiments are in the database.
        $this->checkVariants();

        if ($variant)
        {
            $variant = Variant::active()->where('experiment', $experiment)->where('name', $variant)->firstOrFail();
        }
        else
        {
            $variant = Variant::active()->where('experiment', $experiment)->orderBy('visitors', 'asc')->firstOrFail();
        }

        $this->session->setExperiment($experiment, 'variant', $variant->name);

        // Since there is an ongoing experiment, increase the pageviews.
        // This will only be incremented once during the whole experiment.
        $this->pageview();

        return $variant->name;
    }

    /**
     * Add variants to the database.
     *
     * @return void
     */
    protected function checkVariants()
    {
        // Check if the database contains all variants.
        if (Variant::active()->count() != count($this->getExperiments(), COUNT_RECURSIVE) - count($this->getExperiments()))
        {
            // Insert all experiments.
            foreach ($this->getExperiments() as $experiment => $variants)
            {
                Experiment::firstOrCreate(['name' => $experiment]);

                foreach ($variants as $variant)
                {
                    Variant::firstOrCreate(['name' => $variant, 'experiment' => $experiment, 'experiment_variant' => "$experiment.$variant"]);
                }
            }
        }
    }

}
