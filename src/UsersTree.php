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

        $parentField = config()->get('nova.users-tree-tool.parent-field', 'parent');

        config([
            'nova.users-tree-tool.model' => config()->get('nova.users-tree-tool.model', config('auth.providers.users.model')),
            'nova.users-tree-tool.title-format' => config()->get('nova.users-tree-tool.title-format', '{{name}}'),
            'nova.users-tree-tool.parent-field' => $parentField,
            'nova.users-tree-tool.children-field' => config()->get('nova.users-tree-tool.children-field', 'children'),
            'nova.users-tree-tool.resource' => config()->get('nova.users-tree-tool.resource', 'users'),
            'nova.users-tree-tool.search-columns' => config()->get('nova.users-tree-tool.search-columns', ['name']),
            'nova.users-tree-tool.search-relations-columns' => config()->get('nova.users-tree-tool.search-relations-columns', []),
            'nova.users-tree-tool.search-limit' => config()->get('nova.users-tree-tool.search-limit', 1),
            'nova.users-tree-tool.query-columns' => config()->get('nova.users-tree-tool.query-columns', ['id', $parentField . '_id', 'name']),
            'nova.users-tree-tool.query-with' => config()->get('nova.users-tree-tool.query-with', []),
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
