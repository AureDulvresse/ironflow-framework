<div align="center">

<img src="https://raw.githubusercontent.com/ironflow-framework/framework/main/.github/assets/logo.svg" alt="IronFlow" width="72" />

# `ironflow-framework/framework`

**Le cœur d'IronFlow — conteneur DI, routeur, ORM, modules HMVC, CLI.**

[![PHP 8.2+](https://img.shields.io/badge/PHP-8.2%2B-777BB4?style=flat-square&logo=php&logoColor=white)](https://php.net)
[![License MIT](https://img.shields.io/badge/license-MIT-22c55e?style=flat-square)](LICENSE)
[![Version](https://img.shields.io/badge/version-0.1.0-6366f1?style=flat-square)]()
[![Tests](https://img.shields.io/badge/tests-passing-22c55e?style=flat-square&logo=github-actions&logoColor=white)](https://github.com/ironflow-framework/framework/actions)

[Installation](#installation) · [Architecture modulaire](#architecture-modulaire) · [Injection de dépendances](#injection-de-dépendances) · [Routing](#routing) · [ORM](#orm) · [CLI](#cli-forge) · [Contribuer](#contribuer)

</div>

---

Ce dépôt contient le **noyau du framework**. Il n'est pas destiné à être cloné directement pour démarrer un projet — utilise [`ironflow/skeleton`](https://github.com/ironflow-framework/skeleton) pour ça. Ce README documente l'architecture interne, les API publiques et les conventions à respecter pour y contribuer.

---

## Installation

Via le skeleton (recommandé) :

```bash
composer create-project ironflow/skeleton mon-app
```

Ou comme dépendance directe dans un projet existant :

```bash
composer require ironflow/framework
```

**Prérequis :** PHP 8.2+, Composer 2+, extensions `pdo`, `mbstring`, `json`.

---

## Architecture modulaire

IronFlow organise le code en **modules HMVC isolés** avec dépendances déclaratives — inspiré de NestJS, porté en PHP idiomatique.

```php
#[Module(
    name: 'blog',
    imports: ['auth'],                   // modules dont celui-ci dépend
    providers: [PostService::class],     // services internes
    exports: [PostService::class],       // API publique exposée aux autres modules
)]
class BlogModule extends BaseModule {}
```

### Règles d'isolation

- Un provider est **privé par défaut** : inaccessible hors du module, sauf s'il est listé dans `exports`.
- Un module ne peut consommer que les providers **exportés** de ses dépendances déclarées dans `imports`.
- Deux niveaux de contrôle, complémentaires :
  - **Au démarrage** : imports manquants et cycles de dépendances (tri topologique) sont détectés et bloquent le boot.
  - **À l'exécution** : toute dépendance de constructeur résolue par le conteneur est vérifiée contre les `exports` du module propriétaire — pas seulement au premier niveau, la vérification survit aux chaînes de résolution transitives (service → service → service d'un autre module). Une classe non listée dans `providers:` n'est pas protégée : l'isolation est **opt-in par déclaration explicite**, jamais déduite d'une convention de nommage. `php forge make:controller --module=` ajoute automatiquement le contrôleur généré aux `providers:` de son module.
  - **Limite connue (v1)** : seule l'injection de constructeur est couverte. Les paramètres injectés directement dans une méthode de contrôleur (`show(Post $post, PostService $service)`) ne bénéficient pas encore de ce contrôle.

### Graphe de dépendances

Au boot, IronFlow construit un graphe orienté de tous les modules, valide l'absence de cycles (tri topologique) et détermine l'ordre d'initialisation. Inspecte-le :

```bash
php forge module:graph           # affichage ASCII
php forge module:graph --check   # retourne une erreur en cas de cycle (utile en CI)
```

### Scaffolding d'un module

```bash
php forge make:module Blog
php forge make:controller PostController --module=Blog --resource
php forge make:model Post --module=Blog --migration --factory
```

---

## Injection de dépendances

Le conteneur résout les dépendances **par réflexion** sur les type hints. Aucune configuration manuelle nécessaire pour les cas courants.

```php
#[Injectable]
class PostService
{
    public function __construct(
        private readonly PostRepository $posts,
        private readonly EventBus $events,
        #[Inject('config.app.name')] private readonly string $appName,
    ) {}
}
```

### Ce que supporte le conteneur

| Fonctionnalité | Exemple |
|---|---|
| Auto-résolution par type hint | `PostRepository $posts` |
| Injection de scalaires nommés | `#[Inject('config.mail.from')]` |
| Injection dans les méthodes de contrôleurs | `public function show(Post $post, Request $request)` |
| Singletons et liaisons explicites | `$container->bind(MailerInterface::class, SmtpMailer::class)` |
| Providers de module | définis dans `providers: [...]` de `#[Module]` |

### Liaison manuelle

```php
// Dans un ServiceProvider ou un Module::register()
$this->container->bind(CacheInterface::class, RedisCache::class);
$this->container->singleton(Config::class, fn() => new Config(base_path('config')));
```

---

## Routing

### Routes basiques

```php
Router::get('/posts/{id}', [PostController::class, 'show'])
    ->name('posts.show')
    ->middleware('auth')
    ->where('id', '[0-9]+');

Router::post('/posts', [PostController::class, 'store'])
    ->middleware(['auth', 'throttle:10,1']);
```

### Groupes et ressources

```php
Router::group(['prefix' => '/api/v1', 'middleware' => ['throttle:60']], function () {
    Router::resource('posts', Api\PostController::class);         // 7 routes RESTful
    Router::resource('comments', Api\CommentController::class)->only(['index', 'store']);
});
```

### Routes nommées dans les templates

```twig
<a href="{{ route('posts.show', {id: post.id}) }}">Lire</a>
```

### Inspection

```bash
php forge route:list
# +---------+----------------------------+------------------+-----------+
# | Method  | URI                        | Name             | Middleware|
# +---------+----------------------------+------------------+-----------+
# | GET     | /posts/{id}                | posts.show       | auth      |
# | POST    | /posts                     | posts.store      | auth,thro…|
# …
```

---

## ORM

Active Record sur `doctrine/dbal`. Pas de QueryBuilder exposé directement — passe par les modèles et les scopes.

### Modèle de base

```php
class Post extends Model
{
    protected string $table = 'posts';

    protected array $casts = [
        'published_at' => 'datetime',
        'status'       => PostStatus::class,   // enum PHP 8.1+
        'metadata'     => 'json',
    ];

    protected array $fillable = ['title', 'body', 'author_id'];

    use SoftDeletes;
}
```

### Requêtes

```php
// Eager loading anti N+1
$posts = Post::with('author', 'comments')
    ->withCount('comments')
    ->published()          // scope
    ->latest()
    ->paginate(15);

// Scope personnalisé
public function scopePublished(QueryBuilder $query): QueryBuilder
{
    return $query->where('status', PostStatus::Published)
                 ->where('published_at', '<=', now());
}
```

### Relations supportées

| Relation | Méthode |
|---|---|
| Un-à-plusieurs | `hasMany(Comment::class)` |
| Plusieurs-à-plusieurs avec pivot | `belongsToMany(Tag::class)->withPivot('weight')` |
| À travers | `hasManyThrough(Comment::class, Post::class)` |
| Inverse | `belongsTo(User::class)` |

### Migrations

```php
Schema::create('posts', function (Blueprint $table) {
    $table->id();
    $table->string('title');
    $table->text('body');
    $table->enum('status', ['draft', 'published'])->default('draft');
    $table->timestamp('published_at')->nullable();
    $table->foreignId('author_id')->constrained('users');
    $table->softDeletes();
    $table->timestamps();
});
```

```bash
php forge migrate
php forge migrate --rollback
php forge migrate --fresh --seed
```

### Événements de modèle

```php
Post::creating(fn(Post $post) => $post->slug = Str::slug($post->title));
Post::deleted(fn(Post $post) => app(CacheManager::class)->forget("post:{$post->id}"));
```

---

## Middlewares

Deux styles au choix — cohérence garantie dans les deux cas.

**Style oignon (recommandé)** :

```php
class AuthMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!$request->session()->has('user')) {
            return redirect()->route('login');
        }
        return $next($request);
    }
}
```

**Style Django (hooks)** :

```php
class LogMiddleware
{
    public function processRequest(Request $request): ?Response  { /* ... */ }
    public function processResponse(Request $request, Response $response): Response { /* ... */ }
}
```

Middlewares globaux définis dans `config/http.php`, alias par route enregistrés dans le router.

---

## Bus d'événements

Découplage inter-modules sans import direct.

```php
// Dans BlogModule : émet
$this->events->dispatch(new PostPublished($post));

// Dans NewsletterModule : écoute — sans importer BlogModule
#[EventListener]
class SendNewsletterOnPublish
{
    public function handle(PostPublished $event): void
    {
        $this->mailer->queue(new NewPostEmail($event->post));
    }
}
```

L'abonnement est auto-détecté via `#[EventListener]` à condition que le module soit dans le graphe de dépendances.

---

## Templates Twig

Namespaces par module :

```twig
{# modules/Blog/templates/posts/index.html.twig #}
{% extends '@app/layouts/main.html.twig' %}

{% block content %}
    {% for post in posts %}
        <a href="{{ route('posts.show', {id: post.id}) }}">{{ post.title }}</a>
        <span>{{ post.published_at | time_ago }}</span>
    {% endfor %}
    {{ paginator(posts) }}
{% endblock %}
```

### Fonctions et filtres disponibles

| Catégorie | Exemples |
|---|---|
| Routing | `route('name', params)`, `current_route()` |
| Assets | `asset('app.css')` — cache-busting automatique |
| Formulaires | `csrf_field()`, `old('field')`, `errors('field')` |
| Filtres | `time_ago`, `markdown`, `slug`, `money`, `truncate` |
| Auth | `auth_user()`, `is_auth()` |

---

## CLI Forge

```bash
php forge list                           # toutes les commandes disponibles
php forge make:module <Name>             # scaffolde un module complet
php forge make:controller <Name> [opts]  # --module, --resource, --api
php forge make:model <Name> [opts]       # --module, --migration, --factory
php forge make:middleware <Name>
php forge make:command <Name>
php forge migrate [--fresh] [--seed] [--rollback]
php forge db:seed [--class=]
php forge route:list
php forge module:graph [--check]
php forge down [--message=] [--retry=]  # mode maintenance
php forge up
php forge serve [--host=] [--port=]
```

### Commandes personnalisées

```php
#[Command(signature: 'blog:seed {count=10} {--fresh}', description: 'Seed blog posts')]
class SeedBlogCommand extends BaseCommand
{
    public function handle(): int
    {
        $count = (int) $this->argument('count');
        // ...
        $this->info("$count posts créés.");
        return self::SUCCESS;
    }
}
```

---

## Ce qui est sur étagère vs fait maison

| Délégué à  | Construit par IronFlow  |
|---|---|
| `symfony/http-foundation` — HTTP bas niveau | Conteneur DI avec attributs PHP 8 |
| `symfony/console` — fondation CLI | Système de modules + graphe de dépendances |
| `twig/twig` — moteur de templates | Routeur fluide avec route naming |
| `doctrine/dbal` — couche SQL | ORM Active Record, migrations, factories |
| `monolog/monolog` — logging | Middlewares, bus d'événements |
| `firebase/php-jwt` — tokens JWT | Extension Twig, scaffolding CLI complet |
| `vlucas/phpdotenv` — variables d'env | Auth session + JWT, validation, CSRF |

Chaque composant externe est **wrappé derrière nos propres interfaces** dans `Ironflow\Ironflow\`. Ton code n'importe jamais `Symfony\Component\HttpFoundation\Request` — seulement `Ironflow\Ironflow\Http\Request`.

---

## Roadmap

- [x] Conteneur DI avec attributs, résolution par réflexion
- [x] Architecture modulaire HMVC avec graphe de dépendances
- [x] Routeur fluide — groupes, ressources, named routes, contraintes
- [x] ORM Active Record — relations, eager loading, scopes, soft deletes
- [x] Migrations et Schema builder
- [x] CLI forge avec scaffolding complet
- [x] Auth session + JWT
- [x] Validation
- [x] CSRF, middlewares globaux et par-route
- [x] Bus d'événements découplé
- [x] Extension Twig maison + view composers
- [ ] Cache — interface unifiée, drivers file/redis
- [ ] File d'attente de jobs (queue)
- [ ] WebSockets / diffusion temps réel
- [ ] Documentation complète avec recettes

---

## Contribuer

```bash
git clone https://github.com/ironflow-framework/framework
git clone https://github.com/ironflow-framework/skeleton

cd skeleton
composer install   # le framework est lié en repository path (symlink local)
php forge serve
```

```bash
# Lancer les tests
vendor/bin/phpunit

# Avec couverture
vendor/bin/phpunit --coverage-html coverage/
```

**Conventions :**

- PSR-12, typages stricts (`declare(strict_types=1)` dans tous les fichiers)
- Chaque feature → test unitaire + test d'intégration
- Pas de breaking change en patch, dépréciation avant suppression en minor
- Issues marquées [`good first issue`](https://github.com/ironflow-framework/framework/issues?q=label%3A%22good+first+issue%22) pour débuter

1. Fork du dépôt concerné
2. `git checkout -b feature/ma-feature`
3. Tests au vert (`vendor/bin/phpunit`)
4. Pull Request vers `main` avec description des changements

---

<div align="center">

*Chaque framework est une théorie du bon code.*  
*IronFlow parie sur la modularité explicite, les attributs PHP 8, et le respect de tes conventions à toi.*

</div>