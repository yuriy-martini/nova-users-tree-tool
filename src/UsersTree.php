<?php

namespace SoluzioneSoftware\Nova\Tools\UsersTree;

use Illuminate\View\View;
use Laravel\Nova\Nova;
use Laravel\Nova\Tool;

class UsersTree extends Tool
{

    /**
     * Create a new element.
     *
     * @param  string|null  $component
     * @return void
     */
    public function __construct($component = null)
    {
        parent::__construct($component);
    }

    /**
     * Perform any tasks that need to happen when the tool is booted.
     *
     * @return void
     */
    public function boot()
    {
        Nova::script('users-tree', __DIR__.'/../dist/js/tool.js');
        Nova::style('users-tree', __DIR__.'/../dist/css/tool.css');
    }

    /**
     * Build the view that renders the navigation links for the tool.
     *
     * @return View
     */
    public function renderNavigation()
    {
        return view('nova-users-tree::navigation');
    }
}
