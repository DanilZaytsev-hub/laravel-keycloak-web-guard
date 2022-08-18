## Настройка

1) В начале необходимо отредактировать composer.json файл 

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
2) После редактирования необходимо выполнить команду `composer update`

3) Затем нужно добавить свойства в `.env`
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

4) В файле `config/auth.php` необходимо заменить `guards.web.driver` на `"keycloak-web"` и `providers.users.driver` на `keycloak-users`
```php
'guards' => [
    'web' => [
        'driver' => 'keycloak-web',
        'provider' => 'users',
    ],

    // ...
],
```
```php
'providers' => [
    'users' => [
        'driver' => 'keycloak-users',
        'model' => App\User::class,
    ],

    // ...
]
```
6) В начале файла `routes/web.php` нужно добавить маршруты
```
Vizir\KeycloakWebGuard\Facades\KeycloakWeb::routes();
```
7) В файле модели User нужно добавить `trait Vizir\KeycloakWebGuard\Traits\KeycloakModelTrait`

```
use Vizir\KeycloakWebGuard\Traits\KeycloakModelTrait as KeycloakModel;

class User extends Authenticatable implements Searchable
{
    use KeycloakModel;
}
```
8) Для проверки прав доступа и ролей можно использовать `middlewares`, которые необходимо добавить в `app/Http/Kerner.php` 
```
'keycloak-can' => \Vizir\KeycloakWebGuard\Middleware\KeycloakCan::class,
'keycloak-has' => \Vizir\KeycloakWebGuard\Middleware\KeycloakHas::class
```
Проверка ролей
```
Route::middleware(['auth', 'keycloak-can:role'])
Route::middleware(['auth', 'keycloak-can:role,client'])
```
Если вторым аргументом передать значение `"realm"`, то роли будут браться из realm'a

Проверка прав доступа
```
Route::middleware(['auth', 'keycloak-has:resource'])
Route::middleware(['auth', 'keycloak-has:resource#scope'])
```

10) Gates 

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

Если необходимо отредактировать параметры библиотеки можно опубликовать конфиг файл: 
```
php artisan vendor:publish  --provider="Vizir\KeycloakWebGuard\KeycloakWebGuardServiceProvider"

```
## Настройка администрирования


