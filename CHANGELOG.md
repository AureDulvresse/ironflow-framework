# Changelog

Toutes les modifications notables de ce projet sont documentées ici.

Le format suit [Keep a Changelog](https://keepachangelog.com/fr/1.1.0/) et le projet respecte le [Versionnement sémantique](https://semver.org/lang/fr/).

---

## [Unreleased]

### Removed

- Suppression complète du pattern Facade (`Ironflow\Support\Facade` et les 15 façades statiques concrètes : `Auth`, `Cache`, `Config`, `DB`, `Event`, `Gate`, `Http`, `Log`, `Mail`, `Notification`, `Queue`, `Router`, `Session`, `Validator`, `View`). Aucune n'était appelée depuis du code réel du framework — uniquement des exemples de documentation. Toute dépendance de service doit désormais passer par injection de constructeur.
- `Container::makeInternal()` — jamais utilisée, et son intention documentée ("résoudre sans vérification de module") devenait incompatible avec le nouveau mécanisme d'héritage de contexte.

### Changed

- **L'isolation de modules est maintenant appliquée à l'exécution, pas seulement déclarée.** `Container::make()` propage désormais un contexte de module ambiant à travers toute chaîne de résolution transitive (dépendances de constructeur), y compris pour les singletons déjà mis en cache. Auparavant, `$callerModule` n'était jamais passé nulle part dans le framework — le contrôle d'accès cross-module documenté dans le README n'avait donc jamais d'effet réel.
- `php forge make:controller --module=X` ajoute désormais automatiquement le contrôleur généré aux `providers:` de son module (isolation opt-in par déclaration) et corrige un bug de casse sur `--module` (`blog` → `Blog`).
- `NotificationManager::toMail()` passe désormais explicitement le `Mailer` injecté à la notification (`toMail(object $notifiable, Mailer $mailer)`), remplaçant l'accès via la façade `Mail` supprimée.

### Added

- `Container::resetModuleContext()` — filet de sécurité défensif pour les process long-running (ex. `Queue\Worker`), appelé entre deux jobs pour garantir qu'aucun contexte de module ne fuite d'une résolution avortée vers la suivante.

### À venir

- Cache — interface unifiée, drivers file / Redis
- File d'attente de jobs (queue workers)
- WebSockets / diffusion temps réel
- Documentation complète avec recettes
- Lint statique (`module:lint`) pour détecter les violations de frontière de module au niveau du code source

---

## [1.0.0] — 2026-06-11

Première version publique d'IronFlow. Le noyau est complet et testé (91 assertions, 0 échec).

### Added

#### Conteneur DI

- Résolution automatique par réflexion sur les type hints
- Attribut `#[Injectable]` et `#[Inject('key')]` pour l'injection de scalaires
- Liaison explicite via `bind()`, `singleton()`, `instance()`
- Résolution avec surcharges ponctuelles (`make($class, ['Dep' => $obj])`)

#### Architecture modulaire HMVC

- Attribut `#[Module]` avec déclaration `imports`, `providers`, `exports`
- Graphe de dépendances orienté — tri topologique (Kahn) au boot
- Détection des cycles au démarrage avec message d'erreur lisible
- Isolation : un provider est privé par défaut, exposé seulement via `exports`
- Commandes `module:graph` et `module:graph --check` (vérification CI)

#### Routeur

- Verbes HTTP : `get`, `post`, `put`, `patch`, `delete`, `options`
- Paramètres nommés `{id}`, paramètres optionnels `{slug?}`
- Contraintes regex via `->where('id', '[0-9]+')`
- Routes nommées avec `->name()` et génération d'URL via `route()`
- Groupes avec `prefix`, `middleware`, `namespace`
- Routes ressource RESTful en une ligne (`resource()` → 7 routes)
- Erreurs HTTP structurées : 404 introuvable, 405 méthode non autorisée

#### ORM Active Record

- Modèle de base avec `$table`, `$fillable`, `$hidden`, `$casts`, `$timestamps`
- CRUD complet : `create()`, `find()`, `findOrFail()`, `all()`, `save()`, `delete()`
- `firstOrCreate()`, `updateOrCreate()`
- Suivi de la saleté des attributs via `isDirty()` / `getOriginal()`
- Casts : `bool`, `int`, `float`, `json`, `datetime`, enums PHP 8.1+
- Scopes locaux et globaux
- Soft deletes via trait `SoftDeletes`
- Relations : `hasOne`, `hasMany`, `belongsTo`, `belongsToMany`, `hasManyThrough`
- Eager loading anti-N+1 via `with()`
- Événements de modèle : `creating`, `created`, `updating`, `updated`, `deleting`, `deleted`

#### QueryBuilder

- Interface fluide : `where`, `whereIn`, `whereNotIn`, `orWhere`, `orderBy`, `limit`, `offset`
- Agrégats : `count`, `sum`, `avg`, `min`, `max`
- `select`, `pluck`, `toSql`, `when()`
- `insertGetId`, `update`, `delete`

#### Schema Builder & Migrations

- `Schema::create()`, `table()`, `drop()`, `dropIfExists()`
- Blueprint : `id`, `string`, `text`, `integer`, `bigInteger`, `boolean`, `decimal`, `enum`, `json`, `timestamp`, `timestamps`, `softDeletes`, `foreignId`, `constrained`
- Modificateurs : `nullable`, `default`, `unsigned`, `index`, `unique`
- Migrator : batch tracking, `migrate`, `rollback`, `fresh`

#### CLI Forge

- `serve`, `route:list`, `module:graph [--check]`
- `migrate [--fresh] [--seed] [--rollback]`, `db:seed [--class=]`
- `key:generate`, `jwt:secret`
- `make:module`, `make:controller`, `make:model`, `make:middleware`, `make:command`
- `make:service`, `make:event`, `make:listener`, `make:seeder`, `make:factory`
- `make:form-request`, `make:resource`, `make:component`, `make:policy`
- `down [--message=] [--retry=]`, `up`

#### Authentification

- Auth par session (login, logout, remember me)
- Auth JWT : génération, vérification, refresh tokens
- Hachage bcrypt via `Hash::make()` / `Hash::check()`
- Middlewares `auth` et `guest`

#### RBAC & Sécurité

- `Gate` : définition de capacités globales (`Gate::define()`, `Gate::allows()`, `Gate::denies()`)
- `Policy` : classes de politiques auto-découvertes par convention
- Traits : `HasRole`, `HasPermission`, `HasTwoFactor`, `Auditable`
- Module d'audit : traçabilité complète des actions sensibles
- 6 migrations RBAC : `roles`, `permissions`, `role_user`, `permission_role`, `permission_user`, `audit_logs`
- Middlewares : `HotReloadMiddleware`, `HandleCors`, `SanitizeInput`

#### Validation

- Règles : `required`, `email`, `min`, `max`, `in`, `not_in`, `confirmed`, `nullable`, `integer`, `url`, `boolean`, `regex`
- `FormRequest` avec injection automatique dans les contrôleurs
- Messages d'erreur par champ, méthode `validated()` filtrante

#### Bus d'événements

- `Dispatcher::listen()`, `dispatch()`, `until()`
- Arrêt de propagation via `return false`
- Auto-découverte des listeners via attribut `#[EventListener]`

#### Templates Twig

- Namespaces par module (`@blog/posts/index.html.twig`)
- Fonctions : `route()`, `asset()`, `csrf_field()`, `old()`, `errors()`, `auth_user()`, `is_auth()`
- Filtres : `time_ago`, `markdown`, `slug`, `money`, `truncate`
- View composers

#### Factories & Seeders

- `Factory` abstraite : `definition()`, `count()`, `state()`, `make()`, `create()`
- `FakeGenerator` — wrapper `fakerphp/faker` avec helper `password()` (bcrypt)
- `Seeder` abstraite : `run()`, `call()` pour l'enchaînement

#### Tests

- Suite Pest v3, 91 assertions, 0 échec
- Fixtures PSR-4 conformes dans `tests/Unit/Fixtures/`
- `TestCase` avec helpers HTTP et assertions (`assertStatus`, `assertOk`, `assertRedirect`)
- Trait `RefreshDatabase` pour les tests avec base de données

---

[Unreleased]: https://github.com/ironflow-framework/framework/compare/v1.0.0...HEAD
[1.0.0]: https://github.com/ironflow-framework/framework/releases/tag/v1.0.0
