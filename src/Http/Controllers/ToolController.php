<?php

namespace SoluzioneSoftware\Nova\Tools\UsersTree\Http\Controllers;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class ToolController extends Controller
{

    public function getData()
    {
        $ret = [];

        $users = static::getModelQueryBuilder()
                       ->doesntHave(static::parentField())
                       ->with(static::queryWith())
                       ->get(static::queryColumns());

        foreach ($users as $user){
            $u = $this->loadUser($user);
            array_push($ret, $u);
        }

        return response()->json($ret);
    }

    public function getNodeData($id)
    {
        $user = static::getModelQueryBuilder()
                      ->with(static::queryWith()) // fixme: refactor this shit!
                      ->findOrFail($id, static::queryColumns());

        $userData = $this->loadUser($user);
        $userData['children'] = $this->loadChildren($user);

        return response()->json($userData);
    }

    public function search(Request $request){
        $word = $request->get('word', '');
        $exclude = $request->get('exclude', []);

        $users = static::applySearch(static::getModelQueryBuilder(), $word)
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
            'link' => config('nova.path') . '/resources/' . static::resource() . '/' . $user->id,
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
        return $query->where(function (Builder $query) use ($search) {

            $model = $query->getModel();
            $operator = $model->getConnection()->getDriverName() == 'pgsql' ? 'ilike' : 'like';

            foreach (static::searchableColumns() as $column) {
                $query->orWhere($model->qualifyColumn($column), $operator, '%'.$search.'%');
            }
        });
    }

    protected static function searchableColumns()
    {
        return config('nova.users-tree.search-columns');
    }

    protected static function parentField()
    {
        return config('nova.users-tree.parent-field');
    }

    protected static function childrenField()
    {
        return config('nova.users-tree.children-field');
    }

    protected static function model()
    {
        return User::class;
    }

    protected static function searchLimit()
    {
        return config('nova.users-tree.search-limit');
    }

    protected static function titleField()
    {
        return config('nova.users-tree.title-field');
    }

    protected static function resource()
    {
        return config('nova.users-tree.resource');
    }

    protected static function queryWith()
    {
//        todo:
        return [];
    }

    protected static function queryColumns()
    {
//        todo:
        return ['*'];
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

    /**
     * @return Builder
     */
    protected static function getModelQueryBuilder()
    {
        $model = static::model();
        return $model::query();
    }
}
