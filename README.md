## Install

Edit composer.json file

composer.json
```
{
    "repositories": [
        {
            "type": "vcs",
            "url": "https://github.com/DanilZaytsev-hub/laravel-keycloak-web-guard.git"
        }
    ],
    "require": {
         "vizir/laravel-keycloak-web-guard": "dev-master"
    }
}
```
Run command
```
composer update
```

If you want to change routes or the default values for Keycloak, publish the config file:

```
php artisan vendor:publish  --provider="Vizir\KeycloakWebGuard\KeycloakWebGuardServiceProvider"

```

## Configuration

Edit .env

.env
```
KEYCLOAK_BASE_URL=
KEYCLOAK_REALM=
KEYCLOAK_REALM_PUBLIC_KEY=
KEYCLOAK_CLIENT_ID=portal
KEYCLOAK_CLIENT_SECRET=
KEYCLOAK_REDIRECT_URI="${APP_URL}"
KEYCLOAK_CACHE_OPENID=true
KEYCLOAK_REDIRECT_LOGOUT="${APP_URL}/"
```
## Laravel Auth

You should add Keycloak Web guard to your `config/auth.php`.

Just add **keycloak-web** to "driver" option on configurations you want.

As my default is web, I add to it:

```php
'guards' => [
    'web' => [
        'driver' => 'keycloak-web',
        'provider' => 'users',
    ],

    // ...
],
```

And change your provider config too:

```php
'providers' => [
    'users' => [
        'driver' => 'keycloak-users',
        'model' => User::class,
    ],

    // ...
]
```

**Note:** if you want use another User Model, check the FAQ *How to implement my Model?*.

## API

We implement the `Illuminate\Contracts\Auth\Guard`. So, all Laravel default methods will be available.

Ex: `Auth::user()` returns the authenticated user.

## Roles

You can check user has a role simply by `Auth::hasRole('role')`;

This method accept two parameters: the first is the role (string or array of strings) and the second is the resource.

If not provided, resource will be the client_id, which is the regular check if you authenticating into this client to your front.

### Keycloak Web Gate

You can use [Laravel Authorization Gate](https://laravel.com/docs/7.x/authorization#gates) to check user against one or more roles (and resources).

For example, in your Controller you can check **one role**:

```php
if (Gate::denies('keycloak-web', 'manage-account')) {
  return abort(403);
}
```

Or **multiple roles**:

```php
if (Gate::denies('keycloak-web', ['manage-account'])) {
  return abort(403);
}
```

And **roles for a resource**:

```php
if (Gate::denies('keycloak-web', 'manage-account', 'another-resource')) {
  return abort(403);
}
```

*This last use is not trivial, but you can extend the Guard to request authentication/authorization to multiple resources. By default, we request only the current client.*

### Keycloak Can Middleware

If you do not want to use the Gate or already implemented middlewares, you can check user against one or more roles using the `keycloak-web-can` Middleware.

Add this to your Controller's `__construct` method:

```php
$this->middleware('keycloak-web-can:manage-something-cool');

// For multiple roles, separate with '|'
$this->middleware('keycloak-web-can:manage-something-cool|manage-something-nice|manage-my-application');
```

This middleware works searching for all roles on default resource (client_id).

You can extend it and register your own middleware on Kernel.php or just use `Auth::hasRole($roles, $resource)` on your Controller.

## FAQ

### How to implement my Model?

We registered a new user provider that you configured on `config/auth.php` called "keycloak-users".

In this same configuration you setted the model. So you can register your own model extending `Vizir\KeycloakWebGuard\Models\KeycloakUser` class and changing this configuration.

You can implement your own [User Provider](https://laravel.com/docs/5.8/authentication#adding-custom-user-providers): just remember to implement the `retrieveByCredentials` method receiving the Keycloak Profile information to retrieve a instance of model.

Eloquent/Database User Provider should work well as they will parse the Keycloak Profile and make a "where" to your database. So your user data must match with Keycloak Profile.

### I cannot find my login form.

We register a `login` route to redirect to Keycloak Server. After login we'll receive and proccess the token to authenticate your user.

There's no login/registration form.

### How can I protect a route?

Just add the `keycloak-web` middleware:

```php
// On RouteServiceProvider.php for example

Route::prefix('admin')
  ->middleware('keycloak-web')
  ->namespace($this->namespace)
  ->group(base_path('routes/web.php'));

// Or with Route facade in another place

Route::group(['middleware' => 'keycloak-web'], function () {
    Route::get('/admin', 'Controller@admin');
});
```

### Where the access/refresh tokens and state are persisted?

On session. We recommend implement the database driver if you have load balance.

### What's a state?

State is a unique and non-guessable string used to mitigate CSRF attacks.

We associate each authentication request about to be initiated with one random state and check on callback. You should do it if you are extending/implementing your own Auth controller.

Use `KeycloakWeb::saveState()` method to save the already generated state to session and `KeycloakWeb::validateState()` to check the current state against the saved one.

### I'm having problems with session (stuck on login loop)

For some reason Laravel can present a problem with EncryptCookies middleware changing the session ID.

In this case, we will always try to login, as tokens cannot be retrieved.

You can remove session_id cookie from encryption:

```php
// On your EncryptCookies middleware

class EncryptCookies extends Middleware
{
    protected $except = [];

    public function __construct(EncrypterContract $encrypter)
    {
        parent::__construct($encrypter);

        /**
         * This will disable in runtime.
         *
         * If you have a "session.cookie" option or don't care about changing the app name
         * (in another environment, for example), you can only add it to "$except" array on top
         */
        $this->disableFor(config('session.cookie'));
    }
}

```

### My client is not public.

If your client is not public, you should provide a `KEYCLOAK_CLIENT_SECRET` on your `.env`.

### How can I override the default Guzzle options?

In some use cases you may need to override the default Guzzle options - likely either to disable SSL verification or to set a Proxy to route all requests through.

Every [http://docs.guzzlephp.org/en/stable/request-options.html](Guzzle Request Option) is supported and is passed directly to the Guzzle Client instance.

Just add the options you would like to `guzzle_options` array on `keycloak-web.php` config file. For example:

```
'guzzle_options' => [
    'verify' => false
]
```

## Developers

* Mário Valney [@mariovalney](https://twitter.com/mariovalney)
* [Vizir Software Studio](https://vizir.com.br)

With contributors on GitHub :heart:
