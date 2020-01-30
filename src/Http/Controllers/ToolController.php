<?php

namespace SoluzioneSoftware\Nova\Tools\UsersTree\Http\Controllers;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class ToolController extends Controller
{
    public function getData(Request $request)
    {
        $ret = [];

        if (static::startsFromCurrent($request)){
            /** @var Model $user */
            $user = auth()->user();
            $users = [$user];
        }
        else{
            $users = static::applyTrashed(static::getModelQueryBuilder())
                ->doesntHave(static::parentField())
                ->with(static::queryWith())
                ->get(static::queryColumns());
        }

        foreach ($users as $user){
            $u = $this->loadUser($user);
            array_push($ret, $u);
        }

        return response()->json($ret);
    }

    public function getNodeData(Request $request, $id)
    {
        $user = static::applyTrashed(static::getModelQueryBuilder())
            ->with(static::queryWith())
            ->findOrFail($id, static::queryColumns());

        $userData = $this->loadUser($user);
        $level = (int)$request->get('level', 1);
        $maxLevel = static::maxLevel();
        if (is_null($maxLevel) || $maxLevel >= $level){
            $userData['children'] = $this->loadChildren($user);
        }

        return response()->json($userData);
    }

    public function search(Request $request)
    {
        $word = $request->get('word', '');
        $exclude = $request->get('exclude', []);
        $q = static::applyTrashed(static::model()::query());
        $users = static::applySearch($q, $word)
            ->whereNotIn('id', $exclude)
            ->with(static::queryWith())
            ->take(static::searchLimit())
            ->get(static::queryColumns());

        $tree = $this->makeUsersTree($users);

        return response()->json($tree);
    }

    protected function loadUser(Model $user, $children = [])
    {
        return [
            'id' => $user->getKey(),
            'title' => $this->formatTitle($user),
            'email' => $user->email, // FIXME: refactor this rubbish
            'async' => true,
            'chkDisabled' => true,
            'expanded' => false,
            'link' => config('nova.path') . '/resources/' . static::resource() . '/' . $user->getKey(),
            'trashed' => true,
            'children' => $children,
        ];
    }

    protected function loadChildren($parent){
        $ret = [];

        $children = static::applyTrashed($parent->{static::childrenField()}())
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
     * Apply the search query to the query.
     *
     * @param Builder|HasMany $query
     * @return Builder|HasMany
     */
    protected static function applyTrashed($query)
    {
        return static::withTrashed() ? $query->withTrashed() : $query;
    }

    /**
     * @return Builder
     */
    protected static function getModelQueryBuilder()
    {
        return static::model()::query();
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

    protected function mergeTreeNode(array $tree, $node)
    {
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

    protected function makeUserTree($user, $children = [])
    {
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

    protected function formatTitle(Model $user)
    {
        return
            is_callable(static::titleFormat())
                ? call_user_func(static::titleFormat(), $user, (int)request()->get('level', 1))
                : $user->{static::titleField()};
    }

    protected static function searchableColumns()
    {
        return config('nova.users-tree-tool.search-columns', ['name', 'email']);
    }

    protected static function searchableRelations()
    {
        return config('nova.users-tree-tool.search-relations-columns', []);
    }

    protected static function parentField()
    {
        return config('nova.users-tree-tool.parent-field', 'parent');
    }

    protected static function childrenField()
    {
        return config('nova.users-tree-tool.children-field', 'children');
    }

    protected static function model()
    {
        return config('nova.users-tree-tool.model', config('auth.providers.users.model'));
    }

    protected static function withTrashed()
    {
        return config('nova.users-tree-tool.with-trashed', false);
    }

    protected static function searchLimit()
    {
        return config('nova.users-tree-tool.search-limit', 1);
    }

    protected static function titleFormat()
    {
        return config('nova.users-tree-tool.title-format');
    }

    protected static function resource()
    {
        return config('nova.users-tree-tool.resource', 'users');
    }

    protected static function queryWith()
    {
        return config('nova.users-tree-tool.query-with', []);
    }

    protected static function queryColumns()
    {
        return config('nova.users-tree-tool.query-columns', ['id', 'parent_id', 'name', 'email']);
    }

    private static function startsFromCurrent(Request $request)
    {
        $startFromCurrent = config('nova.users-tree-tool.start-from-current', false);
        return
            is_callable($startFromCurrent)
            ? call_user_func($startFromCurrent, $request)
            : (bool)$startFromCurrent;
    }

    protected static function maxLevel()
    {
        $maxLevel = config('nova.users-tree-tool.max-level');
        if (is_callable($maxLevel)){
            return call_user_func($maxLevel, request());
        }

        return !is_null($maxLevel) ? (int)$maxLevel : null;
    }
}
