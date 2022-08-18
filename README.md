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
8) В формах для выхода из приложения необходимо заменить метод отправки с `GET` на `POST`

9) Для проверки прав доступа и ролей можно использовать `middleware`, которые необходимо добавить в `app/Http/Kerner.php` 
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

1) Для того, чтобы пользователь имел возможность редактировать права доступа, его необходимо добавить в группу со следующими правами:

![image](https://user-images.githubusercontent.com/81566198/185394918-829baf1a-9bec-49f6-8bee-178709218388.png)

2) Для предоставления доступа для редактрования ролей в каком-либо клинте необходимо создать и присвоить пользователю клинтскую роль и с помощью нее настроить правило доступа

2.1) Создание роли

![image](https://user-images.githubusercontent.com/81566198/185395298-6505c129-bee4-43ca-bf0f-4b0db86f213d.png)

2.2) Добавление атрибута для отображения этой роли в списке 

![image](https://user-images.githubusercontent.com/81566198/185395442-362253cd-f774-40e2-b370-60da489e0d5a.png)

2.3) Создание ресурса

![image](https://user-images.githubusercontent.com/81566198/185395713-3856f106-b771-4098-967e-770ea28f1608.png)

2.4) Создание политики проверки роли 

![image](https://user-images.githubusercontent.com/81566198/185395889-c47e8bb6-92b1-4195-8a0e-69ceb1f512c7.png)

2.5) Создание правила доступа к редактированию ресурса

![image](https://user-images.githubusercontent.com/81566198/185396333-f4823ccf-e6b0-4016-9f8d-a1d3a0369c15.png)

2.6) После присвоения пользователю созданной роли он сможет редактировать права доступа в клиенте, к которому роль принадлежит

![image](https://user-images.githubusercontent.com/81566198/185397179-3dca45c7-2c03-4f48-b2f9-ad75805847aa.png)
