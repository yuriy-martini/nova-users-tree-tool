<?php

namespace SoluzioneSoftware\Nova\Tools\UsersTree\Http\Controllers;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class ToolController extends Controller
{
    public function getData(){
        $ret = [];

        $users = static::
        model()::
        doesntHave(static::parentField())
            ->with(static::queryWith())
            ->get(static::queryColumns());

        foreach ($users as $user){
            $u = $this->loadUser($user);
            array_push($ret, $u);
        }

        return response()->json($ret);
    }

    public function getNodeData($id){

        $user = static::
        model()::
        with(static::queryWith())
            ->findOrFail($id, static::queryColumns());

        $userData = $this->loadUser($user);
        $userData['children'] = $this->loadChildren($user);

        return response()->json($userData);
    }

    public function search(Request $request){
        $word = $request->get('word', '');
        $exclude = $request->get('exclude', []);
        $q = static::model()::query();
        $users = static::applySearch($q, $word)
            ->whereNotIn('id', $exclude)
            ->with(static::queryWith())
            ->take(static::searchLimit())
            ->get(static::queryColumns());

        $tree = $this->makeUsersTree($users);

        return response()->json($tree);
    }

    protected function loadUser($user, $children = []){
        $ret = [
            'id' => $user->id,
            'title' => sprintf('[ID %s] %s', $user->id, $user->{static::titleField()}),
            'email' => $user->email,
            'async' => true,
            'chkDisabled' => true,
            'expanded' => false,
            'link' => config('nova.path', '/nova') . '/resources/' . static::resource() . '/' . $user->id,
            'children' => $children,
        ];

        return $ret;
    }

    protected function loadChildren($parent){
        $ret = [];

        $children = $parent->{static::childrenField()}()
            ->with(static::queryWith())
            ->get(static::queryColumns());

        foreach ($children as $child){
            $u = $this->loadUser($child);
            array_push($ret, $u);
        }

        return $ret;
    }

    /**
     * Apply the search query to the query.
     *
     * @param Builder $query
     * @param string $search
     * @return Builder
     */
    protected static function applySearch(Builder $query, $search)
    {
        $model = $query->getModel();
        $operator = $model->getConnection()->getDriverName() == 'pgsql' ? 'ilike' : 'like';

        return $query
            ->where(function (Builder $query) use ($operator, $model, $search) {
                foreach (static::searchableColumns() as $column) {
                    $query->orWhere($model->qualifyColumn($column), $operator, '%'.$search.'%');
                }

                static::applyRelationSearch($query, $search);
            });
    }

    /**
     * Apply the relationship search query to the given query.
     *
     * @param Builder $query
     * @param string $search
     * @return Builder
     */
    protected static function applyRelationSearch(Builder $query, string $search): Builder
    {
        foreach (static::searchableRelations() as $relation => $columns) {
            $query->orWhereHas($relation, function (Builder $query) use ($columns, $search) {
                $query->where(static::searchQueryApplier($columns, $search));
            });
        }

        return $query;
    }

    /**
     * Returns a Closure that applies a search query for a given columns.
     *
     * @param  array $columns
     * @param  string $search
     * @return Closure
     */
    protected static function searchQueryApplier(array $columns, string $search): Closure
    {
        return function (Builder $query) use ($columns, $search) {
            foreach ($columns as $column) {
                $query->orWhere($column, 'LIKE', '%'.$search.'%');
            }
        };
    }

    protected function makeUsersTree(Collection $users)
    {
        $tree = [];

        foreach ($users as $user)
            $tree = $this->mergeTreeNode($tree, $this->makeUserTree($user));

        return $tree;
    }

    protected function mergeTreeNode(array $tree, $node){
        $presentNode = null;
        $presentIndex = null;
        foreach ($tree as $index => $value) {
            if ($value['id'] === $node['id']){
                $presentNode = $value;
                $presentIndex = $index;
                break;
            }
        }

        if (is_null($presentNode))
            return array_merge($tree, [$node]);

        foreach ($node['children'] as $child){
            $presentNode['children'] = $this->mergeTreeNode($presentNode['children'], $child);
        }

        $tree[$presentIndex] = $presentNode;

        return $tree;
    }

    protected function makeUserTree($user, $children = []){
        $ret = [];

        $father = $user->referrer;

        if (is_null($father))
            return $this->loadUser($user, $children);

        $brothers = $father->{static::childrenField()}()
            ->with(static::queryWith())
            ->get(static::queryColumns());

        foreach ($brothers as $brother){
            $brotherData = $this->loadUser($brother);
            if ($brother->id === $user->id)
                $brotherData['children'] = $children;

            array_push($ret, $brotherData);
        }

        return $this->makeUserTree($father, $ret);
    }

    protected static function searchableColumns()
    {
        return config('nova.users-tree-tool.search-columns');
    }

    protected static function searchableRelations()
    {
        return config('nova.users-tree-tool.search-relations-columns');
    }

    protected static function parentField()
    {
        return config('nova.users-tree-tool.parent-field');
    }

    protected static function childrenField()
    {
        return config('nova.users-tree-tool.children-field');
    }

    protected static function model()
    {
        return config('nova.users-tree-tool.model');
    }

    protected static function searchLimit()
    {
        return config('nova.users-tree-tool.search-limit');
    }

    protected static function titleField()
    {
        return config('nova.users-tree-tool.title-field');
    }

    protected static function resource()
    {
        return config('nova.users-tree-tool.resource');
    }

    protected static function queryWith()
    {
        return config('nova.users-tree-tool.query-with');
    }

    protected static function queryColumns()
    {
        return config('nova.users-tree-tool.query-columns');
    }

}
