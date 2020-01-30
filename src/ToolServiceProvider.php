<?php

namespace SoluzioneSoftware\Nova\Tools\UsersTree;

use Illuminate\Http\Request;
use Laravel\Nova\Nova;
use Laravel\Nova\Events\ServingNova;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use SoluzioneSoftware\Nova\Tools\UsersTree\Http\Middleware\Authorize;

class ToolServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'nova-users-tree');

        $this->publishes([
            __DIR__ . '/../config/users-tree-tool.php' => config_path('nova/users-tree-tool.php'),
        ], 'config');

        $this->publishes([
            __DIR__ . '/../resources/views' => resource_path('views/vendor/nova-users-tree'),
        ], 'views');

        $this->app->booted(function () {
            $this->routes();
        });

        Nova::serving(function (ServingNova $event) {
            Nova::provideToScript([
                'authorizedToSearch' => self::authorizedToSearch($event->request),
                'authorizedToOpenUser' => self::authorizedToOpenUser($event->request),
            ]);
        });
    }

    private static function authorizedToSearch(Request $request)
    {
        $callback = config('nova.users-tree-tool.can-search');
        return
            is_callable($callback)
                ? call_user_func($callback, $request)
                : (bool)$callback;
    }

    private static function authorizedToOpenUser(Request $request)
    {
        $callback = config('nova.users-tree-tool.can-open-user');
        return
            is_callable($callback)
                ? call_user_func($callback, $request)
                : (bool)$callback;
    }

    /**
     * Register the tool's routes.
     *
     * @return void
     */
    protected function routes()
    {
        if ($this->app->routesAreCached()) {
            return;
        }

        Route::middleware(['nova', Authorize::class])
            ->prefix('nova-vendor/users-tree')
            ->group(__DIR__.'/../routes/api.php');
    }

    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        //
    }
}
