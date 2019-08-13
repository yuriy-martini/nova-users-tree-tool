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

      config([
        'nova.users-tree.model' => config()->get('nova.users-tree.model', config('auth.providers.users.model')),
        'nova.users-tree.title-field' => config()->get('nova.users-tree.title-field', 'name'),
        'nova.users-tree.parent-field' => config()->get('nova.users-tree.parent-field', 'parent'),
        'nova.users-tree.children-field' => config()->get('nova.users-tree.children-field', 'children'),
        'nova.users-tree.resource' => config()->get('nova.users-tree.resource', 'users'),
        'nova.users-tree.search-columns' => config()->get('nova.users-tree.search-columns', ['name', 'email']),
        'nova.users-tree.search-limit' => config()->get('nova.users-tree.search-limit', 1),
      ]);
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
        return view('users-tree::navigation');
    }
}
