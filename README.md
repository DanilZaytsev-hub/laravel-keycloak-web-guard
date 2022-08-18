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
        'model' => App\User::class,
    ],

    // ...
]
```

## Roles

You can check user has a role simply by `Auth::hasRole('role')`;

This method accept two parameters: the first is the role (string or array of strings) and the second is the resource.

If not provided, resource will be the client_id, which is the regular check if you authenticating into this client to your front.

## Permissions

You can check user has a permission by `Auth::hasPermissions('resource#scope')`;

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

### Keycloak Middlewares


